<?php
/** Front controller: every request enters here. */
declare(strict_types=1);

use App\Core\HttpException;
use App\Core\RedirectException;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

require dirname(__DIR__) . '/app/bootstrap.php';

// Security headers (every response)
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; "
    . "script-src 'self'; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; "
    . "form-action 'self'; frame-ancestors 'none'");
if (is_https() && is_production()) {
    header('Strict-Transport-Security: max-age=31536000');
}

// Health checks: no session, no login, no details. /healthz = process is up; /readyz = database reachable.
$path = (new Request())->path;   // base-path aware (works under /Splitwise/public on XAMPP too)
if ($path === '/healthz' || $path === '/readyz') {
    App\Core\Health::respond($path);
    return;
}

$router = new Router();
require BASE_PATH . '/config/routes.php';

$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
try {
    Session::start();   // inside try: if MySQL is down the user gets a clean error page, not a stack trace
    // A POST bigger than post_max_size arrives with $_POST/$_FILES EMPTY (PHP drops it),
    // which would otherwise look like a missing CSRF token ("session expired").
    if ($isPost && $_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > ini_bytes((string) ini_get('post_max_size'))) {
        Session::flash('danger', 'The upload is too large (the server accepts up to '
            . ini_get('post_max_size') . 'B per form; receipts must be at most 5 MB).');
        throw new RedirectException(same_origin_referer() ?? url('/dashboard'));
    }
    $router->dispatch(new Request());
} catch (RedirectException $r) {
    header('Location: ' . $r->location, true, 303);
} catch (HttpException $h) {
    http_response_code($h->status);
    if (str_starts_with((new Request())->path, '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $h->getMessage()]);
    } else {
        echo View::render('errors/error', ['status' => $h->status, 'message' => $h->getMessage()], 'layouts/guest');
    }
} catch (Throwable $t) {
    error_log((string) $t);
    http_response_code(500);
    $debug = !is_production() && App\Core\Env::get('APP_DEBUG', 'false') === 'true';
    echo View::render('errors/error', [
        'status'  => 500,
        'message' => $debug ? $t->getMessage() : 'Something went wrong. Please try again.',
    ], 'layouts/guest');
} finally {
    if ($isPost && session_status() === PHP_SESSION_ACTIVE) {
        App\Services\MockMailer::flush();
    }
}
