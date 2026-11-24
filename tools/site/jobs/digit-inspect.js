// Inspect the reference topper-candle product (digit 3) before making digits 6 and 7.
const login = require('../login');
(async () => { const { b, api } = await login();
  const r = await api('wc/v3/products?slug=dabiga-vaska-kuku-toperis-svecite-ar-ciparu-3-2&status=any');
  const p = (r.data || [])[0]; if (!p) { console.log('not found', r.status); await b.close(); return; }
  console.log(JSON.stringify({ id: p.id, name: p.name, type: p.type, status: p.status, price: p.regular_price, sku: p.sku, stock: [p.manage_stock, p.stock_quantity, p.stock_status, p.backorders], cats: p.categories.map(c => c.id + ':' + c.slug), tags: p.tags.map(t => t.name), attrs: p.attributes, images: p.images.map(i => i.id + ' ' + i.alt), short: p.short_description, cross: p.cross_sell_ids, upsell: p.upsell_ids, weight: p.weight, ship: p.shipping_class }, null, 1));
  console.log('DESC:\n' + p.description);
  const m = await api('rankmath/v1/getHead?url=' + encodeURIComponent(p.permalink)).catch(() => null);
  const s = await api('wc/v3/products?search=ciparu&per_page=50&_fields=id,name,slug,status');
  console.log('similar:\n' + (s.data || []).map(x => x.id + ' ' + x.status + ' ' + x.slug + ' | ' + x.name).join('\n'));
  await b.close(); })();
