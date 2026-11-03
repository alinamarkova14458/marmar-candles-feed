<?php
namespace MarMarLang;

/** WooCommerce: order language and customer emails in the order's language. */
final class Woo {
	public const ORDER_META = '_mm_lang';

	/** Order being saved/processed in this request (emails are triggered from its status change). */
	private static ?\WC_Order $ctx = null;
	/** Language we switched to for the current email (null = no switch active). */
	private static ?string $switched = null;

	public static function boot(): void {
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'save_lang' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'save_lang' ) );
		add_action( 'woocommerce_before_order_object_save', array( __CLASS__, 'remember' ) );
		add_filter( 'woocommerce_allow_switching_email_locale', array( __CLASS__, 'email_locale' ), 10, 2 );
		add_filter( 'woocommerce_allow_restoring_email_locale', array( __CLASS__, 'email_restore' ), 10, 2 );
	}

	public static function save_lang( $order ): void {
		if ( $order instanceof \WC_Order && ! $order->get_meta( self::ORDER_META ) ) {
			$order->update_meta_data( self::ORDER_META, Router::current() );
			$order->save_meta_data();
		}
	}

	public static function remember( $order ): void {
		if ( $order instanceof \WC_Order ) {
			self::$ctx = $order;
		}
	}

	public static function order_lang( ?\WC_Order $order ): string {
		$l = $order ? (string) $order->get_meta( self::ORDER_META ) : '';
		return Languages::is_enabled( $l ) ? $l : Languages::DEFAULT;
	}

	/**
	 * WC_Email::setup_locale(): for customer emails of a non-default-language order we switch to that
	 * language ourselves (and stop WooCommerce from switching to the site locale).
	 * The order comes from the order save that triggered the email (setup_locale runs before
	 * $email->object is assigned, so $email->object may still hold the previous order).
	 */
	public static function email_locale( $allow, $email ) {
		if ( ! $email instanceof \WC_Email || ! $email->is_customer_email() ) {
			return $allow;
		}
		self::restore(); // never stack switches
		$order = self::$ctx ?? ( $email->object instanceof \WC_Order ? $email->object : null );
		$lang  = self::order_lang( $order );
		if ( Languages::DEFAULT === $lang || ! switch_to_locale( Languages::locale( $lang ) ) ) {
			return $allow; // default language, or language pack missing → WooCommerce's own behaviour
		}
		self::$switched = $lang;
		Router::override( $lang );
		Content::hooks( true );
		return false;
	}

	/** WC_Email::restore_locale(). */
	public static function email_restore( $allow, $email ) {
		if ( null === self::$switched ) {
			return $allow;
		}
		self::restore();
		return false;
	}

	private static function restore(): void {
		if ( null !== self::$switched ) {
			restore_previous_locale();
			Router::override( null );
			self::$switched = null;
		}
	}
}
