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
  polygon: [
    { lat: 20.25, lng: 105.97 },
    { lat: 20.26, lng: 105.97 },
    { lat: 20.26, lng: 105.98 }
  ],
  stats: { households: 1, citizens: 3, area_m2: 1200 }
};

const household = {
  id: 7,
  household_code: 'HK001',
  head_citizen_name: 'Nguyen Van A',
  address: 'Thon 09',
  total_members: 3,
  latitude: 20.255,
  longitude: 105.976,
  area_code: 'A'
};

function googleMapsStub() {
  return `
    (function(){
      const listeners = new WeakMap();
      function LatLng(lat, lng){ this._lat = Number(lat.lat ?? lat); this._lng = Number(lat.lng ?? lng); }
      LatLng.prototype.lat = function(){ return this._lat; };
      LatLng.prototype.lng = function(){ return this._lng; };
      function asLatLng(point){ return point && typeof point.lat === 'function' ? point : new LatLng(point); }
      function MVCPath(points){ this.points = (points || []).map(asLatLng); }
      MVCPath.prototype.forEach = function(cb){ this.points.forEach(cb); };
      function eventFor(el, name, cb){
        if (!listeners.has(el)) listeners.set(el, []);
        const item = { name, cb };
        listeners.get(el).push(item);
        return { remove: function(){ const arr = listeners.get(el) || []; const i = arr.indexOf(item); if (i >= 0) arr.splice(i, 1); } };
      }
      const event = {
        addListenerOnce: function(target, name, cb){ setTimeout(cb, 0); return { remove: function(){} }; },
        removeListener: function(listener){ if (listener && listener.remove) listener.remove(); }
      };
      function Map(el, opts){ this.el = el; this.center = opts.center; this.zoom = opts.zoom || 14; this.type = opts.mapTypeId; el.__gmMap = this; }
      Map.prototype.addListener = function(name, cb){
        if (name === 'click') {
          const handler = (ev) => cb({ latLng: new LatLng(20.25 + (ev.offsetY || 0) / 10000, 105.97 + (ev.offsetX || 0) / 10000) });
          this.el.addEventListener('click', handler);
          return { remove: () => this.el.removeEventListener('click', handler) };
        }
        return eventFor(this, name, cb);
      };
      Map.prototype.fitBounds = function(){ this.fit = true; };
      Map.prototype.panTo = function(p){ this.center = p; };
      Map.prototype.setCenter = function(p){ this.center = p; };
      Map.prototype.getCenter = function(){ return new LatLng(this.center || {lat:20.257,lng:105.975}); };
      Map.prototype.setZoom = function(z){ this.zoom = z; };
      Map.prototype.getZoom = function(){ return this.zoom; };
      Map.prototype.getStreetView = function(){ return { setPosition: function(){}, setVisible: function(){} }; };
      function Marker(opts){ this.opts = opts || {}; this.position = asLatLng(opts.position || {lat:0,lng:0}); this.map = opts.map; }
      Marker.prototype.setMap = function(map){ this.map = map; };
      Marker.prototype.setPosition = function(p){ this.position = asLatLng(p); };
      Marker.prototype.getPosition = function(){ return this.position; };
      Marker.prototype.addListener = function(name, cb){ return eventFor(this, name, cb); };
      function Polygon(opts){ this.opts = opts || {}; this.path = new MVCPath(opts.paths || []); this.map = opts.map; }
      Polygon.prototype.setMap = function(map){ this.map = map; };
      Polygon.prototype.addListener = function(name, cb){ return eventFor(this, name, cb); };
      Polygon.prototype.getPath = function(){ return this.path; };
      Polygon.prototype.setPath = function(points){ this.path = new MVCPath(points); };
      Polygon.prototype.setOptions = function(opts){ Object.assign(this.opts, opts); };
      function Circle(opts){ this.opts = opts || {}; }
      Circle.prototype.setMap = function(map){ this.map = map; };
      Circle.prototype.setCenter = function(center){ this.center = center; };
      Circle.prototype.setRadius = function(radius){ this.radius = radius; };
      function InfoWindow(){ this.content = ''; }
      InfoWindow.prototype.setContent = function(html){ this.content = html; };
      InfoWindow.prototype.setPosition = function(p){ this.position = p; };
      InfoWindow.prototype.open = function(){ document.body.insertAdjacentHTML('beforeend', '<div data-test-infowindow>'+this.content+'</div>'); };
      function LatLngBounds(){ this.points = []; }
      LatLngBounds.prototype.extend = function(p){ this.points.push(p); };
      function DirectionsService(){}
      DirectionsService.prototype.route = function(request, cb){ cb({ routes: [{ legs: [{ distance: { text: '1 km' }, duration: { text: '3 phut' } }] }] }, 'OK'); };
      function DirectionsRenderer(opts){ this.opts = opts; }
      DirectionsRenderer.prototype.setDirections = function(result){ this.result = result; };
      function StreetViewService(){}
      StreetViewService.prototype.getPanorama = function(request, cb){ cb({ location: { latLng: request.location } }, 'OK'); };
      window.google = { maps: { Map, Marker, Polygon, Circle, InfoWindow, LatLng, LatLngBounds, DirectionsService, DirectionsRenderer, StreetViewService, MapTypeId: { ROADMAP: 'roadmap' }, MapTypeControlStyle: { HORIZONTAL_BAR: 1 }, SymbolPath: { CIRCLE: 0 }, TravelMode: { DRIVING: 'DRIVING' }, StreetViewSource: { OUTDOOR: 'OUTDOOR' }, StreetViewStatus: { OK: 'OK' }, geometry: { spherical: { computeDistanceBetween: function(){ return 1234; } } }, event } };
    })();
  `;
}

