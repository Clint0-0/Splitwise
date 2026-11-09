<div class="page-head"><h1 class="page-title">Administration</h1></div>
<?php require __DIR__ . '/_nav.php'; ?>
<section class="panel table-responsive" tabindex="0" role="region" aria-label="All groups">
  <table class="table">
    <thead><tr><th>Group</th><th>Type</th><th>Created by</th><th>Members</th><th>Expenses</th><th class="num">Total</th><th>Last expense</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($groups as $g): ?>
      <tr><td><?= e($g['name']) ?></td><td><?= e($g['group_type']) ?></td><td><?= e($g['creator_name']) ?></td>
        <td><?= (int) $g['member_count'] ?></td><td><?= (int) $g['expense_count'] ?></td>
        <td class="num"><?= e(inr($g['total_spent'])) ?></td><td class="text-nowrap"><?= $g['last_expense_date'] ? e(nice_date($g['last_expense_date'])) : 'none yet' ?></td>
        <td><?= (int) $g['is_archived'] === 1 ? 'archived' : 'active' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="mt-3"><?php require __DIR__ . '/../partials/pagination.php'; ?></div>
</section>
