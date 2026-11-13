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
	var sum = el('div', 'mmco-sum');
	function updateSum() {
		var st = store(); var t = st && st.getCartTotals && st.getCartTotals();
		if (!t || t.total_price === undefined) return;
		var n = cartNumbers();
		var total = parseInt(t.total_price, 10) / Math.pow(10, t.currency_minor_unit || 2);
		var range = n && n.express ? C.estimateFast : C.estimate;
		var html = C.i18n.total + ' <strong>' + money(total) + '</strong><span class="mmco-dot" aria-hidden="true">·</span>' + C.i18n.eta.replace('%s', range);
		if (sum.innerHTML !== html) sum.innerHTML = html;
	}
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
			if (note) {
				note.classList.add('mmco-note-open');
				var lbl = note.querySelector('.wc-block-components-checkbox__label');
				if (lbl && lbl.textContent !== C.i18n.noteLabel) lbl.textContent = C.i18n.noteLabel;
				var ta = note.querySelector('textarea');
				if (ta && ta.placeholder !== C.i18n.noteHint) ta.placeholder = C.i18n.noteHint;
			}
			var actions = document.querySelector('.wc-block-checkout__actions');
			if (actions) {
				document.querySelectorAll('.mmco-eta').forEach(function (n) { if (n !== eta) n.remove(); });
				document.querySelectorAll('.mmco-trust').forEach(function (n) { if (n !== trust) n.remove(); });
				document.querySelectorAll('.mmco-sum').forEach(function (n) { if (n !== sum) n.remove(); });
				document.querySelectorAll('.mmco-fast').forEach(function (n) { if (n !== fast) n.remove(); });
				if (actions.previousElementSibling !== fast) actions.parentNode.insertBefore(fast, actions);
				if (fast.previousElementSibling !== eta) actions.parentNode.insertBefore(eta, fast);
				if (actions.nextElementSibling !== sum) actions.parentNode.insertBefore(sum, actions.nextSibling);
				if (sum.nextElementSibling !== trust) actions.parentNode.insertBefore(trust, sum.nextSibling);
			}
			safe(placeWho);
			safe(translateMisc);
		}
	}

	/* ---------- Privātpersona / Uzņēmums ---------- */
	var BIZ = ['company', 'marmar/reg-nr', 'marmar/pvn-nr', 'marmar/jur-adrese'];
	var bizOn = null;
	var who = el('div', 'mmco-who', '<label><input type="radio" name="mmco-who" value="p"> Privātpersona</label>' +
		'<label><input type="radio" name="mmco-who" value="b"> Uzņēmums</label>');
	function bizWraps(form) {
		return BIZ.map(function (k) {
			var i = form.querySelector('[id$="-' + k.replace('/', '\\/') + '"]') || form.querySelector('[id$="-' + k.replace('/', '-') + '"]');
			if (!i) return null;
			while (i.parentNode && i.parentNode !== form) i = i.parentNode; // tiešais formas bērns — tad strādā CSS «order»
			return i.parentNode === form ? i : null;
		}).filter(Boolean);
	}
	function placeWho() {
		var form = document.querySelector('#shipping .wc-block-components-address-form, .wc-block-checkout__shipping-fields .wc-block-components-address-form');
		if (!form) return;
		var wraps = bizWraps(form);
		if (!wraps.length) return;
		wraps.forEach(function (w) { w.classList.add('mmco-biz'); });
		document.querySelectorAll('.mmco-who').forEach(function (n) { if (n !== who) n.remove(); });
		if (who.parentNode !== form) form.appendChild(who);
		if (bizOn === null) {
			var s = store(), a = s && s.getCustomerData && s.getCustomerData().shippingAddress;
			bizOn = !!(a && a.company);
		}
		form.classList.toggle('mmco-is-biz', bizOn);
		who.querySelector('input[value="' + (bizOn ? 'b' : 'p') + '"]').checked = true;
	}
	who.addEventListener('change', function (e) {
		bizOn = e.target.value === 'b';
		var form = who.parentNode; if (form) form.classList.toggle('mmco-is-biz', bizOn);
		if (bizOn) { var f = form && form.querySelector('.mmco-biz input'); if (f) setTimeout(function () { f.focus(); }, 50); return; }
		// Privātpersona — notīram uzņēmuma laukus, lai tie nenonāk pasūtījumā.
		var d = window.wp && wp.data && wp.data.dispatch('wc/store/cart');
		if (!d) return;
		var empty = {}; BIZ.forEach(function (k) { empty[k] = ''; });
		safe(function () { d.setShippingAddress(empty); });
		safe(function () { d.setBillingAddress(empty); });
	});

	function translateMisc() {
		document.querySelectorAll('.mp-please-select-location').forEach(function (n) {
			if (/pickup location/i.test(n.textContent)) n.textContent = 'Izvēlieties pakomātu';
		});
		// Paysera juridiskais teksts — īsāk, saite uz noteikumiem paliek.
		var c = document.querySelector('.paysera-customer-consent p');
		if (c && !c.dataset.mmco) {
			var a = c.querySelector('a');
			c.dataset.mmco = '1';
			c.innerHTML = 'Maksājumu nodrošina Paysera. Turpinot jūs piekrītat ' + (a ? '<a href="' + a.getAttribute('href') + '" target="_blank" rel="noopener">Paysera noteikumiem</a>' : 'Paysera noteikumiem') + '.';
		}
	}

	var scheduled = false;
	new MutationObserver(function () {
		if (scheduled) return; scheduled = true;
		requestAnimationFrame(function () { scheduled = false; safe(place); refresh(); });
	}).observe(document.body, { childList: true, subtree: true });

	// Наш код никогда не должен ронять корзину WooCommerce: любые ошибки гасим здесь.
	function safe(fn) { try { fn(); } catch (err) { if (window.console) console.warn('MarMar Checkout:', err); } }
	function refresh() { safe(updateBar); safe(updateEta); if (C.page === 'checkout') { safe(updateFast); safe(updateSum); } }
	if (window.wp && wp.data && wp.data.subscribe) wp.data.subscribe(refresh);
	safe(place); refresh();
})();
