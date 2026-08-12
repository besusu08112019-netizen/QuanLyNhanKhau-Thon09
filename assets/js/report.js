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
    ['summary', 'BÃ¡o cÃ¡o tá»•ng há»£p'],
    ['population', 'NhÃ¢n kháº©u - Danh sÃ¡ch'],
    ['household', 'Há»™ gia Ä‘Ã¬nh - Danh sÃ¡ch'],
    ['temporary_residence', 'Táº¡m trÃº - Danh sÃ¡ch'],
    ['temporary_absence', 'Táº¡m váº¯ng - Danh sÃ¡ch'],
    ['migration', 'Biáº¿n Ä‘á»™ng nhÃ¢n kháº©u'],
    ['public-assets', 'CÃ´ng trÃ¬nh cÃ´ng cá»™ng - Danh sÃ¡ch'],
    ['public-assets-located', 'CÃ´ng trÃ¬nh cÃ´ng cá»™ng - ÄÃ£ cÃ³ GPS'],
    ['public-assets-missing-gps', 'CÃ´ng trÃ¬nh cÃ´ng cá»™ng - ChÆ°a cÃ³ GPS'],
    ['public-assets-inventory', 'CÃ´ng trÃ¬nh cÃ´ng cá»™ng - Kiá»ƒm kÃª tÃ i sáº£n'],
    ['houses', 'NhÃ  á»Ÿ vÃ  cÃ´ng trÃ¬nh - Danh sÃ¡ch'],
    ['houses-degraded', 'NhÃ  á»Ÿ xuá»‘ng cáº¥p'],
    ['houses-temporary', 'NhÃ  táº¡m'],
    ['houses-fire-risk', 'NhÃ  nguy cÆ¡ PCCC'],
    ['houses-missing-gps', 'NhÃ  chÆ°a cÃ³ GPS'],
    ['household-business-production', 'Há»™ sáº£n xuáº¥t'],
    ['household-business-trade', 'Há»™ kinh doanh'],
    ['household-business-sector', 'Há»™ SXKD theo ngÃ nh nghá»'],
    ['household-business-status', 'Há»™ SXKD theo tráº¡ng thÃ¡i'],
    ['agriculture', 'Sáº£n xuáº¥t nÃ´ng nghiá»‡p - Danh sÃ¡ch'],
    ['agriculture-producers', 'Chá»§ thá»ƒ sáº£n xuáº¥t nÃ´ng nghiá»‡p'],
    ['agriculture-area', 'Diá»‡n tÃ­ch sáº£n xuáº¥t nÃ´ng nghiá»‡p'],
    ['agriculture-crop', 'CÃ¢y trá»“ng'],
    ['agriculture-season', 'MÃ¹a vá»¥'],
    ['agriculture-production', 'Sáº£n lÆ°á»£ng nÃ´ng nghiá»‡p'],
    ['agriculture-damage', 'Thiá»‡t háº¡i nÃ´ng nghiá»‡p'],
    ['livestock', 'Váº­t nuÃ´i - Danh sÃ¡ch'],
    ['livestock-by-type', 'Váº­t nuÃ´i theo loáº¡i'],
    ['livestock-vaccinated', 'Váº­t nuÃ´i Ä‘Ã£ tiÃªm phÃ²ng'],
    ['livestock-unvaccinated', 'Váº­t nuÃ´i chÆ°a tiÃªm phÃ²ng'],
    ['livestock-disease', 'Váº­t nuÃ´i cÃ³ dá»‹ch bá»‡nh'],
    ['livestock-pig-farms', 'Danh sÃ¡ch trang tráº¡i lá»£n'],
    ['livestock-pig-sow', 'Danh sÃ¡ch há»™ nuÃ´i lá»£n nÃ¡i'],
    ['livestock-pig-meat', 'Danh sÃ¡ch há»™ nuÃ´i lá»£n thá»‹t'],
    ['livestock-pig-sow-and-meat', 'Há»™ vá»«a nuÃ´i lá»£n nÃ¡i vá»«a nuÃ´i lá»£n thá»‹t'],
    ['rural-clean-water', 'NÆ°á»›c sáº¡ch nÃ´ng thÃ´n - Tá»•ng há»£p NÃ´ng thÃ´n má»›i'],
    ['rural-clean-water-detail', 'NÆ°á»›c sáº¡ch nÃ´ng thÃ´n - Danh sÃ¡ch chi tiáº¿t'],
    ['rural-clean-water-standard', 'NÆ°á»›c sáº¡ch nÃ´ng thÃ´n - Äáº¡t quy chuáº©n'],
    ['rural-clean-water-hygienic', 'NÆ°á»›c sáº¡ch nÃ´ng thÃ´n - Há»£p vá»‡ sinh'],
    ['rural-clean-water-centralized', 'NÆ°á»›c sáº¡ch nÃ´ng thÃ´n - Cáº¥p nÆ°á»›c táº­p trung'],
    ['rural-clean-water-household-scale', 'NÆ°á»›c sáº¡ch nÃ´ng thÃ´n - Quy mÃ´ há»™ gia Ä‘Ã¬nh'],
    ['rural-clean-water-non-compliant', 'NÆ°á»›c sáº¡ch nÃ´ng thÃ´n - KhÃ´ng Ä‘áº¡t quy chuáº©n'],
    ['rural-clean-water-unknown', 'NÆ°á»›c sáº¡ch nÃ´ng thÃ´n - ChÆ°a xÃ¡c Ä‘á»‹nh'],
    ['party-members', 'Äáº£ng viÃªn - Danh sÃ¡ch'],
    ['party-members-branch', 'Äáº£ng viÃªn theo chi bá»™'],
    ['party-members-age', 'Äáº£ng viÃªn theo Ä‘á»™ tuá»•i'],
    ['party-members-gender', 'Äáº£ng viÃªn theo giá»›i tÃ­nh'],
    ['party-members-position', 'Äáº£ng viÃªn theo chá»©c vá»¥'],
    ['party-members-official', 'Äáº£ng viÃªn chÃ­nh thá»©c'],
    ['party-members-probationary', 'Äáº£ng viÃªn dá»± bá»‹'],
    ['party-members-status', 'Äáº£ng viÃªn theo tÃ¬nh tráº¡ng'],
    ['vehicles', 'Xe cá»™ - Danh sÃ¡ch'],
    ['vehicles-by-type', 'Xe cá»™ theo loáº¡i'],
    ['vehicles-missing-plate', 'Xe chÆ°a cÃ³ biá»ƒn sá»‘'],
    ['vehicles-expired-inspection', 'Xe háº¿t háº¡n kiá»ƒm Ä‘á»‹nh'],
    ['vehicles-expired-insurance', 'Xe háº¿t háº¡n báº£o hiá»ƒm'],
    ['contributions-list', 'ÄÃ³ng gÃ³p há»™ - Danh sÃ¡ch'],
    ['contributions-collection', 'ÄÃ³ng gÃ³p há»™ - Thu tiá»n'],
    ['contributions-unpaid-list', 'ÄÃ³ng gÃ³p há»™ - ChÆ°a ná»™p'],
    ['contributions-partial', 'ÄÃ³ng gÃ³p há»™ - Ná»™p má»™t pháº§n'],
    ['contributions-exempt', 'ÄÃ³ng gÃ³p há»™ - Miá»…n giáº£m'],
    ['contributions-summary', 'ÄÃ³ng gÃ³p há»™ - Tá»•ng há»£p'],
    ['contributions-year-summary', 'ÄÃ³ng gÃ³p há»™ - Tá»•ng há»£p nÄƒm'],
    ['contributions-by-contribution', 'ÄÃ³ng gÃ³p há»™ - Theo khoáº£n thu'],
    ['gis', 'GIS - Há»™ gia Ä‘Ã¬nh'],
    ['gis-located', 'GIS - ÄÃ£ Ä‘á»‹nh vá»‹'],
    ['gis-unlocated', 'GIS - ChÆ°a Ä‘á»‹nh vá»‹'],
    ['digital-profile', 'Há»“ sÆ¡ sá»‘'],
    ['profile-complete', 'Há»“ sÆ¡ hoÃ n chá»‰nh'],
    ['profile-missing-photo', 'Há»“ sÆ¡ thiáº¿u áº£nh'],
    ['profile-missing-documents', 'Há»“ sÆ¡ thiáº¿u giáº¥y tá»'],
    ['profile-incomplete', 'Há»“ sÆ¡ chÆ°a hoÃ n thiá»‡n'],
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
    actions.register('reports.export.excel', () => downloadReport(REPORT_ENDPOINTS.exportExcel, 'xls', 'Ã„ÂÃƒÂ£ xuÃ¡ÂºÂ¥t Excel'));
    actions.register('reports.export.pdf', () => downloadReport(REPORT_ENDPOINTS.exportPdf, 'pdf', 'Ã„ÂÃƒÂ£ xuÃ¡ÂºÂ¥t PDF'));
    actions.register('reports.export.word', () => downloadReport(REPORT_ENDPOINTS.exportWord, 'doc', 'Ã„ÂÃƒÂ£ xuÃ¡ÂºÂ¥t Word'));
  }

  function ensureReportTypes() {
    const select = $('#reportTypeSelect');
    if (!select) return;
    const value = select.value || 'summary';
    const types = [
      ['summary', 'BÃƒÂ¡o cÃƒÂ¡o tÃ¡Â»â€¢ng hÃ¡Â»Â£p'], ['population', 'BÃƒÂ¡o cÃƒÂ¡o nhÃƒÂ¢n khÃ¡ÂºÂ©u'], ['household', 'BÃƒÂ¡o cÃƒÂ¡o hÃ¡Â»â„¢ gia Ã„â€˜ÃƒÂ¬nh'], ['migration', 'BÃƒÂ¡o cÃƒÂ¡o biÃ¡ÂºÂ¿n Ã„â€˜Ã¡Â»â„¢ng'],
      ['contributions-list', 'Ã„ÂÃƒÂ³ng gÃƒÂ³p - Danh sÃƒÂ¡ch hÃ¡Â»â„¢'], ['contributions-collection', 'Ã„ÂÃƒÂ³ng gÃƒÂ³p - Danh sÃƒÂ¡ch thu tiÃ¡Â»Ân'], ['contributions-unpaid-list', 'Ã„ÂÃƒÂ³ng gÃƒÂ³p - HÃ¡Â»â„¢ chÃ†Â°a nÃ¡Â»â„¢p'], ['contributions-partial', 'Ã„ÂÃƒÂ³ng gÃƒÂ³p - HÃ¡Â»â„¢ nÃ¡Â»â„¢p mÃ¡Â»â„¢t phÃ¡ÂºÂ§n'], ['contributions-exempt', 'Ã„ÂÃƒÂ³ng gÃƒÂ³p - HÃ¡Â»â„¢ Ã„â€˜Ã†Â°Ã¡Â»Â£c miÃ¡Â»â€¦n'], ['contributions-summary', 'Ã„ÂÃƒÂ³ng gÃƒÂ³p - TÃ¡Â»â€¢ng hÃ¡Â»Â£p cuÃ¡Â»â€˜i Ã„â€˜Ã¡Â»Â£t'], ['contributions-year-summary', 'Ã„ÂÃƒÂ³ng gÃƒÂ³p - TÃ¡Â»â€¢ng hÃ¡Â»Â£p theo nÃ„Æ’m'], ['contributions-by-contribution', 'Ã„ÂÃƒÂ³ng gÃƒÂ³p - Theo khoÃ¡ÂºÂ£n thu'],
      ['gis', 'BÃƒÂ¡o cÃƒÂ¡o GIS'], ['gis-located', 'HÃ¡Â»â„¢ Ã„â€˜ÃƒÂ£ Ã„â€˜Ã¡Â»â€¹nh vÃ¡Â»â€¹ GPS'], ['gis-unlocated', 'HÃ¡Â»â„¢ chÃ†Â°a Ã„â€˜Ã¡Â»â€¹nh vÃ¡Â»â€¹ GPS'],
      ['digital-profile', 'BÃƒÂ¡o cÃƒÂ¡o HÃ¡Â»â€œ sÃ†Â¡ sÃ¡Â»â€˜'], ['profile-complete', 'HÃ¡Â»â€œ sÃ†Â¡ hoÃƒÂ n chÃ¡Â»â€°nh'], ['profile-missing-photo', 'HÃ¡Â»â€œ sÃ†Â¡ thiÃ¡ÂºÂ¿u Ã¡ÂºÂ£nh'], ['profile-missing-documents', 'HÃ¡Â»â€œ sÃ†Â¡ thiÃ¡ÂºÂ¿u giÃ¡ÂºÂ¥y tÃ¡Â»Â'], ['profile-incomplete', 'HÃ¡Â»â€œ sÃ†Â¡ chÃ†Â°a hoÃƒÂ n thiÃ¡Â»â€¡n'],
      ['health_insurance', 'ThÃ¡Â»â€˜ng kÃƒÂª BÃ¡ÂºÂ£o hiÃ¡Â»Æ’m y tÃ¡ÂºÂ¿'], ['health-insurance-missing', 'Danh sÃƒÂ¡ch chÃ†Â°a tham gia BHYT'], ['health-insurance-expiring', 'Danh sÃƒÂ¡ch BHYT sÃ¡ÂºÂ¯p hÃ¡ÂºÂ¿t hÃ¡ÂºÂ¡n (30 ngÃƒÂ y)'], ['health-insurance-expired', 'Danh sÃƒÂ¡ch BHYT Ã„â€˜ÃƒÂ£ hÃ¡ÂºÂ¿t hÃ¡ÂºÂ¡n'], ['health-insurance-household', 'ThÃ¡Â»â€˜ng kÃƒÂª BHYT theo hÃ¡Â»â„¢'], ['health-insurance-area', 'ThÃ¡Â»â€˜ng kÃƒÂª BHYT theo khu vÃ¡Â»Â±c'], ['temporary_residence', 'Danh sÃƒÂ¡ch tÃ¡ÂºÂ¡m trÃƒÂº'], ['temporary_absence', 'Danh sÃƒÂ¡ch tÃ¡ÂºÂ¡m vÃ¡ÂºÂ¯ng'], ['children', 'Danh sÃƒÂ¡ch trÃ¡ÂºÂ» em'], ['elderly', 'Danh sÃƒÂ¡ch ngÃ†Â°Ã¡Â»Âi cao tuÃ¡Â»â€¢i'], ['labor', 'Danh sÃƒÂ¡ch lao Ã„â€˜Ã¡Â»â„¢ng'], ['party_member', 'Danh sÃƒÂ¡ch Ã„ÂÃ¡ÂºÂ£ng viÃƒÂªn'], ['youth_union', 'Danh sÃƒÂ¡ch Ã„ÂoÃƒÂ n viÃƒÂªn'], ['poor-households', 'Danh sÃƒÂ¡ch hÃ¡Â»â„¢ nghÃƒÂ¨o'], ['near-poor-households', 'Danh sÃƒÂ¡ch hÃ¡Â»â„¢ cÃ¡ÂºÂ­n nghÃƒÂ¨o'], ['age', 'ThÃ¡Â»â€˜ng kÃƒÂª theo Ã„â€˜Ã¡Â»â„¢ tuÃ¡Â»â€¢i'], ['gender', 'ThÃ¡Â»â€˜ng kÃƒÂª theo giÃ¡Â»â€ºi tÃƒÂ­nh']
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
    if (!res.ok || !json?.ok) throw new Error(json?.error?.message || 'KhÃƒÂ´ng tÃ¡ÂºÂ£i Ã„â€˜Ã†Â°Ã¡Â»Â£c dÃ¡Â»Â¯ liÃ¡Â»â€¡u');
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
    setTitle('BÃƒÂ¡o cÃƒÂ¡o');
    setCount('Ã„Âang tÃ¡ÂºÂ£i dÃ¡Â»Â¯ liÃ¡Â»â€¡u...');
    renderBox('#reportPreview', reportLoading());
    scrollToReportResult(false);
    try {
      const report = await smartApi(reportEndpoint('summary', reportQuery()));
      state.report = report;
      setTitle(report.title || 'BÃƒÂ¡o cÃƒÂ¡o');
      setCount(fmt(report.totalRows || 0) + ' dÃƒÂ²ng');
      renderBox('#reportPreview', reportMeta(report) + reportTable(report) + reportSignatures(report));
      setActions(true);
      if (shouldCollapseFilters) collapseReportFilters();
      else expandReportFilters();
      setBiOpen(false);
      scrollToReportResult(true);
      return report;
    } catch (error) {
      setCount('KhÃƒÂ´ng sinh Ã„â€˜Ã†Â°Ã¡Â»Â£c bÃƒÂ¡o cÃƒÂ¡o');
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
    if (!items.length) return renderBox('#reportSavedTemplates', '<div class="report-template-empty">ChÃ†Â°a cÃƒÂ³ mÃ¡ÂºÂ«u Ã„â€˜ÃƒÂ£ lÃ†Â°u</div>');
    renderBox('#reportSavedTemplates', items.map(item => {
      const filters = esc(item.filters_json || '{}');
      return '<article class="report-saved-template"><button type="button" data-platform-action="reports.template.open" data-template-open="' + item.id + '" data-filters="' + filters + '" data-type="' + esc(item.type) + '"><strong>' + esc(item.name) + '</strong><span>' + esc(item.type) + (Number(item.is_default) ? ' - MÃ¡ÂºÂ·c Ã„â€˜Ã¡Â»â€¹nh' : '') + '</span></button><div><button type="button" title="Ã„ÂÃ¡ÂºÂ·t mÃ¡ÂºÂ·c Ã„â€˜Ã¡Â»â€¹nh" data-platform-action="reports.template.default" data-template-default="' + item.id + '"><i class="fa-solid fa-star"></i></button><button type="button" title="XÃƒÂ³a" data-platform-action="reports.template.delete" data-template-delete="' + item.id + '"><i class="fa-solid fa-trash"></i></button></div></article>';
    }).join(''));
  }

  function renderBi(data) {
    const metrics = data.metrics || {};
    $('#reportBiGeneratedAt') && ($('#reportBiGeneratedAt').textContent = 'CÃ¡ÂºÂ­p nhÃ¡ÂºÂ­t ' + new Date(data.generatedAt || Date.now()).toLocaleString('vi-VN'));
    const kpis = [
      ['HÃ¡Â»â„¢', metrics.total_households], ['NhÃƒÂ¢n khÃ¡ÂºÂ©u', metrics.total_citizens], ['Nam', metrics.male_count], ['NÃ¡Â»Â¯', metrics.female_count], ['HÃ¡Â»â„¢ nghÃƒÂ¨o', metrics.poor_households], ['GPS', progressValue(data, 'gps')]
    ];
    const healthInsurance = healthInsuranceSummary(metrics);
    renderBox('#reportBiKpis', kpis.map(([label, value]) => '<div><span>' + esc(label) + '</span><strong>' + esc(value ?? 0) + '</strong></div>').join('') + '<div class="report-bi-health"><span>CÃƒÂ³ BHYT</span><strong>' + esc(healthInsurance.covered) + '</strong></div><div class="report-bi-health"><span>ChÃ†Â°a cÃƒÂ³ BHYT</span><strong>' + esc(healthInsurance.uninsured) + '</strong></div><div class="report-bi-health"><span>TÃ¡Â»Â· lÃ¡Â»â€¡ bao phÃ¡Â»Â§</span><strong>' + esc(healthInsurance.coverage) + '</strong></div>');
    const charts = data.charts || {};
    const cards = [
      ['CÃ†Â¡ cÃ¡ÂºÂ¥u dÃƒÂ¢n sÃ¡Â»â€˜', charts.population], ['BHYT', charts.healthInsurance], ['Ã„ÂÃ¡Â»â„¢ tuÃ¡Â»â€¢i', charts.age], ['NghÃ¡Â»Â nghiÃ¡Â»â€¡p', charts.occupation], ['Lao Ã„â€˜Ã¡Â»â„¢ng', charts.labor], ['HÃ¡Â»â„¢ nghÃƒÂ¨o/cÃ¡ÂºÂ­n nghÃƒÂ¨o', charts.poverty], ['BiÃ¡ÂºÂ¿n Ã„â€˜Ã¡Â»â„¢ng theo thÃƒÂ¡ng', charts.monthlyMovements]
    ];
    renderBox('#reportBiCharts', cards.map(([title, rows]) => '<article class="report-bi-chart"><h4>' + esc(title) + '</h4>' + miniBars(rows || []) + '</article>').join(''));
  }

  function healthInsuranceSummary(metrics) {
    const total = Number(metrics.health_insurance_total ?? metrics.total_citizens ?? 0);
    const insured = Number(metrics.health_insurance_count ?? metrics.health_insurance_covered_count ?? 0);
    const uninsured = Number(metrics.health_insurance_missing_count ?? metrics.health_insurance_uninsured_count ?? Math.max(0, total - insured));
    const coverage = Number(metrics.health_insurance_coverage_percent ?? metrics.health_insurance_percent ?? (total > 0 ? insured * 100 / total : 0));
    return { covered: fmt(insured) + '/' + fmt(total) + ' nhÃƒÂ¢n khÃ¡ÂºÂ©u', uninsured: fmt(uninsured) + ' nhÃƒÂ¢n khÃ¡ÂºÂ©u', coverage: coverage.toFixed(2) + '%' };
  }

  function progressValue(data, key) {
    const item = (data.progress || []).find(row => row.key === key);
    return item ? (item.progress?.percent || 0) + '%' : '0%';
  }

  function miniBars(rows) {
    const max = Math.max(1, ...rows.map(row => Number(row.value || 0)));
    return rows.length ? rows.slice(0, 8).map(row => '<div class="report-mini-bar"><span>' + esc(row.label || '') + '</span><i style="--w:' + Math.round(Number(row.value || 0) * 100 / max) + '%"></i><b>' + fmt(row.value || 0) + '</b></div>').join('') : '<div class="report-template-empty">ChÃ†Â°a cÃƒÂ³ dÃ¡Â»Â¯ liÃ¡Â»â€¡u</div>';
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
    const name = prompt('TÃƒÂªn mÃ¡ÂºÂ«u bÃƒÂ¡o cÃƒÂ¡o');
    if (!name) return;
    const filters = currentFilters();
    const template = await smartApi(REPORT_ENDPOINTS.templates, { method: 'POST', body: { name, type: filters.type || 'summary', filters } });
    state.templates.unshift(template);
    await loadTemplates();
    toast('Ã„ÂÃƒÂ£ lÃ†Â°u mÃ¡ÂºÂ«u bÃƒÂ¡o cÃƒÂ¡o');
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
    toast('Ã„ÂÃƒÂ£ xÃƒÂ³a mÃ¡ÂºÂ«u bÃƒÂ¡o cÃƒÂ¡o');
  }

  async function defaultTemplate(id) {
    await smartApi(REPORT_ENDPOINTS.templateDefault(id), { method: 'POST', body: {} });
    await loadTemplates();
    toast('Ã„ÂÃƒÂ£ Ã„â€˜Ã¡ÂºÂ·t mÃ¡ÂºÂ«u mÃ¡ÂºÂ·c Ã„â€˜Ã¡Â»â€¹nh');
  }

  async function downloadReport(endpoint, extension, successMessage) {
    const response = await fetch(endpoint + '?' + reportQuery(), { headers: { Authorization: 'Bearer ' + token() }, cache: 'no-store' });
    if (!response.ok) throw new Error('KhÃƒÂ´ng xuÃ¡ÂºÂ¥t Ã„â€˜Ã†Â°Ã¡Â»Â£c dÃ¡Â»Â¯ liÃ¡Â»â€¡u');
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
    return '<div class="report-print-unit">' + esc(reportUnitName(report)) + '</div><div class="report-print-title">' + esc(report.title || 'BÃƒÂ¡o cÃƒÂ¡o') + '</div>';
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
    return unit || 'Ã„ÂÃ†Â¡n vÃ¡Â»â€¹ hÃƒÂ nh chÃƒÂ­nh';
  }

  function reportSignatures(report) {
    const meta = report.meta || {};
    if (!meta.prepared_by && !meta.approved_by) return '';
    return '<div class="report-signatures"><div>' + esc(meta.prepared_by || 'NgÃ†Â°Ã¡Â»Âi lÃ¡ÂºÂ­p biÃ¡Â»Æ’u') + '</div><div>' + esc(meta.approved_by || 'TrÃ†Â°Ã¡Â»Å¸ng thÃƒÂ´n kÃƒÂ½ xÃƒÂ¡c nhÃ¡ÂºÂ­n') + '</div></div>';
  }

  function setTitle(text) { const el = $('#reportTitle'); if (el) el.textContent = text; }
  function setCount(text) { const el = $('#reportCount'); if (el) el.textContent = text; }
  function setActions(show) { $('#reportActions')?.classList.toggle('d-none', !show); }
  function renderBox(selector, html) { const el = $(selector); if (el) el.innerHTML = html; }
  function fileNameFromHeader(header) { return (/filename="?([^";]+)"?/i.exec(header || '') || [])[1] || ''; }
  function toast(message, type = 'success') { if (typeof window.showToast === 'function') window.showToast(message, type); }
  function debounce(fn, wait) { let timer; return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), wait); }; }
})();
