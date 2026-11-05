<?php
// php-scoper: все зависимости получают префикс MarMarChat\Vendor, чтобы не конфликтовать
// с копиями Guzzle/PSR в других плагинах (UpdraftPlus, Site Kit и т.д.).
use Isolated\Symfony\Component\Finder\Finder;

return [
	'prefix'             => 'MarMarChat\\Vendor',
	'finders'            => [
		Finder::create()->files()->in( 'vendor' )
			->name( [ '*.php', 'LICENSE', 'composer.json', 'installed.json' ] )
			->notPath( '#/(tests?|Tests|examples|fixtures|scripts|docs|bin)/#' )
			->exclude( [ 'bin' ] ),
	],
	'exclude-namespaces' => [],
	'exclude-functions'  => [],
];
