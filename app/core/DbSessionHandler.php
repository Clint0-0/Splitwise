<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * PHP sessions stored in MySQL (table app_sessions) so that every app instance
 * (e.g. Vercel containers) sees the same session. Replaces file sessions.
 *
 *  - read() ignores rows idle longer than the lifetime (server-side expiry)
 *  - validateId() lets session.use_strict_mode reject unknown/expired IDs
 *    (no session fixation with attacker-chosen IDs)
 *  - updateTimestamp() keeps lazy_write cheap (no payload rewrite)
 *  - gc() also removes expired app_kv rows
 * Note: unlike file sessions there is no per-session lock; two requests from the
 * same user at the same instant can overwrite each other's session changes.
 * SplitWise+ stores only the user id, CSRF token and flash messages there,
 * so the worst case is a lost flash message.
 */
final class DbSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    public function __construct(private PDO $db, private int $lifetime)
    {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $st = $this->db->prepare('SELECT payload FROM app_sessions WHERE session_id = ? AND last_activity >= ?');
        $st->execute([$id, time() - $this->lifetime]);
        $v = $st->fetchColumn();
        return $v === false ? '' : (string) $v;
    }

    public function write(string $id, string $data): bool
    {
        $this->db->prepare('INSERT INTO app_sessions (session_id, payload, last_activity) VALUES (?, ?, ?) AS n
                            ON DUPLICATE KEY UPDATE payload = n.payload, last_activity = n.last_activity')
            ->execute([$id, $data, time()]);
        return true;
    }

    public function destroy(string $id): bool
    {
        $this->db->prepare('DELETE FROM app_sessions WHERE session_id = ?')->execute([$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $st = $this->db->prepare('DELETE FROM app_sessions WHERE last_activity < ?');
        $st->execute([time() - $this->lifetime]);
        $this->db->prepare('DELETE FROM app_kv WHERE expires_at IS NOT NULL AND expires_at < ?')->execute([time()]);
        return $st->rowCount();
    }

    public function validateId(string $id): bool
    {
        $st = $this->db->prepare('SELECT 1 FROM app_sessions WHERE session_id = ? AND last_activity >= ?');
        $st->execute([$id, time() - $this->lifetime]);
        return $st->fetchColumn() !== false;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $this->db->prepare('UPDATE app_sessions SET last_activity = ? WHERE session_id = ?')->execute([time(), $id]);
        return true;
    }
}
