#!/usr/bin/env python3
"""Обновление страницы /kazu-sveces/ (14.11.2026): одно настоящее фото вместо карусели, честная цена,
данные о свечах, отзывы невест, крупный просмотр фото, новые вопросы + разметка FAQ для Google.

Запуск: python3 kazu_update_2026_11.py backups/2026-11-14/page-kazu-sveces.json > kazu-sveces.blocks.html
(на входе — сохранённая страница из REST API, поле content.raw)."""
import json, re, sys, html

raw = json.load(open(sys.argv[1]))['content']['raw']
CFG = 'https://marmarcandles.com/product/personalizetas-kazu-sveces-viesiem-izveidojiet-savas/'
HERO = ('https://marmarcandles.com/wp-content/uploads/2026/11/kazu-sveces-viesiem-zala-lente-birka.webp', 28173,
        'Kāzu svece viesiem caurspīdīgā kastītē ar tumši zaļu lentīti un birku ar pateicības tekstu')
WA = 'https://wa.me/37129710414?text=' + 'Labdien!%20Man%20ir%20jaut%C4%81jums%20par%20k%C4%81zu%20sv%C4%93c%C4%93m%20viesiem.'


def top_blocks(src):
    depth, out = 0, []
    for m in re.finditer(r'<!-- (/?)wp:([a-z0-9/-]+)([^>]*?)(/?)-->', src):
        if m.group(1):
            depth -= 1
            if depth == 0:
                out[-1][2] = m.end()
        else:
            if depth == 0:
                out.append([m.group(2), m.start(), m.end()])
            if not m.group(4):
                depth += 1
    return [[n, src[s:e]] for n, s, e in out]


def must_replace(s, old, new, count=1):
    assert s.count(old) >= count, (old[:60], s.count(old))
    return s.replace(old, new)


def p(text, center=False, cls=None):
    attrs, classes = {}, []
    if center:
        attrs['style'] = {'typography': {'textAlign': 'center'}}
        classes.append('has-text-align-center')
    if cls:
        attrs['className'] = cls
        classes.append(cls)
    a = (' ' + json.dumps(attrs, ensure_ascii=False)) if attrs else ''
    c = f' class="{" ".join(classes)}"' if classes else ''
    return f'<!-- wp:paragraph{a} -->\n<p{c}>{text}</p>\n<!-- /wp:paragraph -->'


def h2(text, anchor=None):
    attrs = {'style': {'typography': {'textAlign': 'center'}}}
    if anchor:
        attrs['anchor'] = anchor
    idattr = f' id="{anchor}"' if anchor else ''
    return (f'<!-- wp:heading {json.dumps(attrs, ensure_ascii=False)} -->\n'
            f'<h2 class="wp-block-heading has-text-align-center"{idattr}>{text}</h2>\n<!-- /wp:heading -->')


def btn(text, url=CFG):
    return ('<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->\n<div class="wp-block-buttons">'
            '<!-- wp:button {"className":"mm-cta-primary"} -->\n<div class="wp-block-button mm-cta-primary">'
            f'<a class="wp-block-button__link wp-element-button" href="{url}">{text}</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons -->')


def group(content, cls):
    return (f'<!-- wp:group {{"className":"{cls}","layout":{{"type":"constrained"}}}} -->\n<div class="wp-block-group {cls}">'
            + '\n\n'.join(content) + '</div>\n<!-- /wp:group -->')


def details(q, a):
    return f'<!-- wp:details -->\n<details class="wp-block-details"><summary>{q}</summary>' + p(a) + '</details>\n<!-- /wp:details -->'


blocks = top_blocks(raw)
names = [n for n, _ in blocks]
assert names == ['html', 'stackable/carousel'] + ['group'] * 9, names
css, carousel, hero, choose, prices, pers, steps, works, why, faq_g, form_g = [b for _, b in blocks]

