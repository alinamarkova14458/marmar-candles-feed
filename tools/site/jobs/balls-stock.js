// Ziemassvētku bumbas (24565): ready stock per colour via MarMar Pieejams uzreiz form (24.11.2026).
const login = require('../login');
const WANT = { 'bordo': 3, 'tumši zaļš': 3, 'pienbalts': 1, 'koši sarkans': 1 };
(async () => { const { b, p, api } = await login();
  const v = await api('wc/v3/products/24565/variations?per_page=50&_fields=id,attributes,stock_quantity,manage_stock,status,price');
  const rows = (v.data || []).map(x => ({ id: x.id, attrs: x.attributes.map(a => a.name + '=' + a.option).join(', '), opts: x.attributes.map(a => a.option.toLowerCase()), stock: x.manage_stock ? x.stock_quantity : '-', price: x.price, status: x.status }));
  rows.forEach(r => console.log('var', r.id, r.attrs, 'stock', r.stock, r.price, r.status));
  const plan = [];
  for (const [col, qty] of Object.entries(WANT)) {
    const m = rows.filter(r => r.status === 'publish' && r.opts.some(o => o === col || o.startsWith(col)));
    if (m.length !== 1) { console.log('SKIP', col, 'matches', m.map(r => r.id).join(',')); continue; }
    plan.push([m[0].id, qty, col]);
  }
  await p.goto('https://marmarcandles.com/wp-admin/admin.php?page=marmar-ready', { waitUntil: 'domcontentloaded', timeout: 90000 });
  let nonce = await p.$eval('#mmr-form input[name=_wpnonce]', e => e.value).catch(() => '');
  if (!nonce) { const href = await p.$$eval('#adminmenu a', a => (a.find(x => /Pieejams uzreiz/.test(x.textContent)) || {}).href); if (href) { await p.goto(href); nonce = await p.$eval('#mmr-form input[name=_wpnonce]', e => e.value).catch(() => ''); } }
  console.log('nonce found', !!nonce);
  for (const [id, qty, col] of plan) {
    const st = await p.evaluate(async ([id, qty, nonce]) => { const fd = new URLSearchParams({ action: 'mm_ready_save', do: 'set', _wpnonce: nonce, item: String(id), qty: String(qty) }); const r = await fetch('/wp-admin/admin-post.php', { method: 'POST', body: fd, credentials: 'include' }); return r.status + ' ' + r.url.split('&m=')[1]; }, [id, qty, nonce]);
    console.log('set', col, id, qty, st);
  }
  const after = await api('wc/v3/products/24565/variations?per_page=50&_fields=id,stock_quantity,manage_stock');
  console.log('after', JSON.stringify(after.data));
  const prod = await api('wc/v3/products/24565?_fields=categories');
  console.log('categories', (prod.data.categories || []).map(c => c.slug).join(','));
  await require('../deploy').purge(p);
  await b.close(); })();
