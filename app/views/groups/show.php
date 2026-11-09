<?php
$gid = (int) $group['group_id'];
$isAdmin = $group['my_role'] === 'admin';
$archived = (int) $group['is_archived'] === 1;
$active = array_values(array_filter($members, fn($m) => $m['status'] === 'active'));
$former = array_filter($members, fn($m) => $m['status'] !== 'active');
$mine = null;
foreach ($active as $m) { if ((int) $m['user_id'] === $uid) { $mine = $m; } }
$kinds = ['trip' => 'Trip', 'home' => 'Home', 'family' => 'Family', 'friends' => 'Friends', 'work' => 'Work', 'other' => 'Group'];
?>
<div class="page-head">
  <div>
    <h1 class="page-title"><?= e($group['name']) ?></h1>
    <p class="page-sub"><?= e($kinds[$group['group_type']] ?? 'Group') ?> with <?= count($active) ?> members<?= $group['description'] ? '. ' . e($group['description']) : '' ?>
      <?php if ($archived): ?> <span class="pill pill-warn"><i class="bi bi-archive" aria-hidden="true"></i> archived, read-only</span><?php endif; ?></p>
  </div>
  <div class="page-actions">
    <?php if (!$archived): ?>
      <a class="btn btn-primary" href="<?= e(url('/expenses/create?group=' . $gid)) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add expense</a>
    <?php endif; ?>
    <a class="btn btn-settle" href="<?= e(url("/groups/$gid/settle")) ?>"><i class="bi bi-cash-coin" aria-hidden="true"></i> Settle up</a>
    <?php if ($isAdmin): ?>
      <div class="dropdown">
        <button class="btn btn-quiet" data-bs-toggle="dropdown" aria-expanded="false">Manage</button>
        <div class="dropdown-menu dropdown-menu-end p-1">
          <?php if (!$archived): ?><a class="dropdown-item" href="<?= e(url("/groups/$gid/edit")) ?>">Edit name and details</a><?php endif; ?>
          <form method="post" action="<?= e(url("/groups/$gid/archive")) ?>"><?= csrf_field() ?>
            <input type="hidden" name="archive" value="<?= $archived ? '0' : '1' ?>">
            <button class="dropdown-item"><?= $archived ? 'Unarchive group' : 'Archive group (read-only)' ?></button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?= field_error($errors, 'member') ?>

<?php if ($mine): [$fig, $words, $cls] = net_parts((string) $mine['net_balance']);
  $groupTotal = '0.00'; foreach ($members as $m) { $groupTotal = App\Helpers\Money::fromPaise(App\Helpers\Money::toPaise($groupTotal) + App\Helpers\Money::toPaise((string) $m['total_paid'])); } ?>
<dl class="summary" aria-label="Your balance in this group">
  <div class="is-key"><dt>Your balance in this group</dt> <dd class="<?= $cls ?>"><?= e($fig) ?> <small><?= e($words) ?></small></dd></div>
  <div><dt>You paid</dt> <dd><?= e(inr($mine['total_paid'])) ?></dd></div>
  <div><dt>Your share</dt> <dd><?= e(inr($mine['total_owed'])) ?></dd></div>
  <div><dt>Group has spent</dt> <dd><?= e(inr($groupTotal)) ?></dd></div>
</dl>
<?php endif; ?>

