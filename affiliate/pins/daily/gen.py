"""Daily article pins (style A Cherry, 1000x1500): list / single / collage per post.
Usage: python3 gen.py posts.json  → writes <slug>-list.html, <slug>-single.html, <slug>-collage.html; render: node shot.js"""
import json, sys
I = '/home/user/jalina-blog/public/img'
H = lambda body, css: f"<html><head><link rel=stylesheet href=base.css><style>{css}</style></head><body>{body}</body></html>"
for p in json.load(open(sys.argv[1])):
    s, items = p['slug'], p['items']
    tiles = ''.join(f'<div class=t><img src="file://{I}/{f}.webp"><div>{n}</div></div>' for f, n in items[:6])
    open(f'{s}-list.html', 'w').write(H(f'''<div class=top><div class=k>{p['kicker']}</div><h1>{p['title']}</h1><div class=sub>{p['sub']}</div></div>
<div class=g>{tiles}</div><div class=cta><span class=btn>{p['cta']}</span></div><div class=foot>jalina.art</div>''',
    '''body{background:#FBE3E1}.top{text-align:center;padding:40px 40px 6px}.k{font-family:H;font-size:48px;color:#9E1B32}
h1{font-family:S;font-weight:400;font-size:84px;line-height:1;margin:6px 0}.sub{font-size:25px;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:#6E1022}
.g{display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:20px 36px}.t{background:#fff;border-radius:18px;overflow:hidden}
.t img{width:100%;height:235px;object-fit:cover;display:block}.t div{font-size:23px;font-weight:700;text-align:center;padding:8px 6px 10px;color:#6E1022}
.cta{text-align:center;margin-top:4px}.foot{color:#9E1B32}'''))
    f, n = items[p.get('hero', 0)]; n = p.get('single_title', n)
    open(f'{s}-single.html', 'w').write(H(f'''<div class=hero><img src="file://{I}/{f}.webp"></div><div class=band><div class=k>{p['kicker']}</div><b>{n}</b><span>{p['single_sub']}</span></div><div class=foot>jalina.art</div>''',
    '''body{background:#FFF7F3}.hero{position:absolute;top:0;left:0;right:0;height:1010px;overflow:hidden}.hero img{width:100%;height:100%;object-fit:cover}
.band{position:absolute;left:0;right:0;top:1010px;bottom:88px;background:#9E1B32;color:#FFF7F3;text-align:center;padding:22px 40px}
.k{font-family:H;font-size:46px;color:#F4C6C3}.band b{display:block;font-family:S;font-weight:400;font-size:64px;line-height:1.02;margin:4px 0 12px}
.band span{font-size:25px;font-weight:700;letter-spacing:4px;text-transform:uppercase}.foot{color:#9E1B32}'''))
    imgs = ''.join(f'<img src="file://{I}/{f}.webp">' for f, _ in items[:6])
    open(f'{s}-collage.html', 'w').write(H(f'''<div class=g>{imgs}</div><div class=c><div class=k>{p['kicker']}</div><h1>{p['title']}</h1><div class=s>{p['sub']}</div></div><div class=foot>jalina.art</div>''',
    '''body{background:#FFF7F3}.g{position:absolute;inset:0 0 88px 0;display:grid;grid-template-columns:1fr 1fr;grid-template-rows:repeat(3,1fr);gap:8px}
.g img{width:100%;height:100%;object-fit:cover}.c{position:absolute;left:70px;right:70px;top:490px;background:#FFF7F3;border-radius:30px;padding:28px 30px 32px;text-align:center;box-shadow:0 18px 50px rgba(43,20,24,.35);border:4px solid #9E1B32}
.k{font-family:H;font-size:44px;color:#9E1B32}h1{font-family:S;font-weight:400;font-size:78px;line-height:1;margin:6px 0 12px}.s{font-size:24px;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:#6E1022}
.foot{background:#9E1B32;color:#FFF7F3}'''))
print('ok')
