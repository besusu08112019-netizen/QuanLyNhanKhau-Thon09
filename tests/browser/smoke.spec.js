const { test, expect } = require('@playwright/test');

const loginConfig = {
  ok: true,
  data: {
    settings: {
      logoUrl: null,
      backgroundUrl: null,
      backgroundImages: [],
      systemName: 'Administrative Management System',
      hamletName: 'Hamlet 09',
      communeName: 'Hong Phong Commune',
      slogan: 'Serving citizens',
      version: 'v2.0',
      copyright: 'Hamlet 09 - Hong Phong Commune'
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
  await expect(page.locator('#loginForm .login-submit')).toBeVisible();
});

test('operation center renders widgets without console errors', async ({ page }) => {
  const consoleErrors = [];
  page.on('console', message => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('pageerror', error => consoleErrors.push(error.message));

  await page.route('**/api/**', async (route) => {
    const url = route.request().url();
    const payload = (data) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) });

    if (url.includes('/api/public/login-config')) return payload(loginConfig.data);
    if (url.includes('/api/auth/login')) return payload({
      token: 'test-token',
      csrfToken: 'test-csrf',
      expiresIn: 3600,
      user: { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' }
    });
    if (url.includes('/api/auth/me')) return payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.includes('/api/dashboard/summary')) return payload({ metrics: {}, charts: {} });
    if (url.includes('/api/operation-center/notifications')) return payload({ ok: true, widget: 'notifications', data: { items: [{ key: 'missing_gps', label: 'Missing GPS', count: 2, priority: 'high', status: 'new', screen: 'gis', action: 'Open GIS', createdAt: new Date().toISOString() }] } });
    if (url.includes('/api/operation-center/tasks')) return payload({ ok: true, widget: 'tasks', data: { items: [{ key: 'missing_photo', label: 'Missing photo', count: 3, priority: 'high', status: 'open', screen: 'persons' }] } });
    if (url.includes('/api/operation-center/timeline')) return payload({ ok: true, widget: 'timeline', data: { items: [{ title: 'Created household', time: new Date().toISOString(), module: 'households', actor: 'admin@example.test' }] } });
    if (url.includes('/api/operation-center/area-dashboard')) return payload({ ok: true, widget: 'areaDashboard', data: { area: '', areas: [{ area_code: 'A1', total: 4 }], metrics: { total_households: 4, total_citizens: 12, male_count: 6, female_count: 6, children_count: 2, elderly_count: 1, party_member_count: 1, poor_households: 0, near_poor_households: 1 }, gpsProgress: { done: 3, total: 4, percent: 75 }, profileProgress: { done: 2, total: 4, percent: 50 } } });
    if (url.includes('/api/operation-center/progress')) return payload({ ok: true, widget: 'progress', data: { items: [{ key: 'gps', label: 'GPS', progress: { done: 8, total: 10, percent: 80 } }, { key: 'identity', label: 'CCCD', progress: { done: 7, total: 10, percent: 70 } }] } });
    if (url.includes('/api/operation-center/system-logs')) return payload({ ok: true, widget: 'systemLogs', data: { items: [{ created_at: new Date().toISOString(), user_email: 'admin@example.test', module: 'operation_center', message: 'Test log', ip_address: '127.0.0.1' }], total: 1, page: 1, pageSize: 20 } });
    if (url.includes('/api/operation-center/search')) return payload({ ok: true, widget: 'search', data: { items: [{ type: 'household', id: 1, title: 'HK001', subtitle: 'Head - Address' }], total: 1 } });
    if (url.includes('/api/operation-center/quick-profile')) return payload({ ok: true, widget: 'quickProfile', data: { type: 'household', id: 1, profile: { household_code: 'HK001', head_citizen_name: 'Head', address: 'Address' }, members: [{ full_name: 'Member A', relationship: 'Head' }], files: [{ original_name: 'profile.pdf' }], gps: { latitude: 10.1, longitude: 106.1 }, timeline: [{ created_at: '2026-07-06', message: 'Created household' }] } });

    return payload({});
  });

  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };
    window.App.token = 'test-token';
    window.App.csrfToken = 'test-csrf';
    window.App.user = user;
    localStorage.setItem('thon09_token', 'test-token');
    localStorage.setItem('thon09_csrf', 'test-csrf');
    localStorage.setItem('thon09_user', JSON.stringify(user));
    if (typeof window.showApp === 'function') window.showApp();
    if (typeof window.switchScreen === 'function') window.switchScreen('operationCenter');
  });
  await expect(page.locator('#appView')).not.toHaveClass(/d-none/);

  await expect(page.locator('#operationCenterScreen')).toHaveClass(/active/);
  await expect(page.locator('#operationNotifications')).toContainText('GPS');
  await expect(page.locator('#operationTasks')).toContainText('Missing photo');
  await expect(page.locator('#operationProgress')).toContainText('80%');

  await page.locator('#operationSearchInput').fill('HK001');
  await expect(page.locator('#operationSearchResults')).toContainText('HK001');
  await page.locator('#operationSearchResults [data-profile-id="1"]').click();
  await expect(page.locator('#detailModal')).toContainText('HK001');

  expect(consoleErrors).toEqual([]);
});

