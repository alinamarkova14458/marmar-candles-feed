<?php
defined( 'ABSPATH' ) || exit;

class MarMar_Chat_Widget {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	private static function visible() {
		if ( is_admin() || wp_doing_ajax() ) {
			return false;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return false; // не отвлекаем на оформлении заказа
		}
		// Пока чат выключен — виден только администратору (для проверки на живом сайте).
		return 'yes' === MarMar_Chat_Settings::get( 'enabled' ) || current_user_can( 'manage_options' );
	}

	public static function enqueue() {
		if ( ! self::visible() ) {
			return;
		}
		$s = MarMar_Chat_Settings::all();
		wp_enqueue_style( 'marmar-chat', MARMAR_CHAT_URL . 'assets/chat.css', array(), MARMAR_CHAT_VERSION );
		wp_enqueue_script( 'marmar-chat', MARMAR_CHAT_URL . 'assets/chat.js', array(), MARMAR_CHAT_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script(
			'marmar-chat',
			'MarMarChat',
			array(
				'endpoint'   => esc_url_raw( rest_url( 'marmar-chat/v1/message' ) ),
				'launcher'   => $s['launcher_text'],
				'greeting'   => $s['greeting'],
				'chips'      => array_values( array_filter( array_map( 'trim', explode( "\n", (string) $s['chips'] ) ) ) ),
				'disclaimer' => $s['disclaimer'],
				'privacy'    => get_privacy_policy_url(),
				'position'   => 'right' === $s['position'] ? 'right' : 'left',
				'title'      => 'MarMar CANDLES',
				'placeholder'=> 'Rakstiet ziņu…',
				'preview'    => 'yes' !== $s['enabled'] ? 'Testa režīms: čats redzams tikai administratoram.' : '',
			)
		);
	}
}
