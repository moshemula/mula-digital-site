# -*- coding: utf-8 -*-
"""
מפרסם פוסט בגוגל לעסק שלי (Google Business Profile) עבור דף חדש באתר.

הרצה:  python tools_gbp_post.py <file.html> [file2.html ...]

משתני סביבה נדרשים (ב-GitHub הם Secrets):
  GBP_CLIENT_ID       מזהה הלקוח מ-Google Cloud Console
  GBP_CLIENT_SECRET   הסוד של אותו לקוח
  GBP_REFRESH_TOKEN   refresh token עם ההרשאה business.manage
  GBP_ACCOUNT_ID      מספר החשבון (accounts/123... - רק המספר)
  GBP_LOCATION_ID     מספר המיקום  (locations/456... - רק המספר)

אם אחד מהם חסר - הסקריפט יוצא בשקט בהצלחה, כדי שלא יפיל
כל דחיפה לפני שהחיבור הוקם.
"""
import io, os, re, sys, json, html
import urllib.request, urllib.parse, urllib.error

TOKEN_URL = "https://oauth2.googleapis.com/token"
POST_URL = "https://mybusiness.googleapis.com/v4/accounts/{acc}/locations/{loc}/localPosts"
SUMMARY_MAX = 1500          # מגבלת גוגל
DRY = os.environ.get("GBP_DRY_RUN") == "1"

REQUIRED = ["GBP_CLIENT_ID", "GBP_CLIENT_SECRET", "GBP_REFRESH_TOKEN",
            "GBP_ACCOUNT_ID", "GBP_LOCATION_ID"]


def grab(pattern, text, group=1):
    m = re.search(pattern, text, re.S | re.I)
    return m.group(group).strip() if m else ""


def clean(s):
    return re.sub(r"\s+", " ", html.unescape(s)).strip()


def read_page(path):
    """מוציא מהדף את מה שצריך לפוסט. מחזיר None אם הדף לא מיועד לפרסום."""
    t = io.open(path, encoding="utf-8", errors="replace").read()

    has_date = bool(re.search(r'"datePublished"\s*:\s*"[0-9]{4}-', t)) or \
               bool(re.search(r'<meta\s+name=["\']pubdate["\']', t, re.I))
    if not has_date:
        return None

    title = clean(grab(r"<title>(.*?)</title>", t))
    title = re.sub(r"\s*\|\s*Mula Digital\s*$", "", title)
    title = re.split(r"\s*\|\s*", title)[0].strip()

    desc = clean(grab(r'<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']\s*/?>', t))
    link = grab(r'<link\s+rel=["\']canonical["\']\s+href=["\'](.*?)["\']', t)
    img = grab(r'<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']', t)

    if not (title and desc and link):
        return None
    return {"title": title, "desc": desc, "link": link, "img": img}


def access_token():
    body = urllib.parse.urlencode({
        "client_id": os.environ["GBP_CLIENT_ID"],
        "client_secret": os.environ["GBP_CLIENT_SECRET"],
        "refresh_token": os.environ["GBP_REFRESH_TOKEN"],
        "grant_type": "refresh_token",
    }).encode()
    req = urllib.request.Request(TOKEN_URL, data=body,
                                 headers={"Content-Type": "application/x-www-form-urlencoded"})
    with urllib.request.urlopen(req, timeout=30) as r:
        return json.loads(r.read().decode())["access_token"]


def build_summary(page):
    """הטקסט שמופיע בפוסט. כותרת, שורה ריקה, ואז התיאור."""
    s = u"%s\n\n%s" % (page["title"], page["desc"])
    if len(s) > SUMMARY_MAX:
        s = s[:SUMMARY_MAX - 1].rstrip() + u"…"
    return s


def post(page, token):
    payload = {
        "languageCode": "he",
        "summary": build_summary(page),
        "topicType": "STANDARD",
        "callToAction": {"actionType": "LEARN_MORE", "url": page["link"]},
    }
    if page["img"]:
        payload["media"] = [{"mediaFormat": "PHOTO", "sourceUrl": page["img"]}]

    url = POST_URL.format(acc=os.environ["GBP_ACCOUNT_ID"],
                          loc=os.environ["GBP_LOCATION_ID"])
    req = urllib.request.Request(
        url, data=json.dumps(payload, ensure_ascii=False).encode("utf-8"),
        headers={"Authorization": "Bearer " + token,
                 "Content-Type": "application/json; charset=utf-8"})
    with urllib.request.urlopen(req, timeout=40) as r:
        return json.loads(r.read().decode())


def main(argv):
    files = [f for f in argv if f.lower().endswith(".html") and os.path.exists(f)]
    if not files:
        print("no html files to post"); return 0

    pages = []
    for f in files:
        p = read_page(f)
        if p:
            pages.append(p)
        else:
            print("skip  %s  (no publish date / missing title, description or canonical)" % f)
    if not pages:
        print("nothing to post"); return 0

    missing = [k for k in REQUIRED if not os.environ.get(k)]
    if missing:
        print("GBP not connected yet - missing: %s" % ", ".join(missing))
        print("would have posted: %s" % ", ".join(p["title"] for p in pages))
        return 0

    if DRY:
        for p in pages:
            print("DRY RUN - would post:\n%s\n-> %s\n" % (build_summary(p), p["link"]))
        return 0

    token = access_token()
    failed = 0
    for p in pages:
        try:
            res = post(p, token)
            print("posted: %s\n   %s" % (p["title"], res.get("searchUrl") or res.get("name", "")))
        except urllib.error.HTTPError as e:
            failed += 1
            print("FAILED: %s\n   HTTP %s %s" % (p["title"], e.code,
                                                 e.read().decode("utf-8", "replace")[:400]))
        except Exception as e:
            failed += 1
            print("FAILED: %s\n   %s" % (p["title"], e))
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
