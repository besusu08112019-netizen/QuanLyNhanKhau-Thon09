(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const API = '/api/party-members';
  const state = { page: 1, pageSize: 20, search: '', branch_name: '', member_type: '', activity_status: '', party_position: '', gender: '', age_from: '', age_to: '', sort: 'full_name', direction: 'ASC', catalogs: null, suggestions: [] };
  const labels = { total: 'Tổng số', records: 'hồ sơ', loading: 'Đang tải...', noData: 'Chưa có hồ sơ Đảng viên', permission: 'Tài khoản hiện tại không có quyền thực hiện thao tác này' };
  let platformRegistered = false;

  registerPlatform();
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('tenant:screen-change', e => { if (e.detail?.screen === 'partyMembers') load(); });

  function init() {
    registerPlatform();
    registerModal('partyMemberModal');
    registerModal('partyMemberDetailModal');
    $('#partyMemberForm')?.addEventListener('submit', save);
    $('#partyMemberSearch')?.addEventListener('input', debounce(() => { state.search = $('#partyMemberSearch').value.trim(); state.page = 1; load(); }, 300));
    [['partyMemberBranchFilter', 'branch_name'], ['partyMemberTypeFilter', 'member_type'], ['partyMemberStatusFilter', 'activity_status'], ['partyMemberPositionFilter', 'party_position'], ['partyMemberGenderFilter', 'gender']].forEach(([id, key]) => $('#' + id)?.addEventListener('change', e => { state[key] = e.target.value; state.page = 1; load(); }));
    [['partyMemberAgeFrom', 'age_from'], ['partyMemberAgeTo', 'age_to']].forEach(([id, key]) => $('#' + id)?.addEventListener('input', debounce(e => { state[key] = e.target.value; state.page = 1; load(); }, 350)));
    $('#partyMemberPageSize')?.addEventListener('change', e => { state.pageSize = Number(e.target.value || 20); state.page = 1; load(); });
    $('#partyCitizenAutocomplete')?.addEventListener('input', debounce(searchCitizens, 250));
    document.addEventListener('pointerdown', e => { if (!e.target.closest('#partyCitizenSuggestions') && e.target.id !== 'partyCitizenAutocomplete') hideSuggestions(); });
    registerActions();
    if ($('#partyMembersScreen')?.classList.contains('active')) load();
  }

  function registerPlatform() {
    if (platformRegistered) return;
    const p = window.TenantAppPlatform;
    if (!p) return;
    platformRegistered = true;
    if (!p.modules?.get?.('partyMembers')) {
      p.modules?.register?.({ moduleKey: 'partyMembers', screenId: 'partyMembers', path: '/party-members', label: 'Quản lý Đảng viên', mobileLabel: 'Đảng viên', icon: 'fa-flag', permissionScope: 'party_members', loaderName: 'loadPartyMembers' });
    }
    if (!p.routes?.match?.('/party-members')) {
      p.routes?.register?.({ path: '/party-members', moduleKey: 'partyMembers', screenId: 'partyMembers', action: 'list' });
    }
    const population = p.menus?.get?.('population');
    if (population && !String(population.items || '').includes('partyMembers')) {
      p.menus.upsert?.(Object.assign({}, population, { items: [...(population.items || []), 'partyMembers'] }));
    }
    p.menuRenderer?.renderAll?.();
  }

  function registerActions() {
    const actions = window.TenantAppPlatform?.actions;
    if (!actions?.register) return;
    actions.register('partyMembers.openCreate', () => openForm());
    actions.register('partyMembers.search', () => { collectFilters(); state.page = 1; load(); });
    actions.register('partyMembers.reset', resetFilters);
    actions.register('partyMembers.sort', context => sortBy(context.dataset.partySort));
    actions.register('partyMembers.page', context => { state.page = Number(context.dataset.page || 1); load(); });
    actions.register('partyMembers.detail', context => openDetail(Number(context.dataset.id || 0)));
    actions.register('partyMembers.edit', context => openForm(Number(context.dataset.id || 0)));
    actions.register('partyMembers.delete', context => remove(Number(context.dataset.id || 0)));
    actions.register('partyMembers.selectCitizen', context => selectCitizen(state.suggestions.find(x => String(x.id) === context.dataset.id)));
    actions.register('partyMembers.export', context => exportReport(context.dataset.format || 'excel'));
    actions.register('partyMembers.print', () => printReport());
    actions.bind?.(document);
  }

  async function load() {
    if (!can('read')) return;
    await ensureCatalogs();
    collectFilters();
    try {
      const data = await request(API + '?' + params().toString(), { cacheTtl: 3000 });
      renderRows(data);
      renderPager(data);
      renderDashboard();
    } catch (e) {
      toast(e.message, 'danger');
    }
  }

  async function ensureCatalogs() {
    if (state.catalogs) return state.catalogs;
    state.catalogs = await request(API + '/catalogs', { cacheTtl: 60000 });
    fill('#partyMemberTypeFilter', state.catalogs.member_types, 'Tất cả');
    fill('#partyMemberTypeSelect', state.catalogs.member_types, 'Chọn loại');
    fill('#partyMemberStatusFilter', state.catalogs.statuses, 'Tất cả');
    fill('#partyMemberActivityStatusSelect', state.catalogs.statuses, 'Chọn tình trạng');
    fill('#partyMemberBranchFilter', state.catalogs.branches, 'Tất cả');
    fill('#partyMemberPositionFilter', state.catalogs.positions, 'Tất cả');
    return state.catalogs;
  }

  async function renderDashboard() {
    const host = $('#partyMembersDashboard');
    if (!host) return;
    try {
      const data = await request(API + '/dashboard?' + params().toString(), { cacheTtl: 5000 });
      const m = data.metrics || {};
      const cards = [
        ['Tổng Đảng viên', m.total, 'fa-flag', 'red'],
        ['Chính thức', m.official, 'fa-id-badge', 'green'],
        ['Dự bị', m.probationary, 'fa-user-clock', 'orange'],
        ['Nam', m.male, 'fa-mars', 'blue'],
        ['Nữ', m.female, 'fa-venus', 'pink'],
        ['Nghỉ hưu', m.retired, 'fa-person-cane', 'gray'],
        ['Miễn sinh hoạt', m.exempt, 'fa-circle-pause', 'orange'],
        ['Chuyển sinh hoạt', m.transferred, 'fa-right-left', 'blue'],
        ['Đến kỳ Huy hiệu', m.badge_due, 'fa-award', 'green']
      ];
      host.innerHTML = cards.map(c => '<article class="party-kpi-card tone-' + c[3] + '"><span class="app-v2-card-icon party-kpi-icon"><i class="fa-solid ' + c[2] + '"></i></span><div><p>' + esc(c[0]) + '</p><strong>' + num(c[1]) + '</strong></div></article>').join('');
    } catch (_) {
      host.innerHTML = '';
    }
  }

  function renderRows(data) {
    const host = $('#partyMemberRows');
    if (!host) return;
    const items = data.items || [];
    $('#partyMemberTotalCount') && ($('#partyMemberTotalCount').textContent = labels.total + ': ' + num(data.total || 0) + ' ' + labels.records);
    host.innerHTML = items.length ? items.map(rowHtml).join('') : '<tr><td colspan="9" class="text-center text-muted py-4">' + labels.noData + '</td></tr>';
    if (typeof window.TenantAppSyncResponsiveTableLabels === 'function') window.TenantAppSyncResponsiveTableLabels($('#partyMembersScreen') || document);
  }

  function rowHtml(r) {
    const id = Number(r.id);
    return '<tr><td data-label="Ảnh">' + avatar(r) + '</td><td data-label="Họ tên"><strong>' + esc(r.full_name) + '</strong><div class="text-muted small">' + esc(r.citizen_code || '') + ' - ' + esc(r.gender || '') + ', ' + esc(r.age || '') + ' tuổi</div><div class="text-muted small">' + esc(r.address || '') + '</div></td><td data-label="Mã ĐV">' + esc(r.party_member_code || '') + '<div class="text-muted small">' + esc(r.party_card_number || '') + '</div></td><td data-label="Chi bộ">' + esc(r.branch_name || '') + '</td><td data-label="Chức vụ">' + esc(r.party_position || '') + '</td><td data-label="Loại"><span class="badge-soft">' + esc(r.member_type_label || '') + '</span></td><td data-label="Tình trạng"><span class="badge-soft">' + esc(r.activity_status_label || '') + '</span></td><td data-label="Ngày vào Đảng">' + esc(date(r.joined_party_date)) + '</td><td data-label="Thao tác" class="text-end"><div class="party-row-actions"><button class="btn btn-sm btn-outline-secondary" type="button" title="Xem" aria-label="Xem chi tiết" data-platform-action="partyMembers.detail" data-id="' + id + '"><i class="fa-solid fa-eye"></i></button>' + (can('update') ? '<button class="btn btn-sm btn-outline-primary" type="button" title="Sửa" aria-label="Sửa" data-platform-action="partyMembers.edit" data-id="' + id + '"><i class="fa-solid fa-pen"></i></button>' : '') + (can('delete') ? '<button class="btn btn-sm btn-outline-danger" type="button" title="Xóa" aria-label="Xóa" data-platform-action="partyMembers.delete" data-id="' + id + '"><i class="fa-solid fa-trash"></i></button>' : '') + '</div></td></tr>';
  }

  function renderPager(data) {
    const host = $('#partyMemberPager');
    if (!host) return;
    const page = Number(data.page || 1), totalPages = Number(data.totalPages || 1);
    const pages = [];
    for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) pages.push(i);
    host.innerHTML = '<button class="btn btn-sm btn-outline-secondary" ' + (page <= 1 ? 'disabled' : '') + ' data-platform-action="partyMembers.page" data-page="' + (page - 1) + '">Trước</button>' + pages.map(p => '<button class="btn btn-sm ' + (p === page ? 'btn-primary' : 'btn-outline-secondary') + '" data-platform-action="partyMembers.page" data-page="' + p + '">' + p + '</button>').join('') + '<button class="btn btn-sm btn-outline-secondary" ' + (page >= totalPages ? 'disabled' : '') + ' data-platform-action="partyMembers.page" data-page="' + (page + 1) + '">Sau</button>';
  }

  async function openForm(id = null) {
    if (!can(id ? 'update' : 'create')) return toast(labels.permission, 'warning');
    await ensureCatalogs();
    const form = $('#partyMemberForm');
    if (!form) return;
    form.reset();
    form.classList.remove('was-validated');
    form.elements.id.value = '';
    form.elements.citizen_id.value = '';
    $('#partyCitizenAutocomplete').disabled = false;
    $('#partyCitizenAutocomplete').value = '';
    $('#partyCitizenSelected').textContent = '';
    hideSuggestions();
    if (id) {
      const row = await request(API + '/' + encodeURIComponent(id));
      setForm(row);
      $('#partyCitizenAutocomplete').disabled = true;
    }
    openModal('partyMemberModal');
  }

  function setForm(row) {
    const form = $('#partyMemberForm');
    const map = { id: 'id', citizen_id: 'citizen_id', citizen_code: 'citizen_code', full_name: 'full_name', date_of_birth: 'date_of_birth', gender: 'gender', identity_number: 'identity_number', address: 'address', party_member_code: 'party_member_code', party_card_number: 'party_card_number', joined_party_date: 'joined_party_date', official_party_date: 'official_party_date', branch_name: 'branch_name', parent_party_org: 'parent_party_org', party_position: 'party_position', government_position: 'government_position', education_level: 'education_level', professional_level: 'professional_level', political_theory_level: 'political_theory_level', member_type: 'member_type', activity_status: 'activity_status', note: 'note' };
    Object.entries(map).forEach(([key, name]) => { if (form.elements[name]) form.elements[name].value = row[key] ?? ''; });
    if (form.elements.phone_display) form.elements.phone_display.value = row.phone || '';
    $('#partyCitizenAutocomplete').value = (row.citizen_code || '') + ' - ' + (row.full_name || '');
    $('#partyCitizenSelected').textContent = row.address || '';
  }

  async function openDetail(id) {
    if (!can('read')) return toast(labels.permission, 'warning');
    if (!id) return;
    try {
      const row = await request(API + '/' + encodeURIComponent(id));
      renderDetail(row);
      openModal('partyMemberDetailModal');
    } catch (e) {
      toast(e.message, 'danger');
    }
  }

  function renderDetail(row) {
    const body = $('#partyMemberDetailBody');
    if (!body) return;
    const title = $('#partyMemberDetailTitle');
    const subtitle = $('#partyMemberDetailSubtitle');
    if (title) title.textContent = row.full_name || 'Chi tiết Đảng viên';
    if (subtitle) subtitle.textContent = [row.citizen_code, row.party_member_code, row.branch_name].filter(Boolean).join(' - ');
    body.innerHTML =
      '<section class="party-detail-hero">' + avatar(row) + '<div><h3>' + esc(row.full_name || '') + '</h3><p>' + esc([row.citizen_code, row.gender, row.age ? row.age + ' tuổi' : ''].filter(Boolean).join(' - ')) + '</p></div></section>' +
      '<div class="party-detail-grid">' +
      detailCard('Thông tin nhân khẩu', 'fa-id-card', [
        ['Mã nhân khẩu', row.citizen_code], ['Mã đảng viên', row.party_member_code], ['Số thẻ đảng viên', row.party_card_number], ['Ngày sinh', date(row.date_of_birth)], ['Giới tính', row.gender], ['CCCD', row.identity_number], ['Địa chỉ', row.address], ['Điện thoại', row.phone]
      ]) +
      detailCard('Thông tin Đảng', 'fa-flag', [
        ['Chi bộ', row.branch_name], ['Đảng bộ', row.parent_party_org], ['Ngày vào Đảng', date(row.joined_party_date)], ['Ngày chính thức', date(row.official_party_date)], ['Loại đảng viên', row.member_type_label], ['Tình trạng', row.activity_status_label], ['Chức vụ Đảng', row.party_position], ['Chức vụ chính quyền', row.government_position]
      ]) +
      detailCard('Trình độ', 'fa-graduation-cap', [
        ['Học vấn', row.education_level || row.citizen_education_level], ['Chuyên môn', row.professional_level], ['Lý luận chính trị', row.political_theory_level]
      ]) +
      detailCard('Thông tin khác', 'fa-note-sticky', [
        ['Ghi chú', row.note]
      ], true) +
      '</div>';
  }

  function detailCard(title, icon, items, wide = false) {
    return '<article class="party-detail-card' + (wide ? ' party-detail-wide' : '') + '"><h4><i class="fa-solid ' + esc(icon) + '"></i>' + esc(title) + '</h4>' + items.map(item => '<div class="party-detail-row"><span>' + esc(item[0]) + '</span><strong>' + esc(value(item[1])) + '</strong></div>').join('') + '</article>';
  }

  async function searchCitizens() {
    const input = $('#partyCitizenAutocomplete'), host = $('#partyCitizenSuggestions'), form = $('#partyMemberForm');
    if (!input || !host || !form) return;
    const q = input.value.trim();
    form.elements.citizen_id.value = '';
    state.suggestions = [];
    if (q.length < 2) { hideSuggestions(); return; }
    const data = await request(API + '/citizen-search?q=' + encodeURIComponent(q), { cacheTtl: 3000 });
    const items = data.items || [];
    state.suggestions = items;
    host.innerHTML = items.length ? items.map(item => '<button type="button" class="list-group-item list-group-item-action" data-platform-action="partyMembers.selectCitizen" data-id="' + Number(item.id) + '"><strong>' + esc(item.full_name) + '</strong><div class="small text-muted">' + esc(item.citizen_code) + ' - ' + esc(item.identity_number || '') + '</div><div class="small text-muted">' + esc(item.address || '') + '</div></button>').join('') : '<div class="list-group-item text-muted">Không tìm thấy nhân khẩu phù hợp hoặc nhân khẩu đã có hồ sơ Đảng viên</div>';
    host.classList.remove('d-none');
  }

  function selectCitizen(item) {
    if (!item) return;
    const form = $('#partyMemberForm');
    form.elements.citizen_id.value = item.id;
    form.elements.citizen_code.value = item.citizen_code || '';
    form.elements.full_name.value = item.full_name || '';
    form.elements.date_of_birth.value = item.date_of_birth || '';
    form.elements.gender.value = item.gender || '';
    form.elements.identity_number.value = item.identity_number || '';
    form.elements.phone_display.value = item.phone || '';
    form.elements.address.value = item.address || '';
    $('#partyCitizenAutocomplete').value = (item.citizen_code || '') + ' - ' + (item.full_name || '');
    $('#partyCitizenSelected').textContent = item.address || '';
    hideSuggestions();
  }

  async function save(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const id = form.elements.id.value;
    if (!form.elements.citizen_id.value) return toast('Vui lòng chọn nhân khẩu', 'warning');
    const payload = Object.fromEntries(new FormData(form).entries());
    delete payload.id;
    delete payload.citizen_code; delete payload.full_name; delete payload.date_of_birth; delete payload.gender; delete payload.identity_number; delete payload.phone_display; delete payload.address;
    try {
      await request(id ? API + '/' + encodeURIComponent(id) : API, { method: id ? 'PUT' : 'POST', body: payload });
      closeModal('partyMemberModal');
      toast('Đã lưu hồ sơ Đảng viên');
      state.catalogs = null;
      load();
      if (typeof window.loadDashboard === 'function') window.loadDashboard();
    } catch (err) {
      toast(err.message, 'danger');
    }
  }

  async function remove(id) {
    if (!can('delete')) return toast(labels.permission, 'warning');
    const ok = await confirmAction({ title: 'Xác nhận xóa', message: 'Xóa hồ sơ Đảng viên này?', confirmLabel: 'Xóa', tone: 'danger' });
    if (!ok) return;
    try {
      await request(API + '/' + encodeURIComponent(id), { method: 'DELETE' });
      toast('Đã xóa hồ sơ Đảng viên');
      load();
      if (typeof window.loadDashboard === 'function') window.loadDashboard();
    } catch (e) {
      toast(e.message, 'danger');
    }
  }

  async function exportReport(format) {
    const q = params();
    q.set('type', 'party-members');
    const endpoint = format === 'pdf' ? '/api/reports/export-pdf' : '/api/reports/export-excel';
    try {
      await downloadFile(endpoint + '?' + q.toString(), format === 'pdf' ? 'pdf' : 'xlsx');
      toast(format === 'pdf' ? 'Đã tải file PDF' : 'Đã tải file Excel');
    } catch (e) {
      toast(e.message, 'danger');
    }
  }

  async function printReport() {
    const q = params();
    q.set('type', 'party-members');
    try {
      const data = await request('/api/reports/print?' + q.toString());
      const printer = window.TenantAppPrint;
      if (!printer?.render) return toast('Không tải được mẫu in báo cáo', 'warning');
      const popup = printer.render(Object.assign({}, data, { type: 'party-members', filters: Object.assign({}, data.filters || {}, Object.fromEntries(q.entries())), orientation: 'portrait', paperSize: 'A4' }));
      if (!popup) toast('Trình duyệt đang chặn cửa sổ in', 'warning');
    } catch (e) {
      toast(e.message, 'danger');
    }
  }

  function collectFilters() {
    state.search = $('#partyMemberSearch')?.value.trim() || '';
    state.branch_name = $('#partyMemberBranchFilter')?.value || '';
    state.member_type = $('#partyMemberTypeFilter')?.value || '';
    state.activity_status = $('#partyMemberStatusFilter')?.value || '';
    state.party_position = $('#partyMemberPositionFilter')?.value || '';
    state.gender = $('#partyMemberGenderFilter')?.value || '';
    state.age_from = $('#partyMemberAgeFrom')?.value || '';
    state.age_to = $('#partyMemberAgeTo')?.value || '';
  }

  function resetFilters() {
    Object.assign(state, { page: 1, search: '', branch_name: '', member_type: '', activity_status: '', party_position: '', gender: '', age_from: '', age_to: '' });
    ['partyMemberSearch','partyMemberBranchFilter','partyMemberTypeFilter','partyMemberStatusFilter','partyMemberPositionFilter','partyMemberGenderFilter','partyMemberAgeFrom','partyMemberAgeTo'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
    load();
  }

  function params() {
    const p = new URLSearchParams({ page: state.page, pageSize: state.pageSize, sort: state.sort, direction: state.direction });
    ['search','branch_name','member_type','activity_status','party_position','gender','age_from','age_to'].forEach(k => { if (state[k]) p.set(k, state[k]); });
    return p;
  }

  function sortBy(key) {
    if (!key) return;
    if (state.sort === key) state.direction = state.direction === 'ASC' ? 'DESC' : 'ASC';
    else { state.sort = key; state.direction = 'ASC'; }
    load();
  }

  function fill(selector, items, first) {
    const el = $(selector);
    if (!el) return;
    const value = el.value;
    el.innerHTML = '<option value="">' + esc(first || 'Chọn') + '</option>' + (items || []).map(i => '<option value="' + esc(i.value) + '">' + esc(i.label || i.value) + '</option>').join('');
    el.value = value;
  }

  async function request(url, options = {}) {
    if (typeof window.api === 'function') return window.api(url, options);
    const token = window.App?.token || localStorage.getItem(tenantStorageKey('token')) || '';
    const init = { method: options.method || 'GET', headers: { Accept: 'application/json', Authorization: token ? 'Bearer ' + token : '' } };
    if (options.body) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(options.body); }
    const res = await fetch(url, init);
    const json = await res.json().catch(() => null);
    if (!res.ok || json?.ok === false) throw new Error(json?.error?.message || 'Không tải được dữ liệu');
    return json?.data ?? json;
  }

  function downloadFile(url, extension) {
    if (window.TenantAppExport?.download) return window.TenantAppExport.download(url, { extension });
    const token = window.App?.token || localStorage.getItem(tenantStorageKey('token')) || '';
    return fetch(url, { headers: { Authorization: 'Bearer ' + token }, cache: 'no-store' }).then(async res => {
      const type = res.headers.get('Content-Type') || '';
      if (!res.ok || type.includes('application/json')) {
        const json = type.includes('application/json') ? await res.json().catch(() => null) : null;
        throw new Error(json?.error?.message || json?.message || 'Không xuất được file báo cáo');
      }
      const blob = await res.blob();
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = 'danh_sach_dang_vien_' + Date.now() + '.' + extension;
      document.body.appendChild(link);
      link.click();
      setTimeout(() => URL.revokeObjectURL(link.href), 30000);
      link.remove();
    });
  }

  function can(action) {
    if (typeof window.TenantAppCanAccess === 'function') return window.TenantAppCanAccess('party_members', action);
    const role = String(window.App?.user?.role || '').toUpperCase();
    if (['SUPER_ADMIN','ADMIN'].includes(role)) return true;
    if (role === 'VIEWER') return action === 'read';
    return ['read','create','update','delete','export','print'].includes(action);
  }

  function registerModal(id) { window.TenantAppPlatform?.modals?.registerBootstrap?.(id, '#' + id); }
  function openModal(id) { return window.TenantAppPlatform?.modals?.open?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.show(); }
  function closeModal(id) { return window.TenantAppPlatform?.modals?.close?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.hide(); }
  function confirmAction(options) { const dialog = window.TenantAppPlatform?.confirmDialog; if (dialog?.ask) return dialog.ask(options); return Promise.resolve(window.confirm(options.message || 'Xác nhận?')); }
  function hideSuggestions() { $('#partyCitizenSuggestions')?.classList.add('d-none'); }
  function avatar(row) { const initials = String(row.full_name || '?').trim().split(/\s+/).slice(-2).map(x => x[0] || '').join('').toUpperCase(); return row.photo_url ? '<img class="party-avatar" src="' + esc(row.photo_url) + '" alt="">' : '<span class="party-avatar">' + esc(initials || '?') + '</span>'; }
  function date(v) { if (!v) return ''; const d = new Date(v); return Number.isNaN(d.getTime()) ? String(v) : new Intl.DateTimeFormat('vi-VN').format(d); }
  function num(v) { return new Intl.NumberFormat('vi-VN').format(Number(v || 0)); }
  function value(v) { return v == null || String(v).trim() === '' ? 'Chưa cập nhật' : v; }
  function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c])); }
  function toast(message, type = 'success') { if (typeof window.showToast === 'function') window.showToast(message, type); else console[type === 'danger' ? 'error' : 'log'](message); }
  function debounce(fn, ms) { let t; return function () { clearTimeout(t); t = setTimeout(() => fn.apply(this, arguments), ms); }; }

  window.loadPartyMembers = load;
  window.openPartyMemberForm = openForm;
})();
