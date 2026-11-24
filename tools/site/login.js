const { chromium } = require('playwright');
// Logs into wp-admin with WP_ADMIN_USER / WP_ADMIN_PASSWORD and returns a REST helper (cookie + nonce).
module.exports = async function login() {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 1400, height: 1000 } });
  const p = await ctx.newPage();
  await p.goto('https://marmarcandles.com/wp-login.php', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await p.fill('#user_login', process.env.WP_ADMIN_USER);
  await p.fill('#user_pass', process.env.WP_ADMIN_PASSWORD);
  await Promise.all([p.waitForNavigation({ timeout: 60000 }).catch(()=>{}), p.click('#wp-submit')]);
  await p.waitForLoadState('domcontentloaded');
  await p.goto('https://marmarcandles.com/wp-admin/edit.php?post_type=product', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await p.waitForFunction(() => window.wpApiSettings && wpApiSettings.nonce, null, { timeout: 60000 }).catch(() => {});
  const nonce = await p.evaluate(() => (window.wpApiSettings && wpApiSettings.nonce) || '');
  const api = async (path, opts={}) => p.evaluate(async ([path, opts, nonce]) => {
    const o = { method: opts.method || 'GET', headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' } };
    if (opts.data) o.body = JSON.stringify(opts.data);
    const r = await fetch('/wp-json/' + path, o); const t = await r.text();
    try { return { status: r.status, data: JSON.parse(t) }; } catch { return { status: r.status, data: t.slice(0,300) }; }
  }, [path, opts, nonce]);
  return { b, ctx, p, api };
};
