const { test, expect } = require('@playwright/test');

const loginConfig = {
  ok: true,
  data: {
    settings: { systemName: 'Test', hamletName: 'Hamlet 09', communeName: 'Hong Phong', version: 'v2' },
    metrics: {}
  }
};

const area = {
  id: 11,
  name: 'Khu A',
  area_code: 'A',
  color: '#2563eb',
  note: 'old',
  geometry: {
    type: 'Polygon',
    coordinates: [[
      [105.97, 20.25],
      [105.97, 20.26],
      [105.98, 20.26],
      [105.97, 20.25]
    ]]
  },
  stats: { households: 1, citizens: 3, area_m2: 1200 }
};

const marker = {
  id: 7,
  household_code: 'HK001',
  head_citizen_name: 'Nguyen Van A',
  latitude: 20.255,
  longitude: 105.976,
  located: 1,
  location_accuracy: 8,
  location_source: 'GPS'
};

const detail = {
  household: {
    id: 7,
    household_code: 'HK001',
    head_citizen_name: 'Nguyen Van A',
    address: 'Thon 09',
    phone: '0900000000',
    total_members: 3,
    at_home_count: 2,
    away_count: 1,
    latitude: 20.255,
    longitude: 105.976,
    location_accuracy: 8
  },
  members: [{ full_name: 'Nguyen Van A', relationship: 'Chu ho' }],
  business: [{ business_name: 'Cua hang vat tu', economic_type: 'Thuong mai', sector: 'Ban le' }],
  livestock: [{ animal_type: 'Ga', breed: 'Ri', quantity: 30 }],
  vehicles: [],
  contributions: [],
  timeline: [{ message: 'Cap nhat GPS', created_at: '2026-07-09' }]
};

