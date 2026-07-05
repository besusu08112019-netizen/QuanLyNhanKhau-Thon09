const App = {
  token: localStorage.getItem('thon09_token') || '',
  user: JSON.parse(localStorage.getItem('thon09_user') || 'null'),
  screen: localStorage.getItem('thon09_screen') || 'dashboard',
  households: { page: 1, pageSize: 20, search: '', category: '', status: '' },
  gis: { areas: [], map: null, layerGroup: null, drawControl: null, drawnLayer: null, selectedArea: null },
  persons: { page: 1, pageSize: 20, search: '', householdId: '' },
  modals: {},
  dictionaries: {
    ethnicities: ['Kinh','TÃ y','ThÃ¡i','MÆ°á»ng','Khmer','Hoa','NÃ¹ng','Hmong','Dao','Gia Rai','ÃŠ ÄÃª','Ba Na','SÃ¡n Chay','ChÄƒm','CÆ¡ Ho','XÆ¡ ÄÄƒng','SÃ¡n DÃ¬u','HrÃª','Ra Glai','MnÃ´ng','Thá»•','StiÃªng','KhÆ¡ MÃº','Bru - VÃ¢n Kiá»u','CÆ¡ Tu','GiÃ¡y','TÃ  Ã”i','Máº¡','Co','ChÆ¡ Ro','Xinh Mun','HÃ  NhÃ¬','Chu Ru','LÃ o','La ChÃ­','La Ha','PhÃ¹ LÃ¡','La Há»§','Lá»±','LÃ´ LÃ´','Chá»©t','Máº£ng','PÃ  Tháº»n','CÆ¡ Lao','Cá»‘ng','Bá»‘ Y','Si La','Pu PÃ©o','BrÃ¢u','Æ  Äu','RÆ¡ MÄƒm','NgÃ¡i','Cá» Ho','KhÃ¡c'],
    religions: ['KhÃ´ng','Pháº­t giÃ¡o','CÃ´ng giÃ¡o','Tin lÃ nh','Cao ÄÃ i','HÃ²a Háº£o','Há»“i giÃ¡o','TÃ­n ngÆ°á»¡ng dÃ¢n gian','KhÃ¡c'],
    occupations: ['NÃ´ng nghiá»‡p','CÃ´ng nhÃ¢n','CÃ¡n bá»™','CÃ´ng chá»©c','ViÃªn chá»©c','Kinh doanh','Lao Ä‘á»™ng tá»± do','Há»c sinh','Sinh viÃªn','Ná»™i trá»£','HÆ°u trÃ­','KhÃ´ng cÃ³ viá»‡c lÃ m','KhÃ¡c'],
    relationships: ['Chá»§ há»™','Vá»£','Chá»“ng','Con','Cha','Máº¹','Ã”ng','BÃ ','ChÃ¡u','Anh','Chá»‹','Em','NgÆ°á»i á»Ÿ cÃ¹ng','KhÃ¡c'],
    educationLevels: ['ChÆ°a Ä‘i há»c','Tiá»ƒu há»c','Trung há»c cÆ¡ sá»Ÿ','Trung há»c phá»• thÃ´ng','Trung cáº¥p','Cao Ä‘áº³ng','Äáº¡i há»c','Sau Ä‘áº¡i há»c','KhÃ¡c'],
    maritalStatuses: ['ChÆ°a káº¿t hÃ´n','ÄÃ£ káº¿t hÃ´n','Ly hÃ´n','GÃ³a','KhÃ¡c']
  }
};

window.App = App;

const DASHBOARD_STAT_CONFIG = [
  { key: 'total_households', label: 'Tá»•ng sá»‘ há»™', icon: 'fa-house', loginClass: 'stat-house', unit: 'há»™' },
  { key: 'total_citizens', label: 'Tá»•ng nhÃ¢n kháº©u', icon: 'fa-users', loginClass: 'stat-pop', unit: 'ngÆ°á»i' },
  { key: 'party_member_count', label: 'Äáº£ng viÃªn', icon: 'fa-landmark-flag', loginClass: 'stat-party', unit: 'ngÆ°á»i' },
  { key: 'male_count', label: 'Nam', icon: 'fa-person', dashboardIcon: 'fa-mars', loginClass: 'stat-male', unit: 'ngÆ°á»i' },
  { key: 'female_count', label: 'Ná»¯', icon: 'fa-person-dress', dashboardIcon: 'fa-venus', loginClass: 'stat-female', unit: 'ngÆ°á»i' },
  { key: 'away_count', label: 'Táº¡m váº¯ng', icon: 'fa-person-walking-arrow-right', loginClass: 'stat-away', unit: 'ngÆ°á»i' }
];

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

window.switchScreen = switchScreen;
if (!window.__thon09NavDelegated) {
  window.__thon09NavDelegated = true;
  document.addEventListener('click', event => {
    const navButton = event.target.closest('.sidebar .nav-link[data-screen]');
    if (!navButton) return;
    event.preventDefault();
    switchScreen(navButton.dataset.screen);
  });
}

document.addEventListener('DOMContentLoaded', init);

function init() {
  App.modals.household = new bootstrap.Modal($('#householdModal'));
  App.modals.person = new bootstrap.Modal($('#personModal'));
  App.modals.detail = new bootstrap.Modal($('#detailModal'));
  fillDictionaries();
  bindEvents();
  initNotificationPopover();
  initLoginExperience();
  App.token ? showApp() : showLogin();
}

function bindEvents() {
  $('#loginForm').addEventListener('submit', login);
  $('#logoutBtn').addEventListener('click', logout);
  $('#sidebarToggle').addEventListener('click', toggleMobileSidebar);
  ensureMobileSidebarBackdrop();
  startResponsiveTableObserver();
  const dashboardFilters = $('#dashboardFilters');
  if (dashboardFilters) dashboardFilters.addEventListener('submit', event => { event.preventDefault(); loadDashboard(); refreshLoginConfig({ force: true }); });
  const dashboardResetBtn = $('#dashboardResetBtn');
  if (dashboardResetBtn) dashboardResetBtn.addEventListener('click', () => { if (dashboardFilters) dashboardFilters.reset(); loadDashboard(); refreshLoginConfig({ force: true }); });
  $('#householdAddBtn').addEventListener('click', () => openHouseholdForm());
  const personAddBtn = $('#personAddBtn');
  if (personAddBtn) personAddBtn.addEventListener('click', () => openPersonForm());
  $('#householdForm').addEventListener('submit', saveHousehold);
  $('#personForm').addEventListener('submit', savePerson);
  $('#householdSearch').addEventListener('input', debounce(() => { App.households.search = $('#householdSearch').value.trim(); App.households.page = 1; loadHouseholds(); }, 350));
  const personSearchInput = $('#personSearch');
  if (personSearchInput) personSearchInput.addEventListener('input', debounce(() => { App.persons.search = personSearchInput.value.trim(); App.persons.page = 1; loadPersons(); }, 350));
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
  if (personCheckAll) personCheckAll.addEventListener('change', e => { $$('.person-check').forEach(c => c.checked = e.target.checked); updateBulkDeleteButtons(); });
  const householdCheckAll = $('#householdCheckAll');
  if (householdCheckAll) householdCheckAll.addEventListener('change', () => updateBulkDeleteButtons());
  document.addEventListener('change', event => { if (event.target.matches('.household-check,.person-check')) updateBulkDeleteButtons(); });
  $('#householdBulkDeleteBtn').addEventListener('click', bulkDeleteHouseholds);
  const personBulkDeleteBtn = $('#personBulkDeleteBtn');
  if (personBulkDeleteBtn) personBulkDeleteBtn.addEventListener('click', bulkDeletePersons);
  updateBulkDeleteButtons();
}

