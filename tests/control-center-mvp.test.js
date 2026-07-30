const assert = require('assert');
const { spawnSync } = require('child_process');
const path = require('path');

const root = path.resolve(__dirname, '..');
const php = process.env.PHP || 'php';

function runIndex(host, uri, method = 'GET') {
  const code = [
    `$_SERVER['HTTP_HOST']=${JSON.stringify(host)};`,
    `$_SERVER['REQUEST_URI']=${JSON.stringify(uri)};`,
    `$_SERVER['REQUEST_METHOD']=${JSON.stringify(method)};`,
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

const html = runIndex('hongphongnb.com', '/');
assert.match(html, /id="loginScreen"/);
assert.match(html, /id="loginForm"/);
assert.match(html, /data-section="dashboard"/);
assert.match(html, /data-section="units"/);
assert.match(html, /data-section="accounts"/);
assert.match(html, /data-section="permissions"/);
assert.match(html, /id="permissionsSection"/);
assert.match(html, /id="logoutButton"/);
assert.match(html, /Phân quyền Community Control Center/);

const permissions = jsonResponse(runIndex('hongphongnb.com', '/api/control-center/permissions'));
assert.strictEqual(permissions.ok, false);
assert.match(permissions.message, /đăng nhập/i);

const update = jsonResponse(runIndex('hongphongnb.com', '/api/control-center/permissions', 'PUT'));
assert.strictEqual(update.ok, false);
assert.match(update.message, /đăng nhập/i);

const tenantBlocked = jsonResponse(runIndex('tenant-a.hongphongnb.com', '/api/control-center/permissions'));
assert.strictEqual(tenantBlocked.ok, false);

console.log('Control Center MVP smoke tests passed.');
