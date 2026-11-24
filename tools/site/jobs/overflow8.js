// Logged-in admin on a phone: what makes the wedding page wider (Alina sees it zoomed/shifted).
const login = require('../login'); const { devices } = require('playwright');
(async () => { const { b, ctx } = await login();
  const cookies = await ctx.cookies();
  const c2 = await b.newContext({ ...devices['Pixel 7'] }); await c2.addCookies(cookies); const q = await c2.newPage();
  for (const u of ['/lv/product/personalizetas-kazu-sveces-viesiem-izveidojiet-savas/', '/lv/product/personalizetas-sveces-kristibam/']) {
    await q.goto('https://marmarcandles.com' + u + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 }); await q.waitForTimeout(3000);
    const r = await q.evaluate(() => {
      const lab = el => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (typeof el.className === 'string' && el.className ? '.' + el.className.trim().split(/\s+/).slice(0, 3).join('.') : '');
      const res = { iw: window.innerWidth, sw: document.documentElement.scrollWidth, bodySW: document.body.scrollWidth, admin: !!document.getElementById('wpadminbar') };
      const wide = []; for (const el of document.querySelectorAll('body *')) { const b = el.getBoundingClientRect(); if (b.width > 0 && (b.right > 414 || b.left < -2) && getComputedStyle(el).position !== 'fixed') { wide.push(lab(el) + ' L=' + Math.round(b.left) + ' R=' + Math.round(b.right)); } }
      res.wide = wide.slice(0, 15);
      return res;
    });
    console.log('==', u, JSON.stringify(r, null, 1));
  }
  await b.close(); })();
