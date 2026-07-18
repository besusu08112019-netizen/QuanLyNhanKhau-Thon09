const { test, expect, chromium } = require('@playwright/test');

const moduleOrderScreens = ['households', 'persons', 'temporaryResidence', 'temporaryAbsence', 'movements', 'publicAssets', 'houses', 'businessHouseholds', 'agriculture', 'livestock', 'vehicles', 'contributions'];
const screens = [
  'dashboard',
  ...moduleOrderScreens,
  'gis',
  'reports',
  'import',
  'exportExcel',
  'printForms',
  'operationCenter',
  'systemAdmin',
  'users',
  'permissions',
  'logs',
  'backups',
  'restore',
  'settings',
  'appearance'
];
const viewports = [
  { name: 'desktop', width: 1366, height: 768 },
  { name: 'tablet-portrait', width: 768, height: 1024 },
  { name: 'tablet-landscape', width: 1024, height: 768 },
  { name: 'mobile-portrait', width: 390, height: 844 },
  { name: 'mobile-landscape', width: 844, height: 390 }
];
const modalIds = ['householdModal', 'businessHouseholdModal', 'agriDetailModal', 'agriFormModal', 'personModal', 'houseDetailModal', 'houseFormModal', 'detailModal', 'livestockHouseholdModal', 'publicAssetDetailModal', 'publicAssetFormModal', 'publicAssetInventoryModal'];
const mojibakePattern = /(?:\u00e1[\u00bb\u00ba]|\u00c4\u2018|\u00c6[\u00b0\u00a1]|\u00c3[\u00a1\u00a0\u00a2\u00aa\u00b4\u00b9\u00ba\u00b3\u00b2\u00b5\u00a8\u00a9]|\uFFFD|\? d\?|\?n kh|\?o c)/i;

function ok(data) {
  return { ok: true, success: true, data };
}

async function mockApis(page) {
  await page.route('**/api/**', async (route) => {
    const url = route.request().url();
    const fulfill = (data) => route.fulfill({ contentType: 'application/json', body: JSON.stringify(ok(data)) });
    if (url.includes('/api/public/login-config')) return fulfill({ settings: { systemName: 'H? th?ng qu?n l� h�nh ch�nh', hamletName: 'Th�n 09', communeName: 'X� H?ng Phong', version: 'v2.0' }, metrics: {} });
    if (url.includes('/api/auth/me')) return fulfill({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.includes('/api/dashboard/summary')) return fulfill({ metrics: {}, charts: {}, generatedAt: new Date().toISOString() });
    if (url.includes('/api/dashboard/')) return fulfill({ metrics: {}, charts: {}, kpis: [], generatedAt: new Date().toISOString() });
    if (url.includes('/api/operation-center/')) return fulfill({ items: [], total: 0, widget: 'audit', data: { items: [] } });
    if (url.includes('/api/gis/households')) return fulfill({ items: [], total: 0 });
    if (url.includes('/api/gis/summary')) return fulfill({ total: 0, located: 0, missing: 0, areas: [] });
    if (url.includes('/api/household-business')) return fulfill({ items: [], total: 0, page: 1, pageSize: 20, dashboard: {} });
    if (url.includes('/api/livestock')) return fulfill({ items: [], total: 0, page: 1, pageSize: 20, kpis: {} });
    if (url.includes('/api/agriculture')) return fulfill({ items: [], total: 0, page: 1, pageSize: 20, kpis: {} });
    if (url.includes('/api/public-assets/catalogs')) return fulfill({ types: [], areas: [], statuses: [] });
    if (url.includes('/api/public-assets/dashboard')) return fulfill({ metrics: {}, charts: {} });
    if (url.includes('/api/public-assets/gis')) return fulfill({ items: [] });
    if (url.includes('/api/public-assets')) return fulfill({ items: [], total: 0, page: 1, pageSize: 20, totalPages: 1 });
    if (url.includes('/api/households')) return fulfill({ items: [], total: 0, page: 1, pageSize: 20 });
    if (url.includes('/api/persons')) return fulfill({ items: [], total: 0, page: 1, pageSize: 20 });
    return fulfill({ items: [], total: 0 });
  });
}

async function openApp(page, viewport) {
  await page.setViewportSize({ width: viewport.width, height: viewport.height });
  await mockApis(page);
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };
    window.App.token = 'test-token';
    window.App.user = user;
    localStorage.setItem('thon09_token', 'test-token');
    localStorage.setItem('thon09_user', JSON.stringify(user));
    if (typeof window.showApp === 'function') window.showApp();
  });
  await expect(page.locator('#appView')).not.toHaveClass(/d-none/);
}

