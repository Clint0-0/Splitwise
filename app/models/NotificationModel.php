<?php
declare(strict_types=1);

namespace App\Models;

final class NotificationModel extends Model
{
    public function unreadCount(int $userId): int
    {
        return (int) $this->value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    public function forUser(int $userId, int $limit, int $offset): array
    {
        return $this->all('SELECT * FROM notifications WHERE user_id = ?
                            ORDER BY created_at DESC, notification_id DESC LIMIT ? OFFSET ?', [$userId, $limit, $offset]);
    }

    public function countForUser(int $userId): int
    {
        return (int) $this->value('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$userId]);
    }

    public function find(int $id, int $userId): ?array
    {
        return $this->one('SELECT * FROM notifications WHERE notification_id = ? AND user_id = ?', [$id, $userId]);
    }

    public function markRead(int $id, int $userId): void
    {
        $this->run('UPDATE notifications SET is_read = 1, read_at = NOW()
                     WHERE notification_id = ? AND user_id = ? AND is_read = 0', [$id, $userId]);
    }

    public function markAllRead(int $userId): void
    {
        $this->run('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    public function create(int $userId, string $type, string $title, string $message, ?string $entityType, ?int $entityId): void
    {
        $this->run('INSERT INTO notifications (user_id, type, title, message, entity_type, entity_id)
                    VALUES (?, ?, ?, ?, ?, ?)', [$userId, $type, $title, $message, $entityType, $entityId]);
    }

    /** Has this user already been warned about this budget (since a date)? */
    public function budgetWarned(int $userId, int $budgetId, string $since): bool
    {
        return (bool) $this->value("SELECT 1 FROM notifications
                                     WHERE user_id = ? AND type = 'budget_warning' AND entity_type = 'budget'
                                       AND entity_id = ? AND created_at >= ? LIMIT 1", [$userId, $budgetId, $since]);
    }
}
