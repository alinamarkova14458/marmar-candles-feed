<?php
/**
 * Plugin Name:       MarMar Speed
 * Description:       Ātruma uzlabojumi: galvenā attēla (LCP) priekšielāde, Montserrat no sava servera (nevis Google), produktu video ielādējas tikai pēc “play”; piegādes, CF7 un WPForms faili tikai tur, kur vajag.
 * Version:           1.3.3
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Speed {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'buffer' ), 1 );
		add_filter( 'wp_video_shortcode', array( __CLASS__, 'video_shortcode' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'drop_shipping_assets' ), 9999 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'drop_shipping_assets' ), 9999 );
		add_action( 'wp_print_styles', array( __CLASS__, 'drop_shipping_assets' ), 9999 );
		add_filter( 'style_loader_tag', array( __CLASS__, 'drop_style_tag' ), 99, 4 );
		add_action( 'wp_head', array( __CLASS__, 'montserrat_alias' ), 2 );
		add_filter( 'script_loader_tag', array( __CLASS__, 'drop_script_tag' ), 99, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'voice_stub' ), 1 );
	}

	/**
	 * GPTranslate “lasītājs” (balss) ir izslēgts (chatgptEnableReader = 0), bet tā faili
	 * ielādējās katrā lapā: responsivevoice.js (~106 KB), Bootstrap toast + dom.min.js un
	 * bootstrap.min.css (~230 KB). Tie vajadzīgi tikai lasītājam. Tulkošanu un valodu
	 * pārslēdzēju (gptranslate.js, jsonrepair) neaiztiekam.
	 * Ja lasītāju kādreiz ieslēgs — izņemt šos rokturus no saraksta.
	 */
	const GPT_READER_SCRIPTS = array( 'gptranslate-responsivevoice', 'gptranslate-bstoast' );
	const GPT_READER_STYLES  = array( 'bootstrap-css' );

	public static function drop_script_tag( $tag, $handle ) {
		if ( ! is_admin() && in_array( $handle, self::GPT_READER_SCRIPTS, true ) ) {
			return '';
		}
		return $tag;
	}

	/** Drošībai: ja gptranslate.js tomēr pieskaras responsiveVoice, lai nav kļūdas. */
	public static function voice_stub() {
		if ( is_admin() ) {
			return;
		}
		echo '<script id="marmar-speed-voice-stub">window.responsiveVoice=window.responsiveVoice||{setDefaultVoice:function(){},speak:function(){},cancel:function(){}};</script>' . "\n";
	}

	/**
	 * Stackable Google Fonts saiti drukā vēlu (wp_head beigās), tāpēc dequeue nestrādā.
	 * Tā ir bloķējoša CSS no cita servera (Montserrat 18 variantos) — izņemam pašu tagu.
	 */
	public static function drop_style_tag( $tag, $handle, $href = '', $media = 'all' ) {
		if ( is_admin() ) {
			return $tag;
		}
		if ( 'stackable-google-fonts' === $handle || in_array( $handle, self::GPT_READER_STYLES, true ) ) {
			return '';
		}
		if ( in_array( $handle, self::ASYNC_CSS, true ) && self::async_css_ok() && false === strpos( $tag, 'onload=' ) ) {
			$tag = str_replace( array( "media='" . $media . "'", 'media="' . $media . '"' ), "media='print' onload=\"this.media='" . esc_attr( $media ? $media : 'all' ) . "'\"", $tag );
			return $tag . '<noscript><link rel="stylesheet" href="' . esc_url( $href ) . '"></noscript>' . "\n";
		}
		return self::inline_small_css( $tag, $handle, $href, $media );
	}

	/**
	 * Stili, kas sākumlapā/rakstos/lapās vajadzīgi tikai lejāk (produktu kartīšu nozīmītes,
	 * padomi, variantu izvēle, komplekti) — tur tos ielādējam fonā. Produktu lapās,
	 * veikalā un kategorijās paliek kā bija (kartītes ir redzamas uzreiz).
	 */
	const ASYNC_CSS = array( 'woo-variation-swatches', 'hint', 'wpcbm-style', 'wpcbm-frontend', 'woosb-frontend', 'woosb-blocks' );

	private static function async_css_ok() {
		if ( ! function_exists( 'is_product' ) ) {
			return false;
		}
		return ! ( is_product() || is_shop() || is_product_taxonomy() || is_cart() || is_checkout() || is_account_page() );
	}

	/** Mazos CSS failus (līdz 8 KB) ieliekam lapā — katrs atsevišķs fails ir vēl viens bloķējošs pieprasījums. */
	const INLINE_MAX = 8192;

	private static function inline_small_css( $tag, $handle, $href, $media ) {
		if ( ! $href || false !== strpos( $tag, 'rel=\'preload\'' ) || false !== strpos( $tag, 'rel="preload"' ) || false !== strpos( $tag, 'alternate' ) ) {
			return $tag;
		}
		$url  = preg_replace( '~[?#].*$~', '', $href );
		$path = '';
		foreach ( array( content_url() => WP_CONTENT_DIR, includes_url() => ABSPATH . WPINC . '/' ) as $base => $dir ) {
			$base = set_url_scheme( $base, 'https' );
			$u    = set_url_scheme( $url, 'https' );
			if ( 0 === strpos( $u, $base ) ) {
				$path = rtrim( $dir, '/' ) . '/' . ltrim( substr( $u, strlen( $base ) ), '/' );
				break;
			}
		}
		if ( ! $path || '.css' !== substr( $path, -4 ) || false !== strpos( $path, '..' ) || ! is_readable( $path ) ) {
			return $tag;
		}
		$size = filesize( $path );
		if ( ! $size || $size > self::INLINE_MAX ) {
			return $tag;
		}
		$css = file_get_contents( $path );
		if ( false === $css || false !== stripos( $css, '@import' ) || false !== stripos( $css, '</style' ) ) {
			return $tag;
		}
		$dir = trailingslashit( dirname( $url ) );
		$css = preg_replace_callback(
			'~url\(\s*([\'"]?)([^\'")]+)\1\s*\)~i',
			function ( $m ) use ( $dir ) {
				$u = trim( $m[2] );
				if ( preg_match( '~^(data:|https?:|//|/|#)~i', $u ) ) {
					return $m[0];
				}
				return 'url("' . $dir . $u . '")';
			},
			$css
		);
		$css = preg_replace( '~/\*# sourceMappingURL=[^*]*\*/~', '', $css );
		$media = $media ? $media : 'all';
		if ( 'print' === $media ) {
			return $tag; // asinhronas ielādes triks — neaiztiekam
		}
		return '<style id="' . esc_attr( $handle ) . '-css" data-marmar-inline' . ( 'all' !== $media ? ' media="' . esc_attr( $media ) . '"' : '' ) . '>' . $css . "</style>\n";
	}

	/**
	 * Montserrat (pogas un vecie bloki tēmā) — no sava servera, nevis no Google
	 * (Stackable saite bija bloķējoša un ar 18 variantiem). Mainīgais fonts 100–900,
	 * tie paši faili, ko deva Google (SIL Open Font License).
	 * Nevar aizstāt ar Figtree: citāda burtu metrika — pogu teksts nobīdās.
	 */
	public static function montserrat_alias() {
		if ( is_admin() ) {
			return;
		}
		$base = plugins_url( 'assets/fonts/', __FILE__ );
		$ext  = 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C4,U+2113,U+2C60-2C7F,U+A720-A7FF';
		$lat  = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
		echo "<style id=\"marmar-speed-montserrat\">"
			. "@font-face{font-family:Montserrat;font-style:normal;font-weight:100 900;font-display:swap;src:url(" . esc_url( $base . 'montserrat-latin-ext.woff2' ) . ") format('woff2');unicode-range:{$ext}}"
			. "@font-face{font-family:Montserrat;font-style:normal;font-weight:100 900;font-display:swap;src:url(" . esc_url( $base . 'montserrat-latin.woff2' ) . ") format('woff2');unicode-range:{$lat}}"
			. "</style>\n";
	}

	/**
	 * Piegādes spraudņi ielādē savus skriptus katrā lapā. UNISEND skripts katrā lapā
	 * veic sinhronu AJAX pieprasījumu (fetchSavedTerminalSync) un “sasaldē” lapu 1,5–3 s.
	 * Tie vajadzīgi tikai grozā, noformēšanā un kontā.
	 */
	public static function drop_shipping_assets() {
		if ( is_admin() || ! function_exists( 'is_cart' ) ) {
			return;
		}
		// Stackable ielādē Montserrat 18 variantos, bet tēma jau ielādē Montserrat 400–800 — dublikāts.
		wp_dequeue_style( 'stackable-google-fonts' );
		self::drop_form_assets();
		if ( is_cart() || is_checkout() || is_account_page() ) {
			return;
		}
		$scripts = array( 'woo-lithuaniapost-lpexpress-terminal-block', 'woo-lithuaniapost-shipping-logo', 'woo-lithuaniapost', 'woo-shipping-dpd-baltic', 'select2' );
		foreach ( $scripts as $h ) {
			wp_dequeue_script( $h );
		}
		foreach ( array( 'woo-lithuaniapost', 'select2-css', 'select2css', 'woo-shipping-dpd-baltic' ) as $h ) {
			wp_dequeue_style( $h );
		}
	}

	/**
	 * Contact Form 7 un WPForms faili (CSS bloķē zīmēšanu) — tikai lapās, kur ir forma.
	 * Formas ir tikai lapu saturā (kāzu lapa, sadarbības lapa), ne kājenē vai logrīkos.
	 */
	private static function drop_form_assets() {
		$content = '';
		if ( is_singular() ) {
			$post    = get_queried_object();
			$content = ( $post && isset( $post->post_content ) ) ? $post->post_content : '';
		}
		if ( false === strpos( $content, 'contact-form-7' ) && false === stripos( $content, 'wpcf7' ) ) {
			foreach ( array( 'contact-form-7', 'ct-cf-7-styles', 'swv', 'googlesitekit-events-provider-contact-form-7' ) as $h ) {
				wp_dequeue_style( $h );
				wp_dequeue_script( $h );
			}
		}
		if ( false === stripos( $content, 'wpforms' ) ) {
			foreach ( array( 'ct-wpforms-styles', 'googlesitekit-events-provider-wpforms' ) as $h ) {
				wp_dequeue_style( $h );
				wp_dequeue_script( $h );
			}
		}
	}

	/** Vai drīkst atlikt analītiku/atsauksmes (ne grozā, noformēšanā, kontā — tur pirkuma notikumi). */
	private static $delay_ok = false;

	public static function buffer() {
		self::$delay_ok = function_exists( 'is_cart' ) && ! ( is_cart() || is_checkout() || is_account_page() );
		if ( is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || 'GET' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET' ) ) {
			return;
		}
		ob_start( array( __CLASS__, 'filter_html' ) );
	}

	public static function filter_html( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, '</head>' ) ) {
			return $html;
		}
		$html = self::preload_lcp( $html );
		$html = self::lazy_below_lcp( $html );
		if ( self::$delay_ok ) {
			$html = self::delay_third_party( $html );
		}
		if ( false === strpos( $html, 'vwg_video' ) ) {
			return $html;
		}
		// Tikai <video> tagos (arī JSON-escaped variantā).
		return preg_replace_callback(
			'~<video\b[^>]*>~i',
			function ( $m ) {
				return str_replace( array( 'preload="auto"', 'preload=\\"auto\\"' ), array( 'preload="none"', 'preload=\\"none\\"' ), $m[0] );
			},
			$html
		);
	}

	/**
	 * Galvenais attēls (baneris sākumlapā, produkta foto) HTML kodā ir ļoti tālu
	 * (sākumlapā ~180 KB no sākuma), tāpēc pārlūks to sāk lādēt vēlu. Pirmo attēlu ar
	 * fetchpriority="high" pieteicam <head> sākumā kā <link rel="preload">.
	 * Ja tas ir <picture> ar <source media>, piesakām katram ekrānam savu variantu.
	 */
	private static function preload_lcp( $html ) {
		$head_end = stripos( $html, '</head>' );
		if ( false !== strpos( substr( $html, 0, $head_end ), 'marmar-lcp-preload' ) ) {
			return $html;
		}
		$body = substr( $html, $head_end );
		if ( ! self::find_lcp_img( $body, $img ) ) {
			return $html;
		}
		$tag = $img[0][0];
		if ( preg_match( '~\bloading=["\']lazy~i', $tag ) ) {
			return $html;
		}
		$links = array();
		// Vai attēls ir <picture> iekšā?
		$before = substr( $body, 0, $img[0][1] );
		$pic    = strripos( $before, '<picture' );
		$sources_media = array();
		if ( false !== $pic && false === stripos( substr( $before, $pic ), '</picture' ) ) {
			preg_match_all( '~<source\b[^>]*>~i', substr( $before, $pic ), $srcs );
			foreach ( $srcs[0] as $src ) {
				$media  = self::attr( $src, 'media' );
				$srcset = self::attr( $src, 'srcset' );
				$type   = self::attr( $src, 'type' );
				if ( '' === $media || '' === $srcset || ( $type && 'image/webp' !== $type && 'image/avif' !== $type ) ) {
					continue;
				}
				$links[]         = self::link( $srcset, self::sizes( $src ), '', $media );
				$sources_media[] = $media;
			}
		}
		$srcset = self::attr( $tag, 'srcset' );
		$src    = self::attr( $tag, 'src' );
		if ( '' === $srcset && '' === $src ) {
			return $html;
		}
		$media = $sources_media ? 'not all and ' . ( 1 === count( $sources_media ) ? $sources_media[0] : '(min-width: 0px)' ) : '';
		if ( count( $sources_media ) > 1 ) {
			$media = ''; // sarežģīts gadījums — neriskējam ar dubultu ielādi
			$links = array();
		}
		$links[] = self::link( $srcset, $srcset ? self::sizes( $tag ) : '', $src, $media );
		$out     = "\n<!-- marmar-lcp-preload -->\n" . implode( "\n", $links ) . "\n";
		// Aiz <meta name="viewport">: pirms tās telefons media vaicājumus vērtē kā 980 px platu
		// ekrānu un ielādētu arī datora baneri.
		if ( preg_match( '~<meta\s[^>]*name=["\']viewport["\'][^>]*>~i', $html, $m, PREG_OFFSET_CAPTURE ) && $m[0][1] < $head_end ) {
			$pos = $m[0][1] + strlen( $m[0][0] );
		} else {
			return $html;
		}
		return substr( $html, 0, $pos ) . $out . substr( $html, $pos );
	}

	/**
	 * Attēli zem galvenā attēla bez loading atribūta (produktu režģi, kondiotoru bloks) tika
	 * lādēti uzreiz un konkurēja ar baneri. Tiem liekam loading="lazy".
	 */
	private static function lazy_below_lcp( $html ) {
		$head_end = stripos( $html, '</head>' );
		$body     = substr( $html, $head_end );
		if ( ! self::find_lcp_img( $body, $m ) ) {
			return $html;
		}
		$start = $head_end + $m[0][1] + strlen( $m[0][0] );
		$rest  = self::outside_code(
			substr( $html, $start ),
			'~<img\b(?![^>]*\bloading=)(?![^>]*\bfetchpriority=["\']high)[^>]*>~i',
			function ( $t ) {
				$tag = $t[0];
				if ( false === stripos( $tag, 'decoding=' ) ) {
					$tag = preg_replace( '~^<img\b~i', '<img decoding="async"', $tag );
				}
				return preg_replace( '~^<img\b~i', '<img loading="lazy"', $tag );
			}
		);
		return substr( $html, 0, $start ) . $rest;
	}

	/**
	 * Google tag (gtag.js, ~190 KB) un Trustindex atsauksmju ielādētājs lādējās kopā ar baneri.
	 * gtag() izsaukumi krājas dataLayer, tāpēc skriptu droši var ielādēt pēc lapas "load".
	 * Trustindex — pēc pirmās ritināšanas/pieskāriena vai 5 s pēc "load".
	 */
	private static function delay_third_party( $html ) {
		$loader = '';
		$html   = preg_replace_callback(
			'~<script\b[^>]*\bsrc=["\'](https://www\.googletagmanager\.com/gtag/js\?[^"\']+)["\'][^>]*>\s*</script>~i',
			function ( $m ) use ( &$loader ) {
				$loader .= 'mmLoad(' . wp_json_encode( html_entity_decode( $m[1] ) ) . ',0);';
				return '';
			},
			$html,
			1
		);
		$html = preg_replace_callback(
			'~<script\b[^>]*\bsrc=["\'](https://cdn\.trustindex\.io/loader\.js[^"\']*)["\'][^>]*>\s*</script>~i',
			function ( $m ) use ( &$loader ) {
				$loader .= 'mmLoad(' . wp_json_encode( html_entity_decode( $m[1] ) ) . ',1);';
				return '';
			},
			$html,
			1
		);
		if ( '' === $loader ) {
			return $html;
		}
		$js = '<script id="marmar-speed-delay">(function(){'
			. 'var q=[],u=0;function add(s){var e=document.createElement("script");e.src=s;e.async=true;document.head.appendChild(e);}'
			. 'function mmLoad(s,w){if(!w){window.addEventListener("load",function(){setTimeout(function(){add(s)},300)});return;}q.push(s);}'
			. 'function go(){if(u)return;u=1;q.forEach(add);["scroll","touchstart","mousemove","keydown"].forEach(function(n){removeEventListener(n,go)});}'
			. '["scroll","touchstart","mousemove","keydown"].forEach(function(n){addEventListener(n,go,{passive:true,once:true})});'
			. 'window.addEventListener("load",function(){setTimeout(go,5000)});'
			. $loader . '})();</script>' . "\n";
		return preg_replace( '~</body>~i', $js . '</body>', $html, 1 );
	}

	/** Pirmais <img fetchpriority="high"> ārpus skriptiem/stiliem; $m kā preg_match ar PREG_OFFSET_CAPTURE. */
	private static function find_lcp_img( $body, &$m ) {
		$parts = preg_split( '~(<(?:script|style|template|noscript|textarea)\b.*?</(?:script|style|template|noscript|textarea)\s*>)~is', $body, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return false;
		}
		foreach ( $parts as $i => $part ) {
			if ( 1 === $i % 2 ) {
				continue; // koda bloks
			}
			if ( preg_match( '~<img\b[^>]*\bfetchpriority=["\']high["\'][^>]*>~i', $part[0], $x, PREG_OFFSET_CAPTURE ) ) {
				$m = array( array( $x[0][0], $part[1] + $x[0][1] ) );
				return true;
			}
		}
		return false;
	}

	/**
	 * preg_replace_callback tikai ārpus <script>, <style>, <template>, <noscript>, <textarea>.
	 * Skriptos <img …> mēdz būt JS/JSON virknēs — tur pievienotas pēdiņas salauztu kodu
	 * ("Unexpected identifier 'lazy'").
	 */
	private static function outside_code( $html, $pattern, $callback ) {
		$parts = preg_split( '~(<(script|style|template|noscript|textarea)\b.*?</\2\s*>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return $html; // regex kļūda (piem., backtrack limit) — labāk neko nemainīt
		}
		$out = '';
		$n   = count( $parts );
		for ( $i = 0; $i < $n; $i++ ) {
			if ( 0 === $i % 3 ) {
				$out .= preg_replace_callback( $pattern, $callback, $parts[ $i ] );
			} elseif ( 1 === $i % 3 ) {
				$out .= $parts[ $i ];
			} // 2 — iekšējā grupa (taga nosaukums), izlaižam
		}
		return $out;
	}

	private static function attr( $tag, $name ) {
		return preg_match( '~\s' . $name . '=(["\'])(.*?)\1~is', $tag, $m ) ? html_entity_decode( $m[2], ENT_QUOTES ) : '';
	}

	private static function sizes( $tag ) {
		$s = trim( preg_replace( '~^\s*auto\s*,?~i', '', self::attr( $tag, 'sizes' ) ) );
		return '' === $s ? '100vw' : $s;
	}

	private static function link( $srcset, $sizes, $src, $media ) {
		$l = '<link rel="preload" as="image" fetchpriority="high"';
		if ( $src ) {
			$l .= ' href="' . esc_url( $src ) . '"';
		}
		if ( $srcset ) {
			$l .= ' imagesrcset="' . esc_attr( $srcset ) . '" imagesizes="' . esc_attr( $sizes ) . '"';
		}
		if ( $media ) {
			$l .= ' media="' . esc_attr( $media ) . '"';
		}
		return $l . '>';
	}

	/** [video] apraksta tekstā — arī nelādējam iepriekš. */
	public static function video_shortcode( $out ) {
		$out = str_replace( array( 'preload="metadata"', 'preload="auto"' ), 'preload="none"', $out );
		// Bez iepriekšējas ielādes video būtu melns — liekam produkta foto kā vāku.
		if ( false === strpos( $out, 'poster=' ) && function_exists( 'is_product' ) && is_product() ) {
			$img = get_the_post_thumbnail_url( get_queried_object_id(), 'woocommerce_single' );
			if ( $img ) {
				$out = preg_replace( '/<video\b/', '<video poster="' . esc_url( $img ) . '"', $out, 1 );
			}
		}
		return $out;
	}
}

add_action( 'plugins_loaded', array( 'MarMar_Speed', 'init' ) );
