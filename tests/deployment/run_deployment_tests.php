<?php
/** Deployment-readiness checks; started by tests/deployment/run.sh (see there). */
declare(strict_types=1);

require __DIR__ . '/../functional/lib.php';

$ROOT = getenv('ROOT');
$A = 'http://127.0.0.1:8771';   // instance 1 (production mode, behind "proxy")
$B = 'http://127.0.0.1:8772';   // instance 2 (same DB) — shared sessions
$BAD = 'http://127.0.0.1:8773'; // wrong DB password
$demo = getenv('DEMOPW');
$sock = '/var/run/mysqld/mysqld.sock';
$db = new PDO((file_exists($sock) && !getenv('DB_ROOT_TCP') ? "mysql:unix_socket=$sock" : 'mysql:host=127.0.0.1;port=3306')
        . ';dbname=' . getenv('DBN') . ';charset=utf8mb4', getenv('DB_ROOT_USER') ?: 'root', getenv('DB_ROOT_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$val = function (string $sql, array $p = []) use ($db) { $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); };

/** raw request with custom headers; returns [status, headers, body] */
function req(string $url, string $method = 'GET', array $headers = [], ?string $body = null, ?string $cookie = null): array
{
    $ch = curl_init($url);
    $h = $headers;
    if ($cookie !== null) { $h[] = "Cookie: $cookie"; }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => 20] + ($body !== null ? [CURLOPT_POSTFIELDS => $body] : []));
    $raw = (string) curl_exec($ch);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return [(int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), substr($raw, 0, $hs), substr($raw, $hs)];
}
function hdr(string $headers, string $name): string
{
    return preg_match('/^' . preg_quote($name, '/') . ':\s*(.*?)\r?$/mi', $headers, $m) ? $m[1] : '';
}
/** login through a proxy IP; returns [status, location, cookie] */
function proxLogin(string $base, string $login, string $pw, string $ip): array
{
    $fwd = ["X-Forwarded-For: $ip", 'X-Forwarded-Proto: https'];
    [, $h, $b] = req("$base/login", 'GET', $fwd);
    preg_match('/splitwise_sess=([^;]+)/', $h, $c);
    preg_match('/name="_csrf" value="([0-9a-f]+)"/', $b, $t);
    $cookie = 'splitwise_sess=' . ($c[1] ?? '');
    [$s, $h2] = req("$base/login", 'POST', $fwd, http_build_query(['_csrf' => $t[1] ?? '', 'login' => $login, 'password' => $pw]), $cookie);
    if (preg_match('/splitwise_sess=([^;]+)/', $h2, $c2)) { $cookie = 'splitwise_sess=' . $c2[1]; }
    return [$s, hdr($h2, 'Location'), $cookie];
}

// ---------------------------------------------------------------- static configuration
$docker = (string) file_get_contents("$ROOT/Dockerfile");
$caddy = (string) file_get_contents("$ROOT/Caddyfile");
$ignore = (string) file_get_contents("$ROOT/.dockerignore");
check('C1', 'Dockerfile: FrankenPHP base, pdo_mysql, production php.ini, non-root, no secrets',
    str_contains($docker, 'FROM dunglas/frankenphp') && str_contains($docker, 'pdo_mysql') && str_contains($docker, 'php.ini-production')
    && preg_match('/^USER www-data/m', $docker) === 1
    && preg_match('/DB_PASS(WORD)?\s*=|COPY\s+\.env|ARG\s+\w*(PASS|SECRET|KEY)/i', $docker) === 0);
check('C2', 'Dockerfile copies only app/, config/, public/, Caddyfile, deploy/php.ini (no tests, docs, database, .git)',
    preg_match_all('/^COPY\s+(\S+)/m', $docker, $m) > 0 && array_diff($m[1], ['app/', 'config/', 'public/', 'Caddyfile', 'deploy/php.ini']) === []);
