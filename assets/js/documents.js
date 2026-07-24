(function () {
  'use strict';

  const API = '/api/documents';
  const state = { ready: false, loading: false, page: 1, pageSize: 20, search: '', category_id: '', year: '', status: '', date_from: '', date_to: '', sort: 'created_at', direction: 'DESC', catalogs: null, current: null };
  const $ = (selector, root = document) => root.querySelector(selector);
  const safe = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  const text = (value, empty = '--') => String(value ?? '').trim() || empty;
  const number = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
  const date = value => value ? new Date(String(value).slice(0, 10) + 'T00:00:00').toLocaleDateString('vi-VN') : '--';
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
    if (['create', 'update', 'delete', 'upload'].includes(action)) {
      const role = String(window.App?.user?.role || window.App?.user?.roleName || '').toUpperCase().replace(/[\s-]+/g, '_');
      if (!['SUPER_ADMIN', 'ADMIN'].includes(role)) return false;
    }
    const service = window.Thon09Platform?.permissions;
    if (service?.can) return service.can('documents', action, window.App?.user);
    return typeof window.thon09CanAccess === 'function' ? window.thon09CanAccess('documents', action) : true;
  };
  const openModal = id => window.Thon09Platform?.modals?.open?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.show();
  const closeModal = id => window.Thon09Platform?.modals?.close?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.hide();

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
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
      '<section id="documentsDashboard" class="agri-kpi-grid" aria-label="Thong ke van ban"></section>',
      '<section class="agri-filter-card" aria-label="Bo loc van ban"><div class="agri-filter-row">',
      '<div class="agri-field agri-search-field"><label for="documentsSearch">Tim kiem</label><div class="module-search-input-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="documentsSearch" class="form-control" placeholder="Tieu de, so van ban, don vi, nguoi ky..."></div></div>',
      '<div class="agri-field"><label for="documentsCategoryFilter">Loai</label><select id="documentsCategoryFilter" class="form-select"></select></div>',
      '<div class="agri-field"><label for="documentsYearFilter">Nam</label><select id="documentsYearFilter" class="form-select"></select></div>',
      '<div class="agri-field"><label for="documentsPageSize">Hien thi</label><select id="documentsPageSize" class="form-select"><option>20</option><option>50</option><option>100</option></select></div>',
      '<div class="agri-field agri-actions"><button class="btn btn-outline-secondary" type="button" data-platform-action="documents.reset"><i class="fa-solid fa-rotate-right"></i></button><button class="btn btn-outline-success" type="button" data-platform-action="documents.export" data-format="excel"><i class="fa-solid fa-file-excel"></i> Excel</button><button class="btn btn-outline-danger" type="button" data-platform-action="documents.export" data-format="pdf"><i class="fa-solid fa-file-pdf"></i> PDF</button></div>',
      '</div></section>',
      '<section class="module-list-card household-list-card"><div class="module-list-head"><div><h3>Danh sach van ban</h3><span id="documentsTotalCount">Tong so: 0 van ban</span></div><button class="btn btn-success" type="button" data-platform-action="documents.create"><i class="fa-solid fa-plus"></i> Them van ban</button></div><div id="documentsLoadState" class="text-muted small px-3 pb-2 d-none"></div><div class="table-responsive"><table class="table module-table align-middle mb-0"><thead><tr><th data-platform-action="documents.sort" data-document-sort="title">Tieu de</th><th data-platform-action="documents.sort" data-document-sort="document_number">So van ban</th><th data-platform-action="documents.sort" data-document-sort="category">Loai</th><th data-platform-action="documents.sort" data-document-sort="issued_date">Ngay ban hanh</th><th data-platform-action="documents.sort" data-document-sort="uploader">Nguoi tai len</th><th data-platform-action="documents.sort" data-document-sort="created_at">Thoi gian tao</th><th>File</th><th class="text-end">Thao tac</th></tr></thead><tbody id="documentsRows"></tbody></table></div><div id="documentsPager" class="pager module-pager"></div></section>',
      formModal(),
      detailModal()
    ].join('');
    bind();
    state.ready = true;
  }

  function formModal() {
    return '<div class="modal fade" id="documentsModal" tabindex="-1"><div class="modal-dialog modal-xl"><form id="documentsForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">Van ban</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="row g-3"><div class="col-12"><label class="form-label">Tieu de van ban</label><input name="title" class="form-control" required maxlength="255"></div><div class="col-md-4"><label class="form-label">So van ban</label><input name="document_number" class="form-control" maxlength="120"></div><div class="col-md-4"><label class="form-label">Loai van ban</label><select name="category_id" id="documentsCategoryInput" class="form-select"></select></div><div class="col-md-4"><label class="form-label">Ngay ban hanh</label><input name="issued_date" type="date" class="form-control"></div><div class="col-md-4"><label class="form-label">Don vi ban hanh</label><input name="issuing_unit" class="form-control" maxlength="255"></div><div class="col-md-4"><label class="form-label">Nguoi ky</label><input name="signer_name" class="form-control" maxlength="255"></div><div class="col-md-4"><label class="form-label">Trang thai</label><select name="status" id="documentsStatusInput" class="form-select"></select></div><div class="col-12"><label class="form-label">File dinh kem</label><input id="documentsFiles" class="form-control" type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/zip"><small class="text-muted">Ho tro PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ZIP.</small></div><div class="col-12"><label class="form-label">Mo ta</label><textarea name="summary" class="form-control" rows="3"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Dong</button><button id="documentsSaveBtn" class="btn btn-primary" type="submit">Luu van ban</button></div></form></div></div>';
  }

  function detailModal() {
    return '<div class="modal fade" id="documentsDetailModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><h5 id="documentsDetailTitle" class="modal-title">Chi tiet van ban</h5><small id="documentsDetailSub" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div id="documentsDetailBody" class="modal-body"></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Dong</button></div></div></div></div>';
  }

  function bind() {
    $('#documentsSearch')?.addEventListener('input', debounce(() => run(() => { state.page = 1; readFilters(); return load(); }), 350));
    ['documentsCategoryFilter', 'documentsYearFilter'].forEach(id => $('#' + id)?.addEventListener('change', () => run(() => { state.page = 1; readFilters(); return load(); })));
    $('#documentsPageSize')?.addEventListener('change', event => run(() => { state.pageSize = Number(event.target.value || 20); state.page = 1; return load(); }));
    $('#documentsForm')?.addEventListener('submit', event => { event.preventDefault(); run(() => save(event.currentTarget)); });
  }

  async function catalogs() {
    if (state.catalogs) return state.catalogs;
    state.catalogs = await request(API + '/catalogs', { cacheTtl: 60000 });
    fill($('#documentsCategoryFilter'), state.catalogs.categories, 'Tat ca');
    fill($('#documentsCategoryInput'), state.catalogs.categories, 'Chon loai');
    fill($('#documentsYearFilter'), state.catalogs.years, 'Tat ca');
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
    state.year = $('#documentsYearFilter')?.value || '';
    state.pageSize = Number($('#documentsPageSize')?.value || state.pageSize || 20);
  }

  function params() {
    readFilters();
    return new URLSearchParams({ page: state.page, pageSize: state.pageSize, search: state.search, category_id: state.category_id, year: state.year, sort: state.sort, direction: state.direction });
  }

  function setLoading(on, message = '') {
    state.loading = on;
    const el = $('#documentsLoadState');
    if (!el) return;
    el.classList.toggle('d-none', !on && !message);
    el.textContent = on ? (message || 'Dang tai du lieu...') : message;
  }

  async function load() {
    if (!$('#documentsScreen')) return;
    registerActions();
    shell();
    setLoading(true);
    try {
      await catalogs();
      const query = params();
      const [list, dashboard] = await Promise.all([
        request(API + '?' + query),
        request(API + '/dashboard?' + query, { cacheTtl: 15000 })
      ]);
      renderDashboard(dashboard);
      renderRows(list);
      renderPager(list);
      setLoading(false, '');
      window.thon09ApplyAccessControls?.();
      window.thon09SyncResponsiveTableLabels?.($('#documentsScreen'));
    } catch (error) {
      renderRows({ items: [], total: 0 });
      setLoading(false, error.message || 'Khong tai duoc van ban');
      toast(error.message || 'Khong tai duoc van ban', 'danger');
    } finally {
      state.loading = false;
    }
  }

  function renderDashboard(data = {}) {
    const m = data.metrics || {};
    const cards = [['Tong van ban', 'fa-file-lines', m.total || 0], ['Dang hieu luc', 'fa-circle-check', m.active_count || 0], ['Luu tru', 'fa-box-archive', m.archived_count || 0], ['30 ngay gan day', 'fa-clock', m.recent_count || 0]];
    $('#documentsDashboard').innerHTML = cards.map(card => `<article class="agri-kpi-card"><span><i class="fa-solid ${card[1]}"></i></span><div><strong>${number(card[2])}</strong><small>${safe(card[0])}</small></div></article>`).join('');
  }

  function renderRows(data = {}) {
    const rows = data.items || [], tbody = $('#documentsRows');
    $('#documentsTotalCount').textContent = `Tong so: ${number(data.total || 0)} van ban`;
    if (!rows.length) { tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Chua co van ban phu hop bo loc.</td></tr>'; return; }
    tbody.innerHTML = rows.map(item => {
      const actions = [
        `<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="documents.detail" data-id="${item.id}" title="Xem"><i class="fa-solid fa-eye"></i></button>`,
        item.attachment_count ? `<a class="btn btn-sm btn-outline-success" href="${API}/${item.id}/download" target="_blank" rel="noopener" title="Tai xuong"><i class="fa-solid fa-download"></i></a>` : '',
        can('update') ? `<button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="documents.edit" data-id="${item.id}" title="Sua"><i class="fa-solid fa-pen"></i></button>` : '',
        can('delete') ? `<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="documents.delete" data-id="${item.id}" title="Xoa"><i class="fa-solid fa-trash"></i></button>` : ''
      ].filter(Boolean).join(' ');
      return `<tr><td><div class="fw-semibold">${safe(item.title)}</div><small class="text-muted">${safe(text(item.summary, '').slice(0, 90))}</small></td><td>${safe(text(item.document_number))}</td><td>${safe(text(item.category_name))}</td><td>${date(item.issued_date)}</td><td>${safe(text(item.created_by_name))}</td><td>${safe(text(item.created_at))}</td><td>${number(item.attachment_count || 0)}</td><td class="text-end">${actions}</td></tr>`;
    }).join('');
  }

  function renderPager(data = {}) {
    const host = $('#documentsPager'), totalPages = Number(data.totalPages || 1), page = Number(data.page || state.page || 1);
    state.page = page;
    host.innerHTML = totalPages <= 1 ? '' : `<div class="d-flex gap-2 justify-content-end flex-wrap"><button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="documents.page" data-page="${Math.max(1, page - 1)}" ${page <= 1 ? 'disabled' : ''}>Truoc</button><span class="px-2">${page} / ${totalPages}</span><button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="documents.page" data-page="${Math.min(totalPages, page + 1)}" ${page >= totalPages ? 'disabled' : ''}>Sau</button></div>`;
  }

  async function openForm(id = null) {
    if (id && !can('update')) return toast('Khong co quyen sua', 'warning');
    if (!id && !can('create')) return toast('Khong co quyen them', 'warning');
    await catalogs();
    const form = $('#documentsForm');
    form.reset();
    form.elements.id.value = '';
    form.elements.status.value = 'ACTIVE';
    $('#documentsFiles').value = '';
    if (id) {
      const item = await request(API + '/' + id);
      Object.entries(item).forEach(([key, value]) => { if (form.elements[key]) form.elements[key].value = value ?? ''; });
      form.elements.id.value = item.id;
    }
    openModal('documentsModal');
  }

  async function save(form) {
    const body = Object.fromEntries(new FormData(form).entries());
    const id = Number(body.id || 0);
    delete body.id;
    const button = $('#documentsSaveBtn');
    if (button) button.disabled = true;
    try {
      const saved = await request(id ? API + '/' + id : API, { method: id ? 'PUT' : 'POST', body });
      const files = Array.from($('#documentsFiles')?.files || []);
      for (const [index, file] of files.entries()) {
        const upload = new FormData();
        upload.append('file', file);
        const replace = id && index === 0 ? '?replace=1' : '';
        await request(API + '/' + saved.id + '/attachments' + replace, { method: 'POST', body: upload });
      }
      closeModal('documentsModal');
      toast('Da luu van ban', 'success');
      await load();
    } finally {
      if (button) button.disabled = false;
    }
  }

  async function openDetail(id) {
    const item = await request(API + '/' + id);
    state.current = item;
    $('#documentsDetailTitle').textContent = item.title || 'Chi tiet van ban';
    $('#documentsDetailSub').textContent = [item.document_code, item.document_number, item.status_label].filter(Boolean).join(' - ');
    const pdf = (item.attachments || []).find(file => file.mime_type === 'application/pdf');
    const preview = pdf ? `<iframe src="${safe(pdf.preview_url)}" title="PDF preview" style="width:100%;height:520px;border:1px solid #dee2e6;border-radius:8px"></iframe>` : '<div class="text-muted border rounded p-3">File nay khong ho tro preview tren trinh duyet. Vui long tai xuong de xem.</div>';
    const files = (item.attachments || []).map(file => `<div class="work-task-file"><i class="fa-solid ${file.file_kind === 'PDF' ? 'fa-file-pdf' : 'fa-file-lines'}"></i><a href="${safe(file.download_url)}" target="_blank" rel="noopener">${safe(file.original_name)}</a><small>${safe(file.file_kind)} - ${number(file.file_size)} bytes</small>${can('delete') ? `<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="documents.attachment.delete" data-id="${file.id}"><i class="fa-solid fa-trash"></i></button>` : ''}</div>`).join('') || '<div class="text-muted">Chua co file dinh kem</div>';
    $('#documentsDetailBody').innerHTML = `<div class="row g-3"><div class="col-md-5"><dl class="row mb-0"><dt class="col-sm-4">So van ban</dt><dd class="col-sm-8">${safe(text(item.document_number))}</dd><dt class="col-sm-4">Loai</dt><dd class="col-sm-8">${safe(text(item.category_name))}</dd><dt class="col-sm-4">Don vi</dt><dd class="col-sm-8">${safe(text(item.issuing_unit))}</dd><dt class="col-sm-4">Nguoi ky</dt><dd class="col-sm-8">${safe(text(item.signer_name))}</dd><dt class="col-sm-4">Ban hanh</dt><dd class="col-sm-8">${date(item.issued_date)}</dd><dt class="col-sm-4">Nguoi tai</dt><dd class="col-sm-8">${safe(text(item.created_by_name))}</dd><dt class="col-sm-4">Mo ta</dt><dd class="col-sm-8">${safe(text(item.summary))}</dd></dl><h6 class="mt-3">File dinh kem</h6><div class="work-task-files">${files}</div></div><div class="col-md-7">${preview}</div></div>`;
    openModal('documentsDetailModal');
  }

  async function deleteAttachment(fileId) {
    if (!state.current?.id || !fileId) return;
    if (!window.confirm('Xoa file dinh kem nay?')) return;
    await request(API + '/' + state.current.id + '/attachments/' + fileId, { method: 'DELETE' });
    await openDetail(state.current.id);
  }

  async function remove(id) {
    if (!can('delete')) return toast('Khong co quyen xoa', 'warning');
    if (!window.confirm('Xoa van ban nay? File vat ly se bi xoa khoi uploads.')) return;
    await request(API + '/' + id, { method: 'DELETE' });
    toast('Da xoa van ban', 'success');
    await load();
  }

  async function reset() {
    ['documentsSearch', 'documentsCategoryFilter', 'documentsYearFilter'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
    Object.assign(state, { page: 1, search: '', category_id: '', year: '', status: '', date_from: '', date_to: '', sort: 'created_at', direction: 'DESC' });
    await load();
  }

  function sortBy(field) {
    if (!field) return;
    state.direction = state.sort === field && state.direction === 'ASC' ? 'DESC' : 'ASC';
    state.sort = field;
    return load();
  }

  function exportReport(format) {
    const query = params();
    window.open(API + (format === 'pdf' ? '/export-pdf?' : '/export-excel?') + query.toString(), '_blank', 'noopener');
  }

  window.loadDocuments = load;
})();
