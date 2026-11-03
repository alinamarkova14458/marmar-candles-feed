<?php
/**
 * MarMar UX — пачки 1–2 (03.11.2026). Заменяет прежний сниппет «MarMar UX — пачка 1».
 *
 * Куда: WPCode → Add Snippet → "Add Your Custom Code" → тип "PHP Snippet",
 *       Location: "Run Everywhere" → Active → Save.
 * Откат: выключить сниппет в WPCode (сайт сразу вернётся к прежнему виду).
 *
 * Что делает:
 *  1. Не показывает ошибку минимального заказа (5 €), когда корзина пустая.
 *  2. Блок «сроки · доставка · минимум» под кнопкой покупки на странице товара.
 *  3. Подсказка «Vispirms izvēlies…» у неактивной кнопки + подписи под свотчами.
 *  4. Прячет «SKU: nav pieejams».
 *  5. Переводит баннер cookies на латышский и делает его компактным на телефоне.
 *  6. Уменьшает кнопку AI-чата на телефоне и прячет её в корзине и оформлении заказа.
 *  7. Короткие названия на карточках каталога (сами названия товаров не меняются).
 *  8. Компактные карточки на телефоне, компактное окно cookies.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* 1. Пустая корзина — без красной ошибки «Minimālā pasūtījuma summa…» */
add_action( 'woocommerce_check_cart_items', function () {
	if ( function_exists( 'WC' ) && WC()->cart && WC()->cart->is_empty() ) {
		wc_clear_notices();
	}
}, 999 );

/* Категории, где текст про изготовление и доставку не подходит. */
function marmar_ux_info_mode( $product ) {
	$skip  = array( 'materiali-svecu-izgatavosanai', 'svecu-veidnes', 'davanu-kartes' );
	$slugs = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'slugs' ) );
	if ( is_wp_error( $slugs ) ) {
		return 'default';
	}
	foreach ( $slugs as $s ) {
		if ( false !== strpos( rawurldecode( $s ), 'apmacibas' ) || in_array( $s, $skip, true ) ) {
			return 'none';
		}
	}
	if ( in_array( 'sveces-pieejamas-uzreiz', $slugs, true ) ) {
		return 'ready';
	}
	return 'default';
}

/* 2. Блок под кнопкой покупки */
add_action( 'woocommerce_after_add_to_cart_form', function () {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$mode = marmar_ux_info_mode( $product );
	if ( 'none' === $mode ) {
		return;
	}
	$make = ( 'ready' === $mode )
		? '<b>Jau gatava</b> — izsūtu 1–2 darba dienās pēc apmaksas'
		: '<b>Izgatavoju ar rokām 5–7 darba dienās</b><br>Steidzami? +5 € — gatavs 2 darba dienās';
	?>
	<div class="mm-info" role="note">
		<div><span class="mm-ic" aria-hidden="true">🕯️</span><span><?php echo wp_kses_post( $make ); ?></span></div>
		<div><span class="mm-ic" aria-hidden="true">📦</span><span><b>Piegāde visā Latvijā</b> no 2 € — Omniva, DPD, Venipak, Unisend<br>Bez maksas no 50 € · Saņemšana Rīgā, Iļģuciemā</span></div>
		<div><span class="mm-ic" aria-hidden="true">🧺</span><span>Minimālais pasūtījums — <b>5 €</b></span></div>
	</div>
	<?php
} );

/* 3a. Подсказка у неактивной кнопки (только вариативные товары) */
add_action( 'woocommerce_before_add_to_cart_button', function () {
	global $product;
	if ( $product instanceof WC_Product && $product->is_type( 'variable' ) ) {
		echo '<div class="mm-hint" aria-live="polite">👆 <span>Vispirms izvēlies variantu — tad varēsi pievienot grozam</span></div>';
	}
} );

