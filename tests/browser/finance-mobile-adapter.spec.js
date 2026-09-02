const { test, expect } = require('@playwright/test');

function payload(data) {
  return { contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) };
}

const catalogs = {
  types: [{ value: 'INCOME', label: 'Thu' }, { value: 'EXPENSE', label: 'Chi' }],
  funds: [{ value: '1', label: 'Quy tien mat' }, { value: '2', label: 'Quy ngan hang' }],
  categories: [
    { value: '10', label: 'Thu phi', transaction_type: 'INCOME' },
    { value: '11', label: 'Thu khac', transaction_type: 'INCOME' },
    { value: '20', label: 'Chi van hanh', transaction_type: 'EXPENSE' },
    { value: '21', label: 'Chi khac', transaction_type: 'EXPENSE' }
  ],
  statuses: [{ value: 'POSTED', label: 'Da ghi so' }, { value: 'CANCELLED', label: 'Da huy' }],
  payment_methods: [{ value: 'CASH', label: 'Tien mat' }]
};

const transactions = Array.from({ length: 45 }, (_, index) => {
  const income = index % 2 === 0;
  return {
    id: index + 1,
    transaction_code: `${income ? 'PT' : 'PC'}-${String(index + 1).padStart(3, '0')}`,
    transaction_date: `2026-08-${String((index % 28) + 1).padStart(2, '0')}`,
    transaction_type: income ? 'INCOME' : 'EXPENSE',
    type_label: income ? 'Thu' : 'Chi',
    fund_id: income ? '1' : '2',
    fund_name: income ? 'Quy tien mat' : 'Quy ngan hang',
    category_id: income ? '10' : '20',
    category_name: income ? 'Thu phi' : 'Chi van hanh',
    status: index % 5 === 0 ? 'CANCELLED' : 'POSTED',
    status_label: index % 5 === 0 ? 'Da huy' : 'Da ghi so',
    amount: (index + 1) * 1000,
    receipt_number: `CT-${index + 1}`,
    payer_name: income ? `Nguoi nop ${index + 1}` : '',
    receiver_name: income ? '' : `Nguoi nhan ${index + 1}`,
    description: income ? 'thu tien dich vu' : 'chi hoat dong',
    attachment_count: index % 3
  };
});

function filterTransactions(params) {
  const search = (params.get('search') || '').trim().toLowerCase();
  return transactions.filter((item) => {
    if (search && ![item.transaction_code, item.receipt_number, item.payer_name, item.receiver_name, item.description].some((value) => String(value || '').toLowerCase().includes(search))) return false;
    if (params.get('transaction_type') && item.transaction_type !== params.get('transaction_type')) return false;
    if (params.get('fund_id') && item.fund_id !== params.get('fund_id')) return false;
    if (params.get('category_id') && item.category_id !== params.get('category_id')) return false;
    if (params.get('status') && item.status !== params.get('status')) return false;
    if (params.get('date_from') && item.transaction_date < params.get('date_from')) return false;
    if (params.get('date_to') && item.transaction_date > params.get('date_to')) return false;
    return true;
  });
}

async function openFinance(page, width, requestLog) {
  await page.setViewportSize({ width, height: 900 });
  await page.route('**/api/**', async (route) => {
    const url = new URL(route.request().url());
    const path = url.pathname;
    requestLog.push({ path, method: route.request().method(), params: Object.fromEntries(url.searchParams.entries()) });
    if (path === '/api/public/login-config') return route.fulfill(payload({ settings: {}, metrics: {} }));
    if (path === '/api/auth/me') return route.fulfill(payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' }));
    if (path === '/api/finance/catalogs') return route.fulfill(payload(catalogs));
    if (/^\/api\/finance\/\d+\/attachments$/.test(path) && route.request().method() === 'POST') return route.fulfill(payload({ id: 900, original_name: 'receipt.pdf' }));
    if (/^\/api\/finance\/\d+\/attachments\/\d+$/.test(path) && route.request().method() === 'DELETE') return route.fulfill(payload({ deleted: true }));
    if (/^\/api\/finance\/\d+$/.test(path)) {
      const id = Number(path.split('/').pop());
      if (route.request().method() === 'PUT') {
        const body = route.request().postDataJSON();
        return route.fulfill(payload(Object.assign({}, transactions.find((item) => item.id === id), body, { id })));
      }
      if (route.request().method() === 'DELETE') return route.fulfill(payload({ deleted: true }));
      const item = transactions.find((row) => row.id === id) || transactions[0];
      return route.fulfill(payload(Object.assign({ attachments: [] }, item)));
    }
    if (path === '/api/finance/dashboard') {
      const items = filterTransactions(url.searchParams);
      const income = items.filter((item) => item.transaction_type === 'INCOME').reduce((sum, item) => sum + item.amount, 0);
      const expense = items.filter((item) => item.transaction_type === 'EXPENSE').reduce((sum, item) => sum + item.amount, 0);
      return route.fulfill(payload({ metrics: { total_income: income, total_expense: expense, balance: income - expense, total: items.length }, charts: {} }));
    }
    if (path === '/api/finance') {
      if (route.request().method() === 'POST') {
        if (url.searchParams.get('force_error') === '1') {
          return route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { message: 'forced finance error' } }) });
        }
        const body = route.request().postDataJSON();
        return route.fulfill(payload(Object.assign({ id: 500, transaction_code: 'PT-500', attachments: [] }, body)));
      }
      const items = filterTransactions(url.searchParams);
      const pageNumber = Number(url.searchParams.get('page') || 1);
      const pageSize = Number(url.searchParams.get('pageSize') || 20);
      return route.fulfill(payload({
        items: items.slice((pageNumber - 1) * pageSize, pageNumber * pageSize),
        total: items.length,
        page: pageNumber,
        pageSize,
        totalPages: Math.max(1, Math.ceil(items.length / pageSize))
      }));
    }
    return route.fulfill(payload({ items: [], total: 0, page: 1, pageSize: 20, totalPages: 1, metrics: {}, charts: {} }));
  });
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };
    App.token = 'test-token';
    App.csrfToken = 'test-csrf';
    App.user = user;
    localStorage.setItem(tenantStorageKey('token'), 'test-token');
    localStorage.setItem(tenantStorageKey('csrf'), 'test-csrf');
    localStorage.setItem(tenantStorageKey('user'), JSON.stringify(user));
    if (typeof window.showApp === 'function') window.showApp();
  });
  await page.evaluate(() => window.TenantAppNavigationController?.navigate('finance'));
  await page.waitForTimeout(250);
  await page.evaluate(() => window.TenantAppMobileComponents?.schedule());
  await page.waitForTimeout(150);
}

