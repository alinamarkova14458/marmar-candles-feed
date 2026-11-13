#!/usr/bin/env python3
"""GA4 за последние 7 дней: воронка, каналы, устройства, входы, товары, выходы + прошлая неделя для сравнения.
Нужен GOOGLE_SERVICE_ACCOUNT_JSON (см. CLAUDE.md). Только чтение."""
import json, os
from google.oauth2 import service_account
from google.auth.transport.requests import AuthorizedSession

cr = service_account.Credentials.from_service_account_info(
    json.loads(os.environ['GOOGLE_SERVICE_ACCOUNT_JSON']),
    scopes=['https://www.googleapis.com/auth/analytics.readonly'])
s = AuthorizedSession(cr)
P = 'properties/336408410'
RANGE = [{'startDate': '7daysAgo', 'endDate': 'yesterday'}]
PREV = [{'startDate': '14daysAgo', 'endDate': '8daysAgo'}]


def run(dims, mets, filt=None, order=None, limit=25, ranges=RANGE):
    body = {'dateRanges': ranges, 'dimensions': [{'name': d} for d in dims],
            'metrics': [{'name': m} for m in mets], 'limit': limit}
    if filt:
        body['dimensionFilter'] = filt
    if order:
        body['orderBys'] = [{'metric': {'metricName': order}, 'desc': True}]
    import time
    for i in range(5):
        resp = s.post(f'https://analyticsdata.googleapis.com/v1beta/{P}:runReport', json=body)
        if resp.status_code < 500:
            break
        time.sleep(3 * (i + 1))
    try:
        r = resp.json()
    except Exception:
        print('HTTP', resp.status_code); return []
    if 'error' in r:
        print('ERR', r['error'].get('message'))
        return []
    return [([d['value'] for d in row.get('dimensionValues', [])], [m['value'] for m in row['metricValues']]) for row in r.get('rows', [])]


def show(title, rows, mets):
    print(f'\n=== {title}')
    for d, m in rows:
        print('  ' + ' | '.join(d) + '  →  ' + ', '.join(f'{k}={v}' for k, v in zip(mets, m)))


ev = {'filter': {'fieldName': 'eventName', 'inListFilter': {'values': ['session_start', 'page_view', 'view_item', 'add_to_cart', 'view_cart', 'begin_checkout', 'purchase']}}}
show('Funnel events, last 7 days (events, users)', run(['eventName'], ['eventCount', 'totalUsers'], ev, 'eventCount'), ['events', 'users'])
mets = ['sessions', 'engagementRate', 'ecommercePurchases', 'purchaseRevenue']
show('By channel', run(['sessionDefaultChannelGroup'], mets, order='sessions'), mets)
show('By device', run(['deviceCategory'], ['sessions', 'engagementRate', 'addToCarts', 'checkouts', 'ecommercePurchases'], order='sessions'), ['sessions', 'engRate', 'addToCarts', 'checkouts', 'purchases'])
show('Landing pages (top 15)', run(['landingPagePlusQueryString'], ['sessions', 'engagementRate', 'bounceRate', 'ecommercePurchases'], order='sessions', limit=15), ['sessions', 'engRate', 'bounce', 'purchases'])
show('Items: viewed / added / purchased (top 20)', run(['itemName'], ['itemsViewed', 'itemsAddedToCart', 'itemsPurchased'], order='itemsViewed', limit=20), ['viewed', 'added', 'bought'])
show('Exit pages (top 12)', run(['pagePath'], ['screenPageViews', 'sessions'], order='screenPageViews', limit=12), ['views', 'sessions'])
show('Previous 7 days, channels', run(['sessionDefaultChannelGroup'], mets, order='sessions', ranges=PREV), mets)
