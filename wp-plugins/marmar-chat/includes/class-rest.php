<?php
defined( 'ABSPATH' ) || exit;

/**
 * POST /wp-json/marmar-chat/v1/message  { cid?, message, page? }
 * Ответ: { cid, text, products[] } или { error, text }.
 */
class MarMar_Chat_Rest {

	const MAX_LEN = 1000;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'marmar-chat/v1',
			'/message',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				// Публичный эндпоинт для гостей; защищён проверкой источника и лимитами (nonce не используем — страницы кэшируются LiteSpeed).
				'permission_callback' => array( __CLASS__, 'same_origin' ),
				'args'                => array(
					'message' => array( 'type' => 'string', 'required' => true ),
					'cid'     => array( 'type' => 'string', 'required' => false ),
					'page'    => array( 'type' => 'string', 'required' => false ),
				),
			)
		);
	}

	public static function same_origin( WP_REST_Request $request ) {
		$origin = $request->get_header( 'origin' ) ?: $request->get_header( 'referer' );
		if ( ! $origin ) {
			return false;
		}
		$host = wp_parse_url( $origin, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host && ( strcasecmp( preg_replace( '/^www\./i', '', $host ), preg_replace( '/^www\./i', '', $home ) ) === 0 );
	}

	private static function ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 16 );
	}

	private static function friendly( $code ) {
		$contact = 'info@marmarcandles.lv';
		$map     = array(
			'busy'        => 'Šobrīd man ir daudz sarunu 🙈 Lūdzu, pamēģiniet pēc brīža vai rakstiet mums: ' . $contact,
			'too_long'    => 'Ziņa ir pārāk gara — lūdzu, saīsiniet to līdz ' . self::MAX_LEN . ' rakstzīmēm.',
			'max_turns'   => 'Šī saruna kļuvusi gara 🙂 Lai atbildētu precīzāk, lūdzu, rakstiet mums: ' . $contact,
			'disabled'    => 'Čats pašlaik nav pieejams. Rakstiet mums: ' . $contact,
		);
		return $map[ $code ] ?? 'Atvainojiet, pašlaik nevaru atbildēt. Lūdzu, pamēģiniet vēlreiz vai rakstiet mums: ' . $contact;
	}

	private static function error( $code, $status = 200 ) {
		return new WP_REST_Response( array( 'error' => $code, 'text' => self::friendly( $code ) ), $status );
	}

	public static function handle( WP_REST_Request $request ) {
		nocache_headers();
		if ( 'yes' !== MarMar_Chat_Settings::get( 'enabled' ) && ! current_user_can( 'manage_options' ) ) {
			return self::error( 'disabled' );
		}

		$text = trim( wp_strip_all_tags( (string) $request->get_param( 'message' ) ) );
		if ( '' === $text ) {
			return self::error( 'empty', 400 );
		}
		if ( mb_strlen( $text ) > self::MAX_LEN ) {
			return self::error( 'too_long' );
		}

		// Лимиты: на IP в час и на весь сайт в сутки.
		$ip      = self::ip_hash();
		$ip_key  = 'marmar_chat_ip_' . $ip;
		$day_key = 'marmar_chat_day_' . gmdate( 'Ymd' );
		$ip_n    = (int) get_transient( $ip_key );
		$day_n   = (int) get_option( $day_key, 0 );
		if ( $ip_n >= (int) MarMar_Chat_Settings::get( 'ip_limit' ) || $day_n >= (int) MarMar_Chat_Settings::get( 'daily_limit' ) ) {
			return self::error( 'busy' );
		}

		$cid = preg_replace( '/[^A-Za-z0-9]/', '', (string) $request->get_param( 'cid' ) );
		$row = $cid ? MarMar_Chat_Store::get( $cid ) : null;
		if ( $row && $row->ip_hash !== $ip ) {
			$row = null; // чужой разговор не продолжаем
		}
		if ( ! $row ) {
			$row = MarMar_Chat_Store::create( (string) $request->get_param( 'page' ), $ip );
		}
		if ( (int) $row->turns >= (int) MarMar_Chat_Settings::get( 'max_turns' ) ) {
			return self::error( 'max_turns' );
		}

		set_transient( $ip_key, $ip_n + 1, HOUR_IN_SECONDS );
		update_option( $day_key, $day_n + 1, false );
		delete_option( 'marmar_chat_day_' . gmdate( 'Ymd', time() - 2 * DAY_IN_SECONDS ) );

		$history   = $row->messages;
		$history[] = array( 'role' => 'user', 'content' => array( array( 'type' => 'text', 'text' => $text ) ) );

		$result = MarMar_Chat_Claude::reply( self::api_history( $history ) );

		if ( ! $result['ok'] ) {
			// Ошибку тоже сохраняем, чтобы в админке было видно, что клиент спрашивал.
			$history[] = array( 'role' => 'assistant', 'content' => array( array( 'type' => 'text', 'text' => '[' . $result['error'] . '] ' . self::friendly( $result['error'] ) ) ), 'error' => true );
			MarMar_Chat_Store::save( $row, $history, $result['usage'] + array( 'input' => 0, 'output' => 0, 'cost' => 0 ) );
			return new WP_REST_Response( array( 'cid' => $row->cid, 'error' => $result['error'], 'text' => self::friendly( $result['error'] ) ), 200 );
		}

		$history[] = array( 'role' => 'assistant', 'content' => $result['content'] );
		MarMar_Chat_Store::save( $row, $history, $result['usage'] );
		if ( 1 === wp_rand( 1, 50 ) ) {
			MarMar_Chat_Store::cleanup();
		}

		// Маркеры [[p:ID]] → карточки товаров.
		$products = array();
		$clean    = preg_replace_callback(
			'/\[\[p:(\d+)\]\]/',
			static function ( $m ) use ( &$products ) {
				$card = MarMar_Chat_Catalog::card( (int) $m[1] );
				if ( $card && count( $products ) < 4 && ! isset( $products[ $card['id'] ] ) ) {
					$products[ $card['id'] ] = $card;
				}
				return '';
			},
			$result['text']
		);
		$clean = trim( preg_replace( "/\n{3,}/", "\n\n", $clean ) );

		return new WP_REST_Response(
			array(
				'cid'      => $row->cid,
				'text'     => $clean,
				'products' => array_values( $products ),
			),
			200
		);
	}

	/**
	 * Для API: история без неудачных обменов (вопрос, на который не было ответа, и заглушка
	 * ошибки выпадают парой — так роли всегда чередуются, а успешная часть истории не меняется).
	 */
	public static function api_history( array $messages ) {
		$out = array();
		foreach ( $messages as $m ) {
			if ( ! empty( $m['error'] ) ) {
				if ( $out && 'user' === end( $out )['role'] ) {
					array_pop( $out );
				}
				continue;
			}
			$out[] = array( 'role' => $m['role'], 'content' => $m['content'] );
		}
		return $out;
	}
}
