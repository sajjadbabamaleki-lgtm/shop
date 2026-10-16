const { chromium } = require('playwright');
const fs = require('fs');
(async () => {
  const b = await chromium.launch();
  const p = await (await b.newContext()).newPage();
  await p.goto('https://vikyplus.ir/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  const rows = await p.evaluate(async () => {
    const out = [];
    for (let pg = 1; pg <= 12; pg++) {
      const d = new DOMParser().parseFromString(await (await fetch('/products?page=' + pg)).text(), 'text/html');
      const got = [...d.querySelectorAll('.vp-shop-grid .col')];
      if (!got.length) break;
      for (const c of got) out.push({ name: c.querySelector('.vp-card-name')?.textContent.trim(), href: c.querySelector('.vp-card-name')?.getAttribute('href'), src: c.querySelector('img')?.getAttribute('src'), out: !!c.querySelector('.vp-card-out') });
    }
    for (const r of out) {
      try {
        const img = new Image(); img.crossOrigin = 'anonymous'; img.src = r.src;
        await img.decode();
        const cv = document.createElement('canvas'); cv.width = img.naturalWidth; cv.height = img.naturalHeight;
        const x = cv.getContext('2d'); x.drawImage(img, 0, 0);
        const px = (a, b2) => { const d = x.getImageData(a, b2, 8, 8).data; let s = [0,0,0]; for (let i = 0; i < d.length; i += 4) { s[0]+=d[i]; s[1]+=d[i+1]; s[2]+=d[i+2]; } return s.map(v => Math.round(v / 64)); };
        const W = img.naturalWidth, H = img.naturalHeight;
        r.size = W + 'x' + H; r.tl = px(4, 4); r.tr = px(W - 12, 4); r.ml = px(4, H / 2);
      } catch (e) { r.err = String(e); }
    }
    return out;
  });
  fs.mkdirSync('shots', { recursive: true });
  fs.writeFileSync('shots/scan.json', JSON.stringify(rows, null, 1));
  await b.close();
})();
