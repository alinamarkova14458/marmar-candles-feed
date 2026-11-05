<?php
/**
 * Plugin Name:       MarMar Filters
 * Description:       Фильтры на страницах магазина и категорий: подкатегория, цена, «Pieejams uzreiz», дизайн, цвет. Колонка слева на компьютере, кнопка «Filtri» на телефоне. Страницы с фильтрами закрыты от индексации.
 * Version:           1.0.0
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Filters {

	const VERSION   = '1.0.0';
	const READY_CAT = 'sveces-pieejamas-uzreiz';

	/** Атрибуты-фильтры: slug без pa_ => заголовок. */
	const ATTRIBUTES = array(
		'izvelieties-krasu'   => 'Krāsa',
		'izvelieties-dizainu' => 'Dizains',
	);

	/** Ценовые диапазоны (min, max|null, подпись). */
	const PRICES = array(
		array( 0, 3, 'līdz 3 €' ),
		array( 3, 5, '3–5 €' ),
		array( 5, 10, '5–10 €' ),
		array( 10, 20, '10–20 €' ),
		array( 20, null, 'no 20 €' ),
	);

	public static function init() {
		add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'open_layout' ), 1 );
		add_action( 'woocommerce_after_shop_loop', array( __CLASS__, 'close_layout' ), 999 );
		add_action( 'woocommerce_no_products_found', array( __CLASS__, 'no_products' ), 1 );
		add_action( 'woocommerce_product_query', array( __CLASS__, 'apply_ready_filter' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'robots' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'wp_robots' ) );
	}

	private static function is_listing() {
		return function_exists( 'is_shop' ) && ( is_shop() || is_product_category() );
	}

	/* ---------------- Состояние фильтров ---------------- */

	private static function filter_keys() {
		$keys = array( 'min_price', 'max_price', 'mm_ready' );
		foreach ( array_keys( self::ATTRIBUTES ) as $a ) {
			$keys[] = 'filter_' . $a;
			$keys[] = 'query_type_' . $a;
		}
		return $keys;
	}

	public static function has_filters() {
		foreach ( self::filter_keys() as $k ) {
			if ( isset( $_GET[ $k ] ) && '' !== $_GET[ $k ] ) { // phpcs:ignore WordPress.Security.NonceVerification
				return true;
			}
		}
		return false;
	}

	/** Текущий URL без пагинации. */
	private static function base_url() {
		$url = remove_query_arg( array( 'paged', 'product-page' ) );
		return preg_replace( '#/page/\d+/?#', '/', $url );
	}

	/* ---------------- «Pieejams uzreiz» ---------------- */

	public static function apply_ready_filter( $q ) {
		if ( empty( $_GET['mm_ready'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$tax   = (array) $q->get( 'tax_query' );
		$tax[] = array(
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => array( self::READY_CAT ),
		);
		$q->set( 'tax_query', $tax );
	}

	/* ---------------- SEO: страницы с фильтрами не индексируем ---------------- */

	public static function robots( $robots ) {
		if ( self::is_listing() && self::has_filters() ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	}

	public static function wp_robots( $robots ) {
		if ( self::is_listing() && self::has_filters() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/* ---------------- Вывод ---------------- */

	public static function assets() {
		if ( ! self::is_listing() ) {
			return;
		}
		wp_enqueue_style( 'marmar-filters', plugin_dir_url( __FILE__ ) . 'assets/filters.css', array(), self::VERSION );
		wp_enqueue_script( 'marmar-filters', plugin_dir_url( __FILE__ ) . 'assets/filters.js', array(), self::VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	private static $opened = false;

	public static function open_layout() {
		if ( ! self::is_listing() || self::$opened ) {
			return;
		}
		self::$opened = true;
		echo '<div class="mm-shop">';
		self::render_sidebar();
		echo '<div class="mm-shop__main">';
		self::render_mobile_button();
	}

	public static function close_layout() {
		if ( ! self::$opened ) {
			return;
		}
		echo '</div></div>';
		self::$opened = false;
	}

	public static function no_products() {
		if ( ! self::is_listing() || ! self::has_filters() ) {
			return;
		}
		echo '<div class="mm-shop mm-shop--empty">';
		self::render_sidebar();
		echo '<div class="mm-shop__main">';
		self::render_mobile_button();
		echo '<p class="mm-empty">Ar šiem filtriem nekas neatradās. <a href="' . esc_url( self::clear_url() ) . '">Notīrīt filtrus</a></p>';
		echo '</div></div>';
	}

	private static function active_count() {
		$n = 0;
		if ( isset( $_GET['min_price'] ) || isset( $_GET['max_price'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$n++;
		}
		if ( ! empty( $_GET['mm_ready'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$n++;
		}
		foreach ( array_keys( self::ATTRIBUTES ) as $a ) {
			if ( ! empty( $_GET[ 'filter_' . $a ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$n += count( explode( ',', sanitize_text_field( wp_unslash( $_GET[ 'filter_' . $a ] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}
		}
		return $n;
	}

	private static function clear_url() {
		return remove_query_arg( self::filter_keys(), self::base_url() );
	}

	private static function render_mobile_button() {
		$n = self::active_count();
		echo '<button type="button" class="mm-filters-toggle" aria-controls="mm-filters" aria-expanded="false">';
		echo '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18M6 12h12M10 19h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg>';
		echo '<span>Filtri</span>' . ( $n ? '<span class="mm-badge">' . (int) $n . '</span>' : '' ) . '</button>';
	}

	private static function render_sidebar() {
		echo '<aside id="mm-filters" class="mm-filters" aria-label="Filtri">';
		echo '<div class="mm-filters__head"><span class="mm-filters__title">Filtri</span><button type="button" class="mm-filters__close" aria-label="Aizvērt">×</button></div>';
		echo '<div class="mm-filters__body">';

		if ( self::has_filters() ) {
			echo '<p class="mm-clear"><a href="' . esc_url( self::clear_url() ) . '">✕ Notīrīt visus filtrus</a></p>';
		}

		self::render_categories();
		self::render_ready();
		self::render_prices();

		foreach ( self::ATTRIBUTES as $slug => $label ) {
			if ( ! taxonomy_exists( 'pa_' . $slug ) ) {
				continue;
			}
			ob_start();
			the_widget(
				'WC_Widget_Layered_Nav',
				array(
					'title'        => $label,
					'attribute'    => $slug,
					'display_type' => 'list',
					'query_type'   => 'or',
				),
				array(
					'before_widget' => '<section class="mm-group mm-group--' . esc_attr( $slug ) . '">',
					'after_widget'  => '</section>',
					'before_title'  => '<h3 class="mm-group__title">',
					'after_title'   => '</h3>',
				)
			);
			echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput -- вывод виджета WooCommerce
		}

		echo '</div>';
		echo '<div class="mm-filters__foot"><button type="button" class="mm-filters__apply">Rādīt preces</button></div>';
		echo '</aside>';
		echo '<div class="mm-filters-backdrop" hidden></div>';
	}

	private static function render_categories() {
		$current = is_product_category() ? get_queried_object() : null;
		$parent  = $current ? $current->term_id : 0;
		$terms   = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $parent,
				'hide_empty' => true,
				'orderby'    => 'menu_order',
				'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			)
		);
		// Для листовой категории показываем «соседей», чтобы было куда перейти.
		if ( ( is_wp_error( $terms ) || ! $terms ) && $current && $current->parent ) {
			$terms = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $current->parent, 'hide_empty' => true, 'orderby' => 'menu_order' ) );
		}
		if ( is_wp_error( $terms ) || ! $terms ) {
			return;
		}
		echo '<section class="mm-group"><h3 class="mm-group__title">Kategorija</h3><ul class="mm-list">';
		foreach ( $terms as $t ) {
			$active = $current && $current->term_id === $t->term_id;
			echo '<li' . ( $active ? ' class="is-active"' : '' ) . '><a href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . ' <span class="mm-count">' . (int) $t->count . '</span></a></li>';
		}
		echo '</ul></section>';
	}

	private static function render_ready() {
		$current = is_product_category() ? get_queried_object() : null;
		if ( $current && self::READY_CAT === $current->slug ) {
			return;
		}
		if ( ! term_exists( self::READY_CAT, 'product_cat' ) ) {
			return;
		}
		$on  = ! empty( $_GET['mm_ready'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$url = $on ? remove_query_arg( 'mm_ready', self::base_url() ) : add_query_arg( 'mm_ready', 1, self::base_url() );
		echo '<section class="mm-group"><h3 class="mm-group__title">Pieejamība</h3><ul class="mm-list mm-list--check">';
		echo '<li' . ( $on ? ' class="is-active"' : '' ) . '><a href="' . esc_url( $url ) . '" rel="nofollow">Pieejams uzreiz <small>(bez gaidīšanas)</small></a></li>';
		echo '</ul></section>';
	}

	private static function render_prices() {
		$min = isset( $_GET['min_price'] ) ? (float) $_GET['min_price'] : null; // phpcs:ignore WordPress.Security.NonceVerification
		$max = isset( $_GET['max_price'] ) ? (float) $_GET['max_price'] : null; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<section class="mm-group"><h3 class="mm-group__title">Cena</h3><ul class="mm-list mm-list--check">';
		foreach ( self::PRICES as $range ) {
			list( $lo, $hi, $label ) = $range;
			$active = null !== $min && (float) $lo === $min && ( null === $hi ? null === $max : (float) $hi === $max );
			if ( $active ) {
				$url = remove_query_arg( array( 'min_price', 'max_price' ), self::base_url() );
			} else {
				$url = remove_query_arg( array( 'min_price', 'max_price' ), self::base_url() );
				$url = add_query_arg( null === $hi ? array( 'min_price' => $lo ) : array( 'min_price' => $lo, 'max_price' => $hi ), $url );
			}
			echo '<li' . ( $active ? ' class="is-active"' : '' ) . '><a href="' . esc_url( $url ) . '" rel="nofollow">' . esc_html( $label ) . '</a></li>';
		}
		echo '</ul></section>';
	}
}

add_action( 'plugins_loaded', function () {
	if ( class_exists( 'WooCommerce' ) ) {
		MarMar_Filters::init();
	}
} );

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
