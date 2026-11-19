"""Pinterest bulk-upload CSV plan for jalina.art, 19.11–18.12.2026 (Alina uploads it via Settings → Import content).

  python3 build_csv.py            -> jalina-2026-11-19_12-02.csv, jalina-2026-12-03_12-18.csv, plan.json, PLAN.md
  python3 check_csv.py *.csv      -> rule checker

Rules (Alina, Nov 2026): jalina.art pins/day 19.11 ≤3, 20–22.11 ≤5, from 23.11 ≤8, counting pins already scheduled in
Metricool (EXISTING below, read 15.11.2026, non-draft Pinterest only); + 3–4 Payhip pins of own sets/day; total ≤15/day;
slots every 20 min 15:00–23:00 Riga (= 13:00–21:00 UTC, Riga is UTC+2 in Nov/Dec); one jalina.art URL ≤1/day and ≤3 per
7 days, never two days in a row; an image is not reused within 3 days (≥4 days apart); unique descriptions ≤500 chars,
titles ≤100. Images: v2/article.py (A/B/C alternate per post) and v2/gen.py R --variant N (own sets).
"""
import csv, datetime as dt, json, os, random, itertools

HERE = os.path.dirname(os.path.abspath(__file__))
D0, D1 = dt.date(2026, 11, 19), dt.date(2026, 12, 18)
SPLIT = dt.date(2026, 12, 3)  # file 2 starts here (Pinterest schedules at most 30 days ahead)
MEDIA = 'https://jalina.art/pins/csv/'
COLS = ['Title', 'Media URL', 'Pinterest board', 'Thumbnail', 'Description', 'Link', 'Publish date', 'Keywords']
B_GUIDE = 'Fonts & Clipart for Canva and Cricut'
B_CUTE = 'Cute Clipart PNG for Crafts'
B_FONTS = 'Canva Fonts & Font Pairings'
B_HOL = 'Holiday Clipart & Fonts'
B_OWN = 'Watercolor Birthday & Holiday Clipart'


def d(m, day):
    return dt.date(2026, m, day)


# Pinterest pins already scheduled in Metricool (brand 7764143), Riga time, read 15.11.2026 day by day 19.11–02.12.
EXISTING = {
    d(11, 19): [('15:00', '/christmas-elf-clipart/'), ('15:40', '/christmas-village-clipart/'), ('16:20', '/christmas-planner-stickers/'),
                ('17:00', '/christmas-card-fonts/'), ('17:40', '/retro-christmas-png/'), ('19:00', '/shop/moody-florals/'),
                ('20:20', '/shop/vintage-christmas/'), ('15:00', '/shop/hanukkah/')],
    d(11, 21): [('10:00', '/shop/new-years-eve/'), ('15:00', '/christmas-penguin-clipart/'), ('15:40', '/christmas-truck-clipart/'),
                ('16:20', '/christmas-cat-clipart/'), ('17:00', '/christmas-coloring-pages-printable/'), ('17:40', '/candy-cane-clipart/')],
    d(11, 22): [('15:00', '/christmas-elf-clipart/'), ('15:40', '/christmas-village-clipart/'), ('16:20', '/christmas-planner-stickers/'),
                ('17:00', '/christmas-card-fonts/'), ('17:40', '/retro-christmas-png/')],
    d(11, 24): [('15:00', '/shop/lunar-new-year/')],
}


PAYHIP_ONLY = os.environ.get('PAYHIP_ONLY') == '1'  # Alina 19.11: jalina.art blocked by Pinterest (spam) -> Payhip pins only
if PAYHIP_ONLY:
    EXISTING = {}  # all Metricool jalina.art pins 19.11-02.12 moved to draft on 19.11.2026
    D0 = dt.date(2026, 11, 20)


def jal_cap(day):
    if PAYHIP_ONLY:
        return 0
    return 3 if day == d(11, 19) else 5 if day <= d(11, 22) else 8


# ---------------------------------------------------------------- article / page pins
# slug -> dict(path, w(day)->weight or 0 when out of season, boards, titles, hooks, details, kw)
def season(start, end, w, peak=None):
    def f(day):
        if not (start <= day <= end):
            return 0
        if peak and peak[0] <= day <= peak[1]:
            return w * peak[2]
        return w
    return f


