const { chromium, devices } = require('playwright');
(async () => { const b = await chromium.launch(); const c = await b.newContext({ ...devices['Pixel 7'] }); const q = await c.newPage();
  await q.goto('https://marmarcandles.com/lv/product/personalizetas-kazu-sveces-viesiem-izveidojiet-savas/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 }); await q.waitForTimeout(2500);
  const r = await q.evaluate(() => {
    const info = s => { const e = document.querySelector(s); if (!e) return s + ' none'; const cs = getComputedStyle(e); const b = e.getBoundingClientRect(); return s + ' x=' + Math.round(b.left) + ' w=' + Math.round(b.width) + ' sw=' + e.scrollWidth + ' disp=' + cs.display + ' pos=' + cs.position + ' minw=' + cs.minWidth + ' maxw=' + cs.maxWidth + ' ovx=' + cs.overflowX + ' gtc=' + cs.gridTemplateColumns; };
    const lis = [...document.querySelectorAll('.flexy-pills li')].map(li => Math.round(li.getBoundingClientRect().left) + '/' + Math.round(li.getBoundingClientRect().width));
    return [info('.woocommerce-product-gallery'), info('.ct-product-gallery-container'), info('.flexy-container'), info('.flexy'), info('.flexy-pills'), info('.flexy-pills > ol'), 'lis ' + lis.join(' '), 'css: ' + [...document.querySelectorAll('link[rel=stylesheet]')].map(l => l.href).filter(h => /marmar-design/.test(h)).join(' '), 'vw ' + window.innerWidth + ' cw ' + document.documentElement.clientWidth];
  });
  console.log(r.join('\n'));
  await b.close(); })();
