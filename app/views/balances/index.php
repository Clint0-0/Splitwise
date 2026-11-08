<h1 class="h3 mb-4">Balances</h1>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card card-body"><div class="small text-muted">Total paid (groups)</div><div class="fs-5 amount"><?= e(inr($overall['total_paid'])) ?></div></div></div>
  <div class="col-md-3"><div class="card card-body"><div class="small text-muted">Total share</div><div class="fs-5 amount"><?= e(inr($overall['total_owed'])) ?></div></div></div>
  <div class="col-md-3"><div class="card card-body"><div class="small text-muted">Receivable</div><div class="fs-5 amount text-gets"><?= e(inr($overall['receivable'])) ?></div></div></div>
  <div class="col-md-3"><div class="card card-body"><div class="small text-muted">Payable</div><div class="fs-5 amount text-owes"><?= e(inr($overall['payable'])) ?></div></div></div>
</div>
<div class="row g-4">
  <div class="col-lg-5"><div class="card"><div class="card-body">
    <h2 class="h6">By group</h2>
    <?php if (!$groups): ?><p class="text-muted mb-0">No groups.</p><?php endif; ?>
    <?php foreach ($groups as $g): ?>
      <div class="d-flex justify-content-between mb-1"><a href="<?= e(url('/groups/' . (int) $g['group_id'])) ?>"><?= e($g['name']) ?></a>
        <?php $net = $g['net_balance']; require __DIR__ . '/../partials/balance.php'; ?></div>
    <?php endforeach; ?>
  </div></div></div>
  <div class="col-lg-7"><div class="card"><div class="card-body">
    <h2 class="h6">Individual balances</h2>
    <?php if (!$pairs): ?><p class="text-muted mb-0">You are settled up with everyone.</p><?php else: ?>
    <table class="table table-sm mb-0">
      <thead><tr><th>Person</th><th>Group</th><th class="text-end">Original</th><th class="text-end">Settled</th><th class="text-end">Remaining</th></tr></thead>
      <tbody>
      <?php foreach ($pairs as $p): $iOwe = (int) $p['debtor_id'] === $uid; ?>
        <tr><td><?= $iOwe ? 'You owe <strong>' . e($p['creditor_name']) . '</strong>' : '<strong>' . e($p['debtor_name']) . '</strong> owes you' ?></td>
          <td><?= e($p['group_name']) ?></td>
          <td class="text-end amount"><?= e(inr($p['original_owed'])) ?></td>
          <td class="text-end amount"><?= e(inr($p['settled'])) ?></td>
          <td class="text-end amount fw-semibold <?= $iOwe ? 'text-owes' : 'text-gets' ?>"><?= e(inr($p['remaining'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="small text-muted mt-2 mb-0">"Original" is what one person's shares came to before any money was paid back. Remaining = original − amounts owed back − settled.</p>
    <?php endif; ?>
  </div></div></div>
</div>
