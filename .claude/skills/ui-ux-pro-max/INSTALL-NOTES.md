# ui-ux-pro-max — provenance and how to update

Vendored from https://github.com/nextlevelbuilder/ui-ux-pro-max-skill
(MIT), upstream commit `8a1a6d8` (2026-08-19). `skill.json` still says version
2.13.0 — upstream has not bumped it — but the data has moved a good deal since
`abb7f2f`, which is what was here before: 98 UX guidelines became 119, the
icons 104 became 105, the GSAP presets 16 became 17, and `scripts/` gained
`reasoning_contract.py`. Refreshed on 2026-08-19 at the client's request
(«میخوام این اسکیلو نصب کنی»), before the panel's phone design was worked on
with it.

Copied from the repo's `.claude/skills/ui-ux-pro-max/`. Two deliberate
deviations from upstream:

- `scripts/tests/` was dropped — it tests the upstream project, not this one.
- `SKILL.md`'s command examples used `${CLAUDE_PLUGIN_ROOT}/.claude/skills/…`,
  which is only set when the skill is installed as a Claude Code *plugin*.
  This is a plain project skill, so the paths were rewritten to be relative to
  the repository root.

Everything else is byte-for-byte upstream. Re-apply both changes if you update.

`scripts/__pycache__/` used to be committed here. It is not upstream, it is
made by whichever machine last ran the search, and a stale `.pyc` outlived the
source it was compiled from across this update. It is in `.gitignore` now.

## Running it

From the repository root:

```bash
python3 .claude/skills/ui-ux-pro-max/scripts/search.py "<query>" --domain <domain>
python3 .claude/skills/ui-ux-pro-max/scripts/search.py "<query>" --design-system -p "VikyPlus"
python3 .claude/skills/ui-ux-pro-max/scripts/search.py "<query>" --stack html-tailwind
```

Python 3 standard library only — no dependencies, no network calls. The data is
the CSV files under `data/`.

## A caution for this repo

This skill hands out generic design-system recommendations: it will happily
suggest a palette, a font pairing and a card treatment for "e-commerce shoes
bags". Those are starting points for new work, not verdicts about the existing
page. `HANDOFF.md` lists the numbers the finished part of the storefront is not
allowed to lose, and `CLAUDE.md` records decisions — «لبه پنهان» in particular —
that were settled by measuring rendered pixels. Where the two disagree, the
measurements win.

## Companion skills not installed

Upstream also ships `ui-styling`, `design-system`, `brand`, `design`,
`banner-design` and `slides` in the same directory (~6.6 MB more). Only
`ui-ux-pro-max` was requested. To add one, copy its folder out of the upstream
repo into `.claude/skills/` and check its `SKILL.md` for the same
`CLAUDE_PLUGIN_ROOT` path issue.