test('gis directions opens Google Maps with household coordinates', async ({ page }) => {
  await page.route('**/api/**', async (route) => {
    const url = route.request().url();
    if (url.includes('/api/public/login-config')) {
      await route.fulfill({ contentType: 'application/json', body: JSON.stringify(loginConfig) });
      return;
    }
    if (url.includes('/api/auth/me')) {
      await route.fulfill({ status: 401, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { message: 'Unauthenticated' } }) });
      return;
    }
    await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data: {} }) });
  });

  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => typeof window.thon09GisOpenDirectionsForRow === 'function');
  const openedUrl = await page.evaluate(() => {
    let captured = '';
    const originalOpen = window.open;
    window.open = (url) => { captured = String(url || ''); return null; };
    window.thon09GisOpenDirectionsForRow({ id: 123, latitude: 10.123456, longitude: 106.654321 });
    window.open = originalOpen;
    return captured;
  });

  expect(openedUrl).toBe('https://www.google.com/maps/dir/?api=1&destination=10.123456,106.654321');
});


test('smart reporting renders center, filters, BI and export actions', async ({ page }) => {
  const consoleErrors = [];
  page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
  page.on('pageerror', error => consoleErrors.push(error.message));

  await page.route('**/api/**', async (route) => {
    const url = route.request().url();
    const payload = (data) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) });
    if (url.includes('/api/public/login-config')) return payload(loginConfig.data);
    if (url.includes('/api/auth/login')) return payload({ token: 'test-token', csrfToken: 'test-csrf', expiresIn: 3600, user: { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' } });
    if (url.includes('/api/auth/me')) return payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.includes('/api/dashboard/summary')) return payload({ metrics: {}, charts: {} });
    if (url.includes('/api/reports/center')) return payload({ ok: true, widget: 'center', data: { groups: [{ key: 'gis', title: 'GIS reports', icon: 'fa-map-location-dot', description: 'GPS reports', types: ['gis'] }, { key: 'profile', title: 'Digital profile reports', icon: 'fa-folder-open', description: 'Profile reports', types: ['digital-profile'] }], templates: [{ key: 'household-list', title: 'Household list', type: 'household' }], filters: [], exports: ['preview', 'print', 'pdf', 'excel', 'word'] } });
    if (url.includes('/api/reports/bi')) return payload({ ok: true, widget: 'bi', data: { metrics: { total_households: 3, total_citizens: 9, male_count: 4, female_count: 5, poor_households: 1 }, charts: { population: [{ label: 'Nam', value: 4 }, { label: 'Nu', value: 5 }], age: [{ label: '18-59', value: 6 }], occupation: [{ label: 'Worker', value: 3 }], labor: [{ label: 'Employed', value: 5 }], poverty: [{ label: 'Poor', value: 1 }], monthlyMovements: [{ label: '2026-07', value: 2 }] }, progress: [{ key: 'gps', progress: { percent: 80 } }], generatedAt: new Date().toISOString() } });
    if (url.includes('/api/reports/templates')) return payload({ ok: true, widget: 'templates', data: [] });
    if (url.includes('/api/reports/summary')) return payload({ title: 'Smart report preview', headers: ['Metric', 'Value'], rows: [['Households', 3]], totalRows: 1, generatedAt: new Date().toISOString() });
    if (url.includes('/api/reports/print')) return payload({ title: 'Smart report preview', headers: ['Metric', 'Value'], rows: [['Households', 3]], totalRows: 1 });
    return payload({});
  });

  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };
    window.App.token = 'test-token';
    window.App.csrfToken = 'test-csrf';
    window.App.user = user;
    localStorage.setItem('thon09_token', 'test-token');
    localStorage.setItem('thon09_csrf', 'test-csrf');
    localStorage.setItem('thon09_user', JSON.stringify(user));
    if (typeof window.showApp === 'function') window.showApp();
    if (typeof window.switchScreen === 'function') window.switchScreen('reports');
  });

  await expect(page.locator('#reportsScreen')).toHaveClass(/active/);
  await expect(page.locator('#reportGroupGrid')).toContainText('GIS reports');
  await expect(page.locator('#reportBiKpis')).toContainText('3');
  await page.locator('#reportTypeSelect').selectOption('gis');
  await page.locator('#reportForm').evaluate(form => form.requestSubmit());
  await expect(page.locator('#reportPreview')).toContainText('Smart report preview');
  await expect(page.locator('#reportWordBtn')).toBeVisible();
  expect(consoleErrors).toEqual([]);
});


