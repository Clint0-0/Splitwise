<?php
// Router for PHP's built-in server (development/tests only):
//   php -d upload_max_filesize=6M -d post_max_size=8M -S 127.0.0.1:8000 -t public public/router.php
// (PHP's default upload_max_filesize is only 2M; receipts may be up to 5 MB.)
$file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($_SERVER['REQUEST_URI'] !== '/' && is_file($file)) {
    return false; // serve static asset
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