XMAS_END = d(12, 15)
POSTS = {
 'png-not-transparent': dict(w=season(D0, D1, 3), boards=[B_GUIDE, B_CUTE],
  titles=['PNG Not Transparent? Spot a Fake Checkerboard', 'How to Tell if a PNG Is Really Transparent', 'Fake Transparent PNG: How to Check and Fix It'],
  hooks=['Bought clipart that shows a grey-and-white checkerboard in Canva? That pattern is painted into the pixels, not real transparency.',
         "A checkerboard in a shop preview doesn't prove a PNG is transparent: some files have it painted right into the image.",
         "Before you pay for a 'transparent PNG', there's a quick test that tells you if the background is real or painted.",
         'White fringe around your design on a dark shirt? That is a halo, the second transparency problem after fake checkerboards.',
         'If a PNG looks fine on white but goes wrong in Canva or Cricut Design Space, the background may never have been transparent.'],
  details=['My guide shows how to spot a fake in 10 seconds, how to fix it for free or in Canva and Cricut, and when to ask for a refund.',
           "Inside: why it happens, a free fix and a Canva fix, how to check for white halos on dark backgrounds, and a buyer's checklist.",
           'I explain it with my own watercolor Christmas clipart: the same element on a painted checkerboard and on a real transparent background.',
           "Also what to do when a fake can't be fixed well, and a short checklist to run before you buy any clipart set.",
           'Useful for Canva, Cricut Print Then Cut and sublimation: check the file once, before it goes on a product.'],
  kw='transparent png, png not transparent, fake transparent background, canva, cricut print then cut'),
 'clipart-blurry-when-printed': dict(w=season(D0, D1, 3), boards=[B_GUIDE, B_CUTE],
  titles=['Why Your Clipart Prints Blurry (DPI Explained)', 'Clipart Blurry When Printed? Pixels vs DPI', 'How Big Can Your Clipart Print? A DPI Guide'],
  hooks=['Your clipart looked sharp on screen and printed soft? Usually it is the pixels, not your printer.',
         "DPI is just a label inside the file: changing 72 to 300 DPI doesn't add a single pixel.",
         'Print size in inches = pixels ÷ DPI. That one formula explains most blurry prints.',
         'A 3600 px file prints 12 inches wide at 300 DPI, and 24 inches at 150 DPI with bigger, softer pixels.',
         'A 500 px image is fine for a tiny gift tag motif, but stretched across a 5-inch card it drops to 100 DPI.'],
  details=["My guide covers the DPI each project needs, 8 causes of blur that aren't resolution, when upscaling helps, and a 6-step checklist.",
           'Includes a pixels-to-inches table, Cricut Print Then Cut scaling and what a 20 oz tumbler wrap needs (about 2790 × 2460 px).',
           'With a side-by-side print simulation from my own watercolor clipart: 3600 px vs 500 px at the same size.',
           'Read it before you print stickers, tags, cards or shirts, and try the free print size calculator for your exact file.',
           'Also the sneaky causes: JPG compression, Canva export settings, printer draft mode, the wrong paper setting and saved previews.'],
  kw='dpi, print size, blurry print, 300 dpi, clipart printing, cricut print then cut'),
 'free-fonts-for-commercial-use': dict(w=season(D0, D1, 3), boards=[B_FONTS, B_GUIDE],
  titles=['Free Fonts for Commercial Use: How to Check', 'Is That Free Font OK for Products You Sell?', 'Free Fonts for Cricut & Canva Sellers: Licences'],
  hooks=["'Free font' can mean free to download, free to try or free to use on things you sell. Only the last one matters for products.",
         "Selling shirts, stickers or printables? The big FREE button on a font site doesn't tell you if commercial use is allowed.",
         "OFL, Apache, 'free for personal use', demo: what each font licence means, in plain English.",
         'Before a free font goes on something you sell, run a quick 5-step licence check.',
         'Fonts inside Canva and Cricut Design Space follow their own rules, separate from the font\'s original licence.'],
  details=['My guide explains the licence types, Canva and Cricut font rules, a 5-step check and 10 free fonts I checked for commercial use.',
           'Includes 10 checked free fonts (SIL OFL) in script, handwritten, serif and sans styles, with sample text.',
           'Plus red flags, like a font from a random free-fonts site with no licence file at all.',
           'Covers what OFL and Apache 2.0 allow, why personal-use and demo fonts are a no for products, and how to save proof of the licence.',
           'Handy for Cricut crafters, Canva template sellers and anyone printing shirts, mugs or stickers to sell.'],
  kw='free fonts for commercial use, font licence, ofl fonts, canva fonts, cricut fonts'),
 'my-art-sold-on-temu': dict(w=season(D0, D1, 2), boards=[B_GUIDE],
  titles=['I Found My Etsy Wall Art on Temu', 'Is Your Art Sold on Temu? How to Check', 'My Printables Were Copied: What I Did Next'],
  hooks=['From 2020 I sold printable nail salon wall art on Etsy. Years later I found the same designs as canvas prints on Temu and AliExpress.',
         "My Etsy shop reached 400 sales and the Star Seller badge. Now my old designs hang in homes as marketplace canvas prints, and I don't get paid.",
         "Sell printables or illustrations? Here's how I found copies of my designs, and how you can check yours.",
         'Copies of digital art often turn up as canvas prints on big marketplaces. Checking takes a few minutes.',
         'Finding your own artwork on Temu is a shock. What helps is calm documentation, not panic.'],
  details=['I share how I found the copies, how to document them with dates, what the law generally says and your realistic options.',
           'A practical guide for Etsy printable sellers and illustrators: how to search, how to document a copy, and what comes next.',
           'With my original Instagram posts from 2021 and 2022 as proof of dates, and where I am with it now.',
           'Includes a step-by-step way to document a copy and a plain overview of your options.',
           'Read it if you sell wall art, clipart or printables online and want to be ready if it happens to you.'],
  kw='art theft, temu copies, etsy printables, copyright for artists, printable wall art'),
 'tools/print-size-calculator': dict(w=season(D0, D1, 3), boards=[B_GUIDE, B_CUTE],
  titles=['Free Print Size & DPI Calculator', 'Pixels to Inches: How Big Can Your PNG Print?', 'Print Size Calculator for Clipart & Stickers'],
  hooks=['How big can your PNG print? Type in the pixels and see the size at 300, 240, 200 or 150 DPI.',
         'Need the pixels for a tumbler wrap, a Print Then Cut sticker or 8×10 wall art? A free calculator does the math.',
         'A free pixels-to-inches calculator for crafters, no sign-up needed.',
         'Pixels ÷ DPI = inches. The free calculator works both ways: print size from pixels, or pixels for a size.',
         'Stop guessing whether your clipart is big enough for that mug, shirt or sticker sheet.'],
  details=['Free tool on jalina.art, plus my guide to why clipart prints blurry if a file comes out too small.',
           'Works for tumbler wraps, Print Then Cut stickers, cards and 8×10 wall art.',
           'Handy before you buy clipart: check the pixel size and you know the real print size.',
           'Made for Canva, Cricut and sublimation makers who want sharp prints.',
           'Bookmark it next to your Canva and Design Space tabs.'],
  kw='print size calculator, dpi calculator, pixels to inches, 300 dpi, cricut print then cut'),
 'tools/png-transparency-checker': dict(w=season(D0, D1, 3), boards=[B_GUIDE, B_CUTE],
  titles=['Free PNG Transparency Checker', 'Is Your PNG Really Transparent? Free Check', 'Check Clipart for Fake Backgrounds & Halos'],
  hooks=['Is your PNG really transparent? The free checker spots fake checkerboard backgrounds and white halos.',
         'Drop in a PNG and preview it on black, white and dark backgrounds before it goes on a shirt or sticker.',
         'Painted checkerboards and pale halos ruin dark-shirt designs. Check the file before you print.',
         'A free transparency checker that runs in your browser: nothing is uploaded to a server.',
         "Bought a 'transparent' clipart set? Check a few files in seconds before you use them."],
  details=['Free tool on jalina.art, with my guide to fixing fake transparent PNGs if a file fails.',
           'Your file stays on your device; the check runs in the browser.',
           'Useful for Canva, Cricut Print Then Cut and sublimation projects on dark colors.',
           'Pairs with the free print size calculator, so you can check transparency and size in one go.',
           'A 10-second check that can save you a misprinted shirt or a sheet of stickers.'],
  kw='png transparency checker, transparent png, fake transparent background, clipart, sublimation'),
 'canva-font-pairings': dict(w=season(D0, D1, 2.5), boards=[B_FONTS],
  titles=['6 Canva Font Pairings That Stand Out', 'Canva Font Pairings: Statement + Free Font', 'Font Pairings for Canva: Retro, Elegant, Cute'],
  hooks=["6 Canva font pairings that don't look like everyone else's: a statement headline font plus a free Canva body font.",
         'Funky Groovy + Poppins, Marquis + Montserrat, Remla + Lato: pairings for retro, elegant and minimal designs.',
         'The quickest way to make a Canva design look less like a template is a better font pairing.',
         'Retro, elegant, cute and signature styles, each paired with a free font that is already in Canva.',
         'Pairing fonts is mostly about contrast. Here are 3 simple rules and 6 ready pairs.'],
  details=['My guide gives each pair with sizes and spacing tips, and shows how to add a Creative Fabrica font to Canva.',
           'Includes clipart that matches these pairings.',
           'Great for logos, shop banners, planners and social posts.',
           "Each pairing has a short 'best for' note.",
           'The body fonts are free in Canva; the headline fonts are uploaded.'],
  kw='canva font pairings, canva fonts, font combinations, branding fonts, canva tips'),
 'christmas-food-clipart': dict(w=season(D0, XMAS_END, 3), boards=[B_HOL, B_CUTE],
  titles=['Christmas Food Clipart: Gingerbread, Cookies & Cocoa', 'Gingerbread & Hot Cocoa Clipart PNG Sets', 'Christmas Cookie & Candy Cane Clipart Compared'],
  hooks=['Gingerbread, decorated cookies, candy canes and hot cocoa: 31 Christmas food clipart PNG sets compared in one place.',
         "Making bakery labels, a hot cocoa bar sign or cookie exchange invites? Here's which food clipart set fits which project.",
         'Watercolor gingerbread cuties, sugar cookies, candy canes with satin bows and cozy cocoa mugs, all PNG.',
         'Planning a hot cocoa bar this Christmas? Matching clipart makes the signs and tags look like one set.',
         'Christmas food clipart is everywhere in December: bakery boxes, cards, shirts and recipe cards.'],
  details=['My guide groups the sets by food, with picks for labels, cards and shirts, and what goes wrong when printing food clipart.',
           'Includes project ideas that combine sets, and a short FAQ.',
           "Every pick has a short note on what it's best for, so you don't have to open 31 product pages.",
           'Also how to choose food clipart that prints well, plus my own bakery clipart.',
           'One hub instead of four lists: gingerbread, cookies, candy canes and hot cocoa side by side.'],
  kw='christmas food clipart, gingerbread clipart, hot cocoa clipart, candy cane clipart, christmas cookie clipart'),
 'christmas-gift-tags-printable': dict(w=season(D0, XMAS_END, 3), boards=[B_HOL, B_GUIDE],
  titles=['Printable Christmas Gift Tags: 8 Sets', 'Christmas Gift Tags to Print, Edit or Cut', 'DIY Christmas Gift Tags: Print & Cut Tips'],
  hooks=['8 printable Christmas gift tag sets: vintage puppies, woodland babies, snowmen, pink tags, plaid and wooden name tags.',
         'Wrapping season! Print your own Christmas gift tags at home, edit them in Canva or cut them with Print Then Cut.',
         'From 168 tag templates to 8 laser-cut wooden name tags: Christmas gift tags for every way of making.',
         'Gift tags are the easiest Christmas printable: a 3.5-inch tag needs only about 1050 px at 300 DPI.',
         'Matching tags make even simple brown-paper wrapping look planned.'],
  details=['My guide covers tag size and cardstock, laying out a sheet in Canva, printing, punching and cutting.',
           'Plus which fonts work for names on tags, and the common mistakes with printable tags.',
           'Each set has a short note on who it suits, so you can pick fast.',
           'Includes Print Then Cut tips and a short FAQ.',
           'Also my own watercolor clipart if you want to design your own tags.'],
  kw='christmas gift tags, printable gift tags, diy gift tags, cricut print then cut, christmas printables'),
 'christmas-digital-paper': dict(w=season(D0, XMAS_END, 2.5), boards=[B_HOL, B_GUIDE],
  titles=['Christmas Digital Paper: 7 Seamless Packs', 'Seamless Christmas Patterns for Gift Wrap', 'Christmas Digital Paper for Cards & Planners'],
  hooks=['7 seamless Christmas digital paper packs: elegant plaid, watercolor, vintage, modern, Nordic forest, pink and navy-and-gold.',
         'Print your own Christmas gift wrap, card backgrounds and scrapbook pages with seamless digital paper.',
         "Not every 'seamless' pattern repeats cleanly. Here's how to test it before you print a big sheet.",
         'Pink Christmas, Nordic forest or moonlit navy and gold? Pick a paper pack by the project, not just the preview.',
         'Seamless papers tile without visible joins, so they work for wrap, tumblers and planner pages.'],
  details=['My guide explains seamless vs not, size, DPI and JPG vs PNG, then compares 7 packs.',
           'Includes how to test a seamless repeat, how to use the papers, printing tips and common mistakes.',
           'Each pack has a note on what it is best for.',
           'Useful for Canva, Cricut and sublimation makers.',
           'Includes a FAQ and the most common digital paper mistakes.'],
  kw='christmas digital paper, seamless pattern, christmas patterns, digital paper pack, gift wrap printable'),
 'christmas-planner-stickers': dict(w=season(D0, XMAS_END, 2.5), boards=[B_CUTE, B_HOL],
  titles=['Printable Christmas Planner Stickers: 6 Sets', 'Christmas Planner Stickers for Print Then Cut', 'Cute Christmas Stickers for Your Planner'],
  hooks=['6 printable Christmas planner sticker sets, from 64 winter stickers sized for Cricut to cute Santa and reindeer.',
         'Plan December in style: pastel stickers, washi strips and stickers for digital planners.',
         "Print Then Cut your own Christmas planner stickers at home: here's what to check first.",
         'Functional stickers (labels, checklists, dates) plus cute deco stickers for your December spreads.',
         'Sticker sheets for Christmas planning: gift lists, baking days and countdowns.'],
  details=['My guide compares 6 sets and explains how to print and cut planner stickers.',
           'Also how to make functional planner stickers from clipart and fonts, and the common mistakes.',
           'Sets include 64, 26, 24 and 30 stickers, plus washi strips and a holiday sticker sheet.',
           'With tips on sticker paper and Print Then Cut sizes.',
           'Includes my own clipart for stickers if you want to design your own sheet.'],
  kw='christmas planner stickers, printable stickers, print then cut, planner stickers, digital planner stickers'),
 'christmas-fonts-for-canva': dict(w=season(D0, XMAS_END, 3), boards=[B_FONTS, B_HOL],
  titles=['Christmas Fonts for Canva: 6 Festive Scripts', 'Christmas Script Fonts + the Canva Swash Trick', 'How to Use Swash Fonts in Canva at Christmas'],
  hooks=['6 Christmas script fonts that look beautiful in Canva: Adorable, Family, Wonderful, Sunday, Miracle and Jolly Christmas.',
         "Fancy swash letters don't show up in Canva by default. There's a trick to use them.",
         "Want Christmas cards and tags that don't use the same Canva fonts as everyone else?",
         "Uploading a font to your Canva Brand Kit takes a minute. Here's how, with 6 festive scripts to try.",
         'Script fonts make a Christmas design feel handmade, especially with their alternate letters.'],
  details=['My guide shows each font, which project it suits, how to add fonts to Canva and the swash trick.',
           'Includes a note on licences and clipart that pairs with these fonts.',
           'Short and practical, made for Canva users.',
           "Each font has a 'best for' note: cards, tags, mugs or shirts.",
           'Plus clipart to match the fonts in one design.'],
  kw='christmas fonts, canva fonts, christmas fonts for canva, script fonts, swash fonts'),
 'free-christmas-fonts': dict(w=season(D0, XMAS_END, 3), boards=[B_FONTS, B_HOL],
  titles=['8 Free Christmas Fonts (Commercial Use OK)', 'Free Christmas Fonts for Canva & Cricut', 'Truly Free Christmas Fonts, No Credit Card'],
  hooks=['8 truly free Christmas fonts from Google Fonts: no sign-up, no credit card, and commercial use is allowed.',
         'Free fonts you can actually use on things you sell this Christmas, under OFL or Apache licences.',
         'Mountains of Christmas, Great Vibes, Berkshire Swash, Dancing Script, Pacifico, Playball, Cookie and Fredoka.',
         'Script, cute and bold Christmas fonts you can use in Canva and Cricut for free.',
         "Many 'free' font offers need a trial or a card. These 8 don't."],
  details=['My list shows each font with a Christmas sample and how to use it in Canva.',
           'Includes clipart to go with these fonts, and a few less common ideas.',
           'All 8 are free for commercial use under OFL or Apache.',
           'A cheat sheet helps you pick the right one fast.',
           'Great for cards, tags, mugs and shirts.'],
  kw='free christmas fonts, free fonts, christmas fonts, canva fonts, cricut fonts'),
 'christmas-card-fonts': dict(w=season(D0, XMAS_END, 2.5), boards=[B_FONTS, B_HOL],
  titles=['Christmas Card Fonts: 7 Script Fonts', 'Script Fonts for Handmade Christmas Cards', 'Fonts for Christmas Cards & Greetings'],
  hooks=['7 Christmas script fonts for handmade cards: swashy calligraphy, a monoline script, a whimsical font and a snowy font duo.',
         'Making your own Christmas cards this year? The font does half the work.',
         'Elegant calligraphy for the front, a tall monoline script for modern cards, a playful font for kids.',
         'Richland Christmas, Christmas Greeting, Snow Christmas and more: fonts made for greetings.',
         'Cards look handmade but polished when the script font has real swashes.'],
  details=['My guide matches each font to a type of card and shares tips for fonts on cards.',
           'Includes my own Christmas clipart for cards.',
           "Each font has a short 'best for' note.",
           'Covers classic family cards, minimal cards and playful ones.',
           'Tips for fonts on cards included.'],
  kw='christmas card fonts, script fonts, christmas fonts, handmade cards, calligraphy fonts'),
 'christmas-stocking-name-fonts': dict(w=season(D0, XMAS_END, 2), boards=[B_FONTS],
  titles=['Stocking Name Fonts: 7 Christmas Fonts', 'Fonts for Names on Christmas Stockings', 'Christmas Eve Box Name Fonts'],
  hooks=['7 Christmas fonts for names on stockings and Christmas Eve boxes.',
         'Personalizing stockings with vinyl? Names need a font that stays readable when it is cut.',
         'Two Christmas Stocking fonts, swashy scripts, a chic serif and a bold block duo.',
         'Names are the star of a stocking. Pick a font that cuts cleanly and reads from across the room.',
         'Christmas Eve boxes and stockings for the whole family: one font, many names.'],
  details=['My guide matches fonts to stocking styles and shares tips for names.',
           'Includes how to install fonts and my own Christmas clipart for the box.',
           "Each font has a 'best for' note.",
           'Useful for Cricut and Canva projects.',
           'Tips for names on stockings included.'],
  kw='stocking name font, christmas fonts, christmas eve box, cricut fonts, personalized stockings'),
 'christmas-tumbler-wrap': dict(w=season(D0, XMAS_END, 2), boards=[B_GUIDE, B_HOL],
  titles=['Christmas Tumbler Wraps: 20 oz How-To', 'How to Sublimate a Christmas Tumbler', '8 Christmas Tumbler Wrap PNG Designs'],
  hooks=['Step by step: sublimate a 20 oz skinny tumbler with a Christmas wrap.',
         'Straight or tapered file? Mirrored or not? The tumbler wrap questions, answered.',
         '8 checked Christmas tumbler wraps: vintage embroidered, stained glass tree, storybook Santa, gingerbread and more.',
         'A 20 oz skinny wrap is about 9.3 × 8.2 inches. Here is how to get it onto the tumbler cleanly.',
         'Handmade tumblers are a favorite Christmas gift. The wrap file makes or breaks it.'],
  details=['My guide covers straight vs tapered files, mirroring and seams, then lists 8 wraps.',
           'Includes my own clipart for tumblers.',
           'Each wrap has a note on who it suits as a gift.',
           'Made for sublimation beginners.',
           'With a short comparison to help you choose between wraps.'],
  kw='christmas tumbler wrap, sublimation tumbler, 20 oz tumbler, tumbler png, sublimation designs'),
 'coquette-christmas-clipart': dict(w=season(D0, XMAS_END, 2), boards=[B_CUTE, B_HOL],
  titles=['Coquette Christmas Clipart: 9 Bow Sets', 'How to Pick Coquette Christmas Bows', 'Pink & Red Coquette Bow Clipart PNG'],
  hooks=['Coquette Christmas clipart by color story: pink pearl bows, red and cream ribbons, gingham and bow trees.',
         'Bows everywhere: 9 coquette Christmas PNG sets compared.',
         'Pinkmas bows, red watercolor bows, a reindeer antler bow and a checkered Christmas lights bow.',
         "What makes clipart 'coquette'? Soft ribbons, pearls, pastels and a little vintage.",
         'Coquette bows on mugs, shirts and gift tags: pick a palette first.'],
  details=['My guide sorts the sets by color and suggests what each suits.',
           'Includes statement pieces like bow trees and novelty bows.',
           'Each set has a short note on style and use.',
           'Handy for sublimation shirts, stickers and cards.',
           'Starts with what coquette clipart is, then compares the 9 sets.'],
  kw='coquette christmas, coquette bow png, pink bow clipart, christmas bow clipart, coquette aesthetic'),
 'watercolor-christmas-clipart': dict(w=season(D0, XMAS_END, 2), boards=[B_HOL, B_CUTE],
  titles=['Watercolor Christmas Clipart: 8 Sets', 'Watercolor Christmas Clipart for Cards', 'Cozy Watercolor Christmas Clipart PNG'],
  hooks=['8 watercolor Christmas clipart sets in PNG: cozy animals, Santa & friends, a Christmas village, florals and vintage scenes.',
         'Soft watercolor clipart makes Christmas cards and prints look painted.',
         'Animals in Christmas sweaters, a watercolor village, poinsettia florals and Christmas foods.',
         "Watercolor needs a bit of care when printing. Here's which set to pick and how to print it.",
         'Christmas cards, tags and wall prints with gentle watercolor art.'],
  details=['My guide compares 8 sets and shares printing tips for watercolor.',
           'Includes how to use watercolor clipart in Canva.',
           'Includes my own watercolor Christmas sets.',
           "Each set has a 'best for' note.",
           'Good picks for cards and prints.'],
  kw='watercolor christmas clipart, christmas clipart png, watercolor clipart, christmas cards, christmas png'),
 'hanukkah-clipart': dict(w=season(D0, d(12, 10), 1.5, (D0, d(12, 4), 2.2)), boards=[B_HOL],
  titles=['Hanukkah Clipart PNG: 7 Sets', 'Blue & Gold Hanukkah Clipart Sets', 'Menorah & Dreidel Clipart PNG'],
  hooks=['7 Hanukkah clipart PNG sets in blue, silver and gold: menorahs, dreidels, gnomes and storybook art.',
         'Hanukkah 2026 runs December 4–12. Make cards, gift tags and invitations with these sets.',
         'Watercolor menorahs, a cute blue-and-white bundle, Hanukkah gnomes and a 43-piece watercolor set.',
         'Making Hanukkah gift tags or a party invite? Pick a clipart set by style.',
         'Eight nights of gifts need eight nights of tags.'],
  details=['My guide compares 7 sets with design and printing tips.',
           'Includes my own Festival of Lights Hanukkah clipart.',
           "Each set has a 'best for' note.",
           'Sets range from 26 to 43 PNG.',
           'Design tips for blue and gold included.'],
  kw='hanukkah clipart, menorah clipart, dreidel png, hanukkah printables, jewish holiday clipart'),
 'winter-wonderland-clipart': dict(w=season(d(11, 25), D1, 2, (d(12, 1), D1, 1.5)), boards=[B_CUTE, B_HOL],
  titles=['Winter Wonderland Clipart: 6 PNG Sets', 'Snowflake & Snowy Cottage Clipart PNG', 'Winter Animal & Snowflake Clipart Sets'],
  hooks=['6 winter wonderland clipart PNG sets: pink winter watercolor, crystal snowflakes, celestial animals and snowy cottages.',
         'Winter designs that last past Christmas: snowflakes, cottages and snowy animals.',
         'Jeweled snowflakes, a cute snowman set and fairy-tale winter cottages, all PNG.',
         'January needs graphics too: winter wonderland clipart without Santa.',
         'Make winter cards, invitations and planner pages with snowy watercolor clipart.'],
  details=['My guide compares 6 sets with design and printing tips.',
           'Includes my own Cozy Winter Wonderland clipart.',
           'Each set has a note on which project it suits.',
           'Sets range from 20 to 57 PNG.',
           'Design tips and printing tips included.'],
  kw='winter wonderland clipart, snowflake clipart, winter clipart png, snowman clipart, winter printables'),
 'new-years-eve-clipart': dict(w=season(d(11, 27), D1, 1.2, (d(12, 1), D1, 2.5)), boards=[B_HOL],
  titles=["New Year's Eve Clipart: 7 PNG Sets", 'New Year 2027 Clipart: Champagne & Disco Balls', "Black & Gold New Year's Eve Clipart PNG"],
  hooks=["7 New Year's Eve clipart PNG sets for 2027: champagne, midnight clocks, disco balls and black and gold glam.",
         "Planning a New Year's Eve party? Clipart for invitations, countdown signs and photo props.",
         "From a 65+ PNG watercolor bundle to a single 'Cheers to the Midnight' design.",
         "Glam party girls, frosty snowmen or black and gold: pick a New Year's style by the project.",
         "2027 is coming: New Year's Eve graphics for cards, shirts and party printables."],
  details=['My guide compares 7 sets, with design and printing tips.',
           "Includes my own Midnight New Year's Eve clipart.",
           'Each set has a note on what it suits best.',
           'Printing tips for gold and black designs included.',
           'Handy for Canva, Cricut and sublimation.'],
  kw="new years eve clipart, new year 2027, champagne clipart, disco ball png, party printables"),
 'new-year-fonts': dict(w=season(d(11, 27), D1, 1.2, (d(12, 1), D1, 2.5)), boards=[B_FONTS],
  titles=['New Year Fonts for Canva: 7 Fonts', 'Fonts for 2027 New Year Invitations', 'Art Deco & Retro New Year Fonts'],
  hooks=['7 New Year fonts you can upload to Canva: Art Deco, retro script, cross-stitch, fireworks dingbats and more.',
         'Countdown invitations and 2027 designs look better with a font made for the night.',
         'New Year Deco, New Year Show, Cheerful New Year and a fireworks dingbat font.',
         'A playful slab serif, handwritten fonts and a cross-stitch font for your New Year designs.',
         'Gold, black and the right font: the New Year party look in Canva.'],
  details=['My guide matches each font to a design and shows how to use them in Canva.',
           "Includes pairing tips and my own New Year's Eve clipart.",
           "Each font has a 'best for' note.",
           'Works for invitations, countdown posters and social posts.',
           'Short pairing tips included.'],
  kw='new year fonts, canva fonts, art deco font, new years eve invitation, 2027 fonts'),
 'winter-animal-clipart': dict(w=season(d(12, 1), D1, 1.5), boards=[B_CUTE, B_HOL],
  titles=['Winter Animal Clipart: 8 PNG Sets', 'Foxes, Bears & Penguins in Scarves Clipart', 'Cute Winter Animal Clipart for All Winter'],
  hooks=['8 winter animal clipart PNG sets: woodland animals in knitted hats, peeking animals, arctic friends and sticker designs.',
         'Foxes, bears and penguins in scarves: winter clipart you can use long after Christmas.',
         'Peeking animals for card edges, vintage winter animals and a set of foxes in scarves.',
         'Winter birthday invites, January planner pages and cozy mugs: animals in hats and scarves fit them all.',
         'Skip the Santa hats and your winter designs work until March.'],
  details=['My guide compares 8 sets and shows how to make winter clipart last all season.',
           'Includes tips for Canva and printing.',
           'Includes my own Cozy Winter Wonderland clipart.',
           "Each set has a 'best for' note.",
           'With ideas for cards, stickers and kids\' crafts.'],
  kw='winter animal clipart, fox clipart, woodland animals png, winter clipart, cute animal clipart'),
 'lunar-new-year-clipart': dict(w=season(d(12, 8), D1, 1.2), boards=[B_HOL],
  titles=['Lunar New Year Clipart 2027: Year of the Goat', 'Year of the Goat 2027 Clipart & Designs', 'Chinese New Year 2027 Goat Clipart PNG'],
  hooks=['7 Lunar New Year 2027 clipart sets for the Year of the Goat: watercolor goats, kawaii goat bundles and a gold goat design.',
         'Lunar New Year 2027 is the Year of the Goat. Start your cards and shirts early.',
         "Watercolor goats, a cute dumpling, a baby's first Chinese New Year design and a mahjong goat.",
         'Red lanterns, plum blossoms and goats: Lunar New Year designs for cards, shirts and red envelopes.',
         'From a 36 PNG goat bundle to single shirt-ready designs.'],
  details=['My guide compares 7 sets with design and printing tips.',
           'Includes my own Lunar New Year Blossoms clipart.',
           "Each set has a 'best for' note.",
           'Handy for sublimation shirts and Canva cards.',
           'Design tips for red and gold included.'],
  kw='lunar new year 2027, year of the goat, chinese new year clipart, lunar new year png, goat clipart'),
}

