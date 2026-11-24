// Helper for jobs: upload wp-plugins/<slug> as a zip (overwriting), activate it, purge LiteSpeed.
// Usage inside a job:  await require('../deploy')(p, api, 'marmar-emails', 'marmar-emails.php')
const { execSync } = require('child_process');
const path = require('path');
module.exports = async function deploy(p, api, slug, mainFile) {
  const root = path.resolve(__dirname, '../../wp-plugins');
  const zip = `/tmp/${slug}.zip`;
  execSync(`rm -f ${zip} && cd ${root} && zip -qr ${zip} ${slug}`);
  await p.goto('https://marmarcandles.com/wp-admin/plugin-install.php?tab=upload', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await p.setInputFiles('input[name=pluginzip]', zip);
  await Promise.all([p.waitForNavigation({ timeout: 120000 }), p.click('#install-plugin-submit')]);
  const replace = await p.$('a.update-from-upload-overwrite');
  if (replace) await Promise.all([p.waitForNavigation({ timeout: 120000 }), replace.click()]);
  const r = await api(`wp/v2/plugins/${slug}/${(mainFile || slug + '.php').replace(/\.php$/, '')}`, { method: 'POST', data: { status: 'active' } });
  console.log('deploy', slug, r.status, r.data && r.data.status, r.data && r.data.version);
  await purge(p);
};
async function purge(p) {
  await p.goto('https://marmarcandles.com/wp-admin/', { waitUntil: 'domcontentloaded', timeout: 90000 });
  const links = await p.$$eval('#wpadminbar a', a => a.map(x => x.href).filter(h => /type=purge_all(&|$)/.test(h)));
  if (links[0]) { await p.goto(links[0]); console.log('purged'); }
}
module.exports.purge = purge;