/* 3b. JS: прячем подсказку после выбора варианта, подписи под свотчами */
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	?>
	<script>
	(function ($) {
		if (!$) { return; }
		$(function () {
			var $form = $('form.variations_form');
			if (!$form.length) { return; }
			// Подсказка: название атрибутов из подписей формы («krāsu», «dizainu»…)
			var names = $form.find('.variations th.label label, .variations .label label').map(function () {
				return $(this).text().replace(/^\s*Izvēlieties\s+/i, '').replace(/[:\s]+$/, '').toLowerCase();
			}).get().filter(Boolean);
			if (names.length) {
				var txt = names.length > 1 ? names.slice(0, -1).join(', ') + ' un ' + names.slice(-1) : names[0];
				$form.find('.mm-hint span').html('Vispirms izvēlies <b>' + $('<i>').text(txt).html() + '</b> — tad varēsi pievienot grozam');
			}
			$form.on('show_variation found_variation', function () { $form.find('.mm-hint').hide(); });
			$form.on('hide_variation reset_data', function () { $form.find('.mm-hint').show(); });
			// Подписи под свотчами из title
			$form.find('li.variable-item').each(function () {
				var $li = $(this);
				if ($li.find('.mm-lbl').length || $li.hasClass('button-variable-item')) { return; }
				var t = ($li.attr('title') || $li.data('title') || '').toString().trim();
				if (!t) { return; }
				t = t.charAt(0).toUpperCase() + t.slice(1).toLowerCase();
				$('<span class="mm-lbl" aria-hidden="true"></span>').text(t).appendTo($li);
			});
		});
	})(window.jQuery);
	</script>
	<?php
}, 99 );

/* 7. Короткие названия на карточках (каталог, главная, похожие товары).
      Название товара в базе, на странице товара, в корзине, в фиде и в Google не меняется. */
