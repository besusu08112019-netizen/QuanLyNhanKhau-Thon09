(function () {
  'use strict';

  const API = '/api/photo-gallery';
  const state = { ready: false, page: 1, pageSize: 24, search: '', album_id: '', tag: '', area_code: '', source_module: '', date_from: '', date_to: '', catalogs: null, current: null };
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const safe = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  const number = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
  const text = (value, empty = '--') => String(value ?? '').trim() || empty;
  const toast = (message, type = 'info') => typeof window.showToast === 'function' ? window.showToast(message, type) : console[type === 'danger' ? 'error' : 'log'](message);
  const run = fn => Promise.resolve().then(fn).catch(error => toast(error.message || 'Thao tÃ¡c khÃ´ng thÃ nh cÃ´ng', 'danger'));
  const debounce = (fn, delay) => { let timer; return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); }; };

  const can = action => {
    const service = window.TenantAppPlatform?.permissions;
    if (service?.can) return service.can('photo_gallery', action, window.App?.user);
    return typeof window.TenantAppCanAccess === 'function' ? window.TenantAppCanAccess('photo_gallery', action) : true;
  };

  const openModal = id => window.TenantAppPlatform?.modals?.open?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.show();
  const closeModal = id => window.TenantAppPlatform?.modals?.close?.(id) || window.bootstrap?.Modal?.getOrCreateInstance?.($('#' + id))?.hide();
  const authHeaders = () => { const h = {}; if (window.App?.token) h.Authorization = `Bearer ${window.App.token}`; if (window.App?.csrfToken) h['X-CSRF-Token'] = window.App.csrfToken; return h; };

  async function loadAuthorizedImage(url) {
    const response = await fetch(url, { headers: authHeaders(), cache: 'no-store' });
    if (!response.ok) throw new Error('Image preview failed');
    return URL.createObjectURL(await response.blob());
  }

  function hydrateImages(root = document) {
    $$('[data-photo-gallery-image]', root).forEach(async element => {
      if (element.dataset.loaded === '1') return;
      element.dataset.loaded = '1';
      try {
        const objectUrl = await loadAuthorizedImage(element.dataset.photoGalleryImage || '');
        element.src = objectUrl;
        element.addEventListener('load', () => setTimeout(() => URL.revokeObjectURL(objectUrl), 60000), { once: true });
      } catch (_) {
        element.removeAttribute('src');
        element.alt = 'KhÃ´ng táº£i Ä‘Æ°á»£c áº£nh';
      }
    });
  }

  async function request(url, options = {}) {
    if (typeof window.api === 'function' && !(options.body instanceof FormData)) return window.api(url, options);
    const headers = { Accept: 'application/json', ...authHeaders() };
    const init = { method: options.method || 'GET', headers };
    if (options.body instanceof FormData) init.body = options.body;
    else if (options.body) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(options.body); }
    const response = await fetch(url, init);
    const payload = await response.json().catch(() => null);
    if (!response.ok || payload?.ok === false || payload?.success === false) throw new Error(payload?.error?.message || payload?.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c dá»¯ liá»‡u');
    return payload?.data ?? payload;
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
  document.addEventListener('tenant:screen-change', event => { if (event.detail?.screen === 'photoGallery') run(load); });

  function init() {
    registerActions();
    if ($('#photoGalleryScreen')?.classList.contains('active') || window.App?.screen === 'photoGallery') run(load);
  }

  function registerActions() {
    if (window.__TenantAppPhotoGalleryActionsRegistered || !window.TenantAppPlatform?.actions) return;
    window.__TenantAppPhotoGalleryActionsRegistered = true;
    window.TenantAppPlatform.actions
      .register({ key: 'photoGallery.upload', handler: () => run(openUpload) })
      .register({ key: 'photoGallery.album.create', handler: () => run(openAlbum) })
      .register({ key: 'photoGallery.detail', handler: ({ dataset }) => run(() => openDetail(Number(dataset.id || 0))) })
      .register({ key: 'photoGallery.edit', handler: ({ dataset }) => run(() => openEdit(Number(dataset.id || 0))) })
      .register({ key: 'photoGallery.delete', handler: ({ dataset }) => run(() => remove(Number(dataset.id || 0))) })
      .register({ key: 'photoGallery.reset', handler: () => run(reset) })
      .register({ key: 'photoGallery.page', handler: ({ dataset, target }) => !target.disabled && run(() => { state.page = Number(dataset.page || 1); return load(); }) });
  }

  function shell() {
    const host = $('#photoGalleryScreen');
    if (!host || state.ready) return;
    host.classList.remove('module-placeholder-screen');
    host.innerHTML = [
      '<section id="photoGalleryDashboard" class="agri-kpi-grid" aria-label="Thá»‘ng kÃª kho áº£nh"></section>',
      '<section class="agri-filter-card" aria-label="Bá»™ lá»c kho áº£nh"><div class="agri-filter-row">',
      '<div class="agri-field agri-search-field"><label for="photoGallerySearch">TÃ¬m kiáº¿m</label><div class="module-search-input-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="photoGallerySearch" class="form-control" placeholder="TÃªn áº£nh, mÃ´ táº£, album, tag..."></div></div>',
      '<div class="agri-field"><label for="photoGalleryAlbumFilter">Album</label><select id="photoGalleryAlbumFilter" class="form-select"></select></div>',
      '<div class="agri-field"><label for="photoGalleryTagFilter">Tag</label><select id="photoGalleryTagFilter" class="form-select"></select></div>',
      '<div class="agri-field"><label for="photoGallerySourceFilter">Nguá»“n</label><select id="photoGallerySourceFilter" class="form-select"></select></div>',
      '<div class="agri-field"><label for="photoGalleryAreaFilter">Äá»‹a bÃ n</label><input id="photoGalleryAreaFilter" class="form-control" placeholder="MÃ£ khu vá»±c"></div>',
      '<div class="agri-field"><label for="photoGalleryDateFrom">Tá»« ngÃ y</label><input id="photoGalleryDateFrom" type="date" class="form-control"></div>',
      '<div class="agri-field"><label for="photoGalleryDateTo">Äáº¿n ngÃ y</label><input id="photoGalleryDateTo" type="date" class="form-control"></div>',
      '<div class="agri-field module-page-size-field"><label for="photoGalleryPageSize">Hiá»ƒn thá»‹</label><select id="photoGalleryPageSize" class="form-select"><option>24</option><option>48</option><option>96</option></select></div>',
      '<div class="agri-field agri-actions"><button class="btn btn-outline-secondary" type="button" data-platform-action="photoGallery.reset"><i class="fa-solid fa-rotate-right"></i></button><button class="btn btn-outline-primary" type="button" data-platform-action="photoGallery.album.create"><i class="fa-solid fa-folder-plus"></i> Album</button><button class="btn btn-success" type="button" data-platform-action="photoGallery.upload"><i class="fa-solid fa-cloud-arrow-up"></i> Táº£i áº£nh</button></div>',
      '</div></section>',
      '<section class="module-list-card household-list-card"><div class="module-list-head"><div><h3>Kho áº£nh</h3><span id="photoGalleryTotalCount">Tá»•ng sá»‘: 0 áº£nh</span></div></div><div id="photoGalleryLoadState" class="text-muted small px-3 pb-2 d-none"></div><div id="photoGalleryGrid" class="photo-gallery-grid"></div><div id="photoGalleryPager" class="pager module-pager"></div></section>',
      uploadModal(),
      albumModal(),
      detailModal()
    ].join('');
    bind();
    state.ready = true;
  }

  function uploadModal() {
    return '<div class="modal fade" id="photoGalleryUploadModal" tabindex="-1"><div class="modal-dialog modal-lg"><form id="photoGalleryUploadForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">Táº£i áº£nh lÃªn kho</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-6"><label class="form-label">Album</label><select name="album_id" id="photoGalleryAlbumInput" class="form-select"></select></div><div class="col-md-6"><label class="form-label">NgÃ y sá»± kiá»‡n</label><input name="event_date" type="date" class="form-control"></div><div class="col-md-6"><label class="form-label">Nguá»“n áº£nh</label><select name="source_module" id="photoGallerySourceInput" class="form-select"></select></div><div class="col-md-6"><label class="form-label">Äá»‹a bÃ n</label><input name="area_code" class="form-control"></div><div class="col-12"><label class="form-label">TiÃªu Ä‘á»</label><input name="title" class="form-control" maxlength="255"></div><div class="col-12"><label class="form-label">Tag</label><input name="tags" class="form-control" placeholder="há»™i nghá»‹, cÃ´ng trÃ¬nh, Ä‘oÃ n thá»ƒ"></div><div class="col-12"><label class="form-label">MÃ´ táº£</label><textarea name="description" class="form-control" rows="3"></textarea></div><div class="col-12"><label class="form-label">áº¢nh</label><input name="files" type="file" class="form-control" accept="image/jpeg,image/png,image/webp" multiple required><div class="form-text">Há»— trá»£ JPG, PNG, WEBP. Tá»‘i Ä‘a theo cáº¥u hÃ¬nh upload há»‡ thá»‘ng.</div></div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ÄÃ³ng</button><button class="btn btn-primary" type="submit">Táº£i lÃªn</button></div></form></div></div>';
  }

  function albumModal() {
    return '<div class="modal fade" id="photoGalleryAlbumModal" tabindex="-1"><div class="modal-dialog"><form id="photoGalleryAlbumForm" class="modal-content"><div class="modal-header"><h5 class="modal-title">Album áº£nh</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="form-label">TÃªn album</label><input name="name" class="form-control mb-3" required maxlength="255"><label class="form-label">MÃ´ táº£</label><textarea name="description" class="form-control" rows="3"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ÄÃ³ng</button><button class="btn btn-primary" type="submit">LÆ°u album</button></div></form></div></div>';
  }

  function detailModal() {
    return '<div class="modal fade" id="photoGalleryDetailModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><h5 id="photoGalleryDetailTitle" class="modal-title">Chi tiáº¿t áº£nh</h5><small id="photoGalleryDetailSub" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div id="photoGalleryDetailBody" class="modal-body"></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">ÄÃ³ng</button></div></div></div></div>';
  }

  function bind() {
    $('#photoGallerySearch')?.addEventListener('input', debounce(() => run(() => { state.page = 1; readFilters(); return load(); }), 350));
    ['photoGalleryAlbumFilter','photoGalleryTagFilter','photoGallerySourceFilter','photoGalleryAreaFilter','photoGalleryDateFrom','photoGalleryDateTo'].forEach(id => $('#' + id)?.addEventListener('change', () => run(() => { state.page = 1; readFilters(); return load(); })));
    $('#photoGalleryPageSize')?.addEventListener('change', event => run(() => { state.pageSize = Number(event.target.value || 24); state.page = 1; return load(); }));
    $('#photoGalleryUploadForm')?.addEventListener('submit', event => { event.preventDefault(); run(() => upload(event.currentTarget)); });
    $('#photoGalleryAlbumForm')?.addEventListener('submit', event => { event.preventDefault(); run(() => saveAlbum(event.currentTarget)); });
  }

  async function catalogs(force = false) {
    if (state.catalogs && !force) return state.catalogs;
    state.catalogs = await request(API + '/catalogs', { cacheTtl: 60000 });
    fill($('#photoGalleryAlbumFilter'), state.catalogs.albums, 'Táº¥t cáº£');
    fill($('#photoGalleryAlbumInput'), state.catalogs.albums, 'ChÆ°a phÃ¢n loáº¡i');
    fill($('#photoGalleryTagFilter'), state.catalogs.tags, 'Táº¥t cáº£');
    fill($('#photoGallerySourceFilter'), state.catalogs.sources, 'Táº¥t cáº£');
    fill($('#photoGallerySourceInput'), state.catalogs.sources, 'Chá»n nguá»“n');
    return state.catalogs;
  }

  function fill(select, items = [], first = '') {
    if (!select) return;
    const current = select.value;
    select.innerHTML = first ? `<option value="">${safe(first)}</option>` : '';
    items.forEach(item => { const option = document.createElement('option'); option.value = item.value; option.textContent = item.label; select.appendChild(option); });
    if ([...select.options].some(option => option.value === current)) select.value = current;
  }

  function readFilters() {
    state.search = $('#photoGallerySearch')?.value.trim() || '';
    state.album_id = $('#photoGalleryAlbumFilter')?.value || '';
    state.tag = $('#photoGalleryTagFilter')?.value || '';
    state.source_module = $('#photoGallerySourceFilter')?.value || '';
    state.area_code = $('#photoGalleryAreaFilter')?.value.trim() || '';
    state.date_from = $('#photoGalleryDateFrom')?.value || '';
    state.date_to = $('#photoGalleryDateTo')?.value || '';
    state.pageSize = Number($('#photoGalleryPageSize')?.value || state.pageSize || 24);
  }

  function params() {
    readFilters();
    return new URLSearchParams({ page: state.page, pageSize: state.pageSize, search: state.search, album_id: state.album_id, tag: state.tag, source_module: state.source_module, area_code: state.area_code, date_from: state.date_from, date_to: state.date_to });
  }

  function setLoading(on, message = '') {
    const el = $('#photoGalleryLoadState');
    if (!el) return;
    el.classList.toggle('d-none', !on && !message);
    el.textContent = on ? (message || 'Dang tai du lieu...') : message;
  }

  async function load() {
    if (!$('#photoGalleryScreen')) return;
    registerActions();
    shell();
    setLoading(true);
    try {
      await catalogs();
      const query = params();
      const [list, dashboard] = await Promise.all([request(API + '?' + query), request(API + '/dashboard?' + query, { cacheTtl: 15000 })]);
      renderDashboard(dashboard);
      renderGrid(list);
      renderPager(list);
      setLoading(false, '');
      window.TenantAppApplyAccessControls?.();
    } catch (error) {
      renderDashboard({});
      renderGrid({ items: [], total: 0 });
      renderPager({});
      setLoading(false, error.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c kho áº£nh');
      toast(error.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c kho áº£nh', 'danger');
    }
  }

  function renderDashboard(data = {}) {
    const metrics = data.metrics || {};
    const cards = [['Tá»•ng áº£nh', 'fa-images', metrics.total_photos || 0], ['ChÆ°a phÃ¢n loáº¡i', 'fa-folder-open', metrics.unclassified_photos || 0], ['30 ngÃ y gáº§n Ä‘Ã¢y', 'fa-clock', metrics.recent_photos || 0], ['Dung lÆ°á»£ng', 'fa-hard-drive', bytes(metrics.total_size || 0)]];
    $('#photoGalleryDashboard').innerHTML = cards.map(card => `<article class="agri-kpi-card"><span><i class="fa-solid ${card[1]}"></i></span><div><strong>${typeof card[2] === 'number' ? number(card[2]) : safe(card[2])}</strong><small>${safe(card[0])}</small></div></article>`).join('');
  }

  function renderGrid(data = {}) {
    const rows = data.items || [];
    $('#photoGalleryTotalCount').textContent = `Tá»•ng sá»‘: ${number(data.total || 0)} áº£nh`;
    const host = $('#photoGalleryGrid');
    if (!rows.length) { host.innerHTML = '<div class="text-center text-muted py-4">ChÆ°a cÃ³ áº£nh phÃ¹ há»£p bá»™ lá»c.</div>'; return; }
    host.innerHTML = rows.map(item => {
      const tags = (item.tags || []).slice(0, 4).map(tag => `<span class="badge bg-light text-dark border">${safe(tag)}</span>`).join(' ');
      const actions = [`<button class="btn btn-sm btn-light" type="button" data-platform-action="photoGallery.detail" data-id="${item.id}" title="Xem"><i class="fa-solid fa-eye"></i></button>`, can('update') ? `<button class="btn btn-sm btn-light" type="button" data-platform-action="photoGallery.edit" data-id="${item.id}" title="Sá»­a"><i class="fa-solid fa-pen"></i></button>` : '', can('delete') ? `<button class="btn btn-sm btn-light text-danger" type="button" data-platform-action="photoGallery.delete" data-id="${item.id}" title="XÃ³a"><i class="fa-solid fa-trash"></i></button>` : ''].filter(Boolean).join('');
      return `<article class="photo-gallery-card"><button type="button" class="photo-gallery-thumb" data-platform-action="photoGallery.detail" data-id="${item.id}"><img data-photo-gallery-image="${safe(item.preview_url)}" alt="${safe(item.title)}" loading="lazy"></button><div class="photo-gallery-meta"><strong>${safe(text(item.title))}</strong><small>${safe(text(item.album_name, 'ChÆ°a phÃ¢n loáº¡i'))} Â· ${safe(text(item.event_date, 'KhÃ´ng ngÃ y'))}</small><div class="photo-gallery-tags">${tags}</div><div class="photo-gallery-actions">${actions}</div></div></article>`;
    }).join('');
    hydrateImages(host);
  }

  function renderPager(data = {}) {
    const totalPages = Number(data.totalPages || 1), page = Number(data.page || state.page || 1);
    state.page = page;
    $('#photoGalleryPager').innerHTML = totalPages <= 1 ? '' : `<div class="d-flex gap-2 justify-content-end flex-wrap"><button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="photoGallery.page" data-page="${Math.max(1, page - 1)}" ${page <= 1 ? 'disabled' : ''}>TrÆ°á»›c</button><span class="px-2">${page} / ${totalPages}</span><button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="photoGallery.page" data-page="${Math.min(totalPages, page + 1)}" ${page >= totalPages ? 'disabled' : ''}>Sau</button></div>`;
  }

  async function openUpload() {
    if (!can('upload')) return toast('KhÃ´ng cÃ³ quyá»n táº£i áº£nh', 'warning');
    shell();
    const form = $('#photoGalleryUploadForm');
    if (form) {
      form.reset();
      delete form.dataset.editId;
      const fileInput = form.querySelector('[name="files"]');
      if (fileInput) fileInput.required = true;
    }
    openModal('photoGalleryUploadModal');
    await catalogs(true);
  }

  async function upload(form) {
    const editId = form.dataset.editId || '';
    if (editId) {
      const data = Object.fromEntries(new FormData(form).entries());
      delete data.files;
      await request(API + '/' + editId, { method: 'PUT', body: data });
      delete form.dataset.editId;
      form.querySelector('[name="files"]').required = true;
      closeModal('photoGalleryUploadModal');
      toast('ÄÃ£ cáº­p nháº­t thÃ´ng tin áº£nh', 'success');
      state.catalogs = null;
      await load();
      return;
    }
    const body = new FormData(form);
    await request(API + '/upload', { method: 'POST', body });
    closeModal('photoGalleryUploadModal');
    toast('ÄÃ£ táº£i áº£nh lÃªn kho', 'success');
    state.catalogs = null;
    await load();
  }

  function openAlbum() {
    if (!can('create')) return toast('KhÃ´ng cÃ³ quyá»n thÃªm album', 'warning');
    $('#photoGalleryAlbumForm')?.reset();
    openModal('photoGalleryAlbumModal');
  }

  async function saveAlbum(form) {
    await request(API + '/albums', { method: 'POST', body: Object.fromEntries(new FormData(form).entries()) });
    closeModal('photoGalleryAlbumModal');
    toast('ÄÃ£ lÆ°u album', 'success');
    state.catalogs = null;
    await catalogs(true);
  }

  async function openDetail(id) {
    const item = await request(API + '/' + id);
    state.current = item;
    $('#photoGalleryDetailTitle').textContent = item.title || 'Chi tiáº¿t áº£nh';
    $('#photoGalleryDetailSub').textContent = [item.album_name, item.event_date, item.area_code].filter(Boolean).join(' Â· ');
    $('#photoGalleryDetailBody').innerHTML = `<div class="row g-3"><div class="col-lg-8"><img class="img-fluid rounded border w-100" data-photo-gallery-image="${safe(item.preview_url)}" alt="${safe(item.title)}"></div><div class="col-lg-4"><div class="mb-2"><strong>Album</strong><div>${safe(text(item.album_name, 'ChÆ°a phÃ¢n loáº¡i'))}</div></div><div class="mb-2"><strong>Tag</strong><div>${safe(text(item.tags_text))}</div></div><div class="mb-2"><strong>Nguá»“n</strong><div>${safe(text(item.source_module))}</div></div><div class="mb-2"><strong>Dung lÆ°á»£ng</strong><div>${bytes(item.file_size)}</div></div><div class="mb-2"><strong>MÃ´ táº£</strong><p>${safe(text(item.description))}</p></div></div></div>`;
    hydrateImages($('#photoGalleryDetailBody'));
    openModal('photoGalleryDetailModal');
  }

  async function openEdit(id) {
    const item = await request(API + '/' + id);
    await openUpload();
    const form = $('#photoGalleryUploadForm');
    if (!form) return;
    form.dataset.editId = String(id);
    Object.entries(item).forEach(([key, value]) => { if (form.elements[key]) form.elements[key].value = Array.isArray(value) ? value.join(', ') : value ?? ''; });
    form.querySelector('[name="files"]').required = false;
  }

  async function remove(id) {
    if (!can('delete')) return toast('KhÃ´ng cÃ³ quyá»n xÃ³a áº£nh', 'warning');
    if (!window.confirm('XÃ³a áº£nh nÃ y khá»i kho?')) return;
    await request(API + '/' + id, { method: 'DELETE' });
    toast('ÄÃ£ xÃ³a áº£nh', 'success');
    await load();
  }

  async function reset() {
    ['photoGallerySearch','photoGalleryAlbumFilter','photoGalleryTagFilter','photoGallerySourceFilter','photoGalleryAreaFilter','photoGalleryDateFrom','photoGalleryDateTo'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
    Object.assign(state, { page: 1, search: '', album_id: '', tag: '', area_code: '', source_module: '', date_from: '', date_to: '' });
    await load();
  }

  function bytes(value) {
    const n = Number(value || 0);
    if (n >= 1024 * 1024) return number((n / 1024 / 1024).toFixed(1)) + ' MB';
    if (n >= 1024) return number((n / 1024).toFixed(1)) + ' KB';
    return number(n) + ' B';
  }

  window.loadPhotoGallery = load;
})();
