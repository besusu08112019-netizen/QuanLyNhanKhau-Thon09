(function () {
  'use strict';

  const API = '/api/work-tasks';
  const state = { ready: false, page: 1, pageSize: 20, search: '', category_id: '', priority_id: '', status_id: '', area_code: '', date_from: '', date_to: '', overdue: '', sort: 'due_at', direction: 'ASC', catalogs: null, current: null, sourceNote: '' };
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const safe = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  const text = (value, empty = '--') => String(value ?? '').trim() || empty;
  const number = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
  const date = value => value ? new Date(String(value).replace(' ', 'T')).toLocaleDateString('vi-VN') : '--';
  const dateTime = value => value ? new Date(String(value).replace(' ', 'T')).toLocaleString('vi-VN') : '--';
  const toast = (message, type = 'info') => typeof window.showToast === 'function' ? window.showToast(message, type) : console[type === 'danger' ? 'error' : 'log'](message);

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
    if (service?.can) return service.can('work_tasks', action, window.App?.user);
    return typeof window.TenantAppCanAccess === 'function' ? window.TenantAppCanAccess('work_tasks', action) : true;
  };
  const openModal = id => window.TenantAppPlatform?.modals?.open?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.show();
  const closeModal = id => window.TenantAppPlatform?.modals?.close?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.hide();
  const run = fn => Promise.resolve().then(fn).catch(error => toast(error.message || 'Thao tÃ¡c khÃ´ng thÃ nh cÃ´ng', 'danger'));
  const debounce = (fn, delay) => { let timer; return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); }; };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
  document.addEventListener('tenant:platform-ready', init);
  document.addEventListener('tenant:screen-change', event => { if (event.detail?.screen === 'workTasks') { registerActions(); run(load); } });

  function init() {
    registerActions();
    if ($('#workTasksScreen')?.classList.contains('active') || window.App?.screen === 'workTasks') run(load);
  }

  function registerActions() {
    if (window.__TenantAppWorkTasksActionsRegistered || !window.TenantAppPlatform?.actions) return;
    window.__TenantAppWorkTasksActionsRegistered = true;
    window.TenantAppPlatform.actions
      .register({ key: 'workTasks.create', handler: () => run(() => openForm()) })
      .register({ key: 'workTasks.createFromSource', handler: ({ dataset }) => run(() => openPrefilledForm(sourceFromDataset(dataset))) })
      .register({ key: 'workTasks.detail', handler: ({ dataset }) => run(() => openDetail(Number(dataset.id || 0))) })
      .register({ key: 'workTasks.edit', handler: ({ dataset }) => run(() => openForm(Number(dataset.id || 0))) })
      .register({ key: 'workTasks.delete', handler: ({ dataset }) => run(() => remove(Number(dataset.id || 0))) })
      .register({ key: 'workTasks.search', handler: () => run(() => { state.page = 1; readFilters(); return load(); }) })
      .register({ key: 'workTasks.reset', handler: () => run(reset) })
      .register({ key: 'workTasks.sort', handler: ({ dataset }) => run(() => sortBy(dataset.workTaskSort)) })
      .register({ key: 'workTasks.page', handler: ({ dataset, target }) => !target.disabled && run(() => { state.page = Number(dataset.page || 1); return load(); }) })
      .register({ key: 'workTasks.log.add', handler: () => run(addLog) })
      .register({ key: 'workTasks.attachment.delete', handler: ({ dataset }) => run(() => deleteAttachment(Number(dataset.id || 0))) })
      .register({ key: 'workTasks.export', handler: ({ dataset }) => exportReport(dataset.format || 'excel') });
    window.TenantAppPlatform.actions.bind?.(document);
  }

  function shell() {
    const host = $('#workTasksScreen');
    if (!host || state.ready) return;
    host.classList.remove('module-placeholder-screen');
    host.innerHTML = [
      '<section id="workTasksDashboard" class="agri-kpi-grid" aria-label="Thá»‘ng kÃª cÃ´ng viá»‡c"></section>',
      '<section class="agri-filter-card" aria-label="Bá»™ lá»c cÃ´ng viá»‡c"><div class="agri-filter-row">',
      '<div class="agri-field agri-search-field"><label for="workTasksSearch">TÃ¬m kiáº¿m</label><div class="module-search-input-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="workTasksSearch" class="form-control" placeholder="MÃ£, tiÃªu Ä‘á», ngÆ°á»i phá»¥ trÃ¡ch, khu vá»±c..."></div></div>',
      '<div class="agri-field"><label for="workTasksCategoryFilter">Loáº¡i</label><select id="workTasksCategoryFilter" class="form-select"></select></div>',
      '<div class="agri-field"><label for="workTasksStatusFilter">Tráº¡ng thÃ¡i</label><select id="workTasksStatusFilter" class="form-select"></select></div>',
      '<div class="agri-field"><label for="workTasksPriorityFilter">Æ¯u tiÃªn</label><select id="workTasksPriorityFilter" class="form-select"></select></div>',
      '<div class="agri-field"><label for="workTasksOverdueFilter">QuÃ¡ háº¡n</label><select id="workTasksOverdueFilter" class="form-select"><option value="">Táº¥t cáº£</option><option value="1">QuÃ¡ háº¡n</option><option value="0">ChÆ°a quÃ¡ háº¡n</option></select></div>',
      '<div class="agri-field"><label for="workTasksAreaFilter">Äá»‹a bÃ n</label><input id="workTasksAreaFilter" class="form-control" placeholder="MÃ£ khu vá»±c"></div>',
      '<div class="agri-field"><label for="workTasksDateFrom">Tá»« ngÃ y</label><input id="workTasksDateFrom" class="form-control" type="date"></div>',
      '<div class="agri-field"><label for="workTasksDateTo">Äáº¿n ngÃ y</label><input id="workTasksDateTo" class="form-control" type="date"></div>',
      '<div class="agri-field module-page-size-field"><label for="workTasksPageSize">Hiá»ƒn thá»‹</label><select id="workTasksPageSize" class="form-select"><option>20</option><option>50</option><option>100</option></select></div>',
      '<div class="agri-field agri-actions"><button class="btn btn-outline-secondary" type="button" data-platform-action="workTasks.reset"><i class="fa-solid fa-rotate-right"></i></button><button class="btn btn-outline-success" type="button" data-platform-action="workTasks.export" data-format="excel"><i class="fa-solid fa-file-excel"></i> Excel</button><button class="btn btn-outline-danger" type="button" data-platform-action="workTasks.export" data-format="pdf"><i class="fa-solid fa-file-pdf"></i> PDF</button></div>',
      '</div></section>',
      '<section class="module-list-card household-list-card"><div class="module-list-head"><div><h3>Danh sÃ¡ch cÃ´ng viá»‡c</h3><span id="workTasksTotalCount">Tá»•ng sá»‘: 0 cÃ´ng viá»‡c</span></div><button class="btn btn-success" type="button" data-platform-action="workTasks.create"><i class="fa-solid fa-plus"></i> ThÃªm cÃ´ng viá»‡c</button></div><div class="table-responsive"><table class="table module-table align-middle mb-0"><thead><tr><th data-platform-action="workTasks.sort" data-work-task-sort="task_code">MÃ£</th><th data-platform-action="workTasks.sort" data-work-task-sort="title">CÃ´ng viá»‡c</th><th data-platform-action="workTasks.sort" data-work-task-sort="category">Loáº¡i</th><th data-platform-action="workTasks.sort" data-work-task-sort="priority">Æ¯u tiÃªn</th><th data-platform-action="workTasks.sort" data-work-task-sort="status">Tráº¡ng thÃ¡i</th><th data-platform-action="workTasks.sort" data-work-task-sort="assigned">Phá»¥ trÃ¡ch</th><th data-platform-action="workTasks.sort" data-work-task-sort="progress">Tiáº¿n Ä‘á»™</th><th data-platform-action="workTasks.sort" data-work-task-sort="due_at">Háº¡n</th><th class="text-end">Thao tÃ¡c</th></tr></thead><tbody id="workTasksRows"></tbody></table></div><div id="workTasksPager" class="pager module-pager"></div></section>',
      formModal(),
      detailModal()
    ].join('');
    bind();
    registerModals();
    state.ready = true;
  }

  function registerModals() {
    window.TenantAppPlatform?.modals?.registerBootstrap?.('workTaskModal', '#workTaskModal');
    window.TenantAppPlatform?.modals?.registerBootstrap?.('workTaskDetailModal', '#workTaskDetailModal');
  }

  function formModal() {
    return '<div class="modal fade" id="workTaskModal" tabindex="-1"><div class="modal-dialog modal-xl"><form id="workTaskForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">CÃ´ng viá»‡c</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="row g-3"><div class="col-md-8"><label class="form-label">TiÃªu Ä‘á»</label><input name="title" class="form-control" required maxlength="255"></div><div class="col-md-4"><label class="form-label">Loáº¡i cÃ´ng viá»‡c</label><select name="category_id" id="workTaskCategoryInput" class="form-select"></select></div><div class="col-md-4"><label class="form-label">Æ¯u tiÃªn</label><select name="priority_id" id="workTaskPriorityInput" class="form-select"></select></div><div class="col-md-4"><label class="form-label">Tráº¡ng thÃ¡i</label><select name="status_id" id="workTaskStatusInput" class="form-select"></select></div><div class="col-md-4"><label class="form-label">Tiáº¿n Ä‘á»™ (%)</label><input name="progress_percent" type="number" min="0" max="100" class="form-control" value="0"></div><div class="col-md-4"><label class="form-label">NgÆ°á»i phá»¥ trÃ¡ch</label><input name="assigned_name" class="form-control" maxlength="255"></div><div class="col-md-4"><label class="form-label">NgÃ y báº¯t Ä‘áº§u</label><input name="start_at" type="datetime-local" class="form-control"></div><div class="col-md-4"><label class="form-label">Háº¡n hoÃ n thÃ nh</label><input name="due_at" type="datetime-local" class="form-control"></div><div class="col-md-4"><label class="form-label">Äá»‹a bÃ n</label><input name="area_code" class="form-control" maxlength="80"></div><div class="col-md-4"><label class="form-label">Module liÃªn káº¿t</label><select name="related_module" id="workTaskRelatedModuleInput" class="form-select"></select></div><div class="col-md-4"><label class="form-label">ID liÃªn káº¿t</label><input name="related_id" type="number" min="1" class="form-control"></div><div class="col-12"><label class="form-label">MÃ´ táº£</label><textarea name="description" class="form-control" rows="3"></textarea></div><div class="col-12"><label class="form-label">Ghi chÃº</label><textarea name="note" class="form-control" rows="2"></textarea></div><div class="col-12"><label class="form-label">File Ä‘Ã­nh kÃ¨m</label><input id="workTaskFiles" class="form-control" type="file" multiple accept="image/*,video/mp4,video/webm,application/pdf,.doc,.docx,.xls,.xlsx,.csv"><div class="form-text">File sáº½ Ä‘Æ°á»£c táº£i lÃªn sau khi lÆ°u cÃ´ng viá»‡c.</div></div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ÄÃ³ng</button><button class="btn btn-primary" type="submit">LÆ°u cÃ´ng viá»‡c</button></div></form></div></div>';
  }

  function detailModal() {
    return '<div class="modal fade" id="workTaskDetailModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><h5 id="workTaskDetailTitle" class="modal-title">Chi tiáº¿t cÃ´ng viá»‡c</h5><small id="workTaskDetailSub" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div id="workTaskDetailBody"></div><hr><form id="workTaskLogForm" class="row g-2 align-items-end"><div class="col-md-5"><label class="form-label">Ná»™i dung cáº­p nháº­t</label><input name="content" class="form-control" required></div><div class="col-md-3"><label class="form-label">Tráº¡ng thÃ¡i sau cáº­p nháº­t</label><select name="status_id" id="workTaskLogStatusInput" class="form-select"></select></div><div class="col-md-2"><label class="form-label">Tiáº¿n Ä‘á»™</label><input name="progress_percent" type="number" min="0" max="100" class="form-control"></div><div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-clock-rotate-left"></i> Ghi nháº­t kÃ½</button></div></form></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">ÄÃ³ng</button></div></div></div></div>';
  }

  function bind() {
    $('#workTasksSearch')?.addEventListener('input', debounce(() => run(() => { state.page = 1; readFilters(); return load(); }), 350));
    ['workTasksCategoryFilter', 'workTasksPriorityFilter', 'workTasksStatusFilter', 'workTasksOverdueFilter', 'workTasksAreaFilter', 'workTasksDateFrom', 'workTasksDateTo'].forEach(id => $('#' + id)?.addEventListener('change', () => run(() => { state.page = 1; readFilters(); return load(); })));
    $('#workTasksPageSize')?.addEventListener('change', event => run(() => { state.pageSize = Number(event.target.value || 20); state.page = 1; return load(); }));
    $('#workTaskForm')?.addEventListener('submit', event => { event.preventDefault(); run(() => save(event.currentTarget)); });
    $('#workTaskLogForm')?.addEventListener('submit', event => { event.preventDefault(); run(addLog); });
  }

  async function catalogs() {
    if (state.catalogs) return state.catalogs;
    state.catalogs = await request(API + '/catalogs', { cacheTtl: 60000 });
    fill($('#workTasksCategoryFilter'), state.catalogs.categories, 'Táº¥t cáº£');
    fill($('#workTasksPriorityFilter'), state.catalogs.priorities, 'Táº¥t cáº£');
    fill($('#workTasksStatusFilter'), state.catalogs.statuses, 'Táº¥t cáº£');
    fill($('#workTaskCategoryInput'), state.catalogs.categories, 'Chá»n loáº¡i');
    fill($('#workTaskPriorityInput'), state.catalogs.priorities, 'Chá»n Æ°u tiÃªn');
    fill($('#workTaskStatusInput'), state.catalogs.statuses, 'Chá»n tráº¡ng thÃ¡i');
    fill($('#workTaskLogStatusInput'), state.catalogs.statuses, 'Giá»¯ nguyÃªn');
    fill($('#workTaskRelatedModuleInput'), state.catalogs.related_modules, '');
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
      option.dataset.progress = item.progress_percent ?? '';
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
    state.search = $('#workTasksSearch')?.value.trim() || '';
    state.category_id = $('#workTasksCategoryFilter')?.value || '';
    state.priority_id = $('#workTasksPriorityFilter')?.value || '';
    state.status_id = $('#workTasksStatusFilter')?.value || '';
    state.overdue = $('#workTasksOverdueFilter')?.value || '';
    state.area_code = $('#workTasksAreaFilter')?.value.trim() || '';
    state.date_from = $('#workTasksDateFrom')?.value || '';
    state.date_to = $('#workTasksDateTo')?.value || '';
    state.pageSize = Number($('#workTasksPageSize')?.value || state.pageSize || 20);
  }

  function params() {
    readFilters();
    return new URLSearchParams({ page: state.page, pageSize: state.pageSize, search: state.search, category_id: state.category_id, priority_id: state.priority_id, status_id: state.status_id, area_code: state.area_code, date_from: state.date_from, date_to: state.date_to, overdue: state.overdue, sort: state.sort, direction: state.direction });
  }

  async function load() {
    if (!$('#workTasksScreen')) return;
    shell();
    await catalogs();
    const query = params();
    const [list, dashboard] = await Promise.all([request(API + '?' + query), request(API + '/dashboard?' + query, { cacheTtl: 15000 })]);
    renderDashboard(dashboard);
    renderRows(list);
    renderPager(list);
    window.TenantAppApplyAccessControls?.();
    window.TenantAppSyncResponsiveTableLabels?.($('#workTasksScreen'));
  }

  function renderDashboard(data = {}) {
    const metrics = data.metrics || {};
    const category = (data.charts?.by_category || []).slice(0, 5).map(row => `<span class="badge bg-light text-dark border me-1 mb-1">${safe(text(row.label, 'KhÃ¡c'))}: ${number(row.value)}</span>`).join('');
    const cards = [
      ['Tá»•ng cÃ´ng viá»‡c', 'fa-list-check', metrics.total || 0],
      ['Má»›i táº¡o', 'fa-circle-plus', metrics.new_count || 0],
      ['Äang xá»­ lÃ½', 'fa-spinner', metrics.processing_count || 0],
      ['HoÃ n thÃ nh', 'fa-circle-check', metrics.done_count || 0],
      ['QuÃ¡ háº¡n', 'fa-clock', metrics.overdue_count || 0],
      ['Tiáº¿n Ä‘á»™ TB', 'fa-chart-line', (metrics.avg_progress || 0) + '%']
    ];
    $('#workTasksDashboard').innerHTML = cards.map(card => `<article class="agri-kpi-card"><span><i class="fa-solid ${card[1]}"></i></span><div><strong>${safe(card[2])}</strong><small>${safe(card[0])}</small></div></article>`).join('') + `<article class="agri-kpi-card" style="grid-column:span 2"><span><i class="fa-solid fa-chart-pie"></i></span><div><strong>Theo loáº¡i cÃ´ng viá»‡c</strong><small>${category || 'ChÆ°a cÃ³ dá»¯ liá»‡u'}</small></div></article>`;
  }

  function renderRows(data = {}) {
    const tbody = $('#workTasksRows');
    const rows = data.items || [];
    $('#workTasksTotalCount').textContent = `Tá»•ng sá»‘: ${number(data.total || 0)} cÃ´ng viá»‡c`;
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">ChÆ°a cÃ³ cÃ´ng viá»‡c phÃ¹ há»£p bá»™ lá»c.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(item => {
      const actions = [
        `<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="workTasks.detail" data-id="${item.id}" title="Xem"><i class="fa-solid fa-eye"></i></button>`,
        can('update') ? `<button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="workTasks.edit" data-id="${item.id}" title="Sá»­a"><i class="fa-solid fa-pen"></i></button>` : '',
        can('delete') ? `<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="workTasks.delete" data-id="${item.id}" title="XÃ³a"><i class="fa-solid fa-trash"></i></button>` : ''
      ].filter(Boolean).join(' ');
      return `<tr><td><strong>${safe(item.task_code)}</strong></td><td><div class="fw-semibold">${safe(text(item.title))}</div><small class="text-muted">${safe(text(item.description, '').slice(0, 90))}</small></td><td>${safe(text(item.category_name))}</td><td>${priorityBadge(item)}</td><td>${statusBadge(item)}</td><td>${safe(text(item.assigned_name))}</td><td>${progress(item.progress_percent)}</td><td>${date(item.due_at)}${item.is_overdue ? '<br><span class="badge bg-danger">QuÃ¡ háº¡n</span>' : ''}</td><td class="text-end">${actions}</td></tr>`;
    }).join('');
  }

  function renderPager(data = {}) {
    const host = $('#workTasksPager');
    const totalPages = Number(data.totalPages || 1);
    const page = Number(data.page || state.page || 1);
    state.page = page;
    if (totalPages <= 1) { host.innerHTML = ''; return; }
    const buttons = [`<button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="workTasks.page" data-page="${Math.max(1, page - 1)}" ${page <= 1 ? 'disabled' : ''}>TrÆ°á»›c</button>`];
    for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) buttons.push(`<button class="btn btn-sm ${i === page ? 'btn-primary' : 'btn-outline-secondary'}" type="button" data-platform-action="workTasks.page" data-page="${i}">${i}</button>`);
    buttons.push(`<button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="workTasks.page" data-page="${Math.min(totalPages, page + 1)}" ${page >= totalPages ? 'disabled' : ''}>Sau</button>`);
    host.innerHTML = `<div class="d-flex gap-2 justify-content-end flex-wrap">${buttons.join('')}</div>`;
  }

  function priorityBadge(item) {
    const tones = { URGENT: 'danger', HIGH: 'warning', NORMAL: 'primary', LOW: 'secondary' };
    return `<span class="badge bg-${tones[item.priority_code] || 'secondary'}">${safe(text(item.priority_name))}</span>`;
  }

  function statusBadge(item) {
    const tones = { NEW: 'secondary', ASSIGNED: 'info', IN_PROGRESS: 'warning', WAITING: 'dark', DONE: 'success', CANCELLED: 'secondary' };
    return `<span class="badge bg-${tones[item.status_code] || 'secondary'}">${safe(text(item.status_name))}</span>`;
  }

  function progress(value) {
    const percent = Math.max(0, Math.min(100, Number(value || 0)));
    return `<div class="progress" style="height:8px"><div class="progress-bar bg-success" style="width:${percent}%"></div></div><small>${percent}%</small>`;
  }

  async function openForm(id = null) {
    if (id && !can('update')) return toast('KhÃ´ng cÃ³ quyá»n sá»­a', 'warning');
    if (!id && !can('create')) return toast('KhÃ´ng cÃ³ quyá»n thÃªm', 'warning');
    await catalogs();
    const form = $('#workTaskForm');
    form.reset();
    state.sourceNote = '';
    form.elements.id.value = '';
    form.elements.progress_percent.value = '0';
    $('#workTaskFiles').value = '';
    if (id) {
      const item = await request(API + '/' + id);
      Object.entries(item).forEach(([key, value]) => {
        if (!form.elements[key]) return;
        form.elements[key].value = datetimeLocal(value) ?? value ?? '';
      });
      form.elements.id.value = item.id;
    }
    openModal('workTaskModal');
  }

  async function save(form) {
    const data = Object.fromEntries(new FormData(form).entries());
    const id = Number(data.id || 0);
    delete data.id;
    if (state.sourceNote && !String(data.note || '').includes('SOURCE_REF:')) {
      data.note = [String(data.note || '').trim(), state.sourceNote].filter(Boolean).join('\n');
    }
    const saved = await request(id ? API + '/' + id : API, { method: id ? 'PUT' : 'POST', body: data });
    const files = Array.from($('#workTaskFiles')?.files || []);
    for (const file of files) {
      const body = new FormData();
      body.append('file', file);
      await request(API + '/' + saved.id + '/attachments', { method: 'POST', body });
    }
    closeModal('workTaskModal');
    state.sourceNote = '';
    document.dispatchEvent(new CustomEvent('work-tasks:changed', { detail: { task: saved } }));
    toast('ÄÃ£ lÆ°u cÃ´ng viá»‡c', 'success');
    await load();
  }

  async function openDetail(id) {
    const item = await request(API + '/' + id);
    state.current = item;
    $('#workTaskDetailTitle').textContent = item.title || 'Chi tiáº¿t cÃ´ng viá»‡c';
    $('#workTaskDetailSub').textContent = `${item.task_code || ''} - ${item.status_name || 'ChÆ°a cáº­p nháº­t'}`;
    $('#workTaskDetailBody').innerHTML = detailHtml(item);
    $('#workTaskLogForm').reset();
    $('#workTaskLogForm').classList.toggle('d-none', !can('update'));
    openModal('workTaskDetailModal');
  }

  function detailHtml(item) {
    const attachments = (item.attachments || []).map(file => `<div class="work-task-file"><i class="fa-solid ${fileIcon(file)}"></i><a href="${safe(file.preview_url)}" target="_blank" rel="noopener">${safe(file.original_name)}</a><small>${safe(file.file_kind || '')}</small>${can('delete') ? `<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="workTasks.attachment.delete" data-id="${file.id}"><i class="fa-solid fa-trash"></i></button>` : ''}</div>`).join('') || '<div class="text-muted">ChÆ°a cÃ³ file Ä‘Ã­nh kÃ¨m</div>';
    const logs = (item.logs || []).map(log => `<li><strong>${dateTime(log.created_at)}</strong> - ${safe(text(log.actor_name, 'Há»‡ thá»‘ng'))}<div>${safe(log.content)}</div><small class="text-muted">${safe(text(log.status_name, ''))}${log.progress_percent !== null && log.progress_percent !== undefined ? ' - ' + safe(log.progress_percent) + '%' : ''}</small></li>`).join('') || '<li class="text-muted">ChÆ°a cÃ³ nháº­t kÃ½</li>';
    return `<div class="row g-3"><div class="col-md-8"><dl class="row mb-0"><dt class="col-sm-3">Loáº¡i</dt><dd class="col-sm-9">${safe(text(item.category_name))}</dd><dt class="col-sm-3">Æ¯u tiÃªn</dt><dd class="col-sm-9">${priorityBadge(item)}</dd><dt class="col-sm-3">Tráº¡ng thÃ¡i</dt><dd class="col-sm-9">${statusBadge(item)}</dd><dt class="col-sm-3">Phá»¥ trÃ¡ch</dt><dd class="col-sm-9">${safe(text(item.assigned_name))}</dd><dt class="col-sm-3">Háº¡n</dt><dd class="col-sm-9">${dateTime(item.due_at)} ${item.is_overdue ? '<span class="badge bg-danger">QuÃ¡ háº¡n</span>' : ''}</dd><dt class="col-sm-3">Tiáº¿n Ä‘á»™</dt><dd class="col-sm-9">${progress(item.progress_percent)}</dd><dt class="col-sm-3">MÃ´ táº£</dt><dd class="col-sm-9">${safe(text(item.description))}</dd></dl></div><div class="col-md-4"><h6>File Ä‘Ã­nh kÃ¨m</h6><div class="work-task-files">${attachments}</div></div><div class="col-12"><h6>Nháº­t kÃ½ xá»­ lÃ½</h6><ol class="work-task-log">${logs}</ol></div></div>`;
  }

  async function addLog() {
    if (!state.current?.id) return;
    const form = $('#workTaskLogForm');
    const body = Object.fromEntries(new FormData(form).entries());
    await request(API + '/' + state.current.id + '/logs', { method: 'POST', body });
    toast('ÄÃ£ ghi nháº­t kÃ½', 'success');
    await openDetail(state.current.id);
    await load();
  }

  async function deleteAttachment(fileId) {
    if (!state.current?.id || !fileId) return;
    if (!window.confirm('XÃ³a file Ä‘Ã­nh kÃ¨m nÃ y?')) return;
    await request(API + '/' + state.current.id + '/attachments/' + fileId, { method: 'DELETE' });
    await openDetail(state.current.id);
  }

  async function remove(id) {
    if (!id || !can('delete')) return;
    if (!window.confirm('XÃ³a cÃ´ng viá»‡c nÃ y?')) return;
    await request(API + '/' + id, { method: 'DELETE' });
    toast('ÄÃ£ xÃ³a cÃ´ng viá»‡c', 'success');
    await load();
  }

  async function reset() {
    ['workTasksSearch', 'workTasksCategoryFilter', 'workTasksPriorityFilter', 'workTasksStatusFilter', 'workTasksOverdueFilter', 'workTasksAreaFilter', 'workTasksDateFrom', 'workTasksDateTo'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
    Object.assign(state, { page: 1, search: '', category_id: '', priority_id: '', status_id: '', area_code: '', date_from: '', date_to: '', overdue: '', sort: 'due_at', direction: 'ASC' });
    await load();
  }

  function sortBy(key) {
    if (!key) return;
    if (state.sort === key) state.direction = state.direction === 'ASC' ? 'DESC' : 'ASC';
    else { state.sort = key; state.direction = 'ASC'; }
    state.page = 1;
    return load();
  }

  function exportReport(format) {
    const query = params();
    const url = format === 'pdf' ? API + '/export-pdf' : API + '/export-excel';
    window.open(url + '?' + query.toString(), '_blank', 'noopener');
  }

  async function openPrefilledForm(prefill = {}) {
    if (!can('create')) return toast('KhÃ´ng cÃ³ quyá»n thÃªm', 'warning');
    shell();
    await catalogs();
    const form = $('#workTaskForm');
    if (!form) throw new Error('KhÃ´ng tÃ¬m tháº¥y form cÃ´ng viá»‡c');
    form.reset();
    form.elements.id.value = '';
    form.elements.progress_percent.value = '0';
    $('#workTaskFiles').value = '';
    form.elements.title.value = text(prefill.title, 'CÃ´ng viá»‡c cáº§n xá»­ lÃ½');
    form.elements.description.value = buildDescription(prefill);
    form.elements.assigned_name.value = prefill.assignedName || prefill.assigned_name || '';
    form.elements.area_code.value = prefill.areaCode || prefill.area_code || '';
    form.elements.related_module.value = prefill.relatedModule || prefill.related_module || 'risk_warning';
    form.elements.related_id.value = Number(prefill.relatedId || prefill.related_id || 0) || '';
    state.sourceNote = buildSourceNote(prefill);
    form.elements.note.value = state.sourceNote;
    selectByCode(form.elements.priority_id, priorityCode(prefill.priority || prefill.severity));
    selectByCode(form.elements.status_id, 'NEW');
    selectByCode(form.elements.category_id, prefill.categoryCode || 'other');
    openModal('workTaskModal');
  }

  function sourceFromDataset(dataset = {}) {
    return {
      title: dataset.title,
      description: dataset.description,
      sourceType: dataset.sourceType,
      sourceId: dataset.sourceId,
      sourceModule: dataset.sourceModule,
      sourceTitle: dataset.sourceTitle,
      sourceRef: dataset.sourceRef,
      sourceUrl: dataset.sourceUrl,
      relatedModule: dataset.relatedModule,
      relatedId: dataset.relatedId,
      priority: dataset.priority,
      severity: dataset.severity,
      action: dataset.action,
      citizens: dataset.citizens,
      households: dataset.households,
      categoryCode: dataset.categoryCode,
    };
  }

  function buildDescription(prefill = {}) {
    const lines = [];
    if (prefill.description || prefill.message) lines.push(text(prefill.description || prefill.message, ''));
    if (prefill.action) lines.push('Gá»£i Ã½ xá»­ lÃ½: ' + prefill.action);
    if (prefill.citizens) lines.push('NhÃ¢n kháº©u liÃªn quan: ' + prefill.citizens);
    if (prefill.households) lines.push('Há»™ liÃªn quan: ' + prefill.households);
    if (prefill.sourceUrl) lines.push('Nguá»“n phÃ¡t sinh: ' + prefill.sourceUrl);
    return lines.filter(Boolean).join('\n');
  }

  function buildSourceNote(prefill = {}) {
    const sourceType = metadataValue(prefill.sourceType || prefill.source_type || 'unknown');
    const sourceId = metadataValue(prefill.sourceId || prefill.source_id || '');
    const sourceModule = metadataValue(prefill.sourceModule || prefill.source_module || prefill.relatedModule || prefill.related_module || '');
    const sourceTitle = metadataValue(prefill.sourceTitle || prefill.source_title || prefill.title || '');
    const sourceRef = text(prefill.sourceRef || prefill.source_ref || [sourceType, sourceId].filter(Boolean).join(':'), '');
    return [
      sourceRef ? 'SOURCE_REF:' + sourceRef : '',
      'SOURCE_TYPE:' + sourceType,
      sourceId ? 'SOURCE_ID:' + sourceId : '',
      sourceModule ? 'SOURCE_MODULE:' + sourceModule : '',
      sourceTitle ? 'SOURCE_TITLE:' + sourceTitle : '',
      prefill.sourceUrl ? 'SOURCE_URL:' + prefill.sourceUrl : '',
    ].filter(Boolean).join('\n');
  }

  function metadataValue(value) {
    return String(value ?? '').replace(/\s+/g, ' ').trim();
  }

  function priorityCode(value) {
    value = String(value || '').toLowerCase();
    if (value === 'critical' || value === 'urgent') return 'URGENT';
    if (value === 'high') return 'HIGH';
    if (value === 'low') return 'LOW';
    return 'NORMAL';
  }

  function selectByCode(select, code) {
    if (!select || !code) return;
    const option = Array.from(select.options).find(item => String(item.dataset.code || '').toUpperCase() === String(code).toUpperCase());
    if (option) select.value = option.value;
  }

  async function findBySource(sourceRef) {
    sourceRef = String(sourceRef || '').trim();
    if (!sourceRef) return null;
    const query = new URLSearchParams({ source_ref: sourceRef, pageSize: 1, sort: 'task_code', direction: 'DESC' });
    const data = await request(API + '?' + query.toString(), { cacheTtl: 0 });
    return (data.items || [])[0] || null;
  }

  async function openSourceTask(sourceRef) {
    const task = await findBySource(sourceRef);
    if (!task) return toast('ChÆ°a tÃ¬m tháº¥y cÃ´ng viá»‡c tá»« nguá»“n nÃ y', 'warning');
    await openDetail(Number(task.id || 0));
  }

  function datetimeLocal(value) {
    if (!value || !/^\d{4}-\d{2}-\d{2}/.test(String(value))) return null;
    return String(value).replace(' ', 'T').slice(0, 16);
  }

  function fileIcon(file) {
    const kind = String(file.file_kind || '').toUpperCase();
    if (kind === 'IMAGE') return 'fa-image';
    if (kind === 'VIDEO') return 'fa-video';
    if (kind === 'PDF') return 'fa-file-pdf';
    return 'fa-file-lines';
  }

  window.loadWorkTasks = load;
  window.WorkTaskQuickCreate = { open: openPrefilledForm, findBySource, openTaskBySource: openSourceTask };
})();
