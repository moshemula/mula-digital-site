# Mula Digital — אתר שיווקי (עברית / RTL)

אתר סטטי (HTML/CSS/JS, ללא build) ל-Mula Digital — סוכנות דיגיטל ומערכות צמיחה לעסקים.
בעלים: משה מולה.

> 🤖 אם אתה עובד עם Claude Code — קרא קודם את `CLAUDE.md`. שם כל ההקשר, הכללים, והמשימות הפתוחות.

---

## הרצה מקומית

האתר משתמש ב-SPA router, אז צריך להריץ דרך שרת (לא לפתוח את הקובץ ישירות בדפדפן):

```bash
# אפשרות 1 — Python
python3 -m http.server 8000

# אפשרות 2 — Node
npx serve
```

פתח בדפדפן: `http://localhost:8000`

---

## העלאה (Deploy)

**Netlify Drop (הכי פשוט):**
1. היכנס ל-[app.netlify.com/drop](https://app.netlify.com/drop)
2. גרור את **כל התיקייה** (לא רק `index.html` — חובה לכלול את `assets/`)
3. זהו. האתר עלה.

**או דרך Git:** חבר את הריפו ל-Netlify.
אין build command. תיקיית הפרסום (publish) = שורש הפרויקט.
קובץ `_redirects` מטפל ב-SPA fallback וה-`netlify.toml` מוכן.

---

## מבנה

```
index.html                       בית + תיק עבודות + בלוג (SPA)
about.html                       עמוד אודות (דגל)
blog-atar-yafe-lo-maspik.html    מאמר #1 (תבנית לכל המאמרים)
assets/                          לוגואים, תמונות, אייקונים, 32 לוגואי לקוחות (assets/logos/)
_redirects, netlify.toml         הגדרות Netlify
robots.txt, sitemap.xml          SEO
site.webmanifest                 PWA
CLAUDE.md                        הנחיות מלאות ל-Claude Code (קרא את זה!)
mula-storyboard.md               תיעוד פנימי (לא חלק מהאתר)
```

---

## מה כבר קיים

- ✅ דף בית מלא
- ✅ דף אודות (עמוד דגל)
- ✅ מאמר ראשון (תבנית זהב: SEO + GEO + schema + כלים אינטראקטיביים)
- ✅ 32 לוגואי לקוחות, פוטר מלא בכל דף, אייקוני SVG

## מה נשאר (ראה CLAUDE.md לפרטים)

- [ ] דף צור קשר (טופס + מפה + פרטים)
- [ ] 5 מאמרים נוספים
- [ ] דפי שירות

---

## פרטי קשר (במקרה שצריך)

טלפון 050-824-9824 · Moshe@muladigital.co.il · אלי הורוביץ 27, רחובות
