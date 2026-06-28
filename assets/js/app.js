const App = {
  token: localStorage.getItem('thon09_token') || '',
  user: JSON.parse(localStorage.getItem('thon09_user') || 'null'),
  screen: 'dashboard',
  households: { page: 1, pageSize: 20, search: '' },
  persons: { page: 1, pageSize: 20, search: '', householdId: '' },
  modals: {},
  dictionaries: {
    ethnicities: ['Kinh','Tày','Thái','Mường','Khmer','Hoa','Nùng','Hmong','Dao','Gia Rai','Ê Đê','Ba Na','Sán Chay','Chăm','Cơ Ho','Xơ Đăng','Sán Dìu','Hrê','Ra Glai','Mnông','Thổ','Stiêng','Khơ Mú','Bru - Vân Kiều','Cơ Tu','Giáy','Tà Ôi','Mạ','Co','Chơ Ro','Xinh Mun','Hà Nhì','Chu Ru','Lào','La Chí','La Ha','Phù Lá','La Hủ','Lự','Lô Lô','Chứt','Mảng','Pà Thẻn','Cơ Lao','Cống','Bố Y','Si La','Pu Péo','Brâu','Ơ Đu','Rơ Măm','Ngái','Cờ Ho','Khác'],
    religions: ['Không','Phật giáo','Công giáo','Tin lành','Cao Đài','Hòa Hảo','Hồi giáo','Tín ngưỡng dân gian','Khác'],
    occupations: ['Nông nghiệp','Công nhân','Cán bộ','Công chức','Viên chức','Kinh doanh','Lao động tự do','Học sinh','Sinh viên','Nội trợ','Hưu trí','Không có việc làm','Khác'],
    relationships: ['Chủ hộ','Vợ','Chồng','Con','Cha','Mẹ','Ông','Bà','Cháu','Anh','Chị','Em','Người ở cùng','Khác'],
    educationLevels: ['Chưa đi học','Tiểu học','Trung học cơ sở','Trung học phổ thông','Trung cấp','Cao đẳng','Đại học','Sau đại học','Khác'],
    maritalStatuses: ['Chưa kết hôn','Đã kết hôn','Ly hôn','Góa','Khác']
  }
};

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

function init() {
  App.modals.household = new bootstrap.Modal($('#householdModal'));
  App.modals.person = new bootstrap.Modal($('#personModal'));
  App.modals.detail = new bootstrap.Modal($('#detailModal'));
  fillDictionaries();
  bindEvents();
  App.token ? showApp() : showLogin();
}

document.addEventListener('DOMContentLoaded', init);

function bindEvents() {
  $('#loginForm').addEventListener('submit', login);
  $('#logoutBtn').addEventListener('click', logout);
  $('#sidebarToggle').addEventListener('click', () => $('.sidebar').classList.toggle('open'));
  $$('.sidebar .nav-link').forEach(btn => btn.addEventListener('click', () => switchScreen(btn.dataset.screen)));

  $('#householdAddBtn').addEventListener('click', () => openHouseholdForm());
  $('#personAddBtn').addEventListener('click', () => openPersonForm());
  $('#householdForm').addEventListener('submit', saveHousehold);
  $('#personForm').addEventListener('submit', savePerson);
  $('#householdSearch').addEventListener('input', debounce(() => { App.households.search = $('#householdSearch').value.trim(); App.households.page = 1; loadHouseholds(); }, 350));
  $('#personSearch').addEventListener('input', debounce(() => { App.persons.search = $('#personSearch').value.trim(); App.persons.page = 1; loadPersons(); }, 350));
  $('#personHouseholdFilter').addEventListener('input', debounce(() => { App.persons.householdId = $('#personHouseholdFilter').value.trim(); App.persons.page = 1; loadPersons(); }, 350));
  $('#householdPageSize').addEventListener('change', () => { App.households.pageSize = Number($('#householdPageSize').value); App.households.page = 1; loadHouseholds(); });
  $('#personPageSize').addEventListener('change', () => { App.persons.pageSize = Number($('#personPageSize').value); App.persons.page = 1; loadPersons(); });
  $('#householdCheckAll').addEventListener('change', e => $$('.household-check').forEach(c => c.checked = e.target.checked));
  $('#personCheckAll').addEventListener('change', e => $$('.person-check').forEach(c => c.checked = e.target.checked));
  $('#householdBulkDeleteBtn').addEventListener('click', bulkDeleteHouseholds);
  $('#personBulkDeleteBtn').addEventListener('click', bulkDeletePersons);
}

