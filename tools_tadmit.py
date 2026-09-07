# -*- coding: utf-8 -*-
"""
בונה את דף הנחיתה atar-tadmit.html מתוך שלד service-web-build.html.
משמר header / mega-menu / footer / סקריפטים, מחליף SEO, סכמות ותוכן.
"""
import io, re, sys

SRC = "service-web-build.html"
DST = "atar-tadmit.html"

src = io.open(SRC, encoding="utf-8").read()

M_HERO = '<section class="srv-hero">'
M_FOOT = '  <footer class="site">'
assert src.count(M_HERO) == 1, "hero marker not unique"
assert src.count(M_FOOT) == 1, "footer marker not unique"

head_body = src[: src.index(M_HERO)]
tail = src[src.index(M_FOOT):]

# ---------------------------------------------------------------- SEO ------
TITLE = u"בניית אתר תדמית לעסק | מחיר, דוגמאות ותהליך | Mula Digital"
DESC = (u"אתר תדמית שגורם ללקוח לבחור בך עוד לפני שדיברתם. מחיר 6,000-18,000 ש\"ח, "
        u"2-4 שבועות, 29 אתרים חיים בתיק העבודות. משה מולה, Mula Digital.")
URL = "https://muladigital.co.il/atar-tadmit.html"

head_body = re.sub(r"<title>.*?</title>", u"<title>%s</title>" % TITLE, head_body, count=1, flags=re.S)
head_body = re.sub(r'<meta name="description" content=".*?" />',
                   u'<meta name="description" content="%s" />' % DESC, head_body, count=1, flags=re.S)
head_body = re.sub(r'<link rel="canonical" href=".*?" />',
                   u'<link rel="canonical" href="%s" />' % URL, head_body, count=1, flags=re.S)
head_body = re.sub(r'<meta property="og:title" content=".*?" />',
                   u'<meta property="og:title" content="%s" />' % u"בניית אתר תדמית לעסק | מחיר, דוגמאות ותהליך",
                   head_body, count=1, flags=re.S)
head_body = re.sub(r'<meta property="og:description" content=".*?" />',
                   u'<meta property="og:description" content="%s" />' % DESC, head_body, count=1, flags=re.S)

# ------------------------------------------------------------ schemas ------
FAQS = [
    (u"מה זה אתר תדמית?",
     u"אתר תדמית הוא האתר שמציג את העסק שלך: מי אתה, מה אתה עושה, למי, ולמה שיבחרו דווקא בך. "
     u"הוא לא חנות ולא דף נחיתה בודד - הוא הבית הדיגיטלי של העסק, בדרך כלל 5 עד 10 עמודים. "
     u"התפקיד שלו הוא לא למכור בלחיצה אחת, אלא לגרום למי שכבר שמע עליך להרים טלפון בביטחון."),
    (u"כמה עולה אתר תדמית?",
     u"בשוק הישראלי ב-2026 אתר תדמית עסקי של 5-10 עמודים נע בין 6,000 ל-18,000 ש\"ח. "
     u"הפער נובע מרמת העיצוב (תבנית מותאמת מול עיצוב מאפס), מכמות התוכן שצריך לכתוב, "
     u"ומהחיבורים למערכות - CRM, טפסים, אזור אישי. בשיחת אבחון של רבע שעה אפשר לומר לך מספר מדויק."),
    (u"מה ההבדל בין אתר תדמית לדף נחיתה?",
     u"דף נחיתה הוא עמוד אחד עם מטרה אחת - להשאיר פרטים, בדרך כלל מאחורי קמפיין ממומן. "
     u"אתר תדמית הוא מבנה שלם שנועד לבסס אמון לאורך זמן ולהיסרק על ידי גוגל. "
     u"בפועל רוב העסקים צריכים את שניהם: האתר בונה את המוניטין, דף הנחיתה קולט את התנועה הממומנת."),
    (u"אתר תדמית בוורדפרס או בוויקס?",
     u"אני בונה בוורדפרס. הסיבה פשוטה: האתר נשאר שלך. אפשר להעביר אותו לכל שרת, לחבר אותו לכל מערכת, "
     u"ולקדם אותו בגוגל בלי תקרה טכנית. בוויקס אתה שוכר, בוורדפרס אתה בעלים. "
     u"בעסק שמתכוון לגדול, ההבדל הזה מרגיש אחרי שנתיים."),
    (u"כמה זמן לוקח לבנות אתר תדמית?",
     u"בין שבועיים לארבעה שבועות. השלב שהכי מאריך הוא התוכן - לא הבנייה. "
     u"אם יש חומרים מוכנים אפשר לסיים מהר, ואם צריך לכתוב מאפס אני כותב, וזה מוסיף כשבוע."),
    (u"אתר תדמית באמת מביא לקוחות?",
     u"אתר תדמית לבדו לא מייצר תנועה - הוא סוגר אותה. הוא הופך מישהו שכבר הגיע אליך לפנייה. "
     u"מי שמצפה שהאתר יביא גולשים בעצמו צריך להוסיף לו מנוע: קידום אורגני או קמפיין ממומן. "
     u"האתר הוא מה שגורם לתנועה הזו להיות שווה משהו."),
    (u"מה כולל אתר תדמית לעסק קטן?",
     u"עמוד בית, עמוד אודות, עמודי שירות, תיק עבודות או המלצות, עמוד יצירת קשר, "
     u"התאמה מלאה למובייל, מהירות טעינה, חיבור לגוגל אנליטיקס ולגוגל ביזנס, טופס שמגיע לוואטסאפ, "
     u"וכתיבת התוכן. הכל נמסר עובד, לא כשלד ריק שאתה צריך למלא."),
]

