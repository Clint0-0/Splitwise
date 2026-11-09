<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Helpers\Validator;
use App\Models\CategoryModel;
use App\Models\ExpenseModel;
use App\Models\GroupModel;

/**
 * Expense write path (ADR-6): PHP computes the split (SplitCalculator), then the
 * expense, its splits, tags and receipt are written in ONE PDO transaction and
 * fn_expense_split_gap() must be 0.00 before COMMIT. Triggers add audit rows
 * and participant notifications inside the same transaction.
 */
final class ExpenseService
{
    public function __construct(
        private ExpenseModel $expenses = new ExpenseModel(),
        private GroupModel $groups = new GroupModel(),
        private SplitCalculator $calc = new SplitCalculator(),
    ) {
    }

    /** Expense the user may see: own personal expense, or any group they are/were in. */
    public function requireViewable(int $expenseId, int $userId): array
    {
        $e = $this->expenses->find($expenseId) ?? throw new HttpException(404);
        if ($e['group_id'] === null) {
            if ((int) $e['paid_by'] !== $userId) {
                throw new HttpException(403);
            }
        } elseif (!$this->groups->membership((int) $e['group_id'], $userId)) {
            throw new HttpException(403);
        }
        return $e;
    }

    /**
     * Why this expense may not be changed by anyone, or null.
     * An expense that involves a FORMER member is locked: they left at a zero balance,
     * so editing, deleting or restoring it would silently change money they no longer
     * can see or settle.
     */
    public function lockReason(array $e): ?string
    {
        if ($e['group_id'] === null) {
            return null;
        }
        if ((int) $e['is_archived'] === 1) {
            return 'The group is archived (read-only).';
        }
        $former = $this->expenses->formerMembers((int) $e['expense_id'], (int) $e['group_id']);
        return $former === [] ? null : sprintf(
            'Locked: %s %s left the group with a settled balance, so this expense can no longer be changed.',
            implode(', ', $former), count($former) === 1 ? 'has' : 'have');
    }

    /** Creator, payer or an active group admin may edit/delete, unless the expense is locked. */
    public function canEdit(array $e, int $userId): bool
    {
        if ($e['group_id'] === null) {
            return (int) $e['paid_by'] === $userId;
        }
        $m = $this->groups->membership((int) $e['group_id'], $userId);
        if (!$m || $m['status'] !== 'active' || $this->lockReason($e) !== null) {
            return false;
        }
        return in_array($userId, [(int) $e['created_by'], (int) $e['paid_by']], true) || $m['role'] === 'admin';
    }

    public function create(int $actor, array $in, ?array $receipt): int
    {
        $groupId = ($in['group_id'] ?? '') === '' ? null : (int) $in['group_id'];
        if ($groupId !== null) {
            $group = (new GroupService())->requireMember($groupId, $actor);
            (new GroupService())->requireWritable($group);
        }
        $data = $this->validate($actor, $groupId, $in);

        $stored = $receipt ? (new UploadService())->storeReceipt($receipt) : null;
        try {
            $id = Database::transaction(function () use ($actor, $groupId, $data, $stored) {
                if ($groupId !== null) {
                    $this->groups->lockMembers($groupId, false);   // nobody leaves mid-write
                }
                $id = $this->expenses->create($data['row'] + ['group_id' => $groupId, 'created_by' => $actor]);
                $this->writeDetails($id, $data, $stored, $actor);
                return $id;
            });
        } catch (\Throwable $t) {
            if ($stored) {
                (new UploadService())->discard($stored['path']);
            }
            throw $this->friendly($t);
        }
        (new BudgetService())->checkThresholds(array_keys($data['shares']), $groupId);
        return $id;
    }

