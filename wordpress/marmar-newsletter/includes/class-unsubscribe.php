<?php
/**
 * Отписка: страница с кнопкой (GET) и отписка в один клик (POST, RFC 8058).
 *
 * GET сам не отписывает: почтовые сканеры открывают ссылки заранее.
 */

defined( 'ABSPATH' ) || exit;

class Marmar_NL_Unsubscribe {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 1 );
	}

	public static function url( $subscriber ) {
		$token = $subscriber && ! empty( $subscriber->token ) ? $subscriber->token : 'tests-paraugs-bez-darbibas';
		return add_query_arg( 'marmar_unsub', $token, home_url( '/' ) );
	}

	public static function handle() {
		if ( ! isset( $_GET['marmar_unsub'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$token      = sanitize_text_field( wp_unslash( $_GET['marmar_unsub'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$subscriber = Marmar_NL_Subscribers::get_by_token( $token );
		$is_post    = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' );

		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'marmar newsletter unsubscribe' );
		header( 'X-Robots-Tag: noindex, nofollow' );

		if ( $is_post && $subscriber ) {
			Marmar_NL_Subscribers::unsubscribe( $subscriber->id );
			// Почтовый клиент (One-Click) страницу не показывает.
			if ( isset( $_POST['List-Unsubscribe'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				status_header( 200 );
				exit;
			}
			self::page(
				'Tu esi atrakstījies(-usies)',
				'<p>Vairs nesūtīšu tev jaunumus. Paldies, ka biji kopā ar mani!</p><p>Ja pārdomāsi, vari atkal atzīmēt piekrišanu nākamajā pasūtījumā.</p>'
			);
		}

		if ( ! $subscriber ) {
			self::page( 'Saite nav derīga', '<p>Šī atrakstīšanās saite nav derīga vai jau ir izmantota. Ja vēstules joprojām pienāk, uzraksti man, un es to sakārtošu.</p>' );
		}

		if ( 'subscribed' !== $subscriber->status ) {
			self::page( 'Tu jau esi atrakstījies(-usies)', '<p>Jaunumi tev vairs netiek sūtīti.</p>' );
		}

		$action = esc_url( self::url( $subscriber ) );
		self::page(
			'Atrakstīties no jaunumiem?',
			'<p>Adrese: <strong>' . esc_html( $subscriber->email ) . '</strong></p>'
			. '<form method="post" action="' . $action . '"><button type="submit" style="background:#8a4b3c;color:#fff;border:0;border-radius:24px;padding:12px 28px;font-size:16px;cursor:pointer;">Jā, atrakstīties</button></form>'
			. '<p style="margin-top:24px;"><a href="' . esc_url( home_url( '/' ) ) . '">Nē, palikt — atpakaļ uz veikalu</a></p>'
		);
	}

	private static function page( $title, $body_html ) {
		status_header( 200 );
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo '<!DOCTYPE html><html lang="lv"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . esc_html( $title ) . '</title></head>'
			. '<body style="margin:0;background:#f6efe9;font-family:Arial,Helvetica,sans-serif;color:#3d2b26;">'
			. '<div style="max-width:520px;margin:60px auto;padding:36px 28px;background:#fff;border-radius:16px;text-align:center;line-height:1.6;">'
			. '<h1 style="font-family:Georgia,serif;font-weight:normal;font-size:26px;margin-top:0;">' . esc_html( $title ) . '</h1>'
			. $body_html // phpcs:ignore WordPress.Security.EscapeOutput
			. '</div></body></html>';
		exit;
	}
}
