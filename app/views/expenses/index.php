<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">Expenses</h1>
  <a class="btn btn-primary" href="<?= e(url('/expenses/create')) ?>"><i class="bi bi-plus"></i> Add expense</a>
</div>

<form method="get" class="card card-body mb-3" role="search">
  <div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label small" for="q">Search</label>
      <input class="form-control form-control-sm" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Description or notes"></div>
    <div class="col-md-2"><label class="form-label small" for="group">Group</label>
      <select class="form-select form-select-sm" id="group" name="group">
        <option value="">All</option>
        <option value="personal" <?= $filters['group'] === 'personal' ? 'selected' : '' ?>>Personal</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?= (int) $g['group_id'] ?>" <?= $filters['group'] === (string) $g['group_id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="form-label small" for="category">Category</label>
      <select class="form-select form-select-sm" id="category" name="category">
        <option value="">All</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['category_id'] ?>" <?= $filters['category'] === (string) $c['category_id'] ? 'selected' : '' ?>><?= $c['parent_category_id'] ? '— ' : '' ?><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="form-label small" for="from">From</label>
      <input class="form-control form-control-sm" type="date" id="from" name="from" value="<?= e($filters['from']) ?>"></div>
    <div class="col-md-2"><label class="form-label small" for="to">To</label>
      <input class="form-control form-control-sm" type="date" id="to" name="to" value="<?= e($filters['to']) ?>"></div>
    <div class="col-md-1"><button class="btn btn-sm btn-outline-primary w-100">Filter</button></div>
  </div>
  <div class="form-check mt-2">
    <input class="form-check-input" type="checkbox" id="deleted" name="deleted" value="1" <?= $filters['deleted'] ? 'checked' : '' ?>>
    <label class="form-check-label small" for="deleted">Show deleted expenses instead</label>
    <?php if ($filters['tag'] !== ''): ?><input type="hidden" name="tag" value="<?= e($filters['tag']) ?>"><span class="badge text-bg-light ms-2">#<?= e($filters['tag']) ?></span><?php endif; ?>
  </div>
</form>

<div class="card"><div class="card-body">
  <p class="small text-muted"><?= (int) $total ?> expense<?= $total === 1 ? '' : 's' ?></p>
  <?php require __DIR__ . '/../partials/expense_rows.php'; ?>
  <div class="mt-3"><?php require __DIR__ . '/../partials/pagination.php'; ?></div>
</div></div>
