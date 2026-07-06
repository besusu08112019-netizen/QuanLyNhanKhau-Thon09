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


test('mobile overlays hide bottom navigation and keep actions reachable', async ({ page }) => {
  await page.route('**/api/**', async (route) => {
    const url = route.request().url();
    const payload = (data) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) });
    if (url.includes('/api/public/login-config')) return payload(loginConfig.data);
    if (url.includes('/api/auth/me')) return payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.includes('/api/dashboard/summary')) return payload({ metrics: {}, charts: {} });
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
    if (typeof window.switchScreen === 'function') window.switchScreen('households');
  });

  const viewport = page.viewportSize();
  if (!viewport || viewport.width > 1024) return;

  const nav = page.locator('.mobile-bottom-nav');
  await expect(nav).toBeVisible();

  await page.evaluate(() => {
    let host = document.querySelector('[data-test-mobile-sheet-host]');
    if (!host) {
      host = document.createElement('div');
      host.dataset.testMobileSheetHost = '1';
      host.className = 'mobile-filter-open';
      host.innerHTML = '<div class="mobile-filter-sheet"><div class="mobile-filter-sheet-head"><strong>Filter</strong><button class="mobile-filter-close" type="button">Close</button></div><div class="mobile-filter-sheet-body"><input class="form-control"><button class="btn btn-primary">Apply</button></div></div>';
      document.body.appendChild(host);
    }
    document.body.classList.add('mobile-filter-active');
  });

  await expect(nav).toBeHidden();
  const sheetMetrics = await page.locator('[data-test-mobile-sheet-host] .mobile-filter-sheet').evaluate((el) => {
    const style = getComputedStyle(el);
    return { zIndex: Number(style.zIndex), maxHeight: style.maxHeight, overflow: getComputedStyle(el.querySelector('.mobile-filter-sheet-body')).overflowY };
  });
  expect(sheetMetrics.zIndex).toBeGreaterThan(42);
  expect(sheetMetrics.maxHeight).toContain('px');
  expect(sheetMetrics.overflow).toBe('auto');

  await page.evaluate(() => {
    document.body.classList.remove('mobile-filter-active');
    document.querySelector('[data-test-mobile-sheet-host]')?.remove();
  });
  await expect(nav).toBeVisible();

  await page.evaluate(() => {
    const modal = document.getElementById('householdModal');
    window.bootstrap.Modal.getOrCreateInstance(modal).show();
  });
  await expect(page.locator('#householdModal')).toHaveClass(/show/);
  await expect(nav).toBeHidden();
  await expect(page.locator('#householdModal .modal-footer')).toBeVisible();

  const modalMetrics = await page.locator('#householdModal .modal-content').evaluate((el) => {
    const style = getComputedStyle(el);
    const body = el.querySelector('.modal-body');
    return { maxHeight: style.maxHeight, display: style.display, bodyOverflow: getComputedStyle(body).overflowY };
  });
  expect(modalMetrics.maxHeight).toContain('px');
  expect(modalMetrics.display).toBe('grid');
  expect(modalMetrics.bodyOverflow).toBe('auto');

  await page.evaluate(() => {
    const modal = document.getElementById('householdModal');
    window.bootstrap.Modal.getInstance(modal)?.hide();
    modal.classList.remove('show');
    modal.style.display = 'none';
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
  });
});


