# MarMar Lang — план реализации

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** бесплатный плагин переводов для marmarcandles.com: lv по умолчанию, en (позже lt, et) по префиксу в адресе, переводы в полях записей, правильное SEO, корзина и письма на языке покупателя.

**Architecture:** один WordPress‑плагин `marmar-lang` из небольших классов по зонам ответственности (адреса, ссылки, хранение, контент, SEO, карта сайта, WooCommerce, админка). Язык определяется по первому сегменту пути и снимается с `REQUEST_URI` до разбора запроса WordPress; все переводы — мета‑поля записей и терминов, открытые в REST. Проверка — интеграционные тесты pytest по HTTP против локальной копии WordPress + WooCommerce + Rank Math + Blocksy на SQLite.

**Tech Stack:** PHP 8.3, WordPress 7.x, WooCommerce 11.x, Rank Math 1.0.x, Blocksy 2.1.x; тесты — Python 3 + pytest + requests, WP‑CLI, плагин SQLite Database Integration, `php -S`.

**Spec:** `docs/superpowers/specs/2026-11-03-marmar-lang-design.md`

## Global Constraints

- Языки: `lv` — основной, без префикса; `en` — первым; `lt`, `et` — включаются настройкой позже.
- Локали WordPress: `lv` → `lv`, `en` → `en_GB`, `lt` → `lt_LT`, `et` → `et`.
- Адреса: латышский без префикса; `/en/…`, `/lt/…`, `/et/…`; слаги в v1 латышские (без переводных адресов).
- `/lv/…` → 301 на тот же путь без `/lv/` (с сохранением query string).
- Переводы хранятся в той же записи/термине: мета `_mm_tr_{lang}_{field}`; поля записи: `title`, `content`, `excerpt`, `seo_title`, `seo_desc`; поля термина: `name`, `description`, `seo_title`, `seo_desc`; пункт меню: `title`.
- Все мета‑поля доступны в REST (чтение — всем, запись — `edit_posts` / `manage_categories`).
- Нет перевода → страница на этом языке не существует: 302 на латышскую версию, нет в hreflang и в карте сайта.
- Блок в админке называется **«Tulkojumi»**; переключатель языков показывает `LV · EN` (и `LT`, `ET`, когда включены).
- Плагин выключен → сайт ведёт себя как без него (латышский, без следов плагина в выводе).
- Скрытый режим: опция `mm_lang_public` = `false` → языковые адреса видят только администраторы, остальным 302 на латышский; hreflang не выводится.
- Машинного перевода нет; переключение языков и цены/валюты не меняются.
- Включение на живом сайте — январь 2027; до этого только тестовая копия.

## Review Focus

1. **`/en/` без перевода** — не должен показывать латышский текст под английским адресом; ожидается 302 на lv и отсутствие в hreflang/карте сайта (тест в Task 4).
2. **Корзина‑блок и оформление заказа через Store API** — после перехода в `/en/` названия товаров в корзине английские, после возврата в lv — латышские (тест в Task 7).
3. **Служебные адреса не получают префикс** — `wp-admin`, `wp-json`, `wp-content`, `wp-login.php`, `feed`, `?wc-ajax=` остаются без `/en/`; а меню, карточки, хлебные крошки, пагинация на `/en/` — с `/en/` (тест в Task 3).
4. **Старые `/lv/…` адреса из индекса Google** — 301 на адрес без `/lv/` с тем же query string; `/en/…` старого формата продолжает открываться (тест в Task 2).
5. **Латышские страницы не меняются** — HTML главной, категории и товара на lv с включённым плагином совпадает с выводом без плагина, кроме блока hreflang (тест в Task 9).

---

## Файлы

