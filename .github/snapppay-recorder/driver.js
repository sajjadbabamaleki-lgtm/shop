// Temporary: a remote-controlled, recorded browser for the SnappPay test
// purchase. The runner can reach vikyplus.ir; the session driving it cannot,
// so commands arrive as commits on CMD_BRANCH and every result goes back as a
// commit on OUT_BRANCH. Delete this directory and its workflow afterwards.
//
// A command file is {"seq": N, "steps": [ {do: ...}, ... ]}. After each batch
// the driver writes out/NNN.json (url, title, visible text, the controls on
// the page, any error) and out/NNN.png. Time spent waiting for the next batch
// is logged as idle and cut out of the final film; explicit `wait` steps stay.

const { chromium } = require(process.env.PW_PATH || 'playwright');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const WORK = process.env.WORK || path.resolve('rc-work');
const GIT = process.env.GIT_MODE === '1';
const CMD_BRANCH = process.env.CMD_BRANCH || 'claude/snapppay-rc';
const OUT_BRANCH = process.env.OUT_BRANCH || 'claude/snapppay-rc-out';
const LOCAL_CMD_DIR = process.env.LOCAL_CMD_DIR || path.join(WORK, 'cmd-local');
const IDLE_LIMIT_MS = Number(process.env.IDLE_LIMIT_MS || 45 * 60 * 1000);
const POLL_MS = 2500;

const CMD_DIR = path.join(WORK, 'cmd');
const OUT_DIR = path.join(WORK, 'out');
const VID_DIR = path.join(WORK, 'video');
for (const d of [WORK, CMD_DIR, OUT_DIR, VID_DIR]) fs.mkdirSync(d, { recursive: true });

const sh = (c, opts = {}) => execSync(c, { stdio: ['ignore', 'pipe', 'pipe'], encoding: 'utf8', ...opts });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const t0 = Date.now();
const now = () => Date.now() - t0;
const timeline = []; // {page, start, end} idle windows, ms since t0
const pages = []; // {idx, created, video}
const activeLog = []; // {t, idx}: which tab is in front from t on

// --- the channel -----------------------------------------------------------

function setupGit() {
    if (!GIT) return;
    // OUT: an orphan worktree we commit into and push (never forced).
    sh(`git config --global user.name "snapppay-recorder"`);
    sh(`git config --global user.email "snapppay-recorder@users.noreply.github.com"`);
    const outRepo = path.join(WORK, 'out-repo');
    sh(`git init -q ${outRepo}`);
    const remote = sh('git remote get-url origin').trim();
    sh(`git -C ${outRepo} remote add origin ${remote}`);
    // carry the checkout's auth header over
    try {
        const hdr = sh('git config --get-all http.https://github.com/.extraheader').trim();
        if (hdr) sh(`git -C ${outRepo} config http.https://github.com/.extraheader "${hdr}"`);
    } catch (_) {}
    sh(`git -C ${outRepo} checkout -q --orphan ${OUT_BRANCH}`);
}

let lastSeq = 0;
function readCommand() {
    let raw = null;
    if (GIT) {
        try {
            sh(`git fetch -q origin ${CMD_BRANCH}`);
            raw = sh('git show FETCH_HEAD:cmd.json');
        } catch (_) { return null; }
    } else {
        const f = path.join(LOCAL_CMD_DIR, 'cmd.json');
        if (!fs.existsSync(f)) return null;
        raw = fs.readFileSync(f, 'utf8');
    }
    let cmd;
    try { cmd = JSON.parse(raw); } catch (e) { return null; }
    if (!cmd || typeof cmd.seq !== 'number' || cmd.seq <= lastSeq) return null;
    lastSeq = cmd.seq;
    return cmd;
}

