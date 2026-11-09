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
  <div class="table-responsive" tabindex="0" role="region" aria-label="Settlements">
  <table class="table data-table">
    <thead><tr><th scope="col">Date</th><th scope="col">Payment</th><th scope="col">Group</th><th scope="col">Status</th><th scope="col" class="num">Amount</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($settlements as $s): $sid = (int) $s['settlement_id'];
      $mePayer = (int) $s['payer_id'] === $uid; $mePayee = (int) $s['payee_id'] === $uid; ?>
      <tr>
        <td class="text-nowrap" data-label="Date"><?= e(nice_date($s['settlement_date'])) ?></td>
        <td class="desc"><strong><?= $mePayer ? 'You' : e($s['payer_name']) ?> paid <?= $mePayee ? 'you' : e($s['payee_name']) ?></strong>
          <span class="sub"><?= e(str_replace('_', ' ', $s['method'])) ?><?= $s['note'] ? ', ' . e($s['note']) : '' ?></span></td>
        <td data-label="Group"><a href="<?= e(url('/groups/' . (int) $s['group_id'])) ?>"><?= e($s['group_name']) ?></a></td>
        <td data-label="Status"><?php require __DIR__ . '/_status.php'; ?></td>
        <?php $void = in_array($s['status'], ['rejected', 'cancelled'], true); ?>
        <td class="num fw-semibold<?= $void ? ' text-muted text-decoration-line-through' : '' ?>" data-label="Amount"><?php if ($void): ?><span class="visually-hidden">not counted: </span><?php endif; ?><?= $void ? '' : ($mePayee ? '+' : ($mePayer ? '−' : '')) ?><?= e(inr($s['amount'])) ?></td>
        <td class="text-end">
          <?php if ($s['status'] === 'pending' && $mePayee): ?>
            <div class="d-inline-flex gap-2">
              <form method="post" action="<?= e(url("/settlements/$sid/confirm")) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-confirm">Confirm</button></form>
              <form method="post" action="<?= e(url("/settlements/$sid/reject")) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger-quiet">Reject</button></form>
            </div>
          <?php elseif ($s['status'] === 'pending' && (int) $s['created_by'] === $uid): ?>
            <form method="post" action="<?= e(url("/settlements/$sid/cancel")) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-quiet" aria-label="Cancel payment to <?= e($s['payee_name']) ?>">Cancel</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <p class="note mt-2 mb-0">+ is money you received, − is money you paid. Pending payments count once the receiver confirms; rejected and cancelled ones (struck through) never count.</p>
  <div class="mt-3"><?php require __DIR__ . '/../partials/pagination.php'; ?></div>
<?php endif; ?>
</section>
