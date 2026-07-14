const { test, expect } = require('@playwright/test');

async function mockApis(page) {
  await page.route('**/api/**', async route => {
    const url = route.request().url();
    const payload = data => route.fulfill({ contentType: 'application/json; charset=utf-8', body: JSON.stringify({ ok: true, success: true, data }) });

    if (url.includes('/api/public/login-config')) return payload({ settings: { systemName: 'Thong 09' }, metrics: {} });
    if (url.includes('/api/auth/me')) return payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.includes('/api/persons/123')) return payload({
      id: 123,
      citizen_code: 'NK001',
      full_name: 'Nguyễn Văn A',
      gender: 'Nam',
      date_of_birth: '1990-01-01',
      household_id: 9,
      relationship: 'Chủ hộ',
      display_address: 'Thôn 09, xã Hồng Phong'
    });
    if (url.includes('/api/files?')) return payload({
      items: [{
        id: 77,
        original_name: 'Hồ sơ nhân khẩu.pdf',
        file_name: 'ho-so-nhan-khau.pdf',
        profile_section: 'citizen_pdf',
        file_type: 'DOCUMENT',
        file_size: 2048,
        created_at: '2026-07-14 08:00:00',
        created_by_name: 'Cán bộ thôn'
      }],
      total: 1,
      page: 1,
      pageSize: 24
    });
    if (url.includes('/api/profiles/timeline/citizen/123')) return payload([{ title: 'Cập nhật hồ sơ', time: '2026-07-14 08:00:00', actor: 'Admin' }]);
    if (url.includes('/api/dashboard/summary')) return payload({ metrics: {}, charts: {}, generatedAt: new Date().toISOString() });
    return payload({ items: [], total: 0, page: 1, pageSize: 20 });
  });
}

async function openAuthenticatedApp(page) {
  await mockApis(page);
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };
    App.token = 'test-token';
    App.csrfToken = 'test-csrf';
    App.user = user;
    window.App = App;
    localStorage.setItem('thon09_token', 'test-token');
    localStorage.setItem('thon09_csrf', 'test-csrf');
    localStorage.setItem('thon09_user', JSON.stringify(user));
    if (typeof window.showApp === 'function') window.showApp();
  });
  await expect(page.locator('#appView')).not.toHaveClass(/d-none/);
}

test('Digital Profile tabs are safe and keep the Vietnamese font stack', async ({ page }) => {
  const failures = [];
  page.on('console', message => {
    if (message.type() === 'error') failures.push(message.text());
  });
  page.on('pageerror', error => failures.push(error.message));

  await openAuthenticatedApp(page);
  await page.evaluate(() => window.showPerson(123));
  await expect(page.locator('#detailModal')).toBeVisible();

  await page.locator('[data-profile-tab="files"]').click();
  await expect(page.locator('[data-profile-pane="files"]')).not.toHaveClass(/d-none/);
  await expect(page.locator('[data-profile-pane="files"]')).toContainText('Hồ sơ nhân khẩu.pdf');

  await page.locator('[data-profile-tab="timeline"]').click();
  await expect(page.locator('[data-profile-pane="timeline"]')).not.toHaveClass(/d-none/);
  await expect(page.locator('[data-profile-pane="timeline"]')).toContainText('Cập nhật hồ sơ');

  const fontState = await page.evaluate(() => {
    const modal = document.querySelector('#detailModal .modal-content');
    const bottomNav = document.querySelector('.mobile-bottom-nav');
    const cssVar = getComputedStyle(document.documentElement).getPropertyValue('--app-font-family').trim();
    return {
      cssVar,
      body: getComputedStyle(document.body).fontFamily,
      modal: modal ? getComputedStyle(modal).fontFamily : '',
      bottomNav: bottomNav ? getComputedStyle(bottomNav).fontFamily : ''
    };
  });

  expect(fontState.cssVar).toContain('Segoe UI');
  expect(fontState.body).not.toContain('Be Vietnam Pro');
  expect(fontState.modal).not.toContain('Be Vietnam Pro');
  expect(fontState.bottomNav).not.toContain('Be Vietnam Pro');
  expect(failures).toEqual([]);
});
