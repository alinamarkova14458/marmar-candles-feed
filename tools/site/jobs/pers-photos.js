// Alina's photos (24.11.2026) for the christening/children constructor 28329: main image + gallery.
const login = require('../login'); const fs = require('fs'); const path = require('path');
const FILES = [
  ['personalizeta-svecite-berna-dzimsanas-dienai-karlim-3.webp', 'Personalizēta svecīte bērna svētkiem caurspīdīgā kastītē ar uzlīmi «Kārlim 3»'],
  ['personalizetas-svecites-viesiem-berna-ballitei.webp', 'Personalizētas svecītes viesiem bērna svētkos — kastītes ar lentīti un vārdu'],
];
(async () => { const { b, p, api } = await login();
  const ids = [];
  for (const [f, alt] of FILES) {
    const b64 = fs.readFileSync(path.join(__dirname, '../upload', f)).toString('base64');
    const up = await p.evaluate(async ([b64, f, alt]) => {
      const bin = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
      const fd = new FormData(); fd.append('file', new Blob([bin], { type: 'image/webp' }), f); fd.append('alt_text', alt); fd.append('title', alt);
      const r = await fetch('/wp-json/wp/v2/media', { method: 'POST', headers: { 'X-WP-Nonce': wpApiSettings.nonce }, body: fd });
      const j = await r.json(); return { status: r.status, id: j.id, url: j.source_url };
    }, [b64, f, alt]);
    console.log('upload', f, JSON.stringify(up)); ids.push(up.id);
  }
  const cur = await api('wc/v3/products/28329?_fields=images');
  const old = (cur.data.images || []).map(i => i.id);
  console.log('old images', old.join(','));
  const images = [...ids, ...old.filter(x => !ids.includes(x))].map(id => ({ id }));
  const u = await api('wc/v3/products/28329', { method: 'PUT', data: { images } });
  console.log('28329 images', u.status, (u.data.images || []).map(i => i.id).join(','));
  await require('../deploy').purge(p);
  await b.close(); })();
