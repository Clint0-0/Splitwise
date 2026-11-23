<div class="page-head"><h1 class="page-title">Administration</h1></div>
<?php require __DIR__ . '/_nav.php'; ?>
<dl class="stat-line mb-4">
  <div><dt>Users</dt><dd><?= (int) $stats['users'] ?></dd><dd class="note"><?= (int) $stats['active_users'] ?> active</dd></div>
  <div><dt>Groups</dt><dd><?= (int) $stats['groups_total'] ?></dd></div>
  <div><dt>Expenses</dt><dd><?= (int) $stats['expenses'] ?></dd><dd class="note"><?= e(inr($stats['expense_total'])) ?> in total</dd></div>
  <div><dt>Confirmed settlements</dt><dd><?= (int) $stats['settlements'] ?></dd><dd class="note"><?= e(inr($stats['settled_total'])) ?> paid back</dd></div>
  <div><dt>Waiting for confirmation</dt><dd><?= (int) $stats['pending_settlements'] ?></dd></div>
</dl>
<section class="panel mb-4" aria-labelledby="scan-h">
  <h2 id="scan-h">Bill scanning (<?= e($scan['month']) ?>)</h2>
  <?php if ($scan['provider'] === 'off'): ?><p class="mb-0">Switched off (<code>SCAN_PROVIDER=off</code>).</p><?php else: ?>
  <dl class="stat-line mb-1">
    <div><dt>Scans this month</dt><dd><?= (int) $scan['scans'] ?></dd><dd class="note">limit <?= (int) $scan['limit'] ?></dd></div>
    <div><dt>Failed reads</dt><dd><?= (int) $scan['failed'] ?></dd></div>
    <div><dt>Estimated cost</dt><dd><?= e(inr(App\Helpers\Money::fromPaise($scan['cost_paise']))) ?></dd><dd class="note">estimate (<code>SCAN_COST_PAISE</code> × scans); check the provider's billing page</dd></div>
  </dl>
  <p class="note mb-0">Provider: <?= e($scan['provider']) ?>. When the monthly limit is reached, scanning stops for everyone until next month (manual entry still works).</p>
  <?php endif; ?>
</section>
<section class="panel" aria-labelledby="act-h">
  <div class="panel-head"><h2 id="act-h">Latest activity</h2><a href="<?= e(url('/admin/activity')) ?>">Full activity log</a></div>
  <?php $logs = $recent; require __DIR__ . '/_logs.php'; ?>
</section>
