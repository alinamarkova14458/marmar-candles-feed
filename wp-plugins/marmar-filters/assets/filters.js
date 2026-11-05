/* MarMar Filters — выезжающая панель на телефоне. */
(function () {
	'use strict';
	var panel = document.getElementById('mm-filters');
	var btn = document.querySelector('.mm-filters-toggle');
	var backdrop = document.querySelector('.mm-filters-backdrop');
	if (!panel || !btn) return;

	function open() {
		panel.classList.add('is-open');
		if (backdrop) backdrop.hidden = false;
		btn.setAttribute('aria-expanded', 'true');
		document.documentElement.style.overflow = 'hidden';
		var c = panel.querySelector('.mm-filters__close');
		if (c) c.focus();
	}
	function close() {
		panel.classList.remove('is-open');
		if (backdrop) backdrop.hidden = true;
		btn.setAttribute('aria-expanded', 'false');
		document.documentElement.style.overflow = '';
		btn.focus();
	}
	btn.addEventListener('click', open);
	if (backdrop) backdrop.addEventListener('click', close);
	panel.querySelectorAll('.mm-filters__close, .mm-filters__apply').forEach(function (b) { b.addEventListener('click', close); });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && panel.classList.contains('is-open')) close(); });
})();
