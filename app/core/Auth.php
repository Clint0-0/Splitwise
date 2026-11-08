<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\UserModel;

/** Who is logged in. The user row is loaded once per request. */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('user_id');
            if (is_int($id)) {
                $user = (new UserModel())->find($id);
                // deactivated by an admin -> logged out on next request
                self::$user = ($user && (int) $user['is_active'] === 1) ? $user : null;
                if (self::$user === null) {
                    Session::forget('user_id');
                }
            }
            Database::setActor(self::$user ? (int) self::$user['user_id'] : null);
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['user_id'] : null;
    }

    /** Logged-in user id, or 403 (use after the auth middleware). */
    public static function requireId(): int
    {
        return self::id() ?? throw new HttpException(403);
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['user_id']);
        self::$user = $user;
        self::$loaded = true;
        Database::setActor((int) $user['user_id']);
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
    }
}
