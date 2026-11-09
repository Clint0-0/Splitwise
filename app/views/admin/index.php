<div class="page-head"><h1 class="page-title">Administration</h1></div>
<?php require __DIR__ . '/_nav.php'; ?>
<dl class="stat-line mb-4">
  <div><dt>Users</dt><dd><?= (int) $stats['users'] ?></dd><dd class="note"><?= (int) $stats['active_users'] ?> active</dd></div>
  <div><dt>Groups</dt><dd><?= (int) $stats['groups_total'] ?></dd></div>
  <div><dt>Expenses</dt><dd><?= (int) $stats['expenses'] ?></dd><dd class="note"><?= e(inr($stats['expense_total'])) ?> in total</dd></div>
  <div><dt>Confirmed settlements</dt><dd><?= (int) $stats['settlements'] ?></dd><dd class="note"><?= e(inr($stats['settled_total'])) ?> paid back</dd></div>
  <div><dt>Waiting for confirmation</dt><dd><?= (int) $stats['pending_settlements'] ?></dd></div>
</dl>
<section class="panel" aria-labelledby="act-h">
  <div class="panel-head"><h2 id="act-h">Latest activity</h2><a href="<?= e(url('/admin/activity')) ?>">Full activity log</a></div>
  <?php $logs = $recent; require __DIR__ . '/_logs.php'; ?>
</section>
