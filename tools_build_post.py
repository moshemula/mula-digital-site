# -*- coding: utf-8 -*-
"""Assembles blog-lakoach-lekol-hachaim.html from an existing post, so the nav,
footer, CSS and scripts stay identical across the blog.

Run from the site root:  python tools_build_post.py
"""
import io, re

SRC = "blog-tochen-shemavi-lekochot.html"
DST = "blog-lakoach-lekol-hachaim.html"
BODY = (r"C:/Users/moshe/AppData/Local/Temp/claude/"
        r"C--Users-moshe-Downloads----------------/"
        r"7bdb3e72-4d43-4e07-8399-e6b550a27d29/scratchpad/post-body.html")

SLUG = "blog-lakoach-lekol-hachaim.html"
# No stock photo fits an article whose point is that there is a real person
# behind the business, so the cover is him. Built by tools_build_cover.py.
IMG = "https://muladigital.co.il/assets/cover-lakoach.jpg"
IMG_REL = "assets/cover-lakoach.jpg"
TITLE = "לקוח לכל החיים - למה עצמאי מפסיד בלי אתר | Mula Digital"
OGT = "לקוח לכל החיים - למה עצמאי עם לקוחות לטווח ארוך מפסיד הכי הרבה בלי אתר"
DESC = ("סוכן ביטוח, רואה חשבון, יועץ מס ועורך דין - לקוח אצלם נשאר שנים, ולכן ההחזר על "
        "לקוח מהרשת הוא מהגבוהים בשוק. אז למה כל כך הרבה מהם עדיין בלי אתר?")
OGD = ("החשבון האמיתי של לקוח לטווח ארוך, מה עולות השנים שגוגל לא ידע עליכם, "
       "ומתי אתר הוא לא הדבר הראשון שצריך. מניסיון של משה מולה.")

FAQ = [
 ("כמה שווה לקוח של סוכן ביטוח לאורך זמן?",
  "אין מספר אחד, זה תלוי בתמהיל הפוליסות ובוותק. אבל העיקרון קבוע: לקוח של סוכן ביטוח לא נמדד בעסקה אחת אלא בשנים. פוליסה, פנסיה, בריאות, משכנתה, הילדים, וחידוש שקורה כל שנה מחדש. הדרך הנכונה לחשב היא לקחת את ההכנסה השנתית מלקוח ממוצע ולהכפיל במספר השנים שלקוח נשאר אצלכם בפועל."),
 ("כמה עולה אתר לרואה חשבון, ליועץ מס או לעורך דין?",
  "אתר תדמית עם דף ראשי, שלושה עד ארבעה דפי שירות וצור קשר עולה 5,000 עד 8,000 שקל. אתר עם דפי שירות לכל תחום, דפי אזורים, בלוג ומערכת ניהול עולה 8,000 עד 12,000 שקל. השאלה החשובה יותר היא לא כמה זה עולה אלא כמה לקוחות צריך כדי לכסות את זה, ובמקצועות האלה זה בדרך כלל מספר קטן מאוד."),
 ("כמה זמן לוקח לאתר להתחיל להביא פניות?",
  "אתר חדש לא מדורג ביום. פרופיל Google Business מסודר יכול להשפיע כבר בשבועות הראשונים, אבל תנועה אורגנית יציבה מדפי שירות ותוכן לוקחת בדרך כלל ארבעה עד שישה חודשים, ולפעמים יותר לאתר חדש לגמרי. מי שמבטיח לכם תוצאות מהחודש הראשון שווה להיזהר ממנו."),
 ("יש לי מספיק לקוחות. למה שאשקיע באתר?",
  "זו סיבה לגיטימית לדחות, אבל שווה לשאול שתי שאלות. הראשונה: אם היו לכם עוד פניות, הייתם בוחרים לקוחות טובים יותר? השנייה: מה קורה בשנה שבה לקוח גדול עוזב? נוכחות דיגיטלית היא לא רק ברז לידים, היא גם ביטוח לעסק עצמו."),
 ("יש לי פרופיל בפייסבוק ובגוגל. למה צריך אתר?",
  "פרופיל ברשתות הוא שטח שאתם שוכרים, לא שטח שאתם מחזיקים. הוא לא מאפשר דפי שירות מפורטים, בלוג שמושך תנועה אורגנית, חיבור למערכת CRM או אזור אישי ללקוחות. וכשמישהו שכבר שמע עליכם מחפש אתכם בשם, מה שהוא מוצא קובע אם הוא מרים טלפון."),
 ("בניתי אתר פעם והוא לא הביא כלום. מה יהיה שונה?",
  "ברוב המקרים שראיתי, האתר הקודם היה עמוד תדמית בלי דפי שירות ממוקדים, בלי תוכן, ובלי אף אחד שליווה אחרי העלייה לאוויר. אתר לבד לא מביא לקוחות. מה שמביא הוא דפי שירות שמדורגים, פרופיל עסקי מחובר, ותוכן שנבנה לאורך זמן. לפני שסוגרים, שווה לשאול בדיוק מה קורה אחרי שהאתר עולה."),
 ("מה זה אזור אישי ולמה סוכן ביטוח צריך אותו?",
  "אזור אישי הוא המקום שבו לקוח קיים נכנס לראות את מה ששייך לו: פוליסות, מסמכים, סטטוס. הוא לא נועד להביא לקוחות חדשים אלא לחסוך שיחות טלפון ולתת ללקוח תחושה שיש מאחורי הכל מערכת מסודרת. יחד עם חיבור למערכת CRM, זה מה שהופך את האתר ממודעה למשרד."),
 ("אני עצמאי כבר עשרים שנה בלי אתר. לא מאוחר מדי?",
  "להפך. ותק הוא נכס שאי אפשר לקנות, והוא בדיוק מה שאתר טוב יודע להציג: שנים בתחום, לקוחות שנשארו, מקרים שטיפלתם בהם. אתר חדש של איש מקצוע ותיק מתחיל מנקודה חזקה בהרבה מאתר של מישהו שהתחיל אתמול. השנים שעברו לא חוזרות, אבל הן גם לא סיבה להוסיף עליהן עוד אחת."),
]

