const CACHE_NAME = 'thon09-static-v1';
const STATIC_ASSETS = [
  '/',
  '/manifest.webmanifest',
  '/assets/icons/app-icon.svg',
  '/assets/vendor/bootstrap/bootstrap.min.css',
  '/assets/vendor/bootstrap/bootstrap.bundle.min.js',
  '/assets/css/app.min.css',
  '/assets/js/i18n.min.js',
  '/assets/js/app-platform.min.js',
  '/assets/js/app.utf8.min.js',
  '/assets/js/csrf.min.js',
  '/assets/js/session.min.js',
  '/assets/js/admin.utf8.min.js',
  '/assets/js/import.min.js',
  '/assets/js/admin-panel.min.js',
  '/assets/js/admin-panel-bridge.min.js',
  '/assets/js/gis-household-location.min.js',
  '/assets/js/gis-platform.min.js',
  '/assets/js/household-business.min.js',
  '/assets/js/livestock.min.js',
  '/assets/js/agriculture.min.js',
  '/assets/js/houses.min.js',
  '/assets/js/public-assets.min.js',
  '/assets/js/module-dashboards.min.js'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  if (request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/api/')) return;

  event.respondWith(
    caches.match(request).then(cached => {
      const refresh = fetch(request)
        .then(response => {
          if (response && response.ok && response.type === 'basic') {
            const copy = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(request, copy));
          }
          return response;
        })
        .catch(() => cached);
      return cached || refresh;
    })
  );
});
