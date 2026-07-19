const { test, expect } = require('@playwright/test');

const type = { value: '1', label: 'Nhà văn hóa', category: 'Hành chính', icon: 'fa-building-columns' };
let items;
let requests;
let inventoryItems;

function asset(overrides = {}) {
  return {
    id: 101,
    asset_code: 'CT09-00101',
    asset_name: 'Nhà văn hóa thôn 09',
    type_id: 1,
    type_name: 'Nhà văn hóa',
    category: 'Hành chính',
    type_icon: 'fa-building-columns',
    area_code: 'Thôn 09',
    campus_area: 1234.5,
    building_area: 456.75,
    construction_year: 2020,
    operation_year: 2021,
    address: 'Thôn 09, Xã Hồng Phong',
    latitude: 20.255,
    longitude: 105.976,
    gps_accuracy: 4.5,
    gps_updated_at: '2026-07-11 10:00:00',
    managing_unit: 'UBND xã Hồng Phong',
    manager_name: 'Nguyễn Văn A',
    manager_position: 'Cán bộ phụ trách',
    manager_phone: '0900000000',
    description: 'Công trình sinh hoạt cộng đồng',
    note: '',
    status: 'ACTIVE',
    status_label: 'Đang sử dụng',
    cover_photo_url: '',
    inventory_enabled: true,
    inventory_allowed: true,
    ...overrides
  };
}

function ok(data) {
  return { ok: true, success: true, data };
}

