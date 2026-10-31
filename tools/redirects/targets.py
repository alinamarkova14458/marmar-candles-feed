import json, requests
H={"User-Agent":"Mozilla/5.0 (compatible; MarMarAudit/1.0)"}
B="https://marmarcandles.com/wp-json"
def allp(path, extra=""):
    out=[];pg=1
    while True:
        r=requests.get(f"{B}{path}?per_page=100&page={pg}{extra}",headers=H,timeout=90)
        if r.status_code!=200: print(path,r.status_code,r.text[:100]); break
        j=r.json(); out+=j
        if len(j)<100: break
        pg+=1
    return out
prods=allp("/wc/store/v1/products")
cats=allp("/wc/store/v1/products/categories")
pages=allp("/wp/v2/pages","&_fields=id,slug,link,title,status")
posts=allp("/wp/v2/posts","&_fields=id,slug,link,title,status")
T={"products":[{"id":p["id"],"slug":p["slug"],"link":p["permalink"],"name":p["name"],"cats":[c["slug"] for c in p.get("categories",[])]} for p in prods],
   "cats":[{"id":c["id"],"slug":c["slug"],"link":c.get("permalink"),"name":c["name"],"parent":c.get("parent"),"count":c.get("count")} for c in cats],
   "pages":[{"slug":p["slug"],"link":p["link"],"title":p["title"]["rendered"]} for p in pages],
   "posts":[{"slug":p["slug"],"link":p["link"],"title":p["title"]["rendered"]} for p in posts]}
json.dump(T,open("redir/targets.json","w"),ensure_ascii=False,indent=0)
print({k:len(v) for k,v in T.items()})
for c in T["cats"]: print("CAT",c["slug"],c["count"],c["link"])
for p in T["pages"]: print("PAGE",p["slug"],p["link"])
for p in T["posts"]: print("POST",p["slug"])
