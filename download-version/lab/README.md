# Lens study

A working reproduction of the "liquid glass" control from iOS 26 — the one
where the background is visibly *bent* through the control rather than blurred
behind it.

Open `liquid-glass.html`. Nothing here is part of the storefront; it exists so
the technique can be judged on its own before any of it is pointed at the shop.

## What actually makes it

Three effects stacked, in this order of importance:

1. **Refraction.** A displacement map, one pixel per pixel of the shape, whose
   red and green channels carry the x and y offset the background should be
   sampled from. The offset is the shape's outward normal scaled by how close
   the pixel is to the edge, so the middle is flat glass and the rim bends. The
   bevel has to run deep — a thin one distorts only a band that the hairline
   and the inner shadow then cover, and nothing survives to be seen.

2. **Chromatic aberration.** The same displacement run three times at slightly
   different strengths, one per colour channel, recombined. That is what the
   effect physically is: one ray bent by a different amount per wavelength. A
   single displacement warps the background but can never split its colour.

3. **The lit rim.** One hairline, bright where the light would fall and nearly
   gone on the opposite corners, plus a blurred prismatic ring screened over
   the extreme edge.

Tint is almost nothing — 10% white. Glass this clear must not turn milky, and
every attempt to "help" it with more white takes it straight back to frosted.

## The one engine trap

`feImage` will not resolve as a displacement map when the filter is applied to
an **HTML** element through CSS `filter:` — Blink silently passes the source
through, so the shape renders correctly and simply never distorts. The same
filter chain works inside an `<svg>`. Hence each piece's refraction layer is an
inline `<svg>` holding an `<image>` of the ground, not a `<div>`.

## Regenerating the rasters

    node theme/make-lens-assets.js

Writes `ground.png` (the stand-in background) and one displacement map per
shape. Bevel depth and falloff are arguments to `displacementMap()`.
