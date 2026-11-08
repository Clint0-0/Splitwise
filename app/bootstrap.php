<?php
/**
 * Application bootstrap: autoloader, environment, error handling, session.
 * Included by public/index.php and by the test runners.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// PSR-4 style autoloader: App\Core\Router -> app/core/Router.php
// (namespace folders are lower-case, class file keeps its case).
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $parts = explode('\\', substr($class, 4));
    $file  = array_pop($parts);
    $dir   = implode('/', array_map('strtolower', $parts));
    $path  = BASE_PATH . '/app/' . ($dir !== '' ? $dir . '/' : '') . $file . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require BASE_PATH . '/app/helpers/functions.php';

App\Core\Env::load(BASE_PATH . '/.env');

date_default_timezone_set(App\Core\Env::get('APP_TIMEZONE', 'Asia/Kolkata'));

$debug = App\Core\Env::get('APP_DEBUG', 'false') === 'true';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
error_reporting(E_ALL);
