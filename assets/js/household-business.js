(() => {
  const state = { page: 1, pageSize: 20, search: '', business_type: '', economic_type: '', business_scale: '', sector: '', status: '', ocop: '', food_safety: '', social_insurance: '', located: '', sort: 'updated_at', direction: 'DESC', catalogs: null, selectedHousehold: null, currentDetailHouseholdId: null };
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
  const num = value => Number(value || 0).toLocaleString('vi-VN');
  const date = value => {
    if (!value) return '';
    const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
    return match ? match[3] + '/' + match[2] + '/' + match[1] : String(value);
  };
  const can = (module, action) => {
    if (typeof window.thon09CanAccess === 'function') return window.thon09CanAccess(module, action);
    const role = String(window.App?.user?.role || '').toUpperCase();
    if (['SUPER_ADMIN', 'ADMIN'].includes(role)) return true;
    if (role === 'VIEWER') return module === 'household_business' && action === 'read';
    if (role === 'OFFICER') return module === 'household_business' && ['read', 'create', 'update'].includes(action);
    return false;
  };
  const show = (message, type = 'success') => typeof window.showToast === 'function' ? window.showToast(message, type) : console.log(message);
  const request = (url, options = {}) => (window.api || window.thon09Api)(url, options);

  function setValue(selector, value) { const el = $(selector); if (el) el.value = value ?? ''; }
  function setText(selector, value) { const el = $(selector); if (el) el.textContent = value ?? ''; }
  function setDisabled(selector, disabled) { const el = $(selector); if (el) el.disabled = !!disabled; }
  function setFormValue(form, name, value) { if (form?.elements?.[name]) form.elements[name].value = value ?? ''; }

  function bind() {
    const businessModal = $('#businessHouseholdModal');
    if (businessModal && window.bootstrap && window.App?.modals) window.App.modals.businessHousehold = new bootstrap.Modal(businessModal);
    $('#businessHouseholdAddBtn')?.addEventListener('click', () => openForm());
    $('#businessHouseholdForm')?.addEventListener('submit', save);
    $('#businessHouseholdAutocomplete')?.addEventListener('input', debounce(searchHouseholds, 250));
    document.addEventListener('click', event => { if (!event.target.closest('#businessHouseholdSuggestions') && event.target.id !== 'businessHouseholdAutocomplete') hideSuggestions(); });
    ['businessOcopCheck', 'businessSocialInsuranceCheck', 'businessFoodSafetyCheck'].forEach(id => $('#' + id)?.addEventListener('change', toggleConditionalFields));
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
    $('#businessHouseholdFilterReset')?.addEventListener('click', resetFilters);
    $$('[data-business-sort]').forEach(th => th.addEventListener('click', () => sortBy(th.dataset.businessSort)));
    document.addEventListener('thon09:screen-change', event => {
      if (event.detail?.screen === 'businessHouseholds') load();
      if (event.detail?.screen === 'dashboard') setTimeout(renderDashboard, 120);
    });
    document.addEventListener('thon09:auth-state', () => { applyAccess(); addReportOptions(); ensureCatalogs().catch(() => {}); renderDashboard(); });
    addReportOptions();
    applyAccess();
    ensureCatalogs().catch(() => {});
    if ($('#businessHouseholdsScreen')?.classList.contains('active')) load();
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
    const items = uniqueValues(activities.map(item => item.sector_label || item.economic_type));
    if (!items.length) return '<span class="text-muted">Chưa cập nhật</span>';
    const remaining = Math.max(0, activities.length - 1);
    const householdId = Number(row.household_id || row.id || 0);
    return '<div class="business-sector-cell" title="' + esc(items.join('\n')) + '"><span>' + esc(items[0]) + '</span>' + (remaining > 0 ? '<button class="business-more-badge" type="button" onclick="window.showHouseholdBusiness(' + householdId + ')">+' + remaining + ' hoạt động</button>' : '') + '</div>';
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
      + '<td data-label="Mã hộ"><button class="btn btn-link p-0 fw-semibold" type="button" onclick="window.showHouseholdBusiness(' + householdId + ')">' + esc(row.household_code) + '</button></td>'
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
      '<button class="btn btn-sm btn-outline-secondary business-icon-btn" type="button" title="Xem chi tiết" aria-label="Xem chi tiết" onclick="window.showHouseholdBusiness(' + householdId + ')"><i class="fa-solid fa-eye"></i></button>',
      can('household_business', 'update') && activities.length === 1 && firstActivityId ? '<button class="btn btn-sm btn-outline-primary business-icon-btn" type="button" title="Sửa hoạt động" aria-label="Sửa hoạt động" onclick="window.openHouseholdBusinessForm(' + firstActivityId + ')"><i class="fa-solid fa-pen-to-square"></i></button>' : '',
      can('household_business', 'create') ? '<button class="btn btn-sm btn-outline-primary business-icon-btn" type="button" title="Thêm hoạt động" aria-label="Thêm hoạt động" onclick="window.addHouseholdBusinessActivity(' + householdId + ')"><i class="fa-solid fa-plus"></i></button>' : '',
      can('household_business', 'delete') && activities.length === 1 && firstActivityId ? '<button class="btn btn-sm btn-outline-danger business-icon-btn" type="button" title="Xóa hoạt động" aria-label="Xóa hoạt động" onclick="window.deleteHouseholdBusiness(' + firstActivityId + ')"><i class="fa-solid fa-trash"></i></button>' : ''
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
    host.innerHTML = '<button class="btn btn-sm btn-outline-secondary" ' + (page <= 1 ? 'disabled' : '') + ' data-business-page="' + (page - 1) + '">Trước</button>'
      + pages.map(item => '<button class="btn btn-sm ' + (item === page ? 'btn-primary' : 'btn-outline-secondary') + '" data-business-page="' + item + '">' + item + '</button>').join('')
      + '<button class="btn btn-sm btn-outline-secondary" ' + (page >= totalPages ? 'disabled' : '') + ' data-business-page="' + (page + 1) + '">Sau</button>';
    host.querySelectorAll('[data-business-page]').forEach(btn => btn.addEventListener('click', () => { state.page = Number(btn.dataset.businessPage); load(); }));
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
    toggleConditionalFields();
    if (id) {
      const row = await request('/api/household-business/' + encodeURIComponent(id));
      setForm(form, row);
    }
    if (!window.App?.modals?.businessHousehold && $('#businessHouseholdModal') && window.bootstrap && window.App?.modals) window.App.modals.businessHousehold = new bootstrap.Modal($('#businessHouseholdModal'));
    window.App?.modals?.businessHousehold?.show();
  }

  async function searchHouseholds() {
    const input = $('#businessHouseholdAutocomplete');
    const host = $('#businessHouseholdSuggestions');
    if (!input || !host) return;
    const q = input.value.trim();
    state.selectedHousehold = null;
    setValue('#businessHouseholdId', '');
    setText('#businessHouseholdSelected', '');
    if (q.length < 2) { hideSuggestions(); return; }
    const data = await request('/api/household-business/household-search?q=' + encodeURIComponent(q), { cacheTtl: 3000 });
    const items = data.items || [];
    host.innerHTML = items.length ? items.map(item => suggestionHtml(item)).join('') : '<div class="list-group-item text-muted">Không tìm thấy hộ phù hợp</div>';
    host.classList.remove('d-none');
    host.querySelectorAll('[data-household-choice]').forEach(btn => btn.addEventListener('click', () => selectHousehold(items.find(item => String(item.id) === btn.dataset.householdChoice))));
  }

  function suggestionHtml(item) {
    return '<button type="button" class="list-group-item list-group-item-action" data-household-choice="' + Number(item.id) + '">'
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
    setFormValue(form, 'latitude', item.latitude ?? '');
    setFormValue(form, 'longitude', item.longitude ?? '');
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
    setFormValue(form, 'latitude', row.latitude ?? '');
    setFormValue(form, 'longitude', row.longitude ?? '');
    setValue('#businessHouseholdAutocomplete', row.household_code + ' - ' + (row.head_citizen_name || ''));
    setText('#businessHouseholdSelected', row.address || '');
  }

  function hideSuggestions() { $('#businessHouseholdSuggestions')?.classList.add('d-none'); }

  function setForm(form, row) {
    state.selectedHousehold = { id: row.household_id, household_code: row.household_code, head_citizen_name: row.head_citizen_name, address: row.address };
    setValue('#businessHouseholdAutocomplete', row.household_code + ' - ' + (row.head_citizen_name || ''));
    setText('#businessHouseholdSelected', row.address || '');
    Object.entries({
      id: row.id, household_id: row.household_id, business_name: row.business_name, owner_name: row.owner_name, business_type: row.business_type,
      production_sector: row.production_sector, business_sector: row.business_sector, start_date: row.start_date,
      business_license: row.business_license, license_date: row.license_date, license_place: row.license_place,
      tax_code: row.tax_code, worker_count: row.worker_count, annual_revenue: row.annual_revenue,
      phone: row.phone, email: row.email, address: row.address, latitude: row.latitude, longitude: row.longitude,
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
    ['is_ocop', 'food_safety_certified', 'social_insurance'].forEach(key => { payload[key] = form.elements[key]?.checked ? '1' : '0'; });
    payload.main_products = splitTags(payload.main_products || '');
    try {
      const saved = await request(id ? '/api/household-business/' + encodeURIComponent(id) : '/api/household-business', { method: id ? 'PUT' : 'POST', body: payload });
      await uploadPendingFiles(saved.id || id);
      window.App.modals.businessHousehold?.hide();
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
        window.App.modals.detail?.show();
        return;
      }
      state.currentDetailHouseholdId = Number(row.household_id || row.id || id || 0) || null;
      $('#detailTitle').textContent = 'Chi tiết hộ sản xuất & kinh doanh';
      $('#detailBody').innerHTML = detailHtml(row);
      window.App.modals.detail?.show();
    } catch (error) { show(error.message, 'danger'); }
  }

  function detailHtml(row) {
    const activities = activitiesOf(row);
    const members = (row.members || []).map(member => '<tr><td>' + esc(member.citizen_code || '') + '</td><td>' + esc(member.full_name || '') + '</td><td>' + esc(member.relationship || '') + '</td><td>' + esc(member.phone || '') + '</td></tr>').join('');
    const householdId = Number(row.household_id || row.id || 0);
    const addButton = can('household_business', 'create') ? '<button class="btn btn-primary btn-sm" type="button" onclick="window.addHouseholdBusinessActivity(' + householdId + ')"><i class="fa-solid fa-plus"></i> Thêm hoạt động</button>' : '';
    const openHouseholdButton = '<button class="btn btn-outline-primary btn-sm w-100" type="button" onclick="window.showHousehold && window.showHousehold(' + householdId + ')"><i class="fa-solid fa-house-user"></i> Mở hồ sơ hộ gia đình</button>';
    const hasOcop = activities.some(activity => Number(activity.is_ocop || 0) === 1 || activity.is_ocop === true);
    const hasFoodSafety = activities.some(activity => Number(activity.food_safety_certified || 0) === 1 || activity.food_safety_certified === true);
    const hasSocialInsurance = activities.some(activity => Number(activity.social_insurance || 0) === 1 || activity.social_insurance === true);
    const activityTotal = Number(row.business_count || activities.length || 0);
    return '<article class="business-detail-popup">'
      + '<aside class="business-detail-sidebar">'
      + '<section class="business-household-summary">'
      + '<div class="business-detail-kicker">Thông tin hộ</div>'
      + '<h3>' + esc(row.household_code || '') + '</h3>'
      + '<div class="business-detail-owner">' + esc(row.head_citizen_name || '') + '</div>'
      + '<div class="business-summary-grid">'
      + summaryItem('Mã hộ', row.household_code)
      + summaryItem('Chủ hộ', row.head_citizen_name)
      + summaryItem('Địa chỉ', row.address)
      + summaryItem('Điện thoại', row.phone)
      + summaryItem('GPS', row.latitude && row.longitude ? row.latitude + ', ' + row.longitude : 'Không GPS')
      + '</div>'
      + '<div class="business-summary-stats">'
      + statItem('Tổng hoạt động', num(activityTotal))
      + statItem('Tổng lao động', num(row.worker_count || 0))
      + statItem('OCOP', hasOcop ? 'Có' : 'Không')
      + statItem('ATTP', hasFoodSafety ? 'Có' : 'Không')
      + statItem('BHXH', hasSocialInsurance ? 'Có' : 'Không')
      + '</div>'
      + '<div class="business-detail-actions">' + openHouseholdButton + '</div>'
      + '</section>'
      + '<section class="business-household-members">'
      + '<div class="business-detail-kicker">Nhân khẩu</div>'
      + '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Mã NK</th><th>Họ tên</th></tr></thead><tbody>'
      + (members || '<tr><td colspan="2" class="text-muted text-center">Chưa có nhân khẩu</td></tr>')
      + '</tbody></table></div>'
      + '</section>'
      + '</aside>'
      + '<section class="business-detail-main">'
      + '<div class="business-detail-main-head"><div><div class="business-detail-kicker">Hoạt động kinh tế</div><h4>' + num(activityTotal) + ' hoạt động</h4></div>' + addButton + '</div>'
      + '<div class="business-activity-scroll">' + (activities.length ? activities.map((activity, index) => activityCard(activity, index)).join('') : '<div class="business-empty-state">Chưa có hoạt động sản xuất/kinh doanh</div>') + '</div>'
      + '</section>'
      + '</article>';
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
    const edit = can('household_business', 'update') ? '<button class="btn btn-sm btn-outline-primary" type="button" onclick="window.openHouseholdBusinessForm(' + Number(activity.id || 0) + ')">Sửa</button>' : '';
    const del = can('household_business', 'delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" onclick="window.deleteHouseholdBusiness(' + Number(activity.id || 0) + ')">Xóa</button>' : '';
    const title = activity.business_name || activity.economic_type || activity.sector_label || 'Hoạt động số ' + (index + 1);
    return '<article class="business-activity-card">'
      + '<header class="business-activity-card-head"><div><span>Hoạt động số ' + (index + 1) + '</span><h5>' + esc(title) + '</h5></div><div class="business-card-actions">' + edit + del + '</div></header>'
      + '<div class="business-activity-fields">'
      + detailField('Loại hình', activity.business_type_label)
      + detailField('Loại hình kinh tế', activity.economic_type)
      + detailField('Ngành nghề', activity.sector_label)
      + detailField('Quy mô', activity.business_scale)
      + detailField('Sản phẩm chính', (activity.main_products || []).join(', '))
      + detailField('Lao động', num(activity.worker_count || 0))
      + detailField('Trạng thái', activity.status_label)
      + detailField('GPS', activity.latitude && activity.longitude ? activity.latitude + ', ' + activity.longitude : 'Không GPS')
      + detailField('Ngày cập nhật', date(activity.updated_at || activity.created_at))
      + detailField('OCOP', activity.is_ocop ? ((activity.ocop_product || 'Có') + (activity.ocop_star ? ' - ' + activity.ocop_star + ' sao' : '')) : 'Không')
      + detailField('ATTP', activity.food_safety_certified ? (activity.food_safety_certificate_no || 'Có') : 'Không')
      + detailField('BHXH', activity.social_insurance ? (num(activity.insured_workers || 0) + ' lao động') : 'Không')
      + '</div>'
      + '<div class="business-activity-files">'
      + fileGallery('Ảnh', images, activity.id)
      + fileGallery('Tài liệu', documents, activity.id)
      + '</div>'
      + '</article>';
  }

  function detailField(label, value) {
    if (value === null || value === undefined || String(value).trim() === '') return '';
    return '<div class="person-info-field"><span>' + esc(label) + '</span><div class="person-info-value">' + esc(value) + '</div></div>';
  }

  function fileGallery(title, files, businessId) {
    if (!files.length) return section(title, [['Tệp đính kèm', 'Chưa có']]);
    const cards = files.map(file => {
      const preview = '/api/household-business/' + encodeURIComponent(businessId) + '/files/' + encodeURIComponent(file.id) + '/preview';
      const download = '/api/household-business/' + encodeURIComponent(businessId) + '/files/' + encodeURIComponent(file.id) + '/download';
      const thumb = file.file_kind === 'IMAGE' ? '<a href="' + preview + '" target="_blank" rel="noopener"><img src="' + preview + '" alt="" style="width:72px;height:54px;object-fit:cover;border-radius:6px"></a>' : '<i class="fa-solid fa-file-lines fs-3 text-secondary"></i>';
      const del = can('household_business', 'delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" onclick="window.deleteHouseholdBusinessFile(' + Number(businessId) + ',' + Number(file.id) + ')"><i class="fa-solid fa-trash"></i></button>' : '';
      return '<div class="d-flex align-items-center gap-2 border rounded p-2 mb-2">' + thumb + '<div class="flex-grow-1"><div class="fw-semibold">' + esc(file.original_name || '') + '</div><div class="text-muted small">' + esc(file.category || '') + ' - ' + date(file.created_at) + ' - ' + esc(file.uploaded_by || '') + '</div></div><a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="' + preview + '"><i class="fa-solid fa-magnifying-glass-plus"></i></a><a class="btn btn-sm btn-outline-secondary" href="' + download + '"><i class="fa-solid fa-download"></i></a>' + del + '</div>';
    }).join('');
    return '<section class="person-info-section"><div class="person-info-section-title"><i class="fa-solid fa-paperclip"></i><h4>' + esc(title) + '</h4></div>' + cards + '</section>';
  }

  function section(title, rows) {
    const items = rows.filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== '').map(([label, value]) => '<div class="person-info-field"><span>' + esc(label) + '</span><div class="person-info-value">' + esc(value) + '</div></div>').join('');
    return items ? '<section class="person-info-section"><div class="person-info-section-title"><i class="fa-solid fa-circle-info"></i><h4>' + esc(title) + '</h4></div><div class="person-info-grid">' + items + '</div></section>' : '';
  }

  async function deleteBusinessFile(businessId, fileId) {
    if (!can('household_business', 'delete')) return show('Tài khoản hiện tại không có quyền xóa file', 'warning');
    if (!confirm('Xóa file đính kèm này?')) return;
    await request('/api/household-business/' + encodeURIComponent(businessId) + '/files/' + encodeURIComponent(fileId), { method: 'DELETE' });
    show('Đã xóa file đính kèm');
    if (state.currentDetailHouseholdId) showDetail(state.currentDetailHouseholdId);
  }

  async function remove(id) {
    if (!can('household_business', 'delete')) return show('Tài khoản hiện tại không có quyền xóa', 'warning');
    if (!confirm('Xóa hoạt động sản xuất/kinh doanh này? Dữ liệu hộ gia đình và nhân khẩu không bị xóa.')) return;
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
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind); else bind();
})();
