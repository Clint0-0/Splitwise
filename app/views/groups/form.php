<?php $editing = $group !== null; ?>
<h1 class="h3 mb-4"><?= $editing ? 'Edit group' : 'New group' ?></h1>
<div class="card" style="max-width: 640px"><div class="card-body">
  <form method="post" action="<?= e(url($editing ? '/groups/' . (int) $group['group_id'] : '/groups')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="name">Group name</label>
      <input class="form-control<?= is_invalid($errors, 'name') ?>" id="name" name="name" maxlength="80" required
             value="<?= e(old($old, 'name', $group['name'] ?? '')) ?>" placeholder="e.g. Goa Trip 2026">
      <?= field_error($errors, 'name') ?>
    </div>
    <div class="mb-3">
      <label class="form-label" for="group_type">Type</label>
      <select class="form-select" id="group_type" name="group_type">
        <?php foreach (App\Models\GroupModel::TYPES as $t): ?>
          <option value="<?= e($t) ?>" <?= old($old, 'group_type', $group['group_type'] ?? 'other') === $t ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label" for="description">Description (optional)</label>
      <input class="form-control<?= is_invalid($errors, 'description') ?>" id="description" name="description" maxlength="255"
             value="<?= e(old($old, 'description', $group['description'] ?? '')) ?>">
      <?= field_error($errors, 'description') ?>
    </div>
    <button class="btn btn-primary" type="submit"><?= $editing ? 'Save' : 'Create group' ?></button>
    <a class="btn btn-link" href="<?= e(url($editing ? '/groups/' . (int) $group['group_id'] : '/groups')) ?>">Cancel</a>
  </form>
</div></div>
