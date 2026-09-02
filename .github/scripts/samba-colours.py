"""Which Samba sandal is which colour, read off its own photograph.

⛔ TEMPORARY. The colour is in every slug, but «قهوه ای» and «کرم قهوه ای» are
two names for two browns, and putting a photograph on the wrong one is exactly
the mistake this repository keeps paying for. So: fetch each colourway's own
page, take the photograph it shows, and print the average colour and lightness
of the shoe itself (the middle of the frame, away from the studio ground).
"""

import re
import subprocess
import urllib.parse

from PIL import Image

BASE = ('صندل-ادیداس-سامبا-چسبی-Adidas-Samba-Sandal-رنگ-')
COLOURS = [
    'سرمه-ای',
    'سفید-آبی-روشن',
    'سفید-سرمه-ای',
    'سفید-صورتی',
    'سفید-مشکی',
    'قهوه-ای',
    'کرم-قهوه-ای',
]


def get(url, out=None):
    cmd = ['curl', '-sS', '-m', '60', '--compressed', url]
    if out:
        subprocess.run(cmd + ['-o', out], check=False)
        return b''
    return subprocess.run(cmd, check=False, capture_output=True).stdout


for n, colour in enumerate(COLOURS, start=1):
    slug = urllib.parse.quote(BASE + colour, safe='-')
    page = get(f'https://vikyplus.ir/products/{slug}').decode('utf-8', 'replace')

    shots = [s for s in re.findall(r'src="([^"]+\.(?:jpg|jpeg|png|webp))"', page)
             if '/assets/img/' not in s]

    if not shots:
        print(f'{colour}: page has no product photograph (404?)')
        continue

    url = shots[0] if shots[0].startswith('http') else 'https://vikyplus.ir' + shots[0]
    get(url, f'/tmp/s{n}')

    im = Image.open(f'/tmp/s{n}').convert('RGB')
    w, h = im.size

    # **Not the middle of the frame.** That was the first try and it told me
    # nothing: all seven colourways came back 186–215, because the middle of a
    # studio shot is mostly still the ground — the shoe is small and low in it.
    # So: take the ground from the corners, keep only the pixels far from it,
    # and average those. That is the shoe.
    small = im.resize((160, 160))
    px = small.load()
    corners = [px[0, 0], px[159, 0], px[0, 159], px[159, 159]]
    ground = tuple(sum(c[i] for c in corners) // 4 for i in range(3))

    shoe = [px[x, y] for x in range(160) for y in range(160)
            if sum(abs(px[x, y][i] - ground[i]) for i in range(3)) > 60]

    if not shoe:
        print(f'{colour:16} no pixels differ from the ground — cannot tell')
        continue

    r = sum(p[0] for p in shoe) // len(shoe)
    g = sum(p[1] for p in shoe) // len(shoe)
    b = sum(p[2] for p in shoe) // len(shoe)

    print(f'{colour:16} shoe rgb({r:3},{g:3},{b:3})  lightness={(r + g + b) // 3:3}  '
          f'({100 * len(shoe) // 25600}% of frame)  ground rgb{ground}')
    print(f'                 path: {shots[0]}')
