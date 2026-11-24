// Digits 9 and 8 as new products (copies of 19481); new photos for existing «3» (19481) and «2» (19361) as main image.
const login = require('../login'); const fs = require('fs'); const path = require('path');
const up = async (p, f, alt) => { const b64 = fs.readFileSync(path.join(__dirname, '../upload', f)).toString('base64');
  return p.evaluate(async ([b64, f, alt]) => { const bin = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
    const fd = new FormData(); fd.append('file', new Blob([bin], { type: 'image/webp' }), f); fd.append('alt_text', alt); fd.append('title', alt);
    const r = await fetch('/wp-json/wp/v2/media', { method: 'POST', headers: { 'X-WP-Nonce': wpApiSettings.nonce }, body: fd }); const j = await r.json(); return j.id; }, [b64, f, alt]); };
(async () => { const { b, p, api } = await login();
  for (const n of [{ d: '9', f: 'kuku-toperis-svecite-ar-ciparu-9-pistaciju-zals-spidumi.webp', c: 'pistāciju zaļā krāsā' }, { d: '8', f: 'kuku-toperis-svecite-ar-ciparu-8-debeszils-spidumi.webp', c: 'debeszilā krāsā' }]) {
    const img = await up(p, n.f, 'Dabīgā vaska kūku toperis-svecīte ar ciparu ' + n.d + ' — ' + n.c + ' ar spīdumiem');
    await p.goto('https://marmarcandles.com/wp-admin/post.php?post=19481&action=edit', { waitUntil: 'domcontentloaded', timeout: 120000 });
    const dup = await p.$$eval('a', as => (as.find(a => /action=duplicate_product/.test(a.href)) || {}).href);
    await p.goto(dup, { waitUntil: 'domcontentloaded', timeout: 120000 });
    const id = Number((p.url().match(/post=(\d+)/) || [])[1]);
    const u = await api('wc/v3/products/' + id, { method: 'PUT', data: { name: 'Dabīgā vaska kūku toperis-svecīte ar ciparu "' + n.d + '"', slug: 'dabiga-vaska-kuku-toperis-svecite-ar-ciparu-' + n.d, status: 'publish', sku: '', images: [{ id: img }] } });
    console.log('new digit', n.d, 'product', id, u.status, u.data.status, u.data.permalink, u.data.price);
    await api('rankmath/v1/updateMeta', { method: 'POST', data: { objectType: 'post', objectID: id, meta: {
      rank_math_title: 'Kūku toperis-svecīte ar ciparu ' + n.d + ' — dabīgais vasks | MarMar Candles',
      rank_math_description: 'Roku darināta dabīgā vaska svecīte-toperis ar ciparu ' + n.d + ' dzimšanas dienas tortei. Dažādas krāsas un dizaini, piegāde uz pakomātu visā Latvijā.',
      rank_math_focus_keyword: 'svece cipars ' + n.d + ',ciparu sveces,kūku toperi' } } });
  }
  for (const n of [{ id: 19481, d: '3', f: 'kuku-toperis-svecite-ar-ciparu-3-dzeltens-spidumi.webp', c: 'dzeltenā krāsā' }, { id: 19361, d: '2', f: 'kuku-toperis-svecite-ar-ciparu-2-roza-spidumi.webp', c: 'rozā krāsā' }]) {
    const img = await up(p, n.f, 'Dabīgā vaska kūku toperis-svecīte ar ciparu ' + n.d + ' — ' + n.c + ' ar spīdumiem');
    const cur = await api('wc/v3/products/' + n.id + '?_fields=images,name');
    const ids = [img, ...(cur.data.images || []).map(i => i.id)];
    const u = await api('wc/v3/products/' + n.id, { method: 'PUT', data: { images: ids.map(id => ({ id })) } });
    console.log('photo for', n.id, cur.data.name, u.status, (u.data.images || []).map(i => i.id).join(','));
  }
  await require('../deploy').purge(p);
  await b.close(); })();
