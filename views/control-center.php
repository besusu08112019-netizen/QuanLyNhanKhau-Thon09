<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>{{APP_NAME}}</title>
  <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/vendor/fontawesome-local.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/app.min.css">
  <style>
    :root {
      --cc-ink: #17202a;
      --cc-muted: #667085;
      --cc-line: #d8dee8;
      --cc-bg: #f4f6f9;
      --cc-panel: #ffffff;
      --cc-brand: #0f766e;
      --cc-blue: #2563eb;
      --cc-warn: #b45309;
      --cc-danger: #b42318;
    }

    body {
      margin: 0;
      background: var(--cc-bg);
      color: var(--cc-ink);
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .control-center {
      min-height: 100vh;
      display: grid;
      grid-template-columns: 280px minmax(0, 1fr);
    }

    .cc-sidebar {
      background: #101828;
      color: #f9fafb;
      padding: 20px 16px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .cc-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      min-height: 44px;
    }

    .cc-brand-mark {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      display: grid;
      place-items: center;
      background: var(--cc-brand);
      font-weight: 700;
    }

    .cc-brand-title {
      font-size: 15px;
      font-weight: 700;
      line-height: 1.25;
    }

    .cc-brand-subtitle {
      color: #cbd5e1;
      font-size: 12px;
    }

    .cc-nav {
      display: grid;
      gap: 6px;
    }

    .cc-nav button {
      border: 0;
      border-radius: 8px;
      background: transparent;
      color: #d0d5dd;
      display: flex;
      align-items: center;
      gap: 10px;
      min-height: 40px;
      padding: 9px 10px;
      text-align: left;
      font-weight: 600;
    }

    .cc-nav button.active,
    .cc-nav button:hover {
      background: #1d2939;
      color: #ffffff;
    }

    .cc-main {
      min-width: 0;
      display: flex;
      flex-direction: column;
    }

    .cc-header {
      min-height: 68px;
      padding: 14px 24px;
      background: var(--cc-panel);
      border-bottom: 1px solid var(--cc-line);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }

    .cc-title {
      margin: 0;
      font-size: 20px;
      font-weight: 750;
      line-height: 1.2;
    }

    .cc-meta {
      color: var(--cc-muted);
      font-size: 13px;
    }

    .cc-content {
      padding: 24px;
      display: grid;
      gap: 20px;
    }

    .cc-section {
      display: none;
      gap: 16px;
    }

    .cc-section.active {
      display: grid;
    }

    .metric-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
    }

    .metric-card,
    .cc-panel {
      background: var(--cc-panel);
      border: 1px solid var(--cc-line);
      border-radius: 8px;
    }

    .metric-card {
      min-height: 108px;
      padding: 16px;
      display: grid;
      align-content: space-between;
      gap: 8px;
    }

    .metric-label {
      color: var(--cc-muted);
      font-size: 13px;
      font-weight: 650;
    }

    .metric-value {
      font-size: 30px;
      line-height: 1;
      font-weight: 800;
      letter-spacing: 0;
    }

    .metric-note {
      color: var(--cc-muted);
      font-size: 12px;
    }

    .cc-panel {
      overflow: hidden;
    }

    .cc-panel-header {
      padding: 14px 16px;
      border-bottom: 1px solid var(--cc-line);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }

    .cc-panel-title {
      margin: 0;
      font-size: 16px;
      font-weight: 750;
    }

    .cc-toolbar {
      padding: 12px 16px;
      border-bottom: 1px solid var(--cc-line);
      display: grid;
      grid-template-columns: minmax(220px, 1fr) 180px auto;
      gap: 10px;
      align-items: center;
    }

    .cc-input,
    .cc-select {
      width: 100%;
      min-height: 40px;
      border: 1px solid #cfd6e2;
      border-radius: 8px;
      padding: 8px 10px;
      background: #ffffff;
      color: var(--cc-ink);
      font: inherit;
    }

    .cc-btn {
      min-height: 40px;
      border: 1px solid #cfd6e2;
      border-radius: 8px;
      padding: 8px 12px;
      background: #ffffff;
      color: var(--cc-ink);
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      white-space: nowrap;
    }

    .cc-btn:hover {
      background: #f8fafc;
    }

    .cc-btn.primary {
      border-color: var(--cc-brand);
      background: var(--cc-brand);
      color: #ffffff;
    }

    .cc-btn.danger {
      border-color: #fecdca;
      color: var(--cc-danger);
      background: #fff7f6;
    }

    .cc-btn:disabled {
      cursor: not-allowed;
      opacity: 0.65;
    }

    .cc-row-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .cc-state {
      padding: 16px;
      color: var(--cc-muted);
      font-size: 14px;
    }

    .cc-alert {
      display: none;
      margin: 0 16px 12px;
      padding: 10px 12px;
      border: 1px solid #fedf89;
      border-radius: 8px;
      background: #fffaeb;
      color: #93370d;
      font-weight: 650;
    }

    .cc-alert.active {
      display: block;
    }

    .cc-modal-backdrop {
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
      background: rgba(15, 23, 42, 0.45);
      z-index: 50;
    }

    .cc-modal-backdrop.active {
      display: flex;
    }

    .cc-modal {
      width: min(760px, 100%);
      max-height: calc(100vh - 40px);
      overflow: auto;
      background: #ffffff;
      border-radius: 8px;
      box-shadow: 0 24px 80px rgba(15, 23, 42, 0.24);
    }

    .cc-modal-header,
    .cc-modal-footer {
      padding: 14px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      border-bottom: 1px solid var(--cc-line);
    }

    .cc-modal-footer {
      border-top: 1px solid var(--cc-line);
      border-bottom: 0;
      justify-content: flex-end;
    }

    .cc-modal-title {
      margin: 0;
      font-size: 18px;
      font-weight: 760;
    }

    .cc-form {
      padding: 16px;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
    }

    .cc-field {
      display: grid;
      gap: 6px;
    }

    .cc-field.full {
      grid-column: 1 / -1;
    }

    .cc-field label {
      color: #344054;
      font-size: 13px;
      font-weight: 700;
    }

    .cc-form-error {
      display: none;
      margin: 0 16px 14px;
      color: var(--cc-danger);
      font-weight: 700;
    }

    .cc-form-error.active {
      display: block;
    }

    .cc-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }

    .cc-table th,
    .cc-table td {
      padding: 12px 14px;
      border-bottom: 1px solid #eef1f5;
      text-align: left;
      vertical-align: middle;
    }

    .cc-table th {
      color: #475467;
      background: #f8fafc;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0;
    }

    .cc-badge {
      display: inline-flex;
      align-items: center;
      min-height: 24px;
      border-radius: 999px;
      padding: 3px 10px;
      font-size: 12px;
      font-weight: 700;
      background: #ecfdf3;
      color: #027a48;
    }

    .cc-badge.warn {
      background: #fffaeb;
      color: var(--cc-warn);
    }

    .cc-badge.danger {
      background: #fef3f2;
      color: var(--cc-danger);
    }

    .monitor-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }

    .monitor-item {
      padding: 14px 16px;
      border-bottom: 1px solid #eef1f5;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }

    .cc-footer {
      margin-top: auto;
      padding: 14px 24px;
      color: var(--cc-muted);
      font-size: 12px;
      border-top: 1px solid var(--cc-line);
      background: var(--cc-panel);
    }

    @media (max-width: 1100px) {
      .metric-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 760px) {
      .control-center {
        grid-template-columns: 1fr;
      }

      .cc-sidebar {
        position: static;
      }

      .cc-nav {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .cc-header,
      .cc-content,
      .cc-footer {
        padding-left: 16px;
        padding-right: 16px;
      }

      .metric-grid,
      .monitor-grid,
      .cc-toolbar,
      .cc-form {
        grid-template-columns: 1fr;
      }

      .cc-table {
        min-width: 760px;
      }

      .cc-table-wrap {
        overflow-x: auto;
      }
    }
  </style>
  <script>
    window.AppSettings={{APP_SETTINGS_JSON}};
  </script>
</head>
<body>
  <div class="control-center">
    <aside class="cc-sidebar">
      <div class="cc-brand">
        <div class="cc-brand-mark">CC</div>
        <div>
          <div class="cc-brand-title">Community Control Center</div>
          <div class="cc-brand-subtitle">CONTROL_CENTER</div>
        </div>
      </div>
      <nav class="cc-nav" aria-label="Control Center">
        <button class="active" type="button" data-section="dashboard"><i class="fa-solid fa-chart-line"></i>Dashboard tong</button>
        <button type="button" data-section="units"><i class="fa-solid fa-sitemap"></i>Don vi hanh chinh</button>
        <button type="button" data-section="accounts"><i class="fa-solid fa-users-gear"></i>Tai khoan he thong</button>
        <button type="button" data-section="monitoring"><i class="fa-solid fa-heart-pulse"></i>Monitoring</button>
      </nav>
    </aside>

    <main class="cc-main">
      <header class="cc-header">
        <div>
          <h1 class="cc-title" id="sectionTitle">Dashboard tong</h1>
          <div class="cc-meta" id="portalMeta">Community Control Center - {{APP_NAME}}</div>
        </div>
        <span class="cc-badge" id="healthBadge">Dang kiem tra</span>
      </header>

      <div class="cc-content">
        <section class="cc-section active" id="dashboardSection">
          <div class="metric-grid" id="metricGrid"></div>
        </section>

        <section class="cc-section" id="unitsSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Quan ly don vi hanh chinh</h2>
              <button class="cc-btn primary" type="button" id="addUnitButton"><i class="fa-solid fa-plus"></i>Them don vi</button>
            </div>
            <div class="cc-toolbar">
              <input class="cc-input" type="search" id="unitSearch" placeholder="Tim theo ma, ten, domain">
              <select class="cc-select" id="unitStatusFilter" aria-label="Loc trang thai">
                <option value="">Tat ca trang thai</option>
                <option value="ACTIVE">Dang hoat dong</option>
                <option value="INACTIVE">Da khoa</option>
              </select>
              <button class="cc-btn" type="button" id="refreshUnitsButton"><i class="fa-solid fa-rotate"></i>Tai lai</button>
            </div>
            <div class="cc-alert" id="unitsAlert"></div>
            <div class="cc-table-wrap">
              <table class="cc-table">
                <thead>
                  <tr>
                    <th>Ma</th>
                    <th>Ten don vi</th>
                    <th>Domain</th>
                    <th>Trang thai</th>
                    <th>Nguoi quan ly</th>
                    <th>Phien ban</th>
                    <th>Health</th>
                    <th>Thao tac</th>
                  </tr>
                </thead>
                <tbody id="unitsBody"></tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="cc-section" id="accountsSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Quan ly tai khoan he thong</h2>
              <span class="cc-meta">Chua mo phan quyen chi tiet</span>
            </div>
            <div class="cc-table-wrap">
              <table class="cc-table">
                <thead>
                  <tr>
                    <th>Role</th>
                    <th>Ten hien thi</th>
                    <th>So tai khoan</th>
                    <th>Trang thai</th>
                  </tr>
                </thead>
                <tbody id="accountsBody"></tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="cc-section" id="monitoringSection">
          <div class="monitor-grid" id="monitorGrid"></div>
        </section>
      </div>

      <footer class="cc-footer">
        Community Control Center Platform. Tenant Portal va Business Modules duoc giu tach biet.
      </footer>
    </main>
  </div>

  <div class="cc-modal-backdrop" id="unitModal" role="dialog" aria-modal="true" aria-labelledby="unitModalTitle">
    <div class="cc-modal">
      <div class="cc-modal-header">
        <h2 class="cc-modal-title" id="unitModalTitle">Them don vi</h2>
        <button class="cc-btn" type="button" id="closeUnitModalButton" aria-label="Dong"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="unitForm" novalidate>
        <div class="cc-form">
          <input type="hidden" id="unitId">
          <div class="cc-field">
            <label for="unitCode">Ma don vi *</label>
            <input class="cc-input" id="unitCode" name="code" required maxlength="50" pattern="[a-z0-9_-]{2,50}" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitName">Ten don vi *</label>
            <input class="cc-input" id="unitName" name="name" required maxlength="190" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitCommuneName">Xa/Phuong</label>
            <input class="cc-input" id="unitCommuneName" name="commune_name" maxlength="190" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitStatus">Trang thai</label>
            <select class="cc-select" id="unitStatus" name="status">
              <option value="ACTIVE">Dang hoat dong</option>
              <option value="INACTIVE">Da khoa</option>
            </select>
          </div>
          <div class="cc-field">
            <label for="unitDomain">Domain</label>
            <input class="cc-input" id="unitDomain" name="domain" maxlength="190" placeholder="thon09.hongphongnb.com" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitSubdomain">Subdomain</label>
            <input class="cc-input" id="unitSubdomain" name="subdomain" maxlength="190" placeholder="thon09.hongphongnb.com" autocomplete="off">
          </div>
          <div class="cc-field full">
            <label for="unitLogo">Logo URL</label>
            <input class="cc-input" id="unitLogo" name="logo" maxlength="500" placeholder="/assets/logo.png" autocomplete="off">
          </div>
        </div>
        <div class="cc-form-error" id="unitFormError"></div>
        <div class="cc-modal-footer">
          <button class="cc-btn" type="button" id="cancelUnitButton">Huy</button>
          <button class="cc-btn primary" type="submit" id="saveUnitButton"><i class="fa-solid fa-floppy-disk"></i>Luu</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const sections = {
      dashboard: document.getElementById('dashboardSection'),
      units: document.getElementById('unitsSection'),
      accounts: document.getElementById('accountsSection'),
      monitoring: document.getElementById('monitoringSection')
    };
    const sectionTitles = {
      dashboard: 'Dashboard tong',
      units: 'Don vi hanh chinh',
      accounts: 'Tai khoan he thong',
      monitoring: 'Monitoring'
    };

    document.querySelectorAll('.cc-nav button').forEach((button) => {
      button.addEventListener('click', () => {
        document.querySelectorAll('.cc-nav button').forEach((item) => item.classList.remove('active'));
        button.classList.add('active');
        Object.values(sections).forEach((section) => section.classList.remove('active'));
        sections[button.dataset.section].classList.add('active');
        document.getElementById('sectionTitle').textContent = sectionTitles[button.dataset.section];
      });
    });

    const nf = new Intl.NumberFormat('vi-VN');
    const percent = (value) => `${Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 1 })}%`;
    const storageKey = window.tenantStorageKey || function (key) {
      const namespace = String(location.host || 'control_center').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '') || 'control_center';
      return namespace + '_' + String(key || '').replace(/^_+/, '');
    };
    const unitState = {
      items: [],
      editing: null,
      loading: false
    };

    function metric(label, value, note = '') {
      const card = document.createElement('article');
      card.className = 'metric-card';
      card.innerHTML = '<div class="metric-label"></div><div class="metric-value"></div><div class="metric-note"></div>';
      card.children[0].textContent = label;
      card.children[1].textContent = value;
      card.children[2].textContent = note;
      return card;
    }

    function badge(value) {
      const span = document.createElement('span');
      span.className = 'cc-badge';
      if (value === 'LOCKED' || value === 'DEGRADED') span.classList.add('warn');
      if (value === 'ERROR') span.classList.add('danger');
      span.textContent = value || 'UNKNOWN';
      return span;
    }

    function authHeaders(method) {
      const headers = { Accept: 'application/json' };
      const token = (window.App && window.App.token) || localStorage.getItem(storageKey('token')) || localStorage.getItem('token') || '';
      const csrf = (window.App && window.App.csrfToken) || localStorage.getItem(storageKey('csrf')) || localStorage.getItem('csrf') || '';
      if (token) headers.Authorization = 'Bearer ' + token;
      if (method !== 'GET' && method !== 'HEAD' && csrf) headers['X-CSRF-Token'] = csrf;
      return headers;
    }

    async function api(path, options = {}) {
      const method = options.method || 'GET';
      const headers = authHeaders(method);
      const init = { method, headers, cache: 'no-store' };
      if (options.body) {
        headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(options.body);
      }
      const response = await fetch(path, init);
      const payload = await response.json();
      if (!payload.ok) throw new Error(payload.message || 'Request failed');
      return payload.data;
    }

    async function loadDashboard() {
      const data = await api('/api/control-center/dashboard');
      const metrics = [
        ['Tong don vi', nf.format(data.totalUnits), 'Don vi dang quan ly'],
        ['Tong ho', nf.format(data.totalHouseholds), 'Tong hop toan he thong'],
        ['Tong nhan khau', nf.format(data.totalCitizens), 'Khong hien thi du lieu ca nhan'],
        ['Tong tre em', nf.format(data.totalChildren), 'So lieu tong hop'],
        ['Tong nguoi cao tuoi', nf.format(data.totalElderly), 'Theo cau hinh chinh sach hien co'],
        ['Tong lao dong', nf.format(data.totalWorkers), 'Theo truong lao dong hien co'],
        ['Tong Dang vien', nf.format(data.totalPartyMembers), 'So lieu tong hop'],
        ['Tong ty le BHYT', percent(data.healthInsuranceRate), 'Tren nhan khau con song']
      ];
      const grid = document.getElementById('metricGrid');
      grid.replaceChildren(...metrics.map((item) => metric(item[0], item[1], item[2])));
    }

    async function loadUnits() {
      const body = document.getElementById('unitsBody');
      body.replaceChildren(stateRow(8, 'Dang tai du lieu...'));
      setUnitsAlert('');
      const params = new URLSearchParams();
      const search = document.getElementById('unitSearch').value.trim();
      const status = document.getElementById('unitStatusFilter').value;
      if (search) params.set('search', search);
      if (status) params.set('status', status);
      const data = await api('/api/control-center/units' + (params.toString() ? '?' + params.toString() : ''));
      unitState.items = data.items || [];
      const rows = unitState.items.map((unit) => {
        const tr = document.createElement('tr');
        const cells = [unit.code || '-', unit.name, unit.domain || '-', unit.status, unit.manager, unit.version || '-', unit.healthStatus];
        cells.forEach((cell, index) => {
          const td = document.createElement('td');
          if (index === 3 || index === 6) td.appendChild(badge(cell));
          else td.textContent = cell;
          tr.appendChild(td);
        });
        const actions = document.createElement('td');
        actions.className = 'cc-row-actions';
        const edit = actionButton('Sua', 'fa-pen-to-square');
        edit.addEventListener('click', () => openUnitModal(unit));
        actions.appendChild(edit);
        if (unit.status === 'ACTIVE') {
          const lock = actionButton('Khoa', 'fa-lock', 'danger');
          lock.addEventListener('click', () => changeUnitStatus(unit, 'lock'));
          actions.appendChild(lock);
        } else {
          const activate = actionButton('Kich hoat', 'fa-unlock');
          activate.addEventListener('click', () => changeUnitStatus(unit, 'activate'));
          actions.appendChild(activate);
        }
        tr.appendChild(actions);
        return tr;
      });
      body.replaceChildren(...(rows.length ? rows : [emptyRow(8)]));
    }

    async function loadAccounts() {
      const data = await api('/api/control-center/accounts');
      const rows = (data.roles || []).map((role) => {
        const tr = document.createElement('tr');
        [role.code, role.name, nf.format(role.users), role.status].forEach((cell, index) => {
          const td = document.createElement('td');
          if (index === 3) td.appendChild(badge(cell));
          else td.textContent = cell;
          tr.appendChild(td);
        });
        return tr;
      });
      document.getElementById('accountsBody').replaceChildren(...rows);
    }

    async function loadMonitoring() {
      const data = await api('/api/control-center/monitoring');
      const usedBytes = Math.max(0, Number(data.storage.totalBytes || 0) - Number(data.storage.freeBytes || 0));
      const items = [
        ['Version', data.version],
        ['Runtime', `PHP ${data.runtime.phpVersion}`],
        ['Database Status', data.database.ok ? 'Connected' : 'Unavailable'],
        ['Storage', `${formatBytes(usedBytes)} / ${formatBytes(data.storage.totalBytes)}`],
        ['Storage Writable', data.storage.writable ? 'OK' : 'DEGRADED'],
        ['Health Check', data.healthCheck.status]
      ];
      document.getElementById('healthBadge').textContent = data.healthCheck.status;
      document.getElementById('healthBadge').className = data.healthCheck.status === 'OK' ? 'cc-badge' : 'cc-badge warn';
      document.getElementById('monitorGrid').replaceChildren(...items.map(([label, value]) => metric(label, value || '-', '')));
    }

    function emptyRow(colspan) {
      return stateRow(colspan, 'Chua co du lieu hien thi');
    }

    function stateRow(colspan, text) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = colspan;
      td.className = 'cc-state';
      td.textContent = text;
      tr.appendChild(td);
      return tr;
    }

    function actionButton(label, icon, variant = '') {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'cc-btn' + (variant ? ' ' + variant : '');
      button.innerHTML = '<i class="fa-solid ' + icon + '"></i><span></span>';
      button.querySelector('span').textContent = label;
      return button;
    }

    function setUnitsAlert(message) {
      const alert = document.getElementById('unitsAlert');
      alert.textContent = message || '';
      alert.classList.toggle('active', Boolean(message));
    }

    function formValue(id) {
      return document.getElementById(id).value.trim();
    }

    function openUnitModal(unit = null) {
      unitState.editing = unit;
      document.getElementById('unitModalTitle').textContent = unit ? 'Sua don vi' : 'Them don vi';
      document.getElementById('unitId').value = unit?.id || '';
      document.getElementById('unitCode').value = unit?.code || '';
      document.getElementById('unitCode').disabled = Boolean(unit);
      document.getElementById('unitName').value = unit?.name || '';
      document.getElementById('unitCommuneName').value = unit?.communeName || '';
      document.getElementById('unitDomain').value = unit?.domain || '';
      document.getElementById('unitSubdomain').value = unit?.subdomain || '';
      document.getElementById('unitLogo').value = unit?.logo || '';
      document.getElementById('unitStatus').value = unit?.status || 'ACTIVE';
      setFormError('');
      document.getElementById('unitModal').classList.add('active');
      document.getElementById(unit ? 'unitName' : 'unitCode').focus();
    }

    function closeUnitModal() {
      document.getElementById('unitModal').classList.remove('active');
      unitState.editing = null;
    }

    function unitPayload() {
      const payload = {
        name: formValue('unitName'),
        commune_name: formValue('unitCommuneName') || null,
        domain: formValue('unitDomain') || null,
        subdomain: formValue('unitSubdomain') || null,
        logo: formValue('unitLogo') || null,
        status: formValue('unitStatus') || 'ACTIVE',
        type: 'VILLAGE'
      };
      if (!unitState.editing) payload.code = formValue('unitCode').toLowerCase();
      return payload;
    }

    function validateUnitForm(payload) {
      if (!unitState.editing && !/^[a-z0-9_-]{2,50}$/.test(payload.code || '')) {
        return 'Ma don vi chi gom chu thuong, so, dau gach ngang/gach duoi va tu 2 den 50 ky tu';
      }
      if (!payload.name || payload.name.length > 190) {
        return 'Ten don vi la bat buoc va khong vuot qua 190 ky tu';
      }
      return '';
    }

    function setFormError(message) {
      const error = document.getElementById('unitFormError');
      error.textContent = message || '';
      error.classList.toggle('active', Boolean(message));
    }

    async function saveUnit(event) {
      event.preventDefault();
      const button = document.getElementById('saveUnitButton');
      const payload = unitPayload();
      const validation = validateUnitForm(payload);
      if (validation) {
        setFormError(validation);
        return;
      }
      button.disabled = true;
      setFormError('');
      try {
        if (unitState.editing) {
          await api('/api/control-center/units/' + encodeURIComponent(unitState.editing.id), { method: 'PUT', body: payload });
        } else {
          await api('/api/control-center/units', { method: 'POST', body: payload });
        }
        closeUnitModal();
        await loadUnits();
      } catch (error) {
        setFormError(error.message || 'Khong luu duoc don vi');
      } finally {
        button.disabled = false;
      }
    }

    async function changeUnitStatus(unit, action) {
      const isLock = action === 'lock';
      const message = isLock ? 'Xac nhan khoa don vi nay?' : 'Xac nhan kich hoat don vi nay?';
      if (!confirm(message)) return;
      setUnitsAlert('');
      try {
        await api('/api/control-center/units/' + encodeURIComponent(unit.id) + '/' + action, { method: 'PATCH' });
        await loadUnits();
      } catch (error) {
        setUnitsAlert(error.message || 'Khong cap nhat duoc trang thai don vi');
      }
    }

    function formatBytes(value) {
      const bytes = Number(value || 0);
      if (bytes <= 0) return '0 B';
      const units = ['B', 'KB', 'MB', 'GB', 'TB'];
      const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
      return `${(bytes / (1024 ** index)).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
    }

    Promise.all([loadDashboard(), loadUnits(), loadAccounts(), loadMonitoring()]).catch((error) => {
      document.getElementById('healthBadge').textContent = 'DEGRADED';
      document.getElementById('healthBadge').className = 'cc-badge warn';
    });

    document.getElementById('addUnitButton').addEventListener('click', () => openUnitModal());
    document.getElementById('refreshUnitsButton').addEventListener('click', () => loadUnits().catch((error) => setUnitsAlert(error.message)));
    document.getElementById('unitStatusFilter').addEventListener('change', () => loadUnits().catch((error) => setUnitsAlert(error.message)));
    document.getElementById('unitSearch').addEventListener('input', (() => {
      let timer = null;
      return () => {
        clearTimeout(timer);
        timer = setTimeout(() => loadUnits().catch((error) => setUnitsAlert(error.message)), 250);
      };
    })());
    document.getElementById('unitForm').addEventListener('submit', saveUnit);
    document.getElementById('closeUnitModalButton').addEventListener('click', closeUnitModal);
    document.getElementById('cancelUnitButton').addEventListener('click', closeUnitModal);
    document.getElementById('unitModal').addEventListener('click', (event) => {
      if (event.target.id === 'unitModal') closeUnitModal();
    });
  </script>
</body>
</html>
