<?php
defined( 'ABSPATH' ) || exit;

class MarMar_Wedding_Settings {

	const OPTION = 'marmar_wedding_settings';
	const META   = '_marmar_wedding';
	/** Повод конструктора: kazas (по умолчанию), kristibas, korporativs. 1.5.0, 23.11.2026. */
	const PROFILE_META = '_marmar_wedding_profile';

	/** @var string текущий повод (ставится по товару перед выводом/проверкой) */
	private static $profile = 'kazas';

	/** Тексты, которые отличаются у поводов. Ключи без значения берутся из общих настроек (свадьба). */
	const PROFILES = array(
		'kristibas'   => array(
			'title'        => 'Kristības',
			'event_label'  => 'Kristību datums',
			'name_label'   => 'Bērna vārds (vai teksts)',
			'name_ph'      => 'Emīlija',
			'date2_ph'     => '12.06.2027',
			'upload_label' => 'Ielūgums, gatavs dizains vai iedvesmas attēls',
			'min_msg'      => 'Minimālais svecīšu daudzums ir %d gab.',
			'date_err'     => 'Lūdzu, norādiet kristību datumu.',
			'default_qty'  => 20,
			'ribbons'      => "Balta: #ffffff\nKrēmkrāsa: #f3e9d2\nPūderrozā: #e8c4c0\nGaiši zila: #c9dcef\nZila: #8fb3d9\nLavandas: #b9a7d6\nEikalipta zaļa: #9db4a0\nZelta: #c9a64b\nSudraba: #c0c0c0",
			'terms'        => "Maketu atsūtīsim 3 darba dienu laikā pēc apmaksas\nIzgatavošana — ap 2 nedēļām pēc maketa apstiprināšanas\nIesakām pasūtīt 4–6 nedēļas pirms kristībām",
			'reviews'      => '',
			'wa_text'      => 'Labdien! Man ir jautājums par svecēm kristībām.',
			'cross_title'  => 'Sveces kristību tortei',
			'cross_ids'    => '20440,24744,26097,11212',
		),
		'korporativs' => array(
			'title'        => 'Korporatīvās dāvanas',
			'event_label'  => 'Pasākuma vai dāvināšanas datums',
			'name_label'   => 'Teksts (uzņēmuma nosaukums vai novēlējums)',
			'name_ph'      => 'Gaišus svētkus! — SIA Piemērs',
			'date2_label'  => 'Papildu teksts vai gads',
			'date2_ph'     => '2027',
			'pers_tag'     => 'Birka ar tekstu',
			'pers_sticker' => 'Uzlīme ar uzņēmuma logo',
			'upload_label' => 'Uzņēmuma logo (vēlams PNG vai PDF augstā kvalitātē)',
			'min_msg'      => 'Minimālais daudzums ir %d gab.',
			'date_err'     => 'Lūdzu, norādiet pasākuma vai dāvināšanas datumu.',
			'default_qty'  => 30,
			'terms'        => "Maketu atsūtīsim 3 darba dienu laikā pēc apmaksas\nIzgatavošana — ap 2 nedēļām pēc maketa apstiprināšanas\nRēķins uzņēmumam: noformējot pasūtījumu, izvēlieties «Uzņēmums» un norādiet rekvizītus\nLielākiem apjomiem (100+ gab.) termiņu saskaņosim individuāli",
			'reviews'      => '',
			'wa_text'      => 'Labdien! Man ir jautājums par korporatīvajām dāvanām — svecēm ar logo.',
			'cross_ids'    => '',
			'lead_time'    => 'Izgatavošana parasti aizņem 2 nedēļas pēc maketa apstiprināšanas. Norādiet, līdz kuram datumam dāvanas vajadzīgas, — vadīsimies pēc tā.',
			'process'      => "Noformējiet pasūtījumu un apmaksājiet internetbankā vai ar pārskaitījumu pēc rēķina (rēķins uz uzņēmumu).\n3 darba dienu laikā pēc apmaksas atsūtīsim uzlīmes vai birkas maketu ar Jūsu logo.\nJūs apskatāt maketu un to apstiprināt vai lūdzat labojumus.\nPēc apstiprināšanas izgatavojam sveces (parasti 2 nedēļās) un nosūtām uz izvēlēto pakomātu.",
		),
	);

	public static function profiles() {
		return array( 'kazas' => 'Kāzas' ) + array_map( function ( $p ) { return $p['title']; }, self::PROFILES );
	}

