# -*- coding: utf-8 -*-
"""Clean brand-mark.png: the source has a baked-in checkerboard (fake
transparency) in the corners. Key out the light grey/white checker -> real
transparency, keeping the dark rounded tile + lime mark. Overwrites in place.
Run: python scripts/clean_mark.py
"""
import os
from PIL import Image

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(BASE, "public", "assets", "img", "brand-mark.png")


def main():
    im = Image.open(SRC).convert("RGBA")
    px = im.load()
    w, h = im.size
    keyed = 0
    for y in range(h):
        for x in range(w):
            r, g, b, a = px[x, y]
            mx, mn = max(r, g, b), min(r, g, b)
            # near-grayscale AND bright = the checkerboard (≈203 grey / 255 white)
            if (mx - mn) < 28 and mn > 150:
                px[x, y] = (r, g, b, 0)
                keyed += 1
    im.save(SRC, "PNG", optimize=True)
    print("Keyed", keyed, "checker px -> transparent. Saved", SRC, im.size)


if __name__ == "__main__":
    main()
