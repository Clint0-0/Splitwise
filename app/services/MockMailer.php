<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;

/**
 * Mock e-mail (no paid service, nothing is really sent).
 * After each POST request, every notification created since the last run —
 * whether by PHP or by a database trigger — is "e-mailed" to users who ticked
 * "Email me" on their profile.
 *   Output: MAIL_LOG = a file path (default storage/logs/mail.log locally) or
 *           "stderr" (default when APP_ENV=production, i.e. the hosting log).
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
                $out = '';
                foreach ($st->fetchAll() as $n) {
                    $cursor = max($cursor, (int) $n['notification_id']);
                    if ((int) $n['notify_email'] !== 1 || (int) $n['is_active'] !== 1) {
                        continue;
                    }
                    $out .= sprintf("----- %s\nTo: %s <%s>\nSubject: [SplitWise+] %s\n\n%s\n\n",
                        $n['created_at'], self::header($n['full_name']), self::header($n['email']),
                        self::header($n['title']), $n['message']);
                }
                if ($out !== '') {
                    self::write($out);
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

    private static function write(string $text): void
    {
        $target = Env::get('MAIL_LOG', Env::get('APP_ENV') === 'production' ? 'stderr' : '');
        if ($target === 'stderr') {
            file_put_contents('php://stderr', preg_replace('/^/m', '[mock-mail] ', $text));
            return;
        }
        $file = $target !== '' ? $target : BASE_PATH . '/storage/logs/mail.log';
        file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
    }

    /** No header injection: strip CR/LF. */
    private static function header(string $v): string
    {
        return str_replace(["\r", "\n"], ' ', $v);
    }
}
