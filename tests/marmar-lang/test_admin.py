import json
import re

import requests

from conftest import SITE, get


def admin():
    s = requests.Session()
    s.get(SITE + "/wp-login.php", timeout=60)
    s.post(SITE + "/wp-login.php", data={"log": "admin", "pwd": "admin", "wp-submit": "Log In", "testcookie": "1"},
           timeout=60)
    return s


def test_metabox_renders(ids):
    html = admin().get(f"{SITE}/wp-admin/post.php?post={ids['simple']}&action=edit", timeout=60).text
    assert "Tulkojumi" in html
    for f in ("title", "content", "excerpt", "seo_title", "seo_desc"):
        assert f'name="mm_tr[en][{f}]"' in html, f
    assert "Number candle 5 for cakes | MarMar Candles" in html  # current value shown


def test_metabox_saves(wpcli, ids):
    pid = ids["partial"]
    wpcli("eval", f"""
        wp_set_current_user(1);
        $_POST = ['mm_tr_nonce' => wp_create_nonce('mm_tr_save'), 'mm_tr' => ['en' => ['title' => 'Heart candle', 'excerpt' => 'Short heart text.']]];
        do_action('save_post', {pid}, get_post({pid}), true);
    """)
    assert wpcli("eval", f'echo get_post_meta({pid}, "_mm_tr_en_excerpt", true);') == "Short heart text."
    # without a valid nonce nothing is saved
    wpcli("eval", f"""
        wp_set_current_user(1);
        $_POST = ['mm_tr' => ['en' => ['excerpt' => 'HACK']]];
        do_action('save_post', {pid}, get_post({pid}), true);
    """)
    assert wpcli("eval", f'echo get_post_meta({pid}, "_mm_tr_en_excerpt", true);') == "Short heart text."
    wpcli("eval", f'delete_post_meta({pid}, "_mm_tr_en_excerpt");')


def test_term_fields_save(wpcli, ids):
    html = admin().get(f"{SITE}/wp-admin/term.php?taxonomy=product_cat&tag_ID={ids['cat']}", timeout=60).text
    assert 'name="mm_tr[en][name]"' in html and "Number candles" in html
    wpcli("eval", f"""
        wp_set_current_user(1);
        $_POST = ['mm_tr_nonce' => wp_create_nonce('mm_tr_save'), 'mm_tr' => ['en' => ['name' => 'Number candles', 'seo_title' => 'Number candles | MarMar']]];
        do_action('edited_product_cat', {ids['cat']}, 0);
    """)
    assert wpcli("eval", f'echo get_term_meta({ids["cat"]}, "_mm_tr_en_seo_title", true);') == "Number candles | MarMar"


def test_settings_page():
    html = admin().get(SITE + "/wp-admin/options-general.php?page=marmar-lang", timeout=60).text
    assert "MarMar Lang" in html
    for lang in ("en", "lt", "et"):
        assert f'name="mm_lang_enabled[]" value="{lang}"' in html
    assert "Publiski redzams" in html


def test_settings_sanitize(wpcli):
    out = wpcli("eval", 'echo wp_json_encode(MarMarLang\\Admin::sanitize_enabled(["en","lt","xx","lv"]));')
    assert json.loads(out) == ["en", "lt"]


def menu_html(path):
    html = get(path).text
    return " ".join(re.findall(r'<ul[^>]+class="menu"[^>]*>(.*?)</ul>', html, re.S))


def test_switcher_in_menu():
    m = menu_html("/en/product/ciparu-svece-5/")
    assert 'class="mm-switch' in m
    assert f'href="{SITE}/product/ciparu-svece-5/"' in m and ">LV<" in m
    assert ">EN<" in m


def test_switcher_hides_missing_language():
    m = menu_html("/product/galda-svece/")
    assert ">EN<" not in m


def test_switcher_hidden_for_guests(option):
    option("mm_lang_public", False)
    assert 'class="mm-switch' not in menu_html("/product/ciparu-svece-5/")