function publish(seq, extraFiles = []) {
    if (!GIT) return;
    const outRepo = path.join(WORK, 'out-repo');
    for (const f of fs.readdirSync(OUT_DIR)) fs.copyFileSync(path.join(OUT_DIR, f), path.join(outRepo, f));
    for (const f of extraFiles) fs.copyFileSync(f, path.join(outRepo, path.basename(f)));
    sh(`git -C ${outRepo} add -A`);
    sh(`git -C ${outRepo} commit -q -m "out ${seq} [skip ci]" --allow-empty`);
    for (let i = 0; i < 5; i++) {
        try { sh(`git -C ${outRepo} push -q origin ${OUT_BRANCH}`); return; } catch (e) { execSync(`sleep ${2 ** i}`); }
    }
}

// --- the browser -----------------------------------------------------------

const TAP_SCRIPT = `
(() => {
  if (window.__vpTap) return; window.__vpTap = 1;
  const draw = (x, y) => {
    const d = document.createElement('div');
    d.style.cssText = 'position:fixed;z-index:2147483647;pointer-events:none;left:' + (x-18) + 'px;top:' + (y-18) +
      'px;width:36px;height:36px;border-radius:50%;background:rgba(255,64,64,.35);border:2px solid rgba(255,64,64,.9);' +
      'transition:transform .45s ease-out,opacity .45s ease-out;transform:scale(.6)';
    (document.body || document.documentElement).appendChild(d);
    requestAnimationFrame(() => { d.style.transform = 'scale(1.4)'; d.style.opacity = '0'; });
    setTimeout(() => d.remove(), 600);
  };
  addEventListener('pointerdown', (e) => draw(e.clientX, e.clientY), true);
})();`;

let browser, context, page;

async function start() {
    browser = await chromium.launch({ args: ['--lang=fa-IR'] });
    context = await browser.newContext({
        viewport: { width: 390, height: 844 },
        deviceScaleFactor: 2,
        isMobile: true,
        hasTouch: true,
        locale: 'fa-IR',
        timezoneId: 'Asia/Tehran',
        userAgent: 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36',
        recordVideo: { dir: VID_DIR, size: { width: 390, height: 844 } },
    });
    await context.addInitScript(TAP_SCRIPT);
    context.on('page', (p) => track(p));
    page = await context.newPage();
}

function track(p) {
    if (pages.find((x) => x.page === p)) return;
    pages.push({ idx: pages.length, page: p, created: now(), video: p.video() });
    page = p; // follow the newest tab
    activeLog.push({ t: now(), idx: pages.length - 1 });
    p.on('close', () => {
        const rec = pages.find((x) => x.page === p); if (rec) rec.closedAt = now();
        if (page === p) {
            const alive = pages.filter((x) => !x.page.isClosed());
            if (alive.length) { page = alive[alive.length - 1].page; activeLog.push({ t: now(), idx: alive[alive.length - 1].idx }); }
        }
    });
}

function frameOf(step) {
    return step.frame ? page.frameLocator(step.frame) : page;
}

