/* מדידת פניות: טלפון, וואטסאפ, מייל וטפסים. שולח אירוע לגוגל אנליטיקס ולקלריטי עם הדף שממנו בוצעה הפנייה. */
(function () {
  if (window.__mulaTrack) return;
  window.__mulaTrack = true;

  function send(name, params) {
    params = params || {};
    params.page_path = location.pathname;
    params.page_title = document.title;
    try { if (window.gtag) gtag('event', name, params); } catch (e) {}
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
})();
