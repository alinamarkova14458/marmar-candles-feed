const { chromium } = require('playwright');const fs=require('fs');
(async()=>{const b=await chromium.launch();const p=await b.newPage({viewport:{width:1000,height:1500}});
for(const f of fs.readdirSync('.').filter(x=>x.endsWith('.html'))){await p.goto('file://'+process.cwd()+'/'+f);await p.waitForTimeout(500);await p.screenshot({path:'out/'+f.replace('.html','.jpg'),type:'jpeg',quality:88});}
await b.close();})();
