// TEMPORARY (run 7). SnappPay's merchant test, recorded at 1440x900 on the live site.
// Scripted where the shop's own pages are known; remote-controlled through
// commits where they are not (SnappPay's pages, the card page, the panel).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const SITE = 'https://vikyplus.ir';
const BRANCH = process.env.BRANCH;
const ROOT = path.resolve(__dirname, '../..');
const OUT = path.join(__dirname, 'out');
const CONTROL = '.github/snapp/control/';
fs.mkdirSync(OUT, { recursive: true });

const sh = (c) => execSync(c, { cwd: ROOT, stdio: ['ignore', 'pipe', 'pipe'] }).toString();
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const log = (...a) => console.log(new Date().toISOString().slice(11, 19), ...a);

function push(msg) {
  for (let i = 0; i < 5; i++) {
    try {
      sh('git add -A .github/snapp/out');
      try { sh(`git commit -q -m "${msg} [skip ci]"`); } catch { return; }
      sh(`git pull -q --rebase origin ${BRANCH}`);
      sh(`git push -q origin HEAD:${BRANCH}`);
      return;
    } catch (e) { log('push retry', e.message.slice(0, 200)); }
  }
}

async function waitFor(name, minutes = 45) {
  log('waiting for control/' + name);
  const end = Date.now() + minutes * 60000;
  while (Date.now() < end) {
    try {
      sh(`git fetch -q origin ${BRANCH}`);
      return sh(`git show FETCH_HEAD:${CONTROL}${name}`).trim();
    } catch { await sleep(5000); }
  }
  throw new Error('timed out waiting for ' + name);
}

function clip(name) {
  const segs = fs.readdirSync('/tmp/seg').filter((f) => f.endsWith('.mp4')).sort();
  const done = segs.slice(0, -1);
  if (!done.length) return;
  fs.writeFileSync('/tmp/list.txt', done.map((f) => `file '/tmp/seg/${f}'`).join('\n'));
  execSync(`ffmpeg -v error -y -f concat -safe 0 -i /tmp/list.txt -c copy ${path.join(OUT, name + '.mp4')}`);
  log('clip', name, done.length * 4, 's');
}

let page;
async function shot(name) {
  await page.screenshot({ path: path.join(OUT, name + '.png') });
  const what = await page.evaluate(() => {
    const vis = (e) => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0; };
    const desc = (e) => ({ tag: e.tagName.toLowerCase(), id: e.id || undefined, name: e.getAttribute('name') || undefined, type: e.getAttribute('type') || undefined, text: (e.innerText || e.value || e.getAttribute('placeholder') || e.getAttribute('aria-label') || '').trim().slice(0, 60) });
    return { url: location.href, fields: [...document.querySelectorAll('input,select,textarea,button,a[role=button],[onclick]')].filter(vis).map(desc).slice(0, 60) };
  });
  fs.writeFileSync(path.join(OUT, name + '.json'), JSON.stringify(what, null, 1));
}

const fill = (v) => String(v);

// A value nobody should read in git — the panel's password — arrives
// encrypted to a key made on this runner at start and never pushed. Only the
// public half goes up; it dies with the runner, so the ciphertext left in the
// branch's history opens for nobody.
async function secret(name) {
  const b64 = await waitFor(name);
  fs.writeFileSync('/tmp/secret.bin', Buffer.from(b64, 'base64'));
  return execSync('openssl pkeyutl -decrypt -inkey /tmp/key.pem -in /tmp/secret.bin').toString();
}

