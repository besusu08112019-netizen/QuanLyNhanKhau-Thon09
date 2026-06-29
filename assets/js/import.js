(() => {
  const start = () => {
    if (!canImport()) return;
    injectImportScreen();
    bindImportNavigation();
  };

  const originalShowApp = window.showApp;
  if (typeof originalShowApp === 'function') {
    window.showApp = function patchedShowApp() {
      originalShowApp();
      start();
    };
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();

  function canImport() {
    const role = window.App?.user?.role;
    return role === 'ADMIN' || role === 'SUPER_ADMIN' || role === 'OFFICER';
  }

  function injectImportScreen() {
    const nav = document.querySelector('.sidebar .nav');
    const main = document.querySelector('.main-area');
    if (!nav || !main || document.querySelector('[data-screen="import"]')) return;
    const reportsButton = document.querySelector('[data-screen="reports"]');
    const button = '<button class="nav-link" data-screen="import">Import dữ liệu</button>';
    reportsButton ? reportsButton.insertAdjacentHTML('beforebegin', button) : nav.insertAdjacentHTML('beforeend', button);
    main.insertAdjacentHTML('beforeend', '<section id="importScreen" class="screen">' +
      '<div class="content-card mb-3"><h3 class="section-title">Hướng dẫn Import Excel</h3><ul class="mb-3"><li>Chọn đúng loại dữ liệu trước khi import.</li><li>Không đổi tên Sheet.</li><li>Không đổi tên cột.</li><li>CCCD và số điện thoại để dạng Text.</li><li>Với hộ dân: không trùng Mã hộ; cột Gia đình có công/Hộ nghèo/Hộ cận nghèo/Hộ có người khuyết tật nhập 1 hoặc 0.</li></ul><div class="d-flex flex-wrap gap-2"><a class="btn btn-success" href="sample-data/Mau_Import_NhanKhau.xlsx" download><i class="fa-solid fa-file-excel"></i> Tải mẫu nhân khẩu</a><a class="btn btn-outline-success" href="sample-data/Mau_Import_HoDan.xlsx" download><i class="fa-solid fa-file-excel"></i> Tải mẫu hộ dân</a></div></div>' +
      '<form id="importForm" class="content-card mb-3"><div class="row g-3 align-items-end"><div class="col-md-3"><label class="form-label">Loại dữ liệu</label><select name="type" class="form-select"><option value="person">Nhân khẩu</option><option value="household">Hộ dân</option></select></div><div class="col-md-3"><label class="form-label">Khi trùng mã hộ</label><select name="mode" class="form-select"><option value="skip">Bỏ qua</option><option value="update">Cập nhật</option></select></div><div class="col-md-4"><label class="form-label">File dữ liệu</label><input name="file" type="file" class="form-control" accept=".csv,.xlsx" required></div><div class="col-md-2 d-grid gap-2"><button id="importPreviewBtn" class="btn btn-outline-primary" type="button">Kiểm tra</button><button id="importRunBtn" class="btn btn-primary" type="button">Bắt đầu Import</button></div></div></form>' +
      '<div class="row g-3 mb-3"><div class="col-md-3"><div class="metric-card"><div class="metric-label">Tổng số dòng</div><div id="importTotal" class="metric-value">0</div></div></div><div class="col-md-3"><div class="metric-card"><div class="metric-label">Hợp lệ</div><div id="importValid" class="metric-value">0</div></div></div><div class="col-md-3"><div class="metric-card"><div class="metric-label">Thành công</div><div id="importSuccess" class="metric-value">0</div></div></div><div class="col-md-3"><div class="metric-card"><div class="metric-label">Thất bại</div><div id="importFailed" class="metric-value">0</div></div></div></div>' +
      '<div class="content-card"><div class="d-flex justify-content-between align-items-center mb-3"><h3 class="section-title mb-0">Kết quả Import</h3><span id="importStatus" class="text-muted small"></span></div><div id="importResult" class="table-responsive"><p class="text-muted mb-0">Chọn file CSV/XLSX và bấm Kiểm tra để xem trước dữ liệu.</p></div></div>' +
    '</section>');
    document.querySelector('#importPreviewBtn').addEventListener('click', () => submitImport('/api/import/preview', false));
    document.querySelector('#importRunBtn').addEventListener('click', () => submitImport('/api/import/process', true));
  }

  function bindImportNavigation() {
    document.querySelectorAll('[data-screen="import"]').forEach(button => {
      button.addEventListener('click', () => {
        switchScreen('import');
        document.querySelector('#screenTitle').textContent = 'Import dữ liệu';
      });
    });
  }

  async function submitImport(endpoint, refreshAfter) {
    const form = document.querySelector('#importForm');
    if (!form.reportValidity()) return;
    const formData = new FormData(form);
    setImportStatus('Đang xử lý...');
    setLoading(true);
    try {
      const response = await fetch(endpoint, { method: 'POST', headers: { Authorization: `Bearer ${App.token}`, 'X-CSRF-Token': App.csrfToken || '' }, body: formData });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.ok) throw new Error(payload?.error?.message || 'Không import được dữ liệu');
      renderImportResult(payload.data, refreshAfter);
      if (refreshAfter) {
        showToast('Đã xử lý Import');
        loadDashboard();
        loadHouseholds();
        loadPersons();
      }
    } catch (error) {
      showToast(error.message, 'danger');
      setImportStatus('Có lỗi');
    } finally {
      setLoading(false);
    }
  }

  function renderImportResult(data, processed) {
    document.querySelector('#importTotal').textContent = number(data.total || 0);
    document.querySelector('#importValid').textContent = number(data.valid ?? data.success ?? 0);
    document.querySelector('#importSuccess').textContent = number(data.success || 0);
    document.querySelector('#importFailed').textContent = number(data.failed || 0);
    const message = processed ? 'Thành công ' + number(data.success || 0) + ', bỏ qua ' + number(data.skipped || 0) + ', thất bại ' + number(data.failed || 0) : 'Hợp lệ ' + number(data.valid || 0) + ', thất bại ' + number(data.failed || 0);
    setImportStatus(message);
    const rows = (data.errors || []).map(error => '<tr><td>' + escapeHtml(error.row || '') + '</td><td>' + escapeHtml(error.message || '') + '</td></tr>').join('') || '<tr><td colspan="2" class="text-center text-muted py-3">Không có lỗi</td></tr>';
    document.querySelector('#importResult').innerHTML = '<table class="table table-sm table-bordered align-middle mb-0"><thead><tr><th style="width:120px">Dòng</th><th>Lý do lỗi</th></tr></thead><tbody>' + rows + '</tbody></table>';
  }

  function setImportStatus(text) {
    document.querySelector('#importStatus').textContent = text;
  }
})();
