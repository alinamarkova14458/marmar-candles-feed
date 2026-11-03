<?php
/**
 * Plugin Name: MarMar Lang
 * Description: Lightweight translations for MarMar Candles: language prefixes, translations in post/term meta, hreflang, WooCommerce language.
 * Version: 0.1.0
 * Author: MarMar Candles
 * Requires PHP: 8.0
 * Text Domain: marmar-lang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MM_LANG_VERSION = '0.1.0';
define( 'MM_LANG_DIR', __DIR__ );

spl_autoload_register( function ( $class ) {
	if ( str_starts_with( $class, 'MarMarLang\\' ) ) {
		$file = MM_LANG_DIR . '/src/' . substr( $class, strlen( 'MarMarLang\\' ) ) . '.php';
		if ( is_file( $file ) ) {
			require $file;
		}
	}
} );

/* ---------- Public API ---------- */

function mm_lang_default(): string {
	return MarMarLang\Languages::DEFAULT;
}

/** Enabled non-default languages, e.g. ['en']. */
function mm_lang_enabled(): array {
	return MarMarLang\Languages::enabled();
}

function mm_lang_locale( string $lang ): string {
	return MarMarLang\Languages::locale( $lang );
}

/** Language of the current request. */
function mm_lang_current(): string {
	return MarMarLang\Router::current();
}

/** Same URL in another language. */
function mm_lang_url( string $url, string $lang ): string {
	return MarMarLang\Links::to_lang( $url, $lang );
}

/** Translation of one field, or null when empty/missing. $kind = 'post' | 'term'. */
function mm_tr_get( string $kind, int $id, string $field, string $lang ): ?string {
	return MarMarLang\Store::get( $kind, $id, $field, $lang );
}

/** True when the object has its main field (title / name) translated. */
function mm_tr_has( string $kind, int $id, string $lang ): bool {
	return MarMarLang\Store::has( $kind, $id, $lang );
}

foreach ( array( 'Store', 'Router', 'Links', 'Content' ) as $mod ) {
	call_user_func( array( 'MarMarLang\\' . $mod, 'boot' ) );
}
