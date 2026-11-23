<?php
/**
 * Unit tests for money math (no database needed).
 * Usage: php tests/unit/run_unit_tests.php
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\ValidationException;
use App\Helpers\Money;
use App\Services\DebtSimplifier;
use App\Services\SplitCalculator;

$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%s %s%s\n", $ok ? 'PASS' : 'FAIL', $name, $ok || $detail === '' ? '' : "  [$detail]");
}
function throwsValidation(callable $fn, string $needle = ''): bool {
    try { $fn(); return false; }
    catch (ValidationException $e) { return $needle === '' || stripos($e->getMessage(), $needle) !== false; }
}
function rupees(array $paise): array { return array_map([Money::class, 'fromPaise'], $paise); }

$calc = new SplitCalculator();
$simp = new DebtSimplifier();

// ---------------------------------------------------------------- Money
check('Money: "1500.5" -> 150050 paise', Money::parse('1500.5') === 150050);
check('Money: "₹1,23,456.78" accepted', Money::parse('₹1,23,456.78') === 12345678);
check('Money: 3 decimals rejected', throwsValidation(fn() => Money::parse('10.005'), '2 decimals'));
check('Money: negative rejected by default', throwsValidation(fn() => Money::parse('-5')));
check('Money: zero rejected by default', throwsValidation(fn() => Money::parse('0'), 'greater than zero'));
check('Money: text rejected', throwsValidation(fn() => Money::parse("1e3")));
check('Money: SQL-ish input rejected', throwsValidation(fn() => Money::parse("1; DROP TABLE users")));
check('Money: too large rejected', throwsValidation(fn() => Money::parse('99999999999')));
check('Money: fromPaise(-5) = "-0.05"', Money::fromPaise(-5) === '-0.05');
check('Money: round-trip DB decimal', Money::fromPaise(Money::toPaise('-16262.99')) === '-16262.99');
check('inr(): Indian grouping', inr('1234567.5') === '₹12,34,567.50' && inr('-500') === '−₹500.00');

// ---------------------------------------------------------------- equal
$r = $calc->calculate('equal', 150000, 1, [1 => '', 2 => '', 3 => '']);
check('Equal: spec ₹1500 / 3 = 500 each', rupees($r['shares']) === [1 => '500.00', 2 => '500.00', 3 => '500.00']);
$r = $calc->calculate('equal', 100000, 3, [2 => '', 3 => '', 5 => '']);
check('Equal: ₹1000/3 -> payer 333.34 (same as sp_add_equal_expense)',
    rupees($r['shares']) === [2 => '333.33', 3 => '333.34', 5 => '333.33']);
$r = $calc->calculate('equal', 278000, 3, [3 => '', 6 => '', 7 => '']);
check('Equal: ₹2780/3 -> payer, then lowest id get the 2 leftover paise',
    rupees($r['shares']) === [3 => '926.67', 6 => '926.67', 7 => '926.66']);
$r = $calc->calculate('equal', 100, 9, [1 => '', 2 => '', 3 => '']);
check('Equal: payer not a participant -> leftover to lowest ids', $r['shares'] === [1 => 34, 2 => 33, 3 => 33]);
$r = $calc->calculate('equal', 50000, 1, [1 => '']);
check('Equal: single participant gets everything', $r['shares'] === [1 => 50000]);
check('Equal: no participants rejected', throwsValidation(fn() => $calc->calculate('equal', 100, 1, []), 'participant'));

// ---------------------------------------------------------------- percentage
$r = $calc->calculate('percentage', 120000, 1, [1 => '50', 2 => '30', 3 => '20']);
check('Percentage: spec 50/30/20 of ₹1200 = 600/360/240', rupees($r['shares']) === [1 => '600.00', 2 => '360.00', 3 => '240.00']);
check('Percentage: inputs stored as "50.00"', $r['inputs'][1]['percentage'] === '50.00');
$r = $calc->calculate('percentage', 100000, 1, [1 => '33.33', 2 => '33.33', 3 => '33.34']);
check('Percentage: 33.33/33.33/33.34 of ₹1000', rupees($r['shares']) === [1 => '333.30', 2 => '333.30', 3 => '333.40']);
$r = $calc->calculate('percentage', 100, 2, [1 => '33.33', 2 => '33.33', 3 => '33.34']);
check('Percentage: ₹1 sums exactly to 100 paise', array_sum($r['shares']) === 100);
check('Percentage: 99% rejected', throwsValidation(fn() => $calc->calculate('percentage', 1000, 1, [1 => '50', 2 => '49']), '100%'));
check('Percentage: 101% rejected', throwsValidation(fn() => $calc->calculate('percentage', 1000, 1, [1 => '51', 2 => '50']), '100%'));
check('Percentage: 3 decimals rejected', throwsValidation(fn() => $calc->calculate('percentage', 1000, 1, [1 => '50.005', 2 => '49.995'])));

// ---------------------------------------------------------------- exact
$r = $calc->calculate('exact', 240000, 5, [2 => '800', 4 => '800', 5 => '800.00']);
check('Exact: 800+800+800 = 2400', $r['shares'] === [2 => 80000, 4 => 80000, 5 => 80000]);
check('Exact: short total rejected with amount', throwsValidation(fn() => $calc->calculate('exact', 200000, 1, [1 => '500', 2 => '1000']), 'short by ₹500.00'));
check('Exact: over total rejected', throwsValidation(fn() => $calc->calculate('exact', 100000, 1, [1 => '500', 2 => '600']), 'over by ₹100.00'));
$r = $calc->calculate('exact', 50000, 1, [1 => '0', 2 => '500']);
check('Exact: zero share allowed (payer not charged)', $r['shares'] === [1 => 0, 2 => 50000]);

// ---------------------------------------------------------------- shares
$r = $calc->calculate('shares', 1800000, 3, [2 => '1', 3 => '1', 4 => '2', 5 => '1']);
check('Shares: 1:1:2:1 of ₹18000 = 3600/3600/7200/3600', rupees($r['shares']) === [2 => '3600.00', 3 => '3600.00', 4 => '7200.00', 5 => '3600.00']);
$r = $calc->calculate('shares', 100000, 1, [1 => '1.5', 2 => '1', 3 => '0.5']);
check('Shares: fractional units 1.5:1:0.5 of ₹1000', rupees($r['shares']) === [1 => '500.00', 2 => '333.33', 3 => '166.67']);
check('Shares: zero units rejected', throwsValidation(fn() => $calc->calculate('shares', 1000, 1, [1 => '0', 2 => '1']), 'greater than zero'));

// ---------------------------------------------------------------- adjustment
$r = $calc->calculate('adjustment', 600000, 2, [2 => '', 3 => '+600', 4 => '0', 5 => '']);
check('Adjustment: ₹6000, Priya +600 -> 1350/1950/1350/1350', rupees($r['shares']) === [2 => '1350.00', 3 => '1950.00', 4 => '1350.00', 5 => '1350.00']);
$r = $calc->calculate('adjustment', 90000, 1, [1 => '-150', 2 => '', 3 => '']);
check('Adjustment: negative adjustment (discount) works', rupees($r['shares']) === [1 => '200.00', 2 => '350.00', 3 => '350.00']);
check('Adjustment: share pushed below zero rejected', throwsValidation(fn() => $calc->calculate('adjustment', 30000, 1, [1 => '-200', 2 => '', 3 => '']), 'negative'));
check('Adjustment: adjustments above total rejected', throwsValidation(fn() => $calc->calculate('adjustment', 1000, 1, [1 => '20', 2 => '']), 'more than the total'));

// ---------------------------------------------------------------- property test
mt_srand(20261109);
$ok = true;
for ($i = 0; $i < 3000; $i++) {
    $n = mt_rand(1, 9);
    $ids = array_slice(array_unique(array_map(fn() => mt_rand(1, 40), range(1, 20))), 0, $n);
    $total = mt_rand(1, 50_000_000);
    $method = ['equal', 'shares', 'percentage'][mt_rand(0, 2)];
    $inputs = [];
    if ($method === 'percentage') {
        $left = 10000;
        foreach ($ids as $k => $id) {
            $bp = $k === array_key_last($ids) ? $left : mt_rand(0, $left);
            $left -= $bp;
            $inputs[$id] = Money::fromPaise($bp);
        }
    } else {
        foreach ($ids as $id) { $inputs[$id] = $method === 'shares' ? (string) mt_rand(1, 5) : ''; }
    }
    $s = $calc->calculate($method, $total, $ids[0], $inputs)['shares'];
    if (array_sum($s) !== $total || min($s) < 0) { $ok = false; break; }
}
check('Property: 3000 random splits always sum exactly to the total, never negative', $ok);

// ---------------------------------------------------------------- itemized bills (Phase 6)
// Restaurant: Paneer ₹300 (1,2), Biryani ₹250 (2), Lassi ₹90 (1,2,3); bill ₹704 incl. 10% tax (₹64)
$r = $calc->itemized(70400, 1, [
    ['amount' => 30000, 'users' => [1, 2]], ['amount' => 25000, 'users' => [2]], ['amount' => 9000, 'users' => [1, 2, 3]]]);
check('Itemized: subtotals 180 / 430 / 30 from shared and solo items', rupees($r['subtotals']) === [1 => '180.00', 2 => '430.00', 3 => '30.00']);
check('Itemized: ₹64 tax shared by subtotal -> 198 / 473 / 33', rupees($r['shares']) === [1 => '198.00', 2 => '473.00', 3 => '33.00'], json_encode(rupees($r['shares'])));
check('Itemized: per-item parts kept (Lassi 30/30/30)', rupees($r['items'][2]) === [1 => '30.00', 2 => '30.00', 3 => '30.00']);
$r = $calc->itemized(9000, 2, [['amount' => 10000, 'users' => [1, 2, 3]]]);
check('Itemized: ₹10 discount on ₹100 item, payer gets the odd paisa -> 30/30/30', rupees($r['shares']) === [1 => '30.00', 2 => '30.00', 3 => '30.00'] && $r['extra'] === -1000);
$r = $calc->itemized(1000, 2, [['amount' => 1000, 'users' => [1, 2, 3]]]);
check('Itemized: ₹10 between 3, odd paisa to payer first (333/334/333)', $r['shares'] === [1 => 333, 2 => 334, 3 => 333]);
$r = $calc->itemized(20000, 1, [['amount' => 20000, 'users' => [1, 2], 'weights' => [1 => 300, 2 => 100]]]);
check('Itemized: uneven units 3:1 on ₹200 naan -> 150 / 50', rupees($r['shares']) === [1 => '150.00', 2 => '50.00']);
$r = $calc->itemized(10000, 3, [['amount' => 10000, 'users' => [1, 2, 3], 'weights' => [1 => 150, 2 => 100, 3 => 50]]]);
check('Itemized: fractional units 1.5:1:0.5 -> 50 / 33.33 / 16.67', rupees($r['shares']) === [1 => '50.00', 2 => '33.33', 3 => '16.67']);
check('Itemized: zero units rejected', throwsValidation(fn() => $calc->itemized(1000, 1, [['amount' => 1000, 'users' => [1], 'weights' => [1 => 0]]]), 'more than 0'));
check('Itemized: no items rejected', throwsValidation(fn() => $calc->itemized(1000, 1, []), 'at least one item'));
check('Itemized: item with nobody ticked rejected', throwsValidation(fn() => $calc->itemized(1000, 1, [['amount' => 1000, 'users' => []]]), 'Tick who'));
check('Itemized: more than 50 items rejected', throwsValidation(fn() => $calc->itemized(100000, 1,
    array_fill(0, 51, ['amount' => 100, 'users' => [1]])), 'at most 50'));
check('Itemized: calculate() refuses the itemized method', (function () use ($calc) {
    try { $calc->calculate('itemized', 1000, 1, [1 => '']); return false; } catch (LogicException) { return true; } })());
$ok = true;
for ($i = 0; $i < 3000; $i++) {
    $items = [];
    $sum = 0;
    for ($k = 0, $n = mt_rand(1, 12); $k < $n; $k++) {
        $a = mt_rand(1, 500000);
        $sum += $a;
        $us = array_values(array_unique(array_map(fn() => mt_rand(1, 8), range(1, mt_rand(1, 5)))));
        $items[] = ['amount' => $a, 'users' => $us, 'weights' => array_combine($us, array_map(fn() => mt_rand(1, 999999), $us))];
    }
    $total = max(1, $sum + mt_rand(-$sum + 1, intdiv($sum, 3)));   // discount up to almost everything, or tax up to 33%
    $res = $calc->itemized($total, mt_rand(1, 8), $items);
    $itemsOk = true;
    foreach ($items as $k => $it) { $itemsOk = $itemsOk && array_sum($res['items'][$k]) === $it['amount']; }
    if (array_sum($res['shares']) !== $total || min($res['shares']) < 0 || !$itemsOk) { $ok = false; break; }
}
check('Property: 3000 random itemized bills (tax and discounts) sum exactly, items exact, never negative', $ok);

// ---------------------------------------------------------------- UPI links (Phase 6)
use App\Services\UpiLink;
check('UPI: link has payee, 2-decimal amount, INR and a cleaned note',
    UpiLink::build('aarav.k@okicici', 'Aarav Sharma', 123450, 'SplitWise+ Goa  Trip')
    === 'upi://pay?pa=aarav.k@okicici&pn=Aarav%20Sharma&am=1234.50&cu=INR&tn=SplitWise%20Goa%20Trip');
check('UPI: no link for a malformed ID, zero or negative amount',
    UpiLink::build('no-at-sign', 'X', 100, '') === null && UpiLink::build('a.b@ybl', 'X', 0, '') === null
    && UpiLink::build('a.b@ybl', 'X', -5, '') === null);
check('UPI: no link for an ID longer than the 100-character column', UpiLink::build(str_repeat('a', 64) . '@' . str_repeat('b', 64), 'X', 100, '') === null);
check('UPI: & and = in names cannot inject extra parameters', !str_contains(
    (string) UpiLink::build('ab@ybl', 'Tom&am=1', 100, 'x&pa=evil@ybl'), '&am=1&') 
    && substr_count((string) UpiLink::build('ab@ybl', 'Tom&am=1', 100, 'x&pa=evil@ybl'), 'pa=') === 1);

// ---------------------------------------------------------------- bill scanner (Phase 6)
use App\Services\BillScanner;
$envSet = function (array $vars): void { foreach ($vars as $k => $v) { putenv($v === null ? $k : "$k=$v"); } };
$envSet(['SCAN_PROVIDER' => 'mock', 'APP_ENV' => 'production']);
check('Scan: mock provider is refused in production', BillScanner::provider() === 'off');
$envSet(['APP_ENV' => 'local', 'SCAN_PROVIDER' => 'anthropic', 'SCAN_API_KEY' => '']);
check('Scan: anthropic without an API key stays off', BillScanner::provider() === 'off');
$envSet(['SCAN_PROVIDER' => 'nonsense']);
check('Scan: unknown provider = off', !BillScanner::enabled());
$scanner = new BillScanner();
$n = $scanner->normalise("Sure!\n{\"merchant\":\"A\\u0007B\",\"items\":[{\"name\":\"Tea\",\"qty\":1.5,\"amount\":20.005},"
    . "{\"name\":\"  \",\"amount\":5},{\"name\":\"Neg\",\"amount\":-3},{\"name\":\"Str\",\"amount\":\"12.3\"},{\"name\":\"" . str_repeat('x', 200) . "\",\"amount\":1}],\"total\":\"x\"}\nThanks");
check('Scan: reply cleaned (JSON found in prose, blank/negative lines dropped, names clamped, amounts 2-dp, bad total -> null)',
    count($n['items']) === 3 && $n['items'][0] === ['name' => 'Tea', 'qty' => '1.5', 'amount' => '20.01']
    && $n['items'][1]['amount'] === '12.30' && mb_strlen($n['items'][2]['name']) === 80 && $n['total'] === null && $n['merchant'] === 'A B');
check('Scan: no readable items -> friendly error', throwsValidation(fn() => $scanner->normalise('{"items":[]}'), "Couldn't read any items")
    && throwsValidation(fn() => $scanner->normalise('I cannot help'), "Couldn't read any items"));
check('Scan: at most 50 lines kept', count($scanner->normalise(json_encode(['items' => array_fill(0, 80, ['name' => 'i', 'amount' => 1])]))['items']) === 50);
// real HTTP request/response shape against a local fake API (no network)
$port = 18000 + mt_rand(0, 999);
$srv = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/fixtures/fake_anthropic.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pp);
usleep(400000);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$envSet(['SCAN_PROVIDER' => 'anthropic', 'SCAN_API_KEY' => 'test-key-123', 'SCAN_API_URL' => "http://127.0.0.1:$port/v1/messages"]);
$res = null;
try { $res = $scanner->read($png, 'image/png'); } catch (Throwable $e) { $res = $e->getMessage(); }
check('Scan: Messages API request (key header, version, image block) + reply parsed', is_array($res)
    && $res['merchant'] === 'Cafe Test' && count($res['items']) === 2 && $res['items'][1]['amount'] === '90.50' && $res['total'] === '284.03',
    is_string($res) ? $res : '');
$envSet(['SCAN_API_KEY' => 'wrong-key']);
check('Scan: API error -> friendly message, no crash', throwsValidation(fn() => $scanner->read($png, 'image/png'), "Couldn't read the bill right now"));
check('Scan: SCAN_API_URL test override is guarded by !is_production() (static check, no real API call)', (function () {
    $src = file_get_contents(__DIR__ . '/../../app/services/BillScanner.php');
    return str_contains($src, "!is_production() && (\$u = (string) Env::get('SCAN_API_URL'"); })());
proc_terminate($srv);
$envSet(['SCAN_PROVIDER' => null, 'SCAN_API_KEY' => null, 'SCAN_API_URL' => null, 'APP_ENV' => null]);

// ---------------------------------------------------------------- debt simplifier
check('Simplify: cycle A->B->C->A (all nets 0) -> no transfers', $simp->simplify([1 => 0, 2 => 0, 3 => 0]) === []);
check('Simplify: chain A owes B 500, B owes C 500 -> A pays C 500',
    $simp->simplify([1 => -50000, 2 => 0, 3 => 50000]) === [['from' => 1, 'to' => 3, 'amount' => 50000]]);
// Goa sample data after confirmed settlements
$goa = [2 => 999167, 3 => 342916, 4 => -628750, 5 => -713333];
$t = $simp->simplify($goa);
check('Simplify: Goa group -> 3 transfers', count($t) === 3, json_encode($t));
check('Simplify: rejects nets that do not sum to 0', (function () use ($simp) {
    try { $simp->simplify([1 => 10, 2 => -9]); return false; } catch (InvalidArgumentException) { return true; } })());
$ok = true;
for ($i = 0; $i < 2000; $i++) {
    $n = mt_rand(2, 12);
    $nets = [];
    for ($u = 1; $u < $n; $u++) { $nets[$u] = mt_rand(-500000, 500000); }
    $nets[$n] = -array_sum($nets);
    $t = $simp->simplify($nets);
    $after = $nets;
    foreach ($t as $x) {
        if ($x['amount'] <= 0) { $ok = false; }
        $after[$x['from']] += $x['amount'];
        $after[$x['to']]   -= $x['amount'];
    }
    if (array_filter($after) !== [] || count($t) > $n - 1) { $ok = false; break; }
}
check('Property: 2000 random groups — transfers zero every balance, ≤ n−1 transfers', $ok);

// ---------------------------------------------------------------- Env parser + proxy helpers (Phase 5.5)
$envFile = tempnam(sys_get_temp_dir(), 'env');
file_put_contents($envFile, "UT_A=local        # comment after value\nUT_B=\"p#ss word\"  # quoted keeps # and spaces\nUT_C='x'\nUT_D=a#b\n# UT_E=never\nUT_F=\n");
App\Core\Env::load($envFile);
unlink($envFile);
check('Env: unquoted inline " # comment" stripped', App\Core\Env::get('UT_A') === 'local');
check('Env: quoted value keeps # and spaces, trailing comment dropped', App\Core\Env::get('UT_B') === 'p#ss word');
check('Env: single quotes removed', App\Core\Env::get('UT_C') === 'x');
check('Env: # inside a word is kept (a#b)', App\Core\Env::get('UT_D') === 'a#b');
check('Env: commented-out line ignored; empty value is ""', App\Core\Env::get('UT_E') === null && App\Core\Env::get('UT_F') === '');
$_SERVER['REMOTE_ADDR'] = '10.0.0.5';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 203.0.113.9';
putenv('APP_TRUST_PROXY=false');
check('client_ip: X-Forwarded-For ignored unless APP_TRUST_PROXY', client_ip() === '10.0.0.5');
putenv('APP_TRUST_PROXY=true');
check('client_ip: right-most X-Forwarded-For entry behind a trusted proxy', client_ip() === '203.0.113.9');
$_SERVER['HTTP_X_FORWARDED_FOR'] = 'garbage';
check('client_ip: invalid header falls back to REMOTE_ADDR', client_ip() === '10.0.0.5');
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
putenv('APP_ENV=local');
check('is_https: X-Forwarded-Proto honoured only with trusted proxy', is_https() === true);
putenv('APP_TRUST_PROXY=false');
check('is_https: plain local request is not HTTPS', is_https() === false);
putenv('APP_TRUST_PROXY'); putenv('APP_ENV');
$opt = new ReflectionMethod(App\Core\Database::class, 'options');
putenv('DB_SSL=true'); putenv('DB_SSL_CA=/nonexistent/ca.pem');
$closed = false;
try { $opt->invoke(null); } catch (RuntimeException $e) { $closed = str_contains($e->getMessage(), 'DB_SSL'); }
check('Database: DB_SSL=true with unreadable CA fails closed (no silent plain-text connection)', $closed);
$caf = tempnam(sys_get_temp_dir(), 'ca'); putenv("DB_SSL_CA=$caf");
$o = $opt->invoke(null);
check('Database: DB_SSL=true sets CA + verify; timeout from DB_CONNECT_TIMEOUT; not persistent',
    ($o[PDO::MYSQL_ATTR_SSL_CA] ?? '') === $caf && ($o[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] ?? null) === true
    && $o[PDO::ATTR_TIMEOUT] === 5 && empty($o[PDO::ATTR_PERSISTENT]));
unlink($caf); putenv('DB_SSL'); putenv('DB_SSL_CA'); unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_FORWARDED_PROTO']);

echo "\n$pass / " . ($pass + $fail) . " passed\n";
exit($fail === 0 ? 0 : 1);
