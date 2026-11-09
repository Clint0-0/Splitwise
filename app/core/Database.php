<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * One PDO connection per request.
 *  - utf8mb4 (see TESTING_REPORT issue #1)
 *  - real prepared statements (EMULATE_PREPARES off)
 *  - session time zone = app time zone, so CURDATE() in triggers matches PHP
 *  - @app_user_id tells audit triggers who is acting
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Env::get('DB_HOST', '127.0.0.1'),
                Env::get('DB_PORT', '3306'),
                Env::get('DB_NAME', 'splitwise_plus')
            );
            self::$pdo = new PDO($dsn, Env::get('DB_USER', 'splitwise_app'), Env::get('DB_PASSWORD', Env::get('DB_PASS', '')), self::options());
            self::$pdo->exec("SET time_zone = '" . (new \DateTime())->format('P') . "'");
        }
        return self::$pdo;
    }

    /**
     * Connection options. No persistent connections: on serverless containers they
     * pile up idle connections against the provider's limit. One short-lived
     * connection per request, opened only when needed.
     */
    private static function options(): array
    {
        $o = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_TIMEOUT            => max(1, (int) Env::get('DB_CONNECT_TIMEOUT', '5')),
        ];
        // TLS for managed MySQL: DB_SSL=true, optional DB_SSL_CA (path to the provider's CA file)
        if (in_array(strtolower((string) Env::get('DB_SSL', 'false')), ['1', 'true', 'required', 'on'], true)) {
            $ca = (string) Env::get('DB_SSL_CA', '');
            if ($ca === '') {
                foreach (['/etc/ssl/certs/ca-certificates.crt', '/etc/pki/tls/certs/ca-bundle.crt', '/etc/ssl/cert.pem'] as $bundle) {
                    if (is_readable($bundle)) {
                        $ca = $bundle;
                        break;
                    }
                }
            }
            if ($ca !== '') {
                $o[PDO::MYSQL_ATTR_SSL_CA] = $ca;
            }
            $o[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = strtolower((string) Env::get('DB_SSL_VERIFY', 'true')) !== 'false';
        }
        return $o;
    }

    public static function setActor(?int $userId): void
    {
        self::pdo()->prepare('SET @app_user_id = ?')->execute([$userId]);
    }

    /**
     * Business-rule message raised by a trigger/procedure via SIGNAL SQLSTATE '45000',
     * or null for any other database error (which must not be shown to users).
     */
    public static function signalMessage(\PDOException $e): ?string
    {
        $info = $e->errorInfo ?? [];
        return ($info[0] ?? '') === '45000' ? (string) ($info[2] ?? '') : null;
    }

    /** Run $fn inside a transaction; roll back and rethrow on any error. */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
