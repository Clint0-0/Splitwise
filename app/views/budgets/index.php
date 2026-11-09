<div class="page-head">
  <h1 class="page-title">Budgets</h1>
  <div class="page-actions"><a class="btn btn-primary" href="<?= e(url('/budgets/create')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> New budget</a></div>
</div>
<?php if (!$budgets): ?>
  <div class="empty panel"><i class="bi bi-piggy-bank" aria-hidden="true"></i><p>No budgets yet. Set a monthly limit and SplitWise+ warns you before you go over.</p>
    <a class="btn btn-primary" href="<?= e(url('/budgets/create')) ?>">Create a budget</a></div>
<?php endif; ?>
<?php foreach (['Personal' => fn($b) => $b['user_id'] !== null, 'Group' => fn($b) => $b['group_id'] !== null] as $label => $filter):
  $list = array_filter($budgets, $filter); if (!$list) { continue; } ?>
  <section class="panel mb-4">
    <h2><?= $label === 'Personal' ? 'Your budgets' : 'Group budgets' ?></h2>
    <?php foreach ($list as $b): ?>
      <div class="d-flex gap-2 align-items-start">
        <div class="flex-grow-1">
          <?php require __DIR__ . '/_bar.php'; ?>
          <?php if ($b['group_name']): ?><div class="note mt-n2 mb-2"><?= e($b['group_name']) ?>, counts the whole group's spending</div><?php endif; ?>
        </div>
        <?php if ((int) ($b['user_id'] ?? 0) === $uid || $b['group_id'] !== null): ?>
          <a class="btn btn-sm btn-link mt-2" href="<?= e(url('/budgets/' . (int) $b['budget_id'] . '/edit')) ?>" aria-label="Edit <?= e($b['name']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>
<p class="note">Your budgets count your share of each expense, including sub-categories (a Food budget also counts Groceries). Group budgets count everything the group spends.</p>
