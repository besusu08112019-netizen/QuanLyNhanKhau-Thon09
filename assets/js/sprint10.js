(() => {
  document.addEventListener('DOMContentLoaded', bootSprint10);
  document.addEventListener('tenant:auth-state', event => { if (event.detail?.authenticated) bootSprint10(); });

  function bootSprint10() {
    injectSprint10Screens();
    bindSprint10Menu();
    patchSprint10Dashboard();
    patchSprint10Reports();
    patchSprint10Import();
    patchSprint10Users();
    registerSprint10PlatformActions();
  }

  function registerModal(id) {
    const modal = document.querySelector('#' + id);
    const service = window.TenantAppPlatform?.modals;
    if (modal && service?.registerBootstrap) service.registerBootstrap(id, '#' + id);
    return modal;
  }

  function openModal(id) {
    const service = window.TenantAppPlatform?.modals;
    if (service?.open && service.open(id)) return;
    window.bootstrap?.Modal?.getOrCreateInstance?.(document.querySelector('#' + id))?.show();
  }

  function closeModal(id) {
    const service = window.TenantAppPlatform?.modals;
    if (service?.close && service.close(id)) return;
    window.bootstrap?.Modal?.getOrCreateInstance?.(document.querySelector('#' + id))?.hide();
  }

  function injectSprint10Screens() {
    const main = document.querySelector('.main-area');
    if (!main) return;
    if (!document.querySelector('#logsScreen')) main.insertAdjacentHTML('beforeend', '<section id="logsScreen" class="screen"><div class="toolbar"><input id="logSearch" class="form-control" placeholder="TÃ¬m user, hÃ nh Ä‘á»™ng, module, chi tiáº¿t"><select id="logAction" class="form-select w-auto"><option value="">Táº¥t cáº£ hÃ nh Ä‘á»™ng</option><option value="login">ÄÄƒng nháº­p</option><option value="logout">ÄÄƒng xuáº¥t</option><option value="create">ThÃªm</option><option value="update">Sá»­a</option><option value="delete">XÃ³a</option><option value="read">Xem</option><option value="export">Xuáº¥t dá»¯ liá»‡u</option><option value="restore">KhÃ´i phá»¥c</option><option value="reset_password">Äáº·t láº¡i máº­t kháº©u</option></select></div><div class="content-card table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Thá»i gian</th><th>NgÆ°á»i dÃ¹ng</th><th>Thao tÃ¡c</th><th>Module</th><th>IP</th><th>Chi tiáº¿t</th></tr></thead><tbody id="logRows"></tbody></table></div><div id="logPager" class="pager"></div></section>');
    if (!document.querySelector('#backupsScreen')) main.insertAdjacentHTML('beforeend', '<section id="backupsScreen" class="screen"><div class="toolbar"><button id="backupCreateBtn" class="btn btn-primary">Backup database</button><button id="backupAutoBtn" class="btn btn-outline-secondary">Auto backup: daily</button></div><div class="content-card table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Time</th><th>File</th><th>Size</th><th>Status</th><th>User</th><th>Download</th></tr></thead><tbody id="backupRows"></tbody></table></div><div id="backupPager" class="pager"></div></section>');
    if (!document.querySelector('#restoreScreen')) main.insertAdjacentHTML('beforeend', '<section id="restoreScreen" class="screen"><form id="restoreForm" class="content-card"><label class="form-label">Restore backup .sql</label><input name="file" type="file" accept=".sql" class="form-control mb-3"><textarea name="sql" class="form-control font-monospace" rows="12" placeholder="Hoáº·c dÃ¡n SQL táº¡i Ä‘Ã¢y"></textarea><div class="text-end mt-3"><button class="btn btn-danger" type="submit">Restore backup</button></div></form></section>');
    if (!document.querySelector('#usersScreen')) main.insertAdjacentHTML('beforeend', '<section id="usersScreen" class="screen"><div class="toolbar"><input id="userSearch" class="form-control" placeholder="TÃ¬m username, há» tÃªn, email, sá»‘ Ä‘iá»‡n thoáº¡i"><select id="userPageSize" class="form-select w-auto"><option>20</option><option>50</option></select><button id="userAddBtn" class="btn btn-primary">Táº¡o tÃ i khoáº£n</button></div><div class="content-card table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Username</th><th>Há» tÃªn</th><th>Email</th><th>Äiá»‡n thoáº¡i</th><th>Chá»©c vá»¥</th><th>Vai trÃ²</th><th>Tráº¡ng thÃ¡i</th><th>NgÃ y táº¡o</th><th>Láº§n Ä‘Äƒng nháº­p cuá»‘i</th><th></th></tr></thead><tbody id="userRows"></tbody></table></div><div id="userPager" class="pager"></div></section>');
    if (!document.querySelector('#userModal')) document.body.insertAdjacentHTML('beforeend', '<div class="modal fade" id="userModal" tabindex="-1"><div class="modal-dialog"><form id="userForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">Account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="row g-3"><div class="col-md-6"><label class="form-label">Username</label><input name="username" class="form-control" required></div><div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div><div class="col-md-6"><label class="form-label">Full name</label><input name="displayName" class="form-control" required></div><div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control"></div><div class="col-md-6"><label class="form-label">Position</label><input name="position" class="form-control"></div><div class="col-md-6"><label class="form-label">Role</label><select name="role" class="form-select"><option value="SUPER_ADMIN">Super Admin</option><option value="ADMIN">Admin</option><option value="OFFICER">Staff</option><option value="VIEWER">Viewer</option></select></div><div class="col-12"><label class="form-label">Password</label><input name="password" type="password" minlength="8" class="form-control"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div></form></div></div>');
    App.logs ||= { page: 1, pageSize: 50, search: '', action: '' };
    App.backups ||= { page: 1, pageSize: 20 };
    App.users ||= { page: 1, pageSize: 20, search: '' };
    registerModal('userModal');
  }

  function bindSprint10Menu() {
    if (!window.__TenantAppSprint10ScreenChangeBound) {
      window.__TenantAppSprint10ScreenChangeBound = true;
      document.addEventListener('tenant:screen-change', event => {
        loadSprint10Screen(event.detail?.screen);
      });
    }
    bindOnce('#logSearch', 'input', debounce(() => { App.logs.search = document.querySelector('#logSearch').value.trim(); App.logs.page = 1; loadLogs10(); }, 300));
    bindOnce('#logAction', 'change', () => { App.logs.action = document.querySelector('#logAction').value; App.logs.page = 1; loadLogs10(); });
    bindOnce('#backupCreateBtn', 'click', createBackup10);
    bindOnce('#restoreForm', 'submit', restoreBackup10);
    bindOnce('#userSearch', 'input', debounce(() => { App.users.search = document.querySelector('#userSearch').value.trim(); App.users.page = 1; loadUsers10(); }, 300));
    bindOnce('#userPageSize', 'change', () => { App.users.pageSize = Number(document.querySelector('#userPageSize').value); App.users.page = 1; loadUsers10(); });
    bindOnce('#userAddBtn', 'click', () => openUserForm10());
    bindOnce('#userForm', 'submit', saveUser10);
  }

  function loadSprint10Screen(screen) {
    if (screen === 'reports' && typeof window.TenantAppViewReport === 'function') window.TenantAppViewReport();
    if (screen === 'logs') loadLogs10();
    if (screen === 'backups') loadBackups10();
    if (screen === 'restore') {}
    if (screen === 'users') loadUsers10();
    if (screen === 'temporaryResidence') loadPresence10('TEMPORARY', '#temporaryResidenceRows');
    if (screen === 'temporaryAbsence') loadPresence10('AWAY', '#temporaryAbsenceRows');
  }


  function patchSprint10Dashboard() {}

  function renderDashboard10(raw) {
    const data = raw?.data || raw || {};
    const m = data.metrics || {};
    const cards = [
      ['Tá»•ng sá»‘ há»™', m.total_households, 'fa-house'],
      ['Tá»•ng sá»‘ nhÃ¢n kháº©u', m.total_citizens, 'fa-users'],
      ['Nam', m.male_count, 'fa-mars'],
      ['Ná»¯', m.female_count, 'fa-venus'],
      ['Chá»§ há»™', m.household_head_count, 'fa-user-check'],
      ['Táº¡m trÃº', m.temporary_count, 'fa-location-dot'],
      ['Táº¡m váº¯ng', m.away_count, 'fa-person-walking-arrow-right'],
      ['Tráº» em', m.children_count, 'fa-child'],
      ['NgÆ°á»i cao tuá»•i', m.elderly_count, 'fa-person-cane'],
      ['Äá»™ tuá»•i lao Ä‘á»™ng', m.working_age_count, 'fa-briefcase'],
      ['Há»™ nghÃ¨o', m.poor_households, 'fa-hand-holding-heart'],
      ['Há»™ cáº­n nghÃ¨o', m.near_poor_households, 'fa-scale-balanced']
    ];
    const host = document.querySelector('#dashboardCards');
    if (host) host.innerHTML = cards.map(([label, value, icon]) => '<div class="col-sm-6 col-xl-3"><div class="metric-card admin-metric"><i class="fa-solid ' + icon + '"></i><div><div class="metric-label">' + escapeHtml(label) + '</div><div class="metric-value">' + number(Number(value || 0)) + '</div></div></div></div>').join('');
    ensureChart10('genderChart', 'DÃ¢n sá»‘ theo giá»›i tÃ­nh'); ensureChart10('ageChart', 'DÃ¢n sá»‘ theo Ä‘á»™ tuá»•i'); ensureChart10('householdChart', 'TÃ¬nh tráº¡ng há»™'); ensureChart10('residencyChart', 'CÆ° trÃº'); ensureChart10('hamletChart', 'DÃ¢n sá»‘ theo thÃ´n'); ensureChart10('monthlyChart', 'TÄƒng giáº£m dÃ¢n sá»‘ theo thÃ¡ng'); ensureChart10('povertyChart', 'Biá»ƒu Ä‘á»“ há»™ nghÃ¨o');
    renderChart10('#genderChart', data.charts?.population || []); renderChart10('#ageChart', data.charts?.ages || []); renderChart10('#householdChart', data.charts?.households || []); renderChart10('#residencyChart', data.charts?.residency || []); renderChart10('#hamletChart', data.charts?.hamlets || []); renderChart10('#monthlyChart', data.charts?.monthlyChanges || []); renderChart10('#povertyChart', data.charts?.poverty || []);
  }

  function ensureChart10(id, title) {
    if (document.querySelector('#' + id)) return;
    const row = document.querySelector('#dashboardScreen .row.g-3.mt-1');
    if (row) row.insertAdjacentHTML('beforeend', '<div class="col-lg-4"><div class="content-card"><h3 class="section-title">' + escapeHtml(title) + '</h3><div id="' + id + '" class="chart-list"></div></div></div>');
  }

  function renderChart10(selector, items) {
    const normalized = (items || []).map(item => ({ label: item.label ?? item.name ?? item.type ?? 'KhÃ¡c', value: Number(item.value ?? item.total ?? item.count ?? 0) }));
    if (typeof window.renderChart === 'function') { window.renderChart(selector, normalized); return; }
    const host = document.querySelector(selector); if (!host) return;
    host.innerHTML = normalized.length ? '<ul class="list-group list-group-flush">' + normalized.map(i => '<li class="list-group-item d-flex justify-content-between"><span>' + escapeHtml(i.label) + '</span><strong>' + number(i.value) + '</strong></li>').join('') + '</ul>' : '<p class="text-muted mb-0">KhÃ´ng cÃ³ dá»¯ liá»‡u</p>';
  }


  function patchSprint10Reports() { return; }

  async function loadReport10() { if (typeof window.TenantAppViewReport === 'function') return window.TenantAppViewReport(); }

  async function downloadReport10(kind) { return; }
  async function printReport10() { return; }

  function patchSprint10Import() {
    const personLink = document.querySelector('#importScreen a[download*="NhanKhau"], #importScreen a[href*="Mau_Import_NhanKhau"]');
    const householdLink = document.querySelector('#importScreen a[download*="HoDan"], #importScreen a[href*="Mau_Import_HoDan"]');
    if (personLink) { personLink.href = '/api/import/template?type=person'; personLink.download = 'Mau_Import_NhanKhau.xlsx'; }
    if (householdLink) { householdLink.href = '/api/import/template?type=household'; householdLink.download = 'Mau_Import_HoDan.xlsx'; }
    const result = document.querySelector('#importResult');
    if (result && !result.dataset.sprint10) result.dataset.sprint10 = '1';
  }

  function patchSprint10Users() { window.openUserForm = openUserForm10; window.resetUserPassword = resetUserPassword10; }

  function actorIsSuperAdmin10() {
    return String(App.user?.role || '').toUpperCase() === 'SUPER_ADMIN';
  }

  function protectedUserActions10(row) {
    const isSuperAdminRow = String(row.role || '').toUpperCase() === 'SUPER_ADMIN';
    if (isSuperAdminRow && !actorIsSuperAdmin10()) {
      return '<span class="text-muted small">&#272;&#432;&#7907;c b&#7843;o v&#7879;</span>';
    }
    if (isSuperAdminRow) {
      return '<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="users.edit" data-id="' + row.id + '">S&#7917;a</button> <span class="text-muted small ms-1">B&#7843;o v&#7879; m&#7853;t kh&#7849;u</span>';
    }
    const action = row.status === 'ACTIVE' ? 'lock' : 'unlock';
    const actionLabel = action === 'lock' ? 'Kh&oacute;a' : 'M&#7903; kh&oacute;a';
    return '<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="users.edit" data-id="' + row.id + '">S&#7917;a</button> <button class="btn btn-sm btn-outline-warning" type="button" data-platform-action="users.toggle" data-id="' + row.id + '" data-action="' + action + '">' + actionLabel + '</button> <button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="users.resetPassword" data-id="' + row.id + '">&#272;&#7863;t l&#7841;i m&#7853;t kh&#7849;u</button> <button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="users.delete" data-id="' + row.id + '">X&oacute;a</button>';
  }

  async function loadUsers10() {
    if (typeof window.TenantAppCanAccess === 'function' && !window.TenantAppCanAccess('users', 'read')) return;
    const rows = document.querySelector('#userRows');
    if (!rows) return;
    const data = await api('/api/users?' + new URLSearchParams(App.users));
    rows.innerHTML = (data.items || []).map(row => {
      return '<tr><td>' + escapeHtml(row.username || '') + '</td><td>' + escapeHtml(row.display_name || '') + '</td><td>' + escapeHtml(row.email || '') + '</td><td>' + escapeHtml(row.phone || '') + '</td><td>' + escapeHtml(row.position || '') + '</td><td>' + roleLabel(row.role) + '</td><td>' + escapeHtml(row.status || '') + '</td><td>' + escapeHtml(row.created_at || '') + '</td><td>' + escapeHtml(row.last_login_at || '') + '</td><td class="text-end">' + protectedUserActions10(row) + '</td></tr>';
    }).join('') || emptyRow(10, 'Kh&ocirc;ng c&oacute; t&agrave;i kho&#7843;n');
    renderPager('#userPager', data, page => { App.users.page = page; loadUsers10(); });
  }

  window.toggleUser = async function(id, action) { await api('/api/users/' + id + '/' + action, { method: 'POST' }); showToast('ÄÃ£ cáº­p nháº­t tráº¡ng thÃ¡i'); loadUsers10(); };
  window.deleteUser = async function(id) { if (!confirm('XÃ³a tÃ i khoáº£n nÃ y?')) return; await api('/api/users/' + id, { method: 'DELETE' }); showToast('ÄÃ£ xÃ³a tÃ i khoáº£n'); loadUsers10(); };
  async function openUserForm10(id = null) {
    const form = document.querySelector('#userForm'); form.reset(); form.elements.id.value = ''; form.elements.email.disabled = false; form.elements.username.disabled = false;
    if (id) { const row = await api('/api/users/' + id); setForm(form, { id: row.id, username: row.username, email: row.email, displayName: row.displayName, phone: row.phone, position: row.position, role: row.role }); form.elements.email.disabled = true; form.elements.username.disabled = true; }
    openModal('userModal');
  }
  async function saveUser10(event) {
    event.preventDefault(); const data = formData(event.currentTarget); const id = data.id; delete data.id;
    await api(id ? '/api/users/' + id : '/api/users', { method: id ? 'PUT' : 'POST', body: data });
    closeModal('userModal'); showToast('ÄÃ£ lÆ°u tÃ i khoáº£n'); loadUsers10();
  }
  async function resetUserPassword10(id) {
    const row = await api('/api/users/' + id);
    if (String(row.role || '').toUpperCase() === 'SUPER_ADMIN') {
      showToast('TÃ i khoáº£n Super Admin Ä‘Æ°á»£c báº£o vá»‡, khÃ´ng thá»ƒ Ä‘áº·t láº¡i máº­t kháº©u táº¡i Ä‘Ã¢y', 'warning');
      return;
    }
    const password = prompt('Nháº­p máº­t kháº©u má»›i tá»‘i thiá»ƒu 8 kÃ½ tá»±'); if (!password) return;
    await api('/api/users/' + id + '/reset-password', { method: 'POST', body: { password } }); showToast('ÄÃ£ Ä‘áº·t láº¡i máº­t kháº©u');
  }

  async function loadLogs10() {
    const data = await api('/api/logs?' + new URLSearchParams(App.logs));
    document.querySelector('#logRows').innerHTML = (data.items || []).map(row => '<tr><td>' + escapeHtml(row.created_at || '') + '</td><td>' + escapeHtml(row.actor_email || '') + '</td><td>' + escapeHtml(row.action || '') + '</td><td>' + escapeHtml(row.module || '') + '</td><td>' + escapeHtml(row.ip_address || '') + '</td><td>' + escapeHtml(row.message || '') + '</td></tr>').join('') || emptyRow(6, 'ChÆ°a cÃ³ nháº­t kÃ½');
    renderPager('#logPager', data, page => { App.logs.page = page; loadLogs10(); });
  }

  async function loadBackups10() {
    const data = await api('/api/backups?' + new URLSearchParams(App.backups));
    document.querySelector('#backupRows').innerHTML = (data.items || []).map(row => '<tr><td>' + escapeHtml(row.created_at || '') + '</td><td>' + escapeHtml(row.file_name || '') + '</td><td>' + number(row.file_size || 0) + ' byte</td><td>' + escapeHtml(row.status || '') + '</td><td>' + escapeHtml(row.created_by_email || '') + '</td><td><button class="btn btn-sm btn-outline-success" type="button" data-platform-action="backups.create">Download new</button></td></tr>').join('') || emptyRow(6, 'ChÆ°a cÃ³ lá»‹ch sá»­ backup');
    renderPager('#backupPager', data, page => { App.backups.page = page; loadBackups10(); });
  }
  window.createBackup10 = createBackup10;
  async function createBackup10() {
    const response = await fetch('/api/backups', { method: 'POST', headers: { Authorization: 'Bearer ' + App.token, 'X-CSRF-Token': App.csrfToken || '' } });
    if (!response.ok) throw new Error('KhÃ´ng táº¡o Ä‘Æ°á»£c backup');
    const blob = await response.blob(); const name = /filename="?([^";]+)"?/i.exec(response.headers.get('Content-Disposition') || '')?.[1] || 'Backup.sql';
    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    showToast('ÄÃ£ backup database'); loadBackups10();
  }
  async function restoreBackup10(event) {
    event.preventDefault(); if (!confirm('Restore sáº½ thay Ä‘á»•i database. Tiáº¿p tá»¥c?')) return;
    const file = event.currentTarget.elements.file?.files?.[0];
    if (file) {
      const body = new FormData(event.currentTarget);
      const response = await fetch('/api/backups/restore', { method: 'POST', headers: { Authorization: 'Bearer ' + App.token, 'X-CSRF-Token': App.csrfToken || '' }, body });
      const payload = await response.json().catch(() => null); if (!response.ok || !payload?.ok) throw new Error(payload?.error?.message || 'Restore lá»—i');
    } else await api('/api/backups/restore', { method: 'POST', body: formData(event.currentTarget) });
    showToast('ÄÃ£ restore backup');
  }

  async function loadPresence10(value, selector) {
    const params = value === 'TEMPORARY' ? { pageSize: 100 } : { presenceStatus: 'AWAY', pageSize: 100 };
    const data = await api('/api/persons?' + new URLSearchParams(params));
    let items = data.items || [];
    if (value === 'TEMPORARY') {
      items = items.filter(row => {
        const temporaryFlag = row.temporary_residence ?? row.temporaryResidence ?? row.is_temporary_residence;
        if (temporaryFlag === true || temporaryFlag === 1 || temporaryFlag === '1' || temporaryFlag === 'true') return true;
        return String(row.residency_status || row.residencyStatus || '').toUpperCase() === 'TEMPORARY';
      });
    }
    document.querySelector(selector).innerHTML = presenceTable10(items);
  }

  function presenceTable10(items) {
    const rows = items.map(row => '<tr>'
      + '<td>' + escapeHtml(row.household_code || '') + '</td>'
      + '<td>' + escapeHtml(row.full_name || '') + '</td>'
      + '<td>' + formatDate(row.date_of_birth) + '</td>'
      + '<td>' + escapeHtml(row.identity_number || '') + '</td>'
      + '<td>' + escapeHtml(row.phone || '') + '</td>'
      + '<td class="text-end"><button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="persons.detail" data-id="' + Number(row.id || 0) + '">Xem</button></td>'
      + '</tr>').join('') || '<tr><td colspan="6" class="text-center text-muted py-3">KhÃ´ng cÃ³ dá»¯ liá»‡u</td></tr>';
    return '<table class="table table-hover table-bordered align-middle mb-0"><thead><tr><th>MÃ£ há»™</th><th>Há» tÃªn</th><th>NgÃ y sinh</th><th>CCCD</th><th>Äiá»‡n thoáº¡i</th><th class="text-end">Thao tÃ¡c</th></tr></thead><tbody>' + rows + '</tbody></table>';
  }

  function bindReportControl10(selector, event, handler) {
    const el = document.querySelector(selector);
    if (!el) return;
    if (el.dataset.reportBound === event) return;
    el.dataset.reportBound = event;
    el.addEventListener(event, handler);
  }

  function table10(headers, rows) { return '<table class="table table-hover table-bordered align-middle mb-0"><thead><tr>' + headers.map(h => '<th>' + escapeHtml(h) + '</th>').join('') + '</tr></thead><tbody>' + (rows.length ? rows.map(r => '<tr>' + r.map(c => '<td>' + escapeHtml(c ?? '') + '</td>').join('') + '</tr>').join('') : '<tr><td colspan="' + Math.max(1, headers.length) + '" class="text-center text-muted py-3">KhÃ´ng cÃ³ dá»¯ liá»‡u</td></tr>') + '</tbody></table>'; }
  function openPersonDetailAction10(id) {
    if (!id) return;
    if (typeof window.showPerson === 'function') {
      window.showPerson(id);
      return;
    }
    const navigation = window.TenantAppPlatform?.navigation;
    if (navigation?.navigate) navigation.navigate({ screenId: 'persons', moduleKey: 'persons', action: 'detail', params: { id } });
  }
  function registerSprint10PlatformActions() {
    if (window.__TenantAppSprint10ActionsRegistered || !window.TenantAppPlatform?.actions) return;
    window.__TenantAppSprint10ActionsRegistered = true;
    window.TenantAppPlatform.actions
      .register({ key: 'users.edit', handler: ({ dataset }) => openUserForm10(Number(dataset.id || 0)) })
      .register({ key: 'users.toggle', handler: ({ dataset }) => window.toggleUser(Number(dataset.id || 0), dataset.action) })
      .register({ key: 'users.resetPassword', handler: ({ dataset }) => resetUserPassword10(Number(dataset.id || 0)) })
      .register({ key: 'users.delete', handler: ({ dataset }) => window.deleteUser(Number(dataset.id || 0)) })
      .register({ key: 'persons.detail', handler: ({ dataset }) => openPersonDetailAction10(Number(dataset.id || dataset.personId || 0)) })
      .register({ key: 'backups.create', handler: () => createBackup10() });
  }
  function bindOnce(selector, event, handler) { const el = document.querySelector(selector); if (!el || el.dataset['bound' + event]) return; el.dataset['bound' + event] = '1'; el.addEventListener(event, handler); }
})();

