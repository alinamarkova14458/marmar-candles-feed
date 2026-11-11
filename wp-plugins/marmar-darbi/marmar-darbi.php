<?php
/**
 * Plugin Name: MarMar Darbi
 * Description: WooCommerce → «Ko izgatavot»: kas jāizgatavo un jānosūta — kopsavilkums pa precēm, krāsām un dizainiem + pasūtījumi pēc termiņa. Tikai administratoram, atjaunojas pati.
 * Version: 1.0.1
 * Author: MarMar Candles
 */
defined( 'ABSPATH' ) || exit;

final class MarMar_Darbi {

	/** Atvaļinājums: šīs dienas neskaitās izgatavošanā (kā MarMar Checkout). */
	const VACATIONS = array( array( '2026-11-26', '2026-12-03' ) );

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
	}

	public static function menu() {
		add_submenu_page( 'woocommerce', 'Ko izgatavot', 'Ko izgatavot', 'manage_woocommerce', 'mm-darbi', array( __CLASS__, 'page' ) );
	}

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

	/** Preces variants: redzamie meta lauki (krāsa, dizains, teksts…), bez tehniskajiem. */
	private static function item_meta( WC_Order_Item_Product $item ) {
		$out = array();
		foreach ( $item->get_all_formatted_meta_data( '_', true ) as $m ) {
			$k = trim( wp_strip_all_tags( $m->display_key ) );
			$v = trim( wp_strip_all_tags( $m->display_value ) );
			if ( '' === $k || '' === $v ) {
				continue;
			}
			$out[ $k ] = $v;
		}
		return $out;
	}

	private static function short_key( $k ) {
		$k = mb_strtolower( $k );
		if ( false !== mb_strpos( $k, 'lent' ) ) {
			return 'Lente';
		}
		if ( false !== mb_strpos( $k, 'krās' ) ) {
			return 'Krāsa';
		}
		if ( false !== mb_strpos( $k, 'dizain' ) ) {
			return 'Dizains';
		}
		if ( false !== mb_strpos( $k, 'variant' ) ) {
			return 'Variants';
		}
		return mb_convert_case( $k, MB_CASE_TITLE );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$tz     = wp_timezone();
		$today  = new DateTimeImmutable( 'today', $tz );
		$orders = wc_get_orders( array( 'status' => array( 'processing', 'on-hold' ), 'limit' => 300, 'orderby' => 'date', 'order' => 'ASC' ) );

		$sum  = array(); // product => variant => [qty, orders[]]
		$rows = array();
		foreach ( $orders as $o ) {
			$express = false;
			foreach ( $o->get_fees() as $fee ) {
				if ( false !== mb_stripos( $fee->get_name(), 'steidzam' ) ) {
					$express = true;
				}
			}
			$start = $o->get_date_paid() ? $o->get_date_paid() : $o->get_date_created();
			$start = ( new DateTimeImmutable( '@' . $start->getTimestamp() ) )->setTimezone( $tz )->setTime( 0, 0 );
			$due   = self::add_make_days( $start, $express ? 2 : 7 );
			$wish  = '';
			$items = array();
			foreach ( $o->get_items() as $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}
				$meta = self::item_meta( $item );
				foreach ( $meta as $k => $v ) {
					if ( false !== mb_stripos( $k, 'saņemšanas datums' ) && preg_match( '/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $v, $mm ) ) {
						$w = DateTimeImmutable::createFromFormat( '!Y-n-j', $mm[3] . '-' . (int) $mm[2] . '-' . (int) $mm[1], $tz );
						if ( $w ) {
							$wish = $w;
						}
					}
				}
				$name    = $item->get_name();
				$variant = array();
				foreach ( $meta as $k => $v ) {
					if ( preg_match( '/krās|dizain|variant|svece|lentes|daudzums|personaliz/iu', $k ) ) {
						$variant[] = self::short_key( $k ) . ': ' . $v;
					}
				}
				$vkey = $variant ? implode( ' · ', $variant ) : '—';
				$st   = $o->get_status();
				if ( ! isset( $sum[ $st ][ $name ][ $vkey ] ) ) {
					$sum[ $st ][ $name ][ $vkey ] = array( 'qty' => 0, 'orders' => array() );
				}
				$sum[ $st ][ $name ][ $vkey ]['qty']     += $item->get_quantity();
				$sum[ $st ][ $name ][ $vkey ]['orders'][] = '#' . $o->get_order_number();
				$items[] = array( 'qty' => $item->get_quantity(), 'name' => $name, 'meta' => $meta );
			}
			$deadline = $wish ? $wish : $due; // kāzām u.c. — klienta vēlamais saņemšanas datums
			$ship     = $o->get_shipping_method();
			$rows[]   = array(
				'o'        => $o,
				'express'  => $express,
				'deadline' => $deadline,
				'wish'     => $wish,
				'items'    => $items,
				'ship'     => $ship ? $ship : '—',
			);
		}
		usort( $rows, function ( $a, $b ) {
			if ( $a['o']->get_status() !== $b['o']->get_status() ) {
				return 'processing' === $a['o']->get_status() ? -1 : 1;
			}
			return $a['deadline'] <=> $b['deadline'];
		} );

		echo '<div class="wrap mmd-darbi"><h1>Ko izgatavot <small style="font-size:13px;color:#777;font-weight:normal">atjaunots ' . esc_html( wp_date( 'd.m.Y H:i' ) ) . '</small> <button class="button" onclick="window.print()">Drukāt</button></h1>';
		echo '<style>
.mmd-darbi table{border-collapse:collapse;width:100%;max-width:1100px;background:#fff;margin:.5em 0 1.5em}
.mmd-darbi th,.mmd-darbi td{border:1px solid #e5e0de;padding:7px 10px;text-align:left;vertical-align:top}
.mmd-darbi th{background:#FAF3F0}
.mmd-darbi .q{font-size:18px;font-weight:700;color:#B9605F;text-align:center;width:60px}
.mmd-darbi .prod td{background:#fbf7f5;font-weight:600}
.mmd-darbi .late{color:#b32d2e;font-weight:700}.mmd-darbi .soon{color:#c46a00;font-weight:700}
.mmd-darbi .exp{display:inline-block;background:#B9605F;color:#fff;border-radius:10px;padding:1px 8px;font-size:12px;margin-left:6px}
.mmd-darbi ul{margin:0;padding-left:1.1em}.mmd-darbi small{color:#666}
@media print{#adminmenumain,#wpadminbar,.mmd-darbi button,.notice,.update-nag{display:none!important}#wpcontent{margin:0!important}}
</style>';

		$labels = array( 'processing' => 'Izgatavot (apmaksāti, statuss «Apstrādē»)', 'on-hold' => 'Gaida apmaksu — vēl neizgatavot' );
		if ( ! $orders ) {
			echo '<p>Nav atvērtu pasūtījumu 🎉</p></div>';
			return;
		}
		echo '<h2>Kopsavilkums</h2>';
		foreach ( $labels as $st => $lbl ) {
			if ( empty( $sum[ $st ] ) ) {
				continue;
			}
			ksort( $sum[ $st ] );
			echo '<h3>' . esc_html( $lbl ) . '</h3><table><thead><tr><th>Prece / variants</th><th>Gab.</th><th>Pasūtījumi</th></tr></thead><tbody>';
			foreach ( $sum[ $st ] as $name => $variants ) {
				$total = array_sum( wp_list_pluck( $variants, 'qty' ) );
				echo '<tr class="prod"><td>' . esc_html( $name ) . '</td><td class="q">' . (int) $total . '</td><td></td></tr>';
				ksort( $variants );
				foreach ( $variants as $vkey => $v ) {
					echo '<tr><td style="padding-left:28px">' . esc_html( $vkey ) . '</td><td class="q">' . (int) $v['qty'] . '</td><td>' . esc_html( implode( ', ', array_unique( $v['orders'] ) ) ) . '</td></tr>';
				}
			}
			echo '</tbody></table>';
		}

		echo '<h2>Pasūtījumi pēc termiņa</h2><table><thead><tr><th>Pasūtījums</th><th>Termiņš</th><th>Preces</th><th>Piegāde</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$o    = $r['o'];
			$days = (int) $today->diff( $r['deadline'] )->format( '%r%a' );
			$cls  = $days < 0 ? 'late' : ( $days <= 2 ? 'soon' : '' );
			$when = $days < 0 ? 'nokavēts ' . abs( $days ) . ' d.' : ( 0 === $days ? 'šodien' : 'pēc ' . $days . ' d.' );
			echo '<tr><td><a href="' . esc_url( $o->get_edit_order_url() ) . '"><strong>#' . esc_html( $o->get_order_number() ) . '</strong></a>' . ( $r['express'] ? '<span class="exp">Steidzams</span>' : '' ) . '<br><small>' . esc_html( wc_get_order_status_name( $o->get_status() ) ) . ' · ' . esc_html( $o->get_date_created()->date_i18n( 'd.m.Y' ) ) . '<br>' . esc_html( trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ) ) . '</small></td>';
			echo '<td class="' . esc_attr( $cls ) . '">' . esc_html( $r['deadline']->format( 'd.m.Y' ) ) . '<br><small>' . esc_html( $when ) . ( $r['wish'] ? '<br>klienta vēlamais datums' : '' ) . '</small></td><td><ul>';
			foreach ( $r['items'] as $it ) {
				$m = array();
				foreach ( $it['meta'] as $k => $v ) {
					$m[] = self::short_key( $k ) . ': ' . $v;
				}
				echo '<li><strong>' . (int) $it['qty'] . ' ×</strong> ' . esc_html( $it['name'] ) . ( $m ? '<br><small>' . esc_html( implode( ' · ', $m ) ) . '</small>' : '' ) . '</li>';
			}
			echo '</ul></td><td><small>' . esc_html( $r['ship'] ) . '</small></td></tr>';
		}
		echo '</tbody></table><p><small>Termiņš: no apmaksas (vai pasūtījuma) dienas + 7 dienas, steidzamam + 2; atvaļinājuma dienas 26.11–3.12 neskaitās. Ja klients norādījis vēlamo saņemšanas datumu (kāzas) — rāda to.</small></p></div>';
	}
}
MarMar_Darbi::init();
