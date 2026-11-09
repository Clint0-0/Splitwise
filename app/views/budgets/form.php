<?php $editing = $b !== null; ?>
<div class="page-head"><h1 class="page-title"><?= $editing ? 'Edit budget' : 'New budget' ?></h1></div>
<div class="panel form-narrow">
  <form method="post" action="<?= e(url($editing ? '/budgets/' . (int) $b['budget_id'] : '/budgets')) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if (!$editing): ?>
      <div class="mb-3">
        <label class="form-label" for="scope">Whose spending?</label>
        <select class="form-select" id="scope" name="scope">
          <option value="personal">Me (personal)</option>
          <?php foreach ($adminGroups as $g): ?>
            <option value="group" data-group="<?= (int) $g['group_id'] ?>" <?= old($old, 'group_id') === (string) $g['group_id'] ? 'selected' : '' ?>>Group: <?= e($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="group_id" id="group_id" value="<?= e(old($old, 'group_id')) ?>">
        <div class="form-text">Group budgets can be created by group admins.</div>
      </div>
    <?php endif; ?>
    <div class="mb-3">
      <label class="form-label" for="name">Name</label>
      <input class="form-control<?= is_invalid($errors, 'name') ?>" id="name" name="name" maxlength="80" value="<?= e(old($old, 'name', $b['name'] ?? '')) ?>" placeholder="Food in November">
      <?= field_error($errors, 'name') ?>
    </div>
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label" for="limit_amount">Limit (₹)</label>
        <input class="form-control<?= is_invalid($errors, 'limit_amount') ?>" id="limit_amount" name="limit_amount" inputmode="decimal" value="<?= e(old($old, 'limit_amount', $b['limit_amount'] ?? '')) ?>">
        <?= field_error($errors, 'limit_amount') ?>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="category_id">Category</label>
        <select class="form-select<?= is_invalid($errors, 'category_id') ?>" id="category_id" name="category_id">
          <option value="">All categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['category_id'] ?>" <?= old($old, 'category_id', (string) ($b['category_id'] ?? '')) === (string) $c['category_id'] ? 'selected' : '' ?>><?= $c['parent_category_id'] ? '— ' : '' ?><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'category_id') ?>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="period_type">Period</label>
        <select class="form-select" id="period_type" name="period_type">
          <?php foreach ($periods as $p): ?>
            <option value="<?= $p ?>" <?= old($old, 'period_type', $b['period_type'] ?? 'monthly') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="start_date">Start</label>
        <input class="form-control<?= is_invalid($errors, 'start_date') ?>" type="date" id="start_date" name="start_date" value="<?= e(old($old, 'start_date', $b['start_date'] ?? date('Y-m-01'))) ?>">
        <?= field_error($errors, 'start_date') ?>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="end_date">End <span class="text-muted fw-normal">(custom only)</span></label>
        <input class="form-control<?= is_invalid($errors, 'end_date') ?>" type="date" id="end_date" name="end_date" value="<?= e(old($old, 'end_date', $b['end_date'] ?? '')) ?>">
        <?= field_error($errors, 'end_date') ?>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="alert_threshold">Warn me when this much is used (%)</label>
      <input class="form-control<?= is_invalid($errors, 'alert_threshold') ?>" id="alert_threshold" name="alert_threshold" inputmode="numeric" value="<?= e(old($old, 'alert_threshold', (string) ($b['alert_threshold'] ?? 80))) ?>">
      <?= field_error($errors, 'alert_threshold') ?>
    </div>
    <button class="btn btn-primary" type="submit"><?= $editing ? 'Save' : 'Create budget' ?></button>
    <a class="btn btn-link" href="<?= e(url('/budgets')) ?>">Cancel</a>
  </form>
  <?php if ($editing): ?>
    <form method="post" action="<?= e(url('/budgets/' . (int) $b['budget_id'] . '/delete')) ?>" class="mt-3"><?= csrf_field() ?>
      <button class="btn btn-sm btn-danger-quiet">Delete budget</button></form>
  <?php endif; ?>
</div>
<script>
  // scope select -> hidden group_id
  (function () {
    var s = document.getElementById('scope'), g = document.getElementById('group_id');
    if (!s) return;
    function sync() { var o = s.options[s.selectedIndex]; g.value = o.dataset.group || ''; }
    s.addEventListener('change', sync); sync();
  })();
</script>
