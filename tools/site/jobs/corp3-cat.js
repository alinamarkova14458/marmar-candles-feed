// Corporate photo #4 (salon logo) + category 324 description with birthday occasion.
const login = require('../login'); const fs = require('fs'); const path = require('path');
(async () => { const { b, p, api } = await login();
  const f = 'svecite-ar-salona-logo-uzlimi-bordo-lentite.webp', alt = 'Svecīte kastītē ar bordo lentīti un salona logo uzlīmi — dāvana klientiem';
  const b64 = fs.readFileSync(path.join(__dirname, '../upload', f)).toString('base64');
  const up = await p.evaluate(async ([b64, f, alt]) => {
    const bin = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
    const fd = new FormData(); fd.append('file', new Blob([bin], { type: 'image/webp' }), f); fd.append('alt_text', alt); fd.append('title', alt);
    const r = await fetch('/wp-json/wp/v2/media', { method: 'POST', headers: { 'X-WP-Nonce': wpApiSettings.nonce }, body: fd });
    const j = await r.json(); return { status: r.status, id: j.id };
  }, [b64, f, alt]);
  console.log('upload', JSON.stringify(up));
  const order = [28364, 28362, 28365, up.id, 28363, 28361];
  const u = await api('wc/v3/products/28330', { method: 'PUT', data: { images: order.map(id => ({ id })) } });
  console.log('28330 images', u.status, (u.data.images || []).map(i => i.id).join(','));
  const c0 = await api('wc/v3/products/categories/324?_fields=description');
  console.log('old cat desc:', c0.data.description);
  const desc = 'Personalizētas sveces viesiem un dāvanām: kāzām, kristībām, bērna dzimšanas dienai un korporatīvām dāvanām — ar vārdiem, vecumu, datumu vai Jūsu logo. No 10 gab.';
  const c = await api('wc/v3/products/categories/324', { method: 'PUT', data: { description: desc } });
  console.log('cat', c.status, c.data.description);
  await require('../deploy').purge(p);
  await b.close(); })();
