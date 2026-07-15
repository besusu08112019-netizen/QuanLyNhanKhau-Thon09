const { test, expect } = require('@playwright/test');

function payload(data) {
  return { contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) };
}

async function mockApis(page) {
  await page.route('**/api/**', async (route) => {
    const url = new URL(route.request().url());
    const path = url.pathname;
    if (path === '/api/public/login-config') return route.fulfill(payload({ settings: {}, metrics: {} }));
    if (path === '/api/auth/me') return route.fulfill(payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' }));
    if (path === '/api/household-business/catalogs') return route.fulfill(payload({ economic_type: [], business_scale: [], image_category: [], document_category: [] }));
    if (path === '/api/household-business') return route.fulfill(payload({
      items: [{
        id: 11,
        household_id: 11,
        household_code: 'H09-0007',
        head_citizen_name: 'PHAM VAN BICH',
        business_count: 1,
        activities: [{
          business_name: 'Co so moc Bich',
          business_type_label: 'Ho kinh doanh',
          sector_label: 'Moc dan dung',
          status: 'ACTIVE',
          status_label: 'Dang hoat dong'
        }]
      }],
      total: 1,
      page: 1,
      pageSize: 20,
      totalPages: 1,
      dashboard: {}
    }));
    if (path === '/api/contributions/catalogs') return route.fulfill(payload({
      campaign_statuses: [],
      payment_statuses: [],
      payment_methods: [],
      unit_types: [],
      category_statuses: [],
      collection_cycles: [],
      target_options: [],
      exemption_options: []
    }));
    if (path === '/api/contributions/categories') return route.fulfill(payload({
      items: [{ id: 1, name: 'Quy thon', code: 'QT', status: 'ACTIVE', status_label: 'Dang ap dung', campaign_count: 1 }],
      total: 1
    }));
    if (path === '/api/contributions/dashboard') return route.fulfill(payload({ metrics: {}, charts: {} }));
    if (path === '/api/contributions') return route.fulfill(payload({
      items: [{
        id: 21,
        category_id: 1,
        category_name: 'Quy thon',
        category_code: 'QT',
        contribution_name: 'Quy thon 2026',
        period_name: 'Nam 2026',
        year: 2026,
        unit_type_label: 'Theo ho',
        paid_households: 12,
        unpaid_households: 97,
        expected_total: 10900000,
        collected_amount: 1200000
      }],
      total: 1,
      page: 1,
      pageSize: 20,
      totalPages: 1
    }));
    if (path.includes('/api/dashboard') || path.includes('/api/gis')) return route.fulfill(payload({ items: [], total: 0, metrics: {}, charts: {} }));
    return route.fulfill(payload({ items: [], total: 0, page: 1, pageSize: 20, totalPages: 1 }));
  });
}

async function openAuthenticatedApp(page) {
  await page.setViewportSize({ width: 390, height: 844 });
  await mockApis(page);
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };
    App.token = 'test-token';
    App.csrfToken = 'test-csrf';
    App.user = user;
    localStorage.setItem('thon09_token', 'test-token');
    localStorage.setItem('thon09_csrf', 'test-csrf');
    localStorage.setItem('thon09_user', JSON.stringify(user));
    if (typeof window.showApp === 'function') window.showApp();
  });
  await expect(page.locator('#appView')).not.toHaveClass(/d-none/);
}

async function dedupMetrics(page, screenId, tbodyId, pagerId) {
  return page.evaluate(({ screenId, tbodyId, pagerId }) => {
    const screen = document.querySelector('#' + screenId);
    const tbody = document.querySelector('#' + tbodyId);
    const pager = document.querySelector('#' + pagerId);
    const wrapper = tbody?.closest('.table-responsive');
    const firstCard = tbody?.querySelector(':scope > tr.mobile-source-card');
    const search = screen?.querySelector('.mobile-filter-system .mobile-search-control');
    const filterTrigger = screen?.querySelector('.mobile-filter-trigger');
    return {
      sourceRows: tbody ? tbody.querySelectorAll(':scope > tr').length : 0,
      decoratedRows: tbody ? tbody.querySelectorAll(':scope > tr.mobile-source-card').length : 0,
      generatedSurfaces: screen ? screen.querySelectorAll('.mobile-list-surface').length : 0,
      generatedCards: screen ? screen.querySelectorAll('.mobile-list-surface .mobile-list-card').length : 0,
      nestedTables: wrapper ? wrapper.querySelectorAll(':scope > table').length : 0,
      pagers: screen ? screen.querySelectorAll('#' + pagerId).length : 0,
      pagerChildren: pager ? pager.children.length : 0,
      pagerSystem: pager ? pager.classList.contains('mobile-pager-system') : false,
      title: firstCard?.dataset.mobileTitle || '',
      code: firstCard?.dataset.mobileCode || '',
      searchPlaceholder: search?.getAttribute('placeholder') || '',
      searchVisible: search ? getComputedStyle(search).display !== 'none' && search.getBoundingClientRect().width > 0 : false,
      filterTriggerVisible: filterTrigger ? getComputedStyle(filterTrigger).display !== 'none' : false
    };
  }, { screenId, tbodyId, pagerId });
}

test('mobile render keeps one source row for business households and contributions', async ({ page }) => {
  await openAuthenticatedApp(page);

  await page.evaluate(() => window.Thon09NavigationController?.navigate('businessHouseholds'));
  await expect(page.locator('#businessHouseholdRows > tr')).toHaveCount(1);
  await expect.poll(() => dedupMetrics(page, 'businessHouseholdsScreen', 'businessHouseholdRows', 'businessHouseholdPager')).toMatchObject({
    sourceRows: 1,
    decoratedRows: 1,
    generatedSurfaces: 0,
    generatedCards: 0,
    nestedTables: 1,
    pagers: 1,
    pagerSystem: true,
    title: 'PHAM VAN BICH',
    code: 'H09-0007',
    searchVisible: true,
    filterTriggerVisible: true
  });
  const businessMetrics = await dedupMetrics(page, 'businessHouseholdsScreen', 'businessHouseholdRows', 'businessHouseholdPager');
  expect(businessMetrics.title).not.toContain(businessMetrics.code);
  expect(businessMetrics.searchPlaceholder.length).toBeGreaterThan(0);

  await page.evaluate(() => window.Thon09NavigationController?.navigate('contributions'));
  await expect(page.locator('#contributionsRows > tr')).toHaveCount(1);
  await expect.poll(() => dedupMetrics(page, 'contributionsScreen', 'contributionsRows', 'contributionsPager')).toMatchObject({
    sourceRows: 1,
    decoratedRows: 1,
    generatedSurfaces: 0,
    generatedCards: 0,
    nestedTables: 1,
    pagers: 1,
    pagerSystem: true
  });
  await expect.poll(() => page.evaluate(() => document.querySelectorAll('#contributionStats > *').length)).toBeGreaterThanOrEqual(6);
  const contributionDashboard = await page.evaluate(() => {
    const grid = document.querySelector('#contributionsScreen .dashboard-kpi-grid');
    const firstCell = grid?.querySelector(':scope > *');
    const rect = firstCell?.getBoundingClientRect();
    return {
      cells: grid ? grid.children.length : 0,
      columns: grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').length : 0,
      cellHeight: rect ? Math.round(rect.height) : 0
    };
  });
  expect(contributionDashboard.cells).toBeGreaterThanOrEqual(6);
  expect(contributionDashboard.columns).toBe(2);
  expect(contributionDashboard.cellHeight).toBeLessThanOrEqual(64);
});
