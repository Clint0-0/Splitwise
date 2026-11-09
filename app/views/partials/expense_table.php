<?php
/**
 * Expense history as a real table (desktop) that collapses into labelled rows on phones.
 * @var array $expenses rows from ExpenseModel::search
 */
?>
<?php if (!$expenses): ?>
  <div class="empty"><i class="bi bi-receipt" aria-hidden="true"></i><p>No expenses yet.</p></div>
<?php else: ?>
<div class="table-responsive" tabindex="0" role="region" aria-label="Expenses">
<table class="table data-table">
  <thead><tr>
    <th scope="col">Date</th><th scope="col">Description</th><th scope="col">Paid by</th><th scope="col">Group</th>
    <th scope="col" class="num">Total</th><th scope="col" class="num">Your share</th>
  </tr></thead>
  <tbody>
  <?php foreach ($expenses as $x): $deleted = (int) $x['is_deleted'] === 1; ?>
    <tr<?= $deleted ? ' class="is-deleted"' : '' ?>>
      <td class="text-nowrap" data-label="Date"><?= e(nice_date($x['expense_date'])) ?></td>
      <td class="desc"><a href="<?= e(url('/expenses/' . (int) $x['expense_id'])) ?>"><?= e($x['description']) ?></a>
        <?php if ($deleted): ?> <span class="pill pill-neutral">deleted</span><?php endif; ?>
        <span class="sub"><span class="cat-dot" style="background: <?= e($x['category_color'] ?? '#6c757d') ?>" aria-hidden="true"></span> <?= e($x['category_name']) ?></span></td>
      <td data-label="Paid by"><?= e($x['payer_name']) ?></td>
      <td data-label="Group"><?= $x['group_id'] === null ? '<span class="muted-cell">Personal</span>' : e($x['group_name']) ?></td>
      <td class="num fw-semibold" data-label="Total"><?= e(inr($x['total_amount'])) ?></td>
      <td class="num" data-label="Your share"><?= $x['my_share'] !== null ? e(inr($x['my_share'])) : '<span class="muted-cell">not in it</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
