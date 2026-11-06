/* MarMar Design — parādīšanās, ritinot (tiek ievietots lapas apakšā inline, lai sāktu agri) */
(function () {
	'use strict';
	// --- Parādīšanās, ritinot (sākuma stāvoklis — CSS, sk. html.mmd-js) ---
	try {
		var root = document.documentElement;
		if (window.mmdT) clearTimeout(window.mmdT); // JS strādā — rezerves “parādīt visu” vairs nevajag
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (reduce || !('IntersectionObserver' in window)) { root.classList.add('mmd-all'); }
		else {
			var vh = window.innerHeight || 800;
			var SEL = '[data-products] > .product, .entry-content .wp-block-image, .entry-content .wp-block-column, .mmk-card';
			var all = [].slice.call(document.querySelectorAll(SEL));
			var items = all.filter(function (el) { var par = el.parentElement && el.parentElement.closest(SEL); return !par; });
			// Iekšējie elementi parādās kopā ar ārējo bloku (citādi tos “nogriež” vecāka overflow:hidden).
			all.forEach(function (el) { if (items.indexOf(el) === -1) el.classList.add('is-in', 'mmd-done'); });
			var rio = new IntersectionObserver(function (es) {
				es.forEach(function (e) {
					if (!e.isIntersecting) return;
					var t = e.target; t.classList.add('is-in'); rio.unobserve(t);
					var d = parseInt(t.style.getPropertyValue('--mmd-d'), 10) || 0;
					setTimeout(function () { t.classList.add('mmd-done'); }, d + 950); // pēc tam — ātra reakcija uz peli
				});
			}, { rootMargin: '0px 0px -6% 0px' });
			// Drošība: ja kāds elements jau ir ekrānā vai virs tā, bet novērotājs to nepamanīja — parādām.
			var check = function () {
				var cur = document.querySelectorAll(SEL); // svaigi — ja kāds skripts elementu aizstājis
				for (var i = 0; i < cur.length; i++) {
					var el = cur[i];
					if (!el.classList.contains('is-in') && el.getBoundingClientRect().top < vh * 0.95) { el.classList.add('is-in'); setTimeout(function (t) { return function () { t.classList.add('mmd-done'); }; }(el), 1000); }
				}
			};
			var tick = false;
			window.addEventListener('scroll', function () { if (!tick) { tick = true; setTimeout(function () { tick = false; check(); }, 250); } }, { passive: true });
			window.addEventListener('load', function () { setTimeout(check, 1200); });
			items.forEach(function (el) {
				var sib = el.parentElement ? [].indexOf.call(el.parentElement.children, el) : 0;
				var first = el.getBoundingClientRect().top < vh;
				el.style.setProperty('--mmd-d', (first ? 60 + Math.min(sib, 5) * 110 : (sib % 4) * 110) + 'ms');
				rio.observe(el);
			});
		}
	} catch (e) { document.documentElement.classList.add('mmd-all'); }

})();
