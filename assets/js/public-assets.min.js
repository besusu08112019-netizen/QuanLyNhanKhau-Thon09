(function () {
'use strict';

const API = '/api/public-assets';
const state = {
  page: 1,
  pageSize: 20,
  search: '',
  type_id: '',
  area_code: '',
  status: '',
  located: '',
  area_min: '',
  area_max: '',
  sort: 'asset_code',
  direction: 'ASC',
  catalogs: null,
  current: null,
  loaded: false
};

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
const safe = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[c]));
const number = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
const toast = (message, type = 'info') => typeof window.showToast === 'function' ? window.showToast(message, type) : console[type === 'danger' ? 'error' : 'log'](message);
const can = action => typeof window.thon09CanAccess === 'function' ? window.thon09CanAccess('public_assets', action) : true;
const request = (url, options = {}) => typeof window.api === 'function' ? window.api(url, options) : fetch(url, options).then(r => r.json()).then(p => p.data || p);

function init() {
  bindEvents();
  wrapGisLoader();
  if (window.App?.screen === 'publicAssets') loadPublicAssets();
  if (window.App?.screen === 'gis') scheduleGisLayer();
}

function bindEvents() {
  $('#publicAssetsSearchBtn')?.addEventListener('click', () => { state.page = 1; readFilters(); loadPublicAssets(); });
  $('#publicAssetsResetBtn')?.addEventListener('click', resetFilters);
  $('#publicAssetsSearch')?.addEventListener('input', debounce(() => { state.page = 1; readFilters(); loadPublicAssets(); }, 350));
  ['publicAssetsTypeFilter','publicAssetsAreaFilter','publicAssetsStatusFilter','publicAssetsLocatedFilter','publicAssetsAreaMin','publicAssetsAreaMax'].forEach(id => $('#'+id)?.addEventListener('change', () => { state.page = 1; readFilters(); loadPublicAssets(); }));
  $('#publicAssetsPageSize')?.addEventListener('change', event => { state.pageSize = Number(event.target.value || 20); state.page = 1; loadPublicAssets(); });
  $('#publicAssetsAddBtn')?.addEventListener('click', () => openPublicAssetForm());
  $('#publicAssetForm')?.addEventListener('submit', savePublicAsset);
  $('#publicAssetUseGpsBtn')?.addEventListener('click', useCurrentGps);
  $('#publicAssetDetailEditBtn')?.addEventListener('click', () => state.current?.id && openPublicAssetForm(state.current.id));
  $('#publicAssetsRows')?.addEventListener('click', handleRowClick);
  $('#publicAssetsPager')?.addEventListener('click', handlePagerClick);
  $('#publicAssetsMiniDashboard')?.addEventListener('click', handleDashboardClick);
  $$('[data-public-asset-sort]').forEach(th => th.addEventListener('click', () => sortBy(th.dataset.publicAssetSort)));
  document.addEventListener('thon09:screen-change', event => {
    const screen = event.detail?.screen;
    if (screen === 'publicAssets') loadPublicAssets();
    if (screen === 'gis') scheduleGisLayer();
  });
}

function debounce(fn, wait) {
  let timer;
  return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), wait); };
}

function readFilters() {
  state.search = $('#publicAssetsSearch')?.value.trim() || '';
  state.type_id = $('#publicAssetsTypeFilter')?.value || '';
  state.area_code = $('#publicAssetsAreaFilter')?.value || '';
  state.status = $('#publicAssetsStatusFilter')?.value || '';
  state.located = $('#publicAssetsLocatedFilter')?.value || '';
  state.area_min = $('#publicAssetsAreaMin')?.value.trim() || '';
  state.area_max = $('#publicAssetsAreaMax')?.value.trim() || '';
  state.pageSize = Number($('#publicAssetsPageSize')?.value || state.pageSize || 20);
}

