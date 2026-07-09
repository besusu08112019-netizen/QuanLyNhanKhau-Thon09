<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quản Lý Nhân Khẩu Thôn 09</title>
  <link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.min.css">
</head>
<body>
  <div id="toastHost" class="toast-container position-fixed top-0 end-0 p-3"></div>

  <main id="loginView" class="login-view">
    <section class="login-landing" aria-label="Đăng nhập hệ thống quản lý hành chính Thôn 09">
      <section class="login-panel" aria-labelledby="loginSystemName">
        <div class="login-brand" aria-label="Thôn 09 xã Hồng Phong">
          <div id="loginLogo" class="login-logo login-logo-emblem" aria-hidden="true">
            <span class="logo-flag"><i class="fa-solid fa-star"></i></span>
            <span class="logo-landmark"><i class="fa-solid fa-landmark"></i></span>
            <span class="logo-village"><i class="fa-solid fa-house-chimney"></i></span>
            <span class="logo-name">Thôn</span>
            <strong>09</strong>
          </div>
          <div class="login-title-block">
            <p id="loginSystemName">Hệ thống Quản lý Hành chính</p>
            <h1><span id="loginHamletName">Thôn 09</span> - <span id="loginCommuneName">Xã Hồng Phong</span></h1>
            <div id="loginSlogan" class="login-slogan">Vì Nhân dân phục vụ</div>
          </div>
        </div>
        <form id="loginForm" class="login-form" novalidate>
          <div class="login-field">
            <label class="form-label" for="loginEmail">Tên đăng nhập</label>
            <div class="login-input-wrap"><i class="fa-solid fa-user" aria-hidden="true"></i><input id="loginEmail" name="email" type="text" class="form-control" placeholder="Nhập tên đăng nhập" autocomplete="username" inputmode="email" required></div>
          </div>
          <div class="login-field">
            <label class="form-label" for="loginPassword">Mật khẩu</label>
            <div class="login-input-wrap"><i class="fa-solid fa-lock" aria-hidden="true"></i><input id="loginPassword" name="password" type="password" class="form-control" placeholder="Nhập mật khẩu" autocomplete="current-password" required minlength="8"><button class="password-toggle" type="button" aria-label="Hiện mật khẩu" data-password-toggle><i class="fa-solid fa-eye" aria-hidden="true"></i></button></div>
          </div>
          <button class="btn login-submit" type="submit"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Đăng nhập</span></button>
        </form>
        <footer class="login-footer"><span id="loginVersion">Phiên bản v2.0</span><span id="loginCopyright">© Thôn 09 - Xã Hồng Phong</span></footer>
      </section>
    </section>
  </main>

  <div id="appView" class="app-shell d-none">
    
    <aside class="sidebar gov-sidebar">
      <button id="sidebarCollapse" class="sidebar-collapse-btn" type="button" title="Thu gọn Sidebar" aria-label="Thu gọn Sidebar"><i class="fa-solid fa-angle-left"></i></button>
      <div class="sidebar-brand gov-brand">
        <span class="state-mark small-mark">09</span>
        <div>
          <strong>Hệ thống quản lý hành chính</strong>
          <b>Thôn 09</b>
          <small>Xã Hồng Phong</small>
        </div>
      </div>
      <nav class="nav flex-column gov-nav" aria-label="Điều hướng chính">
        <div class="nav-section"><div class="nav-section-title">Dashboard</div><button class="nav-link active" data-screen="dashboard" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-gauge-high"></i><span>Dashboard Tổng quan</span></button><button class="nav-link" data-screen="dashboardHouseholds" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-house-chimney"></i><span>Dashboard Hộ dân</span></button><button class="nav-link" data-screen="dashboardPopulation" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-users"></i><span>Dashboard Nhân khẩu</span></button><button class="nav-link" data-screen="dashboardBusiness" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-store"></i><span>Dashboard Kinh doanh</span></button><button class="nav-link" data-screen="dashboardVehicles" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-car"></i><span>Dashboard Xe cộ</span></button><button class="nav-link" data-screen="dashboardLivestock" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-paw"></i><span>Dashboard Chăn nuôi</span></button><button class="nav-link" data-screen="dashboardGis" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-map-location-dot"></i><span>Dashboard GIS</span></button><button class="nav-link" data-screen="dashboardReports" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-file-lines"></i><span>Dashboard Báo cáo</span></button></div><div class="nav-section"><div class="nav-section-title">Tổng quan</div><button class="nav-link" data-screen="operationCenter" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-tower-broadcast"></i><span>Trung tâm điều hành</span></button><button class="nav-link" data-screen="gis" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-map-location-dot"></i><span>Bản đồ địa bàn</span></button></div><div class="nav-section"><div class="nav-section-title">Quản lý dân cư</div><button class="nav-link" data-screen="households" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-house-chimney"></i><span>Quản lý hộ gia đình</span></button><button class="nav-link" data-screen="businessHouseholds" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-store"></i><span>Hộ sản xuất &amp; kinh doanh</span></button><button class="nav-link" data-screen="agriculture" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-seedling"></i><span>S&#7843;n xu&#7845;t n&#244;ng nghi&#7879;p</span></button><button class="nav-link" data-screen="houses" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-building-user"></i><span>Nh&#224; &#7903; &amp; C&#244;ng tr&#236;nh</span></button><button class="nav-link" data-screen="livestock" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-paw"></i><span>Quản lý vật nuôi</span></button><button class="nav-link" data-screen="persons" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-users"></i><span>Quản lý nhân khẩu</span></button><button class="nav-link" data-screen="temporaryResidence" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-location-dot"></i><span>Tạm trú</span></button><button class="nav-link" data-screen="temporaryAbsence" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-person-walking-arrow-right"></i><span>Tạm vắng</span></button><button class="nav-link" data-screen="movements" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-right-left"></i><span>Biến động nhân khẩu</span></button></div>
        <div class="nav-section"><div class="nav-section-title">Báo cáo</div><button class="nav-link" data-screen="reports" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-chart-pie"></i><span>Báo cáo thống kê</span></button></div>
        <div class="nav-section"><div class="nav-section-title">Dữ liệu</div><button class="nav-link" data-screen="import" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-file-import"></i><span>Import dữ liệu</span></button><button class="nav-link" data-screen="exportExcel" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-file-export"></i><span>Xuất Excel</span></button><button class="nav-link" data-screen="printForms" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-print"></i><span>In biểu mẫu</span></button></div>
        <div class="nav-section"><div class="nav-section-title">Hệ thống</div><button class="nav-link" data-screen="users" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-user-shield"></i><span>Quản lý tài khoản</span></button><button class="nav-link" data-screen="permissions" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-key"></i><span>Phân quyền</span></button><button class="nav-link" data-screen="logs" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-clock-rotate-left"></i><span>Nhật ký</span></button><button class="nav-link" data-screen="backups" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-database"></i><span>Sao lưu</span></button><button class="nav-link" data-screen="restore" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-rotate-left"></i><span>Khôi phục</span></button></div>
        <div class="nav-section"><div class="nav-section-title">Cấu hình</div><button class="nav-link" data-screen="settings" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-gear"></i><span>Cấu hình hệ thống</span></button><button class="nav-link" data-screen="appearance" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-palette"></i><span>Cấu hình giao diện</span></button></div>
      </nav>
      <button id="sidebarLogoutBtn" class="nav-link gov-logout" type="button"><i class="fa-solid fa-right-from-bracket"></i><span>Đăng xuất</span></button>
    </aside>

    <section class="main-area">
      <header class="topbar gov-topbar">
        <button id="sidebarToggle" class="btn btn-outline-secondary btn-sm d-lg-none" type="button"><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title-block gov-breadcrumb-block">
          <small id="breadcrumbTrail" class="breadcrumb-trail">Trang chủ / Dashboard</small>
        </div>
        <div class="topbar-meta ms-auto">
          <span id="topbarClock" class="topbar-clock"><i class="fa-regular fa-calendar"></i> --/--/----</span>
          <button id="notificationBtn" class="gov-notification-btn" type="button" aria-label="Thông báo"><i class="fa-solid fa-bell"></i><span>3</span></button>
          <span id="currentUser" class="topbar-user"></span>
          <button id="logoutBtn" class="btn btn-link btn-sm text-decoration-none px-0">Đăng xuất</button>
        </div>
      </header>
      <div id="loadingBar" class="progress rounded-0 d-none" style="height:3px"><div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div></div>

      <section id="dashboardScreen" class="screen active dashboard-overview-screen">
        <section class="dashboard-status-row"><div id="dashboardGeneratedAt" class="dashboard-sync-note">Đang cập nhật dữ liệu</div></section>
        <section id="dashboardKpis" class="dashboard-kpi-grid" aria-label="Chỉ số tổng quan"></section>
        <section class="dashboard-chart-grid" aria-label="Biểu đồ tổng quan">
          <article class="dashboard-panel dashboard-panel-large">
            <div class="dashboard-panel-head">
              <h3>Cơ cấu nhân khẩu theo độ tuổi</h3>
            </div>
            <div id="ageStructureChart" class="dashboard-chart-body"></div>
          </article>
          <article class="dashboard-panel dashboard-panel-large">
            <div class="dashboard-panel-head">
              <h3>Biến động nhân khẩu 6 tháng gần nhất</h3>
              <span class="dashboard-filter-pill">6 tháng</span>
            </div>
            <div id="populationMovementChart" class="dashboard-chart-body"></div>
          </article>
          <article class="dashboard-panel">
            <div class="dashboard-panel-head">
              <h3>Cơ cấu nhân khẩu theo giới tính</h3>
            </div>
            <div id="genderStructureChart" class="dashboard-chart-body"></div>
          </article>
          <article class="dashboard-panel">
            <div class="dashboard-panel-head">
              <h3>Đảng viên trên tổng nhân khẩu</h3>
            </div>
            <div id="partyMemberChart" class="dashboard-chart-body"></div>
          </article>
          <article class="dashboard-panel">
            <div class="dashboard-panel-head">
              <h3>Cơ cấu diện hộ</h3>
            </div>
            <div id="householdTypeChart" class="dashboard-chart-body"></div>
          </article>
          <article class="dashboard-panel">
            <div class="dashboard-panel-head">
              <h3>Tình trạng lao động</h3>
            </div>
            <div id="laborStatusChart" class="dashboard-chart-body"></div>
          </article>
        </section>
      </section>

      <section id="dashboardHouseholdsScreen" class="screen dashboard-overview-screen module-dashboard-screen" data-module-dashboard="dashboardHouseholds">
        <section class="dashboard-status-row"><div id="dashboardHouseholdsGeneratedAt" class="dashboard-sync-note">Đang cập nhật dữ liệu</div></section>
        <section class="content-card dashboard-filter mb-3" aria-label="Bộ lọc Dashboard"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">Từ ngày</label><input class="form-control form-control-sm" type="date" data-dashboard-filter="date_from"></div><div class="col-md-3"><label class="form-label">Đến ngày</label><input class="form-control form-control-sm" type="date" data-dashboard-filter="date_to"></div><div class="col-md-3"><label class="form-label">Khu vực</label><input class="form-control form-control-sm" data-dashboard-filter="area_code" placeholder="Mã khu vực"></div><div class="col-md-3 d-flex gap-2"><button class="btn btn-primary btn-sm" type="button" data-dashboard-refresh><i class="fa-solid fa-rotate-right"></i> Cập nhật</button><button class="btn btn-outline-secondary btn-sm" type="button" data-dashboard-clear>Làm mới</button></div></div></section>
        <section id="dashboardHouseholdsKpis" class="dashboard-kpi-grid" aria-label="Chỉ số Dashboard"></section>
        <section class="dashboard-chart-grid" aria-label="Biểu đồ Dashboard"><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Cơ cấu hộ</h3></div><div id="dashboardHouseholdsChartOne" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Tiến độ GPS</h3></div><div id="dashboardHouseholdsChartTwo" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Hồ sơ số</h3></div><div id="dashboardHouseholdsChartThree" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Tác vụ nổi bật</h3></div><div id="dashboardHouseholdsExtra" class="dashboard-chart-body"></div></article></section>
      </section>
      <section id="dashboardPopulationScreen" class="screen dashboard-overview-screen module-dashboard-screen" data-module-dashboard="dashboardPopulation">
        <section class="dashboard-status-row"><div id="dashboardPopulationGeneratedAt" class="dashboard-sync-note">Đang cập nhật dữ liệu</div></section>
        <section class="content-card dashboard-filter mb-3" aria-label="Bộ lọc Dashboard"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">Từ ngày</label><input class="form-control form-control-sm" type="date" data-dashboard-filter="date_from"></div><div class="col-md-3"><label class="form-label">Đến ngày</label><input class="form-control form-control-sm" type="date" data-dashboard-filter="date_to"></div><div class="col-md-3"><label class="form-label">Khu vực</label><input class="form-control form-control-sm" data-dashboard-filter="area_code" placeholder="Mã khu vực"></div><div class="col-md-3 d-flex gap-2"><button class="btn btn-primary btn-sm" type="button" data-dashboard-refresh><i class="fa-solid fa-rotate-right"></i> Cập nhật</button><button class="btn btn-outline-secondary btn-sm" type="button" data-dashboard-clear>Làm mới</button></div></div></section>
        <section id="dashboardPopulationKpis" class="dashboard-kpi-grid" aria-label="Chỉ số Dashboard"></section>
        <section class="dashboard-chart-grid" aria-label="Biểu đồ Dashboard"><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Cơ cấu giới tính</h3></div><div id="dashboardPopulationChartOne" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Cơ cấu độ tuổi</h3></div><div id="dashboardPopulationChartTwo" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Tình trạng lao động</h3></div><div id="dashboardPopulationChartThree" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>BHYT</h3></div><div id="dashboardPopulationExtra" class="dashboard-chart-body"></div></article></section>
      </section>
      <section id="dashboardBusinessScreen" class="screen dashboard-overview-screen module-dashboard-screen" data-module-dashboard="dashboardBusiness">
        <section class="dashboard-status-row"><div id="dashboardBusinessGeneratedAt" class="dashboard-sync-note">Đang cập nhật dữ liệu</div></section>
        <section class="content-card dashboard-filter mb-3" aria-label="Bộ lọc Dashboard"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">Từ ngày</label><input class="form-control form-control-sm" type="date" data-dashboard-filter="date_from"></div><div class="col-md-3"><label class="form-label">Đến ngày</label><input class="form-control form-control-sm" type="date" data-dashboard-filter="date_to"></div><div class="col-md-3"><label class="form-label">Khu vực</label><input class="form-control form-control-sm" data-dashboard-filter="area_code" placeholder="Mã khu vực"></div><div class="col-md-3 d-flex gap-2"><button class="btn btn-primary btn-sm" type="button" data-dashboard-refresh><i class="fa-solid fa-rotate-right"></i> Cập nhật</button><button class="btn btn-outline-secondary btn-sm" type="button" data-dashboard-clear>Làm mới</button></div></div></section>
        <section id="dashboardBusinessKpis" class="dashboard-kpi-grid" aria-label="Chỉ số Dashboard"></section>
        <section class="dashboard-chart-grid" aria-label="Biểu đồ Dashboard"><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Theo loại hình</h3></div><div id="dashboardBusinessChartOne" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Số lượng cơ sở theo ngành</h3></div><div id="dashboardBusinessChartTwo" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Quy mô hoạt động</h3></div><div id="dashboardBusinessChartThree" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Top 10 hộ nhiều ngành nghề</h3></div><div id="dashboardBusinessExtra" class="dashboard-chart-body"></div></article></section>
      </section>
      <section id="dashboardVehiclesScreen" class="screen dashboard-overview-screen module-dashboard-screen" data-module-dashboard="dashboardVehicles">
        <section class="dashboard-status-row"><div id="dashboardVehiclesGeneratedAt" class="dashboard-sync-note">Đang cập nhật dữ liệu</div></section>
        <section class="content-card dashboard-filter mb-3" aria-label="Bộ lọc Dashboard"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">Từ ngày</label><input class="form-control form-control-sm" type="date" data-dashboard-filter="date_from"></div><div class="col-md-3"><label class="form-label">Đến ngày</label><input class="form-control form-control-sm" type="date" data-dashboard-filter="date_to"></div><div class="col-md-3"><label class="form-label">Khu vực</label><input class="form-control form-control-sm" data-dashboard-filter="area_code" placeholder="Mã khu vực"></div><div class="col-md-3 d-flex gap-2"><button class="btn btn-primary btn-sm" type="button" data-dashboard-refresh><i class="fa-solid fa-rotate-right"></i> Cập nhật</button><button class="btn btn-outline-secondary btn-sm" type="button" data-dashboard-clear>Làm mới</button></div></div></section>
        <section id="dashboardVehiclesKpis" class="dashboard-kpi-grid" aria-label="Chỉ số Dashboard"></section>
        <section class="dashboard-chart-grid" aria-label="Biểu đồ Dashboard"><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Theo loại xe</h3></div><div id="dashboardVehiclesChartOne" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Theo hộ</h3></div><div id="dashboardVehiclesChartTwo" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Theo khu vực</h3></div><div id="dashboardVehiclesChartThree" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Bản đồ</h3></div><div id="dashboardVehiclesExtra" class="dashboard-chart-body"></div></article></section>
      </section>
      <section id="dashboardLivestockScreen" class="screen dashboard-overview-screen module-dashboard-screen" data-module-dashboard="dashboardLivestock">
        <section class="dashboard-status-row"><div id="dashboardLivestockGeneratedAt" class="dashboard-sync-note">Đang cập nhật dữ liệu</div></section>
        <section class="content-card dashboard-filter mb-3" aria-label="Bộ lọc Dashboard"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">Từ ngày</label><input class="form-control form-control-sm" type="date" data-dashboard-filter="date_from"></div><div class="col-md-3"><label class="form-label">Đến ngày</label><input class="form-control form-control-sm" type="date" data-dashboard-filter="date_to"></div><div class="col-md-3"><label class="form-label">Khu vực</label><input class="form-control form-control-sm" data-dashboard-filter="area_code" placeholder="Mã khu vực"></div><div class="col-md-3 d-flex gap-2"><button class="btn btn-primary btn-sm" type="button" data-dashboard-refresh><i class="fa-solid fa-rotate-right"></i> Cập nhật</button><button class="btn btn-outline-secondary btn-sm" type="button" data-dashboard-clear>Làm mới</button></div></div></section>
        <section id="dashboardLivestockKpis" class="dashboard-kpi-grid" aria-label="Chỉ số Dashboard"></section>
        <section class="dashboard-chart-grid" aria-label="Biểu đồ Dashboard"><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Cơ cấu vật nuôi</h3></div><div id="dashboardLivestockChartOne" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Quy mô đàn</h3></div><div id="dashboardLivestockChartTwo" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Theo khu vực</h3></div><div id="dashboardLivestockChartThree" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Top 10 hộ nuôi nhiều nhất</h3></div><div id="dashboardLivestockExtra" class="dashboard-chart-body"></div></article></section>
      </section>
      <section id="dashboardGisScreen" class="screen dashboard-overview-screen module-dashboard-screen" data-module-dashboard="dashboardGis">
        <section class="dashboard-status-row"><div id="dashboardGisGeneratedAt" class="dashboard-sync-note">Đang cập nhật dữ liệu</div></section>
        <section class="content-card dashboard-filter mb-3" aria-label="Bộ lọc Dashboard"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">Từ ngày</label><input class="form-control form-control-sm" type="date" data-dashboard-filter="date_from"></div><div class="col-md-3"><label class="form-label">Đến ngày</label><input class="form-control form-control-sm" type="date" data-dashboard-filter="date_to"></div><div class="col-md-3"><label class="form-label">Khu vực</label><input class="form-control form-control-sm" data-dashboard-filter="area_code" placeholder="Mã khu vực"></div><div class="col-md-3 d-flex gap-2"><button class="btn btn-primary btn-sm" type="button" data-dashboard-refresh><i class="fa-solid fa-rotate-right"></i> Cập nhật</button><button class="btn btn-outline-secondary btn-sm" type="button" data-dashboard-clear>Làm mới</button></div></div></section>
        <section id="dashboardGisKpis" class="dashboard-kpi-grid" aria-label="Chỉ số Dashboard"></section>
        <section class="dashboard-chart-grid" aria-label="Biểu đồ Dashboard"><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Tiến độ định vị</h3></div><div id="dashboardGisChartOne" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Hộ kinh doanh trên GIS</h3></div><div id="dashboardGisChartTwo" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Layer GIS</h3></div><div id="dashboardGisChartThree" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Lớp bản đồ</h3></div><div id="dashboardGisExtra" class="dashboard-chart-body"></div></article></section>
      </section>
      <section id="dashboardReportsScreen" class="screen dashboard-overview-screen module-dashboard-screen" data-module-dashboard="dashboardReports">
        <section class="dashboard-status-row"><div id="dashboardReportsGeneratedAt" class="dashboard-sync-note">Đang cập nhật dữ liệu</div></section>
        <section class="content-card dashboard-filter mb-3" aria-label="Bộ lọc Dashboard"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">Từ ngày</label><input class="form-control form-control-sm" type="date" data-dashboard-filter="date_from"></div><div class="col-md-3"><label class="form-label">Đến ngày</label><input class="form-control form-control-sm" type="date" data-dashboard-filter="date_to"></div><div class="col-md-3"><label class="form-label">Khu vực</label><input class="form-control form-control-sm" data-dashboard-filter="area_code" placeholder="Mã khu vực"></div><div class="col-md-3 d-flex gap-2"><button class="btn btn-primary btn-sm" type="button" data-dashboard-refresh><i class="fa-solid fa-rotate-right"></i> Cập nhật</button><button class="btn btn-outline-secondary btn-sm" type="button" data-dashboard-clear>Làm mới</button></div></div></section>
        <section id="dashboardReportsKpis" class="dashboard-kpi-grid" aria-label="Chỉ số Dashboard"></section>
        <section class="dashboard-chart-grid" aria-label="Biểu đồ Dashboard"><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Nhóm báo cáo</h3></div><div id="dashboardReportsChartOne" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Định dạng xuất</h3></div><div id="dashboardReportsChartTwo" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Trạng thái dữ liệu</h3></div><div id="dashboardReportsChartThree" class="dashboard-chart-body"></div></article><article class="dashboard-panel"><div class="dashboard-panel-head"><h3>Danh sách báo cáo</h3></div><div id="dashboardReportsExtra" class="dashboard-chart-body"></div></article></section>
      </section>
      <section id="operationCenterScreen" class="screen operation-center-screen">
        <div class="operation-shell" data-operation-center>
          <section class="operation-toolbar content-card">
            <div class="operation-toolbar-main">
              <h3>Trung tâm điều hành số</h3>
              <div class="operation-search-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="operationSearchInput" class="form-control" autocomplete="off" placeholder="Tìm họ tên, CCCD, mã hộ, chủ hộ, địa chỉ, điện thoại, GPS, hồ sơ số"><div id="operationSearchResults" class="operation-search-results d-none"></div></div>
            </div>
            <div class="operation-toolbar-actions">
              <select id="operationReportRange" class="form-select form-select-sm"><option value="today">Hôm nay</option><option value="week">Tuần</option><option value="month">Tháng</option><option value="quarter">Quý</option><option value="year">Năm</option></select>
              <button class="btn btn-outline-primary btn-sm" type="button" data-operation-export="pdf"><i class="fa-solid fa-file-pdf"></i> PDF</button>
              <button class="btn btn-outline-success btn-sm" type="button" data-operation-export="excel"><i class="fa-solid fa-file-excel"></i> Excel</button>
              <button class="btn btn-outline-secondary btn-sm" type="button" data-operation-export="word"><i class="fa-solid fa-file-word"></i> Word</button>
            </div>
          </section>
          <section class="operation-grid">
            <article class="content-card operation-panel"><div class="operation-panel-head"><h4>Notification Center</h4><button class="btn btn-sm btn-outline-secondary" type="button" data-operation-refresh="notifications"><i class="fa-solid fa-rotate-right"></i></button></div><div id="operationNotifications"></div></article>
            <article class="content-card operation-panel"><div class="operation-panel-head"><h4>Cần xử lý</h4><div class="operation-filter-inline"><select id="operationTaskPriority" class="form-select form-select-sm"><option value="">Tất cả</option><option value="high">Cao</option><option value="medium">Vừa</option><option value="low">Theo dõi</option></select><input id="operationTaskDate" class="form-control form-control-sm" type="date"></div></div><div id="operationTasks"></div></article>
            <article class="content-card operation-panel"><div class="operation-panel-head"><h4>Timeline toàn hệ thống</h4><div class="operation-filter-inline"><input id="operationTimelineSearch" class="form-control form-control-sm" placeholder="Tìm nhật ký"><select id="operationTimelineModule" class="form-select form-select-sm"><option value="">Tất cả module</option><option value="household">Hộ</option><option value="citizen">Công dân</option><option value="movements">Biến động</option><option value="gis">GIS</option><option value="file">Hồ sơ số</option></select></div></div><div id="operationTimeline"></div></article>
            <article class="content-card operation-panel"><div class="operation-panel-head"><h4>Dashboard theo khu vực</h4><select id="operationAreaSelect" class="form-select form-select-sm"><option value="">Tất cả khu vực</option></select></div><div id="operationAreaDashboard"></div></article>
            <article class="content-card operation-panel"><div class="operation-panel-head"><h4>Widget tiến độ</h4><button class="btn btn-sm btn-outline-secondary" type="button" data-operation-refresh="progress"><i class="fa-solid fa-rotate-right"></i></button></div><div id="operationProgress"></div></article>
            <article class="content-card operation-panel"><div class="operation-panel-head"><h4>Nhật ký hệ thống</h4><button class="btn btn-sm btn-outline-success" type="button" data-operation-export-logs><i class="fa-solid fa-file-excel"></i> Xuất Excel</button></div><div class="operation-log-filters"><input id="operationLogSearch" class="form-control form-control-sm" placeholder="Tìm nhật ký"><input id="operationLogDateFrom" class="form-control form-control-sm" type="date"><input id="operationLogDateTo" class="form-control form-control-sm" type="date"></div><div id="operationLogs"></div></article>
          </section>
        </div>
      </section>


      <section id="gisScreen" class="screen gis-screen">
        <div class="gis-layout">
          <aside class="gis-panel content-card">
            <div class="gis-panel-head">
              <div>
                <h3>Bản đồ địa bàn</h3>
                <p>Vẽ ranh giới xóm/tổ/khu dân cư, xem thống kê và lọc hộ theo khu vực.</p>
              </div>
              <button id="gisRefreshBtn" class="btn btn-outline-secondary btn-sm" type="button"><i class="fa-solid fa-rotate-right"></i></button>
            </div>
            <div class="gis-actions">
              <button id="gisDrawBtn" class="btn btn-success" type="button"><i class="fa-solid fa-draw-polygon"></i> Vẽ ranh giới</button>
              <button id="gisSaveBtn" class="btn btn-primary" type="button" disabled><i class="fa-solid fa-floppy-disk"></i> Lưu khu vực</button>
              <button id="gisPdfBtn" class="btn btn-outline-danger" type="button"><i class="fa-solid fa-file-pdf"></i> Xuất PDF</button>
            </div>
            <form id="gisAreaForm" class="gis-area-form">
              <input type="hidden" id="gisAreaId">
              <label>Tên khu vực</label>
              <input id="gisAreaName" class="form-control" placeholder="Ví dụ: Xóm 1, Tổ 2...">
              <label>Mã khu vực / Area code</label>
              <input id="gisAreaCode" class="form-control" placeholder="Khớp trường area_code của hộ dân">
              <label>Màu hiển thị</label>
              <input id="gisAreaColor" class="form-control form-control-color" type="color" value="#0f8a4b">
              <label>Ghi chú</label>
              <textarea id="gisAreaNote" class="form-control" rows="2" placeholder="Ghi chú nội bộ"></textarea>
            </form>
            <div class="gis-summary-grid" id="gisSummaryCards"></div>
            <div class="gis-area-list" id="gisAreaList"></div>
          </aside>
          <main class="gis-map-card content-card">
            <div class="gis-map-toolbar">
              <div class="gis-search-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="gisSearch" class="form-control" placeholder="Tìm mã hộ, chủ hộ, thành viên, địa chỉ, điện thoại, kinh doanh..."><div id="gisSearchResults" class="gis-search-results d-none"></div></div>
              <select id="gisBaseLayer" class="form-select form-select-sm gis-layer-select" aria-label="Lớp nền bản đồ"><option value="osm">OSM Standard</option><option value="hot">OSM Humanitarian</option><option value="cartoLight">Carto Light</option><option value="cartoDark">Carto Dark</option><option value="esriWorldImagery">Esri World Imagery</option></select><button id="gisCurrentLocationBtn" class="btn btn-outline-success btn-sm" type="button"><i class="fa-solid fa-location-crosshairs"></i> GPS</button><span id="gisMapStatus" class="gis-status-pill">Đang tải bản đồ...</span>
            </div>
            <div id="gisMap" class="gis-map" role="application" aria-label="Bản đồ địa bàn"></div>
          </main>
        </div>
      </section>
      <section id="householdsScreen" class="screen household-management-screen">
        <div class="module-action-row"><button id="householdAddBtn" class="module-primary-action" type="button"><i class="fa-solid fa-plus"></i> Thêm hộ dân</button></div>
        <div class="content-card module-filter-card household-filter-card">
          <div class="household-filter-grid">
            <div class="module-field household-search-field"><label for="householdSearch">Tìm kiếm</label><div class="module-search-input-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="householdSearch" class="form-control" placeholder="Tìm mã hộ, chủ hộ, địa chỉ..."></div></div>
            <div class="module-field"><label for="householdCategoryFilter">Diện hộ</label><select id="householdCategoryFilter" class="form-select"><option value="">Tất cả</option><option value="poor">Hộ nghèo</option><option value="near_poor">Hộ cận nghèo</option><option value="escaped_poverty">Hộ mới thoát nghèo</option><option value="policy">Hộ chính sách</option><option value="meritorious">Hộ có công</option><option value="normal">Hộ bình thường</option><option value="other">Khác</option></select></div>
            <div class="module-field"><label for="householdStatusFilter">Trạng thái</label><select id="householdStatusFilter" class="form-select"><option value="">Tất cả</option><option value="active">Đang quản lý</option><option value="temporary_absence">Có tạm vắng</option><option value="empty_home">Không có người ở nhà</option></select></div>
            <div class="module-field module-page-size-field"><label for="householdPageSize">Hiển thị</label><select id="householdPageSize" class="form-select"><option>20</option><option>50</option><option>100</option></select></div>
            <button id="householdFilterReset" class="btn module-reset-btn" type="button"><i class="fa-solid fa-rotate-right"></i> Làm mới</button>
          </div>
        </div>
        <div class="content-card module-list-card">
          <div class="module-list-head"><div><h3>Danh sách hộ gia đình</h3><span id="householdTotalCount">Tổng số: 0 hộ</span></div><button id="householdBulkDeleteBtn" class="btn btn-outline-danger btn-sm d-none"><i class="fa-solid fa-trash"></i> Xóa đã chọn</button></div>
          <div class="table-responsive"><table class="table module-table align-middle mb-0"><thead><tr><th><input type="checkbox" id="householdCheckAll"></th><th>Mã hộ</th><th>Chủ hộ</th><th>Địa chỉ</th><th>Ở nhà</th><th>Đi vắng</th><th>Diện hộ</th><th class="text-end">Thao tác</th></tr></thead><tbody id="householdRows"></tbody></table></div>
          <div id="householdPager" class="pager module-pager"></div>
        </div>
      </section>



      <section id="businessHouseholdsScreen" class="screen household-management-screen">
        <div class="module-action-row"><button id="businessHouseholdAddBtn" class="module-primary-action" type="button"><i class="fa-solid fa-plus"></i> Thêm thông tin SX/KD</button></div>
        <div class="module-filter-panel household-filter-panel">
          <div class="module-filter-grid household-filter-grid">
            <div class="module-field household-search-field"><label for="businessHouseholdSearch">Tìm kiếm</label><div class="module-search-input-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="businessHouseholdSearch" class="form-control" placeholder="Mã hộ, chủ hộ, tên cơ sở, ngành nghề, điện thoại..."></div></div>
            <div class="module-field"><label for="businessHouseholdTypeFilter">Loại hình</label><select id="businessHouseholdTypeFilter" class="form-select"><option value="">Tất cả</option><option value="RESIDENT">Hộ dân</option><option value="PRODUCTION">Hộ sản xuất</option><option value="BUSINESS">Hộ kinh doanh</option><option value="BOTH">Hộ sản xuất và kinh doanh</option></select></div>
            <div class="module-field"><label for="businessEconomicTypeFilter">Loại hình kinh tế</label><select id="businessEconomicTypeFilter" class="form-select"><option value="">Tất cả</option></select></div>
            <div class="module-field"><label for="businessScaleFilter">Quy mô</label><select id="businessScaleFilter" class="form-select"><option value="">Tất cả</option></select></div>
            <div class="module-field"><label for="businessHouseholdSectorFilter">Ngành nghề</label><input id="businessHouseholdSectorFilter" class="form-control" placeholder="Nhập ngành nghề"></div>
            <div class="module-field"><label for="businessOcopFilter">OCOP</label><select id="businessOcopFilter" class="form-select"><option value="">Tất cả</option><option value="1">Có OCOP</option><option value="0">Không OCOP</option></select></div>
            <div class="module-field"><label for="businessFoodSafetyFilter">ATTP</label><select id="businessFoodSafetyFilter" class="form-select"><option value="">Tất cả</option><option value="1">Có ATTP</option><option value="0">Không ATTP</option></select></div>
            <div class="module-field"><label for="businessSocialInsuranceFilter">BHXH</label><select id="businessSocialInsuranceFilter" class="form-select"><option value="">Tất cả</option><option value="1">Có BHXH</option><option value="0">Không BHXH</option></select></div>
            <div class="module-field"><label for="businessHouseholdLocationFilter">GPS</label><select id="businessHouseholdLocationFilter" class="form-select"><option value="">Tất cả</option><option value="1">Có GPS</option><option value="0">Không GPS</option></select></div>
            <div class="module-field"><label for="businessHouseholdStatusFilter">Trạng thái</label><select id="businessHouseholdStatusFilter" class="form-select"><option value="">Tất cả</option><option value="ACTIVE">Đang hoạt động</option><option value="INACTIVE">Ngừng hoạt động</option><option value="SUSPENDED">Tạm ngừng</option></select></div>
            <div class="module-field module-page-size-field"><label for="businessHouseholdPageSize">Hiển thị</label><select id="businessHouseholdPageSize" class="form-select"><option>20</option><option>50</option><option>100</option></select></div>
            <button id="businessHouseholdFilterReset" class="btn module-reset-btn" type="button"><i class="fa-solid fa-rotate-right"></i> Làm mới</button>
          </div>
        </div>
        <div class="module-list-card household-list-card">
          <div class="module-list-head"><div><h3>Danh sách hộ sản xuất &amp; kinh doanh</h3><span id="businessHouseholdTotalCount">Tổng số: 0 hộ</span></div></div>
          <div class="table-responsive business-list-responsive"><table class="table module-table business-list-table align-middle mb-0"><thead><tr><th>STT</th><th data-business-sort="household_code">Mã hộ</th><th data-business-sort="head_citizen_name">Chủ hộ</th><th data-business-sort="business_name">Tên cơ sở</th><th data-business-sort="business_type">Loại hình</th><th data-business-sort="sector">Ngành nghề</th><th>Hoạt động</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead><tbody id="businessHouseholdRows"></tbody></table></div>
          <div id="businessHouseholdPager" class="pager module-pager"></div>
        </div>
      </section>

      <section id="agricultureScreen" class="screen household-management-screen agriculture-screen">
        <section id="agriMiniDashboard" class="agri-kpi-grid" aria-label="Th&#7889;ng k&#234; s&#7843;n xu&#7845;t n&#244;ng nghi&#7879;p"></section>
        <section class="agri-filter-card" aria-label="B&#7897; l&#7885;c s&#7843;n xu&#7845;t n&#244;ng nghi&#7879;p">
          <div class="agri-filter-row">
            <div class="agri-field agri-search-field"><label for="agriSearch">T&#236;m ki&#7871;m</label><div class="module-search-input-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="agriSearch" class="form-control" placeholder="M&#227; th&#7917;a, khu &#273;&#7891;ng, ch&#7911; s&#7917; d&#7909;ng, ng&#432;&#7901;i s&#7843;n xu&#7845;t..."></div></div>
            <div class="agri-field"><label for="agriLandTypeFilter">Lo&#7841;i &#273;&#7845;t</label><select id="agriLandTypeFilter" class="form-select"><option value="">T&#7845;t c&#7843;</option></select></div>
            <div class="agri-field"><label for="agriCropFilter">C&#226;y tr&#7891;ng</label><select id="agriCropFilter" class="form-select"><option value="">T&#7845;t c&#7843;</option></select></div>
            <div class="agri-field"><label for="agriSeasonFilter">M&#249;a v&#7909;</label><select id="agriSeasonFilter" class="form-select"><option value="">T&#7845;t c&#7843;</option></select></div>
            <div class="agri-field"><label for="agriStatusFilter">Tr&#7841;ng th&#225;i</label><select id="agriStatusFilter" class="form-select"><option value="">T&#7845;t c&#7843;</option></select></div>
            <div class="agri-filter-actions"><button id="agriSearchBtn" class="btn btn-primary" type="button"><i class="fa-solid fa-magnifying-glass"></i> T&#236;m</button><button id="agriResetBtn" class="btn btn-outline-secondary" type="button"><i class="fa-solid fa-rotate-right"></i> &#272;&#7863;t l&#7841;i</button></div>
          </div>
        </section>
        <section class="agri-toolbar"><div><button id="agriAddBtn" class="btn btn-success" type="button"><i class="fa-solid fa-plus"></i> Th&#234;m th&#7917;a</button><button id="agriExportExcelBtn" class="btn btn-outline-success" type="button"><i class="fa-solid fa-file-excel"></i> Xu&#7845;t Excel</button><button id="agriExportPdfBtn" class="btn btn-outline-danger" type="button"><i class="fa-solid fa-file-pdf"></i> Xu&#7845;t PDF</button></div><div class="agri-page-size"><label for="agriPageSize">Hi&#7875;n th&#7883;</label><select id="agriPageSize" class="form-select"><option>20</option><option>50</option><option>100</option></select></div></section>
        <div class="module-list-card household-list-card agri-table-card"><div class="module-list-head"><div><h3>Danh s&#225;ch th&#7917;a s&#7843;n xu&#7845;t n&#244;ng nghi&#7879;p</h3><span id="agriTotalCount">T&#7893;ng s&#7889;: 0 th&#7917;a</span></div></div><div class="table-responsive"><table class="table module-table agri-data-table align-middle mb-0"><thead><tr><th>STT</th><th data-agri-sort="parcel_code">M&#227; th&#7917;a</th><th data-agri-sort="field_area">Khu &#273;&#7891;ng</th><th data-agri-sort="owner_name">Ch&#7911; s&#7917; d&#7909;ng</th><th data-agri-sort="producer_name">Ng&#432;&#7901;i s&#7843;n xu&#7845;t</th><th data-agri-sort="actual_area">Di&#7879;n t&#237;ch</th><th>C&#226;y tr&#7891;ng hi&#7879;n t&#7841;i</th><th>M&#249;a v&#7909;</th><th data-agri-sort="status">Tr&#7841;ng th&#225;i</th><th class="text-end">Thao t&#225;c</th></tr></thead><tbody id="agriRows"></tbody></table></div><div id="agriPager" class="pager module-pager"></div></div>
      </section>
      <section id="housesScreen" class="screen household-management-screen houses-screen">
        <section id="housesMiniDashboard" class="houses-kpi-grid" aria-label="Th&#7889;ng k&#234; nh&#224; &#7903;"></section>
        <section class="houses-filter-card" aria-label="B&#7897; l&#7885;c nh&#224; &#7903;"><div class="houses-filter-row">
          <div class="houses-field houses-search-field"><label for="housesSearch">T&#236;m ki&#7871;m</label><div class="module-search-input-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="housesSearch" class="form-control" placeholder="M&#227; nh&#224;, m&#227; h&#7897;, ch&#7911; h&#7897;, &#273;&#7883;a ch&#7881;..."></div></div>
          <div class="houses-field"><label for="housesTypeFilter">Lo&#7841;i nh&#224;</label><select id="housesTypeFilter" class="form-select"><option value="">T&#7845;t c&#7843;</option></select></div>
          <div class="houses-field"><label for="housesConditionFilter">T&#236;nh tr&#7841;ng</label><select id="housesConditionFilter" class="form-select"><option value="">T&#7845;t c&#7843;</option></select></div>
          <div class="houses-field"><label for="housesFireRiskFilter">PCCC</label><select id="housesFireRiskFilter" class="form-select"><option value="">T&#7845;t c&#7843;</option></select></div>
          <div class="houses-field"><label for="housesLocatedFilter">GPS</label><select id="housesLocatedFilter" class="form-select"><option value="">T&#7845;t c&#7843;</option><option value="1">&#272;&#227; &#273;&#7883;nh v&#7883;</option><option value="0">Ch&#432;a &#273;&#7883;nh v&#7883;</option></select></div>
          <div class="houses-filter-actions"><button id="housesSearchBtn" class="btn btn-primary" type="button"><i class="fa-solid fa-magnifying-glass"></i> T&#236;m</button><button id="housesResetBtn" class="btn btn-outline-secondary" type="button"><i class="fa-solid fa-rotate-right"></i> &#272;&#7863;t l&#7841;i</button></div>
        </div></section>
        <section class="houses-toolbar"><div><button id="housesAddBtn" class="btn btn-success" type="button"><i class="fa-solid fa-plus"></i> Th&#234;m nh&#224;</button><button id="housesExportExcelBtn" class="btn btn-outline-success" type="button"><i class="fa-solid fa-file-excel"></i> Xu&#7845;t Excel</button><button id="housesExportPdfBtn" class="btn btn-outline-danger" type="button"><i class="fa-solid fa-file-pdf"></i> Xu&#7845;t PDF</button></div><div><label for="housesPageSize">Hi&#7875;n th&#7883;</label><select id="housesPageSize" class="form-select"><option>20</option><option>50</option><option>100</option></select></div></section>
        <section class="module-list-card houses-list-card"><div class="module-list-head"><div><h3>Danh s&#225;ch nh&#224; &#7903; &amp; c&#244;ng tr&#236;nh</h3><span id="housesTotalCount">T&#7893;ng s&#7889;: 0 nh&#224;</span></div></div><div id="housesGrid" class="houses-card-grid"></div><div id="housesPager" class="pager module-pager"></div></section>
      </section>      <section id="livestockScreen" class="screen household-management-screen livestock-modern-screen">
        <section id="livestockMiniDashboard" class="livestock-kpi-grid" aria-label="Th&#7889;ng k&#234; v&#7853;t nu&#244;i"></section>

        <section class="livestock-filter-card" aria-label="B&#7897; l&#7885;c v&#7853;t nu&#244;i">
          <div class="livestock-filter-row">
            <div class="livestock-filter-field livestock-search-field">
              <label for="livestockSearch">T&#236;m ki&#7871;m</label>
              <div class="module-search-input-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="livestockSearch" class="form-control" placeholder="M&#227; h&#7897;, ch&#7911; h&#7897;, lo&#7841;i, gi&#7889;ng, &#273;&#7883;a ch&#7881;..."></div>
            </div>
            <div class="livestock-filter-field"><label for="livestockAreaFilter">Khu v&#7921;c</label><select id="livestockAreaFilter" class="form-select"><option value="">T&#7845;t c&#7843;</option></select></div>
            <div class="livestock-filter-field"><label for="livestockAnimalTypeFilter">Lo&#7841;i v&#7853;t nu&#244;i</label><select id="livestockAnimalTypeFilter" class="form-select"><option value="">T&#7845;t c&#7843;</option></select></div>
            <div class="livestock-filter-field"><label for="livestockClassFilter">Ph&#226;n lo&#7841;i</label><input id="livestockClassFilter" class="form-control" placeholder="Khu nu&#244;i, ph&#226;n lo&#7841;i..."></div>
            <div class="livestock-filter-field"><label for="livestockVaccinatedFilter">Ti&#234;m ph&#242;ng</label><select id="livestockVaccinatedFilter" class="form-select"><option value="">T&#7845;t c&#7843;</option><option value="1">&#272;&#227; ti&#234;m</option><option value="0">Ch&#432;a ti&#234;m</option></select></div>
            <div class="livestock-filter-field"><label for="livestockDiseaseFilter">D&#7883;ch b&#7879;nh</label><select id="livestockDiseaseFilter" class="form-select"><option value="">T&#7845;t c&#7843;</option></select></div>
            <div class="livestock-filter-actions"><button id="livestockSearchBtn" class="btn btn-primary" type="button"><i class="fa-solid fa-magnifying-glass"></i> T&#236;m ki&#7871;m</button><button id="livestockFilterReset" class="btn btn-outline-secondary" type="button"><i class="fa-solid fa-rotate-right"></i> &#272;&#7863;t l&#7841;i</button></div>
          </div>
        </section>

        <section class="livestock-toolbar" aria-label="Thanh c&#244;ng c&#7909; v&#7853;t nu&#244;i">
          <div class="livestock-toolbar-left">
            <button id="livestockAddBtn" class="btn btn-success" type="button"><i class="fa-solid fa-plus"></i> Th&#234;m m&#7899;i</button>
            <button id="livestockExportExcelBtn" class="btn btn-outline-success" type="button"><i class="fa-solid fa-file-excel"></i> Xu&#7845;t Excel</button>
            <button id="livestockExportPdfBtn" class="btn btn-outline-danger" type="button"><i class="fa-solid fa-file-pdf"></i> Xu&#7845;t PDF</button>
            <button id="livestockAdvancedFilterBtn" class="btn btn-outline-secondary" type="button"><i class="fa-solid fa-sliders"></i> B&#7897; l&#7885;c n&#226;ng cao</button>
          </div>
          <div class="livestock-toolbar-right"><label for="livestockStatusFilter">Tr&#7841;ng th&#225;i</label><select id="livestockStatusFilter" class="form-select"><option value="">T&#7845;t c&#7843; tr&#7841;ng th&#225;i</option><option value="ACTIVE">&#272;ang nu&#244;i</option><option value="INACTIVE">Ng&#7915;ng nu&#244;i</option></select><label for="livestockPageSize">Hi&#7875;n th&#7883;</label><select id="livestockPageSize" class="form-select"><option>20</option><option>50</option><option>100</option></select></div>
        </section>

        <div class="module-list-card household-list-card livestock-table-card"><div class="module-list-head"><div><h3>Danh s&#225;ch v&#7853;t nu&#244;i</h3><span id="livestockTotalCount">T&#7893;ng s&#7889;: 0 b&#7843;n ghi</span></div></div><div class="table-responsive livestock-table-responsive"><table class="table module-table livestock-data-table align-middle mb-0"><thead><tr><th>STT</th><th data-livestock-sort="household_code">M&#227; h&#7897;</th><th data-livestock-sort="head_citizen_name">Ch&#7911; h&#7897;</th><th>Khu v&#7921;c</th><th data-livestock-sort="animal_type">Lo&#7841;i v&#7853;t nu&#244;i</th><th>Ph&#226;n lo&#7841;i</th><th data-livestock-sort="breed">Gi&#7889;ng</th><th data-livestock-sort="quantity">S&#7889; l&#432;&#7907;ng</th><th>&#272;&#417;n v&#7883;</th><th data-livestock-sort="vaccinated">Ti&#234;m ph&#242;ng</th><th data-livestock-sort="updated_at">Ng&#224;y c&#7853;p nh&#7853;t</th><th class="text-end">Thao t&#225;c</th></tr></thead><tbody id="livestockRows"></tbody></table></div><div id="livestockPager" class="pager module-pager"></div></div>
      </section>

      <div class="modal fade livestock-profile-modal" id="livestockHouseholdModal" tabindex="-1" aria-labelledby="livestockDrawerTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
          <div class="modal-content livestock-profile-content">
            <div class="modal-header livestock-profile-header">
              <div><span>H&#7891; s&#417; ch&#259;n nu&#244;i</span><h3 id="livestockDrawerTitle">&#272;ang t&#7843;i...</h3><p id="livestockDrawerSubtitle"></p></div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="&#272;&#243;ng"></button>
            </div>
            <div id="livestockDrawerBody" class="modal-body livestock-profile-body"></div>
            <div class="modal-footer livestock-profile-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">&#272;&#243;ng</button></div>
          </div>
        </div>
      </div>
      <section id="personsScreen" class="screen person-management-screen">
        <div class="module-action-row person-action-row"><button id="personAddBtn" class="person-primary-action" type="button"><i class="fa-solid fa-plus"></i> Thêm nhân khẩu</button></div>

        <div class="content-card person-search-card mb-4">
          <div class="person-search-row">
            <div class="person-field person-search-field">
              <label for="personSearch">Tìm kiếm nhanh</label>
              <div class="person-search-input-wrap"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input id="personSearch" class="form-control" placeholder="Họ tên, CCCD, mã nhân khẩu hoặc số định danh cá nhân..."></div>
            </div>
          </div>
          <div class="person-quick-filter-grid">
            <div class="person-field"><label>Giới tính</label><select class="form-select" data-person-filter="gender" name="gender"><option value="">Tất cả</option><option value="Nam">Nam</option><option value="Nữ">Nữ</option></select></div>
            <div class="person-field"><label>Cư trú</label><select class="form-select" data-person-filter="residenceCombined" name="residenceCombined"><option value="">Tất cả</option><option value="PERMANENT">Thường trú</option><option value="TEMPORARY">Tạm trú</option><option value="AWAY">Tạm vắng</option></select></div>
            <div class="person-field"><label>Đảng viên</label><select class="form-select" data-person-filter="party_member" name="party_member"><option value="">Tất cả</option><option value="1">Có</option><option value="0">Không</option></select></div>
            <div class="person-field"><label>BHYT</label><select class="form-select" data-person-filter="has_health_insurance" name="has_health_insurance"><option value="">T&#7845;t c&#7843;</option><option value="1">C&#243; BHYT</option><option value="0">Ch&#432;a tham gia</option></select></div>
            <div class="person-field"><label>Độ tuổi</label><select class="form-select" data-person-filter="ageGroup" name="ageGroup"><option value="">Tất cả</option><option value="0_5">0-5 tuổi</option><option value="6_14">6-14 tuổi</option><option value="15_17">15-17 tuổi</option><option value="18_59">18-59 tuổi</option><option value="60_plus">Từ 60 tuổi trở lên</option></select></div>
            <div class="person-filter-actions">
              <button id="personFilterReset" class="person-reset-icon" type="button" title="Làm mới bộ lọc" aria-label="Làm mới bộ lọc"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></button>
              <button id="personAdvancedToggle" class="btn person-advanced-toggle" type="button" aria-expanded="false" aria-controls="personAdvancedFilters"><i class="fa-solid fa-sliders"></i> Bộ lọc nâng cao</button>
            </div>
          </div>
          <div id="personAdvancedFilters" class="person-advanced-panel d-none">
            <div class="person-advanced-grid">
              <div class="person-field"><label>Đoàn viên Thanh niên</label><select class="form-select" data-person-filter="youth_union_member" name="youth_union_member"><option value="">Tất cả</option><option value="1">Có</option><option value="0">Không</option></select></div>
              <div class="person-field"><label>Hội Phụ nữ</label><select class="form-select" data-person-filter="women_union_member" name="women_union_member"><option value="">Tất cả</option><option value="1">Có</option><option value="0">Không</option></select></div>
              <div class="person-field"><label>Hội Nông dân</label><select class="form-select" data-person-filter="farmers_union_member" name="farmers_union_member"><option value="">Tất cả</option><option value="1">Có</option><option value="0">Không</option></select></div>
              <div class="person-field"><label>Hội Cựu chiến binh</label><select class="form-select" data-person-filter="veterans_union_member" name="veterans_union_member"><option value="">Tất cả</option><option value="1">Có</option><option value="0">Không</option></select></div>
              <div class="person-field"><label>Hội Người cao tuổi</label><select class="form-select" data-person-filter="elderly_union_member" name="elderly_union_member"><option value="">Tất cả</option><option value="1">Có</option><option value="0">Không</option></select></div>
              <div class="person-field"><label>Người có công</label><select class="form-select" data-person-filter="meritorious_person" name="meritorious_person"><option value="">Tất cả</option><option value="1">Có</option><option value="0">Không</option></select></div>
              <div class="person-field"><label>Người khuyết tật</label><select class="form-select" data-person-filter="disabled_person" name="disabled_person"><option value="">Tất cả</option><option value="1">Có</option><option value="0">Không</option></select></div>
              <div class="person-field"><label>Bảo trợ xã hội</label><select class="form-select" data-person-filter="social_assistance" name="social_assistance"><option value="">Tất cả</option><option value="1">Có</option><option value="0">Không</option></select></div>
              <div class="person-field"><label>Tình trạng hôn nhân</label><select class="form-select" data-person-filter="maritalStatus" name="maritalStatus" data-dictionary="maritalStatuses"><option value="">Tất cả</option></select></div>
              <div class="person-field"><label>Quốc tịch</label><input class="form-control" data-person-filter="nationality" name="nationality" placeholder="Nhập quốc tịch"></div>
            </div>
            <div class="person-advanced-footer">
              <button id="personAdvancedClear" class="btn person-advanced-clear" type="button">Xóa lọc nâng cao</button>
              <button id="personAdvancedApply" class="btn person-advanced-apply" type="button">Áp dụng</button>
            </div>
          </div>
        </div>

        <div class="content-card person-list-card">
          <div class="person-list-head">
            <div><h3>Danh sách nhân khẩu</h3><span id="personTotalCount">Tổng số: 0 nhân khẩu</span></div>
            <div class="d-flex gap-2 align-items-center"><button id="personBulkDeleteBtn" class="btn btn-outline-danger btn-sm d-none"><i class="fa-solid fa-trash"></i> Xóa đã chọn</button><select id="personPageSize" class="form-select person-page-size"><option>20</option><option>50</option><option>100</option></select></div>
          </div>
          <div class="table-responsive person-table-wrap"><table class="table person-table align-middle mb-0"><thead><tr><th><input type="checkbox" id="personCheckAll"></th><th>Mã hộ</th><th>Mã nhân khẩu</th><th>Họ và tên</th><th>Quan hệ</th><th>Ngày sinh</th><th>Tuổi</th><th>Giới tính</th><th>CCCD/Số định danh</th><th>Cư trú</th><th>Đảng viên</th><th>BHYT</th><th class="text-end">Thao tác</th></tr></thead><tbody id="personRows"></tbody></table></div>
          <div id="personPager" class="pager person-pager"></div>
        </div>
      </section>

      <section id="reportsScreen" class="screen report-screen smart-report-screen">
        <div class="report-center-grid">
          <section class="content-card smart-report-center">
            <div class="smart-report-head">
              <div><h3>Trung t&#226;m B&#225;o c&#225;o</h3><span>Nh&#243;m b&#225;o c&#225;o d&#249;ng d&#7919; li&#7879;u th&#7921;c, l&#7885;c n&#226;ng cao v&#224; xu&#7845;t theo m&#7851;u A4.</span></div>
              <button id="reportRefreshBtn" class="btn btn-outline-secondary btn-sm" type="button"><i class="fa-solid fa-rotate"></i> T&#7843;i l&#7841;i</button>
            </div>
            <div id="reportGroupGrid" class="report-group-grid"></div>
          </section>

          <section class="content-card smart-report-bi">
            <div class="smart-report-head"><div><h3>Dashboard BI</h3><span id="reportBiGeneratedAt">&#272;ang ch&#7901; d&#7919; li&#7879;u</span></div></div>
            <div id="reportBiKpis" class="report-bi-kpis"></div>
            <div id="reportBiCharts" class="report-bi-charts"></div>
          </section>
        </div>

        <form id="reportForm" class="content-card report-filter-card smart-report-filter-card">
          <div class="report-filter-toolbar">
            <div class="report-field report-type-field">
              <label class="form-label">Lo&#7841;i b&#225;o c&#225;o</label>
              <select name="type" class="form-select" id="reportTypeSelect">
                <option value="summary">B&#225;o c&#225;o t&#7893;ng h&#7907;p</option>
                <option value="population">B&#225;o c&#225;o nh&#226;n kh&#7849;u</option>
                <option value="household">B&#225;o c&#225;o h&#7897; gia &#273;&#236;nh</option>
                <option value="migration">B&#225;o c&#225;o bi&#7871;n &#273;&#7897;ng</option>
                <option value="gis">B&#225;o c&#225;o GIS</option>
                <option value="gis-located">H&#7897; &#273;&#227; &#273;&#7883;nh v&#7883; GPS</option>
                <option value="gis-unlocated">H&#7897; ch&#432;a &#273;&#7883;nh v&#7883; GPS</option>
                <option value="digital-profile">B&#225;o c&#225;o H&#7891; s&#417; s&#7889;</option>
                <option value="profile-complete">H&#7891; s&#417; ho&#224;n ch&#7881;nh</option>
                <option value="profile-missing-photo">H&#7891; s&#417; thi&#7871;u &#7843;nh</option>
                <option value="profile-missing-documents">H&#7891; s&#417; thi&#7871;u gi&#7845;y t&#7901;</option>
                <option value="profile-incomplete">H&#7891; s&#417; ch&#432;a ho&#224;n thi&#7879;n</option>
                <option value="temporary_residence">Danh s&#225;ch t&#7841;m tr&#250;</option>
                <option value="temporary_absence">Danh s&#225;ch t&#7841;m v&#7855;ng</option>
                <option value="children">Danh s&#225;ch tr&#7867; em</option>
                <option value="elderly">Danh s&#225;ch ng&#432;&#7901;i cao tu&#7893;i</option>
                <option value="labor">Danh s&#225;ch lao &#273;&#7897;ng</option>
                <option value="health_insurance">Báo cáo Bảo hiểm y tế</option>
                <option value="health-insurance-area">Th&#7889;ng k&#234; BHYT theo khu v&#7921;c</option>
                <option value="health-insurance-household">Th&#7889;ng k&#234; BHYT theo h&#7897;</option>
                <option value="health-insurance-expired">Danh s&#225;ch BHYT &#273;&#227; h&#7871;t h&#7841;n</option>
                <option value="health-insurance-expiring">Danh s&#225;ch BHYT s&#7855;p h&#7871;t h&#7841;n (30 ng&#224;y)</option>
                <option value="health-insurance-missing">Danh s&#225;ch ch&#432;a tham gia BHYT</option>
                <option value="party_member">Danh s&#225;ch &#272;&#7843;ng vi&#234;n</option>
                <option value="youth_union">Danh s&#225;ch &#272;o&#224;n vi&#234;n</option>
                <option value="poor-households">Danh s&#225;ch h&#7897; ngh&#232;o</option>
                <option value="near-poor-households">Danh s&#225;ch h&#7897; c&#7853;n ngh&#232;o</option>
                <option value="age">Th&#7889;ng k&#234; theo &#273;&#7897; tu&#7893;i</option>
                <option value="gender">Th&#7889;ng k&#234; theo gi&#7899;i t&#237;nh</option>
              </select>
            </div>
            <button class="btn report-view-btn" type="submit"><i class="fa-solid fa-chart-column"></i> Xem b&#225;o c&#225;o</button>
          </div>
          <div class="report-filter-grid smart-report-filter-grid">
            <div class="report-field"><label class="form-label">T&#7915; ng&#224;y</label><input name="dateFrom" type="date" class="form-control"></div>
            <div class="report-field"><label class="form-label">&#272;&#7871;n ng&#224;y</label><input name="dateTo" type="date" class="form-control"></div>
            <div class="report-field"><label class="form-label">Khu v&#7921;c</label><input name="area" class="form-control" placeholder="M&#227; khu v&#7921;c"></div>
            <div class="report-field"><label class="form-label">M&#227; h&#7897;</label><input name="householdCode" class="form-control" placeholder="VD: H001"></div>
            <div class="report-field"><label class="form-label">Ch&#7911; h&#7897;</label><input name="headName" class="form-control" placeholder="T&#234;n ch&#7911; h&#7897;"></div>
            <div class="report-field"><label class="form-label">Nh&#226;n kh&#7849;u</label><input name="citizen" class="form-control" placeholder="H&#7885; t&#234;n, CCCD, S&#272;T"></div>
            <div class="report-field"><label class="form-label">Gi&#7899;i t&#237;nh</label><select name="gender" class="form-select"><option value="">T&#7845;t c&#7843;</option><option>Nam</option><option>N&#7919;</option><option>Kh&#225;c</option></select></div>
            <div class="report-field"><label class="form-label">Tu&#7893;i t&#7915;</label><input name="ageFrom" type="number" min="0" class="form-control"></div>
            <div class="report-field"><label class="form-label">Tu&#7893;i &#273;&#7871;n</label><input name="ageTo" type="number" min="0" class="form-control"></div>
            <div class="report-field"><label class="form-label">Ngh&#7873; nghi&#7879;p</label><input name="occupation" class="form-control" placeholder="Ngh&#7873; nghi&#7879;p"></div>
            <div class="report-field"><label class="form-label">Di&#7879;n h&#7897;</label><select name="category" class="form-select"><option value="">T&#7845;t c&#7843;</option><option value="poor">H&#7897; ngh&#232;o</option><option value="near_poor">H&#7897; c&#7853;n ngh&#232;o</option><option value="meritorious">H&#7897; c&#243; c&#244;ng</option><option value="policy">H&#7897; ch&#237;nh s&#225;ch</option><option value="normal">H&#7897; b&#236;nh th&#432;&#7901;ng</option></select></div>
            <div class="report-field"><label class="form-label">C&#432; tr&#250;</label><select name="residencyStatus" class="form-select"><option value="">T&#7845;t c&#7843;</option><option value="PERMANENT">Th&#432;&#7901;ng tr&#250;</option><option value="TEMPORARY">T&#7841;m tr&#250;</option></select></div>
            <div class="report-field"><label class="form-label">Hi&#7879;n di&#7879;n</label><select name="presenceStatus" class="form-select"><option value="">T&#7845;t c&#7843;</option><option value="AT_HOME">&#7902; nh&#224;</option><option value="AWAY">T&#7841;m v&#7855;ng</option></select></div>
            <div class="report-field"><label class="form-label">GPS</label><select name="gpsStatus" class="form-select"><option value="">T&#7845;t c&#7843;</option><option value="located">&#272;&#227; &#273;&#7883;nh v&#7883;</option><option value="missing">Ch&#432;a &#273;&#7883;nh v&#7883;</option></select></div>
            <div class="report-field"><label class="form-label">H&#7891; s&#417; s&#7889;</label><select name="digitalProfileStatus" class="form-select"><option value="">T&#7845;t c&#7843;</option><option value="complete">Ho&#224;n ch&#7881;nh</option><option value="incomplete">Ch&#432;a ho&#224;n thi&#7879;n</option></select></div>
            <div class="report-checks">
              <label><input name="party_member" type="checkbox" value="1"> &#272;&#7843;ng vi&#234;n</label>
              <label><input name="youth_union_member" type="checkbox" value="1"> &#272;o&#224;n vi&#234;n</label>
            </div>
          </div>
        </form>

        <div class="report-tools-grid">
          <section class="content-card report-template-card"><div class="smart-report-head"><div><h3>Th&#432; vi&#7879;n bi&#7875;u m&#7851;u</h3><span>Chu&#7849;n kh&#7893; A4, d&#249;ng chung b&#7897; l&#7885;c hi&#7879;n t&#7841;i.</span></div></div><div id="reportTemplateLibrary" class="report-template-library"></div></section>
          <section class="content-card report-template-card"><div class="smart-report-head"><div><h3>M&#7851;u &#273;&#227; l&#432;u</h3><span>L&#432;u, m&#7903; l&#7841;i, x&#243;a ho&#7863;c &#273;&#7863;t m&#7863;c &#273;&#7883;nh b&#7897; l&#7885;c.</span></div><button id="reportSaveTemplateBtn" class="btn btn-outline-primary btn-sm" type="button"><i class="fa-solid fa-bookmark"></i> L&#432;u m&#7851;u</button></div><div id="reportSavedTemplates" class="report-saved-templates"></div></section>
        </div>

        <div class="content-card report-result-card smart-report-result-card">
          <div class="report-result-head">
            <div><h3 id="reportTitle">B&#225;o c&#225;o</h3><span id="reportCount">Ch&#432;a sinh b&#225;o c&#225;o</span></div>
            <div id="reportActions" class="report-actions d-none">
              <button id="reportPrintBtn" class="btn report-action-btn" type="button"><i class="fa-solid fa-print"></i> In</button>
              <button id="reportExcelBtn" class="btn report-action-btn report-excel-btn" type="button"><i class="fa-solid fa-file-excel"></i> Excel</button>
              <button id="reportPdfBtn" class="btn report-action-btn report-pdf-btn" type="button"><i class="fa-solid fa-file-pdf"></i> PDF</button>
              <button id="reportWordBtn" class="btn report-action-btn report-word-btn" type="button"><i class="fa-solid fa-file-word"></i> Word</button>
            </div>
          </div>
          <div id="reportPreview" class="report-preview table-responsive"><div class="report-empty-state">Chọn loại báo cáo và bấm Xem báo cáo để sinh dữ liệu.</div></div>
        </div>
      </section>
    </section>
  </div>

  <div class="modal fade" id="householdModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" id="householdForm"><div class="modal-header"><h5 class="modal-title">Thông tin hộ dân</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="row g-3"><div class="col-md-4"><label class="form-label">Mã hộ</label><input name="householdCode" class="form-control" required></div><div class="col-md-4"><label class="form-label">Tên chủ hộ</label><input name="headCitizenName" class="form-control" required></div><div class="col-md-4"><label class="form-label">Số điện thoại</label><input name="phone" class="form-control"></div><div class="col-12"><label class="form-label">Địa chỉ</label><input name="address" class="form-control" required></div><div class="col-md-3 form-check ms-2"><input name="meritoriousFamily" class="form-check-input" type="checkbox" id="meritoriousFamily"><label class="form-check-label" for="meritoriousFamily">Gia đình có công</label></div><div class="col-md-2 form-check"><input name="poorHousehold" class="form-check-input" type="checkbox" id="poorHousehold"><label class="form-check-label" for="poorHousehold">Hộ nghèo</label></div><div class="col-md-2 form-check"><input name="nearPoorHousehold" class="form-check-input" type="checkbox" id="nearPoorHousehold"><label class="form-check-label" for="nearPoorHousehold">Cận nghèo</label></div><div class="col-md-2 form-check"><input name="disabledHousehold" class="form-check-input" type="checkbox" id="disabledHousehold"><label class="form-check-label" for="disabledHousehold">Tàn tật</label></div><div class="col-12"><label class="form-label">Ảnh hộ</label><input name="householdPhoto" type="file" class="form-control" accept="image/*"></div><div class="col-12"><label class="form-label">Ghi chú</label><textarea name="note" class="form-control" rows="2"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button><button type="button" class="btn btn-outline-secondary" onclick="window.print()">In phiếu hộ</button><button type="submit" class="btn btn-primary">Lưu</button></div></form></div></div>



  <div class="modal fade" id="businessHouseholdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <form class="modal-content" id="businessHouseholdForm" novalidate>
        <div class="modal-header">
          <h5 class="modal-title">Th&ocirc;ng tin h&#7897; s&#7843;n xu&#7845;t &amp; kinh doanh</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="&#272;&oacute;ng"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id">
          <input type="hidden" name="household_id" id="businessHouseholdId">
          <div class="row g-3">
            <div class="col-12">
              <h6 class="module-section-title">Th&ocirc;ng tin h&#7897;</h6>
            </div>
            <div class="col-md-6 position-relative">
              <label class="form-label" for="businessHouseholdAutocomplete">H&#7897; gia &#273;&igrave;nh</label>
              <input id="businessHouseholdAutocomplete" class="form-control" autocomplete="off" placeholder="Nh&#7853;p m&atilde; h&#7897; ho&#7863;c t&ecirc;n ch&#7911; h&#7897;..." required>
              <div id="businessHouseholdSuggestions" class="list-group position-absolute w-100 shadow d-none" style="z-index:1060;max-height:260px;overflow:auto"></div>
              <div id="businessHouseholdSelected" class="form-text"></div>
            </div>
            <div class="col-md-3"><label class="form-label">Ch&#7911; c&#417; s&#7903;</label><input name="owner_name" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">&#272;i&#7879;n tho&#7841;i</label><input name="phone" class="form-control"></div>

            <div class="col-12"><h6 class="module-section-title">Th&ocirc;ng tin c&#417; s&#7903;</h6></div>
            <div class="col-md-4"><label class="form-label">T&ecirc;n c&#417; s&#7903;</label><input name="business_name" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Lo&#7841;i h&igrave;nh</label><select name="business_type" class="form-select" required><option value="PRODUCTION">H&#7897; s&#7843;n xu&#7845;t</option><option value="BUSINESS">H&#7897; kinh doanh</option><option value="BOTH">H&#7897; s&#7843;n xu&#7845;t v&agrave; kinh doanh</option><option value="RESIDENT">H&#7897; d&acirc;n</option></select></div>
            <div class="col-md-4"><label class="form-label">Ng&agrave;y b&#7855;t &#273;&#7847;u ho&#7841;t &#273;&#7897;ng</label><input name="start_date" type="date" class="form-control"></div>
            <div class="col-md-4"><label class="form-label" for="businessEconomicTypeSelect">Lo&#7841;i h&igrave;nh kinh t&#7871;</label><select id="businessEconomicTypeSelect" name="economic_type" class="form-select"></select></div>
            <div class="col-md-4"><label class="form-label" for="businessScaleSelect">Quy m&ocirc;</label><select id="businessScaleSelect" name="business_scale" class="form-select"></select></div>
            <div class="col-md-4"><label class="form-label">S&#7843;n ph&#7849;m ch&iacute;nh</label><input name="main_products" class="form-control" placeholder="L&uacute;a, Rau, D&#7883;ch v&#7909;..."></div>
            <div class="col-md-6"><label class="form-label">Ng&agrave;nh s&#7843;n xu&#7845;t</label><input name="production_sector" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Ng&agrave;nh kinh doanh</label><input name="business_sector" class="form-control"></div>

            <div class="col-12"><h6 class="module-section-title">Gi&#7845;y ph&eacute;p v&agrave; thu&#7871;</h6></div>
            <div class="col-md-4"><label class="form-label">S&#7889; gi&#7845;y ph&eacute;p</label><input name="business_license" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Ng&agrave;y c&#7845;p</label><input name="license_date" type="date" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">N&#417;i c&#7845;p</label><input name="license_place" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">M&atilde; s&#7889; thu&#7871;</label><input name="tax_code" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">S&#7889; lao &#273;&#7897;ng</label><input name="worker_count" type="number" min="0" class="form-control" value="0"></div>
            <div class="col-md-4"><label class="form-label">Doanh thu n&#259;m</label><input name="annual_revenue" type="number" min="0" step="1000" class="form-control"></div>

            <div class="col-12"><h6 class="module-section-title">OCOP, ATTP, BHXH</h6></div>
            <div class="col-md-4"><div class="form-check form-switch mt-4"><input id="businessOcopCheck" name="is_ocop" class="form-check-input" type="checkbox" value="1"><label class="form-check-label" for="businessOcopCheck">Tham gia OCOP</label></div></div>
            <div class="col-md-4 business-ocop-fields d-none"><label class="form-label">S&#7843;n ph&#7849;m OCOP</label><input name="ocop_product" class="form-control"></div>
            <div class="col-md-4 business-ocop-fields d-none"><label class="form-label">S&#7889; sao OCOP</label><select name="ocop_star" class="form-select"><option value="">Ch&#7885;n</option><option value="3">3 sao</option><option value="4">4 sao</option><option value="5">5 sao</option></select></div>
            <div class="col-md-4"><div class="form-check form-switch mt-4"><input id="businessFoodSafetyCheck" name="food_safety_certified" class="form-check-input" type="checkbox" value="1"><label class="form-check-label" for="businessFoodSafetyCheck">C&oacute; ch&#7913;ng nh&#7853;n ATTP</label></div></div>
            <div class="col-md-4"><label class="form-label">S&#7889; ch&#7913;ng nh&#7853;n ATTP</label><input name="food_safety_certificate_no" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Ng&agrave;y h&#7871;t h&#7841;n ATTP</label><input name="food_safety_expired_date" type="date" class="form-control"></div>
            <div class="col-md-4"><div class="form-check form-switch mt-4"><input id="businessSocialInsuranceCheck" name="social_insurance" class="form-check-input" type="checkbox" value="1"><label class="form-check-label" for="businessSocialInsuranceCheck">Tham gia BHXH</label></div></div>
            <div class="col-md-4"><label class="form-label">Lao &#273;&#7897;ng tham gia BHXH</label><input name="insured_workers" type="number" min="0" class="form-control" value="0"></div>

            <div class="col-12"><h6 class="module-section-title">Li&ecirc;n h&#7879; v&agrave; GIS</h6></div>
            <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Tr&#7841;ng th&aacute;i</label><select name="status" class="form-select"><option value="ACTIVE">&#272;ang ho&#7841;t &#273;&#7897;ng</option><option value="INACTIVE">Ng&#7915;ng ho&#7841;t &#273;&#7897;ng</option><option value="SUSPENDED">T&#7841;m ng&#7915;ng</option></select></div>
            <div class="col-12"><label class="form-label">&#272;&#7883;a ch&#7881;</label><input name="address" class="form-control"></div>
            <input type="hidden" name="gps_source" value="household">
            <input type="hidden" name="latitude">
            <input type="hidden" name="longitude">
            <div class="col-12">
              <div class="business-gis-panel" id="businessGpsPanel">
                <div class="business-gis-header">
                  <div>
                    <div class="business-gis-title"><i class="fa-solid fa-location-dot"></i> GPS</div>
                    <div class="business-gis-status" id="businessGpsStatusText">Ch&#432;a ch&#7885;n h&#7897; gia &#273;&igrave;nh.</div>
                  </div>
                  <span class="business-gis-badge" id="businessGpsSourceBadge">GPS h&#7897;</span>
                </div>
                <div class="business-gis-meta" id="businessGpsMeta">
                  Ch&#7885;n h&#7897; gia &#273;&igrave;nh &#273;&#7875; h&#7879; th&#7889;ng t&#7921; l&#7845;y v&#7883; tr&iacute; GIS c&#7911;a h&#7897;.
                </div>
                <div class="form-check form-switch business-gis-switch">
                  <input id="businessActivityOwnGps" class="form-check-input" type="checkbox">
                  <label class="form-check-label" for="businessActivityOwnGps">Ho&#7841;t &#273;&#7897;ng c&oacute; v&#7883; tr&iacute; ri&ecirc;ng</label>
                </div>
                <div class="business-gis-actions d-none" id="businessGpsActions">
                  <button type="button" class="btn btn-sm btn-outline-success" data-business-gps-action="current"><i class="fa-solid fa-location-crosshairs"></i> L&#7845;y GPS hi&#7879;n t&#7841;i</button>
                  <button type="button" class="btn btn-sm btn-outline-primary" data-business-gps-action="map"><i class="fa-solid fa-map-location-dot"></i> Ch&#7885;n tr&ecirc;n b&#7843;n &#273;&#7891;</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-business-gps-action="geocode"><i class="fa-solid fa-magnifying-glass-location"></i> Nh&#7853;p &#273;&#7883;a ch&#7881;</button>
                </div>
              </div>
            </div>

            <div class="col-12"><h6 class="module-section-title">H&igrave;nh &#7843;nh v&agrave; h&#7891; s&#417;</h6></div>
            <div class="col-md-4"><label class="form-label" for="businessImageCategory">Danh m&#7909;c &#7843;nh</label><select id="businessImageCategory" class="form-select"></select></div>
            <div class="col-md-8"><label class="form-label" for="businessImageFiles">Upload &#7843;nh</label><input id="businessImageFiles" type="file" class="form-control" accept="image/*" multiple></div>
            <div class="col-md-4"><label class="form-label" for="businessDocumentCategory">Lo&#7841;i t&agrave;i li&#7879;u</label><select id="businessDocumentCategory" class="form-select"></select></div>
            <div class="col-md-8"><label class="form-label" for="businessDocumentFiles">Upload t&agrave;i li&#7879;u</label><input id="businessDocumentFiles" type="file" class="form-control" accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png" multiple></div>
            <div class="col-12"><div id="businessFileExisting" class="form-text">Ch&#432;a c&oacute; &#7843;nh ho&#7863;c h&#7891; s&#417; &#273;&iacute;nh k&egrave;m</div></div>
            <div class="col-12"><label class="form-label">Ghi ch&uacute;</label><textarea name="note" class="form-control" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">H&#7911;y</button>
          <button type="submit" class="btn btn-primary">L&#432;u</button>
        </div>
      </form>
    </div>
  </div>


  <div class="modal fade agri-detail-modal" id="agriDetailModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><span class="agri-modal-eyebrow">H&#7891; s&#417; s&#7843;n xu&#7845;t n&#244;ng nghi&#7879;p</span><h5 id="agriDetailTitle" class="modal-title">Chi ti&#7871;t th&#7917;a</h5><p id="agriDetailSubtitle" class="mb-0 text-muted"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="&#272;&#243;ng"></button></div><div class="modal-body"><ul class="nav nav-tabs agri-tabs" role="tablist"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#agriTabOverview" type="button">T&#7893;ng quan</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#agriTabPlots" type="button">L&#244; s&#7843;n xu&#7845;t</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#agriTabSeasons" type="button">M&#249;a v&#7909;</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#agriTabLogs" type="button">Nh&#7853;t k&#253;</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#agriTabDamages" type="button">Thi&#7879;t h&#7841;i</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#agriTabFiles" type="button">H&#236;nh &#7843;nh</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#agriTabGis" type="button">GIS</button></li></ul><div class="tab-content agri-tab-content"><div id="agriTabOverview" class="tab-pane fade show active"></div><div id="agriTabPlots" class="tab-pane fade"></div><div id="agriTabSeasons" class="tab-pane fade"></div><div id="agriTabLogs" class="tab-pane fade"></div><div id="agriTabDamages" class="tab-pane fade"></div><div id="agriTabFiles" class="tab-pane fade"></div><div id="agriTabGis" class="tab-pane fade"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-primary" id="agriDetailEditBtn"><i class="fa-solid fa-pen"></i> S&#7917;a</button><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">&#272;&#243;ng</button></div></div></div></div>
  <div class="modal fade" id="agriFormModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" id="agriForm"><div class="modal-header"><h5 class="modal-title">Th&#244;ng tin th&#7917;a s&#7843;n xu&#7845;t</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="&#272;&#243;ng"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="row g-3"><div class="col-12"><h6 class="module-section-title">Th&#244;ng tin th&#7917;a</h6></div><div class="col-md-3"><label class="form-label">S&#7889; t&#7901; b&#7843;n &#273;&#7891;</label><input name="map_sheet_no" class="form-control"></div><div class="col-md-3"><label class="form-label">S&#7889; th&#7917;a</label><input name="parcel_no" class="form-control"></div><div class="col-md-3"><label class="form-label">Khu &#273;&#7891;ng</label><input name="field_area" class="form-control"></div><div class="col-md-3"><label class="form-label">X&#7913; &#273;&#7891;ng</label><input name="field_name" class="form-control"></div><div class="col-md-4"><label class="form-label">Lo&#7841;i &#273;&#7845;t</label><select name="land_type" id="agriFormLandType" class="form-select"></select></div><div class="col-md-4"><label class="form-label">H&#236;nh th&#7913;c s&#7917; d&#7909;ng</label><select name="usage_form" id="agriFormUsage" class="form-select"></select></div><div class="col-md-4"><label class="form-label">Tr&#7841;ng th&#225;i</label><select name="status" id="agriFormStatus" class="form-select"></select></div><div class="col-md-3"><label class="form-label">Di&#7879;n t&#237;ch gi&#7845;y t&#7901; (m&#178;)</label><input name="legal_area" type="number" min="0" step="0.01" class="form-control" value="0"></div><div class="col-md-3"><label class="form-label">Di&#7879;n t&#237;ch th&#7921;c t&#7871; (m&#178;)</label><input name="actual_area" type="number" min="0" step="0.01" class="form-control" value="0"></div><div class="col-md-3"><label class="form-label">&#272;ang s&#7843;n xu&#7845;t (m&#178;)</label><input name="cultivated_area" type="number" min="0" step="0.01" class="form-control" value="0"></div><div class="col-md-3"><label class="form-label">B&#7887; hoang (m&#178;)</label><input name="abandoned_area" type="number" min="0" step="0.01" class="form-control" value="0"></div><div class="col-12"><h6 class="module-section-title">Ch&#7911; s&#7917; d&#7909;ng &#273;&#7845;t</h6></div><div class="col-md-4"><label class="form-label">Lo&#7841;i ch&#7911; th&#7875;</label><select name="owner_type" id="agriOwnerType" class="form-select"></select></div><div class="col-md-4"><label class="form-label">T&#234;n ch&#7911; s&#7917; d&#7909;ng</label><input name="owner_name" class="form-control" required></div><div class="col-md-4"><label class="form-label">&#272;i&#7879;n tho&#7841;i</label><input name="owner_phone" class="form-control"></div><div class="col-12"><label class="form-label">&#272;&#7883;a ch&#7881; ch&#7911; s&#7917; d&#7909;ng</label><input name="owner_address" class="form-control"></div><div class="col-12"><h6 class="module-section-title">Ng&#432;&#7901;i tr&#7921;c ti&#7871;p s&#7843;n xu&#7845;t</h6></div><div class="col-md-4"><label class="form-label">Lo&#7841;i ch&#7911; th&#7875;</label><select name="producer_type" id="agriProducerType" class="form-select"></select></div><div class="col-md-4"><label class="form-label">T&#234;n ng&#432;&#7901;i s&#7843;n xu&#7845;t</label><input name="producer_name" class="form-control" required></div><div class="col-md-4"><label class="form-label">&#272;i&#7879;n tho&#7841;i</label><input name="producer_phone" class="form-control"></div><div class="col-12"><label class="form-label">&#272;&#7883;a ch&#7881; ng&#432;&#7901;i s&#7843;n xu&#7845;t</label><input name="producer_address" class="form-control"></div><div class="col-12"><h6 class="module-section-title">GIS</h6></div><div class="col-md-3"><label class="form-label">V&#297; &#273;&#7897;</label><input name="latitude" type="number" step="0.00000001" class="form-control"></div><div class="col-md-3"><label class="form-label">Kinh &#273;&#7897;</label><input name="longitude" type="number" step="0.00000001" class="form-control"></div><div class="col-md-6"><label class="form-label">Polygon GeoJSON</label><input name="polygon_geojson" class="form-control" placeholder="S&#7869; &#273;&#432;&#7907;c c&#7853;p nh&#7853;t t&#7915; GIS"></div><div class="col-12"><label class="form-label">Ghi ch&#250;</label><textarea name="note" rows="3" class="form-control"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">H&#7911;y</button><button type="submit" class="btn btn-primary">L&#432;u</button></div></form></div></div>
  <div class="modal fade" id="personModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" id="personForm"><div class="modal-header"><h5 class="modal-title">Thông tin nhân khẩu</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="row g-3"><div class="col-md-3"><label class="form-label">Mã hộ</label><input name="householdCode" class="form-control" required></div><div class="col-md-3"><label class="form-label">Mã nhân khẩu</label><input name="citizenCode" class="form-control"></div><div class="col-md-6"><label class="form-label">Họ và tên</label><input name="fullName" class="form-control" required></div><div class="col-md-3"><label class="form-label">Giới tính</label><select name="gender" class="form-select"><option>Nam</option><option>Nữ</option><option>Khác</option></select></div><div class="col-md-3"><label class="form-label">Ngày sinh</label><input name="dateOfBirth" type="date" class="form-control" required></div><div class="col-md-3"><label class="form-label">CCCD</label><input name="identityNumber" class="form-control"></div><div class="col-md-3"><label class="form-label">Số điện thoại</label><input name="phone" class="form-control"></div><div class="col-md-3"><label class="form-label">Quan hệ với chủ hộ</label><select name="relationship" class="form-select" data-options="relationships"></select></div><div class="col-md-3"><label class="form-label">Dân tộc</label><select name="ethnicity" class="form-select" data-options="ethnicities"></select></div><div class="col-md-3"><label class="form-label">Tôn giáo</label><select name="religion" class="form-select" data-options="religions"></select></div><div class="col-md-3"><label class="form-label">Nghề nghiệp</label><select name="occupation" class="form-select" data-options="occupations"></select></div><div class="col-md-3"><label class="form-label">Học vấn</label><select name="educationLevel" class="form-select" data-options="educationLevels"></select></div><div class="col-md-3"><label class="form-label">Hôn nhân</label><select name="maritalStatus" class="form-select" data-options="maritalStatuses"></select></div><div class="col-md-3"><label class="form-label">Thường trú</label><select name="residency_status" class="form-select"><option value="PERMANENT">Thường trú</option><option value="TEMPORARY">Tạm trú</option></select></div><div class="col-md-3"><label class="form-label">Hiện tại</label><select name="presenceStatus" class="form-select"><option value="AT_HOME">Ở nhà</option><option value="AWAY">Đi vắng</option></select></div><div class="col-md-3"><label class="form-label">Trạng thái</label><select name="status" class="form-select"><option value="ALIVE">Còn sống</option><option value="DECEASED">Đã chết</option></select></div><div class="col-md-9"><label class="form-label">Địa chỉ hiện tại</label><input name="currentAddress" class="form-control"></div><div class="col-12 person-bhyt-form-section"><div class="person-bhyt-form-head"><div><strong>B&#7843;o hi&#7875;m y t&#7871;</strong></div><div class="form-check form-switch mb-0"><input name="hasHealthInsurance" value="1" class="form-check-input" type="checkbox" id="hasHealthInsurance"><label class="form-check-label" for="hasHealthInsurance">C&#243; tham gia BHYT</label></div></div><div class="row g-3 mt-1 d-none" data-health-insurance-fields><div class="col-md-3"><label class="form-label">S&#7889; BHYT</label><input name="healthInsuranceNumber" maxlength="20" class="form-control"></div><div class="col-md-3"><label class="form-label">Nh&#243;m &#273;&#7889;i t&#432;&#7907;ng</label><select name="healthInsuranceGroup" class="form-select"><option value="">Ch&#7885;n nh&#243;m</option><option>H&#7897; gia &#273;&#236;nh</option><option>Ng&#432;&#7901;i ngh&#232;o</option><option>C&#7853;n ngh&#232;o</option><option>Tr&#7867; em d&#432;&#7899;i 6 tu&#7893;i</option><option>H&#7885;c sinh - Sinh vi&#234;n</option><option>Ng&#432;&#7901;i lao &#273;&#7897;ng</option><option>Ng&#432;&#7901;i h&#432;&#7903;ng l&#432;&#417;ng h&#432;u</option><option>Ng&#432;&#7901;i c&#243; c&#244;ng</option><option>Ng&#432;&#7901;i cao tu&#7893;i</option><option>Kh&#225;c</option></select></div><div class="col-md-3"><label class="form-label">Ng&#224;y b&#7855;t &#273;&#7847;u</label><input name="healthInsuranceStartDate" type="date" class="form-control"></div><div class="col-md-3"><label class="form-label">Ng&#224;y h&#7871;t h&#7841;n</label><input name="healthInsuranceEndDate" type="date" class="form-control"></div><div class="col-12"><label class="form-label">N&#417;i &#273;&#259;ng k&#253; KCB ban &#273;&#7847;u</label><input name="healthInsuranceFacility" class="form-control"></div></div></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button><button type="button" class="btn btn-outline-secondary" onclick="showPerson(document.querySelector('#personForm').elements.id.value)">Lịch sử thay đổi</button><button type="submit" class="btn btn-primary">Lưu</button></div></form></div></div>

  <div class="modal fade house-detail-modal" id="houseDetailModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><span class="house-modal-eyebrow">H&#7891; s&#417; nh&#224; &#7903; &amp; c&#244;ng tr&#236;nh</span><h5 id="houseDetailTitle" class="modal-title">Chi ti&#7871;t nh&#224;</h5><p id="houseDetailSubtitle" class="mb-0 text-muted"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="&#272;&#243;ng"></button></div><div id="houseDetailBody" class="modal-body"></div><div class="modal-footer"><button id="houseDetailEditBtn" type="button" class="btn btn-outline-primary"><i class="fa-solid fa-pen"></i> S&#7917;a</button><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">&#272;&#243;ng</button></div></div></div></div>
  <div class="modal fade" id="houseFormModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" id="houseForm" novalidate><div class="modal-header"><h5 class="modal-title">Th&#244;ng tin nh&#224; &#7903; v&#224; c&#244;ng tr&#236;nh</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="&#272;&#243;ng"></button></div><div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="household_id"><input type="hidden" name="structures"><div class="row g-3"><div class="col-12 position-relative"><label class="form-label" for="houseHouseholdAutocomplete">H&#7897; gia &#273;&#236;nh</label><input id="houseHouseholdAutocomplete" class="form-control" autocomplete="off" placeholder="Nh&#7853;p m&#227; h&#7897; ho&#7863;c t&#234;n ch&#7911; h&#7897;..." required><div id="houseHouseholdSuggestions" class="list-group position-absolute w-100 shadow d-none" style="z-index:1060;max-height:260px;overflow:auto"></div><div id="houseHouseholdSelected" class="form-text"></div></div><div class="col-md-4"><label class="form-label">T&#234;n nh&#224;</label><input name="house_name" class="form-control" placeholder="Nh&#224; ch&#237;nh, nh&#224; ph&#7909;..."></div><div class="col-md-4"><label class="form-label" for="houseTypeSelect">Lo&#7841;i nh&#224;</label><select id="houseTypeSelect" name="house_type" class="form-select"></select></div><div class="col-md-4"><label class="form-label" for="houseStructureSelect">K&#7871;t c&#7845;u</label><select id="houseStructureSelect" name="structure_type" class="form-select"></select></div><div class="col-md-3"><label class="form-label">S&#7889; t&#7847;ng</label><input name="floors" type="number" min="0" step="1" class="form-control" value="1"></div><div class="col-md-3"><label class="form-label">Di&#7879;n t&#237;ch &#273;&#7845;t (m&#178;)</label><input name="land_area" type="number" min="0" step="0.01" class="form-control" value="0"></div><div class="col-md-3"><label class="form-label">Di&#7879;n t&#237;ch x&#226;y d&#7921;ng (m&#178;)</label><input name="building_area" type="number" min="0" step="0.01" class="form-control" value="0"></div><div class="col-md-3"><label class="form-label">Di&#7879;n t&#237;ch s&#224;n (m&#178;)</label><input name="floor_area" type="number" min="0" step="0.01" class="form-control" value="0"></div><div class="col-md-3"><label class="form-label">N&#259;m x&#226;y</label><input name="build_year" type="number" min="1800" max="2100" class="form-control"></div><div class="col-md-3"><label class="form-label">N&#259;m s&#7917;a</label><input name="renovated_year" type="number" min="1800" max="2100" class="form-control"></div><div class="col-md-3"><label class="form-label" for="houseConditionSelect">T&#236;nh tr&#7841;ng</label><select id="houseConditionSelect" name="condition" class="form-select"></select></div><div class="col-md-3"><label class="form-label" for="houseSoliditySelect">M&#7913;c &#273;&#7897;</label><select id="houseSoliditySelect" name="solidity" class="form-select"></select></div><div class="col-md-4"><label class="form-label" for="houseUsageSelect">M&#7909;c &#273;&#237;ch</label><select id="houseUsageSelect" name="usage" class="form-select"></select></div><div class="col-md-4"><label class="form-label" for="houseLegalSelect">Ph&#225;p l&#253;</label><select id="houseLegalSelect" name="legal_status" class="form-select"></select></div><div class="col-md-4"><label class="form-label" for="houseFireRiskSelect">Nguy c&#417; PCCC</label><select id="houseFireRiskSelect" name="fire_risk" class="form-select"></select></div><div class="col-md-3"><label class="form-label">S&#7889; &#273;i&#7879;n</label><input name="electric_meter" class="form-control"></div><div class="col-md-3"><label class="form-label">S&#7889; n&#432;&#7899;c</label><input name="water_meter" class="form-control"></div><div class="col-md-2"><div class="form-check form-switch mt-4"><input name="internet" id="houseInternet" class="form-check-input" type="checkbox" value="1"><label class="form-check-label" for="houseInternet">Internet</label></div></div><div class="col-md-2"><div class="form-check form-switch mt-4"><input name="security_camera" id="houseCamera" class="form-check-input" type="checkbox" value="1"><label class="form-check-label" for="houseCamera">Camera</label></div></div><div class="col-md-2"><div class="form-check form-switch mt-4"><input name="fire_extinguisher" id="houseFireExt" class="form-check-input" type="checkbox" value="1"><label class="form-check-label" for="houseFireExt">B&#236;nh PCCC</label></div></div><div class="col-12"><label class="form-label">&#272;&#7883;a ch&#7881;</label><input name="address" class="form-control"></div><div class="col-md-4"><label class="form-label">V&#297; &#273;&#7897;</label><input name="latitude" type="number" step="0.00000001" class="form-control"></div><div class="col-md-4"><label class="form-label">Kinh &#273;&#7897;</label><input name="longitude" type="number" step="0.00000001" class="form-control"></div><div class="col-md-4"><label class="form-label">Sai s&#7889; GPS (m)</label><input name="gps_accuracy" type="number" min="0" step="0.01" class="form-control"></div><div class="col-12"><div class="house-structure-editor"><div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">C&#244;ng tr&#236;nh ph&#7909;</h6><button id="houseAddStructureBtn" type="button" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-plus"></i> Th&#234;m c&#244;ng tr&#236;nh</button></div><div id="houseStructureRows" class="house-structure-rows"></div></div></div><div class="col-md-4"><label class="form-label" for="housePhotoTypeSelect">Lo&#7841;i &#7843;nh</label><select id="housePhotoTypeSelect" class="form-select"></select></div><div class="col-md-8"><label class="form-label" for="housePhotoFiles">Upload &#7843;nh</label><input id="housePhotoFiles" type="file" class="form-control" accept="image/*" multiple></div><div class="col-12"><label class="form-label">Ghi ch&#250;</label><textarea name="notes" class="form-control" rows="3"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">H&#7911;y</button><button type="submit" class="btn btn-primary">L&#432;u</button></div></form></div></div>  <div class="modal fade" id="livestockModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" id="livestockForm" novalidate><div class="modal-header"><h5 class="modal-title">Thông tin vật nuôi</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="household_id"><div class="row g-3"><div class="col-12 position-relative"><label class="form-label" for="livestockHouseholdAutocomplete">Hộ gia đình</label><input id="livestockHouseholdAutocomplete" class="form-control" autocomplete="off" placeholder="Nhập mã hộ hoặc tên chủ hộ..." required><div id="livestockHouseholdSuggestions" class="list-group position-absolute w-100 shadow d-none" style="z-index:1060;max-height:260px;overflow:auto"></div><div id="livestockHouseholdSelected" class="form-text"></div></div><div class="col-md-4"><label class="form-label" for="livestockAnimalTypeSelect">Loại vật nuôi</label><select id="livestockAnimalTypeSelect" name="animal_type" class="form-select" required></select></div><div class="col-md-4"><label class="form-label">Giống</label><input name="breed" class="form-control" placeholder="Ví dụ: Ri, BBB, Landrace"></div><div class="col-md-4"><label class="form-label">Số lượng</label><input name="quantity" type="number" min="0" step="1" class="form-control" value="0" required></div><div class="col-md-4"><div class="form-check form-switch mt-4"><input id="livestockVaccinatedCheck" name="vaccinated" class="form-check-input" type="checkbox" value="1"><label class="form-check-label" for="livestockVaccinatedCheck">Đã tiêm phòng</label></div></div><div class="col-md-4"><label class="form-label">Ngày tiêm</label><input name="vaccine_date" type="date" class="form-control"></div><div class="col-md-4"><label class="form-label" for="livestockDiseaseSelect">Dịch bệnh</label><select id="livestockDiseaseSelect" name="disease_status" class="form-select"></select></div><div class="col-md-6"><label class="form-label">Khu chuồng / khu nuôi</label><input name="barn_area" class="form-control"></div><div class="col-md-6"><label class="form-label" for="livestockStatusSelect">Trạng thái</label><select id="livestockStatusSelect" name="status" class="form-select"></select></div><div class="col-12"><label class="form-label">Ghi chú</label><textarea name="note" class="form-control" rows="3"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-primary">Lưu</button></div></form></div></div>
  <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 id="detailTitle" class="modal-title">Chi tiết</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div id="detailBody" class="modal-body"></div></div></div></div>

  <script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
  <script src="assets/js/i18n.min.js"></script>
  <script src="assets/js/app.utf8.min.js"></script>
  <script src="assets/js/csrf.min.js"></script>
  <script src="assets/js/session.min.js"></script>
  <script src="assets/js/admin.utf8.min.js"></script>
  <script src="assets/js/import.min.js"></script>
  <script src="assets/js/admin-panel.min.js"></script>
  <script src="assets/js/admin-panel-bridge.min.js"></script>
  <script src="assets/js/sprint8.min.js"></script>
  <script src="assets/js/sprint9.min.js"></script>
  <script src="assets/js/sprint10.min.js"></script>
  <script src="assets/js/view-inline-patches.min.js"></script>
  <script src="assets/js/operation-center.min.js"></script>
  <script src="assets/js/system-admin.min.js"></script>
  <script src="assets/js/report.min.js"></script>
  <script src="assets/js/gis-household-location.min.js"></script>
  <script src="assets/js/household-photo-capture.min.js"></script>
  <script src="assets/js/household-photo-camera-fix.min.js"></script>
  <script src="assets/js/household-photo-gps.min.js"></script>
  <script src="assets/js/digital-profile.min.js"></script>
  <script src="assets/js/household-business.min.js"></script>
  <script src="assets/js/livestock.min.js"></script>
  <script src="assets/js/agriculture.min.js"></script>
  <script src="assets/js/houses.min.js"></script>
  <script src="assets/js/module-dashboards.min.js"></script>
</body>
</html>
