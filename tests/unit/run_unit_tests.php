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

echo "\n$pass / " . ($pass + $fail) . " passed\n";
exit($fail === 0 ? 0 : 1);
