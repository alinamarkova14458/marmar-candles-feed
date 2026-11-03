const { chromium } = require('playwright');const fs=require('fs');
(async()=>{const b=await chromium.launch();const p=await b.newPage({viewport:{width:1000,height:1500}});
const only=process.argv[2]||'';
for(const f of fs.readdirSync('.').filter(x=>x.endsWith('.html')&&x.startsWith(only))){await p.goto('file://'+process.cwd()+'/'+f);await p.evaluate(()=>document.fonts.ready);await p.waitForTimeout(300);await p.screenshot({path:'out/'+f.replace('.html','.jpg'),type:'jpeg',quality:88});console.log(f);}
await b.close();})();
