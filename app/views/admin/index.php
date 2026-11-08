<h1 class="h3 mb-3">Administration</h1>
<?php require __DIR__ . '/_nav.php'; ?>
<div class="row g-3 mb-4">
  <?php foreach ([
      ['Users', $stats['users'] . ' (' . $stats['active_users'] . ' active)'],
      ['Groups', $stats['groups_total']],
      ['Expenses', $stats['expenses'] . ' · ' . inr($stats['expense_total'])],
      ['Confirmed settlements', $stats['settlements'] . ' · ' . inr($stats['settled_total'])],
      ['Pending settlements', $stats['pending_settlements']],
  ] as [$label, $value]): ?>
    <div class="col-md-4 col-lg"><div class="card card-body"><div class="small text-muted"><?= e($label) ?></div><div class="fs-5"><?= e((string) $value) ?></div></div></div>
  <?php endforeach; ?>
</div>
<div class="card"><div class="card-body">
  <h2 class="h6">Latest activity</h2>
  <?php $logs = $recent; require __DIR__ . '/_logs.php'; ?>
</div></div>
