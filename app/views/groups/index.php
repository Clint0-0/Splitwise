<?php $kinds = ['trip' => 'Trip', 'home' => 'Home', 'family' => 'Family', 'friends' => 'Friends', 'work' => 'Work', 'other' => 'Group']; ?>
<div class="page-head">
  <h1 class="page-title">Groups</h1>
  <div class="page-actions"><a class="btn btn-primary" href="<?= e(url('/groups/create')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> New group</a></div>
</div>

<?php if ($invitations): ?>
  <section class="todo mb-4" aria-labelledby="inv-h">
    <h2 id="inv-h">Invitations</h2>
    <?php foreach ($invitations as $i): ?>
      <div class="todo-item">
        <span><strong><?= e($i['inviter_name']) ?></strong> invited you to <strong><?= e($i['group_name']) ?></strong></span>
        <form method="post" action="<?= e(url('/invitations/' . (int) $i['invitation_id'] . '/accept')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary">Accept</button></form>
        <form method="post" action="<?= e(url('/invitations/' . (int) $i['invitation_id'] . '/decline')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-quiet">Decline</button></form>
      </div>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<?php if (!$groups): ?>
  <div class="empty panel">
    <i class="bi bi-people" aria-hidden="true"></i>
    <p>No groups yet. Make one for a trip, your flat or your friends, then invite people by username.</p>
    <a class="btn btn-primary" href="<?= e(url('/groups/create')) ?>">Create a group</a>
  </div>
<?php else: ?>
<div class="group-cards">
  <?php foreach ($groups as $g): $archived = (int) $g['is_archived'] === 1; ?>
    <article class="group-card<?= $archived ? ' is-archived' : '' ?>">
      <h2><a href="<?= e(url('/groups/' . (int) $g['group_id'])) ?>"><?= e($g['name']) ?></a></h2>
      <div class="group-kind"><?= e($kinds[$g['group_type']] ?? 'Group') ?>, <?= (int) $g['member_count'] ?> members, <?= e(inr($g['total_spent'])) ?> spent</div>
      <div>
        <?php if ($g['role'] === 'admin'): ?><span class="pill pill-admin">you are admin</span><?php endif; ?>
        <?php if ($archived): ?><span class="pill pill-warn">archived</span><?php endif; ?>
      </div>
      <div class="group-balance"><?php $net = $g['net_balance']; require __DIR__ . '/../partials/balance.php'; ?></div>
    </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>