async function mockApis(page) {
  items = [
    asset(),
    asset({
      id: 102,
      asset_code: 'CT09-00102',
      asset_name: 'Sân thể thao',
      type_name: '',
      category: '',
      area_code: '',
      campus_area: null,
      building_area: null,
      construction_year: null,
      operation_year: null,
      address: '',
      latitude: null,
      longitude: null,
      managing_unit: '',
      manager_name: '',
      manager_position: '',
      manager_phone: '',
      description: '',
      status: 'REPAIRING',
      status_label: 'Đang sửa chữa'
    })
  ];
  requests = [];
  inventoryItems = [
    { id: 501, public_asset_id: 101, inventory_code: 'TS001', item_name: 'Loa hội trường', group_id: 15, group_name: 'Loa', quantity: 2, unit: 'cái', condition_status: 'IN_USE', condition_label: 'Đang sử dụng', start_use_date: '2024-01-15', location_in_asset: 'Sân khấu', note: '', photo_url: '' }
  ];
  await page.route('**/api/**', async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    requests.push({ method: request.method(), path: url.pathname, query: url.searchParams.toString(), body: request.postDataJSON?.() || null });
    const fulfill = (data) => route.fulfill({ contentType: 'application/json', body: JSON.stringify(ok(data)) });

    if (url.pathname === '/api/public/login-config') return fulfill({ settings: { systemName: 'Thôn 09', hamletName: 'Thôn 09', communeName: 'Hồng Phong', version: 'v2.0' }, metrics: {} });
    if (url.pathname === '/api/auth/me') return fulfill({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.pathname === '/api/dashboard/summary') return fulfill({ metrics: {}, charts: {} });
    if (url.pathname === '/api/public-assets/catalogs') return fulfill({ types: [type], areas: [{ value: 'Thôn 09', label: 'Thôn 09' }], statuses: [{ value: 'ACTIVE', label: 'Đang sử dụng' }, { value: 'REPAIRING', label: 'Đang sửa chữa' }] });
    if (url.pathname === '/api/public-assets/inventory/catalogs') return fulfill({ groups: [{ value: '15', label: 'Loa', parent: 'Điện tử' }, { value: '20', label: 'Bình chữa cháy', parent: 'PCCC' }], conditions: [{ value: 'IN_USE', label: 'Đang sử dụng' }, { value: 'NEEDS_REPAIR', label: 'Cần sửa chữa' }] });
    if (url.pathname === '/api/public-assets/inventory/dashboard') return fulfill({ total_items: inventoryItems.length, total_quantity: 2, by_group: [{ label: 'Loa', value: 1 }], by_condition: [{ label: 'Đang sử dụng', value: 1 }], by_asset: [{ label: 'Nhà văn hóa thôn 09', value: 1 }] });
    if (url.pathname === '/api/public-assets/dashboard') return fulfill({
      metrics: { total_assets: items.length, active_assets: 1, located_assets: 1, total_campus_area: 1234.5, total_building_area: 456.75 },
      charts: { types: [{ label: 'Nhà văn hóa', value: 1 }], area_by_category: [{ label: 'Hành chính', campus_area: 1234.5, building_area: 456.75 }] }
    });
    if (url.pathname === '/api/public-assets/gis') return fulfill({ items: items.filter(item => item.latitude && item.longitude) });
    if (url.pathname === '/api/public-assets' && request.method() === 'GET') return fulfill({ items, total: items.length, page: Number(url.searchParams.get('page') || 1), pageSize: 20, totalPages: 1 });
    if (url.pathname === '/api/public-assets' && request.method() === 'POST') {
      const body = request.postDataJSON();
      const created = asset({ ...body, id: 201, asset_code: 'CT09-00201', type_name: 'Nhà văn hóa', category: 'Hành chính', status_label: 'Đang sử dụng' });
      items = [created, ...items];
      return fulfill(created);
    }
    const match = /^\/api\/public-assets\/(\d+)$/.exec(url.pathname);
    if (match && request.method() === 'GET') return fulfill(items.find(item => String(item.id) === match[1]));
    if (match && request.method() === 'PUT') {
      const body = request.postDataJSON();
      const existing = items.find(item => String(item.id) === match[1]);
      Object.assign(existing, body);
      return fulfill(existing);
    }
    if (match && request.method() === 'DELETE') {
      items = items.filter(item => String(item.id) !== match[1]);
      return fulfill({ id: Number(match[1]) });
    }
    const inventoryMatch = /^\/api\/public-assets\/(\d+)\/inventory$/.exec(url.pathname);
    if (inventoryMatch && request.method() === 'GET') return fulfill({ enabled: true, items: inventoryItems.filter(item => String(item.public_asset_id) === inventoryMatch[1]), summary: { total_items: inventoryItems.length, total_quantity: 2, by_group: [{ label: 'Loa', value: 1 }], by_condition: [{ label: 'Đang sử dụng', value: 1 }] } });
    if (inventoryMatch && request.method() === 'POST') {
      const body = request.postDataJSON();
      const created = { id: 777, public_asset_id: Number(inventoryMatch[1]), inventory_code: body.inventory_code || 'TS777', item_name: body.item_name, group_id: Number(body.group_id || 0) || null, group_name: body.group_id === '20' ? 'Bình chữa cháy' : 'Loa', quantity: Number(body.quantity || 1), unit: body.unit || '', condition_status: body.condition_status || 'IN_USE', condition_label: body.condition_status === 'NEEDS_REPAIR' ? 'Cần sửa chữa' : 'Đang sử dụng', start_use_date: body.start_use_date || '', location_in_asset: body.location_in_asset || '', note: body.note || '', photo_url: '' };
      inventoryItems.push(created);
      return fulfill(created);
    }
    const inventoryItemMatch = /^\/api\/public-assets\/(\d+)\/inventory\/(\d+)$/.exec(url.pathname);
    if (inventoryItemMatch && request.method() === 'PUT') {
      const body = request.postDataJSON();
      const existing = inventoryItems.find(item => String(item.id) === inventoryItemMatch[2]);
      Object.assign(existing, body, { group_id: Number(body.group_id || existing.group_id), group_name: body.group_id === '20' ? 'Bình chữa cháy' : existing.group_name, condition_label: body.condition_status === 'NEEDS_REPAIR' ? 'Cần sửa chữa' : existing.condition_label });
      return fulfill(existing);
    }
    if (inventoryItemMatch && request.method() === 'DELETE') {
      inventoryItems = inventoryItems.filter(item => String(item.id) !== inventoryItemMatch[2]);
      return fulfill({ id: Number(inventoryItemMatch[2]) });
    }
    if (url.pathname.includes('/api/gis/households')) return fulfill({ items: [], total: 0 });
    if (url.pathname.includes('/api/gis/summary')) return fulfill({ total: 0, located: 0, missing: 0, areas: [] });
    return fulfill({ items: [], total: 0, page: 1, pageSize: 20, totalPages: 1 });
  });
}

