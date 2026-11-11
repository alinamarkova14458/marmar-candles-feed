<?php
defined( 'ABSPATH' ) || exit;

/**
 * Настройки плагина: одна опция-массив с значениями по умолчанию.
 */
class MarMar_Emails_Settings {

	const OPTION = 'marmar_emails_settings';

	public static function defaults() {
		$button = 'display:inline-block;background:#d68684;color:#ffffff;padding:12px 22px;border-radius:4px;text-decoration:none;font-weight:bold;';

		return array(
			'carts_enabled'      => 'yes',
			'cart_delay_1'       => 1,   // часов после последней активности
			'cart_delay_2'       => 24,  // часов после последней активности
			'cart_max_age'       => 7,   // дней: старше не пишем
			'cart_notice'        => 'Ja pasūtījumu nepabeigsiet, varam atsūtīt atgādinājumu par grozu. Atteikties var jebkurā brīdī.',

			'cart_subject_1'     => 'Jūsu grozs joprojām gaida ❤️',
			'cart_heading_1'     => 'Nepametiet savu grozu',
			'cart_body_1'        => "<p>Sveiki{first_name_comma}!</p>\n<p>Pamanījām, ka pievienojāt grozam sveces, bet pasūtījumu vēl nepabeidzāt. Tās joprojām gaida — un mēs arī 🕯️</p>\n<p>Ja tas notika nejauši vai vienkārši pietrūka laika, varat turpināt tieši no vietas, kur apstājāties:</p>\n{cart_items}\n<p style=\"margin:28px 0;\"><a href=\"{cart_url}\" style=\"$button\">Pabeigt pasūtījumu</a></p>\n<p>Ar cieņu,<br>MarMar CANDLES</p>",

			'cart_subject_2'     => 'Vai joprojām domājat par savu pasūtījumu? ❤️',
			'cart_heading_2'     => 'Saglabājām Jūsu grozu',
			'cart_body_2'        => "<p>Sveiki{first_name_comma}!</p>\n<p>Jūsu izvēlētās sveces joprojām ir saglabātas grozā.</p>\n<p>Katra MarMar CANDLES svece ir roku darbs, ko gatavojam ar rūpību un uzmanību pret detaļām. Ja vēl vēlaties tās saņemt, ar prieku sagatavosim pasūtījumu tieši Jums.</p>\n<p>✔ Roku darbs Latvijā<br>✔ Droša apmaksa<br>✔ Piegāde uz pakomātiem visā Latvijā</p>\n{cart_items}\n<p style=\"margin:28px 0;\"><a href=\"{cart_url}\" style=\"$button\">Atgriezties pie pasūtījuma</a></p>\n<p>Ar cieņu,<br>MarMar CANDLES</p>",

			'reviews_enabled'    => 'yes',
			'review_delay_days'  => 5,
			'review_url'         => 'https://g.page/r/CXL0frKfTFEFEBM/review',
			'review_subject'     => 'Paldies par Jūsu pasūtījumu! 🤍',
			'review_heading'     => 'Kā Jums patika sveces?',
			'review_body'        => "<p>Sveiki{first_name_comma}!</p>\n<p>Paldies, ka izvēlējāties MarMar CANDLES! 🤍</p>\n<p>Ja Jums patika pasūtījums, mēs ļoti priecāsimies, ja veltīsiet minūti un atstāsiet atsauksmi Google. Tā palīdz citiem atrast mūsu sveces — un mums dod spārnus.</p>\n<p style=\"margin:28px 0;\"><a href=\"{review_url}\" style=\"$button\">⭐ Atstāt atsauksmi Google</a></p>\n<p>Paldies par atbalstu! ❤️</p>\n<p>Ar cieņu,<br>MarMar CANDLES</p>",

			'excluded_emails'    => '',
			'unsubscribe_text'   => 'Nevēlaties saņemt šādus e-pastus? <a href="{unsubscribe_url}">Atteikties</a>.',
			'unsubscribed_page'  => 'Jūs esat atteikušies no atgādinājumiem un atsauksmju lūgumiem. Pasūtījumu e-pastus joprojām saņemsiet.',
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function update( array $values ) {
		$current = self::all();
		update_option( self::OPTION, array_merge( $current, $values ), false );
	}

	/**
	 * Адреса, на которые никогда не пишем (владелица, магазин, админ).
	 */
	public static function excluded_emails() {
		$list = array_filter( array_map( 'trim', preg_split( '/[\s,;]+/', strtolower( (string) self::get( 'excluded_emails' ) ) ) ) );
		$list[] = strtolower( (string) get_option( 'admin_email' ) );
		$list[] = strtolower( (string) get_option( 'woocommerce_email_from_address' ) );
		return array_values( array_unique( array_filter( $list ) ) );
	}

	public static function is_excluded( $email ) {
		return in_array( strtolower( trim( $email ) ), self::excluded_emails(), true );
	}
}
