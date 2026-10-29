(function () {
  'use strict';
  var rows = document.getElementById('moSvcRows'), tpl = document.getElementById('moSvcTpl');
  function fmt(n) { return '₪' + Math.round(n).toLocaleString('en-US'); }
  function totals() {
    var t = document.getElementById('moTotals'); if (!t || !rows) return;
    var once = 0, monthly = 0;
    rows.querySelectorAll('.mo-svc-row').forEach(function (r) {
      var p = parseFloat(r.querySelector('[name="svc_price[]"]').value) || 0;
      if (r.querySelector('[name="svc_type[]"]').value === 'monthly') monthly += p; else once += p;
    });
    t.innerHTML = 'חד־פעמי: <strong>' + fmt(once) + '</strong> · חודשי: <strong>' + fmt(monthly) + '</strong>';
  }
  function addRow(s) {
    var node = tpl.content.firstElementChild.cloneNode(true);
    if (s) {
      node.querySelector('[name="svc_name[]"]').value = s.name || '';
      node.querySelector('[name="svc_desc[]"]').value = s.desc || '';
      node.querySelector('[name="svc_type[]"]').value = s.type === 'monthly' ? 'monthly' : 'once';
      node.querySelector('[name="svc_price[]"]').value = s.price || '';
    }
    rows.appendChild(node);
    node.querySelector('[name="svc_name[]"]').focus();
    totals();
  }
  if (rows && tpl) {
    var add = document.getElementById('moAddRow');
    if (add) add.addEventListener('click', function () { addRow(null); });
    var lib = document.getElementById('moLib');
    if (lib) lib.addEventListener('change', function () {
      var s = (window.MOA && MOA.library || [])[+lib.value];
      if (lib.value !== '' && s) addRow(s);
      lib.value = '';
    });
    rows.addEventListener('click', function (e) {
      if (e.target.classList.contains('mo-del')) { e.target.closest('tr').remove(); totals(); }
    });
    rows.addEventListener('input', totals);
    rows.addEventListener('change', totals);
  }
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = document.getElementById(b.getAttribute('data-copy'));
      i.select();
      (navigator.clipboard ? navigator.clipboard.writeText(i.value) : Promise.reject()).catch(function () { document.execCommand('copy'); });
      var t = b.textContent; b.textContent = 'הועתק'; setTimeout(function () { b.textContent = t; }, 1600);
    });
  });
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });
  var edit = document.getElementById('moEdit'), dirty = false;
  if (edit) {
    edit.addEventListener('input', function () { dirty = true; });
    edit.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  }
})();
