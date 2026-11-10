import json,subprocess,sys
A='bd173e7da157c219a9ebdbbb06d01041'
def q(dims, start, end, limit=20):
    query='''query($a:String!,$s:Time!,$e:Time!){viewer{accounts(filter:{accountTag:$a}){
      rumPageloadEventsAdaptiveGroups(limit:%d, filter:{datetime_geq:$s, datetime_lt:$e%s}, orderBy:[count_DESC]){
        count sum{visits} dimensions{%s}}}}}'''%(limit,EXCL,dims)
    body=json.dumps({"query":query,"variables":{"a":A,"s":start,"e":end}})
    r=subprocess.run(['curl','-s','-H','Authorization: Bearer proxy-injected','-H','Content-Type: application/json','https://api.cloudflare.com/client/v4/graphql','-d',body],capture_output=True,text=True).stdout
    return json.loads(r)
s,e=sys.argv[1],sys.argv[2]
EXCL='' if '--all' in sys.argv else ', countryName_neq:"LV"'  # без Латвии: визиты Алины и проверки
print('Без Латвии' if EXCL else 'Все страны')
for d in ['date','refererHost','requestPath','countryName']:
    r=q(d,s,e,25)
    if r.get('errors'): print(d,r['errors']); continue
    print('##',d)
    for row in r['data']['viewer']['accounts'][0]['rumPageloadEventsAdaptiveGroups']:
        print(' ',row['dimensions'][d],'views',row['count'],'visits',row['sum']['visits'])
