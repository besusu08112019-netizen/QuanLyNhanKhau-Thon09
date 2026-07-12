(() => {
  const householdCategories = [
    ['', 'Táº¥t cáº£'],
    ['poor', 'Há»™ nghÃ¨o'],
    ['near_poor', 'Há»™ cáº­n nghÃ¨o'],
    ['escaped_poverty', 'Há»™ má»›i thoÃ¡t nghÃ¨o'],
    ['policy', 'Há»™ chÃ­nh sÃ¡ch'],
    ['meritorious', 'Há»™ cÃ³ cÃ´ng'],
    ['normal', 'Há»™ bÃ¬nh thÆ°á»ng'],
    ['other', 'KhÃ¡c'],
  ];

  window.roleLabel = function roleLabel(role) {
    return ({ SUPER_ADMIN:'Super Admin', ADMIN:'Admin', OFFICER:'CÃ¡n bá»™', VIEWER:'KhÃ¡ch' })[role] || role || '';
  };

  function bootBridgeUi() {
    enforceSuperAdminMenu();
    setupHouseholdCategoryFilters();
    setupDigitalGovernmentFeatures();
    setTimeout(setupHouseholdCategoryFilters, 800);
    setTimeout(setupDigitalGovernmentFeatures, 900);
    setTimeout(setupReportCategoryFilter, 500);
  }

  document.addEventListener('DOMContentLoaded', () => {
    bootBridgeUi();
    document.addEventListener('thon09:screen-change', event => {
      const screen = event.detail?.screen;
      if (screen === 'users') { ensureRoleOptions(); loadAdminUsers(); }
      if (screen === 'logs') loadAdminLogs();
      if (screen === 'backups') loadAdminBackups();
    });
  });
  document.addEventListener('thon09:auth-state', event => {
    if (event.detail?.authenticated) bootBridgeUi();
  });


  const digitalCitizenFields = [
    ['partyMember','Äáº£ng viÃªn'], ['youthUnionMember','ÄoÃ n viÃªn Thanh niÃªn'], ['womenUnionMember','Há»™i viÃªn Há»™i Phá»¥ ná»¯'], ['farmersUnionMember','Há»™i viÃªn Há»™i NÃ´ng dÃ¢n'], ['veteransUnionMember','Há»™i viÃªn Há»™i Cá»±u chiáº¿n binh'], ['elderlyUnionMember','Há»™i viÃªn Há»™i NgÆ°á»i cao tuá»•i'],
    ['meritoriousPerson','NgÆ°á»i cÃ³ cÃ´ng'], ['martyrRelative','ThÃ¢n nhÃ¢n liá»‡t sÄ©'], ['woundedSoldier','ThÆ°Æ¡ng binh'], ['sickSoldier','Bá»‡nh binh'], ['disabledPerson','NgÆ°á»i khuyáº¿t táº­t'], ['socialAssistance','Báº£o trá»£ xÃ£ há»™i'],
    ['employed','CÃ³ viá»‡c lÃ m'], ['unemployed','Tháº¥t nghiá»‡p'], ['freelanceLabor','Lao Ä‘á»™ng tá»± do'], ['outProvinceLabor','Lao Ä‘á»™ng ngoÃ i tá»‰nh'], ['foreignLabor','Lao Ä‘á»™ng nÆ°á»›c ngoÃ i'], ['pupil','Há»c sinh'], ['student','Sinh viÃªn'], ['retired','Nghá»‰ hÆ°u'],
  ];

  function setupDigitalGovernmentFeatures() {
    setupDigitalCitizenForm();
    setupDigitalPersonFilters();
    setupDigitalReports();
    setupDigitalDashboard();
  }

  function setupDigitalCitizenForm() {
    const form = document.querySelector('#personForm');
    const bodyRow = form?.querySelector('.modal-body .row');
    if (!form || !bodyRow) return;
    ensurePersonProfileTabs(form, bodyRow);
    if (form.querySelector('[data-digital-citizen-fields]')) return;
    const group = (title, fields) => '<div class="col-12" data-digital-citizen-fields><div class="border rounded p-3"><div class="fw-semibold mb-2">' + title + '</div><div class="row g-2">' + fields.map(([name,label]) => '<div class="col-md-3 col-sm-6 form-check ms-2"><input class="form-check-input" type="checkbox" name="' + name + '" id="' + name + '"><label class="form-check-label" for="' + name + '">' + label + '</label></div>').join('') + '</div></div></div>';
    bodyRow.insertAdjacentHTML('beforeend', group('ChÃ­nh trá»‹ - XÃ£ há»™i', digitalCitizenFields.slice(0,6)) + group('ChÃ­nh sÃ¡ch', digitalCitizenFields.slice(6,12)) + group('Lao Ä‘á»™ng', digitalCitizenFields.slice(12)));
    const originalOpen = window.openPersonForm;
    if (typeof originalOpen === 'function' && !originalOpen.__digitalWrapped) {
      window.openPersonForm = async function digitalOpenPersonForm(id = null) {
        await originalOpen(id);
        const targetForm = document.querySelector('#personForm');
        resetPersonProfileTabs(targetForm, id);
        digitalCitizenFields.forEach(([name]) => { const el = targetForm?.elements[name]; if (el) el.checked = false; });
        if (id) {
          const row = await api('/api/persons/' + id);
          digitalCitizenFields.forEach(([name]) => {
            const snake = name.replace(/[A-Z]/g, m => '_' + m.toLowerCase());
            const el = targetForm?.elements[name];
            if (el) el.checked = Number(row[snake] ?? row[name] ?? 0) > 0;
          });
        }
      };
      window.openPersonForm.__digitalWrapped = true;
    }
  }

  const personProfileGroups = [
    ['images', 'áº¢nh', 'IMAGE', 'citizen_image', file => String(file.mime_type || '').startsWith('image/') || ['PHOTO','IMAGE'].includes(String(file.file_type || '').toUpperCase())],
    ['documents', 'TÃ i liá»‡u', 'DOCUMENT', 'citizen_document', file => String(file.mime_type || '').includes('pdf') || String(file.mime_type || '').includes('word') || String(file.mime_type || '').includes('excel') || ['DOCUMENT','WORD','EXCEL','SCAN'].includes(String(file.file_type || '').toUpperCase())],
    ['videos', 'Video', 'VIDEO', 'citizen_video', file => String(file.mime_type || '').startsWith('video/') || String(file.file_type || '').toUpperCase() === 'VIDEO'],
    ['other', 'KhÃ¡c', 'OTHER', 'citizen_other', () => true],
  ];

  function ensurePersonProfileTabs(form, bodyRow) {
    if (form.querySelector('[data-person-profile-tabs]')) return;
    const modalBody = form.querySelector('.modal-body');
    const nav = document.createElement('ul');
    nav.className = 'nav nav-tabs mb-3';
    nav.dataset.personProfileTabs = '1';
    nav.innerHTML = '<li class="nav-item"><button class="nav-link active" type="button" data-person-tab="info">ThÃ´ng tin</button></li>'
      + '<li class="nav-item"><button class="nav-link" type="button" data-person-tab="files">Há»“ sÆ¡ sá»‘</button></li>'
      + '<li class="nav-item"><button class="nav-link" type="button" data-person-tab="timeline">Lá»‹ch sá»­</button></li>';
    const infoPane = document.createElement('section');
    infoPane.dataset.personPane = 'info';
    const filesPane = document.createElement('section');
    filesPane.dataset.personPane = 'files';
    filesPane.className = 'd-none';
    filesPane.innerHTML = '<div class="text-muted small py-3">Chá»n tab Há»“ sÆ¡ sá»‘ Ä‘á»ƒ táº£i tÃ i liá»‡u.</div>';
    const timelinePane = document.createElement('section');
    timelinePane.dataset.personPane = 'timeline';
    timelinePane.className = 'd-none';
    timelinePane.innerHTML = '<div class="text-muted small py-3">Chá»n tab Lá»‹ch sá»­ Ä‘á»ƒ táº£i timeline.</div>';
    modalBody.insertBefore(nav, bodyRow);
    modalBody.insertBefore(infoPane, bodyRow);
    infoPane.appendChild(bodyRow);
    modalBody.appendChild(filesPane);
    modalBody.appendChild(timelinePane);
    nav.addEventListener('click', event => {
      const button = event.target.closest('[data-person-tab]');
      if (!button) return;
      activatePersonProfileTab(form, button.dataset.personTab);
    });
  }

  function resetPersonProfileTabs(form, personId) {
    if (!form) return;
    form.dataset.personProfileId = personId || '';
    form.querySelector('[data-person-pane="files"]')?.removeAttribute('data-loaded');
    form.querySelector('[data-person-pane="timeline"]')?.removeAttribute('data-loaded');
    activatePersonProfileTab(form, 'info');
  }

  function activatePersonProfileTab(form, tab) {
    form.querySelectorAll('[data-person-tab]').forEach(button => button.classList.toggle('active', button.dataset.personTab === tab));
    form.querySelectorAll('[data-person-pane]').forEach(pane => pane.classList.toggle('d-none', pane.dataset.personPane !== tab));
    const id = Number(form.elements.id?.value || form.dataset.personProfileId || 0);
    if (tab === 'files') loadPersonProfileFiles(form, id);
    if (tab === 'timeline') loadPersonProfileTimeline(form, id);
  }

  async function loadPersonProfileFiles(form, id, force = false) {
    const pane = form.querySelector('[data-person-pane="files"]');
    if (!pane || (pane.dataset.loaded === '1' && !force)) return;
    if (!id) {
      pane.innerHTML = '<div class="alert alert-info py-2 mb-0">Vui lÃ²ng lÆ°u nhÃ¢n kháº©u trÆ°á»›c khi quáº£n lÃ½ há»“ sÆ¡ sá»‘.</div>';
      return;
    }
    pane.innerHTML = '<div class="text-muted small py-3">Äang táº£i há»“ sÆ¡ sá»‘...</div>';
    try {
      const files = await api('/api/files?' + new URLSearchParams({ module: 'citizen', entityId: String(id) }).toString());
      pane.innerHTML = renderPersonFileManager(Array.isArray(files) ? files : []);
      pane.dataset.loaded = '1';
      bindPersonFileManager(form, pane, id);
    } catch (error) {
      pane.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + escapeHtml(error.message) + '</div>';
    }
  }

  function renderPersonFileManager(files) {
    const buckets = { images: [], documents: [], videos: [], other: [] };
    files.forEach(file => {
      const group = personProfileGroups.find(([key, , , , match]) => key !== 'other' && match(file)) || personProfileGroups[3];
      buckets[group[0]].push(file);
    });
    return '<div class="digital-file-manager">' + personProfileGroups.map(([key, title, fileType, section]) => renderPersonFileGroup(key, title, fileType, section, buckets[key] || [])).join('') + '</div>';
  }

  function renderPersonFileGroup(key, title, fileType, section, files) {
    const canUpdateCitizen = typeof window.thon09CanAccess === 'function' && window.thon09CanAccess('citizen', 'update');
    const rows = files.length ? files.map(file => '<tr><td><div class="fw-semibold">' + escapeHtml(file.original_name || file.file_name || 'Tá»‡p Ä‘Ã­nh kÃ¨m') + '</div><div class="small text-muted">' + escapeHtml(file.description || file.profile_section || file.category || '') + '</div></td><td>' + escapeHtml(formatFileSize(file.file_size)) + '</td><td>' + escapeHtml(formatDateTime(file.created_at)) + '</td><td><div class="btn-group btn-group-sm"><button class="btn btn-outline-primary" type="button" data-profile-preview="' + Number(file.id || 0) + '">Xem</button><button class="btn btn-outline-secondary" type="button" data-profile-download="' + Number(file.id || 0) + '">Táº£i</button>' + (canUpdateCitizen ? '<button class="btn btn-outline-danger" type="button" data-profile-delete="' + Number(file.id || 0) + '">XÃ³a</button>' : '') + '</div></td></tr>').join('') : '<tr><td colspan="4" class="text-muted small">ChÆ°a cÃ³ file.</td></tr>';
    return '<section class="border rounded p-3 mb-3" data-profile-file-group="' + key + '"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><h6 class="mb-0">' + title + '</h6><div class="d-flex flex-wrap gap-2"><input class="form-control form-control-sm" data-profile-description placeholder="MÃ´ táº£"><input type="file" class="d-none" data-profile-file-input data-file-type="' + fileType + '" data-section="' + section + '"><button class="btn btn-sm btn-primary" type="button" data-profile-upload>Upload</button></div></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>TÃªn file</th><th>Dung lÆ°á»£ng</th><th>NgÃ y upload</th><th>Thao tÃ¡c</th></tr></thead><tbody>' + rows + '</tbody></table></div></section>';
  }

  function bindPersonFileManager(form, pane, id) {
    pane.querySelectorAll('[data-profile-upload]').forEach(button => button.addEventListener('click', () => button.closest('[data-profile-file-group]')?.querySelector('[data-profile-file-input]')?.click()));
    pane.querySelectorAll('[data-profile-file-input]').forEach(input => input.addEventListener('change', event => uploadPersonProfileFile(form, event.currentTarget, id)));
    pane.querySelectorAll('[data-profile-preview]').forEach(button => button.addEventListener('click', () => window.open('/api/files/' + encodeURIComponent(button.dataset.profilePreview) + '/preview', '_blank', 'noopener')));
    pane.querySelectorAll('[data-profile-download]').forEach(button => button.addEventListener('click', () => downloadProfileFile(button.dataset.profileDownload)));
    pane.querySelectorAll('[data-profile-delete]').forEach(button => button.addEventListener('click', () => deletePersonProfileFile(form, button.dataset.profileDelete, id)));
  }

  async function uploadPersonProfileFile(form, input, id) {
    const file = input.files?.[0];
    if (!file) return;
    const group = input.closest('[data-profile-file-group]');
    const data = new FormData();
    data.append('module', 'citizen');
    data.append('entityId', id);
    data.append('profileSection', input.dataset.section || 'citizen_other');
    data.append('fileType', input.dataset.fileType || 'OTHER');
    data.append('description', group?.querySelector('[data-profile-description]')?.value || '');
    data.append('file', file);
    try {
      await api('/api/files', { method: 'POST', body: data });
      showToast('ÄÃ£ táº£i lÃªn há»“ sÆ¡ sá»‘');
      await loadPersonProfileFiles(form, id, true);
      form.querySelector('[data-person-pane="timeline"]')?.removeAttribute('data-loaded');
    } catch (error) {
      showToast(error.message, 'danger');
    } finally {
      input.value = '';
    }
  }

  async function deletePersonProfileFile(form, fileId, id) {
    if (!confirm('XÃ³a file nÃ y?')) return;
    try {
      await api('/api/files/' + encodeURIComponent(fileId), { method: 'DELETE' });
      showToast('ÄÃ£ xÃ³a file');
      await loadPersonProfileFiles(form, id, true);
      form.querySelector('[data-person-pane="timeline"]')?.removeAttribute('data-loaded');
    } catch (error) {
      showToast(error.message, 'danger');
    }
  }

  async function downloadProfileFile(id) {
    const response = await fetch('/api/files/' + encodeURIComponent(id) + '/download', { headers: { Authorization: 'Bearer ' + App.token }, cache: 'no-store' });
    if (!response.ok) return showToast('KhÃ´ng táº£i Ä‘Æ°á»£c file', 'danger');
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'attachment-' + id;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 30000);
  }

  async function loadPersonProfileTimeline(form, id, force = false) {
    const pane = form.querySelector('[data-person-pane="timeline"]');
    if (!pane || (pane.dataset.loaded === '1' && !force)) return;
    if (!id) {
      pane.innerHTML = '<div class="alert alert-info py-2 mb-0">Vui lÃ²ng lÆ°u nhÃ¢n kháº©u trÆ°á»›c khi xem lá»‹ch sá»­.</div>';
      return;
    }
    pane.innerHTML = '<div class="text-muted small py-3">Äang táº£i lá»‹ch sá»­...</div>';
    try {
      const items = await api('/api/timeline/citizen/' + encodeURIComponent(id));
      pane.innerHTML = renderPersonTimeline(Array.isArray(items) ? items : []);
      pane.dataset.loaded = '1';
    } catch (error) {
      pane.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + escapeHtml(error.message) + '</div>';
    }
  }

  function renderPersonTimeline(items) {
    if (!items.length) return '<div class="text-muted small">ChÆ°a cÃ³ lá»‹ch sá»­.</div>';
    return '<div class="list-group list-group-flush border rounded">' + items.map(item => '<div class="list-group-item"><div class="d-flex justify-content-between gap-2"><strong>' + escapeHtml(item.title || item.type || '') + '</strong><span class="small text-muted">' + escapeHtml(formatDateTime(item.time)) + '</span></div><div class="small text-muted">' + escapeHtml(timelineActor(item)) + '</div>' + (item.description ? '<div>' + escapeHtml(item.description) + '</div>' : '') + '</div>').join('') + '</div>';
  }

  function timelineActor(item) {
    const data = item?.data || {};
    return data.actor_email || data.created_by_name || data.created_by_email || data.updated_by_name || data.updated_by_email || item?.actor || '';
  }

  function formatFileSize(bytes) {
    const size = Number(bytes || 0);
    if (!size) return '';
    if (size < 1024) return size + ' B';
    if (size < 1024 * 1024) return (size / 1024).toFixed(1) + ' KB';
    return (size / 1024 / 1024).toFixed(1) + ' MB';
  }

  function formatDateTime(value) {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('vi-VN');
  }
  function setupDigitalPersonFilters() { return; }

  function setupDigitalReports() {
    const select = document.querySelector('#reportForm select[name="type"]');
    if (!select) return;
    const options = [['party_member','BÃ¡o cÃ¡o Äáº£ng viÃªn'],['youth_union_member','BÃ¡o cÃ¡o ÄoÃ n viÃªn'],['meritorious_person','BÃ¡o cÃ¡o NgÆ°á»i cÃ³ cÃ´ng'],['poor-households','BÃ¡o cÃ¡o Há»™ nghÃ¨o'],['near-poor-households','BÃ¡o cÃ¡o Há»™ cáº­n nghÃ¨o'],['labor','BÃ¡o cÃ¡o Lao Ä‘á»™ng'],['elderly','BÃ¡o cÃ¡o NgÆ°á»i cao tuá»•i'],['children','BÃ¡o cÃ¡o Tráº» em'],['disabled_person','BÃ¡o cÃ¡o NgÆ°á»i khuyáº¿t táº­t']];
    options.forEach(([value,label]) => { if (!Array.from(select.options).some(o => o.value === value)) select.insertAdjacentHTML('beforeend', '<option value="' + value + '">' + label + '</option>'); });
  }

  function setupDigitalDashboard() { return; }

  function ensureDigitalChartHost() {
    if (document.querySelector('#partyChart')) return;
    const dashboard = document.querySelector('#dashboardScreen');
    if (!dashboard) return;
    dashboard.insertAdjacentHTML('beforeend', '<div class="row g-3 mt-1" data-digital-charts><div class="col-lg-4"><div class="content-card"><h3 class="section-title">Äáº£ng viÃªn</h3><div id="partyChart" class="chart-list"></div></div></div><div class="col-lg-4"><div class="content-card"><h3 class="section-title">ÄoÃ n viÃªn</h3><div id="youthChart" class="chart-list"></div></div></div><div class="col-lg-4"><div class="content-card"><h3 class="section-title">Lao Ä‘á»™ng</h3><div id="laborChart" class="chart-list"></div></div></div><div class="col-lg-4"><div class="content-card"><h3 class="section-title">Nghá» nghiá»‡p</h3><div id="occupationChart" class="chart-list"></div></div></div><div class="col-lg-4"><div class="content-card"><h3 class="section-title">DÃ¢n tá»™c</h3><div id="ethnicityChart" class="chart-list"></div></div></div><div class="col-lg-4"><div class="content-card"><h3 class="section-title">TÃ´n giÃ¡o</h3><div id="religionChart" class="chart-list"></div></div></div></div>');
  }

  function setupHouseholdCategoryFilters() {
    const category = document.querySelector('#householdCategoryFilter');
    const status = document.querySelector('#householdStatusFilter');
    if (category && !category.dataset.bridgeBound) { category.dataset.bridgeBound = '1'; category.addEventListener('change', () => { App.households.category = category.value; App.households.household_type = category.value; App.households.page = 1; window.loadHouseholds(); }); }
    if (status && !status.dataset.bridgeBound) { status.dataset.bridgeBound = '1'; status.addEventListener('change', () => { App.households.status = status.value; App.households.page = 1; window.loadHouseholds(); }); }
  }

  async function loadHouseholdsWithCategory() {
    try {
      App.households = App.households || { page: 1, pageSize: 20, search: '' };
      const category = App.households.category || App.households.household_type || '';
      const params = new URLSearchParams({ page: App.households.page || 1, pageSize: App.households.pageSize || 20 });
      if (App.households.search) params.set('search', App.households.search);
      if (category) { params.set('category', category); params.set('household_type', category); }
      const data = await api('/api/households?' + params.toString());
      const items = data.items || [];
      const tbody = document.querySelector('#householdRows');
      if (!tbody) return;
      const canDeleteHousehold = typeof window.thon09CanAccess === 'function' && window.thon09CanAccess('household', 'delete');
      const canUpdateHousehold = typeof window.thon09CanAccess === 'function' && window.thon09CanAccess('household', 'update');
      tbody.innerHTML = items.map(row => '<tr>' +
        '<td>' + (canDeleteHousehold ? '<input type="checkbox" class="household-check" value="' + row.id + '">' : '') + '</td>' +
        '<td><button class="btn btn-link p-0 fw-semibold" onclick="showHousehold(' + row.id + ')">' + escapeHtml(row.household_code) + '</button></td>' +
        '<td>' + escapeHtml(row.head_citizen_name || '') + '</td>' +
        '<td>' + escapeHtml(row.address || '') + '</td>' +
        '<td>' + number(row.at_home_count || 0) + '</td>' +
        '<td>' + number(row.away_count || 0) + '</td>' +
        '<td>' + householdBadges(row) + '</td>' +
        '<td class="text-end"><button class="btn btn-sm btn-outline-secondary" onclick="showHousehold(' + row.id + ')">Xem</button>' + (canUpdateHousehold ? ' <button class="btn btn-sm btn-outline-primary" onclick="openHouseholdForm(' + row.id + ')">Sá»­a</button>' : '') + (canDeleteHousehold ? ' <button class="btn btn-sm btn-outline-danger" onclick="deleteHousehold(' + row.id + ')">XÃ³a</button>' : '') + '</td>' +
      '</tr>').join('') || emptyRow(8, 'KhÃ´ng cÃ³ dá»¯ liá»‡u');
      renderPager('#householdPager', data, page => { App.households.page = page; window.loadHouseholds(); });
    } catch (error) { showToast('KhÃ´ng táº£i Ä‘Æ°á»£c danh sÃ¡ch há»™ dÃ¢n: ' + error.message, 'danger'); }
  }

  function setupReportCategoryFilter() {
    const form = document.querySelector('#reportForm');
    if (!form) return;
    if (form.querySelector('.report-filter-grid')) return;
    if (!form.querySelector('[name="householdType"]')) {
      const viewButtonCol = form.querySelector('button[type="submit"]')?.closest('[class*="col-"]');
      const html = `<div class="col-md-2"><label class="form-label">Diá»‡n há»™</label><select name="householdType" class="form-select">${householdCategories.map(([value, label]) => `<option value="${value}">${label}</option>`).join('')}</select></div>`;
      (viewButtonCol || form.querySelector('.row')).insertAdjacentHTML('beforebegin', html);
    }
    window.thon09ViewReport = viewReportFromApi;
    form.onsubmit = event => { event.preventDefault(); event.stopPropagation(); viewReportFromApi(); return false; };
    const submit = form.querySelector('button[type="submit"]');
    if (submit) submit.onclick = event => { event.preventDefault(); event.stopPropagation(); viewReportFromApi(); return false; };
    setupDigitalReports();
    const excel = document.querySelector('#reportExcelBtn');
    if (excel) excel.onclick = event => { event.preventDefault(); downloadReport('excel'); return false; };
    const pdf = document.querySelector('#reportPdfBtn');
    if (pdf) pdf.onclick = event => { event.preventDefault(); downloadReport('pdf'); return false; };
    const print = document.querySelector('#reportPrintBtn');
    if (print) print.onclick = event => { event.preventDefault(); viewReportFromApi(true); return false; };
  }

  async function viewReportFromApi(printAfter = false) {
    const form = document.querySelector('#reportForm');
    const preview = document.querySelector('#reportPreview');
    const titleEl = document.querySelector('#reportTitle');
    const countEl = document.querySelector('#reportCount');
    if (!form || !preview) return;
    try {
      preview.innerHTML = '<p class="text-muted mb-0">Äang táº£i dá»¯ liá»‡u...</p>';
      const params = new URLSearchParams(new FormData(form));
      const data = await api('/api/reports/summary?' + params.toString());
      if (titleEl) titleEl.textContent = data.title || 'BÃ¡o cÃ¡o';
      if (countEl) countEl.textContent = number(data.totalRows || 0) + ' dÃ²ng';
      preview.innerHTML = reportTable(data.headers || [], data.rows || []);
      if (printAfter) window.print();
    } catch (error) {
      preview.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(error.message) + '</div>';
    }
  }

  function downloadReport(format) {
    const form = document.querySelector('#reportForm');
    if (!form) return;
    const params = new URLSearchParams(new FormData(form));
    const endpoint = format === 'pdf' ? '/api/reports/export-pdf' : '/api/reports/export-excel';
    window.location.href = endpoint + '?' + params.toString();
  }

  function reportTable(headers, rows) {
    const head = headers.map(header => '<th>' + escapeHtml(header) + '</th>').join('');
    const body = rows.length ? rows.map(row => '<tr>' + row.map(cell => '<td>' + escapeHtml(cell ?? '') + '</td>').join('') + '</tr>').join('') : '<tr><td colspan="' + Math.max(1, headers.length) + '" class="text-center text-muted py-3">KhÃ´ng cÃ³ dá»¯ liá»‡u</td></tr>';
    return '<table class="table table-bordered table-hover align-middle mb-0"><thead><tr>' + head + '</tr></thead><tbody>' + body + '</tbody></table>';
  }

function scheduleCategoryFilterSetup() {
    setupHouseholdCategoryFilters();
    setupReportCategoryFilter();
    setTimeout(setupHouseholdCategoryFilters, 250);
    setTimeout(setupHouseholdCategoryFilters, 1000);
    setTimeout(setupReportCategoryFilter, 1000);
  }

  document.addEventListener('thon09:screen-change', event => {
    const screen = event.detail?.screen;
    if (screen === 'households') { setTimeout(setupHouseholdCategoryFilters, 250); setTimeout(setupDigitalGovernmentFeatures, 300); }
    if (screen === 'reports') { setTimeout(setupReportCategoryFilter, 250); setTimeout(setupDigitalGovernmentFeatures, 350); }
    if (screen === 'persons' || screen === 'dashboard') setTimeout(setupDigitalGovernmentFeatures, 300);
  });
  let categorySetupTicks = 0;
  const categorySetupTimer = setInterval(() => {
    categorySetupTicks += 1;
    scheduleCategoryFilterSetup();
    if (categorySetupTicks >= 12 || (document.querySelector('#householdCategoryFilter') && document.querySelector('#reportForm [name="householdType"]'))) {
      clearInterval(categorySetupTimer);
    }
  }, 1000);

  function enforceSuperAdminMenu() {
    setTimeout(() => {
      const role = App.user?.role || '';
      const adminOnly = ['systemAdmin','users','permissions','logs','settings','appearance','backups','restore'];
      document.querySelectorAll('.sidebar .nav-link').forEach(btn => {
        btn.classList.toggle('d-none', adminOnly.includes(btn.dataset.screen) && !['SUPER_ADMIN','ADMIN'].includes(role));
      });
    }, 0);
  }

  function ensureRoleOptions() {
    const select = document.querySelector('#userForm select[name="role"]');
    if (!select) return;
    select.innerHTML = ['ADMIN','OFFICER','VIEWER'].map(role => `<option value="${role}">${roleLabel(role)}</option>`).join('');
  }

  async function loadAdminUsers() {
    if (!document.querySelector('#userRows')) return;
    App.users = App.users || { page: 1, pageSize: 20, search: '' };
    const data = await api('/api/users?' + new URLSearchParams(App.users));
    if (typeof window.renderUserRowsSprint8 === 'function') window.renderUserRowsSprint8(data);
    else document.querySelector('#userRows').innerHTML = data.items.map(row => `<tr><td>${escapeHtml(row.email)}</td><td>${escapeHtml(row.display_name)}</td><td>${roleLabel(row.role)}</td><td>${statusLabel(row.status)}</td><td>${escapeHtml(row.last_login_at || '')}</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="openUserForm(${row.id})">Sá»­a</button> <button class="btn btn-sm btn-outline-warning" onclick="toggleUser(${row.id}, '${row.status === 'ACTIVE' ? 'lock' : 'unlock'}')">${row.status === 'ACTIVE' ? 'KhÃ³a' : 'Má»Ÿ khÃ³a'}</button> <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${row.id})">XÃ³a</button></td></tr>`).join('') || emptyRow(6, 'ChÆ°a cÃ³ ngÆ°á»i dÃ¹ng');
    renderPager('#userPager', data, page => { App.users.page = page; loadAdminUsers(); });
  }

  async function loadAdminLogs() {
    if (!document.querySelector('#logRows')) return;
    App.logs = App.logs || { page: 1, pageSize: 50, search: '' };
    const data = await api('/api/logs?' + new URLSearchParams(App.logs));
    document.querySelector('#logRows').innerHTML = data.items.map(row => `<tr><td>${escapeHtml(row.created_at)}</td><td>${escapeHtml(row.actor_email || '')}</td><td>${escapeHtml(row.module)}</td><td>${escapeHtml(row.action)}</td><td>${escapeHtml(row.message)}</td></tr>`).join('') || emptyRow(5, 'ChÆ°a cÃ³ nháº­t kÃ½');
    renderPager('#logPager', data, page => { App.logs.page = page; loadAdminLogs(); });
  }

  async function loadAdminBackups() {
    if (!document.querySelector('#backupRows')) return;
    App.backups = App.backups || { page: 1, pageSize: 20 };
    const data = await api('/api/backups?' + new URLSearchParams(App.backups));
    document.querySelector('#backupRows').innerHTML = data.items.map(row => `<tr><td>${escapeHtml(row.created_at)}</td><td>${escapeHtml(row.file_name)}</td><td>${number(row.file_size || 0)} byte</td><td>${escapeHtml(row.status)}</td><td>${escapeHtml(row.created_by_email || '')}</td></tr>`).join('') || emptyRow(5, 'ChÆ°a cÃ³ báº£n sao lÆ°u');
    renderPager('#backupPager', data, page => { App.backups.page = page; loadAdminBackups(); });
  }

  function statusLabel(status) { return status === 'ACTIVE' ? 'Hoáº¡t Ä‘á»™ng' : status === 'INACTIVE' ? 'ÄÃ£ khÃ³a' : status; }
})();

