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
				'description' => 'Показать на странице товара конструктор персонализированных свечей (цены по количеству — в «Настройки → MarMar Wedding»).',
			)
		);
		woocommerce_wp_select(
			array(
				'id'          => MarMar_Wedding_Settings::PROFILE_META,
				'label'       => 'Повод конструктора',
				'options'     => MarMar_Wedding_Settings::profiles(),
				'description' => 'Подписи полей, ленты и сроки для свадьбы, крестин или корпоративных подарков.',
			)
		);
	}

	public static function save_product( $product ) {
		$product->update_meta_data( MarMar_Wedding_Settings::META, isset( $_POST[ MarMar_Wedding_Settings::META ] ) ? 'yes' : 'no' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_POST[ MarMar_Wedding_Settings::PROFILE_META ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$p = sanitize_key( wp_unslash( $_POST[ MarMar_Wedding_Settings::PROFILE_META ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$product->update_meta_data( MarMar_Wedding_Settings::PROFILE_META, array_key_exists( $p, MarMar_Wedding_Settings::profiles() ) ? $p : 'kazas' );
		}
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
						'pers_images' => sanitize_textarea_field( $in['pers_images'] ?? '' ),
						'ribbons' => sanitize_textarea_field( $in['ribbons'] ?? '' ),
						'intro'     => sanitize_textarea_field( $in['intro'] ?? '' ),
						'lead_time' => sanitize_textarea_field( $in['lead_time'] ?? '' ),
						'process'   => sanitize_textarea_field( $in['process'] ?? '' ),
						'default_qty' => max( 1, (int) ( $in['default_qty'] ?? 30 ) ),
						'type_notes'  => sanitize_textarea_field( $in['type_notes'] ?? '' ),
						'facts'       => sanitize_textarea_field( $in['facts'] ?? '' ),
						'ship_note'   => sanitize_textarea_field( $in['ship_note'] ?? '' ),
						'terms'       => sanitize_textarea_field( $in['terms'] ?? '' ),
						'reviews'     => sanitize_textarea_field( $in['reviews'] ?? '' ),
						'cross_ids'   => implode( ',', array_filter( array_map( 'absint', explode( ',', (string) ( $in['cross_ids'] ?? '' ) ) ) ) ),
						'whatsapp'    => preg_replace( '/\D+/', '', (string) ( $in['whatsapp'] ?? '' ) ),
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
		echo '<tr><th>Количество при открытии страницы</th><td><input type="number" min="1" name="' . esc_attr( $o ) . '[default_qty]" value="' . esc_attr( $s['default_qty'] ) . '" class="small-text"></td></tr>';
		echo '<tr><th>WhatsApp для вопросов</th><td><input type="text" name="' . esc_attr( $o ) . '[whatsapp]" value="' . esc_attr( $s['whatsapp'] ) . '" class="regular-text"><p class="description">Только цифры, с кодом страны. Пусто — строка не показывается.</p></td></tr>';
		echo '<tr><th>Товары «к свадебному торту»</th><td><input type="text" name="' . esc_attr( $o ) . '[cross_ids]" value="' . esc_attr( $s['cross_ids'] ) . '" class="regular-text"><p class="description">ID товаров через запятую — показываются под конструктором. Пусто — блок скрыт.</p></td></tr>';
		$areas = array(
			'tiers'   => array( 'Цены по количеству', 'Формат: «от N шт.: цена за штуку», по одному в строке. Например: 50: 3.5' ),
			'types'   => array( 'Виды свечей', 'По одному в строке: «Название | ссылка на картинку» (картинка необязательна).' ),
			'pers_images' => array( 'Примеры персонализации', 'Строки «tag | ссылка» и «sticker | ссылка» — маленькое фото рядом с вариантом.' ),
			'ribbons' => array( 'Цвета лент', 'Формат: «Название: #цвет», по одному в строке. Вариант «Cita» добавляется автоматически.' ),
			'intro'     => array( 'Текст над конструктором', 'Можно оставить пустым.' ),
			'lead_time' => array( 'Подсказка о сроках', 'Показывается под полем «Vēlamais saņemšanas datums».' ),
			'process'   => array( 'Шаги «Kā notiek pasūtījums»', 'По одному шагу в строке.' ),
			'type_notes' => array( 'Подписи под видами свечей', 'Строки «Название | подпись» (размер, время горения).' ),
			'facts'      => array( 'Строка о свечах', 'Под карточками свечей: воск, аромат.' ),
			'ship_note'  => array( 'Строка о доставке', 'Под таблицей цен.' ),
			'terms'      => array( 'Сроки под кнопкой', 'По одной строке. Заменяют общий блок «Saņemsiet…» и срочный заказ.' ),
			'reviews'    => array( 'Отзывы', 'По одному отзыву в строке, без имён.' ),
		);
		foreach ( $areas as $k => $a ) {
			echo '<tr><th>' . esc_html( $a[0] ) . '</th><td><textarea name="' . esc_attr( $o ) . '[' . esc_attr( $k ) . ']" rows="' . ( in_array( $k, array( 'intro', 'lead_time', 'facts', 'ship_note' ), true ) ? 3 : 7 ) . '" class="large-text">' . esc_textarea( $s[ $k ] ) . '</textarea>' . ( $a[1] ? '<p class="description">' . esc_html( $a[1] ) . '</p>' : '' ) . '</td></tr>';
		}
		echo '</table>';
		submit_button( 'Сохранить' );
		echo '</form></div>';
	}
}
