ï»¿(() => {
  'use strict';

  const state = { tab: 'nvqs', page: 1, pageSize: 20, sort: '', direction: 'ASC', catalogs: null, metric: '' };
  const API = '/api/defense-security';
  const $ = (selector, root = document) => root.querySelector(selector);
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[c]));
  const fmt = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
  const token = () => (window.App && App.token) || localStorage.getItem(tenantStorageKey('token')) || '';
  const csrf = () => (window.App && App.csrfToken) || localStorage.getItem(tenantStorageKey('csrf')) || '';

  document.addEventListener('DOMContentLoaded', boot);
  document.addEventListener('tenant:screen-change', event => { if (event.detail?.screen === 'defenseSecurity') loadAll(); });

  function boot() {
    registerPlatformModule();
    registerActions();
    ensureModal();
    bindForm();
    bindCitizenSearch();
    $('#defenseYearFilter') && ($('#defenseYearFilter').value = new Date().getFullYear());
    $('#defensePageSize')?.addEventListener('change', () => { state.page = 1; loadList(); });
    if (isActive()) loadAll();
  }

  function registerPlatformModule() {
    const platform = window.TenantAppPlatform;
    if (!platform?.modules || platform.modules.has?.('defenseSecurity')) return;
    platform.modules.upsert({ moduleKey: 'defenseSecurity', screenId: 'defenseSecurity', path: '/defense-security', label: 'Quá»‘c phÃ²ng - An ninh', mobileLabel: 'QP-AN', icon: 'fa-shield-halved', permissionScope: 'defense_security' });
    platform.routes?.upsert?.({ path: '/defense-security', moduleKey: 'defenseSecurity', screenId: 'defenseSecurity', action: 'list' });
    platform.menus?.upsert?.({ key: 'defense', label: 'Quá»‘c phÃ²ng - An ninh', icon: 'fa-shield-halved', items: ['defenseSecurity'] });
    platform.menuRenderer?.renderAll?.();
  }

  function registerActions() {
    const actions = window.TenantAppPlatform?.actions;
    if (!actions?.register) return;
    actions.register('defenseSecurity.search', () => { state.page = 1; state.metric = ''; loadAll(); });
    actions.register('defenseSecurity.reset', resetFilters);
    actions.register('defenseSecurity.tab', ctx => switchTab(ctx.dataset.tab));
    actions.register('defenseSecurity.metric', ctx => { state.metric = ctx.dataset.metric || ''; state.page = 1; switchTab(ctx.dataset.tab || 'nvqs', false); loadList(); });
    actions.register('defenseSecurity.create', () => openForm());
    actions.register('defenseSecurity.createForCitizen', ctx => openForm({ citizen_id: ctx.dataset.citizenId, full_name: ctx.dataset.label || '', address: ctx.dataset.address || '', recruitment_year: $('#defenseYearFilter')?.value || new Date().getFullYear() }));
    actions.register('defenseSecurity.edit', ctx => edit(ctx.dataset.id));
    actions.register('defenseSecurity.delete', ctx => remove(ctx.dataset.id));
    actions.register('defenseSecurity.page', ctx => page(ctx.dataset.direction));
    actions.register('defenseSecurity.selectCitizen', ctx => selectCitizen(ctx.target?.closest('[data-citizen-id]') || ctx.target));
    actions.register('defenseSecurity.clearCitizen', clearCitizenSelection);
    actions.register('defenseSecurity.export', ctx => downloadReport(ctx.dataset.format || 'excel'));
    actions.register('defenseSecurity.print', () => openReport(true));
  }

  function isActive() { return $('#defenseSecurityScreen')?.classList.contains('active'); }

  async function api(url, options = {}) {
    const headers = { Accept: 'application/json' };
    if (options.body) headers['Content-Type'] = 'application/json';
    if (token()) headers.Authorization = 'Bearer ' + token();
    if (csrf()) headers['X-CSRF-Token'] = csrf();
    const res = await fetch(url, { method: options.method || 'GET', headers, body: options.body ? JSON.stringify(options.body) : undefined, cache: 'no-store' });
    const json = await res.json().catch(() => null);
    if (!res.ok || !json?.ok) throw new Error(json?.error?.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c dá»¯ liá»‡u Quá»‘c phÃ²ng - An ninh');
    return unwrap(json);
  }

  function unwrap(payload) {
    if (payload && payload.ok !== undefined && payload.data !== undefined) return unwrap(payload.data);
    return payload || {};
  }

  async function loadAll() {
    if (!$('#defenseSecurityScreen')) return;
    try {
      if (!state.catalogs) await loadCatalogs();
      await Promise.all([loadDashboard(), loadList()]);
    } catch (error) { toast(error.message, 'danger'); }
  }

  async function loadCatalogs() {
    state.catalogs = await api(API + '/catalogs');
    fillStatusFilter();
  }

  function fillStatusFilter() {
    const select = $('#defenseStatusFilter');
    if (!select || !state.catalogs) return;
    const items = state.tab === 'nvqs' ? state.catalogs.nvqs_eligibility_statuses : (state.tab === 'militia' ? state.catalogs.participation_statuses : state.catalogs.security_statuses);
    select.innerHTML = '<option value="">Táº¥t cáº£</option>' + (items || []).map(item => '<option value="' + esc(item.value) + '">' + esc(item.label) + '</option>').join('');
  }

  async function loadDashboard() {
    const data = await api(API + '/dashboard?' + baseQuery(false));
    const n = data.nvqs || {}, m = data.militia || {}, s = data.security_force || {};
    const cards = [
      ['Sáº¯p Ä‘áº¿n tuá»•i Ä‘Äƒng kÃ½ NVQS', n.warning_age, 'fa-user-clock', 'nvqs', 'warning_age'],
      ['Äáº¿n tuá»•i Ä‘Äƒng kÃ½ NVQS', n.registration_age, 'fa-id-card', 'nvqs', 'registration_age'],
      ['Theo dÃµi tuyá»ƒn quÃ¢n', n.tracking_age, 'fa-users-viewfinder', 'nvqs', 'tracking_age'],
      ['ChÆ°a Ä‘Äƒng kÃ½ NVQS', n.unregistered, 'fa-triangle-exclamation', 'nvqs', 'unregistered'],
      ['ChÆ°a cáº­p nháº­t sÆ¡ tuyá»ƒn', n.preliminary_missing, 'fa-clipboard-question', 'nvqs', ''],
      ['ChÆ°a cáº­p nháº­t khÃ¡m tuyá»ƒn', n.medical_missing, 'fa-stethoscope', 'nvqs', ''],
      ['ÄÃ£ nháº­p ngÅ©', n.enlisted, 'fa-person-military-rifle', 'nvqs', 'enlisted'],
      ['Äang táº¡i ngÅ©', n.active_service, 'fa-shield', 'nvqs', 'active_service'],
      ['Tá»•ng dÃ¢n quÃ¢n', m.total, 'fa-people-group', 'militia', ''],
      ['DÃ¢n quÃ¢n Ä‘ang tham gia', m.active, 'fa-user-check', 'militia', ''],
      ['Tá»•ng ANTT cÆ¡ sá»Ÿ', s.total, 'fa-shield-halved', 'security_force', ''],
      ['ANTT Ä‘ang hoáº¡t Ä‘á»™ng', s.active, 'fa-user-shield', 'security_force', '']
    ];
    $('#defenseSecurityDashboard').innerHTML = cards.map(([label, value, icon, tab, metric]) => '<article class="content-card rural-water-kpi" role="button" tabindex="0" data-platform-action="defenseSecurity.metric" data-tab="' + esc(tab) + '" data-metric="' + esc(metric) + '"><i class="fa-solid ' + icon + '"></i><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong></article>').join('');
  }

  async function loadList() {
    const endpoint = state.tab === 'nvqs' ? '/nvqs' : (state.tab === 'militia' ? '/militia' : '/security-force');
    const data = await api(API + endpoint + '?' + baseQuery(true));
    $('#defenseTotalCount').textContent = 'Tá»•ng sá»‘: ' + fmt(data.total || 0) + ' há»“ sÆ¡';
    $('#defenseListTitle').textContent = state.tab === 'nvqs' ? 'Danh sÃ¡ch nghÄ©a vá»¥ quÃ¢n sá»±' : (state.tab === 'militia' ? 'Danh sÃ¡ch dÃ¢n quÃ¢n tá»± vá»‡' : 'Danh sÃ¡ch ANTT cÆ¡ sá»Ÿ');
    renderHead();
    $('#defenseRows').innerHTML = (data.items || []).map((row, index) => rowHtml(row, index, data)).join('') || '<tr><td colspan="12" class="text-center text-muted py-4">ChÆ°a cÃ³ há»“ sÆ¡ phÃ¹ há»£p</td></tr>';
    renderPager(data);
  }

  function renderHead() {
    const nvqs = ['STT','MÃ£ NK','Há» tÃªn','NgÃ y sinh','MÃ£ há»™','NÄƒm','ÄÄƒng kÃ½','SÆ¡ tuyá»ƒn','KhÃ¡m tuyá»ƒn','Äiá»u kiá»‡n','Tuyá»ƒn chá»n','Thao tÃ¡c'];
    const militia = ['STT','MÃ£ NK','Há» tÃªn','MÃ£ há»™','Loáº¡i','Chá»©c vá»¥','ÄÆ¡n vá»‹/tá»•','NgÃ y tham gia','Huáº¥n luyá»‡n','Tráº¡ng thÃ¡i','Thao tÃ¡c'];
    const security = ['STT','MÃ£ NK','Há» tÃªn','MÃ£ há»™','Tá»• ANTT','Chá»©c vá»¥','Khu vá»±c','NgÃ y tham gia','Tráº¡ng thÃ¡i','Thao tÃ¡c'];
    const headers = state.tab === 'nvqs' ? nvqs : (state.tab === 'militia' ? militia : security);
    $('#defenseTableHead').innerHTML = '<tr>' + headers.map(h => '<th>' + esc(h) + '</th>').join('') + '</tr>';
  }

  function rowHtml(row, index, data) {
    const stt = index + 1 + ((data.page || 1) - 1) * (data.pageSize || 20);
    const actions = '<td class="text-end"><button class="btn btn-sm btn-outline-primary" data-platform-action="defenseSecurity.edit" data-id="' + esc(row.id) + '" title="Sá»­a"><i class="fa-solid fa-pen"></i></button> <button class="btn btn-sm btn-outline-danger" data-platform-action="defenseSecurity.delete" data-id="' + esc(row.id) + '" title="XÃ³a"><i class="fa-solid fa-trash"></i></button></td>';
    if (state.tab === 'nvqs') return '<tr><td>' + stt + '</td><td>' + esc(row.citizen_code) + '</td><td>' + esc(row.full_name) + '</td><td>' + esc(formatDate(row.date_of_birth)) + '</td><td>' + esc(row.household_code) + '</td><td>' + esc(row.recruitment_year) + '</td><td>' + esc(row.registered_status_label) + '</td><td>' + esc(row.preliminary_status_label) + '</td><td>' + esc(row.medical_exam_status_label) + '</td><td>' + esc(row.eligibility_status_label) + '</td><td>' + esc(row.selection_status_label) + '</td>' + actions + '</tr>';
    if (state.tab === 'militia') return '<tr><td>' + stt + '</td><td>' + esc(row.citizen_code) + '</td><td>' + esc(row.full_name) + '</td><td>' + esc(row.household_code) + '</td><td>' + esc(row.militia_type_label) + '</td><td>' + esc(row.position_name) + '</td><td>' + esc(row.unit_name) + '</td><td>' + esc(formatDate(row.joined_date)) + '</td><td>' + esc(row.training_result || row.training_name) + '</td><td>' + esc(row.participation_status_label) + '</td>' + actions + '</tr>';
    return '<tr><td>' + stt + '</td><td>' + esc(row.citizen_code) + '</td><td>' + esc(row.full_name) + '</td><td>' + esc(row.household_code) + '</td><td>' + esc(row.team_name) + '</td><td>' + esc(row.position_label) + '</td><td>' + esc(row.area_in_charge) + '</td><td>' + esc(formatDate(row.joined_date)) + '</td><td>' + esc(row.participation_status_label) + '</td>' + actions + '</tr>';
  }

  function baseQuery(withPaging) {
    const params = new URLSearchParams();
    const year = $('#defenseYearFilter')?.value || new Date().getFullYear();
    const search = $('#defenseSearch')?.value?.trim();
    const status = $('#defenseStatusFilter')?.value;
    if (year) params.set('year', year);
    if (search) params.set('search', search);
    if (state.metric) params.set('metric', state.metric);
    if (status) params.set(state.tab === 'nvqs' ? 'eligibility_status' : 'participation_status', status);
    if (withPaging) { params.set('page', state.page); params.set('pageSize', $('#defensePageSize')?.value || state.pageSize); if (state.sort) params.set('sort', state.sort); params.set('direction', state.direction); }
    return params.toString();
  }

  function switchTab(tab, reload = true) {
    state.tab = tab || 'nvqs';
    state.page = 1;
    state.metric = '';
    document.querySelectorAll('[data-platform-action="defenseSecurity.tab"]').forEach(btn => btn.classList.toggle('active', btn.dataset.tab === state.tab));
    fillStatusFilter();
    if (reload) loadList();
  }

  function resetFilters() {
    if ($('#defenseYearFilter')) $('#defenseYearFilter').value = new Date().getFullYear();
    if ($('#defenseSearch')) $('#defenseSearch').value = '';
    if ($('#defenseStatusFilter')) $('#defenseStatusFilter').value = '';
    state.metric = '';
    state.page = 1;
    loadAll();
  }

  function ensureModal() {
    if ($('#defenseRecordModal')) return;
    document.body.insertAdjacentHTML('beforeend', '<div class="modal fade" id="defenseRecordModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" id="defenseRecordForm" novalidate><div class="modal-header"><h5 class="modal-title" id="defenseModalTitle">Há»“ sÆ¡ Quá»‘c phÃ²ng - An ninh</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ÄÃ³ng"></button></div><div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="citizen_id"><div class="row g-3"><div class="col-12 position-relative"><label class="form-label" for="defenseCitizenSearch">NhÃ¢n kháº©u</label><input id="defenseCitizenSearch" class="form-control" autocomplete="off" placeholder="Nháº­p há» tÃªn, mÃ£ nhÃ¢n kháº©u, mÃ£ há»™ hoáº·c nÄƒm sinh" required><div id="defenseCitizenSuggestions" class="list-group position-absolute w-100 shadow d-none" style="z-index:1060;max-height:260px;overflow:auto"></div><div id="defenseCitizenSelected" class="form-text"></div></div><div id="defenseDynamicFields" class="row g-3 m-0 p-0"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Há»§y</button><button type="submit" class="btn btn-primary">LÆ°u</button></div></form></div></div>');
  }

  function dynamicFields(record = {}) {
    if (state.tab === 'nvqs') return [
      input('recruitment_year', 'NÄƒm tuyá»ƒn quÃ¢n', 'number', record.recruitment_year || $('#defenseYearFilter')?.value || new Date().getFullYear(), 'col-md-3'),
      select('registered_status', 'ÄÃ£ Ä‘Äƒng kÃ½ NVQS', state.catalogs.yes_no, record.registered_status || 'NO'),
      input('registration_date', 'NgÃ y Ä‘Äƒng kÃ½', 'text', formatDate(record.registration_date), 'col-md-3 defense-date'),
      select('preliminary_status', 'SÆ¡ tuyá»ƒn', state.catalogs.nvqs_preliminary_statuses, record.preliminary_status || 'NOT_UPDATED'),
      input('preliminary_date', 'NgÃ y sÆ¡ tuyá»ƒn', 'text', formatDate(record.preliminary_date), 'col-md-3 defense-date'),
      select('medical_exam_status', 'KhÃ¡m tuyá»ƒn', state.catalogs.nvqs_medical_statuses, record.medical_exam_status || 'NOT_UPDATED'),
      input('medical_exam_date', 'NgÃ y khÃ¡m', 'text', formatDate(record.medical_exam_date), 'col-md-3 defense-date'),
      input('health_classification', 'PhÃ¢n loáº¡i sá»©c khá»e', 'text', record.health_classification || '', 'col-md-3'),
      select('eligibility_status', 'TÃ¬nh tráº¡ng Ä‘iá»u kiá»‡n', state.catalogs.nvqs_eligibility_statuses, record.eligibility_status || 'UNKNOWN'),
      input('deferment_reason', 'LÃ½ do táº¡m hoÃ£n', 'text', record.deferment_reason || '', 'col-md-4'),
      input('exemption_reason', 'LÃ½ do miá»…n', 'text', record.exemption_reason || '', 'col-md-4'),
      select('selection_status', 'Tuyá»ƒn chá»n', state.catalogs.nvqs_selection_statuses, record.selection_status || 'NOT_SELECTED'),
      checkbox('order_received', 'ÄÃ£ nháº­n lá»‡nh nháº­p ngÅ©', record.order_received), checkbox('active_service', 'Äang táº¡i ngÅ©', record.active_service), checkbox('completed_service', 'ÄÃ£ hoÃ n thÃ nh NVQS', record.completed_service),
      input('enlistment_date', 'NgÃ y nháº­p ngÅ©', 'text', formatDate(record.enlistment_date), 'col-md-3 defense-date'), input('enlistment_unit', 'ÄÆ¡n vá»‹ nháº­p ngÅ©', 'text', record.enlistment_unit || '', 'col-md-5'), input('discharge_date', 'NgÃ y xuáº¥t ngÅ©', 'text', formatDate(record.discharge_date), 'col-md-3 defense-date'), input('discharge_unit', 'ÄÆ¡n vá»‹ trÆ°á»›c khi xuáº¥t ngÅ©', 'text', record.discharge_unit || '', 'col-md-5'), textarea(record.note || '')
    ].join('');
    if (state.tab === 'militia') return [select('militia_type','Loáº¡i/lá»±c lÆ°á»£ng dÃ¢n quÃ¢n',state.catalogs.militia_types,record.militia_type||'CORE'), input('position_name','Chá»©c vá»¥','text',record.position_name||'','col-md-4'), input('unit_name','ÄÆ¡n vá»‹/tá»•','text',record.unit_name||'','col-md-4'), input('joined_date','NgÃ y tham gia','text',formatDate(record.joined_date),'col-md-3 defense-date'), input('ended_date','NgÃ y káº¿t thÃºc','text',formatDate(record.ended_date),'col-md-3 defense-date'), input('training_name','Huáº¥n luyá»‡n','text',record.training_name||'','col-md-4'), input('training_date','NgÃ y huáº¥n luyá»‡n','text',formatDate(record.training_date),'col-md-3 defense-date'), input('training_result','Káº¿t quáº£ huáº¥n luyá»‡n','text',record.training_result||'','col-md-4'), select('participation_status','Tráº¡ng thÃ¡i',state.catalogs.participation_statuses,record.participation_status||'ACTIVE'), input('reason','LÃ½ do','text',record.reason||'','col-md-4'), textarea(record.note||'')].join('');
    return [input('team_name','Tá»• ANTT','text',record.team_name||'','col-md-4'), select('position_code','Chá»©c vá»¥',state.catalogs.security_positions,record.position_code||'MEMBER'), input('area_in_charge','Khu vá»±c phá»¥ trÃ¡ch','text',record.area_in_charge||'','col-md-4'), input('joined_date','NgÃ y tham gia','text',formatDate(record.joined_date),'col-md-3 defense-date'), input('ended_date','NgÃ y káº¿t thÃºc','text',formatDate(record.ended_date),'col-md-3 defense-date'), select('participation_status','Tráº¡ng thÃ¡i',state.catalogs.security_statuses,record.participation_status||'ACTIVE'), input('reason','LÃ½ do','text',record.reason||'','col-md-4'), textarea(record.note||'')].join('');
  }

  function input(name, label, type, value, cls = 'col-md-3') { return '<div class="' + cls + '"><label class="form-label">' + esc(label) + '</label><input name="' + esc(name) + '" type="' + type + '" inputmode="' + (cls.includes('defense-date') ? 'numeric' : '') + '" placeholder="' + (cls.includes('defense-date') ? 'dd/mm/yyyy' : '') + '" class="form-control" value="' + esc(value) + '"></div>'; }
  function select(name, label, items, value) { return '<div class="col-md-4"><label class="form-label">' + esc(label) + '</label><select name="' + esc(name) + '" class="form-select">' + (items || []).map(item => '<option value="' + esc(item.value) + '" ' + (item.value === value ? 'selected' : '') + '>' + esc(item.label) + '</option>').join('') + '</select></div>'; }
  function checkbox(name, label, checked) { return '<div class="col-md-3 d-flex align-items-end"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="' + esc(name) + '" value="1" ' + (checked ? 'checked' : '') + '> <span class="form-check-label">' + esc(label) + '</span></label></div>'; }
  function textarea(value) { return '<div class="col-12"><label class="form-label">Ghi chÃº</label><textarea name="note" class="form-control" rows="3">' + esc(value) + '</textarea></div>'; }

  function openForm(record = {}) {
    ensureModal();
    const form = $('#defenseRecordForm');
    form.reset();
    form.elements.id.value = record.id || '';
    form.elements.citizen_id.value = record.citizen_id || '';
    $('#defenseModalTitle').textContent = state.tab === 'nvqs' ? 'Há»“ sÆ¡ nghÄ©a vá»¥ quÃ¢n sá»±' : (state.tab === 'militia' ? 'Há»“ sÆ¡ dÃ¢n quÃ¢n tá»± vá»‡' : 'Há»“ sÆ¡ ANTT cÆ¡ sá»Ÿ');
    $('#defenseDynamicFields').innerHTML = dynamicFields(record);
    const label = record.full_name ? [record.citizen_code, record.full_name, record.household_code].filter(Boolean).join(' - ') : '';
    $('#defenseCitizenSearch').value = label;
    setSelectedCitizenText(label, record.address || '');
    bootstrap.Modal.getOrCreateInstance($('#defenseRecordModal')).show();
  }

  async function edit(id) {
    if (!id) return;
    const endpoint = state.tab === 'nvqs' ? '/nvqs/' : (state.tab === 'militia' ? '/militia/' : '/security-force/');
    try { openForm(await api(API + endpoint + encodeURIComponent(id))); } catch (error) { toast(error.message, 'danger'); }
  }

  function bindForm() {
    $('#defenseRecordForm')?.addEventListener('submit', async event => {
      event.preventDefault();
      const form = event.currentTarget;
      const data = Object.fromEntries(new FormData(form).entries());
      if (!data.citizen_id) { toast('Vui lÃ²ng chá»n nhÃ¢n kháº©u tá»« danh sÃ¡ch.', 'warning'); $('#defenseCitizenSearch')?.focus(); return; }
      try {
        normalizeDates(data);
        ['order_received','active_service','completed_service'].forEach(key => { if (!data[key]) data[key] = 0; });
        const id = data.id; delete data.id;
        const endpoint = state.tab === 'nvqs' ? '/nvqs' : (state.tab === 'militia' ? '/militia' : '/security-force');
        await api(API + endpoint + (id ? '/' + encodeURIComponent(id) : ''), { method: id ? 'PUT' : 'POST', body: data });
        bootstrap.Modal.getOrCreateInstance($('#defenseRecordModal')).hide();
        await loadAll();
        toast('ÄÃ£ lÆ°u há»“ sÆ¡ Quá»‘c phÃ²ng - An ninh');
      } catch (error) { toast(error.message, 'danger'); }
    });
  }

  function bindCitizenSearch() {
    let timer = null;
    document.addEventListener('input', event => {
      if (event.target?.id !== 'defenseCitizenSearch') return;
      clearTimeout(timer);
      $('#defenseRecordForm').elements.citizen_id.value = '';
      setSelectedCitizenText('', '');
      timer = setTimeout(() => searchCitizens(event.target.value), 250);
    });
  }

  async function searchCitizens(value) {
    const box = $('#defenseCitizenSuggestions');
    const q = String(value || '').trim();
    if (!box) return;
    if (q.length < 2) { box.innerHTML = ''; box.classList.add('d-none'); return; }
    try {
      const data = await api(API + '/citizen-search?q=' + encodeURIComponent(q));
      const items = data.items || [];
      if (!items.length) { box.innerHTML = '<div class="list-group-item text-muted">KhÃ´ng tÃ¬m tháº¥y nhÃ¢n kháº©u phÃ¹ há»£p</div>'; box.classList.remove('d-none'); return; }
      box.innerHTML = items.map(row => {
        const label = [row.citizen_code, row.full_name, row.household_code].filter(Boolean).join(' - ');
        const detail = [formatDate(row.date_of_birth), row.gender, row.address || row.area_code].filter(Boolean).join(' - ');
        return '<button type="button" class="list-group-item list-group-item-action" data-platform-action="defenseSecurity.selectCitizen" data-citizen-id="' + esc(row.id) + '" data-label="' + esc(label) + '" data-detail="' + esc(detail) + '"><strong>' + esc(label) + '</strong>' + (detail ? '<br><small>' + esc(detail) + '</small>' : '') + '</button>';
      }).join('');
      box.classList.remove('d-none');
    } catch (error) { box.innerHTML = '<div class="list-group-item text-danger">' + esc(error.message) + '</div>'; box.classList.remove('d-none'); }
  }

  function selectCitizen(button) {
    if (!button?.dataset?.citizenId) return;
    $('#defenseRecordForm').elements.citizen_id.value = button.dataset.citizenId;
    $('#defenseCitizenSearch').value = button.dataset.label || '';
    setSelectedCitizenText(button.dataset.label || '', button.dataset.detail || '');
    $('#defenseCitizenSuggestions')?.classList.add('d-none');
  }

  function clearCitizenSelection() {
    const form = $('#defenseRecordForm');
    if (form) form.elements.citizen_id.value = '';
    if ($('#defenseCitizenSearch')) $('#defenseCitizenSearch').value = '';
    setSelectedCitizenText('', '');
    $('#defenseCitizenSuggestions')?.classList.add('d-none');
    $('#defenseCitizenSearch')?.focus();
  }

  function setSelectedCitizenText(label, detail) {
    const selected = $('#defenseCitizenSelected');
    if (!selected) return;
    selected.innerHTML = label ? '<span>' + esc([label, detail].filter(Boolean).join(' - ')) + '</span> <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-platform-action="defenseSecurity.clearCitizen" aria-label="Bá» chá»n nhÃ¢n kháº©u">&times;</button>' : '';
  }

  async function remove(id) {
    if (!id || !confirm('XÃ³a há»“ sÆ¡ nÃ y?')) return;
    const endpoint = state.tab === 'nvqs' ? '/nvqs/' : (state.tab === 'militia' ? '/militia/' : '/security-force/');
    try { await api(API + endpoint + encodeURIComponent(id), { method: 'DELETE' }); await loadAll(); toast('ÄÃ£ xÃ³a há»“ sÆ¡'); } catch (error) { toast(error.message, 'danger'); }
  }

  function page(direction) {
    const totalPages = Number($('#defensePager')?.dataset.totalPages || 1);
    state.page = direction === 'prev' ? Math.max(1, state.page - 1) : Math.min(totalPages, state.page + 1);
    loadList();
  }

  function renderPager(data) {
    const host = $('#defensePager');
    if (!host) return;
    const totalPages = Number(data.totalPages || 1);
    host.dataset.totalPages = String(totalPages);
    host.innerHTML = '<button class="btn btn-sm btn-outline-secondary" data-platform-action="defenseSecurity.page" data-direction="prev" ' + (state.page <= 1 ? 'disabled' : '') + '>TrÆ°á»›c</button><span>Trang ' + fmt(state.page) + '/' + fmt(totalPages) + '</span><button class="btn btn-sm btn-outline-secondary" data-platform-action="defenseSecurity.page" data-direction="next" ' + (state.page >= totalPages ? 'disabled' : '') + '>Sau</button>';
  }

  function normalizeDates(data) {
    Object.keys(data).forEach(key => { if (key.endsWith('_date')) data[key] = toIsoDate(data[key]); });
  }

  function toIsoDate(value) {
    value = String(value || '').trim();
    if (!value) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
    const m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(value);
    if (!m) throw new Error('NgÃ y pháº£i theo Ä‘á»‹nh dáº¡ng dd/mm/yyyy.');
    const day = Number(m[1]), month = Number(m[2]), year = Number(m[3]);
    const d = new Date(Date.UTC(year, month - 1, day));
    if (d.getUTCFullYear() !== year || d.getUTCMonth() + 1 !== month || d.getUTCDate() !== day) throw new Error('NgÃ y khÃ´ng há»£p lá»‡.');
    return String(year).padStart(4, '0') + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
  }

  function formatDate(value) {
    value = String(value || '').slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return value || '';
    const [y, m, d] = value.split('-');
    return d + '/' + m + '/' + y;
  }

  function currentReportType() {
    if (state.tab === 'militia') return 'defense-security-militia';
    if (state.tab === 'security_force') return 'defense-security-antt';
    return 'defense-security-nvqs';
  }

  function downloadReport(format) {
    const endpoint = format === 'pdf' ? '/api/reports/export-pdf' : '/api/reports/export-excel';
    window.open(endpoint + '?type=' + encodeURIComponent(currentReportType()) + '&' + baseQuery(false), '_blank');
  }

  function openReport() {
    const navigate = window.TenantAppNavigationController?.navigate || window.TenantAppPlatform?.navigation?.navigate;
    if (typeof navigate === 'function') navigate.call(window.TenantAppNavigationController || window.TenantAppPlatform.navigation, 'reports');
    setTimeout(() => { const select = $('#reportTypeSelect'); if (select) { select.value = currentReportType(); window.loadReport?.(); } }, 150);
  }

  function toast(message, variant = 'success') {
    if (typeof window.showToast === 'function') window.showToast(message, variant);
    else console[variant === 'danger' ? 'error' : 'log'](message);
  }
})();
