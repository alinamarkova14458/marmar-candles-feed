<?php
defined( 'ABSPATH' ) || exit;

class MarMar_Wedding_Settings {

	const OPTION = 'marmar_wedding_settings';
	const META   = '_marmar_wedding';

	public static function defaults() {
		return array(
			'min_qty'  => 10,
			// «от N шт.: цена за штуку», по одному в строке.
			'tiers'    => "10: 5\n20: 4.5\n30: 4\n50: 3.5\n100: 3",
			// «Название | URL картинки», по одному в строке.
			'types'    => "Svece burciņā (20 ml)\nMini Bubble svecīte",
			// Примеры персонализации: «tag | URL» и «sticker | URL».
			'pers_images' => '',
			// «Название: #цвет», по одному в строке.
			'ribbons'  => "Balta: #ffffff\nKrēmkrāsa: #f3e9d2\nPūderrozā: #e8c4c0\nPutekļaini rozā: #c99a96\nBordo: #6d1f2f\nLavandas: #b9a7d6\nZila: #8fb3d9\nEikalipta zaļa: #9db4a0\nOlīvzaļa: #6b7350\nZelta: #c9a64b\nSudraba: #c0c0c0\nMelna: #222222",
			'intro'    => '', // краткое описание товара уже объясняет конструктор
			'lead_time' => 'Izgatavošana parasti aizņem 2 nedēļas pēc maketa apstiprināšanas. Norādiet, līdz kuram datumam vēlaties saņemt sveces, — vadīsimies pēc tā.',
			// Шаги «Kā notiek pasūtījums», по одному в строке.
			'process'  => "Noformējiet pasūtījumu — mēs atsūtīsim rēķinu e-pastā.\n3 darba dienu laikā pēc apmaksas atsūtīsim birkas vai uzlīmes maketu, lai Jūs redzētu gatavo dizainu.\nJūs apskatāt maketu un to apstiprināt vai lūdzat labojumus.\nPēc apstiprināšanas izgatavojam sveces (parasti 2 nedēļās) un nosūtām uz izvēlēto pakomātu.",
		);
	}

	public static function all() {
		$s = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $s ) ? $s : array(), self::defaults() );
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
			'tag'     => 'Birka ar tekstu',
			'sticker' => 'Individuāla uzlīme (dizains pēc Jūsu ielūguma vai stila)',
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
