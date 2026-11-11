<?php
/**
 * Plugin Name:       MarMar Chat
 * Description:       Чат-помощник на Claude: подбирает свечи из каталога WooCommerce, отвечает про сроки, доставку и заказы. Сохраняет переписку для просмотра в админке.
 * Version:           1.0.0
 * Author:            MarMar CANDLES
 * Requires at least: 6.3
 * Requires PHP:      8.1
 * WC requires at least: 8.0
 * Text Domain:       marmar-chat
 */

defined( 'ABSPATH' ) || exit;

define( 'MARMAR_CHAT_VERSION', '1.0.0' );
define( 'MARMAR_CHAT_FILE', __FILE__ );
define( 'MARMAR_CHAT_DIR', plugin_dir_path( __FILE__ ) );
define( 'MARMAR_CHAT_URL', plugin_dir_url( __FILE__ ) );

require_once MARMAR_CHAT_DIR . 'includes/class-settings.php';
require_once MARMAR_CHAT_DIR . 'includes/class-store.php';
require_once MARMAR_CHAT_DIR . 'includes/class-catalog.php';
require_once MARMAR_CHAT_DIR . 'includes/class-claude.php';
require_once MARMAR_CHAT_DIR . 'includes/class-rest.php';
require_once MARMAR_CHAT_DIR . 'includes/class-widget.php';
require_once MARMAR_CHAT_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, array( 'MarMar_Chat_Store', 'install' ) );

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	MarMar_Chat_Store::maybe_upgrade();
	MarMar_Chat_Rest::init();
	MarMar_Chat_Widget::init();
	MarMar_Chat_Catalog::init();
	if ( is_admin() ) {
		MarMar_Chat_Admin::init();
	}
} );