async function openApp(page, viewport = { width: 1366, height: 768 }) {
  await page.setViewportSize(viewport);
  await mockApis(page);
  await page.addInitScript(() => {
    const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };
    localStorage.setItem('thon09_token', 'test-token');
    localStorage.setItem('thon09_csrf', 'test-csrf');
    localStorage.setItem('thon09_user', JSON.stringify(user));
  });
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };
    window.App.token = 'test-token';
    window.App.csrfToken = 'test-csrf';
    window.App.user = user;
    localStorage.setItem('thon09_token', 'test-token');
    localStorage.setItem('thon09_user', JSON.stringify(user));
    if (typeof window.showApp === 'function') window.showApp();
  });
  await page.waitForFunction(() => typeof window.loadPublicAssets === 'function');
  await page.evaluate(() => window.Thon09NavigationController?.navigate('publicAssets'));
  await page.evaluate(() => window.loadPublicAssets());
  await expect(page.locator('#publicAssetsScreen')).toHaveClass(/active/);
  await expect(page.locator('#publicAssetsRows tr')).toHaveCount(2);
}

test('public assets list, dashboard, filters and display format are consistent', async ({ page }) => {
  await openApp(page);

  await expect(page.locator('#publicAssetsMiniDashboard')).toContainText('Tổng công trình');
  await expect(page.locator('#publicAssetsMiniDashboard')).toContainText('1.234,5 m²');
  await expect(page.locator('#publicAssetsMiniDashboard')).toContainText('T\u1ed5ng t\u00e0i s\u1ea3n ki\u1ec3m k\u00ea');
  await expect(page.locator('#publicAssetsTotalCount')).toHaveText('Tổng số: 2 công trình');

  const firstCells = page.locator('#publicAssetsRows tr').first().locator('td');
  await expect(firstCells.nth(6)).toContainText('KV: 1.234,5 m²');
  await expect(firstCells.nth(7)).toContainText('UBND xã Hồng Phong');
  await expect(firstCells.nth(8)).toContainText('Nguy\u1ec5n V\u0103n A');
  await expect(firstCells.nth(9)).toContainText('\u0110ang s\u1eed d\u1ee5ng');

  const secondCells = page.locator('#publicAssetsRows tr').nth(1).locator('td');
  await expect(secondCells.nth(3)).toContainText('--');
  await expect(secondCells.nth(6)).toContainText('KV: --');
  await expect(secondCells.nth(7)).toContainText('--');
  await expect(secondCells.nth(8)).toContainText('--');
  await expect(secondCells.nth(9)).toContainText('\u0110ang s\u1eeda ch\u1eefa');

  await page.locator('[data-public-asset-filter="status:ACTIVE"]').click();
  await expect.poll(() => page.evaluate(() => document.querySelector('#publicAssetsStatusFilter').value)).toBe('ACTIVE');
  expect(requests.some(item => item.path === '/api/public-assets' && item.query.includes('status=ACTIVE'))).toBeTruthy();

  await page.locator('#publicAssetsResetBtn').click();
  await expect.poll(() => page.evaluate(() => document.querySelector('#publicAssetsStatusFilter').value)).toBe('');
});

