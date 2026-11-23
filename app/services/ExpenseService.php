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
                $this->expenses->deleteItems($expenseId);    // bill lines + item shares (CASCADE)
                $this->expenses->deleteCharges($expenseId);
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
        // itemized bill: lines after the splits, because item shares reference
        // expense_splits (expense_id, user_id) through a composite FK
        foreach ($data['items'] as $line => $it) {
            $itemId = $this->expenses->addItem($expenseId, $line, $it['name'], $it['qty'], Money::fromPaise($it['amount']));
            foreach ($it['shares'] as $uid => $paise) {
                $this->expenses->addItemShare($itemId, $expenseId, $uid, Money::fromPaise($paise), Money::fromPaise($it['weights'][$uid] ?? 100));
            }
        }
        foreach ($data['charges'] as $line => $c) {
            $this->expenses->addCharge($expenseId, $line, $c['kind'], $c['label'], Money::fromPaise($c['amount']));
        }
        if ($data['items'] !== [] && $this->expenses->itemGap($expenseId) !== '0.00') {
            throw new \LogicException('Item share total mismatch.');   // rolls back
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
            $participants = $method === 'itemized'
                ? $this->itemParticipants((array) ($in['items'] ?? []))
                : array_values(array_unique(array_map('intval', (array) ($in['participants'] ?? []))));
            if ($participants === [] && $method !== 'itemized') {
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
        $items = [];
        $charges = [];
        if ($method === 'itemized' && $groupId !== null) {
            // a scanned bill is saved only after the person says they checked it against the paper bill
            if (($in['scanned'] ?? '') === '1' && ($in['scan_reviewed'] ?? '') !== '1') {
                $v->add('scan_reviewed', 'Tick the box to confirm you checked the scanned lines against the bill.');
            }
            $rows = $this->parseItems((array) ($in['items'] ?? []), $v);
            $charges = $this->parseCharges((array) ($in['charges'] ?? []), $v);
            if (!$v->fails() && $total !== null && $charges !== []) {
                $itemsSum = array_sum(array_column($rows, 'amount'));
                $chargeSum = array_sum(array_column($charges, 'amount'));
                if ($itemsSum + $chargeSum !== $total) {
                    $v->add('charges', sprintf('Items (%s) and charges (%s) add up to %s, but the bill total is %s. Fix a line or the total.',
                        inr(Money::fromPaise($itemsSum)), inr(Money::fromPaise($chargeSum)),
                        inr(Money::fromPaise($itemsSum + $chargeSum)), inr(Money::fromPaise($total))));
                }
            }
            if (!$v->fails() && $total !== null) {
                $res = $v->try(fn() => $this->calc->itemized($total, $payer,
                    array_map(fn($r) => ['amount' => $r['amount'], 'users' => $r['users'], 'weights' => $r['weights']], $rows)));
                if ($res !== null) {
                    $split = ['shares' => $res['shares'],
                              'inputs' => array_fill_keys(array_keys($res['shares']), ['percentage' => null, 'units' => null, 'adjustment' => null])];
                    $line = 0;
                    foreach ($rows as $k => $r) {
                        $items[++$line] = ['name' => $r['name'], 'qty' => $r['qty'], 'amount' => $r['amount'],
                                           'shares' => $res['items'][$k], 'weights' => $r['weights']];
                    }
                }
            }
        } elseif (!$v->fails() && $total !== null) {
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
            'items'  => $items,
            'charges' => $charges,
        ];
    }

    public const CHARGE_KINDS = ['tax' => 'Tax / GST', 'service' => 'Service charge', 'discount' => 'Discount', 'other' => 'Other / round-off'];

    /**
     * Validate charge lines. Amounts are typed as positive numbers; a discount is stored
     * negative; "other" (e.g. round-off) may be typed with a minus sign. Blank rows ignored.
     * @return array<int,array{kind:string, label:string, amount:int}> keyed by line number 1..10
     */
    private function parseCharges(array $rows, Validator $v): array
    {
        $out = [];
        $line = 0;
        foreach ($rows as $i => $r) {
            if (!is_array($r) || trim((string) ($r['amount'] ?? '')) === '') {
                continue;
            }
            $kind = (string) ($r['kind'] ?? '');
            if (!isset(self::CHARGE_KINDS[$kind])) {
                $v->add("charge_$i", 'Choose what this charge is.');
                continue;
            }
            $raw = str_replace('+', '', trim((string) $r['amount']));
            $amt = $v->try(fn() => Money::parse($raw, "charge_$i", false, $kind === 'other'));
            if ($amt === null) {
                continue;
            }
            if ($amt === 0) {
                $v->add("charge_$i", 'A charge cannot be zero.');
                continue;
            }
            if ($kind === 'discount') {
                $amt = -$amt;
            }
            $label = trim((string) ($r['label'] ?? ''));
            $label = mb_substr($label !== '' ? $label : self::CHARGE_KINDS[$kind], 0, 60);
            if (++$line > 10) {
                $v->add('charges', 'A bill can have at most 10 charge lines.');
                break;
            }
            $out[$line] = ['kind' => $kind, 'label' => $label, 'amount' => $amt];
        }
        return $out;
    }

    /** User ids ticked on any non-empty bill line (the participants of an itemized expense). */
    private function itemParticipants(array $rows): array
    {
        $ids = [];
        foreach ($rows as $r) {
            if (is_array($r) && !$this->blankItem($r)) {
                foreach ((array) ($r['who'] ?? []) as $u) {
                    if (is_string($u) && ctype_digit($u)) {
                        $ids[(int) $u] = true;
                    }
                }
                foreach ((array) ($r['units'] ?? []) as $uid => $raw) {   // units typed = ticked
                    if (is_int($uid) && preg_match('/^\d{1,6}(\.\d{1,2})?$/', trim((string) $raw)) && (float) $raw > 0) {
                        $ids[$uid] = true;
                    }
                }
            }
        }
        return array_keys($ids);
    }

    private function blankItem(array $r): bool
    {
        return trim((string) ($r['name'] ?? '')) === '' && trim((string) ($r['amount'] ?? '')) === '';
    }

    /**
     * Validate bill lines. Errors are keyed item_<row index> so the form can show them on
     * the right row. Fully blank rows (no name, no amount) are ignored.
     * @return array<int,array{name:string, qty:string, amount:int, users:list<int>}>
     */
    private function parseItems(array $rows, Validator $v): array
    {
        $out = [];
        foreach ($rows as $i => $r) {
            if (!is_array($r) || $this->blankItem($r)) {
                continue;
            }
            $name = trim((string) ($r['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 80) {
                $v->add("item_$i", $name === '' ? 'Give this item a name.' : 'Item names can be at most 80 characters.');
                continue;
            }
            $qtyRaw = trim((string) ($r['qty'] ?? ''));
            if (preg_match('/^\d{6,}/', $qtyRaw) === 1) {   // quantity is DECIMAL(7,2)
                $v->add("item_$i", 'Quantity can be at most 99999.99.');
                continue;
            }
            $qty = $v->try(fn() => Money::parseHundredths($qtyRaw === '' ? '1' : $qtyRaw, "item_$i", 9999999));
            if ($qty !== null && $qty <= 0) {
                $v->add("item_$i", 'Quantity must be more than 0.');
                continue;
            }
            $amount = $v->try(fn() => Money::parse((string) ($r['amount'] ?? ''), "item_$i"));
            $users = [];
            foreach ((array) ($r['who'] ?? []) as $u) {
                if (is_string($u) && ctype_digit($u)) {
                    $users[] = (int) $u;
                }
            }
            // uneven / quantity shares: units typed for a person (blank = 1); typing units ticks them
            $weights = [];
            $badUnits = false;
            foreach ((array) ($r['units'] ?? []) as $uid => $raw) {
                $raw = trim((string) $raw);
                if ($raw === '' || !is_int($uid)) {
                    continue;
                }
                $w = $v->try(fn() => Money::parseHundredths($raw, "item_$i", 999999));
                if ($w === null || $w <= 0) {
                    if ($w !== null && in_array($uid, $users, true)) {
                        $v->add("item_$i", 'Units must be more than 0 (untick the person instead).');
                    }
                    $badUnits = $badUnits || $w === null || in_array($uid, $users, true);
                    continue;
                }
                $weights[$uid] = $w;
                $users[] = $uid;
            }
            if ($badUnits) {
                continue;
            }
            if ($users === [] && $amount !== null) {
                $v->add("item_$i", 'Tick who had this item.');
                continue;
            }
            if ($qty === null || $amount === null) {
                continue;
            }
            $users = array_values(array_unique($users));
            $out[$i] = ['name' => $name, 'qty' => Money::fromPaise($qty), 'amount' => $amount, 'users' => $users,
                        'weights' => array_replace(array_fill_keys($users, 100), $weights)];
        }
        if ($out === [] && !$v->fails()) {
            $v->add('items', 'Add at least one item from the bill.');
        }
        if (count($out) > SplitCalculator::MAX_ITEMS) {
            $v->add('items', 'A bill can have at most ' . SplitCalculator::MAX_ITEMS . ' items.');
        }
        return $out;
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
