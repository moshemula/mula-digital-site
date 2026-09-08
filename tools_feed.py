# -*- coding: utf-8 -*-
"""
מייצר את feed.xml מתוך קבצי ה-HTML של האתר.

הפיד הוא הצינור שדרכו כל פוסט או דף חדש מגיע לגוגל לעסק שלי:
מערכת תזמון (Publer / Metricool / Zapier / Make) עוקבת אחרי הפיד,
וכשמופיע בו פריט חדש היא מפרסמת אותו כפוסט ב-GBP.

מקור התאריך: "datePublished" בסכמה של הדף, או <meta name="pubdate">.
דף בלי אף אחד מהם פשוט לא נכנס לפיד.

הרצה: python tools_feed.py
"""
import io, os, re, glob, html
from datetime import datetime, timezone, timedelta

SITE = "https://muladigital.co.il"
TITLE = "Mula Digital - שיווק דיגיטלי שמביא לקוחות"
DESC = "מאמרים, מדריכים ועדכונים על בניית אתרים, קידום בגוגל וקמפיינים ממומנים. משה מולה, Mula Digital."
MAX_ITEMS = 40
IL = timezone(timedelta(hours=3))

# דפים שנכנסים לפיד. כל blog-*.html נכנס אוטומטית.
EXTRA_PAGES = ["daf-nechita.html", "atar-tadmit.html"]


def grab(pattern, text, group=1):
    m = re.search(pattern, text, re.S | re.I)
    return m.group(group).strip() if m else ""


def clean(s):
    """מנקה ישויות HTML ורווחים כפולים."""
    s = html.unescape(s)
    return re.sub(r"\s+", " ", s).strip()


def read_page(path):
    t = io.open(path, encoding="utf-8", errors="replace").read()

    date = (grab(r'"datePublished"\s*:\s*"([0-9]{4}-[0-9]{2}-[0-9]{2})', t)
            or grab(r'<meta\s+name=["\']pubdate["\']\s+content=["\']([0-9-]{10})', t))
    if not date:
        return None

    title = clean(grab(r"<title>(.*?)</title>", t))
    # מסירים את הזנב של המותג - ב-GBP הוא רק תופס מקום
    title = re.sub(r"\s*\|\s*Mula Digital\s*$", "", title)
    title = re.split(r"\s*\|\s*", title)[0].strip()

    desc = clean(grab(r'<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']\s*/?>', t))
    link = grab(r'<link\s+rel=["\']canonical["\']\s+href=["\'](.*?)["\']', t) \
        or SITE + "/" + os.path.basename(path)
    img = grab(r'<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']', t)

    if not (title and desc):
        return None

    try:
        dt = datetime.strptime(date, "%Y-%m-%d").replace(hour=10, tzinfo=IL)
    except ValueError:
        return None

    return {"title": title, "desc": desc, "link": link, "img": img,
            "dt": dt, "file": os.path.basename(path)}


def esc(s):
    return (s.replace("&", "&amp;").replace("<", "&lt;")
             .replace(">", "&gt;").replace('"', "&quot;"))


def main():
    here = os.path.dirname(os.path.abspath(__file__))
    os.chdir(here)

    paths = sorted(glob.glob("blog-*.html"))
    for p in EXTRA_PAGES:
        if os.path.exists(p) and p not in paths:
            paths.append(p)

    items, skipped = [], []
    for p in paths:
        it = read_page(p)
        (items.append(it) if it else skipped.append(p))

    items.sort(key=lambda x: x["dt"], reverse=True)
    items = items[:MAX_ITEMS]

    now = datetime.now(IL).strftime("%a, %d %b %Y %H:%M:%S %z")
    out = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" '
        'xmlns:media="http://search.yahoo.com/mrss/">',
        "  <channel>",
        "    <title>%s</title>" % esc(TITLE),
        "    <link>%s/</link>" % SITE,
        "    <description>%s</description>" % esc(DESC),
        "    <language>he-IL</language>",
        "    <lastBuildDate>%s</lastBuildDate>" % now,
        '    <atom:link href="%s/feed.xml" rel="self" type="application/rss+xml" />' % SITE,
        "    <image>",
        "      <url>%s/assets/logo-dark.png</url>" % SITE,
        "      <title>%s</title>" % esc(TITLE),
        "      <link>%s/</link>" % SITE,
        "    </image>",
    ]

    for it in items:
        out += [
            "    <item>",
            "      <title>%s</title>" % esc(it["title"]),
            "      <link>%s</link>" % esc(it["link"]),
            '      <guid isPermaLink="true">%s</guid>' % esc(it["link"]),
            "      <description>%s</description>" % esc(it["desc"]),
            "      <pubDate>%s</pubDate>" % it["dt"].strftime("%a, %d %b %Y %H:%M:%S %z"),
        ]
        if it["img"]:
            out.append('      <enclosure url="%s" type="image/jpeg" />' % esc(it["img"]))
            out.append('      <media:content url="%s" medium="image" />' % esc(it["img"]))
        out.append("    </item>")

    out += ["  </channel>", "</rss>", ""]
    new = "\n".join(out)

    old = io.open("feed.xml", encoding="utf-8").read() if os.path.exists("feed.xml") else ""
    # מתעלמים מ-lastBuildDate כדי לא לייצר קומיט על כל הרצה
    strip = lambda s: re.sub(r"<lastBuildDate>.*?</lastBuildDate>", "", s)
    changed = strip(old) != strip(new)

    if changed:
        io.open("feed.xml", "w", encoding="utf-8").write(new)

    print("feed.xml: %d items%s" % (len(items), "" if changed else " (no change)"))
    for it in items[:5]:
        print("   %s  %s" % (it["dt"].strftime("%Y-%m-%d"), it["title"][:56]))
    if skipped:
        print("skipped (no date / no description): %s" % ", ".join(skipped))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
