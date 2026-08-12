(function () {
  'use strict';

  const API = '/api/policy-alerts';
  const state = { type: 'age_70', page: 1, pageSize: 20, search: '', status: 'pending', summary: null };
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const safe = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
  const number = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
  const toast = (message, type = 'info') => typeof window.showToast === 'function' ? window.showToast(message, type) : console[type === 'danger' ? 'error' : 'log'](message);
  const policyDefaults = window.AppSettings?.citizenPolicyDefaults || {};
  const SUMMARY_SCREENS = new Set(['dashboard', 'persons', 'households']);
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
      .register('policyAlerts.open', ({ dataset }) => openList(dataset.type || 'age_70'))
      .register('policyAlerts.page', ({ dataset, target }) => !target.disabled && loadList(Number(dataset.page || 1)))
      .register('policyAlerts.search', () => { state.search = $('#policyAlertSearch')?.value.trim() || ''; loadList(1); })
      .register('policyAlerts.status', ({ target }) => { state.status = target.value || ''; loadList(1); })
      .register('policyAlerts.mark', ({ dataset }) => mark(Number(dataset.id || 0), dataset.status || 'reviewed'))
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
    if (!response.ok || payload?.ok === false || payload?.success === false) throw new Error(payload?.error?.message || payload?.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c dá»¯ liá»‡u');
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
    card.innerHTML = '<div class="policy-alert-head"><div><h3><i class="fa-solid fa-triangle-exclamation"></i> Cáº£nh bÃ¡o chÃ­nh sÃ¡ch</h3><span>Tá»± Ä‘á»™ng tÃ­nh theo ngÃ y sinh, chá»‰ hiá»ƒn thá»‹ nhÃ¢n kháº©u Ä‘ang cÆ° trÃº vÃ  cÃ²n sá»‘ng.</span></div><button class="btn btn-warning btn-sm" type="button" data-platform-action="policyAlerts.open" data-type="age_70">Xem chi tiáº¿t</button></div>'
      + '<div class="policy-alert-grid">'
      + items.map(item => '<button type="button" class="policy-alert-tile" data-platform-action="policyAlerts.open" data-type="' + safe(item.key) + '"><span>' + safe(item.label) + '</span><strong>' + number(item.count) + '</strong><small>' + safe(item.purpose || item.message || '') + '</small></button>').join('')
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
      + '<div class="modal-header"><div><h5 id="policyAlertTitle" class="modal-title">Cáº£nh bÃ¡o chÃ­nh sÃ¡ch</h5><small id="policyAlertSubtitle" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>'
      + '<div class="modal-body"><div class="policy-alert-toolbar"><div class="module-search-input-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="policyAlertSearch" class="form-control" placeholder="TÃ¬m há» tÃªn, mÃ£ nhÃ¢n kháº©u, mÃ£ há»™..."></div><select id="policyAlertStatus" class="form-select"><option value="pending">ChÆ°a xá»­ lÃ½</option><option value="reviewed">ÄÃ£ rÃ  soÃ¡t</option><option value="processed">ÄÃ£ xá»­ lÃ½</option><option value="">Táº¥t cáº£</option></select><button class="btn btn-outline-success" type="button" data-platform-action="policyAlerts.export" data-format="excel"><i class="fa-solid fa-file-excel"></i> Excel</button><button class="btn btn-outline-danger" type="button" data-platform-action="policyAlerts.export" data-format="pdf"><i class="fa-solid fa-file-pdf"></i> PDF</button><button class="btn btn-outline-secondary" type="button" data-platform-action="policyAlerts.print"><i class="fa-solid fa-print"></i> In</button></div><div class="table-responsive"><table class="table module-table align-middle mb-0"><thead><tr><th>Há» tÃªn</th><th>NgÃ y sinh</th><th>Tuá»•i</th><th>Chá»§ há»™</th><th>Äá»‹a chá»‰</th><th>BHYT h\u1ed9/n\u0103m</th><th>BH ng\u01b0\u1eddi cao tu\u1ed5i</th><th>Tr\u1ee3 c\u1ea5p</th><th>Tr\u1ea1ng th\u00e1i</th><th class="text-end">Thao tÃ¡c</th></tr></thead><tbody id="policyAlertRows"></tbody></table></div><div id="policyAlertPager" class="pager module-pager"></div></div>'
      + '<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ÄÃ³ng</button></div></div></div></div>');
    $('#policyAlertSearch')?.addEventListener('input', debounce(() => window.TenantAppPlatform?.actions?.dispatch('policyAlerts.search'), 300));
    $('#policyAlertStatus')?.addEventListener('change', event => window.TenantAppPlatform?.actions?.dispatch('policyAlerts.status', { target: event.target }));
    window.TenantAppPlatform?.modals?.registerBootstrap?.('policyAlertModal', '#policyAlertModal');
  }

  async function openList(type) {
    if (!isSummaryScreen(currentScreen())) return;
    installModal();
    state.type = type;
    state.page = 1;
    state.search = '';
    state.status = 'pending';
    if ($('#policyAlertSearch')) $('#policyAlertSearch').value = '';
    if ($('#policyAlertStatus')) $('#policyAlertStatus').value = 'pending';
    window.TenantAppPlatform?.modals?.open?.('policyAlertModal') || window.bootstrap?.Modal?.getOrCreateInstance?.($('#policyAlertModal'))?.show();
    await loadList(1);
  }

  async function loadList(page = state.page) {
    state.page = page;
    const params = new URLSearchParams({ type: state.type, page: state.page, pageSize: state.pageSize, status: state.status, search: state.search });
    const data = await request(API + '?' + params.toString(), { cacheTtl: 5000 });
    const current = (data.summary?.items || state.summary?.items || []).find(item => item.key === state.type) || {};
    $('#policyAlertTitle').textContent = current.label || 'Cáº£nh bÃ¡o chÃ­nh sÃ¡ch';
    $('#policyAlertSubtitle').textContent = current.message || '';
    renderRows(data.items || []);
    renderPager(data);
  }

  function renderRows(rows) {
    const tbody = $('#policyAlertRows');
    if (!tbody) return;
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">KhÃ´ng cÃ³ dá»¯ liá»‡u phÃ¹ há»£p.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(row => '<tr><td><strong>' + safe(row.full_name) + '</strong><br><small>' + safe(row.citizen_code) + '</small></td><td>' + safe(row.date_of_birth) + '</td><td>' + number(row.age) + '</td><td>' + safe(row.head_citizen_name) + '<br><small>' + safe(row.household_code) + '</small></td><td>' + safe(row.address) + '</td><td>' + (row.has_health_insurance ? '<span class="badge bg-success">C\u00f3</span>' : '<span class="badge bg-warning text-dark">Ch\u01b0a c\u00f3</span>') + '</td><td>' + (row.elderly_health_insurance ? '<span class="badge bg-success">\u0110\u00e3 chuy\u1ec3n</span>' : '<span class="badge bg-warning text-dark">Ch\u01b0a chuy\u1ec3n</span>') + '</td><td>' + (row.social_assistance ? '<span class="badge bg-success">Äang hÆ°á»Ÿng</span>' : '<span class="badge bg-light text-dark border">ChÆ°a hÆ°á»Ÿng</span>') + '</td><td>' + statusBadge(row) + '</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="policyAlerts.mark" data-id="' + row.id + '" data-status="reviewed">ÄÃ£ rÃ  soÃ¡t</button> <button class="btn btn-sm btn-success" type="button" data-platform-action="policyAlerts.mark" data-id="' + row.id + '" data-status="processed">' + processedActionLabel() + '</button></td></tr>').join('');
  }


  function isElderlyHealthInsuranceAlert() {
    return state.type === 'age_70' || state.type === 'upcoming_70';
  }

  function processedActionLabel() {
    return isElderlyHealthInsuranceAlert() ? '\u0110\u00e3 chuy\u1ec3n BH NCT' : '\u0110\u00e3 x\u1eed l\u00fd';
  }

  function statusBadge(row) {
    if (row.processed_at) return '<span class="badge bg-success">ÄÃ£ xá»­ lÃ½</span>';
    if (row.reviewed_at) return '<span class="badge bg-info text-dark">ÄÃ£ rÃ  soÃ¡t</span>';
    return '<span class="badge bg-warning text-dark">ChÆ°a xá»­ lÃ½</span>';
  }

  function renderPager(data) {
    const host = $('#policyAlertPager');
    if (!host) return;
    const totalPages = Number(data.totalPages || 1);
    const page = Number(data.page || 1);
    if (totalPages <= 1) { host.innerHTML = ''; return; }
    host.innerHTML = '<div class="d-flex gap-2 justify-content-end flex-wrap"><button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="policyAlerts.page" data-page="' + Math.max(1, page - 1) + '" ' + (page <= 1 ? 'disabled' : '') + '>TrÆ°á»›c</button><span class="px-2">' + page + ' / ' + totalPages + '</span><button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="policyAlerts.page" data-page="' + Math.min(totalPages, page + 1) + '" ' + (page >= totalPages ? 'disabled' : '') + '>Sau</button></div>';
  }

  async function mark(id, status) {
    if (!id) return;
    try {
      await request(API + '/' + id + '/mark', { method: 'POST', body: { alert_key: state.type, status } });
      toast(status === 'processed' ? (isElderlyHealthInsuranceAlert() ? '\u0110\u00e3 chuy\u1ec3n b\u1ea3o hi\u1ec3m ng\u01b0\u1eddi cao tu\u1ed5i' : '\u0110\u00e3 \u0111\u00e1nh d\u1ea5u x\u1eed l\u00fd') : '\u0110\u00e3 \u0111\u00e1nh d\u1ea5u r\u00e0 so\u00e1t', 'success');
      await loadList(state.page);
      await loadSummary();
    } catch (error) {
      toast(error.message || 'KhÃ´ng cáº­p nháº­t Ä‘Æ°á»£c tráº¡ng thÃ¡i cáº£nh bÃ¡o', 'danger');
    }
  }

  function exportReport(format) {
    const params = new URLSearchParams({ type: state.type, status: state.status, search: state.search });
    const token = window.App?.token || localStorage.getItem(tenantStorageKey('token')) || '';
    fetch(API + (format === 'pdf' ? '/export-pdf?' : '/export-excel?') + params.toString(), { headers: { Authorization: 'Bearer ' + token } })
      .then(response => { if (!response.ok) throw new Error('KhÃ´ng xuáº¥t Ä‘Æ°á»£c bÃ¡o cÃ¡o'); return response.blob(); })
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
    if (!window.TenantAppPrint?.render) return toast('Print Framework chÆ°a sáºµn sÃ ng', 'warning');
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


