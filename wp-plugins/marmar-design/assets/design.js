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

/* «Tukšie» klikšķi (Clarity, 13.11.2026): foto, uz kurām cilvēki spiež, bet nekas nenotiek. */
(function () {
	'use strict';
	document.addEventListener('click', function (e) {
		var t = e.target;
		if (!t || !t.closest) { return; }
		// Preces galerija: spiediens uz foto atver palielinājumu (tāpat kā lupas ikona).
		var fig = t.closest('.woocommerce-product-gallery .flexy-item, .woocommerce-product-gallery .ct-media-container');
		// Sīktēli (.flexy-pills) tikai pārslēdz galveno foto — tos neaiztiekam, citādi atvērās cits foto.
		if (fig && !t.closest('a,button') && !t.closest('.flexy-pills')) {
			var trig = document.querySelector('.woocommerce-product-gallery__trigger');
			if (trig) { e.preventDefault(); trig.click(); }
			return;
		}
		// Sākumlapa: foto banerī un Ziemassvētku blokā ved tur pat, kur sadaļas poga.
		// Baneris (bumbu foto) ved uzreiz uz bumbu preci — hits; poga «Ziemassvētku kolekcija» paliek uz kategoriju.
		var sec = t.closest('.mmh-hero, .mmh-xmas');
		if (sec && !t.closest('a,button,.mmh-hero-text,.mmh-xmas-text') && t.closest('picture,img,figure')) {
			var a = sec.classList.contains('mmh-hero') ? { href: '/lv/product/ziemassvetku-bumbas-formas-svece/' } : sec.querySelector('a[href]');
			if (a) { window.location.href = a.href; }
		}
	});
})();

/* Augšējā josla telefonā: teksti pa vienam (16.11.2026). */
(function () {
	'use strict';
	try {
		var bar = document.querySelector('.mmd-topbar');
		if (!bar || !window.matchMedia('(max-width: 689px)').matches) { return; }
		var items = [].filter.call(bar.children, function (s) { return !s.classList.contains('mmd-sep') && s.textContent.trim(); });
		if (items.length < 2) { return; }
		var i = 0;
		bar.classList.add('mmd-rot');
		items[0].classList.add('mmd-on');
		setInterval(function () {
			items[i].classList.remove('mmd-on');
			i = (i + 1) % items.length;
			items[i].classList.add('mmd-on');
		}, 4000);
	} catch (e) { /* nav kritiski */ }
})();

/* Pievienots grozam — paziņojums apakšā (16.11.2026): telefonā groza ikona augšā nav redzama, kad lapa noritināta. */
(function () {
	'use strict';
	try {
		var box, timer;
		function show() {
			if (!box) {
				box = document.createElement('div');
				box.className = 'mmd-added';
				box.setAttribute('role', 'status');
				box.innerHTML = '<span class="mmd-added-t">✓ Pievienots grozam</span><a class="mmd-added-b" href="/lv/cart/">Skatīt grozu →</a><button type="button" class="mmd-added-x" aria-label="Aizvērt">×</button>';
				document.body.appendChild(box);
				box.querySelector('.mmd-added-x').addEventListener('click', function () { box.classList.remove('is-on'); });
			}
			box.classList.add('is-on');
			clearTimeout(timer);
			timer = setTimeout(function () { box.classList.remove('is-on'); }, 6000);
		}
		if (window.jQuery) {
			jQuery(document.body).on('added_to_cart', show);
		}
		document.body.addEventListener('wc-blocks_added_to_cart', show);
	} catch (e) { /* nav kritiski */ }
})();

/* Telefonā: maza galvene ar izvēlni, meklēšanu un grozu, kad lapa noritināta (16.11.2026). */
(function () {
	'use strict';
	try {
		if (!window.matchMedia('(max-width: 999px)').matches) { return; }
		if (document.body.classList.contains('woocommerce-checkout')) { return; }
		var head = document.querySelector('header#header');
		var mob = head && head.querySelector('[data-device="mobile"]');
		if (!mob) { return; }
		var menuBtn = mob.querySelector('[data-toggle-panel="#offcanvas"]');
		var searchBtn = mob.querySelector('[data-toggle-panel="#search-modal"]');
		var logo = mob.querySelector('.site-logo-container img');
		var bar = document.createElement('div');
		bar.className = 'mmd-mini';
		bar.setAttribute('aria-hidden', 'true');
		bar.innerHTML =
			'<button type="button" class="mmd-mini-btn mmd-mini-menu" aria-label="Izvēlne" tabindex="-1"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" fill="none"/></svg></button>' +
			'<a class="mmd-mini-logo" href="/lv/" tabindex="-1">' + (logo ? '<img src="' + (logo.currentSrc || logo.src) + '" alt="MarMar CANDLES">' : 'MARMAR CANDLES') + '</a>' +
			'<button type="button" class="mmd-mini-btn mmd-mini-search" aria-label="Meklēt" tabindex="-1"><svg viewBox="0 0 24 24" width="21" height="21"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8" fill="none"/><path d="M20 20l-4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>' +
			'<a class="mmd-mini-btn mmd-mini-cart" href="/lv/cart/" aria-label="Grozs" tabindex="-1"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M5 8h14l-1.2 11.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8z M9 8V6a3 3 0 0 1 6 0v2" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" fill="none"/></svg><span class="mmd-mini-count"></span></a>';
		document.body.appendChild(bar);
		bar.querySelector('.mmd-mini-menu').addEventListener('click', function () { if (menuBtn) { menuBtn.click(); } });
		bar.querySelector('.mmd-mini-search').addEventListener('click', function () { if (searchBtn) { searchBtn.click(); } });
		var countEl = bar.querySelector('.mmd-mini-count');
		function count() {
			var src = document.querySelector('header#header [data-device="mobile"] .ct-dynamic-count-cart') || document.querySelector('.ct-dynamic-count-cart');
			var n = src ? parseInt(src.getAttribute('data-count') || src.textContent, 10) || 0 : 0;
			countEl.textContent = n > 0 ? String(n) : '';
			countEl.style.display = n > 0 ? '' : 'none';
		}
		var shown = false;
		function onScroll() {
			var on = head.getBoundingClientRect().bottom < 0;
			if (on !== shown) {
				shown = on;
				bar.classList.toggle('is-on', on);
				bar.setAttribute('aria-hidden', on ? 'false' : 'true');
				bar.querySelectorAll('a,button').forEach(function (el) { el.tabIndex = on ? 0 : -1; });
				if (on) { count(); }
			}
		}
		window.addEventListener('scroll', onScroll, { passive: true });
		if (window.jQuery) { jQuery(document.body).on('added_to_cart removed_from_cart wc_fragments_refreshed wc_fragments_loaded', function () { setTimeout(count, 300); }); }
		count(); onScroll();
	} catch (e) { /* nav kritiski */ }
})();
