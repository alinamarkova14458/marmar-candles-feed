// Return the gingerbread-man candle (14592) to sale (Alina 24.11.2026): deploy qa-fixes without its redirect, publish.
const login = require('../login'); const deploy = require('../deploy');
(async () => { const { b, p, api } = await login();
  await deploy(p, api, 'marmar-qa-fixes', 'marmar-qa-fixes.php');
  const g = await api('wc/v3/products/14592?_fields=id,name,status,stock_status,catalog_visibility,categories,price');
  console.log('before', JSON.stringify(g.data));
  const u = await api('wc/v3/products/14592', { method: 'PUT', data: { status: 'publish', catalog_visibility: 'visible', stock_status: 'instock' } });
  console.log('after', u.status, u.data.status, u.data.stock_status, u.data.price, u.data.permalink);
  await require('../deploy').purge(p);
  const r = await p.goto(u.data.permalink.replace('marmarcandles.com/product', 'marmarcandles.com/lv/product') + '?nc=' + Date.now(), { waitUntil: 'domcontentloaded' });
  console.log('page', r.status(), p.url(), await p.title());
  await b.close(); })();
