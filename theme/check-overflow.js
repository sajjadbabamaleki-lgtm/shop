/*
 * Nothing on any page may be wider than the window it is in.
 *
 * The home page has a pixel-for-pixel guard (check-parity.js) because there is
 * a second copy of it to compare against. The pages built after it have no
 * such copy, and a baseline screenshot per page would be a folder of binaries
 * nobody can read a diff of. So this checks the one thing that is objectively
 * wrong rather than a matter of taste: a page that scrolls sideways, and the
 * element that makes it.
 *
 * A phone is in the list because every one of these pages was designed on a
 * desktop and screenshotted on a desktop, which is how a table with nine
 * columns ends up two centimetres wider than an iPhone.
 *
 * Run the storefront on 8812 first. Zero offenders is the expected answer.
 */
const { chromium } = require('/opt/node22/lib/node_modules/playwright');

const BASE = process.env.VP_BASE || 'http://127.0.0.1:8812';
const WIDTHS = [390, 768, 1200, 1920];

const PAGES = (process.env.VP_PAGES || [
  '/',
  '/products',
  '/products?q=nothing-at-all',
  '/products/new-balance-530',
  '/categories/sneaker',
  '/cart',
  '/orders',
  '/vendors/apply',
  // The content pages. The size guide is the one that earns its place here: it
  // is the only page on this site with a table on it, and a four-column table
  // is exactly the thing that is two centimetres wider than a phone.
  '/about',
  '/contact',
  '/size-guide',
  '/faq',
  '/terms',
  '/privacy',
  // The two enquiry pages. Each ends in a form, which is the other thing that
  // reliably overflows a phone.
  '/wholesale',
  '/franchise',
  // A 404. It has a shell of its own — layouts/error.blade.php — so nothing
  // else in this list covers it.
  '/no-such-page-as-this',
  '/admin/login',
  // The panel's enquiry screen, reached only with VP_LOGIN. Seven columns and
  // a sentence in one of them — the exact shape this check exists for.
  '/admin/enquiries',
].join(',')).split(',');

(async () => {
  const browser = await chromium.launch({
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
  });

  let offenders = 0;

  // The panel's pages need a session. VP_LOGIN=email:password signs in once
  // per context — the wide tables in there are exactly what this check is for,
  // so leaving them out would leave out the risk.
  const login = process.env.VP_LOGIN ? process.env.VP_LOGIN.split(':') : null;

  for (const width of WIDTHS) {
    const context = await browser.newContext({ viewport: { width, height: 900 } });
    const page = await context.newPage();

    if (login) {
      await page.goto(BASE + '/admin/login', { waitUntil: 'networkidle' });
      await page.fill('#lg-email', login[0]);
      await page.fill('#lg-pass', login[1]);
      await page.click('button[type=submit]');
      await page.waitForLoadState('networkidle');
    }

    for (const path of PAGES) {
      await page.goto(BASE + path, { waitUntil: 'networkidle' });

      // The document's own scroll width, and then — only if it is over — the
      // elements actually sticking out, so the report names something a person
      // can go and look at rather than saying "somewhere on this page".
      const found = await page.evaluate((viewport) => {
        const doc = document.documentElement;
        const over = doc.scrollWidth - viewport;

        if (over <= 1) {
          return null;
        }

        const guilty = [...document.querySelectorAll('body *')]
          .map((el) => ({ el, box: el.getBoundingClientRect() }))
          .filter(({ box }) => box.width > 0 && (box.right > viewport + 1 || box.left < -1))
          // The outermost offender is the useful one: everything inside a wide
          // element is also wide, and listing all of them buries it.
          .filter(({ el }) => ![...document.querySelectorAll('body *')].some((other) =>
            other !== el && other.contains(el) && other.getBoundingClientRect().right > viewport + 1))
          .slice(0, 4)
          .map(({ el, box }) => `${el.tagName.toLowerCase()}.${(el.className || '').toString().split(' ').filter(Boolean).slice(0, 3).join('.')} → ${Math.round(box.right)}px`);

        return { over: Math.round(over), guilty };
      }, width);

      if (found) {
        offenders++;
        console.log(`${width}  ${path}  overflows by ${found.over}px`);
        found.guilty.forEach((g) => console.log(`        ${g}`));
      }
    }

    await context.close();
  }

  await browser.close();

  console.log(offenders === 0
    ? `no page overflows at ${WIDTHS.join('/')}`
    : `${offenders} page/width combinations overflow`);

  process.exit(offenders === 0 ? 0 : 1);
})();