```
site/plugins/marmar-lang/
  marmar-lang.php          — заголовок плагина, автозагрузка, публичные функции mm_*
  src/Languages.php        — список языков, локали, опции mm_lang_enabled / mm_lang_public
  src/Router.php           — язык из пути, снятие префикса, locale, 301 /lv/, скрытый режим, cookie mm_lang
  src/Links.php            — префикс языка в ссылках
  src/Store.php            — регистрация мета (REST), mm_tr_get / mm_tr_has
  src/Content.php          — подстановка переводов (записи, товары, термины, меню), 302 без перевода
  src/Seo.php              — html lang, hreflang, canonical, Rank Math title/description
  src/Sitemap.php          — /mm-sitemap-{lang}.xml
  src/Woo.php              — Store API по языку, язык заказа, письма
  src/Admin.php            — «Tulkojumi» у записей и терминов, настройки, переключатель [mm_lang_switcher]
tests/marmar-lang/
  setup_wp.sh              — локальный WordPress + плагины + тестовые данные
  conftest.py              — запуск/остановка php -S, хелперы http/rest/wp-cli
  test_*.py                — тесты по задачам
```

Публичные функции (`marmar-lang.php`), на которые опираются все задачи:

- `mm_lang_default(): string` → `'lv'`
- `mm_lang_enabled(): array` → включённые неосновные языки, по умолчанию `['en']`
- `mm_lang_current(): string` → язык текущего запроса
- `mm_lang_locale(string $lang): string`
- `mm_lang_url(string $url, string $lang): string` → тот же адрес на другом языке
- `mm_tr_get(string $kind, int $id, string $field, string $lang): ?string` (`$kind` = `post` | `term`)
- `mm_tr_has(string $kind, int $id, string $lang): bool` → есть ли `title`/`name` на этом языке

---

### Task 1: Тестовая копия WordPress и каркас плагина

**Files:**
- Create: `tests/marmar-lang/setup_wp.sh`, `tests/marmar-lang/conftest.py`, `tests/marmar-lang/test_smoke.py`
- Create: `site/plugins/marmar-lang/marmar-lang.php`, `src/Languages.php`, `src/Store.php`

**Interfaces:**
- Produces: окружение `MMWP_DIR` (по умолчанию scratchpad `/mmwp`), сервер `http://127.0.0.1:8899`; фикстуры pytest `site` (base URL), `rest(method, path, **kw)` с паролем приложения админа, `wp(*args) -> str` (WP‑CLI); тестовые данные: товары «Ciparu svece “5”» (простой, с переводом en), «Ziemassvētku bumba» (вариативный, цвета Bordo/Pienbalts, с переводом en), «Galda svece» (без перевода), категория `ciparu-sveces` (с en), страница `piegade` (с en), меню `header` из 3 пунктов.
- Produces: публичные функции `mm_lang_*`, `mm_tr_get`, `mm_tr_has`; мета `_mm_tr_{lang}_{field}` в REST.

- [ ] **Step 1:** `setup_wp.sh` — скачать WP‑CLI phar, WordPress, SQLite Database Integration, WooCommerce, Rank Math, тему Blocksy + Blocksy Companion; `wp core install`, включить ЧПУ `/%postname%/`, база товаров `/product/`; создать пароль приложения; симлинк `site/plugins/marmar-lang` в `wp-content/plugins`; создать тестовые данные. Скрипт идемпотентен (повторный запуск пересоздаёт базу).
- [ ] **Step 2: Тесты** `test_smoke.py`:
  - `test_site_up`: GET `/` → 200, в HTML есть `Ciparu svece`.
  - `test_meta_rest_roundtrip`: POST `wp/v2/product/{id}` с `meta: {"_mm_tr_en_title": "Number candle “5”"}` → затем GET с `context=edit` возвращает то же значение.
  - `test_meta_requires_auth`: тот же POST без авторизации → 401.
  - `test_tr_has`: `wp eval 'var_export(mm_tr_has("post", ID, "en"));'` → `true` для «Ciparu svece “5”», `false` для «Galda svece».
