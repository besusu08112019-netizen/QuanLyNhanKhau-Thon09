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
    window.Thon09MobileComponents?.renderModuleScreen(screen);
    const host = screen?.querySelector('.app-v2-module-screen');
    const firstCard = host?.querySelector('.app-v2-record-card');
    const search = host?.querySelector('.app-v2-search input');
    const filterSheet = host?.querySelector('.app-v2-filter-sheet');
    return {
      sourceRows: tbody ? tbody.querySelectorAll(':scope > tr').length : 0,
      decoratedRows: tbody ? tbody.querySelectorAll(':scope > tr.mobile-source-card').length : 0,
      generatedSurfaces: host ? 1 : 0,
      generatedCards: host ? host.querySelectorAll('.app-v2-record-card').length : 0,
      nestedTables: screen ? screen.querySelectorAll('.table-responsive table').length : 0,
      pagers: screen ? screen.querySelectorAll('#' + pagerId).length : 0,
      pagerChildren: pager ? pager.children.length : 0,
      pagerSystem: !!pager,
      title: firstCard?.querySelector('.app-v2-record-title')?.textContent?.trim() || '',
      code: firstCard?.querySelector('.app-v2-record-meta')?.textContent?.trim() || '',
      searchPlaceholder: search?.getAttribute('placeholder') || '',
      searchVisible: search ? getComputedStyle(search).display !== 'none' && search.getBoundingClientRect().width > 0 : false,
      filterTriggerVisible: filterSheet ? getComputedStyle(filterSheet).display !== 'none' : false
    };
  }, { screenId, tbodyId, pagerId });
}

