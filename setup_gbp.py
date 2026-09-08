# -*- coding: utf-8 -*-
"""
עוזר חיבור לגוגל לעסק שלי.

מה זה עושה: פותח לך את גוגל בדפדפן, מבקש אישור, ובסוף מדפיס
את חמשת הערכים שצריך להדביק ב-GitHub. אתה לא צריך להבין OAuth.

איך מריצים:
    python setup_gbp.py

לפני ההרצה צריך שיהיו לך שני דברים מ-Google Cloud Console:
מזהה לקוח (Client ID) וסוד (Client Secret) מסוג "Desktop app".
הסקריפט יבקש אותם.
"""
import io, os, sys, json, base64, hashlib, secrets as pysecrets
import urllib.request, urllib.parse, urllib.error
import webbrowser, threading
from http.server import BaseHTTPRequestHandler, HTTPServer

SCOPE = "https://www.googleapis.com/auth/business.manage"
AUTH = "https://accounts.google.com/o/oauth2/v2/auth"
TOKEN = "https://oauth2.googleapis.com/token"
ACCOUNTS = "https://mybusinessaccountmanagement.googleapis.com/v1/accounts"
LOCATIONS = ("https://mybusinessbusinessinformation.googleapis.com/v1/"
             "accounts/{acc}/locations?readMask=name,title&pageSize=100")
PORT = 8731
REDIRECT = "http://localhost:%d" % PORT

_code = {}


class Catch(BaseHTTPRequestHandler):
    def do_GET(self):
        q = urllib.parse.parse_qs(urllib.parse.urlparse(self.path).query)
        _code["code"] = (q.get("code") or [None])[0]
        _code["error"] = (q.get("error") or [None])[0]
        body = (u"<html dir='rtl'><body style='font-family:sans-serif;"
                u"text-align:center;padding-top:80px'>"
                u"<h2>%s</h2><p>אפשר לסגור את החלון ולחזור לטרמינל.</p></body></html>"
                % (u"מחובר ✓" if _code.get("code") else u"ההרשאה בוטלה"))
        self.send_response(200)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.end_headers()
        self.wfile.write(body.encode("utf-8"))

    def log_message(self, *a):
        pass


def get_json(url, token):
    req = urllib.request.Request(url, headers={"Authorization": "Bearer " + token})
    with urllib.request.urlopen(req, timeout=30) as r:
        return json.loads(r.read().decode())


def ask(label):
    v = ""
    while not v:
        v = input(label).strip()
    return v


