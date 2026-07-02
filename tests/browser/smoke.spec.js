const { test, expect } = require('@playwright/test');

test('application shell renders externalized scripts without console errors', async ({ page }) => {
  const consoleErrors = [];
  const pageErrors = [];

  await page.route('**/api/public/login-config', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
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
      })
    });
  });

  await page.route('**/api/auth/me', async (route) => {
    await route.fulfill({
      status: 401,
      contentType: 'application/json',
      body: JSON.stringify({ ok: false, error: { message: 'Unauthenticated' } })
    });
  });

  page.on('console', (message) => {
    if (message.type() === 'error') {
      const text = message.text();
      if (!text.includes('/favicon.ico')) {
        consoleErrors.push(text);
      }
    }
  });
  page.on('pageerror', (error) => pageErrors.push(error.message));

  await page.goto('/', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('#loginView')).toBeVisible();
  await expect(page.locator('#loginForm')).toBeVisible();
  await expect(page.locator('script[src*="assets/js/view-inline-patches.js"]')).toHaveCount(1);

  const html = await page.content();
  expect(html).not.toContain('thon09-report-inline-stable');
  expect(html).not.toContain('thon09-person-advanced-filter-fix');
  expect(html).not.toContain('thon09-header-duplicate-guard');
  expect(html).not.toContain('thon09-final-navigation-repair');

  expect(pageErrors).toEqual([]);
  expect(consoleErrors).toEqual([]);
});
