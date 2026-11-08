<?php
/**
 * Plugin Name:       MarMar QA labojumi
 * Description:       Testēšanā atrasto kļūdu labojumi: latviski teksti meklēšanā, 404 lapā un kases kļūdu paziņojumos; 301 no demo lapas «About Us»; grozs, kase un konts netiek iekļauti sitemap.
 * Version:           1.0.0
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
