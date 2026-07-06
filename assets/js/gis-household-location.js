(function () {
  'use strict';

  const state = {
    markers: new Map(),
    layer: null,
    loading: false,
    picker: null,
    lastSearch: '',
    thumbnailCache: new Map(),
    lastRows: [],
    lastLocatedRows: [],
    openPopupId: '',
    renderingMarkers: false,
    lastMarkerTouchAt: 0,
    mapDraggingWasEnabled: null
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

  function gisDebugEnabled() {
    try {
      return /(?:^|[?&])gis_debug=1(?:&|$)/.test(window.location.search || '') || localStorage.getItem('thon09_gis_debug') === '1';
    } catch (_) {
      return false;
    }
  }

  function gisDebugLog(label, detail) {
    if (!gisDebugEnabled()) return;
    const payload = Object.assign({
      label,
      time: new Date().toISOString(),
      openPopupId: state.openPopupId,
      markerCount: state.markers.size,
      layerCount: state.layer && typeof state.layer.getLayers === 'function' ? state.layer.getLayers().length : null,
      touchViewport: typeof isTouchViewport === 'function' ? isTouchViewport() : null,
    }, detail || {});
    console.log('[GIS_POPUP_DEBUG]', payload);
    console.trace('[GIS_POPUP_TRACE] ' + label);
  }

  function wrapMethodForGisDebug(target, methodName, label) {
    if (!target || typeof target[methodName] !== 'function') return;
    const flag = '__thon09DebugWrapped_' + methodName;
    if (target[flag]) return;
    target[flag] = true;
    const original = target[methodName];
    target[methodName] = function () {
      gisDebugLog(label || methodName, { args: Array.from(arguments).map(arg => {
        if (arg && typeof arg === 'object') return arg.constructor && arg.constructor.name ? '[object ' + arg.constructor.name + ']' : '[object]';
        return arg;
      }) });
      return original.apply(this, arguments);
    };
  }


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
    if (!response.ok) throw new Error('Không tải được ảnh hộ');
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
      const rowId = normalizeHouseholdId(row);
      const popupIsOpen = state.openPopupId === rowId && marker.isPopupOpen && marker.isPopupOpen();
      if (!popupIsOpen) marker.setIcon(markerIcon(state.activeMarkerId === rowId, url));
      marker.setPopupContent(popupHtml(row));
      if (popupIsOpen) {
        marker.openPopup();
        const popup = marker.getPopup && marker.getPopup();
        protectPopupElement(popup);
        bindPopupActions(popup, row);
      }
    }).catch(() => {});
  }

  function isTouchViewport() {
    return Boolean(
      (navigator && navigator.maxTouchPoints > 0) ||
      (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) ||
      window.innerWidth <= 1024
    );
  }

  function shouldDeferMarkerReload(search, options) {
    if (search || (options && options.force)) return false;
    if (state.openPopupId) return true;
    return isTouchViewport() && state.markers.size > 0;
  }

  function stopLeafletPropagation(event) {
    const original = event && event.originalEvent ? event.originalEvent : event;
    if (!original || !window.L || !L.DomEvent) return;
    L.DomEvent.stopPropagation(original);
  }

  function stopNativeEvent(event, preventDefault) {
    if (!event) return;
    if (preventDefault && typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }

  function disableMapDraggingForPopup() {
    const m = map();
    if (!m || !m.dragging) return;
    if (state.mapDraggingWasEnabled === null) {
      state.mapDraggingWasEnabled = typeof m.dragging.enabled === 'function' ? m.dragging.enabled() : true;
    }
    if (typeof m.dragging.disable === 'function') m.dragging.disable();
  }

  function restoreMapDraggingAfterPopup() {
    const m = map();
    if (!m || !m.dragging) return;
    const shouldEnable = state.mapDraggingWasEnabled !== false;
    state.mapDraggingWasEnabled = null;
    if (shouldEnable && typeof m.dragging.enable === 'function') m.dragging.enable();
  }

  function protectPopupElement(popup) {
    if (!popup || !window.L || !L.DomEvent || typeof popup.getElement !== 'function') return;
    const el = popup.getElement();
    if (!el || el.__thon09PopupPropagationLocked) return;
    el.__thon09PopupPropagationLocked = true;
    el.style.pointerEvents = 'auto';
    el.style.touchAction = 'manipulation';
    L.DomEvent.disableClickPropagation(el);
    L.DomEvent.disableScrollPropagation(el);
    ['touchstart', 'touchend'].forEach(type => {
      el.addEventListener(type, event => stopNativeEvent(event, false), { passive: false });
    });
    ['pointerdown', 'pointerup'].forEach(type => {
      el.addEventListener(type, event => stopNativeEvent(event, false));
    });
  }

  function normalizeHouseholdId(row) {
    if (!row) return '';
    const raw = row.id != null && row.id !== '' ? row.id : (row.household_id != null && row.household_id !== '' ? row.household_id : row.householdId);
    return raw == null ? '' : String(raw).trim();
  }

  function normalizeHouseholdRow(row) {
    if (!row) return null;
    const id = normalizeHouseholdId(row);
    return id && String(row.id || '') !== id ? Object.assign({}, row, { id }) : row;
  }

  function popupActionElement(event) {
    if (!event) return null;
    const current = event.currentTarget;
    if (current && current.dataset && current.dataset.gisPopupAction) return current;
    const target = event.target;
    return target && typeof target.closest === 'function' ? target.closest('[data-gis-popup-action]') : null;
  }
  function popupRowById(id, fallbackRow) {
    const fallback = normalizeHouseholdRow(fallbackRow);
    const key = String(id || normalizeHouseholdId(fallback) || '').trim();
    if (fallback && (!key || normalizeHouseholdId(fallback) === key)) return fallback;
    if (key && typeof window.thon09GisGetHouseholdMarkerRow === 'function') {
      const row = normalizeHouseholdRow(window.thon09GisGetHouseholdMarkerRow(key));
      if (row) return row;
    }
    return fallback || null;
  }

  function googleDirectionsUrl(row) {
    if (!row || row.latitude == null || row.longitude == null) return '';
    const lat = Number(row.latitude);
    const lng = Number(row.longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return '';
    return 'https://www.google.com/maps/dir/?api=1&destination=' + lat + ',' + lng;
  }

  function openHouseholdTab(id, tab) {
    if (!id || typeof window.showHousehold !== 'function') {
      toast('Không mở được hồ sơ hộ.', 'warning');
      return;
    }
    Promise.resolve(window.showHousehold(id)).then(() => {
      if (!tab) return;
      setTimeout(() => {
        const button = document.querySelector('[data-household-tab="' + tab + '"]');
        if (button) button.click();
      }, 80);
    }).catch(error => toast(error.message || 'Không mở được hồ sơ hộ.', 'danger'));
  }

  function openGoogleDirections(row) {
    const url = googleDirectionsUrl(row);
    if (!url) {
      toast('Hộ này chưa có tọa độ trên bản đồ.', 'warning');
      return;
    }
    window.open(url, '_blank', 'noopener');
  }

  function closeOpenHouseholdPopup(exceptId) {
    const except = String(exceptId || '').trim();
    state.markers.forEach((marker, id) => {
      if (except && String(id) === except) return;
      if (marker && marker.isPopupOpen && marker.isPopupOpen() && marker.closePopup) marker.closePopup();
    });
    const m = map();
    if (m && typeof m.closePopup === 'function' && (!except || state.openPopupId !== except)) m.closePopup();
    if (!except || state.openPopupId !== except) state.openPopupId = '';
  }
  function runPopupAction(action, row, event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }
    const target = popupActionElement(event);
    const targetDataset = target && target.dataset ? target.dataset : {};
    const id = String(targetDataset.householdId || normalizeHouseholdId(row) || '').trim();
    const activeRow = popupRowById(id, row);
    const householdId = id || normalizeHouseholdId(activeRow);
    if (!householdId) {
      toast('Không xác định được hộ gia đình.', 'warning');
      return;
    }
    if (action === 'open') window.thon09GisOpenHousehold(householdId);
    if (action === 'route') openGoogleDirections(activeRow);
    if (action === 'gallery') window.thon09GisOpenHouseholdGallery(householdId);
    if (action === 'relocate') window.thon09GisRelocateHousehold(householdId, target);
    if (action === 'phone') {
      const phone = targetDataset.phone || '';
      if (phone) window.location.href = 'tel:' + phone;
    }
  }

  function bindPopupDelegation() {
    if (document.__thon09GisPopupDelegationBound) return;
    document.__thon09GisPopupDelegationBound = true;
    document.addEventListener('click', event => {
      const button = event.target && event.target.closest ? event.target.closest('.leaflet-popup [data-gis-popup-action]') : null;
      if (!button || button.__thon09PopupActionBound) return;
      const householdId = button.getAttribute('data-household-id') || '';
      runPopupAction(button.dataset.gisPopupAction, popupRowById(householdId), event);
    });
  }

  function bindPopupActions(popup, row) {
    if (!popup || typeof popup.getElement !== 'function') return;
    const el = popup.getElement();
    if (!el) return;
    el.querySelectorAll('[data-gis-popup-action]').forEach(button => {
      if (button.__thon09PopupActionBound) return;
      button.__thon09PopupActionBound = true;
      button.style.pointerEvents = 'auto';
      button.style.touchAction = 'manipulation';
      ['touchstart', 'pointerdown', 'pointerup'].forEach(type => {
        button.addEventListener(type, event => stopNativeEvent(event, false), { passive: false });
      });
      button.addEventListener('touchend', event => {
        button.__thon09LastTouchActionAt = Date.now();
        runPopupAction(button.dataset.gisPopupAction, row, event);
      }, { passive: false });
      button.addEventListener('click', event => {
        if (button.__thon09LastTouchActionAt && Date.now() - button.__thon09LastTouchActionAt < 700) {
          stopNativeEvent(event, true);
          return;
        }
        runPopupAction(button.dataset.gisPopupAction, row, event);
      });
    });
  }

  function bindHouseholdPopup(marker, row) {
    row = normalizeHouseholdRow(row) || {};
    const rowId = normalizeHouseholdId(row);
    const popup = L.popup({ closeButton: true, autoClose: true, closeOnClick: false, bubblingMouseEvents: false, autoPan: true, keepInView: true }).setContent(popupHtml(row));
    popup.on('add', () => {
      protectPopupElement(popup);
      bindPopupActions(popup, row);
    });
    marker.bindPopup(popup);
    marker.on('click', event => {
      gisDebugLog('marker click', { id: rowId });
      state.lastMarkerTouchAt = Date.now();
      stopLeafletPropagation(event);
      closeOpenHouseholdPopup(rowId);
      state.openPopupId = rowId;
      marker.openPopup();
      activateMarker(marker, row);
    });
    marker.on('popupopen', event => {
      gisDebugLog('popup open', { id: rowId, popupInDom: Boolean(event && event.popup && event.popup.getElement && event.popup.getElement()) });
      state.openPopupId = rowId;
      disableMapDraggingForPopup();
      window.thon09GisEnsureHouseholdMarkerLayerVisible && window.thon09GisEnsureHouseholdMarkerLayerVisible();
      protectPopupElement(event && event.popup ? event.popup : marker.getPopup && marker.getPopup());
      bindPopupActions(event && event.popup ? event.popup : marker.getPopup && marker.getPopup(), row);
      activateMarker(marker, row);
    });
    marker.on('popupclose', () => {
      gisDebugLog('popup close', { id: rowId, renderingMarkers: state.renderingMarkers, now: Date.now() });
      if (state.renderingMarkers) return;
      if (state.openPopupId !== rowId) return;
      state.openPopupId = '';
      restoreMapDraggingAfterPopup();
    });
    ['touchstart', 'touchend', 'pointerdown', 'pointerup'].forEach(type => {
      marker.on(type, event => {
        gisDebugLog('marker ' + type, { id: rowId });
        state.lastMarkerTouchAt = Date.now();
        stopLeafletPropagation(event);
      });
    });
  }

  function activateMarker(marker, row) {
    state.activeMarkerId = normalizeHouseholdId(row);
    state.markers.forEach(item => {
      const element = item.getElement && item.getElement();
      if (element) element.classList.remove('is-active');
    });
    const element = marker.getElement && marker.getElement();
    if (element) element.classList.add('is-active');
  }

  function gpsText(row) {
    row = row || {};
    if (row.latitude == null || row.longitude == null) return 'Chưa có GPS';
    return Number(row.latitude).toFixed(6) + ', ' + Number(row.longitude).toFixed(6);
  }

  function popupImageHtml(row) {
    row = row || {};
    const url = row.__thumbnailObjectUrl || '';
    if (url) return '<img src="' + escapeHtml(url) + '" alt="Ảnh hộ" loading="lazy">';
    return '<div class="gis-household-popup-photo-empty"><i class="fa-solid fa-house-chimney"></i></div>';
  }

  function popupHtml(row) {
    row = normalizeHouseholdRow(row) || {};
    const phone = String(row.phone || '').trim();
    const householdId = escapeHtml(normalizeHouseholdId(row));
    return '<div class="gis-household-popup gis-smart-popup">' +
      '<div class="gis-smart-popup-head"><div class="gis-smart-popup-photo" data-gis-popup-photo="' + Number(row.thumbnail_file_id || 0) + '">' + popupImageHtml(row) + '</div>' +
      '<div><h4>' + escapeHtml(row.household_code || 'Hộ gia đình') + '</h4><p>' + escapeHtml(row.head_citizen_name || 'Chưa có chủ hộ') + '</p><span>' + escapeHtml(row.household_type || 'Hộ bình thường') + '</span></div></div>' +
      '<dl>' +
        '<dt>Mã hộ</dt><dd>' + escapeHtml(row.household_code) + '</dd>' +
        '<dt>Chủ hộ</dt><dd>' + escapeHtml(row.head_citizen_name) + '</dd>' +
        '<dt>Địa chỉ</dt><dd>' + escapeHtml(row.address) + '</dd>' +
        '<dt>Số nhân khẩu</dt><dd>' + Number(row.total_members || 0).toLocaleString('vi-VN') + '</dd>' +
        '<dt>Đang cư trú</dt><dd>' + Number(row.at_home_count || 0).toLocaleString('vi-VN') + '</dd>' +
        '<dt>GPS</dt><dd>' + escapeHtml(gpsText(row)) + '</dd>' +
      '</dl>' +
      '<div class="gis-smart-popup-actions">' +
        '<button class="btn btn-sm btn-primary" type="button" data-gis-popup-action="open" data-household-id="' + householdId + '"><i class="fa-solid fa-folder-open"></i> Hồ sơ số</button>' +
        '<button class="btn btn-sm btn-success" type="button" data-gis-popup-action="route" data-household-id="' + householdId + '"><i class="fa-solid fa-route"></i> Chỉ đường</button>' +
        '<button class="btn btn-sm btn-outline-secondary" type="button" data-gis-popup-action="gallery" data-household-id="' + householdId + '"><i class="fa-solid fa-images"></i> Xem ảnh</button>' +
        (phone ? '<a class="btn btn-sm btn-outline-primary" href="tel:' + escapeHtml(phone) + '" data-gis-popup-action="phone" data-household-id="' + householdId + '" data-phone="' + escapeHtml(phone) + '"><i class="fa-solid fa-phone"></i> Gọi điện</a>' : '') +
        '<button class="btn btn-sm btn-outline-success" type="button" data-gis-popup-action="relocate" data-household-id="' + householdId + '"><i class="fa-solid fa-location-crosshairs"></i> GPS</button>' +
      '</div>' +
    '</div>';
  }

  function markerParams(search) {
    const params = new URLSearchParams();
    if (search) {
      params.set('q', search);
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

  function createHouseholdMarkerLayer() {
    const useCluster = !isTouchViewport() && Boolean(L.markerClusterGroup);
    gisDebugLog('markerLayer:create', { markerCluster: useCluster, touchViewport: isTouchViewport() });
    return useCluster
      ? L.markerClusterGroup({ chunkedLoading: true, showCoverageOnHover: false, maxClusterRadius: 46 })
      : L.layerGroup();
  }

  function locatedRowsFromResponse(rows) {
    return (rows || []).map(normalizeHouseholdRow).filter(row => row && row.latitude != null && row.longitude != null);
  }

  function renderLocatedMarkers(rows) {
    gisDebugLog('renderLocatedMarkers', { rows: (rows || []).length });
    if (!state.layer) return;
    state.layer.clearLayers();
    state.markers.clear();
    rows.forEach(row => {
      const marker = L.marker([row.latitude, row.longitude], { icon: markerIcon(false, row.__thumbnailObjectUrl || ''), title: row.head_citizen_name || row.household_code, bubblingMouseEvents: false });
      marker.__thon09HouseholdRow = row;
      bindHouseholdPopup(marker, row);
      marker.addTo(state.layer);
      state.markers.set(normalizeHouseholdId(row), marker);
      hydrateMarkerThumbnail(marker, row);
    });
  }

  function restoreMarkersIfMissing() {
    gisDebugLog('restoreMarkersIfMissing:check');
    const m = map();
    if (!m || !window.L || !state.layer || !isTouchViewport() || !state.lastLocatedRows.length) return;
    if (typeof m.hasLayer === 'function' && !m.hasLayer(state.layer)) state.layer.addTo(m);
    const layerHasMarkers = typeof state.layer.getLayers === 'function' ? state.layer.getLayers().length > 0 : state.markers.size > 0;
    if (state.markers.size > 0 && layerHasMarkers) return;
    renderLocatedMarkers(state.lastLocatedRows);
    document.dispatchEvent(new CustomEvent('thon09:gis-markers-loaded', { detail: { rows: state.lastRows, layer: state.layer, restored: true } }));
  }

  async function loadHouseholdMarkers(search, options) {
    gisDebugLog('loadHouseholdMarkers:start', { search: search || '', options: options || null });
    if (!isAuthenticated()) return;
    if (shouldDeferMarkerReload(search, options)) return;
    const m = map();
    if (!m || !window.L || state.loading) return;
    state.loading = true;
    state.renderingMarkers = true;
    try {
      if (!state.layer) {
        if (!isTouchViewport()) await ensureMarkerCluster();
        state.layer = createHouseholdMarkerLayer();
        wrapMethodForGisDebug(state.layer, 'clearLayers', 'markerLayer.clearLayers');
        wrapMethodForGisDebug(state.layer, 'addLayer', 'markerLayer.addLayer');
        wrapMethodForGisDebug(state.layer, 'removeLayer', 'markerLayer.removeLayer');
        state.layer.addTo(m);
      }
      const popupIdBeforeRender = state.openPopupId;
      const data = await request('/api/gis/households?' + markerParams(search || '').toString());
      gisDebugLog('loadHouseholdMarkers:response', { items: (data.items || []).length });
      const popupOpenedDuringRequest = !popupIdBeforeRender && state.openPopupId && !search && !(options && options.force);
      if (popupOpenedDuringRequest) {
        document.dispatchEvent(new CustomEvent('thon09:gis-markers-loaded', { detail: { rows: state.lastRows, layer: state.layer, keptExisting: true, skippedStaleReload: true } }));
        return;
      }
      const rows = data.items || [];
      const locatedRows = locatedRowsFromResponse(rows);
      const keepExistingMarkers = !search && !(options && options.force) && locatedRows.length === 0 && state.markers.size > 0;
      if (keepExistingMarkers) {
        document.dispatchEvent(new CustomEvent('thon09:gis-markers-loaded', { detail: { rows: state.lastRows, layer: state.layer, keptExisting: true } }));
        return;
      }
      state.openPopupId = popupIdBeforeRender;
      if (locatedRows.length > 0) state.lastLocatedRows = locatedRows;
      renderLocatedMarkers(locatedRows);
      state.lastRows = rows;
      document.dispatchEvent(new CustomEvent('thon09:gis-markers-loaded', { detail: { rows: state.lastRows, layer: state.layer } }));
      if (search && state.markers.size) {
        const first = state.markers.values().next().value;
        m.setView(first.getLatLng(), Math.max(m.getZoom(), 17));
        const firstId = String((first.__thon09HouseholdRow || {}).id || '');
        closeOpenHouseholdPopup(firstId);
        state.openPopupId = firstId;
        first.openPopup();
      }
    } catch (error) {
      console.warn('GIS household markers failed', error);
    } finally {
      state.renderingMarkers = false;
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
        setTimeout(() => loadHouseholdMarkers(undefined, { background: true }), 180);
        return result;
      };
    }
    const bindMove = () => {
      const m = map();
      if (!m || m.__thon09HouseholdMarkerMoveBound) return;
      m.__thon09HouseholdMarkerMoveBound = true;
      m.on('moveend zoomend', debounce(() => {
        if (state.openPopupId) return;
        loadHouseholdMarkers(undefined, { background: true });
      }, 450));
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
    bindMove();
    setInterval(bindMove, 1000);
  }



  window.thon09GisHasOpenHouseholdPopup = function () {
    return Boolean(state.openPopupId);
  };

  window.thon09GisEnsureHouseholdMarkerLayerVisible = function () {
    const m = map();
    if (!m || !state.layer) return false;
    if (typeof m.hasLayer === 'function' && !m.hasLayer(state.layer)) state.layer.addTo(m);
    return true;
  };

  window.thon09GisGetHouseholdMarkerRow = function (id) {
    const marker = state.markers.get(String(id || ''));
    return marker ? marker.__thon09HouseholdRow || null : null;
  };

  window.thon09GisFocusHouseholdMarker = function (row) {
    const m = map();
    if (!m || !row) return false;
    const id = normalizeHouseholdId(row);
    let marker = id ? state.markers.get(id) : null;
    if (!marker && row.latitude != null && row.longitude != null) {
      const normalized = Object.assign({}, row, { head_citizen_name: row.head_citizen_name || row.head_name || '' });
      marker = L.marker([row.latitude, row.longitude], { icon: markerIcon(true), title: normalized.head_citizen_name || normalized.household_code });
      marker.__thon09HouseholdRow = normalized;
      bindHouseholdPopup(marker, normalized);
      if (!state.layer) state.layer = createHouseholdMarkerLayer().addTo(m);
      marker.addTo(state.layer);
      state.markers.set(id, marker);
    }
    if (!marker) return false;
    state.activeMarkerId = id;
    activateMarker(marker, marker.__thon09HouseholdRow || row || { id });
    m.setView(marker.getLatLng(), Math.max(m.getZoom(), 17), { animate: true });
    closeOpenHouseholdPopup(id);
    state.openPopupId = id;
    marker.openPopup();
    return true;
  };

  window.thon09GisOpenHousehold = function (id) { openHouseholdTab(id, 'files'); };
  window.thon09GisOpenHouseholdGallery = function (id) { openHouseholdTab(id, 'gallery'); };
  window.thon09GisDirectionsUrl = function (row) { return googleDirectionsUrl(normalizeHouseholdRow(row)); };
  window.thon09GisOpenDirectionsForRow = function (row) { openGoogleDirections(normalizeHouseholdRow(row)); };
  if (typeof window.thon09GisRouteToHousehold !== 'function') {
    window.thon09GisRouteToHousehold = function (id) { openGoogleDirections(popupRowById(id)); };
    window.thon09GisRouteToHousehold.__thon09Fallback = true;
  }
  window.thon09GisEditHousehold = function (id) {
    if (typeof window.openHouseholdForm === 'function') window.openHouseholdForm(id);
  };
  window.thon09GisRelocateHousehold = function (id, triggerButton) { useGps(id, triggerButton); };
  window.thon09LoadGisHouseholdMarkers = loadHouseholdMarkers;

  function start() {
    ensureLocationFields();
    wrapHouseholdForm();
    bindLocationButtons();
    bindPopupDelegation();
    bindGisMapHooks();
    if (isAuthenticated()) setTimeout(() => loadHouseholdMarkers(undefined, { background: true }), 1200);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
