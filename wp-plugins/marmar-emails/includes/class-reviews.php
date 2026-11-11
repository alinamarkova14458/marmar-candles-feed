<?php
defined( 'ABSPATH' ) || exit;

/**
 * Просьба оставить отзыв в Google через N дней после статуса «Выполнен» (после каждого заказа).
 */
class MarMar_Emails_Reviews {

	public static function init() {
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'schedule' ), 20, 2 );
	}

	private static function enabled() {
		return 'yes' === MarMar_Emails_Settings::get( 'reviews_enabled' );
	}

	public static function schedule( $order_id, $order = null ) {
		global $wpdb;
		if ( ! self::enabled() ) {
			return;
		}
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order || ! $order->get_billing_email() ) {
			return;
		}
		$now   = time();
		$delay = max( 0, (int) MarMar_Emails_Settings::get( 'review_delay_days' ) );
		$wpdb->query( $wpdb->prepare( // phpcs:ignore
			'INSERT IGNORE INTO ' . MarMar_Emails_Install::reviews_table() . ' (order_id, email, first_name, completed_at, send_at, status) VALUES (%d, %s, %s, %s, %s, %s)',
			$order->get_id(),
			strtolower( $order->get_billing_email() ),
			$order->get_billing_first_name(),
			gmdate( 'Y-m-d H:i:s', $now ),
			gmdate( 'Y-m-d H:i:s', $now + $delay * DAY_IN_SECONDS ),
			'pending'
		) );
	}

	public static function legacy_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'wc-google-reviews-pro/wc-google-reviews-pro.php' );
	}

	public static function process_queue() {
		global $wpdb;
		if ( ! self::enabled() || self::legacy_active() ) {
			return;
		}
		$table = MarMar_Emails_Install::reviews_table();
		$now   = gmdate( 'Y-m-d H:i:s' );

		// Слишком старые (например, после долгого простоя) не отправляем.
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'expired' WHERE status = 'pending' AND send_at < %s", gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) ); // phpcs:ignore

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'pending' AND send_at <= %s ORDER BY send_at ASC LIMIT 20", $now ) ); // phpcs:ignore
		foreach ( $rows as $row ) {
			self::send_row( $row );
		}
	}

	private static function send_row( $row ) {
		global $wpdb;
		$table = MarMar_Emails_Install::reviews_table();
		$order = wc_get_order( $row->order_id );

		if ( ! $order || 'completed' !== $order->get_status() ) {
			$wpdb->update( $table, array( 'status' => 'cancelled' ), array( 'id' => $row->id ) );
			return;
		}
		if ( MarMar_Emails_Optout::is_opted_out( $row->email ) || MarMar_Emails_Settings::is_excluded( $row->email ) ) {
			$wpdb->update( $table, array( 'status' => 'skipped' ), array( 'id' => $row->id ) );
			return;
		}

		$sent = self::send_to( $row->email, $row->first_name ? $row->first_name : $order->get_billing_first_name(), $order->get_order_number() );

		$wpdb->update(
			$table,
			array(
				'status'  => $sent ? 'sent' : 'failed',
				'sent_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $row->id )
		);
	}

	public static function send_to( $email, $first_name, $order_number = '' ) {
		return MarMar_Emails_Mailer::send(
			$email,
			MarMar_Emails_Settings::get( 'review_subject' ),
			MarMar_Emails_Settings::get( 'review_heading' ),
			MarMar_Emails_Settings::get( 'review_body' ),
			array(
				'first_name'   => $first_name,
				'review_url'   => MarMar_Emails_Settings::get( 'review_url' ),
				'order_number' => $order_number,
			)
		);
	}
}