function fillDictionaries() {
  $$('[data-options]').forEach(select => {
    const key = select.dataset.options;
    select.innerHTML = App.dictionaries[key].map(value => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join('');
  });
}

function renderTopbarUser() {
  const host = $('#currentUser');
  if (!host) return;
  host.textContent = '';
  if (!App.user) return;
  const email = document.createElement('span');
  email.className = 'topbar-user-email';
  email.textContent = App.user.email || '';
  const role = document.createElement('span');
  role.className = 'topbar-user-role';
  role.textContent = roleLabel(App.user.role);
  host.append(email, role);
}

function markResponsiveTableWrappers(root = document) {
  root.querySelectorAll('.table-responsive').forEach(wrapper => {
    wrapper.classList.add('module-card-list');
  });
}

function applyResponsiveTableLabels(root = document) {
  markResponsiveTableWrappers(root);
  const tables = new Set();
  if (root?.matches?.('.table-responsive table')) tables.add(root);
  const parentTable = root?.closest?.('.table-responsive table');
  if (parentTable) tables.add(parentTable);
  root.querySelectorAll?.('.table-responsive table').forEach(table => tables.add(table));
  tables.forEach(table => {
    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
    if (!headers.length) return;
    table.querySelectorAll('tbody tr').forEach(row => {
      if (row.classList.contains('group-row')) {
        row.dataset.mobileRole = 'group';
        return;
      }
      let titleAssigned = false;
      let headerMetaAssigned = false;
      Array.from(row.children).forEach((cell, index) => {
        const label = headers[index] || '';
        if (!cell.hasAttribute('data-label')) cell.setAttribute('data-label', label);
        const normalizedLabel = normalizeSearchText(label);
        const textValue = cell.textContent.replace(/\s+/g, ' ').trim();
        const normalizedValue = normalizeSearchText(textValue);
        const hasControl = !!cell.querySelector('button, .btn, a[href], input, select, textarea');
        const hasRealValue = hasControl || !!textValue && !isEmptyMobileCardValue(normalizedValue);
        cell.dataset.mobileRole = '';
        cell.toggleAttribute('data-mobile-empty', !hasRealValue);
        delete cell.dataset.mobileTone;
        if (cell.querySelector('input[type="checkbox"]') && index === 0) {
          cell.dataset.mobileRole = 'select';
          return;
        }
        if (normalizedLabel.includes('thao tac') && cell.querySelector('button, .btn, a[href], input, select')) {
          cell.dataset.mobileRole = 'actions';
          return;
        }
        if (!titleAssigned && isMobileCardTitleLabel(normalizedLabel, index)) {
          cell.dataset.mobileRole = 'title';
          titleAssigned = true;
          return;
        }
        if (isMobileCardAddressLabel(normalizedLabel)) cell.dataset.mobileRole = 'address';
        else if (isMobileCardStatLabel(normalizedLabel)) cell.dataset.mobileRole = 'stat';
        else if (isMobileCardBadgeLabel(normalizedLabel)) {
          cell.dataset.mobileRole = 'badge';
          cell.dataset.mobileTone = getMobileBadgeTone(normalizedLabel, normalizedValue);
          normalizeMobileBadgeText(cell, normalizedLabel, normalizedValue);
        }
        else if (isMobileCardMetaLabel(normalizedLabel)) {
          if (!headerMetaAssigned && isMobileCardHeaderMetaLabel(normalizedLabel)) {
            cell.dataset.mobileRole = 'header-meta';
            headerMetaAssigned = true;
          } else {
            cell.dataset.mobileRole = 'meta';
          }
        }
      });
    });
  });
  decorateMobileCardActions(root);
  if (window.thon09EnhanceDesignSystem) window.thon09EnhanceDesignSystem(root);
}

function isMobileCardTitleLabel(label, index) {
  return ['chu ho', 'ho va ten', 'ho ten', 'ten dang nhap', 'username', 'nguoi dung', 'tieu de', 'ten file', 'file', 'chi tieu'].some(key => label.includes(key));
}

function isMobileCardMetaLabel(label) {
  return ['ma ho', 'ma nhan khau', 'cccd', 'so dinh danh'].some(key => label.includes(key));
}

function isMobileCardHeaderMetaLabel(label) {
  return ['ma ho', 'ma nhan khau', 'username', 'ten dang nhap'].some(key => label.includes(key));
}

function isMobileCardAddressLabel(label) {
  return ['dia chi'].some(key => label.includes(key));
}

function isMobileCardStatLabel(label) {
  return ['o nha', 'di vang', 'so nhan khau', 'tong so', 'so luong'].some(key => label.includes(key));
}

function isMobileCardBadgeLabel(label) {
  return ['dien ho', 'trang thai', 'dang vien', 'cu tru', 'vai tro', 'status'].some(key => label.includes(key));
}

function isEmptyMobileCardValue(value) {
  return ['', '-', '--', '---', 'na', 'n/a', 'null', 'undefined', 'khong co du lieu'].includes(value);
}

function getMobileBadgeTone(label, value) {
  if (label.includes('dien ho')) {
    if (value.includes('ngheo')) return 'danger';
    if (value.includes('can ngheo') || value.includes('moi thoat')) return 'warning';
    if (value.includes('chinh sach') || value.includes('co cong')) return 'info';
    if (value === 'khong' || value.includes('binh thuong') || value.includes('thuong')) return 'neutral';
  }
  if (label.includes('trang thai') || label.includes('status')) {
    if (value.includes('khoa') || value.includes('ngung') || value.includes('xoa')) return 'danger';
    if (value.includes('hoat dong') || value.includes('active')) return 'success';
  }
  if (label.includes('dang vien') || label.includes('vai tro')) {
    if (value === 'co' || value.includes('admin') || value.includes('dang vien')) return 'success';
    if (value === 'khong') return 'neutral';
  }
  if (label.includes('cu tru')) {
    if (value.includes('tam vang')) return 'warning';
    if (value.includes('tam tru')) return 'info';
    if (value.includes('thuong tru')) return 'success';
  }
  return 'neutral';
}

function normalizeMobileBadgeText(cell, label, value) {
  if (!label.includes('dien ho') || value !== 'khong') return;
  const target = cell.querySelector('.badge, .badge-soft, .person-badge, span, a, button') || cell;
  target.textContent = 'Khong uu tien';
}

function startResponsiveTableObserver() {
  applyResponsiveTableLabels();
}

function refreshUiEnhancements(root = document) {
  applyResponsiveTableLabels(root);
  if (window.thon09SyncResponsiveTableLabels) window.thon09SyncResponsiveTableLabels(root);
}

function decorateMobileCardActions(root = document) {
  root.querySelectorAll?.('td[data-mobile-role="actions"] button, td[data-mobile-role="actions"] .btn').forEach(button => {
    const text = normalizeSearchText(button.textContent || '');
    if (text.includes('xem')) button.dataset.mobileAction = 'view';
    else if (text.includes('sua')) button.dataset.mobileAction = 'edit';
    else if (text.includes('xoa')) button.dataset.mobileAction = 'delete';
  });
}

function ensureMobileSidebarBackdrop() {
  if (document.querySelector('.sidebar-backdrop')) return;
  const backdrop = document.createElement('div');
  backdrop.className = 'sidebar-backdrop';
  backdrop.setAttribute('aria-hidden', 'true');
  document.body.appendChild(backdrop);
  backdrop.addEventListener('click', closeMobileSidebar);
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeMobileSidebar();
  });
}

function toggleMobileSidebar() {
  const sidebar = $('.sidebar');
  if (!sidebar) return;
  const willOpen = !sidebar.classList.contains('open');
  sidebar.classList.toggle('open', willOpen);
  document.body.classList.toggle('sidebar-open', willOpen);
}

function closeMobileSidebar() {
  const sidebar = $('.sidebar');
  if (sidebar) sidebar.classList.remove('open');
  document.body.classList.remove('sidebar-open');
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
    window.__thon09SessionExpired = false;
    localStorage.setItem('thon09_token', App.token);
    localStorage.setItem('thon09_user', JSON.stringify(App.user));
    window.App = App;
    showToast('ÄÄƒng nháº­p thÃ nh cÃ´ng');
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
  document.dispatchEvent(new CustomEvent('thon09:auth-state', { detail: { authenticated: false } }));
  showLogin();
}

function showLogin() { $('#loginView').classList.remove('d-none'); $('#appView').classList.add('d-none'); initLoginExperience(); }
function showApp() {
  $('#loginView').classList.add('d-none');
  $('#appView').classList.remove('d-none');
  renderTopbarUser();
  if (typeof window.ensureAdminScreens === 'function') window.ensureAdminScreens();
  switchScreen(App.screen);
  document.dispatchEvent(new CustomEvent('thon09:auth-state', { detail: { authenticated: true } }));
}

function normalizeAppHeader(screen) {
  const screenLabels = { dashboard: 'Dashboard', gis: 'Báº£n Ä‘á»“ Ä‘á»‹a bÃ n', households: 'Quáº£n lÃ½ há»™ gia Ä‘Ã¬nh', persons: 'Quáº£n lÃ½ nhÃ¢n kháº©u', temporaryResidence: 'Táº¡m trÃº', temporaryAbsence: 'Táº¡m váº¯ng', movements: 'Biáº¿n Ä‘á»™ng nhÃ¢n kháº©u', reports: 'BÃ¡o cÃ¡o thá»‘ng kÃª', import: 'Import dá»¯ liá»‡u', export: 'Export Excel', exportExcel: 'Export Excel', printForms: 'In biá»ƒu máº«u', users: 'Quáº£n lÃ½ tÃ i khoáº£n', logs: 'Nháº­t kÃ½ há»‡ thá»‘ng', appearance: 'Cáº¥u hÃ¬nh giao diá»‡n', settings: 'Cáº¥u hÃ¬nh há»‡ thá»‘ng', backups: 'Sao lÆ°u dá»¯ liá»‡u', restore: 'KhÃ´i phá»¥c dá»¯ liá»‡u', permissions: 'PhÃ¢n quyá»n' };
  const label = screenLabels[screen] || 'Dashboard';
  const title = $('#screenTitle');
  const breadcrumb = $('#breadcrumbTrail');
  if (title) title.textContent = label;
  if (breadcrumb) breadcrumb.textContent = 'Trang chá»§ / ' + label;
  $$('.topbar-title-block small:not(#breadcrumbTrail), .topbar-title-block .text-muted:not(#breadcrumbTrail), .topbar > div:first-of-type small:not(#breadcrumbTrail), .topbar > div:first-of-type .text-muted:not(#breadcrumbTrail)').forEach(el => el.remove());
  $$('.dashboard-hero-row, .module-page-head > div, .person-page-head > div, .report-page-head, .screen > .admin-heading > div').forEach(el => el.remove());
}

function switchScreen(screen) {
  const requestedScreen = screen;
  const screenAliases = { export: 'exportExcel' };
  screen = screenAliases[screen] || screen;
  if (!document.querySelector('#' + screen + 'Screen')) screen = 'dashboard';
  App.screen = screen;
  localStorage.setItem('thon09_screen', screen);
  $$('.sidebar .nav-link').forEach(btn => btn.classList.toggle('active', btn.dataset.screen === screen || btn.dataset.screen === requestedScreen));
  $$('.screen').forEach(el => el.classList.remove('active'));
  $(`#${screen}Screen`).classList.add('active');
  normalizeAppHeader(screen);
  closeMobileSidebar();
  if (screen === 'dashboard') { loadDashboard(); refreshLoginConfig(); }
  if (screen === 'gis') loadGisMap();
  if (screen === 'households') loadHouseholds();
  if (screen === 'persons') loadPersons();
  document.dispatchEvent(new CustomEvent('thon09:screen-change', { detail: { screen, requestedScreen } }));
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
    renderHouseholdTypeChart(charts.households || charts.poverty || [], metrics);
    renderLaborStatusChart(charts.labor || charts.occupations || [], metrics);
    updateDashboardGeneratedAt(data.generatedAt);
  } catch (error) {
    if (!String(error.message || '').includes('Ä‘Äƒng nháº­p')) showToast('KhÃ´ng táº£i Ä‘Æ°á»£c tá»•ng quan: ' + error.message, 'danger');
  }
}

function updateDashboardGeneratedAt(value) {
  const el = $('#dashboardGeneratedAt');
  if (!el) return;
  const date = value ? new Date(value) : new Date();
  el.textContent = 'Cáº­p nháº­t lÃºc ' + new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
}

