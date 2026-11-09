<div class="page-head"><h1 class="page-title">Administration</h1></div>
<?php require __DIR__ . '/_nav.php'; ?>
<ul class="nav nav-pills mb-3">
  <li class="nav-item"><a class="nav-link<?= $table === null ? ' active' : '' ?>" href="<?= e(url('/admin/activity')) ?>">All</a></li>
  <?php foreach ($tables as $t): ?>
    <li class="nav-item"><a class="nav-link<?= $table === $t ? ' active' : '' ?>" href="<?= e(url('/admin/activity?table=' . $t)) ?>"><?= e($t) ?></a></li>
  <?php endforeach; ?>
</ul>
<section class="panel" aria-label="Activity log">
  <p class="note">Written by database triggers into audit_logs. The application's database account cannot change or delete these rows.</p>
  <?php require __DIR__ . '/_logs.php'; ?>
  <div class="mt-3"><?php require __DIR__ . '/../partials/pagination.php'; ?></div>
</section>
