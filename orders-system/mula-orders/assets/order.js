(function () {
  'use strict';
  var M = window.MO || {};
  var cur = 1, saved = !!M.saved, busy = false;
  var steps = document.querySelectorAll('.o-steps li');
  var panels = document.querySelectorAll('.o-panel');
  var bar = document.getElementById('oBar');
  var form = document.getElementById('oForm');
  var toastEl = document.getElementById('oToast');
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  function toast(msg, err) {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.classList.toggle('err', !!err);
    toastEl.classList.add('show');
    clearTimeout(toast.t);
    toast.t = setTimeout(function () { toastEl.classList.remove('show'); }, 4200);
  }

  function post(path, body) {
    body.token = M.token;
    return fetch(M.rest + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'omit',
      body: JSON.stringify(body)
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) { j._status = r.status; return j; });
    });
  }

  function go(n, scroll) {
    cur = n;
    panels.forEach(function (p) { var on = +p.dataset.step === n; p.hidden = !on; p.classList.toggle('is-active', on); });
    steps.forEach(function (s) {
      var k = +s.dataset.go, b = s.querySelector('button');
      s.classList.toggle('is-active', k === n);
      s.classList.toggle('is-done', k < n);
      if (k === n) b.setAttribute('aria-current', 'step'); else b.removeAttribute('aria-current');
    });
    bar.style.width = (n * 25) + '%';
    if (n === 4) refreshSign();
    if (scroll !== false) {
      var top = document.querySelector('.o-steps').getBoundingClientRect().top + window.pageYOffset - 100;
      window.scrollTo({ top: top, behavior: reduce ? 'auto' : 'smooth' });
      var h = document.querySelector('.o-panel[data-step="' + n + '"] h2');
      if (h) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); }
    }
  }

  /* ---------- client details ---------- */
  function isCompany() { return form.ctype.value === 'company'; }
  function syncType() {
    var c = isCompany();
    form.querySelectorAll('[data-company]').forEach(function (el) { el.hidden = !c; });
    form.querySelectorAll('[data-osek]').forEach(function (el) { el.hidden = c; });
  }
  function setErr(field, bad) {
    field.classList.toggle('has-err', bad);
    var e = field.querySelector('.o-err'); if (e) e.hidden = !bad;
    var i = field.querySelector('input'); if (i) i.setAttribute('aria-invalid', bad ? 'true' : 'false');
  }
  function check(field) {
    var inp = field.querySelector('input'); if (!inp || field.hidden) return true;
    var v = inp.value.trim(), ok;
    switch (field.dataset.f) {
      case 'role': ok = !isCompany() || v.length > 1; break;
      case 'email': ok = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); break;
      case 'phone': ok = /^0\d{1,2}-?\d{7}$/.test(v.replace(/\s/g, '')); break;
      case 'reg': ok = /^\d{9}$/.test(v); break;
      case 'addr': ok = v.length > 3; break;
      default: ok = v.length > 1;
    }
    setErr(field, !ok);
    return ok;
  }
  function validate() {
    var bad = null;
    form.querySelectorAll('.field').forEach(function (f) { if (!check(f) && !bad) bad = f; });
    if (bad) { bad.querySelector('input').focus(); return false; }
    return true;
  }
  function values() {
    var o = { ctype: form.ctype.value };
    ['entity', 'reg', 'signer', 'role', 'phone', 'email', 'addr'].forEach(function (k) { o[k] = form[k].value.trim(); });
    return o;
  }

  if (form) {
    syncType();
    form.querySelectorAll('.field input').forEach(function (i) {
      i.addEventListener('input', function () { var f = i.closest('.field'); if (f.classList.contains('has-err')) check(f); saved = false; });
    });
    form.querySelectorAll('input[name="ctype"]').forEach(function (r) { r.addEventListener('change', function () { syncType(); saved = false; }); });
  }

  function saveClient(btn) {
    if (!validate()) return Promise.resolve(false);
    if (!M.open) { return Promise.resolve(true); } // תצוגה בלבד
    if (saved) return Promise.resolve(true);
    btn.classList.add('is-busy');
    return post('client', values()).then(function (j) {
      btn.classList.remove('is-busy');
      if (j.ok) {
        saved = true;
        var e = document.getElementById('rvEntity');
        if (e) { e.textContent = j.entity; e.classList.remove('o-empty'); }
        toast('הפרטים נשמרו.');
        return true;
      }
      if (j.errors) {
        Object.keys(j.errors).forEach(function (k) { var f = form.querySelector('[data-f="' + k + '"]'); if (f) setErr(f, true); });
        toast('יש פרטים שצריך לתקן.', true);
      } else {
        toast(j.message || 'לא הצלחנו לשמור. נסו שוב.', true);
      }
      return false;
    }).catch(function () { btn.classList.remove('is-busy'); toast('אין חיבור. נסו שוב.', true); return false; });
  }

  /* ---------- sign ---------- */
  var signBtn = document.querySelector('.o-sign'), why = document.getElementById('signWhy');
  var c1 = document.getElementById('c1'), c2 = document.getElementById('c2');
  function refreshSign() {
    if (!signBtn) return;
    var reason = '';
    if (M.preview) reason = 'תצוגת מנהל. החתימה זמינה רק בקישור של הלקוח.';
    else if (!M.open) reason = 'ההזמנה אינה פתוחה לחתימה.';
    else if (M.missing) reason = 'החתימה תיפתח כשכל התנאים יושלמו.';
    else if (!saved) reason = 'צריך להשלים ולשמור את פרטי הלקוח.';
    else if (!(c1.checked && c2.checked)) reason = 'סמנו את שתי ההסכמות כדי להמשיך.';
    else if (!M.provider) reason = 'החתימה תיפתח בקרוב. ספק החתימה עדיין מתחבר.';
    signBtn.disabled = !!reason;
    if (why) why.textContent = reason;
  }
  if (c1) { c1.addEventListener('change', refreshSign); c2.addEventListener('change', refreshSign); }
  if (signBtn) signBtn.addEventListener('click', function () {
    if (signBtn.disabled || busy) return;
    busy = true; signBtn.classList.add('is-busy');
    post('sign', { c1: c1.checked, c2: c2.checked }).then(function (j) {
      busy = false; signBtn.classList.remove('is-busy');
      if (j.embed_url) {
        var slot = document.getElementById('oDocSlot');
        slot.innerHTML = '';
        var f = document.createElement('iframe');
        f.src = j.embed_url; f.title = 'חתימה על ההזמנה';
        f.style.cssText = 'width:100%;min-height:640px;border:0;display:block';
        slot.appendChild(f);
        return;
      }
      toast(j.message || 'החתימה אינה זמינה כרגע.', true);
    }).catch(function () { busy = false; signBtn.classList.remove('is-busy'); toast('אין חיבור. נסו שוב.', true); });
  });

  /* ---------- navigation ---------- */
  document.querySelectorAll('[data-next]').forEach(function (b) {
    b.addEventListener('click', function () {
      var n = +b.dataset.next;
      if (b.hasAttribute('data-save')) { saveClient(b).then(function (ok) { if (ok) go(n); }); return; }
      go(n);
    });
  });
  document.querySelectorAll('[data-prev]').forEach(function (b) { b.addEventListener('click', function () { go(+b.dataset.prev); }); });
  document.querySelectorAll('[data-go-btn]').forEach(function (b) { b.addEventListener('click', function () { go(+b.dataset.goBtn); }); });
  steps.forEach(function (s) {
    s.querySelector('button').addEventListener('click', function () {
      var n = +s.dataset.go;
      if (n > 2 && cur === 2) {
        var btn = document.querySelector('[data-save]');
        saveClient(btn).then(function (ok) { if (ok) go(n); });
        return;
      }
      go(n);
    });
  });

  /* ---------- summary notes ---------- */
  var more = document.querySelector('.o-sum-more'), notes = document.getElementById('oNotes');
  if (more && notes) {
    more.addEventListener('click', function () { var o = notes.classList.toggle('open'); more.setAttribute('aria-expanded', o ? 'true' : 'false'); });
    if (window.innerWidth > 960) { notes.classList.add('open'); more.setAttribute('aria-expanded', 'true'); }
  }

  /* ---------- fix request ---------- */
  var dlg = document.getElementById('oDlg');
  if (dlg) {
    document.querySelectorAll('[data-fix]').forEach(function (b) { b.addEventListener('click', function () { if (dlg.showModal) dlg.showModal(); document.getElementById('dlgT').focus(); }); });
    dlg.querySelector('[data-close]').addEventListener('click', function () { dlg.close(); });
    var send = document.getElementById('oFixSend');
    send.addEventListener('click', function () {
      var t = document.getElementById('dlgT').value.trim();
      if (t.length < 3) { toast('כתבו מה לתקן.', true); return; }
      send.classList.add('is-busy');
      post('fix', { text: t }).then(function (j) {
        send.classList.remove('is-busy');
        if (j.ok) { dlg.close(); document.getElementById('dlgT').value = ''; toast('הבקשה נשלחה. נחזור אליכם עם גרסה מעודכנת.'); }
        else toast(j.error || j.message || 'השליחה לא הצליחה.', true);
      }).catch(function () { send.classList.remove('is-busy'); toast('אין חיבור. נסו שוב.', true); });
    });
  }

  go(1, false);
  refreshSign();
})();
