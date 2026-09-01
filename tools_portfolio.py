# -*- coding: utf-8 -*-
"""Builds the client-work grid on each vertical service page.

Reads the hero captures from the uploads folder, normalises them to one ratio
at two widths, and injects a `wrk-` grid section into the matching page.
Run from the site root:  python tools_portfolio.py
"""
import io, os, re
from PIL import Image

SRC_DIR = r"C:/Users/moshe/Downloads/משה מולה תמונות/ההירו של לקוחות של משה"
RATIO = 1280 / 600

# page -> (folder, eyebrow, heading, [(source png, slug, name, role)])
PLAN = {
 "service-web-development.html": ("b2b", "לא דוגמאות. לקוחות.", "אתרים שבנינו לחברות B2B ותעשייה", [
   ("ERP LOGIC שותף של חברת SAP .png", "erplogic", "ERP Logic", "שותפה גלובלית של SAP"),
   ("קלאוד בים שותף סלספורס.png", "cloudbeam", "CloudBeam", "שותפה של Salesforce"),
   ("תמוז אף טי קיי סולושנס.png", "tamuz", "Tamuz FTK Solutions", "פתרונות תעשייתיים"),
   ("קובוט אתר לתחום התעשייה והאוטומציה.png", "kobot", "קובוט", "תעשייה ואוטומציה"),
   ("לייזר קאט אתר לתחום החיתוך לייזר.png", "lasercut", "לייזר קאט", "חיתוך לייזר"),
 ]),
 "service-web-build-electrician.html": ("electrician", "לא דוגמאות. לקוחות.", "אתרים שבנינו לחשמלאים", [
   ("החשמלאי המהיר - חשמלאי ברחובות .png", "fast-electrician", "החשמלאי המהיר", "חשמלאי ברחובות"),
   ("עמית מתן חשמלאי מוסמך.png", "amitmatan", "עמית מתן", "חשמלאי מוסמך"),
 ]),
 "service-web-build-installer.html": ("installer", "לא דוגמאות. לקוחות.", "אתרים שבנינו לבעלי מקצוע", [
   ("יוחאי גקסון אינסטלטור ואיתור נזילות .png", "yochai", "יוחאי ג׳קסון", "אינסטלציה ואיתור נזילות"),
   ("לירן בבחנוב טכנאי ומתקין מזגנים.png", "liran", "לירן בבחנוב", "טכנאי ומתקין מזגנים"),
 ]),
 "service-web-build-accountant.html": ("accountant", "לא דוגמאות. לקוחות.", "אתרים שבנינו בתחום הפיננסי", [
   ("הראלה הלוי רואת חשבון .png", "harela", "הראלה הלוי", "רואת חשבון"),
   ("חיבור החזרי מס.png", "tax", "חיבור החזרי מס", "החזרי מס לשכירים"),
   ("פיננסי הלוואות ואשראי לעסקים.png", "finansee", "פיננסי", "הלוואות ואשראי לעסקים"),
   ("אלדד לוי שמאות מקרקעיו.png", "eldadlevi", "אלדד לוי", "שמאות מקרקעין"),
 ]),
 "service-web-build-lawyer.html": ("lawyer", "לא דוגמאות. לקוחות.", "אתרים שבנינו למשרדי עורכי דין", [
   ("אמיר ברכה עורך דין פלילי.png", "amirbracha", "עו״ד אמיר ברכה", "דין פלילי"),
   ("אלדד לוי שמאות מקרקעיו.png", "eldadlevi", "אלדד לוי", "שמאות מקרקעין"),
 ]),
 "service-web-build-fitness.html": ("fitness", "לא דוגמאות. לקוחות.", "אתרים שבנינו לעולם הספורט", [
   ("מועדון האתלטיקה הקלה ראשון לציון.png", "athletics", "מועדון האתלטיקה הקלה", "ראשון לציון"),
   ("קבוצת הריצה של יבנה DANIRUN דף נחיתה.png", "danirun", "DANIRUN", "קבוצת ריצה · דף נחיתה"),
 ]),
 "service-web-build-therapist.html": ("therapist", "לא דוגמאות. לקוחות.", "אתר שבנינו למטפלת", [
   ("דקלה מדואלה שליט עיסוי עד הבית .png", "dikla", "דקלה מדואלה שליט", "עיסוי עד הבית"),
 ]),
 "service-web-build-smallbiz.html": ("smallbiz", "לא דוגמאות. לקוחות.", "אתרים שבנינו לעסקים קטנים", [
   ("שלומי סאסי הובלות ומשלוחים.png", "shlomi", "שלומי סאסי", "הובלות ומשלוחים"),
   ("אלכס ויזל שירותי משרד מרחוק.png", "alex", "אלכס ויזל", "שירותי משרד מרחוק"),
   ("איריס רון קומיוטק .png", "comutech", "איריס רון", "COMUTech"),
   ("יובל אסף צלם וידיאו.png", "yuval", "Yuval Assaf", "צלם וידאו"),
 ]),
}

SIZES = ("(max-width:820px) calc(100vw - 48px), "
         "(max-width:1148px) calc(50vw - 39px), 511px")

