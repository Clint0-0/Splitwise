<?php
/** Shared helpers for the Candidate Management lab app (no framework). */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Kolkata');

session_name('cms_lab_sess');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'");

require __DIR__ . '/Validator.php';
require __DIR__ . '/CandidateRepository.php';
require __DIR__ . '/forms.php';

const STATUSES = ['Applied', 'Shortlisted', 'Interviewed', 'Selected', 'Rejected'];

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = require __DIR__ . '/../config/database.php';
        $pdo = new PDO(
            "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4",
            $c['user'], $c['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 5]
        );
        // MySQL CURRENT_TIMESTAMP in the same zone PHP displays (otherwise created_at shows server/UTC time)
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}

function repo(): CandidateRepository
{
    static $r = null;
    return $r ??= new CandidateRepository(db());
}

function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Every POST must carry the session's token. */
function require_post_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        render_error('The form expired or was not sent correctly. Go back, reload the page and try again.');
    }
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

/** A request field as a string ('' when missing or sent as an array, e.g. name[]=x). */
function str_param(string $key, array $src): string
{
    $v = $src[$key] ?? '';
    return is_string($v) ? $v : '';
}

function int_param(string $key, array $src): int
{
    $v = $src[$key] ?? '';
    return is_string($v) && ctype_digit($v) && $v !== '0' ? (int) $v : 0;
}

function nice_date(?string $d): string
{
    return $d ? date('j M Y', strtotime($d)) : '—';
}

function page_header(string $title): void
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . e($title) . ' · Candidate Management</title><link rel="icon" href="assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/css/style.css"></head><body>'
       . '<a class="skip" href="#main">Skip to content</a>'
       . '<header class="top"><div class="wrap"><a class="brand" href="index.php">Candidate Management</a>'
       . '<span class="tag">DBMS Experiment 12 · lab app</span></div></header><main id="main" class="wrap">';
    foreach ($flashes as [$type, $msg]) {
        echo '<div class="alert alert-' . e($type) . '" role="' . ($type === 'error' ? 'alert' : 'status') . '">' . e($msg) . '</div>';
    }
}

function page_footer(): void
{
    echo '</main><footer class="wrap foot">Independent lab application · database <code>splitwise_candidate_lab</code> · not part of SplitWise+</footer></body></html>';
}

function render_error(string $msg, int $code = 0): never
{
    if ($code) {
        http_response_code($code);
    }
    page_header('Error');
    echo '<h1>Something went wrong</h1><p>' . e($msg) . '</p><p><a href="index.php">Back to candidates</a></p>';
    page_footer();
    exit;
}

/** Field error + aria hookup helpers for forms. */
function field_error(array $errors, string $f): string
{
    return isset($errors[$f]) ? '<p class="err" id="' . e($f) . '-err">' . e($errors[$f]) . '</p>' : '';
}

function invalid_attrs(array $errors, string $f): string
{
    return isset($errors[$f]) ? ' aria-invalid="true" aria-describedby="' . e($f) . '-err"' : '';
}

set_exception_handler(function (Throwable $t): void {
    error_log('candidate-lab: ' . $t->getMessage());
    $noDb = $t instanceof PDOException && (str_contains($t->getMessage(), '[2002]') || str_contains($t->getMessage(), '[1045]') || str_contains($t->getMessage(), '[1049]'));
    render_error($noDb
        ? 'Cannot connect to the database. Check that MySQL is running and the settings in config/database.php.'
        : 'Unexpected error. Please try again.', 500);
});
