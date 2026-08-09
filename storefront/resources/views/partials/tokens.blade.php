{{--
    The design tokens the static page settled on, carried across so the Laravel
    side does not invent a second visual language before the port happens.

    The four that were argued for and measured (see HANDOFF.md) are the glass
    tint, the gold, the 24px corner on floating panes, and the way a pane meets
    the page: an ink hairline along the foot rather than a drop shadow. That
    last one is the codename «لبه پنهان» in CLAUDE.md — a soft shadow under a
    pane this pale reads as a fade, and raising it reads as a drawn line. The
    foot is drawn as an edge instead.

    Written inline rather than through Vite because none of these screens is
    built yet and a stylesheet that needs `npm run build` to exist is a
    stylesheet that will be missing the first time someone opens the panel.
--}}
<style>
    :root {
        --ink: #101111;
        --ink-soft: #5b5f60;
        --ink-faint: #8b9092;
        --page: #ffffff;
        --glass: rgba(16, 17, 17, 0.034);
        --hairline: rgba(16, 17, 17, 0.07);
        --lit: rgba(255, 255, 255, 0.5);
        --gold-a: #C0972F;
        --gold-b: #E3B54A;
        --good: #1f7a4d;
        --warn: #9a6b12;
        --bad: #a33127;
        --radius: 24px;
        --radius-sm: 14px;
    }

    * { box-sizing: border-box; }

    html, body {
        margin: 0;
        padding: 0;
        background: var(--page);
        color: var(--ink);
        font-family: Vazirmatn, "IRANSans", "Segoe UI", Tahoma, system-ui, sans-serif;
        font-size: 15px;
        line-height: 1.8;
    }

    a { color: inherit; text-decoration: none; }
    h1, h2, h3, h4 { margin: 0 0 .6rem; font-weight: 700; line-height: 1.5; }
    h1 { font-size: 1.45rem; }
    h2 { font-size: 1.15rem; }
    h3 { font-size: 1rem; }
    p { margin: 0 0 .8rem; }

    .wrap { width: min(1180px, 100% - 36px); margin-inline: auto; }

    /* A pane of the same glass the hero card is made of. The foot is ink,
       the other three sides carry the lit hairline. */
    .pane {
        background: var(--glass);
        border-radius: var(--radius);
        box-shadow:
            inset 1.4px 1.4px 0 var(--lit),
            inset -1.4px 0 0 var(--lit),
            inset 0 -1.4px 0 var(--hairline);
        padding: 22px 24px;
    }
    .pane--tight { padding: 16px 18px; }

    .grid { display: grid; gap: 18px; }
    .grid--2 { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
    .grid--3 { grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); }
    .grid--4 { grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); }
    .row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .row--between { justify-content: space-between; }
    .push { margin-inline-start: auto; }

    .stat { display: block; }
    .stat b { display: block; font-size: 1.35rem; font-variant-numeric: tabular-nums; }
    .stat span { color: var(--ink-soft); font-size: .82rem; }

    .muted { color: var(--ink-soft); }
    .faint { color: var(--ink-faint); font-size: .82rem; }
    .num { font-variant-numeric: tabular-nums; }

    .btn {
        display: inline-flex; align-items: center; gap: 8px;
        border: 0; cursor: pointer; font: inherit; font-weight: 600;
        padding: 9px 18px; border-radius: 999px;
        background: var(--glass); color: var(--ink);
        box-shadow: inset 0 0 0 1.4px var(--hairline);
    }
    .btn--gold {
        background: linear-gradient(100deg, var(--gold-a), var(--gold-b));
        color: #fff; box-shadow: none;
    }
    .btn--quiet { background: transparent; }
    .btn--small { padding: 5px 12px; font-size: .82rem; }

    input[type=text], input[type=email], input[type=password], input[type=number],
    input[type=url], input[type=date], input[type=search], select, textarea {
        width: 100%; font: inherit; color: var(--ink);
        padding: 9px 14px; border-radius: var(--radius-sm);
        border: 0; background: #fff;
        box-shadow: inset 0 0 0 1.4px var(--hairline);
    }
    textarea { min-height: 96px; resize: vertical; }
    label { display: block; font-size: .84rem; color: var(--ink-soft); margin-bottom: 4px; }
    .field { margin-bottom: 14px; }

    table { width: 100%; border-collapse: collapse; }
    th, td { text-align: right; padding: 10px 8px; border-bottom: 1px solid var(--hairline); }
    th { font-size: .8rem; color: var(--ink-soft); font-weight: 600; }
    .scroller { overflow-x: auto; }

    .tag {
        display: inline-block; padding: 2px 10px; border-radius: 999px;
        font-size: .76rem; background: var(--glass); color: var(--ink-soft);
    }
    .tag--good { color: var(--good); }
    .tag--warn { color: var(--warn); }
    .tag--bad { color: var(--bad); }

    .notice {
        border-radius: var(--radius-sm); padding: 10px 16px; margin-bottom: 18px;
        background: var(--glass); box-shadow: inset 0 -1.4px 0 var(--hairline);
    }
    .notice--bad { color: var(--bad); }

    /* A bar chart drawn from the daily figures, so the dashboard shows a shape
       and not only a number. No library: 30 divs and a percentage. */
    .spark { display: flex; align-items: flex-end; gap: 3px; height: 72px; }
    .spark i { flex: 1; background: linear-gradient(180deg, var(--gold-b), var(--gold-a)); border-radius: 3px 3px 0 0; min-height: 2px; }

    .thumb {
        width: 100%; aspect-ratio: 4 / 3; object-fit: cover;
        border-radius: var(--radius-sm); background: var(--glass);
    }

    .pagination { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 18px; }
    .pagination a, .pagination span { padding: 4px 11px; border-radius: 999px; background: var(--glass); font-size: .85rem; }
</style>
