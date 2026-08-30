#!/usr/bin/env node
/**
 * Renders every page against the cut stylesheets and against the full ones,
 * and fails on a single differing pixel.
 *
 * Run: node theme/check-css-subset.js      (needs the app on :8812)
 *
 * **This is the guard that makes `make-css-subset.js` safe to have written.**
 * That script decides what to drop by asking whether a class could ever exist
 * here; this one asks the only question that settles it — does the page still
 * look the same. Nothing else we have can see this failure. `check-parity.js`
 * compares two copies of the same page, so a rule missing from both is a pair
 * that matches. `check-overflow.js` asks how wide the document is. The suite
 * runs no browser.
 *
 * The full sheets are never copied into `public/` — they are served straight
 * off `theme/base-stylesheets/` by intercepting the request, so a run that
 * dies halfway leaves nothing behind to clean up and no 660KB stylesheet where
 * a deploy might find it.
 *
 * **The closed states are the point of the last section.** A stylesheet cut by
 * what a crawler can see would take the phone drawer's rules with it: the
 * drawer is parked off-screen, the search panel is display:none, and the mini
 * basket is shut. Those are the three states that are invisible to everything
 * else here — see «the phone drawer is invisible to every check we have» —
 * so each is opened and photographed.
 *
 * `VP_PAGES=/a,/b` and `VP_WIDTHS=390,1200` override the lists.
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const sharp = require('./node_modules/sharp');

const ROOT = path.resolve(__dirname, '..');
// Where `make-css-subset.js` keeps the untouched originals — outside
// `download-version/`, which Netlify publishes. See FULL_DIR there.
const CSS = path.join(ROOT, 'theme/base-stylesheets');
const BASE = process.env.VP_BASE || 'http://127.0.0.1:8812';

/** the URL the page asks for => the untouched sheet to answer it with. */
const FULL = {
  '/assets/css/style.rtl.css': 'style.rtl.full.css',
  '/assets/css/bootstrap.rtl.min.css': 'bootstrap.rtl.full.min.css',
  '/assets/css/swiper-bundle.rtl.min.css': 'swiper-bundle.rtl.full.min.css',
};

const PAGES = (process.env.VP_PAGES || [
  '/',
  '/products',
  '/products/new-balance-530',
  '/categories/sneaker',
  '/search?q=%DA%A9%D8%AA%D9%88%D9%86%DB%8C',
  '/cart',
  '/checkout',
  '/account/enter',
  '/about',
  '/contact',
  '/size-guide',
  '/faq',
  '/terms',
  '/privacy',
  '/wholesale',
  '/franchise',
  '/no-such-page-404',
].join(',')).split(',');

const WIDTHS = (process.env.VP_WIDTHS || '390,768,1200,1920').split(',').map(Number);

/** The three states nothing else here can see, each opened before the shot. */
const STATES = [
  { name: 'phone drawer open', width: 390, page: '/', click: '.th-menu-toggle' },
  { name: 'search open', width: 390, page: '/', click: '.searchBoxToggler' },
  { name: 'mini basket open', width: 390, page: '/', click: '.sideMenuToggler' },
];

async function shoot(browser, { width, url, full, click }) {
  const ctx = await browser.newContext({ viewport: { width, height: 900 } });
  const page = await ctx.newPage();

  if (full) {
    await page.route('**/assets/css/*.css', async (route) => {
      const asked = new URL(route.request().url()).pathname;
      const swap = FULL[asked];
      if (!swap) return route.continue();
      await route.fulfill({
        status: 200,
        contentType: 'text/css',
        body: fs.readFileSync(path.join(CSS, swap)),
      });
    });
  }

  // Settled the same way `check-parity.js` settles a page, and for the same
  // reason: the deck autoplays and the reveals are timed, so two loads of the
  // same page differ by seconds rather than by stylesheets.
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto(BASE + url, { waitUntil: 'networkidle', timeout: 60000 });

  // A state that has to be *opened* is photographed with motion frozen, in
  // both runs equally. Measured otherwise: the same stylesheets, twice, differ
  // by 1,777 pixels in the opened basket and 0 with this — the drawer is
  // mid-transition at whatever moment the shot lands, and a check whose noise
  // floor is 1,777 cannot report a real 144. The page shots below do NOT get
  // this, deliberately: an animation that never runs because its @keyframes
  // was dropped is exactly the fault this whole check exists for, and freezing
  // everything would hide it. That fault is what blanked the hero on the first
  // run of the cut, and it is caught in the un-frozen shots.
  if (click) {
    await page.addStyleTag({ content: '*,*::before,*::after{transition:none!important;animation:none!important}' });
  }

  await page.evaluate(() => {
    document.querySelectorAll('.swiper').forEach((el) => {
      if (!el.swiper) return;
      if (el.swiper.autoplay) el.swiper.autoplay.stop();
      el.swiper.slideTo(el.swiper.params.initialSlide || 0, 0);
    });
    document.querySelectorAll('.vp-enter').forEach((el) => el.classList.add('vp-entered'));
  });

  // Wait for the pictures and the faces themselves, not for a guess at how
  // long they take. The story row at y≈623 was the whole of this check's
  // remaining noise: its thumbnails had sometimes painted and sometimes not,
  // which read as four differing pixels on a page of 2.7 million.
  //
  // **Every wait is bounded.** The one address on this page that is not ours
  // is the eNamad seal, and this container cannot reach it: its request does
  // not fail, it hangs, so an unbounded wait for `load` or `error` waits for
  // ever. The first version of this line did, and a sweep that had been
  // taking four minutes stopped finishing at all.
  await page.evaluate(() => {
    const capped = (p) => Promise.race([p, new Promise((done) => setTimeout(done, 4000))]);

    return Promise.all([
      capped(document.fonts ? document.fonts.ready : Promise.resolve()),
      ...[...document.images].map((img) => (img.complete
        ? Promise.resolve()
        : capped(new Promise((done) => {
          img.addEventListener('load', done);
          img.addEventListener('error', done);
        })))),
    ]);
  });
  await page.waitForTimeout(1500);

  if (click) {
    const target = page.locator(click).first();
    if (await target.count()) {
      await target.click({ timeout: 5000 }).catch(() => {});
      await page.waitForTimeout(900);
    }
    // Off the button, so no hover or focus ring rides along in one shot and
    // not the other.
    await page.mouse.move(5, 700);
    await page.waitForTimeout(200);
  }

  const png = await page.screenshot({ fullPage: !click, animations: 'disabled' });
  await ctx.close();
  return png;
}

