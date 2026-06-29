const App = {
  token: localStorage.getItem('thon09_token') || '',
  user: JSON.parse(localStorage.getItem('thon09_user') || 'null'),
  screen: localStorage.getItem('thon09_screen') || 'dashboard',
  households: { page: 1, pageSize: 20, search: '', category: '', status: '' },
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

window.App = App;

const DASHBOARD_STAT_CONFIG = [
  { key: 'total_households', label: 'Tổng số hộ', icon: 'fa-house', loginClass: 'stat-house', unit: 'hộ' },
  { key: 'total_citizens', label: 'Tổng nhân khẩu', icon: 'fa-users', loginClass: 'stat-pop', unit: 'người' },
  { key: 'party_member_count', label: 'Đảng viên', icon: 'fa-landmark-flag', loginClass: 'stat-party', unit: 'người' },
  { key: 'male_count', label: 'Nam', icon: 'fa-person', dashboardIcon: 'fa-mars', loginClass: 'stat-male', unit: 'người' },
  { key: 'female_count', label: 'Nữ', icon: 'fa-person-dress', dashboardIcon: 'fa-venus', loginClass: 'stat-female', unit: 'người' },
  { key: 'away_count', label: 'Tạm vắng', icon: 'fa-person-walking-arrow-right', loginClass: 'stat-away', unit: 'người' }
];

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

document.addEventListener('DOMContentLoaded', init);

function init() {
  App.modals.household = new bootstrap.Modal($('#householdModal'));
  App.modals.person = new bootstrap.Modal($('#personModal'));
  App.modals.detail = new bootstrap.Modal($('#detailModal'));
  fillDictionaries();
  bindEvents();
  initLoginExperience();
  App.token ? showApp() : showLogin();
}

function bindEvents() {
  $('#loginForm').addEventListener('submit', login);
  $('#logoutBtn').addEventListener('click', logout);
  $('#sidebarToggle').addEventListener('click', () => $('.sidebar').classList.toggle('open'));
  $$('.sidebar .nav-link').forEach(btn => btn.addEventListener('click', () => switchScreen(btn.dataset.screen)));
  const dashboardFilters = $('#dashboardFilters');
  if (dashboardFilters) dashboardFilters.addEventListener('submit', event => { event.preventDefault(); loadDashboard(); refreshLoginConfig(); });
  const dashboardResetBtn = $('#dashboardResetBtn');
  if (dashboardResetBtn) dashboardResetBtn.addEventListener('click', () => { if (dashboardFilters) dashboardFilters.reset(); loadDashboard(); refreshLoginConfig(); });
  $('#householdAddBtn').addEventListener('click', () => openHouseholdForm());
  const personAddBtn = $('#personAddBtn');
  if (personAddBtn) personAddBtn.addEventListener('click', () => openPersonForm());
  $('#householdForm').addEventListener('submit', saveHousehold);
  $('#personForm').addEventListener('submit', savePerson);
  $('#householdSearch').addEventListener('input', debounce(() => { App.households.search = $('#householdSearch').value.trim(); App.households.page = 1; loadHouseholds(); }, 350));
  const personSearchInput = $('#personSearch');
  if (personSearchInput) personSearchInput.addEventListener('input', debounce(() => { App.persons.search = personSearchInput.value.trim(); App.persons.page = 1; loadPersons(); }, 350));
  const personHouseholdFilter = $('#personHouseholdFilter');
  if (personHouseholdFilter) personHouseholdFilter.addEventListener('input', debounce(() => { App.persons.householdId = personHouseholdFilter.value.trim(); App.persons.page = 1; loadPersons(); }, 350));
  $('#householdPageSize').addEventListener('change', () => { App.households.pageSize = Number($('#householdPageSize').value); App.households.page = 1; loadHouseholds(); });
  const householdCategoryFilter = $('#householdCategoryFilter');
  if (householdCategoryFilter) householdCategoryFilter.addEventListener('change', () => { App.households.category = householdCategoryFilter.value; App.households.household_type = householdCategoryFilter.value; App.households.page = 1; loadHouseholds(); });
  const householdStatusFilter = $('#householdStatusFilter');
  if (householdStatusFilter) householdStatusFilter.addEventListener('change', () => { App.households.status = householdStatusFilter.value; App.households.page = 1; loadHouseholds(); });
  const householdFilterReset = $('#householdFilterReset');
  if (householdFilterReset) householdFilterReset.addEventListener('click', () => { $('#householdSearch').value = ''; if (householdCategoryFilter) householdCategoryFilter.value = ''; if (householdStatusFilter) householdStatusFilter.value = ''; App.households.search = ''; App.households.category = ''; App.households.household_type = ''; App.households.status = ''; App.households.page = 1; loadHouseholds(); });
  const personPageSize = $('#personPageSize');
  if (personPageSize) personPageSize.addEventListener('change', () => { App.persons.pageSize = Number(personPageSize.value); App.persons.page = 1; loadPersons(); });
  $('#householdCheckAll').addEventListener('change', e => $$('.household-check').forEach(c => c.checked = e.target.checked));
  const personCheckAll = $('#personCheckAll');
  if (personCheckAll) personCheckAll.addEventListener('change', e => $('.person-check').forEach(c => c.checked = e.target.checked));
  $('#householdBulkDeleteBtn').addEventListener('click', bulkDeleteHouseholds);
  const personBulkDeleteBtn = $('#personBulkDeleteBtn');
  if (personBulkDeleteBtn) personBulkDeleteBtn.addEventListener('click', bulkDeletePersons);
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
  try {
    const payload = Object.fromEntries(new FormData(form).entries());
    const res = await api('/api/auth/login', { method: 'POST', body: payload, public: true });
    App.token = res.token;
    App.user = res.user;
    localStorage.setItem('thon09_token', App.token);
    localStorage.setItem('thon09_user', JSON.stringify(App.user));
    window.App = App;
    showToast('Đăng nhập thành công');
    showApp();
  } catch (error) { showToast(error.message, 'danger'); }
}

async function logout() {
  try { await api('/api/auth/logout', { method: 'POST' }); } catch (_) {}
  App.token = '';
  App.user = null;
  localStorage.removeItem('thon09_token');
  localStorage.removeItem('thon09_user');
  localStorage.removeItem('thon09_screen');
  showLogin();
}

function showLogin() { $('#loginView').classList.remove('d-none'); $('#appView').classList.add('d-none'); initLoginExperience(); }
function showApp() {
  $('#loginView').classList.add('d-none');
  $('#appView').classList.remove('d-none');
  $('#currentUser').textContent = App.user ? `${App.user.email} | ${roleLabel(App.user.role)}` : '';
  if (typeof window.ensureAdminScreens === 'function') window.ensureAdminScreens();
  switchScreen(App.screen);
}

function normalizeAppHeader(screen) {
  const screenLabels = { dashboard: 'Dashboard', households: 'Quản lý hộ gia đình', persons: 'Quản lý nhân khẩu', temporaryResidence: 'Tạm trú', temporaryAbsence: 'Tạm vắng', movements: 'Biến động nhân khẩu', reports: 'Báo cáo thống kê', import: 'Import dữ liệu', export: 'Export Excel', exportExcel: 'Export Excel', printForms: 'In biểu mẫu', users: 'Quản lý tài khoản', logs: 'Nhật ký hệ thống', appearance: 'Cấu hình giao diện', settings: 'Cấu hình hệ thống', backups: 'Sao lưu dữ liệu', restore: 'Khôi phục dữ liệu', permissions: 'Phân quyền' };
  const label = screenLabels[screen] || 'Dashboard';
  const title = $('#screenTitle');
  const breadcrumb = $('#breadcrumbTrail');
  if (title) title.textContent = label;
  if (breadcrumb) breadcrumb.textContent = 'Trang chủ / ' + label;
  $$('.topbar-title-block small:not(#breadcrumbTrail), .topbar-title-block .text-muted:not(#breadcrumbTrail), .topbar > div:first-of-type small:not(#breadcrumbTrail), .topbar > div:first-of-type .text-muted:not(#breadcrumbTrail)').forEach(el => el.remove());
  $$('.dashboard-hero-row, .module-page-head > div, .person-page-head > div, .report-page-head, .screen > .admin-heading > div').forEach(el => el.remove());
}

function switchScreen(screen) {
  if (!document.querySelector('#' + screen + 'Screen')) screen = 'dashboard';
  App.screen = screen;
  localStorage.setItem('thon09_screen', screen);
  $$('.sidebar .nav-link').forEach(btn => btn.classList.toggle('active', btn.dataset.screen === screen));
  $$('.screen').forEach(el => el.classList.remove('active'));
  $(`#${screen}Screen`).classList.add('active');
  normalizeAppHeader(screen);
  $('.sidebar').classList.remove('open');
  if (screen === 'dashboard') { loadDashboard(); refreshLoginConfig(); }
  if (screen === 'households') loadHouseholds();
  if (screen === 'persons') loadPersons();
}

async function loadDashboard() {
  try {
    const data = await api('/api/dashboard/summary');
    App.dashboardSummary = data;
    const metrics = data?.metrics || {};
    const charts = data?.charts || {};
    renderDashboardKpis(metrics);
    renderAgeStructureChart(charts.ages || []);
    renderMonthlyChangeChart(charts.monthlyChanges || [], metrics.total_citizens || 0);
    renderGenderDashboardChart(charts.population || [], metrics);
    renderPartyDashboardChart(charts.partyMembers || [], metrics);
    updateDashboardGeneratedAt(data.generatedAt);
  } catch (error) {
    if (!String(error.message || '').includes('đăng nhập')) showToast('Không tải được tổng quan: ' + error.message, 'danger');
  }
}

function updateDashboardGeneratedAt(value) {
  const el = $('#dashboardGeneratedAt');
  if (!el) return;
  const date = value ? new Date(value) : new Date();
  el.textContent = 'Cập nhật lúc ' + new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
}

function renderDashboardKpis(metrics) {
  const host = $('#dashboardKpis');
  if (!host) return;
  const cards = [
    { key: 'total_households', label: 'Tổng số hộ', unit: 'hộ', icon: 'fa-house-chimney', tone: 'green' },
    { key: 'total_citizens', label: 'Tổng số nhân khẩu', unit: 'người', icon: 'fa-users', tone: 'blue' },
    { key: 'party_member_count', label: 'Đảng viên', unit: 'người', icon: 'fa-star', tone: 'orange' },
    { key: 'male_count', label: 'Nam', unit: 'người', icon: 'fa-person', tone: 'cyan' },
    { key: 'female_count', label: 'Nữ', unit: 'người', icon: 'fa-person-dress', tone: 'pink' },
    { key: 'away_count', label: 'Tạm vắng', unit: 'người', icon: 'fa-person-walking-arrow-right', tone: 'purple' }
  ];
  host.innerHTML = cards.map(card => {
    const value = Number(metrics[card.key] || 0);
    return '<article class="dashboard-kpi dashboard-tone-' + card.tone + '">'
      + '<div class="dashboard-kpi-head"><span class="dashboard-kpi-icon"><i class="fa-solid ' + card.icon + '"></i></span><span class="dashboard-kpi-label">' + escapeHtml(card.label) + '</span></div>'
      + '<div class="dashboard-kpi-value"><strong>' + number(value) + '</strong><span>' + escapeHtml(card.unit) + '</span></div>'
      + '</article>';
  }).join('');
}

function normalizeChartItems(items) {
  return (items || []).map(item => ({ label: item.label || 'Khác', value: Number(item.value || 0) })).filter(item => item.value >= 0);
}

function totalChartValue(items) {
  return normalizeChartItems(items).reduce((sum, item) => sum + item.value, 0);
}

function percent(value, total) {
  if (!total) return 0;
  return Math.round((Number(value || 0) * 1000 / total)) / 10;
}

function formatPercent(value) {
  return String(value).replace('.', ',') + '%';
}

function chartEmpty(message = 'Chưa có dữ liệu') {
  return '<div class="dashboard-empty-chart">' + escapeHtml(message) + '</div>';
}

function renderAgeStructureChart(items) {
  const host = $('#ageStructureChart');
  if (!host) return;
  const preferred = ['0-5 tuổi','6-14 tuổi','15-17 tuổi','18-59 tuổi','Trên 60 tuổi'];
  const map = new Map(normalizeChartItems(items).map(item => [item.label, item.value]));
  const normalized = preferred.map(label => ({ label, value: Number(map.get(label) || 0) }));
  const total = normalized.reduce((sum, item) => sum + item.value, 0);
  if (!total) { host.innerHTML = chartEmpty(); return; }
  host.innerHTML = '<div class="dashboard-age-layout">'
    + renderDonut(normalized, total, { centerLabel: 'Tổng số', centerValue: number(total), centerUnit: 'người', className: 'dashboard-age-donut' })
    + '<div class="dashboard-legend">' + normalized.map((item, index) => renderLegendRow(item, total, index)).join('') + '</div>'
    + '</div>';
}

function renderMonthlyChangeChart(items, fallbackTotal) {
  const host = $('#populationMovementChart');
  if (!host) return;
  const source = normalizeChartItems(items).slice(-6);
  const rows = source.length ? source : [{ label: 'Hiện tại', value: Number(fallbackTotal || 0) }];
  const values = rows.map(item => Number(item.value || 0));
  if (!values.some(Boolean)) { host.innerHTML = chartEmpty(); return; }
  const min = Math.min(...values);
  const max = Math.max(...values);
  const range = Math.max(1, max - min);
  const width = 680;
  const height = 250;
  const points = values.map((value, index) => {
    const x = rows.length === 1 ? width / 2 : 32 + index * ((width - 64) / (rows.length - 1));
    const y = 42 + (max - value) * 150 / range;
    return { x, y, value };
  });
  const line = points.map(point => point.x + ',' + point.y).join(' ');
  const area = 'M' + points.map(point => point.x + ' ' + point.y).join(' L') + ' L' + points[points.length - 1].x + ' ' + height + ' L' + points[0].x + ' ' + height + ' Z';
  host.innerHTML = '<div class="dashboard-line-chart">'
    + '<svg viewBox="0 0 ' + width + ' ' + height + '" preserveAspectRatio="none" aria-hidden="true">'
    + '<defs><linearGradient id="dashboardArea" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#16834c" stop-opacity=".24"/><stop offset="1" stop-color="#16834c" stop-opacity=".02"/></linearGradient></defs>'
    + '<path d="' + area + '" fill="url(#dashboardArea)"></path>'
    + '<polyline points="' + line + '" fill="none" stroke="#16834c" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></polyline>'
    + points.map(point => '<circle cx="' + point.x + '" cy="' + point.y + '" r="6" fill="#16834c"></circle><text x="' + (point.x - 20) + '" y="' + (point.y - 14) + '" fill="#0b6b3a" font-size="13" font-weight="700">' + number(point.value) + '</text>').join('')
    + '</svg><div class="dashboard-line-months">' + rows.map(item => '<span>' + escapeHtml(formatMonthLabel(item.label)) + '</span>').join('') + '</div></div>';
}

function formatMonthLabel(label) {
  const text = String(label || '');
  const match = text.match(/^(\d{4})-(\d{2})$/);
  return match ? match[2] + '/' + match[1] : text;
}

function renderGenderDashboardChart(items, metrics) {
  const host = $('#genderStructureChart');
  if (!host) return;
  let normalized = normalizeChartItems(items);
  if (!normalized.length) normalized = [{ label: 'Nam', value: Number(metrics.male_count || 0) }, { label: 'Nữ', value: Number(metrics.female_count || 0) }];
  const total = normalized.reduce((sum, item) => sum + item.value, 0);
  if (!total) { host.innerHTML = chartEmpty(); return; }
  const male = normalized.find(item => normalizeSearchText(item.label).includes('nam')) || { label: 'Nam', value: Number(metrics.male_count || 0) };
  const female = normalized.find(item => normalizeSearchText(item.label).includes('nu')) || { label: 'Nữ', value: Number(metrics.female_count || 0) };
  host.innerHTML = '<div class="dashboard-gender-layout">'
    + renderSideStat('Nam', male.value, percent(male.value, total), 'blue', 'fa-mars')
    + renderDonut([male, female], total, { centerLabel: 'Tổng số', centerValue: number(total), centerUnit: 'người', className: 'dashboard-gender-donut' })
    + renderSideStat('Nữ', female.value, percent(female.value, total), 'pink', 'fa-venus')
    + '</div>';
}

function renderPartyDashboardChart(items, metrics) {
  const host = $('#partyMemberChart');
  if (!host) return;
  const total = Number(metrics.total_citizens || 0);
  const party = Number(metrics.party_member_count || (normalizeChartItems(items)[0]?.value || 0));
  if (!total) { host.innerHTML = chartEmpty(); return; }
  const rows = [{ label: 'Đảng viên', value: party }, { label: 'Còn lại', value: Math.max(0, total - party) }];
  const rate = percent(party, total);
  host.innerHTML = '<div class="dashboard-party-layout">'
    + renderDonut(rows, total, { centerLabel: 'Tỷ lệ', centerValue: formatPercent(rate), centerUnit: 'Đảng viên', className: 'dashboard-party-donut' })
    + '<div class="dashboard-summary-box"><span>Tổng số Đảng viên</span><strong>' + number(party) + ' người</strong><small>Tính trên tổng số ' + number(total) + ' nhân khẩu đang quản lý trong hệ thống.</small></div>'
    + '</div>';
}

function renderSideStat(label, value, rate, tone, icon) {
  return '<div class="dashboard-side-stat dashboard-side-' + tone + '"><span><i class="fa-solid ' + icon + '"></i></span><strong>' + number(value) + '</strong><small>' + escapeHtml(label) + ' - ' + formatPercent(rate) + '</small></div>';
}

function renderLegendRow(item, total, index) {
  return '<div class="dashboard-legend-row"><i class="dashboard-dot dashboard-dot-' + (index + 1) + '"></i><span>' + escapeHtml(item.label) + '</span><strong>' + number(item.value) + ' người (' + formatPercent(percent(item.value, total)) + ')</strong></div>';
}

function renderDonut(items, total, options = {}) {
  const colors = ['#21a366','#3b82f6','#f59e0b','#a78bfa','#ec4899','#0891b2'];
  let cursor = 0;
  const stops = normalizeChartItems(items).map((item, index) => {
    const start = cursor;
    cursor += total ? item.value * 100 / total : 0;
    return colors[index % colors.length] + ' ' + start + '% ' + cursor + '%';
  }).join(', ');
  return '<div class="dashboard-donut ' + escapeHtml(options.className || '') + '" style="--donut:' + escapeHtml(stops || '#e5e7eb 0% 100%') + '"><div class="dashboard-donut-center"><span>' + escapeHtml(options.centerLabel || '') + '</span><strong>' + escapeHtml(options.centerValue || '') + '</strong><small>' + escapeHtml(options.centerUnit || '') + '</small></div></div>';
}
window.loadDashboard = loadDashboard;
async function loadHouseholds() {
  try {
    const searchText = normalizeSearchText(App.households.search || '');
    let items = [];
    let total = 0;
    if (searchText) {
      const allItems = await fetchAllPaged('/api/households');
      const filtered = allItems.filter(row => [row.household_code, row.head_citizen_name, row.address, row.phone, row.note]
        .some(value => normalizeSearchText(value).includes(searchText)));
      total = filtered.length;
      const startIndex = (App.households.page - 1) * App.households.pageSize;
      items = filtered.slice(startIndex, startIndex + App.households.pageSize);
    } else {
      const params = new URLSearchParams({ page: App.households.page, pageSize: App.households.pageSize });
      if (App.households.search) params.set('search', App.households.search);
      if (App.households.category) { params.set('category', App.households.category); params.set('household_type', App.households.category); }
      if (App.households.status) params.set('status', App.households.status);
      const data = await api('/api/households?' + params.toString());
      items = data.items || [];
      total = data.total || 0;
    }
    const householdTotal = $('#householdTotalCount');
    if (householdTotal) householdTotal.innerHTML = 'Tổng số: <strong>' + number(total) + '</strong> hộ';
    $('#householdRows').innerHTML = items.map(row => '<tr>' +
      '<td><input type="checkbox" class="household-check" value="' + row.id + '"></td>' +
      '<td><button class="btn btn-link p-0 fw-semibold" onclick="showHousehold(' + row.id + ')">' + escapeHtml(row.household_code) + '</button></td>' +
      '<td>' + escapeHtml(row.head_citizen_name || '') + '</td>' +
      '<td>' + escapeHtml(row.address || '') + '</td>' +
      '<td>' + number(row.at_home_count || 0) + '</td>' +
      '<td>' + number(row.away_count || 0) + '</td>' +
      '<td>' + householdBadges(row) + '</td>' +
      '<td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="openHouseholdForm(' + row.id + ')">Sửa</button> <button class="btn btn-sm btn-outline-danger" onclick="deleteHousehold(' + row.id + ')">Xóa</button></td>' +
    '</tr>').join('') || emptyRow(8, 'Không có dữ liệu');
    renderPager('#householdPager', { total, page: App.households.page, pageSize: App.households.pageSize }, page => { App.households.page = page; loadHouseholds(); });
  } catch (error) { showToast('Không tải được danh sách hộ dân: ' + error.message, 'danger'); }
}

async function loadPersons() {
  try {
    const searchText = normalizeSearchText(App.persons.search || '');
    const householdText = (App.persons.householdId || '').trim();
    let items = [];
    let total = 0;
    if (searchText) {
      const extra = householdText ? { householdId: householdText } : {};
      const allItems = await fetchAllPaged('/api/persons', extra);
      const filtered = allItems.filter(row => [row.full_name, row.citizen_code, row.identity_number, row.phone, row.household_code, row.current_address, row.household_address]
        .some(value => normalizeSearchText(value).includes(searchText)));
      total = filtered.length;
      const startIndex = (App.persons.page - 1) * App.persons.pageSize;
      items = filtered.slice(startIndex, startIndex + App.persons.pageSize);
    } else {
      const params = new URLSearchParams({ page: App.persons.page, pageSize: App.persons.pageSize });
      if (householdText) params.set('householdId', householdText);
      const data = await api('/api/persons?' + params.toString());
      items = data.items || [];
      total = data.total || 0;
    }
    const grouped = items.reduce((acc, row) => {
      const code = row.household_code || 'Chưa có hộ';
      (acc[code] ||= []).push(row);
      return acc;
    }, {});
    $('#personRows').innerHTML = Object.entries(grouped).map(([code, rows]) => '<tr class="group-row"><td colspan="9">Mã hộ: ' + escapeHtml(code) + '</td></tr>' + rows.map(personRow).join('')).join('') || '<tr><td colspan="9" class="text-center text-muted py-4">Không có dữ liệu</td></tr>';
    renderPager('#personPager', { total, page: App.persons.page, pageSize: App.persons.pageSize }, page => { App.persons.page = page; loadPersons(); });
  } catch (error) { showToast('Không tải được danh sách nhân khẩu: ' + error.message, 'danger'); }
}

function personRow(row) {
  const party = Number(row.party_member || row.partyMember || 0) === 1;
  const residenceClass = row.presence_status === 'AWAY' ? 'person-badge-away' : (row.residency_status === 'TEMPORARY' ? 'person-badge-temp' : 'person-badge-home');
  const residenceText = row.presence_status === 'AWAY' ? 'Tạm vắng' : residencyLabel(row.residency_status);
  return '<tr>'
    + '<td>' + escapeHtml(row.household_code || '') + '</td>'
    + '<td>' + escapeHtml(row.citizen_code || '') + '</td>'
    + '<td><button class="btn btn-link person-name-link" onclick="showPerson(' + row.id + ')">' + escapeHtml(row.full_name || '') + '</button></td>'
    + '<td>' + formatDate(row.date_of_birth) + '</td>'
    + '<td>' + escapeHtml(row.gender || '') + '</td>'
    + '<td>' + escapeHtml(row.identity_number || '') + '</td>'
    + '<td><span class="person-badge ' + residenceClass + '">' + escapeHtml(residenceText) + '</span></td>'
    + '<td><span class="person-badge ' + (party ? 'person-badge-party' : 'person-badge-muted') + '">' + (party ? 'Có' : 'Không') + '</span></td>'
    + '<td class="text-end"><button class="btn btn-sm person-row-btn" onclick="showPerson(' + row.id + ')">Xem</button> <button class="btn btn-sm person-row-btn person-row-edit" onclick="openPersonForm(' + row.id + ')">Sửa</button></td>'
    + '</tr>';
}

async function openHouseholdForm(id = null) {
  const form = $('#householdForm');
  form.reset(); form.classList.remove('was-validated'); form.elements.id.value = '';
  if (id) {
    const row = await api(`/api/households/${id}`);
    setForm(form, { id: row.id, householdCode: row.household_code, headCitizenName: row.head_citizen_name, phone: row.phone, address: row.address, note: row.note, meritoriousFamily: !!Number(row.meritorious_family), poorHousehold: !!Number(row.poor_household), nearPoorHousehold: !!Number(row.near_poor_household), disabledHousehold: !!Number(row.disabled_household) });
  }
  App.modals.household.show();
}

async function openPersonForm(id = null) {
  const form = $('#personForm');
  form.reset(); form.classList.remove('was-validated'); form.elements.id.value = '';
  if (id) {
    const row = await api(`/api/persons/${id}`);
    setForm(form, { id: row.id, householdCode: row.household_code, citizenCode: row.citizen_code, fullName: row.full_name, gender: row.gender, dateOfBirth: row.date_of_birth, identityNumber: row.identity_number, phone: row.phone, relationship: row.relationship, ethnicity: row.ethnicity, religion: row.religion, occupation: row.occupation, educationLevel: row.education_level, maritalStatus: row.marital_status, residency_status: row.residency_status, presenceStatus: row.presence_status, status: row.life_status, currentAddress: row.current_address });
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
    App.modals.household.hide(); showToast('Đã lưu hộ dân'); loadHouseholds(); loadDashboard(); refreshLoginConfig();
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
    App.modals.person.hide(); showToast('Đã lưu nhân khẩu'); loadPersons(); loadHouseholds(); loadDashboard(); refreshLoginConfig();
  } catch (error) { showToast(error.message, 'danger'); }
}

async function showHousehold(id) {
  try {
    const row = await api(`/api/households/${id}`);
    const members = await api('/api/persons?' + new URLSearchParams({ householdId: row.household_code, pageSize: 100 }).toString());
    $('#detailTitle').textContent = 'Chi tiết hộ dân';
    $('#detailBody').innerHTML = details([['Mã hộ', row.household_code], ['Chủ hộ', row.head_citizen_name], ['Địa chỉ', row.address], ['Số điện thoại', row.phone], ['Ở nhà', row.at_home_count || 0], ['Đi vắng', row.away_count || 0], ['Diện hộ', stripTags(householdBadges(row))], ['Ghi chú', row.note]]) + memberTable(members.items || []);
    App.modals.detail.show();
  } catch (error) { showToast(error.message, 'danger'); }
}

async function showPerson(id) {
  try {
    const row = await api(`/api/persons/${id}`);
    $('#detailTitle').textContent = 'Chi tiết nhân khẩu';
    $('#detailBody').innerHTML = details([['Mã hộ', row.household_code], ['Mã nhân khẩu', row.citizen_code], ['Họ tên', row.full_name], ['Giới tính', row.gender], ['Ngày sinh', formatDate(row.date_of_birth)], ['CCCD', row.identity_number], ['Số điện thoại', row.phone], ['Quan hệ', row.relationship], ['Dân tộc', row.ethnicity], ['Tôn giáo', row.religion], ['Nghề nghiệp', row.occupation], ['Học vấn', row.education_level], ['Hôn nhân', row.marital_status], ['Thường trú', residencyLabel(row.residency_status)], ['Hiện tại', presenceLabel(row.presence_status)], ['Trạng thái', lifeLabel(row.life_status)], ['Địa chỉ hiện tại', row.current_address]]);
    App.modals.detail.show();
  } catch (error) { showToast(error.message, 'danger'); }
}

async function deleteHousehold(id) {
  if (!confirm('Xóa hộ dân này?')) return;
  try { await api(`/api/households/${id}`, { method: 'DELETE' }); showToast('Đã xóa hộ dân'); loadHouseholds(); loadDashboard(); refreshLoginConfig(); }
  catch (error) { showToast(error.message, 'danger'); }
}
async function deletePerson(id) {
  if (!confirm('Xóa nhân khẩu này?')) return;
  try { await api(`/api/persons/${id}`, { method: 'DELETE' }); showToast('Đã xóa nhân khẩu'); loadPersons(); loadHouseholds(); loadDashboard(); refreshLoginConfig(); }
  catch (error) { showToast(error.message, 'danger'); }
}
async function bulkDeleteHouseholds() { await bulkDelete('.household-check:checked', '/api/households/bulk-delete', loadHouseholds, 'hộ dân'); }
async function bulkDeletePersons() { await bulkDelete('.person-check:checked', '/api/persons/bulk-delete', loadPersons, 'nhân khẩu'); }
async function bulkDelete(selector, url, reload, label) {
  const ids = $$(selector).map(c => Number(c.value));
  if (!ids.length) return showToast('Chưa chọn dữ liệu cần xóa', 'warning');
  if (!confirm(`Xóa ${ids.length} ${label} đã chọn?`)) return;
  try {
    const result = await api(url, { method: 'POST', body: { ids } });
    showToast(`Đã xóa ${result.success || 0} ${label}` + (result.errors?.length ? `, lỗi ${result.errors.length}` : ''));
    reload(); loadDashboard(); refreshLoginConfig();
  } catch (error) { showToast(error.message, 'danger'); }
}

async function api(url, options = {}) {
  setLoading(true);
  try {
    const headers = { 'Accept': 'application/json' };
    if (options.body) headers['Content-Type'] = 'application/json';
    if (App.token && !options.public) headers.Authorization = `Bearer ${App.token}`;
    const response = await fetch(url, { method: options.method || 'GET', headers, body: options.body ? JSON.stringify(options.body) : undefined });
    const payload = await response.json().catch(() => null);
    if (response.status === 401 && !options.public) logout();
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
function normalizeSearchText(value) {
  return String(value || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'd');
}
async function fetchAllPaged(basePath, extra = {}) {
  const pageSize = 100;
  let page = 1;
  let all = [];
  let totalPages = 1;
  do {
    const params = new URLSearchParams({ ...extra, page, pageSize });
    const data = await api(basePath + '?' + params.toString());
    const items = data.items || [];
    all = all.concat(items);
    totalPages = Number(data.totalPages || Math.ceil(Number(data.total || all.length) / pageSize) || 1);
    if (!items.length) break;
    page += 1;
  } while (page <= totalPages && page <= 200);
  return all;
}
function ageFromDate(value) {
  if (!value) return null;
  const year = Number(String(value).slice(0, 4));
  if (!year) return null;
  return new Date().getFullYear() - year;
}
function buildAgeGroups(persons) {
  const groups = [
    { label: '0-5 tuổi', value: 0 },
    { label: '6-14 tuổi', value: 0 },
    { label: '15-17 tuổi', value: 0 },
    { label: '18-59 tuổi', value: 0 },
    { label: 'Trên 60 tuổi', value: 0 }
  ];
  (persons || []).forEach(person => {
    const age = ageFromDate(person.date_of_birth);
    if (age === null) return;
    if (age <= 5) groups[0].value += 1;
    else if (age <= 14) groups[1].value += 1;
    else if (age <= 17) groups[2].value += 1;
    else if (age <= 59) groups[3].value += 1;
    else groups[4].value += 1;
  });
  return groups;
}
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
function memberTable(items) {
  const rows = items.map(row => `<tr><td>${escapeHtml(row.household_code || '')}</td><td>${escapeHtml(row.citizen_code || '')}</td><td>${escapeHtml(row.full_name || '')}</td><td>${formatDate(row.date_of_birth)}</td><td>${escapeHtml(row.identity_number || '')}</td><td>${escapeHtml(row.household_address || '')}</td><td>${escapeHtml(row.phone || '')}</td></tr>`).join('') || `<tr><td colspan="7" class="text-center text-muted py-3">Chưa có thành viên</td></tr>`;
  return `<h6 class="mt-4 mb-2">Thành viên trong hộ</h6><div class="table-responsive"><table class="table table-sm table-bordered align-middle"><thead><tr><th>Mã hộ</th><th>Mã nhân khẩu</th><th>Họ tên</th><th>Ngày sinh</th><th>CCCD</th><th>Địa chỉ</th><th>Số điện thoại</th></tr></thead><tbody>${rows}</tbody></table></div>`;
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
  const page = Math.max(1, Number(data.page || 1));
  const pageSize = Math.max(1, Number(data.pageSize || App.households?.pageSize || App.persons?.pageSize || 20));
  const total = Number(data.total || 0);
  const totalPages = Math.max(1, Number(data.totalPages || Math.ceil(total / pageSize) || 1));
  const host = $(selector);
  if (!host) return;
  host.innerHTML = `<span class="text-muted small">Trang ${page}/${totalPages} - ${number(total)} dòng</span><button class="btn btn-outline-secondary btn-sm" ${page <= 1 ? 'disabled' : ''} data-page="${page - 1}">Trước</button><button class="btn btn-outline-secondary btn-sm" ${page >= totalPages ? 'disabled' : ''} data-page="${page + 1}">Sau</button>`;
  $$(`${selector} button`).forEach(btn => btn.addEventListener('click', () => {
    const nextPage = Number(btn.dataset.page || 1);
    if (nextPage >= 1 && nextPage <= totalPages) go(nextPage);
  }));
}
function renderChart(selector, items) {
  const host = $(selector);
  const normalized = (items || []).map(item => ({ label: item.label || 'Khác', value: Number(item.value || 0) }));
  const total = normalized.reduce((sum, item) => sum + item.value, 0);
  if (!host) return;
  if (!total) {
    host.innerHTML = '<p class="text-muted mb-0">Chưa có dữ liệu</p>';
    return;
  }
  host.innerHTML = '<div class="percent-chart-list ' + (normalized.length === 1 ? 'single' : '') + '">' + normalized.map(item => {
    const percent = Math.round(item.value * 100 / total);
    const sweep = Math.max(2, percent);
    return '<div class="percent-chart-item">'
      + '<div class="percent-donut" style="--percent:' + sweep + '"><span>' + percent + '%</span></div>'
      + '<div class="percent-chart-meta"><strong>' + escapeHtml(item.label || 'Khác') + '</strong><small>' + number(item.value) + '</small></div>'
      + '</div>';
  }).join('') + '</div>';
}
function details(rows) {
  return `<div class="detail-grid">${rows.map(([label, value]) => `<div class="detail-item"><div class="detail-label">${escapeHtml(label)}</div><div class="detail-value">${escapeHtml(value ?? '')}</div></div>`).join('')}</div>`;
}
function showToast(message, type = 'success') {
  const id = 'toast-' + Date.now();
  $('#toastHost').insertAdjacentHTML('beforeend', `<div id="${id}" class="toast align-items-center text-bg-${type} border-0" role="alert"><div class="d-flex"><div class="toast-body">${escapeHtml(message)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Đóng"></button></div></div>`);
  const el = $('#' + id);
  const toast = new bootstrap.Toast(el, { delay: 3500 });
  toast.show();
  el.addEventListener('hidden.bs.toast', () => el.remove());
}


function initLoginExperience() {
  const loginView = $('#loginView');
  if (!loginView || loginView.dataset.loginReady === '1') return;
  loginView.dataset.loginReady = '1';

  const updateClock = () => {
    const now = new Date();
    const weekday = $('#loginWeekday');
    const date = $('#loginDate');
    const clock = $('#loginClock');
    if (weekday) weekday.textContent = new Intl.DateTimeFormat('vi-VN', { weekday: 'long' }).format(now);
    if (date) date.textContent = new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(now);
    if (clock) clock.textContent = new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).format(now);
  };
  updateClock();
  setInterval(updateClock, 1000);
  startTopbarClock();

  const toggle = $('[data-password-toggle]', loginView);
  const password = $('#loginPassword');
  if (toggle && password) {
    toggle.addEventListener('click', () => {
      const visible = password.type === 'text';
      password.type = visible ? 'password' : 'text';
      toggle.setAttribute('aria-label', visible ? 'Hiện mật khẩu' : 'Ẩn mật khẩu');
      toggle.innerHTML = '<i class="fa-solid ' + (visible ? 'fa-eye' : 'fa-eye-slash') + '" aria-hidden="true"></i>';
    });
  }

  hydrateLoginIntro();
}

async function hydrateLoginIntro() {
  await refreshLoginConfig();
}

async function refreshLoginConfig() {
  try {
    const data = await loginFetchJson('/api/public/login-config', true);
    if (data?.settings) applyLoginSettings(data.settings);
    if (data?.metrics) updateLoginStats(data.metrics);
  } catch (_) {}
}
window.refreshLoginConfig = refreshLoginConfig;

async function loginFetchJson(url, isPublic = false) {
  const headers = { Accept: 'application/json' };
  if (!isPublic && App.token) headers.Authorization = 'Bearer ' + App.token;
  const response = await fetch(url, { headers, cache: 'no-store' });
  const payload = await response.json().catch(() => null);
  if (!response.ok || !payload?.ok) throw new Error('Không tải được dữ liệu');
  return payload.data;
}

function updateLoginStats(metrics) {
  const data = { ...metrics };
  DASHBOARD_STAT_CONFIG.forEach(item => {
    const el = $('[data-stat="' + item.key + '"]', $('#loginStats') || document);
    if (el) animateLoginNumber(el, Number(data[item.key] || 0));
  });
}

function applyLoginSettings(settings) {
  setText('#loginSystemName', settings.systemName || 'Hệ thống Quản lý Hành chính');
  setText('#loginHamletName', settings.hamletName || 'Thôn 09');
  setText('#loginCommuneName', settings.communeName || 'Xã Hồng Phong');
  setText('#loginSlogan', settings.slogan || 'Vì Nhân dân phục vụ');
  setText('#loginVersion', 'Phiên bản ' + (settings.softwareVersion || 'v2.0'));
  setText('#loginCopyright', settings.copyright || ('© ' + (settings.hamletName || 'Thôn 09') + ' - ' + (settings.communeName || 'Xã Hồng Phong')));
  updateLoginHistory(settings);
  updateLoginLogo(settings.logoUrl || '');
  updateLoginBackground(settings);
}

function setText(selector, value) { const el = $(selector); if (el) el.textContent = value; }

function updateLoginHistory(settings) {
  const host = $('#loginHistoryText');
  if (!host) return;
  const value = settings.hamletHistory || settings.introduction || settings.history || settings.aboutHamlet || settings.about;
  if (value) host.innerHTML = sanitizeRichHtml(value);
}

function sanitizeRichHtml(value) {
  const template = document.createElement('template');
  template.innerHTML = String(value || '');
  template.content.querySelectorAll('script,style,iframe,object,embed').forEach(el => el.remove());
  template.content.querySelectorAll('*').forEach(el => {
    Array.from(el.attributes).forEach(attr => {
      const name = attr.name.toLowerCase();
      if (name.startsWith('on') || (['href','src'].includes(name) && /^javascript:/i.test(attr.value))) el.removeAttribute(attr.name);
    });
  });
  return template.innerHTML;
}

function updateLoginLogo(url) {
  const logo = $('#loginLogo');
  if (!logo) return;
  if (!logo.dataset.defaultHtml) logo.dataset.defaultHtml = logo.innerHTML;
  if (url) {
    logo.classList.add('login-logo-image');
    logo.innerHTML = '<img src="' + escapeHtml(url) + '" alt="Logo Thôn 09" decoding="async">';
  } else {
    logo.classList.remove('login-logo-image');
    logo.innerHTML = logo.dataset.defaultHtml;
  }
}

function updateLoginBackground(settings) {
  const intro = $('#loginView');
  if (!intro) return;
  const images = parseBackgroundImages(settings);
  if (!images.length) { intro.style.removeProperty('--login-bg'); return; }
  let index = 0;
  const apply = () => { intro.style.setProperty('--login-bg', 'url("' + images[index] + '")'); };
  apply();
  clearInterval(window.__thon09LoginBgTimer);
  if (images.length > 1) {
    const interval = Math.max(2500, Number(settings.backgroundInterval || 6000));
    window.__thon09LoginBgTimer = setInterval(() => { index = (index + 1) % images.length; apply(); }, interval);
  }
}

function parseBackgroundImages(settings) {
  const raw = settings.backgroundImages || settings.backgroundUrl || '';
  if (Array.isArray(raw)) return raw.filter(Boolean);
  const text = String(raw || '').trim();
  if (!text) return [];
  try { const parsed = JSON.parse(text); if (Array.isArray(parsed)) return parsed.filter(Boolean); } catch (_) {}
  return text.split(/[\n,]+/).map(item => item.trim()).filter(Boolean);
}

function animateLoginNumber(el, target) {
  const end = Math.max(0, Number(target || 0));
  const duration = 650;
  const startTime = performance.now();
  const step = now => {
    const progress = Math.min(1, (now - startTime) / duration);
    el.textContent = number(Math.round(end * progress));
    if (progress < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}


window.addEventListener('thon09:data-mutated', () => { if (typeof refreshLoginConfig === 'function') refreshLoginConfig(); });

function startTopbarClock() {
  if (window.__thon09TopbarClockStarted) return;
  window.__thon09TopbarClockStarted = true;
  const tick = () => {
    const el = document.querySelector('#topbarClock');
    if (!el) return;
    const now = new Date();
    const date = new Intl.DateTimeFormat('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' }).format(now);
    const time = new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).format(now);
    el.innerHTML = '<i class="fa-regular fa-calendar"></i> ' + date + ' <i class="fa-regular fa-clock ms-2"></i> ' + time;
  };
  tick();
  setInterval(tick, 1000);
}
