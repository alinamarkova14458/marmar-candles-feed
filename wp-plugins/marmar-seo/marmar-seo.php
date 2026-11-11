<?php
/**
 * Plugin Name: MarMar SEO
 * Description: Один адрес для каждой страницы: 301 с www./http/area.lv на https://marmarcandles.com, canonical, og:url и карта сайта — на адреса с /lv/ (как их отдаёт GPTranslate).
 * Version: 1.0.0
 * Author: MarMar Candles
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MarMar_SEO {
	const HOST = 'marmarcandles.com';
	const LANG = 'lv';
	/** Языковые префиксы GPTranslate, которые не трогаем. */
	const PREFIXES = array( 'lv', 'en', 'lt', 'et', 'ru' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'force_host' ), 0 );
		add_filter( 'rank_math/frontend/canonical', array( __CLASS__, 'lv_url' ), 99 );
		add_filter( 'rank_math/opengraph/url', array( __CLASS__, 'lv_url' ), 99 );
		add_filter( 'rank_math/sitemap/entry', array( __CLASS__, 'sitemap_entry' ), 99, 3 );
	}

	/** www., marmarcandles.area.lv и http → https://marmarcandles.com (301). */
	public static function force_host() {
		if ( defined( 'WP_CLI' ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) || PHP_SAPI === 'cli' ) {
			return;
		}
		$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( preg_replace( '/:\d+$/', '', $_SERVER['HTTP_HOST'] ) ) : '';
		if ( '' === $host ) {
			return;
		}
		$https = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== strtolower( $_SERVER['HTTPS'] ) )
			|| ( isset( $_SERVER['SERVER_PORT'] ) && '443' == $_SERVER['SERVER_PORT'] )
			|| ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) );
		$plain_http = ! $https && isset( $_SERVER['SERVER_PORT'] ) && '80' == $_SERVER['SERVER_PORT'];
		if ( self::HOST === $host && ! $plain_http ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		header( 'X-Redirect-By: MarMar SEO' );
		header( 'Location: https://' . self::HOST . $uri, true, 301 );
		exit;
	}

	/** https://marmarcandles.com/x/ → https://marmarcandles.com/lv/x/ */
	public static function lv_url( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return $url;
		}
		$p = wp_parse_url( $url );
		if ( empty( $p['host'] ) || self::HOST !== strtolower( preg_replace( '/^www\./i', '', $p['host'] ) ) ) {
			return $url;
		}
		$path = isset( $p['path'] ) ? $p['path'] : '/';
		if ( preg_match( '#^/(' . implode( '|', self::PREFIXES ) . ')(/|$)#', $path ) ) {
			$new_path = $path;
		} elseif ( preg_match( '#^/(wp-content|wp-json|wp-admin|wp-includes)/#', $path ) || preg_match( '#\.(xml|xsl|txt)$#', $path ) ) {
			return $url;
		} else {
			$new_path = '/' . self::LANG . ( '/' === $path ? '/' : $path );
		}
		return 'https://' . self::HOST . $new_path . ( isset( $p['query'] ) ? '?' . $p['query'] : '' );
	}

	public static function sitemap_entry( $entry, $type = '', $object = null ) {
		if ( is_array( $entry ) && ! empty( $entry['loc'] ) ) {
			$entry['loc'] = self::lv_url( $entry['loc'] );
		}
		return $entry;
	}
}
MarMar_SEO::init();
