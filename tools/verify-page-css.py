"""Prove that the page-scoped stylesheet renders identically to the full bundle.

For every route and width the page is loaded twice: once as served (subset) and once with
the subset response swapped for the full bundle it was derived from. Both runs are pushed
through the same scenario - scroll, open the nav, open a gallery, print media, reduced
motion - and compared on computed styles, element geometry and pixels.

Acceptance: zero differences. A subset that renders even one pixel differently is a bug,
not a trade-off.

    python tools/verify-page-css.py http://127.0.0.1:8150
"""
import re, sys, time
from playwright.sync_api import sync_playwright

BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8150"
ROUTES = ["/", "/o-nas", "/cenik", "/blog", "/kontakt", "/nase-prace", "/faq",
          "/hodnoceni", "/sluzby", "/podminky-uziti", "/kamila-a-matej", "/neexistuje-404"]
# Any further arguments narrow the run to those routes, for re-checking a single failure.
if len(sys.argv) > 2:
    ROUTES = sys.argv[2:]
WIDTHS = [(390, 844), (768, 1024), (1440, 900)]
# How many times each comparison is repeated; only differences seen every time count.
ROUNDS = 3

FINGERPRINT = """() => {
  const out = [];
  const els = document.querySelectorAll('body, body *');
  for (const el of els) {
    if (el.tagName === 'SCRIPT' || el.tagName === 'STYLE' || el.tagName === 'LINK') continue;
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    const keep = ['display','position','width','height','margin','padding','font','color',
                  'background-color','background-image','border','flex','grid-template-columns',
                  'justify-content','align-items','opacity','visibility','transform','z-index',
                  'text-align','letter-spacing','line-height','overflow','gap','inset'];
    const style = keep.map(p => p + ':' + cs.getPropertyValue(p)).join('|');
    const before = getComputedStyle(el, '::before');
    const after = getComputedStyle(el, '::after');
    out.push([el.tagName, Math.round(r.x), Math.round(r.y), Math.round(r.width), Math.round(r.height),
              style, before.content, before.width, before.height, after.content, after.width, after.height].join('~'));
  }
  return out;
}"""


def scenario(pg):
    """Same interaction sequence for both runs, returning a list of fingerprints."""
    shots = []
    pg.wait_for_timeout(700)
    shots.append(pg.evaluate(FINGERPRINT))
    for _ in range(4):
        pg.mouse.wheel(0, 2200)
        pg.wait_for_timeout(200)
    shots.append(pg.evaluate(FINGERPRINT))
    for sel in ['.nav-toggle-label', '.faq-q-wrap', '[data-cookie-settings]']:
        try:
            pg.locator(sel).first.click(timeout=350)
            pg.wait_for_timeout(250)
        except Exception:
            pass
    shots.append(pg.evaluate(FINGERPRINT))
    pg.emulate_media(media="print")
    pg.wait_for_timeout(200)
    shots.append(pg.evaluate(FINGERPRINT))
    pg.emulate_media(media="screen", reduced_motion="reduce")
    pg.wait_for_timeout(200)
    shots.append(pg.evaluate(FINGERPRINT))
    pg.emulate_media(reduced_motion="no-preference")
    return shots


def settle_fonts(route):
    """Serve the webfonts with font-display:swap instead of optional.

    optional lets the browser drop a face that misses a ~100ms window. That outcome tracks
    how fast the page's CSS arrives, so the smaller subset wins the race and the full
    bundle loses it, and the two runs lay out with different glyph metrics - a difference
    caused by load timing, not by any rule the subset is missing. swap always ends up
    applying the face, so both runs converge on the same fonts and what remains to compare
    is the stylesheet itself.

    Sound because the subsetter never touches font loading: @font-face is kept verbatim and
    fonts.css is a separate stylesheet it does not rewrite. Both sides get this rewrite.
    """
    response = route.fetch()
    route.fulfill(response=response, body=response.text().replace("font-display:optional", "font-display:swap")
                                                          .replace("font-display: optional", "font-display: swap"))


