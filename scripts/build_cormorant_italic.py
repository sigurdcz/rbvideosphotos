"""Build the complete Czech-capable Regular italic from the official Google Fonts VF.

Usage: python scripts/build_cormorant_italic.py downloaded-upstream.ttf
Development dependency: fonttools[woff]==4.65.0 (not needed on the hosting).
"""
import hashlib
import sys
from pathlib import Path
from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont

source = Path(sys.argv[1])
expected = '0f48ea6abb2084537854f7174c470991a463b13036309e3b50a81511611c530d'
if hashlib.sha256(source.read_bytes()).hexdigest() != expected:
    raise SystemExit('Unexpected upstream file; verify the source and version explicitly.')
font = instantiateVariableFont(TTFont(source), {'wght': 400}, updateFontNames=True)
if not all(ord(c) in font.getBestCmap() for c in 'ěščřžýáíéůúďťňĚŠČŘŽÝÁÍÉŮÚĎŤŇ'):
    raise SystemExit('Missing Czech glyphs')
font.flavor = 'woff2'
target = Path(__file__).resolve().parents[1] / 'public/assets/fonts/cormorantgaramond-italic-v4.001.woff2'
font.save(target)
print(target)
