const { test, expect } = require('@playwright/test');

async function openLogin(page) {
  await page.route('**/api/public/login-config', route => route.fulfill({
    contentType: 'application/json',
    body: JSON.stringify({
      ok: true,
      success: true,
      data: {
        settings: {
          systemName: 'He thong quan ly',
          hamletName: 'Thon 09',
          communeName: 'Xa Hong Phong',
          version: 'v2.1'
        },
        metrics: {}
      }
    })
  }));
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#loginPassword')).toBeVisible();
}

test('login password input keeps typed value in password and text modes without DOM rewrite', async ({ page }) => {
  await openLogin(page);
  const password = page.locator('#loginPassword');
  const toggle = page.locator('[data-password-toggle]');
  const secret = 'Admin@2019@@@@DesktopPWA';

  await expect(password).toHaveAttribute('type', 'password');
  await expect(password).toHaveAttribute('autocomplete', 'current-password');
  await expect(password).toHaveAttribute('autocorrect', 'off');
  await expect(password).toHaveAttribute('autocapitalize', 'off');
  await expect(password).toHaveAttribute('spellcheck', 'false');

  await page.evaluate(() => {
    window.__loginPasswordInputRef = document.getElementById('loginPassword');
    window.__loginPasswordEvents = [];
    const input = document.getElementById('loginPassword');
    for (const type of ['keydown', 'keypress', 'keyup', 'beforeinput', 'input', 'change', 'focus', 'blur']) {
      input.addEventListener(type, event => {
        window.__loginPasswordEvents.push({
          type,
          defaultPrevented: event.defaultPrevented,
          value: input.value,
          selectionStart: input.selectionStart
        });
      }, true);
    }
  });

  await password.focus();
  await password.fill('');
  await password.type(secret);
  await expect(password).toHaveValue(secret);
  await expect(password).toBeFocused();
  await expect(password).toHaveJSProperty('selectionStart', secret.length);

  const preventedEvents = await page.evaluate(() => window.__loginPasswordEvents.filter(event => (
    ['keydown', 'keypress', 'keyup', 'beforeinput', 'input'].includes(event.type) && event.defaultPrevented
  )));
  expect(preventedEvents).toEqual([]);

  await toggle.click();
  await expect(password).toHaveAttribute('type', 'text');
  await expect(password).toHaveValue(secret);
  await expect(toggle.locator('i')).toHaveClass(/fa-eye-slash/);
  await expect.poll(() => page.evaluate(() => window.__loginPasswordInputRef === document.getElementById('loginPassword'))).toBe(true);

  await password.fill('');
  await password.type(secret);
  await expect(password).toHaveValue(secret);
});

test('login password field is not inside compositor effects that affect desktop secure input rendering', async ({ page }) => {
  await openLogin(page);
  const styles = await page.evaluate(() => {
    const panel = document.querySelector('.login-panel');
    const wrap = document.querySelector('.login-input-wrap');
    const before = getComputedStyle(document.querySelector('.login-view'), '::before');
    const panelStyle = getComputedStyle(panel);
    const wrapStyle = getComputedStyle(wrap);
    return {
      beforeFilter: before.filter,
      beforeTransform: before.transform,
      panelBackdropFilter: panelStyle.backdropFilter || panelStyle.webkitBackdropFilter || 'none',
      panelAnimationName: panelStyle.animationName,
      wrapTransition: wrapStyle.transitionProperty
    };
  });

  expect(styles.beforeFilter).toBe('none');
  expect(styles.beforeTransform).toBe('none');
  expect(styles.panelBackdropFilter).toBe('none');
  expect(styles.panelAnimationName).toBe('none');
  expect(styles.wrapTransition).not.toContain('transform');
});
