/* MarMar Chat — виджет. Без зависимостей. */
(function () {
	'use strict';
	var C = window.MarMarChat;
	if (!C || document.getElementById('mmchat')) return;

	var store = {
		get: function (k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } },
		set: function (k, v) { try { window.localStorage.setItem(k, v); } catch (e) {} },
		sget: function (k) { try { return window.sessionStorage.getItem(k); } catch (e) { return null; } },
		sset: function (k, v) { try { window.sessionStorage.setItem(k, v); } catch (e) {} }
	};

	function el(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) n.className = cls;
		if (text != null) n.textContent = text;
		return n;
	}

	// Безопасный рендер текста: **жирный**, переносы строк, ссылки только на этот сайт.
	function renderText(target, text) {
		var host = location.hostname.replace(/^www\./, '');
		text.split('\n').forEach(function (line, i) {
			if (i) target.appendChild(document.createElement('br'));
			var re = /\*\*([^*]+)\*\*|\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, last = 0, m;
			while ((m = re.exec(line))) {
				if (m.index > last) target.appendChild(document.createTextNode(line.slice(last, m.index)));
				if (m[1]) {
					target.appendChild(el('strong', null, m[1]));
				} else {
					var ok = false;
					try { ok = new URL(m[3]).hostname.replace(/^www\./, '') === host; } catch (e) {}
					if (ok) { var a = el('a', null, m[2]); a.href = m[3]; target.appendChild(a); }
					else target.appendChild(document.createTextNode(m[2]));
				}
				last = re.lastIndex;
			}
			if (last < line.length) target.appendChild(document.createTextNode(line.slice(last)));
		});
	}

	var root = el('div', 'mmchat mmchat--' + C.position);
	root.id = 'mmchat';
	var launcher = el('button', 'mmchat__launcher', C.launcher);
	launcher.type = 'button';
	launcher.setAttribute('aria-expanded', 'false');

	var panel = el('section', 'mmchat__panel');
	panel.setAttribute('role', 'dialog');
	panel.setAttribute('aria-label', C.title);
	panel.hidden = true;

	var head = el('header', 'mmchat__head');
	head.appendChild(el('span', 'mmchat__title', C.title));
	var close = el('button', 'mmchat__close', '×');
	close.type = 'button';
	close.setAttribute('aria-label', 'Aizvērt');
	head.appendChild(close);

	var log = el('div', 'mmchat__log');
	log.setAttribute('aria-live', 'polite');
	var chips = el('div', 'mmchat__chips');
	var form = el('form', 'mmchat__form');
	var input = el('textarea', 'mmchat__input');
	input.rows = 1;
	input.maxLength = 1000;
	input.placeholder = C.placeholder;
	input.setAttribute('aria-label', C.placeholder);
	var send = el('button', 'mmchat__send', '➤');
	send.type = 'submit';
	send.setAttribute('aria-label', 'Sūtīt');
	form.appendChild(input);
	form.appendChild(send);

	var foot = el('p', 'mmchat__foot', C.disclaimer + ' ');
	if (C.privacy) { var pl = el('a', null, 'Privātuma politika'); pl.href = C.privacy; foot.appendChild(pl); }

	panel.appendChild(head);
	if (C.preview) panel.appendChild(el('div', 'mmchat__preview', C.preview));
	panel.appendChild(log);
	panel.appendChild(chips);
	panel.appendChild(form);
	panel.appendChild(foot);
	root.appendChild(panel);
	root.appendChild(launcher);
	document.body.appendChild(root);

	var history = [];
	try { history = JSON.parse(store.sget('mmchat_log') || '[]'); } catch (e) { history = []; }

	function scroll() { log.scrollTop = log.scrollHeight; }

	function addMsg(who, text, products, save) {
		var b = el('div', 'mmchat__msg mmchat__msg--' + who);
		renderText(b, text || '');
		log.appendChild(b);
		if (products && products.length) {
			var row = el('div', 'mmchat__cards');
			products.forEach(function (p) {
				var a = el('a', 'mmchat__card');
				a.href = p.url;
				var img = el('img');
				img.src = p.image; img.alt = ''; img.loading = 'lazy'; img.width = 120; img.height = 120;
				a.appendChild(img);
				a.appendChild(el('span', 'mmchat__card-name', p.name));
				a.appendChild(el('span', 'mmchat__card-price', p.price));
				row.appendChild(a);
			});
			log.appendChild(row);
		}
		if (save !== false) {
			history.push({ who: who, text: text, products: products || [] });
			store.sset('mmchat_log', JSON.stringify(history.slice(-40)));
		}
		scroll();
	}

	function renderChips() {
		chips.textContent = '';
		if (history.some(function (h) { return h.who === 'user'; })) return;
		(C.chips || []).forEach(function (c) {
			var b = el('button', 'mmchat__chip', c);
			b.type = 'button';
			b.addEventListener('click', function () { submit(c); });
			chips.appendChild(b);
		});
	}

	var busy = false;
	function submit(text) {
		text = (text || '').trim();
		if (!text || busy) return;
		busy = true;
		send.disabled = true;
		addMsg('user', text);
		chips.textContent = '';
		input.value = '';
		autosize();
		var typing = el('div', 'mmchat__msg mmchat__msg--bot mmchat__typing');
		typing.appendChild(el('span')); typing.appendChild(el('span')); typing.appendChild(el('span'));
		log.appendChild(typing);
		scroll();

		fetch(C.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ message: text, cid: store.get('mmchat_cid') || '', page: location.href })
		}).then(function (r) { return r.json(); }).then(function (d) {
			typing.remove();
			if (d.cid) store.set('mmchat_cid', d.cid);
			addMsg('bot', d.text || '…', d.products || []);
		}).catch(function () {
			typing.remove();
			addMsg('bot', 'Neizdevās savienoties. Lūdzu, pamēģiniet vēlreiz.', [], false);
		}).then(function () {
			busy = false;
			send.disabled = false;
			input.focus();
		});
	}

	function autosize() {
		input.style.height = 'auto';
		input.style.height = Math.min(input.scrollHeight, 110) + 'px';
	}

	function open() {
		panel.hidden = false;
		root.classList.add('is-open');
		launcher.setAttribute('aria-expanded', 'true');
		if (!log.childNodes.length) {
			addMsg('bot', C.greeting, [], false);
			history.forEach(function (h) { addMsg(h.who, h.text, h.products, false); });
			renderChips();
		}
		setTimeout(function () { input.focus(); }, 50);
	}
	function shut() {
		panel.hidden = true;
		root.classList.remove('is-open');
		launcher.setAttribute('aria-expanded', 'false');
		launcher.focus();
	}

	launcher.addEventListener('click', function () { panel.hidden ? open() : shut(); });
	close.addEventListener('click', shut);
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) shut(); });
	form.addEventListener('submit', function (e) { e.preventDefault(); submit(input.value); });
	input.addEventListener('input', autosize);
	input.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submit(input.value); }
	});
})();
