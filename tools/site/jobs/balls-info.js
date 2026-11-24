const login = require('../login');
(async () => { const { b, api } = await login();
  const pr = await api('wc/v3/products/24565?_fields=id,name,type,attributes,default_attributes,price,stock_status,manage_stock,backorders');
  console.log(JSON.stringify(pr.data, null, 1));
  const v = await api('wc/v3/products/24565/variations/24572');
  const d = v.data; console.log('variation', JSON.stringify({ id: d.id, attributes: d.attributes, price: d.price, regular_price: d.regular_price, sku: d.sku, image: d.image && d.image.id, manage_stock: d.manage_stock, backorders: d.backorders, stock_status: d.stock_status, weight: d.weight, shipping_class: d.shipping_class, description: d.description }, null, 1));
  await b.close(); })();
