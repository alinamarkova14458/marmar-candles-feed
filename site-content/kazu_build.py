#!/usr/bin/env python3
"""Собирает новое содержимое страницы /kazu-sveces/ (блоки Gutenberg) из сохранённых кусков старой страницы."""
import json, re, html, sys

raw = json.load(open(sys.argv[1]))['content']['raw']
CFG = 'https://marmarcandles.com/product/personalizetas-kazu-sveces-viesiem-izveidojiet-savas/'
UP = 'https://marmarcandles.com/wp-content/uploads/2026/11/'


def top_blocks(src):
    depth, out = 0, []
    for m in re.finditer(r'<!-- (/?)wp:([a-z0-9/-]+)([^>]*?)(/?)-->', src):
        closing, name, selfc = m.group(1), m.group(2), m.group(4)
        if closing:
            depth -= 1
            if depth == 0:
                out[-1][2] = m.end()
        else:
            if depth == 0:
                out.append([name, m.start(), m.end()])
            if not selfc:
                depth += 1
    return [(n, src[s:e]) for n, s, e in out]


blocks = top_blocks(raw)
carousel = next(b for n, b in blocks if n == 'stackable/carousel')
galleries = [b for n, b in blocks if n == 'gallery']
assert len(galleries) == 3, len(galleries)
g_sticker, g_tag, g_works = galleries
form = next(b for n, b in blocks if n == 'shortcode' and 'contact-form-7' in b)

e = html.escape


def h(text, level=2, align=None, anchor=None):
    attrs = {}
    if level != 2:
        attrs['level'] = level
    if align:
        attrs['style'] = {'typography': {'textAlign': align}}
    a = (' ' + json.dumps(attrs, ensure_ascii=False)) if attrs else ''
    cls = 'wp-block-heading' + (f' has-text-align-{align}' if align else '')
    idattr = f' id="{anchor}"' if anchor else ''
    if anchor:
        a = ' ' + json.dumps({**attrs, 'anchor': anchor}, ensure_ascii=False)
    return f'<!-- wp:heading{a} -->\n<h{level} class="{cls}"{idattr}>{text}</h{level}>\n<!-- /wp:heading -->'


def p(text, align=None, cls=None):
    attrs = {}
    classes = []
    if align:
        attrs['style'] = {'typography': {'textAlign': align}}
        classes.append(f'has-text-align-{align}')
    if cls:
        attrs['className'] = cls
        classes.append(cls)
    a = (' ' + json.dumps(attrs, ensure_ascii=False)) if attrs else ''
    c = f' class="{" ".join(classes)}"' if classes else ''
    return f'<!-- wp:paragraph{a} -->\n<p{c}>{text}</p>\n<!-- /wp:paragraph -->'


def ul(items, ordered=False):
    tag = 'ol' if ordered else 'ul'
    a = ' {"ordered":true}' if ordered else ''
    lis = '\n\n'.join(f'<!-- wp:list-item -->\n<li>{i}</li>\n<!-- /wp:list-item -->' for i in items)
    return f'<!-- wp:list{a} -->\n<{tag} class="wp-block-list">{lis}</{tag}>\n<!-- /wp:list -->'


def btns(items, center=True):
    inner = []
    for text, url, outline in items:
        if outline:
            inner.append(f'<!-- wp:button {{"className":"is-style-outline"}} -->\n<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="{url}">{text}</a></div>\n<!-- /wp:button -->')
        else:
            inner.append(f'<!-- wp:button {{"className":"mm-cta-primary"}} -->\n<div class="wp-block-button mm-cta-primary"><a class="wp-block-button__link wp-element-button" href="{url}">{text}</a></div>\n<!-- /wp:button -->')
    lay = ' {"layout":{"type":"flex","justifyContent":"center"}}' if center else ''
    return f'<!-- wp:buttons{lay} -->\n<div class="wp-block-buttons">' + '\n\n'.join(inner) + '</div>\n<!-- /wp:buttons -->'


