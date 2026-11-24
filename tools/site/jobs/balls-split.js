// Split ball variation 24572 ("any colour") into one variation per colour so each colour can have its own ready stock,
// then mark ready stock (Alina 24.11.2026): Bordo 3, Tumši zaļš 3, Pienbalts 1, Koši sarkans 1. Still orderable when sold out (backorders).
const login = require('../login');
const P = 24565, BASE = 24572;
const STOCK = { 'Bordo': 3, 'Tumši zaļš': 3, 'Pienbalts': 1, 'Koši sarkans': 1 };
(async () => { const { b, p, api } = await login();
  const attr = (o) => [{ id: 22, option: o }];
  const ids = {};
  const u = await api(`wc/v3/products/${P}/variations/${BASE}`, { method: 'PUT', data: { attributes: attr('Bordo') } });
  console.log('base->Bordo', u.status, JSON.stringify(u.data.attributes)); ids['Bordo'] = BASE;
  for (const col of ['Tumši zaļš', 'Koši sarkans', 'Pienbalts']) {
    const c = await api(`wc/v3/products/${P}/variations`, { method: 'POST', data: { regular_price: '9', image: { id: 24566 }, attributes: attr(col), status: 'publish', stock_status: 'instock' } });
    console.log('create', col, c.status, c.data.id, JSON.stringify(c.data.attributes)); ids[col] = c.data.id;
  }
  await p.goto('https://marmarcandles.com/wp-admin/admin.php?page=marmar-ready', { waitUntil: 'domcontentloaded', timeout: 90000 });
  const nonce = await p.$eval('#mmr-form input[name=_wpnonce]', e => e.value);
  for (const [col, qty] of Object.entries(STOCK)) {
    const st = await p.evaluate(async ([id, qty, nonce]) => { const fd = new URLSearchParams({ action: 'mm_ready_save', do: 'set', _wpnonce: nonce, item: String(id), qty: String(qty) }); const r = await fetch('/wp-admin/admin-post.php', { method: 'POST', body: fd, credentials: 'include' }); return r.status + ' ' + (r.url.split('&m=')[1] || ''); }, [ids[col], qty, nonce]);
    console.log('ready', col, ids[col], qty, st);
  }
  const after = await api(`wc/v3/products/${P}/variations?per_page=20&_fields=id,attributes,price,manage_stock,stock_quantity,backorders,stock_status`);
  after.data.forEach(x => console.log('after', x.id, x.attributes.map(a => a.option).join(','), x.price, x.manage_stock, x.stock_quantity, x.backorders, x.stock_status));
  const prod = await api(`wc/v3/products/${P}?_fields=categories`);
  console.log('categories', prod.data.categories.map(c => c.slug).join(','));
  await require('../deploy').purge(p);
  const s = await api('wc/store/v1/products/' + P);
  console.log('store variations', JSON.stringify((s.data.variations || []).map(v => v.attributes.map(a => a.value).join(','))));
  await b.close(); })();
