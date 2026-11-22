<?php
/**
 * Plugin Name: MarMar SEO
 * Description: Один адрес для каждой страницы: 301 с www./http/area.lv на https://marmarcandles.com, canonical, og:url и карта сайта — на адреса с /lv/ (как их отдаёт GPTranslate).
 * Version: 1.2.1
 * Author: MarMar Candles
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MarMar_SEO {
	const HOST = 'marmarcandles.com';
	const LANG = 'lv';
	/** Языковые префиксы GPTranslate, которые не трогаем. */
	const PREFIXES = array( 'lv', 'en', 'lt', 'et', 'ru' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'force_host' ), 0 );
		add_filter( 'rank_math/frontend/canonical', array( __CLASS__, 'lv_url' ), 99 );
		add_filter( 'rank_math/opengraph/url', array( __CLASS__, 'lv_url' ), 99 );
		add_filter( 'rank_math/sitemap/entry', array( __CLASS__, 'sitemap_entry' ), 99, 3 );
		add_filter( 'rank_math/json_ld', array( __CLASS__, 'schema_host' ), 99 );
		// llms.txt (Rank Math): īss apraksts sākumā un fakti beigās — ChatGPT, Perplexity, Gemini u.c.
		add_filter( 'option_rank-math-options-general', array( __CLASS__, 'llms_summary' ) );
		add_filter( 'rank_math/llms_txt/extra_content', array( __CLASS__, 'llms_facts' ) );
		// llms-full.txt — pilns katalogs + BUJ ИИ-агентам (1.2.0, 22.11.2026).
		add_action( 'init', array( __CLASS__, 'llms_full' ), 99 ); // pēc WooCommerce taksonomiju reģistrācijas
		add_action( 'save_post_product', array( __CLASS__, 'llms_full_flush' ) );
	}

	/** В разметке schema.org (организация, логотип) — основной адрес сайта вместо технического area.lv. (1.1.1, из master) */
	public static function schema_host( $data ) {
		array_walk_recursive(
			$data,
			function ( &$v ) {
				if ( is_string( $v ) && false !== strpos( $v, 'marmarcandles.area.lv' ) ) {
					$v = str_replace( array( 'https://marmarcandles.area.lv', 'http://marmarcandles.area.lv' ), 'https://marmarcandles.com', $v );
				}
			}
		);
		return $data;
	}

	/** www., marmarcandles.area.lv и http → https://marmarcandles.com (301). */
	public static function force_host() {
		if ( defined( 'WP_CLI' ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) || PHP_SAPI === 'cli' ) {
			return;
		}
		$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( preg_replace( '/:\d+$/', '', $_SERVER['HTTP_HOST'] ) ) : '';
		if ( '' === $host ) {
			return;
		}
		$https = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== strtolower( $_SERVER['HTTPS'] ) )
			|| ( isset( $_SERVER['SERVER_PORT'] ) && '443' == $_SERVER['SERVER_PORT'] )
			|| ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) );
		$plain_http = ! $https && isset( $_SERVER['SERVER_PORT'] ) && '80' == $_SERVER['SERVER_PORT'];
		if ( self::HOST === $host && ! $plain_http ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		header( 'X-Redirect-By: MarMar SEO' );
		header( 'Location: https://' . self::HOST . $uri, true, 301 );
		exit;
	}

	/** https://marmarcandles.com/x/ → https://marmarcandles.com/lv/x/ */
	public static function lv_url( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return $url;
		}
		$p = wp_parse_url( $url );
		if ( empty( $p['host'] ) || self::HOST !== strtolower( preg_replace( '/^www\./i', '', $p['host'] ) ) ) {
			return $url;
		}
		$path = isset( $p['path'] ) ? $p['path'] : '/';
		if ( preg_match( '#^/(' . implode( '|', self::PREFIXES ) . ')(/|$)#', $path ) ) {
			$new_path = $path;
		} elseif ( preg_match( '#^/(wp-content|wp-json|wp-admin|wp-includes)/#', $path ) || preg_match( '#\.(xml|xsl|txt)$#', $path ) ) {
			return $url;
		} else {
			$new_path = '/' . self::LANG . ( '/' === $path ? '/' : $path );
		}
		return 'https://' . self::HOST . $new_path . ( isset( $p['query'] ) ? '?' . $p['query'] : '' );
	}

	const LLMS_SUMMARY = 'MarMar Candles — handmade natural-wax candles from Riga, Latvia (roku darba sveces): cake candles, number candles (ciparu sveces), figure cake toppers, Christmas ball candles, personalized wedding guest candles and corporate gifts. Online shop marmarcandles.com, delivery to parcel lockers across Latvia from 2.50 EUR.';

	public static function llms_summary( $opts ) {
		if ( is_array( $opts ) ) {
			$opts['llms_summary'] = self::LLMS_SUMMARY;
		}
		return $opts;
	}

	public static function llms_facts( $extra ) {
		$f   = array();
		$f[] = '## Fakti par MarMar Candles (atjaunināts ' . wp_date( 'd.m.Y' ) . ')';
		$f[] = '- Kas: Latvijas zīmols, roku darba sveces no dabīgā vaska maisījuma, darbnīca Rīgā (Iļģuciems). Valoda: latviešu (arī krievu, angļu).';
		$f[] = '- Produkti: sveces tortēm un kūkām, ciparu sveces, sveces-toperi (figūras), Ziemassvētku bumbu sveces (9 €, ⌀6 cm; dāvanu kastītē 15 €), personalizētas kāzu sveces viesiem (no 3 € gab., no 10 gab., konstruktors: https://marmarcandles.com/lv/product/personalizetas-kazu-sveces-viesiem-izveidojiet-savas/), korporatīvās dāvanas ar personalizāciju; sezonā — Ziemassvētku kolekcija; ierobežotā daudzumā rievotas galda sveces.';
		$f[] = '- Pasūtīšana: tikai internetā https://marmarcandles.com/lv/. Izgatavošana 5–7 dienas pēc apmaksas; steidzami 1–2 dienās (+5 €). Minimālais pasūtījums 10 €.';
		$f[] = '- Piegāde Latvijā uz pakomātiem: Unisend un Venipak 2,50 € (no 50 € — bez maksas), DPD 4 €; saņemšana Rīgā (Iļģuciemā) pēc vienošanās. Iespējama piegāde uz Lietuvu un Igauniju.';
		$f[] = '- Apmaksa: internetbankā (Swedbank, SEB, Citadele, Luminor, Revolut) vai ar rēķinu (pārskaitījums).';
		$f[] = '- Konditoriem: īpašas cenas un bezmaksas vieta konditoru katalogā — https://marmarcandles.com/lv/konditori/';
		$f[] = '- Atsauksmes: Google 5,0 ★. Kontakti: info@marmarcandles.lv, WhatsApp +371 29710414, Instagram @marmar.candles.';
		if ( wp_date( 'Y-m-d' ) <= '2026-12-03' ) {
			$f[] = '- Atvaļinājums 26.11.–3.12.2026: pasūtījumus pieņemam, izgatavošana sākas 4.12.';
		}
		if ( wp_date( 'Y-m-d' ) <= '2026-12-31' ) {
			$f[] = '- Svētki 2026: līdz Ziemassvētkiem — pasūtīt līdz 13.12 (steidzami līdz 17.12), līdz Jaunajam gadam — līdz 18.12 (steidzami līdz 22.12).';
		}
		$f[] = '';
		$f[] = '## Key facts (English)';
		$f[] = '- Handmade cake candles, number candles, figure toppers, Christmas ball candles and personalized wedding favours from Riga, Latvia. Order online at https://marmarcandles.com/lv/ (English version: https://marmarcandles.com/en/). Made in 5–7 days (express 1–2 days, +5 EUR). Parcel-locker delivery in Latvia from 2.50 EUR, free from 50 EUR (Unisend, Venipak). Payment by online bank or invoice. Contact: info@marmarcandles.lv.';
		$f[] = '';
		$f[] = 'Full catalogue with prices and stock for AI agents: https://marmarcandles.com/llms-full.txt';
		$mine = implode( "\n", $f );
		return trim( (string) $extra ) ? $mine . "\n\n" . $extra : $mine;
	}

	public static function llms_full_flush() {
		delete_transient( 'marmar_llms_full2' );
	}

	/** /llms-full.txt: kategorijas, visas preces ar cenu un pieejamību, BUJ. Kešs 6 h. */
	public static function llms_full() {
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		if ( ! in_array( $path, array( 'llms-full.txt', 'lv/llms-full.txt' ), true ) || ! function_exists( 'wc_get_products' ) ) {
			return;
		}
		$out = get_transient( 'marmar_llms_full2' );
		if ( ! $out ) {
			$L   = array();
			$L[] = '# MarMar Candles — pilns katalogs (llms-full.txt)';
			$L[] = '';
			$L[] = '> ' . self::LLMS_SUMMARY;
			$L[] = '';
			$L[] = 'Atjaunināts: ' . wp_date( 'd.m.Y H:i' ) . ' (Rīgas laiks). Cenas EUR ar PVN. Valūta: EUR. Valsts: Latvija.';
			$L[] = '';
			$L[] = self::llms_facts( '' );
			$L[] = '';
			$L[] = '## Biežāk uzdotie jautājumi / FAQ';
			$faq = array(
				'Cik ilgi tiek izgatavots pasūtījums?' => '5–7 dienas pēc apmaksas. Steidzams pasūtījums (+5 €) — 1–2 dienās. Piegāde uz pakomātu vēl 1–2 darba dienas.',
				'Vai ir minimālais pasūtījums?' => 'Jā, 10 €. Ja grozā ir mazāk, starpība tiek pieskaitīta. Konditoriem — 30 €.',
				'Kā notiek piegāde?' => 'Tikai uz pakomātiem visā Latvijā (Unisend, Venipak 2,50 €, no 50 € bez maksas; DPD 4 €) vai saņemšana darbnīcā Rīgā, Iļģuciemā, pēc vienošanās. Kurjera nav.',
				'Kā var samaksāt?' => 'Internetbankā (Paysera: Swedbank, SEB, Citadele, Luminor, Revolut) vai pēc rēķina ar pārskaitījumu. Maksājumu kartes netiek pieņemtas.',
				'No kā ir sveces?' => 'Roku darbs no dabīgā vaska maisījuma, bez parafīna. Dabīgais vasks var notecēt — dedziniet uz paliktņa, nekad neatstājiet bez uzraudzības.',
				'Kāzu sveces viesiem?' => 'Personalizētas sveces viesiem no 3 € gab. (no 10 gab.; 10–19 gab. 5 €, 20–29 4,50 €, 30–49 4 €, 50–99 3,50 €, 100+ 3 €). Pasūtījums konstruktorā; maketu sagatavojam 3 darba dienās pēc apmaksas, izgatavošana ~2 nedēļas.',
				'Vai ir atlaides?' => 'Nē, cenas ir pastāvīgas.',
			);
			foreach ( $faq as $q => $a ) {
				$L[] = '- **' . $q . '** ' . $a;
			}
			$L[] = '';
			$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
			foreach ( (array) $cats as $c ) {
				if ( 'uncategorized' === $c->slug ) {
					continue;
				}
				$items = wc_get_products( array( 'status' => 'publish', 'limit' => 200, 'category' => array( $c->slug ), 'orderby' => 'popularity' ) );
				if ( ! $items ) {
					continue;
				}
				$L[] = '## ' . $c->name . ' — ' . self::lv_url( get_term_link( $c ) );
				foreach ( $items as $p ) {
					if ( 'hidden' === $p->get_catalog_visibility() ) {
						continue;
					}
					$price = $p->is_type( 'variable' ) ? 'no ' . wc_format_decimal( $p->get_variation_price( 'min' ), 2 ) : wc_format_decimal( $p->get_price(), 2 );
					$stock = $p->is_in_stock() ? ( $p->managing_stock() ? 'noliktavā ' . $p->get_stock_quantity() . ' gab.' : 'izgatavojam pēc pasūtījuma' ) : 'nav pieejams';
					$L[] = '- ' . wp_strip_all_tags( $p->get_name() ) . ' — ' . $price . ' € — ' . $stock . ' — ' . self::lv_url( $p->get_permalink() );
				}
				$L[] = '';
			}
			$out = implode( "\n", $L );
			set_transient( 'marmar_llms_full2', $out, 6 * HOUR_IN_SECONDS );
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $out; // phpcs:ignore
		exit;
	}

	public static function sitemap_entry( $entry, $type = '', $object = null ) {
		if ( is_array( $entry ) && ! empty( $entry['loc'] ) ) {
			$entry['loc'] = self::lv_url( $entry['loc'] );
		}
		return $entry;
	}
}
MarMar_SEO::init();
