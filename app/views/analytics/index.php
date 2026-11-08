<h1 class="h3 mb-4">Analytics</h1>
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card card-body"><div class="small text-muted">Personal spending (all time)</div><div class="fs-5 amount"><?= e(inr($totals['personal_spend'])) ?></div></div></div>
  <div class="col-md-4"><div class="card card-body"><div class="small text-muted">Your share of group spending</div><div class="fs-5 amount"><?= e(inr($totals['group_share'])) ?></div></div></div>
</div>
<div class="row g-4" id="analytics" data-base="<?= e(url('/api/analytics')) ?>">
  <div class="col-lg-6"><div class="card"><div class="card-body"><h2 class="h6">Your spending by category (last 12 months)</h2><canvas id="chartCategories" height="240" role="img" aria-label="Spending by category"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-body"><h2 class="h6">Monthly spending (your share)</h2><canvas id="chartMonthly" height="240" role="img" aria-label="Monthly spending"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-body"><h2 class="h6">Group spending</h2><canvas id="chartGroups" height="240" role="img" aria-label="Group spending"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-body"><h2 class="h6">Paid vs your share, per group</h2><canvas id="chartPaidOwed" height="240" role="img" aria-label="Paid versus owed"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-body"><h2 class="h6">Settlements sent / received</h2><canvas id="chartSettlements" height="240" role="img" aria-label="Settlements"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-body">
    <h2 class="h6">Largest expenses you shared</h2>
    <table class="table table-sm mb-0"><tbody>
    <?php foreach ($top as $t): ?>
      <tr><td><a href="<?= e(url('/expenses/' . (int) $t['expense_id'])) ?>"><?= e($t['description']) ?></a><div class="small text-muted"><?= e($t['group_name']) ?> · <?= e($t['expense_date']) ?></div></td>
        <td class="text-end amount"><?= e(inr($t['total_amount'])) ?><div class="small text-muted">your share <?= e(inr($t['share_amount'])) ?></div></td></tr>
    <?php endforeach; ?>
    <?php if (!$top): ?><tr><td class="text-muted">No expenses yet.</td></tr><?php endif; ?>
    </tbody></table>
  </div></div></div>
</div>
