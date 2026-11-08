<?php
/**
 * Plugin Name:       MarMar WebP
 * Description:       Izveido WebP kopijas visiem JPG/PNG attēliem (oriģināli netiek mainīti) un vietnē rāda WebP. Jauniem attēliem WebP top automātiski.
 * Version:           1.0.0
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_WebP {

	const OPT     = 'marmar_webp_state';
	const QUALITY = 80;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'on_upload' ), 99, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'buffer' ), 0 );
		add_action( 'delete_attachment', array( __CLASS__, 'on_delete' ) );
	}

	/* ---------- Konvertēšana ---------- */

	public static function engine() {
		if ( class_exists( 'Imagick' ) && in_array( 'WEBP', Imagick::queryFormats( 'WEBP' ), true ) ) {
			return 'imagick';
		}
		if ( function_exists( 'imagewebp' ) ) {
			return 'gd';
		}
		return '';
	}

	/** Faila ceļš → ceļš.webp (piem. foto-300x300.png.webp). */
	public static function target( $path ) {
		return $path . '.webp';
	}

	public static function convert( $src ) {
		if ( ! is_file( $src ) || ! preg_match( '/\.(jpe?g|png)$/i', $src ) ) {
			return 'skip';
		}
		$dst = self::target( $src );
		if ( is_file( $dst ) && filemtime( $dst ) >= filemtime( $src ) ) {
			return 'exists';
		}
		$ok = false;
		try {
			if ( 'imagick' === self::engine() ) {
				$im = new Imagick( $src );
				$im->setImageFormat( 'webp' );
				$im->setImageCompressionQuality( self::QUALITY );
				$im->setOption( 'webp:method', '4' );
				$im->stripImage();
				$ok = $im->writeImage( $dst );
				$im->clear();
			} elseif ( 'gd' === self::engine() ) {
				$is_png = (bool) preg_match( '/\.png$/i', $src );
				$im     = $is_png ? @imagecreatefrompng( $src ) : @imagecreatefromjpeg( $src );
				if ( $im ) {
					if ( $is_png ) {
						imagepalettetotruecolor( $im );
						imagealphablending( $im, true );
						imagesavealpha( $im, true );
					}
					$ok = imagewebp( $im, $dst, self::QUALITY );
					imagedestroy( $im );
				}
			}
		} catch ( Throwable $e ) {
			$ok = false;
		}
		if ( ! $ok || ! is_file( $dst ) || filesize( $dst ) < 50 ) {
			@unlink( $dst );
			return 'fail';
		}
		// Ja WebP nav mazāks — nevajag.
		if ( filesize( $dst ) >= filesize( $src ) ) {
			@unlink( $dst );
			return 'bigger';
		}
		return 'ok';
	}

	public static function files_of( $id ) {
		$file = get_attached_file( $id );
		if ( ! $file ) {
			return array();
		}
		$out  = array( $file );
		$meta = wp_get_attachment_metadata( $id );
		$dir  = dirname( $file );
		if ( ! empty( $meta['original_image'] ) ) {
			$out[] = $dir . '/' . $meta['original_image'];
		}
		foreach ( (array) ( $meta['sizes'] ?? array() ) as $s ) {
			if ( ! empty( $s['file'] ) ) {
				$out[] = $dir . '/' . $s['file'];
			}
		}
		return array_unique( $out );
	}

	public static function on_upload( $meta, $id ) {
		if ( self::engine() ) {
			// Pēc metadatu saglabāšanas.
			add_action( 'shutdown', function () use ( $id ) {
				foreach ( self::files_of( $id ) as $f ) {
					self::convert( $f );
				}
			} );
		}
		return $meta;
	}

	public static function on_delete( $id ) {
		foreach ( self::files_of( $id ) as $f ) {
			@unlink( self::target( $f ) );
		}
	}

	/* ---------- REST: statuss un partiju palaišana ---------- */

	public static function routes() {
		$perm = function () {
			return current_user_can( 'manage_options' );
		};
		register_rest_route( 'marmar-webp/v1', '/status', array(
			'methods'             => 'GET',
			'permission_callback' => $perm,
			'callback'            => function () {
				global $wpdb;
				$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type IN ('image/jpeg','image/png')" );
				return array(
					'engine' => self::engine(),
					'gd'     => function_exists( 'gd_info' ) ? gd_info() : null,
					'total'  => $total,
					'state'  => get_option( self::OPT, array() ),
					'enabled'=> (bool) get_option( 'marmar_webp_enabled', 0 ),
				);
			},
		) );
		register_rest_route( 'marmar-webp/v1', '/run', array(
			'methods'             => 'POST',
			'permission_callback' => $perm,
			'callback'            => function ( WP_REST_Request $r ) {
				global $wpdb;
				$after = (int) $r->get_param( 'after' );
				$n     = max( 1, min( 40, (int) ( $r->get_param( 'n' ) ?: 15 ) ) );
				$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type IN ('image/jpeg','image/png') AND ID > %d ORDER BY ID ASC LIMIT %d", $after, $n ) );
				$stat  = array();
				$last  = $after;
				$t0    = microtime( true );
				foreach ( $ids as $id ) {
					foreach ( self::files_of( (int) $id ) as $f ) {
						$res          = self::convert( $f );
						$stat[ $res ] = ( $stat[ $res ] ?? 0 ) + 1;
					}
					$last = (int) $id;
					if ( microtime( true ) - $t0 > 20 ) {
						break;
					}
				}
				update_option( self::OPT, array( 'last' => $last, 'time' => time() ), false );
				return array( 'last' => $last, 'done' => count( $ids ) < $n && $last === (int) end( $ids ) || ! $ids, 'stat' => $stat, 'sec' => round( microtime( true ) - $t0, 1 ) );
			},
		) );
		register_rest_route( 'marmar-webp/v1', '/enable', array(
			'methods'             => 'POST',
			'permission_callback' => $perm,
			'callback'            => function ( WP_REST_Request $r ) {
				update_option( 'marmar_webp_enabled', $r->get_param( 'on' ) ? 1 : 0 );
				return array( 'enabled' => (bool) get_option( 'marmar_webp_enabled' ) );
			},
		) );
	}

	/* ---------- Vietnē: .png/.jpg → .webp, ja fails eksistē ---------- */

	public static function buffer() {
		if ( is_admin() || wp_doing_ajax() || ! get_option( 'marmar_webp_enabled' ) || is_feed() ) {
			return;
		}
		ob_start( array( __CLASS__, 'filter_html' ) );
	}

	public static function filter_html( $html ) {
		if ( strlen( $html ) < 200 ) {
			return $html;
		}
		$up   = wp_get_upload_dir();
		$base = $up['baseurl']; // https://marmarcandles.com/wp-content/uploads
		$dir  = $up['basedir'];
		$host = wp_parse_url( $base, PHP_URL_HOST );
		$path = wp_parse_url( $base, PHP_URL_PATH ); // /wp-content/uploads
		// Parasts un JSON (ar \/) pieraksts; ar vai bez www.
		$re = '~(?:(https?:(?:\\\\?/){2}(?:www\.)?' . preg_quote( $host, '~' ) . ')|(?<=["\'\s(=,;]))(' . str_replace( '/', '\\\\?/', preg_quote( $path, '~' ) ) . ')((?:\\\\?/[^"\'\s<>()?\\\\,]+?)+?\.(?:png|jpe?g))(?=[\s"\'<>),?\\\\&]|$)~i';
		static $cache = array();
		return preg_replace_callback( $re, function ( $m ) use ( $dir, &$cache ) {
			$rel = str_replace( '\\/', '/', $m[3] );
			if ( ! isset( $cache[ $rel ] ) ) {
				$cache[ $rel ] = is_file( $dir . rawurldecode( $rel ) . '.webp' );
			}
			return $cache[ $rel ] ? $m[0] . '.webp' : $m[0];
		}, $html );
	}
}

add_action( 'plugins_loaded', array( 'MarMar_WebP', 'init' ) );
