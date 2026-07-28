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

    .cc-header-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .cc-global-search {
      width: min(360px, 38vw);
      min-width: 220px;
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

    .quick-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .operation-list {
      display: grid;
      gap: 10px;
    }

    .operation-item {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 12px;
      align-items: center;
      padding: 12px;
      border: 1px solid var(--cc-border);
      background: #fff;
    }

    .operation-title {
      font-weight: 800;
      margin-bottom: 4px;
    }

    .operation-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      justify-content: flex-end;
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

    .cc-login-screen {
      position: fixed;
      inset: 0;
      z-index: 100;
      display: grid;
      place-items: center;
      padding: 20px;
      background: #f4f6f9;
    }

    .cc-login-screen.hidden {
      display: none;
    }

    .cc-login-card {
      width: min(420px, 100%);
      background: #ffffff;
      border: 1px solid var(--cc-line);
      border-radius: 8px;
      padding: 22px;
      box-shadow: 0 18px 50px rgba(15, 23, 42, 0.12);
      display: grid;
      gap: 14px;
    }

    .cc-login-card h1 {
      margin: 0;
      font-size: 22px;
      line-height: 1.25;
    }

    .cc-login-card p {
      margin: 0;
      color: var(--cc-muted);
      font-size: 14px;
    }

    .cc-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }

    .permission-grid {
      display: grid;
      grid-template-columns: 260px minmax(0, 1fr);
      gap: 16px;
      align-items: start;
    }

    .permission-groups {
      display: grid;
      gap: 8px;
      padding: 12px;
    }

    .permission-groups button {
      border: 1px solid var(--cc-line);
      border-radius: 8px;
      background: #ffffff;
      min-height: 40px;
      padding: 8px 10px;
      text-align: left;
      font-weight: 700;
    }

    .permission-groups button.active {
      border-color: var(--cc-brand);
      color: var(--cc-brand);
      background: #ecfdf3;
    }

    .permission-cell {
      text-align: center;
    }

    .permission-toggle {
      width: 20px;
      height: 20px;
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
      .permission-grid,
      .cc-toolbar,
      .cc-form,
      .operation-item,
      .cc-global-search {
        grid-template-columns: 1fr;
        width: 100%;
        min-width: 0;
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
  <div class="cc-login-screen" id="loginScreen">
    <form class="cc-login-card" id="loginForm" novalidate>
      <div class="cc-brand">
        <div class="cc-brand-mark">CC</div>
        <div>
          <h1>Community Control Center</h1>
          <p>Dang nhap de dieu hanh Hong Phong Community Platform.</p>
        </div>
      </div>
      <div class="cc-field">
        <label for="loginUsername">Tai khoan hoac email</label>
        <input class="cc-input" id="loginUsername" name="username" autocomplete="username" required>
      </div>
      <div class="cc-field">
        <label for="loginPassword">Mat khau</label>
        <input class="cc-input" id="loginPassword" name="password" type="password" autocomplete="current-password" required>
      </div>
      <div class="cc-form-error active" id="loginError"></div>
      <button class="cc-btn primary" type="submit" id="loginButton"><i class="fa-solid fa-right-to-bracket"></i>Dang nhap</button>
    </form>
  </div>
  <div class="control-center">
    <aside class="cc-sidebar">
      <div class="cc-brand">
        <div class="cc-brand-mark">CC</div>
        <div>
          <div class="cc-brand-title">HONG PHONG COMMUNITY PLATFORM</div>
          <div class="cc-brand-subtitle">Community Control Center</div>
        </div>
      </div>
      <nav class="cc-nav" aria-label="Control Center">
        <button class="active" type="button" data-section="dashboard"><i class="fa-solid fa-chart-line"></i>Tong quan</button>
        <button type="button" data-section="units"><i class="fa-solid fa-sitemap"></i>Don vi</button>
        <button type="button" data-section="accounts"><i class="fa-solid fa-users-gear"></i>Nguoi dung</button>
        <button type="button" data-section="permissions"><i class="fa-solid fa-shield-halved"></i>Phan quyen</button>
        <button type="button" data-section="dashboard"><i class="fa-solid fa-gauge-high"></i>Dashboard</button>
        <button type="button" data-section="monitoring"><i class="fa-solid fa-heart-pulse"></i>Monitoring</button>
        <button type="button" data-section="audit"><i class="fa-solid fa-clock-rotate-left"></i>Audit</button>
        <button type="button" data-section="configuration"><i class="fa-solid fa-gear"></i>Cau hinh</button>
        <button type="button" data-section="notifications"><i class="fa-solid fa-bell"></i>Thong bao</button>
        <button type="button" data-section="ai"><i class="fa-solid fa-wand-magic-sparkles"></i>AI</button>
      </nav>
    </aside>

    <main class="cc-main">
      <header class="cc-header">
        <div>
          <h1 class="cc-title" id="sectionTitle">Tong quan</h1>
          <div class="cc-meta" id="portalMeta">HONG PHONG COMMUNITY PLATFORM - Community Control Center</div>
        </div>
        <div class="cc-header-actions">
          <input class="cc-input cc-global-search" type="search" id="globalSearch" placeholder="Tim nhanh: dashboard, don vi, tai khoan, phan quyen">
          <span class="cc-meta" id="currentUserLabel">Chua dang nhap</span>
          <span class="cc-badge" id="healthBadge">Dang kiem tra</span>
          <button class="cc-btn" type="button" id="logoutButton"><i class="fa-solid fa-right-from-bracket"></i>Dang xuat</button>
        </div>
      </header>

      <div class="cc-content">
        <section class="cc-section active" id="dashboardSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <div>
                <h2 class="cc-panel-title">Cong viec can xu ly hom nay</h2>
                <div class="cc-meta">Uu tien cac su co can nguoi quan tri xu ly ngay.</div>
              </div>
              <button class="cc-btn" type="button" id="refreshOperationsButton"><i class="fa-solid fa-rotate"></i>Kiem tra lai</button>
            </div>
            <div class="operation-list" id="operationsList"></div>
          </div>
          <div class="metric-grid" id="metricGrid"></div>
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Hoat dong gan day</h2>
              <button class="cc-btn" type="button" data-go-section="audit"><i class="fa-solid fa-clock-rotate-left"></i>Xem Audit</button>
            </div>
            <div class="operation-list" id="recentActivityList"></div>
          </div>
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Thao tac nhanh</h2>
              <span class="cc-meta">Mo nhanh cac nang luc dieu hanh dang san sang</span>
            </div>
            <div class="cc-state quick-actions">
              <button class="cc-btn" type="button" data-go-section="units"><i class="fa-solid fa-sitemap"></i>Quan ly don vi</button>
              <button class="cc-btn" type="button" data-go-section="accounts"><i class="fa-solid fa-users-gear"></i>Quan ly tai khoan</button>
              <button class="cc-btn" type="button" data-go-section="permissions"><i class="fa-solid fa-shield-halved"></i>Phan quyen</button>
            </div>
          </div>
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
                    <th>Database</th>
                    <th>Trang thai</th>
                    <th>Nguoi quan ly</th>
                    <th>Website</th>
                    <th>Database</th>
                    <th>Phien ban</th>
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
              <button class="cc-btn primary" type="button" id="addAccountButton"><i class="fa-solid fa-user-plus"></i>Them tai khoan</button>
            </div>
            <div class="cc-toolbar">
              <input class="cc-input" type="search" id="accountSearch" placeholder="Tim username, ho ten, email, vai tro, don vi">
              <select class="cc-select" id="accountRoleFilter" aria-label="Loc vai tro">
                <option value="">Tat ca vai tro</option>
                <option value="SYSTEM_ADMIN">Quan tri he thong</option>
                <option value="VILLAGE_ADMIN">Quan tri thon</option>
                <option value="STAFF">Can bo nhap lieu</option>
                <option value="VIEWER">Chi xem</option>
              </select>
              <select class="cc-select" id="accountStatusFilter" aria-label="Loc trang thai">
                <option value="">Tat ca trang thai</option>
                <option value="ACTIVE">Dang su dung</option>
                <option value="INACTIVE">Ngung su dung</option>
              </select>
              <button class="cc-btn" type="button" id="refreshAccountsButton"><i class="fa-solid fa-rotate"></i>Tai lai</button>
            </div>
            <div class="cc-alert" id="accountsAlert"></div>
            <div class="cc-table-wrap">
              <table class="cc-table">
                <thead>
                  <tr>
                    <th>Ten</th>
                    <th>Vai tro</th>
                    <th>Don vi</th>
                    <th>Trang thai</th>
                    <th>Dang nhap cuoi</th>
                    <th>IP cuoi</th>
                    <th>Thiet bi cuoi</th>
                    <th>Thoi gian tao</th>
                    <th>Nguoi tao</th>
                    <th>Thao tac</th>
                  </tr>
                </thead>
                <tbody id="accountsBody"></tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="cc-section" id="permissionsSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <div>
                <h2 class="cc-panel-title">Phan quyen Community Control Center</h2>
                <div class="cc-meta">Kiem soat menu, module, button, action va API trong Control Center.</div>
              </div>
              <button class="cc-btn primary" type="button" id="savePermissionsButton" disabled><i class="fa-solid fa-floppy-disk"></i>Luu thay doi</button>
            </div>
            <div class="cc-toolbar">
              <input class="cc-input" type="search" id="permissionSearch" placeholder="Tim permission, module, action">
              <select class="cc-select" id="permissionRoleFilter" aria-label="Loc vai tro">
                <option value="">Tat ca vai tro</option>
              </select>
              <button class="cc-btn" type="button" id="refreshPermissionsButton"><i class="fa-solid fa-rotate"></i>Tai lai</button>
            </div>
            <div class="cc-alert" id="permissionsAlert"></div>
            <div class="permission-grid">
              <div class="cc-panel">
                <div class="permission-groups" id="permissionGroups"></div>
              </div>
              <div class="cc-table-wrap">
                <table class="cc-table">
                  <thead id="permissionsHead"></thead>
                  <tbody id="permissionsBody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </section>

        <section class="cc-section" id="monitoringSection">
          <div class="monitor-grid" id="monitorGrid"></div>
        </section>

        <section class="cc-section" id="auditSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Audit</h2>
              <button class="cc-btn" type="button" id="refreshAuditButton"><i class="fa-solid fa-rotate"></i>Tai lai</button>
            </div>
            <div class="cc-toolbar">
              <select class="cc-select" id="auditTenantFilter" aria-label="Loc Tenant">
                <option value="">Tat ca Tenant</option>
              </select>
              <select class="cc-select" id="auditLevelFilter" aria-label="Loc muc do">
                <option value="">Tat ca muc do</option>
                <option value="INFO">INFO</option>
                <option value="WARN">WARN</option>
                <option value="ERROR">ERROR</option>
              </select>
              <input class="cc-input" type="search" id="auditSearch" placeholder="Tim actor, hanh dong, tenant">
            </div>
            <div class="cc-table-wrap">
              <table class="cc-table">
                <thead>
                  <tr>
                    <th>Thoi gian</th>
                    <th>Tenant</th>
                    <th>Nguoi thuc hien</th>
                    <th>Hanh dong</th>
                    <th>Muc do</th>
                    <th>Ket qua</th>
                  </tr>
                </thead>
                <tbody id="auditBody"></tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="cc-section" id="configurationSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Cau hinh</h2>
              <span class="cc-badge warn">Dang phat trien</span>
            </div>
            <div class="cc-state">Se quan ly cau hinh chung cua Community Control Center va nen tang.</div>
          </div>
        </section>

        <section class="cc-section" id="notificationsSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Thong bao</h2>
              <span class="cc-badge warn">Dang phat trien</span>
            </div>
            <div class="cc-state">Se dieu phoi thong tin, thong bao noi bo va canh bao van hanh.</div>
          </div>
        </section>

        <section class="cc-section" id="aiSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">AI</h2>
              <span class="cc-badge warn">Dang phat trien</span>
            </div>
            <div class="cc-state">Se ho tro tim kiem, tong hop va goi y thao tac trong pham vi duoc cap quyen.</div>
          </div>
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
          <div class="cc-field">
            <label for="unitDatabaseHost">Database Host</label>
            <input class="cc-input" id="unitDatabaseHost" name="database_host" maxlength="190" placeholder="localhost" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitDatabaseName">Database</label>
            <input class="cc-input" id="unitDatabaseName" name="database_name" maxlength="190" placeholder="nhhon5mp_thon09" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitDatabaseCharset">Database Charset</label>
            <input class="cc-input" id="unitDatabaseCharset" name="database_charset" maxlength="50" placeholder="utf8mb4" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitAppVersion">Phien ban ung dung</label>
            <input class="cc-input" id="unitAppVersion" name="app_version" maxlength="50" placeholder="v2.0" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitBuildVersion">Build Version</label>
            <input class="cc-input" id="unitBuildVersion" name="build_version" maxlength="100" placeholder="20260727-gis-multi-area-1" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitSchemaVersion">Schema Version</label>
            <input class="cc-input" id="unitSchemaVersion" name="schema_version" maxlength="50" placeholder="20260729" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitManagerName">Nguoi quan ly</label>
            <input class="cc-input" id="unitManagerName" name="manager_name" maxlength="190" placeholder="Chua gan" autocomplete="off">
          </div>
          <div class="cc-field full">
            <label for="unitLogo">Logo URL</label>
            <input class="cc-input" id="unitLogo" name="logo" maxlength="500" placeholder="/assets/logo.png" autocomplete="off">
          </div>
          <div class="cc-field full">
            <label for="unitNotes">Ghi chu</label>
            <textarea class="cc-input" id="unitNotes" name="notes" maxlength="2000" rows="3" placeholder="Thong tin van hanh, lich backup, nguoi phu trach..."></textarea>
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

  <div class="cc-modal-backdrop" id="accountModal" role="dialog" aria-modal="true" aria-labelledby="accountModalTitle">
    <div class="cc-modal">
      <div class="cc-modal-header">
        <h2 class="cc-modal-title" id="accountModalTitle">Them tai khoan</h2>
        <button class="cc-btn" type="button" id="closeAccountModalButton" aria-label="Dong"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="accountForm" novalidate>
        <div class="cc-form">
          <input type="hidden" id="accountId">
          <div class="cc-field">
            <label for="accountDisplayName">Ho ten *</label>
            <input class="cc-input" id="accountDisplayName" name="display_name" required maxlength="190" autocomplete="name">
          </div>
          <div class="cc-field">
            <label for="accountEmail">Email *</label>
            <input class="cc-input" id="accountEmail" name="email" type="email" required maxlength="190" autocomplete="email">
          </div>
          <div class="cc-field">
            <label for="accountUsername">Username *</label>
            <input class="cc-input" id="accountUsername" name="username" required maxlength="60" pattern="[a-z0-9._-]{3,60}" autocomplete="username">
          </div>
          <div class="cc-field">
            <label for="accountRole">Vai tro *</label>
            <select class="cc-select" id="accountRole" name="role" required>
              <option value="VILLAGE_ADMIN">Quan tri thon</option>
              <option value="STAFF">Can bo nhap lieu</option>
              <option value="VIEWER">Chi xem</option>
              <option value="SYSTEM_ADMIN">Quan tri he thong</option>
              <option value="COMMUNE_ADMIN" disabled>Quan tri xa (sau)</option>
            </select>
          </div>
          <div class="cc-field">
            <label for="accountUnit">Don vi *</label>
            <select class="cc-select" id="accountUnit" name="unit_id" required></select>
          </div>
          <div class="cc-field">
            <label for="accountStatus">Trang thai</label>
            <select class="cc-select" id="accountStatus" name="status">
              <option value="ACTIVE">Dang su dung</option>
              <option value="INACTIVE">Ngung su dung</option>
            </select>
          </div>
          <div class="cc-field account-password-field">
            <label for="accountPassword">Mat khau *</label>
            <input class="cc-input" id="accountPassword" name="password" type="password" minlength="8" autocomplete="new-password">
          </div>
          <div class="cc-field">
            <label for="accountPhone">Dien thoai</label>
            <input class="cc-input" id="accountPhone" name="phone" maxlength="50" autocomplete="tel">
          </div>
          <div class="cc-field full">
            <label for="accountPosition">Chuc vu</label>
            <input class="cc-input" id="accountPosition" name="position" maxlength="190" autocomplete="organization-title">
          </div>
        </div>
        <div class="cc-form-error" id="accountFormError"></div>
        <div class="cc-modal-footer">
          <button class="cc-btn" type="button" id="cancelAccountButton">Huy</button>
          <button class="cc-btn primary" type="submit" id="saveAccountButton"><i class="fa-solid fa-floppy-disk"></i>Luu</button>
        </div>
      </form>
    </div>
  </div>

  <div class="cc-modal-backdrop" id="passwordModal" role="dialog" aria-modal="true" aria-labelledby="passwordModalTitle">
    <div class="cc-modal">
      <div class="cc-modal-header">
        <h2 class="cc-modal-title" id="passwordModalTitle">Reset mat khau</h2>
        <button class="cc-btn" type="button" id="closePasswordModalButton" aria-label="Dong"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="passwordForm" novalidate>
        <div class="cc-form">
          <input type="hidden" id="passwordAccountId">
          <div class="cc-field full">
            <label for="newPassword">Mat khau moi *</label>
            <input class="cc-input" id="newPassword" name="password" type="password" minlength="8" required autocomplete="new-password">
          </div>
        </div>
        <div class="cc-form-error" id="passwordFormError"></div>
        <div class="cc-modal-footer">
          <button class="cc-btn" type="button" id="cancelPasswordButton">Huy</button>
          <button class="cc-btn primary" type="submit" id="savePasswordButton"><i class="fa-solid fa-key"></i>Cap nhat</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const sections = {
      dashboard: document.getElementById('dashboardSection'),
      units: document.getElementById('unitsSection'),
      accounts: document.getElementById('accountsSection'),
      permissions: document.getElementById('permissionsSection'),
      monitoring: document.getElementById('monitoringSection'),
      audit: document.getElementById('auditSection'),
      configuration: document.getElementById('configurationSection'),
      notifications: document.getElementById('notificationsSection'),
      ai: document.getElementById('aiSection')
    };
    const sectionTitles = {
      dashboard: 'Tong quan',
      units: 'Don vi',
      accounts: 'Nguoi dung',
      permissions: 'Phan quyen',
      monitoring: 'Monitoring',
      audit: 'Audit',
      configuration: 'Cau hinh',
      notifications: 'Thong bao',
      ai: 'AI'
    };

    document.querySelectorAll('.cc-nav button').forEach((button) => {
      button.addEventListener('click', () => activateSection(button.dataset.section));
    });

    function activateSection(section) {
      if (!sections[section]) return;
      document.querySelectorAll('.cc-nav button').forEach((item) => item.classList.toggle('active', item.dataset.section === section));
      Object.values(sections).forEach((item) => item.classList.remove('active'));
      sections[section].classList.add('active');
      document.getElementById('sectionTitle').textContent = sectionTitles[section];
    }

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
    const accountState = {
      items: [],
      editing: null,
      passwordTarget: null
    };
    const permissionState = {
      roles: [],
      groups: [],
      matrix: [],
      pending: new Map(),
      activeGroup: ''
    };
    const auditState = { items: [] };
    const roleLabels = {
      SYSTEM_ADMIN: 'Quan tri he thong',
      VILLAGE_ADMIN: 'Quan tri thon',
      STAFF: 'Can bo nhap lieu',
      VIEWER: 'Chi xem',
      COMMUNE_ADMIN: 'Quan tri xa'
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
      if (value === 'LOCKED' || value === 'DEGRADED' || value === 'UNKNOWN' || value === 'NOT_APPLICABLE' || value === 'MEDIUM') span.classList.add('warn');
      if (value === 'ERROR' || value === 'DISCONNECTED' || value === 'OFFLINE' || value === 'INVALID' || value === 'HIGH') span.classList.add('danger');
      span.textContent = statusLabel(value);
      return span;
    }

    function statusLabel(value) {
      const labels = {
        UNKNOWN: 'Chua kiem tra',
        ONLINE: 'Online',
        OFFLINE: 'Offline',
        CONNECTED: 'Database OK',
        DISCONNECTED: 'Database loi',
        LOCKED: 'Da khoa',
        VALID: 'SSL OK',
        INVALID: 'SSL loi',
        NOT_APPLICABLE: 'Khong ap dung',
        ACTIVE: 'Dang hoat dong',
        INACTIVE: 'Da khoa',
        HIGH: 'Cao',
        MEDIUM: 'Trung binh',
        LOW: 'Thap'
      };
      return labels[value] || value || 'Chua kiem tra';
    }

    function authHeaders(method) {
      const headers = { Accept: 'application/json' };
      const token = (window.App && window.App.token) || localStorage.getItem(storageKey('token')) || localStorage.getItem('token') || '';
      const csrf = (window.App && window.App.csrfToken) || localStorage.getItem(storageKey('csrf')) || localStorage.getItem('csrf') || '';
      if (token) headers.Authorization = 'Bearer ' + token;
      if (method !== 'GET' && method !== 'HEAD' && csrf) headers['X-CSRF-Token'] = csrf;
      return headers;
    }

    function setSession(result) {
      window.App = window.App || {};
      window.App.token = result?.token || '';
      window.App.csrfToken = result?.csrfToken || '';
      window.App.user = result?.user || null;
      if (window.App.token) {
        localStorage.setItem(storageKey('token'), window.App.token);
        localStorage.setItem(storageKey('csrf'), window.App.csrfToken || '');
      } else {
        localStorage.removeItem(storageKey('token'));
        localStorage.removeItem(storageKey('csrf'));
      }
      renderAuthState();
    }

    function renderAuthState() {
      const user = window.App?.user || null;
      const loggedIn = Boolean(window.App?.token && user);
      document.getElementById('loginScreen').classList.toggle('hidden', loggedIn);
      document.getElementById('currentUserLabel').textContent = loggedIn ? `${user.displayName || user.email} - ${roleLabels[user.role] || user.role}` : 'Chua dang nhap';
    }

    async function restoreSession() {
      const token = localStorage.getItem(storageKey('token')) || '';
      const csrf = localStorage.getItem(storageKey('csrf')) || '';
      if (!token) {
        renderAuthState();
        return false;
      }
      window.App = window.App || {};
      window.App.token = token;
      window.App.csrfToken = csrf;
      try {
        const user = await api('/api/control-center/me');
        window.App.user = user;
        renderAuthState();
        return true;
      } catch (error) {
        setSession(null);
        return false;
      }
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

    async function login(event) {
      event.preventDefault();
      const button = document.getElementById('loginButton');
      const error = document.getElementById('loginError');
      const username = formValue('loginUsername');
      const password = formValue('loginPassword');
      if (!username || !password) {
        error.textContent = 'Vui long nhap tai khoan va mat khau';
        return;
      }
      button.disabled = true;
      error.textContent = '';
      try {
        const result = await api('/api/control-center/login', { method: 'POST', body: { username, password } });
        setSession(result);
        await loadControlCenter();
      } catch (loginError) {
        error.textContent = loginError.message || 'Dang nhap khong thanh cong';
      } finally {
        button.disabled = false;
      }
    }

    async function logout() {
      try {
        await api('/api/control-center/logout', { method: 'POST' });
      } catch (error) {
        // Local logout must still clear stale credentials.
      }
      setSession(null);
      document.getElementById('loginPassword').value = '';
      document.getElementById('loginUsername').focus();
    }

    async function loadDashboard() {
      const data = await api('/api/control-center/dashboard');
      const metrics = [
        ['Tong so don vi', nf.format(data.totalUnits), 'Don vi dang quan ly'],
        ['Tenant dang hoat dong', nf.format(data.activeUnits || 0), 'Theo Tenant Registry'],
        ['Website Online', nf.format(data.websiteOnlineUnits || 0), 'Tenant co website dang truy cap duoc'],
        ['Database OK', nf.format(data.databaseConnectedUnits || 0), 'Tenant ket noi database thanh cong'],
        ['Tenant loi website', nf.format(data.websiteOfflineUnits || 0), 'Can kiem tra domain/hosting'],
        ['Tenant loi database', nf.format(data.databaseDisconnectedUnits || 0), 'Can kiem tra cau hinh database'],
        ['Tong ho', nf.format(data.totalHouseholds), 'Tong hop toan he thong'],
        ['Tong nguoi dung', nf.format(accountState.items.length), 'Tai khoan trong Community Control Center'],
        ['Tong tre em', nf.format(data.totalChildren), 'So lieu tong hop'],
        ['Tong nguoi cao tuoi', nf.format(data.totalElderly), 'Theo cau hinh chinh sach hien co'],
        ['Tong lao dong', nf.format(data.totalWorkers), 'Theo truong lao dong hien co'],
        ['Tong Dang vien', nf.format(data.totalPartyMembers), 'So lieu tong hop'],
        ['Tong ty le BHYT', percent(data.healthInsuranceRate), 'Tren nhan khau con song']
      ];
      const grid = document.getElementById('metricGrid');
      grid.replaceChildren(...metrics.map((item) => metric(item[0], item[1], item[2])));
      renderOperations(data.operations || []);
      renderRecentActivity(data.recentActivity || []);
    }

    function renderOperations(items) {
      const holder = document.getElementById('operationsList');
      if (!items.length) {
        holder.replaceChildren(stateMessage('Hom nay chua co viec can xu ly ngay.'));
        return;
      }
      holder.replaceChildren(...items.map((item) => {
        const row = document.createElement('div');
        row.className = 'operation-item';
        const main = document.createElement('div');
        const title = document.createElement('div');
        title.className = 'operation-title';
        title.textContent = (item.message || 'Can xu ly') + ' - ' + (item.tenant?.name || item.tenant?.code || 'Tenant');
        const meta = document.createElement('div');
        meta.className = 'cc-meta';
        meta.textContent = 'Muc do: ' + statusLabel(item.severity) + ' | Nguoi phu trach: ' + (item.tenant?.manager || 'Chua gan');
        main.append(title, meta);
        const actions = document.createElement('div');
        actions.className = 'operation-actions';
        const unit = operationUnit(item);
        if (item.primaryAction === 'check_website') {
          const check = actionButton('Kiem tra Website', 'fa-globe');
          check.addEventListener('click', () => checkUnitWebsite(unit));
          actions.appendChild(check);
        } else if (item.primaryAction === 'check_database') {
          const check = actionButton('Kiem tra Database', 'fa-database');
          check.addEventListener('click', () => checkUnitConnection(unit));
          actions.appendChild(check);
        }
        const view = actionButton('Xem Tenant', 'fa-sitemap');
        view.addEventListener('click', () => {
          activateSection('units');
          document.getElementById('unitSearch').value = item.tenant?.code || item.tenant?.name || '';
          loadUnits().catch((error) => setUnitsAlert(error.message));
        });
        actions.appendChild(view);
        if (item.tenant?.domain) {
          const portal = actionButton('Mo Portal', 'fa-arrow-up-right-from-square');
          portal.addEventListener('click', () => openTenantPortal(unit));
          actions.appendChild(portal);
        }
        row.append(main, actions);
        return row;
      }));
    }

    function operationUnit(item) {
      return {
        id: item.tenant?.id,
        code: item.tenant?.code,
        name: item.tenant?.name,
        domain: item.tenant?.domain
      };
    }

    function renderRecentActivity(items) {
      const holder = document.getElementById('recentActivityList');
      if (!items.length) {
        holder.replaceChildren(stateMessage('Chua co hoat dong quan tri gan day.'));
        return;
      }
      holder.replaceChildren(...items.map((item) => {
        const row = document.createElement('div');
        row.className = 'operation-item';
        const main = document.createElement('div');
        const title = document.createElement('div');
        title.className = 'operation-title';
        title.textContent = item.message || item.action || 'Hoat dong';
        const meta = document.createElement('div');
        meta.className = 'cc-meta';
        meta.textContent = `${item.createdAt || '-'} | ${item.tenantName || 'He thong'} | ${item.actor || '-'}`;
        main.append(title, meta);
        const actions = document.createElement('div');
        actions.className = 'operation-actions';
        actions.appendChild(badge(item.level || 'INFO'));
        row.append(main, actions);
        return row;
      }));
    }

    async function loadUnits() {
      const body = document.getElementById('unitsBody');
      body.replaceChildren(stateRow(10, 'Dang tai du lieu...'));
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
        const cells = [unit.code || '-', unit.name, unit.domain || '-', unit.databaseName || '-', unit.status, unit.manager, unit.websiteStatus || 'UNKNOWN', unit.databaseStatus || unit.healthStatus || 'UNKNOWN', unit.version || '-'];
        cells.forEach((cell, index) => {
          const td = document.createElement('td');
          if (index === 4 || index === 6 || index === 7) td.appendChild(badge(cell));
          else td.textContent = cell;
          tr.appendChild(td);
        });
        const actions = document.createElement('td');
        actions.className = 'cc-row-actions';
        if (unit.domain) {
          const portal = actionButton('Mo Portal', 'fa-arrow-up-right-from-square');
          portal.addEventListener('click', () => openTenantPortal(unit));
          actions.appendChild(portal);
        }
        const checkWebsite = actionButton('Website', 'fa-globe');
        checkWebsite.addEventListener('click', () => checkUnitWebsite(unit));
        actions.appendChild(checkWebsite);
        const checkDatabase = actionButton('Database', 'fa-database');
        checkDatabase.addEventListener('click', () => checkUnitConnection(unit));
        actions.appendChild(checkDatabase);
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
      body.replaceChildren(...(rows.length ? rows : [emptyRow(10)]));
    }

    async function loadAccounts() {
      const body = document.getElementById('accountsBody');
      body.replaceChildren(stateRow(10, 'Dang tai du lieu...'));
      setAccountsAlert('');
      const params = new URLSearchParams();
      const search = document.getElementById('accountSearch').value.trim();
      const role = document.getElementById('accountRoleFilter').value;
      const status = document.getElementById('accountStatusFilter').value;
      if (search) params.set('search', search);
      if (role) params.set('role', role);
      if (status) params.set('status', status);
      try {
        const data = await api('/api/control-center/users' + (params.toString() ? '?' + params.toString() : ''));
        accountState.items = data.items || [];
        const rows = accountState.items.map((account) => {
          const tr = document.createElement('tr');
          const name = document.createElement('td');
          const primary = document.createElement('div');
          primary.textContent = account.displayName || account.username || account.email;
          const secondary = document.createElement('div');
          secondary.className = 'cc-meta';
          secondary.textContent = account.email || account.username || '';
          name.append(primary, secondary);
          tr.appendChild(name);

          [roleLabels[account.role] || account.role, account.unitName || '-', account.status, account.lastLoginLabel || account.lastLoginAt || 'Chua dang nhap', account.lastIp || '-', account.lastDevice || '-', account.createdAt || '-', account.createdBy || '-'].forEach((cell, index) => {
            const td = document.createElement('td');
            if (index === 2) td.appendChild(badge(cell));
            else td.textContent = cell;
            tr.appendChild(td);
          });

          const actions = document.createElement('td');
          actions.className = 'cc-row-actions';
          const edit = actionButton('Sua', 'fa-user-pen');
          edit.addEventListener('click', () => openAccountModal(account));
          actions.appendChild(edit);
          const password = actionButton('Mat khau', 'fa-key');
          password.addEventListener('click', () => openPasswordModal(account));
          actions.appendChild(password);
          if (account.status === 'ACTIVE') {
            const deactivate = actionButton('Ngung', 'fa-user-slash', 'danger');
            deactivate.addEventListener('click', () => changeAccountStatus(account, 'deactivate'));
            actions.appendChild(deactivate);
          } else {
            const activate = actionButton('Kich hoat', 'fa-user-check');
            activate.addEventListener('click', () => changeAccountStatus(account, 'activate'));
            actions.appendChild(activate);
          }
          tr.appendChild(actions);
          return tr;
        });
        body.replaceChildren(...(rows.length ? rows : [emptyRow(10)]));
      } catch (error) {
        body.replaceChildren(emptyRow(10));
        setAccountsAlert(error.message || 'Khong tai duoc danh sach tai khoan');
      }
    }

    async function loadPermissions() {
      const head = document.getElementById('permissionsHead');
      const body = document.getElementById('permissionsBody');
      body.replaceChildren(stateRow(2, 'Dang tai phan quyen...'));
      try {
        const data = await api('/api/control-center/permissions');
        permissionState.roles = data.roles || [];
        permissionState.groups = data.groups || [];
        permissionState.matrix = data.matrix || [];
        permissionState.pending.clear();
        if (!permissionState.activeGroup && permissionState.groups.length) {
          permissionState.activeGroup = permissionState.groups[0].id;
        }
        renderPermissionRoleFilter();
        renderPermissionGroups();
        renderPermissions();
        setPermissionsAlert('');
      } catch (error) {
        head.replaceChildren();
        body.replaceChildren(stateRow(2, 'Khong tai duoc phan quyen'));
        setPermissionsAlert(error.message || 'Khong tai duoc phan quyen');
      }
    }

    function renderPermissionRoleFilter() {
      const select = document.getElementById('permissionRoleFilter');
      const current = select.value;
      const options = [new Option('Tat ca vai tro', '')].concat(permissionState.roles.map((role) => new Option(role.label || role.role, role.role)));
      select.replaceChildren(...options);
      select.value = current;
    }

    function renderPermissionGroups() {
      const holder = document.getElementById('permissionGroups');
      const groups = permissionState.groups.map((group) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = group.name || group.id;
        button.classList.toggle('active', group.id === permissionState.activeGroup);
        button.addEventListener('click', () => {
          permissionState.activeGroup = group.id;
          renderPermissionGroups();
          renderPermissions();
        });
        return button;
      });
      holder.replaceChildren(...(groups.length ? groups : [stateMessage('Chua co permission')]));
    }

    function renderPermissions() {
      const head = document.getElementById('permissionsHead');
      const body = document.getElementById('permissionsBody');
      const roleFilter = document.getElementById('permissionRoleFilter').value;
      const search = document.getElementById('permissionSearch').value.trim().toLowerCase();
      const group = permissionState.groups.find((item) => item.id === permissionState.activeGroup) || permissionState.groups[0];
      const roles = permissionState.roles.filter((role) => !roleFilter || role.role === roleFilter);
      const permissions = (group?.permissions || []).filter((permission) => {
        const haystack = `${permission.key} ${permission.label} ${permission.action}`.toLowerCase();
        return !search || haystack.includes(search);
      });

      const headerRow = document.createElement('tr');
      ['Quyen'].concat(roles.map((role) => role.label || role.role)).forEach((label) => {
        const th = document.createElement('th');
        th.textContent = label;
        headerRow.appendChild(th);
      });
      head.replaceChildren(headerRow);

      const rows = permissions.map((permission) => {
        const tr = document.createElement('tr');
        const name = document.createElement('td');
        const primary = document.createElement('div');
        primary.textContent = permission.label || permission.key;
        const secondary = document.createElement('div');
        secondary.className = 'cc-meta';
        secondary.textContent = permission.key;
        name.append(primary, secondary);
        tr.appendChild(name);

        roles.forEach((role) => {
          const td = document.createElement('td');
          td.className = 'permission-cell';
          const item = matrixItem(role.role, permission.key);
          const checkbox = document.createElement('input');
          checkbox.type = 'checkbox';
          checkbox.className = 'permission-toggle';
          checkbox.checked = item.allowed;
          checkbox.disabled = item.locked;
          checkbox.title = item.locked ? 'Quyen cot loi khong the tat' : '';
          checkbox.addEventListener('change', () => {
            permissionState.pending.set(role.role + '|' + permission.key, {
              role: role.role,
              permission: permission.key,
              allowed: checkbox.checked
            });
            document.getElementById('savePermissionsButton').disabled = permissionState.pending.size === 0;
          });
          td.appendChild(checkbox);
          tr.appendChild(td);
        });
        return tr;
      });
      body.replaceChildren(...(rows.length ? rows : [emptyRow(Math.max(1, roles.length + 1))]));
    }

    function matrixItem(role, permission) {
      const pending = permissionState.pending.get(role + '|' + permission);
      if (pending) return { allowed: pending.allowed, locked: false };
      return permissionState.matrix.find((item) => item.role === role && item.permission === permission) || { allowed: false, locked: false };
    }

    async function savePermissions() {
      const button = document.getElementById('savePermissionsButton');
      if (permissionState.pending.size === 0) return;
      button.disabled = true;
      setPermissionsAlert('');
      try {
        await api('/api/control-center/permissions', { method: 'PUT', body: { items: Array.from(permissionState.pending.values()) } });
        await loadPermissions();
      } catch (error) {
        setPermissionsAlert(error.message || 'Khong luu duoc phan quyen');
      } finally {
        button.disabled = permissionState.pending.size === 0;
      }
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
      const tenantPanel = document.createElement('div');
      tenantPanel.className = 'cc-panel full';
      const header = document.createElement('div');
      header.className = 'cc-panel-header';
      const title = document.createElement('h2');
      title.className = 'cc-panel-title';
      title.textContent = 'Trang thai Tenant';
      header.appendChild(title);
      const tableWrap = document.createElement('div');
      tableWrap.className = 'cc-table-wrap';
      const table = document.createElement('table');
      table.className = 'cc-table';
      const head = document.createElement('thead');
      const headRow = document.createElement('tr');
      ['Tenant', 'Domain', 'Website', 'Database', 'SSL', 'Phien ban', 'Lan kiem tra', 'Loi gan nhat'].forEach((label) => {
        const th = document.createElement('th');
        th.textContent = label;
        headRow.appendChild(th);
      });
      head.appendChild(headRow);
      const body = document.createElement('tbody');
      (data.tenants || []).forEach((tenant) => {
        const tr = document.createElement('tr');
        const values = [tenant.name || tenant.code, tenant.domain || '-', tenant.websiteStatus || 'UNKNOWN', tenant.databaseStatus || 'UNKNOWN', tenant.sslStatus || 'UNKNOWN', tenant.version || '-', tenant.lastCheckedAt || '-', tenant.lastError || '-'];
        values.forEach((value, index) => {
          const td = document.createElement('td');
          if (index >= 2 && index <= 4) td.appendChild(badge(value));
          else td.textContent = value;
          tr.appendChild(td);
        });
        body.appendChild(tr);
      });
      if (!(data.tenants || []).length) body.appendChild(emptyRow(8));
      table.append(head, body);
      tableWrap.appendChild(table);
      tenantPanel.append(header, tableWrap);
      document.getElementById('monitorGrid').replaceChildren(...items.map(([label, value]) => metric(label, value || '-', '')), tenantPanel);
    }

    async function loadAudit() {
      const body = document.getElementById('auditBody');
      body.replaceChildren(stateRow(6, 'Dang tai audit...'));
      const params = new URLSearchParams();
      const tenant = document.getElementById('auditTenantFilter').value;
      const level = document.getElementById('auditLevelFilter').value;
      const search = document.getElementById('auditSearch').value.trim();
      if (tenant) params.set('village_id', tenant);
      if (level) params.set('level', level);
      if (search) params.set('search', search);
      const data = await api('/api/control-center/audit' + (params.toString() ? '?' + params.toString() : ''));
      auditState.items = data.items || [];
      const rows = auditState.items.map((item) => {
        const tr = document.createElement('tr');
        [item.createdAt || '-', item.tenantName || '-', item.actor || '-', item.action || '-', item.level || 'INFO', item.message || '-'].forEach((value, index) => {
          const td = document.createElement('td');
          if (index === 4) td.appendChild(badge(value));
          else td.textContent = value;
          tr.appendChild(td);
        });
        return tr;
      });
      body.replaceChildren(...(rows.length ? rows : [emptyRow(6)]));
    }

    function renderAuditTenantFilter() {
      const select = document.getElementById('auditTenantFilter');
      const current = select.value;
      const options = [new Option('Tat ca Tenant', '')].concat((unitState.items || []).map((unit) => new Option(unit.name || unit.code, unit.id)));
      select.replaceChildren(...options);
      select.value = current;
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

    function stateMessage(text) {
      const div = document.createElement('div');
      div.className = 'cc-state';
      div.textContent = text;
      return div;
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

    function setAccountsAlert(message) {
      const alert = document.getElementById('accountsAlert');
      alert.textContent = message || '';
      alert.classList.toggle('active', Boolean(message));
    }

    function setPermissionsAlert(message) {
      const alert = document.getElementById('permissionsAlert');
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
      document.getElementById('unitDatabaseHost').value = unit?.databaseHost || '';
      document.getElementById('unitDatabaseName').value = unit?.databaseName || '';
      document.getElementById('unitDatabaseCharset').value = unit?.databaseCharset || 'utf8mb4';
      document.getElementById('unitAppVersion').value = unit?.appVersion || unit?.version || '';
      document.getElementById('unitBuildVersion').value = unit?.buildVersion || '';
      document.getElementById('unitSchemaVersion').value = unit?.schemaVersion || '';
      document.getElementById('unitManagerName').value = unit?.manager === 'Chua gan' ? '' : (unit?.manager || '');
      document.getElementById('unitLogo').value = unit?.logo || '';
      document.getElementById('unitNotes').value = unit?.notes || '';
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
        database_host: formValue('unitDatabaseHost') || null,
        database_name: formValue('unitDatabaseName') || null,
        database_charset: formValue('unitDatabaseCharset') || null,
        app_version: formValue('unitAppVersion') || null,
        build_version: formValue('unitBuildVersion') || null,
        schema_version: formValue('unitSchemaVersion') || null,
        manager_name: formValue('unitManagerName') || null,
        notes: formValue('unitNotes') || null,
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
      if (payload.database_name && !/^[a-zA-Z0-9_]{1,190}$/.test(payload.database_name)) {
        return 'Ten database chi gom chu, so va dau gach duoi';
      }
      if (payload.database_charset && !/^[a-z0-9_]{1,50}$/.test(payload.database_charset)) {
        return 'Database charset khong hop le';
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

    async function checkUnitConnection(unit) {
      setUnitsAlert('Dang kiem tra database ' + (unit.name || unit.code || '') + '...');
      try {
        await api('/api/control-center/units/' + encodeURIComponent(unit.id) + '/check-connection', { method: 'PATCH' });
        setUnitsAlert('Da cap nhat trang thai database cho ' + (unit.name || unit.code || 'don vi'));
        await loadUnits();
      } catch (error) {
        setUnitsAlert(error.message || 'Khong kiem tra duoc database don vi');
      }
    }

    async function checkUnitWebsite(unit) {
      setUnitsAlert('Dang kiem tra website ' + (unit.name || unit.code || '') + '...');
      try {
        await api('/api/control-center/units/' + encodeURIComponent(unit.id) + '/check-website', { method: 'PATCH' });
        setUnitsAlert('Da cap nhat trang thai website cho ' + (unit.name || unit.code || 'don vi'));
        await loadUnits();
      } catch (error) {
        setUnitsAlert(error.message || 'Khong kiem tra duoc website don vi');
      }
    }

    async function openTenantPortal(unit) {
      const popup = window.open('', '_blank', 'noopener');
      try {
        const data = await api('/api/control-center/units/' + encodeURIComponent(unit.id) + '/open-portal', { method: 'POST' });
        if (popup) popup.location = data.url;
        else window.location.href = data.url;
      } catch (error) {
        if (popup) popup.close();
        setUnitsAlert(error.message || 'Khong mo duoc Tenant Portal');
      }
    }

    async function ensureAccountUnitOptions(selectedId = '') {
      let units = unitState.items || [];
      if (!units.length) {
        try {
          const data = await api('/api/control-center/units');
          units = data.items || [];
          unitState.items = units;
        } catch (error) {
          units = [];
        }
      }
      const select = document.getElementById('accountUnit');
      const options = units.map((unit) => {
        const option = document.createElement('option');
        option.value = unit.id;
        option.textContent = unit.name || unit.code || ('Don vi #' + unit.id);
        if (String(unit.id) === String(selectedId)) option.selected = true;
        return option;
      });
      if (!options.length) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Chua co don vi';
        options.push(option);
      }
      select.replaceChildren(...options);
    }

    async function openAccountModal(account = null) {
      accountState.editing = account;
      document.getElementById('accountModalTitle').textContent = account ? 'Sua tai khoan' : 'Them tai khoan';
      document.getElementById('accountId').value = account?.id || '';
      document.getElementById('accountDisplayName').value = account?.displayName || '';
      document.getElementById('accountEmail').value = account?.email || '';
      document.getElementById('accountUsername').value = account?.username || '';
      document.getElementById('accountRole').value = account?.role || 'VILLAGE_ADMIN';
      document.getElementById('accountStatus').value = account?.status || 'ACTIVE';
      document.getElementById('accountPhone').value = account?.phone || '';
      document.getElementById('accountPosition').value = account?.position || '';
      document.getElementById('accountPassword').value = '';
      document.querySelector('.account-password-field').style.display = account ? 'none' : 'grid';
      document.getElementById('accountPassword').required = !account;
      await ensureAccountUnitOptions(account?.unitId || '');
      setAccountFormError('');
      document.getElementById('accountModal').classList.add('active');
      document.getElementById('accountDisplayName').focus();
    }

    function closeAccountModal() {
      document.getElementById('accountModal').classList.remove('active');
      accountState.editing = null;
    }

    function accountPayload() {
      const payload = {
        username: formValue('accountUsername').toLowerCase(),
        email: formValue('accountEmail').toLowerCase(),
        display_name: formValue('accountDisplayName'),
        role: formValue('accountRole'),
        status: formValue('accountStatus') || 'ACTIVE',
        unit_id: Number(formValue('accountUnit') || 0),
        phone: formValue('accountPhone') || null,
        position: formValue('accountPosition') || null
      };
      const password = formValue('accountPassword');
      if (password) payload.password = password;
      return payload;
    }

    function validateAccountForm(payload, creating) {
      if (!payload.display_name) return 'Ho ten la bat buoc';
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email || '')) return 'Email khong hop le';
      if (!/^[a-z0-9._-]{3,60}$/.test(payload.username || '')) return 'Username khong hop le';
      if (!['SYSTEM_ADMIN', 'VILLAGE_ADMIN', 'STAFF', 'VIEWER'].includes(payload.role)) return 'Vai tro khong hop le';
      if (!['ACTIVE', 'INACTIVE'].includes(payload.status)) return 'Trang thai khong hop le';
      if (!payload.unit_id) return 'Don vi la bat buoc';
      if (creating && (!payload.password || payload.password.length < 8)) return 'Mat khau toi thieu 8 ky tu';
      return '';
    }

    function setAccountFormError(message) {
      const error = document.getElementById('accountFormError');
      error.textContent = message || '';
      error.classList.toggle('active', Boolean(message));
    }

    async function saveAccount(event) {
      event.preventDefault();
      const button = document.getElementById('saveAccountButton');
      const payload = accountPayload();
      const validation = validateAccountForm(payload, !accountState.editing);
      if (validation) {
        setAccountFormError(validation);
        return;
      }
      button.disabled = true;
      setAccountFormError('');
      try {
        if (accountState.editing) {
          await api('/api/control-center/users/' + encodeURIComponent(accountState.editing.id), { method: 'PUT', body: payload });
        } else {
          await api('/api/control-center/users', { method: 'POST', body: payload });
        }
        closeAccountModal();
        await loadAccounts();
      } catch (error) {
        setAccountFormError(error.message || 'Khong luu duoc tai khoan');
      } finally {
        button.disabled = false;
      }
    }

    async function changeAccountStatus(account, action) {
      const message = action === 'deactivate' ? 'Xac nhan ngung su dung tai khoan nay?' : 'Xac nhan kich hoat tai khoan nay?';
      if (!confirm(message)) return;
      setAccountsAlert('');
      try {
        await api('/api/control-center/users/' + encodeURIComponent(account.id) + '/' + action, { method: 'PATCH' });
        await loadAccounts();
      } catch (error) {
        setAccountsAlert(error.message || 'Khong cap nhat duoc trang thai tai khoan');
      }
    }

    function openPasswordModal(account) {
      accountState.passwordTarget = account;
      document.getElementById('passwordModalTitle').textContent = 'Reset mat khau - ' + (account.displayName || account.email);
      document.getElementById('passwordAccountId').value = account.id;
      document.getElementById('newPassword').value = '';
      setPasswordFormError('');
      document.getElementById('passwordModal').classList.add('active');
      document.getElementById('newPassword').focus();
    }

    function closePasswordModal() {
      document.getElementById('passwordModal').classList.remove('active');
      accountState.passwordTarget = null;
    }

    function setPasswordFormError(message) {
      const error = document.getElementById('passwordFormError');
      error.textContent = message || '';
      error.classList.toggle('active', Boolean(message));
    }

    async function savePassword(event) {
      event.preventDefault();
      const password = formValue('newPassword');
      if (password.length < 8) {
        setPasswordFormError('Mat khau toi thieu 8 ky tu');
        return;
      }
      const button = document.getElementById('savePasswordButton');
      button.disabled = true;
      setPasswordFormError('');
      try {
        await api('/api/control-center/users/' + encodeURIComponent(accountState.passwordTarget.id) + '/reset-password', { method: 'PATCH', body: { password } });
        closePasswordModal();
        await loadAccounts();
      } catch (error) {
        setPasswordFormError(error.message || 'Khong cap nhat duoc mat khau');
      } finally {
        button.disabled = false;
      }
    }

    function suggestUsername() {
      const username = document.getElementById('accountUsername');
      if (username.value.trim() !== '' || accountState.editing) return;
      const source = formValue('accountEmail') || formValue('accountDisplayName');
      const suggested = source.split('@')[0].normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9._-]+/g, '.').replace(/^\.+|\.+$/g, '').slice(0, 60);
      if (suggested.length >= 3) username.value = suggested;
    }

    function formatBytes(value) {
      const bytes = Number(value || 0);
      if (bytes <= 0) return '0 B';
      const units = ['B', 'KB', 'MB', 'GB', 'TB'];
      const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
      return `${(bytes / (1024 ** index)).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
    }

    async function loadControlCenter() {
      await Promise.all([loadUnits(), loadAccounts(), loadPermissions(), loadMonitoring()]).catch((error) => {
        document.getElementById('healthBadge').textContent = 'DEGRADED';
        document.getElementById('healthBadge').className = 'cc-badge warn';
      });
      renderAuditTenantFilter();
      await loadAudit().catch(() => {});
      await loadDashboard().catch(() => {});
    }

    restoreSession().then((restored) => {
      if (restored) {
        loadControlCenter();
      } else {
        document.getElementById('loginUsername').focus();
      }
    });

    document.getElementById('loginForm').addEventListener('submit', login);
    document.getElementById('logoutButton').addEventListener('click', logout);
    document.getElementById('refreshOperationsButton').addEventListener('click', () => loadDashboard().catch(() => {}));
    document.querySelectorAll('[data-go-section]').forEach((button) => {
      button.addEventListener('click', () => activateSection(button.dataset.goSection));
    });
    document.getElementById('globalSearch').addEventListener('keydown', (event) => {
      if (event.key !== 'Enter') return;
      const query = event.currentTarget.value.trim().toLowerCase();
      const targets = [
        ['dashboard', ['dashboard', 'tong quan', 'thong ke']],
        ['units', ['don vi', 'thon', 'xa', 'administrative']],
        ['accounts', ['tai khoan', 'nguoi dung', 'user']],
        ['permissions', ['phan quyen', 'permission', 'quyen']],
        ['monitoring', ['monitoring', 'health', 'trang thai']],
        ['audit', ['audit', 'lich su', 'truy vet']],
        ['configuration', ['cau hinh', 'config', 'settings']],
        ['notifications', ['thong bao', 'notification']],
        ['ai', ['ai', 'tro ly']]
      ];
      const match = targets.find(([, terms]) => terms.some((term) => query.includes(term) || term.includes(query)));
      if (match) {
        activateSection(match[0]);
        event.currentTarget.blur();
      }
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
    document.getElementById('addAccountButton').addEventListener('click', () => openAccountModal());
    document.getElementById('refreshAccountsButton').addEventListener('click', () => loadAccounts());
    document.getElementById('accountRoleFilter').addEventListener('change', () => loadAccounts());
    document.getElementById('accountStatusFilter').addEventListener('change', () => loadAccounts());
    document.getElementById('accountSearch').addEventListener('input', (() => {
      let timer = null;
      return () => {
        clearTimeout(timer);
        timer = setTimeout(() => loadAccounts(), 250);
      };
    })());
    document.getElementById('accountForm').addEventListener('submit', saveAccount);
    document.getElementById('closeAccountModalButton').addEventListener('click', closeAccountModal);
    document.getElementById('cancelAccountButton').addEventListener('click', closeAccountModal);
    document.getElementById('accountModal').addEventListener('click', (event) => {
      if (event.target.id === 'accountModal') closeAccountModal();
    });
    document.getElementById('accountEmail').addEventListener('blur', suggestUsername);
    document.getElementById('accountDisplayName').addEventListener('blur', suggestUsername);
    document.getElementById('passwordForm').addEventListener('submit', savePassword);
    document.getElementById('closePasswordModalButton').addEventListener('click', closePasswordModal);
    document.getElementById('cancelPasswordButton').addEventListener('click', closePasswordModal);
    document.getElementById('passwordModal').addEventListener('click', (event) => {
      if (event.target.id === 'passwordModal') closePasswordModal();
    });
    document.getElementById('refreshPermissionsButton').addEventListener('click', () => loadPermissions());
    document.getElementById('savePermissionsButton').addEventListener('click', savePermissions);
    document.getElementById('permissionRoleFilter').addEventListener('change', renderPermissions);
    document.getElementById('permissionSearch').addEventListener('input', (() => {
      let timer = null;
      return () => {
        clearTimeout(timer);
        timer = setTimeout(renderPermissions, 250);
      };
    })());
    document.getElementById('refreshAuditButton').addEventListener('click', () => loadAudit());
    document.getElementById('auditTenantFilter').addEventListener('change', () => loadAudit());
    document.getElementById('auditLevelFilter').addEventListener('change', () => loadAudit());
    document.getElementById('auditSearch').addEventListener('input', (() => {
      let timer = null;
      return () => {
        clearTimeout(timer);
        timer = setTimeout(() => loadAudit(), 250);
      };
    })());
  </script>
</body>
</html>
