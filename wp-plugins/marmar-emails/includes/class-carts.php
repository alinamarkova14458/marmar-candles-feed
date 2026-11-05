<?php
defined( 'ABSPATH' ) || exit;

/**
 * Брошенные корзины.
 *
 * Статусы строки: open → sent1 → sent2; финальные: recovered (купил после письма),
 * ordered (купил без письма), expired (устарела), empty (корзину очистили), skipped (отписка/исключение).
 */
class MarMar_Emails_Carts {

	const ACTIVE = array( 'open', 'sent1', 'sent2' );

	public static function init() {
		add_action( 'woocommerce_cart_updated', array( __CLASS__, 'on_cart_updated' ), 20 );

		// Email: блочный checkout (Store API), классический checkout, наш JS (на случай, если клиент ушёл сразу после ввода).
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', array( __CLASS__, 'on_store_api_customer' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_order_review', array( __CLASS__, 'on_classic_update_review' ) );
		add_action( 'wc_ajax_marmar_capture_email', array( __CLASS__, 'ajax_capture_email' ) );
		add_action( 'wp_footer', array( __CLASS__, 'checkout_script' ) );

		// Связь заказа с корзиной.
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'attach_token_to_order' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'attach_token_to_order' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_order_status_changed' ), 10, 4 );

