// Budget form: the scope select fills the hidden group_id (external file, so the CSP needs no inline script)
(function () {
  'use strict';
  var s = document.getElementById('scope'), g = document.getElementById('group_id');
  if (!s || !g) return;
  function sync() { var o = s.options[s.selectedIndex]; g.value = (o && o.dataset.group) || ''; }
  s.addEventListener('change', sync);
  sync();
})();
