<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Core\ValidationException;
use App\Helpers\Validator;
use App\Models\UserModel;

final class AuthService
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 900;

    public function __construct(private UserModel $users = new UserModel())
    {
    }

    public function register(array $in): int
    {
        $username = trim((string) ($in['username'] ?? ''));
        $email    = mb_strtolower(trim((string) ($in['email'] ?? '')));
        $name     = trim((string) ($in['full_name'] ?? ''));
        $phone    = trim((string) ($in['phone'] ?? ''));
        $pass     = (string) ($in['password'] ?? '');
        $confirm  = (string) ($in['password_confirm'] ?? '');

        $v = (new Validator())
            ->required('full_name', $name, 'Full name')->maxLen('full_name', $name, 100, 'Full name')
            ->required('username', $username, 'Username')
            ->regex('username', $username, '/^[A-Za-z0-9_.]{3,30}$/', 'Username: 3–30 letters, digits, _ or .')
            ->required('email', $email, 'Email')->email('email', $email)->maxLen('email', $email, 100, 'Email')
            ->regex('phone', $phone, '/^\+?[0-9]{10,14}$/', 'Phone: 10–14 digits.');
        $this->validatePassword($v, $pass, $confirm);

        // uniqueness is checked for every well-formed field, even if other fields have errors
        if (!$v->has('username') && $this->users->exists('username', $username)) {
            $v->add('username', 'That username is taken.');
        }
        if (!$v->has('email') && $this->users->exists('email', $email)) {
            $v->add('email', 'An account with this email already exists.');
        }
        $v->throw();

        try {
            $id = $this->users->create($username, $email, password_hash($pass, PASSWORD_DEFAULT), $name, $phone ?: null);
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {   // lost a race against another sign-up: UNIQUE key fired
                throw ValidationException::single('username', 'That username or email was just taken. Try another.');
            }
            throw $e;
        }
        Auth::login($this->users->find($id));
        return $id;
    }

    public function login(string $login, string $password): void
    {
        $lock = Session::get('_login_lock', ['count' => 0, 'until' => 0]);
        if ($lock['until'] > time()) {
            throw ValidationException::single('login', 'Too many failed attempts. Try again in a few minutes.');
        }

        $user = $login !== '' ? $this->users->findForLogin($login) : null;
        // Same message for "no such user" and "wrong password" (no account enumeration).
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $lock['count']++;
            if ($lock['count'] >= self::MAX_ATTEMPTS) {
                $lock = ['count' => 0, 'until' => time() + self::LOCK_SECONDS];
            }
            Session::set('_login_lock', $lock);
            throw ValidationException::single('login', 'Incorrect username/email or password.');
        }
        if ((int) $user['is_active'] !== 1) {
            throw ValidationException::single('login', 'This account has been deactivated.');
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->users->setPassword((int) $user['user_id'], password_hash($password, PASSWORD_DEFAULT));
        }
        Session::forget('_login_lock');
        $this->users->touchLogin((int) $user['user_id']);
        Auth::login($this->users->find((int) $user['user_id']));
    }

    public function updateProfile(int $userId, array $in): void
    {
        $name  = trim((string) ($in['full_name'] ?? ''));
        $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
        $phone = trim((string) ($in['phone'] ?? ''));
        $v = (new Validator())
            ->required('full_name', $name, 'Full name')->maxLen('full_name', $name, 100, 'Full name')
            ->required('email', $email, 'Email')->email('email', $email)
            ->regex('phone', $phone, '/^\+?[0-9]{10,14}$/', 'Phone: 10–14 digits.');
        if (!$v->fails() && $this->users->exists('email', $email, $userId)) {
            $v->add('email', 'Another account uses this email.');
        }
        $v->throw();
        $this->users->updateProfile($userId, $name, $email, $phone ?: null, !empty($in['notify_email']));
    }

    public function changePassword(int $userId, array $in): void
    {
        $v = new Validator();
        if (!password_verify((string) ($in['current_password'] ?? ''), (string) $this->users->passwordHash($userId))) {
            $v->add('current_password', 'Current password is incorrect.');
        }
        $this->validatePassword($v, (string) ($in['password'] ?? ''), (string) ($in['password_confirm'] ?? ''));
        $v->throw();
        $this->users->setPassword($userId, password_hash((string) $in['password'], PASSWORD_DEFAULT));
        Session::regenerate();
    }

    private function validatePassword(Validator $v, string $pass, string $confirm): void
    {
        if (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
            $v->add('password', 'Password: at least 8 characters with a letter and a digit.');
        } elseif (strlen($pass) > 72) {
            $v->add('password', 'Password is too long (max 72 characters).');
        }
        if ($pass !== $confirm) {
            $v->add('password_confirm', 'Passwords do not match.');
        }
    }
}