- [ ] **Step 3:** Запустить `pytest tests/marmar-lang/test_smoke.py -v` → FAIL (плагина нет).
- [ ] **Step 4:** Реализовать `marmar-lang.php` (константа `MM_LANG_VERSION = '0.1.0'`), `Languages.php` (локали из Global Constraints, опции `mm_lang_enabled` = `['en']`, `mm_lang_public` = `false`), `Store.php` (`register_post_meta` для `product`, `page`, `post`, `nav_menu_item`; `register_term_meta` для `product_cat`, `pa_*`; тип `string`, `single`, `show_in_rest`, `auth_callback` по правам из Global Constraints; `sanitize_callback` для `content` — `wp_kses_post`).
- [ ] **Step 5:** `pytest tests/marmar-lang/test_smoke.py -v` → PASS.
- [ ] **Step 6:** Commit `feat(marmar-lang): plugin skeleton, translation meta in REST, local test site`.

### Task 2: Язык из адреса, локаль, редиректы, скрытый режим

**Files:**
- Create: `site/plugins/marmar-lang/src/Router.php`
- Test: `tests/marmar-lang/test_router.py`

**Interfaces:**
- Consumes: `mm_lang_enabled()`, `mm_lang_locale()`.
- Produces: `mm_lang_current()`; cookie `mm_lang` (значение языка, path `/`, 30 дней) ставится при каждом HTML‑ответе на языковом адресе и при заходе на lv.

- [ ] **Step 1: Тесты** (`mm_lang_public` = true, кроме теста скрытого режима):
  - `test_en_prefix_serves_page`: GET `/en/product/ciparu-svece-5/` → 200, `<html lang="en-GB"`.
  - `test_lv_has_no_prefix`: GET `/product/ciparu-svece-5/` → 200, `<html lang="lv"`.
  - `test_legacy_lv_redirect`: GET `/lv/product/ciparu-svece-5/?a=1` без редиректов → 301, `Location` = `/product/ciparu-svece-5/?a=1`; GET `/lv/` → 301 на `/`.
  - `test_disabled_language_404`: GET `/lt/product/ciparu-svece-5/` → 404, пока `lt` не включён.
  - `test_hidden_mode`: `mm_lang_public` = false → гость GET `/en/…` → 302 на lv; админ (cookie авторизации) → 200.
  - `test_cookie_set`: ответ на `/en/…` содержит `Set-Cookie: mm_lang=en`.
- [ ] **Step 2:** Запустить → FAIL.
- [ ] **Step 3:** Реализовать `Router` на хуке `plugins_loaded` (приоритет 1): разбор первого сегмента `REQUEST_URI`, снятие префикса до `parse_request`, фильтр `locale` → `mm_lang_locale()`, 301 для `/lv/…`, скрытый режим, cookie. Для REST/AJAX (`/wp-json/`, `?wc-ajax=`) язык берётся из cookie `mm_lang`.
- [ ] **Step 4:** Запустить → PASS.
- [ ] **Step 5:** Commit `feat(marmar-lang): language routing, legacy /lv/ redirects, hidden mode`.

### Task 3: Ссылки с префиксом языка

**Files:**
- Create: `site/plugins/marmar-lang/src/Links.php`
- Test: `tests/marmar-lang/test_links.py`

**Interfaces:**
- Consumes: `mm_lang_current()`.
- Produces: `mm_lang_url(string $url, string $lang): string` — заменяет/добавляет/убирает префикс у адресов сайта; чужие домены и служебные пути возвращает без изменений.

- [ ] **Step 1: Тесты:**
  - `test_links_keep_language`: на `/en/product-category/ciparu-sveces/` все ссылки на `/product/…`, `/product-category/…`, ссылки меню, хлебные крошки и `page/2/` начинаются с `/en/`.
  - `test_service_paths_untouched`: на той же странице ни одна ссылка/скрипт/стиль с `wp-content`, `wp-json`, `wp-admin`, `wp-login.php`, `wc-ajax`, `/feed/` не содержит `/en/`.
  - `test_mm_lang_url`: `mm_lang_url("{site}/product/x/?a=1","en")` → `{site}/en/product/x/?a=1`; `mm_lang_url("{site}/en/product/x/","lv")` → `{site}/product/x/`; `mm_lang_url("https://example.com/x","en")` без изменений.
  - `test_lv_links_unprefixed`: на lv‑странице нет ни одной ссылки с `/en/`.
