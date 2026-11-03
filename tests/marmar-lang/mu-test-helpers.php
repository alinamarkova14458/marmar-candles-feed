<?php
/**
 * Test-only helpers (installed as mu-plugin in the local test site only).
 * - Logs outgoing mail to MMWP_MAIL_LOG instead of sending it.
 */
add_filter( 'pre_wp_mail', function ( $null, $atts ) {
	if ( defined( 'MMWP_MAIL_LOG' ) ) {
		file_put_contents( MMWP_MAIL_LOG, wp_json_encode( array(
			'to'      => $atts['to'],
			'subject' => $atts['subject'],
			'message' => $atts['message'],
		) ) . "\n", FILE_APPEND );
	}
	return true;
}, 10, 2 );