def img(url, alt, iid):
    return (f'<!-- wp:image {{"id":{iid},"sizeSlug":"full","linkDestination":"custom","className":"is-style-rounded-soft"}} -->\n'
            f'<figure class="wp-block-image size-full is-style-rounded-soft"><a href="{CFG}"><img src="{url}" alt="{e(alt)}" class="wp-image-{iid}"/></a></figure>\n<!-- /wp:image -->')


def cols(columns):
    inner = '\n\n'.join(f'<!-- wp:column -->\n<div class="wp-block-column">' + '\n\n'.join(c) + '</div>\n<!-- /wp:column -->' for c in columns)
    return f'<!-- wp:columns -->\n<div class="wp-block-columns">{inner}</div>\n<!-- /wp:columns -->'


def group(content, cls):
    return (f'<!-- wp:group {{"className":"{cls}","layout":{{"type":"constrained"}}}} -->\n<div class="wp-block-group {cls}">'
            + '\n\n'.join(content) + '</div>\n<!-- /wp:group -->')


def table(rows, head):
    th = ''.join(f'<th>{c}</th>' for c in head)
    trs = ''.join('<tr>' + ''.join(f'<td>{c}</td>' for c in r) + '</tr>' for r in rows)
    return (f'<!-- wp:table {{"className":"mm-price-table"}} -->\n<figure class="wp-block-table mm-price-table"><table><thead><tr>{th}</tr></thead><tbody>{trs}</tbody></table></figure>\n<!-- /wp:table -->')


def details(q, a):
    return f'<!-- wp:details -->\n<details class="wp-block-details"><summary>{q}</summary>' + a + '</details>\n<!-- /wp:details -->'


def sc(code):
    return f'<!-- wp:shortcode -->\n{code}\n<!-- /wp:shortcode -->'


css = '''<!-- wp:html -->
<style>
.mm-wed .wp-block-buttons{gap:12px}
.mm-trust{display:flex;flex-wrap:wrap;justify-content:center;gap:8px 18px;font-size:14px;margin:18px 0 8px}
.mm-trust span{white-space:nowrap}
.mm-card{background:#fff;border:1px solid #eee3e1;border-radius:16px;padding:14px;height:100%}
.mm-card .wp-block-image img{border-radius:12px;aspect-ratio:1/1;object-fit:cover;width:100%}
.mm-card h3{margin:12px 0 6px}
.mm-price-table table{border-collapse:collapse;width:100%;max-width:520px;margin:0 auto}
.mm-price-table th,.mm-price-table td{padding:10px 14px;border-bottom:1px solid #eee3e1;text-align:left}
.mm-price-table td:last-child,.mm-price-table th:last-child{text-align:right;font-weight:600}
.mm-steps{counter-reset:s;list-style:none;padding:0;display:grid;gap:12px;max-width:640px;margin:0 auto}
.mm-steps li{counter-increment:s;position:relative;padding:12px 14px 12px 56px;background:#fff;border:1px solid #eee3e1;border-radius:12px}
.mm-steps li::before{content:counter(s);position:absolute;left:14px;top:12px;width:28px;height:28px;border-radius:50%;background:#c88a85;color:#fff;display:grid;place-items:center;font-weight:600}
.mm-faq details{border-bottom:1px solid #eee3e1;padding:12px 0}
.mm-faq summary{cursor:pointer;font-weight:600}
.mm-faq details p{margin:8px 0 0}
.mm-section{margin-top:48px}
</style>
<!-- /wp:html -->'''

steps_html = ('<!-- wp:list {"ordered":true,"className":"mm-steps"} -->\n<ol class="wp-block-list mm-steps">' + '\n\n'.join(
    f'<!-- wp:list-item -->\n<li>{t}</li>\n<!-- /wp:list-item -->' for t in [
        '<strong>Izveidojiet savas sveces konstruktorā</strong> — izvēlieties sveci, daudzumu, lentes krāsu un personalizāciju. Tas aizņem pāris minūtes.',
        '<strong>Saņemiet rēķinu</strong> e-pastā un apmaksājiet to.',
        '<strong>3 darba dienu laikā pēc apmaksas</strong> atsūtīsim birkas vai uzlīmes maketu — redzēsiet gatavo dizainu.',
        '<strong>Apstipriniet maketu</strong> vai palūdziet labojumus.',
        '<strong>Izgatavojam ~2 nedēļās</strong> un nosūtām uz pakomātu vai saņemat Rīgā, Iļģuciemā.',
    ]) + '</ol>\n<!-- /wp:list -->')

