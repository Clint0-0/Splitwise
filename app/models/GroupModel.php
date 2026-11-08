<?php
declare(strict_types=1);

namespace App\Models;

final class GroupModel extends Model
{
    public const TYPES = ['trip', 'home', 'family', 'friends', 'work', 'other'];

    public function create(string $name, ?string $description, string $type, int $createdBy): int
    {
        return $this->insert('INSERT INTO expense_groups (name, description, group_type, created_by) VALUES (?, ?, ?, ?)',
            [$name, $description, $type, $createdBy]);
    }

    public function find(int $id): ?array
    {
        return $this->one('SELECT g.*, u.full_name AS creator_name
                             FROM expense_groups g JOIN users u ON u.user_id = g.created_by
                            WHERE g.group_id = ?', [$id]);
    }

    public function update(int $id, string $name, ?string $description, string $type): void
    {
        $this->run('UPDATE expense_groups SET name = ?, description = ?, group_type = ? WHERE group_id = ?',
            [$name, $description, $type, $id]);
    }

    public function setArchived(int $id, bool $archived): void
    {
        $this->run('UPDATE expense_groups SET is_archived = ? WHERE group_id = ?', [$archived ? 1 : 0, $id]);
    }

    /** Groups where the user is an active member, with their net balance. */
    public function forUser(int $userId): array
    {
        return $this->all("SELECT g.group_id, g.name, g.description, g.group_type, g.is_archived, gm.role,
                                  COALESCE(b.net_balance, 0) AS net_balance,
                                  (SELECT COUNT(*) FROM group_members x
                                    WHERE x.group_id = g.group_id AND x.status = 'active') AS member_count,
                                  (SELECT COALESCE(SUM(e.total_amount), 0) FROM expenses e
                                    WHERE e.group_id = g.group_id AND e.is_deleted = 0) AS total_spent
                             FROM group_members gm
                             JOIN expense_groups g ON g.group_id = gm.group_id
                             LEFT JOIN v_group_member_balances b ON b.group_id = gm.group_id AND b.user_id = gm.user_id
                            WHERE gm.user_id = ? AND gm.status = 'active'
                            ORDER BY g.is_archived, g.name", [$userId]);
    }

    public function membership(int $groupId, int $userId): ?array
    {
        return $this->one('SELECT * FROM group_members WHERE group_id = ? AND user_id = ?', [$groupId, $userId]);
    }

    /** Members (all statuses) with balances; active first. */
    public function members(int $groupId): array
    {
        return $this->all("SELECT gm.user_id, gm.role, gm.status, gm.joined_at, gm.left_at,
                                  u.full_name, u.username,
                                  b.total_paid, b.total_owed, b.settled_paid, b.settled_received, b.net_balance
                             FROM group_members gm
                             JOIN users u ON u.user_id = gm.user_id
                             JOIN v_group_member_balances b ON b.group_id = gm.group_id AND b.user_id = gm.user_id
                            WHERE gm.group_id = ?
                            ORDER BY gm.status = 'active' DESC, gm.role = 'admin' DESC, u.full_name", [$groupId]);
    }

    public function activeMemberIds(int $groupId): array
    {
        return array_map('intval', array_column($this->all(
            "SELECT user_id FROM group_members WHERE group_id = ? AND status = 'active'", [$groupId]), 'user_id'));
    }

    /** Add or re-activate a membership (a user who left can be re-invited). */
    public function addMember(int $groupId, int $userId, string $role, ?int $addedBy): void
    {
        $this->run("INSERT INTO group_members (group_id, user_id, role, status, added_by)
                    VALUES (?, ?, ?, 'active', ?)
                    ON DUPLICATE KEY UPDATE status = 'active', left_at = NULL, role = VALUES(role),
                                            joined_at = NOW(), added_by = VALUES(added_by)",
            [$groupId, $userId, $role, $addedBy]);
    }

    public function endMembership(int $groupId, int $userId, string $status): void
    {
        $this->run("UPDATE group_members SET status = ?, left_at = NOW(), role = 'member'
                     WHERE group_id = ? AND user_id = ? AND status = 'active'", [$status, $groupId, $userId]);
    }

    public function setRole(int $groupId, int $userId, string $role): void
    {
        $this->run("UPDATE group_members SET role = ? WHERE group_id = ? AND user_id = ? AND status = 'active'",
            [$role, $groupId, $userId]);
    }

    /**
     * Lock the group's membership rows for the current transaction.
     * Exclusive (FOR UPDATE) for leave/remove; shared (FOR SHARE) for expense writes.
     * Leaving waits for in-flight expense writes and vice versa, so a member can never
     * leave "in between" a balance check and a new expense. Same lock order as
     * sp_record_settlement (membership rows of one group).
     */
    public function lockMembers(int $groupId, bool $exclusive): void
    {
        $this->all('SELECT membership_id FROM group_members WHERE group_id = ? ORDER BY membership_id '
            . ($exclusive ? 'FOR UPDATE' : 'FOR SHARE'), [$groupId]);
    }

    public function activeAdminCount(int $groupId): int
    {
        return (int) $this->value("SELECT COUNT(*) FROM group_members
                                    WHERE group_id = ? AND role = 'admin' AND status = 'active'", [$groupId]);
    }

    public function netBalance(int $groupId, int $userId): string
    {
        return (string) $this->value('SELECT fn_member_net_balance(?, ?)', [$groupId, $userId]);
    }

    public function hasPendingSettlements(int $groupId, int $userId): bool
    {
        return (bool) $this->value("SELECT 1 FROM settlements WHERE group_id = ? AND status = 'pending'
                                     AND (payer_id = ? OR payee_id = ?) LIMIT 1", [$groupId, $userId, $userId]);
    }

    /** Admin overview. */
    public function allWithStats(int $limit, int $offset): array
    {
        return $this->all("SELECT s.group_id, s.group_name AS name, s.expense_count, s.total_spent, s.last_expense_date,
                                  g.group_type, g.is_archived, g.created_at, u.full_name AS creator_name,
                                  (SELECT COUNT(*) FROM group_members x WHERE x.group_id = g.group_id AND x.status = 'active') AS member_count
                             FROM v_group_expense_summary s
                             JOIN expense_groups g ON g.group_id = s.group_id
                             JOIN users u ON u.user_id = g.created_by
                            ORDER BY g.created_at DESC
                            LIMIT ? OFFSET ?", [$limit, $offset]);
    }

    public function count(): int
    {
        return (int) $this->value('SELECT COUNT(*) FROM expense_groups');
    }
}