function browserName() {
  return process.env.PW_BROWSER_LABEL || 'chromium';
}

function attachRuntimeErrorAudit(page) {
  const errors = [];
  page.on('console', message => {
    if (message.type() === 'error') errors.push('console: ' + message.text());
  });
  page.on('pageerror', error => errors.push('pageerror: ' + error.message));
  page.on('requestfailed', request => {
    let url;
    try {
      url = new URL(request.url());
    } catch (error) {
      return;
    }
    if (['127.0.0.1', 'localhost'].includes(url.hostname)) {
      errors.push('requestfailed: ' + request.method() + ' ' + url.pathname + ' ' + (request.failure()?.errorText || 'failed'));
    }
  });
  page.on('response', response => {
    let url;
    try {
      url = new URL(response.url());
    } catch (error) {
      return;
    }
    if (['127.0.0.1', 'localhost'].includes(url.hostname) && url.pathname.startsWith('/api/') && response.status() >= 400) {
      errors.push('response: ' + response.status() + ' ' + url.pathname);
    }
  });
  return errors;
}

test.describe(`Production UI audit (${browserName()})`, () => {
  for (const viewport of viewports) {
    test(`module layout, text and controls: ${viewport.name}`, async ({ page }) => {
      const runtimeErrors = attachRuntimeErrorAudit(page);
      await openApp(page, viewport);
      for (const screen of screens) {
        await page.evaluate((target) => window.Thon09NavigationController?.navigate(target), screen);
        await page.waitForTimeout(100);
        const result = await page.evaluate(({ screen, width, moduleOrderScreens }) => {
          const active = document.querySelector('.screen.active');
          const visible = (el) => {
            if (!el) return false;
            const style = getComputedStyle(el);
              if (el.closest('.mobile-filter-sheet') && el.closest('.mobile-filter-sheet').getAttribute('aria-hidden') === 'true') return;
            const rect = el.getBoundingClientRect();
            return style.visibility !== 'hidden' && style.display !== 'none' && rect.width > 0 && rect.height > 0;
          };
          const textNodes = Array.from(document.querySelectorAll('#screenTitle,#breadcrumbTrail,.sidebar,.topbar,.screen.active'))
            .filter(visible)
            .map((el) => el.innerText || '')
            .join('\n');
          const auditRoot = active?.querySelector('.app-v2-module-screen, .app-v2-module-dashboard') || active;
          const navItems = Array.from(document.querySelectorAll('.mobile-bottom-nav [data-mobile-screen]')).map((btn) => btn.dataset.mobileScreen);
          const sidebarModuleItems = Array.from(document.querySelectorAll('.sidebar .nav-section .nav-link[data-screen]'))
            .map((btn) => btn.dataset.screen)
            .filter((item) => moduleOrderScreens.includes(item));
          const touchFailures = [];
          if (width <= 820 && auditRoot) {
            Array.from(auditRoot.querySelectorAll('button:not([disabled]), .btn:not([disabled]), input:not([type="hidden"]), select, textarea, a[href]')).filter(visible).forEach((el) => {
              if (el.closest('.mobile-filter-sheet') && el.closest('.mobile-filter-sheet').getAttribute('aria-hidden') === 'true') return;
              let rect = el.getBoundingClientRect();
              if (el.matches('input[type="checkbox"], input[type="radio"]') && el.closest('label')) {
                const labelRect = el.closest('label').getBoundingClientRect();
                if (labelRect.width >= rect.width && labelRect.height >= rect.height) rect = labelRect;
              }
              if (Math.ceil(rect.width) < 44 || Math.ceil(rect.height) < 44) touchFailures.push((el.id || el.name || el.textContent || el.getAttribute('aria-label') || el.tagName).trim().slice(0, 60));
            });
          }
          const cardSelector = auditRoot?.classList?.contains('app-v2-module-screen') || auditRoot?.classList?.contains('app-v2-module-dashboard')
            ? '.app-v2-card,.app-v2-stat-card,.app-v2-panel,.app-v2-record-card'
            : '.content-card,.module-filter-card,.dashboard-panel,.livestock-filter-card,.agri-filter-card,.houses-filter-card';
          const cardStyles = Array.from((auditRoot || document).querySelectorAll(cardSelector)).filter(visible).slice(0, 8).map((el) => {
            const cs = getComputedStyle(el);
            const childPaddings = Array.from(el.children).slice(0, 3).map((child) => parseFloat(getComputedStyle(child).paddingTop) || 0);
            return { radius: parseFloat(cs.borderTopLeftRadius) || 0, padding: Math.max(parseFloat(cs.paddingTop) || 0, ...childPaddings) };
          });
          const focusable = auditRoot && Array.from(auditRoot.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href]')).find(visible);
          let focusOk = true;
          if (focusable) {
            focusable.focus();
            focusOk = document.activeElement === focusable;
          }
          return {
            activeId: active ? active.id : '',
            scrollWidth: Math.ceil(document.documentElement.scrollWidth),
            clientWidth: Math.ceil(document.documentElement.clientWidth),
            bodyScrollWidth: active ? Math.ceil(active.scrollWidth) : Math.ceil(document.body.scrollWidth),
            bodyClientWidth: active ? Math.ceil(active.clientWidth) : Math.ceil(document.body.clientWidth),
            navItems,
            sidebarModuleItems,
            text: textNodes,
            touchFailures,
            cardStyles,
            focusOk
          };
        }, { screen, width: viewport.width, moduleOrderScreens });

        expect(result.activeId).toBe(`${screen}Screen`);
        expect(result.scrollWidth).toBeLessThanOrEqual(result.clientWidth + 2);
        expect(result.bodyScrollWidth).toBeLessThanOrEqual(result.bodyClientWidth + 96);
        expect(result.navItems).not.toContain('operationCenter');
        expect(result.navItems.length).toBeLessThanOrEqual(5);
        expect(result.navItems.filter((item) => moduleOrderScreens.includes(item))).toEqual(['households', 'persons']);
        expect(result.sidebarModuleItems).toEqual(moduleOrderScreens);
        expect(result.text).not.toMatch(mojibakePattern);
        expect(result.touchFailures, `${viewport.name}/${screen} touch target failures`).toEqual([]);
        expect(result.focusOk).toBe(true);
        for (const card of result.cardStyles) {
          expect(card.radius).toBeGreaterThanOrEqual(8);
          expect(card.padding).toBeGreaterThanOrEqual(12);
        }
      }
      expect(runtimeErrors).toEqual([]);
    });
  }

  for (const viewport of viewports.filter((item) => item.name !== 'desktop' || true)) {
    test(`popup, form and table baseline: ${viewport.name}`, async ({ page }) => {
      const runtimeErrors = attachRuntimeErrorAudit(page);
      await openApp(page, viewport);
      for (const id of modalIds) {
        const exists = await page.locator(`#${id}`).count();
        if (!exists) continue;
        await page.evaluate((modalId) => {
          document.querySelectorAll('.modal.show').forEach((el) => { el.classList.remove('show'); el.style.display = 'none'; });
          const el = document.getElementById(modalId);
          el.style.display = 'block';
          el.classList.add('show');
          el.removeAttribute('aria-hidden');
          document.body.classList.add('modal-open');
        }, id);
        await page.waitForTimeout(60);
        const modal = await page.evaluate((modalId) => {
          const el = document.getElementById(modalId);
          const dialog = el && el.querySelector('.modal-dialog');
          const content = el && el.querySelector('.modal-content');
          const header = el && el.querySelector('.modal-header');
          const footer = el && el.querySelector('.modal-footer');
          const body = el && el.querySelector('.modal-body');
          const rect = dialog ? dialog.getBoundingClientRect() : null;
          const contentRect = content ? content.getBoundingClientRect() : null;
          const headerRect = header ? header.getBoundingClientRect() : null;
          const footerRect = footer ? footer.getBoundingClientRect() : null;
          const contentStyle = content ? getComputedStyle(content) : null;
          const headerStyle = header ? getComputedStyle(header) : null;
          const bodyStyle = body ? getComputedStyle(body) : null;
          const footerStyle = footer ? getComputedStyle(footer) : null;
          const controls = Array.from(el.querySelectorAll('input:not([type="hidden"]), select, textarea')).filter((control) => getComputedStyle(control).display !== 'none');
          const unlabeled = controls.filter((control) => {
            const id = control.id;
            return !(control.getAttribute('aria-label') || control.closest('label') || (id && el.querySelector(`label[for="${CSS.escape(id)}"]`)) || control.previousElementSibling?.tagName === 'LABEL' || control.parentElement?.querySelector('label'));
          }).map((control) => control.name || control.id || control.tagName).slice(0, 8);
          return {
            rect, contentRect, headerRect, footerRect,
            bodyScrollable: body ? body.scrollHeight >= body.clientHeight : true,
            commonModal: el.classList.contains('common-modal') && el.dataset.commonModal === 'true',
            dialogScrollable: dialog ? dialog.classList.contains('modal-dialog-scrollable') : false,
            contentDisplay: contentStyle ? contentStyle.display : '',
            contentRows: contentStyle ? contentStyle.gridTemplateRows : '',
            contentRadius: contentStyle ? parseFloat(contentStyle.borderTopLeftRadius) || 0 : 0,
            contentShadow: contentStyle ? contentStyle.boxShadow : '',
            headerPaddingTop: headerStyle ? parseFloat(headerStyle.paddingTop) || 0 : null,
            bodyPaddingTop: bodyStyle ? parseFloat(bodyStyle.paddingTop) || 0 : null,
            bodyOverflowY: bodyStyle ? bodyStyle.overflowY : '',
            footerPaddingTop: footerStyle ? parseFloat(footerStyle.paddingTop) || 0 : null,
            unlabeled
          };
        }, id);
        const expectedWidth = Math.min(1200, viewport.width - (viewport.width <= 820 ? 16 : 32));
        const widthTolerance = viewport.width <= 430 ? 8 : 28;
        expect(modal.rect, id).toBeTruthy();
        expect(modal.rect.left).toBeGreaterThanOrEqual(-2);
        expect(modal.rect.right).toBeLessThanOrEqual(viewport.width + 2);
        expect(Math.abs((modal.rect.left + modal.rect.width / 2) - viewport.width / 2), `${id} centered`).toBeLessThanOrEqual(3);
        expect(modal.rect.width, `${id} common width`).toBeGreaterThanOrEqual(expectedWidth - widthTolerance);
        expect(modal.rect.width, `${id} common width`).toBeLessThanOrEqual(expectedWidth + 3);
        expect(modal.contentRect.height).toBeLessThanOrEqual(viewport.height - (viewport.width <= 820 ? 16 : 32) + 2);
        expect(modal.commonModal, `${id} uses CommonModal`).toBe(true);
        expect(modal.dialogScrollable, `${id} scrollable dialog`).toBe(true);
        expect(modal.contentDisplay, `${id} content grid`).toBe('grid');
        expect(modal.contentRows, `${id} content rows`).not.toBe('');
        expect(modal.contentRadius, `${id} radius`).toBeGreaterThanOrEqual(viewport.width <= 820 ? 16 : 18);
        expect(modal.contentShadow, `${id} shadow`).not.toBe('none');
        expect(modal.headerPaddingTop, `${id} header padding`).toBeGreaterThanOrEqual(viewport.width <= 820 ? 12 : 18);
        expect(modal.bodyPaddingTop, `${id} body padding`).toBeGreaterThanOrEqual(viewport.width <= 820 ? 14 : 22);
        expect(modal.bodyOverflowY, `${id} body scroll`).toMatch(/auto|scroll/);
        if (modal.footerPaddingTop !== null) {
          expect(modal.footerPaddingTop, `${id} footer padding`).toBeGreaterThanOrEqual(viewport.width <= 820 ? 12 : 16);
        }
        if (modal.headerRect) {
          expect(modal.headerRect.top).toBeGreaterThanOrEqual(-2);
          expect(modal.headerRect.bottom).toBeLessThanOrEqual(viewport.height + 2);
        }
        if (modal.footerRect) {
          expect(modal.footerRect.top).toBeGreaterThanOrEqual(-2);
          expect(modal.footerRect.bottom).toBeLessThanOrEqual(viewport.height + 2);
        }
        expect(modal.unlabeled, `${id} unlabeled controls`).toEqual([]);
      }
      await page.evaluate(() => { document.querySelectorAll('.modal.show').forEach((el) => { el.classList.remove('show'); el.style.display = 'none'; }); document.body.classList.remove('modal-open'); });
      expect(runtimeErrors).toEqual([]);
    });
  }

  test('system admin destructive actions use the shared confirm dialog on mobile', async ({ page }) => {
    const runtimeErrors = attachRuntimeErrorAudit(page);
    await openApp(page, { name: 'mobile-portrait', width: 390, height: 844 });
    await page.evaluate(() => window.Thon09NavigationController?.navigate('systemAdmin'));
    await page.waitForTimeout(200);
    const backupAction = page.locator('#systemAdminScreen .app-v2-button[data-app-v2-proxy-click*="systemAdmin.backup"], #systemAdminScreen .app-v2-fab[data-app-v2-proxy-click*="systemAdmin.backup"]').first();
    if (await backupAction.count()) {
      await backupAction.click();
    } else {
      await page.evaluate(() => {
        const button = document.querySelector('[data-platform-action="systemAdmin.backup"][data-system-backup="database"]');
        button?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
      });
    }
    const dialog = page.locator('.platform-confirm-dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText('Xác nhận tạo backup');
    await expect(dialog).toContainText('Tạo backup database ngay bây giờ?');
    await expect(page.locator('.platform-confirm-footer .btn-danger')).toContainText('Tạo backup');
    expect(runtimeErrors).toEqual([]);
  });
});
