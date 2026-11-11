/* MarMar Analytics — события воронки GA4 через тег Site Kit. */
(function () {
	'use strict';
	var D = window.MarMarGA;
	if (!D || !D.view) return;

	function send(name, params) {
		var sk = window._googlesitekit;
		if (sk && typeof sk.gtagEvent === 'function') { sk.gtagEvent(name, params); return; }
		if (typeof window.gtag === 'function') window.gtag('event', name, params);
	}

	// Ждём, пока Site Kit подключит gtag (скрипты грузятся отложенно).
	function ready(fn, tries) {
		tries = tries || 0;
		if ((window._googlesitekit && window._googlesitekit.gtagEvent) || typeof window.gtag === 'function' || tries > 40) fn();
		else setTimeout(function () { ready(fn, tries + 1); }, 150);
	}

	ready(function () {
		if (D.page === 'product') send('view_item', D.view);
		if (D.page === 'cart') send('view_cart', D.view);
		if (D.page === 'checkout') send('begin_checkout', D.view);
	});

	if (D.page !== 'product' || !window.jQuery) return;
	var $ = window.jQuery;
	var variation = null;

	// Вариативный товар: запоминаем выбранный вариант и его цену.
	$(document.body).on('found_variation', 'form.variations_form', function (e, v) { variation = v || null; });
	$(document.body).on('reset_data', 'form.variations_form', function () { variation = null; });

	// Кнопка на странице товара (AJAX Blocksy). Кнопки каталога с data-product_id уже считает Site Kit.
	$(document.body).on('added_to_cart', function (e, fragments, hash, $button) {
		if (!$button || !$button.jquery || $button.data('product_id')) return;
		if (!$button.hasClass('single_add_to_cart_button')) return;
		var form = $button.closest('form.cart');
		var qty = parseInt(form.find('input.qty').val(), 10) || 1;
		var item = $.extend({}, D.item, { quantity: qty });
		if (variation) {
			item.price = Math.round(parseFloat(variation.display_price) * 100) / 100;
			var attrs = [];
			$.each(variation.attributes || {}, function (k, v) { if (v) attrs.push(v); });
			if (attrs.length) item.item_variant = attrs.join(', ');
		}
		send('add_to_cart', { currency: D.currency, value: Math.round(item.price * qty * 100) / 100, items: [item] });
	});
})();
