# -*- coding: utf-8 -*-
"""Opens the mega menu on hover, not only on click.

Click stays for touch and keyboard. On a real pointer the trigger link goes
back to being a link - hover opens the panel, clicking follows through to the
section's own page. A short close delay lets the cursor cross the gap between
the bar and the panel without it snapping shut.
"""
import io, glob

ANCHOR = """      document.addEventListener('keydown',function(e){
        if(e.key==='Escape')closeAllMegas();
      });"""

HOVER = """
      /* hover opens it on a real pointer; click still works for touch */
      var megaHover=window.matchMedia('(hover:hover) and (pointer:fine)');
      var megaCloseTimer;
      function megaHoverOn(){return megaHover.matches&&window.innerWidth>880;}
      megaItems.forEach(function(item){
        item.addEventListener('mouseenter',function(){
          if(!megaHoverOn())return;
          clearTimeout(megaCloseTimer);
          if(item.classList.contains('mega-open'))return;
          closeAllMegas();
          item.classList.add('mega-open');
        });
        item.addEventListener('mouseleave',function(){
          if(!megaHoverOn())return;
          clearTimeout(megaCloseTimer);
          megaCloseTimer=setTimeout(function(){item.classList.remove('mega-open');},220);
        });
      });"""

CLICK_OLD = """        link.addEventListener('click',function(e){
          if(window.innerWidth<=880)return;
          e.preventDefault();"""

CLICK_NEW = """        link.addEventListener('click',function(e){
          if(window.innerWidth<=880)return;
          /* with hover available the panel is already open, so let the click
             follow the link through to the section page */
          if(megaHoverOn())return;
          e.preventDefault();"""

hovered = clicked = 0
for p in sorted(glob.glob('*.html')):
    s = io.open(p, encoding='utf-8').read()
    if ANCHOR not in s or 'megaHoverOn' in s:
        continue
    s = s.replace(ANCHOR, ANCHOR + HOVER, 1)
    hovered += 1
    if CLICK_OLD in s:
        s = s.replace(CLICK_OLD, CLICK_NEW, 1)
        clicked += 1
    io.open(p, 'w', encoding='utf-8').write(s)

print('hover added to %d pages, click handler relaxed on %d' % (hovered, clicked))
