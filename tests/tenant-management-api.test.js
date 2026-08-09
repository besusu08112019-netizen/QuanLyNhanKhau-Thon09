const assert = require('assert');
const fs = require('fs');
const { spawnSync } = require('child_process');
const path = require('path');

const root = path.resolve(__dirname, '..');
const php = process.env.PHP || 'php';

function read(relativePath) {
  return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

function runIndex(host, method, uri) {
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
  return JSON.parse(result.stdout.trim());
}

const index = read('index.php');
for (const route of [
  "GET' && $path === '/api/control-center/tenants'",
  "POST' && $path === '/api/control-center/tenants'",
  "GET' && preg_match('#^/api/control-center/tenants/(\\d+)$#",
  "PUT' && preg_match('#^/api/control-center/tenants/(\\d+)$#",
  "PATCH' && preg_match('#^/api/control-center/tenants/(\\d+)/lock$#",
  "PATCH' && preg_match('#^/api/control-center/tenants/(\\d+)/unlock$#",
  "DELETE' && preg_match('#^/api/control-center/tenants/(\\d+)$#",
  "GET' && preg_match('#^/api/control-center/tenants/(\\d+)/activity$#",
]) {
  assert.ok(index.includes(route), `Missing Tenant Management route: ${route}`);
}

const controller = read('app/Controllers/TenantManagementController.php');
assert.match(controller, /class TenantManagementController/);
assert.match(controller, /TenantManagementService/);
assert.match(controller, /ControlCenterAuthorizationFactory::make/);

const permissionService = read('app/Services/ControlCenterPermissionService.php');
for (const permission of [
  'tenant.view',
  'tenant.create',
  'tenant.update',
  'tenant.lock',
  'tenant.unlock',
  'tenant.delete',
  'tenant.activity.view',
]) {
  assert.ok(permissionService.includes(`'${permission}'`), `Missing permission ${permission}`);
}

const permissionRepository = read('app/Repositories/ControlCenterPermissionRepository.php');
assert.match(permissionRepository, /OR module = "tenant"/);
assert.match(permissionRepository, /OR module LIKE "tenant\.%"/);
assert.match(permissionRepository, /count\(\$parts\) < 2/);

const service = read('app/Services/TenantManagementService.php');
assert.match(service, /authorize\('tenant\.view'\)/);
assert.match(service, /authorize\('tenant\.create'\)/);
assert.match(service, /authorize\('tenant\.update'\)/);
assert.match(service, /authorize\('tenant\.lock'\)/);
assert.match(service, /authorize\('tenant\.unlock'\)/);
assert.match(service, /authorize\('tenant\.delete'\)/);
assert.match(service, /authorize\('tenant\.activity\.view'\)/);
assert.match(service, /'before' =>/);
assert.match(service, /'after' =>/);
assert.doesNotMatch(service.match(/private function auditSnapshot[\s\S]*?\n    \}/)?.[0] || '', /databaseHost|databaseName|databaseCharset/);

for (const [method, uri] of [
  ['GET', '/api/control-center/tenants'],
  ['GET', '/api/control-center/tenants/1'],
  ['POST', '/api/control-center/tenants'],
  ['PUT', '/api/control-center/tenants/1'],
  ['PATCH', '/api/control-center/tenants/1/lock'],
  ['PATCH', '/api/control-center/tenants/1/unlock'],
  ['DELETE', '/api/control-center/tenants/1'],
]) {
  const response = runIndex('hongphongnb.com', method, uri);
  assert.strictEqual(response.ok, false, `${method} ${uri} should require authentication`);
  assert.match(response.message, /??ng nh?p/i);
}

console.log('Tenant Management API stage 2 smoke tests passed.');
