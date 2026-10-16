const { chromium } = require('playwright');
const fs = require('fs');
const CSS = fs.readFileSync(__dirname + '/band.css', 'utf8');
const B = 'https://vikyplus.ir';
(async () => {
  const b = await chromium.launch();
  for (const w of [390, 1440]) {
    const p = await (await b.newContext({ viewport: { width: w, height: 900 }, deviceScaleFactor: 2 })).newPage();
    await p.goto(B + '/', { waitUntil: 'networkidle', timeout: 120000 });
    await p.evaluate(async (CSS) => {
      const s = document.createElement('style'); s.textContent = CSS; document.head.appendChild(s);
      const doc = new DOMParser().parseFromString(await (await fetch('/products')).text(), 'text/html');
      const cards = [...doc.querySelectorAll('.vp-shop-grid .col')]
        .filter(c => !c.querySelector('.vp-card-out') && !(c.querySelector('img')?.getAttribute('src') || '').includes('placeholder'))
        .slice(0, 8).map(c => `<div>${c.innerHTML}</div>`).join('');
      const strip = doc.querySelector('.vp-shop-strip').outerHTML;
      const total = (doc.querySelector('.vp-shop-bar-count')?.textContent || '').trim();
      const arrow = '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i>';
      const sec = document.createElement('section');
      sec.className = 'vp-homeshop-area'; sec.id = 'vp-homeshop';
      sec.innerHTML = `<div class="container th-container"><div class="vp-listing-panel vp-homeshop">
        <div class="vp-homeshop-head"><div><h2 class="vp-homeshop-title">فروشگاه</h2><p class="vp-homeshop-sub">جست‌وجو کن، دسته را بزن، یا از همین‌جا بخر</p></div><a class="vp-homeshop-all" href="/products">همه ${arrow}</a></div>
        <div class="vp-shop-top"><form class="vp-shop-find"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" placeholder="چی می‌خوای؟"></form><a class="vp-shop-filter-btn" href="/products?filter=open"><i class="fa-solid fa-sliders" aria-hidden="true"></i>فیلتر</a></div>
        ${strip}<div class="vp-homeshop-grid">${cards}</div>
        <a class="vp-homeshop-go" href="/products">دیدن همه ${total} ${arrow}</a></div></div>`;
      const l = document.querySelector('.vp-ladder-area'); l.parentNode.insertBefore(sec, l);
      document.querySelectorAll('img[loading=lazy]').forEach(i => i.loading = 'eager');
    }, CSS);
    await p.addStyleTag({ content: '.vp-support-fab,.vp-whatsapp,header,.th-header{display:none!important}' });
    await p.waitForTimeout(4000);
    const r = await p.evaluate(() => { const e = document.querySelector('#vp-homeshop').getBoundingClientRect(); return { y: e.top + scrollY, h: e.height }; });
    await p.setViewportSize({ width: w, height: Math.ceil(r.h) + 40 });
    await p.evaluate(y => scrollTo(0, y - 20), r.y);
    await p.waitForTimeout(3000);
    await p.screenshot({ path: `shots/homeshop-live-${w}.png` });
  }
  await b.close();
})();
