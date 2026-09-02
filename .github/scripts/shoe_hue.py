"""The colour of a shoe, measured the same way on both sides.

⛔ TEMPORARY.

Two earlier instruments failed here and both failed the same way — they
measured the studio, not the shoe. The middle of the frame is mostly ground;
"far from the corner colour" flags the ground itself when the ground is a
gradient (63% of one frame).

The darkest tenth of the pixels is the shoe's body in every one of these
photographs, whatever the ground does, so that is what this averages. It is
used on the shop's photographs and on the client's with the same code.
"""

import sys

from PIL import Image


def hue_of(path):
    im = Image.open(path).convert('RGB').resize((160, 160))
    px = list(im.getdata())
    px.sort(key=lambda p: p[0] + p[1] + p[2])
    dark = px[:len(px) // 10]
    r = sum(p[0] for p in dark) // len(dark)
    g = sum(p[1] for p in dark) // len(dark)
    b = sum(p[2] for p in dark) // len(dark)
    # warm = red over blue; cool = blue over red. Neutral means black or grey.
    return r, g, b, r - b


for path in sys.argv[1:]:
    r, g, b, warmth = hue_of(path)
    print(f'rgb({r:3},{g:3},{b:3})  lightness={(r + g + b) // 3:3}  r-b={warmth:+4}  {path}')
