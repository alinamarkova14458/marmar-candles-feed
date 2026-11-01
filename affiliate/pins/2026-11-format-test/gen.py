import json,os
I='/home/user/jalina-blog/public/img'
pairs=[("font-funky-groovy","Funky Groovy","Poppins","retro & fun"),("font-marquis","Marquis","Montserrat","elegant & minimal"),
("font-playful-bubble","Playful Bubble","Quicksand","cute & bubbly"),("font-antonio","Antonio Signature","Playfair Display","luxe signature"),
("font-remla","Remla","Lato","high-fashion"),("font-simple-signature","Simple Signature","Cormorant Garamond","soft & romantic")]
clip=[("gnome-artoya","Whimsical Christmas Gnomes","31 PNG · transparent"),("gnome-pink","Pink Christmas Gnomes","18 kawaii PNG"),
("gingerbread","Cute Gingerbread Clipart","30 PNG · 300 DPI"),("kawaii-xmas","Kawaii Christmas Animals","23 PNG · transparent"),
("penguin","Watercolor Christmas Penguins","17 PNG · 300 DPI"),("penguin-snowman","Penguin Snowman Clipart","16 watercolor PNG")]
H=lambda body,css: f"<html><head><link rel=stylesheet href=base.css><style>{css}</style></head><body>{body}</body></html>"
# 1 Canva list
tiles=''.join(f'<div class=t><img src="file://{I}/{f}.webp"><div><b>{n}</b> + {c}</div></div>' for f,n,c,_ in pairs)
open('canva-list.html','w').write(H(f'''<div class=top><div class=k>headline font + free Canva font</div><h1>6 Canva Font Pairings</h1><div class=sub>that don't look like everyone else's</div></div>
<div class=g>{tiles}</div><div class=cta><span class=btn>See the pairings</span></div><div class=foot>jalina.art</div>''',
'''body{background:#FBE3E1}.top{text-align:center;padding:46px 40px 10px}.k{font-family:H;font-size:50px;color:#9E1B32}
h1{font-family:S;font-weight:400;font-size:100px;line-height:1;margin:6px 0}.sub{font-size:27px;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:#6E1022}
.g{display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:20px 36px}.t{background:#fff;border-radius:18px;overflow:hidden}
.t img{width:100%;height:220px;object-fit:cover;display:block}.t div{font-size:23px;text-align:center;padding:8px 6px 10px;color:#6E1022}
.cta{text-align:center;margin-top:6px}.foot{color:#9E1B32}'''))
# 2 Canva singles
for f,n,c,mood in pairs:
  open(f'canva-{f[5:]}.html','w').write(H(f'''<div class=top><div class=k>Canva font pairing · {mood}</div></div>
<div class=card><img src="file://{I}/{f}.webp"><div class=plus>+</div><div class=c2>{c}<span>free in Canva</span></div></div>
<div class=cap><b>{n}</b> for headlines,<br>{c} for everything else</div><div class=cta><span class=btn>How to set it up</span></div><div class=foot>jalina.art</div>''',
'''body{background:#FFF7F3}.top{text-align:center;padding:60px 30px 20px}.k{font-family:H;font-size:56px;color:#9E1B32}
.card{margin:10px 50px;background:#fff;border-radius:28px;overflow:hidden;box-shadow:0 14px 40px rgba(43,20,24,.12);text-align:center;padding-bottom:30px}
.card img{width:100%;height:600px;object-fit:cover;display:block}.plus{font-family:S;font-size:90px;color:#9E1B32;line-height:1;margin-top:10px}
.c2{font-family:S;font-size:64px;line-height:1.05}.c2 span{display:block;font-family:M;font-size:24px;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:#9E1B32;margin-top:8px}
.cap{text-align:center;font-size:34px;line-height:1.35;margin:34px 40px 26px}.cta{text-align:center}.foot{color:#9E1B32}'''))
# 3 Clipart collage list
tiles=''.join(f'<img src="file://{I}/{f}.webp">' for f,_,_ in clip)
open('clip-list.html','w').write(H(f'''<div class=g>{tiles}</div><div class=c><div class=k>gnomes · gingerbread · penguins</div><h1>Cute Christmas Clipart PNG</h1><div class=s>6 bundles · transparent · 300 DPI</div></div><div class=foot>jalina.art</div>''',
'''body{background:#FFF7F3}.g{position:absolute;inset:0 0 88px 0;display:grid;grid-template-columns:1fr 1fr;grid-template-rows:repeat(3,1fr);gap:8px}
.g img{width:100%;height:100%;object-fit:cover}.c{position:absolute;left:70px;right:70px;top:500px;background:#FFF7F3;border-radius:30px;padding:28px 30px 32px;text-align:center;box-shadow:0 18px 50px rgba(43,20,24,.35);border:4px solid #9E1B32}
.k{font-family:H;font-size:44px;color:#9E1B32}h1{font-family:S;font-weight:400;font-size:84px;line-height:1;margin:6px 0 12px}.s{font-size:25px;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:#6E1022}
.foot{background:#9E1B32;color:#FFF7F3}'''))
# 4 Clipart singles: big image
for f,n,sub in clip:
  open(f'clip-{f}.html','w').write(H(f'''<div class=hero><img src="file://{I}/{f}.webp"></div><div class=band><div class=k>cute Christmas clipart</div><b>{n}</b><span>{sub}</span></div><div class=foot>jalina.art</div>''',
'''body{background:#FFF7F3}.hero{position:absolute;top:0;left:0;right:0;height:1010px;overflow:hidden}.hero img{width:100%;height:100%;object-fit:cover}
.band{position:absolute;left:0;right:0;top:1010px;bottom:88px;background:#9E1B32;color:#FFF7F3;text-align:center;padding:22px 40px}
.k{font-family:H;font-size:46px;color:#F4C6C3}.band b{display:block;font-family:S;font-weight:400;font-size:66px;line-height:1.02;margin:4px 0 12px}
.band span{font-size:26px;font-weight:700;letter-spacing:4px;text-transform:uppercase}.foot{color:#9E1B32}'''))
print(len([x for x in os.listdir('.') if x.endswith('.html')]))
