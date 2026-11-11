/* MarMar Filters — выезжающая панель на телефоне.
 * При открытии панель переносится в <body>: иначе липкая шапка темы и админ-панель
 * перекрывают её верх (у родительских блоков свой контекст наложения). */
(function () {
	'use strict';
	var panel = document.getElementById('mm-filters');
	var btn = document.querySelector('.mm-filters-toggle');
	var backdrop = document.querySelector('.mm-filters-backdrop');
	if (!panel || !btn) return;

	var home = document.createComment('mm-filters');
	panel.parentNode.insertBefore(home, panel);
	var timer = null;

	function open() {
		clearTimeout(timer);
		document.body.appendChild(panel);
		if (backdrop) { document.body.appendChild(backdrop); backdrop.hidden = false; }
		document.body.classList.add('mm-filters-active');
		requestAnimationFrame(function () { requestAnimationFrame(function () { panel.classList.add('is-open'); }); });
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
		document.body.classList.remove('mm-filters-active');
		btn.focus();
		// Возвращаем панель на место после анимации (на компьютере она — левая колонка).
		timer = setTimeout(function () {
			home.parentNode.insertBefore(panel, home);
			if (backdrop) home.parentNode.insertBefore(backdrop, home.nextSibling);
		}, 300);
	}
	btn.addEventListener('click', open);
	if (backdrop) backdrop.addEventListener('click', close);
	panel.querySelectorAll('.mm-filters__close, .mm-filters__apply').forEach(function (b) { b.addEventListener('click', close); });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && panel.classList.contains('is-open')) close(); });
})();
