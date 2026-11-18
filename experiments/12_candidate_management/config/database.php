<?php
/**
 * Database settings for the Candidate Management lab app.
 * Values come from environment variables; config/local.php (git-ignored) may
 * override them on a lab PC. Never commit a real password.
 */
declare(strict_types=1);

$cfg = [
    'host'     => getenv('CANDIDATE_DB_HOST') ?: '127.0.0.1',
    'port'     => getenv('CANDIDATE_DB_PORT') ?: '3306',
    'name'     => getenv('CANDIDATE_DB_NAME') ?: 'splitwise_candidate_lab',
    'user'     => getenv('CANDIDATE_DB_USER') ?: 'candidate_app',
    'password' => getenv('CANDIDATE_DB_PASSWORD') !== false ? (string) getenv('CANDIDATE_DB_PASSWORD') : '',
];
if (is_file(__DIR__ . '/local.php')) {
    $cfg = array_merge($cfg, (array) require __DIR__ . '/local.php');
}
return $cfg;
