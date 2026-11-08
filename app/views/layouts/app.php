<?php
/** Main layout (logged-in). Phase 4 restyles; structure and data are final. */
use App\Core\Auth;

$me = Auth::user();
$unread = $me ? (new App\Models\NotificationModel())->unreadCount((int) $me['user_id']) : 0;
$nav = [
    ['/dashboard', 'Dashboard', 'bi-speedometer2'],
    ['/groups', 'Groups', 'bi-people'],
    ['/expenses', 'Expenses', 'bi-receipt'],
    ['/balances', 'Balances', 'bi-scale'],
    ['/settlements', 'Settlements', 'bi-arrow-left-right'],
    ['/budgets', 'Budgets', 'bi-piggy-bank'],
    ['/analytics', 'Analytics', 'bi-bar-chart'],
];
$current = (new App\Core\Request())->path;
?>
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
<nav class="navbar navbar-expand-lg bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="<?= e(url('/dashboard')) ?>"><i class="bi bi-wallet2"></i> SplitWise+</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
            aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto">
        <?php foreach ($nav as [$href, $label, $icon]): ?>
          <li class="nav-item">
            <a class="nav-link<?= str_starts_with($current, $href) ? ' active fw-semibold' : '' ?>" href="<?= e(url($href)) ?>">
              <i class="bi <?= e($icon) ?>"></i> <?= e($label) ?></a>
          </li>
        <?php endforeach; ?>
        <?php if (Auth::isAdmin()): ?>
          <li class="nav-item"><a class="nav-link" href="<?= e(url('/admin')) ?>"><i class="bi bi-shield-lock"></i> Admin</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav align-items-lg-center">
        <li class="nav-item">
          <a class="nav-link position-relative" href="<?= e(url('/notifications')) ?>" aria-label="Notifications">
            <i class="bi bi-bell"></i>
            <?php if ($unread > 0): ?><span class="badge text-bg-danger rounded-pill"><?= (int) $unread ?></span><?php endif; ?>
          </a>
        </li>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('/profile')) ?>"><i class="bi bi-person-circle"></i> <?= e($me['full_name'] ?? '') ?></a></li>
        <li class="nav-item">
          <form method="post" action="<?= e(url('/logout')) ?>" class="d-inline"><?= csrf_field() ?>
            <button class="btn btn-link nav-link" type="submit">Log out</button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</nav>
<main class="container py-4">
  <?php foreach ($flash ?? [] as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
      <?= e($f['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<?php if (!empty($scripts)): foreach ($scripts as $s): ?>
  <script src="<?= e(asset($s)) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
