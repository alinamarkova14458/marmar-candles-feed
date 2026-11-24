// Read the mobile menu tiles part of WPCode snippet 25060 «MarMar UX».
const login = require('../login');
(async () => { const { b, p } = await login();
  await p.goto('https://marmarcandles.com/wp-admin/admin.php?page=wpcode-snippet-manager&snippet_id=25060', { waitUntil: 'domcontentloaded', timeout: 120000 });
  const code = await p.$eval('#wpcode_snippet_code', e => e.value).catch(e => 'ERR ' + e.message);
  const i = code.indexOf('Kāzām');
  console.log('len', code.length, 'idx', i);
  console.log(code.slice(Math.max(0, i - 1500), i + 400));
  const forms = await p.$$eval('form', fs => fs.map(f => f.id + '|' + f.action + '|' + [...f.querySelectorAll('button,input[type=submit]')].map(x => x.name + '=' + (x.value || x.innerText)).join(',')));
  console.log(forms.join('\n'));
  await b.close(); })();
