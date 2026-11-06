<?php
defined( 'ABSPATH' ) || exit;

class MarMar_Wedding_Admin {

	public static function init() {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_checkbox' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product' ) );
		add_action( 'woocommerce_after_order_itemmeta', array( __CLASS__, 'order_files' ), 10, 3 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function product_checkbox() {
		woocommerce_wp_checkbox(
			array(
				'id'          => MarMar_Wedding_Settings::META,
				'label'       => 'Kāzu konstruktors',
				'description' => 'Показать на странице товара свадебный конструктор (цены по количеству — в «Настройки → MarMar Wedding»).',
			)
		);
	}

	public static function save_product( $product ) {
		$product->update_meta_data( MarMar_Wedding_Settings::META, isset( $_POST[ MarMar_Wedding_Settings::META ] ) ? 'yes' : 'no' ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/** Ссылки на файлы пары под строкой заказа (только в админке). */
	public static function order_files( $item_id, $item, $product ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return;
		}
		$w = $item->get_meta( '_marmar_wedding' );
		if ( empty( $w['files'] ) ) {
			return;
		}
		echo '<div class="mmw-admin-files" style="margin-top:6px"><strong>Faili:</strong> ';
		$links = array();
		foreach ( $w['files'] as $f ) {
			$links[] = '<a href="' . esc_url( MarMar_Wedding_Uploads::admin_url( $f['token'] ) ) . '" target="_blank" rel="noopener">' . esc_html( $f['name'] ) . '</a>';
		}
		echo implode( ', ', $links ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function menu() {
		add_options_page( 'MarMar Wedding', 'MarMar Wedding', 'manage_woocommerce', 'marmar-wedding', array( __CLASS__, 'page' ) );
	}

	public static function register() {
		register_setting(
			'marmar_wedding',
			MarMar_Wedding_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $in ) {
					$in = is_array( $in ) ? $in : array();
					return array(
						'min_qty' => max( 1, (int) ( $in['min_qty'] ?? 10 ) ),
						'tiers'   => sanitize_textarea_field( $in['tiers'] ?? '' ),
						'types'   => sanitize_textarea_field( $in['types'] ?? '' ),
						'ribbons' => sanitize_textarea_field( $in['ribbons'] ?? '' ),
						'intro'     => sanitize_textarea_field( $in['intro'] ?? '' ),
						'lead_time' => sanitize_textarea_field( $in['lead_time'] ?? '' ),
						'process'   => sanitize_textarea_field( $in['process'] ?? '' ),
					);
				},
			)
		);
	}

	public static function page() {
		$s = MarMar_Wedding_Settings::all();
		$o = MarMar_Wedding_Settings::OPTION;
		echo '<div class="wrap"><h1>MarMar Wedding</h1><p>Конструктор включается галочкой «Kāzu konstruktors» в карточке товара (вкладка «Общие»).</p><form method="post" action="options.php">';
		settings_fields( 'marmar_wedding' );
		echo '<table class="form-table">';
		echo '<tr><th>Минимальное количество</th><td><input type="number" min="1" name="' . esc_attr( $o ) . '[min_qty]" value="' . esc_attr( $s['min_qty'] ) . '" class="small-text"></td></tr>';
		$areas = array(
			'tiers'   => array( 'Цены по количеству', 'Формат: «от N шт.: цена за штуку», по одному в строке. Например: 50: 3.5' ),
			'types'   => array( 'Виды свечей', 'По одному в строке.' ),
			'ribbons' => array( 'Цвета лент', 'Формат: «Название: #цвет», по одному в строке. Вариант «Cita» добавляется автоматически.' ),
			'intro'     => array( 'Текст над конструктором', 'Можно оставить пустым.' ),
			'lead_time' => array( 'Подсказка о сроках', 'Показывается под полем «Vēlamais saņemšanas datums».' ),
			'process'   => array( 'Шаги «Kā notiek pasūtījums»', 'По одному шагу в строке.' ),
		);
		foreach ( $areas as $k => $a ) {
			echo '<tr><th>' . esc_html( $a[0] ) . '</th><td><textarea name="' . esc_attr( $o ) . '[' . esc_attr( $k ) . ']" rows="' . ( in_array( $k, array( 'intro', 'lead_time' ), true ) ? 3 : 7 ) . '" class="large-text">' . esc_textarea( $s[ $k ] ) . '</textarea>' . ( $a[1] ? '<p class="description">' . esc_html( $a[1] ) . '</p>' : '' ) . '</td></tr>';
		}
		echo '</table>';
		submit_button( 'Сохранить' );
		echo '</form></div>';
	}
}
