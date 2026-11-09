<?php
/**
 * Plugin Name:       MarMar QA labojumi
 * Description:       Testēšanā atrasto kļūdu labojumi: latviski teksti meklēšanā, 404 lapā un kases kļūdu paziņojumos; video galerijas faili tikai produktiem ar video; 301 no demo lapas «About Us»; grozs, kase un konts netiek iekļauti sitemap.
 * Version:           1.9.4
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
	);

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'redirects' ), 1 );
		add_filter( 'gettext', array( __CLASS__, 'gettext' ), 20, 3 );
		add_filter( 'rank_math/frontend/title', array( __CLASS__, 'title_404' ), 20 );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'title_404' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'checkout_i18n' ), 100 );
		add_filter( 'rank_math/sitemap/entry', array( __CLASS__, 'sitemap_entry' ), 10, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'product_buffer' ), 2 );
		add_action( 'template_redirect', array( __CLASS__, 'page_buffer' ), 3 );
		add_action( 'wp_head', array( __CLASS__, 'css' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'verify_meta' ), 1 );
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
		if ( self::is_kc() ) {
			return $html;
		}
		$m    = self::MIN_ORDER;
		$html = preg_replace(
			'~Minimāl\w*\s+pasūtījum\w*(?:\s+summa)?\s*(?:—|–|-|ir)?\s*(<b>|<strong>)?(?:5|10)((?:\s|&nbsp;|\x{00A0}|\x{202F})*€)(</b>|</strong>)?~u',
			'Pasūtījumiem zem ${1}' . $m . '$2$3 — piemaksa līdz ' . $m . ' €',
			$html
		);
		return preg_replace( '~Min\.\s*pasūtījums\s*(?:5|10)((?:\s|&nbsp;|\x{00A0}|\x{202F})*€)~u', 'Zem ' . $m . '$1 — piemaksa', $html );
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
		$html = self::min_order_texts( $html );
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
		return $translation;
	}

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
