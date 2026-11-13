// WooCommerce за последние 7 дней: заказы по источнику (order attribution) и проданные товары. Только чтение.
// Запуск: node tools/report/wc_week.js   (нужны WP_ADMIN_USER / WP_ADMIN_PASSWORD в окружении)
const login = require('./wp_login');
(async () => {
  const { b, api } = await login();
  const since = new Date(Date.now() - 7 * 864e5).toISOString().slice(0, 19);
  const r = await api(`wc/v3/orders?after=${since}&per_page=100&status=processing,completed,on-hold`);
  const orders = Array.isArray(r.data) ? r.data : [];
  const src = {}, items = {};
  let sum = 0;
  for (const o of orders) {
    const m = Object.fromEntries((o.meta_data || []).filter(x => /_wc_order_attribution_/.test(x.key)).map(x => [x.key.replace('_wc_order_attribution_', ''), String(x.value)]));
    const k = (m.source_type || '?') + ' / ' + (m.utm_source || '?') + (m.utm_medium ? ' / ' + m.utm_medium : '');
    src[k] = src[k] || { n: 0, sum: 0 }; src[k].n++; src[k].sum += +o.total; sum += +o.total;
    for (const li of o.line_items) { items[li.name] = (items[li.name] || 0) + li.quantity; }
  }
  console.log(`Заказов: ${orders.length}, сумма ${sum.toFixed(2)} €`);
  Object.entries(src).sort((a, b) => b[1].n - a[1].n).forEach(([k, v]) => console.log(`  ${k}: ${v.n} заказ(ов), ${v.sum.toFixed(2)} €`));
  console.log('Товары:');
  Object.entries(items).sort((a, b) => b[1] - a[1]).slice(0, 12).forEach(([k, v]) => console.log(`  ${v} × ${k}`));
  await b.close();
})();
