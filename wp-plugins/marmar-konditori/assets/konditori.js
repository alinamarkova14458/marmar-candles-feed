/* MarMar Konditori — filtri bez pārlādes */
(function () {
	document.querySelectorAll('[data-mmk]').forEach(function (root) {
		var q = root.querySelector('[data-mmk-q]');
		var cards = [].slice.call(root.querySelectorAll('.mmk-card'));
		var empty = root.querySelector('[data-mmk-empty]');
		var city = '';
		var types = [];
		function apply() {
			var term = q ? q.value.trim().toLowerCase() : '';
			var shown = 0;
			cards.forEach(function (c) {
				var ok = (!city || c.dataset.city === city) &&
					(!term || c.dataset.hay.indexOf(term) !== -1) &&
					types.every(function (t) { return ('|' + c.dataset.types + '|').indexOf('|' + t + '|') !== -1; });
				c.hidden = !ok;
				if (ok) shown++;
			});
			if (empty) empty.hidden = shown > 0;
		}
		root.addEventListener('click', function (e) {
			var b = e.target.closest('[data-mmk-city]');
			if (b) {
				city = b.dataset.mmkCity;
				root.querySelectorAll('[data-mmk-city]').forEach(function (x) { x.classList.toggle('is-on', x === b); });
				apply();
				return;
			}
			var t = e.target.closest('[data-mmk-type]');
			if (t) {
				var v = t.dataset.mmkType, i = types.indexOf(v);
				if (i === -1) types.push(v); else types.splice(i, 1);
				t.classList.toggle('is-on', i === -1);
				t.setAttribute('aria-pressed', i === -1 ? 'true' : 'false');
				apply();
			}
		});
		if (q) q.addEventListener('input', apply);
	});
})();
