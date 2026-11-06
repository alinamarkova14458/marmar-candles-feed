<?php
/**
 * Plugin Name: MarMar Newsletter
 * Description: Собственные рассылки без платных сервисов: галочка согласия при оформлении заказа, база подписчиков, письма-кампании с тестовой отправкой, отправка порциями, ссылка «atrakstīties», отправка через SMTP (Google).
 * Version: 1.0.0
 * Author: MarMar CANDLES
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * Text Domain: marmar-newsletter
 */

defined( 'ABSPATH' ) || exit;

define( 'MARMAR_NL_VERSION', '1.0.0' );
define( 'MARMAR_NL_FILE', __FILE__ );
define( 'MARMAR_NL_DIR', plugin_dir_path( __FILE__ ) );

require_once MARMAR_NL_DIR . 'includes/class-subscribers.php';
require_once MARMAR_NL_DIR . 'includes/class-mailer.php';
require_once MARMAR_NL_DIR . 'includes/class-campaigns.php';
require_once MARMAR_NL_DIR . 'includes/class-checkout.php';
require_once MARMAR_NL_DIR . 'includes/class-unsubscribe.php';
require_once MARMAR_NL_DIR . 'includes/class-admin.php';
require_once MARMAR_NL_DIR . 'includes/class-rest.php';

register_activation_hook( __FILE__, array( 'Marmar_NL_Subscribers', 'install' ) );

add_action(
	'plugins_loaded',
	static function () {
		Marmar_NL_Subscribers::maybe_upgrade();
		Marmar_NL_Mailer::init();
		Marmar_NL_Campaigns::init();
		Marmar_NL_Checkout::init();
		Marmar_NL_Unsubscribe::init();
		Marmar_NL_Admin::init();
		Marmar_NL_Rest::init();
	}
);
