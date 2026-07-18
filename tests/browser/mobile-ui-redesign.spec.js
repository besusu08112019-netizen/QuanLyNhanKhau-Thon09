const { test, expect } = require('@playwright/test');

const widths = [320, 360, 375, 390, 414, 430, 600, 768, 1024, 1280, 1440, 1920];
const screens = ['dashboard', 'households', 'persons', 'gis', 'reports', 'businessHouseholds', 'agriculture', 'livestock', 'publicAssets', 'houses', 'vehicles', 'contributions'];

function payload(data) {
  return { contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) };
}

async function mockApis(page) {
  await page.route('**/api/**', async (route) => {
    const url = new URL(route.request().url());
    const path = url.pathname;
    if (path === '/api/public/login-config') return route.fulfill(payload({ settings: {}, metrics: {} }));
    if (path === '/api/auth/me') return route.fulfill(payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' }));
    if (path.includes('/api/dashboard')) return route.fulfill(payload({ metrics: {}, charts: {}, kpis: [], generatedAt: new Date().toISOString() }));
    if (path.includes('/api/gis')) return route.fulfill(payload({ items: [], total: 0, metrics: {}, charts: {} }));
    if (path === '/api/households') return route.fulfill(payload({
      items: [{ id: 1, code: 'H09-0001', headName: 'NGUYEN VAN AN', address: 'Thon 09, xa Hong Phong', atHome: 4, away: 1, householdType: 'Ho thuong tru' }],
      total: 1, page: 1, pageSize: 20, totalPages: 1
    }));
    if (path === '/api/persons') return route.fulfill(payload({
      items: [{ id: 1, householdCode: 'H09-0001', citizenCode: 'NK-0001', fullName: 'TRAN THI BINH', relationship: 'Chu ho', dateOfBirth: '1985-01-01', gender: 'Nu', identityNumber: '001185000001', residencyStatus: 'Thuong tru' }],
      total: 1, page: 1, pageSize: 20, totalPages: 1
    }));
    if (path === '/api/household-business') return route.fulfill(payload({
      items: [{ id: 1, household_code: 'H09-0002', head_citizen_name: 'PHAM VAN BICH', business_name: 'Co so moc Bich', business_type_label: 'Ho kinh doanh', sector_label: 'Moc dan dung', status_label: 'Dang hoat dong' }],
      total: 1, page: 1, pageSize: 20, totalPages: 1, dashboard: {}
    }));
    if (path === '/api/livestock') return route.fulfill(payload({
      items: [{ id: 1, household_code: 'H09-0003', head_citizen_name: 'LE VAN CUONG', animal_type_label: 'Bo', breed: 'BBB', quantity: 3, vaccinated_label: 'Da tiem', updated_at: '2026-07-16' }],
      total: 1, page: 1, pageSize: 20, totalPages: 1, kpis: {}
    }));
    if (path === '/api/agriculture') return route.fulfill(payload({
      items: [{ id: 1, parcel_code: 'TD-01', field_area: 'Dong Tren', owner_name: 'DO THI DUNG', producer_name: 'DO THI DUNG', actual_area: 720, crop_name: 'Lua', season_name: 'Vu mua', status_label: 'Dang san xuat' }],
      total: 1, page: 1, pageSize: 20, totalPages: 1, kpis: {}
    }));
    if (path.includes('/api/public-assets')) return route.fulfill(payload({
      items: [{ id: 1, asset_code: 'CT-01', asset_name: 'Nha van hoa thon', type_name: 'Nha van hoa', area_code: 'K1', campus_area: 350, managing_unit: 'Thon 09', manager_name: 'Nguyen Van An', status_label: 'Dang su dung' }],
      total: 1, page: 1, pageSize: 20, totalPages: 1, metrics: {}, charts: {}, types: [], areas: [], statuses: []
    }));
    return route.fulfill(payload({ items: [], total: 0, page: 1, pageSize: 20, totalPages: 1, metrics: {}, charts: {} }));
  });
}

async function openApp(page, width) {
  await page.setViewportSize({ width, height: 900 });
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

async function navigate(page, screen) {
  await page.evaluate((target) => window.Thon09NavigationController?.navigate(target), screen);
  await page.waitForTimeout(180);
  await page.evaluate(() => window.Thon09MobileComponents?.schedule());
  await page.waitForTimeout(120);
}

test.describe('mobile tablet UI redesign contract', () => {
  for (const width of widths) {
    test(`shared UI stays stable at ${width}px`, async ({ page }) => {
      await openApp(page, width);
      for (const screen of screens) {
        await navigate(page, screen);
        const metrics = await page.evaluate(() => {
          const active = document.querySelector('.screen.active');
          const v2Host = active?.querySelector('.app-v2-dashboard, .app-v2-module-screen, .app-v2-module-dashboard');
          const cards = Array.from(active?.querySelectorAll('.app-v2-card, .app-v2-stat-card') || []).filter((card) => {
            const rect = card.getBoundingClientRect();
            return rect.width > 1 && rect.height > 1;
          });
          const titles = cards.map((card) => {
            const title = card.querySelector('.app-v2-record-title, .app-v2-title, .app-v2-section-title, .app-v2-list-title');
            const rect = title?.getBoundingClientRect();
            return {
              text: title?.textContent || '',
              width: rect ? Math.round(rect.width) : 0,
              height: rect ? Math.round(rect.height) : 0
            };
          });
          const actions = Array.from(active?.querySelectorAll('.app-v2-button, .app-v2-icon-button, .app-v2-fab') || []).map((button) => {
            const rect = button.getBoundingClientRect();
            const icon = button.querySelector('i, svg');
            const style = icon ? getComputedStyle(icon) : null;
            return {
              width: Math.round(rect.width),
              height: Math.round(rect.height),
              icon: !!icon,
              color: style ? style.color : ''
            };
          });
          const visibleTables = Array.from(active?.querySelectorAll('.table-responsive table') || []).filter((table) => {
            const rect = table.getBoundingClientRect();
            return rect.width > 2 && rect.height > 2;
          });
          const cardFitFailures = cards.filter((card) => {
            const rect = card.getBoundingClientRect();
            const hostRect = v2Host?.getBoundingClientRect();
            return !hostRect || rect.width > hostRect.width + 2 || rect.width < 44;
          });
          return {
            activeId: active?.id || '',
            v2Visible: !!v2Host && getComputedStyle(v2Host).display !== 'none' && v2Host.getBoundingClientRect().width > 0,
            documentScrollWidth: Math.ceil(document.documentElement.scrollWidth),
            documentClientWidth: Math.ceil(document.documentElement.clientWidth),
            bodyScrollWidth: Math.ceil(document.body.scrollWidth),
            bodyClientWidth: Math.ceil(document.body.clientWidth),
            cardCount: cards.length,
            cardFitFailures: cardFitFailures.length,
            verticalTitles: titles.filter((item) => item.text.length >= 4 && item.width <= 24 && item.height > 80).length,
            badActions: actions.filter((item) => item.width < 44 || item.height < 44 || !item.icon).length,
            visibleTables: visibleTables.length,
            v2Loaded: !!window.Thon09MobileComponents,
            legacyLoaded: !!window.Thon09MobileDesignSystem
          };
        });

        expect(metrics.activeId).toBe(`${screen}Screen`);
        expect(metrics.v2Loaded).toBe(true);
        expect(metrics.legacyLoaded).toBe(false);
        expect(metrics.documentScrollWidth).toBeLessThanOrEqual(metrics.documentClientWidth + 2);
        if (width <= 1024) {
          expect(metrics.bodyScrollWidth).toBeLessThanOrEqual(metrics.bodyClientWidth + 2);
          expect(metrics.v2Visible).toBe(true);
          expect(metrics.cardFitFailures).toBe(0);
          expect(metrics.verticalTitles).toBe(0);
          expect(metrics.badActions).toBe(0);
          expect(metrics.visibleTables).toBe(0);
        } else {
          expect(metrics.v2Visible).toBe(false);
        }
      }
    });
  }
});
