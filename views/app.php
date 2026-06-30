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
  <link rel="stylesheet" href="assets/css/app.css?v=20260630-phase2-uiux-1">
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
        <div class="nav-section"><div class="nav-section-title">Tổng quan</div><button class="nav-link active" data-screen="dashboard" onclick="window.switchScreen && window.switchScreen(this.dataset.screen)"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></button></div>
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
        <section class="dashboard-shortcuts" aria-label="Thao tác nhanh">
          <button type="button" data-quick-screen="households" data-quick-action="addHousehold"><i class="fa-solid fa-house-circle-check"></i><span>Thêm hộ</span></button>
          <button type="button" data-quick-screen="persons" data-quick-action="addPerson"><i class="fa-solid fa-user-plus"></i><span>Thêm nhân khẩu</span></button>
          <button type="button" data-quick-screen="temporaryResidence"><i class="fa-solid fa-location-dot"></i><span>Thêm tạm trú</span></button>
          <button type="button" data-quick-screen="temporaryAbsence"><i class="fa-solid fa-person-walking-arrow-right"></i><span>Thêm tạm vắng</span></button>
          <button type="button" data-quick-screen="movements"><i class="fa-solid fa-right-left"></i><span>Biến động</span></button>
          <button type="button" data-quick-screen="reports"><i class="fa-solid fa-chart-pie"></i><span>Báo cáo</span></button>
        </section>
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
        </section>
        <section class="dashboard-activity-card content-card">
          <div class="dashboard-activity-head"><h3>Hoạt động gần đây</h3><span>Từ nhật ký hệ thống</span></div>
          <div id="dashboardRecentActivity" class="dashboard-activity-list">
            <div class="dashboard-activity-item"><i class="fa-solid fa-user-plus"></i><div><strong>Thêm nhân khẩu</strong><span>Cập nhật sau khi nhật ký hệ thống ghi nhận thao tác.</span></div><time>Gần đây</time></div>
            <div class="dashboard-activity-item"><i class="fa-solid fa-house-chimney"></i><div><strong>Cập nhật hộ</strong><span>Theo dõi nhanh thay đổi dữ liệu hộ gia đình.</span></div><time>Gần đây</time></div>
            <div class="dashboard-activity-item"><i class="fa-solid fa-file-export"></i><div><strong>Xuất báo cáo</strong><span>Các thao tác xuất/in báo cáo sẽ hiển thị tại đây.</span></div><time>Gần đây</time></div>
          </div>
        </section>
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
          <div class="module-list-head"><div><h3>Danh sách hộ gia đình</h3><span id="householdTotalCount">Tổng số: 0 hộ</span></div><button id="householdBulkDeleteBtn" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-trash"></i> Xóa đã chọn</button></div>
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
            <select id="personPageSize" class="form-select person-page-size"><option>20</option><option>50</option><option>100</option></select>
          </div>
          <div class="table-responsive person-table-wrap"><table class="table person-table align-middle mb-0"><thead><tr><th>Mã hộ</th><th>Mã nhân khẩu</th><th>Họ và tên</th><th>Ngày sinh</th><th>Giới tính</th><th>CCCD/Số định danh</th><th>Cư trú</th><th>Đảng viên</th><th class="text-end">Thao tác</th></tr></thead><tbody id="personRows"></tbody></table></div>
          <div id="personPager" class="pager person-pager"></div>
        </div>
      </section>

      <section id="reportsScreen" class="screen report-screen">

        <form id="reportForm" class="content-card report-filter-card">
          <div class="report-filter-grid">
            <div class="report-field report-type-field">
              <label class="form-label">Loại báo cáo</label>
              <select name="type" class="form-select" id="reportTypeSelect">
                <option value="summary">Báo cáo tổng hợp</option>
                <option value="population">Báo cáo nhân khẩu</option>
                <option value="household">Báo cáo hộ gia đình</option>
                <option value="temporary_residence">Báo cáo tạm trú</option>
                <option value="temporary_absence">Báo cáo tạm vắng</option>
                <option value="migration">Báo cáo biến động</option>
                <option value="party_member">Báo cáo Đảng viên</option>
                <option value="meritorious_person">Báo cáo người có công</option>
                <option value="disabled_person">Báo cáo người khuyết tật</option>
                <option value="age">Báo cáo theo độ tuổi</option>
                <option value="gender">Báo cáo theo giới tính</option>
              </select>
            </div>
            <div class="report-field report-date-field" data-report-date-field>
              <label class="form-label">Từ ngày</label>
              <input name="dateFrom" type="date" class="form-control">
            </div>
            <div class="report-field report-date-field" data-report-date-field>
              <label class="form-label">Đến ngày</label>
              <input name="dateTo" type="date" class="form-control">
            </div>
            <button class="btn report-view-btn" type="submit"><i class="fa-solid fa-chart-column"></i> Xem báo cáo</button>
          </div>
        </form>

        <div class="content-card report-result-card">
          <div class="report-result-head">
            <div>
              <h3 id="reportTitle">Báo cáo</h3>
              <span id="reportCount">Chưa sinh báo cáo</span>
            </div>
            <div id="reportActions" class="report-actions d-none">
              <button id="reportPrintBtn" class="btn report-action-btn" type="button"><i class="fa-solid fa-print"></i> In</button>
              <button id="reportExcelBtn" class="btn report-action-btn report-excel-btn" type="button"><i class="fa-solid fa-file-excel"></i> Xuất Excel</button>
              <button id="reportPdfBtn" class="btn report-action-btn report-pdf-btn" type="button"><i class="fa-solid fa-file-pdf"></i> Xuất PDF</button>
            </div>
          </div>
          <div id="reportPreview" class="report-preview table-responsive">
            <div class="report-empty-state">Chọn loại báo cáo và bấm Xem báo cáo để sinh dữ liệu.</div>
          </div>
        </div>
      </section>
    </section>
  </div>

  <div class="modal fade" id="householdModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" id="householdForm"><div class="modal-header"><h5 class="modal-title">Thông tin hộ dân</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="row g-3"><div class="col-md-4"><label class="form-label">Mã hộ</label><input name="householdCode" class="form-control" required></div><div class="col-md-4"><label class="form-label">Tên chủ hộ</label><input name="headCitizenName" class="form-control" required></div><div class="col-md-4"><label class="form-label">Số điện thoại</label><input name="phone" class="form-control"></div><div class="col-12"><label class="form-label">Địa chỉ</label><input name="address" class="form-control" required></div><div class="col-md-3 form-check ms-2"><input name="meritoriousFamily" class="form-check-input" type="checkbox" id="meritoriousFamily"><label class="form-check-label" for="meritoriousFamily">Gia đình có công</label></div><div class="col-md-2 form-check"><input name="poorHousehold" class="form-check-input" type="checkbox" id="poorHousehold"><label class="form-check-label" for="poorHousehold">Hộ nghèo</label></div><div class="col-md-2 form-check"><input name="nearPoorHousehold" class="form-check-input" type="checkbox" id="nearPoorHousehold"><label class="form-check-label" for="nearPoorHousehold">Cận nghèo</label></div><div class="col-md-2 form-check"><input name="disabledHousehold" class="form-check-input" type="checkbox" id="disabledHousehold"><label class="form-check-label" for="disabledHousehold">Tàn tật</label></div><div class="col-12"><label class="form-label">Ảnh hộ</label><input name="householdPhoto" type="file" class="form-control" accept="image/*"></div><div class="col-12"><label class="form-label">Ghi chú</label><textarea name="note" class="form-control" rows="2"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button><button type="button" class="btn btn-outline-secondary" onclick="window.print()">In phiếu hộ</button><button type="submit" class="btn btn-primary">Lưu</button></div></form></div></div>

  <div class="modal fade" id="personModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" id="personForm"><div class="modal-header"><h5 class="modal-title">Thông tin nhân khẩu</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="row g-3"><div class="col-md-3"><label class="form-label">Mã hộ</label><input name="householdCode" class="form-control" required></div><div class="col-md-3"><label class="form-label">Mã nhân khẩu</label><input name="citizenCode" class="form-control"></div><div class="col-md-6"><label class="form-label">Họ và tên</label><input name="fullName" class="form-control" required></div><div class="col-md-3"><label class="form-label">Giới tính</label><select name="gender" class="form-select"><option>Nam</option><option>Nữ</option><option>Khác</option></select></div><div class="col-md-3"><label class="form-label">Ngày sinh</label><input name="dateOfBirth" type="date" class="form-control" required></div><div class="col-md-3"><label class="form-label">CCCD</label><input name="identityNumber" class="form-control"></div><div class="col-md-3"><label class="form-label">Số điện thoại</label><input name="phone" class="form-control"></div><div class="col-md-3"><label class="form-label">Quan hệ với chủ hộ</label><select name="relationship" class="form-select" data-options="relationships"></select></div><div class="col-md-3"><label class="form-label">Dân tộc</label><select name="ethnicity" class="form-select" data-options="ethnicities"></select></div><div class="col-md-3"><label class="form-label">Tôn giáo</label><select name="religion" class="form-select" data-options="religions"></select></div><div class="col-md-3"><label class="form-label">Nghề nghiệp</label><select name="occupation" class="form-select" data-options="occupations"></select></div><div class="col-md-3"><label class="form-label">Học vấn</label><select name="educationLevel" class="form-select" data-options="educationLevels"></select></div><div class="col-md-3"><label class="form-label">Hôn nhân</label><select name="maritalStatus" class="form-select" data-options="maritalStatuses"></select></div><div class="col-md-3"><label class="form-label">Thường trú</label><select name="residency_status" class="form-select"><option value="PERMANENT">Thường trú</option><option value="TEMPORARY">Tạm trú</option></select></div><div class="col-md-3"><label class="form-label">Hiện tại</label><select name="presenceStatus" class="form-select"><option value="AT_HOME">Ở nhà</option><option value="AWAY">Đi vắng</option></select></div><div class="col-md-3"><label class="form-label">Trạng thái</label><select name="status" class="form-select"><option value="ALIVE">Còn sống</option><option value="DECEASED">Đã chết</option></select></div><div class="col-md-9"><label class="form-label">Địa chỉ hiện tại</label><input name="currentAddress" class="form-control"></div><div class="col-md-6"><label class="form-label">Ảnh nhân khẩu</label><input name="personPhoto" type="file" class="form-control" accept="image/*"></div><div class="col-md-6"><label class="form-label">Giấy tờ đính kèm</label><input name="personDocument" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button><button type="button" class="btn btn-outline-secondary" onclick="showPerson(document.querySelector('#personForm').elements.id.value)">Lịch sử thay đổi</button><button type="submit" class="btn btn-primary">Lưu</button></div></form></div></div>

  <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 id="detailTitle" class="modal-title">Chi tiết</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div id="detailBody" class="modal-body"></div></div></div></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
  <script src="assets/js/app.js?v=20260630-final-nav-inline-3"></script>
  <script src="assets/js/csrf.js?v=20260629-temporary-filter-3"></script>
  <script src="assets/js/session.js?v=20260629-temporary-filter-3"></script>
  <script src="assets/js/admin.js?v=20260629-temporary-filter-3"></script>
  <script src="assets/js/import.js?v=20260629-two-import-buttons-1"></script>
  <script src="assets/js/admin-panel.js?v=20260630-phase2-uiux-1"></script>
  <script src="assets/js/admin-panel-bridge.js?v=20260630-final-bridge-title-1"></script>
  <script src="assets/js/sprint8.js?v=20260629-two-import-buttons-1"></script>
  <script src="assets/js/sprint9.js?v=20260629-two-import-buttons-1"></script>
  <script src="assets/js/sprint10.js?v=20260629-report-disable-old-1"></script>

