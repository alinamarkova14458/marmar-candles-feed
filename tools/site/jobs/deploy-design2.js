// Deploy MarMar Design 1.15.6 and check body width on phone as visitor and as admin.
const login = require('../login'); const deploy = require('../deploy'); const { devices } = require('playwright');
(async () => { const { b, p, ctx, api } = await login();
  await deploy(p, api, 'marmar-design', 'marmar-design.php');
  const cookies = await ctx.cookies();
  for (const who of ['visitor', 'admin']) {
    const c2 = await b.newContext({ ...devices['Pixel 7'] }); if (who === 'admin') await c2.addCookies(cookies); const q = await c2.newPage();
    for (const u of ['/lv/product/personalizetas-kazu-sveces-viesiem-izveidojiet-savas/', '/lv/product/ziemassvetku-bumbas-formas-svece/', '/lv/product/personalizetas-svecites-berna-dzimsanas-dienai/']) {
      await q.goto('https://marmarcandles.com' + u + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 }); await q.waitForTimeout(2500);
      console.log(who, u, await q.evaluate(() => 'iw ' + innerWidth + ' body ' + document.body.scrollWidth + ' gallery x ' + Math.round(document.querySelector('.woocommerce-product-gallery').getBoundingClientRect().left) + ' thumbs scroll ' + ((document.querySelector('.flexy-pills > ol') || {}).scrollWidth || '-')));
    }
    await c2.close();
  }
  await b.close(); })();
