<?php $editing = $group !== null; ?>
<div class="page-head"><h1 class="page-title"><?= $editing ? 'Edit group' : 'New group' ?></h1></div>
<div class="panel form-narrow">
  <form method="post" action="<?= e(url($editing ? '/groups/' . (int) $group['group_id'] : '/groups')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="name">Group name</label>
      <input class="form-control<?= is_invalid($errors, 'name') ?>" id="name" name="name" maxlength="80" required
             value="<?= e(old($old, 'name', $group['name'] ?? '')) ?>" placeholder="Goa Trip 2026">
      <?= field_error($errors, 'name') ?>
    </div>
    <div class="mb-3">
      <label class="form-label" for="group_type">What is it for?</label>
      <select class="form-select" id="group_type" name="group_type">
        <?php foreach (['trip' => 'A trip', 'home' => 'A shared home', 'family' => 'Family', 'friends' => 'Friends', 'work' => 'Work', 'other' => 'Something else'] as $t => $label): ?>
          <option value="<?= e($t) ?>" <?= old($old, 'group_type', $group['group_type'] ?? 'other') === $t ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-4">
      <label class="form-label" for="description">Description <span class="text-muted fw-normal">(optional)</span></label>
      <input class="form-control<?= is_invalid($errors, 'description') ?>" id="description" name="description" maxlength="255"
             value="<?= e(old($old, 'description', $group['description'] ?? '')) ?>" placeholder="Four friends, four days in North Goa">
      <?= field_error($errors, 'description') ?>
    </div>
    <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Create group' ?></button>
    <a class="btn btn-link" href="<?= e(url($editing ? '/groups/' . (int) $group['group_id'] : '/groups')) ?>">Cancel</a>
  </form>
  <?php if (!$editing): ?><p class="note mt-3 mb-0">You will be the group's admin. Invite members from the group page.</p><?php endif; ?>
</div>
