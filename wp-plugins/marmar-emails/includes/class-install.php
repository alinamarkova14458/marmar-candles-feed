<?php
defined( 'ABSPATH' ) || exit;

/**
 * Таблицы, миграция со старых плагинов, уборка.
 */
class MarMar_Emails_Install {

	const DB_VERSION        = '1';
	const DB_VERSION_OPTION = 'marmar_emails_db_version';

	public static function carts_table() {
		global $wpdb;
		return $wpdb->prefix . 'marmar_carts';
	}

	public static function reviews_table() {
		global $wpdb;
		return $wpdb->prefix . 'marmar_review_requests';
	}

	public static function optout_table() {
		global $wpdb;
		return $wpdb->prefix . 'marmar_optout';
	}

	public static function activate() {
		self::create_tables();
		self::migrate_legacy();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	public static function deactivate() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'marmar_emails_tick', array(), 'marmar-emails' );
		}
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::activate();
		}
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		// Время во всех таблицах — UTC (current_time( 'mysql', true )).
		dbDelta( 'CREATE TABLE ' . self::carts_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token char(32) NOT NULL,
			session_key varchar(100) NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email varchar(190) NOT NULL DEFAULT '',
			first_name varchar(100) NOT NULL DEFAULT '',
			contents longtext NOT NULL,
			cart_total decimal(12,2) NOT NULL DEFAULT 0,
			currency char(3) NOT NULL DEFAULT 'EUR',
			status varchar(20) NOT NULL DEFAULT 'open',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			sent1_at datetime NULL DEFAULT NULL,
			sent2_at datetime NULL DEFAULT NULL,
			clicked_at datetime NULL DEFAULT NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_total decimal(12,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY session_key (session_key),
			KEY email (email),
			KEY status (status)
		) $charset;" );

		dbDelta( 'CREATE TABLE ' . self::reviews_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			email varchar(190) NOT NULL,
			first_name varchar(100) NOT NULL DEFAULT '',
			completed_at datetime NOT NULL,
			send_at datetime NOT NULL,
			sent_at datetime NULL DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			PRIMARY KEY  (id),
			UNIQUE KEY order_id (order_id),
			KEY status_send (status,send_at)
		) $charset;" );

		dbDelta( 'CREATE TABLE ' . self::optout_table() . " (
			email varchar(190) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (email)
		) $charset;" );
	}

	/**
	 * Переносит историю из «Google Reviews PRO», чтобы никому не написать повторно,
	 * и подхватывает уже ожидающие отправки письма.
	 */
	public static function migrate_legacy() {
		global $wpdb;
		if ( get_option( 'marmar_emails_migrated' ) ) {
			return;
		}
		$legacy = $wpdb->prefix . 'wc_google_reviews_pro';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy ) ) === $legacy ) {
			$rows  = $wpdb->get_results( "SELECT order_id, email, completed_at, status FROM {$legacy}" ); // phpcs:ignore
			$delay = (int) MarMar_Emails_Settings::get( 'review_delay_days' );
			foreach ( $rows as $row ) {
				// В старом плагине время хранилось по Риге (UTC+2/+3) — для очереди хватит приблизительного UTC.
				$completed = gmdate( 'Y-m-d H:i:s', strtotime( $row->completed_at ) - 2 * HOUR_IN_SECONDS );
				$sent = (int) $row->status === 1;
				$wpdb->query( $wpdb->prepare( // phpcs:ignore
					'INSERT IGNORE INTO ' . self::reviews_table() . ' (order_id, email, completed_at, send_at, sent_at, status) VALUES (%d, %s, %s, %s, ' . ( $sent ? '%s' : 'NULL' ) . ', %s)',
					array_merge(
						array( $row->order_id, $row->email, $completed, gmdate( 'Y-m-d H:i:s', strtotime( $completed ) + $delay * DAY_IN_SECONDS ) ),
						$sent ? array( $completed ) : array(),
						array( $sent ? 'sent' : 'pending' )
					)
				) );
			}
		}
		update_option( 'marmar_emails_migrated', current_time( 'mysql', true ), false );
	}

	/**
	 * Минимизация данных: старые корзины удаляем через 90 дней.
	 */
	public static function cleanup() {
		global $wpdb;
		if ( get_transient( 'marmar_emails_cleanup' ) ) {
			return;
		}
		set_transient( 'marmar_emails_cleanup', 1, DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( // phpcs:ignore
			'DELETE FROM ' . self::carts_table() . " WHERE updated_at < %s AND status IN ('open','sent1','sent2','expired','empty')",
			gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS )
		) );
	}
}
