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

test('application shell opens in browser', async ({ page }) => {
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

  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('button', { name: /đăng nhập/i }).first()).toBeVisible();
});
