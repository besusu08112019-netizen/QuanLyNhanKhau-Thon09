const { test, expect } = require('@playwright/test');

const type = { value: '1', label: 'Nhà văn hóa', category: 'Hành chính', icon: 'fa-building-columns' };
let items;
let requests;

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
  await page.route('**/api/**', async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    requests.push({ method: request.method(), path: url.pathname, query: url.searchParams.toString(), body: request.postDataJSON?.() || null });
    const fulfill = (data) => route.fulfill({ contentType: 'application/json', body: JSON.stringify(ok(data)) });

    if (url.pathname === '/api/public/login-config') return fulfill({ settings: { systemName: 'Thôn 09', hamletName: 'Thôn 09', communeName: 'Hồng Phong', version: 'v2.0' }, metrics: {} });
    if (url.pathname === '/api/auth/me') return fulfill({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.pathname === '/api/dashboard/summary') return fulfill({ metrics: {}, charts: {} });
    if (url.pathname === '/api/public-assets/catalogs') return fulfill({ types: [type], areas: [{ value: 'Thôn 09', label: 'Thôn 09' }], statuses: [{ value: 'ACTIVE', label: 'Đang sử dụng' }, { value: 'REPAIRING', label: 'Đang sửa chữa' }] });
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
  await page.evaluate(() => window.switchScreen && window.switchScreen('publicAssets'));
  await page.evaluate(() => window.loadPublicAssets());
  await expect(page.locator('#publicAssetsScreen')).toHaveClass(/active/);
  await expect(page.locator('#publicAssetsRows tr')).toHaveCount(2);
}

test('public assets list, dashboard, filters and display format are consistent', async ({ page }) => {
  await openApp(page);

  await expect(page.locator('#publicAssetsMiniDashboard')).toContainText('Tổng công trình');
  await expect(page.locator('#publicAssetsMiniDashboard')).toContainText('1.234,5 m²');
  await expect(page.locator('#publicAssetsTotalCount')).toHaveText('Tổng số: 2 công trình');

  const firstCells = page.locator('#publicAssetsRows tr').first().locator('td');
  await expect(firstCells.nth(6)).toContainText('KV: 1.234,5 m²');
  await expect(firstCells.nth(7)).toContainText('UBND xã Hồng Phong');
  await expect(firstCells.nth(8)).toContainText('Đang sử dụng');

  const secondCells = page.locator('#publicAssetsRows tr').nth(1).locator('td');
  await expect(secondCells.nth(3)).toContainText('--');
  await expect(secondCells.nth(6)).toContainText('KV: --');
  await expect(secondCells.nth(7)).toContainText('--');
  await expect(secondCells.nth(8)).toContainText('Đang sửa chữa');

  await page.locator('[data-public-asset-filter="status:ACTIVE"]').click();
  await expect.poll(() => page.evaluate(() => document.querySelector('#publicAssetsStatusFilter').value)).toBe('ACTIVE');
  expect(requests.some(item => item.path === '/api/public-assets' && item.query.includes('status=ACTIVE'))).toBeTruthy();

  await page.locator('#publicAssetsResetBtn').click();
  await expect.poll(() => page.evaluate(() => document.querySelector('#publicAssetsStatusFilter').value)).toBe('');
});

test('public assets detail, create, update, delete and GIS layer work', async ({ page }) => {
  await openApp(page);

  await page.locator('#publicAssetsRows [data-action="detail"]').first().click();
  await expect(page.locator('#publicAssetDetailModal.show')).toBeVisible();
  await expect(page.locator('#publicAssetDetailBody')).toContainText('Đơn vị quản lý');
  await expect(page.locator('#publicAssetDetailBody')).toContainText('Người quản lý');
  await expect(page.locator('#publicAssetDetailBody')).toContainText('1.234,5 m²');
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

  await page.locator('#publicAssetsRows tr').filter({ hasText: 'CT09-00101' }).locator('[data-action="edit"]').click();
  await expect(page.locator('#publicAssetFormModal.show')).toBeVisible();
  await page.locator('#publicAssetForm [name="manager_name"]').fill('Trần Thị C');
  await page.locator('#publicAssetForm button[type="submit"]').click();
  await expect(page.locator('#publicAssetFormModal.show')).toHaveCount(0);
  await expect.poll(() => requests.some(item => item.method === 'PUT' && item.path.startsWith('/api/public-assets/'))).toBeTruthy();
  const putRequest = requests.find(item => item.method === 'PUT' && item.path.startsWith('/api/public-assets/'));
  expect(putRequest.body.manager_name).toBe('Trần Thị C');

  page.on('dialog', dialog => dialog.accept());
  await page.locator('#publicAssetsRows tr').filter({ hasText: 'CT09-00102' }).locator('[data-action="delete"]').click();
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
