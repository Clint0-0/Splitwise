<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">Budgets</h1>
  <a class="btn btn-primary" href="<?= e(url('/budgets/create')) ?>"><i class="bi bi-plus"></i> New budget</a>
</div>
<?php if (!$budgets): ?>
  <div class="text-center text-muted py-5"><i class="bi bi-piggy-bank display-4"></i><p class="mt-2">No budgets yet. Set a monthly limit to track your spending.</p></div>
<?php endif; ?>
<?php foreach (['Personal' => fn($b) => $b['user_id'] !== null, 'Group' => fn($b) => $b['group_id'] !== null] as $label => $filter):
  $list = array_filter($budgets, $filter); if (!$list) { continue; } ?>
  <div class="card mb-4"><div class="card-body">
    <h2 class="h6"><?= $label ?> budgets</h2>
    <?php foreach ($list as $b): ?>
      <div class="d-flex gap-2 align-items-start">
        <div class="flex-grow-1">
          <?php require __DIR__ . '/_bar.php'; ?>
          <?php if ($b['group_name']): ?><div class="small text-muted mt-n2 mb-2">Group: <?= e($b['group_name']) ?> (counts total group spending)</div><?php endif; ?>
        </div>
        <?php if ((int) ($b['user_id'] ?? 0) === $uid || $b['group_id'] !== null): ?>
          <a class="btn btn-sm btn-light" href="<?= e(url('/budgets/' . (int) $b['budget_id'] . '/edit')) ?>" aria-label="Edit budget"><i class="bi bi-pencil"></i></a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div></div>
<?php endforeach; ?>
<p class="small text-muted">Personal budgets count <em>your share</em> of each expense (including sub-categories). Group budgets count the group's total spending.</p>
