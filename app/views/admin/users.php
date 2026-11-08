<h1 class="h3 mb-3">Administration</h1>
<?php require __DIR__ . '/_nav.php'; ?>
<form method="get" class="d-flex gap-2 mb-3" style="max-width: 420px" role="search">
  <input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Search name, username, email" aria-label="Search users">
  <button class="btn btn-sm btn-outline-primary">Search</button>
</form>
<div class="card"><div class="card-body table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Groups</th><th>Last login</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['full_name']) ?></td><td>@<?= e($u['username']) ?></td><td><?= e($u['email']) ?></td>
        <td><?= e($u['role']) ?></td><td><?= (int) $u['group_count'] ?></td>
        <td class="small"><?= e($u['last_login_at'] ?? '—') ?></td>
        <td><?= (int) $u['is_active'] === 1 ? '<span class="badge text-bg-success">active</span>' : '<span class="badge text-bg-secondary">deactivated</span>' ?></td>
        <td><?php if ((int) $u['user_id'] !== $me): ?>
          <form method="post" action="<?= e(url('/admin/users/' . (int) $u['user_id'] . '/toggle')) ?>"><?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-<?= (int) $u['is_active'] === 1 ? 'danger' : 'success' ?>"><?= (int) $u['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?></button></form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="mt-3"><?php require __DIR__ . '/../partials/pagination.php'; ?></div>
</div></div>