# ---------- стили ----------
css = must_replace(css, '</style>', '''.mm-hero{align-items:center;gap:32px}
.mm-hero-img img{border-radius:18px;width:100%;max-height:600px;object-fit:cover;aspect-ratio:4/5}
.mm-facts{font-size:14px;color:#6f6468}
.mm-reviews{display:grid;gap:12px;max-width:640px;margin:0 auto}
.mm-reviews blockquote{margin:0;padding:14px 18px;border:0;border-left:3px solid #c88a85;border-radius:0 12px 12px 0;background:#fff}
.mm-reviews blockquote p{margin:0;font-style:normal}
.mm-wed .wp-block-gallery .wp-block-image{cursor:zoom-in}
@media(max-width:781px){.mm-hero{gap:18px}.mm-hero-img img{aspect-ratio:1/1;max-height:none}}
</style>''')

# ---------- первый экран: одно настоящее фото + текст (вместо карусели) ----------
inner = hero[hero.index('<!-- wp:heading'):hero.rindex('</div>')]
inner = must_replace(inner, '<strong>No 3 € par gabalu, no 10 sveču.</strong>',
                     '<strong>3–5 € par gabalu, no 10 svecēm — jo vairāk, jo izdevīgāk.</strong>')
url, iid, alt = HERO
hero = (
    '<!-- wp:group {"className":"mm-wed","layout":{"type":"constrained"}} -->\n<div class="wp-block-group mm-wed">'
    '<!-- wp:columns {"verticalAlignment":"center","className":"mm-hero"} -->\n'
    '<div class="wp-block-columns are-vertically-aligned-center mm-hero">'
    '<!-- wp:column {"verticalAlignment":"center","width":"42%"} -->\n'
    '<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:42%">'
    f'<!-- wp:image {{"id":{iid},"sizeSlug":"full","linkDestination":"none","className":"mm-hero-img"}} -->\n'
    f'<figure class="wp-block-image size-full mm-hero-img"><img src="{url}" alt="{html.escape(alt)}" class="wp-image-{iid}"/></figure>\n'
    '<!-- /wp:image --></div>\n<!-- /wp:column -->\n\n'
    '<!-- wp:column {"verticalAlignment":"center"} -->\n<div class="wp-block-column is-vertically-aligned-center">'
    + inner +
    '</div>\n<!-- /wp:column --></div>\n<!-- /wp:columns --></div>\n<!-- /wp:group -->'
)

# ---------- карточки свечей: честная цена + данные о свече ----------
choose = must_replace(choose, '<p><strong>no 3 € / gab.</strong></p>', '<p><strong>3–5 € / gab.</strong></p>', 2)
choose = must_replace(
    choose, '<p>Eleganta klasika: svece mazā stikla burciņā ar baltu vāciņu. Lieliski izskatās uz svētku galda.</p>',
    '<p>Eleganta klasika: svece mazā stikla burciņā ar baltu vāciņu. Lieliski izskatās uz svētku galda.<br>'
    '<span class="mm-facts">Deg apmēram 1 stundu · dabīgs vasks, bez aromāta</span></p>')
choose = must_replace(
    choose, '<p>Mūsdienīga burbuļu forma — stilīgs akcents un oriģināla dāvana, ko viesi noteikti atcerēsies.</p>',
    '<p>Mūsdienīga burbuļu forma — stilīgs akcents un oriģināla dāvana, ko viesi noteikti atcerēsies.<br>'
    '<span class="mm-facts">Ap 3 × 4 cm · deg apmēram 1 stundu · dabīgs vasks, bez aromāta</span></p>')

# ---------- цены: пример расчёта и доставка ----------
prices = must_replace(
    prices, 'Abiem sveču veidiem cena ir vienāda.</p>',
    'Abiem sveču veidiem cena ir vienāda.<br><strong>Piemēram, 50 viesiem — 175 €.</strong> '
    'Piegāde uz Unisend vai Venipak pakomātu — bez maksas.</p>')
prices = prices[:prices.rindex('</div>')] + '\n\n' + btn('Aprēķināt savu cenu →') + '</div>\n<!-- /wp:group -->'

