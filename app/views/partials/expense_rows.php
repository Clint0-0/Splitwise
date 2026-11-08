<?php /** @var array $expenses */ ?>
<?php if (!$expenses): ?>
  <p class="text-muted mb-0">No expenses yet.</p>
<?php else: ?>
<div class="table-responsive">
<table class="table table-sm table-hover mb-0">
  <thead><tr><th>Date</th><th>Description</th><th>Group</th><th>Paid by</th><th class="text-end">Total</th><th class="text-end">My share</th></tr></thead>
  <tbody>
  <?php foreach ($expenses as $x): ?>
    <tr<?= (int) $x['is_deleted'] === 1 ? ' class="text-decoration-line-through text-muted"' : '' ?>>
      <td class="text-nowrap"><?= e($x['expense_date']) ?></td>
      <td><i class="bi <?= e($x['category_icon']) ?>"></i> <a href="<?= e(url('/expenses/' . (int) $x['expense_id'])) ?>"><?= e($x['description']) ?></a>
        <div class="small text-muted"><?= e($x['category_name']) ?></div></td>
      <td><?= e($x['group_name']) ?></td>
      <td><?= e($x['payer_name']) ?></td>
      <td class="text-end amount"><?= e(inr($x['total_amount'])) ?></td>
      <td class="text-end amount"><?= $x['my_share'] !== null ? e(inr($x['my_share'])) : '<span class="text-muted">—</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
