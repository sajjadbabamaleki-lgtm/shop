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
    # The middle half of the frame is the shoe; the border is the ground.
    mid = im.crop((w // 4, h // 4, 3 * w // 4, 3 * h // 4)).resize((32, 32))
    px = list(mid.getdata())
    r = sum(p[0] for p in px) // len(px)
    g = sum(p[1] for p in px) // len(px)
    b = sum(p[2] for p in px) // len(px)

    print(f'{colour:16} rgb({r:3},{g:3},{b:3})  lightness={(r + g + b) // 3:3}  '
          f'{w}x{h}  {url.split("/")[-1]}')
    print(f'                 path: {shots[0]}')
