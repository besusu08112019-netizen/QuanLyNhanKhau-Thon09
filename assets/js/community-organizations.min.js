(function () {
  'use strict';
  const API = '/api/organizations';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const state = { page: 1, pageSize: 20, search: '', organization_code: '', status: '', gender: '', area_code: '', position_id: '', age_from: '', age_to: '', joined_year: '', catalogs: null, suggestions: [], selectedCitizen: null, editingId: 0 };
  let registered = false;
  let profilePatched = false;

  registerPlatform();
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('tenant:screen-change', e => { if (e.detail?.screen === 'communityOrganizations') load(); });

  function init() {
    ensureScreen();
    registerPlatform();
    registerActions();
    bindEvents();
    if ($('#communityOrganizationsScreen')?.classList.contains('active')) load();
  }

  function ensureScreen() {
    if ($('#communityOrganizationsScreen')) return;
    const host = $('#mainContent') || $('.main-content') || document.body;
    host.insertAdjacentHTML('beforeend', screenHtml());
    injectStyle();
  }

  function screenHtml() {
    return '<section id="communityOrganizationsScreen" class="app-screen" data-screen="communityOrganizations">' +
      '<div class="content-header d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3"><div><h1 class="h4 mb-1">Đoàn thể - Chi hội</h1><div class="text-muted small">Quản lý hội viên liên kết trực tiếp với hồ sơ nhân khẩu</div></div><div class="d-flex gap-2"><button class="btn btn-outline-secondary" data-platform-action="communityOrganizations.print"><i class="fa-solid fa-print me-1"></i> In</button><button class="btn btn-outline-success" data-platform-action="communityOrganizations.export"><i class="fa-solid fa-file-excel me-1"></i> Excel</button><button class="btn btn-primary" data-platform-action="communityOrganizations.openCreate"><i class="fa-solid fa-plus me-1"></i> Thêm thành viên</button></div></div>' +
      '<div id="communityOrgDashboard" class="community-org-dashboard mb-3"></div><div id="communityOrgWarnings" class="mb-3"></div>' +
      '<div class="card content-card mb-3"><div class="card-body"><div class="row g-2 align-items-end"><div class="col-lg-3 col-md-6"><label class="form-label">Tìm kiếm</label><input id="communityOrgSearch" class="form-control" placeholder="Họ tên, mã nhân khẩu, mã hộ, số thẻ"></div><div class="col-lg-2 col-md-6"><label class="form-label">Tổ chức</label><select id="communityOrgFilter" class="form-select"></select></div><div class="col-lg-2 col-md-6"><label class="form-label">Trạng thái</label><select id="communityOrgStatusFilter" class="form-select"></select></div><div class="col-lg-2 col-md-6"><label class="form-label">Khu vực</label><select id="communityOrgAreaFilter" class="form-select"></select></div><div class="col-lg-1 col-6"><label class="form-label">Từ tuổi</label><input id="communityOrgAgeFrom" class="form-control" type="number" min="0"></div><div class="col-lg-1 col-6"><label class="form-label">Đến tuổi</label><input id="communityOrgAgeTo" class="form-control" type="number" min="0"></div><div class="col-lg-1 col-12"><button class="btn btn-outline-secondary w-100" data-platform-action="communityOrganizations.reset"><i class="fa-solid fa-rotate-left"></i></button></div></div></div></div>' +
      '<div class="card content-card"><div class="card-header d-flex justify-content-between align-items-center"><div><strong>Danh sách thành viên</strong><div class="small text-muted" id="communityOrgTotal">Tổng số: 0</div></div><select id="communityOrgPageSize" class="form-select form-select-sm" style="width:auto"><option>20</option><option>50</option><option>100</option></select></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>STT</th><th>Họ và tên</th><th>Ngày sinh</th><th>Tuổi</th><th>Giới tính</th><th>Mã hộ</th><th>Khu vực</th><th>Tổ chức</th><th>Chức vụ</th><th>Ngày tham gia</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead><tbody id="communityOrgRows"></tbody></table></div><div class="card-footer d-flex justify-content-end gap-2" id="communityOrgPager"></div></div>' +
      modalHtml() + '</section>';
  }

  function modalHtml() {
    return '<div class="modal fade" id="communityOrgModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form id="communityOrgForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">Thông tin đoàn thể - chi hội</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="citizen_id"><input type="hidden" name="id"><div class="row g-3"><div class="col-md-6 position-relative"><label class="form-label">Tìm nhân khẩu *</label><input id="communityOrgCitizenSearch" class="form-control" autocomplete="off" placeholder="Nhập họ tên, mã nhân khẩu, mã hộ, CCCD"><div id="communityOrgCitizenSuggestions" class="list-group community-org-suggestions"></div><div id="communityOrgSelectedCitizen" class="small text-muted mt-1"></div></div><div class="col-md-6"><label class="form-label">Tổ chức *</label><select name="organization_code" id="communityOrgOrgSelect" class="form-select" required></select></div><div class="col-md-6"><label class="form-label">Chức vụ</label><select name="position_id" id="communityOrgPositionSelect" class="form-select"></select></div><div class="col-md-6"><label class="form-label">Tổ/nhóm trực thuộc</label><input name="subgroup_name" class="form-control"></div><div class="col-md-4"><label class="form-label">Số thẻ</label><input name="member_number" class="form-control"></div><div class="col-md-4"><label class="form-label">Ngày tham gia</label><input name="joined_date" class="form-control" inputmode="numeric" placeholder="dd/mm/yyyy"></div><div class="col-md-4"><label class="form-label">Ngày kết thúc</label><input name="ended_date" class="form-control" inputmode="numeric" placeholder="dd/mm/yyyy"></div><div class="col-md-6"><label class="form-label">Trạng thái</label><select name="status" id="communityOrgStatusSelect" class="form-select"></select></div><div class="col-12"><label class="form-label">Ghi chú</label><textarea name="note" class="form-control" rows="3"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Lưu</button></div></form></div></div>' +
      '<div class="modal fade" id="communityOrgDetailModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Chi tiết hội viên</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div id="communityOrgDetailBody" class="modal-body"></div></div></div></div>';
  }

  function injectStyle() {
    if ($('#communityOrgStyle')) return;
    document.head.insertAdjacentHTML('beforeend', '<style id="communityOrgStyle">.community-org-dashboard{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.community-org-card{border:1px solid #d8e2ef;border-radius:8px;padding:14px;background:#fff;display:flex;gap:12px;align-items:center;min-height:92px}.community-org-card i{font-size:22px;color:#087f5b}.community-org-card .label{font-size:13px;color:#475569;line-height:1.35}.community-org-card .value{font-size:28px;font-weight:800;line-height:1.1;color:#0f172a}.community-org-suggestions{position:absolute;z-index:1070;left:12px;right:12px;max-height:260px;overflow:auto}.community-org-status{border-radius:999px;padding:3px 8px;font-size:12px;font-weight:700}.community-org-status.ACTIVE{background:#dcfce7;color:#166534}.community-org-status.PAUSED{background:#fef9c3;color:#854d0e}.community-org-status.TRANSFERRED{background:#dbeafe;color:#1d4ed8}.community-org-status.ENDED,.community-org-status.DECEASED{background:#fee2e2;color:#991b1b}@media(max-width:1199px){.community-org-dashboard{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:767px){.community-org-dashboard{grid-template-columns:repeat(2,minmax(0,1fr))}.community-org-card{min-height:86px;padding:12px}.community-org-card .value{font-size:24px}}@media(max-width:420px){.community-org-dashboard{grid-template-columns:1fr}}</style>');
  }

  function registerPlatform() {
    const p = window.TenantAppPlatform;
    if (!p || registered) return;
    registered = true;
    if (!p.modules?.get?.('communityOrganizations')) p.modules?.register?.({ moduleKey: 'communityOrganizations', screenId: 'communityOrganizations', path: '/community-organizations', label: 'Đoàn thể - Chi hội', mobileLabel: 'Đoàn thể', icon: 'fa-people-group', permissionScope: 'citizen', loaderName: 'loadCommunityOrganizations' });
    if (!p.routes?.match?.('/community-organizations')) p.routes?.register?.({ path: '/community-organizations', moduleKey: 'communityOrganizations', screenId: 'communityOrganizations', action: 'list' });
    const population = p.menus?.get?.('population');
    if (population && !String(population.items || '').includes('communityOrganizations')) {
      const items = population.items || [];
      const idx = items.indexOf('partyMembers');
      const next = items.slice();
      next.splice(idx >= 0 ? idx + 1 : next.length, 0, 'communityOrganizations');
      p.menus.upsert?.(Object.assign({}, population, { items: next }));
    }
    p.menuRenderer?.renderAll?.();
  }

  function registerActions() {
    const actions = window.TenantAppPlatform?.actions;
    if (!actions?.register) return;
    actions.register('communityOrganizations.openCreate', () => openForm());
    actions.register('communityOrganizations.edit', ctx => openForm(Number(ctx.dataset.id || 0)));
    actions.register('communityOrganizations.detail', ctx => openDetail(Number(ctx.dataset.id || 0)));
    actions.register('communityOrganizations.end', ctx => endMembership(Number(ctx.dataset.id || 0)));
    actions.register('communityOrganizations.delete', ctx => remove(Number(ctx.dataset.id || 0)));
    actions.register('communityOrganizations.page', ctx => { state.page = Number(ctx.dataset.page || 1); load(); });
    actions.register('communityOrganizations.reset', resetFilters);
    actions.register('communityOrganizations.selectCitizen', ctx => selectCitizen(state.suggestions.find(x => String(x.id) === ctx.dataset.id)));
    actions.register('communityOrganizations.export', exportExcel);
    actions.register('communityOrganizations.print', printReport);
    actions.register('communityOrganizations.filterOrg', ctx => { const filter = $('#communityOrgFilter'); if (filter) filter.value = ctx.dataset.org || ''; state.page = 1; load(); });
    actions.bind?.(document);
  }

  function bindEvents() {
    $('#communityOrgForm')?.addEventListener('submit', save);
    $('#communityOrgSearch')?.addEventListener('input', debounce(e => { state.search = e.target.value.trim(); state.page = 1; load(); }, 300));
    ['communityOrgFilter','communityOrgStatusFilter','communityOrgAreaFilter'].forEach(id => $('#' + id)?.addEventListener('change', () => { collectFilters(); state.page = 1; load(); }));
    ['communityOrgAgeFrom','communityOrgAgeTo'].forEach(id => $('#' + id)?.addEventListener('input', debounce(() => { collectFilters(); state.page = 1; load(); }, 300)));
    $('#communityOrgPageSize')?.addEventListener('change', e => { state.pageSize = Number(e.target.value || 20); state.page = 1; load(); });
    $('#communityOrgOrgSelect')?.addEventListener('change', e => fillPositions(e.target.value));
    $('#communityOrgCitizenSearch')?.addEventListener('input', debounce(searchCitizens, 250));
    document.addEventListener('pointerdown', e => { if (!e.target.closest('#communityOrgCitizenSuggestions') && e.target.id !== 'communityOrgCitizenSearch') hideSuggestions(); });
  }


  function patchCitizenProfile() {
    if (profilePatched || typeof window.showPerson !== 'function') return;
    const original = window.showPerson;
    profilePatched = true;
    window.showPerson = async function communityOrganizationShowPerson(id) {
      const result = await original.apply(this, arguments);
      appendCitizenOrganizations(Number(id || 0));
      return result;
    };
  }

  async function appendCitizenOrganizations(citizenId) {
    if (!citizenId || !can('read')) return;
    const body = $('#detailBody');
    if (!body || body.querySelector('[data-community-org-profile]')) return;
    try {
      const data = await request(API + '/citizen/' + citizenId);
      const items = data.items || [];
      const html = '<section class="person-info-section mt-3" data-community-org-profile><div class="person-info-section-title"><i class="fa-solid fa-people-group"></i><h4>Đoàn thể - Chi hội</h4></div>' + (items.length ? '<div class="list-group list-group-flush border rounded">' + items.map(item => '<div class="list-group-item"><div class="fw-semibold">' + esc(item.organization_name || '') + ' - ' + esc(item.position_name || 'Thành viên') + '</div><div class="small text-muted">Tham gia từ ' + (date(item.joined_date) || 'chưa cập nhật') + ' - ' + esc(item.status_label || '') + '</div>' + (item.movement_warning ? '<div class="small text-warning">' + esc(item.movement_warning) + '</div>' : '') + '</div>').join('') + '</div>' : '<div class="text-muted small">Chưa có thông tin tham gia đoàn thể/chi hội.</div>') + '</section>';
      body.insertAdjacentHTML('beforeend', html);
    } catch (error) {
      console.warn('[community-organizations] citizen profile link skipped', error);
    }
  }
  async function load() {
    if (!can('read')) return;
    await ensureCatalogs();
    collectFilters();
    const data = await request(API + '?' + params().toString());
    renderRows(data);
    renderPager(data);
    renderDashboard();
  }

  async function ensureCatalogs() {
    if (state.catalogs) return state.catalogs;
    state.catalogs = await request(API + '/catalogs');
    fill('#communityOrgFilter', state.catalogs.organizations, 'Tất cả');
    fill('#communityOrgOrgSelect', state.catalogs.organizations, 'Chọn tổ chức');
    fill('#communityOrgStatusFilter', state.catalogs.statuses, 'Tất cả trạng thái');
    fill('#communityOrgStatusSelect', state.catalogs.statuses, 'Chọn trạng thái');
    fill('#communityOrgAreaFilter', state.catalogs.areas || [], 'Tất cả khu vực');
    fillPositions('');
    return state.catalogs;
  }

  function collectFilters() {
    state.search = $('#communityOrgSearch')?.value.trim() || '';
    state.organization_code = $('#communityOrgFilter')?.value || '';
    state.status = $('#communityOrgStatusFilter')?.value || '';
    state.area_code = $('#communityOrgAreaFilter')?.value || '';
    state.age_from = $('#communityOrgAgeFrom')?.value || '';
    state.age_to = $('#communityOrgAgeTo')?.value || '';
  }

  function params() {
    const p = new URLSearchParams({ page: state.page, pageSize: state.pageSize });
    ['search','organization_code','status','gender','area_code','position_id','age_from','age_to','joined_year'].forEach(k => { if (state[k]) p.set(k, state[k]); });
    return p;
  }

  async function renderDashboard() {
    const data = await request(API + '/dashboard?' + params().toString());
    const host = $('#communityOrgDashboard');
    const cards = [{ label: 'Tổng thành viên đang tham gia', value: data.metrics?.total_active_members || 0, icon: 'fa-users' }].concat((data.organizations || []).map(o => ({ label: o.name, value: o.active_count || 0, icon: iconFor(o.code), code: o.code })));
    host.innerHTML = cards.map(c => '<button type="button" class="community-org-card text-start" ' + (c.code ? 'data-platform-action="communityOrganizations.filterOrg" data-org="' + esc(c.code) + '"' : '') + '><i class="fa-solid ' + c.icon + '"></i><span><span class="label d-block">' + esc(c.label) + '</span><span class="value d-block">' + fmt(c.value) + '</span></span></button>').join('');
    const warnings = data.warnings || [];
    $('#communityOrgWarnings').innerHTML = warnings.length ? warnings.map(w => '<div class="alert alert-warning mb-2"><i class="fa-solid fa-triangle-exclamation me-2"></i>' + esc(w.message || '') + '</div>').join('') : '';
  }

  function renderRows(data) {
    const rows = data.items || [];
    $('#communityOrgTotal').textContent = 'Tổng số: ' + fmt(data.total || 0) + ' thành viên';
    $('#communityOrgRows').innerHTML = rows.length ? rows.map((r, i) => '<tr><td data-label="STT">' + fmt(((data.page || 1) - 1) * (data.pageSize || 20) + i + 1) + '</td><td data-label="Họ tên"><button class="btn btn-link p-0 fw-bold" data-platform-action="communityOrganizations.detail" data-id="' + r.id + '">' + esc(r.full_name) + '</button><div class="small text-muted">' + esc(r.citizen_code || '') + '</div>' + (r.movement_warning ? '<div class="small text-warning">' + esc(r.movement_warning) + '</div>' : '') + '</td><td data-label="Ngày sinh">' + date(r.date_of_birth) + '</td><td data-label="Tuổi">' + esc(r.age || '') + '</td><td data-label="Giới tính">' + esc(r.gender || '') + '</td><td data-label="Mã hộ">' + esc(r.household_code || '') + '</td><td data-label="Khu vực">' + esc(r.area_code || '') + '</td><td data-label="Tổ chức">' + esc(r.organization_name || '') + '</td><td data-label="Chức vụ">' + esc(r.position_name || '') + '</td><td data-label="Ngày tham gia">' + date(r.joined_date) + '</td><td data-label="Trạng thái"><span class="community-org-status ' + esc(r.status || '') + '">' + esc(r.status_label || r.status || '') + '</span></td><td data-label="Thao tác" class="text-end"><div class="btn-group btn-group-sm"><button class="btn btn-outline-secondary" data-platform-action="communityOrganizations.detail" data-id="' + r.id + '" title="Xem"><i class="fa-solid fa-eye"></i></button>' + (can('update') ? '<button class="btn btn-outline-primary" data-platform-action="communityOrganizations.edit" data-id="' + r.id + '" title="Sửa"><i class="fa-solid fa-pen"></i></button><button class="btn btn-outline-warning" data-platform-action="communityOrganizations.end" data-id="' + r.id + '" title="Thôi tham gia"><i class="fa-solid fa-user-minus"></i></button>' : '') + (can('delete') ? '<button class="btn btn-outline-danger" data-platform-action="communityOrganizations.delete" data-id="' + r.id + '" title="Xóa"><i class="fa-solid fa-trash"></i></button>' : '') + '</div></td></tr>').join('') : '<tr><td colspan="12" class="text-center text-muted py-4">Chưa có thành viên đoàn thể - chi hội</td></tr>';
    if (typeof window.TenantAppSyncResponsiveTableLabels === 'function') window.TenantAppSyncResponsiveTableLabels($('#communityOrganizationsScreen'));
  }

  function renderPager(data) {
    const totalPages = Number(data.totalPages || 1), page = Number(data.page || 1);
    $('#communityOrgPager').innerHTML = '<button class="btn btn-sm btn-outline-secondary" ' + (page <= 1 ? 'disabled' : '') + ' data-platform-action="communityOrganizations.page" data-page="' + (page - 1) + '">Trước</button><span class="align-self-center small">Trang ' + fmt(page) + '/' + fmt(totalPages) + '</span><button class="btn btn-sm btn-outline-secondary" ' + (page >= totalPages ? 'disabled' : '') + ' data-platform-action="communityOrganizations.page" data-page="' + (page + 1) + '">Sau</button>';
  }

  async function openForm(id = 0) {
    if (!can(id ? 'update' : 'create')) return toast('Tài khoản hiện tại không có quyền thực hiện thao tác này', 'warning');
    await ensureCatalogs();
    state.editingId = id;
    state.selectedCitizen = null;
    const form = $('#communityOrgForm');
    form.reset(); form.elements.id.value = id || ''; form.elements.citizen_id.value = '';
    $('#communityOrgSelectedCitizen').textContent = '';
    $('#communityOrgCitizenSearch').disabled = !!id;
    if (id) {
      const row = await request(API + '/' + id);
      fillForm(form, row);
      state.selectedCitizen = { id: row.citizen_id, full_name: row.full_name, citizen_code: row.citizen_code };
      form.elements.citizen_id.value = row.citizen_id;
      $('#communityOrgCitizenSearch').value = [row.full_name, row.citizen_code].filter(Boolean).join(' - ');
      $('#communityOrgSelectedCitizen').textContent = [row.full_name, row.citizen_code, row.household_code].filter(Boolean).join(' - ');
      fillPositions(row.organization_code, row.position_id);
    } else {
      fillPositions($('#communityOrgOrgSelect').value);
    }
    bootstrap.Modal.getOrCreateInstance($('#communityOrgModal')).show();
  }

  async function save(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.elements.citizen_id.value) return toast('Vui lòng chọn nhân khẩu từ danh sách.', 'warning');
    const payload = Object.fromEntries(new FormData(form).entries());
    const id = Number(payload.id || 0);
    const row = await request(API + (id ? '/' + id : ''), { method: id ? 'PUT' : 'POST', body: JSON.stringify(payload) });
    bootstrap.Modal.getOrCreateInstance($('#communityOrgModal')).hide();
    toast('Đã lưu thông tin đoàn thể - chi hội', 'success');
    load();
    return row;
  }

  async function searchCitizens() {
    const q = $('#communityOrgCitizenSearch').value.trim();
    const org = $('#communityOrgOrgSelect').value || '';
    if (q.length < 2) return hideSuggestions();
    state.suggestions = (await request(API + '/citizen-search?q=' + encodeURIComponent(q) + '&organization_code=' + encodeURIComponent(org))).items || [];
    const box = $('#communityOrgCitizenSuggestions');
    box.innerHTML = state.suggestions.length ? state.suggestions.map(x => '<button type="button" class="list-group-item list-group-item-action" data-platform-action="communityOrganizations.selectCitizen" data-id="' + x.id + '" ' + (x.has_current_membership ? 'disabled' : '') + '><strong>' + esc(x.full_name) + '</strong><div class="small text-muted">' + esc([x.citizen_code, x.household_code, x.address].filter(Boolean).join(' - ')) + '</div>' + (x.has_current_membership ? '<div class="small text-danger">Nhân khẩu này đang tham gia tổ chức đã chọn</div>' : '') + '</button>').join('') : '<div class="list-group-item text-muted">Không tìm thấy nhân khẩu phù hợp</div>';
  }

  function selectCitizen(item) {
    if (!item || item.has_current_membership) return;
    state.selectedCitizen = item;
    $('#communityOrgForm').elements.citizen_id.value = item.id;
    $('#communityOrgCitizenSearch').value = [item.full_name, item.citizen_code].filter(Boolean).join(' - ');
    $('#communityOrgSelectedCitizen').textContent = [item.full_name, item.citizen_code, item.household_code, item.address].filter(Boolean).join(' - ');
    hideSuggestions();
  }

  async function openDetail(id) {
    const row = await request(API + '/' + id);
    const history = (await request(API + '/' + id + '/history')).items || [];
    $('#communityOrgDetailBody').innerHTML = '<dl class="row"><dt class="col-sm-4">Nhân khẩu</dt><dd class="col-sm-8">' + esc([row.full_name, row.citizen_code].filter(Boolean).join(' - ')) + '</dd><dt class="col-sm-4">Tổ chức</dt><dd class="col-sm-8">' + esc(row.organization_name || '') + '</dd><dt class="col-sm-4">Chức vụ</dt><dd class="col-sm-8">' + esc(row.position_name || '') + '</dd><dt class="col-sm-4">Trạng thái</dt><dd class="col-sm-8">' + esc(row.status_label || '') + '</dd><dt class="col-sm-4">Ghi chú</dt><dd class="col-sm-8">' + esc(row.note || '') + '</dd></dl><h6>Lịch sử thay đổi</h6>' + (history.length ? '<ul class="list-group">' + history.map(h => '<li class="list-group-item"><strong>' + esc(h.change_type || '') + '</strong><div class="small text-muted">' + dateTime(h.changed_at) + '</div><div>' + esc(h.note || '') + '</div></li>').join('') + '</ul>' : '<div class="text-muted">Chưa có lịch sử thay đổi.</div>');
    bootstrap.Modal.getOrCreateInstance($('#communityOrgDetailModal')).show();
  }

  async function endMembership(id) {
    if (!confirm('Xác nhận thôi tham gia tổ chức này?')) return;
    await request(API + '/' + id + '/end', { method: 'PUT', body: JSON.stringify({ status: 'ENDED', ended_date: today() }) });
    toast('Đã cập nhật trạng thái thôi tham gia', 'success'); load();
  }

  async function remove(id) {
    if (!confirm('Xóa hồ sơ đoàn thể - chi hội này? Dữ liệu nhân khẩu gốc không bị xóa.')) return;
    await request(API + '/' + id, { method: 'DELETE' });
    toast('Đã xóa hồ sơ đoàn thể - chi hội', 'success'); load();
  }

  function resetFilters() { ['communityOrgSearch','communityOrgAgeFrom','communityOrgAgeTo'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; }); ['communityOrgFilter','communityOrgStatusFilter','communityOrgAreaFilter'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; }); state.page = 1; load(); }
  async function exportExcel() { const data = await request(API + '/report?' + params().toString()); const rows = data.items || []; const html = '<table><tr><th>Họ tên</th><th>Mã nhân khẩu</th><th>Mã hộ</th><th>Tổ chức</th><th>Chức vụ</th><th>Ngày tham gia</th><th>Trạng thái</th></tr>' + rows.map(r => '<tr><td>' + esc(r.full_name) + '</td><td>' + esc(r.citizen_code) + '</td><td>' + esc(r.household_code) + '</td><td>' + esc(r.organization_name) + '</td><td>' + esc(r.position_name) + '</td><td>' + date(r.joined_date) + '</td><td>' + esc(r.status_label) + '</td></tr>').join('') + '</table>'; download('bao-cao-doan-the-chi-hoi.xls', html); }
  async function printReport() { const data = await request(API + '/report?' + params().toString()); const rows = data.items || []; const html = '<h2>BÁO CÁO ĐOÀN THỂ - CHI HỘI</h2><table border="1" cellspacing="0" cellpadding="6" style="width:100%;border-collapse:collapse"><tr><th>STT</th><th>Họ tên</th><th>Mã NK</th><th>Mã hộ</th><th>Tổ chức</th><th>Chức vụ</th><th>Ngày tham gia</th><th>Trạng thái</th></tr>' + rows.map((r,i) => '<tr><td>' + (i+1) + '</td><td>' + esc(r.full_name) + '</td><td>' + esc(r.citizen_code) + '</td><td>' + esc(r.household_code) + '</td><td>' + esc(r.organization_name) + '</td><td>' + esc(r.position_name) + '</td><td>' + date(r.joined_date) + '</td><td>' + esc(r.status_label) + '</td></tr>').join('') + '</table>'; const w = window.open('', '_blank'); w.document.write('<!doctype html><meta charset="utf-8"><title>Báo cáo đoàn thể</title>' + html); w.document.close(); w.print(); }

  function fill(selector, items, allLabel) { const el = typeof selector === 'string' ? $(selector) : selector; if (!el) return; el.innerHTML = (allLabel ? '<option value="">' + esc(allLabel) + '</option>' : '') + (items || []).map(i => '<option value="' + esc(i.value ?? i.code ?? i.id) + '">' + esc(i.label ?? i.name) + '</option>').join(''); }
  function fillPositions(code, selected) { const list = (state.catalogs?.positions || []).filter(p => !code || p.organization_code === code); fill('#communityOrgPositionSelect', list, 'Chọn chức vụ'); if (selected) $('#communityOrgPositionSelect').value = selected; }
  function fillForm(form, row) { Object.keys(row || {}).forEach(k => { if (form.elements[k]) form.elements[k].value = ['joined_date','ended_date'].includes(k) ? date(row[k]) : (row[k] ?? ''); }); if (form.elements.organization_code) form.elements.organization_code.value = row.organization_code || ''; }
  function hideSuggestions() { const box = $('#communityOrgCitizenSuggestions'); if (box) box.innerHTML = ''; }
  function can(action) {
    if (window.TenantAppPlatform?.permissions?.can?.('organizations', action)) return true;
    const role = String(window.App?.user?.role || window.TenantAppPlatform?.state?.user?.role || '').toUpperCase();
    if (role === 'SUPER_ADMIN' || role === 'ADMIN') return true;
    if (role === 'VIEWER') return action === 'read';
    if (role === 'OFFICER') return ['read','create','update','export','print'].includes(action);
    return window.TenantAppPlatform?.permissions?.can?.('citizen', 'read') === true && action === 'read';
  }
  async function request(url, options = {}) {
    const method = String(options.method || 'GET').toUpperCase();
    const headers = Object.assign({ Accept: 'application/json' }, options.headers || {});
    const body = options.body;
    if (body !== undefined && !(body instanceof FormData)) headers['Content-Type'] = headers['Content-Type'] || 'application/json';
    const token = window.App?.token || storageGet(storageKey('token')) || '';
    const csrf = window.App?.csrfToken || storageGet(storageKey('csrf')) || '';
    if (token) headers.Authorization = 'Bearer ' + token;
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && csrf) headers['X-CSRF-Token'] = csrf;
    const response = await fetch(url, { method, headers, credentials: 'same-origin', body: body instanceof FormData ? body : body });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.ok === false) throw new Error(payload.error?.message || payload.message || 'Lỗi API');
    return payload.data ?? payload;
  }

  function storageKey(key) {
    if (typeof window.tenantStorageKey === 'function') return window.tenantStorageKey(key);
    return 'tenant_app_' + key;
  }

  function storageGet(key) {
    try { return window.localStorage?.getItem?.(key) || ''; } catch (_) { return ''; }
  }
  function toast(message, type = 'info') { if (window.TenantAppPlatform?.toast) return window.TenantAppPlatform.toast(message, type); if (window.showToast) return window.showToast(message, type); alert(message); }
  function debounce(fn, wait) { let t; return function () { clearTimeout(t); const args = arguments; t = setTimeout(() => fn.apply(this, args), wait); }; }
  function esc(v) { return String(v ?? '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c])); }
  function fmt(v) { return new Intl.NumberFormat('vi-VN').format(Number(v || 0)); }
  function date(v) { if (!v) return ''; const m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? m[3] + '/' + m[2] + '/' + m[1] : String(v); }
  function dateTime(v) { return v ? date(String(v).slice(0,10)) + ' ' + String(v).slice(11,16) : ''; }
  function today() { const d = new Date(); return String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0') + '/' + d.getFullYear(); }
  function iconFor(code) { return { WOMEN: 'fa-venus', FARMER: 'fa-seedling', VETERAN: 'fa-medal', YOUTH: 'fa-people-arrows' }[code] || 'fa-people-group'; }
  function download(name, html) { const blob = new Blob(['\ufeff' + html], { type: 'application/vnd.ms-excel;charset=utf-8' }); const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; a.click(); URL.revokeObjectURL(a.href); }
  window.loadCommunityOrganizations = load;
})();
