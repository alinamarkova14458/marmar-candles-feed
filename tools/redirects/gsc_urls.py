import json, os, urllib.parse
from google.oauth2 import service_account
from google.auth.transport.requests import AuthorizedSession
S=AuthorizedSession(service_account.Credentials.from_service_account_info(json.loads(os.environ["GOOGLE_SERVICE_ACCOUNT_JSON"]),scopes=["https://www.googleapis.com/auth/webmasters.readonly"]))
site=urllib.parse.quote("sc-domain:marmarcandles.com",safe="")
rows=[];start=0
while True:
    r=S.post(f"https://searchconsole.googleapis.com/webmasters/v3/sites/{site}/searchAnalytics/query",json={"startDate":"2025-06-01","endDate":"2026-10-29","dimensions":["page"],"rowLimit":25000,"startRow":start},timeout=60).json().get("rows",[])
    rows+=r
    if len(r)<25000: break
    start+=25000
json.dump([{"url":x["keys"][0],"clicks":x["clicks"],"impr":x["impressions"]} for x in rows],open("redir/gsc_pages.json","w"))
print(len(rows))
