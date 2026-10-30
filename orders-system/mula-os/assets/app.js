/* Mula OS · single-page app (vanilla, no build step) */
(function () {
  'use strict';
  var M = window.MOS || {};
  var root = document.getElementById('mos-app');
  var BOOT = null;

  /* ================= utils ================= */
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function money(n) { n = Math.round(+n || 0); return '₪' + n.toLocaleString('en-US'); }
  function api(path, opts) {
    opts = opts || {};
    var o = { method: opts.method || 'GET', headers: { 'X-WP-Nonce': M.nonce }, credentials: 'same-origin' };
    if (opts.body !== undefined) { o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(opts.body); }
    return fetch(M.api + path, o).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok) { var e = new Error(j.message || 'שגיאה'); e.data = j; throw e; }
        return j;
      });
    });
  }
  function utc(s) { return s ? new Date(s.replace(' ', 'T') + 'Z') : null; }
  function ago(s) {
    var d = utc(s); if (!d) return '';
    var x = (Date.now() - d.getTime()) / 1000;
    if (x < 60) return 'עכשיו';
    if (x < 3600) return 'לפני ' + Math.round(x / 60) + ' דק׳';
    if (x < 86400) { var h = Math.round(x / 3600); return h === 1 ? 'לפני שעה' : 'לפני ' + h + ' שעות'; }
    var dd = Math.floor(x / 86400); return dd === 1 ? 'אתמול' : dd < 30 ? 'לפני ' + dd + ' ימים' : d.toLocaleDateString('he-IL');
  }
  function fmtDate(s, withTime) {
    var d = utc(s); if (!d) return '';
    var o = { day: 'numeric', month: 'short', timeZone: 'Asia/Jerusalem' };
    if (withTime) { o.hour = '2-digit'; o.minute = '2-digit'; }
    return d.toLocaleString('he-IL', o);
  }
  function initials(n) { n = (n || '?').trim(); var p = n.split(/\s+/); return (p[0][0] || '') + (p[1] ? p[1][0] : ''); }
  function avatar(name, cls) { var hue = 0; for (var i = 0; i < (name || '').length; i++) hue = (hue + name.charCodeAt(i) * 7) % 360; return '<span class="av ' + (cls || '') + '" style="--h:' + hue + '">' + esc(initials(name)) + '</span>'; }
  function tagChip(t, removable) { return '<span class="chip" style="--c:' + esc(t.color || '#7B2FBE') + '">' + esc(t.name) + (removable ? '<button data-untag="' + esc(t.name) + '" aria-label="הסרה">×</button>' : '') + '</span>'; }
  function waLink(phone) { var d = String(phone || '').replace(/\D/g, ''); if (d[0] === '0') d = '972' + d.slice(1); return 'https://wa.me/' + d; }
  function $(s, el) { return (el || document).querySelector(s); }
  function $$(s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); }
  function on(el, ev, sel, fn) { el.addEventListener(ev, function (e) { var t = e.target.closest(sel); if (t && el.contains(t)) fn(e, t); }); }
  function debounce(fn, ms) { var t; return function () { var a = arguments, s = this; clearTimeout(t); t = setTimeout(function () { fn.apply(s, a); }, ms); }; }

  var ICON = {
    home: '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
    users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    kanban: '<rect x="3" y="3" width="5" height="18" rx="1.5"/><rect x="10" y="3" width="5" height="12" rx="1.5"/><rect x="17" y="3" width="4" height="8" rx="1.5"/>',
    inbox: '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    cal: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    bolt: '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>',
    check: '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
    gear: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    doc: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
    search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    phone: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
    mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
    wa: '<path d="M3 21l1.65-4.9A9 9 0 1 1 8.4 19.6z"/><path d="M9 10c.5 2 2 3.5 4 4l1.2-1.2c.3-.3.7-.3 1 0l1.3 1c.3.3.3.8 0 1.1-.7.8-1.8 1.1-2.9.8-2.6-.8-4.6-2.8-5.4-5.4-.3-1.1 0-2.2.8-2.9.3-.3.8-.3 1.1 0l1 1.3c.3.3.3.7 0 1z"/>',
    form: '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    trash: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
    link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    send: '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
    chevl: '<path d="m15 18-6-6 6-6"/>', chevr: '<path d="m9 18 6-6-6-6"/>',
    clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    tag: '<path d="M20.59 13.41 13.42 20.6a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1.5"/>',
    flow: '<circle cx="6" cy="6" r="3"/><circle cx="18" cy="18" r="3"/><path d="M6 9v3a3 3 0 0 0 3 3h6"/>'
  };
  function ic(n, cls) { return '<svg class="ic ' + (cls || '') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICON[n] || '') + '</svg>'; }
  var CH_ICON = { email: 'mail', whatsapp: 'wa', form: 'form' };
  var CH_NAME = { email: 'מייל', whatsapp: 'וואטסאפ', form: 'טופס' };

  /* ================= toast & modal ================= */
  function toast(msg, err) {
    var t = document.createElement('div');
    t.className = 'toast' + (err ? ' err' : '');
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('in'); });
    setTimeout(function () { t.classList.remove('in'); setTimeout(function () { t.remove(); }, 300); }, 3200);
  }
  function modal(title, body, onSubmit, opts) {
    opts = opts || {};
    var w = document.createElement('div');
    w.className = 'mdl-wrap';
    w.innerHTML = '<div class="mdl ' + (opts.wide ? 'wide' : '') + '" role="dialog" aria-modal="true" aria-label="' + esc(title) + '"><form class="mdl-f" novalidate><header><h3>' + esc(title) + '</h3><button type="button" class="ib" data-close aria-label="סגירה">' + ic('x') + '</button></header><div class="mdl-b">' + body + '</div>' +
      (onSubmit ? '<footer><button type="button" class="btn ghost" data-close>ביטול</button><button class="btn pri" type="submit">' + esc(opts.ok || 'שמירה') + '</button></footer>' : '') + '</form></div>';
    document.body.appendChild(w);
    requestAnimationFrame(function () { w.classList.add('in'); });
    var close = function () { w.classList.remove('in'); setTimeout(function () { w.remove(); }, 200); };
    on(w, 'click', '[data-close]', close);
    w.addEventListener('mousedown', function (e) { if (e.target === w) close(); });
    w.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    var f = $('form', w);
    var first = $('input,select,textarea', f); if (first) setTimeout(function () { first.focus(); }, 60);
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!onSubmit) return;
      var btn = $('button[type=submit]', f); btn.disabled = true;
      Promise.resolve(onSubmit(f, close)).catch(function (er) { toast(er.message, true); }).then(function () { btn.disabled = false; });
    });
    return { el: w, close: close, form: f };
  }
  function val(f, n) { var el = f.elements.namedItem(n); return el ? (el.type === 'checkbox' ? el.checked : el.value.trim()) : ''; }

  /* contact picker (search-as-you-type) */
  function contactPicker(name, selected) {
    return '<div class="picker" data-picker="' + name + '"><input type="hidden" name="' + name + '" value="' + esc(selected ? selected.id : '') + '"><input class="inp picker-q" placeholder="חיפוש איש קשר לפי שם, טלפון או מייל" value="' + esc(selected ? selected.name : '') + '" autocomplete="off"><div class="picker-list" hidden></div></div>';
  }
  function bindPickers(scope) {
    $$('[data-picker]', scope).forEach(function (p) {
      var q = $('.picker-q', p), list = $('.picker-list', p), hid = $('input[type=hidden]', p);
      var run = debounce(function () {
        if (q.value.trim().length < 2) { list.hidden = true; return; }
        api('contacts?q=' + encodeURIComponent(q.value.trim())).then(function (r) {
          list.innerHTML = r.items.slice(0, 8).map(function (c) { return '<button type="button" data-id="' + c.id + '" data-name="' + esc(c.name) + '">' + avatar(c.name, 'sm') + '<span><b>' + esc(c.name) + '</b><small>' + esc(c.phone || c.email) + '</small></span></button>'; }).join('') || '<p class="muted pad">לא נמצא</p>';
          list.hidden = false;
        });
      }, 220);
      q.addEventListener('input', function () { hid.value = ''; run(); });
      on(list, 'click', 'button', function (e, b) { hid.value = b.dataset.id; q.value = b.dataset.name; list.hidden = true; });
    });
  }

  /* ================= shell ================= */
  var NAV = [
    ['dashboard', 'home', 'לוח בקרה'], ['inbox', 'inbox', 'שיחות'], ['contacts', 'users', 'אנשי קשר'], ['pipeline', 'kanban', 'צנרת'],
    ['calendar', 'cal', 'יומן'], ['tasks', 'check', 'משימות'], ['automations', 'bolt', 'אוטומציות'], ['settings', 'gear', 'הגדרות']
  ];
  function shell() {
    root.innerHTML =
      '<aside class="side"><a class="brand" href="#/dashboard"><img src="' + esc(M.logo) + '" alt="Mula Digital"><span>OS</span></a><nav>' +
      NAV.map(function (n) { return '<a href="#/' + n[0] + '" data-nav="' + n[0] + '">' + ic(n[1]) + '<span>' + n[2] + '</span>' + (n[0] === 'inbox' ? '<b class="badge" id="unread" hidden></b>' : '') + '</a>'; }).join('') +
      '<div class="side-sep"></div><a href="' + esc(M.orders) + '">' + ic('doc') + '<span>הזמנות וחתימות</span></a></nav>' +
      '<div class="side-foot"><a href="' + esc(M.wpadmin) + 'plugins.php" class="muted">וורדפרס</a></div></aside>' +
      '<div class="main"><header class="top"><button class="ib menu-btn" id="menuBtn" aria-label="תפריט">☰</button><label class="gsearch">' + ic('search') + '<input id="gq" placeholder="חיפוש איש קשר…" autocomplete="off"><div class="gres" id="gres" hidden></div></label>' +
      '<div class="top-act"><div class="dd"><button class="btn pri" id="newBtn">' + ic('plus') + 'חדש</button><div class="dd-m" id="newMenu" hidden>' +
      '<button data-new="contact">' + ic('users') + 'איש קשר</button><button data-new="deal">' + ic('kanban') + 'עסקה</button><button data-new="task">' + ic('check') + 'משימה</button><a href="' + esc(M.newOrder) + '">' + ic('doc') + 'הזמנה לחתימה</a></div></div>' +
      avatar(BOOT.user.name, 'me') + '</div></header><section class="view" id="view"></section></div>';
    var nb = $('#newBtn'), nm = $('#newMenu');
    nb.addEventListener('click', function (e) { e.stopPropagation(); nm.hidden = !nm.hidden; });
    document.addEventListener('click', function () { nm.hidden = true; $('#gres').hidden = true; });
    on(nm, 'click', '[data-new]', function (e, b) { nm.hidden = true; ({ contact: newContact, deal: function () { newDeal(); }, task: function () { newTask(); } })[b.dataset.new](); });
    $('#menuBtn').addEventListener('click', function () { document.body.classList.toggle('side-open'); });
    var gq = $('#gq'), gres = $('#gres');
    gq.addEventListener('input', debounce(function () {
      if (gq.value.trim().length < 2) { gres.hidden = true; return; }
      api('contacts?q=' + encodeURIComponent(gq.value.trim())).then(function (r) {
        gres.innerHTML = r.items.slice(0, 8).map(function (c) { return '<a href="#/contacts/' + c.id + '">' + avatar(c.name, 'sm') + '<span><b>' + esc(c.name) + '</b><small>' + esc(c.phone || c.email) + '</small></span></a>'; }).join('') || '<p class="muted pad">לא נמצא</p>';
        gres.hidden = false;
      });
    }, 200));
    gres.addEventListener('click', function () { gres.hidden = true; gq.value = ''; });
    setUnread(BOOT.unread);
  }
  function setUnread(n) { var b = $('#unread'); if (!b) return; b.textContent = n; b.hidden = !n; }

  /* ================= router ================= */
  var ROUTES = {
    dashboard: vDashboard, contacts: vContacts, pipeline: vPipeline, inbox: vInbox, calendar: vCalendar, tasks: vTasks, automations: vAutomations, settings: vSettings
  };
  var view;
  function route() {
    var h = (location.hash || '#/dashboard').slice(2).split('/');
    var name = ROUTES[h[0]] ? h[0] : 'dashboard';
    $$('[data-nav]').forEach(function (a) { a.classList.toggle('on', a.dataset.nav === name); });
    document.body.classList.remove('side-open');
    // אלמנט חדש בכל מעבר, כדי שמאזינים של מסך קודם לא יישארו
    var old = $('#view'); view = old.cloneNode(false); old.parentNode.replaceChild(view, old);
    view.className = 'view v-' + name;
    window.scrollTo(0, 0);
    view.innerHTML = '<div class="loading"><span></span><span></span><span></span></div>';
    if (name === 'contacts' && h[1]) return vContact(+h[1]);
    if (name === 'automations' && h[1]) return vAutomation(h[1]);
    ROUTES[name](h);
  }
  window.addEventListener('hashchange', route);

  function head(title, sub, actions) {
    return '<div class="vh"><div><h1>' + esc(title) + '</h1>' + (sub ? '<p>' + sub + '</p>' : '') + '</div><div class="vh-act">' + (actions || '') + '</div></div>';
  }
  function empty(icon, title, text, cta) { return '<div class="empty">' + ic(icon) + '<h3>' + esc(title) + '</h3><p>' + esc(text) + '</p>' + (cta || '') + '</div>'; }
  function fail(e) { view.innerHTML = empty('x', 'משהו לא נטען', e.message || ''); }

  /* ================= dashboard ================= */
  var ACT = {
    contact_created: ['users', 'איש קשר חדש'], message_in: ['inbox', 'הודעה נכנסת'], message_out: ['send', 'הודעה נשלחה'], tag_added: ['tag', 'תגית נוספה'],
    stage_changed: ['kanban', 'עסקה עברה שלב'], deal_created: ['kanban', 'עסקה חדשה'], note: ['doc', 'הערה'], task_created: ['check', 'משימה חדשה'],
    appointment_booked: ['cal', 'נקבעה פגישה'], appointment_cancelled: ['cal', 'פגישה בוטלה'],
    order_opened: ['doc', 'פתח את ההזמנה'], order_client_details_saved: ['doc', 'מילא פרטים בהזמנה'], order_fix_requested: ['doc', 'ביקש תיקון בהזמנה'], order_signed: ['doc', 'חתם על ההזמנה'], order_sent: ['doc', 'נשלחה הזמנה']
  };
  function actLine(a, withContact) {
    var m = ACT[a.type] || ['flow', a.type], d = a.data || {};
    var extra = d.preview || d.tag || d.stage || d.when || d.title || d.order || d.source || '';
    if (a.type === 'message_in' || a.type === 'message_out') extra = (CH_NAME[d.channel] || '') + (d.preview ? ': ' + d.preview : '');
    return '<li class="act">' + '<span class="act-ic t-' + esc(a.type) + '">' + ic(m[0]) + '</span><div><b>' + esc(m[1]) + '</b>' +
      (withContact && a.contact ? ' · <a href="#/contacts/' + a.contact_id + '">' + esc(a.contact) + '</a>' : '') +
      (extra ? '<p>' + esc(extra) + '</p>' : '') + '<time>' + esc(ago(a.created_at)) + '</time></div></li>';
  }
  function vDashboard() {
    api('dashboard').then(function (d) {
      var k = d.kpi, hr = new Date().getHours();
      var greet = hr < 12 ? 'בוקר טוב' : hr < 17 ? 'צהריים טובים' : 'ערב טוב';
      var maxV = Math.max.apply(null, d.funnel.map(function (f) { return +f.n; }).concat([1]));
      view.innerHTML =
        '<div class="hero"><div><p class="eyebrow">' + esc(new Date().toLocaleDateString('he-IL', { weekday: 'long', day: 'numeric', month: 'long' })) + '</p><h1>' + greet + ', ' + esc(BOOT.user.name.split(' ')[0]) + '.</h1><p>זה מה שקורה בעסק עכשיו.</p></div>' +
        '<div class="hero-act"><a class="btn glass" href="' + esc(BOOT.book_url) + 'avchun" target="_blank" rel="noopener">' + ic('cal') + 'עמוד קביעת פגישה</a><button class="btn pri" data-new-task>' + ic('plus') + 'משימה</button></div></div>' +
        '<div class="kpis">' +
        kpi('בצנרת עכשיו', money(k.pipeline), k.pipeline_n + ' עסקאות פתוחות' + (k.pipeline_m ? ' · ' + money(k.pipeline_m) + ' לחודש' : ''), 'grad') +
        kpi('נסגר החודש', money(k.won), k.won_n + ' עסקאות' + (k.won_m ? ' · ' + money(k.won_m) + ' לחודש' : '')) +
        kpi('לידים חדשים החודש', k.leads, 'אנשי קשר שנוספו') +
        kpi('אחוז סגירה', k.conv == null ? '—' : k.conv + '%', k.conv == null ? 'יחושב אחרי עסקאות שנסגרו' : 'מתוך עסקאות שהוכרעו') + '</div>' +
        '<div class="dgrid">' +
        '<section class="card"><div class="card-h"><h2>משימות פתוחות</h2><a href="#/tasks">כל המשימות</a></div>' + (d.tasks.length ? '<ul class="tasks">' + d.tasks.map(taskRow).join('') + '</ul>' : '<p class="muted pad">אין משימות פתוחות. יופי.</p>') + '</section>' +
        '<section class="card"><div class="card-h"><h2>פגישות קרובות</h2><a href="#/calendar">ליומן</a></div>' + (d.appointments.length ? '<ul class="appts">' + d.appointments.map(function (a) { return '<li><span class="ap-d">' + ic('clock') + '</span><div><b>' + esc(a.when) + '</b><p><a href="#/contacts/' + a.contact_id + '">' + esc(a.contact) + '</a> · ' + esc(a.cal) + '</p></div></li>'; }).join('') + '</ul>' : '<p class="muted pad">אין פגישות קרובות. שלחו ללידים את <a href="' + esc(BOOT.book_url) + 'avchun" target="_blank">עמוד קביעת הפגישה</a>.</p>') + '</section>' +
        '<section class="card"><div class="card-h"><h2>משפך מכירות</h2><a href="#/pipeline">לצנרת</a></div><div class="funnel">' + d.funnel.map(function (f) { return '<div class="fn"><span class="fn-l">' + esc(f.name) + '</span><span class="fn-bar"><i style="width:' + Math.max(4, (f.n / maxV) * 100) + '%;background:' + esc(f.color) + '"></i></span><span class="fn-n">' + f.n + '</span><span class="fn-v">' + money(f.v) + '</span></div>'; }).join('') + '</div></section>' +
        '<section class="card"><div class="card-h"><h2>פעילות אחרונה</h2></div>' + (d.activities.length ? '<ul class="acts">' + d.activities.map(function (a) { return actLine(a, true); }).join('') + '</ul>' : '<p class="muted pad">עוד אין פעילות.</p>') + '</section>' +
        '</div>';
      bindTasks(view, vDashboard);
      on(view, 'click', '[data-new-task]', function () { newTask(); });
    }).catch(fail);
  }
  function kpi(l, v, f, mod) { return '<div class="kpi ' + (mod || '') + '"><span class="kpi-l">' + esc(l) + '</span><span class="kpi-v"><bdi>' + esc(v) + '</bdi></span><span class="kpi-f">' + esc(f) + '</span></div>'; }

  /* ================= tasks ================= */
  function taskRow(t) {
    var due = t.due_at ? utc(t.due_at) : null, late = due && due < new Date() && !+t.done;
    return '<li class="task ' + (+t.done ? 'done' : '') + '"><label class="cb"><input type="checkbox" data-task="' + t.id + '"' + (+t.done ? ' checked' : '') + '><span></span></label><div><b>' + esc(t.title) + '</b><p>' +
      (t.contact ? '<a href="#/contacts/' + t.contact_id + '">' + esc(t.contact) + '</a>' : '') + (due ? '<span class="due ' + (late ? 'late' : '') + '">' + ic('clock') + esc(fmtDate(t.due_at, true)) + '</span>' : '') + '</p></div></li>';
  }
  function bindTasks(scope, refresh) {
    on(scope, 'change', '[data-task]', function (e, cb) {
      cb.closest('.task').classList.toggle('done', cb.checked);
      api('tasks/' + cb.dataset.task, { method: 'PATCH', body: { done: cb.checked } }).then(function () { if (cb.checked) toast('משימה הושלמה'); if (refresh) setTimeout(refresh, 600); });
    });
  }
  function vTasks() {
    api('tasks').then(function (rows) {
      var now = new Date(), end = new Date(); end.setHours(23, 59, 59);
      var g = { late: [], today: [], later: [], none: [] };
      rows.forEach(function (t) { var d = utc(t.due_at); if (!d) g.none.push(t); else if (d < now) g.late.push(t); else if (d <= end) g.today.push(t); else g.later.push(t); });
      var sec = function (k, title) { return g[k].length ? '<section class="card"><div class="card-h"><h2>' + title + ' <small>' + g[k].length + '</small></h2></div><ul class="tasks">' + g[k].map(taskRow).join('') + '</ul></section>' : ''; };
      view.innerHTML = head('משימות', 'מה צריך לעשות, לפי דחיפות.', '<button class="btn pri" data-add>' + ic('plus') + 'משימה חדשה</button>') +
        (rows.length ? '<div class="stack">' + sec('late', 'באיחור') + sec('today', 'היום') + sec('later', 'בהמשך') + sec('none', 'בלי תאריך') + '</div>' : empty('check', 'אין משימות פתוחות', 'משימות נוצרות ידנית או אוטומטית מאוטומציות.', '<button class="btn pri" data-add>משימה חדשה</button>'));
      bindTasks(view);
      on(view, 'click', '[data-add]', function () { newTask(null, vTasks); });
    }).catch(fail);
  }
  function newTask(contact, after) {
    var dflt = new Date(Date.now() + 864e5); dflt.setHours(10, 0, 0, 0);
    var local = new Date(dflt.getTime() - dflt.getTimezoneOffset() * 6e4).toISOString().slice(0, 16);
    var m = modal('משימה חדשה', '<label class="fl"><span>מה צריך לעשות?</span><input class="inp" name="title" required placeholder="למשל: לחזור למשה לגבי ההצעה"></label>' +
      '<label class="fl"><span>עד מתי</span><input class="inp" type="datetime-local" name="due_at" value="' + local + '"></label>' +
      '<label class="fl"><span>איש קשר</span>' + contactPicker('contact_id', contact) + '</label>', function (f, close) {
        if (!val(f, 'title')) throw new Error('כתבו מה המשימה');
        return api('tasks', { method: 'POST', body: { title: val(f, 'title'), due_at: val(f, 'due_at').replace('T', ' '), contact_id: +val(f, 'contact_id') || null } }).then(function () { close(); toast('המשימה נוספה'); (after || route)(); });
      });
    bindPickers(m.el);
  }

  /* ================= contacts ================= */
  var cState = { q: '', tag: '', type: '', page: 1 };
  function vContacts() {
    view.innerHTML = head('אנשי קשר', 'לידים, לקוחות ושותפים. הכל במקום אחד.', '<button class="btn pri" data-add>' + ic('plus') + 'איש קשר</button>') +
      '<div class="filters"><label class="search">' + ic('search') + '<input id="cq" placeholder="חיפוש לפי שם, חברה, טלפון או מייל" value="' + esc(cState.q) + '"></label>' +
      '<select id="ctype" class="inp sel"><option value="">כל הסוגים</option><option value="lead">לידים</option><option value="client">לקוחות</option><option value="partner">שותפים</option></select>' +
      '<select id="ctag" class="inp sel"><option value="">כל התגיות</option>' + BOOT.tags.map(function (t) { return '<option value="' + t.id + '">' + esc(t.name) + '</option>'; }).join('') + '</select></div>' +
      '<div class="card flush" id="clist"></div>';
    $('#ctype').value = cState.type; $('#ctag').value = cState.tag;
    var load = function () {
      var q = 'contacts?q=' + encodeURIComponent(cState.q) + '&tag=' + cState.tag + '&type=' + cState.type + '&page=' + cState.page;
      api(q).then(function (r) {
        var el = $('#clist');
        if (!r.items.length) { el.innerHTML = empty('users', cState.q || cState.tag ? 'לא נמצאו אנשי קשר' : 'עוד אין אנשי קשר', 'אנשי קשר נוצרים מטפסים באתר, מוואטסאפ, מקביעת פגישה או ידנית.', '<button class="btn pri" data-add>איש קשר ראשון</button>'); return; }
        el.innerHTML = '<table class="tbl"><thead><tr><th>שם</th><th>טלפון</th><th>מייל</th><th>תגיות</th><th>מקור</th><th>פעילות אחרונה</th></tr></thead><tbody>' +
          r.items.map(function (c) {
            return '<tr data-href="#/contacts/' + c.id + '"><td><div class="who">' + avatar(c.name) + '<div><b>' + esc(c.name) + '</b>' + (c.company && c.company !== c.name ? '<small>' + esc(c.company) + '</small>' : '') + '</div>' + (c.type === 'client' ? '<span class="pill ok">לקוח</span>' : '') + '</div></td><td dir="ltr" class="num">' + esc(c.phone) + '</td><td class="ell">' + esc(c.email) + '</td><td>' + c.tags.slice(0, 3).map(function (t) { return tagChip(t); }).join('') + '</td><td class="muted">' + esc(c.source) + '</td><td class="muted">' + esc(ago(c.last_activity_at || c.created_at)) + '</td></tr>';
          }).join('') + '</tbody></table><div class="tbl-foot">' + r.total + ' אנשי קשר' + (r.total > 50 ? ' · <button class="lnk" data-more>' + (cState.page * 50 < r.total ? 'עמוד הבא' : 'לעמוד הראשון') + '</button>' : '') + '</div>';
      }).catch(fail);
    };
    load();
    $('#cq').addEventListener('input', debounce(function (e) { cState.q = e.target.value.trim(); cState.page = 1; load(); }, 250));
    $('#ctype').addEventListener('change', function (e) { cState.type = e.target.value; cState.page = 1; load(); });
    $('#ctag').addEventListener('change', function (e) { cState.tag = e.target.value; cState.page = 1; load(); });
    on(view, 'click', 'tr[data-href]', function (e, tr) { location.hash = tr.dataset.href; });
    on(view, 'click', '[data-more]', function () { cState.page = $('[data-more]').textContent === 'עמוד הבא' ? cState.page + 1 : 1; load(); });
    on(view, 'click', '[data-add]', newContact);
  }
  function newContact() {
    modal('איש קשר חדש', '<div class="g2"><label class="fl"><span>שם פרטי</span><input class="inp" name="first_name" autocomplete="off"></label><label class="fl"><span>שם משפחה</span><input class="inp" name="last_name" autocomplete="off"></label></div>' +
      '<label class="fl"><span>עסק / חברה</span><input class="inp" name="company"></label><div class="g2"><label class="fl"><span>טלפון</span><input class="inp" name="phone" dir="ltr" inputmode="tel"></label><label class="fl"><span>מייל</span><input class="inp" name="email" dir="ltr" inputmode="email"></label></div>',
      function (f, close) {
        var body = { first_name: val(f, 'first_name'), last_name: val(f, 'last_name'), company: val(f, 'company'), phone: val(f, 'phone'), email: val(f, 'email') };
        return api('contacts', { method: 'POST', body: body }).then(function (r) { close(); toast('איש הקשר נוסף'); location.hash = '#/contacts/' + r.id; });
      }, { ok: 'יצירה' });
  }

  function vContact(id) {
    api('contacts/' + id).then(function (c) {
      var tabs = '<div class="tabs" role="tablist"><button class="on" data-tab="timeline">ציר זמן</button><button data-tab="convo">שיחה</button><button data-tab="notes">הערות <small>' + c.notes.length + '</small></button></div>';
      view.innerHTML =
        '<a class="back" href="#/contacts">' + ic('chevr') + 'אנשי קשר</a>' +
        '<div class="cgrid">' +
        // profile
        '<aside class="card prof"><div class="prof-h">' + avatar(c.name, 'lg') + '<h1>' + esc(c.name) + '</h1>' + (c.company && c.company !== c.name ? '<p>' + esc(c.company) + '</p>' : '') +
        '<div class="prof-act">' + (c.phone ? '<a class="ib round" href="tel:' + esc(c.phone) + '" title="חיוג">' + ic('phone') + '</a><a class="ib round wa" href="' + waLink(c.phone) + '" target="_blank" rel="noopener" title="וואטסאפ">' + ic('wa') + '</a>' : '') + (c.email ? '<a class="ib round" href="mailto:' + esc(c.email) + '" title="מייל">' + ic('mail') + '</a>' : '') + '<button class="ib round" data-tab-go="convo" title="הודעה מהמערכת">' + ic('send') + '</button></div></div>' +
        '<form class="prof-f" id="pf">' +
        fld('first_name', 'שם פרטי', c.first_name) + fld('last_name', 'שם משפחה', c.last_name) + fld('company', 'עסק', c.company) + fld('phone', 'טלפון', c.phone, 'ltr') + fld('email', 'מייל', c.email, 'ltr') +
        '<label class="fl"><span>סוג</span><select class="inp" name="type"><option value="lead">ליד</option><option value="client">לקוח</option><option value="partner">שותף</option></select></label>' +
        fld('source', 'מקור', c.source) +
        '<label class="tgl"><input type="checkbox" name="dnd"' + (c.dnd ? ' checked' : '') + '><span></span>לא לשלוח הודעות אוטומטיות</label>' +
        '<button class="btn pri sm" hidden id="pfSave">שמירת שינויים</button></form>' +
        '<div class="prof-sec"><h3>תגיות</h3><div class="tags" id="ctags">' + c.tags.map(function (t) { return tagChip(t, true); }).join('') + '</div><form id="tagf" class="tag-add"><input class="inp sm" name="tag" list="taglist" placeholder="+ תגית"><datalist id="taglist">' + BOOT.tags.map(function (t) { return '<option value="' + esc(t.name) + '">'; }).join('') + '</datalist></form></div>' +
        '<div class="prof-meta muted">נוצר ' + esc(fmtDate(c.created_at)) + '</div><button class="lnk danger" data-del>מחיקת איש הקשר</button></aside>' +
        // center
        '<section class="card cmain">' + tabs + '<div id="tabBody"></div></section>' +
        // right
        '<aside class="cside">' +
        '<section class="card"><div class="card-h"><h2>עסקאות</h2><button class="lnk" data-new-deal>+ עסקה</button></div>' + (c.deals.length ? c.deals.map(function (d) { return '<div class="mini-deal"><b>' + esc(d.title) + '</b><span class="pill" style="--c:' + esc(d.color) + '">' + esc(d.stage) + '</span><p>' + money(d.value) + (+d.monthly ? ' + ' + money(d.monthly) + ' לחודש' : '') + '</p></div>'; }).join('') : '<p class="muted">אין עסקאות.</p>') + '</section>' +
        (c.orders && c.orders.length ? '<section class="card"><div class="card-h"><h2>הזמנות</h2></div>' + c.orders.map(function (o) { return '<a class="mini-deal" href="' + esc(o.url) + '"><b>' + esc(o.number) + '</b><span class="pill">' + esc(o.status) + '</span></a>'; }).join('') + '</section>' : '') +
        '<section class="card"><div class="card-h"><h2>משימות</h2><button class="lnk" data-new-task>+ משימה</button></div>' + (c.tasks.length ? '<ul class="tasks">' + c.tasks.map(taskRow).join('') + '</ul>' : '<p class="muted">אין משימות.</p>') + '</section>' +
        '<section class="card"><div class="card-h"><h2>פגישות</h2></div>' + (c.appointments.length ? c.appointments.map(function (a) { return '<div class="mini-deal"><b>' + esc(a.when) + '</b><span class="pill ' + (a.status === 'booked' ? 'ok' : '') + '">' + (a.status === 'booked' ? 'נקבעה' : 'בוטלה') + '</span><p>' + esc(a.cal) + '</p></div>'; }).join('') : '<p class="muted">אין פגישות. <button class="lnk" data-copy-book>העתקת קישור לקביעת פגישה</button></p>') + '</section>' +
        '</aside></div>';

      var pf = $('#pf'); pf.elements.type.value = c.type;
      pf.addEventListener('input', function () { $('#pfSave').hidden = false; });
      pf.addEventListener('change', function () { $('#pfSave').hidden = false; });
      pf.addEventListener('submit', function (e) {
        e.preventDefault();
        api('contacts/' + id, { method: 'PATCH', body: { first_name: val(pf, 'first_name'), last_name: val(pf, 'last_name'), company: val(pf, 'company'), phone: val(pf, 'phone'), email: val(pf, 'email'), type: val(pf, 'type'), source: val(pf, 'source'), dnd: val(pf, 'dnd') } })
          .then(function () { toast('נשמר'); vContact(id); }).catch(function (er) { toast(er.message, true); });
      });
      $('#tagf').addEventListener('submit', function (e) {
        e.preventDefault(); var t = e.target.tag.value.trim(); if (!t) return;
        api('contacts/' + id + '/tags', { method: 'POST', body: { tag: t } }).then(function (tags) { $('#ctags').innerHTML = tags.map(function (x) { return tagChip(x, true); }).join(''); e.target.reset(); refreshBoot(); });
      });
      on($('#ctags'), 'click', '[data-untag]', function (e, b) {
        api('contacts/' + id + '/tags/remove', { method: 'POST', body: { tag: b.dataset.untag } }).then(function (tags) { $('#ctags').innerHTML = tags.map(function (x) { return tagChip(x, true); }).join(''); });
      });
      on(view, 'click', '[data-del]', function () {
        modal('מחיקת איש קשר', '<p>למחוק את <b>' + esc(c.name) + '</b> לצמיתות? ההודעות, ההערות והמשימות שלו יימחקו. עסקאות יישארו בלי איש קשר.</p>', function (f, close) {
          return api('contacts/' + id, { method: 'DELETE' }).then(function () { close(); toast('נמחק'); location.hash = '#/contacts'; });
        }, { ok: 'מחיקה' });
      });
      on(view, 'click', '[data-new-task]', function () { newTask({ id: id, name: c.name }, function () { vContact(id); }); });
      on(view, 'click', '[data-new-deal]', function () { newDeal({ id: id, name: c.name }, function () { vContact(id); }); });
      on(view, 'click', '[data-copy-book]', function () { copy(BOOT.book_url + 'avchun'); });
      bindTasks(view);

      var setTab = function (t) {
        $$('.tabs button', view).forEach(function (b) { b.classList.toggle('on', b.dataset.tab === t); });
        var tb = $('#tabBody');
        if (t === 'timeline') tb.innerHTML = c.activities.length ? '<ul class="acts tl">' + c.activities.map(function (a) { return actLine(a); }).join('') + '</ul>' : '<p class="muted pad">עוד אין פעילות.</p>';
        if (t === 'notes') {
          tb.innerHTML = '<form id="nf" class="note-f"><textarea class="inp" name="body" rows="3" placeholder="מה חשוב לזכור על הלקוח הזה?"></textarea><button class="btn pri sm">הוספת הערה</button></form>' +
            c.notes.map(function (n) { return '<div class="note"><p>' + esc(n.body).replace(/\n/g, '<br>') + '</p><time>' + esc(fmtDate(n.created_at, true)) + '</time></div>'; }).join('');
          $('#nf').addEventListener('submit', function (e) { e.preventDefault(); var b = e.target.body.value.trim(); if (!b) return; api('contacts/' + id + '/notes', { method: 'POST', body: { body: b } }).then(function () { toast('ההערה נשמרה'); vContact(id); }); });
        }
        if (t === 'convo') { tb.innerHTML = '<div class="thread-wrap" id="tw"></div>'; mountThread($('#tw'), id); }
      };
      on(view, 'click', '[data-tab]', function (e, b) { setTab(b.dataset.tab); });
      on(view, 'click', '[data-tab-go]', function (e, b) { setTab(b.dataset.tabGo); });
      setTab('timeline');
    }).catch(fail);
  }
  function fld(n, l, v, dir) { return '<label class="fl"><span>' + l + '</span><input class="inp" name="' + n + '" value="' + esc(v) + '"' + (dir ? ' dir="' + dir + '"' : '') + '></label>'; }
  function copy(t) { (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { toast('הקישור הועתק'); }).catch(function () { prompt('העתיקו:', t); }); }
  function refreshBoot() { api('boot').then(function (b) { BOOT = b; setUnread(b.unread); }); }

  /* ================= conversation thread (shared) ================= */
  function mountThread(el, cid, onSent) {
    api('conversations/' + cid).then(function (d) {
      var c = d.contact;
      var hasWA = !!c.phone, hasMail = !!c.email;
      var def = hasWA && BOOT.wa_ready ? 'whatsapp' : (hasMail ? 'email' : 'whatsapp');
      el.innerHTML = '<div class="thread" id="th">' + (d.messages.length ? d.messages.map(msgBubble).join('') : '<p class="muted pad center">עוד אין הודעות עם ' + esc(c.name) + '.</p>') + '</div>' +
        '<form class="composer" id="cmp"><div class="cmp-ch">' +
        '<button type="button" data-ch="whatsapp" class="' + (def === 'whatsapp' ? 'on' : '') + '"' + (hasWA ? '' : ' disabled title="אין טלפון"') + '>' + ic('wa') + 'וואטסאפ</button>' +
        '<button type="button" data-ch="email" class="' + (def === 'email' ? 'on' : '') + '"' + (hasMail ? '' : ' disabled title="אין מייל"') + '>' + ic('mail') + 'מייל</button>' +
        '<span class="cmp-note" id="cmpNote"></span></div>' +
        '<input class="inp sm" name="subject" placeholder="נושא המייל" hidden>' +
        '<div class="cmp-row"><textarea class="inp" name="body" rows="2" placeholder="כתבו הודעה… אפשר {{first_name}}"></textarea><button class="btn pri" aria-label="שליחה">' + ic('send') + '</button></div>' +
        '<input class="inp sm" name="template" placeholder="שם תבנית מאושרת בוואטסאפ (מחוץ לחלון 24 שעות)" hidden></form>';
      var th = $('#th', el); th.scrollTop = th.scrollHeight;
      var f = $('#cmp', el), ch = def;
      var sync = function () {
        $$('[data-ch]', f).forEach(function (b) { b.classList.toggle('on', b.dataset.ch === ch); });
        f.subject.hidden = ch !== 'email';
        f.template.hidden = !(ch === 'whatsapp' && !d.wa_window);
        var note = '';
        if (ch === 'whatsapp' && !BOOT.wa_ready) note = 'וואטסאפ לא מחובר עדיין. <a href="#/settings">לחיבור</a>';
        else if (ch === 'whatsapp' && !d.wa_window) note = 'מחוץ לחלון 24 שעות: וואטסאפ מאפשר רק תבנית מאושרת.';
        $('#cmpNote', el).innerHTML = note;
      };
      sync();
      on(f, 'click', '[data-ch]', function (e, b) { ch = b.dataset.ch; sync(); });
      f.body.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) f.requestSubmit(); });
      f.addEventListener('submit', function (e) {
        e.preventDefault();
        var body = f.body.value.trim();
        if (!body && !f.template.value) return;
        var btn = $('button.pri', f); btn.disabled = true;
        api('send', { method: 'POST', body: { contact_id: cid, channel: ch, body: body, subject: f.subject.value, template: f.template.value } })
          .then(function () { toast('נשלח'); mountThread(el, cid, onSent); if (onSent) onSent(); })
          .catch(function (er) { toast(er.message, true); btn.disabled = false; });
      });
      refreshBoot();
    });
  }
  function msgBubble(m) {
    var out = m.direction === 'out';
    return '<div class="msg ' + (out ? 'out' : 'in') + (m.status === 'failed' ? ' failed' : '') + '"><div class="bub">' + (m.subject ? '<b class="subj">' + esc(m.subject) + '</b>' : '') + esc(m.body).replace(/\n/g, '<br>') + '</div><div class="meta">' + ic(CH_ICON[m.channel] || 'inbox') + esc(fmtDate(m.created_at, true)) + (out ? ' · ' + ({ sent: 'נשלח', delivered: 'נמסר', read: 'נקרא', failed: 'נכשל' }[m.status] || m.status) : '') + '</div></div>';
  }

  /* ================= inbox ================= */
  var iState = { filter: 'all', channel: '' };
  function vInbox(h) {
    var active = +h[1] || 0;
    view.innerHTML = '<div class="inbox"><aside class="ib-list"><div class="ib-h"><h1>שיחות</h1><div class="seg"><button data-f="all">הכל</button><button data-f="unread">לא נקראו</button></div>' +
      '<div class="seg sm"><button data-c="">כל הערוצים</button><button data-c="whatsapp">' + ic('wa') + '</button><button data-c="email">' + ic('mail') + '</button><button data-c="form">' + ic('form') + '</button></div></div><div id="convs"></div></aside>' +
      '<section class="ib-thread" id="ibt">' + empty('inbox', 'בחרו שיחה', 'כל ההודעות מוואטסאפ, מייל וטפסים באתר מגיעות לכאן.') + '</section></div>';
    var load = function () {
      $$('[data-f]', view).forEach(function (b) { b.classList.toggle('on', b.dataset.f === iState.filter); });
      $$('[data-c]', view).forEach(function (b) { b.classList.toggle('on', b.dataset.c === iState.channel); });
      api('conversations?filter=' + iState.filter + '&channel=' + iState.channel).then(function (rows) {
        $('#convs').innerHTML = rows.length ? rows.map(function (v) {
          return '<a class="conv ' + (+v.unread ? 'unread' : '') + (+v.contact_id === active ? ' on' : '') + '" href="#/inbox/' + v.contact_id + '">' + avatar(v.name) + '<div><b>' + esc(v.name) + '</b><p>' + ic(CH_ICON[v.last_channel] || 'inbox', 'xs') + esc(v.last_preview) + '</p></div><span class="conv-m"><time>' + esc(ago(v.last_message_at)) + '</time>' + (+v.unread ? '<i>' + v.unread + '</i>' : '') + '</span></a>';
        }).join('') : '<p class="muted pad">אין שיחות.</p>';
      });
    };
    on(view, 'click', '[data-f]', function (e, b) { iState.filter = b.dataset.f; load(); });
    on(view, 'click', '[data-c]', function (e, b) { iState.channel = b.dataset.c; load(); });
    load();
    if (active) {
      $('#ibt').innerHTML = '<div class="ibt-h" id="ibth"></div><div class="thread-wrap full" id="tw"></div>';
      api('contacts/' + active).then(function (c) {
        $('#ibth').innerHTML = avatar(c.name) + '<div><b>' + esc(c.name) + '</b><p class="muted">' + esc([c.phone, c.email].filter(Boolean).join(' · ')) + '</p></div><a class="btn ghost sm" href="#/contacts/' + c.id + '">לכרטיס</a>';
      });
      mountThread($('#tw'), active, load);
      view.classList.add('has-thread');
    }
  }

  /* ================= pipeline ================= */
  var pState = { pipeline: null };
  function vPipeline() {
    var pipes = BOOT.pipelines; if (!pipes.length) { view.innerHTML = empty('kanban', 'אין צנרת', ''); return; }
    var p = pipes.filter(function (x) { return +x.id === +pState.pipeline; })[0] || pipes[0]; pState.pipeline = p.id;
    api('deals?pipeline=' + p.id).then(function (deals) {
      var openSum = deals.filter(function (d) { return d.status === 'open'; }).reduce(function (s, d) { return s + d.value; }, 0);
      view.innerHTML = head('צנרת מכירות', 'גוררים עסקה בין שלבים. ' + money(openSum) + ' פתוחים כרגע.', (pipes.length > 1 ? '<select class="inp sel" id="pp">' + pipes.map(function (x) { return '<option value="' + x.id + '">' + esc(x.name) + '</option>'; }).join('') + '</select>' : '') + '<button class="btn pri" data-add>' + ic('plus') + 'עסקה</button>') +
        '<div class="board">' + p.stages.map(function (s) {
          var items = deals.filter(function (d) { return +d.stage_id === +s.id; });
          var sum = items.reduce(function (a, d) { return a + d.value; }, 0);
          return '<div class="col" data-stage="' + s.id + '" style="--c:' + esc(s.color) + '"><div class="col-h"><b>' + esc(s.name) + '</b><span>' + items.length + '</span><small>' + money(sum) + '</small></div><div class="col-b" data-drop="' + s.id + '">' +
            items.map(dealCard).join('') + '</div><button class="col-add" data-add-stage="' + s.id + '">+ עסקה</button></div>';
        }).join('') + '</div>';
      if ($('#pp')) { $('#pp').value = p.id; $('#pp').addEventListener('change', function (e) { pState.pipeline = e.target.value; vPipeline(); }); }
      on(view, 'click', '[data-add]', function () { newDeal(null, vPipeline, p.stages[0].id); });
      on(view, 'click', '[data-add-stage]', function (e, b) { newDeal(null, vPipeline, +b.dataset.addStage); });
      on(view, 'click', '.deal', function (e, el) { if (!el.classList.contains('dragging')) editDeal(deals.filter(function (d) { return +d.id === +el.dataset.id; })[0], p); });
      dnd(view, p);
    }).catch(fail);
  }
  function dealCard(d) {
    return '<div class="deal" draggable="true" data-id="' + d.id + '"><b>' + esc(d.title) + '</b>' + (d.contact ? '<p>' + avatar(d.contact, 'xs') + esc(d.contact) + '</p>' : '') +
      '<div class="deal-f"><span class="num">' + money(d.value) + '</span>' + (d.monthly ? '<small>+' + money(d.monthly) + '/ח׳</small>' : '') + (d.order_id ? '<span class="pill">' + ic('doc', 'xs') + 'הזמנה</span>' : '') + '</div><time>' + esc(ago(d.updated_at)) + '</time></div>';
  }
  function dnd(scope, p) {
    var drag = null;
    scope.addEventListener('dragstart', function (e) { var d = e.target.closest('.deal'); if (!d) return; drag = d; d.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', d.dataset.id); });
    scope.addEventListener('dragend', function () { if (drag) drag.classList.remove('dragging'); $$('.col-b.over', scope).forEach(function (c) { c.classList.remove('over'); }); setTimeout(function () { drag = null; }, 0); });
    scope.addEventListener('dragover', function (e) {
      var col = e.target.closest('.col-b'); if (!col || !drag) return;
      e.preventDefault(); col.classList.add('over');
      var after = $$('.deal:not(.dragging)', col).filter(function (el) { var r = el.getBoundingClientRect(); return e.clientY < r.top + r.height / 2; })[0];
      if (after) col.insertBefore(drag, after); else col.appendChild(drag);
    });
    scope.addEventListener('dragleave', function (e) { var col = e.target.closest('.col-b'); if (col && !col.contains(e.relatedTarget)) col.classList.remove('over'); });
    scope.addEventListener('drop', function (e) {
      var col = e.target.closest('.col-b'); if (!col || !drag) return;
      e.preventDefault(); col.classList.remove('over');
      var order = $$('.deal', col).map(function (el) { return +el.dataset.id; });
      api('deals/' + drag.dataset.id, { method: 'PATCH', body: { stage_id: +col.dataset.drop, order: order } }).then(function () {
        var st = p.stages.filter(function (s) { return +s.id === +col.dataset.drop; })[0];
        toast('הועבר ל' + (st ? st.name : ''));
        setTimeout(vPipeline, 350);
      }).catch(function (er) { toast(er.message, true); vPipeline(); });
    });
  }
  function stageOptions(sel) {
    return BOOT.pipelines.map(function (p) { return '<optgroup label="' + esc(p.name) + '">' + p.stages.map(function (s) { return '<option value="' + s.id + '"' + (+s.id === +sel ? ' selected' : '') + '>' + esc(s.name) + '</option>'; }).join('') + '</optgroup>'; }).join('');
  }
  function newDeal(contact, after, stage) {
    var m = modal('עסקה חדשה', '<label class="fl"><span>שם העסקה</span><input class="inp" name="title" required placeholder="למשל: אתר + קמפיינים לשיפוצניק"></label>' +
      '<label class="fl"><span>איש קשר</span>' + contactPicker('contact_id', contact) + '</label>' +
      '<div class="g2"><label class="fl"><span>שווי הקמה ₪</span><input class="inp" name="value" type="number" min="0" dir="ltr"></label><label class="fl"><span>שווי חודשי ₪</span><input class="inp" name="monthly" type="number" min="0" dir="ltr"></label></div>' +
      '<label class="fl"><span>שלב</span><select class="inp" name="stage_id">' + stageOptions(stage || (BOOT.pipelines[0] && BOOT.pipelines[0].stages[0].id)) + '</select></label>',
      function (f, close) {
        if (!val(f, 'title')) throw new Error('תנו שם לעסקה');
        return api('deals', { method: 'POST', body: { title: val(f, 'title'), contact_id: +val(f, 'contact_id') || null, value: +val(f, 'value') || 0, monthly: +val(f, 'monthly') || 0, stage_id: +val(f, 'stage_id') } })
          .then(function () { close(); toast('העסקה נוספה'); (after || function () { location.hash = '#/pipeline'; })(); });
      }, { ok: 'יצירה' });
    bindPickers(m.el);
  }
  function editDeal(d, p) {
    var m = modal('עסקה', '<label class="fl"><span>שם העסקה</span><input class="inp" name="title" value="' + esc(d.title) + '"></label>' +
      '<label class="fl"><span>איש קשר</span>' + contactPicker('contact_id', d.contact_id ? { id: d.contact_id, name: d.contact } : null) + '</label>' +
      '<div class="g2"><label class="fl"><span>שווי הקמה ₪</span><input class="inp" name="value" type="number" min="0" dir="ltr" value="' + d.value + '"></label><label class="fl"><span>שווי חודשי ₪</span><input class="inp" name="monthly" type="number" min="0" dir="ltr" value="' + d.monthly + '"></label></div>' +
      '<label class="fl"><span>שלב</span><select class="inp" name="stage_id">' + stageOptions(d.stage_id) + '</select></label>' +
      (d.contact_id ? '<a class="lnk" href="#/contacts/' + d.contact_id + '">לכרטיס איש הקשר ←</a>' : ''),
      function (f, close) {
        return api('deals/' + d.id, { method: 'PATCH', body: { title: val(f, 'title'), contact_id: +val(f, 'contact_id') || 0, value: +val(f, 'value') || 0, monthly: +val(f, 'monthly') || 0, stage_id: +val(f, 'stage_id') } })
          .then(function () { close(); toast('נשמר'); vPipeline(); });
      });
    bindPickers(m.el);
    on(m.el, 'click', 'a.lnk', function () { m.close(); });
  }

  /* ================= calendar ================= */
  var calWeek = 0;
  function vCalendar() {
    var start = new Date(); start.setHours(0, 0, 0, 0); start.setDate(start.getDate() - start.getDay() + calWeek * 7);
    var end = new Date(start); end.setDate(end.getDate() + 7);
    var iso = function (d) { var x = new Date(d.getTime() - d.getTimezoneOffset() * 6e4); return x.toISOString().slice(0, 19).replace('T', ' '); };
    Promise.all([api('appointments?from=' + encodeURIComponent(iso(start)) + '&to=' + encodeURIComponent(iso(end))), api('calendars')]).then(function (r) {
      var aps = r[0], cals = r[1], H0 = 8, H1 = 20, PX = 56;
      var days = []; for (var i = 0; i < 7; i++) { var d = new Date(start); d.setDate(d.getDate() + i); days.push(d); }
      var today = new Date().toDateString();
      var label = days[0].toLocaleDateString('he-IL', { day: 'numeric', month: 'long' }) + ' – ' + days[6].toLocaleDateString('he-IL', { day: 'numeric', month: 'long', year: 'numeric' });
      var cal = cals[0];
      view.innerHTML = head('יומן', esc(label), '<div class="seg"><button data-w="-1">' + ic('chevr') + '</button><button data-w="0">היום</button><button data-w="1">' + ic('chevl') + '</button></div>' +
        (cal ? '<button class="btn ghost" data-copy="' + esc(cal.url) + '">' + ic('link') + 'קישור לקביעה</button><button class="btn pri" data-cal-set>' + ic('gear') + 'זמינות</button>' : '')) +
        '<div class="card flush week" style="--px:' + PX + 'px"><div class="wk-h"><span></span>' + days.map(function (d) { return '<div class="' + (d.toDateString() === today ? 'today' : '') + '"><small>' + d.toLocaleDateString('he-IL', { weekday: 'short' }) + '</small><b>' + d.getDate() + '</b></div>'; }).join('') + '</div>' +
        '<div class="wk-b"><div class="wk-hours">' + range(H0, H1).map(function (h) { return '<span>' + String(h).padStart(2, '0') + ':00</span>'; }).join('') + '</div>' +
        days.map(function (d) {
          var list = aps.filter(function (a) { return new Date(a.start_ts * 1000).toDateString() === d.toDateString(); });
          return '<div class="wk-day ' + (d.toDateString() === today ? 'today' : '') + '">' + list.map(function (a) {
            var s = new Date(a.start_ts * 1000), e = new Date(a.end_ts * 1000);
            var top = ((s.getHours() + s.getMinutes() / 60) - H0) * PX, hgt = Math.max(28, (e - s) / 36e5 * PX - 3);
            return '<button class="ev ' + (a.status !== 'booked' ? 'cx' : '') + '" style="top:' + top + 'px;height:' + hgt + 'px" data-ap="' + a.id + '"><b>' + esc(a.contact) + '</b><small>' + s.toLocaleTimeString('he-IL', { hour: '2-digit', minute: '2-digit' }) + ' · ' + esc(a.cal) + '</small></button>';
          }).join('') + (d.toDateString() === today ? nowLine(H0, PX) : '') + '</div>';
        }).join('') + '</div></div>' +
        (aps.length ? '' : '<p class="muted center pad">אין פגישות בשבוע הזה.</p>');
      on(view, 'click', '[data-w]', function (e, b) { var w = +b.dataset.w; calWeek = w === 0 ? 0 : calWeek + w; vCalendar(); });
      on(view, 'click', '[data-copy]', function (e, b) { copy(b.dataset.copy); });
      on(view, 'click', '[data-cal-set]', function () { calSettings(cal); });
      on(view, 'click', '[data-ap]', function (e, b) {
        var a = aps.filter(function (x) { return +x.id === +b.dataset.ap; })[0];
        modal(a.cal, '<div class="ap-card"><p class="big">' + esc(a.when) + '</p><p>' + avatar(a.contact, 'sm') + ' <a href="#/contacts/' + a.contact_id + '">' + esc(a.contact) + '</a> · <bdi>' + esc(a.phone) + '</bdi></p>' + (a.notes ? '<blockquote>' + esc(a.notes) + '</blockquote>' : '') + '<p class="muted">' + (a.status === 'booked' ? 'נקבעה' : 'בוטלה') + '</p></div>',
          a.status === 'booked' ? function (f, close) { return api('appointments/' + a.id + '/cancel', { method: 'POST' }).then(function () { close(); toast('הפגישה בוטלה'); vCalendar(); }); } : null, { ok: 'ביטול הפגישה' });
      });
      var wb = $('.wk-b', view); if (wb && calWeek === 0) wb.scrollTop = Math.max(0, (new Date().getHours() - H0 - 1) * PX);
    }).catch(fail);
  }
  function range(a, b) { var r = []; for (var i = a; i <= b; i++) r.push(i); return r; }
  function nowLine(H0, PX) { var n = new Date(); var t = ((n.getHours() + n.getMinutes() / 60) - H0) * PX; return t > 0 ? '<i class="now" style="top:' + t + 'px"></i>' : ''; }
  function calSettings(c) {
    var DN = ['ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת'];
    var av = c.availability || {};
    var rows = DN.map(function (n, i) {
      var w = (av[i] && av[i][0]) || null;
      return '<div class="avr"><label class="tgl"><input type="checkbox" name="on' + i + '"' + (w ? ' checked' : '') + '><span></span>' + n + '</label><input class="inp sm" type="time" name="f' + i + '" value="' + (w ? w[0] : '09:00') + '"><span>עד</span><input class="inp sm" type="time" name="t' + i + '" value="' + (w ? w[1] : '17:00') + '"></div>';
    }).join('');
    modal('הגדרות יומן', '<label class="fl"><span>שם הפגישה</span><input class="inp" name="name" value="' + esc(c.name) + '"></label>' +
      '<label class="fl"><span>תיאור בעמוד</span><textarea class="inp" name="description" rows="2">' + esc(c.description) + '</textarea></label>' +
      '<label class="fl"><span>מיקום</span><input class="inp" name="location" value="' + esc(c.location) + '"></label>' +
      '<div class="g4"><label class="fl"><span>משך (דק׳)</span><input class="inp" type="number" name="duration" value="' + c.duration + '"></label><label class="fl"><span>מרווח (דק׳)</span><input class="inp" type="number" name="buffer" value="' + c.buffer + '"></label><label class="fl"><span>התראה מראש (שעות)</span><input class="inp" type="number" name="notice_hours" value="' + c.notice_hours + '"></label><label class="fl"><span>ימים קדימה</span><input class="inp" type="number" name="days_ahead" value="' + c.days_ahead + '"></label></div>' +
      '<h4>שעות פעילות</h4><div class="avail">' + rows + '</div><label class="tgl"><input type="checkbox" name="active"' + (+c.active ? ' checked' : '') + '><span></span>עמוד הקביעה פעיל</label>' +
      '<p class="muted">הקישור: <a href="' + esc(c.url) + '" target="_blank">' + esc(c.url) + '</a></p>',
      function (f, close) {
        var a = {}; for (var i = 0; i < 7; i++) { if (val(f, 'on' + i)) a[i] = [[val(f, 'f' + i), val(f, 't' + i)]]; }
        return api('calendars/' + c.id, { method: 'POST', body: { name: val(f, 'name'), description: val(f, 'description'), location: val(f, 'location'), duration: +val(f, 'duration'), buffer: +val(f, 'buffer'), notice_hours: +val(f, 'notice_hours'), days_ahead: +val(f, 'days_ahead'), availability: a, active: val(f, 'active') } })
          .then(function () { close(); toast('היומן עודכן'); vCalendar(); });
      }, { wide: true });
  }

  /* ================= automations ================= */
  var RECIPES = [
    { name: 'ליד חדש מהאתר: מענה מיידי', trigger_type: 'form_submitted', steps: [
      { type: 'add_tag', tag: 'ליד מהאתר' },
      { type: 'send_email', subject: 'קיבלתי, {{first_name}}', body: 'היי {{first_name}},\n\nקיבלתי את הפרטים שלך.\nאחזור אליך היום.\n\nאם נוח לך, אפשר כבר לקבוע שיחת אבחון קצרה כאן:\n{{booking_link}}' },
      { type: 'create_task', title: 'לחזור לליד חדש מהאתר', due_minutes: 60 },
      { type: 'notify_me', subject: 'ליד חדש מהאתר', body: 'נכנס ליד: {{name}}' }] },
    { name: 'תזכורת לפני שיחת אבחון', trigger_type: 'appointment_booked', steps: [
      { type: 'move_stage', stage_id: 0, _stage_name: 'שיחת אבחון' },
      { type: 'wait_before', minutes: 1440 },
      { type: 'send_email', subject: 'מחר מדברים', body: 'היי {{first_name}},\nרק מזכיר שמחר יש לנו שיחה.\nאם משהו השתנה, הקישור לשינוי נמצא במייל האישור.' },
      { type: 'wait_before', minutes: 60 },
      { type: 'send_whatsapp', body: 'היי {{first_name}}, מדברים בעוד שעה. משה' }] },
    { name: 'הזמנה נפתחה ולא נחתמה: פולואפ', trigger_type: 'order_opened', steps: [
      { type: 'wait', minutes: 2880 },
      { type: 'create_task', title: 'פולואפ: ההזמנה נפתחה ועוד לא נחתמה', due_minutes: 120 }] },
    { name: 'הזמנה נחתמה: קליטת לקוח', trigger_type: 'order_signed', steps: [
      { type: 'add_tag', tag: 'לקוח פעיל' },
      { type: 'send_email', subject: 'ברוך הבא, {{first_name}}', body: 'היי {{first_name}},\nתודה על האמון.\nבימים הקרובים אחזור אליך עם רשימת החומרים שצריך כדי להתחיל.' },
      { type: 'create_task', title: 'שיחת קליטה ללקוח חדש', due_minutes: 1440 }] }
  ];
  function vAutomations() {
    api('automations').then(function (rows) {
      view.innerHTML = head('אוטומציות', 'המערכת עובדת בשבילך: מענה ללידים, תזכורות, פולואפים.', '<button class="btn pri" data-new>' + ic('plus') + 'אוטומציה חדשה</button>') +
        (rows.length ? '<div class="autos">' + rows.map(function (a) {
          return '<a class="card auto" href="#/automations/' + a.id + '"><div class="auto-h"><span class="auto-ic">' + ic('bolt') + '</span><div><b>' + esc(a.name) + '</b><p>כש' + esc(lc(BOOT.triggers[a.trigger_type] || a.trigger_type)) + ' · ' + a.steps.length + ' שלבים</p></div><span class="pill ' + (+a.active ? 'ok' : '') + '">' + (+a.active ? 'פעילה' : 'כבויה') + '</span></div>' +
            '<div class="auto-f"><span>' + a.runs + ' הפעלות</span>' + (a.waiting ? '<span>' + a.waiting + ' ממתינות</span>' : '') + '</div></a>';
        }).join('') + '</div>' : '') +
        '<h2 class="sec-t">מתכונים מוכנים</h2><div class="recipes">' + RECIPES.map(function (r, i) { return '<button class="card recipe" data-recipe="' + i + '"><span class="auto-ic">' + ic('flow') + '</span><b>' + esc(r.name) + '</b><p>' + r.steps.map(function (s) { return BOOT.steps[s.type]; }).join(' ← ') + '</p></button>'; }).join('') + '</div>';
      on(view, 'click', '[data-new]', function () { location.hash = '#/automations/new'; });
      on(view, 'click', '[data-recipe]', function (e, b) { sessionStorage.setItem('mos_recipe', JSON.stringify(RECIPES[+b.dataset.recipe])); location.hash = '#/automations/new'; });
    }).catch(fail);
  }
  function lc(s) { return s ? s.charAt(0) + s.slice(1) : ''; }
  function allStages() { var r = []; BOOT.pipelines.forEach(function (p) { p.stages.forEach(function (s) { r.push(s); }); }); return r; }
  function vAutomation(id) {
    var load = id === 'new' ? Promise.resolve(null) : api('automations').then(function (rows) { return rows.filter(function (a) { return +a.id === +id; })[0]; });
    load.then(function (a) {
      if (!a) {
        var rec = null; try { rec = JSON.parse(sessionStorage.getItem('mos_recipe') || 'null'); sessionStorage.removeItem('mos_recipe'); } catch (e) {}
        a = rec ? JSON.parse(JSON.stringify(rec)) : { name: '', trigger_type: 'form_submitted', trigger_cfg: {}, steps: [], active: 0 };
        a.trigger_cfg = a.trigger_cfg || {};
        a.steps.forEach(function (s) { if (s._stage_name) { var st = allStages().filter(function (x) { return x.name === s._stage_name; })[0]; if (st) s.stage_id = +st.id; delete s._stage_name; } });
      }
      var A = a;
      var render = function () {
        view.innerHTML = '<a class="back" href="#/automations">' + ic('chevr') + 'אוטומציות</a>' +
          '<div class="builder"><div class="b-top card"><input class="inp b-name" id="an" placeholder="שם האוטומציה" value="' + esc(A.name) + '"><label class="tgl"><input type="checkbox" id="aact"' + (+A.active ? ' checked' : '') + '><span></span>פעילה</label><button class="btn pri" id="asave">שמירה</button></div>' +
          '<div class="flow"><div class="node trig"><span class="n-ic">' + ic('bolt') + '</span><div class="n-b"><small>טריגר</small><select class="inp" id="atr">' + Object.keys(BOOT.triggers).map(function (k) { return '<option value="' + k + '"' + (k === A.trigger_type ? ' selected' : '') + '>' + esc(BOOT.triggers[k]) + '</option>'; }).join('') + '</select>' + trigCfg(A) + '</div></div>' +
          A.steps.map(function (s, i) { return '<div class="link"><button class="add-in" data-ins="' + i + '" title="הוספת שלב">+</button></div>' + stepNode(s, i); }).join('') +
          '<div class="link"><button class="add-in big" data-ins="' + A.steps.length + '">+ שלב</button></div><div class="node end"><span class="n-ic">' + ic('check') + '</span><div class="n-b"><b>סוף</b></div></div></div>' +
          (id !== 'new' ? '<section class="card"><div class="card-h"><h2>הרצות אחרונות</h2></div><div id="runs"><p class="muted pad">טוען…</p></div></section>' : '') + '</div>';
        if (id !== 'new') api('automations/' + id + '/runs').then(function (rs) {
          $('#runs').innerHTML = rs.length ? '<table class="tbl"><thead><tr><th>איש קשר</th><th>סטטוס</th><th>שלב</th><th>מתי</th></tr></thead><tbody>' + rs.map(function (r) { return '<tr><td>' + (r.contact_id ? '<a href="#/contacts/' + r.contact_id + '">' + esc(r.contact) + '</a>' : '—') + '</td><td><span class="pill ' + (r.status === 'done' ? 'ok' : '') + '">' + ({ done: 'הושלמה', waiting: 'ממתינה', queued: 'בתור', running: 'רצה', stopped: 'נעצרה' }[r.status] || r.status) + '</span></td><td>' + r.step + '/' + A.steps.length + '</td><td class="muted">' + esc(ago(r.created_at)) + '</td></tr>'; }).join('') + '</tbody></table>' : '<p class="muted pad">עוד לא רצה.</p>';
        });
      };
      var collect = function () {
        A.name = $('#an').value.trim(); A.active = $('#aact').checked ? 1 : 0; A.trigger_type = $('#atr').value;
        A.trigger_cfg = {}; $$('[data-cfg]', view).forEach(function (el) { if (el.value) A.trigger_cfg[el.dataset.cfg] = el.value; });
        $$('.node.step', view).forEach(function (n) { var i = +n.dataset.i; $$('[data-k]', n).forEach(function (el) { A.steps[i][el.dataset.k] = el.type === 'checkbox' ? el.checked : (el.type === 'number' ? +el.value : el.value); }); });
      };
      render();
      view.addEventListener('change', function (e) { if (e.target.id === 'atr') { collect(); A.trigger_cfg = {}; render(); } });
      on(view, 'click', '[data-ins]', function (e, b) {
        collect();
        var at = +b.dataset.ins;
        var m = modal('איזה שלב להוסיף?', '<div class="step-pick">' + Object.keys(BOOT.steps).map(function (k) { return '<button type="button" data-pick="' + k + '">' + ic(stepIcon(k)) + '<span>' + esc(BOOT.steps[k]) + '</span></button>'; }).join('') + '</div>');
        on(m.el, 'click', '[data-pick]', function (e2, p) { A.steps.splice(at, 0, defaultStep(p.dataset.pick)); m.close(); render(); });
      });
      on(view, 'click', '[data-rm]', function (e, b) { collect(); A.steps.splice(+b.dataset.rm, 1); render(); });
      on(view, 'click', '[data-up]', function (e, b) { collect(); var i = +b.dataset.up; if (i > 0) { var t = A.steps[i]; A.steps[i] = A.steps[i - 1]; A.steps[i - 1] = t; render(); } });
      on(view, 'click', '#asave', function () {
        collect();
        if (!A.name) { toast('תנו שם לאוטומציה', true); $('#an').focus(); return; }
        if (!A.steps.length) { toast('הוסיפו לפחות שלב אחד', true); return; }
        api(id === 'new' ? 'automations' : 'automations/' + id, { method: 'POST', body: A }).then(function (r) { toast(A.active ? 'נשמר ופעיל' : 'נשמר (כבוי)'); if (id === 'new') location.hash = '#/automations/' + r.id; });
      });
    }).catch(fail);
  }
  function trigCfg(A) {
    var c = A.trigger_cfg || {}, t = A.trigger_type;
    if (t === 'form_submitted' || t === 'contact_created') return '<input class="inp sm" data-cfg="source" placeholder="רק ממקור שמכיל… (לא חובה)" value="' + esc(c.source || '') + '">';
    if (t === 'tag_added') return '<input class="inp sm" data-cfg="tag" list="taglist2" placeholder="שם התגית" value="' + esc(c.tag || '') + '"><datalist id="taglist2">' + BOOT.tags.map(function (x) { return '<option value="' + esc(x.name) + '">'; }).join('') + '</datalist>';
    if (t === 'stage_changed') return '<select class="inp sm" data-cfg="stage_id"><option value="">כל שלב</option>' + allStages().map(function (s) { return '<option value="' + s.id + '"' + (+c.stage_id === +s.id ? ' selected' : '') + '>' + esc(s.name) + '</option>'; }).join('') + '</select>';
    if (t === 'message_received') return '<select class="inp sm" data-cfg="channel"><option value="">כל ערוץ</option><option value="whatsapp"' + (c.channel === 'whatsapp' ? ' selected' : '') + '>וואטסאפ</option><option value="email"' + (c.channel === 'email' ? ' selected' : '') + '>מייל</option><option value="form"' + (c.channel === 'form' ? ' selected' : '') + '>טופס</option></select>';
    return '';
  }
  function stepIcon(k) { return { wait: 'clock', wait_before: 'clock', send_email: 'mail', send_whatsapp: 'wa', add_tag: 'tag', remove_tag: 'tag', create_task: 'check', move_stage: 'kanban', notify_me: 'inbox', if_tag: 'flow' }[k] || 'bolt'; }
  function defaultStep(k) {
    return ({ wait: { type: k, minutes: 60 }, wait_before: { type: k, minutes: 60 }, send_email: { type: k, subject: '', body: 'היי {{first_name}},\n' }, send_whatsapp: { type: k, body: 'היי {{first_name}}, ' },
      add_tag: { type: k, tag: '' }, remove_tag: { type: k, tag: '' }, create_task: { type: k, title: '', due_minutes: 1440 }, move_stage: { type: k, stage_id: 0 }, notify_me: { type: k, subject: '', body: '' }, if_tag: { type: k, tag: '', negate: false } })[k];
  }
  function stepNode(s, i) {
    var b = '';
    var t = function (k, ph, v) { return '<input class="inp sm" data-k="' + k + '" placeholder="' + ph + '" value="' + esc(v == null ? '' : v) + '">'; };
    var ta = function (k, ph, v) { return '<textarea class="inp sm" rows="3" data-k="' + k + '" placeholder="' + ph + '">' + esc(v || '') + '</textarea>'; };
    var n = function (k, v, unit) { return '<span class="inl"><input class="inp sm num" type="number" min="0" data-k="' + k + '" value="' + (v || 0) + '">' + unit + '</span>'; };
    switch (s.type) {
      case 'wait': b = '<span class="inl">לחכות ' + n('minutes', s.minutes, 'דקות') + '</span><small class="muted">' + humanMin(s.minutes) + '</small>'; break;
      case 'wait_before': b = '<span class="inl">עד ' + n('minutes', s.minutes, 'דקות לפני הפגישה') + '</span><small class="muted">' + humanMin(s.minutes) + ' לפני</small>'; break;
      case 'send_email': b = t('subject', 'נושא', s.subject) + ta('body', 'תוכן. אפשר {{first_name}}, {{booking_link}}', s.body); break;
      case 'send_whatsapp': b = ta('body', 'הודעה (בתוך חלון 24 שעות)', s.body) + t('template', 'או שם תבנית מאושרת (לא חובה)', s.template) + t('params', 'פרמטרים לתבנית, מופרדים ב־|', s.params); break;
      case 'add_tag': case 'remove_tag': case 'if_tag': b = t('tag', 'שם התגית', s.tag) + (s.type === 'if_tag' ? '<label class="tgl sm"><input type="checkbox" data-k="negate"' + (s.negate ? ' checked' : '') + '><span></span>להמשיך רק אם אין את התגית</label>' : ''); break;
      case 'create_task': b = t('title', 'מה המשימה', s.title) + '<span class="inl">מועד: בעוד ' + n('due_minutes', s.due_minutes, 'דקות') + '</span>'; break;
      case 'move_stage': b = '<select class="inp sm" data-k="stage_id"><option value="0">בחרו שלב</option>' + allStages().map(function (x) { return '<option value="' + x.id + '"' + (+s.stage_id === +x.id ? ' selected' : '') + '>' + esc(x.name) + '</option>'; }).join('') + '</select>'; break;
      case 'notify_me': b = t('subject', 'כותרת', s.subject) + ta('body', 'מה לכתוב לי', s.body); break;
    }
    return '<div class="node step" data-i="' + i + '"><span class="n-ic">' + ic(stepIcon(s.type)) + '</span><div class="n-b"><div class="n-h"><b>' + esc(BOOT.steps[s.type]) + '</b><span class="n-tools">' + (i ? '<button class="ib xs" data-up="' + i + '" title="למעלה">↑</button>' : '') + '<button class="ib xs" data-rm="' + i + '" title="הסרה">' + ic('trash') + '</button></span></div>' + b + '</div></div>';
  }
  function humanMin(m) { m = +m || 0; if (m < 60) return m + ' דקות'; if (m < 1440) return (Math.round(m / 6) / 10) + ' שעות'; return (Math.round(m / 144) / 10) + ' ימים'; }

  /* ================= settings ================= */
  function vSettings() {
    api('settings').then(function (s) {
      var snippet = '<form id="mula-lead">\n  <input name="name" placeholder="שם" required>\n  <input name="phone" placeholder="טלפון" required>\n  <input name="email" placeholder="מייל">\n  <textarea name="message"></textarea>\n  <input name="website" style="display:none">\n  <button>שליחה</button>\n</form>\n<script>\ndocument.getElementById("mula-lead").addEventListener("submit", function (e) {\n  e.preventDefault();\n  var d = Object.fromEntries(new FormData(e.target));\n  d.key = "' + s.form_key + '"; d.source = document.title;\n  fetch("' + s.form_endpoint + '", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(d) })\n    .then(function () { e.target.innerHTML = "<p>תודה! נחזור אליכם בהקדם.</p>"; });\n});\n<\/script>';
      view.innerHTML = head('הגדרות', 'חיבור ערוצים ופרטי שליחה.') +
        '<div class="stack">' +
        '<form class="card set" id="sMail"><div class="card-h"><h2>' + ic('mail') + 'מייל</h2><span class="pill ok">פעיל</span></div><p class="muted">המיילים נשלחים מהשרת שלך. לחתימה ולמשלוח טוב יותר מומלץ מייל בדומיין שלך.</p>' +
        '<div class="g2">' + fld('from_name', 'שם השולח', s.from_name || '') + fld('from_email', 'כתובת השולח', s.from_email || '', 'ltr') + fld('reply_to', 'תשובות יגיעו אל', s.reply_to || '', 'ltr') + fld('notify_email', 'התראות אליי', s.notify_email || s.admin_email, 'ltr') + '</div><button class="btn pri sm">שמירה</button></form>' +
        '<form class="card set" id="sWa"><div class="card-h"><h2>' + ic('wa') + 'וואטסאפ (WhatsApp Cloud API)</h2><span class="pill ' + (BOOT.wa_ready ? 'ok' : '') + '">' + (BOOT.wa_ready ? 'מחובר' : 'לא מחובר') + '</span></div>' +
        '<ol class="steps-help"><li>ב־Meta for Developers יוצרים אפליקציה מסוג Business ומוסיפים את WhatsApp.</li><li>מעתיקים לכאן את <b>Phone number ID</b> ו־<b>Access token</b> קבוע (System User).</li><li>ב־Webhooks מדביקים את הכתובת ואת מפתח האימות שלמטה, ונרשמים לאירוע <b>messages</b>.</li></ol>' +
        '<div class="g2">' + fld('wa_phone_id', 'Phone number ID', s.wa_phone_id || '', 'ltr') + fld('wa_business_id', 'WhatsApp Business Account ID', s.wa_business_id || '', 'ltr') + fld('wa_token', 'Access token', s.wa_token || '', 'ltr') + fld('wa_app_secret', 'App secret (לאימות חתימה)', s.wa_app_secret || '', 'ltr') + '</div>' +
        '<div class="copyrow"><span>Webhook URL</span><code dir="ltr">' + esc(s.wa_webhook) + '</code><button type="button" class="btn ghost sm" data-copy="' + esc(s.wa_webhook) + '">העתקה</button></div>' +
        '<div class="copyrow"><span>Verify token</span><code dir="ltr">' + esc(s.wa_verify) + '</code><button type="button" class="btn ghost sm" data-copy="' + esc(s.wa_verify) + '">העתקה</button></div>' +
        '<button class="btn pri sm">שמירה</button></form>' +
        '<div class="card set"><div class="card-h"><h2>' + ic('form') + 'טפסים מהאתר</h2><span class="pill ok">מוכן</span></div><p class="muted">כל טופס ששולח לכתובת הזו יוצר איש קשר, נכנס לשיחות ומפעיל אוטומציות. מתקבל רק מ־muladigital.co.il.</p>' +
        '<div class="copyrow"><span>כתובת</span><code dir="ltr">' + esc(s.form_endpoint) + '</code><button type="button" class="btn ghost sm" data-copy="' + esc(s.form_endpoint) + '">העתקה</button></div>' +
        '<details><summary>קוד לדוגמה להטמעה באתר</summary><pre dir="ltr">' + esc(snippet) + '</pre><button type="button" class="btn ghost sm" data-copy-snip>העתקת הקוד</button></details></div>' +
        '</div>';
      var save = function (form) { var b = {}; $$('input', form).forEach(function (i) { b[i.name] = i.value; }); return api('settings', { method: 'POST', body: b }).then(function () { toast('נשמר'); refreshBoot(); setTimeout(vSettings, 300); }); };
      $('#sMail').addEventListener('submit', function (e) { e.preventDefault(); save(e.target); });
      $('#sWa').addEventListener('submit', function (e) { e.preventDefault(); save(e.target); });
      on(view, 'click', '[data-copy]', function (e, b) { copy(b.dataset.copy); });
      on(view, 'click', '[data-copy-snip]', function () { copy(snippet); });
    }).catch(fail);
  }

  /* ================= boot ================= */
  api('boot').then(function (b) {
    BOOT = b; shell(); route();
    setInterval(function () { if (!document.hidden) api('boot').then(function (x) { if (x.unread !== BOOT.unread) { if (x.unread > BOOT.unread) toast('הודעה חדשה נכנסה'); } BOOT = x; setUnread(x.unread); }); }, 45000);
  }).catch(function (e) { root.innerHTML = '<div class="empty">' + ic('x') + '<h3>לא הצלחנו לטעון</h3><p>' + esc(e.message) + '</p></div>'; });
})();