function renderDashboardKpis(metrics) {
  const host = $('#dashboardKpis');
  if (!host) return;
  const cards = [
    { key: 'total_households', label: 'Tá»•ng sá»‘ há»™', unit: 'há»™', icon: 'fa-house-chimney', tone: 'green' },
    { key: 'total_citizens', label: 'Tá»•ng sá»‘ nhÃ¢n kháº©u', unit: 'ngÆ°á»i', icon: 'fa-users', tone: 'blue' },
    { key: 'party_member_count', label: 'Äáº£ng viÃªn', unit: 'ngÆ°á»i', icon: 'fa-star', tone: 'orange' },
    { key: 'male_count', label: 'Nam', unit: 'ngÆ°á»i', icon: 'fa-person', tone: 'cyan' },
    { key: 'female_count', label: 'Ná»¯', unit: 'ngÆ°á»i', icon: 'fa-person-dress', tone: 'pink' },
    { key: 'away_count', label: 'Táº¡m váº¯ng', unit: 'ngÆ°á»i', icon: 'fa-person-walking-arrow-right', tone: 'purple' }
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
  return (items || []).map(item => ({ label: item.label || 'KhÃ¡c', value: Number(item.value || 0) })).filter(item => item.value >= 0);
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

function chartEmpty(message = 'ChÆ°a cÃ³ dá»¯ liá»‡u') {
  return '<div class="dashboard-empty-chart">' + escapeHtml(message) + '</div>';
}

function renderAgeStructureChart(items) {
  const host = $('#ageStructureChart');
  if (!host) return;
  const buckets = [
    { key: '0_5', label: '0-5 tuá»•i', match: text => text.includes('0-5') },
    { key: '6_14', label: '6-14 tuá»•i', match: text => text.includes('6-14') },
    { key: '15_17', label: '15-17 tuá»•i', match: text => text.includes('15-17') },
    { key: '18_59', label: '18-59 tuá»•i', match: text => text.includes('18-59') },
    { key: '60_plus', label: 'Tá»« 60 tuá»•i trá»Ÿ lÃªn', match: text => text.includes('60') && (text.includes('tro len') || text.includes('trá»Ÿ lÃªn') || text.includes('tren') || text.includes('trÃªn') || text.includes('+')) }
  ];
  const source = normalizeChartItems(items);
  const normalized = buckets.map(bucket => {
    const value = source.reduce((sum, item) => {
      const text = normalizeSearchText(item.label || '');
      return sum + (bucket.match(text) ? Number(item.value || 0) : 0);
    }, 0);
    return { label: bucket.label, value };
  });
  const total = normalized.reduce((sum, item) => sum + item.value, 0);
  if (!total) { host.innerHTML = chartEmpty(); return; }
  host.innerHTML = '<div class="dashboard-age-layout">'
    + renderDonut(normalized, total, { centerLabel: 'Tá»•ng sá»‘', centerValue: number(total), centerUnit: 'ngÆ°á»i', className: 'dashboard-age-donut' })
    + '<div class="dashboard-legend">' + normalized.map((item, index) => renderLegendRow(item, total, index)).join('') + '</div>'
    + '</div>';
}
function renderMonthlyChangeChart(items, fallbackTotal) {
  const host = $('#populationMovementChart');
  if (!host) return;
  const source = normalizeChartItems(items).slice(-6);
  const rows = source.length ? source : [{ label: 'Hiá»‡n táº¡i', value: Number(fallbackTotal || 0) }];
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
  if (!normalized.length) normalized = [{ label: 'Nam', value: Number(metrics.male_count || 0) }, { label: 'Ná»¯', value: Number(metrics.female_count || 0) }];
  const total = normalized.reduce((sum, item) => sum + item.value, 0);
  if (!total) { host.innerHTML = chartEmpty(); return; }
  const male = normalized.find(item => normalizeSearchText(item.label).includes('nam')) || { label: 'Nam', value: Number(metrics.male_count || 0) };
  const female = normalized.find(item => normalizeSearchText(item.label).includes('nu')) || { label: 'Ná»¯', value: Number(metrics.female_count || 0) };
  host.innerHTML = '<div class="dashboard-gender-layout">'
    + renderSideStat('Nam', male.value, percent(male.value, total), 'blue', 'fa-mars')
    + renderDonut([male, female], total, { centerLabel: 'Tá»•ng sá»‘', centerValue: number(total), centerUnit: 'ngÆ°á»i', className: 'dashboard-gender-donut' })
    + renderSideStat('Ná»¯', female.value, percent(female.value, total), 'pink', 'fa-venus')
    + '</div>';
}

function renderPartyDashboardChart(items, metrics) {
  const host = $('#partyMemberChart');
  if (!host) return;
  const total = Number(metrics.total_citizens || 0);
  const party = Number(metrics.party_member_count || (normalizeChartItems(items)[0]?.value || 0));
  if (!total) { host.innerHTML = chartEmpty(); return; }
  const rows = [{ label: 'Äáº£ng viÃªn', value: party }, { label: 'CÃ²n láº¡i', value: Math.max(0, total - party) }];
  const rate = percent(party, total);
  host.innerHTML = '<div class="dashboard-party-layout">'
    + renderDonut(rows, total, { centerLabel: 'Tá»· lá»‡', centerValue: formatPercent(rate), centerUnit: 'Äáº£ng viÃªn', className: 'dashboard-party-donut' })
    + '<div class="dashboard-summary-box"><span>Tá»•ng sá»‘ Äáº£ng viÃªn</span><strong>' + number(party) + ' ngÆ°á»i</strong><small>TÃ­nh trÃªn tá»•ng sá»‘ ' + number(total) + ' nhÃ¢n kháº©u Ä‘ang quáº£n lÃ½ trong há»‡ thá»‘ng.</small></div>'
    + '</div>';
}

function renderHouseholdTypeChart(items, metrics) {
  const host = $('#householdTypeChart');
  if (!host) return;
  const rows = normalizeChartItems(items).filter(item => item.value > 0);
  const total = rows.reduce((sum, item) => sum + item.value, 0) || Number(metrics.total_households || 0);
  if (!total || !rows.length) { host.innerHTML = chartEmpty(); return; }
  host.innerHTML = '<div class="dashboard-household-type-layout">'
    + renderDonut(rows, total, { centerLabel: 'Tá»•ng sá»‘', centerValue: number(total), centerUnit: 'há»™', className: 'dashboard-household-type-donut' })
    + '<div class="dashboard-legend">' + rows.map((item, index) => renderLegendRow(item, total, index, 'há»™')).join('') + '</div>'
    + '</div>';
}

function renderLaborStatusChart(items, metrics) {
  const host = $('#laborStatusChart');
  if (!host) return;
  const rows = normalizeChartItems(items).filter(item => item.value > 0);
  const total = Number(metrics.total_citizens || rows.reduce((sum, item) => sum + item.value, 0) || 0);
  if (!total || !rows.length) { host.innerHTML = chartEmpty(); return; }
  const max = Math.max(...rows.map(item => item.value), 1);
  host.innerHTML = '<div class="dashboard-bar-chart">' + rows.map((item, index) => {
    const height = Math.max(8, Math.round(item.value * 100 / max));
    const rate = percent(item.value, total);
    return '<div class="dashboard-bar-item" title="' + escapeHtml(item.label + ': ' + number(item.value) + ' nhÃ¢n kháº©u (' + formatPercent(rate) + ')') + '">'
      + '<strong>' + number(item.value) + '</strong>'
      + '<div class="dashboard-bar-track"><span class="dashboard-bar-fill dashboard-bar-color-' + ((index % 6) + 1) + '" style="height:' + height + '%"></span></div>'
      + '<small>' + escapeHtml(item.label) + '<br><b>' + formatPercent(rate) + '</b></small>'
      + '</div>';
  }).join('') + '</div>';
}

function renderSideStat(label, value, rate, tone, icon) {
  return '<div class="dashboard-side-stat dashboard-side-' + tone + '"><span><i class="fa-solid ' + icon + '"></i></span><strong>' + number(value) + '</strong><small>' + escapeHtml(label) + ' - ' + formatPercent(rate) + '</small></div>';
}

function renderLegendRow(item, total, index, unit = 'ngÆ°á»i') {
  return '<div class="dashboard-legend-row"><i class="dashboard-dot dashboard-dot-' + (index + 1) + '"></i><span>' + escapeHtml(item.label) + '</span><strong>' + number(item.value) + ' ' + escapeHtml(unit) + ' (' + formatPercent(percent(item.value, total)) + ')</strong></div>';
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
    if (householdTotal) householdTotal.innerHTML = 'Tá»•ng sá»‘: <strong>' + number(total) + '</strong> há»™';
    $('#householdRows').innerHTML = items.map(row => '<tr>' +
      '<td><input type="checkbox" class="household-check" value="' + row.id + '"></td>' +
      '<td><button class="btn btn-link p-0 fw-semibold" onclick="showHousehold(' + row.id + ')">' + escapeHtml(row.household_code) + '</button></td>' +
      '<td>' + escapeHtml(row.head_citizen_name || '') + '</td>' +
      '<td>' + escapeHtml(row.address || '') + '</td>' +
      '<td>' + number(row.at_home_count || 0) + '</td>' +
      '<td>' + number(row.away_count || 0) + '</td>' +
      '<td>' + householdBadges(row) + '</td>' +
      '<td class="text-end"><button class="btn btn-sm btn-outline-secondary" onclick="showHousehold(' + row.id + ')">Xem</button> <button class="btn btn-sm btn-outline-primary" onclick="openHouseholdForm(' + row.id + ')">Sá»­a</button> <button class="btn btn-sm btn-outline-danger" onclick="deleteHousehold(' + row.id + ')">XÃ³a</button></td>' +
    '</tr>').join('') || emptyRow(8, 'KhÃ´ng cÃ³ dá»¯ liá»‡u');
    updateBulkDeleteButtons();
    renderPager('#householdPager', { total, page: App.households.page, pageSize: App.households.pageSize }, page => { App.households.page = page; loadHouseholds(); });
    refreshUiEnhancements($('#householdsScreen') || document);
  } catch (error) { showToast('KhÃ´ng táº£i Ä‘Æ°á»£c danh sÃ¡ch há»™ dÃ¢n: ' + error.message, 'danger'); }
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
      const filtered = allItems.filter(row => [row.full_name, row.citizen_code, row.identity_number, row.personal_id, row.national_id, row.phone, row.household_code, row.current_address, row.household_address]
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
      const code = row.household_code || 'ChÆ°a cÃ³ há»™';
      (acc[code] ||= []).push(row);
      return acc;
    }, {});
    $('#personRows').innerHTML = Object.entries(grouped).map(([code, rows]) => '<tr class="group-row"><td colspan="12">MÃ£ há»™: ' + escapeHtml(code) + '</td></tr>' + rows.map(personRow).join('')).join('') || '<tr><td colspan="12" class="text-center text-muted py-4">KhÃ´ng cÃ³ dá»¯ liá»‡u</td></tr>';
    updateBulkDeleteButtons();
    renderPager('#personPager', { total, page: App.persons.page, pageSize: App.persons.pageSize }, page => { App.persons.page = page; loadPersons(); });
    refreshUiEnhancements($('#personsScreen') || document);
  } catch (error) { showToast('KhÃ´ng táº£i Ä‘Æ°á»£c danh sÃ¡ch nhÃ¢n kháº©u: ' + error.message, 'danger'); }
}

function personRelationship(row) {
  return String(row.relationship || row.relationship_to_head || row.relationshipToHead || row.relation_to_head || row.household_relationship || row.member_relationship || '').trim();
}

function personAge(dateValue) {
  const raw = String(dateValue || '').trim();
  if (!raw) return '';
  let birth = null;
  const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (iso) birth = new Date(Number(iso[1]), Number(iso[2]) - 1, Number(iso[3]));
  const vn = !birth && raw.match(/^(\d{2})\/(\d{2})\/(\d{4})/);
  if (vn) birth = new Date(Number(vn[3]), Number(vn[2]) - 1, Number(vn[1]));
  if (!birth || Number.isNaN(birth.getTime())) return '';
  const today = new Date();
  let age = today.getFullYear() - birth.getFullYear();
  const hadBirthday = today.getMonth() > birth.getMonth() || (today.getMonth() === birth.getMonth() && today.getDate() >= birth.getDate());
  if (!hadBirthday) age -= 1;
  return age >= 0 ? age + ' tuá»•i' : '';
}

