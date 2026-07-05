(() => {
  App.csrfToken = localStorage.getItem('thon09_csrf') || App.csrfToken || '';
  const AUTH_REQUIRED_MESSAGE = 'Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại';

  function redirectToLoginOnAuthFailure() {
    if (window.__thon09SessionExpired) return;
    window.__thon09SessionExpired = true;
    if (typeof clearClientSession === 'function') {
      clearClientSession();
    } else {
      App.token = '';
      App.user = null;
      App.csrfToken = '';
      localStorage.removeItem('thon09_token');
      localStorage.removeItem('thon09_user');
      localStorage.removeItem('thon09_csrf');
    }
    if (typeof showLogin === 'function') showLogin();
  }

  window.api = async function secureApi(url, options = {}) {
    setLoading(true);
    try {
      const method = String(options.method || 'GET').toUpperCase();
      const headers = { Accept: 'application/json' };
      const isFormData = options.body instanceof FormData;

      if (options.body && !isFormData) {
        headers['Content-Type'] = 'application/json';
      }
      if (!options.public && !App.token) {
        redirectToLoginOnAuthFailure();
        throw new Error(AUTH_REQUIRED_MESSAGE);
      }
      if (App.token && !options.public) {
        headers.Authorization = `Bearer ${App.token}`;
      }
      if (!options.public && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
        App.csrfToken = App.csrfToken || localStorage.getItem('thon09_csrf') || '';
        if (!App.csrfToken) {
          throw new Error('Phiên đăng nhập thiếu CSRF token, vui lòng đăng nhập lại');
        }
        headers['X-CSRF-Token'] = App.csrfToken;
      }

      const response = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: options.body ? (isFormData ? options.body : JSON.stringify(options.body)) : undefined,
      });
      const payload = await response.json().catch(() => null);

      if (payload?.data?.csrfToken) {
        App.csrfToken = payload.data.csrfToken;
        localStorage.setItem('thon09_csrf', App.csrfToken);
      }
      if (response.status === 401 && !options.public && !String(url).includes('/api/auth/logout')) {
        redirectToLoginOnAuthFailure();
        throw new Error(AUTH_REQUIRED_MESSAGE);
      }
      if (!response.ok || !payload?.ok) {
        throw new Error(payload?.error?.message || 'Không nhận được phản hồi từ hệ thống');
      }
      return payload.data;
    } finally {
      setLoading(false);
    }
  };

  function clearClientSession() {
    App.token = '';
    App.user = null;
    App.csrfToken = '';
    localStorage.removeItem('thon09_token');
    localStorage.removeItem('thon09_user');
    localStorage.removeItem('thon09_csrf');
  }

  window.clearClientSession = clearClientSession;
})();

