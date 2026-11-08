/** Analytics charts: every number comes from /api/analytics/* (real database data). */
(function () {
  'use strict';
  const root = document.getElementById('analytics');
  if (!root || typeof Chart === 'undefined') return;
  const base = root.dataset.base;
  const inr = (v) => '₹' + Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const money = { callbacks: { label: (c) => (c.dataset.label ? c.dataset.label + ': ' : '') + inr(c.parsed.y ?? c.parsed) } };

  async function get(type) {
    const r = await fetch(base + '/' + type, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (!r.ok) throw new Error(type + ' ' + r.status);
    return (await r.json()).data;
  }
  function empty(id) {
    const c = document.getElementById(id);
    const p = document.createElement('p'); p.className = 'text-muted small mb-0'; p.textContent = 'No data yet.';
    c.replaceWith(p);
  }

  get('categories').then((d) => {
    if (!d.length) return empty('chartCategories');
    new Chart('chartCategories', { type: 'doughnut',
      data: { labels: d.map((x) => x.label), datasets: [{ data: d.map((x) => +x.total), backgroundColor: d.map((x) => x.color) }] },
      options: { plugins: { tooltip: { callbacks: { label: (c) => c.label + ': ' + inr(c.parsed) } } } } });
  });
  get('monthly').then((d) => {
    new Chart('chartMonthly', { type: 'line',
      data: { labels: d.map((x) => x.month), datasets: [{ label: 'Your share', data: d.map((x) => +x.total), tension: 0.25, fill: false }] },
      options: { plugins: { legend: { display: false }, tooltip: money }, scales: { y: { beginAtZero: true } } } });
  });
  get('groups').then((d) => {
    if (!d.length) return empty('chartGroups');
    new Chart('chartGroups', { type: 'bar',
      data: { labels: d.map((x) => x.label), datasets: [{ label: 'Total spent', data: d.map((x) => +x.total) }] },
      options: { indexAxis: 'y', plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => inr(c.parsed.x) } } } } });
  });
  get('paid-vs-owed').then((d) => {
    if (!d.length) return empty('chartPaidOwed');
    new Chart('chartPaidOwed', { type: 'bar',
      data: { labels: d.map((x) => x.label), datasets: [
        { label: 'You paid', data: d.map((x) => +x.paid) },
        { label: 'Your share', data: d.map((x) => +x.owed) }] },
      options: { plugins: { tooltip: money }, scales: { y: { beginAtZero: true } } } });
  });
  get('settlements').then((d) => {
    if (!d.length) return empty('chartSettlements');
    new Chart('chartSettlements', { type: 'bar',
      data: { labels: d.map((x) => x.month), datasets: [
        { label: 'Sent', data: d.map((x) => +x.sent) }, { label: 'Received', data: d.map((x) => +x.received) }] },
      options: { plugins: { tooltip: money }, scales: { y: { beginAtZero: true } } } });
  });
})();
