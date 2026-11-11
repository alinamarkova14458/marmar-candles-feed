<?php
defined( 'ABSPATH' ) || exit;

/**
 * Загрузка файлов пары (дизайн/приглашение/референс).
 * Файлы лежат в закрытой папке uploads/marmar-wedding/ (доступ из веба запрещён),
 * открыть их можно только из админки через проверку прав.
 */
class MarMar_Wedding_Uploads {

	const MAX_BYTES = 10485760; // 10 МБ
	const MAX_FILES = 3;
	const TYPES     = array(
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'webp'     => 'image/webp',
		'heic'     => 'image/heic',
		'pdf'      => 'application/pdf',
	);

	public static function init() {
		add_action( 'wc_ajax_marmar_wedding_upload', array( __CLASS__, 'ajax_upload' ) );
		add_action( 'admin_post_marmar_wedding_file', array( __CLASS__, 'admin_download' ) );
		add_action( 'admin_init', array( __CLASS__, 'cleanup' ) );
	}

	public static function dir() {
		$u = wp_upload_dir( null, false );
		return trailingslashit( $u['basedir'] ) . 'marmar-wedding';
	}

	public static function protect_dir() {
		$dir = self::dir();
		wp_mkdir_p( $dir );
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore
		}
		if ( ! file_exists( $dir . '/index.html' ) ) {
			file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore
		}
	}

	public static function ajax_upload() {
		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_send_json_error( array( 'message' => 'Fails nav saņemts.' ), 400 );
		}
		// Простая защита от злоупотреблений: не больше 15 загрузок в час с одного IP.
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'mmw_up_' . substr( md5( $ip . wp_salt() ), 0, 16 );
		$n   = (int) get_transient( $key );
		if ( $n >= 15 ) {
			wp_send_json_error( array( 'message' => 'Pārāk daudz failu. Lūdzu, pamēģiniet vēlāk.' ), 429 );
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );

		$file = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidationSanitization
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'Neizdevās augšupielādēt failu.' ), 400 );
		}
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			wp_send_json_error( array( 'message' => 'Fails ir lielāks par 10 MB.' ), 400 );
		}
		$name  = sanitize_file_name( wp_unslash( $file['name'] ) );
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $name, self::TYPES );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			wp_send_json_error( array( 'message' => 'Atļautie formāti: JPG, PNG, WEBP, HEIC, PDF.' ), 400 );
		}

		self::protect_dir();
		$token  = wp_generate_password( 24, false, false );
		$stored = $token . '.' . $check['ext'];
		if ( ! move_uploaded_file( $file['tmp_name'], self::dir() . '/' . $stored ) ) {
			wp_send_json_error( array( 'message' => 'Neizdevās saglabāt failu.' ), 500 );
		}
		update_option( 'marmar_wedding_file_' . $token, array( 'stored' => $stored, 'name' => $name, 'time' => time() ), false );
		wp_send_json_success( array( 'token' => $token, 'name' => $name ) );
	}

	public static function mark_used( $token ) {
		$key  = 'marmar_wedding_file_' . preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
		$info = get_option( $key );
		if ( is_array( $info ) ) {
			$info['used'] = 1;
			update_option( $key, $info, false );
		}
	}

	/** Раз в сутки удаляет файлы, которые загрузили, но так и не оформили заказ (старше 7 дней). */
	public static function cleanup() {
		global $wpdb;
		if ( get_transient( 'marmar_wedding_cleanup' ) ) {
			return;
		}
		set_transient( 'marmar_wedding_cleanup', 1, DAY_IN_SECONDS );
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 500", $wpdb->esc_like( 'marmar_wedding_file_' ) . '%' ) );
		foreach ( $names as $name ) {
			$info = get_option( $name );
			if ( is_array( $info ) && empty( $info['used'] ) && (int) $info['time'] < time() - 7 * DAY_IN_SECONDS ) {
				$path = self::dir() . '/' . basename( $info['stored'] );
				if ( file_exists( $path ) ) {
					wp_delete_file( $path );
				}
				delete_option( $name );
			}
		}
	}

	/** @return array{token:string,name:string}|null */
	public static function get( $token ) {
		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
		$info  = $token ? get_option( 'marmar_wedding_file_' . $token ) : null;
		if ( ! is_array( $info ) || ! file_exists( self::dir() . '/' . $info['stored'] ) ) {
			return null;
		}
		return array( 'token' => $token, 'name' => $info['name'], 'stored' => $info['stored'] );
	}

	public static function admin_url( $token ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=marmar_wedding_file&f=' . rawurlencode( $token ) ), 'marmar_wedding_file' );
	}

	public static function admin_download() {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( 'No access', 403 );
		}
		check_admin_referer( 'marmar_wedding_file' );
		$f = self::get( isset( $_GET['f'] ) ? sanitize_text_field( wp_unslash( $_GET['f'] ) ) : '' );
		if ( ! $f ) {
			wp_die( 'Fails nav atrasts.', 404 );
		}
		$path = self::dir() . '/' . $f['stored'];
		$type = wp_check_filetype( $path );
		nocache_headers();
		header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
		header( 'Content-Disposition: inline; filename="' . str_replace( '"', '', $f['name'] ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore
		exit;
	}
}