function fillDictionaries() {
  $$('[data-options]').forEach(select => {
    const key = select.dataset.options;
    select.innerHTML = App.dictionaries[key].map(value => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join('');
  });
}

async function login(event) {
  event.preventDefault();
  const form = event.currentTarget;
  if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
  const payload = Object.fromEntries(new FormData(form).entries());
  try {
    const res = await api('/api/auth/login', { method: 'POST', body: payload, public: true });
    App.token = res.token; App.user = res.user;
    localStorage.setItem('thon09_token', App.token);
    localStorage.setItem('thon09_user', JSON.stringify(App.user));
    showToast('Đăng nhập thành công');
    showApp();
  } catch (error) { showToast(error.message, 'danger'); }
}

async function logout() {
  try { await api('/api/auth/logout', { method: 'POST' }); } catch (_) {}
  App.token = ''; App.user = null;
  localStorage.removeItem('thon09_token'); localStorage.removeItem('thon09_user');
  showLogin();
}

function showLogin() { $('#loginView').classList.remove('d-none'); $('#appView').classList.add('d-none'); }
function showApp() {
  $('#loginView').classList.add('d-none'); $('#appView').classList.remove('d-none');
  $('#currentUser').textContent = App.user ? `${App.user.email} | ${roleLabel(App.user.role)}` : '';
  switchScreen(App.screen);
}

function switchScreen(screen) {
  App.screen = screen;
  $$('.sidebar .nav-link').forEach(btn => btn.classList.toggle('active', btn.dataset.screen === screen));
  $$('.screen').forEach(el => el.classList.remove('active'));
  $(`#${screen}Screen`).classList.add('active');
  $('#screenTitle').textContent = { dashboard: 'Tổng quan', households: 'Hộ dân', persons: 'Nhân khẩu', reports: 'Báo cáo' }[screen];
  $('.sidebar').classList.remove('open');
  if (screen === 'dashboard') loadDashboard();
  if (screen === 'households') loadHouseholds();
  if (screen === 'persons') loadPersons();
}

async function loadDashboard() {
  try {
    const data = await api('/api/dashboard/summary');
    const metrics = data.metrics || {};
    const cards = [
      ['Tổng số hộ', metrics.total_households || 0],
      ['Tổng số nhân khẩu', metrics.total_citizens || 0],
      ['Nam', metrics.male_count || 0],
      ['Nữ', metrics.female_count || 0],
      ['Tạm trú', metrics.temporary_count || 0],
      ['Tạm vắng', metrics.away_count || 0]
    ];
    $('#dashboardCards').innerHTML = cards.map(([label, value]) => `<div class="col-sm-6 col-xl-2"><div class="metric-card"><div class="metric-label">${label}</div><div class="metric-value">${number(value)}</div></div></div>`).join('');
    renderChart('#genderChart', data.charts?.population || []);
    renderChart('#ageChart', data.charts?.ages || []);
    renderChart('#householdChart', data.charts?.households || []);
  } catch (error) { showToast('Không tải được tổng quan: ' + error.message, 'danger'); }
}

async function loadHouseholds() {
  try {
    const query = new URLSearchParams(App.households).toString();
    const data = await api('/api/households?' + query);
    $('#householdRows').innerHTML = data.items.map(row => `<tr>
      <td><input type="checkbox" class="household-check" value="${row.id}"></td>
      <td><button class="btn btn-link p-0 fw-semibold" onclick="showHousehold(${row.id})">${escapeHtml(row.household_code)}</button></td>
      <td>${escapeHtml(row.head_citizen_name || '')}</td>
      <td>${escapeHtml(row.address || '')}</td>
      <td>${number(row.at_home_count || 0)}</td>
      <td>${number(row.away_count || 0)}</td>
      <td>${householdBadges(row)}</td>
      <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="openHouseholdForm(${row.id})">Sửa</button> <button class="btn btn-sm btn-outline-danger" onclick="deleteHousehold(${row.id})">Xóa</button></td>
    </tr>`).join('') || emptyRow(8, 'Chưa có hộ dân');
    renderPager('#householdPager', data, page => { App.households.page = page; loadHouseholds(); });
  } catch (error) { showToast('Không tải được danh sách hộ dân: ' + error.message, 'danger'); }
}

async function loadPersons() {
  try {
    const query = new URLSearchParams(App.persons).toString();
    const data = await api('/api/persons?' + query);
    let lastHousehold = '';
    const rows = [];
    data.items.forEach(row => {
      if (row.household_code !== lastHousehold) {
        lastHousehold = row.household_code;
        rows.push(`<tr class="group-row"><td colspan="9">Mã hộ: ${escapeHtml(lastHousehold)}</td></tr>`);
      }
      rows.push(`<tr>
        <td><input type="checkbox" class="person-check" value="${row.id}"></td>
        <td>${escapeHtml(row.household_code || '')}</td>
        <td>${escapeHtml(row.citizen_code || '')}</td>
        <td><button class="btn btn-link p-0 fw-semibold" onclick="showPerson(${row.id})">${escapeHtml(row.full_name || '')}</button></td>
        <td>${formatDate(row.date_of_birth)}</td>
        <td>${escapeHtml(row.identity_number || '')}</td>
        <td>${residencyLabel(row.residency_status)}</td>
        <td>${presenceLabel(row.presence_status)}</td>
        <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="openPersonForm(${row.id})">Sửa</button> <button class="btn btn-sm btn-outline-danger" onclick="deletePerson(${row.id})">Xóa</button></td>
      </tr>`);
    });
    $('#personRows').innerHTML = rows.join('') || emptyRow(9, 'Chưa có nhân khẩu');
    renderPager('#personPager', data, page => { App.persons.page = page; loadPersons(); });
  } catch (error) { showToast('Không tải được danh sách nhân khẩu: ' + error.message, 'danger'); }
}

async function openHouseholdForm(id = null) {
  const form = $('#householdForm'); form.reset(); form.classList.remove('was-validated'); form.id.value = '';
  if (id) {
    const row = await api(`/api/households/${id}`);
    setForm(form, {
      id: row.id, householdCode: row.household_code, headCitizenName: row.head_citizen_name, phone: row.phone, address: row.address, note: row.note,
      meritoriousFamily: !!Number(row.meritorious_family), poorHousehold: !!Number(row.poor_household), nearPoorHousehold: !!Number(row.near_poor_household), disabledHousehold: !!Number(row.disabled_household)
    });
  }
  App.modals.household.show();
}

async function openPersonForm(id = null) {
  const form = $('#personForm'); form.reset(); form.classList.remove('was-validated'); form.id.value = '';
  if (id) {
    const row = await api(`/api/persons/${id}`);
    setForm(form, {
      id: row.id, householdCode: row.household_code, citizenCode: row.citizen_code, fullName: row.full_name, gender: row.gender, dateOfBirth: row.date_of_birth,
      identityNumber: row.identity_number, phone: row.phone, relationship: row.relationship, ethnicity: row.ethnicity, religion: row.religion,
      occupation: row.occupation, educationLevel: row.education_level, maritalStatus: row.marital_status, residency_status: row.residency_status,
      presenceStatus: row.presence_status, status: row.life_status, currentAddress: row.current_address
    });
  }
  App.modals.person.show();
}

async function saveHousehold(event) {
  event.preventDefault();
  const form = event.currentTarget;
  if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
  const payload = formData(form);
  const id = payload.id; delete payload.id;
  try {
    await api(id ? `/api/households/${id}` : '/api/households', { method: id ? 'PUT' : 'POST', body: payload });
    App.modals.household.hide(); showToast('Đã lưu hộ dân'); loadHouseholds(); loadDashboard();
  } catch (error) { showToast(error.message, 'danger'); }
}

async function savePerson(event) {
  event.preventDefault();
  const form = event.currentTarget;
  if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
  const payload = formData(form);
  const id = payload.id; delete payload.id;
  try {
    await api(id ? `/api/persons/${id}` : '/api/persons', { method: id ? 'PUT' : 'POST', body: payload });
    App.modals.person.hide(); showToast('Đã lưu nhân khẩu'); loadPersons(); loadHouseholds(); loadDashboard();
  } catch (error) { showToast(error.message, 'danger'); }
}

async function showHousehold(id) {
  const row = await api(`/api/households/${id}`);
  $('#detailTitle').textContent = 'Chi tiết hộ dân';
  $('#detailBody').innerHTML = details([
    ['Mã hộ', row.household_code], ['Chủ hộ', row.head_citizen_name], ['Địa chỉ', row.address], ['Số điện thoại', row.phone],
    ['Ở nhà', row.at_home_count || 0], ['Đi vắng', row.away_count || 0], ['Diện hộ', stripTags(householdBadges(row))], ['Ghi chú', row.note]
  ]);
  App.modals.detail.show();
}

async function showPerson(id) {
  const row = await api(`/api/persons/${id}`);
  $('#detailTitle').textContent = 'Chi tiết nhân khẩu';
  $('#detailBody').innerHTML = details([
    ['Mã hộ', row.household_code], ['Mã nhân khẩu', row.citizen_code], ['Họ tên', row.full_name], ['Giới tính', row.gender], ['Ngày sinh', formatDate(row.date_of_birth)],
    ['CCCD', row.identity_number], ['Số điện thoại', row.phone], ['Quan hệ', row.relationship], ['Dân tộc', row.ethnicity], ['Tôn giáo', row.religion],
    ['Nghề nghiệp', row.occupation], ['Học vấn', row.education_level], ['Hôn nhân', row.marital_status], ['Thường trú', residencyLabel(row.residency_status)],
    ['Hiện tại', presenceLabel(row.presence_status)], ['Trạng thái', lifeLabel(row.life_status)], ['Địa chỉ hiện tại', row.current_address]
  ]);
  App.modals.detail.show();
}

async function deleteHousehold(id) { if (!confirm('Xóa hộ dân này?')) return; await api(`/api/households/${id}`, { method: 'DELETE' }); showToast('Đã xóa hộ dân'); loadHouseholds(); loadDashboard(); }
async function deletePerson(id) { if (!confirm('Xóa nhân khẩu này?')) return; await api(`/api/persons/${id}`, { method: 'DELETE' }); showToast('Đã xóa nhân khẩu'); loadPersons(); loadHouseholds(); loadDashboard(); }

async function bulkDeleteHouseholds() { await bulkDelete('.household-check:checked', '/api/households/bulk-delete', loadHouseholds, 'hộ dân'); }
async function bulkDeletePersons() { await bulkDelete('.person-check:checked', '/api/persons/bulk-delete', loadPersons, 'nhân khẩu'); }
async function bulkDelete(selector, url, reload, label) {
  const ids = $$(selector).map(c => Number(c.value));
  if (!ids.length) return showToast('Chưa chọn dữ liệu cần xóa', 'warning');
  if (!confirm(`Xóa ${ids.length} ${label} đã chọn?`)) return;
  const result = await api(url, { method: 'POST', body: { ids } });
  showToast(`Đã xóa ${result.success || 0} ${label}` + (result.errors?.length ? `, lỗi ${result.errors.length}` : ''));
  reload(); loadDashboard();
}

async function api(url, options = {}) {
  setLoading(true);
  try {
    const headers = { 'Accept': 'application/json' };
    if (options.body) headers['Content-Type'] = 'application/json';
    if (App.token && !options.public) headers.Authorization = `Bearer ${App.token}`;
    const response = await fetch(url, { method: options.method || 'GET', headers, body: options.body ? JSON.stringify(options.body) : undefined });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload?.ok) throw new Error(payload?.error?.message || 'Không nhận được phản hồi từ hệ thống');
    return payload.data;
  } finally { setLoading(false); }
}

