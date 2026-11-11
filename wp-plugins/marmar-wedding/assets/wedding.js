/* MarMar Wedding — конструктор: цена по количеству, персонализация, загрузка файлов. */
(function () {
	'use strict';
	var C = window.MarMarWedding;
	var root = document.querySelector('[data-mmw]');
	if (!C || !root) return;
	var form = root.closest('form.cart');
	if (!form) return;

	var qty = form.querySelector('input.qty');
	var own = root.querySelector('#mmw_qty');
	var unitEl = root.querySelector('.mmw-summary-unit');
	var totalEl = root.querySelector('.mmw-summary-total');
	var tiers = Object.keys(C.tiers).map(Number).sort(function (a, b) { return a - b; });

	function money(v) { return v.toFixed(2).replace('.', C.decimal) + ' €'; }
	function unitFor(n) { var p = C.tiers[tiers[0]]; tiers.forEach(function (t) { if (n >= t) p = C.tiers[t]; }); return Number(p); }

	// Собственное поле количества в шаге 2 управляет скрытым полем WooCommerce.
	function setQty(n) {
		n = Math.max(C.minQty, parseInt(n, 10) || C.minQty);
		if (own) own.value = n;
		if (qty) { qty.value = n; qty.setAttribute('value', n); }
		update();
	}
	if (own) {
		root.querySelectorAll('.mmw-qty-btn').forEach(function (b) {
			b.addEventListener('click', function () { setQty((parseInt(own.value, 10) || C.minQty) + Number(b.dataset.step)); });
		});
		own.addEventListener('input', function () { var n = parseInt(own.value, 10); if (n >= C.minQty && qty) { qty.value = n; } update(); });
		own.addEventListener('change', function () { setQty(own.value); });
		form.addEventListener('submit', function () { setQty(own.value); }, true);
	}

	function update() {
		var n = Math.max(parseInt(own ? own.value : (qty && qty.value), 10) || C.minQty, 0);
		var u = unitFor(n);
		unitEl.textContent = n + ' gab. × ' + money(u);
		totalEl.textContent = C.i18n.total + ': ' + money(u * n);
		root.querySelectorAll('.mmw-tiers tr').forEach(function (tr, i) {
			var from = Number(tr.dataset.from), next = tiers[i + 1] || Infinity;
			tr.classList.toggle('is-active', n >= from && n < next);
		});
	}
	if (qty) {
		qty.min = C.minQty;
		if ((parseInt(qty.value, 10) || 0) < C.minQty) qty.value = C.minQty;
		if (own) own.value = qty.value;
		['input', 'change'].forEach(function (ev) { qty.addEventListener(ev, update); });
		// Кнопки +/- темы Blocksy меняют значение без события input.
		new MutationObserver(update).observe(qty, { attributes: true, attributeFilter: ['value'] });
		form.addEventListener('click', function (e) { if (e.target.closest('.ct-increase, .ct-decrease')) setTimeout(update, 30); });
	}
	update();

	// Поля персонализации показываем по выбору.
	function persToggle() {
		var sel = form.querySelector('input[name="mmw_pers"]:checked');
		var v = sel ? sel.value : 'none';
		root.querySelectorAll('.mmw-pers-fields').forEach(function (box) {
			box.hidden = box.dataset.for.split(' ').indexOf(v) === -1;
		});
	}
	form.addEventListener('change', function (e) { if (e.target.name === 'mmw_pers') persToggle(); });
	persToggle();

	// Загрузка файлов: сразу на сервер, в форму — только токены.
	var fileInput = root.querySelector('#mmw_file');
	var list = root.querySelector('.mmw-files');
	var hidden = root.querySelector('input[name="mmw_files"]');
	var files = [];
	function render() {
		list.textContent = '';
		files.forEach(function (f, i) {
			var row = document.createElement('span');
			row.className = 'mmw-file' + (f.error ? ' is-error' : '');
			row.textContent = f.error ? f.name + ' — ' + f.error : (f.token ? '📎 ' + f.name : f.name + ' — ' + C.i18n.uploading);
			if (f.token || f.error) {
				var x = document.createElement('button');
				x.type = 'button'; x.className = 'mmw-file-x'; x.textContent = '×'; x.setAttribute('aria-label', C.i18n.remove);
				x.addEventListener('click', function () { files.splice(i, 1); render(); });
				row.appendChild(x);
			}
			list.appendChild(row);
		});
		hidden.value = files.filter(function (f) { return f.token; }).map(function (f) { return f.token; }).join(',');
	}
	if (fileInput) {
		fileInput.addEventListener('change', function () {
			Array.prototype.forEach.call(fileInput.files, function (file) {
				if (files.filter(function (f) { return !f.error; }).length >= C.maxFiles) { files.push({ name: file.name, error: C.i18n.tooMany }); return; }
				if (file.size > C.maxBytes) { files.push({ name: file.name, error: C.i18n.tooBig }); return; }
				var entry = { name: file.name };
				files.push(entry);
				var data = new FormData(); data.append('file', file);
				fetch(C.upload, { method: 'POST', body: data, credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (d) { if (d && d.success) entry.token = d.data.token; else entry.error = (d && d.data && d.data.message) || C.i18n.failed; render(); })
					.catch(function () { entry.error = C.i18n.failed; render(); });
			});
			fileInput.value = '';
			render();
		});
	}
})();
