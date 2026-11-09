<?php
$net = (string) $overall['net_balance'];
$cmp = bccomp_str($net, '0');
$first = explode(' ', (string) (App\Core\Auth::user()['full_name'] ?? ''))[0];
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Hi <?= e($first) ?></h1>
  </div>
</div>

<section class="hero" aria-label="Your overall balance">
  <?php if ($cmp > 0): ?>
    <p class="hero-line">Across all your groups, you get back</p>
    <span class="hero-figure text-gets"><?= e(inr($net)) ?></span>
  <?php elseif ($cmp < 0): ?>
    <p class="hero-line">Across all your groups, you owe</p>
    <span class="hero-figure text-owes"><?= e(inr(abs_amount($net))) ?></span>
  <?php else: ?>
    <p class="hero-line">Across all your groups, you are</p>
    <span class="hero-figure">settled up</span>
  <?php endif; ?>
  <div class="hero-split">
    <span>Others owe you <strong class="amount"><?= e(inr($overall['receivable'])) ?></strong></span>
    <span>You owe others <strong class="amount"><?= e(inr($overall['payable'])) ?></strong></span>
  </div>
</section>

<?php if ($invitations): ?>
  <section class="todo" aria-labelledby="inv-h">
    <h2 id="inv-h">Group invitations</h2>
    <?php foreach ($invitations as $i): ?>
      <div class="todo-item">
        <span><strong><?= e($i['inviter_name']) ?></strong> invited you to <strong><?= e($i['group_name']) ?></strong></span>
        <form method="post" action="<?= e(url('/invitations/' . (int) $i['invitation_id'] . '/accept')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary">Accept</button></form>
        <form method="post" action="<?= e(url('/invitations/' . (int) $i['invitation_id'] . '/decline')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-quiet">Decline</button></form>
      </div>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<?php if ($toConfirm): ?>
  <section class="todo" aria-labelledby="conf-h">
    <h2 id="conf-h">Did you receive this money?</h2>
    <?php foreach ($toConfirm as $s): ?>
      <div class="todo-item">
        <span><?= e($s['payer_name']) ?> says they paid you <strong class="amount"><?= e(inr($s['amount'])) ?></strong> on <?= e(nice_date($s['settlement_date'])) ?> (<?= e($s['group_name']) ?>).</span>
        <form method="post" action="<?= e(url('/settlements/' . (int) $s['settlement_id'] . '/confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="/dashboard"><button class="btn btn-sm btn-confirm">Yes, confirm</button></form>
        <form method="post" action="<?= e(url('/settlements/' . (int) $s['settlement_id'] . '/reject')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="/dashboard"><button class="btn btn-sm btn-danger-quiet">Not received</button></form>
      </div>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<?php if ($budgetAlerts): ?>
  <div class="alert alert-warning mt-3" role="status">
    <strong>Budgets running out:</strong>
    <?php foreach ($budgetAlerts as $b): ?>
      <div><?= e($b['name']) ?>: <?= e(inr($b['spent'])) ?> of <?= e(inr($b['limit_amount'])) ?> used (<?= e(rtrim(rtrim((string) $b['utilization_pct'], '0'), '.')) ?>%)</div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="grid-2 mt-4">
  <div class="stack">
    <section class="section" aria-labelledby="iou-h">
      <h2 id="iou-h">Who owes whom</h2>
      <?php require __DIR__ . '/../partials/slips.php'; ?>
    </section>

    <section class="section" aria-labelledby="grp-h">
      <div class="panel-head"><h2 id="grp-h">Your groups</h2><a href="<?= e(url('/groups/create')) ?>">New group</a></div>
      <?php if (!$groups): ?>
        <div class="empty"><i class="bi bi-people" aria-hidden="true"></i><p>You are not in a group yet. Make one for your flat, a trip or your friends.</p>
          <a class="btn btn-primary" href="<?= e(url('/groups/create')) ?>">Create a group</a></div>
      <?php else: ?>
        <ul class="rows panel py-1">
          <?php foreach ($groups as $g): ?>
            <li class="d-flex justify-content-between align-items-center gap-3 py-2 border-bottom">
              <a class="fw-bold text-decoration-none" href="<?= e(url('/groups/' . (int) $g['group_id'])) ?>"><?= e($g['name']) ?></a>
              <?php $net = $g['net_balance']; require __DIR__ . '/../partials/balance.php'; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <section class="panel" aria-labelledby="rec-h">
    <div class="panel-head"><h2 id="rec-h">Recent expenses</h2><a href="<?= e(url('/expenses')) ?>">All expenses</a></div>
    <?php $expenses = $recent; require __DIR__ . '/../partials/expense_rows.php'; ?>
  </section>
</div>
