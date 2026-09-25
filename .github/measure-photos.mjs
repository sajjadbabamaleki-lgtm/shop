import { chromium } from 'playwright';
const B = 'https://vikyplus.ir';
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 390, height: 900 } });
const cards = [];
for (let p = 1; p <= 15; p++) {
  const r = await page.goto(`${B}/products?page=${p}`, { waitUntil: 'load', timeout: 90000 }).catch(e => null);
  if (!r) { console.log('page', p, 'failed'); continue; }
  await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } });
  await page.waitForTimeout(1500);
  const got = await page.evaluate(() => [...document.querySelectorAll('a.vp-card-shot')].map(a => {
    const img = a.querySelector('img'); const box = a.getBoundingClientRect();
    if (!img) return { href: a.href, noimg: true };
    const ir = img.getBoundingClientRect(); const cs = getComputedStyle(img);
    const nw = img.naturalWidth, nh = img.naturalHeight;
    let cw = ir.width, ch = ir.height;
    if (cs.objectFit === 'contain' && nw && nh) { const s = Math.min(ir.width / nw, ir.height / nh); cw = nw * s; ch = nh * s; }
    if (cs.objectFit === 'cover' && nw && nh) { cw = ir.width; ch = ir.height; }
    return { href: a.getAttribute('href'), supplied: a.classList.contains('is-supplied'), fit: cs.objectFit, file: `${nw}x${nh}`, src: img.currentSrc.split('/').slice(-1)[0], box: `${Math.round(box.width)}x${Math.round(box.height)}`, coverPct: Math.round(100 * (cw * ch) / (box.width * box.height)) };
  }));
  if (got.length === 0) break;
  cards.push(...got);
}
console.log('CARDS', cards.length);
const bySupplied = {}; for (const c of cards) { const k = `${c.supplied}|${c.fit}`; bySupplied[k] = (bySupplied[k] || 0) + 1; }
console.log('GROUPS', JSON.stringify(bySupplied));
for (const c of cards) console.log('CARD', JSON.stringify(c));
// product pages of up to 6 non-supplied and 3 supplied non-square
const pick = [...cards.filter(c => !c.supplied).slice(0, 6), ...cards.filter(c => c.supplied && c.file.split('x')[0] !== c.file.split('x')[1]).slice(0, 4)];
for (const c of pick) {
  await page.goto(B + c.href, { waitUntil: 'load', timeout: 90000 }).catch(() => null);
  await page.waitForTimeout(1500);
  const pd = await page.evaluate(() => { const s = document.querySelector('.vp-pdp-shot'); if (!s) return null; const box = s.getBoundingClientRect(); return { cls: s.className, box: `${Math.round(box.width)}x${Math.round(box.height)}`, imgs: [...s.querySelectorAll('img')].slice(0, 3).map(i => { const r = i.getBoundingClientRect(); const cs = getComputedStyle(i); return { file: `${i.naturalWidth}x${i.naturalHeight}`, fit: cs.objectFit, pad: cs.padding, rect: `${Math.round(r.width)}x${Math.round(r.height)}` }; }) }; });
  console.log('PDP', c.href, JSON.stringify(pd));
}
await browser.close();
