#!/usr/bin/env python3
"""Generate Google Play Store graphics for KupiTelefon."""

from __future__ import annotations

import os
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont, ImageFilter

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "assets" / "play-store"
LOGO = ROOT / "public" / "assets" / "img" / "pwa-512.png"
ACCOUNT_MOCKUP = ROOT / "assets" / "mockups" / "nalog-pregled-mockup.png"

GREEN = "#3D9A50"
GREEN_DARK = "#2D7A3E"
YELLOW = "#F5C518"
BG = "#F2F2F2"
WHITE = "#FFFFFF"
TEXT = "#1A1A1A"
MUTED = "#6B7280"
BORDER = "#E5E7EB"


def fonts() -> dict[str, ImageFont.FreeTypeFont | ImageFont.ImageFont]:
    win = Path(os.environ.get("WINDIR", r"C:\Windows")) / "Fonts"
    candidates = {
        "bold": [win / "segoeuib.ttf", win / "arialbd.ttf"],
        "regular": [win / "segoeui.ttf", win / "arial.ttf"],
        "semibold": [win / "seguisb.ttf", win / "segoeuib.ttf"],
    }

    def load(key: str, size: int):
        for path in candidates[key]:
            if path.exists():
                return ImageFont.truetype(str(path), size)
        return ImageFont.load_default()

    return {
        "bold32": load("bold", 32),
        "bold36": load("bold", 36),
        "bold44": load("bold", 44),
        "bold52": load("bold", 52),
        "bold64": load("bold", 64),
        "bold72": load("bold", 72),
        "semi28": load("semibold", 28),
        "semi32": load("semibold", 32),
        "reg24": load("regular", 24),
        "reg26": load("regular", 26),
        "reg28": load("regular", 28),
        "reg30": load("regular", 30),
        "reg34": load("regular", 34),
    }


def rounded_rect(
    draw: ImageDraw.ImageDraw,
    xy: tuple[int, int, int, int],
    radius: int,
    fill: str,
    outline: str | None = None,
    width: int = 1,
) -> None:
    draw.rounded_rectangle(xy, radius=radius, fill=fill, outline=outline, width=width)


