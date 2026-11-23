<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Session;
use App\Core\ValidationException;
use App\Helpers\Validator;
use App\Models\UserModel;

/**
 * "Forgot password" (Phase 6).
 * - The answer is identical whether or not the e-mail belongs to an account (no account probing).
 * - Token: 32 random bytes in the link; only its SHA-256 is stored; 1 hour; single use;
 *   a new request replaces older tokens of that user.
 * - Throttled per e-mail and per IP in app_kv.
 * - After a reset every session of that user is ended ("log out everywhere").
 * - The link uses APP_URL (required in production): never the request's Host header,
 *   which an attacker could set to steal the token.
 */
final class PasswordResetService
{
    public const TTL = 3600;
    private const MAX_PER_EMAIL = 3;     // per hour
    private const MAX_PER_IP = 20;       // per hour

    public function request(string $email, string $ip): void
    {
        $email = mb_strtolower(trim($email));
        (new Validator())->required('email', $email, 'Email')->email('email', $email)->throw();

        if ($this->tooMany('pr:e:' . hash('sha256', $email), self::MAX_PER_EMAIL)
            || $this->tooMany('pr:i:' . hash('sha256', $ip), self::MAX_PER_IP)) {
            return;   // silently: same answer as success
        }
        $user = (new UserModel())->findByUsernameOrEmail($email);
        if ($user === null || mb_strtolower($user['email']) !== $email) {
            return;
        }
        $base = $this->baseUrl();
        if ($base === null) {
            error_log('password reset: APP_URL is not set, cannot build a safe link');
            return;
        }
        $token = bin2hex(random_bytes(32));
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([(int) $user['user_id']]);
            $pdo->prepare('INSERT INTO password_resets (token_hash, user_id, expires_at) VALUES (?, ?, ?)')
                ->execute([hash('sha256', $token), (int) $user['user_id'], date('Y-m-d H:i:s', time() + self::TTL)]);
            $pdo->commit();
        } catch (\Throwable $t) {
            $pdo->rollBack();
            throw $t;
        }
        $link = $base . '/reset-password?token=' . $token;
        Mailer::send($user['email'], $user['full_name'], 'Reset your password',
            "Hi {$user['full_name']},\n\nSomeone (hopefully you) asked to reset your SplitWise+ password.\n"
            . "Open this link within 1 hour to choose a new one:\n\n$link\n\n"
            . "If you didn't ask for this, ignore this e-mail; your password stays the same.\n", true);
    }

    /** Valid, unused, unexpired token -> user id; otherwise null. */
    public function check(string $token): ?int
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }
        $st = Database::pdo()->prepare('SELECT user_id FROM password_resets
                                          WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?');
        $st->execute([hash('sha256', $token), date('Y-m-d H:i:s')]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function reset(string $token, string $pass, string $confirm): void
    {
        $v = new Validator();
        if (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
            $v->add('password', 'Use at least 8 characters with a letter and a digit.');
        } elseif ($pass !== $confirm) {
            $v->add('password_confirm', 'The passwords do not match.');
        }
        $v->throw();

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // lock the token row: two tabs submitting at once cannot both use it
            $st = $pdo->prepare('SELECT user_id FROM password_resets
                                  WHERE token_hash = ? AND used_at IS NULL AND expires_at > ? FOR UPDATE');
            $st->execute([hash('sha256', $token), date('Y-m-d H:i:s')]);
            $uid = $st->fetchColumn();
            if ($uid === false) {
                throw ValidationException::single('token', 'This reset link has expired or was already used. Ask for a new one.');
            }
            $uid = (int) $uid;
            (new UserModel())->setPassword($uid, password_hash($pass, PASSWORD_DEFAULT));
            $pdo->prepare('UPDATE password_resets SET used_at = ? WHERE token_hash = ?')->execute([date('Y-m-d H:i:s'), hash('sha256', $token)]);
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL')->execute([$uid]);
            $pdo->prepare('DELETE FROM app_sessions WHERE user_id = ?')->execute([$uid]);   // log out everywhere
            $pdo->commit();
        } catch (\Throwable $t) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $t;
        }
        Session::regenerate();
    }

    private function baseUrl(): ?string
    {
        $u = rtrim((string) Env::get('APP_URL', ''), '/');
        if ($u !== '' && preg_match('#^https?://[^/\s]+#', $u)) {
            return $u;
        }
        if (is_production()) {
            return null;
        }
        return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1') . rtrim(url(''), '/');   // local only
    }

    private function tooMany(string $key, int $max): bool
    {
        $pdo = Database::pdo();
        $now = time();
        $pdo->prepare("INSERT INTO app_kv (kv_key, kv_value, expires_at) VALUES (?, '1', ?) AS n
                       ON DUPLICATE KEY UPDATE
                         kv_value   = IF(app_kv.expires_at <= ?, '1', CAST(app_kv.kv_value AS UNSIGNED) + 1),
                         expires_at = IF(app_kv.expires_at <= ?, n.expires_at, app_kv.expires_at)")
            ->execute([$key, $now + 3600, $now, $now]);
        $st = $pdo->prepare('SELECT CAST(kv_value AS UNSIGNED) FROM app_kv WHERE kv_key = ?');
        $st->execute([$key]);
        return (int) $st->fetchColumn() > $max;
    }
}
