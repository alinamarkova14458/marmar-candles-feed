// Smoke test: login and read basic site info.
const login = require('../login');
(async () => { const { b, api } = await login();
  const r = await api('wp/v2/users/me?_fields=id,name');
  console.log('me', r.status, JSON.stringify(r.data));
  await b.close(); })();
