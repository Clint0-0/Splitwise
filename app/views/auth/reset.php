<div class="panel p-4">
  <h1 class="h2 mb-2">Choose a new password</h1>
  <?php if (!$valid): ?>
    <p>This reset link has expired or was already used.</p>
    <a class="btn btn-primary" href="<?= e(url('/forgot-password')) ?>">Ask for a new link</a>
  <?php else: ?>
    <form method="post" action="<?= e(url('/reset-password')) ?>" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="mb-3">
        <label for="password" class="form-label">New password</label>
        <input id="password" name="password" type="password" class="form-control<?= is_invalid($errors, 'password') ?>" required autocomplete="new-password" aria-describedby="pw-help">
        <div id="pw-help" class="form-text">At least 8 characters, with a letter and a digit.</div>
        <?= field_error($errors, 'password') ?>
      </div>
      <div class="mb-3">
        <label for="password_confirm" class="form-label">Confirm new password</label>
        <input id="password_confirm" name="password_confirm" type="password" class="form-control<?= is_invalid($errors, 'password_confirm') ?>" required autocomplete="new-password">
        <?= field_error($errors, 'password_confirm') ?>
      </div>
      <button class="btn btn-primary w-100" type="submit">Save new password</button>
      <p class="note mt-2 mb-0">You'll be logged out on every device.</p>
    </form>
  <?php endif; ?>
</div>
