<?php
/**
 * Plugin Name:       MarMar Konditoru cenas
 * Description:       Īpašas cenas konditoriem (tikai kūku sveces un ciparu sveces): loma «Konditors», pieteikums ar apstiprināšanu ([marmar_konditoru_cenas]), konditora cenas precēm un variācijām, minimālais pasūtījums konditoriem, informācija uz preces un kategorijā, «Mana mēneša kaste» (ikmēneša pasūtījums ar atgādinājumu).
 * Version:           1.6.4
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 * Text Domain:       marmar-konditoru-cenas
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Konditoru_Cenas {

	const VER       = '1.6.4';
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
		add_action( 'admin_post_mm_kc_link_catalog', array( __CLASS__, 'admin_link_catalog' ) );
		add_action( 'admin_post_mm_kc_mail', array( __CLASS__, 'admin_mail' ) );
		add_action( 'admin_post_mm_kc_test_mail', array( __CLASS__, 'admin_test_mail' ) );
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
		$tier = $product instanceof WC_Product ? $product->get_meta( '_mm_tier_price', true ) : '';
		if ( '' !== $tier && is_numeric( $tier ) ) {
			return (float) $tier; // 200+ sveces grozā (MarMar_KC_Qty)
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
				'<p class="mmkc-teaser-pro">Esat konditors? Jums ir īpašas cenas kūku un ciparu svecēm, pasūtījums no %1$s €. <a href="%2$s">Pieteikties&nbsp;→</a></p>',
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
		return $t && preg_match( '/kūk|tort|dzimšan|cipar|svecīt/iu', $t->name . ' ' . $t->slug ) && ! preg_match( '/toper|figūr/iu', $t->name . ' ' . $t->slug );
	}

	public static function category_banner() {
		if ( self::is_konditor() || ! self::is_konditor_category() ) {
			return;
		}
		$s = self::settings();
		printf(
			'<div class="mmkc-banner"><div><strong>Konditoriem un konditorejām</strong> — īpašas cenas kūku un ciparu svecēm, rēķins automātiski, ērta atkārtota pasūtīšana.</div><a class="mmkc-btn" href="%s">Uzzināt vairāk →</a></div>',
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
.single-product .image-variable-items-wrapper{display:flex!important;flex-wrap:wrap!important;justify-content:flex-start!important;gap:2px!important;column-gap:2px!important;row-gap:2px!important}
.single-product .image-variable-items-wrapper .variable-item{margin:0 calc((min(62px,17vw) - 30px)/2) 30px!important}
body.single-product .woo-variation-items-wrapper ul.image-variable-items-wrapper{gap:2px!important;padding-left:0!important}
.single-product .image-variable-items-wrapper .mm-lbl{font-size:10.5px!important;line-height:1.2!important;width:min(62px,17vw)!important;max-width:none!important;white-space:normal!important;text-align:center}
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
.mmkc-uni{max-width:760px;margin:0 auto 2.5em;scroll-margin-top:90px}
.mmkc-uni>span[id]{display:block;position:relative;top:-90px}
.mmkc-hero{text-align:center;margin:0 0 1.2em}
.mmkc-hero h2{font-size:clamp(22px,3vw,30px);line-height:1.25;margin:0 0 .8em}
.mmkc-steps{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(3,1fr);gap:.7em}
.mmkc-steps li{display:flex;flex-direction:column;align-items:center;gap:.45em;padding:1em .7em;border-radius:16px;background:#fff;border:1px solid #F0D9D6;font-size:.95em;line-height:1.35}
.mmkc-steps b{display:flex;align-items:center;justify-content:center;width:2em;height:2em;border-radius:50%;background:#B9605F;color:#fff;font-size:1em}
.mmkc-terms{margin:.2em 0 1.2em 1.1em}
.mmkc-form select{width:100%;padding:.65em .8em;border:1px solid #E3C9C5;border-radius:10px;background:#fff;font-size:16px}
.mmkc-form .mmkc-cat{padding:.8em 1em;border-radius:12px;background:#fff;border:1px solid #E3C9C5}
.mmkc-catfields{display:flex;flex-direction:column;gap:.8em}
.mmkc-catfields[hidden]{display:none}
.mmkc-catfields fieldset{border:0;margin:0;padding:0}
.mmkc-catfields legend{font-size:.95em;margin-bottom:.4em}
.mmkc-types{display:flex;flex-wrap:wrap;gap:.35em 1.1em}
.mmkc-types .mmkc-check{font-size:.92em}
.mmkc-msg{margin:0 0 1em;padding:.8em 1em;border-radius:12px;background:#fff;border:1px solid #D68684}
@media(max-width:689px){.mmkc-form{grid-template-columns:1fr}.mmkc-steps{grid-template-columns:1fr}.mmkc-steps li{flex-direction:row;justify-content:flex-start;text-align:left;padding:.7em .9em}.mmkc-form button{justify-self:stretch}}
@media(max-width:640px){.mmkc-tabbtns{gap:.5em}.mmkc-tabbtn{flex:1 1 0;min-width:0;font-size:13.5px;padding:.55em .7em;white-space:nowrap}}
.mm-toggle{margin:1em 0}
.mm-toggle>summary{display:inline-block;list-style:none;cursor:pointer;padding:.75em 1.6em;border-radius:999px;background:#B9605F;color:#fff;font-weight:600;font-size:16px;line-height:1.3}
.mm-toggle>summary::-webkit-details-marker{display:none}
.mm-toggle>summary::after{content:" ↓"}
.mm-toggle[open]>summary{margin-bottom:1.2em;background:#fff;color:#B9605F;border:1px solid #B9605F}
.mm-toggle[open]>summary::after{content:" ↑"}
.mmkb-cartbox{margin:1.5em 0;padding:1em 1.2em;border-radius:14px;background:#FBF3F2;border:1px solid #F0D9D6}
.mmkb-cartbox p{margin:0 0 .7em}
.mmkb-head{display:flex;align-items:center;gap:.5em;flex-wrap:wrap;position:relative;margin:0 0 .7em}
.mmkb-help{display:inline-block;position:relative}
.mmkb-help summary{list-style:none;cursor:pointer;width:22px;height:22px;border-radius:50%;border:1.5px solid #B85F5F;color:#B85F5F;font:600 13px/19px sans-serif;text-align:center;user-select:none}
.mmkb-help summary::-webkit-details-marker{display:none}
.mmkb-help[open] summary{background:#B85F5F;color:#fff}
.mmkb-help-pop{position:absolute;z-index:50;top:30px;left:50%;transform:translateX(-50%);width:min(320px,86vw);background:#fff;border:1px solid #F0D9D6;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:.9em 1em;font-size:14px;line-height:1.45;font-weight:400;text-transform:none;letter-spacing:0;color:#444;font-family:inherit}
.mmkb-help-pop ul{margin:.5em 0 0 1.1em;padding:0}.mmkb-help-pop li{margin:.3em 0}
@media(max-width:600px){.mmkb-help{position:static}.mmkb-help-pop{left:0;right:0;width:auto;transform:none}}
.mmkb-cartbox .mmkb-save{background:transparent!important;color:#B85F5F!important;border:1.5px solid #B85F5F!important;box-shadow:none!important;padding:.55em 1.1em!important;font-size:.85em!important;letter-spacing:.04em!important;min-height:0!important;width:auto!important}
.mmkb-actions .button{margin:0 .3em .5em 0}
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

	/** [marmar_konditoru_cenas] — tagad tā pati vienotā reģistrācija. */
	public static function shortcode( $atts = array() ) {
		return self::unified();
	}

	/** [marmar_konditori_pogas] — vecais nosaukums, tagad viena forma (agrāk divas pogas). */
	public static function buttons() {
		return self::unified();
	}

	private static function err_msgs() {
		return array(
			'1'       => 'Lūdzu, aizpildi vārdu, e-pastu un Instagram vai mājaslapu, un apstiprini piekrišanu.',
			'city'    => 'Lai parādītos katalogā, izvēlies pilsētu.',
			'photo'   => 'Lai parādītos katalogā, pievieno tortes foto (JPG, PNG vai WebP, līdz 5 MB).',
			'account' => 'Neizdevās izveidot kontu. Lūdzu, pamēģini vēlreiz vai raksti mums e-pastā.',
		);
	}

	/**
	 * Viena reģistrācija konditoriem: konts + īpašas cenas + (pēc izvēles) vieta katalogā.
	 */
	public static function unified() {
		do_action( 'litespeed_control_set_nocache', 'marmar-konditoru-cenas form' );
		$s   = self::settings();
		$uid = get_current_user_id();
		$out = '<div id="konditoru-cenas" class="mmkc-uni"><span id="mmkc-p-katalogs"></span><span id="mmkc-p-cenas"></span><span id="pieteikties"></span>';

		if ( $uid && self::is_konditor( $uid ) ) {
			$out .= '<div class="mmkc-box"><h2>Tavs konditora konts ir aktīvs 🎂</h2><p>Veikalā tu redzi īpašās cenas kūku svecēm un ciparu svecēm. Minimālais pasūtījums — ' . esc_html( (int) $s['min_order'] ) . ' € (bez piegādes). Rēķins pienāk automātiski uz e-pastu, iepriekšējo pasūtījumu vari atkārtot ar vienu klikšķi sadaļā «Mans konts».</p>';
			$out .= '<p><a class="mmkc-btn" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Uz veikalu →</a></p></div></div>';
			return $out;
		}
		if ( isset( $_GET['mmkc'] ) && 'ok' === $_GET['mmkc'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			$out .= '<div class="mmkc-box mmkc-done" role="status"><h2>Paldies! Pieteikums saņemts 🎂</h2><p>Mēs to izskatīsim tuvākajā laikā. Kad konts būs apstiprināts, saņemsi e-pastu ar piekļuvi un saiti paroles iestatīšanai.</p></div></div>';
			return $out;
		}
		if ( $uid && 'pending' === get_user_meta( $uid, self::U_STATUS, true ) ) {
			$out .= '<div class="mmkc-box"><h2>Pieteikums saņemts</h2><p>Tavs pieteikums gaida apstiprinājumu. Kad tas būs apstiprināts, saņemsi e-pastu.</p></div></div>';
			return $out;
		}

		$out .= '<div class="mmkc-hero"><h2>Viena reģistrācija — īpašas cenas un vieta konditoru katalogā</h2>';
		$out .= '<ol class="mmkc-steps"><li><b>1</b><span>Aizpildi formu — izveidojam kontu</span></li><li><b>2</b><span>Mēs apstiprinām pieteikumu</span></li><li><b>3</b><span>Īpašas cenas un vieta katalogā</span></li></ol></div>';
		$out .= '<div class="mmkc-box">';
		$out .= '<ul class="mmkc-terms">';
		$out .= '<li><strong>Īpašas cenas</strong> kūku svecēm un ciparu svecēm — redzamas pēc pieslēgšanās;</li>';
		$out .= '<li>minimālais pasūtījums — ' . esc_html( (int) $s['min_order'] ) . ' € (bez piegādes);</li>';
		$out .= '<li>piegāde uz pakomātu 2,50 €, no 50 € — bez maksas;</li>';
		$out .= '<li>rēķins automātiski pienāk uz e-pastu;</li>';
		$out .= '<li>iepriekšējo pasūtījumu var atkārtot ar vienu klikšķi;</li>';
		$out .= '<li>bez maksas — vieta konditoru katalogā un kopīgs ieraksts Instagram.</li></ul>';

		$err  = isset( $_GET['mmkc_err'] ) ? sanitize_key( wp_unslash( $_GET['mmkc_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$msgs = self::err_msgs();
		if ( $err ) {
			$out .= '<div class="mmkc-msg" role="alert">' . esc_html( $msgs[ $err ] ?? $msgs['1'] ) . '</div>';
		}
		$u      = $uid ? wp_get_current_user() : null;
		$cities = class_exists( 'MarMar_Konditori' ) ? MarMar_Konditori::cities() : array();
		$types  = class_exists( 'MarMar_Konditori' ) ? MarMar_Konditori::types() : array();
		ob_start();
		?>
		<form class="mmkc-form" method="post" enctype="multipart/form-data" action="">
			<?php wp_nonce_field( 'mm_kc_apply', 'mm_kc_nonce' ); ?>
			<input type="hidden" name="mm_kc_apply" value="1">
			<label class="mmkc-hp" aria-hidden="true">Mājaslapa2<input type="text" name="mm_kc_url2" tabindex="-1" autocomplete="off"></label>
			<label>Vārds, uzvārds *<input type="text" name="mm_kc_name" required maxlength="80" autocomplete="name" value="<?php echo esc_attr( $u ? trim( $u->first_name . ' ' . $u->last_name ) : '' ); ?>"></label>
			<label>Konditorejas nosaukums<input type="text" name="mm_kc_brand" maxlength="80" placeholder="ja ir"></label>
			<label>E-pasts *<input type="email" name="mm_kc_email" required maxlength="120" autocomplete="email" value="<?php echo esc_attr( $u ? $u->user_email : '' ); ?>"></label>
			<label>Tālrunis<input type="tel" name="mm_kc_phone" maxlength="30" autocomplete="tel" placeholder="+371 …"></label>
			<label>Instagram vai mājaslapa *<input type="text" name="mm_kc_insta" required maxlength="200" placeholder="@mana.konditoreja"></label>
			<label>Pilsēta<select name="mm_kc_city"><option value="">— izvēlies —</option><?php foreach ( $cities as $c ) : ?><option><?php echo esc_html( $c ); ?></option><?php endforeach; ?></select></label>
			<label>Uzņēmuma nosaukums<input type="text" name="mm_kc_company" maxlength="120"></label>
			<label>Reģ. nr.<input type="text" name="mm_kc_regnr" maxlength="40"></label>
			<label class="mmkc-full">Komentārs<textarea name="mm_kc_msg" rows="2" maxlength="600" placeholder="Piemēram, cik sveču parasti vajag mēnesī"></textarea></label>

			<label class="mmkc-full mmkc-check mmkc-cat"><input type="checkbox" name="mm_kc_catalog" value="1" checked data-mmkc-cat> <span><strong>Rādīt mani konditoru katalogā</strong> — klienti atradīs tavas tortes, un sveces tām pasūtīs šeit. Piekrītu, ka publicē manu vārdu vai konditorejas nosaukumu, pilsētu, foto un Instagram/tālruni/mājaslapu. Datus var mainīt vai dzēst jebkurā brīdī.</span></label>
			<div class="mmkc-full mmkc-catfields" data-mmkc-catfields>
				<?php if ( $types ) : ?>
				<fieldset><legend>Ko gatavo?</legend><div class="mmkc-types"><?php foreach ( $types as $t ) : ?><label class="mmkc-check"><input type="checkbox" name="mm_kc_types[]" value="<?php echo esc_attr( $t ); ?>"> <span><?php echo esc_html( $t ); ?></span></label><?php endforeach; ?></div></fieldset>
				<?php endif; ?>
				<label>Tortes foto katalogam * <small>(JPG, PNG vai WebP, līdz 5 MB)</small><input type="file" name="mm_kc_photo" accept="image/jpeg,image/png,image/webp" data-mmkc-photo required></label>
			</div>

			<label class="mmkc-full mmkc-check"><input type="checkbox" name="mm_kc_consent" value="1" required> <span>Piekrītu, ka MarMar Candles apstrādā šos datus, lai izskatītu pieteikumu un izveidotu konditora kontu. *</span></label>
			<button type="submit">Reģistrēties</button>
		</form>
		<script>(function(){var c=document.querySelector('[data-mmkc-cat]'),f=document.querySelector('[data-mmkc-catfields]'),ph=document.querySelector('[data-mmkc-photo]');if(!c||!f)return;function u(){f.hidden=!c.checked;if(ph)ph.required=c.checked;}c.addEventListener('change',u);u();})();</script>
		<?php
		$out .= ob_get_clean() . '</div></div>';
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
		$out .= '<p class="mmkp-pro">🎂 <strong>Esat konditors?</strong> <a href="' . esc_url( $page . '#konditoru-cenas' ) . '">Viena reģistrācija — īpašas cenas un vieta katalogā →</a></p>';
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
		$ref  = wp_validate_redirect( wp_get_raw_referer(), '' );
		$back = remove_query_arg( array( 'mmkc', 'mmkc_err' ), $ref ? $ref : home_url( '/lv/konditori/' ) );
		$back = strtok( $back, '#' );
		$fail = function ( $code ) use ( $back ) {
			wp_safe_redirect( add_query_arg( 'mmkc_err', $code, $back ) . '#konditoru-cenas' );
			exit;
		};
		if ( ! empty( $_POST['mm_kc_url2'] ) ) { // honeypot
			wp_safe_redirect( add_query_arg( 'mmkc', 'ok', $back ) . '#konditoru-cenas' );
			exit;
		}
		$name    = sanitize_text_field( wp_unslash( $_POST['mm_kc_name'] ?? '' ) );
		$email   = sanitize_email( wp_unslash( $_POST['mm_kc_email'] ?? '' ) );
		$insta   = sanitize_text_field( wp_unslash( $_POST['mm_kc_insta'] ?? '' ) );
		$catalog = ! empty( $_POST['mm_kc_catalog'] );
		$city    = sanitize_text_field( wp_unslash( $_POST['mm_kc_city'] ?? '' ) );
		if ( ! $name || ! is_email( $email ) || ! $insta || empty( $_POST['mm_kc_consent'] ) ) {
			$fail( '1' );
		}
		$photo = null;
		if ( $catalog ) {
			if ( class_exists( 'MarMar_Konditori' ) && ! in_array( $city, MarMar_Konditori::cities(), true ) ) {
				$fail( 'city' );
			}
			$f = $_FILES['mm_kc_photo'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( ! $f || UPLOAD_ERR_OK !== (int) $f['error'] || (int) $f['size'] > 5 * MB_IN_BYTES ) {
				$fail( 'photo' );
			}
			$check = wp_check_filetype_and_ext( $f['tmp_name'], $f['name'], array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) );
			if ( empty( $check['type'] ) || ! @getimagesize( $f['tmp_name'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$fail( 'photo' );
			}
			$photo = $check['ext'];
		}
		$types = array();
		if ( class_exists( 'MarMar_Konditori' ) ) {
			$types = array_values( array_intersect( MarMar_Konditori::types(), array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['mm_kc_types'] ?? array() ) ) ) );
		}
		$data = array(
			'name'    => $name,
			'brand'   => sanitize_text_field( wp_unslash( $_POST['mm_kc_brand'] ?? '' ) ),
			'email'   => $email,
			'phone'   => preg_replace( '/[^0-9+ ()-]/', '', (string) wp_unslash( $_POST['mm_kc_phone'] ?? '' ) ),
			'insta'   => $insta,
			'city'    => $city,
			'company' => sanitize_text_field( wp_unslash( $_POST['mm_kc_company'] ?? '' ) ),
			'regnr'   => sanitize_text_field( wp_unslash( $_POST['mm_kc_regnr'] ?? '' ) ),
			'msg'     => sanitize_textarea_field( wp_unslash( $_POST['mm_kc_msg'] ?? '' ) ),
			'catalog' => $catalog ? 'yes' : 'no',
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
					$fail( 'account' );
				}
			}
		}
		$already = self::is_konditor( $uid );
		if ( ! $already ) {
			update_user_meta( $uid, self::U_DATA, $data );
			update_user_meta( $uid, self::U_STATUS, 'pending' );
			if ( $data['phone'] ) {
				update_user_meta( $uid, 'billing_phone', $data['phone'] );
			}
			if ( $data['company'] ) {
				update_user_meta( $uid, 'billing_company', $data['company'] );
			}
		}

		$cat_id = 0;
		if ( $catalog && ! self::catalog_post( $uid ) ) {
			$cat_id = self::create_catalog_post( $uid, $data, $types, $photo );
		}

		$s         = self::settings();
		$admin_url = admin_url( 'admin.php?page=mm-konditoru-cenas' );
		if ( $already && ! $cat_id ) {
			wp_safe_redirect( add_query_arg( 'mmkc', 'ok', $back ) . '#konditoru-cenas' );
			exit;
		}
		$body  = ( $already ? "Apstiprināts konditors vēlas vietu katalogā:\n\n" : "Jauns konditora pieteikums:\n\n" );
		$body .= "Vārds: {$data['name']}\nKonditoreja: {$data['brand']}\nE-pasts: {$data['email']}\nTālrunis: {$data['phone']}\nInstagram / mājaslapa: {$data['insta']}\nPilsēta: {$data['city']}\nUzņēmums: {$data['company']} {$data['regnr']}\nKomentārs: {$data['msg']}\nKatalogā: " . ( $catalog ? 'jā' : 'nē' ) . "\n\n";
		$body .= $already ? 'Publicēt kartīti: ' . admin_url( 'post.php?post=' . $cat_id . '&action=edit' ) . "\n" : "Apstiprināt vai noraidīt: {$admin_url}\n";
		wp_mail( $s['notify'], ( $already ? 'Konditors katalogam — ' : 'Jauns konditora pieteikums — ' ) . $data['name'], $body, array( 'Reply-To: ' . $email ) );
		if ( ! $already ) {
			wp_mail(
				$email,
				'MarMar Candles: konditora pieteikums saņemts',
				'Sveiki, ' . current( explode( ' ', $name ) ) . "!\n\nPaldies par pieteikumu. Mēs to izskatīsim tuvākajā laikā. Kad konts būs apstiprināts, saņemsi e-pastu ar piekļuvi un saiti paroles iestatīšanai" . ( $catalog ? ', un tava kartīte parādīsies konditoru katalogā' : '' ) . ".\n\nAr cieņu,\nMarMar Candles\nhttps://marmarcandles.com"
			);
		}
		wp_safe_redirect( add_query_arg( 'mmkc', 'ok', $back ) . '#konditoru-cenas' );
		exit;
	}

	/** Kataloga kartīte (mm_konditors), kas piesaistīta lietotājam. */
	public static function catalog_post( $uid ) {
		$pid = (int) get_user_meta( $uid, 'mm_kc_catalog', true );
		if ( $pid && get_post( $pid ) && 'trash' !== get_post_status( $pid ) ) {
			return $pid;
		}
		$u = get_userdata( $uid );
		if ( ! $u || ! post_type_exists( 'mm_konditors' ) ) {
			return 0;
		}
		$q = get_posts( array( 'post_type' => 'mm_konditors', 'post_status' => array( 'publish', 'pending', 'draft' ), 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_mmk_email', 'meta_value' => $u->user_email ) ); // phpcs:ignore
		if ( $q ) {
			update_user_meta( $uid, 'mm_kc_catalog', (int) $q[0] );
			update_post_meta( (int) $q[0], '_mmk_user', $uid );
			return (int) $q[0];
		}
		return 0;
	}

	private static function create_catalog_post( $uid, $data, $types, $photo_ext ) {
		if ( ! post_type_exists( 'mm_konditors' ) ) {
			return 0;
		}
		$title = $data['brand'] ? $data['brand'] : $data['name'];
		$pid   = wp_insert_post( array( 'post_type' => 'mm_konditors', 'post_status' => 'pending', 'post_title' => $title, 'post_content' => '' ), true );
		if ( is_wp_error( $pid ) ) {
			return 0;
		}
		$raw = trim( $data['insta'] );
		$ig  = '';
		$web = '';
		if ( preg_match( '#^(https?://)?(www\.)?instagram\.com/#i', $raw ) || ! preg_match( '#[./]#', ltrim( $raw, '@' ) ) || 0 === strpos( $raw, '@' ) ) {
			$ig = class_exists( 'MarMar_Konditori' ) ? MarMar_Konditori::ig_handle( $raw ) : ltrim( $raw, '@' );
		} else {
			$web = esc_url_raw( preg_match( '#^https?://#i', $raw ) ? $raw : 'https://' . $raw );
		}
		update_post_meta( $pid, '_mmk_city', $data['city'] );
		update_post_meta( $pid, '_mmk_types', $types );
		update_post_meta( $pid, '_mmk_instagram', $ig );
		update_post_meta( $pid, '_mmk_phone', trim( $data['phone'] ) );
		update_post_meta( $pid, '_mmk_website', $web );
		update_post_meta( $pid, '_mmk_email', $data['email'] );
		update_post_meta( $pid, '_mmk_collab', 'yes' );
		update_post_meta( $pid, '_mmk_consent_at', current_time( 'mysql' ) );
		update_post_meta( $pid, '_mmk_user', $uid );
		update_post_meta( $pid, '_mmk_notified', 'via-konditoru-cenas' ); // apstiprinājuma e-pastu sūta šis spraudnis
		update_user_meta( $uid, 'mm_kc_catalog', $pid );
		if ( $photo_ext ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$_FILES['mm_kc_photo']['name'] = sanitize_file_name( 'konditors-' . sanitize_title( $title ) . '-torte.' . $photo_ext );
			$att = media_handle_upload( 'mm_kc_photo', $pid, array( 'post_title' => $title . ' — torte' ) );
			if ( ! is_wp_error( $att ) ) {
				set_post_thumbnail( $pid, $att );
				update_post_meta( $att, '_wp_attachment_image_alt', $title . ' — torte' );
			}
		}
		return $pid;
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
			echo '<table class="widefat striped"><thead><tr><th>Vārds</th><th>E-pasts</th><th>Instagram / mājaslapa</th><th>Uzņēmums</th><th>Komentārs</th><th>Katalogā</th><th>Datums</th><th></th></tr></thead><tbody>';
			foreach ( $users as $u ) {
				$d = (array) get_user_meta( $u->ID, self::U_DATA, true );
				echo '<tr><td>' . esc_html( $d['name'] ?? $u->display_name ) . '</td><td>' . esc_html( $u->user_email ) . '</td><td>' . esc_html( $d['insta'] ?? '' ) . '</td><td>' . esc_html( trim( ( $d['company'] ?? '' ) . ' ' . ( $d['regnr'] ?? '' ) ) ) . '</td><td>' . esc_html( $d['msg'] ?? '' ) . '</td><td>' . self::catalog_cell( $u->ID ) . '</td><td>' . esc_html( $d['date'] ?? '' ) . '</td><td>';
				foreach ( array( 'approve' => 'Apstiprināt', 'reject' => 'Noraidīt' ) as $act => $lbl ) {
					if ( ( 'approve' === $act && 'approved' === $status ) || ( 'reject' === $act && 'rejected' === $status ) ) {
						continue;
					}
					$url = wp_nonce_url( admin_url( 'admin-post.php?action=mm_kc_decide&do=' . $act . '&user=' . $u->ID ), 'mm_kc_decide_' . $u->ID );
					echo '<a class="button' . ( 'approve' === $act ? ' button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $lbl ) . '</a> ';
				}
				if ( 'approved' === $status ) {
					$murl = wp_nonce_url( admin_url( 'admin-post.php?action=mm_kc_mail&user=' . $u->ID ), 'mm_kc_mail_' . $u->ID );
					echo '<a class="button" data-mmkc-mail="' . (int) $u->ID . '" href="' . esc_url( $murl ) . '">Nosūtīt e-pastu «konts izveidots»</a>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		if ( isset( $_GET['linked'] ) ) { // phpcs:ignore
			echo '<div class="notice notice-success"><p>' . implode( '<br>', array_map( 'esc_html', (array) get_option( 'mm_kc_link_log', array() ) ) ) . '</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:1em 0">';
		wp_nonce_field( 'mm_kc_link_catalog' );
		echo '<input type="hidden" name="action" value="mm_kc_link_catalog"><button class="button" data-mmkc-link>Izveidot kontus kataloga konditoriem (jauniem — e-pasts ar paroles saiti)</button></form>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0 0 1em">';
		wp_nonce_field( 'mm_kc_test_mail' );
		echo '<input type="hidden" name="action" value="mm_kc_test_mail"><button class="button" data-mmkc-test>Nosūtīt testa e-pastu sev</button></form>';
		if ( class_exists( 'MarMar_KC_Box' ) ) {
			MarMar_KC_Box::admin_section();
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

	/**
	 * Vienreizēji: katram publicētam kataloga konditoram — konts ar īpašām cenām (bez e-pasta).
	 * Ja konts ar šo e-pastu jau ir — tikai sasaistām un apstiprinām.
	 */
	public static function admin_link_catalog() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'mm_kc_link_catalog' ) ) {
			wp_die( 'Nav atļauts.' );
		}
		$log = array();
		$ids = get_posts( array( 'post_type' => 'mm_konditors', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
		foreach ( $ids as $pid ) {
			$email = sanitize_email( (string) get_post_meta( $pid, '_mmk_email', true ) );
			$title = get_the_title( $pid );
			if ( ! is_email( $email ) ) {
				$log[] = $title . ': nav e-pasta';
				continue;
			}
			$u = get_user_by( 'email', $email );
			if ( $u ) {
				$uid = $u->ID;
				$was = 'esošs konts';
			} else {
				$uid = wp_insert_user( array( 'user_login' => self::unique_login( $email ), 'user_email' => $email, 'user_pass' => wp_generate_password( 20 ), 'first_name' => $title, 'role' => 'customer' ) );
				if ( is_wp_error( $uid ) ) {
					$log[] = $title . ': kļūda — ' . $uid->get_error_message();
					continue;
				}
				$u   = get_userdata( $uid );
				$was = 'jauns konts';
			}
			update_user_meta( $uid, 'mm_kc_catalog', $pid );
			update_post_meta( $pid, '_mmk_user', $uid );
			if ( ! self::is_konditor( $uid ) ) {
				$u->add_role( self::ROLE );
				update_user_meta( $uid, self::U_STATUS, 'approved' );
				if ( ! get_user_meta( $uid, self::U_DATA, true ) ) {
					$ig = (string) get_post_meta( $pid, '_mmk_instagram', true );
					update_user_meta( $uid, self::U_DATA, array( 'name' => $title, 'brand' => $title, 'email' => $email, 'phone' => (string) get_post_meta( $pid, '_mmk_phone', true ), 'insta' => $ig ? '@' . $ig : (string) get_post_meta( $pid, '_mmk_website', true ), 'city' => (string) get_post_meta( $pid, '_mmk_city', true ), 'msg' => 'No kataloga (konts izveidots automātiski)', 'catalog' => 'yes', 'date' => current_time( 'mysql' ) ) );
				}
				if ( 'jauns konts' === $was ) {
					self::approved_mail( $u, '', true );
					$was .= ', e-pasts nosūtīts';
				}
				$log[] = $title . ': ' . $was . ', īpašas cenas ieslēgtas';
			} else {
				$log[] = $title . ': ' . $was . ' (jau konditors), sasaistīts';
			}
		}
		update_option( 'mm_kc_link_log', $log, false );
		wp_safe_redirect( admin_url( 'admin.php?page=mm-konditoru-cenas&linked=1' ) );
		exit;
	}

	private static function catalog_cell( $uid ) {
		$cat = self::catalog_post( $uid );
		if ( ! $cat ) {
			return '—';
		}
		$st = get_post_status( $cat );
		$lb = array( 'publish' => 'publicēta', 'pending' => 'gaida', 'draft' => 'melnraksts' );
		return '<a href="' . esc_url( admin_url( 'post.php?post=' . $cat . '&action=edit' ) ) . '">' . esc_html( $lb[ $st ] ?? $st ) . '</a>';
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

	/** E-pasts: konts apstiprināts / izveidots — ar paroles saiti. */
	public static function approved_mail( $u, $cat_line = '', $created = false ) {
		$uid = $u->ID;
		$key  = get_password_reset_key( $u );
		$link = is_wp_error( $key ) ? wc_get_page_permalink( 'myaccount' ) : add_query_arg( array( 'key' => $key, 'id' => $uid ), wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ) );
		$name = $u->first_name ? $u->first_name : $u->display_name;
		$cat     = self::catalog_post( $uid );
		$catalog = $cat && 'publish' === get_post_status( $cat );
		MarMar_KC_Mail::send(
			$u->user_email,
			$created ? 'MarMar Candles: tavs konditora konts ir izveidots 🎂' : 'MarMar Candles: tavs konditora konts ir apstiprināts 🎂',
			self::pretty_args( $name, $link, $created, $catalog && ! $created )
		);
	}

	/** Saturs skaistajai vēstulei «konts izveidots / apstiprināts». */
	public static function pretty_args( $name, $link, $created, $catalog ) {
		$min = (int) self::settings()['min_order'];
		return array(
			'title'        => $created ? 'Tavs konditora konts ir izveidots' : 'Tavs konditora konts ir apstiprināts',
			'preheader'    => 'Tagad vari pasūtīt kūku sveces un ciparu sveces par īpašām cenām.',
			'hello'        => 'Sveiki, ' . $name . '!',
			'intro'        => ( $created ? 'Paldies, ka esi mūsu konditoru katalogā! Mēs izveidojām tev konditora kontu MarMar Candles veikalā' : 'Tavs konditora konts MarMar Candles veikalā ir apstiprināts' ) . ' — tagad vari pasūtīt <strong>kūku sveces un ciparu sveces par īpašām cenām</strong>. Cenas redzēsi pēc pieslēgšanās.' . ( $catalog ? ' Tava kartīte ir publicēta <a href="' . esc_url( home_url( '/lv/konditori/' ) ) . '" style="color:#B9605F;">konditoru katalogā</a>.' : '' ),
			'button_url'   => $link,
			'button_label' => 'Iestatīt paroli',
			'button_hint'  => 'Saite paroles iestatīšanai ir personīga. Pēc tam pieslēdzies sadaļā <a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" style="color:#B9605F;">«Mans konts»</a>.',
			'items'        => array( 'Īpašas cenas kūku svecēm un ciparu svecēm', 'Minimālais pasūtījums — ' . $min . ' € (bez piegādes)', 'Piegāde uz pakomātu 2,50 €, no 50 € — bez maksas', 'Rēķins automātiski pienāk uz e-pastu', 'Iepriekšējo pasūtījumu var atkārtot ar vienu klikšķi' ),
			'note'         => 'Ja ir jautājumi — raksti mums Instagram <a href="https://www.instagram.com/marmar.candles" style="color:#B9605F;">@marmar.candles</a>.',
		);
	}

	/** Testa vēstule uz paziņojumu e-pastu (paroles saite — uz «Mans konts», bez atslēgas). */
	public static function admin_test_mail() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'mm_kc_test_mail' ) ) {
			wp_die( 'Nav atļauts.' );
		}
		$to = self::settings()['notify'];
		$ok = MarMar_KC_Mail::send( $to, '[TESTS] MarMar Candles: tavs konditora konts ir izveidots 🎂', self::pretty_args( 'Tikaso_cake', wc_get_page_permalink( 'myaccount' ), true, false ) );
		update_option( 'mm_kc_link_log', array( $ok ? 'Testa e-pasts nosūtīts.' : 'Neizdevās nosūtīt testa e-pastu.' ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=mm-konditoru-cenas&linked=1' ) );
		exit;
	}

	public static function admin_mail() {
		$uid = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
		if ( ! $uid || ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'mm_kc_mail_' . $uid ) ) {
			wp_die( 'Nav atļauts.' );
		}
		$u = get_userdata( $uid );
		if ( $u && self::is_konditor( $uid ) ) {
			self::approved_mail( $u, '', true );
			update_option( 'mm_kc_link_log', array( 'E-pasts nosūtīts: ' . $u->display_name ), false );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=mm-konditoru-cenas&linked=1' ) );
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
			$cat      = self::catalog_post( $uid );
			$cat_line = '';
			if ( $cat ) {
				update_post_meta( $cat, '_mmk_notified', 'via-konditoru-cenas' );
				if ( 'publish' !== get_post_status( $cat ) ) {
					wp_update_post( array( 'ID' => $cat, 'post_status' => 'publish' ) );
				}
				$cat_line = "\nTava kartīte ir publicēta konditoru katalogā: " . home_url( '/lv/konditori/' ) . "\n";
			}
			self::approved_mail( $u, $cat_line );
		} elseif ( 'reject' === $do ) {
			$u->remove_role( self::ROLE );
			update_user_meta( $uid, self::U_STATUS, 'rejected' );
			$cat = self::catalog_post( $uid );
			if ( $cat && 'pending' === get_post_status( $cat ) ) {
				wp_update_post( array( 'ID' => $cat, 'post_status' => 'draft' ) );
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=mm-konditoru-cenas&done=1' ) );
		exit;
	}
}

register_activation_hook( __FILE__, array( 'MarMar_Konditoru_Cenas', 'activate' ) );
require_once __DIR__ . '/includes/class-box.php';
require_once __DIR__ . '/includes/class-qty.php';
require_once __DIR__ . '/includes/class-mail.php';
add_action( 'plugins_loaded', array( 'MarMar_Konditoru_Cenas', 'init' ) );
add_action( 'plugins_loaded', array( 'MarMar_KC_Box', 'init' ) );
add_action( 'plugins_loaded', array( 'MarMar_KC_Qty', 'init' ) );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'mm_kc_daily' ); } );
