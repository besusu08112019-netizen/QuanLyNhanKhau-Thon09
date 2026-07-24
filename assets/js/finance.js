(function () {
  'use strict';

  const API = '/api/finance';
  const state = { ready: false, page: 1, pageSize: 20, search: '', transaction_type: '', fund_id: '', category_id: '', status: '', date_from: '', date_to: '', sort: 'transaction_date', direction: 'DESC', catalogs: null, current: null };
  const $ = (selector, root = document) => root.querySelector(selector);
  const safe = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  const money = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
  const date = value => value ? new Date(String(value) + 'T00:00:00').toLocaleDateString('vi-VN') : '--';
  const text = (value, empty = '--') => String(value ?? '').trim() || empty;
  const toast = (message, type = 'info') => typeof window.showToast === 'function' ? window.showToast(message, type) : console[type === 'danger' ? 'error' : 'log'](message);
  const run = fn => Promise.resolve().then(fn).catch(error => toast(error.message || 'Thao tac khong thanh cong', 'danger'));
  const debounce = (fn, delay) => { let timer; return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); }; };

  async function request(url, options = {}) {
    if (typeof window.api === 'function' && !(options.body instanceof FormData)) return window.api(url, options);
    const headers = { Accept: 'application/json' };
    if (window.App?.token) headers.Authorization = `Bearer ${window.App.token}`;
    if (window.App?.csrfToken) headers['X-CSRF-Token'] = window.App.csrfToken;
    const init = { method: options.method || 'GET', headers };
    if (options.body instanceof FormData) init.body = options.body;
    else if (options.body) { headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(options.body); }
    const response = await fetch(url, init);
    const payload = await response.json().catch(() => null);
    if (!response.ok || payload?.ok === false || payload?.success === false) throw new Error(payload?.error?.message || payload?.message || 'Khong tai duoc du lieu');
    return payload?.data ?? payload;
  }

  const can = action => {
    const service = window.Thon09Platform?.permissions;
    if (service?.can) return service.can('finance', action, window.App?.user);
    return typeof window.thon09CanAccess === 'function' ? window.thon09CanAccess('finance', action) : true;
  };
  const openModal = id => window.Thon09Platform?.modals?.open?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.show();
  const closeModal = id => window.Thon09Platform?.modals?.close?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.hide();

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
  document.addEventListener('thon09:screen-change', event => { if (event.detail?.screen === 'finance') run(load); });

  function init() {
    registerActions();
    if ($('#financeScreen')?.classList.contains('active') || window.App?.screen === 'finance') run(load);
  }

  function registerActions() {
    if (window.__thon09FinanceActionsRegistered || !window.Thon09Platform?.actions) return;
    window.__thon09FinanceActionsRegistered = true;
    window.Thon09Platform.actions
      .register({ key: 'finance.create', handler: ({ dataset }) => run(() => openForm(null, dataset.type || 'INCOME')) })
      .register({ key: 'finance.detail', handler: ({ dataset }) => run(() => openDetail(Number(dataset.id || 0))) })
      .register({ key: 'finance.edit', handler: ({ dataset }) => run(() => openForm(Number(dataset.id || 0))) })
      .register({ key: 'finance.delete', handler: ({ dataset }) => run(() => remove(Number(dataset.id || 0))) })
      .register({ key: 'finance.reset', handler: () => run(reset) })
      .register({ key: 'finance.sort', handler: ({ dataset }) => run(() => sortBy(dataset.financeSort)) })
      .register({ key: 'finance.page', handler: ({ dataset, target }) => !target.disabled && run(() => { state.page = Number(dataset.page || 1); return load(); }) })
      .register({ key: 'finance.attachment.delete', handler: ({ dataset }) => run(() => deleteAttachment(Number(dataset.id || 0))) })
      .register({ key: 'finance.export', handler: ({ dataset }) => exportReport(dataset.format || 'excel') });
  }

  function shell() {
    const host = $('#financeScreen');
    if (!host || state.ready) return;
    host.classList.remove('module-placeholder-screen');
    host.innerHTML = [
      '<section id="financeDashboard" class="agri-kpi-grid" aria-label="Thong ke thu chi"></section>',
      '<section class="agri-filter-card" aria-label="Bo loc thu chi"><div class="agri-filter-row">',
      '<div class="agri-field agri-search-field"><label for="financeSearch">Tim kiem</label><div class="module-search-input-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="financeSearch" class="form-control" placeholder="Ma phieu, so chung tu, nguoi nop/nhan, noi dung..."></div></div>',
      '<div class="agri-field"><label for="financeTypeFilter">Loai</label><select id="financeTypeFilter" class="form-select"></select></div>',
      '<div class="agri-field"><label for="financeFundFilter">Quy</label><select id="financeFundFilter" class="form-select"></select></div>',
      '<div class="agri-field"><label for="financeCategoryFilter">Danh muc</label><select id="financeCategoryFilter" class="form-select"></select></div>',
      '<div class="agri-field"><label for="financeStatusFilter">Trang thai</label><select id="financeStatusFilter" class="form-select"></select></div>',
      '<div class="agri-field"><label for="financeDateFrom">Tu ngay</label><input id="financeDateFrom" class="form-control" type="date"></div>',
      '<div class="agri-field"><label for="financeDateTo">Den ngay</label><input id="financeDateTo" class="form-control" type="date"></div>',
      '<div class="agri-field module-page-size-field"><label for="financePageSize">Hien thi</label><select id="financePageSize" class="form-select"><option>20</option><option>50</option><option>100</option></select></div>',
      '<div class="agri-field agri-actions"><button class="btn btn-outline-secondary" type="button" data-platform-action="finance.reset"><i class="fa-solid fa-rotate-right"></i></button><button class="btn btn-outline-success" type="button" data-platform-action="finance.export" data-format="excel"><i class="fa-solid fa-file-excel"></i> Excel</button><button class="btn btn-outline-danger" type="button" data-platform-action="finance.export" data-format="pdf"><i class="fa-solid fa-file-pdf"></i> PDF</button></div>',
      '</div></section>',
      '<section class="module-list-card household-list-card"><div class="module-list-head"><div><h3>So thu chi</h3><span id="financeTotalCount">Tong so: 0 phieu</span></div><div class="d-flex gap-2 flex-wrap"><button class="btn btn-success" type="button" data-platform-action="finance.create" data-type="INCOME"><i class="fa-solid fa-plus"></i> Phieu thu</button><button class="btn btn-primary" type="button" data-platform-action="finance.create" data-type="EXPENSE"><i class="fa-solid fa-minus"></i> Phieu chi</button></div></div><div id="financeLoadState" class="text-muted small px-3 pb-2 d-none"></div><div class="table-responsive"><table class="table module-table align-middle mb-0"><thead><tr><th data-platform-action="finance.sort" data-finance-sort="transaction_code">Ma phieu</th><th data-platform-action="finance.sort" data-finance-sort="transaction_date">Ngay</th><th data-platform-action="finance.sort" data-finance-sort="transaction_type">Loai</th><th data-platform-action="finance.sort" data-finance-sort="fund">Quy</th><th data-platform-action="finance.sort" data-finance-sort="category">Danh muc</th><th data-platform-action="finance.sort" data-finance-sort="amount">So tien</th><th>Chung tu</th><th data-platform-action="finance.sort" data-finance-sort="status">Trang thai</th><th class="text-end">Thao tac</th></tr></thead><tbody id="financeRows"></tbody></table></div><div id="financePager" class="pager module-pager"></div></section>',
      formModal(),
      detailModal()
    ].join('');
    bind();
    state.ready = true;
  }

  function formModal() {
    return '<div class="modal fade" id="financeModal" tabindex="-1"><div class="modal-dialog modal-xl"><form id="financeForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">Phieu thu chi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="row g-3"><div class="col-md-3"><label class="form-label">Loai phieu</label><select name="transaction_type" id="financeTypeInput" class="form-select" required></select></div><div class="col-md-3"><label class="form-label">Quy</label><select name="fund_id" id="financeFundInput" class="form-select" required></select></div><div class="col-md-3"><label class="form-label">Danh muc</label><select name="category_id" id="financeCategoryInput" class="form-select"></select></div><div class="col-md-3"><label class="form-label">Trang thai</label><select name="status" id="financeStatusInput" class="form-select"></select></div><div class="col-md-4"><label class="form-label">So tien</label><input name="amount" class="form-control" type="number" min="0" step="1000" required></div><div class="col-md-4"><label class="form-label">Ngay thu chi</label><input name="transaction_date" type="date" class="form-control" required></div><div class="col-md-4"><label class="form-label">So chung tu</label><input name="receipt_number" class="form-control" maxlength="100"></div><div class="col-md-4"><label class="form-label">Nguoi nop</label><input name="payer_name" class="form-control" maxlength="180"></div><div class="col-md-4"><label class="form-label">Nguoi nhan</label><input name="receiver_name" class="form-control" maxlength="180"></div><div class="col-md-4"><label class="form-label">Hinh thuc</label><select name="payment_method" id="financePaymentMethodInput" class="form-select"></select></div><div class="col-md-4"><label class="form-label">Nguon lien ket</label><input name="source_module" class="form-control" maxlength="80" placeholder="VD: contributions"></div><div class="col-md-4"><label class="form-label">ID nguon</label><input name="source_id" class="form-control" type="number" min="1"></div><div class="col-md-4"><label class="form-label">File chung tu</label><input id="financeFiles" class="form-control" type="file" multiple accept="application/pdf,.pdf,.doc,.docx,.xls,.xlsx,image/jpeg,image/png,image/webp"></div><div class="col-12"><label class="form-label">Noi dung</label><textarea name="description" class="form-control" rows="3"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Dong</button><button class="btn btn-primary" type="submit">Luu phieu</button></div></form></div></div>';
  }

  function detailModal() {
    return '<div class="modal fade" id="financeDetailModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><h5 id="financeDetailTitle" class="modal-title">Chi tiet thu chi</h5><small id="financeDetailSub" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div id="financeDetailBody" class="modal-body"></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Dong</button></div></div></div></div>';
  }

  function bind() {
    $('#financeSearch')?.addEventListener('input', debounce(() => run(() => { state.page = 1; readFilters(); return load(); }), 350));
    ['financeTypeFilter','financeFundFilter','financeCategoryFilter','financeStatusFilter','financeDateFrom','financeDateTo'].forEach(id => $('#' + id)?.addEventListener('change', () => run(() => { state.page = 1; readFilters(); return load(); })));
    $('#financePageSize')?.addEventListener('change', event => run(() => { state.pageSize = Number(event.target.value || 20); state.page = 1; return load(); }));
    $('#financeTypeInput')?.addEventListener('change', () => fillCategoryInputs());
    $('#financeForm')?.addEventListener('submit', event => { event.preventDefault(); run(() => save(event.currentTarget)); });
  }

  async function catalogs() {
    if (state.catalogs) return state.catalogs;
    state.catalogs = await request(API + '/catalogs', { cacheTtl: 60000 });
    fill($('#financeTypeFilter'), state.catalogs.types, 'Tat ca');
    fill($('#financeTypeInput'), state.catalogs.types, '');
    fill($('#financeFundFilter'), state.catalogs.funds, 'Tat ca');
    fill($('#financeFundInput'), state.catalogs.funds, 'Chon quy');
    fill($('#financeStatusFilter'), state.catalogs.statuses, 'Tat ca');
    fill($('#financeStatusInput'), state.catalogs.statuses, '');
    fill($('#financePaymentMethodInput'), state.catalogs.payment_methods, '');
    fillCategoryFilters();
    return state.catalogs;
  }

  function fill(select, items = [], first = '') {
    if (!select) return;
    const current = select.value;
    select.innerHTML = first ? `<option value="">${safe(first)}</option>` : '';
    items.forEach(item => { const option = document.createElement('option'); option.value = item.value; option.textContent = item.label; select.appendChild(option); });
    if ([...select.options].some(option => option.value === current)) select.value = current;
  }

  function categoriesFor(type) {
    return (state.catalogs?.categories || []).filter(item => !type || item.transaction_type === type);
  }

  function fillCategoryFilters() {
    fill($('#financeCategoryFilter'), categoriesFor(state.transaction_type), 'Tat ca');
  }

  function fillCategoryInputs() {
    const current = $('#financeCategoryInput')?.value || '';
    fill($('#financeCategoryInput'), categoriesFor($('#financeTypeInput')?.value || ''), 'Chon danh muc');
    if ([...($('#financeCategoryInput')?.options || [])].some(option => option.value === current)) $('#financeCategoryInput').value = current;
  }

  function readFilters() {
    state.search = $('#financeSearch')?.value.trim() || '';
    state.transaction_type = $('#financeTypeFilter')?.value || '';
    state.fund_id = $('#financeFundFilter')?.value || '';
    state.category_id = $('#financeCategoryFilter')?.value || '';
    state.status = $('#financeStatusFilter')?.value || '';
    state.date_from = $('#financeDateFrom')?.value || '';
    state.date_to = $('#financeDateTo')?.value || '';
    state.pageSize = Number($('#financePageSize')?.value || state.pageSize || 20);
  }
  function params() { readFilters(); return new URLSearchParams({ page: state.page, pageSize: state.pageSize, search: state.search, transaction_type: state.transaction_type, fund_id: state.fund_id, category_id: state.category_id, status: state.status, date_from: state.date_from, date_to: state.date_to, sort: state.sort, direction: state.direction }); }
  function setLoading(on, message = '') {
    const el = $('#financeLoadState');
    if (!el) return;
    el.classList.toggle('d-none', !on && !message);
    el.textContent = on ? (message || 'Dang tai du lieu...') : message;
  }
  async function load() {
    if (!$('#financeScreen')) return;
    registerActions();
    shell();
    setLoading(true);
    try {
      await catalogs();
      fillCategoryFilters();
      const query = params();
      const [list, dashboard] = await Promise.all([request(API + '?' + query), request(API + '/dashboard?' + query, { cacheTtl: 15000 })]);
      renderDashboard(dashboard);
      renderRows(list);
      renderPager(list);
      setLoading(false, '');
      window.thon09ApplyAccessControls?.();
      window.thon09SyncResponsiveTableLabels?.($('#financeScreen'));
    } catch (error) {
      renderDashboard({});
      renderRows({ items: [], total: 0 });
      renderPager({});
      setLoading(false, error.message || 'Khong tai duoc thu chi');
      toast(error.message || 'Khong tai duoc thu chi', 'danger');
    }
  }

  function renderDashboard(data = {}) {
    const m = data.metrics || {};
    const cards = [['Tong thu', 'fa-arrow-trend-up', m.total_income || 0, 'success'], ['Tong chi', 'fa-arrow-trend-down', m.total_expense || 0, 'danger'], ['Chenh lech', 'fa-scale-balanced', m.balance || 0, Number(m.balance || 0) >= 0 ? 'primary' : 'warning'], ['So phieu', 'fa-receipt', m.total || 0, 'secondary']];
    $('#financeDashboard').innerHTML = cards.map(card => `<article class="agri-kpi-card"><span class="text-${card[3]}"><i class="fa-solid ${card[1]}"></i></span><div><strong>${money(card[2])}</strong><small>${safe(card[0])}</small></div></article>`).join('');
  }

  function renderRows(data = {}) {
    const rows = data.items || [], tbody = $('#financeRows');
    $('#financeTotalCount').textContent = `Tong so: ${money(data.total || 0)} phieu`;
    if (!rows.length) { tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Chua co phieu thu chi phu hop bo loc.</td></tr>'; return; }
    tbody.innerHTML = rows.map(item => {
      const typeClass = item.transaction_type === 'EXPENSE' ? 'danger' : 'success';
      const actions = [`<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="finance.detail" data-id="${item.id}" title="Xem"><i class="fa-solid fa-eye"></i></button>`, can('update') ? `<button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="finance.edit" data-id="${item.id}" title="Sua"><i class="fa-solid fa-pen"></i></button>` : '', can('delete') ? `<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="finance.delete" data-id="${item.id}" title="Xoa"><i class="fa-solid fa-trash"></i></button>` : ''].filter(Boolean).join(' ');
      return `<tr><td><strong>${safe(item.transaction_code)}</strong><br><small class="text-muted">${safe(text(item.receipt_number, ''))}</small></td><td>${date(item.transaction_date)}</td><td><span class="badge bg-${typeClass}">${safe(item.type_label)}</span></td><td>${safe(text(item.fund_name))}</td><td>${safe(text(item.category_name))}</td><td class="fw-semibold">${money(item.amount)}</td><td>${money(item.attachment_count || 0)}</td><td><span class="badge bg-${item.status === 'CANCELLED' ? 'secondary' : 'primary'}">${safe(item.status_label)}</span></td><td class="text-end">${actions}</td></tr>`;
    }).join('');
  }

  function renderPager(data = {}) { const host = $('#financePager'), totalPages = Number(data.totalPages || 1), page = Number(data.page || state.page || 1); state.page = page; host.innerHTML = totalPages <= 1 ? '' : `<div class="d-flex gap-2 justify-content-end flex-wrap"><button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="finance.page" data-page="${Math.max(1, page - 1)}" ${page <= 1 ? 'disabled' : ''}>Truoc</button><span class="px-2">${page} / ${totalPages}</span><button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="finance.page" data-page="${Math.min(totalPages, page + 1)}" ${page >= totalPages ? 'disabled' : ''}>Sau</button></div>`; }

  async function openForm(id = null, type = 'INCOME') {
    if (id && !can('update')) return toast('Khong co quyen sua', 'warning');
    if (!id && !can('create')) return toast('Khong co quyen them', 'warning');
    await catalogs();
    const form = $('#financeForm');
    form.reset(); form.elements.id.value = ''; form.elements.transaction_type.value = type; form.elements.status.value = 'POSTED'; form.elements.payment_method.value = 'CASH'; form.elements.transaction_date.value = new Date().toISOString().slice(0, 10); $('#financeFiles').value = ''; fillCategoryInputs();
    if (id) { const item = await request(API + '/' + id); Object.entries(item).forEach(([key, value]) => { if (form.elements[key]) form.elements[key].value = value ?? ''; }); form.elements.id.value = item.id; fillCategoryInputs(); if (item.category_id) form.elements.category_id.value = item.category_id; }
    openModal('financeModal');
  }

  async function save(form) {
    const body = Object.fromEntries(new FormData(form).entries());
    const id = Number(body.id || 0); delete body.id;
    const saved = await request(id ? API + '/' + id : API, { method: id ? 'PUT' : 'POST', body });
    for (const file of Array.from($('#financeFiles')?.files || [])) { const upload = new FormData(); upload.append('file', file); await request(API + '/' + saved.id + '/attachments', { method: 'POST', body: upload }); }
    closeModal('financeModal'); toast('Da luu phieu thu chi', 'success'); await load();
  }

  async function openDetail(id) {
    const item = await request(API + '/' + id);
    state.current = item;
    $('#financeDetailTitle').textContent = `${item.type_label || 'Thu chi'} ${item.transaction_code || ''}`.trim();
    $('#financeDetailSub').textContent = [date(item.transaction_date), item.fund_name, item.status_label].filter(Boolean).join(' - ');
    const files = (item.attachments || []).map(file => `<div class="work-task-file"><i class="fa-solid ${file.file_kind === 'PDF' ? 'fa-file-pdf' : file.file_kind === 'IMAGE' ? 'fa-file-image' : 'fa-file-lines'}"></i><a href="${safe(file.preview_url)}" target="_blank" rel="noopener">${safe(file.original_name)}</a><small>${safe(file.file_kind)}</small>${can('delete') ? `<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="finance.attachment.delete" data-id="${file.id}"><i class="fa-solid fa-trash"></i></button>` : ''}</div>`).join('') || '<div class="text-muted">Chua co chung tu dinh kem</div>';
    $('#financeDetailBody').innerHTML = `<div class="row g-3"><div class="col-md-7"><dl class="row mb-0"><dt class="col-sm-3">Loai</dt><dd class="col-sm-9">${safe(item.type_label)}</dd><dt class="col-sm-3">So tien</dt><dd class="col-sm-9 fw-semibold">${money(item.amount)}</dd><dt class="col-sm-3">Quy</dt><dd class="col-sm-9">${safe(text(item.fund_name))}</dd><dt class="col-sm-3">Danh muc</dt><dd class="col-sm-9">${safe(text(item.category_name))}</dd><dt class="col-sm-3">Nguoi nop</dt><dd class="col-sm-9">${safe(text(item.payer_name))}</dd><dt class="col-sm-3">Nguoi nhan</dt><dd class="col-sm-9">${safe(text(item.receiver_name))}</dd><dt class="col-sm-3">So chung tu</dt><dd class="col-sm-9">${safe(text(item.receipt_number))}</dd><dt class="col-sm-3">Noi dung</dt><dd class="col-sm-9">${safe(text(item.description))}</dd></dl></div><div class="col-md-5"><h6>Chung tu dinh kem</h6><div class="work-task-files">${files}</div></div></div>`;
    openModal('financeDetailModal');
  }

  async function deleteAttachment(fileId) { if (!state.current?.id || !fileId) return; if (!window.confirm('Xoa chung tu nay?')) return; await request(API + '/' + state.current.id + '/attachments/' + fileId, { method: 'DELETE' }); await openDetail(state.current.id); }
  async function remove(id) { if (!can('delete')) return toast('Khong co quyen xoa', 'warning'); if (!window.confirm('Xoa phieu thu chi nay?')) return; await request(API + '/' + id, { method: 'DELETE' }); toast('Da xoa phieu thu chi', 'success'); await load(); }
  async function reset() { ['financeSearch','financeTypeFilter','financeFundFilter','financeCategoryFilter','financeStatusFilter','financeDateFrom','financeDateTo'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; }); Object.assign(state, { page: 1, search: '', transaction_type: '', fund_id: '', category_id: '', status: '', date_from: '', date_to: '', sort: 'transaction_date', direction: 'DESC' }); fillCategoryFilters(); await load(); }
  function sortBy(field) { if (!field) return; state.direction = state.sort === field && state.direction === 'ASC' ? 'DESC' : 'ASC'; state.sort = field; return load(); }
  function exportReport(format) { const query = params(); window.open(API + (format === 'pdf' ? '/export-pdf?' : '/export-excel?') + query.toString(), '_blank', 'noopener'); }

  window.loadFinance = load;
})();
