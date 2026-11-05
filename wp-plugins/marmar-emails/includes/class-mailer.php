<?php
defined( 'ABSPATH' ) || exit;

/**
 * Отправка писем в оформлении WooCommerce (шапка, логотип, цвета из настроек WooCommerce → Email).
 */
class MarMar_Emails_Mailer {

	/**
	 * @param string $to      Получатель.
	 * @param string $subject Тема (с плейсхолдерами).
	 * @param string $heading Заголовок внутри письма (с плейсхолдерами).
	 * @param string $body    HTML тела (с плейсхолдерами).
	 * @param array  $vars    Значения плейсхолдеров без фигурных скобок.
	 * @return bool
	 */
	public static function send( $to, $subject, $heading, $body, array $vars = array() ) {
		$message = self::render( $to, $heading, $body, $vars );
		$subject = self::replace( $subject, $vars );

		$headers  = "Content-Type: text/html; charset=UTF-8\r\n";
		$headers .= 'List-Unsubscribe: <' . esc_url_raw( MarMar_Emails_Optout::url( $to ) ) . ">\r\n";

		return (bool) WC()->mailer()->send( $to, wp_strip_all_tags( $subject ), $message, $headers );
	}

	/**
	 * Готовый HTML письма (используется и для отправки, и для предпросмотра).
	 */
	public static function render( $to, $heading, $body, array $vars = array() ) {
		$vars['unsubscribe_url'] = MarMar_Emails_Optout::url( $to );

		$content  = self::replace( $body, $vars );
		$content .= '<p style="margin-top:32px;font-size:12px;color:#8a8a8a;">' . self::replace( (string) MarMar_Emails_Settings::get( 'unsubscribe_text' ), $vars ) . '</p>';

		$mailer  = WC()->mailer();
		$wrapped = $mailer->wrap_message( wp_strip_all_tags( self::replace( $heading, $vars ) ), wpautop( $content, false ) );

		// Инлайн-стили WooCommerce (как у стандартных писем).
		$email = new WC_Email();
		return $email->style_inline( $wrapped );
	}

	public static function replace( $text, array $vars ) {
		$first = isset( $vars['first_name'] ) ? trim( (string) $vars['first_name'] ) : '';
		$vars['first_name_comma'] = '' !== $first ? ', ' . $first : '';
		$vars['site_title']       = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$search  = array();
		$replace = array();
		foreach ( $vars as $key => $value ) {
			$search[]  = '{' . $key . '}';
			// HTML-блоки (cart_items) вставляем как есть, остальное экранируем.
			$replace[] = in_array( $key, array( 'cart_items' ), true ) ? (string) $value : esc_html( (string) $value );
		}
		// Ссылки в href: esc_html не ломает URL, но & превращается в &amp; — это корректно для HTML.
		return str_replace( $search, $replace, (string) $text );
	}
}
