// Find elements wider than a phone screen on product pages (page renders zoomed out on mobile).
const { chromium, devices } = require('playwright');
(async () => { const b = await chromium.launch(); const c = await b.newContext({ ...devices['Pixel 7'] }); const p = await c.newPage();
  for (const u of ['/lv/product/personalizetas-kazu-sveces-viesiem-izveidojiet-savas/', '/lv/product/personalizetas-svecites-berna-dzimsanas-dienai/', '/lv/product/korporativas-davanas-sveces-ar-logo/', '/lv/product/ziemassvetku-bumbas-formas-svece/']) {
    await p.goto('https://marmarcandles.com' + u + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 }); await p.waitForTimeout(3000);
    const r = await p.evaluate(() => {
      const W = document.documentElement.clientWidth; const out = [];
      for (const el of document.querySelectorAll('body *')) { const b = el.getBoundingClientRect(); if (b.width > 0 && b.right > W + 2) out.push((el.tagName + '.' + (el.className && el.className.baseVal === undefined ? el.className : '')).slice(0, 90) + ' right=' + Math.round(b.right) + ' w=' + Math.round(b.width)); }
      return { W, sw: document.documentElement.scrollWidth, vp: window.innerWidth, zoom: window.visualViewport && window.visualViewport.scale, over: out.slice(0, 12) };
    });
    console.log('==', u, JSON.stringify(r, null, 1));
  }
  await b.close(); })();