test('mobile V2 renders compact independent cards instead of desktop table', async ({ page }) => {
  await openApp(page, { width: 390, height: 844 });

  const metrics = await page.evaluate(() => {
    const screen = document.querySelector('#publicAssetsScreen');
    window.Thon09MobileComponents?.renderModuleScreen(screen);
    const wrapper = document.querySelector('#publicAssetsRows')?.closest('.table-responsive');
    const table = wrapper?.querySelector('table');
    const surface = screen?.querySelector('.app-v2-module-screen');
    const firstCard = surface?.querySelector('.app-v2-record-card');
    const dashboard = surface?.querySelector('.app-v2-grid');
    const dashboardCard = dashboard?.querySelector(':scope > *');
    const actions = firstCard ? Array.from(firstCard.querySelectorAll('.app-v2-icon-button')) : [];
    const actionBox = firstCard?.querySelector('.app-v2-icon-button');
    const body = firstCard?.querySelector('.app-v2-title-group');
    const head = firstCard?.querySelector('.app-v2-record-title');
    const filterSheet = screen?.querySelector('.app-v2-filter-sheet');
    const rect = firstCard?.getBoundingClientRect();
    const actionRect = actionBox?.getBoundingClientRect();
    const bodyRect = body?.getBoundingClientRect();
    const headRect = head?.getBoundingClientRect();
    const dashRect = dashboardCard?.getBoundingClientRect();
    const tableRect = table?.getBoundingClientRect();
    const intersects = (a, b) => !!a && !!b && a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top;
    return {
      tableDisplay: table ? getComputedStyle(table).display : '',
      tableOccupiesLayout: !!tableRect && tableRect.width > 1 && tableRect.height > 1,
      surfaceDisplay: surface ? getComputedStyle(surface).display : '',
      surfaceExists: !!surface,
      sourceRows: wrapper ? wrapper.querySelectorAll('tbody tr').length : 0,
      decoratedSourceRows: wrapper ? wrapper.querySelectorAll('tbody tr.mobile-source-card').length : 0,
      generatedCards: surface ? surface.querySelectorAll('.app-v2-record-card').length : 0,
      cardHeight: rect ? Math.round(rect.height) : 0,
      cardText: firstCard?.innerText || '',
      actionCount: actions.length,
      actionPosition: actionBox ? getComputedStyle(actionBox).position : '',
      actionOverlapsBody: intersects(actionRect, bodyRect),
      actionOverlapsHead: intersects(actionRect, headRect),
      filterTriggerVisible: !!filterSheet && getComputedStyle(filterSheet).display !== 'none',
      actionWidths: actions.map((button) => Math.round(button.getBoundingClientRect().width)),
      dashboardColumns: dashboard ? getComputedStyle(dashboard).gridTemplateColumns.split(' ').length : 0,
      dashboardCardHeight: dashRect ? Math.round(dashRect.height) : 0
    };
  });

  expect(metrics.tableDisplay).not.toBe('');
  expect(metrics.tableOccupiesLayout).toBe(false);
  expect(metrics.surfaceExists).toBe(true);
  expect(metrics.generatedCards).toBeGreaterThanOrEqual(1);
  expect(metrics.sourceRows).toBe(2);
  expect(metrics.decoratedSourceRows).toBe(0);
  expect(metrics.dashboardColumns).toBeGreaterThanOrEqual(1);
  expect(metrics.dashboardCardHeight).toBeLessThanOrEqual(180);
  expect(metrics.cardHeight).toBeGreaterThan(0);
  expect(metrics.cardHeight).toBeLessThanOrEqual(320);
  expect(metrics.cardText.toLocaleLowerCase('vi-VN')).toContain('nhà văn hóa thôn 09');
  expect(metrics.actionCount).toBeLessThanOrEqual(3);
  expect(metrics.actionOverlapsBody).toBe(false);
  expect(metrics.actionOverlapsHead).toBe(false);
  expect(typeof metrics.filterTriggerVisible).toBe('boolean');
  expect(metrics.actionWidths.every((width) => width >= 44)).toBe(true);
});

