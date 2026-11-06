<?php
/**
 * Plugin Name:       MarMar Speed
 * Description:       Ātruma uzlabojumi: produktu video ielādējas tikai pēc “play”; piegādes spraudņu skripti (UNISEND, DPD, select2) ielādējas tikai grozā un noformēšanā.
 * Version:           1.2.1
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Speed {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'buffer' ), 1 );
		add_filter( 'wp_video_shortcode', array( __CLASS__, 'video_shortcode' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'drop_shipping_assets' ), 9999 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'drop_shipping_assets' ), 9999 );
		add_action( 'wp_print_styles', array( __CLASS__, 'drop_shipping_assets' ), 9999 );
	}

	/**
	 * Piegādes spraudņi ielādē savus skriptus katrā lapā. UNISEND skripts katrā lapā
	 * veic sinhronu AJAX pieprasījumu (fetchSavedTerminalSync) un “sasaldē” lapu 1,5–3 s.
	 * Tie vajadzīgi tikai grozā, noformēšanā un kontā.
	 */
	public static function drop_shipping_assets() {
		if ( is_admin() || ! function_exists( 'is_cart' ) ) {
			return;
		}
		// Stackable ielādē Montserrat 18 variantos, bet tēma jau ielādē Montserrat 400–800 — dublikāts.
		wp_dequeue_style( 'stackable-google-fonts' );
		if ( is_cart() || is_checkout() || is_account_page() ) {
			return;
		}
		$scripts = array( 'woo-lithuaniapost-lpexpress-terminal-block', 'woo-lithuaniapost-shipping-logo', 'woo-lithuaniapost', 'woo-shipping-dpd-baltic', 'select2' );
		foreach ( $scripts as $h ) {
			wp_dequeue_script( $h );
		}
		foreach ( array( 'woo-lithuaniapost', 'select2-css', 'select2css' ) as $h ) {
			wp_dequeue_style( $h );
		}
	}

	/** Produkta lapās: video galerijas spraudnis liek preload="auto" — mainām uz "none". */
	public static function buffer() {
		if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		ob_start( array( __CLASS__, 'filter_html' ) );
	}

	public static function filter_html( $html ) {
		if ( false === strpos( $html, 'vwg_video' ) ) {
			return $html;
		}
		// Tikai <video> tagos (arī JSON-escaped variantā).
		return preg_replace_callback(
			'~<video\b[^>]*>~i',
			function ( $m ) {
				return str_replace( array( 'preload="auto"', 'preload=\\"auto\\"' ), array( 'preload="none"', 'preload=\\"none\\"' ), $m[0] );
			},
			$html
		);
	}

	/** [video] apraksta tekstā — arī nelādējam iepriekš. */
	public static function video_shortcode( $out ) {
		$out = str_replace( array( 'preload="metadata"', 'preload="auto"' ), 'preload="none"', $out );
		// Bez iepriekšējas ielādes video būtu melns — liekam produkta foto kā vāku.
		if ( false === strpos( $out, 'poster=' ) && function_exists( 'is_product' ) && is_product() ) {
			$img = get_the_post_thumbnail_url( get_queried_object_id(), 'woocommerce_single' );
			if ( $img ) {
				$out = preg_replace( '/<video\b/', '<video poster="' . esc_url( $img ) . '"', $out, 1 );
			}
		}
		return $out;
	}
}

add_action( 'plugins_loaded', array( 'MarMar_Speed', 'init' ) );
