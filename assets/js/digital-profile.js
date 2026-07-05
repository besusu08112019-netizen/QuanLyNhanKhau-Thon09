(() => {
  const sectionOptions = {
    household: [
      ['front_house', 'PHOTO', 'Ảnh mặt tiền nhà'],
      ['gate_photo', 'PHOTO', 'Ảnh cổng'],
      ['overview_photo', 'PHOTO', 'Ảnh toàn cảnh khuôn viên'],
      ['household_video', 'VIDEO', 'Video hộ gia đình'],
      ['household_pdf', 'DOCUMENT', 'Hồ sơ PDF'],
      ['head_cccd_scan', 'IMAGE', 'CCCD scan của chủ hộ'],
      ['old_household_book', 'DOCUMENT', 'Sổ hộ khẩu cũ'],
      ['household_document', 'DOCUMENT', 'Giấy tờ liên quan'],
      ['household_avatar', 'PHOTO', 'Logo hoặc hình đại diện hộ'],
    ],    citizen: [
      ['portrait', 'PHOTO', 'Ảnh chân dung'],
      ['cccd_front', 'IMAGE', 'CCCD mặt trước'],
      ['cccd_back', 'IMAGE', 'CCCD mặt sau'],
      ['birth_certificate', 'DOCUMENT', 'Giấy khai sinh'],
      ['party_profile', 'DOCUMENT', 'Hồ sơ Đảng'],
      ['military_profile', 'DOCUMENT', 'Hồ sơ quân sự'],
      ['health_insurance', 'DOCUMENT', 'Hồ sơ BHYT'],
      ['social_insurance', 'DOCUMENT', 'Hồ sơ BHXH'],
      ['citizen_document', 'DOCUMENT', 'Giấy tờ khác'],
    ],  };

  function $(selector, root = document) { return root.querySelector(selector); }
  function $$(selector, root = document) { return Array.from(root.querySelectorAll(selector)); }
  function esc(value) { return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char])); }
  function hasValue(value) { return value !== null && value !== undefined && String(value).trim() !== ''; }
  function dateText(value) { if (!hasValue(value)) return ''; const date = new Date(String(value).replace(' ', 'T')); return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('vi-VN'); }
  function show(message, type = 'success') { if (typeof showToast === 'function') showToast(message, type); }

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
      const profile = await loadProfile('citizen', id);
      $('#detailTitle').textContent = 'Hồ sơ số nhân khẩu';
      $('#detailBody').innerHTML = renderProfile(profile);
      bindProfileActions(profile);
      App.modals.detail.show();
    } catch (error) { show(error.message, 'danger'); }
  };

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
    return '<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">'
      + '<div><div class="text-muted small">Hồ sơ số</div><h4 class="mb-1">' + esc(title) + '</h4><div class="text-muted">' + esc(subtitle) + '</div></div>'
      + '<div class="btn-group btn-group-sm" role="group">'
      + '<button class="btn btn-outline-primary" type="button" data-profile-refresh><i class="fa-solid fa-rotate-right"></i> Tải lại</button>'
      + '<button class="btn btn-outline-secondary" type="button" data-profile-print><i class="fa-solid fa-print"></i> In</button>'
      + '</div></div>';
  }

  function renderQuickLinks(profile) {
    const type = profile.type;
    const id = Number(profile.profile?.id || 0);
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
      + '<form class="row g-2 align-items-end mb-2" data-profile-upload><div class="col-md-3"><label class="form-label small">Loại tài liệu</label><select name="profileSection" class="form-select form-select-sm">' + options + '</select></div><div class="col-md-4"><label class="form-label small">Mô tả</label><input name="description" class="form-control form-control-sm"></div><div class="col-md-3"><label class="form-label small">File</label><input name="file" type="file" class="form-control form-control-sm" required></div><div class="col-md-2"><button class="btn btn-sm btn-primary w-100" type="submit"><i class="fa-solid fa-upload"></i> Tải lên</button></div></form>'
      + (files.length ? '<div class="list-group list-group-flush border rounded">' + files.map(file => '<div class="list-group-item d-flex justify-content-between gap-2 align-items-start"><div><div class="fw-semibold">' + esc(file.original_name || 'Tệp đính kèm') + '</div><div class="small text-muted">' + esc(file.profile_section || file.file_type || '') + (file.description ? ' - ' + esc(file.description) : '') + '</div></div><div class="btn-group btn-group-sm"><button class="btn btn-outline-primary" type="button" data-preview-file="' + Number(file.id || 0) + '"><i class="fa-solid fa-eye"></i></button><button class="btn btn-outline-secondary" type="button" data-download-file="' + Number(file.id || 0) + '"><i class="fa-solid fa-download"></i></button><button class="btn btn-outline-danger" type="button" data-delete-file="' + Number(file.id || 0) + '"><i class="fa-solid fa-trash"></i></button></div></div>').join('') + '</div>' : '<div class="text-muted small">Chưa có tài liệu đính kèm.</div>')
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
      + (items.length ? '<div class="list-group list-group-flush border rounded">' + items.map(item => '<div class="list-group-item"><div class="d-flex justify-content-between gap-2"><strong>' + esc(item.title || item.type || '') + '</strong><span class="small text-muted">' + dateText(item.time) + '</span></div><div class="small text-muted">' + esc(item.type || '') + '</div>' + (item.description ? '<div>' + esc(item.description) + '</div>' : '') + '</div>').join('') + '</div>' : '<div class="text-muted small">Chưa có lịch sử.</div>')
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
    $$('[data-edit-note]', root).forEach(btn => btn.addEventListener('click', () => editNote(btn, type, id)));
    $$('[data-delete-note]', root).forEach(btn => btn.addEventListener('click', () => deleteNote(btn.dataset.deleteNote, type, id)));
    $('[data-profile-upload]', root)?.addEventListener('submit', event => uploadFile(event, type, id));
    $('[data-profile-note]', root)?.addEventListener('submit', event => createNote(event, type, id));
    $$('[data-profile-link]', root).forEach(btn => btn.addEventListener('click', () => openLink(btn.dataset.profileLink)));
  }

  async function uploadFile(event, type, id) {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData();
    const section = form.elements.profileSection.value;
    const option = form.elements.profileSection.selectedOptions[0];
    data.append('module', type);
    data.append('entityId', id);
    data.append('profileSection', section);
    data.append('fileType', option?.dataset.fileType || 'OTHER');
    data.append('description', form.elements.description.value || '');
    data.append('file', form.elements.file.files[0]);
    await api('/api/files', { method: 'POST', body: data });
    show('Đã tải lên tài liệu');
    type === 'household' ? window.showHousehold(id) : window.showPerson(id);
  }

  async function createNote(event, type, id) {
    event.preventDefault();
    const form = event.currentTarget;
    await api('/api/profiles/' + type + '/' + id + '/notes', { method: 'POST', body: { title: form.elements.title.value, content: form.elements.content.value, section: 'general' } });
    show('Đã thêm ghi chú');
    type === 'household' ? window.showHousehold(id) : window.showPerson(id);
  }

  async function editNote(button, type, id) {
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
    if (!confirm('Xóa ghi chú này?')) return;
    await api('/api/profiles/notes/' + encodeURIComponent(noteId), { method: 'DELETE' });
    show('Đã xóa ghi chú');
    type === 'household' ? window.showHousehold(id) : window.showPerson(id);
  }

  async function deleteFile(id, type, entityId) {
    if (!confirm('Xóa file đính kèm này?')) return;
    await api('/api/files/' + encodeURIComponent(id), { method: 'DELETE' });
    show('Đã xóa file đính kèm');
    type === 'household' ? window.showHousehold(entityId) : window.showPerson(entityId);
  }
  function previewFile(id) {
    window.open('/api/files/' + encodeURIComponent(id) + '/preview', '_blank', 'noopener');
  }
  async function downloadFile(id) {
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

  function sectionTitle(key) {
    return ({ general: 'Thông tin chung', statistics: 'Thống kê', basic: 'Thông tin cơ bản', residence: 'Cư trú', personal: 'Thông tin cá nhân', administrative: 'Hành chính' })[key] || key;
  }
})();
