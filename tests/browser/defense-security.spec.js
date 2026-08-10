const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

function appHtml() {
  const root = path.resolve(__dirname, '..', '..');
  const settings = { tenantNamespace: 'test_tenant', systemName: 'Test', hamletName: 'Thon 09', idleTimeoutSeconds: 21600, idleWarningSeconds: 60 };
  return fs.readFileSync(path.join(root, 'views', 'app.php'), 'utf8')
    .replace(/{{APP_SETTINGS_JSON}}/g, JSON.stringify(settings))
    .replace(/{{APP_NAME}}/g, 'Test')
    .replace(/{{THEME_COLOR}}/g, '#0f8a3b')
    .replace(/{{BACKGROUND_COLOR}}/g, '#ffffff')
    .replace(/{{LOGIN_BACKGROUND_STYLE}}/g, '')
    .replace(/{{UNIT_NAME}}/g, 'Thon 09')
    .replace(/{{TENANT_LOGO_CLASS}}/g, 'tenant-logo-placeholder')
    .replace(/{{TENANT_LOGO_HTML}}/g, 'QP')
    .replace(/{{HAMLET_NAME}}/g, 'Thon 09')
    .replace(/{{COMMUNE_NAME}}/g, 'Xa test')
    .replace(/{{COPYRIGHT}}/g, 'Test');
}

async function mockApis(page) {
  let savedNvqs = null;
  await page.route('**/', route => route.fulfill({ contentType: 'text/html; charset=utf-8', body: appHtml() }));
  await page.route('**/api/**', async route => {
    const request = route.request();
    const url = new URL(request.url());
    const payload = data => route.fulfill({ contentType: 'application/json; charset=utf-8', body: JSON.stringify({ ok: true, success: true, data }) });
    if (url.pathname === '/api/public/login-config') return payload({ settings: { systemName: 'Test', hamletName: 'Thon 09' }, metrics: {} });
    if (url.pathname === '/api/auth/me') return payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.pathname === '/api/defense-security/catalogs') return payload({
      settings: { nvqs_warning_age: 16, nvqs_registration_age: 17, nvqs_call_age: 18, nvqs_follow_end_age: 25 },
      yes_no: [{ value: 'YES', label: 'Co' }, { value: 'NO', label: 'Khong' }],
      nvqs_preliminary_statuses: [{ value: 'NOT_UPDATED', label: 'Chua cap nhat' }, { value: 'PASSED', label: 'Dat' }],
      nvqs_medical_statuses: [{ value: 'NOT_UPDATED', label: 'Chua cap nhat' }, { value: 'PASSED', label: 'Dat' }],
      nvqs_eligibility_statuses: [{ value: 'UNKNOWN', label: 'Chua xac dinh' }, { value: 'ELIGIBLE', label: 'Du dieu kien' }],
      nvqs_selection_statuses: [{ value: 'NOT_SELECTED', label: 'Chua trung tuyen' }, { value: 'ENLISTED', label: 'Da nhap ngu' }],
      militia_types: [{ value: 'CORE', label: 'Dan quan nong cot' }],
      participation_statuses: [{ value: 'ACTIVE', label: 'Dang tham gia' }, { value: 'ENDED', label: 'Thoi tham gia' }],
      security_positions: [{ value: 'LEADER', label: 'To truong' }, { value: 'MEMBER', label: 'To vien' }],
      security_statuses: [{ value: 'ACTIVE', label: 'Dang hoat dong' }]
    });
    if (url.pathname === '/api/defense-security/dashboard') return payload({ year: 2026, nvqs: { warning_age: 1, registration_age: 1, tracking_age: 2, registered: 0, unregistered: 1, preliminary_missing: 1, medical_missing: 1, enlisted: 0, active_service: 0 }, militia: { total: 0, active: 0 }, security_force: { total: 0, active: 0 } });
    if (url.pathname === '/api/defense-security/citizen-search') return payload({ items: [{ id: 501, citizen_code: 'NK09-0501', full_name: 'Nguyen Van Nam', date_of_birth: '2009-08-11', gender: 'Nam', household_code: 'H09-0125', address: 'Xom 2' }] });
    if (url.pathname === '/api/defense-security/nvqs' && request.method() === 'POST') { savedNvqs = JSON.parse(request.postData() || '{}'); return payload({ id: 91, ...savedNvqs, citizen_code: 'NK09-0501', full_name: 'Nguyen Van Nam', household_code: 'H09-0125' }); }
    if (url.pathname === '/api/defense-security/nvqs') return payload({ items: [{ id: null, citizen_id: 501, citizen_code: 'NK09-0501', full_name: 'Nguyen Van Nam', date_of_birth: '2009-08-11', gender: 'Nam', household_code: 'H09-0125', recruitment_year: 2026, registered_status_label: 'Khong', preliminary_status_label: 'Chua cap nhat', medical_exam_status_label: 'Chua cap nhat', eligibility_status_label: 'Chua xac dinh', selection_status_label: 'Chua trung tuyen', address: 'Xom 2' }], total: 1, page: 1, pageSize: 20, totalPages: 1 });
    if (url.pathname.includes('/api/defense-security/militia') || url.pathname.includes('/api/defense-security/security-force')) return payload({ items: [], total: 0, page: 1, pageSize: 20, totalPages: 1 });
    if (url.pathname.includes('/api/dashboard')) return payload({ metrics: {}, charts: {}, generatedAt: new Date().toISOString() });
    return payload({ items: [], total: 0, page: 1, pageSize: 20 });
  });
  await page.exposeFunction('__getSavedDefenseNvqs', () => savedNvqs);
}

