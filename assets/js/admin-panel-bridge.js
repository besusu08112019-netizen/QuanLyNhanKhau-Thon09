(() => {
  const householdCategories = [
    ['', 'Tất cả'],
    ['poor', 'Hộ nghèo'],
    ['near_poor', 'Hộ cận nghèo'],
    ['escaped_poverty', 'Hộ mới thoát nghèo'],
    ['policy', 'Hộ chính sách'],
    ['meritorious', 'Hộ có công'],
    ['normal', 'Hộ bình thường'],
    ['other', 'Khác'],
  ];

  window.roleLabel = function roleLabel(role) {
    return ({ SUPER_ADMIN:'Super Admin', ADMIN:'Admin', OFFICER:'Cán bộ', VIEWER:'Khách' })[role] || role || '';
  };

  document.addEventListener('DOMContentLoaded', () => {
    loadSprint8Script();
    loadSprint9Script();
    enforceSuperAdminMenu();
    setupHouseholdCategoryFilters();
    setupDigitalGovernmentFeatures();
    setTimeout(setupHouseholdCategoryFilters, 800);
    setTimeout(setupDigitalGovernmentFeatures, 900);
    setTimeout(setupReportCategoryFilter, 1800);
    const previousShowApp = window.showApp;
    if (typeof previousShowApp === 'function') {
      window.showApp = function bridgeShowApp() {
        previousShowApp();
        enforceSuperAdminMenu();
        loadSprint9Script();
        setupHouseholdCategoryFilters();
        setupDigitalGovernmentFeatures();
        setTimeout(setupReportCategoryFilter, 500);
        setTimeout(setupDigitalGovernmentFeatures, 900);
      };
    }
    const nav = document.querySelector('.sidebar .nav');
    if (!nav) return;
    nav.addEventListener('click', event => {
      const button = event.target.closest('.nav-link');
      if (!button) return;
      const screen = button.dataset.screen;
      if (!['users','logs','backups'].includes(screen)) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      switchScreen(screen);
      document.querySelector('#screenTitle').textContent = { users: 'Quản lý tài khoản', logs: 'Nhật ký hệ thống', backups: 'Sao lưu dữ liệu' }[screen];
      const breadcrumb = document.querySelector('#breadcrumbTrail');
      if (breadcrumb) breadcrumb.textContent = 'Trang chủ / ' + document.querySelector('#screenTitle').textContent;
      if (screen === 'users') { ensureRoleOptions(); loadAdminUsers(); }
      if (screen === 'logs') loadAdminLogs();
      if (screen === 'backups') loadAdminBackups();
    }, true);
  });


  const digitalCitizenFields = [
    ['partyMember','Đảng viên'], ['youthUnionMember','Đoàn viên Thanh niên'], ['womenUnionMember','Hội viên Hội Phụ nữ'], ['farmersUnionMember','Hội viên Hội Nông dân'], ['veteransUnionMember','Hội viên Hội Cựu chiến binh'], ['elderlyUnionMember','Hội viên Hội Người cao tuổi'],
    ['meritoriousPerson','Người có công'], ['martyrRelative','Thân nhân liệt sĩ'], ['woundedSoldier','Thương binh'], ['sickSoldier','Bệnh binh'], ['disabledPerson','Người khuyết tật'], ['socialAssistance','Bảo trợ xã hội'],
    ['employed','Có việc làm'], ['unemployed','Thất nghiệp'], ['freelanceLabor','Lao động tự do'], ['outProvinceLabor','Lao động ngoài tỉnh'], ['foreignLabor','Lao động nước ngoài'], ['pupil','Học sinh'], ['student','Sinh viên'], ['retired','Nghỉ hưu'],
  ];

  function setupDigitalGovernmentFeatures() {
    setupDigitalCitizenForm();
    setupDigitalPersonFilters();
    setupDigitalReports();
    setupDigitalDashboard();
  }

  function setupDigitalCitizenForm() {
    const form = document.querySelector('#personForm');
    const bodyRow = form?.querySelector('.modal-body .row');
    if (!form || !bodyRow || form.querySelector('[data-digital-citizen-fields]')) return;
    const group = (title, fields) => '<div class="col-12" data-digital-citizen-fields><div class="border rounded p-3"><div class="fw-semibold mb-2">' + title + '</div><div class="row g-2">' + fields.map(([name,label]) => '<div class="col-md-3 col-sm-6 form-check ms-2"><input class="form-check-input" type="checkbox" name="' + name + '" id="' + name + '"><label class="form-check-label" for="' + name + '">' + label + '</label></div>').join('') + '</div></div></div>';
    bodyRow.insertAdjacentHTML('beforeend', group('Chính trị - Xã hội', digitalCitizenFields.slice(0,6)) + group('Chính sách', digitalCitizenFields.slice(6,12)) + group('Lao động', digitalCitizenFields.slice(12)));
    const originalOpen = window.openPersonForm;
    if (typeof originalOpen === 'function' && !originalOpen.__digitalWrapped) {
      window.openPersonForm = async function digitalOpenPersonForm(id = null) {
        await originalOpen(id);
        const targetForm = document.querySelector('#personForm');
        digitalCitizenFields.forEach(([name]) => { const el = targetForm?.elements[name]; if (el) el.checked = false; });
        if (id) {
          const row = await api('/api/persons/' + id);
          digitalCitizenFields.forEach(([name]) => {
            const snake = name.replace(/[A-Z]/g, m => '_' + m.toLowerCase());
            const el = targetForm?.elements[name];
            if (el) el.checked = Number(row[snake] ?? row[name] ?? 0) > 0;
          });
        }
      };
      window.openPersonForm.__digitalWrapped = true;
    }
  }

  function setupDigitalPersonFilters() {
    const search = document.querySelector('#personSearch');
    if (!search || document.querySelector('#personPartyFilter')) return;
    search.insertAdjacentHTML('afterend', '<select id="personPartyFilter" class="form-select" style="max-width:170px"><option value="">Đảng viên</option><option value="1">Có</option><option value="0">Không</option></select><select id="personYouthFilter" class="form-select" style="max-width:170px"><option value="">Đoàn viên</option><option value="1">Có</option><option value="0">Không</option></select><select id="personPolicyFilter" class="form-select" style="max-width:190px"><option value="">Chính sách</option><option value="meritorious_person">Người có công</option><option value="disabled_person">Người khuyết tật</option></select>');
    const reload = () => { App.persons = App.persons || {}; App.persons.party_member = document.querySelector('#personPartyFilter').value; App.persons.youth_union_member = document.querySelector('#personYouthFilter').value; App.persons.meritorious_person = document.querySelector('#personPolicyFilter').value === 'meritorious_person' ? 1 : ''; App.persons.disabled_person = document.querySelector('#personPolicyFilter').value === 'disabled_person' ? 1 : ''; App.persons.page = 1; window.loadPersons(); };
    ['#personPartyFilter','#personYouthFilter','#personPolicyFilter'].forEach(sel => document.querySelector(sel).addEventListener('change', reload));
  }

  function setupDigitalReports() {
    const select = document.querySelector('#reportForm select[name="type"]');
    if (!select) return;
    const options = [['party_member','Báo cáo Đảng viên'],['youth_union_member','Báo cáo Đoàn viên'],['meritorious_person','Báo cáo Người có công'],['poor-households','Báo cáo Hộ nghèo'],['near-poor-households','Báo cáo Hộ cận nghèo'],['labor','Báo cáo Lao động'],['elderly','Báo cáo Người cao tuổi'],['children','Báo cáo Trẻ em'],['disabled_person','Báo cáo Người khuyết tật']];
    options.forEach(([value,label]) => { if (!Array.from(select.options).some(o => o.value === value)) select.insertAdjacentHTML('beforeend', '<option value="' + value + '">' + label + '</option>'); });
  }

  function setupDigitalDashboard() {
    if (window.loadDashboard?.__digitalWrapped) return;
    const originalLoad = window.loadDashboard;
    if (typeof originalLoad !== 'function') return;
    window.loadDashboard = async function digitalLoadDashboard() {
      await originalLoad();
      try {
        const data = await api('/api/dashboard/summary');
        const m = data.metrics || {}, c = data.charts || {};
        const pct = key => (Number(m[key] || 0)).toLocaleString('vi-VN', { maximumFractionDigits: 2 }) + '%';
        const cards = [
          ['Đảng viên', (m.party_member_count || 0) + ' (' + pct('party_member_percent') + ')', 'fa-landmark-flag'],
          ['Đoàn viên', (m.youth_union_member_count || 0) + ' (' + pct('youth_union_member_percent') + ')', 'fa-people-group'],
          ['Người có công', (m.meritorious_person_count || 0) + ' (' + pct('meritorious_person_percent') + ')', 'fa-award'],
          ['Người khuyết tật', (m.disabled_person_count || 0) + ' (' + pct('disabled_person_percent') + ')', 'fa-wheelchair'],
          ['Có việc làm', (m.employed_count || 0) + ' (' + pct('employed_percent') + ')', 'fa-user-tie'],
          ['Thất nghiệp', (m.unemployed_count || 0) + ' (' + pct('unemployed_percent') + ')', 'fa-user-clock'],
          ['LĐ ngoài tỉnh', m.out_province_labor_count || 0, 'fa-route'],
          ['LĐ nước ngoài', m.foreign_labor_count || 0, 'fa-plane-departure'],
        ];
        const host = document.querySelector('#dashboardCards');
        if (host && !host.querySelector('[data-digital-kpi]')) host.insertAdjacentHTML('beforeend', cards.map(([label,value,icon]) => '<div class="col-sm-6 col-xl-3" data-digital-kpi><div class="metric-card admin-metric"><i class="fa-solid ' + icon + '"></i><div><div class="metric-label">' + label + '</div><div class="metric-value">' + value + '</div></div></div></div>').join(''));
        ensureDigitalChartHost();
        renderChart('#partyChart', c.partyMembers || []);
        renderChart('#youthChart', c.youthUnion || []);
        renderChart('#laborChart', c.labor || []);
        renderChart('#occupationChart', c.occupations || []);
        renderChart('#ethnicityChart', c.ethnicities || []);
        renderChart('#religionChart', c.religions || []);
      } catch (_) {}
    };
    window.loadDashboard.__digitalWrapped = true;
  }

  function ensureDigitalChartHost() {
    if (document.querySelector('#partyChart')) return;
    const dashboard = document.querySelector('#dashboardScreen');
    if (!dashboard) return;
    dashboard.insertAdjacentHTML('beforeend', '<div class="row g-3 mt-1" data-digital-charts><div class="col-lg-4"><div class="content-card"><h3 class="section-title">Đảng viên</h3><div id="partyChart" class="chart-list"></div></div></div><div class="col-lg-4"><div class="content-card"><h3 class="section-title">Đoàn viên</h3><div id="youthChart" class="chart-list"></div></div></div><div class="col-lg-4"><div class="content-card"><h3 class="section-title">Lao động</h3><div id="laborChart" class="chart-list"></div></div></div><div class="col-lg-4"><div class="content-card"><h3 class="section-title">Nghề nghiệp</h3><div id="occupationChart" class="chart-list"></div></div></div><div class="col-lg-4"><div class="content-card"><h3 class="section-title">Dân tộc</h3><div id="ethnicityChart" class="chart-list"></div></div></div><div class="col-lg-4"><div class="content-card"><h3 class="section-title">Tôn giáo</h3><div id="religionChart" class="chart-list"></div></div></div></div>');
  }

  function setupHouseholdCategoryFilters() {
    const toolbar = document.querySelector('#householdsScreen .toolbar');
    const search = document.querySelector('#householdSearch');
    if (!toolbar || !search || document.querySelector('#householdCategoryFilter')) return;
    App.households = App.households || { page: 1, pageSize: 20, search: '' };
    App.households.category = App.households.category || '';
    search.insertAdjacentHTML('afterend', `<select id="householdCategoryFilter" class="form-select" style="max-width:220px">${householdCategories.map(([value, label]) => `<option value="${value}">${label}</option>`).join('')}</select>`);
    document.querySelector('#householdCategoryFilter').addEventListener('change', () => {
      App.households.category = document.querySelector('#householdCategoryFilter').value;
      App.households.household_type = App.households.category;
      App.households.page = 1;
      window.loadHouseholds();
    });
    search.addEventListener('input', () => {
      App.households.search = search.value.trim();
      App.households.page = 1;
    }, true);
    window.loadHouseholds = loadHouseholdsWithCategory;
  }

  async function loadHouseholdsWithCategory() {
    try {
      App.households = App.households || { page: 1, pageSize: 20, search: '' };
      const category = App.households.category || App.households.household_type || '';
      const params = new URLSearchParams({ page: App.households.page || 1, pageSize: App.households.pageSize || 20 });
      if (App.households.search) params.set('search', App.households.search);
      if (category) { params.set('category', category); params.set('household_type', category); }
      const data = await api('/api/households?' + params.toString());
      const items = data.items || [];
      const tbody = document.querySelector('#householdRows');
      if (!tbody) return;
      tbody.innerHTML = items.map(row => '<tr>' +
        '<td><input type="checkbox" class="household-check" value="' + row.id + '"></td>' +
        '<td><button class="btn btn-link p-0 fw-semibold" onclick="showHousehold(' + row.id + ')">' + escapeHtml(row.household_code) + '</button></td>' +
        '<td>' + escapeHtml(row.head_citizen_name || '') + '</td>' +
        '<td>' + escapeHtml(row.address || '') + '</td>' +
        '<td>' + number(row.at_home_count || 0) + '</td>' +
        '<td>' + number(row.away_count || 0) + '</td>' +
        '<td>' + householdBadges(row) + '</td>' +
        '<td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="openHouseholdForm(' + row.id + ')">Sửa</button> <button class="btn btn-sm btn-outline-danger" onclick="deleteHousehold(' + row.id + ')">Xóa</button></td>' +
      '</tr>').join('') || emptyRow(8, 'Không có dữ liệu');
      renderPager('#householdPager', data, page => { App.households.page = page; window.loadHouseholds(); });
    } catch (error) { showToast('Không tải được danh sách hộ dân: ' + error.message, 'danger'); }
  }

  function setupReportCategoryFilter() {
    const form = document.querySelector('#reportForm');
    if (!form) return;
    if (!form.querySelector('[name="householdType"]')) {
      const viewButtonCol = form.querySelector('button[type="submit"]')?.closest('[class*="col-"]');
      const html = `<div class="col-md-2"><label class="form-label">Diện hộ</label><select name="householdType" class="form-select">${householdCategories.map(([value, label]) => `<option value="${value}">${label}</option>`).join('')}</select></div>`;
      (viewButtonCol || form.querySelector('.row')).insertAdjacentHTML('beforebegin', html);
    }
    window.thon09ViewReport = viewReportFromApi;
    form.onsubmit = event => { event.preventDefault(); event.stopPropagation(); viewReportFromApi(); return false; };
    const submit = form.querySelector('button[type="submit"]');
    if (submit) submit.onclick = event => { event.preventDefault(); event.stopPropagation(); viewReportFromApi(); return false; };
    setupDigitalReports();
    const excel = document.querySelector('#reportExcelBtn');
    if (excel) excel.onclick = event => { event.preventDefault(); downloadReport('excel'); return false; };
    const pdf = document.querySelector('#reportPdfBtn');
    if (pdf) pdf.onclick = event => { event.preventDefault(); downloadReport('pdf'); return false; };
    const print = document.querySelector('#reportPrintBtn');
    if (print) print.onclick = event => { event.preventDefault(); viewReportFromApi(true); return false; };
  }

  async function viewReportFromApi(printAfter = false) {
    const form = document.querySelector('#reportForm');
    const preview = document.querySelector('#reportPreview');
    const titleEl = document.querySelector('#reportTitle');
    const countEl = document.querySelector('#reportCount');
    if (!form || !preview) return;
    try {
      preview.innerHTML = '<p class="text-muted mb-0">Đang tải dữ liệu...</p>';
      const params = new URLSearchParams(new FormData(form));
      const data = await api('/api/reports/summary?' + params.toString());
      if (titleEl) titleEl.textContent = data.title || 'Báo cáo';
      if (countEl) countEl.textContent = number(data.totalRows || 0) + ' dòng';
      preview.innerHTML = reportTable(data.headers || [], data.rows || []);
      if (printAfter) window.print();
    } catch (error) {
      preview.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(error.message) + '</div>';
    }
  }

  function downloadReport(format) {
    const form = document.querySelector('#reportForm');
    if (!form) return;
    const params = new URLSearchParams(new FormData(form));
    const endpoint = format === 'pdf' ? '/api/reports/export-pdf' : '/api/reports/export-excel';
    window.location.href = endpoint + '?' + params.toString();
  }

  function reportTable(headers, rows) {
    const head = headers.map(header => '<th>' + escapeHtml(header) + '</th>').join('');
    const body = rows.length ? rows.map(row => '<tr>' + row.map(cell => '<td>' + escapeHtml(cell ?? '') + '</td>').join('') + '</tr>').join('') : '<tr><td colspan="' + Math.max(1, headers.length) + '" class="text-center text-muted py-3">Không có dữ liệu</td></tr>';
    return '<table class="table table-bordered table-hover align-middle mb-0"><thead><tr>' + head + '</tr></thead><tbody>' + body + '</tbody></table>';
  }

  function loadSprint8Script() {
    if (document.querySelector('script[src*="sprint8.js"]')) return;
    const script = document.createElement('script');
    script.src = 'assets/js/sprint8.js?v=20260628-sprint8';
    script.defer = true;
    document.body.appendChild(script);
  }

  function loadSprint9Script() {
    if (document.querySelector('script[src*="sprint9.js"]')) return;
    const script = document.createElement('script');
    script.src = 'assets/js/sprint9.js?v=20260629-sprint9';
    script.defer = true;
    document.body.appendChild(script);
  }

  function scheduleCategoryFilterSetup() {
    setupHouseholdCategoryFilters();
    setupReportCategoryFilter();
    setTimeout(setupHouseholdCategoryFilters, 250);
    setTimeout(setupHouseholdCategoryFilters, 1000);
    setTimeout(setupReportCategoryFilter, 1000);
  }

  document.addEventListener('click', event => {
    const button = event.target.closest('[data-screen]');
    if (!button) return;
    if (button.dataset.screen === 'households') { setTimeout(setupHouseholdCategoryFilters, 250); setTimeout(setupDigitalGovernmentFeatures, 300); }
    if (button.dataset.screen === 'reports') { setTimeout(setupReportCategoryFilter, 250); setTimeout(setupDigitalGovernmentFeatures, 350); }
    if (button.dataset.screen === 'persons' || button.dataset.screen === 'dashboard') setTimeout(setupDigitalGovernmentFeatures, 300);
  }, true);

  let categorySetupTicks = 0;
  const categorySetupTimer = setInterval(() => {
    categorySetupTicks += 1;
    scheduleCategoryFilterSetup();
    if (categorySetupTicks >= 12 || (document.querySelector('#householdCategoryFilter') && document.querySelector('#reportForm [name="householdType"]'))) {
      clearInterval(categorySetupTimer);
    }
  }, 1000);

  function enforceSuperAdminMenu() {
    setTimeout(() => {
      const role = App.user?.role || '';
      const adminOnly = ['users','permissions','logs','settings','backups','restore'];
      document.querySelectorAll('.sidebar .nav-link').forEach(btn => {
        btn.classList.toggle('d-none', adminOnly.includes(btn.dataset.screen) && role !== 'SUPER_ADMIN');
      });
    }, 0);
  }

  function ensureRoleOptions() {
    const select = document.querySelector('#userForm select[name="role"]');
    if (!select) return;
    select.innerHTML = ['ADMIN','OFFICER','VIEWER'].map(role => `<option value="${role}">${roleLabel(role)}</option>`).join('');
  }

  async function loadAdminUsers() {
    if (!document.querySelector('#userRows')) return;
    App.users = App.users || { page: 1, pageSize: 20, search: '' };
    const data = await api('/api/users?' + new URLSearchParams(App.users));
    if (typeof window.renderUserRowsSprint8 === 'function') window.renderUserRowsSprint8(data);
    else document.querySelector('#userRows').innerHTML = data.items.map(row => `<tr><td>${escapeHtml(row.email)}</td><td>${escapeHtml(row.display_name)}</td><td>${roleLabel(row.role)}</td><td>${statusLabel(row.status)}</td><td>${escapeHtml(row.last_login_at || '')}</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="openUserForm(${row.id})">Sửa</button> <button class="btn btn-sm btn-outline-warning" onclick="toggleUser(${row.id}, '${row.status === 'ACTIVE' ? 'lock' : 'unlock'}')">${row.status === 'ACTIVE' ? 'Khóa' : 'Mở khóa'}</button> <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${row.id})">Xóa</button></td></tr>`).join('') || emptyRow(6, 'Chưa có người dùng');
    renderPager('#userPager', data, page => { App.users.page = page; loadAdminUsers(); });
  }

  async function loadAdminLogs() {
    if (!document.querySelector('#logRows')) return;
    App.logs = App.logs || { page: 1, pageSize: 50, search: '' };
    const data = await api('/api/logs?' + new URLSearchParams(App.logs));
    document.querySelector('#logRows').innerHTML = data.items.map(row => `<tr><td>${escapeHtml(row.created_at)}</td><td>${escapeHtml(row.actor_email || '')}</td><td>${escapeHtml(row.module)}</td><td>${escapeHtml(row.action)}</td><td>${escapeHtml(row.message)}</td></tr>`).join('') || emptyRow(5, 'Chưa có nhật ký');
    renderPager('#logPager', data, page => { App.logs.page = page; loadAdminLogs(); });
  }

  async function loadAdminBackups() {
    if (!document.querySelector('#backupRows')) return;
    App.backups = App.backups || { page: 1, pageSize: 20 };
    const data = await api('/api/backups?' + new URLSearchParams(App.backups));
    document.querySelector('#backupRows').innerHTML = data.items.map(row => `<tr><td>${escapeHtml(row.created_at)}</td><td>${escapeHtml(row.file_name)}</td><td>${number(row.file_size || 0)} byte</td><td>${escapeHtml(row.status)}</td><td>${escapeHtml(row.created_by_email || '')}</td></tr>`).join('') || emptyRow(5, 'Chưa có bản sao lưu');
    renderPager('#backupPager', data, page => { App.backups.page = page; loadAdminBackups(); });
  }

  function statusLabel(status) { return status === 'ACTIVE' ? 'Hoạt động' : status === 'INACTIVE' ? 'Đã khóa' : status; }
})();