<div class="grid-2">
  <div class="stack">
    <section class="section" aria-labelledby="sug-h">
      <h2 id="sug-h">Suggested settlements</h2>
      <p class="note mb-2">The fewest payments that settle everyone. Nothing is paid until someone records it.</p>
      <?php if (!$suggestions): ?>
        <div class="empty"><i class="bi bi-check2-circle" aria-hidden="true"></i><p>Everyone is settled up.</p></div>
      <?php else: ?>
      <ul class="slips">
        <?php foreach ($suggestions as $s): $meFrom = $s['from'] === $uid; $meTo = $s['to'] === $uid; ?>
          <li class="slip<?= $meFrom ? ' is-owes' : ($meTo ? ' is-gets' : '') ?>" data-suggestion>
            <span class="avatar" aria-hidden="true"><?= e(initials($s['from_name'])) ?></span>
            <div class="slip-who"><strong><?= $meFrom ? 'You' : e($s['from_name']) ?></strong> <?= $meFrom ? 'pay' : 'pays' ?> <strong><?= $meTo ? 'you' : e($s['to_name']) ?></strong></div>
            <div class="slip-actions">
              <span class="slip-amount"><?= e(inr($s['amount'])) ?></span>
              <?php if ($meFrom || $meTo): ?>
                <a class="btn btn-sm btn-settle" href="<?= e(url("/groups/$gid/settle?" . http_build_query(['payer' => $s['from'], 'payee' => $s['to'], 'amount' => $s['amount']]))) ?>">Record</a>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="iou-h">
      <h2 id="iou-h">Who owes whom</h2>
      <?php $showGroupName = false; require __DIR__ . '/../partials/slips.php'; ?>
    </section>

    <section class="panel" aria-labelledby="mem-h">
      <h2 id="mem-h">Members</h2>
      <div class="table-responsive" tabindex="0" role="region" aria-label="Scrollable table">
      <table class="table">
        <thead><tr><th scope="col">Member</th><th scope="col" class="num">Balance</th><?php if ($isAdmin && !$archived): ?><th scope="col"><span class="visually-hidden">Actions</span></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($active as $m): ?>
          <tr>
            <td class="who"><span class="fw-semibold"><?= e($m['full_name']) ?></span><?= (int) $m['user_id'] === $uid ? ' (you)' : '' ?>
              <?= $m['role'] === 'admin' ? ' <span class="pill pill-admin">admin</span>' : '' ?>
              <div class="small text-muted">paid <?= e(inr($m['total_paid'])) ?> · share <?= e(inr($m['total_owed'])) ?></div></td>
            <td class="num small text-nowrap"><?php $net = $m['net_balance']; require __DIR__ . '/../partials/balance.php'; ?></td>
            <?php if ($isAdmin && !$archived): ?>
            <td class="text-end">
              <?php if ((int) $m['user_id'] !== $uid): ?>
              <div class="dropdown">
                <button class="btn btn-sm btn-link" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for <?= e($m['full_name']) ?>"><i class="bi bi-three-dots"></i></button>
                <div class="dropdown-menu dropdown-menu-end p-1">
                  <form method="post" action="<?= e(url("/groups/$gid/members/" . (int) $m['user_id'] . '/role')) ?>"><?= csrf_field() ?>
                    <input type="hidden" name="role" value="<?= $m['role'] === 'admin' ? 'member' : 'admin' ?>">
                    <button class="dropdown-item"><?= $m['role'] === 'admin' ? 'Remove admin rights' : 'Make admin' ?></button>
                  </form>
                  <form method="post" action="<?= e(url("/groups/$gid/members/" . (int) $m['user_id'] . '/remove')) ?>"><?= csrf_field() ?>
                    <button class="dropdown-item text-owes">Remove from group</button>
                  </form>
                </div>
              </div>
              <?php endif; ?>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php if ($former): ?><p class="note mt-2 mb-0">Former members: <?= e(implode(', ', array_column($former, 'full_name'))) ?></p><?php endif; ?>

      <?php if (!$archived): ?>
      <form method="post" action="<?= e(url("/groups/$gid/invite")) ?>" class="mt-3"><?= csrf_field() ?>
        <label class="form-label" for="invite">Invite someone</label>
        <div class="d-flex gap-2">
          <input class="form-control<?= is_invalid($errors, 'invite') ?>" id="invite" name="invite" placeholder="Their username or email" value="<?= e(old($old, 'invite')) ?>">
          <button class="btn btn-quiet">Invite</button>
        </div>
        <?= field_error($errors, 'invite') ?>
      </form>
      <?php foreach ($pending as $p): ?>
        <div class="d-flex justify-content-between align-items-center small mt-2">
          <span><?= e($p['full_name']) ?> (@<?= e($p['username']) ?>) has not answered yet</span>
          <?php if ($isAdmin || (int) $p['invited_by'] === $uid): ?>
            <form method="post" action="<?= e(url('/invitations/' . (int) $p['invitation_id'] . '/cancel')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link text-owes">Cancel invitation</button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <form method="post" action="<?= e(url("/groups/$gid/leave")) ?>"><?= csrf_field() ?>
      <button class="btn btn-sm btn-danger-quiet">Leave group</button>
      <span class="note ms-2">Only possible once you are settled up.</span>
    </form>
  </div>

  <div class="stack">
    <section class="panel" aria-labelledby="exp-h">
      <div class="panel-head"><h2 id="exp-h">Expenses</h2><a href="<?= e(url('/expenses?group=' . $gid)) ?>">See all</a></div>
      <?php $showGroup = false; require __DIR__ . '/../partials/expense_rows.php'; ?>
    </section>

    <section class="panel" aria-labelledby="set-h">
      <h2 id="set-h">Payments</h2>
      <?php if (!$settlements): ?>
        <div class="empty"><i class="bi bi-cash-coin" aria-hidden="true"></i><p>No payments recorded yet.</p></div>
      <?php else: ?>
      <ul class="rows">
        <?php foreach ($settlements as $s): [$day, $mon] = date_parts($s['settlement_date']); ?>
          <li><div class="row-item">
            <div class="row-date" aria-hidden="true"><b><?= e($day) ?></b><span><?= e($mon) ?></span></div>
            <div class="row-main"><span class="fw-semibold"><?= e($s['payer_name']) ?> paid <?= e($s['payee_name']) ?></span>
              <div class="row-sub"><?php require __DIR__ . '/../settlements/_status.php'; ?> <?= e(str_replace('_', ' ', $s['method'])) ?></div></div>
            <div class="row-amounts"><span class="total"><?= e(inr($s['amount'])) ?></span></div>
          </div></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>

    <?php if ($budgets): ?>
    <section class="panel" aria-labelledby="bud-h">
      <h2 id="bud-h">Group budget</h2>
      <?php foreach ($budgets as $b): require __DIR__ . '/../budgets/_bar.php'; endforeach; ?>
    </section>
    <?php endif; ?>
  </div>
</div>
