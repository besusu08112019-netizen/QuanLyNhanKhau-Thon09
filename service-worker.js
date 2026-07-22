const PWA_VERSION = 'thon09-pwa-v20260718-mobile-ui-10';
const STATIC_CACHE = `${PWA_VERSION}-static`;
const RUNTIME_CACHE = `${PWA_VERSION}-runtime`;
const APP_BASE_PATH = new URL('./', self.location.href).pathname;
const withBase = path => {
  const url = new URL(String(path || '').replace(/^\/+/, ''), self.location.origin + APP_BASE_PATH);
  return url.pathname + url.search;
};
const OFFLINE_URL = withBase('offline.html');
const PRECACHE_TIMEOUT_MS = 8000;

const STATIC_ASSETS = [
  APP_BASE_PATH,
  OFFLINE_URL,
  withBase('manifest.json'),
  withBase('manifest.webmanifest'),
  withBase('favicon.ico?v=20260715-1'),
  withBase('assets/icons/thon09-logo.png?v=20260715-1'),
  withBase('assets/icons/icon-192.png?v=20260715-1'),
  withBase('assets/icons/icon-512.png?v=20260715-1'),
  withBase('assets/icons/maskable-192.png?v=20260715-1'),
  withBase('assets/icons/maskable-512.png?v=20260715-1'),
  withBase('assets/icons/apple-touch-icon.png?v=20260715-1'),
  withBase('assets/icons/splash-512.png?v=20260715-1'),
  withBase('assets/vendor/bootstrap/bootstrap.min.css'),
  withBase('assets/vendor/bootstrap/bootstrap.bundle.min.js'),
  withBase('assets/vendor/leaflet/leaflet.css'),
  withBase('assets/vendor/leaflet/leaflet.js'),
  withBase('assets/vendor/leaflet/images/layers.png'),
  withBase('assets/vendor/leaflet/images/layers-2x.png'),
  withBase('assets/vendor/leaflet/images/marker-icon.png'),
  withBase('assets/vendor/leaflet/images/marker-icon-2x.png'),
  withBase('assets/vendor/leaflet/images/marker-shadow.png'),
  withBase('assets/vendor/leaflet-draw/leaflet.draw.css'),
  withBase('assets/vendor/leaflet-draw/leaflet.draw.js'),
  withBase('assets/vendor/leaflet-draw/images/spritesheet.png'),
  withBase('assets/vendor/leaflet-draw/images/spritesheet-2x.png'),
  withBase('assets/vendor/leaflet-draw/images/spritesheet.svg'),
  withBase('assets/vendor/leaflet.markercluster/MarkerCluster.css'),
  withBase('assets/vendor/leaflet.markercluster/MarkerCluster.Default.css'),
  withBase('assets/vendor/leaflet.markercluster/leaflet.markercluster.js'),
  withBase('assets/css/app.min.css'),
  withBase('assets/css/mobile-design-system-v2.min.css'),
  withBase('assets/js/i18n.min.js'),
  withBase('assets/js/print-framework.min.js'),
  withBase('assets/js/app-platform.min.js'),
  withBase('assets/js/mobile-component-library.min.js'),
  withBase('assets/js/app.utf8.min.js'),
  withBase('assets/js/csrf.min.js'),
  withBase('assets/js/session.min.js'),
  withBase('assets/js/admin.utf8.min.js'),
  withBase('assets/js/admin-panel.min.js'),
  withBase('assets/js/view-inline-patches.min.js'),
  withBase('assets/js/module-dashboards.min.js'),
  withBase('assets/js/pwa.min.js')
];

const isApiRequest = url => url.origin === self.location.origin && url.pathname.startsWith('/api/');
const isManifestRequest = url => url.origin === self.location.origin && (url.pathname === withBase('manifest.json') || url.pathname === withBase('manifest.webmanifest'));
const isStaticRequest = url => /\.(?:css|js|mjs|json|webmanifest|png|jpg|jpeg|webp|svg|ico|woff2?|ttf|otf)$/i.test(url.pathname);
const isHtmlNavigation = request => request.mode === 'navigate' || (request.headers.get('accept') || '').includes('text/html');

self.addEventListener('install', event => {
  event.waitUntil((async () => {
    const cache = await caches.open(STATIC_CACHE);
    const summary = await cacheAssets(cache, STATIC_ASSETS);
    if (summary.failed.length) console.warn('[Thon09 PWA] Precache skipped assets', summary.failed);
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    const staleKeys = keys.filter(key => key !== STATIC_CACHE && key !== RUNTIME_CACHE);
    await Promise.allSettled(staleKeys.map(key => caches.delete(key)));
    await self.clients.claim();
    await broadcast({ type: 'PWA_UPDATED', version: PWA_VERSION, deletedCaches: staleKeys });
    await broadcast({ type: 'PWA_READY', version: PWA_VERSION });
  })());
});

