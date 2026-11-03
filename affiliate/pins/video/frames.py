import json
R='/home/user/jalina-factory/ready'; I='/home/user/jalina-blog/public/img'
own=[("05-pastel-christmas","Pastel Christmas Clipart","22 watercolor PNG"),("15-vintage-christmas","Vintage Christmas Clipart","22 watercolor PNG"),("17-cozy-winter-wonderland","Cozy Winter Wonderland","23 watercolor PNG"),
("01-birthday-number-candles","Birthday Number Candles","22 watercolor PNG"),("02-birthday-number-balloons","Birthday Number Balloons","22 watercolor PNG"),("04-birthday-party-animals","Birthday Party Animals","20 watercolor PNG"),
("07-first-birthday-girl","First Birthday Girl","22 watercolor PNG"),("16-under-the-sea","Under the Sea Birthday","22 watercolor PNG")]
art=[("canva-pairings","6 Canva Font Pairings","headline font + free Canva font",[f"{I}/font-{x}.webp" for x in ["funky-groovy","marquis","playful-bubble","antonio","remla","simple-signature"]]),
("cute-xmas","Cute Christmas Clipart PNG","gnomes · gingerbread · penguins",[f"{I}/{x}.webp" for x in ["gnome-artoya","gnome-pink","gingerbread","kawaii-xmas","penguin","penguin-snowman"]]),
("free-fonts","8 Free Christmas Fonts","no credit card · commercial use OK",[f"{I}/free-{x}.webp" for x in ["mountains-of-christmas","great-vibes","berkshire-swash","dancing-script","pacifico","playball","cookie","fredoka"]])]
css=f"""@font-face{{font-family:S;src:url(file:///home/user/jalina-blog/public/fonts/DMSerifDisplay-400-latin.woff2)}}@font-face{{font-family:H;src:url(file:///home/user/jalina-blog/public/fonts/Caveat-700-latin.woff2)}}@font-face{{font-family:M;src:url(file:///home/user/jalina-blog/public/fonts/Manrope-400-latin.woff2)}}
*{{margin:0;box-sizing:border-box}}body{{width:1000px;height:1500px;overflow:hidden;background:#FFF7F3;font-family:M;position:relative}}
.top{{position:absolute;top:0;left:0;right:0;height:300px;background:#9E1B32;color:#FFF7F3;text-align:center;padding-top:50px}}
.k{{font-family:H;font-size:48px;color:#F4C6C3}}.t{{font-family:S;font-size:76px;line-height:1.02;margin-top:6px}}
.img{{position:absolute;top:350px;left:30px;width:940px;height:627px;border-radius:24px;overflow:hidden;box-shadow:0 16px 40px rgba(43,20,24,.18)}}.img img{{width:100%;height:100%;object-fit:cover}}
.s{{position:absolute;top:1030px;left:0;right:0;text-align:center;font-size:30px;font-weight:800;letter-spacing:4px;text-transform:uppercase;color:#6E1022}}
.cta{{position:absolute;top:1120px;left:0;right:0;text-align:center}}.btn{{display:inline-block;background:#9E1B32;color:#FFF7F3;font-weight:800;font-size:32px;letter-spacing:2px;text-transform:uppercase;padding:20px 40px;border-radius:999px}}
.b{{position:absolute;bottom:60px;left:0;right:0;text-align:center;font-family:S;font-size:44px;color:#9E1B32}}"""
jobs=[]
for k,t,sub in own:
  imgs=[f"{R}/{k}/preview-{i}.jpg" for i in range(1,6)]
  jobs.append((f"own-{k}",t,sub,imgs,"hand-painted clipart","Shop on Creative Market"))
for k,t,sub,imgs in art:
  jobs.append((f"art-{k}",t,sub,imgs,"jalina.art finds","See the full list"))
for name,t,sub,imgs,kick,cta in jobs:
  for i,im in enumerate(imgs):
    open(f"frames/{name}-{i}.html","w").write(f"<html><head><style>{css}</style></head><body><div class=top><div class=k>{kick}</div><div class=t>{t}</div></div><div class=img><img src='file://{im}'></div><div class=s>{sub}</div><div class=cta><span class=btn>{cta}</span></div><div class=b>jalina.art</div></body></html>")
json.dump([[j[0],len(j[3])] for j in jobs],open('jobs.json','w'))
