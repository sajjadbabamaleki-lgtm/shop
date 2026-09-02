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

import sys

sys.path.insert(0, '.github/scripts')
from shoe_hue import hue_of  # noqa: E402

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

    # The same instrument the client's own photographs were measured with —
    # `shoe-hue.py`, the darkest tenth — because two earlier ones measured the
    # studio rather than the shoe and gave seven near-identical answers.
    r, g, b, warmth = hue_of(f'/tmp/s{n}')

    print(f'{colour:16} rgb({r:3},{g:3},{b:3})  lightness={(r + g + b) // 3:3}  '
          f'r-b={warmth:+4}')
    print(f'                 path: {shots[0]}')
