"""Prove the deduplicated variable webfonts still serve every declared weight.

The upright brand files are variable, so after deduplication one file serves 400/500/700
where twelve urls over six byte-identical payloads used to. The failure that guards
against is silent: if the weight descriptor stopped driving the variable axis, every
weight would render from the file's default instance - Montserrat Thin - and the page
would merely look a little off rather than break. Three weights of one string must
produce three increasing widths.

Weights are probed through explicitly constructed FontFace objects, not through the page's
own stylesheet, because font-display:optional refuses to render a face that missed its
~100ms window even after document.fonts.load() brings it in. Measuring through the page
would compare "the fonts won the race" against "they lost it" instead of weight against
weight - that artifact cost an hour of confusion the first time round.

What to probe is read out of fonts.css rather than hardcoded, so this cannot keep passing
after a face is added, removed or repointed.

    python tools/verify-fonts.py http://127.0.0.1:8161
"""
import os
import re
import sys

from playwright.sync_api import sync_playwright

BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8161"
FONTS_CSS = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                         "public", "assets", "fonts", "fonts.css")

# latin-ext carries no ASCII at all, so probing it with "Svatebni" measures pure fallback
# and every weight comes out the same width. Each file is probed with text it really holds.
ASCII_TEXT = "Svatebni fotograf"
CZECH_TEXT = "ěščřžůďťň"
# At 40px two Cormorant weights differ by about a pixel; 200px gives the comparison room.
PROBE_SIZE = 200

MEASURE = """async ([url, weights, text, size]) => {
  const out = [];
  for (const w of weights) {
    const name = 'Probe' + w + '_' + Math.random().toString(36).slice(2);
    const face = new FontFace(name, 'url(' + url + ')', { weight: String(w) });
    await face.load();
    document.fonts.add(face);
    const el = document.createElement('span');
    el.style.cssText = 'position:absolute;left:-9999px;white-space:pre;font-size:' + size
      + 'px;font-family:' + name;
    el.textContent = text;
    document.body.appendChild(el);
    out.push(Math.round(el.getBoundingClientRect().width * 100) / 100);
    el.remove();
  }
  return out;
}"""


def declared_faces():
    """Every (url, style) fonts.css declares, with the weights declared against it."""
    css = open(FONTS_CSS, encoding="utf-8").read()
    faces = {}
    for block in re.findall(r"@font-face\s*\{(.*?)\}", css, re.S):
        src = re.search(r"url\((/assets/fonts/[^)]+\.woff2)\)", block)
        weight = re.search(r"font-weight:\s*([^;]+);", block)
        style = re.search(r"font-style:\s*([^;]+);", block)
        if not (src and weight):
            continue
        key = (src.group(1), style.group(1).strip() if style else "normal")
        faces.setdefault(key, set()).add(int(weight.group(1).strip().split()[0]))
    return {k: sorted(v) for k, v in faces.items()}


def launch(p):
    """Branded Chrome where it is installed, bundled Chromium otherwise."""
    try:
        return p.chromium.launch(channel="chrome")
    except Exception:
        print("(chrome nenalezen, pouzivam chromium)")
        return p.chromium.launch()


def main():
    failures = []
    faces = declared_faces()
    if not faces:
        print("CHYBA: fonts.css nedeklaruje zadny zdroj")
        return 1

    with sync_playwright() as p:
        b = launch(p)
        pg = b.new_context().new_page()

        requests = []
        pg.on("response", lambda r: requests.append((r.url, r.status)) if ".woff2" in r.url else None)
        pg.goto(BASE + "/", wait_until="networkidle")
        pg.evaluate("() => document.fonts.ready")

        print("=== pozadavky na pisma")
        urls = [u for u, _ in requests]
        for u, s in requests:
            print(f"  {s}  {u.split('/')[-1]}")
            if s != 200:
                failures.append(f"{u.split('/')[-1]} vratil {s}")
        if len(urls) != len(set(urls)):
            failures.append("stejny soubor pisma se stahuje vickrat")
        if not urls:
            failures.append("nestahlo se zadne pismo")

        print("\n=== sirka textu podle vahy z JEDNOHO souboru (musi rust)")
        for (url, style), weights in sorted(faces.items()):
            name = url.split("/")[-1]
            if len(weights) < 2:
                print(f"  {name:<38} {style:<7} jedina vaha {weights}, neni co porovnat")
                continue
            text = CZECH_TEXT if "latin-ext" in url else ASCII_TEXT
            widths = pg.evaluate(MEASURE, [url, weights, text, PROBE_SIZE])
            up = all(x < y for x, y in zip(widths, widths[1:]))
            print(f"  {name:<38} {style:<7} {weights} = {widths}   {'rostouci' if up else 'NEROSTE'}")
            if not up:
                failures.append(f"{url}: vahy nerostou, variabilni osa se nepouziva")

        print("\n=== dostupnost pro cesky text (obe poloviny abecedy)")
        for spec in ('400 40px "Montserrat"', '500 40px "Cormorant Garamond"',
                     'italic 400 40px "Cormorant Garamond"'):
            ok = pg.evaluate("(s) => document.fonts.check(s, 'Svatební fotograf ěščřžůďťň')", spec)
            print(f"  {spec:<40} {ok}")
            if not ok:
                failures.append(f"{spec} neni k dispozici pro cesky text")

        b.close()

    print()
    if failures:
        for f in failures:
            print("CHYBA:", f)
        return 1
    print("VSE OK")
    return 0


if __name__ == "__main__":
    sys.exit(main())
