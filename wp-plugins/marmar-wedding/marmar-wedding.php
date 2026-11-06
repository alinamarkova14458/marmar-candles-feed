<?php
/**
 * Plugin Name:       MarMar Wedding
 * Description:       Конструктор свадебных свечей на странице товара: тип свечи, количество со ступенчатой ценой (от 10 шт.), цвет ленты, бирочка или индивидуальная наклейка, загрузка дизайна/референса, дата свадьбы, комментарий. Всё попадает в заказ WooCommerce.
 * Version:           1.2.0
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'MARMAR_WEDDING_VERSION', '1.2.0' );
define( 'MARMAR_WEDDING_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-uploads.php';
require_once __DIR__ . '/includes/class-product.php';
require_once __DIR__ . '/includes/class-cart.php';
require_once __DIR__ . '/includes/class-admin.php';

register_activation_hook( __FILE__, array( 'MarMar_Wedding_Uploads', 'protect_dir' ) );

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	MarMar_Wedding_Uploads::init();
	MarMar_Wedding_Product::init();
	MarMar_Wedding_Cart::init();
	MarMar_Wedding_Admin::init();
} );
