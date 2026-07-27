const { defineConfig, devices } = require('@playwright/test');

const host = process.env.PW_PHP_HOST || '127.0.0.1';
const port = process.env.PW_PHP_PORT || '8080';

module.exports = defineConfig({
  testDir: './tests/browser',
  timeout: 30000,
  fullyParallel: false,
  retries: process.env.CI ? 1 : 0,
  use: {
    baseURL: `http://${host}:${port}`,
    trace: 'retain-on-failure'
  },
  globalSetup: './tests/browser/global-setup.js',
  globalTeardown: './tests/browser/global-teardown.js',
  projects: [
    {
      name: 'desktop-chromium',
      use: { ...devices['Desktop Chrome'], browserName: 'chromium', viewport: { width: 1366, height: 768 } }
    },
    {
      name: 'mobile-390',
      use: { ...devices['Pixel 5'], browserName: 'chromium', viewport: { width: 390, height: 844 } }
    },
    {
      name: 'tablet-768',
      use: { ...devices['iPad (gen 7)'], browserName: 'chromium', viewport: { width: 768, height: 1024 } }
    }
  ]
});
