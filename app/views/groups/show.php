<?php
$gid = (int) $group['group_id'];
$isAdmin = $group['my_role'] === 'admin';
$archived = (int) $group['is_archived'] === 1;
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h1 class="h3 mb-1"><?= e($group['name']) ?> <?= $archived ? '<span class="badge text-bg-warning align-middle">archived</span>' : '' ?></h1>
    <p class="text-muted mb-0"><?= e(ucfirst($group['group_type'])) ?><?= $group['description'] ? ' · ' . e($group['description']) : '' ?></p>
  </div>
  <div class="d-flex gap-2">
    <?php if (!$archived): ?>
      <a class="btn btn-primary" href="<?= e(url('/expenses/create?group=' . $gid)) ?>"><i class="bi bi-plus"></i> Add expense</a>
    <?php endif; ?>
    <a class="btn btn-outline-success" href="<?= e(url("/groups/$gid/settle")) ?>"><i class="bi bi-cash"></i> Settle up</a>
    <?php if ($isAdmin): ?>
      <a class="btn btn-outline-secondary" href="<?= e(url("/groups/$gid/edit")) ?>">Edit</a>
      <form method="post" action="<?= e(url("/groups/$gid/archive")) ?>"><?= csrf_field() ?>
        <input type="hidden" name="archive" value="<?= $archived ? '0' : '1' ?>">
        <button class="btn btn-outline-secondary"><?= $archived ? 'Unarchive' : 'Archive' ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?= field_error($errors, 'member') ?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card mb-4"><div class="card-body">
      <h2 class="h6">Balances</h2>
      <table class="table table-sm mb-0">
        <thead><tr><th>Member</th><th class="text-end">Paid</th><th class="text-end">Share</th><th class="text-end">Balance</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($members as $m): if ($m['status'] !== 'active') { continue; } ?>
          <tr>
            <td><?= e($m['full_name']) ?><?= (int) $m['user_id'] === $uid ? ' (you)' : '' ?>
              <?= $m['role'] === 'admin' ? '<span class="badge text-bg-secondary">admin</span>' : '' ?></td>
            <td class="text-end amount small"><?= e(inr($m['total_paid'])) ?></td>
            <td class="text-end amount small"><?= e(inr($m['total_owed'])) ?></td>
            <td class="text-end small"><?php $net = $m['net_balance']; require __DIR__ . '/../partials/balance.php'; ?></td>
            <td class="text-end">
              <?php if ($isAdmin && (int) $m['user_id'] !== $uid && !$archived): ?>
                <div class="dropdown">
                  <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="Member actions"><i class="bi bi-three-dots"></i></button>
                  <div class="dropdown-menu dropdown-menu-end p-2">
                    <form method="post" action="<?= e(url("/groups/$gid/members/" . (int) $m['user_id'] . '/role')) ?>"><?= csrf_field() ?>
                      <input type="hidden" name="role" value="<?= $m['role'] === 'admin' ? 'member' : 'admin' ?>">
                      <button class="dropdown-item"><?= $m['role'] === 'admin' ? 'Remove admin' : 'Make admin' ?></button>
                    </form>
                    <form method="post" action="<?= e(url("/groups/$gid/members/" . (int) $m['user_id'] . '/remove')) ?>"><?= csrf_field() ?>
                      <button class="dropdown-item text-danger">Remove from group</button>
                    </form>
                  </div>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php $former = array_filter($members, fn($m) => $m['status'] !== 'active'); if ($former): ?>
        <p class="small text-muted mt-2 mb-0">Former members: <?= e(implode(', ', array_column($former, 'full_name'))) ?></p>
      <?php endif; ?>
    </div></div>

    <div class="card mb-4"><div class="card-body">
      <h2 class="h6">Suggested settlements <span class="text-muted small">(simplified, not yet paid)</span></h2>
      <?php if (!$suggestions): ?><p class="text-muted small mb-0">Everyone is settled up.</p><?php endif; ?>
      <?php foreach ($suggestions as $s): ?>
        <div class="d-flex justify-content-between align-items-center small mb-1">
          <span><?= e($s['from_name']) ?> → <?= e($s['to_name']) ?> <strong class="amount"><?= e(inr($s['amount'])) ?></strong></span>
          <?php if (in_array($uid, [$s['from'], $s['to']], true)): ?>
            <a class="btn btn-sm btn-outline-success" href="<?= e(url("/groups/$gid/settle?" . http_build_query(['payer' => $s['from'], 'payee' => $s['to'], 'amount' => $s['amount']]))) ?>">Record</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div></div>

    <div class="card mb-4"><div class="card-body">
      <h2 class="h6">Who owes whom</h2>
      <?php if (!$pairs): ?><p class="text-muted small mb-0">No outstanding debts.</p><?php endif; ?>
      <table class="table table-sm small mb-0">
        <?php if ($pairs): ?><thead><tr><th>Debt</th><th class="text-end">Original</th><th class="text-end">Settled</th><th class="text-end">Remaining</th></tr></thead><?php endif; ?>
        <?php foreach ($pairs as $p): ?>
          <tr><td><?= e($p['debtor_name']) ?> → <?= e($p['creditor_name']) ?></td>
            <td class="text-end amount"><?= e(inr($p['original_owed'])) ?></td>
            <td class="text-end amount"><?= e(inr($p['settled'])) ?></td>
            <td class="text-end amount fw-semibold"><?= e(inr($p['remaining'])) ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div></div>

    <?php if (!$archived): ?>
    <div class="card mb-4"><div class="card-body">
      <h2 class="h6">Invite a member</h2>
      <form method="post" action="<?= e(url("/groups/$gid/invite")) ?>" class="d-flex gap-2"><?= csrf_field() ?>
        <input class="form-control<?= is_invalid($errors, 'invite') ?>" name="invite" placeholder="Username or email" aria-label="Username or email" value="<?= e(old($old, 'invite')) ?>">
        <button class="btn btn-outline-primary">Invite</button>
      </form>
      <?= field_error($errors, 'invite') ?>
      <?php foreach ($pending as $p): ?>
        <div class="d-flex justify-content-between align-items-center small mt-2">
          <span><?= e($p['full_name']) ?> (@<?= e($p['username']) ?>) — pending</span>
          <?php if ($isAdmin || (int) $p['invited_by'] === $uid): ?>
            <form method="post" action="<?= e(url('/invitations/' . (int) $p['invitation_id'] . '/cancel')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger">Cancel</button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url("/groups/$gid/leave")) ?>"><?= csrf_field() ?>
      <button class="btn btn-sm btn-outline-danger">Leave group</button>
    </form>
  </div>

  <div class="col-lg-7">
    <div class="card mb-4"><div class="card-body">
      <div class="d-flex justify-content-between"><h2 class="h6">Recent expenses</h2>
        <a class="small" href="<?= e(url('/expenses?group=' . $gid)) ?>">All expenses →</a></div>
      <?php require __DIR__ . '/../partials/expense_rows.php'; ?>
    </div></div>

    <div class="card mb-4"><div class="card-body">
      <h2 class="h6">Settlements</h2>
      <?php if (!$settlements): ?><p class="text-muted small mb-0">No payments recorded yet.</p><?php endif; ?>
      <?php foreach ($settlements as $s): ?>
        <div class="small mb-1"><?= e($s['settlement_date']) ?> · <?= e($s['payer_name']) ?> paid <?= e($s['payee_name']) ?>
          <span class="amount fw-semibold"><?= e(inr($s['amount'])) ?></span>
          <span class="badge text-bg-<?= ['confirmed' => 'success', 'pending' => 'warning', 'rejected' => 'danger', 'cancelled' => 'secondary'][$s['status']] ?>"><?= e($s['status']) ?></span></div>
      <?php endforeach; ?>
    </div></div>

    <?php if ($budgets): ?>
    <div class="card"><div class="card-body">
      <h2 class="h6">Group budgets</h2>
      <?php foreach ($budgets as $b): require __DIR__ . '/../budgets/_bar.php'; endforeach; ?>
    </div></div>
    <?php endif; ?>
  </div>
</div>
