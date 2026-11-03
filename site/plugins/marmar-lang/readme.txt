=== MarMar Lang ===
Stable tag: 0.1.0
Requires PHP: 8.0
License: GPLv2 or later

Lightweight translations for marmarcandles.com.

* Latvian (default) without prefix; /en/, /lt/, /et/ when enabled (Iestatījumi → MarMar Lang).
* Translations live in the same post/term, meta _mm_tr_{lang}_{field}, editable in the "Tulkojumi" box and via REST.
* Pages without a translation redirect (302) to the Latvian page and are left out of hreflang and the sitemap.
* hreflang, canonical, Rank Math title/description, /mm-sitemap-{lang}.xml.
* WooCommerce: cart (Store API) follows the visitor's language; order language is stored in _mm_lang; customer emails use it.
* Hidden mode (default): only administrators see other languages until "Publiski redzams" is enabled.
* Deactivating the plugin leaves the site exactly as before (Latvian); translations stay in the database.
