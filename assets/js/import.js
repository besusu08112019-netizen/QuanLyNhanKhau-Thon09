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
    if (!nav || !main || document.querySelector('#importScreen')) return;
    const reportsButton = document.querySelector('[data-screen="reports"]');
    const button = '<button class="nav-link" data-screen="import">Import dữ liệu</button>';
    if (!document.querySelector('[data-screen="import"]')) {
      reportsButton ? reportsButton.insertAdjacentHTML('beforebegin', button) : nav.insertAdjacentHTML('beforeend', button);
    }
    main.insertAdjacentHTML('beforeend', '<section id="importScreen" class="screen">' +
      '<div class="content-card mb-3"><h3 class="section-title">Hướng dẫn Import Excel</h3><ul class="mb-3"><li>Chọn đúng loại dữ liệu trước khi import.</li><li>Không đổi tên Sheet.</li><li>Không đổi tên cột.</li><li>CCCD và số điện thoại để dạng Text.</li><li>Với hộ dân: không trùng Mã hộ; cột Gia đình có công/Hộ nghèo/Hộ cận nghèo/Hộ có người khuyết tật nhập 1 hoặc 0.</li></ul><div class="d-flex flex-wrap gap-2"><a class="btn btn-success" href="/api/import/template?type=person" download="Mau_Import_NhanKhau.xlsx"><i class="fa-solid fa-file-excel"></i> Tải mẫu nhân khẩu</a><a class="btn btn-outline-success" href="/api/import/template?type=household" download="Mau_Import_HoDan.xlsx"><i class="fa-solid fa-file-excel"></i> Tải mẫu hộ dân</a></div></div>' +
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
    const warningEl = document.querySelector('#importWarning');
    if (warningEl) warningEl.textContent = number((data.warnings || []).length || data.warning || 0);
    document.querySelector('#importSuccess').textContent = number(data.success || 0);
    document.querySelector('#importFailed').textContent = number(data.failed || 0);
    const message = processed ? 'Thành công ' + number(data.success || 0) + ', bỏ qua ' + number(data.skipped || 0) + ', cảnh báo ' + number((data.warnings || []).length || data.warning || 0) + ', thất bại ' + number(data.failed || 0) : 'Hợp lệ ' + number(data.valid || 0) + ', cảnh báo ' + number((data.warnings || []).length || data.warning || 0) + ', thất bại ' + number(data.failed || 0);
    setImportStatus(message + (data.rolledBack ? ' - đã rollback' : ''));
    const preview = previewTable(data.previewRows || []);
    const warnings = issueTable(data.warnings || [], 'Cảnh báo', 'warning');
    const errors = issueTable(data.errors || [], 'Danh sách lỗi', 'danger');
    const download = (data.errors || []).length ? '<button id="importErrorDownload" class="btn btn-outline-danger btn-sm mt-3" type="button"><i class="fa-solid fa-download"></i> Tải file lỗi CSV</button>' : '';
    document.querySelector('#importResult').innerHTML = preview + warnings + errors + download;
    const btn = document.querySelector('#importErrorDownload');
    if (btn) btn.addEventListener('click', () => downloadImportErrors(data.errors || []));
  }

  function previewTable(rows) {
    if (!rows.length) return '<div class="alert alert-info mb-3">Chưa có dữ liệu hợp lệ để xem trước.</div>';
    const keys = Object.keys(rows[0].data || {}).slice(0, 8);
    const head = keys.map(key => '<th>' + escapeHtml(importLabel(key)) + '</th>').join('');
    const body = rows.map(item => '<tr><td>' + escapeHtml(item.row || '') + '</td>' + keys.map(key => '<td>' + escapeHtml(item.data[key] ?? '') + '</td>').join('') + '</tr>').join('');
    return '<h4 class="section-title">Dữ liệu xem trước</h4><table class="table table-sm table-bordered align-middle mb-3"><thead><tr><th style="width:90px">Dòng</th>' + head + '</tr></thead><tbody>' + body + '</tbody></table>';
  }

  function issueTable(items, title, type) {
    if (!items.length) return '';
    const rows = items.map(error => '<tr><td>' + escapeHtml(error.row || '') + '</td><td>' + escapeHtml(error.message || '') + '</td></tr>').join('');
    return '<h4 class="section-title text-' + type + '">' + title + '</h4><table class="table table-sm table-bordered align-middle mb-3"><thead><tr><th style="width:120px">Dòng</th><th>Nội dung</th></tr></thead><tbody>' + rows + '</tbody></table>';
  }

  function downloadImportErrors(errors) {
    const csv = 'Dong,Loi\n' + errors.map(error => '"' + String(error.row || '').replace(/"/g, '""') + '","' + String(error.message || '').replace(/"/g, '""') + '"').join('\n');
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'loi_import_' + Date.now() + '.csv';
    document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
  }

  function importLabel(key) {
    return ({householdCode:'Mã hộ', citizenCode:'Mã nhân khẩu', fullName:'Họ tên', dateOfBirth:'Ngày sinh', identityNumber:'CCCD', phone:'Số điện thoại', headCitizenName:'Chủ hộ', address:'Địa chỉ', relationship:'Quan hệ'})[key] || key;
  }

  function setImportStatus(text) {
    document.querySelector('#importStatus').textContent = text;
  }
})();
