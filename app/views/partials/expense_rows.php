<?php
/**
 * Expense list as rows (reads the same on phones).
 * @var array $expenses  rows from ExpenseModel::search
 * @var bool  $showGroup (optional) show the group name; off on a group's own page
 */
$showGroup = $showGroup ?? true;
?>
<?php if (!$expenses): ?>
  <div class="empty"><i class="bi bi-receipt" aria-hidden="true"></i><p>No expenses yet.</p></div>
<?php else: ?>
<ul class="rows">
  <?php foreach ($expenses as $x): [$day, $mon] = date_parts($x['expense_date']); $deleted = (int) $x['is_deleted'] === 1; ?>
    <li><div class="row-item<?= $deleted ? ' is-deleted' : '' ?>">
      <div class="row-date" aria-hidden="true"><b><?= e($day) ?></b><span><?= e($mon) ?></span></div>
      <div class="row-main">
        <a class="row-title" href="<?= e(url('/expenses/' . (int) $x['expense_id'])) ?>"><span><?= e($x['description']) ?></span></a>
        <?php if ($deleted): ?> <span class="pill pill-neutral">deleted</span><?php endif; ?>
        <div class="row-sub">
          <span class="visually-hidden"><?= e(nice_date($x['expense_date'])) ?>, </span>
          <span class="cat-dot" style="background: <?= e($x['category_color'] ?? '#6c757d') ?>" aria-hidden="true"></span>
          <?= e($x['category_name']) ?>, paid by <?= e($x['payer_name']) ?><?php if ($showGroup): ?>
            <?= $x['group_id'] === null ? '(personal)' : 'in ' . e($x['group_name']) ?><?php endif; ?>
        </div>
      </div>
      <div class="row-amounts">
        <span class="total"><?= e(inr($x['total_amount'])) ?></span>
        <span class="share"><?= $x['my_share'] !== null ? 'your share ' . e(inr($x['my_share'])) : 'not in it' ?></span>
      </div>
    </div></li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
