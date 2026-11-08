<?php
/**
 * Plugin Name:       MarMar QA labojumi
 * Description:       Testēšanā atrasto kļūdu labojumi: latviski teksti meklēšanā, 404 lapā un kases kļūdu paziņojumos; video galerijas faili tikai produktiem ar video; 301 no demo lapas «About Us»; grozs, kase un konts netiek iekļauti sitemap.
 * Version:           1.7.0
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_QA_Fixes {

	/** Vecās / demo adreses → jaunā vieta (301). Ceļš bez valodas prefiksa. */
	const REDIRECTS = array(
		'about-us' => '/lv/',
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
		add_filter( 'marmar_checkout_config', array( __CLASS__, 'min_order' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'single_option_js' ), 99 );
		add_filter( 'wp_nav_menu_items', array( __CLASS__, 'mobile_account_item' ), 20, 2 );
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
		return preg_replace(
			'~((?:Minimāl\w*\s+pasūtījum\w*(?:\s+summa)?|Min\.\s*pasūtījums)\s*(?:—|–|-|ir)?\s*(?:<b>|<strong>)?)5((?:\s|&nbsp;|\x{00A0}|\x{202F})*€)~u',
			'${1}' . self::MIN_ORDER . '$2',
			$html
		);
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
