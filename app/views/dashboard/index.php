<h1 class="h3 mb-4">Dashboard</h1>

<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card h-100"><div class="card-body">
    <div class="text-muted small">Overall balance</div>
    <div class="fs-4"><?php $net = $overall['net_balance']; require __DIR__ . '/../partials/balance.php'; ?></div>
  </div></div></div>
  <div class="col-md-4"><div class="card h-100"><div class="card-body">
    <div class="text-muted small">You are owed</div>
    <div class="fs-4 amount text-gets"><?= e(inr($overall['receivable'])) ?></div>
  </div></div></div>
  <div class="col-md-4"><div class="card h-100"><div class="card-body">
    <div class="text-muted small">You owe</div>
    <div class="fs-4 amount text-owes"><?= e(inr($overall['payable'])) ?></div>
  </div></div></div>
</div>

<?php if ($invitations): ?>
  <div class="card mb-4 border-primary"><div class="card-body">
    <h2 class="h6">Group invitations</h2>
    <?php foreach ($invitations as $i): ?>
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="me-auto"><strong><?= e($i['inviter_name']) ?></strong> invited you to <strong><?= e($i['group_name']) ?></strong></span>
        <form method="post" action="<?= e(url('/invitations/' . (int) $i['invitation_id'] . '/accept')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary">Accept</button></form>
        <form method="post" action="<?= e(url('/invitations/' . (int) $i['invitation_id'] . '/decline')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Decline</button></form>
      </div>
    <?php endforeach; ?>
  </div></div>
<?php endif; ?>

<?php if ($toConfirm): ?>
  <div class="card mb-4 border-warning"><div class="card-body">
    <h2 class="h6">Payments waiting for your confirmation</h2>
    <?php foreach ($toConfirm as $s): ?>
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="me-auto"><?= e($s['payer_name']) ?> says they paid you <strong class="amount"><?= e(inr($s['amount'])) ?></strong> (<?= e($s['group_name']) ?>, <?= e($s['settlement_date']) ?>)</span>
        <form method="post" action="<?= e(url('/settlements/' . (int) $s['settlement_id'] . '/confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="/dashboard"><button class="btn btn-sm btn-success">Confirm</button></form>
        <form method="post" action="<?= e(url('/settlements/' . (int) $s['settlement_id'] . '/reject')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="/dashboard"><button class="btn btn-sm btn-outline-danger">Reject</button></form>
      </div>
    <?php endforeach; ?>
  </div></div>
<?php endif; ?>

<?php if ($budgetAlerts): ?>
  <div class="alert alert-warning">
    <strong>Budget alerts:</strong>
    <?php foreach ($budgetAlerts as $b): ?>
      <div><?= e($b['name']) ?> — <?= e(inr($b['spent'])) ?> of <?= e(inr($b['limit_amount'])) ?> (<?= e($b['utilization_pct']) ?>%)</div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">Your groups</h2>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/groups/create')) ?>"><i class="bi bi-plus"></i> New group</a>
      </div>
      <?php if (!$groups): ?>
        <p class="text-muted mb-0">You are not in any group yet. Create one and invite friends.</p>
      <?php endif; ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($groups as $g): ?>
          <li class="list-group-item d-flex justify-content-between px-0">
            <a href="<?= e(url('/groups/' . (int) $g['group_id'])) ?>"><?= e($g['name']) ?></a>
            <?php $net = $g['net_balance']; require __DIR__ . '/../partials/balance.php'; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div></div>

    <div class="card mt-4"><div class="card-body">
      <h2 class="h6">Who owes whom (you)</h2>
      <?php if (!$pairs): ?><p class="text-muted mb-0">All settled up.</p><?php endif; ?>
      <?php foreach ($pairs as $p): ?>
        <div class="small mb-1">
          <?php if ((int) $p['debtor_id'] === $uid): ?>
            You owe <strong><?= e($p['creditor_name']) ?></strong> <span class="amount text-owes"><?= e(inr($p['remaining'])) ?></span>
          <?php else: ?>
            <strong><?= e($p['debtor_name']) ?></strong> owes you <span class="amount text-gets"><?= e(inr($p['remaining'])) ?></span>
          <?php endif; ?>
          <span class="text-muted">· <?= e($p['group_name']) ?></span>
        </div>
      <?php endforeach; ?>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="card"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">Recent expenses</h2>
        <a class="btn btn-sm btn-primary" href="<?= e(url('/expenses/create')) ?>"><i class="bi bi-plus"></i> Add expense</a>
      </div>
      <?php $expenses = $recent; require __DIR__ . '/../partials/expense_rows.php'; ?>
    </div></div>
  </div>
</div>
