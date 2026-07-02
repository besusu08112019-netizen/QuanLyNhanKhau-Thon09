const { test, expect } = require('@playwright/test');

const loginConfig = {
  ok: true,
  data: {
    settings: {
      logoUrl: null,
      backgroundUrl: null,
      backgroundImages: [],
      systemName: 'Hệ thống Quản lý Hành chính',
      hamletName: 'Thôn 09',
      communeName: 'Xã Hồng Phong',
      slogan: 'Vì Nhân dân phục vụ',
      version: 'v2.0',
      copyright: '© Thôn 09 - Xã Hồng Phong'
    },
    metrics: {
      total_households: 0,
      total_citizens: 0,
      party_member_count: 0,
      male_count: 0,
      female_count: 0,
      away_count: 0
    }
  }
};

test('application shell renders externalized scripts without fatal runtime errors', async ({ page }) => {
  const consoleErrors = [];
  const pageErrors = [];

  await page.route('**/api/**', async (route) => {
    const url = route.request().url();
    if (url.includes('/api/public/login-config')) {
      await route.fulfill({ contentType: 'application/json', body: JSON.stringify(loginConfig) });
      return;
    }
    if (url.includes('/api/auth/me')) {
      await route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ ok: false, error: { message: 'Unauthenticated' } })
      });
      return;
    }
    await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, data: {} }) });
  });

  page.on('console', (message) => {
    if (message.type() !== 'error') return;
    const text = message.text();
    if (text.includes('/favicon.ico') || text.includes('Failed to load resource')) return;
    consoleErrors.push(text);
  });
  page.on('pageerror', (error) => pageErrors.push(error.message));

  await page.goto('/', { waitUntil: 'domcontentloaded' });

  await expect(page.getByRole('button', { name: /đăng nhập/i })).toBeVisible();
  await expect(page.locator('script[src*="assets/js/view-inline-patches.js"]')).toHaveCount(1);

  const html = await page.content();
  expect(html).not.toContain('thon09-report-inline-stable');
  expect(html).not.toContain('thon09-person-advanced-filter-fix');
  expect(html).not.toContain('thon09-header-duplicate-guard');
  expect(html).not.toContain('thon09-final-navigation-repair');

  expect(pageErrors).toEqual([]);
  expect(consoleErrors).toEqual([]);
});
