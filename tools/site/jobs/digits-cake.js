// Second photo (candle burning on a cake) for digit toppers 3, 6, 7, 8, 9.
const login = require('../login'); const fs = require('fs'); const path = require('path');
const MAP = { '3': 19481, '6': 28377, '7': 28380, '8': 28386, '9': 28383 };
(async () => { const { b, p, api } = await login();
  for (const [d, pid] of Object.entries(MAP)) {
    const f = 'svece-cipars-' + d + '-uz-tortes-deg.webp', alt = 'Svecīte ar ciparu ' + d + ' deg uz dzimšanas dienas tortes';
    const b64 = fs.readFileSync(path.join(__dirname, '../upload', f)).toString('base64');
    const img = await p.evaluate(async ([b64, f, alt]) => { const bin = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
      const fd = new FormData(); fd.append('file', new Blob([bin], { type: 'image/webp' }), f); fd.append('alt_text', alt); fd.append('title', alt);
      const r = await fetch('/wp-json/wp/v2/media', { method: 'POST', headers: { 'X-WP-Nonce': wpApiSettings.nonce }, body: fd }); const j = await r.json(); return j.id; }, [b64, f, alt]);
    const cur = (await api('wc/v3/products/' + pid + '?_fields=images')).data.images.map(i => i.id);
    const ids = [cur[0], img, ...cur.slice(1)];
    const u = await api('wc/v3/products/' + pid, { method: 'PUT', data: { images: ids.map(id => ({ id })) } });
    console.log('digit', d, pid, u.status, (u.data.images || []).map(i => i.id).join(','));
  }
  await require('../deploy').purge(p);
  await b.close(); })();
