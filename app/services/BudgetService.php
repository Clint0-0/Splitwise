<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Helpers\Money;
use App\Helpers\Validator;
use App\Models\BudgetModel;
use App\Models\CategoryModel;
use App\Models\GroupModel;
use App\Models\NotificationModel;

/**
 * Budgets: personal (owner's SHARE of expenses) or group (group totals).
 * Spent/remaining are derived by v_budget_utilization — never stored.
 */
final class BudgetService
{
    public function __construct(private BudgetModel $budgets = new BudgetModel())
    {
    }

    public function create(int $actor, array $in): int
    {
        $d = $this->validate($in);
        $scope = (string) ($in['scope'] ?? 'personal');
        if ($scope === 'group') {
            $gid = (int) ($in['group_id'] ?? 0);
            (new GroupService())->requireAdmin($gid, $actor);
            $d += ['user_id' => null, 'group_id' => $gid];
        } else {
            $d += ['user_id' => $actor, 'group_id' => null];
        }
        return $this->budgets->create($d + ['created_by' => $actor]);
    }

    public function update(int $actor, int $id, array $in): void
    {
        $this->requireManage($actor, $id);
        $this->budgets->update($id, $this->validate($in));
    }

    public function delete(int $actor, int $id): void
    {
        $this->requireManage($actor, $id);
        $this->budgets->delete($id);
    }

    /** Owner of a personal budget, or admin of the group for a group budget. */
    public function requireManage(int $actor, int $id): array
    {
        $b = $this->budgets->find($id) ?? throw new HttpException(404);
        if ($b['user_id'] !== null) {
            if ((int) $b['user_id'] !== $actor) {
                throw new HttpException(403);
            }
        } else {
            (new GroupService())->requireAdmin((int) $b['group_id'], $actor);
        }
        return $b;
    }

    public function canView(int $actor, array $b): bool
    {
        if ($b['user_id'] !== null) {
            return (int) $b['user_id'] === $actor;
        }
        $m = (new GroupModel())->membership((int) $b['group_id'], $actor);
        return $m !== null && $m['status'] === 'active';
    }

    /**
     * After an expense is saved: warn owners/members once per budget when it
     * reaches its alert threshold or goes over.
     */
    public function checkThresholds(array $userIds, ?int $groupId): void
    {
        $notes = new NotificationModel();
        $groups = new GroupModel();
        foreach ($this->budgets->affected($userIds, $groupId) as $b) {
            if ($b['budget_status'] === 'ok' || $b['end_date'] < date('Y-m-d')) {
                continue;
            }
            $recipients = $b['user_id'] !== null ? [(int) $b['user_id']] : $groups->activeMemberIds((int) $b['group_id']);
            $over = $b['budget_status'] === 'over_budget';
            foreach ($recipients as $uid) {
                if ($notes->budgetWarned($uid, (int) $b['budget_id'], $b['start_date'])) {
                    continue;
                }
                $notes->create($uid, 'budget_warning', $over ? 'Budget exceeded' : 'Budget warning',
                    sprintf('%s: %s of %s used (%s%%).', $b['name'], inr($b['spent']), inr($b['limit_amount']),
                        rtrim(rtrim((string) $b['utilization_pct'], '0'), '.')),
                    'budget', (int) $b['budget_id']);
            }
        }
    }

    private function validate(array $in): array
    {
        $name   = trim((string) ($in['name'] ?? ''));
        $period = (string) ($in['period_type'] ?? 'monthly');
        $start  = trim((string) ($in['start_date'] ?? ''));
        $end    = trim((string) ($in['end_date'] ?? ''));
        $cat    = (string) ($in['category_id'] ?? '');
        $thr    = (string) ($in['alert_threshold'] ?? '80');

        $v = (new Validator())
            ->required('name', $name, 'Name')->maxLen('name', $name, 80, 'Name')
            ->in('period_type', $period, BudgetModel::PERIODS, 'period')
            ->required('start_date', $start, 'Start date')->date('start_date', $start, 'Start date')
            ->regex('alert_threshold', $thr, '/^(100|[1-9][0-9]?)$/', 'Alert threshold: 1–100 %.');
        if ($cat !== '' && !(new CategoryModel())->isActive((int) $cat)) {
            $v->add('category_id', 'Choose a valid category.');
        }
        $limit = $v->try(fn() => Money::parse((string) ($in['limit_amount'] ?? ''), 'limit_amount'));

        // fixed periods: end date follows from the start date
        if (!$v->fails() && $period !== 'custom') {
            $d = new \DateTime($start);
            $d->modify(['weekly' => '+6 days', 'monthly' => '+1 month -1 day', 'yearly' => '+1 year -1 day'][$period]);
            $end = $d->format('Y-m-d');
        }
        $v->required('end_date', $end, 'End date')->date('end_date', $end, 'End date');
        if (!$v->fails() && $end < $start) {
            $v->add('end_date', 'End date must be on or after the start date.');
        }
        $v->throw();

        return [
            'category_id' => $cat !== '' ? (int) $cat : null, 'name' => $name,
            'limit_amount' => Money::fromPaise($limit), 'period_type' => $period,
            'start_date' => $start, 'end_date' => $end, 'alert_threshold' => (int) $thr,
        ];
    }
}