faq = [
    ('Cik maksā personalizētas kāzu sveces?', p('Cena par gabalu atkarīga no daudzuma: 10–19 gab. — 5 €, 20–29 — 4,50 €, 30–49 — 4 €, 50–99 — 3,50 €, no 100 gab. — 3 €. Lentīte, caurspīdīgs iepakojums un personalizācija (birka vai uzlīme) ir iekļauta cenā.')),
    ('Kāds ir minimālais pasūtījums?', p('10 sveces. Lielākiem daudzumiem cena par gabalu samazinās.')),
    ('Cik laicīgi jāpasūta?', p('Iesakām pasūtīt <strong>4–8 nedēļas pirms kāzām</strong>. Maketu atsūtām 3 darba dienu laikā pēc apmaksas, izgatavošana aizņem ~2 nedēļas pēc maketa apstiprināšanas. Konstruktorā varat norādīt vēlamo saņemšanas datumu — vadīsimies pēc tā.')),
    ('Vai dizainu var pieskaņot mūsu ielūgumiem?', p('Jā. Konstruktorā pievienojiet ielūgumu, gatavu dizainu vai iedvesmas attēlu — uzlīmes dizainu izveidosim Jūsu kāzu stilā un krāsās.')),
    ('Ko var uzrakstīt uz birkas vai uzlīmes?', p('Jūsu vārdus, kāzu datumu, pateicības tekstu viesiem, iniciāļus vai monogrammu.')),
    ('Kādas lentes krāsas ir pieejamas?', p('Sarkana, tumši zaļa, balta, melna, lavandas, zelta, oranža un rozā. Ja vēlaties citu krāsu, aprakstiet to komentārā.')),
    ('Kā notiek piegāde?', p('Sūtām uz Omniva, DPD, Venipak un Unisend pakomātiem visā Latvijā, kā arī uz Lietuvu un Igauniju. Pasūtījumu var arī saņemt mūsu darbnīcā Rīgā, Iļģuciemā.')),
    ('Vai varam pasūtīt vairāk nekā 100 sveču vai īpašu dizainu?', p('Jā. Lielus un nestandarta pasūtījumus izgatavojam regulāri — uzrakstiet mums zemāk esošajā formā, un sagatavosim piedāvājumu.')),
]

