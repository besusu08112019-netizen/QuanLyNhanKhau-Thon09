const assert = require('assert');
const { spawnSync } = require('child_process');
const path = require('path');

const root = path.resolve(__dirname, '..');
const php = process.env.PHP || 'php';

function runIndex(host, uri, extraEnv = {}) {
  const code = [
    `$_SERVER['HTTP_HOST']=${JSON.stringify(host)};`,
    `$_SERVER['REQUEST_URI']=${JSON.stringify(uri)};`,
    "$_SERVER['REQUEST_METHOD']='GET';",
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
      ...extraEnv,
    },
    encoding: 'utf8',
  });

  assert.strictEqual(result.status, 0, result.stderr || result.stdout);
  return result.stdout;
}

function firstJson(stdout) {
  const text = stdout.trim();
  assert.ok(text.startsWith('{'), 'Expected JSON response in stdout');
  return JSON.parse(text);
}

const status = firstJson(runIndex('hongphongnb.com', '/api/control-center/status'));
assert.strictEqual(status.ok, true);
assert.strictEqual(status.data.portal, 'CONTROL_CENTER');
assert.strictEqual(status.data.status, 'ready');

const blocked = firstJson(runIndex('hongphongnb.com', '/api/citizens'));
assert.strictEqual(blocked.ok, false);
assert.match(blocked.message, /đơn vị không khả dụng/i);

const controlCenterHtml = runIndex('hongphongnb.com', '/');
assert.match(controlCenterHtml, /Community Control Center/);
assert.match(controlCenterHtml, /CONTROL_CENTER/);

const tenantHtml = runIndex('tenant-a.hongphongnb.com', '/');
assert.match(tenantHtml, /id="loginView"/);
assert.doesNotMatch(tenantHtml, /Phase 1 Shell/);

const compatibilityHtml = runIndex('hongphongnb.com', '/', { PLATFORM_ADMIN_ENABLED: 'false' });
assert.match(compatibilityHtml, /Chế độ bảo trì/);
assert.doesNotMatch(compatibilityHtml, /Production rollback active/);

console.log('Control Center Phase 1 smoke tests passed.');
