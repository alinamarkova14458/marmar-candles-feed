<?php
defined( 'ABSPATH' ) || exit;

/**
 * Отписка (GDPR): подписанная ссылка в каждом письме + экспорт/удаление данных по запросу.
 */
class MarMar_Emails_Optout {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'handle_request' ), 1 );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	private static function sign( $email ) {
		return substr( hash_hmac( 'sha256', strtolower( trim( $email ) ), wp_salt( 'auth' ) . 'marmar-optout' ), 0, 20 );
	}

	public static function url( $email ) {
		$email = strtolower( trim( $email ) );
		$token = rtrim( strtr( base64_encode( $email ), '+/', '-_' ), '=' ) . '.' . self::sign( $email );
		return add_query_arg( 'marmar_unsub', $token, home_url( '/' ) );
	}

	public static function is_opted_out( $email ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . MarMar_Emails_Install::optout_table() . ' WHERE email = %s', strtolower( trim( $email ) ) ) ); // phpcs:ignore
	}

	public static function opt_out( $email ) {
		global $wpdb;
		$email = strtolower( trim( $email ) );
		$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . MarMar_Emails_Install::optout_table() . ' (email, created_at) VALUES (%s, %s)', $email, current_time( 'mysql', true ) ) ); // phpcs:ignore
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . MarMar_Emails_Install::carts_table() . " SET status = 'skipped' WHERE email = %s AND status IN ('open','sent1')", $email ) ); // phpcs:ignore
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . MarMar_Emails_Install::reviews_table() . " SET status = 'skipped' WHERE email = %s AND status = 'pending'", $email ) ); // phpcs:ignore
	}

	public static function handle_request() {
		if ( empty( $_GET['marmar_unsub'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$raw   = sanitize_text_field( wp_unslash( $_GET['marmar_unsub'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$parts = explode( '.', $raw );
		$email = count( $parts ) === 2 ? base64_decode( strtr( $parts[0], '-_', '+/' ) ) : '';

		if ( $email && is_email( $email ) && hash_equals( self::sign( $email ), $parts[1] ) ) {
			self::opt_out( $email );
			$message = MarMar_Emails_Settings::get( 'unsubscribed_page' );
			$status  = 200;
		} else {
			$message = 'Saite nav derīga.';
			$status  = 400;
		}

		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'marmar unsubscribe' );
		wp_die(
			'<p style="font-size:16px;">' . esc_html( $message ) . '</p><p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</a></p>',
			esc_html( get_bloginfo( 'name' ) ),
			array( 'response' => $status )
		);
	}

	public static function register_exporter( $exporters ) {
		$exporters['marmar-emails'] = array(
			'exporter_friendly_name' => 'MarMar Emails',
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['marmar-emails'] = array(
			'eraser_friendly_name' => 'MarMar Emails',
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	public static function export( $email ) {
		global $wpdb;
		$data  = array();
		$carts = $wpdb->get_results( $wpdb->prepare( 'SELECT id, created_at, status, contents FROM ' . MarMar_Emails_Install::carts_table() . ' WHERE email = %s', strtolower( $email ) ) ); // phpcs:ignore
		foreach ( $carts as $cart ) {
			$data[] = array(
				'group_id'    => 'marmar-carts',
				'group_label' => 'Saglabātie grozi',
				'item_id'     => 'marmar-cart-' . $cart->id,
				'data'        => array(
					array( 'name' => 'Datums', 'value' => $cart->created_at ),
					array( 'name' => 'Statuss', 'value' => $cart->status ),
					array( 'name' => 'Saturs', 'value' => $cart->contents ),
				),
			);
		}
		return array( 'data' => $data, 'done' => true );
	}

	public static function erase( $email ) {
		global $wpdb;
		$email   = strtolower( $email );
		$removed = (int) $wpdb->delete( MarMar_Emails_Install::carts_table(), array( 'email' => $email ) );
		$removed += (int) $wpdb->delete( MarMar_Emails_Install::reviews_table(), array( 'email' => $email ) );
		return array( 'items_removed' => $removed > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
	}
}