function resetFilters() {
  ['publicAssetsSearch','publicAssetsTypeFilter','publicAssetsAreaFilter','publicAssetsStatusFilter','publicAssetsLocatedFilter','publicAssetsAreaMin','publicAssetsAreaMax'].forEach(id => { const el = $('#'+id); if (el) el.value = ''; });
  Object.assign(state, { page: 1, search: '', type_id: '', area_code: '', status: '', located: '', area_min: '', area_max: '', sort: 'asset_code', direction: 'ASC' });
  loadPublicAssets();
}

async function ensureCatalogs() {
  if (state.catalogs) return state.catalogs;
  state.catalogs = await request(API + '/catalogs', { cacheTtl: 60000 });
  fillSelect($('#publicAssetsTypeFilter'), state.catalogs.types, 'Tất cả');
  fillSelect($('#publicAssetsAreaFilter'), state.catalogs.areas, 'Tất cả');
  fillSelect($('#publicAssetsStatusFilter'), state.catalogs.statuses, 'Tất cả');
  fillSelect($('#publicAssetTypeSelect'), state.catalogs.types, 'Chọn loại');
  fillSelect($('#publicAssetStatusSelect'), state.catalogs.statuses, 'Chọn trạng thái');
  return state.catalogs;
}

function fillSelect(select, items = [], first = '') {
  if (!select) return;
  const current = select.value;
  select.innerHTML = first ? `<option value="">${safe(first)}</option>` : '';
  items.forEach(item => {
    const option = document.createElement('option');
    option.value = item.value;
    option.textContent = item.category ? `${item.category} - ${item.label}` : item.label;
    select.appendChild(option);
  });
  if ([...select.options].some(option => option.value === current)) select.value = current;
}

async function loadPublicAssets() {
  if (!$('#publicAssetsScreen')) return;
  await ensureCatalogs();
  readFilters();
  const params = new URLSearchParams({ page: state.page, pageSize: state.pageSize, search: state.search, type_id: state.type_id, area_code: state.area_code, status: state.status, located: state.located, area_min: state.area_min, area_max: state.area_max, sort: state.sort, direction: state.direction });
  const [list, dashboard] = await Promise.all([
    request(API + '?' + params.toString()),
    request(API + '/dashboard?' + params.toString(), { cacheTtl: 15000 })
  ]);
  renderDashboard(dashboard);
  renderRows(list);
  renderPager(list);
  state.loaded = true;
  if (typeof window.thon09ApplyAccessControls === 'function') window.thon09ApplyAccessControls();
}

function renderDashboard(data = {}) {
  const host = $('#publicAssetsMiniDashboard');
  if (!host) return;
  const m = data.metrics || {};
  const cards = [
    ['total_assets', 'Tổng công trình', 'fa-building-columns', number(m.total_assets || 0), ''],
    ['active_assets', 'Đang sử dụng', 'fa-circle-check', number(m.active_assets || 0), 'status:ACTIVE'],
    ['located_assets', 'Đã định vị GPS', 'fa-location-dot', number(m.located_assets || 0), 'located:1'],
    ['total_campus_area', 'Tổng diện tích khuôn viên', 'fa-ruler-combined', formatArea(m.total_campus_area || 0), '']
  ];
  host.innerHTML = cards.map(card => `<article class="agri-kpi-card" ${card[4] ? `data-public-asset-filter="${card[4]}"` : ''}><span><i class="fa-solid ${card[2]}"></i></span><div><strong>${card[3]}</strong><small>${safe(card[1])}</small></div></article>`).join('') + renderMiniBreakdown(data.charts || {});
}