/* Sprint 14 GIS module - Leaflet.Draw, modern toolbar, guarded events */
(() => {
  const CENTER = [20.257, 105.975];
  const PRIMARY = '#2563eb';
  const state = {
    map: null,
    areaLayer: null,
    draftLayer: null,
    measureLayer: null,
    activeHandler: null,
    activeTool: 'select',
    areas: [],
    undo: [],
    redo: [],
    loading: false,
    bound: false,
  };

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
  const fmt = value => Number(value || 0).toLocaleString('vi-VN');
  const show = (message, type = 'success') => typeof showToast === 'function' ? showToast(message, type) : console[type === 'danger' ? 'error' : 'log'](message);

  function injectStyle() {
    if ($('#thon09-gis-sprint14-style')) return;
    const style = document.createElement('style');
    style.id = 'thon09-gis-sprint14-style';
    style.textContent = `
      .gis-screen{padding:18px;background:#f5f8fb}.gis-layout{display:grid;grid-template-columns:340px minmax(0,1fr);gap:16px;min-height:calc(100vh - 108px)}
      .gis-panel,.gis-map-card{border:1px solid #dbe5ef!important;border-radius:18px!important;background:#fff!important;box-shadow:0 12px 32px rgba(15,23,42,.08)!important}.gis-panel{padding:16px!important;gap:14px;max-height:calc(100vh - 128px);overflow:auto}.gis-panel-head h3{color:#0f3768!important;font-size:21px!important;font-weight:800!important}.gis-panel-head p{font-size:13px;color:#64748b;line-height:1.45}.gis-area-form{border:1px solid #e4edf6!important;background:#f8fbff!important;border-radius:16px!important;padding:14px!important}.gis-area-form label{font-size:12px;font-weight:800;color:#475569}.gis-area-form .form-control{border-radius:12px;min-height:42px}.gis-actions{display:grid;gap:12px}.gis-toolbox{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;padding:10px;border:1px solid #dbeafe;border-radius:16px;background:#f8fbff}.gis-tool{min-height:52px;border-radius:13px!important;font-size:11px;font-weight:800;display:flex;flex-direction:column;gap:4px;align-items:center;justify-content:center;border-color:#cbd5e1!important;color:#334155!important;background:#fff!important}.gis-tool i{font-size:15px;color:${PRIMARY}}.gis-tool.active,.gis-tool:hover{background:${PRIMARY}!important;border-color:${PRIMARY}!important;color:#fff!important;box-shadow:0 10px 22px rgba(37,99,235,.22)}.gis-tool.active i,.gis-tool:hover i{color:#fff}.gis-actions-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}.gis-actions-row .btn{min-height:44px;border-radius:13px;font-weight:800}.gis-map-card{padding:14px!important;position:relative}.gis-map-toolbar{display:flex;align-items:center;gap:12px;margin-bottom:12px}.gis-search-wrap{flex:1;position:relative}.gis-search-wrap input{padding-left:40px;padding-right:40px;border-radius:999px;min-height:48px}.gis-search-wrap i{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#64748b;z-index:2}.gis-search-clear{position:absolute;right:10px;top:50%;transform:translateY(-50%);border:0;background:#eef2f7;color:#475569;width:28px;height:28px;border-radius:50%;display:none}.gis-search-wrap.has-value .gis-search-clear{display:inline-flex;align-items:center;justify-content:center}.gis-status-pill{background:#e8f1ff!important;color:#1d4ed8!important;border-radius:999px;font-weight:800;padding:10px 13px}.gis-map{height:calc(100vh - 188px);min-height:620px;border-radius:18px!important;border:1px solid #dbe5ef!important;background:#eaf2fb!important}.gis-summary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.gis-summary-card{border:1px solid #e2e8f0;border-radius:14px;background:linear-gradient(180deg,#fff,#f8fbff);padding:11px}.gis-summary-card span{display:block;font-size:11px;color:#64748b;font-weight:800}.gis-summary-card strong{display:block;color:#0f3768;font-size:18px;margin-top:3px}.gis-area-list{display:grid;gap:8px}.gis-area-item{border:1px solid #e2e8f0;border-radius:14px;background:#fff;padding:10px 12px;display:flex;align-items:center;justify-content:space-between;text-align:left;gap:10px}.gis-area-item:hover{border-color:${PRIMARY};box-shadow:0 8px 18px rgba(37,99,235,.14)}.gis-area-item b{display:block;color:#0f172a;font-size:13px}.gis-area-item small{display:block;color:#64748b}.gis-area-item strong{color:#1d4ed8;font-size:12px}.gis-map-label{border:0!important;border-radius:12px!important;padding:7px 10px!important;box-shadow:0 8px 20px rgba(15,23,42,.16)!important;color:#0f3768!important;font-size:12px;text-align:center}.gis-popup h4{margin:0 0 5px;color:#0f3768;font-size:16px}.gis-popup p{margin:0 0 8px;color:#475569}.gis-popup-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;margin-bottom:10px}.gis-popup-stats span{border-radius:11px;background:#f1f5f9;padding:8px;font-size:12px}.gis-popup-stats b{color:#1d4ed8}.gis-popup-actions{display:flex;flex-wrap:wrap;gap:7px}.gis-inline-suggestions{position:absolute;z-index:1000;top:54px;left:0;right:0;background:#fff;border:1px solid #dbe5ef;border-radius:14px;box-shadow:0 18px 36px rgba(15,23,42,.14);padding:6px;display:none}.gis-inline-suggestions.show{display:grid}.gis-inline-suggestions button{border:0;background:#fff;text-align:left;border-radius:10px;padding:8px 10px;color:#334155}.gis-inline-suggestions button:hover{background:#eef6ff;color:#1d4ed8}
      @media(max-width:1100px){.gis-layout{grid-template-columns:1fr}.gis-panel{order:2;max-height:none}.gis-map-card{order:1}.gis-map{height:58vh;min-height:430px}}
      @media(max-width:560px){.gis-screen{padding:12px}.gis-toolbox{grid-template-columns:repeat(2,minmax(0,1fr))}.gis-actions-row{grid-template-columns:1fr}.gis-map-toolbar{flex-direction:column;align-items:stretch}.gis-status-pill{text-align:center}.gis-map{height:54vh;min-height:340px}.gis-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    `;
    document.head.appendChild(style);
  }

  function hasLeaflet() {
    if (!window.L) {
      setStatus('Không tải được thư viện bản đồ');
      show('Không tải được Leaflet/OpenStreetMap. Vui lòng kiểm tra kết nối mạng.', 'danger');
      return false;
    }
    return true;
  }

  function setStatus(text) {
    const status = $('#gisMapStatus');
    if (status) status.textContent = text;
  }

  function ensureUi() {
    injectStyle();
    const actions = $('.gis-actions');
    if (actions && actions.dataset.sprint14 !== '1') {
      actions.dataset.sprint14 = '1';
      actions.innerHTML = `
        <div class="gis-toolbox" role="toolbar" aria-label="Công cụ bản đồ">
          ${toolButton('select','fa-arrow-pointer','Chọn')}${toolButton('polygon','fa-draw-polygon','Polygon')}${toolButton('line','fa-slash','Đường')}
          ${toolButton('point','fa-location-dot','Điểm')}${toolButton('pan','fa-hand','Di chuyển')}${toolButton('measure-distance','fa-ruler','Khoảng cách')}
          ${toolButton('measure-area','fa-vector-square','Diện tích')}${toolButton('edit','fa-pen-to-square','Chỉnh sửa')}${toolButton('delete','fa-trash','Xóa')}
          ${toolButton('undo','fa-rotate-left','Undo')}${toolButton('redo','fa-rotate-right','Redo')}
        </div>
        <div class="gis-actions-row">
          <button id="gisSaveBtn" class="btn btn-primary" type="button" disabled><i class="fa-solid fa-floppy-disk"></i> Lưu khu vực</button>
          <button id="gisPdfBtn" class="btn btn-outline-danger" type="button"><i class="fa-solid fa-file-pdf"></i> Xuất PDF</button>
        </div>`;
    }
    const searchWrap = $('.gis-search-wrap');
    if (searchWrap && !$('.gis-search-clear', searchWrap)) {
      const clear = document.createElement('button');
      clear.className = 'gis-search-clear';
      clear.type = 'button';
      clear.title = 'Xóa tìm kiếm';
      clear.innerHTML = '<i class="fa-solid fa-xmark"></i>';
      searchWrap.appendChild(clear);
      const suggest = document.createElement('div');
      suggest.className = 'gis-inline-suggestions';
      suggest.id = 'gisSearchSuggestions';
      searchWrap.appendChild(suggest);
    }
    if ($('#gisAreaColor') && !$('#gisAreaColor').value) $('#gisAreaColor').value = PRIMARY;
    updateSaveState();
  }

  function toolButton(tool, icon, label) {
    const active = tool === 'select' ? ' active' : '';
    return `<button class="gis-tool${active}" type="button" data-gis-tool="${tool}" title="${label}"><i class="fa-solid ${icon}"></i><span>${label}</span></button>`;
  }

  function ensureMap() {
    if (!hasLeaflet()) return null;
    const appGis = window.App.gis = window.App.gis || {};
    if (state.map) return state.map;
    if (appGis.map) {
      state.map = appGis.map;
      try { if (appGis.drawControl) state.map.removeControl(appGis.drawControl); } catch (error) {}
    } else {
      state.map = L.map('gisMap', { preferCanvas: true, zoomControl: true }).setView(CENTER, 14);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20, attribution: '&copy; OpenStreetMap' }).addTo(state.map);
      appGis.map = state.map;
    }
    state.areaLayer = appGis.areaLayerGroup || L.featureGroup().addTo(state.map);
    state.measureLayer = appGis.measureLayerGroup || L.featureGroup().addTo(state.map);
    appGis.areaLayerGroup = state.areaLayer;
    appGis.measureLayerGroup = state.measureLayer;
    state.map.off(L.Draw?.Event?.CREATED || 'draw:created');
    state.map.off(L.Draw?.Event?.EDITED || 'draw:edited');
    state.map.on(L.Draw.Event.CREATED, onDrawCreated);
    state.map.on(L.Draw.Event.EDITED, event => event.layers.eachLayer(layer => setDraftLayer(layer, true)));
    setTimeout(() => state.map.invalidateSize(), 120);
    return state.map;
  }

  function stopActiveHandler() {
    if (state.activeHandler && typeof state.activeHandler.disable === 'function') {
      try { state.activeHandler.disable(); } catch (error) {}
    }
    state.activeHandler = null;
  }

  function activateTool(tool) {
    state.activeTool = tool;
    $$('.gis-tool').forEach(btn => btn.classList.toggle('active', btn.dataset.gisTool === tool));
    stopActiveHandler();
    if (!ensureMap()) return;
    if (tool === 'select' || tool === 'pan') return setStatus(tool === 'pan' ? 'Di chuyển bản đồ' : 'Chế độ chọn khu vực');
    if (tool === 'undo') return undoDraft();
    if (tool === 'redo') return redoDraft();
    if (tool === 'delete') return deleteDraft();
    if (tool === 'edit') return editDraft();
    if (!window.L.Draw) return show('Chưa tải được công cụ vẽ Leaflet.Draw', 'warning');
    const color = $('#gisAreaColor')?.value || PRIMARY;
    const options = { shapeOptions: { color, fillColor: color, fillOpacity: 0.18, weight: 2.4 } };
    if (tool === 'polygon') {
      state.activeHandler = new L.Draw.Polygon(state.map, { ...options, allowIntersection: false, showArea: true, guidelineDistance: 12 });
      setStatus('Click từng đỉnh để vẽ Polygon. Double click để kết thúc.');
    } else if (tool === 'line' || tool === 'measure-distance') {
      state.activeHandler = new L.Draw.Polyline(state.map, { shapeOptions: { color: tool === 'measure-distance' ? PRIMARY : '#64748b', weight: 3 } });
      setStatus(tool === 'measure-distance' ? 'Click từng điểm để đo khoảng cách.' : 'Click từng điểm để vẽ đường.');
    } else if (tool === 'point') {
      state.activeHandler = new L.Draw.Marker(state.map);
      setStatus('Click lên bản đồ để đặt điểm.');
    } else if (tool === 'measure-area') {
      state.activeHandler = new L.Draw.Polygon(state.map, { allowIntersection: false, showArea: true, shapeOptions: { color: '#f59e0b', fillColor: '#f59e0b', fillOpacity: 0.14, weight: 2.4 } });
      setStatus('Click từng đỉnh để đo diện tích.');
    }
    if (state.activeHandler) state.activeHandler.enable();
  }

  function onDrawCreated(event) {
    stopActiveHandler();
    const layer = event.layer;
    if (state.activeTool === 'polygon') {
      setDraftLayer(layer, true);
      setStatus('Polygon hợp lệ. Nhập tên, mã khu vực rồi bấm Lưu.');
      activateTool('select');
      return;
    }
    renderMeasurement(layer, state.activeTool);
    activateTool('select');
  }

  function setDraftLayer(layer, saveHistory = false) {
    if (!state.map) return;
    if (state.draftLayer) state.draftLayer.remove();
    state.draftLayer = layer;
    if (!state.map.hasLayer(layer)) layer.addTo(state.map);
    const color = $('#gisAreaColor')?.value || PRIMARY;
    if (layer.setStyle) layer.setStyle({ color, fillColor: color, fillOpacity: 0.2, weight: 2.6 });
    if (saveHistory) pushHistory(getDraftPoints());
    updateSaveState();
  }

  function getDraftPoints() {
    if (!state.draftLayer || typeof state.draftLayer.getLatLngs !== 'function') return [];
    const raw = state.draftLayer.getLatLngs();
    const points = Array.isArray(raw?.[0]) ? raw[0] : raw;
    return (points || []).map(point => ({ lat: Number(point.lat.toFixed(7)), lng: Number(point.lng.toFixed(7)) }));
  }

  function validPolygon(points = getDraftPoints()) {
    return Array.isArray(points) && points.length >= 3 && points.every(point => Number.isFinite(point.lat) && Number.isFinite(point.lng));
  }

  function pushHistory(points) {
    if (!validPolygon(points)) return;
    state.undo.push(points.map(point => ({ ...point })));
    if (state.undo.length > 30) state.undo.shift();
    state.redo = [];
  }

  function restoreDraft(points) {
    if (!ensureMap() || !validPolygon(points)) return;
    const color = $('#gisAreaColor')?.value || PRIMARY;
    setDraftLayer(L.polygon(points, { color, fillColor: color, fillOpacity: 0.2, weight: 2.6 }), false);
    state.map.fitBounds(state.draftLayer.getBounds(), { padding: [24, 24], maxZoom: 18 });
  }

  function undoDraft() {
    if (!state.undo.length) return setStatus('Không còn thao tác để hoàn tác.');
    const current = getDraftPoints();
    if (validPolygon(current)) state.redo.push(current);
    const previous = state.undo.pop();
    if (previous) restoreDraft(previous);
  }

  function redoDraft() {
    if (!state.redo.length) return setStatus('Không còn thao tác để làm lại.');
    const current = getDraftPoints();
    if (validPolygon(current)) state.undo.push(current);
    restoreDraft(state.redo.pop());
  }

  function deleteDraft() {
    if (!state.draftLayer) return setStatus('Chưa chọn khu vực để xóa trên bản đồ.');
    if (!confirm('Xóa bản vẽ đang chọn? Dữ liệu đã lưu chỉ bị xóa khi bấm Xóa trong popup khu vực.')) return;
    state.draftLayer.remove();
    state.draftLayer = null;
    updateSaveState();
  }

  function editDraft() {
    if (!state.draftLayer || !state.draftLayer.editing) return show('Vui lòng chọn hoặc vẽ Polygon trước khi chỉnh sửa', 'warning');
    state.draftLayer.editing.enable();
    setStatus('Đang chỉnh sửa Polygon. Kéo các đỉnh để điều chỉnh rồi bấm Lưu.');
  }

  function renderMeasurement(layer, tool) {
    if (!state.measureLayer || !layer) return;
    layer.addTo(state.measureLayer);
    if (tool === 'point' && layer.getLatLng) {
      const p = layer.getLatLng();
      layer.bindPopup(`Điểm: ${p.lat.toFixed(6)}, ${p.lng.toFixed(6)}`).openPopup();
      return;
    }
    const points = (Array.isArray(layer.getLatLngs?.()[0]) ? layer.getLatLngs()[0] : layer.getLatLngs?.()) || [];
    const text = tool === 'measure-area' ? `Diện tích: ${formatArea(calcArea(points))}` : `${tool === 'measure-distance' ? 'Khoảng cách' : 'Độ dài'}: ${formatDistance(calcDistance(points))}`;
    layer.bindTooltip(text, { permanent: true, direction: 'center' }).openTooltip();
  }

  function calcDistance(points) {
    let total = 0;
    for (let i = 1; i < points.length; i++) total += L.latLng(points[i - 1]).distanceTo(L.latLng(points[i]));
    return total;
  }

  function calcArea(points) {
    if (!points || points.length < 3) return 0;
    const radius = 6378137;
    let area = 0;
    for (let i = 0; i < points.length; i++) {
      const p1 = points[i], p2 = points[(i + 1) % points.length];
      area += (toRad(p2.lng) - toRad(p1.lng)) * (2 + Math.sin(toRad(p1.lat)) + Math.sin(toRad(p2.lat)));
    }
    return Math.abs(area * radius * radius / 2);
  }

  function toRad(value) { return Number(value || 0) * Math.PI / 180; }
  function formatDistance(value) { return value >= 1000 ? `${(value / 1000).toFixed(2)} km` : `${Math.round(value)} m`; }
  function formatArea(value) { return value >= 10000 ? `${(value / 10000).toFixed(2)} ha` : `${Math.round(value)} m²`; }

  function updateSaveState() {
    const save = $('#gisSaveBtn');
    if (!save) return;
    const ready = Boolean(($('#gisAreaName')?.value || '').trim() && ($('#gisAreaCode')?.value || '').trim() && validPolygon());
    save.disabled = !ready;
  }

  async function loadAreas(options = {}) {
    if (state.loading) return null;
    if (typeof window.ensureGisAssets === 'function') await window.ensureGisAssets();
    if (!window.App?.token) {
      setStatus('Vui lòng đăng nhập lại để tải bản đồ');
      return null;
    }
    ensureUi();
    if (!ensureMap()) return null;
    state.loading = true;
    setStatus('Đang tải bản đồ...');
    try {
      const data = await api('/api/gis/areas');
      state.areas = data.areas || [];
      window.App.gis = window.App.gis || {};
      window.App.gis.areas = state.areas;
      renderSummary(data.summary || {}, data.unassigned || {}, state.areas.length);
      renderAreaList(state.areas);
      renderMapAreas(state.areas);
      setStatus(`${state.areas.length} khu vực - ${fmt(data.summary?.households)} hộ`);
      return data;
    } catch (error) {
      setStatus('Không tải được dữ liệu GIS');
      show('Không tải được GIS: ' + (error.message || 'Lỗi không xác định'), 'danger');
      throw error;
    } finally {
      state.loading = false;
      setTimeout(() => state.map?.invalidateSize(), 120);
    }
  }

  function renderSummary(summary, unassigned, areaCount) {
    const host = $('#gisSummaryCards');
    if (!host) return;
    const cards = [
      ['fa-map-location-dot', 'Khu vực', areaCount || summary.areas || 0],
      ['fa-house-chimney', 'Số hộ', summary.households || 0],
      ['fa-users', 'Nhân khẩu', summary.citizens || 0],
      ['fa-triangle-exclamation', 'Chưa gán', unassigned.households || 0],
      ['fa-location-crosshairs', 'Đã định vị GPS', summary.located || 0],
      ['fa-map-pin', 'Chưa định vị', summary.unlocated || 0],
      ['fa-vector-square', 'Diện tích', formatArea(Number(summary.area_m2 || 0))],
      ['fa-chart-simple', 'Mật độ', `${fmt(summary.density || 0)} NK/km²`],
      ['fa-hand-holding-heart', 'Hộ nghèo', summary.poor_households || 0],
      ['fa-scale-balanced', 'Hộ cận nghèo', summary.near_poor_households || 0],
      ['fa-location-dot', 'Tạm trú', summary.temporary || 0],
      ['fa-person-walking-arrow-right', 'Tạm vắng', summary.away || 0],
    ];
    host.innerHTML = cards.map(([icon, label, value]) => `<div class="gis-summary-card"><span><i class="fa-solid ${icon}"></i> ${esc(label)}</span><strong>${esc(value)}</strong></div>`).join('');
  }

  function renderAreaList(areas) {
    const host = $('#gisAreaList');
    if (!host) return;
    const keyword = normalize($('#gisSearch')?.value || '');
    const filtered = areas.filter(area => !keyword || [area.name, area.area_code, area.note].some(value => normalize(value).includes(keyword)));
    host.innerHTML = filtered.length ? filtered.map(area => `
      <button class="gis-area-item" type="button" data-gis-id="${Number(area.id)}">
        <span><b>${esc(area.name)}</b><small>${esc(area.area_code)}</small></span>
        <strong>${fmt(area.stats?.households)} hộ / ${fmt(area.stats?.citizens)} NK</strong>
      </button>`).join('') : '<div class="text-muted small py-2">Chưa có khu vực bản đồ</div>';
    host.querySelectorAll('[data-gis-id]').forEach(button => button.addEventListener('click', () => focusArea(filtered.find(area => String(area.id) === button.dataset.gisId))));
    renderSuggestions(filtered.slice(0, 8));
  }

  function renderSuggestions(areas) {
    const box = $('#gisSearchSuggestions');
    if (!box) return;
    const hasQuery = Boolean(($('#gisSearch')?.value || '').trim());
    box.classList.toggle('show', hasQuery && areas.length > 0);
    box.innerHTML = areas.map(area => `<button type="button" data-gis-suggest="${Number(area.id)}"><b>${esc(area.name)}</b><small class="d-block text-muted">${esc(area.area_code)}</small></button>`).join('');
    box.querySelectorAll('[data-gis-suggest]').forEach(button => button.addEventListener('click', () => {
      const area = state.areas.find(item => String(item.id) === button.dataset.gisSuggest);
      focusArea(area);
      box.classList.remove('show');
    }));
  }

  function renderMapAreas(areas) {
    if (!state.areaLayer) return;
    state.areaLayer.clearLayers();
    const bounds = [];
    areas.forEach(area => {
      const points = normalizePoints(area.polygon || area.geometry || []);
      if (!validPolygon(points)) return;
      const layer = L.polygon(points, { color: area.color || PRIMARY, fillColor: area.color || PRIMARY, fillOpacity: 0.16, weight: 2.4 }).addTo(state.areaLayer);
      layer.bindTooltip(`<strong>${esc(area.name)}</strong><br>${fmt(area.stats?.households)} hộ`, { permanent: true, direction: 'center', className: 'gis-map-label' });
      layer.bindPopup(areaPopup(area));
      layer.on('click', () => focusArea(area, false));
      points.forEach(point => bounds.push([point.lat, point.lng]));
    });
    if (bounds.length) state.map.fitBounds(bounds, { padding: [26, 26], maxZoom: 17 });
  }

  function areaPopup(area) {
    const stats = area.stats || {};
    return `<div class="gis-popup">
      <h4>${esc(area.name)}</h4><p>Mã khu vực: <b>${esc(area.area_code)}</b></p>
      <div class="gis-popup-stats">
        <span><b>${fmt(stats.households)}</b> hộ</span><span><b>${fmt(stats.citizens)}</b> nhân khẩu</span>
        <span><b>${formatArea(Number(stats.area_m2 || area.area_m2 || 0))}</b> diện tích</span><span><b>${fmt(stats.density)}</b> NK/km²</span>
        <span><b>${fmt(stats.temporary)}</b> tạm trú</span><span><b>${fmt(stats.away)}</b> tạm vắng</span>
      </div>
      <div class="gis-popup-actions">
        <button class="btn btn-sm btn-primary" type="button" onclick="window.editGisArea(${Number(area.id)})">Sửa</button>
        <button class="btn btn-sm btn-outline-danger" type="button" onclick="window.deleteGisArea(${Number(area.id)})">Xóa</button>
        <button class="btn btn-sm btn-outline-primary" type="button" onclick="window.focusGisArea('${esc(area.area_code)}')">Zoom</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" onclick="window.filterHouseholdsByGisArea('${esc(area.area_code)}')">Lọc hộ</button>
      </div>
    </div>`;
  }

  function focusArea(area, fit = true) {
    if (!area) return;
    $('#gisAreaId') && ($('#gisAreaId').value = area.id || '');
    $('#gisAreaName') && ($('#gisAreaName').value = area.name || '');
    $('#gisAreaCode') && ($('#gisAreaCode').value = area.area_code || '');
    $('#gisAreaColor') && ($('#gisAreaColor').value = area.color || PRIMARY);
    $('#gisAreaNote') && ($('#gisAreaNote').value = area.note || '');
    const points = normalizePoints(area.polygon || area.geometry || []);
    if (validPolygon(points)) {
      restoreDraft(points);
      if (fit && state.draftLayer) state.map.fitBounds(state.draftLayer.getBounds(), { padding: [28, 28], maxZoom: 18 });
    }
    updateSaveState();
  }

  function normalizePoints(points) {
    return (points || []).map(point => ({ lat: Number(point.lat), lng: Number(point.lng) })).filter(point => Number.isFinite(point.lat) && Number.isFinite(point.lng));
  }

  function normalize(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase();
  }

  async function saveArea() {
    const points = getDraftPoints();
    const payload = {
      name: ($('#gisAreaName')?.value || '').trim(),
      area_code: ($('#gisAreaCode')?.value || '').trim(),
      color: ($('#gisAreaColor')?.value || PRIMARY).trim(),
      note: ($('#gisAreaNote')?.value || '').trim(),
      polygon: points,
    };
    if (!payload.name || !payload.area_code || !validPolygon(points)) {
      updateSaveState();
      return show('Vui lòng nhập tên, mã khu vực và vẽ Polygon hợp lệ', 'warning');
    }
    const id = ($('#gisAreaId')?.value || '').trim();
    const method = id ? 'PUT' : 'POST';
    const url = id ? `/api/gis/areas/${encodeURIComponent(id)}` : '/api/gis/areas';
    const saved = await api(url, { method, body: payload });
    show('Đã lưu khu vực bản đồ');
    clearForm();
    await loadAreas();
    const areaCode = saved?.area_code || payload.area_code;
    focusGisArea(areaCode);
    if (typeof window.loadDashboard === 'function') window.loadDashboard().catch(() => {});
  }

  function clearForm() {
    ['gisAreaId','gisAreaName','gisAreaCode','gisAreaNote'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    if (state.draftLayer) state.draftLayer.remove();
    state.draftLayer = null;
    state.undo = [];
    state.redo = [];
    updateSaveState();
  }

  async function deleteArea(id) {
    if (!id || !confirm('Xóa ranh giới khu vực này? Dữ liệu hộ dân không bị xóa.')) return;
    await api(`/api/gis/areas/${encodeURIComponent(id)}`, { method: 'DELETE' });
    show('Đã xóa ranh giới khu vực');
    clearForm();
    await loadAreas();
  }

  function filterHouseholds(areaCode) {
    if (!window.App?.households) return;
    App.households.search = String(areaCode || '');
    App.households.page = 1;
    const input = $('#householdSearch');
    if (input) input.value = App.households.search;
    if (typeof window.switchScreen === 'function') window.switchScreen('households');
    setTimeout(() => typeof window.loadHouseholds === 'function' && window.loadHouseholds(), 120);
  }

  function exportPdf() {
    if (!App.token) return show('Vui lòng đăng nhập lại để xuất PDF', 'warning');
    fetch('/api/gis/export-pdf', { headers: { Authorization: `Bearer ${App.token}` }, cache: 'no-store' })
      .then(response => {
        if (!response.ok) throw new Error('Không xuất được bản đồ PDF');
        return response.blob();
      })
      .then(blob => {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `ban_do_dia_ban_${Date.now()}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 30000);
      })
      .catch(error => show(error.message || 'Không xuất được bản đồ', 'danger'));
  }

  function bind() {
    if (state.bound) return;
    state.bound = true;
    ensureUi();
    document.addEventListener('click', event => {
      const tool = event.target.closest?.('[data-gis-tool]');
      if (tool) return activateTool(tool.dataset.gisTool);
    });
    ['gisAreaName','gisAreaCode','gisAreaColor'].forEach(id => document.getElementById(id)?.addEventListener('input', updateSaveState));
    $('#gisSaveBtn')?.addEventListener('click', saveArea);
    $('#gisPdfBtn')?.addEventListener('click', exportPdf);
    $('#gisRefreshBtn')?.addEventListener('click', () => loadAreas().catch(error => show(error.message, 'danger')));
    $('#gisSearch')?.addEventListener('input', debounce(() => {
      const wrap = $('.gis-search-wrap');
      if (wrap) wrap.classList.toggle('has-value', Boolean($('#gisSearch').value));
      renderAreaList(state.areas);
    }, 250));
    $('.gis-search-clear')?.addEventListener('click', () => {
      const input = $('#gisSearch');
      if (input) input.value = '';
      $('.gis-search-wrap')?.classList.remove('has-value');
      $('#gisSearchSuggestions')?.classList.remove('show');
      renderAreaList(state.areas);
    });
    const screen = $('#gisScreen');
    if (screen) {
      new MutationObserver(() => {
        if (screen.classList.contains('active')) setTimeout(() => loadAreas().catch(error => show(error.message, 'danger')), 80);
      }).observe(screen, { attributes: true, attributeFilter: ['class'] });
      if (screen.classList.contains('active')) setTimeout(() => loadAreas().catch(error => show(error.message, 'danger')), 80);
    }
  }

  window.loadGisMap = () => loadAreas();
  window.runGisTool = activateTool;
  window.updateGisSaveState = updateSaveState;
  window.clearGisForm = clearForm;
  window.focusGisArea = areaCode => focusArea(state.areas.find(area => String(area.area_code) === String(areaCode)));
  window.editGisArea = id => focusArea(state.areas.find(area => Number(area.id) === Number(id)));
  window.deleteGisArea = deleteArea;
  window.filterHouseholdsByGisArea = filterHouseholds;
  window.exportGisPdf = exportPdf;

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind); else bind();
})();
