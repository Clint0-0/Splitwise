<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Suggests transfers that settle a group, from net balances (paise).
 * Greedy: repeatedly the largest debtor pays the largest creditor
 * min(debt, credit). Produces at most n−1 transfers and preserves every
 * balance exactly. Not guaranteed globally minimal (that problem is NP-hard).
 * Suggestions are NEVER stored; a user must record a settlement.
 */
final class DebtSimplifier
{
    /**
     * @param array<int,int> $nets user_id => net paise (positive = gets back)
     * @return list<array{from:int,to:int,amount:int}>
     */
    public function simplify(array $nets): array
    {
        if (array_sum($nets) !== 0) {
            throw new \InvalidArgumentException('Net balances must sum to zero.');
        }
        $creditors = array_filter($nets, static fn(int $v) => $v > 0);
        $debtors   = array_map(static fn(int $v) => -$v, array_filter($nets, static fn(int $v) => $v < 0));

        $transfers = [];
        while ($creditors !== [] && $debtors !== []) {
            $c = $this->largest($creditors);
            $d = $this->largest($debtors);
            $amount = min($creditors[$c], $debtors[$d]);
            $transfers[] = ['from' => $d, 'to' => $c, 'amount' => $amount];
            $creditors[$c] -= $amount;
            $debtors[$d]   -= $amount;
            if ($creditors[$c] === 0) {
                unset($creditors[$c]);
            }
            if ($debtors[$d] === 0) {
                unset($debtors[$d]);
            }
        }
        return $transfers;
    }

    /** Key with the largest value; ties -> lowest user_id (deterministic). */
    private function largest(array $map): int
    {
        $best = null;
        foreach ($map as $k => $v) {
            if ($best === null || $v > $map[$best] || ($v === $map[$best] && $k < $best)) {
                $best = $k;
            }
        }
        return (int) $best;
    }
}
