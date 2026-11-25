<?php $gid = (int) $group['group_id']; $active = array_filter($members, fn($m) => $m['status'] === 'active');
// "Pay with UPI": only when YOU are paying someone who has a UPI ID (Phase 6)
$payerSel = (int) old($old, 'payer_id', $prefill['payer_id']);
$payeeSel = (int) old($old, 'payee_id', $prefill['payee_id']);
$amtSel   = (string) old($old, 'amount', $prefill['amount']);
$amtPaise = preg_match('/^\d{1,10}(\.\d{1,2})?$/', $amtSel) ? App\Helpers\Money::toPaise($amtSel) : 0;
$names    = array_column($members, 'full_name', 'user_id');
$upiNote  = 'SplitWise ' . $group['name'];
$upiHref  = $payerSel === $uid && $payeeSel !== $uid && isset($upi[$payeeSel])
    ? App\Services\UpiLink::build($upi[$payeeSel], (string) ($names[$payeeSel] ?? ''), $amtPaise, $upiNote) : null; ?>
<div class="page-head">
  <div>
    <h1 class="page-title">Record a payment</h1>
    <p class="page-sub">in <a href="<?= e(url("/groups/$gid")) ?>"><?= e($group['name']) ?></a></p>
  </div>
</div>

<div class="grid-2">
  <section class="panel" aria-label="Payment details">
    <form method="post" action="<?= e(url('/settlements')) ?>" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="group_id" value="<?= $gid ?>">
      <input type="hidden" name="request_token" value="<?= e($token) ?>">
      <?= field_error($errors, 'request_token') ?>
      <div class="row g-3">
        <div class="col-sm-6">
          <label class="form-label" for="payer_id">Who paid</label>
          <select class="form-select<?= is_invalid($errors, 'payer_id') ?>" id="payer_id" name="payer_id">
            <?php foreach ($active as $m): ?>
              <option value="<?= (int) $m['user_id'] ?>" <?= (int) old($old, 'payer_id', $prefill['payer_id']) === (int) $m['user_id'] ? 'selected' : '' ?>><?= e($m['full_name']) ?><?= (int) $m['user_id'] === $uid ? ' (you)' : '' ?></option>
            <?php endforeach; ?>
          </select>
          <?= field_error($errors, 'payer_id') ?>
        </div>
        <div class="col-sm-6">
          <label class="form-label" for="payee_id">Who received it</label>
          <select class="form-select<?= is_invalid($errors, 'payee_id') ?>" id="payee_id" name="payee_id">
            <option value="">Choose…</option>
            <?php foreach ($active as $m): ?>
              <option value="<?= (int) $m['user_id'] ?>" <?= isset($upi[(int) $m['user_id']]) ? 'data-upi="' . e($upi[(int) $m['user_id']]) . '"' : '' ?> <?= (int) old($old, 'payee_id', $prefill['payee_id']) === (int) $m['user_id'] ? 'selected' : '' ?>><?= e($m['full_name']) ?><?= (int) $m['user_id'] === $uid ? ' (you)' : '' ?></option>
            <?php endforeach; ?>
          </select>
          <?= field_error($errors, 'payee_id') ?>
        </div>
        <div class="col-sm-6">
          <label class="form-label" for="amount">Amount (₹)</label>
          <input class="form-control form-control-lg<?= is_invalid($errors, 'amount') ?>" id="amount" name="amount" inputmode="decimal" placeholder="0.00" value="<?= e(old($old, 'amount', $prefill['amount'])) ?>">
          <?= field_error($errors, 'amount') ?>
          <?php if ($maxPayable !== '0.00' && (int) $prefill['payee_id'] > 0): ?>
            <div class="form-text" id="fullAmount" data-payer="<?= (int) $prefill['payer_id'] ?>" data-payee="<?= (int) $prefill['payee_id'] ?>" data-max="<?= e($maxPayable) ?>">
              Outstanding: <strong><?= e(inr($maxPayable)) ?></strong> (after pending payments).
              <a href="<?= e(url("/groups/$gid/settle?" . http_build_query(['payer' => $prefill['payer_id'], 'payee' => $prefill['payee_id'], 'amount' => $maxPayable]))) ?>" id="useFull"><?= (int) $prefill['payer_id'] === $uid ? 'Pay the full amount' : 'Use the full amount' ?></a> or type less for a part payment.
            </div>
          <?php endif; ?>
        </div>
        <div class="col-sm-6">
          <label class="form-label" for="settlement_date">Date</label>
          <input class="form-control form-control-lg<?= is_invalid($errors, 'settlement_date') ?>" type="date" id="settlement_date" name="settlement_date" max="<?= date('Y-m-d') ?>" value="<?= e(old($old, 'settlement_date', date('Y-m-d'))) ?>">
          <?= field_error($errors, 'settlement_date') ?>
        </div>
        <div class="col-sm-6">
          <label class="form-label" for="method">How</label>
          <select class="form-select" id="method" name="method">
            <?php foreach (['upi' => 'UPI', 'cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'other' => 'Other'] as $k => $v): ?>
              <option value="<?= $k ?>" <?= old($old, 'method', 'upi') === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-6">
          <label class="form-label" for="note">Note <span class="text-muted fw-normal">(optional)</span></label>
          <input class="form-control" id="note" name="note" maxlength="255" value="<?= e(old($old, 'note')) ?>" placeholder="GPay reference, what it was for…">
        </div>
      </div>
      <p class="note mt-3">If you received the money, it counts straight away. If you paid, it stays <strong>pending</strong> until the other person confirms they received it.</p>
      <button class="btn btn-settle" type="submit" id="recordBtn" data-sent="I've paid: mark as sent" data-received="Record money I received" data-other="Record payment"><?= $payerSel === $uid ? "I've paid: mark as sent" : ($payeeSel === $uid ? 'Record money I received' : 'Record payment') ?></button>
      <a class="btn btn-link" href="<?= e(url("/groups/$gid")) ?>">Cancel</a>
    </form>
  </section>

  <div class="stack">
    <section class="panel upi-pay" id="upiPay" aria-labelledby="upi-h" data-uid="<?= $uid ?>" data-note="<?= e($upiNote) ?>"<?= $upiHref === null ? ' hidden' : '' ?>>
      <h2 id="upi-h"><i class="bi bi-phone" aria-hidden="true"></i> Pay with UPI</h2>
      <p class="mb-2" id="upiWho"><?php if ($upiHref !== null): ?>Pay <strong><?= e($names[$payeeSel] ?? '') ?></strong> <?= e(inr(App\Helpers\Money::fromPaise($amtPaise))) ?> at <span class="font-monospace"><?= e($upi[$payeeSel]) ?></span><?php endif; ?></p>
      <div class="upi-body">
        <canvas id="upiQr" width="176" height="176" role="img" aria-label="QR code to pay with any UPI app"></canvas>
        <div>
          <a class="btn btn-settle" id="upiLink" href="<?= e($upiHref ?? '#') ?>"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Open UPI app</a>
          <button type="button" class="btn btn-quiet btn-sm ms-1" id="copyUpi" data-upi="<?= e($upiHref !== null ? $upi[$payeeSel] : '') ?>" hidden><i class="bi bi-clipboard" aria-hidden="true"></i> Copy UPI ID</button>
          <span class="note ms-1" id="copyMsg" aria-live="polite"></span>
          <p class="note mt-2 mb-0">On a phone, tap the button. On a computer, scan the code with GPay, PhonePe, Paytm or BHIM. If neither works, copy the UPI ID into your app.</p>
        </div>
      </div>
      <p class="note mt-3 mb-0">The money goes straight from your bank to theirs; SplitWise+ never touches it and can't see whether it went through.
        Opening the app does not mean the payment happened. After paying, put the UPI reference in the note and press <strong>I've paid: mark as sent</strong>. It counts once they confirm they received it.</p>
    </section>
    <section class="section" aria-labelledby="sug-h">
      <h2 id="sug-h">Suggested payments</h2>
      <?php if (!$suggestions): ?><div class="empty"><p>Everyone is settled up.</p></div><?php endif; ?>
      <ul class="slips">
        <?php foreach ($suggestions as $s): ?>
          <li class="slip<?= $s['from'] === $uid ? ' is-owes' : ($s['to'] === $uid ? ' is-gets' : '') ?>">
            <span class="avatar sm" aria-hidden="true"><?= e(initials($s['from_name'])) ?></span>
            <a class="slip-who text-decoration-none" href="<?= e(url("/groups/$gid/settle?" . http_build_query(['payer' => $s['from'], 'payee' => $s['to'], 'amount' => $s['amount']]))) ?>"><strong><?= e($s['from_name']) ?></strong> pays <strong><?= e($s['to_name']) ?></strong></a>
            <span class="slip-amount"><?= e(inr($s['amount'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($suggestions): ?><p class="note mt-2">Choose one to fill in the form.</p><?php endif; ?>
    </section>
    <section class="panel" aria-labelledby="bal-h">
      <h2 id="bal-h">Balances</h2>
      <?php foreach ($active as $m): ?>
        <div class="d-flex justify-content-between py-1"><span><?= e($m['full_name']) ?></span><?php $net = $m['net_balance']; $open = $m['open_debts'] ?? 0; require __DIR__ . '/../partials/balance.php'; ?></div>
      <?php endforeach; ?>
    </section>
  </div>
</div>
