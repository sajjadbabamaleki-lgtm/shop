"""Which of the shop's two navy Samba sandals is which of the client's two sets.

⛔ TEMPORARY.

Two of the client's sets are white with navy stripes and they are not the same
shoe: one has a navy sole and a navy toe, the other is white below the stripes.
The shop has «سرمه ای» and «سفید سرمه ای» and the names alone do not say which
is which. So compare, with one instrument, on the thing that actually differs:
how much of the shoe is navy.
"""

import glob
import subprocess
import urllib.parse

from PIL import Image

SHOP = {
    'سرمه-ای': None,
    'سفید-سرمه-ای': None,
}
BASE = 'صندل-ادیداس-سامبا-چسبی-Adidas-Samba-Sandal-رنگ-'


def navy_share(im: Image.Image) -> tuple[int, int]:
    """Percent of the shoe that is navy, and percent of the frame that is shoe."""
    im = im.convert('RGB').resize((128, 128))
    px = im.load()

    ring = []
    for x in range(128):
        ring += [px[x, 0], px[x, 127]]
    for y in range(128):
        ring += [px[0, y], px[127, y]]
    ground = tuple(sorted(p[i] for p in ring)[len(ring) // 2] for i in range(3))

    shoe = navy = 0
    for x in range(128):
        for y in range(128):
            p = px[x, y]
            if sum(abs(p[i] - ground[i]) for i in range(3)) <= 60:
                continue
            shoe += 1
            # Navy: blue clearly ahead of red, and dark.
            if p[2] - p[0] > 12 and sum(p) / 3 < 130:
                navy += 1

    return (round(100 * navy / shoe) if shoe else 0, round(100 * shoe / (128 * 128)))


print('── the shop, as it stands ──')
for colour in SHOP:
    slug = urllib.parse.quote(BASE + colour, safe='-')
    page = subprocess.run(
        ['curl', '-sS', '-m', '60', '--compressed', f'https://vikyplus.ir/products/{slug}'],
        check=False, capture_output=True).stdout.decode('utf-8', 'replace')

    import re
    shots = [s for s in re.findall(r'src="([^"]+\.(?:jpg|jpeg|png|webp))"', page)
             if '/assets/img/' not in s]
    if not shots:
        print(f'{colour}: no photograph')
        continue

    url = shots[0] if shots[0].startswith('http') else 'https://vikyplus.ir' + shots[0]
    subprocess.run(['curl', '-sS', '-m', '60', '-o', '/tmp/shop.jpg', url], check=False)
    navy, cover = navy_share(Image.open('/tmp/shop.jpg'))
    print(f'{colour:16} navy={navy:3}% of the shoe   (shoe is {cover}% of the frame)')

print()
print('── the two sets the client sent ──')
for d in ['navy', 'navywhite']:
    f = sorted(glob.glob(f'storefront/public/assets/img/product/samba-sandal-{d}/*.jpg'))[0]
    navy, cover = navy_share(Image.open(f))
    print(f'{d:16} navy={navy:3}% of the shoe   (shoe is {cover}% of the frame)')
