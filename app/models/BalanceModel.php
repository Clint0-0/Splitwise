<?php
declare(strict_types=1);

namespace App\Models;

/** Read-only access to the balance views (balances are never stored). */
final class BalanceModel extends Model
{
    public function overall(int $userId): array
    {
        return $this->one('SELECT * FROM v_user_overall_balance WHERE user_id = ?', [$userId])
            ?? ['total_paid' => '0.00', 'total_owed' => '0.00', 'net_balance' => '0.00', 'receivable' => '0.00', 'payable' => '0.00'];
    }

    public function groupNets(int $groupId): array
    {
        return $this->all('SELECT user_id, full_name, member_status, net_balance
                             FROM v_group_member_balances WHERE group_id = ?', [$groupId]);
    }

    /**
     * Pairwise lines involving $userId (or all lines of the group if $userId is null),
     * oriented as debtor -> creditor with original / settled / remaining amounts.
     */
    public function pairwise(?int $groupId, ?int $userId): array
    {
        $sql = 'SELECT d.group_id, g.name AS group_name,
                       IF(d.outstanding > 0, d.user_a, d.user_b)         AS debtor_id,
                       IF(d.outstanding > 0, ua.full_name, ub.full_name) AS debtor_name,
                       IF(d.outstanding > 0, d.user_b, d.user_a)         AS creditor_id,
                       IF(d.outstanding > 0, ub.full_name, ua.full_name) AS creditor_name,
                       IF(d.outstanding > 0, d.gross_a_owes_b, d.gross_b_owes_a)     AS original_owed,
                       IF(d.outstanding > 0, d.gross_b_owes_a, d.gross_a_owes_b)     AS owed_back,
                       IF(d.outstanding > 0, d.settled_a_paid_b - d.settled_b_paid_a,
                                             d.settled_b_paid_a - d.settled_a_paid_b) AS settled,
                       ABS(d.outstanding) AS remaining
                  FROM v_pairwise_debts d
                  JOIN users ua ON ua.user_id = d.user_a
                  JOIN users ub ON ub.user_id = d.user_b
                  JOIN expense_groups g ON g.group_id = d.group_id
                 WHERE d.outstanding <> 0';
        $p = [];
        if ($groupId !== null) {
            $sql .= ' AND d.group_id = ?';
            $p[] = $groupId;
        }
        if ($userId !== null) {
            $sql .= ' AND (d.user_a = ? OR d.user_b = ?)';
            array_push($p, $userId, $userId);
        }
        return $this->all($sql . ' ORDER BY g.name, remaining DESC', $p);
    }
}
