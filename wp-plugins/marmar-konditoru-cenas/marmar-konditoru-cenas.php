<?php
/**
 * Plugin Name:       MarMar Konditoru cenas
 * Description:       Īpašas cenas konditoriem: loma «Konditors», pieteikums ar apstiprināšanu ([marmar_konditoru_cenas]), konditora cenas precēm un variācijām, minimālais pasūtījums konditoriem, informācija uz preces un kategorijā.
 * Version:           1.3.4
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 * Text Domain:       marmar-konditoru-cenas
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Konditoru_Cenas {

	const VER       = '1.3.4';
	const ROLE      = 'konditors';
	const META      = '_mm_kc_price';   // konditora cena precei / variācijai
	const META_HAS  = '_mm_kc_has';     // vecākpreces karodziņš: vismaz vienai variācijai ir konditora cena
	const U_STATUS  = 'mm_kc_status';   // pending | approved | rejected
	const U_DATA    = 'mm_kc_data';
	const OPTION    = 'marmar_konditoru_cenas';

	public static function settings() {
		return wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			array(
				'min_order' => 30,
				'notify'    => get_option( 'admin_email' ),
				'page_url'  => home_url( '/lv/konditori/#mmkc-p-cenas' ),
			)
		);
	}

	public static function init() {
		// Cenas.
		add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_price' ), 99, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'filter_price' ), 99, 2 );
		add_filter( 'woocommerce_variation_prices_price', array( __CLASS__, 'filter_variation_prices' ), 99, 3 );
		add_filter( 'woocommerce_get_variation_prices_hash', array( __CLASS__, 'prices_hash' ), 99, 3 );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html' ), 99, 2 );

		// Minimālais pasūtījums konditoriem.
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_minimum' ) );
		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'check_minimum' ) );

		// Informācija veikalā.
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'product_teaser' ), 11 );
		add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'category_banner' ), 5 );
		add_action( 'wp_body_open', array( __CLASS__, 'konditor_bar' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );

		// Preces lapā nerādām bloku «Kategorija:» (Alīnas lūgums 07.11.2026).
		add_action( 'wp', array( __CLASS__, 'hide_product_meta' ) );

		// Pieteikums.
		add_shortcode( 'marmar_konditoru_cenas', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'marmar_konditori_pogas', array( __CLASS__, 'buttons' ) );
		add_shortcode( 'marmar_konditori_promo', array( __CLASS__, 'promo' ) );
		add_action( 'init', array( __CLASS__, 'handle_application' ), 20 );

		// Administrācija.
		add_action( 'woocommerce_product_options_pricing', array( __CLASS__, 'field_simple' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_simple' ) );
		add_action( 'woocommerce_variation_options_pricing', array( __CLASS__, 'field_variation' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_mm_kc_decide', array( __CLASS__, 'admin_decide' ) );
		add_action( 'admin_post_mm_kc_settings', array( __CLASS__, 'admin_save_settings' ) );
		add_filter( 'add_menu_classes', array( __CLASS__, 'menu_bubble' ) );
	}

	public static function activate() {
		add_role( self::ROLE, 'Konditors', array( 'read' => true ) );
	}

	/* ---------------- Kas ir konditors ---------------- */

	public static function is_konditor( $user_id = null ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		$u = get_userdata( $user_id );
		return $u && in_array( self::ROLE, (array) $u->roles, true );
	}

	public static function kc_price( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}
		$v = $product->get_meta( self::META, true );
		return ( '' !== $v && is_numeric( $v ) && (float) $v > 0 ) ? (float) $v : '';
	}

	public static function has_kc_prices( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}
		if ( $product->is_type( 'variable' ) ) {
			return 'yes' === $product->get_meta( self::META_HAS, true );
		}
		return '' !== self::kc_price( $product );
	}

	/* ---------------- Cenas ---------------- */

	public static function filter_price( $price, $product ) {
		if ( ! self::is_konditor() ) {
			return $price;
		}
		$kc = self::kc_price( $product );
		return '' === $kc ? $price : $kc;
	}

	public static function filter_variation_prices( $price, $variation, $product ) {
		if ( ! self::is_konditor() ) {
			return $price;
		}
		$kc = self::kc_price( $variation );
		return '' === $kc ? $price : $kc;
	}

	public static function prices_hash( $hash, $product, $for_display ) {
		$hash[] = self::is_konditor() ? 'mm_kc_1' : 'mm_kc_0';
		return $hash;
	}

	public static function price_html( $html, $product ) {
		if ( ! self::is_konditor() || ! self::has_kc_prices( $product ) || is_admin() ) {
			return $html;
		}
		if ( $product->is_type( 'variable' ) ) {
			$retail = $product->get_variation_regular_price( 'min', true );
			$retail_label = 'mazumtirdzniecībā no ';
		} else {
			$retail = wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) );
			$retail_label = 'mazumtirdzniecībā ';
		}
		$extra = '<span class="mmkc-label">Konditora cena</span>';
		if ( $retail ) {
			$extra .= '<small class="mmkc-retail">' . esc_html( $retail_label ) . wp_strip_all_tags( wc_price( $retail ) ) . '</small>';
		}
		return $html . $extra;
	}

	/* ---------------- Minimālais pasūtījums ---------------- */

	public static function check_minimum() {
		if ( ! self::is_konditor() || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		$min   = (float) self::settings()['min_order'];
		$total = (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_subtotal_tax();
		if ( $total < $min ) {
			wc_add_notice(
				sprintf(
					'Konditoru minimālais pasūtījums ir %1$s (bez piegādes). Pievieno vēl preces par %2$s 🎂',
					wp_strip_all_tags( wc_price( $min ) ),
					wp_strip_all_tags( wc_price( $min - $total ) )
				),
				'error'
			);
		}
	}

	/* ---------------- Informācija veikalā ---------------- */

	public static function product_teaser() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$cake = self::has_kc_prices( $product );
		if ( ! $cake ) {
			foreach ( wc_get_product_terms( $product->get_id(), 'product_cat' ) as $t ) {
				if ( preg_match( '/kūk|tort|dzimšan|cipar|svecīt|toper/iu', $t->name . ' ' . $t->slug ) ) {
					$cake = true;
					break;
				}
			}
		}
		if ( ! $cake ) {
			return;
		}
		$s    = self::settings();
		$page = home_url( '/lv/konditori/' );
		echo '<div class="mmkc-teaser">';
		echo '<p>🎂 <strong>Meklējat torti šīm svecēm?</strong> <a href="' . esc_url( $page ) . '">Konditori visā Latvijā&nbsp;→</a></p>';
		if ( ! self::is_konditor() && self::has_kc_prices( $product ) ) {
			printf(
				'<p class="mmkc-teaser-pro">Esat konditors? Jums ir īpašas cenas un pasūtījums no %1$s €. <a href="%2$s">Pieteikties&nbsp;→</a></p>',
				esc_html( (int) $s['min_order'] ),
				esc_url( $s['page_url'] )
			);
		}
		echo '</div>';
	}

	public static function is_konditor_category() {
		if ( ! is_product_category() ) {
			return false;
		}
		$t = get_queried_object();
		return $t && preg_match( '/kūk|tort|dzimšan|cipar|svecīt/iu', $t->name . ' ' . $t->slug );
	}

	public static function category_banner() {
		if ( self::is_konditor() || ! self::is_konditor_category() ) {
			return;
		}
		$s = self::settings();
		printf(
			'<div class="mmkc-banner"><div><strong>Konditoriem un konditorejām</strong> — īpašas cenas, rēķins automātiski, ērta atkārtota pasūtīšana.</div><a class="mmkc-btn" href="%s">Uzzināt vairāk →</a></div>',
			esc_url( $s['page_url'] )
		);
	}

	public static function konditor_bar() {
		if ( ! self::is_konditor() || is_admin() ) {
			return;
		}
		$u = wp_get_current_user();
		$s = self::settings();
		printf(
			'<div class="mmkc-bar">Sveiks, %1$s! Tu redzi konditoru cenas · Min. pasūtījums %2$s €</div>',
			esc_html( $u->first_name ? $u->first_name : $u->display_name ),
			esc_html( (int) $s['min_order'] )
		);
	}

	public static function hide_product_meta() {
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
	}

	public static function assets() {
		$css = '
.single-product .product_meta,.single-product .entry-summary .product_meta{display:none!important}
.single-product .entry-summary .ct-product-divider{display:none!important}
.single-product .entry-summary .ct-product-add-to-cart{margin-bottom:0!important}
@media(max-width:999px){.single-product .woocommerce-tabs{padding-top:24px!important}}
/* Vienkāršāka preces lapa (07.11.2026): poga «Pievienot grozam» augstāk, informācija zem tās. */
.single-product .entry-summary{display:flex;flex-direction:column}
.single-product .entry-summary>*{order:10}
.single-product .entry-summary>.product_title{order:1}
.single-product .entry-summary>.price{order:2;margin-bottom:12px!important}
.single-product .entry-summary>.mmrb{order:3;align-self:flex-start;margin-bottom:18px!important}
.single-product .entry-summary>.woocommerce-product-details__short-description{order:4}
.single-product .entry-summary>.ct-product-add-to-cart{order:5}
.single-product .entry-summary>.mmd-eta{order:6;margin-top:12px!important}
.single-product .entry-summary>.mmkc-teaser{order:7;margin-top:12px!important}
.single-product .mm-hint{display:none!important}
.single-product .mm-info>div:first-child{display:none!important}
.single-product .mm-info>div:nth-child(2){border-top:0!important}
.single-product table.variations{margin-bottom:12px!important}
.single-product table.variations tr{margin-bottom:10px!important}
.single-product .button-variable-items-wrapper{flex-wrap:wrap;gap:8px!important;justify-content:flex-start!important}
.single-product .button-variable-items-wrapper .variable-item{margin:0!important}
.single-product .image-variable-items-wrapper .variable-item{margin-bottom:24px!important}
.single-product .variations .variable-items-wrapper{padding-top:4px!important;padding-bottom:0!important;margin:0!important}
.single-product .variations td.value{padding-bottom:0!important}
.single-product .reset_variations{display:none!important}
.single-product table.variations td.value ul.variable-items-wrapper.button-variable-items-wrapper{display:flex!important;flex-wrap:wrap!important;width:100%!important;padding:4px 0 0!important;gap:8px!important}
.single-product table.variations td.value ul.button-variable-items-wrapper li.variable-item{margin:0!important}
@media(max-width:400px){.single-product .single_add_to_cart_button{letter-spacing:.06em!important;font-size:14px!important;white-space:nowrap}}
.single-product .single_variation .price{display:block;margin:2px 0 10px!important;font-size:1.15em}
.single-product .single_variation:empty,.single-product .woocommerce-variation-description:empty{display:none}
@media(max-width:640px){
.single-product .image-variable-items-wrapper{display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr));gap:4px!important;justify-items:center}
.single-product .image-variable-items-wrapper .variable-item{margin:0 0 30px!important}
.single-product .image-variable-items-wrapper .mm-lbl{font-size:10.5px!important;line-height:1.2!important;width:62px!important;max-width:19vw;white-space:normal!important;text-align:center}
.single-product .button-variable-items-wrapper .variable-item{height:auto!important;min-height:34px;padding:0!important}
.single-product .button-variable-items-wrapper .variable-item .variable-item-contents{font-size:12.5px;padding:6px 10px;white-space:nowrap}
.single-product table.variations th.label,.single-product table.variations th.label label{font-size:13px!important}
}
.mmkc-label{display:inline-block;margin-left:.5em;padding:.1em .55em;border-radius:999px;background:#F6E6E4;color:#8E3F3E;font-size:.72em;font-weight:600;vertical-align:middle;letter-spacing:.02em}
.mmkc-retail{display:block;margin-top:.2em;color:#8a8a8a;font-size:.78em;font-weight:400}
.mmkc-teaser{margin:.6em 0 1.1em;padding:.7em .9em;border-radius:12px;background:#FBF3F2;font-size:.95em;line-height:1.5}
.mmkc-teaser p{margin:0}
.mmkc-teaser .mmkc-teaser-pro{margin-top:.35em;font-size:.9em;color:#6b5a58}
.mmkc-teaser a{color:#B9605F;font-weight:600;text-decoration:none}
.mmkc-banner{display:flex;gap:1em;align-items:center;justify-content:space-between;flex-wrap:wrap;margin:0 0 1.5em;padding:1em 1.2em;border-radius:16px;background:#FBF3F2;border:1px solid #F0D9D6;line-height:1.5}
.mmkc-btn{display:inline-block;padding:.55em 1.1em;border-radius:999px;background:#B9605F;color:#fff!important;text-decoration:none;font-weight:600;white-space:nowrap}
.mmkc-bar{background:#B9605F;color:#fff;text-align:center;font-size:14px;padding:6px 16px;line-height:1.4}
.mmkc-box{max-width:760px;margin:0 auto 2.5em;padding:1.4em 1.4em 1.6em;border-radius:20px;background:#FBF3F2;border:1px solid #F0D9D6}
.mmkc-box h2{margin-top:0}
.mmkc-box ul{margin:.6em 0 1.2em 1.1em}
.mmkc-form{display:grid;grid-template-columns:1fr 1fr;gap:.8em}
.mmkc-form label{display:flex;flex-direction:column;gap:.3em;font-size:.95em}
.mmkc-form .mmkc-full{grid-column:1/-1}
.mmkc-form input,.mmkc-form textarea{width:100%;max-width:100%;box-sizing:border-box;padding:.65em .8em;border:1px solid #E3C9C5;border-radius:10px;background:#fff;font-size:16px}
.mmkc-form .mmkc-check{flex-direction:row;align-items:flex-start;gap:.5em}
.mmkc-form .mmkc-check input{width:auto;margin-top:.3em}
.mmkc-form button{grid-column:1/-1;justify-self:start;padding:.75em 1.6em;border:0;border-radius:999px;background:#B9605F;color:#fff;font-weight:600;font-size:16px;cursor:pointer}
.mmkc-hp{position:absolute!important;left:-9999px!important}
.mmkc-tabs{max-width:760px;margin:0 auto 2em}
.mmkp{display:grid;grid-template-columns:1fr 1.15fr;gap:2em;align-items:center;max-width:1200px;margin:3em auto;padding:2.2em;border-radius:24px;background:#FBF3F2;border:1px solid #F0D9D6}
.mmkp-text h2{margin:.3em 0 .5em}
.mmkp-text p{margin:0 0 1.2em;line-height:1.6}
.mmkp-btn{display:inline-block;margin-bottom:1.2em}
.mmkp .mmkp-pro{margin:0;font-size:.95em;color:#5a4a48}
.mmkp-pro a{color:#B9605F;font-weight:600}
.mmkp-photos{display:grid;grid-template-columns:repeat(3,1fr);gap:.8em}
.mmkp-photo{position:relative;display:block;border-radius:16px;overflow:hidden;aspect-ratio:3/4;background:#f3e3e1}
.mmkp-photo img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s}
.mmkp-photo:hover img{transform:scale(1.04)}
.mmkp-photo span{position:absolute;left:0;right:0;bottom:0;padding:1.6em .7em .6em;background:linear-gradient(transparent,rgba(0,0,0,.55));color:#fff;font-size:.85em;font-weight:600;line-height:1.3}
@media(max-width:820px){.mmkp{grid-template-columns:1fr;padding:1.4em;margin:2em 16px;gap:1.2em}.mmkp-photos{order:-1;gap:.5em}.mmkp-photo{border-radius:12px}.mmkp-photo span{font-size:.75em;padding:1.2em .5em .45em}}
.mmkc-tabbtns{display:flex;gap:.6em;justify-content:center;flex-wrap:wrap;margin-bottom:1.2em}
.mmkc-tabbtn{padding:.55em 1.15em;border-radius:999px;border:1px solid #E3C9C5;background:#fff;color:#8E3F3E;font-weight:600;font-size:15px;line-height:1.3;cursor:pointer;transition:background .2s,color .2s,border-color .2s}
.mmkc-tabbtn:hover{border-color:#B9605F}
.mmkc-tabbtn[aria-expanded=true]{background:#B9605F;border-color:#B9605F;color:#fff}
.mmkc-tabbtn:after{content:"⌄";display:inline-block;margin-left:.4em;font-size:.9em;transform:translateY(-.15em)}
.mmkc-tabbtn[aria-expanded=true]:after{transform:rotate(180deg) translateY(-.1em)}
.mmkc-panel .mmkc-box{margin-bottom:0}
@media(max-width:640px){.mmkc-tabbtns{gap:.5em}.mmkc-tabbtn{flex:1 1 0;min-width:0;font-size:13.5px;padding:.55em .7em;white-space:nowrap}}
.mm-toggle{margin:1em 0}
.mm-toggle>summary{display:inline-block;list-style:none;cursor:pointer;padding:.75em 1.6em;border-radius:999px;background:#B9605F;color:#fff;font-weight:600;font-size:16px;line-height:1.3}
.mm-toggle>summary::-webkit-details-marker{display:none}
.mm-toggle>summary::after{content:" ↓"}
.mm-toggle[open]>summary{margin-bottom:1.2em;background:#fff;color:#B9605F;border:1px solid #B9605F}
.mm-toggle[open]>summary::after{content:" ↑"}
.mmkc-msg{padding:.8em 1em;border-radius:12px;background:#fff;border-left:4px solid #B9605F;margin-bottom:1em}
@media(max-width:640px){.mmkc-form{grid-template-columns:1fr}.mmkc-banner{flex-direction:column;align-items:flex-start}}
';
		wp_register_style( 'marmar-konditoru-cenas', false, array(), self::VER );
		wp_enqueue_style( 'marmar-konditoru-cenas' );
		wp_add_inline_style( 'marmar-konditoru-cenas', $css );
		// Atver formu, ja lapa atvērta pēc nosūtīšanas (paziņojums / kļūda) vai ar saiti uz formu.
		$js = "(function(){function tab(name,scroll){var w=document.querySelector('[data-mmkc-tabs]');if(!w)return false;var hit=false;w.querySelectorAll('[data-mmkc-tab]').forEach(function(b){var on=b.getAttribute('data-mmkc-tab')===name&&b.getAttribute('aria-expanded')!=='true';if(b.getAttribute('data-mmkc-tab')===name)hit=true;b.setAttribute('aria-expanded',on?'true':'false');});w.querySelectorAll('[data-mmkc-panel]').forEach(function(p){p.hidden=!(p.getAttribute('data-mmkc-panel')===name&&w.querySelector('[data-mmkc-tab=\\''+name+'\\']').getAttribute('aria-expanded')==='true');});if(scroll)w.scrollIntoView({behavior:'smooth',block:'start'});return hit;}function openFor(h){if(!h||h.length<2)return;var el=document.getElementById(decodeURIComponent(h.slice(1)));if(!el)return;var p=el.closest('[data-mmkc-panel]');if(p){var b=document.querySelector('[data-mmkc-tab=\\''+p.getAttribute('data-mmkc-panel')+'\\']');if(b&&b.getAttribute('aria-expanded')!=='true')tab(p.getAttribute('data-mmkc-panel'),false);}var d=el.tagName==='DETAILS'?el:el.closest('details');if(d)d.open=true;setTimeout(function(){el.scrollIntoView({behavior:'smooth',block:'start'});},50);}document.addEventListener('click',function(e){var t=e.target.closest&&e.target.closest('[data-mmkc-tab]');if(t){tab(t.getAttribute('data-mmkc-tab'),false);return;}var a=e.target.closest&&e.target.closest('a[href*=\\'#\\']');if(a&&a.hash&&a.pathname===location.pathname){if(a.hash==='#konditoru-cenas'&&tab('cenas',true)){e.preventDefault();return;}openFor(a.hash);}});window.addEventListener('hashchange',function(){openFor(location.hash);});document.addEventListener('DOMContentLoaded',function(){if(location.hash==='#konditoru-cenas'&&document.querySelector('[data-mmkc-tabs]')&&!document.querySelector('[data-mmkc-tab=cenas][aria-expanded=true]')){tab('cenas',true);}else{openFor(location.hash);}var q=location.search;if(!/mmk/i.test(q))return;document.querySelectorAll('details.mm-toggle').forEach(function(d){if(/mmkc/i.test(q)?d.querySelector('.mmkc-form'):!d.querySelector('.mmkc-form'))d.open=true;});});})();";
		wp_register_script( 'marmar-konditoru-cenas', false, array(), self::VER, true );
		wp_enqueue_script( 'marmar-konditoru-cenas' );
		wp_add_inline_script( 'marmar-konditoru-cenas', $js );
	}

	/* ---------------- Pieteikums ---------------- */

	public static function shortcode( $atts = array() ) {
		$atts   = shortcode_atts( array( 'inline' => '0' ), (array) $atts );
		$inline = '1' === $atts['inline'];
		$s   = self::settings();
		$out = '<div id="konditoru-cenas" class="mmkc-box">';
		$uid = get_current_user_id();

		if ( isset( $_GET['mmkc'] ) && 'ok' === $_GET['mmkc'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			$out .= '<div class="mmkc-msg"><strong>Paldies! Pieteikums saņemts.</strong> Es to izskatīšu tuvākajā laikā, un tu saņemsi e-pastu, kad konts būs apstiprināts.</div>';
		} elseif ( isset( $_GET['mmkc_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$out .= '<div class="mmkc-msg">Lūdzu, aizpildi vārdu, e-pastu un Instagram vai mājaslapu, un apstiprini piekrišanu.</div>';
		}

		if ( $uid && self::is_konditor( $uid ) ) {
			$out .= '<h2>Tavs konditora konts ir aktīvs 🎂</h2><p>Veikalā tu redzi konditoru cenas. Minimālais pasūtījums — ' . esc_html( (int) $s['min_order'] ) . ' €. Rēķins pienāk automātiski uz e-pastu, un iepriekšējos pasūtījumus vari atkārtot sadaļā «Mans konts».</p>';
			$out .= '<p><a class="mmkc-btn" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Uz veikalu →</a></p></div>';
			return $out;
		}
		if ( $uid && 'pending' === get_user_meta( $uid, self::U_STATUS, true ) ) {
			$out .= '<h2>Pieteikums saņemts</h2><p>Tavs pieteikums gaida apstiprinājumu. Kad tas būs apstiprināts, saņemsi e-pastu.</p></div>';
			return $out;
		}

		$out .= '<h2>Konditoru cenas</h2>';
		$out .= '<p>Konditoriem un konditorejām man ir īpašas cenas sveču komplektiem, cipariem un figūrsvecēm.</p><ul>';
		$out .= '<li>īpašas cenas, kas redzamas pēc pieslēgšanās;</li>';
		$out .= '<li>minimālais pasūtījums — ' . esc_html( (int) $s['min_order'] ) . ' € (bez piegādes);</li>';
		$out .= '<li>rēķins pienāk automātiski uz e-pastu;</li>';
		$out .= '<li>iepriekšējo pasūtījumu var atkārtot ar vienu klikšķi sadaļā «Mans konts»;</li>';
		$out .= '<li>piegāde uz pakomātu 2,50 €, no 50 € — bez maksas.</li></ul>';
		$out .= '<p>Piesakies — es apstiprināšu pieteikumu, un tu saņemsi e-pastu ar pieeju.</p>';

		$u    = $uid ? wp_get_current_user() : null;
		$open = isset( $_GET['mmkc_err'] ) ? ' open' : ''; // phpcs:ignore WordPress.Security.NonceVerification
		ob_start();
		?>
		<?php if ( ! $inline ) : ?>
		<details class="mm-toggle"<?php echo $open; // phpcs:ignore ?>>
		<summary>Pieteikties konditora kontam</summary>
		<?php endif; ?>
		<form class="mmkc-form" method="post" action="">
			<?php wp_nonce_field( 'mm_kc_apply', 'mm_kc_nonce' ); ?>
			<input type="hidden" name="mm_kc_apply" value="1">
			<label class="mmkc-hp" aria-hidden="true">Mājaslapa2<input type="text" name="mm_kc_url2" tabindex="-1" autocomplete="off"></label>
			<label>Vārds, uzvārds *<input type="text" name="mm_kc_name" required value="<?php echo esc_attr( $u ? trim( $u->first_name . ' ' . $u->last_name ) : '' ); ?>"></label>
			<label>E-pasts *<input type="email" name="mm_kc_email" required value="<?php echo esc_attr( $u ? $u->user_email : '' ); ?>"></label>
			<label>Tālrunis<input type="tel" name="mm_kc_phone"></label>
			<label>Instagram vai mājaslapa *<input type="text" name="mm_kc_insta" required placeholder="@mana.konditoreja"></label>
			<label>Uzņēmuma nosaukums<input type="text" name="mm_kc_company"></label>
			<label>Reģ. nr.<input type="text" name="mm_kc_regnr"></label>
			<label class="mmkc-full">Komentārs<textarea name="mm_kc_msg" rows="3" placeholder="Piemēram, cik sveču parasti vajag mēnesī"></textarea></label>
			<label class="mmkc-full mmkc-check"><input type="checkbox" name="mm_kc_consent" value="1" required> <span>Piekrītu, ka MarMar Candles apstrādā šos datus, lai izskatītu pieteikumu un izveidotu konditora kontu.</span></label>
			<button type="submit">Nosūtīt pieteikumu</button>
		</form>
		<?php if ( ! $inline ) : ?>
		</details>
		<?php endif; ?>
		<?php
		$out .= ob_get_clean() . '</div>';
		return $out;
	}

	/**
	 * Divas pogas lapas augšā: «Reģistrēties katalogā» un «Saņemt īpašas cenas».
	 * Katra atver savu paneli (vienlaikus atvērts tikai viens).
	 */
	public static function buttons() {
		$cenas   = self::shortcode( array( 'inline' => '1' ) );
		$katalogs = shortcode_exists( 'marmar_konditori_forma' ) ? do_shortcode( '[marmar_konditori_forma]' ) : '';
		$open    = '';
		if ( isset( $_GET['mmkc'] ) || isset( $_GET['mmkc_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$open = 'cenas';
		} elseif ( isset( $_GET['mmk'] ) || isset( $_GET['mmk_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$open = 'katalogs';
		}
		$out  = '<div class="mmkc-tabs" data-mmkc-tabs>';
		$out .= '<div class="mmkc-tabbtns">';
		$out .= '<button type="button" class="mmkc-tabbtn" data-mmkc-tab="katalogs" aria-expanded="' . ( 'katalogs' === $open ? 'true' : 'false' ) . '" aria-controls="mmkc-p-katalogs">Reģistrēties katalogā</button>';
		$out .= '<button type="button" class="mmkc-tabbtn" data-mmkc-tab="cenas" aria-expanded="' . ( 'cenas' === $open ? 'true' : 'false' ) . '" aria-controls="mmkc-p-cenas">Saņemt īpašas cenas</button>';
		$out .= '</div>';
		$out .= '<div class="mmkc-panel" id="mmkc-p-katalogs" data-mmkc-panel="katalogs"' . ( 'katalogs' === $open ? '' : ' hidden' ) . '><div class="mmkc-box">' . $katalogs . '</div></div>';
		$out .= '<div class="mmkc-panel" id="mmkc-p-cenas" data-mmkc-panel="cenas"' . ( 'cenas' === $open ? '' : ' hidden' ) . '>' . $cenas . '</div>';
		$out .= '</div>';
		return $out;
	}

	/**
	 * Bloks sākumlapai: klientiem — atrast torti, konditoriem — katalogs un īpašas cenas.
	 */
	public static function promo() {
		$page = home_url( '/lv/konditori/' );
		$imgs = '';
		if ( post_type_exists( 'mm_konditors' ) ) {
			$q = get_posts(
				array(
					'post_type'      => 'mm_konditors',
					'post_status'    => 'publish',
					'posts_per_page' => 3,
					'meta_key'       => '_thumbnail_id', // phpcs:ignore
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
			foreach ( $q as $k ) {
				$imgs .= '<a class="mmkp-photo" href="' . esc_url( $page ) . '">' . get_the_post_thumbnail( $k->ID, 'medium_large', array( 'loading' => 'lazy', 'alt' => esc_attr( $k->post_title . ' — torte' ) ) ) . '<span>' . esc_html( $k->post_title ) . '</span></a>';
			}
		}
		$out  = '<section class="mmkp" aria-label="Konditori un konditorejas">';
		$out .= '<div class="mmkp-text"><span class="mmh-eyebrow">Konditori</span>';
		$out .= '<h2>Torte un sveces — vienuviet</h2>';
		$out .= '<p>Mūsu katalogā — konditori un konditorejas visā Latvijā. Atrodiet torti savā pilsētā, un sveces tai pasūtiet šeit.</p>';
		$out .= '<a class="mmh-btn mmkp-btn" href="' . esc_url( $page ) . '">Atrast konditoru →</a>';
		$out .= '<p class="mmkp-pro">🎂 <strong>Esat konditors?</strong> <a href="' . esc_url( $page . '#mmkc-p-katalogs' ) . '">Reģistrējieties katalogā bez maksas</a> vai <a href="' . esc_url( $page . '#mmkc-p-cenas' ) . '">saņemiet īpašas cenas</a>.</p>';
		$out .= '</div>';
		if ( $imgs ) {
			$out .= '<div class="mmkp-photos">' . $imgs . '</div>';
		}
		$out .= '</section>';
		return $out;
	}

	public static function handle_application() {
		if ( empty( $_POST['mm_kc_apply'] ) ) {
			return;
		}
		if ( ! isset( $_POST['mm_kc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mm_kc_nonce'] ) ), 'mm_kc_apply' ) ) {
			return;
		}
		$back = remove_query_arg( array( 'mmkc', 'mmkc_err' ), wp_get_referer() ? wp_get_referer() : home_url( '/konditori/' ) );
		$back = strtok( $back, '#' );
		if ( ! empty( $_POST['mm_kc_url2'] ) ) { // honeypot
			wp_safe_redirect( add_query_arg( 'mmkc', 'ok', $back ) . '#konditoru-cenas' );
			exit;
		}
		$name  = sanitize_text_field( wp_unslash( $_POST['mm_kc_name'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $_POST['mm_kc_email'] ?? '' ) );
		$insta = sanitize_text_field( wp_unslash( $_POST['mm_kc_insta'] ?? '' ) );
		if ( ! $name || ! is_email( $email ) || ! $insta || empty( $_POST['mm_kc_consent'] ) ) {
			wp_safe_redirect( add_query_arg( 'mmkc_err', '1', $back ) . '#konditoru-cenas' );
			exit;
		}
		$data = array(
			'name'    => $name,
			'email'   => $email,
			'phone'   => sanitize_text_field( wp_unslash( $_POST['mm_kc_phone'] ?? '' ) ),
			'insta'   => $insta,
			'company' => sanitize_text_field( wp_unslash( $_POST['mm_kc_company'] ?? '' ) ),
			'regnr'   => sanitize_text_field( wp_unslash( $_POST['mm_kc_regnr'] ?? '' ) ),
			'msg'     => sanitize_textarea_field( wp_unslash( $_POST['mm_kc_msg'] ?? '' ) ),
			'date'    => current_time( 'mysql' ),
		);

		$uid = get_current_user_id();
		if ( ! $uid ) {
			$existing = get_user_by( 'email', $email );
			if ( $existing ) {
				$uid = $existing->ID;
			} else {
				$parts = explode( ' ', $name, 2 );
				$uid   = wp_insert_user(
					array(
						'user_login' => self::unique_login( $email ),
						'user_email' => $email,
						'user_pass'  => wp_generate_password( 20 ),
						'first_name' => $parts[0],
						'last_name'  => $parts[1] ?? '',
						'role'       => 'customer',
					)
				);
				if ( is_wp_error( $uid ) ) {
					wp_safe_redirect( add_query_arg( 'mmkc_err', '1', $back ) . '#konditoru-cenas' );
					exit;
				}
			}
		}
		if ( self::is_konditor( $uid ) ) {
			wp_safe_redirect( add_query_arg( 'mmkc', 'ok', $back ) . '#konditoru-cenas' );
			exit;
		}
		update_user_meta( $uid, self::U_DATA, $data );
		update_user_meta( $uid, self::U_STATUS, 'pending' );
		if ( $data['phone'] ) {
			update_user_meta( $uid, 'billing_phone', $data['phone'] );
		}
		if ( $data['company'] ) {
			update_user_meta( $uid, 'billing_company', $data['company'] );
		}

		$s = self::settings();
		$admin_url = admin_url( 'admin.php?page=mm-konditoru-cenas' );
		$body  = "Jauns konditora pieteikums:\n\n";
		$body .= "Vārds: {$data['name']}\nE-pasts: {$data['email']}\nTālrunis: {$data['phone']}\nInstagram / mājaslapa: {$data['insta']}\nUzņēmums: {$data['company']} {$data['regnr']}\nKomentārs: {$data['msg']}\n\n";
		$body .= "Apstiprināt vai noraidīt: {$admin_url}\n";
		wp_mail( $s['notify'], 'Jauns konditora pieteikums — ' . $data['name'], $body );
		wp_mail(
			$email,
			'MarMar Candles: konditora pieteikums saņemts',
			"Sveiki, " . current( explode( ' ', $name ) ) . "!\n\nPaldies par pieteikumu. Es to izskatīšu tuvākajā laikā, un tu saņemsi e-pastu, kad konts būs apstiprināts.\n\nAr cieņu,\nAlīna, MarMar Candles\nhttps://marmarcandles.com"
		);

		wp_safe_redirect( add_query_arg( 'mmkc', 'ok', $back ) . '#konditoru-cenas' );
		exit;
	}

	private static function unique_login( $email ) {
		$base  = sanitize_user( current( explode( '@', $email ) ), true );
		$base  = $base ? $base : 'konditors';
		$login = $base;
		$i     = 1;
		while ( username_exists( $login ) ) {
			$login = $base . $i++;
		}
		return $login;
	}

	/* ---------------- Administrācija: cenas ---------------- */

	public static function field_simple() {
		woocommerce_wp_text_input(
			array(
				'id'          => self::META,
				'label'       => 'Konditora cena (€)',
				'data_type'   => 'price',
				'desc_tip'    => true,
				'description' => 'Redz tikai apstiprināti konditori. Tukšs — konditors maksā parasto cenu.',
			)
		);
	}

	public static function save_simple( $product ) {
		if ( isset( $_POST[ self::META ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$v = wc_format_decimal( wp_unslash( $_POST[ self::META ] ) ); // phpcs:ignore
			if ( '' === $v ) {
				$product->delete_meta_data( self::META );
			} else {
				$product->update_meta_data( self::META, $v );
			}
		}
	}

	public static function field_variation( $loop, $variation_data, $variation ) {
		woocommerce_wp_text_input(
			array(
				'id'            => self::META . '_' . $loop,
				'name'          => self::META . '[' . $loop . ']',
				'value'         => get_post_meta( $variation->ID, self::META, true ),
				'label'         => 'Konditora cena (€)',
				'data_type'     => 'price',
				'wrapper_class' => 'form-row form-row-full',
			)
		);
	}

	public static function save_variation( $variation_id, $loop ) {
		if ( ! isset( $_POST[ self::META ][ $loop ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$v = wc_format_decimal( wp_unslash( $_POST[ self::META ][ $loop ] ) ); // phpcs:ignore
		if ( '' === $v ) {
			delete_post_meta( $variation_id, self::META );
		} else {
			update_post_meta( $variation_id, self::META, $v );
		}
		$parent = wp_get_post_parent_id( $variation_id );
		if ( $parent ) {
			self::refresh_parent_flag( $parent );
		}
	}

	public static function refresh_parent_flag( $parent_id ) {
		global $wpdb;
		$has = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_parent = %d AND p.post_type = 'product_variation' AND pm.meta_key = %s AND pm.meta_value <> ''",
				$parent_id,
				self::META
			)
		);
		if ( $has ) {
			update_post_meta( $parent_id, self::META_HAS, 'yes' );
		} else {
			delete_post_meta( $parent_id, self::META_HAS );
		}
		wc_delete_product_transients( $parent_id );
	}

	/* ---------------- Administrācija: pieteikumi ---------------- */

	public static function pending_count() {
		$q = new WP_User_Query(
			array(
				'meta_key'    => self::U_STATUS, // phpcs:ignore
				'meta_value'  => 'pending', // phpcs:ignore
				'count_total' => true,
				'number'      => 1,
				'fields'      => 'ID',
			)
		);
		return (int) $q->get_total();
	}

	public static function admin_menu() {
		$n     = self::pending_count();
		$label = 'Konditoru cenas' . ( $n ? ' <span class="awaiting-mod">' . $n . '</span>' : '' );
		add_submenu_page( 'woocommerce', 'Konditoru cenas', $label, 'manage_woocommerce', 'mm-konditoru-cenas', array( __CLASS__, 'admin_page' ) );
	}

	public static function menu_bubble( $menu ) {
		return $menu;
	}

	public static function admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s = self::settings();
		echo '<div class="wrap"><h1>Konditoru cenas</h1>';
		if ( isset( $_GET['done'] ) ) { // phpcs:ignore
			echo '<div class="notice notice-success"><p>Saglabāts.</p></div>';
		}
		foreach ( array( 'pending' => 'Gaida apstiprinājumu', 'approved' => 'Apstiprināti konditori', 'rejected' => 'Noraidīti' ) as $status => $title ) {
			$users = get_users( array( 'meta_key' => self::U_STATUS, 'meta_value' => $status, 'number' => 200 ) ); // phpcs:ignore
			if ( 'approved' === $status ) {
				$by_role = get_users( array( 'role' => self::ROLE, 'number' => 200 ) );
				$ids     = wp_list_pluck( $users, 'ID' );
				foreach ( $by_role as $u ) {
					if ( ! in_array( $u->ID, $ids, true ) ) {
						$users[] = $u;
					}
				}
			}
			echo '<h2>' . esc_html( $title ) . ' (' . count( $users ) . ')</h2>';
			if ( ! $users ) {
				echo '<p>—</p>';
				continue;
			}
			echo '<table class="widefat striped"><thead><tr><th>Vārds</th><th>E-pasts</th><th>Instagram / mājaslapa</th><th>Uzņēmums</th><th>Komentārs</th><th>Datums</th><th></th></tr></thead><tbody>';
			foreach ( $users as $u ) {
				$d = (array) get_user_meta( $u->ID, self::U_DATA, true );
				echo '<tr><td>' . esc_html( $d['name'] ?? $u->display_name ) . '</td><td>' . esc_html( $u->user_email ) . '</td><td>' . esc_html( $d['insta'] ?? '' ) . '</td><td>' . esc_html( trim( ( $d['company'] ?? '' ) . ' ' . ( $d['regnr'] ?? '' ) ) ) . '</td><td>' . esc_html( $d['msg'] ?? '' ) . '</td><td>' . esc_html( $d['date'] ?? '' ) . '</td><td>';
				foreach ( array( 'approve' => 'Apstiprināt', 'reject' => 'Noraidīt' ) as $act => $lbl ) {
					if ( ( 'approve' === $act && 'approved' === $status ) || ( 'reject' === $act && 'rejected' === $status ) ) {
						continue;
					}
					$url = wp_nonce_url( admin_url( 'admin-post.php?action=mm_kc_decide&do=' . $act . '&user=' . $u->ID ), 'mm_kc_decide_' . $u->ID );
					echo '<a class="button' . ( 'approve' === $act ? ' button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $lbl ) . '</a> ';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<h2>Iestatījumi</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'mm_kc_settings' );
		echo '<input type="hidden" name="action" value="mm_kc_settings"><table class="form-table">';
		echo '<tr><th>Minimālais pasūtījums konditoriem (€)</th><td><input type="number" step="1" min="0" name="min_order" value="' . esc_attr( $s['min_order'] ) . '"></td></tr>';
		echo '<tr><th>E-pasts paziņojumiem</th><td><input type="email" class="regular-text" name="notify" value="' . esc_attr( $s['notify'] ) . '"></td></tr>';
		echo '<tr><th>Saite uz pieteikumu</th><td><input type="url" class="regular-text" name="page_url" value="' . esc_attr( $s['page_url'] ) . '"></td></tr>';
		echo '</table>';
		submit_button( 'Saglabāt' );
		echo '</form></div>';
	}

	public static function admin_save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'mm_kc_settings' ) ) {
			wp_die( 'Nav atļauts.' );
		}
		update_option(
			self::OPTION,
			array(
				'min_order' => max( 0, (int) ( $_POST['min_order'] ?? 30 ) ),
				'notify'    => sanitize_email( wp_unslash( $_POST['notify'] ?? '' ) ),
				'page_url'  => esc_url_raw( wp_unslash( $_POST['page_url'] ?? '' ) ),
			)
		);
		wp_safe_redirect( admin_url( 'admin.php?page=mm-konditoru-cenas&done=1' ) );
		exit;
	}

	public static function admin_decide() {
		$uid = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
		$do  = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : '';
		if ( ! $uid || ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'mm_kc_decide_' . $uid ) ) {
			wp_die( 'Nav atļauts.' );
		}
		$u = get_userdata( $uid );
		if ( ! $u ) {
			wp_die( 'Lietotājs nav atrasts.' );
		}
		if ( 'approve' === $do ) {
			$u->add_role( self::ROLE );
			update_user_meta( $uid, self::U_STATUS, 'approved' );
			$key  = get_password_reset_key( $u );
			$link = is_wp_error( $key ) ? wc_get_page_permalink( 'myaccount' ) : add_query_arg( array( 'key' => $key, 'id' => $uid ), wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ) );
			$name = $u->first_name ? $u->first_name : $u->display_name;
			wp_mail(
				$u->user_email,
				'MarMar Candles: tavs konditora konts ir apstiprināts 🎂',
				"Sveiki, {$name}!\n\nTavs konditora konts ir apstiprināts. Pēc pieslēgšanās veikalā redzēsi konditoru cenas.\n\nPieslēgties: " . wc_get_page_permalink( 'myaccount' ) . "\nJa vēl neesi iestatījis paroli, iestati to šeit: {$link}\n\nMinimālais pasūtījums — " . (int) self::settings()['min_order'] . " € (bez piegādes). Rēķins pienāks automātiski uz e-pastu.\n\nAr cieņu,\nAlīna, MarMar Candles"
			);
		} elseif ( 'reject' === $do ) {
			$u->remove_role( self::ROLE );
			update_user_meta( $uid, self::U_STATUS, 'rejected' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=mm-konditoru-cenas&done=1' ) );
		exit;
	}
}

register_activation_hook( __FILE__, array( 'MarMar_Konditoru_Cenas', 'activate' ) );
add_action( 'plugins_loaded', array( 'MarMar_Konditoru_Cenas', 'init' ) );
