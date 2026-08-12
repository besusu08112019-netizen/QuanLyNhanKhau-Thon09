(function () {
  'use strict';

  const API = '/api/work-calendar';
  const state = { ready: false, page: 1, pageSize: 20, search: '', category_id: '', status: '', area_code: '', date_from: '', date_to: '', sort: 'start_at', direction: 'ASC', catalogs: null, current: null };
  const $ = (selector, root = document) => root.querySelector(selector);
  const safe = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  const text = (value, empty = '--') => String(value ?? '').trim() || empty;
  const number = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
  const date = value => value ? new Date(String(value).replace(' ', 'T')).toLocaleDateString('vi-VN') : '--';
  const dateTime = value => value ? new Date(String(value).replace(' ', 'T')).toLocaleString('vi-VN') : '--';
  const toast = (message, type = 'info') => typeof window.showToast === 'function' ? window.showToast(message, type) : console[type === 'danger' ? 'error' : 'log'](message);
  const run = fn => Promise.resolve().then(fn).catch(error => toast(error.message || 'Thao tÃ¡c khÃ´ng thÃ nh cÃ´ng', 'danger'));
  const debounce = (fn, delay) => { let timer; return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); }; };

  async function request(url, options = {}) {
    if (typeof window.api === 'function') return window.api(url, options);
    const token = window.App?.token || localStorage.getItem(tenantStorageKey('token')) || '';
    const headers = { Accept: 'application/json' };
    if (token) headers.Authorization = 'Bearer ' + token;
    if (window.App?.csrfToken) headers['X-CSRF-Token'] = window.App.csrfToken;
    const init = { method: options.method || 'GET', headers };
    if (options.body instanceof FormData) init.body = options.body;
    else if (options.body) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(options.body); }
    const response = await fetch(url, init);
    const payload = await response.json().catch(() => null);
    if (!response.ok || payload?.ok === false || payload?.success === false) throw new Error(payload?.error?.message || payload?.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c dá»¯ liá»‡u');
    return payload?.data ?? payload;
  }

  const can = action => {
    const service = window.TenantAppPlatform?.permissions;
    if (service?.can) return service.can('work_calendar', action, window.App?.user);
    return typeof window.TenantAppCanAccess === 'function' ? window.TenantAppCanAccess('work_calendar', action) : true;
  };
  const openModal = id => window.TenantAppPlatform?.modals?.open?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.show();
  const closeModal = id => window.TenantAppPlatform?.modals?.close?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.hide();

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
  document.addEventListener('tenant:platform-ready', init);
  document.addEventListener('tenant:screen-change', event => { if (event.detail?.screen === 'workCalendar') { registerActions(); run(load); } });

  function init() {
    registerActions();
    if ($('#workCalendarScreen')?.classList.contains('active') || window.App?.screen === 'workCalendar') run(load);
  }

  function registerActions() {
    if (window.__TenantAppWorkCalendarActionsRegistered || !window.TenantAppPlatform?.actions) return;
    window.__TenantAppWorkCalendarActionsRegistered = true;
    window.TenantAppPlatform.actions
      .register({ key: 'workCalendar.create', handler: () => run(() => openForm()) })
      .register({ key: 'workCalendar.detail', handler: ({ dataset }) => run(() => openDetail(Number(dataset.id || 0))) })
      .register({ key: 'workCalendar.edit', handler: ({ dataset }) => run(() => openForm(Number(dataset.id || 0))) })
      .register({ key: 'workCalendar.delete', handler: ({ dataset }) => run(() => remove(Number(dataset.id || 0))) })
      .register({ key: 'workCalendar.reset', handler: () => run(reset) })
      .register({ key: 'workCalendar.sort', handler: ({ dataset }) => run(() => sortBy(dataset.calendarSort)) })
      .register({ key: 'workCalendar.page', handler: ({ dataset, target }) => !target.disabled && run(() => { state.page = Number(dataset.page || 1); return load(); }) })
      .register({ key: 'workCalendar.attachment.delete', handler: ({ dataset }) => run(() => deleteAttachment(Number(dataset.id || 0))) })
      .register({ key: 'workCalendar.export', handler: ({ dataset }) => exportReport(dataset.format || 'excel') });
    window.TenantAppPlatform.actions.bind?.(document);
  }

  function shell() {
    const host = $('#workCalendarScreen');
    if (!host || state.ready) return;
    host.classList.remove('module-placeholder-screen');
    host.innerHTML = [
      '<section id="workCalendarDashboard" class="agri-kpi-grid" aria-label="Thá»‘ng kÃª lá»‹ch cÃ´ng tÃ¡c"></section>',
      '<section class="agri-filter-card" aria-label="Bá»™ lá»c lá»‹ch cÃ´ng tÃ¡c"><div class="agri-filter-row">',
      '<div class="agri-field agri-search-field"><label for="workCalendarSearch">TÃ¬m kiáº¿m</label><div class="module-search-input-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="workCalendarSearch" class="form-control" placeholder="MÃ£, tiÃªu Ä‘á», Ä‘á»‹a Ä‘iá»ƒm, chá»§ trÃ¬..."></div></div>',
      '<div class="agri-field"><label for="workCalendarCategoryFilter">Loáº¡i</label><select id="workCalendarCategoryFilter" class="form-select"></select></div>',
      '<div class="agri-field"><label for="workCalendarStatusFilter">Tráº¡ng thÃ¡i</label><select id="workCalendarStatusFilter" class="form-select"></select></div>',
      '<div class="agri-field"><label for="workCalendarAreaFilter">Äá»‹a bÃ n</label><input id="workCalendarAreaFilter" class="form-control" placeholder="MÃ£ khu vá»±c"></div>',
      '<div class="agri-field"><label for="workCalendarDateFrom">Tá»« ngÃ y</label><input id="workCalendarDateFrom" class="form-control" type="date"></div>',
      '<div class="agri-field"><label for="workCalendarDateTo">Äáº¿n ngÃ y</label><input id="workCalendarDateTo" class="form-control" type="date"></div>',
      '<div class="agri-field module-page-size-field"><label for="workCalendarPageSize">Hiá»ƒn thá»‹</label><select id="workCalendarPageSize" class="form-select"><option>20</option><option>50</option><option>100</option></select></div>',
      '<div class="agri-field agri-actions"><button class="btn btn-outline-secondary" type="button" data-platform-action="workCalendar.reset"><i class="fa-solid fa-rotate-right"></i></button><button class="btn btn-outline-success" type="button" data-platform-action="workCalendar.export" data-format="excel"><i class="fa-solid fa-file-excel"></i> Excel</button><button class="btn btn-outline-danger" type="button" data-platform-action="workCalendar.export" data-format="pdf"><i class="fa-solid fa-file-pdf"></i> PDF</button></div>',
      '</div></section>',
      '<section class="work-calendar-grid"><article class="module-list-card household-list-card"><div class="module-list-head"><div><h3>Lá»‹ch thÃ¡ng</h3><span id="workCalendarMonthLabel">--</span></div></div><div id="workCalendarMonth" class="work-calendar-month"></div></article><article class="module-list-card household-list-card"><div class="module-list-head"><div><h3>Danh sÃ¡ch lá»‹ch cÃ´ng tÃ¡c</h3><span id="workCalendarTotalCount">Tá»•ng sá»‘: 0 lá»‹ch</span></div><button class="btn btn-success" type="button" data-platform-action="workCalendar.create"><i class="fa-solid fa-plus"></i> ThÃªm lá»‹ch</button></div><div class="table-responsive"><table class="table module-table align-middle mb-0"><thead><tr><th data-platform-action="workCalendar.sort" data-calendar-sort="event_code">MÃ£</th><th data-platform-action="workCalendar.sort" data-calendar-sort="title">Ná»™i dung</th><th data-platform-action="workCalendar.sort" data-calendar-sort="category">Loáº¡i</th><th data-platform-action="workCalendar.sort" data-calendar-sort="start_at">Thá»i gian</th><th>Äá»‹a Ä‘iá»ƒm</th><th data-platform-action="workCalendar.sort" data-calendar-sort="host">Chá»§ trÃ¬</th><th data-platform-action="workCalendar.sort" data-calendar-sort="status">Tráº¡ng thÃ¡i</th><th class="text-end">Thao tÃ¡c</th></tr></thead><tbody id="workCalendarRows"></tbody></table></div><div id="workCalendarPager" class="pager module-pager"></div></article></section>',
      formModal(),
      detailModal()
    ].join('');
    bind();
    registerModals();
    state.ready = true;
  }

  function registerModals() {
    window.TenantAppPlatform?.modals?.registerBootstrap?.('workCalendarModal', '#workCalendarModal');
    window.TenantAppPlatform?.modals?.registerBootstrap?.('workCalendarDetailModal', '#workCalendarDetailModal');
  }

  function formModal() {
    return '<div class="modal fade" id="workCalendarModal" tabindex="-1"><div class="modal-dialog modal-xl"><form id="workCalendarForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">Lá»‹ch cÃ´ng tÃ¡c</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="row g-3"><div class="col-md-8"><label class="form-label">TiÃªu Ä‘á»</label><input name="title" class="form-control" required maxlength="255"></div><div class="col-md-4"><label class="form-label">Loáº¡i lá»‹ch</label><select name="category_id" id="workCalendarCategoryInput" class="form-select"></select></div><div class="col-md-3"><label class="form-label">Báº¯t Ä‘áº§u</label><input name="start_at" type="datetime-local" class="form-control" required></div><div class="col-md-3"><label class="form-label">Káº¿t thÃºc</label><input name="end_at" type="datetime-local" class="form-control"></div><div class="col-md-3"><label class="form-label">Nháº¯c viá»‡c</label><input name="reminder_at" type="datetime-local" class="form-control"></div><div class="col-md-3"><label class="form-label">Tráº¡ng thÃ¡i</label><select name="status" id="workCalendarStatusInput" class="form-select"></select></div><div class="col-md-4"><label class="form-label">Äá»‹a Ä‘iá»ƒm</label><input name="location" class="form-control" maxlength="255"></div><div class="col-md-4"><label class="form-label">Chá»§ trÃ¬</label><input name="host_name" class="form-control" maxlength="255"></div><div class="col-md-4"><label class="form-label">Äá»‹a bÃ n</label><input name="area_code" class="form-control" maxlength="80"></div><div class="col-12"><label class="form-label">MÃ´ táº£</label><textarea name="description" class="form-control" rows="3"></textarea></div><div class="col-12"><label class="form-label">Danh sÃ¡ch tham dá»±</label><textarea name="attendees_text" class="form-control" rows="3" placeholder="Má»—i dÃ²ng má»™t ngÆ°á»i: Há» tÃªn | SÄT | Vai trÃ²"></textarea></div><div class="col-12"><label class="form-label">File Ä‘Ã­nh kÃ¨m</label><input id="workCalendarFiles" class="form-control" type="file" multiple accept="image/*,video/mp4,video/webm,application/pdf,.doc,.docx,.xls,.xlsx,.csv"></div><div class="col-12"><label class="form-label">Ghi chÃº</label><textarea name="note" class="form-control" rows="2"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ÄÃ³ng</button><button class="btn btn-primary" type="submit">LÆ°u lá»‹ch</button></div></form></div></div>';
  }

  function detailModal() {
    return '<div class="modal fade" id="workCalendarDetailModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><h5 id="workCalendarDetailTitle" class="modal-title">Chi tiáº¿t lá»‹ch cÃ´ng tÃ¡c</h5><small id="workCalendarDetailSub" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div id="workCalendarDetailBody" class="modal-body"></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">ÄÃ³ng</button></div></div></div></div>';
  }

  function bind() {
    $('#workCalendarSearch')?.addEventListener('input', debounce(() => run(() => { state.page = 1; readFilters(); return load(); }), 350));
    ['workCalendarCategoryFilter', 'workCalendarStatusFilter', 'workCalendarAreaFilter', 'workCalendarDateFrom', 'workCalendarDateTo'].forEach(id => $('#' + id)?.addEventListener('change', () => run(() => { state.page = 1; readFilters(); return load(); })));
    $('#workCalendarPageSize')?.addEventListener('change', event => run(() => { state.pageSize = Number(event.target.value || 20); state.page = 1; return load(); }));
    $('#workCalendarForm')?.addEventListener('submit', event => { event.preventDefault(); run(() => save(event.currentTarget)); });
  }

  async function catalogs() {
    if (state.catalogs) return state.catalogs;
    state.catalogs = await request(API + '/catalogs', { cacheTtl: 60000 });
    fill($('#workCalendarCategoryFilter'), state.catalogs.categories, 'Táº¥t cáº£');
    fill($('#workCalendarStatusFilter'), state.catalogs.statuses, 'Táº¥t cáº£');
    fill($('#workCalendarCategoryInput'), state.catalogs.categories, 'Chá»n loáº¡i');
    fill($('#workCalendarStatusInput'), state.catalogs.statuses, '');
    return state.catalogs;
  }

  function fill(select, items = [], first = '') {
    if (!select) return;
    const current = select.value;
    select.innerHTML = first ? `<option value="">${safe(first)}</option>` : '';
    uniqueOptions(items).forEach(item => {
      const option = document.createElement('option');
      option.value = item.value;
      option.textContent = item.label;
      option.dataset.code = item.code || '';
      option.dataset.color = item.color || '';
      select.appendChild(option);
    });
    if ([...select.options].some(option => option.value === current)) select.value = current;
  }

  function uniqueOptions(items = []) {
    const seen = new Set();
    return items.filter(item => {
      const key = String(item.code || item.value || item.label || '').trim().toLowerCase();
      if (!key || seen.has(key)) return false;
      seen.add(key);
      return true;
    });
  }

  function readFilters() {
    state.search = $('#workCalendarSearch')?.value.trim() || '';
    state.category_id = $('#workCalendarCategoryFilter')?.value || '';
    state.status = $('#workCalendarStatusFilter')?.value || '';
    state.area_code = $('#workCalendarAreaFilter')?.value.trim() || '';
    state.date_from = $('#workCalendarDateFrom')?.value || '';
    state.date_to = $('#workCalendarDateTo')?.value || '';
    state.pageSize = Number($('#workCalendarPageSize')?.value || state.pageSize || 20);
  }

  function params() {
    readFilters();
    return new URLSearchParams({ page: state.page, pageSize: state.pageSize, search: state.search, category_id: state.category_id, status: state.status, area_code: state.area_code, date_from: state.date_from, date_to: state.date_to, sort: state.sort, direction: state.direction });
  }

  async function load() {
    if (!$('#workCalendarScreen')) return;
    shell();
    await catalogs();
    const query = params();
    const [list, dashboard] = await Promise.all([request(API + '?' + query), request(API + '/dashboard?' + query, { cacheTtl: 15000 })]);
    renderDashboard(dashboard);
    renderMonth(list.items || []);
    renderRows(list);
    renderPager(list);
    window.TenantAppApplyAccessControls?.();
    window.TenantAppSyncResponsiveTableLabels?.($('#workCalendarScreen'));
  }

  function renderDashboard(data = {}) {
    const metrics = data.metrics || {};
    const cards = [['Tá»•ng lá»‹ch', 'fa-calendar-days', metrics.total || 0], ['HÃ´m nay', 'fa-calendar-day', metrics.today_count || 0], ['7 ngÃ y tá»›i', 'fa-bell', metrics.week_count || 0], ['HoÃ n thÃ nh', 'fa-circle-check', metrics.done_count || 0], ['ÄÃ£ há»§y', 'fa-ban', metrics.cancelled_count || 0]];
    $('#workCalendarDashboard').innerHTML = cards.map(card => `<article class="agri-kpi-card"><span><i class="fa-solid ${card[1]}"></i></span><div><strong>${number(card[2])}</strong><small>${safe(card[0])}</small></div></article>`).join('');
  }

  function renderMonth(items) {
    const base = state.date_from ? new Date(state.date_from + 'T00:00:00') : new Date();
    const year = base.getFullYear(), month = base.getMonth();
    $('#workCalendarMonthLabel').textContent = `ThÃ¡ng ${month + 1}/${year}`;
    const first = new Date(year, month, 1);
    const startOffset = (first.getDay() + 6) % 7;
    const days = new Date(year, month + 1, 0).getDate();
    const buckets = {};
    items.forEach(item => { const key = String(item.start_at || '').slice(0, 10); (buckets[key] ||= []).push(item); });
    let html = ['T2','T3','T4','T5','T6','T7','CN'].map(d => `<strong>${d}</strong>`).join('');
    for (let i = 0; i < startOffset; i++) html += '<span class="work-calendar-day is-empty"></span>';
    for (let d = 1; d <= days; d++) {
      const key = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
      const events = (buckets[key] || []).slice(0, 3).map(e => `<button type="button" data-platform-action="workCalendar.detail" data-id="${e.id}">${safe(e.title)}</button>`).join('');
      html += `<span class="work-calendar-day"><b>${d}</b>${events}</span>`;
    }
    $('#workCalendarMonth').innerHTML = html;
  }

  function renderRows(data = {}) {
    const rows = data.items || [];
    $('#workCalendarTotalCount').textContent = `Tá»•ng sá»‘: ${number(data.total || 0)} lá»‹ch`;
    const tbody = $('#workCalendarRows');
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">ChÆ°a cÃ³ lá»‹ch cÃ´ng tÃ¡c phÃ¹ há»£p bá»™ lá»c.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(item => {
      const actions = [`<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="workCalendar.detail" data-id="${item.id}" title="Xem"><i class="fa-solid fa-eye"></i></button>`, can('update') ? `<button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="workCalendar.edit" data-id="${item.id}" title="Sá»­a"><i class="fa-solid fa-pen"></i></button>` : '', can('delete') ? `<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="workCalendar.delete" data-id="${item.id}" title="XÃ³a"><i class="fa-solid fa-trash"></i></button>` : ''].filter(Boolean).join(' ');
      return `<tr><td><strong>${safe(item.event_code)}</strong></td><td><div class="fw-semibold">${safe(text(item.title))}</div><small class="text-muted">${safe(text(item.description, '').slice(0, 80))}</small></td><td>${categoryBadge(item)}</td><td>${dateTime(item.start_at)}</td><td>${safe(text(item.location))}</td><td>${safe(text(item.host_name))}</td><td>${statusBadge(item.status)}</td><td class="text-end">${actions}</td></tr>`;
    }).join('');
  }

  function renderPager(data = {}) {
    const host = $('#workCalendarPager');
    const totalPages = Number(data.totalPages || 1);
    const page = Number(data.page || state.page || 1);
    state.page = page;
    if (totalPages <= 1) { host.innerHTML = ''; return; }
    host.innerHTML = `<div class="d-flex gap-2 justify-content-end flex-wrap"><button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="workCalendar.page" data-page="${Math.max(1, page - 1)}" ${page <= 1 ? 'disabled' : ''}>TrÆ°á»›c</button><span class="px-2">${page} / ${totalPages}</span><button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="workCalendar.page" data-page="${Math.min(totalPages, page + 1)}" ${page >= totalPages ? 'disabled' : ''}>Sau</button></div>`;
  }

  async function openForm(id = null) {
    if (id && !can('update')) return toast('KhÃ´ng cÃ³ quyá»n sá»­a', 'warning');
    if (!id && !can('create')) return toast('KhÃ´ng cÃ³ quyá»n thÃªm', 'warning');
    await catalogs();
    const form = $('#workCalendarForm');
    form.reset();
    form.elements.id.value = '';
    form.elements.status.value = 'SCHEDULED';
    form.elements.start_at.value = datetimeLocal(new Date().toISOString());
    $('#workCalendarFiles').value = '';
    if (id) {
      const item = await request(API + '/' + id);
      Object.entries(item).forEach(([key, value]) => { if (form.elements[key]) form.elements[key].value = datetimeLocal(value) ?? value ?? ''; });
      form.elements.attendees_text.value = (item.attendees || []).map(a => [a.attendee_name, a.phone || '', a.role_name || ''].join(' | ')).join('\n');
      form.elements.id.value = item.id;
    }
    openModal('workCalendarModal');
  }

  async function save(form) {
    const body = Object.fromEntries(new FormData(form).entries());
    const id = Number(body.id || 0);
    body.attendees = parseAttendees(body.attendees_text);
    delete body.attendees_text;
    delete body.id;
    const saved = await request(id ? API + '/' + id : API, { method: id ? 'PUT' : 'POST', body });
    for (const file of Array.from($('#workCalendarFiles')?.files || [])) {
      const upload = new FormData();
      upload.append('file', file);
      await request(API + '/' + saved.id + '/attachments', { method: 'POST', body: upload });
    }
    closeModal('workCalendarModal');
    toast('ÄÃ£ lÆ°u lá»‹ch cÃ´ng tÃ¡c', 'success');
    await load();
  }

  async function openDetail(id) {
    const item = await request(API + '/' + id);
    state.current = item;
    $('#workCalendarDetailTitle').textContent = item.title || 'Chi tiáº¿t lá»‹ch cÃ´ng tÃ¡c';
    $('#workCalendarDetailSub').textContent = `${item.event_code || ''} - ${item.status_label || ''}`;
    $('#workCalendarDetailBody').innerHTML = detailHtml(item);
    openModal('workCalendarDetailModal');
  }

  function detailHtml(item) {
    const attendees = (item.attendees || []).map(a => `<tr><td>${safe(a.attendee_name)}</td><td>${safe(text(a.phone, ''))}</td><td>${safe(text(a.role_name, ''))}</td><td>${safe(attendanceLabel(a.attendance_status))}</td></tr>`).join('') || '<tr><td colspan="4" class="text-muted">ChÆ°a cÃ³ danh sÃ¡ch tham dá»±</td></tr>';
    const attachments = (item.attachments || []).map(file => `<div class="work-task-file"><i class="fa-solid ${fileIcon(file)}"></i><a href="${safe(file.preview_url)}" target="_blank" rel="noopener">${safe(file.original_name)}</a><small>${safe(file.file_kind || '')}</small>${can('delete') ? `<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="workCalendar.attachment.delete" data-id="${file.id}"><i class="fa-solid fa-trash"></i></button>` : ''}</div>`).join('') || '<div class="text-muted">ChÆ°a cÃ³ file Ä‘Ã­nh kÃ¨m</div>';
    return `<div class="row g-3"><div class="col-md-7"><dl class="row mb-0"><dt class="col-sm-3">Loáº¡i</dt><dd class="col-sm-9">${categoryBadge(item)}</dd><dt class="col-sm-3">Thá»i gian</dt><dd class="col-sm-9">${dateTime(item.start_at)} - ${dateTime(item.end_at)}</dd><dt class="col-sm-3">Nháº¯c viá»‡c</dt><dd class="col-sm-9">${dateTime(item.reminder_at)}</dd><dt class="col-sm-3">Äá»‹a Ä‘iá»ƒm</dt><dd class="col-sm-9">${safe(text(item.location))}</dd><dt class="col-sm-3">Chá»§ trÃ¬</dt><dd class="col-sm-9">${safe(text(item.host_name))}</dd><dt class="col-sm-3">Ná»™i dung</dt><dd class="col-sm-9">${safe(text(item.description))}</dd></dl></div><div class="col-md-5"><h6>File Ä‘Ã­nh kÃ¨m</h6><div class="work-task-files">${attachments}</div></div><div class="col-12"><h6>Danh sÃ¡ch tham dá»±</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Há» tÃªn</th><th>Äiá»‡n thoáº¡i</th><th>Vai trÃ²</th><th>Tráº¡ng thÃ¡i</th></tr></thead><tbody>${attendees}</tbody></table></div></div></div>`;
  }

  async function deleteAttachment(fileId) {
    if (!state.current?.id || !fileId) return;
    if (!window.confirm('XÃ³a file Ä‘Ã­nh kÃ¨m nÃ y?')) return;
    await request(API + '/' + state.current.id + '/attachments/' + fileId, { method: 'DELETE' });
    await openDetail(state.current.id);
  }

  async function remove(id) {
    if (!id || !can('delete')) return;
    if (!window.confirm('XÃ³a lá»‹ch cÃ´ng tÃ¡c nÃ y?')) return;
    await request(API + '/' + id, { method: 'DELETE' });
    toast('ÄÃ£ xÃ³a lá»‹ch cÃ´ng tÃ¡c', 'success');
    await load();
  }

  async function reset() {
    ['workCalendarSearch', 'workCalendarCategoryFilter', 'workCalendarStatusFilter', 'workCalendarAreaFilter', 'workCalendarDateFrom', 'workCalendarDateTo'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
    Object.assign(state, { page: 1, search: '', category_id: '', status: '', area_code: '', date_from: '', date_to: '', sort: 'start_at', direction: 'ASC' });
    await load();
  }

  function sortBy(key) { if (!key) return; if (state.sort === key) state.direction = state.direction === 'ASC' ? 'DESC' : 'ASC'; else { state.sort = key; state.direction = 'ASC'; } state.page = 1; return load(); }
  function exportReport(format) { const query = params(); window.open((format === 'pdf' ? API + '/export-pdf' : API + '/export-excel') + '?' + query.toString(), '_blank', 'noopener'); }
  function parseAttendees(value) { return String(value || '').split(/\r?\n/).map(line => line.trim()).filter(Boolean).map(line => { const parts = line.split('|').map(p => p.trim()); return { attendee_name: parts[0] || '', phone: parts[1] || '', role_name: parts[2] || '', attendance_status: 'INVITED' }; }); }
  function datetimeLocal(value) { if (!value) return null; const d = String(value).replace(' ', 'T'); return /^\d{4}-\d{2}-\d{2}T/.test(d) ? d.slice(0, 16) : null; }
  function categoryBadge(item) { return `<span class="badge" style="background:${safe(item.category_color || '#6c757d')}">${safe(text(item.category_name))}</span>`; }
  function statusBadge(status) { const tones = { SCHEDULED: 'primary', DONE: 'success', CANCELLED: 'secondary' }; return `<span class="badge bg-${tones[status] || 'secondary'}">${safe(statusLabel(status))}</span>`; }
  function statusLabel(status) { return { SCHEDULED: 'ÄÃ£ lÃªn lá»‹ch', DONE: 'ÄÃ£ hoÃ n thÃ nh', CANCELLED: 'ÄÃ£ há»§y' }[status] || status; }
  function attendanceLabel(status) { return { INVITED: 'ÄÃ£ má»i', ATTENDED: 'CÃ³ máº·t', ABSENT: 'Váº¯ng', EXCUSED: 'CÃ³ lÃ½ do' }[status] || status; }
  function fileIcon(file) { const kind = String(file.file_kind || '').toUpperCase(); if (kind === 'IMAGE') return 'fa-image'; if (kind === 'VIDEO') return 'fa-video'; if (kind === 'PDF') return 'fa-file-pdf'; return 'fa-file-lines'; }

  window.loadWorkCalendar = load;
})();
