<?php
/**
 * Plugin Name:       MarMar Media Rename
 * Description:       Servisa rīks: pārdēvē attēla failu (oriģināls, visi izmēri, LiteSpeed .webp/.avif kopijas), atjauno saites saturā un saglabā žurnālu atjaunošanai. REST: POST /wp-json/marmar/v1/rename (tikai administratoriem).
 * Version:           1.0.0
 * Author:            MarMar CANDLES
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

final class MarMar_Media_Rename {

	const LOG = 'marmar_media_rename_log';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'marmar/v1',
			'/rename',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => array( __CLASS__, 'rest_rename' ),
				'args'                => array(
					'id'      => array( 'required' => true, 'type' => 'integer' ),
					'slug'    => array( 'required' => true, 'type' => 'string' ),
					'alt'     => array( 'type' => 'string' ),
					'title'   => array( 'type' => 'string' ),
					'dry_run' => array( 'type' => 'boolean', 'default' => false ),
				),
			)
		);
		register_rest_route(
			'marmar/v1',
			'/rename-log',
			array(
				'methods'             => 'GET',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => function () {
					return get_option( self::LOG, array() );
				},
			)
		);
	}

	public static function rest_rename( WP_REST_Request $r ) {
		$res = self::rename( (int) $r['id'], (string) $r['slug'], (bool) $r['dry_run'] );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( ! $r['dry_run'] ) {
			if ( isset( $r['alt'] ) && '' !== $r['alt'] ) {
				update_post_meta( (int) $r['id'], '_wp_attachment_image_alt', sanitize_text_field( $r['alt'] ) );
				$res['alt'] = sanitize_text_field( $r['alt'] );
			}
			if ( isset( $r['title'] ) && '' !== $r['title'] ) {
				wp_update_post( array( 'ID' => (int) $r['id'], 'post_title' => sanitize_text_field( $r['title'] ) ) );
			}
		}
		return $res;
	}

	/**
	 * @return array|WP_Error
	 */
	public static function rename( $id, $slug, $dry = false ) {
		global $wpdb;
		$post = get_post( $id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new WP_Error( 'mmr_not_found', 'Attachment not found', array( 'status' => 404 ) );
		}
		$rel = get_post_meta( $id, '_wp_attached_file', true ); // 2026/04/foo-scaled.jpg
		$up  = wp_get_upload_dir();
		$abs = trailingslashit( $up['basedir'] ) . $rel;
		if ( ! $rel || ! file_exists( $abs ) ) {
			return new WP_Error( 'mmr_no_file', 'File missing: ' . $rel, array( 'status' => 400 ) );
		}
		$meta    = wp_get_attachment_metadata( $id );
		$dir     = dirname( $abs );
		$subdir  = trim( dirname( $rel ), '/.' );
		$file    = basename( $abs );
		$ext     = pathinfo( $file, PATHINFO_EXTENSION );
		$scaled  = (bool) preg_match( '/-scaled\.' . preg_quote( $ext, '/' ) . '$/', $file );
		$oldbase = preg_replace( '/(-scaled)?\.' . preg_quote( $ext, '/' ) . '$/', '', $file );
		$slug    = sanitize_title( $slug );
		if ( '' === $slug ) {
			return new WP_Error( 'mmr_slug', 'Empty slug', array( 'status' => 400 ) );
		}
		if ( $slug === $oldbase ) {
			return array( 'id' => $id, 'skipped' => 'same name' );
		}
		// Unikāls nosaukums: pārbaudām oriģinālu un “-scaled”.
		$newbase = $slug;
		$n       = 1;
		while ( file_exists( "$dir/$newbase.$ext" ) || file_exists( "$dir/$newbase-scaled.$ext" ) ) {
			$n++;
			$newbase = $slug . '-' . $n;
		}

		// Visu pārdēvējamo failu saraksts: [vecais => jaunais].
		$map  = array();
		$add  = function ( $old_fn ) use ( &$map, $oldbase, $newbase, $dir ) {
			if ( 0 !== strpos( $old_fn, $oldbase ) ) {
				return;
			}
			$new_fn = $newbase . substr( $old_fn, strlen( $oldbase ) );
			foreach ( array( '', '.webp', '.avif' ) as $suffix ) {
				if ( file_exists( "$dir/$old_fn$suffix" ) ) {
					$map[ $old_fn . $suffix ] = $new_fn . $suffix;
				}
			}
			// LiteSpeed rezerves kopija: foo.bk.jpg
			$bk = preg_replace( '/\.([a-z0-9]+)$/i', '.bk.$1', $old_fn );
			if ( file_exists( "$dir/$bk" ) ) {
				$map[ $bk ] = preg_replace( '/\.([a-z0-9]+)$/i', '.bk.$1', $new_fn );
			}
		};
		$add( $file );
		if ( ! empty( $meta['original_image'] ) ) {
			$add( $meta['original_image'] );
		}
		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $s ) {
				if ( ! empty( $s['file'] ) ) {
					$add( $s['file'] );
				}
			}
		}
		foreach ( $map as $new ) {
			if ( file_exists( "$dir/$new" ) ) {
				return new WP_Error( 'mmr_exists', 'Target exists: ' . $new, array( 'status' => 409 ) );
			}
		}
		$newfile = $newbase . substr( $file, strlen( $oldbase ) );
		$result  = array( 'id' => $id, 'old' => "$subdir/$file", 'new' => "$subdir/$newfile", 'files' => $map );
		if ( $dry ) {
			$result['dry_run'] = true;
			return $result;
		}

		$done = array();
		foreach ( $map as $old => $new ) {
			if ( ! @rename( "$dir/$old", "$dir/$new" ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				foreach ( $done as $o => $nn ) { // atpakaļ
					@rename( "$dir/$nn", "$dir/$o" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}
				return new WP_Error( 'mmr_rename', 'Rename failed: ' . $old, array( 'status' => 500 ) );
			}
			$done[ $old ] = $new;
		}

		// Metadati.
		update_post_meta( $id, '_wp_attached_file', ( $subdir ? "$subdir/" : '' ) . $newfile );
		if ( is_array( $meta ) ) {
			$meta['file'] = ( $subdir ? "$subdir/" : '' ) . $newfile;
			if ( ! empty( $meta['original_image'] ) && isset( $map[ $meta['original_image'] ] ) ) {
				$meta['original_image'] = $map[ $meta['original_image'] ];
			}
			if ( ! empty( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $k => $s ) {
					if ( isset( $map[ $s['file'] ] ) ) {
						$meta['sizes'][ $k ]['file'] = $map[ $s['file'] ];
					}
				}
			}
			wp_update_attachment_metadata( $id, $meta );
		}
		$wpdb->update( $wpdb->posts, array( 'post_name' => $newbase ), array( 'ID' => $id ) );

		// Saites saturā un meta laukos (tikai šī mape + precīzs faila vārds).
		$replaced = 0;
		foreach ( $map as $old => $new ) {
			if ( preg_match( '/\.(webp|avif)$/', $old ) && ! preg_match( '/\.(webp|avif)$/', $file ) ) {
				continue; // LiteSpeed kopijas saturā nefigurē
			}
			$from = "/$subdir/$old";
			$to   = "/$subdir/$new";
			$like = '%' . $wpdb->esc_like( $from ) . '%';
			$replaced += (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s", $from, $to, $like ) ); // phpcs:ignore
			$replaced += (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_value LIKE %s AND meta_value NOT LIKE 'a:%%' AND meta_value NOT LIKE 'O:%%'", $from, $to, $like ) ); // phpcs:ignore
		}
		clean_post_cache( $id );
		wp_cache_flush();

		$log   = get_option( self::LOG, array() );
		$log[] = array( 'time' => current_time( 'mysql' ), 'id' => $id, 'old' => $result['old'], 'new' => $result['new'], 'files' => $map );
		update_option( self::LOG, $log, false );

		$result['replaced_rows'] = $replaced;
		return $result;
	}
}

add_action( 'plugins_loaded', array( 'MarMar_Media_Rename', 'init' ) );
