<?php
declare(strict_types=1);

namespace App\Models;

final class BudgetModel extends Model
{
    public const PERIODS = ['weekly', 'monthly', 'yearly', 'custom'];

    private const SELECT = "SELECT u.*, c.name AS category_name, g.name AS group_name, b.created_by
                              FROM v_budget_utilization u
                              JOIN budgets b ON b.budget_id = u.budget_id
                              LEFT JOIN categories c ON c.category_id = u.category_id
                              LEFT JOIN expense_groups g ON g.group_id = u.group_id";

    /** Personal budgets of the user + budgets of groups they actively belong to. */
    public function visibleFor(int $userId): array
    {
        return $this->all(self::SELECT . " WHERE u.user_id = ?
                              OR u.group_id IN (SELECT group_id FROM group_members WHERE user_id = ? AND status = 'active')
                            ORDER BY u.end_date < CURDATE(), u.utilization_pct DESC", [$userId, $userId]);
    }

    public function findWithUsage(int $id): ?array
    {
        return $this->one(self::SELECT . ' WHERE u.budget_id = ?', [$id]);
    }

    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM budgets WHERE budget_id = ?', [$id]);
    }

    /** Budgets touched by an expense: personal budgets of these users + the group's budgets. */
    public function affected(array $userIds, ?int $groupId): array
    {
        if ($userIds === [] && $groupId === null) {
            return [];
        }
        $in = $userIds === [] ? 'NULL' : implode(',', array_fill(0, count($userIds), '?'));
        return $this->all(self::SELECT . " WHERE u.user_id IN ($in) OR u.group_id = ?",
            array_merge(array_map('intval', $userIds), [$groupId ?? 0]));
    }

    public function create(array $d): int
    {
        return $this->insert('INSERT INTO budgets (user_id, group_id, category_id, name, limit_amount, period_type,
                                                   start_date, end_date, alert_threshold, created_by)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $d['user_id'], $d['group_id'], $d['category_id'], $d['name'], $d['limit_amount'], $d['period_type'],
            $d['start_date'], $d['end_date'], $d['alert_threshold'], $d['created_by'],
        ]);
    }

    public function update(int $id, array $d): void
    {
        $this->run('UPDATE budgets SET category_id = ?, name = ?, limit_amount = ?, period_type = ?,
                                       start_date = ?, end_date = ?, alert_threshold = ?
                     WHERE budget_id = ?', [
            $d['category_id'], $d['name'], $d['limit_amount'], $d['period_type'],
            $d['start_date'], $d['end_date'], $d['alert_threshold'], $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->run('DELETE FROM budgets WHERE budget_id = ?', [$id]);
    }
}