CSS = '''
  /* --- portfolio grid: real client sites, framed identically --- */
  .wrk-sec{padding:clamp(48px,7vw,80px) 0;}
  .wrk-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:clamp(26px,3.4vw,38px) clamp(18px,2.6vw,30px);}
  .wrk-grid.one{grid-template-columns:minmax(0,1fr);max-width:760px;margin-inline:auto;}
  .wrk-card{display:flex;flex-direction:column;min-width:0;}
  .wrk-frame{border:1px solid var(--line-2);border-radius:clamp(10px,1.2vw,14px);overflow:hidden;background:var(--surface);
    box-shadow:0 1px 2px rgba(14,14,18,.04),0 12px 28px -12px rgba(14,14,18,.16);
    transition:transform .35s var(--ease),box-shadow .35s var(--ease);}
  .wrk-card:hover .wrk-frame{transform:translateY(-4px);
    box-shadow:0 1px 2px rgba(14,14,18,.05),0 22px 44px -16px rgba(14,14,18,.24);}
  .wrk-bar{display:flex;align-items:center;gap:clamp(5px,.6vw,7px);padding:clamp(7px,.9vw,10px) clamp(10px,1.2vw,14px);
    background:var(--surface-2);border-bottom:1px solid var(--line);}
  .wrk-dot{width:clamp(6px,.75vw,9px);aspect-ratio:1;border-radius:50%;background:#D6D6DB;flex:none;}
  .wrk-pill{flex:1;height:clamp(11px,1.4vw,16px);border-radius:100px;background:#E7E7EC;margin-inline-start:8px;max-width:280px;}
  .wrk-frame img{display:block;width:100%;height:auto;aspect-ratio:1280/600;}
  .wrk-meta{padding:clamp(12px,1.5vw,16px) 4px 0;}
  .wrk-name{font-family:var(--f-display);font-weight:700;font-size:clamp(15.5px,1.6vw,17px);color:var(--ink);line-height:1.35;
    text-wrap:balance;overflow-wrap:anywhere;}
  .wrk-role{font-size:clamp(13.5px,1.35vw,14.5px);color:var(--muted);margin-top:3px;line-height:1.5;}
  @media (max-width:820px){.wrk-grid{grid-template-columns:1fr;}}
  @media (min-width:1500px){.wrk-grid{gap:42px 34px;}}'''


def build_images(folder, shots):
    out_dir = os.path.join("assets", "portfolio", folder)
    os.makedirs(out_dir, exist_ok=True)
    for src, slug, _name, _role in shots:
        im = Image.open(os.path.join(SRC_DIR, src)).convert("RGB")
        w, h = im.size
        band = min(h, round(w / RATIO))
        hero = im.crop((0, 0, w, band))
        for width in (768, 1280):
            hero.resize((width, round(width / RATIO)), Image.LANCZOS).save(
                os.path.join(out_dir, "%s-%d.webp" % (slug, width)), quality=88, method=6)


def card(folder, slug, name, role):
    role_html = '\n            <div class="wrk-role">%s</div>' % role if role else ""
    return (
        '        <article class="wrk-card">\n'
        '          <div class="wrk-frame">\n'
        '            <div class="wrk-bar" aria-hidden="true"><span class="wrk-dot"></span>'
        '<span class="wrk-dot"></span><span class="wrk-dot"></span><span class="wrk-pill"></span></div>\n'
        '            <img loading="lazy" decoding="async" width="1280" height="600"\n'
        '              src="assets/portfolio/{f}/{s}-1280.webp"\n'
        '              srcset="assets/portfolio/{f}/{s}-768.webp 768w, assets/portfolio/{f}/{s}-1280.webp 1280w"\n'
        '              sizes="{z}"\n'
        '              alt="{n} - אתר שנבנה על ידי Mula Digital" />\n'
        '          </div>\n'
        '          <div class="wrk-meta">\n'
        '            <div class="wrk-name">{n}</div>{r}\n'
        '          </div>\n'
        '        </article>'
    ).format(f=folder, s=slug, n=name, r=role_html, z=SIZES)


for page, (folder, eyebrow, heading, shots) in PLAN.items():
    if not os.path.isfile(page):
        print("MISSING PAGE", page)
        continue
    build_images(folder, shots)
    s = io.open(page, encoding="utf-8").read()

    one = " one" if len(shots) == 1 else ""
    cards = "\n".join(card(folder, sl, n, r) for _f, sl, n, r in shots)
    section = (
        "  <!-- CLIENT WORK -->\n"
        '  <section class="wrk-sec" id="work" aria-label="אתרים שבנינו">\n'
        '    <div class="wrap">\n'
        '      <div class="s-head center reveal">\n'
        '        <span class="eyebrow">%s</span>\n'
        "        <h2>%s</h2>\n"
        "      </div>\n"
        '      <div class="wrk-grid%s">\n%s\n      </div>\n'
        "    </div>\n  </section>\n" % (eyebrow, heading, one, cards)
    )

    if 'class="wrk-sec"' in s:
        a = s.index("  <!-- CLIENT WORK")
        b = s.index("</section>", s.index('<section class="wrk-sec"', a)) + len("</section>\n")
        s = s[:a] + section + s[b:]
    else:
        # drop it in straight after the client marquee
        i = s.index('<section class="clients"')
        j = s.index("</section>", i) + len("</section>\n")
        s = s[:j] + "\n" + section + s[j:]

    if ".wrk-grid{" not in s:
        m = re.search(r"\n[ \t]*</style>", s)
        s = s[: m.start()] + "\n" + CSS + s[m.start():]

    io.open(page, "w", encoding="utf-8").write(s)
    print("%-42s %d cards" % (page, len(shots)))