function renderPersonRows(items) {
  const rows = Array.isArray(items) ? items : [];
  if (!rows.length) return '<tr><td colspan="12" class="text-center text-muted py-4">Khong co du lieu</td></tr>';
  const groups = rows.reduce((acc, row) => {
    const code = String(row.household_code || row.householdCode || 'Chua co ho').trim();
    (acc[code] ||= []).push(row);
    return acc;
  }, {});
  return Object.entries(groups).map(([code, members]) => {
    const head = members.find(row => /chu ho/i.test(normalizeSearchText(personRelationship(row)))) || members[0] || {};
    const group = '<tr class="group-row ds-group-row person-household-group"><td colspan="12"><div class="ds-group-header"><div><i class="fa-solid fa-house-chimney"></i><span>Ho ' + escapeHtml(code) + '</span><small>Chu ho: ' + escapeHtml(head.full_name || '') + '</small></div><strong>' + number(members.length) + ' nhan khau</strong></div></td></tr>';
    return group + members.map(personRow).join('');
  }).join('');
}

window.renderPersonRows = renderPersonRows;
window.thon09SyncResponsiveTableLabels = applyResponsiveTableLabels;

function personRow(row) {
  const party = Number(row.party_member || row.partyMember || 0) === 1;
  const residenceClass = row.presence_status === 'AWAY' ? 'person-badge-away' : (row.residency_status === 'TEMPORARY' ? 'person-badge-temp' : 'person-badge-home');
  const residenceText = row.presence_status === 'AWAY' ? 'Táº¡m váº¯ng' : residencyLabel(row.residency_status);
  const personCode = row.person_code || row.citizen_code || '';
  const age = row.age ?? personAge(row.date_of_birth);
  const ageText = age === null || age === undefined ? '' : String(age);
  return '<tr>'
    + '<td><input type="checkbox" class="person-check" value="' + row.id + '"></td>'
    + '<td>' + escapeHtml(row.household_code || '') + '</td>'
    + '<td>' + escapeHtml(personCode) + '</td>'
    + '<td><button class="btn btn-link person-name-link" onclick="showPerson(' + row.id + ')">' + escapeHtml(row.full_name || '') + '</button></td>'
    + '<td>' + escapeHtml(personRelationship(row)) + '</td>'
    + '<td>' + formatDate(row.date_of_birth) + '</td>'
    + '<td>' + escapeHtml(ageText) + '</td>'
    + '<td>' + escapeHtml(row.gender || '') + '</td>'
    + '<td>' + escapeHtml(row.identity_number || '') + '</td>'
    + '<td><span class="person-badge ' + residenceClass + '">' + escapeHtml(residenceText) + '</span></td>'
    + '<td><span class="person-badge ' + (party ? 'person-badge-party' : 'person-badge-muted') + '">' + (party ? 'CÃ³' : 'KhÃ´ng') + '</span></td>'
    + '<td class="text-end"><button class="btn btn-sm person-row-btn" onclick="showPerson(' + row.id + ')">Xem</button> <button class="btn btn-sm person-row-btn person-row-edit" onclick="openPersonForm(' + row.id + ')">Sá»­a</button> <button class="btn btn-sm btn-outline-danger" onclick="deletePerson(' + row.id + ')">XÃ³a</button></td>'
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
    App.modals.household.hide(); showToast('ÄÃ£ lÆ°u há»™ dÃ¢n'); loadHouseholds(); loadDashboard(); refreshLoginConfig({ force: true });
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
    App.modals.person.hide(); showToast('ÄÃ£ lÆ°u nhÃ¢n kháº©u'); loadPersons(); loadHouseholds(); loadDashboard(); refreshLoginConfig({ force: true });
  } catch (error) { showToast(error.message, 'danger'); }
}

async function showHousehold(id) {
  try {
    const row = await api(`/api/households/${id}`);
    const members = await api('/api/persons?' + new URLSearchParams({ householdId: row.household_code, pageSize: 100 }).toString());
    $('#detailTitle').textContent = 'Chi tiáº¿t há»™ dÃ¢n';
    $('#detailBody').innerHTML = details([['MÃ£ há»™', row.household_code], ['Chá»§ há»™', row.head_citizen_name], ['Äá»‹a chá»‰', row.address], ['Sá»‘ Ä‘iá»‡n thoáº¡i', row.phone], ['á»ž nhÃ ', row.at_home_count || 0], ['Äi váº¯ng', row.away_count || 0], ['Diá»‡n há»™', stripTags(householdBadges(row))], ['Ghi chÃº', row.note]]) + memberTable(members.items || []);
    refreshUiEnhancements($('#detailBody') || document);
    App.modals.detail.show();
  } catch (error) { showToast(error.message, 'danger'); }
}

async function showPerson(id) {
  try {
    const row = await api(`/api/persons/${id}`);
    $('#detailTitle').textContent = 'Chi tiáº¿t nhÃ¢n kháº©u';
    $('#detailBody').innerHTML = renderDynamicPersonDetail(row);
    refreshUiEnhancements($('#detailBody') || document);
    App.modals.detail.show();
  } catch (error) { showToast(error.message, 'danger'); }
}

function renderDynamicPersonDetail(row) {
  const normalized = normalizePersonDetailData(row || {});
  const groups = buildDynamicPersonGroups(normalized);
  const sections = groups.map(group => renderPersonDetailSection(group)).filter(Boolean).join('');
  const heroBadges = buildPersonHeroBadges(normalized).join('');
  const photo = normalized.personPhoto || normalized.photo || normalized.avatar || normalized.image || '';
  return '<article class="person-detail-card person-detail-dynamic">'
    + '<section class="person-detail-hero">'
    + (hasDisplayValue(photo) ? '<div class="person-detail-photo"><img src="' + escapeHtml(photo) + '" alt="áº¢nh nhÃ¢n kháº©u" loading="lazy"></div>' : '')
    + '<div class="person-detail-identity"><span>Há»“ sÆ¡ nhÃ¢n kháº©u</span><h3>' + escapeHtml(normalized.fullName || normalized.name || 'NhÃ¢n kháº©u') + '</h3>'
    + '<div class="person-detail-codes">' + renderCodeBadge('MÃ£ NK', normalized.citizenCode) + renderCodeBadge('CCCD', normalized.identityNumber) + renderCodeBadge('MÃ£ há»™', normalized.householdCode) + '</div>'
    + (heroBadges ? '<div class="person-detail-badges">' + heroBadges + '</div>' : '')
    + '</div></section>'
    + (sections ? '<div class="person-detail-sections">' + sections + '</div>' : '<div class="person-detail-empty">ChÆ°a cÃ³ thÃ´ng tin chi tiáº¿t Ä‘á»ƒ hiá»ƒn thá»‹.</div>')
    + '</article>';
}

function normalizePersonDetailData(row) {
  const aliases = {
    household_code: 'householdCode', citizen_code: 'citizenCode', full_name: 'fullName', date_of_birth: 'dateOfBirth', identity_number: 'identityNumber',
    phone_number: 'phone', household_address: 'householdAddress', current_address: 'currentAddress', permanent_address: 'permanentAddress', residency_status: 'residencyStatus', presence_status: 'presenceStatus', life_status: 'lifeStatus',
    education_level: 'educationLevel', marital_status: 'maritalStatus', party_member: 'partyMember', youth_union_member: 'youthUnionMember', women_union_member: 'womenUnionMember', farmers_union_member: 'farmersUnionMember', veterans_union_member: 'veteransUnionMember', elderly_union_member: 'elderlyUnionMember',
    meritorious_person: 'meritoriousPerson', martyr_relative: 'martyrRelative', wounded_soldier: 'woundedSoldier', sick_soldier: 'sickSoldier', disabled_person: 'disabledPerson', social_assistance: 'socialAssistance',
    health_insurance: 'healthInsurance', social_insurance: 'socialInsurance', household_type: 'householdType', poor_household: 'poorHousehold', near_poor_household: 'nearPoorHousehold', work_place: 'workPlace', workplace: 'workPlace',
    person_photo: 'personPhoto', photo_url: 'personPhoto', blood_type: 'bloodType', note: 'note', notes: 'note'
  };
  return Object.entries(row || {}).reduce((acc, [key, value]) => {
    const normalizedKey = aliases[key] || toCamelCase(key);
    acc[normalizedKey] = value;
    return acc;
  }, {});
}

function buildDynamicPersonGroups(data) {
  const used = new Set(['id', 'createdAt', 'created_at', 'updatedAt', 'updated_at', 'deletedAt', 'deleted_at', 'personPhoto', 'photo', 'avatar', 'image']);
  const groupDefs = [
    { key: 'basic', title: 'ThÃ´ng tin cÆ¡ báº£n', icon: 'fa-id-card', fields: ['fullName','citizenCode','gender','dateOfBirth','age','identityNumber','phone','email'] },
    { key: 'residence', title: 'ThÃ´ng tin cÆ° trÃº', icon: 'fa-house-user', fields: ['householdCode','householdAddress','permanentAddress','currentAddress','residencyStatus','presenceStatus','relationship','lifeStatus'] },
    { key: 'personal', title: 'ThÃ´ng tin cÃ¡ nhÃ¢n', icon: 'fa-user', fields: ['occupation','job','workPlace','ethnicity','religion','educationLevel','maritalStatus','nationality','bloodType'] },
    { key: 'administrative', title: 'ThÃ´ng tin hÃ nh chÃ­nh', icon: 'fa-landmark', fields: ['partyMember','youthUnionMember','womenUnionMember','farmersUnionMember','veteransUnionMember','elderlyUnionMember','meritoriousPerson','martyrRelative','woundedSoldier','sickSoldier','disabledPerson','socialAssistance','householdType','poorHousehold','nearPoorHousehold','healthInsurance','socialInsurance','note'] }
  ];
  const groups = groupDefs.map(def => ({ ...def, items: [] }));
  const addField = (group, key) => {
    if (used.has(key) || !Object.prototype.hasOwnProperty.call(data, key) || !hasDisplayValue(data[key])) return;
    used.add(key);
    group.items.push({ key, label: personFieldLabel(key), value: formatPersonDetailValue(key, data[key]) });
  };
  groups.forEach(group => group.fields.forEach(key => addField(group, key)));
  Object.keys(data).forEach(key => {
    if (used.has(key) || !hasDisplayValue(data[key])) return;
    const group = groups.find(item => item.key === inferPersonGroup(key)) || groups[3];
    used.add(key);
    group.items.push({ key, label: personFieldLabel(key), value: formatPersonDetailValue(key, data[key]) });
  });
  return groups.filter(group => group.items.length);
}

function inferPersonGroup(key) {
  const text = normalizeSearchText(key);
  if (/house|address|residen|presence|temporary|permanent|relation|ho|diachi|cu tru|tam tru|tam vang/.test(text)) return 'residence';
  if (/job|work|occupation|ethnic|religion|education|marital|national|blood|nghe|dan toc|ton giao|hoc van|hon nhan|quoc tich/.test(text)) return 'personal';
  if (/party|union|member|policy|insurance|disabled|soldier|martyr|poor|note|dang|doan|hoi|bao hiem|khuyet tat|co cong|ghi chu/.test(text)) return 'administrative';
  return 'administrative';
}

