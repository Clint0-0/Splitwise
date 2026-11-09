<div class="page-head"><h1 class="page-title">Settlements</h1></div>
<ul class="nav nav-pills mb-3" aria-label="Filter by status">
  <?php foreach (['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $k => $label): ?>
    <li class="nav-item"><a class="nav-link<?= ($status ?? '') === $k ? ' active' : '' ?>"<?= ($status ?? '') === $k ? ' aria-current="page"' : '' ?> href="<?= e(url('/settlements' . ($k ? "?status=$k" : ''))) ?>"><?= $label ?></a></li>
  <?php endforeach; ?>
</ul>
<section class="panel" aria-label="Settlement list">
<?php if (!$settlements): ?>
  <div class="empty"><i class="bi bi-arrow-left-right" aria-hidden="true"></i><p>No payments here. Open a group and choose Settle up to record one.</p></div>
<?php else: ?>
  <ul class="rows">
    <?php foreach ($settlements as $s): $sid = (int) $s['settlement_id']; [$day, $mon] = date_parts($s['settlement_date']);
      $mePayer = (int) $s['payer_id'] === $uid; $mePayee = (int) $s['payee_id'] === $uid; ?>
      <li><div class="row-item">
        <div class="row-date" aria-hidden="true"><b><?= e($day) ?></b><span><?= e($mon) ?></span></div>
        <div class="row-main">
          <span class="fw-semibold"><?= $mePayer ? 'You' : e($s['payer_name']) ?> paid <?= $mePayee ? 'you' : e($s['payee_name']) ?></span>
          <div class="row-sub"><?php require __DIR__ . '/_status.php'; ?>
            <a href="<?= e(url('/groups/' . (int) $s['group_id'])) ?>"><?= e($s['group_name']) ?></a>, <?= e(str_replace('_', ' ', $s['method'])) ?><?= $s['note'] ? ', ' . e($s['note']) : '' ?></div>
          <?php if ($s['status'] === 'pending'): ?>
          <div class="d-flex flex-wrap gap-2 mt-2">
            <?php if ($mePayee): ?>
              <form method="post" action="<?= e(url("/settlements/$sid/confirm")) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-confirm">Confirm</button></form>
              <form method="post" action="<?= e(url("/settlements/$sid/reject")) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger-quiet">Reject</button></form>
            <?php elseif ((int) $s['created_by'] === $uid): ?>
              <span class="note align-self-center">Waiting for <?= e($s['payee_name']) ?> to confirm.</span>
              <form method="post" action="<?= e(url("/settlements/$sid/cancel")) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-quiet">Cancel</button></form>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="row-amounts"><span class="total <?= $mePayee ? 'text-gets' : ($mePayer ? 'text-owes' : '') ?>"><?= e(inr($s['amount'])) ?></span></div>
      </div></li>
    <?php endforeach; ?>
  </ul>
  <div class="mt-3"><?php require __DIR__ . '/../partials/pagination.php'; ?></div>
<?php endif; ?>
</section>