- [ ] **Step 2:** Запустить → FAIL.
- [ ] **Step 3:** Реализовать `Links` на фильтрах `post_link`, `page_link`, `post_type_link`, `term_link`, `home_url` (только фронтенд и язык ≠ lv), `paginate_links`, `woocommerce_get_cart_url`, `woocommerce_get_checkout_url`; исключения — список служебных путей из теста.
- [ ] **Step 4:** Запустить → PASS.
- [ ] **Step 5:** Commit `feat(marmar-lang): language-aware links`.

### Task 4: Подстановка переводов и страницы без перевода

**Files:**
- Create: `site/plugins/marmar-lang/src/Content.php`
- Test: `tests/marmar-lang/test_content.py`

**Interfaces:**
- Consumes: `mm_tr_get()`, `mm_tr_has()`, `mm_lang_current()`, `mm_lang_url()`.

- [ ] **Step 1: Тесты** (на `/en/`):
  - `test_product_translated`: страница товара — H1, описание, короткое описание из `_mm_tr_en_*`; латышского описания в HTML нет.
  - `test_category_and_cards_translated`: H1 категории и описание — из термина; названия на карточках — английские.
  - `test_menu_translated`: пункты меню с `_mm_tr_en_title` выводятся по‑английски, без перевода — по‑латышски.
  - `test_attribute_terms_translated`: свотч «Bordo» с переводом `Burgundy` показывает `Burgundy` в `title`/подписи.
  - `test_missing_translation_redirect`: GET `/en/product/galda-svece/` → 302 на `/product/galda-svece/`.
  - `test_partial_translation_falls_back`: товар с переводом `title`, но без `excerpt` → короткое описание латышское, страница отдаётся 200.
  - `test_lv_untouched`: на lv те же страницы выводят латышский текст.
- [ ] **Step 2:** Запустить → FAIL.
- [ ] **Step 3:** Реализовать `Content`: фильтры `the_title`, `the_content`, `get_the_excerpt`, `woocommerce_product_get_name`, `woocommerce_product_get_description`, `woocommerce_product_get_short_description` (+ `_variation_` варианты), `get_term`, `term_description`, `wp_nav_menu_objects`, `woocommerce_variation_option_name`; на `template_redirect` — 302 для одиночных записей и терминов без `mm_tr_has()`. Совместимость со сниппетом «MarMar UX»: короткие названия на карточках строятся из уже переведённого названия (порядок фильтров: Content раньше, приоритет 5).
- [ ] **Step 4:** Запустить → PASS.
- [ ] **Step 5:** Commit `feat(marmar-lang): translated content and missing-translation redirect`.

### Task 5: SEO — lang, hreflang, canonical, Rank Math

**Files:**
- Create: `site/plugins/marmar-lang/src/Seo.php`
- Test: `tests/marmar-lang/test_seo.py`

**Interfaces:**
- Consumes: `mm_lang_url()`, `mm_tr_has()`, `mm_tr_get()`.

- [ ] **Step 1: Тесты:**
  - `test_hreflang_pairs`: на товаре с en: `hreflang="lv"` → lv‑адрес, `hreflang="en"` → `/en/…`, `hreflang="x-default"` → lv; одинаково на lv и en версиях.
  - `test_hreflang_skips_missing`: на «Galda svece» (lv) нет `hreflang="en"`.
  - `test_canonical_self`: canonical на `/en/…` указывает на себя, на lv — на lv (ровно один `rel="canonical"`).
  - `test_rank_math_meta`: `<title>` и `meta description` на `/en/` берутся из `_mm_tr_en_seo_title` / `_mm_tr_en_seo_desc`; если их нет — из переведённого названия, без латышского SEO‑заголовка.
  - `test_og_locale`: `og:locale` = `en_GB` на `/en/`.
  - `test_hidden_mode_no_hreflang`: `mm_lang_public` = false → в lv‑HTML нет `hreflang`.
