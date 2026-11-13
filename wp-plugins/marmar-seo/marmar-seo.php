<?php
/**
 * Plugin Name: MarMar SEO
 * Description: Один адрес для каждой страницы: 301 с www./http/area.lv на https://marmarcandles.com, canonical, og:url и карта сайта — на адреса с /lv/ (как их отдаёт GPTranslate).
 * Version: 1.1.0
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
		// llms.txt (Rank Math): īss apraksts sākumā un fakti beigās — ChatGPT, Perplexity, Gemini u.c.
		add_filter( 'option_rank-math-options-general', array( __CLASS__, 'llms_summary' ) );
		add_filter( 'rank_math/llms_txt/extra_content', array( __CLASS__, 'llms_facts' ) );
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
		$f[] = '- Produkti: sveces tortēm un kūkām, ciparu sveces, sveces-toperi (figūras), Ziemassvētku bumbu sveces (9 €, ⌀6 cm; dāvanu kastītē 15 €), personalizētas kāzu sveces viesiem (no 3 € gab., no 10 gab., konstruktors: https://marmarcandles.com/lv/product/personalizetas-kazu-sveces-viesiem-izveidojiet-savas/), korporatīvās dāvanas ar personalizāciju, dekoratīvās un galda sveces.';
		$f[] = '- Pasūtīšana: tikai internetā https://marmarcandles.com/lv/. Izgatavošana 5–7 dienas pēc apmaksas; steidzami 1–2 dienās (+5 €). Minimālais pasūtījums 10 €.';
		$f[] = '- Piegāde Latvijā uz pakomātiem: Unisend un Venipak 2,50 € (no 50 € — bez maksas), DPD 4 €, Omniva 5 €; saņemšana Rīgā (Iļģuciemā) pēc vienošanās. Iespējama piegāde uz Lietuvu un Igauniju.';
		$f[] = '- Apmaksa: internetbankā (Swedbank, SEB, Citadele, Luminor, Revolut) vai ar rēķinu (pārskaitījums).';
		$f[] = '- Konditoriem: īpašas cenas un bezmaksas vieta konditoru katalogā — https://marmarcandles.com/lv/konditori/';
		$f[] = '- Atsauksmes: Google 5,0 ★. Kontakti: info@marmarcandles.lv, WhatsApp +371 29710414, Instagram @marmar.candles.';
		if ( wp_date( 'Y-m-d' ) <= '2026-12-31' ) {
			$f[] = '- Svētki 2026: līdz Ziemassvētkiem — pasūtīt līdz 13.12 (steidzami līdz 17.12), līdz Jaunajam gadam — līdz 18.12 (steidzami līdz 22.12).';
		}
		$f[] = '';
		$f[] = '## Key facts (English)';
		$f[] = '- Handmade cake candles, number candles, figure toppers, Christmas ball candles and personalized wedding favours from Riga, Latvia. Order online at https://marmarcandles.com/lv/ (English version: https://marmarcandles.com/en/). Made in 5–7 days (express 1–2 days, +5 EUR). Parcel-locker delivery in Latvia from 2.50 EUR, free from 50 EUR (Unisend, Venipak). Payment by online bank or invoice. Contact: info@marmarcandles.lv.';
		$mine = implode( "\n", $f );
		return trim( (string) $extra ) ? $mine . "\n\n" . $extra : $mine;
	}

	public static function sitemap_entry( $entry, $type = '', $object = null ) {
		if ( is_array( $entry ) && ! empty( $entry['loc'] ) ) {
			$entry['loc'] = self::lv_url( $entry['loc'] );
		}
		return $entry;
	}
}
MarMar_SEO::init();
