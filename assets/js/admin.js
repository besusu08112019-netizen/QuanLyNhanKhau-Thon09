(() => {
  installPersonTableRenderFix();

  function installPersonTableRenderFix() {
    if (window.__thon09PersonTableRenderFixInstalled) return;
    window.__thon09PersonTableRenderFixInstalled = true;

    const text = value => value === null || value === undefined ? '' : String(value).trim();
    const safe = value => typeof escapeHtml === 'function' ? escapeHtml(value) : text(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
    const fmtDate = value => typeof formatDate === 'function' ? formatDate(value) : text(value);
    const normalize = value => typeof normalizeSearchText === 'function' ? normalizeSearchText(value) : text(value).toLowerCase();
    const ageExact = value => {
      const raw = text(value);
      if (!raw) return null;
      let birth = null;
      const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
      if (iso) birth = new Date(Number(iso[1]), Number(iso[2]) - 1, Number(iso[3]));
      const vn = !birth && raw.match(/^(\d{2})\/(\d{2})\/(\d{4})/);
      if (vn) birth = new Date(Number(vn[3]), Number(vn[2]) - 1, Number(vn[1]));
      if (!birth || Number.isNaN(birth.getTime())) return null;
      const today = new Date();
      let age = today.getFullYear() - birth.getFullYear();
      const birthdayPassed = today.getMonth() > birth.getMonth() || (today.getMonth() === birth.getMonth() && today.getDate() >= birth.getDate());
      if (!birthdayPassed) age -= 1;
      return age >= 0 ? age : null;
    };
    const relationshipOf = row => text(row.relationship || row.relationship_to_head || row.relationshipToHead || row.relation_to_head || row.household_relationship);

    window.personRow = function personRow(row = {}) {
      const party = Number(row.party_member || row.partyMember || 0) === 1;
      const residenceClass = row.presence_status === 'AWAY' ? 'person-badge-away' : (row.residency_status === 'TEMPORARY' ? 'person-badge-temp' : 'person-badge-home');
      const residenceText = row.presence_status === 'AWAY' ? 'Táº¡m váº¯ng' : (typeof residencyLabel === 'function' ? residencyLabel(row.residency_status) : (row.residency_status || 'ThÆ°á»ng trÃº'));
      const age = ageExact(row.date_of_birth);
      return '<tr>'
        + '<td><input type="checkbox" class="person-check" value="' + safe(row.id || '') + '"></td>'
        + '<td>' + safe(row.household_code || '') + '</td>'
        + '<td>' + safe(row.person_code || row.citizen_code || '') + '</td>'
        + '<td><button class="btn btn-link person-name-link" onclick="showPerson(' + Number(row.id || 0) + ')">' + safe(row.full_name || '') + '</button></td>'
        + '<td>' + safe(relationshipOf(row)) + '</td>'
        + '<td>' + fmtDate(row.date_of_birth) + '</td>'
        + '<td>' + (age === null ? '' : safe(age + ' tuá»•i')) + '</td>'
        + '<td>' + safe(row.gender || '') + '</td>'
        + '<td>' + safe(row.identity_number || '') + '</td>'
        + '<td><span class="person-badge ' + residenceClass + '">' + safe(residenceText) + '</span></td>'
        + '<td><span class="person-badge ' + (party ? 'person-badge-party' : 'person-badge-muted') + '">' + (party ? 'CÃ³' : 'KhÃ´ng') + '</span></td>'
        + '<td class="text-end"><button class="btn btn-sm person-row-btn" onclick="showPerson(' + Number(row.id || 0) + ')">Xem</button> <button class="btn btn-sm person-row-btn person-row-edit" onclick="openPersonForm(' + Number(row.id || 0) + ')">Sá»­a</button> <button class="btn btn-sm btn-outline-danger" onclick="deletePerson(' + Number(row.id || 0) + ')">XÃ³a</button></td>'
        + '</tr>';
    };

    window.loadPersons = async function loadPersons() {
      try {
        const searchText = normalize(App.persons.search || '');
        const householdText = (App.persons.householdId || '').trim();
        let items = [];
        let total = 0;
        if (searchText) {
          const extra = householdText ? { householdId: householdText } : {};
          const allItems = await fetchAllPaged('/api/persons', extra);
          const filtered = allItems.filter(row => [row.full_name, row.citizen_code, row.identity_number, row.personal_id, row.national_id, row.phone, row.household_code, row.current_address, row.household_address]
            .some(value => normalize(value).includes(searchText)));
          total = filtered.length;
          const startIndex = (App.persons.page - 1) * App.persons.pageSize;
          items = filtered.slice(startIndex, startIndex + App.persons.pageSize);
        } else {
          const params = new URLSearchParams({ page: App.persons.page, pageSize: App.persons.pageSize });
          if (householdText) params.set('householdId', householdText);
          const data = await api('/api/persons?' + params.toString());
          items = data.items || [];
          total = data.total || 0;
        }
        const rows = document.querySelector('#personRows');
        if (rows) rows.innerHTML = renderPersonRows(items);
        updateBulkDeleteButtons();
        renderPager('#personPager', { total, page: App.persons.page, pageSize: App.persons.pageSize }, page => { App.persons.page = page; window.loadPersons(); });
      } catch (error) {
        showToast('KhÃ´ng táº£i Ä‘Æ°á»£c danh sÃ¡ch nhÃ¢n kháº©u: ' + error.message, 'danger');
      }
    };
  }

  const start = () => {
    if (!isAdminUser()) return;
    injectAdminScreens();
    bindAdminNavigation();
  };
  window.ensureAdminScreens = start;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();

  function isAdminUser() {
    const role = window.App?.user?.role;
    return role === 'SUPER_ADMIN';
  }

  function injectAdminScreens() {
    const nav = document.querySelector('.sidebar .nav');
    const main = document.querySelector('.main-area');
    if (!nav || !main || document.querySelector('[data-screen="users"]')) return;
    nav.insertAdjacentHTML('beforeend', '<button class="nav-link" data-screen="users">NgÆ°á»i dÃ¹ng</button><button class="nav-link" data-screen="logs">Nháº­t kÃ½</button><button class="nav-link" data-screen="backups">Sao lÆ°u</button>');
    main.insertAdjacentHTML('beforeend', `<section id="usersScreen" class="screen"><div class="toolbar"><input id="userSearch" class="form-control" placeholder="TÃ¬m email, tÃªn ngÆ°á»i dÃ¹ng"><button id="userAddBtn" class="btn btn-primary">ThÃªm ngÆ°á»i dÃ¹ng</button></div><div class="content-card table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Email</th><th>Há» tÃªn</th><th>Vai trÃ²</th><th>Tráº¡ng thÃ¡i</th><th>ÄÄƒng nháº­p cuá»‘i</th><th></th></tr></thead><tbody id="userRows"></tbody></table></div><div id="userPager" class="pager"></div></section><section id="logsScreen" class="screen"><div class="toolbar"><input id="logSearch" class="form-control" placeholder="TÃ¬m ngÆ°á»i thá»±c hiá»‡n, ná»™i dung, mÃ£ dá»¯ liá»‡u"><select id="logPageSize" class="form-select w-auto"><option>50</option><option>100</option></select></div><div class="content-card table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Thá»i gian</th><th>NgÆ°á»i thá»±c hiá»‡n</th><th>Module</th><th>Thao tÃ¡c</th><th>Ná»™i dung</th></tr></thead><tbody id="logRows"></tbody></table></div><div id="logPager" class="pager"></div></section><section id="backupsScreen" class="screen"><div class="toolbar"><button id="backupCreateBtn" class="btn btn-primary">Táº¡o vÃ  táº£i báº£n sao lÆ°u SQL</button></div><div class="content-card table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Thá»i gian</th><th>TÃªn file</th><th>Dung lÆ°á»£ng</th><th>Tráº¡ng thÃ¡i</th><th>NgÆ°á»i táº¡o</th></tr></thead><tbody id="backupRows"></tbody></table></div><div id="backupPager" class="pager"></div></section><div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" id="userForm"><div class="modal-header"><h5 class="modal-title">NgÆ°á»i dÃ¹ng</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ÄÃ³ng"></button></div><div class="modal-body"><input type="hidden" name="id"><div class="mb-3"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div><div class="mb-3"><label class="form-label">Há» tÃªn</label><input name="displayName" class="form-control" required></div><div class="mb-3"><label class="form-label">Vai trÃ²</label><select name="role" class="form-select"><option value="ADMIN">Quáº£n trá»‹</option><option value="OFFICER">CÃ¡n bá»™</option><option value="VIEWER">Chá»‰ xem</option></select></div><div class="mb-3"><label class="form-label">Máº­t kháº©u</label><input name="password" type="password" class="form-control" minlength="8"><div class="form-text">Báº¯t buá»™c khi táº¡o má»›i, Ä‘á»ƒ trá»‘ng náº¿u khÃ´ng Ä‘á»•i.</div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Há»§y</button><button class="btn btn-primary" type="submit">LÆ°u</button></div></form></div></div>`);
    App.users = { page: 1, pageSize: 20, search: '' }; App.logs = { page: 1, pageSize: 50, search: '' }; App.backups = { page: 1, pageSize: 20 };
    App.modals.user = new bootstrap.Modal(document.querySelector('#userModal'));
    document.querySelector('#userSearch').addEventListener('input', debounce(() => { App.users.search = document.querySelector('#userSearch').value.trim(); App.users.page = 1; loadUsers(); }, 350));
    document.querySelector('#logSearch').addEventListener('input', debounce(() => { App.logs.search = document.querySelector('#logSearch').value.trim(); App.logs.page = 1; loadLogs(); }, 350));
    document.querySelector('#logPageSize').addEventListener('change', () => { App.logs.pageSize = Number(document.querySelector('#logPageSize').value); App.logs.page = 1; loadLogs(); });
    document.querySelector('#userAddBtn').addEventListener('click', () => openUserForm());
    document.querySelector('#userForm').addEventListener('submit', saveUser);
    document.querySelector('#backupCreateBtn').addEventListener('click', createBackup);
  }

  function bindAdminNavigation() { document.querySelectorAll('[data-screen="users"],[data-screen="logs"],[data-screen="backups"]').forEach(button => { button.addEventListener('click', () => { switchScreen(button.dataset.screen); document.querySelector('#screenTitle').textContent = { users: 'NgÆ°á»i dÃ¹ng', logs: 'Nháº­t kÃ½', backups: 'Sao lÆ°u' }[button.dataset.screen]; if (button.dataset.screen === 'users') loadUsers(); if (button.dataset.screen === 'logs') loadLogs(); if (button.dataset.screen === 'backups') loadBackups(); }); }); }

  async function loadUsers() { try { const data = await api('/api/users?' + new URLSearchParams(App.users).toString()); if (typeof window.renderUserRowsSprint8 === 'function') window.renderUserRowsSprint8(data); else document.querySelector('#userRows').innerHTML = data.items.map(row => `<tr><td>${escapeHtml(row.email)}</td><td>${escapeHtml(row.display_name)}</td><td>${roleLabel(row.role)}</td><td>${statusLabel(row.status)}</td><td>${escapeHtml(row.last_login_at || '')}</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="openUserForm(${row.id})">Sá»­a</button> <button class="btn btn-sm btn-outline-warning" onclick="toggleUser(${row.id}, '${row.status === 'ACTIVE' ? 'lock' : 'unlock'}')">${row.status === 'ACTIVE' ? 'KhÃ³a' : 'Má»Ÿ khÃ³a'}</button> <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${row.id})">XÃ³a</button></td></tr>`).join('') || emptyRow(6, 'ChÆ°a cÃ³ ngÆ°á»i dÃ¹ng'); renderPager('#userPager', data, page => { App.users.page = page; loadUsers(); }); } catch (error) { showToast(error.message, 'danger'); } }

  window.openUserForm = async function openUserForm(id = null) { const form = document.querySelector('#userForm'); form.reset(); form.elements.id.value = ''; form.elements.email.disabled = false; if (form.elements.username) form.elements.username.disabled = false; if (id) { const row = await api('/api/users/' + id); setForm(form, { id: row.id, username: row.username, email: row.email, displayName: row.displayName, phone: row.phone, position: row.position, role: row.role === 'SUPER_ADMIN' ? 'ADMIN' : row.role }); form.elements.email.disabled = true; if (form.elements.username) form.elements.username.disabled = true; } App.modals.user.show(); };
  async function saveUser(event) { event.preventDefault(); const data = formData(event.currentTarget); const id = data.id; delete data.id; try { await api(id ? '/api/users/' + id : '/api/users', { method: id ? 'PUT' : 'POST', body: data }); App.modals.user.hide(); showToast('ÄÃ£ lÆ°u ngÆ°á»i dÃ¹ng'); loadUsers(); } catch (error) { showToast(error.message, 'danger'); } }
  window.toggleUser = async function toggleUser(id, action) { try { await api(`/api/users/${id}/${action}`, { method: 'POST' }); showToast(action === 'lock' ? 'ÄÃ£ khÃ³a ngÆ°á»i dÃ¹ng' : 'ÄÃ£ má»Ÿ khÃ³a ngÆ°á»i dÃ¹ng'); loadUsers(); } catch (error) { showToast(error.message, 'danger'); } };
  window.deleteUser = async function deleteUser(id) { if (!confirm('XÃ³a ngÆ°á»i dÃ¹ng nÃ y?')) return; try { await api('/api/users/' + id, { method: 'DELETE' }); showToast('ÄÃ£ xÃ³a ngÆ°á»i dÃ¹ng'); loadUsers(); } catch (error) { showToast(error.message, 'danger'); } };
  async function loadLogs() { try { const data = await api('/api/logs?' + new URLSearchParams(App.logs).toString()); document.querySelector('#logRows').innerHTML = data.items.map(row => `<tr><td>${escapeHtml(row.created_at)}</td><td>${escapeHtml(row.actor_email || '')}</td><td>${escapeHtml(row.module)}</td><td>${escapeHtml(row.action)}</td><td>${escapeHtml(row.message)}</td></tr>`).join('') || emptyRow(5, 'ChÆ°a cÃ³ nháº­t kÃ½'); renderPager('#logPager', data, page => { App.logs.page = page; loadLogs(); }); } catch (error) { showToast(error.message, 'danger'); } }
  async function loadBackups() { try { const data = await api('/api/backups?' + new URLSearchParams(App.backups).toString()); document.querySelector('#backupRows').innerHTML = data.items.map(row => `<tr><td>${escapeHtml(row.created_at)}</td><td>${escapeHtml(row.file_name)}</td><td>${number(row.file_size || 0)} byte</td><td>${escapeHtml(row.status)}</td><td>${escapeHtml(row.created_by_email || '')}</td></tr>`).join('') || emptyRow(5, 'ChÆ°a cÃ³ báº£n sao lÆ°u'); renderPager('#backupPager', data, page => { App.backups.page = page; loadBackups(); }); } catch (error) { showToast(error.message, 'danger'); } }
  async function createBackup() { try { const response = await fetch('/api/backups', { method: 'POST', headers: { Authorization: `Bearer ${App.token}`, 'X-CSRF-Token': App.csrfToken || '' } }); if (!response.ok) { const payload = await response.json().catch(() => null); throw new Error(payload?.error?.message || 'KhÃ´ng táº¡o Ä‘Æ°á»£c báº£n sao lÆ°u'); } const blob = await response.blob(); const name = /filename="?([^";]+)"?/i.exec(response.headers.get('Content-Disposition') || '')?.[1] || `backup_thon09_${Date.now()}.sql`; const url = URL.createObjectURL(blob); const link = document.createElement('a'); link.href = url; link.download = name; document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(url); showToast('ÄÃ£ táº¡o báº£n sao lÆ°u'); loadBackups(); } catch (error) { showToast(error.message, 'danger'); } }
  function statusLabel(status) { return status === 'ACTIVE' ? 'Hoáº¡t Ä‘á»™ng' : status === 'INACTIVE' ? 'ÄÃ£ khÃ³a' : status; }
})();