out = [
    css,
    carousel,
    group([
        h('Personalizētas kāzu sveces viesiem', 1, 'center'),
        p('Roku darinātas mini sveces caurspīdīgā iepakojumā ar lentīti Jūsu kāzu krāsā un personalizētu birku vai uzlīmi — silts paldies, ko viesi aizvedīs mājās. <strong>No 3 € par gabalu, no 10 sveču.</strong>', 'center'),
        btns([('Izveidot un pasūtīt →', CFG, False), ('Uzdot jautājumu', '#pieteikums', True)]),
        sc('[marmar_google_rating]'),
        p('<span>🕯️ Roku darbs Latvijā</span> <span>🎀 Dizains pēc Jūsu ielūguma</span> <span>📦 Piegāde uz pakomātu</span>', 'center', 'mm-trust'),
    ], 'mm-wed'),

    group([
        h('Izvēlieties sveci', 2, 'center'),
        cols([
            [group([img(UP + 'kazu-svece-burcina-sarkana-lentite.webp', 'Kāzu svece stikla burciņā ar sarkanu lentīti', 25082),
                    h('Svece stikla burciņā (20 ml)', 3),
                    p('Eleganta klasika: svece mazā stikla burciņā ar baltu vāciņu. Lieliski izskatās uz svētku galda.'),
                    p('<strong>no 3 € / gab.</strong>'),
                    btns([('Izvēlēties →', CFG, False)], center=False)], 'mm-card')],
            [group([img(UP + 'kazu-mini-bubble-svece-uzlime.webp', 'Mini Bubble kāzu svecīte ar personalizētu uzlīmi', 25083),
                    h('Mini Bubble svecīte', 3),
                    p('Mūsdienīga burbuļu forma — stilīgs akcents un oriģināla dāvana, ko viesi noteikti atcerēsies.'),
                    p('<strong>no 3 € / gab.</strong>'),
                    btns([('Izvēlēties →', CFG, False)], center=False)], 'mm-card')],
        ]),
    ], 'mm-wed mm-section'),

    group([
        h('Cenas', 2, 'center'),
        table([['10–19 gab.', '5,00 €'], ['20–29 gab.', '4,50 €'], ['30–49 gab.', '4,00 €'], ['50–99 gab.', '3,50 €'], ['100+ gab.', '3,00 €']], ['Daudzums', 'Cena par gabalu']),
        p('Lentīte, caurspīdīgs iepakojums un personalizācija ir iekļauta cenā. Abiem sveču veidiem cena ir vienāda.', 'center'),
    ], 'mm-wed mm-section'),

    group([
        h('Personalizācija: birka vai uzlīme', 2, 'center'),
        p('Uz birkas vai uzlīmes var būt Jūsu vārdi, kāzu datums, pateicības teksts vai monogramma. Dizainu pieskaņojam Jūsu ielūgumiem un kāzu krāsām, un <strong>pirms izgatavošanas atsūtām maketu apstiprināšanai</strong>.', 'center'),
        h('Uzlīme', 3),
        p('Apaļa uzlīme ar individuālu dizainu — konstruktorā varat pievienot ielūgumu vai iedvesmas attēlu.'),
        g_sticker,
        h('Birka', 3),
        p('Birka ar tekstu, piesieta ar lentīti — īpaši svinīgs un romantisks noformējums.'),
        g_tag,
        p('Lentes krāsas: sarkana, tumši zaļa, balta, melna, lavandas, zelta, oranža, rozā — vai cita pēc Jūsu vēlmēm.', 'center'),
    ], 'mm-wed mm-section'),

    group([
        h('Kā notiek pasūtījums', 2, 'center'),
        steps_html,
        btns([('Izveidot un pasūtīt →', CFG, False)]),
    ], 'mm-wed mm-section'),

    group([
        h('Mūsu darbi', 2, 'center'),
        p('Katra kāzu svece tiek izgatavota individuāli. Iedvesmojieties no mūsu iepriekšējiem pasūtījumiem.', 'center'),
        g_works,
    ], 'mm-wed mm-section'),

    group([
        h('Kāpēc pāri izvēlas MarMar Candles', 2, 'center'),
        ul([
            '<strong>Roku darbs Latvijā</strong> — katru sveci darinām ar rokām mūsu darbnīcā Rīgā.',
            '<strong>Dizains tieši Jums</strong> — uzlīmi vai birku veidojam pēc Jūsu ielūguma, stila un krāsām.',
            '<strong>Ilgāk par saldumiem</strong> — svece paliek viesiem kā piemiņa par Jūsu dienu ilgi pēc svinībām.',
            '<strong>Der jebkuram stilam</strong> — klasiskām, boho, rustic, minimālisma un modernām kāzām.',
        ]),
    ], 'mm-wed mm-section'),

    group([h('Biežāk uzdotie jautājumi', 2, 'center')] + [details(q, a) for q, a in faq], 'mm-wed mm-section mm-faq'),

    group([
        h('Ir jautājumi vai īpaša ideja?', 2, 'center', anchor='pieteikums'),
        p('Ja vēlaties vairāk nekā 100 sveču, nestandarta dizainu vai vienkārši vēlaties pārrunāt ideju — uzrakstiet mums, un drīz atbildēsim.', 'center'),
        form,
    ], 'mm-wed mm-section'),
]

print('\n\n'.join(out))