    public function update(int $actor, int $expenseId, array $in, ?array $receipt): void
    {
        $e = $this->requireViewable($expenseId, $actor);
        $this->assertChangeable($e, $actor, 'edit');
        if ((int) $e['is_deleted'] === 1) {
            throw new HttpException(403, 'Restore this expense before editing it.');
        }
        $groupId = $e['group_id'] === null ? null : (int) $e['group_id'];
        $data = $this->validate($actor, $groupId, $in);
        $version = (int) ($in['version'] ?? 0);

        $stored = $receipt ? (new UploadService())->storeReceipt($receipt) : null;
        try {
            Database::transaction(function () use ($e, $expenseId, $version, $data, $stored, $actor, $groupId) {
                $this->lockAndRecheck($e, $groupId);
                if (!$this->expenses->update($expenseId, $version, $data['row'])) {
                    throw ValidationException::single('version',
                        'Someone else changed this expense while you were editing. Reload and try again.');
                }
                $this->expenses->deleteSplits($expenseId);   // trigger archives old splits
                $this->writeDetails($expenseId, $data, $stored, $actor);
            });
        } catch (\Throwable $t) {
            if ($stored) {
                (new UploadService())->discard($stored['path']);
            }
            throw $this->friendly($t);
        }
        (new BudgetService())->checkThresholds(array_keys($data['shares']), $groupId);
    }

    public function delete(int $actor, int $expenseId): void
    {
        $e = $this->requireViewable($expenseId, $actor);
        $this->assertChangeable($e, $actor, 'delete');
        $groupId = $e['group_id'] === null ? null : (int) $e['group_id'];
        Database::transaction(function () use ($e, $expenseId, $actor, $groupId) {
            $this->lockAndRecheck($e, $groupId);
            $this->expenses->softDelete($expenseId, $actor);
        });
    }

    /** Restore a soft-deleted expense: group admins (or owner for personal). */
    public function restore(int $actor, int $expenseId): void
    {
        $e = $this->requireViewable($expenseId, $actor);
        $groupId = $e['group_id'] === null ? null : (int) $e['group_id'];
        $ok = $groupId === null
            ? (int) $e['paid_by'] === $actor
            : (($this->groups->membership($groupId, $actor)['role'] ?? '') === 'admin');
        if (!$ok) {
            throw new HttpException(403, 'Only a group admin can restore expenses.');
        }
        if (($reason = $this->lockReason($e)) !== null) {
            throw ValidationException::single('participants', $reason);
        }
        try {
            Database::transaction(function () use ($e, $expenseId, $groupId) {
                $this->lockAndRecheck($e, $groupId);
                $this->expenses->restore($expenseId);
            });
        } catch (\PDOException $p) {
            throw $this->friendly($p);
        }
    }

    /** Permission (403) first, then the lock rule (shown as a message). */
    private function assertChangeable(array $e, int $actor, string $verb): void
    {
        if ($e['group_id'] === null) {
            if ((int) $e['paid_by'] !== $actor) {
                throw new HttpException(403, "You cannot $verb this expense.");
            }
            return;
        }
        $m = $this->groups->membership((int) $e['group_id'], $actor);
        $allowed = $m && $m['status'] === 'active'
            && (in_array($actor, [(int) $e['created_by'], (int) $e['paid_by']], true) || $m['role'] === 'admin');
        if (!$allowed) {
            throw new HttpException(403, "You cannot $verb this expense.");
        }
        if (($reason = $this->lockReason($e)) !== null) {
            throw ValidationException::single('participants', $reason);
        }
    }

    /** Inside the transaction: lock members, then re-check (someone may have left meanwhile). */
    private function lockAndRecheck(array $e, ?int $groupId): void
    {
        if ($groupId === null) {
            return;
        }
        $this->groups->lockMembers($groupId, false);
        if (($reason = $this->lockReason($e)) !== null) {
            throw ValidationException::single('participants', $reason);
        }
    }

