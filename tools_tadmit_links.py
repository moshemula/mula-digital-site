# -*- coding: utf-8 -*-
"""מחבר את atar-tadmit.html לשאר האתר: קישורים פנימיים הקשריים."""
import io

CARD = (u'        <a href="atar-tadmit.html" class="rel-card reveal">\n'
        u'          <span class="r-ic"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/>'
        u'<line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>\n'
        u'          <h4>אתר תדמית לעסק</h4>\n'
        u'          <p>מחיר, תהליך ודוגמאות חיות. הבית הדיגיטלי של העסק.</p>\n'
        u'          <span class="arr">למידע נוסף <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/>'
        u'<polyline points="12 19 5 12 12 5"/></svg></span>\n'
        u'        </a>\n')

EDITS = [
    # (file, needle, replacement)
    ("service-web-build.html",
     u'      <div class="related">\n',
     u'      <div class="related">\n' + CARD),

    ("service-landing-pages.html",
     u'      <div class="related">\n',
     u'      <div class="related">\n' + CARD),

    # קישור הקשרי בתוך הטקסט של פוסט המחירים - האות החזקה ביותר
    ("blog-kama-ole-livnot-atar.html",
     u"אתר תדמית עסקי (5-10 עמודים)",
     u'<a href="atar-tadmit.html">אתר תדמית עסקי</a> (5-10 עמודים)'),
]

for f, needle, repl in EDITS:
    t = io.open(f, encoding="utf-8").read()
    if "atar-tadmit.html" in t:
        print(u"%-34s SKIP (already linked)" % f)
        continue
    if needle not in t:
        print(u"%-34s MISS (needle not found)" % f)
        continue
    t = t.replace(needle, repl, 1)
    io.open(f, "w", encoding="utf-8").write(t)
    print(u"%-34s OK" % f)
