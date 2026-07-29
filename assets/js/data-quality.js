(function () {
  'use strict';

  const API = '/api/data-quality';
  const state = { summary: null, selectedIssue: '' };

  function qs(selector, root) { return (root || document).querySelector(selector); }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;' }[c])); }
  function num(value) { return new Intl.NumberFormat('vi-VN').format(Number(value || 0)); }
  function pct(value) { return Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + '%'; }
  function toast(message, type) { if (typeof window.showToast === 'function') window.showToast(message, type || 'info'); }
  function unwrap(payload) { return payload && payload.data ? payload.data : (payload || {}); }

  function apiGet(path, params) {
    const query = params ? '?' + new URLSearchParams(params).toString() : '';
    if (typeof window.api === 'function') return window.api(path + query, { cacheTtl: 0 }).then(unwrap);
    const headers = { Accept: 'application/json' };
    if (window.App && window.App.token) headers.Authorization = 'Bearer ' + window.App.token;
    return fetch(path + query, { headers, cache: 'no-store' }).then(response => response.json()).then(payload => {
      if (!payload || payload.ok === false) throw new Error(payload && payload.error && payload.error.message || 'Không tải được dữ liệu');
      return unwrap(payload);
    });
  }

  function isActive() {
    return qs('#dataQualityScreen')?.classList.contains('active');
  }

  function setLoading() {
    const host = qs('#dataQualityKpis');
    if (host) host.innerHTML = '<div class="data-quality-loading">Đang kiểm tra chất lượng dữ liệu...</div>';
  }

  async function loadDataQuality() {
    if (!qs('#dataQualityScreen')) return;
    setLoading();
    try {
      state.summary = await apiGet(API + '/summary', { includeEmpty: 1 });
      renderSummary();
      renderIssues();
      renderEmptyDetail();
      return state.summary;
    } catch (error) {
      renderError(error);
      throw error;
    }
  }

  function renderSummary() {
    const data = state.summary || {};
    const totals = data.totals || {};
    const score = data.score || {};
    const completeness = data.completeness || {};

    const kpis = [
      { label: 'Data Quality Score', value: score.value == null ? '--' : score.value + '/100', hint: score.label || 'Chưa đánh giá', tone: scoreTone(score.value) },
      { label: 'Data Completeness', value: pct(completeness.completePercent), hint: 'Thiếu dữ liệu: ' + pct(completeness.missingPercent), tone: 'success' },
      { label: 'Tổng số lỗi', value: num(totals.issues), hint: 'Cần rà soát thủ công', tone: 'neutral' },
      { label: 'Critical', value: num(totals.critical), hint: 'Ảnh hưởng trực tiếp đến thống kê/chính sách', tone: 'critical' },
      { label: 'High', value: num(totals.high), hint: 'Cần xử lý sớm', tone: 'high' },
      { label: 'Medium', value: num(totals.medium), hint: 'Cần bổ sung khi rà soát', tone: 'medium' },
      { label: 'Low', value: num(totals.low), hint: 'Thông tin bổ sung', tone: 'low' }
    ];
    qs('#dataQualityKpis').innerHTML = kpis.map(kpiCard).join('');

    const generatedAt = qs('#dataQualityGeneratedAt');
    if (generatedAt) generatedAt.textContent = data.engine && data.engine.generatedAt ? 'Cập nhật: ' + formatTime(data.engine.generatedAt) : 'Đã kiểm tra';

    renderGroups(data.groups || []);
    syncGroupFilter(data.groups || []);
  }

  function kpiCard(item) {
    return '<article class="data-quality-kpi data-quality-kpi-' + esc(item.tone) + '">' +
      '<span>' + esc(item.label) + '</span>' +
      '<strong>' + esc(item.value) + '</strong>' +
      '<small>' + esc(item.hint) + '</small>' +
      '</article>';
  }

  function renderGroups(groups) {
    const host = qs('#dataQualityGroups');
    if (!host) return;
    if (!groups.length) {
      host.innerHTML = '<div class="data-quality-empty">Không có nhóm lỗi cần xử lý.</div>';
      return;
    }
    host.innerHTML = groups.map(group => (
      '<button class="data-quality-group" type="button" data-platform-action="dataQuality.filterGroup" data-group="' + esc(group.key) + '">' +
      '<span>' + esc(group.label || group.key) + '</span>' +
      '<strong>' + num(group.count) + '</strong>' +
      '<small>Critical ' + num(group.critical) + ' · High ' + num(group.high) + '</small>' +
      '</button>'
    )).join('');
  }

  function syncGroupFilter(groups) {
    const select = qs('#dataQualityGroupFilter');
    if (!select) return;
    const current = select.value;
    select.innerHTML = '<option value="">Tất cả nhóm</option>' + groups.map(group => '<option value="' + esc(group.key) + '">' + esc(group.label || group.key) + '</option>').join('');
    select.value = groups.some(group => group.key === current) ? current : '';
  }

  function renderIssues() {
    const host = qs('#dataQualityIssues');
    if (!host) return;
    const severity = qs('#dataQualitySeverityFilter')?.value || '';
    const group = qs('#dataQualityGroupFilter')?.value || '';
    const issues = (state.summary && state.summary.issues || []).filter(issue => {
      if (severity && issue.severity !== severity) return false;
      if (group && issue.group !== group) return false;
      return true;
    });

    if (!issues.length) {
      host.innerHTML = '<div class="data-quality-empty">Không có lỗi phù hợp bộ lọc.</div>';
      return;
    }

    host.innerHTML = issues.map(issue => (
      '<article class="data-quality-issue data-quality-issue-' + esc(issue.severity.toLowerCase()) + '">' +
      '<div class="data-quality-issue-main">' +
      '<span class="data-quality-severity">' + esc(issue.severity) + '</span>' +
      '<h4>' + esc(issue.name) + '</h4>' +
      '<p>' + esc(issue.description) + '</p>' +
      '<small><strong>Ảnh hưởng:</strong> ' + esc(issue.impact) + '</small>' +
      '</div>' +
      '<div class="data-quality-issue-side">' +
      '<strong>' + num(issue.count) + '</strong>' +
      '<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="dataQuality.issueDetail" data-issue-code="' + esc(issue.code) + '">Xem hồ sơ</button>' +
      '</div>' +
      '</article>'
    )).join('');
  }

  async function loadIssueDetail(code) {
    if (!code) return;
    state.selectedIssue = code;
    const host = qs('#dataQualityIssueDetail');
    if (host) host.innerHTML = '<div class="data-quality-loading">Đang tải hồ sơ liên quan...</div>';
    try {
      const data = await apiGet(API + '/issue', { code, pageSize: 30 });
      renderIssueDetail(data);
    } catch (error) {
      if (host) host.innerHTML = '<div class="data-quality-error">' + esc(error.message || 'Không tải được chi tiết') + '</div>';
    }
  }

  function renderIssueDetail(data) {
    const host = qs('#dataQualityIssueDetail');
    if (!host) return;
    const issue = data.issue || {};
    const rows = data.items || [];
    if (!rows.length) {
      host.innerHTML = '<div class="data-quality-empty">Không còn hồ sơ liên quan đến lỗi này.</div>';
      return;
    }
    host.innerHTML = '<div class="data-quality-detail-summary">' +
      '<div><span>' + esc(issue.severity || '') + '</span><h4>' + esc(issue.name || issue.code || '') + '</h4><p>' + esc(issue.suggestion || '') + '</p></div>' +
      '<strong>' + num(issue.count) + '</strong>' +
      '</div>' +
      '<div class="table-responsive"><table class="table data-quality-table align-middle mb-0"><thead><tr><th>Hồ sơ</th><th>Mã hộ</th><th>Thông tin</th><th class="text-end">Thao tác</th></tr></thead><tbody>' +
      rows.map(detailRow).join('') +
      '</tbody></table></div>';
  }

  function detailRow(row) {
    const title = row.title || row.full_name || row.head_citizen_name || row.household_code || ('#' + row.entity_id);
    const info = [row.identity_number, row.phone, row.relationship, row.date_of_birth, row.message, row.duplicate_value].filter(Boolean).join(' · ');
    const action = row.entity_type === 'household' ? 'dataQuality.openHousehold' : 'dataQuality.openCitizen';
    return '<tr>' +
      '<td><strong>' + esc(title) + '</strong><small class="d-block text-muted">' + esc(row.entity_type || '') + '</small></td>' +
      '<td>' + esc(row.household_code || '') + '</td>' +
      '<td>' + esc(info || row.address || '') + '</td>' +
      '<td class="text-end"><button class="btn btn-sm btn-light" type="button" data-platform-action="' + action + '" data-id="' + esc(row.entity_id || '') + '" data-search="' + esc(row.citizen_code || row.household_code || title) + '">Mở nhanh</button></td>' +
      '</tr>';
  }

  function renderEmptyDetail() {
    const host = qs('#dataQualityIssueDetail');
    if (host) host.innerHTML = '<div class="data-quality-empty">Chọn một lỗi để xem danh sách hồ sơ cần rà soát.</div>';
  }

  function renderError(error) {
    const message = error && error.message ? error.message : 'Không tải được Data Quality Center';
    const kpis = qs('#dataQualityKpis');
    if (kpis) kpis.innerHTML = '<div class="data-quality-error">' + esc(message) + '</div>';
    const issues = qs('#dataQualityIssues');
    if (issues) issues.innerHTML = '<div class="data-quality-error">' + esc(message) + '</div>';
  }

  function openCitizen(context) {
    const id = Number(context.dataset.id || 0);
    if (id && typeof window.showPerson === 'function') {
      window.showPerson(id);
      return;
    }
    openScreen('persons', context.dataset.search || '');
  }

  function openHousehold(context) {
    const id = Number(context.dataset.id || 0);
    if (id && typeof window.showHousehold === 'function') {
      window.showHousehold(id);
      return;
    }
    openScreen('households', context.dataset.search || '');
  }

  function openScreen(screen, search) {
    if (window.TenantAppNavigationController && typeof window.TenantAppNavigationController.navigate === 'function') {
      window.TenantAppNavigationController.navigate(screen);
    }
    setTimeout(() => {
      const input = screen === 'persons' ? qs('#personSearch') : qs('#householdSearch');
      if (input && search) {
        input.value = search;
        input.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }, 120);
  }

  function registerActions() {
    const actions = window.TenantAppPlatform && window.TenantAppPlatform.actions;
    if (!actions || typeof actions.register !== 'function') return;
    actions
      .register('dataQuality.refresh', () => loadDataQuality().then(() => toast('Đã kiểm tra lại chất lượng dữ liệu', 'success')).catch(() => {}))
      .register('dataQuality.issueDetail', context => loadIssueDetail(context.dataset.issueCode || ''))
      .register('dataQuality.filterGroup', context => {
        const select = qs('#dataQualityGroupFilter');
        if (select) select.value = context.dataset.group || '';
        renderIssues();
      })
      .register('dataQuality.openCitizen', openCitizen)
      .register('dataQuality.openHousehold', openHousehold);
  }

  function bindEvents() {
    qs('#dataQualitySeverityFilter')?.addEventListener('change', renderIssues);
    qs('#dataQualityGroupFilter')?.addEventListener('change', renderIssues);
    document.addEventListener('tenant:screen-change', event => {
      const screen = event.detail && (event.detail.screenId || event.detail.screen || event.detail.moduleKey);
      if ((screen === 'dataQuality' || isActive()) && !state.summary) loadDataQuality();
    });
    document.addEventListener('tenant:auth-state', event => {
      if (event.detail && event.detail.authenticated && isActive()) loadDataQuality();
    });
  }

  function boot() {
    registerActions();
    bindEvents();
    if (isActive()) loadDataQuality();
  }

  function scoreTone(value) {
    value = Number(value || 0);
    if (value >= 90) return 'success';
    if (value >= 75) return 'medium';
    return 'critical';
  }

  function formatTime(value) {
    if (!value) return '';
    try {
      return new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value));
    } catch (_) {
      return String(value);
    }
  }

  window.loadDataQuality = loadDataQuality;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
