# -*- coding: utf-8 -*-
"""בונה את עמודי המדריכים (אשכולות נושא) מתוך hubs.json, ומחבר אליהם את המאמרים.

מה הסקריפט עושה בכל הרצה (בטוח להריץ שוב ושוב):
1. יוצר/מעדכן עמוד לכל מדריך (madrich-*.html) על בסיס blog.html.
2. בכל מאמר: תיבת "המאמר הזה הוא חלק מהמדריך" עם קישור לכל המאמרים באותו נושא.
3. בכל מאמר: פירורי לחם בית > בלוג > מדריך > מאמר (גם בתצוגה וגם ב-schema).
4. ב-blog.html: רצועת "מדריכים לפי נושא".

מאמר חדש? מוסיפים את ה-slug שלו לרשימת articles של המדריך המתאים ב-hubs.json ומריצים:
    python3 tools_hubs.py
"""
import json, re, html, os

ROOT = os.path.dirname(os.path.abspath(__file__))
SITE = 'https://muladigital.co.il/'
HUBS = json.load(open(os.path.join(ROOT, 'hubs.json'), encoding='utf-8'))
ARROW = '<svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>'


def rd(slug):
    return open(os.path.join(ROOT, slug + '.html'), encoding='utf-8').read()


def wr(slug, s):
    open(os.path.join(ROOT, slug + '.html'), 'w', encoding='utf-8').write(s)


def esc(t):
    return html.escape(t, quote=True)


def clean_title(s):
    t = re.search(r'<title>(.*?)</title>', s, re.S).group(1)
    return html.unescape(t.split('|')[0].strip())


def page_desc(s):
    m = re.search(r'<meta name="description" content="([^"]*)"', s)
    return html.unescape(m.group(1)) if m else ''


# כותרת ותיאור של כל כרטיס: מהכרטיס הקיים ב-blog.html, ואם אין, מה-meta של הדף
BLOG = rd('blog')
CARD = {}
for m in re.finditer(r'<a href="(blog-[^"]+)" class="svc-card reveal">.*?<h3[^>]*>(.*?)</h3>\s*<p class="svc-desc">(.*?)</p>', BLOG, re.S):
    CARD[m.group(1)] = (html.unescape(m.group(2)), html.unescape(m.group(3)))


def card_info(slug):
    if slug in CARD:
        return CARD[slug]
    s = rd(slug)
    return clean_title(s), page_desc(s)


HUB_CSS = """<style id="hub-css">
.hub-path{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:22px;counter-reset:hp}
.hub-step{position:relative;display:flex;flex-direction:column;gap:10px;padding:30px 28px 26px;border:1px solid var(--line,#ECECEC);border-radius:22px;background:#fff;text-decoration:none;color:inherit;transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease;overflow:hidden}
.hub-step::before{content:"";position:absolute;inset:0 0 auto 0;height:3px;background:linear-gradient(120deg,#E8003D,#7B2FBE);transform:scaleX(0);transform-origin:right;transition:transform .35s ease}
.hub-step:hover{transform:translateY(-4px);box-shadow:0 18px 40px -22px rgba(14,14,18,.35);border-color:transparent}
.hub-step:hover::before{transform:scaleX(1)}
.hub-num{font-family:Heebo,sans-serif;font-weight:900;font-size:44px;line-height:1;background:linear-gradient(120deg,#E8003D,#7B2FBE);-webkit-background-clip:text;background-clip:text;color:transparent}
.hub-step h3{font-size:20px;line-height:1.35;margin:0}
.hub-step p{color:var(--soft,#3f3f46);font-size:16px;line-height:1.6;margin:0}
.hub-step .svc-link{margin-top:auto}
.hub-tag{align-self:flex-start;font-size:12px;font-weight:700;padding:4px 10px;border-radius:99px;background:var(--surface-2,#F3F3F5);color:var(--soft,#3f3f46)}
.hub-step .svc-link,.hub-svc a{display:inline-flex;align-items:center;gap:8px;color:#7B2FBE;font-weight:700;font-size:15px}
.hub-svc a{color:var(--ink,#111)}
.hub-step .svc-link svg,.hub-svc a svg{width:16px!important;height:16px!important;flex:0 0 16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;transition:transform .25s ease}
.hub-step:hover .svc-link svg,.hub-svc a:hover svg{transform:translateX(-4px)}
.hub-start{max-width:720px;margin:0 auto 34px;text-align:center;font-size:18px;line-height:1.7;color:var(--soft,#3f3f46)}
.hub-svc{display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin-top:22px}
.hub-svc a,.hub-others a{display:inline-flex;align-items:center;gap:8px;padding:12px 20px;border-radius:99px;border:1px solid var(--line,#ECECEC);background:#fff;font-weight:700;color:var(--ink,#111);text-decoration:none;transition:border-color .2s,color .2s}
.hub-svc a:hover,.hub-others a:hover{border-color:#7B2FBE;color:#7B2FBE}
.hub-others{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-top:18px}
@media (max-width:640px){.hub-path{grid-template-columns:1fr}.hub-step{padding:24px 20px}.hub-num{font-size:36px}}
@media (prefers-reduced-motion:reduce){.hub-step,.hub-step::before{transition:none}}
</style>"""

