<?php
/**
 * Plugin Name:       MarMar QA labojumi
 * Description:       Testēšanā atrasto kļūdu labojumi: latviski teksti meklēšanā, 404 lapā un kases kļūdu paziņojumos; video galerijas faili tikai produktiem ar video; 301 no demo lapas «About Us»; grozs, kase un konts netiek iekļauti sitemap.
 * Version:           1.9.27
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_QA_Fixes {

	/** Vecās / demo adreses → jaunā vieta (301). Ceļš bez valodas prefiksa. */
	const REDIRECTS = array(
		'about-us' => '/lv/',
		'product/spiralveida-galda-svece-ice-blue' => '/lv/product-category/interjera-sveces/galda-sveces/', // 22816 (08.11 ассортимент)
		'product/gaisi-zala-spiralveida-galda-svece' => '/lv/product-category/interjera-sveces/galda-sveces/', // 22784 (08.11 ассортимент)
		'product/gaisi-roza-spiralveida-galda-svece' => '/lv/product-category/interjera-sveces/galda-sveces/', // 22783 (08.11 ассортимент)
		'product/skaistumkopsanas-davanu-komplekts-ar-bubble-sveci-2' => '/lv/product-category/davanas/sveces-davanai/', // 22273 (08.11 ассортимент)
		'product/dabiga-vaska-galda-svece-25-cm-bumbinas' => '/lv/product-category/interjera-sveces/galda-sveces/', // 21906 (08.11 ассортимент)
		'product/dabiga-vaska-galda-spiralveida-svece-18-cm-dazadas-krasas' => '/lv/product-category/interjera-sveces/galda-sveces/', // 21429 (08.11 ассортимент)
		'product/dabiga-vaska-stavsvece-burgundy-krasa' => '/lv/product-category/interjera-sveces/dizaina-sveces/', // 18828 (08.11 ассортимент)
		'product/eleganta-roku-darba-stavsvece-puderroza' => '/lv/product-category/interjera-sveces/dizaina-sveces/', // 18827 (08.11 ассортимент)
		'product/stavsvece-no-dabiga-vaska-dzeltena' => '/lv/product-category/interjera-sveces/dizaina-sveces/', // 18826 (08.11 ассортимент)
		'product/dabiga-vaska-svece-ar-rievotu-dizainu-lavandas' => '/lv/product-category/interjera-sveces/dizaina-sveces/', // 18822 (08.11 ассортимент)
		'product/dabiga-vaska-galda-svece-18-cm-rievota-gaisi-roza-ar-zeltu' => '/lv/product-category/interjera-sveces/galda-sveces/', // 15209 (08.11 ассортимент)
		'product/dabiga-vaska-peonijas-svece-dekorativa-zieda-formas-svece' => '/lv/product-category/interjera-sveces/dizaina-sveces/', // 11716 (08.11 ассортимент)
		'product/palo-santo-kocini-50g' => '/lv/product-category/davanas/sveces-davanai/', // 11530 (08.11 ассортимент)
		'product/bubble-svecu-davanu-komplekts-ar-zelta-dekoru-3-gab-ar-serkociniem' => '/lv/product-category/davanas/sveces-davanai/', // 10991 (08.11 ассортимент)
		'product/bubble-svecu-davanu-komplekts-ar-zelta-dekoru-4-gab-ar-serkociniem' => '/lv/product-category/davanas/sveces-davanai/', // 10987 (08.11 ассортимент)
		'product/dekorativa-bubble-svece-minimalisma-interjera-svece' => '/lv/product-category/interjera-sveces/dizaina-sveces/', // 10977 (08.11 ассортимент)
		'product/bubble-svece-ar-zelta-folijas-akcentiem' => '/lv/product-category/interjera-sveces/dizaina-sveces/', // 10956 (08.11 ассортимент)
		'product/davanu-karte-100-eur-vertiba-2' => '/lv/product/davanu-karte-100-eur-vertiba/', // 19854 (08.11 ассортимент)
		'product/davanu-karte-20-eur-vertiba-2' => '/lv/product/davanu-karte-20-eur-vertiba/', // 19855 (08.11 ассортимент)
		'product/davanu-karte-20-eur-vertiba-3' => '/lv/product/davanu-karte-20-eur-vertiba/', // 19856 (08.11 ассортимент)
		'product/davanu-karte-30-eur-vertiba-2' => '/lv/product/davanu-karte-30-eur-vertiba/', // 19857 (08.11 ассортимент)
		'product/davanu-karte-40-eur-vertiba-2' => '/lv/product/davanu-karte-40-eur-vertiba/', // 19858 (08.11 ассортимент)
		'product/davanu-karte-50-eur-vertiba-2' => '/lv/product/davanu-karte-50-eur-vertiba/', // 19859 (08.11 ассортимент)
		'product/bisu-vaska-dzimsanas-dienas-kuku-svecites-8cm-melnas-ar-zelta-foliju' => '/lv/product/melnas-dabiga-vaska-sveces-ar-foliju-8-cm/', // 18852 (09.11 melnās apvienotas)
		'product/melnas-bisu-vaska-sveces-kukam-un-tortem-ar-sudraba-foliju-8cm' => '/lv/product/melnas-dabiga-vaska-sveces-ar-foliju-8-cm/', // 19123 (09.11 melnās apvienotas)
		'product/melnas-bisu-vaska-sveces-kukam-un-tortem-ar-roza-foliju-8cm' => '/lv/product/melnas-dabiga-vaska-sveces-ar-foliju-8-cm/', // 18850 (09.11 melnās apvienotas)
		'product/melnas-bisu-vaska-sveces-kukam-un-tortem-ar-sarkanu-foliju-8cm' => '/lv/product/melnas-dabiga-vaska-sveces-ar-foliju-8-cm/', // 19121 (09.11 melnās apvienotas)
		'product/melnas-bisu-vaska-sveces-kukam-un-tortem-ar-zilo-foliju-8cm' => '/lv/product/melnas-dabiga-vaska-sveces-ar-foliju-8-cm/', // 19122 (09.11 melnās apvienotas)
		'product/melnas-dabiga-vaska-kuku-svecites-lilla-foliju' => '/lv/product/melnas-dabiga-vaska-sveces-ar-foliju-8-cm/', // 18853 (09.11 melnās apvienotas)
		'product/melnas-dabiga-vaska-kuku-svecites-zalu-foliju' => '/lv/product/melnas-dabiga-vaska-sveces-ar-foliju-8-cm/', // 18851 (09.11 melnās apvienotas)
		'product/melnas-bisu-vaska-sveces-kukam-un-tortem-ar-foliju-8cm' => '/lv/product/melnas-dabiga-vaska-sveces-ar-foliju-8-cm/', // 11092 (09.11 melnās apvienotas)
		'product/bisu-vaska-dzimsanas-dienas-kuku-svecites-8cm-melnas' => '/lv/product/melnas-dabiga-vaska-sveces-ar-foliju-8-cm/', // 15406 (09.11 melnās apvienotas)
		'product/bisu-vaska-svecites-kuku-un-torsu-rotasanai-8cm-kosi-zals' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 14657 (09.11 8 cm apvienotas)
		'product/bisu-vaska-svecites-kuku-un-torsu-dekoresanai-8cm-ice-blue-zils' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 14536 (09.11 8 cm apvienotas)
		'product/bisu-vaska-svecites-kuku-un-torsu-dekoresanai-8cm-bubble-gum-roza' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 12016 (09.11 8 cm apvienotas)
		'product/bisu-vaska-dzimsanas-dienas-kuku-svecites-8cm-kobaltzils' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11956 (09.11 8 cm apvienotas)
		'product/bisu-vaska-svecites-kuku-un-torsu-dekoresanai-8cm-gaisi-peleks' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11926 (09.11 8 cm apvienotas)
		'product/bisu-vaska-svecites-kuku-un-torsu-dekoresanai-8cm-besa' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11925 (09.11 8 cm apvienotas)
		'product/bisu-vaska-svecites-kukam-un-tortem-8cm-bruns' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11867 (09.11 8 cm apvienotas)
		'product/bisu-vaska-dzimsanas-dienas-kuku-svecites-8cm-pienbalts' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11866 (09.11 8 cm apvienotas)
		'product/bisu-vaska-dzimsanas-dienas-kuku-svecites-8cm-debeszils' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11837 (09.11 8 cm apvienotas)
		'product/bisu-vaska-svecites-kukam-un-tortem-8cm-olivzals' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11836 (09.11 8 cm apvienotas)
		'product/bisu-vaska-svecites-kuku-un-torsu-dekoresanai-8cm-krema' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11806 (09.11 8 cm apvienotas)
		'product/bisu-vaska-svecites-kuku-un-torsu-rotasanai-8cm-korallu' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11777 (09.11 8 cm apvienotas)
		'product/bisu-vaska-dzimsanas-dienas-kuku-svecites-8cm-gaisi-roza' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11776 (09.11 8 cm apvienotas)
		'product/dabiga-bisu-vaska-svecites-kuku-un-torsu-rotajumiem-8cm-oranzas' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11746 (09.11 8 cm apvienotas)
		'product/ekologiskas-bisu-vaska-kuku-svecites-8cm-lavandas' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11717 (09.11 8 cm apvienotas)
		'product/bisu-vaska-svecites-kukam-un-tortem-8cm-dzeltenas' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11511 (09.11 8 cm apvienotas)
		'product/bisu-vaska-svecites-kukam-un-tortem-8cm-fuksija' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11390 (09.11 8 cm apvienotas)
		'product/bisu-vaska-svecites-kukam-un-tortem-8cm-sarkanas' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11213 (09.11 8 cm apvienotas)
		'product/bisu-vaska-svecites-kukam-un-tortem-8cm-pasteltonu-mix' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11690 (09.11 8 cm apvienotas)
		'product/pasteltonu-kukas-svecites' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 22821 (09.11 8 cm apvienotas)
		'product/dzimsanas-dienas-svecisu-komplekts-8-cm-10-gab-krasu-mix-2' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 22059 (09.11 8 cm apvienotas)
		'product/dzimsanas-dienas-svecisu-komplekts-8-cm-10-gab-krasu-mix-4' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 22061 (09.11 8 cm apvienotas)
		'product/dzimsanas-dienas-svecisu-komplekts-8-cm-10-gab-krasu-mix-6' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 22062 (09.11 8 cm apvienotas)
		'product/bisu-vaska-svecites-kukam-un-tortem-8cm-ar-foliju' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 11063 (09.11 8 cm apvienotas)
		'product/roza-kukas-svecites' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 22820 (09.11 8 cm)
		'product/maigu-tonu-kukas-svecites-tievas-dzimsanas-dienas-sveces' => '/lv/product/dabiga-vaska-kuku-svecites-8-cm/', // 22854 (09.11 8 cm)
		'product/bordo-kuku-sveces-ar-zelta-dekoru' => '/lv/product/dabiga-vaska-kuku-svecites-10-cm/', // 24867 (09.11 10 cm apvienotas)
		'product/kobaltzilas-kukas-svecites' => '/lv/product/dabiga-vaska-kuku-svecites-10-cm/', // 22717 (09.11 10 cm apvienotas)
		'product/bisu-vaska-kuku-sveces-aprikozu-krasa-10cm' => '/lv/product/dabiga-vaska-kuku-svecites-10-cm/', // 16639 (09.11 10 cm apvienotas)
		'product/bisu-vaska-svecites-kukam-un-tortem-smilsu-krasa-10-cm' => '/lv/product/dabiga-vaska-kuku-svecites-10-cm/', // 16698 (09.11 10 cm apvienotas)
		'product/bisu-vaska-kuku-sveces-10-cm-puteklaina-roza-krasa' => '/lv/product/dabiga-vaska-kuku-svecites-10-cm/', // 16638 (09.11 10 cm apvienotas)
		'product/bisu-vaska-kuku-sveces-10-cm-puderroza' => '/lv/product/dabiga-vaska-kuku-svecites-10-cm/', // 16637 (09.11 10 cm apvienotas)
		'product/roza-kukas-svecites-ar-spidumiem' => '/lv/product/dabiga-vaska-kuku-svecites-10-cm/', // 22718 (09.11 10 cm apvienotas)
		'product/dzimsanas-dienas-svecite-kukam-un-tortem-ar-spidumiem-10cm' => '/lv/product/dabiga-vaska-kuku-svecites-10-cm/', // 16728 (09.11 10 cm apvienotas)
		'product/bisu-vaska-svecites-kukam-un-tortem-10cm-ar-spidumiem' => '/lv/product/dabiga-vaska-kuku-svecites-10-cm/', // 15196 (09.11 10 cm apvienotas)
		'product/bisu-vaska-svecites-kukam-un-tortem-10cm-pasteltonu-mix' => '/lv/product/dabiga-vaska-kuku-svecites-10-cm/', // 14656 (09.11 10 cm apvienotas)
		'product/pasteltonu-kukas-svecites-ar-zelta-dekoru-dzimsanas-dienas-sveces' => '/lv/product/dabiga-vaska-kuku-svecites-10-cm/', // 22857 (09.11 10 cm apvienotas)
		'product/bisu-vaska-kuku-sveces-10-cm-pudertonu-mix' => '/lv/product/dabiga-vaska-kuku-svecites-10-cm/', // 16336 (09.11 10 cm apvienotas)
		'product/earth-wine-kukas-svecites' => '/lv/product/dabiga-vaska-kuku-svecites-10-cm/', // 22894 (09.11 10 cm apvienotas)
		'product/kukas-sveces-ar-rudzupukem' => '/lv/product/dabiga-vaska-kuku-svecites-10-cm/', // 22891 (09.11 10 cm apvienotas)
		'product/neitralu-tonu-kukas-svecites-tievas-dzimsanas-dienas-sveces' => '/lv/product/dabiga-vaska-kuku-svecites-10-cm/', // 22822 (09.11 10 cm apvienotas)
		'product/dabiga-vaska-tortes-sveces-10-gab' => '/lv/product/dabiga-vaska-kuku-svecites-10-cm/', // 22170 (09.11 10 cm apvienotas)
		'product/bisu-vaska-svecites-bumbinas-kukam-un-tortem-11cm' => '/lv/product/bumbinas-kuku-svecites-11-cm/', // 16337 (10.11 Bumbiņas apvienotas)
		'product/dzimsanas-dienas-svecite-perlites-kukam-un-tortem-krasu-palete-milk-blush-11cm' => '/lv/product/bumbinas-kuku-svecites-11-cm/', // 21967 (10.11 Bumbiņas apvienotas)
		'product/dzimsanas-dienas-svecite-perlites-kukam-un-tortem-persiku-11cm' => '/lv/product/bumbinas-kuku-svecites-11-cm/', // 18767 (10.11 Bumbiņas apvienotas)
		'product/dzimsanas-dienas-svecite-perlites-kukam-un-tortem-ice-blue-zils-11cm' => '/lv/product/bumbinas-kuku-svecites-11-cm/', // 18738 (10.11 Bumbiņas apvienotas)
		'product/dzimsanas-dienas-svecite-perlites-kukam-un-tortem-dzeltenas-11-cm' => '/lv/product/bumbinas-kuku-svecites-11-cm/', // 18737 (10.11 Bumbiņas apvienotas)
		'product/dzimsanas-dienas-svecite-perlites-kukam-un-tortem-lavandas-11-cm' => '/lv/product/bumbinas-kuku-svecites-11-cm/', // 18707 (10.11 Bumbiņas apvienotas)
		'product/bisu-vaska-svecites-perlites-kukam-un-tortem-pasteltonu-mix-11-cm' => '/lv/product/bumbinas-kuku-svecites-11-cm/', // 17567 (10.11 Bumbiņas apvienotas)
		'product/bisu-vaska-svecites-perlites-kukam-un-tortem-gaisi-roza-11cm' => '/lv/product/bumbinas-kuku-svecites-11-cm/', // 16730 (10.11 Bumbiņas apvienotas)
		'product/bisu-vaska-svecites-perlites-kukam-un-tortem-fuksija-11cm' => '/lv/product/bumbinas-kuku-svecites-11-cm/', // 16729 (10.11 Bumbiņas apvienotas)
		'product/roza-bumbinu-kukas-svecites-dekorativas-dzimsanas-dienas-sveces' => '/lv/product/bumbinas-kuku-svecites-11-cm/', // 22889 (10.11 Bumbiņas apvienotas)
		'product/pasteltonu-bumbinu-kukas-svecites-dzimsanas-dienas-sveces-tortei' => '/lv/product/bumbinas-kuku-svecites-11-cm/', // 22750 (10.11 Bumbiņas apvienotas)
		'product/roza-kremkrasas-bumbinu-kukas-sveces' => '/lv/product/bumbinas-kuku-svecites-11-cm/', // 22749 (10.11 Bumbiņas apvienotas)
		'product/kantaina-kuku-svece-dzimsanas-dienas-svecite-11cm' => '/lv/product/kantainas-kuku-svecites-11-cm/', // 17027 (10.11 Kantainās apvienotas)
		'product/dzimsanas-dienas-svecisu-komplekts-kantainas-formas-11cm-10-gab-krasu-mix-ar-spidumiem' => '/lv/product/kantainas-kuku-svecites-11-cm/', // 22083 (10.11 Kantainās apvienotas)
		'product/dzimsanas-dienas-svecisu-komplekts-zigzag-7-gab-krasu-mix' => '/lv/product/zigzag-kuku-svecites/', // 22069 (10.11 Zigzag apvienotas)
		'product/dzimsanas-dienas-svecisu-komplekts-zigzag-7-gab-pienbalts-ar-spidumiem' => '/lv/product/zigzag-kuku-svecites/', // 22071 (10.11 Zigzag apvienotas)
		'product/dzimsanas-dienas-svecisu-komplekts-zigzag-7-gab-zils-ar-spidumiem' => '/lv/product/zigzag-kuku-svecites/', // 22073 (10.11 Zigzag apvienotas)
		'product/dzimsanas-dienas-svecisu-komplekts-zigzag-7-gab-gaisi-zals-ar-zelta-spidumiem' => '/lv/product/zigzag-kuku-svecites/', // 22075 (10.11 Zigzag apvienotas)
		'product/dzimsanas-dienas-svecisu-komplekts-zigzag-7-gab-gaisi-roza-ar-spidumiem' => '/lv/product/zigzag-kuku-svecites/', // 22078 (10.11 Zigzag apvienotas)
		'product/dzimsanas-dienas-svecisu-komplekts-zigzag-7-gab-persiku-ar-spidumiem' => '/lv/product/zigzag-kuku-svecites/', // 22080 (10.11 Zigzag apvienotas)
		'product/vilnotas-kukas-svecites-krasainas-dzimsanas-dienas-sveces-10-gab' => '/lv/product/vilnotas-kuku-svecites/', // 22883 (10.11 Viļņotās)
		'product/persiku-krasas-vilnotas-kukas-svecites-ar-zelta-dekoru' => '/lv/product/vilnotas-kuku-svecites/', // 22858 (10.11 Viļņotās)
		'product/dzimsanas-dienas-svecite-vilnota-kukam-un-tortem-10cm' => '/lv/product/spirales-kuku-svecites-10-cm/', // 14956 (10.11 Spirāles)
		'product/bisu-vaska-kuku-svecite-vilnota-11cm' => '/lv/product/vilnis-kuku-svecites-11-cm/', // 18137 (10.11 Vilnis)
		'product/bisu-vaska-kuku-svecite-vilnis-11cm' => '/lv/product/vilnotas-kuku-svecites/', // 18854 (10.11 = Viļņotās)
	);

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'redirects' ), 1 );
		add_filter( 'gettext', array( __CLASS__, 'gettext' ), 20, 3 );
		add_filter( 'woocommerce_email_enabled_customer_on_hold_order', array( __CLASS__, 'no_onhold_mail_paysera' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'season_eta_js' ), 41 );
		add_action( 'woocommerce_email_before_order_table', array( __CLASS__, 'season_email_note' ), 5, 4 );
		add_filter( 'rank_math/frontend/title', array( __CLASS__, 'title_404' ), 20 );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'title_404' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'checkout_i18n' ), 100 );
		add_filter( 'rank_math/sitemap/entry', array( __CLASS__, 'sitemap_entry' ), 10, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'product_buffer' ), 2 );
		add_action( 'template_redirect', array( __CLASS__, 'page_buffer' ), 3 );
		add_action( 'wp_head', array( __CLASS__, 'css' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'verify_meta' ), 1 );
		// Katalogs: 24 preces lapā (dalās ar 2, 3 un 4 kolonnām) un plāksnīte «Dažādas krāsas».
		add_filter( 'loop_shop_per_page', array( __CLASS__, 'per_page' ), 99 );
		// Komentāri blogā un lapās izslēgti (spams, 09.11.2026). Produktu atsauksmes netiek skartas.
		add_filter( 'comments_open', array( __CLASS__, 'no_comments' ), 20, 2 );
		add_filter( 'pings_open', array( __CLASS__, 'no_comments' ), 20, 2 );
		add_filter( 'marmar_checkout_config', array( __CLASS__, 'min_order' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'single_option_js' ), 99 );
		add_filter( 'wp_nav_menu_items', array( __CLASS__, 'mobile_account_item' ), 20, 2 );
		// Mazā pasūtījuma piemaksa (mazumtirdzniecība) bloķēšanas vietā.
		add_action( 'init', array( __CLASS__, 'unhook_min_block' ), 20 );
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'small_order_fee' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'small_order_js_text' ), 40 );
	}

	const FEE_NAME = 'Mazā pasūtījuma piemaksa';

	private static function is_kc() {
		return class_exists( 'MarMar_Konditoru_Cenas' ) && MarMar_Konditoru_Cenas::is_konditor();
	}

	/** MarMar Checkout vairs nebloķē pasūtījumu zem 10 € (konditoru 30 € pārbaude paliek savā spraudnī). */
	public static function unhook_min_block() {
		if ( class_exists( 'MarMar_Checkout' ) ) {
			remove_action( 'woocommerce_check_cart_items', array( 'MarMar_Checkout', 'check_minimum' ) );
			remove_action( 'woocommerce_checkout_process', array( 'MarMar_Checkout', 'check_minimum' ) );
		}
	}

	/** Preču summa zem 10 € → piemaksa līdz 10 € (bez piegādes). Konditoriem nav. */
	public static function small_order_fee( $cart ) {
		if ( ( is_admin() && ! defined( 'DOING_AJAX' ) ) || self::is_kc() || $cart->is_empty() ) {
			return;
		}
		$items = (float) $cart->get_subtotal() + (float) $cart->get_subtotal_tax();
		if ( $items > 0 && $items < self::MIN_ORDER ) {
			$cart->add_fee( self::FEE_NAME, round( self::MIN_ORDER - $items, 2 ), false );
		}
	}

	/** Groza josliņas teksts (MarMar Checkout JS). */
	public static function small_order_js_text() {
		if ( self::is_kc() || ! wp_script_is( 'marmar-checkout', 'enqueued' ) ) {
			return;
		}
		wp_add_inline_script( 'marmar-checkout', 'if(window.MarMarCheckout&&MarMarCheckout.i18n){MarMarCheckout.i18n.minLeft="Līdz %1$s pievienojam mazā pasūtījuma piemaksu. Pievienojiet preces vēl par %2$s — un piemaksas nebūs";}', 'before' );
	}

	/** Telefonā konta ikona galvenē ir paslēpta (MarMar Design) — pieteikšanos liekam mobilajā izvēlnē. */
	public static function mobile_account_item( $items, $args ) {
		if ( empty( $args->theme_location ) || 'menu_mobile' !== $args->theme_location || ! function_exists( 'wc_get_page_permalink' ) ) {
			return $items;
		}
		$label = is_user_logged_in() ? 'Mans konts' : 'Pieslēgties / Mans konts';
		return $items . '<li class="menu-item mm-account-item"><a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" class="ct-menu-link"><span aria-hidden="true">👤</span>&nbsp;' . esc_html( $label ) . '</a></li>';
	}

	/** Ja atribūtam ir tikai viena vērtība (piem. «Daudzums: 10 gab») — izvēlamies to automātiski un rindu nerādām. */
	public static function single_option_js() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		?>
