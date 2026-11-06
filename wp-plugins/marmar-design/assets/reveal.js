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
			var items = [].slice.call(document.querySelectorAll('[data-products] > .product, .entry-content .wp-block-image, .entry-content .wp-block-column, .mmk-card'));
			var rio = new IntersectionObserver(function (es) {
				es.forEach(function (e) {
					if (!e.isIntersecting) return;
					var t = e.target; t.classList.add('is-in'); rio.unobserve(t);
					var d = parseInt(t.style.getPropertyValue('--mmd-d'), 10) || 0;
					setTimeout(function () { t.classList.add('mmd-done'); }, d + 950); // pēc tam — ātra reakcija uz peli
				});
			}, { rootMargin: '0px 0px -6% 0px' });
			items.forEach(function (el) {
				var sib = el.parentElement ? [].indexOf.call(el.parentElement.children, el) : 0;
				var first = el.getBoundingClientRect().top < vh;
				el.style.setProperty('--mmd-d', (first ? 60 + Math.min(sib, 5) * 110 : (sib % 4) * 110) + 'ms');
				rio.observe(el);
			});
		}
	} catch (e) { document.documentElement.classList.add('mmd-all'); }

})();
