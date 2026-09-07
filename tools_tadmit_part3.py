# -*- coding: utf-8 -*-
"""חלק ג': הרכבת גוף הדף atar-tadmit.html"""
from tools_tadmit_part2 import (PHONE_BTN, WA_BTN, STARS, WALL, INCLUDED, PROCESS)

CHECK = ('<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>')


def build_body(faqs):
    inc = u"\n".join(
        u'          <li>%s<div><b>%s</b><small>%s</small></div></li>' % (CHECK, t, d)
        for t, d in INCLUDED)

    wall = u"\n".join(
        u'          <article class="lp-w reveal">\n'
        u'            <div class="lp-wbar" aria-hidden="true"><i></i><i></i><i></i><span></span></div>\n'
        u'            <div class="lp-wview"><img loading="lazy" decoding="async" '
        u'src="assets/portfolio/strips/%s.webp" alt="%s - אתר תדמית שבניתי" /></div>\n'
        u'            <div class="lp-wcap"><b>%s</b><small>%s</small></div>\n'
        u'          </article>' % (slug, name, name, role)
        for slug, name, role in WALL)

    steps = u"\n".join(
        u'          <div class="tl-step reveal">\n'
        u'            <span class="s-num">%d</span>\n'
        u'            <div><h3>%s</h3><p>%s</p></div>\n'
        u'          </div>' % (i + 1, t, d)
        for i, (t, d) in enumerate(PROCESS))

    faq_html = u"\n".join(
        u'        <details class="faq">\n'
        u'          <summary>%s</summary>\n'
        u'          <div class="ans">%s</div>\n'
        u'        </details>' % (q, a) for q, a in faqs)

    return u"""<section class="srv-hero">
    <div class="hero-bg" aria-hidden="true"></div>
    <div class="hero-shape hero-shape-1"></div>
    <div class="hero-shape hero-shape-2"></div>
    <div class="hero-shape hero-shape-3"></div>
    <div class="hero-shape hero-shape-4"></div>
    <div class="wrap">
      <div class="hero-inner">
        <div class="hero-content">
          <nav class="breadcrumb" aria-label="פירורי לחם">
            <a href="index.html">בית</a>
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <a href="services.html">שירותים</a>
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span style="color:rgba(255,255,255,.7)">אתר תדמית</span>
          </nav>
          <h1><span class="accent">אתר תדמית</span> שגורם ללקוח לבחור בך עוד לפני שדיברתם</h1>
          <p class="subtitle">הלקוח שלך בודק אותך ברשת לפני שהוא מרים טלפון. אתר תדמית הוא מה שהוא מוצא.
          6,000-18,000 ש"ח, 2-4 שבועות, 29 אתרים חיים בתיק העבודות.</p>
          <div class="hero-actions">
            %(phone)s
            %(wa)s
          </div>
          <a href="https://share.google/px8zW59yHP21QZPyc" target="_blank" rel="noopener" class="google-trust">
            <svg class="g-icon" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            <div class="stars"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.27 5.82 21 7 14.14l-5-4.87 6.91-1.01z"/></svg><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.27 5.82 21 7 14.14l-5-4.87 6.91-1.01z"/></svg><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.27 5.82 21 7 14.14l-5-4.87 6.91-1.01z"/></svg><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.27 5.82 21 7 14.14l-5-4.87 6.91-1.01z"/></svg><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.27 5.82 21 7 14.14l-5-4.87 6.91-1.01z"/></svg></div>
            <b>5.0</b>
            <small>20 ביקורות בגוגל</small>
          </a>
        </div>
        <div class="hero-visual">
          <div class="lp-w" style="box-shadow:0 30px 80px rgba(0,0,0,.45)">
            <div class="lp-wbar" aria-hidden="true"><i></i><i></i><i></i><span></span></div>
            <div class="lp-wview" style="height:320px">
              <img src="assets/portfolio/strips/eldadlevi.webp" alt="דוגמה לאתר תדמית שבניתי" />
            </div>
            <div class="lp-wcap"><b>אלדד לוי</b><small>שמאות מקרקעין</small></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- 7 SECONDS -->
  <section class="sec">
    <div class="wrap">
      <div class="s-head center reveal">
        <span class="eyebrow">למה זה קובע</span>
        <h2>בשבע השניות הראשונות הלקוח מחליט שלושה דברים</h2>
        <p>הוא לא קורא. הוא מרגיש. ואם התשובה על אחד מהשלושה שלילית - הוא חוזר לתוצאות החיפוש.</p>
      </div>
      <div class="lp-trio">
        <div class="lp-t reveal">
          <span>01</span>
          <b>האם הוא מקצוען</b>
          <p>אתר שנראה כמו תבנית שראה כבר חמש פעמים אומר לו שאתה כמו כולם.
          אתר שנבנה סביב העסק שלך אומר משהו אחר לגמרי - עוד לפני מילה אחת של טקסט.</p>
        </div>
        <div class="lp-t reveal">
          <span>02</span>
          <b>האם הוא זמין</b>
          <p>טלפון שנמצא בלי לחפש, וואטסאפ בלחיצה, טופס שלוקח עשר שניות.
          כל חיכוך קטן הוא סיבה לוותר ולעבור למישהו אחר.</p>
        </div>
        <div class="lp-t reveal">
          <span>03</span>
          <b>האם אפשר לסמוך עליו</b>
          <p>פנים אמיתיות, לקוחות בשם, ביקורות שאפשר לבדוק, עבודות שאפשר לפתוח.
          ביטחון הוא לא סיסמה בעמוד הבית - הוא הצטברות של הוכחות.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- WHAT IT IS -->
  <section class="sec surface">
    <div class="wrap narrow">
      <div class="s-head reveal">
        <span class="eyebrow">רגע של הגדרה</span>
        <h2>מה זה אתר תדמית, בלי הבלבול</h2>
      </div>
      <div class="prose reveal">
        <p>אתר תדמית הוא הבית הדיגיטלי של העסק. חמישה עד עשרה עמודים שמסבירים מי אתה,
        מה אתה עושה, למי, ולמה שיבחרו דווקא בך. הוא לא חנות אונליין ולא דף נחיתה בודד.</p>
        <p>וזאת הנקודה שהכי הרבה בעלי עסקים מפספסים: <strong>אתר תדמית לא מייצר תנועה - הוא סוגר אותה.</strong>
        מי שמצפה שהאתר לבדו יביא גולשים יתאכזב. מה שהאתר עושה זה להפוך את מי שכבר הגיע -
        מהמלצה, מגוגל, מכרטיס ביקור, מפוסט - ללקוח שמרים טלפון.</p>
        <p>לכן הוא שווה כל כך הרבה. כל שקל שאתה משקיע בשיווק עובר דרכו.
        אתר חלש מבזבז את כל התנועה שהבאת אליו. אתר טוב מכפיל את הערך של אותה תנועה בדיוק.</p>
      </div>
    </div>
  </section>

  <!-- INCLUDED -->
  <section class="sec">
    <div class="wrap">
      <div class="s-head center reveal">
        <span class="eyebrow">מה נכנס פנימה</span>
        <h2>מה כולל אתר תדמית אצלי</h2>
        <p>הכל נמסר עובד. לא שלד ריק עם הערה "תשלים תוכן".</p>
      </div>
      <div class="reveal">
        <ul class="lp-inc">
%(inc)s
        </ul>
      </div>
    </div>
  </section>

  <!-- PRICE -->
  <section class="sec surface" id="price">
    <div class="wrap">
      <div class="s-head center reveal">
        <span class="eyebrow">מחיר</span>
        <h2>כמה עולה אתר תדמית</h2>
        <p>המספרים האמיתיים של השוק הישראלי, כמו שאני עובד איתם. בלי "צור קשר לקבלת הצעה".</p>
      </div>
      <div class="lp-price">
        <div class="lp-p reveal">
          <h3>תבנית מותאמת</h3>
          <div class="lp-range">6,000-9,000 ₪</div>
          <div class="lp-when">2 שבועות · 5 עמודים</div>
          <ul>
            <li>תבנית איכותית מותאמת למותג</li>
            <li>כתיבת התוכן</li>
            <li>מובייל ומהירות</li>
            <li>טופס לוואטסאפ</li>
            <li>אנליטיקס וגוגל ביזנס</li>
          </ul>
          <a href="tel:0508249824" class="btn btn-dark">לבדוק התאמה</a>
        </div>
        <div class="lp-p hot reveal">
          <h3>עיצוב מותאם</h3>
          <div class="lp-range">9,000-14,000 ₪</div>
          <div class="lp-when">3 שבועות · 7-10 עמודים</div>
          <ul>
            <li>עיצוב ייחודי לעסק, לא תבנית</li>
            <li>אסטרטגיית מסרים לכל עמוד</li>
            <li>כתיבת התוכן</li>
            <li>חיבור ל-CRM ולמערכות</li>
            <li>מבנה מוכן לקידום אורגני</li>
            <li>הדרכה ומסירה על שמך</li>
          </ul>
          %(phone2)s
        </div>
        <div class="lp-p reveal">
          <h3>מאפס, עם מיתוג</h3>
          <div class="lp-range">14,000-18,000 ₪</div>
          <div class="lp-when">4 שבועות · לפי הצורך</div>
          <ul>
            <li>כל מה שבחבילה הקודמת</li>
            <li>שפה חזותית מלאה</li>
            <li>צילום תדמית ווידאו</li>
            <li>אזור אישי / סליקה / יומן</li>
            <li>ליווי שיווקי אחרי העלייה</li>
          </ul>
          <a href="tel:0508249824" class="btn btn-dark">לבדוק התאמה</a>
        </div>
      </div>
      <p class="lp-note">המחיר נקבע לפי כמות העמודים, רמת העיצוב והחיבורים למערכות.
      בשיחת אבחון של רבע שעה תדע מספר מדויק - <a href="blog-kama-ole-livnot-atar.html" style="color:var(--purple);font-weight:700">או תקרא קודם את המדריך המלא למחירים</a>.</p>
    </div>
  </section>

  <!-- VS LANDING PAGE -->
  <section class="sec">
    <div class="wrap narrow">
      <div class="s-head center reveal">
        <span class="eyebrow">שאלה שחוזרת</span>
        <h2>אתר תדמית או דף נחיתה?</h2>
        <p>שני דברים שונים לחלוטין. רוב העסקים צריכים את שניהם - אבל לא באותו שלב.</p>
      </div>
      <div class="lp-vs reveal">
        <table>
          <thead>
            <tr><th>&nbsp;</th><th>אתר תדמית</th><th>דף נחיתה</th></tr>
          </thead>
          <tbody>
            <tr><th>המטרה</th><td>לבסס אמון ולסגור פנייה</td><td>להשאיר פרטים עכשיו</td></tr>
            <tr><th>מבנה</th><td>5-10 עמודים</td><td>עמוד אחד</td></tr>
            <tr><th>מאיפה מגיעים</th><td>גוגל, המלצות, כרטיס ביקור</td><td>קמפיין ממומן</td></tr>
            <tr><th>קידום אורגני</th><td>כן, זה עיקר הכוח שלו</td><td>כמעט לא</td></tr>
            <tr><th>אורך חיים</th><td>שנים</td><td>לכל קמפיין בנפרד</td></tr>
            <tr><th>מחיר</th><td>6,000-18,000 ₪</td><td>2,000-7,000 ₪</td></tr>
          </tbody>
        </table>
      </div>
      <p class="lp-note">צריך דווקא דף נחיתה? <a href="service-landing-pages.html" style="color:var(--purple);font-weight:700">יש לזה עמוד נפרד</a>.</p>
    </div>
  </section>

  <!-- PROOF -->
  <section class="sec dark">
    <div class="wrap">
      <div class="s-head center reveal">
        <span class="eyebrow on-dark">תיק עבודות</span>
        <h2>אתרי תדמית חיים שבניתי</h2>
        <p style="color:rgba(255,255,255,.62)">העבירו עכבר כדי לגלול בתוך האתר. אלה אתרים אמיתיים באוויר, לא הדמיות.</p>
      </div>
      <div class="lp-wall">
%(wall)s
      </div>
      <div class="row" style="justify-content:center;margin-top:36px">
        <a href="index.html?r=portfolio" class="btn btn-white">לתיק העבודות המלא</a>
      </div>
    </div>
  </section>

  <!-- PROCESS -->
  <section class="sec process-sec">
    <div class="wrap">
      <div class="s-head center reveal">
        <span class="eyebrow">איך זה עובד</span>
        <h2>תהליך בניית אתר תדמית - 5 שלבים</h2>
        <p>מהשיחה הראשונה ועד שהאתר באוויר. בלי הפתעות ובלי "נעדכן בהמשך".</p>
      </div>
      <div class="timeline">
%(steps)s
      </div>
    </div>
  </section>

  <!-- TESTIMONIALS -->
  <section class="sec surface testi-sec">
    <div class="wrap">
      <div class="s-head center reveal">
        <span class="eyebrow">מה אומרים</span>
        <h2>5.0 מתוך 20 ביקורות בגוגל</h2>
      </div>
      <div class="testi-grid">
        <div class="testi-card featured reveal">
          <div class="tc-top">
            <span class="tc-avatar">א</span>
            <div><span class="tc-name">אלדד לוי</span><span class="tc-role">שמאי מקרקעין</span></div>
          </div>
          %(stars)s
          <p class="tc-text">משה עשה לי מיתוג מלא, צילום מקצועי, <strong>אתר וורדפרס מקצועי</strong>,
          ערוץ יוטיוב וליווי שיווקי שוטף. הולך איתי כבר שנתיים.
          ממליץ לכל בעל עסק שרציני לגבי הנוכחות שלו ברשת.</p>
        </div>
        <div class="testi-card reveal">
          <div class="tc-top">
            <span class="tc-avatar">ע</span>
            <div><span class="tc-name">עידן תמר</span><span class="tc-role">סוכן ביטוח</span></div>
          </div>
          %(stars)s
          <p class="tc-text">קיבלתי לוגו, ניירת עסקית, כרטיסי ביקור, חתימת מייל,
          <strong>אתר ברמה הכי גבוהה עם תוכן</strong>, דף פייסבוק וליווי שיווקי שוטף. מקצוען אמיתי.</p>
        </div>
        <div class="testi-card reveal">
          <div class="tc-top">
            <span class="tc-avatar">א</span>
            <div><span class="tc-name">אמיר ברכה</span><span class="tc-role">בעל עסק</span></div>
          </div>
          %(stars)s
          <p class="tc-text">משה בנה לי מיתוג מלא, צילום מקצועי, וידאו, <strong>אתר וורדפרס</strong>
          וערוץ יוטיוב. כל מי שנכנס לאתר מתקשר.</p>
        </div>
      </div>
      <div class="row" style="justify-content:center;margin-top:32px">
        <a href="testimonials.html" class="btn btn-dark">לכל ההמלצות</a>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section class="sec">
    <div class="wrap">
      <div class="s-head center reveal">
        <span class="eyebrow">שאלות ותשובות</span>
        <h2>מה שואלים אותי על אתר תדמית</h2>
      </div>
      <div class="faq-list reveal">
%(faq)s
      </div>
    </div>
  </section>

  <!-- FINAL CTA -->
  <section class="mid-cta-sec">
    <div class="wrap">
      <div class="mid-cta reveal">
        <h2>נדבר רבע שעה?</h2>
        <p>בלי מצגת ובלי לחץ. תספר לי מה העסק עושה, ואני אגיד לך בדיוק מה אתה צריך,
        כמה זה עולה וכמה זמן זה לוקח. גם אם התשובה היא שאתה עוד לא צריך אתר.</p>
        <div class="row">
          %(phone)s
          <a href="https://wa.me/972508249824?text=%%D7%%94%%D7%%99%%D7%%99%%2C%%20%%D7%%9E%%D7%%A2%%D7%%95%%D7%%A0%%D7%%99%%D7%%99%%D7%%9F%%20%%D7%%91%%D7%%90%%D7%%AA%%D7%%A8%%20%%D7%%AA%%D7%%93%%D7%%9E%%D7%%99%%D7%%AA"
             target="_blank" rel="noopener" class="btn btn-dark">
            <svg viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 0 1 8.413 3.488 11.82 11.82 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24z"/></svg>
            וואטסאפ
          </a>
        </div>
      </div>
    </div>
  </section>

""" % {"phone": PHONE_BTN, "phone2": PHONE_BTN, "wa": WA_BTN, "stars": STARS,
       "inc": inc, "wall": wall, "steps": steps, "faq": faq_html}
