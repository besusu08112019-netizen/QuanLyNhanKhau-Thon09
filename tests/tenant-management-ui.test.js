const assert = require('assert');
const { spawnSync } = require('child_process');
const path = require('path');

const root = path.resolve(__dirname, '..');
const php = process.env.PHP || 'php';

function runIndex(host, uri = '/') {
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
    },
    encoding: 'utf8',
  });

  assert.strictEqual(result.status, 0, result.stderr || result.stdout);
  return result.stdout;
}

const html = runIndex('hongphongnb.com');
assert.match(html, /data-section="tenants"/);
assert.match(html, /id="tenantsSection"/);
assert.match(html, /id="tenantsBody"/);
assert.match(html, /id="tenantsPagination"/);
assert.match(html, /id="tenantModal"/);
assert.match(html, /id="tenantDetailModal"/);
assert.match(html, /id="tenantActivityBody"/);

assert.match(html, /\/api\/control-center\/tenants/);
assert.match(html, /\/api\/control-center\/tenants\/' \+ encodeURIComponent\(tenant\.id\) \+ '\/activity/);

assert.match(html, /data-tenant-permission="tenant.create"/);
for (const permission of ['tenant.update', 'tenant.lock', 'tenant.unlock', 'tenant.delete']) {
  assert.match(html, new RegExp(`dataset\\.tenantPermission = '${permission}'`));
}

const tenantModal = html.slice(html.indexOf('id="tenantModal"'), html.indexOf('id="tenantDetailModal"'));
assert.ok(tenantModal.includes('id="tenantDatabaseHost"'));
assert.ok(tenantModal.includes('id="tenantDatabaseName"'));
assert.doesNotMatch(tenantModal, /password|secret|token|connection[_ -]?string/i);

assert.match(html, /function loadTenants\(\)/);
assert.match(html, /function openTenantDetail\(tenant\)/);
assert.match(html, /function applyTenantPermissions\(\)/);
assert.match(html, /function validateTenantForm\(payload\)/);
assert.match(html, /tenantPrevPageButton/);
assert.match(html, /tenantNextPageButton/);

const scripts = [...html.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((match) => match[1]).join('\n');
new Function(scripts);

const tenantBlocked = JSON.parse(runIndex('tenant-a.hongphongnb.com', '/api/control-center/tenants').trim());
assert.strictEqual(tenantBlocked.ok, false);

console.log('Tenant Management UI smoke tests passed.');
