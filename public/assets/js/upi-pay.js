/**
 * "Pay with UPI" panel on the Record-a-payment page (Phase 6).
 * Keeps the upi://pay link and its QR code in step with the form. Mirrors
 * App\Services\UpiLink::build(); shown only when YOU pay someone who saved a UPI ID.
 * Nothing is sent anywhere: the link just opens the payer's own UPI app.
 */
(function () {
  'use strict';
  const box = document.getElementById('upiPay');
  if (!box) return;
  const payer = document.getElementById('payer_id');
  const payee = document.getElementById('payee_id');
  const amount = document.getElementById('amount');
  const link = document.getElementById('upiLink');
  const who = document.getElementById('upiWho');
  const canvas = document.getElementById('upiQr');
  const uid = box.dataset.uid;
  const ID = /^[a-z0-9._-]{2,64}@[a-z][a-z0-9]{1,63}$/;

  function paise(s) {
    s = String(s).trim().replace(/[,₹\s]/g, '');
    if (!/^\d{1,10}(\.\d{1,2})?$/.test(s)) return 0;
    const [i, d = ''] = s.split('.');
    return parseInt(i, 10) * 100 + parseInt((d + '00').slice(0, 2), 10);
  }
  const rupees = (p) => '₹' + (p / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const enc = (v) => encodeURIComponent(v).replace(/%40/g, '@');
  const clean = (s) => s.replace(/[^\p{L}\p{N} .,\-]+/gu, ' ').replace(/\s+/gu, ' ').trim().slice(0, 50);

  function draw(text) {
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    if (typeof window.qrcode !== 'function') return;
    const qr = window.qrcode(0, 'M');
    qr.addData(text);
    qr.make();
    const n = qr.getModuleCount();
    const quiet = 4;
    const cell = Math.floor(canvas.width / (n + quiet * 2));
    const off = Math.floor((canvas.width - cell * n) / 2);
    ctx.fillStyle = '#000';
    for (let r = 0; r < n; r++) {
      for (let c = 0; c < n; c++) {
        if (qr.isDark(r, c)) ctx.fillRect(off + c * cell, off + r * cell, cell, cell);
      }
    }
  }

  function update() {
    const opt = payee.options[payee.selectedIndex];
    const upi = opt ? (opt.dataset.upi || '') : '';
    const amt = paise(amount.value);
    if (payer.value !== uid || payee.value === uid || !ID.test(upi) || amt <= 0) { box.hidden = true; return; }
    const name = opt.textContent.replace(/\s*\(you\)\s*$/, '').trim();
    const href = 'upi://pay?pa=' + enc(upi) + '&pn=' + enc(name.slice(0, 50)) + '&am=' + (amt / 100).toFixed(2)
      + '&cu=INR' + (clean(box.dataset.note) ? '&tn=' + enc(clean(box.dataset.note)) : '');
    link.href = href;
    who.textContent = '';
    who.append('Pay ');
    const b = document.createElement('strong'); b.textContent = name; who.append(b);
    who.append(' ' + rupees(amt) + ' at ');
    const m = document.createElement('span'); m.className = 'font-monospace'; m.textContent = upi; who.append(m);
    draw(href);
    box.hidden = false;
  }

  // Copy UPI ID fallback (clipboard API needs https or localhost; otherwise select the text)
  const copy = document.getElementById('copyUpi');
  const copyMsg = document.getElementById('copyMsg');
  if (copy) {
    copy.hidden = false;
    copy.addEventListener('click', async () => {
      const id = copy.dataset.upi;
      try { await navigator.clipboard.writeText(id); copyMsg.textContent = 'Copied ' + id; }
      catch (e) { copyMsg.textContent = 'UPI ID: ' + id; }
    });
  }

  // "Pay the full amount": the server-computed outstanding amount, only for the pair it was computed for
  const full = document.getElementById('fullAmount');
  const useFull = document.getElementById('useFull');
  if (useFull) useFull.addEventListener('click', (ev) => { ev.preventDefault(); amount.value = full.dataset.max; update(); });

  const btn = document.getElementById('recordBtn');
  function labels() {
    if (full) full.hidden = payer.value !== full.dataset.payer || payee.value !== full.dataset.payee;
    if (btn) btn.textContent = payer.value === uid ? btn.dataset.sent : (payee.value === uid ? btn.dataset.received : btn.dataset.other);
    const opt = payee.options[payee.selectedIndex];
    if (copy) copy.dataset.upi = opt ? (opt.dataset.upi || '') : '';
  }

  [payer, payee, amount].forEach((el) => { el.addEventListener('input', () => { labels(); update(); }); el.addEventListener('change', () => { labels(); update(); }); });
  labels();
  update();
})();
