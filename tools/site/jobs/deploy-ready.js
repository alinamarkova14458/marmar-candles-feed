// Deploy marmar-ready 1.3.12: personalised products show «Izgatavošana līdz 2 ned.» on cards.
const login = require('../login'); const deploy = require('../deploy');
(async () => { const { b, p, api } = await login();
  await deploy(p, api, 'marmar-ready', 'marmar-ready.php');
  await p.goto('https://marmarcandles.com/lv/product-category/personalizetas-sveces/?nc=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 90000 });
  console.log(await p.$$eval('li.product', ls => ls.map(l => (l.querySelector('.woocommerce-loop-product__title, h2') || {}).innerText + ' → ' + ((l.querySelector('.mm-eta-line') || {}).innerText || '-')).join('\n')));
  await p.goto('https://marmarcandles.com/lv/product-category/tortes-sveces/?nc=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 90000 });
  console.log('tortes first:', await p.$eval('.mm-eta-line', e => e.innerText).catch(() => '?'));
  await b.close(); })();
