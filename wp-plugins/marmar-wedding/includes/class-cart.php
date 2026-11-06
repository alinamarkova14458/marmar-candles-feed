<?php
defined( 'ABSPATH' ) || exit;

/**
 * Корзина и заказ: проверка, данные конструктора, ступенчатая цена, сохранение в заказ.
 */
class MarMar_Wedding_Cart {

	const LABELS = array(
		'type'         => 'Svece',
		'ribbon'       => 'Lentes krāsa',
		'pers'         => 'Personalizācija',
		'names'        => 'Teksts',
		'text_date'    => 'Datums uz birkas/uzlīmes',
		'wedding_date' => 'Kāzu datums',
		'comment'      => 'Komentārs',
		'files'        => 'Faili',
	);

	public static function init() {
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_prices' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display' ), 10, 2 );
		add_filter( 'woocommerce_update_cart_validation', array( __CLASS__, 'validate_update' ), 10, 4 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_cart' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_meta' ), 10, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_minimum', array( __CLASS__, 'store_api_min' ), 10, 3 );
	}

	private static function field( $key ) {
		return isset( $_POST[ 'mmw_' . $key ] ) ? wp_unslash( $_POST[ 'mmw_' . $key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidationSanitization
	}

	public static function validate( $passed, $product_id, $qty ) {
		if ( ! MarMar_Wedding_Settings::is_wedding( $product_id ) ) {
			return $passed;
		}
		$min = MarMar_Wedding_Settings::min_qty();
		if ( (int) $qty < $min ) {
			wc_add_notice( sprintf( 'Minimālais kāzu svecīšu daudzums ir %d gab.', $min ), 'error' );
			return false;
		}
		if ( ! in_array( sanitize_text_field( self::field( 'type' ) ), MarMar_Wedding_Settings::types(), true ) ) {
			wc_add_notice( 'Lūdzu, izvēlieties sveci.', 'error' );
			return false;
		}
		$ribbon = sanitize_text_field( self::field( 'ribbon' ) );
		if ( '' === $ribbon || ( 'Cita' !== $ribbon && ! array_key_exists( $ribbon, MarMar_Wedding_Settings::ribbons() ) ) ) {
			wc_add_notice( 'Lūdzu, izvēlieties lentes krāsu.', 'error' );
			return false;
		}
		if ( 'Cita' === $ribbon && '' === trim( self::field( 'comment' ) ) ) {
			wc_add_notice( 'Lūdzu, komentārā aprakstiet vēlamo lentes krāsu.', 'error' );
			return false;
		}
		$date = sanitize_text_field( self::field( 'wedding_date' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wc_add_notice( 'Lūdzu, norādiet kāzu datumu.', 'error' );
			return false;
		}
		return $passed;
	}

	public static function cart_item_data( $data, $product_id ) {
		if ( ! MarMar_Wedding_Settings::is_wedding( $product_id ) ) {
			return $data;
		}
		$pers  = sanitize_key( self::field( 'pers' ) );
		$pers  = array_key_exists( $pers, MarMar_Wedding_Settings::personalisations() ) ? $pers : 'none';
		$files = array();
		if ( 'sticker' === $pers ) {
			foreach ( array_slice( array_filter( explode( ',', sanitize_text_field( self::field( 'files' ) ) ) ), 0, MarMar_Wedding_Uploads::MAX_FILES ) as $token ) {
				$f = MarMar_Wedding_Uploads::get( $token );
				if ( $f ) {
					$files[] = array( 'token' => $f['token'], 'name' => $f['name'] );
				}
			}
		}
		$data['marmar_wedding'] = array(
			'type'         => sanitize_text_field( self::field( 'type' ) ),
			'ribbon'       => sanitize_text_field( self::field( 'ribbon' ) ),
			'pers'         => $pers,
			'names'        => 'none' === $pers ? '' : sanitize_text_field( self::field( 'names' ) ),
			'text_date'    => 'none' === $pers ? '' : sanitize_text_field( self::field( 'text_date' ) ),
			'wedding_date' => sanitize_text_field( self::field( 'wedding_date' ) ),
			'comment'      => sanitize_textarea_field( self::field( 'comment' ) ),
			'files'        => $files,
		);
		$data['unique_key'] = md5( wp_json_encode( $data['marmar_wedding'] ) . microtime() ); // каждая конфигурация — отдельная строка корзины
		return $data;
	}

	public static function apply_prices( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item['marmar_wedding'] ) && isset( $item['data'] ) ) {
				// Ступенчатая цена — обычная цена, а не «скидка»: иначе блочная корзина зачёркивает 5 € и пишет «Save …».
				$unit = MarMar_Wedding_Settings::unit_price( (int) $item['quantity'] );
				$item['data']->set_regular_price( $unit );
				$item['data']->set_sale_price( '' );
				$item['data']->set_price( $unit );
			}
		}
	}

	private static function pretty( $w ) {
		$out = array(
			'type'   => $w['type'],
			'ribbon' => $w['ribbon'],
			'pers'   => MarMar_Wedding_Settings::personalisations()[ $w['pers'] ] ?? $w['pers'],
		);
		foreach ( array( 'names', 'text_date' ) as $k ) {
			if ( '' !== $w[ $k ] ) {
				$out[ $k ] = $w[ $k ];
			}
		}
		if ( $w['wedding_date'] ) {
			$out['wedding_date'] = wp_date( 'd.m.Y', strtotime( $w['wedding_date'] . ' 12:00:00' ) );
		}
		if ( ! empty( $w['files'] ) ) {
			$out['files'] = implode( ', ', wp_list_pluck( $w['files'], 'name' ) );
		}
		if ( '' !== $w['comment'] ) {
			$out['comment'] = $w['comment'];
		}
		return $out;
	}

	public static function display( $data, $item ) {
		if ( empty( $item['marmar_wedding'] ) ) {
			return $data;
		}
		foreach ( self::pretty( $item['marmar_wedding'] ) as $k => $v ) {
			$data[] = array( 'key' => self::LABELS[ $k ], 'value' => wp_trim_words( $v, 25 ) );
		}
		return $data;
	}

	public static function validate_update( $passed, $key, $values, $qty ) {
		if ( ! empty( $values['marmar_wedding'] ) && (int) $qty < MarMar_Wedding_Settings::min_qty() && (int) $qty > 0 ) {
			wc_add_notice( sprintf( 'Minimālais kāzu svecīšu daudzums ir %d gab.', MarMar_Wedding_Settings::min_qty() ), 'error' );
			return false;
		}
		return $passed;
	}

	/** Для блочной корзины: минимальное количество на уровне Store API. */
	public static function store_api_min( $min, $product, $cart_item ) {
		return ( is_array( $cart_item ) && ! empty( $cart_item['marmar_wedding'] ) ) ? MarMar_Wedding_Settings::min_qty() : $min;
	}

	public static function check_cart() {
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item['marmar_wedding'] ) && (int) $item['quantity'] < MarMar_Wedding_Settings::min_qty() ) {
				wc_add_notice( sprintf( 'Minimālais kāzu svecīšu daudzums ir %d gab.', MarMar_Wedding_Settings::min_qty() ), 'error' );
			}
		}
	}

	public static function order_item_meta( $item, $key, $values ) {
		if ( empty( $values['marmar_wedding'] ) ) {
			return;
		}
		$w = $values['marmar_wedding'];
		foreach ( self::pretty( $w ) as $k => $v ) {
			if ( 'files' === $k ) {
				continue; // файлы в заказе показываются ссылками (MarMar_Wedding_Admin::order_files)
			}
			$item->add_meta_data( self::LABELS[ $k ], $v );
		}
		$item->add_meta_data( '_marmar_wedding', $w );
		foreach ( $w['files'] as $f ) {
			MarMar_Wedding_Uploads::mark_used( $f['token'] );
		}
	}
}
