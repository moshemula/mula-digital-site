# -*- coding: utf-8 -*-
"""חלק ב': ה-CSS והתוכן של atar-tadmit.html"""

CSS = u"""
<style>
  /* ===== landing: atar tadmit (scoped lp-) ===== */
  .lp-trio{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;}
  .lp-t{background:#fff;border:1px solid var(--line);border-radius:var(--r);padding:30px 26px;
        position:relative;overflow:hidden;transition:transform .3s var(--ease),box-shadow .3s,border-color .3s;}
  .lp-t::after{content:"";position:absolute;inset-block-start:0;inset-inline:0;height:3px;background:var(--grad);
        transform:scaleX(0);transform-origin:100% 50%;transition:transform .45s var(--ease);}
  .lp-t:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:var(--line-2);}
  .lp-t:hover::after{transform:scaleX(1);}
  .lp-t b{display:block;font-family:var(--f-display);font-size:clamp(20px,2.4vw,24px);margin-bottom:8px;}
  .lp-t span{display:block;font-size:13px;font-weight:700;color:var(--purple);letter-spacing:.06em;margin-bottom:14px;}
  .lp-t p{color:var(--soft);font-size:16.5px;line-height:1.7;}

  /* deliverables */
  .lp-inc{display:grid;grid-template-columns:repeat(2,1fr);gap:0 40px;}
  .lp-inc li{list-style:none;display:flex;gap:14px;align-items:flex-start;
        padding:16px 0;border-bottom:1px solid var(--line);}
  .lp-inc svg{width:22px;height:22px;flex:none;margin-top:3px;stroke:var(--purple);fill:none;
        stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round;}
  .lp-inc b{font-family:var(--f-display);font-size:17px;display:block;}
  .lp-inc small{display:block;color:var(--muted);font-size:15px;line-height:1.6;margin-top:2px;}

  /* pricing */
  .lp-price{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;align-items:stretch;}
  .lp-p{background:#fff;border:1px solid var(--line);border-radius:var(--r);padding:32px 26px;
        display:flex;flex-direction:column;transition:transform .3s var(--ease),box-shadow .3s;}
  .lp-p:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);}
  .lp-p.hot{border-color:rgba(123,47,190,.42);box-shadow:var(--shadow-glow);position:relative;}
  .lp-p.hot::before{content:"הכי נבחר";position:absolute;inset-block-start:-13px;inset-inline-start:50%;
        transform:translateX(50%);background:var(--grad);color:#fff;font-family:var(--f-display);
        font-weight:700;font-size:12.5px;padding:5px 16px;border-radius:100px;white-space:nowrap;}
  .lp-p h3{font-size:22px;margin-bottom:6px;}
  .lp-p .lp-range{font-family:var(--f-display);font-weight:800;font-size:clamp(26px,3.2vw,32px);
        margin:14px 0 4px;letter-spacing:-.03em;}
  .lp-p .lp-when{color:var(--muted);font-size:14.5px;margin-bottom:18px;}
  .lp-p ul{margin:0 0 22px;padding:0;}
  .lp-p li{list-style:none;font-size:16px;color:var(--soft);padding:7px 0;
        border-bottom:1px dashed var(--line);}
  .lp-p li:last-child{border-bottom:0;}
  .lp-p .btn{margin-top:auto;justify-content:center;}
  .lp-note{margin-top:20px;color:var(--muted);font-size:15px;text-align:center;}

  /* comparison */
  .lp-vs{overflow-x:auto;-webkit-overflow-scrolling:touch;}
  .lp-vs table{width:100%;min-width:560px;border-collapse:collapse;}
  .lp-vs th,.lp-vs td{padding:16px 18px;text-align:start;border-bottom:1px solid var(--line);font-size:16.5px;}
  .lp-vs thead th{font-family:var(--f-display);font-weight:800;font-size:17px;
        border-bottom:2px solid var(--ink);}
  .lp-vs tbody th{font-weight:700;color:var(--ink);width:30%;}
  .lp-vs td{color:var(--soft);}
  .lp-vs tbody tr:hover{background:var(--surface);}

  /* proof windows */
  .lp-wall{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;}
  .lp-w{border-radius:16px;overflow:hidden;background:#0F0F16;border:1px solid var(--night-line);
        box-shadow:0 18px 50px rgba(0,0,0,.28);}
  .lp-wbar{height:26px;display:flex;align-items:center;gap:6px;padding:0 12px;background:#15151c;
        border-bottom:1px solid var(--night-line);}
  .lp-wbar i{width:6px;height:6px;border-radius:50%;background:#3A3446;flex:none;}
  .lp-wbar span{flex:1;height:11px;border-radius:100px;background:rgba(255,255,255,.07);margin-inline-start:8px;}
  .lp-wview{position:relative;height:230px;overflow:hidden;background:#fff;}
  .lp-wview img{width:100%;height:auto;display:block;
        transition:transform 6s cubic-bezier(.24,.4,.28,1);will-change:transform;}
  .lp-w:hover .lp-wview img{transform:translate3d(0,var(--travel,-60%),0);}
  .lp-wcap{padding:14px 16px;background:var(--night-card);color:#fff;
        display:flex;align-items:baseline;justify-content:space-between;gap:10px;}
  .lp-wcap b{font-family:var(--f-display);font-size:16px;}
  .lp-wcap small{color:rgba(255,255,255,.55);font-size:13.5px;}

  @media (max-width:900px){
    .lp-trio,.lp-price,.lp-wall{grid-template-columns:1fr 1fr;}
    .lp-p.hot::before{inset-inline-start:auto;inset-inline-end:20px;transform:none;}
  }
  @media (max-width:640px){
    .lp-trio,.lp-price,.lp-wall,.lp-inc{grid-template-columns:1fr;}
    .lp-inc{gap:0;}
  }
  @media (prefers-reduced-motion:reduce){
    .lp-wview img{transition:none;}
    .lp-w:hover .lp-wview img{transform:none;}
  }
</style>
"""

