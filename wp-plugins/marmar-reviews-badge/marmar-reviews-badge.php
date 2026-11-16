<?php
/**
 * Plugin Name:       MarMar Reviews Badge
 * Description:       Свой виджет отзывов Google в стиле сайта: блок «Ko saka klienti» (шорткод [marmar_reviews]) и компактный блок под кнопкой «в корзину» на товаре. Рейтинг и число отзывов берутся из Trustindex (уже скачанные отзывы Google), без внешних скриптов. Можно задать вручную.
 * Version:           1.1.6
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Reviews_Badge {

	const OPTION = 'marmar_reviews_badge';

	public static function init() {
		// 1.1.0: вместо строки под ценой — блок с отзывом под кнопкой «в корзину».
		add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'render_product' ), 15 );
		add_shortcode( 'marmar_reviews', array( __CLASS__, 'section' ) );
		// Polosa pod banerom na glavnoj: «· 18 Google atsauksmes» → aktualnoe chislo.
		add_filter( 'the_content', array( __CLASS__, 'fix_counts' ), 30 );
		add_action( 'wp_head', array( __CLASS__, 'css' ), 50 );
		add_shortcode( 'marmar_google_rating', array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	private static function settings() {
		return wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			array(
				'enabled' => 'yes',
				'rating'  => '',
				'count'   => '',
				'url'     => 'https://g.page/r/CXL0frKfTFEFEBM',
				'min'     => '4.5',
			)
		);
	}

	/**
	 * Рейтинг из Trustindex: сначала сводка профиля, иначе — среднее по скачанным отзывам.
	 *
	 * @return array{rating:float,count:int,source:string}|null
	 */
	public static function detect() {
		$cached = get_transient( 'marmar_reviews_badge_detect2' );
		if ( is_array( $cached ) ) {
			return $cached ?: null;
		}
		$found   = null;
		$details = get_option( 'trustindex-google-page-details' );
		if ( is_array( $details ) && ! empty( $details['rating_score'] ) && ! empty( $details['rating_number'] ) ) {
			$found = array( 'rating' => (float) $details['rating_score'], 'count' => max( (int) $details['rating_number'], count( self::archive() ) ), 'source' => 'Trustindex: профиль Google' );
		}
		if ( ! $found ) {
			global $wpdb;
			$table = $wpdb->prefix . 'trustindex_google_reviews';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				$row = $wpdb->get_row( "SELECT COUNT(*) n, AVG(rating) r FROM {$table} WHERE rating > 0" ); // phpcs:ignore
				if ( $row && (int) $row->n > 0 ) {
					$found = array( 'rating' => (float) $row->r, 'count' => (int) $row->n, 'source' => 'Trustindex: скачанные отзывы' );
				}
			}
		}
		set_transient( 'marmar_reviews_badge_detect2', $found ? $found : array(), 6 * HOUR_IN_SECONDS );
		return $found;
	}

	/** @return array{rating:float,count:int,url:string}|null */
	public static function data() {
		$s = self::settings();
		if ( 'yes' !== $s['enabled'] ) {
			return null;
		}
		if ( '' !== $s['rating'] && '' !== $s['count'] ) {
			$d = array( 'rating' => (float) str_replace( ',', '.', $s['rating'] ), 'count' => (int) $s['count'] );
		} else {
			$d = self::detect();
		}
		if ( ! $d || $d['count'] < 1 || $d['rating'] < (float) $s['min'] ) {
			return null; // низкий рейтинг не показываем
		}
		return array( 'rating' => $d['rating'], 'count' => $d['count'], 'url' => $s['url'] );
	}

	private static function plural( $n ) {
		// Латышский: 1, 21, 31… — «atsauksme», иначе «atsauksmes».
		return ( 1 === $n % 10 && 11 !== $n % 100 ) ? 'atsauksme' : 'atsauksmes';
	}

	public static function html() {
		$d = self::data();
		if ( ! $d ) {
			return '';
		}
		$score = number_format( round( $d['rating'], 1 ), 1, ',', '' );
		$pct   = max( 0, min( 100, $d['rating'] / 5 * 100 ) );
		$label = sprintf( 'Google vērtējums %s no 5, %d %s', $score, $d['count'], self::plural( $d['count'] ) );
		$inner = '<span class="mmrb-stars" aria-hidden="true"><span class="mmrb-stars-fill" style="width:' . esc_attr( round( $pct, 1 ) ) . '%">★★★★★</span>★★★★★</span>'
			. '<span class="mmrb-score">' . esc_html( $score ) . '</span>'
			. '<span class="mmrb-sep" aria-hidden="true">·</span>'
			. '<span class="mmrb-count">' . esc_html( $d['count'] . ' ' . self::plural( $d['count'] ) ) . ' <span class="mmrb-g">Google</span></span>';
		if ( $d['url'] ) {
			return '<a class="mmrb" href="' . esc_url( $d['url'] ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $label ) . '">' . $inner . '</a>';
		}
		return '<span class="mmrb" role="img" aria-label="' . esc_attr( $label ) . '">' . $inner . '</span>';
	}

	public static function render() {
		echo self::html(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private static $css_done = false;

	public static function shortcode() {
		$html = self::html();
		if ( '' !== $html && ! self::$css_done ) {
			self::$css_done = true;
			$html = self::style() . $html; // шорткод на обычной странице — стили вместе с ним
		}
		return $html;
	}

	public static function css() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		self::$css_done = true;
		echo self::style(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private static function style() {
		return '<style id="marmar-reviews-badge">'
			. '.mmrb{display:inline-flex;align-items:center;gap:6px;margin:6px 0 14px;font-size:14px;color:inherit;text-decoration:none;line-height:1}'
			. 'a.mmrb:hover .mmrb-count{text-decoration:underline}'
			. '.mmrb-stars{position:relative;display:inline-block;color:#e3d6d4;letter-spacing:1px;font-size:15px}'
			. '.mmrb-stars-fill{position:absolute;left:0;top:0;overflow:hidden;white-space:nowrap;color:#e7a93c}'
			. '.mmrb-score{font-weight:600}.mmrb-sep{opacity:.5}.mmrb-count{opacity:.85}.mmrb-g{font-weight:600}'
			. '.has-text-align-center .mmrb,.wp-block-shortcode .mmrb{display:flex;justify-content:center}'
			. '</style>';
	}


	/* ---------------- Свой виджет отзывов (1.1.0, 16.11.2026) ---------------- */

	const ARCHIVE = 'marmar_reviews_archive';

	/**
	 * Все когда-либо скачанные Trustindex отзывы (бесплатный Trustindex хранит только 10 последних).
	 * @return array<string,array{user:string,text:string,rating:int,date:string}>
	 */
	public static function archive() {
		static $memo = null;
		if ( null !== $memo ) {
			return $memo;
		}
		$arch = (array) get_option( self::ARCHIVE, array() );
		global $wpdb;
		$table = $wpdb->prefix . 'trustindex_google_reviews';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			$rows    = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A ); // phpcs:ignore
			$changed = false;
			foreach ( (array) $rows as $r ) {
				$user = trim( (string) ( $r['user'] ?? $r['name'] ?? '' ) );
				$date = substr( (string) ( $r['date'] ?? $r['created_at'] ?? '' ), 0, 10 );
				$key  = ! empty( $r['reviewId'] ) ? (string) $r['reviewId'] : md5( $user . $date );
				if ( ! empty( $r['hidden'] ) ) {
					if ( isset( $arch[ $key ] ) ) { unset( $arch[ $key ] ); $changed = true; }
					continue;
				}
				$item = array( 'user' => $user, 'text' => trim( wp_strip_all_tags( (string) ( $r['text'] ?? '' ) ) ), 'rating' => (int) ( $r['rating'] ?? 5 ), 'date' => $date );
				if ( ! isset( $arch[ $key ] ) || $arch[ $key ] !== $item ) { $arch[ $key ] = $item; $changed = true; }
			}
			if ( $changed ) {
				uasort( $arch, function ( $a, $b ) { return strcmp( $b['date'], $a['date'] ); } );
				update_option( self::ARCHIVE, $arch, false );
			}
		}
		$memo = $arch;
		return $arch;
	}

	/** Только отзывы с текстом и 5★ (для карточек). */
	private static function texts() {
		return array_values( array_filter( self::archive(), function ( $r ) { return $r['rating'] >= 5 && mb_strlen( $r['text'] ) >= 15; } ) );
	}

	private static function short_name( $user ) {
		$parts = preg_split( '/\s+/u', trim( $user ) );
		$first = mb_convert_case( (string) array_shift( $parts ), MB_CASE_TITLE );
		$last  = $parts ? mb_strtoupper( mb_substr( (string) end( $parts ), 0, 1 ) ) . '.' : '';
		return trim( $first . ' ' . $last );
	}

	private static function month( $date ) {
		$m = array( '', 'janvāris', 'februāris', 'marts', 'aprīlis', 'maijs', 'jūnijs', 'jūlijs', 'augusts', 'septembris', 'oktobris', 'novembris', 'decembris' );
		$t = strtotime( $date );
		return $t ? $m[ (int) gmdate( 'n', $t ) ] . ' ' . gmdate( 'Y', $t ) : '';
	}

	private static function review_url() {
		return 'https://g.page/r/CXL0frKfTFEFEBM/review';
	}

	private static function widget_css() {
		return '<style id="mmrw">'
			. '.mmrw{background:#FAF3F0;padding:40px 0 34px;margin:0 calc(50% - 50vw);width:100vw}'
			. '.mmrw-in{max-width:1180px;margin:0 auto;position:relative}'
			. '.mmrw-nav{display:none}@media(min-width:690px){.mmrw-nav{display:grid;place-items:center;position:absolute;top:50%;width:44px;height:44px;border-radius:50%;border:1px solid #efe2de;background:#fff;color:#735360;font-size:20px;cursor:pointer;box-shadow:0 4px 14px rgba(115,83,96,.12);z-index:2;padding:0}.mmrw-prev{left:-24px}.mmrw-next{right:-24px}.mmrw-nav:hover{background:#B9605F;color:#fff}}'
			. '.mmrw-dots i{cursor:pointer}'
			. '.mmrw-head{text-align:center;padding:0 16px}'
			. '.mmrw-flame{width:16px;height:22px;display:block;margin:0 auto 8px}'
			. '.mmrw h2{font-family:Marcellus,serif!important;font-weight:400;font-size:24px!important;letter-spacing:.08em;text-transform:uppercase;color:#735360!important;margin:0 0 8px!important}'
			. '.mmrw-sum{display:inline-flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:8px;font-size:15px;color:#504E4A}'
			. '.mmrw-sum b{font-size:19px;color:#735360}.mmrw-st{color:#E0A84B;letter-spacing:1px}.mmrw-g{font-weight:600;color:#5f6368}'
			. '.mmrw-track{display:flex;align-items:stretch;gap:14px;overflow-x:auto;scroll-snap-type:x mandatory;padding:22px 16px 8px;scrollbar-width:none;-webkit-overflow-scrolling:touch}'
			. '.mmrw-track::-webkit-scrollbar{display:none}'
			. '.mmrw-card{flex:0 0 78%;max-width:340px;scroll-snap-align:start;background:#fff;border:1px solid #efe2de;border-radius:18px;padding:16px;display:flex!important;flex-direction:column!important;gap:10px;box-shadow:0 4px 14px rgba(115,83,96,.06)}'
			. '@media(min-width:690px){.mmrw-card{flex-basis:31%}}'
			. '.mmrw-top{display:flex;justify-content:space-between;align-items:center}'
			. '.mmrw-card p{flex:1 1 auto;margin:0!important;font-size:15px;line-height:1.5;color:#504E4A;display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden}'
			. '.mmrw-who{display:flex;align-items:center;gap:10px;margin-top:auto;padding-top:4px;text-align:left!important}'
			. '.mmrw-av{width:32px;height:32px;border-radius:50%;background:#f1dcd9;color:#735360;display:grid;place-items:center;font-weight:600;font-size:13px;flex:0 0 32px}'
			. '.mmrw-who span{font-size:13.5px;line-height:1.25;color:#504E4A}.mmrw-who small{display:block;color:#9a8f8c;font-size:12px}'
			. '.mmrw-dots{display:flex;justify-content:center;gap:6px;margin:10px 0 20px}.mmrw-dots i{width:6px;height:6px;border-radius:50%;background:#e2c9c6;transition:all .2s}.mmrw-dots i.on{background:#B9605F;width:16px;border-radius:4px}'
			. '.mmrw-acts{display:flex;flex-direction:column;align-items:center;gap:12px;padding:0 16px}'
			. 'a.mmrw-b1{display:block;width:100%;max-width:340px;text-align:center;background:#B9605F;color:#fff!important;text-decoration:none;font-weight:600;font-size:14px;letter-spacing:.08em;text-transform:uppercase;padding:14px;border-radius:999px}'
			. 'a.mmrw-b2{color:#735360!important;font-size:14px;text-decoration:underline;text-underline-offset:3px}'
			. '.mmrp{margin:14px 0 6px;background:#fff;border:1px solid #efe2de;border-radius:16px;padding:14px 16px}'
			. '.mmrp-row{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px;font-size:13.5px;color:#504E4A}'
			. '.mmrp-row b{color:#735360}.mmrp-row a{color:#735360!important;font-size:13px;white-space:nowrap}'
			. '.mmrp q{display:block;font-size:14.5px;line-height:1.5;color:#504E4A;quotes:"«" "»"}'
			. '.mmrp small{display:block;margin-top:6px;color:#9a8f8c;font-size:12px}'
			. '</style>';
	}

	private static $wcss = false;
	private static function wcss() {
		if ( self::$wcss ) { return ''; }
		self::$wcss = true;
		return self::widget_css();
	}

	private static function stars_txt( $n = 5 ) {
		return str_repeat( '★', max( 1, min( 5, (int) $n ) ) );
	}

	public static function fix_counts( $html ) {
		if ( false === strpos( (string) $html, 'Google atsauksm' ) ) {
			return $html;
		}
		$d = self::data();
		if ( ! $d ) {
			return $html;
		}
		return preg_replace( '/·\s*\d+\s+Google atsauksm\w*/u', '· ' . $d['count'] . ' Google ' . self::plural( $d['count'] ), $html );
	}

	/** Блок «Ko saka klienti» — шорткод [marmar_reviews]. */
	public static function section( $atts = array() ) {
		$d    = self::data();
		$list = array_slice( self::texts(), 0, 12 );
		if ( ! $d || ! $list ) {
			return '';
		}
		$score = number_format( round( $d['rating'], 1 ), 1, ',', '' );
		$flame = '<svg class="mmrw-flame" viewBox="0 0 18 24" aria-hidden="true"><path d="M9 1C11 6 15 8 15 14a6 6 0 0 1-12 0c0-3 2-5 3-6 0 3 1 4 2 4C8 9 7 5 9 1z" fill="#D68684"/><path d="M9 11c1 2 3 3 3 5a3 3 0 0 1-6 0c0-1.5 1-2.5 1.5-3 .3 1 .8 1.5 1.5 1.5-.3-1.3-.5-2.3 0-3.5z" fill="#FAF3F0"/></svg>';
		$h     = self::wcss() . '<section class="mmrw" aria-label="Klientu atsauksmes"><div class="mmrw-in"><div class="mmrw-head">' . $flame . '<h2>Ko saka klienti</h2>'
			. '<div class="mmrw-sum"><b>' . esc_html( $score ) . '</b><span class="mmrw-st" aria-hidden="true">★★★★★</span><span>' . esc_html( $d['count'] . ' ' . self::plural( $d['count'] ) ) . '</span><span class="mmrw-g">Google</span></div></div><div class="mmrw-track">';
		foreach ( $list as $r ) {
			$name = self::short_name( $r['user'] );
			$h   .= '<figure class="mmrw-card" style="margin:0"><div class="mmrw-top"><span class="mmrw-st" aria-label="' . esc_attr( $r['rating'] . ' no 5' ) . '">' . self::stars_txt( $r['rating'] ) . '</span></div>'
				. '<p>' . esc_html( $r['text'] ) . '</p>'
				. '<figcaption class="mmrw-who"><span class="mmrw-av" aria-hidden="true">' . esc_html( mb_substr( $name, 0, 1 ) ) . '</span><span>' . esc_html( $name ) . '<small>' . esc_html( self::month( $r['date'] ) ) . '</small></span></figcaption></figure>';
		}
		$h .= '</div><button type="button" class="mmrw-nav mmrw-prev" aria-label="Iepriekšējā atsauksme">←</button><button type="button" class="mmrw-nav mmrw-next" aria-label="Nākamā atsauksme">→</button><div class="mmrw-dots" aria-hidden="true">' . str_repeat( '<i></i>', count( $list ) ) . '</div>'
			. '<div class="mmrw-acts"><a class="mmrw-b1" href="' . esc_url( self::review_url() ) . '" target="_blank" rel="noopener">⭐ Atstāt atsauksmi</a>'
			. '<a class="mmrw-b2" href="' . esc_url( $d['url'] ) . '" target="_blank" rel="noopener">Lasīt visas atsauksmes Google →</a></div></div></section>'
			. '<script>(function(){document.querySelectorAll(".mmrw").forEach(function(s){var t=s.querySelector(".mmrw-track"),d=s.querySelectorAll(".mmrw-dots i");if(!t||!d.length)return;function u(){var c=t.children[0],w=c?c.getBoundingClientRect().width+14:1,i=Math.round(t.scrollLeft/w);d.forEach(function(x,k){x.classList.toggle("on",k===Math.min(i,d.length-1));});}t.addEventListener("scroll",u,{passive:true});u();function w(){var c=t.children[0];return c?c.getBoundingClientRect().width+14:300;}var pv=s.querySelector(".mmrw-prev"),nx=s.querySelector(".mmrw-next");if(pv)pv.addEventListener("click",function(){t.scrollBy({left:-w(),behavior:"smooth"});});if(nx)nx.addEventListener("click",function(){t.scrollBy({left:w(),behavior:"smooth"});});d.forEach(function(x,k){x.addEventListener("click",function(){t.scrollTo({left:k*w(),behavior:"smooth"});});});});})();</script>';
		return $h;
	}

	/** Товар: рейтинг + один короткий отзыв под кнопкой «в корзину». */
	public static function render_product() {
		$d    = self::data();
		$list = array_values( array_filter( self::texts(), function ( $r ) { return mb_strlen( $r['text'] ) <= 220; } ) );
		if ( ! $d ) {
			return;
		}
		$score = number_format( round( $d['rating'], 1 ), 1, ',', '' );
		$h     = self::wcss() . '<div class="mmrp"><div class="mmrp-row"><span><span class="mmrw-st" aria-hidden="true">★★★★★</span> <b>' . esc_html( $score ) . '</b> · ' . esc_html( $d['count'] . ' ' . self::plural( $d['count'] ) ) . ' Google</span><a href="' . esc_url( $d['url'] ) . '" target="_blank" rel="noopener">Visas →</a></div>';
		if ( $list ) {
			$r  = $list[ (int) get_the_ID() % count( $list ) ];
			$h .= '<q>' . esc_html( $r['text'] ) . '</q><small>— ' . esc_html( self::short_name( $r['user'] ) . ', ' . self::month( $r['date'] ) ) . '</small>';
		}
		echo $h . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/* ---------------- Настройки ---------------- */

	public static function register() {
		register_setting(
			'marmar_reviews_badge',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $in ) {
					delete_transient( 'marmar_reviews_badge_detect2' );
					$in = is_array( $in ) ? $in : array();
					return array(
						'enabled' => isset( $in['enabled'] ) ? 'yes' : 'no',
						'rating'  => preg_replace( '/[^0-9.,]/', '', (string) ( $in['rating'] ?? '' ) ),
						'count'   => preg_replace( '/[^0-9]/', '', (string) ( $in['count'] ?? '' ) ),
						'url'     => esc_url_raw( (string) ( $in['url'] ?? '' ) ),
						'min'     => preg_replace( '/[^0-9.]/', '', (string) ( $in['min'] ?? '4.5' ) ),
					);
				},
			)
		);
	}

	public static function menu() {
		add_options_page( 'MarMar Reviews Badge', 'MarMar Reviews Badge', 'manage_options', 'marmar-reviews-badge', array( __CLASS__, 'page' ) );
	}

	public static function page() {
		$s   = self::settings();
		$o   = self::OPTION;
		$det = self::detect();
		echo '<div class="wrap"><h1>MarMar Reviews Badge</h1>';
		echo '<p>Найдено автоматически: ' . ( $det ? '<strong>' . esc_html( number_format( $det['rating'], 1 ) . ' ★, ' . $det['count'] . ' отзывов' ) . '</strong> (' . esc_html( $det['source'] ) . ')' : '<strong>ничего</strong> — заполните вручную' ) . '</p>';
		echo '<p>Предпросмотр: ' . self::html() . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<form method="post" action="options.php">';
		settings_fields( 'marmar_reviews_badge' );
		echo '<table class="form-table">';
		echo '<tr><th>Показывать на странице товара</th><td><label><input type="checkbox" name="' . esc_attr( $o ) . '[enabled]" value="yes" ' . checked( 'yes', $s['enabled'], false ) . '> да</label></td></tr>';
		echo '<tr><th>Рейтинг вручную</th><td><input name="' . esc_attr( $o ) . '[rating]" value="' . esc_attr( $s['rating'] ) . '" class="small-text" placeholder="5.0"> и число отзывов <input name="' . esc_attr( $o ) . '[count]" value="' . esc_attr( $s['count'] ) . '" class="small-text"><p class="description">Пусто — берём из Trustindex автоматически (обновление раз в 6 часов).</p></td></tr>';
		echo '<tr><th>Ссылка на отзывы</th><td><input name="' . esc_attr( $o ) . '[url]" value="' . esc_attr( $s['url'] ) . '" class="regular-text"></td></tr>';
		echo '<tr><th>Не показывать, если рейтинг ниже</th><td><input name="' . esc_attr( $o ) . '[min]" value="' . esc_attr( $s['min'] ) . '" class="small-text"></td></tr>';
		echo '</table>';
		submit_button( 'Сохранить' );
		echo '</form><p>Шорткод для других мест (например, корзины): <code>[marmar_google_rating]</code></p></div>';
	}
}

add_action( 'plugins_loaded', array( 'MarMar_Reviews_Badge', 'init' ) );
