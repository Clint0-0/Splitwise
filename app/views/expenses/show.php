<?php
$id = (int) $e['expense_id'];
$deleted = (int) $e['is_deleted'] === 1;
$labels = ['equal' => 'Split equally', 'percentage' => 'Split by percentage', 'exact' => 'Split by exact amounts', 'shares' => 'Split by shares', 'adjustment' => 'Split equally with adjustments'];
$verbs = ['INSERT' => 'Added', 'UPDATE' => 'Edited', 'SOFT_DELETE' => 'Deleted', 'RESTORE' => 'Restored', 'DELETE' => 'Removed'];
?>
<div class="page-head">
  <div>
    <h1 class="page-title"><?= e($e['description']) ?></h1>
    <p class="page-sub">
      <?= e($e['category_name']) ?> on <?= e(nice_date($e['expense_date'])) ?>,
      <?= $e['group_id'] ? 'in <a href="' . e(url('/groups/' . (int) $e['group_id'])) . '">' . e($e['group_name']) . '</a>' : 'personal' ?>
      <?php if ($deleted): ?> <span class="pill pill-rejected"><i class="bi bi-trash" aria-hidden="true"></i> deleted</span><?php endif; ?>
    </p>
  </div>
  <div class="page-actions">
    <?php if ($canEdit && !$deleted): ?>
      <a class="btn btn-quiet" href="<?= e(url("/expenses/$id/edit")) ?>"><i class="bi bi-pencil" aria-hidden="true"></i> Edit</a>
      <form method="post" action="<?= e(url("/expenses/$id/delete")) ?>"><?= csrf_field() ?><button class="btn btn-danger-quiet"><i class="bi bi-trash" aria-hidden="true"></i> Delete</button></form>
    <?php endif; ?>
    <?php if ($deleted && $lock === null && ($isAdmin || ($e['group_id'] === null && (int) $e['paid_by'] === $uid))): ?>
      <form method="post" action="<?= e(url("/expenses/$id/restore")) ?>"><?= csrf_field() ?><button class="btn btn-confirm">Restore</button></form>
    <?php endif; ?>
  </div>
</div>
<?= field_error($errors, 'participants') ?>
<?php if ($lock !== null): ?><div class="alert alert-secondary" role="status"><i class="bi bi-lock" aria-hidden="true"></i> <?= e($lock) ?></div><?php endif; ?>

<div class="grid-2">
  <div class="stack">
    <section class="panel" aria-label="Split">
      <p class="hero-line mb-0"><?= e($e['payer_name']) ?> paid</p>
      <span class="hero-figure"><?= e(inr($e['total_amount'])) ?></span>
      <p class="note"><?= e($labels[$e['split_method']]) ?>. Added by <?= e($e['creator_name']) ?>.</p>
      <div class="table-responsive" tabindex="0" role="region" aria-label="Scrollable table">
      <table class="table">
        <thead><tr><th scope="col">Who</th>
          <?php if ($e['split_method'] === 'percentage'): ?><th scope="col" class="num">Percent</th><?php endif; ?>
          <?php if ($e['split_method'] === 'shares'): ?><th scope="col" class="num">Shares</th><?php endif; ?>
          <?php if ($e['split_method'] === 'adjustment'): ?><th scope="col" class="num">Adjustment</th><?php endif; ?>
          <th scope="col" class="num">Their part</th></tr></thead>
        <tbody>
        <?php foreach ($splits as $s): $isPayer = (int) $s['user_id'] === (int) $e['paid_by']; ?>
          <tr>
            <td><?= e($s['full_name']) ?><?php if ($isPayer): ?> <span class="note">(paid the bill)</span><?php endif; ?></td>
            <?php if ($e['split_method'] === 'percentage'): ?><td class="num"><?= e(rtrim(rtrim((string) $s['share_percentage'], '0'), '.')) ?>%</td><?php endif; ?>
            <?php if ($e['split_method'] === 'shares'): ?><td class="num"><?= e(rtrim(rtrim((string) $s['share_units'], '0'), '.')) ?></td><?php endif; ?>
            <?php if ($e['split_method'] === 'adjustment'): ?><td class="num"><?= e(inr($s['share_adjustment'] ?? '0')) ?></td><?php endif; ?>
            <td class="num fw-semibold"><?= e(inr($s['share_amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php if ($e['notes']): ?><p class="mt-3 mb-0"><strong>Notes:</strong> <?= nl2br(e($e['notes'])) ?></p><?php endif; ?>
      <?php if ($tags): ?><p class="mt-2 mb-0"><?php foreach ($tags as $t): ?>
        <a class="pill pill-admin text-decoration-none" href="<?= e(url('/expenses?tag=' . rawurlencode($t))) ?>">#<?= e($t) ?></a>
      <?php endforeach; ?></p><?php endif; ?>
    </section>
  </div>
  <div class="stack">
    <?php if ($receipts): ?>
    <section class="panel" aria-labelledby="rc-h">
      <h2 id="rc-h">Receipt</h2>
      <?php foreach ($receipts as $r): ?>
        <div><a href="<?= e(url('/receipts/' . (int) $r['receipt_id'])) ?>" target="_blank" rel="noopener"><i class="bi bi-paperclip" aria-hidden="true"></i> <?= e($r['original_name']) ?></a>
          <span class="note">(<?= e(App\Services\UploadService::human((int) $r['file_size'])) ?>)</span></div>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>
    <section class="panel" aria-labelledby="hi-h">
      <h2 id="hi-h">History</h2>
      <ol class="history">
        <?php foreach ($history as $h): $was = json_decode((string) $h['old_data'], true) ?: []; $now = json_decode((string) $h['new_data'], true) ?: []; ?>
          <li>
            <strong><?= e($verbs[$h['action']] ?? $h['action']) ?></strong> by <?= e($h['changed_by_name'] ?? 'system') ?>
            <div class="note"><?= e(date('j M Y, H:i', strtotime($h['changed_at']))) ?></div>
            <?php if ($h['action'] === 'UPDATE'): foreach (['description' => 'Description', 'total_amount' => 'Amount', 'expense_date' => 'Date', 'split_method' => 'Split'] as $k => $label):
                if (($was[$k] ?? null) != ($now[$k] ?? null)): ?>
              <div class="small"><?= e($label) ?> changed from <?= e((string) ($was[$k] ?? '')) ?> to <?= e((string) ($now[$k] ?? '')) ?></div>
            <?php endif; endforeach; endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>
</div>