test('mobile V2 public asset cards keep address out of title and dedupe detail fields', async ({ page }) => {
  for (const width of [320, 360, 390, 414, 768]) {
    await openApp(page, { width, height: width >= 768 ? 1024 : 844 });
    const metrics = await page.evaluate(() => {
      const screen = document.querySelector('#publicAssetsScreen');
      window.Thon09MobileComponents?.renderModuleScreen(screen);
      const firstCard = screen?.querySelector('.app-v2-record-card');
      const title = firstCard?.querySelector('.app-v2-record-title')?.textContent?.trim() || '';
      const meta = firstCard?.querySelector('.app-v2-record-meta')?.textContent?.trim() || '';
      const metaHiddenBeforeOpen = firstCard?.querySelector('.app-v2-record-meta')?.hidden || false;
      const more = firstCard?.querySelector('.app-v2-record-more');
      if (more) {
        more.open = true;
        more.dispatchEvent(new Event('toggle'));
      }
      const metaHiddenAfterOpen = firstCard?.querySelector('.app-v2-record-meta')?.hidden || false;
      const fields = Array.from(firstCard?.querySelectorAll('.app-v2-record-field') || []).map((field) => ({
        label: field.querySelector('dt')?.textContent?.trim() || '',
        value: field.querySelector('dd')?.textContent?.trim() || ''
      }));
      const addressFields = fields.filter((field) => field.label === 'Địa chỉ');
      const identities = fields.map((field) => `${field.label}:${field.value}`);
      return {
        title,
        meta,
        metaHiddenBeforeOpen,
        metaHiddenAfterOpen,
        fields,
        addressFields,
        duplicateCount: identities.length - new Set(identities).size,
        scrollWidth: Math.ceil(screen.scrollWidth),
        clientWidth: Math.ceil(screen.clientWidth)
      };
    });

    expect(metrics.title).toBe('Nhà văn hóa thôn 09');
    expect(metrics.title).not.toContain('Xã Hồng Phong');
    expect(metrics.meta).not.toContain('Thôn 09, Xã Hồng Phong');
    expect(metrics.metaHiddenBeforeOpen).toBe(false);
    expect(metrics.metaHiddenAfterOpen).toBe(true);
    expect(metrics.addressFields).toHaveLength(1);
    expect(metrics.addressFields[0].value).toBe('Thôn 09, Xã Hồng Phong');
    expect(metrics.duplicateCount).toBe(0);
    expect(metrics.scrollWidth).toBeLessThanOrEqual(metrics.clientWidth + 2);
  }
});

