<?php
namespace MarMarLang;

/** Puts translations into titles, texts, products, terms and menus; redirects untranslated pages. */
final class Content {
	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'hooks' ), 0 );
	}

	private static bool $hooked = false;

	/** Registers filters once; also called when an email switches to the order's language. */
	public static function hooks( bool $force = false ): void {
		if ( self::$hooked || Router::current() === Languages::DEFAULT || ( ! $force && is_admin() && ! wp_doing_ajax() ) ) {
			return;
		}
		self::$hooked = true;
		add_filter( 'the_title', array( __CLASS__, 'title' ), 5, 2 );
		add_filter( 'the_content', array( __CLASS__, 'content' ), 5 );
		add_filter( 'get_the_excerpt', array( __CLASS__, 'excerpt' ), 5, 2 );
		add_filter( 'woocommerce_short_description', array( __CLASS__, 'short_description' ), 5 );
		foreach ( array( 'woocommerce_product_get_name', 'woocommerce_product_variation_get_name' ) as $f ) {
			add_filter( $f, array( __CLASS__, 'product_name' ), 5, 2 );
		}
		add_filter( 'woocommerce_product_get_description', array( __CLASS__, 'product_description' ), 5, 2 );
		add_filter( 'woocommerce_product_get_short_description', array( __CLASS__, 'product_short' ), 5, 2 );
		add_filter( 'get_term', array( __CLASS__, 'term' ), 5 );
		add_filter( 'get_terms', array( __CLASS__, 'terms' ), 5 );
		add_filter( 'wp_get_object_terms', array( __CLASS__, 'terms' ), 5 );
		add_filter( 'woocommerce_variation_option_name', array( __CLASS__, 'option_name' ), 5, 3 );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'menu' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_untranslated' ), 1 );
		add_action( 'pre_get_posts', array( __CLASS__, 'only_translated' ) );
		add_filter( 'woocommerce_shortcode_products_query', array( __CLASS__, 'only_translated_args' ) );
		add_filter( 'woocommerce_related_products', array( __CLASS__, 'only_translated_ids' ) );
		add_filter( 'woocommerce_product_get_upsell_ids', array( __CLASS__, 'only_translated_ids' ) );
		add_filter( 'woocommerce_product_get_cross_sell_ids', array( __CLASS__, 'only_translated_ids' ) );
		add_filter( 'rank_math/json_ld', array( __CLASS__, 'json_ld' ), 99 );
	}

	private static function translated_meta_clause(): array {
		return array( 'key' => Store::key( self::lang(), 'title' ), 'value' => '', 'compare' => '!=' );
	}

	/** Listings (shop, categories, search) in a non-default language show only translated items. */
	public static function only_translated( $q ): void {
		if ( ! $q instanceof \WP_Query || $q->is_singular() || ( is_admin() && ! wp_doing_ajax() ) ) {
			return;
		}
		$types   = (array) $q->get( 'post_type' );
		$product = in_array( 'product', $types, true ) || $q->is_post_type_archive( 'product' ) || $q->is_tax( get_object_taxonomies( 'product' ) );
		if ( ! $product && ! $q->is_search() && ! array_intersect( $types, array( 'post', 'page' ) ) ) {
			return;
		}
		if ( 'nav_menu_item' === $q->get( 'post_type' ) ) {
			return;
		}
		$meta   = (array) $q->get( 'meta_query' );
		$meta[] = self::translated_meta_clause();
		$q->set( 'meta_query', $meta );
	}

	public static function only_translated_args( $args ) {
		$args['meta_query']   = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array();
		$args['meta_query'][] = self::translated_meta_clause();
		return $args;
	}

	public static function only_translated_ids( $ids ) {
		return is_array( $ids ) ? array_values( array_filter( $ids, fn( $id ) => Store::has( 'post', (int) $id, self::lang() ) ) ) : $ids;
	}

	/** Rank Math schema: translated product name/description. */
	public static function json_ld( $data ) {
		if ( ! is_array( $data ) || ! is_singular( 'product' ) ) {
			return $data;
		}
		$id   = (int) get_queried_object_id();
		$name = self::tr( 'post', $id, 'title' );
		$desc = self::tr( 'post', $id, 'excerpt' ) ?? self::tr( 'post', $id, 'content' );
		foreach ( $data as $k => $entity ) {
			$type = is_array( $entity ) ? (array) ( $entity['@type'] ?? array() ) : array();
			if ( in_array( 'Product', $type, true ) ) {
				if ( null !== $name ) {
					$data[ $k ]['name'] = $name;
				}
				$data[ $k ]['description'] = null !== $desc ? wp_trim_words( wp_strip_all_tags( $desc ), 40, '…' ) : '';
			}
		}
		return $data;
	}

	private static function lang(): string {
		return Router::current();
	}

	private static function tr( string $kind, int $id, string $field ): ?string {
		return Store::get( $kind, $id, $field, self::lang() );
	}

	public static function title( $title, $id = 0 ) {
		$id = (int) $id;
		if ( $id && in_array( get_post_type( $id ), Store::POST_TYPES, true ) ) {
			return self::tr( 'post', $id, 'title' ) ?? $title;
		}
		return $title;
	}

	public static function content( $content ) {
		$id = (int) get_the_ID();
		if ( $id && in_array( get_post_type( $id ), Store::POST_TYPES, true ) ) {
			$t = self::tr( 'post', $id, 'content' );
			if ( null !== $t ) {
				return $t; // priority 5: core filters (blocks, shortcodes, wpautop) run after us once
			}
		}
		return $content;
	}

	public static function excerpt( $excerpt, $post = null ) {
		$id = $post ? (int) $post->ID : (int) get_the_ID();
		return ( $id ? self::tr( 'post', $id, 'excerpt' ) : null ) ?? $excerpt;
	}

	public static function short_description( $text ) {
		$id = (int) get_the_ID();
		$t  = $id ? self::tr( 'post', $id, 'excerpt' ) : null;
		return null !== $t ? wpautop( wptexturize( $t ) ) : $text;
	}

	/** Parent id for variations, own id otherwise. */
	private static function product_id( $product ): int {
		return (int) ( $product->get_parent_id() ?: $product->get_id() );
	}

	public static function product_name( $name, $product ) {
		$pid = self::product_id( $product );
		$t   = self::tr( 'post', $pid, 'title' );
		if ( null === $t ) {
			return $name;
		}
		if ( $product->get_parent_id() ) {
			// Variation names are "Parent - Option": swap only the parent part.
			$parent = get_post_field( 'post_title', $pid );
			return str_starts_with( (string) $name, (string) $parent ) ? $t . substr( (string) $name, strlen( (string) $parent ) ) : $t;
		}
		return $t;
	}

	public static function product_description( $text, $product ) {
		return self::tr( 'post', self::product_id( $product ), 'content' ) ?? $text;
	}

	public static function product_short( $text, $product ) {
		return self::tr( 'post', self::product_id( $product ), 'excerpt' ) ?? $text;
	}

	/** Reading term meta calls get_term() again (default-meta lookup): guard against recursion. */
	private static bool $in_term = false;

	public static function term( $term ) {
		if ( self::$in_term ) {
			return $term;
		}
		if ( $term instanceof \WP_Term && Store::is_translatable_taxonomy( $term->taxonomy ) ) {
			self::$in_term = true;
			$term       = clone $term;
			$term->name = self::tr( 'term', (int) $term->term_id, 'name' ) ?? $term->name;
			$desc       = self::tr( 'term', (int) $term->term_id, 'description' );
			if ( null !== $desc ) {
				$term->description = $desc;
			}
			self::$in_term = false;
		}
		return $term;
	}

	public static function terms( $terms ) {
		if ( ! is_array( $terms ) ) {
			return $terms;
		}
		foreach ( $terms as $k => $t ) {
			if ( $t instanceof \WP_Term ) {
				$terms[ $k ] = self::term( $t );
			}
		}
		return $terms;
	}

	public static function option_name( $name, $term = null, $attribute = '' ) {
		if ( $term instanceof \WP_Term ) {
			return self::tr( 'term', (int) $term->term_id, 'name' ) ?? $name;
		}
		return $name;
	}

	public static function menu( $items ) {
		foreach ( $items as $item ) {
			$t = self::tr( 'post', (int) $item->ID, 'title' );
			if ( null !== $t ) {
				$item->title = $t;
			}
		}
		return $items;
	}

	/** No translation → this language version does not exist: 302 to the default language. */
	public static function redirect_untranslated(): void {
		$lv = Links::to_lang( home_url_early_current(), Languages::DEFAULT );
		if ( is_singular( Store::POST_TYPES ) ) {
			$id = (int) get_queried_object_id();
			if ( self::is_shop_system_page( $id ) || Store::has( 'post', $id, self::lang() ) ) {
				return;
			}
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$id = (int) wc_get_page_id( 'shop' );
			if ( $id <= 0 || Store::has( 'post', $id, self::lang() ) ) {
				return;
			}
		} elseif ( is_tax() || is_category() ) {
			$term = get_queried_object();
			if ( ! $term instanceof \WP_Term || Store::has( 'term', (int) $term->term_id, self::lang() ) ) {
				return;
			}
		} else {
			return;
		}
		wp_redirect( $lv, 302 );
		exit;
	}

	/** Cart, checkout and account pages work in every language without their own translation. */
	private static function is_shop_system_page( int $id ): bool {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return false;
		}
		return in_array( $id, array( wc_get_page_id( 'cart' ), wc_get_page_id( 'checkout' ), wc_get_page_id( 'myaccount' ) ), true );
	}
}

/** Current request URL in the default language (REQUEST_URI is already stripped by Router). */
function home_url_early_current(): string {
	return rtrim( (string) get_option( 'home' ), '/' ) . ( $_SERVER['REQUEST_URI'] ?? '/' );
}
