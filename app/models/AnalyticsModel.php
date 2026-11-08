<?php
declare(strict_types=1);

namespace App\Models;

/** Read-only reporting queries for charts (all based on real data). */
final class AnalyticsModel extends Model
{
    /** User's share per top-level category between two dates. */
    public function categoryShare(int $userId, string $from, string $to): array
    {
        return $this->all('SELECT COALESCE(p.name, c.name) AS label, COALESCE(p.color, c.color) AS color,
                                  SUM(s.share_amount) AS total
                             FROM expense_splits s
                             JOIN expenses e         ON e.expense_id = s.expense_id
                             JOIN categories c       ON c.category_id = e.category_id
                             LEFT JOIN categories p  ON p.category_id = c.parent_category_id
                            WHERE s.user_id = ? AND e.is_deleted = 0 AND e.expense_date BETWEEN ? AND ?
                            GROUP BY COALESCE(p.name, c.name), COALESCE(p.color, c.color)
                            ORDER BY total DESC', [$userId, $from, $to]);
    }

    /** Monthly share for the last N months (months with no spending included as 0 by the caller). */
    public function monthly(int $userId, string $fromMonth): array
    {
        return $this->all('SELECT spend_month AS month, my_share AS total, expense_count
                             FROM v_monthly_user_spending
                            WHERE user_id = ? AND spend_month >= ?
                            ORDER BY spend_month', [$userId, $fromMonth]);
    }

    /** Spending of each of the user's groups. */
    public function groups(int $userId): array
    {
        return $this->all("SELECT s.group_name AS label, s.total_spent AS total, s.expense_count
                             FROM v_group_expense_summary s
                            WHERE s.group_id IN (SELECT group_id FROM group_members WHERE user_id = ? AND status = 'active')
                            ORDER BY s.total_spent DESC", [$userId]);
    }

    /** Per group: what the user paid vs. their share. */
    public function paidVsOwed(int $userId): array
    {
        return $this->all("SELECT group_name AS label, total_paid AS paid, total_owed AS owed
                             FROM v_group_member_balances
                            WHERE user_id = ? AND member_status = 'active'
                            ORDER BY group_name", [$userId]);
    }

    /** Confirmed settlements sent / received by the user per month. */
    public function settlements(int $userId): array
    {
        return $this->all("SELECT DATE_FORMAT(settlement_date, '%Y-%m') AS month,
                                  SUM(CASE WHEN payer_id = ? THEN amount ELSE 0 END) AS sent,
                                  SUM(CASE WHEN payee_id = ? THEN amount ELSE 0 END) AS received
                             FROM settlements
                            WHERE status = 'confirmed' AND (payer_id = ? OR payee_id = ?)
                            GROUP BY DATE_FORMAT(settlement_date, '%Y-%m')
                            ORDER BY month", [$userId, $userId, $userId, $userId]);
    }

    /** Largest expenses the user took part in. */
    public function topExpenses(int $userId, int $limit = 5): array
    {
        return $this->all("SELECT e.expense_id, e.description, e.total_amount, e.expense_date, s.share_amount,
                                  COALESCE(g.name, 'Personal') AS group_name
                             FROM expense_splits s
                             JOIN expenses e ON e.expense_id = s.expense_id
                             LEFT JOIN expense_groups g ON g.group_id = e.group_id
                            WHERE s.user_id = ? AND e.is_deleted = 0
                            ORDER BY e.total_amount DESC LIMIT ?", [$userId, $limit]);
    }

    public function totals(int $userId): array
    {
        return $this->one("SELECT
              (SELECT COALESCE(SUM(s.share_amount),0) FROM expense_splits s JOIN expenses e ON e.expense_id = s.expense_id
                WHERE s.user_id = ? AND e.is_deleted = 0 AND e.group_id IS NULL)     AS personal_spend,
              (SELECT COALESCE(SUM(s.share_amount),0) FROM expense_splits s JOIN expenses e ON e.expense_id = s.expense_id
                WHERE s.user_id = ? AND e.is_deleted = 0 AND e.group_id IS NOT NULL) AS group_share",
            [$userId, $userId]);
    }

    /** Admin: whole-system statistics. */
    public function system(): array
    {
        return $this->one("SELECT
              (SELECT COUNT(*) FROM users)                                      AS users,
              (SELECT COUNT(*) FROM users WHERE is_active = 1)                  AS active_users,
              (SELECT COUNT(*) FROM expense_groups)                             AS groups_total,
              (SELECT COUNT(*) FROM expenses WHERE is_deleted = 0)              AS expenses,
              (SELECT COALESCE(SUM(total_amount),0) FROM expenses WHERE is_deleted = 0) AS expense_total,
              (SELECT COUNT(*) FROM settlements WHERE status = 'confirmed')     AS settlements,
              (SELECT COALESCE(SUM(amount),0) FROM settlements WHERE status = 'confirmed') AS settled_total,
              (SELECT COUNT(*) FROM settlements WHERE status = 'pending')       AS pending_settlements");
    }

    public function audit(?string $table, int $limit, int $offset): array
    {
        $sql = 'SELECT a.log_id, a.table_name, a.record_id, a.action, a.old_data, a.new_data, a.changed_at,
                       u.full_name AS changed_by_name
                  FROM audit_logs a LEFT JOIN users u ON u.user_id = a.changed_by';
        $p = [];
        if ($table !== null) {
            $sql .= ' WHERE a.table_name = ?';
            $p[] = $table;
        }
        $sql .= ' ORDER BY a.log_id DESC LIMIT ? OFFSET ?';
        array_push($p, $limit, $offset);
        return $this->all($sql, $p);
    }

    public function auditCount(?string $table): int
    {
        return (int) ($table === null
            ? $this->value('SELECT COUNT(*) FROM audit_logs')
            : $this->value('SELECT COUNT(*) FROM audit_logs WHERE table_name = ?', [$table]));
    }
}