BOX_CSS = """<style id="hub-box-css">.hub-box{margin:40px 0 30px;padding:24px 26px;border-radius:20px;border:1px solid var(--line,#ECECEC);background:linear-gradient(var(--surface,#FAFAFA),var(--surface,#FAFAFA)) padding-box,linear-gradient(120deg,rgba(232,0,61,.35),rgba(123,47,190,.35)) border-box;border:1px solid transparent}
.hub-box .k{font-family:Heebo,sans-serif;font-weight:800;font-size:17px;margin-bottom:12px;color:var(--ink,#111)}
.hub-box .k a{color:#7B2FBE;text-decoration:none}
.hub-box ol{margin:0;padding-inline-start:22px;display:grid;gap:8px;font-size:16px;line-height:1.55}
.hub-box ol a{color:var(--ink,#111);font-weight:600;text-decoration:underline;text-decoration-color:rgba(123,47,190,.35);text-underline-offset:3px}
.hub-box ol a:hover{color:#7B2FBE}
.hub-box .here{font-weight:800;color:var(--muted,#71717A)}
.hub-box .all{display:inline-block;margin-top:14px;font-weight:800;color:#E8003D;text-decoration:none}
</style>"""


def build_hub(h, all_hubs):
    s = BLOG
    url = SITE + h['slug']
    title, desc = h['title'], h['desc']
    s = re.sub(r'<title>.*?</title>', '<title>' + esc(title) + '</title>', s, 1, flags=re.S)
    s = re.sub(r'(<meta name="description" content=")[^"]*', lambda m: m.group(1) + esc(desc), s, 1)
    s = re.sub(r'(<link rel="canonical" href=")[^"]*', lambda m: m.group(1) + url, s, 1)
    s = re.sub(r'(<meta property="og:title" content=")[^"]*', lambda m: m.group(1) + esc(title), s, 1)
    s = re.sub(r'(<meta property="og:description" content=")[^"]*', lambda m: m.group(1) + esc(desc), s, 1)
    items = h['articles'] + h.get('extra', [])
    ld = {"@context": "https://schema.org", "@type": "CollectionPage", "name": h['h1'] + ': ' + h['accent'],
          "description": desc, "url": url, "inLanguage": "he",
          "isPartOf": {"@type": "WebSite", "name": "Mula Digital", "url": SITE},
          "author": {"@type": "Person", "name": "משה מולה", "url": SITE + "about"},
          "mainEntity": {"@type": "ItemList", "numberOfItems": len(items), "itemListElement": [
              {"@type": "ListItem", "position": i + 1, "name": card_info(a)[0], "url": SITE + a} for i, a in enumerate(items)]}}
    bc = {"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": [
        {"@type": "ListItem", "position": 1, "name": "בית", "item": SITE},
        {"@type": "ListItem", "position": 2, "name": "בלוג", "item": SITE + "blog"},
        {"@type": "ListItem", "position": 3, "name": h['h1'], "item": url}]}
    s = re.sub(r'<script type="application/ld\+json">.*?</script>', '', s, flags=re.S)
    s = s.replace('</head>', '<script type="application/ld+json">' + json.dumps(ld, ensure_ascii=False) + '</script>\n'
                  '<script type="application/ld+json">' + json.dumps(bc, ensure_ascii=False) + '</script>\n' + HUB_CSS + '\n</head>', 1)
    # גוף העמוד: מה-HERO ועד הסקשן האחרון לפני הפוטר
    a = s.index('<!-- HERO -->')
    b = s.index('<section class="sec surface">', a)
    n = len(h['articles'])
    steps = ''
    for i, slug in enumerate(h['articles']):
        t, d = card_info(slug)
        steps += ('<a href="%s" class="hub-step reveal"><span class="hub-num">%02d</span><h3>%s</h3><p>%s</p>'
                  '<span class="svc-link">לקריאת המאמר %s</span></a>\n' % (slug, i + 1, esc(t), esc(d), ARROW))
    for slug in h.get('extra', []):
        t, d = card_info(slug)
        steps += ('<a href="%s" class="hub-step reveal"><span class="hub-tag">השוואה</span><h3>%s</h3><p>%s</p>'
                  '<span class="svc-link">להשוואה המלאה %s</span></a>\n' % (slug, esc(t), esc(d), ARROW))
    svc = ''.join('<a href="%s">%s %s</a>' % (u, esc(l), ARROW) for u, l in h['services'])
    others = ''.join('<a href="%s">%s</a>' % (o['slug'], esc(o['h1'])) for o in all_hubs if o['slug'] != h['slug'])
    body = f'''<!-- HERO -->
  <section class="srv-hero">
    <div class="hero-shape hero-shape-1"></div>
    <div class="hero-shape hero-shape-2"></div>
    <div class="wrap">
      <div class="hero-inner">
        <nav class="breadcrumb" aria-label="פירורי לחם">
          <a href="/">בית</a>
          <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
          <a href="blog">בלוג</a>
          <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
          <span style="color:rgba(255,255,255,.7)">{esc(h['h1'])}</span>
        </nav>
        <h1>{esc(h['h1'])}<span class="accent">{esc(h['accent'])}</span></h1>
        <p class="subtitle">{n} מדריכים מהשטח, מסודרים לפי הסדר שבו כדאי לקרוא אותם. בלי קלישאות, עם מספרים.</p>
      </div>
    </div>
  </section>

  <section class="sec">
    <div class="wrap">
      <div class="cat-header reveal" style="text-align:center">
        <span class="eyebrow">מאיפה מתחילים</span>
        <h2>המסלול, שלב אחרי שלב</h2>
      </div>
      <p class="hub-start reveal">{esc(h['start'])}</p>
      <div class="hub-path">
{steps}      </div>
    </div>
  </section>

  <section class="sec">
    <div class="wrap" style="text-align:center">
      <span class="eyebrow">מעדיפים שמישהו יעשה את זה?</span>
      <h2 style="margin-top:12px">את כל מה שכתוב כאן אני עושה בשביל לקוחות</h2>
      <div class="hub-svc">{svc}</div>
      <p style="color:var(--soft);margin-top:34px;font-weight:700">מדריכים נוספים</p>
      <div class="hub-others"><a href="blog">כל המאמרים</a>{others}</div>
    </div>
  </section>

  '''
    s = s[:a] + body + s[b:]
    wr(h['slug'], s)