function renderMiniBreakdown(charts) {
  const areaGroups = (charts.area_by_category || charts.area_by_type || []).slice(0, 4).map(row => `<span class="badge bg-light text-dark border me-1 mb-1">${safe(row.label)}: ${formatArea(row.campus_area)}</span>`).join('');
  const types = (charts.types || []).slice(0, 4).map(row => `<span class="badge bg-light text-dark border me-1 mb-1">${safe(row.label)}: ${number(row.value)}</span>`).join('');
  const areas = (charts.areas || []).slice(0, 4).map(row => `<span class="badge bg-light text-dark border me-1 mb-1">${safe(row.label)}: ${number(row.value)}</span>`).join('');
  if (!areaGroups && !types && !areas) return '';
  return `<article class="agri-kpi-card" style="grid-column:span 2"><span><i class="fa-solid fa-chart-pie"></i></span><div><strong>Phân bổ</strong><small>${areaGroups || types || areas}</small></div></article>`;
}

function renderRows(data = {}) {
  const rows = data.items || [];
  const tbody = $('#publicAssetsRows');
  if (!tbody) return;
  $('#publicAssetsTotalCount') && ($('#publicAssetsTotalCount').textContent = `Tổng số: ${number(data.total || 0)} công trình`);
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Chưa có công trình công cộng phù hợp bộ lọc.</td></tr>';
    return;
  }
  tbody.innerHTML = rows.map((item, index) => {
    const photo = item.cover_photo_url ? `<img src="${safe(item.cover_photo_url)}" alt="" style="width:52px;height:42px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb">` : `<span class="d-inline-flex align-items-center justify-content-center bg-light border rounded" style="width:52px;height:42px"><i class="fa-solid ${safe(item.type_icon || 'fa-building-columns')} text-secondary"></i></span>`;
    const actions = [
      `<button class="btn btn-sm btn-outline-primary" type="button" data-action="detail" data-id="${item.id}"><i class="fa-solid fa-eye"></i></button>`,
      can('update') ? `<button class="btn btn-sm btn-outline-secondary" type="button" data-action="edit" data-id="${item.id}"><i class="fa-solid fa-pen"></i></button>` : '',
      can('delete') ? `<button class="btn btn-sm btn-outline-danger" type="button" data-action="delete" data-id="${item.id}"><i class="fa-solid fa-trash"></i></button>` : ''
    ].filter(Boolean).join(' ');
    return `<tr><td>${(state.page - 1) * state.pageSize + index + 1}</td><td>${photo}</td><td><strong>${safe(item.asset_code)}</strong></td><td><div class="fw-semibold">${safe(item.asset_name)}</div><small class="text-muted">${safe(item.address || '')}</small></td><td>${safe(item.type_name || 'Chưa cập nhật')}<br><small class="text-muted">${safe(item.category || '')}</small></td><td>${safe(item.area_code || 'Chưa cập nhật')}</td><td>${formatAssetAreas(item)}</td><td>${safe(item.manager_name || item.managing_unit || 'Chưa cập nhật')}<br><small class="text-muted">${safe(item.manager_phone || '')}</small></td><td>${statusBadge(item)}</td><td class="text-end">${actions}</td></tr>`;
  }).join('');
}

function formatArea(value) {
  const num = Number(value || 0);
  return num > 0 ? `${number(num)} m²` : 'Chưa cập nhật';
}

function formatAssetAreas(item) {
  const campus = item.campus_area ? `KV: ${formatArea(item.campus_area)}` : 'KV: Chưa cập nhật';
  const building = item.building_area ? `XD: ${formatArea(item.building_area)}` : 'XD: Chưa cập nhật';
  return `${campus}<br><small class="text-muted">${building}</small>`;
}

function statusBadge(item) {
  const map = { ACTIVE: 'success', REPAIRING: 'warning', SUSPENDED: 'secondary', INACTIVE: 'dark' };
  return `<span class="badge bg-${map[item.status] || 'secondary'}">${safe(item.status_label || item.status)}</span>`;
}

