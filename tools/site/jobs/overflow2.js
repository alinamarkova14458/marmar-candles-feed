// Deepest elements wider than the phone on the wedding constructor page.
const { chromium, devices } = require('playwright');
(async () => { const b = await chromium.launch(); const c = await b.newContext({ ...devices['Pixel 7'] }); const q = await c.newPage();
  await q.goto('https://marmarcandles.com/lv/product/personalizetas-kazu-sveces-viesiem-izveidojiet-savas/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 }); await q.waitForTimeout(3000);
  const r = await q.evaluate(() => {
    const W = 412; const wide = [];
    const lab = el => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (typeof el.className === 'string' && el.className ? '.' + el.className.trim().split(/\s+/).slice(0, 3).join('.') : '');
    for (const el of document.querySelectorAll('#main-container *')) {
      const b = el.getBoundingClientRect(); if (getComputedStyle(el).position === 'fixed') continue;
      if (b.width > W + 2 && ![...el.children].some(ch => ch.getBoundingClientRect().width > W + 2)) {
        let a = [], e = el; while (e && a.length < 6) { a.push(lab(e)); e = e.parentElement; }
        wide.push(a.join(' < ') + ' w=' + Math.round(b.width) + ' minw=' + getComputedStyle(el).minWidth + ' ws=' + getComputedStyle(el).whiteSpace + ' txt=' + (el.innerText || '').slice(0, 60).replace(/\n/g, ' '));
      }
    }
    return wide.slice(0, 10);
  });
  console.log(r.join('\n'));
  await b.close(); })();
