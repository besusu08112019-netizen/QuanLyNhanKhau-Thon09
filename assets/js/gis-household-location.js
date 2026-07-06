(function () {
  'use strict';

  const state = {
    markers: new Map(),
    layer: null,
    loading: false,
    picker: null,
    lastSearch: '',
    thumbnailCache: new Map(),
    lastRows: []
  };

  function $(selector, root) { return (root || document).querySelector(selector); }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
  }
  function toast(message, type) {
    if (typeof window.showToast === 'function') window.showToast(message, type || 'info');
    else console[type === 'danger' ? 'error' : 'log'](message);
  }
  async function request(path, options) {
    if (typeof window.api === 'function') return window.api(path, options || {});
    const token = localStorage.getItem('thon09_token') || (window.App && window.App.token) || '';
    if (!isAuthenticated()) throw new Error('Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại');
    const headers = Object.assign({ Accept: 'application/json' }, (options && options.headers) || {});
    if (token) headers.Authorization = 'Bearer ' + token;
    if (options && options.body && !headers['Content-Type']) headers['Content-Type'] = 'application/json';
    const fetchOptions = Object.assign({}, options || {}, { headers });
    if (fetchOptions.body && typeof fetchOptions.body !== 'string' && !(typeof FormData !== 'undefined' && fetchOptions.body instanceof FormData)) {
      fetchOptions.body = JSON.stringify(fetchOptions.body);
    }
    const response = await fetch(path, fetchOptions);
    const json = await response.json().catch(() => null);
    if (!response.ok || !json || json.ok === false) throw new Error((json && json.error && json.error.message) || 'Không tải được dữ liệu.');
    return json.data || json;
  }
  function loadAssetOnce(kind, url, test) {
    if (typeof test === 'function' && test()) return Promise.resolve(true);
    const attr = 'data-gis-smart-asset';
    if (document.querySelector('[' + attr + '="' + url + '"]')) return Promise.resolve(true);
    return new Promise(resolve => {
      const el = kind === 'style' ? document.createElement('link') : document.createElement('script');
      el.setAttribute(attr, url);
      if (kind === 'style') { el.rel = 'stylesheet'; el.href = url; } else { el.src = url; el.async = true; }
      el.onload = () => resolve(true);
      el.onerror = () => resolve(false);
      document.head.appendChild(el);
    });
  }

  function ensureMarkerCluster() {
    if (!window.L || window.L.markerClusterGroup) return Promise.resolve(Boolean(window.L && window.L.markerClusterGroup));
    return Promise.all([
      loadAssetOnce('style', 'https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css'),
      loadAssetOnce('style', 'https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css'),
      loadAssetOnce('script', 'https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js', () => Boolean(window.L && window.L.markerClusterGroup))
    ]).then(() => Boolean(window.L && window.L.markerClusterGroup));
  }

  function debounce(fn, wait) {
    let timer;
    return function () {
      clearTimeout(timer);
      const args = arguments;
      timer = setTimeout(() => fn.apply(this, args), wait);
    };
  }
  function map() { return window.App && window.App.gis && window.App.gis.map ? window.App.gis.map : null; }
  function isAuthenticated() { return Boolean(window.App && window.App.token); }

  function ensureLocationFields() {
    const form = $('#householdForm');
    if (!form || form.querySelector('.household-location-section')) return;
    const row = form.querySelector('.modal-body .row');
    if (!row) return;
    const section = document.createElement('div');
    section.className = 'col-12 household-location-section';
    section.innerHTML =
      '<h6><i class="fa-solid fa-location-crosshairs"></i> Vị trí hộ gia đình</h6>' +
      '<div class="row g-2">' +
        '<div class="col-md-3"><label class="form-label">Latitude</label><input name="latitude" class="form-control" readonly></div>' +
        '<div class="col-md-3"><label class="form-label">Longitude</label><input name="longitude" class="form-control" readonly></div>' +
        '<div class="col-md-3"><label class="form-label">Nguồn</label><input name="locationSource" class="form-control" readonly></div>' +
        '<div class="col-md-3"><label class="form-label">Độ chính xác</label><input name="locationAccuracy" class="form-control" readonly></div>' +
      '</div>' +
      '<div class="household-location-actions">' +
        '<button class="btn btn-outline-success" type="button" data-household-location-action="pick"><i class="fa-solid fa-map-location-dot"></i> Chọn trên bản đồ</button>' +
        '<button class="btn btn-outline-primary" type="button" data-household-location-action="gps"><i class="fa-solid fa-satellite-dish"></i> Lấy GPS</button>' +
        '<button class="btn btn-outline-danger" type="button" data-household-location-action="clear"><i class="fa-solid fa-trash"></i> Xóa vị trí</button>' +
      '</div>';
    row.appendChild(section);
  }

  function setLocationFields(row) {
    const form = $('#householdForm');
    if (!form) return;
    ensureLocationFields();
    const lat = form.elements.latitude;
    const lng = form.elements.longitude;
    const source = form.elements.locationSource;
    const accuracy = form.elements.locationAccuracy;
    const accuracyValue = row ? (row.location_accuracy != null ? row.location_accuracy : row.accuracy) : '';
    if (lat) lat.value = row && row.latitude != null ? row.latitude : '';
    if (lng) lng.value = row && row.longitude != null ? row.longitude : '';
    if (source) source.value = row && row.location_source ? row.location_source : '';
    if (accuracy) accuracy.value = accuracyValue !== '' && accuracyValue != null ? '±' + accuracyValue + ' m' : '';
  }

  async function hydrateHouseholdLocation(id) {
    if (!id) { setLocationFields(null); return; }
    try {
      const row = await request('/api/households/' + encodeURIComponent(id));
      setLocationFields(row || null);
    } catch (error) {
      console.warn('Cannot hydrate household location', error);
    }
  }

  function currentHouseholdId() {
    const form = $('#householdForm');
    return form && form.elements.id ? String(form.elements.id.value || '').trim() : '';
  }

  function wrapHouseholdForm() {
    if (window.__thon09GisHouseholdFormWrapped) return;
    window.__thon09GisHouseholdFormWrapped = true;
    const original = typeof window.openHouseholdForm === 'function' ? window.openHouseholdForm : null;
    if (original) {
      window.openHouseholdForm = async function (id) {
        const result = await original.apply(this, arguments);
        ensureLocationFields();
        await hydrateHouseholdLocation(id || currentHouseholdId());
        return result;
      };
    }
    document.addEventListener('shown.bs.modal', event => {
      if (event.target && event.target.id === 'householdModal') {
        ensureLocationFields();
        hydrateHouseholdLocation(currentHouseholdId());
      }
    });
  }

  async function saveLocation(householdId, lat, lng, source, accuracy) {
    const marker = await request('/api/gis/households/' + encodeURIComponent(householdId) + '/location', {
      method: 'PUT',
      body: { latitude: lat, longitude: lng, source: source || 'MANUAL', accuracy: accuracy || null }
    });
    setLocationFields(marker);
    await refreshAfterLocationChange();
    toast('Đã lưu vị trí hộ gia đình.', 'success');
    return marker;
  }

  async function clearLocation(householdId) {
    await request('/api/gis/households/' + encodeURIComponent(householdId) + '/location', { method: 'DELETE' });
    setLocationFields(null);
    await refreshAfterLocationChange();
    toast('Đã xóa vị trí hộ gia đình.', 'success');
  }

  async function refreshAfterLocationChange() {
    await loadHouseholdMarkers();
    if (typeof window.loadDashboard === 'function') setTimeout(() => window.loadDashboard(), 160);
    if (typeof window.loadHouseholds === 'function') setTimeout(() => window.loadHouseholds(), 180);
  }

  function closeHouseholdModal() {
    const modal = $('#householdModal');
    if (!modal || !window.bootstrap) return;
    const instance = window.bootstrap.Modal.getInstance(modal);
    if (instance) instance.hide();
  }

  function getMapWhenReady(callback, tries) {
    const m = map();
    if (m) { callback(m); return; }
    if ((tries || 0) > 30) { toast('Bản đồ chưa sẵn sàng. Vui lòng mở lại màn hình bản đồ.', 'warning'); return; }
    setTimeout(() => getMapWhenReady(callback, (tries || 0) + 1), 120);
  }

  function startPicker(householdId) {
    if (!householdId) { toast('Vui lòng lưu hộ gia đình trước khi định vị.', 'warning'); return; }
    closeHouseholdModal();
    if (typeof window.switchScreen === 'function') window.switchScreen('gis');
    getMapWhenReady(m => {
      state.picker = { householdId: String(householdId) };
      document.body.classList.add('gis-location-picking');
      showPickBanner('Bấm vào vị trí ngôi nhà trên bản đồ để lưu tọa độ.');
      m.once('click', async event => {
        const ok = window.confirm('Lưu vị trí này cho hộ gia đình?\nLatitude: ' + event.latlng.lat.toFixed(8) + '\nLongitude: ' + event.latlng.lng.toFixed(8));
        hidePickBanner();
        document.body.classList.remove('gis-location-picking');
        if (!ok) return;
        try {
          await saveLocation(householdId, event.latlng.lat, event.latlng.lng, 'MANUAL');
        } catch (error) {
          toast(error.message || 'Không lưu được vị trí hộ gia đình.', 'danger');
        } finally {
          state.picker = null;
        }
      });
    });
  }

  function showPickBanner(text) {
    hidePickBanner();
    const mapEl = $('#gisMap');
    if (!mapEl || !mapEl.parentElement) return;
    const banner = document.createElement('div');
    banner.className = 'gis-location-pick-banner';
    banner.textContent = text;
    mapEl.parentElement.style.position = mapEl.parentElement.style.position || 'relative';
    mapEl.parentElement.appendChild(banner);
  }

  function hidePickBanner() {
    document.querySelectorAll('.gis-location-pick-banner').forEach(el => el.remove());
  }

  function gpsErrorMessage(error) {
    const rawMessage = String(error && error.message ? error.message : '').trim();
    const message = rawMessage.toLowerCase();
    if (message.includes('permission') && message.includes('policy')) {
      return 'GPS đang bị chặn bởi Permissions-Policy của hosting. Vui lòng kiểm tra header geolocation=(self).';
    }
    if (error && error.code === error.PERMISSION_DENIED) {
      return 'Bạn đã từ chối quyền vị trí. Vui lòng mở Site Settings của trình duyệt và cho phép Location.';
    }
    if (error && error.code === error.POSITION_UNAVAILABLE) {
      return 'Thiết bị không cung cấp được vị trí hiện tại. Vui lòng bật GPS hoặc thử lại ngoài trời.';
    }
    if (error && error.code === error.TIMEOUT) {
      return 'Quá thời gian lấy GPS. Vui lòng kiểm tra tín hiệu vị trí và thử lại.';
    }
    return rawMessage || 'Không lấy được vị trí GPS.';
  }

  function useGps(householdId, triggerButton) {
    if (!householdId) { toast('Vui lòng lưu hộ gia đình trước khi lấy GPS.', 'warning'); return; }
    if (!window.isSecureContext) { toast('GPS chỉ hoạt động trên HTTPS hoặc localhost. Vui lòng truy cập bằng HTTPS.', 'danger'); return; }
    if (!navigator.geolocation) { toast('Thiết bị không hỗ trợ GPS.', 'warning'); return; }

    const button = triggerButton || $('[data-household-location-action="gps"]');
    const originalHtml = button ? button.innerHTML : '';
    const restoreButton = function () {
      if (!button) return;
      button.disabled = false;
      button.innerHTML = originalHtml;
    };
    if (button) {
      button.disabled = true;
      button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang lấy GPS...';
    }

    navigator.geolocation.getCurrentPosition(async position => {
      try {
        const accuracy = Math.round(position.coords.accuracy || 0);
        await saveLocation(householdId, position.coords.latitude, position.coords.longitude, 'GPS', accuracy);
      } catch (error) {
        toast(error.message || 'Không lưu được vị trí GPS.', 'danger');
      } finally {
        restoreButton();
      }
    }, error => {
      restoreButton();
      toast(gpsErrorMessage(error), 'danger');
    }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
  }

  function bindLocationButtons() {
    document.addEventListener('click', async event => {
      const button = event.target.closest('[data-household-location-action]');
      if (!button) return;
      event.preventDefault();
      const householdId = currentHouseholdId();
      const action = button.dataset.householdLocationAction;
      if (action === 'pick') startPicker(householdId);
      if (action === 'gps') useGps(householdId, button);
      if (action === 'clear') {
        if (!householdId) return;
        if (!window.confirm('Xóa vị trí hiện tại của hộ gia đình này?')) return;
        try { await clearLocation(householdId); } catch (error) { toast(error.message || 'Không xóa được vị trí.', 'danger'); }
      }
    });
  }

  function previewUrl(id) { return id ? '/api/files/' + encodeURIComponent(id) + '/preview' : ''; }

  async function loadPreviewBlobUrl(fileId) {
    if (!fileId) return '';
    const key = String(fileId);
    if (state.thumbnailCache.has(key)) return state.thumbnailCache.get(key);
    const headers = {};
    if (window.App && window.App.token) headers.Authorization = 'Bearer ' + window.App.token;
    const response = await fetch(previewUrl(fileId), { headers, cache: 'force-cache' });
    if (!response.ok) throw new Error('Kh?ng t?i ???c ?nh h?');
    const url = URL.createObjectURL(await response.blob());
    state.thumbnailCache.set(key, url);
    return url;
  }

  function markerIcon(active, thumbnailUrl) {
    const html = thumbnailUrl
      ? '<span class="gis-household-marker-thumb"><img src="' + escapeHtml(thumbnailUrl) + '" alt=""></span>'
      : '<span class="gis-household-marker-default"><i class="fa-solid fa-house-chimney"></i></span>';
    return L.divIcon({ className: 'gis-household-marker' + (active ? ' is-active' : '') + (thumbnailUrl ? ' has-photo' : ''), html, iconSize: [44, 44], iconAnchor: [22, 38], popupAnchor: [0, -36] });
  }

  function hydrateMarkerThumbnail(marker, row) {
    if (!row.thumbnail_file_id || marker.__thon09ThumbLoading) return;
    marker.__thon09ThumbLoading = true;
    loadPreviewBlobUrl(row.thumbnail_file_id).then(url => {
      row.__thumbnailObjectUrl = url;
      marker.__thon09HouseholdRow = row;
      marker.setIcon(markerIcon(state.activeMarkerId === String(row.id), url));
      marker.setPopupContent(popupHtml(row));
    }).catch(() => {});
  }

  function gpsText(row) {
    if (row.latitude == null || row.longitude == null) return 'Ch?a c? GPS';
    return Number(row.latitude).toFixed(6) + ', ' + Number(row.longitude).toFixed(6);
  }

  function popupImageHtml(row) {
    const url = row.__thumbnailObjectUrl || '';
    if (url) return '<img src="' + escapeHtml(url) + '" alt="?nh h?" loading="lazy">';
    return '<div class="gis-household-popup-photo-empty"><i class="fa-solid fa-house-chimney"></i></div>';
  }

  function popupHtml(row) {
    const phone = String(row.phone || '').trim();
    return '<div class="gis-household-popup gis-smart-popup">' +
      '<div class="gis-smart-popup-head"><div class="gis-smart-popup-photo" data-gis-popup-photo="' + Number(row.thumbnail_file_id || 0) + '">' + popupImageHtml(row) + '</div>' +
      '<div><h4>' + escapeHtml(row.household_code || 'H? gia ??nh') + '</h4><p>' + escapeHtml(row.head_citizen_name || 'Ch?a c? ch? h?') + '</p><span>' + escapeHtml(row.household_type || 'H? b?nh th??ng') + '</span></div></div>' +
      '<dl>' +
        '<dt>M? h?</dt><dd>' + escapeHtml(row.household_code) + '</dd>' +
        '<dt>Ch? h?</dt><dd>' + escapeHtml(row.head_citizen_name) + '</dd>' +
        '<dt>??a ch?</dt><dd>' + escapeHtml(row.address) + '</dd>' +
        '<dt>S? nh?n kh?u</dt><dd>' + Number(row.total_members || 0).toLocaleString('vi-VN') + '</dd>' +
        '<dt>?ang c? tr?</dt><dd>' + Number(row.at_home_count || 0).toLocaleString('vi-VN') + '</dd>' +
        '<dt>Tr?ng th?i h?</dt><dd>' + escapeHtml(row.status || 'ACTIVE') + '</dd>' +
        '<dt>GPS</dt><dd>' + escapeHtml(gpsText(row)) + '</dd>' +
      '</dl>' +
      '<div class="gis-smart-popup-actions">' +
        '<button class="btn btn-sm btn-primary" type="button" onclick="thon09GisOpenHousehold(' + row.id + ')"><i class="fa-solid fa-folder-open"></i> H? s? s?</button>' +
        '<button class="btn btn-sm btn-success" type="button" onclick="thon09GisRouteToHousehold(' + row.id + ')"><i class="fa-solid fa-route"></i> Ch? ???ng</button>' +
        '<button class="btn btn-sm btn-outline-secondary" type="button" onclick="thon09GisOpenHouseholdGallery(' + row.id + ')"><i class="fa-solid fa-images"></i> Xem ?nh</button>' +
        (phone ? '<a class="btn btn-sm btn-outline-primary" href="tel:' + escapeHtml(phone) + '"><i class="fa-solid fa-phone"></i> G?i ?i?n</a>' : '') +
        '<button class="btn btn-sm btn-outline-success" type="button" onclick="thon09GisRelocateHousehold(' + row.id + ')"><i class="fa-solid fa-location-crosshairs"></i> GPS</button>' +
      '</div>' +
      '<div class="gis-smart-route-links"><button type="button" onclick="thon09GisOpenMapProvider(' + row.id + ', \'google\')">Google Maps</button><button type="button" onclick="thon09GisOpenMapProvider(' + row.id + ', \'apple\')">Apple Maps</button><button type="button" onclick="thon09GisOpenMapProvider(' + row.id + ', \'osm\')">OpenStreetMap</button></div>' +
    '</div>';
  }

  function markerParams(search) {
    const params = new URLSearchParams();
    if (search) {
      params.set('search', search);
      return params;
    }
    const m = map();
    if (!m || !m.getBounds) return params;
    const bounds = m.getBounds();
    params.set('north', bounds.getNorth());
    params.set('south', bounds.getSouth());
    params.set('east', bounds.getEast());
    params.set('west', bounds.getWest());
    return params;
  }

  async function loadHouseholdMarkers(search) {
    if (!isAuthenticated()) return;
    const m = map();
    if (!m || !window.L || state.loading) return;
    state.loading = true;
    try {
      if (!state.layer) {
        await ensureMarkerCluster();
        state.layer = L.markerClusterGroup ? L.markerClusterGroup({ chunkedLoading: true, showCoverageOnHover: false, maxClusterRadius: 46 }) : L.layerGroup();
        state.layer.addTo(m);
      }
      state.layer.clearLayers();
      state.markers.clear();
      const data = await request('/api/gis/households?' + markerParams(search || '').toString());
      (data.items || []).forEach(row => {
        if (row.latitude == null || row.longitude == null) return;
        const marker = L.marker([row.latitude, row.longitude], { icon: markerIcon(false, (item.__thon09HouseholdRow || {}).__thumbnailObjectUrl || ''), title: row.head_citizen_name || row.household_code });
        marker.bindPopup(popupHtml(row));
        marker.on('click', () => {
          state.markers.forEach(item => item.setIcon(markerIcon(false, (item.__thon09HouseholdRow || {}).__thumbnailObjectUrl || '')));
          marker.setIcon(markerIcon(true, (marker.__thon09HouseholdRow || {}).__thumbnailObjectUrl || ''));
        });
        marker.addTo(state.layer);
        marker.__thon09HouseholdRow = row;
        state.markers.set(String(row.id), marker);
      });
      state.lastRows = data.items || [];
      document.dispatchEvent(new CustomEvent('thon09:gis-markers-loaded', { detail: { rows: state.lastRows, layer: state.layer } }));
      if (search && state.markers.size) {
        const first = state.markers.values().next().value;
        m.setView(first.getLatLng(), Math.max(m.getZoom(), 17));
        first.openPopup();
      }
    } catch (error) {
      console.warn('GIS household markers failed', error);
    } finally {
      state.loading = false;
    }
  }

  function bindGisMapHooks() {
    if (window.__thon09GisHouseholdMapWrapped) return;
    window.__thon09GisHouseholdMapWrapped = true;
    const originalLoad = typeof window.loadGisMap === 'function' ? window.loadGisMap : null;
    if (originalLoad) {
      window.loadGisMap = async function () {
        const result = await originalLoad.apply(this, arguments);
        setTimeout(() => loadHouseholdMarkers(), 180);
        return result;
      };
    }
    const bindMove = () => {
      const m = map();
      if (!m || m.__thon09HouseholdMarkerMoveBound) return;
      m.__thon09HouseholdMarkerMoveBound = true;
      m.on('moveend zoomend', debounce(() => loadHouseholdMarkers(), 450));
    };
    const search = $('#gisSearch');
    if (search && !search.__thon09HouseholdSearchBound) {
      search.__thon09HouseholdSearchBound = true;
      search.addEventListener('input', debounce(() => {
        const value = String(search.value || '').trim();
        if (value.length > 1) loadHouseholdMarkers(value);
        else loadHouseholdMarkers();
      }, 450));
    }
    setInterval(bindMove, 1000);
  }



  window.thon09GisGetHouseholdMarkerRow = function (id) {
    const marker = state.markers.get(String(id || ''));
    return marker ? marker.__thon09HouseholdRow || null : null;
  };

  window.thon09GisFocusHouseholdMarker = function (row) {
    const m = map();
    if (!m || !row) return false;
    const id = String(row.id || '');
    let marker = id ? state.markers.get(id) : null;
    if (!marker && row.latitude != null && row.longitude != null) {
      const normalized = Object.assign({}, row, { head_citizen_name: row.head_citizen_name || row.head_name || '' });
      marker = L.marker([row.latitude, row.longitude], { icon: markerIcon(true), title: normalized.head_citizen_name || normalized.household_code });
      marker.__thon09HouseholdRow = normalized;
      marker.bindPopup(popupHtml(normalized));
      if (!state.layer) state.layer = L.layerGroup().addTo(m);
      marker.addTo(state.layer);
      state.markers.set(id, marker);
    }
    if (!marker) return false;
    state.activeMarkerId = id;
    state.markers.forEach(item => item.setIcon(markerIcon(false, (item.__thon09HouseholdRow || {}).__thumbnailObjectUrl || '')));
    marker.setIcon(markerIcon(true));
    m.setView(marker.getLatLng(), Math.max(m.getZoom(), 17), { animate: true });
    marker.openPopup();
    return true;
  };

  window.thon09GisOpenHousehold = function (id) {
    if (typeof window.showHousehold === 'function') window.showHousehold(id);
  };
  window.thon09GisEditHousehold = function (id) {
    if (typeof window.openHouseholdForm === 'function') window.openHouseholdForm(id);
  };
  window.thon09GisRelocateHousehold = function (id) { startPicker(id); };
  window.thon09LoadGisHouseholdMarkers = loadHouseholdMarkers;

  function start() {
    ensureLocationFields();
    wrapHouseholdForm();
    bindLocationButtons();
    bindGisMapHooks();
    if (isAuthenticated()) setTimeout(() => loadHouseholdMarkers(), 1200);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