<script id="mm-single-option">
(function(){function run(){document.querySelectorAll('form.variations_form table.variations tr').forEach(function(tr){var lis=tr.querySelectorAll('li.variable-item'),sel=tr.querySelector('select');var opts=sel?[].filter.call(sel.options,function(o){return o.value}):[];
if(lis.length===1){if(!lis[0].classList.contains('selected'))lis[0].click();tr.classList.add('mm-single');}
else if(!lis.length&&opts.length===1){if(sel.value!==opts[0].value){sel.value=opts[0].value;if(window.jQuery)jQuery(sel).trigger('change');}tr.classList.add('mm-single');}});}
if(document.readyState!=='loading')setTimeout(run,300);else document.addEventListener('DOMContentLoaded',function(){setTimeout(run,300);});})();
</script>
		<?php
	}

	/** Minimālais mazumtirdzniecības pasūtījums no 08.11.2026 — 10 € (konditoriem paliek 30 €). */
	const MIN_ORDER = 10;

	public static function min_order( $c ) {
		$c['min_order'] = self::MIN_ORDER;
		return $c;
	}

	/** Teksti ar «5 €» par minimālo pasūtījumu citos spraudņos/snipetos → 10 €. Steidzamības «+5 €» netiek skarts. */
	public static function min_order_texts( $html ) {
		$html = str_replace( '— ar pārskaitījumu pēc rēķina.', '— uzreiz internetbankā (Paysera) vai ar pārskaitījumu pēc rēķina.', $html );
		if ( self::is_kc() ) {
			return $html;
		}
		$m    = self::MIN_ORDER;
		$html = preg_replace(
			'~Minimāl\w*\s+pasūtījum\w*(?:\s+summa)?\s*(?:—|–|-|ir)?\s*(<b>|<strong>)?(?:5|10)((?:\s|&nbsp;|\x{00A0}|\x{202F})*€)(</b>|</strong>)?~u',
			'Minimālais pasūtījums ${1}' . $m . '$2$3 — ja mazāk, starpību pieskaitām',
			$html
		);
		return preg_replace( '~Min\.\s*pasūtījums\s*(?:5|10)((?:\s|&nbsp;|\x{00A0}|\x{202F})*€)~u', 'Min. pasūtījums ' . $m . '$1 (ja mazāk — pieskaitām starpību)', $html );
	}

	public static function per_page() {
		return 24;
	}

	/** Apvienotās kartītes (visas krāsas vienā kartītē) — plāksnīte «Dažādas krāsas» uz foto katalogā. */
	const MERGED = array( 25256, 25282 );

	public static function colors_badge() {
		global $product;
		if ( ! $product instanceof WC_Product || ! in_array( (int) $product->get_id(), apply_filters( 'marmar_merged_products', self::MERGED ), true ) ) {
			return;
		}
		echo '<span class="mm-badge-colors">🎨 Dažādas krāsas</span>';
		static $js = false;
		if ( ! $js ) {
			$js = true;
			add_action( 'wp_footer', function () {
				echo '<script>document.querySelectorAll(".mm-badge-colors").forEach(function(b){var li=b.closest("li");var f=li&&li.querySelector("figure");if(f){f.style.position="relative";f.appendChild(b);}});</script>';
			}, 99 );
		}
	}

	public static function no_comments( $open, $post_id = 0 ) {
		return in_array( get_post_type( $post_id ), array( 'post', 'page', 'attachment' ), true ) ? false : $open;
	}

	/** Domēna verifikācija (Paysera, 09.11.2026). */
	public static function verify_meta() {
		echo '<meta name="paysera-verify" content="paysera-verify-7826bdda7bfcab1a57b017efd44d5ee6" />' . "\n";
	}

	/** Poga «uz augšu» (#fab-stt) bija zem čata pogas apakšējā labajā stūrī — paceļam virs tās. */
	public static function css() {
		echo '<style id="mm-qa-css">#fab-stt{bottom:88px!important;right:22px!important}'
			. '.paysera-hosted-box__methods{display:none!important}'
			. '.woocommerce-columns--addresses .woocommerce-column{width:auto!important;max-width:none!important;flex:none!important;float:none!important;min-width:0}'
			. '.woocommerce-columns--addresses .woocommerce-column address{width:auto!important;word-break:normal;overflow-wrap:anywhere}'
			. '@media(max-width:689px){.woocommerce-columns--addresses{grid-template-columns:1fr!important;gap:20px}}'
			. 'ul.products li.product{position:relative}.mm-badge-colors{position:absolute;top:12px;right:12px;z-index:5;background:rgba(255,255,255,.92);color:#5a4a4a;font-size:12px;font-weight:600;line-height:1;padding:7px 10px;border-radius:999px;box-shadow:0 2px 8px rgba(0,0,0,.08);pointer-events:none}'
			. '.single-product table.variations tr.mm-single,.single-product table.variations tr:has(ul.variable-items-wrapper>li.variable-item:only-child){display:none!important}</style>';
	}

	/** GPTranslate bootstrap.css (27 KB) nebloķē lapas attēlošanu — ielādējas fonā. */
	public static function page_buffer() {
		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return;
		}
		ob_start( array( __CLASS__, 'async_css' ) );
	}

	/**
	 * Clarity ielādējas pēc pirmās mijiedarbības vai 2,5 s pēc lapas ielādes — nebremzē pirmo attēlošanu.
	 * window.clarity rinda paliek uzreiz, tāpēc piekrišanas signāli nepazūd.
	 */
	public static function delay_clarity( $html ) {
		if ( false === strpos( $html, 'id="marmar-clarity"' ) ) {
			return $html;
		}
		$re  = '~t\s*=\s*l\.createElement\(r\);\s*t\.async\s*=\s*1;\s*t\.src\s*=\s*(\'https://www\.clarity\.ms/tag/\'\s*\+\s*i);\s*y\s*=\s*l\.getElementsByTagName\(r\)\[0\];\s*y\.parentNode\.insertBefore\(t,\s*y\);~';
		$rep = 'var go=function(){if(go.d)return;go.d=1;t=l.createElement(r);t.async=1;t.src=$1;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);};'
			. '["scroll","pointerdown","keydown","touchstart"].forEach(function(e){c.addEventListener(e,go,{once:true,passive:true});});'
			. 'c.addEventListener("load",function(){setTimeout(go,2500);});';
		return preg_replace( $re, $rep, $html, 1 );
	}

	public static function async_css( $html ) {
		if ( strlen( $html ) < 500 ) {
			return $html;
		}
		$html = self::delay_clarity( $html );
		$html = self::season_topbar( $html );
		$html = self::min_order_texts( $html );
		// Steidzamības izvēle produkta lapā tagad ir augstāk par termiņa bloku.
		$html = str_replace( 'Steidzami — 1–2 dienās (+5 €), atzīmējiet zemāk.', 'Steidzami — 1–2 dienās (+5 €): atzīmējiet «Steidzams pasūtījums» augstāk.', $html );
		return preg_replace_callback(
			"~<link rel=['\"]stylesheet['\"] id=['\"]bootstrap-css-css['\"]([^>]*?)media=['\"]all['\"]\s*/?>~i",
			function ( $m ) {
				return '<link rel="stylesheet" id="bootstrap-css-css"' . $m[1] . 'media="print" onload="this.media=\'all\'" /><noscript><link rel="stylesheet"' . $m[1] . 'media="all" /></noscript>';
			},
			$html,
			1
		);
	}

	/** Video galerijas skripti/stili (video.js 150 KB + Font Awesome) — tikai produktiem ar video. */
	public static function product_buffer() {
		if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		ob_start( array( __CLASS__, 'strip_video_assets' ) );
	}

	public static function strip_video_assets( $html ) {
		if ( strlen( $html ) < 500 || false !== strpos( $html, 'data-vwg-video=' ) || false !== strpos( $html, '<video' ) ) {
			return $html;
		}
		return preg_replace(
			array(
				"~<link[^>]+id=['\"]vwg_fontawesome-css['\"][^>]*>\s*~i",
				"~<link[^>]+id=['\"]videojs-css-css['\"][^>]*>\s*~i",
				"~<script[^>]+id=['\"]videojs-js['\"][^>]*></script>\s*~i",
			),
			'',
			$html
		);
	}

	public static function redirects() {
		if ( ! is_404() ) {
			return;
		}
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		$path = preg_replace( '~^(lv|en|ru|lt|et)/~', '', $path );
		if ( isset( self::REDIRECTS[ $path ] ) ) {
			wp_safe_redirect( home_url( self::REDIRECTS[ $path ] ), 301 );
			exit;
		}
	}

	public static function gettext( $translation, $text, $domain ) {
		if ( 'blocksy' === $domain && 0 === strpos( $text, 'Search Results for' ) ) {
			return str_replace( 'Search Results for', 'Meklēšanas rezultāti:', $text );
		}
		if ( 'woocommerce' === $domain && $translation === $text && isset( self::MAIL_LV[ $text ] ) ) {
			return self::MAIL_LV[ $text ];
		}
		return $translation;
	}

	/* ---------------- Svētku termiņi un atvaļinājums (Alīnas lēmums 11.11.2026) ---------------- */

	const PAUSE_FROM = '2026-11-26';   // atvaļinājums: pasūtījumus pieņem, neizgatavo
	const PAUSE_TO   = '2026-12-03';   // izgatavošana atsākas 4.12
	const PAUSE_SHOW = '2026-11-19';   // paziņojumu rāda nedēļu iepriekš

	private static function today() {
		return wp_date( 'Y-m-d' );
	}

	private static function pause_msg_on( $d = null ) {
		$d = $d ? $d : self::today();
		return $d >= self::PAUSE_SHOW && $d <= self::PAUSE_TO;
	}

	/** @return string[] Svētku termiņu teksti pēc datuma (pirmais — svarīgākais). */
	private static function season_lines( $d = null ) {
		$d = $d ? $d : self::today();
		if ( $d > '2026-12-31' ) {
			return array();
		}
		if ( $d <= '2026-12-13' ) {
			return array( 'Ziemassvētkiem — pasūti līdz 13.12', 'Steidzami (+5 €) — līdz 17.12' );
		}
		if ( $d <= '2026-12-17' ) {
			return array( 'Ziemassvētkiem — steidzami (+5 €) līdz 17.12', 'Jaunajam gadam — līdz 18.12' );
		}
		if ( $d <= '2026-12-18' ) {
			return array( 'Jaunajam gadam — pasūti līdz 18.12', 'Steidzami (+5 €) — līdz 22.12' );
		}
		if ( $d <= '2026-12-22' ) {
			return array( 'Jaunajam gadam — steidzami (+5 €) līdz 22.12', 'Bezmaksas piegāde no 50 €' );
		}
		return array( 'Pasūtījumus pēc 22.12 līdz svētkiem nepiegādāsim', 'Bezmaksas piegāde no 50 €' );
	}

	/** Augšējā josla (MarMar Design): „Gatavs 5–7 dienās” / „Steidzami 1–2 dienās” → termiņi un atvaļinājums. */
	public static function season_topbar( $html ) {
		if ( false === strpos( $html, 'mmd-topbar' ) ) {
			return $html;
		}
		$lines = self::season_lines();
		if ( self::pause_msg_on() ) {
			$lines = array( 'Atvaļinājums 26.11–3.12 — izgatavošana no 4.12', $lines ? $lines[0] : 'Steidzami 1–2 dienās' );
		}
		if ( ! $lines ) {
			return $html;
		}
		$html = str_replace( '<span>Gatavs 5–7 dienās</span>', '<span>' . esc_html( $lines[0] ) . '</span>', $html );
		return str_replace( '<span class="mmd-d">Steidzami 1–2 dienās</span>', '<span class="mmd-d">' . esc_html( $lines[1] ) . '</span>', $html );
	}

	/** Darbdienas pēc MarMar Checkout loģikas (brīvdienas + LV svētki). */
	private static function add_workdays_lv( DateTimeImmutable $d, $n ) {
		$h = array();
		foreach ( array( (int) $d->format( 'Y' ), (int) $d->format( 'Y' ) + 1 ) as $y ) {
			foreach ( array( '01-01', '05-01', '05-04', '06-23', '06-24', '11-18', '12-24', '12-25', '12-26', '12-31' ) as $md ) {
				$h[] = $y . '-' . $md;
			}
		}
		while ( $n > 0 ) {
			$d = $d->modify( '+1 day' );
			if ( (int) $d->format( 'N' ) < 6 && ! in_array( $d->format( 'Y-m-d' ), $h, true ) ) {
				$n--;
			}
		}
		return $d;
	}

	/** Izgatavošana kalendārajās dienās, izlaižot atvaļinājuma dienas. */
	private static function make_done( DateTimeImmutable $now, $days ) {
		$from = new DateTimeImmutable( self::PAUSE_FROM, wp_timezone() );
		$to   = ( new DateTimeImmutable( self::PAUSE_TO, wp_timezone() ) )->modify( '+1 day' ); // 4.12 00:00
		$d    = $now;
		if ( $d >= $from && $d < $to ) {
			$d = $to;
		}
		$end = $d->modify( '+' . (int) $days . ' days' );
		if ( $d < $from && $end >= $from ) {
			$end = $end->modify( '+' . (int) $from->diff( $to )->days . ' days' );
		}
		return $end;
	}

	private static function lv_range2( DateTimeImmutable $f, DateTimeImmutable $t ) {
		$m = array( 1 => 'janvārī', 'februārī', 'martā', 'aprīlī', 'maijā', 'jūnijā', 'jūlijā', 'augustā', 'septembrī', 'oktobrī', 'novembrī', 'decembrī' );
		if ( $f->format( 'm' ) === $t->format( 'm' ) ) {
			return $f->format( 'j' ) . '.–' . $t->format( 'j' ) . '. ' . $m[ (int) $t->format( 'n' ) ];
		}
		return $f->format( 'j' ) . '. ' . $m[ (int) $f->format( 'n' ) ] . ' – ' . $t->format( 'j' ) . '. ' . $m[ (int) $t->format( 'n' ) ];
	}

	/** Kases „Saņemsiet ~…” ņem vērā atvaļinājumu (pārraksta MarMar Checkout aprēķinu). */
	public static function season_eta_js() {
		if ( ! wp_script_is( 'marmar-checkout', 'enqueued' ) || wp_date( 'Y-m-d' ) > self::PAUSE_TO ) {
			return;
		}
		$now  = new DateTimeImmutable( 'now', wp_timezone() );
		$cfg  = class_exists( 'MarMar_Checkout' ) ? MarMar_Checkout::cfg() : array( 'make_days' => array( 5, 7 ), 'make_days_express' => array( 1, 2 ), 'ship_days' => array( 1, 2 ) );
		$out  = array();
		foreach ( array( 'estimate' => $cfg['make_days'], 'estimateFast' => $cfg['make_days_express'] ) as $k => $mk ) {
			$f         = self::add_workdays_lv( self::make_done( $now, $mk[0] ), $cfg['ship_days'][0] );
			$t         = self::add_workdays_lv( self::make_done( $now, $mk[1] ), $cfg['ship_days'][1] );
			$out[ $k ] = self::lv_range2( $f, $t );
		}
		$js = 'if(window.MarMarCheckout){MarMarCheckout.estimate=' . wp_json_encode( $out['estimate'] ) . ';MarMarCheckout.estimateFast=' . wp_json_encode( $out['estimateFast'] ) . ';';
		if ( self::pause_msg_on() ) {
			$js .= 'if(MarMarCheckout.i18n){MarMarCheckout.i18n.etaNote="pēc apmaksas · 26.11–3.12 atvaļinājums, izgatavošana no 4.12";}';
		}
		wp_add_inline_script( 'marmar-checkout', $js . '}', 'before' );
	}

	/** Klienta e-pastos (apstrādē / gaida): atvaļinājums un svētku termiņi. */
	/**
	 * Pagaidu (līdz 31.12.2026): klienta e-pastos «Apstrādē» un «Gaida apmaksu» —
	 * vai pasūtījums paspēs līdz Ziemassvētkiem / Jaunajam gadam + atvaļinājums.
	 * Termiņi (Alīnas lēmums 11.11): Ziemassvētki — parasts līdz 13.12, steidzams līdz 17.12;
	 * Jaunais gads — parasts līdz 18.12, steidzams līdz 22.12.
	 */
	public static function season_email_note( $order, $sent_to_admin, $plain_text, $email ) {
		if ( $sent_to_admin || ! $order instanceof WC_Order || ! $email || ! in_array( $email->id, array( 'customer_processing_order', 'customer_on_hold_order' ), true ) ) {
			return;
		}
		$created = $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d' ) : self::today();
		if ( $created > '2026-12-31' ) {
			return;
		}
		$unpaid  = 'customer_on_hold_order' === $email->id;
		$d       = $unpaid ? self::today() : ( $order->get_date_paid() ? $order->get_date_paid()->date_i18n( 'Y-m-d' ) : $created );
		$express = false;
		foreach ( $order->get_fees() as $fee ) {
			if ( false !== mb_stripos( $fee->get_name(), 'steidzam' ) ) {
				$express = true;
			}
		}
		$xmas = $express ? '2026-12-17' : '2026-12-13';
		$ny   = $express ? '2026-12-22' : '2026-12-18';
		$dm   = function ( $ymd ) {
			return (int) substr( $ymd, 8, 2 ) . '.' . substr( $ymd, 5, 2 ) . '.';
		};

		$notes = array();
		if ( self::pause_msg_on( $created ) ) {
			$notes[] = '🕯️ No 26.11 līdz 3.12 esam atvaļinājumā: pasūtījumus pieņemam, izgatavot sākam no 4.12.';
		}
		if ( $created >= '2026-11-11' ) {
			if ( $unpaid ) { // gaida apmaksu: termiņš skaitās no apmaksas dienas
				if ( $d <= $xmas ) {
					$notes[] = '🎄 Ja apmaksāsiet līdz ' . $dm( $xmas ) . ', sveces būs pie jums vēl pirms Ziemassvētkiem.';
				} elseif ( $d <= $ny ) {
					$notes[] = '🥂 Līdz Ziemassvētkiem diemžēl vairs nepaspēsim, bet, ja apmaksāsiet līdz ' . $dm( $ny ) . ', sveces paspēs līdz Jaunajam gadam.';
				} else {
					$notes[] = '🕯️ Lūdzu, ņemiet vērā: līdz svētkiem šo pasūtījumu piegādāt vairs nepaspēsim.';
				}
			} elseif ( $d <= $xmas ) {
				$notes[] = '🎄 Jūsu sveces būs pie jums vēl pirms Ziemassvētkiem.';
			} elseif ( $d <= $ny ) {
				$notes[] = '🥂 Līdz Ziemassvētkiem diemžēl nepaspēsim, bet līdz Jaunajam gadam sveces būs pie jums.';
			} else {
				$notes[] = '🕯️ Lūdzu, ņemiet vērā: līdz svētkiem šo pasūtījumu piegādāt nepaspēsim.';
			}
		}
		foreach ( $notes as $n ) {
			echo $plain_text ? "\n" . esc_html( $n ) . "\n" : '<p style="margin:0 0 16px;padding:12px 14px;background:#fbf3f1;border-radius:8px">' . esc_html( $n ) . '</p>';
		}
	}

	/** Paysera „pending_settlement” liek pasūtījumu „Gaida apstrādi” — klientam nesūtām „gaidām apmaksu” vēstuli (viņš jau samaksāja). */
	public static function no_onhold_mail_paysera( $enabled, $order ) {
		if ( $order instanceof WC_Order && 0 === strpos( (string) $order->get_payment_method(), 'paysera' ) ) {
			return false;
		}
		return $enabled;
	}

	/** WooCommerce e-pastu teksti, kam latviešu tulkojuma vēl nav (WC 9.8+ „email improvements"). */
	const MAIL_LV = array(
		// Konts, parole (WooCommerce lv tulkojumā trūkst; pievienots 11.11.2026).
		'This password reset key is for a different user account. Please log out and try again.' => 'Šī paroles atjaunošanas saite ir citam kontam. Lūdzu, izrakstieties un mēģiniet vēlreiz.',
		'Please wait a moment before requesting another link to change your password.' => 'Lūdzu, pagaidiet brīdi, pirms pieprasāt jaunu paroles maiņas saiti.',
		'Sorry, we were unable to resend the link. Please try again.' => 'Diemžēl neizdevās nosūtīt saiti vēlreiz. Lūdzu, mēģiniet vēlreiz.',
		'We have emailed you a new link to change your password.' => 'Mēs nosūtījām jums uz e-pastu jaunu saiti paroles maiņai.',
		'Display name cannot be changed to email address due to privacy concern.' => 'Privātuma dēļ attēlojamo vārdu nevar mainīt uz e-pasta adresi.',
		'An error occurred while saving account details: %s' => 'Saglabājot konta datus, radās kļūda: %s',
		'Please choose product options for %1$s.' => 'Lūdzu, izvēlieties preces “%1$s” opcijas.',
		'Please choose product options by visiting <a href="%1$s" title="%2$s">%2$s</a>.' => 'Lūdzu, izvēlieties preces opcijas: <a href="%1$s" title="%2$s">%2$s</a>.',
		'Your account was created successfully and a password has been sent to your email address.' => 'Konts ir izveidots, un parole nosūtīta uz jūsu e-pastu.',
		'Your account was created successfully. Your login details have been sent to your email address.' => 'Konts ir izveidots. Pieslēgšanās dati nosūtīti uz jūsu e-pastu.',
		'Reset your password for {site_title}' => 'Paroles atjaunošana — {site_title}',
		'%1$sResend%2$s' => '%1$sNosūtīt vēlreiz%2$s',
		'Your account is using a temporary password. We emailed you a link to change your password.' => 'Jūsu kontam ir pagaidu parole. Mēs nosūtījām uz e-pastu saiti paroles maiņai.',
		'Pay for order %s' => 'Apmaksāt pasūtījumu %s',
		'View order %s' => 'Skatīt pasūtījumu %s',
		'Cancel order %s' => 'Atcelt pasūtījumu %s',
		'Please provide a valid account username.' => 'Lūdzu, norādiet derīgu lietotājvārdu.',
		'If you didn’t make this request, just ignore this email. If you’d like to proceed, reset your password via the link below:' => 'Ja jūs to nepieprasījāt, vienkārši ignorējiet šo e-pastu. Lai turpinātu, atjaunojiet paroli, izmantojot saiti zemāk:',
		'Reset your password' => 'Atjaunot paroli',
		'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">billing address</a>, and <a href="%3$s">edit your password and account details</a>.' => 'Savā kontā varat apskatīt <a href="%1$s">pēdējos pasūtījumus</a>, mainīt <a href="%2$s">rēķina adresi</a> un <a href="%3$s">mainīt paroli un konta datus</a>.',
		'This will be how your name will be displayed in the account section and in reviews' => 'Šādi jūsu vārds būs redzams kontā un atsauksmēs',
		'This will be how your name will be displayed in the account section' => 'Šādi jūsu vārds būs redzams kontā',
		'A link to set a new password will be sent to your email address.' => 'Saite jaunas paroles iestatīšanai tiks nosūtīta uz jūsu e-pastu.',
		'View order number %s' => 'Skatīt pasūtījumu Nr. %s',
		'%1$s order number %2$s' => '%1$s pasūtījumu Nr. %2$s',
		'Your withdrawal has been submitted.' => 'Jūsu atteikums ir iesniegts.',
		'We\'ve emailed an acknowledgment to %s with your details and the date and time of submission. Keep it as proof of your withdrawal.' => 'Mēs nosūtījām apstiprinājumu uz %s ar jūsu datiem un iesniegšanas laiku. Saglabājiet to kā atteikuma apliecinājumu.',
		'We\'ll review your request and contact you about next steps, including any refund due.' => 'Mēs izskatīsim pieprasījumu un sazināsimies par turpmākajiem soļiem, tostarp par iespējamo naudas atmaksu.',
		'Back to the shop' => 'Atpakaļ uz veikalu',
		'Check your details before confirming.' => 'Pirms apstiprināšanas pārbaudiet datus.',
		'Nothing has been sent yet. Your withdrawal is submitted when you select "Confirm withdrawal".' => 'Vēl nekas nav nosūtīts. Atteikums tiks iesniegts, kad nospiedīsiet «Apstiprināt atteikumu».',
		'Order withdrawal progress' => 'Atteikuma soļi',
		'Your details' => 'Jūsu dati',
		'Review and confirm' => 'Pārbaudiet un apstipriniet',
		'After you confirm, we\'ll email an acknowledgment to %s with these details and the date and time of submission. Keep it as proof of your withdrawal.' => 'Pēc apstiprināšanas nosūtīsim apstiprinājumu uz %s ar šiem datiem un iesniegšanas laiku. Saglabājiet to kā atteikuma apliecinājumu.',
		'Confirm withdrawal' => 'Apstiprināt atteikumu',
		'Edit details' => 'Labot datus',
		'Tell us if you want to withdraw from an order placed on this store. You do not need to give a reason.' => 'Paziņojiet mums, ja vēlaties atteikties no šajā veikalā veikta pasūtījuma. Iemesls nav jānorāda.',
		'Some items, like personalized products, may not be eligible. We review every request and reply by email.' => 'Uz dažām precēm, piemēram, personalizētām, atteikuma tiesības neattiecas. Katru pieprasījumu izskatām un atbildam e-pastā.',
		'No reason needed. If you selected specific items, list them here.' => 'Iemesls nav jānorāda. Ja atsakāties tikai no dažām precēm, norādiet tās šeit.',
		'Continue to review' => 'Turpināt',
		'Awaiting confirmation' => 'Gaida apstiprinājumu',
		'Stock notifications awaiting confirmation' => 'Paziņojumi par pieejamību, kas gaida apstiprinājumu',
		'Active stock notifications' => 'Aktīvie paziņojumi par pieejamību',
		'Date signed up' => 'Pieteikšanās datums',
		'Product unavailable' => 'Prece nav pieejama',
		'Resend verification' => 'Nosūtīt apstiprinājumu vēlreiz',
		'We’re getting in touch to let you know that order #%1$s from %2$s has been cancelled.' => 'Informējam, ka pasūtījums #%1$s (%2$s) ir atcelts.',
		'Notification to let you know — order #%1$s belonging to %2$s has been cancelled:'      => 'Informējam, ka pasūtījums #%1$s (%2$s) ir atcelts:',
		'You’ve received a new order from %s:'                                                   => 'Saņemts jauns pasūtījums no %s:',
		'You’ve received the following order from %s:'                                          => 'Saņemts šāds pasūtījums no %s:',
		'Manage the order with the app.'                                                          => 'Pārvaldiet pasūtījumu lietotnē.',
		'Unfortunately, the payment for order #%1$s from %2$s has failed. The order was as follows:' => 'Diemžēl pasūtījuma #%1$s (%2$s) apmaksa neizdevās. Pasūtījums:',
		'Payment for order #%1$s from %2$s has failed. The order was as follows:'                 => 'Pasūtījuma #%1$s (%2$s) apmaksa neizdevās. Pasūtījums:',
		'Your order from %s has been refunded.'                                                   => 'Jūsu pasūtījumam no %s ir veikta naudas atmaksa.',
		'Your order from %s has been partially refunded.'                                         => 'Jūsu pasūtījumam no %s ir veikta daļēja naudas atmaksa.',
		'Your order on %s has been refunded. There are more details below for your reference:'   => 'Jūsu pasūtījumam %s ir veikta naudas atmaksa. Sīkāka informācija zemāk:',
		'Your order on %s has been partially refunded. There are more details below for your reference:' => 'Jūsu pasūtījumam %s ir veikta daļēja naudas atmaksa. Sīkāka informācija zemāk:',
		"Unfortunately, we couldn't complete your order due to an issue with your payment method." => 'Diemžēl pasūtījumu nevarējām pabeigt, jo neizdevās apmaksa.',
		"If you'd like to continue with your purchase, please return to %s and try a different method of payment." => 'Ja vēlaties turpināt pirkumu, atgriezieties %s un izvēlieties citu apmaksas veidu.',
		'Your order details are as follows:'                                                      => 'Jūsu pasūtījuma informācija:',
		'Sorry, your order on %1$s was unsuccessful. Your order details are below, with a link to try your payment again: %2$s' => 'Diemžēl Jūsu pasūtījums %1$s netika apmaksāts. Pasūtījuma informācija zemāk, un šeit varat mēģināt apmaksāt vēlreiz: %2$s',
		'An order has been created for you on %1$s. Your order details are below, with a link to make payment when you’re ready: %2$s' => 'Jums ir izveidots pasūtījums %1$s. Pasūtījuma informācija zemāk, un šeit varat to apmaksāt: %2$s',
		'Pay for this order'                                                                      => 'Apmaksāt pasūtījumu',
		'Thanks for creating an account on %s. Here’s a copy of your user details.'              => 'Paldies, ka izveidojāt kontu %s! Jūsu lietotāja dati:',
		'You can access your account area to view orders, change your password, and more via the link below:' => 'Savā kontā varat redzēt pasūtījumus, mainīt paroli un daudz ko citu:',
		'Set your new password.'                                                                  => 'Iestatiet jaunu paroli.',
		'Click here to set your new password.'                                                    => 'Spiediet šeit, lai iestatītu jaunu paroli.',
		'If you didn’t make this request, just ignore this email. If you’d like to proceed, reset your password via the link below:' => 'Ja to nepieprasījāt Jūs, vienkārši ignorējiet šo e-pastu. Lai turpinātu, atjaunojiet paroli, spiežot uz saites zemāk:',
		'Reset your password'                                                                     => 'Atjaunot paroli',
		'Click here to reset your password'                                                       => 'Spiediet šeit, lai atjaunotu paroli',
	);

	public static function title_404( $title ) {
		return is_404() ? 'Lapa nav atrasta - MarMar CANDLES' : $title;
	}

	/** Bloku kases (JS) validācijas teksti nav tulkoti latviski — papildinām. */
	public static function checkout_i18n() {
		if ( ! function_exists( 'is_checkout' ) || ! ( is_checkout() || is_cart() ) ) {
			return;
		}
		$data = array(
			'Please enter a valid %s'           => array( 'Lūdzu, aizpildiet lauku: %s' ),
			'Please enter a valid email address' => array( 'Lūdzu, ievadiet derīgu e-pasta adresi' ),
			'Please select a %s'                => array( 'Lūdzu, izvēlieties: %s' ),
		);
		wp_add_inline_script( 'wp-i18n', 'wp.i18n.setLocaleData(' . wp_json_encode( $data ) . ',"woocommerce");' );
		add_action( 'wp_footer', array( __CLASS__, 'paysera_text_js' ), 99 );
	}

	/** Paysera bloka apraksts kasē nāk angliski (JS) — aizvietojam latviski. */
	public static function paysera_text_js() {
		$map = array(
			'You will be taken to the Paysera payment page to choose how you pay. Banks, cards, wallets and instalments, all in one place.'
				=> 'Pēc noformēšanas atvērsies droša Paysera lapa — izvēlieties savu banku un apmaksājiet.',
			'Pēc pasūtījuma noformēšanas atvērsies drošā Paysera maksājumu lapa — tur izvēlieties savu banku.'
				=> 'Pēc noformēšanas atvērsies droša Paysera lapa — izvēlieties savu banku un apmaksājiet.',
			'Internetbanka — Swedbank, SEB, Citadele, Luminor, Revolut' => 'Internetbanka',
			'Pārskaitījums ar rēķinu' => 'Rēķins uz e-pastu',
			'Select Venipak pickup point:' => 'Izvēlieties Venipak pakomātu:',
			'Piegādes adrese' => 'Saņēmējs',
			'Select pickup point' => 'Izvēlieties pakomātu',
			'DPD Pickup points' => 'DPD pakomāts',
			'Pasūtījuma izpilde tiek uzsākta pēc maksājuma saņemšanas mūsu bankas kontā.' => 'Rēķinu nosūtīsim uz e-pastu. Izgatavošanu sākam pēc apmaksas saņemšanas.',
			'Apmaksa ar rēķinu' => 'Apmaksa internetbankā vai ar rēķinu',
		);
		?>
<script>
(function(){var M=<?php echo wp_json_encode( $map ); ?>;
function fix(root){var w=document.createTreeWalker(root,NodeFilter.SHOW_TEXT),n;while((n=w.nextNode())){var t=n.nodeValue.trim();if(M[t]){n.nodeValue=n.nodeValue.replace(t,M[t]);}}}
fix(document.body);new MutationObserver(function(m){m.forEach(function(r){r.addedNodes.forEach(function(x){if(x.nodeType===1)fix(x);else if(x.nodeType===3&&M[x.nodeValue.trim()])x.nodeValue=M[x.nodeValue.trim()];});});}).observe(document.body,{childList:true,subtree:true});})();
</script>
		<?php
	}

	/** Grozs, kase un konts ir noindex — sitemap tos nevajag. */
	public static function sitemap_entry( $url, $type, $object ) {
		if ( 'post' === $type && isset( $object->ID ) && function_exists( 'wc_get_page_id' ) ) {
			$skip = array( wc_get_page_id( 'cart' ), wc_get_page_id( 'checkout' ), wc_get_page_id( 'myaccount' ) );
			if ( in_array( (int) $object->ID, array_map( 'intval', $skip ), true ) ) {
				return false;
			}
		}
		if ( 'term' === $type && isset( $object->slug ) && 'uncategorized' === $object->slug ) {
			return false;
		}
		return $url;
	}
}

add_action( 'plugins_loaded', array( 'MarMar_QA_Fixes', 'init' ) );
