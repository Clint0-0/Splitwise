<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'SplitWise+') ?> · SplitWise+</title>
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="bg-body-tertiary">
<main class="container py-5" style="max-width: 460px">
  <h1 class="h3 text-center mb-4"><i class="bi bi-wallet2"></i> SplitWise+</h1>
  <?php foreach ($flash ?? [] as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>" role="alert"><?= e($f['message']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
</body>
</html>
