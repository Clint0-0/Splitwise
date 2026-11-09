<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#F5F3FA">
  <title><?= e($title ?? 'SplitWise+') ?> – SplitWise+</title>
  <link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<main class="auth-wrap" id="main">
  <div class="auth-card">
    <a class="brand" href="<?= e(url('/')) ?>"><span class="brand-mark" aria-hidden="true">₹</span> SplitWise+</a>
    <p class="auth-tag">Split bills with friends. Know who owes whom.</p>
    <?php foreach ($flash ?? [] as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>" role="<?= $f['type'] === 'danger' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </div>
</main>
</body>
</html>
