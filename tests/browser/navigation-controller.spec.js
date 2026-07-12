const { test, expect } = require('@playwright/test');

const desktopScreens = [
  'dashboard',
  'operationCenter',
  'gis',
  'households',
  'businessHouseholds',
  'vehicles',
  'contributions',
  'agriculture',
  'houses',
  'publicAssets',
  'livestock',
  'persons',
  'temporaryResidence',
  'temporaryAbsence',
  'movements',
  'reports',
  'import',
  'exportExcel',
  'printForms',
  'users',
  'permissions',
  'logs',
  'backups',
  'restore',
  'settings',
  'appearance'
];

const moduleScreens = [
  'households',
  'persons',
  'temporaryResidence',
  'temporaryAbsence',
  'movements',
  'publicAssets',
  'businessHouseholds',
  'livestock',
  'houses',
  'vehicles',
  'agriculture',
  'contributions'
];

function ok(data) {
  return { ok: true, success: true, data };
}

async function mockApis(page) {
  await page.route('**/api/**', async (route) => {
    const url = route.request().url();
    const fulfill = (data) => route.fulfill({ contentType: 'application/json', body: JSON.stringify(ok(data)) });
    if (url.includes('/api/auth/me')) return fulfill({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.includes('/api/public/login-config')) return fulfill({ settings: {}, metrics: {} });
    if (url.includes('/api/dashboard/')) return fulfill({ metrics: {}, charts: {}, kpis: [], items: [], generatedAt: new Date().toISOString() });
    if (url.includes('/api/gis/')) return fulfill({ items: [], total: 0, areas: [] });
    if (url.includes('/api/operation-center/')) return fulfill({ items: [], total: 0, data: { items: [] } });
    if (url.includes('/api/public-assets/catalogs')) return fulfill({ types: [], areas: [], statuses: [] });
    if (url.includes('/api/public-assets')) return fulfill({ items: [], total: 0, page: 1, pageSize: 20, totalPages: 1 });
    return fulfill({ items: [], total: 0, page: 1, pageSize: 20 });
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

async function inspectNavigation(page, requestedScreen) {
  return page.evaluate((screen) => {
    const screenRows = Array.from(document.querySelectorAll('.screen')).map((el) => {
      const style = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      return {
        id: el.id,
        active: el.classList.contains('active'),
        inlineDisplay: el.style.display || '',
        computedDisplay: style.display,
        visibility: style.visibility,
        zIndex: style.zIndex,
        width: Math.round(rect.width),
        height: Math.round(rect.height)
      };
    });
    const visibleScreens = screenRows.filter((row) => row.computedDisplay !== 'none' && row.visibility !== 'hidden' && row.width > 0 && row.height > 0);
    const activeScreens = screenRows.filter((row) => row.active);
    const highest = visibleScreens
      .map((row) => ({ id: row.id, z: Number.parseInt(row.zIndex, 10) || 0 }))
      .sort((a, b) => b.z - a.z)[0] || null;
    const activeNav = Array.from(document.querySelectorAll('.sidebar .nav-link.active[data-screen], .mobile-bottom-nav .active[data-mobile-screen]')).map((el) => el.dataset.screen || el.dataset.mobileScreen);
    const active = document.querySelector('.screen.active');
    return {
      requestedScreen: screen,
      menuKey: screen,
      moduleKey: screen,
      targetScreen: `${screen}Screen`,
      currentScreen: window.App && window.App.screen,
      activeScreenId: active ? active.id : '',
      activeScreens,
      visibleScreens,
      displayBlockCount: screenRows.filter((row) => row.computedDisplay === 'block').length,
      highestZIndexScreen: highest,
      activeNav
    };
  }, requestedScreen);
}

test('desktop sidebar clicks switch both active menu and visible screen content', async ({ page }) => {
  await page.setViewportSize({ width: 1366, height: 768 });
  await openAuthenticatedApp(page);

  for (const screen of desktopScreens) {
    const button = page.locator(`.sidebar .nav-link[data-screen="${screen}"]`).first();
    await expect(button, `missing sidebar button for ${screen}`).toBeVisible();
    await button.click();
    await page.waitForTimeout(80);
    const state = await inspectNavigation(page, screen);
    await test.info().attach(`navigation-${screen}.json`, {
      body: JSON.stringify(state, null, 2),
      contentType: 'application/json'
    });

    expect(state.currentScreen, `${screen}: App.screen`).toBe(screen);
    expect(state.activeScreenId, `${screen}: active screen`).toBe(`${screen}Screen`);
    expect(state.activeScreens.map((row) => row.id), `${screen}: exactly one active screen`).toEqual([`${screen}Screen`]);
    expect(state.visibleScreens.map((row) => row.id), `${screen}: exactly one visible screen`).toEqual([`${screen}Screen`]);
    expect(state.displayBlockCount, `${screen}: only target screen display:block`).toBe(1);
    expect(state.activeNav, `${screen}: sidebar active follows screen`).toContain(screen);
  }

  await page.screenshot({ path: 'test-results/navigation-desktop-final.png', fullPage: true });
});

test('mobile and tablet module clicks use the same controller and change content', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name === 'desktop-chromium', 'Covered by desktop sidebar test.');
  await openAuthenticatedApp(page);

  for (const screen of moduleScreens) {
    const button = page.locator(`.mobile-bottom-nav [data-mobile-screen="${screen}"]`).first();
    await expect(button, `missing mobile button for ${screen}`).toBeVisible();
    await button.click();
    await page.waitForTimeout(80);
    const state = await inspectNavigation(page, screen);
    await test.info().attach(`${testInfo.project.name}-navigation-${screen}.json`, {
      body: JSON.stringify(state, null, 2),
      contentType: 'application/json'
    });

    expect(state.currentScreen, `${screen}: App.screen`).toBe(screen);
    expect(state.activeScreenId, `${screen}: active screen`).toBe(`${screen}Screen`);
    expect(state.visibleScreens.map((row) => row.id), `${screen}: exactly one visible screen`).toEqual([`${screen}Screen`]);
    expect(state.displayBlockCount, `${screen}: only target screen display:block`).toBe(1);
    expect(state.activeNav, `${screen}: mobile active follows screen`).toContain(screen);
  }

  await page.screenshot({ path: `test-results/navigation-${testInfo.project.name}-final.png`, fullPage: true });
});