async function boot(page, apiLog) {
  await page.addInitScript({ content: googleMapsStub() });
  await page.route('https://maps.googleapis.com/maps/api/js**', route => route.fulfill({ contentType: 'application/javascript', body: googleMapsStub() }));
  await page.route('**/api/**', async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    const method = request.method();
    const payload = (data) => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) });
    if (url.pathname === '/api/public/login-config') return payload(loginConfig.data);
    if (url.pathname === '/api/auth/me') return payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.pathname === '/api/dashboard/summary') return payload({ metrics: {}, charts: {} });
    if (url.pathname === '/api/gis/areas' && method === 'GET') { apiLog.push({ method, path: url.pathname }); return payload({ areas: [area], summary: { households: 1, located: 1 } }); }
    if (url.pathname === '/api/gis/households' && method === 'GET') { apiLog.push({ method, path: url.pathname }); return payload({ items: [household] }); }
    if (url.pathname === '/api/gis/areas' && method === 'POST') { apiLog.push({ method, path: url.pathname, body: request.postDataJSON() }); return payload({ id: 12 }); }
    if (url.pathname === '/api/gis/areas/11' && method === 'PUT') { apiLog.push({ method, path: url.pathname, body: request.postDataJSON() }); return payload({ id: 11 }); }
    if (url.pathname === '/api/gis/areas/11' && method === 'DELETE') { apiLog.push({ method, path: url.pathname }); return payload({ id: 11, deleted: true }); }
    if (url.pathname === '/api/gis/households/7/location' && method === 'PUT') { apiLog.push({ method, path: url.pathname, body: request.postDataJSON() }); return payload({ id: 7 }); }
    if (url.pathname === '/api/gis/households/7/location' && method === 'DELETE') { apiLog.push({ method, path: url.pathname }); return payload({ id: 7, removed: true }); }
    return payload({});
  });
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => typeof window.loadGisGoogleMap === 'function');
  await page.evaluate(() => {
    const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };
    window.App.token = 'test-token';
    window.App.csrfToken = 'test-csrf';
    window.App.user = user;
    window.THON09_GOOGLE_MAPS_API_KEY = 'test-key';
    localStorage.setItem('thon09_token', 'test-token');
    localStorage.setItem('thon09_csrf', 'test-csrf');
    localStorage.setItem('thon09_user', JSON.stringify(user));
    window.showApp();
    window.switchScreen('gisGoogle');
    return window.loadGisGoogleMap();
  });
  await expect(page.locator('#gisGoogleScreen')).toHaveClass(/active/);
  await expect.poll(async () => page.locator('#gisGoogleSearchResults').textContent(), { message: async () => JSON.stringify(apiLog) + ' ERR=' + await page.evaluate(() => window.__gisGoogleLastError || '') }).toContain('HK001');
}
test('gis-google UI supports marker CRUD through existing GIS APIs', async ({ page }) => {
  const apiLog = [];
  await boot(page, apiLog);
  await page.locator('[data-row-select="7"]').click();
  await page.locator('#gisGoogleAddMarkerBtn').click();
  await page.locator('#gisGoogleMap').click({ position: { x: 180, y: 160 } });
  await expect.poll(() => apiLog.some(item => item.method === 'PUT' && item.path === '/api/gis/households/7/location')).toBeTruthy();

  page.once('dialog', dialog => dialog.accept());
  await page.evaluate(() => window.loadGisGoogleMap());
  await page.locator('[data-row-zoom="7"]').click();
  await page.locator('[data-gis-google-clear-marker="7"]').click();
  await expect.poll(() => apiLog.some(item => item.method === 'DELETE' && item.path === '/api/gis/households/7/location')).toBeTruthy();
});

test('gis-google UI supports polygon create edit delete through existing GIS APIs', async ({ page }) => {
  const apiLog = [];
  await boot(page, apiLog);
  await page.locator('#gisGoogleAreaName').fill('Khu moi');
  await page.locator('#gisGoogleAreaCode').fill('NEW');
  await page.locator('#gisGoogleDrawAreaBtn').click();
  await page.locator('#gisGoogleMap').click({ position: { x: 80, y: 80 } });
  await page.locator('#gisGoogleMap').click({ position: { x: 160, y: 80 } });
  await page.locator('#gisGoogleMap').click({ position: { x: 160, y: 160 } });
  await page.locator('#gisGoogleSaveAreaBtn').click();
  await expect.poll(() => apiLog.some(item => item.method === 'POST' && item.path === '/api/gis/areas')).toBeTruthy();

  await page.evaluate(() => window.loadGisGoogleMap());
  await page.locator('[data-area-edit="11"]').first().click();
  await page.locator('#gisGoogleAreaName').fill('Khu A sua');
  await page.locator('#gisGoogleSaveAreaBtn').click();
  await expect.poll(() => apiLog.some(item => item.method === 'PUT' && item.path === '/api/gis/areas/11')).toBeTruthy();

  page.once('dialog', dialog => dialog.accept());
  await page.locator('[data-area-delete="11"]').first().click();
  await expect.poll(() => apiLog.some(item => item.method === 'DELETE' && item.path === '/api/gis/areas/11')).toBeTruthy();
});