function renderPager(data = {}) {
  const host = $('#publicAssetsPager');
  if (!host) return;
  const totalPages = Number(data.totalPages || 1);
  const page = Number(data.page || state.page || 1);
  state.page = page;
  if (totalPages <= 1) { host.innerHTML = ''; return; }
  const buttons = [];
  buttons.push(`<button class="btn btn-sm btn-outline-secondary" data-page="${Math.max(1, page - 1)}" ${page <= 1 ? 'disabled' : ''}>Trước</button>`);
  const start = Math.max(1, page - 2);
  const end = Math.min(totalPages, page + 2);
  for (let i = start; i <= end; i += 1) buttons.push(`<button class="btn btn-sm ${i === page ? 'btn-primary' : 'btn-outline-secondary'}" data-page="${i}">${i}</button>`);
  buttons.push(`<button class="btn btn-sm btn-outline-secondary" data-page="${Math.min(totalPages, page + 1)}" ${page >= totalPages ? 'disabled' : ''}>Sau</button>`);
  host.innerHTML = `<div class="d-flex gap-2 justify-content-end align-items-center mt-3">${buttons.join('')}</div>`;
}

function handlePagerClick(event) {
  const button = event.target.closest('[data-page]');
  if (!button || button.disabled) return;
  state.page = Number(button.dataset.page || 1);
  loadPublicAssets();
}

function handleRowClick(event) {
  const button = event.target.closest('[data-action]');
  if (!button) return;
  const id = Number(button.dataset.id);
  if (button.dataset.action === 'detail') openPublicAssetDetail(id);
  if (button.dataset.action === 'edit') openPublicAssetForm(id);
  if (button.dataset.action === 'delete') deletePublicAsset(id);
}

function handleDashboardClick(event) {
  const card = event.target.closest('[data-public-asset-filter]');
  if (!card) return;
  const [key, value] = card.dataset.publicAssetFilter.split(':');
  const target = key === 'status' ? $('#publicAssetsStatusFilter') : $('#publicAssetsLocatedFilter');
  if (target) target.value = value;
  state.page = 1;
  readFilters();
  loadPublicAssets();
}

function sortBy(field) {
  if (state.sort === field) state.direction = state.direction === 'ASC' ? 'DESC' : 'ASC';
  else Object.assign(state, { sort: field, direction: 'ASC' });
  loadPublicAssets();
}

async function openPublicAssetDetail(id) {
  await ensureCatalogs();
  const item = await request(`${API}/${id}`);
  state.current = item;
  $('#publicAssetDetailTitle') && ($('#publicAssetDetailTitle').textContent = item.asset_name || 'Chi tiết công trình');
  $('#publicAssetDetailSubtitle') && ($('#publicAssetDetailSubtitle').textContent = `${item.asset_code || ''} · ${item.type_name || 'Chưa cập nhật'} · ${item.status_label || ''}`);
  const body = $('#publicAssetDetailBody');
  if (body) body.innerHTML = detailHtml(item);
  $('#publicAssetDetailEditBtn')?.classList.toggle('d-none', !can('update'));
  bootstrap.Modal.getOrCreateInstance($('#publicAssetDetailModal')).show();
}

