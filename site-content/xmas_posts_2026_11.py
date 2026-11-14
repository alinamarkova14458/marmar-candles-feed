from wp import *
import re
posts=json.load(open('/home/user/marmar-candles-feed/backups/2026-11-14/xmas-posts.json'))
GRID={ '25166':[14592,14591,14590,14589,24565,24921,24911,24933],
       '25167':[14589,14592,14591,14590,24903,24565,24907,24921],
       '25153':[24565,24921,14592,14591,24907,24911,24933,24915],
       '25142':[24565,14592,14591,14590,14589,24907,24921,24933] }
SEO={ '25166':('Ziemassvētku dāvana skolotājai un audzinātājai no 5 € | MarMar Candles','Ziemassvētku dāvana skolotājai vai bērnudārza audzinātājai: rokām lietas sveces dāvanu kastītē no 5 €. Idejas no viena bērna un no visas klases, teksti kartītei.'),
      '25167':('Slepenā Santas dāvanas līdz 5 un 10 eur — idejas kolēģiem | MarMar Candles','Dāvanas līdz 5 eur un līdz 10 eur Slepenajam Santam: rokām lietas sveces dāvanu kastītē. Der kolēģiem, draugiem un klasesbiedriem. Piegāde uz pakomātu.'),
      '25153':('Korporatīvās dāvanas Ziemassvētkos — sveces ar uzņēmuma nosaukumu | MarMar Candles','Korporatīvās Ziemassvētku dāvanas kolēģiem un klientiem: rokām lietas sveces dāvanu iepakojumā. No 50 gab. — ar uzņēmuma nosaukumu uz birkas. Izgatavots Rīgā.'),
      '25142':('Ziemassvētku dāvanas līdz 10 € un 20 € kolēģiem, draugiem, ģimenei | MarMar Candles','Mazas Ziemassvētku dāvanas kolēģiem, draugiem un ģimenei: rokām lietas sveces līdz 10 € un 20 €. Dāvanu kastītes, piegāde uz pakomātu visā Latvijā.') }
def grid(ids): return '<!-- wp:shortcode -->\n[products ids="%s" columns="4" orderby="post__in"]\n<!-- /wp:shortcode -->' % ','.join(map(str,ids))
def build(pid):
    raw=posts[pid]['content']['raw']
    assert '[products ' not in raw
    end='<!-- /wp:paragraph -->'; i=raw.index(end)+len(end)
    raw=raw[:i]+'\n\n'+grid(GRID[pid])+raw[i:]
    if pid=='25153':
        old='Dizainu saskaņoju ar jums pirms izgatavošanas.</p>'
        assert old in raw
        raw=raw.replace(old,'Dizainu saskaņoju ar jums pirms izgatavošanas.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><strong>Pasūtījumiem no 50 gab.</strong> pievienoju birku vai uzlīmi ar jūsu uzņēmuma nosaukumu. Uzrakstiet, cik dāvanu vajag, – cenu piedāvājumu atsūtīšu vienas darba dienas laikā.</p>',1)
        old2='<strong>1.</strong> Uzrakstiet man caur formu lapā'
        assert old2 in raw
    return raw
if __name__=='__main__':
    import sys
    for pid in sys.argv[1:]:
        raw=build(pid)
        for k in range(3):
            code,j=jpost(f'wp/v2/posts/{pid}',{'content':raw})
            if isinstance(j,dict): print(pid,code,j.get('modified')); break
            time.sleep(30)
        time.sleep(5)
        t,d=SEO[pid]
        for k in range(3):
            code,j=jpost('rankmath/v1/updateMeta',{'objectType':'post','objectID':int(pid),'meta':{'rank_math_title':t,'rank_math_description':d}})
            if isinstance(j,dict): print('  meta',code); break
            time.sleep(30)
        time.sleep(6)
