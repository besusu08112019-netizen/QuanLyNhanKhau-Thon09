const { test, expect } = require('@playwright/test');

const widths = [320, 360, 375, 390, 414, 480, 600, 768, 820, 1024];
const moduleOrderScreens = ['households', 'persons', 'temporaryResidence', 'temporaryAbsence', 'movements', 'publicAssets', 'businessHouseholds', 'livestock', 'houses', 'vehicles', 'agriculture', 'contributions'];
const mobileScreens = ['dashboard', ...moduleOrderScreens, 'gis'];
const bottomNavScreens = ['dashboard', ...moduleOrderScreens, 'gis'];

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
    if (url.includes('/api/public-assets/catalogs')) return payload({ types: [], areas: [], statuses: [] });
    if (url.includes('/api/public-assets/dashboard')) return payload({ metrics: {}, charts: {} });
    if (url.includes('/api/public-assets/gis')) return payload({ items: [] });
    if (url.includes('/api/public-assets')) return payload({ items: [], total: 0, page: 1, pageSize: 20, totalPages: 1 });
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
          const navStyle = nav ? getComputedStyle(nav) : null;
          const buttons = nav ? Array.from(nav.querySelectorAll('[data-mobile-screen]')) : [];
          const navRect = nav ? nav.getBoundingClientRect() : null;
          const activeButton = nav ? nav.querySelector('[data-mobile-screen].active') : null;
          const activeRect = activeButton ? activeButton.getBoundingClientRect() : null;
          const touchFailures = buttons
            .map((btn) => {
              const rect = btn.getBoundingClientRect();
              return { screen: btn.dataset.mobileScreen, width: Math.round(rect.width), height: Math.round(rect.height), top: Math.round(rect.top) };
            })
            .filter((item) => item.width < 48 || item.height < 48);
          const rowTops = buttons.map((btn) => Math.round(btn.offsetTop || btn.getBoundingClientRect().top));
          const rowSpread = rowTops.length ? Math.max(...rowTops) - Math.min(...rowTops) : 0;
          return {
            activeId: active ? active.id : '',
            scrollWidth: Math.ceil(document.documentElement.scrollWidth),
            clientWidth: Math.ceil(document.documentElement.clientWidth),
            bodyScrollWidth: Math.ceil(document.body.scrollWidth),
            bodyClientWidth: Math.ceil(document.body.clientWidth),
            navVisible: !!nav && getComputedStyle(nav).display !== 'none',
            navDisplay: navStyle ? navStyle.display : '',
            navScrollWidth: nav ? Math.ceil(nav.scrollWidth) : 0,
            navClientWidth: nav ? Math.ceil(nav.clientWidth) : 0,
            activeCenterOffset: navRect && activeRect ? Math.abs((activeRect.left + activeRect.width / 2) - (navRect.left + navRect.width / 2)) : 0,
            touchFailures,
            rowSpread,
            navItems
          };
        });

        expect(metrics.activeId).toBe(`${screen}Screen`);
        expect(metrics.scrollWidth).toBeLessThanOrEqual(metrics.clientWidth + 2);
        expect(metrics.bodyScrollWidth).toBeLessThanOrEqual(metrics.bodyClientWidth + 2);
        expect(metrics.navItems).not.toContain('operationCenter');
        if (width <= 820) {
          expect(metrics.navVisible).toBe(true);
          expect(metrics.navDisplay).toBe('flex');
          expect(metrics.navScrollWidth).toBeGreaterThan(metrics.navClientWidth);
          expect(metrics.touchFailures).toEqual([]);
          expect(metrics.rowSpread).toBeLessThanOrEqual(2);
          expect(metrics.activeCenterOffset).toBeLessThanOrEqual(Math.max(120, metrics.navClientWidth * 0.42));
          expect(metrics.navItems).toEqual(bottomNavScreens);
        }
      }
    });
  }

  test('mobile sidebar masks GIS controls and keeps navigation layers below it', async ({ page }) => {
    await openAuthenticatedApp(page, 390);
    await page.evaluate(() => window.switchScreen && window.switchScreen('gis'));
    await page.waitForTimeout(200);

    await page.locator('#sidebarToggle').click();
    await expect(page.locator('body')).toHaveClass(/sidebar-open/);

    const layerMetrics = await page.evaluate(() => {
      const sidebar = document.querySelector('.sidebar');
      const backdrop = document.querySelector('.sidebar-backdrop');
      const nav = document.querySelector('.mobile-bottom-nav');
      const leafletControl = document.querySelector('.leaflet-control-container');
      const sidebarRect = sidebar ? sidebar.getBoundingClientRect() : null;
      const backdropRect = backdrop ? backdrop.getBoundingClientRect() : null;
      const sidebarStyle = sidebar ? getComputedStyle(sidebar) : null;
      const backdropStyle = backdrop ? getComputedStyle(backdrop) : null;
      const navStyle = nav ? getComputedStyle(nav) : null;
      const leafletStyle = leafletControl ? getComputedStyle(leafletControl) : null;
      return {
        sidebarWidth: sidebarRect ? Math.round(sidebarRect.width) : 0,
        viewportWidth: window.innerWidth,
        backdropCoversViewport: !!backdropRect && Math.round(backdropRect.left) <= 0 && Math.round(backdropRect.top) <= 0 && Math.round(backdropRect.width) >= window.innerWidth && Math.round(backdropRect.height) >= window.innerHeight,
        sidebarZ: sidebarStyle ? Number(sidebarStyle.zIndex) : 0,
        backdropZ: backdropStyle ? Number(backdropStyle.zIndex) : 0,
        navZ: navStyle ? Number(navStyle.zIndex) : 0,
        navPointerEvents: navStyle ? navStyle.pointerEvents : '',
        leafletOpacity: leafletStyle ? leafletStyle.opacity : '',
        leafletVisibility: leafletStyle ? leafletStyle.visibility : '',
        leafletPointerEvents: leafletStyle ? leafletStyle.pointerEvents : ''
      };
    });

    expect(layerMetrics.sidebarWidth).toBeGreaterThanOrEqual(Math.floor(layerMetrics.viewportWidth * 0.8));
    expect(layerMetrics.sidebarWidth).toBeLessThanOrEqual(Math.ceil(layerMetrics.viewportWidth * 0.85));
    expect(layerMetrics.backdropCoversViewport).toBe(true);
    expect(layerMetrics.sidebarZ).toBeGreaterThan(layerMetrics.backdropZ);
    expect(layerMetrics.backdropZ).toBeGreaterThan(layerMetrics.navZ);
    expect(layerMetrics.navPointerEvents).toBe('none');
    expect(layerMetrics.leafletOpacity).toBe('0');
    expect(layerMetrics.leafletVisibility).toBe('hidden');
    expect(layerMetrics.leafletPointerEvents).toBe('none');

    await page.mouse.click(385, 450);
    await expect(page.locator('body')).not.toHaveClass(/sidebar-open/);
  });

  test('module navigation clicks activate the requested screens', async ({ page }) => {
    await openAuthenticatedApp(page, 390);
    const isMobile = await page.evaluate(() => window.innerWidth <= 820);

    if (isMobile) {
      await page.locator('#sidebarToggle').click();
      await expect(page.locator('body')).toHaveClass(/sidebar-open/);
    }
    for (const screen of moduleOrderScreens) {
      await page.locator(`.sidebar .nav-link[data-screen="${screen}"]`).first().click();
      await page.waitForTimeout(120);
      const sidebarState = await page.evaluate((target) => ({
        appScreen: window.App && window.App.screen,
        activeId: document.querySelector('.screen.active')?.id || '',
        activeNav: document.querySelector('.sidebar .nav-link.active')?.dataset.screen || ''
      }), screen);
      expect(sidebarState.activeId).toBe(`${screen}Screen`);
      expect(sidebarState.activeNav).toBe(screen);
      expect(sidebarState.appScreen).toBe(screen);
      await page.evaluate(() => document.querySelectorAll('.toast').forEach((toast) => toast.remove()));
      if (isMobile) {
        await page.locator('#sidebarToggle').click();
        await expect(page.locator('body')).toHaveClass(/sidebar-open/);
      }
    }
    if (isMobile) {
      const size = page.viewportSize() || { width: 390, height: 844 };
      await page.mouse.click(size.width - 8, Math.floor(size.height / 2));
      await expect(page.locator('body')).not.toHaveClass(/sidebar-open/);
    }

    for (const screen of moduleOrderScreens) {
      await page.locator(`.mobile-bottom-nav [data-mobile-screen="${screen}"]`).first().click();
      await page.waitForTimeout(120);
      const mobileState = await page.evaluate((target) => ({
        appScreen: window.App && window.App.screen,
        activeId: document.querySelector('.screen.active')?.id || '',
        activeMobile: document.querySelector('.mobile-bottom-nav [data-mobile-screen].active')?.dataset.mobileScreen || ''
      }), screen);
      expect(mobileState.activeId).toBe(`${screen}Screen`);
      expect(mobileState.activeMobile).toBe(screen);
      expect(mobileState.appScreen).toBe(screen);
    }
  });
});
