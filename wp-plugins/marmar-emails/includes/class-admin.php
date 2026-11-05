<?php
defined( 'ABSPATH' ) || exit;

/**
 * Админка: WooCommerce → MarMar Emails (обзор, настройки, тест/предпросмотр).
 */
class MarMar_Emails_Admin {

	const PAGE = 'marmar-emails';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_post_marmar_emails_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_marmar_emails_test', array( __CLASS__, 'test' ) );
		add_action( 'admin_post_marmar_emails_preview', array( __CLASS__, 'preview' ) );
		add_action( 'admin_notices', array( __CLASS__, 'legacy_notice' ) );
	}

	public static function menu() {
		add_submenu_page( 'woocommerce', 'MarMar Emails', 'MarMar Emails', 'manage_woocommerce', self::PAGE, array( __CLASS__, 'render' ) );
	}

	public static function legacy_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$old = array();
		if ( MarMar_Emails_Carts::legacy_active() ) {
			$old[] = 'WooCommerce Abandoned Cart PRO';
		}
		if ( MarMar_Emails_Reviews::legacy_active() ) {
			$old[] = 'WooCommerce Google Reviews PRO';
		}
		if ( $old ) {
			echo '<div class="notice notice-warning"><p><strong>MarMar Emails:</strong> отключите старые плагины (' . esc_html( implode( ', ', $old ) ) . '). Пока они включены, MarMar Emails не отправляет эти письма, чтобы клиенты не получили их дважды.</p></div>';
		}
	}

	private static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	public static function render() {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification
		$tabs = array(
			'overview' => 'Обзор',
			'settings' => 'Настройки и тексты',
			'test'     => 'Тест и предпросмотр',
		);
		echo '<div class="wrap"><h1>MarMar Emails</h1>';
		if ( isset( $_GET['marmar_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['marmar_msg'] ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
		}
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			echo '<a class="nav-tab' . ( $key === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( self::url( array( 'tab' => $key ) ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
		if ( 'settings' === $tab ) {
			self::render_settings();
		} elseif ( 'test' === $tab ) {
			self::render_test();
		} else {
			self::render_overview();
		}
		echo '</div>';
	}

	/* ------------------------------------------------------------------ */

	private static function card( $title, $value, $hint = '' ) {
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 20px;min-width:160px;flex:1;">';
		echo '<div style="color:#646970;font-size:13px;">' . esc_html( $title ) . '</div>';
		echo '<div style="font-size:26px;font-weight:600;margin-top:6px;">' . wp_kses_post( $value ) . '</div>';
		if ( $hint ) {
			echo '<div style="color:#8c8f94;font-size:12px;margin-top:4px;">' . esc_html( $hint ) . '</div>';
		}
		echo '</div>';
	}

	private static function local_time( $gmt ) {
		return $gmt ? get_date_from_gmt( $gmt, 'd.m.Y H:i' ) : '—';
	}

	private static function render_overview() {
		global $wpdb;
		$carts   = MarMar_Emails_Install::carts_table();
		$reviews = MarMar_Emails_Install::reviews_table();
		$since   = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );

		$captured  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$carts} WHERE created_at >= %s AND status <> 'empty'", $since ) ); // phpcs:ignore
		$emailed   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$carts} WHERE created_at >= %s AND sent1_at IS NOT NULL", $since ) ); // phpcs:ignore
		$clicked   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$carts} WHERE created_at >= %s AND clicked_at IS NOT NULL", $since ) ); // phpcs:ignore
		$recovered = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) n, COALESCE(SUM(order_total),0) s FROM {$carts} WHERE created_at >= %s AND status = 'recovered'", $since ) ); // phpcs:ignore
		$r_sent    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$reviews} WHERE status = 'sent' AND sent_at >= %s", $since ) ); // phpcs:ignore
		$r_queue   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$reviews} WHERE status = 'pending'" ); // phpcs:ignore

		echo '<h2>Последние 30 дней</h2><div style="display:flex;gap:12px;flex-wrap:wrap;margin:12px 0 24px;">';
		self::card( 'Корзин с email', $captured );
		self::card( 'Отправлено напоминаний', $emailed, 'корзин, получивших хотя бы 1 письмо' );
		self::card( 'Переходов по ссылке', $clicked );
		self::card( 'Возвращено корзин', (int) $recovered->n, 'купили после письма' );
		self::card( 'Выручка возвращённых', wc_price( (float) $recovered->s ) );
		self::card( 'Просьб об отзыве', $r_sent, 'в очереди: ' . $r_queue );
		echo '</div>';

		$labels = array(
			'open'      => 'ждёт письма',
			'sent1'     => '1-е письмо отправлено',
			'sent2'     => '2-е письмо отправлено',
			'recovered' => '🎉 куплено после письма',
			'ordered'   => 'куплено',
			'expired'   => 'устарела',
			'empty'     => 'корзину очистили',
			'skipped'   => 'отписка / исключение',
		);

		echo '<h2>Корзины</h2>';
		$rows = $wpdb->get_results( "SELECT * FROM {$carts} WHERE status <> 'empty' ORDER BY updated_at DESC LIMIT 50" ); // phpcs:ignore
		echo '<table class="widefat striped"><thead><tr><th>Email</th><th>Статус</th><th>Активность</th><th>Сумма</th><th>Переход</th><th>Заказ</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="6">Пока нет корзин с email.</td></tr>';
		}
		foreach ( $rows as $r ) {
			$order_link = $r->order_id ? '<a href="' . esc_url( admin_url( 'post.php?post=' . (int) $r->order_id . '&action=edit' ) ) . '">#' . (int) $r->order_id . '</a> (' . wc_price( $r->order_total ) . ')' : '—';
			echo '<tr><td>' . esc_html( $r->email ) . '</td><td>' . esc_html( isset( $labels[ $r->status ] ) ? $labels[ $r->status ] : $r->status ) . '</td><td>' . esc_html( self::local_time( $r->updated_at ) ) . '</td><td>' . wp_kses_post( wc_price( $r->cart_total ) ) . '</td><td>' . esc_html( self::local_time( $r->clicked_at ) ) . '</td><td>' . wp_kses_post( $order_link ) . '</td></tr>';
		}
		echo '</tbody></table>';

		$rlabels = array(
			'pending'   => '⏳ ждёт',
			'sent'      => '✅ отправлено',
			'failed'    => '⚠️ ошибка отправки',
			'cancelled' => 'заказ отменён/возвращён',
			'skipped'   => 'отписка / исключение',
			'expired'   => 'устарело',
		);
		echo '<h2 style="margin-top:28px;">Просьбы об отзыве</h2>';
		$rows = $wpdb->get_results( "SELECT * FROM {$reviews} ORDER BY completed_at DESC LIMIT 50" ); // phpcs:ignore
		echo '<table class="widefat striped"><thead><tr><th>Заказ</th><th>Email</th><th>Выполнен</th><th>Отправка</th><th>Статус</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="5">Пока пусто.</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr><td><a href="' . esc_url( admin_url( 'post.php?post=' . (int) $r->order_id . '&action=edit' ) ) . '">#' . (int) $r->order_id . '</a></td><td>' . esc_html( $r->email ) . '</td><td>' . esc_html( self::local_time( $r->completed_at ) ) . '</td><td>' . esc_html( self::local_time( $r->sent_at ? $r->sent_at : $r->send_at ) ) . '</td><td>' . esc_html( isset( $rlabels[ $r->status ] ) ? $rlabels[ $r->status ] : $r->status ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/* ------------------------------------------------------------------ */

	private static function field_text( $key, $label, $s, $wide = true ) {
		echo '<tr><th><label for="mm_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input type="text" id="mm_' . esc_attr( $key ) . '" name="s[' . esc_attr( $key ) . ']" value="' . esc_attr( $s[ $key ] ) . '" class="' . ( $wide ? 'large-text' : 'small-text' ) . '"></td></tr>';
	}

	private static function field_area( $key, $label, $s, $rows = 10 ) {
		echo '<tr><th><label for="mm_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><textarea id="mm_' . esc_attr( $key ) . '" name="s[' . esc_attr( $key ) . ']" rows="' . (int) $rows . '" class="large-text code">' . esc_textarea( $s[ $key ] ) . '</textarea></td></tr>';
	}

	private static function field_check( $key, $label, $s ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="s[' . esc_attr( $key ) . ']" value="yes" ' . checked( 'yes', $s[ $key ], false ) . '> включено</label></td></tr>';
	}

	private static function render_settings() {
		$s = MarMar_Emails_Settings::all();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'marmar_emails_save' );
		echo '<input type="hidden" name="action" value="marmar_emails_save">';

		echo '<p>Плейсхолдеры: <code>{first_name}</code>, <code>{first_name_comma}</code> (», Имя» или пусто), <code>{cart_items}</code>, <code>{cart_url}</code>, <code>{cart_total}</code>, <code>{review_url}</code>, <code>{order_number}</code>, <code>{site_title}</code>, <code>{unsubscribe_url}</code>. Ссылка «Atteikties» добавляется в конец каждого письма автоматически.</p>';

		echo '<h2>Брошенные корзины</h2><table class="form-table">';
		self::field_check( 'carts_enabled', 'Напоминания о корзине', $s );
		self::field_text( 'cart_delay_1', 'Письмо 1: через сколько часов', $s, false );
		self::field_text( 'cart_delay_2', 'Письмо 2: через сколько часов', $s, false );
		self::field_text( 'cart_max_age', 'Не писать про корзины старше (дней)', $s, false );
		self::field_text( 'cart_notice', 'Подсказка под полем email на оформлении', $s );
		self::field_text( 'cart_subject_1', 'Письмо 1 — тема', $s );
		self::field_text( 'cart_heading_1', 'Письмо 1 — заголовок', $s );
		self::field_area( 'cart_body_1', 'Письмо 1 — текст (HTML)', $s );
		self::field_text( 'cart_subject_2', 'Письмо 2 — тема', $s );
		self::field_text( 'cart_heading_2', 'Письмо 2 — заголовок', $s );
		self::field_area( 'cart_body_2', 'Письмо 2 — текст (HTML)', $s );
		echo '</table>';

		echo '<h2>Отзыв в Google</h2><table class="form-table">';
		self::field_check( 'reviews_enabled', 'Просьба об отзыве', $s );
		self::field_text( 'review_delay_days', 'Через сколько дней после «Выполнен»', $s, false );
		self::field_text( 'review_url', 'Ссылка на отзыв в Google', $s );
		self::field_text( 'review_subject', 'Тема', $s );
		self::field_text( 'review_heading', 'Заголовок', $s );
		self::field_area( 'review_body', 'Текст (HTML)', $s );
		echo '</table>';

		echo '<h2>Общее</h2><table class="form-table">';
		self::field_area( 'excluded_emails', 'Никогда не писать на эти адреса (по одному в строке; email администратора и магазина исключены всегда)', $s, 3 );
		self::field_text( 'unsubscribe_text', 'Строка отписки внизу письма', $s );
		self::field_text( 'unsubscribed_page', 'Текст после отписки', $s );
		echo '</table>';

		submit_button( 'Сохранить' );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Вернуть все тексты и настройки по умолчанию?\');">';
		wp_nonce_field( 'marmar_emails_save' );
		echo '<input type="hidden" name="action" value="marmar_emails_save"><input type="hidden" name="reset" value="1">';
		submit_button( 'Вернуть тексты по умолчанию', 'secondary', 'submit', false );
		echo '</form>';
	}

	public static function save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'No access' );
		}
		check_admin_referer( 'marmar_emails_save' );

		if ( ! empty( $_POST['reset'] ) ) {
			delete_option( MarMar_Emails_Settings::OPTION );
			wp_safe_redirect( self::url( array( 'tab' => 'settings', 'marmar_msg' => 'Настройки сброшены.' ) ) );
			exit;
		}

		$in       = isset( $_POST['s'] ) ? (array) wp_unslash( $_POST['s'] ) : array(); // phpcs:ignore WordPress.Security.ValidationSanitization
		$defaults = MarMar_Emails_Settings::defaults();
		$out      = array();
		foreach ( $defaults as $key => $default ) {
			if ( in_array( $key, array( 'carts_enabled', 'reviews_enabled' ), true ) ) {
				$out[ $key ] = isset( $in[ $key ] ) ? 'yes' : 'no';
			} elseif ( ! isset( $in[ $key ] ) ) {
				continue;
			} elseif ( in_array( $key, array( 'cart_delay_1', 'cart_delay_2' ), true ) ) {
				$out[ $key ] = max( 0.25, (float) str_replace( ',', '.', $in[ $key ] ) );
			} elseif ( in_array( $key, array( 'cart_max_age', 'review_delay_days' ), true ) ) {
				$out[ $key ] = max( 0, (int) $in[ $key ] );
			} elseif ( 'review_url' === $key ) {
				$out[ $key ] = esc_url_raw( $in[ $key ] );
			} elseif ( 'excluded_emails' === $key ) {
				$out[ $key ] = implode( "\n", array_filter( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', $in[ $key ] ) ) ) );
			} else {
				$out[ $key ] = wp_kses_post( $in[ $key ] );
			}
		}
		MarMar_Emails_Settings::update( $out );
		wp_safe_redirect( self::url( array( 'tab' => 'settings', 'marmar_msg' => 'Сохранено.' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ */

	private static function sample_row() {
		$ids      = wc_get_products( array( 'status' => 'publish', 'limit' => 2, 'return' => 'ids', 'type' => array( 'simple' ) ) );
		$contents = array();
		foreach ( $ids as $id ) {
			$contents[] = array( 'product_id' => $id, 'variation_id' => 0, 'variation' => array(), 'quantity' => 1 );
		}
		return (object) array(
			'token'      => 'preview',
			'contents'   => wp_json_encode( $contents ),
			'first_name' => 'Anna',
			'cart_total' => 0,
			'currency'   => get_woocommerce_currency(),
		);
	}

	private static function build( $type, $to ) {
		$s = MarMar_Emails_Settings::all();
		if ( 'review' === $type ) {
			return array(
				$s['review_subject'],
				$s['review_heading'],
				$s['review_body'],
				array( 'first_name' => 'Anna', 'review_url' => $s['review_url'], 'order_number' => '12345' ),
			);
		}
		$step = 'cart2' === $type ? 2 : 1;
		$row  = self::sample_row();
		return array(
			$s[ 'cart_subject_' . $step ],
			$s[ 'cart_heading_' . $step ],
			$s[ 'cart_body_' . $step ],
			array(
				'first_name' => 'Anna',
				'cart_items' => MarMar_Emails_Carts::items_html( $row ),
				'cart_url'   => wc_get_checkout_url(),
				'cart_total' => '',
			),
		);
	}

	private static function render_test() {
		$user = wp_get_current_user();
		$types = array( 'cart1' => 'Корзина — письмо 1', 'cart2' => 'Корзина — письмо 2', 'review' => 'Просьба об отзыве' );
		echo '<p>Предпросмотр открывается в новой вкладке. Тестовое письмо приходит с примером: имя «Anna» и 2 товара из магазина. Ссылка в тестовом письме ведёт на обычное оформление заказа.</p>';
		echo '<table class="widefat" style="max-width:720px"><tbody>';
		foreach ( $types as $key => $label ) {
			$preview = wp_nonce_url( admin_url( 'admin-post.php?action=marmar_emails_preview&type=' . $key ), 'marmar_emails_preview' );
			echo '<tr><td><strong>' . esc_html( $label ) . '</strong></td><td><a class="button" target="_blank" href="' . esc_url( $preview ) . '">Предпросмотр</a></td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:6px;">';
			wp_nonce_field( 'marmar_emails_test' );
			echo '<input type="hidden" name="action" value="marmar_emails_test"><input type="hidden" name="type" value="' . esc_attr( $key ) . '">';
			echo '<input type="email" name="to" value="' . esc_attr( $user->user_email ) . '" required> <button class="button button-primary">Отправить тест</button></form></td></tr>';
		}
		echo '</tbody></table>';
	}

	public static function preview() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'No access' );
		}
		check_admin_referer( 'marmar_emails_preview' );
		$type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : 'cart1';
		list( , $heading, $body, $vars ) = self::build( $type, 'preview@example.com' );
		echo MarMar_Emails_Mailer::render( 'preview@example.com', $heading, $body, $vars ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public static function test() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'No access' );
		}
		check_admin_referer( 'marmar_emails_test' );
		$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'cart1';
		$to   = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		if ( ! is_email( $to ) ) {
			wp_safe_redirect( self::url( array( 'tab' => 'test', 'marmar_msg' => 'Неверный email.' ) ) );
			exit;
		}
		list( $subject, $heading, $body, $vars ) = self::build( $type, $to );
		$ok = MarMar_Emails_Mailer::send( $to, '[TEST] ' . $subject, $heading, $body, $vars );
		wp_safe_redirect( self::url( array( 'tab' => 'test', 'marmar_msg' => $ok ? 'Тестовое письмо отправлено на ' . $to : 'Не удалось отправить письмо (проверьте почтовые настройки сайта).' ) ) );
		exit;
	}
}