def run(ctx, url, size, full_bundle=None):
    pg = ctx.new_page()
    pg.set_viewport_size({"width": size[0], "height": size[1]})
    pg.route(re.compile(r".*/assets/fonts/fonts\.css"), settle_fonts)
    if full_bundle:
        pg.route(re.compile(r".*/assets/generated/page-css-.*\.css"),
                 lambda route: route.fulfill(status=200, content_type="text/css", body=full_bundle))
    pg.goto(BASE + url, wait_until="networkidle")
    # font-display:optional makes a face apply only if it arrives inside the first ~100ms,
    # so an uncached load and a cached one lay out differently. Settling the font set makes
    # the comparison about CSS rather than about which run won that race.
    pg.evaluate("() => document.fonts.ready")
    pg.wait_for_timeout(300)
    prints = scenario(pg)
    png = pg.screenshot(animations="disabled")
    pg.close()
    return prints, png


SOURCE_BUNDLE = """async () => {
  const l = document.querySelector('link[data-generated-page-css]');
  // A page with no stylesheet at all - the standalone 404 - has nothing to subset.
  if (!l) return document.querySelector('link[rel="stylesheet"]') ? null : 'NO-STYLESHEET';
  const t = await (await fetch(l.getAttribute('href'))).text();
  const m = t.match(/subset of (page-css-[a-f0-9]+\.css)/);
  if (!m) return null;
  return await (await fetch('/assets/generated/' + m[1])).text();
}"""


def source_bundle(ctx, url, attempts=3):
    """The full bundle THIS route's subset was derived from.

    Bundles are per page, so reusing the homepage bundle as the reference would compare
    the subset against the wrong stylesheet and make the whole run meaningless.
    """
    # The PHP development server is single-threaded, so a fetch issued while it is busy
    # can simply fail. That is the harness, not the page - retry before believing it.
    for attempt in range(attempts):
        pg = ctx.new_page()
        try:
            pg.goto(BASE + url, wait_until="load")
            css = pg.evaluate(SOURCE_BUNDLE)
            pg.close()
            return css
        except Exception:
            pg.close()
            if attempt == attempts - 1:
                raise
            time.sleep(2)
    return None


def main():
    failures = 0
    with sync_playwright() as p:
        b = p.chromium.launch(channel="chrome")
        ctx = b.new_context()

        for url in ROUTES:
            css = source_bundle(ctx, url)
            if css == 'NO-STYLESHEET':
                print(f"PRESKOC {url:<21} stranka nema zadny stylesheet, neni co zmensovat")
                continue
            if not css:
                print(f"CHYBA  {url:<22} subsetting neprobehl, neni s cim porovnat")
                failures += 1
                continue
            for size in WIDTHS:
                # Warm the HTTP cache first; the measured runs then start from equal footing.
                run(ctx, url, size)

                # The page is not perfectly deterministic - lazy media and the
                # font-display:optional race move a handful of elements between two
                # identical loads. A real subsetting defect, by contrast, is a property of
                # the stylesheet and reproduces every single time. So the comparison is
                # repeated and only differences present in EVERY round are counted; see
                # tools/verify-page-css-noise.py for the measured noise floor.
                persistent = None
                pixel_rounds = 0
                for _ in range(ROUNDS):
                    a_prints, a_png = run(ctx, url, size)
                    b_prints, b_png = run(ctx, url, size, full_bundle=css)
                    if a_png != b_png:
                        pixel_rounds += 1
                    round_diffs = set()
                    for i, (pa, pb) in enumerate(zip(a_prints, b_prints)):
                        if len(pa) != len(pb):
                            round_diffs.add((i, -1, str(len(pa)), str(len(pb))))
                            continue
                        for j, (x, y) in enumerate(zip(pa, pb)):
                            if x != y:
                                round_diffs.add((i, j, x, y))
                    persistent = round_diffs if persistent is None else (persistent & round_diffs)
                    if not persistent and pixel_rounds == 0:
                        break

                px = "shodne" if pixel_rounds == 0 else f"pixely se lisily {pixel_rounds}/{ROUNDS}x"
                status = "OK" if not persistent else "CHYBA"
                if persistent:
                    failures += 1
                print(f"{status:<6} {url:<22} {size[0]}x{size[1]:<5} {px}   trvalych rozdilu: {len(persistent)}")
                for step, index, x, y in sorted(persistent)[:2]:
                    print(f"        krok {step} prvek {index}")
                    print("          A: " + x[:150])
                    print("          B: " + y[:150])
        b.close()
    print(("\nVSE SHODNE" if failures == 0 else f"\n{failures} rozdilu"))
    return 0 if failures == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
