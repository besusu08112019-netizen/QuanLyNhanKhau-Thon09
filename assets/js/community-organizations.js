(function () {
  'use strict';
  const API = '/api/organizations';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const state = { page: 1, pageSize: 20, search: '', organization_code: '', status: '', gender: '', area_code: '', position_id: '', age_from: '', age_to: '', joined_year: '', catalogs: null, suggestions: [], selectedCitizen: null, editingId: 0, saving: false };
  let registered = false;
  let profilePatched = false;

  registerPlatform();
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('tenant:screen-change', e => { if (e.detail?.screen === 'communityOrganizations') load(); });

  function init() {
    ensureScreen();
    registerPlatform();
    registerActions();
    bindEvents();
    bindCreateButtons();
    bindDocumentCreateCapture();
    bindDocumentSaveCapture();
    bindDocumentActionCapture();
    if ($('#communityOrganizationsScreen')?.classList.contains('active')) load();
  }

  function ensureScreen() {
    if ($('#communityOrganizationsScreen')) return;
    const host = $('#mainContent') || $('.main-content') || document.body;
    host.insertAdjacentHTML('beforeend', screenHtml());
    injectStyle();
  }

  function screenHtml() {
    return '<section id="communityOrganizationsScreen" class="screen community-organizations-screen" data-screen="communityOrganizations">' +
      '<div class="content-header d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3"><div><h1 class="h4 mb-1">ÄoÃ n thá»ƒ - Chi há»™i</h1><div class="text-muted small">Quáº£n lÃ½ há»™i viÃªn liÃªn káº¿t trá»±c tiáº¿p vá»›i há»“ sÆ¡ nhÃ¢n kháº©u</div></div><div class="d-flex gap-2"><button class="btn btn-outline-secondary" data-platform-action="communityOrganizations.print"><i class="fa-solid fa-print me-1"></i> In</button><button class="btn btn-outline-success" data-platform-action="communityOrganizations.export"><i class="fa-solid fa-file-excel me-1"></i> Excel</button><button class="btn btn-primary" data-platform-action="communityOrganizations.openCreate"><i class="fa-solid fa-plus me-1"></i> ThÃªm thÃ nh viÃªn</button></div></div>' +
      '<div id="communityOrgDashboard" class="community-org-dashboard mb-3"></div><div id="communityOrgWarnings" class="mb-3"></div><div class="content-card mb-3"><ul id="communityOrgTabs" class="nav nav-tabs" role="tablist"></ul></div>' +
      '<div class="card content-card mb-3"><div class="card-body"><div class="row g-2 align-items-end"><div class="col-lg-3 col-md-6"><label class="form-label">TÃ¬m kiáº¿m</label><input id="communityOrgSearch" class="form-control" placeholder="Há» tÃªn, mÃ£ nhÃ¢n kháº©u, mÃ£ há»™, sá»‘ tháº»"></div><div class="col-lg-2 col-md-6"><label class="form-label">Tá»• chá»©c</label><select id="communityOrgFilter" class="form-select"></select></div><div class="col-lg-2 col-md-6"><label class="form-label">Tráº¡ng thÃ¡i</label><select id="communityOrgStatusFilter" class="form-select"></select></div><div class="col-lg-2 col-md-6"><label class="form-label">Khu vá»±c</label><select id="communityOrgAreaFilter" class="form-select"></select></div><div class="col-lg-1 col-6"><label class="form-label">Tá»« tuá»•i</label><input id="communityOrgAgeFrom" class="form-control" type="number" min="0"></div><div class="col-lg-1 col-6"><label class="form-label">Äáº¿n tuá»•i</label><input id="communityOrgAgeTo" class="form-control" type="number" min="0"></div><div class="col-lg-1 col-12"><button class="btn btn-outline-secondary w-100" data-platform-action="communityOrganizations.reset"><i class="fa-solid fa-rotate-left"></i></button></div></div></div></div>' +
      '<div class="card content-card"><div class="card-header d-flex justify-content-between align-items-center"><div><strong>Danh sÃ¡ch thÃ nh viÃªn</strong><div class="small text-muted" id="communityOrgTotal">Tá»•ng sá»‘: 0</div></div><select id="communityOrgPageSize" class="form-select form-select-sm" style="width:auto"><option>20</option><option>50</option><option>100</option></select></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>STT</th><th>Há» vÃ  tÃªn</th><th>NgÃ y sinh</th><th>Tuá»•i</th><th>Giá»›i tÃ­nh</th><th>MÃ£ há»™</th><th>Khu vá»±c</th><th>Tá»• chá»©c</th><th>Chá»©c vá»¥</th><th>NgÃ y tham gia</th><th>Tráº¡ng thÃ¡i</th><th class="text-end">Thao tÃ¡c</th></tr></thead><tbody id="communityOrgRows"></tbody></table></div><div class="card-footer d-flex justify-content-end gap-2" id="communityOrgPager"></div></div>' +
      modalHtml() + '</section>';
  }

  function modalHtml() {
    return '<div class="modal fade" id="communityOrgModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form id="communityOrgForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">ThÃ´ng tin Ä‘oÃ n thá»ƒ - chi há»™i</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="citizen_id"><input type="hidden" name="id"><div class="row g-3"><div class="col-md-6 position-relative"><label class="form-label">TÃ¬m nhÃ¢n kháº©u *</label><input id="communityOrgCitizenSearch" class="form-control" autocomplete="off" placeholder="Nháº­p há» tÃªn, mÃ£ nhÃ¢n kháº©u, mÃ£ há»™, CCCD"><div id="communityOrgCitizenSuggestions" class="list-group community-org-suggestions"></div><div id="communityOrgSelectedCitizen" class="small text-muted mt-1"></div></div><div class="col-md-6"><label class="form-label">Tá»• chá»©c *</label><select name="organization_code" id="communityOrgOrgSelect" class="form-select" required></select></div><div class="col-md-6"><label class="form-label">Chá»©c vá»¥</label><select name="position_id" id="communityOrgPositionSelect" class="form-select"></select></div><div class="col-md-6"><label class="form-label">Tá»•/nhÃ³m trá»±c thuá»™c</label><input name="subgroup_name" class="form-control"></div><div class="col-md-4"><label class="form-label">Sá»‘ tháº»</label><input name="member_number" class="form-control"></div><div class="col-md-4"><label class="form-label">NgÃ y tham gia</label><input name="joined_date" class="form-control" inputmode="numeric" placeholder="dd/mm/yyyy"></div><div class="col-md-4"><label class="form-label">NgÃ y káº¿t thÃºc</label><input name="ended_date" class="form-control" inputmode="numeric" placeholder="dd/mm/yyyy"></div><div class="col-md-6"><label class="form-label">Tráº¡ng thÃ¡i</label><select name="status" id="communityOrgStatusSelect" class="form-select"></select></div><div class="col-12"><label class="form-label">Ghi chÃº</label><textarea name="note" class="form-control" rows="3"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Há»§y</button><button type="button" class="btn btn-primary" data-platform-action="communityOrganizations.save"><i class="fa-solid fa-floppy-disk me-1"></i> LÆ°u</button></div></form></div></div>' +
      '<div class="modal fade" id="communityOrgDetailModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Chi tiáº¿t há»™i viÃªn</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div id="communityOrgDetailBody" class="modal-body"></div></div></div></div>';
  }

  function injectStyle() {
    if ($('#communityOrgStyle')) return;
    document.head.insertAdjacentHTML('beforeend', '<style id="communityOrgStyle">.community-org-dashboard{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.community-org-card{border:1px solid #d8e2ef;border-radius:8px;padding:14px;background:#fff;display:flex;gap:12px;align-items:center;min-height:92px}.community-org-card i{font-size:22px;color:#087f5b}.community-org-card .label{font-size:13px;color:#475569;line-height:1.35}.community-org-card .value{font-size:28px;font-weight:800;line-height:1.1;color:#0f172a}.community-org-suggestions{position:absolute;z-index:1070;left:12px;right:12px;max-height:260px;overflow:auto}.community-org-status{border-radius:999px;padding:3px 8px;font-size:12px;font-weight:700}.community-org-status.ACTIVE{background:#dcfce7;color:#166534}.community-org-status.PAUSED{background:#fef9c3;color:#854d0e}.community-org-status.TRANSFERRED{background:#dbeafe;color:#1d4ed8}.community-org-status.ENDED,.community-org-status.DECEASED{background:#fee2e2;color:#991b1b}@media(max-width:1199px){.community-org-dashboard{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:767px){.community-org-dashboard{grid-template-columns:repeat(2,minmax(0,1fr))}.community-org-card{min-height:86px;padding:12px}.community-org-card .value{font-size:24px}}@media(max-width:420px){.community-org-dashboard{grid-template-columns:1fr}}</style>');
  }

  function registerPlatform() {
    const p = window.TenantAppPlatform;
    if (!p || registered) return;
    registered = true;
    if (!p.modules?.get?.('communityOrganizations')) p.modules?.register?.({ moduleKey: 'communityOrganizations', screenId: 'communityOrganizations', path: '/community-organizations', label: 'ÄoÃ n thá»ƒ - Chi há»™i', mobileLabel: 'ÄoÃ n thá»ƒ', icon: 'fa-people-group', permissionScope: 'citizen', loaderName: 'loadCommunityOrganizations' });
    if (!p.routes?.match?.('/community-organizations')) p.routes?.register?.({ path: '/community-organizations', moduleKey: 'communityOrganizations', screenId: 'communityOrganizations', action: 'list' });
    const population = p.menus?.get?.('population');
    if (population && !String(population.items || '').includes('communityOrganizations')) {
      const items = population.items || [];
      const idx = items.indexOf('partyMembers');
      const next = items.slice();
      next.splice(idx >= 0 ? idx + 1 : next.length, 0, 'communityOrganizations');
      p.menus.upsert?.(Object.assign({}, population, { items: next }));
    }
    p.menuRenderer?.renderAll?.();
  }

  function registerActions() {
    const actions = window.TenantAppPlatform?.actions;
    if (!actions?.register) return;
    actions.register('communityOrganizations.openCreate', () => openForm());
    actions.register('communityOrganizations.save', () => save({ preventDefault() {}, currentTarget: $('#communityOrgForm') }));
    actions.register('communityOrganizations.edit', ctx => openForm(Number(ctx.dataset.id || 0)));
    actions.register('communityOrganizations.detail', ctx => openDetail(Number(ctx.dataset.id || 0)));
    actions.register('communityOrganizations.end', ctx => endMembership(Number(ctx.dataset.id || 0)));
    actions.register('communityOrganizations.delete', ctx => remove(Number(ctx.dataset.id || 0)));
    actions.register('communityOrganizations.page', ctx => { state.page = Number(ctx.dataset.page || 1); load(); });
    actions.register('communityOrganizations.reset', resetFilters);
    actions.register('communityOrganizations.selectCitizen', ctx => selectCitizen(state.suggestions.find(x => String(x.id) === ctx.dataset.id)));
    actions.register('communityOrganizations.export', exportExcel);
    actions.register('communityOrganizations.print', printReport);
    actions.register('communityOrganizations.filterOrg', ctx => selectOrganizationTab(ctx.dataset.org || ''));
    actions.register('communityOrganizations.tab', ctx => selectOrganizationTab(ctx.dataset.org || ''));
    actions.bind?.(document);
  }

  function bindEvents() {
    $('#communityOrgForm')?.addEventListener('submit', save);
    document.querySelectorAll('[data-platform-action="communityOrganizations.save"]').forEach(button => { button.onclick = event => { event.preventDefault(); event.stopImmediatePropagation?.(); save({ preventDefault() {}, currentTarget: $('#communityOrgForm') }); }; });
    $('#communityOrgSearch')?.addEventListener('input', debounce(e => { state.search = e.target.value.trim(); state.page = 1; load(); }, 300));
    ['communityOrgFilter','communityOrgStatusFilter','communityOrgAreaFilter'].forEach(id => $('#' + id)?.addEventListener('change', () => { collectFilters(); state.page = 1; load(); }));
    ['communityOrgAgeFrom','communityOrgAgeTo'].forEach(id => $('#' + id)?.addEventListener('input', debounce(() => { collectFilters(); state.page = 1; load(); }, 300)));
    $('#communityOrgPageSize')?.addEventListener('change', e => { state.pageSize = Number(e.target.value || 20); state.page = 1; load(); });
    $('#communityOrgOrgSelect')?.addEventListener('change', e => fillPositions(e.target.value));
    $('#communityOrgCitizenSearch')?.addEventListener('input', debounce(searchCitizens, 250));
    document.addEventListener('pointerdown', e => { if (!e.target.closest('#communityOrgCitizenSuggestions') && e.target.id !== 'communityOrgCitizenSearch') hideSuggestions(); });
    bindCreateButtons();
    bindDocumentCreateCapture();
    const screen = $('#communityOrganizationsScreen');
    if (screen && !screen.dataset.directBound) {
      screen.dataset.directBound = '1';
      screen.addEventListener('click', event => {
        const tab = event.target.closest('[data-community-org-tab]');
        if (tab) { event.preventDefault(); event.stopImmediatePropagation?.(); selectOrganizationTab(tab.dataset.org || ''); }
      });
    }
  }


  function bindDocumentCreateCapture() {
    if (document.documentElement.dataset.communityCreateCaptureBound) return;
    document.documentElement.dataset.communityCreateCaptureBound = '1';
    document.addEventListener('click', event => {
      const button = event.target.closest?.('[data-platform-action="communityOrganizations.openCreate"]');
      if (!button || !button.closest('#communityOrganizationsScreen')) return;
      event.preventDefault();
      event.stopImmediatePropagation?.();
      openForm().then(() => {
        const modal = $('#communityOrgModal');
        if (modal && !modal.classList.contains('show')) bootstrap.Modal.getOrCreateInstance(modal).show();
      }).catch(error => toast(error.message || 'KhÃ´ng má»Ÿ Ä‘Æ°á»£c form thÃªm thÃ nh viÃªn', 'danger'));
    }, true);
  }

  function bindDocumentSaveCapture() {
    if (document.documentElement.dataset.communitySaveCaptureBound) return;
    document.documentElement.dataset.communitySaveCaptureBound = '1';
    document.addEventListener('click', event => {
      const button = event.target.closest?.('[data-platform-action="communityOrganizations.save"]');
      if (!button) return;
      const form = button.closest('form');
      if (!form) return;
      event.preventDefault();
      event.stopImmediatePropagation?.();
      save({ preventDefault() {}, currentTarget: form });
    }, true);
    document.addEventListener('submit', event => {
      if (event.target?.id !== 'communityOrgForm') return;
      event.preventDefault();
      event.stopImmediatePropagation?.();
      save(event);
    }, true);
  }

  function bindDocumentActionCapture() {
    if (document.documentElement.dataset.communityActionCaptureBound) return;
    document.documentElement.dataset.communityActionCaptureBound = '1';
    document.addEventListener('click', event => {
      const target = event.target.closest?.('[data-platform-action^="communityOrganizations."]');
      if (!target) return;
      const scope = target.closest('#communityOrganizationsScreen, #communityOrgModal, #communityOrgDetailModal');
      if (!scope) return;
      const action = target.dataset.platformAction || '';
      if (['communityOrganizations.openCreate','communityOrganizations.save','communityOrganizations.selectCitizen','communityOrganizations.tab'].includes(action)) return;
      event.preventDefault();
      event.stopImmediatePropagation?.();
      const id = Number(target.dataset.id || 0);
      if (action === 'communityOrganizations.detail') return openDetail(id);
      if (action === 'communityOrganizations.edit') return openForm(id);
      if (action === 'communityOrganizations.end') return endMembership(id);
      if (action === 'communityOrganizations.delete') return remove(id);
      if (action === 'communityOrganizations.page') { state.page = Number(target.dataset.page || 1); return load(); }
      if (action === 'communityOrganizations.reset') return resetFilters();
      if (action === 'communityOrganizations.filterOrg') return selectOrganizationTab(target.dataset.org || '');
      if (action === 'communityOrganizations.export') return exportExcel();
      if (action === 'communityOrganizations.print') return printReport();
    }, true);
  }

  function bindCreateButtons() {
    document.querySelectorAll('[data-platform-action="communityOrganizations.openCreate"]').forEach(button => {
      if (button.dataset.communityCreateBound) return;
      button.dataset.communityCreateBound = '1';
      const handler = event => {
        event.preventDefault();
        event.stopImmediatePropagation?.();
        openForm().then(() => {
          const modal = $('#communityOrgModal');
          if (modal && !modal.classList.contains('show')) bootstrap.Modal.getOrCreateInstance(modal).show();
        }).catch(error => toast(error.message || 'KhÃ´ng má»Ÿ Ä‘Æ°á»£c form thÃªm thÃ nh viÃªn', 'danger'));
      };
      button.onclick = handler;
      button.addEventListener('click', handler, true);
    });
  }

  function patchCitizenProfile() {
    if (profilePatched || typeof window.showPerson !== 'function') return;
    const original = window.showPerson;
    profilePatched = true;
    window.showPerson = async function communityOrganizationShowPerson(id) {
      const result = await original.apply(this, arguments);
      appendCitizenOrganizations(Number(id || 0));
      return result;
    };
  }

  async function appendCitizenOrganizations(citizenId) {
    if (!citizenId || !can('read')) return;
    const body = $('#detailBody');
    if (!body || body.querySelector('[data-community-org-profile]')) return;
    try {
      const data = await request(API + '/citizen/' + citizenId);
      const items = data.items || [];
      const html = '<section class="person-info-section mt-3" data-community-org-profile><div class="person-info-section-title"><i class="fa-solid fa-people-group"></i><h4>ÄoÃ n thá»ƒ - Chi há»™i</h4></div>' + (items.length ? '<div class="list-group list-group-flush border rounded">' + items.map(item => '<div class="list-group-item"><div class="fw-semibold">' + esc(item.organization_name || '') + ' - ' + esc(item.position_name || 'ThÃ nh viÃªn') + '</div><div class="small text-muted">Tham gia tá»« ' + (date(item.joined_date) || 'chÆ°a cáº­p nháº­t') + ' - ' + esc(item.status_label || '') + '</div>' + (item.movement_warning ? '<div class="small text-warning">' + esc(item.movement_warning) + '</div>' : '') + '</div>').join('') + '</div>' : '<div class="text-muted small">ChÆ°a cÃ³ thÃ´ng tin tham gia Ä‘oÃ n thá»ƒ/chi há»™i.</div>') + '</section>';
      body.insertAdjacentHTML('beforeend', html);
    } catch (error) {
      console.warn('[community-organizations] citizen profile link skipped', error);
    }
  }
  async function load() {
    if (!can('read')) return;
    activateScreen();
    await ensureCatalogs();
    bindCreateButtons();
    bindDocumentCreateCapture();
    renderTabs();
    collectFilters();
    const data = await request(API + '?' + params().toString());
    renderRows(data);
    renderPager(data);
    renderDashboard();
  }

  function activateScreen() {
    const p = window.TenantAppPlatform;
    if (p?.screens?.sync) {
      p.screens.sync({ screenId: 'communityOrganizations' });
      return;
    }
    const screen = $('#communityOrganizationsScreen');
    if (!screen) return;    document.querySelectorAll('.screen.active').forEach(node => {
      if (node !== screen) node.classList.remove('active');
    });
    screen.classList.add('active');
  }

  async function ensureCatalogs() {
    if (state.catalogs) return state.catalogs;
    state.catalogs = await request(API + '/catalogs');
    fill('#communityOrgFilter', state.catalogs.organizations, 'Táº¥t cáº£');
    fill('#communityOrgOrgSelect', state.catalogs.organizations, 'Chá»n tá»• chá»©c');
    fill('#communityOrgStatusFilter', state.catalogs.statuses, 'Táº¥t cáº£ tráº¡ng thÃ¡i');
    fill('#communityOrgStatusSelect', state.catalogs.statuses, 'Chá»n tráº¡ng thÃ¡i');
    fill('#communityOrgAreaFilter', state.catalogs.areas || [], 'Táº¥t cáº£ khu vá»±c');
    fillPositions('');
    return state.catalogs;
  }

  function collectFilters() {
    state.search = $('#communityOrgSearch')?.value.trim() || '';
    state.organization_code = $('#communityOrgFilter')?.value || '';
    state.status = $('#communityOrgStatusFilter')?.value || '';
    state.area_code = $('#communityOrgAreaFilter')?.value || '';
    state.age_from = $('#communityOrgAgeFrom')?.value || '';
    state.age_to = $('#communityOrgAgeTo')?.value || '';
  }

  function params() {
    const p = new URLSearchParams({ page: state.page, pageSize: state.pageSize });
    ['search','organization_code','status','gender','area_code','position_id','age_from','age_to','joined_year'].forEach(k => { if (state[k]) p.set(k, state[k]); });
    return p;
  }

  async function renderDashboard() {
    const data = await request(API + '/dashboard?' + params().toString());
    const host = $('#communityOrgDashboard');
    const cards = [{ label: 'Tá»•ng thÃ nh viÃªn Ä‘ang tham gia', value: data.metrics?.total_active_members || 0, icon: 'fa-users' }].concat((data.organizations || []).map(o => ({ label: o.name, value: o.active_count || 0, icon: iconFor(o.code), code: o.code })));
    host.innerHTML = cards.map(c => '<button type="button" class="community-org-card text-start" ' + (c.code ? 'data-platform-action="communityOrganizations.filterOrg" data-org="' + esc(c.code) + '"' : '') + '><i class="fa-solid ' + c.icon + '"></i><span><span class="label d-block">' + esc(c.label) + '</span><span class="value d-block">' + fmt(c.value) + '</span></span></button>').join('');
    const warnings = data.warnings || [];
    $('#communityOrgWarnings').innerHTML = warnings.length ? warnings.map(w => '<div class="alert alert-warning mb-2"><i class="fa-solid fa-triangle-exclamation me-2"></i>' + esc(w.message || '') + '</div>').join('') : '';
  }

  function renderTabs() {
    const host = $('#communityOrgTabs');
    if (!host || !state.catalogs) return;
    const orgs = state.catalogs.organizations || [];
    const items = [{ code: '', name: 'Táº¥t cáº£' }].concat(orgs.map(o => ({ code: o.code || o.value || '', name: o.name || o.label || '' })));
    host.innerHTML = items.map(item => '<li class="nav-item" role="presentation"><button type="button" class="nav-link ' + (String(state.organization_code || '') === String(item.code || '') ? 'active' : '') + '" data-community-org-tab data-org="' + esc(item.code || '') + '">' + esc(item.name || 'Táº¥t cáº£') + '</button></li>').join('');
    host.querySelectorAll('[data-community-org-tab]').forEach(button => { const handler = event => { event.preventDefault(); event.stopImmediatePropagation?.(); selectOrganizationTab(button.dataset.org || ''); }; button.onpointerdown = handler; button.onmousedown = handler; button.onclick = handler; });
  }

  function selectOrganizationTab(code) {
    state.organization_code = code || '';
    const filter = $('#communityOrgFilter');
    if (filter) filter.value = state.organization_code;
    state.page = 1;
    renderTabs();
    load();
  }

  function renderRows(data) {
    const rows = data.items || [];
    $('#communityOrgTotal').textContent = 'Tá»•ng sá»‘: ' + fmt(data.total || 0) + ' thÃ nh viÃªn';
    $('#communityOrgRows').innerHTML = rows.length ? rows.map((r, i) => '<tr><td data-label="STT">' + fmt(((data.page || 1) - 1) * (data.pageSize || 20) + i + 1) + '</td><td data-label="Há» tÃªn"><button class="btn btn-link p-0 fw-bold" data-platform-action="communityOrganizations.detail" data-id="' + r.id + '">' + esc(r.full_name) + '</button><div class="small text-muted">' + esc(r.citizen_code || '') + '</div>' + (r.movement_warning ? '<div class="small text-warning">' + esc(r.movement_warning) + '</div>' : '') + '</td><td data-label="NgÃ y sinh">' + date(r.date_of_birth) + '</td><td data-label="Tuá»•i">' + esc(r.age || '') + '</td><td data-label="Giá»›i tÃ­nh">' + esc(r.gender || '') + '</td><td data-label="MÃ£ há»™">' + esc(r.household_code || '') + '</td><td data-label="Khu vá»±c">' + esc(r.area_code || '') + '</td><td data-label="Tá»• chá»©c">' + esc(r.organization_name || '') + '</td><td data-label="Chá»©c vá»¥">' + esc(r.position_name || '') + '</td><td data-label="NgÃ y tham gia">' + date(r.joined_date) + '</td><td data-label="Tráº¡ng thÃ¡i"><span class="community-org-status ' + esc(r.status || '') + '">' + esc(r.status_label || r.status || '') + '</span></td><td data-label="Thao tÃ¡c" class="text-end"><div class="btn-group btn-group-sm"><button class="btn btn-outline-secondary" data-platform-action="communityOrganizations.detail" data-id="' + r.id + '" title="Xem"><i class="fa-solid fa-eye"></i></button>' + (can('update') ? '<button class="btn btn-outline-primary" data-platform-action="communityOrganizations.edit" data-id="' + r.id + '" title="Sá»­a"><i class="fa-solid fa-pen"></i></button><button class="btn btn-outline-warning" data-platform-action="communityOrganizations.end" data-id="' + r.id + '" title="ThÃ´i tham gia"><i class="fa-solid fa-user-minus"></i></button>' : '') + (can('delete') ? '<button class="btn btn-outline-danger" data-platform-action="communityOrganizations.delete" data-id="' + r.id + '" title="XÃ³a"><i class="fa-solid fa-trash"></i></button>' : '') + '</div></td></tr>').join('') : '<tr><td colspan="12" class="text-center text-muted py-4">ChÆ°a cÃ³ thÃ nh viÃªn Ä‘oÃ n thá»ƒ - chi há»™i</td></tr>';
    if (typeof window.TenantAppSyncResponsiveTableLabels === 'function') window.TenantAppSyncResponsiveTableLabels($('#communityOrganizationsScreen'));
  }

  function renderPager(data) {
    const totalPages = Number(data.totalPages || 1), page = Number(data.page || 1);
    $('#communityOrgPager').innerHTML = '<button class="btn btn-sm btn-outline-secondary" ' + (page <= 1 ? 'disabled' : '') + ' data-platform-action="communityOrganizations.page" data-page="' + (page - 1) + '">TrÆ°á»›c</button><span class="align-self-center small">Trang ' + fmt(page) + '/' + fmt(totalPages) + '</span><button class="btn btn-sm btn-outline-secondary" ' + (page >= totalPages ? 'disabled' : '') + ' data-platform-action="communityOrganizations.page" data-page="' + (page + 1) + '">Sau</button>';
  }

  async function openForm(id = 0) {
    if (!can(id ? 'update' : 'create')) return toast('TÃ i khoáº£n hiá»‡n táº¡i khÃ´ng cÃ³ quyá»n thá»±c hiá»‡n thao tÃ¡c nÃ y', 'warning');
    await ensureCatalogs();
    state.editingId = id;
    state.selectedCitizen = null;
    const form = $('#communityOrgForm');
    form.reset(); form.elements.id.value = id || ''; form.elements.citizen_id.value = '';
    $('#communityOrgSelectedCitizen').textContent = '';
    $('#communityOrgCitizenSearch').disabled = !!id;
    if (id) {
      const row = await request(API + '/' + id);
      fillForm(form, row);
      state.selectedCitizen = { id: row.citizen_id, full_name: row.full_name, citizen_code: row.citizen_code };
      form.elements.citizen_id.value = row.citizen_id;
      $('#communityOrgCitizenSearch').value = [row.full_name, row.citizen_code].filter(Boolean).join(' - ');
      $('#communityOrgSelectedCitizen').textContent = [row.full_name, row.citizen_code, row.household_code].filter(Boolean).join(' - ');
      fillPositions(row.organization_code, row.position_id);
    } else {
      fillPositions($('#communityOrgOrgSelect').value);
    }
    bootstrap.Modal.getOrCreateInstance($('#communityOrgModal')).show();
  }

  async function save(event) {
    event.preventDefault();
    if (state.saving) return;
    const form = event.currentTarget || $('#communityOrgForm');
    if (!form?.elements?.citizen_id?.value) return toast('Vui lÃ²ng chá»n nhÃ¢n kháº©u tá»« danh sÃ¡ch.', 'warning');
    if (!form.checkValidity()) { form.reportValidity?.(); return; }
    const submitButton = form.querySelector('[data-platform-action="communityOrganizations.save"]');
    state.saving = true;
    if (submitButton) submitButton.disabled = true;
    try {
      const payload = Object.fromEntries(new FormData(form).entries());
      const id = Number(payload.id || 0);
      const row = await request(API + (id ? '/' + id : ''), { method: id ? 'PUT' : 'POST', body: JSON.stringify(payload) });
      bootstrap.Modal.getOrCreateInstance($('#communityOrgModal')).hide();
      toast('ÄÃ£ lÆ°u thÃ´ng tin Ä‘oÃ n thá»ƒ - chi há»™i', 'success');
      load();
      return row;
    } catch (error) {
      toast(error.message || 'KhÃ´ng lÆ°u Ä‘Æ°á»£c thÃ´ng tin Ä‘oÃ n thá»ƒ - chi há»™i', 'danger');
    } finally {
      state.saving = false;
      if (submitButton) submitButton.disabled = false;
    }
  }

  async function searchCitizens() {
    const q = $('#communityOrgCitizenSearch').value.trim();
    const org = $('#communityOrgOrgSelect').value || '';
    if (q.length < 2) return hideSuggestions();
    state.suggestions = (await request(API + '/citizen-search?q=' + encodeURIComponent(q) + '&organization_code=' + encodeURIComponent(org))).items || [];
    const box = $('#communityOrgCitizenSuggestions');
    box.innerHTML = state.suggestions.length ? state.suggestions.map(x => '<button type="button" class="list-group-item list-group-item-action" data-community-org-citizen data-id="' + x.id + '" ' + (x.has_current_membership ? 'disabled' : '') + '><strong>' + esc(x.full_name) + '</strong><div class="small text-muted">' + esc([x.citizen_code, x.household_code, x.address].filter(Boolean).join(' - ')) + '</div>' + (x.has_current_membership ? '<div class="small text-danger">NhÃ¢n kháº©u nÃ y Ä‘ang tham gia tá»• chá»©c Ä‘Ã£ chá»n</div>' : '') + '</button>').join('') : '<div class="list-group-item text-muted">KhÃ´ng tÃ¬m tháº¥y nhÃ¢n kháº©u phÃ¹ há»£p</div>';
    box.querySelectorAll('[data-community-org-citizen]').forEach(button => { const handler = event => { event.preventDefault(); event.stopImmediatePropagation?.(); selectCitizen(state.suggestions.find(x => String(x.id) === String(button.dataset.id))); }; button.onpointerdown = handler; button.onmousedown = handler; button.onclick = handler; });
  }

  function selectCitizen(item) {
    if (!item || item.has_current_membership) return;
    state.selectedCitizen = item;
    $('#communityOrgForm').elements.citizen_id.value = item.id;
    $('#communityOrgCitizenSearch').value = [item.full_name, item.citizen_code].filter(Boolean).join(' - ');
    $('#communityOrgSelectedCitizen').textContent = [item.full_name, item.citizen_code, item.household_code, item.address].filter(Boolean).join(' - ');
    hideSuggestions();
  }

  async function openDetail(id) {
    const row = await request(API + '/' + id);
    const history = (await request(API + '/' + id + '/history')).items || [];
    $('#communityOrgDetailBody').innerHTML = '<dl class="row"><dt class="col-sm-4">NhÃ¢n kháº©u</dt><dd class="col-sm-8">' + esc([row.full_name, row.citizen_code].filter(Boolean).join(' - ')) + '</dd><dt class="col-sm-4">Tá»• chá»©c</dt><dd class="col-sm-8">' + esc(row.organization_name || '') + '</dd><dt class="col-sm-4">Chá»©c vá»¥</dt><dd class="col-sm-8">' + esc(row.position_name || '') + '</dd><dt class="col-sm-4">Tráº¡ng thÃ¡i</dt><dd class="col-sm-8">' + esc(row.status_label || '') + '</dd><dt class="col-sm-4">Ghi chÃº</dt><dd class="col-sm-8">' + esc(row.note || '') + '</dd></dl><h6>Lá»‹ch sá»­ thay Ä‘á»•i</h6>' + (history.length ? '<ul class="list-group">' + history.map(h => '<li class="list-group-item"><strong>' + esc(h.change_type || '') + '</strong><div class="small text-muted">' + dateTime(h.changed_at) + '</div><div>' + esc(h.note || '') + '</div></li>').join('') + '</ul>' : '<div class="text-muted">ChÆ°a cÃ³ lá»‹ch sá»­ thay Ä‘á»•i.</div>');
    bootstrap.Modal.getOrCreateInstance($('#communityOrgDetailModal')).show();
  }

  async function endMembership(id) {
    if (!confirm('XÃ¡c nháº­n thÃ´i tham gia tá»• chá»©c nÃ y?')) return;
    await request(API + '/' + id + '/end', { method: 'PUT', body: JSON.stringify({ status: 'ENDED', ended_date: today() }) });
    toast('ÄÃ£ cáº­p nháº­t tráº¡ng thÃ¡i thÃ´i tham gia', 'success'); load();
  }

  async function remove(id) {
    if (!confirm('XÃ³a há»“ sÆ¡ Ä‘oÃ n thá»ƒ - chi há»™i nÃ y? Dá»¯ liá»‡u nhÃ¢n kháº©u gá»‘c khÃ´ng bá»‹ xÃ³a.')) return;
    await request(API + '/' + id, { method: 'DELETE' });
    toast('ÄÃ£ xÃ³a há»“ sÆ¡ Ä‘oÃ n thá»ƒ - chi há»™i', 'success'); load();
  }

  function resetFilters() { ['communityOrgSearch','communityOrgAgeFrom','communityOrgAgeTo'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; }); ['communityOrgFilter','communityOrgStatusFilter','communityOrgAreaFilter'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; }); state.page = 1; load(); }
  async function exportExcel() { const data = await request(API + '/report?' + params().toString()); const rows = data.items || []; const html = '<table><tr><th>Há» tÃªn</th><th>MÃ£ nhÃ¢n kháº©u</th><th>MÃ£ há»™</th><th>Tá»• chá»©c</th><th>Chá»©c vá»¥</th><th>NgÃ y tham gia</th><th>Tráº¡ng thÃ¡i</th></tr>' + rows.map(r => '<tr><td>' + esc(r.full_name) + '</td><td>' + esc(r.citizen_code) + '</td><td>' + esc(r.household_code) + '</td><td>' + esc(r.organization_name) + '</td><td>' + esc(r.position_name) + '</td><td>' + date(r.joined_date) + '</td><td>' + esc(r.status_label) + '</td></tr>').join('') + '</table>'; download('bao-cao-doan-the-chi-hoi.xls', html); }
  async function printReport() { const data = await request(API + '/report?' + params().toString()); const rows = data.items || []; const html = '<h2>BÃO CÃO ÄOÃ€N THá»‚ - CHI Há»˜I</h2><table border="1" cellspacing="0" cellpadding="6" style="width:100%;border-collapse:collapse"><tr><th>STT</th><th>Há» tÃªn</th><th>MÃ£ NK</th><th>MÃ£ há»™</th><th>Tá»• chá»©c</th><th>Chá»©c vá»¥</th><th>NgÃ y tham gia</th><th>Tráº¡ng thÃ¡i</th></tr>' + rows.map((r,i) => '<tr><td>' + (i+1) + '</td><td>' + esc(r.full_name) + '</td><td>' + esc(r.citizen_code) + '</td><td>' + esc(r.household_code) + '</td><td>' + esc(r.organization_name) + '</td><td>' + esc(r.position_name) + '</td><td>' + date(r.joined_date) + '</td><td>' + esc(r.status_label) + '</td></tr>').join('') + '</table>'; const w = window.open('', '_blank'); w.document.write('<!doctype html><title>BÃ¡o cÃ¡o Ä‘oÃ n thá»ƒ</title>' + html); w.document.close(); w.print(); }

  function fill(selector, items, allLabel) { const el = typeof selector === 'string' ? $(selector) : selector; if (!el) return; el.innerHTML = (allLabel ? '<option value="">' + esc(allLabel) + '</option>' : '') + (items || []).map(i => '<option value="' + esc(i.value ?? i.code ?? i.id) + '">' + esc(i.label ?? i.name) + '</option>').join(''); }
  function fillPositions(code, selected) { const list = (state.catalogs?.positions || []).filter(p => !code || p.organization_code === code); fill('#communityOrgPositionSelect', list, 'Chá»n chá»©c vá»¥'); if (selected) $('#communityOrgPositionSelect').value = selected; }
  function fillForm(form, row) { Object.keys(row || {}).forEach(k => { if (form.elements[k]) form.elements[k].value = ['joined_date','ended_date'].includes(k) ? date(row[k]) : (row[k] ?? ''); }); if (form.elements.organization_code) form.elements.organization_code.value = row.organization_code || ''; }
  function hideSuggestions() { const box = $('#communityOrgCitizenSuggestions'); if (box) box.innerHTML = ''; }
  function can(action) {
    if (window.TenantAppPlatform?.permissions?.can?.('organizations', action)) return true;
    const role = String(window.App?.user?.role || window.TenantAppPlatform?.state?.user?.role || '').toUpperCase();
    if (role === 'SUPER_ADMIN' || role === 'ADMIN') return true;
    if (role === 'VIEWER') return action === 'read';
    if (role === 'OFFICER') return ['read','create','update','export','print'].includes(action);
    return window.TenantAppPlatform?.permissions?.can?.('citizen', 'read') === true && action === 'read';
  }
  async function request(url, options = {}) {
    const method = String(options.method || 'GET').toUpperCase();
    const headers = Object.assign({ Accept: 'application/json' }, options.headers || {});
    const body = options.body;
    if (body !== undefined && !(body instanceof FormData)) headers['Content-Type'] = headers['Content-Type'] || 'application/json';
    const token = window.App?.token || storageGet(storageKey('token')) || '';
    const csrf = window.App?.csrfToken || storageGet(storageKey('csrf')) || '';
    if (token) headers.Authorization = 'Bearer ' + token;
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && csrf) headers['X-CSRF-Token'] = csrf;
    const response = await fetch(url, { method, headers, credentials: 'same-origin', body: body instanceof FormData ? body : body });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.ok === false) throw new Error(payload.error?.message || payload.message || 'Lá»—i API');
    return payload.data ?? payload;
  }

  function storageKey(key) {
    if (typeof window.tenantStorageKey === 'function') return window.tenantStorageKey(key);
    return 'tenant_app_' + key;
  }

  function storageGet(key) {
    try { return window.localStorage?.getItem?.(key) || ''; } catch (_) { return ''; }
  }
  function toast(message, type = 'info') { if (window.TenantAppPlatform?.toast) return window.TenantAppPlatform.toast(message, type); if (window.showToast) return window.showToast(message, type); alert(message); }
  function debounce(fn, wait) { let t; return function () { clearTimeout(t); const args = arguments; t = setTimeout(() => fn.apply(this, args), wait); }; }
  function esc(v) { return String(v ?? '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c])); }
  function fmt(v) { return new Intl.NumberFormat('vi-VN').format(Number(v || 0)); }
  function date(v) { if (!v) return ''; const m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? m[3] + '/' + m[2] + '/' + m[1] : String(v); }
  function dateTime(v) { return v ? date(String(v).slice(0,10)) + ' ' + String(v).slice(11,16) : ''; }
  function today() { const d = new Date(); return String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0') + '/' + d.getFullYear(); }
  function iconFor(code) { return { WOMEN: 'fa-venus', FARMER: 'fa-seedling', VETERAN: 'fa-medal', YOUTH: 'fa-people-arrows' }[code] || 'fa-people-group'; }
  function download(name, html) { const blob = new Blob(['\ufeff' + html], { type: 'application/vnd.ms-excel;charset=utf-8' }); const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; a.click(); URL.revokeObjectURL(a.href); }
  window.loadCommunityOrganizations = load;
})();

