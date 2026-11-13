"""Make web-sized copies of the menu photos.

Originals in public/assets/images/menu/items/ stay untouched (full quality for
print or re-cropping). Pages load the copies from public/assets/images/menu/web/.

    python3 -m pip install Pillow     # once
    python3 scripts/build-web-images.py
"""
from pathlib import Path
from PIL import Image, ImageOps

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "public/assets/images/menu/items"
DST = ROOT / "public/assets/images/menu/web"
LONG_EDGE = 960   # ~2x the largest card a dish appears in
QUALITY = 74

DST.mkdir(exist_ok=True)
for src in sorted(SRC.glob("*.jpg")):
    with Image.open(src) as im:
        im = ImageOps.exif_transpose(im).convert("RGB")
        im.thumbnail((LONG_EDGE, LONG_EDGE), Image.LANCZOS)
        im.save(DST / src.name, "JPEG", quality=QUALITY, optimize=True, progressive=True)
print(f"wrote {len(list(DST.glob('*.jpg')))} images to {DST.relative_to(ROOT)}")