test('mobile FAB stays above bottom navigation and hides under overlays', async ({ page }) => {
  await page.route('**/api/**', async (route) => {
    const url = route.request().url();
    const payload = (data) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) });
    if (url.includes('/api/public/login-config')) return payload(loginConfig.data);
    if (url.includes('/api/auth/me')) return payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.includes('/api/dashboard/summary')) return payload({ metrics: {}, charts: {} });
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

  await expect(page.locator('.mobile-bottom-nav')).toBeVisible();

  const assertFabClearance = async (screen, rowSelector, buttonSelector) => {
    await page.evaluate((name) => window.switchScreen && window.switchScreen(name), screen);
    await expect(page.locator(rowSelector)).toBeVisible();
    await expect(page.locator(buttonSelector)).toBeVisible();

    const metrics = await page.evaluate(({ rowSelector: rowSel, buttonSelector: buttonSel }) => {
      const nav = document.querySelector('.mobile-bottom-nav');
      const row = document.querySelector(rowSel);
      const button = document.querySelector(buttonSel);
      const root = document.documentElement;
      if (!nav || !row || !button) return null;
      const navRect = nav.getBoundingClientRect();
      const rowRect = row.getBoundingClientRect();
      const buttonRect = button.getBoundingClientRect();
      return {
        navTop: navRect.top,
        rowBottom: rowRect.bottom,
        buttonBottom: buttonRect.bottom,
        rowZ: Number(getComputedStyle(row).zIndex),
        navZ: Number(getComputedStyle(nav).zIndex),
        clearance: root.style.getPropertyValue('--mobile-bottom-nav-clearance')
      };
    }, { rowSelector, buttonSelector });

    expect(metrics).not.toBeNull();
    expect(metrics.buttonBottom).toBeLessThanOrEqual(metrics.navTop - 8);
    expect(metrics.rowBottom).toBeLessThanOrEqual(metrics.navTop - 8);
    expect(metrics.rowZ).toBeGreaterThan(metrics.navZ);
    expect(metrics.clearance).toContain('px');
  };

  await assertFabClearance('households', '#householdsScreen .module-action-row', '#householdAddBtn');
  await assertFabClearance('persons', '#personsScreen .module-action-row', '#personAddBtn');

  await page.evaluate(() => {
    if (typeof window.switchScreen === 'function') window.switchScreen('households');
    document.body.classList.add('modal-open');
  });
  await expect(page.locator('#householdsScreen .module-action-row')).toBeHidden();

  await page.evaluate(() => {
    document.body.classList.remove('modal-open');
    document.body.classList.add('mobile-filter-active');
  });
  await expect(page.locator('#householdsScreen .module-action-row')).toBeHidden();

  await page.evaluate(() => document.body.classList.remove('mobile-filter-active'));
});


test('system administration center renders independent operation widgets', async ({ page }) => {
  const consoleErrors = [];
  page.on('console', msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
  await page.route('**/api/**', async (route) => {
    const url = route.request().url();
    const payload = (data) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) });
    if (url.includes('/api/public/login-config')) return payload(loginConfig.data);
    if (url.includes('/api/auth/me')) return payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.includes('/api/dashboard/summary')) return payload({ metrics: {}, charts: {} });
    if (url.includes('/api/system-admin/overview')) return payload({ system: { version: 'test-v17', databaseVersion: '8.0', uptime: 'Load 0.1', generatedAt: new Date().toISOString() }, counts: { users: 2, households: 3, citizens: 9, digitalProfiles: 4, documents: 5, images: 6, videos: 1 }, storage: { uploads: { label: '10 MB' } } });
    if (url.includes('/api/system-admin/health')) return payload({ summary: { ok: 3, warning: 0, error: 0 }, checks: [{ label: 'Database connection', status: 'ok', message: 'OK' }, { label: 'API health', status: 'ok', message: 'OK' }] });
    if (url.includes('/api/system-admin/sessions')) return payload({ items: [{ id: 1, email: 'admin@example.test', display_name: 'Admin Test', device: 'Desktop', browser: 'Chrome', ip_address: '127.0.0.1', status: 'ACTIVE', created_at: new Date().toISOString() }], total: 1 });
    if (url.includes('/api/system-admin/memory')) return payload({ items: [{ key: 'cache', label: 'Cache', stats: { label: '1 KB', files: 1 } }, { key: 'sessions', label: 'Expired sessions', stats: { label: '0 sessions', expired: 0 } }] });
    if (url.includes('/api/system-admin/performance')) return payload({ metrics: [{ label: 'Database response', value: 12, unit: 'ms' }], recommendations: ['Watch APIs over 500ms'] });
    if (url.includes('/api/system-admin/security')) return payload({ checks: [{ label: 'CSRF', status: 'ok', message: 'Enabled' }, { label: 'SQL Injection', status: 'ok', message: 'Prepared statements' }] });
    if (url.includes('/api/system-admin/configuration')) return payload({ settings: { systemName: 'Test System', hamletName: 'Hamlet 09', communeName: 'Hong Phong' }, timezone: 'Asia/Bangkok' });
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
    if (typeof window.switchScreen === 'function') window.switchScreen('systemAdmin');
  });

  await expect(page.locator('#systemAdminScreen')).toHaveClass(/active/);
  await expect(page.locator('[data-screen="systemAdmin"]')).toBeVisible();
  await expect(page.locator('#systemAdminOverview')).toContainText('test-v17');
  await expect(page.locator('#systemAdminHealth')).toContainText('Database connection');
  await expect(page.locator('#systemAdminSessions')).toContainText('Admin Test');
  await expect(page.locator('#systemAdminMemory')).toContainText('Cache');
  await expect(page.locator('#systemAdminSecurity')).toContainText('CSRF');
  await expect(page.locator('#systemAdminConfig')).toContainText('Test System');
  expect(consoleErrors).toEqual([]);
});


