<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Mock e-mail (no paid service, nothing leaves the machine).
 * After each POST request, every notification created since the last run —
 * whether by PHP or by a database trigger — is "e-mailed" to users who ticked
 * "Email me" on their profile, by appending it to storage/logs/mail.log.
 * A cursor file (last notification_id handled) prevents duplicates; flock
 * serialises concurrent requests.
 */
final class MockMailer
{
    private const BATCH = 500;

    public static function flush(): void
    {
        $dir = BASE_PATH . '/storage/logs';
        $cursorFile = $dir . '/mail.cursor';
        $fh = @fopen($cursorFile, 'c+');
        if ($fh === false) {
            return;
        }
        try {
            flock($fh, LOCK_EX);
            $raw = trim((string) stream_get_contents($fh));
            $pdo = Database::pdo();
            if ($raw === '') {
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
                    file_put_contents($dir . '/mail.log', $out, FILE_APPEND | LOCK_EX);
                }
            }
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, (string) $cursor);
        } catch (\Throwable $t) {
            error_log('MockMailer: ' . $t->getMessage());   // never break the user's request
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /** No header injection: strip CR/LF. */
    private static function header(string $v): string
    {
        return str_replace(["\r", "\n"], ' ', $v);
    }
}