src = io.open(SRC, encoding="utf-8").read()
body = io.open(BODY, encoding="utf-8").read()

# the lead form is reused verbatim, retargeted at this article
lf = src[src.index("    <!-- LEAD FORM -->"):src.index("    <!-- S6 -->")]
lf = (lf.replace("רוצה תוכן שמביא לקוחות <span>מגוגל?</span>",
                 "רוצה לדעת כמה לקוח אחד שווה לכם <span>לאורך זמן?</span>")
        .replace("ספר לנו על העסק - נחזור עם הערכה מקצועית ותוכנית תוכן מותאמת. בלי התחייבות.",
                 "ספרו לנו על העסק ונחזור עם הערכה מקצועית ותוכנית מותאמת. בלי התחייבות.")
        .replace("ליד מהמאמר: תוכן שמביא לקוחות", "ליד מהמאמר: לקוח לכל החיים")
        .replace("blog-tochen-shemavi-lekochot.html#thanks", SLUG + "#thanks")
        .replace("ספר בקצרה על העסק ומה אתה רוצה לקדם (לא חובה)",
                 "ספרו בקצרה על התחום ועל מה שחשוב לכם (לא חובה)")
        .replace("שלחו לי תוכנית תוכן", "דברו איתי"))
body = body.replace("    <!-- LEAD FORM -->\n", lf)

faq_html = ('    <!-- FAQ -->\n    <h2 id="faq">שאלות נפוצות</h2>\n    <div class="faq">\n'
    + "".join('      <details>\n        <summary>%s</summary>\n        <p>%s</p>\n      </details>\n' % q
              for q in FAQ)
    + "    </div>\n\n")

ilinks = '''    <!-- Internal links -->
    <div class="ilinks">
      <h3>עמודים ומאמרים שיעזרו לך:</h3>
      <div class="tags">
        <a href="service-web-build-insurance.html">בניית אתר לסוכן ביטוח</a>
        <a href="service-web-build-accountant.html">בניית אתר לרואה חשבון</a>
        <a href="service-web-build-lawyer.html">בניית אתר לעורך דין</a>
        <a href="blog-atar-yafe-lo-maspik.html">למה אתר יפה לא מספיק</a>
        <a href="blog-google-business-profile.html">גוגל לעסק שלי - המדריך המלא</a>
        <a href="blog-kama-ole-livnot-atar.html">כמה עולה לבנות אתר</a>
      </div>
    </div>

'''

tags = '''    <div class="article-tags">
      <span class="label">תגיות:</span>
      <span class="tag">בניית אתרים</span>
      <span class="tag">סוכני ביטוח</span>
      <span class="tag">רואי חשבון</span>
      <span class="tag">עורכי דין</span>
      <span class="tag">עצמאים</span>
      <span class="tag">נוכחות דיגיטלית</span>
      <span class="tag">ROI</span>
    </div>

'''

out = src

# --- head SEO ---
out = out.replace("<title>איך לכתוב תוכן שמביא לקוחות מגוגל? מדריך 2026 | Mula Digital</title>",
                  "<title>%s</title>" % TITLE)
out = re.sub(r'<meta name="description" content="[^"]*" />',
             '<meta name="description" content="%s" />' % DESC, out, count=1)
out = out.replace("blog-tochen-shemavi-lekochot.html", SLUG)
out = out.replace("https://images.unsplash.com/photo-1455390582262-044cdead277a?w=1200&q=70&auto=format&fit=crop", IMG)
for tag in ("og:title", "twitter:title"):
    out = out.replace('<meta property="%s" content="איך לכתוב תוכן שמביא לקוחות מגוגל? המדריך המלא 2026" />' % tag,
                      '<meta property="%s" content="%s" />' % (tag, OGT))
    out = out.replace('<meta name="%s" content="איך לכתוב תוכן שמביא לקוחות מגוגל? המדריך המלא 2026" />' % tag,
                      '<meta name="%s" content="%s" />' % (tag, OGT))
