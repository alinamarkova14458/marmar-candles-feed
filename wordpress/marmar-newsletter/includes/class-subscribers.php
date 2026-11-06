<?php
/**
 * База подписчиков: своя таблица, согласие, отписка, импорт клиентов.
 */

defined( 'ABSPATH' ) || exit;

class Marmar_NL_Subscribers {

	const DB_VERSION = '1';

	/** Источники с явным согласием (галочка, вручную). */
	const CONSENT_SOURCES = array( 'checkout', 'manual' );

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'marmar_nl_subscribers';
	}

	public static function sends_table() {
		global $wpdb;
		return $wpdb->prefix . 'marmar_nl_sends';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$subs    = self::table();
		$sends   = self::sends_table();

		dbDelta(
			"CREATE TABLE {$subs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				email varchar(190) NOT NULL,
				first_name varchar(100) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'subscribed',
				source varchar(20) NOT NULL DEFAULT 'manual',
				token varchar(64) NOT NULL,
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				unsubscribed_at datetime NULL DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY email (email),
				UNIQUE KEY token (token),
				KEY status (status)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$sends} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				campaign_id bigint(20) unsigned NOT NULL,
				subscriber_id bigint(20) unsigned NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'queued',
				error varchar(255) NOT NULL DEFAULT '',
				sent_at datetime NULL DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY campaign_subscriber (campaign_id,subscriber_id),
				KEY campaign_status (campaign_id,status)
			) {$charset};"
		);

		update_option( 'marmar_nl_db_version', self::DB_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'marmar_nl_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function normalize_email( $email ) {
		$email = strtolower( trim( (string) $email ) );
		return is_email( $email ) ? $email : '';
	}

	public static function get_by_email( $email ) {
		global $wpdb;
		$email = self::normalize_email( $email );
		if ( ! $email ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE email = %s', $email ) );
	}

	public static function get_by_token( $token ) {
		global $wpdb;
		$token = preg_replace( '/[^a-zA-Z0-9]/', '', (string) $token );
		if ( strlen( $token ) < 20 ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE token = %s', $token ) );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
	}

	/**
	 * Добавить или обновить подписчика.
	 *
	 * Уже отписавшийся человек снова подписывается только при явном согласии
	 * (галочка / вручную), импорт клиентов его никогда не возвращает.
	 *
	 * @return string created|resubscribed|exists|skipped|invalid
	 */
	public static function add( $email, $first_name = '', $source = 'manual', $order_id = 0 ) {
		global $wpdb;
		$email = self::normalize_email( $email );
		if ( ! $email ) {
			return 'invalid';
		}
		$now      = current_time( 'mysql', true );
		$existing = self::get_by_email( $email );

		if ( $existing ) {
			if ( 'subscribed' === $existing->status ) {
				// Явное согласие «повышает» импортированного клиента.
				if ( in_array( $source, self::CONSENT_SOURCES, true ) && ! in_array( $existing->source, self::CONSENT_SOURCES, true ) ) {
					$wpdb->update( self::table(), array( 'source' => $source, 'updated_at' => $now ), array( 'id' => $existing->id ) );
				}
				return 'exists';
			}
			if ( ! in_array( $source, self::CONSENT_SOURCES, true ) ) {
				return 'skipped';
			}
			$wpdb->update(
				self::table(),
				array(
					'status'          => 'subscribed',
					'source'          => $source,
					'updated_at'      => $now,
					'unsubscribed_at' => null,
				),
				array( 'id' => $existing->id )
			);
			return 'resubscribed';
		}

		$wpdb->insert(
			self::table(),
			array(
				'email'      => $email,
				'first_name' => sanitize_text_field( $first_name ),
				'status'     => 'subscribed',
				'source'     => sanitize_key( $source ),
				'token'      => wp_generate_password( 32, false, false ),
				'order_id'   => (int) $order_id,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		return 'created';
	}

	public static function unsubscribe( $id ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		return (bool) $wpdb->update(
			self::table(),
			array(
				'status'          => 'unsubscribed',
				'updated_at'      => $now,
				'unsubscribed_at' => $now,
			),
			array( 'id' => (int) $id, 'status' => 'subscribed' )
		);
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::sends_table(), array( 'subscriber_id' => (int) $id ) );
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	/**
	 * SQL-условие для аудитории кампании.
	 *
	 * consent — только с явным согласием; all — плюс импортированные клиенты.
	 */
	public static function audience_where( $audience ) {
		$where = "status = 'subscribed'";
		if ( 'all' !== $audience ) {
			$where .= " AND source IN ('" . implode( "','", array_map( 'esc_sql', self::CONSENT_SOURCES ) ) . "')";
		}
		return $where;
	}

	public static function count_audience( $audience ) {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . self::audience_where( $audience ) );
	}

	public static function counts() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT status, source, COUNT(*) AS n FROM ' . self::table() . ' GROUP BY status, source' );
		$out  = array(
			'subscribed'   => 0,
			'consent'      => 0,
			'customers'    => 0,
			'unsubscribed' => 0,
		);
		foreach ( $rows as $r ) {
			if ( 'subscribed' === $r->status ) {
				$out['subscribed'] += (int) $r->n;
				if ( in_array( $r->source, self::CONSENT_SOURCES, true ) ) {
					$out['consent'] += (int) $r->n;
				} else {
					$out['customers'] += (int) $r->n;
				}
			} else {
				$out['unsubscribed'] += (int) $r->n;
			}
		}
		return $out;
	}

	public static function query( $args = array() ) {
		global $wpdb;
		$args   = wp_parse_args( $args, array( 'status' => '', 'search' => '', 'limit' => 50, 'offset' => 0 ) );
		$where  = array( '1=1' );
		$params = array();
		if ( in_array( $args['status'], array( 'subscribed', 'unsubscribed' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( '' !== $args['search'] ) {
			$where[]  = 'email LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}
		$sql      = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$params[] = (int) $args['limit'];
		$params[] = (int) $args['offset'];
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Импорт покупателей из заказов WooCommerce как source=customer.
	 *
	 * Отправка им возможна только кампаниям с аудиторией «all».
	 *
	 * @return array{created:int,exists:int,skipped:int}
	 */
	public static function import_customers() {
		$result = array( 'created' => 0, 'exists' => 0, 'skipped' => 0 );
		$page   = 1;
		do {
			$orders = wc_get_orders(
				array(
					'limit'  => 100,
					'page'   => $page,
					'status' => array( 'wc-processing', 'wc-completed' ),
					'type'   => 'shop_order',
				)
			);
			foreach ( $orders as $order ) {
				$r = self::add( $order->get_billing_email(), $order->get_billing_first_name(), 'customer', $order->get_id() );
				if ( 'created' === $r ) {
					++$result['created'];
				} elseif ( 'exists' === $r ) {
					++$result['exists'];
				} else {
					++$result['skipped'];
				}
			}
			++$page;
		} while ( count( $orders ) === 100 );
		return $result;
	}
}
