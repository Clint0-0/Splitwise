<?php
declare(strict_types=1);

namespace App\Models;

final class InvitationModel extends Model
{
    public function create(int $groupId, int $invitedUserId, int $invitedBy): int
    {
        return $this->insert('INSERT INTO group_invitations (group_id, invited_user_id, invited_by) VALUES (?, ?, ?)',
            [$groupId, $invitedUserId, $invitedBy]);
    }

    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM group_invitations WHERE invitation_id = ?', [$id]);
    }

    public function pendingForUser(int $userId): array
    {
        return $this->all("SELECT i.invitation_id, i.created_at, g.group_id, g.name AS group_name, u.full_name AS inviter_name
                             FROM group_invitations i
                             JOIN expense_groups g ON g.group_id = i.group_id
                             JOIN users u ON u.user_id = i.invited_by
                            WHERE i.invited_user_id = ? AND i.status = 'pending'
                            ORDER BY i.created_at DESC", [$userId]);
    }

    public function pendingForGroup(int $groupId): array
    {
        return $this->all("SELECT i.invitation_id, i.created_at, i.invited_by, u.full_name, u.username
                             FROM group_invitations i JOIN users u ON u.user_id = i.invited_user_id
                            WHERE i.group_id = ? AND i.status = 'pending'
                            ORDER BY i.created_at DESC", [$groupId]);
    }

    public function hasPending(int $groupId, int $userId): bool
    {
        return (bool) $this->value("SELECT 1 FROM group_invitations
                                     WHERE group_id = ? AND invited_user_id = ? AND status = 'pending'", [$groupId, $userId]);
    }

    /** pending -> accepted/declined/cancelled. Returns false if it was no longer pending. */
    public function respond(int $id, string $status): bool
    {
        return $this->run("UPDATE group_invitations SET status = ?, responded_at = NOW()
                            WHERE invitation_id = ? AND status = 'pending'", [$status, $id]) === 1;
    }
}
