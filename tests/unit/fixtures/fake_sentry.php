<?php
// Fake Sentry envelope endpoint for tests/unit (php -S). Saves what it receives.
$out = getenv('SENTRY_OUT') ?: sys_get_temp_dir() . '/fake_sentry.txt';
file_put_contents($out, json_encode(['uri' => $_SERVER['REQUEST_URI'], 'auth' => $_SERVER['HTTP_X_SENTRY_AUTH'] ?? '',
    'type' => $_SERVER['CONTENT_TYPE'] ?? '', 'body' => file_get_contents('php://input')]));
echo '{"id":"x"}';
