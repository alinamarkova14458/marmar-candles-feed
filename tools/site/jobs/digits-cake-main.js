// Cake photo becomes the main image for digit toppers 3, 6, 7, 8, 9 (Alina 24.11.2026).
const login = require('../login');
const MAP = { 19481: [28390, 28388, 2977], 28377: [28391, 28376], 28380: [28392, 28379], 28386: [28393, 28385], 28383: [28394, 28382] };
(async () => { const { b, p, api } = await login();
  for (const [pid, ids] of Object.entries(MAP)) {
    const u = await api('wc/v3/products/' + pid, { method: 'PUT', data: { images: ids.map(id => ({ id })) } });
    console.log(pid, u.status, (u.data.images || []).map(i => i.id).join(','));
  }
  await require('../deploy').purge(p);
  await b.close(); })();