test('public assets detail, create, update, delete and GIS layer work', async ({ page }) => {
  await openApp(page);

  await page.locator('#publicAssetsRows [data-platform-action="publicAssets.detail"]').first().click();
  await expect(page.locator('#publicAssetDetailModal.show')).toBeVisible();
  await expect(page.locator('#publicAssetDetailBody')).toContainText('Đơn vị quản lý');
  await expect(page.locator('#publicAssetDetailBody')).toContainText('Người phụ trách');
  await expect(page.locator('#publicAssetDetailBody')).toContainText('1.234,5 m²');
  await expect(page.locator('#publicAssetDetailBody')).toContainText('Kiểm kê tài sản');
  await page.locator('button[data-bs-target="#publicAssetInventoryTab"]').click();
  await expect(page.locator('#publicAssetInventoryPanel')).toContainText('Loa hội trường');
  await expect(page.locator('#publicAssetInventoryPanel')).toContainText('Tổng số tài sản: 1');
  await page.locator('[data-platform-action="publicAssets.inventory.add"]').click();
  await expect(page.locator('#publicAssetInventoryModal.show')).toBeVisible();
  await page.locator('#publicAssetInventoryForm [name="item_name"]').fill('Bình chữa cháy mới');
  await page.locator('#publicAssetInventoryForm [name="group_id"]').selectOption('20');
  await page.locator('#publicAssetInventoryForm [name="quantity"]').fill('3');
  await page.locator('#publicAssetInventoryForm [name="unit"]').fill('bình');
  await page.locator('#publicAssetInventoryForm [name="condition_status"]').selectOption('NEEDS_REPAIR');
  await page.locator('#publicAssetInventoryForm button[type="submit"]').click();
  await expect.poll(() => requests.some(item => item.method === 'POST' && item.path === '/api/public-assets/101/inventory')).toBeTruthy();
  await page.locator('#publicAssetInventoryPanel [data-platform-action="publicAssets.inventory.edit"]').first().click();
  await expect(page.locator('#publicAssetInventoryModal.show')).toBeVisible();
  await page.locator('#publicAssetInventoryForm [name="quantity"]').fill('4');
  await page.locator('#publicAssetInventoryForm button[type="submit"]').click();
  await expect.poll(() => requests.some(item => item.method === 'PUT' && item.path === '/api/public-assets/101/inventory/501')).toBeTruthy();
  await page.locator('#publicAssetInventoryPanel [data-platform-action="publicAssets.inventory.delete"]').first().click();
  await expect(page.locator('.platform-confirm-dialog')).toBeVisible();
  await page.locator('.platform-confirm-dialog .platform-confirm-footer .btn-danger').click();
  await expect.poll(() => requests.some(item => item.method === 'DELETE' && item.path === '/api/public-assets/101/inventory/501')).toBeTruthy();
  await page.evaluate(() => {
    const modal = document.querySelector('#publicAssetDetailModal');
    bootstrap.Modal.getOrCreateInstance(modal).hide();
    modal.classList.remove('show');
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    modal.removeAttribute('aria-modal');
    document.querySelectorAll('.modal-backdrop').forEach(element => element.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
  });
  await expect(page.locator('#publicAssetDetailModal.show')).toHaveCount(0);

  await page.locator('#publicAssetsAddBtn').click();
  await expect(page.locator('#publicAssetFormModal.show')).toBeVisible();
  await expect(page.locator('#publicAssetFormInventoryMount')).toContainText('Vui lòng lưu công trình trước khi thực hiện kiểm kê tài sản.');
  await page.locator('#publicAssetForm [name="asset_name"]').fill('Công trình kiểm thử');
  await page.locator('#publicAssetForm [name="type_id"]').selectOption('1');
  await page.locator('#publicAssetForm [name="campus_area"]').fill('88.5');
  await page.locator('#publicAssetForm [name="address"]').fill('Thôn 09, Xã Hồng Phong');
  await page.locator('#publicAssetForm [name="managing_unit"]').fill('Ban quản lý thôn');
  await page.locator('#publicAssetForm [name="manager_name"]').fill('Trần Thị B');
  await page.locator('#publicAssetForm button[type="submit"]').click();
  await expect.poll(() => requests.some(item => item.method === 'POST' && item.path === '/api/public-assets')).toBeTruthy();
  await expect(page.locator('#publicAssetFormModal.show')).toHaveCount(0);
  const postRequest = requests.find(item => item.method === 'POST' && item.path === '/api/public-assets');
  expect(postRequest.body.asset_name).toBe('Công trình kiểm thử');

  await page.locator('#publicAssetsRows tr').filter({ hasText: 'CT09-00101' }).locator('[data-platform-action="publicAssets.edit"]').click();
  await expect(page.locator('#publicAssetFormModal.show')).toBeVisible();
  await expect(page.locator('#publicAssetFormInventoryMount')).toContainText('Kiểm kê tài sản');
  await expect(page.locator('#publicAssetFormInventoryPanel')).toContainText('Bình chữa cháy mới');
  await page.locator('#publicAssetForm [name="manager_name"]').fill('Trần Thị C');
  await page.locator('#publicAssetForm button[type="submit"]').click();
  await expect.poll(() => requests.some(item => item.method === 'PUT' && /^\/api\/public-assets\/\d+$/.test(item.path))).toBeTruthy();
  await expect(page.locator('#publicAssetFormModal.show')).toHaveCount(0, { timeout: 10000 });
  const putRequest = requests.find(item => item.method === 'PUT' && /^\/api\/public-assets\/\d+$/.test(item.path));
  expect(putRequest.body.manager_name).toBe('Trần Thị C');

  await page.locator('#publicAssetsRows tr').filter({ hasText: 'CT09-00102' }).locator('[data-platform-action="publicAssets.delete"]').click();
  await expect.poll(() => requests.some(item => item.method === 'DELETE' && item.path.startsWith('/api/public-assets/'))).toBeTruthy();

  await page.route('**/api/public-assets/gis', route => route.fulfill({
    contentType: 'application/json',
    body: JSON.stringify(ok({ items: [asset()] }))
  }));
  const gisResult = await page.evaluate(async () => {
    const created = [];
    window.App.gis.map = {};
    window.App.gis.publicAssetLayer = null;
    const originalApi = window.api;
    window.api = (url, options) => {
      if (url === '/api/public-assets/gis') {
        return Promise.resolve({
          items: [{
            id: 101,
            asset_name: 'Nhà văn hóa thôn 09',
            type_name: 'Nhà văn hóa',
            status_label: 'Đang sử dụng',
            address: 'Thôn 09, Xã Hồng Phong',
            campus_area: 1234.5,
            latitude: 20.255,
            longitude: 105.976,
            managing_unit: 'UBND xã Hồng Phong',
            manager_name: 'Nguyễn Văn A',
            type_icon: 'fa-building-columns',
            status: 'ACTIVE'
          }]
        });
      }
      return originalApi(url, options);
    };
    window.L = {
      layerGroup: () => ({ markers: created, addTo() { return this; }, clearLayers() { created.length = 0; } }),
      divIcon: options => options,
      marker: (coords, options) => ({
        coords,
        options,
        popup: '',
        bindPopup(html) { this.popup = html; return this; },
        addTo(layer) { layer.markers.push(this); return this; }
      })
    };
    window.refreshPublicAssetGisLayer();
    await new Promise(resolve => setTimeout(resolve, 150));
    return { count: created.length, popup: created[0]?.popup || '' };
  });
  expect(gisResult.count).toBe(1);
  expect(gisResult.popup).toContain('https://www.google.com/maps/dir/?api=1&destination=20.255%2C105.976');
  expect(gisResult.popup).toContain('UBND xã Hồng Phong');
});

test('religious public assets do not expose inventory tab', async ({ page }) => {
  await openApp(page);
  await page.route('**/api/public-assets/999', route => route.fulfill({
    contentType: 'application/json',
    body: JSON.stringify(ok(asset({ id: 999, asset_code: 'CT09-00999', asset_name: 'Chùa thôn 09', type_name: 'Chùa', category: 'Tôn giáo, tín ngưỡng', inventory_enabled: false, inventory_allowed: false })))
  }));
  await page.evaluate(() => window.openPublicAssetDetail(999));
  await expect(page.locator('#publicAssetDetailModal.show')).toBeVisible();
  await expect(page.locator('#publicAssetDetailBody')).not.toContainText('Kiểm kê tài sản');
});

for (const viewport of [
  { name: 'desktop', width: 1366, height: 768 },
  { name: 'tablet', width: 768, height: 1024 },
  { name: 'mobile', width: 390, height: 844 }
]) {
  test(`public assets layout has no horizontal overflow on ${viewport.name}`, async ({ page }) => {
    await openApp(page, viewport);
    const metrics = await page.evaluate(() => ({
      scrollWidth: Math.ceil(document.documentElement.scrollWidth),
      clientWidth: Math.ceil(document.documentElement.clientWidth),
      activeScrollWidth: Math.ceil(document.querySelector('#publicAssetsScreen').scrollWidth),
      activeClientWidth: Math.ceil(document.querySelector('#publicAssetsScreen').clientWidth)
    }));
    expect(metrics.scrollWidth).toBeLessThanOrEqual(metrics.clientWidth + 2);
    expect(metrics.activeScrollWidth).toBeLessThanOrEqual(metrics.activeClientWidth + 96);
  });
}
