(function () {
  'use strict';

  const API = '/api/documents';
  const state = { ready: false, page: 1, pageSize: 20, search: '', category_id: '', status: '', area_code: '', date_from: '', date_to: '', sort: 'issued_date', direction: 'DESC', catalogs: null, current: null };
  const $ = (selector, root = document) => root.querySelector(selector);
  const safe = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  const text = (value, empty = '--') => String(value ?? '').trim() || empty;
  const number = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
  const date = value => value ? new Date(String(value) + 'T00:00:00').toLocaleDateString('vi-VN') : '--';
  const toast = (message, type = 'info') => typeof window.showToast === 'function' ? window.showToast(message, type) : console[type === 'danger' ? 'error' : 'log'](message);
  const run = fn => Promise.resolve().then(fn).catch(error => toast(error.message || 'Thao tác không thành công', 'danger'));
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
    if (!response.ok || payload?.ok === false || payload?.success === false) throw new Error(payload?.error?.message || payload?.message || 'Không tải được dữ liệu');
    return payload?.data ?? payload;
  }

  const can = action => {
    const service = window.Thon09Platform?.permissions;
    if (service?.can) return service.can('documents', action, window.App?.user);
    return typeof window.thon09CanAccess === 'function' ? window.thon09CanAccess('documents', action) : true;
  };
  const openModal = id => window.Thon09Platform?.modals?.open?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.show();
  const closeModal = id => window.Thon09Platform?.modals?.close?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.hide();

  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('thon09:screen-change', event => { if (event.detail?.screen === 'documents') run(load); });

  function init() {
    registerActions();
    if ($('#documentsScreen')?.classList.contains('active') || window.App?.screen === 'documents') run(load);
  }

  function registerActions() {
    if (window.__thon09DocumentsActionsRegistered || !window.Thon09Platform?.actions) return;
    window.__thon09DocumentsActionsRegistered = true;
    window.Thon09Platform.actions
      .register({ key: 'documents.create', handler: () => run(() => openForm()) })
      .register({ key: 'documents.detail', handler: ({ dataset }) => run(() => openDetail(Number(dataset.id || 0))) })
      .register({ key: 'documents.edit', handler: ({ dataset }) => run(() => openForm(Number(dataset.id || 0))) })
      .register({ key: 'documents.delete', handler: ({ dataset }) => run(() => remove(Number(dataset.id || 0))) })
      .register({ key: 'documents.reset', handler: () => run(reset) })
      .register({ key: 'documents.sort', handler: ({ dataset }) => run(() => sortBy(dataset.documentSort)) })
      .register({ key: 'documents.page', handler: ({ dataset, target }) => !target.disabled && run(() => { state.page = Number(dataset.page || 1); return load(); }) })
      .register({ key: 'documents.attachment.delete', handler: ({ dataset }) => run(() => deleteAttachment(Number(dataset.id || 0))) })
      .register({ key: 'documents.export', handler: ({ dataset }) => exportReport(dataset.format || 'excel') });
  }

  function shell() {
    const host = $('#documentsScreen');
    if (!host || state.ready) return;
    host.classList.remove('module-placeholder-screen');
    host.innerHTML = [
      '<section id="documentsDashboard" class="agri-kpi-grid" aria-label="Thống kê văn bản"></section>',
      '<section class="agri-filter-card" aria-label="Bộ lọc văn bản"><div class="agri-filter-row">',
      '<div class="agri-field agri-search-field"><label for="documentsSearch">Tìm kiếm</label><div class="module-search-input-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="documentsSearch" class="form-control" placeholder="Mã, số văn bản, tiêu đề, người ký..."></div></div>',
      '<div class="agri-field"><label for="documentsCategoryFilter">Loại</label><select id="documentsCategoryFilter" class="form-select"></select></div>',
      '<div class="agri-field"><label for="documentsStatusFilter">Trạng thái</label><select id="documentsStatusFilter" class="form-select"></select></div>',
      '<div class="agri-field"><label for="documentsAreaFilter">Địa bàn</label><input id="documentsAreaFilter" class="form-control" placeholder="Mã khu vực"></div>',
      '<div class="agri-field"><label for="documentsDateFrom">Từ ngày</label><input id="documentsDateFrom" class="form-control" type="date"></div>',
      '<div class="agri-field"><label for="documentsDateTo">Đến ngày</label><input id="documentsDateTo" class="form-control" type="date"></div>',
      '<div class="agri-field module-page-size-field"><label for="documentsPageSize">Hiển thị</label><select id="documentsPageSize" class="form-select"><option>20</option><option>50</option><option>100</option></select></div>',
      '<div class="agri-field agri-actions"><button class="btn btn-outline-secondary" type="button" data-platform-action="documents.reset"><i class="fa-solid fa-rotate-right"></i></button><button class="btn btn-outline-success" type="button" data-platform-action="documents.export" data-format="excel"><i class="fa-solid fa-file-excel"></i> Excel</button><button class="btn btn-outline-danger" type="button" data-platform-action="documents.export" data-format="pdf"><i class="fa-solid fa-file-pdf"></i> PDF</button></div>',
      '</div></section>',
      '<section class="module-list-card household-list-card"><div class="module-list-head"><div><h3>Danh sách văn bản</h3><span id="documentsTotalCount">Tổng số: 0 văn bản</span></div><button class="btn btn-success" type="button" data-platform-action="documents.create"><i class="fa-solid fa-plus"></i> Thêm văn bản</button></div><div class="table-responsive"><table class="table module-table align-middle mb-0"><thead><tr><th data-platform-action="documents.sort" data-document-sort="document_code">Mã</th><th data-platform-action="documents.sort" data-document-sort="document_number">Số văn bản</th><th data-platform-action="documents.sort" data-document-sort="title">Tiêu đề</th><th data-platform-action="documents.sort" data-document-sort="category">Loại</th><th data-platform-action="documents.sort" data-document-sort="issued_date">Ngày ban hành</th><th data-platform-action="documents.sort" data-document-sort="signer_name">Người ký</th><th>File</th><th data-platform-action="documents.sort" data-document-sort="status">Trạng thái</th><th class="text-end">Thao tác</th></tr></thead><tbody id="documentsRows"></tbody></table></div><div id="documentsPager" class="pager module-pager"></div></section>',
      formModal(),
      detailModal()
    ].join('');
    bind();
    state.ready = true;
  }

  function formModal() {
    return '<div class="modal fade" id="documentsModal" tabindex="-1"><div class="modal-dialog modal-xl"><form id="documentsForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">Văn bản</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="row g-3"><div class="col-md-4"><label class="form-label">Số văn bản</label><input name="document_number" class="form-control" required maxlength="120"></div><div class="col-md-4"><label class="form-label">Loại văn bản</label><select name="category_id" id="documentsCategoryInput" class="form-select"></select></div><div class="col-md-4"><label class="form-label">Trạng thái</label><select name="status" id="documentsStatusInput" class="form-select"></select></div><div class="col-12"><label class="form-label">Tiêu đề</label><input name="title" class="form-control" required maxlength="255"></div><div class="col-md-4"><label class="form-label">Người ký</label><input name="signer_name" class="form-control" maxlength="255"></div><div class="col-md-4"><label class="form-label">Ngày ban hành</label><input name="issued_date" type="date" class="form-control" required></div><div class="col-md-4"><label class="form-label">Ngày hiệu lực</label><input name="effective_date" type="date" class="form-control"></div><div class="col-md-4"><label class="form-label">Địa bàn</label><input name="area_code" class="form-control" maxlength="80"></div><div class="col-md-8"><label class="form-label">File PDF/tài liệu</label><input id="documentsFiles" class="form-control" type="file" multiple accept="application/pdf,.pdf,.doc,.docx,image/jpeg,image/png,image/webp"></div><div class="col-12"><label class="form-label">Tóm tắt</label><textarea name="summary" class="form-control" rows="3"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button><button class="btn btn-primary" type="submit">Lưu văn bản</button></div></form></div></div>';
  }

  function detailModal() {
    return '<div class="modal fade" id="documentsDetailModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><h5 id="documentsDetailTitle" class="modal-title">Chi tiết văn bản</h5><small id="documentsDetailSub" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div id="documentsDetailBody" class="modal-body"></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Đóng</button></div></div></div></div>';
  }

  function bind() {
    $('#documentsSearch')?.addEventListener('input', debounce(() => run(() => { state.page = 1; readFilters(); return load(); }), 350));
    ['documentsCategoryFilter','documentsStatusFilter','documentsAreaFilter','documentsDateFrom','documentsDateTo'].forEach(id => $('#' + id)?.addEventListener('change', () => run(() => { state.page = 1; readFilters(); return load(); })));
    $('#documentsPageSize')?.addEventListener('change', event => run(() => { state.pageSize = Number(event.target.value || 20); state.page = 1; return load(); }));
    $('#documentsForm')?.addEventListener('submit', event => { event.preventDefault(); run(() => save(event.currentTarget)); });
  }

  async function catalogs() {
    if (state.catalogs) return state.catalogs;
    state.catalogs = await request(API + '/catalogs', { cacheTtl: 60000 });
    fill($('#documentsCategoryFilter'), state.catalogs.categories, 'Tất cả');
    fill($('#documentsCategoryInput'), state.catalogs.categories, 'Chọn loại');
    fill($('#documentsStatusFilter'), state.catalogs.statuses, 'Tất cả');
    fill($('#documentsStatusInput'), state.catalogs.statuses, '');
    return state.catalogs;
  }

  function fill(select, items = [], first = '') {
    if (!select) return;
    const current = select.value;
    select.innerHTML = first ? `<option value="">${safe(first)}</option>` : '';
    items.forEach(item => { const option = document.createElement('option'); option.value = item.value; option.textContent = item.label; select.appendChild(option); });
    if ([...select.options].some(option => option.value === current)) select.value = current;
  }

  function readFilters() {
    state.search = $('#documentsSearch')?.value.trim() || '';
    state.category_id = $('#documentsCategoryFilter')?.value || '';
    state.status = $('#documentsStatusFilter')?.value || '';
    state.area_code = $('#documentsAreaFilter')?.value.trim() || '';
    state.date_from = $('#documentsDateFrom')?.value || '';
    state.date_to = $('#documentsDateTo')?.value || '';
    state.pageSize = Number($('#documentsPageSize')?.value || state.pageSize || 20);
  }
  function params() { readFilters(); return new URLSearchParams({ page: state.page, pageSize: state.pageSize, search: state.search, category_id: state.category_id, status: state.status, area_code: state.area_code, date_from: state.date_from, date_to: state.date_to, sort: state.sort, direction: state.direction }); }
  async function load() { if (!$('#documentsScreen')) return; shell(); await catalogs(); const query = params(); const [list, dashboard] = await Promise.all([request(API + '?' + query), request(API + '/dashboard?' + query, { cacheTtl: 15000 })]); renderDashboard(dashboard); renderRows(list); renderPager(list); window.thon09ApplyAccessControls?.(); window.thon09SyncResponsiveTableLabels?.($('#documentsScreen')); }

  function renderDashboard(data = {}) {
    const m = data.metrics || {};
    const cards = [['Tổng văn bản', 'fa-file-lines', m.total || 0], ['Đang hiệu lực', 'fa-circle-check', m.active_count || 0], ['Lưu trữ', 'fa-box-archive', m.archived_count || 0], ['30 ngày gần đây', 'fa-clock', m.recent_count || 0]];
    $('#documentsDashboard').innerHTML = cards.map(card => `<article class="agri-kpi-card"><span><i class="fa-solid ${card[1]}"></i></span><div><strong>${number(card[2])}</strong><small>${safe(card[0])}</small></div></article>`).join('');
  }

  function renderRows(data = {}) {
    const rows = data.items || [], tbody = $('#documentsRows');
    $('#documentsTotalCount').textContent = `Tổng số: ${number(data.total || 0)} văn bản`;
    if (!rows.length) { tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Chưa có văn bản phù hợp bộ lọc.</td></tr>'; return; }
    tbody.innerHTML = rows.map(item => { const actions = [`<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="documents.detail" data-id="${item.id}" title="Xem"><i class="fa-solid fa-eye"></i></button>`, can('update') ? `<button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="documents.edit" data-id="${item.id}" title="Sửa"><i class="fa-solid fa-pen"></i></button>` : '', can('delete') ? `<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="documents.delete" data-id="${item.id}" title="Xóa"><i class="fa-solid fa-trash"></i></button>` : ''].filter(Boolean).join(' '); return `<tr><td><strong>${safe(item.document_code)}</strong></td><td>${safe(item.document_number)}</td><td><div class="fw-semibold">${safe(item.title)}</div><small class="text-muted">${safe(text(item.summary, '').slice(0, 90))}</small></td><td>${safe(text(item.category_name))}</td><td>${date(item.issued_date)}</td><td>${safe(text(item.signer_name))}</td><td>${number(item.attachment_count || 0)}</td><td><span class="badge bg-${item.status === 'ARCHIVED' ? 'secondary' : 'success'}">${safe(item.status_label)}</span></td><td class="text-end">${actions}</td></tr>`; }).join('');
  }

  function renderPager(data = {}) { const host = $('#documentsPager'), totalPages = Number(data.totalPages || 1), page = Number(data.page || state.page || 1); state.page = page; host.innerHTML = totalPages <= 1 ? '' : `<div class="d-flex gap-2 justify-content-end flex-wrap"><button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="documents.page" data-page="${Math.max(1, page - 1)}" ${page <= 1 ? 'disabled' : ''}>Trước</button><span class="px-2">${page} / ${totalPages}</span><button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="documents.page" data-page="${Math.min(totalPages, page + 1)}" ${page >= totalPages ? 'disabled' : ''}>Sau</button></div>`; }

  async function openForm(id = null) {
    if (id && !can('update')) return toast('Không có quyền sửa', 'warning');
    if (!id && !can('create')) return toast('Không có quyền thêm', 'warning');
    await catalogs();
    const form = $('#documentsForm');
    form.reset(); form.elements.id.value = ''; form.elements.status.value = 'ACTIVE'; form.elements.issued_date.value = new Date().toISOString().slice(0, 10); $('#documentsFiles').value = '';
    if (id) { const item = await request(API + '/' + id); Object.entries(item).forEach(([key, value]) => { if (form.elements[key]) form.elements[key].value = value ?? ''; }); form.elements.id.value = item.id; }
    openModal('documentsModal');
  }

  async function save(form) {
    const body = Object.fromEntries(new FormData(form).entries());
    const id = Number(body.id || 0); delete body.id;
    const saved = await request(id ? API + '/' + id : API, { method: id ? 'PUT' : 'POST', body });
    for (const file of Array.from($('#documentsFiles')?.files || [])) { const upload = new FormData(); upload.append('file', file); await request(API + '/' + saved.id + '/attachments', { method: 'POST', body: upload }); }
    closeModal('documentsModal'); toast('Đã lưu văn bản', 'success'); await load();
  }

  async function openDetail(id) {
    const item = await request(API + '/' + id);
    state.current = item;
    $('#documentsDetailTitle').textContent = item.title || 'Chi tiết văn bản';
    $('#documentsDetailSub').textContent = [item.document_code, item.document_number, item.status_label].filter(Boolean).join(' - ');
    const files = (item.attachments || []).map(file => `<div class="work-task-file"><i class="fa-solid ${file.file_kind === 'PDF' ? 'fa-file-pdf' : 'fa-file-lines'}"></i><a href="${safe(file.preview_url)}" target="_blank" rel="noopener">${safe(file.original_name)}</a><small>${safe(file.file_kind)}</small>${can('delete') ? `<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="documents.attachment.delete" data-id="${file.id}"><i class="fa-solid fa-trash"></i></button>` : ''}</div>`).join('') || '<div class="text-muted">Chưa có file đính kèm</div>';
    $('#documentsDetailBody').innerHTML = `<div class="row g-3"><div class="col-md-7"><dl class="row mb-0"><dt class="col-sm-3">Số văn bản</dt><dd class="col-sm-9">${safe(item.document_number)}</dd><dt class="col-sm-3">Loại</dt><dd class="col-sm-9">${safe(text(item.category_name))}</dd><dt class="col-sm-3">Người ký</dt><dd class="col-sm-9">${safe(text(item.signer_name))}</dd><dt class="col-sm-3">Ban hành</dt><dd class="col-sm-9">${date(item.issued_date)}</dd><dt class="col-sm-3">Hiệu lực</dt><dd class="col-sm-9">${date(item.effective_date)}</dd><dt class="col-sm-3">Địa bàn</dt><dd class="col-sm-9">${safe(text(item.area_code))}</dd><dt class="col-sm-3">Tóm tắt</dt><dd class="col-sm-9">${safe(text(item.summary))}</dd></dl></div><div class="col-md-5"><h6>File đính kèm</h6><div class="work-task-files">${files}</div></div></div>`;
    openModal('documentsDetailModal');
  }

  async function deleteAttachment(fileId) { if (!state.current?.id || !fileId) return; if (!window.confirm('Xóa file đính kèm này?')) return; await request(API + '/' + state.current.id + '/attachments/' + fileId, { method: 'DELETE' }); await openDetail(state.current.id); }
  async function remove(id) { if (!can('delete')) return toast('Không có quyền xóa', 'warning'); if (!window.confirm('Xóa văn bản này?')) return; await request(API + '/' + id, { method: 'DELETE' }); toast('Đã xóa văn bản', 'success'); await load(); }
  async function reset() { ['documentsSearch','documentsCategoryFilter','documentsStatusFilter','documentsAreaFilter','documentsDateFrom','documentsDateTo'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; }); Object.assign(state, { page: 1, search: '', category_id: '', status: '', area_code: '', date_from: '', date_to: '', sort: 'issued_date', direction: 'DESC' }); await load(); }
  function sortBy(field) { if (!field) return; state.direction = state.sort === field && state.direction === 'ASC' ? 'DESC' : 'ASC'; state.sort = field; return load(); }
  function exportReport(format) { const query = params(); window.open(API + (format === 'pdf' ? '/export-pdf?' : '/export-excel?') + query.toString(), '_blank', 'noopener'); }

  window.loadDocuments = load;
})();
