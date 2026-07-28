const assert = require('assert');
const { spawnSync } = require('child_process');
const path = require('path');

const root = path.resolve(__dirname, '..');
const php = process.env.PHP || 'php';

function runIndex(host, method, uri, extraServer = {}) {
  const serverAssignments = Object.entries(extraServer).map(([key, value]) => `$_SERVER[${JSON.stringify(key)}]=${JSON.stringify(value)};`);
  const code = [
    `$_SERVER['HTTP_HOST']=${JSON.stringify(host)};`,
    `$_SERVER['REQUEST_URI']=${JSON.stringify(uri)};`,
    `$_SERVER['REQUEST_METHOD']=${JSON.stringify(method)};`,
    ...serverAssignments,
    "include 'index.php';",
  ].join(' ');

  const result = spawnSync(php, ['-r', code], {
    cwd: root,
    env: {
      ...process.env,
      PLATFORM_ADMIN_ENABLED: 'true',
      PLATFORM_ADMIN_DOMAINS: 'hongphongnb.com,www.hongphongnb.com',
      PLATFORM_TENANT_DOMAIN_PATTERN: '{code}.hongphongnb.com',
      PLATFORM_DEFAULT_PORTAL: 'TENANT',
    },
    encoding: 'utf8',
  });

  assert.strictEqual(result.status, 0, result.stderr || result.stdout);
  return result.stdout;
}

function jsonResponse(stdout) {
  const text = stdout.trim();
  assert.ok(text.startsWith('{'), 'Expected JSON response');
  return JSON.parse(text);
}

const list = jsonResponse(runIndex('hongphongnb.com', 'GET', '/api/control-center/units'));
assert.strictEqual(list.ok, true);
assert.ok(Array.isArray(list.data.items));

const controlCenterHtml = runIndex('hongphongnb.com', 'GET', '/');
assert.match(controlCenterHtml, /id="addUnitButton"/);
assert.match(controlCenterHtml, /id="unitModal"/);
assert.match(controlCenterHtml, /id="unitForm"/);
assert.match(controlCenterHtml, /id="unitSearch"/);
assert.match(controlCenterHtml, /\/api\/control-center\/units/);
new Function([...controlCenterHtml.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((match) => match[1]).join('\n'));

const createWithoutToken = jsonResponse(runIndex('hongphongnb.com', 'POST', '/api/control-center/units'));
assert.strictEqual(createWithoutToken.ok, false);
assert.match(createWithoutToken.message, /dang nhap/);

const updateWithoutToken = jsonResponse(runIndex('hongphongnb.com', 'PUT', '/api/control-center/units/1'));
assert.strictEqual(updateWithoutToken.ok, false);
assert.match(updateWithoutToken.message, /dang nhap/);

const lockWithoutToken = jsonResponse(runIndex('hongphongnb.com', 'PATCH', '/api/control-center/units/1/lock'));
assert.strictEqual(lockWithoutToken.ok, false);
assert.match(lockWithoutToken.message, /dang nhap/);

const activateWithoutToken = jsonResponse(runIndex('hongphongnb.com', 'PATCH', '/api/control-center/units/1/activate'));
assert.strictEqual(activateWithoutToken.ok, false);
assert.match(activateWithoutToken.message, /dang nhap/);

const tenantBlocked = jsonResponse(runIndex('thon09.hongphongnb.com', 'GET', '/api/control-center/units'));
assert.strictEqual(tenantBlocked.ok, false);

const tenantApiBlockedOnRoot = jsonResponse(runIndex('hongphongnb.com', 'GET', '/api/households'));
assert.strictEqual(tenantApiBlockedOnRoot.ok, false);
assert.match(tenantApiBlockedOnRoot.message, /tenant khong kha dung/);

console.log('Administrative Unit Management smoke tests passed.');