function leafletStub() {
  return `
    (function(){
      function evented(target){
        target._events = {};
        target.on = function(names, cb){ String(names).split(/\\s+/).forEach(name => { (this._events[name] ||= []).push(cb); }); return this; };
        target.once = target.on;
        target.fire = function(name, payload){ (this._events[name] || []).forEach(cb => cb(payload || {})); return this; };
        return target;
      }
      function layerGroup(opts){
        const group = evented({ layers: new Set(), options: opts || {}, refreshCount: 0 });
        group.addTo = function(map){ this.map = map; map && map.addLayer && map.addLayer(this); return this; };
        group.addLayer = function(layer){ this.layers.add(layer); layer._parent = this; return this; };
        group.addLayers = function(layers){ (layers || []).forEach(layer => this.addLayer(layer)); return this; };
        group.removeLayer = function(layer){ this.layers.delete(layer); return this; };
        group.clearLayers = function(){ this.layers.clear(); return this; };
        group.hasLayer = function(layer){ return this.layers.has(layer); };
        group.getLayers = function(){ return Array.from(this.layers); };
        group.refreshClusters = function(){ this.refreshCount += 1; return this; };
        return group;
      }
      function makePopup(opts){
        const popup = evented({ options: opts || {}, content: '', element: null });
        popup.setContent = function(html){ this.content = html; return this; };
        popup.getElement = function(){ return this.element || document.querySelector('[data-test-popup]'); };
        return popup;
      }
      function makeMarker(latlng, opts){
        const marker = evented({ latlng: Array.isArray(latlng) ? { lat: latlng[0], lng: latlng[1] } : latlng, opts: opts || {}, popup: '', opened: false });
        marker.bindPopup = function(html){ this.popup = html; return this; };
        marker.getPopup = function(){ return this.popup; };
        marker.setPopupContent = function(html){ if (this.popup && typeof this.popup.setContent === 'function') this.popup.setContent(html); else this.popup = html; if (this.opened) this.openPopup(); return this; };
        marker.openPopup = function(){ this.opened = true; let el = document.querySelector('[data-test-popup]'); if (!el) { el = document.createElement('div'); el.setAttribute('data-test-popup', '1'); document.body.appendChild(el); } const html = this.popup && typeof this.popup.setContent === 'function' ? this.popup.content : this.popup; el.innerHTML = html || ''; if (this.popup && typeof this.popup.getElement === 'function') this.popup.element = el; this.fire('popupopen', { popup: this.popup }); return this; };
        marker.closePopup = function(){ this.opened = false; this.fire('popupclose', { popup: this.popup }); return this; };
        marker.isPopupOpen = function(){ return this.opened; };
        marker.getLatLng = function(){ return this.latlng; };
        marker.setLatLng = function(v){ this.latlng = Array.isArray(v) ? { lat: v[0], lng: v[1] } : v; return this; };
        marker.addTo = function(group){ group && group.addLayer && group.addLayer(this); return this; };
        marker.remove = function(){ this._parent && this._parent.removeLayer(this); return this; };
        marker.getElement = function(){ if (!this.el) this.el = document.createElement('div'); return this.el; };
        marker.setIcon = function(icon){ this.icon = icon; return this; };
        return marker;
      }
      function makeMap(id, opts){
        const el = typeof id === 'string' ? document.getElementById(id) : id;
        const map = evented({ el, options: opts || {}, zoom: 14, center: { lat: 20.2506, lng: 105.9748 }, layers: new Set() });
        map.setView = function(center, zoom){ if (center) this.center = Array.isArray(center) ? { lat: center[0], lng: center[1] } : center; if (zoom) this.zoom = zoom; return this; };
        map.getZoom = function(){ return this.zoom; };
        map.getMaxZoom = function(){ return this.options && this.options.maxZoom ? this.options.maxZoom : 20; };
        map.getBounds = function(){ const b = { getSouth: () => 20.20, getWest: () => 105.90, getNorth: () => 20.30, getEast: () => 106.00 }; b.pad = function(){ return b; }; return b; };
        map.addLayer = function(layer){ this.layers.add(layer); return this; };
        map.removeLayer = function(layer){ this.layers.delete(layer); return this; };
        map.hasLayer = function(layer){ return this.layers.has(layer); };
        map.fitBounds = function(){ return this; };
        map.invalidateSize = function(){ return this; };
        map.addControl = function(){ return this; };
        map.panTo = function(center){ this.center = Array.isArray(center) ? { lat: center[0], lng: center[1] } : center; return this; };
        map.closePopup = function(){ return this; };
        return map;
      }
      function tileLayer(url, opts){ return { url, opts: opts || {}, addTo(map){ this.map = map; map && map.addLayer && map.addLayer(this); return this; } }; }
      function polygon(points, opts){ const p = makeMarker(points && points[0] ? points[0] : [0,0], opts); p.points = points || []; p.style = { ...(opts || {}) }; p.bindTooltip = function(){ return this; }; p.getLatLngs = function(){ return [this.points.map(pt => Array.isArray(pt) ? { lat: pt[0], lng: pt[1] } : pt)]; }; p.setStyle = function(style){ this.style = { ...this.style, ...(style || {}) }; return this; }; p.bringToFront = function(){ this.front = true; return this; }; p.toGeoJSON = function(){ return { type: 'Feature', geometry: { type: 'Polygon', coordinates: [this.points.map(pt => Array.isArray(pt) ? [pt[1], pt[0]] : [pt.lng, pt.lat])] } }; }; p.editing = { enabled: false, enable(){ this.enabled = true; } }; return p; }
      function circle(latlng, opts){ const c = makeMarker(latlng, opts); c.setRadius = function(radius){ this.radius = radius; return this; }; return c; }
      function divIcon(opts){ return opts || {}; }
      function DrawControl(){ }
      function DrawPolygon(map, opts){ this.map = map; this.opts = opts; }
      DrawPolygon.prototype.enable = function(){ return this; };
      window.L = { map: makeMap, tileLayer, featureGroup: layerGroup, layerGroup, markerClusterGroup: layerGroup, popup: makePopup, marker: makeMarker, circle, polygon, divIcon, Control: { Draw: DrawControl }, Draw: { Polygon: DrawPolygon, Event: { CREATED: 'draw:created', EDITED: 'draw:edited' } }, DomEvent: { disableClickPropagation(){}, disableScrollPropagation(){}, stopPropagation(){} } };
    })();
  `;
}

