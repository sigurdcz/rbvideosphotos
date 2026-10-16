"""Print the actual differing fingerprints for one route, both sides.

    python tools/verify-page-css-diff.py http://127.0.0.1:8161 /podminky-uziti 390 844 [--full]

Without --full both runs are served the subset, which isolates page nondeterminism.
With --full the second run is served the source bundle, which isolates the subset.
"""
import importlib.util
import sys
from pathlib import Path

argv = sys.argv[1:]
spec = importlib.util.spec_from_file_location("v", Path(__file__).with_name("verify-page-css.py"))
v = importlib.util.module_from_spec(spec)
sys.argv = [sys.argv[0]]
spec.loader.exec_module(v)

from playwright.sync_api import sync_playwright

BASE = argv[0]
URL = argv[1]
SIZE = (int(argv[2]), int(argv[3]))
FULL = "--full" in argv
v.BASE = BASE


def main():
    with sync_playwright() as p:
        b = p.chromium.launch(channel="chrome")
        ctx = b.new_context()
        css = v.source_bundle(ctx, URL) if FULL else None
        v.run(ctx, URL, SIZE)
        a_prints, _ = v.run(ctx, URL, SIZE)
        b_prints, _ = v.run(ctx, URL, SIZE, full_bundle=css)
        for step, (pa, pb) in enumerate(zip(a_prints, b_prints)):
            if len(pa) != len(pb):
                print(f"krok {step}: pocet prvku {len(pa)} vs {len(pb)}")
                continue
            bad = [j for j, (x, y) in enumerate(zip(pa, pb)) if x != y]
            if not bad:
                continue
            print(f"\n=== krok {step}: {len(bad)} prvku")
            for j in bad[:6]:
                print("  A:", pa[j][:220])
                print("  B:", pb[j][:220])
        b.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
