(function () {
  'use strict';

  const APP_NAME = 'Quản lý Nhân khẩu Thôn 09';
  const DB_NAME = 'thon09-pwa';
  const UPDATE_CHECK_INTERVAL_MS = 30 * 60 * 1000;
  const DB_VERSION = 1;
  const SYNC_TAG = 'thon09-background-sync';
  const CACHEABLE_API = [
    [/^\/api\/dashboard/, 'dashboard'],
    [/^\/api\/households(?:\/|\?|$)/, 'households'],
    [/^\/api\/persons(?:\/|\?|$)/, 'persons'],
    [/^\/api\/gis(?:\/|\?|$)/, 'gis'],
    [/^\/api\/public-assets(?:\/|\?|$)/, 'public_assets'],
    [/^\/api\/reports(?:\/|\?|$)/, 'reports'],
    [/^\/api\/settings(?:\/|\?|$)/, 'settings'],
    [/^\/api\/permissions(?:\/|\?|$)/, 'lookups']
  ];
  const QUEUEABLE_API = /^\/api\/(?:households|persons|gis|files|public-assets|reports|household-business|livestock|agriculture|houses)(?:\/|\?|$)/;
  const state = { db: null, deferredInstall: null, registration: null, refreshing: false, syncing: false, updateWorker: null, updateTimer: null };

  if (!('serviceWorker' in navigator)) {
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
      showInstallButton();
    });
    window.addEventListener('appinstalled', () => {
      state.deferredInstall = null;
      hideInstallButton();
      notify('Ứng dụng đã được cài đặt', 'success');
    });
    navigator.serviceWorker.addEventListener('message', event => {
      const type = event.data && event.data.type;
      if (type === 'PWA_SYNC_REQUESTED') flushQueueSoon();
      if (type === 'PWA_READY') document.dispatchEvent(new CustomEvent('thon09:pwa-ready', { detail: event.data }));
      if (type === 'PWA_UPDATED') document.dispatchEvent(new CustomEvent('thon09:pwa-updated', { detail: event.data }));
      if (type === 'PWA_CACHE_CLEARED') document.dispatchEvent(new CustomEvent('thon09:pwa-cache-cleared'));
    });
    document.addEventListener('thon09:auth-state', event => {
      if (!event.detail || event.detail.authenticated === false) clearUserData();
    });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) checkForServiceWorkerUpdate(); });
    window.addEventListener('focus', checkForServiceWorkerUpdate);
  }

  async function registerServiceWorker() {
    const isSupportedContext = window.isSecureContext || ['localhost', '127.0.0.1'].includes(location.hostname);
    if (!isSupportedContext) return;
    try {
      state.registration = await navigator.serviceWorker.register('/service-worker.js', { scope: '/', updateViaCache: 'none' });
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
        state.refreshing = true;
        location.reload();
      });
      await checkForServiceWorkerUpdate();
      state.updateTimer = window.setInterval(checkForServiceWorkerUpdate, UPDATE_CHECK_INTERVAL_MS);
    } catch (error) {
      warn('Service worker registration failed', error);
    }
  }

  async function checkForServiceWorkerUpdate() {
    if (!state.registration || document.hidden) return;
    try {
      await state.registration.update();
      if (state.registration.waiting && navigator.serviceWorker.controller) showUpdateBanner(state.registration.waiting);
    } catch (error) {
      warn('Service worker update check failed', error);
    }
  }

  function patchFetch() {
    if (window.__thon09PwaFetchPatched) return;
    window.__thon09PwaFetchPatched = true;
    const nativeFetch = window.fetch.bind(window);
    window.__thon09PwaNativeFetch = nativeFetch;
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
      return jsonResponse(cached.payload, 200, { 'X-Thon09-Offline': '1' });
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
    const token = localStorage.getItem('thon09_token') || '';
    const csrf = localStorage.getItem('thon09_csrf') || '';
    if (token) headers.set('Authorization', `Bearer ${token}`);
    if (csrf) headers.set('X-CSRF-Token', csrf);
    const body = await deserializeBody(entry.body, headers);
    const nativeFetch = window.__thon09PwaNativeFetch || window.fetch.bind(window);
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
    updateNetworkStatus();
  }

  function showInstallButton() {
    if (isStandalone() || document.querySelector('#pwaInstallBtn')) return;
    const btn = document.createElement('button');
    btn.id = 'pwaInstallBtn';
    btn.className = 'btn btn-success pwa-install-btn';
    btn.type = 'button';
    btn.innerHTML = '<i class="fa-solid fa-download"></i> Cài đặt ứng dụng';
    btn.addEventListener('click', promptInstall);
    document.body.appendChild(btn);
  }

  function hideInstallButton() { document.querySelector('#pwaInstallBtn')?.remove(); }

  async function promptInstall() {
    if (!state.deferredInstall) return;
    state.deferredInstall.prompt();
    await state.deferredInstall.userChoice.catch(() => null);
    state.deferredInstall = null;
    hideInstallButton();
  }

  function showUpdateBanner(worker) {
    state.updateWorker = worker;
    if (document.querySelector('#pwaUpdateBanner')) return;
    const banner = document.createElement('div');
    banner.id = 'pwaUpdateBanner';
    banner.className = 'pwa-update-banner';
    banner.innerHTML = '<p>?? c? phi?n b?n m?i c?a ?ng d?ng Th?n 09. C?p nh?t ngay?</p><button class="btn btn-success btn-sm" type="button">C?p nh?t</button>';
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
    window.Thon09PWA = {
      flushQueue: flushQueueSoon,
      clearUserData,
      checkForUpdate: checkForServiceWorkerUpdate,
      applyUpdate: applyServiceWorkerUpdate,
      queueCount: async () => (await getQueueEntries()).length,
      readApiCache: readApiResponse
    };
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
    try { return String((window.App && App.user && App.user.id) || (JSON.parse(localStorage.getItem('thon09_user') || 'null') || {}).id || ''); } catch (_) { return ''; }
  }
  function isJsonResponse(response) { return (response.headers.get('content-type') || '').includes('application/json'); }
  function jsonResponse(payload, status, extraHeaders) {
    const headers = Object.assign({ 'Content-Type': 'application/json; charset=utf-8' }, extraHeaders || {});
    return new Response(JSON.stringify(payload), { status, headers });
  }
  function isStandalone() { return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true; }
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
