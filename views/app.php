<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quản Lý Nhân Khẩu Thôn 09</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        <div class="nav-section"><div class="nav-section-title">Tổng quan</div><button class="nav-link active" data-screen="dashboard" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></button><button class="nav-link" data-screen="operationCenter" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-tower-broadcast"></i><span>Trung tâm điều hành</span></button><button class="nav-link" data-screen="gis" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-map-location-dot"></i><span>Bản đồ địa bàn</span></button></div>
        <div class="nav-section"><div class="nav-section-title">Quản lý dân cư</div><button class="nav-link" data-screen="households" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-house-chimney"></i><span>Quản lý hộ gia đình</span></button><button class="nav-link" data-screen="persons" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-users"></i><span>Quản lý nhân khẩu</span></button><button class="nav-link" data-screen="temporaryResidence" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-location-dot"></i><span>Tạm trú</span></button><button class="nav-link" data-screen="temporaryAbsence" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-person-walking-arrow-right"></i><span>Tạm vắng</span></button><button class="nav-link" data-screen="movements" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-right-left"></i><span>Biến động nhân khẩu</span></button></div>
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
              <div class="gis-search-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="gisSearch" class="form-control" placeholder="Tìm khu vực, mã khu vực..."></div>
              <span id="gisMapStatus" class="gis-status-pill">Đang tải bản đồ...</span>
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
          <div class="table-responsive person-table-wrap"><table class="table person-table align-middle mb-0"><thead><tr><th><input type="checkbox" id="personCheckAll"></th><th>Mã hộ</th><th>Mã nhân khẩu</th><th>Họ và tên</th><th>Quan hệ</th><th>Ngày sinh</th><th>Tuổi</th><th>Giới tính</th><th>CCCD/Số định danh</th><th>Cư trú</th><th>Đảng viên</th><th class="text-end">Thao tác</th></tr></thead><tbody id="personRows"></tbody></table></div>
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
          <div id="reportPreview" class="report-preview table-responsive"><div class="report-empty-state">Ch?n lo?i b?o c?o v? b?m Xem b&#225;o c&#225;o ?? sinh d? li?u.</div></div>
        </div>
      </section>
    </section>
  </div>

  <div class="modal fade" id="householdModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" id="householdForm"><div class="modal-header"><h5 class="modal-title">Thông tin hộ dân</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="row g-3"><div class="col-md-4"><label class="form-label">Mã hộ</label><input name="householdCode" class="form-control" required></div><div class="col-md-4"><label class="form-label">Tên chủ hộ</label><input name="headCitizenName" class="form-control" required></div><div class="col-md-4"><label class="form-label">Số điện thoại</label><input name="phone" class="form-control"></div><div class="col-12"><label class="form-label">Địa chỉ</label><input name="address" class="form-control" required></div><div class="col-md-3 form-check ms-2"><input name="meritoriousFamily" class="form-check-input" type="checkbox" id="meritoriousFamily"><label class="form-check-label" for="meritoriousFamily">Gia đình có công</label></div><div class="col-md-2 form-check"><input name="poorHousehold" class="form-check-input" type="checkbox" id="poorHousehold"><label class="form-check-label" for="poorHousehold">Hộ nghèo</label></div><div class="col-md-2 form-check"><input name="nearPoorHousehold" class="form-check-input" type="checkbox" id="nearPoorHousehold"><label class="form-check-label" for="nearPoorHousehold">Cận nghèo</label></div><div class="col-md-2 form-check"><input name="disabledHousehold" class="form-check-input" type="checkbox" id="disabledHousehold"><label class="form-check-label" for="disabledHousehold">Tàn tật</label></div><div class="col-12"><label class="form-label">Ảnh hộ</label><input name="householdPhoto" type="file" class="form-control" accept="image/*"></div><div class="col-12"><label class="form-label">Ghi chú</label><textarea name="note" class="form-control" rows="2"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button><button type="button" class="btn btn-outline-secondary" onclick="window.print()">In phiếu hộ</button><button type="submit" class="btn btn-primary">Lưu</button></div></form></div></div>

  <div class="modal fade" id="personModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" id="personForm"><div class="modal-header"><h5 class="modal-title">Thông tin nhân khẩu</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="row g-3"><div class="col-md-3"><label class="form-label">Mã hộ</label><input name="householdCode" class="form-control" required></div><div class="col-md-3"><label class="form-label">Mã nhân khẩu</label><input name="citizenCode" class="form-control"></div><div class="col-md-6"><label class="form-label">Họ và tên</label><input name="fullName" class="form-control" required></div><div class="col-md-3"><label class="form-label">Giới tính</label><select name="gender" class="form-select"><option>Nam</option><option>Nữ</option><option>Khác</option></select></div><div class="col-md-3"><label class="form-label">Ngày sinh</label><input name="dateOfBirth" type="date" class="form-control" required></div><div class="col-md-3"><label class="form-label">CCCD</label><input name="identityNumber" class="form-control"></div><div class="col-md-3"><label class="form-label">Số điện thoại</label><input name="phone" class="form-control"></div><div class="col-md-3"><label class="form-label">Quan hệ với chủ hộ</label><select name="relationship" class="form-select" data-options="relationships"></select></div><div class="col-md-3"><label class="form-label">Dân tộc</label><select name="ethnicity" class="form-select" data-options="ethnicities"></select></div><div class="col-md-3"><label class="form-label">Tôn giáo</label><select name="religion" class="form-select" data-options="religions"></select></div><div class="col-md-3"><label class="form-label">Nghề nghiệp</label><select name="occupation" class="form-select" data-options="occupations"></select></div><div class="col-md-3"><label class="form-label">Học vấn</label><select name="educationLevel" class="form-select" data-options="educationLevels"></select></div><div class="col-md-3"><label class="form-label">Hôn nhân</label><select name="maritalStatus" class="form-select" data-options="maritalStatuses"></select></div><div class="col-md-3"><label class="form-label">Thường trú</label><select name="residency_status" class="form-select"><option value="PERMANENT">Thường trú</option><option value="TEMPORARY">Tạm trú</option></select></div><div class="col-md-3"><label class="form-label">Hiện tại</label><select name="presenceStatus" class="form-select"><option value="AT_HOME">Ở nhà</option><option value="AWAY">Đi vắng</option></select></div><div class="col-md-3"><label class="form-label">Trạng thái</label><select name="status" class="form-select"><option value="ALIVE">Còn sống</option><option value="DECEASED">Đã chết</option></select></div><div class="col-md-9"><label class="form-label">Địa chỉ hiện tại</label><input name="currentAddress" class="form-control"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button><button type="button" class="btn btn-outline-secondary" onclick="showPerson(document.querySelector('#personForm').elements.id.value)">Lịch sử thay đổi</button><button type="submit" class="btn btn-primary">Lưu</button></div></form></div></div>

  <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 id="detailTitle" class="modal-title">Chi tiết</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div id="detailBody" class="modal-body"></div></div></div></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
  <script src="assets/js/report.min.js"></script>
  <script src="assets/js/gis-household-location.min.js"></script>
  <script src="assets/js/household-photo-capture.min.js"></script>
  <script src="assets/js/household-photo-camera-fix.min.js"></script>
  <script src="assets/js/household-photo-gps.min.js"></script>
  <script src="assets/js/gis-search.min.js"></script>
  <script src="assets/js/gis-smart.min.js"></script>
  <script src="assets/js/digital-profile.min.js"></script>
</body>
</html>