def paste_logo(base: Image.Image, box: tuple[int, int, int, int]) -> None:
    logo = Image.open(LOGO).convert("RGBA")
    x0, y0, x1, y1 = box
    size = min(x1 - x0, y1 - y0)
    logo = logo.resize((size, size), Image.Resampling.LANCZOS)
    base.paste(logo, (x0 + (x1 - x0 - size) // 2, y0 + (y1 - y0 - size) // 2), logo)


def draw_status_bar(draw: ImageDraw.ImageDraw, width: int, f: dict) -> None:
    draw.text((48, 36), "9:41", fill=TEXT, font=f["semi28"])
    draw.rounded_rectangle((width - 170, 42, width - 48, 72), radius=16, fill=TEXT)


def draw_bottom_nav(draw: ImageDraw.ImageDraw, width: int, height: int, active: int, f: dict) -> None:
    y0 = height - 150
    draw.rectangle((0, y0, width, height), fill=WHITE)
    draw.line((0, y0, width, y0), fill=BORDER, width=2)
    labels = ["Oglasi", "Pretraga", "Dodaj", "Poruke", "Nalog"]
    xs = [width * i / 5 for i in range(5)]
    for i, (x, label) in enumerate(zip(xs, labels)):
        color = GREEN if i == active else MUTED
        cx = int(x + width / 10)
        if i == 2:
            rounded_rect(draw, (cx - 42, y0 + 18, cx + 42, y0 + 102), 42, GREEN)
            draw.text((cx - 10, y0 + 42), "+", fill=WHITE, font=f["bold44"])
        else:
            draw.ellipse((cx - 16, y0 + 28, cx + 16, y0 + 60), fill=color if i == active else "#D1D5DB")
            draw.text((cx - 42, y0 + 72), label, fill=color, font=f["reg24"])


def screenshot_canvas(title: str) -> tuple[Image.Image, ImageDraw.ImageDraw, dict]:
    img = Image.new("RGB", (1080, 1920), BG)
    draw = ImageDraw.Draw(img)
    f = fonts()
    draw_status_bar(draw, 1080, f)
    draw.text((48, 110), "KupiTelefon", fill=GREEN_DARK, font=f["bold52"])
    draw.text((48, 190), title, fill=TEXT, font=f["bold36"])
    return img, draw, f


def make_icon_512() -> None:
    icon = Image.new("RGBA", (512, 512), WHITE)
    paste_logo(icon, (32, 32, 480, 480))
    icon.convert("RGB").save(OUT / "icon-512.png", optimize=True)


def make_feature_graphic() -> None:
    img = Image.new("RGB", (1024, 500), WHITE)
    draw = ImageDraw.Draw(img)
    f = fonts()

    for x in range(420):
        t = x / 420
        r = int(61 + (45 - 61) * t)
        g = int(154 + (122 - 154) * t)
        b = int(80 + (62 - 80) * t)
        draw.line((x, 0, x, 500), fill=(r, g, b))

    draw.polygon([(420, 0), (1024, 0), (1024, 500), (520, 500)], fill=BG)
    draw.rectangle((0, 430, 1024, 500), fill=YELLOW)

    paste_logo(img, (56, 130, 200, 274))
    draw.text((220, 145), "KupiTelefon", fill=WHITE, font=f["bold64"])
    draw.text((220, 225), "Kupuj i prodaj telefone u Srbiji", fill="#E8F5E9", font=f["reg30"])
    draw.text((220, 285), "Besplatni oglasi · Push poruke · Brz pristup", fill="#E8F5E9", font=f["reg26"])

    draw.text((56, 448), "kupitelefon.rs", fill=TEXT, font=f["bold32"])

    # phone outline right
    px0, py0, px1, py1 = 620, 55, 980, 430
    rounded_rect(draw, (px0, py0, px1, py1), 28, WHITE, outline=BORDER, width=3)
    inner = img.crop((px0 + 18, py0 + 18, px1 - 18, py1 - 18))
    mini = Image.new("RGB", (px1 - px0 - 36, py1 - py0 - 36), BG)
    d = ImageDraw.Draw(mini)
    d.text((24, 20), "iPhone 15 Pro", fill=TEXT, font=f["semi28"])
    d.text((24, 58), "89.990 RSD", fill=GREEN_DARK, font=f["bold32"])
    rounded_rect(d, (24, 110, 300, 250), 16, WHITE, outline=BORDER, width=2)
    d.text((40, 280), "Samsung S24 Ultra", fill=TEXT, font=f["semi28"])
    d.text((40, 318), "720 EUR", fill=GREEN_DARK, font=f["bold32"])
    img.paste(mini, (px0 + 18, py0 + 18))

    img.save(OUT / "feature-graphic-1024x500.png", optimize=True)


def make_screenshot_home() -> None:
    img, draw, f = screenshot_canvas("Najnoviji oglasi")
    cards = [
        ("iPhone 15 Pro 256GB", "89.990 RSD", "Beograd"),
        ("Samsung Galaxy S24", "720 EUR", "Novi Sad"),
        ("Xiaomi 14T Pro", "62.000 RSD", "Niš"),
        ("AirPods Pro 2", "24.990 RSD", "Kragujevac"),
    ]
    y = 280
    for title, price, city in cards:
        rounded_rect(draw, (48, y, 1032, y + 220), 24, WHITE, outline=BORDER, width=2)
        rounded_rect(draw, (72, y + 28, 232, y + 188), 16, "#E5E7EB")
        draw.text((260, y + 36), title, fill=TEXT, font=f["semi32"])
        draw.text((260, y + 88), price, fill=GREEN_DARK, font=f["bold36"])
        draw.text((260, y + 142), city, fill=MUTED, font=f["reg28"])
        y += 250
    draw_bottom_nav(draw, 1080, 1920, 0, f)
    img.save(OUT / "screenshot-01-home.png", optimize=True)


def make_screenshot_ad() -> None:
    img, draw, f = screenshot_canvas("Detalji oglasa")
    rounded_rect(draw, (48, 280, 1032, 980), 24, WHITE, outline=BORDER, width=2)
    rounded_rect(draw, (72, 304, 1008, 700), 20, "#E5E7EB")
    draw.text((72, 730), "iPhone 15 Pro 256GB — odlično stanje", fill=TEXT, font=f["bold36"])
    draw.text((72, 790), "89.990 RSD", fill=GREEN_DARK, font=f["bold52"])
    draw.text((72, 870), "Beograd · Aktivan · 142 pregleda", fill=MUTED, font=f["reg28"])
    rounded_rect(draw, (72, 1010, 500, 1100), 20, GREEN)
    draw.text((170, 1036), "Pošalji poruku", fill=WHITE, font=f["semi32"])
    rounded_rect(draw, (540, 1010, 1008, 1100), 20, WHITE, outline=GREEN, width=3)
    draw.text((700, 1036), "Pozovi", fill=GREEN_DARK, font=f["semi32"])
    draw.text((72, 1160), "Garancija, baterija 92%, bez zamene delova.", fill=TEXT, font=f["reg30"])
    draw.text((72, 1210), "Lično preuzimanje ili slanje.", fill=MUTED, font=f["reg28"])
    draw_bottom_nav(draw, 1080, 1920, 0, f)
    img.save(OUT / "screenshot-02-ad-detail.png", optimize=True)


def make_screenshot_messages() -> None:
    img, draw, f = screenshot_canvas("Poruke")
    chats = [
        ("Marko", "Da li je iPhone još dostupan?", True),
        ("Ana", "Može li preuzimanje danas?", False),
        ("MobilCentar", "Šaljemo ponudu za S24", False),
        ("Petar", "Hvala, dogovorili smo se", False),
    ]
    y = 280
    for name, preview, unread in chats:
        rounded_rect(draw, (48, y, 1032, y + 150), 20, WHITE, outline=BORDER, width=2)
        draw.ellipse((72, y + 35, 142, y + 105), fill=GREEN if unread else "#D1D5DB")
        draw.text((170, y + 32), name, fill=TEXT, font=f["semi32"])
        draw.text((170, y + 82), preview, fill=MUTED, font=f["reg28"])
        if unread:
            draw.ellipse((980, y + 58, 1010, y + 88), fill=GREEN)
        y += 170

    rounded_rect(draw, (120, 1180, 760, 1280), 24, WHITE, outline=BORDER, width=2)
    draw.text((150, 1218), "Zdravo, da — dostupan je.", fill=TEXT, font=f["reg28"])
    rounded_rect(draw, (420, 1310, 960, 1410), 24, GREEN)
    draw.text((470, 1348), "Možemo se čuti posle 18h?", fill=WHITE, font=f["reg28"])
    draw_bottom_nav(draw, 1080, 1920, 3, f)
    img.save(OUT / "screenshot-03-messages.png", optimize=True)


def make_screenshot_account() -> None:
    img = Image.new("RGB", (1080, 1920), BG)
    mock = Image.open(ACCOUNT_MOCKUP).convert("RGB")
    mock = mock.resize((980, 1470), Image.Resampling.LANCZOS)
    img.paste(mock, (50, 220))
    draw = ImageDraw.Draw(img)
    f = fonts()
    draw.text((48, 80), "KupiTelefon", fill=GREEN_DARK, font=f["bold52"])
    draw.text((48, 160), "Tvoj nalog i oglasi", fill=TEXT, font=f["bold36"])
    img.save(OUT / "screenshot-04-account.png", optimize=True)


def make_readme() -> None:
    text = """# KupiTelefon — Play Store grafika

Upload u Google Play Console → Store listing → Graphics:

| Fajl | Gde u Play Console |
|------|-------------------|
| icon-512.png | App icon (512 x 512) |
| feature-graphic-1024x500.png | Feature graphic (1024 x 500) |
| screenshot-01-home.png | Phone screenshots |
| screenshot-02-ad-detail.png | Phone screenshots |
| screenshot-03-messages.png | Phone screenshots |
| screenshot-04-account.png | Phone screenshots |

Minimalno 2 screenshot-a; preporuceno 4.

Generisano: tools/generate_play_store_graphics.py
"""
    (OUT / "README.md").write_text(text, encoding="utf-8")


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    make_icon_512()
    make_feature_graphic()
    make_screenshot_home()
    make_screenshot_ad()
    make_screenshot_messages()
    make_screenshot_account()
    make_readme()
    print(f"Gotovo: {OUT}")
    for p in sorted(OUT.glob("*.png")):
        print(f"  {p.name}  ({p.stat().st_size // 1024} KB)")


if __name__ == "__main__":
    main()
