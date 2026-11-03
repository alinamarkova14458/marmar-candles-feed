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
	 * Called by WC_Email::setup_locale() and again by restore_locale().
	 * For customer emails of a non-default-language order we switch to that language ourselves
	 * (and stop WooCommerce from switching to the site locale), then restore on the second call.
	 */
	public static function email_locale( $allow, $email ) {
		if ( ! $email instanceof \WC_Email || ! $email->is_customer_email() ) {
			return $allow;
		}
		if ( null !== self::$switched ) { // restore_locale()
			restore_previous_locale();
			Router::override( null );
			self::$switched = null;
			return false;
		}
		$order = $email->object instanceof \WC_Order ? $email->object : self::$ctx;
		$lang  = self::order_lang( $order );
		if ( Languages::DEFAULT === $lang ) {
			return $allow;
		}
		self::$switched = $lang;
		Router::override( $lang );
		switch_to_locale( Languages::locale( $lang ) );
		Content::hooks( true );
		return false;
	}
}