test('viewer role is read-only across household and citizen modules', async ({ page }) => {
  const writeRequests = [];
  await page.route('**/api/**', async (route) => {
    const request = route.request();
    const url = request.url();
    const method = request.method();
    const payload = (data) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) });

    if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && !url.includes('/api/auth/')) {
      writeRequests.push({ method, url });
      await route.fulfill({ status: 403, contentType: 'application/json', body: JSON.stringify({ ok: false, success: false, error: { message: 'Forbidden' } }) });
      return;
    }

    if (url.includes('/api/public/login-config')) return payload(loginConfig.data);
    if (url.includes('/api/auth/me')) return payload({ id: 3, email: 'viewer@example.test', displayName: 'Viewer Test', role: 'VIEWER', status: 'ACTIVE' });
    if (url.includes('/api/dashboard/summary')) return payload({ metrics: {}, charts: {} });
    if (url.includes('/api/persons')) return payload({ items: [{ id: 11, household_code: 'HK001', citizen_code: 'NK001', full_name: 'Viewer Citizen', relationship: 'Ch? h?', date_of_birth: '1990-01-01', gender: 'Nam', identity_number: '123456789012', residency_status: 'PERMANENT', presence_status: 'AT_HOME', party_member: 0 }], total: 1, page: 1, pageSize: 20 });
    if (url.includes('/api/households')) return payload({ items: [{ id: 21, household_code: 'HK001', head_citizen_name: 'Viewer Head', address: 'Address', at_home_count: 1, away_count: 0, status: 'ACTIVE' }], total: 1, page: 1, pageSize: 20 });
    return payload({});
  });

  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    const user = { id: 3, email: 'viewer@example.test', displayName: 'Viewer Test', role: 'VIEWER', status: 'ACTIVE' };
    window.App.token = 'viewer-token';
    window.App.csrfToken = 'viewer-csrf';
    window.App.user = user;
    localStorage.setItem('thon09_token', 'viewer-token');
    localStorage.setItem('thon09_csrf', 'viewer-csrf');
    localStorage.setItem('thon09_user', JSON.stringify(user));
    if (typeof window.showApp === 'function') window.showApp();
    if (typeof window.switchScreen === 'function') window.switchScreen('persons');
  });

  await expect(page.locator('#personsScreen')).toHaveClass(/active/);
  await expect(page.locator('#personRows')).toContainText('Viewer Citizen');
  await expect(page.locator('#personAddBtn')).toBeHidden();
  await expect(page.locator('#personBulkDeleteBtn')).toBeHidden();
  await expect(page.locator('#personRows [onclick^="openPersonForm"], #personRows [onclick^="deletePerson"], #personRows .person-check')).toHaveCount(0);

  await page.evaluate(() => window.switchScreen && window.switchScreen('households'));
  await expect(page.locator('#householdsScreen')).toHaveClass(/active/);
  await expect(page.locator('#householdRows')).toContainText('HK001');
  await expect(page.locator('#householdAddBtn')).toBeHidden();
  await expect(page.locator('#householdBulkDeleteBtn')).toBeHidden();
  await expect(page.locator('#householdRows [onclick^="openHouseholdForm"], #householdRows [onclick^="deleteHousehold"], #householdRows .household-check')).toHaveCount(0);
  await expect(page.locator('.sidebar .nav-link[data-screen="users"]')).toBeHidden();
  await expect(page.locator('.sidebar .nav-link[data-screen="backups"]')).toBeHidden();

  await page.evaluate(async () => {
    await window.openPersonForm?.(11);
    await window.deletePerson?.(11);
    await window.openHouseholdForm?.(21);
    await window.deleteHousehold?.(21);
  });
  await page.waitForTimeout(100);
  expect(writeRequests).toEqual([]);
});
