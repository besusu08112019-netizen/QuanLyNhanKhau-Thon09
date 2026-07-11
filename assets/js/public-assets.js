(function () {
  'use strict';

  const API = '/api/public-assets';
  const EMPTY = '--';
  const NO_DATA = 'Chưa có dữ liệu';
  const state = { page: 1, pageSize: 20, search: '', type_id: '', area_code: '', status: '', located: '', area_min: '', area_max: '', sort: 'asset_code', direction: 'ASC', catalogs: null, inventoryCatalogs: null, current: null, inventoryItems: [] };

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const safe = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  const number = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
  const toast = (message, type = 'info') => typeof window.showToast === 'function' ? window.showToast(message, type) : console[type === 'danger' ? 'error' : 'log'](message);
  const can = action => typeof window.thon09CanAccess === 'function' ? window.thon09CanAccess('public_assets', action) : true;

  const request = (url, options = {}) => {
    if (typeof window.api === 'function') return window.api(url, options);
    return fetch(url, options).then(response => response.json()).then(payload => {
      if (payload?.ok === false || payload?.success === false) throw new Error(payload?.error?.message || payload?.message || 'Request failed');
      return payload?.data ?? payload;
    });
  };

  function run(fn) { Promise.resolve().then(fn).catch(error => toast(error.message || 'Thao tác không thành công', 'danger')); }
  function text(value, empty = EMPTY) { const raw = String(value ?? '').trim(); return raw === '' ? empty : raw; }
  function address(value) { const raw = text(value); return raw === EMPTY ? raw : raw.replace(/\s*,\s*/g, ', ').replace(/\s+/g, ' ').trim(); }
  function area(value, empty = EMPTY) { const n = Number(value); return Number.isFinite(n) && n > 0 ? number(n) + ' m²' : empty; }
  function year(value) { const n = Number(value); return Number.isInteger(n) && n > 0 ? String(n) : EMPTY; }
  function gpsText(item) {
    const lat = Number(item?.latitude);
    const lng = Number(item?.longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return EMPTY;
    return `${lat.toFixed(6)}, ${lng.toFixed(6)}${item.gps_updated_at ? ' - Cập nhật: ' + item.gps_updated_at : ''}`;
  }
  function managerSummary(item) {
    const unit = text(item?.managing_unit);
    const name = text(item?.manager_name);
    const position = text(item?.manager_position, '');
    const phone = text(item?.manager_phone, '');
    const primary = unit !== EMPTY ? unit : name;
    const secondary = [name !== primary ? name : '', position, phone].filter(Boolean).join(' - ');
    return { primary, secondary };
  }
  function debounce(fn, delay) { let timer; return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); }; }

  function init() {
    bind();
    wrapGisLoader();
    if (window.App?.screen === 'publicAssets') run(load);
    if (window.App?.screen === 'gis') scheduleGisLayer();
  }

  function bind() {
    $('#publicAssetsSearchBtn')?.addEventListener('click', () => run(() => { state.page = 1; readFilters(); return load(); }));
    $('#publicAssetsResetBtn')?.addEventListener('click', () => run(reset));
    $('#publicAssetsSearch')?.addEventListener('input', debounce(() => run(() => { state.page = 1; readFilters(); return load(); }), 350));
    ['publicAssetsTypeFilter', 'publicAssetsAreaFilter', 'publicAssetsStatusFilter', 'publicAssetsLocatedFilter', 'publicAssetsAreaMin', 'publicAssetsAreaMax'].forEach(id => $('#' + id)?.addEventListener('change', () => run(() => { state.page = 1; readFilters(); return load(); })));
    $('#publicAssetsPageSize')?.addEventListener('change', event => run(() => { state.pageSize = Number(event.target.value || 20); state.page = 1; return load(); }));
    $('#publicAssetsAddBtn')?.addEventListener('click', () => run(() => openForm()));
    $('#publicAssetForm')?.addEventListener('submit', event => {
      event.preventDefault();
      run(() => save(event.currentTarget));
    });
    $('#publicAssetUseGpsBtn')?.addEventListener('click', useGps);
    $('#publicAssetPickMapBtn')?.addEventListener('click', pickMap);
    $('#publicAssetPhotoFile')?.addEventListener('change', previewFile);
    $('#publicAssetDeletePhotoBtn')?.addEventListener('click', () => run(deletePhoto));
    $('#publicAssetDetailEditBtn')?.addEventListener('click', () => state.current?.id && run(() => openForm(state.current.id)));
    $('#publicAssetDetailBody')?.addEventListener('click', detailAction);
    $('#publicAssetInventoryForm')?.addEventListener('submit', event => {
      event.preventDefault();
      run(() => saveInventory(event.currentTarget));
    });
    $('#publicAssetsRows')?.addEventListener('click', rowAction);
    $('#publicAssetsPager')?.addEventListener('click', pagerAction);
    $('#publicAssetsMiniDashboard')?.addEventListener('click', dashboardAction);
    $$('[data-public-asset-sort]').forEach(th => th.addEventListener('click', () => run(() => sortBy(th.dataset.publicAssetSort))));
    document.addEventListener('thon09:screen-change', event => {
      if (event.detail?.screen === 'publicAssets') run(load);
      if (event.detail?.screen === 'gis') scheduleGisLayer();
    });
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

  async function reset() {
    ['publicAssetsSearch', 'publicAssetsTypeFilter', 'publicAssetsAreaFilter', 'publicAssetsStatusFilter', 'publicAssetsLocatedFilter', 'publicAssetsAreaMin', 'publicAssetsAreaMax'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
    Object.assign(state, { page: 1, search: '', type_id: '', area_code: '', status: '', located: '', area_min: '', area_max: '', sort: 'asset_code', direction: 'ASC' });
    await load();
  }

  async function catalogs() {
    if (state.catalogs) return state.catalogs;
    state.catalogs = await request(API + '/catalogs', { cacheTtl: 60000 });
    fill($('#publicAssetsTypeFilter'), state.catalogs.types, 'Tất cả');
    fill($('#publicAssetsAreaFilter'), state.catalogs.areas, 'Tất cả');
    fill($('#publicAssetsStatusFilter'), state.catalogs.statuses, 'Tất cả');
    fill($('#publicAssetTypeSelect'), state.catalogs.types, 'Chọn loại');
    fill($('#publicAssetStatusSelect'), state.catalogs.statuses, 'Chọn trạng thái');
    return state.catalogs;
  }

  async function inventoryCatalogs() {
    if (state.inventoryCatalogs) return state.inventoryCatalogs;
    state.inventoryCatalogs = await request(API + '/inventory/catalogs', { cacheTtl: 60000 });
    fill($('#publicAssetInventoryGroupSelect'), state.inventoryCatalogs.groups, 'Chọn nhóm');
    fill($('#publicAssetInventoryConditionSelect'), state.inventoryCatalogs.conditions, 'Chọn tình trạng');
    return state.inventoryCatalogs;
  }

  function fill(select, items = [], first = '') {
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

  function params() {
    readFilters();
    return new URLSearchParams({ page: state.page, pageSize: state.pageSize, search: state.search, type_id: state.type_id, area_code: state.area_code, status: state.status, located: state.located, area_min: state.area_min, area_max: state.area_max, sort: state.sort, direction: state.direction });
  }

  async function load() {
    if (!$('#publicAssetsScreen')) return;
    await catalogs();
    const query = params();
    const [list, dashboard] = await Promise.all([request(API + '?' + query), request(API + '/dashboard?' + query, { cacheTtl: 15000 })]);
    renderDashboard(dashboard);
    renderRows(list);
    renderPager(list);
    window.thon09ApplyAccessControls?.();
  }

  function renderDashboard(data = {}) {
    const host = $('#publicAssetsMiniDashboard');
    if (!host) return;
    const metrics = data.metrics || {};
    const cards = [
      ['Tổng công trình', 'fa-building-columns', number(metrics.total_assets || 0), ''],
      ['Đang sử dụng', 'fa-circle-check', number(metrics.active_assets || 0), 'status:ACTIVE'],
      ['Đã định vị GPS', 'fa-location-dot', number(metrics.located_assets || 0), 'located:1'],
      ['Tổng diện tích khuôn viên', 'fa-ruler-combined', area(metrics.total_campus_area || 0, '0 m²'), '']
    ];
    host.innerHTML = cards.map(card => `<article class="agri-kpi-card" ${card[3] ? `data-public-asset-filter="${card[3]}"` : ''}><span><i class="fa-solid ${card[1]}"></i></span><div><strong>${card[2]}</strong><small>${safe(card[0])}</small></div></article>`).join('') + breakdown(data.charts || {});
  }

  function breakdown(charts) {
    const areaItems = (charts.area_by_category || charts.area_by_type || []).slice(0, 4).map(row => `<span class="badge bg-light text-dark border me-1 mb-1">${safe(text(row.label, NO_DATA))}: ${area(row.campus_area)}</span>`).join('');
    const typeItems = (charts.types || []).slice(0, 4).map(row => `<span class="badge bg-light text-dark border me-1 mb-1">${safe(text(row.label, NO_DATA))}: ${number(row.value)}</span>`).join('');
    if (!areaItems && !typeItems) return '';
    return `<article class="agri-kpi-card" style="grid-column:span 2"><span><i class="fa-solid fa-chart-pie"></i></span><div><strong>Thống kê</strong><small>${areaItems || typeItems}</small></div></article>`;
  }

  function photo(item) {
    return item.cover_photo_url
      ? `<img src="${safe(item.cover_photo_url)}" alt="Ảnh công trình" style="width:52px;height:42px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb">`
      : `<span class="d-inline-flex align-items-center justify-content-center bg-light border rounded" style="width:52px;height:42px"><i class="fa-solid ${safe(item.type_icon || 'fa-building-columns')} text-secondary"></i></span>`;
  }
  function areaHtml(item) { return `KV: ${area(item.campus_area)}<br><small class="text-muted">XD: ${area(item.building_area)}</small>`; }
  function statusBadge(item) { const tones = { ACTIVE: 'success', REPAIRING: 'warning', SUSPENDED: 'secondary', INACTIVE: 'dark' }; return `<span class="badge bg-${tones[item.status] || 'secondary'}">${safe(item.status_label || item.status)}</span>`; }

  function renderRows(data = {}) {
    const rows = data.items || [];
    const tbody = $('#publicAssetsRows');
    if (!tbody) return;
    const total = $('#publicAssetsTotalCount');
    if (total) total.textContent = `Tổng số: ${number(data.total || 0)} công trình`;
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Chưa có công trình phù hợp bộ lọc.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map((item, index) => {
      const actions = [`<button class="btn btn-sm btn-outline-primary" data-action="detail" data-id="${item.id}" title="Chi tiết"><i class="fa-solid fa-eye"></i></button>`, can('update') ? `<button class="btn btn-sm btn-outline-secondary" data-action="edit" data-id="${item.id}" title="Sửa"><i class="fa-solid fa-pen"></i></button>` : '', can('delete') ? `<button class="btn btn-sm btn-outline-danger" data-action="delete" data-id="${item.id}" title="Xóa"><i class="fa-solid fa-trash"></i></button>` : ''].filter(Boolean).join(' ');
      const manager = managerSummary(item);
      return `<tr><td>${(state.page - 1) * state.pageSize + index + 1}</td><td>${photo(item)}</td><td><strong>${safe(text(item.asset_code))}</strong></td><td><div class="fw-semibold">${safe(text(item.asset_name))}</div><small class="text-muted">${safe(address(item.address))}</small></td><td>${safe(text(item.type_name))}<br><small class="text-muted">${safe(text(item.category))}</small></td><td>${safe(text(item.area_code))}</td><td>${areaHtml(item)}</td><td>${safe(manager.primary)}${manager.secondary ? `<br><small class="text-muted">${safe(manager.secondary)}</small>` : ''}</td><td>${statusBadge(item)}</td><td class="text-end">${actions}</td></tr>`;
    }).join('');
  }

  function renderPager(data = {}) {
    const host = $('#publicAssetsPager');
    const totalPages = Number(data.totalPages || 1);
    const page = Number(data.page || state.page || 1);
    if (!host) return;
    state.page = page;
    if (totalPages <= 1) { host.innerHTML = ''; return; }
    const buttons = [`<button class="btn btn-sm btn-outline-secondary" data-page="${Math.max(1, page - 1)}" ${page <= 1 ? 'disabled' : ''}>Trước</button>`];
    for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) buttons.push(`<button class="btn btn-sm ${i === page ? 'btn-primary' : 'btn-outline-secondary'}" data-page="${i}">${i}</button>`);
    buttons.push(`<button class="btn btn-sm btn-outline-secondary" data-page="${Math.min(totalPages, page + 1)}" ${page >= totalPages ? 'disabled' : ''}>Sau</button>`);
    host.innerHTML = `<div class="d-flex gap-2 justify-content-end align-items-center mt-3">${buttons.join('')}</div>`;
  }

  function pagerAction(event) { const button = event.target.closest('[data-page]'); if (!button || button.disabled) return; run(() => { state.page = Number(button.dataset.page || 1); return load(); }); }
  function rowAction(event) { const button = event.target.closest('[data-action]'); if (!button) return; const id = Number(button.dataset.id); if (button.dataset.action === 'detail') run(() => openDetail(id)); if (button.dataset.action === 'edit') run(() => openForm(id)); if (button.dataset.action === 'delete') run(() => remove(id)); }
  function dashboardAction(event) { const card = event.target.closest('[data-public-asset-filter]'); if (!card) return; const [key, value] = card.dataset.publicAssetFilter.split(':'); const target = key === 'status' ? $('#publicAssetsStatusFilter') : $('#publicAssetsLocatedFilter'); if (target) target.value = value; run(() => { state.page = 1; readFilters(); return load(); }); }
  async function sortBy(field) { state.direction = state.sort === field && state.direction === 'ASC' ? 'DESC' : 'ASC'; state.sort = field; await load(); }

  async function openDetail(id) {
    await catalogs();
    const item = await request(`${API}/${id}`);
    state.current = item;
    $('#publicAssetDetailTitle') && ($('#publicAssetDetailTitle').textContent = item.asset_name || 'Chi tiết công trình');
    $('#publicAssetDetailSubtitle') && ($('#publicAssetDetailSubtitle').textContent = [item.asset_code, item.type_name, item.status_label].filter(Boolean).join(' · '));
    const body = $('#publicAssetDetailBody');
    if (body) body.innerHTML = detail(item);
    $('#publicAssetDetailEditBtn')?.classList.toggle('d-none', !can('update'));
    bootstrap.Modal.getOrCreateInstance($('#publicAssetDetailModal')).show();
    if (inventoryAllowed(item)) run(() => loadInventory(item.id));
  }

  function detail(item) {
    const image = item.cover_photo_url ? `<img src="${safe(item.cover_photo_url)}" class="img-fluid rounded border mb-3" style="max-height:260px;object-fit:cover;width:100%" alt="Ảnh công trình">` : '<div class="bg-light border rounded d-flex align-items-center justify-content-center mb-3" style="height:180px"><i class="fa-solid fa-building-columns fa-3x text-secondary"></i></div>';
    const hasGps = Number.isFinite(Number(item.latitude)) && Number.isFinite(Number(item.longitude));
    const map = hasGps ? `<iframe title="Vị trí công trình" src="https://www.openstreetmap.org/export/embed.html?bbox=${Number(item.longitude) - 0.002}%2C${Number(item.latitude) - 0.002}%2C${Number(item.longitude) + 0.002}%2C${Number(item.latitude) + 0.002}&marker=${item.latitude}%2C${item.longitude}" style="width:100%;height:260px;border:1px solid #d1d5db;border-radius:8px"></iframe>` : '<div class="text-muted border rounded p-4 text-center">Chưa có tọa độ GPS</div>';
    const field = (label, value) => `<div class="col-md-6"><strong>${safe(label)}</strong><div>${safe(text(value))}</div></div>`;
    const overview = `<div class="row g-4"><div class="col-lg-4">${image}${statusBadge(item)}</div><div class="col-lg-8"><div class="row g-3">${field('Mã công trình', item.asset_code)}${field('Loại', item.type_name)}${field('Khu vực', item.area_code)}${field('Địa chỉ', address(item.address))}${field('Diện tích khuôn viên', area(item.campus_area))}${field('Diện tích xây dựng', area(item.building_area))}${field('Năm xây dựng', year(item.construction_year))}${field('Năm đưa vào sử dụng', year(item.operation_year))}${field('GPS', gpsText(item))}${field('Đơn vị quản lý', item.managing_unit)}${field('Người quản lý', item.manager_name)}${field('Chức vụ', item.manager_position)}${field('Điện thoại', item.manager_phone)}<div class="col-12"><strong>Mô tả</strong><p>${safe(text(item.description, NO_DATA))}</p></div><div class="col-12"><strong>Ghi chú</strong><p>${safe(text(item.note, NO_DATA))}</p></div><div class="col-12"><strong>Bản đồ vị trí</strong><div class="mt-2">${map}</div></div></div></div></div>`;
    if (!inventoryAllowed(item)) return overview;
    return `<ul class="nav nav-tabs agri-tabs" role="tablist"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#publicAssetOverviewTab" type="button">Tổng quan</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#publicAssetInventoryTab" type="button">Kiểm kê tài sản</button></li></ul><div class="tab-content agri-tab-content pt-3"><div id="publicAssetOverviewTab" class="tab-pane fade show active">${overview}</div><div id="publicAssetInventoryTab" class="tab-pane fade"><div id="publicAssetInventoryPanel" data-asset-id="${item.id}"><div class="text-muted py-3">Đang tải kiểm kê tài sản...</div></div></div></div>`;
  }

  function inventoryAllowed(item) { return item?.inventory_enabled !== false && item?.inventory_allowed !== false; }

  async function loadInventory(assetId) {
    await inventoryCatalogs();
    const data = await request(`${API}/${assetId}/inventory`, { cacheTtl: 1000 });
    state.inventoryItems = data.items || [];
    renderInventory(data);
  }

  function renderInventory(data = {}) {
    const host = $('#publicAssetInventoryPanel');
    if (!host) return;
    if (data.enabled === false) {
      host.innerHTML = `<div class="alert alert-light border mb-0">${safe(data.message || 'Không áp dụng kiểm kê tài sản cho loại công trình này.')}</div>`;
      return;
    }
    const summary = data.summary || {};
    const rows = data.items || [];
    const groupBadges = (summary.by_group || []).slice(0, 4).map(row => `<span class="badge bg-light text-dark border me-1 mb-1">${safe(text(row.label, NO_DATA))}: ${number(row.value)}</span>`).join('');
    const conditionBadges = (summary.by_condition || []).slice(0, 4).map(row => `<span class="badge bg-light text-dark border me-1 mb-1">${safe(text(row.label, NO_DATA))}: ${number(row.value)}</span>`).join('');
    const addButton = can('update') ? `<button type="button" class="btn btn-sm btn-success" data-inventory-action="add"><i class="fa-solid fa-plus"></i> Thêm tài sản</button>` : '';
    const body = rows.length ? rows.map(item => {
      const actions = can('update') ? `<button type="button" class="btn btn-sm btn-outline-secondary" data-inventory-action="edit" data-id="${item.id}"><i class="fa-solid fa-pen"></i></button> <button type="button" class="btn btn-sm btn-outline-danger" data-inventory-action="delete" data-id="${item.id}"><i class="fa-solid fa-trash"></i></button>` : '';
      return `<tr><td><strong>${safe(text(item.item_name))}</strong><br><small class="text-muted">${safe(text(item.inventory_code))}</small></td><td>${safe(text(item.group_name))}</td><td>${number(item.quantity)} ${safe(text(item.unit, ''))}</td><td>${safe(text(item.condition_label || item.condition_status))}</td><td>${safe(text(item.start_use_date))}</td><td class="text-end">${actions}</td></tr>`;
    }).join('') : '<tr><td colspan="6" class="text-center text-muted py-4">Chưa có tài sản kiểm kê.</td></tr>';
    host.innerHTML = `<div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-3"><div><div class="fw-semibold">Tổng số tài sản: ${number(summary.total_items || 0)}</div><div class="small text-muted">Tổng số lượng: ${number(summary.total_quantity || 0)}</div></div>${addButton}</div><div class="mb-3">${groupBadges}${conditionBadges}</div><div class="table-responsive"><table class="table module-table align-middle mb-0"><thead><tr><th>Tên tài sản</th><th>Nhóm</th><th>Số lượng</th><th>Tình trạng</th><th>Ngày sử dụng</th><th class="text-end">Thao tác</th></tr></thead><tbody>${body}</tbody></table></div>`;
  }

  function detailAction(event) {
    const button = event.target.closest('[data-inventory-action]');
    if (!button) return;
    const action = button.dataset.inventoryAction;
    if (action === 'add') run(() => openInventoryForm());
    if (action === 'edit') run(() => openInventoryForm(Number(button.dataset.id)));
    if (action === 'delete') run(() => deleteInventory(Number(button.dataset.id)));
  }
  async function openInventoryForm(id = null) {
    if (!can('update')) return toast('Không có quyền cập nhật kiểm kê tài sản', 'warning');
    if (!state.current?.id) return toast('Chưa chọn công trình', 'warning');
    await inventoryCatalogs();
    const form = $('#publicAssetInventoryForm');
    form?.reset();
    if (!form) return;
    form.elements.public_asset_id.value = state.current.id;
    form.elements.id.value = '';
    $('#publicAssetInventoryPhotoPreview') && ($('#publicAssetInventoryPhotoPreview').innerHTML = '<span class="text-muted small">Chưa có ảnh tài sản</span>');
    if (id) {
      const item = state.inventoryItems.find(row => Number(row.id) === Number(id));
      if (!item) return toast('Không tìm thấy tài sản kiểm kê', 'warning');
      Object.keys(item).forEach(key => { if (form.elements[key]) form.elements[key].value = item[key] ?? ''; });
      form.elements.id.value = item.id;
      if (form.elements.group_id) form.elements.group_id.value = item.group_id || '';
      if (form.elements.condition_status) form.elements.condition_status.value = item.condition_status || 'IN_USE';
      if (item.photo_url && $('#publicAssetInventoryPhotoPreview')) $('#publicAssetInventoryPhotoPreview').innerHTML = `<img src="${safe(item.photo_url)}" style="width:100%;max-height:160px;object-fit:cover" alt="Ảnh tài sản">`;
    } else {
      if (form.elements.quantity) form.elements.quantity.value = '1';
      if (form.elements.condition_status) form.elements.condition_status.value = 'IN_USE';
    }
    bootstrap.Modal.getOrCreateInstance($('#publicAssetInventoryModal')).show();
  }

  async function saveInventory(form) {
    if (!state.current?.id) throw new Error('Chưa chọn công trình');
    const data = Object.fromEntries(new FormData(form).entries());
    const id = data.id;
    delete data.id;
    delete data.public_asset_id;
    const item = await request(id ? `${API}/${state.current.id}/inventory/${id}` : `${API}/${state.current.id}/inventory`, { method: id ? 'PUT' : 'POST', body: data });
    const file = $('#publicAssetInventoryPhotoFile')?.files?.[0];
    if (file) await uploadInventoryPhoto(state.current.id, item.id, file);
    bootstrap.Modal.getOrCreateInstance($('#publicAssetInventoryModal')).hide();
    toast('Đã lưu tài sản kiểm kê', 'success');
    await loadInventory(state.current.id);
  }

  async function uploadInventoryPhoto(assetId, itemId, file) {
    const body = new FormData();
    body.append('file', file);
    const response = await fetch(`${API}/${assetId}/inventory/${itemId}/photo`, { method: 'POST', headers: authHeaders(), body, cache: 'no-store' });
    const payload = await response.json().catch(() => null);
    if (!response.ok || payload?.ok === false || payload?.success === false) throw new Error(payload?.error?.message || payload?.message || 'Không upload được ảnh tài sản');
    return payload?.data?.item || payload?.item || null;
  }

  async function deleteInventory(id) {
    if (!can('update')) return toast('Không có quyền xóa tài sản kiểm kê', 'warning');
    if (!state.current?.id) return;
    if (!confirm('Xóa tài sản kiểm kê này?')) return;
    await request(`${API}/${state.current.id}/inventory/${id}`, { method: 'DELETE' });
    toast('Đã xóa tài sản kiểm kê', 'success');
    await loadInventory(state.current.id);
  }

  async function openForm(id = null) {
    if (id && !can('update')) return toast('Không có quyền sửa', 'warning');
    if (!id && !can('create')) return toast('Không có quyền thêm', 'warning');
    await catalogs();
    const form = $('#publicAssetForm');
    form?.reset();
    updatePhoto(null);
    updateGps(null);
    if (id) {
      const item = await request(`${API}/${id}`);
      state.current = item;
      Object.keys(item).forEach(key => { if (form.elements[key]) form.elements[key].value = item[key] ?? ''; });
      if (form.elements.type_id) form.elements.type_id.value = item.type_id || '';
      updatePhoto(item.cover_photo_url);
      updateGps(item);
    } else {
      state.current = null;
      if (form?.elements.status) form.elements.status.value = 'ACTIVE';
    }
    bootstrap.Modal.getOrCreateInstance($('#publicAssetFormModal')).show();
  }

  async function save(form) {
    const data = Object.fromEntries(new FormData(form).entries());
    delete data.asset_code;
    const id = data.id;
    const item = await request(id ? `${API}/${id}` : API, { method: id ? 'PUT' : 'POST', body: data });
    let saved = item;
    const file = $('#publicAssetPhotoFile')?.files?.[0];
    if (file) saved = await uploadPhoto(item.id, file);
    bootstrap.Modal.getOrCreateInstance($('#publicAssetFormModal')).hide();
    toast('Đã lưu công trình', 'success');
    await load();
    run(refreshGisLayer);
    state.current = saved;
  }

  function authHeaders() { const headers = {}; if (window.App?.token) headers.Authorization = `Bearer ${window.App.token}`; if (window.App?.csrfToken) headers['X-CSRF-Token'] = window.App.csrfToken; return headers; }
  async function uploadPhoto(id, file) { const body = new FormData(); body.append('file', file); const response = await fetch(`${API}/${id}/photo`, { method: 'POST', headers: authHeaders(), body, cache: 'no-store' }); const payload = await response.json().catch(() => null); if (!response.ok || payload?.ok === false || payload?.success === false) throw new Error(payload?.error?.message || payload?.message || 'Không upload được ảnh'); return payload?.data?.item || payload?.item || state.current; }
  async function deletePhoto() { const id = $('#publicAssetForm')?.elements.id.value || state.current?.id; if (!id) { updatePhoto(null); return; } if (!confirm('Xóa ảnh công trình này?')) return; const response = await fetch(`${API}/${id}/photo`, { method: 'DELETE', headers: authHeaders(), cache: 'no-store' }); const payload = await response.json().catch(() => null); if (!response.ok || payload?.ok === false || payload?.success === false) throw new Error(payload?.error?.message || payload?.message || 'Không xóa được ảnh'); state.current = payload?.data?.item || state.current; updatePhoto(null); const file = $('#publicAssetPhotoFile'); if (file) file.value = ''; await load(); toast('Đã xóa ảnh', 'success'); }
  function previewFile(event) { const file = event.target.files?.[0]; if (file) updatePhoto(URL.createObjectURL(file), true); }
  function updatePhoto(url, temp = false) { const host = $('#publicAssetPhotoPreview'); if (!host) return; host.innerHTML = url ? `<img src="${safe(url)}" style="width:100%;max-height:180px;object-fit:cover" alt="Ảnh công trình">` : '<span class="text-muted small">Chưa có ảnh công trình</span>'; $('#publicAssetDeletePhotoBtn')?.classList.toggle('d-none', !url || temp); if (temp) setTimeout(() => URL.revokeObjectURL(url), 5000); }
  function updateGps(item) { const host = $('#publicAssetGpsMeta'); if (!host) return; host.textContent = gpsText(item) !== EMPTY ? `Vị trí: ${gpsText(item)}` : 'Chưa có vị trí GPS'; }
  function useGps() { if (!navigator.geolocation) return toast('Thiết bị không hỗ trợ GPS', 'warning'); navigator.geolocation.getCurrentPosition(position => { const form = $('#publicAssetForm'); if (!form) return; form.elements.latitude.value = position.coords.latitude.toFixed(8); form.elements.longitude.value = position.coords.longitude.toFixed(8); form.elements.gps_accuracy.value = position.coords.accuracy ? position.coords.accuracy.toFixed(2) : ''; updateGps({ latitude: form.elements.latitude.value, longitude: form.elements.longitude.value, gps_updated_at: 'sẽ cập nhật khi lưu' }); }, error => toast(error.message || 'Không lấy được GPS', 'danger'), { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 }); }
  function pickMap() { const activate = () => { const map = window.App?.gis?.map; if (!map) return toast('Bản đồ GIS chưa sẵn sàng', 'warning'); toast('Click một điểm trên bản đồ để chọn vị trí', 'info'); map.once('click', event => { const form = $('#publicAssetForm'); if (!form) return; form.elements.latitude.value = event.latlng.lat.toFixed(8); form.elements.longitude.value = event.latlng.lng.toFixed(8); updateGps({ latitude: form.elements.latitude.value, longitude: form.elements.longitude.value, gps_updated_at: 'sẽ cập nhật khi lưu' }); window.switchScreen?.('publicAssets'); bootstrap.Modal.getOrCreateInstance($('#publicAssetFormModal')).show(); }); }; bootstrap.Modal.getOrCreateInstance($('#publicAssetFormModal')).hide(); window.switchScreen?.('gis'); setTimeout(activate, 700); }
  async function remove(id) { if (!can('delete')) return toast('Không có quyền xóa', 'warning'); if (!confirm('Xóa công trình này?')) return; await request(`${API}/${id}`, { method: 'DELETE' }); toast('Đã xóa công trình', 'success'); await load(); run(refreshGisLayer); }
  function wrapGisLoader() { if (window.__publicAssetsGisWrapped) return; window.__publicAssetsGisWrapped = true; const original = window.loadGisMap; if (typeof original === 'function') window.loadGisMap = async function (...args) { const result = await original.apply(this, args); scheduleGisLayer(); return result; }; }
  function scheduleGisLayer() { setTimeout(() => run(refreshGisLayer), 250); }
  async function refreshGisLayer() { const app = window.App; if (!app?.gis?.map || !window.L) return; const map = app.gis.map; if (!app.gis.publicAssetLayer) app.gis.publicAssetLayer = L.layerGroup().addTo(map); const layer = app.gis.publicAssetLayer; layer.clearLayers(); if (!can('read')) return; const data = await request(API + '/gis', { cacheTtl: 15000 }); (data.items || []).forEach(item => L.marker([item.latitude, item.longitude], { icon: markerIcon(item) }).bindPopup(gisPopup(item), { maxWidth: 320, closeButton: true, autoPan: true }).addTo(layer)); }
  function markerIcon(item) { const color = { ACTIVE: '#16a34a', REPAIRING: '#f59e0b', SUSPENDED: '#64748b', INACTIVE: '#374151' }[item.status] || '#1976d2'; return L.divIcon({ className: 'public-asset-gis-icon', html: `<span style="width:34px;height:34px;border-radius:50%;background:${color};color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 20px rgba(0,0,0,.25);border:2px solid #fff"><i class="fa-solid ${safe(item.type_icon || 'fa-building-columns')}"></i></span>`, iconSize: [34, 34], iconAnchor: [17, 17], popupAnchor: [0, -18] }); }
  function gisPopup(item) { const image = item.cover_photo_url ? `<img src="${safe(item.cover_photo_url)}" style="width:100%;height:120px;object-fit:cover;border-radius:8px;margin-bottom:8px" alt="Ảnh công trình">` : ''; const directions = Number.isFinite(Number(item.latitude)) && Number.isFinite(Number(item.longitude)) ? `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(Number(item.latitude) + ',' + Number(item.longitude))}` : ''; const manager = managerSummary(item); return `<div style="min-width:240px">${image}<strong>${safe(text(item.asset_name))}</strong><div style="font-size:12px;color:#64748b;margin:4px 0">${safe(text(item.type_name))} · ${safe(text(item.status_label))}</div><div style="font-size:13px;margin-bottom:4px"><i class="fa-solid fa-location-dot"></i> ${safe(address(item.address))}</div><div style="font-size:13px;margin-bottom:4px"><i class="fa-solid fa-ruler-combined"></i> ${area(item.campus_area)}</div><div style="font-size:13px;margin-bottom:8px"><i class="fa-solid fa-building-user"></i> ${safe(manager.primary)}</div><div class="d-flex gap-2"><button type="button" class="btn btn-sm btn-primary" onclick="window.openPublicAssetDetail(${item.id})">Chi tiết</button>${directions ? `<a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="${directions}">Chỉ đường</a>` : ''}</div></div>`; }

  window.loadPublicAssets = load;
  window.openPublicAssetDetail = id => run(() => openDetail(id));
  window.openPublicAssetForm = id => run(() => openForm(id));
  window.refreshPublicAssetGisLayer = () => run(refreshGisLayer);
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();
