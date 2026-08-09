<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>{{APP_NAME}}</title>
  <link rel="icon" href="{{PLATFORM_FAVICON_URL}}">
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

    .tenant-toolbar {
      grid-template-columns: minmax(220px, 1fr) 160px 160px 150px 120px auto;
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

    .cc-pagination {
      padding: 12px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      border-top: 1px solid var(--cc-line);
      flex-wrap: wrap;
    }

    .tenant-detail-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
      padding: 16px;
    }

    .tenant-detail-item {
      border: 1px solid var(--cc-line);
      border-radius: 8px;
      padding: 10px 12px;
      min-width: 0;
    }

    .tenant-detail-item.full {
      grid-column: 1 / -1;
    }

    .tenant-detail-label {
      color: var(--cc-muted);
      font-size: 12px;
      font-weight: 750;
      margin-bottom: 4px;
    }

    .tenant-detail-value {
      overflow-wrap: anywhere;
      font-weight: 700;
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
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .cc-modal-footer .cc-btn {
      white-space: normal;
      text-align: center;
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

    .preflight-panel {
      display: none;
      margin: 0 16px 14px;
      border: 1px solid var(--cc-line);
      border-radius: 8px;
      overflow: hidden;
    }

    .preflight-panel.active {
      display: block;
    }

    .preflight-status {
      padding: 10px 12px;
      font-weight: 800;
      border-bottom: 1px solid var(--cc-line);
    }

    .preflight-status.ready {
      color: #067647;
      background: #ecfdf3;
    }

    .preflight-status.failed {
      color: var(--cc-danger);
      background: #fff1f3;
    }

    .preflight-list {
      display: grid;
      gap: 0;
    }

    .preflight-item {
      display: grid;
      grid-template-columns: 24px minmax(150px, 1fr) minmax(180px, 1.4fr);
      gap: 8px;
      padding: 9px 12px;
      border-top: 1px solid var(--cc-line);
      font-size: 13px;
    }

    .preflight-item:first-child {
      border-top: 0;
    }

    .preflight-icon.pass {
      color: #067647;
    }

    .preflight-icon.fail {
      color: var(--cc-danger);
    }

    .preflight-fix {
      color: var(--cc-muted);
    }

    .tenant-wizard {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      gap: 8px;
      padding: 14px 16px 0;
    }

    .tenant-wizard-step {
      border: 1px solid var(--cc-line);
      border-radius: 8px;
      padding: 8px;
      color: var(--cc-muted);
      font-size: 12px;
      font-weight: 800;
      line-height: 1.25;
      min-width: 0;
      text-align: center;
      overflow-wrap: anywhere;
    }

    .tenant-wizard-step.active {
      border-color: var(--cc-primary);
      color: var(--cc-primary);
      background: #ecfdf3;
    }

    .tenant-wizard-step.done {
      color: #067647;
      background: #f6fef9;
    }

    .wizard-page {
      display: none;
    }

    .wizard-page.active {
      display: contents;
    }

    .preflight-panel.wizard-page.active,
    .tenant-result.wizard-page.active {
      display: block;
    }

    .tenant-result {
      display: none;
      margin: 0 16px 14px;
      padding: 12px;
      border: 1px solid var(--cc-line);
      border-radius: 8px;
      background: #f8fafc;
      color: var(--cc-text);
      font-weight: 700;
    }

    .tenant-result.active {
      display: block;
    }

    .tenant-progress {
      height: 12px;
      border-radius: 999px;
      background: #eef2f7;
      overflow: hidden;
      margin: 8px 0 12px;
    }

    .tenant-progress-bar {
      height: 100%;
      width: 0%;
      background: var(--cc-brand);
      transition: width .2s ease;
    }

    .tenant-log-list {
      display: grid;
      gap: 8px;
      max-height: 220px;
      overflow: auto;
      margin-top: 10px;
    }

    .tenant-log-item {
      display: grid;
      gap: 3px;
      padding: 8px 10px;
      border: 1px solid var(--cc-line);
      border-radius: 8px;
      background: #fff;
      font-size: 13px;
    }

    .tenant-log-item strong {
      font-size: 12px;
      color: var(--cc-muted);
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
      .tenant-toolbar,
      .tenant-detail-grid,
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

      .tenant-wizard {
        grid-template-columns: 1fr;
      }

      .cc-table-wrap {
        overflow-x: auto;
      }
    }
    .settings-tabs {
      display: flex;
      gap: 8px;
      overflow-x: auto;
      padding-bottom: 4px;
    }

    .settings-tab {
      border: 1px solid var(--cc-line);
      background: #fff;
      color: var(--cc-ink);
      border-radius: 8px;
      padding: 9px 12px;
      font-weight: 700;
      white-space: nowrap;
    }

    .settings-tab.active {
      border-color: var(--cc-brand);
      color: var(--cc-brand);
      background: #ecfdf5;
    }

    .settings-pane { display: none; gap: 12px; }
    .settings-pane.active { display: grid; }

    .settings-card {
      background: var(--cc-panel);
      border: 1px solid var(--cc-line);
      border-radius: 8px;
      overflow: hidden;
    }

    .settings-card-head,
    .settings-card-foot {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 14px 16px;
      border-bottom: 1px solid var(--cc-line);
    }

    .settings-card-foot { border-top: 1px solid var(--cc-line); border-bottom: 0; justify-content: flex-end; }
    .settings-card-title { margin: 0; font-size: 16px; font-weight: 800; }
    .settings-card-note { margin: 4px 0 0; color: var(--cc-muted); font-size: 13px; }
    .settings-card-body { padding: 16px; }
    .settings-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .settings-grid.three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .settings-field { display: grid; gap: 6px; }
    .settings-field label { color: #344054; font-size: 13px; font-weight: 700; }
    .settings-status-list { display: grid; gap: 10px; }
    .settings-status-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 10px 0; border-bottom: 1px solid #eef2f7; }
    .settings-status-row:last-child { border-bottom: 0; }
    .settings-toggle-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid #eef2f7; }
    .settings-toggle-row:last-child { border-bottom: 0; }
    .settings-readonly { color: var(--cc-muted); font-size: 13px; }
    .settings-alert { color: var(--cc-danger); font-size: 13px; min-height: 18px; }

    @media (max-width: 900px) {
      .settings-grid,
      .settings-grid.three { grid-template-columns: 1fr; }
    }

    .cc-brand-mark img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      border-radius: 8px;
      background: #fff;
    }

    .branding-upload-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
    }

    .branding-upload-card {
      border: 1px solid var(--cc-line);
      border-radius: 8px;
      padding: 14px;
      display: grid;
      gap: 12px;
      background: #fff;
    }

    .branding-preview {
      min-height: 132px;
      border: 1px dashed #cfd6e2;
      border-radius: 8px;
      background: #f8fafc;
      display: grid;
      place-items: center;
      overflow: hidden;
      color: var(--cc-muted);
      font-weight: 700;
    }

    .branding-preview.wide { min-height: 170px; aspect-ratio: 16 / 6; }
    .branding-preview img { width: 100%; height: 100%; object-fit: contain; }
    .branding-preview.wide img { object-fit: cover; }
    .branding-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .branding-file { display: none; }

    @media (max-width: 900px) {
      .branding-upload-grid { grid-template-columns: 1fr; }
    }</style>
  <script>
    window.AppSettings={{APP_SETTINGS_JSON}};
  </script>
</head>
<body>
  <div class="cc-login-screen" id="loginScreen">
    <form class="cc-login-card" id="loginForm" novalidate>
      <div class="cc-brand">
        <div class="cc-brand-mark">{{CONTROL_CENTER_LOGO_HTML}}</div>
        <div>
          <h1>Community Control Center</h1>
          <p>Đăng nhập để điều hành Hong Phong Community Platform.</p>
        </div>
      </div>
      <div class="cc-field">
        <label for="loginUsername">Tài khoản hoặc email</label>
        <input class="cc-input" id="loginUsername" name="username" autocomplete="username" required>
      </div>
      <div class="cc-field">
        <label for="loginPassword">Mật khẩu</label>
        <input class="cc-input" id="loginPassword" name="password" type="password" autocomplete="current-password" required>
      </div>
      <div class="cc-form-error active" id="loginError"></div>
      <button class="cc-btn primary" type="submit" id="loginButton"><i class="fa-solid fa-right-to-bracket"></i>Đăng nhập</button>
    </form>
  </div>
  <div class="control-center">
    <aside class="cc-sidebar">
      <div class="cc-brand">
        <div class="cc-brand-mark">{{CONTROL_CENTER_LOGO_HTML}}</div>
        <div>
          <div class="cc-brand-title">HONG PHONG COMMUNITY PLATFORM</div>
          <div class="cc-brand-subtitle">Community Control Center</div>
        </div>
      </div>
      <nav class="cc-nav" aria-label="Control Center">
        <button class="active" type="button" data-section="dashboard"><i class="fa-solid fa-chart-line"></i>Tổng quan</button>
        <button type="button" data-section="units"><i class="fa-solid fa-sitemap"></i>Đơn vị</button>
        <button type="button" data-section="tenants"><i class="fa-solid fa-building-user"></i>Tenant</button>
        <button type="button" data-section="accounts"><i class="fa-solid fa-users-gear"></i>Người dùng</button>
        <button type="button" data-section="permissions"><i class="fa-solid fa-shield-halved"></i>Phân quyền</button>
        <button type="button" data-section="executive"><i class="fa-solid fa-gauge-high"></i>Bảng điều hành</button>
        <button type="button" data-section="monitoring"><i class="fa-solid fa-heart-pulse"></i>Giám sát</button>
        <button type="button" data-section="audit"><i class="fa-solid fa-clock-rotate-left"></i>Nhật ký</button>
        <button type="button" data-section="configuration"><i class="fa-solid fa-gear"></i>Cấu hình</button>
        <button type="button" data-section="notifications"><i class="fa-solid fa-bell"></i>Thông báo</button>
        <button type="button" data-section="ai"><i class="fa-solid fa-wand-magic-sparkles"></i>Trợ lý thông minh</button>
      </nav>
    </aside>

    <main class="cc-main">
      <header class="cc-header">
        <div>
          <h1 class="cc-title" id="sectionTitle">Tổng quan</h1>
          <div class="cc-meta" id="portalMeta">HONG PHONG COMMUNITY PLATFORM - Community Control Center</div>
        </div>
        <div class="cc-header-actions">
          <input class="cc-input cc-global-search" type="search" id="globalSearch" placeholder="Tìm nhanh: bảng điều hành, đơn vị, tài khoản, phân quyền">
          <span class="cc-meta" id="currentUserLabel">Chưa đăng nhập</span>
          <span class="cc-badge" id="healthBadge">Đang kiểm tra</span>
          <button class="cc-btn" type="button" id="logoutButton"><i class="fa-solid fa-right-from-bracket"></i>Đăng xuất</button>
        </div>
      </header>

      <div class="cc-content">
        <section class="cc-section active" id="dashboardSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <div>
                <h2 class="cc-panel-title">Công việc cần xử lý hôm nay</h2>
                <div class="cc-meta">Ưu tiên các sự cố cần người quản trị xử lý ngay.</div>
              </div>
              <button class="cc-btn" type="button" id="refreshOperationsButton"><i class="fa-solid fa-rotate"></i>Kiểm tra lại</button>
            </div>
            <div class="operation-list" id="operationsList"></div>
          </div>
          <div class="metric-grid" id="metricGrid"></div>
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Hoạt động gần đây</h2>
              <button class="cc-btn" type="button" data-go-section="audit"><i class="fa-solid fa-clock-rotate-left"></i>Xem nhật ký</button>
            </div>
            <div class="operation-list" id="recentActivityList"></div>
          </div>
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Thao tác nhanh</h2>
              <span class="cc-meta">Mở nhanh các năng lực điều hành đang sẵn sàng</span>
            </div>
            <div class="cc-state quick-actions">
              <button class="cc-btn" type="button" data-go-section="units"><i class="fa-solid fa-sitemap"></i>Quản lý đơn vị</button>
              <button class="cc-btn" type="button" data-go-section="tenants"><i class="fa-solid fa-building-user"></i>Quản lý Tenant</button>
              <button class="cc-btn" type="button" data-go-section="accounts"><i class="fa-solid fa-users-gear"></i>Quản lý tài khoản</button>
              <button class="cc-btn" type="button" data-go-section="permissions"><i class="fa-solid fa-shield-halved"></i>Phân quyền</button>
            </div>
          </div>
        </section>

        <section class="cc-section" id="executiveSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <div>
                <h2 class="cc-panel-title">Bảng điều hành</h2>
                <div class="cc-meta">Tổng hợp tình trạng vận hành toàn bộ Community Control Center.</div>
              </div>
              <button class="cc-btn" type="button" id="refreshExecutiveButton"><i class="fa-solid fa-rotate"></i>Kiểm tra lại</button>
            </div>
            <div class="metric-grid" id="executiveMetricGrid"></div>
          </div>
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Tình trạng vận hành</h2>
            </div>
            <div class="monitor-grid" id="executiveHealthGrid"></div>
          </div>
        </section>

        <section class="cc-section" id="unitsSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Quản lý đơn vị hành chính</h2>
              <button class="cc-btn primary" type="button" id="addUnitButton"><i class="fa-solid fa-plus"></i>Thêm đơn vị</button>
            </div>
            <div class="cc-toolbar">
              <input class="cc-input" type="search" id="unitSearch" placeholder="Tìm theo mã, tên, tên miền">
              <select class="cc-select" id="unitStatusFilter" aria-label="Lọc trạng thái">
                <option value="">Tất cả trạng thái</option>
                <option value="READY">Sẵn sàng</option>
                <option value="CREATING">Đang tạo</option>
                <option value="FAILED">Lỗi</option>
                <option value="DISABLED">Đã khóa</option>
                <option value="MAINTENANCE">Bảo trì</option>
              </select>
              <button class="cc-btn" type="button" id="refreshUnitsButton"><i class="fa-solid fa-rotate"></i>Tải lại</button>
            </div>
            <div class="cc-alert" id="unitsAlert"></div>
            <div class="cc-row-actions" id="tenantInstallerActions" style="display:none;margin-bottom:12px">
              <button class="cc-btn" type="button" id="retryTenantInstallButton"><i class="fa-solid fa-rotate-right"></i>Thử lại</button>
              <button class="cc-btn danger" type="button" id="rollbackTenantInstallButton"><i class="fa-solid fa-clock-rotate-left"></i>Hoàn tác</button>
            </div>
            <div class="cc-table-wrap">
              <table class="cc-table">
                <thead>
                  <tr>
                    <th>Mã</th>
                    <th>Tên đơn vị</th>
                    <th>Tên miền</th>
                    <th>Cơ sở dữ liệu</th>
                    <th>Trạng thái</th>
                    <th>Người quản lý</th>
                    <th>Trang web</th>
                    <th>Cơ sở dữ liệu</th>
                    <th>Phiên bản</th>
                    <th>Thao tác</th>
                  </tr>
                </thead>
                <tbody id="unitsBody"></tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="cc-section" id="tenantsSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <div>
                <h2 class="cc-panel-title">Quản lý Tenant</h2>
                <div class="cc-meta">Quản lý thông tin Tenant, trạng thái, phiên bản, dung lượng và nhật ký hoạt động.</div>
              </div>
              <button class="cc-btn primary" type="button" id="addTenantButton" data-tenant-permission="tenant.create"><i class="fa-solid fa-plus"></i>Thêm Tenant</button>
            </div>
            <div class="cc-toolbar tenant-toolbar">
              <input class="cc-input" type="search" id="tenantSearch" placeholder="Tìm mã, tên, domain, database">
              <select class="cc-select" id="tenantStatusFilter" aria-label="Lọc trạng thái">
                <option value="">Tất cả trạng thái</option>
                <option value="ACTIVE">Hoạt động</option>
                <option value="READY">Sẵn sàng</option>
                <option value="LOCKED">Đã khóa</option>
                <option value="DISABLED">Đã tắt</option>
                <option value="MAINTENANCE">Bảo trì</option>
                <option value="FAILED">Lỗi</option>
                <option value="DELETED">Đã xóa mềm</option>
              </select>
              <select class="cc-select" id="tenantVersionFilter" aria-label="Lọc phiên bản">
                <option value="">Tất cả phiên bản</option>
              </select>
              <select class="cc-select" id="tenantSort" aria-label="Sắp xếp">
                <option value="updated">Cập nhật mới nhất</option>
                <option value="name">Tên Tenant</option>
                <option value="status">Trạng thái</option>
                <option value="code">Mã Tenant</option>
                <option value="storage">Dung lượng</option>
              </select>
              <select class="cc-select" id="tenantDirection" aria-label="Chiều sắp xếp">
                <option value="DESC">Giảm dần</option>
                <option value="ASC">Tăng dần</option>
              </select>
              <button class="cc-btn" type="button" id="refreshTenantsButton"><i class="fa-solid fa-rotate"></i>Tải lại</button>
            </div>
            <div class="cc-alert" id="tenantsAlert"></div>
            <div class="cc-table-wrap">
              <table class="cc-table">
                <thead>
                  <tr>
                    <th>Mã</th>
                    <th>Tên Tenant</th>
                    <th>Domain</th>
                    <th>Database</th>
                    <th>Trạng thái</th>
                    <th>Phiên bản</th>
                    <th>Dung lượng</th>
                    <th>Cập nhật</th>
                    <th>Thao tác</th>
                  </tr>
                </thead>
                <tbody id="tenantsBody"></tbody>
              </table>
            </div>
            <div class="cc-pagination" id="tenantsPagination">
              <span class="cc-meta" id="tenantsPageInfo">Chưa có dữ liệu</span>
              <div class="cc-row-actions">
                <button class="cc-btn" type="button" id="tenantPrevPageButton"><i class="fa-solid fa-chevron-left"></i>Trước</button>
                <button class="cc-btn" type="button" id="tenantNextPageButton">Sau<i class="fa-solid fa-chevron-right"></i></button>
              </div>
            </div>
          </div>
        </section>

        <section class="cc-section" id="accountsSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Quản lý tài khoản hệ thống</h2>
              <button class="cc-btn primary" type="button" id="addAccountButton"><i class="fa-solid fa-user-plus"></i>Thêm tài khoản</button>
            </div>
            <div class="cc-toolbar">
              <input class="cc-input" type="search" id="accountSearch" placeholder="Tìm tên đăng nhập, họ tên, email, vai trò, đơn vị">
              <select class="cc-select" id="accountRoleFilter" aria-label="Lọc vai trò">
                <option value="">Tất cả vai trò</option>
                <option value="SYSTEM_ADMIN">Quản trị hệ thống</option>
                <option value="VILLAGE_ADMIN">Quản trị thôn</option>
                <option value="STAFF">Cán bộ nhập liệu</option>
                <option value="VIEWER">Chỉ xem</option>
              </select>
              <select class="cc-select" id="accountStatusFilter" aria-label="Lọc trạng thái">
                <option value="">Tất cả trạng thái</option>
                <option value="ACTIVE">Đang sử dụng</option>
                <option value="INACTIVE">Ngừng sử dụng</option>
              </select>
              <button class="cc-btn" type="button" id="refreshAccountsButton"><i class="fa-solid fa-rotate"></i>Tải lại</button>
            </div>
            <div class="cc-alert" id="accountsAlert"></div>
            <div class="cc-table-wrap">
              <table class="cc-table">
                <thead>
                  <tr>
                    <th>Tên</th>
                    <th>Vai trò</th>
                    <th>Đơn vị</th>
                    <th>Trạng thái</th>
                    <th>Đăng nhập cuối</th>
                    <th>Địa chỉ IP cuối</th>
                    <th>Thiết bị cuối</th>
                    <th>Thời gian tạo</th>
                    <th>Người tạo</th>
                    <th>Thao tác</th>
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
                <h2 class="cc-panel-title">Phân quyền Community Control Center</h2>
                <div class="cc-meta">Kiểm soát trình đơn, phân hệ, nút, thao tác và API trong Control Center.</div>
              </div>
              <button class="cc-btn primary" type="button" id="savePermissionsButton" disabled><i class="fa-solid fa-floppy-disk"></i>Lưu thay đổi</button>
            </div>
            <div class="cc-toolbar">
              <input class="cc-input" type="search" id="permissionSearch" placeholder="Tìm quyền, phân hệ, thao tác">
              <select class="cc-select" id="permissionRoleFilter" aria-label="Lọc vai trò">
                <option value="">Tất cả vai trò</option>
              </select>
              <button class="cc-btn" type="button" id="refreshPermissionsButton"><i class="fa-solid fa-rotate"></i>Tải lại</button>
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
              <h2 class="cc-panel-title">Nhật ký kiểm toán</h2>
              <button class="cc-btn" type="button" id="refreshAuditButton"><i class="fa-solid fa-rotate"></i>Tải lại</button>
            </div>
            <div class="cc-toolbar">
              <select class="cc-select" id="auditTenantFilter" aria-label="Lọc đơn vị">
                <option value="">Tất cả đơn vị</option>
              </select>
              <select class="cc-select" id="auditLevelFilter" aria-label="Lọc mức độ">
                <option value="">Tất cả mức độ</option>
                <option value="INFO">Thông tin</option>
                <option value="WARN">Cảnh báo</option>
                <option value="ERROR">Lỗi</option>
              </select>
              <input class="cc-input" type="search" id="auditSearch" placeholder="Tìm người thực hiện, hành động, đơn vị">
            </div>
            <div class="cc-table-wrap">
              <table class="cc-table">
                <thead>
                  <tr>
                    <th>Thời gian</th>
                    <th>Đơn vị</th>
                    <th>Người thực hiện</th>
                    <th>Hành động</th>
                    <th>Mức độ</th>
                    <th>Kết quả</th>
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
              <h2 class="cc-panel-title">Cấu hình nền tảng</h2>
              <button class="cc-btn" type="button" id="refreshConfigurationButton"><i class="fa-solid fa-rotate"></i>Tải lại</button>
            </div>
            <div class="settings-card-body">
              <div class="cc-alert" id="configurationAlert"></div>
              <div class="settings-tabs" id="configurationTabs" role="tablist"></div>
            </div>
          </div>
          <div id="configurationPanes"></div>
        </section>
        <section class="cc-section" id="notificationsSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Thông báo</h2>
              <span class="cc-badge warn">Đang phát triển</span>
            </div>
            <div class="cc-state">Sẽ điều phối thông tin, thông báo nội bộ và cảnh báo vận hành.</div>
          </div>
        </section>

        <section class="cc-section" id="aiSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Trợ lý thông minh</h2>
              <span class="cc-badge warn">Đang phát triển</span>
            </div>
            <div class="cc-state">Sẽ hỗ trợ tìm kiếm, tổng hợp và gợi ý thao tác trong phạm vi được cấp quyền.</div>
          </div>
        </section>
      </div>

      <footer class="cc-footer">
        Nền tảng Community Control Center. Cổng đơn vị và các phân hệ nghiệp vụ được giữ tách biệt.
      </footer>
    </main>
  </div>

  <div class="cc-modal-backdrop" id="unitModal" role="dialog" aria-modal="true" aria-labelledby="unitModalTitle">
    <div class="cc-modal">
      <div class="cc-modal-header">
        <h2 class="cc-modal-title" id="unitModalTitle">Thêm đơn vị</h2>
        <button class="cc-btn" type="button" id="closeUnitModalButton" aria-label="Đóng"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="unitForm" novalidate>
        <div class="tenant-wizard" id="tenantWizard">
          <div class="tenant-wizard-step active" data-wizard-indicator="1">1. Cấu hình Tenant</div>
          <div class="tenant-wizard-step" data-wizard-indicator="2">2. Database</div>
          <div class="tenant-wizard-step" data-wizard-indicator="3">3. Preflight</div>
          <div class="tenant-wizard-step" data-wizard-indicator="4">4. Hạ tầng</div>
          <div class="tenant-wizard-step" data-wizard-indicator="5">5. Xác nhận</div>
          <div class="tenant-wizard-step" data-wizard-indicator="6">6. Tiến trình</div>
          <div class="tenant-wizard-step" data-wizard-indicator="7">7. Kết quả</div>
        </div>
        <div class="cc-form">
          <input type="hidden" id="unitId">
          <div class="wizard-page active" data-wizard-page="1">
          <div class="cc-field">
            <label for="unitCode">Mã đơn vị *</label>
            <input class="cc-input" id="unitCode" name="code" required maxlength="50" pattern="[a-z0-9_-]{2,50}" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitName">Tên đơn vị *</label>
            <input class="cc-input" id="unitName" name="name" required maxlength="190" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitCommuneName">Xã/Phường</label>
            <input class="cc-input" id="unitCommuneName" name="commune_name" maxlength="190" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitStatus">Trạng thái</label>
            <select class="cc-select" id="unitStatus" name="status">
              <option value="READY">Sẵn sàng</option>
              <option value="CREATING">Đang tạo</option>
              <option value="FAILED">Lỗi</option>
              <option value="DISABLED">Đã khóa</option>
              <option value="MAINTENANCE">Bảo trì</option>
            </select>
          </div>
          <div class="cc-field">
            <label for="unitDomain">Tên miền</label>
            <input class="cc-input" id="unitDomain" name="domain" maxlength="190" placeholder="thon09.hongphongnb.com" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitSubdomain">Tên miền phụ</label>
            <input class="cc-input" id="unitSubdomain" name="subdomain" maxlength="190" placeholder="thon09.hongphongnb.com" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitAppVersion">Phiên bản ứng dụng</label>
            <input class="cc-input" id="unitAppVersion" name="app_version" maxlength="50" placeholder="v2.0" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitBuildVersion">Phiên bản bản dựng</label>
            <input class="cc-input" id="unitBuildVersion" name="build_version" maxlength="100" placeholder="20260727-gis-multi-area-1" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitSchemaVersion">Phiên bản lược đồ</label>
            <input class="cc-input" id="unitSchemaVersion" name="schema_version" maxlength="50" placeholder="20260729" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitManagerName">Người quản lý</label>
            <input class="cc-input" id="unitManagerName" name="manager_name" maxlength="190" placeholder="Chưa gán" autocomplete="off">
          </div>
          <div class="cc-field full">
            <label for="unitLogo">Đường dẫn logo</label>
            <input class="cc-input" id="unitLogo" name="logo" maxlength="500" placeholder="/assets/logo.png" autocomplete="off">
          </div>
          <div class="cc-field full">
            <label for="unitNotes">Ghi chú</label>
            <textarea class="cc-input" id="unitNotes" name="notes" maxlength="2000" rows="3" placeholder="Thông tin vận hành, lịch sao lưu, người phụ trách..."></textarea>
          </div>
          </div>
          <div class="wizard-page" data-wizard-page="2">
          <div class="cc-field">
            <label for="unitDatabaseHost">Máy chủ cơ sở dữ liệu</label>
            <input class="cc-input" id="unitDatabaseHost" name="database_host" maxlength="190" placeholder="localhost" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitDatabaseName">Tên cơ sở dữ liệu</label>
            <input class="cc-input" id="unitDatabaseName" name="database_name" maxlength="190" placeholder="database_name" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitDatabaseUsername">Người dùng cơ sở dữ liệu</label>
            <input class="cc-input" id="unitDatabaseUsername" name="database_username" maxlength="190" placeholder="nguoi_dung_csdl" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitDatabasePassword">Mật khẩu cơ sở dữ liệu</label>
            <input class="cc-input" id="unitDatabasePassword" name="database_password" type="password" autocomplete="new-password">
          </div>
          <div class="cc-field">
            <label for="unitDatabaseCharset">Bảng mã cơ sở dữ liệu</label>
            <input class="cc-input" id="unitDatabaseCharset" name="database_charset" maxlength="50" placeholder="utf8mb4" autocomplete="off">
          </div>
          </div>
        </div>
        <div class="cc-form-error" id="unitFormError"></div>
        <div class="preflight-panel wizard-page" id="tenantDatabasePanel" data-wizard-page="2">
          <div class="preflight-status failed" id="tenantDatabaseStatus">Chưa kiểm tra cơ sở dữ liệu</div>
          <div class="preflight-list" id="tenantDatabaseList"></div>
        </div>
        <div class="preflight-panel wizard-page" id="tenantPreflightPanel" data-wizard-page="3">
          <div class="preflight-status failed" id="tenantPreflightStatus">Chưa chạy tiền kiểm</div>
          <div class="preflight-list" id="tenantPreflightList"></div>
        </div>
        <div class="preflight-panel wizard-page" id="tenantInfrastructurePanel" data-wizard-page="4">
          <div class="preflight-status failed" id="tenantInfrastructureStatus">Chưa xác minh hạ tầng</div>
          <div class="preflight-list" id="tenantInfrastructureList"></div>
        </div>
        <div class="tenant-result wizard-page" id="tenantConfirmPanel" data-wizard-page="5">Sẵn sàng xác nhận cài đặt Tenant</div>
        <div class="tenant-result wizard-page" id="tenantProgressPanel" data-wizard-page="6">
          <div><span class="cc-badge warn" id="tenantInstallStatusBadge">Pending</span></div>
          <div class="tenant-progress" aria-label="Tiến trình cài đặt Tenant"><div class="tenant-progress-bar" id="tenantInstallProgressBar"></div></div>
          <div id="tenantCreatePanel">Chưa bắt đầu cài đặt</div>
          <div class="tenant-log-list" id="tenantInstallLogList"></div>
        </div>
        <div class="tenant-result wizard-page" id="tenantHealthPanel" data-wizard-page="7">Chưa chạy kiểm tra sau cài đặt</div>
        <div class="cc-modal-footer">
          <button class="cc-btn" type="button" id="cancelUnitButton">Hủy</button>
          <button class="cc-btn" type="button" id="wizardBackButton">Quay lại</button>
          <button class="cc-btn" type="button" id="wizardNextButton">Tiếp</button>
          <button class="cc-btn" type="button" id="databaseCheckButton"><i class="fa-solid fa-plug-circle-check"></i>Kiểm tra kết nối cơ sở dữ liệu</button>
          <button class="cc-btn" type="button" id="preflightUnitButton"><i class="fa-solid fa-shield-halved"></i>Tiền kiểm</button>
          <button class="cc-btn primary" type="submit" id="saveUnitButton" disabled><i class="fa-solid fa-floppy-disk"></i>Tạo đơn vị</button>
        </div>
      </form>
    </div>
  </div>

  <div class="cc-modal-backdrop" id="tenantModal" role="dialog" aria-modal="true" aria-labelledby="tenantModalTitle">
    <div class="cc-modal">
      <div class="cc-modal-header">
        <h2 class="cc-modal-title" id="tenantModalTitle">Thêm Tenant</h2>
        <button class="cc-btn" type="button" id="closeTenantModalButton" aria-label="Đóng"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="tenantForm" novalidate>
        <div class="cc-form">
          <input type="hidden" id="tenantId">
          <div class="cc-field">
            <label for="tenantCode">Mã Tenant *</label>
            <input class="cc-input" id="tenantCode" name="code" required maxlength="50" pattern="[a-z0-9_-]{2,50}" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantName">Tên Tenant *</label>
            <input class="cc-input" id="tenantName" name="name" required maxlength="190" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantUnitName">Tên đơn vị</label>
            <input class="cc-input" id="tenantUnitName" name="unit_name" maxlength="190" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantCommuneName">Xã/Phường</label>
            <input class="cc-input" id="tenantCommuneName" name="commune_name" maxlength="190" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantDomain">Domain</label>
            <input class="cc-input" id="tenantDomain" name="domain" maxlength="190" placeholder="thon09.hongphongnb.com" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantSubdomain">Subdomain</label>
            <input class="cc-input" id="tenantSubdomain" name="subdomain" maxlength="190" placeholder="thon09.hongphongnb.com" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantDatabaseHost">Database host *</label>
            <input class="cc-input" id="tenantDatabaseHost" name="database_host" maxlength="190" placeholder="localhost" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantDatabaseName">Database name *</label>
            <input class="cc-input" id="tenantDatabaseName" name="database_name" maxlength="190" placeholder="database_name" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantDatabaseCharset">Database charset</label>
            <input class="cc-input" id="tenantDatabaseCharset" name="database_charset" maxlength="50" placeholder="utf8mb4" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantStatus">Trạng thái</label>
            <select class="cc-select" id="tenantStatus" name="status">
              <option value="READY">Sẵn sàng</option>
              <option value="ACTIVE">Hoạt động</option>
              <option value="MAINTENANCE">Bảo trì</option>
              <option value="DISABLED">Đã tắt</option>
              <option value="FAILED">Lỗi</option>
            </select>
          </div>
          <div class="cc-field">
            <label for="tenantAppVersion">Phiên bản ứng dụng</label>
            <input class="cc-input" id="tenantAppVersion" name="app_version" maxlength="50" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantBuildVersion">Phiên bản build</label>
            <input class="cc-input" id="tenantBuildVersion" name="build_version" maxlength="100" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantSchemaVersion">Phiên bản schema</label>
            <input class="cc-input" id="tenantSchemaVersion" name="schema_version" maxlength="50" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantStorageQuotaBytes">Dung lượng giới hạn (bytes)</label>
            <input class="cc-input" id="tenantStorageQuotaBytes" name="storage_quota_bytes" type="number" min="0" step="1" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantManagerName">Người quản lý</label>
            <input class="cc-input" id="tenantManagerName" name="manager_name" maxlength="190" autocomplete="off">
          </div>
          <div class="cc-field full">
            <label for="tenantNotes">Ghi chú</label>
            <textarea class="cc-input" id="tenantNotes" name="notes" maxlength="2000" rows="3"></textarea>
          </div>
        </div>
        <div class="cc-form-error" id="tenantFormError"></div>
        <div class="cc-modal-footer">
          <button class="cc-btn" type="button" id="cancelTenantButton">Hủy</button>
          <button class="cc-btn primary" type="submit" id="saveTenantButton"><i class="fa-solid fa-floppy-disk"></i>Lưu</button>
        </div>
      </form>
    </div>
  </div>

  <div class="cc-modal-backdrop" id="tenantDetailModal" role="dialog" aria-modal="true" aria-labelledby="tenantDetailTitle">
    <div class="cc-modal">
      <div class="cc-modal-header">
        <h2 class="cc-modal-title" id="tenantDetailTitle">Chi tiết Tenant</h2>
        <button class="cc-btn" type="button" id="closeTenantDetailButton" aria-label="Đóng"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="tenant-detail-grid" id="tenantDetailGrid"></div>
      <div class="cc-panel" style="border-left:0;border-right:0;border-bottom:0;border-radius:0">
        <div class="cc-panel-header">
          <h3 class="cc-panel-title">Activity</h3>
          <button class="cc-btn" type="button" id="refreshTenantActivityButton"><i class="fa-solid fa-rotate"></i>Tải lại</button>
        </div>
        <div class="cc-table-wrap">
          <table class="cc-table">
            <thead>
              <tr>
                <th>Thời gian</th>
                <th>Người thực hiện</th>
                <th>Hành động</th>
                <th>Mức độ</th>
                <th>Nội dung</th>
              </tr>
            </thead>
            <tbody id="tenantActivityBody"></tbody>
          </table>
        </div>
      </div>
      <div class="cc-modal-footer">
        <button class="cc-btn" type="button" id="closeTenantDetailFooterButton">Đóng</button>
      </div>
    </div>
  </div>

  <div class="cc-modal-backdrop" id="accountModal" role="dialog" aria-modal="true" aria-labelledby="accountModalTitle">
    <div class="cc-modal">
      <div class="cc-modal-header">
        <h2 class="cc-modal-title" id="accountModalTitle">Thêm tài khoản</h2>
        <button class="cc-btn" type="button" id="closeAccountModalButton" aria-label="Đóng"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="accountForm" novalidate>
        <div class="cc-form">
          <input type="hidden" id="accountId">
          <div class="cc-field">
            <label for="accountDisplayName">Họ tên *</label>
            <input class="cc-input" id="accountDisplayName" name="display_name" required maxlength="190" autocomplete="name">
          </div>
          <div class="cc-field">
            <label for="accountEmail">Email *</label>
            <input class="cc-input" id="accountEmail" name="email" type="email" required maxlength="190" autocomplete="email">
          </div>
          <div class="cc-field">
            <label for="accountUsername">Tên đăng nhập *</label>
            <input class="cc-input" id="accountUsername" name="username" required maxlength="60" pattern="[a-z0-9._-]{3,60}" autocomplete="username">
          </div>
          <div class="cc-field">
            <label for="accountRole">Vai trò *</label>
            <select class="cc-select" id="accountRole" name="role" required>
              <option value="VILLAGE_ADMIN">Quản trị thôn</option>
              <option value="STAFF">Cán bộ nhập liệu</option>
              <option value="VIEWER">Chỉ xem</option>
              <option value="SYSTEM_ADMIN">Quản trị hệ thống</option>
              <option value="COMMUNE_ADMIN" disabled>Quản trị xã (sau)</option>
            </select>
          </div>
          <div class="cc-field">
            <label for="accountUnit">Đơn vị *</label>
            <select class="cc-select" id="accountUnit" name="unit_id" required></select>
          </div>
          <div class="cc-field">
            <label for="accountStatus">Trạng thái</label>
            <select class="cc-select" id="accountStatus" name="status">
              <option value="ACTIVE">Đang sử dụng</option>
              <option value="INACTIVE">Ngừng sử dụng</option>
            </select>
          </div>
          <div class="cc-field account-password-field">
            <label for="accountPassword">Mật khẩu *</label>
            <input class="cc-input" id="accountPassword" name="password" type="password" minlength="8" autocomplete="new-password">
          </div>
          <div class="cc-field">
            <label for="accountPhone">Điện thoại</label>
            <input class="cc-input" id="accountPhone" name="phone" maxlength="50" autocomplete="tel">
          </div>
          <div class="cc-field full">
            <label for="accountPosition">Chức vụ</label>
            <input class="cc-input" id="accountPosition" name="position" maxlength="190" autocomplete="organization-title">
          </div>
        </div>
        <div class="cc-form-error" id="accountFormError"></div>
        <div class="cc-modal-footer">
          <button class="cc-btn" type="button" id="cancelAccountButton">Hủy</button>
          <button class="cc-btn primary" type="submit" id="saveAccountButton"><i class="fa-solid fa-floppy-disk"></i>Lưu</button>
        </div>
      </form>
    </div>
  </div>

  <div class="cc-modal-backdrop" id="passwordModal" role="dialog" aria-modal="true" aria-labelledby="passwordModalTitle">
    <div class="cc-modal">
      <div class="cc-modal-header">
        <h2 class="cc-modal-title" id="passwordModalTitle">Đặt lại mật khẩu</h2>
        <button class="cc-btn" type="button" id="closePasswordModalButton" aria-label="Đóng"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="passwordForm" novalidate>
        <div class="cc-form">
          <input type="hidden" id="passwordAccountId">
          <div class="cc-field full">
            <label for="newPassword">Mật khẩu mới *</label>
            <input class="cc-input" id="newPassword" name="password" type="password" minlength="8" required autocomplete="new-password">
          </div>
        </div>
        <div class="cc-form-error" id="passwordFormError"></div>
        <div class="cc-modal-footer">
          <button class="cc-btn" type="button" id="cancelPasswordButton">Hủy</button>
          <button class="cc-btn primary" type="submit" id="savePasswordButton"><i class="fa-solid fa-key"></i>Cập nhật</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const sections = {
      dashboard: document.getElementById('dashboardSection'),
      executive: document.getElementById('executiveSection'),
      units: document.getElementById('unitsSection'),
      tenants: document.getElementById('tenantsSection'),
      accounts: document.getElementById('accountsSection'),
      permissions: document.getElementById('permissionsSection'),
      monitoring: document.getElementById('monitoringSection'),
      audit: document.getElementById('auditSection'),
      configuration: document.getElementById('configurationSection'),
      notifications: document.getElementById('notificationsSection'),
      ai: document.getElementById('aiSection')
    };
    const sectionTitles = {
      dashboard: 'Tổng quan',
      executive: 'Bảng điều hành',
      units: 'Đơn vị',
      tenants: 'Tenant',
      accounts: 'Người dùng',
      permissions: 'Phân quyền',
      monitoring: 'Giám sát',
      audit: 'Nhật ký',
      configuration: 'Cấu hình',
      notifications: 'Thông báo',
      ai: 'Trợ lý thông minh'
    };

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
      loading: false,
      installerJobId: null,
      wizardStep: 1,
      databaseReady: false,
      preflightReady: false,
      createdJob: null,
      installerPollTimer: null
    };
    const tenantState = {
      items: [],
      editing: null,
      detail: null,
      activityTarget: null,
      page: 1,
      perPage: 25,
      total: 0,
      totalPages: 1,
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
    const controlCenterClickEvent = 'click';
    const controlCenterActions = new Map();

    function registerControlCenterAction(action, handler) {
      controlCenterActions.set(action, handler);
    }

    function bindControlCenterAction(element, action, data = {}) {
      if (!element) return element;
      element.dataset.ccAction = action;
      Object.entries(data).forEach(([key, value]) => {
        if (value !== undefined && value !== null) element.dataset[key] = String(value);
      });
      return element;
    }

    function bindControlCenterElementAction(id, action, data = {}) {
      return bindControlCenterAction(document.getElementById(id), action, data);
    }

    function controlCenterItemById(items, id) {
      return (items || []).find((item) => String(item.id) === String(id));
    }

    function controlCenterActionUnit(dataset) {
      if (dataset.unitId) {
        const unit = controlCenterItemById(unitState.items, dataset.unitId);
        if (unit) return unit;
      }
      return {
        id: dataset.unitId || '',
        code: dataset.unitCode || '',
        name: dataset.unitName || '',
        domain: dataset.unitDomain || ''
      };
    }

    function controlCenterActionTenant(dataset) {
      return controlCenterItemById(tenantState.items, dataset.tenantId);
    }

    function controlCenterActionAccount(dataset) {
      return controlCenterItemById(accountState.items, dataset.accountId);
    }

    function handleControlCenterAction(event) {
      const target = event.target.closest && event.target.closest('[data-cc-action]');
      if (!target || target.disabled) return;
      if (target.dataset.ccAction.endsWith('Backdrop') && target !== event.target) return;
      const handler = controlCenterActions.get(target.dataset.ccAction);
      if (!handler) return;
      event.preventDefault();
      handler({ event, target, dataset: target.dataset });
    }

    document.addEventListener(controlCenterClickEvent, handleControlCenterAction);
    const auditState = { items: [] };
    const configurationState = { data: null, activeTab: 'general', saving: false, assetFiles: {} };

    const roleLabels = {
      SYSTEM_ADMIN: 'Quản trị hệ thống',
      VILLAGE_ADMIN: 'Quản trị thôn',
      STAFF: 'Cán bộ nhập liệu',
      VIEWER: 'Chỉ xem',
      COMMUNE_ADMIN: 'Quản trị xã'
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
      if (value === 'LOCKED' || value === 'DEGRADED' || value === 'UNKNOWN' || value === 'NOT_APPLICABLE' || value === 'MEDIUM' || value === 'MAINTENANCE') span.classList.add('warn');
      if (value === 'ERROR' || value === 'DISCONNECTED' || value === 'OFFLINE' || value === 'INVALID' || value === 'HIGH' || value === 'DELETED' || value === 'FAILED') span.classList.add('danger');
      span.textContent = statusLabel(value);
      return span;
    }

    function statusLabel(value) {
      const labels = {
        UNKNOWN: 'Chưa kiểm tra',
        OK: 'Bình thường',
        ONLINE: 'Trực tuyến',
        OFFLINE: 'Ngoại tuyến',
        CONNECTED: 'Database OK',
        DISCONNECTED: 'Cơ sở dữ liệu lỗi',
        LOCKED: 'Đã khóa',
        VALID: 'SSL hợp lệ',
        INVALID: 'SSL lỗi',
        DEGRADED: 'Suy giảm',
        INFO: 'Thông tin',
        WARN: 'Cảnh báo',
        ERROR: 'Lỗi',
        NOT_APPLICABLE: 'Không áp dụng',
        READY: 'Sẵn sàng',
        DELETED: 'Đã xóa mềm',
        CREATING: 'Đang tạo',
        FAILED: 'Lỗi',
        DISABLED: 'Đã khóa',
        MAINTENANCE: 'Bảo trì',
        DRY_RUN_PASSED: 'Chạy thử đạt',
        WAITING_MANUAL: 'Chờ thao tác thủ công',
        RUNNING: 'Đang chạy',
        DONE: 'Hoàn tất',
        ROLLED_BACK: 'Đã hoàn tác',
        ACTIVE: 'Đang hoạt động',
        INACTIVE: 'Đã khóa',
        HIGH: 'Cao',
        MEDIUM: 'Trung bình',
        LOW: 'Thấp'
      };
      return labels[value] || value || 'Chưa kiểm tra';
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
      document.getElementById('currentUserLabel').textContent = loggedIn ? `${user.displayName || user.email} - ${roleLabels[user.role] || user.role}` : 'Chưa đăng nhập';
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
      if (!payload.ok) throw new Error(payload.message || 'Yêu cầu không thành công');
      return payload.data;
    }

    async function login(event) {
      event.preventDefault();
      const button = document.getElementById('loginButton');
      const error = document.getElementById('loginError');
      const username = formValue('loginUsername');
      const password = formValue('loginPassword');
      if (!username || !password) {
        error.textContent = 'Vui lòng nhập tài khoản và mật khẩu';
        return;
      }
      button.disabled = true;
      error.textContent = '';
      try {
        const result = await api('/api/control-center/login', { method: 'POST', body: { username, password } });
        setSession(result);
        await loadControlCenter();
      } catch (loginError) {
        error.textContent = loginError.message || 'Đăng nhập không thành công';
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
        ['Tổng số đơn vị', nf.format(data.totalUnits), 'Đơn vị đang quản lý'],
        ['Đơn vị đang hoạt động', nf.format(data.activeUnits || 0), 'Theo registry đơn vị'],
        ['Trang web trực tuyến', nf.format(data.websiteOnlineUnits || 0), 'Đơn vị có trang web đang truy cập được'],
        ['Cơ sở dữ liệu OK', nf.format(data.databaseConnectedUnits || 0), 'Đơn vị kết nối cơ sở dữ liệu thành công'],
        ['Đơn vị lỗi trang web', nf.format(data.websiteOfflineUnits || 0), 'Cần kiểm tra tên miền/máy chủ lưu trữ'],
        ['Đơn vị lỗi cơ sở dữ liệu', nf.format(data.databaseDisconnectedUnits || 0), 'Cần kiểm tra cấu hình cơ sở dữ liệu'],
        ['Tổng hộ', nf.format(data.totalHouseholds), 'Tổng hợp toàn hệ thống'],
        ['Tổng người dùng', nf.format(accountState.items.length), 'Tài khoản trong Community Control Center'],
        ['Tổng trẻ em', nf.format(data.totalChildren), 'Số liệu tổng hợp'],
        ['Tổng người cao tuổi', nf.format(data.totalElderly), 'Theo cấu hình chính sách hiện có'],
        ['Tổng lao động', nf.format(data.totalWorkers), 'Theo trường lao động hiện có'],
        ['Tổng Đảng viên', nf.format(data.totalPartyMembers), 'Số liệu tổng hợp'],
        ['Tổng tỷ lệ BHYT', percent(data.healthInsuranceRate), 'Trên nhân khẩu còn sống']
      ];
      const grid = document.getElementById('metricGrid');
      grid.replaceChildren(...metrics.map((item) => metric(item[0], item[1], item[2])));
      renderOperations(data.operations || []);
      renderRecentActivity(data.recentActivity || []);
      renderExecutiveDashboard(data);
    }

    function renderExecutiveDashboard(data) {
      const grid = document.getElementById('executiveMetricGrid');
      if (!grid) return;
      const healthGrid = document.getElementById('executiveHealthGrid');
      const executiveMetrics = [
        ['Đơn vị đang hoạt động', nf.format(data.activeUnits || 0), 'Đơn vị đang ở trạng thái hoạt động'],
        ['Trang web trực tuyến', nf.format(data.websiteOnlineUnits || 0), 'Trang web truy cập được qua HTTPS'],
        ['Database OK', nf.format(data.databaseConnectedUnits || 0), 'Database kết nối thành công'],
        ['Cần xử lý', nf.format((data.operations || []).length), 'Cảnh báo vận hành cần theo dõi']
      ];
      const healthItems = [
        ['Tổng đơn vị', nf.format(data.totalUnits || 0)],
        ['Đơn vị bị khóa', nf.format(data.lockedUnits || 0)],
        ['Trang web lỗi', nf.format(data.websiteOfflineUnits || 0)],
        ['Cơ sở dữ liệu lỗi', nf.format(data.databaseDisconnectedUnits || 0)],
        ['Bản sao lưu gần nhất', data.latestBackupAt || 'Chưa có dữ liệu'],
        ['Phiên bản', formatVersions(data.versions)]
      ];
      grid.replaceChildren(...executiveMetrics.map((item) => metric(item[0], item[1], item[2])));
      if (healthGrid) healthGrid.replaceChildren(...healthItems.map(([label, value]) => metric(label, value || '-', '')));
    }

    function formatVersions(versions) {
      if (!Array.isArray(versions) || versions.length === 0) return '-';
      return versions.map((item) => {
        if (typeof item === 'string') return item;
        if (!item || typeof item !== 'object') return String(item || '-');
        return item.version || item.appVersion || item.buildVersion || item.code || item.name || '-';
      }).filter((item) => item && item !== '-').join(', ') || '-';
    }

    function renderOperations(items) {
      const holder = document.getElementById('operationsList');
      if (!items.length) {
        holder.replaceChildren(stateMessage('Hôm nay chưa có việc cần xử lý ngay.'));
        return;
      }
      holder.replaceChildren(...items.map((item) => {
        const row = document.createElement('div');
        row.className = 'operation-item';
        const main = document.createElement('div');
        const title = document.createElement('div');
        title.className = 'operation-title';
        title.textContent = (item.message || 'Cần xử lý') + ' - ' + (item.tenant?.name || item.tenant?.code || 'Đơn vị');
        const meta = document.createElement('div');
        meta.className = 'cc-meta';
        meta.textContent = 'Mức độ: ' + statusLabel(item.severity) + ' | Người phụ trách: ' + (item.tenant?.manager || 'Chưa gán');
        main.append(title, meta);
        const actions = document.createElement('div');
        actions.className = 'operation-actions';
        const unit = operationUnit(item);
        if (item.primaryAction === 'check_website') {
          const check = actionButton('Kiểm tra trang web', 'fa-globe');
          bindControlCenterAction(check, 'unit.checkWebsite', {
            unitId: unit.id,
            unitCode: unit.code,
            unitName: unit.name,
            unitDomain: unit.domain
          });
          actions.appendChild(check);
        } else if (item.primaryAction === 'check_database') {
          const check = actionButton('Kiểm tra cơ sở dữ liệu', 'fa-database');
          bindControlCenterAction(check, 'unit.checkDatabase', {
            unitId: unit.id,
            unitCode: unit.code,
            unitName: unit.name,
            unitDomain: unit.domain
          });
          actions.appendChild(check);
        }
        const view = actionButton('Xem đơn vị', 'fa-sitemap');
        bindControlCenterAction(view, 'unit.focus', {
          unitCode: item.tenant?.code || '',
          unitName: item.tenant?.name || ''
        });
        actions.appendChild(view);
        if (item.tenant?.domain) {
          const portal = actionButton('Mở cổng đơn vị', 'fa-arrow-up-right-from-square');
          bindControlCenterAction(portal, 'unit.portal', {
            unitId: unit.id,
            unitCode: unit.code,
            unitName: unit.name,
            unitDomain: unit.domain
          });
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
        holder.replaceChildren(stateMessage('Chưa có hoạt động quản trị gần đây.'));
        return;
      }
      holder.replaceChildren(...items.map((item) => {
        const row = document.createElement('div');
        row.className = 'operation-item';
        const main = document.createElement('div');
        const title = document.createElement('div');
        title.className = 'operation-title';
        title.textContent = item.message || item.action || 'Hoạt động';
        const meta = document.createElement('div');
        meta.className = 'cc-meta';
        meta.textContent = `${item.createdAt || '-'} | ${item.tenantName || 'Hệ thống'} | ${item.actor || '-'}`;
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
      body.replaceChildren(stateRow(10, 'Đang tải dữ liệu...'));
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
          const portal = actionButton('Mở cổng đơn vị', 'fa-arrow-up-right-from-square');
          bindControlCenterAction(portal, 'unit.portal', { unitId: unit.id });
          actions.appendChild(portal);
        }
        const checkWebsite = actionButton('Trang web', 'fa-globe');
        bindControlCenterAction(checkWebsite, 'unit.checkWebsite', { unitId: unit.id });
        actions.appendChild(checkWebsite);
        const checkDatabase = actionButton('Cơ sở dữ liệu', 'fa-database');
        bindControlCenterAction(checkDatabase, 'unit.checkDatabase', { unitId: unit.id });
        actions.appendChild(checkDatabase);
        const edit = actionButton('Sửa', 'fa-pen-to-square');
        bindControlCenterAction(edit, 'unit.edit', { unitId: unit.id });
        actions.appendChild(edit);
        if (unit.status === 'READY' || unit.status === 'ACTIVE') {
          const lock = actionButton('Khóa', 'fa-lock', 'danger');
          bindControlCenterAction(lock, 'unit.status', { unitId: unit.id, unitStatusAction: 'lock' });
          actions.appendChild(lock);
        } else {
          const activate = actionButton('Kích hoạt', 'fa-unlock');
          bindControlCenterAction(activate, 'unit.status', { unitId: unit.id, unitStatusAction: 'activate' });
          actions.appendChild(activate);
        }
        tr.appendChild(actions);
        return tr;
      });
      body.replaceChildren(...(rows.length ? rows : [emptyRow(10)]));
    }

    async function loadTenants() {
      const body = document.getElementById('tenantsBody');
      body.replaceChildren(stateRow(9, 'Đang tải danh sách Tenant...'));
      setTenantsAlert('');
      tenantState.loading = true;
      const params = new URLSearchParams();
      const search = document.getElementById('tenantSearch').value.trim();
      const status = document.getElementById('tenantStatusFilter').value;
      const version = document.getElementById('tenantVersionFilter').value;
      const sort = document.getElementById('tenantSort').value;
      const direction = document.getElementById('tenantDirection').value;
      if (search) params.set('search', search);
      if (status) params.set('status', status);
      if (version) params.set('version', version);
      if (sort) params.set('sort', sort);
      if (direction) params.set('direction', direction);
      params.set('page', String(tenantState.page));
      params.set('per_page', String(tenantState.perPage));
      if (status === 'DELETED') params.set('include_deleted', '1');

      try {
        const data = await api('/api/control-center/tenants?' + params.toString());
        tenantState.items = data.items || [];
        const pagination = data.pagination || {};
        tenantState.page = Number(pagination.page || tenantState.page || 1);
        tenantState.total = Number(pagination.total || tenantState.items.length || 0);
        tenantState.totalPages = Number(pagination.totalPages || 1);
        renderTenantVersionFilter();
        renderTenantRows();
        renderTenantPagination();
        applyTenantPermissions();
      } catch (error) {
        tenantState.items = [];
        body.replaceChildren(emptyRow(9));
        setTenantsAlert(error.message || 'Không tải được danh sách Tenant');
      } finally {
        tenantState.loading = false;
      }
    }

    function renderTenantRows() {
      const body = document.getElementById('tenantsBody');
      const rows = tenantState.items.map((tenant) => {
        const tr = document.createElement('tr');
        const cells = [
          tenant.code || '-',
          tenant.name || '-',
          tenant.domain || tenant.subdomain || '-',
          tenant.databaseName || '-',
          tenant.status || 'UNKNOWN',
          tenant.appVersion || tenant.buildVersion || tenant.schemaVersion || '-',
          tenantStorageLabel(tenant),
          tenant.updatedAt || tenant.lastStatusChangedAt || '-'
        ];
        cells.forEach((cell, index) => {
          const td = document.createElement('td');
          if (index === 4) td.appendChild(badge(cell));
          else td.textContent = cell;
          tr.appendChild(td);
        });
        const actions = document.createElement('td');
        actions.className = 'cc-row-actions';
        const view = actionButton('Xem', 'fa-eye');
        bindControlCenterAction(view, 'tenant.detail', { tenantId: tenant.id });
        actions.appendChild(view);
        if (canTenant('tenant.update') && tenant.status !== 'DELETED') {
          const edit = actionButton('Sửa', 'fa-pen-to-square');
          edit.dataset.tenantPermission = 'tenant.update';
          bindControlCenterAction(edit, 'tenant.edit', { tenantId: tenant.id });
          actions.appendChild(edit);
        }
        if (canTenant('tenant.lock') && !['LOCKED', 'DELETED'].includes(tenant.status)) {
          const lock = actionButton('Khóa', 'fa-lock', 'danger');
          lock.dataset.tenantPermission = 'tenant.lock';
          bindControlCenterAction(lock, 'tenant.lock', { tenantId: tenant.id });
          actions.appendChild(lock);
        }
        if (canTenant('tenant.unlock') && tenant.status === 'LOCKED') {
          const unlock = actionButton('Mở khóa', 'fa-unlock');
          unlock.dataset.tenantPermission = 'tenant.unlock';
          bindControlCenterAction(unlock, 'tenant.unlock', { tenantId: tenant.id });
          actions.appendChild(unlock);
        }
        if (canTenant('tenant.delete') && tenant.status !== 'DELETED') {
          const remove = actionButton('Xóa mềm', 'fa-trash', 'danger');
          remove.dataset.tenantPermission = 'tenant.delete';
          bindControlCenterAction(remove, 'tenant.delete', { tenantId: tenant.id });
          actions.appendChild(remove);
        }
        tr.appendChild(actions);
        return tr;
      });
      body.replaceChildren(...(rows.length ? rows : [emptyRow(9)]));
    }

    function renderTenantPagination() {
      document.getElementById('tenantsPageInfo').textContent = tenantState.total
        ? `Trang ${tenantState.page}/${tenantState.totalPages} - ${nf.format(tenantState.total)} Tenant`
        : 'Chưa có Tenant phù hợp';
      document.getElementById('tenantPrevPageButton').disabled = tenantState.page <= 1 || tenantState.loading;
      document.getElementById('tenantNextPageButton').disabled = tenantState.page >= tenantState.totalPages || tenantState.loading;
    }

    function renderTenantVersionFilter() {
      const select = document.getElementById('tenantVersionFilter');
      const current = select.value;
      const versions = Array.from(new Set((tenantState.items || []).map((tenant) => tenant.appVersion || '').filter(Boolean))).sort();
      const options = [new Option('Tất cả phiên bản', '')].concat(versions.map((version) => new Option(version, version)));
      select.replaceChildren(...options);
      select.value = versions.includes(current) ? current : '';
    }

    function tenantStorageLabel(tenant) {
      const used = tenant.storageUsageBytes;
      const quota = tenant.storageQuotaBytes;
      if (used === null && quota === null) return '-';
      if (quota) return `${formatBytes(used || 0)} / ${formatBytes(quota)}`;
      return formatBytes(used || 0);
    }

    function openTenantModal(tenant = null) {
      tenantState.editing = tenant;
      document.getElementById('tenantModalTitle').textContent = tenant ? 'Sửa Tenant' : 'Thêm Tenant';
      document.getElementById('tenantId').value = tenant?.id || '';
      document.getElementById('tenantCode').value = tenant?.code || '';
      document.getElementById('tenantCode').disabled = Boolean(tenant);
      document.getElementById('tenantName').value = tenant?.name || '';
      document.getElementById('tenantUnitName').value = tenant?.unitName || '';
      document.getElementById('tenantCommuneName').value = tenant?.communeName || '';
      document.getElementById('tenantDomain').value = tenant?.domain || '';
      document.getElementById('tenantSubdomain').value = tenant?.subdomain || '';
      document.getElementById('tenantDatabaseHost').value = tenant?.databaseHost || '';
      document.getElementById('tenantDatabaseName').value = tenant?.databaseName || '';
      document.getElementById('tenantDatabaseCharset').value = tenant?.databaseCharset || 'utf8mb4';
      document.getElementById('tenantStatus').value = ['READY', 'ACTIVE', 'MAINTENANCE', 'DISABLED', 'FAILED'].includes(tenant?.storedStatus || tenant?.status) ? (tenant?.storedStatus || tenant?.status) : 'READY';
      document.getElementById('tenantAppVersion').value = tenant?.appVersion || '';
      document.getElementById('tenantBuildVersion').value = tenant?.buildVersion || '';
      document.getElementById('tenantSchemaVersion').value = tenant?.schemaVersion || '';
      document.getElementById('tenantStorageQuotaBytes').value = tenant?.storageQuotaBytes ?? '';
      document.getElementById('tenantManagerName').value = tenant?.managerName || '';
      document.getElementById('tenantNotes').value = tenant?.notes || '';
      setTenantFormError('');
      document.getElementById('tenantModal').classList.add('active');
      document.getElementById(tenant ? 'tenantName' : 'tenantCode').focus();
    }

    function closeTenantModal() {
      document.getElementById('tenantModal').classList.remove('active');
      tenantState.editing = null;
    }

    function tenantPayload() {
      const payload = {
        name: formValue('tenantName'),
        unit_name: formValue('tenantUnitName') || null,
        commune_name: formValue('tenantCommuneName') || null,
        domain: formValue('tenantDomain') || null,
        subdomain: formValue('tenantSubdomain') || null,
        database_host: formValue('tenantDatabaseHost') || null,
        database_name: formValue('tenantDatabaseName') || null,
        database_charset: formValue('tenantDatabaseCharset') || null,
        status: formValue('tenantStatus') || 'READY',
        app_version: formValue('tenantAppVersion') || null,
        build_version: formValue('tenantBuildVersion') || null,
        schema_version: formValue('tenantSchemaVersion') || null,
        storage_quota_bytes: formValue('tenantStorageQuotaBytes') || null,
        manager_name: formValue('tenantManagerName') || null,
        notes: formValue('tenantNotes') || null
      };
      if (!tenantState.editing) payload.code = formValue('tenantCode').toLowerCase();
      return payload;
    }

    function validateTenantForm(payload) {
      if (!tenantState.editing && !/^[a-z0-9_-]{2,50}$/.test(payload.code || '')) return 'Mã Tenant không hợp lệ';
      if (!payload.name || payload.name.length > 190) return 'Tên Tenant là bắt buộc và không vượt quá 190 ký tự';
      if (!payload.domain && !payload.subdomain) return 'Tenant cần có domain hoặc subdomain';
      if (!payload.database_host) return 'Database host là bắt buộc';
      if (!payload.database_name || !/^[a-zA-Z0-9_]{1,190}$/.test(payload.database_name)) return 'Database name không hợp lệ';
      if (payload.database_charset && !/^[a-z0-9_]{1,50}$/.test(payload.database_charset)) return 'Database charset không hợp lệ';
      if (payload.storage_quota_bytes !== null && (!/^\d+$/.test(String(payload.storage_quota_bytes)) || Number(payload.storage_quota_bytes) < 0)) return 'Dung lượng giới hạn không hợp lệ';
      return '';
    }

    async function saveTenant(event) {
      event.preventDefault();
      const button = document.getElementById('saveTenantButton');
      const payload = tenantPayload();
      const validation = validateTenantForm(payload);
      if (validation) {
        setTenantFormError(validation);
        return;
      }
      button.disabled = true;
      setTenantFormError('');
      try {
        if (tenantState.editing) {
          await api('/api/control-center/tenants/' + encodeURIComponent(tenantState.editing.id), { method: 'PUT', body: payload });
          setTenantsAlert('Đã cập nhật Tenant');
        } else {
          await api('/api/control-center/tenants', { method: 'POST', body: payload });
          tenantState.page = 1;
          setTenantsAlert('Đã thêm Tenant');
        }
        closeTenantModal();
        await loadTenants();
      } catch (error) {
        setTenantFormError(error.message || 'Không lưu được Tenant');
      } finally {
        button.disabled = false;
      }
    }

    async function openTenantDetail(tenant) {
      tenantState.detail = tenant;
      tenantState.activityTarget = tenant;
      document.getElementById('tenantDetailTitle').textContent = 'Chi tiết Tenant - ' + (tenant.name || tenant.code || tenant.id);
      renderTenantDetail(tenant);
      document.getElementById('tenantDetailModal').classList.add('active');
      await loadTenantActivity(tenant).catch((error) => {
        document.getElementById('tenantActivityBody').replaceChildren(stateRow(5, error.message || 'Không tải được Activity'));
      });
    }

    function renderTenantDetail(tenant) {
      const items = [
        ['Mã Tenant', tenant.code || '-'],
        ['Tên Tenant', tenant.name || '-'],
        ['Tên đơn vị', tenant.unitName || '-'],
        ['Xã/Phường', tenant.communeName || '-'],
        ['Domain', tenant.domain || '-'],
        ['Subdomain', tenant.subdomain || '-'],
        ['Database', tenant.databaseName || '-'],
        ['Trạng thái', statusLabel(tenant.status || '')],
        ['Phiên bản ứng dụng', tenant.appVersion || '-'],
        ['Phiên bản build', tenant.buildVersion || '-'],
        ['Phiên bản schema', tenant.schemaVersion || '-'],
        ['Dung lượng', tenantStorageLabel(tenant)],
        ['Trang web', statusLabel(tenant.websiteStatus || 'UNKNOWN')],
        ['Database status', statusLabel(tenant.databaseStatus || 'UNKNOWN')],
        ['SSL', statusLabel(tenant.sslStatus || 'UNKNOWN')],
        ['Người quản lý', tenant.managerName || '-'],
        ['Cập nhật', tenant.updatedAt || '-'],
        ['Ghi chú', tenant.notes || '-', 'full']
      ];
      const nodes = items.map(([label, value, wide]) => {
        const item = document.createElement('div');
        item.className = 'tenant-detail-item' + (wide ? ' full' : '');
        const title = document.createElement('div');
        title.className = 'tenant-detail-label';
        title.textContent = label;
        const content = document.createElement('div');
        content.className = 'tenant-detail-value';
        content.textContent = value;
        item.append(title, content);
        return item;
      });
      document.getElementById('tenantDetailGrid').replaceChildren(...nodes);
    }

    async function loadTenantActivity(tenant = tenantState.activityTarget) {
      if (!tenant) return;
      const body = document.getElementById('tenantActivityBody');
      body.replaceChildren(stateRow(5, 'Đang tải Activity...'));
      const data = await api('/api/control-center/tenants/' + encodeURIComponent(tenant.id) + '/activity');
      const rows = (data.items || []).map((item) => {
        const tr = document.createElement('tr');
        [item.createdAt || '-', item.actor || '-', item.action || '-', item.severity || 'INFO', item.message || '-'].forEach((value, index) => {
          const td = document.createElement('td');
          if (index === 3) td.appendChild(badge(value));
          else td.textContent = value;
          tr.appendChild(td);
        });
        return tr;
      });
      body.replaceChildren(...(rows.length ? rows : [emptyRow(5)]));
    }

    function closeTenantDetail() {
      document.getElementById('tenantDetailModal').classList.remove('active');
      tenantState.detail = null;
      tenantState.activityTarget = null;
    }

    async function lockTenant(tenant) {
      const reason = prompt('Nhập lý do khóa Tenant');
      if (reason === null) return;
      if (!reason.trim()) {
        setTenantsAlert('Lý do khóa Tenant là bắt buộc');
        return;
      }
      try {
        await api('/api/control-center/tenants/' + encodeURIComponent(tenant.id) + '/lock', { method: 'PATCH', body: { reason: reason.trim() } });
        setTenantsAlert('Đã khóa Tenant');
        await loadTenants();
      } catch (error) {
        setTenantsAlert(error.message || 'Không khóa được Tenant');
      }
    }

    async function unlockTenant(tenant) {
      if (!confirm('Xác nhận mở khóa Tenant này?')) return;
      try {
        await api('/api/control-center/tenants/' + encodeURIComponent(tenant.id) + '/unlock', { method: 'PATCH', body: { targetStatus: 'ACTIVE' } });
        setTenantsAlert('Đã mở khóa Tenant');
        await loadTenants();
      } catch (error) {
        setTenantsAlert(error.message || 'Không mở khóa được Tenant');
      }
    }

    async function deleteTenant(tenant) {
      const confirmation = prompt('Nhập mã Tenant để xác nhận xóa mềm');
      if (confirmation === null) return;
      if (confirmation.trim() !== tenant.code) {
        setTenantsAlert('Mã Tenant xác nhận không khớp');
        return;
      }
      try {
        await api('/api/control-center/tenants/' + encodeURIComponent(tenant.id), { method: 'DELETE', body: { confirmation: confirmation.trim() } });
        setTenantsAlert('Đã xóa mềm Tenant');
        await loadTenants();
      } catch (error) {
        setTenantsAlert(error.message || 'Không xóa mềm được Tenant');
      }
    }

    async function loadAccounts() {
      const body = document.getElementById('accountsBody');
      body.replaceChildren(stateRow(10, 'Đang tải dữ liệu...'));
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

          [roleLabels[account.role] || account.role, account.unitName || '-', account.status, account.lastLoginLabel || account.lastLoginAt || 'Chưa đăng nhập', account.lastIp || '-', account.lastDevice || '-', account.createdAt || '-', account.createdBy || '-'].forEach((cell, index) => {
            const td = document.createElement('td');
            if (index === 2) td.appendChild(badge(cell));
            else td.textContent = cell;
            tr.appendChild(td);
          });

          const actions = document.createElement('td');
          actions.className = 'cc-row-actions';
          const view = actionButton('Xem', 'fa-eye');
          bindControlCenterAction(view, 'account.view', { accountId: account.id });
          actions.appendChild(view);
          const edit = actionButton('Sửa', 'fa-user-pen');
          bindControlCenterAction(edit, 'account.edit', { accountId: account.id });
          actions.appendChild(edit);
          const password = actionButton('Mật khẩu', 'fa-key');
          bindControlCenterAction(password, 'account.password', { accountId: account.id });
          actions.appendChild(password);
          if (account.status === 'ACTIVE') {
            const deactivate = actionButton('Ngừng', 'fa-user-slash', 'danger');
            bindControlCenterAction(deactivate, 'account.status', { accountId: account.id, accountStatusAction: 'deactivate' });
            actions.appendChild(deactivate);
          } else {
            const activate = actionButton('Kích hoạt', 'fa-user-check');
            bindControlCenterAction(activate, 'account.status', { accountId: account.id, accountStatusAction: 'activate' });
            actions.appendChild(activate);
          }
          tr.appendChild(actions);
          return tr;
        });
        body.replaceChildren(...(rows.length ? rows : [emptyRow(10)]));
      } catch (error) {
        body.replaceChildren(emptyRow(10));
        setAccountsAlert(error.message || 'Không tải được danh sách tài khoản');
      }
    }

    async function loadPermissions() {
      const head = document.getElementById('permissionsHead');
      const body = document.getElementById('permissionsBody');
      body.replaceChildren(stateRow(2, 'Đang tải phân quyền...'));
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
        applyTenantPermissions();
        setPermissionsAlert('');
      } catch (error) {
        head.replaceChildren();
        body.replaceChildren(stateRow(2, 'Không tải được phân quyền'));
        setPermissionsAlert(error.message || 'Không tải được phân quyền');
      }
    }

    function renderPermissionRoleFilter() {
      const select = document.getElementById('permissionRoleFilter');
      const current = select.value;
      const options = [new Option('Tất cả vai trò', '')].concat(permissionState.roles.map((role) => new Option(role.label || role.role, role.role)));
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
        bindControlCenterAction(button, 'permission.group', { permissionGroupId: group.id });
        return button;
      });
      holder.replaceChildren(...(groups.length ? groups : [stateMessage('Chưa có quyền')]));
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
      ['Quyền'].concat(roles.map((role) => role.label || role.role)).forEach((label) => {
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
          checkbox.title = item.locked ? 'Quyền cốt lõi không thể tắt' : '';
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

    function currentPlatformRole() {
      const role = (window.App?.user?.role || '').toUpperCase();
      return {
        SUPER_ADMIN: 'SYSTEM_ADMIN',
        ADMIN: 'VILLAGE_ADMIN',
        OFFICER: 'STAFF',
        VIEWER: 'VIEWER',
        SYSTEM_ADMIN: 'SYSTEM_ADMIN',
        VILLAGE_ADMIN: 'VILLAGE_ADMIN',
        STAFF: 'STAFF'
      }[role] || role;
    }

    function canTenant(permission) {
      if (!permission) return true;
      const role = currentPlatformRole();
      if (role === 'SYSTEM_ADMIN') return true;
      const item = permissionState.matrix.find((entry) => entry.role === role && entry.permission === permission);
      return Boolean(item?.allowed);
    }

    function applyTenantPermissions() {
      document.querySelectorAll('[data-tenant-permission]').forEach((element) => {
        const allowed = canTenant(element.dataset.tenantPermission);
        element.hidden = !allowed;
        if ('disabled' in element) element.disabled = !allowed;
      });
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
        setPermissionsAlert(error.message || 'Không lưu được phân quyền');
      } finally {
        button.disabled = permissionState.pending.size === 0;
      }
    }

    async function loadMonitoring() {
      const data = await api('/api/control-center/monitoring');
      const usedBytes = Math.max(0, Number(data.storage.totalBytes || 0) - Number(data.storage.freeBytes || 0));
      const items = [
        ['Phiên bản', data.version],
        ['Môi trường chạy', `PHP ${data.runtime.phpVersion}`],
        ['Trạng thái cơ sở dữ liệu', data.database.ok ? 'Đã kết nối' : 'Không khả dụng'],
        ['Lưu trữ', `${formatBytes(usedBytes)} / ${formatBytes(data.storage.totalBytes)}`],
        ['Quyền ghi lưu trữ', data.storage.writable ? 'Bình thường' : 'Suy giảm'],
        ['Kiểm tra sức khỏe', data.healthCheck.status]
      ];
      document.getElementById('healthBadge').textContent = statusLabel(data.healthCheck.status);
      document.getElementById('healthBadge').className = data.healthCheck.status === 'OK' ? 'cc-badge' : 'cc-badge warn';
      const tenantPanel = document.createElement('div');
      tenantPanel.className = 'cc-panel full';
      const header = document.createElement('div');
      header.className = 'cc-panel-header';
      const title = document.createElement('h2');
      title.className = 'cc-panel-title';
      title.textContent = 'Trạng thái đơn vị';
      header.appendChild(title);
      const tableWrap = document.createElement('div');
      tableWrap.className = 'cc-table-wrap';
      const table = document.createElement('table');
      table.className = 'cc-table';
      const head = document.createElement('thead');
      const headRow = document.createElement('tr');
        ['Đơn vị', 'Tên miền', 'Trang web', 'Cơ sở dữ liệu', 'SSL', 'Phiên bản', 'Lần kiểm tra', 'Lỗi gần nhất'].forEach((label) => {
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
      body.replaceChildren(stateRow(6, 'Đang tải nhật ký...'));
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
      const options = [new Option('Tất cả đơn vị', '')].concat((unitState.items || []).map((unit) => new Option(unit.name || unit.code, unit.id)));
      select.replaceChildren(...options);
      select.value = current;
    }

    function emptyRow(colspan) {
      return stateRow(colspan, 'Chưa có dữ liệu hiển thị');
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

    function viewAccount(account) {
      alert([
        'Tên: ' + (account.displayName || account.username || account.email || '-'),
        'Thư điện tử: ' + (account.email || '-'),
        'Đơn vị: ' + (account.unitName || '-'),
        'Vai trò: ' + (roleLabels[account.role] || account.role || '-'),
        'Trạng thái: ' + statusLabel(account.status || ''),
        'Đăng nhập cuối: ' + (account.lastLoginLabel || account.lastLoginAt || 'Chưa đăng nhập'),
        'Người tạo: ' + (account.createdBy || '-')
      ].join('\n'));
    }

    function setUnitsAlert(message) {
      const alert = document.getElementById('unitsAlert');
      alert.textContent = message || '';
      alert.classList.toggle('active', Boolean(message));
    }

    function setTenantsAlert(message) {
      const alert = document.getElementById('tenantsAlert');
      alert.textContent = message || '';
      alert.classList.toggle('active', Boolean(message));
    }

    function setTenantFormError(message) {
      const error = document.getElementById('tenantFormError');
      error.textContent = message || '';
      error.classList.toggle('active', Boolean(message));
    }

    function setTenantInstallerActions(job) {
      const holder = document.getElementById('tenantInstallerActions');
      const workflowStatus = job?.workflowStatus || '';
      const canAct = job && (['FAILED', 'WAITING_MANUAL'].includes(job.status) || ['Failed', 'Waiting'].includes(workflowStatus));
      unitState.installerJobId = canAct ? job.id : null;
      holder.style.display = canAct ? 'flex' : 'none';
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
      document.getElementById('unitModalTitle').textContent = unit ? 'Sửa đơn vị' : 'Thêm đơn vị';
      document.getElementById('unitId').value = unit?.id || '';
      document.getElementById('unitCode').value = unit?.code || '';
      document.getElementById('unitCode').disabled = Boolean(unit);
      document.getElementById('unitName').value = unit?.name || '';
      document.getElementById('unitCommuneName').value = unit?.communeName || '';
      document.getElementById('unitDomain').value = unit?.domain || '';
      document.getElementById('unitSubdomain').value = unit?.subdomain || '';
      document.getElementById('unitDatabaseHost').value = unit?.databaseHost || '';
      document.getElementById('unitDatabaseName').value = unit?.databaseName || '';
      document.getElementById('unitDatabaseUsername').value = '';
      document.getElementById('unitDatabasePassword').value = '';
      document.getElementById('unitDatabaseCharset').value = unit?.databaseCharset || 'utf8mb4';
      document.getElementById('unitAppVersion').value = unit?.appVersion || unit?.version || '';
      document.getElementById('unitBuildVersion').value = unit?.buildVersion || '';
      document.getElementById('unitSchemaVersion').value = unit?.schemaVersion || '';
      document.getElementById('unitManagerName').value = unit?.manager === 'Chưa gán' || unit?.manager === 'Chua gan' ? '' : (unit?.manager || '');
      document.getElementById('unitLogo').value = unit?.logo || '';
      document.getElementById('unitNotes').value = unit?.notes || '';
      document.getElementById('unitStatus').value = unit?.status || 'READY';
      document.getElementById('unitDatabaseUsername').closest('.cc-field').style.display = unit ? 'none' : '';
      document.getElementById('unitDatabasePassword').closest('.cc-field').style.display = unit ? 'none' : '';
      unitState.wizardStep = 1;
      unitState.databaseReady = Boolean(unit);
      unitState.preflightReady = Boolean(unit);
      unitState.createdJob = null;
      document.getElementById('preflightUnitButton').style.display = unit ? 'none' : '';
      document.getElementById('saveUnitButton').disabled = !unit;
      document.getElementById('saveUnitButton').querySelector('span')?.remove();
      document.getElementById('saveUnitButton').innerHTML = '<i class="fa-solid fa-floppy-disk"></i>' + (unit ? 'Lưu' : 'Tạo đơn vị');
      setFormError('');
      renderDatabaseCheck(null);
      renderPreflight(null);
      renderInfrastructureVerification(null);
      document.getElementById('tenantCreatePanel').textContent = 'Sẵn sàng tạo đơn vị';
      document.getElementById('tenantCreatePanel').classList.remove('active');
      document.getElementById('tenantInstallStatusBadge').textContent = 'Pending';
      document.getElementById('tenantInstallStatusBadge').className = 'cc-badge warn';
      document.getElementById('tenantInstallProgressBar').style.width = '0%';
      document.getElementById('tenantInstallLogList').replaceChildren();
      document.getElementById('tenantHealthPanel').textContent = 'Chưa chạy kiểm tra sau cài đặt';
      document.getElementById('tenantHealthPanel').classList.remove('active');
      setTenantInstallerActions(null);
      updateTenantWizard();
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
        database_username: formValue('unitDatabaseUsername') || null,
        database_password: formValue('unitDatabasePassword') || null,
        database_charset: formValue('unitDatabaseCharset') || null,
        app_version: formValue('unitAppVersion') || null,
        build_version: formValue('unitBuildVersion') || null,
        schema_version: formValue('unitSchemaVersion') || null,
        manager_name: formValue('unitManagerName') || null,
        notes: formValue('unitNotes') || null,
        logo: formValue('unitLogo') || null,
        status: formValue('unitStatus') || 'READY',
        type: 'VILLAGE'
      };
      if (!unitState.editing) payload.code = formValue('unitCode').toLowerCase();
      return payload;
    }

    function validateUnitForm(payload) {
      if (!unitState.editing && !/^[a-z0-9_-]{2,50}$/.test(payload.code || '')) {
        return 'Mã đơn vị chỉ gồm chữ thường, số, dấu gạch ngang/gạch dưới và từ 2 đến 50 ký tự';
      }
      if (!payload.name || payload.name.length > 190) {
        return 'Tên đơn vị là bắt buộc và không vượt quá 190 ký tự';
      }
      if (payload.database_name && !/^[a-zA-Z0-9_]{1,190}$/.test(payload.database_name)) {
        return 'Tên cơ sở dữ liệu chỉ gồm chữ, số và dấu gạch dưới';
      }
      if (!unitState.editing && !payload.database_username) {
        return 'Người dùng cơ sở dữ liệu là bắt buộc để khởi tạo đơn vị';
      }
      if (payload.database_charset && !/^[a-z0-9_]{1,50}$/.test(payload.database_charset)) {
        return 'Bảng mã cơ sở dữ liệu không hợp lệ';
      }
      return '';
    }

    function validateTenantWizardStep(payload, step) {
      if (step === 1) {
        if (!unitState.editing && !/^[a-z0-9_-]{2,50}$/.test(payload.code || '')) {
          return 'Mã đơn vị chỉ gồm chữ thường, số, dấu gạch ngang/gạch dưới và từ 2 đến 50 ký tự';
        }
        if (!payload.name || payload.name.length > 190) {
          return 'Tên đơn vị là bắt buộc và không vượt quá 190 ký tự';
        }
        if (!payload.domain && !payload.subdomain) {
          return 'Tên miền hoặc tên miền phụ là bắt buộc';
        }
      }
      if (step === 2) {
        if (!payload.database_host) return 'Máy chủ cơ sở dữ liệu là bắt buộc';
        if (!payload.database_name || !/^[a-zA-Z0-9_]{1,190}$/.test(payload.database_name)) {
          return 'Tên cơ sở dữ liệu chỉ gồm chữ, số và dấu gạch dưới';
        }
        if (!payload.database_username) return 'Người dùng cơ sở dữ liệu là bắt buộc';
        if (!payload.database_password) return 'Mật khẩu cơ sở dữ liệu là bắt buộc';
        if (payload.database_charset && !/^[a-z0-9_]{1,50}$/.test(payload.database_charset)) {
          return 'Bảng mã cơ sở dữ liệu không hợp lệ';
        }
      }
      return '';
    }

    function setFormError(message) {
      const error = document.getElementById('unitFormError');
      error.textContent = message || '';
      error.classList.toggle('active', Boolean(message));
    }

    function renderChecklist(result, panelId, statusId, listId, readyText, idleText) {
      const panel = document.getElementById(panelId);
      const status = document.getElementById(statusId);
      const list = document.getElementById(listId);
      list.innerHTML = '';
      if (!result) {
        if (!panel.classList.contains('wizard-page')) panel.classList.remove('active');
        status.textContent = idleText;
        status.className = 'preflight-status failed';
        return;
      }
      panel.classList.add('active');
      status.textContent = result.ready ? readyText : 'Lỗi';
      status.className = 'preflight-status ' + (result.ready ? 'ready' : 'failed');
      (result.items || []).forEach((item) => {
        const row = document.createElement('div');
        row.className = 'preflight-item';
        const icon = document.createElement('div');
        icon.className = 'preflight-icon ' + (item.status === 'PASS' ? 'pass' : 'fail');
        icon.textContent = item.status === 'PASS' ? '✓' : '!';
        const label = document.createElement('div');
        label.textContent = item.label || item.key || '';
        const detail = document.createElement('div');
        detail.textContent = item.status === 'PASS' ? (item.message || 'Đạt') : ((item.message || 'Không đạt') + (item.fix ? ' - ' + item.fix : ''));
        if (item.status !== 'PASS') detail.className = 'preflight-fix';
        row.append(icon, label, detail);
        list.appendChild(row);
      });
    }

    function renderDatabaseCheck(result) {
      renderChecklist(result, 'tenantDatabasePanel', 'tenantDatabaseStatus', 'tenantDatabaseList', 'Cơ sở dữ liệu sẵn sàng', 'Chưa kiểm tra cơ sở dữ liệu');
    }

    function renderPreflight(result) {
      renderChecklist(result, 'tenantPreflightPanel', 'tenantPreflightStatus', 'tenantPreflightList', 'Sẵn sàng tạo đơn vị', 'Chưa chạy tiền kiểm');
    }

    function renderInfrastructureVerification(result) {
      const keys = ['installation_profile', 'source_writable', 'storage_writable', 'upload_writable', 'backup_writable'];
      const items = (result?.items || []).filter((item) => keys.includes(item.key));
      renderChecklist({ ready: Boolean(result?.ready), items }, 'tenantInfrastructurePanel', 'tenantInfrastructureStatus', 'tenantInfrastructureList', 'Hạ tầng đã sẵn sàng', 'Chưa xác minh hạ tầng');
    }

    function redactInstallerText(value) {
      return String(value || '').replace(/(password|token|secret|connection string|dsn|cookie|csrf)[^,\n]*/ig, '$1 [REDACTED]');
    }

    function renderTenantInstallProgress(job) {
      if (!job) return;
      unitState.createdJob = job;
      if (job.id) unitState.installerJobId = job.id;
      const status = job.workflowStatus || statusLabel(job.status || '') || 'Pending';
      const percentValue = Math.max(0, Math.min(100, Number(job.progressPercent || 0)));
      const badge = document.getElementById('tenantInstallStatusBadge');
      const bar = document.getElementById('tenantInstallProgressBar');
      const logList = document.getElementById('tenantInstallLogList');
      badge.textContent = status;
      badge.className = 'cc-badge';
      if (['Running', 'Waiting', 'Pending'].includes(status)) badge.classList.add('warn');
      if (status === 'Failed') badge.classList.add('danger');
      bar.style.width = percentValue + '%';
      document.getElementById('tenantCreatePanel').textContent = tenantInstallMessage(job);
      logList.replaceChildren(...(job.steps || []).map((step) => {
        const row = document.createElement('div');
        row.className = 'tenant-log-item';
        const title = document.createElement('strong');
        title.textContent = `${installerStepLabel(step.step)} - ${step.workflowStatus || step.status || 'Pending'}`;
        const message = document.createElement('span');
        message.textContent = redactInstallerText(step.message || '');
        const time = document.createElement('small');
        time.className = 'cc-meta';
        time.textContent = [step.startedAt, step.finishedAt].filter(Boolean).join(' -> ');
        row.append(title, message, time);
        return row;
      }));
      document.getElementById('tenantHealthPanel').textContent = tenantInstallMessage(job);
      setTenantInstallerActions(job);
    }

    function isTenantInstallTerminal(job) {
      const workflowStatus = job?.workflowStatus || '';
      return ['Completed', 'Failed', 'Rolled Back'].includes(workflowStatus) || ['READY', 'FAILED', 'ROLLED_BACK', 'DRY_RUN_PASSED'].includes(job?.status);
    }

    function stopTenantInstallPolling() {
      if (!unitState.installerPollTimer) return;
      clearTimeout(unitState.installerPollTimer);
      unitState.installerPollTimer = null;
    }

    async function pollTenantInstallStatus(jobId) {
      if (!jobId) return;
      stopTenantInstallPolling();
      try {
        const result = await api('/api/control-center/tenant-installer/' + encodeURIComponent(jobId));
        renderTenantInstallProgress(result);
        setUnitsAlert(tenantInstallMessage(result));
        if (isTenantInstallTerminal(result)) {
          localStorage.removeItem(storageKey('tenant_installer_job_id'));
          setTenantWizardStep(7);
          await loadUnits();
          return;
        }
        localStorage.setItem(storageKey('tenant_installer_job_id'), String(jobId));
        setTenantWizardStep(6);
        unitState.installerPollTimer = setTimeout(() => pollTenantInstallStatus(jobId), 2500);
      } catch (error) {
        setUnitsAlert(error.message || 'Không tải được trạng thái cài đặt Tenant');
        unitState.installerPollTimer = setTimeout(() => pollTenantInstallStatus(jobId), 5000);
      }
    }

    async function restoreTenantInstallProgress() {
      const jobId = localStorage.getItem(storageKey('tenant_installer_job_id'));
      if (!jobId) return;
      try {
        const result = await api('/api/control-center/tenant-installer/' + encodeURIComponent(jobId));
        renderTenantInstallProgress(result);
        setUnitsAlert(tenantInstallMessage(result));
        if (isTenantInstallTerminal(result)) {
          localStorage.removeItem(storageKey('tenant_installer_job_id'));
          return;
        }
        pollTenantInstallStatus(jobId);
      } catch (error) {
        setUnitsAlert(error.message || 'Không khôi phục được trạng thái cài đặt Tenant');
      }
    }

    function resetTenantReadiness() {
      if (unitState.editing) return;
      unitState.databaseReady = false;
      unitState.preflightReady = false;
      unitState.createdJob = null;
      document.getElementById('saveUnitButton').disabled = true;
      renderDatabaseCheck(null);
      renderPreflight(null);
      renderInfrastructureVerification(null);
      document.getElementById('tenantCreatePanel').textContent = 'Sẵn sàng tạo đơn vị';
      document.getElementById('tenantCreatePanel').classList.remove('active');
      document.getElementById('tenantInstallStatusBadge').textContent = 'Pending';
      document.getElementById('tenantInstallStatusBadge').className = 'cc-badge warn';
      document.getElementById('tenantInstallProgressBar').style.width = '0%';
      document.getElementById('tenantInstallLogList').replaceChildren();
      document.getElementById('tenantHealthPanel').textContent = 'Chưa chạy kiểm tra sau cài đặt';
      document.getElementById('tenantHealthPanel').classList.remove('active');
      updateTenantWizard();
    }

    function setTenantWizardStep(step) {
      unitState.wizardStep = Math.max(1, Math.min(7, step));
      updateTenantWizard();
    }

    function updateTenantWizard() {
      document.getElementById('tenantWizard').style.display = unitState.editing ? 'none' : 'grid';
      if (unitState.editing) {
        document.querySelectorAll('[data-wizard-page]').forEach((page) => {
          const pageNo = Number(page.dataset.wizardPage);
          page.classList.toggle('active', pageNo === 1 || pageNo === 2);
        });
        document.getElementById('wizardBackButton').style.display = 'none';
        document.getElementById('wizardNextButton').style.display = 'none';
        document.getElementById('databaseCheckButton').style.display = 'none';
        document.getElementById('preflightUnitButton').style.display = 'none';
        document.getElementById('saveUnitButton').style.display = '';
        document.getElementById('saveUnitButton').disabled = false;
        return;
      }
      document.querySelectorAll('[data-wizard-page]').forEach((page) => {
        page.classList.toggle('active', Number(page.dataset.wizardPage) === unitState.wizardStep);
      });
      document.querySelectorAll('[data-wizard-indicator]').forEach((item) => {
        const step = Number(item.dataset.wizardIndicator);
        item.classList.toggle('active', step === unitState.wizardStep);
        item.classList.toggle('done', step < unitState.wizardStep);
      });
      document.getElementById('wizardBackButton').style.display = unitState.editing || unitState.wizardStep === 1 ? 'none' : '';
      document.getElementById('wizardNextButton').style.display = unitState.editing || [2, 3, 5, 6, 7].includes(unitState.wizardStep) ? 'none' : '';
      document.getElementById('databaseCheckButton').style.display = !unitState.editing && unitState.wizardStep === 2 ? '' : 'none';
      document.getElementById('preflightUnitButton').style.display = !unitState.editing && unitState.wizardStep === 3 ? '' : 'none';
      document.getElementById('saveUnitButton').style.display = unitState.editing || unitState.wizardStep === 5 ? '' : 'none';
      document.getElementById('saveUnitButton').disabled = unitState.editing ? false : !unitState.preflightReady;
    }

    function nextTenantWizardStep() {
      const payload = unitPayload();
      const validation = validateTenantWizardStep(payload, unitState.wizardStep);
      if (validation) {
        setFormError(validation);
        return;
      }
      if (unitState.wizardStep === 2 && !unitState.databaseReady) {
        setFormError('Cần kiểm tra kết nối cơ sở dữ liệu đạt trước khi sang bước tiền kiểm');
        return;
      }
      if (unitState.wizardStep === 4 && !unitState.preflightReady) {
        setFormError('Cần xác minh hạ tầng đạt trước khi xác nhận cài đặt Tenant');
        return;
      }
      setFormError('');
      setTenantWizardStep(unitState.wizardStep + 1);
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
          if (!unitState.preflightReady) {
            throw new Error('Cần tiền kiểm đạt trước khi tạo đơn vị');
          }
          setTenantWizardStep(6);
          renderTenantInstallProgress({ workflowStatus: 'Running', status: 'CREATING', progressPercent: 0, currentStep: 'preflight_check', steps: [] });
          const result = await api('/api/control-center/tenant-installer', { method: 'POST', body: payload });
          unitState.createdJob = result;
          document.getElementById('tenantCreatePanel').classList.add('active');
          renderTenantInstallProgress(result);
          document.getElementById('tenantHealthPanel').classList.add('active');
          setUnitsAlert(tenantInstallMessage(result));
          setTenantInstallerActions(result);
          if (result.id) localStorage.setItem(storageKey('tenant_installer_job_id'), String(result.id));
          setTenantWizardStep(isTenantInstallTerminal(result) ? 7 : 6);
          pollTenantInstallStatus(result.id);
          await loadUnits();
          return;
        }
        closeUnitModal();
        await loadUnits();
      } catch (error) {
        setFormError(error.message || 'Không lưu được đơn vị');
      } finally {
        button.disabled = unitState.editing ? false : !unitState.preflightReady;
      }
    }

    async function checkTenantDatabaseConnection() {
      if (unitState.editing) return;
      const button = document.getElementById('databaseCheckButton');
      const payload = unitPayload();
      const validation = validateUnitForm(payload);
      if (validation) {
        setFormError(validation);
        return;
      }
      button.disabled = true;
      unitState.databaseReady = false;
      unitState.preflightReady = false;
      setFormError('');
      try {
        const result = await api('/api/control-center/tenant-installer/database-check', { method: 'POST', body: payload });
        renderDatabaseCheck(result);
        unitState.databaseReady = Boolean(result.ready);
        if (!result.ready) {
          setFormError(result.message || 'Kiểm tra cơ sở dữ liệu còn mục không đạt');
          return;
        }
        setFormError('Cơ sở dữ liệu sẵn sàng. Có thể chuyển sang bước tiền kiểm.');
        setTenantWizardStep(3);
      } catch (error) {
        renderDatabaseCheck(null);
        setFormError(error.message || 'Không kiểm tra được cơ sở dữ liệu');
      } finally {
        button.disabled = false;
        updateTenantWizard();
      }
    }

    async function preflightUnitInstall() {
      if (unitState.editing) return;
      const button = document.getElementById('preflightUnitButton');
      const saveButton = document.getElementById('saveUnitButton');
      const payload = unitPayload();
      const validation = validateUnitForm(payload);
      if (validation) {
        setFormError(validation);
        return;
      }
      if (!unitState.databaseReady) {
        setFormError('Cần kiểm tra kết nối cơ sở dữ liệu đạt trước khi tiền kiểm');
        return;
      }
      button.disabled = true;
      saveButton.disabled = true;
      unitState.preflightReady = false;
      setFormError('');
      try {
        const result = await api('/api/control-center/tenant-installer/preflight', { method: 'POST', body: payload });
        renderPreflight(result);
        renderInfrastructureVerification(result);
        unitState.preflightReady = Boolean(result.ready);
        saveButton.disabled = !unitState.preflightReady;
        if (!result.ready) {
          setFormError(result.message || 'Tiền kiểm còn mục không đạt');
          return;
        }
        setFormError('Sẵn sàng tạo đơn vị. Có thể bấm Tạo đơn vị.');
        setTenantWizardStep(4);
      } catch (error) {
        renderPreflight(null);
        setFormError(error.message || 'Tiền kiểm không thành công');
      } finally {
        button.disabled = false;
      }
    }

    async function changeUnitStatus(unit, action) {
      const isLock = action === 'lock';
      const message = isLock ? 'Xác nhận khóa đơn vị này?' : 'Xác nhận kích hoạt đơn vị này?';
      if (!confirm(message)) return;
      setUnitsAlert('');
      try {
        await api('/api/control-center/units/' + encodeURIComponent(unit.id) + '/' + action, { method: 'PATCH' });
        await loadUnits();
      } catch (error) {
        setUnitsAlert(error.message || 'Không cập nhật được trạng thái đơn vị');
      }
    }

    function installerStepLabel(step) {
      const labels = {
        preflight_check: 'kiểm tra điều kiện',
        verify_infrastructure_prerequisites: 'xác minh hạ tầng',
        verify_database_connection: 'kiểm tra kết nối Database',
        verify_database_privileges: 'kiểm tra quyền Database',
        validate_input: 'kiểm tra dữ liệu',
        check_domain: 'kiểm tra tên miền',
        check_database_connection: 'kiểm tra kết nối cơ sở dữ liệu',
        verify_database_ready: 'xác minh cơ sở dữ liệu',
        initialize_database: 'khởi tạo cơ sở dữ liệu',
        import_schema: 'nạp cấu trúc dữ liệu',
        generate_env: 'tạo file .env',
        initialize_tenant: 'khởi tạo Tenant',
        import_seed: 'nạp dữ liệu mẫu',
        create_tenant_record: 'ghi nhận đơn vị',
        create_admin: 'tạo tài khoản quản trị',
        write_config: 'ghi cấu hình',
        create_storage: 'tạo lưu trữ',
        post_installation_verification: 'kiểm tra sau cài đặt',
        health_check: 'kiểm tra sức khỏe',
        complete: 'hoàn tất',
        mark_ready: 'đánh dấu sẵn sàng'
      };
      return labels[step] || step || '';
    }

    function tenantInstallMessage(job) {
      const step = installerStepLabel(job.currentStep || '');
      const workflowStatus = job.workflowStatus || statusLabel(job.status || '') || 'Pending';
      const base = `Khởi tạo đơn vị ${workflowStatus}: ${job.progressPercent || 0}%${step ? ' - ' + step : ''}`;
      if (job.status === 'DRY_RUN_PASSED') {
        return base + '. Chạy thử đạt.';
      }
      if (job.status === 'READY' || workflowStatus === 'Completed') {
        const admin = job.result?.generatedAdminEmail ? ` Quản trị: ${job.result.generatedAdminEmail}` : '';
        return base + '. Hoàn thành.' + admin;
      }
      if (job.status === 'WAITING_MANUAL' || workflowStatus === 'Waiting') {
        return base + '. Cần thao tác thủ công: ' + redactInstallerText(job.errorMessage || 'Kiểm tra chi tiết');
      }
      if (job.status === 'FAILED' || workflowStatus === 'Failed') {
        return base + '. Lỗi: ' + redactInstallerText(job.errorMessage || 'Không rõ nguyên nhân');
      }
      if (job.status === 'ROLLED_BACK' || workflowStatus === 'Rolled Back') {
        return base + '. Đã hoàn tác phần ứng dụng.';
      }
      return base;
    }

    async function retryTenantInstall() {
      if (!unitState.installerJobId) return;
      setUnitsAlert('Đang thử lại khởi tạo đơn vị...');
      try {
        const result = await api('/api/control-center/tenant-installer/' + encodeURIComponent(unitState.installerJobId) + '/retry', { method: 'POST' });
        renderTenantInstallProgress(result);
        setUnitsAlert(tenantInstallMessage(result));
        setTenantInstallerActions(result);
        setTenantWizardStep(isTenantInstallTerminal(result) ? 7 : 6);
        pollTenantInstallStatus(result.id || unitState.installerJobId);
        await loadUnits();
      } catch (error) {
        setUnitsAlert(error.message || 'Không thử lại được khởi tạo đơn vị');
      }
    }

    async function rollbackTenantInstall() {
      if (!unitState.installerJobId || !confirm('Hoàn tác tiến trình cài đặt đơn vị này?')) return;
      setUnitsAlert('Đang hoàn tác khởi tạo đơn vị...');
      try {
        const result = await api('/api/control-center/tenant-installer/' + encodeURIComponent(unitState.installerJobId) + '/rollback', { method: 'POST' });
        renderTenantInstallProgress(result);
        setUnitsAlert(tenantInstallMessage(result));
        setTenantInstallerActions(null);
        localStorage.removeItem(storageKey('tenant_installer_job_id'));
        setTenantWizardStep(7);
        await loadUnits();
      } catch (error) {
        setUnitsAlert(error.message || 'Không hoàn tác được khởi tạo đơn vị');
      }
    }

    async function checkUnitConnection(unit) {
      setUnitsAlert('Đang kiểm tra cơ sở dữ liệu ' + (unit.name || unit.code || '') + '...');
      try {
        await api('/api/control-center/units/' + encodeURIComponent(unit.id) + '/check-connection', { method: 'PATCH' });
        setUnitsAlert('Đã cập nhật trạng thái cơ sở dữ liệu cho ' + (unit.name || unit.code || 'đơn vị'));
        await loadUnits();
      } catch (error) {
        setUnitsAlert(error.message || 'Không kiểm tra được cơ sở dữ liệu đơn vị');
      }
    }

    async function checkUnitWebsite(unit) {
      setUnitsAlert('Đang kiểm tra trang web ' + (unit.name || unit.code || '') + '...');
      try {
        await api('/api/control-center/units/' + encodeURIComponent(unit.id) + '/check-website', { method: 'PATCH' });
        setUnitsAlert('Đã cập nhật trạng thái trang web cho ' + (unit.name || unit.code || 'đơn vị'));
        await loadUnits();
      } catch (error) {
        setUnitsAlert(error.message || 'Không kiểm tra được trang web đơn vị');
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
        setUnitsAlert(error.message || 'Không mở được cổng đơn vị');
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
        option.textContent = unit.name || unit.code || ('Đơn vị #' + unit.id);
        if (String(unit.id) === String(selectedId)) option.selected = true;
        return option;
      });
      if (!options.length) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Chưa có đơn vị';
        options.push(option);
      }
      select.replaceChildren(...options);
    }

    async function openAccountModal(account = null) {
      accountState.editing = account;
      document.getElementById('accountModalTitle').textContent = account ? 'Sửa tài khoản' : 'Thêm tài khoản';
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
      if (!payload.display_name) return 'Họ tên là bắt buộc';
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email || '')) return 'Email không hợp lệ';
      if (!/^[a-z0-9._-]{3,60}$/.test(payload.username || '')) return 'Tên đăng nhập không hợp lệ';
      if (!['SYSTEM_ADMIN', 'VILLAGE_ADMIN', 'STAFF', 'VIEWER'].includes(payload.role)) return 'Vai trò không hợp lệ';
      if (!['ACTIVE', 'INACTIVE'].includes(payload.status)) return 'Trạng thái không hợp lệ';
      if (!payload.unit_id) return 'Đơn vị là bắt buộc';
      if (creating && (!payload.password || payload.password.length < 8)) return 'Mật khẩu tối thiểu 8 ký tự';
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
        setAccountFormError(error.message || 'Không lưu được tài khoản');
      } finally {
        button.disabled = false;
      }
    }

    async function changeAccountStatus(account, action) {
      const message = action === 'deactivate' ? 'Xác nhận ngừng sử dụng tài khoản này?' : 'Xác nhận kích hoạt tài khoản này?';
      if (!confirm(message)) return;
      setAccountsAlert('');
      try {
        await api('/api/control-center/users/' + encodeURIComponent(account.id) + '/' + action, { method: 'PATCH' });
        await loadAccounts();
      } catch (error) {
        setAccountsAlert(error.message || 'Không cập nhật được trạng thái tài khoản');
      }
    }

    function openPasswordModal(account) {
      accountState.passwordTarget = account;
      document.getElementById('passwordModalTitle').textContent = 'Đặt lại mật khẩu - ' + (account.displayName || account.email);
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
        setPasswordFormError('Mật khẩu tối thiểu 8 ký tự');
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
        setPasswordFormError(error.message || 'Không cập nhật được mật khẩu');
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
    const configurationTabs = [
      ['general', 'Cấu hình chung'],
      ['identity', 'Nhận diện hệ thống'],
      ['tenant', 'Multi-tenant'],
      ['security', 'Bảo mật & phiên'],
      ['data', 'Dữ liệu & sao lưu'],
      ['files', 'Tệp & tải lên'],
      ['email', 'Email / Thông báo'],
      ['system', 'Hệ thống & bảo trì']
    ];

    function configValue(key, fallback = '') {
      const item = configurationState.data?.settings?.[key];
      return item && item.value !== null && item.value !== undefined ? item.value : fallback;
    }

    function configInput(key, label, type = 'text') {
      const value = configValue(key, type === 'number' ? 0 : '');
      return '<div class="settings-field"><label for="cfg_' + key.replace(/[^a-z0-9]/gi, '_') + '">' + label + '</label><input class="cc-input" id="cfg_' + key.replace(/[^a-z0-9]/gi, '_') + '" data-config-key="' + key + '" type="' + type + '" value="' + String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;') + '"></div>';
    }

    function configSelect(key, label, options) {
      const value = String(configValue(key, ''));
      const choices = options.map(([optionValue, optionLabel]) => '<option value="' + optionValue + '"' + (value === optionValue ? ' selected' : '') + '>' + optionLabel + '</option>').join('');
      return '<div class="settings-field"><label for="cfg_' + key.replace(/[^a-z0-9]/gi, '_') + '">' + label + '</label><select class="cc-select" id="cfg_' + key.replace(/[^a-z0-9]/gi, '_') + '" data-config-key="' + key + '">' + choices + '</select></div>';
    }

    function configCheckbox(key, label) {
      const checked = configValue(key, false) ? ' checked' : '';
      return '<label class="settings-toggle-row"><span>' + label + '</span><input type="checkbox" data-config-key="' + key + '"' + checked + '></label>';
    }

    function settingsCard(title, note, body, footer = '') {
      return '<article class="settings-card"><div class="settings-card-head"><div><h3 class="settings-card-title">' + title + '</h3><p class="settings-card-note">' + note + '</p></div></div><div class="settings-card-body">' + body + '</div>' + (footer ? '<div class="settings-card-foot">' + footer + '</div>' : '') + '</article>';
    }

    function renderStatusRows(items) {
      return '<div class="settings-status-list">' + (items || []).map((item) => '<div class="settings-status-row"><div><strong>' + item.name + '</strong><div class="settings-readonly">' + (item.note || '') + '</div></div>' + badge(item.status || (item.ok ? 'OK' : 'ERROR')).outerHTML + '</div>').join('') + '</div>';
    }


    function brandingAsset(type) {
      return configurationState.data?.branding?.[type] || { url: '', configured: false };
    }

    function brandingAssetCard(type, label, note, wide = false) {
      const asset = brandingAsset(type);
      const pending = configurationState.assetFiles[type];
      const previewUrl = pending?.previewUrl || asset.url || '';
      const preview = previewUrl ? '<img src="' + previewUrl.replace(/"/g, '&quot;') + '" alt="' + label + '">' : '<span>Chưa có ảnh</span>';
      return '<article class="branding-upload-card" data-branding-card="' + type + '"><div><strong>' + label + '</strong><div class="settings-readonly">' + note + '</div></div><div class="branding-preview' + (wide ? ' wide' : '') + '">' + preview + '</div><div class="settings-readonly">' + (pending ? ('Đã chọn: ' + pending.file.name) : (asset.configured ? 'Đang dùng ảnh đã lưu' : 'Đang dùng mặc định')) + '</div><input class="branding-file" id="asset_' + type + '" type="file" data-branding-file="' + type + '" accept="' + brandingAccept(type) + '"><div class="branding-actions"><button class="cc-btn" type="button" data-cc-action="configuration.chooseAsset" data-asset-type="' + type + '"><i class="fa-solid fa-image"></i>' + (asset.configured ? 'Thay ảnh' : 'Chọn ảnh') + '</button><button class="cc-btn danger" type="button" data-cc-action="configuration.resetAsset" data-asset-type="' + type + '"' + (!asset.configured && !pending ? ' disabled' : '') + '><i class="fa-solid fa-rotate-left"></i>Khôi phục mặc định</button></div></article>';
    }

    function brandingAccept(type) {
      return type === 'favicon' ? '.png,.ico,image/png,image/x-icon,image/vnd.microsoft.icon' : '.png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp';
    }

    async function apiForm(path, formData, options = {}) {
      const method = options.method || 'POST';
      const headers = authHeaders(method);
      const response = await fetch(path, { method, headers, cache: 'no-store', body: formData });
      const payload = await response.json();
      if (!payload.ok) throw new Error(payload.message || 'Yêu cầu không thành công');
      return payload.data;
    }

    async function uploadPendingBrandingAssets() {
      const entries = Object.entries(configurationState.assetFiles);
      for (const [type, record] of entries) {
        if (!record?.file) continue;
        const form = new FormData();
        form.append('asset_type', type);
        form.append('file', record.file);
        const result = await apiForm('/api/platform/settings/assets', form);
        configurationState.data = result.configuration || configurationState.data;
        if (record.previewUrl) URL.revokeObjectURL(record.previewUrl);
        delete configurationState.assetFiles[type];
      }
    }

    function chooseBrandingAsset(type) {
      document.getElementById('asset_' + type)?.click();
    }

    function handleBrandingFileChange(event) {
      const input = event.target;
      const type = input?.dataset?.brandingFile;
      const file = input?.files?.[0];
      if (!type || !file) return;
      if (configurationState.assetFiles[type]?.previewUrl) URL.revokeObjectURL(configurationState.assetFiles[type].previewUrl);
      configurationState.assetFiles[type] = { file, previewUrl: URL.createObjectURL(file) };
      renderConfiguration();
    }

    async function resetBrandingAsset(type) {
      if (configurationState.assetFiles[type]) {
        if (configurationState.assetFiles[type].previewUrl) URL.revokeObjectURL(configurationState.assetFiles[type].previewUrl);
        delete configurationState.assetFiles[type];
        renderConfiguration();
        return;
      }
      if (!confirm('Khôi phục asset này về mặc định?')) return;
      try {
        configurationState.data = await api('/api/platform/settings/assets/reset', { method: 'POST', body: { asset_type: type } });
        setConfigurationAlert('Đã khôi phục mặc định.');
        renderConfiguration();
      } catch (error) {
        setConfigurationAlert(error.message || 'Không khôi phục được asset');
      }
    }
    function renderConfiguration() {
      const tabs = document.getElementById('configurationTabs');
      const panes = document.getElementById('configurationPanes');
      if (!tabs || !panes) return;
      tabs.innerHTML = configurationTabs.map(([id, label]) => '<button class="settings-tab' + (configurationState.activeTab === id ? ' active' : '') + '" type="button" data-cc-action="configuration.tab" data-tab="' + id + '">' + label + '</button>').join('');
      panes.innerHTML = configurationTabs.map(([id]) => '<div class="settings-pane' + (configurationState.activeTab === id ? ' active' : '') + '" data-settings-pane="' + id + '">' + renderConfigurationPane(id) + '</div>').join('');
    }

    function renderConfigurationPane(id) {
      if (!configurationState.data) return '<div class="cc-state">Đang tải cấu hình...</div>';
      if (id === 'general') {
        const body = '<div class="settings-grid">' +
          configInput('general.platform_name', 'Tên nền tảng') +
          configInput('general.admin_name', 'Tên trang quản trị') +
          configInput('general.parent_unit_name', 'Tên đơn vị cấp trên') +
          configInput('general.province_name', 'Tỉnh/Thành phố') +
          configSelect('general.timezone', 'Múi giờ', [['Asia/Ho_Chi_Minh', 'Asia/Ho_Chi_Minh'], ['UTC', 'UTC']]) +
          configSelect('general.locale', 'Ngôn ngữ mặc định', [['vi_VN', 'Tiếng Việt']]) +
          configSelect('general.date_format', 'Định dạng ngày', [['dd/mm/yyyy', 'dd/mm/yyyy'], ['yyyy-mm-dd', 'yyyy-mm-dd']]) +
          configSelect('general.datetime_format', 'Định dạng ngày giờ', [['dd/mm/yyyy HH:mm', 'dd/mm/yyyy HH:mm'], ['yyyy-mm-dd HH:mm', 'yyyy-mm-dd HH:mm']]) +
          '</div>';
        return settingsCard('Cấu hình chung', 'Thông tin hiển thị và thiết lập mặc định của nền tảng.', body, '<button class="cc-btn primary" type="button" data-cc-action="configuration.save" data-group="general"><i class="fa-solid fa-floppy-disk"></i>Lưu thay đổi</button>');
      }
      if (id === 'identity') {
        const body = '<div class="settings-grid">' +
          configInput('identity.system_name', 'Tên hệ thống') + configInput('identity.short_name', 'Tên rút gọn') +
          '</div><div class="branding-upload-grid" style="margin-top:14px">' +
          brandingAssetCard('control_center_logo', 'Logo Community Control Center', 'PNG/JPG/JPEG/WEBP, tối đa 2MB') +
          brandingAssetCard('favicon', 'Favicon', 'PNG/ICO, tối đa 1MB') +
          brandingAssetCard('default_tenant_logo', 'Logo mặc định tenant', 'Tenant chưa có logo riêng sẽ kế thừa ảnh này') +
          brandingAssetCard('default_login_background', 'Hình nền đăng nhập mặc định', 'Tenant chưa có hình nền riêng sẽ kế thừa ảnh này', true) +
          '</div>';
        return settingsCard('Nhận diện hệ thống', 'Quản lý tên và tài sản nhận diện cấp nền tảng.', body, '<button class="cc-btn primary" type="button" data-cc-action="configuration.save" data-group="identity"><i class="fa-solid fa-floppy-disk"></i>Lưu thay đổi</button>');
      }
      if (id === 'tenant') {
        const mt = configurationState.data.multiTenant || {};
        const protectedRows = (mt.protectedControls || []).map((item) => '<div class="settings-toggle-row"><span>' + item.label + '<div class="settings-readonly">Được hệ thống bảo vệ - không thể tắt</div></span>' + badge(item.enabled ? 'OK' : 'ERROR').outerHTML + '</div>').join('');
        const body = '<div class="settings-grid"><div class="settings-field"><label>Chế độ multi-tenant</label><div class="cc-input">Bật</div></div><div class="settings-field"><label>Kiểu nhận diện tenant</label><div class="cc-input">Theo hostname/domain</div></div><div class="settings-field"><label>Domain nền tảng</label><div class="cc-input">' + (mt.rootDomain || '') + '</div></div><div class="settings-field"><label>Quy tắc subdomain</label><div class="cc-input">' + (mt.subdomainRule || '') + '</div></div>' + configSelect('tenant.default_status', 'Trạng thái tenant mới', [['ACTIVE', 'Hoạt động'], ['PENDING_ACTIVATION', 'Chờ kích hoạt']]) + '</div>' +
          '<div style="margin-top:14px">' + protectedRows + '</div><h3 class="settings-card-title" style="margin-top:16px">Mặc định khi tạo tenant mới</h3>' + configCheckbox('tenant.create_database', 'Tạo database') + configCheckbox('tenant.run_migrations', 'Chạy migration/schema') + configCheckbox('tenant.create_admin_account', 'Tạo tài khoản quản trị tenant') + configCheckbox('tenant.apply_platform_settings', 'Áp dụng cấu hình chung') + configCheckbox('tenant.create_uploads_structure', 'Tạo cấu trúc uploads') + configCheckbox('tenant.audit_log_enabled', 'Cấu hình audit log') + '<h3 class="settings-card-title" style="margin-top:16px">Trạng thái runtime</h3>' + renderStatusRows(mt.components || []);
        return settingsCard('Multi-tenant', 'Cấu hình dùng chung cho mọi tenant hiện tại và tenant tạo mới.', body, '<button class="cc-btn primary" type="button" data-cc-action="configuration.save" data-group="tenant"><i class="fa-solid fa-floppy-disk"></i>Lưu thay đổi</button>');
      }
      if (id === 'security') {
        const body = '<div class="settings-grid">' + configInput('security.idle_timeout_minutes', 'Tự động đăng xuất khi không hoạt động (phút)', 'number') + configInput('security.session_ttl_hours', 'Thời gian sống tối đa session (giờ)', 'number') + configInput('security.max_login_attempts', 'Số lần đăng nhập sai tối đa', 'number') + configInput('security.lockout_minutes', 'Thời gian khóa tạm (phút)', 'number') + '</div>' + renderStatusRows([{name:'Bắt buộc HTTPS',status:'OK'},{name:'Secure Cookie',status:'OK'},{name:'HttpOnly Cookie',status:'OK'},{name:'SameSite Cookie',status:'OK'},{name:'CSRF Protection',status:'OK'},{name:'Session regeneration sau đăng nhập',status:'OK'}]);
        return settingsCard('Bảo mật & phiên đăng nhập', 'Các thiết lập bảo mật nguy hiểm được backend bảo vệ, không cho tắt trực tiếp từ UI.', body, '<button class="cc-btn primary" type="button" data-cc-action="configuration.save" data-group="security"><i class="fa-solid fa-floppy-disk"></i>Lưu thay đổi</button>');
      }
      if (id === 'data') {
        const data = configurationState.data.data || {};
        const health = data.centralRegistry || {};
        const body = renderStatusRows([{name:'Central Registry DB',status: health.ok ? 'OK' : 'ERROR', note: health.database || health.message || ''},{name:'Kết nối các tenant DB',status:'NOT_APPLICABLE',note:data.tenantDatabasePolicy || ''},{name:'Backup engine',status:data.backupPolicy?.engineConfigured ? 'OK' : 'NOT_APPLICABLE',note:'Kiểm tra trạng thái thật; không tạo backup giả.'}]);
        return settingsCard('Dữ liệu & sao lưu', 'Theo dõi registry và chính sách backup nền tảng.', body, '<button class="cc-btn" type="button" data-cc-action="configuration.registry"><i class="fa-solid fa-plug-circle-check"></i>Kiểm tra kết nối</button><button class="cc-btn" type="button" data-cc-action="configuration.backup"><i class="fa-solid fa-database"></i>Kiểm tra trạng thái sao lưu</button>');
      }
      if (id === 'files') {
        const allowed = Array.isArray(configValue('files.allowed_extensions', [])) ? configValue('files.allowed_extensions', []).join(', ') : configValue('files.allowed_extensions', '');
        const body = '<div class="settings-grid">' + configInput('files.max_file_mb', 'Dung lượng file tối đa (MB)', 'number') + configInput('files.max_image_mb', 'Dung lượng ảnh tối đa (MB)', 'number') + '<div class="settings-field"><label>Loại file cho phép</label><input class="cc-input" data-config-key="files.allowed_extensions" value="' + allowed + '"></div></div><div class="settings-readonly" style="margin-top:12px">Backend chặn php, phtml, phar, cgi, exe, js và các đuôi thực thi khi lưu cấu hình.</div>';
        return settingsCard('Tệp & tải lên', 'Chính sách file cấp nền tảng để backend sử dụng khi validate upload.', body, '<button class="cc-btn primary" type="button" data-cc-action="configuration.save" data-group="files"><i class="fa-solid fa-floppy-disk"></i>Lưu thay đổi</button>');
      }
      if (id === 'email') {
        const body = '<div class="settings-grid">' + configInput('email.system_email', 'Email hệ thống') + configInput('email.sender_name', 'Tên người gửi') + configInput('email.smtp_host', 'SMTP Host') + configInput('email.smtp_port', 'SMTP Port', 'number') + configSelect('email.smtp_encryption', 'Encryption', [['tls','TLS'],['ssl','SSL'],['none','Không mã hóa']]) + configInput('email.smtp_username', 'SMTP Username') + '<div class="settings-field"><label>SMTP Password</label><input class="cc-input" id="smtpSecretValue" type="password" placeholder="' + (configurationState.data.settings['email.smtp_password']?.masked || 'Chưa cấu hình') + '"></div></div><div class="settings-readonly" style="margin-top:12px">Secret không trả plaintext về frontend và không ghi giá trị vào audit log.</div>';
        return settingsCard('Email / Thông báo', 'Cấu hình email hệ thống và cảnh báo vận hành.', body, '<button class="cc-btn" type="button" data-cc-action="configuration.secret"><i class="fa-solid fa-key"></i>Cập nhật secret</button><button class="cc-btn" type="button" data-cc-action="configuration.testEmail"' + (configurationState.data.capabilities?.smtpTest?.enabled ? '' : ' disabled') + '><i class="fa-solid fa-paper-plane"></i>Gửi email kiểm tra</button><button class="cc-btn primary" type="button" data-cc-action="configuration.save" data-group="email"><i class="fa-solid fa-floppy-disk"></i>Lưu thay đổi</button>');
      }
      const system = configurationState.data.system || {};
      const maintenance = configValue('maintenance.platform_enabled', false);
      const body = renderStatusRows([{name:'Phiên bản ứng dụng',status:'OK',note:system.version || ''},{name:'Commit đang chạy',status:'OK',note:system.commit || 'Không xác định'},{name:'Môi trường',status:'OK',note:system.environment || ''},{name:'PHP version',status:'OK',note:system.phpVersion || ''},{name:'Database version',status:system.databaseVersion ? 'OK':'UNKNOWN',note:system.databaseVersion || ''},{name:'Thời gian server',status:'OK',note:system.serverTime || ''},{name:'Tenant Guard',status:system.tenantGuard || 'ERROR'},{name:'Central Registry',status:system.centralRegistry || 'ERROR'}]) + '<label class="settings-toggle-row"><span>Chế độ bảo trì toàn nền tảng<div class="settings-readonly">Community Control Center vẫn truy cập được để tắt bảo trì.</div></span><input type="checkbox" id="platformMaintenanceToggle" ' + (maintenance ? 'checked' : '') + '></label>';
      return settingsCard('Hệ thống & bảo trì', 'Thông tin runtime chỉ đọc và công tắc bảo trì thật ở TenantGuard.', body, '<button class="cc-btn primary" type="button" data-cc-action="configuration.maintenance"><i class="fa-solid fa-screwdriver-wrench"></i>Cập nhật bảo trì</button>');
    }

    function configurationGroupKeys(group) {
      return Object.keys(configurationState.data?.settings || {}).filter((key) => configurationState.data.settings[key].group === group && !configurationState.data.settings[key].secret);
    }

    async function loadConfiguration() {
      try {
        configurationState.data = await api('/api/control-center/configuration');
        setConfigurationAlert('');
        renderConfiguration();
      } catch (error) {
        setConfigurationAlert(error.message || 'Không tải được cấu hình nền tảng');
      }
    }

    function setConfigurationAlert(message) {
      const element = document.getElementById('configurationAlert');
      if (!element) return;
      element.textContent = message || '';
      element.classList.toggle('active', Boolean(message));
    }

    async function saveConfigurationGroup(group) {
      if (!configurationState.data) return;
      const settings = {};
      configurationGroupKeys(group).forEach((key) => {
        const field = document.querySelector('[data-config-key="' + key + '"]');
        if (!field) return;
        if (field.type === 'checkbox') settings[key] = field.checked;
        else if (key === 'files.allowed_extensions') settings[key] = field.value.split(/[\s,]+/).filter(Boolean);
        else settings[key] = field.value;
      });
      try {
        configurationState.data = await api('/api/control-center/configuration', { method: 'PUT', body: { settings } });
        if (group === 'identity') await uploadPendingBrandingAssets();
        setConfigurationAlert('Đã lưu cấu hình.');
        renderConfiguration();
      } catch (error) {
        setConfigurationAlert(error.message || 'Không lưu được cấu hình');
      }
    }

    async function updateSmtpSecret() {
      const field = document.getElementById('smtpSecretValue');
      const value = field ? field.value : '';
      if (!value) { setConfigurationAlert('Vui lòng nhập SMTP password mới.'); return; }
      try {
        configurationState.data = await api('/api/control-center/configuration/secret', { method: 'PUT', body: { key: 'email.smtp_password', value } });
        setConfigurationAlert('Đã cập nhật SMTP password.');
        renderConfiguration();
      } catch (error) {
        setConfigurationAlert(error.message || 'Không cập nhật được secret');
      }
    }

    async function updateMaintenance() {
      const enabled = Boolean(document.getElementById('platformMaintenanceToggle')?.checked);
      try {
        configurationState.data = await api('/api/control-center/configuration/maintenance', { method: 'PATCH', body: { enabled } });
        setConfigurationAlert(enabled ? 'Đã bật chế độ bảo trì tenant.' : 'Đã tắt chế độ bảo trì tenant.');
        renderConfiguration();
      } catch (error) {
        setConfigurationAlert(error.message || 'Không cập nhật được bảo trì');
      }
    }

    async function runConfigurationCheck(kind) {
      const endpoint = kind === 'backup' ? '/api/control-center/configuration/check-backup' : '/api/control-center/configuration/check-registry';
      try {
        const result = await api(endpoint, { method: 'POST' });
        setConfigurationAlert((result.message || result.status || 'OK'));
      } catch (error) {
        setConfigurationAlert(error.message || 'Không kiểm tra được trạng thái');
      }
    }

    function formatBytes(value) {
      const bytes = Number(value || 0);
      if (bytes <= 0) return '0 B';
      const units = ['B', 'KB', 'MB', 'GB', 'TB'];
      const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
      return `${(bytes / (1024 ** index)).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
    }

    async function loadControlCenter() {
      await loadPermissions().catch((error) => setPermissionsAlert(error.message || 'Không tải được phân quyền'));
      await Promise.all([loadUnits(), loadTenants(), loadAccounts(), loadMonitoring()]).catch((error) => {
        document.getElementById('healthBadge').textContent = statusLabel('DEGRADED');
        document.getElementById('healthBadge').className = 'cc-badge warn';
      });
      renderAuditTenantFilter();
      await loadAudit().catch(() => {});
      await loadDashboard().catch(() => {});
      await loadConfiguration().catch(() => {});
      await restoreTenantInstallProgress();
    }

    function registerControlCenterActions() {
      registerControlCenterAction('section.activate', ({ target, dataset }) => {
        activateSection(dataset.section || dataset.goSection || target.dataset.section || target.dataset.goSection);
      });
      registerControlCenterAction('auth.logout', () => logout());
      registerControlCenterAction('dashboard.refresh', () => loadDashboard().catch(() => {}));
      registerControlCenterAction('unit.create', () => openUnitModal());
      registerControlCenterAction('unit.edit', ({ dataset }) => {
        const unit = controlCenterActionUnit(dataset);
        if (unit) openUnitModal(unit);
      });
      registerControlCenterAction('unit.portal', ({ dataset }) => {
        const unit = controlCenterActionUnit(dataset);
        if (unit) openTenantPortal(unit);
      });
      registerControlCenterAction('unit.checkWebsite', ({ dataset }) => {
        const unit = controlCenterActionUnit(dataset);
        if (unit) checkUnitWebsite(unit);
      });
      registerControlCenterAction('unit.checkDatabase', ({ dataset }) => {
        const unit = controlCenterActionUnit(dataset);
        if (unit) checkUnitConnection(unit);
      });
      registerControlCenterAction('unit.focus', ({ dataset }) => {
        activateSection('units');
        document.getElementById('unitSearch').value = dataset.unitCode || dataset.unitName || '';
        loadUnits().catch((error) => setUnitsAlert(error.message));
      });
      registerControlCenterAction('unit.status', ({ dataset }) => {
        const unit = controlCenterActionUnit(dataset);
        if (unit) changeUnitStatus(unit, dataset.unitStatusAction);
      });
      registerControlCenterAction('unit.wizardBack', () => setTenantWizardStep(unitState.wizardStep - 1));
      registerControlCenterAction('unit.wizardNext', () => nextTenantWizardStep());
      registerControlCenterAction('unit.databaseCheck', () => checkTenantDatabaseConnection());
      registerControlCenterAction('unit.preflight', () => preflightUnitInstall());
      registerControlCenterAction('unit.refresh', () => loadUnits().catch((error) => setUnitsAlert(error.message)));
      registerControlCenterAction('unit.modalClose', () => closeUnitModal());
      registerControlCenterAction('unit.modalBackdrop', ({ event }) => {
        if (event.target.id === 'unitModal') closeUnitModal();
      });
      registerControlCenterAction('tenantInstall.retry', () => retryTenantInstall());
      registerControlCenterAction('tenantInstall.rollback', () => rollbackTenantInstall());
      registerControlCenterAction('tenant.create', () => openTenantModal());
      registerControlCenterAction('tenant.refresh', () => loadTenants());
      registerControlCenterAction('tenant.detail', ({ dataset }) => {
        const tenant = controlCenterActionTenant(dataset);
        if (tenant) openTenantDetail(tenant);
      });
      registerControlCenterAction('tenant.edit', ({ dataset }) => {
        const tenant = controlCenterActionTenant(dataset);
        if (tenant) openTenantModal(tenant);
      });
      registerControlCenterAction('tenant.lock', ({ dataset }) => {
        const tenant = controlCenterActionTenant(dataset);
        if (tenant) lockTenant(tenant);
      });
      registerControlCenterAction('tenant.unlock', ({ dataset }) => {
        const tenant = controlCenterActionTenant(dataset);
        if (tenant) unlockTenant(tenant);
      });
      registerControlCenterAction('tenant.delete', ({ dataset }) => {
        const tenant = controlCenterActionTenant(dataset);
        if (tenant) deleteTenant(tenant);
      });
      registerControlCenterAction('tenant.prevPage', () => {
        if (tenantState.page <= 1) return;
        tenantState.page -= 1;
        loadTenants();
      });
      registerControlCenterAction('tenant.nextPage', () => {
        if (tenantState.page >= tenantState.totalPages) return;
        tenantState.page += 1;
        loadTenants();
      });
      registerControlCenterAction('tenant.modalClose', () => closeTenantModal());
      registerControlCenterAction('tenant.modalBackdrop', ({ event }) => {
        if (event.target.id === 'tenantModal') closeTenantModal();
      });
      registerControlCenterAction('tenant.detailClose', () => closeTenantDetail());
      registerControlCenterAction('tenant.detailBackdrop', ({ event }) => {
        if (event.target.id === 'tenantDetailModal') closeTenantDetail();
      });
      registerControlCenterAction('tenant.activityRefresh', () => loadTenantActivity().catch((error) => {
        document.getElementById('tenantActivityBody').replaceChildren(stateRow(5, error.message || 'Không tải được Activity'));
      }));
      registerControlCenterAction('account.create', () => openAccountModal());
      registerControlCenterAction('account.refresh', () => loadAccounts());
      registerControlCenterAction('account.view', ({ dataset }) => {
        const account = controlCenterActionAccount(dataset);
        if (account) viewAccount(account);
      });
      registerControlCenterAction('account.edit', ({ dataset }) => {
        const account = controlCenterActionAccount(dataset);
        if (account) openAccountModal(account);
      });
      registerControlCenterAction('account.password', ({ dataset }) => {
        const account = controlCenterActionAccount(dataset);
        if (account) openPasswordModal(account);
      });
      registerControlCenterAction('account.status', ({ dataset }) => {
        const account = controlCenterActionAccount(dataset);
        if (account) changeAccountStatus(account, dataset.accountStatusAction);
      });
      registerControlCenterAction('account.modalClose', () => closeAccountModal());
      registerControlCenterAction('account.modalBackdrop', ({ event }) => {
        if (event.target.id === 'accountModal') closeAccountModal();
      });
      registerControlCenterAction('password.modalClose', () => closePasswordModal());
      registerControlCenterAction('password.modalBackdrop', ({ event }) => {
        if (event.target.id === 'passwordModal') closePasswordModal();
      });
      registerControlCenterAction('permission.group', ({ dataset }) => {
        permissionState.activeGroup = dataset.permissionGroupId;
        renderPermissionGroups();
        renderPermissions();
      });
      registerControlCenterAction('permission.refresh', () => loadPermissions());
      registerControlCenterAction('permission.save', () => savePermissions());
      registerControlCenterAction('audit.refresh', () => loadAudit());
      registerControlCenterAction('configuration.tab', ({ dataset }) => { configurationState.activeTab = dataset.tab || 'general'; renderConfiguration(); });
      registerControlCenterAction('configuration.refresh', () => loadConfiguration());
      registerControlCenterAction('configuration.save', ({ dataset }) => saveConfigurationGroup(dataset.group));
      registerControlCenterAction('configuration.chooseAsset', ({ dataset }) => chooseBrandingAsset(dataset.assetType));
      registerControlCenterAction('configuration.resetAsset', ({ dataset }) => resetBrandingAsset(dataset.assetType));
      registerControlCenterAction('configuration.secret', () => updateSmtpSecret());
      registerControlCenterAction('configuration.maintenance', () => updateMaintenance());
      registerControlCenterAction('configuration.registry', () => runConfigurationCheck('registry'));
      registerControlCenterAction('configuration.backup', () => runConfigurationCheck('backup'));
      registerControlCenterAction('configuration.testEmail', async () => { try { await api('/api/control-center/configuration/test-email', { method: 'POST' }); setConfigurationAlert('Đã gửi email kiểm tra.'); } catch (error) { setConfigurationAlert(error.message || 'SMTP chưa sẵn sàng'); } });
    }

    function bindStaticControlCenterActions() {
      document.querySelectorAll('.cc-nav button').forEach((button) => {
        bindControlCenterAction(button, 'section.activate');
      });
      document.querySelectorAll('[data-go-section]').forEach((button) => {
        bindControlCenterAction(button, 'section.activate');
      });
      bindControlCenterElementAction('logoutButton', 'auth.logout');
      bindControlCenterElementAction('refreshOperationsButton', 'dashboard.refresh');
      bindControlCenterElementAction('refreshExecutiveButton', 'dashboard.refresh');
      bindControlCenterElementAction('addUnitButton', 'unit.create');
      bindControlCenterElementAction('wizardBackButton', 'unit.wizardBack');
      bindControlCenterElementAction('wizardNextButton', 'unit.wizardNext');
      bindControlCenterElementAction('databaseCheckButton', 'unit.databaseCheck');
      bindControlCenterElementAction('preflightUnitButton', 'unit.preflight');
      bindControlCenterElementAction('refreshUnitsButton', 'unit.refresh');
      bindControlCenterElementAction('retryTenantInstallButton', 'tenantInstall.retry');
      bindControlCenterElementAction('rollbackTenantInstallButton', 'tenantInstall.rollback');
      bindControlCenterElementAction('closeUnitModalButton', 'unit.modalClose');
      bindControlCenterElementAction('cancelUnitButton', 'unit.modalClose');
      bindControlCenterElementAction('unitModal', 'unit.modalBackdrop');
      bindControlCenterElementAction('addTenantButton', 'tenant.create');
      bindControlCenterElementAction('refreshTenantsButton', 'tenant.refresh');
      bindControlCenterElementAction('tenantPrevPageButton', 'tenant.prevPage');
      bindControlCenterElementAction('tenantNextPageButton', 'tenant.nextPage');
      bindControlCenterElementAction('closeTenantModalButton', 'tenant.modalClose');
      bindControlCenterElementAction('cancelTenantButton', 'tenant.modalClose');
      bindControlCenterElementAction('tenantModal', 'tenant.modalBackdrop');
      bindControlCenterElementAction('closeTenantDetailButton', 'tenant.detailClose');
      bindControlCenterElementAction('closeTenantDetailFooterButton', 'tenant.detailClose');
      bindControlCenterElementAction('tenantDetailModal', 'tenant.detailBackdrop');
      bindControlCenterElementAction('refreshTenantActivityButton', 'tenant.activityRefresh');
      bindControlCenterElementAction('addAccountButton', 'account.create');
      bindControlCenterElementAction('refreshAccountsButton', 'account.refresh');
      bindControlCenterElementAction('closeAccountModalButton', 'account.modalClose');
      bindControlCenterElementAction('cancelAccountButton', 'account.modalClose');
      bindControlCenterElementAction('accountModal', 'account.modalBackdrop');
      bindControlCenterElementAction('closePasswordModalButton', 'password.modalClose');
      bindControlCenterElementAction('cancelPasswordButton', 'password.modalClose');
      bindControlCenterElementAction('passwordModal', 'password.modalBackdrop');
      bindControlCenterElementAction('refreshPermissionsButton', 'permission.refresh');
      bindControlCenterElementAction('savePermissionsButton', 'permission.save');
      bindControlCenterElementAction('refreshAuditButton', 'audit.refresh');
      bindControlCenterElementAction('refreshConfigurationButton', 'configuration.refresh');
    }

    registerControlCenterActions();
    bindStaticControlCenterActions();

    restoreSession().then((restored) => {
      if (restored) {
        loadControlCenter();
      } else {
        document.getElementById('loginUsername').focus();
      }
    });

    document.getElementById('loginForm').addEventListener('submit', login);
    document.getElementById('globalSearch').addEventListener('keydown', (event) => {
      if (event.key !== 'Enter') return;
      const query = event.currentTarget.value.trim().toLowerCase();
      const targets = [
        ['executive', ['bảng điều hành', 'bang dieu hanh', 'executive']],
        ['dashboard', ['dashboard', 'tổng quan', 'thống kê', 'tong quan', 'thong ke']],
        ['units', ['đơn vị', 'thôn', 'xã', 'don vi', 'thon', 'xa', 'administrative']],
        ['tenants', ['tenant', 'domain', 'database', 'trang web']],
        ['accounts', ['tài khoản', 'người dùng', 'tai khoan', 'nguoi dung', 'user']],
        ['permissions', ['phân quyền', 'quyền', 'phan quyen', 'permission', 'quyen']],
        ['monitoring', ['giám sát', 'monitoring', 'health', 'trạng thái', 'trang thai']],
        ['audit', ['nhật ký', 'kiểm toán', 'audit', 'lịch sử', 'truy vết', 'lich su', 'truy vet']],
        ['configuration', ['cấu hình', 'cau hinh', 'config', 'settings']],
        ['notifications', ['thông báo', 'thong bao', 'notification']],
        ['ai', ['ai', 'trợ lý', 'tro ly']]
      ];
      const match = targets.find(([, terms]) => terms.some((term) => query.includes(term) || term.includes(query)));
      if (match) {
        activateSection(match[0]);
        event.currentTarget.blur();
      }
    });
    document.getElementById('unitStatusFilter').addEventListener('change', () => loadUnits().catch((error) => setUnitsAlert(error.message)));
    document.getElementById('unitSearch').addEventListener('input', (() => {
      let timer = null;
      return () => {
        clearTimeout(timer);
        timer = setTimeout(() => loadUnits().catch((error) => setUnitsAlert(error.message)), 250);
      };
    })());
    document.getElementById('unitForm').addEventListener('submit', saveUnit);
    document.querySelectorAll('#unitForm input, #unitForm select, #unitForm textarea').forEach((input) => {
      input.addEventListener('input', resetTenantReadiness);
      input.addEventListener('change', resetTenantReadiness);
    });
    document.getElementById('tenantStatusFilter').addEventListener('change', () => {
      tenantState.page = 1;
      loadTenants();
    });
    document.getElementById('tenantVersionFilter').addEventListener('change', () => {
      tenantState.page = 1;
      loadTenants();
    });
    document.getElementById('tenantSort').addEventListener('change', () => {
      tenantState.page = 1;
      loadTenants();
    });
    document.getElementById('tenantDirection').addEventListener('change', () => {
      tenantState.page = 1;
      loadTenants();
    });
    document.getElementById('tenantSearch').addEventListener('input', (() => {
      let timer = null;
      return () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
          tenantState.page = 1;
          loadTenants();
        }, 250);
      };
    })());
    document.getElementById('tenantForm').addEventListener('submit', saveTenant);
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
    document.getElementById('accountEmail').addEventListener('blur', suggestUsername);
    document.getElementById('accountDisplayName').addEventListener('blur', suggestUsername);
    document.getElementById('passwordForm').addEventListener('submit', savePassword);
    document.getElementById('permissionRoleFilter').addEventListener('change', renderPermissions);
    document.getElementById('permissionSearch').addEventListener('input', (() => {
      let timer = null;
      return () => {
        clearTimeout(timer);
        timer = setTimeout(renderPermissions, 250);
      };
    })());
    document.getElementById('auditTenantFilter').addEventListener('change', () => loadAudit());
    document.getElementById('auditLevelFilter').addEventListener('change', () => loadAudit());
    document.addEventListener('change', (event) => {
      if (event.target?.matches?.('[data-branding-file]')) handleBrandingFileChange(event);
    });
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
