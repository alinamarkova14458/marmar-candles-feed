// Deploy MarMar Design 1.15.2 (no «Bieži pērk kopā» on personalised constructors) and re-check width on phone.
const login = require('../login'); const deploy = require('../deploy');
const { chromium, devices } = require('playwright');
(async () => { const { b, p, api } = await login();
  await deploy(p, api, 'marmar-design', 'marmar-design.php');
  await b.close();
  const b2 = await chromium.launch(); const c = await b2.newContext({ ...devices['Pixel 7'] }); const q = await c.newPage();
  for (const u of ['/lv/product/personalizetas-kazu-sveces-viesiem-izveidojiet-savas/', '/lv/product/personalizetas-sveces-kristibam/', '/lv/product/korporativas-davanas-sveces-ar-logo/', '/lv/product/personalizetas-svecites-berna-dzimsanas-dienai/', '/lv/product/ziemassvetku-bumbas-formas-svece/', '/lv/cart/']) {
    await q.goto('https://marmarcandles.com' + u + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 }); await q.waitForTimeout(2500);
    console.log(u, 'scrollWidth', await q.evaluate(() => document.documentElement.scrollWidth), 'together', await q.$$eval('.mmd-together', e => e.length));
  }
  await b2.close(); })();
