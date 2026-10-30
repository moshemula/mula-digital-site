(function () {
  'use strict';
  var B = window.MOSB || {};
  var DAYS = ['ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת'];
  var MONTHS = ['ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני', 'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר'];
  var slots = {}, day = null, start = null, startLabel = '';

  // ביטול פגישה בעמוד הניהול
  var cancel = document.getElementById('bkCancel');
  if (cancel) {
    cancel.addEventListener('click', function () {
      cancel.disabled = true; cancel.textContent = 'מבטל...';
      fetch(B.api + 'cancel/' + cancel.dataset.token, { method: 'POST' }).then(function (r) { return r.json(); }).then(function (j) {
        if (j.ok) { location.reload(); } else { cancel.disabled = false; cancel.textContent = 'ביטול הפגישה'; alert(j.message || 'לא הצלחנו לבטל.'); }
      });
    });
  }

  var root = document.getElementById('bk');
  if (!root) return;
  var steps = root.querySelectorAll('.bk-steps span');

  function show(name) {
    root.querySelectorAll('.bk-pane').forEach(function (p) { p.hidden = p.dataset.pane !== name; });
    var idx = { day: 0, time: 1, form: 2, done: 3 }[name];
    steps.forEach(function (s, i) { s.classList.toggle('on', i <= idx); });
    var h = root.querySelector('[data-pane="' + name + '"] h2');
    if (h) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); }
    var top = root.getBoundingClientRect().top + window.pageYOffset - 110;
    if (window.pageYOffset > top) window.scrollTo({ top: top, behavior: 'smooth' });
  }
  function dateLabel(iso) {
    var p = iso.split('-').map(Number), d = new Date(Date.UTC(p[0], p[1] - 1, p[2], 12));
    return { wd: DAYS[d.getUTCDay()], d: p[2], m: MONTHS[p[1] - 1] };
  }

  fetch(B.api + B.slug + '/slots').then(function (r) { return r.json(); }).then(function (j) {
    slots = j.slots || {};
    var wrap = document.getElementById('bkDays');
    var keys = Object.keys(slots).sort();
    wrap.innerHTML = '';
    if (!keys.length) {
      wrap.innerHTML = '<p class="bk-empty">אין כרגע מועדים פנויים. אפשר לשלוח וואטסאפ ל־<a href="https://wa.me/972508249824">050-824-9824</a>.</p>';
      return;
    }
    keys.forEach(function (k, i) {
      var l = dateLabel(k), b = document.createElement('button');
      b.type = 'button'; b.className = 'bk-day';
      b.style.animationDelay = (i * 25) + 'ms';
      b.innerHTML = '<span class="wd">' + (i === 0 && isToday(k) ? 'היום' : 'יום ' + l.wd) + '</span><span class="dn">' + l.d + '</span><span class="mn">' + l.m + '</span><span class="ct">' + slots[k].length + ' מועדים</span>';
      b.addEventListener('click', function () { pickDay(k); });
      wrap.appendChild(b);
    });
  }).catch(function () {
    document.getElementById('bkDays').innerHTML = '<p class="bk-empty">לא הצלחנו לטעון את היומן. נסו לרענן.</p>';
  });

  function isToday(k) {
    var t = new Date(); var s = t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0');
    return s === k;
  }

  function pickDay(k) {
    day = k;
    var l = dateLabel(k);
    document.getElementById('bkDayTitle').textContent = 'יום ' + l.wd + ', ' + l.d + ' ב' + l.m;
    var t = document.getElementById('bkTimes'); t.innerHTML = '';
    slots[k].forEach(function (s, i) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'bk-time'; b.textContent = s.label;
      b.style.animationDelay = (i * 18) + 'ms';
      b.addEventListener('click', function () { start = s.start; startLabel = 'יום ' + l.wd + ', ' + l.d + ' ב' + l.m + ' · ' + s.label; document.getElementById('bkChosen').textContent = startLabel; show('form'); });
      t.appendChild(b);
    });
    show('time');
  }

  root.querySelectorAll('[data-back]').forEach(function (b) { b.addEventListener('click', function () { show(b.dataset.back); }); });

  var form = document.getElementById('bkForm'), err = document.getElementById('bkErr');
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    err.hidden = true;
    var F = function (n) { return form.elements.namedItem(n); };
    var name = F('name').value.trim(), phone = F('phone').value.replace(/[^\d+]/g, ''), email = F('email').value.trim();
    if (name.length < 2) { return fail('נשמח לשם מלא.', F('name')); }
    if (!/^(\+972|0)5\d{8}$/.test(phone) && !/^(\+972|0)\d{8,9}$/.test(phone)) { return fail('מספר טלפון לא תקין.', F('phone')); }
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) { return fail('כתובת המייל לא תקינה.', F('email')); }
    var btn = form.querySelector('.bk-submit'); btn.disabled = true; btn.textContent = 'קובע...';
    fetch(B.api + B.slug, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ start: start, name: name, phone: phone, email: email, note: F('note').value, website: F('website').value }) })
      .then(function (r) { return r.json().then(function (j) { j._s = r.status; return j; }); })
      .then(function (j) {
        btn.disabled = false; btn.textContent = 'קביעת השיחה';
        if (j.ok) { document.getElementById('bkDoneWhen').textContent = j.when || startLabel; show('done'); return; }
        fail(j.message || 'משהו השתבש. נסו שוב.');
        if (j._s === 409) { setTimeout(function () { location.reload(); }, 2200); }
      }).catch(function () { btn.disabled = false; btn.textContent = 'קביעת השיחה'; fail('אין חיבור. נסו שוב.'); });
  });
  function fail(msg, el) { err.textContent = msg; err.hidden = false; if (el) el.focus(); return false; }
})();
