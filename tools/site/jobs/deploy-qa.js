// Deploy marmar-qa-fixes 1.9.40 (cart "item modified" notice in Latvian).
const login = require('../login'); const deploy = require('../deploy');
(async () => { const { b, p, api } = await login();
  await deploy(p, api, 'marmar-qa-fixes', 'marmar-qa-fixes.php');
  await b.close(); })();
