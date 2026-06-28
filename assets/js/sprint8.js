(() => {
  const memberState = { items: [], page: 1, search: '', pageSize: 8 };

  document.addEventListener('DOMContentLoaded', bootSprint8);

  const previousShowApp = window.showApp;
  if (typeof previousShowApp === 'function') {
    window.showApp = function sprint8ShowApp() {
      previousShowApp();
      bootSprint8();
    };
  }

  function bootSprint8() {
    ensureModals();
    patchImportScreen();
    patchUserScreen();
  }

  window.showHousehold = async function showHousehold(id) {
    try {
      const household = await api('/api/households/' + id);
      const params = new URLSearchParams({ householdId: household.household_code, pageSize: 1000 });
      const members = await api('/api/persons?' + params.toString());
      memberState.items = members.items || [];
      memberState.page = 1;
      memberState.search = '';
      $('#householdMemberSearch').value = '';
      $('#householdMemberTitle').textContent = 'Thành viên hộ ' + (household.household_code || '');
      $('#householdMemberMeta').innerHTML = details([
        ['Mã hộ', household.household_code], ['Chủ hộ', household.head_citizen_name],
        ['Địa chỉ', household.address], ['Số điện thoại', household.phone],
        ['Ở nhà', household.at_home_count || 0], ['Đi vắng', household.away_count || 0]
      ]);
      renderMembers();
      bootstrap.Modal.getOrCreateInstance($('#householdMembersModal')).show();
    } catch (error) { showToast(error.message, 'danger'); }
  };

  window.showPerson = async function showPerson(id) {
    try {
      const row = await api('/api/persons/' + id);
      $('#personDetailTitle').textContent = row.full_name || 'Chi tiết nhân khẩu';
      $('#personDetailBody').innerHTML = personHtml(row);
      $('#personDetailEditBtn').onclick = () => {
        bootstrap.Modal.getOrCreateInstance($('#personDetailModal')).hide();
        openPersonForm(row.id);
      };
      $('#personDetailPrintBtn').onclick = () => printPerson(row);
      bootstrap.Modal.getOrCreateInstance($('#personDetailModal')).show();
    } catch (error) { showToast(error.message, 'danger'); }
  };

  function ensureModals() {
    if ($('#householdMembersModal')) return;
    document.body.insertAdjacentHTML('beforeend', `
      <div class="modal fade" id="householdMembersModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
          <div class="modal-header"><div><h5 id="householdMemberTitle" class="modal-title">Thành viên hộ</h5><small class="text-muted">Danh sách nhân khẩu cùng Mã hộ</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
          <div class="modal-body"><div id="householdMemberMeta" class="mb-3"></div><div class="toolbar"><input id="householdMemberSearch" class="form-control" placeholder="Tìm họ tên, mã nhân khẩu, CCCD, số điện thoại"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Đóng</button></div><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Mã nhân khẩu</th><th>Họ tên</th><th>Ngày sinh</th><th>CCCD</th><th>Số điện thoại</th><th>Thường trú</th><th>Hiện tại</th><th></th></tr></thead><tbody id="householdMemberRows"></tbody></table></div><div id="householdMemberPager" class="pager"></div></div>
          <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button></div>
        </div></div>
      </div>
      <div class="modal fade" id="personDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
          <div class="modal-header"><h5 id="personDetailTitle" class="modal-title">Chi tiết nhân khẩu</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
          <div id="personDetailBody" class="modal-body"></div>
          <div class="modal-footer"><button id="personDetailEditBtn" class="btn btn-primary" type="button">Sửa</button><button id="personDetailPrintBtn" class="btn btn-outline-secondary" type="button">In</button><button class="btn btn-light" type="button" data-bs-dismiss="modal">Đóng</button></div>
        </div></div>
      </div>`);
    $('#householdMemberSearch').addEventListener('input', debounce(event => {
      memberState.search = event.target.value.trim().toLowerCase();
      memberState.page = 1;
      renderMembers();
    }, 250));
  }

  function renderMembers() {
    const query = memberState.search;
    const filtered = memberState.items.filter(row => !query || [row.citizen_code, row.full_name, row.identity_number, row.phone].join(' ').toLowerCase().includes(query));
    const totalPages = Math.max(1, Math.ceil(filtered.length / memberState.pageSize));
    memberState.page = Math.min(memberState.page, totalPages);
    const start = (memberState.page - 1) * memberState.pageSize;
    const rows = filtered.slice(start, start + memberState.pageSize);
    $('#householdMemberRows').innerHTML = rows.map(row => `<tr><td>${escapeHtml(row.citizen_code || '')}</td><td>${escapeHtml(row.full_name || '')}</td><td>${formatDate(row.date_of_birth)}</td><td>${escapeHtml(row.identity_number || '')}</td><td>${escapeHtml(row.phone || '')}</td><td>${residencyLabel(row.residency_status)}</td><td>${presenceLabel(row.presence_status)}</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="showPerson(${row.id})">Xem chi tiết</button></td></tr>`).join('') || emptyRow(8, 'Không có thành viên phù hợp');
    $('#householdMemberPager').innerHTML = `<span class="text-muted small">Trang ${memberState.page}/${totalPages} - ${number(filtered.length)} thành viên</span><button class="btn btn-outline-secondary btn-sm" ${memberState.page <= 1 ? 'disabled' : ''} data-page="${memberState.page - 1}">Trước</button><button class="btn btn-outline-secondary btn-sm" ${memberState.page >= totalPages ? 'disabled' : ''} data-page="${memberState.page + 1}">Sau</button>`;
    $$('#householdMemberPager [data-page]').forEach(button => button.addEventListener('click', () => { memberState.page = Number(button.dataset.page); renderMembers(); }));
  }

  function personHtml(row) {
    const photo = row.photo_url ? `<div class="mb-3"><img src="${escapeHtml(row.photo_url)}" alt="Ảnh nhân khẩu" class="img-fluid rounded border" style="max-height:220px"></div>` : '';
    return photo + details([
      ['Mã nhân khẩu', row.citizen_code], ['Mã hộ', row.household_code], ['Họ tên', row.full_name], ['Giới tính', row.gender], ['Ngày sinh', formatDate(row.date_of_birth)], ['CCCD', row.identity_number], ['Nơi sinh', row.birth_place], ['Quê quán', row.hometown], ['Dân tộc', row.ethnicity], ['Tôn giáo', row.religion], ['Nghề nghiệp', row.occupation], ['Nơi làm việc', row.workplace], ['Số điện thoại', row.phone], ['Quan hệ với chủ hộ', row.relationship], ['Chủ hộ', row.head_citizen_name], ['Địa chỉ thường trú', row.household_address], ['Địa chỉ hiện tại', row.current_address], ['Trạng thái cư trú', residencyLabel(row.residency_status)], ['Hiện tại', presenceLabel(row.presence_status)], ['Trạng thái', lifeLabel(row.life_status)], ['Ngày đăng ký', formatDateTime(row.created_at)], ['Ghi chú', row.note]
    ]);
  }

  function printPerson(row) {
    const win = window.open('', '_blank', 'width=1024,height=768');
    win.document.write(`<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>${escapeHtml(row.full_name || 'Nhân khẩu')}</title><style>@page{size:A4;margin:14mm}body{font-family:Arial,sans-serif;color:#111}.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 16px}.detail-item{border-bottom:1px solid #ddd;padding:6px 0}.detail-label{font-size:12px;color:#555}.detail-value{font-weight:700}</style></head><body><h2>Phiếu thông tin nhân khẩu</h2>${personHtml(row)}<script>window.print();<\/script></body></html>`);
    win.document.close();
  }

  function patchImportScreen() {
    const screen = $('#importScreen');
    if (!screen || screen.dataset.sprint8) return;
    screen.dataset.sprint8 = '1';
    $('#importForm')?.insertAdjacentHTML('beforebegin', '<div class="content-card mb-3"><h3 class="section-title">Hướng dẫn import</h3><ul class="mb-3"><li>Không đổi tên Sheet.</li><li>Không đổi tiêu đề cột.</li><li>Ngày sinh định dạng dd/MM/yyyy.</li><li>CCCD dạng Text.</li><li>Không được trùng Mã nhân khẩu.</li></ul><a class="btn btn-success" href="sample-data/import_template_thon09.xls" download>Tải file Excel mẫu</a></div>');
  }

  function patchUserScreen() {
    const screen = $('#usersScreen');
    if (!screen || screen.dataset.sprint8) return;
    screen.dataset.sprint8 = '1';
    const head = screen.querySelector('thead tr');
    if (head) head.innerHTML = '<th>Username</th><th>Họ tên</th><th>Email</th><th>Số điện thoại</th><th>Chức vụ</th><th>Vai trò</th><th>Trạng thái</th><th>Ngày tạo</th><th>Lần đăng nhập cuối</th><th></th>';
    const modalBody = $('#userForm .modal-body');
    if (modalBody) modalBody.innerHTML = '<input type="hidden" name="id"><div class="row g-3"><div class="col-md-6"><label class="form-label">Username</label><input name="username" class="form-control" required></div><div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div><div class="col-md-6"><label class="form-label">Họ tên</label><input name="displayName" class="form-control" required></div><div class="col-md-6"><label class="form-label">Số điện thoại</label><input name="phone" class="form-control"></div><div class="col-md-6"><label class="form-label">Chức vụ</label><input name="position" class="form-control"></div><div class="col-md-6"><label class="form-label">Vai trò</label><select name="role" class="form-select"><option value="ADMIN">Admin</option><option value="OFFICER">Cán bộ</option><option value="VIEWER">Khách</option></select></div><div class="col-12"><label class="form-label">Mật khẩu</label><input name="password" type="password" class="form-control" minlength="8"><div class="form-text">Bắt buộc khi tạo mới, để trống nếu không đổi.</div></div></div>';
  }

  window.renderUserRowsSprint8 = function renderUserRowsSprint8(data) {
    const body = $('#userRows');
    if (!body) return;
    body.innerHTML = data.items.map(row => {
      const action = row.status === 'ACTIVE' ? 'lock' : 'unlock';
      return `<tr><td>${escapeHtml(row.username || '')}</td><td>${escapeHtml(row.display_name || row.displayName || '')}</td><td>${escapeHtml(row.email || '')}</td><td>${escapeHtml(row.phone || '')}</td><td>${escapeHtml(row.position || '')}</td><td>${roleLabel(row.role)}</td><td>${statusLabel(row.status)}</td><td>${formatDateTime(row.created_at)}</td><td>${formatDateTime(row.last_login_at)}</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="openUserForm(${row.id})">Sửa</button> <button class="btn btn-sm btn-outline-warning" onclick="toggleUser(${row.id}, '${action}')">${action === 'lock' ? 'Khóa' : 'Mở khóa'}</button> <button class="btn btn-sm btn-outline-secondary" onclick="resetUserPassword(${row.id})">Đặt lại mật khẩu</button> <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${row.id})">Xóa</button></td></tr>`;
    }).join('') || emptyRow(10, 'Chưa có người dùng');
  };

  window.resetUserPassword = async function resetUserPassword(id) {
    const password = prompt('Nhập mật khẩu mới tối thiểu 8 ký tự');
    if (!password) return;
    if (password.length < 8) return showToast('Mật khẩu tối thiểu 8 ký tự', 'warning');
    const row = await api('/api/users/' + id);
    await api('/api/users/' + id, { method: 'PUT', body: { displayName: row.displayName, role: row.role, phone: row.phone, position: row.position, password } });
    showToast('Đã đặt lại mật khẩu');
  };

  function statusLabel(status) { return status === 'ACTIVE' ? 'Hoạt động' : status === 'INACTIVE' ? 'Đã khóa' : status || ''; }
  function formatDateTime(value) { if (!value) return ''; const date = new Date(String(value).replace(' ', 'T')); return Number.isNaN(date.getTime()) ? formatDate(value) : date.toLocaleString('vi-VN'); }
})();
