// E-mail logo for dark mode: render the unchanged logo on a cream rounded plate (PNG),
// upload it to the media library and set it as the WooCommerce e-mail header image.
const login = require('../login');
const OLD = 'https://marmarcandles.com/wp-content/uploads/2026/04/marmar-candles-logo1-1.png';
(async () => { const { b, ctx, p, api } = await login();
  const cur = await api('wc/v3/settings/email/woocommerce_email_header_image');
  console.log('current header image', cur.status, cur.data && cur.data.value);
  // render
  const r = await ctx.newPage();
  await r.setViewportSize({ width: 900, height: 400 });
  await r.setContent(`<html><body style="margin:0;background:transparent">
    <div id="plate" style="display:inline-block;background:#FAF3F0;border-radius:36px;padding:34px 56px">
      <img id="logo" src="${OLD}" style="display:block;width:520px;height:auto"></div></body></html>`);
  await r.waitForFunction(() => { const i = document.getElementById('logo'); return i.complete && i.naturalWidth > 0; }, null, { timeout: 60000 });
  const png = await (await r.$('#plate')).screenshot({ omitBackground: true });
  console.log('rendered bytes', png.length);
  // upload
  const up = await p.evaluate(async ([b64]) => {
    const bin = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
    const fd = new FormData(); fd.append('file', new Blob([bin], { type: 'image/png' }), 'marmar-candles-logo-e-pasts.png');
    fd.append('alt_text', 'MarMar Candles');
    const res = await fetch('/wp-json/wp/v2/media', { method: 'POST', headers: { 'X-WP-Nonce': wpApiSettings.nonce }, body: fd });
    const j = await res.json(); return { status: res.status, id: j.id, url: j.source_url };
  }, [png.toString('base64')]);
  console.log('upload', JSON.stringify(up));
  if (up.url) {
    const s = await api('wc/v3/settings/email/woocommerce_email_header_image', { method: 'PUT', data: { value: up.url } });
    console.log('set header image', s.status, s.data && s.data.value);
  }
  await b.close(); })();
