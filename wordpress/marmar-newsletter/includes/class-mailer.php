<?php
/**
 * Отправка писем: SMTP (например, Google), шаблон письма, персонализация, UTM.
 */

defined( 'ABSPATH' ) || exit;

class Marmar_NL_Mailer {

	const OPTION = 'marmar_nl_settings';

	/** Включается только на время отправки наших писем (если SMTP не для всего сайта). */
	private static $sending = false;

	public static function init() {
		add_action( 'phpmailer_init', array( __CLASS__, 'configure_phpmailer' ), 20 );
	}

	public static function settings() {
		$defaults = array(
			'from_email'   => get_option( 'admin_email' ),
			'from_name'    => 'Alina | MarMar Candles',
			'smtp_enabled' => 0,
			'smtp_host'    => 'smtp.gmail.com',
			'smtp_port'    => 587,
			'smtp_secure'  => 'tls',
			'smtp_user'    => '',
			'smtp_pass'    => '',
			'smtp_all'     => 0,
			'batch_size'   => 20,
			'test_emails'  => get_option( 'admin_email' ),
			'address'      => 'MarMar Candles, Rīga, Latvija',
		);
		$s = wp_parse_args( (array) get_option( self::OPTION, array() ), $defaults );
		// Пароль можно держать в wp-config.php вместо базы.
		if ( defined( 'MARMAR_NL_SMTP_PASS' ) ) {
			$s['smtp_pass'] = MARMAR_NL_SMTP_PASS;
		}
		return $s;
	}

	public static function smtp_ready() {
		$s = self::settings();
		return $s['smtp_enabled'] && $s['smtp_host'] && $s['smtp_user'] && $s['smtp_pass'];
	}

	public static function configure_phpmailer( $phpmailer ) {
		$s = self::settings();
		if ( ! self::smtp_ready() || ( ! self::$sending && ! $s['smtp_all'] ) ) {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host       = $s['smtp_host'];
		$phpmailer->Port       = (int) $s['smtp_port'];
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Username   = $s['smtp_user'];
		$phpmailer->Password   = $s['smtp_pass'];
		$phpmailer->SMTPSecure = 'ssl' === $s['smtp_secure'] ? 'ssl' : 'tls';
		$phpmailer->Timeout    = 20;
		// Google разрешает отправлять только от своего адреса или подтверждённого псевдонима.
		if ( ! self::$sending && is_email( $s['smtp_user'] ) ) {
			$phpmailer->Sender = $s['smtp_user'];
		}
	}

	/**
	 * Подставить имя и ссылки, добавить UTM к ссылкам на сайт.
	 */
	public static function personalize( $html, $subscriber, $campaign_slug ) {
		$first = $subscriber && $subscriber->first_name ? $subscriber->first_name : '';
		$html  = str_replace(
			array( '{vards}', '{vards_komats}', '{atrakstities_url}' ),
			array(
				esc_html( $first ),
				$first ? ', ' . esc_html( $first ) : '',
				esc_url( Marmar_NL_Unsubscribe::url( $subscriber ) ),
			),
			$html
		);
		return self::add_utm( $html, $campaign_slug );
	}

	public static function add_utm( $html, $campaign_slug ) {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = preg_replace( '/^www\./', '', (string) $host );
		return preg_replace_callback(
			'/href=("|\')(https?:\/\/(?:www\.)?' . preg_quote( $host, '/' ) . '[^"\']*)\1/i',
			static function ( $m ) use ( $campaign_slug ) {
				$url = html_entity_decode( $m[2] );
				if ( false !== strpos( $url, 'marmar_unsub=' ) || false !== strpos( $url, 'utm_source=' ) ) {
					return $m[0];
				}
				$url = add_query_arg(
					array(
						'utm_source'   => 'newsletter',
						'utm_medium'   => 'email',
						'utm_campaign' => $campaign_slug,
					),
					$url
				);
				return 'href=' . $m[1] . esc_url( $url ) . $m[1];
			},
			$html
		);
	}

	/**
	 * Обернуть содержимое в шаблон письма.
	 */
	public static function render( $content_html, $preheader, $subscriber ) {
		$s           = self::settings();
		$unsub_url   = Marmar_NL_Unsubscribe::url( $subscriber );
		$logo_id     = get_theme_mod( 'custom_logo' );
		$logo_url    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		$site_url    = home_url( '/' );
		$site_name   = get_bloginfo( 'name' );
		$address     = $s['address'];
		ob_start();
		include MARMAR_NL_DIR . 'templates/email.php';
		return ob_get_clean();
	}

	/**
	 * Отправить одно письмо кампании одному подписчику.
	 *
	 * @param object|null $subscriber null — тестовое письмо без реального подписчика.
	 * @return true|string true или текст ошибки.
	 */
	public static function send( $to, $subject, $content_html, $preheader, $subscriber, $campaign_slug ) {
		$s     = self::settings();
		$body  = self::render( self::personalize( $content_html, $subscriber, $campaign_slug ), $preheader, $subscriber );
		$from  = is_email( $s['from_email'] ) ? $s['from_email'] : $s['smtp_user'];
		$unsub = Marmar_NL_Unsubscribe::url( $subscriber );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', str_replace( array( '<', '>', '"' ), '', $s['from_name'] ), $from ),
			'Reply-To: ' . $from,
		);
		if ( $subscriber ) {
			$headers[] = 'List-Unsubscribe: <' . esc_url_raw( $unsub ) . '>';
			$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		}

		$error   = '';
		$catcher = static function ( $wp_error ) use ( &$error ) {
			$error = $wp_error->get_error_message();
		};
		add_action( 'wp_mail_failed', $catcher );
		$text_body = static function ( $phpmailer ) use ( $body ) {
			$phpmailer->AltBody = trim( wp_strip_all_tags( preg_replace( '/<(br|\/p|\/h[1-6]|\/tr)[^>]*>/i', "\n", $body ) ) );
		};
		add_action( 'phpmailer_init', $text_body );
		self::$sending = true;

		$ok = wp_mail( $to, $subject, $body, $headers );

		self::$sending = false;
		remove_action( 'phpmailer_init', $text_body );
		remove_action( 'wp_mail_failed', $catcher );

		return $ok ? true : ( $error ? $error : 'wp_mail() atgrieza false' );
	}
}
