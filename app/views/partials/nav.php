<?php
/** Main navigation, used in the desktop rail and the mobile drawer. */
use App\Core\Auth;
$current = (new App\Core\Request())->path;
$items = [
    ['/dashboard', 'Dashboard', 'bi-house'],
    ['/groups', 'Groups', 'bi-people'],
    ['/expenses', 'Expenses', 'bi-receipt'],
    ['/balances', 'Balances', 'bi-wallet2'],
    ['/settlements', 'Settlements', 'bi-arrow-left-right'],
    ['/budgets', 'Budgets', 'bi-piggy-bank'],
    ['/analytics', 'Analytics', 'bi-bar-chart-line'],
    ['/notifications', 'Notifications', 'bi-bell'],
];
if (Auth::isAdmin()) {
    $items[] = ['/admin', 'Admin', 'bi-shield-lock'];
}
?>
<a class="brand" href="<?= e(url('/dashboard')) ?>"><span class="brand-mark" aria-hidden="true">₹</span> SplitWise+</a>
<a class="btn btn-primary btn-add" href="<?= e(url('/expenses/create')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add expense</a>
<nav aria-label="Main">
  <ul class="rail-nav">
    <?php foreach ($items as [$href, $label, $icon]):
      $active = $current === $href || str_starts_with($current, $href . '/'); ?>
      <li><a href="<?= e(url($href)) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
        <i class="bi <?= e($icon) ?>" aria-hidden="true"></i> <?= e($label) ?>
        <?php if ($href === '/notifications' && $unread > 0): ?><span class="count" aria-label="<?= (int) $unread ?> unread"><?= (int) $unread ?></span><?php endif; ?>
      </a></li>
    <?php endforeach; ?>
  </ul>
</nav>
<div class="rail-foot">
  <a class="rail-user" href="<?= e(url('/profile')) ?>">
    <span class="avatar sm" aria-hidden="true"><?= e(initials($me['full_name'] ?? '')) ?></span>
    <span><span class="rail-user-name d-block"><?= e($me['full_name'] ?? '') ?></span><span class="rail-user-sub">@<?= e($me['username'] ?? '') ?></span></span>
  </a>
  <form method="post" action="<?= e(url('/logout')) ?>" class="mt-2"><?= csrf_field() ?>
    <button class="btn btn-link btn-sm px-0" type="submit"><i class="bi bi-box-arrow-left" aria-hidden="true"></i> Log out</button>
  </form>
</div>
