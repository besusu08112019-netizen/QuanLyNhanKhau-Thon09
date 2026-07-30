const { test, expect } = require('@playwright/test');
const { spawnSync } = require('child_process');
const path = require('path');

const root = path.resolve(__dirname, '..', '..');
const php = process.env.PHP || 'php';

function renderControlCenterHtml() {
  const code = [
    "$_SERVER['HTTP_HOST']='hongphongnb.com';",
    "$_SERVER['REQUEST_URI']='/';",
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
  if (result.status !== 0) throw new Error(result.stderr || result.stdout);
  return result.stdout;
}

test('Tenant Management UI renders without console errors across responsive viewports', async ({ page }) => {
  const errors = [];
  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(message.text());
  });
  page.on('pageerror', (error) => errors.push(error.message));

  const html = renderControlCenterHtml();
  await page.route('**/', async (route) => {
    if (route.request().resourceType() === 'document') {
      return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
    }
    return route.fallback();
  });
  await page.route('**/api/control-center/**', async (route) => {
    const url = route.request().url();
    const ok = (data) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) });
    if (url.includes('/permissions')) {
      return ok({
        roles: [{ role: 'SYSTEM_ADMIN', label: 'Quản trị hệ thống' }],
        groups: [{ id: 'tenants', name: 'Quản lý Tenant', permissions: [
          { key: 'tenant.view', label: 'Xem Tenant' },
          { key: 'tenant.create', label: 'Thêm Tenant' },
          { key: 'tenant.update', label: 'Sửa Tenant' },
          { key: 'tenant.lock', label: 'Khóa Tenant' },
          { key: 'tenant.unlock', label: 'Mở khóa Tenant' },
          { key: 'tenant.delete', label: 'Xóa Tenant' },
          { key: 'tenant.activity.view', label: 'Xem nhật ký Tenant' },
        ] }],
        matrix: [],
      });
    }
    if (url.includes('/tenants/1/activity')) return ok({ items: [] });
    if (url.includes('/tenants')) {
      return ok({
        items: [{
          id: 1,
          code: 'thon09',
          name: 'Thôn 09',
          domain: 'thon09.hongphongnb.com',
          databaseName: 'tenant_thon09',
          status: 'ACTIVE',
          appVersion: 'v2.1',
          storageUsageBytes: 1048576,
          storageQuotaBytes: 1073741824,
          updatedAt: '2026-07-30 09:00:00',
        }],
        pagination: { page: 1, perPage: 25, total: 1, totalPages: 1 },
      });
    }
    return ok({ items: [] });
  });

  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    window.App = { token: 'a'.repeat(64), csrfToken: 'csrf', user: { role: 'SUPER_ADMIN', displayName: 'Admin', email: 'admin@example.test' } };
    document.getElementById('loginScreen')?.classList.add('hidden');
  });

  for (const viewport of [
    { width: 1366, height: 768 },
    { width: 768, height: 1024 },
    { width: 390, height: 844 },
  ]) {
    await page.setViewportSize(viewport);
    await page.evaluate(() => activateSection('tenants'));
    await page.evaluate(() => loadPermissions());
    await page.evaluate(() => loadTenants());
    await expect(page.locator('#tenantsSection')).toHaveClass(/active/);
    await expect(page.locator('#tenantsBody tr')).toHaveCount(1);
    await expect(page.locator('#addTenantButton')).toBeVisible();
    const horizontalOverflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(horizontalOverflow).toBeLessThanOrEqual(4);
  }

  expect(errors).toEqual([]);
});
