<div class="page-head"><h1 class="page-title">Administration</h1></div>
<?php require __DIR__ . '/_nav.php'; ?>
<form method="get" class="d-flex gap-2 mb-3 form-narrow" role="search">
  <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Name, username or email" aria-label="Search users">
  <button class="btn btn-quiet">Search</button>
</form>
<section class="panel table-responsive" tabindex="0" role="region" aria-label="Users">
  <table class="table align-middle">
    <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Groups</th><th>Last login</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['full_name']) ?></td><td>@<?= e($u['username']) ?></td><td><?= e($u['email']) ?></td>
        <td><?= e($u['role']) ?></td><td><?= (int) $u['group_count'] ?></td>
        <td class="small text-nowrap"><?= $u['last_login_at'] ? e(date('j M Y, H:i', strtotime($u['last_login_at']))) : 'never' ?></td>
        <td><?= (int) $u['is_active'] === 1 ? '<span class="pill pill-confirmed">active</span>' : '<span class="pill pill-cancelled">deactivated</span>' ?></td>
        <td><?php if ((int) $u['user_id'] !== $me): ?>
          <form method="post" action="<?= e(url('/admin/users/' . (int) $u['user_id'] . '/toggle')) ?>"><?= csrf_field() ?>
            <button class="btn btn-sm <?= (int) $u['is_active'] === 1 ? 'btn-danger-quiet' : 'btn-quiet' ?>"><?= (int) $u['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?></button></form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="mt-3"><?php require __DIR__ . '/../partials/pagination.php'; ?></div>
</section>
