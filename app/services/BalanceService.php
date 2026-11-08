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

    public function overall(int $userId): array
    {
        return $this->balances->overall($userId);
    }

    public function pairwise(?int $groupId, ?int $userId): array
    {
        return $this->balances->pairwise($groupId, $userId);
    }
}
