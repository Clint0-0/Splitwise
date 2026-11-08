<?php $gid = (int) $group['group_id']; $active = array_filter($members, fn($m) => $m['status'] === 'active'); ?>
<h1 class="h3 mb-1">Record a payment</h1>
<p class="text-muted"><a href="<?= e(url("/groups/$gid")) ?>"><?= e($group['name']) ?></a></p>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card"><div class="card-body">
      <form method="post" action="<?= e(url('/settlements')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="group_id" value="<?= $gid ?>">
        <input type="hidden" name="request_token" value="<?= e($token) ?>">
        <?= field_error($errors, 'request_token') ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="payer_id">Who paid</label>
            <select class="form-select<?= is_invalid($errors, 'payer_id') ?>" id="payer_id" name="payer_id">
              <?php foreach ($active as $m): ?>
                <option value="<?= (int) $m['user_id'] ?>" <?= (int) old($old, 'payer_id', $prefill['payer_id']) === (int) $m['user_id'] ? 'selected' : '' ?>><?= e($m['full_name']) ?><?= (int) $m['user_id'] === $uid ? ' (you)' : '' ?></option>
              <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'payer_id') ?>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="payee_id">Who received</label>
            <select class="form-select<?= is_invalid($errors, 'payee_id') ?>" id="payee_id" name="payee_id">
              <option value="">Choose…</option>
              <?php foreach ($active as $m): ?>
                <option value="<?= (int) $m['user_id'] ?>" <?= (int) old($old, 'payee_id', $prefill['payee_id']) === (int) $m['user_id'] ? 'selected' : '' ?>><?= e($m['full_name']) ?><?= (int) $m['user_id'] === $uid ? ' (you)' : '' ?></option>
              <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'payee_id') ?>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="amount">Amount (₹)</label>
            <input class="form-control<?= is_invalid($errors, 'amount') ?>" id="amount" name="amount" inputmode="decimal" value="<?= e(old($old, 'amount', $prefill['amount'])) ?>">
            <?= field_error($errors, 'amount') ?>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="settlement_date">Date</label>
            <input class="form-control<?= is_invalid($errors, 'settlement_date') ?>" type="date" id="settlement_date" name="settlement_date" max="<?= date('Y-m-d') ?>" value="<?= e(old($old, 'settlement_date', date('Y-m-d'))) ?>">
            <?= field_error($errors, 'settlement_date') ?>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="method">Method</label>
            <select class="form-select" id="method" name="method">
              <?php foreach (['cash' => 'Cash', 'upi' => 'UPI', 'bank_transfer' => 'Bank transfer', 'other' => 'Other'] as $k => $v): ?>
                <option value="<?= $k ?>" <?= old($old, 'method', 'upi') === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="note">Note (optional)</label>
            <input class="form-control" id="note" name="note" maxlength="255" value="<?= e(old($old, 'note')) ?>">
          </div>
        </div>
        <p class="small text-muted mt-3">If you are the receiver, the payment is confirmed at once. If you paid, the receiver must confirm it before balances change.</p>
        <button class="btn btn-success" type="submit">Record payment</button>
        <a class="btn btn-link" href="<?= e(url("/groups/$gid")) ?>">Cancel</a>
      </form>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card"><div class="card-body">
      <h2 class="h6">Balances</h2>
      <?php foreach ($active as $m): ?>
        <div class="d-flex justify-content-between small"><span><?= e($m['full_name']) ?></span><?php $net = $m['net_balance']; require __DIR__ . '/../partials/balance.php'; ?></div>
      <?php endforeach; ?>
      <h2 class="h6 mt-3">Suggested transfers</h2>
      <?php if (!$suggestions): ?><p class="small text-muted mb-0">Everyone is settled up.</p><?php endif; ?>
      <?php foreach ($suggestions as $s): ?>
        <div class="small"><a href="<?= e(url("/groups/$gid/settle?" . http_build_query(['payer' => $s['from'], 'payee' => $s['to'], 'amount' => $s['amount']]))) ?>"><?= e($s['from_name']) ?> → <?= e($s['to_name']) ?>: <?= e(inr($s['amount'])) ?></a></div>
      <?php endforeach; ?>
    </div></div>
  </div>
</div>
