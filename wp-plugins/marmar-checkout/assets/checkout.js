/* MarMar Checkout — улучшения блочной корзины и оформления без пересборки блоков WooCommerce. */
(function () {
	'use strict';
	var C = window.MarMarCheckout;
	if (!C) return;
	// wp_localize_script передаёт числа строками.
	C.freeFrom = parseFloat(C.freeFrom) || 0;
	C.minOrder = parseFloat(C.minOrder) || 0;

	function el(tag, cls, html) { var n = document.createElement(tag); if (cls) n.className = cls; if (html != null) n.innerHTML = html; return n; }
	function money(v) { return (Number(v) || 0).toFixed(2).replace('.', ',') + ' €'; }
	function store() { return window.wp && wp.data && wp.data.select && wp.data.select('wc/store/cart'); }

	function cartNumbers() {
		var s = store(); if (!s) return null;
		var t = s.getCartTotals && s.getCartTotals();
		var d = s.getCartData && s.getCartData();
		if (!t || t.total_items === undefined) return null;
		var div = Math.pow(10, t.currency_minor_unit || 2);
		var fees = (d && d.fees) || [];
		return {
			items: (parseInt(t.total_items, 10) + parseInt(t.total_items_tax || 0, 10)) / div,
			express: fees.some(function (f) { return (f.name || '').indexOf(C.feeName) !== -1; })
		};
	}

	/* ---------- Полоска до бесплатной доставки ---------- */
	var bar = el('div', 'mmco-free', '<div class="mmco-free-text"></div><div class="mmco-free-track"><span></span></div>');
	function updateBar() {
		var n = cartNumbers(); if (!n) return;
		// Меньше минимального заказа — сначала показываем, сколько не хватает до него.
		if (C.minOrder && n.items > 0 && n.items < C.minOrder) {
			bar.querySelector('.mmco-free-text').textContent = C.i18n.minLeft.replace('%1$s', money(C.minOrder)).replace('%2$s', money(C.minOrder - n.items));
			bar.querySelector('.mmco-free-track span').style.width = Math.min(100, n.items / C.minOrder * 100) + '%';
			bar.classList.add('is-min'); bar.classList.remove('is-done');
			bar.setAttribute('role', 'alert');
			return;
		}
		bar.classList.remove('is-min'); bar.removeAttribute('role');
		var left = Math.max(0, C.freeFrom - n.items);
		bar.querySelector('.mmco-free-text').textContent = left > 0 ? C.i18n.left.replace('%s', money(left)) : C.i18n.free;
		bar.querySelector('.mmco-free-track span').style.width = Math.min(100, n.items / C.freeFrom * 100) + '%';
		bar.classList.toggle('is-done', left <= 0);
	}

	/* ---------- Срочный заказ (шорткод [marmar_express]) — переносим к итогу ---------- */
	var expressBox = null;
	var expressInput = document.getElementById('marmar_express_order');
	if (expressInput) {
		expressBox = expressInput.closest('div');
		expressBox.classList.add('mmco-express');
	}

	/* ---------- Оформление: срок получения и строка доверия ---------- */
	var eta = el('div', 'mmco-eta');
	var trust = el('div', 'mmco-trust');
	function updateEta() {
		var n = cartNumbers();
		var range = n && n.express ? C.estimateFast : C.estimate;
		eta.innerHTML = '<span aria-hidden="true">🚚</span> <span><strong>' + C.i18n.eta.replace('%s', range) + '</strong> <small>' + C.i18n.etaNote + '</small></span>';
	}
	// Срочный заказ на оформлении (в корзине он уже есть через шорткод).
	var fast = el('label', 'mmco-fast', '<input type="checkbox" class="mmco-fast-input"><span class="mmco-fast-text"><strong></strong><small></small></span>');
	var fastInput = fast.querySelector('input');
	var fastBusy = false;
	fastInput.addEventListener('change', function () {
		var api = window.wc && wc.blocksCheckout && wc.blocksCheckout.extensionCartUpdate;
		if (!api || fastBusy) return;
		fastBusy = true; fast.classList.add('is-busy');
		Promise.resolve(api({ namespace: 'marmar-express', data: { express: fastInput.checked } }))
			.catch(function () { fastInput.checked = !fastInput.checked; })
			.then(function () { fastBusy = false; fast.classList.remove('is-busy'); refresh(); });
	});
	function updateFast() {
		var n = cartNumbers();
		if (n && !fastBusy) fastInput.checked = n.express;
		fast.querySelector('strong').textContent = C.i18n.express;
		fast.querySelector('small').textContent = C.i18n.expressSub.replace('%s', C.estimateFast);
		fast.classList.toggle('is-on', fastInput.checked);
	}

	var parts = [];
	if (C.rating) parts.push('<span>★ ' + C.rating.score + ' Google (' + C.rating.count + ')</span>');
	parts.push('<span>' + C.i18n.handmade + '</span>', '<span>' + C.i18n.invoice + '</span>');
	trust.innerHTML = parts.join('<span class="mmco-dot" aria-hidden="true">·</span>');

	/* ---------- Размещение (React-блоки перерисовываются — проверяем при каждом изменении DOM) ---------- */
	function place() {
		if (C.page === 'cart') {
			var totals = document.querySelector('.wp-block-woocommerce-cart-totals-block');
			var submit = document.querySelector('.wc-block-cart__submit, .wp-block-woocommerce-proceed-to-checkout-block');
			if (totals && submit && submit.parentNode) {
				// React при перерисовке может оставить копии — оставляем только наши живые узлы.
				document.querySelectorAll('.mmco-free').forEach(function (n) { if (n !== bar) n.remove(); });
				document.querySelectorAll('.mmco-express').forEach(function (n) { if (n !== expressBox) n.remove(); });
				if (totals.firstElementChild !== bar) totals.insertBefore(bar, totals.firstChild);
				if (expressBox && expressBox.nextElementSibling !== submit) submit.parentNode.insertBefore(expressBox, submit);
			}
		} else {
			// Поле комментария сразу открыто: один раз ставим скрытую галочку WooCommerce.
			var note = document.querySelector('.wc-block-checkout__add-note');
			if (note && !note.querySelector('textarea') && !note.dataset.mmcoOpened) {
				var cb = note.querySelector('input[type="checkbox"]');
				if (cb && !cb.checked) { note.dataset.mmcoOpened = '1'; cb.click(); }
			}
			if (note) note.classList.add('mmco-note-open');
			var actions = document.querySelector('.wc-block-checkout__actions');
			if (actions) {
				document.querySelectorAll('.mmco-eta').forEach(function (n) { if (n !== eta) n.remove(); });
				document.querySelectorAll('.mmco-trust').forEach(function (n) { if (n !== trust) n.remove(); });
				document.querySelectorAll('.mmco-fast').forEach(function (n) { if (n !== fast) n.remove(); });
				if (actions.previousElementSibling !== fast) actions.parentNode.insertBefore(fast, actions);
				if (fast.previousElementSibling !== eta) actions.parentNode.insertBefore(eta, fast);
				if (actions.nextElementSibling !== trust) actions.parentNode.insertBefore(trust, actions.nextSibling);
			}
		}
	}

	var scheduled = false;
	new MutationObserver(function () {
		if (scheduled) return; scheduled = true;
		requestAnimationFrame(function () { scheduled = false; safe(place); refresh(); });
	}).observe(document.body, { childList: true, subtree: true });

	// Наш код никогда не должен ронять корзину WooCommerce: любые ошибки гасим здесь.
	function safe(fn) { try { fn(); } catch (err) { if (window.console) console.warn('MarMar Checkout:', err); } }
	function refresh() { safe(updateBar); safe(updateEta); if (C.page === 'checkout') safe(updateFast); }
	if (window.wp && wp.data && wp.data.subscribe) wp.data.subscribe(refresh);
	safe(place); refresh();
})();
