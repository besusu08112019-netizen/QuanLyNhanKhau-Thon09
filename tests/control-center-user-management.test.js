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

const listWithoutToken = jsonResponse(runIndex('hongphongnb.com', 'GET', '/api/control-center/users'));
assert.strictEqual(listWithoutToken.ok, false);
assert.match(listWithoutToken.message, /đăng nhập/i);

const controlCenterHtml = runIndex('hongphongnb.com', 'GET', '/');
assert.match(controlCenterHtml, /id="addAccountButton"/);
assert.match(controlCenterHtml, /id="accountModal"/);
assert.match(controlCenterHtml, /id="passwordModal"/);
assert.match(controlCenterHtml, /id="accountSearch"/);
assert.match(controlCenterHtml, /Chưa đăng nhập/);
assert.doesNotMatch(controlCenterHtml, /api\/control-center\/users\/\{id\}/);
new Function([...controlCenterHtml.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((match) => match[1]).join('\n'));

const createWithoutToken = jsonResponse(runIndex('hongphongnb.com', 'POST', '/api/control-center/users'));
assert.strictEqual(createWithoutToken.ok, false);
assert.match(createWithoutToken.message, /đăng nhập/i);

const updateWithoutToken = jsonResponse(runIndex('hongphongnb.com', 'PUT', '/api/control-center/users/1'));
assert.strictEqual(updateWithoutToken.ok, false);
assert.match(updateWithoutToken.message, /đăng nhập/i);

const deactivateWithoutToken = jsonResponse(runIndex('hongphongnb.com', 'PATCH', '/api/control-center/users/1/deactivate'));
assert.strictEqual(deactivateWithoutToken.ok, false);
assert.match(deactivateWithoutToken.message, /đăng nhập/i);

const activateWithoutToken = jsonResponse(runIndex('hongphongnb.com', 'PATCH', '/api/control-center/users/1/activate'));
assert.strictEqual(activateWithoutToken.ok, false);
assert.match(activateWithoutToken.message, /đăng nhập/i);

const resetWithoutToken = jsonResponse(runIndex('hongphongnb.com', 'PATCH', '/api/control-center/users/1/reset-password'));
assert.strictEqual(resetWithoutToken.ok, false);
assert.match(resetWithoutToken.message, /đăng nhập/i);

const deleteNotSupported = jsonResponse(runIndex('hongphongnb.com', 'DELETE', '/api/control-center/users/1'));
assert.strictEqual(deleteNotSupported.ok, false);
assert.match(deleteNotSupported.message, /không tồn tại/i);

const tenantBlocked = jsonResponse(runIndex('tenant-a.hongphongnb.com', 'GET', '/api/control-center/users'));
assert.strictEqual(tenantBlocked.ok, false);

const tenantApiBlockedOnRoot = jsonResponse(runIndex('hongphongnb.com', 'GET', '/api/users'));
assert.strictEqual(tenantApiBlockedOnRoot.ok, false);
assert.match(tenantApiBlockedOnRoot.message, /đơn vị không khả dụng/i);

console.log('Control Center User Management smoke tests passed.');
