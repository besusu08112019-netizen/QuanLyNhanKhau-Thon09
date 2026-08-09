(() => {
  'use strict';

  const state = { report: null, center: null, templates: [], loaded: false };
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[c]));
  const fmt = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
  const token = () => (window.App && App.token) || localStorage.getItem(tenantStorageKey('token')) || '';
  const csrf = () => (window.App && App.csrfToken) || localStorage.getItem(tenantStorageKey('csrf')) || '';
  const REPORT_ENDPOINTS = Object.freeze({
    center: '/api/reports/center',
    bi: '/api/reports/bi',
    templates: '/api/reports/templates',
    summary: '/api/reports/summary',
    exportExcel: '/api/reports/export-excel',
    exportPdf: '/api/reports/export-pdf',
    exportWord: '/api/reports/export-word',
    template: id => '/api/reports/templates/' + encodeURIComponent(id),
    templateDefault: id => '/api/reports/templates/' + encodeURIComponent(id) + '/default'
  });
  const reportEndpoint = (name, query) => REPORT_ENDPOINTS[name] + (query ? '?' + query : '');
  const FALLBACK_REPORT_TYPES = [
    ['summary', 'Báo cáo tổng hợp'],
    ['population', 'Nhân khẩu - Danh sách'],
    ['household', 'Hộ gia đình - Danh sách'],
    ['temporary_residence', 'Tạm trú - Danh sách'],
    ['temporary_absence', 'Tạm vắng - Danh sách'],
    ['migration', 'Biến động nhân khẩu'],
    ['public-assets', 'Công trình công cộng - Danh sách'],
    ['public-assets-located', 'Công trình công cộng - Đã có GPS'],
    ['public-assets-missing-gps', 'Công trình công cộng - Chưa có GPS'],
    ['public-assets-inventory', 'Công trình công cộng - Kiểm kê tài sản'],
    ['houses', 'Nhà ở và công trình - Danh sách'],
    ['houses-degraded', 'Nhà ở xuống cấp'],
    ['houses-temporary', 'Nhà tạm'],
    ['houses-fire-risk', 'Nhà nguy cơ PCCC'],
    ['houses-missing-gps', 'Nhà chưa có GPS'],
    ['household-business-production', 'Hộ sản xuất'],
    ['household-business-trade', 'Hộ kinh doanh'],
    ['household-business-sector', 'Hộ SXKD theo ngành nghề'],
    ['household-business-status', 'Hộ SXKD theo trạng thái'],
    ['agriculture', 'Sản xuất nông nghiệp - Danh sách'],
    ['agriculture-producers', 'Chủ thể sản xuất nông nghiệp'],
    ['agriculture-area', 'Diện tích sản xuất nông nghiệp'],
    ['agriculture-crop', 'Cây trồng'],
    ['agriculture-season', 'Mùa vụ'],
    ['agriculture-production', 'Sản lượng nông nghiệp'],
    ['agriculture-damage', 'Thiệt hại nông nghiệp'],
    ['livestock', 'Vật nuôi - Danh sách'],
    ['livestock-by-type', 'Vật nuôi theo loại'],
    ['livestock-vaccinated', 'Vật nuôi đã tiêm phòng'],
    ['livestock-unvaccinated', 'Vật nuôi chưa tiêm phòng'],
    ['livestock-disease', 'Vật nuôi có dịch bệnh'],
    ['party-members', 'Đảng viên - Danh sách'],
    ['party-members-branch', 'Đảng viên theo chi bộ'],
    ['party-members-age', 'Đảng viên theo độ tuổi'],
    ['party-members-gender', 'Đảng viên theo giới tính'],
    ['party-members-position', 'Đảng viên theo chức vụ'],
    ['party-members-official', 'Đảng viên chính thức'],
    ['party-members-probationary', 'Đảng viên dự bị'],
    ['party-members-status', 'Đảng viên theo tình trạng'],
    ['vehicles', 'Xe cộ - Danh sách'],
    ['vehicles-by-type', 'Xe cộ theo loại'],
    ['vehicles-missing-plate', 'Xe chưa có biển số'],
    ['vehicles-expired-inspection', 'Xe hết hạn kiểm định'],
    ['vehicles-expired-insurance', 'Xe hết hạn bảo hiểm'],
    ['contributions-list', 'Đóng góp hộ - Danh sách'],
    ['contributions-collection', 'Đóng góp hộ - Thu tiền'],
    ['contributions-unpaid-list', 'Đóng góp hộ - Chưa nộp'],
    ['contributions-partial', 'Đóng góp hộ - Nộp một phần'],
    ['contributions-exempt', 'Đóng góp hộ - Miễn giảm'],
    ['contributions-summary', 'Đóng góp hộ - Tổng hợp'],
    ['contributions-year-summary', 'Đóng góp hộ - Tổng hợp năm'],
    ['contributions-by-contribution', 'Đóng góp hộ - Theo khoản thu'],
    ['gis', 'GIS - Hộ gia đình'],
    ['gis-located', 'GIS - Đã định vị'],
    ['gis-unlocated', 'GIS - Chưa định vị'],
    ['digital-profile', 'Hồ sơ số'],
    ['profile-complete', 'Hồ sơ hoàn chỉnh'],
    ['profile-missing-photo', 'Hồ sơ thiếu ảnh'],
    ['profile-missing-documents', 'Hồ sơ thiếu giấy tờ'],
    ['profile-incomplete', 'Hồ sơ chưa hoàn thiện'],
    ['health_insurance', 'Bao hiem y te'],
    ['health-insurance-missing', 'Chua tham gia BHYT'],
    ['health-insurance-expiring', 'BHYT sap het han'],
    ['health-insurance-expired', 'BHYT da het han'],
    ['health-insurance-household', 'BHYT theo ho'],
    ['health-insurance-area', 'BHYT theo khu vuc'],
    ['children', 'Trẻ em'],
    ['elderly', 'Người cao tuổi'],
    ['labor', 'Lao động'],
    ['party_member', 'Đảng viên'],
    ['youth_union', 'Đoàn viên'],
    ['poor-households', 'Hộ nghèo'],
    ['near-poor-households', 'Hộ cận nghèo'],
    ['age', 'Theo độ tuổi'],
    ['gender', 'Theo giới tính']
  ];
  const MODULE_REPORT_TYPES = {
    households: { screen: 'householdsScreen', type: 'household' },
    persons: { screen: 'personsScreen', type: 'population' },
    temporaryResidence: { screen: 'temporaryResidenceScreen', type: 'temporary_residence' },
    temporaryAbsence: { screen: 'temporaryAbsenceScreen', type: 'temporary_absence' },
    movements: { screen: 'movementsScreen', type: 'migration' },
    publicAssets: { screen: 'publicAssetsScreen', type: 'public-assets' },
    houses: { screen: 'housesScreen', type: 'houses' },
    businessHouseholds: { screen: 'businessHouseholdsScreen', type: 'household-business-production' },
    agriculture: { screen: 'agricultureScreen', type: 'agriculture' },
    livestock: { screen: 'livestockScreen', type: 'livestock' },
    partyMembers: { screen: 'partyMembersScreen', type: 'party-members' },
    vehicles: { screen: 'vehiclesScreen', type: 'vehicles' },
    contributions: { screen: 'contributionsScreen', type: 'contributions-list' }
  };

  document.addEventListener('DOMContentLoaded', bindSmartReporting);
  document.addEventListener('tenant:screen-change', event => {
    ensureModuleReportButtons();
    if (event.detail?.screen === 'reports') initSmartReporting(true);
  });

  function isReportsActive() {
    return (window.TenantAppPlatform?.navigation?.current?.()?.screenId || window.App?.screen || document.querySelector('.screen.active')?.id?.replace(/Screen$/, '')) === 'reports';
  }

  function bindSmartReporting() {
    if (window.__TenantAppSmartReportingBound) return;
    window.__TenantAppSmartReportingBound = true;
    const form = $('#reportForm');
    if (!form) return;
    ensureReportTypes();
    setTimeout(ensureReportTypes, 0);
    setTimeout(ensureReportTypes, 80);
    enhanceReportLayout();
    form.addEventListener('submit', event => { event.preventDefault(); event.stopImmediatePropagation(); loadReport(); }, true);
    form.addEventListener('change', event => {
      if (event.target?.name === 'type') {
        state.report = null;
        setActions(false);
        expandReportFilters();
        updateFilterSummary();
      }
      scheduleBiRefresh();
    });
    registerReportPlatformActions();
    ensureModuleReportButtons();
    if (isReportsActive()) initSmartReporting();
  }

  function capture(fn) {
    return event => { event.preventDefault(); event.stopImmediatePropagation(); fn(); };
  }

  function registerReportPlatformActions() {
    const actions = window.TenantAppPlatform && window.TenantAppPlatform.actions;
    if (!actions || typeof actions.register !== 'function') return;
    actions.register('reports.refresh', () => initSmartReporting(true));
    actions.register('reports.template.save', saveTemplate);
    actions.register('reports.template.open', context => openTemplate(context.target));
    actions.register('reports.template.delete', context => deleteTemplate(context.dataset.templateDelete));
    actions.register('reports.template.default', context => defaultTemplate(context.dataset.templateDefault));
    actions.register('reports.type.select', context => selectReportType(context.dataset.reportType));
    actions.register('reports.type.open', context => selectReportType(context.dataset.reportType, true));
    actions.register('reports.module.open', context => openReportType(context.dataset.reportType || context.dataset.moduleReportType));
    actions.register('reports.filters.edit', expandReportFilters);
    actions.register('reports.panel.toggle', context => toggleReportPanel(context.target));
    actions.register('reports.print', printReport);
    actions.register('reports.export.excel', () => downloadReport(REPORT_ENDPOINTS.exportExcel, 'xls', 'Đã xuất Excel'));
    actions.register('reports.export.pdf', () => downloadReport(REPORT_ENDPOINTS.exportPdf, 'pdf', 'Đã xuất PDF'));
    actions.register('reports.export.word', () => downloadReport(REPORT_ENDPOINTS.exportWord, 'doc', 'Đã xuất Word'));
  }

  function ensureReportTypes() {
    const select = $('#reportTypeSelect');
    if (!select) return;
    const value = select.value || 'summary';
    const types = [
      ['summary', 'Báo cáo tổng hợp'], ['population', 'Báo cáo nhân khẩu'], ['household', 'Báo cáo hộ gia đình'], ['migration', 'Báo cáo biến động'],
      ['contributions-list', 'Đóng góp - Danh sách hộ'], ['contributions-collection', 'Đóng góp - Danh sách thu tiền'], ['contributions-unpaid-list', 'Đóng góp - Hộ chưa nộp'], ['contributions-partial', 'Đóng góp - Hộ nộp một phần'], ['contributions-exempt', 'Đóng góp - Hộ được miễn'], ['contributions-summary', 'Đóng góp - Tổng hợp cuối đợt'], ['contributions-year-summary', 'Đóng góp - Tổng hợp theo năm'], ['contributions-by-contribution', 'Đóng góp - Theo khoản thu'],
      ['gis', 'Báo cáo GIS'], ['gis-located', 'Hộ đã định vị GPS'], ['gis-unlocated', 'Hộ chưa định vị GPS'],
      ['digital-profile', 'Báo cáo Hồ sơ số'], ['profile-complete', 'Hồ sơ hoàn chỉnh'], ['profile-missing-photo', 'Hồ sơ thiếu ảnh'], ['profile-missing-documents', 'Hồ sơ thiếu giấy tờ'], ['profile-incomplete', 'Hồ sơ chưa hoàn thiện'],
      ['health_insurance', 'Thống kê Bảo hiểm y tế'], ['health-insurance-missing', 'Danh sách chưa tham gia BHYT'], ['health-insurance-expiring', 'Danh sách BHYT sắp hết hạn (30 ngày)'], ['health-insurance-expired', 'Danh sách BHYT đã hết hạn'], ['health-insurance-household', 'Thống kê BHYT theo hộ'], ['health-insurance-area', 'Thống kê BHYT theo khu vực'], ['temporary_residence', 'Danh sách tạm trú'], ['temporary_absence', 'Danh sách tạm vắng'], ['children', 'Danh sách trẻ em'], ['elderly', 'Danh sách người cao tuổi'], ['labor', 'Danh sách lao động'], ['party_member', 'Danh sách Đảng viên'], ['youth_union', 'Danh sách Đoàn viên'], ['poor-households', 'Danh sách hộ nghèo'], ['near-poor-households', 'Danh sách hộ cận nghèo'], ['age', 'Thống kê theo độ tuổi'], ['gender', 'Thống kê theo giới tính']
    ];
    types.splice(2, 0, ['public-assets', 'Công trình công cộng - Danh sách'], ['public-assets-located', 'Công trình công cộng - Đã có GPS'], ['public-assets-missing-gps', 'Công trình công cộng - Chưa có GPS'], ['public-assets-inventory', 'Công trình công cộng - Kiểm kê tài sản']);
    reportTypes().forEach(([key, label]) => {
      if (!types.some(([itemKey]) => itemKey === key)) types.push([key, label]);
    });
    select.innerHTML = types.map(([key, label]) => '<option value="' + esc(key) + '">' + esc(label) + '</option>').join('');
    select.value = types.some(([key]) => key === value) ? value : 'summary';
  }

  function reportTypes() {
    const byKey = new Map(FALLBACK_REPORT_TYPES);
    (state.center?.templates || []).forEach(item => {
      if (item?.type) byKey.set(item.type, item.title || item.type);
    });
    (state.center?.groups || []).forEach(group => {
      (group.types || []).forEach(type => {
        if (!byKey.has(type)) byKey.set(type, (group.title || 'Báo cáo') + ' - ' + type);
      });
    });
    return Array.from(byKey.entries());
  }

  async function initSmartReporting(force = false) {
    if (!isReportsActive()) return;
    enhanceReportLayout();
    if (typeof window.TenantAppCanAccess === 'function' && !window.TenantAppCanAccess('reports', 'read')) return;
    ensureReportTypes();
    if (!token()) return;
    if (state.loaded && !force) return;
    state.loaded = true;
    await Promise.allSettled([loadCenter(), loadTemplates(), loadBi()]);
    if (!state.report) loadReport({ collapseFilters: false }).catch(() => {});
  }

  async function smartApi(url, options = {}) {
    if (typeof window.api === 'function' && !options.raw) {
      const payload = await window.api(url, options);
      return payload?.data ?? payload;
    }
    const headers = { Accept: 'application/json' };
    if (options.body) headers['Content-Type'] = 'application/json';
    if (token()) headers.Authorization = 'Bearer ' + token();
    if (csrf()) headers['X-CSRF-Token'] = csrf();
    const res = await fetch(url, { method: options.method || 'GET', headers, body: options.body ? JSON.stringify(options.body) : undefined, cache: 'no-store' });
    const json = await res.json().catch(() => null);
    if (!res.ok || !json?.ok) throw new Error(json?.error?.message || 'Không tải được dữ liệu');
    return json.data?.data ?? json.data ?? {};
  }

  function reportQuery() {
    const form = $('#reportForm');
    const params = new URLSearchParams();
    if (!form) return params.toString();
    new FormData(form).forEach((value, key) => {
      const text = String(value ?? '').trim();
      if (text !== '') params.set(key, text);
    });
    const type = params.get('type') || 'summary';
    params.set('type', type);
    params.set('report_type', type);
    return params.toString();
  }

  function currentFilters() {
    const out = {};
    const form = $('#reportForm');
    if (!form) return out;
    new FormData(form).forEach((value, key) => {
      const text = String(value ?? '').trim();
      if (text !== '') out[key] = text;
    });
    return out;
  }

  function enhanceReportLayout() {
    const screen = $('#reportsScreen');
    const form = $('#reportForm');
    const centerGrid = screen ? $('.report-center-grid', screen) : null;
    const center = screen ? $('.smart-report-center', screen) : null;
    const bi = screen ? $('.smart-report-bi', screen) : null;
    const tools = screen ? $('.report-tools-grid', screen) : null;
    const result = screen ? $('.smart-report-result-card', screen) : null;
    if (!screen || !form || !center || !bi || !tools || !result) return;

    result.id ||= 'reportResultCard';
    $('#reportTitle')?.setAttribute('tabindex', '-1');

    if (!$('.report-workspace', screen)) {
      const workspace = document.createElement('div');
      workspace.className = 'report-workspace';
      const side = document.createElement('aside');
      side.className = 'report-workspace-side';
      const main = document.createElement('main');
      main.className = 'report-workspace-main';
      screen.insertBefore(workspace, screen.firstElementChild);
      workspace.append(side, main);
      side.append(center, form, tools);
      main.append(bi, result);
      centerGrid?.remove();
    }

    ensureFilterSummary(form);
    ensurePanelToggle(bi, 'Dashboard BI', true);
    $$('.report-template-card', tools).forEach(card => ensurePanelToggle(card, card.querySelector('h3')?.textContent || 'Mau bao cao', false));
    updateFilterSummary();
  }

  function ensureFilterSummary(form) {
    if ($('#reportFilterSummary', form)) return;
    const summary = document.createElement('div');
    summary.id = 'reportFilterSummary';
    summary.className = 'report-filter-summary d-none';
    summary.setAttribute('aria-live', 'polite');
    form.insertBefore(summary, form.firstElementChild);
  }

  function ensurePanelToggle(card, title, initiallyOpen) {
    if (card.dataset.reportCollapsibleReady === '1') return;
    card.dataset.reportCollapsibleReady = '1';
    card.classList.add('report-collapsible-card');
    const body = document.createElement('div');
    body.className = 'report-collapsible-body';
    while (card.firstChild) body.appendChild(card.firstChild);
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'report-collapsible-toggle';
    button.setAttribute('aria-expanded', initiallyOpen ? 'true' : 'false');
    button.dataset.platformAction = 'reports.panel.toggle';
    button.innerHTML = '<span><i class="fa-solid fa-chevron-right"></i> ' + esc(title) + '</span>';
    card.append(button, body);
    setPanelOpen(card, initiallyOpen);
  }

  function toggleReportPanel(target) {
    const card = target?.closest?.('.report-collapsible-card');
    if (card) setPanelOpen(card, !card.classList.contains('is-open'));
  }

  function setPanelOpen(card, open) {
    card.classList.toggle('is-open', open);
    card.querySelector('.report-collapsible-toggle')?.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function setBiOpen(open) {
    const bi = $('.smart-report-bi');
    if (bi) setPanelOpen(bi, open);
  }

  function collapseReportFilters() {
    const form = $('#reportForm');
    if (!form) return;
    updateFilterSummary();
    form.classList.add('report-filter-collapsed');
    $('#reportFilterSummary', form)?.classList.remove('d-none');
  }

  function expandReportFilters() {
    const form = $('#reportForm');
    if (!form) return;
    form.classList.remove('report-filter-collapsed');
    $('#reportFilterSummary', form)?.classList.add('d-none');
    $('#reportTypeSelect')?.focus({ preventScroll: true });
  }

  function updateFilterSummary() {
    const form = $('#reportForm');
    const target = $('#reportFilterSummary', form || document);
    if (!form || !target) return;
    const labels = {
      type: 'Loại báo cáo',
      dateFrom: 'Từ ngày',
      dateTo: 'Đến ngày',
      area: 'Khu vực',
      householdCode: 'Mã hộ',
      headName: 'Chủ hộ',
      citizen: 'Nhân khẩu',
      gender: 'Giới tính',
      ageFrom: 'Tuổi từ',
      ageTo: 'Tuổi đến',
      occupation: 'Nghề nghiệp',
      category: 'Diện hộ',
      residencyStatus: 'Cư trú',
      presenceStatus: 'Hiện diện',
      gpsStatus: 'GPS',
      digitalProfileStatus: 'Hồ sơ số',
      party_member: 'Đảng viên',
      youth_union_member: 'Đoàn viên'
    };
    const items = [];
    new FormData(form).forEach((value, key) => {
      const text = String(value ?? '').trim();
      if (!text) return;
      const field = form.elements[key];
      let display = text;
      if (field?.tagName === 'SELECT') display = field.selectedOptions?.[0]?.textContent?.trim() || text;
      if (field?.type === 'checkbox') display = 'Co';
      items.push([labels[key] || key, display]);
    });
    if (!items.some(([label]) => label === labels.type)) {
      const select = $('#reportTypeSelect');
      if (select) items.unshift([labels.type, select.selectedOptions?.[0]?.textContent?.trim() || select.value || 'Tong hop']);
    }
    target.innerHTML = '<div><strong>Bộ lọc đang áp dụng</strong><div class="report-filter-summary-tags">' + items.slice(0, 10).map(([label, value]) => '<span><b>' + esc(label) + ':</b> ' + esc(value) + '</span>').join('') + '</div></div><button class="btn btn-outline-primary btn-sm" type="button" data-platform-action="reports.filters.edit"><i class="fa-solid fa-pen-to-square"></i> Chỉnh sửa bộ lọc</button>';
  }

  async function loadCenter() {
    try {
      const data = await smartApi(REPORT_ENDPOINTS.center);
      state.center = data;
      ensureReportTypes();
      renderGroups(data.groups || []);
      renderTemplateLibrary(data.templates || []);
    } catch (error) {
      renderBox('#reportGroupGrid', '<div class="report-widget-error">' + esc(error.message) + '</div>');
    }
  }

  async function loadBi() {
    try {
      const data = await smartApi(reportEndpoint('bi', reportQuery()));
      renderBi(data);
    } catch (error) {
      renderBox('#reportBiCharts', '<div class="report-widget-error">' + esc(error.message) + '</div>');
    }
  }

  const scheduleBiRefresh = debounce(() => loadBi().catch(() => {}), 350);

  async function loadTemplates() {
    try {
      state.templates = await smartApi(REPORT_ENDPOINTS.templates);
      renderSavedTemplates(state.templates || []);
    } catch (error) {
      renderBox('#reportSavedTemplates', '<div class="report-widget-error">' + esc(error.message) + '</div>');
    }
  }

  window.loadReport = window.TenantAppViewReport = async function loadReport(options = {}) {
    const shouldCollapseFilters = options.collapseFilters !== false;
    enhanceReportLayout();
    setActions(false);
    setTitle('Báo cáo');
    setCount('Đang tải dữ liệu...');
    renderBox('#reportPreview', reportLoading());
    scrollToReportResult(false);
    try {
      const report = await smartApi(reportEndpoint('summary', reportQuery()));
      state.report = report;
      setTitle(report.title || 'Báo cáo');
      setCount(fmt(report.totalRows || 0) + ' dòng');
      renderBox('#reportPreview', reportMeta(report) + reportTable(report) + reportSignatures(report));
      setActions(true);
      if (shouldCollapseFilters) collapseReportFilters();
      else expandReportFilters();
      setBiOpen(false);
      scrollToReportResult(true);
      return report;
    } catch (error) {
      setCount('Không sinh được báo cáo');
      renderBox('#reportPreview', '<div class="alert alert-danger mb-0">' + esc(error.message) + '</div>');
      scrollToReportResult(true);
      throw error;
    }
  };

  function renderGroups(groups) {
    renderBox('#reportGroupGrid', groups.map(group => '<button class="report-group-item" type="button" data-platform-action="reports.type.select" data-report-type="' + esc(group.types?.[0] || 'summary') + '"><i class="fa-solid ' + esc(group.icon || 'fa-chart-pie') + '"></i><strong>' + esc(group.title) + '</strong><span>' + esc(group.description) + '</span></button>').join(''));
  }

  function renderTemplateLibrary(items) {
    renderBox('#reportTemplateLibrary', items.map(item => '<button type="button" data-platform-action="reports.type.open" data-report-type="' + esc(item.type) + '"><i class="fa-solid fa-file-lines"></i><span>' + esc(item.title) + '</span></button>').join(''));
  }

  function renderSavedTemplates(items) {
    if (!items.length) return renderBox('#reportSavedTemplates', '<div class="report-template-empty">Chưa có mẫu đã lưu</div>');
    renderBox('#reportSavedTemplates', items.map(item => {
      const filters = esc(item.filters_json || '{}');
      return '<article class="report-saved-template"><button type="button" data-platform-action="reports.template.open" data-template-open="' + item.id + '" data-filters="' + filters + '" data-type="' + esc(item.type) + '"><strong>' + esc(item.name) + '</strong><span>' + esc(item.type) + (Number(item.is_default) ? ' - Mặc định' : '') + '</span></button><div><button type="button" title="Đặt mặc định" data-platform-action="reports.template.default" data-template-default="' + item.id + '"><i class="fa-solid fa-star"></i></button><button type="button" title="Xóa" data-platform-action="reports.template.delete" data-template-delete="' + item.id + '"><i class="fa-solid fa-trash"></i></button></div></article>';
    }).join(''));
  }

  function renderBi(data) {
    const metrics = data.metrics || {};
    $('#reportBiGeneratedAt') && ($('#reportBiGeneratedAt').textContent = 'Cập nhật ' + new Date(data.generatedAt || Date.now()).toLocaleString('vi-VN'));
    const kpis = [
      ['Hộ', metrics.total_households], ['Nhân khẩu', metrics.total_citizens], ['Nam', metrics.male_count], ['Nữ', metrics.female_count], ['Hộ nghèo', metrics.poor_households], ['GPS', progressValue(data, 'gps')]
    ];
    const healthInsurance = healthInsuranceSummary(metrics);
    renderBox('#reportBiKpis', kpis.map(([label, value]) => '<div><span>' + esc(label) + '</span><strong>' + esc(value ?? 0) + '</strong></div>').join('') + '<div class="report-bi-health"><span>Có BHYT</span><strong>' + esc(healthInsurance.covered) + '</strong></div><div class="report-bi-health"><span>Chưa có BHYT</span><strong>' + esc(healthInsurance.uninsured) + '</strong></div><div class="report-bi-health"><span>Tỷ lệ bao phủ</span><strong>' + esc(healthInsurance.coverage) + '</strong></div>');
    const charts = data.charts || {};
    const cards = [
      ['Cơ cấu dân số', charts.population], ['BHYT', charts.healthInsurance], ['Độ tuổi', charts.age], ['Nghề nghiệp', charts.occupation], ['Lao động', charts.labor], ['Hộ nghèo/cận nghèo', charts.poverty], ['Biến động theo tháng', charts.monthlyMovements]
    ];
    renderBox('#reportBiCharts', cards.map(([title, rows]) => '<article class="report-bi-chart"><h4>' + esc(title) + '</h4>' + miniBars(rows || []) + '</article>').join(''));
  }

  function healthInsuranceSummary(metrics) {
    const total = Number(metrics.health_insurance_total ?? metrics.total_citizens ?? 0);
    const insured = Number(metrics.health_insurance_count ?? metrics.health_insurance_covered_count ?? 0);
    const uninsured = Number(metrics.health_insurance_missing_count ?? metrics.health_insurance_uninsured_count ?? Math.max(0, total - insured));
    const coverage = Number(metrics.health_insurance_coverage_percent ?? metrics.health_insurance_percent ?? (total > 0 ? insured * 100 / total : 0));
    return { covered: fmt(insured) + '/' + fmt(total) + ' nhân khẩu', uninsured: fmt(uninsured) + ' nhân khẩu', coverage: coverage.toFixed(2) + '%' };
  }

  function progressValue(data, key) {
    const item = (data.progress || []).find(row => row.key === key);
    return item ? (item.progress?.percent || 0) + '%' : '0%';
  }

  function miniBars(rows) {
    const max = Math.max(1, ...rows.map(row => Number(row.value || 0)));
    return rows.length ? rows.slice(0, 8).map(row => '<div class="report-mini-bar"><span>' + esc(row.label || '') + '</span><i style="--w:' + Math.round(Number(row.value || 0) * 100 / max) + '%"></i><b>' + fmt(row.value || 0) + '</b></div>').join('') : '<div class="report-template-empty">Chưa có dữ liệu</div>';
  }

  function selectReportType(type, autoLoad = false) {
    ensureReportTypes();
    const select = $('#reportTypeSelect');
    if (select) select.value = type || 'summary';
    state.report = null;
    setActions(false);
    if (autoLoad) loadReport().catch(() => {});
    scheduleBiRefresh();
  }

  function openReportType(type) {
    const reportType = type || 'summary';
    const navigate = window.TenantAppNavigationController?.navigate || window.TenantAppPlatform?.navigation?.navigate;
    if (typeof navigate === 'function') navigate.call(window.TenantAppNavigationController || window.TenantAppPlatform.navigation, 'reports');
    setTimeout(() => selectReportType(reportType, true), 80);
  }

  function ensureModuleReportButtons() {
    Object.entries(MODULE_REPORT_TYPES).forEach(([moduleKey, config]) => {
      const screen = document.getElementById(config.screen);
      if (!screen || screen.querySelector('[data-module-report-button="' + moduleKey + '"]')) return;
      const target = screen.querySelector('.agri-toolbar > div, .houses-toolbar > div, .livestock-toolbar-left, .person-list-head > div:last-child, .module-list-head > div:last-child, .module-page-head, .content-card');
      if (!target) return;
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-outline-primary btn-sm module-report-btn';
      button.dataset.platformAction = 'reports.module.open';
      button.dataset.moduleReportButton = moduleKey;
      button.dataset.reportType = config.type;
      button.innerHTML = '<i class="fa-solid fa-chart-pie"></i> Báo cáo';
      target.appendChild(button);
    });
  }

  async function saveTemplate() {
    const name = prompt('Tên mẫu báo cáo');
    if (!name) return;
    const filters = currentFilters();
    const template = await smartApi(REPORT_ENDPOINTS.templates, { method: 'POST', body: { name, type: filters.type || 'summary', filters } });
    state.templates.unshift(template);
    await loadTemplates();
    toast('Đã lưu mẫu báo cáo');
  }

  function openTemplate(btn) {
    const type = btn.dataset.type || 'summary';
    let filters = {};
    try { filters = JSON.parse(btn.dataset.filters || '{}') || {}; } catch (_) {}
    const form = $('#reportForm');
    if (form) Object.entries(filters).forEach(([key, value]) => { if (form.elements[key]) form.elements[key].value = value ?? ''; });
    selectReportType(type, true);
  }

  async function deleteTemplate(id) {
    await smartApi(REPORT_ENDPOINTS.template(id), { method: 'DELETE' });
    await loadTemplates();
    toast('Đã xóa mẫu báo cáo');
  }

  async function defaultTemplate(id) {
    await smartApi(REPORT_ENDPOINTS.templateDefault(id), { method: 'POST', body: {} });
    await loadTemplates();
    toast('Đã đặt mẫu mặc định');
  }

  async function downloadReport(endpoint, extension, successMessage) {
    const response = await fetch(endpoint + '?' + reportQuery(), { headers: { Authorization: 'Bearer ' + token() }, cache: 'no-store' });
    if (!response.ok) throw new Error('Không xuất được dữ liệu');
    const blob = await response.blob();
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = fileNameFromHeader(response.headers.get('Content-Disposition')) || 'bao_cao_' + Date.now() + '.' + extension;
    document.body.appendChild(link);
    link.click();
    URL.revokeObjectURL(link.href);
    link.remove();
    toast(successMessage);
  }

  async function printReport() {
    const report = state.report || await loadReport();
    if (!window.TenantAppPrint) return toast('Print Framework is not ready', 'warning');
    const popup = window.TenantAppPrint.render(reportConfig(report));
    if (!popup) toast('Print popup was blocked', 'warning');
  }

  function reportConfig(report) {
    const filters = Object.assign({}, report.filters || {}, currentFilters());
    return {
      title: report.title || 'Báo cáo',
      type: filters.type || filters.report_type || '',
      paperSize: 'A4',
      orientation: report.orientation || '',
      headers: report.headers || [],
      rows: report.rows || [],
      totalRows: report.totalRows,
      filters,
      meta: report.meta || {},
      unitName: reportUnitName(report),
      repeatHeader: true,
      showFooter: true,
      showSummary: true,
      showSignature: true
    };
  }

  function reportTable(report) {
    const headers = (report.headers || []).map(header => '<th>' + esc(header) + '</th>').join('');
    const rows = (report.rows || []).map(row => '<tr>' + row.map(cell => '<td>' + esc(cell) + '</td>').join('') + '</tr>').join('') || '<tr><td colspan="' + Math.max(1, (report.headers || []).length) + '" class="text-center text-muted py-4">Không có dữ liệu phù hợp với điều kiện lọc.</td></tr>';
    return '<table class="table report-table align-middle mb-0"><thead><tr>' + headers + '</tr></thead><tbody>' + rows + '</tbody></table>';
  }

  function reportMeta(report) {
    const meta = report.meta || {};
    const lines = ['period_label','business_note','report_date'].map(key => meta[key]).filter(Boolean);
    return lines.length ? '<div class="report-print-meta">' + lines.map(line => '<div>' + esc(line) + '</div>').join('') + '</div>' : '';
  }

  function reportHeader(report) {
    return '<div class="report-print-unit">' + esc(reportUnitName(report)) + '</div><div class="report-print-title">' + esc(report.title || 'Báo cáo') + '</div>';
  }

  function reportLoading() {
    return '<div class="report-loading-state" aria-live="polite"><div><span></span><span></span><span></span></div><p>Dang tai bao cao...</p></div>';
  }

  function scrollToReportResult(focusTitle) {
    const target = $('#reportResultCard') || $('.smart-report-result-card');
    if (!target) return;
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (focusTitle) setTimeout(() => $('#reportTitle')?.focus({ preventScroll: true }), 260);
  }

  function reportUnitName(report) {
    const meta = report.meta || {};
    const configured = window.App?.settings || window.AppSettings || {};
    const unit = meta.unit_name || configured.unitName || [configured.hamletName, configured.communeName].filter(Boolean).join(' - ');
    return unit || 'Đơn vị hành chính';
  }

  function reportSignatures(report) {
    const meta = report.meta || {};
    if (!meta.prepared_by && !meta.approved_by) return '';
    return '<div class="report-signatures"><div>' + esc(meta.prepared_by || 'Người lập biểu') + '</div><div>' + esc(meta.approved_by || 'Trưởng thôn ký xác nhận') + '</div></div>';
  }

  function setTitle(text) { const el = $('#reportTitle'); if (el) el.textContent = text; }
  function setCount(text) { const el = $('#reportCount'); if (el) el.textContent = text; }
  function setActions(show) { $('#reportActions')?.classList.toggle('d-none', !show); }
  function renderBox(selector, html) { const el = $(selector); if (el) el.innerHTML = html; }
  function fileNameFromHeader(header) { return (/filename="?([^";]+)"?/i.exec(header || '') || [])[1] || ''; }
  function toast(message, type = 'success') { if (typeof window.showToast === 'function') window.showToast(message, type); }
  function debounce(fn, wait) { let timer; return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), wait); }; }
})();
