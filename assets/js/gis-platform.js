(function () {
  'use strict';

  const layerDefinitions = [
    { key: 'households', label: 'Hộ gia đình', icon: 'house-chimney', color: '#2563eb', existing: 'markerGroup', defaultOn: true },
    { key: 'citizens', label: 'Nhân khẩu', icon: 'users', color: '#0f766e', parentLayer: 'households' },
    { key: 'publicAssets', label: 'Công trình công cộng', icon: 'building-columns', color: '#7c3aed', endpoint: '/api/public-assets/gis', title: row => row.asset_name || row.name || row.asset_code, meta: row => [row.asset_code, row.type_name || row.category, row.area_code].filter(Boolean).join(' - ') },
    { key: 'religiousAssets', label: 'Cơ sở tín ngưỡng, tôn giáo', icon: 'place-of-worship', color: '#9333ea', endpoint: '/api/public-assets/gis', filter: isReligiousAsset, title: row => row.asset_name || row.name || row.asset_code, meta: row => [row.asset_code, row.type_name || row.category, row.area_code].filter(Boolean).join(' - ') },
    { key: 'businessHouseholds', label: 'Hộ sản xuất, kinh doanh', icon: 'store', color: '#ea580c', endpoint: '/api/household-business?page=1&pageSize=1000&located=1', title: row => row.business_name || row.household_code, meta: row => [row.business_type_label, row.sector_label || row.economic_type, row.head_citizen_name].filter(Boolean).join(' - ') },
    { key: 'houses', label: 'Nhà ở & Công trình', icon: 'house-user', color: '#0891b2', endpoint: '/api/houses/gis', title: row => row.house_code || row.house_name || row.household_code, meta: row => [row.house_type, row.condition, row.head_citizen_name].filter(Boolean).join(' - ') },
    { key: 'livestock', label: 'Vật nuôi', icon: 'paw', color: '#16a34a', endpoint: '/api/livestock?page=1&pageSize=1000&located=1', title: row => row.animal_type || row.household_code, meta: row => [row.breed, row.quantity ? String(row.quantity) + ' con' : '', row.household_code].filter(Boolean).join(' - ') },
    { key: 'vehicles', label: 'Xe cộ', icon: 'car-side', color: '#475569', empty: 'Chưa có lớp dữ liệu định vị xe cộ.' },
    { key: 'agriculture', label: 'Sản xuất nông nghiệp', icon: 'seedling', color: '#65a30d', endpoint: '/api/agriculture/gis', title: row => row.parcel_code || row.field_area, meta: row => [row.field_area, row.owner_name, row.current_crop].filter(Boolean).join(' - '), polygon: true },
    { key: 'managementAreas', label: 'Khu vực quản lý', icon: 'draw-polygon', color: '#2e7d32', existing: 'layerGroup', defaultOn: true },
    { key: 'roads', label: 'Đường giao thông', icon: 'road', color: '#64748b', empty: 'Chưa có lớp dữ liệu đường giao thông.' },
    { key: 'adminBoundaries', label: 'Ranh giới hành chính', icon: 'border-all', color: '#0f172a', empty: 'Chưa có lớp dữ liệu ranh giới hành chính.' }
  ];

  const heatmapDefinitions = [
    { key: 'population', label: 'Mật độ dân cư', color: '#dc2626' },
    { key: 'children', label: 'Trẻ em', color: '#f59e0b' },
    { key: 'elderly', label: 'Người cao tuổi', color: '#8b5cf6' },
    { key: 'business', label: 'Hộ kinh doanh', color: '#ea580c' },
    { key: 'livestock', label: 'Vật nuôi', color: '#16a34a' },
    { key: 'vehicles', label: 'Xe cộ', color: '#475569' }
  ];

  const state = {
    installed: false,
    layerGroups: new Map(),
    layerData: new Map(),
    loading: new Set(),
    active: new Set(layerDefinitions.filter(item => item.defaultOn).map(item => item.key)),
    heatmap: new Set(),
    dragBound: new Set(),
    measureMode: '',
    measurePoints: [],
    measureLayers: [],
    searchTimer: null,
    syncTimer: null,
    selectedAreaId: ''
  };

  function $(selector, root) { return (root || document).querySelector(selector); }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
  }
  function toast(message, type) {
    if (typeof window.showToast === 'function') window.showToast(message, type || 'info');
    else console[type === 'danger' ? 'error' : 'log'](message);
  }
  function appGis() { return window.App && window.App.gis ? window.App.gis : null; }
  function map() { return appGis()?.map || null; }
  function canReadGis() {
    const permissions = window.Thon09Platform?.permissions;
    if (permissions?.can) return permissions.can('gis', 'read', window.App?.user);
    return typeof window.thon09CanAccess === 'function' ? window.thon09CanAccess('gis', 'read') : true;
  }
  function canUpdateGis() {
    const permissions = window.Thon09Platform?.permissions;
    if (permissions?.can) return permissions.can('gis', 'update', window.App?.user);
    return typeof window.thon09CanAccess === 'function' ? window.thon09CanAccess('gis', 'update') : true;
  }
  async function request(path, options) {
    const requestOptions = options || { cacheTtl: 30000 };
    if (typeof window.api === 'function') return window.api(path, requestOptions);
    const headers = Object.assign({ Accept: 'application/json' }, requestOptions.headers || {});
    const token = window.App?.token || localStorage.getItem('thon09_token') || '';
    if (token) headers.Authorization = 'Bearer ' + token;
    const fetchOptions = Object.assign({}, requestOptions, { headers });
    if (fetchOptions.body && !headers['Content-Type']) headers['Content-Type'] = 'application/json';
    if (fetchOptions.body && typeof fetchOptions.body !== 'string' && !(typeof FormData !== 'undefined' && fetchOptions.body instanceof FormData)) {
      fetchOptions.body = JSON.stringify(fetchOptions.body);
    }
    const response = await fetch(path, fetchOptions);
    const json = await response.json().catch(() => null);
    if (!response.ok || !json || json.ok === false) throw new Error(json?.error?.message || 'Không tải được dữ liệu GIS.');
    return json.data || json;
  }
  function normalizeText(value) {
    return String(value || '').toLocaleLowerCase('vi-VN').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }
  function isReligiousAsset(row) {
    const text = normalizeText([row.asset_name, row.type_name, row.category, row.note].filter(Boolean).join(' '));
    return /ton giao|tin nguong|dinh|chua|den|mien|nha tho|tu duong/.test(text);
  }
  function coordinates(row) {
    const lat = Number(row?.latitude ?? row?.lat ?? row?.center_lat);
    const lng = Number(row?.longitude ?? row?.lng ?? row?.center_lng);
    return Number.isFinite(lat) && Number.isFinite(lng) ? [lat, lng] : null;
  }
  function collection(data) {
    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.items)) return data.items;
    if (Array.isArray(data?.data)) return data.data;
    if (Array.isArray(data?.features)) return data.features;
    return [];
  }
  function iconFor(def) {
    return window.L.divIcon({
      className: 'gis-v2-marker',
      html: '<span style="--gis-layer-color:' + escapeHtml(def.color) + '"><i class="fa-solid fa-' + escapeHtml(def.icon) + '"></i></span>',
      iconSize: [34, 34],
      iconAnchor: [17, 30],
      popupAnchor: [0, -28]
    });
  }
  function popupHtml(def, row) {
    const title = typeof def.title === 'function' ? def.title(row) : (row.name || row.code || def.label);
    const meta = typeof def.meta === 'function' ? def.meta(row) : '';
    const gps = coordinates(row);
    return '<div class="gis-v2-popup">' +
      '<div class="gis-v2-popup-head"><span style="--gis-layer-color:' + escapeHtml(def.color) + '"><i class="fa-solid fa-' + escapeHtml(def.icon) + '"></i></span><div><h4>' + escapeHtml(title || def.label) + '</h4><p>' + escapeHtml(def.label) + '</p></div></div>' +
      (meta ? '<div class="gis-v2-popup-meta">' + escapeHtml(meta) + '</div>' : '') +
      (gps ? '<a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(gps[0] + ',' + gps[1]) + '"><i class="fa-brands fa-google"></i> Chỉ đường</a>' : '') +
    '</div>';
  }
  function formatNumber(value) {
    return Number(value || 0).toLocaleString('vi-VN');
  }
  function markerRow(marker) {
    return marker?.__thon09HouseholdRow || marker?.__gisPlatformData || null;
  }
  function householdMarkers() {
    const gis = appGis();
    if (!gis?.markerCache) return [];
    return Array.from(gis.markerCache.values()).map(item => item.marker).filter(Boolean);
  }
  function householdMarkerEntries() {
    const gis = appGis();
    if (!gis?.markerCache) return [];
    return Array.from(gis.markerCache.entries()).map(([id, item]) => ({ id, marker: item.marker, row: item.data || markerRow(item.marker) })).filter(item => item.marker);
  }
  function groupFor(def) {
    const gis = appGis();
    if (!gis || !window.L) return null;
    if (def.existing) return gis[def.existing] || null;
    if (!state.layerGroups.has(def.key)) state.layerGroups.set(def.key, L.layerGroup([], { pane: def.polygon ? 'gisAreaPane' : 'gisMarkerPane' }));
    return state.layerGroups.get(def.key);
  }
  function setMapLayerVisible(group, visible) {
    const m = map();
    if (!m || !group) return;
    const has = typeof m.hasLayer === 'function' ? m.hasLayer(group) : false;
    if (visible && !has) group.addTo ? group.addTo(m) : m.addLayer(group);
    if (!visible && has && typeof m.removeLayer === 'function') m.removeLayer(group);
  }
  function geoJsonPolygon(row) {
    const raw = row?.polygon_geojson || row?.geometry_json || row?.geometry || row?.polygon;
    if (!raw) return null;
    try {
      const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
      if (parsed?.type === 'Feature') return parsed.geometry;
      if (parsed?.type === 'Polygon') return parsed;
    } catch (ignored) {}
    return null;
  }
  function polygonLatLngs(geometry) {
    const ring = geometry?.coordinates?.[0] || [];
    return ring.map(point => Array.isArray(point) && point.length >= 2 ? [Number(point[1]), Number(point[0])] : null).filter(Boolean);
  }
  function renderFeature(def, row) {
    const polygon = def.polygon ? geoJsonPolygon(row) : null;
    if (polygon) {
      const points = polygonLatLngs(polygon);
      if (points.length >= 3) {
        const layer = L.polygon(points, { pane: 'gisAreaPane', color: def.color, fillColor: def.color, fillOpacity: 0.12, opacity: 0.98, weight: 3, lineCap: 'round', lineJoin: 'round', className: 'gis-v2-polygon' });
        layer.bindPopup(popupHtml(def, row));
        return layer;
      }
    }
    const point = coordinates(row);
    if (!point) return null;
    const marker = L.marker(point, { icon: iconFor(def), title: typeof def.title === 'function' ? def.title(row) : def.label, pane: 'gisMarkerPane', bubblingMouseEvents: false });
    marker.__gisPlatformData = row;
    marker.__gisPlatformLayer = def.key;
    marker.bindPopup(popupHtml(def, row));
    return marker;
  }
  async function loadLayer(def) {
    if (!def.endpoint || state.layerData.has(def.key) || state.loading.has(def.key)) return;
    if (!canReadGis()) return;
    state.loading.add(def.key);
    updateLayerStatus(def.key, 'Đang tải...');
    try {
      const data = await request(def.endpoint);
      const items = collection(data).filter(row => !def.filter || def.filter(row));
      const group = groupFor(def);
      if (group?.clearLayers) group.clearLayers();
      let count = 0;
      items.forEach(row => {
        const layer = renderFeature(def, row);
        if (!layer || !group?.addLayer) return;
        group.addLayer(layer);
        count++;
      });
      state.layerData.set(def.key, { items, count });
      updateLayerStatus(def.key, count ? count.toLocaleString('vi-VN') + ' đối tượng' : 'Không có dữ liệu định vị');
      if (state.active.has(def.key)) setMapLayerVisible(group, true);
    } catch (error) {
      updateLayerStatus(def.key, 'Lỗi tải dữ liệu');
      console.warn('GIS layer failed', def.key, error);
    } finally {
      state.loading.delete(def.key);
    }
  }
  function toggleLayer(def, checked) {
    if (def.parentLayer) {
      const parent = layerDefinitions.find(item => item.key === def.parentLayer);
      if (parent) toggleCheckbox(parent.key, checked);
      updateLayerStatus(def.key, 'Hiển thị theo lớp ' + (parent?.label || 'liên kết'));
      return;
    }
    const group = groupFor(def);
    if (checked) state.active.add(def.key);
    else state.active.delete(def.key);
    setMapLayerVisible(group, checked);
    if (checked && def.endpoint) loadLayer(def);
    if (checked && def.empty) updateLayerStatus(def.key, def.empty);
  }
  function toggleCheckbox(key, checked) {
    const input = document.querySelector('[data-gis-v2-layer="' + key + '"]');
    if (input && input.checked !== checked) {
      input.checked = checked;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }
  function updateLayerStatus(key, text) {
    const el = document.querySelector('[data-gis-v2-layer-status="' + key + '"]');
    if (el) el.textContent = text || '';
  }
  function installPanel() {
    const panel = $('.gis-panel');
    if (!panel || $('#gisV2LayerPanel')) return;
    const host = document.createElement('section');
    host.id = 'gisV2LayerPanel';
    host.className = 'gis-v2-layer-panel';
    host.innerHTML = '<div class="gis-v2-panel-title"><h4>Lớp dữ liệu GIS</h4><button id="gisV2RefreshLayers" class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="gisPlatform.refreshLayers"><i class="fa-solid fa-rotate"></i></button></div>' +
      '<div class="gis-v2-layer-list">' + layerDefinitions.map(def => layerToggleHtml(def)).join('') + '</div>' +
      '<div class="gis-v2-panel-title gis-v2-heatmap-title"><h4>Heatmap</h4></div>' +
      '<div class="gis-v2-heatmap-list">' + heatmapDefinitions.map(item => heatToggleHtml(item)).join('') + '</div>' +
      '<div class="gis-v2-panel-title gis-v2-tools-title"><h4>Công cụ GIS</h4></div>' +
      '<div class="gis-v2-tools"><button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="gisPlatform.measureDistance"><i class="fa-solid fa-ruler"></i> Đo khoảng cách</button><button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="gisPlatform.measureArea"><i class="fa-solid fa-vector-square"></i> Đo diện tích</button><button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="gisPlatform.clearMeasure"><i class="fa-solid fa-eraser"></i> Xóa đo</button></div>' +
      '<section class="gis-v2-area-dashboard" id="gisV2AreaDashboard"><h4>Thống kê vùng chọn</h4><div class="text-muted small">Chọn một khu vực trên bản đồ để xem thống kê.</div></section>';
    const form = $('#gisAreaForm');
    panel.insertBefore(host, form || panel.children[1] || null);
    host.querySelectorAll('[data-gis-v2-layer]').forEach(input => {
      const def = layerDefinitions.find(item => item.key === input.dataset.gisV2Layer);
      input.addEventListener('change', () => toggleLayer(def, input.checked));
      toggleLayer(def, input.checked);
    });
    host.querySelectorAll('[data-gis-v2-heat]').forEach(input => {
      input.addEventListener('change', () => {
        if (input.checked) state.heatmap.add(input.dataset.gisV2Heat);
        else state.heatmap.delete(input.dataset.gisV2Heat);
        renderHeatmaps();
        toast(input.checked ? 'Đã bật ' + input.dataset.gisV2HeatLabel : 'Đã tắt ' + input.dataset.gisV2HeatLabel);
      });
    });
  }
  function refreshActiveLayers() {
    state.layerData.clear();
    layerDefinitions.filter(def => state.active.has(def.key) && def.endpoint).forEach(loadLayer);
    toast('Đã làm mới các lớp GIS.');
  }
  function registerPlatformActions() {
    const actions = window.Thon09Platform && window.Thon09Platform.actions;
    if (!actions || typeof actions.register !== 'function' || window.__thon09GisPlatformActionsRegistered) return;
    window.__thon09GisPlatformActionsRegistered = true;
    actions.register('gisPlatform.refreshLayers', refreshActiveLayers);
    actions.register('gisPlatform.measureDistance', () => startMeasure('distance'));
    actions.register('gisPlatform.measureArea', () => startMeasure('area'));
    actions.register('gisPlatform.clearMeasure', clearMeasure);
  }
  function layerToggleHtml(def) {
    return '<label class="gis-v2-layer-row">' +
      '<input type="checkbox" data-gis-v2-layer="' + escapeHtml(def.key) + '"' + (def.defaultOn ? ' checked' : '') + '>' +
      '<span class="gis-v2-layer-icon" style="--gis-layer-color:' + escapeHtml(def.color) + '"><i class="fa-solid fa-' + escapeHtml(def.icon) + '"></i></span>' +
      '<span class="gis-v2-layer-copy"><b>' + escapeHtml(def.label) + '</b><small data-gis-v2-layer-status="' + escapeHtml(def.key) + '">' + (def.defaultOn ? 'Đang bật' : 'Tắt') + '</small></span>' +
    '</label>';
  }
  function heatToggleHtml(item) {
    return '<label class="gis-v2-heat-row">' +
      '<input type="checkbox" data-gis-v2-heat="' + escapeHtml(item.key) + '" data-gis-v2-heat-label="' + escapeHtml(item.label) + '">' +
      '<span style="--gis-layer-color:' + escapeHtml(item.color) + '"></span>' +
      '<b>' + escapeHtml(item.label) + '</b>' +
    '</label>';
  }
  function updateAreaDashboard() {
    const host = $('#gisV2AreaDashboard');
    const gis = appGis();
    if (!host || !gis) return;
    const areaId = String(gis.selectedAreaId || '');
    if (!areaId) {
      state.selectedAreaId = '';
      host.innerHTML = '<h4>Thống kê vùng chọn</h4><div class="text-muted small">Chọn một khu vực trên bản đồ để xem thống kê.</div>';
      return;
    }
    if (state.selectedAreaId === areaId && host.querySelector('.gis-v2-dashboard-grid')) return;
    state.selectedAreaId = areaId;
    const area = (gis.areas || []).find(item => String(item.id ?? item.area_id ?? item.area_code) === areaId || String(item.area_code) === areaId);
    const stats = area?.stats || {};
    host.innerHTML = '<h4>' + escapeHtml(area?.name || 'Vùng đã chọn') + '</h4>' +
      '<div class="gis-v2-dashboard-grid">' +
      dashboardCard('Tổng hộ', stats.households) +
      dashboardCard('Nhân khẩu', stats.citizens) +
      dashboardCard('Tạm trú', stats.temporary) +
      dashboardCard('Tạm vắng', stats.away) +
      dashboardCard('Đảng viên', stats.party_members) +
      dashboardCard('Hộ nghèo', stats.poor_households) +
      dashboardCard('Cận nghèo', stats.near_poor_households) +
      dashboardCard('Đã định vị', stats.located) +
      dashboardCard('Diện tích', stats.area_ha ? formatNumber(stats.area_ha) + ' ha' : '') +
      dashboardCard('Mật độ', stats.density ? formatNumber(stats.density) + ' người/km²' : '') +
      '</div>';
  }
  function dashboardCard(label, value) {
    const raw = value === undefined || value === null || value === '' ? 0 : value;
    const numeric = typeof raw === 'number' || /^[0-9.,]+$/.test(String(raw)) ? Number(String(raw).replace(',', '.')) : NaN;
    const display = Number.isFinite(numeric) ? formatNumber(numeric) : String(raw);
    return '<div><span>' + escapeHtml(label) + '</span><b>' + escapeHtml(display) + '</b></div>';
  }
  function heatLayerGroup() {
    const gis = appGis();
    if (!gis || !window.L) return null;
    if (!state.heatLayer) state.heatLayer = L.layerGroup([], { pane: 'gisHeatPane' }).addTo(gis.map);
    return state.heatLayer;
  }
  function heatSources(key) {
    const markers = householdMarkerEntries().map(item => item.row).filter(Boolean);
    if (key === 'business') return (state.layerData.get('businessHouseholds')?.items || markers.filter(row => row.business_name || row.business_type_code)).map(row => ({ row, weight: 1 }));
    if (key === 'livestock') return (state.layerData.get('livestock')?.items || []).map(row => ({ row, weight: Number(row.quantity || 1) || 1 }));
    if (key === 'vehicles') return [];
    return markers.map(row => {
      const weight = key === 'children' ? Number(row.children_count || 0) : key === 'elderly' ? Number(row.elderly_count || 0) : Number(row.total_members || 1);
      return { row, weight: Math.max(0, weight || 0) };
    }).filter(item => item.weight > 0);
  }
  function renderHeatmaps() {
    if (!state.heatmap.size) {
      state.heatLayer?.clearLayers?.();
      return;
    }
    const group = heatLayerGroup();
    if (!group) return;
    group.clearLayers?.();
    heatmapDefinitions.filter(def => state.heatmap.has(def.key)).forEach(def => {
      heatSources(def.key).forEach(item => {
        const point = coordinates(item.row);
        if (!point) return;
        const radius = Math.max(35, Math.min(220, 30 + item.weight * 10));
        const circle = L.circle(point, { pane: 'gisHeatPane', radius, color: def.color, fillColor: def.color, fillOpacity: 0.14, opacity: 0.24, weight: 1, interactive: false });
        circle.__gisHeatmap = def.key;
        group.addLayer(circle);
      });
    });
  }
  function enableMarkerDrag() {
    if (!canUpdateGis()) return;
    householdMarkerEntries().forEach(entry => {
      const marker = entry.marker;
      const row = entry.row || {};
      const id = row.id || entry.id;
      if (!id || state.dragBound.has(String(id))) return;
      state.dragBound.add(String(id));
      marker.options = Object.assign({}, marker.options || {}, { draggable: true });
      marker.dragging?.enable?.();
      marker.on?.('dragend', async () => {
        const latLng = marker.getLatLng?.();
        const lat = Number(latLng?.lat);
        const lng = Number(latLng?.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        try {
          await request('/api/gis/households/' + encodeURIComponent(id) + '/location', { method: 'PUT', body: { latitude: lat, longitude: lng, source: 'MANUAL', accuracy: null } });
          row.latitude = lat;
          row.longitude = lng;
          toast('Đã cập nhật tọa độ hộ trên bản đồ.', 'success');
        } catch (error) {
          toast(error.message || 'Không lưu được tọa độ mới.', 'danger');
        }
      });
      marker.__gisDragEnabled = true;
    });
  }
  function startMeasure(mode) {
    const m = map();
    if (!m) return;
    clearMeasure();
    state.measureMode = mode;
    m.getContainer?.().classList.add('gis-v2-measuring');
    toast(mode === 'area' ? 'Bấm các điểm trên bản đồ để đo diện tích.' : 'Bấm các điểm trên bản đồ để đo khoảng cách.');
  }
  function clearMeasure() {
    const m = map();
    state.measureMode = '';
    state.measurePoints = [];
    state.measureLayers.forEach(layer => {
      if (state.measureGroup?.removeLayer) state.measureGroup.removeLayer(layer);
      else layer.remove?.();
    });
    state.measureLayers = [];
    $('#gisV2MeasureResult')?.remove();
    m?.getContainer?.().classList.remove('gis-v2-measuring');
  }
  function ensureMeasureGroup() {
    const m = map();
    if (!m || !window.L) return null;
    if (!state.measureGroup) state.measureGroup = L.layerGroup([], { pane: 'gisDrawPane' }).addTo(m);
    return state.measureGroup;
  }
  function addMeasurePoint(latlng) {
    if (!state.measureMode) return;
    const group = ensureMeasureGroup();
    if (!group) return;
    state.measurePoints.push(latlng);
    const point = L.circle(latlng, { pane: 'gisDrawPane', radius: 8, color: '#0f766e', fillColor: '#0f766e', fillOpacity: 0.9, weight: 2 });
    group.addLayer(point);
    state.measureLayers.push(point);
    renderMeasureShape();
  }
  function renderMeasureShape() {
    const group = ensureMeasureGroup();
    if (!group || state.measurePoints.length < 2) return;
    state.measureLayers.filter(layer => layer.__gisMeasureShape).forEach(layer => group.removeLayer?.(layer));
    state.measureLayers = state.measureLayers.filter(layer => !layer.__gisMeasureShape);
    let shape = null;
    if (state.measureMode === 'area' && state.measurePoints.length >= 3) shape = L.polygon(state.measurePoints, { pane: 'gisDrawPane', color: '#0f766e', fillColor: '#0f766e', fillOpacity: 0.16, opacity: 1, weight: 3 });
    else if (window.L.polyline) shape = L.polyline(state.measurePoints, { pane: 'gisDrawPane', color: '#0f766e', weight: 3 });
    if (shape) {
      shape.__gisMeasureShape = true;
      group.addLayer(shape);
      state.measureLayers.push(shape);
    }
    updateMeasureResult();
  }
  function updateMeasureResult() {
    const host = $('#gisMap')?.parentElement;
    if (!host) return;
    let el = $('#gisV2MeasureResult');
    if (!el) {
      el = document.createElement('div');
      el.id = 'gisV2MeasureResult';
      el.className = 'gis-v2-measure-result';
      host.appendChild(el);
    }
    const distance = measureDistance(state.measurePoints);
    const area = state.measureMode === 'area' && state.measurePoints.length >= 3 ? polygonArea(state.measurePoints) : 0;
    el.innerHTML = '<strong>' + (state.measureMode === 'area' ? 'Đo diện tích' : 'Đo khoảng cách') + '</strong><span>' + formatDistance(distance) + (area ? ' - ' + formatArea(area) : '') + '</span>';
  }
  function measureDistance(points) {
    let total = 0;
    for (let i = 1; i < points.length; i++) total += haversine(points[i - 1], points[i]);
    return total;
  }
  function haversine(a, b) {
    const r = 6371000;
    const lat1 = Number(a.lat ?? a[0]) * Math.PI / 180;
    const lat2 = Number(b.lat ?? b[0]) * Math.PI / 180;
    const dLat = lat2 - lat1;
    const dLng = (Number(b.lng ?? b[1]) - Number(a.lng ?? a[1])) * Math.PI / 180;
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
    return 2 * r * Math.asin(Math.sqrt(h));
  }
  function polygonArea(points) {
    if (points.length < 3) return 0;
    const meanLat = points.reduce((sum, p) => sum + Number(p.lat ?? p[0]), 0) / points.length * Math.PI / 180;
    const metersPerLat = 111320;
    const metersPerLng = 111320 * Math.cos(meanLat);
    const xy = points.map(p => [Number(p.lng ?? p[1]) * metersPerLng, Number(p.lat ?? p[0]) * metersPerLat]);
    let area = 0;
    for (let i = 0, j = xy.length - 1; i < xy.length; j = i++) area += (xy[j][0] * xy[i][1]) - (xy[i][0] * xy[j][1]);
    return Math.abs(area / 2);
  }
  function formatDistance(value) {
    return value >= 1000 ? (value / 1000).toFixed(2) + ' km' : Math.round(value) + ' m';
  }
  function formatArea(value) {
    return value >= 10000 ? (value / 10000).toFixed(2) + ' ha' : Math.round(value).toLocaleString('vi-VN') + ' m²';
  }
  async function unifiedSearch(query) {
    const q = String(query || '').trim();
    if (q.length < 2) return [];
    const tasks = [
      request('/api/gis/households?light=1&q=' + encodeURIComponent(q)).then(data => collection(data).map(row => ({ type: 'household', label: row.household_code || row.head_citizen_name, sub: row.head_citizen_name || 'Hộ/Nhân khẩu', row }))).catch(() => []),
      request('/api/public-assets/gis?q=' + encodeURIComponent(q)).then(data => collection(data).map(row => ({ type: 'publicAsset', label: row.asset_name || row.asset_code, sub: 'Công trình công cộng', row }))).catch(() => []),
      request('/api/agriculture/gis?q=' + encodeURIComponent(q)).then(data => collection(data).map(row => ({ type: 'agriculture', label: row.parcel_code || row.field_area, sub: 'Sản xuất nông nghiệp', row }))).catch(() => [])
    ];
    return (await Promise.all(tasks)).flat()
      .filter(item => coordinates(item.row) || geoJsonPolygon(item.row))
      .sort((a, b) => searchScore(b, q) - searchScore(a, q))
      .slice(0, 12);
  }
  function searchScore(item, query) {
    const q = normalizeText(query);
    const text = normalizeText([item.label, item.sub, item.row?.household_code, item.row?.asset_code, item.row?.parcel_code, item.row?.head_citizen_name].filter(Boolean).join(' '));
    if (!q || !text) return 0;
    if (text.split(/\s+/).some(part => part === q)) return 40;
    if (text.startsWith(q)) return 30;
    if (text.includes(q)) return 20;
    return 0;
  }
  function installUnifiedSearch() {
    const input = $('#gisSearch');
    const host = $('#gisSearchResults');
    if (!input || !host || input.__gisPlatformSearchBound) return;
    input.__gisPlatformSearchBound = true;
    input.addEventListener('input', () => {
      clearTimeout(state.searchTimer);
      state.searchTimer = setTimeout(async () => {
        const q = input.value.trim();
        if (q.length < 2) return;
        const results = await unifiedSearch(q);
        if (!results.length) return;
        host.innerHTML = results.map((item, index) => '<button type="button" data-platform-action="gisPlatform.searchFocus" data-gis-v2-result="' + index + '"><strong>' + escapeHtml(item.label || item.sub) + '</strong><span>' + escapeHtml(item.sub || '') + '</span></button>').join('');
        state.searchResults = results;
        host.classList.remove('d-none');
      }, 420);
    });
  }
  function focusSearchResult(index) {
    const item = state.searchResults?.[Number(index)];
    const m = map();
    if (!item || !m) return;
    $('#gisSearchResults')?.classList.add('d-none');
    const point = coordinates(item.row);
    if (point) {
      m.setView(point, Math.max(m.getZoom(), 18), { animate: true });
      const def = layerDefinitions.find(layer => layer.key === (item.type === 'publicAsset' ? 'publicAssets' : item.type));
      if (def && !state.active.has(def.key)) toggleCheckbox(def.key, true);
      if (item.type === 'household' && window.App?.gis?.manager?.markers?.focus) window.App.gis.manager.markers.focus(item.row.id);
      return;
    }
    const polygon = geoJsonPolygon(item.row);
    const points = polygonLatLngs(polygon);
    if (points.length && m.fitBounds) m.fitBounds(points, { padding: [28, 28], maxZoom: 17 });
  }
  function installMapHooks() {
    const m = map();
    if (!m || m.__gisPlatformHooksBound) return;
    m.__gisPlatformHooksBound = true;
    m.on?.('click', event => addMeasurePoint(event.latlng));
    m.on?.('moveend zoomend', () => {
      schedulePlatformSync(40);
    });
  }
  function runPlatformSync() {
    state.syncTimer = null;
    updateAreaDashboard();
    enableMarkerDrag();
    renderHeatmaps();
  }
  function schedulePlatformSync(delay) {
    if (state.syncTimer) clearTimeout(state.syncTimer);
    state.syncTimer = setTimeout(runPlatformSync, delay || 0);
  }
  function installStartupSync() {
    [0, 300, 900, 1800, 3200].forEach(delay => setTimeout(() => schedulePlatformSync(0), delay));
  }
  function install() {
    if (state.installed || !map() || !window.L) return;
    state.installed = true;
    registerPlatformActions();
    installPanel();
    installUnifiedSearch();
    installMapHooks();
    installStartupSync();
    window.Thon09GisPlatform = {
      definitions: layerDefinitions.slice(),
      heatmaps: heatmapDefinitions.slice(),
      state,
      loadLayer: key => {
        const def = layerDefinitions.find(item => item.key === key);
        return def ? loadLayer(def) : Promise.resolve();
      },
      refreshLayers: refreshActiveLayers,
      updateAreaDashboard,
      renderHeatmaps,
      enableMarkerDrag,
      startMeasure,
      clearMeasure,
      focusSearchResult,
      setLayerVisible: (key, visible) => {
        const def = layerDefinitions.find(item => item.key === key);
        if (def) toggleCheckbox(key, Boolean(visible));
      }
    };
    const actions = window.Thon09Platform && window.Thon09Platform.actions;
    if (actions?.register && !window.__thon09GisPlatformSearchActionRegistered) {
      window.__thon09GisPlatformSearchActionRegistered = true;
      actions.register('gisPlatform.searchFocus', context => focusSearchResult(context.dataset.gisV2Result));
    }
    document.dispatchEvent(new CustomEvent('thon09:gis-platform-ready', { detail: { definitions: layerDefinitions } }));
  }
  function scheduleInstall() {
    if (state.installed) return;
    let tries = 0;
    const timer = setInterval(() => {
      install();
      tries++;
      if (state.installed || tries > 60) clearInterval(timer);
    }, 150);
  }
  const originalLoadGisMap = window.loadGisMap;
  if (typeof originalLoadGisMap === 'function' && !originalLoadGisMap.__thon09GisPlatformWrapped) {
    const wrapped = async function () {
      const result = await originalLoadGisMap.apply(this, arguments);
      scheduleInstall();
      return result;
    };
    wrapped.__thon09GisPlatformWrapped = true;
    window.loadGisMap = wrapped;
  }
  document.addEventListener('DOMContentLoaded', scheduleInstall);
  scheduleInstall();
})();
