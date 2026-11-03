<?php
namespace MarMarLang;

/** hreflang alternates and Rank Math title / description per language. */
final class Seo {
	public static function boot(): void {
		add_action( 'wp_head', array( __CLASS__, 'hreflang' ), 2 );
		add_action( 'init', array( __CLASS__, 'hooks' ), 0 );
	}

	public static function hooks(): void {
		if ( Router::current() === Languages::DEFAULT || is_admin() ) {
			return;
		}
		add_filter( 'rank_math/frontend/title', array( __CLASS__, 'title' ), 20 );
		add_filter( 'rank_math/frontend/description', array( __CLASS__, 'description' ), 20 );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'title_without_rank_math' ), 20 );
	}

	/** [kind, id] of the queried object, or null for pages that have no translation unit. */
	private static function object(): ?array {
		if ( is_singular( Store::POST_TYPES ) || ( is_front_page() && get_queried_object_id() ) ) {
			return array( 'post', (int) get_queried_object_id() );
		}
		if ( is_tax() || is_category() ) {
			$t = get_queried_object();
			return $t instanceof \WP_Term ? array( 'term', (int) $t->term_id ) : null;
		}
		return null;
	}

	/** Languages that exist for the current page (default first). */
	public static function available(): array {
		$obj = self::object();
		if ( null === $obj ) {
			return array();
		}
		return array_values( array_filter( Languages::all(), fn( $l ) => Store::has( $obj[0], $obj[1], $l ) ) );
	}

	public static function hreflang(): void {
		if ( ! Languages::is_public() ) {
			return;
		}
		$langs = self::available();
		if ( count( $langs ) < 1 ) {
			return;
		}
		$path = (string) parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
		$base = rtrim( (string) get_option( 'home' ), '/' ) . $path;
		foreach ( $langs as $l ) {
			printf( "<link rel=\"alternate\" hreflang=\"%s\" href=\"%s\" />\n", esc_attr( $l ), esc_url( Links::to_lang( $base, $l ) ) );
		}
		printf( "<link rel=\"alternate\" hreflang=\"x-default\" href=\"%s\" />\n", esc_url( Links::to_lang( $base, Languages::DEFAULT ) ) );
	}

	public static function title( $title ) {
		$obj = self::object();
		if ( null === $obj ) {
			return $title;
		}
		$lang = Router::current();
		$seo  = Store::get( $obj[0], $obj[1], 'seo_title', $lang );
		if ( null !== $seo ) {
			return $seo;
		}
		$name = Store::get( $obj[0], $obj[1], 'post' === $obj[0] ? 'title' : 'name', $lang );
		return null !== $name ? $name . ' | ' . get_bloginfo( 'name' ) : $title;
	}

	public static function title_without_rank_math( $title ) {
		return defined( 'RANK_MATH_VERSION' ) ? $title : self::title( $title );
	}

	public static function description( $desc ) {
		$obj = self::object();
		if ( null === $obj ) {
			return $desc;
		}
		$lang = Router::current();
		$seo  = Store::get( $obj[0], $obj[1], 'seo_desc', $lang );
		if ( null !== $seo ) {
			return $seo;
		}
		$text = Store::get( $obj[0], $obj[1], 'post' === $obj[0] ? 'excerpt' : 'description', $lang )
			?? Store::get( $obj[0], $obj[1], 'post' === $obj[0] ? 'content' : 'name', $lang );
		// Never leave the Latvian description on a translated page.
		return null !== $text ? wp_trim_words( wp_strip_all_tags( $text ), 25, '…' ) : '';
	}
}
