<div class="card shadow-sm">
  <div class="card-body p-4">
    <h2 class="h5 mb-3">Log in</h2>
    <?= field_error($errors, 'login') ?>
    <form method="post" action="<?= e(url('/login')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label for="login" class="form-label">Username or email</label>
        <input id="login" name="login" class="form-control" required autocomplete="username" value="<?= e(old($old, 'login')) ?>">
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input id="password" name="password" type="password" class="form-control" required autocomplete="current-password">
      </div>
      <button class="btn btn-primary w-100" type="submit">Log in</button>
    </form>
    <p class="text-center mt-3 mb-0 small">New here? <a href="<?= e(url('/register')) ?>">Create an account</a></p>
  </div>
</div>
