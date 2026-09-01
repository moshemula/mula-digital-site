# -*- coding: utf-8 -*-
"""Makes the mega menu fit every screen.

Two faults, on all 60 pages:
  1. The panel had no max-height. On a short viewport its lower links ran off
     the bottom of the screen with no way to reach them.
  2. Between 900px (where the burger gives way to the desktop nav) and 1100px
     the four columns could not fit, so the grid squeezed and the panel grew
     to ~660px tall - which is what pushed the links off screen.

Fix: cap the height and let it scroll, keep it inside the viewport, and give
the 900-1099 range a two-column layout with the CTA on its own row.
"""
import io, glob

MARK = 'mega menu: fit the viewport at every width'

CSS = '''
  /* --- ''' + MARK + ''' --- */
  .mega{
    max-height:calc(100vh - 132px);
    max-height:calc(100dvh - 132px);
    max-width:calc(100vw - 32px);
    overflow-y:auto;
    overscroll-behavior:contain;
    scrollbar-width:thin;
    scrollbar-color:rgba(14,14,18,.22) transparent;
  }
  .mega::-webkit-scrollbar{width:10px;}
  .mega::-webkit-scrollbar-track{background:transparent;}
  .mega::-webkit-scrollbar-thumb{background:rgba(14,14,18,.2);border-radius:10px;
    border:3px solid transparent;background-clip:content-box;}
  .mega::-webkit-scrollbar-thumb:hover{background:rgba(14,14,18,.34);background-clip:content-box;}

  /* tablet landscape and small laptops: four columns do not fit */
  @media (min-width:900px) and (max-width:1099px){
    .mega{width:min(calc(100vw - 32px),740px);}
    .mega-grid.cols-4,.mega-grid.cols-3{grid-template-columns:1fr 1fr;}
    .mega-col{padding:18px 16px;}
    .mega-col+.mega-col{border-inline-start:0;}
    .mega-col:nth-child(even){border-inline-start:1px solid var(--line);}
    .mega-col:nth-child(n+3){border-top:1px solid var(--line);}
    .mega-cta{grid-column:1 / -1;border-radius:0 0 16px 16px;padding:20px 18px;}
    [dir="rtl"] .mega-cta{border-radius:0 0 16px 16px;}
  }

  /* a phone in landscape has almost no height to spare */
  @media (min-width:900px) and (max-height:560px){
    .mega{max-height:calc(100dvh - 96px);}
    .mega-col{padding:14px 16px;}
    .mega-col a{padding:6px 10px;}
  }
'''

patched = skipped = 0
for p in sorted(glob.glob('*.html')):
    s = io.open(p, encoding='utf-8').read()
    if '.mega{position:absolute' not in s:
        continue
    if MARK in s:
        skipped += 1
        continue
    i = s.index('</style>')
    io.open(p, 'w', encoding='utf-8').write(s[:i] + CSS + s[i:])
    patched += 1

print('mega fix added to %d pages (%d already had it)' % (patched, skipped))
