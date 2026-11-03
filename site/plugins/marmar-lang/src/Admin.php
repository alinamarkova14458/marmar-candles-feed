<?php
namespace MarMarLang;

/** "Tulkojumi" fields for posts and terms, settings page, language switcher. */
final class Admin {
	private const NONCE = 'mm_tr_save';

	private const POST_LABELS = array(
		'title'     => 'Nosaukums',
		'excerpt'   => 'Īsais apraksts',
		'content'   => 'Apraksts / teksts',
		'seo_title' => 'SEO virsraksts',
		'seo_desc'  => 'SEO apraksts',
	);
	private const TERM_LABELS = array(
		'name'        => 'Nosaukums',
		'description' => 'Apraksts',
		'seo_title'   => 'SEO virsraksts',
		'seo_desc'    => 'SEO apraksts',
	);

	public static function boot(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'term_hooks' ), 30 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
		add_shortcode( 'mm_lang_switcher', array( __CLASS__, 'switcher' ) );
		add_filter( 'wp_nav_menu_items', array( __CLASS__, 'menu_switcher' ), 20, 2 );
	}

	/* ---------- posts ---------- */

	public static function meta_box(): void {
		foreach ( Store::POST_TYPES as $type ) {
			add_meta_box( 'mm-tr', 'Tulkojumi', array( __CLASS__, 'render_post' ), $type, 'normal', 'default', array( '__back_compat_meta_box' => false ) );
		}
	}

	public static function render_post( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, 'mm_tr_nonce' );
		foreach ( Languages::enabled() as $lang ) {
			echo '<h4>' . esc_html( Languages::LABELS[ $lang ] ) . '</h4><table class="form-table"><tbody>';
			foreach ( self::POST_LABELS as $field => $label ) {
				$val = (string) get_post_meta( $post->ID, Store::key( $lang, $field ), true );
				self::row( "mm_tr[{$lang}][{$field}]", $label, $val, in_array( $field, array( 'content', 'excerpt', 'seo_desc' ), true ) ? ( 'content' === $field ? 8 : 3 ) : 0 );
			}
			echo '</tbody></table>';
		}
	}

	private static function row( string $name, string $label, string $val, int $rows ): void {
		$id = sanitize_html_class( str_replace( array( '[', ']' ), '-', $name ) );
		echo '<tr><th><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( $rows ) {
			echo '<textarea class="large-text" rows="' . (int) $rows . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $val ) . '</textarea>';
		} else {
			echo '<input type="text" class="large-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '">';
		}
		echo '</td></tr>';
	}

	private static function posted(): ?array {
		if ( empty( $_POST['mm_tr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mm_tr_nonce'] ) ), self::NONCE ) ) {
			return null;
		}
		return isset( $_POST['mm_tr'] ) && is_array( $_POST['mm_tr'] ) ? wp_unslash( $_POST['mm_tr'] ) : array();
	}

	private static function clean( string $field, $v ): string {
		$v = is_string( $v ) ? $v : '';
		return in_array( $field, array( 'content', 'excerpt', 'description' ), true ) ? wp_kses_post( $v ) : sanitize_text_field( $v );
	}

	public static function save_post( $post_id, $post ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! in_array( $post->post_type ?? '', Store::POST_TYPES, true ) ) {
			return;
		}
		$data = self::posted();
		if ( null === $data || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( Languages::enabled() as $lang ) {
			foreach ( Store::POST_FIELDS as $field ) {
				if ( ! isset( $data[ $lang ][ $field ] ) ) {
					continue;
				}
				$v = self::clean( $field, $data[ $lang ][ $field ] );
				'' === trim( $v ) ? delete_post_meta( $post_id, Store::key( $lang, $field ) ) : update_post_meta( $post_id, Store::key( $lang, $field ), $v );
			}
		}
	}

	/* ---------- terms ---------- */

	public static function term_hooks(): void {
		foreach ( Store::taxonomies() as $tax ) {
			add_action( "{$tax}_edit_form_fields", array( __CLASS__, 'render_term' ) );
			add_action( "edited_{$tax}", array( __CLASS__, 'save_term' ) );
		}
	}

	public static function render_term( $term ): void {
		echo '<tr><th colspan="2"><h2>Tulkojumi</h2>';
		wp_nonce_field( self::NONCE, 'mm_tr_nonce' );
		echo '</th></tr>';
		foreach ( Languages::enabled() as $lang ) {
			foreach ( self::TERM_LABELS as $field => $label ) {
				$val = (string) get_term_meta( $term->term_id, Store::key( $lang, $field ), true );
				self::row( "mm_tr[{$lang}][{$field}]", Languages::LABELS[ $lang ] . ' — ' . $label, $val, in_array( $field, array( 'description', 'seo_desc' ), true ) ? 4 : 0 );
			}
		}
	}

	public static function save_term( $term_id ): void {
		$data = self::posted();
		if ( null === $data || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		foreach ( Languages::enabled() as $lang ) {
			foreach ( Store::TERM_FIELDS as $field ) {
				if ( ! isset( $data[ $lang ][ $field ] ) ) {
					continue;
				}
				$v = self::clean( $field, $data[ $lang ][ $field ] );
				'' === trim( $v ) ? delete_term_meta( $term_id, Store::key( $lang, $field ) ) : update_term_meta( $term_id, Store::key( $lang, $field ), $v );
			}
		}
	}

	/* ---------- settings ---------- */

	public static function menu(): void {
		add_options_page( 'MarMar Lang', 'MarMar Lang', 'manage_options', 'marmar-lang', array( __CLASS__, 'render_settings' ) );
	}

	public static function settings(): void {
		register_setting( 'marmar-lang', Languages::OPT_ENABLED, array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize_enabled' ), 'default' => array( 'en' ) ) );
		register_setting( 'marmar-lang', Languages::OPT_PUBLIC, array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false ) );
	}

	public static function sanitize_enabled( $value ): array {
		$value = is_array( $value ) ? array_map( 'sanitize_key', $value ) : array();
		return array_values( array_filter( array_unique( $value ), fn( $l ) => isset( Languages::LOCALES[ $l ] ) && Languages::DEFAULT !== $l ) );
	}

	public static function render_settings(): void {
		$enabled = Languages::enabled();
		echo '<div class="wrap"><h1>MarMar Lang</h1><form method="post" action="options.php">';
		settings_fields( 'marmar-lang' );
		echo '<table class="form-table"><tr><th>Valodas</th><td>';
		foreach ( Languages::LOCALES as $lang => $locale ) {
			if ( Languages::DEFAULT === $lang ) {
				continue;
			}
			printf( '<label><input type="checkbox" name="mm_lang_enabled[]" value="%s" %s> %s</label><br>', esc_attr( $lang ), checked( in_array( $lang, $enabled, true ), true, false ), esc_html( Languages::LABELS[ $lang ] ) );
		}
		echo '</td></tr><tr><th>Publiski redzams</th><td>';
		printf( '<label><input type="hidden" name="mm_lang_public" value="0"><input type="checkbox" name="mm_lang_public" value="1" %s> Publiski redzams (izslēgts — valodas redz tikai administrators)</label>', checked( Languages::is_public(), true, false ) );
		echo '</td></tr></table>';
		submit_button();
		echo '</form></div>';
	}

	/* ---------- switcher ---------- */

	public static function switcher(): string {
		if ( ! Languages::is_public() && ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		if ( ! Languages::enabled() ) {
			return '';
		}
		$langs = did_action( 'wp' ) ? Seo::available() : array();
		if ( ! $langs ) {
			$langs = Languages::all();
		}
		if ( count( $langs ) < 2 ) {
			$langs = array( Languages::DEFAULT ); // only Latvian exists for this page
		}
		$path  = (string) parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
		$base  = rtrim( (string) get_option( 'home' ), '/' ) . $path;
		$cur   = Router::current();
		$links = array();
		foreach ( $langs as $l ) {
			$links[] = sprintf( '<a href="%s" hreflang="%s"%s>%s</a>', esc_url( Links::to_lang( $base, $l ) ), esc_attr( $l ), $l === $cur ? ' class="is-current" aria-current="true"' : '', esc_html( Languages::LABELS[ $l ] ) );
		}
		return '<span class="mm-switch">' . implode( ' · ', $links ) . '</span>';
	}

	public static function menu_switcher( $items, $args ) {
		if ( empty( $args->theme_location ) ) {
			return $items;
		}
		$s = self::switcher();
		return '' === $s ? $items : $items . '<li class="menu-item mm-switch-item">' . $s . '</li>';
	}
}