PHONE_BTN = (u'<a href="tel:0508249824" class="btn btn-primary">'
             u'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
             u'stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 '
             u'19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 '
             u'4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 '
             u'6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>'
             u'שיחת אבחון · 050-824-9824</a>')

WA_BTN = (u'<a href="https://wa.me/972508249824?text=%D7%94%D7%99%D7%99%2C%20%D7%9E%D7%A2%D7%95%D7%A0%D7%99'
          u'%D7%99%D7%9F%20%D7%91%D7%90%D7%AA%D7%A8%20%D7%AA%D7%93%D7%9E%D7%99%D7%AA" class="btn btn-white" '
          u'target="_blank" rel="noopener">'
          u'<svg viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px">'
          u'<path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.82 '
          u'11.82 0 0 1 8.413 3.488 11.82 11.82 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 '
          u'11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24z"/></svg>שלחו וואטסאפ</a>')

STARS = (u'<div class="tc-stars">' + u'<svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 '
         u'4.87L18.18 21 12 17.27 5.82 21 7 14.14l-5-4.87 6.91-1.01z"/></svg>' * 5 + u'</div>')

WALL = [
    ("eldadlevi", u"אלדד לוי", u"שמאות מקרקעין"),
    ("harela",    u"הראל רו\"ח", u"ראיית חשבון"),
    ("tamuz",     u"תמוז", u"בנייה ותשתיות"),
    ("finansee",  u"Finansee", u"פיננסים"),
    ("athletics", u"Athletics", u"כושר וספורט"),
    ("lasercut",  u"Laser Cut", u"תעשייה"),
]

INCLUDED = [
    (u"אסטרטגיית מסרים", u"מה אומרים בעמוד הבית, ובאיזה סדר. זה נקבע לפני שנוגעים בעיצוב."),
    (u"עיצוב מותאם למותג", u"לא תבנית שכולם מכירים. הצבע, הטיפוגרפיה והתחושה - שלך."),
    (u"כתיבת כל התוכן", u"אני כותב. אתה לא מקבל אתר ריק עם הערה \"תשלים טקסטים\"."),
    (u"5 עד 10 עמודים", u"בית, אודות, שירותים, תיק עבודות או המלצות, יצירת קשר."),
    (u"מובייל לפני הכל", u"רוב הגולשים שלך בטלפון. שם האתר נבדק קודם, לא במסך גדול."),
    (u"מהירות טעינה", u"תמונות דחוסות, קוד נקי. אתר איטי מאבד אנשים לפני שראו אותו."),
    (u"טופס שמגיע לוואטסאפ", u"פנייה נוחתת אצלך בטלפון תוך שניות, לא בתיבת מייל נשכחת."),
    (u"חיבור ל-CRM ולמערכות", u"יומן, אזור אישי, סליקה - מה שהעסק שלך באמת מפעיל."),
    (u"מוכנות לגוגל", u"מבנה, כותרות, סכמות ו-Analytics. שהאתר יוכל להתקדם אורגנית."),
    (u"הדרכה ומסירה", u"אתה יודע לעדכן לבד. הקוד והדומיין רשומים על שמך."),
]

PROCESS = [
    (u"שיחת אבחון", u"רבע שעה. מי הלקוח שלך, מה הוא שואל לפני שהוא קונה, ומה מפריד ביניכם. "
                    u"מכאן יוצא מחיר מדויק ולא הערכה."),
    (u"מפת תוכן ומסרים", u"מחליטים מה נמצא בכל עמוד ומה הוא אמור לגרום לגולש לעשות. "
                         u"אתה מאשר את המסרים לפני שמעצבים."),
    (u"עיצוב", u"מקבל את עמוד הבית לצפייה לפני שממשיכים. סבב תיקונים אחד, ואז שאר העמודים "
               u"נבנים על אותה שפה."),
    (u"בנייה וחיבורים", u"וורדפרס, מובייל, מהירות, טפסים, CRM, אנליטיקס. "
                        u"בסוף השלב יש אתר עובד בכתובת פרטית שאתה בודק."),
    (u"עלייה לאוויר וליווי", u"עולים לאוויר, מחברים לגוגל ביזנס ולסרץ' קונסול, "
                             u"ואני נשאר זמין. גם אחרי שהחשבונית נסגרה."),
]
