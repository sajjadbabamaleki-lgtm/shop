const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  for (const w of [360, 390, 430, 1200]) {
    const p = await b.newPage({ viewport: { width: w, height: 1800 }, deviceScaleFactor: 2 });
    await p.goto('http://127.0.0.1:8812/', { waitUntil: 'networkidle' });
    await p.waitForTimeout(3000);
    const r = await p.evaluate(() => {
      const box = (s) => { const e = document.querySelector(s); if (!e) return null; const r = e.getBoundingClientRect(); return { top: Math.round(r.top + scrollY), h: Math.round(r.height), shown: getComputedStyle(e).display !== 'none' }; };
      return { tiles: box('.vp-category-row'), phone: box('.vp-homeshop-area[data-vp-place="phone"]'), wide: box('.vp-homeshop-area[data-vp-place="wide"]'), trust: box('.vp-trust-row-wrap'), dice: box('.vp-dice-area'), ladder: box('.vp-ladder-area'), cards: document.querySelectorAll('.vp-homeshop-area .vp-card-name').length };
    });
    console.log(w, JSON.stringify(r));
    const top = w < 992 ? Math.max(0, r.tiles.top - 40) : Math.max(0, (r.wide ? r.wide.top : 0) - 300);
    await p.screenshot({ path: `home-${w}.png`, fullPage: true, clip: { x: 0, y: top, width: w, height: 1700 } });
    await p.close();
  }
  await b.close();
})();