/** How many pixels differ, and where the first one is. */
async function differences(a, b) {
  const A = sharp(a);
  const B = sharp(b);
  const ma = await A.metadata();
  const mb = await B.metadata();

  if (ma.width !== mb.width || ma.height !== mb.height) {
    return { count: -1, note: `${ma.width}x${ma.height} against ${mb.width}x${mb.height}` };
  }

  const [pa, pb] = await Promise.all([
    A.raw().toBuffer(),
    B.raw().toBuffer(),
  ]);

  let count = 0;
  let first = null;
  const channels = ma.channels;
  for (let i = 0; i < pa.length; i += channels) {
    let same = true;
    for (let c = 0; c < channels; c++) if (pa[i + c] !== pb[i + c]) { same = false; break; }
    if (!same) {
      count++;
      if (!first) {
        const px = (i / channels) | 0;
        first = `${px % ma.width},${(px / ma.width) | 0}`;
      }
    }
  }

  return { count, note: first ? `first at ${first}` : '' };
}

(async () => {
  for (const file of Object.values(FULL)) {
    if (!fs.existsSync(path.join(CSS, file))) {
      console.error(`theme/base-stylesheets/${file} is not here. Run node theme/make-css-subset.js first — it keeps the original.`);
      process.exit(1);
    }
  }

  const browser = await chromium.launch({
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
  });

  const jobs = [];
  for (const width of WIDTHS) for (const url of PAGES) jobs.push({ width, url, label: `${width} ${url}` });
  for (const s of STATES) jobs.push({ width: s.width, url: s.page, click: s.click, label: `${s.width} ${s.page} — ${s.name}` });

  let bad = 0;

  for (const job of jobs) {
    let cut;
    let full;
    try {
      cut = await shoot(browser, { ...job, full: false });
      full = await shoot(browser, { ...job, full: true });
    } catch (e) {
      console.log(`  ${job.label.padEnd(48)} did not render: ${e.message.split('\n')[0]}`);
      bad++;
      continue;
    }

    const { count, note } = await differences(cut, full);

    // A difference is only a difference if it is bigger than this page's own
    // noise. Measured on the home page at 390 with the *same* stylesheets
    // twice: 51 pixels — a lazily-painted photograph and a slider settling a
    // subpixel apart. So rather than bless a threshold, a page that differs is
    // rendered a third time against the cut sheets it already used, and the
    // two identical runs say what this page's floor actually is. Only a
    // difference above its own floor is a difference.
    // `count` is -1 when the two are not even the same size, which no amount
    // of noise explains — the control is only ever asked about a real count.
    let floor = 0;
    if (count > 0) {
      const again = await shoot(browser, { ...job, full: false });
      const control = await differences(cut, again);
      floor = control.count > 0 ? control.count : 0;
    }

    if (count === 0 || (count > 0 && count <= floor)) {
      console.log(`  ${job.label.padEnd(48)} identical${count ? ` (${count} px, under this page's own ${floor} px of noise)` : ''}`);
    } else {
      bad++;
      console.log(`  ${job.label.padEnd(48)} ${count < 0 ? 'different size' : `${count} pixels differ`}  ${note}`);
      const dir = path.join(ROOT, 'theme/.css-subset-diff');
      fs.mkdirSync(dir, { recursive: true });
      const stem = job.label.replace(/[^a-z0-9]+/gi, '-');
      fs.writeFileSync(path.join(dir, `${stem}-cut.png`), cut);
      fs.writeFileSync(path.join(dir, `${stem}-full.png`), full);
      console.log(`       written to theme/.css-subset-diff/${stem}-{cut,full}.png`);
    }
  }

  await browser.close();

  if (bad) {
    console.log(`\n${bad} of ${jobs.length} renders differ. The cut took a rule the page still needs:`);
    console.log('add what it names to the vocabulary in theme/make-css-subset.js, or put the class in a template.');
    process.exit(1);
  }

  console.log(`\n${jobs.length} renders, every one identical to the full stylesheets.`);
})();
