# -*- coding: utf-8 -*-
"""Bundles a page into a single self-contained file for sharing.

Artifacts run under a strict CSP, so every local asset is inlined, the CDN
scripts are dropped, and the scroll-reveal they drive is pinned visible.
Usage: python tools_preview.py <page.html> <out.html> "<title>"
"""
import base64, io, os, re, sys

SRC, OUT, TITLE = sys.argv[1], sys.argv[2], sys.argv[3]
MIME = {'.png':'image/png','.webp':'image/webp','.jpg':'image/jpeg','.jpeg':'image/jpeg',
        '.svg':'image/svg+xml','.ico':'image/x-icon','.gif':'image/gif'}
cache = {}

def data_uri(path):
    if path in cache: return cache[path]
    ext = os.path.splitext(path)[1].lower()
    if not os.path.isfile(path) or ext not in MIME: return None
    with open(path, 'rb') as f:
        cache[path] = 'data:%s;base64,%s' % (MIME[ext], base64.b64encode(f.read()).decode())
    return cache[path]

def sub(m):
    parts = []
    for chunk in m.group(2).split(','):
        bits = chunk.strip().split()
        if not bits: continue
        uri = data_uri(bits[0])
        if uri is None: return m.group(0)
        parts.append(' '.join([uri] + bits[1:]))
    return '%s="%s"' % (m.group(1), ', '.join(parts))

s = io.open(SRC, encoding='utf-8').read()
s = re.sub(r'\b(src|href|srcset)="((?:assets/)[^"]*)"', sub, s)
s = re.sub(r'<script[^>]*src="https://(cdnjs\.cloudflare\.com|www\.googletagmanager\.com)[^"]*"[^>]*></script>\s*', '', s)
s = re.sub(r'<script[^>]*>[^<]*clarity[^<]*</script>\s*', '', s, flags=re.I)
# The CSP blocks youtube iframes, so each one becomes a facade: the real
# thumbnail (fetched to scratchpad/yt and inlined) with a play badge that
# opens the video on youtube.
YT_DIR = os.path.join(os.path.dirname(os.path.abspath(OUT)), 'yt')

def yt_facade(m):
    vid = m.group(1)
    thumb = data_uri(os.path.join(YT_DIR, vid + '.jpg'))
    if not thumb:
        return '<div class="yt-placeholder">סרטון המלצה<br>נטען באתר החי</div>'
    return ('<a class="yt-facade" href="https://www.youtube.com/watch?v=%s" target="_blank" rel="noopener" '
            'aria-label="צפייה בהמלצה ביוטיוב"><img src="%s" alt="" loading="lazy" />'
            '<span class="yt-play" aria-hidden="true"></span></a>') % (vid, thumb)

s = re.sub(r'<iframe[^>]*youtube\.com/embed/([A-Za-z0-9_-]+)[^>]*>\s*</iframe>', yt_facade, s)
s = re.sub(r'<title>.*?</title>', '<title>%s</title>' % TITLE, s, count=1, flags=re.S)

head = re.search(r'<head[^>]*>(.*?)</head>', s, re.S).group(1)
body = re.search(r'<body[^>]*>(.*?)</body>', s, re.S).group(1)

SHIM = '''<script>
  document.documentElement.setAttribute("dir","rtl");
  document.documentElement.setAttribute("lang","he");
</script>
<style id="preview-fixes">
  html,body{direction:rtl;}
  .reveal,[class*="reveal"]{opacity:1 !important;transform:none !important;visibility:visible !important;}
  .cookie-banner{display:none !important;}
  .yt-placeholder{display:flex;align-items:center;justify-content:center;text-align:center;aspect-ratio:16/9;
    width:100%;border-radius:12px;background:#15151c;color:#8b8b96;font:500 14px/1.6 Assistant,sans-serif;padding:16px;}
  .yt-facade{position:relative;display:block;width:100%;aspect-ratio:9/16;overflow:hidden;background:#0b0b0f;}
  .yt-facade img{width:100%;height:100%;object-fit:cover;display:block;}
  .yt-play{position:absolute;inset:50% auto auto 50%;transform:translate(-50%,-50%);
    width:64px;height:64px;border-radius:50%;background:rgba(232,0,61,.92);
    box-shadow:0 6px 24px rgba(0,0,0,.45);transition:transform .25s;}
  .yt-play::after{content:"";position:absolute;inset:50% auto auto 50%;
    transform:translate(-40%,-50%);border-style:solid;border-width:11px 0 11px 18px;
    border-color:transparent transparent transparent #fff;}
  .yt-facade:hover .yt-play{transform:translate(-50%,-50%) scale(1.08);}
  .preview-flag{position:fixed;inset-block-end:14px;inset-inline-end:14px;z-index:9999;background:#0E0E12;color:#fff;
    font:600 12.5px/1 Assistant,sans-serif;padding:9px 15px;border-radius:100px;box-shadow:0 6px 20px rgba(0,0,0,.28);opacity:.92;}
</style>'''

io.open(OUT, 'w', encoding='utf-8').write(
    head + "\n" + SHIM + "\n" + body + '\n<div class="preview-flag">תצוגה מקדימה — לא האתר החי</div>\n')
print('%s -> %.2f MB (%d assets inlined)' % (SRC, os.path.getsize(OUT) / 1024 / 1024, len(cache)))
