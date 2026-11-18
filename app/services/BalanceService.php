<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Money;
use App\Models\BalanceModel;

/** Balances come from the views; suggestions from DebtSimplifier. */
final class BalanceService
{
    public function __construct(private BalanceModel $balances = new BalanceModel())
    {
    }

    /**
     * Suggested transfers for a group (not stored, not recorded).
     * @return list<array{from:int,from_name:string,to:int,to_name:string,amount:string}>
     */
    public function suggestions(int $groupId): array
    {
        $nets = [];
        $names = [];
        foreach ($this->balances->groupNets($groupId) as $row) {
            $paise = Money::toPaise((string) $row['net_balance']);
            if ($paise !== 0) {
                $nets[(int) $row['user_id']] = $paise;
            }
            $names[(int) $row['user_id']] = $row['full_name'];
        }
        $out = [];
        foreach ((new DebtSimplifier())->simplify($nets) as $t) {
            $out[] = [
                'from' => $t['from'], 'from_name' => $names[$t['from']],
                'to'   => $t['to'],   'to_name'   => $names[$t['to']],
                'amount' => Money::fromPaise($t['amount']),
            ];
        }
        return $out;
    }

    /**
     * Totals for the summary bands. "Owed to you" / "You owe" are summed from the SAME
     * person-to-person debts the "Who owes whom" list shows, so the band and the list always
     * agree. (v_user_overall_balance.receivable/payable add up per-GROUP nets instead: a user who
     * is net positive in a group but still owes one member there showed "You owe ₹0.00" next to
     * a list saying "You owe Aarav ₹3,516.67".) The net balance is identical either way.
     */
    public function overall(int $userId): array
    {
        $row = $this->balances->overall($userId);
        $in = 0;
        $out = 0;
        foreach ($this->balances->pairwise(null, $userId) as $p) {
            $amt = Money::toPaise((string) $p['remaining']);
            if ((int) $p['creditor_id'] === $userId) {
                $in += $amt;
            } elseif ((int) $p['debtor_id'] === $userId) {
                $out += $amt;
            }
        }
        $row['receivable'] = Money::fromPaise($in);
        $row['payable'] = Money::fromPaise($out);
        return $row;
    }

    public function pairwise(?int $groupId, ?int $userId): array
    {
        return $this->balances->pairwise($groupId, $userId);
    }
}