    // ---------------------------------------------------------------------
    private function writeDetails(int $expenseId, array $data, ?array $stored, int $actor): void
    {
        foreach ($data['shares'] as $uid => $paise) {
            $in = $data['inputs'][$uid];
            $this->expenses->addSplit($expenseId, $uid, Money::fromPaise($paise), $in['percentage'], $in['units'], $in['adjustment']);
        }
        if ($this->expenses->splitGap($expenseId) !== '0.00') {
            throw new \LogicException('Split total mismatch.');   // rolls back
        }
        $this->expenses->setTags($expenseId, $data['tags']);
        if ($stored) {
            $this->expenses->addReceipt($expenseId, $stored['path'], $stored['original'], $stored['mime'], $stored['size'], $actor, $stored['content']);
        }
    }

    /**
     * Validate the form and compute shares.
     * Input: description, total_amount, category_id, expense_date, paid_by, split_method,
     *        participants[] (user ids), share[<uid>] (method input), notes, tags ("a, b").
     */
    private function validate(int $actor, ?int $groupId, array $in): array
    {
        $desc   = trim((string) ($in['description'] ?? ''));
        $date   = trim((string) ($in['expense_date'] ?? ''));
        $notes  = trim((string) ($in['notes'] ?? ''));
        $method = (string) ($in['split_method'] ?? 'equal');
        $cat    = (int) ($in['category_id'] ?? 0);

        $v = (new Validator())
            ->required('description', $desc, 'Description')->maxLen('description', $desc, 120, 'Description')
            ->required('expense_date', $date, 'Date')->date('expense_date', $date, 'Date', true)
            ->maxLen('notes', $notes, 500, 'Notes')
            ->in('split_method', $method, SplitCalculator::METHODS, 'split method');
        if (!(new CategoryModel())->isActive($cat)) {
            $v->add('category_id', 'Choose a category.');
        }
        $total = $v->try(fn() => Money::parse((string) ($in['total_amount'] ?? ''), 'total_amount'));

        if ($groupId === null) {
            // personal expense: the actor pays and carries the whole amount
            $payer = $actor;
            $participants = [$actor];
            $method = 'equal';
        } else {
            $members = $this->groups->activeMemberIds($groupId);
            $payer = (int) ($in['paid_by'] ?? 0);
            if (!in_array($payer, $members, true)) {
                $v->add('paid_by', 'The payer must be an active group member.');
            }
            $participants = array_values(array_unique(array_map('intval', (array) ($in['participants'] ?? []))));
            if ($participants === []) {
                $v->add('participants', 'Select at least one participant.');
            } elseif (array_diff($participants, $members) !== []) {
                $v->add('participants', 'Every participant must be an active group member.');
            }
        }

        $tags = [];
        foreach (explode(',', mb_strtolower((string) ($in['tags'] ?? ''))) as $t) {
            $t = preg_replace('/[^a-z0-9\-]+/', '-', trim($t));
            $t = trim((string) $t, '-');
            if ($t !== '') {
                $tags[] = mb_substr($t, 0, 30);
            }
        }
        $tags = array_slice(array_values(array_unique($tags)), 0, 10);

        $split = null;
        if (!$v->fails() && $total !== null) {
            $shareIn = (array) ($in['share'] ?? []);
            $inputs = [];
            foreach ($participants as $uid) {
                $raw = $shareIn[$uid] ?? '';
                $inputs[$uid] = is_string($raw) ? $raw : '';
            }
            $split = $v->try(fn() => $this->calc->calculate($method, $total, $payer, $inputs));
        }
        $v->throw();

        return [
            'row' => [
                'paid_by' => $payer, 'category_id' => $cat, 'description' => $desc,
                'total_amount' => Money::fromPaise($total), 'split_method' => $method,
                'expense_date' => $date, 'notes' => $notes !== '' ? $notes : null,
            ],
            'shares' => $split['shares'],
            'inputs' => $split['inputs'],
            'tags'   => $tags,
        ];
    }

    /** Turn trigger SIGNALs into form errors; let everything else bubble up. */
    private function friendly(\Throwable $t): \Throwable
    {
        if ($t instanceof \PDOException && ($msg = Database::signalMessage($t)) !== null) {
            return ValidationException::single('participants', $msg);
        }
        return $t;
    }
}