function renderPersonDetailSection(group) {
  if (!group.items.length) return '';
  return '<section class="person-info-section"><div class="person-info-section-title"><i class="fa-solid ' + group.icon + '"></i><h4>' + escapeHtml(group.title) + '</h4></div>'
    + '<div class="person-info-grid">' + group.items.map(item => '<div class="person-info-field"><span>' + escapeHtml(item.label) + '</span><div class="person-info-value ' + (isCodePersonField(item.key) ? 'person-info-value-code' : '') + '">' + escapeHtml(item.value) + '</div></div>').join('') + '</div></section>';
}

function hasDisplayValue(value) {
  if (value === null || value === undefined) return false;
  if (Array.isArray(value)) return value.some(hasDisplayValue);
  if (typeof value === 'object') return Object.values(value).some(hasDisplayValue);
  const text = String(value).trim();
  if (!text) return false;
  return !['null','undefined','n/a','na','--','â€”','khÃ´ng cÃ³ dá»¯ liá»‡u','khong co du lieu'].includes(normalizeSearchText(text));
}

function formatPersonDetailValue(key, value) {
  if (Array.isArray(value)) return value.filter(hasDisplayValue).map(item => formatPersonDetailValue(key, item)).join(', ');
  if (typeof value === 'object' && value !== null) return Object.entries(value).filter(([, v]) => hasDisplayValue(v)).map(([k, v]) => personFieldLabel(k) + ': ' + formatPersonDetailValue(k, v)).join('; ');
  const bool = booleanDisplayValue(value);
  if (bool !== null) return bool;
  if (/date|birth|ngay/i.test(key)) return formatDate(value);
  if (key === 'residencyStatus') return residencyLabel(value);
  if (key === 'presenceStatus') return presenceLabel(value);
  if (key === 'lifeStatus' || key === 'status') return lifeLabel(value);
  return String(value).trim();
}

function booleanDisplayValue(value) {
  if (value === true || value === 1 || value === '1') return 'CÃ³';
  if (value === false || value === 0 || value === '0') return 'KhÃ´ng';
  return null;
}

function personFieldLabel(key) {
  const labels = {
    householdCode: 'MÃ£ há»™', citizenCode: 'MÃ£ nhÃ¢n kháº©u', fullName: 'Há» tÃªn', gender: 'Giá»›i tÃ­nh', dateOfBirth: 'NgÃ y sinh', age: 'Tuá»•i', identityNumber: 'CCCD/Sá»‘ Ä‘á»‹nh danh', phone: 'Sá»‘ Ä‘iá»‡n thoáº¡i', email: 'Email',
    householdAddress: 'Äá»‹a chá»‰ há»™', permanentAddress: 'Äá»‹a chá»‰ thÆ°á»ng trÃº', currentAddress: 'Äá»‹a chá»‰ hiá»‡n táº¡i', residencyStatus: 'TÃ¬nh tráº¡ng cÆ° trÃº', presenceStatus: 'Hiá»‡n táº¡i', relationship: 'Quan há»‡ vá»›i chá»§ há»™', lifeStatus: 'Tráº¡ng thÃ¡i',
    occupation: 'Nghá» nghiá»‡p', job: 'Nghá» nghiá»‡p', workPlace: 'NÆ¡i lÃ m viá»‡c', ethnicity: 'DÃ¢n tá»™c', religion: 'TÃ´n giÃ¡o', educationLevel: 'TrÃ¬nh Ä‘á»™ há»c váº¥n', maritalStatus: 'TÃ¬nh tráº¡ng hÃ´n nhÃ¢n', nationality: 'Quá»‘c tá»‹ch', bloodType: 'NhÃ³m mÃ¡u',
    partyMember: 'Äáº£ng viÃªn', youthUnionMember: 'ÄoÃ n viÃªn Thanh niÃªn', womenUnionMember: 'Há»™i viÃªn Há»™i Phá»¥ ná»¯', farmersUnionMember: 'Há»™i viÃªn Há»™i NÃ´ng dÃ¢n', veteransUnionMember: 'Há»™i viÃªn Há»™i Cá»±u chiáº¿n binh', elderlyUnionMember: 'Há»™i viÃªn Há»™i NgÆ°á»i cao tuá»•i',
    meritoriousPerson: 'NgÆ°á»i cÃ³ cÃ´ng', martyrRelative: 'ThÃ¢n nhÃ¢n liá»‡t sÄ©', woundedSoldier: 'ThÆ°Æ¡ng binh', sickSoldier: 'Bá»‡nh binh', disabledPerson: 'NgÆ°á»i khuyáº¿t táº­t', socialAssistance: 'Báº£o trá»£ xÃ£ há»™i', householdType: 'Diá»‡n há»™', poorHousehold: 'Há»™ nghÃ¨o', nearPoorHousehold: 'Há»™ cáº­n nghÃ¨o', healthInsurance: 'Báº£o hiá»ƒm y táº¿', socialInsurance: 'Báº£o hiá»ƒm xÃ£ há»™i', note: 'Ghi chÃº'
  };
  return labels[key] || humanizeFieldName(key);
}

function humanizeFieldName(key) {
  return String(key || '').replace(/_/g, ' ').replace(/([a-z0-9])([A-Z])/g, '$1 $2').replace(/\s+/g, ' ').trim().replace(/^./, c => c.toUpperCase());
}

function toCamelCase(key) {
  return String(key || '').replace(/[-_\s]+([a-zA-Z0-9])/g, (_, chr) => chr.toUpperCase());
}

function renderCodeBadge(label, value) {
  return hasDisplayValue(value) ? '<strong>' + escapeHtml(label) + ': ' + escapeHtml(value) + '</strong>' : '';
}

function isCodePersonField(key) {
  return /code|number|cccd|identity|bhxh|bhyt/i.test(key);
}

function buildPersonHeroBadges(data) {
  return [
    ['gender', data.gender, 'neutral'], ['partyMember', data.partyMember, 'green'], ['relationship', data.relationship === 'Chá»§ há»™' ? 'Chá»§ há»™' : '', 'gold'],
    ['residencyStatus', data.residencyStatus, 'blue'], ['presenceStatus', data.presenceStatus, 'purple']
  ].filter(([, value]) => hasDisplayValue(value)).map(([key, value, tone]) => '<span class="person-detail-badge person-detail-badge-' + tone + '">' + escapeHtml(formatPersonDetailValue(key, value)) + '</span>');
}

