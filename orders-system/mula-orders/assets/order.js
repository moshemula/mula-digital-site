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
  var signTxt = document.getElementById('signTxt');
  var c1 = document.getElementById('c1'), c2 = document.getElementById('c2');
  var signer = document.getElementById('oSigner'), idle = document.getElementById('oSlotIdle');
  var slot = document.getElementById('oDocSlot');
  var codeIn = document.getElementById('sgCode'), nameIn = document.getElementById('sgName');
  var resend = document.getElementById('sgResend');
  var mode = 'start', ink = 0, resendT = null;

  function fieldErr(f, msg) {
    var map = { code: 'sgCodeErr', sig: 'sgSigErr', name: 'sgNameErr' };
    var el = document.getElementById(map[f]); if (!el) return;
    el.textContent = msg || ''; el.hidden = !msg;
    var row = el.closest('.o-sg-row'); if (row) row.classList.toggle('has-err', !!msg);
  }

  function refreshSign() {
    if (!signBtn) return;
    var reason = '';
    if (M.preview) reason = 'תצוגת מנהל. החתימה זמינה רק בקישור של הלקוח.';
    else if (!M.open) reason = 'ההזמנה אינה פתוחה לחתימה.';
    else if (M.missing) reason = 'החתימה תיפתח כשכל התנאים יושלמו.';
    else if (!saved) reason = 'צריך להשלים ולשמור את פרטי הלקוח.';
    else if (!(c1.checked && c2.checked)) reason = 'סמנו את שתי ההסכמות כדי להמשיך.';
    signBtn.disabled = !!reason;
    if (why) why.textContent = reason;
    if (signTxt) signTxt.textContent = mode === 'confirm' ? 'חתימה על ההזמנה' : 'שליחת קוד לחתימה';
  }
  if (c1) { c1.addEventListener('change', refreshSign); c2.addEventListener('change', refreshSign); }

  function countdown(sec) {
    if (!resend) return;
    clearInterval(resendT);
    resend.disabled = true;
    var left = sec;
    resend.textContent = 'שליחה מחדש בעוד ' + left;
    resendT = setInterval(function () {
      left--;
      if (left <= 0) { clearInterval(resendT); resend.disabled = false; resend.textContent = 'שליחה מחדש'; return; }
      resend.textContent = 'שליחה מחדש בעוד ' + left;
    }, 1000);
  }

  function startSign(isResend) {
    busy = true; signBtn.classList.add('is-busy');
    return post('sign/start', { c1: c1.checked, c2: c2.checked }).then(function (j) {
      busy = false; signBtn.classList.remove('is-busy');
      if (j.ok) {
        document.getElementById('sgTo').textContent = j.to;
        if (!isResend) {
          idle.hidden = true; signer.hidden = false; slot.classList.add('is-live');
          if (nameIn && !nameIn.placeholder && form) nameIn.placeholder = form.signer.value;
          pad.fit();
        }
        mode = 'confirm'; refreshSign(); countdown(j.gap || 45);
        fieldErr('code', '');
        codeIn.value = ''; codeIn.focus();
        toast('שלחנו קוד אל ' + j.to);
        return;
      }
      if (j.data && j.data.wait) countdown(j.data.wait);
      toast(j.message || 'לא הצלחנו לשלוח קוד. נסו שוב.', true);
    }).catch(function () { busy = false; signBtn.classList.remove('is-busy'); toast('אין חיבור. נסו שוב.', true); });
  }

  function confirmSign() {
    var code = (codeIn.value || '').replace(/\D/g, '');
    var name = (nameIn.value || '').trim();
    var bad = false;
    fieldErr('code', code.length === 6 ? '' : 'הקוד כולל 6 ספרות.'); if (code.length !== 6) bad = bad || 'code';
    fieldErr('sig', ink >= 60 ? '' : 'חסרה חתימה. חתמו במסגרת.'); if (ink < 60) bad = bad || 'sig';
    fieldErr('name', name.length > 1 ? '' : 'הקלידו את השם המלא.'); if (name.length < 2) bad = bad || 'name';
    if (bad) { var f = { code: codeIn, sig: document.getElementById('sgPad'), name: nameIn }[bad]; if (f && f.focus) f.focus(); return; }
    busy = true; signBtn.classList.add('is-busy');
    post('sign/confirm', { c1: c1.checked, c2: c2.checked, code: code, name: name, sig: pad.data(), ink: Math.round(ink) }).then(function (j) {
      busy = false; signBtn.classList.remove('is-busy');
      if (j.ok) { done(j); return; }
      if (j.field) {
        fieldErr(j.field, j.error);
        if (j.expired) { mode = 'confirm'; if (resend) { clearInterval(resendT); resend.disabled = false; resend.textContent = 'שליחת קוד חדש'; } }
        return;
      }
      toast(j.error || j.message || 'החתימה לא הושלמה. נסו שוב.', true);
    }).catch(function () { busy = false; signBtn.classList.remove('is-busy'); toast('אין חיבור. נסו שוב.', true); });
  }

  function done(j) {
    signer.hidden = true;
    var d = document.getElementById('oDone'); d.hidden = false;
    slot.classList.add('is-done');
    var cp = document.getElementById('oCopy'); if (cp && j.copy) cp.href = j.copy; else if (cp) cp.hidden = true;
    document.querySelectorAll('.o-consent,.o-nav-sign,#signWhy,[data-fix]').forEach(function (el) { el.hidden = true; });
    var st = document.querySelector('.o-status');
    if (st) st.innerHTML = '<i style="background:#19B36B;box-shadow:0 0 0 4px rgba(25,179,107,.18)"></i>נחתמה';
    M.open = false;
    d.setAttribute('tabindex', '-1'); d.focus();
  }

  if (signBtn) signBtn.addEventListener('click', function () {
    if (signBtn.disabled || busy) return;
    if (mode === 'confirm') confirmSign(); else startSign(false);
  });
  if (resend) resend.addEventListener('click', function () { if (!busy) startSign(true); });
  if (codeIn) codeIn.addEventListener('input', function () { codeIn.value = codeIn.value.replace(/\D/g, '').slice(0, 6); fieldErr('code', ''); });
  if (nameIn) nameIn.addEventListener('input', function () { fieldErr('name', ''); });

  /* ---------- signature pad ---------- */
  var pad = (function () {
    var cv = document.getElementById('sgPad');
    if (!cv) return { fit: function () {}, data: function () { return ''; } };
    var ctx = cv.getContext('2d'), wrap = document.getElementById('sgPadWrap'), hint = document.getElementById('sgHint');
    var drawing = false, last = null, strokes = [];
    function fit() {
      var r = wrap.getBoundingClientRect(), dpr = Math.max(1, Math.min(window.devicePixelRatio || 1, 2));
      if (!r.width) return;
      cv.width = Math.round(r.width * dpr); cv.height = Math.round(r.height * dpr);
      cv.style.width = r.width + 'px'; cv.style.height = r.height + 'px';
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0B0B0F';
      redraw();
    }
    function line(a, b) {
      var w = Math.max(1.4, Math.min(3.2, 3.4 - Math.hypot(b.x - a.x, b.y - a.y) * 0.08));
      ctx.lineWidth = w; ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
    }
    function redraw() {
      ctx.clearRect(0, 0, cv.width, cv.height);
      strokes.forEach(function (s) { for (var i = 1; i < s.length; i++) line(s[i - 1], s[i]); if (s.length === 1) line(s[0], { x: s[0].x + .5, y: s[0].y + .5 }); });
    }
    function pt(e) { var r = cv.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; }
    cv.addEventListener('pointerdown', function (e) {
      e.preventDefault(); drawing = true; last = pt(e); strokes.push([last]);
      try { cv.setPointerCapture(e.pointerId); } catch (x) {}
      hint.hidden = true; fieldErr('sig', '');
    });
    cv.addEventListener('pointermove', function (e) {
      if (!drawing) return;
      var p = pt(e); ink += Math.hypot(p.x - last.x, p.y - last.y);
      line(last, p); strokes[strokes.length - 1].push(p); last = p;
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (t) { cv.addEventListener(t, function () { drawing = false; }); });
    document.getElementById('sgClear').addEventListener('click', function () { strokes = []; ink = 0; redraw(); hint.hidden = false; });
    window.addEventListener('resize', function () { if (!signer.hidden) fit(); });
    return { fit: fit, data: function () { return cv.toDataURL('image/png'); } };
  })();

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
