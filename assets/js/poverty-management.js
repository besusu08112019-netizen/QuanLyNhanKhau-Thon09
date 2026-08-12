(function () {
  'use strict';

  const API = '/api/poverty';
  const $ = (selector, root = document) => root.querySelector(selector);
  const state = {
    page: 1,
    pageSize: 20,
    search: '',
    period_id: '',
    poverty_type: '',
    record_status: '',
    area_code: '',
    year: '',
    list: '',
    sort: 'effective_from',
    direction: 'DESC',
    catalogs: null,
    householdSuggestions: []
  };
  let registered = false;
  let householdPatched = false;
  let editingRecordSnapshot = null;

  registerPlatform();
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('tenant:screen-change', event => {
    const target = event.detail?.screen || event.detail?.screenId || event.detail?.moduleKey;
    if (target === 'povertyManagement') load();
  });

  function init() {
    registerPlatform();
    ensureDom();
    bindEvents();
    patchHouseholdDetail();
    if ($('#povertyManagementScreen')?.classList.contains('active')) load();
  }

  function registerPlatform() {
    if (registered) return;
    const p = window.TenantAppPlatform;
    if (!p) return;
    registered = true;
    p.modules?.upsert?.({ moduleKey: 'povertyManagement', screenId: 'povertyManagement', path: '/poverty', label: 'Há»™ nghÃ¨o / cáº­n nghÃ¨o', mobileLabel: 'Há»™ nghÃ¨o', icon: 'fa-hand-holding-heart', permissionScope: 'poverty', loaderName: 'loadPovertyManagement' });
    p.routes?.upsert?.({ path: '/poverty', moduleKey: 'povertyManagement', screenId: 'povertyManagement', action: 'list' });
    const population = p.menus?.get?.('population');
    if (population && !String(population.items || '').includes('povertyManagement')) {
      p.menus.upsert?.(Object.assign({}, population, { items: [...(population.items || []), 'povertyManagement'] }));
    }
    p.menuRenderer?.renderAll?.();
  }

  function ensureDom() {
    const main = $('#mainContent') || $('.main-area');
    if (main && !$('#povertyManagementScreen')) {
      main.insertAdjacentHTML('beforeend', screenHtml());
    }
    if (!$('#povertyRecordModal')) document.body.insertAdjacentHTML('beforeend', recordModalHtml() + periodModalHtml() + detailModalHtml());
    ['povertyRecordModal', 'povertyPeriodModal', 'povertyDetailModal'].forEach(registerModal);
    registerActions();
    syncPermissionActions();
  }

  function bindEvents() {
    $('#povertyRecordForm')?.addEventListener('submit', saveRecord);
    $('#povertyPeriodForm')?.addEventListener('submit', savePeriod);
    $('#povertyHouseholdSearch')?.addEventListener('input', debounce(searchHouseholds, 250));
    document.addEventListener('pointerdown', event => {
      if (!event.target.closest('#povertyHouseholdSuggestions') && event.target.id !== 'povertyHouseholdSearch') {
        $('#povertyHouseholdSuggestions')?.classList.add('d-none');
      }
    });
    ['povertySearch', 'povertyYearFilter'].forEach(id => {
      $('#' + id)?.addEventListener('input', debounce(() => { collectFilters(); state.page = 1; load(); }, 300));
    });
    ['povertyPeriodFilter', 'povertyTypeFilter', 'povertyStatusFilter', 'povertyAreaFilter', 'povertyListFilter', 'povertyPageSize'].forEach(id => {
      $('#' + id)?.addEventListener('change', () => { collectFilters(); state.page = 1; load(); });
    });
  }

  function registerActions() {
    const actions = window.TenantAppPlatform?.actions;
    if (!actions?.register) return;
    actions.register('poverty.refresh', () => load());
    actions.register('poverty.reset', resetFilters);
    actions.register('poverty.openRecord', () => openRecordForm());
    actions.register('poverty.openPeriod', () => openPeriodForm());
    actions.register('poverty.periods', () => renderPeriods());
    actions.register('poverty.records', () => load());
    actions.register('poverty.report', () => renderReport());
    actions.register('poverty.selectHousehold', context => selectHouseholdById(context.dataset.householdId || context.dataset.id));
    actions.register('poverty.detail', context => openDetail(Number(context.dataset.id || 0)));
    actions.register('poverty.editRecord', context => openRecordForm(Number(context.dataset.id || 0)));
    actions.register('poverty.deleteRecord', context => deleteRecord(Number(context.dataset.id || 0)));
    actions.register('poverty.editPeriod', context => openPeriodForm(Number(context.dataset.id || 0)));
    actions.register('poverty.deletePeriod', context => deletePeriod(Number(context.dataset.id || 0)));
    actions.register('poverty.page', context => { state.page = Number(context.dataset.page || 1); load(); });
    actions.register('poverty.sort', context => sortBy(context.dataset.sort));
    actions.register('poverty.export', context => exportReport(context.dataset.format || 'excel'));
    actions.register('poverty.print', printReport);
    actions.bind?.(document);
  }

  async function load() {
    ensureDom();
    if (!can('read')) return;
    await ensureCatalogs();
    collectFilters();
    await Promise.all([renderDashboard(), renderRecords()]);
  }

  async function ensureCatalogs(force = false) {
    if (state.catalogs && !force) return state.catalogs;
    state.catalogs = await request(API + '/catalogs', { cacheTtl: 60000 });
    fill('#povertyPeriodFilter', state.catalogs.periods, 'Táº¥t cáº£');
    fill('#povertyRecordPeriod', state.catalogs.periods, 'Chá»n giai Ä‘oáº¡n');
    fill('#povertyTypeFilter', state.catalogs.poverty_types, 'Táº¥t cáº£');
    fill('#povertyRecordType', state.catalogs.poverty_types, 'Chá»n loáº¡i há»™');
    fill('#povertyStatusFilter', state.catalogs.record_statuses, 'Táº¥t cáº£');
    fill('#povertyRecordStatus', state.catalogs.record_statuses, 'Chá»n tráº¡ng thÃ¡i');
    fill('#povertyAreaFilter', state.catalogs.areas, 'Táº¥t cáº£');
    fill('#povertyPeriodStatus', state.catalogs.period_statuses, 'Chá»n tráº¡ng thÃ¡i');
    return state.catalogs;
  }

  async function renderDashboard() {
    const host = $('#povertyDashboard');
    if (!host) return;
    const data = await request(API + '/dashboard?' + params().toString(), { cacheTtl: 4000 });
    const m = data.metrics || {};
    host.innerHTML = [
      ['Tá»•ng há»™ nghÃ¨o', m.poor, 'fa-house-circle-exclamation'],
      ['Tá»•ng há»™ cáº­n nghÃ¨o', m.near_poor, 'fa-house-circle-check'],
      ['Há»™ trung bÃ¬nh', m.medium, 'fa-house-user'],
      ['Há»™ má»›i phÃ¡t sinh', m.new_entries, 'fa-arrow-trend-up'],
      ['Há»™ thoÃ¡t nghÃ¨o', m.escaped_poor, 'fa-person-walking-arrow-right'],
      ['Há»™ thoÃ¡t cáº­n nghÃ¨o', m.escaped_near_poor, 'fa-route'],
      ['Tá»· lá»‡ há»™ nghÃ¨o', (m.poor_rate || 0) + '%', 'fa-percent']
    ].map(card).join('');
    const trend = $('#povertyTrend');
    if (trend) trend.innerHTML = (data.trend || []).length ? (data.trend || []).map(row => '<div class="d-flex justify-content-between border-bottom py-1"><span>' + esc(row.year) + '</span><strong>NghÃ¨o: ' + num(row.poor) + ' Â· Cáº­n nghÃ¨o: ' + num(row.near_poor) + ' Â· Trung bÃ¬nh: ' + num(row.medium) + '</strong></div>').join('') : '<div class="text-muted">ChÆ°a cÃ³ dá»¯ liá»‡u biáº¿n Ä‘á»™ng theo nÄƒm.</div>';
  }

  async function renderRecords() {
    const body = $('#povertyRows');
    if (!body) return;
    const data = await request(API + '/records?' + params().toString(), { cacheTtl: 2000 });
    $('#povertyTotal') && ($('#povertyTotal').textContent = 'Tá»•ng sá»‘: ' + num(data.total || 0) + ' báº£n ghi');
    body.innerHTML = (data.items || []).length ? data.items.map(rowHtml).join('') : '<tr><td colspan="9" class="text-center text-muted py-4">ChÆ°a cÃ³ báº£n ghi há»™ nghÃ¨o/cáº­n nghÃ¨o</td></tr>';
    renderPager(data);
    if (typeof window.TenantAppSyncResponsiveTableLabels === 'function') window.TenantAppSyncResponsiveTableLabels($('#povertyManagementScreen') || document);
  }

  function rowHtml(row) {
    const id = Number(row.id || 0);
    const actions = [
      '<button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="poverty.detail" data-id="' + id + '" title="Xem"><i class="fa-solid fa-eye"></i></button>',
      can('update') ? '<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="poverty.editRecord" data-id="' + id + '" title="Sá»­a"><i class="fa-solid fa-pen"></i></button>' : '',
      can('delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="poverty.deleteRecord" data-id="' + id + '" title="XÃ³a"><i class="fa-solid fa-trash"></i></button>' : ''
    ].filter(Boolean).join(' ');
    return '<tr><td data-label="MÃ£ há»™"><strong>' + esc(row.household_code) + '</strong><div class="text-muted small">' + esc(row.head_citizen_name || '') + '</div></td><td data-label="Khu">' + esc(row.area_code || '') + '</td><td data-label="Giai Ä‘oáº¡n">' + esc(row.period_name || '') + '</td><td data-label="Loáº¡i há»™"><span class="badge text-bg-light">' + esc(row.poverty_type_label || '') + '</span></td><td data-label="Tá»« ngÃ y">' + esc(date(row.effective_from)) + '</td><td data-label="Äáº¿n ngÃ y">' + esc(date(row.effective_to)) + '</td><td data-label="Tráº¡ng thÃ¡i">' + esc(row.status_label || '') + '</td><td data-label="Quyáº¿t Ä‘á»‹nh">' + esc(row.decision_number || '') + '</td><td data-label="Thao tÃ¡c" class="text-end"><div class="d-flex gap-1 justify-content-end">' + actions + '</div></td></tr>';
  }

  function renderPager(data) {
    const host = $('#povertyPager');
    if (!host) return;
    const page = Number(data.page || 1), totalPages = Number(data.totalPages || 1);
    const pages = [];
    for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) pages.push(i);
    host.innerHTML = '<button class="btn btn-sm btn-outline-secondary" type="button" ' + (page <= 1 ? 'disabled' : '') + ' data-platform-action="poverty.page" data-page="' + (page - 1) + '">TrÆ°á»›c</button>' + pages.map(item => '<button class="btn btn-sm ' + (item === page ? 'btn-primary' : 'btn-outline-secondary') + '" type="button" data-platform-action="poverty.page" data-page="' + item + '">' + item + '</button>').join('') + '<button class="btn btn-sm btn-outline-secondary" type="button" ' + (page >= totalPages ? 'disabled' : '') + ' data-platform-action="poverty.page" data-page="' + (page + 1) + '">Sau</button>';
  }

  async function renderPeriods() {
    await ensureCatalogs(true);
    const data = await request(API + '/periods?pageSize=100', { cacheTtl: 0 });
    const body = $('#povertyRows');
    if (!body) return;
    $('#povertyTotal') && ($('#povertyTotal').textContent = 'Giai Ä‘oáº¡n: ' + num(data.total || 0));
    body.innerHTML = (data.items || []).length ? data.items.map(periodRow).join('') : '<tr><td colspan="9" class="text-center text-muted py-4">ChÆ°a cÃ³ giai Ä‘oáº¡n</td></tr>';
    $('#povertyPager') && ($('#povertyPager').innerHTML = '');
  }

  function periodRow(row) {
    const id = Number(row.id || 0);
    return '<tr><td colspan="2" data-label="TÃªn"><strong>' + esc(row.name) + '</strong></td><td data-label="Báº¯t Ä‘áº§u">' + esc(date(row.start_date)) + '</td><td data-label="Káº¿t thÃºc">' + esc(date(row.end_date)) + '</td><td data-label="Tráº¡ng thÃ¡i"><span class="badge text-bg-light">' + esc(row.status_label || '') + '</span></td><td colspan="3" data-label="Ghi chÃº">' + esc(row.note || '') + '</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="poverty.editPeriod" data-id="' + id + '"><i class="fa-solid fa-pen"></i></button> ' + (can('delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="poverty.deletePeriod" data-id="' + id + '"><i class="fa-solid fa-trash"></i></button>' : '') + '</td></tr>';
  }

  async function renderReport() {
    const report = await request(API + '/report?' + params().toString(), { cacheTtl: 0 });
    const body = $('#povertyRows');
    if (!body) return;
    $('#povertyTotal') && ($('#povertyTotal').textContent = 'BÃ¡o cÃ¡o: ' + num(report.totalRows || 0) + ' dÃ²ng');
    body.innerHTML = (report.rows || []).length ? report.rows.map(cols => '<tr>' + cols.slice(1).map((value, index) => '<td data-label="' + esc(report.headers[index + 1] || '') + '">' + esc(value) + '</td>').join('') + '<td></td></tr>').join('') : '<tr><td colspan="9" class="text-center text-muted py-4">ChÆ°a cÃ³ dá»¯ liá»‡u bÃ¡o cÃ¡o</td></tr>';
    $('#povertyPager') && ($('#povertyPager').innerHTML = Object.entries(report.summary || {}).map(([key, value]) => '<span class="badge text-bg-light me-1">' + esc(key) + ': ' + esc(value) + '</span>').join(''));
  }

  async function openRecordForm(id = 0) {
    if (!can(id ? 'update' : 'create')) return toast('TÃ i khoáº£n khÃ´ng cÃ³ quyá»n thao tÃ¡c', 'warning');
    await ensureCatalogs();
    const form = $('#povertyRecordForm');
    if (!form) return;
    form.reset();
    form.elements.id.value = '';
    form.elements.household_id.value = '';
    editingRecordSnapshot = null;
    $('#povertyHouseholdSearch').disabled = false;
    $('#povertyHouseholdSelected').textContent = '';
    if (id) {
      const row = await request(API + '/records/' + encodeURIComponent(id), { cacheTtl: 0 });
      editingRecordSnapshot = row;
      setForm(form, row);
      form.elements.household_id.value = row.household_id || '';
      $('#povertyHouseholdSearch').value = [row.household_code, row.head_citizen_name].filter(Boolean).join(' - ');
      $('#povertyHouseholdSearch').disabled = true;
      $('#povertyHouseholdSelected').textContent = row.address || '';
    }
    openModal('povertyRecordModal');
  }

  async function openPeriodForm(id = 0) {
    if (!can(id ? 'update' : 'create')) return toast('TÃ i khoáº£n khÃ´ng cÃ³ quyá»n thao tÃ¡c', 'warning');
    await ensureCatalogs();
    const form = $('#povertyPeriodForm');
    if (!form) return;
    form.reset();
    form.elements.id.value = '';
    if (form.elements.status && !id) form.elements.status.value = 'ACTIVE';
    if (id) setForm(form, await request(API + '/periods/' + encodeURIComponent(id), { cacheTtl: 0 }));
    openModal('povertyPeriodModal');
  }

  async function saveRecord(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const body = Object.fromEntries(new FormData(form).entries());
    if (!body.household_id || !body.period_id || !body.poverty_type || !body.effective_from) return toast('Vui lÃ²ng nháº­p Ä‘á»§ há»™, giai Ä‘oáº¡n, loáº¡i há»™ vÃ  ngÃ y báº¯t Ä‘áº§u', 'warning');
    try {
      const id = Number(body.id || 0);
      const typeChanged = id && editingRecordSnapshot && body.poverty_type !== editingRecordSnapshot.poverty_type;
      const periodChanged = id && editingRecordSnapshot && String(body.period_id) !== String(editingRecordSnapshot.period_id);
      const householdChanged = id && editingRecordSnapshot && String(body.household_id) !== String(editingRecordSnapshot.household_id);
      const shouldCreateHistory = typeChanged || periodChanged || householdChanged;
      if (shouldCreateHistory) delete body.id;
      await request(API + '/records' + (id && !shouldCreateHistory ? '/' + id : ''), { method: id && !shouldCreateHistory ? 'PUT' : 'POST', body });
      closeModal('povertyRecordModal');
      toast('ÄÃ£ lÆ°u tráº¡ng thÃ¡i há»™');
      await ensureCatalogs(true);
      await load();
    } catch (error) {
      toast(error.message, 'danger');
    }
  }

  async function savePeriod(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const body = Object.fromEntries(new FormData(form).entries());
    body.start_date = isoDate(form.elements.start_date?.value || body.start_date);
    body.end_date = isoDate(form.elements.end_date?.value || body.end_date);
    try {
      const id = Number(body.id || 0);
      await request(API + '/periods' + (id ? '/' + id : ''), { method: id ? 'PUT' : 'POST', body });
      closeModal('povertyPeriodModal');
      toast('ÄÃ£ lÆ°u giai Ä‘oáº¡n');
      await ensureCatalogs(true);
      await renderPeriods();
    } catch (error) {
      toast(error.message, 'danger');
    }
  }

  async function deleteRecord(id) {
    if (!id || !can('delete')) return;
    if (!await confirmAction({ title: 'XÃ³a báº£n ghi', message: 'Báº£n ghi sáº½ Ä‘Æ°á»£c xÃ³a má»m, lá»‹ch sá»­ thay Ä‘á»•i váº«n Ä‘Æ°á»£c lÆ°u.', tone: 'danger', confirmLabel: 'XÃ³a' })) return;
    await request(API + '/records/' + encodeURIComponent(id), { method: 'DELETE' });
    toast('ÄÃ£ xÃ³a báº£n ghi');
    load();
  }

  async function deletePeriod(id) {
    if (!id || !can('delete')) return;
    if (!await confirmAction({ title: 'XÃ³a giai Ä‘oáº¡n', message: 'Chá»‰ xÃ³a Ä‘Æ°á»£c giai Ä‘oáº¡n chÆ°a cÃ³ lá»‹ch sá»­ há»™.', tone: 'danger', confirmLabel: 'XÃ³a' })) return;
    await request(API + '/periods/' + encodeURIComponent(id), { method: 'DELETE' });
    toast('ÄÃ£ xÃ³a giai Ä‘oáº¡n');
    await ensureCatalogs(true);
    renderPeriods();
  }

  async function openDetail(id) {
    if (!id) return;
    const row = await request(API + '/records/' + encodeURIComponent(id), { cacheTtl: 0 });
    $('#povertyDetailTitle').textContent = row.household_code + ' - ' + (row.head_citizen_name || '');
    $('#povertyDetailBody').innerHTML = detailHtml(row);
    openModal('povertyDetailModal');
  }

  function detailHtml(row) {
    return '<div class="row g-3"><div class="col-md-6"><div class="content-card h-100"><h6>Há»™ gia Ä‘Ã¬nh</h6>' + info('MÃ£ há»™', row.household_code) + info('Chá»§ há»™', row.head_citizen_name) + info('Khu', row.area_code) + info('Äá»‹a chá»‰', row.address) + '</div></div><div class="col-md-6"><div class="content-card h-100"><h6>Tráº¡ng thÃ¡i</h6>' + info('Giai Ä‘oáº¡n', row.period_name) + info('Loáº¡i há»™', row.poverty_type_label) + info('Hiá»‡u lá»±c', [date(row.effective_from), date(row.effective_to)].filter(Boolean).join(' - ')) + info('Quyáº¿t Ä‘á»‹nh', row.decision_number) + info('Ghi chÃº', row.note) + '</div></div></div>';
  }

  async function searchHouseholds() {
    const input = $('#povertyHouseholdSearch'), host = $('#povertyHouseholdSuggestions'), form = $('#povertyRecordForm');
    if (!input || !host || !form) return;
    const q = input.value.trim();
    form.elements.household_id.value = '';
    state.householdSuggestions = [];
    if (q.length < 2) { host.classList.add('d-none'); return; }
    const data = await request(API + '/households/search?q=' + encodeURIComponent(q), { cacheTtl: 3000 });
    state.householdSuggestions = data.items || [];
    host.innerHTML = state.householdSuggestions.length ? state.householdSuggestions.map(item => '<button class="list-group-item list-group-item-action" type="button" data-platform-action="poverty.selectHousehold" data-household-id="' + Number(item.id) + '"><strong>' + esc(item.household_code) + '</strong> - ' + esc(item.head_citizen_name || '') + '<div class="small text-muted">' + esc(item.address || '') + '</div></button>').join('') : '<div class="list-group-item text-muted">KhÃ´ng tÃ¬m tháº¥y há»™ gia Ä‘Ã¬nh</div>';
    host.classList.remove('d-none');
  }

  function selectHousehold(item) {
    if (!item) return;
    const form = $('#povertyRecordForm');
    form.elements.household_id.value = item.id;
    $('#povertyHouseholdSearch').value = item.household_code + ' - ' + (item.head_citizen_name || '');
    $('#povertyHouseholdSelected').textContent = item.address || '';
    $('#povertyHouseholdSuggestions')?.classList.add('d-none');
  }

  function selectHouseholdById(id) {
    selectHousehold(state.householdSuggestions.find(item => String(item.id) === String(id)));
  }

  function collectFilters() {
    state.search = $('#povertySearch')?.value.trim() || '';
    state.period_id = $('#povertyPeriodFilter')?.value || '';
    state.poverty_type = $('#povertyTypeFilter')?.value || '';
    state.record_status = $('#povertyStatusFilter')?.value || '';
    state.area_code = $('#povertyAreaFilter')?.value || '';
    state.year = $('#povertyYearFilter')?.value || '';
    state.list = $('#povertyListFilter')?.value || '';
    state.pageSize = Number($('#povertyPageSize')?.value || 20);
  }

  function resetFilters() {
    ['povertySearch', 'povertyPeriodFilter', 'povertyTypeFilter', 'povertyStatusFilter', 'povertyAreaFilter', 'povertyYearFilter', 'povertyListFilter'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
    Object.assign(state, { page: 1, search: '', period_id: '', poverty_type: '', record_status: '', area_code: '', year: '', list: '' });
    load();
  }

  function params() {
    const q = new URLSearchParams({ page: state.page, pageSize: state.pageSize, sort: state.sort, direction: state.direction });
    ['search', 'period_id', 'poverty_type', 'record_status', 'area_code', 'year', 'list'].forEach(key => { if (state[key]) q.set(key, state[key]); });
    return q;
  }

  function sortBy(key) {
    if (!key) return;
    if (state.sort === key) state.direction = state.direction === 'ASC' ? 'DESC' : 'ASC';
    else { state.sort = key; state.direction = 'ASC'; }
    load();
  }

  async function exportReport(format) {
    try {
      await downloadFile(API + (format === 'pdf' ? '/export-pdf?' : '/export-excel?') + params().toString(), format === 'pdf' ? 'pdf' : 'xls');
      toast(format === 'pdf' ? 'ÄÃ£ táº£i PDF' : 'ÄÃ£ táº£i Excel');
    } catch (error) {
      toast(error.message, 'danger');
    }
  }

  async function printReport() {
    try {
      const data = await request(API + '/report?' + params().toString(), { cacheTtl: 0 });
      const printer = window.TenantAppPrint;
      if (!printer?.render) return toast('KhÃ´ng táº£i Ä‘Æ°á»£c máº«u in bÃ¡o cÃ¡o', 'warning');
      const popup = printer.render(Object.assign({}, data, { type: 'poverty', orientation: 'portrait', paperSize: 'A4' }));
      if (!popup) toast('TrÃ¬nh duyá»‡t Ä‘ang cháº·n cá»­a sá»• in', 'warning');
    } catch (error) {
      toast(error.message, 'danger');
    }
  }

  function patchHouseholdDetail() {
    if (householdPatched) return;
    if (typeof window.showHousehold !== 'function') {
      setTimeout(patchHouseholdDetail, 300);
      return;
    }
    const original = window.showHousehold;
    householdPatched = true;
    window.showHousehold = async function patchedShowHousehold(id) {
      const result = await original.apply(this, arguments);
      renderHouseholdPovertyHistory(Number(id || 0));
      return result;
    };
  }

  async function renderHouseholdPovertyHistory(householdId) {
    const body = $('#detailBody');
    if (!body || !householdId || !can('read')) return;
    let host = $('#householdPovertyHistoryTab');
    if (!host) {
      body.insertAdjacentHTML('beforeend', '<section id="householdPovertyHistoryTab" class="content-card mt-3"><h6>Lá»‹ch sá»­ há»™ nghÃ¨o / há»™ cáº­n nghÃ¨o</h6><div id="householdPovertyHistoryRows" class="table-responsive"></div></section>');
      host = $('#householdPovertyHistoryRows');
    } else {
      host = $('#householdPovertyHistoryRows');
    }
    if (!host) return;
    host.innerHTML = '<div class="text-muted">Äang táº£i lá»‹ch sá»­...</div>';
    try {
      const data = await request(API + '/households/' + encodeURIComponent(householdId) + '/history', { cacheTtl: 0 });
      const items = data.items || [];
      host.innerHTML = items.length ? '<table class="table table-sm align-middle mb-0"><thead><tr><th>Giai Ä‘oáº¡n</th><th>Loáº¡i há»™</th><th>Tá»« ngÃ y</th><th>Äáº¿n ngÃ y</th><th>Ghi chÃº</th></tr></thead><tbody>' + items.map(item => '<tr><td>' + esc(item.period_name) + '</td><td>' + esc(item.poverty_type_label) + '</td><td>' + esc(date(item.effective_from)) + '</td><td>' + esc(date(item.effective_to)) + '</td><td>' + esc(item.note || '') + '</td></tr>').join('') + '</tbody></table>' : '<div class="text-muted">ChÆ°a cÃ³ lá»‹ch sá»­ há»™ nghÃ¨o/cáº­n nghÃ¨o.</div>';
    } catch (error) {
      host.innerHTML = '<div class="text-danger">' + esc(error.message) + '</div>';
    }
  }

  function screenHtml() {
    return '<section id="povertyManagementScreen" class="screen household-management-screen poverty-management-screen"><section id="povertyDashboard" class="dashboard-kpi-grid mb-3" aria-label="Thá»‘ng kÃª há»™ nghÃ¨o"></section><section class="content-card mb-3"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">TÃ¬m kiáº¿m</label><input id="povertySearch" class="form-control" placeholder="MÃ£ há»™, chá»§ há»™, Ä‘á»‹a chá»‰, quyáº¿t Ä‘á»‹nh"></div><div class="col-md-2"><label class="form-label">Giai Ä‘oáº¡n</label><select id="povertyPeriodFilter" class="form-select"></select></div><div class="col-md-2"><label class="form-label">NÄƒm</label><input id="povertyYearFilter" class="form-control" type="number" min="1900" max="2200" placeholder="2026"></div><div class="col-md-2"><label class="form-label">Loáº¡i há»™</label><select id="povertyTypeFilter" class="form-select"></select></div><div class="col-md-2"><label class="form-label">Khu</label><select id="povertyAreaFilter" class="form-select"></select></div><div class="col-md-1"><label class="form-label">DÃ²ng</label><select id="povertyPageSize" class="form-select"><option>20</option><option>50</option><option>100</option></select></div><div class="col-md-3"><label class="form-label">Danh sÃ¡ch</label><select id="povertyListFilter" class="form-select"><option value="">Táº¥t cáº£</option><option value="poor">Há»™ nghÃ¨o</option><option value="near_poor">Há»™ cáº­n nghÃ¨o</option><option value="new_entries">Há»™ má»›i vÃ o diá»‡n</option><option value="escaped_poor">Há»™ thoÃ¡t nghÃ¨o</option><option value="escaped_near_poor">Há»™ thoÃ¡t cáº­n nghÃ¨o</option></select></div><div class="col-md-2"><label class="form-label">Tráº¡ng thÃ¡i</label><select id="povertyStatusFilter" class="form-select"></select></div><div class="col-md-7 d-flex flex-wrap gap-2"><button class="btn btn-primary" type="button" data-platform-action="poverty.refresh"><i class="fa-solid fa-magnifying-glass"></i> TÃ¬m kiáº¿m</button><button class="btn btn-outline-secondary" type="button" data-platform-action="poverty.reset"><i class="fa-solid fa-rotate-right"></i> Äáº·t láº¡i</button>' + (can('create') ? '<button class="btn btn-success" type="button" data-platform-action="poverty.openRecord"><i class="fa-solid fa-plus"></i> ThÃªm tráº¡ng thÃ¡i</button><button class="btn btn-outline-primary" type="button" data-platform-action="poverty.openPeriod"><i class="fa-solid fa-calendar-plus"></i> Giai Ä‘oáº¡n</button>' : '') + '<button class="btn btn-outline-secondary" type="button" data-platform-action="poverty.periods"><i class="fa-solid fa-calendar-days"></i> DS giai Ä‘oáº¡n</button><button class="btn btn-outline-secondary" type="button" data-platform-action="poverty.report"><i class="fa-solid fa-chart-simple"></i> BÃ¡o cÃ¡o</button><button class="btn btn-outline-success" type="button" data-platform-action="poverty.export" data-format="excel"><i class="fa-solid fa-file-excel"></i> Excel</button><button class="btn btn-outline-danger" type="button" data-platform-action="poverty.export" data-format="pdf"><i class="fa-solid fa-file-pdf"></i> PDF</button><button class="btn btn-outline-secondary" type="button" data-platform-action="poverty.print"><i class="fa-solid fa-print"></i> In</button></div></div></section><section class="content-card"><div class="d-flex justify-content-between align-items-center mb-2"><strong id="povertyTotal">Tá»•ng sá»‘: 0 báº£n ghi</strong><div id="povertyTrend" class="small text-muted"></div></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th data-platform-action="poverty.sort" data-sort="household_code">MÃ£ há»™</th><th>Khu</th><th>Giai Ä‘oáº¡n</th><th>Loáº¡i há»™</th><th data-platform-action="poverty.sort" data-sort="effective_from">Tá»« ngÃ y</th><th>Äáº¿n ngÃ y</th><th>Tráº¡ng thÃ¡i</th><th>Quyáº¿t Ä‘á»‹nh</th><th class="text-end">Thao tÃ¡c</th></tr></thead><tbody id="povertyRows"></tbody></table></div><div id="povertyPager" class="pager mt-3"></div></section></section>';
  }

  function recordModalHtml() {
    return '<div class="modal fade" id="povertyRecordModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form id="povertyRecordForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">Tráº¡ng thÃ¡i há»™ nghÃ¨o / cáº­n nghÃ¨o</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ÄÃ³ng"></button></div><div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="household_id"><div class="row g-3"><div class="col-12 position-relative"><label class="form-label">Há»™ gia Ä‘Ã¬nh</label><input id="povertyHouseholdSearch" class="form-control" autocomplete="off" placeholder="TÃ¬m mÃ£ há»™, chá»§ há»™, Ä‘á»‹a chá»‰" required><div id="povertyHouseholdSuggestions" class="list-group position-absolute w-100 shadow d-none" style="z-index:1060;max-height:260px;overflow:auto"></div><div id="povertyHouseholdSelected" class="form-text"></div></div><div class="col-md-6"><label class="form-label">Giai Ä‘oáº¡n</label><select id="povertyRecordPeriod" name="period_id" class="form-select" required></select></div><div class="col-md-6"><label class="form-label">Loáº¡i há»™</label><select id="povertyRecordType" name="poverty_type" class="form-select" required></select></div><div class="col-md-6"><label class="form-label">NgÃ y báº¯t Ä‘áº§u</label><input name="effective_from" type="date" class="form-control" required></div><div class="col-md-6"><label class="form-label">NgÃ y káº¿t thÃºc</label><input name="effective_to" type="date" class="form-control"></div><div class="col-md-6"><label class="form-label">Tráº¡ng thÃ¡i</label><select id="povertyRecordStatus" name="status" class="form-select"></select></div><div class="col-md-6"><label class="form-label">Quyáº¿t Ä‘á»‹nh</label><input name="decision_number" class="form-control"></div><div class="col-12"><label class="form-label">Ghi chÃº</label><textarea name="note" rows="3" class="form-control"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Há»§y</button><button class="btn btn-primary" type="submit">LÆ°u</button></div></form></div></div>';
  }

  function periodModalHtml() {
    return '<div class="modal fade" id="povertyPeriodModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form id="povertyPeriodForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">Giai Ä‘oáº¡n há»™ nghÃ¨o / cáº­n nghÃ¨o</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ÄÃ³ng"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="mb-3"><label class="form-label">TÃªn giai Ä‘oáº¡n</label><input name="name" class="form-control" placeholder="2026-2030" required></div><div class="row g-3"><div class="col-md-6"><label class="form-label">NgÃ y báº¯t Ä‘áº§u</label><input name="start_date" type="date" class="form-control" required></div><div class="col-md-6"><label class="form-label">NgÃ y káº¿t thÃºc</label><input name="end_date" type="date" class="form-control" required></div></div><div class="mt-3"><label class="form-label">Tráº¡ng thÃ¡i</label><select id="povertyPeriodStatus" name="status" class="form-select"></select></div><div class="mt-3"><label class="form-label">Ghi chÃº</label><textarea name="note" rows="3" class="form-control"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Há»§y</button><button class="btn btn-primary" type="submit">LÆ°u</button></div></form></div></div>';
  }

  function detailModalHtml() {
    return '<div class="modal fade" id="povertyDetailModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 id="povertyDetailTitle" class="modal-title">Chi tiáº¿t</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ÄÃ³ng"></button></div><div id="povertyDetailBody" class="modal-body"></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">ÄÃ³ng</button></div></div></div></div>';
  }

  function setForm(form, row) {
    Object.entries(row || {}).forEach(([key, value]) => {
      if (form.elements[key]) form.elements[key].value = value ?? '';
    });
  }

  function card(item) {
    return '<article class="content-card"><div class="d-flex align-items-center gap-3"><span class="app-v2-card-icon"><i class="fa-solid ' + esc(item[2]) + '"></i></span><div><div class="text-muted small">' + esc(item[0]) + '</div><strong class="fs-4">' + esc(item[1] ?? 0) + '</strong></div></div></article>';
  }

  function info(label, value) {
    return '<div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">' + esc(label) + '</span><strong>' + esc(value || 'ChÆ°a cáº­p nháº­t') + '</strong></div>';
  }

  function fill(selector, items, first) {
    const el = $(selector);
    if (!el) return;
    const current = el.value;
    el.innerHTML = '<option value="">' + esc(first || 'Chá»n') + '</option>' + (items || []).map(item => '<option value="' + esc(item.value) + '">' + esc(catalogLabel(selector, item)) + '</option>').join('');
    el.value = current;
  }

  function catalogLabel(selector, item) {
    const value = String(item?.value ?? '');
    const periodLabels = { ACTIVE: '\u0110ang \u00e1p d\u1ee5ng', ENDED: '\u0110\u00e3 k\u1ebft th\u00fac' };
    const povertyLabels = { NONE: 'Kh\u00f4ng thu\u1ed9c di\u1ec7n', NEAR_POOR: 'H\u1ed9 c\u1eadn ngh\u00e8o', MEDIUM: 'H\u1ed9 trung b\u00ecnh', POOR: 'H\u1ed9 ngh\u00e8o' };
    const recordLabels = { ACTIVE: 'Hi\u1ec7u l\u1ef1c', ENDED: '\u0110\u00e3 k\u1ebft th\u00fac' };
    if (selector === '#povertyPeriodStatus' && periodLabels[value]) return periodLabels[value];
    if ((selector === '#povertyTypeFilter' || selector === '#povertyRecordType') && povertyLabels[value]) return povertyLabels[value];
    if ((selector === '#povertyStatusFilter' || selector === '#povertyRecordStatus') && recordLabels[value]) return recordLabels[value];
    return item?.label || value;
  }
  function syncPermissionActions() {
    const screen = $('#povertyManagementScreen');
    if (!screen) return;
    const createButtons = screen.querySelectorAll('[data-platform-action="poverty.openRecord"],[data-platform-action="poverty.openPeriod"]');
    if (!can('create')) {
      createButtons.forEach(button => button.remove());
      return;
    }
    if (createButtons.length) return;
    const periodsButton = screen.querySelector('[data-platform-action="poverty.periods"]');
    if (!periodsButton?.parentElement) return;
    periodsButton.insertAdjacentHTML('beforebegin', '<button class="btn btn-success" type="button" data-platform-action="poverty.openRecord"><i class="fa-solid fa-plus"></i> ThÃªm tráº¡ng thÃ¡i</button><button class="btn btn-outline-primary" type="button" data-platform-action="poverty.openPeriod"><i class="fa-solid fa-calendar-plus"></i> Giai Ä‘oáº¡n</button>');
    window.TenantAppPlatform?.actions?.bind?.(screen);
  }

  function isoDate(value) {
    const text = String(value || '').trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(text)) return text;
    const match = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (!match) return text;
    const first = Number(match[1]);
    const second = Number(match[2]);
    const year = match[3];
    const month = second > 12 ? first : (first > 12 ? second : first);
    const day = second > 12 ? second : (first > 12 ? first : second);
    return year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
  }

  async function request(url, options = {}) {
    if (typeof window.api === 'function') return window.api(url, options);
    const storageKey = typeof window.tenantStorageKey === 'function' ? window.tenantStorageKey('token') : 'token';
    const token = window.App?.token || localStorage.getItem(storageKey) || '';
    const init = { method: options.method || 'GET', headers: { Accept: 'application/json', Authorization: token ? 'Bearer ' + token : '' }, cache: 'no-store' };
    if (options.body) {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(options.body);
    }
    const res = await fetch(url, init);
    const json = await res.json().catch(() => null);
    if (!res.ok || json?.ok === false) throw new Error(json?.error?.message || json?.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c dá»¯ liá»‡u');
    return json?.data ?? json;
  }

  function downloadFile(url, extension) {
    if (window.TenantAppExport?.download) return window.TenantAppExport.download(url, { extension });
    const storageKey = typeof window.tenantStorageKey === 'function' ? window.tenantStorageKey('token') : 'token';
    const token = window.App?.token || localStorage.getItem(storageKey) || '';
    return fetch(url, { headers: { Authorization: token ? 'Bearer ' + token : '' }, cache: 'no-store' }).then(async res => {
      const type = res.headers.get('Content-Type') || '';
      if (!res.ok || type.includes('application/json')) {
        const json = type.includes('application/json') ? await res.json().catch(() => null) : null;
        throw new Error(json?.error?.message || json?.message || 'KhÃ´ng xuáº¥t Ä‘Æ°á»£c file');
      }
      const blob = await res.blob();
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = 'bao_cao_ho_ngheo_can_ngheo_' + Date.now() + '.' + extension;
      document.body.appendChild(link);
      link.click();
      URL.revokeObjectURL(link.href);
      link.remove();
    });
  }

  function can(action) {
    if (typeof window.TenantAppCanAccess === 'function') return window.TenantAppCanAccess('poverty', action);
    const role = String(window.App?.user?.role || '').toUpperCase();
    if (['SUPER_ADMIN', 'ADMIN'].includes(role)) return true;
    if (role === 'VIEWER') return action === 'read';
    return ['read', 'create', 'update', 'delete', 'export', 'print'].includes(action);
  }

  function registerModal(id) { window.TenantAppPlatform?.modals?.registerBootstrap?.(id, '#' + id); }
  function openModal(id) { return window.TenantAppPlatform?.modals?.open?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.show(); }
  function closeModal(id) { return window.TenantAppPlatform?.modals?.close?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.hide(); }
  function confirmAction(options) { const dialog = window.TenantAppPlatform?.confirmDialog; if (dialog?.ask) return dialog.ask(options); return Promise.resolve(window.confirm(options.message || 'XÃ¡c nháº­n?')); }
  function date(value) { if (!value) return ''; const d = new Date(value); return Number.isNaN(d.getTime()) ? String(value) : new Intl.DateTimeFormat('vi-VN').format(d); }
  function num(value) { return new Intl.NumberFormat('vi-VN').format(Number(value || 0)); }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c])); }
  function toast(message, type = 'success') { if (typeof window.showToast === 'function') window.showToast(message, type); }
  function debounce(fn, ms) { let timer; return function () { clearTimeout(timer); timer = setTimeout(() => fn.apply(this, arguments), ms); }; }

  window.loadPovertyManagement = load;
  window.openPovertyRecordForm = openRecordForm;
})();