<script id="thon09-report-inline-stable">
(function(){
  var currentReport = null;
  var timeOptionalTypes = new Set(['summary','population','household','party_member','meritorious_person','disabled_person','age','gender']);
  function q(s){return document.querySelector(s);}
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
  function token(){return localStorage.getItem('thon09_token')||(window.App&&window.App.token)||'';}
  function buildParams(){var form=q('#reportForm');var params=new URLSearchParams();if(!form)return params;var data=new FormData(form);var type=data.get('type')||'summary';params.set('type',type);var dateFrom=String(data.get('dateFrom')||'').trim();var dateTo=String(data.get('dateTo')||'').trim();if(dateFrom)params.set('dateFrom',dateFrom);if(dateTo)params.set('dateTo',dateTo);return params;}
  function reportType(){return buildParams().get('type')||'summary';}
  function apiUrl(path){var params=buildParams();return path+(params.toString()?'?'+params.toString():'');}
  async function fetchJson(path){var tk=token();if(!tk)throw new Error('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');var res=await fetch(path,{headers:{Accept:'application/json',Authorization:'Bearer '+tk},cache:'no-store'});var json=await res.json().catch(function(){return null;});if(!res.ok||!json||!json.ok)throw new Error((json&&json.error&&json.error.message)||'Không tải được báo cáo.');return json.data||{};}
  function setTitle(text){var el=q('#reportTitle');if(el)el.textContent=text||'Báo cáo';}
  function setCount(report){var el=q('#reportCount');if(!el)return;var rows=Number(report&&report.totalRows!=null?report.totalRows:(report&&report.rows?report.rows.length:0));el.textContent='Tổng số: '+rows.toLocaleString('vi-VN')+' dòng';}
  function setActions(show){var el=q('#reportActions');if(el)el.classList.toggle('d-none',!show);}
  function table(report){var headers=report.headers||[];var rows=report.rows||[];if(!headers.length)return '<div class="report-empty-state">Báo cáo chưa có cấu trúc hiển thị.</div>';var head=headers.map(function(h){return '<th>'+esc(h)+'</th>';}).join('');var body=rows.length?rows.map(function(row){return '<tr>'+row.map(function(cell){return '<td>'+esc(cell)+'</td>';}).join('')+'</tr>';}).join(''):'<tr><td colspan="'+headers.length+'" class="text-center text-muted py-4">Không có dữ liệu</td></tr>';return '<table class="table report-table align-middle mb-0"><thead><tr>'+head+'</tr></thead><tbody>'+body+'</tbody></table>';}
  function showMessage(text,type){var box=q('#reportPreview');if(box)box.innerHTML='<div class="alert alert-'+(type||'info')+' mb-0">'+esc(text)+'</div>';}
  async function viewReport(){setActions(false);setTitle('Báo cáo');var count=q('#reportCount');if(count)count.textContent='Đang tải dữ liệu...';showMessage('Đang sinh báo cáo...','info');try{var report=await fetchJson(apiUrl('/api/reports/summary'));currentReport=report;setTitle(report.title||'Báo cáo');setCount(report);var preview=q('#reportPreview');if(preview)preview.innerHTML=table(report);setActions(true);return report;}catch(e){currentReport=null;setTitle('Báo cáo');if(count)count.textContent='Không sinh được báo cáo';showMessage(e.message||'Không sinh được báo cáo.','danger');throw e;}}
  async function ensureReport(){return currentReport||viewReport();}
  function download(kind){var tk=token();if(!tk){showMessage('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.','danger');return;}var url=apiUrl(kind==='excel'?'/api/reports/export-excel':'/api/reports/export-pdf');fetch(url,{headers:{Authorization:'Bearer '+tk},cache:'no-store'}).then(function(res){if(!res.ok)throw new Error('Không xuất được file.');return res.blob().then(function(blob){var name=(currentReport&&currentReport.title?currentReport.title:'bao_cao').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/đ/g,'d').replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'')||'bao_cao';var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=name+(kind==='excel'?'.xls':'.pdf');document.body.appendChild(a);a.click();URL.revokeObjectURL(a.href);a.remove();});}).catch(function(e){showMessage(e.message||'Không xuất được file.','danger');});}
  async function printReport(){try{var report=await ensureReport();var printData=await fetchJson(apiUrl('/api/reports/print')).catch(function(){return report;});var w=window.open('','_blank');if(!w){showMessage('Trình duyệt đang chặn cửa sổ in. Vui lòng cho phép popup.','warning');return;}w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>'+esc(printData.title||report.title||'Báo cáo')+'</title><style>body{font-family:Arial,sans-serif;color:#111827;margin:24px}h1{text-align:center;font-size:20px;margin:0 0 8px}p{text-align:center;margin:0 0 18px;color:#555}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #777;padding:6px;text-align:left;vertical-align:top}th{background:#eef2f7;font-weight:700}.sign{margin-top:36px;display:flex;justify-content:flex-end;text-align:center}</style></head><body><h1>'+esc(printData.title||report.title||'Báo cáo')+'</h1><p>Loại báo cáo: '+esc(reportType())+' - Tổng số: '+Number(printData.totalRows||0).toLocaleString('vi-VN')+' dòng</p>'+table(printData)+'<div class="sign"><div>Người lập báo cáo<br><br><br>........................</div></div><script>window.onload=function(){window.print();};<\\/script></body></html>');w.document.close();}catch(e){showMessage(e.message||'Không in được báo cáo.','danger');}}

  function lockReportTypes(){var select=q('#reportTypeSelect');if(!select)return;var value=select.value||'summary';var html='<option value="summary">Báo cáo tổng hợp</option><option value="population">Báo cáo nhân khẩu</option><option value="household">Báo cáo hộ gia đình</option><option value="temporary_residence">Báo cáo tạm trú</option><option value="temporary_absence">Báo cáo tạm vắng</option><option value="migration">Báo cáo biến động</option><option value="party_member">Báo cáo Đảng viên</option><option value="meritorious_person">Báo cáo người có công</option><option value="disabled_person">Báo cáo người khuyết tật</option><option value="age">Báo cáo theo độ tuổi</option><option value="gender">Báo cáo theo giới tính</option>';if(select.innerHTML.replace(/\s+/g,' ').trim()!==html.replace(/\s+/g,' ').trim())select.innerHTML=html;if(!Array.prototype.some.call(select.options,function(o){return o.value===value;}))value='summary';select.value=value;}
  function updateDateVisibility(){lockReportTypes();var type=reportType();var hide=timeOptionalTypes.has(type);document.querySelectorAll('[data-report-date-field]').forEach(function(el){el.classList.toggle('report-date-muted',hide);});}
  function bind(){lockReportTypes();if(window.__thon09ReportReadyV2)return;window.__thon09ReportReadyV2=true;var form=q('#reportForm');if(form){form.addEventListener('submit',function(e){e.preventDefault();viewReport();});form.addEventListener('change',function(e){if(e.target&&e.target.name==='type'){currentReport=null;setActions(false);updateDateVisibility();}});}var print=q('#reportPrintBtn');if(print)print.addEventListener('click',function(e){e.preventDefault();printReport();});var excel=q('#reportExcelBtn');if(excel)excel.addEventListener('click',function(e){e.preventDefault();download('excel');});var pdf=q('#reportPdfBtn');if(pdf)pdf.addEventListener('click',function(e){e.preventDefault();download('pdf');});updateDateVisibility();}
  window.thon09ViewReport=function(){bind();return viewReport();};if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',bind);else bind();setTimeout(lockReportTypes, 0);
})();
</script>
<script id="thon09-person-advanced-filter-fix">
(function(){
  var FLAG_FIELDS=['party_member','youth_union_member','women_union_member','farmers_union_member','veterans_union_member','elderly_union_member','meritorious_person','disabled_person','social_assistance'];
  function qs(s,r){return (r||document).querySelector(s);} function qsa(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s));}
  function fillSelects(){qsa('[data-dictionary]').forEach(function(el){var list=(App.dictionaries&&App.dictionaries[el.dataset.dictionary])||[];var current=el.value;el.innerHTML='<option value="">Tất cả</option>'+list.map(function(item){return '<option value="'+escapeHtml(item)+'">'+escapeHtml(item)+'</option>';}).join('');el.value=current||'';});}
  function applyResidence(p,value){if(value==='PERMANENT'||value==='TEMPORARY')p.set('residencyStatus',value);else if(value==='AWAY')p.set('presenceStatus','AWAY');}
  function applyAgeGroup(p,value){if(value==='0_5'){p.set('ageFrom','0');p.set('ageTo','5');}else if(value==='6_14'){p.set('ageFrom','6');p.set('ageTo','14');}else if(value==='15_17'){p.set('ageFrom','15');p.set('ageTo','17');}else if(value==='18_59'){p.set('ageFrom','18');p.set('ageTo','59');}else if(value==='60_plus'){p.set('ageFrom','60');}}
  function appendFilter(p,key,value){if(!value)return;if(key==='residenceCombined')applyResidence(p,value);else if(key==='ageGroup')applyAgeGroup(p,value);else p.set(key,value);}
  function personParams(includeSearch){var p=new URLSearchParams({page:App.persons.page||1,pageSize:App.persons.pageSize||20});if(includeSearch){var search=(qs('#personSearch')&&qs('#personSearch').value||App.persons.search||'').trim();if(search)p.set('search',search);}qsa('[data-person-filter]').forEach(function(el){var key=el.dataset.personFilter,val=String(el.value||'').trim();App.persons[key]=val;appendFilter(p,key,val);});return p;}
  function activeFilterParams(){var p=personParams(false);p.delete('page');p.delete('pageSize');return Object.fromEntries(p.entries());}
  function matchesQuickSearch(row,searchText){return [row.full_name,row.citizen_code,row.identity_number].some(function(value){return normalizeSearchText(value).includes(searchText);});}
  window.loadPersons=async function loadPersonsAdvanced(){try{var searchText=normalizeSearchText((qs('#personSearch')&&qs('#personSearch').value||App.persons.search||'').trim());App.persons.search=(qs('#personSearch')&&qs('#personSearch').value||'').trim();var items=[],total=0;if(searchText){var allItems=await fetchAllPaged('/api/persons',activeFilterParams());var filtered=allItems.filter(function(row){return matchesQuickSearch(row,searchText);});total=filtered.length;items=filtered.slice((App.persons.page-1)*App.persons.pageSize,(App.persons.page-1)*App.persons.pageSize+App.persons.pageSize);}else{var data=await api('/api/persons?'+personParams(false).toString());items=data.items||[];total=data.total||0;}var grouped=items.reduce(function(acc,row){var code=row.household_code||'Chưa có hộ';(acc[code]||(acc[code]=[])).push(row);return acc;},{});var totalEl=qs('#personTotalCount');if(totalEl)totalEl.innerHTML='Tổng số: <strong>'+number(total)+'</strong> nhân khẩu';qs('#personRows').innerHTML=items.map(personRow).join('')||'<tr><td colspan="9" class="text-center text-muted py-4">Không có dữ liệu</td></tr>';renderPager('#personPager',{total:total,page:App.persons.page,pageSize:App.persons.pageSize},function(page){App.persons.page=page;window.loadPersons();});}catch(error){showToast('Không tải được danh sách nhân khẩu: '+error.message,'danger');}};
  function bind(){fillSelects();if(window.__thon09PersonAdvancedBound)return;window.__thon09PersonAdvancedBound=true;qsa('[data-person-filter]').forEach(function(el){el.addEventListener('change',function(){App.persons.page=1;window.loadPersons();});el.addEventListener('input',debounce(function(){App.persons.page=1;window.loadPersons();},350));});var search=qs('#personSearch');if(search)search.addEventListener('input',debounce(function(){App.persons.page=1;window.loadPersons();},350));var pageSize=qs('#personPageSize');if(pageSize)pageSize.addEventListener('change',function(){App.persons.pageSize=Number(this.value||20);App.persons.page=1;window.loadPersons();});var toggle=qs('#personAdvancedToggle'),panel=qs('#personAdvancedFilters');function setAdvancedFilterOpen(open){if(!toggle||!panel)return;panel.classList.toggle('d-none',!open);toggle.setAttribute('aria-expanded',open?'true':'false');toggle.innerHTML='<i class="fa-solid fa-sliders"></i> '+(open?'Ẩn bộ lọc nâng cao':'Bộ lọc nâng cao');}if(toggle&&panel)toggle.addEventListener('click',function(){setAdvancedFilterOpen(panel.classList.contains('d-none'));});var apply=qs('#personAdvancedApply');if(apply)apply.addEventListener('click',function(){setAdvancedFilterOpen(false);App.persons.page=1;window.loadPersons();});var clearAdvanced=qs('#personAdvancedClear');if(clearAdvanced)clearAdvanced.addEventListener('click',function(){qsa('#personAdvancedFilters [data-person-filter]').forEach(function(el){el.value='';App.persons[el.dataset.personFilter]='';});App.persons.page=1;window.loadPersons();});var reset=qs('#personFilterReset');if(reset)reset.addEventListener('click',function(){if(search)search.value='';qsa('[data-person-filter]').forEach(function(el){el.value='';App.persons[el.dataset.personFilter]='';});App.persons.search='';App.persons.page=1;setAdvancedFilterOpen(false);window.loadPersons();});}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',bind);else bind();
})();
</script>
<script id="thon09-header-duplicate-guard">
(function(){
  var labels = { dashboard:'Dashboard', households:'Quản lý hộ gia đình', persons:'Quản lý nhân khẩu', temporaryResidence:'Tạm trú', temporaryAbsence:'Tạm vắng', movements:'Biến động nhân khẩu', reports:'Báo cáo thống kê', import:'Import dữ liệu', export:'Export Excel', exportExcel:'Export Excel', printForms:'In biểu mẫu', users:'Quản lý tài khoản', permissions:'Phân quyền', logs:'Nhật ký hệ thống', settings:'Cấu hình hệ thống', appearance:'Cấu hình giao diện', backups:'Sao lưu dữ liệu', restore:'Khôi phục dữ liệu' };
  function activeScreen(){
    var active = document.querySelector('.screen.active');
    if (active && active.id) return active.id.replace(/Screen$/, '');
    return (window.App && window.App.screen) || localStorage.getItem('thon09_screen') || 'dashboard';
  }
  function cleanHeader(){
    var screen = activeScreen();
    var label = labels[screen] || 'Dashboard';
    var title = document.querySelector('#screenTitle');
    var crumb = document.querySelector('#breadcrumbTrail');
    if (title) title.textContent = label;
    if (crumb) crumb.textContent = 'Trang chủ / ' + label;
    document.querySelectorAll('.topbar-title-block small:not(#breadcrumbTrail), .topbar-title-block .text-muted:not(#breadcrumbTrail), .topbar > div:first-of-type small:not(#breadcrumbTrail), .topbar > div:first-of-type .text-muted:not(#breadcrumbTrail)').forEach(function(el){ el.remove(); });
    document.querySelectorAll('.dashboard-hero-row, .module-page-head > div, .person-page-head > div, .report-page-head, .screen > .admin-heading > div').forEach(function(el){ el.remove(); });
  }
  window.thon09CleanHeader = cleanHeader;
  document.addEventListener('DOMContentLoaded', cleanHeader);
  document.addEventListener('click', function(e){ if (e.target.closest('[data-screen]')) setTimeout(cleanHeader, 80); }, true);
  setTimeout(cleanHeader, 120); setTimeout(cleanHeader, 500);
})();
</script>

