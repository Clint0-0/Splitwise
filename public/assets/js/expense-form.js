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
    adjustment: ['Adjustment (₹, + or −)', 'e.g. +150'], itemized: ['Share', ''],
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

  // ---- itemized bill (Phase 6). Mirrors SplitCalculator::itemized(). ----
  const itemBox = document.getElementById('itemBox');
  const equalBox = document.getElementById('equalBox');
  const itemList = document.getElementById('itemRows');
  const addItem = document.getElementById('addItem');
  const itemStatus = document.getElementById('itemStatus');
  const preview = document.getElementById('itemPreview');

  function itemized(totalP, payerId, items) {
    const sub = {}, out = {};
    let sumItems = 0;
    items.forEach((it) => {
      const part = allocate(it.amount, Object.fromEntries(it.users.map((u) => [u, (it.weights && it.weights[u]) || 100])), payerId);
      Object.keys(part).forEach((u) => { sub[u] = (sub[u] || 0) + part[u]; });
      sumItems += it.amount;
    });
    const extra = totalP - sumItems;
    Object.keys(sub).forEach((u) => { out[u] = sub[u]; });
    if (extra !== 0) {
      const spread = allocate(Math.abs(extra), Object.fromEntries(Object.keys(sub).map((u) => [u, sub[u]])), payerId);
      if (spread) Object.keys(spread).forEach((u) => { out[u] += extra > 0 ? spread[u] : -spread[u]; });
    }
    return { shares: out, sub, extra, sumItems };
  }

  function updateItems() {
    if (!itemList) return;
    const rows = [...itemList.querySelectorAll('.item-row')];
    const items = [];
    let bad = '';
    rows.forEach((r, k) => {
      const name = r.querySelector('[name$="[name]"]').value.trim();
      const amtRaw = r.querySelector('[name$="[amount]"]').value;
      if (name === '' && amtRaw.trim() === '') return;
      const amount = hundredths(amtRaw, false);
      const weights = {};
      let badUnits = false;
      r.querySelectorAll('.units-cell input').forEach((inp) => {
        const uid = parseInt(inp.name.match(/\[units\]\[(\d+)\]/)[1], 10);
        if (inp.value.trim() === '') return;
        const w = hundredths(inp.value, false);
        if (w === null || w > 999999) { badUnits = true; return; }
        if (w > 0) {
          weights[uid] = w;
          const chip = r.querySelector('input[type=checkbox][value="' + uid + '"]');
          if (chip && !chip.checked) chip.checked = true;   // typing units ticks the person (as the server does)
        }
      });
      const users = [...r.querySelectorAll('input[type=checkbox]:checked')].map((c) => parseInt(c.value, 10));
      users.forEach((u) => { if (weights[u] === undefined) { const inp = r.querySelector('.units-cell input[name$="[units][' + u + ']"]'); if (inp && inp.value.trim() !== '' && hundredths(inp.value, false) === 0) badUnits = true; } });
      if (!name) bad = bad || 'Item ' + (k + 1) + ' needs a name.';
      else if (!amount) bad = bad || 'Item ' + (k + 1) + ' needs an amount.';
      else if (badUnits) bad = bad || 'Item ' + (k + 1) + ': units must be numbers above 0.';
      else if (!users.length) bad = bad || 'Tick who had item ' + (k + 1) + '.';
      else items.push({ amount, users, weights });
    });
    preview.querySelectorAll('tbody tr').forEach((tr) => { tr.querySelectorAll('.num').forEach((c) => { c.textContent = '–'; }); tr.hidden = false; });
    const t = hundredths(total.value, false);
    const fail = (msg) => { itemStatus.textContent = msg; itemStatus.className = 'split-status text-owes'; };
    if (bad) return fail(bad);
    if (!items.length) { itemStatus.textContent = 'Add the bill items and tick who had each.'; itemStatus.className = 'split-status text-muted'; return; }
    if (!t) { itemStatus.textContent = 'Enter the bill total above (including tax).'; itemStatus.className = 'split-status text-muted'; return; }
    // optional charge lines: must reconcile exactly with the total when present
    let chargeSum = 0, anyCharge = false, badCharge = '';
    document.querySelectorAll('#chargeRows .charge-row').forEach((row, k) => {
      const amtRaw = row.querySelector('.charge-amount').value.trim();
      if (amtRaw === '') return;
      const kind = row.querySelector('select').value;
      const v = hundredths(amtRaw, kind === 'other');
      if (!kind) badCharge = badCharge || 'Choose the type of charge ' + (k + 1) + '.';
      else if (kind !== 'other' && amtRaw.startsWith('-')) badCharge = badCharge || 'Charge ' + (k + 1) + ': type it without a minus sign' + (kind === 'discount' ? ' (discounts are subtracted automatically).' : '.');
      else if (v === null || v === 0) badCharge = badCharge || 'Charge ' + (k + 1) + ' needs a non-zero amount.';
      else { anyCharge = true; chargeSum += kind === 'discount' ? -Math.abs(v) : v; }
    });
    if (badCharge) return fail(badCharge);
    const itemSum = items.reduce((a, it) => a + it.amount, 0);
    if (anyCharge && itemSum + chargeSum !== t) {
      return fail('Items ' + rupees(itemSum) + ' + charges ' + rupees(chargeSum) + ' = ' + rupees(itemSum + chargeSum) + ', but the bill total is ' + rupees(t) + '.');
    }
    const payerId = payer ? parseInt(payer.value, 10) : 0;
    const r = itemized(t, payerId, items);
    if (r.extra <= -r.sumItems) return fail('The discount is larger than the items.');
    preview.querySelectorAll('tbody tr').forEach((tr) => {
      const u = tr.dataset.user;
      if (!(u in r.shares)) { tr.hidden = true; return; }
      tr.querySelector('.p-items').textContent = rupees(r.sub[u]);
      tr.querySelector('.p-extra').textContent = rupees(r.shares[u] - r.sub[u]);
      tr.querySelector('.p-total').textContent = rupees(r.shares[u]);
    });
    const extraTxt = r.extra === 0 ? 'no tax or discount' : (r.extra > 0 ? rupees(r.extra) + ' tax / charges shared' : rupees(-r.extra) + ' discount shared');
    itemStatus.textContent = 'Items ' + rupees(r.sumItems) + ', ' + extraTxt + '. Adds up to ' + rupees(t) + ' ✓';
    itemStatus.className = 'split-status text-gets';
  }

  // ---- "Pick from bill": copy a scanned line into this row (the person still checks it) ----
  function setUncertain(row, on) {
    row.classList.toggle('item-uncertain', on);
    let note = row.querySelector('.uncertain-note');
    let flag = row.querySelector('input[name$="[uncertain]"]');
    if (!on) { if (note) note.remove(); if (flag) flag.remove(); return; }
    if (!note) {
      note = document.createElement('p');
      note.className = 'uncertain-note';
      note.innerHTML = '<i class="bi bi-exclamation-triangle" aria-hidden="true"></i> ';
      note.appendChild(document.createTextNode('Hard to read on the photo: check this line.'));
      row.prepend(note);
    }
    if (!flag) {
      flag = document.createElement('input');
      flag.type = 'hidden';
      flag.name = 'items[' + row.dataset.index + '][uncertain]';
      flag.value = '1';
      row.appendChild(flag);
    }
  }
  function markUsedLines() {
    if (!itemList) return;
    const used = new Set([...itemList.querySelectorAll('select[data-pick]')].map((s) => s.value).filter(Boolean));
    itemList.querySelectorAll('select[data-pick]').forEach((sel) => {
      [...sel.options].forEach((o) => {
        if (!o.value) return;
        const base = o.dataset.label || (o.dataset.label = o.textContent);
        o.textContent = used.has(o.value) && sel.value !== o.value ? base + ' (used)' : base;
      });
    });
  }
  if (itemList) {
    itemList.addEventListener('change', (ev) => {
      const sel = ev.target.closest('select[data-pick]');
      if (!sel) return;
      const row = sel.closest('.item-row');
      const o = sel.selectedOptions[0];
      const idx = row.dataset.index;
      const set = (f, v) => { const el = row.querySelector('[name="items[' + idx + '][' + f + ']"]'); if (el) { el.value = v; el.classList.remove('is-invalid'); } };
      if (o && o.value) {
        set('name', o.dataset.name); set('qty', o.dataset.qty || '1'); set('amount', o.dataset.amount);
        setUncertain(row, o.dataset.uncertain === '1');
        const first = row.querySelector('.item-who input[type=checkbox]');
        if (first) first.focus();   // next step: tick who had it
      }
      markUsedLines();
      updateItems();
    });
    // typed over a picked line? then the row no longer shows that bill line
    itemList.addEventListener('input', (ev) => {
      const row = ev.target.closest('.item-row');
      const sel = row && row.querySelector('select[data-pick]');
      if (!sel || !sel.value || !/\[(name|amount)\]$/.test(ev.target.name || '')) return;
      const o = sel.selectedOptions[0];
      const idx = row.dataset.index;
      const val = (f) => (row.querySelector('[name="items[' + idx + '][' + f + ']"]') || {}).value || '';
      const same = val('name').trim() === o.dataset.name && Number(val('amount')) === Number(o.dataset.amount);
      if (!same) { sel.value = ''; markUsedLines(); }
    });
    markUsedLines();
  }

  if (addItem && itemList) {
    addItem.hidden = false;
    addItem.addEventListener('click', () => {
      const rows = itemList.querySelectorAll('.item-row');
      if (rows.length >= 50) return;
      const last = rows[rows.length - 1];
      const from = last.dataset.index;
      const to = String(Math.max(...[...rows].map((r) => parseInt(r.dataset.index, 10))) + 1);
      const clone = last.cloneNode(true);
      clone.dataset.index = to;
      clone.querySelectorAll('[name],[id],[for]').forEach((el) => {
        ['name', 'id', 'for'].forEach((a) => {
          const v = el.getAttribute(a);
          if (v) el.setAttribute(a, v.replace('items[' + from + ']', 'items[' + to + ']').replace('it_' + from + '_', 'it_' + to + '_'));
        });
      });
      clone.querySelectorAll('input:not([type=checkbox])').forEach((i) => { i.value = ''; i.classList.remove('is-invalid'); });
      clone.querySelectorAll('input[type=checkbox]').forEach((c) => { c.checked = false; });
      clone.querySelectorAll('select[data-pick]').forEach((sel) => { sel.selectedIndex = 0; });
      setUncertain(clone, false);
      clone.querySelectorAll('.invalid-feedback').forEach((x) => x.remove());
      const det = clone.querySelector('details'); if (det) det.open = false;
      const leg = clone.querySelector('legend');
      if (leg) leg.textContent = 'Who had item ' + (rows.length + 1);
      itemList.appendChild(clone);
      markUsedLines();
      clone.querySelector('input').focus();
    });
  }

  function update() {
    const m = method();
    if (itemBox && equalBox) {
      itemBox.hidden = m !== 'itemized';
      equalBox.hidden = m === 'itemized';
      if (m === 'itemized') { updateItems(); return; }
    }
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
