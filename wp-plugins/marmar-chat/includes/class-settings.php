<?php
defined( 'ABSPATH' ) || exit;

class MarMar_Chat_Settings {

	const OPTION = 'marmar_chat_settings';

	/** Цены за 1 млн токенов (USD) для оценки расходов в админке. */
	const PRICES = array(
		'claude-opus-5-5'   => array( 'in' => 4.0, 'out' => 20.0, 'cache_read' => 0.20 ),
		'claude-sonnet-5-5' => array( 'in' => 2.0, 'out' => 10.0, 'cache_read' => 0.20 ),
		'claude-haiku-5-5'  => array( 'in' => 0.10, 'out' => 0.50, 'cache_read' => 0.01 ),
	);

	public static function defaults() {
		return array(
			'enabled'        => 'no',
			'api_key'        => '',
			'model'          => 'claude-opus-5-5',
			'effort'         => 'low',
			'daily_limit'    => 300,   // сообщений клиентов в сутки на весь сайт
			'ip_limit'       => 30,    // сообщений в час с одного IP
			'max_turns'      => 25,    // сообщений в одном разговоре
			'launcher_text'  => '✨ Palīdzēšu izvēlēties sveces',
			'greeting'       => "Sveiki! Esmu MarMar CANDLES palīgs 🕯️\nPastāstiet, kam meklējat sveces — dzimšanas dienai, kāzām vai Ziemassvētkiem — un palīdzēšu izvēlēties.",
			'chips'          => "Sveces dzimšanas dienai\nCiparu sveces\nZiemassvētku dāvanas\nKāzu sveces\nPiegāde un termiņi",
			'disclaimer'     => 'Atbildes sagatavo mākslīgais intelekts, tās var kļūdīties. Sarunas tiek saglabātas, lai uzlabotu apkalpošanu.',
			'position'       => 'left',
			'facts'          => self::default_facts(),
		);
	}

	public static function default_facts() {
		return <<<TXT
- MarMar CANDLES is a small handmade candle brand from Riga, Latvia. Every candle is made by hand.
- Main products: cake and birthday candles of unusual shapes, number candles, cake toppers, wedding candles for guests, Christmas/New Year candles and gifts, interior and gift candles, gift cards, an individual online candle-making course.
- Minimum order: 5 € (excluding delivery). There are no discounts or promo codes — never offer or promise them.
- Production time after payment: 5–7 working days. Urgent order: +5 €, production in 1–2 working days (the customer ticks "Steidzams pasūtījums" at checkout).
- Delivery in Latvia only to parcel lockers: Omniva, DPD, Venipak, Unisend. Delivery usually takes 1–2 working days after we hand over the parcel. There is no courier delivery.
- Christmas 2026: to receive before Christmas, order and pay by 8 December (urgent order: by 15 December). For New Year: by 14 December (urgent: by 21 December).
- Weddings: personalised wedding candles for guests (names, date, colours) from 3 € per piece. Corporate orders with personalisation are possible. For personalised, wedding and corporate orders the customer should write to us: info@marmarcandles.lv or Instagram @marmar.candles.
- Contacts: e-mail info@marmarcandles.lv, phone/WhatsApp +371 29710414, Instagram @marmar.candles.
- Payment and order status questions, changes to an existing order, complaints: ask the customer to write to info@marmarcandles.lv with the order number.
TXT;
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	public static function update( array $values ) {
		update_option( self::OPTION, array_merge( self::all(), $values ), false );
	}

	/** Ключ: константа в wp-config.php имеет приоритет над настройкой. */
	public static function api_key() {
		if ( defined( 'MARMAR_CHAT_API_KEY' ) && MARMAR_CHAT_API_KEY ) {
			return (string) MARMAR_CHAT_API_KEY;
		}
		return (string) self::get( 'api_key' );
	}

	public static function estimate_cost( $model, $in, $out, $cache_read, $cache_write ) {
		$p = self::PRICES[ $model ] ?? self::PRICES['claude-opus-5-5'];
		return ( $in * $p['in'] + $cache_write * $p['in'] * 1.25 + $cache_read * $p['cache_read'] + $out * $p['out'] ) / 1000000;
	}
}
