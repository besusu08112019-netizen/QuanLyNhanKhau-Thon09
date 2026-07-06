(function () {
  'use strict';

  if (window.__thon09GisSmartLoaded) return;
  window.__thon09GisSmartLoaded = true;

  const state = {
    rows: [],
    markerLayer: null,
    heatLayer: null,
    gpsLayer: null,
    roadLayer: null,
    layers: {
      markers: true,
      polygons: true,
      photos: true,
      roads: false,
      boundaries: true,
      heatmap: false,
      gps: false,
    },
    filters: {
      area_code: '',
      party: false,
      children: false,
      elderly: false,
      poor: false,
      near_poor: false,
      labor: false,
      permanent: false,
      temporary: false,
    },
    heatMetric: 'density',
  };

  window.thon09GisSmartFilters = state.filters;

  function $(selector, root = document) { return root.querySelector(selector); }
  function $$(selector, root = document) { return Array.from(root.querySelectorAll(selector)); }
  function esc(value) { return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char])); }
  function map() { return window.App?.gis?.map || null; }
  function polygonLayer() { return window.App?.gis?.layerGroup || null; }
  function number(value) { return Number(value || 0).toLocaleString('vi-VN'); }

  function ensureStyles() {
    if ($('#gis-smart-style')) return;
    const style = document.createElement('style');
    style.id = 'gis-smart-style';
    style.textContent = `
      .gis-smart-panel{display:grid;gap:12px;margin-top:12px}
      .gis-smart-block{border:1px solid #e2e8f0;border-radius:14px;background:#fff;padding:12px}
      .gis-smart-block h4{margin:0 0 10px;color:#0f3768;font-size:13px;font-weight:850;text-transform:uppercase}
      .gis-smart-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
      .gis-smart-toggle,.gis-smart-filter{min-height:36px;border:1px solid #dbe5ef;border-radius:11px;background:#f8fbff;color:#334155;font-size:12px;font-weight:800;display:flex;align-items:center;gap:7px;padding:7px 9px}
      .gis-smart-toggle input,.gis-smart-filter input{accent-color:#0f766e}
      .gis-smart-select{min-height:38px;border:1px solid #dbe5ef;border-radius:11px;padding:0 10px;background:#fff;color:#334155;font-size:13px}
      .gis-household-marker{border:0;background:transparent}
      .gis-household-marker-default,.gis-household-marker-thumb{width:44px;height:44px;border-radius:50%;display:grid;place-items:center;border:3px solid #fff;background:#0f766e;color:#fff;box-shadow:0 8px 20px rgba(15,23,42,.22);overflow:hidden}
      .gis-household-marker-thumb img{width:100%;height:100%;object-fit:cover}
      .gis-household-marker.is-active .gis-household-marker-default,.gis-household-marker.is-active .gis-household-marker-thumb{outline:4px solid rgba(245,158,11,.38);transform:scale(1.08)}
      .gis-smart-hide-photos .gis-household-marker-thumb img{display:none}
      .gis-smart-hide-photos .gis-household-marker-thumb::after{content:'\\f015';font-family:'Font Awesome 6 Free';font-weight:900;color:#fff}
      .leaflet-popup-pane{z-index:760}
      .leaflet-popup,.leaflet-popup-content-wrapper,.leaflet-popup-content,.gis-smart-popup,.gis-smart-popup button,.gis-smart-popup a{pointer-events:auto;touch-action:manipulation}
      .gis-smart-popup{min-width:280px}
      .gis-smart-popup-head{display:grid;grid-template-columns:86px minmax(0,1fr);gap:12px;align-items:center;margin-bottom:10px}
      .gis-smart-popup-photo{width:86px;height:72px;border-radius:12px;background:#eef6f1;overflow:hidden;display:grid;place-items:center;color:#0f766e}
      .gis-smart-popup-photo img{width:100%;height:100%;object-fit:cover}
      .gis-smart-popup h4{margin:0;color:#0f3768;font-size:17px;font-weight:850}.gis-smart-popup p{margin:2px 0 4px;color:#334155;font-weight:750}.gis-smart-popup-head span{display:inline-flex;border-radius:999px;background:#e8f5ee;color:#075f35;padding:3px 8px;font-size:11px;font-weight:800}
      .gis-smart-popup dl{display:grid;grid-template-columns:104px minmax(0,1fr);gap:6px 10px;margin:0 0 10px}.gis-smart-popup dt{color:#64748b;font-size:12px}.gis-smart-popup dd{margin:0;color:#172033;font-size:12px;font-weight:750;word-break:break-word}
      .gis-smart-popup-actions{display:flex;flex-wrap:wrap;gap:6px}.gis-smart-popup-actions .btn{border-radius:9px;font-size:12px;font-weight:800}
      .gis-smart-route-links{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}.gis-smart-route-links button{border:0;border-radius:999px;background:#eef2f7;color:#334155;padding:4px 8px;font-size:11px;font-weight:800}
      .gis-heat-dot{border-radius:50%;mix-blend-mode:multiply}
      @media(max-width:820px){.gis-smart-grid{grid-template-columns:1fr}.gis-smart-panel{margin-bottom:12px}.gis-smart-popup{min-width:240px}.gis-smart-popup-head{grid-template-columns:72px minmax(0,1fr)}.gis-smart-popup-photo{width:72px;height:64px}}
    `;
    document.head.appendChild(style);
  }

  function mountPanel() {
    const host = $('#gisAreaList');
    if (!host || $('#gisSmartPanel')) return;
    ensureStyles();
    const panel = document.createElement('section');
    panel.id = 'gisSmartPanel';
    panel.className = 'gis-smart-panel';
    panel.innerHTML = `
      <div class="gis-smart-block">
        <h4>Lớp hiển thị</h4>
        <div class="gis-smart-grid" data-gis-smart-layers>
          ${layerToggle('markers', 'Marker')}
          ${layerToggle('polygons', 'Polygon')}
          ${layerToggle('photos', 'Ảnh hộ')}
          ${layerToggle('roads', 'Đường giao thông')}
          ${layerToggle('boundaries', 'Ranh giới khu vực')}
          ${layerToggle('heatmap', 'Heatmap')}
          ${layerToggle('gps', 'GPS')}
        </div>
      </div>
      <div class="gis-smart-block">
        <h4>Bộ lọc GIS</h4>
        <select class="gis-smart-select w-100 mb-2" data-gis-filter="area_code"><option value="">Tất cả khu vực</option></select>
        <div class="gis-smart-grid">
          ${filterToggle('party', 'Đảng viên')}
          ${filterToggle('children', 'Trẻ em')}
          ${filterToggle('elderly', 'Người cao tuổi')}
          ${filterToggle('poor', 'Hộ nghèo')}
          ${filterToggle('near_poor', 'Hộ cận nghèo')}
          ${filterToggle('labor', 'Lao động')}
          ${filterToggle('permanent', 'Thường trú')}
          ${filterToggle('temporary', 'Tạm trú')}
        </div>
      </div>
      <div class="gis-smart-block">
        <h4>Heatmap</h4>
        <select class="gis-smart-select w-100" data-gis-heat-metric>
          <option value="density">Mật độ dân cư</option>
          <option value="elderly">Người cao tuổi</option>
          <option value="children">Trẻ em</option>
          <option value="party">Đảng viên</option>
          <option value="poor">Hộ nghèo</option>
          <option value="near_poor">Hộ cận nghèo</option>
        </select>
      </div>
    `;
    host.parentElement.insertBefore(panel, host);
    bindPanel(panel);
    refreshAreaOptions();
  }

  function layerToggle(key, label) {
    return '<label class="gis-smart-toggle"><input type="checkbox" data-gis-layer="' + key + '" ' + (state.layers[key] ? 'checked' : '') + '> ' + label + '</label>';
  }

  function filterToggle(key, label) {
    return '<label class="gis-smart-filter"><input type="checkbox" data-gis-filter="' + key + '"> ' + label + '</label>';
  }

  function bindPanel(panel) {
    $$('[data-gis-layer]', panel).forEach(input => input.addEventListener('change', () => {
      state.layers[input.dataset.gisLayer] = input.checked;
      applyLayers();
    }));
    $$('[data-gis-filter]', panel).forEach(input => input.addEventListener('change', () => {
      const key = input.dataset.gisFilter;
      state.filters[key] = input.type === 'checkbox' ? input.checked : input.value;
      reloadMarkers();
    }));
    $('[data-gis-heat-metric]', panel)?.addEventListener('change', event => {
      state.heatMetric = event.target.value;
      renderHeatmap();
    });
  }

  function refreshAreaOptions() {
    const select = $('[data-gis-filter="area_code"]');
    if (!select) return;
    const current = select.value;
    const areas = Array.isArray(window.App?.gis?.areas) ? window.App.gis.areas : [];
    select.innerHTML = '<option value="">Tất cả khu vực</option>' + areas.map(area => '<option value="' + esc(area.area_code || '') + '">' + esc(area.name || area.area_code || '') + '</option>').join('');
    select.value = current;
  }

  function reloadMarkers() {
    window.thon09GisSmartFilters = state.filters;
    if (typeof window.thon09LoadGisHouseholdMarkers === 'function') window.thon09LoadGisHouseholdMarkers('', { force: true });
  }

  function hasOpenHouseholdPopup() {
    return typeof window.thon09GisHasOpenHouseholdPopup === 'function' && window.thon09GisHasOpenHouseholdPopup();
  }

  function applyLayers() {
    const m = map();
    if (!m) return;
    document.body.classList.toggle('gis-smart-hide-photos', !state.layers.photos);
    toggleLayer(state.markerLayer, state.layers.markers || hasOpenHouseholdPopup(), true);
    toggleLayer(polygonLayer(), state.layers.polygons && state.layers.boundaries);
    toggleRoads();
    renderHeatmap();
    toggleGps();
  }

  function toggleLayer(layer, visible, isMarkerLayer) {
    const m = map();
    if (!m || !layer) return;
    if (isMarkerLayer && hasOpenHouseholdPopup()) visible = true;
    if (visible && !m.hasLayer(layer)) layer.addTo(m);
    if (!visible && m.hasLayer(layer)) m.removeLayer(layer);
  }

  function toggleRoads() {
    const m = map();
    if (!m || !window.L) return;
    if (!state.roadLayer) {
      state.roadLayer = L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', { maxZoom: 20, opacity: 0.55, attribution: '&copy; OpenStreetMap contributors' });
    }
    toggleLayer(state.roadLayer, state.layers.roads);
  }

  function metricValue(row) {
    if (state.heatMetric === 'elderly') return Number(row.elderly_count || 0);
    if (state.heatMetric === 'children') return Number(row.children_count || 0);
    if (state.heatMetric === 'party') return Number(row.party_members || 0);
    if (state.heatMetric === 'poor') return Number(row.household_type === 'Hộ nghèo' || row.poor_household ? 1 : 0);
    if (state.heatMetric === 'near_poor') return Number(row.household_type === 'Hộ cận nghèo' || row.near_poor_household ? 1 : 0);
    return Number(row.total_members || 0);
  }

  function renderHeatmap() {
    const m = map();
    if (!m || !window.L) return;
    if (state.heatLayer) {
      m.removeLayer(state.heatLayer);
      state.heatLayer = null;
    }
    if (!state.layers.heatmap) return;
    state.heatLayer = L.layerGroup();
    const max = Math.max(1, ...state.rows.map(metricValue));
    state.rows.forEach(row => {
      if (row.latitude == null || row.longitude == null) return;
      const value = metricValue(row);
      if (value <= 0) return;
      const ratio = Math.max(0.12, value / max);
      const color = ratio > 0.66 ? '#dc2626' : (ratio > 0.33 ? '#f59e0b' : '#22c55e');
      L.circleMarker([row.latitude, row.longitude], {
        radius: 12 + ratio * 24,
        color,
        weight: 1,
        fillColor: color,
        fillOpacity: 0.22 + ratio * 0.28,
        className: 'gis-heat-dot',
        interactive: false,
      }).addTo(state.heatLayer);
    });
    state.heatLayer.addTo(m);
  }

  function toggleGps() {
    const m = map();
    if (!m || !window.L) return;
    if (!state.layers.gps) {
      if (state.gpsLayer && m.hasLayer(state.gpsLayer)) m.removeLayer(state.gpsLayer);
      return;
    }
    if (!navigator.geolocation || !window.isSecureContext) return;
    navigator.geolocation.getCurrentPosition(position => {
      const point = [position.coords.latitude, position.coords.longitude];
      if (!state.gpsLayer) state.gpsLayer = L.layerGroup();
      state.gpsLayer.clearLayers();
      L.circleMarker(point, { radius: 7, color: '#2563eb', weight: 3, fillColor: '#60a5fa', fillOpacity: 0.9 }).addTo(state.gpsLayer).bindPopup('Vị trí hiện tại');
      L.circle(point, { radius: position.coords.accuracy || 30, color: '#2563eb', weight: 1, fillOpacity: 0.06 }).addTo(state.gpsLayer);
      state.gpsLayer.addTo(m);
    }, () => {});
  }

  function updateSummary() {
    const status = $('#gisMapStatus');
    if (!status || !state.rows.length) return;
    const totalMembers = state.rows.reduce((sum, row) => sum + Number(row.total_members || 0), 0);
    status.textContent = number(state.rows.length) + ' marker - ' + number(totalMembers) + ' nhân khẩu';
  }

  document.addEventListener('thon09:gis-markers-loaded', event => {
    state.rows = event.detail?.rows || [];
    state.markerLayer = event.detail?.layer || state.markerLayer;
    if (hasOpenHouseholdPopup() && typeof window.thon09GisEnsureHouseholdMarkerLayerVisible === 'function') window.thon09GisEnsureHouseholdMarkerLayerVisible();
    applyLayers();
    updateSummary();
  });

  function boot() {
    mountPanel();
    refreshAreaOptions();
    document.addEventListener('thon09:screen-change', () => {
      mountPanel();
      refreshAreaOptions();
      setTimeout(applyLayers, 250);
    });
    setInterval(() => {
      mountPanel();
      refreshAreaOptions();
    }, 1200);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
