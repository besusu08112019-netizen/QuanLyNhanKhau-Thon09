(() => {
  'use strict';

  const state = { page: 1, pageSize: 20, sort: 'household_code', direction: 'ASC', catalogs: null, metric: '' };
  const $ = (selector, root = document) => root.querySelector(selector);
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[c]));
  const fmt = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
  const pct = value => new Intl.NumberFormat('vi-VN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0)) + '%';
  const token = () => (window.App && App.token) || localStorage.getItem(tenantStorageKey('token')) || '';
  const csrf = () => (window.App && App.csrfToken) || localStorage.getItem(tenantStorageKey('csrf')) || '';

  document.addEventListener('DOMContentLoaded', boot);
  document.addEventListener('tenant:screen-change', event => {
    if (event.detail?.screen === 'ruralCleanWater') loadAll();
  });

  function boot() {
    registerActions();
    bindForm();
    bindHouseholdSearch();
    if (isActive()) loadAll();
  }

  function isActive() {
    return document.getElementById('ruralCleanWaterScreen')?.classList.contains('active');
  }

  function registerActions() {
    const actions = window.TenantAppPlatform && window.TenantAppPlatform.actions;
    if (!actions || typeof actions.register !== 'function') return;
    actions.register('ruralCleanWater.search', () => { state.page = 1; state.metric = ''; loadAll(); });
    actions.register('ruralCleanWater.reset', resetFilters);
    actions.register('ruralCleanWater.create', () => openCreate());
    actions.register('ruralCleanWater.edit', ctx => edit(ctx.dataset.id, ctx.dataset.householdId, ctx.dataset.label, ctx.dataset.address));
    actions.register('ruralCleanWater.delete', ctx => remove(ctx.dataset.id));
    actions.register('ruralCleanWater.sort', ctx => sort(ctx.dataset.sort));
    actions.register('ruralCleanWater.selectHousehold', ctx => selectHousehold(ctx.target));
    actions.register('ruralCleanWater.page', ctx => page(ctx.dataset.direction));
    actions.register('ruralCleanWater.report', () => openReport('rural-clean-water'));
    actions.register('ruralCleanWater.metric', ctx => applyMetric(ctx.dataset.metric));
  }

  async function api(url, options = {}) {
    if (typeof window.api === 'function' && !options.raw) {
      const payload = await window.api(url, options);
      return payload?.data ?? payload;
    }
    const headers = { Accept: 'application/json' };
    if (options.body) headers['Content-Type'] = 'application/json';
    if (token()) headers.Authorization = 'Bearer ' + token();
    if (csrf()) headers['X-CSRF-Token'] = csrf();
    const res = await fetch(url, { method: options.method || 'GET', headers, body: options.body ? JSON.stringify(options.body) : undefined, cache: 'no-store' });
    const json = await res.json().catch(() => null);
    if (!res.ok || !json?.ok) throw new Error(json?.error?.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c dá»¯ liá»‡u nÆ°á»›c sáº¡ch');
    return json.data?.data ?? json.data ?? {};
  }

  async function loadAll() {
    if (!document.getElementById('ruralCleanWaterScreen')) return;
    try {
      if (!state.catalogs) await loadCatalogs();
      await Promise.all([loadDashboard(), loadList()]);
    } catch (error) {
      toast(error.message, 'danger');
    }
  }

  async function loadCatalogs() {
    state.catalogs = await api('/api/rural-clean-water/catalogs');
    fillSelect('#ruralWaterTypeFilter', state.catalogs.connection_types, 'Táº¥t cáº£');
    fillSelect('#ruralWaterSupplyFormFilter', state.catalogs.supply_forms, 'Táº¥t cáº£');
    fillSelect('#ruralWaterCleanStatusFilter', state.catalogs.clean_water_statuses, 'Táº¥t cáº£');
    fillSelect('#ruralWaterHygienicFilter', state.catalogs.hygienic_water_statuses, 'Táº¥t cáº£');
    fillSelect('#ruralWaterStatusFilter', state.catalogs.statuses, 'Táº¥t cáº£ tráº¡ng thÃ¡i');
    fillSelect('#ruralWaterConnectionType', state.catalogs.connection_types);
    fillSelect('#ruralWaterSupplyForm', state.catalogs.supply_forms);
    fillSelect('#ruralWaterCleanStatus', state.catalogs.clean_water_statuses);
    fillSelect('#ruralWaterHygienicStatus', state.catalogs.hygienic_water_statuses);
    fillSelect('#ruralWaterMeterStatus', state.catalogs.meter_statuses);
    fillSelect('#ruralWaterVerificationBasis', state.catalogs.verification_basis);
    fillSelect('#ruralWaterStatus', state.catalogs.statuses);
  }

  async function loadDashboard() {
    const data = await api('/api/rural-clean-water/dashboard?' + query(false));
    const m = data.metrics || {};
    const cards = [
      ['Tá»•ng sá»‘ há»™', m.total_households, 'fa-house', ''],
      ['NÆ°á»›c há»£p vá»‡ sinh', m.hygienic_water_households, 'fa-droplet', 'hygienic'],
      ['Äáº¡t quy chuáº©n', m.clean_water_households, 'fa-circle-check', 'clean'],
      ['Cáº¥p nÆ°á»›c táº­p trung', m.centralized_water_households, 'fa-faucet-drip', 'centralized'],
      ['Quy mÃ´ há»™ gia Ä‘Ã¬nh', m.household_scale_water_households, 'fa-house-water', 'household_scale'],
      ['KhÃ´ng Ä‘áº¡t quy chuáº©n', m.non_compliant_households, 'fa-triangle-exclamation', 'non_compliant'],
      ['ChÆ°a xÃ¡c Ä‘á»‹nh', m.unknown_households, 'fa-circle-question', 'unknown'],
      ['Tá»· lá»‡ Ä‘áº¡t quy chuáº©n', pct(m.clean_water_rate), 'fa-percent', 'clean']
    ];
    const warning = data.warning ? '<button type="button" class="alert alert-warning w-100 text-start mb-3" data-platform-action="ruralCleanWater.metric" data-metric="unknown"><i class="fa-solid fa-triangle-exclamation me-2"></i>' + esc(data.warning.message) + '</button>' : '';
    $('#ruralWaterDashboard').innerHTML = warning + cards.map(([label, value, icon, metric]) => {
      const attr = metric ? ' role="button" tabindex="0" data-platform-action="ruralCleanWater.metric" data-metric="' + esc(metric) + '"' : '';
      return '<article class="content-card rural-water-kpi"' + attr + '><i class="fa-solid ' + icon + '"></i><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong></article>';
    }).join('');
  }

  async function loadList() {
    const data = await api('/api/rural-clean-water?' + query(true));
    $('#ruralWaterTotalCount').textContent = 'Tá»•ng sá»‘: ' + fmt(data.total || 0) + ' há»™';
    $('#ruralWaterRows').innerHTML = (data.items || []).map((row, index) => {
      const id = row.id || '';
      const label = [row.household_code, row.head_citizen_name].filter(Boolean).join(' - ');
      const editAttrs = ' data-id="' + esc(id) + '" data-household-id="' + esc(row.household_id) + '" data-label="' + esc(label) + '" data-address="' + esc(row.address || '') + '"';
      const deleteButton = id ? '<button class="btn btn-sm btn-outline-danger" data-platform-action="ruralCleanWater.delete" data-id="' + esc(id) + '" title="XÃ³a"><i class="fa-solid fa-trash"></i></button>' : '';
      return '<tr>' +
        '<td>' + (index + 1 + ((data.page || 1) - 1) * (data.pageSize || 20)) + '</td>' +
        '<td>' + esc(row.household_code) + '</td><td>' + esc(row.head_citizen_name) + '</td><td>' + esc(row.area_code) + '</td>' +
        '<td>' + esc(row.connection_type_label) + '</td><td>' + esc(row.water_supply_form_label) + '</td>' +
        '<td>' + badge(row.clean_water_status, row.clean_water_status_label) + '</td><td>' + esc(row.hygienic_water_status_label) + '</td>' +
        '<td>' + esc(row.provider_name || row.water_source) + '</td><td>' + esc(row.has_water_meter_label) + '</td>' +
        '<td>' + esc(row.verification_basis_label) + '</td>' +
        '<td class="text-end"><button class="btn btn-sm btn-outline-primary" data-platform-action="ruralCleanWater.edit"' + editAttrs + ' title="Cáº­p nháº­t"><i class="fa-solid fa-pen"></i></button> ' + deleteButton + '</td>' +
        '</tr>';
    }).join('') || '<tr><td colspan="12" class="text-center text-muted py-4">ChÆ°a cÃ³ há»™ phÃ¹ há»£p bá»™ lá»c</td></tr>';
    renderPager(data);
  }

  function badge(code, label) {
    const cls = code === 'COMPLIANT' ? 'bg-success' : (code === 'NON_COMPLIANT' ? 'bg-danger' : 'bg-secondary');
    return '<span class="badge ' + cls + '">' + esc(label || 'ChÆ°a xÃ¡c Ä‘á»‹nh') + '</span>';
  }

  function query(withPaging) {
    const params = new URLSearchParams();
    const map = {
      search: '#ruralWaterSearch',
      connection_type: '#ruralWaterTypeFilter',
      water_supply_form: '#ruralWaterSupplyFormFilter',
      clean_water_status: '#ruralWaterCleanStatusFilter',
      hygienic_water_status: '#ruralWaterHygienicFilter',
      status: '#ruralWaterStatusFilter',
      area_code: '#ruralWaterAreaFilter'
    };
    Object.entries(map).forEach(([key, selector]) => {
      const value = $(selector)?.value?.trim();
      if (value) params.set(key, value);
    });
    if (state.metric) params.set('metric', state.metric);
    if (withPaging) {
      params.set('page', state.page);
      params.set('pageSize', $('#ruralWaterPageSize')?.value || state.pageSize);
      params.set('sort', state.sort);
      params.set('direction', state.direction);
    }
    return params.toString();
  }

  function resetFilters() {
    ['#ruralWaterSearch', '#ruralWaterTypeFilter', '#ruralWaterSupplyFormFilter', '#ruralWaterCleanStatusFilter', '#ruralWaterHygienicFilter', '#ruralWaterStatusFilter', '#ruralWaterAreaFilter'].forEach(selector => { const el = $(selector); if (el) el.value = ''; });
    state.metric = '';
    state.page = 1;
    loadAll();
  }

  function applyMetric(metric) {
    state.metric = metric || '';
    state.page = 1;
    loadList();
  }

  async function openCreate(prefill = {}) {
    if (!state.catalogs) await loadCatalogs();
    const form = $('#ruralCleanWaterForm');
    form.reset();
    form.elements.id.value = '';
    form.elements.household_id.value = prefill.household_id || '';
    $('#ruralWaterHouseholdSearch').value = prefill.label || '';
    $('#ruralWaterHouseholdSelected').textContent = prefill.address || '';
    bootstrap.Modal.getOrCreateInstance($('#ruralCleanWaterModal')).show();
  }

  async function edit(id, householdId, label, address) {
    if (!id) {
      openCreate({ household_id: householdId, label, address });
      return;
    }
    const row = await api('/api/rural-clean-water/' + encodeURIComponent(id));
    if (!state.catalogs) await loadCatalogs();
    const form = $('#ruralCleanWaterForm');
    form.reset();
    Object.entries(row).forEach(([key, value]) => { if (form.elements[key]) form.elements[key].value = value ?? ''; });
    form.elements.household_id.value = row.household_id;
    $('#ruralWaterHouseholdSearch').value = [row.household_code, row.head_citizen_name].filter(Boolean).join(' - ');
    $('#ruralWaterHouseholdSelected').textContent = row.address || '';
    bootstrap.Modal.getOrCreateInstance($('#ruralCleanWaterModal')).show();
  }

  function bindForm() {
    $('#ruralCleanWaterForm')?.addEventListener('submit', async event => {
      event.preventDefault();
      const form = event.currentTarget;
      const data = Object.fromEntries(new FormData(form).entries());
      try {
        const id = data.id;
        delete data.id;
        await api('/api/rural-clean-water' + (id ? '/' + encodeURIComponent(id) : ''), { method: id ? 'PUT' : 'POST', body: data });
        bootstrap.Modal.getOrCreateInstance($('#ruralCleanWaterModal')).hide();
        await loadAll();
        toast('ÄÃ£ lÆ°u dá»¯ liá»‡u nÆ°á»›c sáº¡ch');
      } catch (error) {
        toast(error.message, 'danger');
      }
    });
    $('#ruralWaterPageSize')?.addEventListener('change', () => { state.page = 1; loadList(); });
  }

  function bindHouseholdSearch() {
    let timer = null;
    $('#ruralWaterHouseholdSearch')?.addEventListener('input', event => {
      clearTimeout(timer);
      timer = setTimeout(() => searchHouseholds(event.target.value), 250);
    });
  }

  async function searchHouseholds(value) {
    const box = $('#ruralWaterHouseholdSuggestions');
    if (!box || String(value || '').trim().length < 2) {
      box?.classList.add('d-none');
      return;
    }
    const data = await api('/api/rural-clean-water/household-search?q=' + encodeURIComponent(value));
    box.innerHTML = (data.items || []).map(row => '<button type="button" class="list-group-item list-group-item-action" data-platform-action="ruralCleanWater.selectHousehold" data-id="' + esc(row.id) + '" data-label="' + esc(row.household_code + ' - ' + row.head_citizen_name) + '" data-address="' + esc(row.address || '') + '"><strong>' + esc(row.household_code) + '</strong> ' + esc(row.head_citizen_name) + '<br><small>' + esc(row.address || '') + '</small></button>').join('');
    box.classList.toggle('d-none', !(data.items || []).length);
  }

  async function remove(id) {
    if (!id || !confirm('XÃ³a báº£n ghi nÆ°á»›c sáº¡ch nÃ y?')) return;
    await api('/api/rural-clean-water/' + encodeURIComponent(id), { method: 'DELETE' });
    await loadAll();
    toast('ÄÃ£ xÃ³a báº£n ghi nÆ°á»›c sáº¡ch');
  }

  function sort(key) {
    if (state.sort === key) state.direction = state.direction === 'ASC' ? 'DESC' : 'ASC';
    else { state.sort = key; state.direction = 'ASC'; }
    loadList();
  }

  function selectHousehold(button) {
    if (!button) return;
    $('#ruralCleanWaterForm').elements.household_id.value = button.dataset.id;
    $('#ruralWaterHouseholdSearch').value = button.dataset.label;
    $('#ruralWaterHouseholdSelected').textContent = button.dataset.address || '';
    $('#ruralWaterHouseholdSuggestions')?.classList.add('d-none');
  }

  function renderPager(data) {
    const host = $('#ruralWaterPager');
    const totalPages = Number(data.totalPages || 1);
    host.dataset.totalPages = String(totalPages);
    host.innerHTML = '<button class="btn btn-sm btn-outline-secondary" data-platform-action="ruralCleanWater.page" data-direction="prev" ' + (state.page <= 1 ? 'disabled' : '') + '>TrÆ°á»›c</button><span>Trang ' + fmt(state.page) + '/' + fmt(totalPages) + '</span><button class="btn btn-sm btn-outline-secondary" data-platform-action="ruralCleanWater.page" data-direction="next" ' + (state.page >= totalPages ? 'disabled' : '') + '>Sau</button>';
  }

  function page(direction) {
    const totalPages = Number($('#ruralWaterPager')?.dataset.totalPages || 1);
    state.page = direction === 'prev' ? Math.max(1, state.page - 1) : Math.min(totalPages, state.page + 1);
    loadList();
  }

  function fillSelect(selector, items, allLabel) {
    const select = $(selector);
    if (!select) return;
    select.innerHTML = (allLabel ? '<option value="">' + esc(allLabel) + '</option>' : '') + (items || []).map(item => '<option value="' + esc(item.value) + '">' + esc(item.label) + '</option>').join('');
  }

  function openReport(type) {
    const navigate = window.TenantAppNavigationController?.navigate || window.TenantAppPlatform?.navigation?.navigate;
    if (typeof navigate === 'function') navigate.call(window.TenantAppNavigationController || window.TenantAppPlatform.navigation, 'reports');
    setTimeout(() => window.loadReport ? (document.getElementById('reportTypeSelect').value = type, window.loadReport()) : null, 120);
  }

  function toast(message, variant = 'success') {
    if (window.App?.toast) return window.App.toast(message, variant);
    if (window.TenantAppToast) return window.TenantAppToast(message, variant);
    console[variant === 'danger' ? 'error' : 'log'](message);
  }
})();
