(function () {
  'use strict';

  const API = '/api/policy-alerts';
  const state = { type: 'age_70', page: 1, pageSize: 20, search: '', status: 'pending', summary: null };
  const BTXH_TYPE = 'age_75_social_assistance_legacy_missing_record';
  const BTXH_RESULTS = [
    ['RECEIVING_SOCIAL_ASSISTANCE', '\u0110ang h\u01b0\u1edfng BTXH'],
    ['ELIGIBLE_NOT_RECEIVING', 'Thu\u1ed9c di\u1ec7n nh\u01b0ng ch\u01b0a h\u01b0\u1edfng'],
    ['NOT_ELIGIBLE', 'Kh\u00f4ng thu\u1ed9c di\u1ec7n h\u01b0\u1edfng'],
    ['UNVERIFIED', 'Ch\u01b0a x\u00e1c minh \u0111\u01b0\u1ee3c']
  ];
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const safe = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
  const number = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
  let lastToast = { message: '', type: '', at: 0 };
  const markRequests = new Set();
  function toast(message, type = 'info') {
    const text = String(message || '');
    const now = Date.now();
    if (lastToast.message === text && lastToast.type === type && now - lastToast.at < 2500) return;
    lastToast = { message: text, type, at: now };
    if (typeof window.showToast === 'function') window.showToast(text, type);
    else console[type === 'danger' ? 'error' : 'log'](text);
  }
  const policyDefaults = window.AppSettings?.citizenPolicyDefaults || {};
  const SUMMARY_SCREENS = new Set(['dashboard', 'dashboardPopulation', 'persons', 'households']);
  const PERSON_FILTER_SCREENS = new Set(['persons']);

  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
  document.addEventListener('tenant:screen-change', event => {
    const screen = normalizeScreen(event.detail?.screen);
    if (isSummaryScreen(screen)) setTimeout(loadSummary, 80);
    else removeDashboardCard();
    if (isPersonFilterScreen(screen)) setTimeout(installPersonFilters, 80);
  });
  document.addEventListener('tenant:auth-state', () => {
    if (isSummaryScreen(currentScreen())) setTimeout(loadSummary, 120);
  });

  function init() {
    registerActions();
    wrapDashboardLoader();
    if (isSummaryScreen(currentScreen())) loadSummary();
    if (isPersonFilterScreen(currentScreen())) installPersonFilters();
  }

  function registerActions() {
    const actions = window.TenantAppPlatform?.actions;
    if (!actions || window.__TenantPolicyAlertActionsRegistered) return;
    window.__TenantPolicyAlertActionsRegistered = true;
    actions
      .register('policyAlerts.open', ({ dataset }) => openList(dataset.type || 'age_70', statusFromDataset(dataset || {})))
      .register('policyAlerts.page', ({ dataset, target }) => !target.disabled && loadList(Number(dataset.page || 1)))
      .register('policyAlerts.search', () => { state.search = $('#policyAlertSearch')?.value.trim() || ''; loadList(1); })
      .register('policyAlerts.status', ({ target }) => { state.status = target.value || ''; loadList(1); })
      .register('policyAlerts.mark', ({ dataset, target }) => { const row = target?.closest?.('tr'); const result = row?.querySelector?.('[data-policy-alert-result]')?.value || dataset.resultStatus || ''; return mark(Number(dataset.id || 0), dataset.status || 'reviewed', result, target); })
      .register('policyAlerts.openCitizen', ({ dataset }) => openCitizen(dataset.code || '', Number(dataset.id || 0)))
      .register('policyAlerts.export', ({ dataset }) => exportReport(dataset.format || 'excel'))
      .register('policyAlerts.print', () => printReport());
    const previousPersonReset = actions.get?.('personFilters.reset')?.handler;
    if (previousPersonReset && !window.__TenantPolicyAlertPersonResetWrapped) {
      window.__TenantPolicyAlertPersonResetWrapped = true;
      actions.register('personFilters.reset', context => {
        clearPersonPolicyChecks();
        return previousPersonReset(context);
      });
    }
    actions.bind?.(document);
  }

  function wrapDashboardLoader() {
    if (window.__TenantPolicyAlertDashboardWrapped || typeof window.loadDashboard !== 'function') return;
    const previousLoadDashboard = window.loadDashboard;
    window.__TenantPolicyAlertDashboardWrapped = true;
    window.loadDashboard = async function policyAlertDashboardLoader(...args) {
      const result = await previousLoadDashboard.apply(this, args);
      if (isSummaryScreen(currentScreen())) loadSummary();
      return result;
    };
  }

  async function request(url, options = {}) {
    if (typeof window.api === 'function') return window.api(url, options);
    const token = window.App?.token || localStorage.getItem(tenantStorageKey('token')) || '';
    const headers = { Accept: 'application/json' };
    if (token) headers.Authorization = 'Bearer ' + token;
    if (window.App?.csrfToken) headers['X-CSRF-Token'] = window.App.csrfToken;
    const initOptions = { method: options.method || 'GET', headers };
    if (options.body) { headers['Content-Type'] = 'application/json'; initOptions.body = JSON.stringify(options.body); }
    const response = await fetch(url, initOptions);
    const payload = await response.json().catch(() => null);
    if (!response.ok || payload?.ok === false || payload?.success === false) throw new Error(payload?.error?.message || payload?.message || 'Không tải được dữ liệu');
    return payload?.data ?? payload;
  }

  async function loadSummary() {
    if (!isSummaryScreen(currentScreen())) {
      removeDashboardCard();
      return;
    }
    try {
      state.summary = await request(API + '/summary', { cacheTtl: 30000 });
      renderDashboardCard(state.summary);
    } catch (_) {
      renderDashboardCard({ items: [], total: 0 });
    }
  }

  function renderDashboardCard(summary) {
    if (!isSummaryScreen(currentScreen())) {
      removeDashboardCard();
      return;
    }
    const screen = policyScreen();
    if (!screen) return;
    let card = $('#policyAlertDashboardCard');
    if (!card || !screen.contains(card)) {
      removeDashboardCard();
      const anchor = policyAnchor(screen);
      anchor.insertAdjacentHTML(policyInsertPosition(anchor), '<section id="policyAlertDashboardCard" class="policy-alert-card"></section>');
      card = $('#policyAlertDashboardCard');
    }
    const items = summary?.items || [];
    card.innerHTML = '<div class="policy-alert-head"><div><h3><i class="fa-solid fa-triangle-exclamation"></i> Cảnh báo chính sách</h3><span>Tự động tính theo ngày sinh, chỉ hiển thị nhân khẩu đang cư trú và còn sống.</span></div><button class="btn btn-warning btn-sm" type="button" data-platform-action="policyAlerts.open" data-type="age_70">Xem chi tiết</button></div>'
      + '<div class="policy-alert-grid">'
      + items.map(item => '<button type="button" class="policy-alert-tile" data-platform-action="policyAlerts.open" data-type="' + safe(item.key) + '" data-status="pending"><span>' + safe(item.label) + '</span><strong>' + number(item.count) + '</strong><small>' + safe(item.purpose || item.message || '') + '</small></button>').join('')
      + '</div>';
  }

  function removeDashboardCard() {
    $('#policyAlertDashboardCard')?.remove();
  }

  function policyScreen() {
    return $('#' + currentScreen() + 'Screen');
  }

  function policyAnchor(screen) {
    if (!screen) return document.body;
    if (screen.id === 'dashboardScreen') return $('#dashboardKpis') || $('.dashboard-status-row', screen) || screen;
    return $('.content-card', screen) || screen;
  }

  function policyInsertPosition(anchor) {
    return anchor?.classList?.contains('screen') ? 'afterbegin' : 'beforebegin';
  }

  function installModal() {
    if ($('#policyAlertModal')) return;
    document.body.insertAdjacentHTML('beforeend',
      '<div class="modal fade" id="policyAlertModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">'
      + '<div class="modal-header"><div><h5 id="policyAlertTitle" class="modal-title">C\u1ea3nh b\u00e1o ch\u00ednh s\u00e1ch</h5><small id="policyAlertSubtitle" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>'
      + '<div class="modal-body"><div class="policy-alert-toolbar"><div class="module-search-input-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="policyAlertSearch" class="form-control" placeholder="T\u00ecm h\u1ecd t\u00ean, m\u00e3 nh\u00e2n kh\u1ea9u, m\u00e3 h\u1ed9..."></div><select id="policyAlertStatus" class="form-select"><option value="pending">Ch\u01b0a x\u1eed l\u00fd</option><option value="reviewed">\u0110\u00e3 r\u00e0 so\u00e1t</option><option value="processed">\u0110\u00e3 x\u1eed l\u00fd</option><option value="">T\u1ea5t c\u1ea3</option></select><button class="btn btn-outline-success" type="button" data-platform-action="policyAlerts.export" data-format="excel"><i class="fa-solid fa-file-excel"></i> Excel</button><button class="btn btn-outline-danger" type="button" data-platform-action="policyAlerts.export" data-format="pdf"><i class="fa-solid fa-file-pdf"></i> PDF</button><button class="btn btn-outline-secondary" type="button" data-platform-action="policyAlerts.print"><i class="fa-solid fa-print"></i> In</button></div><div class="table-responsive"><table class="table module-table align-middle mb-0"><thead><tr><th>Nh\u00e2n kh\u1ea9u</th><th>Ng\u00e0y sinh / tu\u1ed5i</th><th>H\u1ed9</th><th>Khu v\u1ef1c</th><th>C\u01b0 tr\u00fa</th><th>BHYT</th><th>BTXH</th><th>R\u00e0 so\u00e1t</th><th class="text-end">Thao t\u00e1c</th></tr></thead><tbody id="policyAlertRows"></tbody></table></div><div id="policyAlertPager" class="pager module-pager"></div></div>'
      + '<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">\u0110\u00f3ng</button></div></div></div></div>');
    $('#policyAlertSearch')?.addEventListener('input', debounce(() => window.TenantAppPlatform?.actions?.dispatch('policyAlerts.search'), 300));
    $('#policyAlertStatus')?.addEventListener('change', event => window.TenantAppPlatform?.actions?.dispatch('policyAlerts.status', { target: event.target }));
    window.TenantAppPlatform?.modals?.registerBootstrap?.('policyAlertModal', '#policyAlertModal');
  }

  function statusFromDataset(dataset) {
    return Object.prototype.hasOwnProperty.call(dataset, 'status') ? dataset.status : 'pending';
  }

  async function openList(type, status = 'pending') {
    if (!isSummaryScreen(currentScreen())) return;
    installModal();
    state.type = type;
    state.page = 1;
    state.search = '';
    state.status = status;
    if ($('#policyAlertSearch')) $('#policyAlertSearch').value = '';
    renderStatusOptions(type);
    if ($('#policyAlertStatus')) $('#policyAlertStatus').value = status;
    window.TenantAppPlatform?.modals?.open?.('policyAlertModal') || window.bootstrap?.Modal?.getOrCreateInstance?.($('#policyAlertModal'))?.show();
    await loadList(1);
  }

  async function loadList(page = state.page) {
    state.page = page;
    const params = new URLSearchParams({ type: state.type, page: state.page, pageSize: state.pageSize, status: state.status, search: state.search });
    const data = await request(API + '?' + params.toString(), { cacheTtl: 5000 });
    const current = (data.summary?.items || state.summary?.items || []).find(item => item.key === state.type) || {};
    $('#policyAlertTitle').textContent = current.label || 'C\u1ea3nh b\u00e1o ch\u00ednh s\u00e1ch';
    $('#policyAlertSubtitle').textContent = current.message || '';
    renderRows(data.items || []);
    renderPager(data);
  }

  function renderRows(rows) {
    const tbody = $('#policyAlertRows');
    if (!tbody) return;
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Kh\u00f4ng c\u00f3 d\u1eef li\u1ec7u ph\u00f9 h\u1ee3p.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(row => '<tr><td data-label="Nh\u00e2n kh\u1ea9u"><strong>' + safe(row.full_name) + '</strong><br><small>' + safe(row.citizen_code) + '</small><br><small>' + safe(genderLabel(row.gender)) + '</small></td><td data-label="Ng\u00e0y sinh / tu\u1ed5i">' + safe(row.date_of_birth) + '<br><strong>' + number(row.age) + ' tu\u1ed5i</strong></td><td data-label="H\u1ed9">' + safe(row.head_citizen_name) + '<br><small>' + safe(row.household_code) + '</small></td><td data-label="Khu v\u1ef1c">' + safe(row.area_code || '') + '<br><small>' + safe(row.address) + '</small></td><td data-label="C\u01b0 tr\u00fa">' + residencyBadge(row) + '<br>' + presenceBadge(row) + '</td><td data-label="BHYT">' + (row.has_health_insurance ? '<span class="badge bg-success">C\u00f3 BHYT</span>' : '<span class="badge bg-warning text-dark">Ch\u01b0a ghi nh\u1eadn</span>') + '</td><td data-label="BTXH">' + socialAssistanceBadges(row) + '</td><td data-label="R\u00e0 so\u00e1t">' + statusBadge(row) + (row.review_note ? '<br><small>' + safe(row.review_note) + '</small>' : '') + '</td><td data-label="Thao t\u00e1c" class="text-end"><div class="d-flex flex-wrap gap-1 justify-content-end">' + actionButtons(row) + '</div></td></tr>').join('');
  }

  function genderLabel(value) {
    const gender = String(value || '').toUpperCase();
    if (gender === 'MALE' || value === 'Nam') return 'Nam';
    if (gender === 'FEMALE' || value === 'N\u1eef') return 'N\u1eef';
    return value ? String(value) : 'Kh\u00e1c/Ch\u01b0a x\u00e1c \u0111\u1ecbnh';
  }

  function residencyBadge(row) {
    const value = String(row.residency_status || '').toUpperCase();
    const label = value === 'TEMPORARY' ? 'T\u1ea1m tr\u00fa' : (value === 'TRANSFERRED_OUT' ? '\u0110\u00e3 chuy\u1ec3n \u0111i' : 'Th\u01b0\u1eddng tr\u00fa');
    return '<span class="badge bg-light text-dark border">' + safe(label) + '</span>';
  }

  function presenceBadge(row) {
    const value = String(row.presence_status || '').toUpperCase();
    if (value === 'AWAY') return '<span class="badge bg-warning text-dark">T\u1ea1m v\u1eafng</span>';
    return '<span class="badge bg-success">\u0110ang c\u01b0 tr\u00fa</span>';
  }

  function socialAssistanceBadges(row) {
    const legacy = row.legacy_social_assistance || row.social_assistance;
    const parts = [legacy ? '<span class="badge bg-info text-dark">Th\u00f4ng tin hi\u1ec7n c\u00f3: C\u00f3 d\u1eef li\u1ec7u BTXH c\u0169</span>' : '<span class="badge bg-light text-dark border">Th\u00f4ng tin hi\u1ec7n c\u00f3: Ch\u01b0a ghi nh\u1eadn d\u1eef li\u1ec7u BTXH c\u0169</span>'];
    if (row.has_social_assistance_record) parts.push('<span class="badge bg-success">Th\u00f4ng tin hi\u1ec7n c\u00f3: B\u1ea3n ghi BTXH hi\u1ec7n h\u00e0nh</span>');
    else if (row.has_any_social_assistance_record) parts.push('<span class="badge bg-warning text-dark">Th\u00f4ng tin hi\u1ec7n c\u00f3: B\u1ea3n ghi BTXH kh\u00f4ng hi\u1ec7n h\u00e0nh: ' + safe(row.social_assistance_record_status || '') + '</span>');
    return '<div class="d-flex flex-column gap-1">' + parts.join('') + '</div>';
  }

  function statusBadge(row) {
    const result = row.review_result_status || '';
    if (result) {
      const done = row.review_completed ? '\u0110\u00e3 r\u00e0 so\u00e1t' : 'Ch\u01b0a x\u00e1c minh xong';
      return '<span class="badge ' + (row.review_completed ? 'bg-success' : 'bg-warning text-dark') + '">' + done + '</span><br><small>' + safe(btxhResultLabel(result)) + '</small>';
    }
    if (row.processed_at && !isBtxhType()) return '<span class="badge bg-success">\u0110\u00e3 x\u1eed l\u00fd</span>';
    if (row.reviewed_at && !isBtxhType()) return '<span class="badge bg-info text-dark">\u0110\u00e3 r\u00e0 so\u00e1t</span>';
    return '<span class="badge bg-warning text-dark">Ch\u01b0a r\u00e0 so\u00e1t</span>';
  }

  function isBtxhType(type = state.type) {
    return type === BTXH_TYPE;
  }

  function renderStatusOptions(type = state.type) {
    const select = $('#policyAlertStatus');
    if (!select) return;
    if (isBtxhType(type)) {
      select.innerHTML = '<option value="pending">C\u1ea7n r\u00e0 so\u00e1t</option><option value="unreviewed">Ch\u01b0a r\u00e0 so\u00e1t</option><option value="reviewed">\u0110\u00e3 r\u00e0 so\u00e1t</option><option value="receiving">\u0110ang h\u01b0\u1edfng</option><option value="not_receiving">Kh\u00f4ng h\u01b0\u1edfng</option><option value="unverified">Ch\u01b0a x\u00e1c minh</option><option value="all">T\u1ea5t c\u1ea3</option>';
    } else {
      select.innerHTML = '<option value="pending">Ch\u01b0a x\u1eed l\u00fd</option><option value="reviewed">\u0110\u00e3 r\u00e0 so\u00e1t</option><option value="processed">\u0110\u00e3 x\u1eed l\u00fd</option><option value="">T\u1ea5t c\u1ea3</option>';
    }
  }

  function btxhResultLabel(value) {
    const found = BTXH_RESULTS.find(item => item[0] === value);
    return found ? found[1] : '';
  }

  function actionButtons(row) {
    const open = '<button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="policyAlerts.openCitizen" data-id="' + row.id + '" data-code="' + safe(row.citizen_code) + '">M\u1edf h\u1ed3 s\u01a1</button>';
    if (!isBtxhType()) {
      return open + '<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="policyAlerts.mark" data-id="' + row.id + '" data-status="reviewed">\u0110\u00e3 r\u00e0 so\u00e1t</button><button class="btn btn-sm btn-success" type="button" data-platform-action="policyAlerts.mark" data-id="' + row.id + '" data-status="processed">\u0110\u00e3 x\u1eed l\u00fd</button>';
    }
    const current = row.review_result_status || '';
    const options = '<option value="">Ch\u1ecdn k\u1ebft qu\u1ea3</option>' + BTXH_RESULTS.map(item => '<option value="' + safe(item[0]) + '" ' + (current === item[0] ? 'selected' : '') + '>' + safe(item[1]) + '</option>').join('');
    return open + '<select class="form-select form-select-sm w-auto" data-policy-alert-result>' + options + '</select><button class="btn btn-sm btn-success" type="button" data-platform-action="policyAlerts.mark" data-id="' + row.id + '" data-status="processed">L\u01b0u r\u00e0 so\u00e1t</button>';
  }

  function renderPager(data) {
    const host = $('#policyAlertPager');
    if (!host) return;
    const totalPages = Number(data.totalPages || 1);
    const page = Number(data.page || 1);
    if (totalPages <= 1) { host.innerHTML = ''; return; }
    host.innerHTML = '<div class="d-flex gap-2 justify-content-end flex-wrap"><button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="policyAlerts.page" data-page="' + Math.max(1, page - 1) + '" ' + (page <= 1 ? 'disabled' : '') + '>Tr\u01b0\u1edbc</button><span class="px-2">' + page + ' / ' + totalPages + '</span><button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="policyAlerts.page" data-page="' + Math.min(totalPages, page + 1) + '" ' + (page >= totalPages ? 'disabled' : '') + '>Sau</button></div>';
  }

  async function mark(id, status, resultStatus = '', trigger = null) {
    if (!id) return;
    const key = state.type + ':' + id;
    if (markRequests.has(key)) return;
    if (isBtxhType() && !resultStatus) {
      toast('Vui lòng chọn kết quả rà soát BTXH', 'warning');
      return;
    }
    markRequests.add(key);
    const previousHtml = trigger?.innerHTML;
    if (trigger) {
      trigger.disabled = true;
      trigger.setAttribute('aria-busy', 'true');
    }
    try {
      await request(API + '/' + id + '/mark', { method: 'POST', body: { alert_key: state.type, status, result_status: resultStatus } });
      toast(status === 'processed' ? '\u0110\u00e3 l\u01b0u k\u1ebft qu\u1ea3 r\u00e0 so\u00e1t' : '\u0110\u00e3 l\u01b0u tr\u1ea1ng th\u00e1i r\u00e0 so\u00e1t', 'success');
      await loadList(state.page);
      await loadSummary();
    } catch (error) {
      toast(error.message || 'Kh\u00f4ng c\u1eadp nh\u1eadt \u0111\u01b0\u1ee3c tr\u1ea1ng th\u00e1i r\u00e0 so\u00e1t', 'danger');
    } finally {
      markRequests.delete(key);
      if (trigger) {
        trigger.disabled = false;
        trigger.removeAttribute('aria-busy');
        if (previousHtml !== undefined) trigger.innerHTML = previousHtml;
      }
    }
  }

  function openCitizen(code, id) {
    const query = String(code || id || '').trim();
    if (!query) return;
    if (window.App?.persons) {
      window.App.persons.search = query;
      window.App.persons.page = 1;
    }
    const nav = window.TenantAppNavigationController?.navigate || window.TenantAppPlatform?.navigation?.navigate;
    if (typeof nav === 'function') nav.call(window.TenantAppNavigationController || window.TenantAppPlatform?.navigation, 'persons');
    setTimeout(() => {
      const search = document.querySelector('#personSearch, [data-person-filter="search"], input[name="person_search"]');
      if (search) search.value = query;
      window.loadPersons?.();
    }, 160);
  }
  function exportReport(format) {
    const params = new URLSearchParams({ type: state.type, status: state.status, search: state.search });
    const token = window.App?.token || localStorage.getItem(tenantStorageKey('token')) || '';
    fetch(API + (format === 'pdf' ? '/export-pdf?' : '/export-excel?') + params.toString(), { headers: { Authorization: 'Bearer ' + token } })
      .then(response => { if (!response.ok) throw new Error('Kh\u00f4ng xu\u1ea5t \u0111\u01b0\u1ee3c b\u00e1o c\u00e1o'); return response.blob(); })
      .then(blob => {
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'canh-bao-chinh-sach.' + (format === 'pdf' ? 'pdf' : 'xls');
        document.body.appendChild(link);
        link.click();
        URL.revokeObjectURL(link.href);
        link.remove();
      })
      .catch(error => toast(error.message, 'danger'));
  }

  async function printReport() {
    const params = new URLSearchParams({ type: state.type, status: state.status, search: state.search });
    const report = await request(API + '/print?' + params.toString());
    if (!window.TenantAppPrint?.render) return toast('Print Framework ch\u01b0a s\u1eb5n s\u00e0ng', 'warning');
    window.TenantAppPrint.render({ title: report.title, type: 'policy-alerts', paperSize: 'A4', headers: report.headers, rows: report.rows, totalRows: report.totalRows, filters: report.filters, summary: report.summary, repeatHeader: true, showFooter: true, showSignature: true });
  }

  function installPersonFilters() {
    if (!isPersonFilterScreen(currentScreen())) return;
    const grid = $('.person-quick-filter-grid');
    if (!grid || $('#policyAlertPersonFilters')) return;
    const bhytAge = Number(policyDefaults.bhytDefaultAge || '');
    const allowanceAge = Number(policyDefaults.socialAllowanceDefaultAge || '');
    const bhytLabel = safe(Number.isFinite(bhytAge) ? bhytAge : '');
    const allowanceLabel = safe(Number.isFinite(allowanceAge) ? allowanceAge : '');
    grid.insertAdjacentHTML('beforeend', '<div id="policyAlertPersonFilters" class="person-field policy-person-filter"><label>C\u1ea3nh b\u00e1o ch\u00ednh s\u00e1ch</label><div class="policy-person-checks"><label><input type="checkbox" value="upcoming_70"> S\u1eafp \u0111\u1ee7 ' + bhytLabel + '</label><label><input type="checkbox" value="age_70"> \u0110\u1ee7 ' + bhytLabel + '</label><label><input type="checkbox" value="upcoming_75"> S\u1eafp \u0111\u1ee7 ' + allowanceLabel + '</label><label><input type="checkbox" value="age_75"> \u0110\u1ee7 ' + allowanceLabel + '</label></div><input type="hidden" data-person-filter="policyAlert" name="policyAlert"></div>');
    $$('#policyAlertPersonFilters input[type="checkbox"]').forEach(input => input.addEventListener('change', event => {
      $$('#policyAlertPersonFilters input[type="checkbox"]').forEach(other => { if (other !== event.target) other.checked = false; });
      const hidden = $('#policyAlertPersonFilters input[type="hidden"]');
      hidden.value = event.target.checked ? event.target.value : '';
      window.App.persons.page = 1;
      window.loadPersons?.();
    }));
  }
  function clearPersonPolicyChecks() {
    $$('#policyAlertPersonFilters input[type="checkbox"]').forEach(input => { input.checked = false; });
  }

  function currentScreen() {
    const appScreen = normalizeScreen(window.App?.screen);
    if (appScreen) return appScreen;
    const active = $('.screen.active, [data-screen-id].active');
    if (active?.dataset?.screenId) return normalizeScreen(active.dataset.screenId);
    if (active?.id) return normalizeScreen(active.id.replace(/Screen$/, ''));
    return '';
  }

  function normalizeScreen(screen) {
    return String(screen || '').replace(/Screen$/, '');
  }

  function isSummaryScreen(screen) {
    return SUMMARY_SCREENS.has(normalizeScreen(screen));
  }

  function isPersonFilterScreen(screen) {
    return PERSON_FILTER_SCREENS.has(normalizeScreen(screen));
  }

  function debounce(fn, delay) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); };
  }
})();


