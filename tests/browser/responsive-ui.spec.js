const { test, expect } = require('@playwright/test');

const widths = [320, 360, 375, 390, 412, 430, 768, 820, 853, 912, 1024, 1280, 1440, 1920];
const moduleOrderScreens = ['households', 'persons', 'temporaryResidence', 'temporaryAbsence', 'movements', 'publicAssets', 'houses', 'businessHouseholds', 'agriculture', 'livestock', 'vehicles', 'contributions'];
const mobileScreens = moduleOrderScreens;
const bottomNavScreens = ['dashboard', 'households', 'persons', 'gis', 'reports'];
const qaScreens = ['dashboard', 'households', 'persons', 'gis', 'reports', 'contributions', 'vehicles', 'businessHouseholds', 'agriculture', 'livestock', 'publicAssets', 'houses', 'operationCenter', 'import', 'exportExcel', 'printForms', 'systemAdmin', 'users', 'permissions', 'logs', 'backups', 'restore', 'settings', 'appearance'];

async function mockApis(page) {
  await page.route('**/api/**', async (route) => {
    const url = route.request().url();
    const payload = (data) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) });

    if (url.includes('/api/public/login-config')) return payload({
      settings: { systemName: 'Thong 09', hamletName: 'Thon 09', communeName: 'Hong Phong', version: 'v2.0' },
      metrics: {}
    });
    if (url.includes('/api/auth/me')) return payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.includes('/api/dashboard/summary')) return payload({ metrics: {}, charts: {}, generatedAt: new Date().toISOString() });
    if (url.includes('/api/dashboard/')) return payload({ metrics: {}, charts: {}, kpis: [], generatedAt: new Date().toISOString() });
    if (url.includes('/api/gis/households')) return payload({ items: [], total: 0 });
    if (url.includes('/api/gis/summary')) return payload({ total: 0, located: 0, missing: 0, areas: [] });
    if (url.includes('/api/household-business')) return payload({ items: [], total: 0, page: 1, pageSize: 20, dashboard: {} });
    if (url.includes('/api/livestock')) return payload({ items: [], total: 0, page: 1, pageSize: 20, kpis: {} });
    if (url.includes('/api/agriculture')) return payload({ items: [], total: 0, page: 1, pageSize: 20, kpis: {} });
    if (url.includes('/api/public-assets/catalogs')) return payload({ types: [], areas: [], statuses: [] });
    if (url.includes('/api/public-assets/dashboard')) return payload({ metrics: {}, charts: {} });
    if (url.includes('/api/public-assets/gis')) return payload({ items: [] });
    if (url.includes('/api/public-assets')) return payload({ items: [], total: 0, page: 1, pageSize: 20, totalPages: 1 });
    if (url.includes('/api/households')) return payload({ items: [], total: 0, page: 1, pageSize: 20 });
    if (url.includes('/api/persons')) return payload({ items: [], total: 0, page: 1, pageSize: 20 });
    return payload({ items: [], total: 0 });
  });
}

async function openAuthenticatedApp(page, width) {
  await page.setViewportSize({ width, height: 900 });
  await mockApis(page);
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };
    App.token = 'test-token';
    App.csrfToken = 'test-csrf';
    App.user = user;
    window.App = App;
    localStorage.setItem('thon09_token', 'test-token');
    localStorage.setItem('thon09_csrf', 'test-csrf');
    localStorage.setItem('thon09_user', JSON.stringify(user));
    if (typeof window.showApp === 'function') window.showApp();
  });
  await expect(page.locator('#appView')).not.toHaveClass(/d-none/);
}

async function clickSidebarModule(page, screen) {
  const item = page.locator(`.sidebar .nav-link[data-screen="${screen}"]`).first();
  await item.evaluate((button) => {
    const section = button.closest('.nav-section');
    const toggle = section?.querySelector(':scope > .nav-section-title');
    if (section) {
      section.classList.add('is-open');
      section.classList.remove('is-collapsed');
    }
    if (toggle) toggle.setAttribute('aria-expanded', 'true');
    button.scrollIntoView({ block: 'center', inline: 'nearest' });
  });
  await item.click();
}

