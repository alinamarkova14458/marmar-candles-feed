"""Own-set pins (1000x1500): mockup on top, set name band, cover below. Usage: python3 gen.py '<id>:<mockup n>' ...; render: node ../daily/shot.js (run from this dir)"""
import json, sys
R = '/home/user/jalina-factory/ready'
shop = {s['id']: s for s in json.load(open('/home/user/jalina-blog/src/shop.json'))}
for arg in sys.argv[1:]:
    sid, n = arg.split(':')
    s = shop[sid]
    html = f'''<html><head><link rel=stylesheet href=base.css><style>
body{{background:#FFF7F3}}.m{{position:absolute;left:30px;right:30px;top:30px;height:627px;border-radius:24px;overflow:hidden;box-shadow:0 12px 30px rgba(43,20,24,.18)}}
.m img,.c img{{width:100%;height:100%;object-fit:cover;display:block}}
.b{{position:absolute;left:0;right:0;top:680px;height:170px;text-align:center}}.k{{font-family:H;font-size:46px;color:#9E1B32}}
.b b{{display:block;font-family:S;font-weight:400;font-size:60px;line-height:1.02;margin:2px 30px 8px}}.b span{{font-size:23px;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:#6E1022}}
.c{{position:absolute;left:30px;right:30px;top:865px;height:535px;border-radius:24px;overflow:hidden;border:4px solid #9E1B32}}.foot{{background:#9E1B32;color:#FFF7F3}}
</style></head><body><div class=m><img src="file://{R}/{sid}/mockup-{n}.jpg"></div>
<div class=b><div class=k>made by me · see it in use</div><b>{s["name"]} Clipart</b><span>{s["count"]} PNG · 300 DPI · instant download</span></div>
<div class=c><img src="file://{R}/{sid}/preview-1.jpg"></div><div class=foot>jalina.art</div></body></html>'''
    open(f'own-{sid}-m{n}.html', 'w').write(html)
print('ok')