<script id="thon09-final-navigation-repair">
(function () {
  var labels = {
    dashboard: 'Dashboard', households: 'Quản lý hộ gia đình', persons: 'Quản lý nhân khẩu', reports: 'Báo cáo thống kê',
    temporaryResidence: 'Tạm trú', temporaryAbsence: 'Tạm vắng', movements: 'Biến động nhân khẩu', import: 'Import dữ liệu',
    export: 'Xuất Excel', exportExcel: 'Xuất Excel', printForms: 'In biểu mẫu', users: 'Quản lý tài khoản', permissions: 'Phân quyền',
    logs: 'Nhật ký hệ thống', backups: 'Sao lưu dữ liệu', restore: 'Khôi phục dữ liệu', settings: 'Cấu hình hệ thống', appearance: 'Cấu hình giao diện'
  };
  window.switchScreen = function (screen) {
    var requested = screen;
    if (screen === 'export') screen = 'exportExcel';
    var target = document.getElementById(screen + 'Screen');
    if (!target) { screen = 'dashboard'; target = document.getElementById('dashboardScreen'); }
    if (!target) return;
    document.querySelectorAll('.screen').forEach(function (el) { el.classList.remove('active'); });
    target.classList.add('active');
    document.querySelectorAll('.sidebar .nav-link').forEach(function (btn) {
      btn.classList.toggle('active', btn.dataset.screen === screen || btn.dataset.screen === requested);
    });
    var label = labels[screen] || labels[requested] || 'Dashboard';
    var title = document.getElementById('screenTitle');
    if (title) title.textContent = label;
    var breadcrumb = document.getElementById('breadcrumbTrail');
    if (breadcrumb) breadcrumb.textContent = 'Trang chủ / ' + label;
    try { localStorage.setItem('thon09_screen', screen); } catch (error) {}
  };
  document.addEventListener('click', function (event) {
    var button = event.target.closest && event.target.closest('.sidebar .nav-link[data-screen]');
    if (!button || button.classList.contains('gov-logout')) return;
    event.preventDefault();
    window.switchScreen(button.dataset.screen);
  }, true);
})();
</script>
</body>
</html>