async function deleteHousehold(id) {
  try {
    const row = await api('/api/households/' + id);
    const message = 'Báº¡n cÃ³ cháº¯c cháº¯n muá»‘n xÃ³a há»™ gia Ä‘Ã¬nh nÃ y?\n\n'
      + 'MÃ£ há»™: ' + (row.household_code || '') + '\n'
      + 'Chá»§ há»™: ' + (row.head_citizen_name || '') + '\n'
      + 'Äá»‹a chá»‰: ' + (row.address || '') + '\n'
      + 'Sá»‘ thÃ nh viÃªn: ' + number(row.member_count_real || row.total_members || 0);
    if (!confirm(message)) return;
    await api('/api/households/' + id, { method: 'DELETE' });
    showToast('ÄÃ£ xÃ³a há»™ gia Ä‘Ã¬nh');
    loadHouseholds(); loadPersons(); loadDashboard(); refreshLoginConfig({ force: true });
  } catch (error) { showToast(error.message, 'danger'); }
}
async function deletePerson(id) {
  try {
    const row = await api('/api/persons/' + id);
    const message = 'Báº¡n cÃ³ cháº¯c cháº¯n muá»‘n xÃ³a nhÃ¢n kháº©u nÃ y?\n\n'
      + 'MÃ£ nhÃ¢n kháº©u: ' + (row.citizen_code || '') + '\n'
      + 'Há» vÃ  tÃªn: ' + (row.full_name || '') + '\n'
      + 'CCCD: ' + (row.identity_number || '');
    if (!confirm(message)) return;
    await api('/api/persons/' + id, { method: 'DELETE' });
    showToast('ÄÃ£ xÃ³a nhÃ¢n kháº©u');
    loadPersons(); loadHouseholds(); loadDashboard(); refreshLoginConfig({ force: true });
  } catch (error) { showToast(error.message, 'danger'); }
}
async function bulkDeleteHouseholds() { await bulkDelete('.household-check:checked', '/api/households/bulk-delete', () => { loadHouseholds(); loadPersons(); }, 'há»™ gia Ä‘Ã¬nh'); }
async function bulkDeletePersons() { await bulkDelete('.person-check:checked', '/api/persons/bulk-delete', () => { loadPersons(); loadHouseholds(); }, 'nhÃ¢n kháº©u'); }
async function bulkDelete(selector, url, reload, label) {
  const ids = $$(selector).map(c => Number(c.value)).filter(Boolean);
  if (ids.length < 2) return showToast('Vui lÃ²ng chá»n tá»« 2 báº£n ghi trá»Ÿ lÃªn Ä‘á»ƒ xÃ³a hÃ ng loáº¡t', 'warning');
  if (!confirm('Báº¡n sáº¯p xÃ³a ' + ids.length + ' báº£n ghi.\nBáº¡n cÃ³ cháº¯c cháº¯n muá»‘n tiáº¿p tá»¥c?')) return;
  try {
    const result = await api(url, { method: 'POST', body: { ids } });
    showToast('ÄÃ£ xÃ³a ' + (result.success || ids.length) + ' ' + label);
    reload(); loadDashboard(); refreshLoginConfig({ force: true }); updateBulkDeleteButtons();
  } catch (error) { showToast(error.message, 'danger'); }
}
function updateBulkDeleteButtons() {
  const householdCount = $$('.household-check:checked').length;
  const personCount = $$('.person-check:checked').length;
  const householdBtn = $('#householdBulkDeleteBtn');
  const personBtn = $('#personBulkDeleteBtn');
  if (householdBtn) householdBtn.classList.toggle('d-none', householdCount < 2);
  if (personBtn) personBtn.classList.toggle('d-none', personCount < 2);
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
    if (!response.ok || !payload?.ok) throw new Error(payload?.error?.message || 'KhÃ´ng nháº­n Ä‘Æ°á»£c pháº£n há»“i tá»« há»‡ thá»‘ng');
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
  return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/Ä‘/g, 'd').replace(/Ä/g, 'd');
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
    { label: '0-5 tuá»•i', value: 0 },
    { label: '6-14 tuá»•i', value: 0 },
    { label: '15-17 tuá»•i', value: 0 },
    { label: '18-59 tuá»•i', value: 0 },
    { label: 'TrÃªn 60 tuá»•i', value: 0 }
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
function roleLabel(role) { return ({ SUPER_ADMIN:'Quáº£n trá»‹ tá»‘i cao', ADMIN:'Quáº£n trá»‹', OFFICER:'CÃ¡n bá»™', VIEWER:'Chá»‰ xem' })[role] || role || ''; }
function residencyLabel(value) { return value === 'TEMPORARY' ? 'Táº¡m trÃº' : 'ThÆ°á»ng trÃº'; }
function presenceLabel(value) { return value === 'AWAY' ? 'Äi váº¯ng' : 'á»ž nhÃ '; }
function lifeLabel(value) { return value === 'DECEASED' ? 'ÄÃ£ cháº¿t' : 'CÃ²n sá»‘ng'; }
function emptyRow(colspan, message) { return `<tr><td colspan="${colspan}" class="text-center text-muted py-4">${message}</td></tr>`; }
function householdBadges(row) {
  const badges = [];
  if (Number(row.meritorious_family)) badges.push('CÃ³ cÃ´ng');
  if (Number(row.poor_household)) badges.push('Há»™ nghÃ¨o');
  if (Number(row.near_poor_household)) badges.push('Cáº­n nghÃ¨o');
  if (Number(row.disabled_household)) badges.push('TÃ n táº­t');
  return badges.length ? badges.map(b => `<span class="badge-soft">${b}</span>`).join('') : '<span class="text-muted">KhÃ´ng</span>';
}
function memberTable(items) {
  const rows = items.map(row => `<tr><td>${escapeHtml(row.household_code || '')}</td><td>${escapeHtml(row.citizen_code || '')}</td><td>${escapeHtml(row.full_name || '')}</td><td>${formatDate(row.date_of_birth)}</td><td>${escapeHtml(row.identity_number || '')}</td><td>${escapeHtml(row.household_address || '')}</td><td>${escapeHtml(row.phone || '')}</td></tr>`).join('') || `<tr><td colspan="7" class="text-center text-muted py-3">ChÆ°a cÃ³ thÃ nh viÃªn</td></tr>`;
  return `<h6 class="mt-4 mb-2">ThÃ nh viÃªn trong há»™</h6><div class="table-responsive"><table class="table table-sm table-bordered align-middle"><thead><tr><th>MÃ£ há»™</th><th>MÃ£ nhÃ¢n kháº©u</th><th>Há» tÃªn</th><th>NgÃ y sinh</th><th>CCCD</th><th>Äá»‹a chá»‰</th><th>Sá»‘ Ä‘iá»‡n thoáº¡i</th></tr></thead><tbody>${rows}</tbody></table></div>`;
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
  host.innerHTML = `<span class="text-muted small">Trang ${page}/${totalPages} - ${number(total)} dÃ²ng</span><button class="btn btn-outline-secondary btn-sm" ${page <= 1 ? 'disabled' : ''} data-page="${page - 1}">TrÆ°á»›c</button><button class="btn btn-outline-secondary btn-sm" ${page >= totalPages ? 'disabled' : ''} data-page="${page + 1}">Sau</button>`;
  $$(`${selector} button`).forEach(btn => btn.addEventListener('click', () => {
    const nextPage = Number(btn.dataset.page || 1);
    if (nextPage >= 1 && nextPage <= totalPages) go(nextPage);
  }));
}
function renderChart(selector, items) {
  const host = $(selector);
  const normalized = (items || []).map(item => ({ label: item.label || 'KhÃ¡c', value: Number(item.value || 0) }));
  const total = normalized.reduce((sum, item) => sum + item.value, 0);
  if (!host) return;
  if (!total) {
    host.innerHTML = '<p class="text-muted mb-0">ChÆ°a cÃ³ dá»¯ liá»‡u</p>';
    return;
  }
  host.innerHTML = '<div class="percent-chart-list ' + (normalized.length === 1 ? 'single' : '') + '">' + normalized.map(item => {
    const percent = Math.round(item.value * 100 / total);
    const sweep = Math.max(2, percent);
    return '<div class="percent-chart-item">'
      + '<div class="percent-donut" style="--percent:' + sweep + '"><span>' + percent + '%</span></div>'
      + '<div class="percent-chart-meta"><strong>' + escapeHtml(item.label || 'KhÃ¡c') + '</strong><small>' + number(item.value) + '</small></div>'
      + '</div>';
  }).join('') + '</div>';
}
function details(rows) {
  return `<div class="detail-grid">${rows.map(([label, value]) => `<div class="detail-item"><div class="detail-label">${escapeHtml(label)}</div><div class="detail-value">${escapeHtml(value ?? '')}</div></div>`).join('')}</div>`;
}
function showToast(message, type = 'success') {
  const id = 'toast-' + Date.now();
  $('#toastHost').insertAdjacentHTML('beforeend', `<div id="${id}" class="toast align-items-center text-bg-${type} border-0" role="alert"><div class="d-flex"><div class="toast-body">${escapeHtml(message)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="ÄÃ³ng"></button></div></div>`);
  const el = $('#' + id);
  const toast = new bootstrap.Toast(el, { delay: 3500 });
  toast.show();
  el.addEventListener('hidden.bs.toast', () => el.remove());
}



function initNotificationPopover() {
  const button = $('#notificationBtn');
  if (!button || button.dataset.bound === '1') return;
  button.dataset.bound = '1';
  const panel = document.createElement('div');
  panel.id = 'notificationPanel';
  panel.className = 'notification-popover d-none';
  panel.innerHTML = '<div class="notification-popover-head"><strong>ThÃ´ng bÃ¡o</strong><span>Gáº§n Ä‘Ã¢y</span></div><div class="notification-popover-empty">ChÆ°a cÃ³ thÃ´ng bÃ¡o má»›i.</div>';
  button.insertAdjacentElement('afterend', panel);
  button.addEventListener('click', event => {
    event.stopPropagation();
    panel.classList.toggle('d-none');
  });
  document.addEventListener('click', event => {
    if (!panel.contains(event.target) && !button.contains(event.target)) panel.classList.add('d-none');
  });
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
      toggle.setAttribute('aria-label', visible ? 'Hiá»‡n máº­t kháº©u' : 'áº¨n máº­t kháº©u');
      toggle.innerHTML = '<i class="fa-solid ' + (visible ? 'fa-eye' : 'fa-eye-slash') + '" aria-hidden="true"></i>';
    });
  }

  hydrateLoginIntro();
}

async function hydrateLoginIntro() {
  await refreshLoginConfig();
}

let loginConfigCache = null;
let loginConfigFetchedAt = 0;
let loginConfigInFlight = null;

async function refreshLoginConfig(options = {}) {
  const force = !!options.force;
  const now = Date.now();
  if (!force && loginConfigCache && now - loginConfigFetchedAt < 15000) {
    if (loginConfigCache.settings) applyLoginSettings(loginConfigCache.settings);
    if (loginConfigCache.metrics) updateLoginStats(loginConfigCache.metrics);
    return loginConfigCache;
  }
  if (!force && loginConfigInFlight) return loginConfigInFlight;
  loginConfigInFlight = loginFetchJson('/api/public/login-config', true)
    .then(data => {
      loginConfigCache = data || {};
      loginConfigFetchedAt = Date.now();
      if (data?.settings) applyLoginSettings(data.settings);
      if (data?.metrics) updateLoginStats(data.metrics);
      return data;
    })
    .catch(() => null)
    .finally(() => { loginConfigInFlight = null; });
  return loginConfigInFlight;
}
window.refreshLoginConfig = refreshLoginConfig;

async function loginFetchJson(url, isPublic = false) {
  const headers = { Accept: 'application/json' };
  if (!isPublic && App.token) headers.Authorization = 'Bearer ' + App.token;
  const response = await fetch(url, { headers, cache: 'no-store' });
  const payload = await response.json().catch(() => null);
  if (!response.ok || !payload?.ok) throw new Error('KhÃ´ng táº£i Ä‘Æ°á»£c dá»¯ liá»‡u');
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
  setText('#loginSystemName', settings.systemName || 'Há»‡ thá»‘ng Quáº£n lÃ½ HÃ nh chÃ­nh');
  setText('#loginHamletName', settings.hamletName || 'ThÃ´n 09');
  setText('#loginCommuneName', settings.communeName || 'XÃ£ Há»“ng Phong');
  setText('#loginSlogan', settings.slogan || 'VÃ¬ NhÃ¢n dÃ¢n phá»¥c vá»¥');
  setText('#loginVersion', 'PhiÃªn báº£n ' + (settings.softwareVersion || 'v2.0'));
  setText('#loginCopyright', settings.copyright || ('Â© ' + (settings.hamletName || 'ThÃ´n 09') + ' - ' + (settings.communeName || 'XÃ£ Há»“ng Phong')));
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
    logo.innerHTML = '<img src="' + escapeHtml(url) + '" alt="Logo ThÃ´n 09" decoding="async">';
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


window.addEventListener('thon09:data-mutated', () => { if (typeof refreshLoginConfig === 'function') refreshLoginConfig({ force: true }); });

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


// Government Admin Dashboard UI/UX 2.0 interactions
(function(){
  function q(s,r){return (r||document).querySelector(s);}
  function qa(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s));}
  function openScreen(screen){
    if (typeof switchScreen === 'function') switchScreen(screen);
    else {
      qa('.sidebar .nav-link').forEach(function(btn){btn.classList.toggle('active', btn.dataset.screen === screen);});
      qa('.screen').forEach(function(el){el.classList.toggle('active', el.id === screen + 'Screen');});
    }
  }
  document.addEventListener('DOMContentLoaded', function(){
    var collapse = q('#sidebarCollapse');
    if (collapse) collapse.addEventListener('click', function(){ document.body.classList.toggle('sidebar-collapsed'); });
    var sidebarLogout = q('#sidebarLogoutBtn');
    if (sidebarLogout) sidebarLogout.addEventListener('click', function(){ var logout = q('#logoutBtn'); if (logout) logout.click(); });
    qa('[data-quick-screen]').forEach(function(btn){
      btn.addEventListener('click', function(){
        var screen = btn.dataset.quickScreen;
        openScreen(screen);
        setTimeout(function(){
          if (btn.dataset.quickAction === 'addHousehold' && typeof openHouseholdForm === 'function') openHouseholdForm();
          if (btn.dataset.quickAction === 'addPerson' && typeof openPersonForm === 'function') openPersonForm();
        }, 120);
      });
    });
  });
})();


