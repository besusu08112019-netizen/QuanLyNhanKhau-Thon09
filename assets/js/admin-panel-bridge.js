(() => {
  window.roleLabel = function roleLabel(role) {
    return ({ SUPER_ADMIN:'Super Admin', ADMIN:'Admin', OFFICER:'Cán bộ', VIEWER:'Khách' })[role] || role || '';
  };

  document.addEventListener('DOMContentLoaded', () => {
    loadSprint8Script();
    enforceSuperAdminMenu();
    const previousShowApp = window.showApp;
    if (typeof previousShowApp === 'function') {
      window.showApp = function bridgeShowApp() {
        previousShowApp();
        enforceSuperAdminMenu();
      };
    }
    const nav = document.querySelector('.sidebar .nav');
    if (!nav) return;
    nav.addEventListener('click', event => {
      const button = event.target.closest('.nav-link');
      if (!button) return;
      const screen = button.dataset.screen;
      if (!['users','logs','backups'].includes(screen)) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      switchScreen(screen);
      document.querySelector('#screenTitle').textContent = { users: 'Quản lý tài khoản', logs: 'Nhật ký hệ thống', backups: 'Sao lưu dữ liệu' }[screen];
      const breadcrumb = document.querySelector('#breadcrumbTrail');
      if (breadcrumb) breadcrumb.textContent = 'Trang chủ / ' + document.querySelector('#screenTitle').textContent;
      if (screen === 'users') { ensureRoleOptions(); loadAdminUsers(); }
      if (screen === 'logs') loadAdminLogs();
      if (screen === 'backups') loadAdminBackups();
    }, true);
  });

  function loadSprint8Script() {
    if (document.querySelector('script[src*="sprint8.js"]')) return;
    const script = document.createElement('script');
    script.src = 'assets/js/sprint8.js?v=20260628-sprint8';
    script.defer = true;
    document.body.appendChild(script);
  }

  function enforceSuperAdminMenu() {
    setTimeout(() => {
      const role = App.user?.role || '';
      const adminOnly = ['users','permissions','logs','settings','backups','restore'];
      document.querySelectorAll('.sidebar .nav-link').forEach(btn => {
        btn.classList.toggle('d-none', adminOnly.includes(btn.dataset.screen) && role !== 'SUPER_ADMIN');
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
    else document.querySelector('#userRows').innerHTML = data.items.map(row => `<tr><td>${escapeHtml(row.email)}</td><td>${escapeHtml(row.display_name)}</td><td>${roleLabel(row.role)}</td><td>${statusLabel(row.status)}</td><td>${escapeHtml(row.last_login_at || '')}</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="openUserForm(${row.id})">Sửa</button> <button class="btn btn-sm btn-outline-warning" onclick="toggleUser(${row.id}, '${row.status === 'ACTIVE' ? 'lock' : 'unlock'}')">${row.status === 'ACTIVE' ? 'Khóa' : 'Mở khóa'}</button> <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${row.id})">Xóa</button></td></tr>`).join('') || emptyRow(6, 'Chưa có người dùng');
    renderPager('#userPager', data, page => { App.users.page = page; loadAdminUsers(); });
  }

  async function loadAdminLogs() {
    if (!document.querySelector('#logRows')) return;
    App.logs = App.logs || { page: 1, pageSize: 50, search: '' };
    const data = await api('/api/logs?' + new URLSearchParams(App.logs));
    document.querySelector('#logRows').innerHTML = data.items.map(row => `<tr><td>${escapeHtml(row.created_at)}</td><td>${escapeHtml(row.actor_email || '')}</td><td>${escapeHtml(row.module)}</td><td>${escapeHtml(row.action)}</td><td>${escapeHtml(row.message)}</td></tr>`).join('') || emptyRow(5, 'Chưa có nhật ký');
    renderPager('#logPager', data, page => { App.logs.page = page; loadAdminLogs(); });
  }

  async function loadAdminBackups() {
    if (!document.querySelector('#backupRows')) return;
    App.backups = App.backups || { page: 1, pageSize: 20 };
    const data = await api('/api/backups?' + new URLSearchParams(App.backups));
    document.querySelector('#backupRows').innerHTML = data.items.map(row => `<tr><td>${escapeHtml(row.created_at)}</td><td>${escapeHtml(row.file_name)}</td><td>${number(row.file_size || 0)} byte</td><td>${escapeHtml(row.status)}</td><td>${escapeHtml(row.created_by_email || '')}</td></tr>`).join('') || emptyRow(5, 'Chưa có bản sao lưu');
    renderPager('#backupPager', data, page => { App.backups.page = page; loadAdminBackups(); });
  }

  function statusLabel(status) { return status === 'ACTIVE' ? 'Hoạt động' : status === 'INACTIVE' ? 'Đã khóa' : status; }
})();
