"""Витрина товаров с кнопкой «в корзину» во всех статьях блога (решение Алины 14.11.2026).
Статьи, где уже есть [products …], и статья про Адвент/настольные свечи (25152) пропускаются."""
from wp import *
import re, sys
from xmas_posts import STYLE
SKIP_POSTS={25152}
EXCLUDE={15441,15443,15444}            # настольные свечи 18 см — Алина хочет убрать
WEDDING_IDS=[25078,22782,21156,18802]  # конструктор + свечи для свадебного торта
CATS=['ciparu-sveces','kuku-toperi','dzimsanas-dienas-sveces','tortes-sveces','dizaina-sveces','interjera-sveces','ziemassvetku-sveces','davanas','sveces-davanai']
def shortcode(inner): return STYLE+'<!-- wp:shortcode -->\n[products %s columns="4" class="mm-post-grid"]\n<!-- /wp:shortcode -->' % inner
def plan(p, slug2id):
    raw=p['content']['raw']
    if '[products ' in raw or p['id'] in SKIP_POSTS: return None
    ids=[]
    for s in re.findall(r'/product/([a-z0-9-]+)/?',raw):
        i=slug2id.get(s)
        if i and i not in ids and i not in EXCLUDE and i!=25078: ids.append(i)
    cats=[c.split('/')[-1] for c in re.findall(r'/product-category/([a-z0-9/-]+?)/?["#?]',raw)]
    title=p['title']['raw'].lower()
    if 'kāzu' in title or 'kazu-sveces' in cats: return 'ids="%s" orderby="post__in"' % ','.join(map(str,WEDDING_IDS))
    if len(ids)>=4: return 'ids="%s" orderby="post__in"' % ','.join(map(str,ids[:8]))
    cat=next((c for c in cats if c in CATS), 'tortes-sveces')
    for word,c in (('cipar','ciparu-sveces'),('toper','kuku-toperi'),('dizaina','dizaina-sveces')):
        if word in title: cat=c; break
    return 'category="%s" limit="8" orderby="popularity"' % cat
def insert(raw, block):
    m=re.search(r'<!-- /wp:paragraph -->',raw) or re.search(r'</p>',raw)
    return raw[:m.end()]+'\n\n'+block+raw[m.end():]
if __name__=='__main__':
    posts=json.load(open('/home/user/marmar-candles-feed/backups/2026-11-14/all-posts.json'))
    slug2id={}
    for page in range(1,5):
        r=jget('wc/v3/products',per_page=100,page=page,status='publish',_fields='id,slug,stock_status')
        for x in r:
            if x['stock_status']=='instock': slug2id[x['slug']]=x['id']
        time.sleep(4)
        if len(r)<100: break
    print('products',len(slug2id))
    dry='--go' not in sys.argv
    for p in posts:
        pl=plan(p,slug2id)
        print(p['id'],'|',pl or 'SKIP','|',p['title']['raw'][:45])
        if dry or not pl: continue
        raw=insert(p['content']['raw'],shortcode(pl))
        for k in range(4):
            code,j=jpost(f"wp/v2/posts/{p['id']}",{'content':raw})
            if isinstance(j,dict): print('   saved',code); break
            time.sleep(40)
        else: print('   FAILED')
        time.sleep(9)
