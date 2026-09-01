# -*- coding: utf-8 -*-
"""Stitches the full-page capture PDFs into tall strips.

The portfolio card frames scroll these on hover, so each strip is the whole
site in one image. Run from the site root:  python tools_strips.py
"""
import os, glob
import pypdfium2 as pdfium
from PIL import Image

SRC = r"C:/Users/moshe/Downloads/משה מולה תמונות/ההירו של לקוחות של משה"
OUT = os.path.join('assets', 'portfolio', 'strips')
os.makedirs(OUT, exist_ok=True)

# project slug -> the domain in the capture filename
PDF_FOR = {
    'yochai':      'jacksonplumber-co-il',
    'erplogic':    'erplogic-co-il',
    'tamuz':       'tamuzftk-co-il',
    'cloudbeam':   'cloudbeam-muladigital-co-il',
    'harela':      'harelacpa-co-il',
    'eldadlevi':   'eldadlevi-muladigital-co-il',
    'maayan':      'hibur-ins-co-il',
    'hibur':       'hibur-muladigital-co-il',
    'amitmatan':   'amitmatan-co-il',
    'comutech':    'comutech-co-il',
    'dikla':       'diklashalit-co-il',
    'finansee':    'finansee-muladigital-co-il',
    'tax':         'taxreturnil-co-il',
    'athletics':   'maccabirlz-co-il',
    'kobot':       'industrial-muladigital-co-il',
    'lasercut':    'engineering-muladigital-co-il',
    'atal':        'atalair-muladigital-co-il',
    'konstantin':  'kostyains-muladigital-co-il',
    'up':          'up-ins-co-il',
    'tamarfin':    'tamar-fin-co-il',
    'ytzm':        'ytzm-co-il',
    'office':      'office-li-muladigital-co-il',
    'gananot':     'gananot-muladigital-co-il',
    'neta':        'neta-drukman-muladigital-co-il',
    'electricguy': 'electricguy-co-il',
}

WIDTH = 760          # enough for a card frame at 2x on a phone, still light
MAX_H = 9000         # a few captures run very long; cap so one card is not 1MB

def strip(domain, slug):
    hits = glob.glob(os.path.join(SRC, 'screencapture-%s-*.pdf' % domain))
    if not hits:
        return None
    doc = pdfium.PdfDocument(hits[0])
    tiles = [p.render(scale=1.5).to_pil().convert('RGB') for p in doc]
    w = min(t.width for t in tiles)
    tiles = [t if t.width == w else t.resize((w, round(t.height * w / t.width)), Image.LANCZOS) for t in tiles]
    tall = Image.new('RGB', (w, sum(t.height for t in tiles)), 'white')
    y = 0
    for t in tiles:
        tall.paste(t, (0, y)); y += t.height
    h = round(tall.height * WIDTH / tall.width)
    tall = tall.resize((WIDTH, h), Image.LANCZOS)
    if h > MAX_H:
        tall = tall.crop((0, 0, WIDTH, MAX_H))
    path = os.path.join(OUT, slug + '.webp')
    tall.save(path, quality=72, method=6)
    return tall.height, os.path.getsize(path) // 1024

if __name__ == '__main__':
    total = 0
    for slug, domain in sorted(PDF_FOR.items()):
        r = strip(domain, slug)
        if r is None:
            print('  no capture for', slug); continue
        total += r[1]
        print('%-12s %5dpx  %4d KB' % (slug, r[0], r[1]))
    print('---\n%d strips, %.1f MB total' % (len(os.listdir(OUT)), total / 1024))
