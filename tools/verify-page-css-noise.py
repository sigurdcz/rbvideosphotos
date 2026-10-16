"""Measure the verifier's own noise floor.

Runs the identical scenario twice against the identical served page and reports how much
the fingerprints differ. Any difference here is nondeterminism in the page (the
font-display:optional race, lazy media, animation timing), not an effect of subsetting -
so this is the baseline that the A/B numbers have to be read against.

    python tools/verify-page-css-noise.py http://127.0.0.1:8161
"""
import sys

import importlib.util
from pathlib import Path

spec = importlib.util.spec_from_file_location("v", Path(__file__).with_name("verify-page-css.py"))
v = importlib.util.module_from_spec(spec)
sys.argv = [sys.argv[0]]  # keep the module from re-reading BASE from our argv
spec.loader.exec_module(v)

from playwright.sync_api import sync_playwright

BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8161"
v.BASE = BASE


def main():
    total = 0
    with sync_playwright() as p:
        b = p.chromium.launch(channel="chrome")
        ctx = b.new_context()
        for url in ["/", "/kontakt", "/nase-prace", "/sluzby", "/podminky-uziti"]:
            for size in [(390, 844), (1440, 900)]:
                v.run(ctx, url, size)
                a_prints, a_png = v.run(ctx, url, size)
                b_prints, b_png = v.run(ctx, url, size)
                worst = 0
                for pa, pb in zip(a_prints, b_prints):
                    if len(pa) != len(pb):
                        worst = max(worst, abs(len(pa) - len(pb)))
                        continue
                    worst = max(worst, sum(1 for x, y in zip(pa, pb) if x != y))
                total += worst
                px = "shodne" if a_png == b_png else "PIXELY SE LISI"
                print(f"{url:<20} {size[0]}x{size[1]:<5} nejhorsi krok: {worst:>4} prvku   {px}")
        b.close()
    print(f"\nsum sumu ruznych prvku pri IDENTICKE konfiguraci: {total}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
