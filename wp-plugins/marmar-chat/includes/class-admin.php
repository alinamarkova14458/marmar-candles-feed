<?php
defined( 'ABSPATH' ) || exit;

/**
 * Админка: Настройки → MarMar Chat (настройки, разговоры, проверка связи).
 */
class MarMar_Chat_Admin {

	const PAGE = 'marmar-chat';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_marmar_chat_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_marmar_chat_test', array( __CLASS__, 'test' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	public static function menu() {
		add_options_page( 'MarMar Chat', 'MarMar Chat', 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
	}

	private static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'options-general.php' ) );
	}

	public static function notices() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active( 'marmar-ai-product-chat/marmar-ai-product-chat.php' ) && 'yes' === MarMar_Chat_Settings::get( 'enabled' ) ) {
			echo '<div class="notice notice-warning"><p><strong>MarMar Chat:</strong> старый «MarMar AI Product Chat» тоже включён — на сайте два чата. Отключите старый.</p></div>';
		}
	}

	public static function render() {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification
		$tabs = array( 'settings' => 'Настройки', 'chats' => 'Разговоры' );
		echo '<div class="wrap"><h1>MarMar Chat</h1>';
		if ( isset( $_GET['msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['msg'] ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
		}
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $k => $label ) {
			echo '<a class="nav-tab' . ( $k === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( self::url( array( 'tab' => $k ) ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
		'chats' === $tab ? self::render_chats() : self::render_settings();
		echo '</div>';
	}

	private static function render_settings() {
		$s        = MarMar_Chat_Settings::all();
		$has_key  = '' !== MarMar_Chat_Settings::api_key();
		$in_const = defined( 'MARMAR_CHAT_API_KEY' ) && MARMAR_CHAT_API_KEY;
		$sdk_ok   = is_readable( MARMAR_CHAT_DIR . 'vendor-prefixed/autoload.php' );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'marmar_chat_save' );
		echo '<input type="hidden" name="action" value="marmar_chat_save"><table class="form-table">';

		echo '<tr><th>Чат на сайте</th><td><label><input type="checkbox" name="s[enabled]" value="yes" ' . checked( 'yes', $s['enabled'], false ) . '> включён для посетителей</label><p class="description">Пока выключено, чат виден только администраторам — можно спокойно проверить.</p></td></tr>';

		echo '<tr><th><label for="mmc_key">API-ключ Anthropic</label></th><td>';
		if ( $in_const ) {
			echo '<p>Ключ задан в <code>wp-config.php</code> (MARMAR_CHAT_API_KEY).</p>';
		} else {
			echo '<input type="password" id="mmc_key" name="s[api_key]" value="" class="regular-text" autocomplete="off" placeholder="' . ( $has_key ? '•••••••• (сохранён)' : 'sk-ant-…' ) . '">';
			echo '<p class="description">Создаётся на platform.claude.com → API Keys. Пустое поле — ключ не меняется.</p>';
		}
		echo '</td></tr>';

		$models = array(
			'claude-opus-5-5'   => 'Claude Opus 5.5 — лучшее качество (по умолчанию)',
			'claude-sonnet-5-5' => 'Claude Sonnet 5.5 — дешевле в 2 раза',
			'claude-haiku-5-5'  => 'Claude Haiku 5.5 — самый дешёвый',
		);
		echo '<tr><th><label for="mmc_model">Модель</label></th><td><select id="mmc_model" name="s[model]">';
		foreach ( $models as $id => $label ) {
			echo '<option value="' . esc_attr( $id ) . '" ' . selected( $id, $s['model'], false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th><label for="mmc_effort">Глубина ответа (effort)</label></th><td><select id="mmc_effort" name="s[effort]">';
		foreach ( array( 'low' => 'low — быстро, для чата (рекомендуется)', 'medium' => 'medium', 'high' => 'high — медленнее и дороже' ) as $id => $label ) {
			echo '<option value="' . esc_attr( $id ) . '" ' . selected( $id, $s['effort'], false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		foreach ( array(
			'daily_limit' => 'Лимит сообщений в сутки (весь сайт)',
			'ip_limit'    => 'Лимит сообщений в час с одного IP',
			'max_turns'   => 'Максимум сообщений в одном разговоре',
		) as $k => $label ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td><input type="number" min="1" name="s[' . esc_attr( $k ) . ']" value="' . esc_attr( $s[ $k ] ) . '" class="small-text"></td></tr>';
		}

		echo '<tr><th>Кнопка</th><td><input type="text" name="s[launcher_text]" value="' . esc_attr( $s['launcher_text'] ) . '" class="regular-text"> ';
		echo '<select name="s[position]"><option value="left" ' . selected( 'left', $s['position'], false ) . '>слева</option><option value="right" ' . selected( 'right', $s['position'], false ) . '>справа</option></select></td></tr>';
		echo '<tr><th>Приветствие</th><td><textarea name="s[greeting]" rows="3" class="large-text">' . esc_textarea( $s['greeting'] ) . '</textarea></td></tr>';
		echo '<tr><th>Быстрые кнопки (по одной в строке)</th><td><textarea name="s[chips]" rows="5" class="large-text">' . esc_textarea( $s['chips'] ) . '</textarea></td></tr>';
		echo '<tr><th>Подпись внизу чата</th><td><input type="text" name="s[disclaimer]" value="' . esc_attr( $s['disclaimer'] ) . '" class="large-text"></td></tr>';
		echo '<tr><th>Факты о магазине для бота</th><td><textarea name="s[facts]" rows="14" class="large-text code">' . esc_textarea( $s['facts'] ) . '</textarea><p class="description">Бот отвечает про сроки, доставку и заказы только по этому тексту. Обновляйте, когда что-то меняется (например, даты к праздникам).</p></td></tr>';
		echo '</table>';
		submit_button( 'Сохранить' );
		echo '</form>';

		echo '<h2>Проверка</h2><p>SDK: ' . ( $sdk_ok ? '✅ на месте' : '❌ нет папки vendor-prefixed — установите собранный zip' ) . ' · Ключ: ' . ( $has_key ? '✅ задан' : '❌ не задан' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'marmar_chat_test' );
		echo '<input type="hidden" name="action" value="marmar_chat_test"><input type="text" name="q" value="Meklēju sveces 5 gadus vecai meitenei" class="regular-text"> ';
		submit_button( 'Задать тестовый вопрос', 'secondary', 'submit', false );
		echo '</form>';
		if ( $res = get_transient( 'marmar_chat_test_result_' . get_current_user_id() ) ) {
			echo '<pre style="white-space:pre-wrap;background:#fff;border:1px solid #ddd;padding:12px;max-width:760px;">' . esc_html( $res ) . '</pre>';
		}
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'No access' );
		}
		check_admin_referer( 'marmar_chat_save' );
		$in  = isset( $_POST['s'] ) ? (array) wp_unslash( $_POST['s'] ) : array(); // phpcs:ignore WordPress.Security.ValidationSanitization
		$out = array(
			'enabled'       => isset( $in['enabled'] ) ? 'yes' : 'no',
			'model'         => in_array( $in['model'] ?? '', array_keys( MarMar_Chat_Settings::PRICES ), true ) ? $in['model'] : 'claude-opus-5-5',
			'effort'        => in_array( $in['effort'] ?? '', array( 'low', 'medium', 'high' ), true ) ? $in['effort'] : 'low',
			'daily_limit'   => max( 1, (int) ( $in['daily_limit'] ?? 300 ) ),
			'ip_limit'      => max( 1, (int) ( $in['ip_limit'] ?? 30 ) ),
			'max_turns'     => max( 1, (int) ( $in['max_turns'] ?? 25 ) ),
			'launcher_text' => sanitize_text_field( $in['launcher_text'] ?? '' ),
			'position'      => 'right' === ( $in['position'] ?? '' ) ? 'right' : 'left',
			'greeting'      => sanitize_textarea_field( $in['greeting'] ?? '' ),
			'chips'         => sanitize_textarea_field( $in['chips'] ?? '' ),
			'disclaimer'    => sanitize_text_field( $in['disclaimer'] ?? '' ),
			'facts'         => sanitize_textarea_field( $in['facts'] ?? '' ),
		);
		if ( ! empty( $in['api_key'] ) ) {
			$out['api_key'] = sanitize_text_field( $in['api_key'] );
		}
		MarMar_Chat_Settings::update( $out );
		wp_safe_redirect( self::url( array( 'msg' => 'Сохранено.' ) ) );
		exit;
	}

	public static function test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'No access' );
		}
		check_admin_referer( 'marmar_chat_test' );
		$q   = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
		$res = MarMar_Chat_Claude::reply( array( array( 'role' => 'user', 'content' => array( array( 'type' => 'text', 'text' => $q ) ) ) ) );
		$out = $res['ok']
			? $res['text'] . "\n\n— токены: вход " . $res['usage']['input'] . ', выход ' . $res['usage']['output'] . ', ≈ $' . number_format( $res['usage']['cost'], 4 )
			: 'Ошибка: ' . $res['error'] . ' (подробности: WooCommerce → Статус → Журналы → marmar-chat)';
		set_transient( 'marmar_chat_test_result_' . get_current_user_id(), $out, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( self::url() );
		exit;
	}

	private static function render_chats() {
		global $wpdb;
		$t = MarMar_Chat_Store::table();

		if ( ! empty( $_GET['cid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$row = MarMar_Chat_Store::get( preg_replace( '/[^A-Za-z0-9]/', '', wp_unslash( $_GET['cid'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			echo '<p><a href="' . esc_url( self::url( array( 'tab' => 'chats' ) ) ) . '">← все разговоры</a></p>';
			if ( ! $row ) {
				echo '<p>Не найдено.</p>';
				return;
			}
			echo '<p>Начат: ' . esc_html( get_date_from_gmt( $row->created_at, 'd.m.Y H:i' ) ) . ' · страница: ' . esc_html( $row->page_url ) . ' · ≈ $' . esc_html( number_format( (float) $row->cost, 4 ) ) . '</p>';
			echo '<div style="max-width:760px">';
			foreach ( $row->messages as $m ) {
				$txt = '';
				foreach ( (array) $m['content'] as $b ) {
					if ( 'text' === ( $b['type'] ?? '' ) ) {
						$txt .= $b['text'];
					}
				}
				if ( '' === $txt ) {
					continue;
				}
				$user = 'user' === $m['role'];
				echo '<div style="margin:8px 0;padding:10px 12px;border-radius:10px;white-space:pre-wrap;' . ( $user ? 'background:#c88a85;color:#fff;margin-left:80px' : 'background:#f6f0ef;margin-right:80px' ) . '">' . esc_html( $txt ) . '</div>';
			}
			echo '</div>';
			return;
		}

		$stats = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) n, COALESCE(SUM(turns),0) msgs, COALESCE(SUM(cost),0) cost FROM {$t} WHERE created_at >= %s", gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) ); // phpcs:ignore
		echo '<p>За 30 дней: <strong>' . (int) $stats->n . '</strong> разговоров, <strong>' . (int) $stats->msgs . '</strong> сообщений, расходы ≈ <strong>$' . esc_html( number_format( (float) $stats->cost, 2 ) ) . '</strong>. Разговоры хранятся 90 дней.</p>';
		$rows = $wpdb->get_results( "SELECT cid, created_at, turns, cost, messages FROM {$t} ORDER BY updated_at DESC LIMIT 100" ); // phpcs:ignore
		echo '<table class="widefat striped"><thead><tr><th>Дата</th><th>Первый вопрос</th><th>Сообщений</th><th>≈ $</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="4">Пока нет разговоров.</td></tr>';
		}
		foreach ( $rows as $r ) {
			$msgs  = json_decode( $r->messages, true ) ?: array();
			$first = '';
			foreach ( $msgs as $m ) {
				if ( 'user' === $m['role'] ) {
					$first = $m['content'][0]['text'] ?? '';
					break;
				}
			}
			echo '<tr><td>' . esc_html( get_date_from_gmt( $r->created_at, 'd.m.Y H:i' ) ) . '</td><td><a href="' . esc_url( self::url( array( 'tab' => 'chats', 'cid' => $r->cid ) ) ) . '">' . esc_html( mb_substr( $first, 0, 90 ) ?: '—' ) . '</a></td><td>' . (int) $r->turns . '</td><td>' . esc_html( number_format( (float) $r->cost, 4 ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
