<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Helpers\Validator;
use App\Models\GroupModel;
use App\Models\InvitationModel;
use App\Models\NotificationModel;
use App\Models\UserModel;

/**
 * Group rules (enforced here, not just hidden in the UI):
 *  - only active members see a group; only group admins manage members/settings
 *  - leaving / removal only at zero balance and with no pending settlements
 *  - a group always keeps at least one active admin
 */
final class GroupService
{
    public function __construct(
        private GroupModel $groups = new GroupModel(),
        private InvitationModel $invites = new InvitationModel(),
        private NotificationModel $notes = new NotificationModel(),
    ) {
    }

    /** Group row for an active member, else 404/403. */
    public function requireMember(int $groupId, int $userId): array
    {
        $group = $this->groups->find($groupId) ?? throw new HttpException(404);
        $m = $this->groups->membership($groupId, $userId);
        if (!$m || $m['status'] !== 'active') {
            throw new HttpException(403, 'You are not a member of this group.');
        }
        return $group + ['my_role' => $m['role']];
    }

    public function requireAdmin(int $groupId, int $userId): array
    {
        $group = $this->requireMember($groupId, $userId);
        if ($group['my_role'] !== 'admin') {
            throw new HttpException(403, 'Only group admins can do that.');
        }
        return $group;
    }

    /** Write actions are blocked on archived groups. */
    public function requireWritable(array $group): void
    {
        if ((int) $group['is_archived'] === 1) {
            throw new HttpException(403, 'This group is archived (read-only).');
        }
    }

    public function create(int $actor, array $in): int
    {
        [$name, $desc, $type] = $this->validate($in);
        return Database::transaction(function () use ($actor, $name, $desc, $type) {
            $id = $this->groups->create($name, $desc, $type, $actor);
            $this->groups->addMember($id, $actor, 'admin', null);
            return $id;
        });
    }

    public function update(int $actor, int $groupId, array $in): void
    {
        $this->requireWritable($this->requireAdmin($groupId, $actor));
        [$name, $desc, $type] = $this->validate($in);
        $this->groups->update($groupId, $name, $desc, $type);
    }

    public function setArchived(int $actor, int $groupId, bool $archived): void
    {
        $this->requireAdmin($groupId, $actor);
        $this->groups->setArchived($groupId, $archived);
    }

    public function invite(int $actor, int $groupId, string $who): void
    {
        $group = $this->requireMember($groupId, $actor);
        $this->requireWritable($group);
        $who = trim($who);
        $user = $who !== '' ? (new UserModel())->findByUsernameOrEmail($who) : null;
        if (!$user) {
            throw ValidationException::single('invite', 'No registered user with that username or email.');
        }
        $uid = (int) $user['user_id'];
        if ($uid === $actor) {
            throw ValidationException::single('invite', 'You are already in this group.');
        }
        $m = $this->groups->membership($groupId, $uid);
        if ($m && $m['status'] === 'active') {
            throw ValidationException::single('invite', $user['full_name'] . ' is already a member.');
        }
        if ($this->invites->hasPending($groupId, $uid)) {
            throw ValidationException::single('invite', $user['full_name'] . ' already has a pending invitation.');
        }
        Database::transaction(function () use ($groupId, $uid, $actor, $group) {
            $invId = $this->invites->create($groupId, $uid, $actor);
            $this->notes->create($uid, 'group_invite', 'Group invitation',
                sprintf('%s invited you to "%s".', $this->name($actor), $group['name']), 'invitation', $invId);
        });
    }

    /** accept / decline (invitee) or cancel (inviter or group admin). */
    public function respondToInvitation(int $actor, int $invitationId, string $action): int
    {
        $inv = $this->invites->find($invitationId) ?? throw new HttpException(404);
        $groupId = (int) $inv['group_id'];
        if ($action === 'cancel') {
            $m = $this->groups->membership($groupId, $actor);
            $isAdmin = $m && $m['status'] === 'active' && $m['role'] === 'admin';
            if ((int) $inv['invited_by'] !== $actor && !$isAdmin) {
                throw new HttpException(403);
            }
        } elseif ((int) $inv['invited_user_id'] !== $actor) {
            throw new HttpException(403);
        }

        $status = ['accept' => 'accepted', 'decline' => 'declined', 'cancel' => 'cancelled'][$action]
            ?? throw new HttpException(400);

        Database::transaction(function () use ($invitationId, $status, $groupId, $inv, $action) {
            if (!$this->invites->respond($invitationId, $status)) {
                throw ValidationException::single('invite', 'This invitation was already answered.');
            }
            if ($action === 'accept') {
                $this->groups->addMember($groupId, (int) $inv['invited_user_id'], 'member', (int) $inv['invited_by']);
            }
        });
        return $groupId;
    }

