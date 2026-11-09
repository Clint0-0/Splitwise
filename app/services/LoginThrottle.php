<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Brute-force protection that does NOT live in the session (a session can be thrown away).
 * Counters are small files in storage/throttle/ (not web-accessible), updated under flock.
 *   - 5 failed logins per (account, IP) in 15 minutes  -> that pair is locked
 *   - 100 failed logins per IP in 15 minutes          -> the IP is locked (many students can share one Wi-Fi address)
 * Keying the account counter by IP too means an attacker cannot lock a real user
 * out from somewhere else (no account-lockout denial of service).
 */
final class LoginThrottle
{
    public const MAX_PER_ACCOUNT = 5;
    public const MAX_PER_IP = 100;   // high enough for a shared hostel/college Wi-Fi address
    public const WINDOW = 900;

    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? BASE_PATH . '/storage/throttle';
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0770, true);
        }
    }

    public function tooMany(string $login, string $ip): bool
    {
        return $this->count($this->accountKey($login, $ip)) >= self::MAX_PER_ACCOUNT
            || $this->count('ip|' . $ip) >= self::MAX_PER_IP;
    }

    public function hit(string $login, string $ip): void
    {
        $this->bump($this->accountKey($login, $ip));
        $this->bump('ip|' . $ip);
    }

    public function clear(string $login, string $ip): void
    {
        $file = $this->file($this->accountKey($login, $ip));
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function accountKey(string $login, string $ip): string
    {
        return 'acct|' . mb_strtolower(trim($login)) . '|' . $ip;
    }

    private function file(string $key): string
    {
        return $this->dir . '/' . hash('sha256', $key) . '.json';
    }

    private function count(string $key): int
    {
        $data = @json_decode((string) @file_get_contents($this->file($key)), true);
        if (!is_array($data) || time() - (int) ($data['first'] ?? 0) > self::WINDOW) {
            return 0;
        }
        return (int) ($data['count'] ?? 0);
    }

    private function bump(string $key): void
    {
        $fh = @fopen($this->file($key), 'c+');
        if ($fh === false) {
            return;   // fail open on a storage problem rather than locking everyone out
        }
        flock($fh, LOCK_EX);
        $data = json_decode((string) stream_get_contents($fh), true);
        if (!is_array($data) || time() - (int) ($data['first'] ?? 0) > self::WINDOW) {
            $data = ['count' => 0, 'first' => time()];
        }
        $data['count']++;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, (string) json_encode($data));
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}