test.describe('responsive system navigation audit', () => {
  for (const width of widths) {
    test(`main modules do not overflow at ${width}px`, async ({ page }) => {
      await openAuthenticatedApp(page, width);

      for (const screen of mobileScreens) {
        await page.evaluate((target) => window.Thon09NavigationController?.navigate(target), screen);
        await page.waitForTimeout(120);
        const metrics = await page.evaluate(() => {
          const active = document.querySelector('.screen.active');
          const navItems = Array.from(document.querySelectorAll('.mobile-bottom-nav [data-mobile-screen]')).map((btn) => btn.dataset.mobileScreen);
          const nav = document.querySelector('.mobile-bottom-nav');
          const navStyle = nav ? getComputedStyle(nav) : null;
          const buttons = nav ? Array.from(nav.querySelectorAll('[data-mobile-screen]')) : [];
          const navRect = nav ? nav.getBoundingClientRect() : null;
          const activeButton = nav ? nav.querySelector('[data-mobile-screen].active') : null;
          const activeRect = activeButton ? activeButton.getBoundingClientRect() : null;
          const touchFailures = buttons
            .map((btn) => {
              const rect = btn.getBoundingClientRect();
              return { screen: btn.dataset.mobileScreen, width: Math.round(rect.width), height: Math.round(rect.height), top: Math.round(rect.top) };
            })
            .filter((item) => item.width < 48 || item.height < 48);
          const rowTops = buttons.map((btn) => Math.round(btn.offsetTop || btn.getBoundingClientRect().top));
          const rowSpread = rowTops.length ? Math.max(...rowTops) - Math.min(...rowTops) : 0;
          const mobileTables = Array.from((active || document).querySelectorAll('.mobile-list-ready > table.mobile-source-table')).map((table) => {
            const rect = table.getBoundingClientRect();
            const style = getComputedStyle(table);
            return { width: Math.round(rect.width), height: Math.round(rect.height), position: style.position };
          });
          return {
            activeId: active ? active.id : '',
            scrollWidth: Math.ceil(document.documentElement.scrollWidth),
            clientWidth: Math.ceil(document.documentElement.clientWidth),
            bodyScrollWidth: Math.ceil(document.body.scrollWidth),
            bodyClientWidth: Math.ceil(document.body.clientWidth),
            navVisible: !!nav && getComputedStyle(nav).display !== 'none',
            navDisplay: navStyle ? navStyle.display : '',
            navScrollWidth: nav ? Math.ceil(nav.scrollWidth) : 0,
            navClientWidth: nav ? Math.ceil(nav.clientWidth) : 0,
            activeCenterOffset: navRect && activeRect ? Math.abs((activeRect.left + activeRect.width / 2) - (navRect.left + navRect.width / 2)) : 0,
            touchFailures,
            rowSpread,
            navItems,
            mobileTables
          };
        });

        expect(metrics.activeId).toBe(`${screen}Screen`);
        expect(metrics.scrollWidth).toBeLessThanOrEqual(metrics.clientWidth + 2);
        expect(metrics.bodyScrollWidth).toBeLessThanOrEqual(metrics.bodyClientWidth + 2);
        expect(metrics.navItems).not.toContain('operationCenter');
        if (width <= 820) {
          expect(metrics.navVisible).toBe(true);
          expect(metrics.navDisplay).toBe('flex');
          expect(metrics.navItems.length).toBeLessThanOrEqual(5);
          expect(metrics.navScrollWidth).toBeLessThanOrEqual(metrics.navClientWidth + 2);
          expect(metrics.touchFailures).toEqual([]);
          expect(metrics.rowSpread).toBeLessThanOrEqual(2);
          expect(metrics.activeCenterOffset).toBeLessThanOrEqual(Math.max(120, metrics.navClientWidth * 0.42));
          expect(metrics.navItems).toEqual(bottomNavScreens);
          expect(metrics.mobileTables.every((table) => table.width <= 1 && table.height <= 1 && table.position === 'absolute')).toBe(true);
        }
      }
    });
  }

  test('mobile sidebar masks GIS controls and keeps navigation layers below it', async ({ page }) => {
    await openAuthenticatedApp(page, 390);
    await page.evaluate(() => window.Thon09NavigationController?.navigate('gis'));
    await page.waitForTimeout(200);

    await page.locator('#sidebarToggle').click();
    await expect(page.locator('body')).toHaveClass(/sidebar-open/);

    const layerMetrics = await page.evaluate(() => {
      const sidebar = document.querySelector('.sidebar');
      const backdrop = document.querySelector('.sidebar-backdrop');
      const nav = document.querySelector('.mobile-bottom-nav');
      const leafletControl = document.querySelector('.leaflet-control-container');
      const sidebarRect = sidebar ? sidebar.getBoundingClientRect() : null;
      const backdropRect = backdrop ? backdrop.getBoundingClientRect() : null;
      const sidebarStyle = sidebar ? getComputedStyle(sidebar) : null;
      const backdropStyle = backdrop ? getComputedStyle(backdrop) : null;
      const navStyle = nav ? getComputedStyle(nav) : null;
      const leafletStyle = leafletControl ? getComputedStyle(leafletControl) : null;
      return {
        sidebarWidth: sidebarRect ? Math.round(sidebarRect.width) : 0,
        viewportWidth: window.innerWidth,
        backdropCoversViewport: !!backdropRect && Math.round(backdropRect.left) <= 0 && Math.round(backdropRect.top) <= 0 && Math.round(backdropRect.width) >= window.innerWidth && Math.round(backdropRect.height) >= window.innerHeight,
        sidebarZ: sidebarStyle ? Number(sidebarStyle.zIndex) : 0,
        backdropZ: backdropStyle ? Number(backdropStyle.zIndex) : 0,
        navZ: navStyle ? Number(navStyle.zIndex) : 0,
        navPointerEvents: navStyle ? navStyle.pointerEvents : '',
        leafletOpacity: leafletStyle ? leafletStyle.opacity : '',
        leafletVisibility: leafletStyle ? leafletStyle.visibility : '',
        leafletPointerEvents: leafletStyle ? leafletStyle.pointerEvents : ''
      };
    });

    expect(layerMetrics.sidebarWidth).toBeGreaterThanOrEqual(Math.floor(layerMetrics.viewportWidth * 0.8));
    expect(layerMetrics.sidebarWidth).toBeLessThanOrEqual(Math.ceil(layerMetrics.viewportWidth * 0.85));
    expect(layerMetrics.backdropCoversViewport).toBe(true);
    expect(layerMetrics.sidebarZ).toBeGreaterThan(layerMetrics.backdropZ);
    expect(layerMetrics.backdropZ).toBeGreaterThan(layerMetrics.navZ);
    expect(layerMetrics.navPointerEvents).toBe('none');
    expect(layerMetrics.leafletOpacity).toBe('0');
    expect(layerMetrics.leafletVisibility).toBe('hidden');
    expect(layerMetrics.leafletPointerEvents).toBe('none');

    await page.mouse.click(385, 450);
    await expect(page.locator('body')).not.toHaveClass(/sidebar-open/);
  });

  test('filter icons follow desktop and compact responsive contract', async ({ page }) => {
    for (const width of [1024, 1366]) {
      await openAuthenticatedApp(page, width);
      for (const screen of mobileScreens) {
        await page.evaluate((target) => window.Thon09NavigationController?.navigate(target), screen);
        await page.waitForTimeout(120);
        const desktopFilterIcons = await page.evaluate(() => Array.from(document.querySelectorAll('.screen.active :is(.mobile-filter-trigger, .mobile-filter-toggle, .mobile-filter-shell)')).filter((node) => {
          const style = getComputedStyle(node);
          const rect = node.getBoundingClientRect();
          return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 1 && rect.height > 1;
        }).length);
        expect(desktopFilterIcons).toBe(0);
      }
    }

    await openAuthenticatedApp(page, 390);
    for (const screen of mobileScreens) {
      await page.evaluate((target) => window.Thon09NavigationController?.navigate(target), screen);
      await page.waitForTimeout(120);
      const compactState = await page.evaluate(() => Array.from(document.querySelectorAll('.screen.active .mobile-filter-system')).map((filter) => {
        const icon = filter.querySelector('.mobile-filter-trigger, .mobile-filter-toggle');
        const iconVisible = !!icon && (() => {
          const style = getComputedStyle(icon);
          const rect = icon.getBoundingClientRect();
          return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 1 && rect.height > 1;
        })();
        const extraVisible = Array.from(filter.querySelectorAll('.mobile-filter-extra')).some((node) => {
          const style = getComputedStyle(node);
          const rect = node.getBoundingClientRect();
          return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 1 && rect.height > 1;
        });
        return { iconVisible, extraVisible };
      }));
      expect(compactState.every((item) => !(item.iconVisible && item.extraVisible))).toBe(true);
    }
  });

  test('sidebar uses one shared accordion architecture across modules', async ({ page }) => {
    await openAuthenticatedApp(page, 1366);
    const screens = ['dashboard', 'households', 'persons', 'gis', 'reports', 'publicAssets', 'agriculture', 'livestock', 'vehicles', 'contributions'];

    for (const screen of screens) {
      await page.evaluate((target) => window.Thon09NavigationController?.navigate(target), screen);
      await page.waitForTimeout(120);
      const sidebar = await page.evaluate((target) => {
        const sidebars = Array.from(document.querySelectorAll('.sidebar.gov-sidebar'));
        const navs = Array.from(document.querySelectorAll('.gov-nav'));
        const sections = Array.from(document.querySelectorAll('.gov-nav > .nav-section'));
        const activeButton = document.querySelector(`.gov-nav .nav-link[data-screen="${target}"]`);
        const activeSection = activeButton?.closest('.nav-section');
        return {
          sidebarCount: sidebars.length,
          navCount: navs.length,
          dashboardTreeCount: document.querySelectorAll('.dashboard-tree, [data-dashboard-tree], .dashboard-tree-toggle, .dashboard-tree-children').length,
          sectionCount: sections.length,
          uniformSections: sections.every((section) => section.classList.contains('sidebar-accordion-section') && section.querySelector(':scope > .sidebar-accordion-toggle') && section.querySelector(':scope > .sidebar-accordion-panel')),
          activeScreen: document.querySelector('.screen.active')?.id || '',
          activeSectionOpen: !!activeSection && activeSection.classList.contains('is-open'),
          activeSectionExpanded: activeSection?.querySelector(':scope > .sidebar-accordion-toggle')?.getAttribute('aria-expanded') || ''
        };
      }, screen);

      expect(sidebar.sidebarCount).toBe(1);
      expect(sidebar.navCount).toBe(1);
      expect(sidebar.dashboardTreeCount).toBe(0);
      expect(sidebar.sectionCount).toBeGreaterThan(1);
      expect(sidebar.uniformSections).toBe(true);
      expect(sidebar.activeScreen).toBe(`${screen}Screen`);
      expect(sidebar.activeSectionOpen).toBe(true);
      expect(sidebar.activeSectionExpanded).toBe('true');
    }
  });

  test('module navigation clicks activate the requested screens', async ({ page }) => {
    await openAuthenticatedApp(page, 390);
    const isMobile = await page.evaluate(() => window.innerWidth <= 820);

    if (isMobile) {
      await page.locator('#sidebarToggle').click();
      await expect(page.locator('body')).toHaveClass(/sidebar-open/);
    }
    for (const screen of moduleOrderScreens) {
      await clickSidebarModule(page, screen);
      await page.waitForTimeout(120);
      const sidebarState = await page.evaluate((target) => ({
        appScreen: window.App && window.App.screen,
        activeId: document.querySelector('.screen.active')?.id || '',
        activeNav: document.querySelector('.sidebar .nav-link.active')?.dataset.screen || ''
      }), screen);
      expect(sidebarState.activeId).toBe(`${screen}Screen`);
      expect(sidebarState.activeNav).toBe(screen);
      expect(sidebarState.appScreen).toBe(screen);
      await page.evaluate(() => document.querySelectorAll('.toast').forEach((toast) => toast.remove()));
      if (isMobile) {
        await page.locator('#sidebarToggle').click();
        await expect(page.locator('body')).toHaveClass(/sidebar-open/);
      }
    }
    if (isMobile) {
      const size = page.viewportSize() || { width: 390, height: 844 };
      await page.mouse.click(size.width - 8, Math.floor(size.height / 2));
      await expect(page.locator('body')).not.toHaveClass(/sidebar-open/);
    }

    for (const screen of bottomNavScreens) {
      await page.evaluate(() => document.querySelectorAll('.toast').forEach((toast) => toast.remove()));
      await page.locator(`.mobile-bottom-nav [data-mobile-screen="${screen}"]`).first().click();
      await page.waitForTimeout(120);
      const mobileState = await page.evaluate((target) => ({
        appScreen: window.App && window.App.screen,
        activeId: document.querySelector('.screen.active')?.id || '',
        activeMobile: document.querySelector('.mobile-bottom-nav [data-mobile-screen].active')?.dataset.mobileScreen || ''
      }), screen);
      expect(mobileState.activeId).toBe(`${screen}Screen`);
      expect(mobileState.activeMobile).toBe(screen);
      expect(mobileState.appScreen).toBe(screen);
      await page.evaluate(() => document.querySelectorAll('.toast').forEach((toast) => toast.remove()));
    }
  });

  test('narrow desktop sidebar keeps module clicks inside the viewport', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-chromium', 'Desktop sidebar geometry only applies to the desktop project.');
    await openAuthenticatedApp(page, 960);
    await page.setViewportSize({ width: 960, height: 760 });
    await page.evaluate(() => {
      localStorage.setItem('thon09_dashboard_tree_open', '1');
      window.Thon09NavigationController?.navigate('gis');
      const sidebar = document.querySelector('.sidebar');
      if (sidebar) sidebar.scrollTop = 0;
      window.scrollTo(0, 0);
    });
    await page.waitForTimeout(200);
    await page.evaluate(() => {
      const sidebar = document.querySelector('.sidebar');
      if (sidebar) sidebar.scrollTop = 0;
      window.scrollTo(0, 0);
      document.querySelector('.sidebar .nav-link[data-screen="households"]')?.scrollIntoView({ block: 'center', inline: 'nearest' });
    });

    const before = await page.evaluate(() => {
      const button = document.querySelector('.sidebar .nav-link[data-screen="households"]');
      const sidebar = document.querySelector('.sidebar');
      const rect = button.getBoundingClientRect();
      const sidebarRect = sidebar.getBoundingClientRect();
      const topElement = document.elementFromPoint(rect.left + rect.width / 2, rect.top + rect.height / 2);
      return {
        appScreen: window.App && window.App.screen,
        sidebarLeft: Math.round(sidebarRect.left),
        sidebarTransform: getComputedStyle(sidebar).transform,
        buttonTop: Math.round(rect.top),
        buttonLeft: Math.round(rect.left),
        buttonBottom: Math.round(rect.bottom),
        topScreen: topElement?.closest?.('[data-screen]')?.getAttribute('data-screen') || ''
      };
    });

    expect(before.appScreen).toBe('gis');
    expect(before.sidebarLeft).toBe(0);
    expect(before.sidebarTransform).toBe('none');
    expect(before.buttonLeft).toBeGreaterThanOrEqual(0);

    await clickSidebarModule(page, 'households');
    await page.waitForTimeout(200);

    const after = await page.evaluate(() => ({
      appScreen: window.App && window.App.screen,
      activeId: document.querySelector('.screen.active')?.id || '',
      activeNav: document.querySelector('.sidebar .nav-link.active')?.dataset.screen || ''
    }));

    expect(after.appScreen).toBe('households');
    expect(after.activeId).toBe('householdsScreen');
    expect(after.activeNav).toBe('households');
  });

  test('full responsive QA contract across requested breakpoints', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-chromium', 'Full breakpoint matrix runs once; device projects run the focused responsive suite.');
    test.setTimeout(180000);

    for (const width of widths) {
      await openAuthenticatedApp(page, width);
      await page.setViewportSize({ width, height: width >= 1280 ? 900 : 844 });

      for (const screen of qaScreens) {
        await page.evaluate((target) => window.Thon09NavigationController?.navigate(target), screen);
        await page.waitForTimeout(140);

        const audit = await page.evaluate(({ target, width }) => {
          const active = document.querySelector('.screen.active');
          const viewport = {
            width: Math.ceil(document.documentElement.clientWidth),
            height: Math.ceil(window.innerHeight)
          };
          const isVisible = (node) => {
            if (!node || node.nodeType !== 1) return false;
            if (node.closest('.mobile-list-surface[aria-hidden="true"]')) return false;
            if (node.closest('table.mobile-source-table')) return false;
            const style = getComputedStyle(node);
            const rect = node.getBoundingClientRect();
            return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 1 && rect.height > 1;
          };
          const ignoreHorizontalSpill = (node) => {
            return Boolean(node.closest('.table-responsive, .person-table-wrap, .report-preview, .import-result-card, .dropdown-menu, .tooltip, .popover'));
          };
          const nodes = Array.from((active || document).querySelectorAll('*')).filter(isVisible);
          const spills = nodes.filter((node) => {
            if (ignoreHorizontalSpill(node)) return false;
            const rect = node.getBoundingClientRect();
            return Math.floor(rect.left) < -2 || Math.ceil(rect.right) > viewport.width + 2;
          }).map((node) => ({
            tag: node.tagName,
            id: node.id || '',
            className: String(node.className || '').slice(0, 90),
            text: (node.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 70),
            left: Math.floor(node.getBoundingClientRect().left),
            right: Math.ceil(node.getBoundingClientRect().right)
          })).slice(0, 10);
          const textOverflow = nodes.filter((node) => {
            if (!node.matches('h1,h2,h3,h4,h5,h6,p,span,strong,small,label,.badge,.badge-soft,[class*="badge"],[class*="status"],[class*="pill"],[class*="tag"],.btn,button')) return false;
            if (node.closest('.mobile-bottom-nav')) return false;
            const style = getComputedStyle(node);
            if (style.overflowX === 'hidden' && style.textOverflow === 'ellipsis') return false;
            return node.scrollWidth > node.clientWidth + 2;
          }).map((node) => ({
            tag: node.tagName,
            id: node.id || '',
            className: String(node.className || '').slice(0, 90),
            text: (node.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 70),
            scrollWidth: node.scrollWidth,
            clientWidth: node.clientWidth
          })).slice(0, 10);
          const controlOverflow = nodes.filter((node) => {
            if (!node.matches('button,.btn,input:not([type="hidden"]),select,textarea,.input-group')) return false;
            const parent = node.parentElement;
            if (!parent || !isVisible(parent)) return false;
            if (node.closest('.mobile-bottom-nav')) return false;
            return node.getBoundingClientRect().width > parent.getBoundingClientRect().width + 2;
          }).map((node) => ({
            tag: node.tagName,
            id: node.id || '',
            className: String(node.className || '').slice(0, 90),
            text: (node.textContent || node.getAttribute('placeholder') || node.getAttribute('aria-label') || '').replace(/\s+/g, ' ').trim().slice(0, 70)
          })).slice(0, 10);
          const visibleTables = Array.from((active || document).querySelectorAll('table')).filter(isVisible);
          const nakedTables = visibleTables.filter((table) => {
            if (width > 1024) return false;
            return !table.closest('.table-responsive, .person-table-wrap, .report-preview, .import-result-card');
          }).map((table) => table.className || table.id || table.tagName);
          const modalOverflow = Array.from(document.querySelectorAll('.modal.show .modal-dialog')).filter(isVisible).filter((dialog) => {
            const rect = dialog.getBoundingClientRect();
            return rect.left < -2 || rect.right > viewport.width + 2 || rect.bottom > viewport.height + 2;
          }).map((dialog) => dialog.closest('.modal')?.id || dialog.className);
          const compactFilters = Array.from((active || document).querySelectorAll('.mobile-filter-system')).filter(isVisible).map((filter) => {
            const icon = filter.querySelector('.mobile-filter-trigger, .mobile-filter-toggle');
            const iconVisible = icon && isVisible(icon);
            const extraVisible = Array.from(filter.querySelectorAll('.mobile-filter-extra')).some(isVisible);
            return { iconVisible: Boolean(iconVisible), extraVisible };
          });
          const parseRgb = (value) => {
            const match = String(value || '').match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([.\d]+))?\)/);
            if (!match) return null;
            return { r: Number(match[1]), g: Number(match[2]), b: Number(match[3]), a: match[4] === undefined ? 1 : Number(match[4]) };
          };
          const bgColorOf = (node) => {
            var current = node;
            while (current && current !== document.documentElement) {
              const currentStyle = getComputedStyle(current);
              if (currentStyle.backgroundImage && currentStyle.backgroundImage !== 'none') return null;
              const color = parseRgb(currentStyle.backgroundColor);
              if (color && color.a > 0.95) return color;
              current = current.parentElement;
            }
            return parseRgb(getComputedStyle(document.body).backgroundColor) || { r: 255, g: 255, b: 255, a: 1 };
          };
          const colorDistance = (a, b) => a && b ? Math.abs(a.r - b.r) + Math.abs(a.g - b.g) + Math.abs(a.b - b.b) : 255;
          const iconFailures = Array.from((active || document).querySelectorAll('i.fa, i.fa-solid, i.fa-regular, i.fa-brands, i.fas, i.far, i.fab')).filter(isVisible).filter((icon) => {
            const rect = icon.getBoundingClientRect();
            const style = getComputedStyle(icon);
            const parent = icon.closest('button,.btn,.nav-link,.dropdown-item,.page-link,.badge,.badge-soft,[class*="badge"],[class*="status"],[class*="pill"],[class*="tag"]');
            const parentRect = parent ? parent.getBoundingClientRect() : null;
            const color = parseRgb(style.color);
            const background = bgColorOf(parent || icon.parentElement || icon);
            const clippedByParent = parentRect && (rect.left < parentRect.left - 1 || rect.right > parentRect.right + 1 || rect.top < parentRect.top - 1 || rect.bottom > parentRect.bottom + 1);
            const missingGlyph = style.fontFamily.toLowerCase().indexOf('font awesome') === -1 && !icon.className.includes('fa-');
            const tooSmall = Math.ceil(rect.width) < 12 || Math.ceil(rect.height) < 12;
            const sameAsBackground = colorDistance(color, background) < 18 && !(parent && parent.classList.contains('active'));
            return missingGlyph || tooSmall || clippedByParent || sameAsBackground;
          }).map((icon) => ({
            tag: icon.tagName,
            id: icon.id || '',
            className: String(icon.className || '').slice(0, 90),
            parent: icon.closest('button,.btn,.nav-link,.dropdown-item,.page-link')?.id || icon.closest('button,.btn,.nav-link,.dropdown-item,.page-link')?.textContent?.replace(/\s+/g, ' ').trim().slice(0, 60) || ''
          })).slice(0, 10);
          return {
            activeId: active ? active.id : '',
            docScroll: Math.ceil(document.documentElement.scrollWidth),
            docClient: Math.ceil(document.documentElement.clientWidth),
            bodyScroll: Math.ceil(document.body.scrollWidth),
            bodyClient: Math.ceil(document.body.clientWidth),
            activeScroll: active ? Math.ceil(active.scrollWidth) : 0,
            activeClient: active ? Math.ceil(active.clientWidth) : 0,
            spills,
            textOverflow,
            controlOverflow,
            nakedTables,
            modalOverflow,
            compactFilters,
            iconFailures
          };
        }, { target: screen, width });

        expect(audit.activeId, `${width}/${screen} active screen`).toBe(`${screen}Screen`);
        expect(audit.docScroll, `${width}/${screen} document overflow-x`).toBeLessThanOrEqual(audit.docClient + 2);
        expect(audit.bodyScroll, `${width}/${screen} body overflow-x`).toBeLessThanOrEqual(audit.bodyClient + 2);
        expect(audit.activeScroll, `${width}/${screen} active screen overflow-x`).toBeLessThanOrEqual(audit.activeClient + 2);
        expect(audit.spills, `${width}/${screen} visible element spills`).toEqual([]);
        expect(audit.textOverflow, `${width}/${screen} text overflow`).toEqual([]);
        expect(audit.controlOverflow, `${width}/${screen} control overflow`).toEqual([]);
        expect(audit.nakedTables, `${width}/${screen} mobile/tablet naked tables`).toEqual([]);
        expect(audit.modalOverflow, `${width}/${screen} modal overflow`).toEqual([]);
        expect(audit.compactFilters.every((item) => !(item.iconVisible && item.extraVisible)), `${width}/${screen} compact filter state`).toBe(true);
        expect(audit.iconFailures, `${width}/${screen} icon failures`).toEqual([]);
      }
    }
  });
});


