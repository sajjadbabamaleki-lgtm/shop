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
      let all = [];
      for (let pg = 1; pg <= 8; pg++) {
        const d = new DOMParser().parseFromString(await (await fetch('/products?page=' + pg)).text(), 'text/html');
        const got = [...d.querySelectorAll('.vp-shop-grid .col')];
        if (!got.length) break;
        all = all.concat(got);
      }
      const fold = (t) => t.toLowerCase().replace(/ي/g,'ی').replace(/ك/g,'ک').replace(/[\u200c\u064b-\u065f]/g,'').replace(/[۰-۹]/g, c => '۰۱۲۳۴۵۶۷۸۹'.indexOf(c));
      const SKIP = ['کتونی','کفش','صندل','بوت','نیم','نیمبوت','کیف','ونس','اسنیکر','زنانه','مردانه','دخترانه','پسرانه','اسپرت','مجلسی','رسمی','روزمره','مدل','طرح','جدید'].map(fold);
      const key = (t) => { const k = []; for (const w of fold(t).trim().split(/\s+/)) { if (w === 'رنگ') break; if (!SKIP.includes(w)) k.push(w); if (k.length === 2) break; } return k.join(' '); };
      const seen = new Set(); const picked = [];
      for (const c of all) {
        const src = c.querySelector('img')?.getAttribute('src') || '';
        if (c.querySelector('.vp-card-out') || !(src.includes('/storage/basalam/') || src.includes('/assets/img/product/'))) continue;
        const k = key(c.querySelector('.vp-card-name')?.textContent || '');
        if (seen.has(k)) continue; seen.add(k); picked.push(c); if (picked.length === 8) break;
      }
      const cards = picked.map(c => `<div>${c.innerHTML}</div>`).join('');
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
