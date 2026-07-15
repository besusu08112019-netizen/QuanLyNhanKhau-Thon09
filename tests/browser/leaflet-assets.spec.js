const { test, expect } = require('@playwright/test');

const leafletAssets = [
  { path: '/assets/vendor/leaflet/leaflet.css', type: /text\/css/ },
  { path: '/assets/vendor/leaflet/leaflet.js', type: /(?:javascript|ecmascript)/ },
  { path: '/assets/vendor/leaflet/images/marker-icon.png', type: /image\/png/ },
  { path: '/assets/vendor/leaflet/images/marker-icon-2x.png', type: /image\/png/ },
  { path: '/assets/vendor/leaflet/images/marker-shadow.png', type: /image\/png/ },
  { path: '/assets/vendor/leaflet.markercluster/MarkerCluster.css', type: /text\/css/ },
  { path: '/assets/vendor/leaflet.markercluster/MarkerCluster.Default.css', type: /text\/css/ },
  { path: '/assets/vendor/leaflet.markercluster/leaflet.markercluster.js', type: /(?:javascript|ecmascript)/ },
  { path: '/assets/vendor/leaflet-draw/leaflet.draw.css', type: /text\/css/ },
  { path: '/assets/vendor/leaflet-draw/leaflet.draw.js', type: /(?:javascript|ecmascript)/ }
];

test.describe('Leaflet asset loader', () => {
  test('all deployed Leaflet assets are reachable with correct MIME types', async ({ request }) => {
    for (const asset of leafletAssets) {
      const response = await request.get(asset.path);
      expect(response.status(), asset.path).toBe(200);
      expect(response.headers()['content-type'] || '', asset.path).toMatch(asset.type);
    }
  });

  test('GIS loader uses base-aware asset URLs and does not keep old relative paths', async ({ page }) => {
    const requests = [];
    page.on('request', request => {
      const url = request.url();
      if (url.includes('/assets/vendor/leaflet')) requests.push(url);
    });

    await page.goto('/?leaflet-asset-audit=1', { waitUntil: 'domcontentloaded' });
    await page.evaluate(async () => {
      await window.ensureGisAssets();
    });

    expect(await page.evaluate(() => Boolean(window.L && window.L.map))).toBe(true);
    expect(await page.evaluate(() => Boolean(window.L && window.L.markerClusterGroup))).toBe(true);
    expect(requests.some(url => /\/assets\/vendor\/leaflet\/leaflet\.css$/.test(new URL(url).pathname))).toBe(true);
    expect(requests.some(url => /\/assets\/vendor\/leaflet\/leaflet\.js$/.test(new URL(url).pathname))).toBe(true);
    expect(requests.every(url => !/\/\?leaflet-asset-audit=1\/assets\//.test(url))).toBe(true);
  });

  test('service worker precaches Leaflet assets in the current PWA cache', async ({ page }) => {
    await page.goto('/offline.html', { waitUntil: 'domcontentloaded' });
    const result = await page.evaluate(async () => {
      if (!('serviceWorker' in navigator) || !('caches' in window)) return { supported: false };
      const registrations = await navigator.serviceWorker.getRegistrations();
      await Promise.all(registrations.map(registration => registration.unregister()));
      const keys = await caches.keys();
      await Promise.all(keys.map(key => caches.delete(key)));

      const registration = await navigator.serviceWorker.register('/service-worker.js', { scope: '/', updateViaCache: 'none' });
      const worker = registration.installing || registration.waiting || registration.active;
      if (worker && worker.state !== 'activated') {
        await new Promise(resolve => {
          worker.addEventListener('statechange', () => {
            if (worker.state === 'activated') resolve();
          });
        });
      }

      const cacheNames = await caches.keys();
      const staticCache = cacheNames.find(name => name === 'thon09-pwa-v20260715-01-static');
      const cache = staticCache ? await caches.open(staticCache) : null;
      const cached = {};
      for (const asset of [
        '/assets/vendor/leaflet/leaflet.css',
        '/assets/vendor/leaflet/leaflet.js',
        '/assets/vendor/leaflet/images/marker-icon.png',
        '/assets/vendor/leaflet/images/marker-icon-2x.png',
        '/assets/vendor/leaflet/images/marker-shadow.png',
        '/assets/vendor/leaflet.markercluster/leaflet.markercluster.js'
      ]) {
        cached[asset] = cache ? Boolean(await cache.match(asset)) : false;
      }
      await registration.unregister();
      return { supported: true, cacheNames, staticCache, cached };
    });

    expect(result.supported).toBe(true);
    expect(result.staticCache).toBe('thon09-pwa-v20260715-01-static');
    expect(result.cached).toEqual({
      '/assets/vendor/leaflet/leaflet.css': true,
      '/assets/vendor/leaflet/leaflet.js': true,
      '/assets/vendor/leaflet/images/marker-icon.png': true,
      '/assets/vendor/leaflet/images/marker-icon-2x.png': true,
      '/assets/vendor/leaflet/images/marker-shadow.png': true,
      '/assets/vendor/leaflet.markercluster/leaflet.markercluster.js': true
    });
  });
});