test.describe('finance canonical mobile adapter', () => {
  for (const width of [320, 375, 390, 430, 768]) {
    test(`responsive mobile filter delegates Finance canonical state at ${width}px`, async ({ page }) => {
      const requestLog = [];
      await openFinance(page, width, requestLog);

      const fields = await page.locator('#financeScreen .app-v2-filter-bar [data-app-v2-filter-field]').evaluateAll((nodes) => nodes.map((node) => node.getAttribute('data-app-v2-filter-field')));
      expect(fields).toEqual(expect.arrayContaining(['search', 'transaction_type', 'fund_id', 'category_id', 'status', 'date_from', 'date_to', 'pageSize']));
      await expect(page.locator('#financeScreen .app-v2-filter-bar')).toHaveCount(1);

      const lastList = () => requestLog.filter((entry) => entry.path === '/api/finance').findLast(Boolean);
      const lastDashboard = () => requestLog.filter((entry) => entry.path === '/api/finance/dashboard').findLast(Boolean);

      requestLog.length = 0;
      await page.evaluate(() => window.TenantAppMobileFilterAdapters.finance.applyFilters({ transaction_type: 'INCOME', category_id: '10' }));
      await expect.poll(async () => lastList()?.params.transaction_type).toBe('INCOME');
      expect(lastList()?.params.category_id).toBe('10');
      expect(lastDashboard()?.params.transaction_type).toBe('INCOME');
      expect(lastDashboard()?.params.category_id).toBe('10');

      const incomeCategoryOptions = await page.evaluate(() => window.TenantAppMobileFilterAdapters.finance.getDefinition().fields.find((field) => field.key === 'category_id').options.map((option) => option.value));
      expect(incomeCategoryOptions).toEqual(expect.arrayContaining(['', '10', '11']));
      expect(incomeCategoryOptions).not.toContain('20');

      requestLog.length = 0;
      await page.evaluate(() => window.TenantAppMobileFilterAdapters.finance.applyFilters({ transaction_type: 'EXPENSE' }));
      await expect.poll(async () => lastList()?.params.page).toBe('1');
      expect(lastList()?.params.transaction_type).toBe('EXPENSE');
      expect(lastList()?.params.category_id).toBe('');
      const switchedState = await page.evaluate(() => window.TenantAppMobileFilterAdapters.finance.getState());
      expect(switchedState.category_id).toBe('');

      const expenseCategoryOptions = await page.evaluate(() => window.TenantAppMobileFilterAdapters.finance.getDefinition().fields.find((field) => field.key === 'category_id').options.map((option) => option.value));
      expect(expenseCategoryOptions).toEqual(expect.arrayContaining(['', '20', '21']));
      expect(expenseCategoryOptions).not.toContain('10');

      requestLog.length = 0;
      await page.evaluate(() => window.TenantAppMobileFilterAdapters.finance.setPage(2));
      await expect.poll(async () => lastList()?.params.page).toBe('2');
      expect(lastList()?.params.transaction_type).toBe('EXPENSE');

      requestLog.length = 0;
      await page.evaluate(() => window.TenantAppMobileFilterAdapters.finance.applyFilters({
        search: 'chi',
        transaction_type: 'EXPENSE',
        fund_id: '2',
        category_id: '20',
        status: 'POSTED',
        date_from: '2026-08-01',
        date_to: '2026-08-28',
        pageSize: '50'
      }));
      await expect.poll(async () => lastList()?.params.page).toBe('1');
      for (const key of ['search', 'transaction_type', 'fund_id', 'category_id', 'status', 'date_from', 'date_to']) {
        expect(lastDashboard()?.params[key]).toBe(lastList()?.params[key]);
      }
      expect(lastList()?.params.pageSize).toBe('50');

      requestLog.length = 0;
      await page.evaluate(() => window.TenantAppMobileFilterAdapters.finance.reset());
      await expect.poll(async () => lastList()?.params.page).toBe('1');
      expect(lastList()?.params.transaction_type).toBe('');
      expect(lastList()?.params.category_id).toBe('');
      const resetState = await page.evaluate(() => window.TenantAppMobileFilterAdapters.finance.getState());
      expect(resetState).toMatchObject({ search: '', transaction_type: '', fund_id: '', category_id: '', status: '', date_from: '', date_to: '', pageSize: 20 });
    });
  }
});

