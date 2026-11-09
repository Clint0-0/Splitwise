<div class="page-head">
  <h1 class="page-title">Expenses</h1>
  <div class="page-actions"><a class="btn btn-primary" href="<?= e(url('/expenses/create')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add expense</a></div>
</div>

<form method="get" class="panel mb-4" role="search" aria-label="Filter expenses">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-4"><label class="form-label small" for="q">Search</label>
      <input class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Description or notes"></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="group">Group</label>
      <select class="form-select" id="group" name="group">
        <option value="">All</option>
        <option value="personal" <?= $filters['group'] === 'personal' ? 'selected' : '' ?>>Personal</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?= (int) $g['group_id'] ?>" <?= $filters['group'] === (string) $g['group_id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="category">Category</label>
      <select class="form-select" id="category" name="category">
        <option value="">All</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['category_id'] ?>" <?= $filters['category'] === (string) $c['category_id'] ? 'selected' : '' ?>><?= $c['parent_category_id'] ? '– ' : '' ?><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="from">From</label>
      <input class="form-control" type="date" id="from" name="from" value="<?= e($filters['from']) ?>"></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="to">To</label>
      <input class="form-control" type="date" id="to" name="to" value="<?= e($filters['to']) ?>"></div>
  </div>
  <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
    <button class="btn btn-quiet">Apply filters</button>
    <div class="form-check mb-0">
      <input class="form-check-input" type="checkbox" id="deleted" name="deleted" value="1" <?= $filters['deleted'] ? 'checked' : '' ?>>
      <label class="form-check-label" for="deleted">Show deleted expenses instead</label>
    </div>
    <?php if ($filters['tag'] !== ''): ?><input type="hidden" name="tag" value="<?= e($filters['tag']) ?>"><span class="pill pill-admin">#<?= e($filters['tag']) ?></span><?php endif; ?>
    <?php if (array_filter($filters)): ?><a class="small" href="<?= e(url('/expenses')) ?>">Clear filters</a><?php endif; ?>
  </div>
</form>

<section class="panel" aria-label="Expense list">
  <p class="note mb-1"><?= (int) $total ?> expense<?= $total === 1 ? '' : 's' ?></p>
  <?php require __DIR__ . '/../partials/expense_table.php'; ?>
  <div class="mt-3"><?php require __DIR__ . '/../partials/pagination.php'; ?></div>
</section>
