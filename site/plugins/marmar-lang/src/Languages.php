<?php
namespace MarMarLang;

/** Language list, locales and the two plugin options. */
final class Languages {
	public const DEFAULT = 'lv';

	/** All languages the plugin knows => WordPress locale. */
	public const LOCALES = array(
		'lv' => 'lv',
		'en' => 'en_GB',
		'lt' => 'lt_LT',
		'et' => 'et',
	);

	/** Labels for the language switcher. */
	public const LABELS = array( 'lv' => 'LV', 'en' => 'EN', 'lt' => 'LT', 'et' => 'ET' );

	public const OPT_ENABLED = 'mm_lang_enabled';
	public const OPT_PUBLIC  = 'mm_lang_public';

	/** Enabled non-default languages (subset of LOCALES, without lv). */
	public static function enabled(): array {
		$opt = get_option( self::OPT_ENABLED, array( 'en' ) );
		$opt = is_array( $opt ) ? $opt : array( 'en' );
		return array_values( array_filter( $opt, fn( $l ) => $l !== self::DEFAULT && isset( self::LOCALES[ $l ] ) ) );
	}

	/** Default language first, then enabled ones. */
	public static function all(): array {
		return array_merge( array( self::DEFAULT ), self::enabled() );
	}

	public static function is_enabled( string $lang ): bool {
		return in_array( $lang, self::enabled(), true );
	}

	public static function is_public(): bool {
		return (bool) get_option( self::OPT_PUBLIC, false );
	}

	public static function locale( string $lang ): string {
		return self::LOCALES[ $lang ] ?? self::LOCALES[ self::DEFAULT ];
	}
}
