(() => {
  const state = { page: 1, pageSize: 20, search: '', business_type: '', economic_type: '', business_scale: '', sector: '', status: '', ocop: '', food_safety: '', social_insurance: '', located: '', sort: 'updated_at', direction: 'DESC', catalogs: null, selectedHousehold: null, householdSuggestions: [], currentDetailHouseholdId: null };
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
  const num = value => Number(value || 0).toLocaleString('vi-VN');
  const date = value => {
    if (!value) return '';
    const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
    return match ? match[3] + '/' + match[2] + '/' + match[1] : String(value);
  };
  const hasPlatformPermissionRule = (module, action) => {
    const service = window.Thon09Platform?.permissions;
    if (!service?.list) return false;
    const normalizedModule = service.normalizeModule ? service.normalizeModule(module) : module;
    const normalizedAction = service.normalizeAction ? service.normalizeAction(action) : action;
    const keys = new Set(service.list().map(item => item.key));
    return keys.has(normalizedModule + ':' + normalizedAction) || keys.has(normalizedModule + ':manage') || keys.has(normalizedModule + ':*');
  };
  const can = (module, action) => {
    const service = window.Thon09Platform?.permissions;
    if (service?.can && hasPlatformPermissionRule(module, action)) return service.can(module, action, window.App?.user);
    if (typeof window.thon09CanAccess === 'function') return window.thon09CanAccess(module, action);
    const role = String(window.App?.user?.role || '').toUpperCase();
    if (['SUPER_ADMIN', 'ADMIN'].includes(role)) return true;
    if (role === 'VIEWER') return module === 'household_business' && action === 'read';
    if (role === 'OFFICER') return module === 'household_business' && ['read', 'create', 'update'].includes(action);
    return false;
  };
  const show = (message, type = 'success') => typeof window.showToast === 'function' ? window.showToast(message, type) : console.log(message);
  const request = (url, options = {}) => (window.api || window.thon09Api)(url, options);
  const isActive = () => (window.Thon09Platform?.navigation?.current?.()?.screenId || window.App?.screen || document.querySelector('.screen.active')?.id?.replace(/Screen$/, '')) === 'businessHouseholds';
  const confirmAction = options => {
    const dialog = window.Thon09Platform?.confirmDialog;
    if (dialog?.ask) return dialog.ask(options);
    return Promise.resolve(typeof window.confirm === 'function' ? window.confirm(options.message || 'Xác nhận thao tác?') : false);
  };
  const registerModal = id => {
    const modal = $('#' + id);
    const service = window.Thon09Platform?.modals;
    if (modal && service?.registerBootstrap) service.registerBootstrap(id, '#' + id);
    return modal;
  };
  const openModal = id => {
    const service = window.Thon09Platform?.modals;
    if (service?.open && service.open(id)) return;
    window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.show();
  };
  const closeModal = id => {
    const service = window.Thon09Platform?.modals;
    if (service?.close && service.close(id)) return;
    window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.hide();
  };

  function setValue(selector, value) { const el = $(selector); if (el) el.value = value ?? ''; }
  function setText(selector, value) { const el = $(selector); if (el) el.textContent = value ?? ''; }
  function setDisabled(selector, disabled) { const el = $(selector); if (el) el.disabled = !!disabled; }
  function setFormValue(form, name, value) { if (form?.elements?.[name]) form.elements[name].value = value ?? ''; }

  function bind() {
    registerModal('businessHouseholdModal');
    $('#businessHouseholdForm')?.addEventListener('submit', save);
    $('#businessHouseholdAutocomplete')?.addEventListener('input', debounce(searchHouseholds, 250));
    document.addEventListener('pointerdown', event => { if (!event.target.closest('#businessHouseholdSuggestions') && event.target.id !== 'businessHouseholdAutocomplete') hideSuggestions(); });
    ['businessOcopCheck', 'businessSocialInsuranceCheck', 'businessFoodSafetyCheck'].forEach(id => $('#' + id)?.addEventListener('change', toggleConditionalFields));
    $('#businessActivityOwnGps')?.addEventListener('change', syncBusinessGpsSource);
    $('#businessHouseholdSearch')?.addEventListener('input', debounce(() => { state.search = $('#businessHouseholdSearch').value.trim(); state.page = 1; load(); }, 300));
    [
      ['businessHouseholdTypeFilter', 'business_type'], ['businessEconomicTypeFilter', 'economic_type'], ['businessScaleFilter', 'business_scale'],
      ['businessHouseholdSectorFilter', 'sector'], ['businessHouseholdStatusFilter', 'status'], ['businessOcopFilter', 'ocop'],
      ['businessFoodSafetyFilter', 'food_safety'], ['businessSocialInsuranceFilter', 'social_insurance'], ['businessHouseholdLocationFilter', 'located']
    ].forEach(([id, key]) => {
      const el = $('#' + id);
      if (el) el.addEventListener(el.tagName === 'INPUT' ? 'input' : 'change', debounce(() => { state[key] = el.value.trim(); state.page = 1; load(); }, 250));
    });
    $('#businessHouseholdPageSize')?.addEventListener('change', event => { state.pageSize = Number(event.target.value || 20); state.page = 1; load(); });
    registerBusinessPlatformActions();
    document.addEventListener('thon09:screen-change', event => {
      if (event.detail?.screen === 'businessHouseholds') load();
      if (event.detail?.screen === 'dashboard') setTimeout(renderDashboard, 120);
    });
    document.addEventListener('thon09:auth-state', () => { applyAccess(); addReportOptions(); if (isActive()) ensureCatalogs().catch(() => {}); renderDashboard(); });
    addReportOptions();
    applyAccess();
    if (isActive()) ensureCatalogs().catch(() => {});
    if ($('#businessHouseholdsScreen')?.classList.contains('active')) load();
  }

  function registerBusinessPlatformActions() {
    const actions = window.Thon09Platform && window.Thon09Platform.actions;
    if (!actions || typeof actions.register !== 'function') return;
    actions.register('businessHouseholds.openCreate', () => openForm());
    actions.register('businessHouseholds.reset', () => resetFilters());
    actions.register('businessHouseholds.sort', context => sortBy(context.dataset.businessSort));
    actions.register('businessHouseholds.page', context => { state.page = Number(context.dataset.businessPage || context.dataset.page || 1); load(); });
    actions.register('businessHouseholds.gps', context => handleBusinessGpsAction(context.dataset.businessGpsAction || context.dataset.action || ''));
    actions.register('businessHouseholds.selectHousehold', context => selectHousehold(state.householdSuggestions.find(item => String(item.id) === context.dataset.householdChoice)));
    actions.register('businessHouseholds.detail', context => showDetail(Number(context.dataset.householdId || context.dataset.id || 0)));
    actions.register('businessHouseholds.edit', context => openForm(Number(context.dataset.id || context.dataset.businessId || 0)));
    actions.register('businessHouseholds.create', context => openFormForHousehold(Number(context.dataset.householdId || context.dataset.id || 0)));
    actions.register('businessHouseholds.delete', context => remove(Number(context.dataset.id || context.dataset.businessId || 0)));
    actions.register('businessHouseholds.tab', context => selectBusinessDetailTab(context.dataset.tab || context.dataset.businessTab || ''));
    actions.register('businessHouseholds.activity', context => selectBusinessActivity(Number(context.dataset.index || context.dataset.activityIndex || 0)));
    actions.register('businessHouseholds.fileDelete', context => deleteBusinessFile(Number(context.dataset.businessId || 0), Number(context.dataset.fileId || 0)));
    if (typeof actions.bind === 'function') actions.bind(document);
  }

  async function ensureCatalogs() {
    if (state.catalogs) return state.catalogs;
    state.catalogs = await request('/api/household-business/catalogs', { cacheTtl: 60000 });
    fillCatalogSelect('#businessEconomicTypeSelect', state.catalogs.economic_type, 'Chọn loại hình');
    fillCatalogSelect('#businessScaleSelect', state.catalogs.business_scale, 'Chọn quy mô');
    fillCatalogSelect('#businessEconomicTypeFilter', state.catalogs.economic_type, 'Tất cả');
    fillCatalogSelect('#businessScaleFilter', state.catalogs.business_scale, 'Tất cả');
    fillCatalogSelect('#businessImageCategory', state.catalogs.image_category, 'Chọn danh mục ảnh');
    fillCatalogSelect('#businessDocumentCategory', state.catalogs.document_category, 'Chọn loại tài liệu');
    return state.catalogs;
  }

  function fillCatalogSelect(selector, items, firstLabel) {
    const select = $(selector);
    if (!select || !Array.isArray(items)) return;
    select.innerHTML = '<option value="">' + esc(firstLabel || 'Chọn') + '</option>' + items.map(item => '<option value="' + esc(item.value) + '">' + esc(item.label || item.value) + '</option>').join('');
  }

  function applyAccess() { $('#businessHouseholdAddBtn')?.classList.toggle('d-none', !can('household_business', 'create')); }

  async function load() {
    if (!can('household_business', 'read')) return;
    try {
      await ensureCatalogs();
      const params = new URLSearchParams({ page: state.page, pageSize: state.pageSize, sort: state.sort, direction: state.direction });
      ['search', 'business_type', 'economic_type', 'business_scale', 'sector', 'status', 'ocop', 'food_safety', 'social_insurance', 'located'].forEach(key => { if (state[key]) params.set(key, state[key]); });
      const data = await request('/api/household-business?' + params.toString(), { cacheTtl: 5000 });
      const items = data.items || [];
      const total = Number(data.total || 0);
      const totalEl = $('#businessHouseholdTotalCount');
      if (totalEl) totalEl.innerHTML = 'Tổng số: <strong>' + num(total) + '</strong> hộ';
      const start = (Number(data.page || state.page) - 1) * Number(data.pageSize || state.pageSize);
      const rows = $('#businessHouseholdRows');
      if (rows) rows.innerHTML = items.length ? items.map((row, index) => rowHtml(row, start + index + 1)).join('') : '<tr><td colspan="9" class="text-center text-muted py-4">Chưa có hồ sơ sản xuất & kinh doanh</td></tr>';
      renderPager(data);
      if (typeof window.thon09SyncResponsiveTableLabels === 'function') window.thon09SyncResponsiveTableLabels($('#businessHouseholdsScreen') || document);
    } catch (error) {
      show('Không tải được danh sách hộ sản xuất/kinh doanh: ' + error.message, 'danger');
    }
  }

  function uniqueValues(values) {
    return Array.from(new Set((values || []).map(value => String(value || '').trim()).filter(Boolean)));
  }

  function compactList(values, empty = '') {
    const items = uniqueValues(values);
    return items.length ? esc(items[0]) : esc(empty);
  }

  function firstValue(values, empty = '') {
    const items = uniqueValues(values);
    return items.length ? items[0] : empty;
  }

  function activityLabel(activity, fallback = '') {
    return activity.business_name || activity.economic_type || activity.sector_label || activity.production_sector || activity.business_sector || fallback || 'Hoạt động kinh tế';
  }

  function sectorCellForRow(row, activities) {
    const items = uniqueValues(activities.map(item => item.sector_label || item.economic_type || item.business_name));
    if (!items.length) return '<span class="text-muted">Chưa cập nhật</span>';
    const householdId = Number(row.household_id || row.id || 0);
    const shown = items.slice(0, 3);
    const remaining = Math.max(0, items.length - shown.length);
    let html = '<div class="business-sector-cell" title="' + esc(items.join('\n')) + '">'
      + shown.map(item => '<span class="business-sector-badge">' + esc(item) + '</span>').join('');
    if (remaining > 0) {
      html += '<button class="business-more-badge" type="button" data-platform-action="businessHouseholds.detail" data-household-id="' + householdId + '">Còn ' + remaining + ' hoạt động khác</button>';
    }
    return html + '</div>';
  }

  function businessTypeBadge(label) {
    const text = String(label || 'Chưa cập nhật').trim();
    let short = text;
    if (/sản xuấts+vàs+kinh doanh/i.test(text)) short = 'SX + KD';
    else if (/hộs+sản xuất/i.test(text)) short = 'Hộ sản xuất';
    else if (/hộs+kinh doanh/i.test(text)) short = 'Hộ kinh doanh';
    return '<span class="business-type-badge">' + esc(short) + '</span>';
  }

  function typeCell(activities) {
    const items = uniqueValues(activities.map(item => item.business_type_label));
    if (!items.length) return businessTypeBadge('Chưa cập nhật');
    return '<div class="business-type-stack" title="' + esc(items.join('\n')) + '">' + items.slice(0, 2).map(businessTypeBadge).join('') + (items.length > 2 ? '<span class="business-type-badge is-muted">Nhiều loại hình</span>' : '') + '</div>';
  }

  function statusBadge(status, label) {
    const code = String(status || '').toUpperCase();
    let cls = 'is-neutral';
    if (code === 'ACTIVE') cls = 'is-active';
    else if (code === 'SUSPENDED') cls = 'is-paused';
    else if (code === 'INACTIVE') cls = 'is-inactive';
    return '<span class="business-status-badge ' + cls + '">' + esc(label || status || 'Chưa cập nhật') + '</span>';
  }

  function statusCell(activities) {
    const first = activities[0] || {};
    const labels = uniqueValues(activities.map(item => item.status_label || item.status));
    return '<div title="' + esc(labels.join('\n')) + '">' + statusBadge(first.status, first.status_label) + '</div>';
  }

  function activityCountText(row, activities) {
    const count = Number(row.business_count || activities.length || 0);
    return count + ' hoạt động';
  }

  function activityCountBadge(row, activities) {
    return '<span class="business-count-badge">' + esc(activityCountText(row, activities)) + '</span>';
  }

  function activitiesOf(row) {
    return Array.isArray(row.activities) ? row.activities : [];
  }

  function rowHtml(row, index) {
    const activities = activitiesOf(row);
    const householdId = Number(row.household_id || row.id || 0);
    const names = uniqueValues(activities.map(item => item.business_name || row.head_citizen_name));
    const primaryName = names[0] || row.head_citizen_name || '';
    const secondaryName = names.length > 1 ? names.slice(1, 3).join(', ') : '';
    return '<tr class="business-list-row">'
      + '<td data-label="STT">' + index + '</td>'
      + '<td data-label="Mã hộ"><button class="btn btn-link p-0 fw-semibold" type="button" data-platform-action="businessHouseholds.detail" data-household-id="' + householdId + '">' + esc(row.household_code) + '</button></td>'
      + '<td data-label="Chủ hộ"><div class="business-main-person"><strong>' + esc(row.head_citizen_name || '') + '</strong><span>' + esc(row.household_code || '') + '</span></div></td>'
      + '<td data-label="Tên cơ sở"><div class="business-establishment-cell"><strong>' + esc(primaryName) + '</strong>' + (secondaryName ? '<span>' + esc(secondaryName) + '</span>' : '') + '</div></td>'
      + '<td data-label="Loại hình">' + typeCell(activities) + '</td>'
      + '<td data-label="Ngành nghề">' + sectorCellForRow(row, activities) + '</td>'
      + '<td data-label="Hoạt động">' + activityCountBadge(row, activities) + '</td>'
      + '<td data-label="Trạng thái">' + statusCell(activities) + '</td>'
      + '<td data-label="Thao tác" class="text-end business-actions-cell">' + actionButtons(row) + '</td>'
      + '</tr>';
  }

  function actionButtons(row) {
    const householdId = Number(row.household_id || row.id || 0);
    const activities = activitiesOf(row);
    const firstActivityId = Number((activities[0] || {}).id || row.business_id || row.id || 0);
    const buttons = [
      '<button class="btn btn-sm btn-outline-secondary business-icon-btn" type="button" title="Xem chi tiết" aria-label="Xem chi tiết" data-platform-action="businessHouseholds.detail" data-household-id="' + householdId + '"><i class="fa-solid fa-eye"></i></button>',
      can('household_business', 'update') && activities.length === 1 && firstActivityId ? '<button class="btn btn-sm btn-outline-primary business-icon-btn" type="button" title="Sửa hoạt động" aria-label="Sửa hoạt động" data-platform-action="businessHouseholds.edit" data-id="' + firstActivityId + '"><i class="fa-solid fa-pen-to-square"></i></button>' : '',
      can('household_business', 'create') ? '<button class="btn btn-sm btn-outline-primary business-icon-btn" type="button" title="Thêm hoạt động" aria-label="Thêm hoạt động" data-platform-action="businessHouseholds.create" data-household-id="' + householdId + '"><i class="fa-solid fa-plus"></i></button>' : '',
      can('household_business', 'delete') && activities.length === 1 && firstActivityId ? '<button class="btn btn-sm btn-outline-danger business-icon-btn" type="button" title="Xóa hoạt động" aria-label="Xóa hoạt động" data-platform-action="businessHouseholds.delete" data-id="' + firstActivityId + '"><i class="fa-solid fa-trash"></i></button>' : ''
    ];
    return '<div class="business-row-actions">' + buttons.filter(Boolean).join('') + '</div>';
  }

  function renderPager(data) {
    const host = $('#businessHouseholdPager');
    if (!host) return;
    const totalPages = Math.max(1, Number(data.totalPages || 1));
    const page = Number(data.page || state.page);
    const pages = [];
    for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) pages.push(i);
    host.innerHTML = '<button class="btn btn-sm btn-outline-secondary" ' + (page <= 1 ? 'disabled' : '') + ' data-platform-action="businessHouseholds.page" data-business-page="' + (page - 1) + '">Trước</button>'
      + pages.map(item => '<button class="btn btn-sm ' + (item === page ? 'btn-primary' : 'btn-outline-secondary') + '" data-platform-action="businessHouseholds.page" data-business-page="' + item + '">' + item + '</button>').join('')
      + '<button class="btn btn-sm btn-outline-secondary" ' + (page >= totalPages ? 'disabled' : '') + ' data-platform-action="businessHouseholds.page" data-business-page="' + (page + 1) + '">Sau</button>';
  }

  function sortBy(key) { state.direction = state.sort === key && state.direction === 'ASC' ? 'DESC' : 'ASC'; state.sort = key; state.page = 1; load(); }

  function resetFilters() {
    Object.assign(state, { page: 1, search: '', business_type: '', economic_type: '', business_scale: '', sector: '', status: '', ocop: '', food_safety: '', social_insurance: '', located: '' });
    ['businessHouseholdSearch', 'businessHouseholdSectorFilter'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
    ['businessHouseholdTypeFilter','businessEconomicTypeFilter','businessScaleFilter','businessHouseholdStatusFilter','businessOcopFilter','businessFoodSafetyFilter','businessSocialInsuranceFilter','businessHouseholdLocationFilter'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
    load();
  }

  async function openForm(id = null) {
    if (!can('household_business', id ? 'update' : 'create')) return show('Tài khoản hiện tại không có quyền thực hiện thao tác này', 'warning');
    const form = $('#businessHouseholdForm');
    if (!form) return;
    form.reset();
    form.classList.remove('was-validated');
    state.selectedHousehold = null;
    setFormValue(form, 'id', '');
    setFormValue(form, 'household_id', '');
    setDisabled('#businessHouseholdAutocomplete', !!id);
    setValue('#businessHouseholdAutocomplete', '');
    setText('#businessHouseholdSelected', '');
    hideSuggestions();
    await ensureCatalogs();
    renderExistingFiles([]);
    setFormValue(form, 'gps_source', 'household');
    setFormValue(form, 'latitude', '');
    setFormValue(form, 'longitude', '');
    const ownGps = $('#businessActivityOwnGps');
    if (ownGps) ownGps.checked = false;
    updateBusinessGpsPanel(null);
    toggleConditionalFields();
    if (id) {
      const row = await request('/api/household-business/' + encodeURIComponent(id));
      setForm(form, row);
    }
    openModal('businessHouseholdModal');
  }

  async function searchHouseholds() {
    const input = $('#businessHouseholdAutocomplete');
    const host = $('#businessHouseholdSuggestions');
    if (!input || !host) return;
    const q = input.value.trim();
    state.selectedHousehold = null;
    state.householdSuggestions = [];
    setValue('#businessHouseholdId', '');
    setText('#businessHouseholdSelected', '');
    if (q.length < 2) { hideSuggestions(); return; }
    const data = await request('/api/household-business/household-search?q=' + encodeURIComponent(q), { cacheTtl: 3000 });
    const items = data.items || [];
    state.householdSuggestions = items;
    host.innerHTML = items.length ? items.map(item => suggestionHtml(item)).join('') : '<div class="list-group-item text-muted">Không tìm thấy hộ phù hợp</div>';
    host.classList.remove('d-none');
  }

  function suggestionHtml(item) {
    return '<button type="button" class="list-group-item list-group-item-action" data-platform-action="businessHouseholds.selectHousehold" data-household-choice="' + Number(item.id) + '">'
      + '<div class="d-flex justify-content-between gap-2"><strong>' + esc(item.household_code) + '</strong>' + (Number(item.business_count || 0) > 0 ? '<span class="badge text-bg-light">' + num(item.business_count) + ' hoạt động</span>' : '') + '</div>'
      + '<div>' + esc(item.head_citizen_name || '') + '</div><small class="text-muted">' + esc(item.address || '') + '</small></button>';
  }

  function selectHousehold(item) {
    if (!item) return;
    state.selectedHousehold = item;
    const form = $('#businessHouseholdForm');
    if (!form) return;
    setFormValue(form, 'household_id', item.id);
    setFormValue(form, 'owner_name', item.head_citizen_name || '');
    setFormValue(form, 'phone', item.phone || '');
    setFormValue(form, 'address', item.address || '');
    setFormValue(form, 'gps_source', 'household');
    setFormValue(form, 'latitude', '');
    setFormValue(form, 'longitude', '');
    const ownGps = $('#businessActivityOwnGps');
    if (ownGps) ownGps.checked = false;
    updateBusinessGpsPanel(item);
    setValue('#businessHouseholdAutocomplete', item.household_code + ' - ' + (item.head_citizen_name || ''));
    setText('#businessHouseholdSelected', item.address || '');
    hideSuggestions();
  }

  async function openFormForHousehold(householdId) {
    if (!can('household_business', 'create')) return show('Tài khoản hiện tại không có quyền thực hiện thao tác này', 'warning');
    const data = await request('/api/household-business/household/' + encodeURIComponent(householdId));
    const row = data.summary || summarizeHouseholdDetail(data.items || [], householdId);
    await openForm();
    const form = $('#businessHouseholdForm');
    if (!form) return;
    state.selectedHousehold = { id: row.household_id || row.id, household_code: row.household_code, head_citizen_name: row.head_citizen_name, address: row.address, phone: row.phone, latitude: row.latitude, longitude: row.longitude };
    setFormValue(form, 'household_id', row.household_id || row.id);
    setFormValue(form, 'owner_name', row.head_citizen_name || '');
    setFormValue(form, 'phone', row.phone || '');
    setFormValue(form, 'address', row.address || '');
    setFormValue(form, 'gps_source', 'household');
    setFormValue(form, 'latitude', '');
    setFormValue(form, 'longitude', '');
    const ownGps = $('#businessActivityOwnGps');
    if (ownGps) ownGps.checked = false;
    updateBusinessGpsPanel(state.selectedHousehold);
    setValue('#businessHouseholdAutocomplete', row.household_code + ' - ' + (row.head_citizen_name || ''));
    setText('#businessHouseholdSelected', row.address || '');
  }

  function hideSuggestions() { $('#businessHouseholdSuggestions')?.classList.add('d-none'); }

  function hasGps(row) {
    return row && row.latitude !== null && row.latitude !== undefined && row.latitude !== '' && row.longitude !== null && row.longitude !== undefined && row.longitude !== '';
  }

  function gpsDisplay(row) {
    if (!hasGps(row)) return 'Ch\u01b0a \u0111\u1ecbnh v\u1ecb';
    return '\u0110\u00e3 \u0111\u1ecbnh v\u1ecb - ' + (row.gps_source_label || (row.gps_source === 'activity' ? 'GPS ri\u00eang' : 'GPS h\u1ed9 gia \u0111\u00ecnh'));
  }

  function gpsMapLink(row) {
    if (!hasGps(row)) return '';
    return 'https://www.openstreetmap.org/?mlat=' + encodeURIComponent(row.latitude) + '&mlon=' + encodeURIComponent(row.longitude) + '#map=18/' + encodeURIComponent(row.latitude) + '/' + encodeURIComponent(row.longitude);
  }

  function updateBusinessGpsPanel(row, accuracy = '') {
    const status = $('#businessGpsStatusText');
    const meta = $('#businessGpsMeta');
    const badge = $('#businessGpsSourceBadge');
    const actions = $('#businessGpsActions');
    const ownGps = $('#businessActivityOwnGps')?.checked || row?.gps_source === 'activity';
    if (actions) actions.classList.toggle('d-none', !ownGps && hasGps(row));
    if (badge) badge.textContent = ownGps ? 'GPS ri\u00eang' : 'GPS h\u1ed9';
    if (!row) {
      if (status) status.textContent = 'Ch\u01b0a ch\u1ecdn h\u1ed9 gia \u0111\u00ecnh.';
      if (meta) meta.textContent = 'Ch\u1ecdn h\u1ed9 gia \u0111\u00ecnh \u0111\u1ec3 h\u1ec7 th\u1ed1ng t\u1ef1 l\u1ea5y v\u1ecb tr\u00ed GIS c\u1ee7a h\u1ed9.';
      return;
    }
    if (hasGps(row)) {
      if (status) status.textContent = ownGps ? 'Ho\u1ea1t \u0111\u1ed9ng \u0111ang d\u00f9ng v\u1ecb tr\u00ed ri\u00eang.' : '\u0110ang s\u1eed d\u1ee5ng v\u1ecb tr\u00ed c\u1ee7a H\u1ed9 gia \u0111\u00ecnh.';
      const link = gpsMapLink(row);
      if (meta) meta.innerHTML = esc(row.address || 'Ch\u01b0a c\u1eadp nh\u1eadt \u0111\u1ecba ch\u1ec9') + (accuracy ? ' ? Sai s\u1ed1 ' + esc(accuracy) : '') + ' ? ' + (link ? '<a href="' + link + '" target="_blank" rel="noopener">Xem tr\u00ean b\u1ea3n \u0111\u1ed3</a>' : '\u0110\u00e3 \u0111\u1ecbnh v\u1ecb');
      return;
    }
    if (status) status.textContent = ownGps ? 'Ho\u1ea1t \u0111\u1ed9ng ch\u01b0a c\u00f3 v\u1ecb tr\u00ed ri\u00eang.' : 'H\u1ed9 gia \u0111\u00ecnh ch\u01b0a \u0111\u01b0\u1ee3c \u0111\u1ecbnh v\u1ecb.';
    if (meta) meta.innerHTML = ownGps ? 'B\u1ea5m "L\u1ea5y GPS hi\u1ec7n t\u1ea1i" \u0111\u1ec3 l\u01b0u v\u1ecb tr\u00ed ri\u00eang cho ho\u1ea1t \u0111\u1ed9ng.' : '<span class="text-warning fw-semibold">H\u1ed9 gia \u0111\u00ecnh ch\u01b0a \u0111\u01b0\u1ee3c \u0111\u1ecbnh v\u1ecb.</span> B\u1ea5m "Ch\u1ecdn tr\u00ean b\u1ea3n \u0111\u1ed3" \u0111\u1ec3 m\u1edf c\u00f4ng c\u1ee5 GIS.';
  }

  function syncBusinessGpsSource() {
    const form = $('#businessHouseholdForm');
    const ownGps = $('#businessActivityOwnGps')?.checked;
    if (!form) return;
    setFormValue(form, 'gps_source', ownGps ? 'activity' : 'household');
    if (!ownGps) {
      setFormValue(form, 'latitude', '');
      setFormValue(form, 'longitude', '');
    }
    updateBusinessGpsPanel(ownGps ? { latitude: form.elements.latitude?.value, longitude: form.elements.longitude?.value, address: form.elements.address?.value, gps_source: 'activity', gps_source_label: 'GPS ri\u00eang' } : (state.selectedHousehold || null));
  }

  async function handleBusinessGpsAction(action) {
    const form = $('#businessHouseholdForm');
    if (!form) return;
    if (action === 'current') {
      if (!navigator.geolocation || !window.isSecureContext) return show('GPS ch\u1ec9 ho\u1ea1t \u0111\u1ed9ng tr\u00ean HTTPS ho\u1eb7c localhost.', 'warning');
      navigator.geolocation.getCurrentPosition(async position => {
        const lat = Number(position.coords.latitude).toFixed(8);
        const lng = Number(position.coords.longitude).toFixed(8);
        const accuracyValue = Math.round(Number(position.coords.accuracy || 0));
        const accuracy = accuracyValue ? '\u00b1' + accuracyValue.toFixed(1) + ' m' : '';
        const ownGps = $('#businessActivityOwnGps')?.checked;
        if (ownGps) {
          setFormValue(form, 'gps_source', 'activity');
          setFormValue(form, 'latitude', lat);
          setFormValue(form, 'longitude', lng);
          updateBusinessGpsPanel({ latitude: lat, longitude: lng, address: form.elements.address?.value, gps_source: 'activity', gps_source_label: 'GPS ri\u00eang' }, accuracy);
          show(accuracy ? '\u0110\u00e3 l\u1ea5y GPS ri\u00eang cho ho\u1ea1t \u0111\u1ed9ng (' + accuracy + ').' : '\u0110\u00e3 l\u1ea5y GPS ri\u00eang cho ho\u1ea1t \u0111\u1ed9ng.');
          return;
        }
        const householdId = form.elements.household_id?.value;
        if (!householdId) return show('Vui l\u00f2ng ch\u1ecdn h\u1ed9 gia \u0111\u00ecnh tr\u01b0\u1edbc khi \u0111\u1ecbnh v\u1ecb.', 'warning');
        try {
          await request('/api/gis/households/' + encodeURIComponent(householdId) + '/location', { method: 'PUT', body: { latitude: lat, longitude: lng, source: 'GPS', accuracy: accuracyValue || null } });
          state.selectedHousehold = Object.assign({}, state.selectedHousehold || {}, { latitude: lat, longitude: lng, gps_source: 'household', gps_source_label: 'GPS h\u1ed9 gia \u0111\u00ecnh' });
          updateBusinessGpsPanel(state.selectedHousehold, accuracy);
          show(accuracy ? '\u0110\u00e3 l\u01b0u GPS cho h\u1ed9 gia \u0111\u00ecnh (' + accuracy + ').' : '\u0110\u00e3 l\u01b0u GPS cho h\u1ed9 gia \u0111\u00ecnh.');
        } catch (error) {
          show(error.message || 'Kh\u00f4ng l\u01b0u \u0111\u01b0\u1ee3c GPS cho h\u1ed9 gia \u0111\u00ecnh.', 'danger');
        }
      }, () => show('Kh\u00f4ng l\u1ea5y \u0111\u01b0\u1ee3c GPS hi\u1ec7n t\u1ea1i. Vui l\u00f2ng ki\u1ec3m tra quy\u1ec1n v\u1ecb tr\u00ed c\u1ee7a tr\u00ecnh duy\u1ec7t.', 'warning'), { enableHighAccuracy: true, timeout: 30000, maximumAge: 0 });
      return;
    }
    if (action === 'map') {
      closeModal('businessHouseholdModal');
      if (window.Thon09NavigationController && typeof window.Thon09NavigationController.navigate === 'function') window.Thon09NavigationController.navigate('gis');
      show('\u0110\u00e3 m\u1edf GIS Google. H\u00e3y ch\u1ecdn h\u1ed9 v\u00e0 \u0111\u1eb7t marker tr\u00ean b\u1ea3n \u0111\u1ed3, h\u1ec7 th\u1ed1ng s\u1ebd d\u00f9ng GPS h\u1ed9 gia \u0111\u00ecnh.', 'info');
      return;
    }
    if (action === 'geocode') {
      const address = form.elements.address?.value || state.selectedHousehold?.address || '';
      if (address) window.open('https://www.openstreetmap.org/search?query=' + encodeURIComponent(address), '_blank', 'noopener');
      show('\u0110\u00e3 m\u1edf OpenStreetMap theo \u0111\u1ecba ch\u1ec9. \u0110\u1ec3 l\u01b0u t\u1ecda \u0111\u1ed9, h\u00e3y \u0111\u1ecbnh v\u1ecb h\u1ed9 trong module GIS.', 'info');
    }
  }

  function setForm(form, row) {
    state.selectedHousehold = { id: row.household_id, household_code: row.household_code, head_citizen_name: row.head_citizen_name, address: row.address, latitude: row.household_latitude || row.latitude, longitude: row.household_longitude || row.longitude, gps_source: 'household', gps_source_label: 'GPS h\u1ed9 gia \u0111\u00ecnh' };
    setValue('#businessHouseholdAutocomplete', row.household_code + ' - ' + (row.head_citizen_name || ''));
    setText('#businessHouseholdSelected', row.address || '');
    Object.entries({
      id: row.id, household_id: row.household_id, business_name: row.business_name, owner_name: row.owner_name, business_type: row.business_type,
      production_sector: row.production_sector, business_sector: row.business_sector, start_date: row.start_date,
      business_license: row.business_license, license_date: row.license_date, license_place: row.license_place,
      tax_code: row.tax_code, worker_count: row.worker_count, annual_revenue: row.annual_revenue,
      phone: row.phone, email: row.email, address: row.address, gps_source: row.gps_source || 'household', latitude: row.gps_source === 'activity' ? row.activity_latitude : '', longitude: row.gps_source === 'activity' ? row.activity_longitude : '',
      economic_type: row.economic_type, business_scale: row.business_scale, main_products: (row.main_products || []).join(', '),
      is_ocop: row.is_ocop ? '1' : '', ocop_product: row.ocop_product, ocop_star: row.ocop_star,
      food_safety_certified: row.food_safety_certified ? '1' : '', food_safety_certificate_no: row.food_safety_certificate_no, food_safety_expired_date: row.food_safety_expired_date,
      social_insurance: row.social_insurance ? '1' : '', insured_workers: row.insured_workers,
      status: row.status, note: row.note
    }).forEach(([key, value]) => {
      if (!form.elements[key]) return;
      if (form.elements[key].type === 'checkbox') form.elements[key].checked = !!value;
      else form.elements[key].value = value ?? '';
    });
    const ownGps = $('#businessActivityOwnGps');
    if (ownGps) ownGps.checked = row.gps_source === 'activity';
    updateBusinessGpsPanel(row);
    renderExistingFiles(row.files || []);
    toggleConditionalFields();
  }

  function toggleConditionalFields() {
    const ocop = !!$('#businessOcopCheck')?.checked;
    $$('.business-ocop-fields').forEach(el => el.classList.toggle('d-none', !ocop));
    const insuredInput = $('#businessHouseholdForm')?.elements?.insured_workers;
    if (insuredInput) insuredInput.disabled = !$('#businessSocialInsuranceCheck')?.checked;
    ['food_safety_certificate_no','food_safety_expired_date'].forEach(name => { const el = $('#businessHouseholdForm')?.elements?.[name]; if (el) el.disabled = !$('#businessFoodSafetyCheck')?.checked; });
  }

  async function save(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const id = form.elements.id.value;
    if (!can('household_business', id ? 'update' : 'create')) return show('Tài khoản hiện tại không có quyền thực hiện thao tác này', 'warning');
    if (!form.elements.household_id.value) { show('Vui lòng chọn hộ gia đình từ danh sách gợi ý', 'warning'); return; }
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
    const payload = Object.fromEntries(new FormData(form).entries());
    delete payload.id;
    const ownGps = $('#businessActivityOwnGps')?.checked;
    payload.gps_source = ownGps ? 'activity' : 'household';
    if (!ownGps) { payload.latitude = ''; payload.longitude = ''; }
    if (ownGps && (!payload.latitude || !payload.longitude)) { show('Vui l\u00f2ng \u0111\u1ecbnh v\u1ecb ho\u1ea1t \u0111\u1ed9ng b\u1eb1ng GPS hi\u1ec7n t\u1ea1i ho\u1eb7c t\u1eaft v\u1ecb tr\u00ed ri\u00eang \u0111\u1ec3 d\u00f9ng GPS h\u1ed9 gia \u0111\u00ecnh.', 'warning'); return; }
    ['is_ocop', 'food_safety_certified', 'social_insurance'].forEach(key => { payload[key] = form.elements[key]?.checked ? '1' : '0'; });
    payload.main_products = splitTags(payload.main_products || '');
    try {
      const saved = await request(id ? '/api/household-business/' + encodeURIComponent(id) : '/api/household-business', { method: id ? 'PUT' : 'POST', body: payload });
      await uploadPendingFiles(saved.id || id);
      closeModal('businessHouseholdModal');
      show('Đã lưu thông tin hộ sản xuất/kinh doanh');
      load();
      if (state.currentDetailHouseholdId && Number(saved.household_id || payload.household_id || 0) === state.currentDetailHouseholdId) showDetail(state.currentDetailHouseholdId);
      renderDashboard();
      if (typeof window.loadDashboard === 'function') window.loadDashboard().catch(() => {});
    } catch (error) { show(error.message, 'danger'); }
  }

  function splitTags(value) { return String(value || '').split(/[,;\n]+/).map(item => item.trim()).filter(Boolean); }

  async function uploadPendingFiles(id) {
    const imageInput = $('#businessImageFiles');
    const docInput = $('#businessDocumentFiles');
    if (imageInput?.files?.length) await uploadFiles(id, 'IMAGE', $('#businessImageCategory')?.value || 'Khác', imageInput.files);
    if (docInput?.files?.length) await uploadFiles(id, 'DOCUMENT', $('#businessDocumentCategory')?.value || 'Hồ sơ khác', docInput.files);
  }

  async function uploadFiles(id, kind, category, files) {
    const body = new FormData();
    body.append('file_kind', kind);
    body.append('category', category);
    Array.from(files).forEach(file => body.append('file[]', file));
    await request('/api/household-business/' + encodeURIComponent(id) + '/files', { method: 'POST', body });
  }

  function renderExistingFiles(files) {
    const host = $('#businessFileExisting');
    if (!host) return;
    const active = Array.isArray(files) ? files : [];
    host.innerHTML = active.length ? active.map(file => '<span class="badge text-bg-light me-1 mb-1">' + esc(file.file_kind === 'IMAGE' ? 'Ảnh' : 'Tài liệu') + ': ' + esc(file.original_name || '') + '</span>').join('') : 'Chưa có ảnh hoặc hồ sơ đính kèm';
  }

  async function showDetail(id) {
    try {
      const data = await request('/api/household-business/household/' + encodeURIComponent(id));
      const row = data.summary || summarizeHouseholdDetail(data.items || [], id);
      if (!row) {
        state.currentDetailHouseholdId = Number(id || 0) || null;
        $('#detailTitle').textContent = 'Chi tiết hộ sản xuất & kinh doanh';
        $('#detailBody').innerHTML = '<div class="text-muted p-3">Hộ này không còn hoạt động sản xuất/kinh doanh.</div>';
        openModal('detailModal');
        return;
      }
      state.currentDetailHouseholdId = Number(row.household_id || row.id || id || 0) || null;
      $('#detailTitle').textContent = 'Chi tiết hộ sản xuất & kinh doanh';
      $('#detailBody').innerHTML = detailHtml(row);
      openModal('detailModal');
    } catch (error) { show(error.message, 'danger'); }
  }

  function detailHtml(row) {
    const activities = activitiesOf(row);
    const householdId = Number(row.household_id || row.id || 0);
    const addButton = can('household_business', 'create') ? '<button class="btn btn-primary btn-sm" type="button" data-platform-action="businessHouseholds.create" data-household-id="' + householdId + '"><i class="fa-solid fa-plus"></i> Thêm hoạt động</button>' : '';
    const hasOcop = activities.some(activity => businessTruthy(activity.is_ocop));
    const hasFoodSafety = activities.some(activity => businessTruthy(activity.food_safety_certified));
    const hasSocialInsurance = activities.some(activity => businessTruthy(activity.social_insurance));
    const hasGps = Boolean(row.latitude && row.longitude) || activities.some(activity => activity.latitude && activity.longitude);
    const isActive = activities.some(activity => String(activity.status || '').toUpperCase() === 'ACTIVE');
    const tabs = [
      ['info', 'Thông tin', 'fa-circle-info', businessInfoTab(row, activities)],
      ['activities', 'Hoạt động kinh tế', 'fa-store', businessActivityMasterDetail(activities, addButton)],
      ['records', 'Hồ sơ', 'fa-folder-open', businessRecordsTab(activities)],
      ['timeline', 'Timeline', 'fa-clock-rotate-left', businessTimelineTab(activities)]
    ];
    return '<article class="person-detail-card person-detail-dynamic business-person-detail business-economic-popup">'
      + '<section class="person-detail-hero business-economic-hero">'
      + '<div class="business-economic-hero-main"><span>Hồ sơ sản xuất & kinh doanh</span><h3>' + esc(row.household_code || 'Hộ sản xuất & kinh doanh') + '</h3><strong>' + esc(row.head_citizen_name || 'Chưa cập nhật') + '</strong><p>' + esc(row.address || 'Chưa cập nhật địa chỉ') + '</p></div>'
      + '<div class="business-economic-hero-badges">'
      + businessHeroBadge(isActive ? 'Đang hoạt động' : 'Chưa hoạt động', isActive ? 'green' : 'neutral')
      + businessHeroBadge(hasGps ? 'Có GPS' : 'Không GPS', hasGps ? 'blue' : 'neutral')
      + businessHeroBadge(hasOcop ? 'OCOP' : 'Không OCOP', hasOcop ? 'gold' : 'neutral')
      + businessHeroBadge(hasFoodSafety ? 'ATTP' : 'Không ATTP', hasFoodSafety ? 'green' : 'neutral')
      + businessHeroBadge(hasSocialInsurance ? 'BHXH' : 'Không BHXH', hasSocialInsurance ? 'purple' : 'neutral')
      + '</div></section>'
      + businessDetailTabs(tabs)
      + '</article>';
  }

  function businessDetailTabs(tabs) {
    const nav = tabs.map((tab, index) => '<button class="business-person-tab ' + (index === 0 ? 'is-active' : '') + '" type="button" data-business-detail-tab="' + esc(tab[0]) + '" data-platform-action="businessHouseholds.tab" data-tab="' + esc(tab[0]) + '"><i class="fa-solid ' + esc(tab[2]) + '"></i>' + esc(tab[1]) + '</button>').join('');
    const panels = tabs.map((tab, index) => '<section class="business-person-tab-panel ' + (index === 0 ? 'is-active' : '') + '" data-business-detail-panel="' + esc(tab[0]) + '">' + tab[3] + '</section>').join('');
    return '<div class="business-person-tabs"><div class="business-person-tabbar">' + nav + '</div><div class="business-person-tab-content">' + panels + '</div></div>';
  }

  function businessInfoTab(row, activities) {
    const workerTotal = activities.reduce((sum, activity) => sum + Number(activity.worker_count || 0), 0);
    const activeTotal = activities.filter(activity => String(activity.status || '').toUpperCase() === 'ACTIVE').length;
    const revenueTotal = activities.reduce((sum, activity) => sum + Number(activity.annual_revenue || 0), 0);
    return '<div class="person-detail-sections">'
      + businessPersonSection('Thông tin hộ', 'fa-house-user', [
        businessPersonField('Mã hộ', row.household_code, true),
        businessPersonField('Chủ hộ', row.head_citizen_name),
        businessPersonField('Địa chỉ', row.address),
        businessPersonField('Điện thoại', row.phone)
      ].join(''))
      + businessPersonSection('Thông tin liên hệ', 'fa-address-book', [
        businessPersonField('Email', row.email),
        businessPersonField('GPS hộ', row.latitude && row.longitude ? row.latitude + ', ' + row.longitude : 'Chưa định vị'),
        businessPersonField('Địa chỉ cơ sở', firstBusinessValue(activities, 'address') || row.address),
        businessPersonField('Cập nhật', date(latestActivityDate(activities) || row.updated_at || row.created_at))
      ].join(''))
      + businessPersonSection('Thông tin tổng hợp', 'fa-chart-pie', [
        businessPersonField('Tổng hoạt động', num(activities.length || row.business_count || 0)),
        businessPersonField('Đang hoạt động', num(activeTotal)),
        businessPersonField('Tổng lao động', num(workerTotal) + ' người'),
        businessPersonField('Doanh thu', revenueTotal ? num(revenueTotal) : 'Chưa cập nhật')
      ].join(''))
      + businessPersonSection('Chứng nhận', 'fa-certificate', [
        businessPersonField('OCOP', activities.some(activity => businessTruthy(activity.is_ocop)) ? 'Có' : 'Không'),
        businessPersonField('ATTP', activities.some(activity => businessTruthy(activity.food_safety_certified)) ? 'Có' : 'Không'),
        businessPersonField('BHXH', activities.some(activity => businessTruthy(activity.social_insurance)) ? 'Có' : 'Không'),
        businessPersonField('Có GPS', (Boolean(row.latitude && row.longitude) || activities.some(activity => activity.latitude && activity.longitude)) ? 'Có' : 'Không')
      ].join(''))
      + '</div>';
  }

  function businessActivityMasterDetail(activities, addButton) {
    if (!activities.length) return '<div class="person-detail-empty">Chưa có hoạt động sản xuất/kinh doanh.</div>' + (addButton ? '<div class="mt-3">' + addButton + '</div>' : '');
    return '<section class="business-person-activity-shell business-modern-activity-shell">'
      + '<aside class="business-person-activity-list business-modern-activity-list"><div class="business-modern-list-head"><div><i class="fa-solid fa-store"></i><strong>Hoạt động</strong><span>' + num(activities.length) + ' hoạt động</span></div>' + addButton + '</div><div class="business-person-activity-scroll business-modern-activity-scroll">' + activities.map((activity, index) => businessActivityListItem(activity, index)).join('') + '</div></aside>'
      + '<section class="business-person-activity-detail business-modern-activity-detail">' + activities.map((activity, index) => businessActivityDetailPanel(activity, index)).join('') + '</section>'
      + '</section>';
  }

  function businessActivityListItem(activity, index) {
    const title = activity.business_name || activity.economic_type || activity.sector_label || 'Hoạt động số ' + (index + 1);
    return '<button class="business-person-activity-item business-modern-activity-item ' + (index === 0 ? 'is-active' : '') + '" type="button" data-business-activity-tab="' + index + '" data-platform-action="businessHouseholds.activity" data-index="' + index + '"><strong>' + esc(title) + '</strong><span>' + esc(businessActivityListSubtitle(activity)) + '</span>' + statusBadge(activity.status, activity.status_label) + '</button>';
  }

  function businessActivityDetailPanel(activity, index) {
    const files = activity.files || [];
    const images = files.filter(file => file.file_kind === 'IMAGE');
    const documents = files.filter(file => file.file_kind === 'DOCUMENT');
    const edit = can('household_business', 'update') ? '<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="businessHouseholds.edit" data-id="' + Number(activity.id || 0) + '"><i class="fa-solid fa-pen-to-square"></i> Sửa</button>' : '';
    const del = can('household_business', 'delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="businessHouseholds.delete" data-id="' + Number(activity.id || 0) + '"><i class="fa-solid fa-trash"></i> Xóa</button>' : '';
    const title = activity.business_name || activity.economic_type || activity.sector_label || 'Hoạt động số ' + (index + 1);
    const sector = activity.sector_label || activity.production_sector || activity.business_sector || activity.economic_type || 'Chưa cập nhật';
    return '<article class="business-person-activity-panel business-dashboard-activity-view ' + (index === 0 ? 'is-active' : '') + '" data-business-activity-panel="' + index + '">'
      + '<header class="business-dashboard-activity-hero"><div><h3>' + esc(title) + '</h3><p>' + esc(activity.business_type_label || activity.business_type || 'Chưa cập nhật') + '</p><p>' + esc(sector) + '</p></div><div class="business-dashboard-activity-actions">' + statusBadge(activity.status, activity.status_label) + edit + del + '</div></header>'
      + '<div class="business-dashboard-card-stack">'
      + businessDashboardSection('Thông tin cơ bản', 'fa-circle-info', [
        businessDashboardRow('Loại hình', activity.business_type_label || activity.business_type),
        businessDashboardRow('Ngành nghề', sector),
        businessDashboardRow('Quy mô', activity.business_scale),
        businessDashboardRow('Sản phẩm', (activity.main_products || []).join(', ')),
        businessDashboardRow('Ngày bắt đầu', date(activity.start_date))
      ].join(''))
      + businessDashboardSection('Thông tin sản xuất', 'fa-chart-line', [
        businessDashboardRow('Sản phẩm chính', (activity.main_products || []).join(', ') || 'Chưa cập nhật'),
        businessDashboardRow('Lao động', num(activity.worker_count || 0) + ' người'),
        businessDashboardRow('Doanh thu', activity.annual_revenue ? num(activity.annual_revenue) : 'Chưa cập nhật'),
        businessDashboardRow('Diện tích / Quy mô', businessActivityValue(activity, ['production_area','area','scale_description','operation_scale','facility_area']) || 'Chưa cập nhật'),
        businessDashboardRow('Thị trường tiêu thụ', businessActivityValue(activity, ['consumption_market','market','target_market','distribution_market']) || 'Chưa cập nhật')
      ].join(''))
      + businessDashboardSection('Chứng nhận', 'fa-certificate', businessCertificationBadges(activity), true)
      + businessDashboardSection('Vị trí', 'fa-location-dot', [
        businessDashboardRow('Địa chỉ', activity.address || 'Chưa cập nhật'),
        businessDashboardRow('GPS', gpsDisplay(activity)),
        businessDashboardRow('Ngày cập nhật', date(activity.updated_at || activity.created_at) || 'Chưa cập nhật')
      ].join(''))
      + businessDashboardSection('Hồ sơ', 'fa-folder-open', businessActivityImages(images, activity.id) + businessActivityDocuments(documents, activity.id), true)
      + (activity.note ? businessDashboardSection('Ghi chú', 'fa-note-sticky', '<p class="business-dashboard-note">' + esc(activity.note) + '</p>', true) : '')
      + '</div></article>';
  }

  function businessDashboardSection(title, icon, content, raw = false) {
    if (!content) return '';
    return '<section class="business-dashboard-section"><div class="business-dashboard-section-head"><i class="fa-solid ' + esc(icon) + '"></i><h4>' + esc(title) + '</h4></div><div class="business-dashboard-section-body">' + content + '</div></section>';
  }

  function businessDashboardRow(label, value) {
    if (value === null || value === undefined || String(value).trim() === '') return '';
    return '<div class="business-dashboard-row"><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong></div>';
  }

    function businessActivitySection(title, icon, content, raw = false) {
    return businessDashboardSection(title, icon, content, raw);
  }

  function businessCertificationBadges(activity) {
    return '<div class="business-dashboard-cert-list">'
      + businessDashboardRow('OCOP', businessTruthy(activity.is_ocop) ? (activity.ocop_star ? 'OCOP ' + activity.ocop_star + ' sao' : 'Có') : 'Không')
      + businessDashboardRow('ATTP', businessTruthy(activity.food_safety_certified) ? 'Có' : 'Không')
      + businessDashboardRow('BHXH', businessTruthy(activity.social_insurance) ? 'Có' : 'Không')
      + '</div>';
  }

  function businessActivityImages(images, businessId) {
    const add = can('household_business', 'update') ? '<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="businessHouseholds.edit" data-id="' + Number(businessId || 0) + '"><i class="fa-solid fa-plus"></i> Thêm ảnh</button>' : '';
    if (!images.length) return '<div class="business-dashboard-media-line"><div><span><i class="fa-solid fa-images"></i>Ảnh</span><strong><i class="fa-regular fa-image"></i> Chưa có ảnh</strong></div>' + add + '</div>';
    return '<div class="business-dashboard-media-line"><div><span><i class="fa-solid fa-images"></i>Ảnh</span><div class="business-dashboard-thumbs">' + images.slice(0, 6).map(file => mediaThumb(file, businessId)).join('') + (images.length > 6 ? '<span class="business-economic-more">+' + (images.length - 6) + '</span>' : '') + '</div></div>' + add + '</div>';
  }

  function businessActivityDocuments(documents, businessId) {
    if (!documents.length) return '<div class="business-dashboard-media-line"><div><span><i class="fa-solid fa-file-lines"></i>Tài liệu</span><strong><i class="fa-regular fa-file-lines"></i> Chưa có tài liệu</strong></div></div>';
    return '<div class="business-dashboard-document-list"><span><i class="fa-solid fa-file-lines"></i>Tài liệu</span>' + documents.map(file => businessActivityDocumentRow(file, businessId)).join('') + '</div>';
  }

  function businessActivityDocumentRow(file, businessId) {
    const preview = '/api/household-business/' + encodeURIComponent(businessId) + '/files/' + encodeURIComponent(file.id) + '/preview';
    const download = '/api/household-business/' + encodeURIComponent(businessId) + '/files/' + encodeURIComponent(file.id) + '/download';
    return '<div class="business-dashboard-document"><i class="fa-solid fa-file-lines"></i><strong>' + esc(file.original_name || file.category || 'Tài liệu') + '</strong><span><a target="_blank" rel="noopener" href="' + preview + '" title="Xem"><i class="fa-solid fa-eye"></i></a><a href="' + download + '" title="Tải xuống"><i class="fa-solid fa-download"></i></a></span></div>';
  }

  function businessActivityValue(activity, keys) {
    for (const key of keys) {
      if (activity[key] !== null && activity[key] !== undefined && String(activity[key]).trim() !== '') return activity[key];
    }
    return '';
  }

  function businessRecordsTab(activities) {
    const images = [];
    const documents = [];
    activities.forEach(activity => (activity.files || []).forEach(file => (file.file_kind === 'IMAGE' ? images : documents).push({ file, businessId: activity.id })));
    return '<div class="person-detail-sections">'
      + businessPersonSection('Ảnh cơ sở', 'fa-images', images.length ? '<div class="business-person-file-list">' + images.map(item => businessRecordRow(item.file, item.businessId, true)).join('') + '</div>' : '<div class="person-detail-empty">Chưa có ảnh.</div>')
      + businessPersonSection('Giấy phép - chứng nhận - tài liệu', 'fa-folder-open', documents.length ? '<div class="business-person-file-list">' + documents.map(item => businessRecordRow(item.file, item.businessId, false)).join('') + '</div>' : '<div class="person-detail-empty">Chưa có tài liệu.</div>')
      + '</div>';
  }

  function businessRecordRow(file, businessId, isImage) {
    const preview = '/api/household-business/' + encodeURIComponent(businessId) + '/files/' + encodeURIComponent(file.id) + '/preview';
    const download = '/api/household-business/' + encodeURIComponent(businessId) + '/files/' + encodeURIComponent(file.id) + '/download';
    const icon = isImage ? '<img src="' + preview + '" alt="">' : '<i class="fa-solid fa-file-lines"></i>';
    return '<div class="business-person-file-row"><div class="business-person-file-icon">' + icon + '</div><div><strong>' + esc(file.category || file.original_name || 'Hồ sơ') + '</strong><span>' + esc(file.original_name || '') + '</span></div><div><a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="' + preview + '">Xem</a><a class="btn btn-sm btn-outline-secondary" href="' + download + '">Tải xuống</a></div></div>';
  }

  function businessTimelineTab(activities) {
    const events = [];
    activities.forEach(activity => {
      if (activity.created_at) events.push({ time: activity.created_at, title: 'Tạo hoạt động', text: activity.business_name || activity.economic_type || activity.sector_label || 'Hoạt động kinh tế' });
      if (activity.updated_at && activity.updated_at !== activity.created_at) events.push({ time: activity.updated_at, title: 'Cập nhật hoạt động', text: activity.business_name || activity.economic_type || activity.sector_label || 'Hoạt động kinh tế' });
      (activity.files || []).forEach(file => events.push({ time: file.created_at || activity.updated_at || activity.created_at, title: file.file_kind === 'IMAGE' ? 'Upload ảnh' : 'Upload tài liệu', text: file.original_name || file.category || '' }));
    });
    events.sort((a, b) => String(b.time || '').localeCompare(String(a.time || '')));
    if (!events.length) return '<div class="person-detail-empty">Chưa có lịch sử hoạt động.</div>';
    return '<section class="person-info-section business-person-timeline"><div class="person-info-section-title"><i class="fa-solid fa-clock-rotate-left"></i><h4>Timeline</h4></div><div class="business-person-timeline-list">' + events.map(event => '<div class="business-person-timeline-item"><span>' + esc(date(event.time) || 'Chưa cập nhật') + '</span><strong>' + esc(event.title) + '</strong><p>' + esc(event.text || '') + '</p></div>').join('') + '</div></section>';
  }

  function businessPersonSection(title, icon, content) {
    if (!content) return '';
    return '<section class="person-info-section"><div class="person-info-section-title"><i class="fa-solid ' + esc(icon) + '"></i><h4>' + esc(title) + '</h4></div><div class="person-info-grid">' + content + '</div></section>';
  }

  function businessPersonField(label, value, code = false) {
    if (value === null || value === undefined || String(value).trim() === '') return '';
    return '<div class="person-info-field"><span>' + esc(label) + '</span><div class="person-info-value ' + (code ? 'person-info-value-code' : '') + '">' + esc(value) + '</div></div>';
  }

  function businessCodeBadge(label, value) {
    if (value === null || value === undefined || String(value).trim() === '') return '';
    return '<strong>' + esc(label) + ': ' + esc(value) + '</strong>';
  }

  function businessHeroBadge(text, tone) {
    return '<span class="person-detail-badge person-detail-badge-' + esc(tone || 'neutral') + '">' + esc(text) + '</span>';
  }

  function firstBusinessValue(activities, key) {
    const found = activities.find(activity => activity[key] !== null && activity[key] !== undefined && String(activity[key]).trim() !== '');
    return found ? found[key] : '';
  }

  function selectBusinessDetailTab(tab) {
    const root = $('#detailBody');
    if (!root) return;
    root.querySelectorAll('[data-business-detail-tab]').forEach(item => item.classList.toggle('is-active', item.dataset.businessDetailTab === tab));
    root.querySelectorAll('[data-business-detail-panel]').forEach(item => item.classList.toggle('is-active', item.dataset.businessDetailPanel === tab));
  }

  function primaryActivity(activities) {
    if (!activities.length) return null;
    return activities.find(activity => String(activity.status || '').toUpperCase() === 'ACTIVE') || activities[0];
  }

  function latestActivityDate(activities) {
    const values = activities.map(activity => activity.updated_at || activity.created_at).filter(Boolean).sort();
    return values.length ? values[values.length - 1] : '';
  }

  function dashboardInfoCard(title, icon, body) {
    return '<section class="business-detail-card"><div class="business-detail-card-head"><div><i class="fa-solid ' + esc(icon) + '"></i><h4>' + esc(title) + '</h4></div></div><div class="business-detail-card-body">' + body + '</div></section>';
  }

  function dashboardInfoRow(label, value) {
    if (value === null || value === undefined || String(value).trim() === '') return '';
    return '<div class="business-detail-info-row"><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong></div>';
  }

  function dashboardStatusRow(label, trustedValue) {
    return '<div class="business-detail-status-row"><span>' + esc(label) + '</span><div>' + trustedValue + '</div></div>';
  }

  function businessTypeCard(typeValues, activities) {
    if (typeValues.length < 2) return '';
    const rows = activities.map((activity, index) => '<tr><td>' + num(index + 1) + '</td><td>' + esc(activity.business_type_label || activity.business_type || 'Chưa cập nhật') + '</td><td>' + esc(activity.sector_label || activity.economic_type || 'Chưa cập nhật') + '</td><td>' + esc(activity.business_scale || 'Chưa cập nhật') + '</td></tr>').join('');
    return '<section class="business-detail-card"><div class="business-detail-card-head"><div><i class="fa-solid fa-layer-group"></i><h4>Loại hình kinh doanh</h4></div></div><div class="business-detail-table-wrap"><table class="table table-sm align-middle mb-0"><thead><tr><th>STT</th><th>Loại hình</th><th>Ngành nghề</th><th>Quy mô</th></tr></thead><tbody>' + rows + '</tbody></table></div></section>';
  }

  function dashboardActivityRow(activity, index) {
    const edit = can('household_business', 'update') ? '<button class="btn btn-sm btn-outline-primary" type="button" title="Sửa" data-platform-action="businessHouseholds.edit" data-id="' + Number(activity.id || 0) + '"><i class="fa-solid fa-pen-to-square"></i></button>' : '';
    const del = can('household_business', 'delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" title="Xóa" data-platform-action="businessHouseholds.delete" data-id="' + Number(activity.id || 0) + '"><i class="fa-solid fa-trash"></i></button>' : '';
    const title = activity.business_name || activity.economic_type || activity.sector_label || 'Hoạt động số ' + (index + 1);
    return '<article class="business-detail-activity">'
      + '<div class="business-detail-activity-head"><div><span>Hoạt động ' + num(index + 1) + '</span><h5>' + esc(title) + '</h5></div><div class="business-detail-activity-actions">' + edit + del + '</div></div>'
      + '<div class="business-detail-activity-grid">'
      + dashboardMiniField('Ngành nghề', activity.sector_label || activity.economic_type || activity.production_sector || activity.business_sector)
      + dashboardMiniField('Loại hình', activity.business_type_label || activity.business_type)
      + dashboardMiniField('Lao động', num(activity.worker_count || 0) + ' người')
      + dashboardMiniField('OCOP', businessTruthy(activity.is_ocop) ? 'Có' : 'Không')
      + dashboardMiniField('ATTP', businessTruthy(activity.food_safety_certified) ? 'Có' : 'Không')
      + dashboardMiniField('BHXH', businessTruthy(activity.social_insurance) ? 'Có' : 'Không')
      + dashboardMiniField('Trạng thái', activity.status_label || activity.status || 'Chưa cập nhật')
      + '</div>'
      + '</article>';
  }

  function dashboardMiniField(label, value) {
    return '<div class="business-detail-mini-field"><span>' + esc(label) + '</span><strong>' + esc(value || 'Chưa cập nhật') + '</strong></div>';
  }

  function documentsCard(activities) {
    const docs = [];
    activities.forEach(activity => (activity.files || []).filter(file => file.file_kind === 'DOCUMENT').forEach(file => docs.push({ file, businessId: activity.id })));
    const body = docs.length ? '<div class="business-detail-doc-list">' + docs.map(item => documentRow(item.file, item.businessId)).join('') + '</div>' : '<div class="business-empty-state">Chưa có giấy tờ.</div>';
    return dashboardInfoCard('Giấy tờ', 'fa-file-lines', body);
  }

  function documentRow(file, businessId) {
    const preview = '/api/household-business/' + encodeURIComponent(businessId) + '/files/' + encodeURIComponent(file.id) + '/preview';
    const download = '/api/household-business/' + encodeURIComponent(businessId) + '/files/' + encodeURIComponent(file.id) + '/download';
    return '<div class="business-detail-doc-row"><div><i class="fa-solid fa-file-lines"></i><strong>' + esc(file.category || file.original_name || 'Tài liệu') + '</strong><span>' + esc(file.original_name || '') + '</span></div><div><a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="' + preview + '">Xem</a><a class="btn btn-sm btn-outline-secondary" href="' + download + '">Tải xuống</a></div></div>';
  }

  function notesCard(activities) {
    const notes = uniqueValues(activities.map(activity => activity.note).filter(Boolean));
    const body = '<div class="business-detail-note">' + esc(notes.length ? notes.join('\n') : 'Không có ghi chú.') + '</div>';
    return dashboardInfoCard('Ghi chú', 'fa-note-sticky', body);
  }

  function businessActivityMasterDetail(activities, addButton) {
    if (!activities.length) {
      return '<section class="business-detail-card business-detail-card-wide"><div class="business-detail-card-head"><div><i class="fa-solid fa-store"></i><h4>Hoạt động kinh tế</h4></div>' + addButton + '</div><div class="business-empty-state">Chưa có hoạt động sản xuất/kinh doanh.</div></section>';
    }
    return '<section class="business-detail-card business-detail-card-wide business-activity-master-card">'
      + '<div class="business-detail-card-head"><div><i class="fa-solid fa-store"></i><h4>Hoạt động kinh tế</h4></div>' + addButton + '</div>'
      + '<div class="business-activity-master">'
      + '<aside class="business-activity-list-panel"><div class="business-activity-list-scroll">' + activities.map((activity, index) => businessActivityListItem(activity, index)).join('') + '</div></aside>'
      + '<section class="business-activity-detail-panel">' + activities.map((activity, index) => businessActivityDetailPanel(activity, index)).join('') + '</section>'
      + '</div>'
      + '</section>';
  }

  function businessActivityListItem(activity, index) {
    const title = activity.business_name || activity.economic_type || activity.sector_label || 'Hoạt động số ' + (index + 1);
    return '<button class="business-activity-list-item ' + (index === 0 ? 'is-active' : '') + '" type="button" data-business-activity-tab="' + index + '" data-platform-action="businessHouseholds.activity" data-index="' + index + '">'
      + '<strong>' + esc(title) + '</strong>'
      + '<span>' + esc(businessActivityListSubtitle(activity)) + '</span>'
      + statusBadge(activity.status, activity.status_label)
      + '</button>';
  }

  function businessActivityDetailPanel(activity, index) {
    const files = activity.files || [];
    const images = files.filter(file => file.file_kind === 'IMAGE');
    const documents = files.filter(file => file.file_kind === 'DOCUMENT');
    const edit = can('household_business', 'update') ? '<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="businessHouseholds.edit" data-id="' + Number(activity.id || 0) + '"><i class="fa-solid fa-pen-to-square"></i> Sửa</button>' : '';
    const del = can('household_business', 'delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="businessHouseholds.delete" data-id="' + Number(activity.id || 0) + '"><i class="fa-solid fa-trash"></i> Xóa</button>' : '';
    const title = activity.business_name || activity.economic_type || activity.sector_label || 'Hoạt động số ' + (index + 1);
    const sector = activity.sector_label || activity.production_sector || activity.business_sector || activity.economic_type || 'Chưa cập nhật';
    return '<article class="business-activity-detail business-dashboard-activity-view ' + (index === 0 ? 'is-active' : '') + '" data-business-activity-panel="' + index + '">'
      + '<header class="business-dashboard-activity-hero"><div><h3>' + esc(title) + '</h3><p>' + esc(activity.status_label || activity.status || 'Chưa cập nhật') + '</p><p>' + esc(activity.business_type_label || activity.business_type || 'Chưa cập nhật') + ' · ' + esc(sector) + '</p></div><div class="business-dashboard-activity-actions">' + edit + del + '</div></header>'
      + '<div class="business-dashboard-card-stack">'
      + businessDashboardSection('Thông tin cơ bản', 'fa-circle-info', [
        businessDashboardRow('Loại hình', activity.business_type_label || activity.business_type),
        businessDashboardRow('Loại hình kinh tế', activity.economic_type),
        businessDashboardRow('Ngành nghề', sector),
        businessDashboardRow('Quy mô', activity.business_scale),
        businessDashboardRow('Ngày bắt đầu', date(activity.start_date))
      ].join(''))
      + businessDashboardSection('Thông tin sản xuất', 'fa-chart-line', [
        businessDashboardRow('Sản phẩm chính', (activity.main_products || []).join(', ') || 'Chưa cập nhật'),
        businessDashboardRow('Lao động', num(activity.worker_count || 0) + ' người'),
        businessDashboardRow('Doanh thu', activity.annual_revenue ? num(activity.annual_revenue) : 'Chưa cập nhật'),
        businessDashboardRow('Diện tích / Quy mô', businessActivityValue(activity, ['production_area','area','scale_description','operation_scale','facility_area']) || 'Chưa cập nhật'),
        businessDashboardRow('Thị trường tiêu thụ', businessActivityValue(activity, ['consumption_market','market','target_market','distribution_market']) || 'Chưa cập nhật')
      ].join(''))
      + businessDashboardSection('Chứng nhận', 'fa-certificate', businessCertificationBadges(activity), true)
      + businessDashboardSection('Vị trí', 'fa-location-dot', [
        businessDashboardRow('Địa chỉ', activity.address || 'Chưa cập nhật'),
        businessDashboardRow('GPS', gpsDisplay(activity)),
        businessDashboardRow('Ngày cập nhật', date(activity.updated_at || activity.created_at) || 'Chưa cập nhật')
      ].join(''))
      + businessDashboardSection('Hồ sơ', 'fa-folder-open', businessActivityImages(images, activity.id) + businessActivityDocuments(documents, activity.id), true)
      + (activity.note ? businessDashboardSection('Ghi chú', 'fa-note-sticky', '<p class="business-dashboard-note">' + esc(activity.note) + '</p>', true) : '')
      + '</div></article>';
  }

  function businessActivityListSubtitle(activity) {
    const type = activity.business_type_label || activity.business_type || '';
    const sector = activity.sector_label || activity.economic_type || activity.production_sector || activity.business_sector || '';
    if (type && sector) {
      const normalizedType = String(type).trim().toLowerCase();
      const normalizedSector = String(sector).trim().toLowerCase();
      if (normalizedSector.startsWith(normalizedType)) return sector;
      return type + ' - ' + sector;
    }
    return type || sector || 'Chưa cập nhật';
  }

  function selectBusinessActivity(index) {
    const root = $('#detailBody');
    if (!root) return;
    root.querySelectorAll('[data-business-activity-tab]').forEach(item => item.classList.toggle('is-active', Number(item.dataset.businessActivityTab) === Number(index)));
    root.querySelectorAll('[data-business-activity-panel]').forEach(item => item.classList.toggle('is-active', Number(item.dataset.businessActivityPanel) === Number(index)));
  }

  function businessTruthy(value) {
    return value === true || Number(value || 0) === 1 || String(value || '').toLowerCase() === 'true';
  }

  function dashboardBadge(text, cls = '') {
    return '<span class="business-dashboard-badge ' + esc(cls) + '">' + esc(text) + '</span>';
  }

  function overviewMetric(label, value, icon) {
    return '<div class="business-overview-metric"><i class="fa-solid ' + esc(icon) + '"></i><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong></div>';
  }

  function memberList(members) {
    if (!members.length) return '<div class="business-member-empty">Chưa có nhân khẩu</div>';
    const shown = members.slice(0, 6).map(member => '<div class="business-member-item"><strong>' + esc(member.full_name || '') + '</strong><span>' + esc(member.relationship || '') + '</span></div>').join('');
    const remaining = Math.max(0, members.length - 6);
    return shown + (remaining ? '<div class="business-member-more">Còn ' + remaining + ' nhân khẩu khác</div>' : '');
  }

  function summaryItem(label, value) {
    if (value === null || value === undefined || String(value).trim() === '') return '';
    return '<div class="business-summary-item"><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong></div>';
  }

  function statItem(label, value) {
    return '<div class="business-summary-stat"><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong></div>';
  }

  function activityCard(activity, index = 0) {
    const files = activity.files || [];
    const images = files.filter(file => file.file_kind === 'IMAGE');
    const documents = files.filter(file => file.file_kind === 'DOCUMENT');
    const edit = can('household_business', 'update') ? '<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="businessHouseholds.edit" data-id="' + Number(activity.id || 0) + '"><i class="fa-solid fa-pen-to-square"></i> Sửa</button>' : '';
    const del = can('household_business', 'delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="businessHouseholds.delete" data-id="' + Number(activity.id || 0) + '"><i class="fa-solid fa-trash"></i> Xóa</button>' : '';
    const title = activity.business_name || activity.economic_type || activity.sector_label || 'Hoạt động số ' + (index + 1);
    return '<article class="business-dashboard-activity-card">'
      + '<header class="business-activity-dashboard-head"><div><span>Hoạt động số ' + (index + 1) + '</span><h5>' + esc(title) + '</h5></div><div class="business-activity-head-actions">' + statusBadge(activity.status, activity.status_label) + edit + del + '</div></header>'
      + '<div class="business-activity-dashboard-grid">'
      + dashboardField('Loại hình', activity.business_type_label)
      + dashboardField('Ngành nghề', activity.sector_label || activity.economic_type)
      + dashboardField('Quy mô', activity.business_scale)
      + dashboardField('Lao động', num(activity.worker_count || 0) + ' người')
      + dashboardField('Sản phẩm', (activity.main_products || []).join(', '))
      + dashboardField('Doanh thu', activity.annual_revenue ? num(activity.annual_revenue) : '')
      + '</div>'
      + '<div class="business-activity-supplement">'
      + '<div class="business-supplement-badges">'
      + flagBadge(hasGps(activity), hasGps(activity) ? gpsDisplay(activity) : 'Kh\u00f4ng GPS')
      + flagBadge(businessTruthy(activity.is_ocop), activity.is_ocop ? 'OCOP' : 'Không OCOP')
      + flagBadge(businessTruthy(activity.food_safety_certified), activity.food_safety_certified ? 'ATTP' : 'Không ATTP')
      + flagBadge(businessTruthy(activity.social_insurance), activity.social_insurance ? 'BHXH' : 'Không BHXH')
      + '</div>'
      + '<div class="business-supplement-meta">' + compactMeta('Địa chỉ', activity.address) + compactMeta('Cập nhật', date(activity.updated_at || activity.created_at)) + '</div>'
      + '<div class="business-activity-media-inline">' + mediaInline('Ảnh', images, activity.id, 'image') + mediaInline('Tài liệu', documents, activity.id, 'document') + '</div>'
      + '</div>'
      + '</article>';
  }

  function dashboardField(label, value) {
    if (value === null || value === undefined || String(value).trim() === '') return '';
    return '<div class="business-dashboard-field"><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong></div>';
  }

  function compactMeta(label, value) {
    if (value === null || value === undefined || String(value).trim() === '') return '';
    return '<span><strong>' + esc(label) + ':</strong> ' + esc(value) + '</span>';
  }

  function activityInfoGroup(title, items) {
    const content = items.filter(Boolean).join('');
    if (!content) return '';
    return '<section class="business-info-group"><h6>' + esc(title) + '</h6><div class="business-info-items">' + content + '</div></section>';
  }

  function activityInfoItem(label, value, trusted = false) {
    if (value === null || value === undefined || String(value).trim() === '') return '';
    return '<div class="business-info-item"><span>' + esc(label) + '</span><strong>' + (trusted ? value : esc(value)) + '</strong></div>';
  }

  function flagBadge(active, text) {
    return '<span class="business-flag-badge ' + (active ? 'is-yes' : 'is-no') + '">' + esc(text) + '</span>';
  }

  function mediaInline(title, files, businessId, kind = '') {
    const icon = kind === 'image' ? 'fa-images' : 'fa-file-lines';
    if (!files.length) return '<div class="business-media-inline"><span><i class="fa-solid ' + icon + '"></i>' + esc(title) + '</span><strong>Chưa có ' + esc(title.toLowerCase()) + '</strong></div>';
    const previewItems = files.slice(0, 4).map(file => mediaThumb(file, businessId)).join('');
    const remaining = Math.max(0, files.length - 4);
    return '<div class="business-media-inline"><span><i class="fa-solid ' + icon + '"></i>' + esc(title) + '</span><div class="business-media-thumbs">' + previewItems + (remaining ? '<button type="button" class="business-media-more">+' + remaining + '</button>' : '') + '</div></div>';
  }

  function mediaThumb(file, businessId) {
    const preview = '/api/household-business/' + encodeURIComponent(businessId) + '/files/' + encodeURIComponent(file.id) + '/preview';
    const download = '/api/household-business/' + encodeURIComponent(businessId) + '/files/' + encodeURIComponent(file.id) + '/download';
    if (file.file_kind === 'IMAGE') return '<a class="business-media-thumb" href="' + preview + '" target="_blank" rel="noopener"><img src="' + preview + '" alt=""></a>';
    return '<a class="business-media-thumb is-document" href="' + download + '" title="' + esc(file.original_name || 'Tài liệu') + '"><i class="fa-solid fa-file-lines"></i></a>';
  }

  function detailField(label, value) {
    if (value === null || value === undefined || String(value).trim() === '') return '';
    return '<div class="person-info-field"><span>' + esc(label) + '</span><div class="person-info-value">' + esc(value) + '</div></div>';
  }

  function fileGallery(title, files, businessId, kind = '') {
    const countText = files.length ? num(files.length) + ' tệp' : 'Chưa có';
    const icon = kind === 'image' ? 'fa-images' : 'fa-file-lines';
    if (!files.length) {
      return '<details class="business-media-panel"><summary><span><i class="fa-solid ' + icon + '"></i>' + esc(title) + '</span><strong>' + countText + '</strong></summary><div class="business-media-empty">Chưa có dữ liệu</div></details>';
    }
    const cards = files.map(file => {
      const preview = '/api/household-business/' + encodeURIComponent(businessId) + '/files/' + encodeURIComponent(file.id) + '/preview';
      const download = '/api/household-business/' + encodeURIComponent(businessId) + '/files/' + encodeURIComponent(file.id) + '/download';
      const thumb = file.file_kind === 'IMAGE' ? '<a href="' + preview + '" target="_blank" rel="noopener"><img src="' + preview + '" alt=""></a>' : '<i class="fa-solid fa-file-lines business-file-icon"></i>';
      const del = can('household_business', 'delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" title="Xóa" data-platform-action="businessHouseholds.fileDelete" data-business-id="' + Number(businessId) + '" data-file-id="' + Number(file.id) + '"><i class="fa-solid fa-trash"></i></button>' : '';
      return '<div class="business-file-row">' + thumb + '<div class="business-file-meta"><strong>' + esc(file.original_name || '') + '</strong><span>' + esc(file.category || '') + ' - ' + date(file.created_at) + ' - ' + esc(file.uploaded_by || '') + '</span></div><div class="business-file-actions"><a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="' + preview + '" title="Xem"><i class="fa-solid fa-magnifying-glass-plus"></i></a><a class="btn btn-sm btn-outline-secondary" href="' + download + '" title="Tải xuống"><i class="fa-solid fa-download"></i></a>' + del + '</div></div>';
    }).join('');
    return '<details class="business-media-panel"><summary><span><i class="fa-solid ' + icon + '"></i>' + esc(title) + '</span><strong>' + countText + '</strong></summary><div class="business-file-list">' + cards + '</div></details>';
  }

  function section(title, rows) {
    const items = rows.filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== '').map(([label, value]) => '<div class="person-info-field"><span>' + esc(label) + '</span><div class="person-info-value">' + esc(value) + '</div></div>').join('');
    return items ? '<section class="person-info-section"><div class="person-info-section-title"><i class="fa-solid fa-circle-info"></i><h4>' + esc(title) + '</h4></div><div class="person-info-grid">' + items + '</div></section>' : '';
  }

  async function deleteBusinessFile(businessId, fileId) {
    if (!can('household_business', 'delete')) return show('Tài khoản hiện tại không có quyền xóa file', 'warning');
    if (!await confirmAction({ title: 'Xác nhận xóa file', message: 'Xóa file đính kèm này?', confirmLabel: 'Xóa file', tone: 'danger' })) return;
    await request('/api/household-business/' + encodeURIComponent(businessId) + '/files/' + encodeURIComponent(fileId), { method: 'DELETE' });
    show('Đã xóa file đính kèm');
    if (state.currentDetailHouseholdId) showDetail(state.currentDetailHouseholdId);
  }

  async function remove(id) {
    if (!can('household_business', 'delete')) return show('Tài khoản hiện tại không có quyền xóa', 'warning');
    if (!await confirmAction({ title: 'Xác nhận xóa hoạt động', message: 'Xóa hoạt động sản xuất/kinh doanh này? Dữ liệu hộ gia đình và nhân khẩu không bị xóa.', confirmLabel: 'Xóa hoạt động', tone: 'danger' })) return;
    let householdId = state.currentDetailHouseholdId;
    try {
      const before = await request('/api/household-business/' + encodeURIComponent(id), { cacheTtl: 0 });
      householdId = Number(before.household_id || householdId || 0) || householdId;
    } catch (error) {}
    await request('/api/household-business/' + encodeURIComponent(id), { method: 'DELETE' });
    show('Đã xóa hoạt động sản xuất/kinh doanh');
    load();
    if (householdId) showDetail(householdId);
    renderDashboard();
  }

  async function renderDashboard() {
    const grid = $('#dashboardKpis');
    if (!grid || !can('household_business', 'read')) return;
    try {
      const data = await request('/api/household-business/dashboard', { cacheTtl: 12000 });
      const metrics = data.metrics || {};
      let host = $('#businessDashboardExtension');
      if (!host) {
        const charts = $('.dashboard-chart-grid');
        if (charts) charts.insertAdjacentHTML('beforeend', '<article id="businessDashboardExtension" class="dashboard-panel"><div class="dashboard-panel-head"><h3>Hộ sản xuất & kinh doanh</h3><span class="dashboard-filter-pill">SX/KD</span></div><div class="dashboard-chart-body"></div></article>');
        host = $('#businessDashboardExtension');
      }
      const cards = [['Hộ sản xuất', metrics.production_households, 'hộ'], ['Hộ kinh doanh', metrics.business_households, 'hộ'], ['Vừa SX/KD', metrics.production_business_households, 'hộ'], ['Tổng lao động', metrics.business_worker_total, 'người'], ['OCOP', metrics.ocop_households, 'hộ'], ['Có ATTP', metrics.food_safety_households, 'hộ'], ['Có BHXH', metrics.social_insurance_households, 'hộ'], ['Lao động BHXH', metrics.insured_worker_total, 'người']];
      host.querySelector('.dashboard-chart-body').innerHTML = '<div class="dashboard-metric-stack">' + cards.map(([label, value, unit]) => '<div class="dashboard-metric-line"><span>' + esc(label) + '</span><strong>' + num(value) + ' ' + esc(unit) + '</strong></div>').join('') + '</div>';
    } catch (error) { console.warn('[household-business-dashboard]', error); }
  }

  function addReportOptions() {
    const select = $('#reportTypeSelect');
    if (!select || select.querySelector('option[value="household-business-production"]')) return;
    [['household-business-production','Danh sách hộ sản xuất'],['household-business-trade','Danh sách hộ kinh doanh'],['household-business-sector','Theo ngành nghề SX/KD'],['household-business-status','Theo trạng thái SX/KD'],['household-business-gis','Theo khu vực GIS SX/KD'],['household-business-ocop','Hộ OCOP'],['household-business-food-safety','Hộ có ATTP'],['household-business-social-insurance','Hộ tham gia BHXH'],['household-business-economic-type','Theo loại hình kinh tế'],['household-business-scale','Theo quy mô hoạt động'],['household-business-product','Theo sản phẩm chính']].forEach(([value, label]) => select.insertAdjacentHTML('beforeend', '<option value="' + value + '">' + esc(label) + '</option>'));
  }

  function debounce(fn, wait) { let timer; return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), wait); }; }

  window.loadHouseholdBusiness = load;
  window.openHouseholdBusinessForm = openForm;
  window.showHouseholdBusiness = showDetail;
  window.deleteHouseholdBusiness = remove;
  window.addHouseholdBusinessActivity = openFormForHousehold;
  window.deleteHouseholdBusinessFile = deleteBusinessFile;
  window.selectHouseholdBusinessActivity = selectBusinessActivity;
  window.selectHouseholdBusinessDetailTab = selectBusinessDetailTab;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind); else bind();
})();
