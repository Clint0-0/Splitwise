/**
 * Expense form: live split preview. Mirrors SplitCalculator (integer paise,
 * largest remainder, payer first on ties). The SERVER result is authoritative;
 * this only helps the user see mistakes before submitting.
 */
(function () {
  'use strict';

  const pick = document.querySelector('[data-autosubmit]');
  if (pick) pick.addEventListener('change', () => pick.form.submit());

  const form = document.getElementById('expenseForm');
  const table = document.getElementById('participants');
  if (!form || !table) return;

  const total = form.querySelector('#total_amount');
  const payer = form.querySelector('#paid_by');
  const status = document.getElementById('splitStatus');
  const head = table.querySelector('.share-head');
  const hints = {
    equal: ['Share', 'Split equally'], percentage: ['Percent (%)', 'e.g. 50'],
    exact: ['Amount (₹)', 'e.g. 250.00'], shares: ['Shares', 'e.g. 1'],
    adjustment: ['Adjustment (₹, + or −)', 'e.g. +150'],
  };

  // "12.5" -> 1250 (hundredths); null if invalid
  function hundredths(s, allowNeg) {
    s = String(s).trim().replace(/[,₹\s+]/g, '');
    if (s === '') return 0;
    const re = allowNeg ? /^-?\d{1,10}(\.\d{1,2})?$/ : /^\d{1,10}(\.\d{1,2})?$/;
    if (!re.test(s)) return null;
    const neg = s.startsWith('-');
    const [i, d = ''] = s.replace('-', '').split('.');
    const v = parseInt(i, 10) * 100 + parseInt((d + '00').slice(0, 2), 10);
    return neg ? -v : v;
  }
  const rupees = (p) => (p < 0 ? '−' : '') + '₹' + (Math.abs(p) / 100).toFixed(2);

  function allocate(totalP, weights, payerId) {
    const ids = Object.keys(weights).map(Number);
    const sumW = ids.reduce((a, id) => a + weights[id], 0);
    if (sumW <= 0) return null;
    const out = {}, rem = {};
    ids.forEach((id) => { out[id] = Math.floor(totalP * weights[id] / sumW); rem[id] = (totalP * weights[id]) % sumW; });
    let left = totalP - ids.reduce((a, id) => a + out[id], 0);
    ids.sort((a, b) => (rem[b] - rem[a]) || ((a === payerId ? 0 : 1) - (b === payerId ? 0 : 1)) || (a - b));
    for (let k = 0; k < left; k++) out[ids[k]]++;
    return out;
  }

  function method() { return (form.querySelector('[name=split_method]:checked') || {}).value || 'equal'; }

  function update() {
    const m = method();
    head.textContent = hints[m][0];
    const rows = [...table.querySelectorAll('tbody tr')];
    const active = rows.filter((r) => r.querySelector('input[type=checkbox]').checked);
    rows.forEach((r) => {
      const inp = r.querySelector('.share-input');
      const on = r.querySelector('input[type=checkbox]').checked;
      inp.disabled = !on || m === 'equal';
      inp.placeholder = hints[m][1];
      r.querySelector('.preview').textContent = '–';
    });
    const t = hundredths(total.value, false);
    if (!t || active.length === 0) { status.textContent = active.length ? 'Enter the amount to see each part.' : 'Tick at least one person.'; status.className = 'split-status text-muted'; return; }

    const payerId = payer ? parseInt(payer.value, 10) : 0;
    const vals = {};
    let bad = false;
    active.forEach((r) => {
      const v = hundredths(r.querySelector('.share-input').value, m === 'adjustment');
      if (v === null) bad = true;
      vals[parseInt(r.dataset.user, 10)] = v || 0;
    });
    if (bad) { status.textContent = 'Some values are not valid numbers (max 2 decimals).'; status.className = 'split-status text-owes'; return; }

    let shares = null, msg = '', ok = true;
    const sum = Object.values(vals).reduce((a, b) => a + b, 0);
    if (m === 'equal') {
      shares = allocate(t, Object.fromEntries(Object.keys(vals).map((k) => [k, 1])), payerId);
    } else if (m === 'percentage') {
      ok = sum === 10000; msg = ok ? '' : 'Percentages add up to ' + (sum / 100).toFixed(2) + '% (need 100%).';
      if (ok) shares = allocate(t, vals, payerId);
    } else if (m === 'exact') {
      ok = sum === t; msg = ok ? '' : (sum < t ? 'Short by ' : 'Over by ') + rupees(Math.abs(t - sum)) + '.';
      shares = vals;
    } else if (m === 'shares') {
      ok = Object.values(vals).every((v) => v > 0); msg = ok ? '' : 'Every share must be more than 0.';
      if (ok) shares = allocate(t, vals, payerId);
    } else if (m === 'adjustment') {
      const base = t - sum;
      ok = base >= 0; msg = ok ? '' : 'Adjustments are more than the total.';
      if (ok) {
        const eq = allocate(base, Object.fromEntries(Object.keys(vals).map((k) => [k, 1])), payerId);
        shares = {}; Object.keys(vals).forEach((k) => { shares[k] = eq[k] + vals[k]; });
        if (Object.values(shares).some((v) => v < 0)) { ok = false; msg = 'An adjustment makes a share negative.'; }
      }
    }
    if (shares) active.forEach((r) => { r.querySelector('.preview').textContent = rupees(shares[r.dataset.user] ?? 0); });
    status.textContent = ok ? 'Adds up to ' + rupees(t) + ' ✓' : msg;
    status.className = 'split-status ' + (ok ? 'text-gets' : 'text-owes');
  }

  form.addEventListener('input', update);
  form.addEventListener('change', update);
  update();
})();
