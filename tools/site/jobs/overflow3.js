// Wedding page: open accordions and find content wider than the phone.
const { chromium, devices } = require('playwright');
(async () => { const b = await chromium.launch(); const c = await b.newContext({ ...devices['Pixel 7'] }); const q = await c.newPage();
  await q.goto('https://marmarcandles.com/lv/product/personalizetas-kazu-sveces-viesiem-izveidojiet-savas/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 }); await q.waitForTimeout(2500);
  const r = await q.evaluate(() => {
    document.querySelectorAll('details').forEach(d => d.open = true);
    const lab = el => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (typeof el.className === 'string' && el.className ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '');
    const out = [];
    for (const el of document.querySelectorAll('.woocommerce-tabs *, .mmw *, .summary *')) {
      const w = Math.max(el.scrollWidth, el.getBoundingClientRect().width);
      if (w > 414 && ![...el.children].some(ch => Math.max(ch.scrollWidth, ch.getBoundingClientRect().width) > 414)) out.push(lab(el) + ' < ' + lab(el.parentElement) + ' w=' + Math.round(w) + ' ws=' + getComputedStyle(el).whiteSpace + ' :: ' + (el.outerHTML || '').slice(0, 160).replace(/\s+/g, ' '));
    }
    return { sw: document.documentElement.scrollWidth, out: out.slice(0, 10) };
  });
  console.log(JSON.stringify(r, null, 1));
  await b.close(); })();
