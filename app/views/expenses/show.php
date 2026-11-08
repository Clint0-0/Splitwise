<?php
$id = (int) $e['expense_id'];
$deleted = (int) $e['is_deleted'] === 1;
$labels = ['equal' => 'Split equally', 'percentage' => 'By percentage', 'exact' => 'Exact amounts', 'shares' => 'By shares', 'adjustment' => 'Equal + adjustments'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h1 class="h3 mb-1"><i class="bi <?= e($e['category_icon']) ?>"></i> <?= e($e['description']) ?>
      <?= $deleted ? '<span class="badge text-bg-danger align-middle">deleted</span>' : '' ?></h1>
    <p class="text-muted mb-0">
      <?= $e['group_id'] ? '<a href="' . e(url('/groups/' . (int) $e['group_id'])) . '">' . e($e['group_name']) . '</a>' : 'Personal' ?>
      · <?= e($e['category_name']) ?> · <?= e($e['expense_date']) ?></p>
  </div>
  <div class="d-flex gap-2">
    <?php if ($canEdit && !$deleted): ?>
      <a class="btn btn-outline-primary" href="<?= e(url("/expenses/$id/edit")) ?>">Edit</a>
      <form method="post" action="<?= e(url("/expenses/$id/delete")) ?>"><?= csrf_field() ?><button class="btn btn-outline-danger">Delete</button></form>
    <?php endif; ?>
    <?php if ($deleted && $lock === null && ($isAdmin || ($e['group_id'] === null && (int) $e['paid_by'] === $uid))): ?>
      <form method="post" action="<?= e(url("/expenses/$id/restore")) ?>"><?= csrf_field() ?><button class="btn btn-outline-success">Restore</button></form>
    <?php endif; ?>
  </div>
</div>
<?= field_error($errors, 'participants') ?>
<?php if ($lock !== null): ?><div class="alert alert-secondary small"><i class="bi bi-lock"></i> <?= e($lock) ?></div><?php endif; ?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card"><div class="card-body">
      <div class="d-flex justify-content-between">
        <div><div class="text-muted small">Total</div><div class="fs-3 amount"><?= e(inr($e['total_amount'])) ?></div></div>
        <div class="text-end"><div class="text-muted small">Paid by</div><div class="fs-5"><?= e($e['payer_name']) ?></div></div>
      </div>
      <p class="small text-muted mt-2"><?= e($labels[$e['split_method']]) ?> · added by <?= e($e['creator_name']) ?></p>
      <table class="table table-sm mb-0">
        <thead><tr><th>Participant</th>
          <?php if ($e['split_method'] === 'percentage'): ?><th class="text-end">%</th><?php endif; ?>
          <?php if ($e['split_method'] === 'shares'): ?><th class="text-end">Shares</th><?php endif; ?>
          <?php if ($e['split_method'] === 'adjustment'): ?><th class="text-end">Adjustment</th><?php endif; ?>
          <th class="text-end">Share</th><th class="text-end">Owes payer</th></tr></thead>
        <tbody>
        <?php foreach ($splits as $s): $isPayer = (int) $s['user_id'] === (int) $e['paid_by']; ?>
          <tr>
            <td><?= e($s['full_name']) ?></td>
            <?php if ($e['split_method'] === 'percentage'): ?><td class="text-end"><?= e($s['share_percentage']) ?></td><?php endif; ?>
            <?php if ($e['split_method'] === 'shares'): ?><td class="text-end"><?= e($s['share_units']) ?></td><?php endif; ?>
            <?php if ($e['split_method'] === 'adjustment'): ?><td class="text-end amount"><?= e(inr($s['share_adjustment'] ?? '0')) ?></td><?php endif; ?>
            <td class="text-end amount"><?= e(inr($s['share_amount'])) ?></td>
            <td class="text-end amount"><?= $isPayer ? '<span class="text-muted">paid</span>' : e(inr($s['share_amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($e['notes']): ?><p class="mt-3 mb-0"><strong>Notes:</strong> <?= nl2br(e($e['notes'])) ?></p><?php endif; ?>
      <?php if ($tags): ?><p class="mt-2 mb-0"><?php foreach ($tags as $t): ?>
        <a class="badge text-bg-light text-decoration-none" href="<?= e(url('/expenses?tag=' . rawurlencode($t))) ?>">#<?= e($t) ?></a>
      <?php endforeach; ?></p><?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-5">
    <?php if ($receipts): ?>
    <div class="card mb-4"><div class="card-body">
      <h2 class="h6">Receipts</h2>
      <?php foreach ($receipts as $r): ?>
        <div><a href="<?= e(url('/receipts/' . (int) $r['receipt_id'])) ?>" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> <?= e($r['original_name']) ?></a>
          <span class="small text-muted">(<?= number_format($r['file_size'] / 1024, 0) ?> KB)</span></div>
      <?php endforeach; ?>
    </div></div>
    <?php endif; ?>
    <div class="card"><div class="card-body">
      <h2 class="h6">History</h2>
      <ul class="list-unstyled small mb-0">
        <?php foreach ($history as $h): $was = json_decode((string) $h["old_data"], true) ?: []; $now = json_decode((string) $h["new_data"], true) ?: []; ?>
          <li class="mb-2">
            <strong><?= e(['INSERT' => 'Created', 'UPDATE' => 'Edited', 'SOFT_DELETE' => 'Deleted', 'RESTORE' => 'Restored', 'DELETE' => 'Removed'][$h['action']] ?? $h['action']) ?></strong>
            by <?= e($h['changed_by_name'] ?? 'system') ?> · <?= e(substr($h['changed_at'], 0, 16)) ?>
            <?php if ($h['action'] === 'UPDATE'): foreach (['description', 'total_amount', 'expense_date', 'split_method'] as $k):
                if (($was[$k] ?? null) != ($now[$k] ?? null)): ?>
              <div class="text-muted"><?= e(str_replace('_', ' ', $k)) ?>: <?= e((string) ($was[$k] ?? '')) ?> → <?= e((string) ($now[$k] ?? '')) ?></div>
            <?php endif; endforeach; endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div></div>
  </div>
</div>
