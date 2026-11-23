<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Notification e-mails. After each POST request, every notification created since
 * the last run — whether by PHP or by a database trigger — is e-mailed to users who
 * ticked "Email me" on their profile, through Mailer (MAIL_DRIVER=log writes them to
 * MAIL_LOG / stderr as before; MAIL_DRIVER=smtp really sends them — Phase 6).
 *   Cursor: last handled notification_id, kept in MySQL (app_kv) and guarded by
 *           a MySQL named lock, so it is shared by all app instances.
 */
final class MockMailer
{
    private const BATCH = 500;
    private const KEY = 'mail:cursor';

    public static function flush(): void
    {
        $pdo = null;
        $locked = false;
        try {
            $pdo = Database::pdo();
            $locked = (int) $pdo->query("SELECT GET_LOCK('splitwise_mock_mail', 2)")->fetchColumn() === 1;
            if (!$locked) {
                return;   // another request is mailing right now; it will pick these up
            }
            $st = $pdo->prepare('SELECT kv_value FROM app_kv WHERE kv_key = ?');
            $st->execute([self::KEY]);
            $raw = $st->fetchColumn();
            if ($raw === false) {
                // first run: start from "now", do not mail the whole history
                $cursor = (int) $pdo->query('SELECT COALESCE(MAX(notification_id), 0) FROM notifications')->fetchColumn();
            } else {
                $cursor = (int) $raw;
                $st = $pdo->prepare('SELECT n.notification_id, n.title, n.message, n.created_at,
                                            u.email, u.full_name, u.notify_email, u.is_active
                                       FROM notifications n JOIN users u ON u.user_id = n.user_id
                                      WHERE n.notification_id > ? ORDER BY n.notification_id LIMIT ' . self::BATCH);
                $st->execute([$cursor]);
                foreach ($st->fetchAll() as $n) {
                    $cursor = max($cursor, (int) $n['notification_id']);
                    if ((int) $n['notify_email'] !== 1 || (int) $n['is_active'] !== 1) {
                        continue;
                    }
                    Mailer::send($n['email'], $n['full_name'], $n['title'], $n['message']);
                }
            }
            $pdo->prepare('INSERT INTO app_kv (kv_key, kv_value, expires_at) VALUES (?, ?, NULL) AS n
                           ON DUPLICATE KEY UPDATE kv_value = n.kv_value')->execute([self::KEY, (string) $cursor]);
        } catch (\Throwable $t) {
            error_log('MockMailer: ' . $t->getMessage());   // never break the user's request
        } finally {
            if ($locked && $pdo) {
                $pdo->query("SELECT RELEASE_LOCK('splitwise_mock_mail')");
            }
        }
    }
}