function gisEscape(value) { return escapeHtml(value); }
function gisNumber(value) { return number(value || 0); }
function gisCentroid(points) {
  if (!Array.isArray(points) || !points.length) return [20.2506, 105.9748];
  const sum = points.reduce((acc, p) => [acc[0] + Number(p.lat || 0), acc[1] + Number(p.lng || 0)], [0, 0]);
  return [sum[0] / points.length, sum[1] / points.length];
}
function gisPolygonLatLngs(area) {
  return (area.geometry || []).map(p => [Number(p.lat), Number(p.lng)]).filter(p => Number.isFinite(p[0]) && Number.isFinite(p[1]));
}
function gisStatsHtml(stats) {
  return '<div class="gis-popup-stats">'
    + '<span><b>' + gisNumber(stats.households) + '</b> há»™</span>'
    + '<span><b>' + gisNumber(stats.citizens) + '</b> nhÃ¢n kháº©u</span>'
    + '<span><b>' + gisNumber(stats.temporary) + '</b> táº¡m trÃº</span>'
    + '<span><b>' + gisNumber(stats.away) + '</b> táº¡m váº¯ng</span>'
    + '</div>';
}
function gisAreaPopup(area) {
  const stats = area.stats || {};
  return '<div class="gis-popup"><h4>' + gisEscape(area.name) + '</h4><p>MÃ£ khu vá»±c: <b>' + gisEscape(area.area_code) + '</b></p>'
    + gisStatsHtml(stats)
    + '<div class="gis-popup-actions"><button class="btn btn-sm btn-success" onclick="filterHouseholdsByGisArea(\'' + gisEscape(area.area_code) + '\')">Lá»c há»™ khu vá»±c nÃ y</button>'
    + '<button class="btn btn-sm btn-outline-danger" onclick="deleteGisArea(' + Number(area.id) + ')">XÃ³a ranh giá»›i</button></div></div>';
}
function gisTooltip(area) {
  const stats = area.stats || {};
  return '<strong>' + gisEscape(area.name) + '</strong><br>' + gisNumber(stats.households) + ' há»™ - ' + gisNumber(stats.citizens) + ' nhÃ¢n kháº©u';
}
async function loadGisMap() {
  try {
    if (!window.L) {
      const status = $('#gisMapStatus');
      if (status) status.textContent = 'KhÃ´ng táº£i Ä‘Æ°á»£c thÆ° viá»‡n báº£n Ä‘á»“';
      showToast('KhÃ´ng táº£i Ä‘Æ°á»£c Leaflet/OpenStreetMap. Vui lÃ²ng kiá»ƒm tra káº¿t ná»‘i máº¡ng.', 'danger');
      return;
    }
    initGisMap();
    const data = await api('/api/gis/areas');
    App.gis.areas = data.areas || [];
    renderGisAreas(data);
  } catch (error) {
    showToast('KhÃ´ng táº£i Ä‘Æ°á»£c báº£n Ä‘á»“ Ä‘á»‹a bÃ n: ' + error.message, 'danger');
  }
}
function initGisMap() {
  if (App.gis.map) { setTimeout(() => App.gis.map.invalidateSize(), 80); return; }
  const map = L.map('gisMap', { zoomControl: true }).setView([20.2506, 105.9748], 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20, attribution: '&copy; OpenStreetMap' }).addTo(map);
  App.gis.map = map;
  App.gis.layerGroup = L.featureGroup().addTo(map);
  if (L.Control.Draw) {
    App.gis.drawControl = new L.Control.Draw({
      draw: { polygon: { allowIntersection: false, showArea: true }, rectangle: false, circle: false, marker: false, circlemarker: false, polyline: false },
      edit: { featureGroup: App.gis.layerGroup, remove: false }
    });
    map.addControl(App.gis.drawControl);
    map.on(L.Draw.Event.CREATED, event => setGisDrawnLayer(event.layer));
    map.on(L.Draw.Event.EDITED, event => event.layers.eachLayer(layer => setGisDrawnLayer(layer)));
  }
  $('#gisDrawBtn')?.addEventListener('click', () => startGisDraw());
  $('#gisSaveBtn')?.addEventListener('click', () => saveGisArea());
  $('#gisRefreshBtn')?.addEventListener('click', () => loadGisMap());
  $('#gisPdfBtn')?.addEventListener('click', () => exportGisPdf());
  $('#gisSearch')?.addEventListener('input', debounce(() => renderGisAreaList(App.gis.areas, $('#gisSearch').value), 250));
  setTimeout(() => map.invalidateSize(), 200);
}
function setGisDrawnLayer(layer) {
  if (App.gis.drawnLayer && App.gis.layerGroup.hasLayer(App.gis.drawnLayer)) App.gis.layerGroup.removeLayer(App.gis.drawnLayer);
  App.gis.drawnLayer = layer;
  App.gis.layerGroup.addLayer(layer);
  const save = $('#gisSaveBtn');
  if (save) save.disabled = false;
  const color = $('#gisAreaColor')?.value || '#0f8a4b';
  if (layer.setStyle) layer.setStyle({ color, fillColor: color, fillOpacity: .18, weight: 2 });
}
function startGisDraw() {
  if (!App.gis.map || !window.L?.Draw?.Polygon) return showToast('ChÆ°a sáºµn sÃ ng cÃ´ng cá»¥ váº½ báº£n Ä‘á»“', 'warning');
  new L.Draw.Polygon(App.gis.map, { allowIntersection: false, showArea: true, shapeOptions: { color: $('#gisAreaColor')?.value || '#0f8a4b' } }).enable();
}
async function saveGisArea() {
  const layer = App.gis.drawnLayer;
  if (!layer) return showToast('Vui lÃ²ng váº½ ranh giá»›i trÃªn báº£n Ä‘á»“ trÆ°á»›c khi lÆ°u', 'warning');
  const latLngs = (layer.getLatLngs()[0] || []).map(p => ({ lat: Number(p.lat.toFixed(7)), lng: Number(p.lng.toFixed(7)) }));
  const payload = {
    id: $('#gisAreaId')?.value || undefined,
    name: $('#gisAreaName')?.value.trim(),
    area_code: $('#gisAreaCode')?.value.trim(),
    color: $('#gisAreaColor')?.value || '#0f8a4b',
    note: $('#gisAreaNote')?.value.trim(),
    geometry: latLngs
  };
  if (!payload.name) return showToast('Vui lÃ²ng nháº­p tÃªn khu vá»±c', 'warning');
  const saved = await api('/api/gis/areas', { method: 'POST', body: payload });
  showToast('ÄÃ£ lÆ°u ranh giá»›i khu vá»±c');
  clearGisForm();
  await loadGisMap();
  focusGisArea(saved.area_code);
}
function clearGisForm() {
  ['gisAreaId','gisAreaName','gisAreaCode','gisAreaNote'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
  const save = $('#gisSaveBtn'); if (save) save.disabled = true;
  App.gis.drawnLayer = null;
}
function renderGisAreas(data) {
  const map = App.gis.map;
  const group = App.gis.layerGroup;
  if (!map || !group) return;
  group.clearLayers();
  const bounds = [];
  (data.areas || []).forEach(area => {
    const latLngs = gisPolygonLatLngs(area);
    if (latLngs.length < 3) return;
    const polygon = L.polygon(latLngs, { color: area.color || '#0f8a4b', fillColor: area.color || '#0f8a4b', fillOpacity: .18, weight: 2 });
    polygon.bindPopup(gisAreaPopup(area));
    polygon.bindTooltip(gisTooltip(area), { permanent: true, direction: 'center', className: 'gis-area-tooltip' });
    polygon.on('click', () => filterHouseholdsByGisArea(area.area_code));
    polygon.addTo(group);
    latLngs.forEach(p => bounds.push(p));
  });
  if (bounds.length) map.fitBounds(bounds, { padding: [24, 24] });
  $('#gisMapStatus').textContent = (data.areas || []).length + ' khu vá»±c - ' + gisNumber(data.summary?.households) + ' há»™';
  renderGisSummary(data);
  renderGisAreaList(data.areas || [], $('#gisSearch')?.value || '');
  setTimeout(() => map.invalidateSize(), 120);
}
function renderGisSummary(data) {
  const host = $('#gisSummaryCards');
  if (!host) return;
  host.innerHTML = '<div><span>Khu vá»±c</span><b>' + gisNumber(data.areas?.length || 0) + '</b></div>'
    + '<div><span>Sá»‘ há»™</span><b>' + gisNumber(data.summary?.households) + '</b></div>'
    + '<div><span>NhÃ¢n kháº©u</span><b>' + gisNumber(data.summary?.citizens) + '</b></div>'
    + '<div><span>ChÆ°a gÃ¡n</span><b>' + gisNumber(data.unassigned?.households) + '</b></div>';
}
function renderGisAreaList(areas, keyword = '') {
  const host = $('#gisAreaList');
  if (!host) return;
  const q = normalizeSearchText(keyword);
  const filtered = (areas || []).filter(area => !q || [area.name, area.area_code, area.note].some(v => normalizeSearchText(v).includes(q)));
  host.innerHTML = filtered.map(area => '<button class="gis-area-item" type="button" onclick="focusGisArea(\'' + gisEscape(area.area_code) + '\')"><span><b>' + gisEscape(area.name) + '</b><small>' + gisEscape(area.area_code) + '</small></span><em>' + gisNumber(area.stats?.households) + ' há»™ / ' + gisNumber(area.stats?.citizens) + ' NK</em></button>').join('') || '<div class="text-muted small py-2">ChÆ°a cÃ³ khu vá»±c báº£n Ä‘á»“</div>';
}
function focusGisArea(areaCode) {
  const area = (App.gis.areas || []).find(item => String(item.area_code) === String(areaCode));
  if (!area || !App.gis.map) return;
  const latLngs = gisPolygonLatLngs(area);
  if (latLngs.length) App.gis.map.fitBounds(latLngs, { padding: [28, 28], maxZoom: 17 });
}
function filterHouseholdsByGisArea(areaCode) {
  App.households.search = String(areaCode || '');
  App.households.page = 1;
  const search = $('#householdSearch');
  if (search) search.value = App.households.search;
  switchScreen('households');
  setTimeout(() => loadHouseholds(), 100);
}
async function deleteGisArea(id) {
  if (!confirm('XÃ³a ranh giá»›i khu vá»±c nÃ y? Dá»¯ liá»‡u há»™ dÃ¢n khÃ´ng bá»‹ xÃ³a.')) return;
  await api('/api/gis/areas/' + id, { method: 'DELETE' });
  showToast('ÄÃ£ xÃ³a ranh giá»›i khu vá»±c');
  loadGisMap();
}
function exportGisPdf() {
  if (!App.token) return showToast('Vui lÃ²ng Ä‘Äƒng nháº­p láº¡i Ä‘á»ƒ xuáº¥t PDF', 'warning');
  fetch('/api/gis/export-pdf', { headers: { Authorization: 'Bearer ' + App.token }, cache: 'no-store' })
    .then(response => {
      if (!response.ok) throw new Error('KhÃ´ng xuáº¥t Ä‘Æ°á»£c báº£n Ä‘á»“');
      return response.blob();
    })
    .then(blob => {
      const url = URL.createObjectURL(blob);
      const win = window.open(url, '_blank');
      if (!win) {
        const a = document.createElement('a');
        a.href = url; a.download = 'ban_do_dia_ban.html'; document.body.appendChild(a); a.click(); a.remove();
      }
      setTimeout(() => URL.revokeObjectURL(url), 30000);
    })
    .catch(error => showToast(error.message || 'KhÃ´ng xuáº¥t Ä‘Æ°á»£c báº£n Ä‘á»“', 'danger'));
}
window.loadGisMap = loadGisMap;
window.filterHouseholdsByGisArea = filterHouseholdsByGisArea;
window.focusGisArea = focusGisArea;
window.deleteGisArea = deleteGisArea;

/* Mobile/tablet UI system - active asset loaded by views/app.php. */
(function () {
  'use strict';

  var MOBILE_QUERY = '(max-width: 1199.98px)';
  var mq = window.matchMedia ? window.matchMedia(MOBILE_QUERY) : { matches: false };
  var activeSheet = null;

  function q(selector, root) { return (root || document).querySelector(selector); }
  function qa(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
  function isMobile() { return !!mq.matches; }
  function hasSession() { return !!(localStorage.getItem('thon09_token') || (window.App && App.token)); }
  function clean(value) { return value == null ? '' : String(value).trim(); }

  function currentScreen() {
    return (window.App && App.screen) || localStorage.getItem('thon09_screen') || 'dashboard';
  }

  function userLabel() {
    if (window.App && App.user) return clean(App.user.email || App.user.username || App.user.name) || 'Tai khoan';
    var raw = clean(q('#currentUser') && q('#currentUser').textContent);
    return raw || 'Tai khoan';
  }

  function roleLabelMobile() {
    if (window.App && App.user && typeof window.roleLabel === 'function') return window.roleLabel(App.user.role);
    if (window.App && App.user) return clean(App.user.role);
    return '';
  }

  function ensureUserMenu() {
    var topbar = q('.topbar-meta');
    if (!topbar || q('.mobile-user-menu-btn')) return;
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'mobile-user-menu-btn';
    button.setAttribute('aria-haspopup', 'true');
    button.setAttribute('aria-expanded', 'false');
    button.innerHTML = '<i class="fa-regular fa-user"></i><span>Tai khoan</span>';

    var popover = document.createElement('div');
    popover.className = 'mobile-user-popover';
    popover.hidden = true;
    popover.innerHTML = '<strong></strong><small></small><button type="button"><i class="fa-solid fa-right-from-bracket"></i><span>Dang xuat</span></button>';

    button.addEventListener('click', function (event) {
      event.preventDefault();
      var open = popover.hidden;
      popover.hidden = !open;
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
      q('strong', popover).textContent = userLabel();
      q('small', popover).textContent = roleLabelMobile();
    });
    q('button', popover).addEventListener('click', function () {
      var logout = q('#logoutBtn');
      if (logout) logout.click();
      else if (typeof window.logout === 'function') window.logout();
    });
    document.addEventListener('click', function (event) {
      if (popover.hidden || event.target.closest('.mobile-user-menu-btn,.mobile-user-popover')) return;
      popover.hidden = true;
      button.setAttribute('aria-expanded', 'false');
    });
    topbar.appendChild(button);
    topbar.appendChild(popover);
  }

  function ensureMobileChrome() {
    if (!q('.mobile-bottom-nav')) {
      var nav = document.createElement('nav');
      nav.className = 'mobile-bottom-nav d-none';
      nav.setAttribute('aria-label', 'Dieu huong nhanh');
      nav.innerHTML = [
        ['dashboard', 'fa-gauge-high', 'Tong quan'],
        ['households', 'fa-house-chimney', 'Ho'],
        ['persons', 'fa-users', 'Nhan khau'],
        ['gis', 'fa-map-location-dot', 'GIS'],
        ['reports', 'fa-chart-pie', 'Bao cao']
      ].map(function (item) {
        return '<button type="button" data-mobile-screen="' + item[0] + '"><i class="fa-solid ' + item[1] + '"></i><span>' + item[2] + '</span></button>';
      }).join('');
      document.body.appendChild(nav);
    }
    if (!q('.mobile-fab')) {
      var fab = document.createElement('button');
      fab.type = 'button';
      fab.className = 'mobile-fab d-none';
      document.body.appendChild(fab);
    }
    if (!document.body.dataset.mobileChromeBound) {
      document.body.dataset.mobileChromeBound = '1';
      document.addEventListener('click', function (event) {
        var button = event.target.closest('.mobile-bottom-nav [data-mobile-screen]');
        if (!button || typeof window.switchScreen !== 'function') return;
        event.preventDefault();
        window.switchScreen(button.dataset.mobileScreen);
        closeFilterSheet();
        window.setTimeout(syncMobileChrome, 0);
      }, true);
    }
  }

  function syncMobileChrome() {
    var authenticated = hasSession();
    qa('.mobile-bottom-nav,.mobile-fab').forEach(function (el) { el.classList.toggle('d-none', !authenticated || !isMobile()); });
    if (!authenticated || !isMobile()) return;
    var screen = currentScreen();
    qa('.mobile-bottom-nav [data-mobile-screen]').forEach(function (button) {
      button.classList.toggle('active', button.dataset.mobileScreen === screen);
    });
    var config = {
      households: ['fa-plus', 'Them ho', function () { if (window.openHouseholdForm) window.openHouseholdForm(); }],
      persons: ['fa-plus', 'Them NK', function () { if (window.openPersonForm) window.openPersonForm(); }],
      gis: ['fa-draw-polygon', 'Ve khu vuc', function () { q('#gisDrawBtn') && q('#gisDrawBtn').click(); }]
    }[screen];
    var fab = q('.mobile-fab');
    if (!fab) return;
    fab.classList.toggle('d-none', !config);
    if (!config) return;
    fab.innerHTML = '<i class="fa-solid ' + config[0] + '"></i><span>' + config[1] + '</span>';
    fab.onclick = config[2];
  }

  function ensureFilterSheet() {
    var backdrop = q('.mobile-filter-sheet-backdrop');
    var sheet = q('.mobile-filter-sheet');
    if (backdrop && sheet) return { backdrop: backdrop, sheet: sheet };
    backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'mobile-filter-sheet-backdrop';
    backdrop.hidden = true;
    backdrop.setAttribute('aria-label', 'Dong bo loc');
    sheet = document.createElement('aside');
    sheet.className = 'mobile-filter-sheet';
    sheet.hidden = true;
    sheet.setAttribute('role', 'dialog');
    sheet.setAttribute('aria-modal', 'true');
    sheet.innerHTML = '<div class="sheet-handle" aria-hidden="true"></div><div class="sheet-head"><strong>Bo loc</strong><button type="button" aria-label="Dong"><i class="fa-solid fa-xmark"></i></button></div><div class="sheet-grid"></div>';
    document.body.appendChild(backdrop);
    document.body.appendChild(sheet);
    backdrop.addEventListener('click', closeFilterSheet);
    q('.sheet-head button', sheet).addEventListener('click', closeFilterSheet);
    return { backdrop: backdrop, sheet: sheet };
  }

  function moveNodesToSheet(title, nodes) {
    var pair = ensureFilterSheet();
    var grid = q('.sheet-grid', pair.sheet);
    grid.innerHTML = '';
    activeSheet = [];
    nodes.forEach(function (node) {
      if (!node || node.classList.contains('mobile-filter-trigger')) return;
      var marker = document.createComment('mobile-filter-source');
      var hidden = node.classList.contains('d-none');
      node.parentNode.insertBefore(marker, node);
      node.classList.remove('d-none');
      grid.appendChild(node);
      activeSheet.push({ node: node, marker: marker, hidden: hidden });
    });
    q('.sheet-head strong', pair.sheet).textContent = title;
  }

  function openFilterSheet(title, nodes) {
    if (!isMobile()) return;
    closeFilterSheet(true);
    moveNodesToSheet(title, nodes);
    var pair = ensureFilterSheet();
    pair.backdrop.hidden = false;
    pair.sheet.hidden = false;
    requestAnimationFrame(function () {
      pair.backdrop.classList.add('is-open');
      pair.sheet.classList.add('is-open');
    });
    document.body.classList.add('mobile-filter-open');
  }

  function closeFilterSheet(immediate) {
    var backdrop = q('.mobile-filter-sheet-backdrop');
    var sheet = q('.mobile-filter-sheet');
    if (sheet) sheet.classList.remove('is-open');
    if (backdrop) backdrop.classList.remove('is-open');
    document.body.classList.remove('mobile-filter-open');
    if (activeSheet) {
      activeSheet.forEach(function (item) {
        if (item.marker.parentNode) item.marker.parentNode.insertBefore(item.node, item.marker);
        if (item.hidden) item.node.classList.add('d-none');
        item.marker.remove();
      });
      activeSheet = null;
    }
    window.setTimeout(function () {
      if (sheet) sheet.hidden = true;
      if (backdrop) backdrop.hidden = true;
    }, immediate ? 0 : 160);
  }

  function children(parent, skipSelector) {
    if (!parent) return [];
    return Array.prototype.slice.call(parent.children).filter(function (child) { return !skipSelector || !child.matches(skipSelector); });
  }

  function trigger(card, label) {
    var button = q(':scope > .mobile-filter-trigger', card);
    if (button) return button;
    button = document.createElement('button');
    button.type = 'button';
    button.className = 'mobile-filter-trigger';
    button.innerHTML = '<i class="fa-solid fa-sliders"></i><span>' + label + '</span>';
    card.appendChild(button);
    return button;
  }

  function bindFilterSheets() {
    var householdCard = q('.household-filter-card');
    var householdGrid = q('.household-filter-grid');
    if (householdCard && householdGrid && !householdCard.dataset.mobileFilterBound) {
      householdCard.dataset.mobileFilterBound = '1';
      trigger(householdCard, 'Bo loc').addEventListener('click', function () {
        openFilterSheet('Bo loc ho gia dinh', children(householdGrid, '.household-search-field'));
      });
    }
    var personCard = q('.person-search-card');
    var personGrid = q('.person-quick-filter-grid');
    if (personCard && personGrid && !personCard.dataset.mobileFilterBound) {
      personCard.dataset.mobileFilterBound = '1';
      trigger(personCard, 'Bo loc').addEventListener('click', function () {
        var nodes = children(personGrid);
        var advanced = q('#personAdvancedFilters');
        openFilterSheet('Bo loc nhan khau', advanced ? nodes.concat([advanced]) : nodes);
      });
    }
    var reportCard = q('.report-filter-card');
    var reportGrid = q('.report-filter-grid');
    if (reportCard && reportGrid && !reportCard.dataset.mobileFilterBound) {
      reportCard.dataset.mobileFilterBound = '1';
      trigger(reportCard, 'Bo loc').addEventListener('click', function () {
        openFilterSheet('Bo loc bao cao', children(reportGrid, '.report-type-field'));
      });
    }
  }

  function bootMobileUi() {
    ensureUserMenu();
    ensureMobileChrome();
    bindFilterSheets();
    syncMobileChrome();
  }

  window.thon09BootMobileUi = bootMobileUi;
  document.addEventListener('DOMContentLoaded', bootMobileUi);
  document.addEventListener('thon09:auth-state', function () { window.setTimeout(bootMobileUi, 0); });
  document.addEventListener('thon09:screen-change', function () { window.setTimeout(bootMobileUi, 0); });
  window.addEventListener('resize', function () { syncMobileChrome(); if (!isMobile()) closeFilterSheet(true); }, { passive: true });
  if (document.readyState !== 'loading') bootMobileUi();
})();