def article_box(h, slug):
    lis = ''
    for a in h['articles']:
        t = card_info(a)[0]
        if a == slug:
            lis += '<li><span class="here">%s (אתם כאן)</span></li>' % esc(t)
        else:
            lis += '<li><a href="%s">%s</a></li>' % (a, esc(t))
    return ('<!--HUB-BOX--><aside class="hub-box" aria-label="מאמרים נוספים במדריך">'
            '<div class="k">המאמר הזה הוא חלק מהמדריך <a href="%s">%s</a></div><ol>%s</ol>'
            '<a class="all" href="%s">לכל המדריך לפי הסדר</a></aside>%s<!--/HUB-BOX-->'
            % (h['slug'], esc(h['h1']), lis, h['slug'], BOX_CSS))


def link_article(h, slug):
    s = rd(slug)
    s = re.sub(r'<!--HUB-BOX-->.*?<!--/HUB-BOX-->', '', s, flags=re.S)
    box = article_box(h, slug)
    if '<div class="author">' in s:
        s = s.replace('<div class="author">', box + '\n<div class="author">', 1)
    else:
        i = s.rfind('</article>')
        if i < 0:
            i = s.rfind('</main>')
        s = s[:i] + box + s[i:]
    # פירורי לחם בתצוגה
    s = re.sub(r'(<a href="blog">בלוג</a>)(\s*[·›]\s*)(?:<a href="madrich-[^"]+">[^<]*</a>\s*[·›]\s*)?',
               lambda m: m.group(1) + m.group(2) + '<a href="%s">%s</a>%s' % (h['slug'], esc(h['short']), m.group(2)), s, 1)
    # פירורי לחם ב-schema
    def fix_bc(m):
        try:
            d = json.loads(m.group(1))
        except Exception:
            return m.group(0)
        if d.get('@type') != 'BreadcrumbList':
            return m.group(0)
        items = [x for x in d['itemListElement'] if 'madrich-' not in x.get('item', '')]
        for x in items:
            if x.get('item', '').endswith('/#blog'):
                x['item'] = SITE + 'blog'
        last = items[-1]
        items = items[:-1] + [{"@type": "ListItem", "name": h['h1'], "item": SITE + h['slug']}, last]
        for i, x in enumerate(items):
            x['position'] = i + 1
        d['itemListElement'] = items
        return '<script type="application/ld+json">' + json.dumps(d, ensure_ascii=False) + '</script>'
    s = re.sub(r'<script type="application/ld\+json">\s*(\{[^<]*?"BreadcrumbList".*?)\s*</script>', fix_bc, s, flags=re.S)
    wr(slug, s)


