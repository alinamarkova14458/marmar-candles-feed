<?php
/**
 * REST для удалённой работы (черновики, тест, статистика).
 *
 * Черновики создаются стандартным маршрутом /wp/v2/marmar-campaigns.
 * Запуска рассылки всем через REST нет намеренно: только кнопкой в админке.
 */

defined( 'ABSPATH' ) || exit;

class Marmar_NL_Rest {

	const NS = 'marmar-newsletter/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function can() {
		return current_user_can( 'manage_woocommerce' );
	}

	public static function routes() {
		register_rest_route(
			self::NS,
			'/stats',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( __CLASS__, 'can' ),
				'callback'            => static function () {
					$campaigns = get_posts(
						array(
							'post_type'   => Marmar_NL_Campaigns::CPT,
							'post_status' => 'any',
							'numberposts' => 20,
						)
					);
					return array(
						'subscribers' => Marmar_NL_Subscribers::counts(),
						'smtp_ready'  => Marmar_NL_Mailer::smtp_ready(),
						'campaigns'   => array_map(
							static function ( $p ) {
								return array(
									'id'      => $p->ID,
									'title'   => $p->post_title,
									'subject' => get_post_meta( $p->ID, '_mm_subject', true ),
									'state'   => Marmar_NL_Campaigns::state( $p->ID ),
									'stats'   => Marmar_NL_Campaigns::stats( $p->ID ),
								);
							},
							$campaigns
						),
					);
				},
			)
		);

		register_rest_route(
			self::NS,
			'/campaigns/(?P<id>\d+)/test',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'can' ),
				'callback'            => static function ( WP_REST_Request $req ) {
					$emails = preg_split( '/[\s,;]+/', (string) Marmar_NL_Mailer::settings()['test_emails'], -1, PREG_SPLIT_NO_EMPTY );
					return Marmar_NL_Campaigns::send_test( (int) $req['id'], $emails );
				},
			)
		);

		register_rest_route(
			self::NS,
			'/campaigns/(?P<id>\d+)/preview',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( __CLASS__, 'can' ),
				'callback'            => static function ( WP_REST_Request $req ) {
					$id  = (int) $req['id'];
					$sub = (object) array( 'first_name' => 'Alina', 'token' => '' );
					$res = new WP_REST_Response(
						array(
							'subject' => get_post_meta( $id, '_mm_subject', true ),
							'html'    => Marmar_NL_Mailer::render(
								Marmar_NL_Mailer::personalize( Marmar_NL_Campaigns::content_html( $id ), $sub, Marmar_NL_Campaigns::slug( $id ) ),
								get_post_meta( $id, '_mm_preheader', true ),
								$sub
							),
						)
					);
					return $res;
				},
			)
		);
	}
}
