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
    }

    .cc-panel-title {
      margin: 0;
      font-size: 16px;
      font-weight: 750;
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
      .monitor-grid {
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
              <span class="cc-meta">Read-only Phase 2</span>
            </div>
            <div class="cc-table-wrap">
              <table class="cc-table">
                <thead>
                  <tr>
                    <th>Ten don vi</th>
                    <th>Domain</th>
                    <th>Trang thai</th>
                    <th>Nguoi quan ly</th>
                    <th>Phien ban</th>
                    <th>Health</th>
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

    async function api(path) {
      const response = await fetch(path, { headers: { Accept: 'application/json' }, cache: 'no-store' });
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
      const data = await api('/api/control-center/units');
      const rows = (data.items || []).map((unit) => {
        const tr = document.createElement('tr');
        const cells = [unit.name, unit.domain || '-', unit.status, unit.manager, unit.version || '-', unit.healthStatus];
        cells.forEach((cell, index) => {
          const td = document.createElement('td');
          if (index === 2 || index === 5) td.appendChild(badge(cell));
          else td.textContent = cell;
          tr.appendChild(td);
        });
        return tr;
      });
      document.getElementById('unitsBody').replaceChildren(...(rows.length ? rows : [emptyRow(6)]));
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
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = colspan;
      td.textContent = 'Chua co du lieu hien thi';
      tr.appendChild(td);
      return tr;
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
  </script>
</body>
</html>
