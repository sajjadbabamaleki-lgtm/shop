# Vendored skills — where they came from and how to update them

Everything in this directory is a **project skill**: a plain folder with a
`SKILL.md`, loaded because it sits in `.claude/skills/`. Nothing here is a
Claude Code *plugin*, so `${CLAUDE_PLUGIN_ROOT}` is never set and no plugin
marketplace has to be added. That matters for this repository specifically —
sessions run in ephemeral remote containers, `~/.claude` does not survive them,
and a plugin installed into a container is gone the next morning. Committed to
the repo, these load for every session, on every machine, forever.

`ui-ux-pro-max/` was here first and keeps its own `INSTALL-NOTES.md`. The five
families below were added on 2026-08-15 at the client's request.

**A folder's name must match the `name:` in its `SKILL.md`.** Every folder here
does. It is worth stating because one of them looks wrong: the taste skill lives
in `design-taste-frontend/`, not `taste-skill/`, because that is what its
frontmatter calls itself. See its entry below.

## What was installed

| Family | Skills | Upstream | Commit | Licence |
| --- | --- | --- | --- | --- |
| `impeccable` | 1 | [pbakaus/impeccable](https://github.com/pbakaus/impeccable) v4.1.1 | `7b646ba` (2026-08-14) | Apache 2.0 |
| `gsap-*` | 8 | [greensock/gsap-skills](https://github.com/greensock/gsap-skills) | `aed9cfd` (2026-04-21) | MIT |
| Emil Kowalski | 10 | [emilkowalski/skills](https://github.com/emilkowalski/skills) | `78761e1` (2026-08-10) | see repo |
| `hyperframes-*` | 9 of 20 | [heygen-com/hyperframes](https://github.com/heygen-com/hyperframes) v0.7.109 | `fecaf72` (2026-08-15) | Apache 2.0 |
| `design-taste-frontend` | 1 of 13 | [leonxlnx/taste-skill](https://github.com/leonxlnx/taste-skill) | `e988add` (2026-07-23) | MIT |

Each was copied byte-for-byte out of the upstream repository's own skills
directory — `.claude/skills/` for impeccable, `skills/` for the other three. No
file was edited. To update a family, re-clone upstream and copy the folders over
again, then re-read the two cautions at the bottom of this file.

### impeccable

The design-polish pass. One skill, twenty-three sub-commands
(`/impeccable polish`, `/impeccable audit`, `/impeccable critique`, …). Its
scripts are Node and need **Node 22 or newer** — this container has 22.22.2 on
`PATH`, and `/opt/node22/bin/node` is the explicit path if a session finds an
older one.

**Its hooks were deliberately not installed.** Upstream ships a
`.claude/settings.json` that runs its detector after every `Edit`/`Write` and a
"deep pass" on every `Stop`. This repository has no `.claude/settings.json`, the
client runs several sessions on it at once, and the container already carries
its own stop hooks — so adding two more that fire on every edit in every session
is not a decision to make on somebody's behalf. The skill is fully usable
without them. To turn them on, copy the `hooks` block out of
<https://github.com/pbakaus/impeccable/blob/main/.claude/settings.json> into a
new `.claude/settings.json` here; the paths it uses
(`${CLAUDE_PROJECT_DIR}/.claude/skills/impeccable/scripts/hook.mjs`) already
resolve correctly for this install.

Upstream also ships four sub-agents (`impeccable-finish-reviewer`,
`impeccable-documenter`, `impeccable-asset-producer`,
`impeccable-manual-edit-applier`) in its `.claude/agents/`. Three of the skill's
reference files mention them. They were not installed either — same reason —
and the skill degrades to doing that work inline. Copy them into
`.claude/agents/` if the delegation is wanted.

### gsap-*

Eight official GreenSock skills: `gsap-core`, `gsap-timeline`,
`gsap-scrolltrigger`, `gsap-plugins`, `gsap-utils`, `gsap-performance`,
`gsap-react`, `gsap-frameworks`. Reference material only — no scripts, no
network. Note that GSAP itself is **not** a dependency of this storefront; the
Erna template animates with its own bundle. These skills teach the API; they do
not add it to the page.

### Emil Kowalski's ten

`animate`, `improve-animations`, `review-animations`,
`find-animation-opportunities`, `animation-vocabulary`, `apple-design`,
`emil-design-eng`, `prototype`, `pick-ui-library`, `ask-sonner`. Markdown only.

`prototype` and `pick-ui-library` carry `disable-model-invocation: true`: they
run only when a person types them, never on Claude's own initiative.

Two of them lean React/Next — `ask-sonner` is about a React toast library and
`pick-ui-library` recommends npm packages. This storefront is Blade and jQuery,
so those two will rarely have anything to say here. They were installed anyway
rather than picking through somebody else's set.

### hyperframes-* — the core set only, on purpose

HyperFrames turns HTML into rendered MP4 video. Upstream ships **20** skills and
its own README tells agents to install exactly the **core set** — the router
plus the domain skills plus `media-use` — because the router pulls each creation
workflow on demand. That is what is here:

```
hyperframes  hyperframes-core  hyperframes-animation  hyperframes-audio
hyperframes-cli  hyperframes-creative  hyperframes-keyframes
hyperframes-registry  media-use
```

The eleven creation workflows (`product-launch-video`, `faceless-explainer`,
`music-to-video`, `slideshow`, `pr-to-video`, `embedded-captions`,
`talking-head-recut`, `motion-graphics`, `general-video`,
`remotion-to-hyperframes`, `figma`) are **not** installed — 13 MB more, and the
`/hyperframes` router installs the one it needs with
`npx hyperframes skills update <workflow>`. If a workflow is wanted permanently,
copy its folder out of the upstream repo into this directory and commit it,
rather than leaving it to a container that gets thrown away.

**Rendering needs the CLI, which is not installed.** The skills are the
instructions; `npx hyperframes …` fetches the actual renderer from npm at first
use, and rendering wants a browser (this container has Chromium at
`/opt/pw-browsers/chromium-1194/chrome-linux/chrome`). Nothing here has been
exercised end-to-end — no video has been rendered in this repository.

### design-taste-frontend — the taste skill, and why the folder is named that

One 87 KB `SKILL.md`, no scripts. It is the "anti-slop" skill: read the brief,
infer a design direction, then push spacing, hierarchy and type past the safe
default an LLM reaches for on its own. Three dials — `DESIGN_VARIANCE`,
`MOTION_INTENSITY`, `VISUAL_DENSITY`, each 1-10 — set at the top of the file.

**The folder is `design-taste-frontend/` and that is not a mistake.** Upstream's
folder is `skills/taste-skill/` but its frontmatter says
`name: design-taste-frontend`, and upstream's own install line is
`npx skills add … --skill "design-taste-frontend"`. The folder was renamed to
match the frontmatter so the two agree; a skill whose folder and `name:`
disagree is the kind of thing that fails quietly. Nothing inside the file was
touched.

**It is upstream's own v2, marked experimental** — a substantial rewrite of v1,
"actively iterating toward v2.0.0 stable". `design-taste-frontend-v1` is kept
upstream for anyone who needs the old behaviour exactly; it is not installed
here.

Three things to know before letting it near this storefront:

- **It knows nothing about RTL.** The file does not contain the words `rtl`,
  Persian or Arabic once. Every spacing, alignment and type rule in it was
  written for a left-to-right page. This shop is right-to-left in a Persian
  face, and mirrored spacing is not the same problem as spacing.
- **It assumes React / Next / Tailwind** — 45 mentions between them — and
  reaches for GSAP 23 times. The storefront is Blade and jQuery on the Erna
  bundle, and GSAP is not a dependency here. Its code skeletons do not
  transplant; its judgement about hierarchy and rhythm does.
- **It bans the em-dash outright**, in headlines, body, buttons and alt text
  alike. That is its house style for generated copy, not a rule about this
  repository's own prose — `CLAUDE.md` and these notes are full of them on
  purpose.

The other twelve skills in that repo are **not** installed; upstream says
plainly that "each skill does one job; you do not need all of them at once".
The ones most likely to be wanted here later are `redesign-existing-projects`
(audit an existing UI before changing it) and `high-end-visual-design` (the
calm, expensive end of the same idea). Copy the folder out of upstream and
**rename it to its `name:` field**, as above.

## Two cautions for this repository

**These skills give generic advice; the storefront has measurements.** Every
one of them will happily propose a palette, an easing curve, a shadow, a card
treatment. `CLAUDE.md` records decisions that were settled by rendering the page
in Chromium and reading pixel values — «لبه پنهان» above all, where a drop
shadow was tried, rejected by the client, and replaced with an inset edge for
reasons that are written down. `HANDOFF.md` lists the numbers the finished part
is not allowed to lose. **Where a skill's taste disagrees with a measurement in
this repo, the measurement wins**, and `node theme/check-parity.js` is what
proves it — it must still print zero after any visual change.

**The generated files are still generated.** `impeccable` and the animation
skills will offer to edit whatever file is open. `download-version/shoe-shop-rtl.html`
and most of `storefront/resources/views/partials/` are output, not source —
edit `theme/make-rtl-page.js` and re-run the generators. `CLAUDE.md`'s "Where
things are" is the list of which is which.
