(() => {
  'use strict';

  const API = '/api/system-admin';
  const DEBUG_PREFIX = '[SystemAdmin]';
  const state = { loaded: false, timers: {} };
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;' }[c]));
  const fmt = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
  const token = () => (window.App && App.token) || localStorage.getItem(tenantStorageKey('token')) || '';
  const csrf = () => (window.App && App.csrfToken) || localStorage.getItem(tenantStorageKey('csrf')) || '';

  document.addEventListener('DOMContentLoaded', boot);
  document.addEventListener('tenant:auth-state', event => { if (event.detail?.authenticated) boot(); });
  document.addEventListener('tenant:screen-change', event => { if (event.detail?.screen === 'systemAdmin') loadAll(); });

  function boot() {
    try {
      debug('Module initialized');
      ensureNav();
      ensureScreen();
      bindEvents();
      if (isActive()) loadAll();
    } catch (error) {
      logError('Module initialization failed', error);
      renderFatal(error);
    }
  }

  function ensureNav() {
    const existing = $('[data-screen="systemAdmin"]');
    if (existing) { syncAdminNavVisibility(existing); return; }
  }

  function syncAdminNavVisibility(btn) {
    const role = window.App && App.user && App.user.role;
    btn.classList.toggle('d-none', !['SUPER_ADMIN', 'ADMIN'].includes(role || ''));
  }

  function ensureScreen() {
    const main = screenHost();
    if ($('#systemAdminScreen')) return;
    if (!main) throw new Error('System admin render container not found');
    main.insertAdjacentHTML('beforeend', `
      <section id="systemAdminScreen" class="screen system-admin-screen">
        <div class="system-admin-shell">
          <div class="system-admin-head">
            <div><h3>Quáº£n trá»‹ há»‡ thá»‘ng</h3><span id="systemAdminGeneratedAt">Äang táº£i tráº¡ng thÃ¡i váº­n hÃ nh</span></div>
            <div class="system-admin-actions">
              <button class="btn btn-outline-success" type="button" data-platform-action="systemAdmin.refresh" data-system-refresh><i class="fa-solid fa-rotate"></i> LÃ m má»›i</button>
              <button class="btn btn-primary" type="button" data-platform-action="systemAdmin.backup" data-system-backup="database"><i class="fa-solid fa-database"></i> Backup Database</button>
              <button class="btn btn-outline-primary" type="button" data-platform-action="systemAdmin.backup" data-system-backup="full"><i class="fa-solid fa-box-archive"></i> Backup toÃ n há»‡ thá»‘ng</button>
            </div>
          </div>
          <div id="systemAdminOverview" class="system-admin-kpis"></div>
          <div class="system-admin-grid">
            ${panel('systemAdminHealth', 'Kiá»ƒm tra sá»©c khá»e há»‡ thá»‘ng', 'fa-heart-pulse')}
            ${panel('systemAdminSessions', 'Quáº£n lÃ½ phiÃªn Ä‘Äƒng nháº­p', 'fa-user-clock', '<input id="systemSessionSearch" class="form-control form-control-sm" placeholder="TÃ¬m phiÃªn"><select id="systemSessionStatus" class="form-select form-select-sm"><option value="active">Äang hoáº¡t Ä‘á»™ng</option><option value="">Táº¥t cáº£</option><option value="revoked">ÄÃ£ thu há»“i</option></select><button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="systemAdmin.sessions.revokeAll" data-revoke-all>ÄÄƒng xuáº¥t táº¥t cáº£</button>')}
            ${panel('systemAdminMemory', 'Quáº£n lÃ½ bá»™ nhá»›', 'fa-broom')}
            ${panel('systemAdminPerformance', 'Hiá»‡u nÄƒng', 'fa-gauge-high')}
            ${panel('systemAdminSecurity', 'Báº£o máº­t', 'fa-shield-halved')}
            ${panel('systemAdminConfig', 'Cáº¥u hÃ¬nh há»‡ thá»‘ng', 'fa-gears')}
          </div>
        </div>
      </section>`);
  }

  function screenHost() {
    return $('.main-area') || $('.content-area') || $('.main-content') || $('#appView');
  }

  function panel(id, title, icon, tools = '') {
    return '<article class="content-card system-admin-panel"><div class="system-admin-panel-head"><h4><i class="fa-solid ' + icon + '"></i> ' + title + '</h4><div class="system-admin-tools">' + tools + '</div></div><div id="' + id + '"></div></article>';
  }

  function normalizeHeader() {
    const title = $('#screenTitle');
    const crumb = $('#breadcrumbTrail');
    if (title) title.textContent = 'Quáº£n trá»‹ há»‡ thá»‘ng';
    if (crumb) crumb.textContent = 'Trang chá»§ / Quáº£n trá»‹ há»‡ thá»‘ng';
  }

  function bindEvents() {
    registerSystemAdminPlatformActions();
    $('#systemSessionSearch')?.addEventListener('input', debounce(loadSessions, 300));
    $('#systemSessionStatus')?.addEventListener('change', loadSessions);
  }

  function registerSystemAdminPlatformActions() {
    const actions = window.TenantAppPlatform && window.TenantAppPlatform.actions;
    if (!actions || typeof actions.register !== 'function') return;
    actions.register('systemAdmin.refresh', () => loadAll(true));
    actions.register('systemAdmin.backup', context => createBackup(context.dataset.systemBackup || 'database'));
    actions.register('systemAdmin.sessions.revokeAll', revokeAllSessions);
    actions.register('systemAdmin.sessions.revoke', context => revokeSession(context.dataset.revokeSession));
    actions.register('systemAdmin.cleanup', context => cleanup(context.dataset.cleanup));
    actions.register('systemAdmin.settings.open', () => window.TenantAppNavigationController?.navigate('settings'));
  }

  function isActive() { return !!$('#systemAdminScreen.active'); }
  function loading(selector) { setHtml(selector, '<div class="system-admin-empty">Äang táº£i dá»¯ liá»‡u...</div>'); }
  function setHtml(selector, html) {
    const host = $(selector);
    if (!host) throw new Error('System admin container not found: ' + selector);
    host.innerHTML = html;
    return host;
  }
  function setText(selector, value) {
    const host = $(selector);
    if (!host) throw new Error('System admin text container not found: ' + selector);
    host.textContent = value;
  }
  function errorBox(selector, error) {
    logError('Widget render failed: ' + selector, error);
    try { setHtml(selector, '<div class="system-admin-error">' + esc(error?.message || 'Widget táº¡m thá»i khÃ´ng táº£i Ä‘Æ°á»£c') + '</div>'); }
    catch (renderError) { logError('Cannot render widget error box: ' + selector, renderError); renderFatal(error || renderError); }
  }
  function renderFatal(error) {
    const main = screenHost();
    if (!main) return;
    let screen = $('#systemAdminScreen');
    if (!screen) {
      main.insertAdjacentHTML('beforeend', '<section id="systemAdminScreen" class="screen system-admin-screen"><div class="content-card system-admin-error"></div></section>');
      screen = $('#systemAdminScreen');
    }
    const box = $('.system-admin-error', screen) || screen;
    box.innerHTML = '<strong>KhÃ´ng hiá»ƒn thá»‹ Ä‘Æ°á»£c module Quáº£n trá»‹ há»‡ thá»‘ng.</strong><div>' + esc(error?.message || 'Lá»—i khÃ´ng xÃ¡c Ä‘á»‹nh') + '</div>';
  }
  function debug(message, detail) { console.info(DEBUG_PREFIX + ' ' + message, detail || ''); }
  function logError(message, error) { console.error(DEBUG_PREFIX + ' ' + message, error); }

  async function apiGet(path, params = {}) {
    const query = Object.keys(params).length ? '?' + new URLSearchParams(params).toString() : '';
    const url = path + query;
    try {
      let data;
      if (typeof window.api === 'function') data = await window.api(url, { cacheTtl: 0 });
      else {
        const res = await fetch(url, { headers: authHeaders(), cache: 'no-store' });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json?.ok) throw new Error(json?.error?.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c dá»¯ liá»‡u');
        data = json.data;
      }
      debug('API loaded', { url });
      return data;
    } catch (error) {
      logError('API failed: ' + url, error);
      throw error;
    }
  }

  async function apiPost(path, body = {}) {
    const res = await fetch(path, { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() }, body: JSON.stringify(body), cache: 'no-store' });
    const json = await res.json().catch(() => null);
    if (!res.ok || !json?.ok) throw new Error(json?.error?.message || 'KhÃ´ng thá»±c hiá»‡n Ä‘Æ°á»£c thao tÃ¡c');
    return json.data;
  }

  function authHeaders() {
    const headers = { Accept: 'application/json' };
    if (token()) headers.Authorization = 'Bearer ' + token();
    return headers;
  }

  async function loadAll(force = false) {
    try {
      ensureScreen();
      debug('Render started', { force, active: isActive() });
      if (!token()) throw new Error('Missing authentication token');
      if (state.loaded && !force && !isActive()) return;
      state.loaded = true;
      bindEvents();
      const results = await Promise.allSettled([loadOverview(), loadHealth(), loadSessions(), loadMemory(), loadPerformance(), loadSecurity(), loadConfig()]);
      const rejected = results.filter(result => result.status === 'rejected');
      rejected.forEach(result => logError('Render task rejected', result.reason));
      debug('DOM rendered', { rejected: rejected.length });
    } catch (error) {
      logError('Render failed', error);
      renderFatal(error);
    }
  }

  async function loadOverview() {
    loading('#systemAdminOverview');
    try {
      const data = await apiGet(API + '/overview');
      $('#systemAdminGeneratedAt').textContent = 'Cáº­p nháº­t lÃºc ' + formatTime(data.system?.generatedAt);
      const c = data.counts || {}, s = data.storage || {}, sys = data.system || {};
      setHtml('#systemAdminOverview', [
        kpi('PhiÃªn báº£n há»‡ thá»‘ng', esc(sys.version || '')), kpi('Database', esc(sys.databaseVersion || '')), kpi('Uptime', esc(sys.uptime || '')),
        kpi('Dung lÆ°á»£ng Upload', esc(s.uploads?.label || '0 B')), kpi('NgÆ°á»i dÃ¹ng', fmt(c.users)), kpi('Há»™', fmt(c.households)),
        kpi('NhÃ¢n kháº©u', fmt(c.citizens)), kpi('Há»“ sÆ¡ sá»‘', fmt(c.digitalProfiles)), kpi('TÃ i liá»‡u', fmt(c.documents)), kpi('áº¢nh', fmt(c.images)), kpi('Video', fmt(c.videos))
      ].join(''));
    } catch (error) { errorBox('#systemAdminOverview', error); }
  }

  async function loadHealth() {
    loading('#systemAdminHealth');
    try {
      const data = await apiGet(API + '/health');
      setHtml('#systemAdminHealth', '<div class="system-admin-checks">' + (data.checks || []).map(check => checkRow(check)).join('') + '</div>');
    } catch (error) { errorBox('#systemAdminHealth', error); }
  }

  async function loadSessions() {
    loading('#systemAdminSessions');
    try {
      const data = await apiGet(API + '/sessions', { search: value('#systemSessionSearch'), status: value('#systemSessionStatus') || 'active', pageSize: 30 });
      const rows = data.items || [];
      setHtml('#systemAdminSessions', rows.length ? '<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>NgÆ°á»i dÃ¹ng</th><th>Thiáº¿t bá»‹</th><th>IP</th><th>Hoáº¡t Ä‘á»™ng</th><th></th></tr></thead><tbody>' + rows.map(row => '<tr><td><strong>' + esc(row.display_name || row.email || '') + '</strong><small>' + esc(row.email || '') + '</small></td><td>' + esc(row.device) + ' / ' + esc(row.browser) + '</td><td>' + esc(row.ip_address || '') + '</td><td><span class="system-admin-status is-' + esc((row.status || '').toLowerCase()) + '">' + esc(row.status) + '</span><small>' + esc(formatTime(row.created_at)) + '</small></td><td>' + (row.status === 'ACTIVE' ? '<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="systemAdmin.sessions.revoke" data-revoke-session="' + Number(row.id) + '">ÄÄƒng xuáº¥t</button>' : '') + '</td></tr>').join('') + '</tbody></table></div>' : empty('KhÃ´ng cÃ³ phiÃªn phÃ¹ há»£p'));
    } catch (error) { errorBox('#systemAdminSessions', error); }
  }

  async function loadMemory() {
    loading('#systemAdminMemory');
    try {
      const data = await apiGet(API + '/memory');
      setHtml('#systemAdminMemory', '<div class="system-admin-memory">' + (data.items || []).map(item => '<div><span><strong>' + esc(item.label) + '</strong><small>' + esc(item.stats?.label || '0 B') + ' Â· ' + fmt(item.stats?.files || item.stats?.expired || 0) + '</small></span>' + cleanupButton(item.key) + '</div>').join('') + '</div>');
    } catch (error) { errorBox('#systemAdminMemory', error); }
  }

  async function loadPerformance() {
    loading('#systemAdminPerformance');
    try {
      const data = await apiGet(API + '/performance');
      setHtml('#systemAdminPerformance', '<div class="system-admin-perf">' + (data.metrics || []).map(m => '<div><strong>' + esc(m.label) + '</strong><span>' + esc(m.value) + ' ' + esc(m.unit) + '</span></div>').join('') + '</div><h6>Äá» xuáº¥t tá»‘i Æ°u</h6><ul class="system-admin-list">' + (data.recommendations || []).map(item => '<li>' + esc(item) + '</li>').join('') + '</ul>');
    } catch (error) { errorBox('#systemAdminPerformance', error); }
  }

  async function loadSecurity() {
    loading('#systemAdminSecurity');
    try {
      const data = await apiGet(API + '/security');
      setHtml('#systemAdminSecurity', '<div class="system-admin-checks">' + (data.checks || []).map(check => checkRow(check)).join('') + '</div>');
    } catch (error) { errorBox('#systemAdminSecurity', error); }
  }

  async function loadConfig() {
    loading('#systemAdminConfig');
    try {
      const data = await apiGet(API + '/configuration');
      const settings = data.settings || {};
      const keys = ['systemName','hamletName','communeName','email','phone','address','timezone','language'];
      setHtml('#systemAdminConfig', '<div class="system-admin-config">' + keys.map(key => '<div><span>' + esc(key) + '</span><strong>' + esc(settings[key] || data[key] || '') + '</strong></div>').join('') + '</div><button class="btn btn-sm btn-outline-primary mt-2" type="button" data-platform-action="systemAdmin.settings.open" data-open-settings>Má»Ÿ cáº¥u hÃ¬nh</button>');
    } catch (error) { errorBox('#systemAdminConfig', error); }
  }

  async function createBackup(type) {
    const accepted = await confirmAction({
      title: 'XÃ¡c nháº­n táº¡o backup',
      message: type === 'full' ? 'Táº¡o backup toÃ n há»‡ thá»‘ng? Há»‡ thá»‘ng sáº½ xuáº¥t SQL vÃ  ghi nháº­t kÃ½ thao tÃ¡c.' : 'Táº¡o backup database ngay bÃ¢y giá»?',
      confirmLabel: 'Táº¡o backup',
      tone: 'danger'
    });
    if (!accepted) return;
    const res = await fetch(API + '/backups', { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() }, body: JSON.stringify({ type }), cache: 'no-store' });
    if (!res.ok) { notify('KhÃ´ng táº¡o Ä‘Æ°á»£c backup', 'danger'); return; }
    const blob = await res.blob(); const url = URL.createObjectURL(blob); const link = document.createElement('a');
    link.href = url; link.download = 'system-backup.sql'; document.body.appendChild(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 30000);
    notify('ÄÃ£ táº¡o backup', 'success');
  }

  async function revokeSession(id) {
    if (!(await confirmAction({ title: 'XÃ¡c nháº­n Ä‘Äƒng xuáº¥t', message: 'ÄÄƒng xuáº¥t phiÃªn nÃ y?', confirmLabel: 'ÄÄƒng xuáº¥t', tone: 'danger' }))) return;
    await apiPost(API + '/sessions/' + id + '/revoke'); notify('ÄÃ£ Ä‘Äƒng xuáº¥t phiÃªn', 'success'); loadSessions();
  }
  async function revokeAllSessions() {
    if (!(await confirmAction({ title: 'XÃ¡c nháº­n Ä‘Äƒng xuáº¥t', message: 'ÄÄƒng xuáº¥t táº¥t cáº£ phiÃªn khÃ¡c?', confirmLabel: 'ÄÄƒng xuáº¥t táº¥t cáº£', tone: 'danger' }))) return;
    await apiPost(API + '/sessions/revoke-all'); notify('ÄÃ£ Ä‘Äƒng xuáº¥t cÃ¡c phiÃªn khÃ¡c', 'success'); loadSessions();
  }
  async function cleanup(target) {
    if (!(await confirmAction({ title: 'XÃ¡c nháº­n dá»n dáº¹p', message: 'Dá»n dáº¹p má»¥c nÃ y? Dá»¯ liá»‡u ngÆ°á»i dÃ¹ng sáº½ khÃ´ng bá»‹ xÃ³a.', confirmLabel: 'Dá»n dáº¹p', tone: 'danger' }))) return;
    const data = await apiPost(API + '/cleanup', { target }); notify('ÄÃ£ dá»n ' + (data.label || ''), 'success'); loadMemory();
  }

  function kpi(label, value) { return '<div class="system-admin-kpi"><span>' + esc(label) + '</span><strong>' + value + '</strong></div>'; }
  function checkRow(check) { return '<div class="system-admin-check is-' + esc(check.status || 'ok') + '"><span></span><div><strong>' + esc(check.label) + '</strong><small>' + esc(check.message) + '</small></div></div>'; }
  function cleanupButton(key) { return ['cache','sessions','tmp'].includes(key) ? '<button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="systemAdmin.cleanup" data-cleanup="' + esc(key) + '">Dá»n</button>' : '<span class="text-muted small">Chá»‰ xem</span>'; }
  function empty(text) { return '<div class="system-admin-empty">' + esc(text) + '</div>'; }
  function value(selector) { return String($(selector)?.value || '').trim(); }
  function formatTime(value) { if (!value) return ''; try { return new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value)); } catch (_) { return String(value); } }
  function debounce(fn, wait) { let timer; return function () { clearTimeout(timer); timer = setTimeout(() => fn.apply(this, arguments), wait); }; }
  function notify(message, type) { if (typeof window.showToast === 'function') window.showToast(message, type || 'info'); }
  window.loadSystemAdmin = loadAll;
  function confirmAction(options) {
    const service = window.TenantAppPlatform && window.TenantAppPlatform.confirmDialog;
    if (service && typeof service.ask === 'function') return service.ask(options);
    return Promise.resolve(typeof window.confirm === 'function' ? window.confirm(options.message || 'XÃ¡c nháº­n thao tÃ¡c?') : false);
  }
})();