- [ ] **Step 2:** Запустить → FAIL.
- [ ] **Step 3:** Реализовать `Seo`: `wp_head` (hreflang), `rank_math/frontend/canonical`, `rank_math/frontend/title`, `rank_math/frontend/description`, `rank_math/opengraph/facebook/og_locale`, `language_attributes`.
- [ ] **Step 4:** Запустить → PASS.
- [ ] **Step 5:** Commit `feat(marmar-lang): hreflang, canonical and Rank Math integration`.

### Task 6: Карта сайта по языкам

**Files:**
- Create: `site/plugins/marmar-lang/src/Sitemap.php`
- Test: `tests/marmar-lang/test_sitemap.py`

- [ ] **Step 1: Тесты:**
  - `test_sitemap_en`: GET `/mm-sitemap-en.xml` → 200, `Content-Type: application/xml`, содержит `/en/product/ciparu-svece-5/`, `/en/product-category/ciparu-sveces/`, `/en/piegade/`; не содержит `galda-svece`; каждый `<url>` с `xhtml:link` для lv и en.
  - `test_sitemap_disabled_lang`: `/mm-sitemap-lt.xml` → 404, пока `lt` не включён.
  - `test_sitemap_hidden_mode`: при `mm_lang_public` = false → 404.
- [ ] **Step 2:** Запустить → FAIL.
- [ ] **Step 3:** Реализовать `Sitemap` (правило перезаписи `^mm-sitemap-([a-z]{2})\.xml$`, только опубликованные записи/термины с `mm_tr_has()`).
- [ ] **Step 4:** Запустить → PASS.
- [ ] **Step 5:** Commit `feat(marmar-lang): per-language sitemaps`.

### Task 7: WooCommerce — корзина, заказ, письма

**Files:**
- Create: `site/plugins/marmar-lang/src/Woo.php`
- Test: `tests/marmar-lang/test_woo.py`

**Interfaces:**
- Consumes: cookie `mm_lang`, `mm_lang_locale()`, `Content` фильтры.
- Produces: мета заказа `_mm_lang`.

- [ ] **Step 1: Тесты** (сессия requests с cookie):
  - `test_store_api_cart_language`: открыть `/en/…`, добавить товар через Store API `cart/add-item` → в ответе `items[0].name` английский; затем открыть lv‑страницу, GET `wc/store/v1/cart` → имя латышское.
  - `test_cart_checkout_pages`: GET `/en/cart/` и `/en/checkout/` → 200, `<html lang="en-GB"`.
  - `test_order_language_saved`: оформить заказ через Store API `checkout` (оплата «наличными при получении», тестовый адрес) из `/en/` → у заказа `_mm_lang` = `en`.
  - `test_order_email_language`: письмо покупателю по этому заказу (перехват через `pre_wp_mail` в mu‑плагине тестов, запись в файл) — тема и текст из английского пакета WooCommerce (`Your … order has been received`), название товара английское.
  - `test_lv_order_email_latvian`: заказ из lv → письмо латышское.
- [ ] **Step 2:** Запустить → FAIL.
- [ ] **Step 3:** Реализовать `Woo`: язык Store API из cookie, сохранение `_mm_lang` при создании заказа (классическое и блочное оформление), переключение локали и языка контента при отправке писем заказа с восстановлением после. В `setup_wp.sh` — загрузка языковых пакетов `en_GB`, `lv` для WordPress и WooCommerce (`wp language core/plugin install`).
- [ ] **Step 4:** Запустить → PASS.
- [ ] **Step 5:** Commit `feat(marmar-lang): WooCommerce cart, order and email language`.

