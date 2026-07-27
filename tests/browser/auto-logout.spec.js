const { test, expect } = require('@playwright/test');

const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };

function ok(data) {
  return { ok: true, success: true, data };
}

function unauthorized() {
  return { ok: false, success: false, message: 'Vui lòng đăng nhập', errors: [], error: { message: 'Vui lòng đăng nhập' } };
}

async function installApiMocks(context, state) {
  await context.route('**/api/**', async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    const auth = request.headers().authorization || '';
    const token = auth.replace(/^Bearer\s+/i, '');
    const fulfillJson = (status, body) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });

    if (url.pathname === '/api/public/login-config') {
      return fulfillJson(200, ok({ settings: { systemName: 'Thong 09' }, metrics: {} }));
    }

    if (url.pathname === '/api/auth/login') {
      state.loginRequests += 1;
      state.activeToken = `test-token-${state.loginRequests}`;
      state.revokedTokens.delete(state.activeToken);
      return fulfillJson(200, ok({ token: state.activeToken, csrfToken: `csrf-${state.loginRequests}`, expiresIn: 4, user }));
    }

    if (url.pathname === '/api/auth/logout') {
      state.logoutRequests += 1;
      if (token) state.revokedTokens.add(token);
      return fulfillJson(200, ok({ loggedOutAt: new Date().toISOString() }));
    }

    if (!token || state.revokedTokens.has(token)) {
      return fulfillJson(401, unauthorized());
    }

    if (url.pathname === '/api/auth/keepalive') {
      state.keepAliveRequests += 1;
      return fulfillJson(200, ok({ activeUntil: new Date(Date.now() + 4000).toISOString(), user }));
    }

    if (url.pathname === '/api/auth/me' || url.pathname === '/api/me') {
      return fulfillJson(200, ok(user));
    }

    if (url.pathname.includes('/dashboard')) {
      return fulfillJson(200, ok({ metrics: {}, charts: {}, generatedAt: new Date().toISOString() }));
    }

    return fulfillJson(200, ok({ items: [], total: 0, page: 1, pageSize: 20 }));
  });
}

async function login(page) {
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#loginView')).toBeVisible();
  await page.locator('#loginEmail').fill('admin@example.test');
  await page.locator('#loginPassword').fill('password123');
  await page.locator('#loginForm button[type="submit"]').click();
  await expect(page.locator('#appView')).not.toHaveClass(/d-none/);
  await expect(page.locator('#currentUser')).toContainText(/Admin Test|admin@example.test/);
}

async function loginByStorage(page, token = 'test-token-shared', csrf = 'csrf-shared') {
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.evaluate(({ token, csrf, user }) => {
    App.token = token;
    App.csrfToken = csrf;
    App.user = user;
    window.App = App;
    localStorage.setItem(tenantStorageKey('token'), token);
    localStorage.setItem(tenantStorageKey('csrf'), csrf);
    localStorage.setItem(tenantStorageKey('user'), JSON.stringify(user));
    if (typeof window.showApp === 'function') window.showApp();
  }, { token, csrf, user });
  await expect(page.locator('#appView')).not.toHaveClass(/d-none/);
}