test('mobile render keeps one source row for business households and contributions', async ({ page }) => {
  await openAuthenticatedApp(page);

  await page.evaluate(() => window.Thon09NavigationController?.navigate('businessHouseholds'));
  await expect(page.locator('#businessHouseholdRows > tr')).toHaveCount(1);
  await expect.poll(() => dedupMetrics(page, 'businessHouseholdsScreen', 'businessHouseholdRows', 'businessHouseholdPager')).toMatchObject({
    sourceRows: 1,
    decoratedRows: 0,
    generatedSurfaces: 1,
    generatedCards: 1,
    nestedTables: 1,
    pagers: 1,
    pagerSystem: true,
    searchVisible: true
  });
  const businessMetrics = await dedupMetrics(page, 'businessHouseholdsScreen', 'businessHouseholdRows', 'businessHouseholdPager');
  expect(businessMetrics.title.length).toBeGreaterThan(0);

  await page.evaluate(() => window.Thon09NavigationController?.navigate('contributions'));
  await expect(page.locator('#contributionsRows > tr')).toHaveCount(1);
  await expect.poll(() => dedupMetrics(page, 'contributionsScreen', 'contributionsRows', 'contributionsPager')).toMatchObject({
    sourceRows: 1,
    decoratedRows: 0,
    generatedSurfaces: 1,
    generatedCards: 1,
    nestedTables: 2,
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
  expect(contributionDashboard.cellHeight).toBeLessThanOrEqual(120);
});

test('mobile shared filters do not render duplicate icon-only controls and empty lists stay informative', async ({ page }) => {
  await openAuthenticatedApp(page);

  for (const screen of ['households', 'persons', 'movements', 'agriculture', 'livestock', 'businessHouseholds', 'contributions']) {
    await page.evaluate((name) => window.Thon09NavigationController?.navigate(name), screen);
    await page.waitForTimeout(150);
    const metrics = await page.evaluate(() => {
      const active = document.querySelector('.screen.active');
      window.Thon09MobileComponents?.renderModuleScreen(active);
      const filters = Array.from(active?.querySelectorAll('.app-v2-filter-sheet') || []);
      const generatedTriggers = Array.from(active?.querySelectorAll('.app-v2-toolbar .app-v2-chip') || []).filter((trigger) => /lọc|loc|filter/i.test(trigger.textContent || ''));
      const orphanTriggers = [];
      const emptyFilterBoxes = filters.filter((filter) => {
        const visibleChildren = Array.from(filter.children).filter((child) => {
          const style = getComputedStyle(child);
          const rect = child.getBoundingClientRect();
          return style.visibility !== 'hidden' && style.display !== 'none' && rect.width > 1 && rect.height > 1;
        });
        return visibleChildren.length === 0;
      });
      const searchControls = Array.from(active?.querySelectorAll('.app-v2-search input, .app-v2-filter-sheet input[type="text"]') || []);
      return {
        activeId: active?.id || '',
        generatedTriggerCount: generatedTriggers.length,
        orphanTriggerCount: orphanTriggers.length,
        emptyFilterBoxCount: emptyFilterBoxes.length,
        missingSearchPlaceholder: searchControls.filter((input) => !input.getAttribute('placeholder')).length
      };
    });
    expect(metrics.orphanTriggerCount, `${screen} orphan filter trigger`).toBe(0);
    expect(metrics.emptyFilterBoxCount, `${screen} duplicate icon-only filter boxes`).toBeLessThanOrEqual(1);
    expect(metrics.missingSearchPlaceholder, `${screen} search placeholder`).toBe(0);
    expect(metrics.generatedTriggerCount, `${screen} duplicate generated filter trigger`).toBeLessThanOrEqual(1);
  }

  await page.evaluate(() => window.Thon09NavigationController?.navigate('temporaryResidence'));
  await expect(page.locator('#temporaryResidenceRows table')).toHaveCount(1);
  await expect.poll(() => page.evaluate(() => {
    const screen = document.querySelector('#temporaryResidenceScreen');
    window.Thon09MobileComponents?.renderModuleScreen(screen);
    const row = document.querySelector('#temporaryResidenceScreen .app-v2-empty, #temporaryResidenceScreen .app-v2-record-card');
    return {
      exists: !!row,
      message: row?.textContent || '',
      height: row ? Math.round(row.getBoundingClientRect().height) : 0
    };
  })).toMatchObject({
    exists: true
  });
  const emptyState = await page.evaluate(() => {
    const visibleEmpty = document.querySelector('#temporaryResidenceScreen .app-v2-empty, #temporaryResidenceScreen .app-v2-record-card');
    return {
      message: visibleEmpty?.textContent || '',
      height: visibleEmpty ? Math.round(visibleEmpty.getBoundingClientRect().height) : 0
    };
  });
  expect(emptyState.message.length).toBeGreaterThan(0);
  expect(emptyState.height).toBeGreaterThanOrEqual(54);
});

test('mobile shared component shells avoid blank panels, vertical toolbars and oversized whitespace', async ({ page }) => {
  await openAuthenticatedApp(page);

  const screens = ['households', 'persons', 'movements', 'vehicles', 'businessHouseholds', 'agriculture', 'livestock', 'contributions'];
  for (const screen of screens) {
    await page.evaluate((name) => window.Thon09NavigationController?.navigate(name), screen);
    await page.waitForTimeout(180);
    const metrics = await page.evaluate(() => {
      window.Thon09MobileUiSystem?.enhance(document);
      const active = document.querySelector('.screen.active');
      window.Thon09MobileComponents?.renderModuleScreen(active);
      const visible = (el) => {
        const style = getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 1 && rect.height > 1;
      };
      const shells = Array.from(active?.querySelectorAll('.app-v2-filter-sheet, .app-v2-action-row, .app-v2-toolbar') || []).filter(visible);
      const shellIssues = shells.map((shell) => {
        const rect = shell.getBoundingClientRect();
        const controls = Array.from(shell.querySelectorAll('input, select, button, a[href], .btn')).filter(visible);
        const textLength = shell.innerText.replace(/\s+/g, ' ').trim().length;
        const onlyIcon = controls.length === 1 && textLength <= 3;
        return {
          className: shell.className,
          height: Math.round(rect.height),
          controls: controls.length,
          textLength,
          onlyIcon,
          blankTall: rect.height > 96 && controls.length <= 1
        };
      });
      const actionGroups = Array.from(active?.querySelectorAll('.app-v2-action-row') || []).filter(visible).map((group) => {
        const buttons = Array.from(group.querySelectorAll('button, a[href], .btn, select')).filter(visible);
        const rects = buttons.map((button) => button.getBoundingClientRect());
        const columns = rects.length ? new Set(rects.map((rect) => Math.round(rect.left))).size : 0;
        const rows = rects.length ? new Set(rects.map((rect) => Math.round(rect.top))).size : 0;
        return { buttons: buttons.length, columns, rows, height: Math.round(group.getBoundingClientRect().height) };
      });
      return {
        activeId: active?.id || '',
        shellIssues,
        verticalActionGroups: actionGroups.filter((group) => group.buttons >= 3 && group.columns <= 1 && group.rows >= 3)
      };
    });

    expect(metrics.shellIssues.filter((item) => item.onlyIcon && !/app-v2-toolbar/.test(item.className)), `${screen} icon-only filter/action shell`).toEqual([]);
    expect(metrics.shellIssues.filter((item) => item.blankTall), `${screen} blank tall filter/action shell`).toEqual([]);
    expect(metrics.verticalActionGroups, `${screen} vertical toolbar`).toEqual([]);
  }
});
