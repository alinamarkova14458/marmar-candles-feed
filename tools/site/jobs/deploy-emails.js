// Deploy MarMar Emails 1.2.2 (dark-mode logo + Paysera exclusion), switch logo, send 3 test e-mails to the shop address.
const login = require('../login'); const deploy = require('../deploy');
(async () => { const { b, p, api } = await login();
  await deploy(p, api, 'marmar-emails', 'marmar-emails.php');
  await p.goto('https://marmarcandles.com/wp-admin/admin.php?page=wc-settings&tab=email', { waitUntil: 'domcontentloaded', timeout: 90000 });
  console.log('header image now:', await p.$eval('#woocommerce_email_header_image', e => e.value).catch(() => '?'));
  await p.goto('https://marmarcandles.com/wp-admin/admin.php?page=marmar-emails', { waitUntil: 'domcontentloaded', timeout: 90000 });
  const btn = await p.$('form input[name=action][value=marmar_emails_test_all]');
  if (btn) { const f = await btn.evaluateHandle(e => e.form); await Promise.all([p.waitForNavigation({ timeout: 120000 }).catch(() => {}), f.evaluate(form => form.submit())]); console.log('test mails sent; notice:', (await p.$$eval('.notice', n => n.map(x => x.innerText.trim()).join(' | '))).slice(0, 300)); }
  else console.log('test button not found');
  await b.close(); })();