faq_schema = {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [
        {"@type": "Question", "name": q,
         "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in FAQS
    ],
}

service_schema = {
    "@context": "https://schema.org",
    "@type": "Service",
    "name": u"בניית אתר תדמית לעסק",
    "serviceType": u"אתר תדמית",
    "description": u"בניית אתר תדמית לעסק - 5 עד 10 עמודים, עיצוב מותאם, תוכן כתוב, חיבור למערכות ומוכנות לקידום בגוגל.",
    "provider": {
        "@type": "Organization",
        "@id": "https://muladigital.co.il/#business",
        "name": "Mula Digital",
        "url": "https://muladigital.co.il/",
        "telephone": "+972-50-824-9824",
        "email": "Moshe@muladigital.co.il",
        "founder": {"@type": "Person", "@id": "https://muladigital.co.il/#moshe",
                    "name": u"משה מולה", "alternateName": "Moshe Mula",
                    "url": "https://muladigital.co.il/about.html",
                    "sameAs": ["https://www.linkedin.com/in/moshemula/"]},
    },
    "areaServed": {"@type": "Country", "name": u"ישראל"},
    "url": URL,
    "offers": {
        "@type": "Offer",
        "priceCurrency": "ILS",
        "priceSpecification": {
            "@type": "PriceSpecification",
            "minPrice": "6000", "maxPrice": "18000", "priceCurrency": "ILS",
        },
    },
    "aggregateRating": {"@type": "AggregateRating", "ratingValue": "5.0",
                        "reviewCount": "20", "bestRating": "5"},
}

crumb_schema = {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        {"@type": "ListItem", "position": 1, "name": u"בית", "item": "https://muladigital.co.il/"},
        {"@type": "ListItem", "position": 2, "name": u"שירותים", "item": "https://muladigital.co.il/services.html"},
        {"@type": "ListItem", "position": 3, "name": u"אתר תדמית", "item": URL},
    ],
}

import json
from tools_tadmit_part2 import (CSS, PHONE_BTN, WA_BTN, STARS, WALL,
                                INCLUDED, PROCESS)

def ld(obj):
    return (u'  <script type="application/ld+json">\n  '
            + json.dumps(obj, ensure_ascii=False, indent=2).replace("\n", "\n  ")
            + u'\n  </script>\n')

# החלף את שלוש הסכמות הקיימות בשלוש החדשות
blocks = re.findall(r'  <script type="application/ld\+json">.*?</script>\n',
                    head_body, flags=re.S)
assert len(blocks) == 3, "expected 3 ld+json blocks, got %d" % len(blocks)
new_ld = ld(service_schema) + ld(faq_schema) + ld(crumb_schema)
head_body = head_body.replace(blocks[0] + blocks[1] + blocks[2], new_ld, 1)

# הזרק CSS ייעודי לפני סגירת ה-head
head_body = head_body.replace("</head>", CSS + "</head>", 1)

from tools_tadmit_part3 import build_body

WALL_JS = u"""
  <script>
  /* גלילה בתוך חלונות ההוכחה: מרחק אמיתי לפי גובה הצילום */
  (function(){
    function fit(w){
      var img=w.querySelector('.lp-wview img'), view=w.querySelector('.lp-wview');
      if(!img||!view) return;
      var go=function(){
        var d=img.offsetHeight-view.clientHeight;
        if(d<=0){w.style.setProperty('--travel','0px');return;}
        w.style.setProperty('--travel', (-Math.min(d,2600))+'px');
      };
      if(img.complete) go(); else img.addEventListener('load',go,{once:true});
    }
    document.querySelectorAll('.lp-w').forEach(fit);
    addEventListener('resize',function(){document.querySelectorAll('.lp-w').forEach(fit);},{passive:true});
  })();
  </script>
"""

body = build_body(FAQS) + WALL_JS
out = head_body + body + tail

io.open(DST, "w", encoding="utf-8").write(out)
print("wrote %s  (%d KB)" % (DST, len(out.encode("utf-8")) // 1024))

# ---- מעקב המרות: לחיצת טלפון / וואטסאפ ----
TRACK = u"""
  <script>
  /* מעקב המרות לדף הנחיתה - נשלח ל-GA4 וממנו מייבאים ל-Google Ads */
  (function(){
    function fire(name, label){
      if(typeof gtag!=='function') return;   /* gtag.js עדיין לא נטען - לא מפילים את השאר */
      gtag('event', name, {
        event_category:'lead',
        event_label:label,
        page_location:location.href,
        send_to:'G-GEHNCC9X6F'
      });
    }
    document.addEventListener('click', function(e){
      var a = e.target.closest('a[href]');
      if(!a) return;
      var h = a.getAttribute('href') || '';
      if(h.indexOf('tel:') === 0)        fire('click_phone','atar-tadmit');
      else if(h.indexOf('wa.me') > -1)   fire('click_whatsapp','atar-tadmit');
      else if(h.indexOf('mailto:') === 0) fire('click_email','atar-tadmit');
    }, true);
    /* גלילה של 75% = עניין אמיתי, שימושי כאות משני ל-Ads */
    var deep=false;
    addEventListener('scroll', function(){
      if(deep) return;
      var p=(scrollY+innerHeight)/document.body.scrollHeight;
      if(p>=.75){ deep=true; fire('scroll_75','atar-tadmit'); }
    }, {passive:true});
  })();
  </script>
"""

t = io.open(DST, encoding="utf-8").read()
if "click_whatsapp" not in t:
    t = t.replace("</body>", TRACK + "</body>", 1)
    io.open(DST, "w", encoding="utf-8").write(t)
    print("tracking: injected")
