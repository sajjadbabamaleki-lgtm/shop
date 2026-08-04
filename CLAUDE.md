# VikyPlus — notes for whoever picks this up next

An Iranian shoe and bag storefront (vikyplus.ir) built on the ThemeForest
"Erna" HTML template. The page being worked on is the RTL Persian preview.

## Where things are

- `download-version/shoe-shop-rtl.html` — **generated**. Never edit it by hand;
  edit `theme/make-rtl-page.js` and re-run `node theme/make-rtl-page.js`.
- `download-version/assets/css/tweaks.css` — every deliberate deviation from
  the template, loaded last. One block per decision, with the reasoning and the
  measurements in the comment above it.
- `theme/make-category-photos.js` — the six category tiles. The photographs go
  in exactly as supplied: resize only, no crop, no cut-out.
- Preview server:
  `cd download-version && setsid nohup python3 -m http.server 8811 &`
  It dies often; restart it with `setsid` rather than assuming it is up.

## Measure, don't eyeball

Every visual claim in this repo's history was settled by rendering the page in
Chromium and reading pixels, not by looking. Playwright is at
`/opt/node22/lib/node_modules/playwright`, Chromium at
`/opt/pw-browsers/chromium-1194/chrome-linux/chrome`, sharp at
`theme/node_modules/sharp`. Screenshot, take the raw buffer, print the column
or row of values across the thing being argued about. It is faster than a
round trip with the client, and it is the only way most of these questions have
an answer.

---

# Codenames

Problems that have already cost real time. If the client names one of these,
read the entry before touching anything.

## «لبه پنهان» — the hidden edge

**Symptom, in the client's words:** the bottom of the hero card is not
defined; there is a horrible fade under it; extra white seems stuck to the
bottom of the cards. Reported three separate times over one afternoon.

**What it is not.** It is not extra white, not a stray element, not the
neighbouring carousel slides, and not a clipped shadow. Each of those was
investigated and each was a dead end. The last one cost a full change that had
to be reverted.

**What it is.** The pane's tint is `rgba(16,17,17,0.034)`, which composites to
247 on white. Its own lit hairline is white at about half alpha, which reads
250. A drop shadow's value at the element's own edge is roughly half its alpha,
because the other half falls under the element and is clipped away — with an
alpha low enough not to look like a drawn line, that came to 244. So down the
card's foot the page read

```
247 (pane)   250 (lit hairline)   244 (shadow)   … 30px climbing … 255 (page)
```

Four values within a few levels of each other and no step anywhere. The card
dissolved into the page, and the long climb is the "fade". The top edge, which
nobody has ever complained about, does 254 → 247 in a single pixel.

**Why a shadow cannot fix it.** At the edge a soft shadow contributes about two
levels; everything else it does is the fade. Raising the alpha buys the edge
back and immediately puts a hard line under the card, which is where the whole
thread started — the client rejected that too, in the same words ("خطی و تیز").
Removing the shadow does not fix it either: that leaves the white hairline
sitting on the boundary and the edge is still soft. Both were tried.

**The fix.** Draw the edge as an edge.

- The lit hairline stops short of the foot: the `:after` ring's padding is
  `1.4px 1.4px 0`, so the mask draws no band along the bottom.
- The foot is ink: `box-shadow: inset 0 -1.4px 0 rgba(16,17,17,0.07)`, the same
  thickness as the hairline on the other three sides.
- No drop shadow on either pane.

Measured after: card `247 → 234 → 255`, header island `247 → 230 → 255`.
A panel lit from above and resting on a surface is darker where it meets it,
so this is also what the light would do.

Both panes — `.heroSlide6 .hero-inner` and `.th-header .menu-area` — carry it,
and they must stay in step: they are meant to read as one material.

**Do not** put the drop shadow back without re-reading this. If a shadow ever
does return, note that `.th-hero-wrapper` and the swiper are both
`overflow: hidden` and end on the same pixel as the card, so the room for it
has to be made inside the clip (`.heroSlide6` bottom padding, with the same
amount taken off `.feature-area2`'s top padding) — and that room has to be
changed whenever the shadow's reach changes. Getting that wrong is what put a
straight cut across the page 20px under the card.

## «همسایه» — the peeking neighbours

Not a bug, and a change here was reverted once. The template gives the hero
deck `margin: 0 -36%` and runs two slides to a view, centred, so the cards
either side of the active one show past the page's margins — 83px of pane on
each side at 1440. In the template every card is a different pastel and the
slivers read as the next colour coming; ours are six panes of the same glass,
so they can be mistaken for a stray panel. **Leave it alone unless the client
asks for it directly**, and if they do, the change is: `slidesPerView: 1` above
992 in the hero's `data-slider-options`, the track back to the page's width at
`width: 85%`, plus `initialSlide: 1` so the deck still opens on the slide that
carries the real product photograph.