		// Ссылка восстановления корзины из письма.
		add_action( 'wp_loaded', array( __CLASS__, 'handle_restore_link' ), 30 );
	}

	/* ------------------------------------------------------------------ */
	/* Захват                                                              */
	/* ------------------------------------------------------------------ */

	private static function enabled() {
		return 'yes' === MarMar_Emails_Settings::get( 'carts_enabled' );
	}

	private static function session_key() {
		if ( ! WC()->session ) {
			return '';
		}
		return (string) WC()->session->get_customer_id();
	}

	public static function on_cart_updated() {
		if ( ! self::enabled() || is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		self::capture();
	}

	public static function on_store_api_customer( $customer, $request ) {
		if ( ! self::enabled() ) {
			return;
		}
		$email = $customer ? $customer->get_billing_email() : '';
		$name  = $customer ? $customer->get_billing_first_name() : '';
		if ( $email && is_email( $email ) ) {
			self::remember_email( $email, $name );
			self::capture();
		}
	}

	public static function on_classic_update_review( $post_data ) {
		if ( ! self::enabled() ) {
			return;
		}
		parse_str( (string) $post_data, $data );
		if ( ! empty( $data['billing_email'] ) && is_email( $data['billing_email'] ) ) {
			self::remember_email( $data['billing_email'], isset( $data['billing_first_name'] ) ? $data['billing_first_name'] : '' );
			self::capture();
		}
	}

	public static function ajax_capture_email() {
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$name  = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! self::enabled() || ! $email || ! is_email( $email ) ) {
			wp_send_json_error();
		}
		self::remember_email( $email, $name );
		self::capture();
		wp_send_json_success();
	}

	private static function remember_email( $email, $first_name = '' ) {
		if ( ! WC()->session ) {
			return;
		}
		WC()->session->set( 'marmar_email', strtolower( trim( $email ) ) );
		if ( $first_name ) {
			WC()->session->set( 'marmar_first_name', sanitize_text_field( $first_name ) );
		}
	}

	private static function current_email() {
		$email = WC()->session ? (string) WC()->session->get( 'marmar_email' ) : '';
		if ( ! $email && WC()->customer ) {
			$email = (string) WC()->customer->get_billing_email();
		}
		if ( ! $email && is_user_logged_in() ) {
			$email = (string) wp_get_current_user()->user_email;
		}
		return strtolower( trim( $email ) );
	}

	private static function current_first_name() {
		$name = WC()->session ? (string) WC()->session->get( 'marmar_first_name' ) : '';
		if ( ! $name && WC()->customer ) {
			$name = (string) WC()->customer->get_billing_first_name();
		}
		return $name;
	}

	/**
	 * Сохраняет/обновляет снимок корзины текущей сессии. Пишем только если email известен.
	 */
	public static function capture() {
		global $wpdb;
		if ( ! WC()->cart || ! WC()->session ) {
			return;
		}
		$key = self::session_key();
		if ( '' === $key ) {
			return;
		}
		$table = MarMar_Emails_Install::carts_table();
		$now   = current_time( 'mysql', true );
		$row   = self::find_active_by_session( $key );

		if ( WC()->cart->is_empty() ) {
			if ( $row ) {
				$wpdb->update( $table, array( 'status' => 'empty', 'updated_at' => $now ), array( 'id' => $row->id ) );
			}
			return;
		}

		$email = self::current_email();
		if ( ! $email || ! is_email( $email ) ) {
			return;
		}
		if ( MarMar_Emails_Settings::is_excluded( $email ) ) {
			return;
		}

		$contents = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$contents[] = array(
				'product_id'   => (int) $item['product_id'],
				'variation_id' => (int) $item['variation_id'],
				'variation'    => isset( $item['variation'] ) ? (array) $item['variation'] : array(),
				'quantity'     => (int) $item['quantity'],
			);
		}
		$data = array(
			'email'      => $email,
			'first_name' => self::current_first_name(),
			'user_id'    => get_current_user_id(),
			'contents'   => wp_json_encode( $contents ),
			'cart_total' => (float) WC()->cart->get_total( 'edit' ),
			'currency'   => get_woocommerce_currency(),
			'updated_at' => $now,
		);

		if ( $row ) {
			// Если содержимое не поменялось — не сдвигаем таймер (иначе письма никогда не уйдут при каждом заходе на сайт).
			if ( $row->contents === $data['contents'] && $row->email === $email ) {
				if ( $data['first_name'] && $row->first_name !== $data['first_name'] ) {
					$wpdb->update( $table, array( 'first_name' => $data['first_name'] ), array( 'id' => $row->id ) );
				}
				return;
			}
			$wpdb->update( $table, $data, array( 'id' => $row->id ) );
		} else {
			$data['token']       = wp_generate_password( 32, false, false );
			$data['session_key'] = $key;
			$data['status']      = 'open';
			$data['created_at']  = $now;
			$wpdb->insert( $table, $data );
		}
	}

	private static function find_active_by_session( $key ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MarMar_Emails_Install::carts_table() . " WHERE session_key = %s AND status IN ('open','sent1','sent2') ORDER BY id DESC LIMIT 1", $key ) ); // phpcs:ignore
	}

	/* ------------------------------------------------------------------ */
	/* JS на странице оформления                                           */
	/* ------------------------------------------------------------------ */

	public static function checkout_script() {
		if ( ! self::enabled() || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		$endpoint = WC_AJAX::get_endpoint( 'marmar_capture_email' );
		$notice   = (string) MarMar_Emails_Settings::get( 'cart_notice' );
		?>
		<script id="marmar-emails-capture">
		(function () {
			var endpoint = <?php echo wp_json_encode( $endpoint ); ?>;
			var notice = <?php echo wp_json_encode( $notice ); ?>;
			var last = '', timer = null;
			var re = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
			function firstName() {
				var f = document.querySelector('#billing-first_name, #billing_first_name, #shipping-first_name, #shipping_first_name');
				return f ? f.value : '';
			}
			var lastName = '';
			function send(email) {
				var name = firstName();
				if (!re.test(email) || (email === last && name === lastName)) return;
				last = email; lastName = name;
				var body = new URLSearchParams({ email: email, first_name: firstName() });
				fetch(endpoint, { method: 'POST', credentials: 'same-origin', body: body }).catch(function () {});
			}
			function onInput(e) {
				var t = e.target;
				if (!t || t.tagName !== 'INPUT') return;
				var isName = /first_name$/.test(t.id || '');
				if (t.type !== 'email' && t.id !== 'billing_email' && !isName) return;
				if (isName && e.type === 'input') return;
				clearTimeout(timer);
				var emailInput = document.querySelector('#email, #billing_email');
				var v = ((isName ? (emailInput && emailInput.value) : t.value) || '').trim();
				timer = setTimeout(function () { send(v); }, e.type === 'input' ? 1200 : 0);
			}
			document.addEventListener('input', onInput, true);
			document.addEventListener('change', onInput, true);
			document.addEventListener('blur', onInput, true);
			function addNotice() {
				if (!notice) return;
				var input = document.querySelector('#email, #billing_email');
				if (!input || document.getElementById('marmar-cart-notice')) return;
				var wrap = input.closest('.wc-block-components-text-input, .form-row') || input.parentNode;
				var p = document.createElement('p');
				p.id = 'marmar-cart-notice';
				p.style.cssText = 'font-size:12px;opacity:.75;margin:6px 0 0;';
				p.textContent = notice;
				wrap.parentNode.insertBefore(p, wrap.nextSibling);
			}
			new MutationObserver(addNotice).observe(document.body, { childList: true, subtree: true });
			addNotice();
		})();
		</script>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Заказы                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * @param WC_Order $order
	 */
	public static function attach_token_to_order( $order ) {
		if ( ! WC()->session || ! $order instanceof WC_Order ) {
			return;
		}
		$row   = self::find_active_by_session( self::session_key() );
		$token = $row ? $row->token : (string) WC()->session->get( 'marmar_restored_token' );
		if ( $token ) {
			$order->update_meta_data( '_marmar_cart_token', $token );
		}
	}

	public static function on_order_status_changed( $order_id, $from, $to, $order ) {
		if ( ! in_array( $to, array( 'processing', 'on-hold', 'completed' ), true ) ) {
			return;
		}
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order || $order->get_meta( '_marmar_cart_closed' ) ) {
			return;
		}
		self::close_for_order( $order );
		$order->update_meta_data( '_marmar_cart_closed', 1 );
		$order->save_meta_data();
	}

	/**
	 * Помечает корзины покупателя как купленные. Если письмо уже уходило — это «возвращённая» корзина.
	 */
	public static function close_for_order( WC_Order $order ) {
		global $wpdb;
		$table = MarMar_Emails_Install::carts_table();
		$email = strtolower( (string) $order->get_billing_email() );
		$token = (string) $order->get_meta( '_marmar_cart_token' );

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status IN ('open','sent1','sent2') AND ( token = %s OR ( email <> '' AND email = %s ) )", $token, $email ) ); // phpcs:ignore
		foreach ( $rows as $row ) {
			$was_emailed = ! empty( $row->sent1_at );
			$wpdb->update(
				$table,
				array(
					'status'      => $was_emailed ? 'recovered' : 'ordered',
					'order_id'    => $order->get_id(),
					'order_total' => (float) $order->get_total(),
					'updated_at'  => current_time( 'mysql', true ),
				),
				array( 'id' => $row->id )
			);
		}
	}

	/* ------------------------------------------------------------------ */
	/* Восстановление корзины по ссылке                                    */
	/* ------------------------------------------------------------------ */

	public static function restore_url( $token, $step ) {
		return add_query_arg(
			array(
				'marmar_cart'  => $token,
				'utm_source'   => 'marmar',
				'utm_medium'   => 'email',
				'utm_campaign' => 'abandoned_cart_' . (int) $step,
			),
			home_url( '/' )
		);
	}

	public static function handle_restore_link() {
		if ( empty( $_GET['marmar_cart'] ) || is_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		global $wpdb;
		$token = preg_replace( '/[^A-Za-z0-9]/', '', wp_unslash( $_GET['marmar_cart'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$table = MarMar_Emails_Install::carts_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ) ); // phpcs:ignore

		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'marmar cart restore' );

		$utm = array();
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign' ) as $k ) {
			if ( ! empty( $_GET[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$utm[ $k ] = sanitize_text_field( wp_unslash( $_GET[ $k ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}
		}

		if ( ! $row || in_array( $row->status, array( 'recovered', 'ordered' ), true ) ) {
			wp_safe_redirect( add_query_arg( $utm, wc_get_page_permalink( 'shop' ) ) );
			exit;
		}

		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		// Не дублируем товары, если корзина в этой сессии уже такая же.
		WC()->cart->empty_cart( false );
		$items = json_decode( (string) $row->contents, true );
		foreach ( (array) $items as $item ) {
			$product = wc_get_product( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );
			if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				continue;
			}
			WC()->cart->add_to_cart( (int) $item['product_id'], max( 1, (int) $item['quantity'] ), (int) $item['variation_id'], (array) $item['variation'] );
		}

		WC()->session->set( 'marmar_restored_token', $row->token );
		WC()->session->set( 'marmar_email', $row->email );
		if ( WC()->customer ) {
			if ( ! WC()->customer->get_billing_email() ) {
				WC()->customer->set_billing_email( $row->email );
			}
			if ( $row->first_name && ! WC()->customer->get_billing_first_name() ) {
				WC()->customer->set_billing_first_name( $row->first_name );
			}
			WC()->customer->save();
		}

		$wpdb->update(
			$table,
			array(
				'clicked_at'  => $row->clicked_at ? $row->clicked_at : current_time( 'mysql', true ),
				'session_key' => self::session_key(),
			),
			array( 'id' => $row->id )
		);

		wp_safe_redirect( add_query_arg( $utm, wc_get_checkout_url() ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Очередь писем                                                       */
	/* ------------------------------------------------------------------ */

	public static function legacy_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'wc-abandoned-cart-pro/wc-abandoned-cart-pro.php' );
	}

	public static function process_queue() {
		global $wpdb;
		if ( ! self::enabled() || self::legacy_active() ) {
			return;
		}
		$table   = MarMar_Emails_Install::carts_table();
		$now     = time();
		$d1      = max( 0.25, (float) MarMar_Emails_Settings::get( 'cart_delay_1' ) ) * HOUR_IN_SECONDS;
		$d2      = max( 1, (float) MarMar_Emails_Settings::get( 'cart_delay_2' ) ) * HOUR_IN_SECONDS;
		$max_age = max( 1, (int) MarMar_Emails_Settings::get( 'cart_max_age' ) ) * DAY_IN_SECONDS;
		$oldest  = gmdate( 'Y-m-d H:i:s', $now - $max_age );

		// Устаревшие — больше не трогаем.
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'expired' WHERE status IN ('open','sent1') AND updated_at < %s", $oldest ) ); // phpcs:ignore

		$first = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'open' AND email <> '' AND updated_at <= %s ORDER BY id ASC LIMIT 20", gmdate( 'Y-m-d H:i:s', $now - $d1 ) ) ); // phpcs:ignore
		foreach ( $first as $row ) {
			self::send_step( $row, 1 );
		}

		$second = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'sent1' AND updated_at <= %s AND sent1_at <= %s ORDER BY id ASC LIMIT 20", gmdate( 'Y-m-d H:i:s', $now - $d2 ), gmdate( 'Y-m-d H:i:s', $now - 12 * HOUR_IN_SECONDS ) ) ); // phpcs:ignore
		foreach ( $second as $row ) {
			self::send_step( $row, 2 );
		}
	}

	private static function has_paid_order_since( $email, $since_mysql_gmt ) {
		$orders = wc_get_orders(
			array(
				'billing_email' => $email,
				'status'        => array( 'wc-processing', 'wc-on-hold', 'wc-completed' ),
				'date_created'  => '>=' . strtotime( $since_mysql_gmt . ' UTC' ),
				'limit'         => 1,
				'return'        => 'ids',
			)
		);
		return ! empty( $orders );
	}

	private static function send_step( $row, $step ) {
		global $wpdb;
		$table = MarMar_Emails_Install::carts_table();

		if ( MarMar_Emails_Optout::is_opted_out( $row->email ) || MarMar_Emails_Settings::is_excluded( $row->email ) ) {
			$wpdb->update( $table, array( 'status' => 'skipped' ), array( 'id' => $row->id ) );
			return;
		}
		if ( self::has_paid_order_since( $row->email, $row->created_at ) ) {
			$wpdb->update( $table, array( 'status' => 'ordered' ), array( 'id' => $row->id ) );
			return;
		}
		$items_html = self::items_html( $row );
		if ( '' === $items_html ) {
			$wpdb->update( $table, array( 'status' => 'expired' ), array( 'id' => $row->id ) );
			return;
		}

		$sent = MarMar_Emails_Mailer::send(
			$row->email,
			MarMar_Emails_Settings::get( 'cart_subject_' . $step ),
			MarMar_Emails_Settings::get( 'cart_heading_' . $step ),
			MarMar_Emails_Settings::get( 'cart_body_' . $step ),
			array(
				'first_name' => $row->first_name,
				'cart_items' => $items_html,
				'cart_url'   => self::restore_url( $row->token, $step ),
				'cart_total' => wp_strip_all_tags( wc_price( $row->cart_total, array( 'currency' => $row->currency ) ) ),
			)
		);

		// Даже если отправка не удалась — не пытаемся бесконечно.
		$wpdb->update(
			$table,
			array(
				'status'           => 'sent' . $step,
				'sent' . $step . '_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $row->id )
		);
		if ( ! $sent ) {
			wc_get_logger()->warning( 'Cart email ' . $step . ' failed for cart #' . $row->id, array( 'source' => 'marmar-emails' ) );
		}
	}

	/**
	 * Таблица товаров для письма (актуальные цены и картинки). Пустая строка — нечего показывать.
	 */
	public static function items_html( $row ) {
		$items = json_decode( (string) $row->contents, true );
		$html  = '';
		foreach ( (array) $items as $item ) {
			$product = wc_get_product( ! empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'] );
			if ( ! $product || 'publish' !== get_post_status( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id() ) ) {
				continue;
			}
			$qty   = max( 1, (int) $item['quantity'] );
			$img   = wp_get_attachment_image_url( $product->get_image_id() ? $product->get_image_id() : get_post_thumbnail_id( $product->get_parent_id() ), 'thumbnail' );
			$price = wc_price( wc_get_price_to_display( $product ) * $qty );
			$html .= '<tr>';
			$html .= '<td style="padding:8px 8px 8px 0;width:64px;vertical-align:middle;">' . ( $img ? '<img src="' . esc_url( $img ) . '" width="56" height="56" style="border-radius:6px;display:block;" alt="">' : '' ) . '</td>';
			$html .= '<td style="padding:8px;vertical-align:middle;"><a href="' . esc_url( $product->get_permalink() ) . '" style="color:#3c3c3c;text-decoration:none;">' . esc_html( $product->get_name() ) . '</a> × ' . $qty . '</td>';
			$html .= '<td style="padding:8px 0 8px 8px;text-align:right;white-space:nowrap;vertical-align:middle;">' . wp_kses_post( $price ) . '</td>';
			$html .= '</tr>';
		}
		if ( '' === $html ) {
			return '';
		}
		return '<table cellspacing="0" cellpadding="0" style="width:100%;margin:16px 0;border-top:1px solid #eee;border-bottom:1px solid #eee;">' . $html . '</table>';
	}
}