    public function removeMember(int $actor, int $groupId, int $userId): void
    {
        $group = $this->requireAdmin($groupId, $actor);
        if ($userId === $actor) {
            throw ValidationException::single('member', 'Use "Leave group" to remove yourself.');
        }
        Database::transaction(function () use ($groupId, $userId, $group) {
            // exclusive lock: waits for in-flight expense/settlement writes, blocks new ones
            $this->groups->lockMembers($groupId, true);
            $m = $this->groups->membership($groupId, $userId);
            if (!$m || $m['status'] !== 'active') {
                throw new HttpException(404);
            }
            $this->assertSettled($groupId, $userId, 'They');
            if ($m['role'] === 'admin' && $this->groups->activeAdminCount($groupId) <= 1) {
                throw ValidationException::single('member', 'A group needs at least one admin.');
            }
            $this->groups->endMembership($groupId, $userId, 'removed');
            $this->notes->create($userId, 'member_removed', 'Removed from group',
                sprintf('You were removed from "%s".', $group['name']), 'group', $groupId);
        });
    }

    public function leave(int $actor, int $groupId): void
    {
        $this->requireMember($groupId, $actor);
        Database::transaction(function () use ($groupId, $actor) {
            $this->groups->lockMembers($groupId, true);
            $m = $this->groups->membership($groupId, $actor);
            if (!$m || $m['status'] !== 'active') {
                throw new HttpException(403);
            }
            $this->assertSettled($groupId, $actor, 'You');
            $others = count($this->groups->activeMemberIds($groupId)) - 1;
            if ($m['role'] === 'admin' && $others > 0 && $this->groups->activeAdminCount($groupId) <= 1) {
                throw ValidationException::single('member', 'Make another member an admin before you leave.');
            }
            $this->groups->endMembership($groupId, $actor, 'left');
        });
    }

    public function setRole(int $actor, int $groupId, int $userId, string $role): void
    {
        $this->requireAdmin($groupId, $actor);
        if (!in_array($role, ['admin', 'member'], true)) {
            throw new HttpException(400);
        }
        $m = $this->groups->membership($groupId, $userId);
        if (!$m || $m['status'] !== 'active') {
            throw new HttpException(404);
        }
        if ($role === 'member' && $m['role'] === 'admin' && $this->groups->activeAdminCount($groupId) <= 1) {
            throw ValidationException::single('member', 'A group needs at least one admin.');
        }
        $this->groups->setRole($groupId, $userId, $role);
    }

    private function assertSettled(int $groupId, int $userId, string $who): void
    {
        $net = $this->groups->netBalance($groupId, $userId);
        if (Money::toPaise($net) !== 0) {
            throw ValidationException::single('member', sprintf('%s must be settled up first (balance %s).', $who, inr($net)));
        }
        if ($this->groups->hasPendingSettlements($groupId, $userId)) {
            throw ValidationException::single('member', "$who still have a pending settlement in this group.");
        }
    }

    private function validate(array $in): array
    {
        $name = trim((string) ($in['name'] ?? ''));
        $desc = trim((string) ($in['description'] ?? ''));
        $type = (string) ($in['group_type'] ?? 'other');
        (new Validator())
            ->required('name', $name, 'Group name')->maxLen('name', $name, 80, 'Group name')
            ->maxLen('description', $desc, 255, 'Description')
            ->in('group_type', $type, GroupModel::TYPES, 'group type')
            ->throw();
        return [$name, $desc !== '' ? $desc : null, $type];
    }

    private function name(int $userId): string
    {
        return (string) ((new UserModel())->find($userId)['full_name'] ?? 'Someone');
    }
}
