const { chromium, devices } = require('playwright');
(async () => { const b = await chromium.launch(); const c = await b.newContext({ ...devices['Pixel 7'] }); const q = await c.newPage();
  await q.goto('https://marmarcandles.com/lv/product/personalizetas-kazu-sveces-viesiem-izveidojiet-savas/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 }); await q.waitForTimeout(2500);
  const r = await q.evaluate(() => {
    const lab = el => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (typeof el.className === 'string' && el.className ? '.' + el.className.trim().split(/\s+/).slice(0, 3).join('.') : '');
    const out = []; const walk = (el, d) => { if (d > 4) return; for (const ch of el.children) { const bw = ch.getBoundingClientRect(); out.push('  '.repeat(d) + lab(ch) + ' w=' + Math.round(bw.width) + ' sw=' + ch.scrollWidth + ' disp=' + getComputedStyle(ch).display); if (ch.scrollWidth > 414) walk(ch, d + 1); } };
    walk(document.querySelector('#main'), 0);
    return out.filter(l => /sw=(4[2-9]\d|[5-9]\d\d|\d{4})/.test(l)).slice(0, 40);
  });
  console.log(r.join('\n'));
  await b.close(); })();
