// Deploy MarMar Checkout 1.4.9 (no condition chips in cart, corrected terms list) + find what makes the wedding page wider than the phone.
const login = require('../login'); const deploy = require('../deploy');
const { chromium, devices } = require('playwright');
(async () => { const { b, p, api } = await login();
  await deploy(p, api, 'marmar-checkout', 'marmar-checkout.php');
  await b.close();
  const b2 = await chromium.launch(); const c = await b2.newContext({ ...devices['Pixel 7'] }); const q = await c.newPage();
  for (const u of ['/lv/product/personalizetas-kazu-sveces-viesiem-izveidojiet-savas/', '/lv/cart/']) {
    await q.goto('https://marmarcandles.com' + u + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 }); await q.waitForTimeout(3000);
    const r = await q.evaluate(() => {
      const W = 412; const out = [];
      const path = el => { const a = []; while (el && el !== document.body && a.length < 7) { a.push(el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (typeof el.className === 'string' && el.className ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '')); el = el.parentElement; } return a.join(' < '); };
      for (const el of document.querySelectorAll('body *')) { const b = el.getBoundingClientRect(); const cs = getComputedStyle(el); if (b.width > 0 && b.right > W + 2 && cs.position !== 'fixed' && !el.closest('.flexy-items,.woocommerce-product-gallery__wrapper,.mmd-topbar,.ct-drawer-canvas,.cookie-notification')) out.push(path(el) + ' right=' + Math.round(b.right)); }
      return { sw: document.documentElement.scrollWidth, chips: !!document.querySelector('.mmco-chips'), over: out.slice(0, 8) };
    });
    console.log('==', u, JSON.stringify(r, null, 1));
  }
  await b2.close(); })();
