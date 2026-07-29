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

function jsonResponse(stdout) {
  const text = stdout.trim();
  assert.ok(text.startsWith('{'), 'Expected JSON response');
  return JSON.parse(text);
}

const status = jsonResponse(runIndex('hongphongnb.com', '/api/control-center/status'));
assert.strictEqual(status.ok, true);
assert.strictEqual(status.data.portal, 'CONTROL_CENTER');
assert.strictEqual(status.data.phase, 'phase2');

const dashboard = jsonResponse(runIndex('hongphongnb.com', '/api/control-center/dashboard'));
assert.strictEqual(dashboard.ok, true);
assert.strictEqual(typeof dashboard.data.totalUnits, 'number');
assert.strictEqual(typeof dashboard.data.healthInsuranceRate, 'number');

const units = jsonResponse(runIndex('hongphongnb.com', '/api/control-center/units'));
assert.strictEqual(units.ok, true);
assert.ok(Array.isArray(units.data.items));

const accounts = jsonResponse(runIndex('hongphongnb.com', '/api/control-center/accounts'));
assert.strictEqual(accounts.ok, true);
assert.deepStrictEqual(accounts.data.roles.map((role) => role.code), [
  'SYSTEM_ADMIN',
  'COMMUNE_ADMIN',
  'VILLAGE_ADMIN',
  'STAFF',
  'VIEWER',
]);

const monitoring = jsonResponse(runIndex('hongphongnb.com', '/api/control-center/monitoring'));
assert.strictEqual(monitoring.ok, true);
assert.ok(monitoring.data.version);
assert.ok(monitoring.data.runtime.phpVersion);
assert.ok(monitoring.data.database);
assert.ok(monitoring.data.storage);
assert.ok(monitoring.data.healthCheck);

const controlCenterHtml = runIndex('hongphongnb.com', '/');
assert.match(controlCenterHtml, /Community Control Center/);
assert.match(controlCenterHtml, /Tong quan/);
assert.match(controlCenterHtml, /Don vi/);
assert.match(controlCenterHtml, /Nguoi dung/);
assert.match(controlCenterHtml, /Phan quyen/);
assert.match(controlCenterHtml, /Monitoring/);
assert.doesNotMatch(controlCenterHtml, /id="loginView"/);

const blockedTenantApi = jsonResponse(runIndex('hongphongnb.com', '/api/citizens'));
assert.strictEqual(blockedTenantApi.ok, false);
assert.match(blockedTenantApi.message, /tenant khong kha dung/);

const tenantHtml = runIndex('tenant-a.hongphongnb.com', '/');
assert.match(tenantHtml, /id="loginView"/);
assert.doesNotMatch(tenantHtml, /Community Control Center Platform/);

const rollbackHtml = runIndex('hongphongnb.com', '/', { PLATFORM_ADMIN_ENABLED: 'false' });
assert.match(rollbackHtml, /id="loginView"/);
assert.doesNotMatch(rollbackHtml, /Community Control Center Platform/);

console.log('Control Center Phase 2 smoke tests passed.');
