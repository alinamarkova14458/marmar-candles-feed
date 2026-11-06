<?php
/**
 * Plugin Name:       MarMar Design
 * Description:       Jaunais MarMar Candles dizains virs Blocksy tēmas: krāsas, šrifti Marcellus + Figtree (no sava servera), pogas, liesmiņas, augšējā josla, produkta lapas termiņu bloks un lipīgā “Ielikt grozā” josla. Izslēdzot spraudni — atgriežas iepriekšējais izskats.
 * Version:           1.9.4
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Design {

	const VER = '1.9.4';

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'preload' ), 1 );
		add_action( 'wp_body_open', array( __CLASS__, 'topbar' ), 5 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'eta_box' ), 25 );
		// trust_row nav vajadzīga: produkta lapā jau ir MarMar UX informācijas bloks.
		add_action( 'wp_footer', array( __CLASS__, 'reveal_inline' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'sticky_bar' ), 20 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'theme_mod_header_placements', array( __CLASS__, 'one_logo' ), 20 );
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'express_checkbox' ), 30 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'express_on_add' ), 10, 0 );
		add_action( 'woocommerce_after_single_product_summary', array( __CLASS__, 'together' ), 12 );
		add_filter( 'woocommerce_product_tabs', array( __CLASS__, 'tabs' ), 98 );
	}

	/**
	 * Telefonā un planšetē — tas pats pilnais logo “MARMAR CANDLES” (agrāk melns aplis ar “M”).
	 * Mainām tikai izvadi; Blocksy iestatījumi datubāzē paliek, izslēdzot spraudni viss atgriežas.
	 */
	public static function one_logo( $v ) {
		if ( ! is_array( $v ) || is_admin() || is_customize_preview() ) {
			return $v;
		}
		$walk = function ( &$node ) use ( &$walk ) {
			if ( ! is_array( $node ) ) {
				return;
			}
			if ( isset( $node['id'] ) && 'logo' === $node['id'] && isset( $node['values']['custom_logo'] ) && is_array( $node['values']['custom_logo'] ) ) {
				$l = $node['values']['custom_logo'];
				if ( ! empty( $l['desktop'] ) ) {
					$node['values']['custom_logo']['tablet'] = $l['desktop'];
					$node['values']['custom_logo']['mobile'] = $l['desktop'];
				}
			}
			foreach ( $node as &$child ) {
				$walk( $child );
			}
		};
		$walk( $v );
		return $v;
	}

	public static function body_class( $c ) {
		$c[] = 'mmd';
		return $c;
	}

	public static function assets() {
		if ( is_admin() ) {
			return;
		}
		$u = plugins_url( 'assets/', __FILE__ );
		wp_enqueue_style( 'marmar-design-fonts', $u . 'fonts.css', array(), self::VER );
		$deps = wp_style_is( 'ct-main-styles', 'registered' ) ? array( 'ct-main-styles' ) : array();
		wp_enqueue_style( 'marmar-design', $u . 'design.css', $deps, self::VER );
		wp_enqueue_script( 'marmar-design', $u . 'design.js', array(), self::VER, array( 'strategy' => 'defer', 'in_footer' => true ) );
		// Vecie Google šrifti (Montserrat, Cormorant) vairs nav vajadzīgi.
		wp_dequeue_style( 'blocksy-fonts-font-source-google' );
	}

	public static function preload() {
		// Parādīšanās animācijai: sākuma stāvoklis uzreiz (bez “mirgošanas”), drošībai — viss redzams pēc 4 s.
		echo "<script>document.documentElement.classList.add('mmd-js');window.mmdT=setTimeout(function(){document.documentElement.classList.add('mmd-all')},4000);</script>\n";
		$u = plugins_url( 'assets/fonts/', __FILE__ );
		foreach ( array( 'marcellus-latin', 'figtree-latin' ) as $f ) {
			echo '<link rel="preload" href="' . esc_url( $u . $f . '.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
		}
	}

	public static function topbar() {
		echo '<div class="mmd-topbar" role="note"><span>Bezmaksas piegāde no 50 €</span><span class="mmd-sep mmd-flame" aria-hidden="true"></span><span>Gatavs 5–7 dienās</span><span class="mmd-sep mmd-flame mmd-d" aria-hidden="true"></span><span class="mmd-d">Steidzami 1–2 dienās</span></div>';
	}

	/** Termiņu bloks produkta lapā. Datumu aprēķina JS (lapa tiek kešota). */
	public static function eta_box() {
		global $product;
		if ( ! $product || ! $product->is_purchasable() ) {
			return;
		}
		echo '<div class="mmd-eta" data-mmd-eta>';
		echo '<span class="mmd-eta-ico" aria-hidden="true">✓</span><span><strong>Saņemsiet <span data-mmd-eta-date>5–9 dienu laikā</span></strong><br><small>Izgatavošana 5–7 dienas + pakomātā 1–2 darba dienās. Steidzami — 1–2 dienās (+5 €), atzīmējiet zemāk.</small></span>';
		echo '</div>';
	}

	/** “Steidzams pasūtījums +5 €” produkta lapā — ieslēdz to pašu sesijas karogu, ko grozs (+5eur sniplets pievieno maksu). */
	public static function express_checkbox() {
		echo '<label class="mmd-fast"><input type="checkbox" name="marmar_express" value="1"><span><strong>Steidzams pasūtījums +5 €</strong><small>Izgatavosim 1–2 dienās. Grozā var mainīt.</small></span></label>';
	}

	public static function express_on_add() {
		if ( ! empty( $_REQUEST['marmar_express'] ) && function_exists( 'WC' ) && WC()->session ) { // phpcs:ignore WordPress.Security.NonceVerification
			WC()->session->set( 'marmar_express_order', true );
		}
	}

	/** “Bieži pērk kopā”: produkta cross-sells vai populārākās preces no tās pašas kategorijas. */
	public static function together() {
		global $product;
		if ( ! $product ) {
			return;
		}
		$key = 'mmd_together_' . $product->get_id();
		$ids = get_transient( $key );
		if ( ! is_array( $ids ) ) {
			$ids = array_slice( array_map( 'intval', $product->get_cross_sell_ids() ), 0, 3 );
			if ( count( $ids ) < 3 ) {
				$cats = $product->get_category_ids();
				$more = wc_get_products(
					array(
						'status'       => 'publish',
						'limit'        => 8,
						'orderby'      => 'popularity',
						'order'        => 'DESC',
						'category'     => array_map( function ( $id ) { $t = get_term( $id ); return $t && ! is_wp_error( $t ) ? $t->slug : ''; }, $cats ),
						'exclude'      => array_merge( array( $product->get_id() ), $ids ),
						'stock_status' => 'instock',
						'return'       => 'ids',
					)
				);
				$ids = array_slice( array_merge( $ids, $more ), 0, 3 );
			}
			set_transient( $key, $ids, 12 * HOUR_IN_SECONDS );
		}
		$rows = '';
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p || ! $p->is_purchasable() || ! $p->is_visible() ) {
				continue;
			}
			$img  = $p->get_image( 'woocommerce_thumbnail', array( 'class' => 'mmd-tg-img', 'loading' => 'lazy' ) );
			$link = get_permalink( $id );
			if ( $p->is_type( 'simple' ) && $p->is_in_stock() ) {
				$btn = '<a href="' . esc_url( $p->add_to_cart_url() ) . '" data-quantity="1" data-product_id="' . esc_attr( $id ) . '" class="mmd-tg-add add_to_cart_button ajax_add_to_cart" aria-label="' . esc_attr( 'Ielikt grozā: ' . $p->get_name() ) . '" rel="nofollow">+</a>';
			} else {
				$btn = '<a href="' . esc_url( $link ) . '" class="mmd-tg-add mmd-tg-add--more" aria-label="' . esc_attr( 'Izvēlēties: ' . $p->get_name() ) . '">→</a>';
			}
			$rows .= '<div class="mmd-tg-row"><a class="mmd-tg-pic" href="' . esc_url( $link ) . '">' . $img . '</a><div class="mmd-tg-info"><a href="' . esc_url( $link ) . '">' . esc_html( $p->get_name() ) . '</a><span class="mmd-tg-price">' . wp_kses_post( $p->get_price_html() ) . '</span></div>' . $btn . '</div>';
		}
		if ( $rows ) {
			echo '<section class="mmd-together"><h2 class="has-text-align-center mmd-star">Bieži pērk kopā</h2><div class="mmd-tg-list">' . $rows . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/** Cilnes: Apraksts · Piegāde un termiņi · Drošība (bez “Papildu informācija” un atsauksmēm). */
	public static function tabs( $tabs ) {
		unset( $tabs['additional_information'], $tabs['reviews'] );
		$tabs['mmd_delivery'] = array( 'title' => 'Piegāde un termiņi', 'priority' => 20, 'callback' => array( __CLASS__, 'tab_delivery' ) );
		$tabs['mmd_safety']   = array( 'title' => 'Drošība', 'priority' => 30, 'callback' => array( __CLASS__, 'tab_safety' ) );
		return $tabs;
	}

	public static function tab_delivery() {
		echo '<ul class="mmd-list">'
			. '<li><strong>Izgatavošana — 5–7 dienas</strong> pēc apmaksas. Katru sveci darinām ar rokām.</li>'
			. '<li><strong>Steidzams pasūtījums — 1–2 dienās</strong>, +5 €. Atzīmējiet to virs pogas “Pievienot grozam” vai grozā.</li>'
			. '<li><strong>Piegāde uz pakomātiem visā Latvijā</strong> — Omniva, DPD, Venipak, Unisend: no 2,00 € līdz 4,50 €, parasti 1–2 darba dienās.</li>'
			. '<li><strong>Bezmaksas piegāde</strong>, ja pasūtījums ir no 50 €.</li>'
			. '<li><strong>Saņemšana Rīgā, Iļģuciemā</strong> — saskaņojot laiku.</li>'
			. '<li><strong>Minimālais pasūtījums — 5 €</strong> (bez piegādes).</li>'
			. '<li><strong>Apmaksa</strong> — ar pārskaitījumu pēc rēķina.</li>'
			. '</ul><p class="mmd-more"><a href="' . esc_url( home_url( '/lv/piegade/' ) ) . '">Visi piegādes noteikumi →</a></p>';
	}

	public static function tab_safety() {
		echo '<div class="mmd-warn"><strong>Nekad neatstājiet degošu sveci bez uzraudzības.</strong></div>'
			. '<ul class="mmd-list">'
			. '<li>Turiet bērniem un mājdzīvniekiem nepieejamā vietā.</li>'
			. '<li>Novietojiet uz stabilas, karstumizturīgas virsmas — prom no aizkariem, papīra, eglītes zariem un citiem viegli uzliesmojošiem priekšmetiem, kā arī no caurvēja.</li>'
			. '<li><strong>Kūkas sveces</strong> aizdedziet tieši pirms svinībām un nopūtiet pēc 1–2 minūtēm, lai vasks nenotek uz kūkas. Pirms kūkas griešanas sveces noņemiet.</li>'
			. '<li>Dekoratīvās un figūru sveces dedziniet tikai uz paliktņa; nededziniet ilgāk par 2–3 stundām pēc kārtas.</li>'
			. '<li>Pirms katras aizdedzināšanas apgrieziet degli līdz ~5 mm. Ja liesma kļūst pārāk liela vai svece kūp — nodzēsiet.</li>'
			. '<li>Neaizdedziniet sveci tuvāk par 10 cm no citām degošām svecēm.</li>'
			. '<li>Sveces nav ēdamas.</li>'
			. '</ul>';
	}

	public static function trust_row() {
		echo '<div class="mmd-trust"><span>Pakomātā no 2 €</span><span>Bez maksas no 50 €</span><span>Min. pasūtījums 5 €</span></div>';
	}

	/** Inline lapas apakšā pirms citiem skriptiem — kartītes parādās uzreiz, negaidot jQuery u.c. */
	public static function reveal_inline() {
		$f = __DIR__ . '/assets/reveal.js';
		if ( is_readable( $f ) ) {
			echo '<script id="marmar-design-reveal">' . file_get_contents( $f ) . '</script>' . "\n"; // phpcs:ignore
		}
	}

	public static function sticky_bar() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return;
		}
		echo '<div class="mmd-sticky" data-mmd-sticky hidden><div class="mmd-sticky-info"><span class="mmd-sticky-price">' . wp_kses_post( $product->get_price_html() ) . '</span><small data-mmd-eta-short></small></div><button type="button" class="mmd-sticky-btn" data-mmd-sticky-btn>' . esc_html( $product->is_type( 'simple' ) ? 'Ielikt grozā' : 'Izvēlēties' ) . '</button></div>';
	}
}

add_action( 'plugins_loaded', array( 'MarMar_Design', 'init' ) );
