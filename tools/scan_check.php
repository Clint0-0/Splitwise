<?php
/**
 * Bill-scan self-test: checks the PHP setup and sends ONE small test bill to the provider
 * configured in .env (SCAN_PROVIDER / SCAN_API_KEY / SCAN_MODEL), then prints what came back
 * or the exact reason it failed. The API key is never printed (only whether one is set).
 *
 * Windows / XAMPP:
 *   C:\xampp\php\php.exe tools\scan_check.php
 *   C:\xampp\php\php.exe tools\scan_check.php path\to\bill.jpg     (try a real photo)
 * Note: the command-line PHP reads the same php.ini as Apache on XAMPP, so a fix here
 * (curl, gd, curl.cainfo) also fixes the website after restarting Apache.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Env;
use App\Core\ValidationException;
use App\Services\BillScanner;

$ok = static fn(bool $b): string => $b ? 'OK ' : 'NO ';
$key = trim((string) Env::get('SCAN_API_KEY', ''));
echo "SplitWise+ bill-scan check\n";
echo '  PHP ' . PHP_VERSION . '  (' . PHP_BINARY . ")\n";
echo '  php.ini: ' . (php_ini_loaded_file() ?: '(none)') . "\n";
echo '  ' . $ok(function_exists('curl_init')) . "curl extension\n";
echo '  ' . $ok(extension_loaded('openssl')) . "openssl extension\n";
echo '  ' . $ok(function_exists('imagecreatefromstring')) . "gd extension (needed only to shrink photos over ~3 MB)\n";
echo '  SCAN_PROVIDER = ' . (Env::get('SCAN_PROVIDER', 'off') ?: 'off') . '  ->  active provider: ' . BillScanner::provider() . "\n";
echo '  SCAN_API_KEY  = ' . ($key === '' ? '(empty)' : 'set, ' . strlen($key) . ' characters'
    . (str_starts_with($key, 'gsk_') ? ', starts with gsk_' : ', does NOT start with gsk_ (Groq keys do)')
    . (preg_match('/["\'\s]/', $key) ? ', CONTAINS QUOTES OR SPACES: remove them' : '')) . "\n";
echo '  SCAN_MODEL    = ' . (Env::get('SCAN_MODEL', '') ?: '(default)') . "\n";
$ca = BillScanner::caBundle();
echo '  CA bundle     = ' . ($ca ?? (ini_get('curl.cainfo') ?: ini_get('openssl.cafile') ?: '(system default)')) . "\n\n";

if (BillScanner::provider() === 'off') {
    echo "Scanning is OFF. In .env set SCAN_PROVIDER=groq and SCAN_API_KEY=<your key>, then run this again.\n";
    exit(1);
}

if (isset($argv[1])) {
    $bytes = (string) @file_get_contents($argv[1]);
    $mime = $bytes !== '' ? ((new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '') : '';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        echo "Cannot read {$argv[1]} as a JPG/PNG/WebP photo.\n";
        exit(1);
    }
} elseif (function_exists('imagecreatetruecolor')) {
    // a tiny printed bill: 2 items + total
    $im = imagecreatetruecolor(360, 220);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    $ink = imagecolorallocate($im, 0, 0, 0);
    foreach (['TEST CAFE', 'Date: 2026-11-20', '', 'Masala Dosa   2   180.00', 'Filter Coffee 2    60.00', '', 'TOTAL              240.00'] as $n => $line) {
        imagestring($im, 5, 20, 15 + $n * 26, $line, $ink);
    }
    ob_start(); imagepng($im); $bytes = (string) ob_get_clean(); imagedestroy($im);
    $mime = 'image/png';
} else {
    echo "gd is off, so no test picture can be drawn: pass a photo, e.g.  php tools\\scan_check.php bill.jpg\n";
    exit(1);
}

if (!is_production()) {
    putenv('APP_DEBUG=true');   // show the exact failure reason below
}
echo 'Sending ' . round(strlen($bytes) / 1024) . " KB to the provider...\n";
$t = microtime(true);
try {
    $r = (new BillScanner())->read($bytes, $mime);
} catch (ValidationException $e) {
    echo "\nFAILED: " . implode(' ', $e->errors) . "\n";
    exit(1);
} catch (Throwable $e) {
    echo "\nFAILED: " . get_class($e) . ': ' . $e->getMessage() . "\n";
    exit(1);
}
printf("\nWORKS (%.1f s). Read %d line(s):\n", microtime(true) - $t, count($r['items']));
foreach ($r['items'] as $it) {
    echo "  - {$it['name']}  x{$it['qty']}  {$it['amount']}" . ($it['uncertain'] ? '  (hard to read)' : '') . "\n";
}
echo '  total: ' . ($r['total'] ?? '(not read)') . '   date: ' . ($r['date'] ?? '(not read)') . "\n";
foreach ($r['warnings'] as $w) {
    echo "  warning: $w\n";
}
