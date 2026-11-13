#!/usr/bin/env python3
"""Снимок Microsoft Clarity за последние 3 дня → analytics/clarity/YYYY-MM-DD.json (краткая сводка).

API Clarity отдаёт только последние 1–3 дня и не больше 10 запросов в сутки, поэтому снимок
делаем 3 раза в неделю (пн, ср, пт), а понедельничный отчёт собирает снимки за неделю.
Ключ (Bearer) подставляет прокси окружения для хоста www.clarity.ms — в коде его нет.
"""
import collections, datetime, json, pathlib, re, subprocess, sys

API = 'https://www.clarity.ms/export-data/api/v1/project-live-insights?numOfDays=3'
OUT = pathlib.Path(__file__).resolve().parents[2] / 'analytics' / 'clarity'


def get(extra=''):
    r = subprocess.run(['curl', '-s', '-m', '90', '-w', '\n%{http_code}', API + extra], capture_output=True, text=True)
    body, _, code = r.stdout.rpartition('\n')
    if code != '200':
        sys.exit(f'Clarity HTTP {code}: {body[:200]}')
    return json.loads(body)


def norm(u):
    return re.sub(r'^https?://[^/]+', '', re.sub(r'\?.*', '', u or '')) or '/'


def main():
    total, by_url, by_dev = get(), get('&dimension1=URL'), get('&dimension1=Device')
    summary = {'date': datetime.date.today().isoformat(), 'window_days': 3, 'total': {}, 'device': {}, 'pages': {}}
    for m in total:
        if m['information']:
            summary['total'][m['metricName']] = {k: v for k, v in m['information'][0].items() if k != 'name'}
    for m in by_dev:
        for i in m['information']:
            summary['device'].setdefault(i.get('Device', '?'), {})[m['metricName']] = {k: v for k, v in i.items() if k != 'Device'}
    pages = collections.defaultdict(lambda: collections.defaultdict(float))
    for m in by_url:
        for i in m['information']:
            a = pages[norm(i.get('Url') or i.get('URL'))]
            name = m['metricName']
            if name == 'Traffic':
                a['sessions'] += float(i.get('totalSessionCount') or 0)
                a['bots'] += float(i.get('totalBotSessionCount') or 0)
            elif name == 'ScrollDepth':
                a['scroll'] = max(a['scroll'], float(i.get('averageScrollDepth') or 0))
            elif name == 'EngagementTime':
                a['active_s'] = max(a['active_s'], float(i.get('activeTime') or 0))
            elif name in ('DeadClickCount', 'RageClickCount', 'QuickbackClick', 'ScriptErrorCount', 'ErrorClickCount', 'ExcessiveScroll'):
                a[name] += float(i.get('subTotal') or 0)
    top = sorted(pages.items(), key=lambda x: -x[1]['sessions'])[:40]
    summary['pages'] = {u: {k: round(v, 1) for k, v in a.items()} for u, a in top}
    OUT.mkdir(parents=True, exist_ok=True)
    path = OUT / f"{summary['date']}.json"
    path.write_text(json.dumps(summary, ensure_ascii=False, indent=1))
    print('saved', path, 'sessions', summary['total'].get('Traffic', {}).get('totalSessionCount'))


if __name__ == '__main__':
    main()