async function boot(page, apiLog) {
  const savedGps = { latitude: detail.household.latitude, longitude: detail.household.longitude, accuracy: detail.household.location_accuracy };
  await page.addInitScript({ content: leafletStub() });
  await page.addInitScript(() => {
    const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };
    localStorage.setItem('thon09_token', 'test-token');
    localStorage.setItem('thon09_csrf', 'test-csrf');
    localStorage.setItem('thon09_user', JSON.stringify(user));
    localStorage.setItem('thon09_screen', 'gis');
  });
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'geolocation', {
      configurable: true,
      value: {
        watchPosition(success, error, options) {
          window.__gpsWatchOptions = options;
          const samples = [32.6, 18.2, 9.4, 4.8];
          samples.forEach((accuracy, index) => setTimeout(() => success({ coords: { latitude: 20.2551 + index * 0.000001, longitude: 105.9761 + index * 0.000001, accuracy } }), 20 + index * 25));
          return 101;
        },
        clearWatch(id) { window.__gpsCleared = id; }
      }
    });
  });
  await page.route('https://cdn.jsdelivr.net/npm/leaflet**', route => route.fulfill({ contentType: route.request().url().endsWith('.css') ? 'text/css' : 'application/javascript', body: '' }));
  await page.route('**/api/**', async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    const method = request.method();
    const payload = (data) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) });
    if (url.pathname === '/api/public/login-config') return payload(loginConfig.data);
    if (url.pathname === '/api/auth/me') return payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.pathname === '/api/dashboard/summary') return payload({ metrics: {}, charts: {} });
    if (url.pathname === '/api/gis/areas' && method === 'GET') { apiLog.push({ method, path: url.pathname }); return payload({ areas: [area], summary: { households: 1, located: 1, unlocated: 0 } }); }
    if (url.pathname === '/api/gis/households' && method === 'GET') { apiLog.push({ method, path: url.pathname, query: Object.fromEntries(url.searchParams.entries()) }); return payload({ items: [marker], summary: { households: 1, located: 1, unlocated: 0 } }); }
    if (url.pathname === '/api/gis/households/7/detail' && method === 'GET') { apiLog.push({ method, path: url.pathname }); return payload({ ...detail, household: { ...detail.household, latitude: savedGps.latitude, longitude: savedGps.longitude, location_accuracy: savedGps.accuracy } }); }
    if (url.pathname === '/api/gis/households/7/location' && method === 'PUT') { const body = request.postDataJSON(); savedGps.latitude = body.latitude; savedGps.longitude = body.longitude; savedGps.accuracy = body.accuracy; apiLog.push({ method, path: url.pathname, body }); return payload({ id: 7, latitude: savedGps.latitude, longitude: savedGps.longitude, location_accuracy: savedGps.accuracy }); }
    return payload({});
  });
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => typeof window.loadGisMap === 'function');
  await page.evaluate(() => {
    const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };
    App.token = 'test-token';
    App.csrfToken = 'test-csrf';
    App.user = user;
    window.App = App;
    window.__thon09SessionExpired = false;
    localStorage.setItem('thon09_token', 'test-token');
    localStorage.setItem('thon09_csrf', 'test-csrf');
    localStorage.setItem('thon09_user', JSON.stringify(user));
    window.showApp();
    window.switchScreen('gis');
    return window.loadGisMap();
  });
}

test('leaflet GIS keeps drawn polygon visible after draw created', async ({ page }) => {
  const apiLog = [];
  await boot(page, apiLog);
  await page.evaluate(() => window.startGisDraw(false));
  const result = await page.evaluate(() => {
    const layer = window.L.polygon([[20.251, 105.971], [20.252, 105.972], [20.253, 105.971]]);
    window.App.gis.map.fire(window.L.Draw.Event.CREATED, { layer });
    return {
      isDrawn: window.App.gis.drawnLayer === layer,
      inGroup: window.App.gis.layerGroup.hasLayer(layer),
      saveDisabled: document.querySelector('#gisSaveBtn')?.disabled,
      dirty: window.App.gis.areaDirty,
      style: layer.style,
      editingEnabled: Boolean(layer.editing?.enabled)
    };
  });
  expect(result.isDrawn).toBe(true);
  expect(result.inGroup).toBe(true);
  expect(result.saveDisabled).toBe(false);
  expect(result.dirty).toBe(true);
  expect(result.style).toMatchObject({ color: '#0f8a4b', fillColor: '#0f8a4b', weight: 3 });
  expect(result.style.fillOpacity).toBeCloseTo(0.35);
  expect(result.editingEnabled).toBe(true);
});