	public static function set_profile( $p ) {
		self::$profile = isset( self::PROFILES[ $p ] ) ? $p : 'kazas';
	}

	public static function profile() {
		return self::$profile;
	}

	/** Ставит повод по товару (или его родителю). */
	public static function use_product( $product ) {
		$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
		$id      = $product ? ( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id() ) : 0;
		self::set_profile( $id ? (string) get_post_meta( $id, self::PROFILE_META, true ) : '' );
	}

	public static function defaults() {
		return array(
			'min_qty'  => 10,
			// «от N шт.: цена за штуку», по одному в строке.
			'tiers'    => "10: 5\n20: 4.5\n30: 4\n50: 3.5\n100: 3",
			// «Название | URL картинки», по одному в строке.
			'types'    => "Svece burciņā (20 ml)\nMini Bubble svecīte",
			// Количество, которое стоит в конструкторе при открытии страницы.
			'default_qty' => 30,
			// «Название | короткая подпись под карточкой», по одному в строке.
			'type_notes' => "Svece burciņā (20 ml) | Stikla burciņa ar vāciņu · deg ~1 stundu\nMini Bubble svecīte | ~3 × 4 cm · deg ~1 stundu",
			// Общая строка под карточками свечей.
			'facts'    => 'Dabīgs vasks, bez aromāta. Katru sveci lejam ar rokām Rīgā.',
			// Строка о доставке под таблицей цен.
			'ship_note' => 'Piegāde uz Unisend vai Venipak pakomātu šim pasūtījumam — bez maksas.',
			// Сроки под кнопкой «в корзину».
			'terms'    => "Maketu atsūtīsim 3 darba dienu laikā pēc apmaksas\nIzgatavošana — ap 2 nedēļām pēc maketa apstiprināšanas\nIesakām pasūtīt 4–8 nedēļas pirms kāzām",
			// Отзывы под конструктором, по одному в строке.
			'reviews'  => "Vienkārši nevarēju nociesties pateikt milzīgu PALDIES par ko tik skaistu, īpašu un smalku! Tā izcili iederēsies mūsu svinībās.\nLiels paldies, dāvaniņas izskatās burvīgi!\nSveiki. Šodien saņēmām svecītes. Superīgas! Paldies!",
			// Товары «к свадебному торту» под конструктором: ID через запятую; пусто — блок не показываем.
			'cross_ids' => '22782,21156,18802,11212',
			// Номер WhatsApp для вопросов (только цифры); пусто — строку не показываем.
			'whatsapp' => '37129710414',
			// Примеры персонализации: «tag | URL» и «sticker | URL».
			'pers_images' => '',
			// «Название: #цвет», по одному в строке.
			'ribbons'  => "Balta: #ffffff\nKrēmkrāsa: #f3e9d2\nPūderrozā: #e8c4c0\nPutekļaini rozā: #c99a96\nBordo: #6d1f2f\nLavandas: #b9a7d6\nZila: #8fb3d9\nEikalipta zaļa: #9db4a0\nOlīvzaļa: #6b7350\nZelta: #c9a64b\nSudraba: #c0c0c0\nMelna: #222222",
			'intro'    => '', // краткое описание товара уже объясняет конструктор
			'lead_time' => 'Izgatavošana parasti aizņem 2 nedēļas pēc maketa apstiprināšanas. Norādiet, līdz kuram datumam vēlaties saņemt sveces, — vadīsimies pēc tā.',
			// Шаги «Kā notiek pasūtījums», по одному в строке.
			// Подписи полей (свадьба). Другие поводы переопределяют их в PROFILES.
			'event_label'  => 'Kāzu datums',
			'name_label'   => 'Teksts (piem., vārdi)',
			'name_ph'      => 'Anna & Jānis',
			'date2_label'  => 'Datums uz birkas/uzlīmes',
			'date2_ph'     => '12.06.2027',
			'pers_tag'     => 'Birka ar tekstu',
			'pers_sticker' => 'Individuāla uzlīme (dizains pēc Jūsu ielūguma vai stila)',
			'upload_label' => 'Ielūgums, gatavs dizains vai iedvesmas attēls',
			'min_msg'      => 'Minimālais kāzu svecīšu daudzums ir %d gab.',
			'date_err'     => 'Lūdzu, norādiet kāzu datumu.',
			'wa_text'      => 'Labdien! Man ir jautājums par kāzu svecēm viesiem.',
			'reviews_title' => 'Ko saka līgavas, saņemot pasūtījumu',
			'cross_title'  => 'Sveces kāzu tortei',
			'process'  => "Noformējiet pasūtījumu un apmaksājiet uzreiz internetbankā vai ar pārskaitījumu pēc rēķina.\n3 darba dienu laikā pēc apmaksas atsūtīsim birkas vai uzlīmes maketu, lai Jūs redzētu gatavo dizainu.\nJūs apskatāt maketu un to apstiprināt vai lūdzat labojumus.\nPēc apstiprināšanas izgatavojam sveces (parasti 2 nedēļās) un nosūtām uz izvēlēto pakomātu.",
		);
	}

