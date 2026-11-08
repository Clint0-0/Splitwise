<?php
declare(strict_types=1);

namespace App\Models;

final class UserModel extends Model
{
    private const PUBLIC_COLS = 'user_id, username, email, full_name, phone, avatar_path, role, is_active,
                                 notify_email, last_login_at, created_at';

    public function find(int $id): ?array
    {
        return $this->one('SELECT ' . self::PUBLIC_COLS . ' FROM users WHERE user_id = ?', [$id]);
    }

    /** For login only: includes password_hash. */
    public function findForLogin(string $login): ?array
    {
        return $this->one('SELECT user_id, username, email, full_name, role, is_active, password_hash
                             FROM users WHERE email = ? OR username = ?', [mb_strtolower($login), $login]);
    }

    public function findByUsernameOrEmail(string $value): ?array
    {
        return $this->one('SELECT ' . self::PUBLIC_COLS . ' FROM users
                            WHERE (username = ? OR email = ?) AND is_active = 1', [$value, mb_strtolower($value)]);
    }

    public function exists(string $column, string $value, ?int $exceptId = null): bool
    {
        $column = $column === 'email' ? 'email' : 'username'; // whitelist
        return (bool) $this->value("SELECT 1 FROM users WHERE $column = ? AND user_id <> ?", [$value, $exceptId ?? 0]);
    }

    public function create(string $username, string $email, string $hash, string $fullName, ?string $phone): int
    {
        return $this->insert('INSERT INTO users (username, email, password_hash, full_name, phone)
                              VALUES (?, ?, ?, ?, ?)', [$username, $email, $hash, $fullName, $phone]);
    }

    public function updateProfile(int $id, string $fullName, string $email, ?string $phone, bool $notifyEmail): void
    {
        $this->run('UPDATE users SET full_name = ?, email = ?, phone = ?, notify_email = ? WHERE user_id = ?',
            [$fullName, $email, $phone, $notifyEmail ? 1 : 0, $id]);
    }

    public function passwordHash(int $id): ?string
    {
        return $this->value('SELECT password_hash FROM users WHERE user_id = ?', [$id]);
    }

    public function setPassword(int $id, string $hash): void
    {
        $this->run('UPDATE users SET password_hash = ? WHERE user_id = ?', [$hash, $id]);
    }

    public function touchLogin(int $id): void
    {
        $this->run('UPDATE users SET last_login_at = NOW() WHERE user_id = ?', [$id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $this->run('UPDATE users SET is_active = ? WHERE user_id = ?', [$active ? 1 : 0, $id]);
    }

    /** Admin list with simple search + pagination. */
    public function search(string $q, int $limit, int $offset): array
    {
        $like = '%' . $q . '%';
        return $this->all('SELECT ' . self::PUBLIC_COLS . ',
                                  (SELECT COUNT(*) FROM group_members gm WHERE gm.user_id = users.user_id AND gm.status = \'active\') AS group_count
                             FROM users
                            WHERE full_name LIKE ? OR username LIKE ? OR email LIKE ?
                            ORDER BY full_name
                            LIMIT ? OFFSET ?', [$like, $like, $like, $limit, $offset]);
    }

    public function countSearch(string $q): int
    {
        $like = '%' . $q . '%';
        return (int) $this->value('SELECT COUNT(*) FROM users WHERE full_name LIKE ? OR username LIKE ? OR email LIKE ?',
            [$like, $like, $like]);
    }
}
