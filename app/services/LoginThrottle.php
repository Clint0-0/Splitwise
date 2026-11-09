<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Brute-force protection shared by every app instance (counters in MySQL table app_kv,
 * not in the session — a session can be thrown away — and not in local files, which
 * are per-container on Vercel).
 *   - 5 failed logins per (account, IP) in 15 minutes   -> that pair is locked
 *   - 100 failed logins per IP in 15 minutes            -> the IP is locked (many students can share one Wi-Fi address)
 * Keying the account counter by IP too means an attacker cannot lock a real user
 * out from somewhere else (no account-lockout denial of service).
 * Each increment is one atomic INSERT … ON DUPLICATE KEY UPDATE (safe under concurrency).
 */
final class LoginThrottle
{
    public const MAX_PER_ACCOUNT = 5;
    public const MAX_PER_IP = 100;
    public const WINDOW = 900;

    public function tooMany(string $login, string $ip): bool
    {
        return $this->count($this->accountKey($login, $ip)) >= self::MAX_PER_ACCOUNT
            || $this->count($this->ipKey($ip)) >= self::MAX_PER_IP;
    }

    public function hit(string $login, string $ip): void
    {
        $this->bump($this->accountKey($login, $ip));
        $this->bump($this->ipKey($ip));
    }

    public function clear(string $login, string $ip): void
    {
        Database::pdo()->prepare('DELETE FROM app_kv WHERE kv_key = ?')->execute([$this->accountKey($login, $ip)]);
    }

    private function accountKey(string $login, string $ip): string
    {
        return 'thr:a:' . hash('sha256', mb_strtolower(trim($login)) . '|' . $ip);
    }

    private function ipKey(string $ip): string
    {
        return 'thr:i:' . hash('sha256', $ip);
    }

    private function count(string $key): int
    {
        $st = Database::pdo()->prepare('SELECT kv_value FROM app_kv WHERE kv_key = ? AND expires_at > ?');
        $st->execute([$key, time()]);
        return (int) $st->fetchColumn();
    }

    private function bump(string $key): void
    {
        $now = time();
        // expired window -> restart at 1; otherwise +1 (assignments run left to right,
        // so kv_value is decided while expires_at still holds the old value)
        Database::pdo()->prepare('INSERT INTO app_kv (kv_key, kv_value, expires_at) VALUES (?, \'1\', ?) AS n
            ON DUPLICATE KEY UPDATE
              kv_value   = IF(app_kv.expires_at <= ?, \'1\', CAST(app_kv.kv_value AS UNSIGNED) + 1),
              expires_at = IF(app_kv.expires_at <= ?, n.expires_at, app_kv.expires_at)')
            ->execute([$key, $now + self::WINDOW, $now, $now]);
    }
}
