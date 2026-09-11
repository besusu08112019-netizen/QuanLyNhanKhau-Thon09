(function () {
  'use strict';

  const API = '/api/data-quality';
  const MOBILE_DETAIL_QUERY = '(max-width: 1024px)';
  const state = { summary: null, selectedIssue: '', sourceTasks: {}, relationshipReviewFilter: 'ALL_UNRESOLVED' };

  function qs(selector, root) { return (root || document).querySelector(selector); }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;' }[c])); }
  function num(value) { return new Intl.NumberFormat('vi-VN').format(Number(value || 0)); }
  function pct(value) { return Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + '%'; }
  function toast(message, type) { if (typeof window.showToast === 'function') window.showToast(message, type || 'info'); }
  function unwrap(payload) { return payload && payload.data ? payload.data : (payload || {}); }

  function normalizeRelationshipReviewResponse(payload) {
    if (!payload) return {};
    if (Array.isArray(payload.groups) || Array.isArray(payload.items)) return payload;
    const first = unwrap(payload);
    if (first && (Array.isArray(first.groups) || Array.isArray(first.items))) return first;
    if (first && first.data && !Array.isArray(first.groups) && !Array.isArray(first.items)) return unwrap(first);
    return first || {};
  }

  function apiGet(path, params, options) {
    const query = params ? '?' + new URLSearchParams(params).toString() : '';
    const preservePayload = !!(options && options.preservePayload);
    if (typeof window.api === 'function') return window.api(path + query, { cacheTtl: 0 }).then(payload => preservePayload ? (payload || {}) : unwrap(payload));
    const headers = { Accept: 'application/json' };
    if (window.App && window.App.token) headers.Authorization = 'Bearer ' + window.App.token;
    return fetch(path + query, { headers, cache: 'no-store' }).then(response => response.json()).then(payload => {
      if (!payload || payload.ok === false) throw new Error(payload && payload.error && payload.error.message || 'Không tải được dữ liệu');
      return preservePayload ? normalizeRelationshipReviewResponse(payload) : unwrap(payload);
    });
  }

  function apiPost(path, body) {
    if (typeof window.api === 'function') return window.api(path, { method: 'POST', body }).then(unwrap);
    const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
    if (window.App && window.App.token) headers.Authorization = 'Bearer ' + window.App.token;
    return fetch(path, { method: 'POST', headers, body: JSON.stringify(body || {}), cache: 'no-store' })
      .then(response => response.json())
      .then(payload => {
        if (!payload || payload.ok === false) throw new Error(payload && payload.error && payload.error.message || 'Không lưu được dữ liệu');
        return unwrap(payload);
      });
  }

  function isActive() {
    return qs('#dataQualityScreen')?.classList.contains('active');
  }

  function isDataQualityMobileDetailMode() {
    return !!(window.matchMedia && window.matchMedia(MOBILE_DETAIL_QUERY).matches);
  }

  function dataQualityDetailPanel() {
    return qs('#dataQualityScreen .data-quality-detail-panel');
  }

  function ensureMobileDetailBackControl() {
    const host = qs('#dataQualityIssueDetail');
    if (!host || qs('[data-data-quality-detail-back]', host)) return;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-sm btn-outline-secondary mb-3';
    button.setAttribute('data-data-quality-detail-back', 'true');
    button.setAttribute('data-platform-action', 'dataQuality.backToIssues');
    button.textContent = 'Quay lại danh sách vấn đề';
    host.insertBefore(button, host.firstChild);
  }

  function showDataQualityDetailPanel() {
    const screen = qs('#dataQualityScreen');
    const panel = dataQualityDetailPanel();
    if (!screen || !panel || !isDataQualityMobileDetailMode()) return;
    screen.setAttribute('data-data-quality-detail-state', 'open');
    panel.style.display = 'block';
    ensureMobileDetailBackControl();
    window.requestAnimationFrame(() => {
      if (typeof panel.scrollIntoView === 'function') panel.scrollIntoView({ block: 'start', behavior: 'smooth' });
      const focusTarget = qs('#relationshipReviewFilter', panel) || qs('button, select, input, a[href]', panel);
      if (focusTarget && typeof focusTarget.focus === 'function') focusTarget.focus({ preventScroll: true });
    });
  }

  function hideDataQualityDetailPanel() {
    const screen = qs('#dataQualityScreen');
    const panel = dataQualityDetailPanel();
    if (screen) screen.removeAttribute('data-data-quality-detail-state');
    if (panel) panel.style.display = '';
    const list = qs('#dataQualityIssues');
    if (list && typeof list.scrollIntoView === 'function') list.scrollIntoView({ block: 'start', behavior: 'smooth' });
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
      hydrateIssueTasks();
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
      issueWorkTaskActions(issue) +
      '<button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="dataQuality.issueDetail" data-issue-code="' + esc(issue.code) + '">Xem hồ sơ</button>' +
      '</div>' +
      '</article>'
    )).join('');
  }

  function issueWorkTaskActions(issue) {
    const ref = dataQualitySourceRef(issue);
    const task = state.sourceTasks[ref];
    if (task && task.id) {
      return '<span class="badge bg-success">Đã tạo công việc</span>' +
        '<button class="btn btn-sm btn-success" type="button" data-platform-action="dataQuality.openWorkTask" data-source-ref="' + esc(ref) + '">Mở công việc</button>';
    }
    return '<button class="btn btn-sm btn-outline-success" type="button" data-platform-action="dataQuality.createWorkTask" data-issue-code="' + esc(issue.code) + '"><i class="fa-solid fa-list-check"></i> Tạo công việc</button>';
  }

  function dataQualitySourceRef(issue) {
    return 'data_quality:' + String(issue?.code || '').trim();
  }

  async function hydrateIssueTasks() {
    const issues = state.summary?.issues || [];
    const refs = issues.map(dataQualitySourceRef).filter(Boolean);
    if (!refs.length || !window.WorkTaskQuickCreate?.findBySource) return;
    await Promise.all(refs.map(async ref => {
      try { state.sourceTasks[ref] = await window.WorkTaskQuickCreate.findBySource(ref); }
      catch (_) { state.sourceTasks[ref] = null; }
    }));
    renderIssues();
  }

  function createWorkTaskFromIssue(code) {
    const issue = (state.summary?.issues || []).find(item => item.code === code);
    if (!issue) return toast('Không tìm thấy lỗi cần xử lý', 'warning');
    const ref = dataQualitySourceRef(issue);
    window.WorkTaskQuickCreate?.open({
      sourceType: 'Data Quality',
      sourceId: issue.code,
      sourceModule: 'data_quality',
      sourceTitle: issue.name || issue.code,
      sourceRef: ref,
      sourceUrl: '/data-quality?issue=' + encodeURIComponent(issue.code),
      relatedModule: 'data_quality',
      title: 'Rà soát dữ liệu: ' + (issue.name || issue.code),
      description: [
        issue.description || '',
        issue.impact ? 'Ảnh hưởng: ' + issue.impact : '',
        issue.suggestion ? 'Gợi ý xử lý: ' + issue.suggestion : '',
        'Số hồ sơ liên quan: ' + num(issue.count),
      ].filter(Boolean).join('\n'),
      priority: issue.severity,
      action: issue.suggestion || 'Mở danh sách hồ sơ liên quan và rà soát.',
      citizens: ['citizen', 'identity', 'policy', 'labor', 'student'].includes(issue.group) ? num(issue.count) + ' hồ sơ' : '',
      households: ['household', 'relation', 'gis'].includes(issue.group) ? num(issue.count) + ' hồ sơ' : '',
      categoryCode: 'other',
    });
  }

  async function loadIssueDetail(code) {
    if (!code) return;
    state.selectedIssue = code;
    const host = qs('#dataQualityIssueDetail');
    if (host) host.innerHTML = '<div class="data-quality-loading">Đang tải hồ sơ liên quan...</div>';
    try {
      if (code === 'citizen.relationship_unresolved') {
        const payload = await apiGet('/api/citizens/relationship-review', { filter: state.relationshipReviewFilter }, { preservePayload: true });
        renderRelationshipReview(normalizeRelationshipReviewResponse(payload));
        showDataQualityDetailPanel();
        return;
      }
      const data = await apiGet(API + '/issue', { code, pageSize: 30 });
      renderIssueDetail(data);
      showDataQualityDetailPanel();
    } catch (error) {
      if (host) host.innerHTML = '<div class="data-quality-error">' + esc(error.message || 'Không tải được chi tiết') + '</div>';
      showDataQualityDetailPanel();
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

  function renderRelationshipReview(data) {
    const host = qs('#dataQualityIssueDetail');
    if (!host) return;
    const groups = Array.isArray(data.groups) ? data.groups : [];
    const options = Array.isArray(data.relationshipOptions) ? data.relationshipOptions : [];
    const priorities = data.priorities || {};
    const filter = state.relationshipReviewFilter || 'ALL_UNRESOLVED';
    const isTwoMemberGroup = group => Number(group.current_member_count || 0) === 2 || group.priority === 'TWO_MEMBER_HOUSEHOLD' || group.priority === 'ELDERLY_TWO_MEMBER_HOUSEHOLD';
    const isElderlyTwoMemberGroup = group => (Number(group.current_member_count || 0) === 2 && Number(group.elderly_member_count || 0) === 2) || group.priority === 'ELDERLY_TWO_MEMBER_HOUSEHOLD';
    const filteredGroups = groups.filter(group => {
      if (filter === 'TWO_MEMBER_HOUSEHOLD') return isTwoMemberGroup(group);
      if (filter === 'ELDERLY_TWO_MEMBER_HOUSEHOLD') return isElderlyTwoMemberGroup(group);
      if (filter === 'MULTI_MEMBER_HOUSEHOLD') return Number(group.current_member_count || 0) !== 2;
      return true;
    });
    const filterOptions = [
      ['ALL_UNRESOLVED', 'Tất cả cần rà soát', data.total || groups.length],
      ['TWO_MEMBER_HOUSEHOLD', 'Hộ 2 người', priorities.two_member_household || 0],
      ['ELDERLY_TWO_MEMBER_HOUSEHOLD', 'Hộ 2 người cao tuổi', priorities.elderly_two_member_household || 0],
      ['MULTI_MEMBER_HOUSEHOLD', 'Hộ nhiều người', priorities.multi_member_household || 0]
    ];
    host.innerHTML = '<div class="data-quality-detail-summary relationship-review-summary">' +
      '<div><strong>Quan hệ cần rà soát</strong><span>' + num(data.total || groups.length) + ' nhân khẩu</span></div>' +
      '<div><strong>Ưu tiên hộ 2 người</strong><span>' + num(priorities.two_member_household || 0) + ' hộ</span></div>' +
      '<div><strong>Hộ 2 người cao tuổi</strong><span>' + num(priorities.elderly_two_member_household || 0) + ' hộ</span></div>' +
      '</div>' +
      '<div class="d-flex flex-wrap gap-2 align-items-center mb-3">' +
      '<label class="form-label mb-0" for="relationshipReviewFilter">Bộ lọc</label>' +
      '<select id="relationshipReviewFilter" class="form-select form-select-sm w-auto" data-platform-action="dataQuality.relationshipFilter">' +
      filterOptions.map(row => '<option value="' + esc(row[0]) + '"' + (filter === row[0] ? ' selected' : '') + '>' + esc(row[1]) + ' (' + num(row[2]) + ')</option>').join('') +
      '</select>' +
      '<span class="text-muted small">Chỉ gợi ý thứ tự rà soát, không tự suy luận quan hệ.</span>' +
      '</div>' +
      (filteredGroups.length ? filteredGroups.map(group => relationshipReviewGroup(group, options)).join('') : '<div class="data-quality-empty">Không có hộ phù hợp bộ lọc.</div>');
  }

  function relationshipReviewGroup(group, options) {
    const citizens = Array.isArray(group.citizens) ? group.citizens : [];
    return '<article class="relationship-review-household" data-relationship-review-household="' + Number(group.householdId || 0) + '">' +
      '<header><div><strong>' + esc(group.householdCode || 'Chưa có mã hộ') + '</strong>' +
      '<span>Chủ hộ: ' + esc(group.headName || 'Chưa xác định') + '</span></div>' +
      '<div class="relationship-review-priority">' + relationshipPriorityLabel(group) + '</div></header>' +
      '<div class="relationship-review-members">' + citizens.map(citizen => relationshipReviewRow(citizen, options)).join('') + '</div>' +
      '</article>';
  }

  function relationshipPriorityLabel(group) {
    if (group.priority === 'ELDERLY_TWO_MEMBER_HOUSEHOLD' || (Number(group.current_member_count || 0) === 2 && Number(group.elderly_member_count || 0) === 2)) return '<span class="badge bg-warning text-dark">Hộ 2 người cao tuổi - ưu tiên rà soát</span>';
    if (group.priority === 'TWO_MEMBER_HOUSEHOLD' || Number(group.current_member_count || 0) === 2) return '<span class="badge bg-info text-dark">Hộ 2 người - cần xác nhận quan hệ</span>';
    return '<span class="badge bg-secondary">Cần rà soát</span>';
  }

  function relationshipReviewRow(citizen, options) {
    const optionHtml = options.map(option => '<option value="' + esc(option) + '">' + esc(option) + '</option>').join('');
    return '<div class="relationship-review-member" data-citizen-id="' + Number(citizen.id || 0) + '">' +
      '<div><strong>' + esc(citizen.fullName || citizen.full_name || '') + '</strong>' +
      '<span>' + esc(citizen.citizenCode || citizen.citizen_code || '') + ' · ' + esc(citizen.dateOfBirth || citizen.date_of_birth || citizen.birthYear || '') + ' · ' + esc(citizen.gender || '') + '</span>' +
      '<small>Quan hệ hiện tại: ' + esc(citizen.relationship || 'Chưa xác định') + '</small></div>' +
      '<div class="relationship-review-actions">' +
      '<select class="form-select form-select-sm" data-relationship-select="' + Number(citizen.id || 0) + '"><option value="">Chọn quan hệ</option>' + optionHtml + '</select>' +
      '<button class="btn btn-sm btn-primary" type="button" data-platform-action="dataQuality.confirmRelationship" data-citizen-id="' + Number(citizen.id || 0) + '">Xác nhận</button>' +
      '</div></div>';
  }

  async function confirmRelationship(button) {
    const citizenId = Number(button && button.getAttribute('data-citizen-id') || 0);
    const select = qs('[data-relationship-select="' + citizenId + '"]');
    const relationship = select && select.value;
    if (!citizenId || !relationship) return toast('Vui lòng chọn quan hệ cần xác nhận.', 'warning');
    if (!window.confirm('Xác nhận cập nhật quan hệ nhân khẩu này?')) return;
    button.disabled = true;
    try {
      await apiPost('/api/citizens/' + citizenId + '/relationship-review', { relationship });
      toast('Đã cập nhật quan hệ.', 'success');
      await loadIssueDetail('citizen.relationship_unresolved');
    } catch (error) {
      toast(error.message || 'Không cập nhật được quan hệ.', 'error');
    } finally {
      button.disabled = false;
    }
  }

  function changeRelationshipFilter() {
    const filter = qs('#relationshipReviewFilter');
    state.relationshipReviewFilter = filter && filter.value || 'ALL_UNRESOLVED';
    loadIssueDetail('citizen.relationship_unresolved');
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
    hideDataQualityDetailPanel();
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
      .register('dataQuality.createWorkTask', context => createWorkTaskFromIssue(context.dataset.issueCode || ''))
      .register('dataQuality.openWorkTask', context => window.WorkTaskQuickCreate?.openTaskBySource(context.dataset.sourceRef || ''))
      .register('dataQuality.filterGroup', context => {
        const select = qs('#dataQualityGroupFilter');
        if (select) select.value = context.dataset.group || '';
        renderIssues();
      })
      .register('dataQuality.openCitizen', openCitizen)
      .register('dataQuality.openHousehold', openHousehold)
      .register('dataQuality.confirmRelationship', confirmRelationship)
      .register('dataQuality.relationshipFilter', changeRelationshipFilter)
      .register('dataQuality.backToIssues', hideDataQualityDetailPanel);
  }

  function bindEvents() {
    qs('#dataQualitySeverityFilter')?.addEventListener('change', renderIssues);
    qs('#dataQualityGroupFilter')?.addEventListener('change', renderIssues);
    document.addEventListener('change', event => {
      if (event.target && event.target.id === 'relationshipReviewFilter') changeRelationshipFilter();
    });
    document.addEventListener('tenant:screen-change', event => {
      const screen = event.detail && (event.detail.screenId || event.detail.screen || event.detail.moduleKey);
      if ((screen === 'dataQuality' || isActive()) && !state.summary) loadDataQuality();
    });
    document.addEventListener('tenant:auth-state', event => {
      if (event.detail && event.detail.authenticated && isActive()) loadDataQuality();
    });
    document.addEventListener('work-tasks:changed', () => {
      if (state.summary) hydrateIssueTasks();
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
