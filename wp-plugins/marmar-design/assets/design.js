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
	// Atvaļinājums: šīs dienas neskaitās izgatavošanā (pasūtījumus pieņemam, izgatavojam no nākamās dienas).
	var VAC = [[new Date(2026, 10, 26, 12), new Date(2026, 11, 3, 12)]];
	function onVac(d) { return VAC.some(function (v) { return d >= v[0] && d <= v[1]; }); }
	function plus(n) { var d = new Date(); d.setHours(12, 0, 0, 0); while (n > 0) { d.setDate(d.getDate() + 1); if (!onVac(d)) n--; } return d; }
	try {
		var now = new Date(), vb = document.querySelector('[data-mmd-vac]');
		if (vb) { var a = new Date(2026, 10, 19), z = new Date(2026, 11, 3, 23, 59); vb.hidden = !(now >= a && now <= z); }
	} catch (e) { /* nav kritiski */ }
	try {
		var from = addWork(plus(5), 1), to = addWork(plus(7), 2);
		var txt = from.getMonth() === to.getMonth()
			? '~' + from.getDate() + '.–' + to.getDate() + '. ' + MON[to.getMonth()]
			: '~' + from.getDate() + '. ' + MON[from.getMonth()] + ' – ' + to.getDate() + '. ' + MON[to.getMonth()];
		var short = '~' + from.getDate() + '.–' + to.getDate() + '. ' + MS[to.getMonth()];
		document.querySelectorAll('[data-mmd-eta-date]').forEach(function (el) { el.textContent = txt; });
		document.querySelectorAll('[data-mmd-eta-short]').forEach(function (el) { el.textContent = short; });
	} catch (e) { /* atstājam noklusēto tekstu */ }

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

	// --- Produkta cilnes → izvēršamas rindas (akordeons) ---
	try {
		var tabs = document.querySelector('.woocommerce-tabs');
		if (tabs && tabs.querySelector('ul.tabs')) {
			var wrap = document.createElement('div');
			wrap.className = 'mmd-acc-wrap';
			[].slice.call(tabs.querySelectorAll('ul.tabs li a')).forEach(function (a, i) {
				var id = (a.getAttribute('href') || '').replace(/^#/, '');
				var panel = id && document.getElementById(id);
				if (!panel) return;
				var d = document.createElement('details');
				d.className = 'mmd-acc';
				d.id = 'mmd-' + id;
				var sm = document.createElement('summary');
				sm.textContent = a.textContent.trim();
				d.appendChild(sm);
				panel.style.display = '';
				panel.removeAttribute('hidden');
				d.appendChild(panel);
				wrap.appendChild(d);
			});
			if (wrap.children.length) {
				tabs.appendChild(wrap);
				tabs.classList.add('mmd-acc-on');
				// Woo cilņu skripts slēpj paneļus — atceļam to arī vēlāk
				setTimeout(function () { wrap.querySelectorAll('.wc-tab').forEach(function (pn) { pn.style.display = ''; }); }, 600);
				var openHash = function () {
					var h = (location.hash || '').replace('#', '');
					if (/review/.test(h)) { var r = document.getElementById('mmd-tab-reviews'); if (r) { r.open = true; r.scrollIntoView({ behavior: 'smooth', block: 'start' }); } }
				};
				window.addEventListener('hashchange', openHash); openHash();
			}
		}
	} catch (e) { /* nav kritiski */ }

	// --- Liesmiņa šūpojas, kamēr lapa tiek ritināta ---
	try {
		var sT = 0, root2 = document.documentElement;
		window.addEventListener('scroll', function () {
			if (!root2.classList.contains('mmd-scrolling')) root2.classList.add('mmd-scrolling');
			clearTimeout(sT);
			sT = setTimeout(function () { root2.classList.remove('mmd-scrolling'); }, 450);
		}, { passive: true });
	} catch (e) { /* nav kritiski */ }
})();
