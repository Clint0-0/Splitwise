<?php
/** Budget progress row. @var array $b (row from v_budget_utilization) */
$pct = (float) $b['utilization_pct'];
$cls = ['ok' => 'bg-success', 'warning' => 'bg-warning', 'over_budget' => 'bg-danger'][$b['budget_status']];
?>
<div class="mb-3">
  <div class="d-flex justify-content-between small">
    <span><strong><?= e($b['name']) ?></strong> <span class="text-muted">· <?= e($b['category_name'] ?? 'All categories') ?> · <?= e($b['start_date']) ?> → <?= e($b['end_date']) ?></span></span>
    <span class="amount"><?= e(inr($b['spent'])) ?> / <?= e(inr($b['limit_amount'])) ?></span>
  </div>
  <div class="progress" role="progressbar" aria-label="<?= e($b['name']) ?> used" aria-valuenow="<?= e(min(100, $pct)) ?>" aria-valuemin="0" aria-valuemax="100">
    <div class="progress-bar <?= $cls ?>" style="width: <?= e(min(100, $pct)) ?>%"><?= e($b['utilization_pct']) ?>%</div>
  </div>
  <div class="small <?= bccomp_str((string) $b['remaining'], '0') < 0 ? 'text-owes' : 'text-muted' ?>">
    <?= bccomp_str((string) $b['remaining'], '0') < 0 ? 'Over by ' . e(inr(ltrim((string) $b['remaining'], '-'))) : e(inr($b['remaining'])) . ' left' ?></div>
</div>
