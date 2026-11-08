<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">Groups</h1>
  <a class="btn btn-primary" href="<?= e(url('/groups/create')) ?>"><i class="bi bi-plus"></i> New group</a>
</div>

<?php if ($invitations): ?>
  <div class="card mb-4 border-primary"><div class="card-body">
    <h2 class="h6">Invitations</h2>
    <?php foreach ($invitations as $i): ?>
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="me-auto"><strong><?= e($i['inviter_name']) ?></strong> invited you to <strong><?= e($i['group_name']) ?></strong></span>
        <form method="post" action="<?= e(url('/invitations/' . (int) $i['invitation_id'] . '/accept')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary">Accept</button></form>
        <form method="post" action="<?= e(url('/invitations/' . (int) $i['invitation_id'] . '/decline')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Decline</button></form>
      </div>
    <?php endforeach; ?>
  </div></div>
<?php endif; ?>

<?php if (!$groups): ?>
  <div class="text-center text-muted py-5">
    <i class="bi bi-people display-4"></i>
    <p class="mt-2">No groups yet. Create one for a trip, your flat or your friends.</p>
  </div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($groups as $g): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card h-100<?= (int) $g['is_archived'] === 1 ? ' opacity-75' : '' ?>"><div class="card-body">
        <h2 class="h5"><a href="<?= e(url('/groups/' . (int) $g['group_id'])) ?>" class="stretched-link text-decoration-none"><?= e($g['name']) ?></a></h2>
        <p class="small text-muted mb-2"><?= e(ucfirst($g['group_type'])) ?> · <?= (int) $g['member_count'] ?> members
          <?= $g['role'] === 'admin' ? ' · <span class="badge text-bg-secondary">admin</span>' : '' ?>
          <?= (int) $g['is_archived'] === 1 ? ' · <span class="badge text-bg-warning">archived</span>' : '' ?></p>
        <div class="small">Total spent: <span class="amount"><?= e(inr($g['total_spent'])) ?></span></div>
        <div><?php $net = $g['net_balance']; require __DIR__ . '/../partials/balance.php'; ?></div>
      </div></div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
