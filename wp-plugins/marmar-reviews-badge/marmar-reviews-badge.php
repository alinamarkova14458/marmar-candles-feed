<?php
/**
 * Plugin Name:       MarMar Reviews Badge
 * Description:       Компактная строка «★★★★★ 5,0 · N atsauksmes Google» под ценой на странице товара. Рейтинг и число отзывов берутся из Trustindex (уже скачанные отзывы Google), без внешних скриптов. Можно задать вручную.
 * Version:           1.0.1
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Reviews_Badge {

	const OPTION = 'marmar_reviews_badge';

	public static function init() {
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render' ), 11 );
		add_action( 'wp_head', array( __CLASS__, 'css' ), 50 );
		add_shortcode( 'marmar_google_rating', array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	private static function settings() {
		return wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			array(
				'enabled' => 'yes',
				'rating'  => '',
				'count'   => '',
				'url'     => 'https://g.page/r/CXL0frKfTFEFEBM',
				'min'     => '4.5',
			)
		);
	}

	/**
	 * Рейтинг из Trustindex: сначала сводка профиля, иначе — среднее по скачанным отзывам.
	 *
	 * @return array{rating:float,count:int,source:string}|null
	 */
	public static function detect() {
		$cached = get_transient( 'marmar_reviews_badge_detect' );
		if ( is_array( $cached ) ) {
			return $cached ?: null;
		}
		$found   = null;
		$details = get_option( 'trustindex-google-page-details' );
		if ( is_array( $details ) && ! empty( $details['rating_score'] ) && ! empty( $details['rating_number'] ) ) {
			$found = array( 'rating' => (float) $details['rating_score'], 'count' => (int) $details['rating_number'], 'source' => 'Trustindex: профиль Google' );
		}
		if ( ! $found ) {
			global $wpdb;
			$table = $wpdb->prefix . 'trustindex_google_reviews';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				$row = $wpdb->get_row( "SELECT COUNT(*) n, AVG(rating) r FROM {$table} WHERE rating > 0" ); // phpcs:ignore
				if ( $row && (int) $row->n > 0 ) {
					$found = array( 'rating' => (float) $row->r, 'count' => (int) $row->n, 'source' => 'Trustindex: скачанные отзывы' );
				}
			}
		}
		set_transient( 'marmar_reviews_badge_detect', $found ? $found : array(), 6 * HOUR_IN_SECONDS );
		return $found;
	}

	/** @return array{rating:float,count:int,url:string}|null */
	public static function data() {
		$s = self::settings();
		if ( 'yes' !== $s['enabled'] ) {
			return null;
		}
		if ( '' !== $s['rating'] && '' !== $s['count'] ) {
			$d = array( 'rating' => (float) str_replace( ',', '.', $s['rating'] ), 'count' => (int) $s['count'] );
		} else {
			$d = self::detect();
		}
		if ( ! $d || $d['count'] < 1 || $d['rating'] < (float) $s['min'] ) {
			return null; // низкий рейтинг не показываем
		}
		return array( 'rating' => $d['rating'], 'count' => $d['count'], 'url' => $s['url'] );
	}

	private static function plural( $n ) {
		// Латышский: 1, 21, 31… — «atsauksme», иначе «atsauksmes».
		return ( 1 === $n % 10 && 11 !== $n % 100 ) ? 'atsauksme' : 'atsauksmes';
	}

	public static function html() {
		$d = self::data();
		if ( ! $d ) {
			return '';
		}
		$score = number_format( round( $d['rating'], 1 ), 1, ',', '' );
		$pct   = max( 0, min( 100, $d['rating'] / 5 * 100 ) );
		$label = sprintf( 'Google vērtējums %s no 5, %d %s', $score, $d['count'], self::plural( $d['count'] ) );
		$inner = '<span class="mmrb-stars" aria-hidden="true"><span class="mmrb-stars-fill" style="width:' . esc_attr( round( $pct, 1 ) ) . '%">★★★★★</span>★★★★★</span>'
			. '<span class="mmrb-score">' . esc_html( $score ) . '</span>'
			. '<span class="mmrb-sep" aria-hidden="true">·</span>'
			. '<span class="mmrb-count">' . esc_html( $d['count'] . ' ' . self::plural( $d['count'] ) ) . ' <span class="mmrb-g">Google</span></span>';
		if ( $d['url'] ) {
			return '<a class="mmrb" href="' . esc_url( $d['url'] ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $label ) . '">' . $inner . '</a>';
		}
		return '<span class="mmrb" role="img" aria-label="' . esc_attr( $label ) . '">' . $inner . '</span>';
	}

	public static function render() {
		echo self::html(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private static $css_done = false;

	public static function shortcode() {
		$html = self::html();
		if ( '' !== $html && ! self::$css_done ) {
			self::$css_done = true;
			$html = self::style() . $html; // шорткод на обычной странице — стили вместе с ним
		}
		return $html;
	}

	public static function css() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		self::$css_done = true;
		echo self::style(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private static function style() {
		return '<style id="marmar-reviews-badge">'
			. '.mmrb{display:inline-flex;align-items:center;gap:6px;margin:6px 0 14px;font-size:14px;color:inherit;text-decoration:none;line-height:1}'
			. 'a.mmrb:hover .mmrb-count{text-decoration:underline}'
			. '.mmrb-stars{position:relative;display:inline-block;color:#e3d6d4;letter-spacing:1px;font-size:15px}'
			. '.mmrb-stars-fill{position:absolute;left:0;top:0;overflow:hidden;white-space:nowrap;color:#e7a93c}'
			. '.mmrb-score{font-weight:600}.mmrb-sep{opacity:.5}.mmrb-count{opacity:.85}.mmrb-g{font-weight:600}'
			. '.has-text-align-center .mmrb,.wp-block-shortcode .mmrb{display:flex;justify-content:center}'
			. '</style>';
	}

	/* ---------------- Настройки ---------------- */

	public static function register() {
		register_setting(
			'marmar_reviews_badge',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $in ) {
					delete_transient( 'marmar_reviews_badge_detect' );
					$in = is_array( $in ) ? $in : array();
					return array(
						'enabled' => isset( $in['enabled'] ) ? 'yes' : 'no',
						'rating'  => preg_replace( '/[^0-9.,]/', '', (string) ( $in['rating'] ?? '' ) ),
						'count'   => preg_replace( '/[^0-9]/', '', (string) ( $in['count'] ?? '' ) ),
						'url'     => esc_url_raw( (string) ( $in['url'] ?? '' ) ),
						'min'     => preg_replace( '/[^0-9.]/', '', (string) ( $in['min'] ?? '4.5' ) ),
					);
				},
			)
		);
	}

	public static function menu() {
		add_options_page( 'MarMar Reviews Badge', 'MarMar Reviews Badge', 'manage_options', 'marmar-reviews-badge', array( __CLASS__, 'page' ) );
	}

	public static function page() {
		$s   = self::settings();
		$o   = self::OPTION;
		$det = self::detect();
		echo '<div class="wrap"><h1>MarMar Reviews Badge</h1>';
		echo '<p>Найдено автоматически: ' . ( $det ? '<strong>' . esc_html( number_format( $det['rating'], 1 ) . ' ★, ' . $det['count'] . ' отзывов' ) . '</strong> (' . esc_html( $det['source'] ) . ')' : '<strong>ничего</strong> — заполните вручную' ) . '</p>';
		echo '<p>Предпросмотр: ' . self::html() . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<form method="post" action="options.php">';
		settings_fields( 'marmar_reviews_badge' );
		echo '<table class="form-table">';
		echo '<tr><th>Показывать на странице товара</th><td><label><input type="checkbox" name="' . esc_attr( $o ) . '[enabled]" value="yes" ' . checked( 'yes', $s['enabled'], false ) . '> да</label></td></tr>';
		echo '<tr><th>Рейтинг вручную</th><td><input name="' . esc_attr( $o ) . '[rating]" value="' . esc_attr( $s['rating'] ) . '" class="small-text" placeholder="5.0"> и число отзывов <input name="' . esc_attr( $o ) . '[count]" value="' . esc_attr( $s['count'] ) . '" class="small-text"><p class="description">Пусто — берём из Trustindex автоматически (обновление раз в 6 часов).</p></td></tr>';
		echo '<tr><th>Ссылка на отзывы</th><td><input name="' . esc_attr( $o ) . '[url]" value="' . esc_attr( $s['url'] ) . '" class="regular-text"></td></tr>';
		echo '<tr><th>Не показывать, если рейтинг ниже</th><td><input name="' . esc_attr( $o ) . '[min]" value="' . esc_attr( $s['min'] ) . '" class="small-text"></td></tr>';
		echo '</table>';
		submit_button( 'Сохранить' );
		echo '</form><p>Шорткод для других мест (например, корзины): <code>[marmar_google_rating]</code></p></div>';
	}
}

add_action( 'plugins_loaded', array( 'MarMar_Reviews_Badge', 'init' ) );
