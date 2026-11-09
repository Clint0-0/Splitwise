<div class="page-head"><h1 class="page-title">Analytics</h1></div>

<dl class="stat-line mb-4">
  <div><dt>Spent on your own</dt><dd><?= e(inr($totals['personal_spend'])) ?></dd></div>
  <div><dt>Your share of group spending</dt><dd><?= e(inr($totals['group_share'])) ?></dd></div>
</dl>

<div class="grid-2 even" id="analytics" data-base="<?= e(url('/api/analytics')) ?>">
  <?php foreach ([
      ['chartCategories', 'Where your money went', 'Your share by category, last 12 months.'],
      ['chartMonthly', 'Month by month', 'Your share of all expenses each month.'],
      ['chartGroups', 'Spending per group', 'Everything each of your groups has spent.'],
      ['chartPaidOwed', 'What you paid vs. your share', 'Paying more than your share means others owe you.'],
      ['chartSettlements', 'Payments sent and received', 'Confirmed settlements per month.'],
  ] as [$id, $title, $desc]): ?>
    <section class="panel" aria-labelledby="<?= $id ?>-h">
      <h2 id="<?= $id ?>-h"><?= e($title) ?></h2>
      <p class="note"><?= e($desc) ?></p>
      <div class="chart-box"><canvas id="<?= $id ?>" role="img" aria-label="<?= e($title) ?> chart; the same numbers are in the table below"></canvas></div>
      <details class="mt-2"><summary class="small">Show as table</summary><div class="chart-table table-responsive" tabindex="0" data-table-for="<?= $id ?>"></div></details>
    </section>
  <?php endforeach; ?>

  <section class="panel" aria-labelledby="top-h">
    <h2 id="top-h">Biggest bills you shared</h2>
    <?php if (!$top): ?><div class="empty"><p>No expenses yet.</p></div><?php else: ?>
    <ul class="rows">
      <?php foreach ($top as $t): [$day, $mon] = date_parts($t['expense_date']); ?>
        <li><div class="row-item">
          <div class="row-date" aria-hidden="true"><b><?= e($day) ?></b><span><?= e($mon) ?></span></div>
          <div class="row-main"><a class="row-title" href="<?= e(url('/expenses/' . (int) $t['expense_id'])) ?>"><span><?= e($t['description']) ?></span></a>
            <div class="row-sub"><?= e($t['group_name']) ?></div></div>
          <div class="row-amounts"><span class="total"><?= e(inr($t['total_amount'])) ?></span><span class="share">your share <?= e(inr($t['share_amount'])) ?></span></div>
        </div></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>
