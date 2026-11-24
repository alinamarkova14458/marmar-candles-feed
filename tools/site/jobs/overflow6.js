const { chromium, devices } = require('playwright');
(async () => { const b = await chromium.launch(); const c = await b.newContext({ ...devices['Pixel 7'] }); const q = await c.newPage();
  await q.goto('https://marmarcandles.com/lv/product/personalizetas-kazu-sveces-viesiem-izveidojiet-savas/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 }); await q.waitForTimeout(2500);
  const r = await q.evaluate(() => {
    const lab = el => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (typeof el.className === 'string' && el.className ? '.' + el.className.trim().split(/\s+/).slice(0, 3).join('.') : '');
    const g = document.querySelector('.woocommerce-product-gallery'); const out = ['gallery html start: ' + g.outerHTML.slice(0, 300).replace(/\s+/g, ' ')];
    const all = [...g.querySelectorAll('*')];
    for (const el of all) { const prev = el.style.display; el.style.display = 'none'; const w = document.documentElement.scrollWidth; el.style.display = prev; if (w <= 414) out.push('hiding fixes: ' + lab(el) + ' < ' + lab(el.parentElement) + ' :: ' + (el.outerHTML || '').slice(0, 200).replace(/\s+/g, ' ')); if (out.length > 8) break; }
    return out;
  });
  console.log(r.join('\n'));
  await b.close(); })();
