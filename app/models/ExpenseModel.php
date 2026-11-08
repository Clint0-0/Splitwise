<?php
declare(strict_types=1);

namespace App\Models;

final class ExpenseModel extends Model
{
    public function create(array $d): int
    {
        return $this->insert('INSERT INTO expenses (group_id, paid_by, created_by, category_id, description,
                                                    total_amount, split_method, expense_date, notes)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $d['group_id'], $d['paid_by'], $d['created_by'], $d['category_id'], $d['description'],
            $d['total_amount'], $d['split_method'], $d['expense_date'], $d['notes'],
        ]);
    }

    /** Optimistic locking: only updates if nobody changed the row since $version was read. */
    public function update(int $id, int $version, array $d): bool
    {
        return $this->run('UPDATE expenses
                              SET paid_by = ?, category_id = ?, description = ?, total_amount = ?,
                                  split_method = ?, expense_date = ?, notes = ?, version = version + 1
                            WHERE expense_id = ? AND version = ? AND is_deleted = 0', [
            $d['paid_by'], $d['category_id'], $d['description'], $d['total_amount'],
            $d['split_method'], $d['expense_date'], $d['notes'], $id, $version,
        ]) === 1;
    }

    public function addSplit(int $expenseId, int $userId, string $amount, ?string $pct, ?string $units, ?string $adj): void
    {
        $this->run('INSERT INTO expense_splits (expense_id, user_id, share_amount, share_percentage, share_units, share_adjustment)
                    VALUES (?, ?, ?, ?, ?, ?)', [$expenseId, $userId, $amount, $pct, $units, $adj]);
    }

    public function deleteSplits(int $expenseId): void
    {
        $this->run('DELETE FROM expense_splits WHERE expense_id = ?', [$expenseId]);
    }

    public function splitGap(int $expenseId): string
    {
        return (string) $this->value('SELECT fn_expense_split_gap(?)', [$expenseId]);
    }

    public function find(int $id): ?array
    {
        return $this->one('SELECT e.*, payer.full_name AS payer_name, creator.full_name AS creator_name,
                                  c.name AS category_name, c.icon AS category_icon, g.name AS group_name, g.is_archived
                             FROM expenses e
                             JOIN users payer       ON payer.user_id = e.paid_by
                             JOIN users creator     ON creator.user_id = e.created_by
                             JOIN categories c      ON c.category_id = e.category_id
                             LEFT JOIN expense_groups g ON g.group_id = e.group_id
                            WHERE e.expense_id = ?', [$id]);
    }

    public function splits(int $expenseId): array
    {
        return $this->all('SELECT s.*, u.full_name FROM expense_splits s JOIN users u ON u.user_id = s.user_id
                            WHERE s.expense_id = ? ORDER BY u.full_name', [$expenseId]);
    }

    /** Payer/participants of a group expense who are no longer active members of the group. */
    public function formerMembers(int $expenseId, int $groupId): array
    {
        return array_column($this->all("SELECT DISTINCT u.full_name
                                           FROM (SELECT user_id FROM expense_splits WHERE expense_id = ?
                                                 UNION SELECT paid_by FROM expenses WHERE expense_id = ?) p
                                           JOIN users u ON u.user_id = p.user_id
                                           LEFT JOIN group_members gm ON gm.group_id = ? AND gm.user_id = p.user_id
                                          WHERE gm.status IS NULL OR gm.status <> 'active'
                                          ORDER BY u.full_name", [$expenseId, $expenseId, $groupId]), 'full_name');
    }

    public function softDelete(int $id, int $by): bool
    {
        return $this->run('UPDATE expenses SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                            WHERE expense_id = ? AND is_deleted = 0', [$by, $id]) === 1;
    }

    public function restore(int $id): bool
    {
        return $this->run('UPDATE expenses SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL
                            WHERE expense_id = ? AND is_deleted = 1', [$id]) === 1;
    }

    /**
     * Expenses visible to $userId (own personal expenses + groups they belong/belonged to),
     * filtered. Only whitelisted filters; every value is a bound parameter.
     */
    public function search(int $userId, array $f, int $limit, int $offset): array
    {
        [$where, $params] = $this->filters($userId, $f);
        $params[] = $limit;
        $params[] = $offset;
        return $this->all("SELECT e.expense_id, e.expense_date, e.description, e.total_amount, e.split_method,
                                  e.is_deleted, e.group_id, COALESCE(g.name, 'Personal') AS group_name,
                                  payer.full_name AS payer_name, c.name AS category_name, c.icon AS category_icon,
                                  my.share_amount AS my_share
                             FROM expenses e
                             JOIN users payer  ON payer.user_id = e.paid_by
                             JOIN categories c ON c.category_id = e.category_id
                             LEFT JOIN expense_groups g ON g.group_id = e.group_id
                             LEFT JOIN expense_splits my ON my.expense_id = e.expense_id AND my.user_id = ?
                            WHERE $where
                            ORDER BY e.expense_date DESC, e.expense_id DESC
                            LIMIT ? OFFSET ?", array_merge([$userId], $params));
    }

    public function countSearch(int $userId, array $f): int
    {
        [$where, $params] = $this->filters($userId, $f);
        return (int) $this->value("SELECT COUNT(*) FROM expenses e
                                     JOIN categories c ON c.category_id = e.category_id
                                    WHERE $where", $params);
    }

    private function filters(int $userId, array $f): array
    {
        $w = ['(e.paid_by = ? AND e.group_id IS NULL
                OR e.group_id IN (SELECT group_id FROM group_members WHERE user_id = ?))'];
        $p = [$userId, $userId];
        $w[] = empty($f['deleted']) ? 'e.is_deleted = 0' : 'e.is_deleted = 1';
        if (!empty($f['q'])) {
            $w[] = '(e.description LIKE ? OR e.notes LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($p, $like, $like);
        }
        if (($f['group'] ?? '') === 'personal') {
            $w[] = 'e.group_id IS NULL';
        } elseif (!empty($f['group'])) {
            $w[] = 'e.group_id = ?';
            $p[] = (int) $f['group'];
        }
        if (!empty($f['category'])) {
            $w[] = '(c.category_id = ? OR c.parent_category_id = ?)';
            array_push($p, (int) $f['category'], (int) $f['category']);
        }
        if (!empty($f['from'])) {
            $w[] = 'e.expense_date >= ?';
            $p[] = $f['from'];
        }
        if (!empty($f['to'])) {
            $w[] = 'e.expense_date <= ?';
            $p[] = $f['to'];
        }
        if (!empty($f['payer'])) {
            $w[] = 'e.paid_by = ?';
            $p[] = (int) $f['payer'];
        }
        if (!empty($f['tag'])) {
            $w[] = 'EXISTS (SELECT 1 FROM expense_tags et JOIN tags t ON t.tag_id = et.tag_id
                             WHERE et.expense_id = e.expense_id AND t.name = ?)';
            $p[] = $f['tag'];
        }
        return [implode(' AND ', $w), $p];
    }

    public function recentForUser(int $userId, int $limit = 5): array
    {
        return $this->search($userId, [], $limit, 0);
    }

    // ---- tags ---------------------------------------------------------------
    public function setTags(int $expenseId, array $names): void
    {
        $this->run('DELETE FROM expense_tags WHERE expense_id = ?', [$expenseId]);
        foreach ($names as $name) {
            $this->run('INSERT IGNORE INTO tags (name) VALUES (?)', [$name]);
            $this->run('INSERT IGNORE INTO expense_tags (expense_id, tag_id) SELECT ?, tag_id FROM tags WHERE name = ?',
                [$expenseId, $name]);
        }
    }

    public function tags(int $expenseId): array
    {
        return array_column($this->all('SELECT t.name FROM expense_tags et JOIN tags t ON t.tag_id = et.tag_id
                                         WHERE et.expense_id = ? ORDER BY t.name', [$expenseId]), 'name');
    }

    // ---- receipts -----------------------------------------------------------
    public function addReceipt(int $expenseId, string $path, string $original, string $mime, int $size, int $by): void
    {
        $this->run('INSERT INTO receipts (expense_id, file_path, original_name, mime_type, file_size, uploaded_by)
                    VALUES (?, ?, ?, ?, ?, ?)', [$expenseId, $path, $original, $mime, $size, $by]);
    }

    public function receipts(int $expenseId): array
    {
        return $this->all('SELECT * FROM receipts WHERE expense_id = ? ORDER BY uploaded_at', [$expenseId]);
    }

    public function receipt(int $receiptId): ?array
    {
        return $this->one('SELECT * FROM receipts WHERE receipt_id = ?', [$receiptId]);
    }

    /** Audit trail of one expense (and its splits) for the details page. */
    public function history(int $expenseId): array
    {
        return $this->all("SELECT a.action, a.changed_at, a.old_data, a.new_data, u.full_name AS changed_by_name
                             FROM audit_logs a LEFT JOIN users u ON u.user_id = a.changed_by
                            WHERE a.table_name = 'expenses' AND a.record_id = ?
                            ORDER BY a.log_id", [$expenseId]);
    }
}