async function remote(prefix) {
  for (let n = 1; ; n++) {
    await shot(`${prefix}-${n}`);
    push(`snapp test: ${prefix}-${n}`);
    const cmds = JSON.parse(await waitFor(`${prefix}-${n}.json`));
    for (const c of cmds) {
      log('cmd', JSON.stringify(c));
      try {
        if (c.goto) await page.goto(c.goto, { waitUntil: 'domcontentloaded' });
        if (c.fill) { const v = c.secret ? await secret(c.secret) : fill(c.value); await page.locator(c.fill).first().click(); await page.locator(c.fill).first().fill(''); await page.locator(c.fill).first().pressSequentially(v, { delay: c.secret ? 40 : 90 }); }
        if (c.click) await page.locator(c.click).first().click();
        if (c.text) await page.getByText(c.text, { exact: !!c.exact }).first().click();
        if (c.press) await page.keyboard.press(c.press);
        if (c.select) await page.locator(c.select).first().selectOption(c.option);
        if (c.scroll) await page.mouse.wheel(0, c.scroll);
        if (c.wait) await sleep(c.wait);
        if (c.clip) { await sleep(4500); clip(c.clip); }
        if (c.done) return;
      } catch (e) { log('cmd failed', e.message.slice(0, 300)); fs.writeFileSync(path.join(OUT, `${prefix}-${n}-error.txt`), e.message); }
      await sleep(c.after ?? 1200);
    }
  }
}

