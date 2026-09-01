#!/usr/bin/env node
/**
 * Renders the preview page and the Laravel page side by side and reports how
 * many pixels differ.
 *
 * The look was settled on the static page and the storefront was ported from
 * it; the storefront now draws six of its bands out of the database instead of
 * out of the markup. This is what says those two facts have not come apart —
 * that the catalogue is still serving exactly what was designed, down to the
 * pixel, and that a change to one side has not quietly failed to reach the
 * other.
 *
 * Zero is the expected answer at every width. Anything else is either a change
 * that has only landed on one side, or seed data that no longer matches what
 * the page was drawn with.
 *
 * Needs both servers up:
 *   cd download-version && setsid nohup python3 -m http.server 8811 &
 *   cd storefront && php artisan serve --port=8812
 *
 * Run: node theme/check-parity.js
 * Exits non-zero if any width differs.
 */
const fs = require('fs');
const os = require('os');
const path = require('path');
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const sharp = require(path.join(__dirname, 'node_modules/sharp'));

const PAGES = {
  preview: 'http://127.0.0.1:8811/shoe-shop-rtl.html',
  storefront: 'http://127.0.0.1:8812/',
};

// The header has broken onto three lines and overflowed the document twice, so
// the check is never run at one width.
const WIDTHS = [992, 1200, 1440, 1920];

const OUT = fs.mkdtempSync(path.join(os.tmpdir(), 'vp-parity-'));

async function shoot(browser, url, width, file) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } });
  // The reveal-on-scroll and the ladder's own walk-through are timed, so both
  // pages are put in the state the CSS falls back to instead of being raced.
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.evaluate(() => {
    // **Every swiper, not only the hero.** The hero deck autoplays, so it was
    // pinned from the start; the stories, the brand row and the best-sellers
    // band are sliders too, and they settle where they settle. That was worth
    // about 6,800 pixels — 0.09% — on the 1440 shot roughly one run in four,
    // always in the best-sellers band at row ~1970, and it was indistinguish-
    // able from a real regression except by re-running until it went away.
    // A check whose answer is "zero, usually" is not a check.
    //
    // `initialSlide` rather than 1, because that is each deck's own opening
    // slide; the hero's is the one carrying the real photograph.
    document.querySelectorAll('.swiper').forEach((el) => {
      if (!el.swiper) return;
      if (el.swiper.autoplay) el.swiper.autoplay.stop();
      el.swiper.slideTo(el.swiper.params.initialSlide || 0, 0);
    });
    // Only what has been scrolled past is revealed; show everything so the
    // whole page is in its settled state.
    document.querySelectorAll('.vp-enter').forEach((el) => el.classList.add('vp-entered'));

    // **The two bands that have no static counterpart, hidden on both pages.**
    //
    // «نظر مشتریان» and «مقالات» draw approved customer reviews and articles
    // the shop has written. Neither exists in `download-version/`, and neither
    // may: that directory is published, and a published page carrying
    // testimonials nobody wrote or articles nobody published would be a
    // fabrication rather than a design preview. The Blade side draws nothing
    // when there is nothing, so on the freshly seeded database this check is
    // meant to run against, both pages already agree — this line is what keeps
    // that true on a developer's own database, which is full of whatever they
    // were last looking at.
    //
    // Hidden on *both* pages, so the check still notices if one of them ever
    // grows a band the other has not.
    document.querySelectorAll('.vp-home-reviews, .vp-home-arts')
        .forEach((el) => { el.style.display = 'none'; });
  });

  // The pictures themselves, bounded: a photograph that has not decoded is
  // the other half of the same flake. The one address on this page that is
  // not ours is the eNamad seal, which this container cannot reach and whose
  // request hangs rather than failing, so every wait here has a ceiling.
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
  await page.screenshot({ path: file, fullPage: true, animations: 'disabled' });
  await page.close();
}

/** Pixels whose worst channel differs by more than a rounding step. */
async function compare(a, b) {
  const [ma, mb] = [await sharp(a).metadata(), await sharp(b).metadata()];
  if (ma.width !== mb.width || ma.height !== mb.height) {
    return { sizes: [`${ma.width}x${ma.height}`, `${mb.width}x${mb.height}`] };
  }

  const [ra, rb] = [
    await sharp(a).raw().toColourspace('srgb').toBuffer(),
    await sharp(b).raw().toColourspace('srgb').toBuffer(),
  ];

  const channels = ra.length / (ma.width * ma.height);
  let differing = 0;
  let worst = 0;
  let firstRow = -1;

  for (let i = 0; i < ra.length; i += channels) {
    let d = 0;
    for (let c = 0; c < 3; c++) d = Math.max(d, Math.abs(ra[i + c] - rb[i + c]));
    if (d > 2) {
      differing++;
      if (d > worst) worst = d;
      if (firstRow < 0) firstRow = Math.floor(i / channels / ma.width);
    }
  }

  return { size: `${ma.width}x${ma.height}`, total: ma.width * ma.height, differing, worst, firstRow };
}

(async () => {
  const browser = await chromium.launch({
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
  });

  let failed = false;

  for (const width of WIDTHS) {
    const files = {};
    for (const [name, url] of Object.entries(PAGES)) {
      files[name] = path.join(OUT, `${name}-${width}.png`);
      await shoot(browser, url, width, files[name]);
    }

    const result = await compare(files.preview, files.storefront);

    if (result.sizes) {
      failed = true;
      console.log(`${width}: the two pages are not even the same size — preview ${result.sizes[0]}, storefront ${result.sizes[1]}`);
      console.log(`         ${files.preview}\n         ${files.storefront}`);
      continue;
    }

    if (result.differing === 0) {
      console.log(`${width}: ${result.size}, identical`);
      fs.unlinkSync(files.preview);
      fs.unlinkSync(files.storefront);
      continue;
    }

    failed = true;
    const share = ((result.differing / result.total) * 100).toFixed(4);
    console.log(
      `${width}: ${result.size}, ${result.differing} of ${result.total} pixels differ (${share}%), ` +
      `worst channel gap ${result.worst}, first at row ${result.firstRow}`
    );
    console.log(`         ${files.preview}\n         ${files.storefront}`);
  }

  await browser.close();

  if (!failed) fs.rmSync(OUT, { recursive: true, force: true });

  process.exit(failed ? 1 : 0);
})();
