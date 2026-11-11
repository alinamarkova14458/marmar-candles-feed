<?php
/**
 * Plugin Name:       MarMar Analytics
 * Description:       Дополняет GA4 (Site Kit) событиями воронки: view_item, add_to_cart со страницы товара, view_cart, begin_checkout. Подключает Microsoft Clarity с учётом выбора в баннере cookies.
 * Version:           1.0.0
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Analytics {

	const VERSION = '1.0.0';
	const OPTION  = 'marmar_analytics_clarity_id';

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'clarity' ), 5 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/* ---------------- Данные для событий ---------------- */

	private static function item( WC_Product $p, $qty = 1, $price = null ) {
		$item = array(
			'item_id'   => (string) ( $p->get_parent_id() ? $p->get_parent_id() : $p->get_id() ),
			'item_name' => wp_strip_all_tags( $p->get_parent_id() ? wc_get_product( $p->get_parent_id() )->get_name() : $p->get_name() ),
			'price'     => round( (float) ( null !== $price ? $price : wc_get_price_to_display( $p ) ), 2 ),
			'quantity'  => (int) $qty,
		);
		if ( $p->is_type( 'variation' ) ) {
			$item['item_variant'] = wp_strip_all_tags( wc_get_formatted_variation( $p, true, false, false ) );
		}
		$cats = wp_get_post_terms( $p->get_parent_id() ? $p->get_parent_id() : $p->get_id(), 'product_cat', array( 'fields' => 'names' ) );
		$n    = 1;
		foreach ( is_array( $cats ) ? array_slice( $cats, 0, 5 ) : array() as $cat ) {
			$item[ 1 === $n ? 'item_category' : 'item_category' . $n ] = $cat;
			$n++;
		}
		return $item;
	}

	private static function cart_payload() {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return null;
		}
		$items = array();
		foreach ( WC()->cart->get_cart() as $line ) {
			if ( empty( $line['data'] ) ) {
				continue;
			}
			$qty     = max( 1, (int) $line['quantity'] );
			$items[] = self::item( $line['data'], $qty, ( (float) $line['line_total'] + (float) $line['line_tax'] ) / $qty );
		}
		return array(
			'currency' => get_woocommerce_currency(),
			'value'    => round( (float) WC()->cart->get_cart_contents_total() + (float) WC()->cart->get_cart_contents_tax(), 2 ),
			'items'    => $items,
		);
	}

	public static function enqueue() {
		if ( ! function_exists( 'is_product' ) ) {
			return;
		}
		$data = array( 'currency' => get_woocommerce_currency() );

		if ( is_product() ) {
			$p = wc_get_product( get_queried_object_id() );
			if ( $p ) {
				$item         = self::item( $p );
				$data['page'] = 'product';
				$data['view'] = array( 'currency' => $data['currency'], 'value' => $item['price'], 'items' => array( $item ) );
				$data['item'] = $item;
			}
		} elseif ( is_cart() ) {
			$data['page'] = 'cart';
			$data['view'] = self::cart_payload();
		} elseif ( is_checkout() && ! is_order_received_page() ) {
			$data['page'] = 'checkout';
			$data['view'] = self::cart_payload();
		} else {
			return;
		}
		if ( empty( $data['view'] ) ) {
			return;
		}
		wp_enqueue_script( 'marmar-analytics', plugin_dir_url( __FILE__ ) . 'assets/events.js', array(), self::VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script( 'marmar-analytics', 'MarMarGA', $data );
	}

	/* ---------------- Microsoft Clarity ---------------- */

	public static function clarity() {
		$id = preg_replace( '/[^a-z0-9]/i', '', (string) get_option( self::OPTION, '' ) );
		if ( '' === $id || is_admin() || current_user_can( 'manage_options' ) ) {
			return; // администраторов не записываем
		}
		?>
		<script id="marmar-clarity">
		(function (c, l, a, r, i, t, y) {
			c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments); };
			t = l.createElement(r); t.async = 1; t.src = 'https://www.clarity.ms/tag/' + i;
			y = l.getElementsByTagName(r)[0]; y.parentNode.insertBefore(t, y);
		})(window, document, 'clarity', 'script', <?php echo wp_json_encode( $id ); ?>);
		(function () {
			// Согласие из баннера Blocksy: без «Piekrītu» Clarity работает без cookies.
			function accepted() { return /(?:^|;\s*)blocksy_cookies_consent_accepted=true(?:;|$)/.test(document.cookie); }
			function signal(ok) { window.clarity('consentv2', { ad_Storage: 'denied', analytics_Storage: ok ? 'granted' : 'denied' }); }
			signal(accepted());
			document.addEventListener('click', function (e) {
				if (e.target.closest && e.target.closest('.ct-cookies-accept-button')) { setTimeout(function () { signal(true); }, 50); }
				if (e.target.closest && e.target.closest('.ct-cookies-decline-button')) { setTimeout(function () { signal(false); }, 50); }
			}, true);
		})();
		</script>
		<?php
	}

	/* ---------------- Настройки ---------------- */

	public static function register() {
		register_setting( 'marmar_analytics', self::OPTION, array( 'type' => 'string', 'sanitize_callback' => function ( $v ) { return preg_replace( '/[^a-z0-9]/i', '', (string) $v ); } ) );
	}

	public static function menu() {
		add_options_page( 'MarMar Analytics', 'MarMar Analytics', 'manage_options', 'marmar-analytics', array( __CLASS__, 'page' ) );
	}

	public static function page() {
		echo '<div class="wrap"><h1>MarMar Analytics</h1><form method="post" action="options.php">';
		settings_fields( 'marmar_analytics' );
		echo '<table class="form-table"><tr><th><label for="mma_clarity">Microsoft Clarity Project ID</label></th><td>';
		echo '<input id="mma_clarity" name="' . esc_attr( self::OPTION ) . '" value="' . esc_attr( get_option( self::OPTION, '' ) ) . '" class="regular-text" placeholder="например, abc12de3fg">';
		echo '<p class="description">clarity.microsoft.com → проект → Settings → Overview → Project ID. Пусто — Clarity выключен. Визиты администраторов не записываются.</p></td></tr></table>';
		submit_button( 'Сохранить' );
		echo '</form><h2>События GA4</h2><p>Плагин отправляет через тег Site Kit: <code>view_item</code> (страница товара), <code>add_to_cart</code> (кнопка на странице товара — кнопки в каталоге уже считает Site Kit), <code>view_cart</code>, <code>begin_checkout</code>. Покупки (<code>purchase</code>) по-прежнему отправляет Site Kit.</p></div>';
	}
}

add_action( 'plugins_loaded', function () {
	if ( class_exists( 'WooCommerce' ) ) {
		MarMar_Analytics::init();
	}
} );

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
