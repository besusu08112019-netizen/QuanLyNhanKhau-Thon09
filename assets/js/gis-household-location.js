(function () {
  'use strict';

  const state = {
    markers: new Map(),
    layer: null,
    loading: false,
    picker: null,
    lastSearch: ''
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
    const headers = Object.assign({ Accept: 'application/json' }, (options && options.headers) || {});
    if (token) headers.Authorization = 'Bearer ' + token;
    if (options && options.body && !headers['Content-Type']) headers['Content-Type'] = 'application/json';
    const response = await fetch(path, Object.assign({}, options || {}, { headers }));
    const json = await response.json().catch(() => null);
    if (!response.ok || !json || json.ok === false) throw new Error((json && json.error && json.error.message) || 'Không tải được dữ liệu.');
    return json.data || json;
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
        '<div class="col-md-4"><label class="form-label">Latitude</label><input name="latitude" class="form-control" readonly></div>' +
        '<div class="col-md-4"><label class="form-label">Longitude</label><input name="longitude" class="form-control" readonly></div>' +
        '<div class="col-md-4"><label class="form-label">Nguồn</label><input name="locationSource" class="form-control" readonly></div>' +
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
    if (lat) lat.value = row && row.latitude != null ? row.latitude : '';
    if (lng) lng.value = row && row.longitude != null ? row.longitude : '';
    if (source) source.value = row && row.location_source ? row.location_source : '';
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
    if (typeof window.loadGisMap === 'function') setTimeout(() => window.loadGisMap(), 120);
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

  function useGps(householdId) {
    if (!householdId) { toast('Vui lòng lưu hộ gia đình trước khi lấy GPS.', 'warning'); return; }
    if (!navigator.geolocation) { toast('Thiết bị không hỗ trợ GPS.', 'warning'); return; }
    navigator.geolocation.getCurrentPosition(async position => {
      try {
        await saveLocation(householdId, position.coords.latitude, position.coords.longitude, 'GPS', Math.round(position.coords.accuracy || 0));
      } catch (error) {
        toast(error.message || 'Không lưu được vị trí GPS.', 'danger');
      }
    }, error => toast(error.message || 'Không lấy được vị trí GPS.', 'danger'), { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 });
  }

  function bindLocationButtons() {
    document.addEventListener('click', async event => {
      const button = event.target.closest('[data-household-location-action]');
      if (!button) return;
      event.preventDefault();
      const householdId = currentHouseholdId();
      const action = button.dataset.householdLocationAction;
      if (action === 'pick') startPicker(householdId);
      if (action === 'gps') useGps(householdId);
      if (action === 'clear') {
        if (!householdId) return;
        if (!window.confirm('Xóa vị trí hiện tại của hộ gia đình này?')) return;
        try { await clearLocation(householdId); } catch (error) { toast(error.message || 'Không xóa được vị trí.', 'danger'); }
      }
    });
  }

  function markerIcon(active) {
    return L.divIcon({ className: 'gis-household-marker' + (active ? ' is-active' : ''), html: '<i class="fa-solid fa-house-chimney"></i>', iconSize: [34, 34], iconAnchor: [17, 28], popupAnchor: [0, -28] });
  }

  function popupHtml(row) {
    return '<div class="gis-household-popup">' +
      '<h4>Hộ gia đình</h4>' +
      '<dl>' +
        '<dt>Mã hộ</dt><dd>' + escapeHtml(row.household_code) + '</dd>' +
        '<dt>Chủ hộ</dt><dd>' + escapeHtml(row.head_citizen_name) + '</dd>' +
        '<dt>Địa chỉ</dt><dd>' + escapeHtml(row.address) + '</dd>' +
        '<dt>Nhân khẩu</dt><dd>' + Number(row.total_members || 0).toLocaleString('vi-VN') + '</dd>' +
        '<dt>Điện thoại</dt><dd>' + escapeHtml(row.phone || '') + '</dd>' +
        '<dt>Diện hộ</dt><dd>' + escapeHtml(row.household_type || '') + '</dd>' +
      '</dl>' +
      '<div class="gis-household-popup-actions">' +
        '<button class="btn btn-outline-secondary" type="button" onclick="thon09GisOpenHousehold(' + row.id + ')">Xem</button>' +
        '<button class="btn btn-outline-primary" type="button" onclick="thon09GisEditHousehold(' + row.id + ')">Sửa</button>' +
        '<button class="btn btn-outline-success" type="button" onclick="thon09GisRelocateHousehold(' + row.id + ')">Định vị lại</button>' +
      '</div>' +
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
    const m = map();
    if (!m || !window.L || state.loading) return;
    state.loading = true;
    try {
      if (!state.layer) state.layer = L.layerGroup().addTo(m);
      state.layer.clearLayers();
      state.markers.clear();
      const data = await request('/api/gis/households?' + markerParams(search || '').toString());
      (data.items || []).forEach(row => {
        if (row.latitude == null || row.longitude == null) return;
        const marker = L.marker([row.latitude, row.longitude], { icon: markerIcon(false), title: row.head_citizen_name || row.household_code });
        marker.bindPopup(popupHtml(row));
        marker.on('click', () => {
          state.markers.forEach(item => item.setIcon(markerIcon(false)));
          marker.setIcon(markerIcon(true));
        });
        marker.addTo(state.layer);
        state.markers.set(String(row.id), marker);
      });
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
    setTimeout(() => loadHouseholdMarkers(), 1200);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
