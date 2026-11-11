<?php
/**
 * Plugin Name:       MarMar Emails
 * Description:       Брошенные корзины (2 письма с восстановлением корзины) и просьба об отзыве в Google через N дней после статуса «Выполнен». Письма в стиле WooCommerce, ссылка «отписаться», статистика.
 * Version:           1.1.0
 * Author:            MarMar CANDLES
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 * Text Domain:       marmar-emails
 */

defined( 'ABSPATH' ) || exit;

define( 'MARMAR_EMAILS_VERSION', '1.1.0' );
define( 'MARMAR_EMAILS_FILE', __FILE__ );
define( 'MARMAR_EMAILS_DIR', plugin_dir_path( __FILE__ ) );

require_once MARMAR_EMAILS_DIR . 'includes/class-settings.php';
require_once MARMAR_EMAILS_DIR . 'includes/class-install.php';
require_once MARMAR_EMAILS_DIR . 'includes/class-mailer.php';
require_once MARMAR_EMAILS_DIR . 'includes/class-carts.php';
require_once MARMAR_EMAILS_DIR . 'includes/class-reviews.php';
require_once MARMAR_EMAILS_DIR . 'includes/class-optout.php';
require_once MARMAR_EMAILS_DIR . 'includes/class-admin.php';
require_once MARMAR_EMAILS_DIR . 'includes/class-design.php';

register_activation_hook( __FILE__, array( 'MarMar_Emails_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MarMar_Emails_Install', 'deactivate' ) );

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
	MarMar_Emails_Install::maybe_upgrade();
	MarMar_Emails_Carts::init();
	MarMar_Emails_Reviews::init();
	MarMar_Emails_Optout::init();
	MarMar_Emails_Design::init();
	if ( is_admin() ) {
		MarMar_Emails_Admin::init();
	}

	// Один общий «тик» каждые 15 минут через Action Scheduler (входит в WooCommerce).
	add_action( 'marmar_emails_tick', 'marmar_emails_run_tick' );
	add_action( 'init', function () {
		if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( 'marmar_emails_tick', array(), 'marmar-emails' ) ) {
			as_schedule_recurring_action( time() + 60, 15 * MINUTE_IN_SECONDS, 'marmar_emails_tick', array(), 'marmar-emails' );
		}
	} );
} );

/**
 * Обработка очередей: письма о корзинах, просьбы об отзывах, уборка старых данных.
 */
function marmar_emails_run_tick() {
	MarMar_Emails_Carts::process_queue();
	MarMar_Emails_Reviews::process_queue();
	MarMar_Emails_Install::cleanup();
}
