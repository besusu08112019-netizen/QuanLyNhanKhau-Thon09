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
    return typeof window.thon09CanAccess === 'function' ? window.thon09CanAccess('import', 'import') : false;
  }

  function injectImportScreen() {
    const nav = document.querySelector('.sidebar .nav');
    const main = document.querySelector('.main-area');
    if (!nav || !main || document.querySelector('#importScreen')) return;
    const reportsButton = document.querySelector('[data-screen="reports"]');
    const button = '<button class="nav-link" data-screen="import">Import dá»¯ liá»‡u</button>';
    if (!document.querySelector('[data-screen="import"]')) {
      reportsButton ? reportsButton.insertAdjacentHTML('beforebegin', button) : nav.insertAdjacentHTML('beforeend', button);
    }
    main.insertAdjacentHTML('beforeend', '<section id="importScreen" class="screen import-screen">' +
      '<div class="content-card import-guide-card mb-3"><div class="import-card-head"><h3 class="section-title">HÆ°á»›ng dáº«n Import Excel</h3><span>Card 1</span></div><ul class="mb-3"><li>Chá»n Ä‘Ãºng loáº¡i dá»¯ liá»‡u trÆ°á»›c khi import.</li><li>KhÃ´ng Ä‘á»•i tÃªn Sheet vÃ  khÃ´ng Ä‘á»•i tÃªn cá»™t trong file máº«u.</li><li>NgÃ y sinh dÃ¹ng Ä‘á»‹nh dáº¡ng dd/MM/yyyy hoáº·c yyyy-MM-dd.</li><li>CCCD vÃ  sá»‘ Ä‘iá»‡n thoáº¡i Ä‘á»ƒ dáº¡ng Text Ä‘á»ƒ khÃ´ng máº¥t sá»‘ 0 Ä‘áº§u.</li><li>Kiá»ƒm tra dá»¯ liá»‡u trÆ°á»›c, chá»‰ báº¥m Báº¯t Ä‘áº§u Import khi khÃ´ng cÃ²n lá»—i.</li></ul><div class="d-flex flex-wrap gap-2"><button class="btn btn-success" type="button" data-import-template="person"><i class="fa-solid fa-file-excel"></i> Máº«u nhÃ¢n kháº©u</button><button class="btn btn-outline-success" type="button" data-import-template="household"><i class="fa-solid fa-file-excel"></i> Máº«u há»™ dÃ¢n</button></div></div>' +
      '<form id="importForm" class="content-card import-form-card mb-3"><div class="import-card-head"><h3 class="section-title">ThÃ´ng tin Import</h3><span>Card 2</span></div><div class="row g-3 align-items-end"><div class="col-lg-3 col-md-6"><label class="form-label">Loáº¡i dá»¯ liá»‡u</label><select name="type" class="form-select"><option value="person">NhÃ¢n kháº©u</option><option value="household">Há»™ dÃ¢n</option></select></div><div class="col-lg-3 col-md-6"><label class="form-label">Khi trÃ¹ng mÃ£ há»™</label><select name="mode" class="form-select"><option value="skip">Bá» qua</option><option value="update">Cáº­p nháº­t</option></select></div><div class="col-lg-4 col-md-12"><label class="form-label">File dá»¯ liá»‡u</label><input name="file" type="file" class="form-control" accept=".csv,.xlsx" required></div><div class="col-lg-2 col-md-12 d-grid gap-2"><button id="importPreviewBtn" class="btn btn-outline-primary" type="button"><i class="fa-solid fa-magnifying-glass"></i> Kiá»ƒm tra</button><button id="importRunBtn" class="btn btn-primary" type="button"><i class="fa-solid fa-file-import"></i> Báº¯t Ä‘áº§u Import</button></div></div></form>' +
      '<div class="content-card import-stats-card mb-3"><div class="import-card-head"><h3 class="section-title">Thá»‘ng kÃª</h3><span>Card 3</span></div><div class="row g-3"><div class="col-md-3 col-6"><div class="metric-card"><div class="metric-label">Tá»•ng sá»‘ dÃ²ng</div><div id="importTotal" class="metric-value">0</div></div></div><div class="col-md-3 col-6"><div class="metric-card"><div class="metric-label">Há»£p lá»‡</div><div id="importValid" class="metric-value">0</div></div></div><div class="col-md-3 col-6"><div class="metric-card"><div class="metric-label">ThÃ nh cÃ´ng</div><div id="importSuccess" class="metric-value">0</div></div></div><div class="col-md-3 col-6"><div class="metric-card"><div class="metric-label">Tháº¥t báº¡i</div><div id="importFailed" class="metric-value">0</div></div></div></div></div>' +
      '<div class="content-card import-result-card"><div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap"><h3 class="section-title mb-0">Káº¿t quáº£ Import</h3><span id="importStatus" class="text-muted small"></span></div><div id="importResult" class="table-responsive"><p class="text-muted mb-0">Chá»n file CSV/XLSX vÃ  báº¥m Kiá»ƒm tra Ä‘á»ƒ xem trÆ°á»›c dá»¯ liá»‡u.</p></div></div>' +
    '</section>');
    document.querySelector('#importPreviewBtn')?.addEventListener('click', () => submitImport('/api/import/preview', false));
    document.querySelector('#importRunBtn')?.addEventListener('click', () => submitImport('/api/import/process', true));
    document.querySelectorAll('[data-import-template]').forEach(button => button.addEventListener('click', () => downloadImportTemplate(button.dataset.importTemplate)));
  }

  function bindImportNavigation() {
    if (window.__thon09ImportNavigationBound) return;
    window.__thon09ImportNavigationBound = true;
    document.addEventListener('thon09:screen-change', event => {
      if (event.detail?.screen !== 'import') return;
      const title = document.querySelector('#screenTitle');
      if (title) title.textContent = 'Import dữ liệu';
    });
  }

  async function downloadImportTemplate(type) {
    const fileName = type === 'household' ? 'Mau_Import_HoDan.xlsx' : 'Mau_Import_NhanKhau.xlsx';
    setImportStatus('Äang táº£i ' + fileName + '...');
    try {
      const response = await fetch('/api/import/template?type=' + encodeURIComponent(type), {
        headers: { Authorization: 'Bearer ' + (App.token || ''), 'X-CSRF-Token': App.csrfToken || '' },
        cache: 'no-store'
      });
      if (!response.ok) {
        const payload = await response.json().catch(() => null);
        throw new Error(payload?.error?.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c file máº«u');
      }
      const blob = await response.blob();
      if (!blob.size) throw new Error('File máº«u rá»—ng hoáº·c khÃ´ng tá»“n táº¡i trÃªn host');
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = fileName;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
      setImportStatus('ÄÃ£ táº£i ' + fileName);
    } catch (error) {
      setImportStatus('KhÃ´ng táº£i Ä‘Æ°á»£c file máº«u');
      showToast(error.message, 'danger');
    }
  }

  async function submitImport(endpoint, refreshAfter) {
    const form = document.querySelector('#importForm');
    if (!form || !form.reportValidity()) return;
    const formData = new FormData(form);
    setImportStatus('Äang xá»­ lÃ½...');
    setLoading(true);
    try {
      const response = await fetch(endpoint, { method: 'POST', headers: { Authorization: `Bearer ${App.token}`, 'X-CSRF-Token': App.csrfToken || '' }, body: formData });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.ok) throw new Error(payload?.error?.message || 'KhÃ´ng import Ä‘Æ°á»£c dá»¯ liá»‡u');
      renderImportResult(payload.data, refreshAfter);
      if (refreshAfter) {
        showToast('ÄÃ£ xá»­ lÃ½ Import');
        if (typeof loadDashboard === 'function') loadDashboard();
        if (typeof loadHouseholds === 'function') loadHouseholds();
        if (typeof loadPersons === 'function') loadPersons();
        if (typeof refreshLoginConfig === 'function') refreshLoginConfig();
        window.dispatchEvent(new CustomEvent('thon09:data-mutated', { detail: { module: 'import' } }));
      }
    } catch (error) {
      showToast(error.message, 'danger');
      setImportStatus('CÃ³ lá»—i');
    } finally {
      setLoading(false);
    }
  }

  function renderImportResult(data, processed) {
    const totalEl = document.querySelector('#importTotal');
    const validEl = document.querySelector('#importValid');
    const successEl = document.querySelector('#importSuccess');
    const failedEl = document.querySelector('#importFailed');
    if (totalEl) totalEl.textContent = number(data.total || 0);
    if (validEl) validEl.textContent = number(data.valid ?? data.success ?? 0);
    const warningEl = document.querySelector('#importWarning');
    if (warningEl) warningEl.textContent = number((data.warnings || []).length || data.warning || 0);
    if (successEl) successEl.textContent = number(data.success || 0);
    if (failedEl) failedEl.textContent = number(data.failed || 0);
    const message = processed ? 'ThÃ nh cÃ´ng ' + number(data.success || 0) + ', bá» qua ' + number(data.skipped || 0) + ', cáº£nh bÃ¡o ' + number((data.warnings || []).length || data.warning || 0) + ', tháº¥t báº¡i ' + number(data.failed || 0) : 'Há»£p lá»‡ ' + number(data.valid || 0) + ', cáº£nh bÃ¡o ' + number((data.warnings || []).length || data.warning || 0) + ', tháº¥t báº¡i ' + number(data.failed || 0);
    setImportStatus(message + (data.rolledBack ? ' - Ä‘Ã£ rollback' : ''));
    const preview = previewTable(data.previewRows || []);
    const warnings = issueTable(data.warnings || [], 'Cáº£nh bÃ¡o', 'warning');
    const errors = issueTable(data.errors || [], 'Danh sÃ¡ch lá»—i', 'danger');
    const download = (data.errors || []).length ? '<button id="importErrorDownload" class="btn btn-outline-danger btn-sm mt-3" type="button"><i class="fa-solid fa-download"></i> Táº£i file lá»—i CSV</button>' : '';
    const resultEl = document.querySelector('#importResult');
    if (resultEl) resultEl.innerHTML = preview + warnings + errors + download;
    const btn = document.querySelector('#importErrorDownload');
    if (btn) btn.addEventListener('click', () => downloadImportErrors(data.errors || []));
  }

  function previewTable(rows) {
    if (!rows.length) return '<div class="alert alert-info mb-3">ChÆ°a cÃ³ dá»¯ liá»‡u há»£p lá»‡ Ä‘á»ƒ xem trÆ°á»›c.</div>';
    const keys = Object.keys(rows[0].data || {}).slice(0, 8);
    const head = keys.map(key => '<th>' + escapeHtml(importLabel(key)) + '</th>').join('');
    const body = rows.map(item => '<tr><td>' + escapeHtml(item.row || '') + '</td>' + keys.map(key => '<td>' + escapeHtml(item.data[key] ?? '') + '</td>').join('') + '</tr>').join('');
    return '<h4 class="section-title">Dá»¯ liá»‡u xem trÆ°á»›c</h4><table class="table table-sm table-bordered align-middle mb-3"><thead><tr><th style="width:90px">DÃ²ng</th>' + head + '</tr></thead><tbody>' + body + '</tbody></table>';
  }

  function issueTable(items, title, type) {
    if (!items.length) return '';
    const rows = items.map(error => '<tr><td>' + escapeHtml(error.row || '') + '</td><td>' + escapeHtml(error.message || '') + '</td></tr>').join('');
    return '<h4 class="section-title text-' + type + '">' + title + '</h4><table class="table table-sm table-bordered align-middle mb-3"><thead><tr><th style="width:120px">DÃ²ng</th><th>Ná»™i dung</th></tr></thead><tbody>' + rows + '</tbody></table>';
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
    return ({householdCode:'MÃ£ há»™', citizenCode:'MÃ£ nhÃ¢n kháº©u', fullName:'Há» tÃªn', dateOfBirth:'NgÃ y sinh', identityNumber:'CCCD', phone:'Sá»‘ Ä‘iá»‡n thoáº¡i', headCitizenName:'Chá»§ há»™', address:'Äá»‹a chá»‰', relationship:'Quan há»‡', fatherName:'Há» tÃªn bá»‘', motherName:'Há» tÃªn máº¹'})[key] || key;
  }

  function setImportStatus(text) {
    const status = document.querySelector('#importStatus');
    if (status) status.textContent = text;
  }
})();
