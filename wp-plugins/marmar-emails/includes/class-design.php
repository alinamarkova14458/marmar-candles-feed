<?php
defined( 'ABSPATH' ) || exit;

/**
 * Единый вид всех писем клиентам (статусы заказа WooCommerce, корзины, отзывы):
 * кремовый фон, белая карточка со скруглением, логотип, заголовок цвета #735360, розовые кнопки-«пилюли».
 * Цвета и логотип — в настройках WooCommerce → E-pasti (меняются кнопкой «Применить оформление»),
 * остальное — CSS здесь (WooCommerce встраивает его инлайн).
 * Отключить: удалить фильтр или вернуть настройки из бэкапа (option marmar_emails_design_backup).
 */
class MarMar_Emails_Design {

	const LOGO = 'https://marmarcandles.com/wp-content/uploads/2026/04/marmar-candles-logo1-1.png';

	const SETTINGS = array(
		'woocommerce_email_header_image'          => self::LOGO,
		'woocommerce_email_header_image_width'    => '230',
		'woocommerce_email_base_color'            => '#B9605F',
		'woocommerce_email_background_color'      => '#FAF3F0',
		'woocommerce_email_body_background_color' => '#FFFFFF',
		'woocommerce_email_text_color'            => '#504E4A',
		'woocommerce_email_footer_text_color'     => '#8A7F7C',
		'woocommerce_email_footer_text'           => 'MarMar Candles · Roku darbs Rīgā<br><a href="https://marmarcandles.com/lv/">marmarcandles.com</a> · <a href="https://www.instagram.com/marmar.candles">@marmar.candles</a>',
	);

	const BUTTON = 'display:inline-block;background:#B9605F;color:#ffffff;padding:15px 34px;border-radius:999px;text-decoration:none;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;';

	public static function init() {
		add_filter( 'woocommerce_email_styles', array( __CLASS__, 'css' ), 99 );
		add_action( 'admin_post_marmar_emails_design_apply', array( __CLASS__, 'apply' ) );
		add_action( 'admin_post_marmar_emails_design_restore', array( __CLASS__, 'restore' ) );
		add_action( 'admin_post_marmar_emails_test_all', array( __CLASS__, 'test_all' ) );
	}

