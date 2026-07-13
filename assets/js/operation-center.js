(function () {
  'use strict';

  const state = { booted: false, taskStatus: loadTaskStatus(), timers: {} };
  const API = '/api/operation-center';

  function qs(selector, root) { return (root || document).querySelector(selector); }
  function qsa(selector, root) { return Array.from((root || document).querySelectorAll(selector)); }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;' }[c])); }
  function num(value) { return new Intl.NumberFormat('vi-VN').format(Number(value || 0)); }
  function pct(value) { return Math.max(0, Math.min(100, Number(value || 0))); }
  function toast(message, type) { if (typeof window.showToast === 'function') window.showToast(message, type || 'info'); }
  function openModal(id) {
    const service = window.Thon09Platform?.modals;
    if (service?.open && service.open(id)) return;
    window.bootstrap?.Modal?.getOrCreateInstance?.(qs('#' + id))?.show();
  }
  function closeModal(id) {
    const service = window.Thon09Platform?.modals;
    if (service?.close && service.close(id)) return;
    window.bootstrap?.Modal?.getOrCreateInstance?.(qs('#' + id))?.hide();
  }
  function apiGet(path, params, ttl) {
    const query = params ? '?' + new URLSearchParams(params).toString() : '';
    if (typeof window.api === 'function') return window.api(path + query, { cacheTtl: ttl || 0 });
    const headers = { Accept: 'application/json' };
    if (window.App && window.App.token) headers.Authorization = 'Bearer ' + window.App.token;
    return fetch(path + query, { headers, cache: 'no-store' }).then(r => r.json()).then(payload => {
      if (!payload || payload.ok === false) throw new Error(payload && payload.error && payload.error.message || 'Không tải được dữ liệu');
      return payload.data;
    });
  }
  function unwrap(payload) { return payload && payload.data ? payload.data : (payload || {}); }
  function loading(host, text) { if (host) host.innerHTML = '<div class="operation-empty">' + esc(text || 'Đang tải dữ liệu') + '</div>'; }
  function widgetError(host, error) { if (host) host.innerHTML = '<div class="operation-error">' + esc(error && error.message ? error.message : 'Widget tạm thời không tải được') + '</div>'; }

  function installStyles() {
    if (document.getElementById('operation-center-style')) return;
    const style = document.createElement('style');
    style.id = 'operation-center-style';
    style.textContent = `
      .operation-center-screen{background:#f5f8f6}.operation-shell{display:grid;gap:16px}.operation-toolbar{display:flex;gap:14px;align-items:center;justify-content:space-between}.operation-toolbar-main{display:grid;gap:10px;flex:1}.operation-toolbar-main h3{margin:0;color:#0b6b3a;font-size:20px;font-weight:850}.operation-toolbar-actions,.operation-filter-inline,.operation-log-filters{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.operation-toolbar-actions .form-select{width:auto;min-width:112px}.operation-search-wrap{position:relative;max-width:720px}.operation-search-wrap>i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#64748b}.operation-search-wrap input{padding-left:38px;min-height:42px;border-radius:12px}.operation-search-results{position:absolute;z-index:900;top:48px;left:0;right:0;background:#fff;border:1px solid #dbe5ef;border-radius:12px;box-shadow:0 16px 36px rgba(15,23,42,.14);overflow:hidden}.operation-search-results button{display:block;width:100%;border:0;background:#fff;text-align:left;padding:10px 12px;border-bottom:1px solid #edf2f7}.operation-search-results button:hover{background:#eef7f2}.operation-search-results strong{display:block;color:#172033}.operation-search-results span{display:block;color:#64748b;font-size:12px}.operation-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.operation-panel{min-height:280px}.operation-panel-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:12px}.operation-panel-head h4{margin:0;color:#0f3768;font-size:15px;font-weight:850;text-transform:uppercase}.operation-list{display:grid;gap:8px}.operation-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:10px;text-align:left}.operation-item strong{display:block;color:#172033}.operation-item small{display:block;color:#64748b}.operation-priority-high{border-left:4px solid #dc2626}.operation-priority-medium{border-left:4px solid #d97706}.operation-priority-low{border-left:4px solid #2563eb}.operation-badge{border-radius:999px;background:#eef2f7;color:#334155;font-size:12px;font-weight:800;padding:4px 8px}.operation-actions{display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap}.operation-actions button{border-radius:8px}.operation-progress{display:grid;gap:11px}.operation-progress-row{display:grid;gap:5px}.operation-progress-row>div{display:flex;justify-content:space-between;gap:10px}.operation-progress-row i{display:block;height:10px;border-radius:999px;background:#edf2f7;overflow:hidden}.operation-progress-row b{display:block;height:100%;width:var(--value);background:#0b6b3a}.operation-metric-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.operation-metric{border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:10px}.operation-metric span{display:block;color:#64748b;font-size:12px}.operation-metric strong{font-size:22px;color:#0f3768}.operation-timeline{display:grid;gap:8px;max-height:420px;overflow:auto}.operation-timeline-item{border-left:3px solid #0b6b3a;padding:4px 0 8px 10px}.operation-timeline-item strong{display:block}.operation-log-table{max-height:420px;overflow:auto}.operation-empty,.operation-error{padding:14px;color:#64748b;background:#f8fafc;border-radius:10px}.operation-error{color:#b91c1c;background:#fef2f2}.operation-profile-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.operation-profile-grid dl{display:grid;grid-template-columns:120px minmax(0,1fr);gap:6px 10px;margin:0}.operation-profile-grid dt{color:#64748b}.operation-profile-grid dd{margin:0;font-weight:700}.operation-member-list,.operation-file-list{display:grid;gap:6px}.operation-member-list span,.operation-file-list span{border:1px solid #e2e8f0;border-radius:8px;padding:7px 9px;background:#fff}.operation-panel .table{margin-bottom:0}@media(max-width:1100px){.operation-grid{grid-template-columns:1fr}.operation-toolbar{align-items:stretch;flex-direction:column}.operation-toolbar-actions .btn,.operation-toolbar-actions .form-select{flex:1 1 auto}}@media(max-width:640px){.operation-center-screen{padding:12px}.operation-toolbar-actions,.operation-filter-inline,.operation-log-filters{display:grid;grid-template-columns:1fr;width:100%}.operation-search-wrap{max-width:none}.operation-metric-grid,.operation-profile-grid{grid-template-columns:1fr}.operation-item{grid-template-columns:1fr}.operation-actions{justify-content:flex-start}.operation-profile-grid dl{grid-template-columns:96px minmax(0,1fr)}}`;
    document.head.appendChild(style);
  }

  function loadTaskStatus() {
    try { return JSON.parse(localStorage.getItem('thon09_operation_task_status') || '{}') || {}; } catch (_) { return {}; }
  }
  function saveTaskStatus() { localStorage.setItem('thon09_operation_task_status', JSON.stringify(state.taskStatus)); }

  function boot() {
    installStyles();
    registerOperationPlatformActions();
    bindStaticEvents();
    document.addEventListener('thon09:auth-state', event => { if (event.detail && event.detail.authenticated && isActive()) loadAll(); });
    if (isActive()) loadAll();
  }

  function registerOperationPlatformActions() {
    const actions = window.Thon09Platform && window.Thon09Platform.actions;
    if (!actions || typeof actions.register !== 'function') return;
    actions
      .register('operationCenter.refresh', context => context.dataset.operationRefresh === 'progress' ? loadProgress() : loadNotifications())
      .register('operationCenter.export', context => exportReport(context.dataset.operationExport))
      .register('operationCenter.exportLogs', exportLogsFile)
      .register('operationCenter.taskStatus', context => {
        state.taskStatus[context.dataset.operationTask] = context.dataset.status;
        saveTaskStatus();
        renderTasks();
      })
      .register('operationCenter.openScreen', context => window.Thon09NavigationController?.navigate(context.dataset.operationScreen || 'dashboard'))
      .register('operationCenter.quickProfile', context => openQuickProfile(context.dataset.profileType, Number(context.dataset.profileId || 0)))
      .register('operationCenter.openDetail', context => {
        closeModal('detailModal');
        if (context.dataset.operationDetail === 'citizen' && typeof window.showPerson === 'function') window.showPerson(Number(context.dataset.id));
        else if (typeof window.showHousehold === 'function') window.showHousehold(Number(context.dataset.id));
      });
  }

  function normalizeHeader(screen) {
    if (screen !== 'operationCenter') return;
    const title = qs('#screenTitle');
    const crumb = qs('#breadcrumbTrail');
    if (title) title.textContent = 'Trung tâm điều hành';
    if (crumb) crumb.textContent = 'Trang chủ / Trung tâm điều hành';
  }

  function isActive() { return !!qs('#operationCenterScreen.screen.active'); }

  function bindStaticEvents() {
    const search = qs('#operationSearchInput');
    if (search && search.dataset.boundOperationSearch !== '1') {
      search.dataset.boundOperationSearch = '1';
      search.addEventListener('input', debounce(() => runSearch(search.value.trim()), 260));
    }
    [['#operationTaskPriority', renderTasks], ['#operationTaskDate', renderTasks], ['#operationTimelineSearch', loadTimeline], ['#operationTimelineModule', loadTimeline], ['#operationAreaSelect', loadAreaDashboard], ['#operationLogSearch', loadLogs], ['#operationLogDateFrom', loadLogs], ['#operationLogDateTo', loadLogs]].forEach(pair => {
      const el = qs(pair[0]);
      if (el && el.dataset.boundOperation !== '1') { el.dataset.boundOperation = '1'; el.addEventListener('input', debounce(pair[1], 250)); el.addEventListener('change', pair[1]); }
    });
  }

  function loadAll() {
    bindStaticEvents();
    loadNotifications(); loadTasks(); loadTimeline(); loadAreaDashboard(); loadProgress(); loadLogs();
  }

  async function loadNotifications() {
    const host = qs('#operationNotifications'); loading(host);
    try { renderNotifications(unwrap(await apiGet(API + '/notifications', null, 10000)).items || []); } catch (error) { widgetError(host, error); }
  }
  function renderNotifications(items) {
    const host = qs('#operationNotifications'); if (!host) return;
    host.innerHTML = items.length ? '<div class="operation-list">' + items.map(item => '<div class="operation-item operation-priority-' + esc(item.priority || 'low') + '"><span><strong>' + esc(item.label) + '</strong><small>' + esc(priorityLabel(item.priority)) + ' · ' + esc(item.status || 'new') + ' · ' + esc(formatTime(item.createdAt)) + '</small></span><div class="operation-actions"><span class="operation-badge">' + num(item.count) + '</span><button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="operationCenter.openScreen" data-operation-screen="' + esc(item.screen || 'dashboard') + '">' + esc(item.action || 'Mở') + '</button></div></div>').join('') + '</div>' : empty('Không có cảnh báo nổi bật');
  }

  let latestTasks = [];
  async function loadTasks() {
    const host = qs('#operationTasks'); loading(host);
    try { latestTasks = unwrap(await apiGet(API + '/tasks', null, 10000)).items || []; renderTasks(); } catch (error) { widgetError(host, error); }
  }
  function renderTasks() {
    const host = qs('#operationTasks'); if (!host) return;
    const priority = (qs('#operationTaskPriority') || {}).value || '';
    const rows = latestTasks.filter(item => !priority || item.priority === priority);
    host.innerHTML = rows.length ? '<div class="operation-list">' + rows.map(item => {
      const status = state.taskStatus[item.key] || item.status || 'open';
      return '<div class="operation-item operation-priority-' + esc(item.priority || 'low') + '"><span><strong>' + esc(item.label) + '</strong><small>' + esc(priorityLabel(item.priority)) + ' · ' + esc(statusLabel(status)) + '</small></span><div class="operation-actions"><span class="operation-badge">' + num(item.count) + '</span><button class="btn btn-sm btn-outline-warning" data-platform-action="operationCenter.taskStatus" data-operation-task="' + esc(item.key) + '" data-status="doing">Đang xử lý</button><button class="btn btn-sm btn-outline-success" data-platform-action="operationCenter.taskStatus" data-operation-task="' + esc(item.key) + '" data-status="done">Hoàn thành</button><button class="btn btn-sm btn-outline-primary" data-platform-action="operationCenter.openScreen" data-operation-screen="' + esc(item.screen || 'dashboard') + '">Mở</button></div></div>';
    }).join('') + '</div>' : empty('Không có công việc phù hợp');
  }

  async function runSearch(query) {
    const host = qs('#operationSearchResults'); if (!host) return;
    if (query.length < 2) { host.classList.add('d-none'); host.innerHTML = ''; return; }
    try {
      const data = unwrap(await apiGet(API + '/search', { q: query, limit: 12 }, 4000));
      const items = data.items || [];
      host.innerHTML = items.length ? items.map(item => '<button type="button" data-platform-action="operationCenter.quickProfile" data-profile-type="' + esc(item.type) + '" data-profile-id="' + Number(item.id || 0) + '"><strong>' + esc(item.title) + '</strong><span>' + esc(item.subtitle || item.meta || '') + '</span></button>').join('') : '<div class="operation-empty">Không tìm thấy kết quả</div>';
      host.classList.remove('d-none');
    } catch (error) { host.innerHTML = '<div class="operation-error">Không tải được kết quả</div>'; host.classList.remove('d-none'); }
  }

  async function openQuickProfile(type, id) {
    const results = qs('#operationSearchResults'); if (results) results.classList.add('d-none');
    try {
      const data = unwrap(await apiGet(API + '/quick-profile', { type, id }, 0));
      renderQuickProfile(data);
    } catch (error) { toast(error.message || 'Không mở được hồ sơ nhanh', 'danger'); }
  }

  function renderQuickProfile(data) {
    const body = qs('#detailBody'); const title = qs('#detailTitle');
    if (!body || !title) return;
    const p = data.profile || {};
    title.textContent = data.type === 'citizen' ? 'Hồ sơ nhanh nhân khẩu' : 'Hồ sơ nhanh hộ gia đình';
    body.innerHTML = '<div class="operation-profile-grid"><section><dl>'
      + detailLine('Mã/Họ tên', p.full_name || p.household_code || p.head_citizen_name)
      + detailLine('CCCD', p.identity_number)
      + detailLine('Điện thoại', p.phone)
      + detailLine('Địa chỉ', p.current_address || p.address || p.household_address)
      + detailLine('GPS', data.gps && data.gps.latitude ? data.gps.latitude + ', ' + data.gps.longitude : '')
      + '</dl></section><section><h6>Thành viên hộ</h6><div class="operation-member-list">' + ((data.members || []).slice(0, 8).map(m => '<span>' + esc(m.full_name || '') + ' <small>' + esc(m.relationship || '') + '</small></span>').join('') || '<span>Chưa có dữ liệu</span>') + '</div></section><section><h6>Hồ sơ số</h6><div class="operation-file-list">' + ((data.files || []).slice(0, 8).map(f => '<span>' + esc(f.original_name || f.file_name || 'Tệp đính kèm') + '</span>').join('') || '<span>Chưa có hồ sơ số</span>') + '</div></section><section><h6>Nhật ký</h6><div class="operation-file-list">' + ((data.timeline || []).slice(0, 6).map(t => '<span>' + esc(t.created_at || '') + ' · ' + esc(t.message || t.action || '') + '</span>').join('') || '<span>Chưa có nhật ký</span>') + '</div></section></div><div class="mt-3"><button class="btn btn-primary" type="button" data-platform-action="operationCenter.openDetail" data-operation-detail="' + esc(data.type) + '" data-id="' + Number(data.id || 0) + '">Mở chi tiết</button></div>';
    openModal('detailModal');
  }

  async function loadTimeline() {
    const host = qs('#operationTimeline'); loading(host);
    try {
      const data = unwrap(await apiGet(API + '/timeline', { search: value('#operationTimelineSearch'), module: value('#operationTimelineModule'), limit: 80 }, 8000));
      const items = data.items || [];
      host.innerHTML = items.length ? '<div class="operation-timeline">' + items.map(item => '<div class="operation-timeline-item"><strong>' + esc(item.title || item.action || '') + '</strong><small>' + esc(formatTime(item.time)) + ' · ' + esc(item.module || '') + (item.actor ? ' · ' + esc(item.actor) : '') + '</small></div>').join('') + '</div>' : empty('Chưa có timeline');
    } catch (error) { widgetError(host, error); }
  }

  async function loadAreaDashboard() {
    const host = qs('#operationAreaDashboard'); loading(host);
    try {
      const data = unwrap(await apiGet(API + '/area-dashboard', { area: value('#operationAreaSelect') }, 10000));
      syncAreaOptions(data.areas || [], data.area || '');
      const m = data.metrics || {};
      host.innerHTML = '<div class="operation-metric-grid">' + metric('Tổng số hộ', m.total_households) + metric('Tổng nhân khẩu', m.total_citizens) + metric('Nam', m.male_count) + metric('Nữ', m.female_count) + metric('Trẻ em', m.children_count) + metric('Người cao tuổi', m.elderly_count) + metric('Đảng viên', m.party_member_count) + metric('Hộ nghèo', m.poor_households) + metric('Hộ cận nghèo', m.near_poor_households) + '</div><div class="operation-progress mt-3">' + progressRow('Tiến độ GPS', data.gpsProgress) + progressRow('Tiến độ hồ sơ số', data.profileProgress) + '</div>';
    } catch (error) { widgetError(host, error); }
  }

  function syncAreaOptions(areas, current) {
    const select = qs('#operationAreaSelect'); if (!select || select.dataset.loadedAreas === '1') return;
    select.dataset.loadedAreas = '1';
    select.innerHTML = '<option value="">Tất cả khu vực</option>' + areas.map(area => '<option value="' + esc(area.area_code || '') + '">' + esc(area.area_code || 'Chưa phân khu') + '</option>').join('');
    select.value = current || '';
  }

  async function loadProgress() {
    const host = qs('#operationProgress'); loading(host);
    try {
      const data = unwrap(await apiGet(API + '/progress', null, 10000));
      host.innerHTML = '<div class="operation-progress">' + (data.items || []).map(item => progressRow(item.label, item.progress)).join('') + '</div>';
    } catch (error) { widgetError(host, error); }
  }

  async function loadLogs() {
    const host = qs('#operationLogs'); loading(host);
    try {
      const data = unwrap(await apiGet(API + '/system-logs', { search: value('#operationLogSearch'), dateFrom: value('#operationLogDateFrom'), dateTo: value('#operationLogDateTo'), pageSize: 20 }, 8000));
      const rows = data.items || [];
      host.innerHTML = rows.length ? '<div class="table-responsive operation-log-table"><table class="table table-sm align-middle"><thead><tr><th>Thời gian</th><th>Người thao tác</th><th>Module</th><th>Nội dung</th><th>IP</th></tr></thead><tbody>' + rows.map(r => '<tr><td>' + esc(formatTime(r.created_at)) + '</td><td>' + esc(r.user_email) + '</td><td>' + esc(r.module) + '</td><td>' + esc(r.message) + '</td><td>' + esc(r.ip_address) + '</td></tr>').join('') + '</tbody></table></div>' : empty('Chưa có nhật ký');
      if (typeof window.refreshUiEnhancements === 'function') window.refreshUiEnhancements(host);
    } catch (error) { widgetError(host, error); }
  }

  function exportParams(format) { return { format, range: value('#operationReportRange') || 'today' }; }
  function exportReport(format) { download(API + '/export-report', exportParams(format), 'bao_cao_dieu_hanh.' + (format === 'excel' ? 'xls' : format === 'word' ? 'doc' : 'pdf')); }
  function exportLogsFile() { download(API + '/export-logs', { search: value('#operationLogSearch'), dateFrom: value('#operationLogDateFrom'), dateTo: value('#operationLogDateTo') }, 'nhat_ky_he_thong.xls'); }
  async function download(path, params, name) {
    const headers = {}; if (window.App && App.token) headers.Authorization = 'Bearer ' + App.token;
    const response = await fetch(path + '?' + new URLSearchParams(params).toString(), { headers, cache: 'no-store' });
    if (!response.ok) { toast('Không xuất được file', 'danger'); return; }
    const blob = await response.blob(); const url = URL.createObjectURL(blob); const link = document.createElement('a');
    link.href = url; link.download = name; document.body.appendChild(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 30000);
  }

  function detailLine(label, value) { return '<dt>' + esc(label) + '</dt><dd>' + esc(value || '---') + '</dd>'; }
  function metric(label, value) { return '<div class="operation-metric"><span>' + esc(label) + '</span><strong>' + num(value) + '</strong></div>'; }
  function progressRow(label, progress) { progress = progress || {}; const value = pct(progress.percent); return '<div class="operation-progress-row"><div><span>' + esc(label) + '</span><strong>' + value.toFixed(value % 1 ? 1 : 0) + '%</strong></div><i style="--value:' + value + '%"><b></b></i><small>' + num(progress.done) + '/' + num(progress.total) + '</small></div>'; }
  function empty(text) { return '<div class="operation-empty">' + esc(text) + '</div>'; }
  function value(selector) { const el = qs(selector); return el ? String(el.value || '').trim() : ''; }
  function priorityLabel(priority) { return ({ high: 'Ưu tiên cao', medium: 'Ưu tiên vừa', low: 'Theo dõi' })[priority] || 'Theo dõi'; }
  function statusLabel(status) { return ({ open: 'Cần xử lý', doing: 'Đang xử lý', done: 'Hoàn thành' })[status] || status; }
  function formatTime(value) { if (!value) return ''; try { return new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value)); } catch (_) { return String(value); } }
  function debounce(fn, wait) { let timer; return function () { clearTimeout(timer); const args = arguments; timer = setTimeout(() => fn.apply(this, args), wait); }; }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
