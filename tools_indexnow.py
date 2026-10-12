# -*- coding: utf-8 -*-
"""שולח ל-IndexNow (בינג, Yandex, Seznam ועוד) את הדפים שנוספו או השתנו.
בינג הוא גם מקור החיפוש של ChatGPT, כך שדף חדש נכנס מהר יותר גם לתשובות AI.

שימוש: python3 tools_indexnow.py blog-x.html about.html ...
"""
import json, sys, urllib.request

HOST = 'muladigital.co.il'
KEY = 'af73d662d92b432592f3917732fed19a'
SKIP = {'404.html'}


def to_url(f):
    f = f.strip()
    if not f.endswith('.html') or f in SKIP or '/' in f:
        return None
    slug = f[:-5]
    return 'https://%s/%s' % (HOST, '' if slug == 'index' else slug)


urls = sorted({u for u in map(to_url, sys.argv[1:]) if u})
if 'sitemap.xml' in sys.argv[1:] and 'https://%s/' % HOST not in urls:
    urls.append('https://%s/' % HOST)
if not urls:
    print('אין דפים לשליחה')
    sys.exit(0)
body = json.dumps({'host': HOST, 'key': KEY, 'keyLocation': 'https://%s/%s.txt' % (HOST, KEY), 'urlList': urls[:10000]}).encode()
req = urllib.request.Request('https://api.indexnow.org/indexnow', data=body,
                             headers={'Content-Type': 'application/json; charset=utf-8'})
try:
    with urllib.request.urlopen(req, timeout=30) as r:
        print('IndexNow:', r.status, 'נשלחו', len(urls), 'כתובות')
except urllib.error.HTTPError as e:
    print('IndexNow שגיאה', e.code, e.read()[:300])
    sys.exit(1)
for u in urls:
    print(' ', u)