# own-set data (shop.json, checked 15.11.2026) for Payhip pins and /shop/<slug>/ pins
SETS = {
 '05-pastel-christmas': dict(n=22, code='PMjaE', slug='pastel-christmas', w=season(D0, XMAS_END, 1),
  titles=['Pastel Christmas Clipart – 22 PNG', 'Pink & Sage Christmas Clipart PNG', 'Soft Pastel Christmas Watercolor Clipart'],
  hooks=['Christmas in blush pink, sage green, cream and peach: a gentle alternative to classic red and green.',
         'A pink vintage truck with a tree, a gingerbread house, a reindeer in a flower crown and knitted mittens.',
         'Soft watercolor Christmas clipart for cards, gift tags, invitations and cozy seasonal decor.',
         'Pastel Christmas for people who love pink: baubles, candy canes, a wreath and a hot cocoa mug.',
         'Make your own pastel Christmas cards and tags with 22 watercolor elements.'],
  kw='pastel christmas clipart, pink christmas, watercolor christmas png, christmas clipart, gift tags'),
 '14-moody-florals': dict(n=23, code='Cr0WO', slug='moody-florals', w=season(D0, D1, 0.7),
  titles=['Dark Moody Florals Clipart – 23 PNG', 'Burgundy Watercolor Flower Clipart PNG', 'Moody Winter Floral Clipart Set'],
  hooks=['Rich burgundy, deep plum and dusty mauve florals with touches of gold for moody winter designs.',
         'Dark roses, peonies, dahlias, calla lilies and a gold butterfly for dramatic invitations and cards.',
         'Moody florals for a winter wedding, an elegant birthday or a deep red holiday card.',
         'A floral wreath, a crescent moon with flowers, a moth and dark berries: florals with a mood.',
         "Not every December design needs Santa: deep red and plum flowers look festive too."],
  kw='moody florals, burgundy flowers png, dark floral clipart, winter wedding, watercolor flowers'),
 '15-vintage-christmas': dict(n=22, code='h4UTP', slug='vintage-christmas', w=season(D0, XMAS_END, 1),
  titles=['Vintage Christmas Storybook Clipart – 22 PNG', 'Retro 1950s Christmas Clipart PNG', 'Old-Fashioned Christmas Clipart Set'],
  hooks=['Nostalgic Christmas clipart in the style of a 1950s storybook: pen-and-ink with red, pine green, cream and gold.',
         'Santa, a reindeer, a nutcracker, a rocking horse and a toy train, like pages from an old Christmas book.',
         'Vintage Christmas cards, tags and retro shirts with storybook clipart.',
         'A red truck with a tree, a teddy bear, a sleigh and a stocking: classic Christmas, softly vintage.',
         'For anyone who misses old-fashioned Christmas illustrations.'],
  kw='vintage christmas clipart, retro christmas png, storybook christmas, santa clipart, christmas cards'),
 '17-cozy-winter-wonderland': dict(n=23, code='vi5ym', slug='cozy-winter-wonderland', w=season(D0, D1, 1),
  titles=['Cozy Winter Wonderland Clipart – 23 PNG', 'Nordic Winter Clipart PNG', 'Scandinavian Winter Animal Clipart'],
  hooks=['Scandinavian-style winter clipart in icy blue, navy, berry red and warm wood.',
         'A fox in a scarf, a bunny with a mug, an owl in earmuffs and a polar bear cub.',
         'Winter clipart that works from December to February: no Santa, just snow, cocoa and cozy knits.',
         'A snowy cabin, a red barn, a wooden sled, ice skates and folk-art snowflakes.',
         "Cozy Nordic winter designs for cards, mugs, planner pages and kids' crafts."],
  kw='winter clipart, nordic winter, winter animals png, cozy winter, snowflake clipart'),
 '25-new-years-eve': dict(n=22, code='ypATM', slug='new-years-eve', w=season(d(11, 24), D1, 0.6, (d(12, 1), D1, 2.5)),
  titles=["Midnight New Year's Eve Clipart – 22 PNG", "Art Deco New Year's Eve Clipart PNG", 'Navy & Gold New Year Clipart'],
  hooks=["Art Deco New Year's Eve clipart in midnight navy, silver and champagne gold.",
         'A disco ball, a clock striking midnight, champagne toasts and a masquerade mask.',
         'Not Christmas, pure New Year glamour: invitations, countdown signs and party favors.',
         'A champagne tower, a top hat, a bow tie, fireworks and balloons for your 2027 party.',
         'Ring in 2027 with elegant navy and gold party printables.'],
  kw="new years eve clipart, art deco clipart, champagne png, new year 2027, party printables"),
 '26-hanukkah': dict(n=22, code='VkyWz', slug='hanukkah', w=season(D0, d(12, 11), 0.9, (D0, d(12, 4), 1.4)),
  titles=['Festival of Lights Hanukkah Clipart – 22 PNG', 'Paper-Cut Hanukkah Clipart PNG', 'Blue & Gold Hanukkah Clipart Set'],
  hooks=['A layered paper-cut Hanukkah collection in deep blue, silver and gold.',
         'A nine-branch menorah, a dreidel, a Star of David, sufganiyot and latkes.',
         'Hanukkah cards, gift tags and party invitations in one cohesive style.',
         'Gelt coins, an oil jug, gift boxes with blue bows and a potted hyacinth.',
         'Eight nights of gifts: matching tags and cards in blue and gold.'],
  kw='hanukkah clipart, menorah png, dreidel clipart, hanukkah printables, blue and gold'),
}
PAY_DETAILS = ['{n} transparent PNG, 300 DPI, designed by me. Personal and commercial use included.',
               "{n} PNG at 300 DPI. Licence: personal and commercial use; you can't resell or share the files themselves.",
               'Instant download: {n} PNG with transparent backgrounds, 300 DPI, ready for Canva and Cricut.',
               '{n} PNG, 300 DPI, from my own little shop. Commercial use included.',
               "A set of {n} transparent PNG files at 300 DPI. Use them on products you sell; just don't resell the files."]