async function run(step) {
    const tgt = frameOf(step);
    const loc = step.sel ? tgt.locator(step.sel).nth(step.nth || 0) : null;
    const timeout = step.timeout || 20000;
    const val = step.secret ? process.env[step.secret] || '' : step.value;
    switch (step.do) {
        case 'goto': await page.goto(step.url, { waitUntil: 'domcontentloaded', timeout: 60000 }); break;
        case 'click': await loc.scrollIntoViewIfNeeded({ timeout }); await sleep(250); await loc.click({ timeout }); break;
        case 'type': await loc.scrollIntoViewIfNeeded({ timeout }); await loc.click({ timeout }); await loc.fill('');
            await loc.pressSequentially(String(val), { delay: step.delay ?? 70 }); break;
        case 'fill': await loc.fill(String(val), { timeout }); break;
        case 'select': await loc.selectOption(step.value, { timeout }); break;
        case 'check': await loc.scrollIntoViewIfNeeded({ timeout }); await sleep(250); await loc.check({ timeout }); break;
        case 'press': await (loc || page.keyboard).press(step.key); break;
        case 'wait': await sleep(step.ms); break;
        case 'waitFor': await loc.waitFor({ state: step.state || 'visible', timeout: step.timeout || 60000 }); break;
        case 'waitUrl': await page.waitForURL(new RegExp(step.re), { timeout: step.timeout || 90000 }); break;
        case 'load': await page.waitForLoadState(step.state || 'load', { timeout: step.timeout || 60000 }); break;
        case 'scrollTo': await loc.evaluate((el) => el.scrollIntoView({ behavior: 'smooth', block: 'center' })); await sleep(900); break;
        case 'scroll': await page.evaluate((y) => window.scrollBy({ top: y, behavior: 'smooth' }), step.y); await sleep(900); break;
        case 'top': await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'smooth' })); await sleep(900); break;
        case 'eval': { const r = await page.evaluate(step.js);
            try { return JSON.parse(JSON.stringify(r ?? null)); } catch (_) { return String(r); } }
        case 'dialogs': page.removeAllListeners('dialog');
            page.on('dialog', (d) => (step.accept === false ? d.dismiss() : d.accept()).catch(() => {})); break;
        case 'tab': page = pages[step.idx].page; activeLog.push({ t: now(), idx: step.idx }); await page.bringToFront(); break;
        default: throw new Error('unknown step ' + step.do);
    }
}

async function snapshot(seq, result) {
    const name = String(seq).padStart(3, '0');
    const info = { seq, at: now(), ...result };
    try {
        info.url = page.url();
        info.title = await page.title();
        info.tabs = pages.map((p) => ({ idx: p.idx, url: p.page.isClosed() ? '(closed)' : p.page.url() }));
        info.text = (await page.evaluate(() => document.body ? document.body.innerText : '')).slice(0, 8000);
        info.controls = await page.evaluate(() => {
            const vis = (el) => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0; };
            return [...document.querySelectorAll('input,button,select,textarea,a[href],[role=button]')]
                .filter((el) => vis(el) && el.type !== 'hidden').slice(0, 150).map((el) => ({
                    tag: el.tagName.toLowerCase(), id: el.id || undefined, name: el.getAttribute('name') || undefined,
                    type: el.getAttribute('type') || undefined, cls: (el.className && el.className.baseVal === undefined ? el.className : '').slice(0, 60) || undefined,
                    text: (el.innerText || el.value || el.getAttribute('aria-label') || el.getAttribute('placeholder') || '').trim().slice(0, 60) || undefined,
                    href: el.tagName === 'A' ? el.getAttribute('href').slice(0, 100) : undefined,
                    checked: el.checked || undefined, disabled: el.disabled || undefined,
                }));
        });
        info.frames = page.frames().map((f) => f.url()).filter((u) => u && u !== 'about:blank');
        await page.screenshot({ path: path.join(OUT_DIR, name + '.png'), timeout: 15000 });
    } catch (e) { info.snapError = String(e).slice(0, 500); }
    fs.writeFileSync(path.join(OUT_DIR, name + '.json'), JSON.stringify(info, null, 1));
}

// --- the film --------------------------------------------------------------

