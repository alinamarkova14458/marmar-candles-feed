import json, requests, concurrent.futures as cf, urllib.parse
pages=json.load(open("redir/gsc_pages.json"))
H={"User-Agent":"Mozilla/5.0 (compatible; MarMarAudit/1.0)"}
def trace(u):
    hops=[];cur=u
    for _ in range(8):
        try: r=requests.get(cur,headers=H,allow_redirects=False,timeout=30)
        except Exception as e: hops.append({"url":cur,"status":"ERR","by":str(e)[:60]}); break
        hops.append({"url":cur,"status":r.status_code,"by":r.headers.get("x-redirect-by","")})
        if r.status_code in (301,302,307,308) and r.headers.get("location"):
            cur=urllib.parse.urljoin(cur,r.headers["location"])
        else: break
    return hops
with cf.ThreadPoolExecutor(6) as ex:
    res=list(ex.map(lambda p: trace(p["url"]), pages))
for p,h in zip(pages,res): p["hops"]=h
json.dump(pages,open("redir/traced.json","w"),ensure_ascii=False)
import collections
c=collections.Counter((len(p["hops"])-1, p["hops"][-1]["status"]) for p in pages); print(sorted(c.items()))
