<?php
// Делает идентификаторы Composer autoload "files" уникальными. Иначе, если другой плагин
// подключит те же пакеты раньше, Composer посчитает наши файлы уже загруженными и пропустит их.
foreach ( array( 'autoload_files.php', 'autoload_static.php' ) as $file ) {
	$path = $argv[1] . '/vendor/composer/' . $file;
	if ( ! is_file( $path ) ) {
		continue;
	}
	$code = preg_replace_callback(
		"/'([0-9a-f]{32})' => /",
		static function ( $m ) {
			return "'" . md5( $m[1] . 'marmarchat' ) . "' => ";
		},
		file_get_contents( $path )
	);
	file_put_contents( $path, $code );
}
