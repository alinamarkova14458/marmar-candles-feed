const { chromium } = require('playwright');const fs=require('fs');
(async()=>{const b=await chromium.launch();const p=await b.newPage({viewport:{width:1000,height:1500}});
for(const f of fs.readdirSync('frames').filter(x=>x.endsWith('.html'))){await p.goto('file://'+process.cwd()+'/frames/'+f);await p.waitForTimeout(300);await p.screenshot({path:'frames/'+f.replace('.html','.png')});}
await b.close();})();
