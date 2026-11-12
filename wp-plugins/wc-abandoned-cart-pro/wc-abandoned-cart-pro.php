<?php
/**
 * Plugin Name: WooCommerce Abandoned Cart PRO
 * Description: Рассылка 3 писем, Шаблоны, Аналитика, Тесты и принудительная корректировка местного времени.
 * Version: 5.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Abandoned_Cart_Pro {

    private $table_name;
    private $time_offset = 3; // Смещение в часах (для Латвии UTC+3 летом). Измени на нужное, если вдруг потребуется.

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wc_abandoned_carts_pro';

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'woocommerce_cart_updated', array( $this, 'track_cart_on_update' ) );
        add_action( 'woocommerce_thankyou', array( $this, 'track_recovery_on_purchase' ) );

        add_action( 'wc_ajax_wc_ac_capture_email', array( $this, 'ajax_capture_email' ) );

        add_action( 'wp_footer', array( $this, 'inject_checkout_script' ) );
        add_action( 'wc_ac_pro_cron_worker', array( $this, 'process_emails' ) );

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
            session_id varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            cart_data longtext NOT NULL,
            updated_at datetime NOT NULL,
            status tinyint(1) DEFAULT 0 NOT NULL,
            recovered tinyint(1) DEFAULT 0 NOT NULL,
            order_id bigint(20) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_id (session_id)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        
        if ( ! wp_next_scheduled( 'wc_ac_pro_cron_worker' ) ) {
            wp_schedule_event( time(), 'hourly', 'wc_ac_pro_cron_worker' );
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook( 'wc_ac_pro_cron_worker' );
    }

    private function get_session_id() {
        if ( is_user_logged_in() ) return 'user_' . get_current_user_id();
        elseif ( isset( WC()->session ) ) return 'guest_' . WC()->session->get_customer_id();
        return '';
    }

    public function ajax_capture_email() {
        if ( isset( $_POST['email'] ) && is_email( $_POST['email'] ) ) {
            $email = sanitize_email( $_POST['email'] );
            WC()->session->set( 'wc_ac_captured_email', $email );
            $this->save_cart_data( $email );
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    public function inject_checkout_script() {
        if ( ! is_checkout() || is_user_logged_in() ) return;
        $ajax_url = WC_AJAX::get_endpoint( 'wc_ac_capture_email' );
        ?>
        <script>
        jQuery(document).ready(function($) {
            $(document).on('blur', '#email, #billing_email', function() {
                var email = $(this).val();
                if(email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    $.post( '<?php echo esc_url( $ajax_url ); ?>', { email: email });
                }
            });
        });
        </script>
        <?php
    }

    public function save_cart_data( $email = '' ) {
        if ( is_admin() || ! isset( WC()->cart ) || WC()->cart->is_empty() ) return;
        global $wpdb;
        $session_id = $this->get_session_id();
        if ( empty( $session_id ) ) return;

        if ( empty( $email ) ) {
            if ( is_user_logged_in() ) $email = wp_get_current_user()->user_email;
            else $email = WC()->session->get( 'wc_ac_captured_email', '' );
        }
        if ( empty( $email ) ) return; 

        $cart_data = maybe_serialize( WC()->cart->get_cart() );
        $now = $this->get_local_time(); // ИСПОЛЬЗУЕМ НАШЕ ЛОКАЛЬНОЕ ВРЕМЯ
        $exists = $wpdb->get_row( $wpdb->prepare( "SELECT id, recovered FROM {$this->table_name} WHERE session_id = %s", $session_id ) );

        if ( $exists ) {
            if ( $exists->recovered == 1 ) return;
            $wpdb->update( $this->table_name, array( 'cart_data' => $cart_data, 'updated_at' => $now, 'email' => $email, 'status' => 0 ), array( 'session_id' => $session_id ) );
        } else {
            $wpdb->insert( $this->table_name, array( 'session_id' => $session_id, 'email' => $email, 'cart_data' => $cart_data, 'updated_at' => $now, 'status' => 0 ) );
        }
    }

    public function track_cart_on_update() { $this->save_cart_data(); }

    public function track_recovery_on_purchase( $order_id ) {
        global $wpdb;
        $session_id = $this->get_session_id();
        
        $order = wc_get_order( $order_id );
        $billing_email = $order ? $order->get_billing_email() : '';
        
        $cart = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE (session_id = %s OR email = %s) AND recovered = 0 ORDER BY id DESC LIMIT 1", $session_id, $billing_email ) );
        
        if ( $cart ) {
            if ( $cart->status > 0 ) {
                $wpdb->update( 
                    $this->table_name, 
                    array( 'status' => 4, 'recovered' => 1, 'order_id' => $order_id, 'updated_at' => $this->get_local_time() ), 
                    array( 'id' => $cart->id ) 
                );
            } else {
                $wpdb->delete( $this->table_name, array( 'id' => $cart->id ) );
            }
        }
    }

    public function process_emails() {
        global $wpdb;
        $carts = $wpdb->get_results( "SELECT * FROM {$this->table_name} WHERE status < 3 AND recovered = 0" );
        $now_timestamp = time() + ($this->time_offset * 3600);

        foreach ( $carts as $cart ) {
            $updated_timestamp = strtotime( $cart->updated_at );
            $hours_passed = ( $now_timestamp - $updated_timestamp ) / 3600;
            $new_status = $cart->status;

            if ( $cart->status == 0 && $hours_passed >= 1 && $hours_passed < 24 ) {
                $this->send_reminder( $cart, 1 ); $new_status = 1;
            } elseif ( $cart->status == 1 && $hours_passed >= 24 && $hours_passed < 72 ) {
                $this->send_reminder( $cart, 2 ); $new_status = 2;
            } elseif ( $cart->status == 2 && $hours_passed >= 72 ) {
                $this->send_reminder( $cart, 3 ); $new_status = 3;
            }

            if ( $new_status != $cart->status ) {
                $wpdb->update( $this->table_name, array( 'status' => $new_status ), array( 'id' => $cart->id ) );
            }
        }
    }

    public function add_admin_menu() {
        add_submenu_page( 'woocommerce', 'Брошенные корзины', 'Брошенные корзины', 'manage_woocommerce', 'wc-abandoned-carts', array( $this, 'render_admin_page' ) );
    }

    public function render_admin_page() {
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'analytics';
        
        echo '<div class="wrap">';
        echo '<h1>Брошенные корзины PRO <span style="font-size:14px; background:#e0e0e0; padding:3px 8px; border-radius:10px;">v5.4</span></h1>';
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="?page=wc-abandoned-carts&tab=analytics" class="nav-tab ' . ( $tab == 'analytics' ? 'nav-tab-active' : '' ) . '">📊 Аналитика и Статистика</a>';
        echo '<a href="?page=wc-abandoned-carts&tab=settings" class="nav-tab ' . ( $tab == 'settings' ? 'nav-tab-active' : '' ) . '">⚙️ Настройки шаблонов</a>';
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

        $column_check = $wpdb->get_results( "SHOW COLUMNS FROM {$this->table_name} LIKE 'recovered'" );
        if ( empty( $column_check ) ) {
            echo '<div class="notice notice-error"><p><strong>Внимание!</strong> Обновите базу данных: зайдите в "Плагины", деактивируйте "WooCommerce Abandoned Cart PRO", а затем активируйте снова.</p></div>';
            return;
        }

        $total_carts = $wpdb->get_var( "SELECT COUNT(id) FROM {$this->table_name}" );
        $recovered_carts = $wpdb->get_var( "SELECT COUNT(id) FROM {$this->table_name} WHERE recovered = 1" );
        $recovery_rate = ($total_carts > 0) ? round( ($recovered_carts / $total_carts) * 100, 1 ) : 0;
        
        $recovered_revenue = 0;
        $recovered_orders = $wpdb->get_results( "SELECT order_id FROM {$this->table_name} WHERE recovered = 1 AND order_id > 0" );
        foreach ( $recovered_orders as $ord ) {
            $order = wc_get_order( $ord->order_id );
            if ( $order ) {
                $recovered_revenue += $order->get_total();
            }
        }

        echo '<div style="display:flex; gap: 20px; margin: 20px 0;">';
        echo '<div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; flex:1; text-align:center;"><h3 style="margin:0 0 10px; color:#646970;">🛒 Зафиксировано корзин</h3><p style="font-size:28px; font-weight:bold; margin:0; color:#2271b1;">' . esc_html($total_carts) . '</p></div>';
        echo '<div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; flex:1; text-align:center;"><h3 style="margin:0 0 10px; color:#646970;">✅ Успешно возвращено</h3><p style="font-size:28px; font-weight:bold; margin:0; color:#00a32a;">' . esc_html($recovered_carts) . '</p></div>';
        echo '<div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; flex:1; text-align:center;"><h3 style="margin:0 0 10px; color:#646970;">📈 Конверсия возврата</h3><p style="font-size:28px; font-weight:bold; margin:0; color:#d63638;">' . esc_html($recovery_rate) . '%</p></div>';
        echo '<div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; flex:1; text-align:center;"><h3 style="margin:0 0 10px; color:#646970;">💰 Спасенная выручка</h3><p style="font-size:28px; font-weight:bold; margin:0; color:#00a32a;">' . wp_kses_post( wc_price( $recovered_revenue ) ) . '</p></div>';
        echo '</div>';

        $results = $wpdb->get_results( "SELECT * FROM {$this->table_name} ORDER BY updated_at DESC LIMIT 50" );
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Email клиента</th><th>Статус</th><th>Последняя активность</th><th>Товары в корзине</th></tr></thead><tbody>';
        
        if ( $results ) {
            foreach ( $results as $row ) {
                if ( $row->recovered == 1 ) {
                    $order_edit_url = get_edit_post_link( $row->order_id );
                    $status_text = '<span style="color:#00a32a; font-weight:bold;">🎉 Возвращена (Заказ <a href="' . esc_url($order_edit_url) . '">#' . esc_html($row->order_id) . '</a>)</span>';
                } else {
                    $status_text = '⏳ Ждет 1 часа';
                    if ($row->status == 1) $status_text = '<span style="color:#d63638;">Отправлено 1-е письмо</span>';
                    if ($row->status == 2) $status_text = '<span style="color:#d63638;">Отправлено 2-е письмо</span>';
                    if ($row->status == 3) $status_text = '<span style="color:#8c8f94;">Отправлено 3-е письмо (Потеряна)</span>';
                }

                $cart_items = maybe_unserialize( $row->cart_data );
                $items_list = '';
                if (is_array($cart_items)) {
                    foreach ($cart_items as $item) {
                        $product = wc_get_product( $item['product_id'] );
                        if ($product) $items_list .= $product->get_name() . ' (x' . $item['quantity'] . ')<br>';
                    }
                }

                echo '<tr>';
                echo '<td><strong><a href="mailto:' . esc_attr($row->email) . '">' . esc_html($row->email) . '</a></strong></td>';
                echo '<td>' . wp_kses_post($status_text) . '</td>';
                echo '<td>' . esc_html( date( 'd.m.Y H:i', strtotime( $row->updated_at ) ) ) . '</td>';
                echo '<td>' . wp_kses_post($items_list) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="4">Пока нет брошенных корзин.</td></tr>';
        }
        echo '</tbody></table>';
    }

    private function get_default_body($step) {
        $texts = array(
            1 => "<div style=\"font-family: 'Times New Roman', serif; font-size: 22px; letter-spacing: 1px; margin-bottom: 30px; text-transform: uppercase;\">MARMAR CANDLES</div>\n<h2 style=\"color: #111; margin-bottom: 20px; font-size: 24px;\">Nepametiet savu grozu</h2>\n<p>Sveiki!</p>\n<p>Pamanījām, ka Jūs pievienojāt preces grozam, bet pasūtījumu vēl nepabeidzāt.<br>Jūsu izvēlētās sveces joprojām gaida.</p>\n<p>Ja tas notika nejauši vai vienkārši pietrūka laika, varat turpināt pirkumu jebkurā brīdī.</p>\n<p>{cart_items}</p>\n<p>👉 <strong><a href=\"{checkout_url}\" style=\"color: #0056b3; text-decoration: underline;\">Pabeigt pasūtījumu</a></strong></p>\n<p style=\"margin-top: 40px; font-size: 15px;\">Ar cieņu,<br>MarMar CANDLES komanda</p>",
            2 => "<div style=\"font-family: 'Times New Roman', serif; font-size: 22px; letter-spacing: 1px; margin-bottom: 30px; text-transform: uppercase;\">MARMAR CANDLES</div>\n<h2 style=\"color: #111; margin-bottom: 20px; font-size: 24px;\">Saglabājām Jūsu pasūtījumu</h2>\n<p>Sveiki!</p>\n<p>Jūsu izvēlētās preces joprojām ir saglabātas grozā.<br>Katra MarMar CANDLES svece ir roku darbs, kas tiek izgatavots ar rūpību un uzmanību pret detaļām.<br>Ja vēl neesat pabeidzis pasūtījumu, mēs ar prieku sagatavosim to tieši Jums.</p>\n<ul>\n<li>100% dabīgs sojas vasks bez smaržas</li>\n<li>Lentas krāsas pielāgošana Jūsu svētkiem</li>\n<li>Pasūtījuma izpilde precīzi norunātajā laikā</li>\n<li>Droša apmaksa</li>\n</ul>\n<p>{cart_items}</p>\n<p>👉 <strong><a href=\"{checkout_url}\" style=\"color: #0056b3; text-decoration: underline;\">Atgriezties pie pasūtījuma</a></strong></p>\n<p style=\"margin-top: 40px; font-size: 15px;\">Ar cieņu,<br>MarMar CANDLES komanda</p>",
            3 => "<div style=\"font-family: 'Times New Roman', serif; font-size: 22px; letter-spacing: 1px; margin-bottom: 30px; text-transform: uppercase;\">MARMAR CANDLES</div>\n<h2 style=\"color: #111; margin-bottom: 20px; font-size: 24px;\">Pēdējais atgādinājums par Jūsu grozu</h2>\n<p>Sveiki!</p>\n<p>Atgādinām, ka Jūsu izvēlētās preces joprojām atrodas grozā.<br>Daļa mūsu produktu tiek izgatavota nelielos daudzumos, tāpēc to pieejamība var mainīties.<br>Ja vēlaties saņemt savu pasūtījumu, iesakām pabeigt pirkumu tuvākajā laikā.</p>\n<p>{cart_items}</p>\n<p>👉 <strong><a href=\"{checkout_url}\" style=\"color: #0056b3; text-decoration: underline;\">Noformēt pasūtījumu</a></strong></p>\n<p style=\"margin-top: 40px; font-size: 15px;\">Ar cieņu,<br>MarMar CANDLES komanda</p>"
        );
        return $texts[$step];
    }

    private function render_settings_tab() {
        if ( isset( $_POST['wc_ac_save_settings'] ) ) {
            check_admin_referer( 'wc_ac_save_action', 'wc_ac_nonce' );
            for ( $i = 1; $i <= 3; $i++ ) {
                update_option( 'wc_ac_subj_' . $i, sanitize_text_field( $_POST['wc_ac_subj_' . $i] ) );
                update_option( 'wc_ac_body_' . $i, wp_unslash( $_POST['wc_ac_body_' . $i] ) );
            }
            echo '<div class="notice notice-success is-dismissible"><p>Настройки шаблонов успешно сохранены!</p></div>';
        }

        if ( isset( $_POST['wc_ac_send_test'] ) ) {
            check_admin_referer( 'wc_ac_test_action', 'wc_ac_test_nonce' );
            $test_step = intval( $_POST['test_step'] );
            $test_email = sanitize_email( $_POST['test_email'] );

            if ( is_email( $test_email ) ) {
                $fake_cart_row = (object) array(
                    'email' => $test_email,
                    'cart_data' => serialize( array(
                        array( 'product_id' => 0, 'quantity' => 1 )
                    ) )
                );
                
                global $wpdb;
                $sample_product_id = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' LIMIT 1" );
                if ( $sample_product_id ) {
                    $fake_cart_row->cart_data = serialize( array(
                        array( 'product_id' => $sample_product_id, 'quantity' => 1 )
                    ) );
                }

                $this->send_reminder( $fake_cart_row, $test_step );
                echo '<div class="notice notice-success is-dismissible"><p>Тестовое письмо №' . $test_step . ' успешно отправлено на <strong>' . esc_html( $test_email ) . '</strong>!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Указан некорректный email для теста.</p></div>';
            }
        }

        echo '<br><div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">';
        echo '<p><strong>Доступные переменные:</strong><br><code>{cart_items}</code> — выведет список товаров в корзине<br><code>{checkout_url}</code> — ссылка на страницу оформления заказа</p>';
        
        echo '<form method="post">';
        wp_nonce_field( 'wc_ac_save_action', 'wc_ac_nonce' );

        $default_subjs = array( 1 => 'Nepametiet savu grozu', 2 => 'Saglabājām Jūsu pasūtījumu', 3 => 'Pēdējais atgādinājums par Jūsu grozu' );

        for ( $i = 1; $i <= 3; $i++ ) {
            $time_labels = array( 1 => '1 час', 2 => '1 день (24 часа)', 3 => '3 дня (72 часа)' );
            echo '<h3>Письмо №' . $i . ' (Отправляется через ' . $time_labels[$i] . ')</h3>';
            
            $subj = get_option( 'wc_ac_subj_' . $i, $default_subjs[$i] );
            echo '<table class="form-table"><tr><th scope="row">Тема письма</th>';
            echo '<td><input type="text" name="wc_ac_subj_' . $i . '" value="' . esc_attr( $subj ) . '" class="regular-text" style="width:100%;"></td></tr>';
            
            $body = get_option( 'wc_ac_body_' . $i, $this->get_default_body($i) );
            echo '<tr><th scope="row">Текст письма (HTML)</th><td>';
            
            wp_editor( $body, 'wc_ac_body_' . $i, array(
                'textarea_name' => 'wc_ac_body_' . $i,
                'media_buttons' => true,
                'textarea_rows' => 10,
                'teeny'         => false,
            ) );
            
            echo '</td></tr></table>';
            
            echo '<div style="background: #f0f0f1; padding: 10px 15px; margin-top: 10px; border-radius: 4px; display: flex; align-items: center; justify-content: space-between;">';
            echo '<span><strong>Проверить шаблон:</strong> отправить тестовое письмо</span>';
            echo '<div>';
            echo '<input type="email" name="test_email_' . $i . '" placeholder="Ваш email" value="' . esc_attr( wp_get_current_user()->user_email ) . '" style="width: 220px; margin-right: 10px;" id="test_email_input_' . $i . '">';
            echo '<button type="submit" name="wc_ac_send_test" class="button" onclick="document.getElementById(\'selected_test_step\').value = \'' . $i . '\'; document.getElementById(\'active_test_email\').value = document.getElementById(\'test_email_input_' . $i . '\').value;">Отправить тест</button>';
            echo '</div>';
            echo '</div>';
            
            echo '<hr style="margin: 30px 0;">';
        }

        echo '<input type="hidden" name="test_step" id="selected_test_step" value="1">';
        echo '<input type="hidden" name="test_email" id="active_test_email" value="">';
        wp_nonce_field( 'wc_ac_test_action', 'wc_ac_test_nonce' );

        echo '<p class="submit"><input type="submit" name="wc_ac_save_settings" id="submit" class="button button-primary" value="Сохранить все настройки"></p>';
        echo '</form></div>';
    }

    private function send_reminder( $cart_row, $step ) {
        $to = $cart_row->email;
        $checkout_url = wc_get_checkout_url();
        
        $default_subjs = array( 1 => 'Nepametiet savu grozu', 2 => 'Saglabājām Jūsu pasūtījumu', 3 => 'Pēdējais atgādinājums par Jūsu grozu' );
        $subject = get_option( 'wc_ac_subj_' . $step, $default_subjs[$step] );
        $body = get_option( 'wc_ac_body_' . $step, $this->get_default_body($step) );

        $cart_items_html = '<div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        $cart_items = maybe_unserialize( $cart_row->cart_data );
        if (is_array($cart_items)) {
            foreach ( $cart_items as $item ) {
                $product = wc_get_product( $item['product_id'] );
                if ( $product ) {
                    $cart_items_html .= '<div style="margin-bottom: 5px;">&bull; ' . $product->get_name() . ' (Skaits: ' . $item['quantity'] . ')</div>';
                } else {
                    $cart_items_html .= '<div style="margin-bottom: 5px;">&bull; Dabīgā vaska galda sveču komplekts (Skaits: 1)</div>';
                }
            }
        }
        $cart_items_html .= '</div>';

        $body = str_replace( '{cart_items}', $cart_items_html, $body );
        $body = str_replace( '{checkout_url}', $checkout_url, $body );

        $message = '<html><body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px;">';
        $message .= $body;
        $message .= '</body></html>';

        wp_mail( $to, $subject, $message, array('Content-Type: text/html; charset=UTF-8') );
    }
}
new WC_Abandoned_Cart_Pro();