<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;
use App\Helpers\Money;

/**
 * Turns an expense total + split method + per-participant input into exact
 * per-participant shares in PAISE. Integer arithmetic only.
 *
 * Rounding (largest-remainder method, identical to sp_add_equal_expense):
 *   each share = floor(total × weight / Σweights); the leftover paise (< n)
 *   go one each to the largest fractional remainders; ties -> payer first,
 *   then lower user_id. Σ shares == total, always.
 *
 * Methods and their per-participant input:
 *   equal       –                         total / n
 *   percentage  "33.33" (%)               Σ must be exactly 100.00
 *   exact       "250.00" (₹)              Σ must equal the total
 *   shares      "2" / "1.5" (units > 0)   proportional to units
 *   adjustment  "+150" / "-50" / "" (₹)   (total − Σadj) split equally, then + adj
 *               (this is the spec's "custom" split; see PROJECT_SPECIFICATION A2)
 */
final class SplitCalculator
{
    public const METHODS = ['equal', 'percentage', 'exact', 'shares', 'adjustment'];

    /**
     * @param array<int,string> $inputs participant user_id => raw input ('' for equal)
     * @return array{shares: array<int,int>, inputs: array<int,array{percentage:?string,units:?string,adjustment:?string}>}
     */
    public function calculate(string $method, int $totalPaise, int $payerId, array $inputs): array
    {
        if (!in_array($method, self::METHODS, true)) {
            throw ValidationException::single('split_method', 'Choose a valid split method.');
        }
        if ($totalPaise <= 0) {
            throw ValidationException::single('total_amount', 'Amount must be greater than zero.');
        }
        if ($inputs === []) {
            throw ValidationException::single('participants', 'Select at least one participant.');
        }

        $ids = array_map('intval', array_keys($inputs));
        $stored = array_fill_keys($ids, ['percentage' => null, 'units' => null, 'adjustment' => null]);

        switch ($method) {
            case 'equal':
                $shares = $this->allocate($totalPaise, array_fill_keys($ids, 1), $payerId);
                break;

            case 'percentage':
                $bp = [];
                foreach ($inputs as $uid => $raw) {
                    $bp[(int) $uid] = Money::parseHundredths((string) $raw, "share_$uid", 10000);
                    $stored[(int) $uid]['percentage'] = Money::fromPaise($bp[(int) $uid]);
                }
                $sum = array_sum($bp);
                if ($sum !== 10000) {
                    throw ValidationException::single('participants',
                        'Percentages must add up to exactly 100% (currently ' . Money::fromPaise($sum) . '%).');
                }
                $shares = $this->allocate($totalPaise, $bp, $payerId);
                break;

            case 'exact':
                $shares = [];
                foreach ($inputs as $uid => $raw) {
                    $shares[(int) $uid] = Money::parse((string) $raw === '' ? '0' : (string) $raw, "share_$uid", true);
                }
                $sum = array_sum($shares);
                if ($sum !== $totalPaise) {
                    throw ValidationException::single('participants', sprintf(
                        'Amounts must add up to %s (currently %s, %s %s).',
                        inr(Money::fromPaise($totalPaise)), inr(Money::fromPaise($sum)),
                        $sum < $totalPaise ? 'short by' : 'over by', inr(Money::fromPaise(abs($totalPaise - $sum)))));
                }
                break;

            case 'shares':
                $units = [];
                foreach ($inputs as $uid => $raw) {
                    $u = Money::parseHundredths((string) $raw, "share_$uid", 999999); // DECIMAL(6,2)
                    if ($u <= 0) {
                        throw ValidationException::single("share_$uid", 'Shares must be greater than zero.');
                    }
                    $units[(int) $uid] = $u;
                    $stored[(int) $uid]['units'] = Money::fromPaise($u);
                }
                $shares = $this->allocate($totalPaise, $units, $payerId);
                break;

            case 'adjustment':
                $adj = [];
                foreach ($inputs as $uid => $raw) {
                    $raw = str_replace('+', '', trim((string) $raw));
                    $adj[(int) $uid] = $raw === '' ? 0 : Money::parse($raw, "share_$uid", true, true);
                    $stored[(int) $uid]['adjustment'] = Money::fromPaise($adj[(int) $uid]);
                }
                $base = $totalPaise - array_sum($adj);
                if ($base < 0) {
                    throw ValidationException::single('participants', 'Adjustments add up to more than the total.');
                }
                $equal = $this->allocate($base, array_fill_keys($ids, 1), $payerId);
                $shares = [];
                foreach ($ids as $uid) {
                    $shares[$uid] = $equal[$uid] + $adj[$uid];
                    if ($shares[$uid] < 0) {
                        throw ValidationException::single("share_$uid", 'This adjustment makes the share negative.');
                    }
                }
                break;
        }

        // invariant (defence in depth; the DB checks it again before COMMIT)
        if (array_sum($shares) !== $totalPaise) {
            throw new \LogicException('Split does not add up to the total.');
        }
        ksort($shares);
        return ['shares' => $shares, 'inputs' => $stored];
    }

    /**
     * Largest-remainder allocation of $total paise proportionally to integer weights.
     * @param array<int,int> $weights user_id => weight (> = 0, Σ > 0)
     * @return array<int,int>
     */
    public function allocate(int $total, array $weights, int $payerId): array
    {
        $sumW = array_sum($weights);
        if ($sumW <= 0) {
            throw ValidationException::single('participants', 'At least one participant needs a non-zero share.');
        }
        $shares = [];
        $remainders = [];
        foreach ($weights as $uid => $w) {
            $shares[$uid] = intdiv($total * $w, $sumW);
            $remainders[$uid] = ($total * $w) % $sumW;
        }
        $left = $total - array_sum($shares);
        $order = array_keys($weights);
        usort($order, static fn(int $a, int $b): int =>
            [$remainders[$b], $a === $payerId ? 0 : 1, $a] <=> [$remainders[$a], $b === $payerId ? 0 : 1, $b]);
        for ($i = 0; $i < $left; $i++) {
            $shares[$order[$i]]++;
        }
        return $shares;
    }
}
