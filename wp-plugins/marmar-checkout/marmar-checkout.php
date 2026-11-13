<?php
/**
 * Plugin Name:       MarMar Checkout
 * Description:       Аккуратная корзина и оформление: условия карточками вместо стены текста, срочный заказ над кнопкой «Tālāk», полоска до бесплатной доставки, ориентировочная дата получения, строка доверия у кнопки заказа, латышские переводы недостающих фраз WooCommerce.
 * Version:           1.3.3
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Checkout {

	const VERSION = '1.3.3';

	/** Настройки (позже можно вынести в админку). */
	public static function cfg() {
		return apply_filters(
			'marmar_checkout_config',
			array(
				'free_shipping_from' => 50,
				'min_order'          => 5,   // должен совпадать со сниппетом «Minimum Order 5 EUR»
				'make_days'          => array( 5, 7 ),  // изготовление, КАЛЕНДАРНЫЕ дни (мастерская работает и в выходные)
				'make_days_express'  => array( 1, 2 ),  // срочное изготовление, календарные дни
				'ship_days'          => array( 1, 2 ),  // доставка в пакомат
				'express_fee_name'   => 'Steidzams pasūtījums',
			)
		);
	}

	/**
	 * Visi piegādes veidi ir pakomāti vai saņemšana darbnīcā (kurjera nav) — adrese, pilsēta, indekss un novads
	 * nav vajadzīgi. Paslēpjam tos visām valstīm (LV, LT, EE). Kontaktiem paliek e-pasts, vārds, uzvārds, tālrunis.
	 * Atslēgt: MarMar Checkout iestatījumos nav — vienkārši izņemt šo filtru (vai MMCO_NEED_ADDRESS = true).
	 */
	public static function hide_state_lv( $locale ) {
		if ( defined( 'MMCO_NEED_ADDRESS' ) && MMCO_NEED_ADDRESS ) {
			return $locale;
		}
		$keys = array( 'address_1', 'address_2', 'city', 'state', 'postcode' );
		foreach ( array( 'LV', 'LT', 'EE' ) as $cc ) {
			foreach ( $keys as $k ) {
				$locale[ $cc ][ $k ] = array_merge( isset( $locale[ $cc ][ $k ] ) ? (array) $locale[ $cc ][ $k ] : array(), array( 'required' => false, 'hidden' => true ) );
			}
		}
		return $locale;
	}

	/** Pakomāta adrese → pasūtījuma piegādes (un tukšā rēķina) adrese. Nekad nepārraksta jau aizpildītu adresi. */
	public static function fill_address( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order || '' !== trim( (string) $order->get_shipping_address_1() ) ) {
			return;
		}
		$a = array( 'address_1' => '', 'address_2' => '', 'city' => '', 'postcode' => '' );

		$uni = (string) $order->get_meta( '_woo_lithuaniapost_lpexpress_terminal' );
		$ven = $order->get_meta( '_venipak_pickup_point_data' );
		$dpd = (string) $order->get_meta( 'wc_shipping_dpd_parcels_terminal_name' );
		if ( '' === $dpd ) {
			$dpd = (string) $order->get_meta( '_wc_shipping_dpd_parcels_terminal_name' );
		}
		if ( '' !== $uni ) { // "Nosaukums - Iela 1, Pilsēta"
			$parts          = explode( ' - ', $uni, 2 );
			$street_city    = isset( $parts[1] ) ? $parts[1] : $uni;
			$pos            = strrpos( $street_city, ',' );
			$a['address_1'] = trim( false !== $pos ? substr( $street_city, 0, $pos ) : $street_city );
			$a['city']      = trim( false !== $pos ? substr( $street_city, $pos + 1 ) : '' );
			$a['address_2'] = 'Unisend: ' . trim( $parts[0] );
		} elseif ( $ven ) {
			$v              = is_string( $ven ) ? json_decode( $ven, true ) : (array) $ven;
			$a['address_1'] = isset( $v['address'] ) ? (string) $v['address'] : '';
			$a['city']      = isset( $v['city'] ) ? (string) $v['city'] : '';
			$a['postcode']  = isset( $v['zip'] ) && '' !== (string) $v['zip'] ? ( ctype_digit( (string) $v['zip'] ) ? 'LV-' . $v['zip'] : (string) $v['zip'] ) : '';
			$a['address_2'] = 'Venipak: ' . ( isset( $v['display_name'] ) ? (string) $v['display_name'] : '' );
		} elseif ( '' !== $dpd ) { // "Nosaukums,IELA 1"
			$parts          = explode( ',', $dpd, 2 );
			$a['address_1'] = trim( isset( $parts[1] ) ? $parts[1] : $dpd );
			$a['address_2'] = 'DPD: ' . trim( $parts[0] );
		} else {
			foreach ( $order->get_shipping_methods() as $m ) {
				if ( false !== strpos( $m->get_method_id(), 'free_shipping' ) || false !== stripos( $m->get_name(), 'Iļģuciem' ) ) {
					$a = array( 'address_1' => 'Jaunsaules iela 7a', 'address_2' => 'Saņemšana darbnīcā', 'city' => 'Rīga', 'postcode' => '' );
				} elseif ( '' === $a['address_1'] ) {
					$a['address_1'] = $m->get_name();
				}
			}
		}
		if ( '' === $a['address_1'] ) {
			return;
		}
		foreach ( $a as $k => $v ) {
			$order->{'set_shipping_' . $k}( $v );
			if ( '' === trim( (string) $order->{'get_billing_' . $k}() ) && 'address_2' !== $k ) {
				$order->{'set_billing_' . $k}( $v );
			}
		}
		if ( ! $order->get_shipping_country() ) {
			$order->set_shipping_country( 'LV' );
		}
		$order->add_order_note( 'MarMar: adrese aizpildīta no izvēlētā pakomāta / saņemšanas vietas.' );
		$order->save();
	}

	public static function init() {
		add_shortcode( 'marmar_cart_info', array( __CLASS__, 'cart_info' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 30 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'i18n' ), 5 );
		add_filter( 'gettext_woocommerce', array( __CLASS__, 'gettext' ), 10, 2 );
		// Latvijā lauks «Novads» nav vajadzīgs (nav obligāts) — paslēpjam, lai forma īsāka.
		add_filter( 'woocommerce_get_country_locale', array( __CLASS__, 'hide_state_lv' ) );
		// Adrese oformēšanā paslēpta — pasūtījumā ierakstām izvēlētā pakomāta adresi (uzlīmei un rēķinam).
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'fill_address' ), 999 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'fill_address' ), 999 );
		add_action( 'woocommerce_order_status_on-hold', array( __CLASS__, 'fill_address' ), 5 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'fill_address' ), 5 );
		// Минимальный заказ (заменяет сниппет WPCode «Minimum Order 5 EUR»).
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_minimum' ) );
		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'check_minimum' ) );
		// Переключатель срочного заказа на оформлении: обновление корзины без перезагрузки (Store API).
		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'register_express_update' ) );
	}

	private static function on_cart_or_checkout() {
		return function_exists( 'is_cart' ) && ( is_cart() || ( is_checkout() && ! is_order_received_page() ) );
	}

	/**
	 * Флаг срочного заказа хранится в сессии под тем же ключом, что использует сниппет «+5eur»
	 * (он и добавляет плату 5 € при пересчёте корзины).
	 */
	public static function register_express_update() {
		if ( ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			return;
		}
		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => 'marmar-express',
				'callback'  => function ( $data ) {
					if ( WC()->session ) {
						WC()->session->set( 'marmar_express_order', ! empty( $data['express'] ) );
					}
				},
			)
		);
	}

	/** Сумма товаров с НДС, без доставки и доплат. */
	public static function items_total() {
		if ( ! WC()->cart ) {
			return 0.0;
		}
		return (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_subtotal_tax();
	}

	public static function check_minimum() {
		if ( ! WC()->cart || WC()->cart->is_empty() || function_exists( 'marmar_minimum_order_amount' ) ) {
			return; // старый сниппет ещё включён — не дублируем сообщение
		}
		$min   = (float) self::cfg()['min_order'];
		$total = self::items_total();
		if ( $total < $min ) {
			wc_add_notice(
				sprintf(
					'Minimālā pasūtījuma summa ir %1$s (bez piegādes). Pievienojiet vēl preces par %2$s 🕯️',
					wp_strip_all_tags( wc_price( $min ) ),
					wp_strip_all_tags( wc_price( $min - $total ) )
				),
				'error'
			);
		}
	}

	/* ---------------- Даты ---------------- */

	/** Латвийские праздники, когда мастерская и пакоматы не работают как обычно. */
	private static function holidays( $year ) {
		$fixed = array( '01-01', '05-01', '05-04', '06-23', '06-24', '11-18', '12-24', '12-25', '12-26', '12-31' );
		$out   = array();
		foreach ( $fixed as $md ) {
			$out[] = $year . '-' . $md;
		}
		// Пасха (Великая пятница и понедельник).
		$easter = easter_date_safe( $year );
		$out[]  = gmdate( 'Y-m-d', $easter - 2 * DAY_IN_SECONDS );
		$out[]  = gmdate( 'Y-m-d', $easter + DAY_IN_SECONDS );
		return $out;
	}

	private static function add_workdays( DateTimeImmutable $d, $n ) {
		$h = array_merge( self::holidays( (int) $d->format( 'Y' ) ), self::holidays( (int) $d->format( 'Y' ) + 1 ) );
		while ( $n > 0 ) {
			$d = $d->modify( '+1 day' );
			if ( (int) $d->format( 'N' ) < 6 && ! in_array( $d->format( 'Y-m-d' ), $h, true ) ) {
				$n--;
			}
		}
		return $d;
	}

	/** Atvaļinājums: šīs dienas neskaitās izgatavošanā (pasūtījumus pieņemam). */
	const VACATIONS = array( array( '2026-11-26', '2026-12-03' ) );

	/** Izgatavošanas kalendārās dienas, izlaižot atvaļinājuma dienas. */
	private static function add_make_days( DateTimeImmutable $d, $n ) {
		while ( $n > 0 ) {
			$d   = $d->modify( '+1 day' );
			$ymd = $d->format( 'Y-m-d' );
			$vac = false;
			foreach ( self::VACATIONS as $v ) {
				if ( $ymd >= $v[0] && $ymd <= $v[1] ) {
					$vac = true;
				}
			}
			if ( ! $vac ) {
				$n--;
			}
		}
		return $d;
	}

	/** @return array{from:string,to:string,express:bool} */
	public static function estimate( $express ) {
		$c    = self::cfg();
		$make = $express ? $c['make_days_express'] : $c['make_days'];
		$now  = new DateTimeImmutable( 'now', wp_timezone() );
		// Изготовление — календарные дни, доставка в пакомат — рабочие.
		$from = self::add_workdays( self::add_make_days( $now, (int) $make[0] ), $c['ship_days'][0] );
		$to   = self::add_workdays( self::add_make_days( $now, (int) $make[1] ), $c['ship_days'][1] );
		return array( 'from' => $from->format( 'Y-m-d' ), 'to' => $to->format( 'Y-m-d' ), 'express' => (bool) $express );
	}

	private static function lv_range( $from, $to ) {
		$m  = array( 1 => 'janvārī', 'februārī', 'martā', 'aprīlī', 'maijā', 'jūnijā', 'jūlijā', 'augustā', 'septembrī', 'oktobrī', 'novembrī', 'decembrī' );
		$f  = new DateTimeImmutable( $from );
		$t  = new DateTimeImmutable( $to );
		if ( $f->format( 'm' ) === $t->format( 'm' ) ) {
			return $f->format( 'j' ) . '.–' . $t->format( 'j' ) . '. ' . $m[ (int) $t->format( 'n' ) ];
		}
		return $f->format( 'j' ) . '. ' . $m[ (int) $f->format( 'n' ) ] . ' – ' . $t->format( 'j' ) . '. ' . $m[ (int) $t->format( 'n' ) ];
	}

	/* ---------------- Шорткод условий в корзине ---------------- */

	public static function cart_info() {
		$c = self::cfg();
		ob_start();
		?>
		<div class="mmco-info">
			<div class="mmco-chips">
				<div class="mmco-chip"><span class="mmco-ico" aria-hidden="true">🕯️</span><span><strong>Gatavs <?php echo esc_html( $c['make_days'][0] . '–' . $c['make_days'][1] ); ?> dienās</strong><br><small>Steidzami — <?php echo esc_html( $c['make_days_express'][0] . '–' . $c['make_days_express'][1] ); ?> dienās (+5 €)</small></span></div>
				<div class="mmco-chip"><span class="mmco-ico" aria-hidden="true">📦</span><span><strong>Pakomātā no 2,50 €</strong><br><small>Bez maksas no <?php echo esc_html( $c['free_shipping_from'] ); ?> €</small></span></div>
				<div class="mmco-chip"><span class="mmco-ico" aria-hidden="true">🛒</span><span><strong>Minimālais pasūtījums <?php echo esc_html( $c['min_order'] ); ?> €</strong><br><small>bez piegādes</small></span></div>
				<div class="mmco-chip"><span class="mmco-ico" aria-hidden="true">📍</span><span><strong>Saņemšana Rīgā</strong><br><small>Iļģuciemā, saskaņojot laiku</small></span></div>
			</div>
			<details class="mmco-details">
				<summary>Visi piegādes un pasūtījuma noteikumi</summary>
				<ul>
					<li>Minimālā pasūtījuma summa — 5 € (bez piegādes).</li>
					<li>Piegāde uz Omniva, DPD, Venipak un Unisend pakomātiem visā Latvijā: 2,50–5,00 €; Unisend un Venipak no <?php echo esc_html( $c['free_shipping_from'] ); ?> € — bez maksas.</li>
					<li>Saņemšana Rīgā, Iļģuciemā (Jaunsaules iela 7a), iepriekš saskaņojot laiku.</li>
					<li>Izgatavošana: <?php echo esc_html( $c['make_days'][0] . '–' . $c['make_days'][1] ); ?> dienas pēc apmaksas (strādājam arī brīvdienās). Steidzams pasūtījums: +5 €, <?php echo esc_html( $c['make_days_express'][0] . '–' . $c['make_days_express'][1] ); ?> dienas. Piegāde uz pakomātu — 1–2 darba dienas.</li>
					<li>Sūtījuma izsekošanas saiti atsūtīsim e-pastā, kad pasūtījums būs ceļā.</li>
				</ul>
			</details>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------------- Скрипты ---------------- */

	public static function assets() {
		if ( ! self::on_cart_or_checkout() ) {
			return;
		}
		$url = plugin_dir_url( __FILE__ );
		wp_enqueue_style( 'marmar-checkout', $url . 'assets/checkout.css', array(), self::VERSION );
		wp_enqueue_script( 'marmar-checkout', $url . 'assets/checkout.js', array( 'wp-data' ), self::VERSION, array( 'in_footer' => true ) );

		$rating = class_exists( 'MarMar_Reviews_Badge' ) ? MarMar_Reviews_Badge::data() : null;
		$normal = self::estimate( false );
		$fast   = self::estimate( true );
		$c      = self::cfg();
		wp_localize_script(
			'marmar-checkout',
			'MarMarCheckout',
			array(
				'page'        => is_cart() ? 'cart' : 'checkout',
				'freeFrom'    => (float) $c['free_shipping_from'],
				'minOrder'    => (float) $c['min_order'],
				'feeName'     => $c['express_fee_name'],
				'estimate'    => self::lv_range( $normal['from'], $normal['to'] ),
				'estimateFast'=> self::lv_range( $fast['from'], $fast['to'] ),
				'rating'      => $rating ? array( 'score' => number_format( round( $rating['rating'], 1 ), 1, ',', '' ), 'count' => (int) $rating['count'] ) : null,
				'i18n'        => array(
					'left'      => 'Vēl %s līdz bezmaksas piegādei',
					'minLeft'   => 'Minimālais pasūtījums — %1$s. Pievienojiet vēl preces par %2$s',
					'free'      => 'Piegāde pakomātā — bez maksas! 🎉',
					'eta'       => 'Saņemsiet ~%s',
					'etaNote'   => 'pēc apmaksas saņemšanas',
					'handmade'  => 'Roku darbs Latvijā',
					'total'     => 'Kopā apmaksai:',
					'invoice'   => 'Apmaksa ar rēķinu',
					'noteLabel' => 'Komentārs pasūtījumam (nav obligāts)',
					'noteHint'  => 'Piemēram: vārds vai cipars uz sveces, vēlamais saņemšanas datums vai cita svarīga informācija',
					'express'   => 'Steidzams pasūtījums — +5 €',
					'expressSub'=> 'Izgatavošana 1–2 dienās — saņemsiet ~%s',
				),
			)
		);
	}

	/** Те же недостающие переводы для строк, которые WooCommerce формирует на сервере (подписи полей адреса). */
	public static function gettext( $translation, $text ) {
		static $map = array(
			'%s (optional)'                                      => '%s (nav obligāts)',
			'Select a %s'                                        => 'Izvēlieties: %s',
			'Enter a shipping address to view shipping options.' => 'Ievadiet piegādes adresi, lai redzētu piegādes iespējas.',
		);
		return ( $translation === $text && isset( $map[ $text ] ) ) ? $map[ $text ] : $translation;
	}

	/**
	 * Недостающие латышские переводы строк блочной корзины/оформления (JS-строки WooCommerce).
	 */
	public static function i18n() {
		if ( ! self::on_cart_or_checkout() ) {
			return;
		}
		$t = array(
			'Enter a shipping address to view shipping options.' => array( 'Ievadiet piegādes adresi, lai redzētu piegādes iespējas.' ),
			'Enter address to calculate'                         => array( 'Ievadiet adresi, lai aprēķinātu' ),
			'Select a %s'                                        => array( 'Izvēlieties: %s' ),
			'%s (optional)'                                      => array( '%s (nav obligāts)' ),
			'%d item'                                            => array( '%d prece', '%d preces', '%d preču' ),
			'Total price for %1$d %2$s item: %3$s'               => array( 'Kopējā cena par %1$d × %2$s: %3$s', 'Kopējā cena par %1$d × %2$s: %3$s', 'Kopējā cena par %1$d × %2$s: %3$s' ),
			'Remove item'                                        => array( 'Noņemt preci' ),
			'Free'                                               => array( 'Bez maksas' ),
			'Add a note to your order'                           => array( 'Komentārs pasūtījumam (nav obligāts)' ),
			'Notes about your order, e.g. special notes for delivery.' => array( 'Piemēram: vārds vai cipars uz sveces, vēlamais saņemšanas datums vai cita svarīga informācija' ),
			// _n(): ключ — форма единственного числа, значения — три латышские формы.
			'Please fix the following error before continuing'  => array( 'Lūdzu, izlabojiet šo kļūdu, lai turpinātu', 'Lūdzu, izlabojiet šīs kļūdas, lai turpinātu', 'Lūdzu, izlabojiet šīs kļūdas, lai turpinātu' ),
			'Please fix the following errors before continuing' => array( 'Lūdzu, izlabojiet šīs kļūdas, lai turpinātu' ),
		);
		$data = array( '' => array( 'domain' => 'woocommerce', 'lang' => 'lv', 'plural-forms' => 'nplurals=3; plural=(n%10==0 || (n%100>=11 && n%100<=19) ? 2 : (n%10==1 && n%100!=11 ? 0 : 1));' ) );
		foreach ( $t as $k => $v ) {
			$data[ $k ] = $v;
		}
		// Дополняем (не заменяем) уже загруженные переводы домена woocommerce.
		wp_add_inline_script( 'wp-i18n', 'wp.i18n.setLocaleData(' . wp_json_encode( $data ) . ', "woocommerce");', 'after' );
	}
}

if ( ! function_exists( 'easter_date_safe' ) ) {
	/** easter_date() есть не везде (нужно расширение calendar). */
	function easter_date_safe( $year ) {
		if ( function_exists( 'easter_date' ) ) {
			return easter_date( $year );
		}
		$a = $year % 19; $b = intdiv( $year, 100 ); $c = $year % 100; $d = intdiv( $b, 4 ); $e = $b % 4;
		$f = intdiv( $b + 8, 25 ); $g = intdiv( $b - $f + 1, 3 ); $h = ( 19 * $a + $b - $d - $g + 15 ) % 30;
		$i = intdiv( $c, 4 ); $k = $c % 4; $l = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7; $m = intdiv( $a + 11 * $h + 22 * $l, 451 );
		$month = intdiv( $h + $l - 7 * $m + 114, 31 ); $day = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;
		return gmmktime( 12, 0, 0, $month, $day, $year );
	}
}

add_action( 'plugins_loaded', function () {
	if ( class_exists( 'WooCommerce' ) ) {
		MarMar_Checkout::init();
	}
} );
