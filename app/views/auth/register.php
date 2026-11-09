<div class="panel p-4">
    <h1 class="h2 mb-3">Create your account</h1>
    <form method="post" action="<?= e(url('/register')) ?>" novalidate>
      <?= csrf_field() ?>
      <?php foreach ([
          ['full_name', 'Full name', 'text', 'name'],
          ['username', 'Username', 'text', 'username'],
          ['email', 'Email', 'email', 'email'],
          ['phone', 'Phone (optional)', 'tel', 'tel'],
      ] as [$f, $label, $type, $ac]): ?>
        <div class="mb-3">
          <label for="<?= $f ?>" class="form-label"><?= e($label) ?></label>
          <input id="<?= $f ?>" name="<?= $f ?>" type="<?= $type ?>" autocomplete="<?= $ac ?>"
                 class="form-control<?= is_invalid($errors, $f) ?>" value="<?= e(old($old, $f)) ?>">
          <?= field_error($errors, $f) ?>
        </div>
      <?php endforeach; ?>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password"
               class="form-control<?= is_invalid($errors, 'password') ?>" aria-describedby="pwHelp">
        <div id="pwHelp" class="form-text">At least 8 characters, with a letter and a digit.</div>
        <?= field_error($errors, 'password') ?>
      </div>
      <div class="mb-3">
        <label for="password_confirm" class="form-label">Confirm password</label>
        <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password"
               class="form-control<?= is_invalid($errors, 'password_confirm') ?>">
        <?= field_error($errors, 'password_confirm') ?>
      </div>
      <button class="btn btn-primary w-100" type="submit">Create account</button>
    </form>
    <p class="text-center mt-3 mb-0">Already have an account? <a href="<?= e(url('/login')) ?>">Log in</a></p>
</div>
