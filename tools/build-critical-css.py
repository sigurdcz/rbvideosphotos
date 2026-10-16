"""Generate per-page critical CSS for the active theme.

KNOWN DEFECT - this script produces broken CSS as written.

Chrome reports rule offsets relative to the stylesheet, but a rule nested in
@media(max-width:700px){...} is reported as the bare inner rule. Slicing those offsets
therefore drops every media wrapper: a generated file from this theme contained 0
@media blocks where the source has 54, so mobile-only rules applied at every width.
A visual A/B over 10 routes showed 95-99% of pixels differing, with collapsed layouts.

A correct version has to rebuild the @media / @supports context around each kept rule -
either by parsing the stylesheet itself and mapping offsets onto the parse tree, or by
walking CSSOM parent rules instead of raw offsets.

Loads every route in a headless Chrome, records which CSS rules the browser uses for the
initial render, keeps only those whose selectors match something inside the first screen,
and writes the subset to public/assets/critical/<page-key>.css.

STATUS: measured, NOT wired into the renderer. Keep this script for future work.

Measured on 2026-09-17 (Lighthouse 13.4.1, mobile, simulated 4G, local server):

  full bundle blocking (shipped)   FCP 4.1 s  SI 4.1 s  render-blocking 2440 ms
  inline critical + deferred       FCP 1.9 s  SI 1.9 s  render-blocking   30 ms

The speed win is real, but deferring the bundle turns layout into a race: three
consecutive runs measured CLS 0.002, 0.9202 and 0.002 on the same build, because the
remaining CSS sometimes lands after first paint and re-lays the page out. Production
currently reports CLS 0, so the trade was refused and the change reverted.

To make this shippable the critical set has to cover every geometry-affecting rule for
the route, so the late bundle can only change paint, never layout. A per-page bundle that
stays render-blocking is the safer alternative: same protection as today, less CSS.

Usage:  python tools/build-critical-css.py http://127.0.0.1:8140
"""

import json, os, re, sys
from playwright.sync_api import sync_playwright

BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8140"
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUTDIR = os.path.join(ROOT, "public", "assets", "critical")

# url path -> page key used by body[data-cms-page-key]
ROUTES = {
    "/": "homepage", "/o-nas": "o-nas", "/cenik": "cenik", "/blog": "blog",
    "/kontakt": "kontakt", "/nase-prace": "nase-prace", "/faq": "faq",
    "/hodnoceni": "hodnoceni", "/sluzby": "sluzby", "/podminky-uziti": "podminky-uziti",
}

def critical_for(ctx, path):
    """Rules used for the first render whose selector also matches something inside the
    initial viewport. Chrome's rule usage is not viewport aware, so without this filter
    the 'critical' set includes every below-the-fold rule and grows larger than the
    bundle it is meant to replace."""
    spans, text = set(), None
    for w, h in ((390, 844), (1440, 900)):
        pg = ctx.new_page()
        pg.set_viewport_size({"width": w, "height": h})
        cdp = ctx.new_cdp_session(pg)
        cdp.send("DOM.enable"); cdp.send("CSS.enable")
        cdp.send("CSS.startRuleUsageTracking")
        pg.goto(BASE + path, wait_until="load")
        pg.wait_for_timeout(400)
        res = cdp.send("CSS.stopRuleUsageTracking")
        cache = {}
        candidates = []
        for r in res.get("ruleUsage", []):
            if not r.get("used"):
                continue
            sid = r["styleSheetId"]
            if sid not in cache:
                try:
                    cache[sid] = cdp.send("CSS.getStyleSheetText", {"styleSheetId": sid})["text"]
                except Exception:
                    cache[sid] = None
            t = cache[sid]
            if not t or len(t) < 50000:
                continue
            text = text or t
            candidates.append((r["startOffset"], r["endOffset"]))

        # Ask the page which of those selectors touch the first screen.
        chunks = [text[s:e] for s, e in candidates]
        keep = pg.evaluate("""(chunks) => {
            const vh = window.innerHeight;
            return chunks.map(css => {
              const m = css.match(/^\s*([^{@]+)\{/);
              if (!m) return true;                       // @media/@supports wrapper: keep
              const sels = m[1].split(',').map(s => s.replace(/::?[a-z-]+(\([^)]*\))?/gi,'').trim()).filter(Boolean);
              for (const sel of sels) {
                let nodes = [];
                try { nodes = document.querySelectorAll(sel); } catch (e) { return true; }
                for (const n of nodes) {
                  const r = n.getBoundingClientRect();
                  if (r.bottom >= 0 && r.top <= vh && r.width > 0 && r.height > 0) return true;
                }
              }
              return false;
            });
        }""", chunks)
        for (span, ok) in zip(candidates, keep):
            if ok:
                spans.add(span)
        pg.close()
    if not text:
        return ""
    return "\n".join(text[s:e] for s, e in sorted(spans))

def main():
    os.makedirs(OUTDIR, exist_ok=True)
    with sync_playwright() as p:
        b = p.chromium.launch(channel="chrome")
        ctx = b.new_context()
        for path, key in ROUTES.items():
            css = critical_for(ctx, path)
            if not css:
                print(f"  {key}: bundle nenalezen, preskakuji"); continue
            out = os.path.join(OUTDIR, key + ".css")
            with open(out, "w", encoding="utf-8", newline="") as fh:
                fh.write(css)
            print(f"  {key}: {len(css)//1024} KiB")
        b.close()

if __name__ == "__main__":
    main()
