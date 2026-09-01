"""Pull the slug and the full name of every product card on a listing page.

⛔ TEMPORARY, with the workflow that calls it.

A card is `<a class="vp-card-name" href=".../products/<slug>">` carrying a
shortened name, above an `<img … alt="<full name>">`. The slug comes off the
anchor and the name off the `alt`, because `cardName()` cuts at six words and
«کتونی نایک وی تو…» is not enough to sort a shoe by.

Reads a page on stdin, writes `slug<TAB>name` lines on stdout.
"""

import html
import re
import sys

page = sys.stdin.read()

alts = dict(re.findall(
    r'/products/([^"?#]+)"[^>]*>\s*(?:<[^>]+>\s*)*<img[^>]+alt="([^"]+)"', page))
short = dict(re.findall(
    r'class="vp-card-name"[^>]*href="[^"]*/products/([^"?#]+)"[^>]*>\s*([^<]+?)\s*<', page))

for slug in dict.fromkeys(list(short) + list(alts)):
    print(f'{slug}\t{html.unescape(alts.get(slug) or short.get(slug, ""))}')