function marmar_short_name( $name ) {
	$name = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ) ) );
	$parts = array_map( 'trim', preg_split( '/\s*\|\s*/u', $name ) );
	$main  = rtrim( array_shift( $parts ), ', ' );
	$keep  = array();
	foreach ( $parts as $p ) {
		$p = trim( $p, ', ' );
		if ( '' === $p ) { continue; }
		// SEO-хвосты без новой информации
		if ( preg_match( '/^(dzimšanas dienas (sveces|svecītes)( tortei| tortēm)?|(dekoratīvās|romantiskas|tievās) (kūkas |kūku )?(sveces|svecītes)( tortei| tortēm)?|sveces tortei|dzimšanas dienas tortei)$/iu', $p ) ) { continue; }
		// ВЕРХНИЙ РЕГИСТР (цвет) → обычный
		if ( mb_strtoupper( $p, 'UTF-8' ) === $p && preg_match( '/\p{L}/u', $p ) ) {
			$p = mb_strtoupper( mb_substr( $p, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_strtolower( mb_substr( $p, 1, null, 'UTF-8' ), 'UTF-8' );
		}
		$keep[] = $p;
	}
	// лишние слова в основной части
	$main = preg_replace( '/\s+(tortēm un kūkām|kūkām un tortēm)\b/iu', '', $main );
	$main = preg_replace( '/\s+no dabīgā vaska\b/iu', '', $main );
	$main = preg_replace( '/\s+[–-]\s+(tievās\s+)?dzimšanas dienas (sveces|svecītes)( tortei| tortēm)?$/iu', '', $main );
	$main = preg_replace( '/^Dabīgā vaska\s+/iu', '', $main );
	$main = preg_replace( '/,\s*$/u', '', trim( $main ) );
	$main = mb_strtoupper( mb_substr( $main, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $main, 1, null, 'UTF-8' );
	$out = $main;
	foreach ( $keep as $k ) {
		if ( mb_strlen( $out . ' · ' . $k, 'UTF-8' ) > 70 ) { break; }
		$out .= ' · ' . $k;
	}
	return $out;
}

add_filter( 'the_title', function ( $title, $id = 0 ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() || ! $id || 'product' !== get_post_type( $id ) ) {
		return $title;
	}
	if ( function_exists( 'is_product' ) && is_product() && (int) $id === (int) get_queried_object_id() ) {
		return $title; // сам товар на своей странице — полное название
	}
	if ( doing_action( 'wp_head' ) || doing_filter( 'document_title_parts' ) || doing_filter( 'wp_title' ) ) {
		return $title;
	}
	return esc_html( marmar_short_name( $title ) );
}, 20, 2 );

/* 5a. Баннер cookies (Blocksy) по-латышски — работает, если текст в теме не менялся вручную */
add_filter( 'gettext', function ( $translation, $text, $domain ) {
	if ( 'blocksy-companion' !== $domain && 'blocksy' !== $domain ) {
		return $translation;
	}
	$map = array(
		'We use cookies to ensure that we give you the best experience on our website.' => 'Es izmantoju sīkdatnes, lai vietne darbotos ērti un tu vieglāk atrastu savu sveci.',
		'Accept'  => 'Piekrītu',
		'Decline' => 'Atteikties',
	);
	return isset( $map[ $text ] ) ? $map[ $text ] : $translation;
}, 20, 3 );

/* Стили для всех пунктов */
add_action( 'wp_head', function () {
	?>
	<style id="marmar-ux-batch1">
	/* 2. Блок «сроки · доставка · минимум» */
	.mm-info{margin:16px 0 8px;border:1px solid #efd9dc;background:#fff;border-radius:12px;padding:10px 14px}
	.mm-info>div{display:flex;gap:10px;align-items:flex-start;font-size:14px;line-height:1.4;color:#3a3134;padding:7px 0}
	.mm-info>div+div{border-top:1px solid #f5e8ea}
	.mm-info b{color:#7a4a5a}
	.mm-ic{font-size:18px;line-height:1.1;width:22px;flex:none;text-align:center}
	/* 3. Подсказка и подписи свотчей */
	.mm-hint{font-size:14px;line-height:1.35;color:#8a4b5c;background:#fff;border:1px dashed #d98a95;border-radius:8px;padding:8px 12px;margin:0 0 10px;display:flex;gap:8px;align-items:center;width:100%;box-sizing:border-box}
	.woo-variation-items-wrapper ul.variable-items-wrapper{align-items:flex-start;column-gap:34px!important;row-gap:2px!important;padding-left:18px!important}
	body .woo-variation-items-wrapper ul.variable-items-wrapper li.variable-item{position:relative;overflow:visible!important;margin-bottom:34px!important}
	.woo-variation-items-wrapper li.variable-item .mm-lbl{white-space:normal;position:absolute;top:calc(100% + 4px);left:50%;transform:translateX(-50%);width:68px;font-size:11px;line-height:1.2;text-align:center;color:#6b5a5e;pointer-events:none}
	/* 4. SKU */
	.product_meta .sku_wrapper{display:none!important}
	/* 5b. Cookies — компактная полоска на телефоне */
	@media (max-width:689px){
		.cookie-notification{padding:10px 14px!important}
		.cookie-notification .ct-cookies-content{font-size:13px!important;line-height:1.35!important;margin-bottom:8px!important}
		.cookie-notification .ct-button{min-height:36px!important;padding:6px 16px!important;font-size:13px!important}
		.cookie-notification .container{gap:8px!important}
	}
	/* 6. Кнопка AI-чата */
	@media (max-width:689px){
		.marmar-ai-launch{transform:scale(.78);transform-origin:bottom right}
	}
	.woocommerce-cart .marmar-ai-root,.woocommerce-checkout .marmar-ai-root{display:none!important}
	/* 7–8. Карточки каталога на телефоне */
	@media (max-width:689px){
		ul.products li.product .woocommerce-loop-product__title{font-size:14px!important;line-height:1.28!important}
		ul.products li.product .button{padding:8px 10px!important;min-height:0!important;font-size:12px!important}
	}
	/* 8. Cookies — одна строка: текст слева, кнопки справа */
	@media (max-width:689px){
		.cookie-notification .container{padding:6px 0!important;flex-direction:row!important;align-items:center!important;gap:10px!important}
		.cookie-notification .ct-cookies-content{flex:1;text-align:left!important;margin:0!important;font-size:12px!important}
		.cookie-notification .ct-button-group{flex:none;flex-direction:column!important;gap:6px!important}
		.cookie-notification .ct-button{min-height:30px!important;padding:4px 12px!important;font-size:12px!important}
	}
	</style>
	<?php
} );
