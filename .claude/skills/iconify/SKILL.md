---
name: iconify
description: Find, preview and export icons from Iconify's 236 sets (Lucide, Phosphor, Material, Tabler, Fluent Emoji, Solar…) without leaving the container. Use when an icon is wanted — searching for one, showing the client a sheet to choose from, or writing an SVG out — and for the rules about putting somebody else's artwork on this site.
---

# Iconify, offline

<https://iconify.design> is 236 icon sets — around 200,000 drawings — under one
naming scheme: `prefix:name`, as in `lucide:home`, `ph:sneaker-move-fill`,
`mdi:arrow-right`.

**Its API is blocked in this container.** `api.iconify.design` gets a 403 at the
egress proxy (`connect_rejected`), and so does `iconify.design` itself. Every
icon CLI and MCP server for Iconify — `better-icons`, `pickapicon`,
`iconify-mcp-server` — is that one host with a command line on top, so all of
them answer `Error: Forbidden` here. Do not install one and do not spend a round
debugging it.

**npm is not blocked** — it is in the proxy's `noProxy` list. Iconify publishes
every set there as `@iconify-json/<prefix>`, so the tool in this folder reads
the sets off disk and never calls anything at search time. It is also how the
category icons have always been made: `theme/make-category-icons.js` reads
`@iconify-json/fluent-emoji-high-contrast` out of `theme/node_modules`.

## The tool

```bash
node .claude/skills/iconify/iconify.js sets [query]      # which sets exist, and their licences
node .claude/skills/iconify/iconify.js add ph tabler     # download sets from npm
node .claude/skills/iconify/iconify.js search sneaker --in ph,tabler --limit 20
node .claude/skills/iconify/iconify.js get ph:sneaker-move-fill --gold --size 32 --out icon.svg
node .claude/skills/iconify/iconify.js sheet ph:sneaker-fill tabler:shoe --cols 3 --out /tmp/sheet.png
```

- `search` with no `--in` searches only what is already downloaded; `--in`
  fetches any set it names first. Aliases are searched too — a good part of a
  set's vocabulary lives in them.
- `get` prints to stdout unless `--out` is given. `--gold` bakes `#A08119`, the
  page's gold, over `currentColor`; `--color` takes any other value.
- **Nothing it does touches the shop.** Downloads land in this folder's own
  `node_modules` (ignored, along with the `package.json` it generates). The only
  files it writes anywhere else are the ones you name with `--out`.

First run in a fresh container downloads `@iconify/collections` and
`@iconify/utils` — about 3MB, a few seconds. Sets are 0.5–10MB each. It borrows
`theme/node_modules/sharp` for the sheet if `theme`'s dependencies are
installed, rather than fetching a second copy.

## Choosing one

**Print a sheet and let the client pick a number.** Every icon in this
repository was chosen that way — «شماره ۳ خوبه اونکه انگار در حال دویدنه» off a
sheet of ten. A list of names in a terminal is not something anyone can answer.
`sheet` numbers each cell and writes the id under it.

Two things narrow the field before you draw anything:

- **Monochrome sets only.** `sets` marks a set `fixed palette` when its artwork
  carries its own colours; those cannot be recoloured to the page's gold and
  will arrive multicoloured. `palette: false` sets take `currentColor`.
- **Sets already in use here** are `@iconify-json/fluent-emoji-high-contrast`
  (seven category icons) and `@phosphor-icons/core` (the sneaker, `ph` on
  Iconify). Reaching for one of those first keeps a row of icons looking like
  one family, which is the whole reason those two were settled on — nine sets
  were measured against the eight categories before Fluent won.

## An icon that ships

Everything in this repository that draws somebody else's artwork carries three
obligations, and the tests enforce two of them.

1. **The licence travels with the copy.** Most sets are MIT, Apache or CC-BY,
   and all three ask for the notice. `ShippedAssetsTest` reads the set's name
   out of each shipped icon and requires the matching `LICENSE-*.txt` beside it
   in `download-version/assets/img/icon/`. Its list of sets is written into the
   test — **a third source means editing `$notices` there too**, or the suite
   fails on the set it does not know.
2. **It comes from a generator, not from a hand-edit.**
   `download-version/assets/img/icon/vp-cat-*.svg` are output. Add the icon to
   the map in `theme/make-category-icons.js`, re-run it, then
   `node theme/sync-storefront-assets.js` to carry it to Laravel. A file edited
   by hand there is undone by the next run, silently. If a set becomes part of
   that generator, it belongs in `theme/package.json` — not in this folder's
   cache, which is a scratch directory.
3. **The pixels are checked.** `node theme/check-parity.js` must still print
   zero, and `node theme/check-overflow.js` must still pass, after any icon that
   reaches the page.

Gold is `#A08119` and it is baked into the file, because these icons are loaded
through `<img>` and an `<img>` does not inherit `currentColor` from the page
around it.

## Two things not to do

**Do not put Iconify on the page.** Iconify's own browser package fetches each
icon from `api.iconify.design` at render time. That host is blocked from this
container and from CI, so nothing here could ever test it — and the page's
appearance already hangs on one file arriving over the network, which is the
whole of «قالب قبلی» in `CLAUDE.md`: a deploy dropped `tweaks.css` and the
client watched the template come back on their phone. Adding a second such
dependency, for icons, on a third party's uptime, is that bug again. Export the
SVG and ship the file.

**Do not add an icon font.** The template already loads FontAwesome, and 125
`<i class="fa…">` glyphs in the Blade views come from it. A second font is
another 100KB of network for a handful of drawings that could be files.

## RTL

This page is right-to-left. A directional icon — arrow, chevron, reply, undo,
anything with a handle or a tail — points the wrong way once it is mirrored into
place, and Iconify hands you the left-to-right drawing. The template's own fix
is in `rtl-fixes.css`:

```css
[dir="rtl"] .th-btn .btn-icon { transform: scaleX(-1); }
```

Either mirror it in CSS like that, or ask for the mirrored icon: most sets carry
both (`arrow-left` / `arrow-right`), and Iconify aliases apply `hFlip` for the
rest — `get` renders those correctly, because it goes through Iconify's own
renderer rather than reading the JSON by hand.

## Updating, and where this came from

Hand-written for this repository, unlike the vendored skills beside it — there
is no upstream Iconify skill, and the ones that exist are wrappers around the
blocked API. The data is Iconify's, pulled from npm at the version npm resolves;
`rm -rf .claude/skills/iconify/node_modules package.json` inside this folder and
run any command to start again with current sets.
