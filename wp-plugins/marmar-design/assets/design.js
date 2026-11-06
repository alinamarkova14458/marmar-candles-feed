/* MarMar Design */
(function () {
	'use strict';
	// --- Termiņš: izgatavošana 5–7 kalendārās dienas + pakomāts 1–2 darba dienas (LV svētki) ---
	function easter(y) {
		var a = y % 19, b = Math.floor(y / 100), c = y % 100, d = Math.floor(b / 4), e = b % 4, f = Math.floor((b + 8) / 25),
			g = Math.floor((b - f + 1) / 3), h = (19 * a + b - d - g + 15) % 30, i = Math.floor(c / 4), k = c % 4,
			l = (32 + 2 * e + 2 * i - h - k) % 7, m = Math.floor((a + 11 * h + 22 * l) / 451), mo = Math.floor((h + l - 7 * m + 114) / 31),
			da = ((h + l - 7 * m + 114) % 31) + 1;
		return new Date(y, mo - 1, da);
	}
	function key(d) { return d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate(); }
	function holidays(y) {
		var s = {};
		[[1, 1], [5, 1], [5, 4], [6, 23], [6, 24], [11, 18], [12, 24], [12, 25], [12, 26], [12, 31]].forEach(function (md) { s[y + '-' + md[0] + '-' + md[1]] = 1; });
		var e = easter(y), gf = new Date(e), em = new Date(e);
		gf.setDate(e.getDate() - 2); em.setDate(e.getDate() + 1);
		s[key(gf)] = 1; s[key(em)] = 1;
		return s;
	}
	function addWork(d, n) {
		var h = Object.assign({}, holidays(d.getFullYear()), holidays(d.getFullYear() + 1));
		d = new Date(d);
		while (n > 0) { d.setDate(d.getDate() + 1); var w = d.getDay(); if (w !== 0 && w !== 6 && !h[key(d)]) n--; }
		return d;
	}
	var MON = ['janvārī', 'februārī', 'martā', 'aprīlī', 'maijā', 'jūnijā', 'jūlijā', 'augustā', 'septembrī', 'oktobrī', 'novembrī', 'decembrī'];
	var MS = ['janv.', 'febr.', 'marts', 'apr.', 'maijs', 'jūn.', 'jūl.', 'aug.', 'sept.', 'okt.', 'nov.', 'dec.'];
	function plus(n) { var d = new Date(); d.setHours(12, 0, 0, 0); d.setDate(d.getDate() + n); return d; }
	try {
		var from = addWork(plus(5), 1), to = addWork(plus(7), 2);
		var txt = from.getMonth() === to.getMonth()
			? '~' + from.getDate() + '.–' + to.getDate() + '. ' + MON[to.getMonth()]
			: '~' + from.getDate() + '. ' + MON[from.getMonth()] + ' – ' + to.getDate() + '. ' + MON[to.getMonth()];
		var short = '~' + from.getDate() + '.–' + to.getDate() + '. ' + MS[to.getMonth()];
		document.querySelectorAll('[data-mmd-eta-date]').forEach(function (el) { el.textContent = txt; });
		document.querySelectorAll('[data-mmd-eta-short]').forEach(function (el) { el.textContent = short; });
	} catch (e) { /* atstājam noklusēto tekstu */ }

	// --- Parādīšanās, ritinot (kā pašreizējā vietnē, bet arī Safari) ---
	try {
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (!reduce && 'IntersectionObserver' in window) {
			var sel = '[data-products] > .product, .entry-content .wp-block-image, .entry-content .wp-block-column, .entry-content .wp-block-gallery figure, .mmk-card, .wc-block-grid__product, .wp-block-post';
			var vh = window.innerHeight || 800;
			var items = [].slice.call(document.querySelectorAll(sel)).filter(function (el) {
				var r = el.getBoundingClientRect();
				return r.top > vh * 0.9 && !el.closest('[data-aos]'); // pirmo ekrānu neaiztiekam (ātrums, LCP)
			});
			var rio = new IntersectionObserver(function (es) {
				es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); rio.unobserve(e.target); } });
			}, { rootMargin: '0px 0px -8% 0px' });
			items.forEach(function (el) {
				var sib = el.parentElement ? [].indexOf.call(el.parentElement.children, el) : 0;
				el.style.setProperty('--mmd-d', ((sib % 4) * 90) + 'ms');
				el.classList.add('mmd-rv');
				rio.observe(el);
			});
		}
	} catch (e) { /* nav kritiski */ }

	// --- Lipīgā “Ielikt grozā” josla (telefonā) ---
	try {
		var bar = document.querySelector('[data-mmd-sticky]');
		var form = document.querySelector('form.cart');
		var real = form && form.querySelector('.single_add_to_cart_button');
		if (bar && form && real && 'IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (en) {
				var e = en[0];
				var past = !e.isIntersecting && e.boundingClientRect.top < 0;
				bar.hidden = !past;
				document.body.classList.toggle('mmd-has-sticky', past);
			});
			io.observe(real);
			bar.querySelector('[data-mmd-sticky-btn]').addEventListener('click', function () {
				var variable = form.classList.contains('variations_form');
				if (!variable && !real.disabled && !real.classList.contains('disabled')) { real.click(); return; }
				form.scrollIntoView({ behavior: 'smooth', block: 'center' });
			});
		}
	} catch (e) { /* nav kritiski */ }
})();
