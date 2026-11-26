// Download new Google reviews in Trustindex (scheduled task, every 10 days).
const login = require('../login');
const URL = 'https://marmarcandles.com/wp-admin/admin.php?page=wp-reviews-plugin-for-google/settings.php&tab=my-reviews';
async function names(p) {
  await p.goto(URL, { waitUntil: 'domcontentloaded', timeout: 120000 });
  return p.evaluate(() => [...document.querySelectorAll('table tr, .ti-review-item, .ti-widget-review')]
    .map(r => (r.querySelector('.ti-name, td:nth-child(2), strong') || {}).innerText || '').map(s => s.trim()).filter(Boolean));
}
(async () => { const { b, p } = await login();
  const before = await names(p);
  console.log('before', before.length);
  const btn = await p.$('a.btn-download-reviews');
  const state = btn ? await btn.evaluate(a => ({ text: a.innerText.trim(), disabled: a.classList.contains('disabled') || a.hasAttribute('disabled') })) : null;
  const pageTxt = await p.evaluate(() => (document.body.innerText.match(/pieejama pēc[^\n]*/i) || [''])[0]);
  console.log('button', JSON.stringify(state), pageTxt);
  if (btn && !state.disabled && !pageTxt) {
    await btn.click(); await p.waitForTimeout(25000);
    const after = await names(p);
    console.log('after', after.length, 'new:', JSON.stringify(after.filter(n => !before.includes(n)).slice(0, 20)));
    await require('../deploy').purge(p);
  }
  await p.goto('https://marmarcandles.com/lv/?nc=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 90000 });
  console.log('home', await p.evaluate(() => { const s = [...document.querySelectorAll('section,div')].find(e => /Ko saka klienti/i.test(e.innerText || '') && e.innerText.length < 4000); return s ? (s.innerText.match(/\d+\s*atsauksm\S*/i) || [''])[0] : 'block not found'; }));
  await b.close(); })();
