"""Regenerates every optimized image in assets/ from the originals in _src/.

    python tools/optimize.py

Photos -> AVIF at several widths plus a JPEG fallback. Logos -> trimmed WebP at
~2.5-3x their largest display height. The one-colour Община Сливен crests are
derived from the colour original (the municipality asked for black and white).
"""
from pathlib import Path
from PIL import Image, ImageChops

ROOT = Path(__file__).resolve().parent.parent
SRC, OUT = ROOT / "_src", ROOT / "assets"


def resize_w(im, w):
    return im if im.width <= w else im.resize((w, round(im.height * w / im.width)), Image.LANCZOS)


def resize_h(im, h):
    return im if im.height <= h else im.resize((round(im.width * h / im.height), h), Image.LANCZOS)


def trim(im):
    """Crop to content: the alpha bbox if transparent, otherwise the non-white bbox."""
    a = im.getchannel("A")
    if a.getextrema()[0] < 255:
        box = a.point(lambda v: 255 if v > 8 else 0).getbbox()
    else:
        rgb = im.convert("RGB")
        diff = ImageChops.difference(rgb, Image.new("RGB", rgb.size, "white")).convert("L")
        box = diff.point(lambda v: 255 if v > 12 else 0).getbbox()
    return im.crop(box) if box else im


def save(im, name, **kw):
    p = OUT / name
    fmt = {"avif": "AVIF", "webp": "WEBP", "jpg": "JPEG"}[name.rsplit(".", 1)[1]]
    if fmt == "JPEG":
        im = im.convert("RGB")
    im.save(p, fmt, **kw)
    print(f"{name:28} {im.width:>5}x{im.height:<5} {p.stat().st_size / 1024:6.1f} KB")


# ── hero ──────────────────────────────────────────────────────────
hero = Image.open(SRC / "hero-sliven.jpg").convert("RGB")
for w in (960, 1600, 2400):
    save(resize_w(hero, w), f"hero-{w}.avif", quality=52, speed=4)
save(resize_w(hero, 1600), "hero-1600.jpg", quality=78, optimize=True, progressive=True)

# share image, 1200x630, cropped around the same focus as --hero-focus (72% 38%)
big = resize_w(hero, 1200)
top = min(max(round(big.height * 0.38 - 315), 0), big.height - 630)
save(big.crop((0, top, 1200, top + 630)), "og.jpg", quality=82, optimize=True, progressive=True)

# ── gallery ───────────────────────────────────────────────────────
for i in range(1, 7):
    g = Image.open(SRC / f"gallery-{i}.jpg").convert("RGB")
    for w in (400, 800):
        save(resize_w(g, w), f"gallery-{i}-{w}.avif", quality=55, speed=4)
    save(g, f"gallery-{i}.jpg", quality=76, optimize=True, progressive=True)

# ── logos ─────────────────────────────────────────────────────────
for src, out, h in [
    ("org-urbo-studio.png", "logo-urbo-studio.webp", 102),        # shown 34px
    ("org-digital4.png", "logo-digital4.webp", 102),              # shown 34px
    ("org-rim-sliven.png", "logo-rim-sliven.webp", 160),          # shown 52-64px
    ("partner-sliven-tourism.png", "logo-sliven-tourism.webp", 160),
]:
    save(resize_h(trim(Image.open(SRC / src).convert("RGBA")), h), out, quality=90, method=6)

# ── one-colour crests: luminance <= LO is full ink, >= HI is none ──
LO, HI = 150, 190
color = Image.open(SRC / "obshtina-crest-color.png").convert("RGBA")
ramp = color.convert("RGB").convert("L").point(
    lambda v: 255 if v <= LO else 0 if v >= HI else int(255 * (HI - v) / (HI - LO)))
ink = ImageChops.multiply(ramp, color.getchannel("A"))
for rgb, name in (((255, 255, 255), "crest-white.webp"), ((0, 0, 0), "crest-black.webp")):
    mono = Image.new("RGBA", color.size, rgb + (0,))
    mono.putalpha(ink)
    save(resize_h(mono.crop(mono.getbbox()), 160), name, quality=90, method=6)

# ── one-colour partners: the logo's alpha as a black silhouette, so they sit
#    with the black crest. Sources are the originals behind the white logos on
#    the main TravelX sponsors screen (Figma 4822:559).
for src, out, h in [
    ("partner-seomax.png", "partner-seomax.webp", 132),            # shown 44px
    ("partner-img-connect-card.png", "partner-img-connect-card.webp", 132),
    ("partner-devin.png", "partner-devin.webp", 132),
    ("partner-dives.png", "partner-dives.webp", 160),              # white-on-transparent original
    ("partner-abtta.png", "partner-abtta.webp", 132),
]:
    a = Image.open(SRC / src).convert("RGBA").getchannel("A")
    mono = Image.new("RGBA", a.size, (0, 0, 0, 0))
    mono.putalpha(a)
    save(resize_h(mono.crop(a.point(lambda v: 255 if v > 8 else 0).getbbox()), h), out,
         quality=90, method=6)

# ── partners shown in their own colours, as supplied (Nov 2026)
for src, out, h in [
    ("partner-travelnews.png", "partner-travelnews.webp", 132),
    ("partner-sliven-press.png", "partner-sliven-press.webp", 132),
    ("partner-hotelspoint.png", "partner-hotelspoint.webp", 132),
]:
    save(resize_h(trim(Image.open(SRC / src).convert("RGBA")), h), out, quality=90, method=6)

# TravelRadar's original is for dark backgrounds: "Travel" and the tagline are
# white. Those (any unsaturated pixel) go black; cyan and magenta stay.
tr = Image.open(SRC / "partner-travelradar.png").convert("RGBA")
px = tr.load()
for y in range(tr.height):
    for x in range(tr.width):
        r, g, b, a = px[x, y]
        if a and max(r, g, b) - min(r, g, b) < 40:
            px[x, y] = (0, 0, 0, a)
save(resize_h(trim(tr), 132), "partner-travelradar.webp", quality=90, method=6)

# ── speakers: transparent cut-outs from the Figma announcement posts ──
for name in ("yanev", "ivanov", "petrova", "todorov", "draganova-2",
             "alexiev", "dimitrov", "todorova", "sirakov",
             "rangelova", "zlatina", "lilov", "markov", "stoyanov",
             "georgiev", "gramova", "stoilov", "mermekliev"):
    im = trim(Image.open(SRC / f"speaker-{name}.png").convert("RGBA"))
    save(resize_w(im, 640), f"speaker-{name}.webp", quality=82, method=6)

total = sum(p.stat().st_size for p in OUT.rglob("*") if p.is_file())
print(f"assets/ total: {total / 1024:.0f} KB")
