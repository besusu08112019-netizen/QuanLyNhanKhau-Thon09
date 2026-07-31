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
    p.modules?.upsert?.({ moduleKey: 'policySubjects', screenId: 'policySubjects', path: '/policy-subjects', label: 'Đối tượng chính sách', mobileLabel: 'Chính sách', icon: 'fa-ribbon', permissionScope: 'policy_subjects', loaderName: 'loadPolicySubjects' });
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
    fill('#policySubjectTypeFilter', state.catalogs.policy_types, 'Tất cả');
    fill('#policySubjectRecordType', state.catalogs.policy_types, 'Chọn loại đối tượng');
    fill('#policySubjectStatusFilter', state.catalogs.record_statuses, 'Tất cả');
    fill('#policySubjectRecordStatus', state.catalogs.record_statuses, 'Chọn trạng thái');
    fill('#policySubjectAreaFilter', state.catalogs.areas, 'Tất cả');
    fill('#policySubjectGenderFilter', state.catalogs.genders, 'Tất cả');
    return state.catalogs;
  }

  async function renderDashboard() {
    const host = $('#policySubjectDashboard');
    if (!host) return;
    const data = await request(API + '/dashboard?' + params().toString(), { cacheTtl: 4000 });
    host.innerHTML = (data.metrics || []).map(metric => card([metric.label, metric.total, iconFor(metric.code)])).join('');
    const trend = $('#policySubjectTrend');
    if (trend) trend.innerHTML = (data.trend || []).length ? data.trend.map(row => '<div class="d-flex justify-content-between border-bottom py-1"><span>' + esc(row.year) + '</span><strong>' + num(row.total) + ' hồ sơ</strong></div>').join('') : '<div class="text-muted">Chưa có dữ liệu biến động theo năm.</div>';
  }

  async function renderRecords() {
    const body = $('#policySubjectRows');
    if (!body) return;
    const data = await request(API + '/records?' + params().toString(), { cacheTtl: 2000 });
    $('#policySubjectTotal') && ($('#policySubjectTotal').textContent = 'Tổng số: ' + num(data.total || 0) + ' hồ sơ');
    body.innerHTML = (data.items || []).length ? data.items.map(rowHtml).join('') : '<tr><td colspan="10" class="text-center text-muted py-4">Chưa có hồ sơ đối tượng chính sách</td></tr>';
    renderPager(data);
    if (typeof window.TenantAppSyncResponsiveTableLabels === 'function') window.TenantAppSyncResponsiveTableLabels($('#policySubjectsScreen') || document);
  }

  function rowHtml(row) {
    const id = Number(row.id || 0);
    const actions = [
      '<button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="policySubjects.detail" data-id="' + id + '" title="Xem"><i class="fa-solid fa-eye"></i></button>',
      can('update') ? '<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="policySubjects.editRecord" data-id="' + id + '" title="Sửa"><i class="fa-solid fa-pen"></i></button>' : '',
      can('delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="policySubjects.deleteRecord" data-id="' + id + '" title="Xóa"><i class="fa-solid fa-trash"></i></button>' : ''
    ].filter(Boolean).join(' ');
    return '<tr><td data-label="Mã nhân khẩu"><strong>' + esc(row.citizen_code) + '</strong><div class="text-muted small">' + esc(row.full_name || '') + '</div></td><td data-label="Mã hộ">' + esc(row.household_code || '') + '</td><td data-label="Khu">' + esc(row.area_code || '') + '</td><td data-label="Loại đối tượng"><span class="badge text-bg-light">' + esc(row.policy_type_name || '') + '</span></td><td data-label="Mức hưởng">' + esc(row.benefit_level || '') + '</td><td data-label="Quyết định">' + esc(row.decision_number || '') + '</td><td data-label="Bắt đầu">' + esc(date(row.benefit_start_date)) + '</td><td data-label="Kết thúc">' + esc(date(row.benefit_end_date)) + '</td><td data-label="Trạng thái">' + esc(row.status_label || '') + '</td><td data-label="Thao tác" class="text-end"><div class="d-flex gap-1 justify-content-end">' + actions + '</div></td></tr>';
  }

  function renderPager(data) {
    const host = $('#policySubjectPager');
    if (!host) return;
    const page = Number(data.page || 1), totalPages = Number(data.totalPages || 1);
    const pages = [];
    for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) pages.push(i);
    host.innerHTML = '<button class="btn btn-sm btn-outline-secondary" type="button" ' + (page <= 1 ? 'disabled' : '') + ' data-platform-action="policySubjects.page" data-page="' + (page - 1) + '">Trước</button>' + pages.map(item => '<button class="btn btn-sm ' + (item === page ? 'btn-primary' : 'btn-outline-secondary') + '" type="button" data-platform-action="policySubjects.page" data-page="' + item + '">' + item + '</button>').join('') + '<button class="btn btn-sm btn-outline-secondary" type="button" ' + (page >= totalPages ? 'disabled' : '') + ' data-platform-action="policySubjects.page" data-page="' + (page + 1) + '">Sau</button>';
  }

  async function renderTypes() {
    const data = await request(API + '/types?pageSize=200', { cacheTtl: 0 });
    const body = $('#policySubjectRows');
    if (!body) return;
    $('#policySubjectTotal') && ($('#policySubjectTotal').textContent = 'Loại đối tượng: ' + num(data.total || 0));
    body.innerHTML = (data.items || []).length ? data.items.map(typeRow).join('') : '<tr><td colspan="10" class="text-center text-muted py-4">Chưa có loại đối tượng</td></tr>';
    $('#policySubjectPager') && ($('#policySubjectPager').innerHTML = '');
  }

  function typeRow(row) {
    const id = Number(row.id || 0);
    return '<tr><td colspan="2" data-label="Mã"><strong>' + esc(row.code) + '</strong></td><td colspan="3" data-label="Tên">' + esc(row.name) + '</td><td colspan="2" data-label="Mô tả">' + esc(row.description || '') + '</td><td data-label="Trạng thái">' + (Number(row.is_active) ? 'Đang dùng' : 'Tạm dừng') + '</td><td colspan="2" class="text-end"><button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="policySubjects.editType" data-id="' + id + '"><i class="fa-solid fa-pen"></i></button> ' + (can('delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="policySubjects.deleteType" data-id="' + id + '"><i class="fa-solid fa-trash"></i></button>' : '') + '</td></tr>';
  }

  async function renderReport() {
    const report = await request(API + '/report?' + params().toString(), { cacheTtl: 0 });
    const body = $('#policySubjectRows');
    if (!body) return;
    $('#policySubjectTotal') && ($('#policySubjectTotal').textContent = 'Báo cáo: ' + num(report.totalRows || 0) + ' dòng');
    body.innerHTML = (report.rows || []).length ? report.rows.map(cols => '<tr>' + cols.slice(1, 10).map((value, index) => '<td data-label="' + esc(report.headers[index + 1] || '') + '">' + esc(value) + '</td>').join('') + '<td></td></tr>').join('') : '<tr><td colspan="10" class="text-center text-muted py-4">Chưa có dữ liệu báo cáo</td></tr>';
    $('#policySubjectPager') && ($('#policySubjectPager').innerHTML = Object.entries(report.summary || {}).map(([key, value]) => '<span class="badge text-bg-light me-1">' + esc(key) + ': ' + esc(value) + '</span>').join(''));
  }

  async function openRecordForm(id = 0) {
    if (!can(id ? 'update' : 'create')) return toast('Tài khoản không có quyền thao tác', 'warning');
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
      $('#policySubjectCitizenSearch').value = [row.citizen_code, row.full_name].filter(Boolean).join(' - ');
      $('#policySubjectCitizenSearch').disabled = true;
      $('#policySubjectCitizenSelected').textContent = [row.household_code, row.household_address].filter(Boolean).join(', ');
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
    if (!body.citizen_id || !body.policy_type_id || !body.benefit_start_date) return toast('Vui lòng nhập đủ nhân khẩu, loại đối tượng và ngày bắt đầu hưởng', 'warning');
    try {
      const id = Number(body.id || 0);
      const typeChanged = id && editingRecordSnapshot && String(body.policy_type_id) !== String(editingRecordSnapshot.policy_type_id);
      const citizenChanged = id && editingRecordSnapshot && String(body.citizen_id) !== String(editingRecordSnapshot.citizen_id);
      if (typeChanged || citizenChanged) delete body.id;
      await request(API + '/records' + (id && !typeChanged && !citizenChanged ? '/' + id : ''), { method: id && !typeChanged && !citizenChanged ? 'PUT' : 'POST', body });
      closeModal('policySubjectRecordModal');
      toast('Đã lưu hồ sơ chính sách');
      await ensureCatalogs(true);
      await load();
    } catch (error) {
      toast(error.message, 'danger');
    }
  }

  async function openTypeForm(id = 0) {
    if (!can(id ? 'update' : 'create')) return toast('Tài khoản không có quyền thao tác', 'warning');
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
      toast('Đã lưu loại đối tượng');
      await ensureCatalogs(true);
      await renderTypes();
    } catch (error) {
      toast(error.message, 'danger');
    }
  }

  async function deleteRecord(id) {
    if (!id || !can('delete')) return;
    if (!await confirmAction({ title: 'Xóa hồ sơ', message: 'Hồ sơ sẽ được xóa mềm, lịch sử thay đổi vẫn được lưu.', tone: 'danger', confirmLabel: 'Xóa' })) return;
    await request(API + '/records/' + encodeURIComponent(id), { method: 'DELETE' });
    toast('Đã xóa hồ sơ');
    load();
  }

  async function deleteType(id) {
    if (!id || !can('delete')) return;
    if (!await confirmAction({ title: 'Xóa loại đối tượng', message: 'Chỉ xóa được loại chưa có hồ sơ chính sách.', tone: 'danger', confirmLabel: 'Xóa' })) return;
    await request(API + '/types/' + encodeURIComponent(id), { method: 'DELETE' });
    toast('Đã xóa loại đối tượng');
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
    return '<div class="row g-3"><div class="col-md-6"><div class="content-card h-100"><h6>Nhân khẩu</h6>' + info('Mã nhân khẩu', row.citizen_code) + info('Họ tên', row.full_name) + info('Mã hộ', row.household_code) + info('Khu', row.area_code) + info('Địa chỉ', row.household_address) + '</div></div><div class="col-md-6"><div class="content-card h-100"><h6>Chính sách</h6>' + info('Loại đối tượng', row.policy_type_name) + info('Mức hưởng', row.benefit_level) + info('Quyết định', row.decision_number) + info('Cơ quan ban hành', row.issuing_authority) + info('Hiệu lực', [date(row.benefit_start_date), date(row.benefit_end_date)].filter(Boolean).join(' - ')) + info('Trạng thái', row.status_label) + '</div></div><div class="col-md-6"><div class="content-card h-100"><h6>Hồ sơ đính kèm</h6>' + (attachments ? '<ul class="mb-0">' + attachments + '</ul>' : '<div class="text-muted">Chưa có hồ sơ đính kèm.</div>') + '</div></div><div class="col-md-6"><div class="content-card h-100"><h6>Lịch sử</h6><table class="table table-sm mb-0"><tbody>' + (history || '<tr><td class="text-muted">Chưa có lịch sử.</td></tr>') + '</tbody></table></div></div></div>';
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
    host.innerHTML = state.citizenSuggestions.length ? state.citizenSuggestions.map(item => '<button class="list-group-item list-group-item-action" type="button" data-platform-action="policySubjects.selectCitizen" data-id="' + Number(item.id) + '"><strong>' + esc(item.citizen_code) + '</strong> - ' + esc(item.full_name || '') + '<div class="small text-muted">' + esc([item.household_code, item.address].filter(Boolean).join(', ')) + '</div></button>').join('') : '<div class="list-group-item text-muted">Không tìm thấy nhân khẩu</div>';
    host.classList.remove('d-none');
  }

  function selectCitizen(item) {
    if (!item) return;
    const form = $('#policySubjectRecordForm');
    form.elements.citizen_id.value = item.id;
    $('#policySubjectCitizenSearch').value = item.citizen_code + ' - ' + (item.full_name || '');
    $('#policySubjectCitizenSelected').textContent = [item.household_code, item.address].filter(Boolean).join(', ');
    $('#policySubjectCitizenSuggestions')?.classList.add('d-none');
  }

  async function uploadAttachment() {
    const form = $('#policySubjectAttachmentForm');
    const recordId = Number(form?.querySelector('[name="record_id"]')?.value || 0);
    if (!recordId) return toast('Vui lòng lưu hồ sơ trước khi upload', 'warning');
    const data = new FormData();
    const file = form.querySelector('[name="file"]')?.files?.[0];
    if (!file) return toast('Vui lòng chọn file', 'warning');
    data.set('record_id', String(recordId));
    data.set('file_type', form.querySelector('[name="file_type"]')?.value || 'OTHER');
    data.set('file', file);
    try {
      await request(API + '/records/' + encodeURIComponent(recordId) + '/attachments', { method: 'POST', body: data });
      toast('Đã upload hồ sơ đính kèm');
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
    list.innerHTML = items.length ? items.map(file => '<div class="d-flex justify-content-between align-items-center border-bottom py-1"><a href="' + esc(file.url) + '" target="_blank" rel="noopener">' + esc(file.original_name) + '</a>' + (can('delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="policySubjects.deleteAttachment" data-id="' + Number(file.id) + '" data-record-id="' + id + '"><i class="fa-solid fa-trash"></i></button>' : '') + '</div>').join('') : '<div class="text-muted small">Chưa có hồ sơ đính kèm.</div>';
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
      toast(format === 'pdf' ? 'Đã tải PDF' : 'Đã tải Excel');
    } catch (error) {
      toast(error.message, 'danger');
    }
  }

  async function printReport() {
    try {
      const data = await request(API + '/report?' + params().toString(), { cacheTtl: 0 });
      const printer = window.TenantAppPrint;
      if (!printer?.render) return toast('Không tải được mẫu in báo cáo', 'warning');
      const popup = printer.render(Object.assign({}, data, { type: 'policy-subjects', orientation: 'landscape', paperSize: 'A4' }));
      if (!popup) toast('Trình duyệt đang chặn cửa sổ in', 'warning');
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
      body.insertAdjacentHTML('beforeend', '<section id="personPolicySubjectSummary" class="content-card mt-3"><div class="d-flex justify-content-between align-items-center"><h6 class="mb-0">Đối tượng chính sách</h6><button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="policySubjects.records">Mở module</button></div><div id="personPolicySubjectRows" class="mt-2"></div></section>');
      host = $('#personPolicySubjectRows');
    } else {
      host = $('#personPolicySubjectRows');
    }
    if (!host) return;
    host.innerHTML = '<div class="text-muted">Đang tải...</div>';
    try {
      const data = await request(API + '/citizens/' + encodeURIComponent(citizenId) + '/summary', { cacheTtl: 0 });
      const items = data.items || [];
      host.innerHTML = items.length ? items.map(item => '<button class="btn btn-sm btn-outline-secondary me-1 mb-1" type="button" data-platform-action="policySubjects.detail" data-id="' + Number(item.id) + '"><i class="fa-solid fa-check"></i> ' + esc(item.policy_type_name) + '</button>').join('') : '<div class="text-muted">Chưa có diện chính sách.</div>';
      window.TenantAppPlatform?.actions?.bind?.(host);
    } catch (error) {
      host.innerHTML = '<div class="text-danger">' + esc(error.message) + '</div>';
    }
  }

  function screenHtml() {
    return '<section id="policySubjectsScreen" class="screen household-management-screen policy-subjects-screen"><section id="policySubjectDashboard" class="dashboard-kpi-grid mb-3" aria-label="Thống kê đối tượng chính sách"></section><section class="content-card mb-3"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">Tìm kiếm</label><input id="policySubjectSearch" class="form-control" placeholder="Mã nhân khẩu, họ tên, mã hộ, quyết định"></div><div class="col-md-2"><label class="form-label">Loại đối tượng</label><select id="policySubjectTypeFilter" class="form-select"></select></div><div class="col-md-2"><label class="form-label">Trạng thái</label><select id="policySubjectStatusFilter" class="form-select"></select></div><div class="col-md-2"><label class="form-label">Khu</label><select id="policySubjectAreaFilter" class="form-select"></select></div><div class="col-md-1"><label class="form-label">Giới tính</label><select id="policySubjectGenderFilter" class="form-select"></select></div><div class="col-md-1"><label class="form-label">Tuổi từ</label><input id="policySubjectAgeFrom" class="form-control" type="number" min="0"></div><div class="col-md-1"><label class="form-label">Đến</label><input id="policySubjectAgeTo" class="form-control" type="number" min="0"></div><div class="col-md-1"><label class="form-label">Dòng</label><select id="policySubjectPageSize" class="form-select"><option>20</option><option>50</option><option>100</option></select></div><div class="col-md-11 d-flex flex-wrap gap-2"><button class="btn btn-primary" type="button" data-platform-action="policySubjects.refresh"><i class="fa-solid fa-magnifying-glass"></i> Tìm kiếm</button><button class="btn btn-outline-secondary" type="button" data-platform-action="policySubjects.reset"><i class="fa-solid fa-rotate-right"></i> Đặt lại</button>' + (can('create') ? '<button class="btn btn-success" type="button" data-platform-action="policySubjects.openRecord"><i class="fa-solid fa-plus"></i> Thêm hồ sơ</button><button class="btn btn-outline-primary" type="button" data-platform-action="policySubjects.openType"><i class="fa-solid fa-tags"></i> Loại đối tượng</button>' : '') + '<button class="btn btn-outline-secondary" type="button" data-platform-action="policySubjects.types"><i class="fa-solid fa-list"></i> Danh mục</button><button class="btn btn-outline-secondary" type="button" data-platform-action="policySubjects.report"><i class="fa-solid fa-chart-simple"></i> Báo cáo</button><button class="btn btn-outline-success" type="button" data-platform-action="policySubjects.export" data-format="excel"><i class="fa-solid fa-file-excel"></i> Excel</button><button class="btn btn-outline-danger" type="button" data-platform-action="policySubjects.export" data-format="pdf"><i class="fa-solid fa-file-pdf"></i> PDF</button><button class="btn btn-outline-secondary" type="button" data-platform-action="policySubjects.print"><i class="fa-solid fa-print"></i> In</button></div></div></section><section class="content-card"><div class="d-flex justify-content-between align-items-center mb-2"><strong id="policySubjectTotal">Tổng số: 0 hồ sơ</strong><div id="policySubjectTrend" class="small text-muted"></div></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th data-platform-action="policySubjects.sort" data-sort="citizen_code">Mã nhân khẩu</th><th>Mã hộ</th><th>Khu</th><th data-platform-action="policySubjects.sort" data-sort="policy_type">Loại đối tượng</th><th>Mức hưởng</th><th>Quyết định</th><th data-platform-action="policySubjects.sort" data-sort="start_date">Bắt đầu</th><th>Kết thúc</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead><tbody id="policySubjectRows"></tbody></table></div><div id="policySubjectPager" class="pager mt-3"></div></section></section>';
  }

  function recordModalHtml() {
    return '<div class="modal fade" id="policySubjectRecordModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form id="policySubjectRecordForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">Hồ sơ đối tượng chính sách</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="citizen_id"><div class="row g-3"><div class="col-12 position-relative"><label class="form-label">Nhân khẩu</label><input id="policySubjectCitizenSearch" class="form-control" autocomplete="off" placeholder="Tìm mã nhân khẩu, họ tên, CCCD, mã hộ" required><div id="policySubjectCitizenSuggestions" class="list-group position-absolute w-100 shadow d-none" style="z-index:1060;max-height:260px;overflow:auto"></div><div id="policySubjectCitizenSelected" class="form-text"></div></div><div class="col-md-6"><label class="form-label">Loại đối tượng</label><select id="policySubjectRecordType" name="policy_type_id" class="form-select" required></select></div><div class="col-md-6"><label class="form-label">Mức hưởng</label><input name="benefit_level" class="form-control"></div><div class="col-md-4"><label class="form-label">Số quyết định</label><input name="decision_number" class="form-control"></div><div class="col-md-4"><label class="form-label">Ngày quyết định</label><input name="decision_date" type="date" class="form-control"></div><div class="col-md-4"><label class="form-label">Cơ quan ban hành</label><input name="issuing_authority" class="form-control"></div><div class="col-md-4"><label class="form-label">Ngày bắt đầu hưởng</label><input name="benefit_start_date" type="date" class="form-control" required></div><div class="col-md-4"><label class="form-label">Ngày kết thúc</label><input name="benefit_end_date" type="date" class="form-control"></div><div class="col-md-4"><label class="form-label">Trạng thái</label><select id="policySubjectRecordStatus" name="status" class="form-select"></select></div><div class="col-12"><label class="form-label">Ghi chú</label><textarea name="note" rows="3" class="form-control"></textarea></div><div class="col-12"><div class="content-card"><h6>Hồ sơ đính kèm</h6><div id="policySubjectAttachmentList" class="mb-2 text-muted small">Lưu hồ sơ trước khi upload.</div><div id="policySubjectAttachmentForm" class="d-none d-flex gap-2 flex-wrap align-items-end"><input type="hidden" name="record_id"><div><label class="form-label">Loại file</label><select name="file_type" class="form-select"><option value="DECISION">Quyết định</option><option value="CERTIFICATE">Giấy chứng nhận</option><option value="MERITORIOUS_PROFILE">Hồ sơ người có công</option><option value="DISABILITY_CERTIFICATE">Giấy xác nhận khuyết tật</option><option value="SOCIAL_ASSISTANCE_PROFILE">Hồ sơ bảo trợ xã hội</option><option value="OTHER">Khác</option></select></div><div class="flex-grow-1"><label class="form-label">File</label><input name="file" type="file" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,application/pdf,image/png,image/jpeg,image/webp"></div><button id="policySubjectAttachmentButton" class="btn btn-outline-primary" type="button" data-platform-action="policySubjects.uploadAttachment">Upload</button></div></div></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button><button class="btn btn-primary" type="submit">Lưu</button></div></form></div></div>';
  }

  function typeModalHtml() {
    return '<div class="modal fade" id="policySubjectTypeModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form id="policySubjectTypeForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">Loại đối tượng chính sách</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="mb-3"><label class="form-label">Mã loại</label><input name="code" class="form-control" placeholder="DISABLED_PERSON"></div><div class="mb-3"><label class="form-label">Tên loại</label><input name="name" class="form-control" required></div><div class="row g-3"><div class="col-md-6"><label class="form-label">Thứ tự</label><input name="display_order" type="number" class="form-control" value="0"></div><div class="col-md-6"><label class="form-label">Trạng thái</label><select name="is_active" class="form-select"><option value="1">Đang dùng</option><option value="0">Tạm dừng</option></select></div></div><div class="mt-3"><label class="form-label">Mô tả</label><textarea name="description" rows="3" class="form-control"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button><button class="btn btn-primary" type="submit">Lưu</button></div></form></div></div>';
  }

  function detailModalHtml() {
    return '<div class="modal fade" id="policySubjectDetailModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 id="policySubjectDetailTitle" class="modal-title">Chi tiết</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div id="policySubjectDetailBody" class="modal-body"></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Đóng</button></div></div></div></div>';
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
    if (!can('create')) createButtons.forEach(button => button.remove());
  }

  function fill(selector, items, first) {
    const el = $(selector);
    if (!el) return;
    const current = el.value;
    el.innerHTML = '<option value="">' + esc(first || 'Chọn') + '</option>' + (items || []).map(item => '<option value="' + esc(item.value) + '">' + esc(item.label || item.value) + '</option>').join('');
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
    if (!res.ok || json?.ok === false) throw new Error(json?.error?.message || json?.message || 'Không tải được dữ liệu');
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
        throw new Error(json?.error?.message || json?.message || 'Không xuất được file');
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
  function info(label, value) { return '<div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">' + esc(label) + '</span><strong>' + esc(value || 'Chưa cập nhật') + '</strong></div>'; }
  function registerModal(id) { window.TenantAppPlatform?.modals?.registerBootstrap?.(id, '#' + id); }
  function openModal(id) { return window.TenantAppPlatform?.modals?.open?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.show(); }
  function closeModal(id) { return window.TenantAppPlatform?.modals?.close?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.hide(); }
  function confirmAction(options) { const dialog = window.TenantAppPlatform?.confirmDialog; if (dialog?.ask) return dialog.ask(options); return Promise.resolve(window.confirm(options.message || 'Xác nhận?')); }
  function date(value) { if (!value) return ''; const text = String(value).slice(0, 10); const parts = text.split('-'); return parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : text; }
  function dateTime(value) { return value ? String(value).replace('T', ' ').slice(0, 19) : ''; }
  function num(value) { return new Intl.NumberFormat('vi-VN').format(Number(value || 0)); }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c])); }
  function toast(message, type = 'success') { if (typeof window.showToast === 'function') window.showToast(message, type); }
  function debounce(fn, ms) { let timer; return function () { clearTimeout(timer); timer = setTimeout(() => fn.apply(this, arguments), ms); }; }

  window.loadPolicySubjects = load;
  window.openPolicySubjectRecordForm = openRecordForm;
})();
