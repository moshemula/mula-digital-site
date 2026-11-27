/* מדידת פניות: טלפון, וואטסאפ, מייל וטפסים. שולח אירוע לגוגל אנליטיקס ולקלריטי עם הדף שממנו בוצעה הפנייה. */
(function () {
  if (window.__mulaTrack) return;
  window.__mulaTrack = true;

  /* פיקסל פייסבוק: מולה דיגיטל */
  var PIXEL_ID = '2415553159249294';
  if (!window.fbq) {
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
    document,'script','https://connect.facebook.net/en_US/fbevents.js');
  }
  fbq('init', PIXEL_ID);
  fbq('track', 'PageView');

  var FB_MAP = {
    click_call: ['Lead', 'טלפון'],
    click_whatsapp: ['Lead', 'וואטסאפ'],
    lead_form_submit: ['Lead', 'טופס'],
    click_email: ['Contact', 'מייל']
  };

  function send(name, params) {
    params = params || {};
    params.page_path = location.pathname;
    params.page_title = document.title;
    try { if (window.gtag) gtag('event', name, params); } catch (e) {}
    try {
      var m = FB_MAP[name];
      if (m && window.fbq) fbq('track', m[0], { content_name: m[1], content_category: location.pathname });
    } catch (e) {}
    try {
      if (window.clarity) {
        clarity('event', name);
        clarity('set', 'last_lead_type', name);
        clarity('set', 'last_lead_page', location.pathname);
      }
    } catch (e) {}
  }

  function where(el) {
    var s = el.closest('header,nav,footer,section,[id]');
    if (!s) return 'body';
    return s.id || s.tagName.toLowerCase();
  }

  document.addEventListener('click', function (ev) {
    var a = ev.target.closest && ev.target.closest('a[href]');
    if (!a) return;
    var h = a.getAttribute('href') || '';
    var label = (a.getAttribute('aria-label') || a.textContent || '').trim().slice(0, 60);
    var p = { link_url: h, link_text: label, cta_location: where(a) };
    if (/^tel:/i.test(h)) send('click_call', p);
    else if (/wa\.me|api\.whatsapp\.com|whatsapp:/i.test(h)) send('click_whatsapp', p);
    else if (/^mailto:/i.test(h)) send('click_email', p);
  }, true);

  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!f || f.tagName !== 'FORM') return;
    send('lead_form_submit', { form_id: f.id || f.getAttribute('name') || '', cta_location: where(f) });
  }, true);

  /* מערכת הלידים של MULA DIGITAL: כל טופס שנשלח ל-FormSubmit עובר למייל ממותג
     (moshe@muladigital.co.il + moshemula@gmail.com) ולגיליון לידים. אם יש תקלה, נופל חזרה ל-FormSubmit כגיבוי. */
  var LEAD_ENDPOINT = 'https://script.google.com/macros/s/AKfycbwdvER1oBcEPxBOXHr4u6cLsmFpagmVFkRu5aOFD6H_X6_uPsdef-CnA84KYDX1k-XF/exec';
  var isFS = function (u) { return typeof u === 'string' && u.indexOf('formsubmit.co') > -1; };
  var origFetch = window.fetch ? window.fetch.bind(window) : null;

  function leadParams(body) {
    var p = new URLSearchParams();
    try {
      var fd = body instanceof FormData ? body : (body instanceof HTMLFormElement ? new FormData(body) : null);
      if (fd) fd.forEach(function (v, k) { if (typeof v === 'string') p.append(k, v); });
      else if (body instanceof URLSearchParams) body.forEach(function (v, k) { p.append(k, v); });
      else if (typeof body === 'string') { try { var o = JSON.parse(body); Object.keys(o).forEach(function (k) { p.append(k, o[k]); }); } catch (e) { new URLSearchParams(body).forEach(function (v, k) { p.append(k, v); }); } }
    } catch (e) {}
    p.set('page_url', location.href.split('#')[0]);
    p.set('page_title', document.title);
    p.set('referrer', document.referrer ? document.referrer.replace(/^https?:\/\//, '').split('/')[0] : 'ישיר');
    var q = new URLSearchParams(location.search), utm = [];
    ['utm_source', 'utm_medium', 'utm_campaign', 'gclid', 'fbclid'].forEach(function (k) { if (q.get(k)) utm.push(k.replace('utm_', '') + '=' + q.get(k)); });
    if (utm.length) p.set('utm', utm.join(' '));
    return p;
  }

  function sendLead(body) {
    return origFetch(LEAD_ENDPOINT, { method: 'POST', mode: 'no-cors', body: leadParams(body) });
  }

  if (origFetch && LEAD_ENDPOINT.indexOf('https://') === 0) {
    window.fetch = function (input, init) {
      var url = typeof input === 'string' ? input : (input && input.url);
      if (isFS(url) && init && init.body) {
        var body = init.body;
        return sendLead(body).then(function () {
          return new Response('{"success":"true"}', { status: 200, headers: { 'Content-Type': 'application/json' } });
        }, function () { return origFetch(input, init); });
      }
      return origFetch(input, init);
    };

    /* טפסים רגילים בלי סקריפט משלהם: שולחים למערכת ומעבירים לדף התודה */
    document.addEventListener('submit', function (ev) {
      var f = ev.target;
      if (!f || f.tagName !== 'FORM' || ev.defaultPrevented || !isFS(f.action)) return;
      ev.preventDefault();
      var btn = f.querySelector('[type=submit]');
      if (btn) { btn.disabled = true; }
      var next = (f.querySelector('[name=_next]') || {}).value;
      var go = function () {
        if (next) location.href = next;
        else { f.innerHTML = '<p style="font-size:18px;font-weight:700;text-align:center;padding:24px 0">תודה! קיבלנו את הפרטים ונחזור אליך בהקדם.</p>'; }
      };
      sendLead(f).then(go, function () { HTMLFormElement.prototype.submit.call(f); });
    }, false);
  }
})();
