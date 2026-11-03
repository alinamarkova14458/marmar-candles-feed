<?php
namespace MarMarLang;

/** /mm-sitemap-{lang}.xml — translated posts and product categories with hreflang alternates. */
final class Sitemap {
	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'maybe_render' ), 99 );
	}

	public static function maybe_render(): void {
		$path = (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
		if ( ! preg_match( '#^/mm-sitemap-([a-z]{2})\.xml$#', $path, $m ) ) {
			return;
		}
		$lang = $m[1];
		if ( ! Languages::is_enabled( $lang ) || ! Languages::is_public() ) {
			return; // falls through to WordPress 404
		}
		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		echo self::xml( $lang );
		exit;
	}

	/** @return array<int, array{lv:string, langs:string[]}> */
	private static function entries( string $lang ): array {
		$out = array();
		$ids = get_posts( array(
			'post_type'      => Store::POST_TYPES,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => Store::key( $lang, 'title' ), 'value' => '', 'compare' => '!=' ) ),
		) );
		foreach ( $ids as $id ) {
			$out[] = array( 'lv' => get_permalink( $id ), 'kind' => 'post', 'id' => (int) $id );
		}
		$terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'meta_query' => array( array( 'key' => Store::key( $lang, 'name' ), 'value' => '', 'compare' => '!=' ) ),
		) );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $t ) {
				$out[] = array( 'lv' => get_term_link( $t ), 'kind' => 'term', 'id' => (int) $t->term_id );
			}
		}
		return $out;
	}

	private static function xml( string $lang ): string {
		$x  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$x .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
		foreach ( self::entries( $lang ) as $e ) {
			if ( ! is_string( $e['lv'] ) ) {
				continue;
			}
			$x .= "<url>\n<loc>" . esc_url( Links::to_lang( $e['lv'], $lang ) ) . "</loc>\n";
			foreach ( Languages::all() as $l ) {
				if ( Store::has( $e['kind'], $e['id'], $l ) ) {
					$x .= '<xhtml:link rel="alternate" hreflang="' . esc_attr( $l ) . '" href="' . esc_url( Links::to_lang( $e['lv'], $l ) ) . "\"/>\n";
				}
			}
			$x .= "</url>\n";
		}
		return $x . '</urlset>' . "\n";
	}
}
