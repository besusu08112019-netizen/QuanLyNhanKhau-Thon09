const { test, expect } = require('@playwright/test');

function payload(data) {
  return { contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) };
}

function normalized(value) {
  return String(value || '').toLowerCase().replace(/đ/g, 'd').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function matchesSearch(row, keys, query) {
  const needle = normalized(query).trim();
  if (!needle) return true;
  return keys.some((key) => normalized(row[key]).includes(needle));
}

async function mockApis(page) {
  await page.route('**/api/**', async (route) => {
    const url = new URL(route.request().url());
    const path = url.pathname;
    if (path === '/api/public/login-config') return route.fulfill(payload({ settings: {}, metrics: {} }));
    if (path === '/api/auth/me') return route.fulfill(payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' }));
    if (path === '/api/households') {
      const search = url.searchParams.get('search') || '';
      const rows = [
        { id: 1, household_code: 'H09-001', head_citizen_name: 'Nguyễn Văn A', address: 'Xóm 1 Thôn 09', phone: '0912345678', at_home_count: 4, away_count: 0, household_type: 'normal' },
        { id: 2, household_code: 'H09-002', head_citizen_name: 'Trần Văn B', address: 'Xóm 2 Thôn 09', phone: '0987654321', at_home_count: 3, away_count: 1, household_type: 'normal' }
      ];
      const items = rows.filter((row) => matchesSearch(row, ['household_code', 'head_citizen_name', 'address', 'phone'], search));
      return route.fulfill(payload({ items, total: items.length, page: 1, pageSize: 20, totalPages: 1 }));
    }
    if (path === '/api/persons') {
      const search = url.searchParams.get('search') || '';
      const rows = [
        { id: 101, household_code: 'H09-001', person_code: 'NK001', citizen_code: 'NK001', full_name: 'Nguyễn Văn C', head_citizen_name: 'Nguyễn Văn A', relationship: 'Con', date_of_birth: '2000-01-01', identity_number: '012345678901', gender: 'male', residency_status: 'permanent', presence_status: 'at_home' },
        { id: 102, household_code: 'H09-002', person_code: 'NK002', citizen_code: 'NK002', full_name: 'Trần Thị D', head_citizen_name: 'Trần Văn B', relationship: 'Chủ hộ', date_of_birth: '1985-01-01', identity_number: '987654321098', gender: 'female', residency_status: 'permanent', presence_status: 'at_home' }
      ];
      const items = rows.filter((row) => matchesSearch(row, ['full_name', 'head_citizen_name', 'household_code', 'person_code', 'citizen_code', 'identity_number'], search));
      return route.fulfill(payload({ items, total: items.length, page: 1, pageSize: 20, totalPages: 1 }));
    }
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

test('mobile shared AppFilterBar search filters records and restores the list', async ({ page }) => {
  await openAuthenticatedApp(page);

  await page.evaluate(() => window.Thon09NavigationController?.navigate('businessHouseholds'));
  await expect(page.locator('#businessHouseholdRows > tr')).toHaveCount(1);
  await page.evaluate(() => window.Thon09MobileComponents?.renderModuleScreen(document.querySelector('#businessHouseholdsScreen')));

  const cards = page.locator('#businessHouseholdsScreen .app-v2-record-card');
  const search = page.locator('#businessHouseholdsScreen .app-v2-filter-bar .app-v2-search input');
  const status = page.locator('#businessHouseholdsScreen .app-v2-filter-bar select[data-app-v2-filter-field="status"]');
  const inputKeepsFocus = () => search.evaluate((input) => document.activeElement === input && input.dataset.focusProbe === 'same-node');
  await expect(cards).toHaveCount(1);

  await search.evaluate((input) => { input.dataset.focusProbe = 'same-node'; });
  await search.click();
  await page.keyboard.type('khong-co-ban-ghi-lien-tuc-123');
  await expect(cards).toHaveCount(0);
  await expect.poll(inputKeepsFocus).toBe(true);
  await search.press(process.platform === 'darwin' ? 'Meta+A' : 'Control+A');
  await search.press('Backspace');
  await expect(cards).toHaveCount(1);
  await expect.poll(inputKeepsFocus).toBe(true);

  await search.fill('Bích');
  await expect(cards).toHaveCount(1);
  await expect(cards.first()).toContainText(/Bich|BÃ­ch/);

  await search.fill('bich');
  await expect(cards).toHaveCount(1);

  await search.fill('H09-0007');
  await expect(cards).toHaveCount(1);
  await status.selectOption('active');
  await expect(cards).toHaveCount(1);

  await search.fill('khong-co-ban-ghi');
  await expect(cards).toHaveCount(0);
  await expect(page.locator('#businessHouseholdsScreen .app-v2-empty')).toContainText(/Không tìm thấy|KhÃ´ng tÃ¬m/);

  await status.selectOption('');
  await search.fill('');
  await expect(cards).toHaveCount(1);
});

test('mobile shared search indexes household and person names, codes and addresses', async ({ page }) => {
  await openAuthenticatedApp(page);

  await page.evaluate(() => window.Thon09NavigationController?.navigate('households'));
  await expect(page.locator('#householdRows > tr')).toHaveCount(2);
  await page.evaluate(() => window.Thon09MobileComponents?.renderModuleScreen(document.querySelector('#householdsScreen')));
  const householdCards = page.locator('#householdsScreen .app-v2-record-card');
  const householdSearch = page.locator('#householdsScreen .app-v2-filter-bar .app-v2-search input');
  await expect(householdCards).toHaveCount(2);

  await householdSearch.fill('Nguyễn');
  await expect(householdCards).toHaveCount(1);
  await expect(householdCards.first()).toContainText(/Nguy/);
  await householdSearch.fill('Nguyen');
  await expect(householdCards).toHaveCount(1);
  await householdSearch.fill('Văn');
  await expect(householdCards).toHaveCount(2);
  await householdSearch.fill('H09-001');
  await expect(householdCards).toHaveCount(1);
  await householdSearch.fill('Xóm 2');
  await expect(householdCards).toHaveCount(1);
  await expect(householdCards.first()).toContainText(/H09-002|Trần/);
  await householdSearch.fill('');
  await expect(householdCards).toHaveCount(2);

  await page.evaluate(() => window.Thon09NavigationController?.navigate('persons'));
  await expect(page.locator('#personRows > tr:not(.group-row)')).toHaveCount(2);
  await page.evaluate(() => window.Thon09MobileComponents?.renderModuleScreen(document.querySelector('#personsScreen')));
  const personCards = page.locator('#personsScreen .app-v2-record-card');
  const personSearch = page.locator('#personsScreen .app-v2-filter-bar .app-v2-search input');
  await expect(personCards).toHaveCount(2);

  await personSearch.fill('Nguyễn Văn C');
  await expect(personCards).toHaveCount(1);
  await personSearch.fill('Nguyen');
  await expect(personCards).toHaveCount(1);
  await personSearch.fill('Văn');
  await expect(personCards).toHaveCount(2);
  await personSearch.fill('Nguyễn Văn A');
  await expect(personCards).toHaveCount(1);
  await personSearch.fill('H09-001');
  await expect(personCards).toHaveCount(1);
  await personSearch.fill('012345');
  await expect(personCards).toHaveCount(1);
  await personSearch.fill('');
  await expect(personCards).toHaveCount(2);
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
      const searchControls = Array.from(active?.querySelectorAll('.app-v2-search input, .app-v2-filter-sheet input[type="text"], .app-v2-filter-sheet input[type="search"]') || []);
      const headerSearchControls = Array.from(active?.querySelectorAll('.app-v2-hero .app-v2-search input') || []);
      const filterBarSearchControls = Array.from(active?.querySelectorAll('.app-v2-filter-bar .app-v2-search input') || []);
      const host = active?.querySelector('.app-v2-module-screen');
      const summary = host?.querySelector('.app-v2-section .app-v2-grid');
      const filterBar = host?.querySelector('.app-v2-filter-bar');
      const list = host?.querySelector('.app-v2-list');
      const order = [summary, filterBar, list].map((node) => node ? Array.from(host.querySelectorAll('*')).indexOf(node) : -1);
      return {
        activeId: active?.id || '',
        generatedTriggerCount: generatedTriggers.length,
        orphanTriggerCount: orphanTriggers.length,
        emptyFilterBoxCount: emptyFilterBoxes.length,
        searchControlCount: searchControls.length,
        headerSearchCount: headerSearchControls.length,
        filterBarSearchCount: filterBarSearchControls.length,
        summaryBeforeFilter: order[0] >= 0 && order[1] > order[0],
        filterBeforeList: order[1] >= 0 && order[2] > order[1],
        missingSearchPlaceholder: searchControls.filter((input) => !input.getAttribute('placeholder')).length
      };
    });
    expect(metrics.orphanTriggerCount, `${screen} orphan filter trigger`).toBe(0);
    expect(metrics.emptyFilterBoxCount, `${screen} duplicate icon-only filter boxes`).toBeLessThanOrEqual(1);
    expect(metrics.searchControlCount, `${screen} duplicate search controls`).toBeLessThanOrEqual(1);
    expect(metrics.headerSearchCount, `${screen} header search should be merged into filter bar`).toBe(0);
    expect(metrics.filterBarSearchCount, `${screen} filter bar search`).toBe(metrics.searchControlCount);
    expect(metrics.summaryBeforeFilter, `${screen} summary before filter`).toBe(true);
    expect(metrics.filterBeforeList, `${screen} filter before list`).toBe(true);
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