check('C3', 'Caddyfile: web root is /app/public only, php_server, admin off, dotfiles + router.php blocked, listens on $PORT',
    str_contains($caddy, 'root * /app/public') && str_contains($caddy, 'php_server') && str_contains($caddy, 'admin off')
    && str_contains($caddy, '/router.php') && str_contains($caddy, '@blocked path */.*') && str_contains($caddy, '{$PORT:8080}'));
check('C4', '.dockerignore excludes .env, .git, tests, database, docs, build',
    count(array_intersect(['.env', '.git', 'tests/', 'database/', 'docs/', 'build/'], array_map('trim', explode("\n", $ignore)))) === 6);
$gi = (string) file_get_contents("$ROOT/.gitignore");
check('C5', '.gitignore excludes .env, .env.* and build/ (generated hosted scripts)',
    preg_match('/^\.env$/m', $gi) && preg_match('/^\.env\.\*$/m', $gi) && preg_match('/^build\/$/m', $gi));
$ex = (string) file_get_contents("$ROOT/.env.example");
check('C6a', '.env.example has no trailing comments on value lines (Docker --env-file keeps them as part of the value)',
    preg_match('/^[A-Z_]+=[^\n]*[ \t]#/m', $ex) === 0);
check('C6', '.env.example documents every production variable with placeholder values only',
    array_filter(['APP_ENV', 'APP_TRUST_PROXY', 'DB_PASSWORD', 'DB_SSL', 'DB_SSL_CA', 'DB_CONNECT_TIMEOUT', 'SESSION_DRIVER', 'SESSION_LIFETIME', 'MAIL_LOG'],
        fn($k) => !preg_match('/^' . $k . '=/m', $ex)) === [] && preg_match('/^DB_PASSWORD=change_me\b/m', $ex) === 1);
$tracked = shell_exec('cd ' . escapeshellarg($ROOT) . ' && git ls-files 2>/dev/null') ?? '';
check('C7', 'No .env / key / generated hosted script tracked by git',
    preg_match('/^(\.env|.*\.pem|.*\.key|build\/.*)$/m', $tracked) === 0);

// ---------------------------------------------------------------- hosted-style database
check('H1', 'Hosted import: 16 tables, triggers, procedures in a database with a different name',
    (int) $val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'") === 16
    && (int) $val('SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE()') > 0
    && (int) $val("SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema=DATABASE() AND routine_type='PROCEDURE'") > 0);
check('H2', 'Hosted import: sample users no longer use the published demo password',
    !password_verify('Password@123', (string) $val("SELECT password_hash FROM users WHERE username='priya'"))
    && password_verify($demo, (string) $val("SELECT password_hash FROM users WHERE username='priya'")));
check('H3', 'Hosted app user requires TLS (REQUIRE SSL)',
    (string) $val("SELECT ssl_type FROM mysql.user WHERE user='sw_hosted' AND host='%'") === 'ANY');
$h4 = false;
try { new PDO('mysql:host=127.0.0.1;dbname=' . getenv('DBN'), 'sw_hosted', getenv('APPPW'), [PDO::ATTR_TIMEOUT => 3]); }
catch (PDOException $e) { $h4 = true; }
check('H4', 'Hosted app user cannot connect WITHOUT TLS', $h4);

// ---------------------------------------------------------------- production instance
$fwd = ['X-Forwarded-For: 203.0.113.10', 'X-Forwarded-Proto: https'];
[$s, $h, $b] = req("$A/healthz", 'GET', $fwd);
check('E1', '/healthz 200 {"status":"ok"} in production', $s === 200 && trim($b) === '{"status":"ok"}');
[$s, $h, $b] = req("$A/readyz", 'GET', $fwd);
check('E2', '/readyz 200 {"status":"ready"} over TLS to MySQL', $s === 200 && trim($b) === '{"status":"ready"}');
[$s, $h, $b] = req("$A/login", 'GET', $fwd);
check('E3', 'HSTS sent in production', str_contains(hdr($h, 'Strict-Transport-Security'), 'max-age='));
check('E4', 'Session cookie: Secure + HttpOnly + SameSite=Lax behind the HTTPS proxy',
    (bool) preg_match('/^Set-Cookie: splitwise_sess=[^\r\n]*;\s*secure[^\r\n]*HttpOnly[^\r\n]*SameSite=Lax/mi', $h));
