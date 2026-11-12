<?php
/**
 * Plugin Name: WooCommerce Google Reviews PRO
 * Description: Рассылка писем для отзывов в Google через 5 дней, Шаблоны с именем клиента, Аналитика и Тесты.
 * Version: 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Google_Reviews_Pro {

    private $table_name;
    private $google_review_url = 'https://g.page/r/CXL0frKfTFEFEBM/review';
    private $time_offset = 3; // Смещение в часах для местного времени

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wc_google_reviews_pro';

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'woocommerce_order_status_completed', array( $this, 'schedule_review_request' ), 10, 1 );
        add_action( 'wc_gr_pro_cron_worker', array( $this, 'process_review_emails' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        add_filter( 'wp_mail_from_name', array( $this, 'set_mail_sender_name' ) );
        add_filter( 'wp_mail_from', array( $this, 'set_mail_sender_email' ) );
    }

    private function get_local_time() {
        return date( 'Y-m-d H:i:s', time() + ($this->time_offset * 3600) );
    }

    public function set_mail_sender_name( $original_name ) {
        return 'MarMar CANDLES';
    }

    public function set_mail_sender_email( $original_email ) {
        $wc_email = get_option( 'woocommerce_email_from_address' );
        return !empty( $wc_email ) ? $wc_email : $original_email;
    }

    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            email varchar(255) NOT NULL,
            completed_at datetime NOT NULL,
            status tinyint(1) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY order_id (order_id)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        
        if ( ! wp_next_scheduled( 'wc_gr_pro_cron_worker' ) ) {
            wp_schedule_event( time(), 'hourly', 'wc_gr_pro_cron_worker' );
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook( 'wc_gr_pro_cron_worker' );
    }

    public function schedule_review_request( $order_id ) {
        global $wpdb;
        $order = wc_get_order( $order_id );
        if ( !$order ) return;

        $email = $order->get_billing_email();
        if ( empty( $email ) ) return;

        $now = $this->get_local_time();
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table_name} WHERE order_id = %d", $order_id ) );

        if ( !$exists ) {
            $wpdb->insert( 
                $this->table_name, 
                array( 
                    'order_id' => $order_id, 
                    'email' => $email, 
                    'completed_at' => $now, 
                    'status' => 0 
                ) 
            );
        }
    }

    public function process_review_emails() {
        global $wpdb;
        $pending = $wpdb->get_results( "SELECT * FROM {$this->table_name} WHERE status = 0" );
        $now_timestamp = time() + ($this->time_offset * 3600);

        foreach ( $pending as $row ) {
            $completed_timestamp = strtotime( $row->completed_at );
            $hours_passed = ( $now_timestamp - $completed_timestamp ) / 3600;

            // 5 дней = 120 часов
            if ( $hours_passed >= 120 ) {
                $order = wc_get_order( $row->order_id );
                $first_name = $order ? $order->get_billing_first_name() : '';
                if ( empty( $first_name ) ) $first_name = 'klient';

                $this->send_review_email( $row->email, $first_name );
                $wpdb->update( 
                    $this->table_name, 
                    array( 'status' => 1 ), 
                    array( 'id' => $row->id ) 
                );
            }
        }
    }

    private function get_default_subject() {
        return 'Paldies par Jūsu pasūtījumu! 🤍';
    }

    private function get_default_body() {
        return "<div style=\"font-family: 'Times New Roman', serif; font-size: 22px; letter-spacing: 1px; margin-bottom: 30px; text-transform: uppercase;\">MARMAR CANDLES</div>\n" .
               "<p>Sveiki, {customer_first_name}!</p>\n" .
               "<p>Paldies, ka izvēlējāties MarMar Candles! 🤍</p>\n" .
               "<p>Ja Jums patika pasūtījums, priecāsimies, ja veltīsiet minūti un atstāsiet Google atsauksmi.</p>\n" .
               "<p style=\"margin: 30px 0;\">⭐ <strong><a href=\"{review_url}\" style=\"color: #fff; background-color: #D68684; padding: 12px 20px; text-decoration: none; border-radius: 4px; display: inline-block;\">Atstāt atsauksmi Google</a></strong></p>\n" .
               "<p>Jūsu atsauksme mums nozīmē ļoti daudz. Paldies par atbalstu! ❤️</p>\n" .
               "<p style=\"margin-top: 40px; font-size: 15px;\">Ar cieņu,<br>MarMar Candles</p>";
    }

    private function send_review_email( $to, $customer_name = 'klient' ) {
        $subject = get_option( 'wc_gr_subject', $this->get_default_subject() );
        $body = get_option( 'wc_gr_body', $this->get_default_body() );

        // Подставляем имя и ссылку
        $body = str_replace( '{customer_first_name}', esc_html( $customer_name ), $body );
        $body = str_replace( '{review_url}', esc_url( $this->google_review_url ), $body );

        $message = '<html><body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px;">';
        $message .= $body;
        $message .= '</body></html>';

        wp_mail( $to, $subject, $message, array('Content-Type: text/html; charset=UTF-8') );
    }

    // --- АДМИНКА С ВКЛАДКАМИ ---
    public function add_admin_menu() {
        add_submenu_page( 
            'woocommerce', 
            'Отзывы Google', 
            'Отзывы Google', 
            'manage_woocommerce', 
            'wc-google-reviews', 
            array( $this, 'render_admin_page' ) 
        );
    }

    public function render_admin_page() {
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'analytics';
        
        echo '<div class="wrap">';
        echo '<h1>Google Отзывы PRO <span style="font-size:14px; background:#e0e0e0; padding:3px 8px; border-radius:10px;">v2.1</span></h1>';
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="?page=wc-google-reviews&tab=analytics" class="nav-tab ' . ( $tab == 'analytics' ? 'nav-tab-active' : '' ) . '">📊 Статистика и очередь</a>';
        echo '<a href="?page=wc-google-reviews&tab=settings" class="nav-tab ' . ( $tab == 'settings' ? 'nav-tab-active' : '' ) . '">⚙️ Настройки шаблона</a>';
        echo '</h2>';

        if ( $tab == 'settings' ) {
            $this->render_settings_tab();
        } else {
            $this->render_analytics_tab();
        }
        echo '</div>';
    }

    private function render_analytics_tab() {
        global $wpdb;

        echo '<p style="margin-top:15px;">Плагин автоматически отправляет клиенту письмо с просьбой оставить отзыв через <strong>5 дней</strong> после того, как заказ получает статус <strong>«Выполнен»</strong>.</p>';
        echo '<p>Ссылка для отзывов в Google: <a href="' . esc_url( $this->google_review_url ) . '" target="_blank">' . esc_html( $this->google_review_url ) . '</a></p>';

        $total = $wpdb->get_var( "SELECT COUNT(id) FROM {$this->table_name}" );
        $sent = $wpdb->get_var( "SELECT COUNT(id) FROM {$this->table_name} WHERE status = 1" );
        $pending = $wpdb->get_var( "SELECT COUNT(id) FROM {$this->table_name} WHERE status = 0" );

        echo '<div style="display:flex; gap: 20px; margin: 20px 0;">';
        echo '<div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; flex:1; text-align:center;"><h3 style="margin:0 0 10px; color:#646970;">📦 Всего заказов в базе</h3><p style="font-size:28px; font-weight:bold; margin:0; color:#2271b1;">' . esc_html($total) . '</p></div>';
        echo '<div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; flex:1; text-align:center;"><h3 style="margin:0 0 10px; color:#646970;">⏳ Ждут отправки (до 5 дней)</h3><p style="font-size:28px; font-weight:bold; margin:0; color:#d63638;">' . esc_html($pending) . '</p></div>';
        echo '<div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; flex:1; text-align:center;"><h3 style="margin:0 0 10px; color:#646970;">✅ Письмо отправлено</h3><p style="font-size:28px; font-weight:bold; margin:0; color:#00a32a;">' . esc_html($sent) . '</p></div>';
        echo '</div>';

        $results = $wpdb->get_results( "SELECT * FROM {$this->table_name} ORDER BY completed_at DESC LIMIT 50" );
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Номер заказа</th><th>Email клиента</th><th>Дата выполнения заказа</th><th>Статус рассылки</th></tr></thead><tbody>';
        
        if ( $results ) {
            foreach ( $results as $row ) {
                $order_edit_url = get_edit_post_link( $row->order_id );
                $status_text = '<span style="color:#d63638;">⏳ Ожидает отправки (пройдет 5 дней)</span>';
                if ( $row->status == 1 ) {
                    $status_text = '<span style="color:#00a32a; font-weight:bold;">✅ Письмо отправлено</span>';
                }

                echo '<tr>';
                echo '<td><strong><a href="' . esc_url($order_edit_url) . '">Заказ #' . esc_html($row->order_id) . '</a></strong></td>';
                echo '<td><a href="mailto:' . esc_attr($row->email) . '">' . esc_html($row->email) . '</a></td>';
                echo '<td>' . esc_html( date( 'd.m.Y H:i', strtotime( $row->completed_at ) ) ) . '</td>';
                echo '<td>' . wp_kses_post($status_text) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="4">Пока нет записей для сбора отзывов.</td></tr>';
        }
        echo '</tbody></table>';
    }

    private function render_settings_tab() {
        if ( isset( $_POST['wc_gr_save_settings'] ) ) {
            check_admin_referer( 'wc_gr_save_action', 'wc_gr_nonce' );
            update_option( 'wc_gr_subject', sanitize_text_field( $_POST['wc_gr_subject'] ) );
            update_option( 'wc_gr_body', wp_unslash( $_POST['wc_gr_body'] ) );
            echo '<div class="notice notice-success is-dismissible"><p>Шаблон письма успешно сохранен!</p></div>';
        }

        if ( isset( $_POST['wc_gr_send_test'] ) ) {
            check_admin_referer( 'wc_gr_test_action', 'wc_gr_test_nonce' );
            $test_email = sanitize_email( $_POST['test_email'] );
            if ( is_email( $test_email ) ) {
                $current_user_name = wp_get_current_user()->display_name;
                if ( empty( $current_user_name ) ) $current_user_name = 'Alīna';

                $this->send_review_email( $test_email, $current_user_name );
                echo '<div class="notice notice-success is-dismissible"><p>Тестовое письмо успешно отправлено на <strong>' . esc_html( $test_email ) . '</strong>!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Указан некорректный email для теста.</p></div>';
            }
        }

        $subject = get_option( 'wc_gr_subject', $this->get_default_subject() );
        $body = get_option( 'wc_gr_body', $this->get_default_body() );

        echo '<br><div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">';
        echo '<p><strong>Доступные переменные:</strong><br>';
        echo '<code>{customer_first_name}</code> — подставит имя клиента<br>';
        echo '<code>{review_url}</code> — ссылка на Google отзывы (вшита в кнопку)</p>';
        
        echo '<form method="post">';
        wp_nonce_field( 'wc_gr_save_action', 'wc_gr_nonce' );

        echo '<table class="form-table">';
        echo '<tr><th scope="row">Тема письма</th>';
        echo '<td><input type="text" name="wc_gr_subject" value="' . esc_attr( $subject ) . '" class="regular-text" style="width:100%;"></td></tr>';
        
        echo '<tr><th scope="row">Текст письма (HTML)</th><td>';
        wp_editor( $body, 'wc_gr_body', array(
            'textarea_name' => 'wc_gr_body',
            'media_buttons' => true,
            'textarea_rows' => 12,
            'teeny'         => false,
        ) );
        echo '</td></tr></table>';

        echo '<p class="submit"><input type="submit" name="wc_gr_save_settings" id="submit" class="button button-primary" value="Сохранить настройки"></p>';
        echo '</form>';

        echo '<hr style="margin: 30px 0;">';
        echo '<h3>Проверить шаблон</h3>';
        echo '<form method="post" style="background: #f0f0f1; padding: 15px; border-radius: 4px; display: flex; align-items: center; gap: 10px;">';
        wp_nonce_field( 'wc_gr_test_action', 'wc_gr_test_nonce' );
        echo '<input type="email" name="test_email" value="' . esc_attr( wp_get_current_user()->user_email ) . '" placeholder="Ваш email" style="width: 250px;">';
        echo '<button type="submit" name="wc_gr_send_test" class="button">Отправить тестовое письмо</button>';
        echo '</form>';

        echo '</div>';
    }
}
new WC_Google_Reviews_Pro();