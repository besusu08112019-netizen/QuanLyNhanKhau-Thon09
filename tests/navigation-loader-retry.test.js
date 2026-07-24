const fs = require('fs');
const assert = require('assert');
const { chromium } = require('@playwright/test');

(async () => {
  const source = fs.readFileSync('assets/js/view-inline-patches.js', 'utf8');
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  await page.setContent(`
    <!doctype html>
    <div id="dashboardScreen" class="screen"></div>
    <div id="documentsScreen" class="screen">
      <div>Dang tai module van ban...</div>
    </div>
    <div id="screenTitle"></div>
    <div id="breadcrumbTrail"></div>
    <button class="nav-link" data-screen="documents"></button>
  `);

  await page.evaluate(() => {
    window.Thon09Platform = {
      modules: {
        list: () => [{
          moduleKey: 'documents',
          screenId: 'documents',
          path: '/documents',
          label: 'Van ban',
          loaderName: 'loadDocuments'
        }]
      },
      navigation: { current: () => ({ screenId: 'documents' }) },
      actions: { register() { return this; } }
    };
    window.App = { screen: 'documents', user: { role: 'ADMIN' } };
  });

  await page.evaluate(source);
  await page.waitForTimeout(250);
  await page.evaluate(() => {
    window.loadDocuments = () => {
      document.getElementById('documentsScreen').innerHTML = '<div id="documentsLoaded">loaded</div>';
    };
  });

  await page.waitForSelector('#documentsLoaded', { timeout: 5000 });
  const log = await page.evaluate(() => window.__thon09NavigationLog || []);
  assert.ok(log.some(entry => entry.step === 'renderRetryWaiting' || entry.step === 'renderRetryReady'));

  await browser.close();
  console.log('navigation loader retry test passed');
})().catch(error => {
  console.error(error);
  process.exit(1);
});
