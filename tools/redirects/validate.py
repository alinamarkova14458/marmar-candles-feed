import json, requests, concurrent.futures as cf
M=json.load(open("redir/map.json")); H={"User-Agent":"Mozilla/5.0 (compatible; MarMarAudit/1.0)"}
dests=sorted({m["destination"] for m in M})
def chk(u):
    for _ in range(3):
        try:
            r=requests.head(u,headers=H,allow_redirects=False,timeout=60); return u,r.status_code,r.headers.get("location","")
        except Exception as e: err=str(e)[:50]
    return u,"ERR",err
with cf.ThreadPoolExecutor(4) as ex: res=list(ex.map(chk,dests))
json.dump(res,open("redir/dest_check.json","w"))
bad=[r for r in res if r[1]!=200]
print(len(dests),"destinations; bad:",len(bad))
for b in bad: print(b)
