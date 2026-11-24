// New constructor occasion «Bērna dzimšanas diena — dāvaniņas viesiem» (Alina 24.11.2026).
// Deploy MarMar Wedding 1.6.0, duplicate 28329, set profile/texts/photos/category/menu/SEO, revert 28329 photos.
const login = require('../login'); const deploy = require('../deploy');
const SHORT = '<p>Mazas svecītes caurspīdīgās kastītēs ar lentīti un uzlīmi ar bērna vārdu un vecumu — dāvaniņa katram ballītes viesim. Uzlīmes dizainu veidojam pēc jūsu ballītes tēmas: Lego, vienradži, dinozauri, mašīnas vai jebkas cits. No 10 gab., cena no 3 € gab.</p>';
const DESC = `<h2>Dāvaniņas viesiem bērna dzimšanas dienā</h2>
<p>Bērnu ballītes beigās katrs mazais viesis grib aiznest mājās kaut ko savu. Personalizēta svecīte ar jubilāra vārdu ir skaists paldies viesiem un vecākiem — tā nav vēl viena plastmasas rotaļlieta, bet mīļa piemiņa no svētkiem.</p>
<h3>Kā tas izskatās</h3>
<ul><li>Svecīte no dabīga vaska (Mini Bubble vai burciņā 20 ml), bez aromāta.</li>
<li>Caurspīdīga kastīte ar lentīti — krāsu izvēlaties pēc ballītes tēmas.</li>
<li>Apaļa uzlīme ar bērna vārdu un vecumu, piemēram, «Kārlim 3», dizainā pēc jūsu tēmas.</li></ul>
<h3>Cena</h3>
<p>10–19 gab. — 5 € gab., 20–29 — 4,50 €, 30–49 — 4 €, 50–99 — 3,50 €, no 100 gab. — 3 €. Personalizācija un iepakojums ir iekļauti cenā.</p>
<h3>Kā pasūtīt</h3>
<ol><li>Izvēlieties svecīti, daudzumu un lentes krāsu, ierakstiet vārdu un vecumu, pievienojiet ielūgumu vai tēmas attēlu.</li>
<li>Pēc apmaksas 3 darba dienu laikā atsūtām uzlīmes maketu.</li>
<li>Pēc maketa apstiprināšanas izgatavojam svecītes apmēram 2 nedēļās un nosūtām uz pakomātu.</li></ol>
<p>Iesakām pasūtīt 3–4 nedēļas pirms ballītes. Svecītes ir dekoratīvas — dedziniet tās tikai pieaugušo uzraudzībā.</p>`;
(async () => { const { b, p, api } = await login();
  await deploy(p, api, 'marmar-wedding', 'marmar-wedding.php');
  // duplicate 28329 via admin action
  await p.goto('https://marmarcandles.com/wp-admin/post.php?post=28329&action=edit', { waitUntil: 'domcontentloaded', timeout: 120000 });
  const dupHref = await p.$$eval('a', as => (as.find(a => /action=duplicate_product/.test(a.href)) || {}).href);
  console.log('dup link', !!dupHref);
  await p.goto(dupHref, { waitUntil: 'domcontentloaded', timeout: 120000 });
  const newId = Number((p.url().match(/post=(\d+)/) || [])[1]);
  console.log('new id', newId);
  const up = await api('wc/v3/products/' + newId, { method: 'PUT', data: {
    name: 'Personalizētas svecītes bērna dzimšanas dienai — dāvaniņas viesiem',
    slug: 'personalizetas-svecites-berna-dzimsanas-dienai', status: 'publish',
    short_description: SHORT, description: DESC,
    images: [{ id: 28356 }, { id: 28357 }, { id: 25083 }, { id: 25084 }],
    meta_data: [{ key: '_marmar_wedding_profile', value: 'dzimsanas' }],
  } });
  console.log('product', up.status, up.data.id, up.data.status, up.data.permalink, (up.data.categories || []).map(c => c.id).join(','));
  const meta = await api('rankmath/v1/updateMeta', { method: 'POST', data: { objectType: 'post', objectID: newId, meta: {
    rank_math_focus_keyword: 'dāvaniņas viesiem bērnu ballītē,personalizētas svecītes,dzimšanas dienas dāvaniņas',
    rank_math_title: 'Dāvaniņas viesiem bērna dzimšanas dienā — personalizētas svecītes | MarMar Candles',
    rank_math_description: 'Personalizētas svecītes ar bērna vārdu un vecumu — dāvaniņa katram ballītes viesim. Dizains pēc ballītes tēmas, no 10 gab., no 3 € gab. Piegāde uz pakomātu visā Latvijā.' } } });
  console.log('seo', meta.status);
  // 28329 back to christening-neutral photos
  const k = await api('wc/v3/products/28329', { method: 'PUT', data: { images: [{ id: 25083 }, { id: 25082 }, { id: 25084 }] } });
  console.log('28329 images', k.status, (k.data.images || []).map(i => i.id).join(','));
  // menu: child under 28333
  const kids = await api('wp/v2/menu-items?menus=241&parent=28333&per_page=50&_fields=id,title,menu_order,object_id');
  console.log('menu kids', JSON.stringify((kids.data || []).map(x => [x.id, x.title && x.title.rendered, x.menu_order])));
  const c = await api('wp/v2/menu-items', { method: 'POST', data: { title: 'Bērna dzimšanas dienai', menus: 241, parent: 28333, type: 'post_type', object: 'product', object_id: newId, status: 'publish', menu_order: 13 } });
  console.log('menu add', c.status, c.data && c.data.id);
  // check page
  await p.goto(up.data.permalink + '?nc=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 90000 });
  console.log('page', await p.evaluate(() => ({ h1: (document.querySelector('h1') || {}).innerText, labels: [...document.querySelectorAll('.mmw label')].map(l => l.innerText.trim()).slice(0, 12) })));
  await require('../deploy').purge(p);
  await b.close(); })();
