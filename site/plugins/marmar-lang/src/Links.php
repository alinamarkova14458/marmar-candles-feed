<?php
namespace MarMarLang;

/** Adds the current language prefix to site links on non-default-language requests. */
final class Links {
	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'hooks' ), 0 );
	}

	public static function hooks(): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( Router::current() === Languages::DEFAULT ) {
			return;
		}
		foreach ( array( 'post_link', 'page_link', 'post_type_link', 'term_link' ) as $f ) {
			add_filter( $f, array( __CLASS__, 'current' ), 20, 2 );
		}
		foreach ( array( 'paginate_links', 'get_pagenum_link', 'attachment_link' ) as $f ) {
			add_filter( $f, array( __CLASS__, 'current' ), 20 );
		}
		add_filter( 'home_url', array( __CLASS__, 'home_url' ), 20, 2 );
		add_filter( 'nav_menu_link_attributes', array( __CLASS__, 'menu_link' ), 20 );
		foreach ( array( 'feed_link', 'taxonomy_feed_link', 'category_feed_link', 'tag_feed_link', 'post_comments_feed_link', 'author_feed_link' ) as $f ) {
			add_filter( $f, array( __CLASS__, 'unprefix' ), 20 );
		}
	}

	/** Feeds stay in the default language (built from already-prefixed links). */
	public static function unprefix( $url ) {
		if ( ! is_string( $url ) ) {
			return $url;
		}
		$base  = rtrim( (string) get_option( 'home' ), '/' );
		$codes = implode( '|', array_keys( Languages::LOCALES ) );
		return preg_replace( '#^' . preg_quote( $base, '#' ) . '/(' . $codes . ')(?=/)#', $base, $url, 1 );
	}

	/** Custom menu links (e.g. "Sākums" → home) are stored as plain URLs. */
	public static function menu_link( $atts ) {
		if ( ! empty( $atts['href'] ) && is_string( $atts['href'] ) ) {
			$atts['href'] = self::to_lang( $atts['href'], Router::current() );
		}
		return $atts;
	}

	public static function current( $url, $obj = null ) {
		if ( ! is_string( $url ) ) {
			return $url;
		}
		// Links to items without a translation lead to their Latvian page.
		$post_id = $obj instanceof \WP_Post ? (int) $obj->ID : ( is_numeric( $obj ) ? (int) $obj : 0 );
		if ( $post_id && in_array( get_post_type( $post_id ), Store::POST_TYPES, true ) && ! self::is_system_page( $post_id ) && ! Store::has( 'post', $post_id, Router::current() ) ) {
			return self::to_lang( $url, Languages::DEFAULT );
		}
		if ( $obj instanceof \WP_Term && Store::is_translatable_taxonomy( $obj->taxonomy ) && ! Store::has( 'term', (int) $obj->term_id, Router::current() ) ) {
			return self::to_lang( $url, Languages::DEFAULT );
		}
		return self::to_lang( $url, Router::current() );
	}

	private static function is_system_page( int $id ): bool {
		return function_exists( 'wc_get_page_id' ) && in_array( $id, array( wc_get_page_id( 'cart' ), wc_get_page_id( 'checkout' ), wc_get_page_id( 'myaccount' ), wc_get_page_id( 'shop' ) ), true );
	}

	public static function home_url( $url, $path ) {
		$path = (string) $path;
		if ( preg_match( '#^/?(wp-json|wp-admin|wp-content|wp-includes|wp-[a-z-]+\.php)#', $path ) || str_contains( $path, 'wc-ajax' ) || str_contains( $path, 'rest_route' ) ) {
			return $url;
		}
		return self::to_lang( $url, Router::current() );
	}

	/** Same URL in $lang; foreign hosts and service paths are returned unchanged. */
	public static function to_lang( string $url, string $lang ): string {
		$home = (string) get_option( 'home' );
		$base = rtrim( $home, '/' );
		if ( ! str_starts_with( $url, $base ) ) {
			return $url;
		}
		$rest = substr( $url, strlen( $base ) );
		if ( '' === $rest || ( '/' !== $rest[0] && '?' !== $rest[0] && '#' !== $rest[0] ) ) {
			if ( '' !== $rest ) {
				return $url; // e.g. https://site.com.evil
			}
			$rest = '/';
		}
		if ( '/' !== $rest[0] ) {
			$rest = '/' . $rest;
		}
		if ( Router::is_service( $rest ) || preg_match( '#/feed(/|$|\?)#', (string) parse_url( $rest, PHP_URL_PATH ) ) ) {
			return $url;
		}
		$codes = implode( '|', array_keys( Languages::LOCALES ) );
		$rest  = preg_replace( '#^/(' . $codes . ')(?=/|\?|\#|$)#', '', $rest, 1 );
		if ( '' === $rest || '/' !== $rest[0] ) {
			$rest = '/' . $rest;
		}
		return $base . ( Languages::DEFAULT === $lang ? '' : '/' . $lang ) . $rest;
	}
}
