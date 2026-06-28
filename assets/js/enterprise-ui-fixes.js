(() => {
  const roleLabels = {
    SUPER_ADMIN: 'Quản trị tối cao',
    ADMIN: 'Quản trị',
    OFFICER: 'Cán bộ',
    COLLABORATOR: 'Cộng tác viên',
    VIEWER: 'Chỉ xem',
    DATA_ENTRY: 'Chỉ nhập liệu',
    NO_DELETE: 'Không được xóa',
    NO_EXPORT: 'Không được xuất dữ liệu',
  };

  window.roleLabel = function normalizedRoleLabel(role) {
    return roleLabels[role] || role || '';
  };

  const observer = new MutationObserver(syncRoleSelects);
  observer.observe(document.documentElement, { childList: true, subtree: true });
  document.addEventListener('DOMContentLoaded', syncRoleSelects);

  function syncRoleSelects() {
    document.querySelectorAll('select[name="role"]').forEach(select => {
      if (select.dataset.enterpriseRoles === '1') return;
      select.dataset.enterpriseRoles = '1';
      select.innerHTML = Object.entries(roleLabels)
        .filter(([role]) => role !== 'SUPER_ADMIN')
        .map(([role, label]) => `<option value="${role}">${label}</option>`)
        .join('');
    });
  }
})();
