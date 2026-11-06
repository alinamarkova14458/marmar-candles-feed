<?php
/**
 * Кампании: тип записи, метаполя, очередь отправки порциями.
 *
 * Статусы рассылки (_mm_state): draft → sending → sent (или paused).
 * Запустить отправку всем можно только кнопкой в админке (Marmar_NL_Admin),
 * через REST — только черновик и тест.
 */

defined( 'ABSPATH' ) || exit;

class Marmar_NL_Campaigns {

	const CPT        = 'marmar_campaign';
	const BATCH_HOOK = 'marmar_nl_send_batch';
	const GROUP      = 'marmar-newsletter';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'classic_editor' ), 10, 2 );
		add_action( self::BATCH_HOOK, array( __CLASS__, 'process_batch' ), 10, 1 );
	}

	public static function register() {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => 'Рассылки',
					'singular_name' => 'Рассылка',
					'add_new'       => 'Новая рассылка',
					'add_new_item'  => 'Новая рассылка',
					'edit_item'     => 'Редактировать рассылку',
					'all_items'     => 'Все рассылки',
					'menu_name'     => 'Рассылки',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-email-alt',
				'menu_position'   => 56,
				'supports'        => array( 'title', 'editor' ),
				'show_in_rest'    => true,
				'rest_base'       => 'marmar-campaigns',
				'capability_type' => 'post',
				'capabilities'    => array(
					'edit_post'          => 'manage_woocommerce',
					'read_post'          => 'manage_woocommerce',
					'delete_post'        => 'manage_woocommerce',
					'edit_posts'         => 'manage_woocommerce',
					'edit_others_posts'  => 'manage_woocommerce',
					'publish_posts'      => 'manage_woocommerce',
					'read_private_posts' => 'manage_woocommerce',
					'delete_posts'       => 'manage_woocommerce',
					'create_posts'       => 'manage_woocommerce',
				),
				'map_meta_cap'    => false,
			)
		);

		$auth = static function () {
			return current_user_can( 'manage_woocommerce' );
		};
		foreach ( array( '_mm_subject', '_mm_preheader' ) as $key ) {
			register_post_meta(
				self::CPT,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => $auth,
				)
			);
		}
		register_post_meta(
			self::CPT,
			'_mm_audience',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => 'consent',
				'show_in_rest'      => array( 'schema' => array( 'enum' => array( 'consent', 'all' ) ) ),
				'sanitize_callback' => static function ( $v ) {
					return 'all' === $v ? 'all' : 'consent';
				},
				'auth_callback'     => $auth,
			)
		);
	}

	public static function classic_editor( $use, $post_type ) {
		return self::CPT === $post_type ? false : $use;
	}

	public static function state( $campaign_id ) {
		$state = get_post_meta( $campaign_id, '_mm_state', true );
		return $state ? $state : 'draft';
	}

	public static function slug( $campaign_id ) {
		$post = get_post( $campaign_id );
		$slug = $post && $post->post_name ? $post->post_name : sanitize_title( $post ? $post->post_title : '' );
		return $slug ? $slug : 'kampana-' . $campaign_id;
	}

	public static function content_html( $campaign_id ) {
		$post = get_post( $campaign_id );
		return $post ? wpautop( do_shortcode( $post->post_content ) ) : '';
	}

	/**
	 * Проверка перед любой отправкой.
	 *
	 * @return true|string
	 */
	public static function validate( $campaign_id ) {
		$post = get_post( $campaign_id );
		if ( ! $post || self::CPT !== $post->post_type ) {
			return 'Рассылка не найдена.';
		}
		if ( '' === trim( (string) get_post_meta( $campaign_id, '_mm_subject', true ) ) ) {
			return 'Не заполнена тема письма.';
		}
		if ( '' === trim( wp_strip_all_tags( $post->post_content ) ) ) {
			return 'Письмо пустое.';
		}
		return true;
	}

	/**
	 * Тестовое письмо на указанные адреса.
	 *
	 * @return array<string,true|string> адрес => результат
	 */
	public static function send_test( $campaign_id, array $emails ) {
		$valid = self::validate( $campaign_id );
		if ( true !== $valid ) {
			return array( '*' => $valid );
		}
		$out = array();
		foreach ( $emails as $email ) {
			$email = Marmar_NL_Subscribers::normalize_email( $email );
			if ( ! $email ) {
				continue;
			}
			$fake        = Marmar_NL_Subscribers::get_by_email( $email );
			$preview_sub = (object) array(
				'first_name' => $fake ? $fake->first_name : 'Alina',
				'token'      => '',
			);
			$out[ $email ] = Marmar_NL_Mailer::send(
				$email,
				'[TESTS] ' . get_post_meta( $campaign_id, '_mm_subject', true ),
				self::content_html( $campaign_id ),
				get_post_meta( $campaign_id, '_mm_preheader', true ),
				$preview_sub,
				self::slug( $campaign_id )
			);
		}
		return $out;
	}

	/**
	 * Поставить рассылку в очередь. Повторный запуск не дублирует письма:
	 * пара (рассылка, подписчик) уникальна.
	 *
	 * @return int|string число добавленных в очередь или текст ошибки
	 */
	public static function start( $campaign_id ) {
		global $wpdb;
		$valid = self::validate( $campaign_id );
		if ( true !== $valid ) {
			return $valid;
		}
		if ( 'sent' === self::state( $campaign_id ) ) {
			return 'Эта рассылка уже отправлена.';
		}
		$audience = get_post_meta( $campaign_id, '_mm_audience', true );
		$audience = 'all' === $audience ? 'all' : 'consent';

		$queued = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . Marmar_NL_Subscribers::sends_table() . ' (campaign_id, subscriber_id, status) SELECT %d, id, %s FROM ' . Marmar_NL_Subscribers::table() . ' WHERE ' . Marmar_NL_Subscribers::audience_where( $audience ),
				$campaign_id,
				'queued'
			)
		);
		if ( false === $queued ) {
			return 'Ошибка базы данных: ' . $wpdb->last_error;
		}

		update_post_meta( $campaign_id, '_mm_state', 'sending' );
		if ( ! get_post_meta( $campaign_id, '_mm_started_at', true ) ) {
			update_post_meta( $campaign_id, '_mm_started_at', current_time( 'mysql', true ) );
		}
		if ( 'publish' !== get_post_status( $campaign_id ) ) {
			wp_update_post( array( 'ID' => $campaign_id, 'post_status' => 'publish' ) );
		}
		self::schedule( $campaign_id, 0 );
		return (int) $queued;
	}

	public static function pause( $campaign_id ) {
		update_post_meta( $campaign_id, '_mm_state', 'paused' );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::BATCH_HOOK, array( (int) $campaign_id ), self::GROUP );
		}
		wp_clear_scheduled_hook( self::BATCH_HOOK, array( (int) $campaign_id ) );
	}

	private static function schedule( $campaign_id, $delay ) {
		$args = array( (int) $campaign_id );
		if ( function_exists( 'as_schedule_single_action' ) ) {
			if ( ! as_has_scheduled_action( self::BATCH_HOOK, $args, self::GROUP ) ) {
				as_schedule_single_action( time() + $delay, self::BATCH_HOOK, $args, self::GROUP );
			}
			return;
		}
		if ( ! wp_next_scheduled( self::BATCH_HOOK, $args ) ) {
			wp_schedule_single_event( time() + $delay, self::BATCH_HOOK, $args );
		}
	}

	/**
	 * Отправить одну порцию. Следующая — через минуту, чтобы не упереться
	 * в лимиты хостинга и почты.
	 */
	public static function process_batch( $campaign_id ) {
		global $wpdb;
		$campaign_id = (int) $campaign_id;
		if ( 'sending' !== self::state( $campaign_id ) ) {
			return;
		}
		$lock = 'marmar_nl_lock_' . $campaign_id;
		if ( get_transient( $lock ) ) {
			return;
		}
		set_transient( $lock, 1, 5 * MINUTE_IN_SECONDS );

		$settings = Marmar_NL_Mailer::settings();
		$size     = max( 1, min( 100, (int) $settings['batch_size'] ) );
		$sends    = Marmar_NL_Subscribers::sends_table();
		$subs     = Marmar_NL_Subscribers::table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id AS send_id, u.* FROM {$sends} s JOIN {$subs} u ON u.id = s.subscriber_id WHERE s.campaign_id = %d AND s.status = 'queued' ORDER BY s.id ASC LIMIT %d",
				$campaign_id,
				$size
			)
		);

		$subject   = get_post_meta( $campaign_id, '_mm_subject', true );
		$preheader = get_post_meta( $campaign_id, '_mm_preheader', true );
		$html      = self::content_html( $campaign_id );
		$slug      = self::slug( $campaign_id );
		$now       = current_time( 'mysql', true );

		foreach ( $rows as $row ) {
			// Отписался после постановки в очередь — пропускаем.
			if ( 'subscribed' !== $row->status ) {
				$wpdb->update( $sends, array( 'status' => 'skipped', 'sent_at' => $now ), array( 'id' => $row->send_id ) );
				continue;
			}
			$result = Marmar_NL_Mailer::send( $row->email, $subject, $html, $preheader, $row, $slug );
			$wpdb->update(
				$sends,
				array(
					'status'  => true === $result ? 'sent' : 'failed',
					'error'   => true === $result ? '' : substr( (string) $result, 0, 250 ),
					'sent_at' => current_time( 'mysql', true ),
				),
				array( 'id' => $row->send_id )
			);
		}

		delete_transient( $lock );

		$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sends} WHERE campaign_id = %d AND status = 'queued'", $campaign_id ) );
		if ( $left > 0 ) {
			self::schedule( $campaign_id, MINUTE_IN_SECONDS );
		} else {
			update_post_meta( $campaign_id, '_mm_state', 'sent' );
			update_post_meta( $campaign_id, '_mm_finished_at', current_time( 'mysql', true ) );
		}
	}

	public static function stats( $campaign_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT status, COUNT(*) AS n FROM ' . Marmar_NL_Subscribers::sends_table() . ' WHERE campaign_id = %d GROUP BY status', $campaign_id )
		);
		$out = array( 'queued' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0 );
		foreach ( $rows as $r ) {
			$out[ $r->status ] = (int) $r->n;
		}
		return $out;
	}

	public static function last_errors( $campaign_id, $limit = 5 ) {
		global $wpdb;
		return $wpdb->get_col(
			$wpdb->prepare( 'SELECT DISTINCT error FROM ' . Marmar_NL_Subscribers::sends_table() . " WHERE campaign_id = %d AND status = 'failed' LIMIT %d", $campaign_id, $limit )
		);
	}

	/** Вернуть неудачные в очередь (например, после исправления SMTP). */
	public static function retry_failed( $campaign_id ) {
		global $wpdb;
		$n = $wpdb->update( Marmar_NL_Subscribers::sends_table(), array( 'status' => 'queued', 'error' => '' ), array( 'campaign_id' => (int) $campaign_id, 'status' => 'failed' ) );
		if ( $n ) {
			update_post_meta( $campaign_id, '_mm_state', 'sending' );
			self::schedule( $campaign_id, 0 );
		}
		return (int) $n;
	}
}
