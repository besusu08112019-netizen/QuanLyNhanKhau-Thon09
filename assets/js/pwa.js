(function () {
  'use strict';

  const APP_NAME = window.AppSettings?.systemName || 'Hệ thống Quản lý Hành chính';
  const TENANT_NAMESPACE = window.TenantRuntime?.namespace || String(window.AppSettings?.tenantNamespace || location.host || 'tenant').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '') || 'tenant';
  const DB_NAME = `${TENANT_NAMESPACE}-pwa`;
  const UPDATE_CHECK_INTERVAL_MS = 30 * 60 * 1000;
  const INSTALL_CONFIRM_TIMEOUT_MS = 30 * 1000;
  const DB_VERSION = 1;
  const SYNC_TAG = `${TENANT_NAMESPACE}-background-sync`;
  const CACHEABLE_API = [
    [/^\/api\/dashboard/, 'dashboard'],
    [/^\/api\/households(?:\/|\?|$)/, 'households'],
    [/^\/api\/persons(?:\/|\?|$)/, 'persons'],
    [/^\/api\/gis(?:\/|\?|$)/, 'gis'],
    [/^\/api\/public-assets(?:\/|\?|$)/, 'public_assets'],
    [/^\/api\/notifications(?:\/|\?|$)/, 'notifications'],
    [/^\/api\/reports(?:\/|\?|$)/, 'reports'],
    [/^\/api\/settings(?:\/|\?|$)/, 'settings'],
    [/^\/api\/permissions(?:\/|\?|$)/, 'lookups']
  ];
  const QUEUEABLE_API = /^\/api\/(?:households|persons|gis|files|public-assets|reports|household-business|livestock|agriculture|houses|notifications)(?:\/|\?|$)/;
  const state = {
    db: null,
    deferredInstall: null,
    registration: null,
    refreshing: false,
    syncing: false,
    updateWorker: null,
    updateTimer: null,
    installStage: '',
    installOutcome: '',
    installError: '',
    appinstalledFired: false
  };

  if (navigator.webdriver && 'serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations?.().then(registrations => registrations.forEach(registration => registration.unregister())).catch(() => {});
  }

  if (!('serviceWorker' in navigator) || navigator.webdriver) {
    initUi();
    updateNetworkStatus();
    return;
  }

  init();

  async function init() {
    initUi();
    state.db = await openDb().catch(() => null);
    patchFetch();
    bindLifecycle();
    await registerServiceWorker();
    hydrateOnlineState();
    flushQueueSoon();
  }

  function bindLifecycle() {
    window.addEventListener('online', () => { updateNetworkStatus(); flushQueueSoon(); });
    window.addEventListener('offline', updateNetworkStatus);
    window.addEventListener('beforeinstallprompt', event => {
      event.preventDefault();
      state.deferredInstall = event;
      state.installStage = 'AVAILABLE';
      state.installOutcome = '';
      state.installError = '';
      debugInstall('beforeinstallprompt fired');
      renderInstallControl();
    });
    window.addEventListener('appinstalled', () => {
      state.deferredInstall = null;
      state.installStage = 'INSTALLED';
      state.installOutcome = 'installed';
      state.appinstalledFired = true;
      debugInstall('appinstalled fired');
      renderInstallControl();
      notify('Ứng dụng đã được cài đặt', 'success');
    });
    navigator.serviceWorker.addEventListener('message', event => {
      const type = event.data && event.data.type;
      if (type === 'PWA_SYNC_REQUESTED') flushQueueSoon();
      if (type === 'PWA_READY') document.dispatchEvent(new CustomEvent(tenantEventName('pwa-ready'), { detail: event.data }));
      if (type === 'PWA_UPDATED') document.dispatchEvent(new CustomEvent(tenantEventName('pwa-updated'), { detail: event.data }));
      if (type === 'PWA_CACHE_CLEARED') document.dispatchEvent(new CustomEvent(tenantEventName('pwa-cache-cleared')));
    });
    document.addEventListener(tenantEventName('auth-state'), event => {
      if (!event.detail || event.detail.authenticated === false) clearUserData();
    });
    document.addEventListener('visibilitychange', () => {
      debugInstall('visibilitychange', { hidden: document.hidden });
      if (!document.hidden) checkForServiceWorkerUpdate();
    });
    window.addEventListener('focus', () => {
      debugInstall('window focus');
      checkForServiceWorkerUpdate();
    });
    window.addEventListener('blur', () => {
      debugInstall('window blur');
    });
    const displayMode = window.matchMedia('(display-mode: standalone)');
    displayMode.addEventListener?.('change', () => { renderInstallControl(); debugInstall('display-mode changed'); });
    displayMode.addListener?.(() => { renderInstallControl(); debugInstall('display-mode changed'); });
  }

  async function registerServiceWorker() {
    const isSupportedContext = window.isSecureContext || ['localhost', '127.0.0.1'].includes(location.hostname);
    if (!isSupportedContext) return;
    try {
      const scope = appBasePath();
      state.registration = await navigator.serviceWorker.register(scope + 'service-worker.js', { scope, updateViaCache: 'none' });
      if (state.registration.waiting && navigator.serviceWorker.controller) showUpdateBanner(state.registration.waiting);
      state.registration.addEventListener('updatefound', () => {
        const worker = state.registration.installing;
        if (!worker) return;
        worker.addEventListener('statechange', () => {
          if (worker.state === 'installed' && navigator.serviceWorker.controller) showUpdateBanner(worker);
        });
      });
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (state.refreshing) return;
        if (isInstallInProgress()) {
          debugInstall('controllerchange reload suppressed during install');
          return;
        }
        state.refreshing = true;
        location.reload();
      });
      await checkForServiceWorkerUpdate();
      state.updateTimer = window.setInterval(checkForServiceWorkerUpdate, UPDATE_CHECK_INTERVAL_MS);
    } catch (error) {
      warn('Service worker registration failed', error);
    }
  }

  function appBasePath() {
    const script = document.currentScript || Array.from(document.scripts || []).find(item => /\/assets\/js\/pwa(?:\.min)?\.js(?:[?#].*)?$/.test(item.src || ''));
    const source = script?.src || document.baseURI || location.href;
    try {
      const parsed = new URL(source, location.href);
      const marker = '/assets/js/';
      const index = parsed.pathname.indexOf(marker);
      const base = index >= 0 ? parsed.pathname.slice(0, index + 1) : parsed.pathname.replace(/[^/]*$/, '');
      return base.endsWith('/') ? base : base + '/';
    } catch (_) {
      return '/';
    }
  }

  async function checkForServiceWorkerUpdate() {
    if (!state.registration || document.hidden) return;
    if (isInstallInProgress()) {
      debugInstall('service worker update check suppressed during install');
      return;
    }
    try {
      await state.registration.update();
      if (state.registration.waiting && navigator.serviceWorker.controller) showUpdateBanner(state.registration.waiting);
    } catch (error) {
      warn('Service worker update check failed', error);
    }
  }

  function patchFetch() {
    if (window.__TenantAppPwaFetchPatched) return;
    window.__TenantAppPwaFetchPatched = true;
    const nativeFetch = window.fetch.bind(window);
    window.__TenantAppPwaNativeFetch = nativeFetch;
    window.fetch = async function pwaFetch(input, init) {
      const request = normalizeRequest(input, init);
      if (!request || request.url.origin !== location.origin || !request.url.pathname.startsWith('/api/')) {
        return nativeFetch(input, init);
      }
      if (request.method === 'GET') return apiGet(nativeFetch, input, init, request);
      return apiWrite(nativeFetch, input, init, request);
    };
  }

  async function apiGet(nativeFetch, input, init, request) {
    const bucket = apiBucket(request.path);
    try {
      const response = await nativeFetch(input, init);
      if (bucket && response.ok && isJsonResponse(response)) {
        const copy = response.clone();
        copy.json().then(payload => storeApiResponse(bucket, request.cacheKey, payload)).catch(() => {});
      }
      return response;
    } catch (error) {
      if (!bucket) throw error;
      const cached = await readApiResponse(request.cacheKey);
      if (!cached) throw error;
      setOfflineReadonly(true);
      return jsonResponse(cached.payload, 200, { 'X-tenant-app-Offline': '1' });
    }
  }

  async function apiWrite(nativeFetch, input, init, request) {
    if (navigator.onLine !== false) {
      try { return await nativeFetch(input, init); } catch (error) { if (!canQueue(request)) throw error; }
    } else if (!canQueue(request)) {
      return jsonResponse({ ok: false, error: { message: 'Đang ngoại tuyến. Thao tác này cần kết nối Internet.' } }, 503);
    }

    const entry = await buildQueueEntry(input, init, request);
    if (!entry) return jsonResponse({ ok: false, error: { message: 'Không thể lưu thao tác ngoại tuyến này.' } }, 422);
    await addQueueEntry(entry);
    await registerBackgroundSync();
    updateNetworkStatus(true);
    notify('Đã lưu thao tác vào hàng đợi đồng bộ', 'warning');
    return jsonResponse({ ok: true, success: true, data: { queued: true, offline: true }, message: 'Đã lưu vào hàng đợi đồng bộ' }, 202);
  }

  function normalizeRequest(input, init) {
    try {
      const source = input instanceof Request ? input : new Request(input, init);
      const url = new URL(source.url, location.href);
      const method = ((init && init.method) || source.method || 'GET').toUpperCase();
      const path = url.pathname + url.search;
      return { source, url, method, path, cacheKey: userKey(path), headers: new Headers((init && init.headers) || source.headers || {}) };
    } catch (_) { return null; }
  }

  function apiBucket(path) {
    const match = CACHEABLE_API.find(([regex]) => regex.test(path));
    return match && match[1];
  }

  function canQueue(request) {
    return QUEUEABLE_API.test(request.path) && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method);
  }

  async function buildQueueEntry(input, init, request) {
    const body = await serializeBody(input, init);
    if (body && body.unsupported) return null;
    const headers = {};
    request.headers.forEach((value, key) => {
      if (!['authorization', 'cookie', 'x-csrf-token'].includes(key.toLowerCase())) headers[key] = value;
    });
    return {
      id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
      userId: currentUserId(),
      url: request.path,
      method: request.method,
      headers,
      body,
      attempts: 0,
      status: 'pending',
      createdAt: Date.now(),
      updatedAt: Date.now(),
      lastError: ''
    };
  }

  async function serializeBody(input, init) {
    const body = init && Object.prototype.hasOwnProperty.call(init, 'body') ? init.body : null;
    if (body == null) return null;
    if (typeof body === 'string') return { type: 'text', value: body };
    if (body instanceof URLSearchParams) return { type: 'text', value: body.toString(), contentType: 'application/x-www-form-urlencoded;charset=UTF-8' };
    if (body instanceof FormData) {
      const fields = [];
      for (const [name, value] of body.entries()) {
        if (value instanceof File) {
          fields.push({ name, file: true, filename: value.name, contentType: value.type || 'application/octet-stream', value: await blobToDataUrl(value) });
        } else fields.push({ name, value: String(value) });
      }
      return { type: 'formData', fields };
    }
    if (body instanceof Blob) return { type: 'blob', value: await blobToDataUrl(body), contentType: body.type };
    if (input instanceof Request) {
      try { return { type: 'text', value: await input.clone().text() }; } catch (_) { return { unsupported: true }; }
    }
    return { unsupported: true };
  }

  async function flushQueueSoon() {
    if (state.syncing || navigator.onLine === false) return;
    state.syncing = true;
    updateNetworkStatus(true);
    try { await flushQueue(); } finally { state.syncing = false; updateNetworkStatus(); }
  }

  async function flushQueue() {
    const entries = await getQueueEntries();
    if (!entries.length) return;
    for (const entry of entries) {
      try {
        await replayQueueEntry(entry);
        await deleteQueueEntry(entry.id);
        notify('Đã đồng bộ một thao tác ngoại tuyến', 'success');
      } catch (error) {
        entry.attempts += 1;
        entry.updatedAt = Date.now();
        entry.lastError = error.message || 'Đồng bộ thất bại';
        entry.status = entry.attempts >= 5 ? 'failed' : 'pending';
        await put('syncQueue', entry);
        if (entry.status === 'failed') notify('Một thao tác đồng bộ thất bại. Vui lòng kiểm tra lại dữ liệu.', 'danger');
      }
    }
  }

  async function replayQueueEntry(entry) {
    const headers = new Headers(entry.headers || {});
    const token = localStorage.getItem(tenantStorageKey('token')) || '';
    const csrf = localStorage.getItem(tenantStorageKey('csrf')) || '';
    if (token) headers.set('Authorization', `Bearer ${token}`);
    if (csrf) headers.set('X-CSRF-Token', csrf);
    const body = await deserializeBody(entry.body, headers);
    const nativeFetch = window.__TenantAppPwaNativeFetch || window.fetch.bind(window);
    const response = await nativeFetch(entry.url, { method: entry.method, headers, body, cache: 'no-store' });
    const payload = await response.clone().json().catch(() => null);
    if (!response.ok || (payload && payload.ok === false)) throw new Error((payload && payload.error && payload.error.message) || `HTTP ${response.status}`);
  }

  async function deserializeBody(body, headers) {
    if (!body) return undefined;
    if (body.type === 'text') {
      if (body.contentType && !headers.has('Content-Type')) headers.set('Content-Type', body.contentType);
      return body.value;
    }
    if (body.type === 'blob') {
      const blob = await dataUrlToBlob(body.value);
      if (body.contentType && !headers.has('Content-Type')) headers.set('Content-Type', body.contentType);
      return blob;
    }
    if (body.type === 'formData') {
      const form = new FormData();
      for (const field of body.fields || []) {
        if (field.file) {
          const blob = await dataUrlToBlob(field.value);
          form.append(field.name, new File([blob], field.filename || 'upload.bin', { type: field.contentType || blob.type }));
        } else form.append(field.name, field.value);
      }
      headers.delete('Content-Type');
      return form;
    }
    return undefined;
  }

  function initUi() {
    if (!document.querySelector('#pwaStatusBar')) {
      const bar = document.createElement('div');
      bar.id = 'pwaStatusBar';
      bar.className = 'pwa-status-bar';
      bar.innerHTML = '<span class="pwa-status-dot" aria-hidden="true"></span><span data-pwa-status-text>Đang trực tuyến</span><button class="btn btn-outline-secondary d-none" type="button" data-pwa-sync>Đồng bộ</button>';
      document.body.appendChild(bar);
      bar.querySelector('[data-pwa-sync]').addEventListener('click', flushQueueSoon);
    }
    renderInstallControl();
    exposePublicApi();
    renderPwaDebugPanel();
    updateNetworkStatus();
  }

  function showInstallButton() { renderInstallControl(); }

  function hideInstallButton() {
    document.querySelector('#pwaInstallBtn')?.remove();
    document.querySelector('#pwaInstallGuide')?.remove();
  }

  function installState() {
    if (isStandalone()) return 'ALREADY_INSTALLED';
    if (['PROMPTING', 'ACCEPTED_WAITING', 'DISMISSED', 'UNCONFIRMED', 'ERROR', 'INSTALLED'].includes(state.installStage)) return state.installStage;
    if (state.deferredInstall) return 'INSTALL_AVAILABLE';
    if (isIosLike()) return 'IOS_INSTALL_GUIDE';
    if (!('serviceWorker' in navigator) || !window.isSecureContext) return 'NOT_SUPPORTED';
    return 'BROWSER_NOT_ELIGIBLE';
  }

  function renderInstallControl() {
    const mode = installState();
    hideInstallButton();
    if (!['INSTALL_AVAILABLE', 'IOS_INSTALL_GUIDE', 'PROMPTING', 'ACCEPTED_WAITING', 'DISMISSED', 'UNCONFIRMED', 'ERROR'].includes(mode)) return;
    const btn = document.createElement('button');
    btn.id = 'pwaInstallBtn';
    btn.className = 'btn btn-success pwa-install-btn';
    btn.type = 'button';
    btn.dataset.installState = mode;
    btn.innerHTML = installButtonHtml(mode);
    btn.disabled = ['PROMPTING', 'ACCEPTED_WAITING'].includes(mode);
    if (!btn.disabled) btn.addEventListener('click', promptInstall);
    document.body.appendChild(btn);
  }

  async function promptInstall() {
    debugInstall('install button clicked');
    if (isStandalone()) {
      renderInstallControl();
      return;
    }
    if (state.deferredInstall) {
      const promptEvent = state.deferredInstall;
      state.installStage = 'PROMPTING';
      state.installOutcome = '';
      state.installError = '';
      renderInstallControl();
      try {
        debugInstall('prompt called');
        await promptEvent.prompt();
        const choice = await promptEvent.userChoice;
        state.installOutcome = choice && choice.outcome ? choice.outcome : 'unknown';
        debugInstall('userChoice resolved', { outcome: state.installOutcome, platform: choice && choice.platform });
        state.deferredInstall = null;
        if (state.installOutcome === 'accepted') {
          state.installStage = 'ACCEPTED_WAITING';
          window.setTimeout(() => {
            if (state.installStage === 'ACCEPTED_WAITING' && !state.appinstalledFired && !isStandalone()) {
              state.installStage = 'UNCONFIRMED';
              state.installError = 'Install was not confirmed by appinstalled event';
              debugInstall('install timeout unconfirmed', { state: 'UNCONFIRMED' });
              renderInstallControl();
              refreshPwaDebugPanel();
            }
          }, INSTALL_CONFIRM_TIMEOUT_MS);
        } else if (state.installOutcome === 'dismissed') {
          state.installStage = 'DISMISSED';
          notify('Cai ung dung da bi huy', 'warning');
          window.setTimeout(() => {
            if (state.installStage === 'DISMISSED') {
              state.installStage = '';
              renderInstallControl();
            }
          }, 1500);
        } else {
          state.installStage = 'ERROR';
          state.installError = 'Unexpected userChoice outcome';
        }
      } catch (error) {
        state.deferredInstall = null;
        state.installStage = 'ERROR';
        state.installOutcome = 'error';
        state.installError = error && error.message ? error.message : String(error || 'prompt failed');
        debugInstall('prompt error', { error: state.installError });
        notify('Khong the mo hop thoai cai ung dung', 'danger');
      }
      renderInstallControl();
      return;
    }
    if (state.installStage === 'UNCONFIRMED') {
      state.installStage = '';
      state.installError = '';
      debugInstall('unconfirmed retry requested');
      renderInstallControl();
      refreshPwaDebugPanel();
      return;
    }
    if (isIosLike()) showIosInstallGuide();
  }

  function installButtonHtml(mode) {
    if (mode === 'PROMPTING') return '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Dang mo xac nhan';
    if (mode === 'ACCEPTED_WAITING') return '<i class="fa-solid fa-clock" aria-hidden="true"></i> Da chap nhan, cho he thong xac nhan';
    if (mode === 'DISMISSED') return '<i class="fa-solid fa-download" aria-hidden="true"></i> Cai ung dung';
    if (mode === 'UNCONFIRMED') return '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Chua xac nhan duoc viec cai dat - Thu lai';
    if (mode === 'ERROR') return '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Thu cai lai';
    return '<i class="fa-solid fa-download" aria-hidden="true"></i> Cai ung dung';
  }

  function showIosInstallGuide() {
    if (document.querySelector('#pwaInstallGuide')) return;
    const guide = document.createElement('div');
    guide.id = 'pwaInstallGuide';
    guide.className = 'pwa-install-guide';
    guide.setAttribute('role', 'dialog');
    guide.setAttribute('aria-modal', 'true');
    guide.innerHTML = '<div class="pwa-install-guide-panel"><button class="pwa-install-guide-close" type="button" aria-label="Đóng">&times;</button><h2>Cài ứng dụng</h2><ol><li>Mở trang bằng Safari hoặc trình duyệt iOS/iPadOS có hỗ trợ.</li><li>Chọn nút Chia sẻ.</li><li>Chọn Thêm vào Màn hình chính.</li><li>Xác nhận tên ứng dụng và bấm Thêm.</li></ol></div>';
    guide.addEventListener('click', event => {
      if (event.target === guide || event.target.closest('.pwa-install-guide-close')) guide.remove();
    });
    document.body.appendChild(guide);
  }

  function isIosLike() {
    const ua = navigator.userAgent || '';
    return /iPad|iPhone|iPod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  }

  function exposePublicApi() {
    window.TenantAppPWA = Object.assign(window.TenantAppPWA || {}, {
      installState,
      promptInstall,
      isStandalone,
      installDebug: installDebugSnapshot,
      refreshDebugPanel: refreshPwaDebugPanel
    });
  }

  function isPwaDebugPanelEnabled() {
    try {
      const params = new URLSearchParams(location.search || '');
      return String(window.AppSettings?.appEnv || '').toLowerCase() === 'staging' && params.get('pwa_debug') === '1';
    } catch (_) {
      return false;
    }
  }

  function renderPwaDebugPanel() {
    if (!isPwaDebugPanelEnabled() || document.querySelector('#pwaDebugPanel')) return;
    const panel = document.createElement('section');
    panel.id = 'pwaDebugPanel';
    panel.setAttribute('aria-label', 'PWA install debug');
    panel.style.cssText = 'position:fixed;inset:0;z-index:2147483000;background:#f8fafc;color:#111827;font:13px/1.45 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;overflow:auto;padding:14px;';
    panel.innerHTML = '<div style="max-width:760px;margin:0 auto 80px"><div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px"><h1 style="font-size:20px;margin:0">PWA INSTALL DEBUG</h1><button type="button" data-pwa-debug-close style="border:1px solid #cbd5e1;background:#fff;padding:8px 10px;border-radius:6px">Dong</button></div><div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px"><button type="button" data-pwa-debug-refresh style="border:1px solid #0f766e;background:#0f766e;color:#fff;padding:9px 12px;border-radius:6px">Refresh</button><button type="button" data-pwa-debug-copy style="border:1px solid #2563eb;background:#2563eb;color:#fff;padding:9px 12px;border-radius:6px">Copy debug</button><button type="button" data-pwa-debug-reset style="border:1px solid #b91c1c;background:#fff;color:#b91c1c;padding:9px 12px;border-radius:6px">Reset PWA Debug</button></div><pre data-pwa-debug-report style="white-space:pre-wrap;word-break:break-word;background:#fff;border:1px solid #cbd5e1;border-radius:8px;padding:12px;min-height:320px"></pre><textarea data-pwa-debug-copybox style="position:absolute;left:-9999px;top:auto;width:1px;height:1px"></textarea></div>';
    panel.querySelector('[data-pwa-debug-close]').addEventListener('click', () => panel.remove());
    panel.querySelector('[data-pwa-debug-refresh]').addEventListener('click', refreshPwaDebugPanel);
    panel.querySelector('[data-pwa-debug-copy]').addEventListener('click', copyPwaDebugReport);
    panel.querySelector('[data-pwa-debug-reset]').addEventListener('click', resetPwaDebugState);
    document.body.appendChild(panel);
    refreshPwaDebugPanel();
  }

  async function refreshPwaDebugPanel() {
    const report = document.querySelector('#pwaDebugPanel [data-pwa-debug-report]');
    if (!report) return;
    const rows = readPwaDebugRows();
    const manifest = await readPwaManifest();
    report.textContent = buildPwaDebugReport(rows, manifest);
  }

  function readPwaDebugRows() {
    try {
      return JSON.parse(localStorage.getItem(pwaDebugStorageKey()) || '[]').filter(Boolean);
    } catch (_) {
      return [];
    }
  }

  function pwaDebugStorageKey() {
    return typeof tenantStorageKey === 'function' ? tenantStorageKey('pwaInstallDebug') : TENANT_NAMESPACE + '_pwaInstallDebug';
  }

  async function readPwaManifest() {
    const href = document.querySelector('link[rel="manifest"]')?.href || '';
    if (!href) return null;
    try {
      const response = await fetch(href, { cache: 'no-store' });
      if (!response.ok) return { error: 'HTTP ' + response.status, href };
      const payload = await response.json();
      payload.href = href;
      return payload;
    } catch (error) {
      return { error: error && error.message ? error.message : String(error), href };
    }
  }

  function buildPwaDebugReport(rows, manifest) {
    const latest = rows[rows.length - 1] || installDebugSnapshot();
    const find = pattern => rows.find(row => pattern.test(row.message || ''));
    const findLast = pattern => rows.slice().reverse().find(row => pattern.test(row.message || ''));
    const count = pattern => rows.filter(row => pattern.test(row.message || '')).length;
    const userChoice = findLast(/userChoice resolved/i);
    const visibility = findLast(/visibilitychange/i);
    const controller = findLast(/controllerchange/i);
    const timeout = findLast(/install timeout unconfirmed|install accepted but appinstalled not confirmed/i);
    const lines = [];
    lines.push('PWA INSTALL DEBUG');
    lines.push('');
    lines.push('Storage key: ' + pwaDebugStorageKey());
    lines.push('Rows: ' + rows.length);
    lines.push('Generated: ' + new Date().toISOString());
    lines.push('');
    lines.push('Browser: ' + (navigator.userAgent || ''));
    lines.push('Android: ' + (/Android/i.test(navigator.userAgent || '') ? 'YES' : 'NO'));
    lines.push('Standalone: ' + (isStandalone() ? 'YES' : 'NO'));
    lines.push('');
    lines.push('beforeinstallprompt: ' + (find(/beforeinstallprompt fired/i) ? 'YES' : 'NO'));
    lines.push('install click: ' + (find(/install button clicked/i) ? 'YES' : 'NO'));
    lines.push('prompt called: ' + (find(/prompt called/i) ? 'YES' : 'NO'));
    lines.push('userChoice: ' + (userChoice ? (userChoice.outcome || userChoice.installOutcome || 'resolved') : 'MISSING'));
    lines.push('appinstalled: ' + (find(/appinstalled fired/i) ? 'FIRED' : 'NOT_FIRED'));
    lines.push('');
    lines.push('visibilitychange: ' + count(/visibilitychange/i) + (visibility ? ' last hidden=' + String(visibility.hidden) : ''));
    lines.push('focus: ' + count(/window focus/i));
    lines.push('blur: ' + count(/window blur/i));
    lines.push('controllerchange: ' + (controller ? 'YES' : 'NO'));
    lines.push('');
    lines.push('install timeout: ' + (timeout ? 'FIRED' : 'NOT_FIRED'));
    lines.push('final state: ' + (latest.stage || installState()));
    lines.push('error: ' + (latest.error || ''));
    lines.push('');
    lines.push('SW registered: ' + (latest.serviceWorkerRegistered ? 'YES' : 'NO'));
    lines.push('SW active: ' + (latest.serviceWorkerActive ? 'YES' : 'NO'));
    lines.push('SW controlling: ' + (latest.serviceWorkerControlling ? 'YES' : 'NO'));
    lines.push('SW scope: ' + (latest.serviceWorkerScope || ''));
    lines.push('');
    lines.push('Manifest: ' + (manifest?.href || latest.manifestHref || ''));
    lines.push('ID: ' + (manifest?.id || ''));
    lines.push('Start URL: ' + (manifest?.start_url || ''));
    lines.push('Scope: ' + (manifest?.scope || ''));
    lines.push('Display: ' + (manifest?.display || ''));
    if (manifest?.error) lines.push('Manifest error: ' + manifest.error);
    lines.push('');
    lines.push('TIMELINE');
    lines.push(...formatPwaTimeline(rows));
    return lines.join('\n');
  }

  function formatPwaTimeline(rows) {
    if (!rows.length) return ['(no log rows)'];
    return rows.map(row => {
      const d = row.time ? new Date(row.time) : null;
      const time = d && !Number.isNaN(d.getTime()) ? d.toLocaleTimeString('vi-VN', { hour12: false }) : '';
      const detail = [];
      if (row.stage) detail.push('state=' + row.stage);
      if (row.outcome) detail.push('outcome=' + row.outcome);
      if (Object.prototype.hasOwnProperty.call(row, 'hidden')) detail.push('hidden=' + row.hidden);
      if (row.error) detail.push('error=' + row.error);
      if (row.platform) detail.push('platform=' + row.platform);
      return time + ' ' + (row.message || '(event)') + (detail.length ? ' | ' + detail.join(' ') : '');
    });
  }

  async function copyPwaDebugReport() {
    const report = document.querySelector('#pwaDebugPanel [data-pwa-debug-report]');
    const text = report ? report.textContent : '';
    if (!text) return;
    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);
        notify('Da copy PWA debug', 'success');
        return;
      }
    } catch (_) {}
    const box = document.querySelector('#pwaDebugPanel [data-pwa-debug-copybox]');
    if (box) {
      box.value = text;
      box.removeAttribute('readonly');
      box.style.cssText = 'position:fixed;left:8px;right:8px;bottom:8px;width:calc(100% - 16px);height:180px;z-index:2147483001;background:#fff;color:#111;border:2px solid #2563eb;padding:8px';
      box.focus();
      box.select();
    }
  }

  function resetPwaDebugState() {
    try { localStorage.removeItem(pwaDebugStorageKey()); } catch (_) {}
    state.installOutcome = '';
    state.installError = '';
    state.appinstalledFired = false;
    state.installStage = state.deferredInstall ? 'AVAILABLE' : '';
    renderInstallControl();
    refreshPwaDebugPanel();
  }

  function showUpdateBanner(worker) {
    state.updateWorker = worker;
    if (document.querySelector('#pwaUpdateBanner')) return;
    const banner = document.createElement('div');
    banner.id = 'pwaUpdateBanner';
    banner.className = 'pwa-update-banner';
    banner.innerHTML = '<p>Có phiên bản mới của ứng dụng. Cập nhật ngay?</p><button class="btn btn-success btn-sm" type="button">Cập nhật</button>';
    banner.querySelector('button').addEventListener('click', applyServiceWorkerUpdate);
    document.body.appendChild(banner);
  }

  function applyServiceWorkerUpdate() {
    const worker = state.updateWorker || (state.registration && state.registration.waiting);
    if (!worker) {
      checkForServiceWorkerUpdate();
      return;
    }
    document.querySelector('#pwaUpdateBanner button')?.setAttribute('disabled', 'disabled');
    worker.postMessage({ type: 'SKIP_WAITING' });
  }

  async function updateNetworkStatus(forceSyncing) {
    const bar = document.querySelector('#pwaStatusBar');
    if (!bar) return;
    const queued = await getQueueEntries().catch(() => []);
    const syncing = forceSyncing || state.syncing;
    const offline = navigator.onLine === false;
    bar.classList.toggle('is-offline', offline);
    bar.classList.toggle('is-syncing', syncing);
    const text = bar.querySelector('[data-pwa-status-text]');
    const btn = bar.querySelector('[data-pwa-sync]');
    if (text) text.textContent = syncing ? 'Đang đồng bộ dữ liệu' : (offline ? 'Đang ngoại tuyến' : 'Đang trực tuyến') + (queued.length ? ` - ${queued.length} chờ đồng bộ` : '');
    if (btn) btn.classList.toggle('d-none', !queued.length || offline || syncing);
    document.body.classList.toggle('pwa-offline', offline);
  }

  function setOfflineReadonly(enabled) {
    document.body.classList.toggle('pwa-offline-readonly', !!enabled);
    if (enabled) notify('Đang xem dữ liệu đã lưu ngoại tuyến', 'warning');
  }

  function hydrateOnlineState() {
    updateNetworkStatus();
    window.TenantAppPWA = Object.assign(window.TenantAppPWA || {}, {
      installState,
      promptInstall,
      isStandalone,
      installDebug: installDebugSnapshot,
      refreshDebugPanel: refreshPwaDebugPanel,
      flushQueue: flushQueueSoon,
      clearUserData,
      checkForUpdate: checkForServiceWorkerUpdate,
      applyUpdate: applyServiceWorkerUpdate,
      queueCount: async () => (await getQueueEntries()).length,
      readApiCache: readApiResponse
    });
  }

  async function registerBackgroundSync() {
    try {
      const registration = state.registration || await navigator.serviceWorker.ready;
      if (registration && 'sync' in registration) await registration.sync.register(SYNC_TAG);
    } catch (_) {}
  }

  async function clearUserData() {
    try {
      if (navigator.serviceWorker.controller) navigator.serviceWorker.controller.postMessage({ type: 'CLEAR_PWA_DATA' });
      if (!state.db) state.db = await openDb();
      await Promise.all(['apiCache', 'syncQueue', 'settings'].map(clearStore));
      updateNetworkStatus();
    } catch (error) { warn('Clear PWA data failed', error); }
  }

  function openDb() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);
      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains('apiCache')) db.createObjectStore('apiCache', { keyPath: 'key' });
        if (!db.objectStoreNames.contains('syncQueue')) db.createObjectStore('syncQueue', { keyPath: 'id' }).createIndex('userId', 'userId', { unique: false });
        if (!db.objectStoreNames.contains('settings')) db.createObjectStore('settings', { keyPath: 'key' });
      };
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  async function storeApiResponse(bucket, key, payload) {
    await put('apiCache', { key, userId: currentUserId(), bucket, payload, updatedAt: Date.now() });
  }

  async function readApiResponse(key) { return get('apiCache', key); }
  async function addQueueEntry(entry) { await put('syncQueue', entry); }
  async function deleteQueueEntry(id) { await del('syncQueue', id); }
  async function getQueueEntries() {
    const rows = await all('syncQueue');
    const userId = currentUserId();
    return rows.filter(row => !row.userId || row.userId === userId).sort((a, b) => a.createdAt - b.createdAt);
  }

  async function txStore(storeName, mode) {
    if (!state.db) state.db = await openDb();
    return state.db.transaction(storeName, mode).objectStore(storeName);
  }
  async function put(storeName, value) { return requestPromise((await txStore(storeName, 'readwrite')).put(value)); }
  async function get(storeName, key) { return requestPromise((await txStore(storeName, 'readonly')).get(key)); }
  async function del(storeName, key) { return requestPromise((await txStore(storeName, 'readwrite')).delete(key)); }
  async function clearStore(storeName) { return requestPromise((await txStore(storeName, 'readwrite')).clear()); }
  async function all(storeName) { return requestPromise((await txStore(storeName, 'readonly')).getAll()); }
  function requestPromise(request) {
    return new Promise((resolve, reject) => {
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  function userKey(path) { return `${currentUserId() || 'anon'}:${path}`; }
  function currentUserId() {
    try { return String((window.App && App.user && App.user.id) || (JSON.parse(localStorage.getItem(tenantStorageKey('user')) || 'null') || {}).id || ''); } catch (_) { return ''; }
  }
  function isJsonResponse(response) { return (response.headers.get('content-type') || '').includes('application/json'); }
  function jsonResponse(payload, status, extraHeaders) {
    const headers = Object.assign({ 'Content-Type': 'application/json; charset=utf-8' }, extraHeaders || {});
    return new Response(JSON.stringify(payload), { status, headers });
  }
  function isStandalone() { return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true; }
  function isInstallInProgress() { return ['PROMPTING', 'ACCEPTED_WAITING'].includes(state.installStage); }
  function installDebugSnapshot() {
    const controller = !!navigator.serviceWorker?.controller;
    return {
      stage: state.installStage || installState(),
      outcome: state.installOutcome || '',
      error: state.installError || '',
      beforeInstallPromptAvailable: !!state.deferredInstall,
      appinstalledFired: state.appinstalledFired,
      displayModeStandalone: window.matchMedia('(display-mode: standalone)').matches,
      navigatorStandalone: window.navigator.standalone === true,
      serviceWorkerRegistered: !!state.registration,
      serviceWorkerActive: !!state.registration?.active,
      serviceWorkerControlling: controller,
      serviceWorkerScope: state.registration?.scope || '',
      location: location.href,
      manifestHref: document.querySelector('link[rel="manifest"]')?.href || '',
      userAgent: navigator.userAgent || '',
      time: new Date().toISOString()
    };
  }
  function debugInstall(message, detail) {
    const entry = Object.assign({ message, time: new Date().toISOString() }, installDebugSnapshot(), detail || {});
    try {
      const key = tenantStorageKey('pwaInstallDebug');
      const rows = JSON.parse(localStorage.getItem(key) || '[]');
      rows.push(entry);
      localStorage.setItem(key, JSON.stringify(rows.slice(-100)));
    } catch (_) {}
    if (window.console && typeof window.console.info === 'function') window.console.info('[PWA_INSTALL_DEBUG]', entry);
    document.dispatchEvent(new CustomEvent(tenantEventName('pwa-install-debug'), { detail: entry }));
  }
  function blobToDataUrl(blob) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = () => reject(reader.error);
      reader.readAsDataURL(blob);
    });
  }
  async function dataUrlToBlob(dataUrl) { return (await fetch(dataUrl)).blob(); }
  function notify(message, type) {
    if (typeof window.showToast === 'function') window.showToast(message, type || 'info');
  }
  function warn(message, error) {
    if (window.console && typeof window.console.warn === 'function') window.console.warn('[PWA] ' + message, error || '');
  }
})();