test('mobile bottom navigation does not cover module content', async ({ page }) => {
  await page.route('**/api/**', async (route) => {
    const url = route.request().url();
    const payload = (data) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) });
    if (url.includes('/api/public/login-config')) return payload(loginConfig.data);
    if (url.includes('/api/auth/me')) return payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.includes('/api/dashboard/summary')) return payload({ metrics: {}, charts: {} });
    if (url.includes('/api/reports/center')) return payload({ groups: [], templates: [], filters: [], exports: [] });
    if (url.includes('/api/reports/bi')) return payload({ metrics: {}, charts: {}, progress: [] });
    if (url.includes('/api/reports/templates')) return payload([]);
    return payload({});
  });

  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };
    window.App.token = 'test-token';
    window.App.csrfToken = 'test-csrf';
    window.App.user = user;
    localStorage.setItem('thon09_token', 'test-token');
    localStorage.setItem('thon09_csrf', 'test-csrf');
    localStorage.setItem('thon09_user', JSON.stringify(user));
    if (typeof window.showApp === 'function') window.showApp();
  });

  const viewport = page.viewportSize();
  if (!viewport || viewport.width > 820) return;

  const nav = page.locator('.mobile-bottom-nav');
  await expect(nav).toBeVisible();
  await expect(nav).toHaveCSS('position', 'fixed');

  const screens = [
    ['dashboard', '#dashboardScreen'],
    ['operationCenter', '#operationCenterScreen'],
    ['households', '#householdsScreen'],
    ['persons', '#personsScreen'],
    ['gis', '#gisScreen'],
    ['reports', '#reportsScreen']
  ];

  for (const [screen, selector] of screens) {
    await page.evaluate((name) => window.switchScreen && window.switchScreen(name), screen);
    await expect(page.locator(selector)).toHaveClass(/active/);
    const paddingBottom = await page.locator(selector).evaluate((el) => parseFloat(getComputedStyle(el).paddingBottom));
    expect(paddingBottom).toBeGreaterThanOrEqual(100);
  }
});
