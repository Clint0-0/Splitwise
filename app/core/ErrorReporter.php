<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Error monitoring (Phase 6).
 * Every unhandled error gets a short reference id that is shown to the user and written
 * to the log as ONE JSON line (easy to search in the hosting log viewer):
 *   {"level":"error","ref":"a1b2c3d4","type":"PDOException","msg":"...","file":"...","line":42,"path":"/groups/3","method":"POST"}
 * Optionally the same event is sent to Sentry (or any Sentry-compatible service, e.g.
 * GlitchTip) when SENTRY_DSN is set. Never sent: request bodies, cookies, headers,
 * passwords, query strings (they may hold reset tokens) or function arguments.
 */
final class ErrorReporter
{
    public static function report(\Throwable $t): string
    {
        $ref = bin2hex(random_bytes(4));
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        $event = [
            'level'  => 'error',
            'ref'    => $ref,
            'type'   => get_class($t),
            'msg'    => mb_substr(self::scrub($t->getMessage()), 0, 500),
            'file'   => str_replace(BASE_PATH, '', $t->getFile()),
            'line'   => $t->getLine(),
            'path'   => $path,
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
            'time'   => date('c'),
        ];
        error_log(json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $dsn = (string) Env::get('SENTRY_DSN', '');
        if ($dsn !== '' && function_exists('curl_init')) {   // never let monitoring itself crash the error page
            try {
                self::sentry($dsn, $t, $event);
            } catch (\Throwable) {
            }
        }
        return $ref;
    }

    /** Remove things that look like secrets (passwords in DSNs, long hex tokens). */
    public static function scrub(string $s): string
    {
        $s = (string) preg_replace('#(://[^:/\s]+:)[^@\s]+@#', '$1***@', $s);
        return (string) preg_replace('/\b[0-9a-f]{32,}\b/i', '***', $s);
    }

    /** Sentry envelope API: POST https://HOST/api/PROJECT/envelope/ ; 3 s timeout, failures ignored. */
    private static function sentry(string $dsn, \Throwable $t, array $e): void
    {
        $p = parse_url($dsn);
        if (!isset($p['scheme'], $p['host'], $p['user'], $p['path']) || !in_array($p['scheme'], ['https', 'http'], true)) {
            return;
        }
        $project = trim($p['path'], '/');
        $url = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') . "/api/$project/envelope/";
        $id = bin2hex(random_bytes(16));
        $frames = [];
        foreach (array_reverse(array_slice($t->getTrace(), 0, 20)) as $f) {
            $frames[] = ['filename' => str_replace(BASE_PATH, '', (string) ($f['file'] ?? '')), 'lineno' => (int) ($f['line'] ?? 0),
                         'function' => ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '')];
        }
        $event = [
            'event_id' => $id, 'timestamp' => time(), 'platform' => 'php', 'level' => 'error',
            'environment' => (string) Env::get('APP_ENV', 'local'), 'server_name' => 'splitwise',
            'tags' => ['ref' => $e['ref'], 'path' => $e['path'], 'method' => $e['method']],
            'exception' => ['values' => [['type' => $e['type'], 'value' => $e['msg'], 'stacktrace' => ['frames' => $frames]]]],
        ];
        $body = json_encode(['event_id' => $id, 'sent_at' => date('c')]) . "\n" . json_encode(['type' => 'event']) . "\n" . json_encode($event);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 3,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-sentry-envelope',
                'X-Sentry-Auth: Sentry sentry_version=7, sentry_client=splitwise-plus/1.0, sentry_key=' . $p['user']],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