### Task 8: Админка «Tulkojumi», настройки, переключатель

**Files:**
- Create: `site/plugins/marmar-lang/src/Admin.php`
- Test: `tests/marmar-lang/test_admin.py`

- [ ] **Step 1: Тесты:**
  - `test_metabox_saves`: админ открывает редактор товара → есть блок «Tulkojumi» с полями EN (title, content, excerpt, seo_title, seo_desc); сохранение формы записывает `_mm_tr_en_*`.
  - `test_term_fields_save`: форма редактирования категории содержит поля EN и сохраняет их.
  - `test_settings_page`: «Iestatījumi → MarMar Lang» — чекбоксы `en`, `lt`, `et` и «Publiski redzams»; сохранение меняет `mm_lang_enabled` / `mm_lang_public`.
  - `test_switcher_shortcode`: `[mm_lang_switcher]` на `/en/` товара выводит `LV · EN`, ссылка LV → lv‑адрес этого же товара; на товаре без перевода ссылки EN нет.
  - `test_switcher_hidden_for_guests`: при `mm_lang_public` = false гость не видит переключатель.
- [ ] **Step 2:** Запустить → FAIL.
- [ ] **Step 3:** Реализовать `Admin`: метабокс для `product`, `page`, `post` (в блочном редакторе — как классический метабокс), поля терминов `product_cat` и `pa_*`, страница настроек, шорткод; переключатель добавляется последним пунктом в меню `header` и в мобильное меню через фильтр `wp_nav_menu_items`.
- [ ] **Step 4:** Запустить → PASS.
- [ ] **Step 5:** Commit `feat(marmar-lang): Tulkojumi admin, settings and language switcher`.

### Task 9: Безопасность для латышского сайта, сборка, план включения

**Files:**
- Create: `tests/marmar-lang/test_safety.py`, `site/plugins/marmar-lang/readme.txt`, `docs/marmar-lang-rollout.md`
- Create: `tools/build_marmar_lang_zip.sh`

- [ ] **Step 1: Тесты:**
  - `test_lv_html_unchanged`: снять HTML `/`, `/product-category/ciparu-sveces/`, `/product/ciparu-svece-5/` с выключенным плагином и со включённым (en есть, `mm_lang_public` = true); после удаления блока `<link rel="alternate" hreflang=…>` и nonce‑значений — совпадает.
  - `test_deactivate_clean`: `wp plugin deactivate marmar-lang` → `/en/product/ciparu-svece-5/` → 404, lv‑страницы 200, мета‑поля в базе сохранены.
  - `test_coexists_with_ux_snippet`: с подключённым `site/snippets/marmar-ux.php` (как mu‑плагин) на `/en/` карточки показывают короткое английское название, блок под кнопкой покупки присутствует.
- [ ] **Step 2:** Запустить → FAIL/PASS по факту; исправить найденное в коде соответствующего модуля.
- [ ] **Step 3:** `tools/build_marmar_lang_zip.sh` → `dist/marmar-lang-0.1.0.zip` (без тестов). `docs/marmar-lang-rollout.md` — шаги на январь: резервная копия UpdraftPlus → загрузка zip через «Spraudņi → Pievienot» (делает Алина) → скрытый режим → Claude записывает переводы через REST и проверяет `/en/` как администратор → выключить GPTranslate → `mm_lang_public` = true → сброс LiteSpeed Cache → проверить на живом сайте, что `www.` → 301 на адрес без `www.` (уровень хостинга; если нет — добавить в `.htaccess` через Алину) → отправить `/mm-sitemap-en.xml` в Search Console → проверка позиций `/en/` через 7 и 30 дней.
- [ ] **Step 4:** Полный прогон `pytest tests/marmar-lang -v` → всё PASS.
- [ ] **Step 5:** Commit `feat(marmar-lang): safety tests, build script, rollout plan`.
