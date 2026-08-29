#!/usr/bin/env node
/**
 * Loads every page and fails if the browser reported an error.
 *
 * Run: node theme/check-scripts.js
 *
 * **Why this exists.** `main.js` is one long closure, so *one* throw anywhere
 * in it stops every line below — and a jQuery plugin that is not loaded throws
 * whether or not its selector matches anything. When the eight unused
 * libraries came off the page, one call was missed: `$('.progress-bar')
 * .waypoint(…)`, whose plugin had been riding inside `jquery.counterup.min.js`.
 * Everything after line 1138 stopped running on every page of the shop — the
 * daily deal's countdown, the quantity steppers on the product page and in the
 * basket, the colour scheme, the woocommerce toggles.
 *
 * **Nothing else here could see it.** The PHP suite does not run a browser.
 * `check-parity.js` renders two copies of the same page and compares them to
 * each other, so a script that is broken on both is a page that matches.
 * `check-overflow.js` only asks how wide the document is. The client found it,
 * which is the wrong way to find it.
 *
 * `VP_PAGES=/a,/b` overrides the list.
 */
const { chromium } = require('/opt/node22/lib/node_modules/playwright');

const BASE = process.env.VP_BASE || 'http://127.0.0.1:8812';
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
  '/wholesale',
  '/franchise',
  '/no-such-page-404',
].join(',')).split(',');

// Two widths, because a phone runs rules and scripts a desktop does not.
const WIDTHS = [390, 1200];

(async () => {
  const browser = await chromium.launch({
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
  });

  let failed = 0;
  let checked = 0;

  for (const width of WIDTHS) {
    const ctx = await browser.newContext({ viewport: { width, height: 900 } });

    for (const path of PAGES) {
      const page = await ctx.newPage();
      const faults = [];

      page.on('pageerror', (e) => faults.push(`threw: ${e.message}`));
      page.on('console', (m) => {
        if (m.type() !== 'error') return;

        // Only our own files. The one address on the page that is not ours is
        // the eNamad seal, which this container cannot reach at all and which
        // the page is built to survive either way — see «the eNamad seal was
        // holding the page open». A console line about *that* request says
        // nothing about the shop's scripts.
        const from = (m.location() && m.location().url) || '';
        if (from && !from.startsWith(BASE)) return;

        // The 404 page's own response is a 404. That is the page working.
        if (from === BASE + path && /404/.test(m.text())) return;

        faults.push(`console: ${m.text()}${from ? `  (${from.replace(BASE, '')})` : ''}`);
      });

      try {
        await page.goto(BASE + path, { waitUntil: 'networkidle', timeout: 60000 });
        // The scripts at the foot run on ready; give them their turn.
        await page.waitForTimeout(600);
      } catch (e) {
        faults.push(`did not load: ${e.message.split('\n')[0]}`);
      }

      checked++;
      if (faults.length) {
        failed++;
        console.log(`${String(width).padStart(4)} ${path}`);
        for (const fault of faults) console.log(`       ${fault}`);
      }
      await page.close();
    }

    await ctx.close();
  }

  await browser.close();

  if (failed) {
    console.log(`\n${failed} of ${checked} page loads reported an error.`);
    process.exit(1);
  }

  console.log(`${checked} page loads at ${WIDTHS.join('/')}, no script errors.`);
})();