SHOP_DETAILS = ['See every file, its size and project ideas on the set page.',
                "The set page shows all {n} files at a glance, with 'good for' and 'skip if' notes.",
                'All {n} PNG, 300 DPI, on one page with print hints.']
SHOP_PAGES = {  # /shop/<slug>/ pins: R variant 5, at most once a week each
    '05-pastel-christmas': season(D0, d(12, 12), 2), '15-vintage-christmas': season(D0, d(12, 12), 2),
    '17-cozy-winter-wonderland': season(D0, D1, 2), '25-new-years-eve': season(d(11, 27), D1, 2),
}


def safe(slug):
    return slug.strip('/').replace('/', '-')


def build():
    rnd = random.Random(20261119)
    days = [D0 + dt.timedelta(i) for i in range((D1 - D0).days + 1)]
    # URL usage history (path -> list of days) seeded with Metricool pins
    hist = {}
    for day, rows in EXISTING.items():
        for _, p in rows:
            hist.setdefault(p, []).append(day)
    img_last = {}
    pair_used = {}
    rows = []
    # unit list: article posts + shop pages
    units = {}
    for slug, p in POSTS.items():
        units['/' + slug + '/'] = dict(kind='post', slug=slug, w=p['w'], imgs=[f'art-{safe(slug)}-{v}.jpg' for v in 'ABC'],
                                       vlabel=list('ABC'), weekcap=3)
    for sid, wf in SHOP_PAGES.items():
        s = SETS[sid]
        units[f'/shop/{s["slug"]}/'] = dict(kind='shop', sid=sid, w=wf, imgs=[f'own-{sid}-v5.jpg'], vlabel=['v5'], weekcap=1)
    rot = {u: rnd.randrange(3) for u in units}

    def desc_for(key, hooks, details):
        used = pair_used.setdefault(key, set())
        pairs = [(h, dd) for h in range(len(hooks)) for dd in range(len(details)) if (h, dd) not in used]
        # prefer a hook not used recently: spread hooks evenly
        cnt = {}
        for h, _ in used:
            cnt[h] = cnt.get(h, 0) + 1
        pairs.sort(key=lambda x: (cnt.get(x[0], 0), (x[1] - x[0]) % len(details)))
        h, dd = pairs[0]
        used.add((h, dd))
        return h, dd

    def ok_img(f, day):
        return f not in img_last or (day - img_last[f]).days >= 4

    for day in days:
        # ---- jalina.art pins
        cap = jal_cap(day) - sum(1 for _, p in EXISTING.get(day, []))
        picked = []
        today = {p for _, p in EXISTING.get(day, [])}
        for _ in range(max(0, cap)):
            best = None
            for u, U in units.items():
                w = U['w'](day)
                if not w or u in today:
                    continue
                h = hist.get(u, [])
                consecutive = bool(h) and (day - max(h)).days < 2  # allowed only as a last resort (low score)
                if sum(1 for x in h if 0 <= (day - x).days < 7) >= U['weekcap']:
                    continue
                if U['kind'] == 'shop' and any(0 <= (day - x).days < 7 for x in h):
                    continue
                n = len(U['imgs'])
                vi = next((((rot[u] + k) % n) for k in range(n) if ok_img(U['imgs'][(rot[u] + k) % n], day)), None)
                if vi is None:
                    continue
                since = min((day - max(h)).days, 8) if h else 8
                sc = w * since * (0.05 if consecutive else 1) + rnd.random() * 0.01
                if not best or sc > best[0]:
                    best = (sc, u, vi)
            if not best:
                break
            _, u, vi = best
            U = units[u]
            rot[u] = (vi + 1) % len(U['imgs'])
            img = U['imgs'][vi]
            img_last[img] = day
            hist.setdefault(u, []).append(day)
            today.add(u)
            picked.append(('jal', u, vi))
        # ---- Payhip pins of own sets
        npay = 4 if (day - D0).days % 2 == 0 else 3
        pays = []
        for _ in range(npay):
            best = None
            for sid, s in SETS.items():
                w = s['w'](day)
                if not w or sid in [x[1] for x in pays]:
                    continue
                k = 'pay:' + sid
                h = hist.get(k, [])
                imgs = [f'own-{sid}-v{n}.jpg' for n in range(1, 5)]
                rot.setdefault(k, 0)
                vi = next((((rot[k] + j) % 4) for j in range(4) if ok_img(imgs[(rot[k] + j) % 4], day)), None)
                if vi is None:
                    continue
                since = min((day - max(h)).days, 6) if h else 6
                if PAYHIP_ONLY and sum(1 for x in h if 0 <= (day - x).days < 7) >= 4:
                    continue  # one Payhip link ≤4 times per 7 days, to avoid looking repetitive
                sc = w * since * (0.05 if PAYHIP_ONLY and since < 2 else 1) + rnd.random() * 0.01
                if not best or sc > best[0]:
                    best = (sc, sid, vi)
            if not best:
                break
            _, sid, vi = best
            k = 'pay:' + sid
            rot[k] = (vi + 1) % 4
            img_last[f'own-{sid}-v{vi + 1}.jpg'] = day
            hist.setdefault(k, []).append(day)
            pays.append(('pay', sid, vi))
        # ---- time slots (UTC 13:00..21:00 every 20 min), avoiding Metricool slots
        busy = set()
        for t, _ in EXISTING.get(day, []):
            hh, mm = map(int, t.split(':'))
            busy.add((hh - 2) * 60 + mm)
        free = [m for m in range(13 * 60, 21 * 60 + 1, 20) if m not in busy]
        items = []
        jl, pl = list(picked), list(pays)
        while jl or pl:  # interleave: ~2 article pins, 1 own-set pin
            for _ in range(2):
                if jl:
                    items.append(jl.pop(0))
            if pl:
                items.append(pl.pop(0))
        n = len(items)
        if n > len(free):
            raise SystemExit(f'{day}: not enough free slots')
        idx = [round(i * (len(free) - 1) / max(1, n - 1)) for i in range(n)] if n > 1 else [len(free) // 2]
        for (kind, key, vi), si in zip(items, idx):
            m = free[si]
            ts = dt.datetime.combine(day, dt.time(m // 60, m % 60)).strftime('%Y-%m-%dT%H:%M:%S')
            camp = f'csv-{day:%Y%m}'
            if kind == 'pay':
                s = SETS[key]
                h, dd = desc_for('pay:' + key, s['hooks'], PAY_DETAILS)
                desc = s['hooks'][h] + ' ' + PAY_DETAILS[dd].format(n=s['n'])
                title = s['titles'][(h + dd) % 3]
                rows.append({'Title': title, 'Media URL': MEDIA + f'own-{key}-v{vi + 1}.jpg', 'Pinterest board': B_OWN, 'Thumbnail': '',
                             'Description': desc,
                             'Link': f'https://payhip.com/b/{s["code"]}?utm_source=pinterest&utm_medium=pin&utm_campaign=own-payhip&utm_content={key}-v2-{vi + 1}',
                             'Publish date': ts, 'Keywords': s['kw'], '_type': 'payhip', '_key': key})
                continue
            U = units[key]
            if U['kind'] == 'shop':
                s = SETS[U['sid']]
                h, dd = desc_for('shop:' + key, s['hooks'], SHOP_DETAILS)
                desc = s['hooks'][h] + ' ' + SHOP_DETAILS[dd].format(n=s['n'])
                title = s['titles'][(h + 1) % 3]
                link = f'https://jalina.art{key}?utm_source=pinterest&utm_medium=pin&utm_campaign={camp}&utm_content=shop-{s["slug"]}-v5'
                rows.append({'Title': title, 'Media URL': MEDIA + U['imgs'][vi], 'Pinterest board': B_OWN, 'Thumbnail': '',
                             'Description': desc, 'Link': link, 'Publish date': ts, 'Keywords': s['kw'], '_type': 'shop', '_key': key})
                continue
            p = POSTS[U['slug']]
            h, dd = desc_for(key, p['hooks'], p['details'])
            uses = sum(1 for r in rows if r.get('_key') == key)
            rows.append({'Title': p['titles'][uses % len(p['titles'])], 'Media URL': MEDIA + U['imgs'][vi],
                         'Pinterest board': p['boards'][uses % len(p['boards'])], 'Thumbnail': '',
                         'Description': p['hooks'][h] + ' ' + p['details'][dd],
                         'Link': f'https://jalina.art{key}?utm_source=pinterest&utm_medium=pin&utm_campaign={camp}&utm_content={safe(U["slug"])}-{U["vlabel"][vi]}',
                         'Publish date': ts, 'Keywords': p['kw'], '_type': 'post', '_key': key})
    rows.sort(key=lambda r: r['Publish date'])
    return rows


def main():
    rows = build()
    pre = 'jalina-payhip-2026-11-20' if PAYHIP_ONLY else 'jalina-2026-11-19'
    files = {pre + '_12-02.csv': [r for r in rows if r['Publish date'] < SPLIT.isoformat()],
             pre.replace('11-20', '12-03').replace('11-19', '12-03') + '_12-18.csv': [r for r in rows if r['Publish date'] >= SPLIT.isoformat()]}
    for name, rs in files.items():
        with open(os.path.join(HERE, name), 'w', newline='', encoding='utf-8') as f:
            w = csv.DictWriter(f, fieldnames=COLS, extrasaction='ignore', lineterminator='\n')
            w.writeheader()
            w.writerows(rs)
        print(name, len(rs), 'rows')
    json.dump(rows, open(os.path.join(HERE, 'plan-payhip.json' if PAYHIP_ONLY else 'plan.json'), 'w'), indent=1, ensure_ascii=False)
    # PLAN.md
    by = {}
    for r in rows:
        by.setdefault(r['Publish date'][:10], []).append(r)
    L = ['# Pinterest CSV plan, 19.11–18.12.2026 (jalina.art)', '',
         'Built by `build_csv.py`, checked by `check_csv.py`. Times in the CSV are UTC (Riga = UTC+2). '
         '"Metricool" = Pinterest pins already scheduled there (read 15.11.2026, non-draft).', '',
         '| Date | Metricool | CSV jalina.art | CSV Payhip | Total | jalina.art URLs in CSV (Riga time) |', '|---|---|---|---|---|---|']
    for day in sorted(by):
        dd = dt.date.fromisoformat(day)
        ex = len(EXISTING.get(dd, []))
        rs = by[day]
        j = [r for r in rs if r['_type'] != 'payhip']
        pcount = len(rs) - len(j)
        urls = ', '.join(f"{int(r['Publish date'][11:13]) + 2:02d}:{r['Publish date'][14:16]} `{r['_key']}`" for r in j) or '—'
        L.append(f'| {dd:%d.%m} | {ex} | {len(j)} | {pcount} | {ex + len(rs)} | {urls} |')
    L += ['', 'Payhip pins per set: ' + ', '.join(f'{k} ×{sum(1 for r in rows if r["_key"] == k)}' for k in SETS), '',
          'jalina.art pins per URL: ' + ', '.join(f'`{k}` ×{v}' for k, v in sorted(
              ((k, sum(1 for r in rows if r['_key'] == k)) for k in {r['_key'] for r in rows if r['_type'] != 'payhip'}), key=lambda x: -x[1]))]
    open(os.path.join(HERE, 'PLAN-payhip-body.md' if PAYHIP_ONLY else 'PLAN-body.md'), 'w').write('\n'.join(L) + '\n')  # per-day table, pasted into PLAN.md


if __name__ == '__main__':
    main()