function detailHtml(item) {
  const image = item.cover_photo_url ? `<img src="${safe(item.cover_photo_url)}" alt="" class="img-fluid rounded border mb-3" style="max-height:260px;object-fit:cover;width:100%">` : '<div class="bg-light border rounded d-flex align-items-center justify-content-center mb-3" style="height:180px"><i class="fa-solid fa-building-columns fa-3x text-secondary"></i></div>';
  const map = item.latitude && item.longitude ? `<iframe title="Vị trí công trình" src="https://www.openstreetmap.org/export/embed.html?bbox=${item.longitude - 0.002}%2C${item.latitude - 0.002}%2C${item.longitude + 0.002}%2C${item.latitude + 0.002}&marker=${item.latitude}%2C${item.longitude}" style="width:100%;height:260px;border:1px solid #d1d5db;border-radius:8px"></iframe>` : '<div class="text-muted border rounded p-4 text-center">Chưa có tọa độ GPS</div>';
  return `<div class="row g-4"><div class="col-lg-4">${image}${statusBadge(item)}</div><div class="col-lg-8"><div class="row g-3"><div class="col-md-6"><strong>Mã công trình</strong><div>${safe(item.asset_code)}</div></div><div class="col-md-6"><strong>Loại công trình</strong><div>${safe(item.type_name || '')}</div></div><div class="col-md-6"><strong>Khu vực</strong><div>${safe(item.area_code || 'Chưa cập nhật')}</div></div><div class="col-md-6"><strong>Địa chỉ</strong><div>${safe(item.address || 'Chưa cập nhật')}</div></div><div class="col-md-6"><strong>Diện tích khuôn viên</strong><div>${formatArea(item.campus_area)}</div></div><div class="col-md-6"><strong>Diện tích xây dựng</strong><div>${formatArea(item.building_area)}</div></div><div class="col-md-6"><strong>Đơn vị quản lý</strong><div>${safe(item.managing_unit || 'Chưa cập nhật')}</div></div><div class="col-md-6"><strong>Người phụ trách</strong><div>${safe(item.manager_name || 'Chưa cập nhật')}</div></div><div class="col-md-6"><strong>Số điện thoại</strong><div>${safe(item.manager_phone || 'Chưa cập nhật')}</div></div><div class="col-md-6"><strong>Tọa độ GPS</strong><div>${item.latitude && item.longitude ? `${item.latitude}, ${item.longitude}` : 'Chưa cập nhật'}</div></div><div class="col-12"><strong>Mô tả</strong><p class="mb-0">${safe(item.description || 'Chưa cập nhật')}</p></div><div class="col-12"><strong>Ghi chú</strong><p class="mb-0">${safe(item.note || 'Không có')}</p></div><div class="col-12"><strong>Bản đồ vị trí</strong><div class="mt-2">${map}</div></div></div></div></div>`;
}

async function openPublicAssetForm(id = null) {
  if (id && !can('update')) return toast('Tài khoản hiện tại không có quyền sửa công trình', 'warning');
  if (!id && !can('create')) return toast('Tài khoản hiện tại không có quyền thêm công trình', 'warning');
  await ensureCatalogs();
  const form = $('#publicAssetForm');
  form?.reset();
  if (id) {
    const item = await request(`${API}/${id}`);
    state.current = item;
    Object.keys(item).forEach(key => { if (form.elements[key]) form.elements[key].value = item[key] ?? ''; });
    if (form.elements.type_id) form.elements.type_id.value = item.type_id || '';
  } else {
    if (form?.elements.status) form.elements.status.value = 'ACTIVE';
  }
  bootstrap.Modal.getOrCreateInstance($('#publicAssetFormModal')).show();
}

async function savePublicAsset(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const data = Object.fromEntries(new FormData(form).entries());
  const id = data.id;
  const method = id ? 'PUT' : 'POST';
  const url = id ? `${API}/${id}` : API;
  const item = await request(url, { method, body: data });
  bootstrap.Modal.getOrCreateInstance($('#publicAssetFormModal')).hide();
  toast('Đã lưu công trình công cộng', 'success');
  await loadPublicAssets();
  refreshGisLayer();
  state.current = item;
}

async function deletePublicAsset(id) {
  if (!can('delete')) return toast('Tài khoản hiện tại không có quyền xóa công trình', 'warning');
  if (!confirm('Bạn có chắc chắn muốn xóa công trình công cộng này?')) return;
  await request(`${API}/${id}`, { method: 'DELETE' });
  toast('Đã xóa công trình công cộng', 'success');
  await loadPublicAssets();
  refreshGisLayer();
}

