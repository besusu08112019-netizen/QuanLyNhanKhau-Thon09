(() => {
  const state = { page: 1, pageSize: 20, search: '', business_type: '', sector: '', status: '', license: '', tax: '', located: '', sort: 'household_code', direction: 'ASC', catalogs: null };
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
  const num = value => Number(value || 0).toLocaleString('vi-VN');
  const date = value => {
    if (!value) return '';
    const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
    return match ? `${match[3]}/${match[2]}/${match[1]}` : String(value);
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

  function bind() {
    if ($('#businessHouseholdModal') && window.bootstrap) window.App.modals.businessHousehold = new bootstrap.Modal($('#businessHouseholdModal'));
    $('#businessHouseholdAddBtn')?.addEventListener('click', () => openForm());
    $('#businessHouseholdForm')?.addEventListener('submit', save);
    $('#businessOcopCheck')?.addEventListener('change', toggleConditionalFields);
    $('#businessSocialInsuranceCheck')?.addEventListener('change', toggleConditionalFields);
    $('#businessFoodSafetyCheck')?.addEventListener('change', toggleConditionalFields);
    $('#businessHouseholdSearch')?.addEventListener('input', debounce(() => { state.search = $('#businessHouseholdSearch').value.trim(); state.page = 1; load(); }, 300));
    [['businessHouseholdTypeFilter', 'business_type'], ['businessHouseholdSectorFilter', 'sector'], ['businessHouseholdStatusFilter', 'status'], ['businessHouseholdLicenseFilter', 'license'], ['businessHouseholdTaxFilter', 'tax'], ['businessHouseholdLocationFilter', 'located']].forEach(([id, key]) => {
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
    document.addEventListener('thon09:auth-state', () => {
      applyAccess();
      addReportOptions();
      renderDashboard();
    });
    addReportOptions();
    applyAccess();
    if ($('#businessHouseholdsScreen')?.classList.contains('active')) load();
  }

  async function ensureCatalogs() {
    if (state.catalogs) return state.catalogs;
    state.catalogs = await request('/api/household-business/catalogs', { cacheTtl: 60000 });
    fillCatalogSelect('#businessEconomicTypeSelect', state.catalogs.economic_type);
    fillCatalogSelect('#businessScaleSelect', state.catalogs.business_scale);
    fillCatalogSelect('#businessImageCategory', state.catalogs.image_category);
    fillCatalogSelect('#businessDocumentCategory', state.catalogs.document_category);
    return state.catalogs;
  }

  function fillCatalogSelect(selector, items) {
    const select = $(selector);
    if (!select || !Array.isArray(items)) return;
    const first = select.querySelector('option[value=""]')?.outerHTML || '<option value="">Chọn</option>';
    select.innerHTML = first + items.map(item => '<option value="' + esc(item.value) + '">' + esc(item.label || item.value) + '</option>').join('');
  }

  function toggleConditionalFields() {
    const ocop = !!$('#businessOcopCheck')?.checked;
    $('.business-ocop-fields').forEach(el => el.classList.toggle('d-none', !ocop));
    const insured = $('#businessSocialInsuranceCheck')?.checked;
    const insuredInput = $('#businessHouseholdForm')?.elements?.insured_workers;
    if (insuredInput) insuredInput.disabled = !insured;
    const food = $('#businessFoodSafetyCheck')?.checked;
    ['food_safety_certificate_no','food_safety_expired_date'].forEach(name => { const el = $('#businessHouseholdForm')?.elements?.[name]; if (el) el.disabled = !food; });
  }

  function applyAccess() {
    $('#businessHouseholdAddBtn')?.classList.toggle('d-none', !can('household_business', 'create'));
  }

  async function load() {
    if (!can('household_business', 'read')) return;
    try {
      const params = new URLSearchParams({ page: state.page, pageSize: state.pageSize, sort: state.sort, direction: state.direction });
      ['search', 'business_type', 'sector', 'status', 'license', 'tax', 'located'].forEach(key => { if (state[key]) params.set(key, state[key]); });
      const data = await request('/api/household-business?' + params.toString(), { cacheTtl: 8000 });
      const items = data.items || [];
      const total = Number(data.total || 0);
      const totalEl = $('#businessHouseholdTotalCount');
      if (totalEl) totalEl.innerHTML = 'Tá»•ng sá»‘: <strong>' + num(total) + '</strong> há»™';
      const start = (Number(data.page || state.page) - 1) * Number(data.pageSize || state.pageSize);
      $('#businessHouseholdRows').innerHTML = items.length ? items.map((row, index) => rowHtml(row, start + index + 1)).join('') : '<tr><td colspan="10" class="text-center text-muted py-4">KhÃ´ng cÃ³ dá»¯ liá»‡u</td></tr>';
      renderPager(data);
      if (typeof window.thon09SyncResponsiveTableLabels === 'function') window.thon09SyncResponsiveTableLabels($('#businessHouseholdsScreen') || document);
    } catch (error) {
      show('KhÃ´ng táº£i Ä‘Æ°á»£c danh sÃ¡ch há»™ sáº£n xuáº¥t/kinh doanh: ' + error.message, 'danger');
    }
  }

  function rowHtml(row, index) {
    return '<tr>'
      + '<td>' + index + '</td>'
      + '<td><button class="btn btn-link p-0 fw-semibold" type="button" onclick="window.showHouseholdBusiness(' + Number(row.id || 0) + ')">' + esc(row.household_code) + '</button></td>'
      + '<td>' + esc(row.head_citizen_name) + '</td>'
      + '<td>' + esc(row.business_name || '') + '</td>'
      + '<td><span class="badge text-bg-light">' + esc(row.business_type_label || '') + '</span></td>'
      + '<td>' + esc(row.sector_label || '') + '</td>'
      + '<td>' + num(row.worker_count) + '</td>'
      + '<td><span class="badge text-bg-' + statusTone(row.status) + '">' + esc(row.status_label || row.status || '') + '</span></td>'
      + '<td>' + esc(row.address || '') + '</td>'
      + '<td class="text-end">' + actionButtons(row) + '</td>'
      + '</tr>';
  }

  function actionButtons(row) {
    const id = Number(row.id || 0);
    return [
      '<button class="btn btn-sm btn-outline-secondary" type="button" onclick="window.showHouseholdBusiness(' + id + ')">Xem</button>',
      can('household_business', 'update') ? '<button class="btn btn-sm btn-outline-primary" type="button" onclick="window.openHouseholdBusinessForm(' + id + ')">Sá»­a</button>' : '',
      can('household_business', 'delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" onclick="window.deleteHouseholdBusiness(' + id + ')">XÃ³a</button>' : ''
    ].filter(Boolean).join(' ') || '<span class="text-muted small">Chá»‰ xem</span>';
  }

  function statusTone(status) {
    return ({ ACTIVE: 'success', INACTIVE: 'secondary', SUSPENDED: 'warning' })[String(status || '').toUpperCase()] || 'light';
  }

  function renderPager(data) {
    const host = $('#businessHouseholdPager');
    if (!host) return;
    const totalPages = Math.max(1, Number(data.totalPages || 1));
    const page = Number(data.page || state.page);
    const pages = [];
    for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) pages.push(i);
    host.innerHTML = '<button class="btn btn-sm btn-outline-secondary" ' + (page <= 1 ? 'disabled' : '') + ' data-business-page="' + (page - 1) + '">TrÆ°á»›c</button>'
      + pages.map(item => '<button class="btn btn-sm ' + (item === page ? 'btn-primary' : 'btn-outline-secondary') + '" data-business-page="' + item + '">' + item + '</button>').join('')
      + '<button class="btn btn-sm btn-outline-secondary" ' + (page >= totalPages ? 'disabled' : '') + ' data-business-page="' + (page + 1) + '">Sau</button>';
    host.querySelectorAll('[data-business-page]').forEach(btn => btn.addEventListener('click', () => { state.page = Number(btn.dataset.businessPage); load(); }));
  }

  function sortBy(key) {
    if (state.sort === key) state.direction = state.direction === 'ASC' ? 'DESC' : 'ASC';
    else { state.sort = key; state.direction = 'ASC'; }
    state.page = 1;
    load();
  }

  function resetFilters() {
    Object.assign(state, { page: 1, search: '', business_type: '', sector: '', status: '', license: '', tax: '', located: '' });
    ['businessHouseholdSearch', 'businessHouseholdSectorFilter'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
    ['businessHouseholdTypeFilter', 'businessHouseholdStatusFilter', 'businessHouseholdLicenseFilter', 'businessHouseholdTaxFilter', 'businessHouseholdLocationFilter'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
    load();
  }

  async function openForm(id = null) {
    if (!can('household_business', id ? 'update' : 'create')) return show('TÃ i khoáº£n hiá»‡n táº¡i khÃ´ng cÃ³ quyá»n thá»±c hiá»‡n thao tÃ¡c nÃ y', 'warning');
    const form = $('#businessHouseholdForm');
    if (!form) return;
    form.reset();
    form.elements.id.value = '';
    await ensureCatalogs();
    await fillHouseholdSelect();
    renderExistingFiles([]);
    toggleConditionalFields();
    if (id) {
      const row = await request('/api/household-business/' + encodeURIComponent(id));
      setForm(form, row);
    }
    window.App.modals.businessHousehold?.show();
  }

  async function fillHouseholdSelect(selected = '') {
    const select = $('#businessHouseholdSelect');
    if (!select) return;
    const data = await request('/api/households?pageSize=100', { cacheTtl: 15000 });
    select.innerHTML = '<option value="">Chá»n há»™ gia Ä‘Ã¬nh</option>' + (data.items || []).map(row => '<option value="' + Number(row.id) + '">' + esc(row.household_code) + ' - ' + esc(row.head_citizen_name || '') + '</option>').join('');
    if (selected) select.value = String(selected);
  }

  function setForm(form, row) {
    fillHouseholdSelect(row.household_id).then(() => { form.elements.household_id.value = row.household_id || ''; });
    Object.entries({
      id: row.id, business_name: row.business_name, owner_name: row.owner_name, business_type: row.business_type,
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

  async function save(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const id = form.elements.id.value;
    if (!can('household_business', id ? 'update' : 'create')) return show('TÃ i khoáº£n hiá»‡n táº¡i khÃ´ng cÃ³ quyá»n thá»±c hiá»‡n thao tÃ¡c nÃ y', 'warning');
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    delete payload.id;
    ['is_ocop', 'food_safety_certified', 'social_insurance'].forEach(key => { payload[key] = form.elements[key]?.checked ? '1' : '0'; });
    payload.main_products = splitTags(payload.main_products || '');
    try {
      const saved = await request(id ? '/api/household-business/' + encodeURIComponent(id) : '/api/household-business', { method: id ? 'PUT' : 'POST', body: payload });
      await uploadPendingFiles(saved.id || id);
      window.App.modals.businessHousehold?.hide();
      show('ÄÃ£ lÆ°u thÃ´ng tin há»™ sáº£n xuáº¥t/kinh doanh');
      load();
      renderDashboard();
      if (typeof window.loadDashboard === 'function') window.loadDashboard().catch(() => {});
    } catch (error) {
      show(error.message, 'danger');
    }
  }

  function splitTags(value) {
    return String(value || '').split(/[,;\n]+/).map(item => item.trim()).filter(Boolean);
  }

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
    host.innerHTML = active.length ? active.map(fileChip).join('') : 'Chưa có ảnh hoặc hồ sơ đính kèm';
  }

  function fileChip(file) {
    return '<span class="badge text-bg-light me-1 mb-1">' + esc(file.file_kind === 'IMAGE' ? 'Ảnh' : 'Tài liệu') + ': ' + esc(file.original_name || '') + '</span>';
  }

  async function showDetail(id) {
    try {
      const row = await request('/api/household-business/' + encodeURIComponent(id));
      $('#detailTitle').textContent = 'Chi tiáº¿t há»™ sáº£n xuáº¥t & kinh doanh';
      $('#detailBody').innerHTML = detailHtml(row);
      window.App.modals.detail?.show();
    } catch (error) {
      show(error.message, 'danger');
    }
  }

  function detailHtml(row) {
    const members = (row.members || []).map(member => '<tr><td>' + esc(member.citizen_code || '') + '</td><td>' + esc(member.full_name || '') + '</td><td>' + esc(member.relationship || '') + '</td><td>' + esc(member.phone || '') + '</td></tr>').join('');
    const files = row.files || [];
    const images = files.filter(file => file.file_kind === 'IMAGE');
    const docs = files.filter(file => file.file_kind === 'DOCUMENT');
    return '<article class="person-detail-card person-detail-dynamic">'
      + '<section class="person-detail-hero"><div class="person-detail-identity"><span>Há»“ sÆ¡ sáº£n xuáº¥t/kinh doanh</span><h3>' + esc(row.business_name || row.household_code) + '</h3><div class="person-detail-codes"><strong>MÃ£ há»™: ' + esc(row.household_code) + '</strong><strong>Chá»§ há»™: ' + esc(row.head_citizen_name) + '</strong></div></div></section>'
      + '<div class="person-detail-sections">'
      + section('Thông tin kinh tế', [['Loại hình kinh tế', row.economic_type], ['Quy mô', row.business_scale], ['Sản phẩm chính', (row.main_products || []).join(', ')]])
      + section('OCOP, ATTP và BHXH', [['OCOP', row.is_ocop ? ((row.ocop_product || 'OCOP') + (row.ocop_star ? ' - ' + row.ocop_star + ' sao' : '')) : 'Không'], ['ATTP', row.food_safety_certified ? (row.food_safety_certificate_no || 'Có chứng nhận') : 'Không'], ['Hạn ATTP', date(row.food_safety_expired_date)], ['BHXH', row.social_insurance ? (num(row.insured_workers) + ' lao động') : 'Không']])
      + section('ThÃ´ng tin cÆ¡ sá»Ÿ', [['Loáº¡i hÃ¬nh', row.business_type_label], ['TÃªn cÆ¡ sá»Ÿ', row.business_name], ['Chá»§ cÆ¡ sá»Ÿ', row.owner_name], ['NgÃ y báº¯t Ä‘áº§u', date(row.start_date)], ['Tráº¡ng thÃ¡i', row.status_label], ['Ghi chÃº', row.note]])
      + section('ThÃ´ng tin sáº£n xuáº¥t', [['NgÃ nh sáº£n xuáº¥t', row.production_sector], ['Sá»‘ lao Ä‘á»™ng', num(row.worker_count)], ['Doanh thu nÄƒm', row.annual_revenue ? num(row.annual_revenue) : '']])
      + section('ThÃ´ng tin kinh doanh', [['NgÃ nh kinh doanh', row.business_sector], ['Giáº¥y phÃ©p', row.business_license], ['NgÃ y cáº¥p', date(row.license_date)], ['NÆ¡i cáº¥p', row.license_place]])
      + section('ThÃ´ng tin thuáº¿ vÃ  liÃªn há»‡', [['MÃ£ sá»‘ thuáº¿', row.tax_code], ['Äiá»‡n thoáº¡i', row.phone], ['Email', row.email], ['Äá»‹a chá»‰', row.address], ['GPS', row.latitude && row.longitude ? row.latitude + ', ' + row.longitude : '']])
      + fileGallery('Thư viện ảnh', images, row.id)
      + fileGallery('Danh sách tài liệu', docs, row.id)
      + '</div><div class="mt-3 d-flex gap-2 flex-wrap"><button class="btn btn-outline-primary btn-sm" type="button" onclick="window.showHousehold && window.showHousehold(' + Number(row.household_id) + ')">Má»Ÿ há»“ sÆ¡ Há»™ gia Ä‘Ã¬nh</button></div>'
      + '<section class="person-info-section mt-3"><div class="person-info-section-title"><i class="fa-solid fa-users"></i><h4>Danh sÃ¡ch nhÃ¢n kháº©u thuá»™c há»™</h4></div><div class="table-responsive"><table class="table table-sm"><thead><tr><th>MÃ£ NK</th><th>Há» tÃªn</th><th>Quan há»‡</th><th>Äiá»‡n thoáº¡i</th></tr></thead><tbody>' + (members || '<tr><td colspan="4" class="text-muted text-center">ChÆ°a cÃ³ nhÃ¢n kháº©u</td></tr>') + '</tbody></table></div></section>'
      + '</article>';
  }

  function fileGallery(title, files, businessId) {
    if (!files.length) return '';
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
    showDetail(businessId);
  }

  async function remove(id) {
    if (!can('household_business', 'delete')) return show('TÃ i khoáº£n hiá»‡n táº¡i khÃ´ng cÃ³ quyá»n xÃ³a', 'warning');
    if (!confirm('XÃ³a thÃ´ng tin há»™ sáº£n xuáº¥t/kinh doanh nÃ y? Dá»¯ liá»‡u há»™ gia Ä‘Ã¬nh vÃ  nhÃ¢n kháº©u khÃ´ng bá»‹ xÃ³a.')) return;
    await request('/api/household-business/' + encodeURIComponent(id), { method: 'DELETE' });
    show('ÄÃ£ xÃ³a thÃ´ng tin há»™ sáº£n xuáº¥t/kinh doanh');
    load();
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
        if (charts) charts.insertAdjacentHTML('beforeend', '<article id="businessDashboardExtension" class="dashboard-panel"><div class="dashboard-panel-head"><h3>Há»™ sáº£n xuáº¥t & kinh doanh</h3><span class="dashboard-filter-pill">Sprint 19</span></div><div class="dashboard-chart-body"></div></article>');
        host = $('#businessDashboardExtension');
      }
      const cards = [
        ['Há»™ sáº£n xuáº¥t', metrics.production_households, 'há»™'],
        ['Há»™ kinh doanh', metrics.business_households, 'há»™'],
        ['Vá»«a SX/KD', metrics.production_business_households, 'há»™'],
        ['Tổng lao động', metrics.business_worker_total, 'người'],
        ['OCOP', metrics.ocop_households, 'hộ'],
        ['Có ATTP', metrics.food_safety_households, 'hộ'],
        ['Có BHXH', metrics.social_insurance_households, 'hộ'],
        ['Lao động BHXH', metrics.insured_worker_total, 'người']
      ];
      host.querySelector('.dashboard-chart-body').innerHTML = '<div class="dashboard-metric-stack">' + cards.map(([label, value, unit]) => '<div class="dashboard-metric-line"><span>' + esc(label) + '</span><strong>' + num(value) + ' ' + esc(unit) + '</strong></div>').join('') + '</div>';
    } catch (error) {
      console.warn('[household-business-dashboard]', error);
    }
  }

  function addReportOptions() {
    const select = $('#reportTypeSelect');
    if (!select || select.querySelector('option[value="household-business-production"]')) return;
    [
      ['household-business-production', 'Danh sÃ¡ch há»™ sáº£n xuáº¥t'],
      ['household-business-trade', 'Danh sÃ¡ch há»™ kinh doanh'],
      ['household-business-sector', 'Theo ngÃ nh nghá» SX/KD'],
      ['household-business-status', 'Theo tráº¡ng thÃ¡i SX/KD'],
      ['household-business-gis', 'Theo khu vực GIS SX/KD'],
      ['household-business-ocop', 'Hộ OCOP'],
      ['household-business-food-safety', 'Hộ có ATTP'],
      ['household-business-social-insurance', 'Hộ tham gia BHXH'],
      ['household-business-economic-type', 'Theo loại hình kinh tế'],
      ['household-business-scale', 'Theo quy mô hoạt động'],
      ['household-business-product', 'Theo sản phẩm chính']
    ].forEach(([value, label]) => select.insertAdjacentHTML('beforeend', '<option value="' + value + '">' + esc(label) + '</option>'));
  }

  function debounce(fn, wait) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), wait); };
  }

  window.loadHouseholdBusiness = load;
  window.openHouseholdBusinessForm = openForm;
  window.showHouseholdBusiness = showDetail;
  window.deleteHouseholdBusiness = remove;
  window.deleteHouseholdBusinessFile = deleteBusinessFile;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind); else bind();
})();
