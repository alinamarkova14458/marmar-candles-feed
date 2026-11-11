<?php
/**
 * MarMar Candles — skaistā HTML vēstule konditoriem (logo, rozā poga, nosacījumi).
 * Tabulas un iekļauti stili, lai izskatītos labi Gmail, Outlook un telefonā.
 */
defined( 'ABSPATH' ) || exit;

final class MarMar_KC_Mail {

	const LOGO = 'https://marmarcandles.com/wp-content/uploads/2026/04/marmar-candles-logo1-1.png';

	/**
	 * @param array $a title, hello, intro (HTML), button_url, button_label, items (string[]), note, preheader
	 */
	public static function html( $a ) {
		$e     = function ( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); };
		$items = '';
		foreach ( (array) ( $a['items'] ?? array() ) as $it ) {
			$items .= '<tr><td width="26" valign="top" style="padding:6px 0;color:#B9605F;font-size:16px;line-height:22px;">&#10003;</td><td style="padding:6px 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:22px;color:#504E4A;">' . $it . '</td></tr>';
		}
		$btn = '';
		if ( ! empty( $a['button_url'] ) ) {
			$btn = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:26px auto 8px;"><tr><td align="center" bgcolor="#B9605F" style="border-radius:999px;">'
				. '<a href="' . $e( $a['button_url'] ) . '" target="_blank" style="display:inline-block;padding:15px 34px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;color:#FFFFFF;text-decoration:none;border-radius:999px;">' . $e( $a['button_label'] ) . '</a>'
				. '</td></tr></table>';
			if ( ! empty( $a['button_hint'] ) ) {
				$btn .= '<p style="margin:0 0 4px;text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#8a7f7c;">' . $a['button_hint'] . '</p>';
			}
		}
		$h  = '<!doctype html><html lang="lv"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e( $a['title'] ) . '</title></head>';
		$h .= '<body style="margin:0;padding:0;background:#FAF3F0;">';
		$h .= '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . $e( $a['preheader'] ?? '' ) . '</div>';
		$h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#FAF3F0"><tr><td align="center" style="padding:28px 12px;">';
		$h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">';
		// Logo.
		$h .= '<tr><td align="center" style="padding:6px 0 22px;"><a href="https://marmarcandles.com/lv/" target="_blank"><img src="' . self::LOGO . '" width="230" alt="MARMAR CANDLES" style="display:block;width:230px;max-width:70%;height:auto;border:0;"></a></td></tr>';
		// Kartīte.
		$h .= '<tr><td bgcolor="#FFFFFF" style="background:#FFFFFF;border-radius:22px;padding:34px 30px 30px;border:1px solid #F0D9D6;">';
		$h .= '<p style="margin:0 0 6px;text-align:center;font-size:30px;line-height:34px;">&#127874;</p>';
		$h .= '<h1 style="margin:0 0 18px;text-align:center;font-family:Georgia,\'Times New Roman\',serif;font-weight:normal;font-size:24px;line-height:31px;letter-spacing:1.5px;text-transform:uppercase;color:#735360;">' . $e( $a['title'] ) . '</h1>';
		$h .= '<p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:24px;color:#504E4A;">' . $e( $a['hello'] ) . '</p>';
		$h .= '<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:24px;color:#504E4A;">' . $a['intro'] . '</p>';
		$h .= $btn;
		if ( $items ) {
			$h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:22px;background:#FBF3F2;border-radius:16px;"><tr><td style="padding:16px 20px;">';
			$h .= '<p style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;color:#B9605F;">Konditoriem</p>';
			$h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $items . '</table></td></tr></table>';
		}
		if ( ! empty( $a['note'] ) ) {
			$h .= '<p style="margin:20px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:23px;color:#504E4A;">' . $a['note'] . '</p>';
		}
		$h .= '<p style="margin:22px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:23px;color:#504E4A;">Ar cieņu,<br><strong style="color:#735360;">MarMar Candles</strong></p>';
		$h .= '</td></tr>';
		// Kājene.
		$h .= '<tr><td align="center" style="padding:20px 10px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:19px;color:#8a7f7c;">Roku darbs Rīgā &middot; <a href="https://marmarcandles.com/lv/" style="color:#B9605F;text-decoration:none;">marmarcandles.com</a> &middot; <a href="https://www.instagram.com/marmar.candles" style="color:#B9605F;text-decoration:none;">@marmar.candles</a></td></tr>';
		$h .= '</table></td></tr></table></body></html>';
		return $h;
	}

	/** Teksta versija (multipart nav — WP sūta HTML; teksts paliek kā rezerves variants nākotnei). */
	public static function send( $to, $subject, $a ) {
		$set = function () { return 'text/html'; };
		add_filter( 'wp_mail_content_type', $set );
		$ok = wp_mail( $to, $subject, self::html( $a ) );
		remove_filter( 'wp_mail_content_type', $set );
		return $ok;
	}
}
