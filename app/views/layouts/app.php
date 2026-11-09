<?php
/** Main layout (logged in): left rail on desktop, top bar + drawer on mobile. */
use App\Core\Auth;

$me = Auth::user();
$unread = $me ? (new App\Models\NotificationModel())->unreadCount((int) $me['user_id']) : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#E6E0F5">
  <title><?= e($title ?? 'SplitWise+') ?> – SplitWise+</title>
  <link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<a class="visually-hidden-focusable" href="#main">Skip to content</a>
<div class="app">
  <header class="topbar">
    <button class="icon-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#drawer" aria-controls="drawer" aria-label="Open menu"><i class="bi bi-list"></i></button>
    <a class="brand" href="<?= e(url('/dashboard')) ?>"><span class="brand-mark" aria-hidden="true">₹</span> SplitWise+</a>
    <a class="icon-btn" href="<?= e(url('/notifications')) ?>" aria-label="Notifications<?= $unread ? ", $unread unread" : '' ?>">
      <i class="bi bi-bell"></i><?php if ($unread > 0): ?><span class="dot"><?= (int) $unread ?></span><?php endif; ?>
    </a>
  </header>
  <div class="offcanvas offcanvas-start rail-drawer" tabindex="-1" id="drawer" aria-label="Menu">
    <div class="rail"><?php require __DIR__ . '/../partials/nav.php'; ?></div>
  </div>
  <aside class="rail d-none d-lg-flex"><?php require __DIR__ . '/../partials/nav.php'; ?></aside>

  <main class="content" id="main">
    <?php foreach ($flash ?? [] as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="<?= $f['type'] === 'danger' ? 'alert' : 'status' ?>">
        <?= e($f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<?php foreach ($scripts ?? [] as $s): ?>
  <script src="<?= e(asset($s)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
