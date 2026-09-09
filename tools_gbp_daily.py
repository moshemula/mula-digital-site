# -*- coding: utf-8 -*-
"""
פוסט יומי לגוגל לעסק שלי.

לוקח את הפריט הבא מ-gbp-queue.json לפי המונה, מפרסם אותו,
ומקדם את המונה. כשהמאגר נגמר הוא חוזר להתחלה.

הרצה:  python tools_gbp_daily.py
בדיקה: GBP_DRY_RUN=1 python tools_gbp_daily.py

משתני סביבה זהים ל-tools_gbp_post.py. אם חסרים - יוצא בשקט
בהצלחה ומדפיס מה היה מתפרסם, בלי לקדם את המונה.
"""
import io, os, json
import urllib.error

import tools_gbp_post as gbp

SITE = "https://muladigital.co.il"
QUEUE = "gbp-queue.json"


def main():
    here = os.path.dirname(os.path.abspath(__file__))
    os.chdir(here)

    data = json.load(io.open(QUEUE, encoding="utf-8"))
    posts = data.get("posts", [])
    if not posts:
        print("המאגר ריק"); return 0

    idx = int(data.get("cursor", 0)) % len(posts)
    item = posts[idx]

    url = item.get("url") or ""
    link = (SITE + url) if url.startswith("/") else (url or SITE + "/")

    page = {"title": item["t"], "desc": item["b"], "link": link,
            "img": SITE + "/assets/moshe-white.jpg",
            "cta": item.get("cta") or "LEARN_MORE"}

    print(u"פריט %d מתוך %d: %s" % (idx + 1, len(posts), item["t"]))

    missing = [k for k in gbp.REQUIRED if not os.environ.get(k)]
    if missing:
        print("GBP not connected yet - missing: %s" % ", ".join(missing))
        print(gbp.build_summary(page))
        print("-> %s" % link)
        return 0          # לא מקדמים את המונה כשלא פורסם באמת

    if gbp.DRY:
        print("DRY RUN:")
        print(gbp.build_summary(page))
        print("-> %s" % link)
        return 0

    try:
        res = gbp.post(page, gbp.access_token())
        print("פורסם: %s" % (res.get("searchUrl") or res.get("name", "")))
    except urllib.error.HTTPError as e:
        print("FAILED HTTP %s: %s" % (e.code, e.read().decode("utf-8", "replace")[:400]))
        return 1
    except Exception as e:
        print("FAILED: %s" % e)
        return 1

    data["cursor"] = (idx + 1) % len(posts)
    io.open(QUEUE, "w", encoding="utf-8").write(
        json.dumps(data, ensure_ascii=False, indent=2) + "\n")
    print("המונה קודם ל-%d" % data["cursor"])
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