test('leaflet GIS renders GeoJSON area polygon and highlights selected area', async ({ page }) => {
  const apiLog = [];
  await boot(page, apiLog);
  const before = await page.evaluate(() => {
    const layer = window.App.gis.areaLayerMap.get('11');
    return { count: window.App.gis.areaLayerMap.size, points: layer?.points?.length || 0, style: layer?.style || null };
  });
  expect(before.count).toBe(1);
  expect(before.points).toBeGreaterThanOrEqual(3);
  expect(before.style.fillOpacity).toBeCloseTo(0.2);

  await page.locator('#gisAreaList .gis-area-item').click();
  const after = await page.evaluate(() => {
    const layer = window.App.gis.areaLayerMap.get('11');
    return { selectedAreaId: window.App.gis.selectedAreaId, front: Boolean(layer?.front), style: layer?.style || null };
  });
  expect(after.selectedAreaId).toBe('11');
  expect(after.front).toBe(true);
  expect(after.style).toMatchObject({ color: '#1976D2', fillColor: '#1976D2', weight: 5, opacity: 1 });
  expect(after.style.fillOpacity).toBeCloseTo(0.45);
});

test('leaflet GIS loads viewport markers and lazy popup detail', async ({ page }) => {
  const apiLog = [];
  await boot(page, apiLog);
  await expect(page.locator('#gisScreen')).toHaveClass(/active/);
  await expect(page.locator('#gisMapStatus')).toContainText('1 marker');
  expect(apiLog.some(item => item.path === '/api/gis/households' && item.query.light === '1' && item.query.south)).toBeTruthy();

  await page.evaluate(() => window.App.gis.markerCache.get('7').marker.fire('click'));
  await expect(page.locator('[data-test-popup]')).toContainText('HK001');
  await expect(page.locator('[data-test-popup]')).toContainText('Cua hang vat tu');
  expect(apiLog.some(item => item.path === '/api/gis/households/7/detail')).toBeTruthy();

  await page.locator('#gisSearch').fill('Nguyen');
  await expect(page.locator('#gisSearchResults')).toContainText('HK001');
});

test('leaflet GIS waits for accurate GPS before saving selected marker', async ({ page }) => {
  const apiLog = [];
  await boot(page, apiLog);
  await page.evaluate(() => window.App.gis.markerCache.get('7').marker.fire('click'));
  await page.locator('#gisCurrentLocationBtn').click();
  await expect(page.locator('[data-gis-gps-accuracy]')).toBeVisible();
  await expect(page.locator('[data-gis-gps-accuracy]')).toContainText(/m$/);
  await expect.poll(() => apiLog.find(item => item.method === 'PUT' && item.path === '/api/gis/households/7/location')?.body.accuracy).toBe(5);
  expect(apiLog.filter(item => item.method === 'PUT' && item.path === '/api/gis/households/7/location').map(item => item.body.accuracy)).toEqual([5]);
  await expect.poll(() => page.evaluate(() => window.__gpsCleared)).toBe(101);
  const options = await page.evaluate(() => window.__gpsWatchOptions);
  expect(options).toMatchObject({ enableHighAccuracy: true, timeout: 60000, maximumAge: 0 });
  await expect(page.locator('[data-test-popup]')).toContainText('HK001');
});

test('leaflet GIS keeps popup open during map move and uses spiderfy cluster refresh', async ({ page }) => {
  const apiLog = [];
  await boot(page, apiLog);
  const clusterState = await page.evaluate(() => ({
    spiderfyOnMaxZoom: window.App.gis.markerGroup.options.spiderfyOnMaxZoom,
    maxRadiusAtMaxZoom: window.App.gis.markerGroup.options.maxClusterRadius(window.App.gis.map.getMaxZoom()),
    layerCount: window.App.gis.markerGroup.getLayers().length,
    refreshCount: window.App.gis.markerGroup.refreshCount
  }));
  expect(clusterState.spiderfyOnMaxZoom).toBe(true);
  expect(clusterState.maxRadiusAtMaxZoom).toBe(1);
  expect(clusterState.layerCount).toBe(1);
  expect(clusterState.refreshCount).toBeGreaterThan(0);

  await page.evaluate(() => window.App.gis.markerCache.get('7').marker.fire('click'));
  await expect(page.locator('[data-test-popup]')).toContainText('HK001');
  await page.waitForTimeout(320);
  const requestCountBeforeMove = apiLog.filter(item => item.path === '/api/gis/households').length;
  await page.evaluate(async () => {
    window.App.gis.map.fire('moveend');
    window.App.gis.map.fire('zoomend');
    await window.App.gis.manager.markers.loadViewport();
  });
  await page.waitForTimeout(260);
  await expect(page.locator('[data-test-popup]')).toContainText('HK001');
  const requestCountAfterMove = apiLog.filter(item => item.path === '/api/gis/households').length;
  expect(requestCountAfterMove).toBe(requestCountBeforeMove);
});