async function openAuthenticatedApp(page) {
  await mockApis(page);
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };
    window.__toasts = [];
    window.showToast = (message, variant = 'success') => window.__toasts.push({ message, variant });
    window.App = window.App || {};
    const App = window.App;
    App.token = 'test-token'; App.csrfToken = 'test-csrf'; App.user = user;
    const storageKey = typeof window.tenantStorageKey === 'function' ? window.tenantStorageKey : key => key;
    localStorage.setItem(storageKey('token'), 'test-token');
    localStorage.setItem(storageKey('csrf'), 'test-csrf');
    localStorage.setItem(storageKey('user'), JSON.stringify(user));
    if (typeof window.showApp === 'function') window.showApp();
  });
  await expect(page.locator('#appView')).not.toHaveClass(/d-none/);
  await page.evaluate(() => window.TenantAppNavigationController?.navigate('defenseSecurity'));
  await expect(page.locator('#defenseSecurityScreen')).toHaveClass(/active/);
}

test('defense security NVQS uses citizen autocomplete and saves citizen_id', async ({ page }) => {
  await openAuthenticatedApp(page);
  await expect(page.locator('#defenseSecurityDashboard')).toContainText('Đến tuổi đăng ký NVQS');
  await expect(page.locator('#defenseRows')).toContainText('NK09-0501');

  await page.locator('[data-platform-action="defenseSecurity.create"]').click();
  await expect(page.locator('#defenseRecordModal')).toBeVisible();
  await page.locator('#defenseCitizenSearch').fill('Nguyen Van Nam');
  await page.locator('#defenseRecordForm button[type="submit"]').click();
  await expect.poll(() => page.evaluate(() => window.__toasts.at(-1)?.message || '')).toBe('Vui lòng chọn nhân khẩu từ danh sách.');

  await page.locator('#defenseCitizenSearch').fill('nguyen van nam');
  await expect(page.locator('#defenseCitizenSuggestions button')).toHaveCount(1);
  await page.locator('#defenseCitizenSuggestions button').click();
  await expect(page.locator('#defenseRecordForm input[name="citizen_id"]')).toHaveValue('501');
  await page.locator('input[name="registration_date"]').fill('11/08/2026');
  await page.locator('#defenseRecordForm button[type="submit"]').click();
  await expect.poll(() => page.evaluate(() => window.__getSavedDefenseNvqs())).toMatchObject({ citizen_id: '501', registration_date: '2026-08-11' });
});