# ---------- шаги ----------
steps = must_replace(steps, '<strong>Saņemiet rēķinu</strong> e-pastā un apmaksājiet to.',
                     '<strong>Apmaksājiet</strong> uzreiz internetbankā vai ar pārskaitījumu pēc rēķina.')


# ---------- галереи: крупный просмотр по нажатию, подписи для поиска, без старой анимации ----------
ALT_FALLBACK = ['Personalizēta kāzu svece viesiem ar uzlīmi', 'Kāzu svece viesiem ar birku un lentīti',
                'Kāzu sveces viesiem dāvanu kastītēs ar lentīti', 'Mini sveces kāzu viesiem ar personalizētu dizainu']
_alt_i = [0]


def fix_gallery_images(src):
    def img_json(m):
        attrs = json.loads(m.group(1))
        attrs.pop('animationsForBlocks', None)
        attrs['lightbox'] = {'enabled': True}
        return '<!-- wp:image ' + json.dumps(attrs, ensure_ascii=False, separators=(',', ':')) + ' -->'

    def gal_json(m):
        attrs = json.loads(m.group(1))
        attrs.pop('animationsForBlocks', None)
        return '<!-- wp:gallery ' + json.dumps(attrs, ensure_ascii=False, separators=(',', ':')) + ' -->'

    def empty_alt(_m):
        a = ALT_FALLBACK[_alt_i[0] % len(ALT_FALLBACK)]
        _alt_i[0] += 1
        return f'alt="{a}"'

    src = re.sub(r'<!-- wp:image (\{.*?\}) -->', img_json, src)
    src = re.sub(r'<!-- wp:gallery (\{.*?\}) -->', gal_json, src)
    src = re.sub(r' data-aos="[^"]*"', '', src)
    return re.sub(r'alt=""', empty_alt, src)


pers = fix_gallery_images(pers)
works = fix_gallery_images(works)
works = works[:works.rindex('</div>')] + '\n\n' + btn('Izveidot savas sveces →') + '</div>\n<!-- /wp:group -->'

# ---------- отзывы невест (вместо общего списка «Kāpēc pāri izvēlas») ----------
REVIEWS = [
    'Vienkārši nevarēju nociesties pateikt milzīgu PALDIES par ko tik skaistu, īpašu un smalku! Tā izcili iederēsies mūsu svinībās.',
    'Liels paldies, dāvaniņas izskatās burvīgi!',
    'Sveiki. Šodien saņēmām svecītes. Superīgas! Paldies!',
]
quotes = '\n\n'.join(
    '<!-- wp:quote -->\n<blockquote class="wp-block-quote">' + p(f'«{r}»') + '</blockquote>\n<!-- /wp:quote -->'
    for r in REVIEWS)
reviews = group([
    h2('Ko saka līgavas, saņemot pasūtījumu'),
    group([quotes], 'mm-reviews'),
    p('Roku darbs Latvijā: katru sveci lejam ar rokām mūsu darbnīcā Rīgā. Svece paliek viesiem kā piemiņa par Jūsu dienu — ilgāk par saldumiem.', center=True),
], 'mm-wed mm-section')

