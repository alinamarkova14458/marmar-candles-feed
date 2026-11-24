// Who is behind a cart-reminder e-mail address: orders, customer, MarMar Emails cart rows.
const login = require('../login');
const Q = process.env.Q || 'mail3483494';
(async () => { const { b, p, api } = await login();
  const o = await api('wc/v3/orders?search=' + Q + '&per_page=20&_fields=id,status,date_created,total,billing,line_items');
  console.log('orders', o.status, (o.data || []).length);
  for (const x of (o.data || [])) console.log(' ', x.id, x.status, x.date_created, x.total, x.billing.first_name, x.billing.last_name, x.billing.phone, (x.line_items||[]).map(l => l.quantity + '× ' + l.name).join('; '));
  const c = await api('wc/v3/customers?search=' + Q + '&_fields=id,email,first_name,last_name,date_created,orders_count');
  console.log('customers', JSON.stringify(c.data));
  await p.goto('https://marmarcandles.com/wp-admin/admin.php?page=marmar-emails', { waitUntil: 'domcontentloaded', timeout: 90000 });
  const t = await p.evaluate(() => document.body.innerText);
  const lines = t.split('\n'); const i = lines.findIndex(l => l.includes(Q));
  console.log('admin page rows around address:\n' + (i >= 0 ? lines.slice(Math.max(0, i - 3), i + 6).join('\n') : '(not on page)'));
  await b.close(); })();
