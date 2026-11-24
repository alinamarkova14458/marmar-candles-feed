// 1) Mobile menu tile «Kāzām» → «Personalizētas» (category personalizetas-sveces), WPCode 25060.
// 2) Alina's corporate photo as main image of 28330.
const login = require('../login'); const fs = require('fs'); const path = require('path');
(async () => { const { b, p, api } = await login();
  await p.goto('https://marmarcandles.com/wp-admin/admin.php?page=wpcode-snippet-manager&snippet_id=25060', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await p.waitForTimeout(2500);
  const res = await p.evaluate(() => {
    const ta = document.querySelector('#wpcode_snippet_code'); let code = ta.value;
    const old = "array( 'Kāzām', '2026/04/ekskluzivas-kazu-sveces-davanai-300x300.jpg', 'product-category/kazu-sveces/' ),";
    if (!code.includes(old)) return 'old line not found';
    code = code.replace(old, "array( 'Personalizētas', '2026/04/ekskluzivas-kazu-sveces-davanai-300x300.jpg', 'product-category/personalizetas-sveces/' ),");
    const cmEl = document.querySelector('.CodeMirror');
    if (cmEl && cmEl.CodeMirror) cmEl.CodeMirror.setValue(code);
    ta.value = code; return 'replaced, cm=' + !!(cmEl && cmEl.CodeMirror);
  });
  console.log('tile', res);
  if (res.startsWith('replaced')) {
    const btn = await p.$('button[name=button][value=publish]') || await p.$('#wpcode_save_snippet');
    await Promise.all([p.waitForNavigation({ timeout: 120000 }).catch(() => {}), btn.click()]);
    await p.waitForTimeout(2000);
    const saved = await p.$eval('#wpcode_snippet_code', e => e.value.includes("'Personalizētas', '2026/04/ekskluzivas")).catch(() => false);
    const active = await p.$eval('#wpcode_active', e => e.checked).catch(() => '?');
    console.log('saved', saved, 'active', active, (await p.$$eval('.notice', n => n.map(x => x.innerText.trim()).join(' | '))).slice(0, 200));
  }
  // corporate photo
  const f = 'korporativas-davanas-svecites-ar-uzlimi-ziemassvetkos.webp', alt = 'Korporatīvās Ziemassvētku dāvanas — personalizētas svecītes kastītēs ar uzlīmi un lentīti';
  const b64 = fs.readFileSync(path.join(__dirname, '../upload', f)).toString('base64');
  const up = await p.evaluate(async ([b64, f, alt]) => {
    const bin = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
    const fd = new FormData(); fd.append('file', new Blob([bin], { type: 'image/webp' }), f); fd.append('alt_text', alt); fd.append('title', alt);
    const r = await fetch('/wp-json/wp/v2/media', { method: 'POST', headers: { 'X-WP-Nonce': wpApiSettings.nonce }, body: fd });
    const j = await r.json(); return { status: r.status, id: j.id };
  }, [b64, f, alt]);
  console.log('upload', JSON.stringify(up));
  const cur = await api('wc/v3/products/28330?_fields=images');
  const ids = [up.id, ...(cur.data.images || []).map(i => i.id).filter(x => x !== up.id)];
  const u = await api('wc/v3/products/28330', { method: 'PUT', data: { images: ids.map(id => ({ id })) } });
  console.log('28330 images', u.status, (u.data.images || []).map(i => i.id).join(','));
  await require('../deploy').purge(p);
  await p.setViewportSize({ width: 400, height: 850 });
  await p.goto('https://marmarcandles.com/lv/?nc=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 90000 });
  console.log('tiles on page', await p.$$eval('.mmn-tile span', s => s.map(x => x.innerText).join(' | ')).catch(() => '?'));
  await b.close(); })();
