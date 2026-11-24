// Hide candidates one by one and see which one makes the page wider than the phone.
const { chromium, devices } = require('playwright');
(async () => { const b = await chromium.launch(); const c = await b.newContext({ ...devices['Pixel 7'] }); const q = await c.newPage();
  const sels = ['.mmd-topbar', '#fab-stt', '.cookie-notification', '.mmw-reviews', '.mmw-cross', '.woocommerce-tabs', '.summary', '.woocommerce-product-gallery', '.mmw', '.mmd-sticky', 'header#header', 'footer', '.mmd-ig-foot', '.ct-breadcrumbs'];
  await q.goto('https://marmarcandles.com/lv/product/personalizetas-kazu-sveces-viesiem-izveidojiet-savas/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 }); await q.waitForTimeout(2500);
  for (const s of sels) {
    const r = await q.evaluate(s => { const els = [...document.querySelectorAll(s)]; const before = document.documentElement.scrollWidth; els.forEach(e => e.dataset.d = e.style.display, els.forEach(e => e.style.display = 'none')); const after = document.documentElement.scrollWidth; els.forEach(e => e.style.display = e.dataset.d || ''); return els.length + ' els ' + before + '→' + after; }, s);
    console.log(s, r);
  }
  await b.close(); })();
