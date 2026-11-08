<h1 class="h3 mb-3">Administration</h1>
<?php require __DIR__ . '/_nav.php'; ?>
<ul class="nav nav-pills mb-3">
  <li class="nav-item"><a class="nav-link<?= $table === null ? ' active' : '' ?>" href="<?= e(url('/admin/activity')) ?>">All</a></li>
  <?php foreach ($tables as $t): ?>
    <li class="nav-item"><a class="nav-link<?= $table === $t ? ' active' : '' ?>" href="<?= e(url('/admin/activity?table=' . $t)) ?>"><?= e($t) ?></a></li>
  <?php endforeach; ?>
</ul>
<div class="card"><div class="card-body">
  <p class="small text-muted">Written by database triggers (audit_logs). The application account cannot change or delete these rows.</p>
  <?php require __DIR__ . '/_logs.php'; ?>
  <div class="mt-3"><?php require __DIR__ . '/../partials/pagination.php'; ?></div>
</div></div>
