from fix_style_css import NEW
from wp import *
import re, sys
PAT=re.compile(r'<style>(?:@media\(min-width:690px\)\{)?(?:body\.mmd )?\.entry-content \.mm-post-grid.*?</style>',re.S)
ids=[int(x) for x in sys.argv[1:]] or [p['id'] for p in json.load(open('/home/user/marmar-candles-feed/backups/2026-11-14/all-posts.json')) if p['id']!=25152]
for pid in ids:
    p=jget(f'wp/v2/posts/{pid}',context='edit',_fields='id,content'); raw=p['content']['raw']
    new,n=PAT.subn(lambda m: NEW,raw)
    if n!=1: print(pid,'style blocks found:',n,'— skipped'); time.sleep(3); continue
    time.sleep(3)
    for k in range(4):
        code,j=jpost(f'wp/v2/posts/{pid}',{'content':new})
        if isinstance(j,dict): print(pid,'ok'); break
        time.sleep(40)
    else: print(pid,'FAILED')
    time.sleep(7)
