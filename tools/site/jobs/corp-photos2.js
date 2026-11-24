// More corporate photos (client logo stickers, 24.11.2026); reorder 28330 gallery: logo photos first.
const login = require('../login'); const fs = require('fs'); const path = require('path');
const FILES = [
  ['korporativas-davanas-svecites-ar-logo-liels-pasutijums.webp', 'Korporatīvās dāvanas — daudz svecīšu kastītēs ar melnu lentīti un uzņēmuma logo uzlīmi'],
  ['svecite-ar-klinikas-logo-uzlimi-melna-lentite.webp', 'Svecīte caurspīdīgā kastītē ar melnu lentīti un uzņēmuma logo uzlīmi'],
];
(async () => { const { b, p, api } = await login();
  const ids = [];
  for (const [f, alt] of FILES) {
    const b64 = fs.readFileSync(path.join(__dirname, '../upload', f)).toString('base64');
    const up = await p.evaluate(async ([b64, f, alt]) => {
      const bin = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
      const fd = new FormData(); fd.append('file', new Blob([bin], { type: 'image/webp' }), f); fd.append('alt_text', alt); fd.append('title', alt);
      const r = await fetch('/wp-json/wp/v2/media', { method: 'POST', headers: { 'X-WP-Nonce': wpApiSettings.nonce }, body: fd });
      const j = await r.json(); return { status: r.status, id: j.id };
    }, [b64, f, alt]);
    console.log('upload', f, JSON.stringify(up)); ids.push(up.id);
  }
  const order = [ids[0], 28362, ids[1], 28363, 28361];
  const u = await api('wc/v3/products/28330', { method: 'PUT', data: { images: order.map(id => ({ id })) } });
  console.log('28330 images', u.status, (u.data.images || []).map(i => i.id).join(','));
  await require('../deploy').purge(p);
  await b.close(); })();