function useCurrentGps() {
  if (!navigator.geolocation) return toast('Thiết bị không hỗ trợ định vị GPS', 'warning');
  navigator.geolocation.getCurrentPosition(pos => {
    const form = $('#publicAssetForm');
    if (!form) return;
    form.elements.latitude.value = pos.coords.latitude.toFixed(8);
    form.elements.longitude.value = pos.coords.longitude.toFixed(8);
    form.elements.gps_accuracy.value = pos.coords.accuracy ? pos.coords.accuracy.toFixed(2) : '';
  }, error => toast(error.message || 'Không lấy được GPS hiện tại', 'danger'), { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 });
}

function wrapGisLoader() {
  if (window.__publicAssetsGisWrapped) return;
  window.__publicAssetsGisWrapped = true;
  const original = window.loadGisMap;
  if (typeof original === 'function') {
    window.loadGisMap = async function (...args) {
      const result = await original.apply(this, args);
      scheduleGisLayer();
      return result;
    };
  }
}

function scheduleGisLayer() {
  setTimeout(() => refreshGisLayer(), 250);
}

async function refreshGisLayer() {
  const app = window.App;
  if (!app?.gis?.map || !window.L) return;
  const map = app.gis.map;
  if (!app.gis.publicAssetLayer) app.gis.publicAssetLayer = L.layerGroup().addTo(map);
  const layer = app.gis.publicAssetLayer;
  layer.clearLayers();
  if (!can('read')) return;
  const data = await request(API + '/gis', { cacheTtl: 15000 });
  (data.items || []).forEach(item => {
    const marker = L.marker([item.latitude, item.longitude], { icon: markerIcon(item) });
    marker.bindPopup(gisPopup(item), { maxWidth: 320, closeButton: true, autoPan: true });
    marker.addTo(layer);
  });
}

function markerIcon(item) {
  const color = statusColor(item.status);
  const html = `<span style="width:34px;height:34px;border-radius:50%;background:${color};color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 20px rgba(0,0,0,.25);border:2px solid #fff"><i class="fa-solid ${safe(item.type_icon || 'fa-building-columns')}"></i></span>`;
  return L.divIcon({ className: 'public-asset-gis-icon', html, iconSize: [34, 34], iconAnchor: [17, 17], popupAnchor: [0, -18] });
}

function statusColor(status) {
  return { ACTIVE: '#16a34a', REPAIRING: '#f59e0b', SUSPENDED: '#64748b', INACTIVE: '#374151' }[status] || '#1976d2';
}

function gisPopup(item) {
  const photo = item.cover_photo_url ? `<img src="${safe(item.cover_photo_url)}" alt="" style="width:100%;height:120px;object-fit:cover;border-radius:8px;margin-bottom:8px">` : '';
  const directions = item.latitude && item.longitude ? `https://www.google.com/maps/dir/?api=1&destination=${item.latitude},${item.longitude}` : '#';
  return `<div style="min-width:240px">${photo}<strong>${safe(item.asset_name)}</strong><div style="font-size:12px;color:#64748b;margin:4px 0">${safe(item.type_name || '')} · ${safe(item.status_label || '')}</div><div style="font-size:13px;margin-bottom:4px"><i class="fa-solid fa-location-dot"></i> ${safe(item.address || 'Chưa cập nhật địa chỉ')}</div><div style="font-size:13px;margin-bottom:4px"><i class="fa-solid fa-ruler-combined"></i> ${formatArea(item.campus_area)}</div><div style="font-size:13px;margin-bottom:8px"><i class="fa-solid fa-user"></i> ${safe(item.manager_name || 'Chưa cập nhật')} ${item.manager_phone ? ' · ' + safe(item.manager_phone) : ''}</div><div class="d-flex gap-2"><button type="button" class="btn btn-sm btn-primary" onclick="window.openPublicAssetDetail(${item.id})">Chi tiết</button><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="${directions}">Chỉ đường</a></div></div>`;
}

window.loadPublicAssets = loadPublicAssets;
window.openPublicAssetDetail = openPublicAssetDetail;
window.openPublicAssetForm = openPublicAssetForm;
window.refreshPublicAssetGisLayer = refreshGisLayer;

document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();