function cutFilm() {
    // One clock for the whole session: keep everything that is not idle
    // (plus a beat of each pause), and within each kept stretch show whichever
    // tab was in front at that moment, read from that tab's own webm.
    const endT = now();
    const kept = [];
    let cursor = 0;
    for (const w of [...timeline].sort((a, b) => a.start - b.start)) {
        const s = Math.max(w.start + 600, cursor);
        if (s > cursor) kept.push([cursor, Math.min(s, w.end)]);
        cursor = Math.max(cursor, w.end);
    }
    if (endT > cursor) kept.push([cursor, endT]);
    const segs = [];
    for (const [a, b] of kept) {
        const marks = activeLog.filter((m) => m.t > a && m.t < b).map((m) => m.t);
        const cuts = [a, ...marks, b];
        for (let i = 0; i + 1 < cuts.length; i++) {
            const from = cuts[i], to = cuts[i + 1];
            const act = [...activeLog].reverse().find((m) => m.t <= from) || activeLog[0];
            const p = pages[act.idx];
            if (!p || !p.videoPath || !fs.existsSync(p.videoPath)) continue;
            segs.push({ file: p.videoPath, from: Math.max(0, from - p.created) / 1000, to: (to - p.created) / 1000 });
        }
    }
    fs.writeFileSync(path.join(WORK, 'segments.json'), JSON.stringify(segs, null, 1));
    const parts = [];
    segs.forEach((s, i) => {
        if (s.to - s.from < 0.2) return;
        const out = path.join(WORK, `seg-${String(i).padStart(3, '0')}.mp4`);
        try {
            sh(`ffmpeg -y -loglevel error -ss ${s.from.toFixed(2)} -to ${s.to.toFixed(2)} -i "${s.file}" ` +
               `-vf "scale=780:1688:flags=lanczos,fps=25" ` +
               `-c:v libx264 -preset veryfast -crf 23 -pix_fmt yuv420p -an "${out}"`);
            parts.push(out);
        } catch (e) { console.log('segment failed', s, String(e.stderr || e).slice(0, 300)); }
    });
    const list = path.join(WORK, 'concat.txt');
    fs.writeFileSync(list, parts.map((p) => `file '${p}'`).join('\n'));
    const film = path.join(WORK, 'snapppay-test.mp4');
    if (parts.length) sh(`ffmpeg -y -loglevel error -f concat -safe 0 -i "${list}" -c copy -movflags +faststart "${film}"`);
    return fs.existsSync(film) ? film : null;
}

// --- main ------------------------------------------------------------------

(async () => {
    setupGit();
    await start();
    track(page);
    await snapshot(0, { ready: true });
    publish(0);
    let idleFrom = now();
    let ended = false;
    while (!ended) {
        const cmd = readCommand();
        if (!cmd) {
            if (now() - idleFrom > IDLE_LIMIT_MS) { console.log('idle limit, ending'); break; }
            await sleep(POLL_MS);
            continue;
        }
        timeline.push({ start: idleFrom, end: now() });
        console.log(`[${(now() / 1000).toFixed(1)}s] seq ${cmd.seq}: ${cmd.steps.length} steps`);
        const result = { done: 0, returns: [] };
        for (const step of cmd.steps) {
            if (step.do === 'end') { ended = true; break; }
            try {
                const r = await run(step);
                if (r !== undefined) result.returns.push(r);
                result.done++;
            } catch (e) {
                result.error = { step, message: String(e.message || e).slice(0, 1500) };
                break;
            }
        }
        if (!ended) {
            try { await snapshot(cmd.seq, result); } catch (e) { console.log('snapshot failed', e); }
            try { publish(cmd.seq); } catch (e) { console.log('publish failed', String(e.stderr || e)); }
        }
        idleFrom = now();
    }
    timeline.push({ start: idleFrom, end: now() + 10 });
    for (const p of pages) { if (!p.page.isClosed()) p.closedAt = now(); }
    for (const p of pages) { try { p.videoPath = await p.video.path(); } catch (_) {} }
    await context.close();
    await browser.close();
    fs.writeFileSync(path.join(WORK, 'timeline.json'), JSON.stringify({ timeline, pages: pages.map((p) => ({ idx: p.idx, created: p.created, closedAt: p.closedAt, video: p.videoPath })) }, null, 1));
    const film = cutFilm();
    fs.writeFileSync(path.join(OUT_DIR, 'final.json'), JSON.stringify({ film: !!film, bytes: film ? fs.statSync(film).size : 0 }));
    publish('final', film ? [film, path.join(WORK, 'timeline.json')] : [path.join(WORK, 'timeline.json')]);
    console.log('film', film);
})().catch((e) => { console.error(e); process.exit(1); });
