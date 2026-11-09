<?php
/** One budget with its meter. @var array $b (row from v_budget_utilization) */
$pct = (float) $b['utilization_pct'];
$state = ['ok' => '', 'warning' => ' is-warning', 'over_budget' => ' is-over'][$b['budget_status']];
$left = (string) $b['remaining'];
$over = bccomp_str($left, '0') < 0;
$pctLabel = rtrim(rtrim((string) $b['utilization_pct'], '0'), '.');
?>
<div class="budget">
  <div class="budget-head">
    <span class="budget-name"><?= e($b['name']) ?></span>
    <span class="budget-figures"><?= e(inr($b['spent'])) ?> / <?= e(inr($b['limit_amount'])) ?></span>
  </div>
  <div class="meter<?= $state ?>" role="progressbar" aria-label="<?= e($b['name']) ?>: <?= e($pctLabel) ?>% used"
       aria-valuenow="<?= e((string) min(100, $pct)) ?>" aria-valuemin="0" aria-valuemax="100"><span style="width: <?= e((string) min(100, $pct)) ?>%"></span></div>
  <div class="d-flex flex-wrap justify-content-between gap-2 small">
    <span class="text-muted"><?= e($b['category_name'] ?? 'All categories') ?>, <?= e(nice_date($b['start_date'])) ?> to <?= e(nice_date($b['end_date'])) ?></span>
    <span class="<?= $over ? 'text-owes fw-semibold' : ($b['budget_status'] === 'warning' ? 'fw-semibold' : 'text-muted') ?>">
      <?= $over ? 'Over by ' . e(inr(abs_amount($left))) : e(inr($left)) . ' left' ?> (<?= e($pctLabel) ?>% used)</span>
  </div>
</div>
