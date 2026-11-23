<?php
/**
 * Plugin Name:       MarMar Design
 * Description:       Jaunais MarMar Candles dizains virs Blocksy tēmas: krāsas, šrifti Marcellus + Figtree (no sava servera), pogas, liesmiņas, augšējā josla, produkta lapas termiņu bloks un lipīgā “Ielikt grozā” josla. Izslēdzot spraudni — atgriežas iepriekšējais izskats.
 * Version:           1.14.1
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Design {

	const VER = '1.14.1';

	/** Preces, kuras nerādām blokā “Bieži pērk kopā” (pakalpojumi, papildinājumi). */
	const TOGETHER_EXCLUDE = array( 11691, 11024 ); // Dzintara putekļi, Individuālā dizaina izstrāde

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'preload' ), 1 );
		add_action( 'wp_body_open', array( __CLASS__, 'topbar' ), 5 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'eta_box' ), 25 );
		// trust_row nav vajadzīga: produkta lapā jau ir MarMar UX informācijas bloks.
		add_action( 'wp_footer', array( __CLASS__, 'reveal_inline' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'sticky_bar' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'whatsapp' ), 21 );
		// Sīkdatņu josla (Blocksy) — kartīte zīmola krāsās, «Nē, paldies» kā saite (14.11.2026).
		add_action( 'wp_footer', array( __CLASS__, 'cookie_banner' ), 999 );
		add_filter( 'gettext', array( __CLASS__, 'blocksy_lv' ), 20, 3 );
		// Dalīšanās pogas blogā (23.11.2026): bez ārējiem skriptiem.
		add_filter( 'the_content', array( __CLASS__, 'share_buttons' ), 30 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'theme_mod_header_placements', array( __CLASS__, 'one_logo' ), 20 );
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'express_checkbox' ), 30 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'express_on_add' ), 10, 0 );
		add_action( 'woocommerce_after_single_product_summary', array( __CLASS__, 'together' ), 12 );
		add_filter( 'woocommerce_product_tabs', array( __CLASS__, 'tabs' ), 98 );
		add_action( 'init', array( __CLASS__, 'schedule_purge' ) );
		add_action( 'mmd_daily_purge', array( __CLASS__, 'daily_purge' ) );
	}

	/** Dienas, kad mainās augšējās joslas teksti (marmar-qa-fixes season_lines / atvaļinājums) — tad iztīrām LiteSpeed kešu. */
	const PURGE_DATES = array( '2026-11-19', '2026-11-25', '2026-11-26', '2026-12-04', '2026-12-14', '2026-12-18', '2026-12-19', '2026-12-23', '2027-01-01' );

	public static function schedule_purge() {
		if ( ! wp_next_scheduled( 'mmd_daily_purge' ) ) {
			$t = new DateTimeImmutable( 'tomorrow 00:05', wp_timezone() );
			wp_schedule_event( $t->getTimestamp(), 'daily', 'mmd_daily_purge' );
		}
	}

	public static function daily_purge() {
		$today = wp_date( 'Y-m-d' );
		if ( in_array( $today, self::PURGE_DATES, true ) && get_option( 'mmd_last_purge' ) !== $today ) {
			do_action( 'litespeed_purge_all' );
			update_option( 'mmd_last_purge', $today, false );
		}
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
		$key = 'mmd_together2_' . $product->get_id();
		$ids = get_transient( $key );
		if ( ! is_array( $ids ) ) {
			$ids = array_slice( array_values( array_diff( array_map( 'intval', $product->get_cross_sell_ids() ), self::TOGETHER_EXCLUDE ) ), 0, 3 );
			if ( count( $ids ) < 3 ) {
				$cats = $product->get_category_ids();
				$more = wc_get_products(
					array(
						'status'       => 'publish',
						'limit'        => 8,
						'orderby'      => 'popularity',
						'order'        => 'DESC',
						'category'     => array_map( function ( $id ) { $t = get_term( $id ); return $t && ! is_wp_error( $t ) ? $t->slug : ''; }, $cats ),
						'exclude'      => array_merge( array( $product->get_id() ), $ids, self::TOGETHER_EXCLUDE ),
						'stock_status' => 'instock',
						'return'       => 'ids',
					)
				);
				$ids = array_slice( array_values( array_diff( array_merge( $ids, $more ), self::TOGETHER_EXCLUDE ) ), 0, 3 );
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
			. '<li><strong>Piegāde uz pakomātiem visā Latvijā</strong> — Unisend un Venipak — 2,50 €, DPD — 4,00 €, Omniva — 5,00 €; parasti 1–2 darba dienās.</li>'
			. '<li><strong>Bezmaksas piegāde</strong> ar Unisend un Venipak, ja pasūtījums ir no 50 €.</li>'
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
		echo '<div class="mmd-trust"><span>Pakomātā no 2,50 €</span><span>Bez maksas no 50 €</span><span>Min. pasūtījums 5 €</span></div>';
	}

	/** Inline lapas apakšā pirms citiem skriptiem — kartītes parādās uzreiz, negaidot jQuery u.c. */
	public static function reveal_inline() {
		$f = __DIR__ . '/assets/reveal.js';
		if ( is_readable( $f ) ) {
			echo '<script id="marmar-design-reveal">' . file_get_contents( $f ) . '</script>' . "\n"; // phpcs:ignore
		}
	}

	/** Blocksy Companion (pieteikšanās logs galvenē) nav latviešu tulkojuma. */
	const BLOCKSY_LV = array(
		'Username or Email Address' => 'Lietotājvārds vai e-pasts',
		'Password'                  => 'Parole',
		'Remember Me'               => 'Atcerēties mani',
		'Forgot Password?'          => 'Aizmirsāt paroli?',
		'Log In'                    => 'Pieslēgties',
		'Login'                     => 'Pieslēgties',
		'Sign Up'                   => 'Reģistrēties',
		'Register'                  => 'Reģistrēties',
		'Username'                  => 'Lietotājvārds',
		'Email'                     => 'E-pasts',
		'Registration confirmation will be emailed to you.' => 'Reģistrācijas apstiprinājumu nosūtīsim uz e-pastu.',
		'A link to set a new password will be sent to your email address.' => 'Saite jaunas paroles iestatīšanai tiks nosūtīta uz jūsu e-pastu.',
		'Get New Password'          => 'Saņemt jaunu paroli',
		'Back to login'             => 'Atpakaļ uz pieslēgšanos',
		'Account modal'             => 'Konta logs',
		'Close account modal'       => 'Aizvērt konta logu',
	);

	public static function blocksy_lv( $translation, $text, $domain ) {
		if ( 'blocksy-companion' === $domain && $translation === $text && isset( self::BLOCKSY_LV[ $text ] ) ) {
			return self::BLOCKSY_LV[ $text ];
		}
		return $translation;
	}

	/** WhatsApp poga (aizstāj veco čatu). Produkta lapā ziņā jau ir preces nosaukums. */
	const WHATSAPP = '37129710414';

	/** Kopīgot rakstu: Facebook, WhatsApp, Pinterest, Messenger (telefonā — sistēmas dalīšanās), kopēt saiti. */
	public static function share_buttons( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$link  = get_permalink();
		$link  = class_exists( 'MarMar_SEO' ) ? MarMar_SEO::lv_url( $link ) : $link;
		$url   = rawurlencode( $link );
		$title = rawurlencode( wp_strip_all_tags( get_the_title() ) );
		$img   = rawurlencode( (string) get_the_post_thumbnail_url( null, 'large' ) );
		$ic    = array(
			'fb' => '<path d="M14 8h2V5h-2.5C10.9 5 10 6.6 10 8.7V11H8v3h2v7h3v-7h2.4l.6-3h-3V9c0-.6.3-1 1-1z" fill="currentColor"/>',
			'wa' => '<path d="M12 3.5a8.4 8.4 0 0 0-7.2 12.8L3.7 20.5l4.3-1.1A8.4 8.4 0 1 0 12 3.5zm0 15.2c-1.3 0-2.6-.4-3.7-1l-.3-.2-2.5.7.7-2.4-.2-.3a6.8 6.8 0 1 1 6 3.2zm3.7-5.1c-.2-.1-1.2-.6-1.4-.7s-.3-.1-.5.1l-.6.8c-.1.1-.2.2-.4.1a5.6 5.6 0 0 1-2.8-2.4c-.2-.4.2-.4.6-1.2.1-.1 0-.3 0-.4l-.6-1.5c-.2-.4-.3-.3-.5-.3h-.4a.8.8 0 0 0-.6.3 2.4 2.4 0 0 0-.8 1.8 4.3 4.3 0 0 0 .9 2.2 9.6 9.6 0 0 0 3.7 3.3c1.4.6 1.9.6 2.6.5a2.2 2.2 0 0 0 1.4-1c.2-.5.2-.9.1-1z" fill="currentColor"/>',
			'pin' => '<path d="M12 3a9 9 0 0 0-3.3 17.4c-.1-.7-.1-1.8 0-2.6l1.1-4.6s-.3-.6-.3-1.4c0-1.3.8-2.3 1.7-2.3.8 0 1.2.6 1.2 1.3 0 .8-.5 2-.8 3.1-.2.9.5 1.7 1.4 1.7 1.7 0 3-1.8 3-4.4 0-2.3-1.6-3.9-4-3.9a4.1 4.1 0 0 0-4.3 4.1c0 .8.3 1.7.7 2.2l.1.3-.3 1.1c0 .2-.2.2-.4.1-1.2-.6-2-2.4-2-3.8 0-3.1 2.3-6 6.6-6 3.5 0 6.2 2.5 6.2 5.8 0 3.4-2.2 6.2-5.2 6.2-1 0-2-.5-2.3-1.1l-.6 2.4c-.2.9-.9 2-1.3 2.7A9 9 0 1 0 12 3z" fill="currentColor"/>',
			'ln' => '<path d="M10 14l4-4M8.5 11.5l-2 2a3 3 0 0 0 4.2 4.2l2-2M15.5 12.5l2-2a3 3 0 0 0-4.2-4.2l-2 2" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round"/>',
			'sh' => '<path d="M12 4v11M8 8l4-4 4 4M6 13v5a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-5" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
		);
		$svg = function ( $k ) use ( $ic ) {
			return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">' . $ic[ $k ] . '</svg>';
		};
		$links = array(
			array( 'fb', 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . $url ),
			array( 'wa', 'WhatsApp', 'https://wa.me/?text=' . $title . '%20' . $url ),
			array( 'pin', 'Pinterest', 'https://pinterest.com/pin/create/button/?url=' . $url . '&media=' . $img . '&description=' . $title ),
		);
		$h  = '<div class="mmd-share" role="group" aria-label="Dalīties"><span class="mmd-share-t">Patika raksts? Padalieties:</span>';
		$h .= '<button type="button" class="mmd-share-b mmd-share-native" hidden aria-label="Dalīties">' . $svg( 'sh' ) . '<span>Dalīties</span></button>';
		foreach ( $links as $l ) {
			$h .= '<a class="mmd-share-b mmd-share-' . $l[0] . '" href="' . esc_url( $l[2] ) . '" target="_blank" rel="noopener nofollow" aria-label="' . esc_attr( $l[1] ) . '">' . $svg( $l[0] ) . '<span>' . esc_html( $l[1] ) . '</span></a>';
		}
		$h .= '<button type="button" class="mmd-share-b mmd-share-copy" aria-label="Kopēt saiti">' . $svg( 'ln' ) . '<span>Kopēt saiti</span></button></div>';
		$h .= '<style>.mmd-share{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:32px 0 8px;padding:16px;border-radius:14px;background:#FAF3F0}.mmd-share-t{flex-basis:100%;font-size:14px;color:#735360;font-weight:600}.mmd-share-b{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:999px;border:1px solid #e8d8d2;background:#fff;color:#504E4A;font-size:14px;line-height:1;text-decoration:none;cursor:pointer}.mmd-share-b:hover{border-color:#B9605F;color:#B9605F}.mmd-share-b[hidden]{display:none}.mmd-share-native{background:#B9605F;border-color:#B9605F;color:#fff}.mmd-share-native:hover{color:#fff}</style>';
		$h .= '<script>(function(){var w=document.currentScript.previousElementSibling.previousElementSibling;var n=w.querySelector(".mmd-share-native");if(navigator.share&&matchMedia("(max-width:999px)").matches){n.hidden=false;n.addEventListener("click",function(){navigator.share({title:document.title,url:location.href}).catch(function(){});});}var c=w.querySelector(".mmd-share-copy");c.addEventListener("click",function(){var u=location.href;(navigator.clipboard?navigator.clipboard.writeText(u):Promise.reject()).then(function(){c.querySelector("span").textContent="Saite nokopēta ✓";setTimeout(function(){c.querySelector("span").textContent="Kopēt saiti";},2500);}).catch(function(){prompt("Kopējiet saiti:",u);});});})();</script>';
		return $content . $h;
	}

	public static function cookie_banner() {
		$flame = '<svg class="mm-ck-flame" viewBox="0 0 18 24" aria-hidden="true"><path d="M9 1C11 6 15 8 15 14a6 6 0 0 1-12 0c0-3 2-5 3-6 0 3 1 4 2 4C8 9 7 5 9 1z" fill="#D68684"/><path d="M9 11c1 2 3 3 3 5a3 3 0 0 1-6 0c0-1.5 1-2.5 1.5-3 .3 1 .8 1.5 1.5 1.5-.3-1.3-.5-2.3 0-3.5z" fill="#FAF3F0"/></svg>';
		$text  = $flame . '<span class="mm-ck-title">Sīkdatnes</span>Tās palīdz vietnei darboties ērti un mums saprast, kuras sveces jums patīk, lai rādītu tieši tās.';
		?>
<style id="mm-cookie-banner">
.cookie-notification{left:12px!important;right:12px!important;bottom:12px!important;width:auto!important;max-width:460px;margin:0 auto;background:#FAF3F0!important;border:1px solid #eee3e1;border-radius:20px!important;box-shadow:0 10px 30px rgba(115,83,96,.18)!important;padding:18px 18px 12px!important}
.cookie-notification .container{display:block!important;padding:0!important;max-width:none!important;background:transparent!important;box-shadow:none!important}
.cookie-notification .ct-cookies-content{font-size:15px!important;line-height:1.45!important;color:#504E4A!important;margin:0 0 14px!important;text-align:center!important;width:100%!important;max-width:none!important}
.cookie-notification .mm-ck-title{display:block;font-family:Marcellus,serif;text-transform:uppercase;letter-spacing:.06em;color:#735360;font-size:16px;margin-bottom:6px}
.cookie-notification .mm-ck-flame{display:block;width:18px;height:24px;margin:0 auto 6px}
.cookie-notification .ct-button-group{display:flex!important;flex-direction:column;gap:4px!important;width:100%}
.cookie-notification .ct-cookies-accept-button{width:100%!important;min-height:48px;border-radius:999px!important;background:#B9605F!important;color:#fff!important;font-size:15px!important;letter-spacing:.08em}
.cookie-notification .ct-cookies-decline-button{width:100%!important;background:none!important;border:0!important;color:#735360!important;text-decoration:underline;text-underline-offset:3px;font-size:14px!important;letter-spacing:0!important;text-transform:none!important;min-height:40px}
</style>
<script id="mm-cookie-banner-js">
(function () {
	var TEXT = <?php echo wp_json_encode( $text ); ?>, NO = 'Nē, paldies';
	function fix(root) {
		if (!root) return;
		var c = root.querySelector('.ct-cookies-content'), d = root.querySelector('.ct-cookies-decline-button');
		if (c && !c.querySelector('.mm-ck-title')) c.innerHTML = TEXT;
		if (d && d.textContent.trim() !== NO) d.textContent = NO;
	}
	var t = document.getElementById('ct-cookies-consent-template');
	if (t && t.content) fix(t.content);
	fix(document.querySelector('.cookie-notification'));
	new MutationObserver(function () { var n = document.querySelector('.cookie-notification'); if (n) fix(n); }).observe(document.body, { childList: true });
})();
</script>
		<?php
	}

	public static function whatsapp() {
		if ( is_admin() || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
			return;
		}
		$text = 'Sveiki! Man ir jautājums';
		if ( function_exists( 'is_product' ) && is_product() ) {
			$text .= ' par: ' . get_the_title( get_queried_object_id() ) . ' (' . get_permalink( get_queried_object_id() ) . ')';
		}
		$url = 'https://wa.me/' . self::WHATSAPP . '?text=' . rawurlencode( $text );
		echo '<a class="mmd-wa" href="' . esc_url( $url ) . '" target="_blank" rel="noopener" aria-label="Rakstīt WhatsApp"><svg viewBox="0 0 32 32" width="28" height="28" aria-hidden="true"><path fill="#fff" d="M16.04 3C8.86 3 3.03 8.82 3.03 15.99c0 2.29.6 4.53 1.74 6.5L3 29l6.68-1.75a13 13 0 0 0 6.35 1.62h.01c7.17 0 13-5.83 13-13S23.2 3 16.04 3zm0 23.68h-.01a10.8 10.8 0 0 1-5.5-1.5l-.4-.24-3.96 1.04 1.06-3.86-.26-.4a10.77 10.77 0 0 1-1.65-5.73c0-5.96 4.85-10.81 10.82-10.81 2.89 0 5.6 1.13 7.64 3.17a10.73 10.73 0 0 1 3.16 7.65c0 5.96-4.85 10.8-10.9 10.8zm5.93-8.09c-.33-.16-1.93-.95-2.23-1.06-.3-.11-.52-.16-.73.17-.22.32-.84 1.05-1.03 1.27-.19.22-.38.24-.7.08-.33-.16-1.38-.51-2.62-1.62-.97-.86-1.62-1.93-1.81-2.25-.19-.33-.02-.5.14-.66.15-.15.33-.38.49-.57.16-.19.22-.33.33-.54.11-.22.05-.41-.03-.57-.08-.16-.73-1.77-1-2.42-.27-.64-.54-.55-.73-.56h-.62c-.22 0-.57.08-.87.41-.3.32-1.14 1.11-1.14 2.71s1.17 3.15 1.33 3.36c.16.22 2.3 3.51 5.57 4.92.78.34 1.39.54 1.86.69.78.25 1.49.21 2.05.13.63-.09 1.93-.79 2.2-1.55.27-.76.27-1.41.19-1.55-.08-.13-.3-.21-.62-.37z"/></svg><span>WhatsApp</span></a>';
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