check('E5', 'No X-Powered-By / PHP version leaked', hdr($h, 'X-Powered-By') === '' || !preg_match('/\d/', hdr($h, 'X-Powered-By')));
[$st, $loc, $cookie] = proxLogin($A, 'priya', $demo, '203.0.113.10');
check('E6', 'Login works in production mode with the hosted demo password', $st === 303 && str_ends_with($loc, '/dashboard'));
[$s, $h, $b] = req("$A/dashboard", 'GET', $fwd, null, $cookie);
check('E7', 'Dashboard renders for the logged-in user (instance 1)', $s === 200 && str_contains($b, 'Net balance'));
[$s, $h, $b] = req("$B/dashboard", 'GET', $fwd, null, $cookie);
check('E8', 'Same session cookie works on instance 2 (sessions shared through MySQL)', $s === 200 && str_contains($b, 'Net balance'));
[$st, $loc] = proxLogin($A, 'priya', 'Password@123', '203.0.113.10');
check('E9', 'Published demo password Password@123 does not work on the hosted copy', !str_ends_with($loc, '/dashboard'));
for ($i = 0; $i < 5; $i++) { proxLogin($A, 'kabir', 'wrong' . $i, '198.51.100.7'); }
[$st, $loc] = proxLogin($B, 'kabir', $demo, '198.51.100.7');
[$st2, $loc2] = proxLogin($B, 'kabir', $demo, '198.51.100.8');
check('E10', 'Throttle uses the real visitor IP (X-Forwarded-For) and is shared by both instances',
    !str_ends_with($loc, '/dashboard') && $st2 === 303 && str_ends_with($loc2, '/dashboard'));
for ($i = 0; $i < 5; $i++) { proxLogin($A, 'meera', 'wrong' . $i, "10.9.9.$i, 192.0.2.44"); }   // forged left entries, real edge IP last
[, $loc3] = proxLogin($A, 'meera', $demo, '7.7.7.7, 192.0.2.44');
check('E10b', 'Forged X-Forwarded-For entries cannot dodge the throttle (right-most = address the edge saw)', !str_ends_with($loc3, '/dashboard'));
$err = (string) @file_get_contents(getenv('LOGDIR') . '/s1.log') . (string) @file_get_contents(getenv('LOGDIR') . '/s2.log');
check('E11', 'Server logs contain no passwords', !str_contains($err, $demo) && !str_contains($err, (string) getenv('APPPW')));
check('E12', 'Production writes no local log files (logs go to stderr)', !is_file("$ROOT/storage/logs/mail.log") || filemtime("$ROOT/storage/logs/mail.log") < time() - 120);

// ---------------------------------------------------------------- failure behaviour
[$s, $h, $b] = req("$BAD/readyz");
check('F1', '/readyz 503 {"status":"unavailable"} when the DB rejects the login — no reason leaked',
    $s === 503 && trim($b) === '{"status":"unavailable"}');
[$s, $h, $b] = req("$BAD/healthz");
check('F2', '/healthz still 200 when the DB is down (liveness ≠ readiness)', $s === 200);
[$s, $h, $b] = req("$BAD/login");
check('F3', 'DB down: generic 500 page even with APP_DEBUG=true (production forces debug off), no SQLSTATE / user / host',
    $s === 500 && str_contains($b, 'Something went wrong') && !preg_match('/SQLSTATE|sw_hosted|Access denied|127\.0\.0\.1/', $b));
$bad = (string) @file_get_contents(getenv('LOGDIR') . '/s3.log');
check('F4', 'DB failure is logged server-side without the password', str_contains($bad, 'Access denied') && !str_contains($bad, 'wrong-password'));

finish();
