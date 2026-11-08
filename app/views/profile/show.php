<h1 class="h3 mb-4">Profile</h1>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card"><div class="card-body">
      <h2 class="h5">Details</h2>
      <p class="text-muted small mb-3">Username: <strong><?= e($user['username']) ?></strong> · Member since <?= e(substr($user['created_at'], 0, 10)) ?></p>
      <form method="post" action="<?= e(url('/profile')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label" for="full_name">Full name</label>
          <input class="form-control<?= is_invalid($errors, 'full_name') ?>" id="full_name" name="full_name" value="<?= e(old($old, 'full_name', $user['full_name'])) ?>">
          <?= field_error($errors, 'full_name') ?>
        </div>
        <div class="mb-3">
          <label class="form-label" for="email">Email</label>
          <input class="form-control<?= is_invalid($errors, 'email') ?>" id="email" name="email" type="email" value="<?= e(old($old, 'email', $user['email'])) ?>">
          <?= field_error($errors, 'email') ?>
        </div>
        <div class="mb-3">
          <label class="form-label" for="phone">Phone</label>
          <input class="form-control<?= is_invalid($errors, 'phone') ?>" id="phone" name="phone" value="<?= e(old($old, 'phone', $user['phone'] ?? '')) ?>">
          <?= field_error($errors, 'phone') ?>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="notify_email" name="notify_email" value="1" <?= (int) $user['notify_email'] === 1 ? 'checked' : '' ?>>
          <label class="form-check-label" for="notify_email">Email me about new expenses (mock, not sent)</label>
        </div>
        <button class="btn btn-primary" type="submit">Save</button>
      </form>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card"><div class="card-body">
      <h2 class="h5">Change password</h2>
      <form method="post" action="<?= e(url('/profile/password')) ?>" novalidate>
        <?= csrf_field() ?>
        <?php foreach (['current_password' => 'Current password', 'password' => 'New password', 'password_confirm' => 'Confirm new password'] as $f => $label): ?>
          <div class="mb-3">
            <label class="form-label" for="<?= $f ?>"><?= e($label) ?></label>
            <input class="form-control<?= is_invalid($errors, $f) ?>" type="password" id="<?= $f ?>" name="<?= $f ?>"
                   autocomplete="<?= $f === 'current_password' ? 'current-password' : 'new-password' ?>">
            <?= field_error($errors, $f) ?>
          </div>
        <?php endforeach; ?>
        <button class="btn btn-outline-primary" type="submit">Change password</button>
      </form>
    </div></div>
  </div>
</div>