function setLoading(active) { $('#loadingBar').classList.toggle('d-none', !active); }
function debounce(fn, wait) { let timer; return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), wait); }; }
function number(value) { return new Intl.NumberFormat('vi-VN').format(Number(value || 0)); }
function formatDate(value) { if (!value) return ''; const [y, m, d] = String(value).split('-'); return y && m && d ? `${d}/${m}/${y}` : value; }
function escapeHtml(value) { return String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;' }[c])); }
function stripTags(html) { return String(html).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim(); }
function roleLabel(role) { return ({ SUPER_ADMIN:'Quản trị tối cao', ADMIN:'Quản trị', OFFICER:'Cán bộ', VIEWER:'Chỉ xem' })[role] || role || ''; }
function residencyLabel(value) { return value === 'TEMPORARY' ? 'Tạm trú' : 'Thường trú'; }
function presenceLabel(value) { return value === 'AWAY' ? 'Đi vắng' : 'Ở nhà'; }
function lifeLabel(value) { return value === 'DECEASED' ? 'Đã chết' : 'Còn sống'; }
function emptyRow(colspan, message) { return `<tr><td colspan="${colspan}" class="text-center text-muted py-4">${message}</td></tr>`; }
function householdBadges(row) {
  const badges = [];
  if (Number(row.meritorious_family)) badges.push('Có công');
  if (Number(row.poor_household)) badges.push('Hộ nghèo');
  if (Number(row.near_poor_household)) badges.push('Cận nghèo');
  if (Number(row.disabled_household)) badges.push('Tàn tật');
  return badges.length ? badges.map(b => `<span class="badge-soft">${b}</span>`).join('') : '<span class="text-muted">Không</span>';
}
function formData(form) {
  const data = Object.fromEntries(new FormData(form).entries());
  $$('input[type="checkbox"]', form).forEach(input => data[input.name] = input.checked ? 1 : 0);
  Object.keys(data).forEach(key => { if (data[key] === '') data[key] = null; });
  return data;
}
function setForm(form, values) {
  Object.entries(values).forEach(([key, value]) => {
    const el = form.elements[key]; if (!el) return;
    if (el.type === 'checkbox') el.checked = !!value; else el.value = value ?? '';
  });
}
function renderPager(selector, data, go) {
  const page = Number(data.page || 1), totalPages = Number(data.totalPages || 1);
  $(selector).innerHTML = `<span class="text-muted small">Trang ${page}/${totalPages} - ${number(data.total)} dòng</span>
    <button class="btn btn-outline-secondary btn-sm" ${page <= 1 ? 'disabled' : ''} data-page="${page - 1}">Trước</button>
    <button class="btn btn-outline-secondary btn-sm" ${page >= totalPages ? 'disabled' : ''} data-page="${page + 1}">Sau</button>`;
  $$(`${selector} button`).forEach(btn => btn.addEventListener('click', () => go(Number(btn.dataset.page))));
}
function renderChart(selector, items) {
  const total = items.reduce((sum, item) => sum + Number(item.value || 0), 0) || 1;
  $(selector).innerHTML = items.map(item => {
    const percent = Math.round(Number(item.value || 0) * 100 / total);
    return `<div class="chart-line"><span>${escapeHtml(item.label || 'Khác')}</span><div class="chart-track"><div class="chart-bar" style="width:${percent}%"></div></div><strong>${number(item.value)}</strong></div>`;
  }).join('') || '<p class="text-muted mb-0">Chưa có dữ liệu</p>';
}
function details(rows) {
  return `<div class="detail-grid">${rows.map(([label, value]) => `<div class="detail-item"><div class="detail-label">${escapeHtml(label)}</div><div class="detail-value">${escapeHtml(value ?? '')}</div></div>`).join('')}</div>`;
}
