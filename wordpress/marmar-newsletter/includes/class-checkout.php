<?php
/**
 * Галочка согласия на оформлении заказа (блочное оформление WooCommerce).
 *
 * Галочка не отмечена заранее: согласие по GDPR должно быть активным действием.
 */

defined( 'ABSPATH' ) || exit;

class Marmar_NL_Checkout {

	const FIELD_ID = 'marmar/newsletter';
	const META_KEY = '_wc_other/marmar/newsletter';

	public static function init() {
		add_action( 'woocommerce_init', array( __CLASS__, 'register_field' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'maybe_subscribe' ) );
	}

	public static function label() {
		return 'Gribu saņemt jaunumus par sezonas kolekcijām un jaunām svecēm (atrakstīties var jebkurā brīdī)';
	}

	public static function register_field() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}
		try {
			woocommerce_register_additional_checkout_field(
				array(
					'id'       => self::FIELD_ID,
					'label'    => self::label(),
					'location' => 'contact',
					'type'     => 'checkbox',
					'required' => false,
				)
			);
		} catch ( \Throwable $e ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error( 'marmar-newsletter: ' . $e->getMessage(), array( 'source' => 'marmar-newsletter' ) );
			}
		}
	}

	/**
	 * @param WC_Order $order
	 */
	public static function maybe_subscribe( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$value = $order->get_meta( self::META_KEY );
		if ( ! wc_string_to_bool( $value ) ) {
			return;
		}
		Marmar_NL_Subscribers::add( $order->get_billing_email(), $order->get_billing_first_name(), 'checkout', $order->get_id() );
	}
}
