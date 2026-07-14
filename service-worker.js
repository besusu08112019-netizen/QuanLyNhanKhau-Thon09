const PWA_VERSION = 'thon09-pwa-v20260714-1';
const STATIC_CACHE = `${PWA_VERSION}-static`;
const RUNTIME_CACHE = `${PWA_VERSION}-runtime`;
const OFFLINE_URL = '/offline.html';

const STATIC_ASSETS = [
  '/',
  OFFLINE_URL,
  '/manifest.webmanifest',
  '/favicon.ico',
  '/assets/icons/app-icon.svg',
  '/assets/icons/thon09-logo.png',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png',
  '/assets/icons/maskable-192.png',
  '/assets/icons/maskable-512.png',
  '/assets/icons/apple-touch-icon.png',
  '/assets/vendor/bootstrap/bootstrap.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
  '/assets/vendor/bootstrap/bootstrap.bundle.min.js',
  '/assets/vendor/leaflet/leaflet.css',
  '/assets/vendor/leaflet/leaflet.js',
  '/assets/vendor/leaflet/images/layers.png',
  '/assets/vendor/leaflet/images/layers-2x.png',
  '/assets/vendor/leaflet/images/marker-icon.png',
  '/assets/vendor/leaflet/images/marker-icon-2x.png',
  '/assets/vendor/leaflet/images/marker-shadow.png',
  '/assets/vendor/leaflet-draw/leaflet.draw.css',
  '/assets/vendor/leaflet-draw/leaflet.draw.js',
  '/assets/vendor/leaflet-draw/images/spritesheet.png',
  '/assets/vendor/leaflet-draw/images/spritesheet-2x.png',
  '/assets/vendor/leaflet.markercluster/leaflet.markercluster.js',
  '/assets/vendor/leaflet.markercluster/MarkerCluster.css',
  '/assets/vendor/leaflet.markercluster/MarkerCluster.Default.css',
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
  '/assets/js/sprint8.min.js',
  '/assets/js/sprint9.min.js',
  '/assets/js/sprint10.min.js',
  '/assets/js/view-inline-patches.min.js',
  '/assets/js/operation-center.min.js',
  '/assets/js/system-admin.min.js',
  '/assets/js/report.min.js',
  '/assets/js/gis-household-location.min.js',
  '/assets/js/gis-platform.min.js',
  '/assets/js/household-photo-capture.min.js',
  '/assets/js/household-photo-camera-fix.min.js',
  '/assets/js/household-photo-gps.min.js',
  '/assets/js/digital-profile.min.js',
  '/assets/js/household-business.min.js',
  '/assets/js/livestock.min.js',
  '/assets/js/agriculture.min.js',
  '/assets/js/houses.min.js',
  '/assets/js/public-assets.min.js',
  '/assets/js/module-dashboards.min.js',
  '/assets/js/pwa.min.js'
];

const isApiRequest = url => url.origin === self.location.origin && url.pathname.startsWith('/api/');
const isStaticRequest = url => /\.(?:css|js|mjs|json|webmanifest|png|jpg|jpeg|webp|svg|ico|woff2?|ttf|otf)$/i.test(url.pathname);
const isHtmlNavigation = request => request.mode === 'navigate' || (request.headers.get('accept') || '').includes('text/html');

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => cacheAssets(cache, STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter(key => ![STATIC_CACHE, RUNTIME_CACHE].includes(key)).map(key => caches.delete(key)));
    await self.clients.claim();
    await broadcast({ type: 'PWA_READY', version: PWA_VERSION });
  })());
});

self.addEventListener('message', event => {
  const type = event.data && event.data.type;
  if (type === 'SKIP_WAITING') self.skipWaiting();
  if (type === 'CLEAR_PWA_DATA') {
    event.waitUntil((async () => {
      const keys = await caches.keys();
      await Promise.all(keys.map(key => caches.delete(key)));
      await broadcast({ type: 'PWA_CACHE_CLEARED' });
    })());
  }
});

self.addEventListener('sync', event => {
  if (event.tag === 'thon09-background-sync') {
    event.waitUntil(broadcast({ type: 'PWA_SYNC_REQUESTED' }));
  }
});

self.addEventListener('push', event => {
  const fallback = { title: 'Quản lý Nhân khẩu Thôn 09', body: 'Bạn có thông báo mới từ hệ thống.', data: { url: '/' } };
  const payload = (() => {
    try { return event.data ? event.data.json() : fallback; } catch (_) { return fallback; }
  })();
  event.waitUntil(self.registration.showNotification(payload.title || fallback.title, {
    body: payload.body || fallback.body,
    icon: '/assets/icons/icon-192.png',
    badge: '/assets/icons/maskable-192.png',
    tag: payload.tag || 'thon09-system',
    data: payload.data || fallback.data
  }));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const url = new URL((event.notification.data && event.notification.data.url) || '/', self.location.origin).href;
  event.waitUntil((async () => {
    const clientsList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of clientsList) {
      if ('focus' in client) {
        client.navigate(url);
        return client.focus();
      }
    }
    return self.clients.openWindow(url);
  })());
});

self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  if (request.method !== 'GET') return;
  if (isApiRequest(url)) return;

  if (isHtmlNavigation(request)) {
    event.respondWith(networkFirstHtml(request));
    return;
  }

  if (isStaticRequest(url)) {
    event.respondWith(staleWhileRevalidate(request));
    return;
  }

  event.respondWith(cacheFirst(request));
});

async function networkFirstHtml(request) {
  const cache = await caches.open(RUNTIME_CACHE);
  try {
    const response = await fetch(request);
    if (response && response.ok) await cache.put(request, response.clone());
    return response;
  } catch (_) {
    return (await cache.match(request)) || (await caches.match('/')) || (await caches.match(OFFLINE_URL));
  }
}

async function cacheAssets(cache, assets) {
  await Promise.allSettled(assets.map(url => {
    const sameOrigin = String(url).startsWith('/');
    const request = sameOrigin ? new Request(url, { cache: 'reload' }) : new Request(url, { mode: 'no-cors', cache: 'reload' });
    return fetch(request).then(response => {
      if (response && (response.ok || response.type === 'opaque')) return cache.put(request, response);
      throw new Error(`Cannot cache ${url}`);
    });
  }));
}

async function staleWhileRevalidate(request) {
  const cache = await caches.open(RUNTIME_CACHE);
  const cached = await caches.match(request);
  const refresh = fetch(request).then(response => {
    if (response && (response.ok || response.type === 'opaque')) cache.put(request, response.clone());
    return response;
  }).catch(() => cached);
  return cached || refresh;
}

async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) return cached;
  const cache = await caches.open(RUNTIME_CACHE);
  try {
    const response = await fetch(request);
    if (response && (response.ok || response.type === 'opaque')) await cache.put(request, response.clone());
    return response;
  } catch (_) {
    return (await caches.match(OFFLINE_URL)) || Response.error();
  }
}

async function broadcast(message) {
  const clientsList = await self.clients.matchAll({ includeUncontrolled: true });
  clientsList.forEach(client => client.postMessage(message));
}