	public static function css( $css ) {
		return $css . '
#wrapper{background-color:#FAF3F0!important;padding:28px 12px!important}
#template_header_image{padding:4px 0 22px!important}
#template_header_image img{width:230px;max-width:70%;height:auto}
#template_container{background-color:#FFFFFF!important;border:1px solid #F0D9D6!important;border-radius:22px!important;box-shadow:none!important;overflow:hidden}
#template_header{background-color:#FFFFFF!important;color:#735360!important;border-radius:22px 22px 0 0!important;border-bottom:0!important}
#template_header_inner,#header_wrapper{padding:30px 30px 4px!important;text-align:center!important}
#template_header h1,#header_wrapper h1{color:#735360!important;background:none!important;text-shadow:none!important;font-family:Georgia,"Times New Roman",serif!important;font-weight:normal!important;font-size:24px!important;line-height:31px!important;letter-spacing:1.5px!important;text-transform:uppercase!important;text-align:center!important}
#body_content{background-color:#FFFFFF!important;border-radius:0 0 22px 22px!important}
#body_content_inner,#body_content table td{font-family:Arial,Helvetica,sans-serif!important;font-size:15px!important;line-height:23px!important;color:#504E4A!important}
#body_content p{margin:0 0 14px!important}
h2,h3{color:#735360!important;font-family:Georgia,"Times New Roman",serif!important;font-weight:normal!important;letter-spacing:1.2px!important;text-transform:uppercase!important}
h2{font-size:17px!important}h3{font-size:15px!important}
.td{border-color:#F0D9D6!important;color:#504E4A!important}
table.td{background-color:#FBF3F2!important;border:0!important;border-radius:14px!important}
th.td{color:#B9605F!important}
tfoot th.td{color:#504E4A!important}
.address{border:0!important;background-color:#FBF3F2!important;border-radius:14px!important;padding:14px 16px!important;color:#504E4A!important}
a{color:#B9605F!important}
#template_footer #credit,#template_footer td{color:#8A7F7C!important;font-family:Arial,Helvetica,sans-serif!important;font-size:12px!important;line-height:19px!important;text-align:center!important;border:0!important}
#template_footer a{color:#B9605F!important;text-decoration:none!important}
';
	}

	/** Старые кнопки MarMar Emails (#d68684, квадратные) → новые розовые «пилюли» по центру. */
	public static function buttons( $html ) {
		$html = preg_replace( '/<p style="margin:28px 0;">(\s*<a [^>]*style="display:inline-block;background:#d68684[^"]*")/i', '<p style="margin:26px 0;text-align:center;">$1', $html );
		return preg_replace( '/style="display:inline-block;background:#d68684[^"]*"/i', 'style="' . self::BUTTON . '"', $html );
	}

	private static function guard( $action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( $action ) ) {
			wp_die( 'Nav atļauts.' );
		}
	}

	/** Логотип и цвета в настройках писем WooCommerce (старые значения сохраняются для отката). */
	public static function apply() {
		self::guard( 'marmar_emails_design_apply' );
		if ( ! get_option( 'marmar_emails_design_backup' ) ) {
			$backup = array();
			foreach ( array_keys( self::SETTINGS ) as $k ) {
				$backup[ $k ] = get_option( $k, '' );
			}
			update_option( 'marmar_emails_design_backup', $backup, false );
		}
		foreach ( self::SETTINGS as $k => $v ) {
			update_option( $k, $v );
		}
		update_option( 'marmar_emails_design_log', 'Noformējums ieslēgts ' . wp_date( 'd.m.Y H:i' ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=marmar-emails&design=1' ) );
		exit;
	}

	public static function restore() {
		self::guard( 'marmar_emails_design_restore' );
		foreach ( (array) get_option( 'marmar_emails_design_backup', array() ) as $k => $v ) {
			update_option( $k, $v );
		}
		update_option( 'marmar_emails_design_log', 'Atjaunots vecais noformējums ' . wp_date( 'd.m.Y H:i' ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=marmar-emails&design=1' ) );
		exit;
	}

	/** Тесты на адрес магазина: «Apstrādē» (последний заказ), отзыв, корзина. Клиентам ничего не уходит. */
	public static function test_all() {
		self::guard( 'marmar_emails_test_all' );
		$to   = get_option( 'woocommerce_email_from_address' );
		$log  = array();
		$list = wc_get_orders( array( 'status' => array( 'processing' ), 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
		$mail = WC()->mailer()->get_emails();
		if ( $list && isset( $mail['WC_Email_Customer_Processing_Order'] ) ) {
			$e         = $mail['WC_Email_Customer_Processing_Order'];
			$e->object = $list[0];
			$e->placeholders['{order_date}']   = wc_format_datetime( $list[0]->get_date_created() );
			$e->placeholders['{order_number}'] = $list[0]->get_order_number();
			$ok    = $e->send( $to, '[TESTS] ' . $e->get_subject(), $e->get_content(), $e->get_headers(), array() );
			$log[] = 'Apstrādē #' . $list[0]->get_order_number() . ': ' . ( $ok ? 'nosūtīts' : 'kļūda' );
		}
		$ok    = MarMar_Emails_Mailer::send( $to, '[TESTS] ' . MarMar_Emails_Settings::get( 'review_subject' ), MarMar_Emails_Settings::get( 'review_heading' ), MarMar_Emails_Settings::get( 'review_body' ), array( 'first_name' => 'Anna', 'review_url' => MarMar_Emails_Settings::get( 'review_url' ), 'order_number' => '0000' ) );
		$log[] = 'Atsauksme: ' . ( $ok ? 'nosūtīts' : 'kļūda' );
		$items = '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;"><tr><td style="padding:8px 0;border-bottom:1px solid #F0D9D6;">Ziemassvētku bumbas formas svece ar zelta dekoru × 2</td><td style="padding:8px 0;border-bottom:1px solid #F0D9D6;text-align:right;white-space:nowrap;">13,00 €</td></tr></table>';
		$ok    = MarMar_Emails_Mailer::send( $to, '[TESTS] ' . MarMar_Emails_Settings::get( 'cart_subject_1' ), MarMar_Emails_Settings::get( 'cart_heading_1' ), MarMar_Emails_Settings::get( 'cart_body_1' ), array( 'first_name' => 'Anna', 'cart_items' => $items, 'cart_url' => wc_get_cart_url(), 'cart_total' => '13,00 €' ) );
		$log[] = 'Grozs: ' . ( $ok ? 'nosūtīts' : 'kļūda' );
		update_option( 'marmar_emails_design_log', 'Testi uz ' . $to . ': ' . implode( '; ', $log ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=marmar-emails&design=1' ) );
		exit;
	}

	/** Блок на странице настроек MarMar Emails. */
	public static function admin_box() {
		echo '<div style="background:#fff;border:1px solid #e5e0de;border-radius:10px;padding:14px 18px;margin:16px 0;max-width:820px"><h2 style="margin-top:0">Vēstuļu noformējums</h2>';
		$log = get_option( 'marmar_emails_design_log' );
		if ( $log ) {
			echo '<p><strong>' . esc_html( $log ) . '</strong></p>';
		}
		foreach ( array( 'marmar_emails_design_apply' => array( 'Ieslēgt jauno noformējumu (logo, krāsas)', 'button-primary' ), 'marmar_emails_test_all' => array( 'Nosūtīt 3 testa e-pastus sev', '' ), 'marmar_emails_design_restore' => array( 'Atjaunot veco noformējumu', '' ) ) as $act => $b ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 8px 8px 0">';
			wp_nonce_field( $act );
			echo '<input type="hidden" name="action" value="' . esc_attr( $act ) . '"><button class="button ' . esc_attr( $b[1] ) . '" data-mme="' . esc_attr( $act ) . '">' . esc_html( $b[0] ) . '</button></form>';
		}
		echo '</div>';
	}
}
