const { test, expect } = require('@playwright/test');
const path = require('path');

function ok(data) { return { ok: true, success: true, data }; }

async function boot(page) {
  const requests = [];
  await page.route('**/api/**', async route => {
    const request = route.request();
    const url = new URL(request.url());
    const fulfill = data => route.fulfill({ contentType: 'application/json', body: JSON.stringify(ok(data)) });
    if (url.pathname === '/api/organizations/catalogs') return fulfill({
      organizations: [{ value: 'WOMEN', code: 'WOMEN', label: 'Chi hội Phụ nữ', name: 'Chi hội Phụ nữ' }],
      positions: [{ value: 11, id: 11, label: 'Hội viên', organization_code: 'WOMEN' }],
      statuses: [{ value: 'ACTIVE', label: 'Đang tham gia' }, { value: 'ENDED', label: 'Đã thôi tham gia' }],
      areas: [{ value: 'Xóm 1', label: 'Xóm 1' }]
    });
    if (url.pathname === '/api/organizations/dashboard') return fulfill({ metrics: { total_active_members: 1 }, organizations: [{ code: 'WOMEN', name: 'Chi hội Phụ nữ', active_count: 1 }], warnings: [] });
    if (url.pathname === '/api/organizations/citizen-search') return fulfill({ items: [{ id: 7, citizen_id: 7, citizen_code: 'NK-0007', full_name: 'Nguyễn Thị Thắm', household_code: 'H09-0125', address: 'Xóm 1', has_current_membership: false }] });
    if (url.pathname === '/api/organizations' && request.method() === 'GET') return fulfill({ items: [], total: 0, page: 1, pageSize: 20, totalPages: 1 });
    if (url.pathname === '/api/organizations' && request.method() === 'POST') {
      const body = request.postDataJSON();
      requests.push({ method: 'POST', path: url.pathname, body });
      return fulfill({ id: 99, ...body, citizen_id: Number(body.citizen_id), full_name: 'Nguyễn Thị Thắm', organization_name: 'Chi hội Phụ nữ', status_label: 'Đang tham gia' });
    }
    return fulfill({ items: [], total: 0, page: 1, pageSize: 20, totalPages: 1 });
  });
  await page.setContent('<!doctype html><html><head><base href="http://app.test/"></head><body><main id="mainContent"></main><div id="detailBody"></div></body></html>');
  await page.evaluate(() => {
    window.bootstrap = { Modal: { getOrCreateInstance: () => ({ show() {}, hide() {} }) } };
    const actions = new Map();
    window.TenantAppPlatform = {
      modules: { get: () => null, register: () => {} },
      routes: { match: () => null, register: () => {} },
      menus: { get: () => ({ items: [] }), upsert: () => {} },
      menuRenderer: { renderAll: () => {} },
      permissions: { can: () => true },
      api: { request: async (url, options = {}) => {
        const response = await fetch(url, { method: options.method || 'GET', headers: { 'Content-Type': 'application/json' }, body: options.body });
        const payload = await response.json();
        if (!response.ok || payload.ok === false) throw new Error(payload.error?.message || 'API error');
        return payload.data;
      } },
      actions: {
        register: (key, handler) => { actions.set(key, handler); },
        bind: root => {
          if (root.__communityOrgBound) return;
          root.__communityOrgBound = true;
          root.addEventListener('click', event => {
            const el = event.target.closest('[data-platform-action]');
            if (!el) return;
            const handler = actions.get(el.dataset.platformAction);
            if (handler) handler(el);
          });
        }
      }
    };
    window.App = { user: { role: 'SUPER_ADMIN' } };
    window.showToast = () => {};
    window.alert = () => {};
    window.confirm = () => true;
  });
  await page.addScriptTag({ path: path.resolve('assets/js/community-organizations.js') });
  await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
  await expect(page.locator('#communityOrganizationsScreen')).toBeVisible();
  await page.evaluate(() => window.loadCommunityOrganizations());
  return requests;
}

test('community organizations requires selected citizen and saves citizen_id', async ({ page }) => {
  const requests = await boot(page);
  await page.getByRole('button', { name: /Thêm thành viên/i }).click();
  await page.locator('#communityOrgOrgSelect').selectOption('WOMEN');
  await page.locator('#communityOrgCitizenSearch').fill('Nguyễn Thị Thắm');
  await expect(page.locator('#communityOrgCitizenSuggestions')).toContainText('NK-0007');
  await page.getByRole('button', { name: /Nguyễn Thị Thắm/i }).click();
  await page.locator('#communityOrgPositionSelect').selectOption('11');
  await page.locator('[name="joined_date"]').fill('11/08/2026');
  await page.locator('#communityOrgForm button[type="submit"]').click();
  await expect.poll(() => requests.length).toBe(1);
  expect(requests[0].body.citizen_id).toBe('7');
  expect(requests[0].body.organization_code).toBe('WOMEN');
});

test('community organizations blocks free-text citizen value', async ({ page }) => {
  const requests = await boot(page);
  await page.getByRole('button', { name: /Thêm thành viên/i }).click();
  await page.locator('#communityOrgOrgSelect').selectOption('WOMEN');
  await page.locator('#communityOrgCitizenSearch').fill('Nguyễn Thị Thắm');
  await page.locator('#communityOrgForm button[type="submit"]').click();
  await expect.poll(() => requests.length).toBe(0);
});
