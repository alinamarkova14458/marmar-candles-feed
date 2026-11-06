<?php
/**
 * Plugin Name:       MarMar Design
 * Description:       Jaunais MarMar Candles dizains virs Blocksy tēmas: krāsas, šrifti Marcellus + Figtree (no sava servera), pogas, liesmiņas, augšējā josla, produkta lapas termiņu bloks un lipīgā “Ielikt grozā” josla. Izslēdzot spraudni — atgriežas iepriekšējais izskats.
 * Version:           1.3.0
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Design {

	const VER = '1.3.0';

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'preload' ), 1 );
		add_action( 'wp_body_open', array( __CLASS__, 'topbar' ), 5 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'eta_box' ), 25 );
		// trust_row nav vajadzīga: produkta lapā jau ir MarMar UX informācijas bloks.
		add_action( 'wp_footer', array( __CLASS__, 'sticky_bar' ), 20 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'theme_mod_header_placements', array( __CLASS__, 'one_logo' ), 20 );
	}

	/**
	 * Telefonā un planšetē — tas pats pilnais logo “MARMAR CANDLES” (agrāk melns aplis ar “M”).
	 * Mainām tikai izvadi; Blocksy iestatījumi datubāzē paliek, izslēdzot spraudni viss atgriežas.
	 */
	public static function one_logo( $v ) {
		if ( ! is_array( $v ) || is_admin() || is_customize_preview() ) {
			return $v;
		}
		$walk = function ( &$node ) use ( &$walk ) {
			if ( ! is_array( $node ) ) {
				return;
			}
			if ( isset( $node['id'] ) && 'logo' === $node['id'] && isset( $node['values']['custom_logo'] ) && is_array( $node['values']['custom_logo'] ) ) {
				$l = $node['values']['custom_logo'];
				if ( ! empty( $l['desktop'] ) ) {
					$node['values']['custom_logo']['tablet'] = $l['desktop'];
					$node['values']['custom_logo']['mobile'] = $l['desktop'];
				}
			}
			foreach ( $node as &$child ) {
				$walk( $child );
			}
		};
		$walk( $v );
		return $v;
	}

	public static function body_class( $c ) {
		$c[] = 'mmd';
		return $c;
	}

	public static function assets() {
		if ( is_admin() ) {
			return;
		}
		$u = plugins_url( 'assets/', __FILE__ );
		wp_enqueue_style( 'marmar-design-fonts', $u . 'fonts.css', array(), self::VER );
		$deps = wp_style_is( 'ct-main-styles', 'registered' ) ? array( 'ct-main-styles' ) : array();
		wp_enqueue_style( 'marmar-design', $u . 'design.css', $deps, self::VER );
		wp_enqueue_script( 'marmar-design', $u . 'design.js', array(), self::VER, array( 'strategy' => 'defer', 'in_footer' => true ) );
		// Vecie Google šrifti (Montserrat, Cormorant) vairs nav vajadzīgi.
		wp_dequeue_style( 'blocksy-fonts-font-source-google' );
	}

	public static function preload() {
		$u = plugins_url( 'assets/fonts/', __FILE__ );
		foreach ( array( 'marcellus-latin', 'figtree-latin' ) as $f ) {
			echo '<link rel="preload" href="' . esc_url( $u . $f . '.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
		}
	}

	public static function topbar() {
		echo '<div class="mmd-topbar" role="note"><span>Bezmaksas piegāde no 50 €</span><span class="mmd-sep mmd-flame" aria-hidden="true"></span><span>Gatavs 5–7 dienās</span><span class="mmd-sep mmd-flame mmd-d" aria-hidden="true"></span><span class="mmd-d">Steidzami 1–2 dienās</span></div>';
	}

	/** Termiņu bloks produkta lapā. Datumu aprēķina JS (lapa tiek kešota). */
	public static function eta_box() {
		global $product;
		if ( ! $product || ! $product->is_purchasable() ) {
			return;
		}
		echo '<div class="mmd-eta" data-mmd-eta>';
		echo '<span class="mmd-eta-ico" aria-hidden="true">✓</span><span><strong>Saņemsiet <span data-mmd-eta-date>5–9 dienu laikā</span></strong><br><small>Izgatavošana 5–7 dienas + pakomātā 1–2 darba dienās. Steidzami — 1–2 dienās (+5 €), izvēlieties grozā.</small></span>';
		echo '</div>';
	}

	public static function trust_row() {
		echo '<div class="mmd-trust"><span>Pakomātā no 2 €</span><span>Bez maksas no 50 €</span><span>Min. pasūtījums 5 €</span></div>';
	}

	public static function sticky_bar() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return;
		}
		echo '<div class="mmd-sticky" data-mmd-sticky hidden><div class="mmd-sticky-info"><span class="mmd-sticky-price">' . wp_kses_post( $product->get_price_html() ) . '</span><small data-mmd-eta-short></small></div><button type="button" class="mmd-sticky-btn" data-mmd-sticky-btn>' . esc_html( $product->is_type( 'simple' ) ? 'Ielikt grozā' : 'Izvēlēties' ) . '</button></div>';
	}
}

add_action( 'plugins_loaded', array( 'MarMar_Design', 'init' ) );