(async () => {
  const browser = await chromium.launch({ headless: false, args: ['--window-size=1440,900', '--window-position=0,0', '--lang=fa-IR'] });
  const ctx = await browser.newContext({ viewport: null, locale: 'fa-IR' });
  page = await ctx.newPage();
  execSync('openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out /tmp/key.pem 2>/dev/null && openssl pkey -in /tmp/key.pem -pubout -out ' + path.join(OUT, 'pub.pem'));
  push('snapp test: public key');
  page.setDefaultTimeout(30000);
  try {
    // 1 — the shop, two different products, one discounted, into the basket.
    await page.goto(SITE + '/', { waitUntil: 'domcontentloaded' });
    await sleep(3500);

    // 0 — the dice game, for the discount code. The answer is read off the
    // game's own JSON rather than the card's text.
    let code = null;
    page.on('response', async (r) => {
      if (r.url().includes('/game/dice')) { try { const j = await r.json(); if (j.code) code = j.code; } catch {} }
    });
    await page.locator('#vp-dice').scrollIntoViewIfNeeded(); await sleep(1500);
    for (let i = 0; i < 6 && !code; i++) {
      await page.locator('[data-dice-go]').click();
      await sleep(4500);
    }
    log('dice code', code);
    if (!code) throw new Error('the dice game gave no code in its throws');
    await sleep(4000);
    await page.locator('.vp-prize-shut').click().catch(() => {}); await sleep(1500);
    // Only the shop's test products, and a basket of 300,000-500,000 toman:
    // the listing's own price filter, then the items named «آزمایشی».
    await page.goto(SITE + '/products?max=500000&sort=cheapest', { waitUntil: 'domcontentloaded' });
    await sleep(3000);
    const items = await page.evaluate(() => {
      const num = (t) => Number((t || '').replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[^0-9]/g, '')) || 0;
      return [...document.querySelectorAll('article.vp-card')]
        .filter((c) => !c.querySelector('.vp-card-out') && /آزمایشی/.test(c.innerText))
        .map((c) => ({ href: c.querySelector('a.vp-card-name').href, name: c.querySelector('a.vp-card-name').innerText.trim(), price: num(c.querySelector('.vp-card-price')?.innerText), off: !!c.querySelector('.vp-card-was') }));
    });
    log('test items', JSON.stringify(items));
    fs.writeFileSync(path.join(OUT, 'test-items.json'), JSON.stringify(items, null, 1));
    // Two different items, one discounted when the shop has one, and the
    // quantities that put the basket between 300,000 and 500,000 (after the
    // dice's 5% as well as before it).
    let plan = null;
    const inRange = (t) => t >= 300000 && t <= 500000 && t * 0.95 >= 300000;
    for (const a of items) for (const b of items) {
      if (a.href === b.href || plan) continue;
      if (items.some((x) => x.off) && !b.off) continue;
      for (let qa = 1; qa <= 4 && !plan; qa++) for (let qb = 1; qb <= 3 && !plan; qb++) {
        if (inRange(a.price * qa + b.price * qb)) plan = { a, qa, b, qb, total: a.price * qa + b.price * qb };
      }
    }
    log('plan', JSON.stringify(plan));
    if (!plan) { push('snapp test: no test basket'); throw new Error('no two test items make a 300,000-500,000 basket: ' + JSON.stringify(items)); }

    const addToBasket = async (item, qty) => {
      await page.goto(item.href, { waitUntil: 'domcontentloaded' }); await sleep(2500);
      for (let i = 1; i < qty; i++) { await page.locator('.vp-pick .quantity-plus').first().click(); await sleep(600); }
      await page.locator('.vp-pick .vp-pick-go').first().click(); await page.waitForLoadState('domcontentloaded'); await sleep(2000);
    };
    await addToBasket(plan.a, plan.qa);
    await addToBasket(plan.b, plan.qb);
    await page.goto(SITE + '/cart', { waitUntil: 'domcontentloaded' }); await sleep(5000);
    clip('01-basket'); await shot('01-basket'); push('snapp test: 01 basket');
    await waitFor('go-1');

    // 2 — checkout, the order, the instalment button.
    await page.locator('a.vp-cart-go').first().click(); await page.waitForLoadState('domcontentloaded'); await sleep(1500);
    // The code first: its form reloads the page, so it goes in before the
    // address does.
    await page.locator('#vp-code').click(); await page.locator('#vp-code').pressSequentially(code, { delay: 90 });
    await sleep(800);
    await page.locator('form.vp-code button[type="submit"]').click(); await page.waitForLoadState('domcontentloaded'); await sleep(3000);
    const type = async (sel, v) => { await page.locator(sel).click(); await page.locator(sel).pressSequentially(v, { delay: 70 }); };
    await type('#co-name', 'تست اسنپ پی');
    await type('#co-phone', '09121161311');
    await type('#co-province', 'تهران');
    await type('#co-city', 'تهران');
    await type('#co-address', 'تهران، خیابان آزمایشی، پلاک ۱ — سفارش تست اسنپ‌پی');
    await type('#co-postal', '1234567890');
    await page.locator('input[name="shipping_method_id"]').first().check(); await sleep(1500);
    await page.locator('form.vp-checkout button[type="submit"]').click(); await page.waitForLoadState('domcontentloaded');
    await page.waitForSelector('.vp-snapp-form:not([hidden])', { timeout: 20000 }).catch(() => log('snapp button did not appear'));
    await sleep(4000);
    // The site's own «turn the VPN off» notice opens over the payment page.
    await page.getByText('متوجه شدم').first().click({ timeout: 8000 }).catch(() => log('no VPN notice'));
    await sleep(3000);
    await shot('02-order'); push('snapp test: 02 order');

    // 3 — SnappPay, straight on (the order lets go of its stock after 15
    // minutes), then step by step from here.
    await page.locator('.vp-snapp-form button[type="submit"]').first().click();
    await sleep(7000);
    // SnappPay refuses some addresses outside Iran with a 403 page; this
    // runner's address is whatever GitHub gave it, so try again a few times.
    for (let i = 0; i < 4 && /Access Denied|Error 403/.test(await page.content()); i++) {
      log('SnappPay 403, retry', i + 1);
      await sleep(8000); await page.reload({ waitUntil: 'domcontentloaded' }).catch(() => {}); await sleep(5000);
    }
    await remote('03');
  } catch (e) {
    log('FAILED', e.message);
    fs.writeFileSync(path.join(OUT, 'error.txt'), e.stack || e.message);
    try { await shot('error'); } catch {}
  }
  await sleep(4500);
  try { execSync('kill -INT $(cat /tmp/ffmpeg.pid)'); } catch {}
  await sleep(3000);
  const segs = fs.readdirSync('/tmp/seg').filter((f) => f.endsWith('.mp4')).sort();
  fs.writeFileSync('/tmp/list.txt', segs.map((f) => `file '/tmp/seg/${f}'`).join('\n'));
  try { execSync(`ffmpeg -v error -y -f concat -safe 0 -i /tmp/list.txt -c copy ${path.join(OUT, 'full.mp4')}`); } catch (e) { log('final concat', e.message); }
  push('snapp test: full video');
  await browser.close();
})();
