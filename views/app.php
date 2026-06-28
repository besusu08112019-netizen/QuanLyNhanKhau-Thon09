<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quản Lý Nhân Khẩu Thôn 09</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <div id="toastHost" class="toast-container position-fixed top-0 end-0 p-3"></div>

  <main id="loginView" class="login-view">
    <section class="login-panel shadow-lg">
      <div class="text-center mb-4">
        <div class="state-mark mx-auto mb-3">UB</div>
        <p class="text-uppercase small fw-semibold text-primary mb-1">Ủy ban nhân dân xã Hồng Phong</p>
        <h1 class="h4 mb-1">Quản Lý Nhân Khẩu Thôn 09</h1>
        <p class="text-muted mb-0">Đăng nhập hệ thống quản lý hành chính</p>
      </div>
      <form id="loginForm" novalidate>
        <div class="mb-3">
          <label class="form-label" for="loginEmail">Tài khoản</label>
          <input id="loginEmail" name="email" type="email" class="form-control form-control-lg" autocomplete="username" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="loginPassword">Mật khẩu</label>
          <input id="loginPassword" name="password" type="password" class="form-control form-control-lg" autocomplete="current-password" required minlength="8">
        </div>
        <button class="btn btn-primary btn-lg w-100" type="submit">Đăng nhập</button>
      </form>
    </section>
  </main>

  <div id="appView" class="app-shell d-none">
    <aside class="sidebar">
      <div class="sidebar-brand">
        <span class="state-mark small-mark">09</span>
        <div>
          <strong>Thôn 09</strong>
          <small>xã Hồng Phong</small>
        </div>
      </div>
      <nav class="nav flex-column gap-1">
        <button class="nav-link active" data-screen="dashboard">Tổng quan</button>
        <button class="nav-link" data-screen="households">Hộ dân</button>
        <button class="nav-link" data-screen="persons">Nhân khẩu</button>
        <button class="nav-link" data-screen="reports">Báo cáo</button>
      </nav>
    </aside>

    <section class="main-area">
      <header class="topbar">
        <button id="sidebarToggle" class="btn btn-outline-secondary btn-sm d-lg-none" type="button">Menu</button>
        <div>
          <h2 id="screenTitle" class="h5 mb-0">Tổng quan</h2>
          <small class="text-muted">Quản Lý Nhân Khẩu Thôn 09 xã Hồng Phong</small>
        </div>
        <div class="ms-auto text-end">
          <div id="currentUser" class="small text-muted"></div>
          <button id="logoutBtn" class="btn btn-link btn-sm text-decoration-none px-0">Đăng xuất</button>
        </div>
      </header>

      <div id="loadingBar" class="progress rounded-0 d-none" style="height:3px"><div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div></div>

      <section id="dashboardScreen" class="screen active">
        <div class="row g-3" id="dashboardCards"></div>
        <div class="row g-3 mt-1">
          <div class="col-lg-4"><div class="content-card"><h3 class="section-title">Giới tính</h3><div id="genderChart" class="chart-list"></div></div></div>
          <div class="col-lg-4"><div class="content-card"><h3 class="section-title">Độ tuổi</h3><div id="ageChart" class="chart-list"></div></div></div>
          <div class="col-lg-4"><div class="content-card"><h3 class="section-title">Hộ dân</h3><div id="householdChart" class="chart-list"></div></div></div>
        </div>
      </section>

      <section id="householdsScreen" class="screen">
        <div class="toolbar">
          <input id="householdSearch" class="form-control" placeholder="Tìm mã hộ, chủ hộ, địa chỉ, điện thoại">
          <select id="householdPageSize" class="form-select w-auto"><option>20</option><option>50</option><option>100</option></select>
          <button id="householdAddBtn" class="btn btn-primary">Thêm hộ dân</button>
          <button id="householdBulkDeleteBtn" class="btn btn-outline-danger">Xóa đã chọn</button>
        </div>
        <div class="content-card table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th><input type="checkbox" id="householdCheckAll"></th><th>Mã hộ</th><th>Chủ hộ</th><th>Địa chỉ</th><th>Ở nhà</th><th>Đi vắng</th><th>Diện hộ</th><th></th></tr></thead>
            <tbody id="householdRows"></tbody>
          </table>
        </div>
        <div id="householdPager" class="pager"></div>
      </section>

      <section id="personsScreen" class="screen">
        <div class="toolbar">
          <input id="personSearch" class="form-control" placeholder="Tìm họ tên, mã nhân khẩu, CCCD, điện thoại, mã hộ">
          <input id="personHouseholdFilter" class="form-control" placeholder="Mã hộ" style="max-width:180px">
          <select id="personPageSize" class="form-select w-auto"><option>20</option><option>50</option><option>100</option></select>
          <button id="personAddBtn" class="btn btn-primary">Thêm nhân khẩu</button>
          <button id="personBulkDeleteBtn" class="btn btn-outline-danger">Xóa đã chọn</button>
        </div>
        <div class="content-card table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th><input type="checkbox" id="personCheckAll"></th><th>Mã hộ</th><th>Mã nhân khẩu</th><th>Họ tên</th><th>Ngày sinh</th><th>CCCD</th><th>Thường trú</th><th>Hiện tại</th><th></th></tr></thead>
            <tbody id="personRows"></tbody>
          </table>
        </div>
        <div id="personPager" class="pager"></div>
      </section>

      <section id="reportsScreen" class="screen">
        <div class="content-card">
          <h3 class="section-title">Báo cáo</h3>
          <p class="text-muted mb-0">Báo cáo, Excel, PDF và in phiếu sẽ được chuyển trong các sprint tiếp theo.</p>
        </div>
      </section>
    </section>
  </div>

  <div class="modal fade" id="householdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" id="householdForm">
      <div class="modal-header"><h5 class="modal-title">Thông tin hộ dân</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
      <div class="modal-body"><input type="hidden" name="id"><div class="row g-3">
        <div class="col-md-4"><label class="form-label">Mã hộ</label><input name="householdCode" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Tên chủ hộ</label><input name="headCitizenName" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Số điện thoại</label><input name="phone" class="form-control"></div>
        <div class="col-12"><label class="form-label">Địa chỉ</label><input name="address" class="form-control" required></div>
        <div class="col-md-3 form-check ms-2"><input name="meritoriousFamily" class="form-check-input" type="checkbox" id="meritoriousFamily"><label class="form-check-label" for="meritoriousFamily">Gia đình có công</label></div>
        <div class="col-md-2 form-check"><input name="poorHousehold" class="form-check-input" type="checkbox" id="poorHousehold"><label class="form-check-label" for="poorHousehold">Hộ nghèo</label></div>
        <div class="col-md-2 form-check"><input name="nearPoorHousehold" class="form-check-input" type="checkbox" id="nearPoorHousehold"><label class="form-check-label" for="nearPoorHousehold">Cận nghèo</label></div>
        <div class="col-md-2 form-check"><input name="disabledHousehold" class="form-check-input" type="checkbox" id="disabledHousehold"><label class="form-check-label" for="disabledHousehold">Tàn tật</label></div>
        <div class="col-12"><label class="form-label">Ghi chú</label><textarea name="note" class="form-control" rows="2"></textarea></div>
      </div></div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-primary">Lưu</button></div>
    </form></div>
  </div>

  <div class="modal fade" id="personModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" id="personForm">
      <div class="modal-header"><h5 class="modal-title">Thông tin nhân khẩu</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
      <div class="modal-body"><input type="hidden" name="id"><div class="row g-3">
        <div class="col-md-3"><label class="form-label">Mã hộ</label><input name="householdCode" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Mã nhân khẩu</label><input name="citizenCode" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Họ và tên</label><input name="fullName" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Giới tính</label><select name="gender" class="form-select"><option>Nam</option><option>Nữ</option><option>Khác</option></select></div>
        <div class="col-md-3"><label class="form-label">Ngày sinh</label><input name="dateOfBirth" type="date" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">CCCD</label><input name="identityNumber" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Số điện thoại</label><input name="phone" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Quan hệ với chủ hộ</label><select name="relationship" class="form-select" data-options="relationships"></select></div>
        <div class="col-md-3"><label class="form-label">Dân tộc</label><select name="ethnicity" class="form-select" data-options="ethnicities"></select></div>
        <div class="col-md-3"><label class="form-label">Tôn giáo</label><select name="religion" class="form-select" data-options="religions"></select></div>
        <div class="col-md-3"><label class="form-label">Nghề nghiệp</label><select name="occupation" class="form-select" data-options="occupations"></select></div>
        <div class="col-md-3"><label class="form-label">Học vấn</label><select name="educationLevel" class="form-select" data-options="educationLevels"></select></div>
        <div class="col-md-3"><label class="form-label">Hôn nhân</label><select name="maritalStatus" class="form-select" data-options="maritalStatuses"></select></div>
        <div class="col-md-3"><label class="form-label">Thường trú</label><select name="residency_status" class="form-select"><option value="PERMANENT">Thường trú</option><option value="TEMPORARY">Tạm trú</option></select></div>
        <div class="col-md-3"><label class="form-label">Hiện tại</label><select name="presenceStatus" class="form-select"><option value="AT_HOME">Ở nhà</option><option value="AWAY">Đi vắng</option></select></div>
        <div class="col-md-3"><label class="form-label">Trạng thái</label><select name="status" class="form-select"><option value="ALIVE">Còn sống</option><option value="DECEASED">Đã chết</option></select></div>
        <div class="col-md-9"><label class="form-label">Địa chỉ hiện tại</label><input name="currentAddress" class="form-control"></div>
      </div></div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-primary">Lưu</button></div>
    </form></div>
  </div>

  <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
      <div class="modal-header"><h5 id="detailTitle" class="modal-title">Chi tiết</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
      <div id="detailBody" class="modal-body"></div>
    </div></div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js"></script>
</body>
</html>
