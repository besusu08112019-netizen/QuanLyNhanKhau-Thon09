const { test, expect } = require('@playwright/test');

test('application shell renders externalized scripts without console errors', async ({ page }) => {
  const consoleErrors = [];
  const pageErrors = [];

  page.on('console', (message) => {
    if (message.type() === 'error') {
      const text = message.text();
      if (!text.includes('/favicon.ico')) {
        consoleErrors.push(text);
      }
    }
  });
  page.on('pageerror', (error) => pageErrors.push(error.message));

  await page.goto('/', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('#loginView')).toBeVisible();
  await expect(page.locator('#loginForm')).toBeVisible();
  await expect(page.locator('script[src*="assets/js/view-inline-patches.js"]')).toHaveCount(1);

  const html = await page.content();
  expect(html).not.toContain('thon09-report-inline-stable');
  expect(html).not.toContain('thon09-person-advanced-filter-fix');
  expect(html).not.toContain('thon09-header-duplicate-guard');
  expect(html).not.toContain('thon09-final-navigation-repair');

  expect(pageErrors).toEqual([]);
  expect(consoleErrors).toEqual([]);
});
