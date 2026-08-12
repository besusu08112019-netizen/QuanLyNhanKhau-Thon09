<!doctype html>
<html lang="vi">
<head><meta charset="utf-8">
  
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
          <p>ÄÄƒng nháº­p Ä‘á»ƒ Ä‘iá»u hÃ nh Hong Phong Community Platform.</p>
        </div>
      </div>
      <div class="cc-field">
        <label for="loginUsername">TÃ i khoáº£n hoáº·c email</label>
        <input class="cc-input" id="loginUsername" name="username" autocomplete="username" required>
      </div>
      <div class="cc-field">
        <label for="loginPassword">Máº­t kháº©u</label>
        <input class="cc-input" id="loginPassword" name="password" type="password" autocomplete="current-password" required>
      </div>
      <div class="cc-form-error active" id="loginError"></div>
      <button class="cc-btn primary" type="submit" id="loginButton"><i class="fa-solid fa-right-to-bracket"></i>ÄÄƒng nháº­p</button>
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
        <button class="active" type="button" data-section="dashboard"><i class="fa-solid fa-chart-line"></i>Tá»•ng quan</button>
        <button type="button" data-section="units"><i class="fa-solid fa-sitemap"></i>ÄÆ¡n vá»‹</button>
        <button type="button" data-section="tenants"><i class="fa-solid fa-building-user"></i>Tenant</button>
        <button type="button" data-section="accounts"><i class="fa-solid fa-users-gear"></i>NgÆ°á»i dÃ¹ng</button>
        <button type="button" data-section="permissions"><i class="fa-solid fa-shield-halved"></i>PhÃ¢n quyá»n</button>
        <button type="button" data-section="executive"><i class="fa-solid fa-gauge-high"></i>Báº£ng Ä‘iá»u hÃ nh</button>
        <button type="button" data-section="monitoring"><i class="fa-solid fa-heart-pulse"></i>GiÃ¡m sÃ¡t</button>
        <button type="button" data-section="audit"><i class="fa-solid fa-clock-rotate-left"></i>Nháº­t kÃ½</button>
        <button type="button" data-section="configuration"><i class="fa-solid fa-gear"></i>Cáº¥u hÃ¬nh</button>
        <button type="button" data-section="notifications"><i class="fa-solid fa-bell"></i>ThÃ´ng bÃ¡o</button>
        <button type="button" data-section="ai"><i class="fa-solid fa-wand-magic-sparkles"></i>Trá»£ lÃ½ thÃ´ng minh</button>
      </nav>
    </aside>

    <main class="cc-main">
      <header class="cc-header">
        <div>
          <h1 class="cc-title" id="sectionTitle">Tá»•ng quan</h1>
          <div class="cc-meta" id="portalMeta">HONG PHONG COMMUNITY PLATFORM - Community Control Center</div>
        </div>
        <div class="cc-header-actions">
          <input class="cc-input cc-global-search" type="search" id="globalSearch" placeholder="TÃ¬m nhanh: báº£ng Ä‘iá»u hÃ nh, Ä‘Æ¡n vá»‹, tÃ i khoáº£n, phÃ¢n quyá»n">
          <span class="cc-meta" id="currentUserLabel">ChÆ°a Ä‘Äƒng nháº­p</span>
          <span class="cc-badge" id="healthBadge">Äang kiá»ƒm tra</span>
          <button class="cc-btn" type="button" id="logoutButton"><i class="fa-solid fa-right-from-bracket"></i>ÄÄƒng xuáº¥t</button>
        </div>
      </header>

      <div class="cc-content">
        <section class="cc-section active" id="dashboardSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <div>
                <h2 class="cc-panel-title">CÃ´ng viá»‡c cáº§n xá»­ lÃ½ hÃ´m nay</h2>
                <div class="cc-meta">Æ¯u tiÃªn cÃ¡c sá»± cá»‘ cáº§n ngÆ°á»i quáº£n trá»‹ xá»­ lÃ½ ngay.</div>
              </div>
              <button class="cc-btn" type="button" id="refreshOperationsButton"><i class="fa-solid fa-rotate"></i>Kiá»ƒm tra láº¡i</button>
            </div>
            <div class="operation-list" id="operationsList"></div>
          </div>
          <div class="metric-grid" id="metricGrid"></div>
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Hoáº¡t Ä‘á»™ng gáº§n Ä‘Ã¢y</h2>
              <button class="cc-btn" type="button" data-go-section="audit"><i class="fa-solid fa-clock-rotate-left"></i>Xem nháº­t kÃ½</button>
            </div>
            <div class="operation-list" id="recentActivityList"></div>
          </div>
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Thao tÃ¡c nhanh</h2>
              <span class="cc-meta">Má»Ÿ nhanh cÃ¡c nÄƒng lá»±c Ä‘iá»u hÃ nh Ä‘ang sáºµn sÃ ng</span>
            </div>
            <div class="cc-state quick-actions">
              <button class="cc-btn" type="button" data-go-section="units"><i class="fa-solid fa-sitemap"></i>Quáº£n lÃ½ Ä‘Æ¡n vá»‹</button>
              <button class="cc-btn" type="button" data-go-section="tenants"><i class="fa-solid fa-building-user"></i>Quáº£n lÃ½ Tenant</button>
              <button class="cc-btn" type="button" data-go-section="accounts"><i class="fa-solid fa-users-gear"></i>Quáº£n lÃ½ tÃ i khoáº£n</button>
              <button class="cc-btn" type="button" data-go-section="permissions"><i class="fa-solid fa-shield-halved"></i>PhÃ¢n quyá»n</button>
            </div>
          </div>
        </section>

        <section class="cc-section" id="executiveSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <div>
                <h2 class="cc-panel-title">Báº£ng Ä‘iá»u hÃ nh</h2>
                <div class="cc-meta">Tá»•ng há»£p tÃ¬nh tráº¡ng váº­n hÃ nh toÃ n bá»™ Community Control Center.</div>
              </div>
              <button class="cc-btn" type="button" id="refreshExecutiveButton"><i class="fa-solid fa-rotate"></i>Kiá»ƒm tra láº¡i</button>
            </div>
            <div class="metric-grid" id="executiveMetricGrid"></div>
          </div>
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">TÃ¬nh tráº¡ng váº­n hÃ nh</h2>
            </div>
            <div class="monitor-grid" id="executiveHealthGrid"></div>
          </div>
        </section>

        <section class="cc-section" id="unitsSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Quáº£n lÃ½ Ä‘Æ¡n vá»‹ hÃ nh chÃ­nh</h2>
              <button class="cc-btn primary" type="button" id="addUnitButton"><i class="fa-solid fa-plus"></i>ThÃªm Ä‘Æ¡n vá»‹</button>
            </div>
            <div class="cc-toolbar">
              <input class="cc-input" type="search" id="unitSearch" placeholder="TÃ¬m theo mÃ£, tÃªn, tÃªn miá»n">
              <select class="cc-select" id="unitStatusFilter" aria-label="Lá»c tráº¡ng thÃ¡i">
                <option value="">Táº¥t cáº£ tráº¡ng thÃ¡i</option>
                <option value="READY">Sáºµn sÃ ng</option>
                <option value="CREATING">Äang táº¡o</option>
                <option value="FAILED">Lá»—i</option>
                <option value="DISABLED">ÄÃ£ khÃ³a</option>
                <option value="MAINTENANCE">Báº£o trÃ¬</option>
              </select>
              <button class="cc-btn" type="button" id="refreshUnitsButton"><i class="fa-solid fa-rotate"></i>Táº£i láº¡i</button>
            </div>
            <div class="cc-alert" id="unitsAlert"></div>
            <div class="cc-row-actions" id="tenantInstallerActions" style="display:none;margin-bottom:12px">
              <button class="cc-btn" type="button" id="retryTenantInstallButton"><i class="fa-solid fa-rotate-right"></i>Thá»­ láº¡i</button>
              <button class="cc-btn danger" type="button" id="rollbackTenantInstallButton"><i class="fa-solid fa-clock-rotate-left"></i>HoÃ n tÃ¡c</button>
            </div>
            <div class="cc-table-wrap">
              <table class="cc-table">
                <thead>
                  <tr>
                    <th>MÃ£</th>
                    <th>TÃªn Ä‘Æ¡n vá»‹</th>
                    <th>TÃªn miá»n</th>
                    <th>CÆ¡ sá»Ÿ dá»¯ liá»‡u</th>
                    <th>Tráº¡ng thÃ¡i</th>
                    <th>NgÆ°á»i quáº£n lÃ½</th>
                    <th>Trang web</th>
                    <th>CÆ¡ sá»Ÿ dá»¯ liá»‡u</th>
                    <th>PhiÃªn báº£n</th>
                    <th>Thao tÃ¡c</th>
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
                <h2 class="cc-panel-title">Quáº£n lÃ½ Tenant</h2>
                <div class="cc-meta">Quáº£n lÃ½ thÃ´ng tin Tenant, tráº¡ng thÃ¡i, phiÃªn báº£n, dung lÆ°á»£ng vÃ  nháº­t kÃ½ hoáº¡t Ä‘á»™ng.</div>
              </div>
              <button class="cc-btn primary" type="button" id="addTenantButton" data-tenant-permission="tenant.create"><i class="fa-solid fa-plus"></i>ThÃªm Tenant</button>
            </div>
            <div class="cc-toolbar tenant-toolbar">
              <input class="cc-input" type="search" id="tenantSearch" placeholder="TÃ¬m mÃ£, tÃªn, domain, database">
              <select class="cc-select" id="tenantStatusFilter" aria-label="Lá»c tráº¡ng thÃ¡i">
                <option value="">Táº¥t cáº£ tráº¡ng thÃ¡i</option>
                <option value="ACTIVE">Hoáº¡t Ä‘á»™ng</option>
                <option value="READY">Sáºµn sÃ ng</option>
                <option value="LOCKED">ÄÃ£ khÃ³a</option>
                <option value="DISABLED">ÄÃ£ táº¯t</option>
                <option value="MAINTENANCE">Báº£o trÃ¬</option>
                <option value="FAILED">Lá»—i</option>
                <option value="DELETED">ÄÃ£ xÃ³a má»m</option>
              </select>
              <select class="cc-select" id="tenantVersionFilter" aria-label="Lá»c phiÃªn báº£n">
                <option value="">Táº¥t cáº£ phiÃªn báº£n</option>
              </select>
              <select class="cc-select" id="tenantSort" aria-label="Sáº¯p xáº¿p">
                <option value="updated">Cáº­p nháº­t má»›i nháº¥t</option>
                <option value="name">TÃªn Tenant</option>
                <option value="status">Tráº¡ng thÃ¡i</option>
                <option value="code">MÃ£ Tenant</option>
                <option value="storage">Dung lÆ°á»£ng</option>
              </select>
              <select class="cc-select" id="tenantDirection" aria-label="Chiá»u sáº¯p xáº¿p">
                <option value="DESC">Giáº£m dáº§n</option>
                <option value="ASC">TÄƒng dáº§n</option>
              </select>
              <button class="cc-btn" type="button" id="refreshTenantsButton"><i class="fa-solid fa-rotate"></i>Táº£i láº¡i</button>
            </div>
            <div class="cc-alert" id="tenantsAlert"></div>
            <div class="cc-table-wrap">
              <table class="cc-table">
                <thead>
                  <tr>
                    <th>MÃ£</th>
                    <th>TÃªn Tenant</th>
                    <th>Domain</th>
                    <th>Database</th>
                    <th>Tráº¡ng thÃ¡i</th>
                    <th>PhiÃªn báº£n</th>
                    <th>Dung lÆ°á»£ng</th>
                    <th>Cáº­p nháº­t</th>
                    <th>Thao tÃ¡c</th>
                  </tr>
                </thead>
                <tbody id="tenantsBody"></tbody>
              </table>
            </div>
            <div class="cc-pagination" id="tenantsPagination">
              <span class="cc-meta" id="tenantsPageInfo">ChÆ°a cÃ³ dá»¯ liá»‡u</span>
              <div class="cc-row-actions">
                <button class="cc-btn" type="button" id="tenantPrevPageButton"><i class="fa-solid fa-chevron-left"></i>TrÆ°á»›c</button>
                <button class="cc-btn" type="button" id="tenantNextPageButton">Sau<i class="fa-solid fa-chevron-right"></i></button>
              </div>
            </div>
          </div>
        </section>

        <section class="cc-section" id="accountsSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Quáº£n lÃ½ tÃ i khoáº£n há»‡ thá»‘ng</h2>
              <button class="cc-btn primary" type="button" id="addAccountButton"><i class="fa-solid fa-user-plus"></i>ThÃªm tÃ i khoáº£n</button>
            </div>
            <div class="cc-toolbar">
              <input class="cc-input" type="search" id="accountSearch" placeholder="TÃ¬m tÃªn Ä‘Äƒng nháº­p, há» tÃªn, email, vai trÃ², Ä‘Æ¡n vá»‹">
              <select class="cc-select" id="accountRoleFilter" aria-label="Lá»c vai trÃ²">
                <option value="">Táº¥t cáº£ vai trÃ²</option>
                <option value="SYSTEM_ADMIN">Quáº£n trá»‹ há»‡ thá»‘ng</option>
                <option value="VILLAGE_ADMIN">Quáº£n trá»‹ thÃ´n</option>
                <option value="STAFF">CÃ¡n bá»™ nháº­p liá»‡u</option>
                <option value="VIEWER">Chá»‰ xem</option>
              </select>
              <select class="cc-select" id="accountStatusFilter" aria-label="Lá»c tráº¡ng thÃ¡i">
                <option value="">Táº¥t cáº£ tráº¡ng thÃ¡i</option>
                <option value="ACTIVE">Äang sá»­ dá»¥ng</option>
                <option value="INACTIVE">Ngá»«ng sá»­ dá»¥ng</option>
              </select>
              <button class="cc-btn" type="button" id="refreshAccountsButton"><i class="fa-solid fa-rotate"></i>Táº£i láº¡i</button>
            </div>
            <div class="cc-alert" id="accountsAlert"></div>
            <div class="cc-table-wrap">
              <table class="cc-table">
                <thead>
                  <tr>
                    <th>TÃªn</th>
                    <th>Vai trÃ²</th>
                    <th>ÄÆ¡n vá»‹</th>
                    <th>Tráº¡ng thÃ¡i</th>
                    <th>ÄÄƒng nháº­p cuá»‘i</th>
                    <th>Äá»‹a chá»‰ IP cuá»‘i</th>
                    <th>Thiáº¿t bá»‹ cuá»‘i</th>
                    <th>Thá»i gian táº¡o</th>
                    <th>NgÆ°á»i táº¡o</th>
                    <th>Thao tÃ¡c</th>
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
                <h2 class="cc-panel-title">PhÃ¢n quyá»n Community Control Center</h2>
                <div class="cc-meta">Kiá»ƒm soÃ¡t trÃ¬nh Ä‘Æ¡n, phÃ¢n há»‡, nÃºt, thao tÃ¡c vÃ  API trong Control Center.</div>
              </div>
              <button class="cc-btn primary" type="button" id="savePermissionsButton" disabled><i class="fa-solid fa-floppy-disk"></i>LÆ°u thay Ä‘á»•i</button>
            </div>
            <div class="cc-toolbar">
              <input class="cc-input" type="search" id="permissionSearch" placeholder="TÃ¬m quyá»n, phÃ¢n há»‡, thao tÃ¡c">
              <select class="cc-select" id="permissionRoleFilter" aria-label="Lá»c vai trÃ²">
                <option value="">Táº¥t cáº£ vai trÃ²</option>
              </select>
              <button class="cc-btn" type="button" id="refreshPermissionsButton"><i class="fa-solid fa-rotate"></i>Táº£i láº¡i</button>
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
              <h2 class="cc-panel-title">Nháº­t kÃ½ kiá»ƒm toÃ¡n</h2>
              <button class="cc-btn" type="button" id="refreshAuditButton"><i class="fa-solid fa-rotate"></i>Táº£i láº¡i</button>
            </div>
            <div class="cc-toolbar">
              <select class="cc-select" id="auditTenantFilter" aria-label="Lá»c Ä‘Æ¡n vá»‹">
                <option value="">Táº¥t cáº£ Ä‘Æ¡n vá»‹</option>
              </select>
              <select class="cc-select" id="auditLevelFilter" aria-label="Lá»c má»©c Ä‘á»™">
                <option value="">Táº¥t cáº£ má»©c Ä‘á»™</option>
                <option value="INFO">ThÃ´ng tin</option>
                <option value="WARN">Cáº£nh bÃ¡o</option>
                <option value="ERROR">Lá»—i</option>
              </select>
              <input class="cc-input" type="search" id="auditSearch" placeholder="TÃ¬m ngÆ°á»i thá»±c hiá»‡n, hÃ nh Ä‘á»™ng, Ä‘Æ¡n vá»‹">
            </div>
            <div class="cc-table-wrap">
              <table class="cc-table">
                <thead>
                  <tr>
                    <th>Thá»i gian</th>
                    <th>ÄÆ¡n vá»‹</th>
                    <th>NgÆ°á»i thá»±c hiá»‡n</th>
                    <th>HÃ nh Ä‘á»™ng</th>
                    <th>Má»©c Ä‘á»™</th>
                    <th>Káº¿t quáº£</th>
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
              <h2 class="cc-panel-title">Cáº¥u hÃ¬nh ná»n táº£ng</h2>
              <button class="cc-btn" type="button" id="refreshConfigurationButton"><i class="fa-solid fa-rotate"></i>Táº£i láº¡i</button>
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
              <h2 class="cc-panel-title">ThÃ´ng bÃ¡o</h2>
              <span class="cc-badge warn">Äang phÃ¡t triá»ƒn</span>
            </div>
            <div class="cc-state">Sáº½ Ä‘iá»u phá»‘i thÃ´ng tin, thÃ´ng bÃ¡o ná»™i bá»™ vÃ  cáº£nh bÃ¡o váº­n hÃ nh.</div>
          </div>
        </section>

        <section class="cc-section" id="aiSection">
          <div class="cc-panel">
            <div class="cc-panel-header">
              <h2 class="cc-panel-title">Trá»£ lÃ½ thÃ´ng minh</h2>
              <span class="cc-badge warn">Äang phÃ¡t triá»ƒn</span>
            </div>
            <div class="cc-state">Sáº½ há»— trá»£ tÃ¬m kiáº¿m, tá»•ng há»£p vÃ  gá»£i Ã½ thao tÃ¡c trong pháº¡m vi Ä‘Æ°á»£c cáº¥p quyá»n.</div>
          </div>
        </section>
      </div>

      <footer class="cc-footer">
        Ná»n táº£ng Community Control Center. Cá»•ng Ä‘Æ¡n vá»‹ vÃ  cÃ¡c phÃ¢n há»‡ nghiá»‡p vá»¥ Ä‘Æ°á»£c giá»¯ tÃ¡ch biá»‡t.
      </footer>
    </main>
  </div>

  <div class="cc-modal-backdrop" id="unitModal" role="dialog" aria-modal="true" aria-labelledby="unitModalTitle">
    <div class="cc-modal">
      <div class="cc-modal-header">
        <h2 class="cc-modal-title" id="unitModalTitle">ThÃªm Ä‘Æ¡n vá»‹</h2>
        <button class="cc-btn" type="button" id="closeUnitModalButton" aria-label="ÄÃ³ng"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="unitForm" novalidate>
        <div class="tenant-wizard" id="tenantWizard">
          <div class="tenant-wizard-step active" data-wizard-indicator="1">1. Cáº¥u hÃ¬nh Tenant</div>
          <div class="tenant-wizard-step" data-wizard-indicator="2">2. Database</div>
          <div class="tenant-wizard-step" data-wizard-indicator="3">3. Preflight</div>
          <div class="tenant-wizard-step" data-wizard-indicator="4">4. Háº¡ táº§ng</div>
          <div class="tenant-wizard-step" data-wizard-indicator="5">5. XÃ¡c nháº­n</div>
          <div class="tenant-wizard-step" data-wizard-indicator="6">6. Tiáº¿n trÃ¬nh</div>
          <div class="tenant-wizard-step" data-wizard-indicator="7">7. Káº¿t quáº£</div>
        </div>
        <div class="cc-form">
          <input type="hidden" id="unitId">
          <div class="wizard-page active" data-wizard-page="1">
          <div class="cc-field">
            <label for="unitCode">MÃ£ Ä‘Æ¡n vá»‹ *</label>
            <input class="cc-input" id="unitCode" name="code" required maxlength="50" pattern="[a-z0-9_-]{2,50}" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitName">TÃªn Ä‘Æ¡n vá»‹ *</label>
            <input class="cc-input" id="unitName" name="name" required maxlength="190" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitCommuneName">XÃ£/PhÆ°á»ng</label>
            <input class="cc-input" id="unitCommuneName" name="commune_name" maxlength="190" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitStatus">Tráº¡ng thÃ¡i</label>
            <select class="cc-select" id="unitStatus" name="status">
              <option value="READY">Sáºµn sÃ ng</option>
              <option value="CREATING">Äang táº¡o</option>
              <option value="FAILED">Lá»—i</option>
              <option value="DISABLED">ÄÃ£ khÃ³a</option>
              <option value="MAINTENANCE">Báº£o trÃ¬</option>
            </select>
          </div>
          <div class="cc-field">
            <label for="unitDomain">TÃªn miá»n</label>
            <input class="cc-input" id="unitDomain" name="domain" maxlength="190" placeholder="thon09.hongphongnb.com" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitSubdomain">TÃªn miá»n phá»¥</label>
            <input class="cc-input" id="unitSubdomain" name="subdomain" maxlength="190" placeholder="thon09.hongphongnb.com" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitAppVersion">PhiÃªn báº£n á»©ng dá»¥ng</label>
            <input class="cc-input" id="unitAppVersion" name="app_version" maxlength="50" placeholder="v2.0" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitBuildVersion">PhiÃªn báº£n báº£n dá»±ng</label>
            <input class="cc-input" id="unitBuildVersion" name="build_version" maxlength="100" placeholder="20260727-gis-multi-area-1" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitSchemaVersion">PhiÃªn báº£n lÆ°á»£c Ä‘á»“</label>
            <input class="cc-input" id="unitSchemaVersion" name="schema_version" maxlength="50" placeholder="20260729" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitManagerName">NgÆ°á»i quáº£n lÃ½</label>
            <input class="cc-input" id="unitManagerName" name="manager_name" maxlength="190" placeholder="ChÆ°a gÃ¡n" autocomplete="off">
          </div>
          <div class="cc-field full">
            <label for="unitLogo">ÄÆ°á»ng dáº«n logo</label>
            <input class="cc-input" id="unitLogo" name="logo" maxlength="500" placeholder="/assets/logo.png" autocomplete="off">
          </div>
          <div class="cc-field full">
            <label for="unitNotes">Ghi chÃº</label>
            <textarea class="cc-input" id="unitNotes" name="notes" maxlength="2000" rows="3" placeholder="ThÃ´ng tin váº­n hÃ nh, lá»‹ch sao lÆ°u, ngÆ°á»i phá»¥ trÃ¡ch..."></textarea>
          </div>
          </div>
          <div class="wizard-page" data-wizard-page="2">
          <div class="cc-field">
            <label for="unitDatabaseHost">MÃ¡y chá»§ cÆ¡ sá»Ÿ dá»¯ liá»‡u</label>
            <input class="cc-input" id="unitDatabaseHost" name="database_host" maxlength="190" placeholder="localhost" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitDatabaseName">TÃªn cÆ¡ sá»Ÿ dá»¯ liá»‡u</label>
            <input class="cc-input" id="unitDatabaseName" name="database_name" maxlength="190" placeholder="database_name" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitDatabaseUsername">NgÆ°á»i dÃ¹ng cÆ¡ sá»Ÿ dá»¯ liá»‡u</label>
            <input class="cc-input" id="unitDatabaseUsername" name="database_username" maxlength="190" placeholder="nguoi_dung_csdl" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="unitDatabasePassword">Máº­t kháº©u cÆ¡ sá»Ÿ dá»¯ liá»‡u</label>
            <input class="cc-input" id="unitDatabasePassword" name="database_password" type="password" autocomplete="new-password">
          </div>
          <div class="cc-field">
            <label for="unitDatabaseCharset">Báº£ng mÃ£ cÆ¡ sá»Ÿ dá»¯ liá»‡u</label>
            <input class="cc-input" id="unitDatabaseCharset" name="database_charset" maxlength="50" placeholder="utf8mb4" autocomplete="off">
          </div>
          </div>
        </div>
        <div class="cc-form-error" id="unitFormError"></div>
        <div class="preflight-panel wizard-page" id="tenantDatabasePanel" data-wizard-page="2">
          <div class="preflight-status failed" id="tenantDatabaseStatus">ChÆ°a kiá»ƒm tra cÆ¡ sá»Ÿ dá»¯ liá»‡u</div>
          <div class="preflight-list" id="tenantDatabaseList"></div>
        </div>
        <div class="preflight-panel wizard-page" id="tenantPreflightPanel" data-wizard-page="3">
          <div class="preflight-status failed" id="tenantPreflightStatus">ChÆ°a cháº¡y tiá»n kiá»ƒm</div>
          <div class="preflight-list" id="tenantPreflightList"></div>
        </div>
        <div class="preflight-panel wizard-page" id="tenantInfrastructurePanel" data-wizard-page="4">
          <div class="preflight-status failed" id="tenantInfrastructureStatus">ChÆ°a xÃ¡c minh háº¡ táº§ng</div>
          <div class="preflight-list" id="tenantInfrastructureList"></div>
        </div>
        <div class="tenant-result wizard-page" id="tenantConfirmPanel" data-wizard-page="5">Sáºµn sÃ ng xÃ¡c nháº­n cÃ i Ä‘áº·t Tenant</div>
        <div class="tenant-result wizard-page" id="tenantProgressPanel" data-wizard-page="6">
          <div><span class="cc-badge warn" id="tenantInstallStatusBadge">Pending</span></div>
          <div class="tenant-progress" aria-label="Tiáº¿n trÃ¬nh cÃ i Ä‘áº·t Tenant"><div class="tenant-progress-bar" id="tenantInstallProgressBar"></div></div>
          <div id="tenantCreatePanel">ChÆ°a báº¯t Ä‘áº§u cÃ i Ä‘áº·t</div>
          <div class="tenant-log-list" id="tenantInstallLogList"></div>
        </div>
        <div class="tenant-result wizard-page" id="tenantHealthPanel" data-wizard-page="7">ChÆ°a cháº¡y kiá»ƒm tra sau cÃ i Ä‘áº·t</div>
        <div class="cc-modal-footer">
          <button class="cc-btn" type="button" id="cancelUnitButton">Há»§y</button>
          <button class="cc-btn" type="button" id="wizardBackButton">Quay láº¡i</button>
          <button class="cc-btn" type="button" id="wizardNextButton">Tiáº¿p</button>
          <button class="cc-btn" type="button" id="databaseCheckButton"><i class="fa-solid fa-plug-circle-check"></i>Kiá»ƒm tra káº¿t ná»‘i cÆ¡ sá»Ÿ dá»¯ liá»‡u</button>
          <button class="cc-btn" type="button" id="preflightUnitButton"><i class="fa-solid fa-shield-halved"></i>Tiá»n kiá»ƒm</button>
          <button class="cc-btn primary" type="submit" id="saveUnitButton" disabled><i class="fa-solid fa-floppy-disk"></i>Táº¡o Ä‘Æ¡n vá»‹</button>
        </div>
      </form>
    </div>
  </div>

  <div class="cc-modal-backdrop" id="tenantModal" role="dialog" aria-modal="true" aria-labelledby="tenantModalTitle">
    <div class="cc-modal">
      <div class="cc-modal-header">
        <h2 class="cc-modal-title" id="tenantModalTitle">ThÃªm Tenant</h2>
        <button class="cc-btn" type="button" id="closeTenantModalButton" aria-label="ÄÃ³ng"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="tenantForm" novalidate>
        <div class="cc-form">
          <input type="hidden" id="tenantId">
          <div class="cc-field">
            <label for="tenantCode">MÃ£ Tenant *</label>
            <input class="cc-input" id="tenantCode" name="code" required maxlength="50" pattern="[a-z0-9_-]{2,50}" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantName">TÃªn Tenant *</label>
            <input class="cc-input" id="tenantName" name="name" required maxlength="190" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantUnitName">TÃªn Ä‘Æ¡n vá»‹</label>
            <input class="cc-input" id="tenantUnitName" name="unit_name" maxlength="190" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantCommuneName">XÃ£/PhÆ°á»ng</label>
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
            <label for="tenantStatus">Tráº¡ng thÃ¡i</label>
            <select class="cc-select" id="tenantStatus" name="status">
              <option value="READY">Sáºµn sÃ ng</option>
              <option value="ACTIVE">Hoáº¡t Ä‘á»™ng</option>
              <option value="MAINTENANCE">Báº£o trÃ¬</option>
              <option value="DISABLED">ÄÃ£ táº¯t</option>
              <option value="FAILED">Lá»—i</option>
            </select>
          </div>
          <div class="cc-field">
            <label for="tenantAppVersion">PhiÃªn báº£n á»©ng dá»¥ng</label>
            <input class="cc-input" id="tenantAppVersion" name="app_version" maxlength="50" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantBuildVersion">PhiÃªn báº£n build</label>
            <input class="cc-input" id="tenantBuildVersion" name="build_version" maxlength="100" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantSchemaVersion">PhiÃªn báº£n schema</label>
            <input class="cc-input" id="tenantSchemaVersion" name="schema_version" maxlength="50" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantStorageQuotaBytes">Dung lÆ°á»£ng giá»›i háº¡n (bytes)</label>
            <input class="cc-input" id="tenantStorageQuotaBytes" name="storage_quota_bytes" type="number" min="0" step="1" autocomplete="off">
          </div>
          <div class="cc-field">
            <label for="tenantManagerName">NgÆ°á»i quáº£n lÃ½</label>
            <input class="cc-input" id="tenantManagerName" name="manager_name" maxlength="190" autocomplete="off">
          </div>
          <div class="cc-field full">
            <label for="tenantNotes">Ghi chÃº</label>
            <textarea class="cc-input" id="tenantNotes" name="notes" maxlength="2000" rows="3"></textarea>
          </div>
        </div>
        <div class="cc-form-error" id="tenantFormError"></div>
        <div class="cc-modal-footer">
          <button class="cc-btn" type="button" id="cancelTenantButton">Há»§y</button>
          <button class="cc-btn primary" type="submit" id="saveTenantButton"><i class="fa-solid fa-floppy-disk"></i>LÆ°u</button>
        </div>
      </form>
    </div>
  </div>

  <div class="cc-modal-backdrop" id="tenantDetailModal" role="dialog" aria-modal="true" aria-labelledby="tenantDetailTitle">
    <div class="cc-modal">
      <div class="cc-modal-header">
        <h2 class="cc-modal-title" id="tenantDetailTitle">Chi tiáº¿t Tenant</h2>
        <button class="cc-btn" type="button" id="closeTenantDetailButton" aria-label="ÄÃ³ng"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="tenant-detail-grid" id="tenantDetailGrid"></div>
      <div class="cc-panel" style="border-left:0;border-right:0;border-bottom:0;border-radius:0">
        <div class="cc-panel-header">
          <h3 class="cc-panel-title">Activity</h3>
          <button class="cc-btn" type="button" id="refreshTenantActivityButton"><i class="fa-solid fa-rotate"></i>Táº£i láº¡i</button>
        </div>
        <div class="cc-table-wrap">
          <table class="cc-table">
            <thead>
              <tr>
                <th>Thá»i gian</th>
                <th>NgÆ°á»i thá»±c hiá»‡n</th>
                <th>HÃ nh Ä‘á»™ng</th>
                <th>Má»©c Ä‘á»™</th>
                <th>Ná»™i dung</th>
              </tr>
            </thead>
            <tbody id="tenantActivityBody"></tbody>
          </table>
        </div>
      </div>
      <div class="cc-modal-footer">
        <button class="cc-btn" type="button" id="closeTenantDetailFooterButton">ÄÃ³ng</button>
      </div>
    </div>
  </div>

  <div class="cc-modal-backdrop" id="accountModal" role="dialog" aria-modal="true" aria-labelledby="accountModalTitle">
    <div class="cc-modal">
      <div class="cc-modal-header">
        <h2 class="cc-modal-title" id="accountModalTitle">ThÃªm tÃ i khoáº£n</h2>
        <button class="cc-btn" type="button" id="closeAccountModalButton" aria-label="ÄÃ³ng"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="accountForm" novalidate>
        <div class="cc-form">
          <input type="hidden" id="accountId">
          <div class="cc-field">
            <label for="accountDisplayName">Há» tÃªn *</label>
            <input class="cc-input" id="accountDisplayName" name="display_name" required maxlength="190" autocomplete="name">
          </div>
          <div class="cc-field">
            <label for="accountEmail">Email *</label>
            <input class="cc-input" id="accountEmail" name="email" type="email" required maxlength="190" autocomplete="email">
          </div>
          <div class="cc-field">
            <label for="accountUsername">TÃªn Ä‘Äƒng nháº­p *</label>
            <input class="cc-input" id="accountUsername" name="username" required maxlength="60" pattern="[a-z0-9._-]{3,60}" autocomplete="username">
          </div>
          <div class="cc-field">
            <label for="accountRole">Vai trÃ² *</label>
            <select class="cc-select" id="accountRole" name="role" required>
              <option value="VILLAGE_ADMIN">Quáº£n trá»‹ thÃ´n</option>
              <option value="STAFF">CÃ¡n bá»™ nháº­p liá»‡u</option>
              <option value="VIEWER">Chá»‰ xem</option>
              <option value="SYSTEM_ADMIN">Quáº£n trá»‹ há»‡ thá»‘ng</option>
              <option value="COMMUNE_ADMIN" disabled>Quáº£n trá»‹ xÃ£ (sau)</option>
            </select>
          </div>
          <div class="cc-field">
            <label for="accountUnit">ÄÆ¡n vá»‹ *</label>
            <select class="cc-select" id="accountUnit" name="unit_id" required></select>
          </div>
          <div class="cc-field">
            <label for="accountStatus">Tráº¡ng thÃ¡i</label>
            <select class="cc-select" id="accountStatus" name="status">
              <option value="ACTIVE">Äang sá»­ dá»¥ng</option>
              <option value="INACTIVE">Ngá»«ng sá»­ dá»¥ng</option>
            </select>
          </div>
          <div class="cc-field account-password-field">
            <label for="accountPassword">Máº­t kháº©u *</label>
            <input class="cc-input" id="accountPassword" name="password" type="password" minlength="8" autocomplete="new-password">
          </div>
          <div class="cc-field">
            <label for="accountPhone">Äiá»‡n thoáº¡i</label>
            <input class="cc-input" id="accountPhone" name="phone" maxlength="50" autocomplete="tel">
          </div>
          <div class="cc-field full">
            <label for="accountPosition">Chá»©c vá»¥</label>
            <input class="cc-input" id="accountPosition" name="position" maxlength="190" autocomplete="organization-title">
          </div>
        </div>
        <div class="cc-form-error" id="accountFormError"></div>
        <div class="cc-modal-footer">
          <button class="cc-btn" type="button" id="cancelAccountButton">Há»§y</button>
          <button class="cc-btn primary" type="submit" id="saveAccountButton"><i class="fa-solid fa-floppy-disk"></i>LÆ°u</button>
        </div>
      </form>
    </div>
  </div>

  <div class="cc-modal-backdrop" id="passwordModal" role="dialog" aria-modal="true" aria-labelledby="passwordModalTitle">
    <div class="cc-modal">
      <div class="cc-modal-header">
        <h2 class="cc-modal-title" id="passwordModalTitle">Äáº·t láº¡i máº­t kháº©u</h2>
        <button class="cc-btn" type="button" id="closePasswordModalButton" aria-label="ÄÃ³ng"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form id="passwordForm" novalidate>
        <div class="cc-form">
          <input type="hidden" id="passwordAccountId">
          <div class="cc-field full">
            <label for="newPassword">Máº­t kháº©u má»›i *</label>
            <input class="cc-input" id="newPassword" name="password" type="password" minlength="8" required autocomplete="new-password">
          </div>
        </div>
        <div class="cc-form-error" id="passwordFormError"></div>
        <div class="cc-modal-footer">
          <button class="cc-btn" type="button" id="cancelPasswordButton">Há»§y</button>
          <button class="cc-btn primary" type="submit" id="savePasswordButton"><i class="fa-solid fa-key"></i>Cáº­p nháº­t</button>
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
      dashboard: 'Tá»•ng quan',
      executive: 'Báº£ng Ä‘iá»u hÃ nh',
      units: 'ÄÆ¡n vá»‹',
      tenants: 'Tenant',
      accounts: 'NgÆ°á»i dÃ¹ng',
      permissions: 'PhÃ¢n quyá»n',
      monitoring: 'GiÃ¡m sÃ¡t',
      audit: 'Nháº­t kÃ½',
      configuration: 'Cáº¥u hÃ¬nh',
      notifications: 'ThÃ´ng bÃ¡o',
      ai: 'Trá»£ lÃ½ thÃ´ng minh'
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
      SYSTEM_ADMIN: 'Quáº£n trá»‹ há»‡ thá»‘ng',
      VILLAGE_ADMIN: 'Quáº£n trá»‹ thÃ´n',
      STAFF: 'CÃ¡n bá»™ nháº­p liá»‡u',
      VIEWER: 'Chá»‰ xem',
      COMMUNE_ADMIN: 'Quáº£n trá»‹ xÃ£'
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
        UNKNOWN: 'ChÆ°a kiá»ƒm tra',
        OK: 'BÃ¬nh thÆ°á»ng',
        ONLINE: 'Trá»±c tuyáº¿n',
        OFFLINE: 'Ngoáº¡i tuyáº¿n',
        CONNECTED: 'Database OK',
        DISCONNECTED: 'CÆ¡ sá»Ÿ dá»¯ liá»‡u lá»—i',
        LOCKED: 'ÄÃ£ khÃ³a',
        VALID: 'SSL há»£p lá»‡',
        INVALID: 'SSL lá»—i',
        DEGRADED: 'Suy giáº£m',
        INFO: 'ThÃ´ng tin',
        WARN: 'Cáº£nh bÃ¡o',
        ERROR: 'Lá»—i',
        NOT_APPLICABLE: 'KhÃ´ng Ã¡p dá»¥ng',
        READY: 'Sáºµn sÃ ng',
        DELETED: 'ÄÃ£ xÃ³a má»m',
        CREATING: 'Äang táº¡o',
        FAILED: 'Lá»—i',
        DISABLED: 'ÄÃ£ khÃ³a',
        MAINTENANCE: 'Báº£o trÃ¬',
        DRY_RUN_PASSED: 'Cháº¡y thá»­ Ä‘áº¡t',
        WAITING_MANUAL: 'Chá» thao tÃ¡c thá»§ cÃ´ng',
        RUNNING: 'Äang cháº¡y',
        DONE: 'HoÃ n táº¥t',
        ROLLED_BACK: 'ÄÃ£ hoÃ n tÃ¡c',
        ACTIVE: 'Äang hoáº¡t Ä‘á»™ng',
        INACTIVE: 'ÄÃ£ khÃ³a',
        HIGH: 'Cao',
        MEDIUM: 'Trung bÃ¬nh',
        LOW: 'Tháº¥p'
      };
      return labels[value] || value || 'ChÆ°a kiá»ƒm tra';
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
      document.getElementById('currentUserLabel').textContent = loggedIn ? `${user.displayName || user.email} - ${roleLabels[user.role] || user.role}` : 'ChÆ°a Ä‘Äƒng nháº­p';
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
      if (!payload.ok) throw new Error(payload.message || 'YÃªu cáº§u khÃ´ng thÃ nh cÃ´ng');
      return payload.data;
    }

    async function login(event) {
      event.preventDefault();
      const button = document.getElementById('loginButton');
      const error = document.getElementById('loginError');
      const username = formValue('loginUsername');
      const password = formValue('loginPassword');
      if (!username || !password) {
        error.textContent = 'Vui lÃ²ng nháº­p tÃ i khoáº£n vÃ  máº­t kháº©u';
        return;
      }
      button.disabled = true;
      error.textContent = '';
      try {
        const result = await api('/api/control-center/login', { method: 'POST', body: { username, password } });
        setSession(result);
        await loadControlCenter();
      } catch (loginError) {
        error.textContent = loginError.message || 'ÄÄƒng nháº­p khÃ´ng thÃ nh cÃ´ng';
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
        ['Tá»•ng sá»‘ Ä‘Æ¡n vá»‹', nf.format(data.totalUnits), 'ÄÆ¡n vá»‹ Ä‘ang quáº£n lÃ½'],
        ['ÄÆ¡n vá»‹ Ä‘ang hoáº¡t Ä‘á»™ng', nf.format(data.activeUnits || 0), 'Theo registry Ä‘Æ¡n vá»‹'],
        ['Trang web trá»±c tuyáº¿n', nf.format(data.websiteOnlineUnits || 0), 'ÄÆ¡n vá»‹ cÃ³ trang web Ä‘ang truy cáº­p Ä‘Æ°á»£c'],
        ['CÆ¡ sá»Ÿ dá»¯ liá»‡u OK', nf.format(data.databaseConnectedUnits || 0), 'ÄÆ¡n vá»‹ káº¿t ná»‘i cÆ¡ sá»Ÿ dá»¯ liá»‡u thÃ nh cÃ´ng'],
        ['ÄÆ¡n vá»‹ lá»—i trang web', nf.format(data.websiteOfflineUnits || 0), 'Cáº§n kiá»ƒm tra tÃªn miá»n/mÃ¡y chá»§ lÆ°u trá»¯'],
        ['ÄÆ¡n vá»‹ lá»—i cÆ¡ sá»Ÿ dá»¯ liá»‡u', nf.format(data.databaseDisconnectedUnits || 0), 'Cáº§n kiá»ƒm tra cáº¥u hÃ¬nh cÆ¡ sá»Ÿ dá»¯ liá»‡u'],
        ['Tá»•ng há»™', nf.format(data.totalHouseholds), 'Tá»•ng há»£p toÃ n há»‡ thá»‘ng'],
        ['Tá»•ng ngÆ°á»i dÃ¹ng', nf.format(accountState.items.length), 'TÃ i khoáº£n trong Community Control Center'],
        ['Tá»•ng tráº» em', nf.format(data.totalChildren), 'Sá»‘ liá»‡u tá»•ng há»£p'],
        ['Tá»•ng ngÆ°á»i cao tuá»•i', nf.format(data.totalElderly), 'Theo cáº¥u hÃ¬nh chÃ­nh sÃ¡ch hiá»‡n cÃ³'],
        ['Tá»•ng lao Ä‘á»™ng', nf.format(data.totalWorkers), 'Theo trÆ°á»ng lao Ä‘á»™ng hiá»‡n cÃ³'],
        ['Tá»•ng Äáº£ng viÃªn', nf.format(data.totalPartyMembers), 'Sá»‘ liá»‡u tá»•ng há»£p'],
        ['Tá»•ng tá»· lá»‡ BHYT', percent(data.healthInsuranceRate), 'TrÃªn nhÃ¢n kháº©u cÃ²n sá»‘ng']
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
        ['ÄÆ¡n vá»‹ Ä‘ang hoáº¡t Ä‘á»™ng', nf.format(data.activeUnits || 0), 'ÄÆ¡n vá»‹ Ä‘ang á»Ÿ tráº¡ng thÃ¡i hoáº¡t Ä‘á»™ng'],
        ['Trang web trá»±c tuyáº¿n', nf.format(data.websiteOnlineUnits || 0), 'Trang web truy cáº­p Ä‘Æ°á»£c qua HTTPS'],
        ['Database OK', nf.format(data.databaseConnectedUnits || 0), 'Database káº¿t ná»‘i thÃ nh cÃ´ng'],
        ['Cáº§n xá»­ lÃ½', nf.format((data.operations || []).length), 'Cáº£nh bÃ¡o váº­n hÃ nh cáº§n theo dÃµi']
      ];
      const healthItems = [
        ['Tá»•ng Ä‘Æ¡n vá»‹', nf.format(data.totalUnits || 0)],
        ['ÄÆ¡n vá»‹ bá»‹ khÃ³a', nf.format(data.lockedUnits || 0)],
        ['Trang web lá»—i', nf.format(data.websiteOfflineUnits || 0)],
        ['CÆ¡ sá»Ÿ dá»¯ liá»‡u lá»—i', nf.format(data.databaseDisconnectedUnits || 0)],
        ['Báº£n sao lÆ°u gáº§n nháº¥t', data.latestBackupAt || 'ChÆ°a cÃ³ dá»¯ liá»‡u'],
        ['PhiÃªn báº£n', formatVersions(data.versions)]
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
        holder.replaceChildren(stateMessage('HÃ´m nay chÆ°a cÃ³ viá»‡c cáº§n xá»­ lÃ½ ngay.'));
        return;
      }
      holder.replaceChildren(...items.map((item) => {
        const row = document.createElement('div');
        row.className = 'operation-item';
        const main = document.createElement('div');
        const title = document.createElement('div');
        title.className = 'operation-title';
        title.textContent = (item.message || 'Cáº§n xá»­ lÃ½') + ' - ' + (item.tenant?.name || item.tenant?.code || 'ÄÆ¡n vá»‹');
        const meta = document.createElement('div');
        meta.className = 'cc-meta';
        meta.textContent = 'Má»©c Ä‘á»™: ' + statusLabel(item.severity) + ' | NgÆ°á»i phá»¥ trÃ¡ch: ' + (item.tenant?.manager || 'ChÆ°a gÃ¡n');
        main.append(title, meta);
        const actions = document.createElement('div');
        actions.className = 'operation-actions';
        const unit = operationUnit(item);
        if (item.primaryAction === 'check_website') {
          const check = actionButton('Kiá»ƒm tra trang web', 'fa-globe');
          bindControlCenterAction(check, 'unit.checkWebsite', {
            unitId: unit.id,
            unitCode: unit.code,
            unitName: unit.name,
            unitDomain: unit.domain
          });
          actions.appendChild(check);
        } else if (item.primaryAction === 'check_database') {
          const check = actionButton('Kiá»ƒm tra cÆ¡ sá»Ÿ dá»¯ liá»‡u', 'fa-database');
          bindControlCenterAction(check, 'unit.checkDatabase', {
            unitId: unit.id,
            unitCode: unit.code,
            unitName: unit.name,
            unitDomain: unit.domain
          });
          actions.appendChild(check);
        }
        const view = actionButton('Xem Ä‘Æ¡n vá»‹', 'fa-sitemap');
        bindControlCenterAction(view, 'unit.focus', {
          unitCode: item.tenant?.code || '',
          unitName: item.tenant?.name || ''
        });
        actions.appendChild(view);
        if (item.tenant?.domain) {
          const portal = actionButton('Má»Ÿ cá»•ng Ä‘Æ¡n vá»‹', 'fa-arrow-up-right-from-square');
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
        holder.replaceChildren(stateMessage('ChÆ°a cÃ³ hoáº¡t Ä‘á»™ng quáº£n trá»‹ gáº§n Ä‘Ã¢y.'));
        return;
      }
      holder.replaceChildren(...items.map((item) => {
        const row = document.createElement('div');
        row.className = 'operation-item';
        const main = document.createElement('div');
        const title = document.createElement('div');
        title.className = 'operation-title';
        title.textContent = item.message || item.action || 'Hoáº¡t Ä‘á»™ng';
        const meta = document.createElement('div');
        meta.className = 'cc-meta';
        meta.textContent = `${item.createdAt || '-'} | ${item.tenantName || 'Há»‡ thá»‘ng'} | ${item.actor || '-'}`;
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
      body.replaceChildren(stateRow(10, 'Äang táº£i dá»¯ liá»‡u...'));
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
          const portal = actionButton('Má»Ÿ cá»•ng Ä‘Æ¡n vá»‹', 'fa-arrow-up-right-from-square');
          bindControlCenterAction(portal, 'unit.portal', { unitId: unit.id });
          actions.appendChild(portal);
        }
        const checkWebsite = actionButton('Trang web', 'fa-globe');
        bindControlCenterAction(checkWebsite, 'unit.checkWebsite', { unitId: unit.id });
        actions.appendChild(checkWebsite);
        const checkDatabase = actionButton('CÆ¡ sá»Ÿ dá»¯ liá»‡u', 'fa-database');
        bindControlCenterAction(checkDatabase, 'unit.checkDatabase', { unitId: unit.id });
        actions.appendChild(checkDatabase);
        const edit = actionButton('Sá»­a', 'fa-pen-to-square');
        bindControlCenterAction(edit, 'unit.edit', { unitId: unit.id });
        actions.appendChild(edit);
        if (unit.status === 'READY' || unit.status === 'ACTIVE') {
          const lock = actionButton('KhÃ³a', 'fa-lock', 'danger');
          bindControlCenterAction(lock, 'unit.status', { unitId: unit.id, unitStatusAction: 'lock' });
          actions.appendChild(lock);
        } else {
          const activate = actionButton('KÃ­ch hoáº¡t', 'fa-unlock');
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
      body.replaceChildren(stateRow(9, 'Äang táº£i danh sÃ¡ch Tenant...'));
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
        setTenantsAlert(error.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c danh sÃ¡ch Tenant');
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
          const edit = actionButton('Sá»­a', 'fa-pen-to-square');
          edit.dataset.tenantPermission = 'tenant.update';
          bindControlCenterAction(edit, 'tenant.edit', { tenantId: tenant.id });
          actions.appendChild(edit);
        }
        if (canTenant('tenant.lock') && !['LOCKED', 'DELETED'].includes(tenant.status)) {
          const lock = actionButton('KhÃ³a', 'fa-lock', 'danger');
          lock.dataset.tenantPermission = 'tenant.lock';
          bindControlCenterAction(lock, 'tenant.lock', { tenantId: tenant.id });
          actions.appendChild(lock);
        }
        if (canTenant('tenant.unlock') && tenant.status === 'LOCKED') {
          const unlock = actionButton('Má»Ÿ khÃ³a', 'fa-unlock');
          unlock.dataset.tenantPermission = 'tenant.unlock';
          bindControlCenterAction(unlock, 'tenant.unlock', { tenantId: tenant.id });
          actions.appendChild(unlock);
        }
        if (canTenant('tenant.delete') && tenant.status !== 'DELETED') {
          const remove = actionButton('XÃ³a má»m', 'fa-trash', 'danger');
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
        : 'ChÆ°a cÃ³ Tenant phÃ¹ há»£p';
      document.getElementById('tenantPrevPageButton').disabled = tenantState.page <= 1 || tenantState.loading;
      document.getElementById('tenantNextPageButton').disabled = tenantState.page >= tenantState.totalPages || tenantState.loading;
    }

    function renderTenantVersionFilter() {
      const select = document.getElementById('tenantVersionFilter');
      const current = select.value;
      const versions = Array.from(new Set((tenantState.items || []).map((tenant) => tenant.appVersion || '').filter(Boolean))).sort();
      const options = [new Option('Táº¥t cáº£ phiÃªn báº£n', '')].concat(versions.map((version) => new Option(version, version)));
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
      document.getElementById('tenantModalTitle').textContent = tenant ? 'Sá»­a Tenant' : 'ThÃªm Tenant';
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
      if (!tenantState.editing && !/^[a-z0-9_-]{2,50}$/.test(payload.code || '')) return 'MÃ£ Tenant khÃ´ng há»£p lá»‡';
      if (!payload.name || payload.name.length > 190) return 'TÃªn Tenant lÃ  báº¯t buá»™c vÃ  khÃ´ng vÆ°á»£t quÃ¡ 190 kÃ½ tá»±';
      if (!payload.domain && !payload.subdomain) return 'Tenant cáº§n cÃ³ domain hoáº·c subdomain';
      if (!payload.database_host) return 'Database host lÃ  báº¯t buá»™c';
      if (!payload.database_name || !/^[a-zA-Z0-9_]{1,190}$/.test(payload.database_name)) return 'Database name khÃ´ng há»£p lá»‡';
      if (payload.database_charset && !/^[a-z0-9_]{1,50}$/.test(payload.database_charset)) return 'Database charset khÃ´ng há»£p lá»‡';
      if (payload.storage_quota_bytes !== null && (!/^\d+$/.test(String(payload.storage_quota_bytes)) || Number(payload.storage_quota_bytes) < 0)) return 'Dung lÆ°á»£ng giá»›i háº¡n khÃ´ng há»£p lá»‡';
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
          setTenantsAlert('ÄÃ£ cáº­p nháº­t Tenant');
        } else {
          await api('/api/control-center/tenants', { method: 'POST', body: payload });
          tenantState.page = 1;
          setTenantsAlert('ÄÃ£ thÃªm Tenant');
        }
        closeTenantModal();
        await loadTenants();
      } catch (error) {
        setTenantFormError(error.message || 'KhÃ´ng lÆ°u Ä‘Æ°á»£c Tenant');
      } finally {
        button.disabled = false;
      }
    }

    async function openTenantDetail(tenant) {
      tenantState.detail = tenant;
      tenantState.activityTarget = tenant;
      document.getElementById('tenantDetailTitle').textContent = 'Chi tiáº¿t Tenant - ' + (tenant.name || tenant.code || tenant.id);
      renderTenantDetail(tenant);
      document.getElementById('tenantDetailModal').classList.add('active');
      await loadTenantActivity(tenant).catch((error) => {
        document.getElementById('tenantActivityBody').replaceChildren(stateRow(5, error.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c Activity'));
      });
    }

    function renderTenantDetail(tenant) {
      const items = [
        ['MÃ£ Tenant', tenant.code || '-'],
        ['TÃªn Tenant', tenant.name || '-'],
        ['TÃªn Ä‘Æ¡n vá»‹', tenant.unitName || '-'],
        ['XÃ£/PhÆ°á»ng', tenant.communeName || '-'],
        ['Domain', tenant.domain || '-'],
        ['Subdomain', tenant.subdomain || '-'],
        ['Database', tenant.databaseName || '-'],
        ['Tráº¡ng thÃ¡i', statusLabel(tenant.status || '')],
        ['PhiÃªn báº£n á»©ng dá»¥ng', tenant.appVersion || '-'],
        ['PhiÃªn báº£n build', tenant.buildVersion || '-'],
        ['PhiÃªn báº£n schema', tenant.schemaVersion || '-'],
        ['Dung lÆ°á»£ng', tenantStorageLabel(tenant)],
        ['Trang web', statusLabel(tenant.websiteStatus || 'UNKNOWN')],
        ['Database status', statusLabel(tenant.databaseStatus || 'UNKNOWN')],
        ['SSL', statusLabel(tenant.sslStatus || 'UNKNOWN')],
        ['NgÆ°á»i quáº£n lÃ½', tenant.managerName || '-'],
        ['Cáº­p nháº­t', tenant.updatedAt || '-'],
        ['Ghi chÃº', tenant.notes || '-', 'full']
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
      body.replaceChildren(stateRow(5, 'Äang táº£i Activity...'));
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
      const reason = prompt('Nháº­p lÃ½ do khÃ³a Tenant');
      if (reason === null) return;
      if (!reason.trim()) {
        setTenantsAlert('LÃ½ do khÃ³a Tenant lÃ  báº¯t buá»™c');
        return;
      }
      try {
        await api('/api/control-center/tenants/' + encodeURIComponent(tenant.id) + '/lock', { method: 'PATCH', body: { reason: reason.trim() } });
        setTenantsAlert('ÄÃ£ khÃ³a Tenant');
        await loadTenants();
      } catch (error) {
        setTenantsAlert(error.message || 'KhÃ´ng khÃ³a Ä‘Æ°á»£c Tenant');
      }
    }

    async function unlockTenant(tenant) {
      if (!confirm('XÃ¡c nháº­n má»Ÿ khÃ³a Tenant nÃ y?')) return;
      try {
        await api('/api/control-center/tenants/' + encodeURIComponent(tenant.id) + '/unlock', { method: 'PATCH', body: { targetStatus: 'ACTIVE' } });
        setTenantsAlert('ÄÃ£ má»Ÿ khÃ³a Tenant');
        await loadTenants();
      } catch (error) {
        setTenantsAlert(error.message || 'KhÃ´ng má»Ÿ khÃ³a Ä‘Æ°á»£c Tenant');
      }
    }

    async function deleteTenant(tenant) {
      const confirmation = prompt('Nháº­p mÃ£ Tenant Ä‘á»ƒ xÃ¡c nháº­n xÃ³a má»m');
      if (confirmation === null) return;
      if (confirmation.trim() !== tenant.code) {
        setTenantsAlert('MÃ£ Tenant xÃ¡c nháº­n khÃ´ng khá»›p');
        return;
      }
      try {
        await api('/api/control-center/tenants/' + encodeURIComponent(tenant.id), { method: 'DELETE', body: { confirmation: confirmation.trim() } });
        setTenantsAlert('ÄÃ£ xÃ³a má»m Tenant');
        await loadTenants();
      } catch (error) {
        setTenantsAlert(error.message || 'KhÃ´ng xÃ³a má»m Ä‘Æ°á»£c Tenant');
      }
    }

    async function loadAccounts() {
      const body = document.getElementById('accountsBody');
      body.replaceChildren(stateRow(10, 'Äang táº£i dá»¯ liá»‡u...'));
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

          [roleLabels[account.role] || account.role, account.unitName || '-', account.status, account.lastLoginLabel || account.lastLoginAt || 'ChÆ°a Ä‘Äƒng nháº­p', account.lastIp || '-', account.lastDevice || '-', account.createdAt || '-', account.createdBy || '-'].forEach((cell, index) => {
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
          const edit = actionButton('Sá»­a', 'fa-user-pen');
          bindControlCenterAction(edit, 'account.edit', { accountId: account.id });
          actions.appendChild(edit);
          const password = actionButton('Máº­t kháº©u', 'fa-key');
          bindControlCenterAction(password, 'account.password', { accountId: account.id });
          actions.appendChild(password);
          if (account.status === 'ACTIVE') {
            const deactivate = actionButton('Ngá»«ng', 'fa-user-slash', 'danger');
            bindControlCenterAction(deactivate, 'account.status', { accountId: account.id, accountStatusAction: 'deactivate' });
            actions.appendChild(deactivate);
          } else {
            const activate = actionButton('KÃ­ch hoáº¡t', 'fa-user-check');
            bindControlCenterAction(activate, 'account.status', { accountId: account.id, accountStatusAction: 'activate' });
            actions.appendChild(activate);
          }
          tr.appendChild(actions);
          return tr;
        });
        body.replaceChildren(...(rows.length ? rows : [emptyRow(10)]));
      } catch (error) {
        body.replaceChildren(emptyRow(10));
        setAccountsAlert(error.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c danh sÃ¡ch tÃ i khoáº£n');
      }
    }

    async function loadPermissions() {
      const head = document.getElementById('permissionsHead');
      const body = document.getElementById('permissionsBody');
      body.replaceChildren(stateRow(2, 'Äang táº£i phÃ¢n quyá»n...'));
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
        body.replaceChildren(stateRow(2, 'KhÃ´ng táº£i Ä‘Æ°á»£c phÃ¢n quyá»n'));
        setPermissionsAlert(error.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c phÃ¢n quyá»n');
      }
    }

    function renderPermissionRoleFilter() {
      const select = document.getElementById('permissionRoleFilter');
      const current = select.value;
      const options = [new Option('Táº¥t cáº£ vai trÃ²', '')].concat(permissionState.roles.map((role) => new Option(role.label || role.role, role.role)));
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
      holder.replaceChildren(...(groups.length ? groups : [stateMessage('ChÆ°a cÃ³ quyá»n')]));
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
      ['Quyá»n'].concat(roles.map((role) => role.label || role.role)).forEach((label) => {
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
          checkbox.title = item.locked ? 'Quyá»n cá»‘t lÃµi khÃ´ng thá»ƒ táº¯t' : '';
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
        setPermissionsAlert(error.message || 'KhÃ´ng lÆ°u Ä‘Æ°á»£c phÃ¢n quyá»n');
      } finally {
        button.disabled = permissionState.pending.size === 0;
      }
    }

    async function loadMonitoring() {
      const data = await api('/api/control-center/monitoring');
      const usedBytes = Math.max(0, Number(data.storage.totalBytes || 0) - Number(data.storage.freeBytes || 0));
      const items = [
        ['PhiÃªn báº£n', data.version],
        ['MÃ´i trÆ°á»ng cháº¡y', `PHP ${data.runtime.phpVersion}`],
        ['Tráº¡ng thÃ¡i cÆ¡ sá»Ÿ dá»¯ liá»‡u', data.database.ok ? 'ÄÃ£ káº¿t ná»‘i' : 'KhÃ´ng kháº£ dá»¥ng'],
        ['LÆ°u trá»¯', `${formatBytes(usedBytes)} / ${formatBytes(data.storage.totalBytes)}`],
        ['Quyá»n ghi lÆ°u trá»¯', data.storage.writable ? 'BÃ¬nh thÆ°á»ng' : 'Suy giáº£m'],
        ['Kiá»ƒm tra sá»©c khá»e', data.healthCheck.status]
      ];
      document.getElementById('healthBadge').textContent = statusLabel(data.healthCheck.status);
      document.getElementById('healthBadge').className = data.healthCheck.status === 'OK' ? 'cc-badge' : 'cc-badge warn';
      const tenantPanel = document.createElement('div');
      tenantPanel.className = 'cc-panel full';
      const header = document.createElement('div');
      header.className = 'cc-panel-header';
      const title = document.createElement('h2');
      title.className = 'cc-panel-title';
      title.textContent = 'Tráº¡ng thÃ¡i Ä‘Æ¡n vá»‹';
      header.appendChild(title);
      const tableWrap = document.createElement('div');
      tableWrap.className = 'cc-table-wrap';
      const table = document.createElement('table');
      table.className = 'cc-table';
      const head = document.createElement('thead');
      const headRow = document.createElement('tr');
        ['ÄÆ¡n vá»‹', 'TÃªn miá»n', 'Trang web', 'CÆ¡ sá»Ÿ dá»¯ liá»‡u', 'SSL', 'PhiÃªn báº£n', 'Láº§n kiá»ƒm tra', 'Lá»—i gáº§n nháº¥t'].forEach((label) => {
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
      body.replaceChildren(stateRow(6, 'Äang táº£i nháº­t kÃ½...'));
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
      const options = [new Option('Táº¥t cáº£ Ä‘Æ¡n vá»‹', '')].concat((unitState.items || []).map((unit) => new Option(unit.name || unit.code, unit.id)));
      select.replaceChildren(...options);
      select.value = current;
    }

    function emptyRow(colspan) {
      return stateRow(colspan, 'ChÆ°a cÃ³ dá»¯ liá»‡u hiá»ƒn thá»‹');
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
        'TÃªn: ' + (account.displayName || account.username || account.email || '-'),
        'ThÆ° Ä‘iá»‡n tá»­: ' + (account.email || '-'),
        'ÄÆ¡n vá»‹: ' + (account.unitName || '-'),
        'Vai trÃ²: ' + (roleLabels[account.role] || account.role || '-'),
        'Tráº¡ng thÃ¡i: ' + statusLabel(account.status || ''),
        'ÄÄƒng nháº­p cuá»‘i: ' + (account.lastLoginLabel || account.lastLoginAt || 'ChÆ°a Ä‘Äƒng nháº­p'),
        'NgÆ°á»i táº¡o: ' + (account.createdBy || '-')
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
      document.getElementById('unitModalTitle').textContent = unit ? 'Sá»­a Ä‘Æ¡n vá»‹' : 'ThÃªm Ä‘Æ¡n vá»‹';
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
      document.getElementById('unitManagerName').value = unit?.manager === 'ChÆ°a gÃ¡n' || unit?.manager === 'Chua gan' ? '' : (unit?.manager || '');
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
      document.getElementById('saveUnitButton').innerHTML = '<i class="fa-solid fa-floppy-disk"></i>' + (unit ? 'LÆ°u' : 'Táº¡o Ä‘Æ¡n vá»‹');
      setFormError('');
      renderDatabaseCheck(null);
      renderPreflight(null);
      renderInfrastructureVerification(null);
      document.getElementById('tenantCreatePanel').textContent = 'Sáºµn sÃ ng táº¡o Ä‘Æ¡n vá»‹';
      document.getElementById('tenantCreatePanel').classList.remove('active');
      document.getElementById('tenantInstallStatusBadge').textContent = 'Pending';
      document.getElementById('tenantInstallStatusBadge').className = 'cc-badge warn';
      document.getElementById('tenantInstallProgressBar').style.width = '0%';
      document.getElementById('tenantInstallLogList').replaceChildren();
      document.getElementById('tenantHealthPanel').textContent = 'ChÆ°a cháº¡y kiá»ƒm tra sau cÃ i Ä‘áº·t';
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
        return 'MÃ£ Ä‘Æ¡n vá»‹ chá»‰ gá»“m chá»¯ thÆ°á»ng, sá»‘, dáº¥u gáº¡ch ngang/gáº¡ch dÆ°á»›i vÃ  tá»« 2 Ä‘áº¿n 50 kÃ½ tá»±';
      }
      if (!payload.name || payload.name.length > 190) {
        return 'TÃªn Ä‘Æ¡n vá»‹ lÃ  báº¯t buá»™c vÃ  khÃ´ng vÆ°á»£t quÃ¡ 190 kÃ½ tá»±';
      }
      if (payload.database_name && !/^[a-zA-Z0-9_]{1,190}$/.test(payload.database_name)) {
        return 'TÃªn cÆ¡ sá»Ÿ dá»¯ liá»‡u chá»‰ gá»“m chá»¯, sá»‘ vÃ  dáº¥u gáº¡ch dÆ°á»›i';
      }
      if (!unitState.editing && !payload.database_username) {
        return 'NgÆ°á»i dÃ¹ng cÆ¡ sá»Ÿ dá»¯ liá»‡u lÃ  báº¯t buá»™c Ä‘á»ƒ khá»Ÿi táº¡o Ä‘Æ¡n vá»‹';
      }
      if (payload.database_charset && !/^[a-z0-9_]{1,50}$/.test(payload.database_charset)) {
        return 'Báº£ng mÃ£ cÆ¡ sá»Ÿ dá»¯ liá»‡u khÃ´ng há»£p lá»‡';
      }
      return '';
    }

    function validateTenantWizardStep(payload, step) {
      if (step === 1) {
        if (!unitState.editing && !/^[a-z0-9_-]{2,50}$/.test(payload.code || '')) {
          return 'MÃ£ Ä‘Æ¡n vá»‹ chá»‰ gá»“m chá»¯ thÆ°á»ng, sá»‘, dáº¥u gáº¡ch ngang/gáº¡ch dÆ°á»›i vÃ  tá»« 2 Ä‘áº¿n 50 kÃ½ tá»±';
        }
        if (!payload.name || payload.name.length > 190) {
          return 'TÃªn Ä‘Æ¡n vá»‹ lÃ  báº¯t buá»™c vÃ  khÃ´ng vÆ°á»£t quÃ¡ 190 kÃ½ tá»±';
        }
        if (!payload.domain && !payload.subdomain) {
          return 'TÃªn miá»n hoáº·c tÃªn miá»n phá»¥ lÃ  báº¯t buá»™c';
        }
      }
      if (step === 2) {
        if (!payload.database_host) return 'MÃ¡y chá»§ cÆ¡ sá»Ÿ dá»¯ liá»‡u lÃ  báº¯t buá»™c';
        if (!payload.database_name || !/^[a-zA-Z0-9_]{1,190}$/.test(payload.database_name)) {
          return 'TÃªn cÆ¡ sá»Ÿ dá»¯ liá»‡u chá»‰ gá»“m chá»¯, sá»‘ vÃ  dáº¥u gáº¡ch dÆ°á»›i';
        }
        if (!payload.database_username) return 'NgÆ°á»i dÃ¹ng cÆ¡ sá»Ÿ dá»¯ liá»‡u lÃ  báº¯t buá»™c';
        if (!payload.database_password) return 'Máº­t kháº©u cÆ¡ sá»Ÿ dá»¯ liá»‡u lÃ  báº¯t buá»™c';
        if (payload.database_charset && !/^[a-z0-9_]{1,50}$/.test(payload.database_charset)) {
          return 'Báº£ng mÃ£ cÆ¡ sá»Ÿ dá»¯ liá»‡u khÃ´ng há»£p lá»‡';
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
      status.textContent = result.ready ? readyText : 'Lá»—i';
      status.className = 'preflight-status ' + (result.ready ? 'ready' : 'failed');
      (result.items || []).forEach((item) => {
        const row = document.createElement('div');
        row.className = 'preflight-item';
        const icon = document.createElement('div');
        icon.className = 'preflight-icon ' + (item.status === 'PASS' ? 'pass' : 'fail');
        icon.textContent = item.status === 'PASS' ? 'âœ“' : '!';
        const label = document.createElement('div');
        label.textContent = item.label || item.key || '';
        const detail = document.createElement('div');
        detail.textContent = item.status === 'PASS' ? (item.message || 'Äáº¡t') : ((item.message || 'KhÃ´ng Ä‘áº¡t') + (item.fix ? ' - ' + item.fix : ''));
        if (item.status !== 'PASS') detail.className = 'preflight-fix';
        row.append(icon, label, detail);
        list.appendChild(row);
      });
    }

    function renderDatabaseCheck(result) {
      renderChecklist(result, 'tenantDatabasePanel', 'tenantDatabaseStatus', 'tenantDatabaseList', 'CÆ¡ sá»Ÿ dá»¯ liá»‡u sáºµn sÃ ng', 'ChÆ°a kiá»ƒm tra cÆ¡ sá»Ÿ dá»¯ liá»‡u');
    }

    function renderPreflight(result) {
      renderChecklist(result, 'tenantPreflightPanel', 'tenantPreflightStatus', 'tenantPreflightList', 'Sáºµn sÃ ng táº¡o Ä‘Æ¡n vá»‹', 'ChÆ°a cháº¡y tiá»n kiá»ƒm');
    }

    function renderInfrastructureVerification(result) {
      const keys = ['installation_profile', 'source_writable', 'storage_writable', 'upload_writable', 'backup_writable'];
      const items = (result?.items || []).filter((item) => keys.includes(item.key));
      renderChecklist({ ready: Boolean(result?.ready), items }, 'tenantInfrastructurePanel', 'tenantInfrastructureStatus', 'tenantInfrastructureList', 'Háº¡ táº§ng Ä‘Ã£ sáºµn sÃ ng', 'ChÆ°a xÃ¡c minh háº¡ táº§ng');
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
        setUnitsAlert(error.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c tráº¡ng thÃ¡i cÃ i Ä‘áº·t Tenant');
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
        setUnitsAlert(error.message || 'KhÃ´ng khÃ´i phá»¥c Ä‘Æ°á»£c tráº¡ng thÃ¡i cÃ i Ä‘áº·t Tenant');
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
      document.getElementById('tenantCreatePanel').textContent = 'Sáºµn sÃ ng táº¡o Ä‘Æ¡n vá»‹';
      document.getElementById('tenantCreatePanel').classList.remove('active');
      document.getElementById('tenantInstallStatusBadge').textContent = 'Pending';
      document.getElementById('tenantInstallStatusBadge').className = 'cc-badge warn';
      document.getElementById('tenantInstallProgressBar').style.width = '0%';
      document.getElementById('tenantInstallLogList').replaceChildren();
      document.getElementById('tenantHealthPanel').textContent = 'ChÆ°a cháº¡y kiá»ƒm tra sau cÃ i Ä‘áº·t';
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
        setFormError('Cáº§n kiá»ƒm tra káº¿t ná»‘i cÆ¡ sá»Ÿ dá»¯ liá»‡u Ä‘áº¡t trÆ°á»›c khi sang bÆ°á»›c tiá»n kiá»ƒm');
        return;
      }
      if (unitState.wizardStep === 4 && !unitState.preflightReady) {
        setFormError('Cáº§n xÃ¡c minh háº¡ táº§ng Ä‘áº¡t trÆ°á»›c khi xÃ¡c nháº­n cÃ i Ä‘áº·t Tenant');
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
            throw new Error('Cáº§n tiá»n kiá»ƒm Ä‘áº¡t trÆ°á»›c khi táº¡o Ä‘Æ¡n vá»‹');
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
        setFormError(error.message || 'KhÃ´ng lÆ°u Ä‘Æ°á»£c Ä‘Æ¡n vá»‹');
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
          setFormError(result.message || 'Kiá»ƒm tra cÆ¡ sá»Ÿ dá»¯ liá»‡u cÃ²n má»¥c khÃ´ng Ä‘áº¡t');
          return;
        }
        setFormError('CÆ¡ sá»Ÿ dá»¯ liá»‡u sáºµn sÃ ng. CÃ³ thá»ƒ chuyá»ƒn sang bÆ°á»›c tiá»n kiá»ƒm.');
        setTenantWizardStep(3);
      } catch (error) {
        renderDatabaseCheck(null);
        setFormError(error.message || 'KhÃ´ng kiá»ƒm tra Ä‘Æ°á»£c cÆ¡ sá»Ÿ dá»¯ liá»‡u');
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
        setFormError('Cáº§n kiá»ƒm tra káº¿t ná»‘i cÆ¡ sá»Ÿ dá»¯ liá»‡u Ä‘áº¡t trÆ°á»›c khi tiá»n kiá»ƒm');
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
          setFormError(result.message || 'Tiá»n kiá»ƒm cÃ²n má»¥c khÃ´ng Ä‘áº¡t');
          return;
        }
        setFormError('Sáºµn sÃ ng táº¡o Ä‘Æ¡n vá»‹. CÃ³ thá»ƒ báº¥m Táº¡o Ä‘Æ¡n vá»‹.');
        setTenantWizardStep(4);
      } catch (error) {
        renderPreflight(null);
        setFormError(error.message || 'Tiá»n kiá»ƒm khÃ´ng thÃ nh cÃ´ng');
      } finally {
        button.disabled = false;
      }
    }

    async function changeUnitStatus(unit, action) {
      const isLock = action === 'lock';
      const message = isLock ? 'XÃ¡c nháº­n khÃ³a Ä‘Æ¡n vá»‹ nÃ y?' : 'XÃ¡c nháº­n kÃ­ch hoáº¡t Ä‘Æ¡n vá»‹ nÃ y?';
      if (!confirm(message)) return;
      setUnitsAlert('');
      try {
        await api('/api/control-center/units/' + encodeURIComponent(unit.id) + '/' + action, { method: 'PATCH' });
        await loadUnits();
      } catch (error) {
        setUnitsAlert(error.message || 'KhÃ´ng cáº­p nháº­t Ä‘Æ°á»£c tráº¡ng thÃ¡i Ä‘Æ¡n vá»‹');
      }
    }

    function installerStepLabel(step) {
      const labels = {
        preflight_check: 'kiá»ƒm tra Ä‘iá»u kiá»‡n',
        verify_infrastructure_prerequisites: 'xÃ¡c minh háº¡ táº§ng',
        verify_database_connection: 'kiá»ƒm tra káº¿t ná»‘i Database',
        verify_database_privileges: 'kiá»ƒm tra quyá»n Database',
        validate_input: 'kiá»ƒm tra dá»¯ liá»‡u',
        check_domain: 'kiá»ƒm tra tÃªn miá»n',
        check_database_connection: 'kiá»ƒm tra káº¿t ná»‘i cÆ¡ sá»Ÿ dá»¯ liá»‡u',
        verify_database_ready: 'xÃ¡c minh cÆ¡ sá»Ÿ dá»¯ liá»‡u',
        initialize_database: 'khá»Ÿi táº¡o cÆ¡ sá»Ÿ dá»¯ liá»‡u',
        import_schema: 'náº¡p cáº¥u trÃºc dá»¯ liá»‡u',
        generate_env: 'táº¡o file .env',
        initialize_tenant: 'khá»Ÿi táº¡o Tenant',
        import_seed: 'náº¡p dá»¯ liá»‡u máº«u',
        create_tenant_record: 'ghi nháº­n Ä‘Æ¡n vá»‹',
        create_admin: 'táº¡o tÃ i khoáº£n quáº£n trá»‹',
        write_config: 'ghi cáº¥u hÃ¬nh',
        create_storage: 'táº¡o lÆ°u trá»¯',
        post_installation_verification: 'kiá»ƒm tra sau cÃ i Ä‘áº·t',
        health_check: 'kiá»ƒm tra sá»©c khá»e',
        complete: 'hoÃ n táº¥t',
        mark_ready: 'Ä‘Ã¡nh dáº¥u sáºµn sÃ ng'
      };
      return labels[step] || step || '';
    }

    function tenantInstallMessage(job) {
      const step = installerStepLabel(job.currentStep || '');
      const workflowStatus = job.workflowStatus || statusLabel(job.status || '') || 'Pending';
      const base = `Khá»Ÿi táº¡o Ä‘Æ¡n vá»‹ ${workflowStatus}: ${job.progressPercent || 0}%${step ? ' - ' + step : ''}`;
      if (job.status === 'DRY_RUN_PASSED') {
        return base + '. Cháº¡y thá»­ Ä‘áº¡t.';
      }
      if (job.status === 'READY' || workflowStatus === 'Completed') {
        const admin = job.result?.generatedAdminEmail ? ` Quáº£n trá»‹: ${job.result.generatedAdminEmail}` : '';
        return base + '. HoÃ n thÃ nh.' + admin;
      }
      if (job.status === 'WAITING_MANUAL' || workflowStatus === 'Waiting') {
        return base + '. Cáº§n thao tÃ¡c thá»§ cÃ´ng: ' + redactInstallerText(job.errorMessage || 'Kiá»ƒm tra chi tiáº¿t');
      }
      if (job.status === 'FAILED' || workflowStatus === 'Failed') {
        return base + '. Lá»—i: ' + redactInstallerText(job.errorMessage || 'KhÃ´ng rÃµ nguyÃªn nhÃ¢n');
      }
      if (job.status === 'ROLLED_BACK' || workflowStatus === 'Rolled Back') {
        return base + '. ÄÃ£ hoÃ n tÃ¡c pháº§n á»©ng dá»¥ng.';
      }
      return base;
    }

    async function retryTenantInstall() {
      if (!unitState.installerJobId) return;
      setUnitsAlert('Äang thá»­ láº¡i khá»Ÿi táº¡o Ä‘Æ¡n vá»‹...');
      try {
        const result = await api('/api/control-center/tenant-installer/' + encodeURIComponent(unitState.installerJobId) + '/retry', { method: 'POST' });
        renderTenantInstallProgress(result);
        setUnitsAlert(tenantInstallMessage(result));
        setTenantInstallerActions(result);
        setTenantWizardStep(isTenantInstallTerminal(result) ? 7 : 6);
        pollTenantInstallStatus(result.id || unitState.installerJobId);
        await loadUnits();
      } catch (error) {
        setUnitsAlert(error.message || 'KhÃ´ng thá»­ láº¡i Ä‘Æ°á»£c khá»Ÿi táº¡o Ä‘Æ¡n vá»‹');
      }
    }

    async function rollbackTenantInstall() {
      if (!unitState.installerJobId || !confirm('HoÃ n tÃ¡c tiáº¿n trÃ¬nh cÃ i Ä‘áº·t Ä‘Æ¡n vá»‹ nÃ y?')) return;
      setUnitsAlert('Äang hoÃ n tÃ¡c khá»Ÿi táº¡o Ä‘Æ¡n vá»‹...');
      try {
        const result = await api('/api/control-center/tenant-installer/' + encodeURIComponent(unitState.installerJobId) + '/rollback', { method: 'POST' });
        renderTenantInstallProgress(result);
        setUnitsAlert(tenantInstallMessage(result));
        setTenantInstallerActions(null);
        localStorage.removeItem(storageKey('tenant_installer_job_id'));
        setTenantWizardStep(7);
        await loadUnits();
      } catch (error) {
        setUnitsAlert(error.message || 'KhÃ´ng hoÃ n tÃ¡c Ä‘Æ°á»£c khá»Ÿi táº¡o Ä‘Æ¡n vá»‹');
      }
    }

    async function checkUnitConnection(unit) {
      setUnitsAlert('Äang kiá»ƒm tra cÆ¡ sá»Ÿ dá»¯ liá»‡u ' + (unit.name || unit.code || '') + '...');
      try {
        await api('/api/control-center/units/' + encodeURIComponent(unit.id) + '/check-connection', { method: 'PATCH' });
        setUnitsAlert('ÄÃ£ cáº­p nháº­t tráº¡ng thÃ¡i cÆ¡ sá»Ÿ dá»¯ liá»‡u cho ' + (unit.name || unit.code || 'Ä‘Æ¡n vá»‹'));
        await loadUnits();
      } catch (error) {
        setUnitsAlert(error.message || 'KhÃ´ng kiá»ƒm tra Ä‘Æ°á»£c cÆ¡ sá»Ÿ dá»¯ liá»‡u Ä‘Æ¡n vá»‹');
      }
    }

    async function checkUnitWebsite(unit) {
      setUnitsAlert('Äang kiá»ƒm tra trang web ' + (unit.name || unit.code || '') + '...');
      try {
        await api('/api/control-center/units/' + encodeURIComponent(unit.id) + '/check-website', { method: 'PATCH' });
        setUnitsAlert('ÄÃ£ cáº­p nháº­t tráº¡ng thÃ¡i trang web cho ' + (unit.name || unit.code || 'Ä‘Æ¡n vá»‹'));
        await loadUnits();
      } catch (error) {
        setUnitsAlert(error.message || 'KhÃ´ng kiá»ƒm tra Ä‘Æ°á»£c trang web Ä‘Æ¡n vá»‹');
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
        setUnitsAlert(error.message || 'KhÃ´ng má»Ÿ Ä‘Æ°á»£c cá»•ng Ä‘Æ¡n vá»‹');
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
        option.textContent = unit.name || unit.code || ('ÄÆ¡n vá»‹ #' + unit.id);
        if (String(unit.id) === String(selectedId)) option.selected = true;
        return option;
      });
      if (!options.length) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'ChÆ°a cÃ³ Ä‘Æ¡n vá»‹';
        options.push(option);
      }
      select.replaceChildren(...options);
    }

    async function openAccountModal(account = null) {
      accountState.editing = account;
      document.getElementById('accountModalTitle').textContent = account ? 'Sá»­a tÃ i khoáº£n' : 'ThÃªm tÃ i khoáº£n';
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
      if (!payload.display_name) return 'Há» tÃªn lÃ  báº¯t buá»™c';
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email || '')) return 'Email khÃ´ng há»£p lá»‡';
      if (!/^[a-z0-9._-]{3,60}$/.test(payload.username || '')) return 'TÃªn Ä‘Äƒng nháº­p khÃ´ng há»£p lá»‡';
      if (!['SYSTEM_ADMIN', 'VILLAGE_ADMIN', 'STAFF', 'VIEWER'].includes(payload.role)) return 'Vai trÃ² khÃ´ng há»£p lá»‡';
      if (!['ACTIVE', 'INACTIVE'].includes(payload.status)) return 'Tráº¡ng thÃ¡i khÃ´ng há»£p lá»‡';
      if (!payload.unit_id) return 'ÄÆ¡n vá»‹ lÃ  báº¯t buá»™c';
      if (creating && (!payload.password || payload.password.length < 8)) return 'Máº­t kháº©u tá»‘i thiá»ƒu 8 kÃ½ tá»±';
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
        setAccountFormError(error.message || 'KhÃ´ng lÆ°u Ä‘Æ°á»£c tÃ i khoáº£n');
      } finally {
        button.disabled = false;
      }
    }

    async function changeAccountStatus(account, action) {
      const message = action === 'deactivate' ? 'XÃ¡c nháº­n ngá»«ng sá»­ dá»¥ng tÃ i khoáº£n nÃ y?' : 'XÃ¡c nháº­n kÃ­ch hoáº¡t tÃ i khoáº£n nÃ y?';
      if (!confirm(message)) return;
      setAccountsAlert('');
      try {
        await api('/api/control-center/users/' + encodeURIComponent(account.id) + '/' + action, { method: 'PATCH' });
        await loadAccounts();
      } catch (error) {
        setAccountsAlert(error.message || 'KhÃ´ng cáº­p nháº­t Ä‘Æ°á»£c tráº¡ng thÃ¡i tÃ i khoáº£n');
      }
    }

    function openPasswordModal(account) {
      accountState.passwordTarget = account;
      document.getElementById('passwordModalTitle').textContent = 'Äáº·t láº¡i máº­t kháº©u - ' + (account.displayName || account.email);
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
        setPasswordFormError('Máº­t kháº©u tá»‘i thiá»ƒu 8 kÃ½ tá»±');
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
        setPasswordFormError(error.message || 'KhÃ´ng cáº­p nháº­t Ä‘Æ°á»£c máº­t kháº©u');
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
      ['general', 'Cáº¥u hÃ¬nh chung'],
      ['identity', 'Nháº­n diá»‡n há»‡ thá»‘ng'],
      ['tenant', 'Multi-tenant'],
      ['security', 'Báº£o máº­t & phiÃªn'],
      ['data', 'Dá»¯ liá»‡u & sao lÆ°u'],
      ['files', 'Tá»‡p & táº£i lÃªn'],
      ['email', 'Email / ThÃ´ng bÃ¡o'],
      ['system', 'Há»‡ thá»‘ng & báº£o trÃ¬']
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
      const preview = previewUrl ? '<img src="' + previewUrl.replace(/"/g, '&quot;') + '" alt="' + label + '">' : '<span>ChÆ°a cÃ³ áº£nh</span>';
      return '<article class="branding-upload-card" data-branding-card="' + type + '"><div><strong>' + label + '</strong><div class="settings-readonly">' + note + '</div></div><div class="branding-preview' + (wide ? ' wide' : '') + '">' + preview + '</div><div class="settings-readonly">' + (pending ? ('ÄÃ£ chá»n: ' + pending.file.name) : (asset.configured ? 'Äang dÃ¹ng áº£nh Ä‘Ã£ lÆ°u' : 'Äang dÃ¹ng máº·c Ä‘á»‹nh')) + '</div><input class="branding-file" id="asset_' + type + '" type="file" data-branding-file="' + type + '" accept="' + brandingAccept(type) + '"><div class="branding-actions"><button class="cc-btn" type="button" data-cc-action="configuration.chooseAsset" data-asset-type="' + type + '"><i class="fa-solid fa-image"></i>' + (asset.configured ? 'Thay áº£nh' : 'Chá»n áº£nh') + '</button><button class="cc-btn danger" type="button" data-cc-action="configuration.resetAsset" data-asset-type="' + type + '"' + (!asset.configured && !pending ? ' disabled' : '') + '><i class="fa-solid fa-rotate-left"></i>KhÃ´i phá»¥c máº·c Ä‘á»‹nh</button></div></article>';
    }

    function brandingAccept(type) {
      return type === 'favicon' ? '.png,.ico,image/png,image/x-icon,image/vnd.microsoft.icon' : '.png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp';
    }

    async function apiForm(path, formData, options = {}) {
      const method = options.method || 'POST';
      const headers = authHeaders(method);
      const response = await fetch(path, { method, headers, cache: 'no-store', body: formData });
      const payload = await response.json();
      if (!payload.ok) throw new Error(payload.message || 'YÃªu cáº§u khÃ´ng thÃ nh cÃ´ng');
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
      if (!confirm('KhÃ´i phá»¥c asset nÃ y vá» máº·c Ä‘á»‹nh?')) return;
      try {
        configurationState.data = await api('/api/platform/settings/assets/reset', { method: 'POST', body: { asset_type: type } });
        setConfigurationAlert('ÄÃ£ khÃ´i phá»¥c máº·c Ä‘á»‹nh.');
        renderConfiguration();
      } catch (error) {
        setConfigurationAlert(error.message || 'KhÃ´ng khÃ´i phá»¥c Ä‘Æ°á»£c asset');
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
      if (!configurationState.data) return '<div class="cc-state">Äang táº£i cáº¥u hÃ¬nh...</div>';
      if (id === 'general') {
        const body = '<div class="settings-grid">' +
          configInput('general.platform_name', 'TÃªn ná»n táº£ng') +
          configInput('general.admin_name', 'TÃªn trang quáº£n trá»‹') +
          configInput('general.parent_unit_name', 'TÃªn Ä‘Æ¡n vá»‹ cáº¥p trÃªn') +
          configInput('general.province_name', 'Tá»‰nh/ThÃ nh phá»‘') +
          configSelect('general.timezone', 'MÃºi giá»', [['Asia/Ho_Chi_Minh', 'Asia/Ho_Chi_Minh'], ['UTC', 'UTC']]) +
          configSelect('general.locale', 'NgÃ´n ngá»¯ máº·c Ä‘á»‹nh', [['vi_VN', 'Tiáº¿ng Viá»‡t']]) +
          configSelect('general.date_format', 'Äá»‹nh dáº¡ng ngÃ y', [['dd/mm/yyyy', 'dd/mm/yyyy'], ['yyyy-mm-dd', 'yyyy-mm-dd']]) +
          configSelect('general.datetime_format', 'Äá»‹nh dáº¡ng ngÃ y giá»', [['dd/mm/yyyy HH:mm', 'dd/mm/yyyy HH:mm'], ['yyyy-mm-dd HH:mm', 'yyyy-mm-dd HH:mm']]) +
          configInput('general.copyright', 'Copyright toan he thong') +
          '</div>';
        return settingsCard('Cáº¥u hÃ¬nh chung', 'ThÃ´ng tin hiá»ƒn thá»‹ vÃ  thiáº¿t láº­p máº·c Ä‘á»‹nh cá»§a ná»n táº£ng.', body, '<button class="cc-btn primary" type="button" data-cc-action="configuration.save" data-group="general"><i class="fa-solid fa-floppy-disk"></i>LÆ°u thay Ä‘á»•i</button>');
      }
      if (id === 'identity') {
        const body = '<div class="settings-grid">' +
          configInput('identity.system_name', 'TÃªn há»‡ thá»‘ng') + configInput('identity.short_name', 'TÃªn rÃºt gá»n') +
          '</div><div class="branding-upload-grid" style="margin-top:14px">' +
          brandingAssetCard('control_center_logo', 'Logo Community Control Center', 'PNG/JPG/JPEG/WEBP, tá»‘i Ä‘a 2MB') +
          brandingAssetCard('favicon', 'Favicon', 'PNG/ICO, tá»‘i Ä‘a 1MB') +
          brandingAssetCard('default_tenant_logo', 'Logo máº·c Ä‘á»‹nh tenant', 'Tenant chÆ°a cÃ³ logo riÃªng sáº½ káº¿ thá»«a áº£nh nÃ y') +
          brandingAssetCard('default_login_background', 'HÃ¬nh ná»n Ä‘Äƒng nháº­p máº·c Ä‘á»‹nh', 'Tenant chÆ°a cÃ³ hÃ¬nh ná»n riÃªng sáº½ káº¿ thá»«a áº£nh nÃ y', true) +
          '</div>';
        return settingsCard('Nháº­n diá»‡n há»‡ thá»‘ng', 'Quáº£n lÃ½ tÃªn vÃ  tÃ i sáº£n nháº­n diá»‡n cáº¥p ná»n táº£ng.', body, '<button class="cc-btn primary" type="button" data-cc-action="configuration.save" data-group="identity"><i class="fa-solid fa-floppy-disk"></i>LÆ°u thay Ä‘á»•i</button>');
      }
      if (id === 'tenant') {
        const mt = configurationState.data.multiTenant || {};
        const protectedRows = (mt.protectedControls || []).map((item) => '<div class="settings-toggle-row"><span>' + item.label + '<div class="settings-readonly">ÄÆ°á»£c há»‡ thá»‘ng báº£o vá»‡ - khÃ´ng thá»ƒ táº¯t</div></span>' + badge(item.enabled ? 'OK' : 'ERROR').outerHTML + '</div>').join('');
        const body = '<div class="settings-grid"><div class="settings-field"><label>Cháº¿ Ä‘á»™ multi-tenant</label><div class="cc-input">Báº­t</div></div><div class="settings-field"><label>Kiá»ƒu nháº­n diá»‡n tenant</label><div class="cc-input">Theo hostname/domain</div></div><div class="settings-field"><label>Domain ná»n táº£ng</label><div class="cc-input">' + (mt.rootDomain || '') + '</div></div><div class="settings-field"><label>Quy táº¯c subdomain</label><div class="cc-input">' + (mt.subdomainRule || '') + '</div></div>' + configSelect('tenant.default_status', 'Tráº¡ng thÃ¡i tenant má»›i', [['ACTIVE', 'Hoáº¡t Ä‘á»™ng'], ['PENDING_ACTIVATION', 'Chá» kÃ­ch hoáº¡t']]) + '</div>' +
          '<div style="margin-top:14px">' + protectedRows + '</div><h3 class="settings-card-title" style="margin-top:16px">Máº·c Ä‘á»‹nh khi táº¡o tenant má»›i</h3>' + configCheckbox('tenant.create_database', 'Táº¡o database') + configCheckbox('tenant.run_migrations', 'Cháº¡y migration/schema') + configCheckbox('tenant.create_admin_account', 'Táº¡o tÃ i khoáº£n quáº£n trá»‹ tenant') + configCheckbox('tenant.apply_platform_settings', 'Ãp dá»¥ng cáº¥u hÃ¬nh chung') + configCheckbox('tenant.create_uploads_structure', 'Táº¡o cáº¥u trÃºc uploads') + configCheckbox('tenant.audit_log_enabled', 'Cáº¥u hÃ¬nh audit log') + '<h3 class="settings-card-title" style="margin-top:16px">Tráº¡ng thÃ¡i runtime</h3>' + renderStatusRows(mt.components || []);
        return settingsCard('Multi-tenant', 'Cáº¥u hÃ¬nh dÃ¹ng chung cho má»i tenant hiá»‡n táº¡i vÃ  tenant táº¡o má»›i.', body, '<button class="cc-btn primary" type="button" data-cc-action="configuration.save" data-group="tenant"><i class="fa-solid fa-floppy-disk"></i>LÆ°u thay Ä‘á»•i</button>');
      }
      if (id === 'security') {
        const body = '<div class="settings-grid">' + configInput('security.idle_timeout_minutes', 'Tá»± Ä‘á»™ng Ä‘Äƒng xuáº¥t khi khÃ´ng hoáº¡t Ä‘á»™ng (phÃºt)', 'number') + configInput('security.session_ttl_hours', 'Thá»i gian sá»‘ng tá»‘i Ä‘a session (giá»)', 'number') + configInput('security.max_login_attempts', 'Sá»‘ láº§n Ä‘Äƒng nháº­p sai tá»‘i Ä‘a', 'number') + configInput('security.lockout_minutes', 'Thá»i gian khÃ³a táº¡m (phÃºt)', 'number') + '</div>' + renderStatusRows([{name:'Báº¯t buá»™c HTTPS',status:'OK'},{name:'Secure Cookie',status:'OK'},{name:'HttpOnly Cookie',status:'OK'},{name:'SameSite Cookie',status:'OK'},{name:'CSRF Protection',status:'OK'},{name:'Session regeneration sau Ä‘Äƒng nháº­p',status:'OK'}]);
        return settingsCard('Báº£o máº­t & phiÃªn Ä‘Äƒng nháº­p', 'CÃ¡c thiáº¿t láº­p báº£o máº­t nguy hiá»ƒm Ä‘Æ°á»£c backend báº£o vá»‡, khÃ´ng cho táº¯t trá»±c tiáº¿p tá»« UI.', body, '<button class="cc-btn primary" type="button" data-cc-action="configuration.save" data-group="security"><i class="fa-solid fa-floppy-disk"></i>LÆ°u thay Ä‘á»•i</button>');
      }
      if (id === 'data') {
        const data = configurationState.data.data || {};
        const health = data.centralRegistry || {};
        const body = renderStatusRows([{name:'Central Registry DB',status: health.ok ? 'OK' : 'ERROR', note: health.database || health.message || ''},{name:'Káº¿t ná»‘i cÃ¡c tenant DB',status:'NOT_APPLICABLE',note:data.tenantDatabasePolicy || ''},{name:'Backup engine',status:data.backupPolicy?.engineConfigured ? 'OK' : 'NOT_APPLICABLE',note:'Kiá»ƒm tra tráº¡ng thÃ¡i tháº­t; khÃ´ng táº¡o backup giáº£.'}]);
        return settingsCard('Dá»¯ liá»‡u & sao lÆ°u', 'Theo dÃµi registry vÃ  chÃ­nh sÃ¡ch backup ná»n táº£ng.', body, '<button class="cc-btn" type="button" data-cc-action="configuration.registry"><i class="fa-solid fa-plug-circle-check"></i>Kiá»ƒm tra káº¿t ná»‘i</button><button class="cc-btn" type="button" data-cc-action="configuration.backup"><i class="fa-solid fa-database"></i>Kiá»ƒm tra tráº¡ng thÃ¡i sao lÆ°u</button>');
      }
      if (id === 'files') {
        const allowed = Array.isArray(configValue('files.allowed_extensions', [])) ? configValue('files.allowed_extensions', []).join(', ') : configValue('files.allowed_extensions', '');
        const body = '<div class="settings-grid">' + configInput('files.max_file_mb', 'Dung lÆ°á»£ng file tá»‘i Ä‘a (MB)', 'number') + configInput('files.max_image_mb', 'Dung lÆ°á»£ng áº£nh tá»‘i Ä‘a (MB)', 'number') + '<div class="settings-field"><label>Loáº¡i file cho phÃ©p</label><input class="cc-input" data-config-key="files.allowed_extensions" value="' + allowed + '"></div></div><div class="settings-readonly" style="margin-top:12px">Backend cháº·n php, phtml, phar, cgi, exe, js vÃ  cÃ¡c Ä‘uÃ´i thá»±c thi khi lÆ°u cáº¥u hÃ¬nh.</div>';
        return settingsCard('Tá»‡p & táº£i lÃªn', 'ChÃ­nh sÃ¡ch file cáº¥p ná»n táº£ng Ä‘á»ƒ backend sá»­ dá»¥ng khi validate upload.', body, '<button class="cc-btn primary" type="button" data-cc-action="configuration.save" data-group="files"><i class="fa-solid fa-floppy-disk"></i>LÆ°u thay Ä‘á»•i</button>');
      }
      if (id === 'email') {
        const body = '<div class="settings-grid">' + configInput('email.system_email', 'Email há»‡ thá»‘ng') + configInput('email.sender_name', 'TÃªn ngÆ°á»i gá»­i') + configInput('email.smtp_host', 'SMTP Host') + configInput('email.smtp_port', 'SMTP Port', 'number') + configSelect('email.smtp_encryption', 'Encryption', [['tls','TLS'],['ssl','SSL'],['none','KhÃ´ng mÃ£ hÃ³a']]) + configInput('email.smtp_username', 'SMTP Username') + '<div class="settings-field"><label>SMTP Password</label><input class="cc-input" id="smtpSecretValue" type="password" placeholder="' + (configurationState.data.settings['email.smtp_password']?.masked || 'ChÆ°a cáº¥u hÃ¬nh') + '"></div></div><div class="settings-readonly" style="margin-top:12px">Secret khÃ´ng tráº£ plaintext vá» frontend vÃ  khÃ´ng ghi giÃ¡ trá»‹ vÃ o audit log.</div>';
        return settingsCard('Email / ThÃ´ng bÃ¡o', 'Cáº¥u hÃ¬nh email há»‡ thá»‘ng vÃ  cáº£nh bÃ¡o váº­n hÃ nh.', body, '<button class="cc-btn" type="button" data-cc-action="configuration.secret"><i class="fa-solid fa-key"></i>Cáº­p nháº­t secret</button><button class="cc-btn" type="button" data-cc-action="configuration.testEmail"' + (configurationState.data.capabilities?.smtpTest?.enabled ? '' : ' disabled') + '><i class="fa-solid fa-paper-plane"></i>Gá»­i email kiá»ƒm tra</button><button class="cc-btn primary" type="button" data-cc-action="configuration.save" data-group="email"><i class="fa-solid fa-floppy-disk"></i>LÆ°u thay Ä‘á»•i</button>');
      }
      const system = configurationState.data.system || {};
      const maintenance = configValue('maintenance.platform_enabled', false);
      const body = renderStatusRows([{name:'PhiÃªn báº£n á»©ng dá»¥ng',status:'OK',note:system.version || ''},{name:'Commit Ä‘ang cháº¡y',status:'OK',note:system.commit || 'KhÃ´ng xÃ¡c Ä‘á»‹nh'},{name:'MÃ´i trÆ°á»ng',status:'OK',note:system.environment || ''},{name:'PHP version',status:'OK',note:system.phpVersion || ''},{name:'Database version',status:system.databaseVersion ? 'OK':'UNKNOWN',note:system.databaseVersion || ''},{name:'Thá»i gian server',status:'OK',note:system.serverTime || ''},{name:'Tenant Guard',status:system.tenantGuard || 'ERROR'},{name:'Central Registry',status:system.centralRegistry || 'ERROR'}]) + '<label class="settings-toggle-row"><span>Cháº¿ Ä‘á»™ báº£o trÃ¬ toÃ n ná»n táº£ng<div class="settings-readonly">Community Control Center váº«n truy cáº­p Ä‘Æ°á»£c Ä‘á»ƒ táº¯t báº£o trÃ¬.</div></span><input type="checkbox" id="platformMaintenanceToggle" ' + (maintenance ? 'checked' : '') + '></label>';
      return settingsCard('Há»‡ thá»‘ng & báº£o trÃ¬', 'ThÃ´ng tin runtime chá»‰ Ä‘á»c vÃ  cÃ´ng táº¯c báº£o trÃ¬ tháº­t á»Ÿ TenantGuard.', body, '<button class="cc-btn primary" type="button" data-cc-action="configuration.maintenance"><i class="fa-solid fa-screwdriver-wrench"></i>Cáº­p nháº­t báº£o trÃ¬</button>');
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
        setConfigurationAlert(error.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c cáº¥u hÃ¬nh ná»n táº£ng');
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
        setConfigurationAlert('ÄÃ£ lÆ°u cáº¥u hÃ¬nh.');
        renderConfiguration();
      } catch (error) {
        setConfigurationAlert(error.message || 'KhÃ´ng lÆ°u Ä‘Æ°á»£c cáº¥u hÃ¬nh');
      }
    }

    async function updateSmtpSecret() {
      const field = document.getElementById('smtpSecretValue');
      const value = field ? field.value : '';
      if (!value) { setConfigurationAlert('Vui lÃ²ng nháº­p SMTP password má»›i.'); return; }
      try {
        configurationState.data = await api('/api/control-center/configuration/secret', { method: 'PUT', body: { key: 'email.smtp_password', value } });
        setConfigurationAlert('ÄÃ£ cáº­p nháº­t SMTP password.');
        renderConfiguration();
      } catch (error) {
        setConfigurationAlert(error.message || 'KhÃ´ng cáº­p nháº­t Ä‘Æ°á»£c secret');
      }
    }

    async function updateMaintenance() {
      const enabled = Boolean(document.getElementById('platformMaintenanceToggle')?.checked);
      try {
        configurationState.data = await api('/api/control-center/configuration/maintenance', { method: 'PATCH', body: { enabled } });
        setConfigurationAlert(enabled ? 'ÄÃ£ báº­t cháº¿ Ä‘á»™ báº£o trÃ¬ tenant.' : 'ÄÃ£ táº¯t cháº¿ Ä‘á»™ báº£o trÃ¬ tenant.');
        renderConfiguration();
      } catch (error) {
        setConfigurationAlert(error.message || 'KhÃ´ng cáº­p nháº­t Ä‘Æ°á»£c báº£o trÃ¬');
      }
    }

    async function runConfigurationCheck(kind) {
      const endpoint = kind === 'backup' ? '/api/control-center/configuration/check-backup' : '/api/control-center/configuration/check-registry';
      try {
        const result = await api(endpoint, { method: 'POST' });
        setConfigurationAlert((result.message || result.status || 'OK'));
      } catch (error) {
        setConfigurationAlert(error.message || 'KhÃ´ng kiá»ƒm tra Ä‘Æ°á»£c tráº¡ng thÃ¡i');
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
      await loadPermissions().catch((error) => setPermissionsAlert(error.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c phÃ¢n quyá»n'));
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
        document.getElementById('tenantActivityBody').replaceChildren(stateRow(5, error.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c Activity'));
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
      registerControlCenterAction('configuration.testEmail', async () => { try { await api('/api/control-center/configuration/test-email', { method: 'POST' }); setConfigurationAlert('ÄÃ£ gá»­i email kiá»ƒm tra.'); } catch (error) { setConfigurationAlert(error.message || 'SMTP chÆ°a sáºµn sÃ ng'); } });
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
        ['executive', ['báº£ng Ä‘iá»u hÃ nh', 'bang dieu hanh', 'executive']],
        ['dashboard', ['dashboard', 'tá»•ng quan', 'thá»‘ng kÃª', 'tong quan', 'thong ke']],
        ['units', ['Ä‘Æ¡n vá»‹', 'thÃ´n', 'xÃ£', 'don vi', 'thon', 'xa', 'administrative']],
        ['tenants', ['tenant', 'domain', 'database', 'trang web']],
        ['accounts', ['tÃ i khoáº£n', 'ngÆ°á»i dÃ¹ng', 'tai khoan', 'nguoi dung', 'user']],
        ['permissions', ['phÃ¢n quyá»n', 'quyá»n', 'phan quyen', 'permission', 'quyen']],
        ['monitoring', ['giÃ¡m sÃ¡t', 'monitoring', 'health', 'tráº¡ng thÃ¡i', 'trang thai']],
        ['audit', ['nháº­t kÃ½', 'kiá»ƒm toÃ¡n', 'audit', 'lá»‹ch sá»­', 'truy váº¿t', 'lich su', 'truy vet']],
        ['configuration', ['cáº¥u hÃ¬nh', 'cau hinh', 'config', 'settings']],
        ['notifications', ['thÃ´ng bÃ¡o', 'thong bao', 'notification']],
        ['ai', ['ai', 'trá»£ lÃ½', 'tro ly']]
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
