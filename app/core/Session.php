<?php
declare(strict_types=1);

namespace App\Core;

/** Session wrapper: hardened cookie, flash messages, old input, CSRF token. */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name(Env::get('SESSION_NAME', 'splitwise_sess'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** New session id (prevents session fixation) — call on login/logout. */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
        unset($_SESSION['_csrf']);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // ---- flash (shown once on the next page) -----------------------------
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function takeFlash(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }

    public static function flashInput(array $input, array $errors): void
    {
        unset($input['password'], $input['password_confirm'], $input['current_password'], $input['_csrf']);
        $_SESSION['_old'] = $input;
        $_SESSION['_errors'] = $errors;
    }

    public static function takeOld(): array
    {
        $data = ['old' => $_SESSION['_old'] ?? [], 'errors' => $_SESSION['_errors'] ?? []];
        unset($_SESSION['_old'], $_SESSION['_errors']);
        return $data;
    }

    // ---- CSRF -------------------------------------------------------------
    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }
}
