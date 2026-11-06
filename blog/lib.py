"""Tiny helpers to write WordPress block markup for MarMar CANDLES blog posts."""
B = "https://marmarcandles.com/lv"
U = "https://marmarcandles.com/wp-content/uploads"


def p(t):
    return f"<!-- wp:paragraph -->\n<p>{t}</p>\n<!-- /wp:paragraph -->\n\n"


def h2(t):
    return f'<!-- wp:heading -->\n<h2 class="wp-block-heading">{t}</h2>\n<!-- /wp:heading -->\n\n'


def h3(t):
    return f'<!-- wp:heading {{"level":3}} -->\n<h3 class="wp-block-heading">{t}</h3>\n<!-- /wp:heading -->\n\n'


def ul(items):
    li = "".join(f"<!-- wp:list-item -->\n<li>{i}</li>\n<!-- /wp:list-item -->\n\n" for i in items)
    return f'<!-- wp:list -->\n<ul class="wp-block-list">{li}</ul>\n<!-- /wp:list -->\n\n'


def img(media_id, src, alt, link):
    return (f'<!-- wp:image {{"id":{media_id},"sizeSlug":"large","linkDestination":"custom"}} -->\n'
            f'<figure class="wp-block-image size-large"><a href="{link}"><img src="{src}" alt="{alt}" '
            f'class="wp-image-{media_id}"/></a></figure>\n<!-- /wp:image -->\n\n')


def a(path, text):
    return f'<a href="{B}{path}">{text}</a>'


def faq(pairs):
    out = h2("Biežāk uzdotie jautājumi")
    for q, ans in pairs:
        out += h3(q) + p(ans)
    return out


ORDER_DEADLINES = ul([
    "<strong>Parasts pasūtījums:</strong> izgatavošana un nosūtīšana 5–7 darba dienās + piegāde uz pakomātu parasti "
    "1–5 darba dienas. Lai paspētu līdz Ziemassvētkiem, iesaku pasūtīt <strong>līdz 8. decembrim</strong>.",
    "<strong>Steidzams pasūtījums (+5 €):</strong> izgatavoju līdz 2 darba dienām. Iesaku pasūtīt "
    "<strong>līdz 15. decembrim</strong>.",
    "Piegāde visā Latvijā – Omniva, DPD, Venipak un Unisend pakomāti vai kurjers; pasūtījumu var saņemt arī manā "
    "darbnīcā Iļģuciemā, Rīgā.",
])
