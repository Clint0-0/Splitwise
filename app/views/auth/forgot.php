<div class="panel p-4">
    <h1 class="h2 mb-2">Forgot your password?</h1>
    <p class="note">Enter the e-mail address of your account. If it exists, we'll e-mail you a link to choose a new password.</p>
    <form method="post" action="<?= e(url('/forgot-password')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input id="email" name="email" type="email" class="form-control<?= is_invalid($errors, 'email') ?>" required autocomplete="email" value="<?= e(old($old, 'email')) ?>">
        <?= field_error($errors, 'email') ?>
      </div>
      <button class="btn btn-primary w-100" type="submit">Send reset link</button>
    </form>
    <p class="text-center mt-3 mb-0"><a href="<?= e(url('/login')) ?>">Back to log in</a></p>
</div>
