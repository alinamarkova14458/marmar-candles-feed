<?php
defined( 'ABSPATH' ) || exit;

/**
 * Хранение разговоров. История на сервере — клиент присылает только новое сообщение.
 */
class MarMar_Chat_Store {

	const DB_VERSION = '1';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'marmar_chat';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( 'CREATE TABLE ' . self::table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			cid char(32) NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			page_url varchar(255) NOT NULL DEFAULT '',
			ip_hash char(16) NOT NULL DEFAULT '',
			turns int(10) unsigned NOT NULL DEFAULT 0,
			messages longtext NOT NULL,
			input_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			output_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			cost decimal(10,5) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY cid (cid),
			KEY updated_at (updated_at)
		) {$wpdb->get_charset_collate()};" );
		update_option( 'marmar_chat_db_version', self::DB_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'marmar_chat_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function get( $cid ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE cid = %s', $cid ) ); // phpcs:ignore
		if ( $row ) {
			$row->messages = json_decode( $row->messages, true ) ?: array();
		}
		return $row;
	}

	public static function create( $page_url, $ip_hash ) {
		global $wpdb;
		$cid = wp_generate_password( 32, false, false );
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			self::table(),
			array(
				'cid'        => $cid,
				'created_at' => $now,
				'updated_at' => $now,
				'page_url'   => substr( esc_url_raw( $page_url ), 0, 255 ),
				'ip_hash'    => $ip_hash,
				'messages'   => '[]',
			)
		);
		return self::get( $cid );
	}

	public static function save( $row, array $messages, array $usage ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( // phpcs:ignore
			'UPDATE ' . self::table() . ' SET messages = %s, turns = turns + 1, updated_at = %s, input_tokens = input_tokens + %d, output_tokens = output_tokens + %d, cost = cost + %f WHERE id = %d',
			wp_json_encode( $messages, JSON_UNESCAPED_UNICODE ),
			current_time( 'mysql', true ),
			$usage['input'],
			$usage['output'],
			$usage['cost'],
			$row->id
		) );
	}

	/** Хранение 90 дней. */
	public static function cleanup() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE updated_at < %s', gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) ); // phpcs:ignore
	}
}