def main():
    print(u"\n=== חיבור גוגל לעסק שלי ל-GitHub ===\n")
    print(u"צריך Client ID ו-Client Secret מסוג Desktop app")
    print(u"מ-Google Cloud Console -> APIs & Services -> Credentials.\n")

    cid = ask(u"Client ID: ")
    csec = ask(u"Client Secret: ")

    # PKCE - כדי שהזרימה תהיה בטוחה גם בלי שרת
    verifier = base64.urlsafe_b64encode(pysecrets.token_bytes(40)).decode().rstrip("=")
    challenge = base64.urlsafe_b64encode(
        hashlib.sha256(verifier.encode()).digest()).decode().rstrip("=")

    url = AUTH + "?" + urllib.parse.urlencode({
        "client_id": cid, "redirect_uri": REDIRECT, "response_type": "code",
        "scope": SCOPE, "access_type": "offline", "prompt": "consent",
        "code_challenge": challenge, "code_challenge_method": "S256",
    })

    srv = HTTPServer(("localhost", PORT), Catch)
    threading.Thread(target=srv.handle_request, daemon=True).start()

    print(u"\nנפתח דפדפן. תאשר עם החשבון שמנהל את הפרופיל העסקי.")
    print(u"אם לא נפתח, תפתח ידנית את הכתובת הזו:\n%s\n" % url)
    try:
        webbrowser.open(url)
    except Exception:
        pass

    print(u"ממתין לאישור...")
    for _ in range(600):
        if _code:
            break
        import time; time.sleep(0.5)
    srv.server_close()

    if not _code.get("code"):
        print(u"\nלא התקבל אישור. %s" % (_code.get("error") or u"נסה שוב."))
        return 1

    body = urllib.parse.urlencode({
        "client_id": cid, "client_secret": csec, "code": _code["code"],
        "code_verifier": verifier, "grant_type": "authorization_code",
        "redirect_uri": REDIRECT,
    }).encode()
    try:
        req = urllib.request.Request(
            TOKEN, data=body,
            headers={"Content-Type": "application/x-www-form-urlencoded"})
        with urllib.request.urlopen(req, timeout=30) as r:
            tok = json.loads(r.read().decode())
    except urllib.error.HTTPError as e:
        print(u"\nגוגל דחתה את הבקשה:\n%s" % e.read().decode("utf-8", "replace")[:400])
        return 1

    refresh = tok.get("refresh_token")
    access = tok.get("access_token")
    if not refresh:
        print(u"\nלא התקבל refresh token. תנתק את האפליקציה ב-"
              u"myaccount.google.com/permissions ותריץ שוב.")
        return 1

    acc_id = loc_id = ""
    try:
        accs = get_json(ACCOUNTS, access).get("accounts", [])
        if accs:
            acc_id = accs[0]["name"].split("/")[-1]
            print(u"\nנמצא חשבון: %s" % accs[0].get("accountName", acc_id))
            locs = get_json(LOCATIONS.format(acc=acc_id), access).get("locations", [])
            if len(locs) == 1:
                loc_id = locs[0]["name"].split("/")[-1]
                print(u"נמצא מיקום: %s" % locs[0].get("title", loc_id))
            elif locs:
                print(u"\nיש כמה מיקומים. בחר אחד:")
                for i, l in enumerate(locs, 1):
                    print(u"  %d. %s  (%s)" % (i, l.get("title", "?"),
                                               l["name"].split("/")[-1]))
                n = input(u"מספר: ").strip()
                if n.isdigit() and 1 <= int(n) <= len(locs):
                    loc_id = locs[int(n) - 1]["name"].split("/")[-1]
    except urllib.error.HTTPError as e:
        print(u"\nההרשאה עבדה, אבל אין עדיין גישה ל-API (HTTP %d)." % e.code)
        print(u"זה אומר שבקשת הגישה שלך אצל גוגל עוד לא אושרה.")
        print(u"ה-refresh token למטה תקף - שמור אותו, והשלם את שני")
        print(u"המספרים האחרונים אחרי שהאישור יגיע.")

    print(u"\n" + u"=" * 62)
    print(u"להדביק ב-GitHub: Settings -> Secrets and variables -> Actions")
    print(u"=" * 62)
    print(u"GBP_CLIENT_ID       %s" % cid)
    print(u"GBP_CLIENT_SECRET   %s" % csec)
    print(u"GBP_REFRESH_TOKEN   %s" % refresh)
    print(u"GBP_ACCOUNT_ID      %s" % (acc_id or u"<עוד לא זמין>"))
    print(u"GBP_LOCATION_ID     %s" % (loc_id or u"<עוד לא זמין>"))
    print(u"=" * 62)

    out = "gbp-secrets.txt"
    io.open(out, "w", encoding="utf-8").write(
        u"GBP_CLIENT_ID=%s\nGBP_CLIENT_SECRET=%s\nGBP_REFRESH_TOKEN=%s\n"
        u"GBP_ACCOUNT_ID=%s\nGBP_LOCATION_ID=%s\n"
        % (cid, csec, refresh, acc_id, loc_id))
    print(u"\nנשמר גם לקובץ %s" % out)
    print(u"הקובץ הזה מכיל סיסמאות - למחוק אותו אחרי ההדבקה ב-GitHub.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
