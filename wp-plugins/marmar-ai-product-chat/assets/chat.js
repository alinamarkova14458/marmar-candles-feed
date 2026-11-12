(() => {
  const root = document.getElementById('marmar-ai-root');
  if (!root || typeof MarMarAI === 'undefined') return;

  const launch = root.querySelector('.marmar-ai-launch');
  const win = root.querySelector('.marmar-ai-window');
  const close = root.querySelector('.marmar-ai-close');
  const newChat = root.querySelector('.marmar-ai-new');
  const messages = root.querySelector('.marmar-ai-messages');
  const form = root.querySelector('.marmar-ai-form');
  const input = root.querySelector('.marmar-ai-input');

  const STORAGE_KEY = 'marmar_ai_chat_v1';
  const CONVERSATION_KEY = 'marmar_ai_conversation_id_v1';
  const MAX_HISTORY = 12;
  const SELECTION_MAX = 12;
  let history = [];

  function getConversationId() {
    try {
      let id = localStorage.getItem(CONVERSATION_KEY);
      if (id && /^[A-Za-z0-9_-]{12,64}$/.test(id)) return id;
      const bytes = new Uint8Array(16);
      if (window.crypto && crypto.getRandomValues) crypto.getRandomValues(bytes);
      else for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
      id = Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
      localStorage.setItem(CONVERSATION_KEY, id);
      return id;
    } catch (_) {
      return 'session_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
    }
  }

  function startNewConversationId() {
    try { localStorage.removeItem(CONVERSATION_KEY); } catch (_) {}
    return getConversationId();
  }

  function saveState() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({
        history: history.slice(-MAX_HISTORY),
        html: messages.innerHTML,
        open: !win.hidden
      }));
    } catch (e) {
      console.warn('[MarMar AI] Could not save chat state.', e);
    }
  }

  function restoreState() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return;
      const state = JSON.parse(raw);
      if (Array.isArray(state.history)) history = state.history.filter(h =>
        h && (h.role === 'user' || h.role === 'assistant') && typeof h.content === 'string'
      ).slice(-MAX_HISTORY);
      if (typeof state.html === 'string' && state.html.length < 200000) messages.innerHTML = state.html;
      if (state.open) { win.hidden = false; launch.hidden = true; }
      messages.scrollTop = messages.scrollHeight;
    } catch (e) {
      console.warn('[MarMar AI] Could not restore chat state.', e);
      try { localStorage.removeItem(STORAGE_KEY); } catch (_) {}
    }
  }

  function addMessage(role, text) {
    const el = document.createElement('div');
    el.className = `marmar-ai-msg ${role}`;
    el.textContent = text;
    messages.appendChild(el);
    messages.scrollTop = messages.scrollHeight;
    saveState();
  }

  function currentSelection() {
    try {
      const raw = localStorage.getItem('marmar_ai_selection_v1');
      const items = raw ? JSON.parse(raw) : [];
      return Array.isArray(items) ? items : [];
    } catch (_) { return []; }
  }

  function saveSelectionLocal(items) {
    try { localStorage.setItem('marmar_ai_selection_v1', JSON.stringify(items.slice(-SELECTION_MAX))); } catch (_) {}
  }

  function showSelectionBar() {
    const items = currentSelection();
    let bar = root.querySelector('.marmar-ai-selection-bar');
    if (!items.length) { if (bar) bar.remove(); return; }
    if (!bar) {
      bar = document.createElement('div');
      bar.className = 'marmar-ai-selection-bar';
      messages.appendChild(bar);
    }
    bar.innerHTML = `<span>♡ Saglabāta izvēle · ${items.length}</span><button type="button">Apskatīt</button>`;
    bar.querySelector('button').addEventListener('click', openSelectionActions);
  }

  async function saveSelectionToServer(items) {
    const res = await fetch(MarMarAI.selectionSaveEndpoint, {
      method: 'POST', headers: {'Content-Type':'application/json','X-WP-Nonce':MarMarAI.nonce},
      body: JSON.stringify({items})
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || data.code || 'Request failed');
    return data;
  }

  function openSelectionActions() {
    const old = root.querySelector('.marmar-ai-selection-modal');
    if (old) { old.remove(); return; }
    const modal = document.createElement('div');
    modal.className = 'marmar-ai-selection-modal';
    modal.innerHTML = `<div class="marmar-ai-selection-panel">
      <button type="button" class="marmar-ai-selection-x" aria-label="Close">×</button>
      <strong>Tava izvēle 💗</strong>
      <p>Saglabā saiti vai nosūti izvēli uz e-pastu.</p>
      <button type="button" class="marmar-ai-selection-share">🔗 Kopīgot saiti</button>
      <button type="button" class="marmar-ai-selection-copy">📋 Kopēt saiti</button>
      <form class="marmar-ai-selection-email"><input type="email" placeholder="Tavs e-pasts" required><button type="submit">✉️ Nosūtīt</button></form>
      <a class="marmar-ai-selection-cart" href="${escapeAttr(MarMarAI.cartUrl)}">🛒 Iet uz grozu</a>
      <div class="marmar-ai-selection-status"></div>
    </div>`;
    root.appendChild(modal);
    const panel = modal.querySelector('.marmar-ai-selection-panel');
    const status = panel.querySelector('.marmar-ai-selection-status');
    modal.querySelector('.marmar-ai-selection-x').addEventListener('click', () => modal.remove());
    let shareData = null;
    async function ensureShare() {
      if (shareData) return shareData;
      const items = currentSelection();
      if (!items.length) throw new Error('Izvēle ir tukša.');
      shareData = await saveSelectionToServer(items);
      return shareData;
    }
    panel.querySelector('.marmar-ai-selection-share').addEventListener('click', async () => {
      try {
        const data = await ensureShare();
        if (navigator.share) await navigator.share({title:'MarMar CANDLES', text:'Mana MarMar CANDLES izvēle', url:data.share_url});
        else { await navigator.clipboard.writeText(data.share_url); status.textContent='Saite nokopēta ✓'; }
      } catch (e) { if (e.name !== 'AbortError') status.textContent = e.message || 'Neizdevās saglabāt saiti.'; }
    });
    panel.querySelector('.marmar-ai-selection-copy').addEventListener('click', async () => {
      try { const data = await ensureShare(); await navigator.clipboard.writeText(data.share_url); status.textContent='Saite nokopēta ✓'; }
      catch (e) { status.textContent = e.message || 'Neizdevās nokopēt saiti.'; }
    });
    panel.querySelector('form').addEventListener('submit', async e => {
      e.preventDefault();
      try {
        const data = await ensureShare();
        const email = panel.querySelector('input').value.trim();
        const res = await fetch(MarMarAI.selectionEmailEndpoint, {method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':MarMarAI.nonce},body:JSON.stringify({token:data.token,email})});
        const body = await res.json();
        if (!res.ok) throw new Error(body.message || body.code || 'Request failed');
        status.textContent='Nosūtīts uz e-pastu ✓';
      } catch (e) { status.textContent = e.message || 'Neizdevās nosūtīt e-pastu.'; }
    });
  }

  function addProducts(products) {
    if (!Array.isArray(products) || !products.length) return;
    const wrap = document.createElement('div');
    wrap.className = 'marmar-ai-products';
    products.forEach(p => {
      const card = document.createElement('article');
      card.className = 'marmar-ai-product';
      const price = p.price ? `${p.price} ${p.currency || ''}`.trim() : '';
      card.innerHTML = `
        ${p.image ? `<img src="${escapeAttr(p.image)}" alt="">` : ''}
        <div class="marmar-ai-product-info">
          <strong>${escapeHtml(p.name)}</strong>
          ${price ? `<span class="marmar-ai-price">${escapeHtml(price)}</span>` : ''}
          <div class="marmar-ai-product-actions">
            <a href="${escapeAttr(p.url)}" target="_blank" rel="noopener noreferrer">Skatīt produktu →</a>
            <button type="button" class="marmar-ai-save-item">♡ Saglabāt</button>
          </div>
        </div>`;
      card.querySelector('.marmar-ai-save-item').addEventListener('click', e => {
        const items = currentSelection();
        const keyId = Number(p.id);
        const idx = items.findIndex(x => Number(x.id) === keyId && !Number(x.variation_id || 0));
        const item = {id:keyId, variation_id:0, variation:{}};
        if (idx >= 0) items[idx] = item; else items.push(item);
        saveSelectionLocal(items);
        e.currentTarget.textContent = '✓ Saglabāts';
        showSelectionBar(); saveState();
      });
      wrap.appendChild(card);
    });
    messages.appendChild(wrap); messages.scrollTop = messages.scrollHeight; saveState(); showSelectionBar();
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    }[c]));
  }

  function escapeAttr(s) {
    return escapeHtml(s).replace(/javascript:/gi, '');
  }

  function showCartBar(data) {
    let bar = root.querySelector('.marmar-ai-cart-bar');
    if (!bar) {
      bar = document.createElement('div');
      bar.className = 'marmar-ai-cart-bar';
      messages.appendChild(bar);
    }
    bar.innerHTML = `<span>🛍 ${escapeHtml(data.cart_count)} ${data.cart_count === 1 ? 'prece' : 'preces'} · ${escapeHtml(data.cart_total || '')}</span><a href="${escapeAttr(data.cart_url || MarMarAI.cartUrl)}">Iet uz grozu →</a>`;
    messages.scrollTop = messages.scrollHeight;
    saveState();
  }

  async function addToCart(cart, btn) {
    if (!cart || !cart.enabled || !btn || btn.disabled) return;
    btn.disabled = true;
    btn.textContent = 'Pievieno…';
    try {
      const res = await fetch(MarMarAI.cartEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': MarMarAI.nonce
        },
        body: JSON.stringify({
          product_id: cart.product_id,
          variation_id: cart.variation_id || 0,
          variation: cart.variation || {},
          quantity: 1
        })
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || data.code || 'Request failed');
      btn.textContent = 'Pievienots ✓';
      btn.classList.add('is-added');
      showCartBar(data);
    } catch (e) {
      console.error('[MarMar AI] Add to cart failed', e);
      btn.disabled = false;
      btn.textContent = 'Pievienot grozam';
      addMessage('assistant', 'Neizdevās pievienot preci grozam. Lūdzu, pamēģini vēlreiz.');
    }
  }

  async function send(message) {
    addMessage('user', message);
    history.push({role: 'user', content: message});
    history = history.slice(-MAX_HISTORY);
    saveState();

    input.disabled = true;
    const typing = document.createElement('div');
    typing.className = 'marmar-ai-msg assistant marmar-ai-typing';
    typing.textContent = '…';
    messages.appendChild(typing);
    messages.scrollTop = messages.scrollHeight;

    try {
      const res = await fetch(MarMarAI.endpoint, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-WP-Nonce': MarMarAI.nonce},
        body: JSON.stringify({message, history, conversation_id: getConversationId()})
      });

      const data = await res.json();
      typing.remove();
      if (!res.ok) throw new Error(data.message || 'Request failed');

      addMessage('assistant', data.reply || '');
      history.push({role: 'assistant', content: data.reply || ''});
      history = history.slice(-MAX_HISTORY);
      addProducts(data.products);
      saveState();
    } catch (e) {
      typing.remove();
      addMessage('assistant', 'Atvainojiet, šobrīd nevaru apstrādāt pieprasījumu. Lūdzu, mēģiniet vēlreiz.');
      console.error(e);
    } finally {
      input.disabled = false;
      input.focus();
    }
  }

  launch.addEventListener('click', () => {
    win.hidden = false;
    launch.hidden = true;
    if (!messages.children.length && MarMarAI.welcome) addMessage('assistant', MarMarAI.welcome);
    saveState();
    input.focus();
  });

  if (newChat) {
    newChat.addEventListener('click', () => {
      history = [];
      messages.innerHTML = '';
      try { localStorage.removeItem(STORAGE_KEY); } catch (_) {}
      startNewConversationId();
      if (MarMarAI.welcome) addMessage('assistant', MarMarAI.welcome);
      input.focus();
    });
  }

  close.addEventListener('click', () => {
    win.hidden = true;
    launch.hidden = false;
    saveState();
  });

  form.addEventListener('submit', e => {
    e.preventDefault();
    const message = input.value.trim();
    if (!message || input.disabled) return;
    input.value = '';
    send(message);
  });

  messages.addEventListener('click', e => {
    const link = e.target.closest('a');
    if (!link) return;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    saveState();
  });

  async function restoreSharedSelection() {
    try {
      const token = new URLSearchParams(window.location.search).get('marmar_selection');
      if (!token) return;
      const res = await fetch(`${MarMarAI.selectionGetEndpoint}?token=${encodeURIComponent(token)}`);
      const data = await res.json();
      if (!res.ok || !Array.isArray(data.items) || !data.items.length) return;
      const local = data.items.map(x => ({id:x.id, variation_id:x.variation_id || 0, variation:x.variation || {}}));
      saveSelectionLocal(local);
      if (!messages.children.length) addMessage('assistant', 'Tava saglabātā MarMar CANDLES izvēle 💗');
      const products = data.items.map(x => ({...x, cart: x.can_add ? {enabled:true, product_id:x.id, variation_id:x.variation_id || 0, variation:x.variation || {}} : {enabled:false}}));
      addProducts(products);
      win.hidden = false; launch.hidden = true; saveState();
    } catch (e) { console.warn('[MarMar AI] Could not restore shared selection.', e); }
  }

  restoreState();
  showSelectionBar();
  restoreSharedSelection();
})();
