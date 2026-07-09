const { test, expect } = require('@playwright/test');

const widths = [320, 360, 375, 390, 414, 480, 600, 768, 820, 1024];
const mobileScreens = ['dashboard', 'households', 'persons', 'businessHouseholds', 'vehicles', 'livestock', 'agriculture', 'contributions', 'gis'];
const bottomNavScreens = ['dashboard', 'households', 'persons', 'businessHouseholds', 'vehicles', 'livestock', 'agriculture', 'contributions', 'gis'];

async function mockApis(page) {
  await page.route('**/api/**', async (route) => {
    const url = route.request().url();
    const payload = (data) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) });

    if (url.includes('/api/public/login-config')) return payload({
      settings: { systemName: 'Thong 09', hamletName: 'Thon 09', communeName: 'Hong Phong', version: 'v2.0' },
      metrics: {}
    });
    if (url.includes('/api/auth/me')) return payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.includes('/api/dashboard/summary')) return payload({ metrics: {}, charts: {}, generatedAt: new Date().toISOString() });
    if (url.includes('/api/dashboard/')) return payload({ metrics: {}, charts: {}, kpis: [], generatedAt: new Date().toISOString() });
    if (url.includes('/api/gis/households')) return payload({ items: [], total: 0 });
    if (url.includes('/api/gis/summary')) return payload({ total: 0, located: 0, missing: 0, areas: [] });
    if (url.includes('/api/household-business')) return payload({ items: [], total: 0, page: 1, pageSize: 20, dashboard: {} });
    if (url.includes('/api/livestock')) return payload({ items: [], total: 0, page: 1, pageSize: 20, kpis: {} });
    if (url.includes('/api/agriculture')) return payload({ items: [], total: 0, page: 1, pageSize: 20, kpis: {} });
    if (url.includes('/api/households')) return payload({ items: [], total: 0, page: 1, pageSize: 20 });
    if (url.includes('/api/persons')) return payload({ items: [], total: 0, page: 1, pageSize: 20 });
    return payload({ items: [], total: 0 });
  });
}

async function openAuthenticatedApp(page, width) {
  await page.setViewportSize({ width, height: 900 });
  await mockApis(page);
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };
    window.App.token = 'test-token';
    window.App.user = user;
    localStorage.setItem('thon09_token', 'test-token');
    localStorage.setItem('thon09_user', JSON.stringify(user));
    if (typeof window.showApp === 'function') window.showApp();
  });
  await expect(page.locator('#appView')).not.toHaveClass(/d-none/);
}

test.describe('responsive system navigation audit', () => {
  for (const width of widths) {
    test(`main modules do not overflow at ${width}px`, async ({ page }) => {
      await openAuthenticatedApp(page, width);

      for (const screen of mobileScreens) {
        await page.evaluate((target) => window.switchScreen && window.switchScreen(target), screen);
        await page.waitForTimeout(120);
        const metrics = await page.evaluate(() => {
          const active = document.querySelector('.screen.active');
          const navItems = Array.from(document.querySelectorAll('.mobile-bottom-nav [data-mobile-screen]')).map((btn) => btn.dataset.mobileScreen);
          const nav = document.querySelector('.mobile-bottom-nav');
          return {
            activeId: active ? active.id : '',
            scrollWidth: Math.ceil(document.documentElement.scrollWidth),
            clientWidth: Math.ceil(document.documentElement.clientWidth),
            bodyScrollWidth: Math.ceil(document.body.scrollWidth),
            bodyClientWidth: Math.ceil(document.body.clientWidth),
            navVisible: !!nav && getComputedStyle(nav).display !== 'none',
            navItems
          };
        });

        expect(metrics.activeId).toBe(`${screen}Screen`);
        expect(metrics.scrollWidth).toBeLessThanOrEqual(metrics.clientWidth + 2);
        expect(metrics.bodyScrollWidth).toBeLessThanOrEqual(metrics.bodyClientWidth + 2);
        expect(metrics.navItems).not.toContain('operationCenter');
        if (width <= 820) {
          expect(metrics.navVisible).toBe(true);
          expect(metrics.navItems).toEqual(bottomNavScreens);
        }
      }
    });
  }
});