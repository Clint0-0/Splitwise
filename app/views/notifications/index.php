<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">Notifications</h1>
  <form method="post" action="<?= e(url('/notifications/read-all')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Mark all as read</button></form>
</div>
<div class="list-group">
  <?php if (!$notifications): ?><div class="list-group-item text-muted">No notifications.</div><?php endif; ?>
  <?php foreach ($notifications as $n): ?>
    <form method="post" action="<?= e(url('/notifications/' . (int) $n['notification_id'] . '/open')) ?>" class="list-group-item list-group-item-action<?= (int) $n['is_read'] === 0 ? ' list-group-item-primary' : '' ?>">
      <?= csrf_field() ?>
      <button class="btn p-0 text-start w-100 border-0 bg-transparent">
        <div class="d-flex justify-content-between"><strong><?= e($n['title']) ?></strong><small class="text-muted"><?= e(substr($n['created_at'], 0, 16)) ?></small></div>
        <div class="small"><?= e($n['message']) ?></div>
      </button>
    </form>
  <?php endforeach; ?>
</div>
<div class="mt-3"><?php require __DIR__ . '/../partials/pagination.php'; ?></div>