self.addEventListener('message', event => {
  const type = event.data && event.data.type;
  if (type === 'SKIP_WAITING') {
    event.waitUntil(self.skipWaiting());
    return;
  }
  if (type === 'CLEAR_PWA_DATA') {
    event.waitUntil((async () => {
      const keys = await caches.keys();
      await Promise.allSettled(keys.map(key => caches.delete(key)));
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
    icon: '/assets/icons/icon-192.png?v=20260714-7',
    badge: '/assets/icons/maskable-192.png?v=20260714-7',
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
  if (isExternalTileRequest(url)) return;

  if (isHtmlNavigation(request)) {
    event.respondWith(networkFirstHtml(request));
    return;
  }

  if (isManifestRequest(url)) {
    event.respondWith(networkFirstFresh(request));
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
    const response = await fetch(new Request(request, { cache: 'no-store' }));
    if (response && response.ok) await cache.put(request, response.clone());
    return response;
  } catch (_) {
    return (await cache.match(request)) || (await caches.match(APP_BASE_PATH)) || (await caches.match(OFFLINE_URL)) || Response.error();
  }
}

async function networkFirstFresh(request) {
  const cache = await caches.open(RUNTIME_CACHE);
  try {
    const response = await fetch(new Request(request, { cache: 'reload' }));
    if (response && response.ok && isCacheableStaticResponse(request, response)) await cache.put(request, response.clone());
    return response;
  } catch (_) {
    return (await cache.match(request)) || (await caches.match(request)) || Response.error();
  }
}

async function cacheAssets(cache, assets) {
  const results = await Promise.allSettled(assets.map(async url => {
    const sameOrigin = String(url).startsWith('/');
    const request = sameOrigin ? new Request(url, { cache: 'reload' }) : new Request(url, { mode: 'no-cors', cache: 'reload' });
    try {
      const response = await fetchWithTimeout(request, PRECACHE_TIMEOUT_MS);
      const status = response ? response.status : 0;
      const contentType = response ? response.headers.get('content-type') || '' : '';
      if (response && (response.ok || response.type === 'opaque') && isCacheableStaticResponse(request, response)) {
        await cache.put(request, response);
        return { url, ok: true, status, contentType };
      }
      return { url, ok: false, status, contentType, reason: response && response.ok ? 'content-type' : `http-${status}` };
    } catch (error) {
      return { url, ok: false, reason: error && error.name === 'AbortError' ? 'timeout' : 'exception', error: error && error.message ? error.message : String(error) };
    }
  }));
  return results.reduce((summary, item) => {
    const value = item.value || { ok: false, reason: 'settled-rejection', error: item.reason };
    if (value.ok) summary.ok += 1;
    else summary.failed.push(value);
    return summary;
  }, { version: PWA_VERSION, total: assets.length, ok: 0, failed: [] });
}

async function staleWhileRevalidate(request) {
  const cache = await caches.open(RUNTIME_CACHE);
  const cached = await matchStatic(request);
  const refresh = fetch(new Request(request, { cache: 'no-cache' })).then(response => {
    if (response && (response.ok || response.type === 'opaque') && isCacheableStaticResponse(request, response)) {
      cache.put(request, response.clone()).catch(() => {});
    }
    return response;
  }).catch(() => cached);
  return cached || refresh;
}

async function cacheFirst(request) {
  const cached = await matchStatic(request);
  if (cached) return cached;
  const cache = await caches.open(RUNTIME_CACHE);
  try {
    const response = await fetch(request);
    if (response && (response.ok || response.type === 'opaque') && isCacheableStaticResponse(request, response)) await cache.put(request, response.clone());
    return response;
  } catch (_) {
    return (await caches.match(OFFLINE_URL)) || Response.error();
  }
}

async function matchStatic(request) {
  const cached = await caches.match(request);
  if (cached && isCacheableStaticResponse(request, cached)) return cached;
  const url = new URL(request.url);
  if (url.origin === self.location.origin && url.search) {
    if (/\.(?:css|js|mjs)$/i.test(url.pathname)) return null;
    const fallback = await caches.match(new Request(url.pathname, { method: 'GET' }));
    if (fallback && isCacheableStaticResponse(request, fallback)) return fallback;
  }
  return null;
}

function fetchWithTimeout(request, timeoutMs) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  return fetch(request, { signal: controller.signal }).finally(() => clearTimeout(timer));
}

function isCacheableStaticResponse(request, response) {
  if (!response || response.type === 'opaque') return true;
  const url = new URL(request.url);
  const type = (response.headers.get('content-type') || '').toLowerCase();
  if (url.pathname.endsWith('.css')) return type.includes('text/css');
  if (url.pathname.endsWith('.js') || url.pathname.endsWith('.mjs')) return type.includes('javascript') || type.includes('ecmascript');
  if (url.pathname.endsWith('.webmanifest') || url.pathname.endsWith('/manifest.json')) return type.includes('manifest+json') || type.includes('application/json');
  if (/\.(?:png|jpg|jpeg|webp|svg|ico)$/i.test(url.pathname)) return type.startsWith('image/');
  if (/\.(?:woff2?|ttf|otf)$/i.test(url.pathname)) return type.includes('font') || type.includes('octet-stream');
  return true;
}

function isExternalTileRequest(url) {
  return url.hostname.endsWith('arcgisonline.com')
    || url.hostname.endsWith('tile.openstreetmap.org')
    || url.hostname.endsWith('openstreetmap.fr')
    || url.hostname.endsWith('basemaps.cartocdn.com');
}

async function broadcast(message) {
  const clientsList = await self.clients.matchAll({ includeUncontrolled: true });
  clientsList.forEach(client => client.postMessage(message));
}
