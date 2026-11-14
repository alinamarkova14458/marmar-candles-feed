const { chromium } = require('playwright'); const fs=require('fs'), path=require('path');
const S=process.argv[2], spec=JSON.parse(fs.readFileSync(process.argv[3])), OUT=process.argv[4];
const F='/tmp/claude-0/-home-user/3e2e06cc-da17-5318-82d1-83c362dc3422/scratchpad/fonts';
const css=['inter/package/400.css','inter/package/600.css','instrument-serif/package/400.css'].map(f=>`<link rel="stylesheet" href="file://${path.join(F,f)}">`).join('');
const C='#8E1B2A',E="Inter,'Noto Color Emoji'";
let t=0.6, ev=[], body='';
if(spec.kind==='chat'){
 spec.msgs.forEach(([s,n,lv,ru],i)=>{
  let typ='';
  if(s==='l'){ typ=`<div class="typ" style="animation:show .01s ${t}s both, hide .01s ${t+0.75}s both"><span></span><span></span><span></span></div>`; t+=0.75; }
  else t+=0.55;
  body+=`<div class="w ${s}">${typ}<div class="msg ${s}" style="animation:pop .35s ${t}s both">${n?`<div style="font:600 24px Inter;color:${n.c||C}">${n.t}</div>`:''}<div style="font:400 36px/1.3 ${E}">${lv}</div>${ru?`<div style="font:400 25px Inter;color:#8a7d80;margin-top:4px">${ru}</div>`:''}</div></div>`;
  ev.push([t,s]); t+=0.75;
 });
}else{
 body+=`<div class=grid>`+spec.items.map(([e,l,r],i)=>{const h=`<div class=card style="animation:pop .4s ${t}s both"><div style="font-size:100px">${e}</div><div style="font:600 24px Inter;color:${C}">${i+1}</div><div style="font:600 33px/1.22 ${E};margin-top:6px">${l}</div><div style="color:#7d6e71;font-size:23px;margin-top:8px">${r}</div></div>`;ev.push([t,'l']);t+=1.2;return h}).join('')+`</div>`;
}
const tEnd=t+0.4;
const plq=`<div class=plq style="animation:pop .5s ${tEnd}s both"><div style="font:400 58px/1.08 'Instrument Serif','Noto Color Emoji'">${spec.foot}</div><div style="color:#7d6e71;font:400 28px Inter;margin-top:10px">${spec.footru}</div></div>`;
const DUR=tEnd+3.3;
const top= spec.kind==='chat' ? `<div class=hdr><div style="font-size:44px;color:${C}">‹</div><div style="width:84px;height:84px;border-radius:50%;background:#F2B8C0;display:flex;align-items:center;justify-content:center;font-size:44px">${spec.em}</div><div><div style="font:600 34px ${E}">${spec.head}</div><div style="font:400 24px Inter;color:#888">${spec.sub}</div></div></div>`
 : `<div style="padding:150px 70px 40px;text-align:center"><div style="font:400 80px/1.02 'Instrument Serif','Noto Color Emoji'">${spec.title}</div><div style="color:#7d6e71;font-size:30px;margin-top:14px">${spec.titleru}</div></div>`;
const page=`<html><head>${css}<style>html,body{margin:0;width:1080px;height:1920px;overflow:hidden;font-family:${E};color:#2a2224}
body{background:${spec.kind==='chat'?'#E9DED2':'#F3EEE8'};${spec.kind==='chat'?'background-image:radial-gradient(rgba(0,0,0,.04) 2px,transparent 2px);background-size:28px 28px':''}}
.hdr{height:200px;background:#F6F0EA;display:flex;align-items:center;gap:24px;padding:60px 40px 0;box-sizing:border-box;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.col{display:flex;flex-direction:column;gap:22px;padding:60px 40px 0}
.msg{max-width:800px;padding:16px 26px 14px;box-shadow:0 1px 2px rgba(0,0,0,.12)}
.w{position:relative}.w.l{align-self:flex-start}.w.r{align-self:flex-end}.msg.l{background:#fff;border-radius:6px 26px 26px 26px;transform-origin:0 0}
.msg.r{background:#DCF2D5;border-radius:26px 6px 26px 26px;transform-origin:100% 0}
.typ{position:absolute;left:0;top:0;background:#fff;border-radius:6px 26px 26px 26px;padding:24px 30px;display:flex;gap:10px}
.typ span{width:16px;height:16px;border-radius:50%;background:#bbb;animation:blink .9s infinite}.typ span:nth-child(2){animation-delay:.15s}.typ span:nth-child(3){animation-delay:.3s}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:28px;padding:20px 60px 0}
.card{background:#fff;border-radius:28px;box-shadow:0 10px 30px rgba(80,40,40,.10);padding:32px 26px;text-align:center;height:420px;box-sizing:border-box}
.plq{margin:44px 70px 0;background:rgba(251,246,238,.97);border-radius:28px;padding:32px 40px;box-shadow:0 12px 34px rgba(0,0,0,.15);text-align:center}
.brand{position:absolute;left:0;right:0;bottom:60px;text-align:center;font:500 20px Inter;letter-spacing:6px;color:${C}}
@keyframes pop{from{opacity:0;transform:scale(.6) translateY(20px)}to{opacity:1;transform:none}}
@keyframes popr{from{opacity:0;transform:scale(.6) translateY(20px)}to{opacity:1;transform:none}}
@keyframes show{from{opacity:0}to{opacity:1}}@keyframes hide{from{visibility:visible}to{visibility:hidden}}
@keyframes blink{0%,100%{opacity:.3}50%{opacity:1}}</style></head><body>${top}<div class=col>${body}${spec.kind==='chat'?plq:''}</div>${spec.kind!=='chat'?plq:''}<div class=brand>MARMAR CANDLES</div></body></html>`;
(async()=>{const b=await chromium.launch();const p=await b.newPage({viewport:{width:1080,height:1920}});const dir=path.join(OUT,spec.id);fs.rmSync(dir,{recursive:true,force:true});fs.mkdirSync(dir+'/fr',{recursive:true});
fs.writeFileSync(dir+'/p.html',page);await p.goto('file://'+dir+'/p.html');await p.waitForTimeout(800);
const N=Math.round(DUR*30);for(let i=0;i<N;i++){await p.evaluate(t=>document.getAnimations().forEach(a=>{a.pause();a.currentTime=t}),i/30*1000);await p.screenshot({path:dir+'/fr/'+String(i).padStart(4,'0')+'.jpg',type:'jpeg',quality:86});}
fs.writeFileSync(dir+'/ev.json',JSON.stringify({ev,end:tEnd,dur:DUR}));await b.close();})();
