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

	/** Temporary language for work outside the visitor's request (e.g. an email in the order's language). */
	private static ?string $override = null;

	public static function current(): string {
		return self::$override ?? self::$lang;
	}

	public static function override( ?string $lang ): void {
		self::$override = $lang;
	}

	public static function boot(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		$uri = $_SERVER['REQUEST_URI'] ?? '/';
		if ( self::is_service( $uri ) ) {
			// Only the shop front (Store API / wc-ajax) follows the visitor's language;
			// wp-admin and the editor REST API always stay in the default language.
			if ( self::is_storefront_service( $uri ) ) {
				self::$lang = self::from_cookie();
				if ( self::$lang !== Languages::DEFAULT ) {
					add_action( 'send_headers', 'nocache_headers' );
					add_filter( 'rest_post_dispatch', array( __CLASS__, 'rest_nocache' ) );
					do_action( 'litespeed_control_set_nocache', 'marmar-lang: language from cookie' );
				}
			}
		} elseif ( ! is_admin() ) {
			self::route( $uri );
		}
		if ( self::$lang !== Languages::DEFAULT ) {
			add_filter( 'locale', array( __CLASS__, 'locale' ), 1 );
		}
		add_action( 'init', array( __CLASS__, 'hidden_mode' ), 1 );
		add_action( 'wp', array( __CLASS__, 'maybe_404' ) );
		add_action( 'wp_head', array( __CLASS__, 'cookie_script' ), 1 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'redirect_canonical' ), 20 );
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

	public static function is_storefront_service( string $uri ): bool {
		$path = (string) parse_url( $uri, PHP_URL_PATH );
		$q    = (string) parse_url( $uri, PHP_URL_QUERY );
		// Only per-visitor, uncached routes: cart, checkout and batch (never cacheable product lists).
		$route = (string) preg_replace( '#^/wp-json#', '', $path );
		if ( str_contains( $q, 'rest_route=' ) ) {
			parse_str( $q, $qa );
			$route = (string) ( $qa['rest_route'] ?? '' );
		}
		return (bool) preg_match( '#^/wc/store(/v\d+)?/(cart|checkout|batch)(/|$)#', $route ) || str_contains( $q, 'wc-ajax=' );
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
			if ( Languages::is_public() ) { // in hidden mode GPTranslate still owns /lv/
				wp_redirect_early( home_url_early( $rest ), 301 );
			}
			return;
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
		wp_redirect( home_url_early( $_SERVER['REQUEST_URI'] ?? '/' ), 302 );
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

	/**
	 * The cookie is set by a tiny inline script, not a Set-Cookie header, so full-page caches
	 * (LiteSpeed) can cache pages and cached pages still update the visitor's language.
	 */
	public static function cookie_script(): void {
		printf( "<script>document.cookie=\"%s=%s;path=/;max-age=%d;SameSite=Lax\";</script>\n", esc_js( self::COOKIE ), esc_js( self::$lang ), 30 * DAY_IN_SECONDS );
	}

	public static function rest_nocache( $response ) {
		if ( $response instanceof \WP_REST_Response ) {
			$response->header( 'Cache-Control', 'no-store, private' );
		}
		return $response;
	}

	/** WordPress canonical redirects are built from the stripped path: keep the language. */
	public static function redirect_canonical( $url ) {
		if ( ! is_string( $url ) || self::current() === Languages::DEFAULT ) {
			return $url;
		}
		return Links::to_lang( $url, self::current() );
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