# ---------- вопросы и ответы + разметка для Google ----------
FAQ = [
    ('Cik maksā personalizētas kāzu sveces?', 'Cena par gabalu atkarīga no daudzuma: 10–19 gab. — 5 €, 20–29 — 4,50 €, 30–49 — 4 €, 50–99 — 3,50 €, no 100 gab. — 3 €. Piemēram, 50 viesiem — 175 €. Lentīte, caurspīdīgs iepakojums un personalizācija (birka vai uzlīme) ir iekļauta cenā.'),
    ('Kāds ir minimālais pasūtījums?', '10 sveces. Lielākiem daudzumiem cena par gabalu samazinās.'),
    ('Cik laicīgi jāpasūta?', 'Iesakām pasūtīt <strong>4–8 nedēļas pirms kāzām</strong>. Maketu atsūtām 3 darba dienu laikā pēc apmaksas, izgatavošana aizņem ap 2 nedēļām pēc maketa apstiprināšanas. Konstruktorā varat norādīt vēlamo saņemšanas datumu — vadīsimies pēc tā.'),
    ('Cik lielas ir sveces un cik ilgi tās deg?', 'Mini Bubble svecīte ir ap 3 × 4 cm, svece burciņā — 20 ml. Abas deg apmēram 1 stundu. Sveces lejam no dabīga vaska, bez aromāta — tās netraucē ne ēdienam, ne ziediem uz svētku galda.'),
    ('Vai dizainu var pieskaņot mūsu ielūgumiem?', 'Jā. Konstruktorā pievienojiet ielūgumu, gatavu dizainu vai iedvesmas attēlu — uzlīmes dizainu izveidosim Jūsu kāzu stilā un krāsās.'),
    ('Ko var uzrakstīt uz birkas vai uzlīmes?', 'Jūsu vārdus, kāzu datumu, pateicības tekstu viesiem, iniciāļus vai monogrammu.'),
    ('Kādas lentes krāsas ir pieejamas?', 'Sarkana, tumši zaļa, balta, melna, lavandas, zelta, oranža un rozā. Ja vēlaties citu krāsu, aprakstiet to komentārā.'),
    ('Kā notiek piegāde un cik tā maksā?', 'Sūtām uz Omniva, DPD, Venipak un Unisend pakomātiem visā Latvijā, kā arī uz Lietuvu un Igauniju. Kāzu sveču pasūtījumiem piegāde uz Unisend vai Venipak pakomātu Latvijā ir bez maksas. Pasūtījumu var arī saņemt mūsu darbnīcā Rīgā, Iļģuciemā.'),
    ('Vai sveces var pasūtīt arī kristībām, jubilejai vai uzņēmuma pasākumam?', 'Jā. Tās pašas sveces ar birku vai uzlīmi izgatavojam arī kristībām, jubilejām, izlaidumiem un korporatīviem pasākumiem — konstruktorā ierakstiet vēlamo tekstu.'),
    ('Vai varam pasūtīt vairāk nekā 100 sveču vai īpašu dizainu?', 'Jā. Lielus un nestandarta pasūtījumus izgatavojam regulāri — uzrakstiet mums zemāk esošajā formā, un sagatavosim piedāvājumu.'),
]
strip = lambda t: re.sub(r'<[^>]+>', '', t)
ld = {'@context': 'https://schema.org', '@type': 'FAQPage', 'mainEntity': [
    {'@type': 'Question', 'name': q, 'acceptedAnswer': {'@type': 'Answer', 'text': strip(a)}} for q, a in FAQ]}
faq_g = group(
    [h2('Biežāk uzdotie jautājumi')] + [details(q, a) for q, a in FAQ]
    + ['<!-- wp:html -->\n<script type="application/ld+json">' + json.dumps(ld, ensure_ascii=False) + '</script>\n<!-- /wp:html -->'],
    'mm-wed mm-section mm-faq')

# ---------- форма вопросов: + WhatsApp, + ссылки на свечи для торта ----------
form_g = must_replace(
    form_g, 'uzrakstiet mums, un drīz atbildēsim.</p>',
    f'uzrakstiet mums šeit vai <a href="{WA}" target="_blank" rel="noreferrer noopener">WhatsApp</a>, un drīz atbildēsim.</p>')
more = group([p(
    'Meklējat sveces arī kāzu tortei? Skatiet <a href="https://marmarcandles.com/lv/product-category/tortes-sveces/">tortes sveces</a> '
    'un <a href="https://marmarcandles.com/lv/product-category/kuku-toperi/">kūku toperus</a>.', center=True)], 'mm-wed mm-section')

print('\n\n'.join([css, hero, choose, prices, pers, steps, works, reviews, faq_g, form_g, more]))
