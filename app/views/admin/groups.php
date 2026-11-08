<h1 class="h3 mb-3">Administration</h1>
<?php require __DIR__ . '/_nav.php'; ?>
<div class="card"><div class="card-body table-responsive">
  <table class="table table-sm mb-0">
    <thead><tr><th>Group</th><th>Type</th><th>Created by</th><th>Members</th><th>Expenses</th><th class="text-end">Total</th><th>Last expense</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($groups as $g): ?>
      <tr><td><?= e($g['name']) ?></td><td><?= e($g['group_type']) ?></td><td><?= e($g['creator_name']) ?></td>
        <td><?= (int) $g['member_count'] ?></td><td><?= (int) $g['expense_count'] ?></td>
        <td class="text-end amount"><?= e(inr($g['total_spent'])) ?></td><td><?= e($g['last_expense_date'] ?? '—') ?></td>
        <td><?= (int) $g['is_archived'] === 1 ? 'archived' : 'active' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="mt-3"><?php require __DIR__ . '/../partials/pagination.php'; ?></div>
</div></div>
