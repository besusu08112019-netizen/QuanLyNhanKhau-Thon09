(() => {
  const legacyShowPerson = window.showPerson;
  const sectionOptions = {
    household: [
      ['front_house', 'PHOTO', t('Anh mat tien nha')],
      ['inside_house', 'PHOTO', t('Anh ben trong nha')],
      ['auxiliary_work', 'PHOTO', t('Anh cong trinh phu')],
      ['household_video', 'VIDEO', 'Video'],
      ['household_pdf', 'DOCUMENT', t('Tai lieu PDF')],
      ['household_word', 'WORD', 'File Word'],
      ['household_excel', 'EXCEL', 'File Excel'],
      ['land_use_rights', 'DOCUMENT', t('Ho so quyen su dung dat')],
      ['building_permit', 'DOCUMENT', t('Giay phep xay dung')],
      ['electric_contract', 'DOCUMENT', t('Hop dong dien')],
      ['water_contract', 'DOCUMENT', t('Hop dong nuoc')],
      ['internet_contract', 'DOCUMENT', t('Hop dong Internet')],
      ['meeting_minutes', 'DOCUMENT', t('Bien ban hop')],
      ['household_document', 'DOCUMENT', t('Cac giay to khac')],
    ],
    citizen: [
      ['portrait', 'PHOTO', t('Anh chan dung')],
      ['cccd_front', 'IMAGE', t('CCCD mat truoc')],
      ['cccd_back', 'IMAGE', t('CCCD mat sau')],
      ['birth_certificate', 'DOCUMENT', t('Giay khai sinh')],
      ['passport', 'DOCUMENT', t('Ho chieu')],
      ['driver_license', 'DOCUMENT', t('Giay phep lai xe')],
      ['health_insurance', 'DOCUMENT', t('The BHYT')],
      ['party_profile', 'DOCUMENT', t('Ho so Dang')],
      ['youth_union_profile', 'DOCUMENT', t('Ho so Doan')],
      ['military_profile', 'DOCUMENT', t('Ho so Nghia vu quan su')],
      ['degree_certificate', 'DOCUMENT', t('Van bang, chung chi')],
      ['citizen_pdf', 'DOCUMENT', 'File PDF'],
      ['citizen_word', 'WORD', 'File Word'],
      ['citizen_excel', 'EXCEL', 'File Excel'],
      ['citizen_image', 'IMAGE', t('Hinh anh')],
      ['citizen_video', 'VIDEO', 'Video'],
      ['citizen_document', 'DOCUMENT', t('Giay to lien quan')],
    ],
  };

  function $(selector, root = document) { return root.querySelector(selector); }
  function $$(selector, root = document) { return Array.from(root.querySelectorAll(selector)); }
  function esc(value) { return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char])); }
  function t(value, fallback) { return window.AppI18n && typeof window.AppI18n.text === 'function' ? window.AppI18n.text(value, fallback) : (fallback || String(value || '')); }
  function hasValue(value) { return value !== null && value !== undefined && String(value).trim() !== ''; }
  function dateText(value) { if (!hasValue(value)) return ''; const date = new Date(String(value).replace(' ', 'T')); return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('vi-VN'); }
  function show(message, type = 'success') { if (typeof showToast === 'function') showToast(message, type); }
  function can(module, action) { return typeof window.thon09CanAccess === 'function' ? window.thon09CanAccess(module, action) : false; }
  function requirePermission(module, action) {
    if (can(module, action)) return true;
    show('Tài khoản hiện tại không có quyền thực hiện thao tác này', 'warning');
    return false;
  }
  function applyProfilePermissions(root) {
    if (!root) return;
    $$('[data-file-manager-upload],[data-profile-upload]', root).forEach(el => { if (!can('file', 'upload')) el.remove(); });
    $$('[data-preview-file]', root).forEach(el => { if (!can('file', 'read')) el.remove(); });
    $$('[data-download-file]', root).forEach(el => { if (!can('file', 'download')) el.remove(); });
    $$('[data-edit-file]', root).forEach(el => { if (!can('file', 'update')) el.remove(); });
    $$('[data-delete-file]', root).forEach(el => { if (!can('file', 'delete')) el.remove(); });
    $$('[data-profile-note]', root).forEach(el => { if (!can('profile', 'create')) el.remove(); });
    $$('[data-edit-note]', root).forEach(el => { if (!can('profile', 'update')) el.remove(); });
    $$('[data-delete-note]', root).forEach(el => { if (!can('profile', 'delete')) el.remove(); });
  }

  async function loadProfile(type, id) {
    const normalized = type === 'persons' ? 'citizen' : type;
    return api('/api/profiles/' + normalized + '/' + encodeURIComponent(id));
  }

  window.showHousehold = async function showHouseholdDigitalProfile(id) {
    try {
      const profile = await loadProfile('household', id);
      $('#detailTitle').textContent = 'Hồ sơ số hộ gia đình';
      $('#detailBody').innerHTML = renderProfile(profile);
      bindProfileActions(profile);
      App.modals.detail.show();
    } catch (error) { show(error.message, 'danger'); }
  };

  window.showPerson = async function showPersonDigitalProfile(id) {
    try {
      const row = await api('/api/persons/' + encodeURIComponent(id));
      $('#detailTitle').textContent = 'Chi tiết nhân khẩu';
      $('#detailBody').innerHTML = renderCitizenTabbedProfile(row);
      bindCitizenTabs(Number(row.id || id));
      refreshUiEnhancements($('#detailBody') || document);
      App.modals.detail.show();
    } catch (error) {
      if (typeof legacyShowPerson === 'function') return legacyShowPerson(id);
      show(error.message, 'danger');
    }
  };

  function renderCitizenTabbedProfile(row) {
    const id = Number(row?.id || 0);
    const infoHtml = typeof renderDynamicPersonDetail === 'function'
      ? renderDynamicPersonDetail(row)
      : '<div class="text-muted small">Không tải được thông tin nhân khẩu.</div>';
    return '<div class="citizen-profile-tabs" data-citizen-profile="' + id + '">'
      + '<ul class="nav nav-tabs mb-3" role="tablist">'
      + '<li class="nav-item" role="presentation"><button class="nav-link active" type="button" data-profile-tab="info">Thông tin</button></li>'
      + '<li class="nav-item" role="presentation"><button class="nav-link" type="button" data-profile-tab="files">Hồ sơ số</button></li>'
      + '<li class="nav-item" role="presentation"><button class="nav-link" type="button" data-profile-tab="timeline">Timeline</button></li>'
      + '</ul>'
      + '<section data-profile-pane="info">' + infoHtml + '</section>'
      + '<section data-profile-pane="files" class="d-none"><div class="text-muted small py-3">Chọn tab Hồ sơ số để tải tài liệu.</div></section>'
      + '<section data-profile-pane="timeline" class="d-none"><div class="text-muted small py-3">Chọn tab Timeline để tải lịch sử.</div></section>'
      + '</div>';
  }

  function bindCitizenTabs(id) {
    const root = $('[data-citizen-profile]');
    if (!root) return;
    $$('[data-profile-tab]', root).forEach(button => {
      button.addEventListener('click', () => {
        const tab = button.dataset.profileTab;
        $$('[data-profile-tab]', root).forEach(item => item.classList.toggle('active', item === button));
        $$('[data-profile-pane]', root).forEach(pane => pane.classList.toggle('d-none', pane.dataset.profilePane !== tab));
        if (tab === 'files') loadCitizenFileManager(id, root);
        if (tab === 'timeline') loadCitizenTimeline(id, root);
      });
    });
  }

  async function loadCitizenFileManager(id, root, force = false) {
    const pane = $('[data-profile-pane="files"]', root);
    if (!pane || (pane.dataset.loaded === '1' && !force)) return;
    pane.innerHTML = '<div class="text-muted small py-3">Đang tải hồ sơ số...</div>';
    try {
      const files = await api('/api/files?' + new URLSearchParams({ module: 'citizen', entityId: String(id) }).toString());
      pane.innerHTML = renderCitizenFileManager(Array.isArray(files) ? files : []);
      pane.dataset.loaded = '1';
      bindFileManagerActions(pane, id, root);
    } catch (error) {
      pane.innerHTML = '<div class="alert alert-danger py-2">' + esc(error.message) + '</div>';
    }
  }

  function renderCitizenFileManager(files) {
    const grouped = groupFiles(files);
    return '<div class="digital-file-manager">'
      + sectionOptions.citizen.map(([section, fileType, label]) => renderFileGroup(section, fileType, label, grouped[section] || [])).join('')
      + '</div>';
  }

  function renderFileGroup(section, fileType, label, files) {
    return '<section class="border rounded mb-2 p-2" data-file-group="' + esc(section) + '">'
      + '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">'
      + '<h6 class="mb-0">' + esc(label) + '</h6>'
      + '<form class="d-flex flex-wrap gap-2 align-items-center" data-file-manager-upload data-section="' + esc(section) + '" data-file-type="' + esc(fileType) + '">'
      + '<input class="form-control form-control-sm" type="file" name="file" multiple required>'
      + '<input class="form-control form-control-sm" type="text" name="description" placeholder="Mô tả">'
      + '<button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-upload"></i> Upload</button>'
      + '</form></div>'
      + (files.length ? '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Tên file</th><th>Dung lượng</th><th>Ngày upload</th><th>Người upload</th><th class="text-end">Thao tác</th></tr></thead><tbody>'
        + files.map(file => '<tr><td>' + esc(file.original_name || file.file_name || 'Tệp đính kèm') + '</td><td>' + esc(formatSize(file.file_size)) + '</td><td>' + esc(dateText(file.created_at)) + '</td><td>' + esc(file.created_by_name || file.created_by_email || file.created_by || '') + '</td><td class="text-end"><div class="btn-group btn-group-sm"><button class="btn btn-outline-primary" type="button" data-preview-file="' + Number(file.id || 0) + '"><i class="fa-solid fa-eye"></i></button><button class="btn btn-outline-secondary" type="button" data-download-file="' + Number(file.id || 0) + '"><i class="fa-solid fa-download"></i></button><button class="btn btn-outline-primary" type="button" data-edit-file="' + Number(file.id || 0) + '" data-file-name="' + esc(file.file_name || file.original_name || '') + '" data-file-description="' + esc(file.description || '') + '"><i class="fa-solid fa-pen"></i></button><button class="btn btn-outline-danger" type="button" data-delete-file="' + Number(file.id || 0) + '"><i class="fa-solid fa-trash"></i></button></div></td></tr>').join('')
        + '</tbody></table></div>' : '<div class="text-muted small">Chưa có file.</div>')
      + '</section>';
  }

  function groupFiles(files) {
    return files.reduce((acc, file) => {
      const section = file.profile_section || file.category || file.file_type || 'citizen_document';
      (acc[section] ||= []).push(file);
      return acc;
    }, {});
  }

  function bindFileManagerActions(pane, id, root) {
    applyProfilePermissions(pane);
    $$('[data-file-manager-upload]', pane).forEach(form => form.addEventListener('submit', event => uploadCitizenFile(event, id, root)));
    $$('[data-preview-file]', pane).forEach(btn => btn.addEventListener('click', () => previewFile(btn.dataset.previewFile)));
    $$('[data-download-file]', pane).forEach(btn => btn.addEventListener('click', () => downloadFile(btn.dataset.downloadFile)));
    $$('[data-delete-file]', pane).forEach(btn => btn.addEventListener('click', () => deleteCitizenFile(btn.dataset.deleteFile, id, root)));
    $$('[data-edit-file]', pane).forEach(btn => btn.addEventListener('click', () => editCitizenFile(btn, id, root)));
  }

  async function uploadCitizenFile(event, id, root) {
    event.preventDefault();
    if (!requirePermission('file', 'upload')) return;
    const form = event.currentTarget;
    const file = form.elements.file.files[0];
    if (!file) return show('Vui lòng chọn file', 'warning');
    const data = new FormData();
    data.append('module', 'citizen');
    data.append('entityId', id);
    data.append('profileSection', form.dataset.section || 'citizen_document');
    data.append('fileType', form.dataset.fileType || 'DOCUMENT');
    data.append('description', form.elements.description.value || '');
    Array.from(form.elements.file.files || []).forEach(item => data.append('file[]', item));
    await api('/api/files', { method: 'POST', body: data });
    show('Đã tải lên tài liệu');
    await loadCitizenFileManager(id, root, true);
    const timelinePane = $('[data-profile-pane="timeline"]', root);
    if (timelinePane) timelinePane.dataset.loaded = '0';
  }

  async function editCitizenFile(button, id, root) {
    if (!requirePermission('file', 'update')) return;
    const fileName = prompt('Ten file', button.dataset.fileName || '');
    if (fileName === null) return;
    const description = prompt('Mo ta', button.dataset.fileDescription || '');
    if (description === null) return;
    await api('/api/files/' + encodeURIComponent(button.dataset.editFile), { method: 'PUT', body: { file_name: fileName, original_name: fileName, description } });
    show('Da cap nhat file dinh kem');
    await loadCitizenFileManager(id, root, true);
    const timelinePane = $('[data-profile-pane="timeline"]', root);
    if (timelinePane) timelinePane.dataset.loaded = '0';
  }

  async function editFile(button, type, id) {
    if (!requirePermission('file', 'update')) return;
    const fileName = prompt('Ten file', button.dataset.fileName || '');
    if (fileName === null) return;
    const description = prompt('Mo ta', button.dataset.fileDescription || '');
    if (description === null) return;
    await api('/api/files/' + encodeURIComponent(button.dataset.editFile), { method: 'PUT', body: { file_name: fileName, original_name: fileName, description } });
    show('Da cap nhat file dinh kem');
    type === 'household' ? window.showHousehold(id) : window.showPerson(id);
  }

  async function deleteCitizenFile(fileId, id, root) {
    if (!requirePermission('file', 'delete')) return;
    if (!confirm('Xóa file đính kèm này?')) return;
    await api('/api/files/' + encodeURIComponent(fileId), { method: 'DELETE' });
    show('Đã xóa file đính kèm');
    await loadCitizenFileManager(id, root, true);
    const timelinePane = $('[data-profile-pane="timeline"]', root);
    if (timelinePane) timelinePane.dataset.loaded = '0';
  }

  async function loadCitizenTimeline(id, root, force = false) {
    const pane = $('[data-profile-pane="timeline"]', root);
    if (!pane || (pane.dataset.loaded === '1' && !force)) return;
    pane.innerHTML = '<div class="text-muted small py-3">Đang tải timeline...</div>';
    try {
      const items = await api('/api/profiles/timeline/citizen/' + encodeURIComponent(id));
      pane.innerHTML = renderTimeline(Array.isArray(items) ? items : []);
      pane.dataset.loaded = '1';
    } catch (error) {
      pane.innerHTML = '<div class="alert alert-danger py-2">' + esc(error.message) + '</div>';
    }
  }

  function renderProfile(profile) {
    const type = profile.type;
    const entityId = Number(profile.profile?.id || 0);
    return '<div class="digital-profile" data-profile-type="' + esc(type) + '" data-profile-id="' + entityId + '">'
      + renderHeader(profile)
      + renderQuickLinks(profile)
      + renderSections(profile.sections || {})
      + (type === 'household' ? renderMembers(profile.members || []) : renderFamily(profile.family || []))
      + renderFiles(type, profile.files || [])
      + renderNotes(profile.notes || [])
      + renderTimeline(profile.timeline || [])
      + renderAuditLogs(profile.logs || [])
      + '</div>';
  }

  function renderHeader(profile) {
    const row = profile.profile || {};
    const title = profile.type === 'household' ? (row.household_code || 'Hộ gia đình') : (row.full_name || 'Nhân khẩu');
    const subtitle = profile.type === 'household' ? (row.head_citizen_name || row.address || '') : [row.citizen_code, row.identity_number].filter(Boolean).join(' - ');
    return '<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3"><div><div class="text-muted small">Hồ sơ số</div><h4 class="mb-1">' + esc(title) + '</h4><div class="text-muted">' + esc(subtitle) + '</div></div><div class="btn-group btn-group-sm" role="group"><button class="btn btn-outline-primary" type="button" data-profile-refresh><i class="fa-solid fa-rotate-right"></i> Tải lại</button><button class="btn btn-outline-secondary" type="button" data-profile-print><i class="fa-solid fa-print"></i> In</button></div></div>';
  }

  function renderQuickLinks(profile) {
    const type = profile.type;
    const householdId = Number(profile.household?.id || profile.profile?.household_id || 0);
    const buttons = [];
    if (type === 'household') {
      buttons.push(['gis', 'fa-map-location-dot', 'GIS']);
      buttons.push(['members', 'fa-users', 'Nhân khẩu']);
    } else {
      if (householdId > 0) buttons.push(['household:' + householdId, 'fa-house', 'Hồ sơ hộ']);
      buttons.push(['movements', 'fa-right-left', 'Biến động']);
    }
    buttons.push(['files', 'fa-paperclip', 'File đính kèm']);
    return '<div class="d-flex flex-wrap gap-2 mb-3">' + buttons.map(btn => '<button class="btn btn-sm btn-outline-secondary" type="button" data-profile-link="' + esc(btn[0]) + '"><i class="fa-solid ' + btn[1] + '"></i> ' + esc(btn[2]) + '</button>').join('') + '</div>';
  }

  function renderSections(sections) {
    return Object.entries(sections).map(([key, rows]) => {
      if (!Array.isArray(rows) || !rows.length) return '';
      return '<section class="mb-3"><h6 class="border-bottom pb-2 mb-2">' + sectionTitle(key) + '</h6><div class="row g-2">'
        + rows.map(item => '<div class="col-md-6"><div class="border rounded p-2 h-100"><div class="text-muted small">' + esc(item.label) + '</div><div class="fw-semibold">' + esc(item.value) + '</div></div></div>').join('')
        + '</div></section>';
    }).join('');
  }

  function renderMembers(members) {
    if (!members.length) return '';
    return '<section class="mb-3" id="digitalProfileMembers"><h6 class="border-bottom pb-2 mb-2">Danh sách nhân khẩu</h6><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Mã NK</th><th>Họ tên</th><th>Ngày sinh</th><th>Quan hệ</th><th></th></tr></thead><tbody>'
      + members.map(row => '<tr><td>' + esc(row.citizen_code || '') + '</td><td>' + esc(row.full_name || '') + '</td><td>' + esc(row.date_of_birth || '') + '</td><td>' + esc(row.relationship || '') + '</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" type="button" data-open-citizen="' + Number(row.id || 0) + '">Xem</button></td></tr>').join('')
      + '</tbody></table></div></section>';
  }

  function renderFamily(family) {
    if (!family.length) return '';
    return '<section class="mb-3"><h6 class="border-bottom pb-2 mb-2">Người cùng hộ</h6><div class="table-responsive"><table class="table table-sm align-middle"><tbody>'
      + family.map(row => '<tr><td>' + esc(row.full_name || '') + '</td><td>' + esc(row.relationship || '') + '</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" type="button" data-open-citizen="' + Number(row.id || 0) + '">Xem</button></td></tr>').join('')
      + '</tbody></table></div></section>';
  }

  function renderFiles(type, files) {
    const options = (sectionOptions[type] || []).map(([section, fileType, label]) => '<option value="' + section + '" data-file-type="' + fileType + '">' + esc(label) + '</option>').join('');
    return '<section class="mb-3" id="digitalProfileFiles"><h6 class="border-bottom pb-2 mb-2">Tài liệu đính kèm</h6>'
      + '<form class="row g-2 align-items-end mb-2" data-profile-upload><div class="col-md-3"><label class="form-label small">Loại tài liệu</label><select name="profileSection" class="form-select form-select-sm">' + options + '</select></div><div class="col-md-4"><label class="form-label small">Mô tả</label><input name="description" class="form-control form-control-sm"></div><div class="col-md-3"><label class="form-label small">File</label><input name="file" type="file" class="form-control form-control-sm" multiple required></div><div class="col-md-2"><button class="btn btn-sm btn-primary w-100" type="submit"><i class="fa-solid fa-upload"></i> Tải lên</button></div></form>'
      + (files.length ? '<div class="list-group list-group-flush border rounded">' + files.map(file => '<div class="list-group-item d-flex justify-content-between gap-2 align-items-start"><div><div class="fw-semibold">' + esc(file.original_name || 'Tệp đính kèm') + '</div><div class="small text-muted">' + esc(file.profile_section || file.file_type || '') + (file.description ? ' - ' + esc(file.description) : '') + '</div></div><div class="btn-group btn-group-sm"><button class="btn btn-outline-primary" type="button" data-preview-file="' + Number(file.id || 0) + '"><i class="fa-solid fa-eye"></i></button><button class="btn btn-outline-secondary" type="button" data-download-file="' + Number(file.id || 0) + '"><i class="fa-solid fa-download"></i></button><button class="btn btn-outline-primary" type="button" data-edit-file="' + Number(file.id || 0) + '" data-file-name="' + esc(file.file_name || file.original_name || '') + '" data-file-description="' + esc(file.description || '') + '"><i class="fa-solid fa-pen"></i></button><button class="btn btn-outline-danger" type="button" data-delete-file="' + Number(file.id || 0) + '"><i class="fa-solid fa-trash"></i></button></div></div>').join('') + '</div>' : '<div class="text-muted small">Chưa có tài liệu đính kèm.</div>')
      + '</section>';
  }

  function renderNotes(notes) {
    return '<section class="mb-3"><h6 class="border-bottom pb-2 mb-2">Ghi chú nghiệp vụ</h6>'
      + '<form class="row g-2 mb-2" data-profile-note><div class="col-md-3"><input name="title" class="form-control form-control-sm" placeholder="Tiêu đề"></div><div class="col-md-7"><input name="content" class="form-control form-control-sm" placeholder="Nội dung ghi chú" required></div><div class="col-md-2"><button class="btn btn-sm btn-primary w-100" type="submit">Thêm</button></div></form>'
      + (notes.length ? '<div class="list-group list-group-flush border rounded">' + notes.map(note => '<div class="list-group-item d-flex justify-content-between gap-2"><div><div class="fw-semibold">' + esc(note.title || 'Ghi chú') + '</div><div>' + esc(note.content || '') + '</div><div class="small text-muted">' + esc(note.created_by_name || note.created_by_email || '') + ' - ' + dateText(note.created_at) + '</div></div><div class="btn-group btn-group-sm align-self-start"><button class="btn btn-outline-primary" type="button" data-edit-note="' + Number(note.id || 0) + '" data-note-title="' + esc(note.title || '') + '" data-note-content="' + esc(note.content || '') + '"><i class="fa-solid fa-pen"></i></button><button class="btn btn-outline-danger" type="button" data-delete-note="' + Number(note.id || 0) + '"><i class="fa-solid fa-trash"></i></button></div></div>').join('') + '</div>' : '<div class="text-muted small">Chưa có ghi chú nghiệp vụ.</div>')
      + '</section>';
  }

  function renderTimeline(items) {
    return '<section class="mb-3"><h6 class="border-bottom pb-2 mb-2">Timeline lịch sử</h6>'
      + (items.length ? '<div class="list-group list-group-flush border rounded">' + items.map(item => '<div class="list-group-item"><div class="d-flex justify-content-between gap-2"><strong>' + esc(item.title || item.type || '') + '</strong><span class="small text-muted">' + dateText(item.time) + '</span></div><div class="small text-muted">' + esc(timelineActor(item)) + '</div>' + (item.description ? '<div>' + esc(item.description) + '</div>' : '') + '</div>').join('') + '</div>' : '<div class="text-muted small">Chưa có lịch sử.</div>')
      + '</section>';
  }

  function renderAuditLogs(logs) {
    if (!logs.length) return '';
    return '<section class="mb-1"><h6 class="border-bottom pb-2 mb-2">Nhật ký thao tác</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Thời gian</th><th>Người thực hiện</th><th>Thao tác</th><th>Nội dung</th></tr></thead><tbody>'
      + logs.map(log => '<tr><td>' + dateText(log.created_at) + '</td><td>' + esc(log.actor_email || '') + '</td><td>' + esc(log.action || '') + '</td><td>' + esc(log.message || '') + '</td></tr>').join('')
      + '</tbody></table></div></section>';
  }

  function bindProfileActions(profile) {
    const root = $('.digital-profile');
    if (!root) return;
    const type = root.dataset.profileType;
    const id = Number(root.dataset.profileId || 0);
    $('[data-profile-refresh]', root)?.addEventListener('click', () => type === 'household' ? window.showHousehold(id) : window.showPerson(id));
    $('[data-profile-print]', root)?.addEventListener('click', () => window.print());
    $$('[data-open-citizen]', root).forEach(btn => btn.addEventListener('click', () => window.showPerson(btn.dataset.openCitizen)));
    $$('[data-preview-file]', root).forEach(btn => btn.addEventListener('click', () => previewFile(btn.dataset.previewFile)));
    $$('[data-download-file]', root).forEach(btn => btn.addEventListener('click', () => downloadFile(btn.dataset.downloadFile)));
    $$('[data-delete-file]', root).forEach(btn => btn.addEventListener('click', () => deleteFile(btn.dataset.deleteFile, type, id)));
    $$('[data-edit-file]', root).forEach(btn => btn.addEventListener('click', () => editFile(btn, type, id)));
    $$('[data-edit-note]', root).forEach(btn => btn.addEventListener('click', () => editNote(btn, type, id)));
    $$('[data-delete-note]', root).forEach(btn => btn.addEventListener('click', () => deleteNote(btn.dataset.deleteNote, type, id)));
    $('[data-profile-upload]', root)?.addEventListener('submit', event => uploadFile(event, type, id));
    $('[data-profile-note]', root)?.addEventListener('submit', event => createNote(event, type, id));
    $$('[data-profile-link]', root).forEach(btn => btn.addEventListener('click', () => openLink(btn.dataset.profileLink)));
    hydrateAuthPreviews(root);
  }

  async function uploadFile(event, type, id) {
    event.preventDefault();
    if (!requirePermission('file', 'upload')) return;
    const form = event.currentTarget;
    const data = new FormData();
    const section = form.elements.profileSection.value;
    const option = form.elements.profileSection.selectedOptions[0];
    data.append('module', type);
    data.append('entityId', id);
    data.append('profileSection', section);
    data.append('fileType', option?.dataset.fileType || 'OTHER');
    data.append('description', form.elements.description.value || '');
    Array.from(form.elements.file.files || []).forEach(item => data.append('file[]', item));
    await api('/api/files', { method: 'POST', body: data });
    show('Đã tải lên tài liệu');
    type === 'household' ? window.showHousehold(id) : window.showPerson(id);
  }

  async function createNote(event, type, id) {
    event.preventDefault();
    if (!requirePermission('profile', 'create')) return;
    const form = event.currentTarget;
    await api('/api/profiles/' + type + '/' + id + '/notes', { method: 'POST', body: { title: form.elements.title.value, content: form.elements.content.value, section: 'general' } });
    show('Đã thêm ghi chú');
    type === 'household' ? window.showHousehold(id) : window.showPerson(id);
  }

  async function editNote(button, type, id) {
    if (!requirePermission('profile', 'update')) return;
    const title = prompt('Tiêu đề ghi chú', button.dataset.noteTitle || 'Ghi chú nghiệp vụ');
    if (title === null) return;
    const content = prompt('Nội dung ghi chú', button.dataset.noteContent || '');
    if (content === null) return;
    if (!content.trim()) return show('Nội dung ghi chú là bắt buộc', 'warning');
    await api('/api/profiles/notes/' + encodeURIComponent(button.dataset.editNote), { method: 'PUT', body: { title, content, section: 'general' } });
    show('Đã sửa ghi chú');
    type === 'household' ? window.showHousehold(id) : window.showPerson(id);
  }

  async function deleteNote(noteId, type, id) {
    if (!requirePermission('profile', 'delete')) return;
    if (!confirm('Xóa ghi chú này?')) return;
    await api('/api/profiles/notes/' + encodeURIComponent(noteId), { method: 'DELETE' });
    show('Đã xóa ghi chú');
    type === 'household' ? window.showHousehold(id) : window.showPerson(id);
  }

  async function deleteFile(id, type, entityId) {
    if (!requirePermission('file', 'delete')) return;
    if (!confirm('Xóa file đính kèm này?')) return;
    await api('/api/files/' + encodeURIComponent(id), { method: 'DELETE' });
    show('Đã xóa file đính kèm');
    type === 'household' ? window.showHousehold(entityId) : window.showPerson(entityId);
  }

  async function previewFile(id) {
    if (!requirePermission('file', 'read')) return;
    try {
      const url = await loadPreviewBlob(id);
      window.open(url, '_blank', 'noopener');
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (error) {
      show('Kh\u00F4ng xem tr\u01B0\u1EDBc \u0111\u01B0\u1EE3c file', 'danger');
    }
  }
  window.thon09PreviewFile = previewFile;

  async function downloadFile(id) {
    if (!requirePermission('file', 'download')) return;
    const response = await fetch('/api/files/' + encodeURIComponent(id) + '/download', { headers: { Authorization: 'Bearer ' + App.token }, cache: 'no-store' });
    if (!response.ok) return show('Không tải được file', 'danger');
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

  function openLink(link) {
    if (link === 'gis' && typeof window.switchScreen === 'function') return window.switchScreen('gis');
    if (link === 'members') return document.getElementById('digitalProfileMembers')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (link === 'files') return document.getElementById('digitalProfileFiles')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (link === 'movements' && typeof window.switchScreen === 'function') return window.switchScreen('movements');
    if (link.startsWith('household:')) return window.showHousehold(link.split(':')[1]);
  }

  function formatSize(bytes) {
    const size = Number(bytes || 0);
    if (!size) return '';
    if (size < 1024) return size + ' B';
    if (size < 1024 * 1024) return (size / 1024).toFixed(1) + ' KB';
    return (size / 1024 / 1024).toFixed(1) + ' MB';
  }

  function timelineActor(item) {
    const data = item?.data || {};
    return data.actor_email || data.created_by_name || data.created_by_email || data.updated_by_name || data.updated_by_email || item?.description || '';
  }

  function sectionTitle(key) {
    return ({ general: 'Thông tin chung', statistics: 'Thống kê', basic: 'Thông tin cơ bản', residence: 'Cư trú', personal: 'Thông tin cá nhân', administrative: 'Hành chính' })[key] || key;
  }
  window.showHousehold = async function showHouseholdSprint12(id) {
    try {
      const profile = await loadProfile('household', id);
      const entityId = Number(profile.profile?.id || id || 0);
      profile.businessActivities = await loadHouseholdBusinessActivities(entityId);
      $('#detailTitle').textContent = t('Ho so so ho gia dinh');
      $('#detailBody').innerHTML = renderHouseholdTabbedProfile(profile, entityId);
      bindHouseholdTabs(profile, entityId);
      refreshUiEnhancements($('#detailBody') || document);
      App.modals.detail.show();
    } catch (error) { show(error.message, 'danger'); }
  };

  async function loadHouseholdBusinessActivities(id) {
    try {
      const data = await api('/api/household-business/household/' + encodeURIComponent(id));
      return Array.isArray(data?.items) ? data.items : (Array.isArray(data) ? data : []);
    } catch (error) {
      console.warn('[household-business-activities]', error);
      return [];
    }
  }

  function renderHouseholdTabbedProfile(profile, id) {
    return '<div class="household-profile-tabs" data-household-profile="' + id + '">'
      + '<ul class="nav nav-tabs mb-3" role="tablist">'
      + '<li class="nav-item"><button class="nav-link active" type="button" data-household-tab="info">' + t('Thong tin') + '</button></li>'
      + '<li class="nav-item"><button class="nav-link" type="button" data-household-tab="files">' + t('Ho so so') + '</button></li>'
      + '<li class="nav-item"><button class="nav-link" type="button" data-household-tab="gallery">' + t('Thu vien anh') + '</button></li>'
      + '<li class="nav-item"><button class="nav-link" type="button" data-household-tab="video">Video</button></li>'
      + '<li class="nav-item"><button class="nav-link" type="button" data-household-tab="gps">GPS</button></li>'
      + '<li class="nav-item"><button class="nav-link" type="button" data-household-tab="timeline">Timeline</button></li>'
      + '<li class="nav-item"><button class="nav-link" type="button" data-household-tab="logs">' + t('Nhat ky') + '</button></li>'
      + '</ul>'
      + '<section data-household-pane="info">' + renderHouseholdInfo(profile) + '</section>'
      + '<section data-household-pane="files" class="d-none"><div class="text-muted small py-3">' + t('Chon tab de tai ho so so') + '</div></section>'
      + '<section data-household-pane="gallery" class="d-none"><div class="text-muted small py-3">' + t('Chon tab de tai thu vien anh') + '</div></section>'
      + '<section data-household-pane="video" class="d-none"><div class="text-muted small py-3">' + t('Chon tab de tai video') + '</div></section>'
      + '<section data-household-pane="gps" class="d-none">' + renderHouseholdGps(profile) + '</section>'
      + '<section data-household-pane="timeline" class="d-none"><div class="text-muted small py-3">' + t('Chon tab de tai timeline') + '</div></section>'
      + '<section data-household-pane="logs" class="d-none">' + renderAuditLogs(profile.logs || []) + '</section>'
      + '</div>';
  }

  function householdPrimaryPhoto(profile) {
    const row = profile?.profile || {};
    const files = Array.isArray(profile?.files) ? profile.files : [];
    const photo = files.find(file => {
      const type = String(file.file_type || file.fileType || '').toUpperCase();
      const mime = String(file.mime_type || file.mimeType || '').toLowerCase();
      return type === 'PHOTO' || type === 'IMAGE' || mime.startsWith('image/');
    });
    if (photo) {
      const id = Number(photo.id || 0);
      const url = photo.preview_url || photo.previewUrl || photo.thumbnail_url || photo.thumbnailUrl || photo.url || (id > 0 ? '/api/files/' + id + '/preview' : '');
      if (url || id > 0) return { id, url, name: photo.display_name || photo.original_name || photo.file_name || '\u1EA2nh h\u1ED9' };
    }
    const directUrl = row.household_photo_url || row.photo_url || row.thumbnail_url || row.image_url || '';
    const directId = Number(row.photo_file_id || row.thumbnail_file_id || 0);
    return directUrl || directId > 0 ? { id: directId, url: directUrl, name: row.household_code || '\u1EA2nh h\u1ED9' } : null;
  }

  function renderHouseholdPhoto(profile) {
    const photo = householdPrimaryPhoto(profile);
    if (!photo?.url && !photo?.id) return '';
    const image = photo.id ? '<img data-auth-preview="' + Number(photo.id) + '" alt="' + esc(photo.name || '\u1EA2nh h\u1ED9') + '" loading="lazy">' : '<img src="' + esc(photo.url) + '" alt="' + esc(photo.name || '\u1EA2nh h\u1ED9') + '" loading="lazy">';
    const action = photo.id ? '<button class="btn btn-sm btn-outline-primary" type="button" data-preview-file="' + Number(photo.id) + '">' + 'Xem \u1EA3nh' + '</button>' : '<a class="btn btn-sm btn-outline-primary" href="' + esc(photo.url) + '" target="_blank" rel="noopener">' + 'Xem \u1EA3nh' + '</a>';
    return '<div class="household-detail-photo mb-3">'
      + image
      + action
      + '</div>';
  }
  function renderHouseholdInfo(profile) {
    const row = profile.profile || {};
    if (typeof details === 'function' && typeof memberTable === 'function') {
      return renderHouseholdPhoto(profile) + details([
        [t('Ma ho'), row.household_code], [t('Chu ho'), row.head_citizen_name], [t('Dia chi'), row.address], [t('So dien thoai'), row.phone],
        [t('O nha'), row.at_home_count || 0], [t('Di vang'), row.away_count || 0], [t('Dien ho'), row.household_type || ''], [t('Ghi chu'), row.note]
      ]) + renderHouseholdBusinessActivities(profile.businessActivities || []) + memberTable(profile.members || []);
    }
    return renderHouseholdPhoto(profile) + renderSections(profile.sections || {}) + renderMembers(profile.members || []);
  }

  function renderHouseholdBusinessActivities(items) {
    if (!Array.isArray(items) || !items.length) return '';
    const rows = items.map((item, index) => '<tr>'
      + '<td>' + (index + 1) + '</td>'
      + '<td>' + esc(item.business_name || '') + '</td>'
      + '<td>' + esc(item.business_type_label || item.business_type || '') + '</td>'
      + '<td>' + esc(item.economic_type || item.sector_label || item.production_sector || item.business_sector || '') + '</td>'
      + '<td>' + esc(item.status_label || item.status || '') + '</td>'
      + '</tr>').join('');
    return '<section class="person-info-section mt-3"><div class="person-info-section-title"><i class="fa-solid fa-briefcase"></i><h4>Ho?t ??ng kinh t?</h4></div>'
      + '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>STT</th><th>T?n c? s?</th><th>Lo?i h?nh</th><th>Ng?nh ngh?</th><th>Tr?ng th?i</th></tr></thead><tbody>' + rows + '</tbody></table></div></section>';
  }

  function bindHouseholdTabs(profile, id) {
    const root = $('[data-household-profile]');
    if (!root) return;
    $$('[data-household-tab]', root).forEach(button => {
      button.addEventListener('click', () => {
        const tab = button.dataset.householdTab;
        $$('[data-household-tab]', root).forEach(item => item.classList.toggle('active', item === button));
        $$('[data-household-pane]', root).forEach(pane => pane.classList.toggle('d-none', pane.dataset.householdPane !== tab));
        if (tab === 'files') loadHouseholdFiles(id, root);
        if (tab === 'gallery') loadHouseholdGallery(id, root);
        if (tab === 'video') loadHouseholdVideos(id, root);
        if (tab === 'timeline') loadHouseholdTimeline(id, root);
      });
    });
    $$('[data-preview-file]', root).forEach(button => button.addEventListener('click', () => previewFile(button.dataset.previewFile)));
    $$('[data-gps-action]', root).forEach(button => button.addEventListener('click', () => handleGpsAction(button.dataset.gpsAction, profile.profile || {})));
    hydrateAuthPreviews(root);
  }

  async function fetchHouseholdFiles(id, extra = {}) {
    const params = new URLSearchParams({ module: 'household', entityId: String(id), page: String(extra.page || 1), pageSize: String(extra.pageSize || 24) });
    if (extra.search) params.set('search', extra.search);
    if (extra.fileType) params.set('fileType', extra.fileType);
    if (extra.category) params.set('category', extra.category);
    const result = await api('/api/files?' + params.toString());
    return Array.isArray(result) ? { items: result, total: result.length, page: 1, pageSize: result.length || 24 } : result;
  }

  async function loadHouseholdFiles(id, root, page = 1) {
    const pane = $('[data-household-pane="files"]', root);
    if (!pane || (pane.dataset.loaded === '1' && Number(pane.dataset.page || 1) === page)) return;
    pane.innerHTML = '<div class="text-muted small py-3">' + t('Dang tai tai lieu') + '</div>';
    try {
      const search = pane.dataset.search || '';
      const data = await fetchHouseholdFiles(id, { page, pageSize: 20, search });
      pane.innerHTML = renderHouseholdFilesPanel(data, search);
      pane.dataset.loaded = '1';
      pane.dataset.page = String(data.page || page);
      bindHouseholdFilePanel(pane, id, root);
    } catch (error) { pane.innerHTML = '<div class="alert alert-danger py-2">' + esc(error.message) + '</div>'; }
  }

  function renderHouseholdFilesPanel(data, search) {
    const files = data.items || [];
    const options = sectionOptions.household.map(([section, fileType, label]) => '<option value="' + esc(section) + '" data-file-type="' + esc(fileType) + '">' + esc(label) + '</option>').join('');
    return '<section class="mb-3" id="digitalProfileFiles"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom pb-2 mb-2"><h6 class="mb-0">' + t('Tai lieu dinh kem') + '</h6><div class="input-group input-group-sm" style="max-width:280px"><input class="form-control" data-household-file-search value="' + esc(search) + '" placeholder="' + t('Tim theo ten hoac loai') + '"><button class="btn btn-outline-secondary" type="button" data-household-file-search-btn><i class="fa-solid fa-magnifying-glass"></i></button></div></div>'
      + '<form class="row g-2 align-items-end mb-2" data-profile-upload><div class="col-md-3"><label class="form-label small">' + t('Loai tai lieu') + '</label><select name="profileSection" class="form-select form-select-sm">' + options + '</select></div><div class="col-md-4"><label class="form-label small">' + t('Mo ta') + '</label><input name="description" class="form-control form-control-sm"></div><div class="col-md-3"><label class="form-label small">File</label><input name="file" type="file" class="form-control form-control-sm" multiple required></div><div class="col-md-2"><button class="btn btn-sm btn-primary w-100" type="submit"><i class="fa-solid fa-upload"></i> ' + t('Tai len') + '</button></div></form>'
      + renderFileTable(files)
      + renderSimplePager(data, 'household-files')
      + '</section>';
  }

  function renderFileTable(files) {
    if (!files.length) return '<div class="text-muted small">' + t('Chua co tai lieu dinh kem') + '</div>';
    return '<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>' + t('Ten hien thi') + '</th><th>' + t('Loai') + '</th><th>' + t('Mo ta') + '</th><th>' + t('Phien ban') + '</th><th>' + t('Ngay tai len') + '</th><th>' + t('Nguoi tai len') + '</th><th>' + t('Dung luong') + '</th><th class="text-end">' + t('Thao tac') + '</th></tr></thead><tbody>'
      + files.map(file => '<tr><td>' + esc(file.display_name || file.original_name || file.file_name || '') + '</td><td>' + esc(sectionLabel(file.profile_section || file.category || file.file_type)) + '</td><td>' + esc(file.description || '') + '</td><td>' + esc(file.version || '') + '</td><td>' + esc(dateText(file.created_at)) + '</td><td>' + esc(file.created_by_name || file.created_by_email || file.created_by || '') + '</td><td>' + esc(formatSize(file.file_size)) + '</td><td class="text-end"><div class="btn-group btn-group-sm"><button class="btn btn-outline-primary" type="button" data-preview-file="' + Number(file.id || 0) + '"><i class="fa-solid fa-eye"></i></button><button class="btn btn-outline-secondary" type="button" data-download-file="' + Number(file.id || 0) + '"><i class="fa-solid fa-download"></i></button><button class="btn btn-outline-primary" type="button" data-edit-file="' + Number(file.id || 0) + '" data-file-name="' + esc(file.file_name || file.original_name || '') + '" data-file-description="' + esc(file.description || '') + '"><i class="fa-solid fa-pen"></i></button><button class="btn btn-outline-danger" type="button" data-delete-file="' + Number(file.id || 0) + '"><i class="fa-solid fa-trash"></i></button></div></td></tr>').join('')
      + '</tbody></table></div>';
  }

  function bindHouseholdFilePanel(pane, id, root) {
    $('[data-profile-upload]', pane)?.addEventListener('submit', event => uploadHouseholdFile(event, id, root));
    $('[data-household-file-search-btn]', pane)?.addEventListener('click', () => { pane.dataset.search = $('[data-household-file-search]', pane)?.value || ''; pane.dataset.loaded = '0'; loadHouseholdFiles(id, root, 1); });
    $('[data-household-file-search]', pane)?.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); $('[data-household-file-search-btn]', pane)?.click(); } });
    bindSharedFileButtons(pane, 'household', id, () => { pane.dataset.loaded = '0'; loadHouseholdFiles(id, root, Number(pane.dataset.page || 1)); });
    $$('[data-page-target="household-files"]', pane).forEach(btn => btn.addEventListener('click', () => { pane.dataset.loaded = '0'; loadHouseholdFiles(id, root, Number(btn.dataset.page || 1)); }));
  }

  async function uploadHouseholdFile(event, id, root) {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData();
    const option = form.elements.profileSection.selectedOptions[0];
    data.append('module', 'household');
    data.append('entityId', id);
    data.append('profileSection', form.elements.profileSection.value);
    data.append('fileType', option?.dataset.fileType || 'OTHER');
    data.append('description', form.elements.description.value || '');
    Array.from(form.elements.file.files || []).forEach(item => data.append('file[]', item));
    await api('/api/files', { method: 'POST', body: data });
    show(t('Da tai len tai lieu'));
    ['files','gallery','video','timeline'].forEach(name => { const pane = $('[data-household-pane="' + name + '"]', root); if (pane) pane.dataset.loaded = '0'; });
    loadHouseholdFiles(id, root, 1);
  }

  async function loadHouseholdGallery(id, root, page = 1) {
    const pane = $('[data-household-pane="gallery"]', root);
    if (!pane || (pane.dataset.loaded === '1' && Number(pane.dataset.page || 1) === page)) return;
    pane.innerHTML = '<div class="text-muted small py-3">' + t('Dang tai thu vien anh') + '</div>';
    try {
      const search = pane.dataset.search || '';
      const data = await fetchHouseholdFiles(id, { page, pageSize: 12, search, fileType: 'PHOTO' });
      const imageItems = (data.items || []).filter(isImageFile);
      pane.innerHTML = renderGalleryPanel(data, imageItems, search);
      pane.dataset.loaded = '1';
      pane.dataset.page = String(data.page || page);
      $('[data-gallery-search-btn]', pane)?.addEventListener('click', () => { pane.dataset.search = $('[data-gallery-search]', pane)?.value || ''; pane.dataset.loaded = '0'; loadHouseholdGallery(id, root, 1); });
      hydrateAuthPreviews(pane);
      $$('[data-gallery-index]', pane).forEach(btn => btn.addEventListener('click', () => openMediaLightbox(imageItems, Number(btn.dataset.galleryIndex || 0))));
      bindSharedFileButtons(pane, 'household', id, () => { pane.dataset.loaded = '0'; loadHouseholdGallery(id, root, Number(pane.dataset.page || 1)); });
      $$('[data-page-target="household-gallery"]', pane).forEach(btn => btn.addEventListener('click', () => { pane.dataset.loaded = '0'; loadHouseholdGallery(id, root, Number(btn.dataset.page || 1)); }));
    } catch (error) { pane.innerHTML = '<div class="alert alert-danger py-2">' + esc(error.message) + '</div>'; }
  }

  function renderGalleryPanel(data, files, search) {
    return '<section class="mb-3"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom pb-2 mb-2"><h6 class="mb-0">' + t('Thu vien anh') + '</h6><div class="input-group input-group-sm" style="max-width:280px"><input class="form-control" data-gallery-search value="' + esc(search) + '" placeholder="' + t('Tim theo ten hoac loai anh') + '"><button class="btn btn-outline-secondary" type="button" data-gallery-search-btn><i class="fa-solid fa-magnifying-glass"></i></button></div></div>'
      + (files.length ? '<div class="row g-2">' + files.map((file, index) => '<div class="col-6 col-md-4 col-lg-3"><button class="border rounded p-0 w-100 bg-white text-start" type="button" data-gallery-index="' + index + '"><img data-auth-preview="' + Number(file.id || 0) + '" class="img-fluid w-100" style="aspect-ratio:4/3;object-fit:cover" loading="lazy" alt="' + esc(file.display_name || file.original_name || '') + '"><span class="d-block small p-2 text-truncate">' + esc(file.display_name || file.original_name || '') + '</span></button><div class="btn-group btn-group-sm w-100 mt-1"><button class="btn btn-outline-secondary" type="button" data-download-file="' + Number(file.id || 0) + '"><i class="fa-solid fa-download"></i></button><button class="btn btn-outline-danger" type="button" data-delete-file="' + Number(file.id || 0) + '"><i class="fa-solid fa-trash"></i></button></div></div>').join('') + '</div>' : '<div class="text-muted small">' + t('Chua co anh') + '</div>')
      + renderSimplePager(data, 'household-gallery') + '</section>';
  }

  async function loadHouseholdVideos(id, root, page = 1) {
    const pane = $('[data-household-pane="video"]', root);
    if (!pane || (pane.dataset.loaded === '1' && Number(pane.dataset.page || 1) === page)) return;
    pane.innerHTML = '<div class="text-muted small py-3">' + t('Dang tai video') + '</div>';
    try {
      const data = await fetchHouseholdFiles(id, { page, pageSize: 8, fileType: 'VIDEO' });
      const videos = data.items || [];
      pane.innerHTML = '<section class="mb-3"><h6 class="border-bottom pb-2 mb-2">Video</h6>' + (videos.length ? '<div class="row g-2">' + videos.map(file => '<div class="col-md-6"><div class="border rounded p-2"><video class="w-100" controls preload="metadata" data-auth-preview="' + Number(file.id || 0) + '"></video><div class="fw-semibold mt-2">' + esc(file.display_name || file.original_name || '') + '</div><div class="small text-muted">' + t('Dung luong') + ': ' + esc(formatSize(file.file_size)) + ' <span data-video-duration></span></div><div class="btn-group btn-group-sm mt-2"><button class="btn btn-outline-secondary" type="button" data-download-file="' + Number(file.id || 0) + '"><i class="fa-solid fa-download"></i></button><button class="btn btn-outline-danger" type="button" data-delete-file="' + Number(file.id || 0) + '"><i class="fa-solid fa-trash"></i></button></div></div></div>').join('') + '</div>' : '<div class="text-muted small">' + t('Chua co video') + '</div>') + renderSimplePager(data, 'household-video') + '</section>';
      pane.dataset.loaded = '1';
      pane.dataset.page = String(data.page || page);
      hydrateAuthPreviews(pane);
      $$('video', pane).forEach(video => video.addEventListener('loadedmetadata', () => { const host = video.parentElement?.querySelector('[data-video-duration]'); if (host) host.textContent = ' - ' + t('Thoi luong') + ': ' + formatDuration(video.duration); }));
      bindSharedFileButtons(pane, 'household', id, () => { pane.dataset.loaded = '0'; loadHouseholdVideos(id, root, Number(pane.dataset.page || 1)); });
      $$('[data-page-target="household-video"]', pane).forEach(btn => btn.addEventListener('click', () => { pane.dataset.loaded = '0'; loadHouseholdVideos(id, root, Number(btn.dataset.page || 1)); }));
    } catch (error) { pane.innerHTML = '<div class="alert alert-danger py-2">' + esc(error.message) + '</div>'; }
  }

  function renderHouseholdGps(profile) {
    const row = profile.profile || {};
    const hasGps = hasValue(row.latitude) && hasValue(row.longitude);
    const mapUrl = hasGps ? 'https://www.google.com/maps?q=' + encodeURIComponent(row.latitude + ',' + row.longitude) : '';
    const directionUrl = hasGps ? 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(row.latitude + ',' + row.longitude) : '';
    return '<section class="mb-3"><h6 class="border-bottom pb-2 mb-2">' + t('Thong tin GPS / GIS') + '</h6><div class="row g-2">'
      + '<div class="col-md-6"><div class="border rounded p-2 h-100"><div class="text-muted small">' + t('Toa do hien tai') + '</div><div class="fw-semibold">' + esc(hasGps ? row.latitude + ', ' + row.longitude : t('Chua co toa do GIS')) + '</div></div></div>'
      + '<div class="col-md-3"><div class="border rounded p-2 h-100"><div class="text-muted small">' + t('Nguon') + '</div><div class="fw-semibold">' + esc(row.location_source || '') + '</div></div></div>'
      + '<div class="col-md-3"><div class="border rounded p-2 h-100"><div class="text-muted small">' + t('Cap nhat') + '</div><div class="fw-semibold">' + esc(dateText(row.location_updated_at)) + '</div></div></div>'
      + '</div><div class="d-flex flex-wrap gap-2 mt-2"><button class="btn btn-sm btn-outline-primary" type="button" data-gps-action="gis"><i class="fa-solid fa-map-location-dot"></i> ' + t('Xem tren GIS') + '</button>'
      + (hasGps ? '<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="' + esc(mapUrl) + '"><i class="fa-solid fa-map"></i> ' + t('Xem ban do') + '</a><a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="' + esc(directionUrl) + '"><i class="fa-solid fa-route"></i> ' + t('Chi duong') + '</a>' : '')
      + '</div><div class="text-muted small mt-2">' + t('Toa do duoc doc tu du lieu GIS cua ho khong tao nguon GPS rieng') + '</div></section>';
  }

  async function loadHouseholdTimeline(id, root) {
    const pane = $('[data-household-pane="timeline"]', root);
    if (!pane || pane.dataset.loaded === '1') return;
    pane.innerHTML = '<div class="text-muted small py-3">' + t('Dang tai timeline') + '</div>';
    try {
      const items = await api('/api/profiles/timeline/household/' + encodeURIComponent(id));
      pane.innerHTML = renderTimeline(Array.isArray(items) ? items : []);
      pane.dataset.loaded = '1';
    } catch (error) { pane.innerHTML = '<div class="alert alert-danger py-2">' + esc(error.message) + '</div>'; }
  }

  function bindSharedFileButtons(root, type, id, afterChange) {
    $$('[data-preview-file]', root).forEach(btn => btn.addEventListener('click', () => previewFile(btn.dataset.previewFile)));
    $$('[data-download-file]', root).forEach(btn => btn.addEventListener('click', () => downloadFile(btn.dataset.downloadFile)));
    $$('[data-delete-file]', root).forEach(btn => btn.addEventListener('click', async () => { await deleteFile(btn.dataset.deleteFile, type, id); if (afterChange) afterChange(); }));
    $$('[data-edit-file]', root).forEach(btn => btn.addEventListener('click', async () => { await editFile(btn, type, id); if (afterChange) afterChange(); }));
  }

  function renderSimplePager(data, target) {
    const total = Number(data.total || 0), pageSize = Number(data.pageSize || 24), page = Number(data.page || 1);
    const pages = Math.max(1, Math.ceil(total / pageSize));
    if (pages <= 1) return '';
    return '<div class="d-flex justify-content-end gap-1 mt-2"><button class="btn btn-sm btn-outline-secondary" type="button" data-page-target="' + esc(target) + '" data-page="' + Math.max(1, page - 1) + '" ' + (page <= 1 ? 'disabled' : '') + '>' + t('Truoc') + '</button><span class="small text-muted align-self-center">' + page + '/' + pages + '</span><button class="btn btn-sm btn-outline-secondary" type="button" data-page-target="' + esc(target) + '" data-page="' + Math.min(pages, page + 1) + '" ' + (page >= pages ? 'disabled' : '') + '>' + t('Sau') + '</button></div>';
  }

  function openMediaLightbox(items, index) {
    let current = Math.max(0, Math.min(index, items.length - 1));
    let zoom = 1;
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.tabIndex = -1;
    modal.innerHTML = '<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h6 class="modal-title"></h6><div class="btn-group btn-group-sm ms-auto me-2"><button class="btn btn-outline-secondary" type="button" data-lightbox-prev><i class="fa-solid fa-chevron-left"></i></button><button class="btn btn-outline-secondary" type="button" data-lightbox-zoom-out><i class="fa-solid fa-magnifying-glass-minus"></i></button><button class="btn btn-outline-secondary" type="button" data-lightbox-zoom-in><i class="fa-solid fa-magnifying-glass-plus"></i></button><button class="btn btn-outline-secondary" type="button" data-lightbox-next><i class="fa-solid fa-chevron-right"></i></button></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body text-center overflow-auto" style="max-height:75vh"><img data-lightbox-image class="img-fluid" alt=""></div></div></div>';
    document.body.appendChild(modal);
    const instance = new bootstrap.Modal(modal);
    const render = () => {
      const file = items[current] || {};
      zoom = Math.max(0.5, Math.min(3, zoom));
      $('.modal-title', modal).textContent = file.display_name || file.original_name || '';
      const img = $('[data-lightbox-image]', modal);
      loadPreviewBlob(file.id).then(url => { img.src = url; }).catch(() => { img.alt = t('Khong tai duoc anh'); });
      img.style.transform = 'scale(' + zoom + ')';
      img.style.transformOrigin = 'center center';
    };
    $('[data-lightbox-prev]', modal).addEventListener('click', () => { current = (current + items.length - 1) % items.length; zoom = 1; render(); });
    $('[data-lightbox-next]', modal).addEventListener('click', () => { current = (current + 1) % items.length; zoom = 1; render(); });
    $('[data-lightbox-zoom-in]', modal).addEventListener('click', () => { zoom += 0.25; render(); });
    $('[data-lightbox-zoom-out]', modal).addEventListener('click', () => { zoom -= 0.25; render(); });
    modal.addEventListener('hidden.bs.modal', () => modal.remove());
    render();
    instance.show();
  }

  async function loadPreviewBlob(id) {
    const response = await fetch('/api/files/' + encodeURIComponent(id) + '/preview', { headers: { Authorization: 'Bearer ' + App.token }, cache: 'no-store' });
    if (!response.ok) throw new Error('Preview failed');
    return URL.createObjectURL(await response.blob());
  }

  function hydrateAuthPreviews(root) {
    $$('[data-auth-preview]', root).forEach(async element => {
      if (element.dataset.previewLoaded === '1') return;
      element.dataset.previewLoaded = '1';
      try {
        const url = await loadPreviewBlob(element.dataset.authPreview);
        element.src = url;
        if (element.tagName !== 'VIDEO') {
          element.addEventListener('load', () => setTimeout(() => URL.revokeObjectURL(url), 60000), { once: true });
        }
      } catch (error) {
        element.removeAttribute('src');
      }
    });
  }
  function handleGpsAction(action, row) {
    if (action === 'gis' && typeof window.switchScreen === 'function') {
      App.modals.detail.hide();
      window.switchScreen('gis');
      if (window.focusHouseholdMarker) setTimeout(() => window.focusHouseholdMarker(row.id), 400);
    }
  }

  function sectionLabel(value) {
    const found = sectionOptions.household.concat(sectionOptions.citizen).find(item => item[0] === value || item[1] === value);
    return found ? found[2] : t(value);
  }

  function isImageFile(file) {
    const type = String(file.file_type || '').toUpperCase();
    const mime = String(file.mime_type || '').toLowerCase();
    return ['PHOTO', 'IMAGE'].includes(type) || mime.startsWith('image/');
  }

  function formatDuration(value) {
    const seconds = Math.max(0, Math.round(Number(value || 0)));
    const min = Math.floor(seconds / 60);
    const sec = String(seconds % 60).padStart(2, '0');
    return min + ':' + sec;
  }
})();