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
    ['summary', 'Bao cao tong hop'],
    ['population', 'Nhan khau - Danh sach'],
    ['household', 'Ho gia dinh - Danh sach'],
    ['temporary_residence', 'Tam tru - Danh sach'],
    ['temporary_absence', 'Tam vang - Danh sach'],
    ['migration', 'Bien dong nhan khau'],
    ['public-assets', 'Cong trinh cong cong - Danh sach'],
    ['public-assets-located', 'Cong trinh cong cong - Da co GPS'],
    ['public-assets-missing-gps', 'Cong trinh cong cong - Chua co GPS'],
    ['public-assets-inventory', 'Cong trinh cong cong - Kiem ke tai san'],
    ['houses', 'Nha o va cong trinh - Danh sach'],
    ['houses-degraded', 'Nha o xuong cap'],
    ['houses-temporary', 'Nha tam'],
    ['houses-fire-risk', 'Nha nguy co PCCC'],
    ['houses-missing-gps', 'Nha chua co GPS'],
    ['household-business-production', 'Ho san xuat'],
    ['household-business-trade', 'Ho kinh doanh'],
    ['household-business-sector', 'Ho SXKD theo nganh nghe'],
    ['household-business-status', 'Ho SXKD theo trang thai'],
    ['agriculture', 'San xuat nong nghiep - Danh sach'],
    ['agriculture-producers', 'Chu the san xuat nong nghiep'],
    ['agriculture-area', 'Dien tich san xuat nong nghiep'],
    ['agriculture-crop', 'Cay trong'],
    ['agriculture-season', 'Mua vu'],
    ['agriculture-production', 'San luong nong nghiep'],
    ['agriculture-damage', 'Thiet hai nong nghiep'],
    ['livestock', 'Vat nuoi - Danh sach'],
    ['livestock-by-type', 'Vat nuoi theo loai'],
    ['livestock-vaccinated', 'Vat nuoi da tiem phong'],
    ['livestock-unvaccinated', 'Vat nuoi chua tiem phong'],
    ['livestock-disease', 'Vat nuoi co dich benh'],
    ['party-members', 'Dang vien - Danh sach'],
    ['defense-security', 'Quoc phong - An ninh - Tong hop'],
    ['defense-security-nvqs', 'Quoc phong - An ninh - Nghia vu quan su'],
    ['defense-security-upcoming-registration', 'NVQS - Sap den tuoi dang ky'],
    ['defense-security-registration-age', 'NVQS - Den tuoi dang ky'],
    ['defense-security-unregistered', 'NVQS - Chua dang ky'],
    ['defense-security-preliminary', 'NVQS - So tuyen'],
    ['defense-security-medical', 'NVQS - Kham tuyen'],
    ['defense-security-eligible', 'NVQS - Du dieu kien'],
    ['defense-security-deferred', 'NVQS - Tam hoan'],
    ['defense-security-exempt', 'NVQS - Mien'],
    ['defense-security-selected', 'NVQS - Trung tuyen'],
    ['defense-security-enlisted', 'NVQS - Nhap ngu'],
    ['defense-security-active-service', 'NVQS - Dang tai ngu'],
    ['defense-security-discharged', 'NVQS - Xuat ngu'],
    ['defense-security-militia', 'Dan quan tu ve'],
    ['defense-security-antt', 'ANTT co so'],
    ['party-members-branch', 'Dang vien theo chi bo'],
    ['party-members-age', 'Dang vien theo do tuoi'],
    ['party-members-gender', 'Dang vien theo gioi tinh'],
    ['party-members-position', 'Dang vien theo chuc vu'],
    ['party-members-official', 'Dang vien chinh thuc'],
    ['party-members-probationary', 'Dang vien du bi'],
    ['party-members-status', 'Dang vien theo tinh trang'],
    ['vehicles', 'Xe co - Danh sach'],
    ['vehicles-by-type', 'Xe co theo loai'],
    ['vehicles-missing-plate', 'Xe chua co bien so'],
    ['vehicles-expired-inspection', 'Xe het han kiem dinh'],
    ['vehicles-expired-insurance', 'Xe het han bao hiem'],
    ['contributions-list', 'Dong gop ho - Danh sach'],
    ['contributions-collection', 'Dong gop ho - Thu tien'],
    ['contributions-unpaid-list', 'Dong gop ho - Chua nop'],
    ['contributions-partial', 'Dong gop ho - Nop mot phan'],
    ['contributions-exempt', 'Dong gop ho - Mien giam'],
    ['contributions-summary', 'Dong gop ho - Tong hop'],
    ['contributions-year-summary', 'Dong gop ho - Tong hop nam'],
    ['contributions-by-contribution', 'Dong gop ho - Theo khoan thu'],
    ['gis', 'GIS - Ho gia dinh'],
    ['gis-located', 'GIS - Da dinh vi'],
    ['gis-unlocated', 'GIS - Chua dinh vi'],
    ['digital-profile', 'Ho so so'],
    ['profile-complete', 'Ho so hoan chinh'],
    ['profile-missing-photo', 'Ho so thieu anh'],
    ['profile-missing-documents', 'Ho so thieu giay to'],
    ['profile-incomplete', 'Ho so chua hoan thien'],
    ['health_insurance', 'Bao hiem y te'],
    ['health-insurance-missing', 'Chua tham gia BHYT'],
    ['health-insurance-expiring', 'BHYT sap het han'],
    ['health-insurance-expired', 'BHYT da het han'],
    ['health-insurance-household', 'BHYT theo ho'],
    ['health-insurance-area', 'BHYT theo khu vuc'],
    ['children', 'Tre em'],
    ['elderly', 'Nguoi cao tuoi'],
    ['labor', 'Lao dong'],
    ['party_member', 'Dang vien'],
    ['youth_union', 'Doan vien'],
    ['poor-households', 'Ho ngheo'],
    ['near-poor-households', 'Ho can ngheo'],
    ['age', 'Theo do tuoi'],
    ['gender', 'Theo gioi tinh']
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
    defenseSecurity: { screen: 'defenseSecurityScreen', type: 'defense-security' },
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
    actions.register('reports.export.excel', () => downloadReport(REPORT_ENDPOINTS.exportExcel, 'xls', 'ÄÃ£ xuáº¥t Excel'));
    actions.register('reports.export.pdf', () => downloadReport(REPORT_ENDPOINTS.exportPdf, 'pdf', 'ÄÃ£ xuáº¥t PDF'));
    actions.register('reports.export.word', () => downloadReport(REPORT_ENDPOINTS.exportWord, 'doc', 'ÄÃ£ xuáº¥t Word'));
  }

  function ensureReportTypes() {
    const select = $('#reportTypeSelect');
    if (!select) return;
    const value = select.value || 'summary';
    const types = [
      ['summary', 'BÃ¡o cÃ¡o tá»•ng há»£p'], ['population', 'BÃ¡o cÃ¡o nhÃ¢n kháº©u'], ['household', 'BÃ¡o cÃ¡o há»™ gia Ä‘Ã¬nh'], ['migration', 'BÃ¡o cÃ¡o biáº¿n Ä‘á»™ng'],
      ['contributions-list', 'ÄÃ³ng gÃ³p - Danh sÃ¡ch há»™'], ['contributions-collection', 'ÄÃ³ng gÃ³p - Danh sÃ¡ch thu tiá»n'], ['contributions-unpaid-list', 'ÄÃ³ng gÃ³p - Há»™ chÆ°a ná»™p'], ['contributions-partial', 'ÄÃ³ng gÃ³p - Há»™ ná»™p má»™t pháº§n'], ['contributions-exempt', 'ÄÃ³ng gÃ³p - Há»™ Ä‘Æ°á»£c miá»…n'], ['contributions-summary', 'ÄÃ³ng gÃ³p - Tá»•ng há»£p cuá»‘i Ä‘á»£t'], ['contributions-year-summary', 'ÄÃ³ng gÃ³p - Tá»•ng há»£p theo nÄƒm'], ['contributions-by-contribution', 'ÄÃ³ng gÃ³p - Theo khoáº£n thu'],
      ['gis', 'BÃ¡o cÃ¡o GIS'], ['gis-located', 'Há»™ Ä‘Ã£ Ä‘á»‹nh vá»‹ GPS'], ['gis-unlocated', 'Há»™ chÆ°a Ä‘á»‹nh vá»‹ GPS'],
      ['digital-profile', 'BÃ¡o cÃ¡o Há»“ sÆ¡ sá»‘'], ['profile-complete', 'Há»“ sÆ¡ hoÃ n chá»‰nh'], ['profile-missing-photo', 'Há»“ sÆ¡ thiáº¿u áº£nh'], ['profile-missing-documents', 'Há»“ sÆ¡ thiáº¿u giáº¥y tá»'], ['profile-incomplete', 'Há»“ sÆ¡ chÆ°a hoÃ n thiá»‡n'],
      ['health_insurance', 'Thá»‘ng kÃª Báº£o hiá»ƒm y táº¿'], ['health-insurance-missing', 'Danh sÃ¡ch chÆ°a tham gia BHYT'], ['health-insurance-expiring', 'Danh sÃ¡ch BHYT sáº¯p háº¿t háº¡n (30 ngÃ y)'], ['health-insurance-expired', 'Danh sÃ¡ch BHYT Ä‘Ã£ háº¿t háº¡n'], ['health-insurance-household', 'Thá»‘ng kÃª BHYT theo há»™'], ['health-insurance-area', 'Thá»‘ng kÃª BHYT theo khu vá»±c'], ['temporary_residence', 'Danh sÃ¡ch táº¡m trÃº'], ['temporary_absence', 'Danh sÃ¡ch táº¡m váº¯ng'], ['children', 'Danh sÃ¡ch tráº» em'], ['elderly', 'Danh sÃ¡ch ngÆ°á»i cao tuá»•i'], ['labor', 'Danh sÃ¡ch lao Ä‘á»™ng'], ['party_member', 'Danh sÃ¡ch Äáº£ng viÃªn'], ['youth_union', 'Danh sÃ¡ch ÄoÃ n viÃªn'], ['poor-households', 'Danh sÃ¡ch há»™ nghÃ¨o'], ['near-poor-households', 'Danh sÃ¡ch há»™ cáº­n nghÃ¨o'], ['age', 'Thá»‘ng kÃª theo Ä‘á»™ tuá»•i'], ['gender', 'Thá»‘ng kÃª theo giá»›i tÃ­nh']
    ];
    types.splice(2, 0, ['public-assets', 'Cong trinh cong cong - Danh sach'], ['public-assets-located', 'Cong trinh cong cong - Da co GPS'], ['public-assets-missing-gps', 'Cong trinh cong cong - Chua co GPS'], ['public-assets-inventory', 'Cong trinh cong cong - Kiem ke tai san']);
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
        if (!byKey.has(type)) byKey.set(type, (group.title || 'Bao cao') + ' - ' + type);
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
    if (!res.ok || !json?.ok) throw new Error(json?.error?.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c dá»¯ liá»‡u');
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
      type: 'Loai bao cao',
      dateFrom: 'Tu ngay',
      dateTo: 'Den ngay',
      area: 'Khu vuc',
      householdCode: 'Ma ho',
      headName: 'Chu ho',
      citizen: 'Nhan khau',
      gender: 'Gioi tinh',
      ageFrom: 'Tuoi tu',
      ageTo: 'Tuoi den',
      occupation: 'Nghe nghiep',
      category: 'Dien ho',
      residencyStatus: 'Cu tru',
      presenceStatus: 'Hien dien',
      gpsStatus: 'GPS',
      digitalProfileStatus: 'Ho so so',
      party_member: 'Dang vien',
      youth_union_member: 'Doan vien'
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
    target.innerHTML = '<div><strong>Bo loc dang ap dung</strong><div class="report-filter-summary-tags">' + items.slice(0, 10).map(([label, value]) => '<span><b>' + esc(label) + ':</b> ' + esc(value) + '</span>').join('') + '</div></div><button class="btn btn-outline-primary btn-sm" type="button" data-platform-action="reports.filters.edit"><i class="fa-solid fa-pen-to-square"></i> Chinh sua bo loc</button>';
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
    setTitle('BÃ¡o cÃ¡o');
    setCount('Äang táº£i dá»¯ liá»‡u...');
    renderBox('#reportPreview', reportLoading());
    scrollToReportResult(false);
    try {
      const report = await smartApi(reportEndpoint('summary', reportQuery()));
      state.report = report;
      setTitle(report.title || 'BÃ¡o cÃ¡o');
      setCount(fmt(report.totalRows || 0) + ' dÃ²ng');
      renderBox('#reportPreview', reportMeta(report) + reportTable(report) + reportSignatures(report));
      setActions(true);
      if (shouldCollapseFilters) collapseReportFilters();
      else expandReportFilters();
      setBiOpen(false);
      scrollToReportResult(true);
      return report;
    } catch (error) {
      setCount('KhÃ´ng sinh Ä‘Æ°á»£c bÃ¡o cÃ¡o');
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
    if (!items.length) return renderBox('#reportSavedTemplates', '<div class="report-template-empty">ChÆ°a cÃ³ máº«u Ä‘Ã£ lÆ°u</div>');
    renderBox('#reportSavedTemplates', items.map(item => {
      const filters = esc(item.filters_json || '{}');
      return '<article class="report-saved-template"><button type="button" data-platform-action="reports.template.open" data-template-open="' + item.id + '" data-filters="' + filters + '" data-type="' + esc(item.type) + '"><strong>' + esc(item.name) + '</strong><span>' + esc(item.type) + (Number(item.is_default) ? ' - Máº·c Ä‘á»‹nh' : '') + '</span></button><div><button type="button" title="Äáº·t máº·c Ä‘á»‹nh" data-platform-action="reports.template.default" data-template-default="' + item.id + '"><i class="fa-solid fa-star"></i></button><button type="button" title="XÃ³a" data-platform-action="reports.template.delete" data-template-delete="' + item.id + '"><i class="fa-solid fa-trash"></i></button></div></article>';
    }).join(''));
  }

  function renderBi(data) {
    const metrics = data.metrics || {};
    $('#reportBiGeneratedAt') && ($('#reportBiGeneratedAt').textContent = 'Cáº­p nháº­t ' + new Date(data.generatedAt || Date.now()).toLocaleString('vi-VN'));
    const kpis = [
      ['Há»™', metrics.total_households], ['NhÃ¢n kháº©u', metrics.total_citizens], ['Nam', metrics.male_count], ['Ná»¯', metrics.female_count], ['Há»™ nghÃ¨o', metrics.poor_households], ['GPS', progressValue(data, 'gps')]
    ];
    const healthInsurance = healthInsuranceSummary(metrics);
    renderBox('#reportBiKpis', kpis.map(([label, value]) => '<div><span>' + esc(label) + '</span><strong>' + esc(value ?? 0) + '</strong></div>').join('') + '<div class="report-bi-health"><span>CÃ³ BHYT</span><strong>' + esc(healthInsurance.covered) + '</strong></div><div class="report-bi-health"><span>ChÆ°a cÃ³ BHYT</span><strong>' + esc(healthInsurance.uninsured) + '</strong></div><div class="report-bi-health"><span>Tá»· lá»‡ bao phá»§</span><strong>' + esc(healthInsurance.coverage) + '</strong></div>');
    const charts = data.charts || {};
    const cards = [
      ['CÆ¡ cáº¥u dÃ¢n sá»‘', charts.population], ['BHYT', charts.healthInsurance], ['Äá»™ tuá»•i', charts.age], ['Nghá» nghiá»‡p', charts.occupation], ['Lao Ä‘á»™ng', charts.labor], ['Há»™ nghÃ¨o/cáº­n nghÃ¨o', charts.poverty], ['Biáº¿n Ä‘á»™ng theo thÃ¡ng', charts.monthlyMovements]
    ];
    renderBox('#reportBiCharts', cards.map(([title, rows]) => '<article class="report-bi-chart"><h4>' + esc(title) + '</h4>' + miniBars(rows || []) + '</article>').join(''));
  }

  function healthInsuranceSummary(metrics) {
    const total = Number(metrics.health_insurance_total ?? metrics.total_citizens ?? 0);
    const insured = Number(metrics.health_insurance_count ?? metrics.health_insurance_covered_count ?? 0);
    const uninsured = Number(metrics.health_insurance_missing_count ?? metrics.health_insurance_uninsured_count ?? Math.max(0, total - insured));
    const coverage = Number(metrics.health_insurance_coverage_percent ?? metrics.health_insurance_percent ?? (total > 0 ? insured * 100 / total : 0));
    return { covered: fmt(insured) + '/' + fmt(total) + ' nhÃ¢n kháº©u', uninsured: fmt(uninsured) + ' nhÃ¢n kháº©u', coverage: coverage.toFixed(2) + '%' };
  }

  function progressValue(data, key) {
    const item = (data.progress || []).find(row => row.key === key);
    return item ? (item.progress?.percent || 0) + '%' : '0%';
  }

  function miniBars(rows) {
    const max = Math.max(1, ...rows.map(row => Number(row.value || 0)));
    return rows.length ? rows.slice(0, 8).map(row => '<div class="report-mini-bar"><span>' + esc(row.label || '') + '</span><i style="--w:' + Math.round(Number(row.value || 0) * 100 / max) + '%"></i><b>' + fmt(row.value || 0) + '</b></div>').join('') : '<div class="report-template-empty">ChÆ°a cÃ³ dá»¯ liá»‡u</div>';
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
      button.innerHTML = '<i class="fa-solid fa-chart-pie"></i> Bao cao';
      target.appendChild(button);
    });
  }

  async function saveTemplate() {
    const name = prompt('TÃªn máº«u bÃ¡o cÃ¡o');
    if (!name) return;
    const filters = currentFilters();
    const template = await smartApi(REPORT_ENDPOINTS.templates, { method: 'POST', body: { name, type: filters.type || 'summary', filters } });
    state.templates.unshift(template);
    await loadTemplates();
    toast('ÄÃ£ lÆ°u máº«u bÃ¡o cÃ¡o');
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
    toast('ÄÃ£ xÃ³a máº«u bÃ¡o cÃ¡o');
  }

  async function defaultTemplate(id) {
    await smartApi(REPORT_ENDPOINTS.templateDefault(id), { method: 'POST', body: {} });
    await loadTemplates();
    toast('ÄÃ£ Ä‘áº·t máº«u máº·c Ä‘á»‹nh');
  }

  async function downloadReport(endpoint, extension, successMessage) {
    const response = await fetch(endpoint + '?' + reportQuery(), { headers: { Authorization: 'Bearer ' + token() }, cache: 'no-store' });
    if (!response.ok) throw new Error('KhÃ´ng xuáº¥t Ä‘Æ°á»£c dá»¯ liá»‡u');
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
      title: report.title || 'Bao cao',
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
    const rows = (report.rows || []).map(row => '<tr>' + row.map(cell => '<td>' + esc(cell) + '</td>').join('') + '</tr>').join('') || '<tr><td colspan="' + Math.max(1, (report.headers || []).length) + '" class="text-center text-muted py-4">Khong co du lieu phu hop voi dieu kien loc.</td></tr>';
    return '<table class="table report-table align-middle mb-0"><thead><tr>' + headers + '</tr></thead><tbody>' + rows + '</tbody></table>';
  }

  function reportMeta(report) {
    const meta = report.meta || {};
    const lines = ['period_label','business_note','report_date'].map(key => meta[key]).filter(Boolean);
    return lines.length ? '<div class="report-print-meta">' + lines.map(line => '<div>' + esc(line) + '</div>').join('') + '</div>' : '';
  }

  function reportHeader(report) {
    return '<div class="report-print-unit">' + esc(reportUnitName(report)) + '</div><div class="report-print-title">' + esc(report.title || 'BÃ¡o cÃ¡o') + '</div>';
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
    return unit || 'ÄÆ¡n vá»‹ hÃ nh chÃ­nh';
  }

  function reportSignatures(report) {
    const meta = report.meta || {};
    if (!meta.prepared_by && !meta.approved_by) return '';
    return '<div class="report-signatures"><div>' + esc(meta.prepared_by || 'NgÆ°á»i láº­p biá»ƒu') + '</div><div>' + esc(meta.approved_by || 'TrÆ°á»Ÿng thÃ´n kÃ½ xÃ¡c nháº­n') + '</div></div>';
  }

  function setTitle(text) { const el = $('#reportTitle'); if (el) el.textContent = text; }
  function setCount(text) { const el = $('#reportCount'); if (el) el.textContent = text; }
  function setActions(show) { $('#reportActions')?.classList.toggle('d-none', !show); }
  function renderBox(selector, html) { const el = $(selector); if (el) el.innerHTML = html; }
  function fileNameFromHeader(header) { return (/filename="?([^";]+)"?/i.exec(header || '') || [])[1] || ''; }
  function toast(message, type = 'success') { if (typeof window.showToast === 'function') window.showToast(message, type); }
  function debounce(fn, wait) { let timer; return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), wait); }; }
})();
