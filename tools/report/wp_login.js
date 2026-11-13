const { chromium } = require('playwright');
module.exports = async function login() {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 1400, height: 1000 } });
  const p = await ctx.newPage();
  await p.goto('https://marmarcandles.com/wp-login.php', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await p.fill('#user_login', process.env.WP_ADMIN_USER);
  await p.fill('#user_pass', process.env.WP_ADMIN_PASSWORD);
  await Promise.all([p.waitForNavigation({ timeout: 60000 }).catch(()=>{}), p.click('#wp-submit')]);
  await p.waitForLoadState('domcontentloaded');
  const nonce = await p.evaluate(() => (window.wpApiSettings && wpApiSettings.nonce) || null)
    || await (await p.request.get('https://marmarcandles.com/wp-admin/admin-ajax.php?action=rest-nonce')).text();
  const api = async (path, opts={}) => {
    const r = await p.request.fetch('https://marmarcandles.com/wp-json/' + path, { headers: { 'X-WP-Nonce': nonce }, ...opts });
    const t = await r.text(); try { return { status: r.status(), data: JSON.parse(t) }; } catch { return { status: r.status(), data: t.slice(0,300) }; }
  };
  return { b, ctx, p, api };
};
