(function () {
  'use strict';

  const API = '/api/policy-subjects';
  const $ = (selector, root = document) => root.querySelector(selector);
  const state = {
    page: 1,
    pageSize: 20,
    search: '',
    policy_type_id: '',
    record_status: '',
    area_code: '',
    gender: '',
    age_from: '',
    age_to: '',
    sort: 'start_date',
    direction: 'DESC',
    catalogs: null,
    citizenSuggestions: []
  };
  let registered = false;
  let personPatched = false;
  let editingRecordSnapshot = null;

  registerPlatform();
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('tenant:screen-change', event => {
    if (event.detail?.screen === 'policySubjects') load();
  });

  function init() {
    registerPlatform();
    ensureDom();
    bindEvents();
    patchPersonDetail();
    if ($('#policySubjectsScreen')?.classList.contains('active')) load();
  }

  function registerPlatform() {
    if (registered) return;
    const p = window.TenantAppPlatform;
    if (!p) return;
    registered = true;
    p.modules?.upsert?.({ moduleKey: 'policySubjects', screenId: 'policySubjects', path: '/policy-subjects', label: 'Äá»‘i tÆ°á»£ng chÃ­nh sÃ¡ch', mobileLabel: 'ChÃ­nh sÃ¡ch', icon: 'fa-ribbon', permissionScope: 'policy_subjects', loaderName: 'loadPolicySubjects' });
    p.routes?.upsert?.({ path: '/policy-subjects', moduleKey: 'policySubjects', screenId: 'policySubjects', action: 'list' });
    const population = p.menus?.get?.('population');
    if (population && !String(population.items || '').includes('policySubjects')) {
      const items = [...(population.items || [])];
      const povertyIndex = items.indexOf('povertyManagement');
      items.splice(povertyIndex >= 0 ? povertyIndex + 1 : items.length, 0, 'policySubjects');
      p.menus.upsert?.(Object.assign({}, population, { items }));
    }
    p.menuRenderer?.renderAll?.();
  }

  function ensureDom() {
    const main = $('#mainContent') || $('.main-area');
    if (main && !$('#policySubjectsScreen')) main.insertAdjacentHTML('beforeend', screenHtml());
    if (!$('#policySubjectRecordModal')) document.body.insertAdjacentHTML('beforeend', recordModalHtml() + typeModalHtml() + detailModalHtml());
    ['policySubjectRecordModal', 'policySubjectTypeModal', 'policySubjectDetailModal'].forEach(registerModal);
    registerActions();
    syncPermissionActions();
  }

  function bindEvents() {
    $('#policySubjectRecordForm')?.addEventListener('submit', saveRecord);
    $('#policySubjectTypeForm')?.addEventListener('submit', saveType);
    $('#policySubjectCitizenSearch')?.addEventListener('input', debounce(searchCitizens, 250));
    document.addEventListener('pointerdown', event => {
      if (!event.target.closest('#policySubjectCitizenSuggestions') && event.target.id !== 'policySubjectCitizenSearch') $('#policySubjectCitizenSuggestions')?.classList.add('d-none');
    });
    ['policySubjectSearch', 'policySubjectAgeFrom', 'policySubjectAgeTo'].forEach(id => {
      $('#' + id)?.addEventListener('input', debounce(() => { collectFilters(); state.page = 1; load(); }, 300));
    });
    ['policySubjectTypeFilter', 'policySubjectStatusFilter', 'policySubjectAreaFilter', 'policySubjectGenderFilter', 'policySubjectPageSize'].forEach(id => {
      $('#' + id)?.addEventListener('change', () => { collectFilters(); state.page = 1; load(); });
    });
  }

  function registerActions() {
    const actions = window.TenantAppPlatform?.actions;
    if (!actions?.register) return;
    actions.register('policySubjects.refresh', () => load());
    actions.register('policySubjects.reset', resetFilters);
    actions.register('policySubjects.openRecord', () => openRecordForm());
    actions.register('policySubjects.openType', () => openTypeForm());
    actions.register('policySubjects.types', () => renderTypes());
    actions.register('policySubjects.records', () => load());
    actions.register('policySubjects.report', () => renderReport());
    actions.register('policySubjects.selectCitizen', context => selectCitizen(state.citizenSuggestions.find(item => String(item.id) === String(context.dataset.id))));
    actions.register('policySubjects.detail', context => openDetail(Number(context.dataset.id || 0)));
    actions.register('policySubjects.editRecord', context => openRecordForm(Number(context.dataset.id || 0)));
    actions.register('policySubjects.deleteRecord', context => deleteRecord(Number(context.dataset.id || 0)));
    actions.register('policySubjects.editType', context => openTypeForm(Number(context.dataset.id || 0)));
    actions.register('policySubjects.deleteType', context => deleteType(Number(context.dataset.id || 0)));
    actions.register('policySubjects.uploadAttachment', uploadAttachment);
    actions.register('policySubjects.deleteAttachment', context => deleteAttachment(Number(context.dataset.id || 0), Number(context.dataset.recordId || 0)));
    actions.register('policySubjects.page', context => { state.page = Number(context.dataset.page || 1); load(); });
    actions.register('policySubjects.sort', context => sortBy(context.dataset.sort));
    actions.register('policySubjects.export', context => exportReport(context.dataset.format || 'excel'));
    actions.register('policySubjects.print', printReport);
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
    fill('#policySubjectTypeFilter', state.catalogs.policy_types, 'Táº¥t cáº£');
    fill('#policySubjectRecordType', state.catalogs.policy_types, 'Chá»n loáº¡i Ä‘á»‘i tÆ°á»£ng');
    fill('#policySubjectStatusFilter', state.catalogs.record_statuses, 'Táº¥t cáº£');
    fill('#policySubjectRecordStatus', state.catalogs.record_statuses, 'Chá»n tráº¡ng thÃ¡i');
    fill('#policySubjectAreaFilter', state.catalogs.areas, 'Táº¥t cáº£');
    fill('#policySubjectGenderFilter', state.catalogs.genders, 'Táº¥t cáº£');
    return state.catalogs;
  }

  async function renderDashboard() {
    const host = $('#policySubjectDashboard');
    if (!host) return;
    const data = await request(API + '/dashboard?' + params().toString(), { cacheTtl: 4000 });
    host.innerHTML = (data.metrics || []).map(metric => card([metric.label, metric.total, iconFor(metric.code)])).join('');
    const trend = $('#policySubjectTrend');
    if (trend) trend.innerHTML = (data.trend || []).length ? data.trend.map(row => '<div class="d-flex justify-content-between border-bottom py-1"><span>' + esc(row.year) + '</span><strong>' + num(row.total) + ' há»“ sÆ¡</strong></div>').join('') : '<div class="text-muted">ChÆ°a cÃ³ dá»¯ liá»‡u biáº¿n Ä‘á»™ng theo nÄƒm.</div>';
  }

  async function renderRecords() {
    const body = $('#policySubjectRows');
    if (!body) return;
    const data = await request(API + '/records?' + params().toString(), { cacheTtl: 2000 });
    $('#policySubjectTotal') && ($('#policySubjectTotal').textContent = 'Tá»•ng sá»‘: ' + num(data.total || 0) + ' há»“ sÆ¡');
    body.innerHTML = (data.items || []).length ? data.items.map(rowHtml).join('') : '<tr><td colspan="10" class="text-center text-muted py-4">ChÆ°a cÃ³ há»“ sÆ¡ Ä‘á»‘i tÆ°á»£ng chÃ­nh sÃ¡ch</td></tr>';
    renderPager(data);
    if (typeof window.TenantAppSyncResponsiveTableLabels === 'function') window.TenantAppSyncResponsiveTableLabels($('#policySubjectsScreen') || document);
  }

  function rowHtml(row) {
    const id = Number(row.id || 0);
    const actions = [
      '<button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="policySubjects.detail" data-id="' + id + '" title="Xem"><i class="fa-solid fa-eye"></i></button>',
      can('update') ? '<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="policySubjects.editRecord" data-id="' + id + '" title="Sá»­a"><i class="fa-solid fa-pen"></i></button>' : '',
      can('delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="policySubjects.deleteRecord" data-id="' + id + '" title="XÃ³a"><i class="fa-solid fa-trash"></i></button>' : ''
    ].filter(Boolean).join(' ');
    return '<tr><td data-label="MÃ£ nhÃ¢n kháº©u"><strong>' + esc(row.citizen_code) + '</strong><div class="text-muted small">' + esc(row.full_name || '') + '</div></td><td data-label="MÃ£ há»™">' + esc(row.household_code || '') + '</td><td data-label="Khu">' + esc(row.area_code || '') + '</td><td data-label="Loáº¡i Ä‘á»‘i tÆ°á»£ng"><span class="badge text-bg-light">' + esc(row.policy_type_name || '') + '</span></td><td data-label="Má»©c hÆ°á»Ÿng">' + esc(row.benefit_level || '') + '</td><td data-label="Quyáº¿t Ä‘á»‹nh">' + esc(row.decision_number || '') + '</td><td data-label="Báº¯t Ä‘áº§u">' + esc(date(row.benefit_start_date)) + '</td><td data-label="Káº¿t thÃºc">' + esc(date(row.benefit_end_date)) + '</td><td data-label="Tráº¡ng thÃ¡i">' + esc(row.status_label || '') + '</td><td data-label="Thao tÃ¡c" class="text-end"><div class="d-flex gap-1 justify-content-end">' + actions + '</div></td></tr>';
  }

  function renderPager(data) {
    const host = $('#policySubjectPager');
    if (!host) return;
    const page = Number(data.page || 1), totalPages = Number(data.totalPages || 1);
    const pages = [];
    for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) pages.push(i);
    host.innerHTML = '<button class="btn btn-sm btn-outline-secondary" type="button" ' + (page <= 1 ? 'disabled' : '') + ' data-platform-action="policySubjects.page" data-page="' + (page - 1) + '">TrÆ°á»›c</button>' + pages.map(item => '<button class="btn btn-sm ' + (item === page ? 'btn-primary' : 'btn-outline-secondary') + '" type="button" data-platform-action="policySubjects.page" data-page="' + item + '">' + item + '</button>').join('') + '<button class="btn btn-sm btn-outline-secondary" type="button" ' + (page >= totalPages ? 'disabled' : '') + ' data-platform-action="policySubjects.page" data-page="' + (page + 1) + '">Sau</button>';
  }

  async function renderTypes() {
    const data = await request(API + '/types?pageSize=200', { cacheTtl: 0 });
    const body = $('#policySubjectRows');
    if (!body) return;
    $('#policySubjectTotal') && ($('#policySubjectTotal').textContent = 'Loáº¡i Ä‘á»‘i tÆ°á»£ng: ' + num(data.total || 0));
    body.innerHTML = (data.items || []).length ? data.items.map(typeRow).join('') : '<tr><td colspan="10" class="text-center text-muted py-4">ChÆ°a cÃ³ loáº¡i Ä‘á»‘i tÆ°á»£ng</td></tr>';
    $('#policySubjectPager') && ($('#policySubjectPager').innerHTML = '');
  }

  function typeRow(row) {
    const id = Number(row.id || 0);
    return '<tr><td colspan="2" data-label="MÃ£"><strong>' + esc(row.code) + '</strong></td><td colspan="3" data-label="TÃªn">' + esc(row.name) + '</td><td colspan="2" data-label="MÃ´ táº£">' + esc(row.description || '') + '</td><td data-label="Tráº¡ng thÃ¡i">' + (Number(row.is_active) ? 'Äang dÃ¹ng' : 'Táº¡m dá»«ng') + '</td><td colspan="2" class="text-end"><button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="policySubjects.editType" data-id="' + id + '"><i class="fa-solid fa-pen"></i></button> ' + (can('delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="policySubjects.deleteType" data-id="' + id + '"><i class="fa-solid fa-trash"></i></button>' : '') + '</td></tr>';
  }

  async function renderReport() {
    const report = await request(API + '/report?' + params().toString(), { cacheTtl: 0 });
    const body = $('#policySubjectRows');
    if (!body) return;
    $('#policySubjectTotal') && ($('#policySubjectTotal').textContent = 'BÃ¡o cÃ¡o: ' + num(report.totalRows || 0) + ' dÃ²ng');
    body.innerHTML = (report.rows || []).length ? report.rows.map(cols => '<tr>' + cols.slice(1, 10).map((value, index) => '<td data-label="' + esc(report.headers[index + 1] || '') + '">' + esc(value) + '</td>').join('') + '<td></td></tr>').join('') : '<tr><td colspan="10" class="text-center text-muted py-4">ChÆ°a cÃ³ dá»¯ liá»‡u bÃ¡o cÃ¡o</td></tr>';
    $('#policySubjectPager') && ($('#policySubjectPager').innerHTML = Object.entries(report.summary || {}).map(([key, value]) => '<span class="badge text-bg-light me-1">' + esc(key) + ': ' + esc(value) + '</span>').join(''));
  }

  async function openRecordForm(id = 0) {
    if (!can(id ? 'update' : 'create')) return toast('TÃ i khoáº£n khÃ´ng cÃ³ quyá»n thao tÃ¡c', 'warning');
    await ensureCatalogs();
    const form = $('#policySubjectRecordForm');
    if (!form) return;
    form.reset();
    form.elements.id.value = '';
    form.elements.citizen_id.value = '';
    editingRecordSnapshot = null;
    $('#policySubjectCitizenSearch').disabled = false;
    $('#policySubjectCitizenSelected').textContent = '';
    if (id) {
      const row = await request(API + '/records/' + encodeURIComponent(id), { cacheTtl: 0 });
      editingRecordSnapshot = row;
      setForm(form, row);
      form.elements.citizen_id.value = row.citizen_id || '';
      $('#policySubjectCitizenSearch').value = [row.citizen_code, row.full_name, row.identity_number].filter(Boolean).join(' - ');
      $('#policySubjectCitizenSearch').disabled = true;
      $('#policySubjectCitizenSelected').textContent = [row.identity_number ? 'CCCD: ' + row.identity_number : '', row.household_code, row.household_address].filter(Boolean).join(', ');
      renderAttachmentPanel(row);
    } else {
      renderAttachmentPanel(null);
    }
    openModal('policySubjectRecordModal');
  }

  async function saveRecord(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const body = Object.fromEntries(new FormData(form).entries());
    if (!body.citizen_id || !body.policy_type_id || !body.benefit_start_date) return toast('Vui lÃ²ng nháº­p Ä‘á»§ nhÃ¢n kháº©u, loáº¡i Ä‘á»‘i tÆ°á»£ng vÃ  ngÃ y báº¯t Ä‘áº§u hÆ°á»Ÿng', 'warning');
    try {
      const id = Number(body.id || 0);
      const typeChanged = id && editingRecordSnapshot && String(body.policy_type_id) !== String(editingRecordSnapshot.policy_type_id);
      const citizenChanged = id && editingRecordSnapshot && String(body.citizen_id) !== String(editingRecordSnapshot.citizen_id);
      if (typeChanged || citizenChanged) delete body.id;
      await request(API + '/records' + (id && !typeChanged && !citizenChanged ? '/' + id : ''), { method: id && !typeChanged && !citizenChanged ? 'PUT' : 'POST', body });
      closeModal('policySubjectRecordModal');
      toast('ÄÃ£ lÆ°u há»“ sÆ¡ chÃ­nh sÃ¡ch');
      await ensureCatalogs(true);
      await load();
    } catch (error) {
      toast(error.message, 'danger');
    }
  }

  async function openTypeForm(id = 0) {
    if (!can(id ? 'update' : 'create')) return toast('TÃ i khoáº£n khÃ´ng cÃ³ quyá»n thao tÃ¡c', 'warning');
    const form = $('#policySubjectTypeForm');
    if (!form) return;
    form.reset();
    form.elements.id.value = '';
    form.elements.is_active.value = '1';
    if (id) {
      const data = await request(API + '/types?pageSize=200', { cacheTtl: 0 });
      const row = (data.items || []).find(item => Number(item.id) === id);
      if (row) setForm(form, row);
    }
    openModal('policySubjectTypeModal');
  }

  async function saveType(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const body = Object.fromEntries(new FormData(form).entries());
    try {
      const id = Number(body.id || 0);
      await request(API + '/types' + (id ? '/' + id : ''), { method: id ? 'PUT' : 'POST', body });
      closeModal('policySubjectTypeModal');
      toast('ÄÃ£ lÆ°u loáº¡i Ä‘á»‘i tÆ°á»£ng');
      await ensureCatalogs(true);
      await renderTypes();
    } catch (error) {
      toast(error.message, 'danger');
    }
  }

  async function deleteRecord(id) {
    if (!id || !can('delete')) return;
    if (!await confirmAction({ title: 'XÃ³a há»“ sÆ¡', message: 'Há»“ sÆ¡ sáº½ Ä‘Æ°á»£c xÃ³a má»m, lá»‹ch sá»­ thay Ä‘á»•i váº«n Ä‘Æ°á»£c lÆ°u.', tone: 'danger', confirmLabel: 'XÃ³a' })) return;
    await request(API + '/records/' + encodeURIComponent(id), { method: 'DELETE' });
    toast('ÄÃ£ xÃ³a há»“ sÆ¡');
    load();
  }

  async function deleteType(id) {
    if (!id || !can('delete')) return;
    if (!await confirmAction({ title: 'XÃ³a loáº¡i Ä‘á»‘i tÆ°á»£ng', message: 'Chá»‰ xÃ³a Ä‘Æ°á»£c loáº¡i chÆ°a cÃ³ há»“ sÆ¡ chÃ­nh sÃ¡ch.', tone: 'danger', confirmLabel: 'XÃ³a' })) return;
    await request(API + '/types/' + encodeURIComponent(id), { method: 'DELETE' });
    toast('ÄÃ£ xÃ³a loáº¡i Ä‘á»‘i tÆ°á»£ng');
    await ensureCatalogs(true);
    renderTypes();
  }

  async function openDetail(id) {
    if (!id) return;
    const row = await request(API + '/records/' + encodeURIComponent(id), { cacheTtl: 0 });
    $('#policySubjectDetailTitle').textContent = row.citizen_code + ' - ' + (row.full_name || '');
    $('#policySubjectDetailBody').innerHTML = detailHtml(row);
    openModal('policySubjectDetailModal');
  }

  function detailHtml(row) {
    const attachments = (row.attachments || []).map(file => '<li><a href="' + esc(file.url) + '" target="_blank" rel="noopener">' + esc(file.original_name) + '</a> <span class="text-muted small">' + esc(file.file_type) + '</span></li>').join('');
    const history = (row.history || []).slice(0, 12).map(item => '<tr><td>' + esc(item.action) + '</td><td>' + esc(dateTime(item.created_at)) + '</td><td>' + esc(item.actor_user_id || '') + '</td></tr>').join('');
    return '<div class="row g-3"><div class="col-md-6"><div class="content-card h-100"><h6>NhÃ¢n kháº©u</h6>' + info('MÃ£ nhÃ¢n kháº©u', row.citizen_code) + info('Há» tÃªn', row.full_name) + info('CCCD', row.identity_number) + info('MÃ£ há»™', row.household_code) + info('Khu', row.area_code) + info('Äá»‹a chá»‰', row.household_address) + '</div></div><div class="col-md-6"><div class="content-card h-100"><h6>ChÃ­nh sÃ¡ch</h6>' + info('Loáº¡i Ä‘á»‘i tÆ°á»£ng', row.policy_type_name) + info('Má»©c hÆ°á»Ÿng', row.benefit_level) + info('Quyáº¿t Ä‘á»‹nh', row.decision_number) + info('CÆ¡ quan ban hÃ nh', row.issuing_authority) + info('Hiá»‡u lá»±c', [date(row.benefit_start_date), date(row.benefit_end_date)].filter(Boolean).join(' - ')) + info('Tráº¡ng thÃ¡i', row.status_label) + '</div></div><div class="col-md-6"><div class="content-card h-100"><h6>Há»“ sÆ¡ Ä‘Ã­nh kÃ¨m</h6>' + (attachments ? '<ul class="mb-0">' + attachments + '</ul>' : '<div class="text-muted">ChÆ°a cÃ³ há»“ sÆ¡ Ä‘Ã­nh kÃ¨m.</div>') + '</div></div><div class="col-md-6"><div class="content-card h-100"><h6>Lá»‹ch sá»­</h6><table class="table table-sm mb-0"><tbody>' + (history || '<tr><td class="text-muted">ChÆ°a cÃ³ lá»‹ch sá»­.</td></tr>') + '</tbody></table></div></div></div>';
  }

  async function searchCitizens() {
    const input = $('#policySubjectCitizenSearch'), host = $('#policySubjectCitizenSuggestions'), form = $('#policySubjectRecordForm');
    if (!input || !host || !form) return;
    const q = input.value.trim();
    form.elements.citizen_id.value = '';
    state.citizenSuggestions = [];
    if (q.length < 2) { host.classList.add('d-none'); return; }
    const data = await request(API + '/citizens/search?q=' + encodeURIComponent(q), { cacheTtl: 3000 });
    state.citizenSuggestions = data.items || [];
    host.innerHTML = state.citizenSuggestions.length ? state.citizenSuggestions.map(item => '<button class="list-group-item list-group-item-action" type="button" data-platform-action="policySubjects.selectCitizen" data-id="' + Number(item.id) + '"><strong>' + esc(item.citizen_code) + '</strong> - ' + esc(item.full_name || '') + '<div class="small text-muted">' + esc([item.identity_number ? 'CCCD: ' + item.identity_number : '', item.household_code, item.address].filter(Boolean).join(', ')) + '</div></button>').join('') : '<div class="list-group-item text-muted">KhÃ´ng tÃ¬m tháº¥y nhÃ¢n kháº©u</div>';
    host.classList.remove('d-none');
  }

  function selectCitizen(item) {
    if (!item) return;
    const form = $('#policySubjectRecordForm');
    form.elements.citizen_id.value = item.id;
    $('#policySubjectCitizenSearch').value = [item.citizen_code, item.full_name, item.identity_number].filter(Boolean).join(' - ');
    $('#policySubjectCitizenSelected').textContent = [item.identity_number ? 'CCCD: ' + item.identity_number : '', item.household_code, item.address].filter(Boolean).join(', ');
    $('#policySubjectCitizenSuggestions')?.classList.add('d-none');
  }

  async function uploadAttachment() {
    const form = $('#policySubjectAttachmentForm');
    const recordId = Number(form?.querySelector('[name="record_id"]')?.value || 0);
    if (!recordId) return toast('Vui lÃ²ng lÆ°u há»“ sÆ¡ trÆ°á»›c khi upload', 'warning');
    const data = new FormData();
    const file = form.querySelector('[name="file"]')?.files?.[0];
    if (!file) return toast('Vui lÃ²ng chá»n file', 'warning');
    data.set('record_id', String(recordId));
    data.set('file_type', form.querySelector('[name="file_type"]')?.value || 'OTHER');
    data.set('file', file);
    try {
      await request(API + '/records/' + encodeURIComponent(recordId) + '/attachments', { method: 'POST', body: data });
      toast('ÄÃ£ upload há»“ sÆ¡ Ä‘Ã­nh kÃ¨m');
      const row = await request(API + '/records/' + encodeURIComponent(recordId), { cacheTtl: 0 });
      renderAttachmentPanel(row);
    } catch (error) {
      toast(error.message, 'danger');
    }
  }

  async function deleteAttachment(id, recordId) {
    if (!id || !can('delete')) return;
    await request(API + '/attachments/' + encodeURIComponent(id), { method: 'DELETE' });
    const row = await request(API + '/records/' + encodeURIComponent(recordId), { cacheTtl: 0 });
    renderAttachmentPanel(row);
  }

  function renderAttachmentPanel(row) {
    const form = $('#policySubjectAttachmentForm');
    const list = $('#policySubjectAttachmentList');
    if (!form || !list) return;
    const id = Number(row?.id || 0);
    form.querySelector('[name="record_id"]').value = id || '';
    form.classList.toggle('d-none', !id || !can('upload'));
    const items = row?.attachments || [];
    list.innerHTML = items.length ? items.map(file => '<div class="d-flex justify-content-between align-items-center border-bottom py-1"><a href="' + esc(file.url) + '" target="_blank" rel="noopener">' + esc(file.original_name) + '</a>' + (can('delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="policySubjects.deleteAttachment" data-id="' + Number(file.id) + '" data-record-id="' + id + '"><i class="fa-solid fa-trash"></i></button>' : '') + '</div>').join('') : '<div class="text-muted small">ChÆ°a cÃ³ há»“ sÆ¡ Ä‘Ã­nh kÃ¨m.</div>';
  }

  function collectFilters() {
    state.search = $('#policySubjectSearch')?.value.trim() || '';
    state.policy_type_id = $('#policySubjectTypeFilter')?.value || '';
    state.record_status = $('#policySubjectStatusFilter')?.value || '';
    state.area_code = $('#policySubjectAreaFilter')?.value || '';
    state.gender = $('#policySubjectGenderFilter')?.value || '';
    state.age_from = $('#policySubjectAgeFrom')?.value || '';
    state.age_to = $('#policySubjectAgeTo')?.value || '';
    state.pageSize = Number($('#policySubjectPageSize')?.value || 20);
  }

  function resetFilters() {
    ['policySubjectSearch', 'policySubjectTypeFilter', 'policySubjectStatusFilter', 'policySubjectAreaFilter', 'policySubjectGenderFilter', 'policySubjectAgeFrom', 'policySubjectAgeTo'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
    Object.assign(state, { page: 1, search: '', policy_type_id: '', record_status: '', area_code: '', gender: '', age_from: '', age_to: '' });
    load();
  }

  function params() {
    const q = new URLSearchParams({ page: state.page, pageSize: state.pageSize, sort: state.sort, direction: state.direction });
    ['search', 'policy_type_id', 'record_status', 'area_code', 'gender', 'age_from', 'age_to'].forEach(key => { if (state[key]) q.set(key, state[key]); });
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
      const popup = printer.render(Object.assign({}, data, { type: 'policy-subjects', orientation: 'landscape', paperSize: 'A4' }));
      if (!popup) toast('TrÃ¬nh duyá»‡t Ä‘ang cháº·n cá»­a sá»• in', 'warning');
    } catch (error) {
      toast(error.message, 'danger');
    }
  }

  function patchPersonDetail() {
    if (personPatched) return;
    if (typeof window.showPerson !== 'function') {
      setTimeout(patchPersonDetail, 300);
      return;
    }
    const original = window.showPerson;
    personPatched = true;
    window.showPerson = async function patchedShowPerson(id) {
      const result = await original.apply(this, arguments);
      renderPersonPolicySummary(Number(id || 0));
      return result;
    };
  }

  async function renderPersonPolicySummary(citizenId) {
    const body = $('#detailBody');
    if (!body || !citizenId || !can('read')) return;
    let host = $('#personPolicySubjectSummary');
    if (!host) {
      body.insertAdjacentHTML('beforeend', '<section id="personPolicySubjectSummary" class="content-card mt-3"><div class="d-flex justify-content-between align-items-center"><h6 class="mb-0">Äá»‘i tÆ°á»£ng chÃ­nh sÃ¡ch</h6><button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="policySubjects.records">Má»Ÿ module</button></div><div id="personPolicySubjectRows" class="mt-2"></div></section>');
      host = $('#personPolicySubjectRows');
    } else {
      host = $('#personPolicySubjectRows');
    }
    if (!host) return;
    host.innerHTML = '<div class="text-muted">Äang táº£i...</div>';
    try {
      const data = await request(API + '/citizens/' + encodeURIComponent(citizenId) + '/summary', { cacheTtl: 0 });
      const items = data.items || [];
      if (items.length) {
        host.innerHTML = items.map(item => '<button class="btn btn-sm btn-outline-secondary me-1 mb-1" type="button" data-platform-action="policySubjects.detail" data-id="' + Number(item.id) + '"><i class="fa-solid fa-check"></i> ' + esc(item.policy_type_name) + '</button>').join('');
      } else {
        const labels = await citizenPolicyFlagLabels(citizenId);
        host.innerHTML = labels.length ? labels.map(label => '<span class="badge bg-info text-dark me-1 mb-1"><i class="fa-solid fa-check"></i> ' + esc(label) + '</span>').join('') : '<div class="text-muted">ChÆ°a cÃ³ diá»‡n chÃ­nh sÃ¡ch.</div>';
      }
      window.TenantAppPlatform?.actions?.bind?.(host);
    } catch (error) {
      const labels = await citizenPolicyFlagLabels(citizenId).catch(() => []);
      host.innerHTML = labels.length ? labels.map(label => '<span class="badge bg-info text-dark me-1 mb-1"><i class="fa-solid fa-check"></i> ' + esc(label) + '</span>').join('') : '<div class="text-danger">' + esc(error.message) + '</div>';
    }
  }

  async function citizenPolicyFlagLabels(citizenId) {
    if (!citizenId) return [];
    const row = await request('/api/persons/' + encodeURIComponent(citizenId), { cacheTtl: 0 });
    const labels = [];
    const has = (...keys) => keys.some(key => Number(row?.[key] ?? 0) === 1 || row?.[key] === true || String(row?.[key] ?? '').trim() === '1');
    if (has('meritorious_person')) labels.push('Ng\u01b0\u1eddi c\u00f3 c\u00f4ng');
    if (has('martyr_relative')) labels.push('Th\u00e2n nh\u00e2n li\u1ec7t s\u0129');
    if (has('wounded_soldier')) labels.push('Th\u01b0\u01a1ng binh');
    if (has('sick_soldier')) labels.push('B\u1ec7nh binh');
    if (has('chemical_warfare_victim')) labels.push('Nhi\u1ec5m ch\u1ea5t \u0111\u1ed9c h\u00f3a h\u1ecdc');
    if (has('imprisoned_resistance_activist')) labels.push('B\u1ecb \u0111\u1ecbch b\u1eaft t\u00f9, \u0111\u00e0y');
    if (has('youth_volunteer')) labels.push('Thanh ni\u00ean xung phong');
    if (has('resistance_hero')) labels.push('Anh h\u00f9ng LLVTND / Anh h\u00f9ng Lao \u0111\u1ed9ng');
    if (has('revolutionary_activist')) labels.push('Ng\u01b0\u1eddi ho\u1ea1t \u0111\u1ed9ng c\u00e1ch m\u1ea1ng');
    if (has('disabled_person')) labels.push('Ng\u01b0\u1eddi khuy\u1ebft t\u1eadt');
    return labels;
  }

  function screenHtml() {
    return '<section id="policySubjectsScreen" class="screen household-management-screen policy-subjects-screen"><section id="policySubjectDashboard" class="dashboard-kpi-grid mb-3" aria-label="Thá»‘ng kÃª Ä‘á»‘i tÆ°á»£ng chÃ­nh sÃ¡ch"></section><section class="content-card mb-3"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">TÃ¬m kiáº¿m</label><input id="policySubjectSearch" class="form-control" placeholder="MÃ£ nhÃ¢n kháº©u, há» tÃªn, mÃ£ há»™, quyáº¿t Ä‘á»‹nh"></div><div class="col-md-2"><label class="form-label">Loáº¡i Ä‘á»‘i tÆ°á»£ng</label><select id="policySubjectTypeFilter" class="form-select"></select></div><div class="col-md-2"><label class="form-label">Tráº¡ng thÃ¡i</label><select id="policySubjectStatusFilter" class="form-select"></select></div><div class="col-md-2"><label class="form-label">Khu</label><select id="policySubjectAreaFilter" class="form-select"></select></div><div class="col-md-1"><label class="form-label">Giá»›i tÃ­nh</label><select id="policySubjectGenderFilter" class="form-select"></select></div><div class="col-md-1"><label class="form-label">Tuá»•i tá»«</label><input id="policySubjectAgeFrom" class="form-control" type="number" min="0"></div><div class="col-md-1"><label class="form-label">Äáº¿n</label><input id="policySubjectAgeTo" class="form-control" type="number" min="0"></div><div class="col-md-1"><label class="form-label">DÃ²ng</label><select id="policySubjectPageSize" class="form-select"><option>20</option><option>50</option><option>100</option></select></div><div class="col-md-11 d-flex flex-wrap gap-2"><button class="btn btn-primary" type="button" data-platform-action="policySubjects.refresh"><i class="fa-solid fa-magnifying-glass"></i> TÃ¬m kiáº¿m</button><button class="btn btn-outline-secondary" type="button" data-platform-action="policySubjects.reset"><i class="fa-solid fa-rotate-right"></i> Äáº·t láº¡i</button>' + (can('create') ? '<button class="btn btn-success" type="button" data-platform-action="policySubjects.openRecord"><i class="fa-solid fa-plus"></i> ThÃªm há»“ sÆ¡</button><button class="btn btn-outline-primary" type="button" data-platform-action="policySubjects.openType"><i class="fa-solid fa-tags"></i> Loáº¡i Ä‘á»‘i tÆ°á»£ng</button>' : '') + '<button class="btn btn-outline-secondary" type="button" data-platform-action="policySubjects.types"><i class="fa-solid fa-list"></i> Danh má»¥c</button><button class="btn btn-outline-secondary" type="button" data-platform-action="policySubjects.report"><i class="fa-solid fa-chart-simple"></i> BÃ¡o cÃ¡o</button><button class="btn btn-outline-success" type="button" data-platform-action="policySubjects.export" data-format="excel"><i class="fa-solid fa-file-excel"></i> Excel</button><button class="btn btn-outline-danger" type="button" data-platform-action="policySubjects.export" data-format="pdf"><i class="fa-solid fa-file-pdf"></i> PDF</button><button class="btn btn-outline-secondary" type="button" data-platform-action="policySubjects.print"><i class="fa-solid fa-print"></i> In</button></div></div></section><section class="content-card"><div class="d-flex justify-content-between align-items-center mb-2"><strong id="policySubjectTotal">Tá»•ng sá»‘: 0 há»“ sÆ¡</strong><div id="policySubjectTrend" class="small text-muted"></div></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th data-platform-action="policySubjects.sort" data-sort="citizen_code">MÃ£ nhÃ¢n kháº©u</th><th>MÃ£ há»™</th><th>Khu</th><th data-platform-action="policySubjects.sort" data-sort="policy_type">Loáº¡i Ä‘á»‘i tÆ°á»£ng</th><th>Má»©c hÆ°á»Ÿng</th><th>Quyáº¿t Ä‘á»‹nh</th><th data-platform-action="policySubjects.sort" data-sort="start_date">Báº¯t Ä‘áº§u</th><th>Káº¿t thÃºc</th><th>Tráº¡ng thÃ¡i</th><th class="text-end">Thao tÃ¡c</th></tr></thead><tbody id="policySubjectRows"></tbody></table></div><div id="policySubjectPager" class="pager mt-3"></div></section></section>';
  }

  function recordModalHtml() {
    return '<div class="modal fade" id="policySubjectRecordModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form id="policySubjectRecordForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">Há»“ sÆ¡ Ä‘á»‘i tÆ°á»£ng chÃ­nh sÃ¡ch</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ÄÃ³ng"></button></div><div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="citizen_id"><div class="row g-3"><div class="col-12 position-relative"><label class="form-label">NhÃ¢n kháº©u</label><input id="policySubjectCitizenSearch" class="form-control" autocomplete="off" placeholder="TÃ¬m mÃ£ nhÃ¢n kháº©u, há» tÃªn, CCCD, mÃ£ há»™" required><div id="policySubjectCitizenSuggestions" class="list-group position-absolute w-100 shadow d-none" style="z-index:1060;max-height:260px;overflow:auto"></div><div id="policySubjectCitizenSelected" class="form-text"></div></div><div class="col-md-6"><label class="form-label">Loáº¡i Ä‘á»‘i tÆ°á»£ng</label><select id="policySubjectRecordType" name="policy_type_id" class="form-select" required></select></div><div class="col-md-6"><label class="form-label">Má»©c hÆ°á»Ÿng</label><input name="benefit_level" class="form-control"></div><div class="col-md-4"><label class="form-label">Sá»‘ quyáº¿t Ä‘á»‹nh</label><input name="decision_number" class="form-control"></div><div class="col-md-4"><label class="form-label">NgÃ y quyáº¿t Ä‘á»‹nh</label><input name="decision_date" type="date" class="form-control"></div><div class="col-md-4"><label class="form-label">CÆ¡ quan ban hÃ nh</label><input name="issuing_authority" class="form-control"></div><div class="col-md-4"><label class="form-label">NgÃ y báº¯t Ä‘áº§u hÆ°á»Ÿng</label><input name="benefit_start_date" type="date" class="form-control" required></div><div class="col-md-4"><label class="form-label">NgÃ y káº¿t thÃºc</label><input name="benefit_end_date" type="date" class="form-control"></div><div class="col-md-4"><label class="form-label">Tráº¡ng thÃ¡i</label><select id="policySubjectRecordStatus" name="status" class="form-select"></select></div><div class="col-12"><label class="form-label">Ghi chÃº</label><textarea name="note" rows="3" class="form-control"></textarea></div><div class="col-12"><div class="content-card"><h6>Há»“ sÆ¡ Ä‘Ã­nh kÃ¨m</h6><div id="policySubjectAttachmentList" class="mb-2 text-muted small">LÆ°u há»“ sÆ¡ trÆ°á»›c khi upload.</div><div id="policySubjectAttachmentForm" class="d-none d-flex gap-2 flex-wrap align-items-end"><input type="hidden" name="record_id"><div><label class="form-label">Loáº¡i file</label><select name="file_type" class="form-select"><option value="DECISION">Quyáº¿t Ä‘á»‹nh</option><option value="CERTIFICATE">Giáº¥y chá»©ng nháº­n</option><option value="MERITORIOUS_PROFILE">Há»“ sÆ¡ ngÆ°á»i cÃ³ cÃ´ng</option><option value="DISABILITY_CERTIFICATE">Giáº¥y xÃ¡c nháº­n khuyáº¿t táº­t</option><option value="SOCIAL_ASSISTANCE_PROFILE">Há»“ sÆ¡ báº£o trá»£ xÃ£ há»™i</option><option value="OTHER">KhÃ¡c</option></select></div><div class="flex-grow-1"><label class="form-label">File</label><input name="file" type="file" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,application/pdf,image/png,image/jpeg,image/webp"></div><button id="policySubjectAttachmentButton" class="btn btn-outline-primary" type="button" data-platform-action="policySubjects.uploadAttachment">Upload</button></div></div></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Há»§y</button><button class="btn btn-primary" type="submit">LÆ°u</button></div></form></div></div>';
  }

  function typeModalHtml() {
    return '<div class="modal fade" id="policySubjectTypeModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form id="policySubjectTypeForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">Loáº¡i Ä‘á»‘i tÆ°á»£ng chÃ­nh sÃ¡ch</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ÄÃ³ng"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="mb-3"><label class="form-label">MÃ£ loáº¡i</label><input name="code" class="form-control" placeholder="DISABLED_PERSON"></div><div class="mb-3"><label class="form-label">TÃªn loáº¡i</label><input name="name" class="form-control" required></div><div class="row g-3"><div class="col-md-6"><label class="form-label">Thá»© tá»±</label><input name="display_order" type="number" class="form-control" value="0"></div><div class="col-md-6"><label class="form-label">Tráº¡ng thÃ¡i</label><select name="is_active" class="form-select"><option value="1">Äang dÃ¹ng</option><option value="0">Táº¡m dá»«ng</option></select></div></div><div class="mt-3"><label class="form-label">MÃ´ táº£</label><textarea name="description" rows="3" class="form-control"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Há»§y</button><button class="btn btn-primary" type="submit">LÆ°u</button></div></form></div></div>';
  }

  function detailModalHtml() {
    return '<div class="modal fade" id="policySubjectDetailModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 id="policySubjectDetailTitle" class="modal-title">Chi tiáº¿t</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ÄÃ³ng"></button></div><div id="policySubjectDetailBody" class="modal-body"></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">ÄÃ³ng</button></div></div></div></div>';
  }

  function setForm(form, row) {
    Object.entries(row || {}).forEach(([key, value]) => {
      if (form.elements[key]) form.elements[key].value = value ?? '';
    });
  }

  function syncPermissionActions() {
    const screen = $('#policySubjectsScreen');
    if (!screen) return;
    const createButtons = screen.querySelectorAll('[data-platform-action="policySubjects.openRecord"],[data-platform-action="policySubjects.openType"]');
    if (!can('create')) {
      createButtons.forEach(button => button.remove());
      return;
    }
    if (!createButtons.length) {
      const typesButton = screen.querySelector('[data-platform-action="policySubjects.types"]');
      typesButton?.insertAdjacentHTML('beforebegin', '<button class="btn btn-success" type="button" data-platform-action="policySubjects.openRecord"><i class="fa-solid fa-plus"></i> ThÃªm há»“ sÆ¡</button><button class="btn btn-outline-primary" type="button" data-platform-action="policySubjects.openType"><i class="fa-solid fa-tags"></i> Loáº¡i Ä‘á»‘i tÆ°á»£ng</button>');
      window.TenantAppPlatform?.actions?.bind?.(screen);
    }
  }

  function fill(selector, items, first) {
    const el = $(selector);
    if (!el) return;
    const current = el.value;
    el.innerHTML = '<option value="">' + esc(first || 'Chá»n') + '</option>' + (items || []).map(item => '<option value="' + esc(item.value) + '">' + esc(item.label || item.value) + '</option>').join('');
    el.value = current;
  }

  async function request(url, options = {}) {
    if (typeof window.api === 'function') return window.api(url, options);
    const storageKey = typeof window.tenantStorageKey === 'function' ? window.tenantStorageKey('token') : 'token';
    const token = window.App?.token || localStorage.getItem(storageKey) || '';
    const isFormData = options.body instanceof FormData;
    const init = { method: options.method || 'GET', headers: { Accept: 'application/json', Authorization: token ? 'Bearer ' + token : '' }, cache: 'no-store' };
    if (options.body) {
      if (!isFormData) init.headers['Content-Type'] = 'application/json';
      init.body = isFormData ? options.body : JSON.stringify(options.body);
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
      link.download = 'bao_cao_doi_tuong_chinh_sach_' + Date.now() + '.' + extension;
      document.body.appendChild(link);
      link.click();
      URL.revokeObjectURL(link.href);
      link.remove();
    });
  }

  function can(action) {
    if (typeof window.TenantAppCanAccess === 'function') return window.TenantAppCanAccess('policy_subjects', action);
    const role = String(window.App?.user?.role || '').toUpperCase();
    if (['SUPER_ADMIN', 'ADMIN'].includes(role)) return true;
    if (role === 'VIEWER') return action === 'read';
    return ['read', 'create', 'update', 'delete', 'upload', 'export', 'print'].includes(action);
  }

  function iconFor(code) {
    return ({
      MERITORIOUS_PERSON: 'fa-medal',
      WOUNDED_SOLDIER: 'fa-user-shield',
      SICK_SOLDIER: 'fa-briefcase-medical',
      MARTYR: 'fa-star',
      MARTYR_RELATIVE: 'fa-people-roof',
      CHEMICAL_WARFARE_VICTIM: 'fa-radiation',
      SOCIAL_ASSISTANCE: 'fa-hand-holding-heart',
      DISABLED_PERSON: 'fa-wheelchair'
    })[code] || 'fa-ribbon';
  }

  function card(item) { return '<article class="content-card"><div class="d-flex align-items-center gap-3"><span class="app-v2-card-icon"><i class="fa-solid ' + esc(item[2]) + '"></i></span><div><div class="text-muted small">' + esc(item[0]) + '</div><strong class="fs-4">' + esc(item[1] ?? 0) + '</strong></div></div></article>'; }
  function info(label, value) { return '<div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">' + esc(label) + '</span><strong>' + esc(value || 'ChÆ°a cáº­p nháº­t') + '</strong></div>'; }
  function registerModal(id) { window.TenantAppPlatform?.modals?.registerBootstrap?.(id, '#' + id); }
  function openModal(id) { return window.TenantAppPlatform?.modals?.open?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.show(); }
  function closeModal(id) { return window.TenantAppPlatform?.modals?.close?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.hide(); }
  function confirmAction(options) { const dialog = window.TenantAppPlatform?.confirmDialog; if (dialog?.ask) return dialog.ask(options); return Promise.resolve(window.confirm(options.message || 'XÃ¡c nháº­n?')); }
  function date(value) { if (!value) return ''; const text = String(value).slice(0, 10); const parts = text.split('-'); return parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : text; }
  function dateTime(value) { return value ? String(value).replace('T', ' ').slice(0, 19) : ''; }
  function num(value) { return new Intl.NumberFormat('vi-VN').format(Number(value || 0)); }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c])); }
  function toast(message, type = 'success') { if (typeof window.showToast === 'function') window.showToast(message, type); }
  function debounce(fn, ms) { let timer; return function () { clearTimeout(timer); timer = setTimeout(() => fn.apply(this, arguments), ms); }; }

  window.loadPolicySubjects = load;
  window.openPolicySubjectRecordForm = openRecordForm;
})();

