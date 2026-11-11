<?php
/**
 * «Mana mēneša kaste» — konditora ikmēneša pasūtījums.
 *
 * Nav abonements ar automātisku norakstīšanu: kondiors saglabā sastāvu, katra mēneša 20. datumā
 * saņem e-pastu (Apstiprināt / Mainīt / Izlaist). «Apstiprināt» ieliek kasti grozā un atver noformēšanu —
 * tālāk parasts pasūtījums ar rēķinu. Alīna 20. datumā saņem kopsavilkumu ražošanai.
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_KC_Box {

	const ENDPOINT = 'menesa-kaste';
	const U_BOX    = 'mm_kc_box';        // array( 'items' => [...], 'saved' => ts, 'active' => 1 )
	const U_SKIP   = 'mm_kc_box_skip';   // 'Y-m' mēnesis, kuru izlaist
	const OPT_SENT = 'mm_kc_box_sent';   // 'Y-m' kad nosūtīti atgādinājumi
	const DAY      = 20;

	public static function init() {
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'page' ) );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( __CLASS__, 'title' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );

		add_action( 'woocommerce_cart_actions', array( __CLASS__, 'cart_button' ) );
		add_action( 'wp_head', array( __CLASS__, 'no_sale_look' ), 99 );
		add_action( 'wp_loaded', array( __CLASS__, 'handle' ), 30 );
		add_filter( 'woocommerce_login_redirect', array( __CLASS__, 'login_redirect' ), 20 );

		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'flag_order' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'flag_order' ), 10, 1 );
		add_filter( 'render_block', array( __CLASS__, 'cart_block_button' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'admin_order_label' ) );

		add_action( 'mm_kc_daily', array( __CLASS__, 'daily' ) );
		if ( ! wp_next_scheduled( 'mm_kc_daily' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 07:00' ), 'daily', 'mm_kc_daily' );
		}
		add_action( 'admin_post_mm_kc_box_test', array( __CLASS__, 'admin_test' ) );
	}

	/** Konditora cena nav atlaide: grozā un kasē neslēdzam mazumtirdzniecības cenu un «Saglabāt …». */
	public static function no_sale_look() {
		if ( ! MarMar_Konditoru_Cenas::is_konditor() || ! ( is_cart() || is_checkout() ) ) {
			return;
		}
		echo '<style id="mmkb-nosale">.wc-block-components-product-badge,.wc-block-components-sale-badge,.wc-block-components-product-price__regular,.woocommerce-cart-form del,.woocommerce-checkout-review-order del{display:none!important}.wc-block-components-product-price__value.is-discounted{background:none!important;color:inherit!important;margin-left:0!important}</style>';
	}

	/* ---------------- Mans konts ---------------- */

	public static function query_vars( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	public static function maybe_flush() {
		if ( get_option( 'mm_kc_box_rewrite' ) !== MarMar_Konditoru_Cenas::VER ) {
			flush_rewrite_rules( false );
			update_option( 'mm_kc_box_rewrite', MarMar_Konditoru_Cenas::VER, false );
		}
	}

	public static function menu( $items ) {
		if ( ! MarMar_Konditoru_Cenas::is_konditor() ) {
			return $items;
		}
		$new = array();
		foreach ( $items as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'orders' === $k ) {
				$new[ self::ENDPOINT ] = 'Mana mēneša kaste';
			}
		}
		if ( ! isset( $new[ self::ENDPOINT ] ) ) {
			$new[ self::ENDPOINT ] = 'Mana mēneša kaste';
		}
		return $new;
	}

	public static function title() {
		return 'Mana mēneša kaste';
	}

	public static function url( $args = array() ) {
		return add_query_arg( $args, wc_get_account_endpoint_url( self::ENDPOINT ) );
	}

	public static function box( $uid ) {
		$b = get_user_meta( $uid, self::U_BOX, true );
		return is_array( $b ) && ! empty( $b['items'] ) ? $b : null;
	}

	/** Kastes rindas ar aktuālajām (konditora) cenām. */
	public static function lines( $box ) {
		$out = array();
		foreach ( $box['items'] as $it ) {
			$p = wc_get_product( $it['variation_id'] ? $it['variation_id'] : $it['product_id'] );
			if ( ! $p || ! $p->is_purchasable() ) {
				$out[] = array( 'name' => ( $p ? $p->get_name() : '#' . $it['product_id'] ) . ' — nav pieejams', 'qty' => $it['quantity'], 'sum' => 0, 'ok' => false, 'it' => $it );
				continue;
			}
			$name = $p->get_name();
			$extra = array();
			foreach ( (array) $it['extra'] as $k => $v ) {
				if ( is_scalar( $v ) && '' !== (string) $v ) {
					$extra[] = (string) $v;
				} elseif ( is_array( $v ) ) {
					$extra[] = implode( ', ', array_map( 'strval', $v ) );
				}
			}
			if ( $extra ) {
				$name .= ' (' . implode( '; ', $extra ) . ')';
			}
			$out[] = array( 'name' => $name, 'qty' => (int) $it['quantity'], 'sum' => (float) $p->get_price() * (int) $it['quantity'], 'ok' => true, 'it' => $it );
		}
		return $out;
	}

	public static function next_month_label() {
		$m = array( 1 => 'janvāris', 'februāris', 'marts', 'aprīlis', 'maijs', 'jūnijs', 'jūlijs', 'augusts', 'septembris', 'oktobris', 'novembris', 'decembris' );
		return $m[ (int) wp_date( 'n', strtotime( 'first day of next month', current_time( 'timestamp' ) ) ) ];
	}

	public static function page() {
		$uid = get_current_user_id();
		if ( ! MarMar_Konditoru_Cenas::is_konditor( $uid ) ) {
			echo '<p>Šī sadaļa ir pieejama apstiprinātiem konditoriem.</p>';
			return;
		}
		$box = self::box( $uid );
		echo '<div class="mmkb">';
		if ( isset( $_GET['mmkb'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$msgs = array(
				'saved'   => 'Mēneša kaste saglabāta. 20. datumā saņemsi e-pastu ar apstiprināšanu.',
				'skipped' => 'Nākamais mēnesis izlaists. Kaste paliek saglabāta.',
				'resumed' => 'Kaste atkal aktīva.',
				'paused'  => 'Kaste apturēta — atgādinājumi nenāks, līdz to atsāksi.',
				'deleted' => 'Mēneša kaste dzēsta.',
				'empty'   => 'Grozs ir tukšs — vispirms ieliec grozā sveces.',
			);
			$k = sanitize_key( wp_unslash( $_GET['mmkb'] ) ); // phpcs:ignore
			if ( isset( $msgs[ $k ] ) ) {
				echo '<div class="mmkc-msg">' . esc_html( $msgs[ $k ] ) . '</div>';
			}
		}
		if ( ! $box ) {
			echo '<h3 class="mmkb-head">Kā tas strādā' . self::help() . '</h3><ol>'; // phpcs:ignore
			echo '<li>Ieliec grozā sveces un ciparus, ko tev vajag katru mēnesi.</li>';
			echo '<li>Grozā nospied <strong>«Saglabāt kā mēneša kasti»</strong>.</li>';
			echo '<li>Katra mēneša ' . (int) self::DAY . '. datumā saņemsi e-pastu: <em>Apstiprināt / Mainīt / Izlaist</em>.</li>';
			echo '<li>«Apstiprināt» ieliek kasti grozā — izvēlies pakomātu un saņem rēķinu. Bez apmaksas nekas netiek sūtīts.</li>';
			echo '</ol><p>Nav abonēšanas maksas — maksā tikai par to, kas ir kastē, pēc tavām konditora cenām.</p>';
			if ( WC()->cart && ! WC()->cart->is_empty() ) {
				echo '<p>Tavā grozā jau ir preces:</p>' . self::save_form( '🗓 Saglabāt pašreizējo grozu kā mēneša kasti' ); // phpcs:ignore
			}
			echo '<p><a class="button" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Uz veikalu →</a></p></div>';
			return;
		}
		$lines = self::lines( $box );
		$total = array_sum( wp_list_pluck( $lines, 'sum' ) );
		$skip  = get_user_meta( $uid, self::U_SKIP, true ) === wp_date( 'Y-m', strtotime( 'first day of next month', current_time( 'timestamp' ) ) );
		$active = ! empty( $box['active'] );
		echo '<div class="mmkb-head"><strong>Kā tas strādā</strong>' . self::help() . '</div>'; // phpcs:ignore
		echo '<p><strong>Statuss:</strong> ' . ( $active ? ( $skip ? 'nākamais mēnesis izlaists' : 'aktīva — atgādinājums ' . (int) self::DAY . '. datumā' ) : 'apturēta' ) . '</p>';
		echo '<table class="shop_table"><thead><tr><th>Prece</th><th>Daudzums</th><th>Summa</th></tr></thead><tbody>';
		foreach ( $lines as $l ) {
			echo '<tr><td>' . esc_html( $l['name'] ) . '</td><td>' . (int) $l['qty'] . '</td><td>' . wp_kses_post( wc_price( $l['sum'] ) ) . '</td></tr>';
		}
		echo '</tbody><tfoot><tr><th colspan="2">Kopā (bez piegādes)</th><th>' . wp_kses_post( wc_price( $total ) ) . '</th></tr></tfoot></table>';
		$n = wp_create_nonce( 'mm_kc_box' );
		echo '<p class="mmkb-actions">';
		echo '<a class="button alt" href="' . esc_url( self::url( array( 'mmkb_do' => 'order', '_n' => $n ) ) ) . '">Pasūtīt tagad</a> ';
		echo '<a class="button" href="' . esc_url( self::url( array( 'mmkb_do' => 'edit', '_n' => $n ) ) ) . '">Mainīt sastāvu</a> ';
		if ( $active && ! $skip ) {
			echo '<a class="button" href="' . esc_url( self::url( array( 'mmkb_do' => 'skip', '_n' => $n ) ) ) . '">Izlaist nākamo mēnesi</a> ';
		}
		echo $active
			? '<a class="button" href="' . esc_url( self::url( array( 'mmkb_do' => 'pause', '_n' => $n ) ) ) . '">Apturēt</a> '
			: '<a class="button" href="' . esc_url( self::url( array( 'mmkb_do' => 'resume', '_n' => $n ) ) ) . '">Atsākt</a> ';
		echo '<a class="button" href="' . esc_url( self::url( array( 'mmkb_do' => 'delete', '_n' => $n ) ) ) . '" onclick="return confirm(\'Dzēst mēneša kasti?\')">Dzēst</a>';
		echo '</p><p><small>«Mainīt sastāvu» ieliek kasti grozā: pievieno vai noņem preces un grozā vēlreiz nospied «Saglabāt kā mēneša kasti».</small></p></div>';
	}

	/* ---------------- Grozs ---------------- */

	public static function cart_button() {
		if ( ! MarMar_Konditoru_Cenas::is_konditor() ) {
			return;
		}
		wp_nonce_field( 'mm_kc_box_save', 'mm_kc_box_nonce' );
		echo '<button type="submit" class="button mmkb-save" name="mm_kc_box_save" value="1">🗓 Saglabāt kā mēneša kasti</button>';
	}

	/** «?» ikona ar paskaidrojumu (atveras ar pieskārienu, bez JS). */
	public static function help() {
		return '<details class="mmkb-help"><summary aria-label="Kas ir mēneša kaste?">?</summary><div class="mmkb-help-pop">'
			. '<strong>Kas ir mēneša kaste?</strong>'
			. '<ul><li>Tu vienreiz saglabā savu ierasto pasūtījumu.</li>'
			. '<li>Katra mēneša ' . (int) self::DAY . '. datumā saņemsi e-pastu ar kastes saturu un summu.</li>'
			. '<li><b>Apstiprināt</b> — kaste ielikta grozā, atliek apmaksāt. <b>Mainīt</b> — pievieno vai noņem preces. <b>Izlaist</b> — šomēnes nepasūti.</li>'
			. '<li>Nauda netiek noņemta automātiski: maksā tikai tad, kad apstiprini.</li>'
			. '<li>Apturēt vai dzēst vari jebkurā brīdī: Mans konts → Mana mēneša kaste.</li></ul></div></details>';
	}

	public static function save_form( $label = '🗓 Saglabāt kā mēneša kasti' ) {
		$html  = '<form method="post" class="mmkb-saveform" action="' . esc_url( self::url() ) . '">';
		$html .= wp_nonce_field( 'mm_kc_box_save', 'mm_kc_box_nonce', true, false );
		$html .= '<button type="submit" class="button mmkb-save" name="mm_kc_box_save" value="1">' . esc_html( $label ) . '</button></form>';
		return $html;
	}

	/** Bloku grozam (woocommerce/cart) pievieno pogu zem groza — tikai konditoriem. */
	public static function cart_block_button( $content, $block ) {
		if ( 'woocommerce/cart' !== ( $block['blockName'] ?? '' ) || ! MarMar_Konditoru_Cenas::is_konditor() ) {
			return $content;
		}
		return $content . '<div class="mmkb-cartbox"><div class="mmkb-head"><strong>🗓 Mēneša kaste konditoriem</strong>' . self::help() . '</div><p>Pasūti to pašu katru mēnesi? Saglabā šo grozu kā mēneša kasti — 20. datumā saņemsi atgādinājumu ar apstiprināšanu vienā klikšķī.</p>' . self::save_form() . '</div>';
	}

	public static function save_cart_as_box( $uid ) {
		$items = array();
		foreach ( WC()->cart->get_cart() as $ci ) {
			$extra = array();
			foreach ( $ci as $k => $v ) {
				if ( 0 === strpos( $k, 'mm' ) && ! in_array( $k, array( 'mm_kc_box' ), true ) ) {
					$extra[ $k ] = $v;
				}
			}
			$items[] = array(
				'product_id'   => (int) $ci['product_id'],
				'variation_id' => (int) $ci['variation_id'],
				'quantity'     => (int) $ci['quantity'],
				'variation'    => (array) $ci['variation'],
				'extra'        => $extra,
			);
		}
		if ( ! $items ) {
			return false;
		}
		update_user_meta( $uid, self::U_BOX, array( 'items' => $items, 'saved' => time(), 'active' => 1 ) );
		return true;
	}

	public static function load_box_to_cart( $uid ) {
		$box = self::box( $uid );
		if ( ! $box ) {
			return false;
		}
		WC()->cart->empty_cart();
		foreach ( $box['items'] as $it ) {
			WC()->cart->add_to_cart( $it['product_id'], $it['quantity'], $it['variation_id'], $it['variation'], (array) $it['extra'] );
		}
		WC()->session->set( 'mm_kc_box', 1 );
		return true;
	}

	public static function handle() {
		// Saglabāt grozu.
		if ( ! empty( $_POST['mm_kc_box_save'] ) && isset( $_POST['mm_kc_box_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mm_kc_box_nonce'] ) ), 'mm_kc_box_save' ) ) {
			$uid = get_current_user_id();
			if ( $uid && MarMar_Konditoru_Cenas::is_konditor( $uid ) ) {
				$ok = self::save_cart_as_box( $uid );
				wp_safe_redirect( self::url( array( 'mmkb' => $ok ? 'saved' : 'empty' ) ) );
				exit;
			}
		}
		// Izlaist no e-pasta (bez pieslēgšanās, ar parakstu).
		if ( isset( $_GET['mmkb_skip'], $_GET['u'], $_GET['s'] ) ) { // phpcs:ignore
			$uid = absint( $_GET['u'] ); // phpcs:ignore
			$m   = sanitize_text_field( wp_unslash( $_GET['mmkb_skip'] ) ); // phpcs:ignore
			if ( hash_equals( self::sig( $uid, $m ), sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) ) { // phpcs:ignore
				update_user_meta( $uid, self::U_SKIP, $m );
				wp_die( '<p style="font:16px/1.6 sans-serif">Paldies! Šis mēnesis ir izlaists, kaste paliek saglabāta. <a href="' . esc_url( home_url( '/' ) ) . '">MarMar Candles</a></p>', 'MarMar Candles', array( 'response' => 200 ) );
			}
		}
		// Darbības kontā.
		if ( isset( $_GET['mmkb_do'] ) ) { // phpcs:ignore
			$uid = get_current_user_id();
			if ( ! $uid || ! MarMar_Konditoru_Cenas::is_konditor( $uid ) ) {
				return;
			}
			$do    = sanitize_key( wp_unslash( $_GET['mmkb_do'] ) ); // phpcs:ignore
			$email = in_array( $do, array( 'order', 'edit' ), true ) && isset( $_GET['e'] ); // phpcs:ignore -- saite no e-pasta (pieslēgšanās jau notikusi)
			if ( ! $email && ( ! isset( $_GET['_n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_n'] ) ), 'mm_kc_box' ) ) ) { // phpcs:ignore
				return;
			}
			$box = self::box( $uid );
			switch ( $do ) {
				case 'order':
				case 'edit':
					if ( self::load_box_to_cart( $uid ) ) {
						if ( 'edit' === $do ) {
							WC()->session->set( 'mm_kc_box', 0 );
							wc_add_notice( 'Kaste ir grozā. Maini sastāvu un nospied «Saglabāt kā mēneša kasti».', 'notice' );
							wp_safe_redirect( wc_get_cart_url() );
						} else {
							wp_safe_redirect( wc_get_checkout_url() );
						}
						exit;
					}
					break;
				case 'skip':
					update_user_meta( $uid, self::U_SKIP, wp_date( 'Y-m', strtotime( 'first day of next month', current_time( 'timestamp' ) ) ) );
					wp_safe_redirect( self::url( array( 'mmkb' => 'skipped' ) ) );
					exit;
				case 'pause':
				case 'resume':
					if ( $box ) {
						$box['active'] = 'resume' === $do ? 1 : 0;
						update_user_meta( $uid, self::U_BOX, $box );
					}
					wp_safe_redirect( self::url( array( 'mmkb' => 'resume' === $do ? 'resumed' : 'paused' ) ) );
					exit;
				case 'delete':
					delete_user_meta( $uid, self::U_BOX );
					wp_safe_redirect( self::url( array( 'mmkb' => 'deleted' ) ) );
					exit;
			}
		}
	}

	public static function login_redirect( $redirect ) {
		$ref = wp_get_referer();
		if ( $ref && false !== strpos( $ref, self::ENDPOINT ) ) {
			return $ref;
		}
		return $redirect;
	}

	public static function flag_order( $order, $data = null ) {
		if ( WC()->session && WC()->session->get( 'mm_kc_box' ) ) {
			$order->update_meta_data( '_mm_kc_box', wp_date( 'Y-m' ) );
			$order->add_order_note( '🗓 Mēneša kaste (konditors).' );
			$order->save();
			WC()->session->set( 'mm_kc_box', 0 );
		}
	}

	public static function admin_order_label( $order ) {
		$m = $order->get_meta( '_mm_kc_box' );
		if ( $m ) {
			echo '<p class="form-field form-field-wide"><strong>🗓 Mēneša kaste</strong> (' . esc_html( $m ) . ')</p>';
		}
	}

	/* ---------------- Ikmēneša atgādinājumi ---------------- */

	public static function sig( $uid, $month ) {
		return substr( wp_hash( 'mmkb|' . $uid . '|' . $month ), 0, 20 );
	}

	public static function active_boxes() {
		$users = get_users( array( 'role' => MarMar_Konditoru_Cenas::ROLE, 'meta_key' => self::U_BOX, 'number' => 500 ) ); // phpcs:ignore
		$out   = array();
		$next  = wp_date( 'Y-m', strtotime( 'first day of next month', current_time( 'timestamp' ) ) );
		foreach ( $users as $u ) {
			$b = self::box( $u->ID );
			if ( $b && ! empty( $b['active'] ) && get_user_meta( $u->ID, self::U_SKIP, true ) !== $next ) {
				$out[ $u->ID ] = $b;
			}
		}
		return $out;
	}

	public static function daily( $force = false ) {
		$month = wp_date( 'Y-m' );
		if ( ! $force && ( (int) wp_date( 'j' ) < self::DAY || get_option( self::OPT_SENT ) === $month ) ) {
			return;
		}
		update_option( self::OPT_SENT, $month, false );
		$boxes = self::active_boxes();
		$sum   = array();
		$label = self::next_month_label();
		$next  = wp_date( 'Y-m', strtotime( 'first day of next month', current_time( 'timestamp' ) ) );
		foreach ( $boxes as $uid => $box ) {
			$u     = get_userdata( $uid );
			$lines = self::lines( $box );
			$total = array_sum( wp_list_pluck( $lines, 'sum' ) );
			$txt   = '';
			foreach ( $lines as $l ) {
				$txt .= '• ' . $l['name'] . ' × ' . $l['qty'] . ' — ' . wp_strip_all_tags( wc_price( $l['sum'] ) ) . "\n";
				$key = $l['name'];
				$sum[ $key ] = ( $sum[ $key ] ?? 0 ) + $l['qty'];
			}
			$name = $u->first_name ? $u->first_name : $u->display_name;
			$body  = "Sveiki, {$name}!\n\nTava mēneša kaste ({$label}):\n\n{$txt}\nKopā: " . wp_strip_all_tags( wc_price( $total ) ) . " (bez piegādes)\n\n";
			$body .= 'Apstiprināt un pasūtīt: ' . self::url( array( 'mmkb_do' => 'order', 'e' => 1 ) ) . "\n";
			$body .= 'Mainīt sastāvu: ' . self::url( array( 'mmkb_do' => 'edit', 'e' => 1 ) ) . "\n";
			$body .= 'Izlaist šo mēnesi: ' . add_query_arg( array( 'mmkb_skip' => $next, 'u' => $uid, 's' => self::sig( $uid, $next ) ), home_url( '/' ) ) . "\n\n";
			$body .= "Bez apmaksas nekas netiek sūtīts. Rēķins pienāks pēc apstiprināšanas.\n\nAr cieņu,\nAlīna, MarMar Candles";
			wp_mail( $u->user_email, 'MarMar Candles: tava mēneša kaste (' . $label . ')', $body );
		}
		// Kopsavilkums Alīnai.
		$s     = MarMar_Konditoru_Cenas::settings();
		$admin = 'Mēneša kastes (' . $label . '): ' . count( $boxes ) . " aktīvas.\n\nKo sagatavot (kopā visām kastēm, ja visi apstiprinās):\n\n";
		ksort( $sum );
		foreach ( $sum as $k => $q ) {
			$admin .= '• ' . $k . ' × ' . $q . "\n";
		}
		$admin .= "\nApstiprinātie pasūtījumi parādīsies WooCommerce ar atzīmi «🗓 Mēneša kaste».";
		wp_mail( $s['notify'], 'Mēneša kastes — kopsavilkums (' . $label . ')', $admin );
	}

	public static function admin_test() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'mm_kc_box_test' ) ) {
			wp_die( 'Nav atļauts.' );
		}
		delete_option( self::OPT_SENT );
		self::daily( true );
		wp_safe_redirect( admin_url( 'admin.php?page=mm-konditoru-cenas&done=1' ) );
		exit;
	}

	/** Bloks administrācijas lapā «Konditoru cenas». */
	public static function admin_section() {
		$boxes = self::active_boxes();
		echo '<h2>Mēneša kastes (' . count( $boxes ) . ' aktīvas nākamajam mēnesim)</h2>';
		if ( $boxes ) {
			echo '<table class="widefat striped"><thead><tr><th>Konditors</th><th>Sastāvs</th><th>Summa</th></tr></thead><tbody>';
			foreach ( $boxes as $uid => $b ) {
				$u     = get_userdata( $uid );
				$lines = self::lines( $b );
				echo '<tr><td>' . esc_html( $u->display_name . ' <' . $u->user_email . '>' ) . '</td><td>';
				foreach ( $lines as $l ) {
					echo esc_html( $l['name'] . ' × ' . $l['qty'] ) . '<br>';
				}
				echo '</td><td>' . wp_kses_post( wc_price( array_sum( wp_list_pluck( $lines, 'sum' ) ) ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<p>Atgādinājumi konditoriem un kopsavilkums tev tiek sūtīti automātiski katra mēneša ' . (int) self::DAY . '. datumā.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'mm_kc_box_test' );
		echo '<input type="hidden" name="action" value="mm_kc_box_test">';
		submit_button( 'Nosūtīt atgādinājumus tagad', 'secondary', 'submit', false );
		echo '</form>';
	}
}