test.describe('auto logout end-to-end regression', () => {
  test('warning appears on schedule, continue resets once, and logout now clears auth', async ({ page, context }) => {
    const state = { loginRequests: 0, logoutRequests: 0, keepAliveRequests: 0, activeToken: '', revokedTokens: new Set() };
    await installApiMocks(context, state);
    await login(page);

    await page.waitForTimeout(1400);
    await expect(page.locator('#idleTimeoutWarningModal')).toHaveCount(0);

    await expect(page.locator('#idleTimeoutWarningModal')).toBeVisible({ timeout: 1600 });
    await expect.poll(() => page.locator('[data-idle-countdown]').textContent()).toMatch(/^[012]$/);

    await page.locator('[data-idle-continue]').click();
    await expect(page.locator('#idleTimeoutWarningModal')).toBeHidden();
    await page.waitForTimeout(1400);
    await expect(page.locator('#idleTimeoutWarningModal')).toBeHidden();

    await expect(page.locator('#idleTimeoutWarningModal')).toBeVisible({ timeout: 2800 });
    await page.locator('[data-idle-logout]').click();
    await expect(page.locator('#loginView')).toBeVisible();
    await expect.poll(() => state.logoutRequests).toBe(1);

    const clientState = await page.evaluate(() => ({
      token: localStorage.getItem(tenantStorageKey('token')),
      csrf: localStorage.getItem(tenantStorageKey('csrf')),
      user: localStorage.getItem(tenantStorageKey('user')),
      cookie: document.cookie,
    }));
    expect(clientState.token).toBeNull();
    expect(clientState.csrf).toBeNull();
    expect(clientState.user).toBeNull();
    expect(clientState.cookie).not.toContain('test-token');

    const oldTokenStatus = await page.evaluate(async () => {
      const response = await fetch('/api/dashboard/summary', { headers: { Authorization: 'Bearer test-token-1' } });
      return response.status;
    });
    expect(oldTokenStatus).toBe(401);
  });

  test('auto timeout logs out once and survives back, refresh, and new tab', async ({ page, context }) => {
    const state = { loginRequests: 0, logoutRequests: 0, keepAliveRequests: 0, activeToken: '', revokedTokens: new Set() };
    await installApiMocks(context, state);
    await login(page);

    await expect(page.locator('#idleTimeoutWarningModal')).toBeVisible({ timeout: 2800 });
    await expect(page.locator('#loginView')).toBeVisible({ timeout: 2600 });
    await expect.poll(() => state.logoutRequests).toBe(1);
    await page.waitForTimeout(600);
    expect(state.logoutRequests).toBe(1);

    await page.goto('/?screen=households', { waitUntil: 'domcontentloaded' });
    await page.goBack();
    await expect(page.locator('#loginView')).toBeVisible();
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('#loginView')).toBeVisible();

    const second = await context.newPage();
    await installApiMocks(context, state);
    await second.goto('/', { waitUntil: 'domcontentloaded' });
    await expect(second.locator('#loginView')).toBeVisible();
  });

  test('all configured user activity events reset the timer without background request spam', async ({ page, context }) => {
    const state = { loginRequests: 0, logoutRequests: 0, keepAliveRequests: 0, activeToken: '', revokedTokens: new Set() };
    await installApiMocks(context, state);
    await login(page);

    const events = ['mousemove', 'click', 'scroll', 'keydown', 'touchstart', 'touchmove', 'contextmenu', 'pointerdown', 'pointermove'];
    for (const eventName of events) {
      await page.waitForTimeout(900);
      if (eventName === 'scroll') {
        await page.mouse.wheel(0, 120);
      } else if (eventName === 'keydown') {
        await page.keyboard.press('Shift');
      } else if (eventName === 'click') {
        await page.mouse.click(20, 20);
      } else {
        await page.evaluate((name) => {
          window.dispatchEvent(new Event(name, { bubbles: true, cancelable: true }));
        }, eventName);
      }
      await expect(page.locator('#idleTimeoutWarningModal')).toHaveCount(0);
    }

    await page.waitForTimeout(1200);
    await expect(page.locator('#appView')).not.toHaveClass(/d-none/);
    expect(state.logoutRequests).toBe(0);
    expect(state.keepAliveRequests).toBeLessThanOrEqual(events.length + 2);
  });

  test('manual logout in one tab logs out other tabs without event loops', async ({ context }) => {
    const state = { loginRequests: 0, logoutRequests: 0, keepAliveRequests: 0, activeToken: 'test-token-shared', revokedTokens: new Set() };
    await installApiMocks(context, state);
    const first = await context.newPage();
    const second = await context.newPage();
    await loginByStorage(first);
    await loginByStorage(second);

    await first.evaluate(() => window.logout());
    await expect(first.locator('#loginView')).toBeVisible();
    await expect(second.locator('#loginView')).toBeVisible({ timeout: 1500 });
    await pageWait(second, 500);
    expect(state.logoutRequests).toBe(1);

    const secondState = await second.evaluate(() => ({
      token: localStorage.getItem(tenantStorageKey('token')),
      appToken: App.token,
    }));
    expect(secondState.token).toBeNull();
    expect(secondState.appToken).toBe('');
  });

  test('mobile and PWA-sized viewports show usable warning modal', async ({ page, context }) => {
    const state = { loginRequests: 0, logoutRequests: 0, keepAliveRequests: 0, activeToken: '', revokedTokens: new Set() };
    await installApiMocks(context, state);
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);
    await expect(page.locator('#idleTimeoutWarningModal')).toBeVisible({ timeout: 2800 });

    const box = await page.locator('#idleTimeoutWarningModal .modal-dialog').boundingBox();
    expect(box).toBeTruthy();
    expect(box.x).toBeGreaterThanOrEqual(0);
    expect(box.y).toBeGreaterThanOrEqual(0);
    expect(box.width).toBeLessThanOrEqual(390);
    expect(box.height).toBeLessThanOrEqual(844);

    await page.locator('[data-idle-continue]').click();
    await expect(page.locator('#idleTimeoutWarningModal')).toBeHidden();
  });
});

async function pageWait(page, ms) {
  await page.waitForTimeout(ms);
}
