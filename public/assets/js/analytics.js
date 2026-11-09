/**
 * Analytics charts. Every number comes from /api/analytics/* (real database data).
 * Design (dataviz method): magnitude -> one hue; two-series charts use the
 * validated pair --series-1 / --series-2 with a legend; thin marks, 4px rounded
 * bar ends, 2px line, recessive grid; tooltip on hover; every chart also has a
 * "Show as table" view (the marigold series is under 3:1 contrast, so the table
 * is the required relief).
 */
(function () {
  'use strict';
  const root = document.getElementById('analytics');
  if (!root || typeof Chart === 'undefined') return;

  const css = getComputedStyle(document.documentElement);
  const v = (name) => css.getPropertyValue(name).trim();
  const S1 = v('--series-1'), S2 = v('--series-2'), INK = v('--ink'), MUTED = v('--muted'), LINE = v('--line');
  const base = root.dataset.base;

  Chart.defaults.font.family = v('--font-body') || 'Mukta, sans-serif';
  Chart.defaults.font.size = 13;
  Chart.defaults.color = MUTED;
  Chart.defaults.plugins.legend.labels.boxWidth = 12;
  Chart.defaults.plugins.legend.labels.boxHeight = 12;
  Chart.defaults.plugins.legend.labels.color = INK;
  Chart.defaults.plugins.tooltip.backgroundColor = INK;
  Chart.defaults.plugins.tooltip.padding = 10;
  Chart.defaults.plugins.tooltip.cornerRadius = 8;
  Chart.defaults.maintainAspectRatio = false;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) Chart.defaults.animation = false;

  const inr = (x) => '₹' + Number(x).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const short = (x) => '₹' + Number(x).toLocaleString('en-IN', { notation: 'compact', maximumFractionDigits: 1 });
  const grid = { color: LINE, drawTicks: false };
  const noGrid = { display: false };
  const bar = { borderRadius: 4, borderSkipped: 'start', maxBarThickness: 28, borderWidth: 0 };
  const monthLabel = (ym) => new Date(ym + '-01T00:00:00').toLocaleDateString('en-IN', { month: 'short', year: '2-digit' });

  async function get(type) {
    const r = await fetch(base + '/' + type, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (!r.ok) throw new Error(type + ' ' + r.status);
    return (await r.json()).data;
  }
  function empty(id) {
    const box = document.getElementById(id).parentElement;
    box.innerHTML = '<div class="empty"><p>No data yet.</p></div>';
    box.style.height = 'auto';
  }
  // Table view: same numbers as the chart (text, not colour)
  function table(id, head, rows) {
    const el = document.querySelector('[data-table-for="' + id + '"]');
    if (!el) return;
    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    el.innerHTML = '<table class="table table-sm"><thead><tr>' + head.map((h, i) => '<th scope="col"' + (i ? ' class="num"' : '') + '>' + esc(h) + '</th>').join('') +
      '</tr></thead><tbody>' + rows.map((r) => '<tr>' + r.map((c, i) => (i ? '<td class="num">' : '<th scope="row" class="fw-normal">') + esc(c) + (i ? '</td>' : '</th>')).join('') + '</tr>').join('') + '</tbody></table>';
  }
  const failed = (id) => () => { empty(id); };

  // 1. category share: horizontal bars, one hue (magnitude, not identity)
  get('categories').then((d) => {
    if (!d.length) return empty('chartCategories');
    new Chart('chartCategories', { type: 'bar',
      data: { labels: d.map((x) => x.label), datasets: [{ label: 'Your share', data: d.map((x) => +x.total), backgroundColor: S1, ...bar }] },
      options: { indexAxis: 'y', plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => inr(c.parsed.x) } } },
        scales: { x: { grid, border: { display: false }, ticks: { callback: short } }, y: { grid: noGrid, border: { display: false }, ticks: { color: INK } } } } });
    table('chartCategories', ['Category', 'Your share'], d.map((x) => [x.label, inr(x.total)]));
  }).catch(failed('chartCategories'));

  // 2. monthly trend: one 2px line, no legend (the title names it)
  get('monthly').then((d) => {
    new Chart('chartMonthly', { type: 'line',
      data: { labels: d.map((x) => monthLabel(x.month)), datasets: [{ label: 'Your share', data: d.map((x) => +x.total),
        borderColor: S1, backgroundColor: S1, borderWidth: 2, pointRadius: 3, pointHoverRadius: 5, tension: 0.25 }] },
      options: { interaction: { mode: 'index', intersect: false }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => inr(c.parsed.y) } } },
        scales: { y: { beginAtZero: true, grid, border: { display: false }, ticks: { callback: short } }, x: { grid: noGrid } } } });
    table('chartMonthly', ['Month', 'Your share', 'Expenses'], d.map((x) => [monthLabel(x.month), inr(x.total), x.expense_count]));
  }).catch(failed('chartMonthly'));

  // 3. group totals: horizontal bars, one hue
  get('groups').then((d) => {
    if (!d.length) return empty('chartGroups');
    new Chart('chartGroups', { type: 'bar',
      data: { labels: d.map((x) => x.label), datasets: [{ label: 'Total spent', data: d.map((x) => +x.total), backgroundColor: S1, ...bar }] },
      options: { indexAxis: 'y', plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => inr(c.parsed.x) } } },
        scales: { x: { grid, border: { display: false }, ticks: { callback: short } }, y: { grid: noGrid, border: { display: false }, ticks: { color: INK } } } } });
    table('chartGroups', ['Group', 'Total spent', 'Expenses'], d.map((x) => [x.label, inr(x.total), x.expense_count]));
  }).catch(failed('chartGroups'));

  // 4. paid vs share: two series, validated pair, legend
  get('paid-vs-owed').then((d) => {
    if (!d.length) return empty('chartPaidOwed');
    new Chart('chartPaidOwed', { type: 'bar',
      data: { labels: d.map((x) => x.label), datasets: [
        { label: 'You paid', data: d.map((x) => +x.paid), backgroundColor: S1, ...bar },
        { label: 'Your share', data: d.map((x) => +x.owed), backgroundColor: S2, ...bar }] },
      options: { interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'top', align: 'start' }, tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + inr(c.parsed.y) } } },
        datasets: { bar: { categoryPercentage: 0.7, barPercentage: 0.9 } },
        scales: { y: { beginAtZero: true, grid, border: { display: false }, ticks: { callback: short } }, x: { grid: noGrid, ticks: { color: INK } } } } });
    table('chartPaidOwed', ['Group', 'You paid', 'Your share'], d.map((x) => [x.label, inr(x.paid), inr(x.owed)]));
  }).catch(failed('chartPaidOwed'));

  // 5. settlements sent / received
  get('settlements').then((d) => {
    if (!d.length) return empty('chartSettlements');
    new Chart('chartSettlements', { type: 'bar',
      data: { labels: d.map((x) => monthLabel(x.month)), datasets: [
        { label: 'Sent', data: d.map((x) => +x.sent), backgroundColor: S1, ...bar },
        { label: 'Received', data: d.map((x) => +x.received), backgroundColor: S2, ...bar }] },
      options: { interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'top', align: 'start' }, tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + inr(c.parsed.y) } } },
        scales: { y: { beginAtZero: true, grid, border: { display: false }, ticks: { callback: short } }, x: { grid: noGrid } } } });
    table('chartSettlements', ['Month', 'Sent', 'Received'], d.map((x) => [monthLabel(x.month), inr(x.sent), inr(x.received)]));
  }).catch(failed('chartSettlements'));
})();
