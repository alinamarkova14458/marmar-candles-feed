// New products: topper candles with digits 6 and 7, copies of 19481 («3») with Alina's photos (24.11.2026).
const login = require('../login'); const fs = require('fs'); const path = require('path');
const NEW = [
  { d: '6', file: 'kuku-toperis-svecite-ar-ciparu-6-lavandas-spidumi.webp', alt: 'Dabīgā vaska kūku toperis-svecīte ar ciparu 6 — lavandas krāsā ar spīdumiem' },
  { d: '7', file: 'kuku-toperis-svecite-ar-ciparu-7-gaisi-roza-spidumi.webp', alt: 'Dabīgā vaska kūku toperis-svecīte ar ciparu 7 — gaiši rozā ar spīdumiem' },
];
(async () => { const { b, p, api } = await login();
  const src = await api('wc/v3/products/19481?_fields=permalink,meta_data');
  const rm = (src.data.meta_data || []).filter(m => /^rank_math_(title|description|focus_keyword)$/.test(m.key)).map(m => m.key + '=' + m.value);
  console.log('source rank math:', rm.join(' | '));
  for (const n of NEW) {
    const b64 = fs.readFileSync(path.join(__dirname, '../upload', n.file)).toString('base64');
    const img = await p.evaluate(async ([b64, f, alt]) => {
      const bin = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
      const fd = new FormData(); fd.append('file', new Blob([bin], { type: 'image/webp' }), f); fd.append('alt_text', alt); fd.append('title', alt);
      const r = await fetch('/wp-json/wp/v2/media', { method: 'POST', headers: { 'X-WP-Nonce': wpApiSettings.nonce }, body: fd });
      const j = await r.json(); return { status: r.status, id: j.id };
    }, [b64, n.file, n.alt]);
    await p.goto('https://marmarcandles.com/wp-admin/post.php?post=19481&action=edit', { waitUntil: 'domcontentloaded', timeout: 120000 });
    const dup = await p.$$eval('a', as => (as.find(a => /action=duplicate_product/.test(a.href)) || {}).href);
    await p.goto(dup, { waitUntil: 'domcontentloaded', timeout: 120000 });
    const id = Number((p.url().match(/post=(\d+)/) || [])[1]);
    const name = 'Dabīgā vaska kūku toperis-svecīte ar ciparu "' + n.d + '"';
    const u = await api('wc/v3/products/' + id, { method: 'PUT', data: { name, slug: 'dabiga-vaska-kuku-toperis-svecite-ar-ciparu-' + n.d, status: 'publish', sku: '', images: [{ id: img.id }] } });
    console.log('digit', n.d, 'img', img.id, 'product', id, u.status, u.data.status, u.data.permalink, 'price', u.data.price, 'vars', (u.data.variations || []).length);
    await api('rankmath/v1/updateMeta', { method: 'POST', data: { objectType: 'post', objectID: id, meta: {
      rank_math_title: 'Kūku toperis-svecīte ar ciparu ' + n.d + ' — dabīgais vasks | MarMar Candles',
      rank_math_description: 'Roku darināta dabīgā vaska svecīte-toperis ar ciparu ' + n.d + ' dzimšanas dienas tortei. Dažādas krāsas un dizaini, piegāde uz pakomātu visā Latvijā.',
      rank_math_focus_keyword: 'svece cipars ' + n.d + ',ciparu sveces,kūku toperi' } } }).then(r => console.log(' seo', r.status));
  }
  await require('../deploy').purge(p);
  await b.close(); })();
