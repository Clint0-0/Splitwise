<h1 class="h3 mb-3">Settlements</h1>
<ul class="nav nav-pills mb-3">
  <?php foreach (['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $k => $label): ?>
    <li class="nav-item"><a class="nav-link<?= ($status ?? '') === $k ? ' active' : '' ?>" href="<?= e(url('/settlements' . ($k ? "?status=$k" : ''))) ?>"><?= $label ?></a></li>
  <?php endforeach; ?>
</ul>
<div class="card"><div class="card-body">
<?php if (!$settlements): ?>
  <p class="text-muted mb-0">No settlements here. Settle up from a group page.</p>
<?php else: ?>
  <div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead><tr><th>Date</th><th>Group</th><th>From → To</th><th class="text-end">Amount</th><th>Method</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($settlements as $s): $sid = (int) $s['settlement_id']; ?>
      <tr>
        <td class="text-nowrap"><?= e($s['settlement_date']) ?></td>
        <td><a href="<?= e(url('/groups/' . (int) $s['group_id'])) ?>"><?= e($s['group_name']) ?></a></td>
        <td><?= e($s['payer_name']) ?> → <?= e($s['payee_name']) ?><?= $s['note'] ? '<div class="small text-muted">' . e($s['note']) . '</div>' : '' ?></td>
        <td class="text-end amount"><?= e(inr($s['amount'])) ?></td>
        <td><?= e(str_replace('_', ' ', $s['method'])) ?></td>
        <td><span class="badge text-bg-<?= ['confirmed' => 'success', 'pending' => 'warning', 'rejected' => 'danger', 'cancelled' => 'secondary'][$s['status']] ?>"><?= e($s['status']) ?></span></td>
        <td class="text-end text-nowrap">
          <?php if ($s['status'] === 'pending' && (int) $s['payee_id'] === $uid): ?>
            <form class="d-inline" method="post" action="<?= e(url("/settlements/$sid/confirm")) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-success">Confirm</button></form>
            <form class="d-inline" method="post" action="<?= e(url("/settlements/$sid/reject")) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Reject</button></form>
          <?php elseif ($s['status'] === 'pending' && (int) $s['created_by'] === $uid): ?>
            <form class="d-inline" method="post" action="<?= e(url("/settlements/$sid/cancel")) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Cancel</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div class="mt-3"><?php require __DIR__ . '/../partials/pagination.php'; ?></div>
<?php endif; ?>
</div></div>
