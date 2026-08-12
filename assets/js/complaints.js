(function () {
  'use strict';

  const API = '/api/complaints';
  const state = { page: 1, pageSize: 20, search: '', category_id: '', priority_id: '', status_id: '', assigned_user_id: '', receiver_user_id: '', household_id: '', area_code: '', date_from: '', date_to: '', overdue: '', sort: 'received_at', direction: 'DESC', catalogs: null, current: null, relatedLinks: [] };
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const safe = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  const text = (value, empty = '--') => String(value ?? '').trim() || empty;
  const number = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
  const date = value => value ? new Date(value.replace(' ', 'T')).toLocaleDateString('vi-VN') : '--';
  const dateTime = value => value ? new Date(value.replace(' ', 'T')).toLocaleString('vi-VN') : '--';
  const toast = (message, type = 'info') => typeof window.showToast === 'function' ? window.showToast(message, type) : console[type === 'danger' ? 'error' : 'log'](message);
  const request = (url, options = {}) => typeof window.api === 'function' ? window.api(url, options) : fetch(url, options).then(r => r.json()).then(p => {
    if (p?.ok === false || p?.success === false) throw new Error(p?.error?.message || p?.message || 'Request failed');
    return p?.data ?? p;
  });
  const authHeaders = () => {
    const headers = {};
    if (window.App?.token) headers.Authorization = `Bearer ${window.App.token}`;
    if (window.App?.csrfToken) headers['X-CSRF-Token'] = window.App.csrfToken;
    return headers;
  };
  const can = action => {
    const service = window.TenantAppPlatform?.permissions;
    if (service?.can) return service.can('complaints', action, window.App?.user);
    return typeof window.TenantAppCanAccess === 'function' ? window.TenantAppCanAccess('complaints', action) : true;
  };
  const openModal = id => window.TenantAppPlatform?.modals?.open?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.show();
  const closeModal = id => window.TenantAppPlatform?.modals?.close?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.hide();
  const confirmAction = options => window.TenantAppPlatform?.confirmDialog?.ask?.(options) || Promise.resolve(window.confirm(options.message || 'XÃ¡c nháº­n thao tÃ¡c?'));
  function run(fn) { Promise.resolve().then(fn).catch(error => toast(error.message || 'Thao tÃ¡c khÃ´ng thÃ nh cÃ´ng', 'danger')); }
  function debounce(fn, delay) { let timer; return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); }; }

  function init() {
    registerActions();
    bind();
    wrapGisLoader();
    if (window.App?.screen === 'complaints') run(load);
    if (window.App?.screen === 'gis') scheduleGisLayer();
  }

  function registerActions() {
    if (window.__TenantAppComplaintsActionsRegistered || !window.TenantAppPlatform?.actions) return;
    window.__TenantAppComplaintsActionsRegistered = true;
    window.TenantAppPlatform.actions
      .register({ key: 'complaints.create', handler: () => run(() => openForm()) })
      .register({ key: 'complaints.detail', handler: ({ dataset }) => run(() => openDetail(Number(dataset.id || 0))) })
      .register({ key: 'complaints.edit', handler: ({ dataset }) => run(() => openForm(Number(dataset.id || 0))) })
      .register({ key: 'complaints.delete', handler: ({ dataset }) => run(() => remove(Number(dataset.id || 0))) })
      .register({ key: 'complaints.reset', handler: () => run(reset) })
      .register({ key: 'complaints.search', handler: () => run(() => { state.page = 1; readFilters(); return load(); }) })
      .register({ key: 'complaints.sort', handler: ({ dataset }) => run(() => sortBy(dataset.complaintSort)) })
      .register({ key: 'complaints.page', handler: ({ dataset, target }) => !target.disabled && run(() => { state.page = Number(dataset.page || 1); return load(); }) })
      .register({ key: 'complaints.dashboard.filter', handler: ({ dataset }) => run(() => dashboardFilter(dataset.complaintFilter || '')) })
      .register({ key: 'complaints.gps.use', handler: useGps })
      .register({ key: 'complaints.map.pick', handler: pickMap })
      .register({ key: 'complaints.history.add', handler: () => run(addHistory) })
      .register({ key: 'complaints.assign', handler: () => run(assign) })
      .register({ key: 'complaints.evaluate', handler: () => run(evaluate) })
      .register({ key: 'complaints.household.select', handler: ({ dataset }) => selectHousehold(dataset) })
      .register({ key: 'complaints.citizen.select', handler: ({ dataset }) => selectCitizen(dataset) })
      .register({ key: 'complaints.related.show', handler: showRelatedPicker })
      .register({ key: 'complaints.related.select', handler: ({ dataset }) => selectRelated(dataset) })
      .register({ key: 'complaints.related.remove', handler: ({ dataset }) => removeRelated(Number(dataset.index || -1)) })
      .register({ key: 'complaints.attachment.delete', handler: ({ dataset }) => run(() => deleteAttachment(Number(dataset.id || 0))) })
      .register({ key: 'complaints.export', handler: ({ dataset }) => exportReport(dataset.format || 'excel') })
      .register({ key: 'complaints.print', handler: () => run(printReport) });
  }

  function bind() {
    $('#complaintsSearch')?.addEventListener('input', debounce(() => run(() => { state.page = 1; readFilters(); return load(); }), 350));
    ['complaintsCategoryFilter', 'complaintsPriorityFilter', 'complaintsStatusFilter', 'complaintsOverdueFilter', 'complaintsAreaFilter', 'complaintsDateFrom', 'complaintsDateTo'].forEach(id => $('#' + id)?.addEventListener('change', () => run(() => { state.page = 1; readFilters(); return load(); })));
    $('#complaintsPageSize')?.addEventListener('change', event => run(() => { state.pageSize = Number(event.target.value || 20); state.page = 1; return load(); }));
    $('#complaintForm')?.addEventListener('submit', event => { event.preventDefault(); run(() => save(event.currentTarget)); });
    $('#complaintAttachmentFiles')?.addEventListener('change', renderPendingFiles);
    $('#complaintHouseholdSearch')?.addEventListener('input', debounce(event => run(() => searchHouseholds(event.target.value)), 250));
    $('#complaintCitizenSearch')?.addEventListener('input', debounce(event => run(() => searchCitizens(event.target.value)), 250));
    $('#complaintRelatedSearch')?.addEventListener('input', debounce(event => run(() => searchRelated(event.target.value)), 250));
    $('#complaintRelatedType')?.addEventListener('change', clearRelatedSearch);
    document.addEventListener('tenant:screen-change', event => {
      if (event.detail?.screen === 'complaints') run(load);
      if (event.detail?.screen === 'gis') scheduleGisLayer();
    });
  }

  function readFilters() {
    state.search = $('#complaintsSearch')?.value.trim() || '';
    state.category_id = $('#complaintsCategoryFilter')?.value || '';
    state.priority_id = $('#complaintsPriorityFilter')?.value || '';
    state.status_id = $('#complaintsStatusFilter')?.value || '';
    state.overdue = $('#complaintsOverdueFilter')?.value || '';
    state.area_code = $('#complaintsAreaFilter')?.value.trim() || '';
    state.date_from = $('#complaintsDateFrom')?.value || '';
    state.date_to = $('#complaintsDateTo')?.value || '';
    state.pageSize = Number($('#complaintsPageSize')?.value || state.pageSize || 20);
  }

  async function catalogs() {
    if (state.catalogs) return state.catalogs;
    state.catalogs = await request(API + '/catalogs', { cacheTtl: 60000 });
    fill($('#complaintsCategoryFilter'), state.catalogs.categories, 'Táº¥t cáº£');
    fill($('#complaintsPriorityFilter'), state.catalogs.priorities, 'Táº¥t cáº£');
    fill($('#complaintsStatusFilter'), state.catalogs.statuses, 'Táº¥t cáº£');
    fill($('#complaintCategorySelect'), state.catalogs.categories, 'Chá»n loáº¡i');
    fill($('#complaintPrioritySelect'), state.catalogs.priorities, 'Chá»n Æ°u tiÃªn');
    fill($('#complaintStatusSelect'), state.catalogs.statuses, 'Chá»n tráº¡ng thÃ¡i');
    fill($('#complaintHistoryStatusSelect'), state.catalogs.statuses, 'Giá»¯ nguyÃªn');
    fill($('#complaintRatingSelect'), state.catalogs.ratings, 'Chá»n Ä‘Ã¡nh giÃ¡');
    fill($('#complaintRelatedType'), state.catalogs.linkTypes, 'Chá»n loáº¡i Ä‘á»‘i tÆ°á»£ng');
    return state.catalogs;
  }

  function fill(select, items = [], first = '') {
    if (!select) return;
    const current = select.value;
    select.innerHTML = first ? `<option value="">${safe(first)}</option>` : '';
    items.forEach(item => {
      const option = document.createElement('option');
      option.value = item.value;
      option.textContent = item.label;
      option.dataset.code = item.code || '';
      select.appendChild(option);
    });
    if ([...select.options].some(option => option.value === current)) select.value = current;
  }

  function params() {
    readFilters();
    return new URLSearchParams({ page: state.page, pageSize: state.pageSize, search: state.search, category_id: state.category_id, priority_id: state.priority_id, status_id: state.status_id, assigned_user_id: state.assigned_user_id, receiver_user_id: state.receiver_user_id, household_id: state.household_id, area_code: state.area_code, date_from: state.date_from, date_to: state.date_to, overdue: state.overdue, sort: state.sort, direction: state.direction });
  }

  async function load() {
    if (!$('#complaintsScreen')) return;
    await catalogs();
    const query = params();
    const [list, dashboard] = await Promise.all([request(API + '?' + query), request(API + '/dashboard?' + query, { cacheTtl: 15000 })]);
    renderDashboard(dashboard);
    renderRows(list);
    renderPager(list);
    window.TenantAppApplyAccessControls?.();
  }

  function renderDashboard(data = {}) {
    const metrics = data.metrics || {};
    const cards = [
      ['Tá»•ng pháº£n Ã¡nh', 'fa-inbox', metrics.total || 0, ''],
      ['ChÆ°a xá»­ lÃ½', 'fa-circle-exclamation', metrics.new_count || 0, 'status:NEW'],
      ['Äang xá»­ lÃ½', 'fa-spinner', metrics.processing_count || 0, ''],
      ['ÄÃ£ hoÃ n thÃ nh', 'fa-circle-check', metrics.done_count || 0, 'status:DONE'],
      ['QuÃ¡ háº¡n', 'fa-clock', metrics.overdue_count || 0, 'overdue:1'],
      ['Chuyá»ƒn cáº¥p trÃªn', 'fa-share-from-square', metrics.escalated_count || 0, 'status:ESCALATED']
    ];
    const chart = (data.charts?.by_category || []).slice(0, 5).map(row => `<span class="badge bg-light text-dark border me-1 mb-1">${safe(text(row.label, 'KhÃ¡c'))}: ${number(row.value)}</span>`).join('');
    $('#complaintsMiniDashboard').innerHTML = cards.map(card => `<article class="agri-kpi-card" ${card[3] ? `data-platform-action="complaints.dashboard.filter" data-complaint-filter="${safe(card[3])}"` : ''}><span><i class="fa-solid ${card[1]}"></i></span><div><strong>${number(card[2])}</strong><small>${safe(card[0])}</small></div></article>`).join('') + `<article class="agri-kpi-card" style="grid-column:span 2"><span><i class="fa-solid fa-chart-pie"></i></span><div><strong>Theo loáº¡i pháº£n Ã¡nh</strong><small>${chart || 'ChÆ°a cÃ³ dá»¯ liá»‡u'}</small></div></article>`;
  }

  function renderRows(data = {}) {
    const rows = data.items || [];
    const tbody = $('#complaintsRows');
    $('#complaintsTotalCount').textContent = `Tá»•ng sá»‘: ${number(data.total || 0)} pháº£n Ã¡nh`;
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">ChÆ°a cÃ³ pháº£n Ã¡nh phÃ¹ há»£p bá»™ lá»c.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(item => {
      const actions = [`<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="complaints.detail" data-id="${item.id}" title="Xem"><i class="fa-solid fa-eye"></i></button>`, can('update') ? `<button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="complaints.edit" data-id="${item.id}" title="Sá»­a"><i class="fa-solid fa-pen"></i></button>` : '', can('delete') ? `<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="complaints.delete" data-id="${item.id}" title="XÃ³a"><i class="fa-solid fa-trash"></i></button>` : ''].filter(Boolean).join(' ');
      return `<tr><td><strong>${safe(item.complaint_code)}</strong></td><td><div class="fw-semibold">${safe(text(item.title))}</div><small class="text-muted">${safe(text(item.detail, '').slice(0, 90))}</small></td><td>${safe(text(item.reporter_name))}<br><small class="text-muted">${safe(text(item.reporter_phone, ''))}</small></td><td>${safe(text(item.household_code))}<br><small class="text-muted">${safe(text(item.head_citizen_name, ''))}</small></td><td>${safe(text(item.category_name))}</td><td>${priorityBadge(item)}</td><td>${statusBadge(item)}</td><td>${safe(text(item.assigned_name))}</td><td>${date(item.received_at)}</td><td>${date(item.due_at)}${item.is_overdue ? '<br><span class="badge bg-danger">QuÃ¡ háº¡n</span>' : ''}</td><td class="text-end">${actions}</td></tr>`;
    }).join('');
  }

  function renderPager(data = {}) {
    const host = $('#complaintsPager');
    const totalPages = Number(data.totalPages || 1);
    const page = Number(data.page || state.page || 1);
    state.page = page;
    if (totalPages <= 1) { host.innerHTML = ''; return; }
    const buttons = [`<button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="complaints.page" data-page="${Math.max(1, page - 1)}" ${page <= 1 ? 'disabled' : ''}>TrÆ°á»›c</button>`];
    for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) buttons.push(`<button class="btn btn-sm ${i === page ? 'btn-primary' : 'btn-outline-secondary'}" type="button" data-platform-action="complaints.page" data-page="${i}">${i}</button>`);
    buttons.push(`<button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="complaints.page" data-page="${Math.min(totalPages, page + 1)}" ${page >= totalPages ? 'disabled' : ''}>Sau</button>`);
    host.innerHTML = `<div class="d-flex gap-2 justify-content-end flex-wrap">${buttons.join('')}</div>`;
  }

  function priorityBadge(item) {
    const tones = { URGENT: 'danger', HIGH: 'warning', NORMAL: 'primary', LOW: 'secondary' };
    return `<span class="badge bg-${tones[item.priority_code] || 'secondary'}">${safe(text(item.priority_name))}</span>`;
  }

  function statusBadge(item) {
    const tones = { NEW: 'danger', VERIFYING: 'warning', PROCESSING: 'warning', DONE: 'success', ESCALATED: 'info', REJECTED: 'secondary' };
    return `<span class="badge bg-${tones[item.status_code] || 'secondary'}">${safe(text(item.status_name))}</span>`;
  }

  async function reset() {
    ['complaintsSearch', 'complaintsCategoryFilter', 'complaintsPriorityFilter', 'complaintsStatusFilter', 'complaintsOverdueFilter', 'complaintsAreaFilter', 'complaintsDateFrom', 'complaintsDateTo'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
    Object.assign(state, { page: 1, search: '', category_id: '', priority_id: '', status_id: '', area_code: '', date_from: '', date_to: '', overdue: '', sort: 'received_at', direction: 'DESC' });
    await load();
  }

  function sortBy(key) {
    if (!key) return;
    if (state.sort === key) state.direction = state.direction === 'ASC' ? 'DESC' : 'ASC';
    else { state.sort = key; state.direction = 'ASC'; }
    state.page = 1;
    return load();
  }

  function dashboardFilter(value) {
    if (value.startsWith('overdue:')) $('#complaintsOverdueFilter').value = value.split(':')[1];
    if (value.startsWith('status:')) {
      const code = value.split(':')[1];
      const option = [...($('#complaintsStatusFilter')?.options || [])].find(item => item.dataset.code === code);
      if (option) $('#complaintsStatusFilter').value = option.value;
    }
    state.page = 1;
    return load();
  }

  async function openForm(id = null) {
    if (id && !can('update')) return toast('KhÃ´ng cÃ³ quyá»n sá»­a', 'warning');
    if (!id && !can('create')) return toast('KhÃ´ng cÃ³ quyá»n thÃªm', 'warning');
    await catalogs();
    const form = $('#complaintForm');
    form.reset();
    $('#complaintHouseholdSelected').textContent = '';
    $('#complaintCitizenSelected').textContent = '';
    $('#complaintAttachmentPreview').innerHTML = '<span class="text-muted small">ChÆ°a chá»n file.</span>';
    state.relatedLinks = [];
    clearRelatedSearch();
    $('#complaintRelatedPicker')?.classList.add('d-none');
    renderRelatedChips();
    state.current = null;
    if (id) {
      state.current = await request(`${API}/${id}`);
      Object.keys(state.current).forEach(key => { if (form.elements[key]) form.elements[key].value = state.current[key] ?? ''; });
      ['received_at', 'due_at'].forEach(key => {
        if (form.elements[key] && state.current[key]) form.elements[key].value = String(state.current[key]).replace(' ', 'T').slice(0, 16);
      });
      $('#complaintHouseholdSearch').value = [state.current.household_code, state.current.head_citizen_name].filter(Boolean).join(' - ');
      $('#complaintCitizenSearch').value = state.current.citizen_name || '';
      state.relatedLinks = normalizeRelatedLinks(state.current.links || []);
      renderRelatedChips();
      renderWorkflow(state.current);
    } else {
      const now = new Date();
      form.elements.received_at.value = now.toISOString().slice(0, 16);
      const newStatus = [...$('#complaintStatusSelect').options].find(option => option.dataset.code === 'NEW');
      if (newStatus) form.elements.status_id.value = newStatus.value;
      const normalPriority = [...$('#complaintPrioritySelect').options].find(option => option.dataset.code === 'NORMAL');
      if (normalPriority) form.elements.priority_id.value = normalPriority.value;
      renderWorkflow(null);
    }
    openModal('complaintFormModal');
  }

  async function save(form) {
    const data = Object.fromEntries(new FormData(form).entries());
    const id = data.id;
    delete data.id;
    delete data.links;
    data.related_links = state.relatedLinks.map(link => ({ target_type: link.target_type, target_id: link.target_id, label: link.label }));
    const item = await request(id ? `${API}/${id}` : API, { method: id ? 'PUT' : 'POST', body: data });
    await uploadPendingFiles(item.id);
    closeModal('complaintFormModal');
    toast('ÄÃ£ lÆ°u pháº£n Ã¡nh', 'success');
    await load();
    run(refreshGisLayer);
  }

  async function openDetail(id) {
    const item = await request(`${API}/${id}`);
    state.current = item;
    $('#complaintDetailTitle').textContent = item.title || 'Chi tiáº¿t pháº£n Ã¡nh';
    $('#complaintDetailSubtitle').textContent = item.complaint_code || '';
    $('#complaintDetailBody').innerHTML = detailHtml(item);
    $('#complaintDetailModal [data-platform-action="complaints.edit"]')?.setAttribute('data-id', item.id);
    openModal('complaintDetailModal');
  }

  function detailHtml(item) {
    const map = item.latitude && item.longitude ? `<iframe title="Vá»‹ trÃ­ pháº£n Ã¡nh" src="https://www.openstreetmap.org/export/embed.html?bbox=${Number(item.longitude) - 0.002}%2C${Number(item.latitude) - 0.002}%2C${Number(item.longitude) + 0.002}%2C${Number(item.latitude) + 0.002}&marker=${item.latitude}%2C${item.longitude}" style="width:100%;height:240px;border:1px solid #d1d5db;border-radius:8px"></iframe>` : '<div class="text-muted border rounded p-4 text-center">ChÆ°a cÃ³ vá»‹ trÃ­ GIS</div>';
    return `<div class="row g-3"><div class="col-lg-8"><h5>${safe(item.title)}</h5><p>${safe(item.detail)}</p><div class="row g-2">${field('MÃ£ pháº£n Ã¡nh', item.complaint_code)}${field('NgÆ°á»i pháº£n Ã¡nh', item.reporter_name)}${field('Äiá»‡n thoáº¡i', item.reporter_phone)}${field('Há»™ liÃªn quan', item.household_code)}${field('Loáº¡i', item.category_name)}${field('Æ¯u tiÃªn', item.priority_name)}${field('Tráº¡ng thÃ¡i', item.status_name)}${field('NgÆ°á»i phá»¥ trÃ¡ch', item.assigned_name)}${field('NgÃ y tiáº¿p nháº­n', dateTime(item.received_at))}${field('Háº¡n xá»­ lÃ½', dateTime(item.due_at))}</div></div><div class="col-lg-4">${statusBadge(item)} ${item.is_overdue ? '<span class="badge bg-danger">QuÃ¡ háº¡n</span>' : ''}<div class="mt-3">${map}</div></div><div class="col-12">${relatedHtml(item)}</div><div class="col-12">${attachmentsHtml(item)}</div><div class="col-md-6">${historyHtml(item)}</div><div class="col-md-6">${assignmentsHtml(item)}</div>${item.result_rating ? `<div class="col-12"><strong>ÄÃ¡nh giÃ¡</strong><p>${safe(text(ratingLabel(item.result_rating)))} - ${safe(text(item.result_note, ''))}</p></div>` : ''}</div>`;
  }

  function field(label, value) { return `<div class="col-md-6"><strong>${safe(label)}</strong><div>${safe(text(value))}</div></div>`; }
  function ratingLabel(value) { return (state.catalogs?.ratings || []).find(item => item.value === value)?.label || value; }
  function relatedHtml(item) { const links = normalizeRelatedLinks(item.links || []); const rows = links.map(link => `<span class="complaint-related-chip"><span>${safe(link.type_label || link.target_type)} - ${safe(link.label)}</span></span>`).join(''); return `<h6>Äá»‘i tÆ°á»£ng liÃªn quan</h6><div class="complaint-related-chips">${rows || '<span class="text-muted">ChÆ°a cÃ³ Ä‘á»‘i tÆ°á»£ng liÃªn quan.</span>'}</div>`; }
  function historyHtml(item) { const rows = (item.histories || []).map(row => `<li><strong>${dateTime(row.created_at)}</strong><br>${safe(row.content)}<br><small class="text-muted">${safe(text(row.actor_name, ''))} ${row.status_name ? '- ' + safe(row.status_name) : ''}</small></li>`).join(''); return `<h6>Nháº­t kÃ½ xá»­ lÃ½</h6><ul class="complaint-timeline">${rows || '<li>ChÆ°a cÃ³ nháº­t kÃ½.</li>'}</ul>`; }
  function assignmentsHtml(item) { const rows = (item.assignments || []).map(row => `<li><strong>${safe(row.assignee_name)}</strong><br><small>${dateTime(row.assigned_at)} - Háº¡n: ${dateTime(row.due_at)}</small><br>${safe(text(row.note, ''))}</li>`).join(''); return `<h6>Lá»‹ch sá»­ giao viá»‡c</h6><ul class="complaint-timeline">${rows || '<li>ChÆ°a giao viá»‡c.</li>'}</ul>`; }
  function attachmentsHtml(item) { const rows = (item.attachments || []).map(file => `<a class="complaint-attachment" href="${safe(file.preview_url)}" target="_blank" rel="noopener"><i class="fa-solid ${fileIcon(file)}"></i><span>${safe(file.original_name)}</span></a>`).join(''); return `<h6>ÄÃ­nh kÃ¨m</h6><div class="complaint-attachments">${rows || '<span class="text-muted">ChÆ°a cÃ³ file Ä‘Ã­nh kÃ¨m.</span>'}</div>`; }
  function fileIcon(file) { return file.file_kind === 'IMAGE' ? 'fa-image' : (file.file_kind === 'VIDEO' ? 'fa-video' : (file.file_kind === 'PDF' ? 'fa-file-pdf' : 'fa-file')); }

  function renderWorkflow(item) {
    $('#complaintWorkflowPanel').innerHTML = item?.id ? `<div class="row g-3"><div class="col-lg-4"><label class="form-label">Ná»™i dung xá»­ lÃ½</label><textarea id="complaintHistoryContent" class="form-control" rows="4"></textarea><label class="form-label mt-2">Tráº¡ng thÃ¡i sau cáº­p nháº­t</label><select id="complaintHistoryStatusSelect" class="form-select"></select><button class="btn btn-outline-primary btn-sm mt-2" type="button" data-platform-action="complaints.history.add"><i class="fa-solid fa-clock-rotate-left"></i> Ghi nháº­t kÃ½</button></div><div class="col-lg-4"><label class="form-label">NgÆ°á»i xá»­ lÃ½</label><input id="complaintAssignName" class="form-control" value="${safe(item.assigned_name || '')}"><label class="form-label mt-2">Háº¡n hoÃ n thÃ nh</label><input id="complaintAssignDue" class="form-control" type="datetime-local"><label class="form-label mt-2">Ghi chÃº</label><textarea id="complaintAssignNote" class="form-control" rows="2"></textarea><button class="btn btn-outline-success btn-sm mt-2" type="button" data-platform-action="complaints.assign"><i class="fa-solid fa-user-check"></i> Giao viá»‡c</button></div><div class="col-lg-4"><label class="form-label">ÄÃ¡nh giÃ¡ káº¿t quáº£</label><select id="complaintRatingSelect" class="form-select"></select><label class="form-label mt-2">Ghi chÃº</label><textarea id="complaintResultNote" class="form-control" rows="3">${safe(item.result_note || '')}</textarea><button class="btn btn-outline-secondary btn-sm mt-2" type="button" data-platform-action="complaints.evaluate"><i class="fa-solid fa-star"></i> LÆ°u Ä‘Ã¡nh giÃ¡</button></div><div class="col-md-6">${historyHtml(item)}</div><div class="col-md-6">${assignmentsHtml(item)}</div><div class="col-12">${attachmentsHtml(item)}</div></div>` : '<div class="alert alert-light border mb-0">LÆ°u pháº£n Ã¡nh trÆ°á»›c khi ghi nháº­t kÃ½ xá»­ lÃ½, giao viá»‡c, Ä‘Ã¡nh giÃ¡ hoáº·c quáº£n lÃ½ file Ä‘Ã­nh kÃ¨m.</div>';
    if (item?.id && state.catalogs) {
      fill($('#complaintHistoryStatusSelect'), state.catalogs.statuses, 'Giá»¯ nguyÃªn');
      fill($('#complaintRatingSelect'), state.catalogs.ratings, 'Chá»n Ä‘Ã¡nh giÃ¡');
      if (item.result_rating) $('#complaintRatingSelect').value = item.result_rating;
    }
  }

  async function addHistory() {
    if (!state.current?.id) return;
    await request(`${API}/${state.current.id}/histories`, { method: 'POST', body: { content: $('#complaintHistoryContent').value, status_id: $('#complaintHistoryStatusSelect').value } });
    toast('ÄÃ£ ghi nháº­t kÃ½ xá»­ lÃ½', 'success');
    state.current = await request(`${API}/${state.current.id}`);
    renderWorkflow(state.current);
    await load();
  }

  async function assign() {
    if (!state.current?.id) return;
    await request(`${API}/${state.current.id}/assignments`, { method: 'POST', body: { assignee_name: $('#complaintAssignName').value, due_at: $('#complaintAssignDue').value, note: $('#complaintAssignNote').value } });
    toast('ÄÃ£ giao viá»‡c', 'success');
    state.current = await request(`${API}/${state.current.id}`);
    renderWorkflow(state.current);
    await load();
  }

  async function evaluate() {
    if (!state.current?.id) return;
    await request(`${API}/${state.current.id}/evaluation`, { method: 'POST', body: { result_rating: $('#complaintRatingSelect').value, result_note: $('#complaintResultNote').value } });
    toast('ÄÃ£ lÆ°u Ä‘Ã¡nh giÃ¡', 'success');
    state.current = await request(`${API}/${state.current.id}`);
    renderWorkflow(state.current);
  }

  async function remove(id) {
    if (!can('delete')) return toast('KhÃ´ng cÃ³ quyá»n xÃ³a', 'warning');
    if (!await confirmAction({ title: 'XÃ¡c nháº­n xÃ³a pháº£n Ã¡nh', message: 'XÃ³a pháº£n Ã¡nh nÃ y?', confirmLabel: 'XÃ³a', tone: 'danger' })) return;
    await request(`${API}/${id}`, { method: 'DELETE' });
    toast('ÄÃ£ xÃ³a pháº£n Ã¡nh', 'success');
    await load();
    run(refreshGisLayer);
  }

  function renderPendingFiles() {
    const files = Array.from($('#complaintAttachmentFiles')?.files || []);
    $('#complaintAttachmentPreview').innerHTML = files.length ? files.map(file => `<span class="badge bg-light text-dark border me-1 mb-1">${safe(file.name)}</span>`).join('') : '<span class="text-muted small">ChÆ°a chá»n file.</span>';
  }

  async function uploadPendingFiles(id) {
    const input = $('#complaintAttachmentFiles');
    const files = Array.from(input?.files || []);
    for (const file of files) {
      const body = new FormData();
      body.append('file', file);
      const response = await fetch(`${API}/${id}/attachments`, { method: 'POST', headers: authHeaders(), body, cache: 'no-store' });
      const payload = await response.json().catch(() => null);
      if (!response.ok || payload?.ok === false || payload?.success === false) throw new Error(payload?.error?.message || payload?.message || 'KhÃ´ng upload Ä‘Æ°á»£c file');
    }
    if (input) input.value = '';
  }

  async function deleteAttachment(id) {
    if (!state.current?.id || !id) return;
    if (!await confirmAction({ title: 'XÃ³a file Ä‘Ã­nh kÃ¨m', message: 'XÃ³a file nÃ y?', confirmLabel: 'XÃ³a', tone: 'danger' })) return;
    await request(`${API}/${state.current.id}/attachments/${id}`, { method: 'DELETE' });
    state.current = await request(`${API}/${state.current.id}`);
    renderWorkflow(state.current);
  }

  async function searchHouseholds(query) {
    const host = $('#complaintHouseholdSuggestions');
    if (!host || query.trim().length < 2) { host?.classList.add('d-none'); return; }
    const data = await request(`${API}/household-search?q=${encodeURIComponent(query)}`, { cacheTtl: 10000 });
    host.innerHTML = (data.items || []).map(item => `<button type="button" class="list-group-item list-group-item-action" data-platform-action="complaints.household.select" data-complaint-household-id="${item.id}" data-code="${safe(item.code)}" data-head="${safe(item.head)}" data-phone="${safe(item.phone)}" data-address="${safe(item.address)}">${safe(item.label)}<br><small>${safe(item.address)}</small></button>`).join('');
    host.classList.toggle('d-none', !host.innerHTML);
  }

  function selectHousehold(data) {
    const form = $('#complaintForm');
    form.elements.household_id.value = data.complaintHouseholdId || '';
    $('#complaintHouseholdSearch').value = [data.code, data.head].filter(Boolean).join(' - ');
    $('#complaintHouseholdSelected').textContent = data.address || '';
    if (!form.elements.reporter_name.value && data.head) form.elements.reporter_name.value = data.head;
    if (!form.elements.reporter_phone.value && data.phone) form.elements.reporter_phone.value = data.phone;
    $('#complaintHouseholdSuggestions').classList.add('d-none');
  }

  async function searchCitizens(query) {
    const host = $('#complaintCitizenSuggestions');
    if (!host || query.trim().length < 2) { host?.classList.add('d-none'); return; }
    const householdId = $('#complaintForm')?.elements.household_id.value || '';
    const data = await request(`${API}/citizen-search?q=${encodeURIComponent(query)}&household_id=${encodeURIComponent(householdId)}`, { cacheTtl: 10000 });
    host.innerHTML = (data.items || []).map(item => `<button type="button" class="list-group-item list-group-item-action" data-platform-action="complaints.citizen.select" data-complaint-citizen-id="${item.id}" data-name="${safe(item.name)}" data-phone="${safe(item.phone)}" data-household-id="${item.household_id}" data-household-code="${safe(item.household_code)}" data-address="${safe(item.address)}">${safe(item.label)}<br><small>${safe(item.household_code)} - ${safe(item.address)}</small></button>`).join('');
    host.classList.toggle('d-none', !host.innerHTML);
  }

  function selectCitizen(data) {
    const form = $('#complaintForm');
    form.elements.citizen_id.value = data.complaintCitizenId || '';
    if (data.householdId && !form.elements.household_id.value) form.elements.household_id.value = data.householdId;
    $('#complaintCitizenSearch').value = data.name || '';
    $('#complaintCitizenSelected').textContent = [data.householdCode, data.address].filter(Boolean).join(' - ');
    if (!form.elements.reporter_name.value && data.name) form.elements.reporter_name.value = data.name;
    if (!form.elements.reporter_phone.value && data.phone) form.elements.reporter_phone.value = data.phone;
    $('#complaintCitizenSuggestions').classList.add('d-none');
  }

  function showRelatedPicker() {
    $('#complaintRelatedPicker')?.classList.remove('d-none');
    $('#complaintRelatedType')?.focus();
  }

  function clearRelatedSearch() {
    const input = $('#complaintRelatedSearch');
    const host = $('#complaintRelatedSuggestions');
    if (input) input.value = '';
    if (host) {
      host.innerHTML = '';
      host.classList.add('d-none');
    }
  }

  async function searchRelated(query) {
    const host = $('#complaintRelatedSuggestions');
    const type = $('#complaintRelatedType')?.value || '';
    const trimmed = query.trim();
    if (!host || !type || trimmed.length < 2) { host?.classList.add('d-none'); return; }
    const data = await request(`${API}/related-search?target_type=${encodeURIComponent(type)}&q=${encodeURIComponent(trimmed)}`, { cacheTtl: 10000 });
    host.innerHTML = (data.items || []).map(item => `<button type="button" class="list-group-item list-group-item-action" data-platform-action="complaints.related.select" data-target-type="${safe(item.target_type)}" data-target-id="${safe(item.target_id)}" data-label="${safe(item.label)}" data-type-label="${safe(item.type_label || '')}">${safe(item.label)}<br><small>${safe(item.meta || item.type_label || '')}</small></button>`).join('');
    host.classList.toggle('d-none', !host.innerHTML);
  }

  function selectRelated(data) {
    const link = {
      target_type: data.targetType || '',
      target_id: Number(data.targetId || 0),
      label: data.label || '',
      type_label: data.typeLabel || typeLabel(data.targetType || '')
    };
    if (!link.target_type || (!link.target_id && link.target_type !== 'other') || !link.label) return;
    const duplicate = state.relatedLinks.some(item => item.target_type === link.target_type && Number(item.target_id || 0) === link.target_id && item.label === link.label);
    if (!duplicate) state.relatedLinks.push(link);
    renderRelatedChips();
    clearRelatedSearch();
  }

  function removeRelated(index) {
    if (index < 0 || index >= state.relatedLinks.length) return;
    state.relatedLinks.splice(index, 1);
    renderRelatedChips();
  }

  function renderRelatedChips() {
    const host = $('#complaintRelatedChips');
    if (!host) return;
    host.innerHTML = state.relatedLinks.length ? state.relatedLinks.map((link, index) => `<span class="complaint-related-chip"><span>${safe(link.type_label || typeLabel(link.target_type))} - ${safe(link.label)}</span><button type="button" class="btn-close" aria-label="XÃ³a Ä‘á»‘i tÆ°á»£ng liÃªn quan" data-platform-action="complaints.related.remove" data-index="${index}"></button></span>`).join('') : '<span class="text-muted small">ChÆ°a cÃ³ Ä‘á»‘i tÆ°á»£ng liÃªn quan.</span>';
  }

  function normalizeRelatedLinks(links) {
    return (Array.isArray(links) ? links : []).map(link => ({ target_type: link.target_type || link.type || '', target_id: Number(link.target_id || link.id || 0), label: link.label || '', type_label: link.type_label || typeLabel(link.target_type || link.type || '') })).filter(link => link.target_type && link.label);
  }

  function typeLabel(type) {
    return (state.catalogs?.linkTypes || []).find(item => item.value === type)?.label || type;
  }

  function useGps() {
    if (!navigator.geolocation) return toast('Thiáº¿t bá»‹ khÃ´ng há»— trá»£ GPS', 'warning');
    navigator.geolocation.getCurrentPosition(position => {
      const form = $('#complaintForm');
      form.elements.latitude.value = position.coords.latitude.toFixed(8);
      form.elements.longitude.value = position.coords.longitude.toFixed(8);
      form.elements.gps_accuracy.value = position.coords.accuracy ? position.coords.accuracy.toFixed(2) : '';
      $('#complaintGpsMeta').textContent = `Vá»‹ trÃ­: ${form.elements.latitude.value}, ${form.elements.longitude.value}`;
    }, error => toast(error.message || 'KhÃ´ng láº¥y Ä‘Æ°á»£c GPS', 'danger'), { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 });
  }

  function pickMap() {
    const activate = () => {
      const map = window.App?.gis?.map;
      if (!map) return toast('Báº£n Ä‘á»“ GIS chÆ°a sáºµn sÃ ng', 'warning');
      toast('Click má»™t Ä‘iá»ƒm trÃªn báº£n Ä‘á»“ Ä‘á»ƒ chá»n vá»‹ trÃ­', 'info');
      map.once('click', event => {
        const form = $('#complaintForm');
        form.elements.latitude.value = event.latlng.lat.toFixed(8);
        form.elements.longitude.value = event.latlng.lng.toFixed(8);
        $('#complaintGpsMeta').textContent = `Vá»‹ trÃ­: ${form.elements.latitude.value}, ${form.elements.longitude.value}`;
        window.TenantAppNavigationController?.navigate('complaints');
        openModal('complaintFormModal');
      });
    };
    closeModal('complaintFormModal');
    window.TenantAppNavigationController?.navigate('gis');
    setTimeout(activate, 700);
  }

  function wrapGisLoader() {
    if (window.__complaintsGisWrapped) return;
    window.__complaintsGisWrapped = true;
    const original = window.loadGisMap;
    if (typeof original === 'function') window.loadGisMap = async function (...args) { const result = await original.apply(this, args); scheduleGisLayer(); return result; };
  }
  function scheduleGisLayer() { setTimeout(() => run(refreshGisLayer), 250); }
  async function refreshGisLayer() {
    const app = window.App;
    if (!app?.gis?.map || !window.L) return;
    const map = app.gis.map;
    if (!app.gis.complaintLayer) app.gis.complaintLayer = L.layerGroup().addTo(map);
    const layer = app.gis.complaintLayer;
    layer.clearLayers();
    if (!can('read')) return;
    const data = await request(API + '/gis', { cacheTtl: 15000 });
    (data.items || []).forEach(item => L.marker([item.latitude, item.longitude], { icon: markerIcon(item) }).bindPopup(gisPopup(item), { maxWidth: 320 }).addTo(layer));
  }
  function markerIcon(item) {
    const color = item.marker_color === 'green' ? '#16a34a' : (item.marker_color === 'yellow' ? '#f59e0b' : '#dc2626');
    return L.divIcon({ className: 'complaint-gis-icon', html: `<span style="width:32px;height:32px;border-radius:50%;background:${color};color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 20px rgba(0,0,0,.25);border:2px solid #fff"><i class="fa-solid fa-comment-dots"></i></span>`, iconSize: [32, 32], iconAnchor: [16, 16], popupAnchor: [0, -18] });
  }
  function gisPopup(item) { return `<div style="min-width:240px"><strong>${safe(item.title)}</strong><div style="font-size:12px;color:#64748b;margin:4px 0">${safe(item.complaint_code)} Â· ${safe(item.status_name)}</div><div>${safe(text(item.category_name))}</div><div class="mt-2"><button type="button" class="btn btn-sm btn-primary" data-platform-action="complaints.detail" data-id="${safe(item.id)}">Chi tiáº¿t</button></div></div>`; }

  function exportReport(format) {
    const url = `${API}/${format === 'pdf' ? 'export-pdf' : 'export-excel'}?${params()}`;
    window.open(url, '_blank', 'noopener');
  }
  async function printReport() {
    const report = await request(`${API}/report?${params()}`);
    if (window.TenantAppPrint?.print) window.TenantAppPrint.print(report);
  }

  window.loadComplaints = load;
  window.openComplaintForm = id => run(() => openForm(id));
  window.openComplaintDetail = id => run(() => openDetail(id));
  window.refreshComplaintGisLayer = () => run(refreshGisLayer);
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();