def blog_strip():
    s = rd('blog')
    s = re.sub(r'<!--HUBS-->.*?<!--/HUBS-->\s*', '', s, flags=re.S)
    chips = ''.join('<a href="%s" class="hub-step reveal"><span class="hub-tag">%d מדריכים</span><h3>%s</h3><p>%s</p>'
                    '<span class="svc-link">למדריך %s</span></a>' % (h['slug'], len(h['articles']), esc(h['h1']), esc(h['accent']), ARROW)
                    for h in HUBS)
    strip = ('<!--HUBS--><section class="sec" style="padding-bottom:0"><div class="wrap"><div class="cat-header reveal">'
             '<span class="eyebrow">לפי נושא</span><h2>מדריכים מסודרים, מההתחלה ועד הסוף</h2>'
             '<p>בחרו נושא וקבלו את כל המאמרים עליו, לפי הסדר שבו כדאי לקרוא.</p></div>'
             '<div class="hub-path">' + chips + '</div></div></section>' + HUB_CSS.replace('id="hub-css"', 'id="hub-css-blog"') + '<!--/HUBS-->\n  ')
    s = s.replace('<!-- ARTICLES -->', strip + '<!-- ARTICLES -->', 1)
    if '"hasPart"' not in s:
        part = ',"hasPart":[' + ','.join('{"@type":"CollectionPage","name":%s,"url":"%s"}' % (json.dumps(h['h1'], ensure_ascii=False), SITE + h['slug']) for h in HUBS) + ']'
        s = s.replace('"url":"https://muladigital.co.il/blog",', '"url":"https://muladigital.co.il/blog"' + part + ',', 1)
    wr('blog', s)


if __name__ == '__main__':
    blog_strip()
    BLOG = rd('blog')
    for h in HUBS:
        build_hub(h, HUBS)
        for a in h['articles']:
            link_article(h, a)
    print('עודכנו %d מדריכים ו-%d מאמרים' % (len(HUBS), sum(len(h['articles']) for h in HUBS)))