	public static function all() {
		$s   = get_option( self::OPTION, array() );
		$all = wp_parse_args( is_array( $s ) ? $s : array(), self::defaults() );
		if ( isset( self::PROFILES[ self::$profile ] ) ) {
			$all = array_merge( $all, self::PROFILES[ self::$profile ] );
		}
		return $all;
	}

	public static function get( $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	private static function lines( $key ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) self::get( $key ) ) ) ) );
	}

	/** @return array<int,float> qty_from => price, по возрастанию */
	public static function tiers() {
		$out = array();
		foreach ( self::lines( 'tiers' ) as $line ) {
			if ( preg_match( '/^(\d+)\s*[:=]\s*([\d.,]+)/', $line, $m ) ) {
				$out[ (int) $m[1] ] = (float) str_replace( ',', '.', $m[2] );
			}
		}
		ksort( $out );
		return $out;
	}

	public static function min_qty() {
		return max( 1, (int) self::get( 'min_qty' ) );
	}

	public static function default_qty() {
		return max( self::min_qty(), (int) self::get( 'default_qty' ) );
	}

	/** @return array<string,string> название вида => подпись */
	public static function type_notes() {
		$out = array();
		foreach ( self::lines( 'type_notes' ) as $l ) {
			$p = array_map( 'trim', explode( '|', $l, 2 ) );
			if ( isset( $p[1] ) ) {
				$out[ $p[0] ] = $p[1];
			}
		}
		return $out;
	}

	public static function list_of( $key ) {
		return self::lines( $key );
	}

	public static function unit_price( $qty ) {
		$price = null;
		foreach ( self::tiers() as $from => $p ) {
			if ( $qty >= $from ) {
				$price = $p;
			}
		}
		if ( null === $price ) {
			$tiers = self::tiers();
			$price = $tiers ? reset( $tiers ) : 0;
		}
		return (float) $price;
	}

	/** Строка «Название | URL картинки» → название. */
	public static function types() {
		return array_map( function ( $l ) { return trim( explode( '|', $l, 2 )[0] ); }, self::lines( 'types' ) );
	}

	/** @return array<string,string> название => URL картинки (может быть пустым) */
	public static function type_images() {
		$out = array();
		foreach ( self::lines( 'types' ) as $l ) {
			$p = array_map( 'trim', explode( '|', $l, 2 ) );
			$out[ $p[0] ] = isset( $p[1] ) ? esc_url_raw( $p[1] ) : '';
		}
		return $out;
	}

	/** @return array<string,string> tag|sticker => URL примера */
	public static function pers_images() {
		$out = array();
		foreach ( self::lines( 'pers_images' ) as $l ) {
			$p = array_map( 'trim', explode( '|', $l, 2 ) );
			if ( isset( $p[1] ) ) {
				$out[ $p[0] ] = esc_url_raw( $p[1] );
			}
		}
		return $out;
	}

	/** @return array<string,string> name => hex */
	public static function ribbons() {
		$out = array();
		foreach ( self::lines( 'ribbons' ) as $line ) {
			$parts = array_map( 'trim', explode( ':', $line, 2 ) );
			$hex   = isset( $parts[1] ) && preg_match( '/^#[0-9a-f]{3,6}$/i', $parts[1] ) ? $parts[1] : '#eeeeee';
			$out[ $parts[0] ] = $hex;
		}
		return $out;
	}

	public static function personalisations() {
		return array(
			'none'    => 'Bez personalizācijas',
			'tag'     => (string) self::get( 'pers_tag' ),
			'sticker' => (string) self::get( 'pers_sticker' ),
		);
	}

	public static function is_wedding( $product ) {
		$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
		if ( ! $product ) {
			return false;
		}
		$id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		return 'yes' === get_post_meta( $id, self::META, true );
	}
}
