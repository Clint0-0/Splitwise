<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Health endpoints for the hosting platform / monitoring.
 *   GET /healthz  -> 200 {"status":"ok"}                 (PHP is running; does not touch the DB)
 *   GET /readyz   -> 200 {"status":"ready"} or 503 {"status":"unavailable"}  (SELECT 1 on MySQL)
 * The responses never contain versions, host names, error text or timings;
 * a failure reason is only written to the server log.
 */
final class Health
{
    public static function respond(string $path): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'GET' && $method !== 'HEAD') {
            http_response_code(405);
            header('Allow: GET, HEAD');
            echo '{"status":"method_not_allowed"}';
            return;
        }
        if ($path === '/healthz') {
            echo '{"status":"ok"}';
            return;
        }
        try {
            Database::pdo()->query('SELECT 1')->fetchColumn();
            echo '{"status":"ready"}';
        } catch (\Throwable $t) {
            error_log('readyz: database not reachable: ' . $t->getMessage());
            http_response_code(503);
            echo '{"status":"unavailable"}';
        }
    }
}