out = re.sub(r'<meta property="og:description" content="[^"]*" />',
             '<meta property="og:description" content="%s" />' % OGD, out, count=1)
out = re.sub(r'<meta name="twitter:description" content="[^"]*" />',
             '<meta name="twitter:description" content="החשבון האמיתי של לקוח לטווח ארוך, ומה עולות השנים שגוגל לא ידע עליכם." />',
             out, count=1)

# --- BlogPosting schema ---
out = out.replace('"headline":"איך לכתוב תוכן שמביא לקוחות מגוגל? המדריך המלא לבעלי עסקים 2026"',
                  '"headline":"%s"' % OGT)
out = re.sub(r'"description":"מדריך עומק מהשטח[^"]*"', '"description":"%s"' % OGD, out, count=1)
out = out.replace('"wordCount":5200,"datePublished":"2026-08-06","dateModified":"2026-08-06"',
                  '"wordCount":3600,"datePublished":"2026-08-31","dateModified":"2026-08-31"')

# --- FAQPage schema ---
faq_json = ",\n    ".join(
    '{"@type":"Question","name":"%s","acceptedAnswer":{"@type":"Answer","text":"%s"}}' % (q, a)
    for q, a in FAQ)
a = out.index('"@context":"https://schema.org","@type":"FAQPage","mainEntity":[')
b = out.index("]", a)   # the array closes as "]}" with no newline after it
out = (out[:a] + '"@context":"https://schema.org","@type":"FAQPage","mainEntity":[\n    '
       + faq_json + "\n  " + out[b:])

# --- hero ---
out = out.replace("<a href=\"index.html?r=blog\">בלוג</a> · תוכן שמביא לקוחות</div>",
                  "<a href=\"index.html?r=blog\">בלוג</a> · לקוח לכל החיים</div>")
out = out.replace("      תוכן ו-SEO\n", "      אסטרטגיה\n")
out = out.replace('<h1 class="a-title-clean">איך לכתוב תוכן שמביא לקוחות מגוגל?<br>המדריך המלא לבעלי עסקים</h1>',
                  '<h1 class="a-title-clean">לקוח לכל החיים<br>למה עצמאי עם לקוחות לטווח ארוך מפסיד הכי הרבה בלי אתר</h1>')
out = out.replace("<span>22 דק׳ קריאה</span>", "<span>14 דק׳ קריאה</span>")
out = out.replace("<span>מדריך עומק</span>", "<span>טור אישי</span>")
out = out.replace('alt="כתיבת תוכן שמביא לקוחות מגוגל - מדריך מעשי"',
                  'alt="משה מולה - למה לקוח לטווח ארוך שווה השקעה באתר"')
# the social tags need the absolute url; the inline image is better off relative
out = out.replace('<div class="feat-img">\n      <img src="%s"' % IMG,
                  '<div class="feat-img">\n      <img src="%s"' % IMG_REL)

# --- body ---
start = out.index('  <article class="wrap">\n') + len('  <article class="wrap">\n')
end = out.index("    <!-- Internal links -->")
out = out[:start] + "\n" + body + "\n" + out[end:]

# --- internal links / faq / tags ---
a = out.index("    <!-- Internal links -->")
b = out.index('    <div class="article-tags">')
out = out[:a] + ilinks + faq_html + out[b:]
a = out.index('    <div class="article-tags">')
b = out.index("    <!-- author bio -->")
out = out[:a] + tags + out[b:]

# --- bio + closing CTA ---
out = out.replace("כל מה שכתוב פה מגיע מניסיון ישיר בבניית תוכן שמביא לקוחות מגוגל - מבעלי מקצוע עצמאיים ועד חברות B2B.",
                  "עובד כבר שנים עם סוכני ביטוח, רואי חשבון, יועצי מס ועורכי דין, וכל מה שכתוב פה מגיע מהשיחות האלה.")
out = out.replace("<h2>רוצה תוכן שעובד בשבילך <span>24/7?</span></h2>",
                  "<h2>לא חבל על עוד שנה <span>בלי שיידעו עליך?</span></h2>")
out = out.replace('<p class="cta-sub">נקבע שיחה קצרה, נבין מה העסק שלך צריך ונבנה תוכנית תוכן שמביאה לקוחות מגוגל.</p>',
                  '<p class="cta-sub">נקבע שיחה קצרה, נעשה את החשבון על העסק שלך, ונראה אם זה בכלל משתלם לך.</p>')

# the closing block already carries the ask - the bio should just be a bio
out = out.replace('<a class="lead" href="contact.html">קבעו שיחת אבחון</a>\n          ', '')

io.open(DST, "w", encoding="utf-8").write(out)
article = out[out.index("<article"):out.index("</article>")]
print("wrote %s | %d words" % (DST, len(re.sub(r"<[^>]+>", " ", article).split())))
