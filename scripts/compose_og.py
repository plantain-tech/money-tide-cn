# -*- coding: utf-8 -*-
"""Compose the 1200x630 OG share image for 钱潮 Money Tide from the brand mark.
Black canvas + lime accent, the mark on the left, stacked bilingual lockup +
tagline. Run: python scripts/compose_og.py
"""
import os
from PIL import Image, ImageDraw, ImageFont

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
IMG_DIR = os.path.join(BASE, "public", "assets", "img")
MARK = os.path.join(IMG_DIR, "brand-mark.png")
OUT = os.path.join(IMG_DIR, "og-money-tide.png")

INK = (10, 10, 10, 255)
LIME = (220, 255, 0, 255)
WHITE = (245, 245, 240, 255)
GREY = (175, 175, 175, 255)

W, H = 1200, 630
YAHEI_BD = r"C:\Windows\Fonts\msyhbd.ttc"
YAHEI = r"C:\Windows\Fonts\msyh.ttc"


def font(path, size):
    return ImageFont.truetype(path, size)


def text_w(draw, s, f):
    b = draw.textbbox((0, 0), s, font=f)
    return b[2] - b[0]


def main():
    canvas = Image.new("RGBA", (W, H), INK)
    draw = ImageDraw.Draw(canvas)

    # Bottom lime accent bar (brand signature).
    draw.rectangle([0, H - 14, W, H], fill=LIME)

    # --- Brand mark on the left (alpha-composited so corners stay ink, never transparent) ---
    m = 300
    mark = Image.open(MARK).convert("RGBA").resize((m, m), Image.LANCZOS)
    mark_x, mark_y = 90, (H - m) // 2 - 7
    canvas.alpha_composite(mark, (mark_x, mark_y))

    # --- Text block on the right ---
    tx = mark_x + m + 70  # 460
    f_cn = font(YAHEI_BD, 104)       # 钱潮
    f_en = font(YAHEI_BD, 40)        # MONEY TIDE
    f_tag = font(YAHEI_BD, 44)       # tagline
    f_desc = font(YAHEI, 29)         # description
    f_url = font(YAHEI_BD, 27)       # url

    # Line 1: 钱潮 (large)
    draw.text((tx, 120), "钱潮", font=f_cn, fill=WHITE)
    cn_w = text_w(draw, "钱潮", f_cn)
    # MONEY TIDE next to it, baseline-aligned lower
    draw.text((tx + cn_w + 24, 196), "MONEY TIDE", font=f_en, fill=LIME)

    # Lime underline accent under the lockup
    draw.rectangle([tx + 2, 252, tx + 300, 263], fill=LIME)

    # Tagline
    draw.text((tx, 300), "每天 5 分钟，看懂全球市场", font=f_tag, fill=WHITE)
    # Description
    draw.text((tx, 372), "智能综合体 驱动的全球财经 · 科技 · 商业简报", font=f_desc, fill=GREY)
    # URL near bottom
    draw.text((tx, 500), "moneytidecn.avanturadeals.com", font=f_url, fill=GREY)

    canvas.convert("RGB").save(OUT, "PNG", optimize=True)
    print("Wrote", OUT, (W, H))


if __name__ == "__main__":
    main()
