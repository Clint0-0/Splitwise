<?php $net = (string) $overall['net_balance']; $cmp = bccomp_str($net, '0'); ?>
<div class="page-head"><h1 class="page-title">Balances</h1></div>

<section class="hero" aria-label="Overall balance">
  <p class="hero-line"><?= $cmp > 0 ? 'Overall, you get back' : ($cmp < 0 ? 'Overall, you owe' : 'Overall, you are') ?></p>
  <span class="hero-figure <?= $cmp > 0 ? 'text-gets' : ($cmp < 0 ? 'text-owes' : '') ?>"><?= $cmp === 0 ? 'settled up' : e(inr(abs_amount($net))) ?></span>
  <dl class="stat-line mt-3 mb-0">
    <div><dt>You paid in groups</dt><dd><?= e(inr($overall['total_paid'])) ?></dd></div>
    <div><dt>Your share of it</dt><dd><?= e(inr($overall['total_owed'])) ?></dd></div>
    <div><dt>Others owe you</dt><dd class="<?= bccomp_str((string) $overall['receivable'], '0') > 0 ? 'text-gets' : '' ?>"><?= e(inr($overall['receivable'])) ?></dd></div>
    <div><dt>You owe others</dt><dd class="<?= bccomp_str((string) $overall['payable'], '0') > 0 ? 'text-owes' : '' ?>"><?= e(inr($overall['payable'])) ?></dd></div>
  </dl>
</section>

<div class="grid-2">
  <section class="section" aria-labelledby="iou-h">
    <h2 id="iou-h">Person by person</h2>
    <?php require __DIR__ . '/../partials/slips.php'; ?>
    <?php if ($pairs): ?><p class="note mt-2">The amount is what is still open. Payments that were confirmed are already taken off.</p><?php endif; ?>
  </section>
  <section class="panel" aria-labelledby="grp-h">
    <h2 id="grp-h">Group by group</h2>
    <?php if (!$groups): ?><div class="empty"><p>No groups yet.</p></div><?php endif; ?>
    <ul class="rows">
      <?php foreach ($groups as $g): ?>
        <li class="d-flex justify-content-between align-items-center gap-3 py-2 border-bottom">
          <a class="fw-bold text-decoration-none" href="<?= e(url('/groups/' . (int) $g['group_id'])) ?>"><?= e($g['name']) ?></a>
          <?php $net = $g['net_balance']; require __DIR__ . '/../partials/balance.php'; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
