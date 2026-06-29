(() => {
  const HOUSEHOLD_MEMBER_PAGE_SIZE = 8;
  const state = { householdMembers: [], householdPage: 1, householdSearch: '' };

  document.addEventListener('DOMContentLoaded', () => {
    ensureSprint8Modals();
    patchImportGuide();
    patchUserManagementUi();
  });

  const oldShowApp = window.showApp;
  if (typeof oldShowApp === 'function') {
    window.showApp = function sprint8ShowApp() {
      oldShowApp();
      ensureSprint8Modals();
      patchImportGuide();
      patchUserManagementUi();
    };
  }

  window.showHousehold = async function showHousehold(id) {
    try {
      const household = await api('/api/households/' + id);
      const members = await api('/api/persons?' + new URLSearchParams({ householdId: household.household_code, pageSize: 1000 }).toString());
      state.householdMembers = members.items || [];
      state.householdPage = 1;
      state.householdSearch = '';
      document.querySelector('#householdMemberSearch').value = '';
      document.querySelector('#householdMemberTitle').textContent = 'Thành viên hộ ' + (household.household_code || '');
      document.querySelector('#householdMemberMeta').innerHTML = details([
        ['Mã hộ', household.household_code], ['Chủ hộ', household.head_citizen_name], ['Địa chỉ', household.address], ['Số điện thoại', household.phone], ['Ở nhà', household.at_home_count || 0], ['Đi vắng', household.away_count || 0]
      ]);
      renderHouseholdMembers();
      bootstrap.Modal.getOrCreateInstance(document.querySelector('#householdMembersModal')).show();
    } catch (error) { showToast(error.message, 'danger'); }
  };

  window.showPerson = async function showPerson(id) {
    try {
      const row = await api('/api/persons/' + id);
      document.querySelector('#personDetailTitle').textContent = row.full_name || 'Chi tiết nhân khẩu';
      document.querySelector('#personDetailBody').innerHTML = personDetailHtml(row);
      document.querySelector('#personDetailEditBtn').onclick = () => { bootstrap.Modal.getOrCreateInstance(document.querySelector('#personDetailModal')).hide(); openPersonForm(row.id); };
      document.querySelector('#personDetailPrintBtn').onclick = () => printPersonDetail(row);
      bootstrap.Modal.getOrCreateInstance(document.querySelector('#personDetailModal')).show();
    } catch (error) { showToast(error.message, 'danger'); }
  };

  function ensureSprint8Modals() {
    if (document.querySelector('#householdMembersModal')) return;
    document.body.insertAdjacentHTML('beforeend', '<div class="modal fade" id="householdMembersModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><h5 id="householdMemberTitle" class="modal-title">Thành viên hộ</h5><small class="text-muted">Danh sách nhân khẩu cùng mã hộ</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><div id="householdMemberMeta" class="mb-3"></div><div class="toolbar"><input id="householdMemberSearch" class="form-control" placeholder="Tìm họ tên, mã nhân khẩu, CCCD, số điện thoại"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Đóng</button></div><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Mã nhân khẩu</th><th>Họ tên</th><th>Ngày sinh</th><th>CCCD</th><th>Số điện thoại</th><th>Thường trú</th><th>Hiện tại</th><th></th></tr></thead><tbody id="householdMemberRows"></tbody></table></div><div id="householdMemberPager" class="pager"></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button></div></div></div></div><div class="modal fade" id="personDetailModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 id="personDetailTitle" class="modal-title">Chi tiết nhân khẩu</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div id="personDetailBody" class="modal-body"></div><div class="modal-footer"><button id="personDetailEditBtn" class="btn btn-primary" type="button">Sửa</button><button id="personDetailPrintBtn" class="btn btn-outline-secondary" type="button">In</button><button class="btn btn-light" type="button" data-bs-dismiss="modal">Đóng</button></div></div></div></div>');
    document.querySelector('#householdMemberSearch').addEventListener('input', debounce(event => { state.householdSearch = event.target.value.trim().toLowerCase(); state.householdPage = 1; renderHouseholdMembers(); }, 250));
  }

  function renderHouseholdMembers() {
    const filtered = state.householdMembers.filter(row => !state.householdSearch || [row.citizen_code, row.full_name, row.identity_number, row.phone].join(' ').toLowerCase().includes(state.householdSearch));
    const totalPages = Math.max(1, Math.ceil(filtered.length / HOUSEHOLD_MEMBER_PAGE_SIZE));
    state.householdPage = Math.min(state.householdPage, totalPages);
    const start = (state.householdPage - 1) * HOUSEHOLD_MEMBER_PAGE_SIZE;
    const pageItems = filtered.slice(start, start + HOUSEHOLD_MEMBER_PAGE_SIZE);
    document.querySelector('#householdMemberRows').innerHTML = pageItems.map(row => '<tr><td>' + escapeHtml(row.citizen_code || '') + '</td><td>' + escapeHtml(row.full_name || '') + '</td><td>' + formatDate(row.date_of_birth) + '</td><td>' + escapeHtml(row.identity_number || '') + '</td><td>' + escapeHtml(row.phone || '') + '</td><td>' + residencyLabel(row.residency_status) + '</td><td>' + presenceLabel(row.presence_status) + '</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="showPerson(' + row.id + ')">Xem chi tiết</button></td></tr>').join('') || emptyRow(8, 'Không có thành viên phù hợp');
    document.querySelector('#householdMemberPager').innerHTML = '<span class="text-muted small">Trang ' + state.householdPage + '/' + totalPages + ' - ' + number(filtered.length) + ' thành viên</span><button class="btn btn-outline-secondary btn-sm" ' + (state.householdPage <= 1 ? 'disabled' : '') + ' data-member-page="' + (state.householdPage - 1) + '">Trước</button><button class="btn btn-outline-secondary btn-sm" ' + (state.householdPage >= totalPages ? 'disabled' : '') + ' data-member-page="' + (state.householdPage + 1) + '">Sau</button>';
    document.querySelectorAll('[data-member-page]').forEach(button => button.addEventListener('click', () => { state.householdPage = Number(button.dataset.memberPage); renderHouseholdMembers(); }));
  }

  function personDetailHtml(row) {
    const photo = row.photo_url ? '<div class="mb-3"><img src="' + escapeHtml(row.photo_url) + '" alt="Ảnh nhân khẩu" class="img-fluid rounded border" style="max-height:220px"></div>' : '';
    return photo + details([
      ['Mã nhân khẩu', row.citizen_code], ['Mã hộ', row.household_code], ['Họ tên', row.full_name], ['Giới tính', row.gender], ['Ngày sinh', formatDate(row.date_of_birth)], ['CCCD', row.identity_number], ['Nơi sinh', row.birth_place], ['Quê quán', row.hometown], ['Dân tộc', row.ethnicity], ['Tôn giáo', row.religion], ['Nghề nghiệp', row.occupation], ['Nơi làm việc', row.workplace], ['Số điện thoại', row.phone], ['Quan hệ với chủ hộ', row.relationship], ['Chủ hộ', row.head_citizen_name], ['Địa chỉ thường trú', row.household_address], ['Địa chỉ hiện tại', row.current_address], ['Trạng thái cư trú', residencyLabel(row.residency_status)], ['Hiện tại', presenceLabel(row.presence_status)], ['Trạng thái', lifeLabel(row.life_status)], ['Ngày đăng ký', formatDateTime(row.created_at)], ['Ghi chú', row.note]
    ]);
  }

  function printPersonDetail(row) {
    const win = window.open('', '_blank', 'width=1024,height=768');
    win.document.write('<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>' + escapeHtml(row.full_name || 'Nhân khẩu') + '</title><style>@page{size:A4;margin:14mm}body{font-family:Arial,sans-serif;color:#111}.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 16px}.detail-item{border-bottom:1px solid #ddd;padding:6px 0}.detail-label{font-size:12px;color:#555}.detail-value{font-weight:700}</style></head><body><h2>Phiếu thông tin nhân khẩu</h2>' + personDetailHtml(row) + '<script>window.print();<\\/script></body></html>');
    win.document.close();
  }

  function patchImportGuide() {
    const screen = document.querySelector('#importScreen');
    if (!screen || screen.dataset.sprint8) return;
    if (screen.querySelector('a[href="sample-data/Mau_Import_HoDan.xlsx"]')) { screen.dataset.sprint8 = '1'; return; }
    screen.dataset.sprint8 = '1';
    document.querySelector('#importForm')?.insertAdjacentHTML('beforebegin', '<div class="content-card mb-3"><h3 class="section-title">Hướng dẫn Import Excel</h3><ul class="mb-3"><li>Chọn đúng loại dữ liệu trước khi import.</li><li>Không đổi tên Sheet.</li><li>Không đổi tên cột.</li><li>CCCD và số điện thoại để dạng Text.</li><li>Với hộ dân: không trùng Mã hộ; các cột diện hộ nhập 1 hoặc 0.</li></ul><div class="d-flex flex-wrap gap-2"><a class="btn btn-success" href="sample-data/Mau_Import_NhanKhau.xlsx" download><i class="fa-solid fa-file-excel"></i> Mẫu nhân khẩu</a><a class="btn btn-outline-success" href="sample-data/Mau_Import_HoDan.xlsx" download><i class="fa-solid fa-file-excel"></i> Mẫu hộ dân</a></div></div>');
  }

  function patchUserManagementUi() {
    const screen = document.querySelector('#usersScreen');
    if (!screen || screen.dataset.sprint8) return;
    screen.dataset.sprint8 = '1';
    const head = screen.querySelector('thead tr');
    if (head) head.innerHTML = '<th>Username</th><th>Họ tên</th><th>Email</th><th>Số điện thoại</th><th>Chức vụ</th><th>Vai trò</th><th>Trạng thái</th><th>Ngày tạo</th><th>Lần đăng nhập cuối</th><th></th>';
    const modalBody = document.querySelector('#userForm .modal-body');
    if (modalBody) modalBody.innerHTML = '<input type="hidden" name="id"><div class="row g-3"><div class="col-md-6"><label class="form-label">Username</label><input name="username" class="form-control" required></div><div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div><div class="col-md-6"><label class="form-label">Họ tên</label><input name="displayName" class="form-control" required></div><div class="col-md-6"><label class="form-label">Số điện thoại</label><input name="phone" class="form-control"></div><div class="col-md-6"><label class="form-label">Chức vụ</label><input name="position" class="form-control"></div><div class="col-md-6"><label class="form-label">Vai trò</label><select name="role" class="form-select"><option value="ADMIN">Admin</option><option value="OFFICER">Cán bộ</option><option value="VIEWER">Khách</option></select></div><div class="col-12"><label class="form-label">Mật khẩu</label><input name="password" type="password" class="form-control" minlength="8"><div class="form-text">Bắt buộc khi tạo mới, để trống nếu không đổi.</div></div></div>';
    window.openUserForm = async function openUserForm(id = null) {
      const form = document.querySelector('#userForm');
      form.reset(); form.elements.id.value = ''; form.elements.email.disabled = false; form.elements.username.disabled = false;
      if (id) { const row = await api('/api/users/' + id); setForm(form, { id: row.id, username: row.username, email: row.email, displayName: row.displayName, phone: row.phone, position: row.position, role: row.role === 'SUPER_ADMIN' ? 'ADMIN' : row.role }); form.elements.email.disabled = true; form.elements.username.disabled = true; }
      App.modals.user.show();
    };
  }

  window.renderUserRowsSprint8 = function renderUserRowsSprint8(data) {
    const body = document.querySelector('#userRows');
    if (!body) return;
    body.innerHTML = data.items.map(row => { const action = row.status === 'ACTIVE' ? 'lock' : 'unlock'; return '<tr><td>' + escapeHtml(row.username || '') + '</td><td>' + escapeHtml(row.display_name || row.displayName || '') + '</td><td>' + escapeHtml(row.email || '') + '</td><td>' + escapeHtml(row.phone || '') + '</td><td>' + escapeHtml(row.position || '') + '</td><td>' + roleLabel(row.role) + '</td><td>' + statusLabel(row.status) + '</td><td>' + formatDateTime(row.created_at) + '</td><td>' + formatDateTime(row.last_login_at) + '</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="openUserForm(' + row.id + ')">Sửa</button> <button class="btn btn-sm btn-outline-warning" onclick="toggleUser(' + row.id + ', \'' + action + '\')">' + (action === 'lock' ? 'Khóa' : 'Mở khóa') + '</button> <button class="btn btn-sm btn-outline-secondary" onclick="resetUserPassword(' + row.id + ')">Đặt lại mật khẩu</button> <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(' + row.id + ')">Xóa</button></td></tr>'; }).join('') || emptyRow(10, 'Chưa có người dùng');
  };

  window.resetUserPassword = async function resetUserPassword(id) {
    const password = prompt('Nhập mật khẩu mới tối thiểu 8 ký tự');
    if (!password) return;
    if (password.length < 8) return showToast('Mật khẩu tối thiểu 8 ký tự', 'warning');
    const row = await api('/api/users/' + id);
    await api('/api/users/' + id, { method: 'PUT', body: { displayName: row.displayName, role: row.role, phone: row.phone, position: row.position, password } });
    showToast('Đã đặt lại mật khẩu');
  };

  function formatDateTime(value) { if (!value) return ''; const date = new Date(String(value).replace(' ', 'T')); return Number.isNaN(date.getTime()) ? formatDate(value) : date.toLocaleString('vi-VN'); }
  function statusLabel(status) { return status === 'ACTIVE' ? 'Hoạt động' : status === 'INACTIVE' ? 'Đã khóa' : status || ''; }
})();