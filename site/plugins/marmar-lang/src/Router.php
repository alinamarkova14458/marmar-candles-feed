<?php
namespace MarMarLang;

/**
 * Language from the URL prefix (/en/…), locale switch, legacy /lv/ redirects,
 * hidden mode and the mm_lang cookie (used by REST / AJAX requests).
 */
final class Router {
	public const COOKIE = 'mm_lang';

	private static string $lang = Languages::DEFAULT;
	/** Prefix found but language not enabled → 404. */
	private static bool $disabled = false;

	public static function current(): string {
		return self::$lang;
	}

	public static function boot(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		$uri = $_SERVER['REQUEST_URI'] ?? '/';
		if ( self::is_service( $uri ) ) {
			self::$lang = self::from_cookie();
		} elseif ( ! is_admin() ) {
			self::route( $uri );
		}
		if ( self::$lang !== Languages::DEFAULT ) {
			add_filter( 'locale', array( __CLASS__, 'locale' ), 1 );
		}
		add_action( 'init', array( __CLASS__, 'hidden_mode' ), 1 );
		add_action( 'wp', array( __CLASS__, 'maybe_404' ) );
		add_action( 'template_redirect', array( __CLASS__, 'set_cookie' ), 0 );
	}

	/** Paths that never carry a language prefix. */
	public static function is_service( string $uri ): bool {
		$path = (string) parse_url( $uri, PHP_URL_PATH );
		if ( preg_match( '#^/(wp-json|wp-admin|wp-content|wp-includes)(/|$)|^/wp-[a-z-]+\.php#', $path ) ) {
			return true;
		}
		$q = (string) parse_url( $uri, PHP_URL_QUERY );
		return str_contains( $q, 'wc-ajax=' ) || str_contains( $q, 'rest_route=' );
	}

	private static function from_cookie(): string {
		$c = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		return Languages::is_enabled( $c ) ? $c : Languages::DEFAULT;
	}

	private static function route( string $uri ): void {
		if ( ! preg_match( '#^/([a-z]{2})(/|\?|$)(.*)$#s', $uri, $m ) || ! isset( Languages::LOCALES[ $m[1] ] ) ) {
			return;
		}
		$lang = $m[1];
		$rest = ( '?' === $m[2] ? '/?' : '/' ) . $m[3];
		if ( Languages::DEFAULT === $lang ) {
			wp_redirect_early( home_url_early( $rest ), 301 );
		}
		$_SERVER['REQUEST_URI'] = $rest;
		// Some servers (and php -S) also pass the path in PATH_INFO / PHP_SELF.
		foreach ( array( 'PATH_INFO', 'PHP_SELF', 'ORIG_PATH_INFO' ) as $k ) {
			if ( isset( $_SERVER[ $k ] ) ) {
				$_SERVER[ $k ] = preg_replace( '#(^|/index\.php)/' . $lang . '(?=/|$)#', '$1', $_SERVER[ $k ], 1 );
			}
		}
		if ( Languages::is_enabled( $lang ) ) {
			self::$lang = $lang;
		} else {
			self::$disabled = true;
		}
	}

	public static function locale( $locale ) {
		return Languages::locale( self::$lang );
	}

	/** Hidden mode: only administrators see non-default languages. */
	public static function hidden_mode(): void {
		if ( self::$lang === Languages::DEFAULT || Languages::is_public() || current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( self::is_service( $_SERVER['REQUEST_URI'] ?? '/' ) ) {
			self::$lang = Languages::DEFAULT;
			return;
		}
		wp_safe_redirect( home_url( $_SERVER['REQUEST_URI'] ?? '/' ), 302 );
		exit;
	}

	public static function maybe_404(): void {
		if ( self::$disabled ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	public static function set_cookie(): void {
		if ( headers_sent() ) {
			return;
		}
		setcookie( self::COOKIE, self::$lang, array(
			'expires'  => time() + 30 * DAY_IN_SECONDS,
			'path'     => '/',
			'secure'   => is_ssl(),
			'httponly' => false,
			'samesite' => 'Lax',
		) );
	}
}

/** home_url() before WordPress is fully loaded (plugin load time). */
function home_url_early( string $path ): string {
	return rtrim( (string) get_option( 'home' ), '/' ) . $path;
}

function wp_redirect_early( string $url, int $status ): void {
	header( 'Location: ' . $url, true, $status );
	exit;
}
