<?php
namespace MarMarLang;

/** Translation storage: meta _mm_tr_{lang}_{field} on posts and terms, exposed in REST. */
final class Store {
	public const POST_FIELDS = array( 'title', 'content', 'excerpt', 'seo_title', 'seo_desc' );
	public const TERM_FIELDS = array( 'name', 'description', 'seo_title', 'seo_desc' );
	public const POST_TYPES  = array( 'product', 'page', 'post' );

	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
	}

	public static function key( string $lang, string $field ): string {
		return "_mm_tr_{$lang}_{$field}";
	}

	/** Languages that can carry translations (all known except default). */
	private static function langs(): array {
		return array_values( array_diff( array_keys( Languages::LOCALES ), array( Languages::DEFAULT ) ) );
	}

	public static function register(): void {
		foreach ( self::langs() as $lang ) {
			foreach ( self::POST_TYPES as $type ) {
				foreach ( self::POST_FIELDS as $field ) {
					register_post_meta( $type, self::key( $lang, $field ), self::args( 'edit_posts', $field ) );
				}
			}
			register_post_meta( 'nav_menu_item', self::key( $lang, 'title' ), self::args( 'edit_theme_options', 'title' ) );
			foreach ( self::taxonomies() as $tax ) {
				foreach ( self::TERM_FIELDS as $field ) {
					register_term_meta( $tax, self::key( $lang, $field ), self::args( 'manage_categories', $field ) );
				}
			}
		}
	}

	/** Cheap check used on every get_term call. */
	public static function is_translatable_taxonomy( string $tax ): bool {
		return 'product_cat' === $tax || str_starts_with( $tax, 'pa_' );
	}

	/** product_cat + all WooCommerce attribute taxonomies (pa_*). */
	public static function taxonomies(): array {
		$taxes = array( 'product_cat' );
		if ( function_exists( 'wc_get_attribute_taxonomy_names' ) ) {
			$taxes = array_merge( $taxes, wc_get_attribute_taxonomy_names() );
		}
		return $taxes;
	}

	private static function args( string $cap, string $field ): array {
		return array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'auth_callback'     => fn() => current_user_can( $cap ),
			'sanitize_callback' => in_array( $field, array( 'content', 'description', 'excerpt' ), true ) ? 'wp_kses_post' : 'sanitize_text_field',
		);
	}

	public static function get( string $kind, int $id, string $field, string $lang ): ?string {
		if ( $lang === Languages::DEFAULT || $id <= 0 ) {
			return null;
		}
		$v = 'term' === $kind ? get_term_meta( $id, self::key( $lang, $field ), true ) : get_post_meta( $id, self::key( $lang, $field ), true );
		$v = is_string( $v ) ? trim( $v ) : '';
		return '' === $v ? null : $v;
	}

	public static function has( string $kind, int $id, string $lang ): bool {
		if ( $lang === Languages::DEFAULT ) {
			return true;
		}
		return null !== self::get( $kind, $id, 'term' === $kind ? 'name' : 'title', $lang );
	}
}