test('finance local CRUD smoke still uses canonical catalogs and loader', async ({ page }) => {
  const requestLog = [];
  await openFinance(page, 390, requestLog);

  requestLog.length = 0;
  await page.evaluate(() => {
    const form = document.querySelector('#financeForm');
    form.elements.id.value = '';
    form.elements.transaction_type.value = 'INCOME';
    form.elements.fund_id.value = '1';
    form.elements.category_id.value = '10';
    form.elements.status.value = 'POSTED';
    form.elements.amount.value = '125000';
    form.elements.transaction_date.value = '2026-08-15';
    form.elements.receipt_number.value = 'LOCAL-001';
    form.elements.payer_name.value = 'Nguoi nop local';
    form.elements.description.value = 'local finance create smoke';
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
  });
  await expect.poll(() => requestLog.some((entry) => entry.path === '/api/finance' && entry.method === 'POST')).toBeTruthy();
  expect(requestLog.filter((entry) => entry.path === '/api/finance' && entry.method === 'POST')).toHaveLength(1);

  await page.evaluate(() => window.TenantAppPlatform?.actions?.dispatch('finance.detail', { dataset: { id: '1' } }));
  await expect(page.locator('#financeDetailBody')).toContainText('1.000');
  await page.evaluate(() => window.TenantAppPlatform?.modals?.close?.('financeDetailModal'));

  requestLog.length = 0;
  await page.evaluate(() => {
    const form = document.querySelector('#financeForm');
    form.elements.id.value = '1';
    form.elements.transaction_type.value = 'INCOME';
    form.elements.fund_id.value = '1';
    form.elements.category_id.value = '10';
    form.elements.status.value = 'POSTED';
    form.elements.amount.value = '126000';
    form.elements.transaction_date.value = '2026-08-15';
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
  });
  await expect.poll(() => requestLog.some((entry) => entry.path === '/api/finance/1' && entry.method === 'PUT')).toBeTruthy();
  expect(requestLog.filter((entry) => entry.path === '/api/finance/1' && entry.method === 'PUT')).toHaveLength(1);

  page.on('dialog', (dialog) => dialog.accept());
  requestLog.length = 0;
  await page.evaluate(() => window.TenantAppPlatform?.actions?.dispatch('finance.delete', { dataset: { id: '1' } }));
  await expect.poll(() => requestLog.some((entry) => entry.path === '/api/finance/1' && entry.method === 'DELETE')).toBeTruthy();
  expect(requestLog.filter((entry) => entry.path === '/api/finance/1' && entry.method === 'DELETE')).toHaveLength(1);
});

test('finance submit handler keeps form reference through async error path', async ({ page }) => {
  const requestLog = [];
  await openFinance(page, 390, requestLog);
  await page.evaluate(() => {
    const originalFetch = window.fetch.bind(window);
    window.fetch = (input, init) => {
      const url = new URL(String(input), location.href);
      if (url.pathname === '/api/finance' && String(init?.method || 'GET').toUpperCase() === 'POST') url.searchParams.set('force_error', '1');
      return originalFetch(url.pathname + url.search, init);
    };
    const form = document.querySelector('#financeForm');
    form.elements.id.value = '';
    form.elements.transaction_type.value = 'INCOME';
    form.elements.fund_id.value = '1';
    form.elements.category_id.value = '10';
    form.elements.status.value = 'POSTED';
    form.elements.amount.value = '125000';
    form.elements.transaction_date.value = '2026-08-15';
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
  });
  await expect.poll(() => requestLog.some((entry) => entry.path === '/api/finance' && entry.method === 'POST')).toBeTruthy();
  expect(requestLog.filter((entry) => entry.path === '/api/finance' && entry.method === 'POST')).toHaveLength(1);
});
