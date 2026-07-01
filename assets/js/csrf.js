(() => {
  App.csrfToken = localStorage.getItem('thon09_csrf') || App.csrfToken || '';

  window.api = async function secureApi(url, options = {}) {
    setLoading(true);
    try {
      const method = String(options.method || 'GET').toUpperCase();
      const headers = { Accept: 'application/json' };
      const isFormData = options.body instanceof FormData;

      if (options.body && !isFormData) {
        headers['Content-Type'] = 'application/json';
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
        clearClientSession();
        showLogin();
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

/* GIS stability patch v3 - toolbar, validation and guarded loading */
(() => {
  const state = { map: null, layerGroup: null, drawLayer: null, activeTool: 'select', undo: [], redo: [], currentPolygon: [], loadingAreas: false, areasBlocked: false, lastLoadError: '', autoLoadStarted: false };
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const number = value => Number(value || 0).toLocaleString('vi-VN');
  const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));

  function validPolygon(points = state.currentPolygon) {
    return Array.isArray(points) && points.length >= 3 && points.every(point => Number.isFinite(Number(point.lat)) && Number.isFinite(Number(point.lng)));
  }

  function updateSaveState() {
    const save = $('#gisSaveBtn');
    if (!save) return;
    const hasName = ($('#gisAreaName')?.value || '').trim().length > 0;
    const hasCode = ($('#gisAreaCode')?.value || '').trim().length > 0;
    save.disabled = !(hasName && hasCode && validPolygon());
  }

  function installToolbar() {
    const actions = $('.gis-actions');
    if (!actions || actions.dataset.gisToolbarReady === '1') return;
    actions.dataset.gisToolbarReady = '1';
    actions.innerHTML = `
      <div class="gis-toolbox" role="toolbar" aria-label="Công cụ bản đồ">
        <button class="btn btn-outline-secondary gis-tool active" data-gis-tool="select" type="button" title="Chọn"><i class="fa-solid fa-arrow-pointer"></i><span>Chọn</span></button>
        <button class="btn btn-outline-secondary gis-tool" data-gis-tool="polygon" type="button" title="Vẽ Polygon"><i class="fa-solid fa-draw-polygon"></i><span>Polygon</span></button>
        <button class="btn btn-outline-secondary gis-tool" data-gis-tool="line" type="button" title="Vẽ đường"><i class="fa-solid fa-slash"></i><span>Đường</span></button>
        <button class="btn btn-outline-secondary gis-tool" data-gis-tool="point" type="button" title="Vẽ điểm"><i class="fa-solid fa-location-dot"></i><span>Điểm</span></button>
        <button class="btn btn-outline-secondary gis-tool" data-gis-tool="pan" type="button" title="Di chuyển"><i class="fa-solid fa-hand"></i><span>Di chuyển</span></button>
        <button class="btn btn-outline-secondary gis-tool" data-gis-tool="measure-distance" type="button" title="Đo khoảng cách"><i class="fa-solid fa-ruler"></i><span>Khoảng cách</span></button>
        <button class="btn btn-outline-secondary gis-tool" data-gis-tool="measure-area" type="button" title="Đo diện tích"><i class="fa-solid fa-vector-square"></i><span>Diện tích</span></button>
        <button class="btn btn-outline-secondary gis-tool" data-gis-tool="undo" type="button" title="Hoàn tác"><i class="fa-solid fa-rotate-left"></i><span>Undo</span></button>
        <button class="btn btn-outline-secondary gis-tool" data-gis-tool="redo" type="button" title="Làm lại"><i class="fa-solid fa-rotate-right"></i><span>Redo</span></button>
      </div>
      <div class="gis-actions-row"><button id="gisSaveBtn" class="btn btn-primary" type="button" disabled><i class="fa-solid fa-floppy-disk"></i> Lưu khu vực</button><button id="gisPdfBtn" class="btn btn-outline-danger" type="button"><i class="fa-solid fa-file-pdf"></i> Xuất PDF</button></div>`;
    actions.addEventListener('click', event => {
      const button = event.target.closest('[data-gis-tool]');
      if (!button) return;
      const tool = button.dataset.gisTool;
      if (tool === 'undo') return undoPolygon();
      if (tool === 'redo') return redoPolygon();
      state.activeTool = tool;
      $$('.gis-tool', actions).forEach(item => item.classList.toggle('active', item === button));
      setMapStatus(toolLabel(tool));
    });
    $('#gisSaveBtn')?.addEventListener('click', saveArea);
    $('#gisPdfBtn')?.addEventListener('click', () => window.open('/api/gis/export-pdf', '_blank'));
  }

  function toolLabel(tool) {
    return ({select:'Chế độ chọn khu vực', polygon:'Bấm trên bản đồ để vẽ Polygon', line:'Bấm để đo/vẽ đường', point:'Bấm để đặt điểm', pan:'Di chuyển bản đồ', 'measure-distance':'Đo khoảng cách', 'measure-area':'Đo diện tích'})[tool] || 'Sẵn sàng';
  }

  function setMapStatus(text) {
    const status = $('#gisMapStatus');
    if (status) status.textContent = text;
  }

  function pushUndo() {
    state.undo.push(state.currentPolygon.map(point => ({...point})));
    state.redo = [];
  }

  function undoPolygon() {
    if (!state.undo.length) return;
    state.redo.push(state.currentPolygon.map(point => ({...point})));
    state.currentPolygon = state.undo.pop() || [];
    redrawDraft();
  }

  function redoPolygon() {
    if (!state.redo.length) return;
    state.undo.push(state.currentPolygon.map(point => ({...point})));
    state.currentPolygon = state.redo.pop() || [];
    redrawDraft();
  }

  function redrawDraft() {
    if (!state.map || !window.L) return;
    if (state.drawLayer) state.drawLayer.remove();
    if (validPolygon()) {
      state.drawLayer = L.polygon(state.currentPolygon, {color: $('#gisAreaColor')?.value || '#0f8a4b', fillOpacity: 0.22}).addTo(state.map);
      state.map.fitBounds(state.drawLayer.getBounds(), {padding: [20, 20]});
    } else if (state.currentPolygon.length > 1) {
      state.drawLayer = L.polyline(state.currentPolygon, {color: '#0f8a4b'}).addTo(state.map);
    }
    updateSaveState();
  }

  async function loadAreas(options = {}) {
    const force = Boolean(options.force);
    if (state.loadingAreas) return null;
    if (state.areasBlocked && !force) throw new Error(state.lastLoadError || 'GIS đang tạm dừng tải lại do lỗi trước đó');

    state.loadingAreas = true;
    try {
      const data = await api('/api/gis/areas');
      state.areasBlocked = false;
      state.lastLoadError = '';
      renderSummary(data.summary || {}, data.unassigned || {});
      renderAreaList(data.areas || []);
      renderMapAreas(data.areas || []);
      return data;
    } catch (error) {
      state.areasBlocked = true;
      state.lastLoadError = error.message || 'Không tải được GIS';
      throw error;
    } finally {
      state.loadingAreas = false;
    }
  }

  function showGisLoadError(error) {
    const message = error?.message || state.lastLoadError || 'Không tải được GIS';
    setMapStatus('Không tải được bản đồ. Kiểm tra cấu hình dữ liệu.');
    showToast('Không tải được GIS: ' + message, 'danger');
  }

  function renderSummary(summary, unassigned) {
    const box = $('#gisSummaryCards');
    if (!box) return;
    const cards = [
      ['Khu vực', summary.areas], ['Số hộ', summary.households], ['Nhân khẩu', summary.citizens], ['Chưa gán', unassigned.households],
      ['Đã định vị GPS', summary.located], ['Chưa định vị', summary.unlocated], ['Diện tích khu vực', `${number(summary.area_m2)} m²`], ['Mật độ dân cư', `${number(summary.density)} người/km²`],
      ['Hộ nghèo', summary.poor_households], ['Hộ cận nghèo', summary.near_poor_households], ['Tạm trú', summary.temporary], ['Tạm vắng', summary.away]
    ];
    box.innerHTML = cards.map(([label, value]) => `<div class="gis-summary-card"><span>${escape(label)}</span><strong>${escape(value ?? 0)}</strong></div>`).join('');
  }

  function renderAreaList(areas) {
    const list = $('#gisAreaList');
    if (!list) return;
    list.innerHTML = areas.length ? areas.map(area => `<button class="gis-area-item" type="button" data-gis-area-id="${area.id}"><span><b>${escape(area.name)}</b><small>${escape(area.area_code)}</small></span><strong>${number(area.stats?.households)} hộ</strong></button>`).join('') : '<div class="text-muted small">Chưa có khu vực bản đồ.</div>';
    list.querySelectorAll('[data-gis-area-id]').forEach(button => button.addEventListener('click', () => focusArea(areas.find(item => String(item.id) === button.dataset.gisAreaId))));
  }

  function renderMapAreas(areas) {
    if (!window.L || !$('#gisMap')) return;
    if (!state.map) {
      state.map = L.map('gisMap', {preferCanvas: true}).setView([20.257, 105.975], 14);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 20, attribution: '&copy; OpenStreetMap'}).addTo(state.map);
      state.map.on('click', event => {
        if (!['polygon','line','point','measure-distance','measure-area'].includes(state.activeTool)) return;
        pushUndo();
        state.currentPolygon.push({lat: event.latlng.lat, lng: event.latlng.lng});
        redrawDraft();
      });
    }
    if (!state.layerGroup) state.layerGroup = L.layerGroup().addTo(state.map);
    state.layerGroup.clearLayers();
    areas.forEach(area => {
      const points = area.polygon || area.geometry || [];
      if (!validPolygon(points)) return;
      const layer = L.polygon(points, {color: area.color || '#0f8a4b', fillOpacity: 0.2, weight: 2}).addTo(state.layerGroup);
      const label = `<b>${escape(area.name)}</b><br>${escape(area.area_code)}<br>${number(area.stats?.households)} hộ - ${number(area.stats?.citizens)} nhân khẩu<br>Diện tích: ${number(area.stats?.area_m2)} m²`;
      layer.bindTooltip(area.name, {permanent: true, direction: 'center', className: 'gis-map-label'});
      layer.bindPopup(label);
      layer.on('click', () => focusArea(area));
    });
  }

  function focusArea(area) {
    if (!area) return;
    $('#gisAreaId') && ($('#gisAreaId').value = area.id || '');
    $('#gisAreaName') && ($('#gisAreaName').value = area.name || '');
    $('#gisAreaCode') && ($('#gisAreaCode').value = area.area_code || '');
    $('#gisAreaColor') && ($('#gisAreaColor').value = area.color || '#0f8a4b');
    $('#gisAreaNote') && ($('#gisAreaNote').value = area.note || '');
    state.currentPolygon = (area.polygon || area.geometry || []).map(point => ({lat: Number(point.lat), lng: Number(point.lng)}));
    redrawDraft();
  }

  async function saveArea() {
    if (!validPolygon()) return updateSaveState();
    const id = ($('#gisAreaId')?.value || '').trim();
    const payload = {
      name: ($('#gisAreaName')?.value || '').trim(),
      area_code: ($('#gisAreaCode')?.value || '').trim(),
      color: ($('#gisAreaColor')?.value || '#0f8a4b').trim(),
      note: ($('#gisAreaNote')?.value || '').trim(),
      polygon: state.currentPolygon
    };
    if (!payload.name || !payload.area_code) return updateSaveState();
    const url = id ? `/api/gis/areas/${encodeURIComponent(id)}` : '/api/gis/areas';
    const method = id ? 'PUT' : 'POST';
    await api(url, {method, body: payload});
    showToast('Đã lưu khu vực bản đồ', 'success');
    clearForm();
    await loadAreas({force: true});
    if (typeof window.loadDashboard === 'function') window.loadDashboard().catch(() => {});
  }

  function clearForm() {
    ['gisAreaId','gisAreaName','gisAreaCode','gisAreaNote'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    state.currentPolygon = [];
    if (state.drawLayer) state.drawLayer.remove();
    state.drawLayer = null;
    updateSaveState();
  }

  function bind() {
    installToolbar();
    ['gisAreaName','gisAreaCode','gisAreaColor','gisAreaNote'].forEach(id => document.getElementById(id)?.addEventListener('input', updateSaveState));
    $('#gisRefreshBtn')?.addEventListener('click', () => {
      state.areasBlocked = false;
      loadAreas({force: true}).catch(showGisLoadError);
    });
    const oldDraw = $('#gisDrawBtn');
    if (oldDraw) oldDraw.addEventListener('click', () => { state.activeTool = 'polygon'; setMapStatus(toolLabel('polygon')); });
    const screen = $('#gisScreen');
    if (screen && !screen.dataset.gisObserverReady) {
      screen.dataset.gisObserverReady = '1';
      new MutationObserver(() => {
        if (!screen.classList.contains('active') || state.autoLoadStarted || state.areasBlocked) return;
        state.autoLoadStarted = true;
        setTimeout(() => loadAreas().catch(showGisLoadError), 80);
      }).observe(screen, {attributes: true, attributeFilter: ['class']});
    }
    if (screen?.classList.contains('active') && !state.autoLoadStarted) {
      state.autoLoadStarted = true;
      loadAreas().catch(showGisLoadError);
    }
    updateSaveState();
  }

  window.loadGisMap = () => loadAreas({force: true});
  window.clearGisForm = clearForm;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind); else bind();
})();
