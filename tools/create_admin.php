<?php
/**
 * Create the first administrator of a production database built with
 *   php database/build_hosted.php --db=NAME --no-sample
 * (that build has no accounts at all, so nobody can log in with a published password).
 *
 * Usage (uses the same DB_* settings as the app, from the environment or .env):
 *   php tools/create_admin.php --username=owner --email=owner@example.com --name="Site Owner"
 * The password is read from ADMIN_PASSWORD if set, otherwise typed at a hidden prompt.
 * It is never printed, logged or passed on the command line.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$o = getopt('', ['username:', 'email:', 'name:']);
$username = trim((string) ($o['username'] ?? ''));
$email = mb_strtolower(trim((string) ($o['email'] ?? '')));
$name = trim((string) ($o['name'] ?? ''));
$fail = static function (string $m): never { fwrite(STDERR, $m . "\n"); exit(2); };

if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) { $fail('--username: 3–30 letters, digits, _ or .'); }
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) { $fail('--email: a valid e-mail address'); }
if ($name === '' || mb_strlen($name) > 100) { $fail('--name: 1–100 characters'); }

$pass = getenv('ADMIN_PASSWORD');
if ($pass === false || $pass === '') {
    fwrite(STDOUT, 'Password for the new admin (hidden): ');
    $tty = DIRECTORY_SEPARATOR === '/' && function_exists('posix_isatty') && posix_isatty(STDIN);
    if ($tty) { shell_exec('stty -echo'); }
    $pass = rtrim((string) fgets(STDIN), "\r\n");
    if ($tty) { shell_exec('stty echo'); }
    fwrite(STDOUT, "\n");
}
if (strlen($pass) < 12 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
    $fail('Admin password: at least 12 characters with a letter and a digit.');
}

$pdo = Database::pdo();
$st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
$st->execute([$username, $email]);
if ((int) $st->fetchColumn() > 0) { $fail('That username or e-mail already exists. Nothing changed.'); }

$pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, 'admin')")
    ->execute([$username, $email, password_hash($pass, PASSWORD_DEFAULT), $name]);
fwrite(STDOUT, "Admin '$username' created (user id " . $pdo->lastInsertId() . "). Log in at /login.\n");
