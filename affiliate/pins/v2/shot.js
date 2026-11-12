// Render every *.html in this folder (or those starting with argv[2]) to out/<name>.jpg, 1000x1500.
const { chromium } = require('playwright');
const fs = require('fs'), path = require('path');
(async () => {
  const dir = __dirname, out = path.join(dir, 'out');
  fs.mkdirSync(out, { recursive: true });
  const only = process.argv[2] || '';
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1000, height: 1500 } });
  for (const f of fs.readdirSync(dir).filter(x => x.endsWith('.html') && x.startsWith(only))) {
    await p.goto('file://' + path.join(dir, f));
    await p.waitForFunction(() => document.body.dataset.ready === '1', null, { timeout: 15000 }).catch(() => {});
    await p.evaluate(() => Promise.all([...document.images].map(i => i.complete ? 0 : new Promise(r => { i.onload = i.onerror = r; }))));
    await p.waitForTimeout(150);
    await p.screenshot({ path: path.join(out, f.replace(/\.html$/, '.jpg')), type: 'jpeg', quality: 90 });
    console.log('out/' + f.replace(/\.html$/, '.jpg'));
  }
  await b.close();
})();
