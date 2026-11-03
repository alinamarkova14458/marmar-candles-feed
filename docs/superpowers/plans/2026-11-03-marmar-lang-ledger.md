# SDD ledger — plan: docs/superpowers/plans/2026-11-03-marmar-lang.md
Spec: docs/superpowers/specs/2026-11-03-marmar-lang-design.md (read)
Pre-flight:
- T1→T2..T9: mm_lang_*/mm_tr_* public functions + meta keys _mm_tr_{lang}_{field} — consistent across tasks.
- T2→T3: mm_lang_current() — T3 consumes as defined. OK.
- T3→T4/T5/T8: mm_lang_url(url,lang) — consistent.
- T2→T7: cookie mm_lang — consistent.
- T4↔UX snippet (site/snippets/marmar-ux.php the_title prio 20): Content prio 5 → translated title reaches short-name filter. OK.
- Ruling: hidden mode (mm_lang_public=false) applies to T2 routing, T5 hreflang, T6 sitemap, T8 switcher — tests set mm_lang_public=true except hidden-mode tests — spec "скрытый режим" — cost if wrong: test fixtures need toggling.
Task 1: complete (commits 3862230..177f7fc, tests: python3 -m pytest tests/marmar-lang -q → 5 passed in 4.09s)
- Task 2: Ruling: lv pages carry html lang="lv-LV" (WordPress's own value for locale lv), plan said lang="lv" — both valid BCP47 for Google — cost if wrong: one string in Seo/tests
Task 2: complete (commits 177f7fc..ddbb24c, tests: python3 -m pytest tests/marmar-lang -q → 11 passed in 7.72s)
- Task 3: Ruling: feed links (/feed/) stay unprefixed and are excluded from the 'content links' assertion — feeds are service output per plan Global/Review Focus #3 — cost if wrong: English feeds unavailable (not needed)
Task 3: complete (commits ddbb24c..517e9ec, tests: python3 -m pytest tests/marmar-lang -q → 15 passed in 13.96s)
- Task 4: Ruling: cookie language applies only to storefront services (Store API, wc-ajax); editor REST (wp/v2) and wp-admin always lv — prevents the block editor from loading/saving English text over Latvian; added test_rest_editor_untouched — cost if wrong: none for shoppers
- Task 4: Ruling: cart/checkout/my-account pages exempt from missing-translation redirect — spec requires checkout in every language — cost if wrong: Latvian page titles on /en/cart until translated
Task 4: complete (commits 517e9ec..bb3b636, tests: python3 -m pytest tests/marmar-lang -q → 23 passed in 15.07s)
- Task 5: Ruling: hreflang <link> tags excluded from Task 3 link assertions (they point to other languages by design); test site marks Rank Math as configured (rank_math_is_configured etc.) so its head output runs as on the live site — cost if wrong: none
Task 5: complete (commits c35f875..482a815, tests: python3 -m pytest tests/marmar-lang -q → 29 passed in 20.04s)
Task 6: complete (commits 482a815..add53d9, tests: python3 -m pytest tests/marmar-lang -q → 32 passed in 24.38s)
- Task 7: Ruling: /en/checkout/ test uses a session with an item — WooCommerce redirects empty checkout to cart by design — cost if wrong: none
Task 7: complete (commits add53d9..c480fb8, tests: python3 -m pytest tests/marmar-lang -q → 38 passed in 30.67s)
- Task 8: Ruling: language switcher links excluded from Task 3 link assertions (point to other languages by design); completed-email test checks English vs Latvian instead of exact WooCommerce subject wording (WC 11 says 'is on its way!') — cost if wrong: none
Task 8: complete (commits c480fb8..a4ef97f, tests: python3 -m pytest tests/marmar-lang -q → 46 passed in 48.66s)
- Task 9: Ruling: safety-test normalization also drops Blocksy random search ids and WooCommerce related-products block (random per request); coexistence test checks card titles, not alt attributes — image alt translation is outside v1 (documented in rollout as known limitation) — cost if wrong: Latvian alt texts on /en/ pages
Task 9: complete (commits a4ef97f..e7eb3a6, tests: python3 -m pytest tests/marmar-lang -q → 49 passed in 49.57s)
- Final: Ruling: test_cookie_set/shopper updated — language cookie is now set by inline script (review Important 5b) instead of Set-Cookie; tests emulate the browser running it — cost if wrong: none
Final review: fresh reviewer (opus) — 0 Critical, 8 Important, 11 Minor.
- Final: fixed I1 email locale restore (woocommerce_allow_restoring_email_locale, order from ctx, switch result checked) — test_bulk_emails_each_in_order_language; Ruling: test passed before the fix in this env (CLI locale = site locale hides the bug) — fix verified against WooCommerce 11 source (class-wc-email.php:439/459) — cost if wrong: wrong-language emails in bulk admin actions
- Final: fixed I2 rollout doc language packs + fallback when switch_to_locale fails — test_email_falls_back_when_language_pack_missing; Ruling: passed before fix for the same CLI-locale reason
- Final: fixed I3 EN listings only translated items, links to untranslated → lv, shop page handled — test_untranslated_products_hidden_on_en_archive RED→GREEN
- Final: fixed I4 redirect_canonical keeps language — test_canonical_redirect_keeps_language RED→GREEN
- Final: fixed I5 cookie language only for Store API cart/checkout/batch + wc-ajax, no-store/LiteSpeed nocache there, cookie via inline script (no Set-Cookie on pages) — test_store_api_products_ignore_cookie, test_language_cookie_set_by_page_script RED→GREEN; real LiteSpeed check moved to rollout step 7
- Final: fixed I6 translated content passes the_content once — test_translated_content_rendered_once RED→GREEN
- Final: fixed I7 /lv/ 301 only when public — test_legacy_lv_redirect_only_when_public RED→GREEN
- Final: fixed I8 Rank Math JSON-LD Product name/description — test_json_ld_translated; Ruling: test site has no Product schema (Rank Math free default) so test passed before fix; filter matches live Product entity shape — cost if wrong: Latvian description in EN schema
- Final: minor (deferred): save_post may copy mm_tr onto another post saved in same request (guard post_ID)
- Final: minor (deferred): order language not updated if visitor switches language mid-checkout
- Final: minor (deferred): mini-cart fragments name ignores language
- Final: minor (deferred): /{lang}/wp-json not treated as service
- Final: minor (deferred): hidden mode resets language after textdomains loaded (cookie leftovers)
- Final: minor (deferred): hreflang built from request path, not canonical permalink
- Final: minor (deferred): sitemap N+1 queries; includes system/noindex pages; trim mismatch
- Final: minor (deferred): email links not prefixed
- Final: minor (deferred): switcher added to every theme_location menu (incl. footer)
- Final: minor (deferred): wp_kses_post strips embeds in translated content
- Final: minor (deferred): /LV/ uppercase and POST to /lv/ not handled
