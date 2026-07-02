(() => {
  const MAX_IMAGE_SIDE = 1280;
  const JPEG_QUALITY = 0.8;
  const IMAGE_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);
  const state = {
    initialized: false,
    selectedBlob: null,
    selectedName: '',
    selectedUrl: '',
    currentFile: null,
    pendingDelete: false,
    saving: false,
  };

  function $(selector, root = document) {
    return root.querySelector(selector);
  }

  function show(message, type = 'success') {
    if (typeof window.showToast === 'function') window.showToast(message, type);
    else if (type === 'danger') console.error(message);
    else console.log(message);
  }

  function authHeaders(extra = {}) {
    const headers = { Accept: 'application/json', ...extra };
    const token = window.App?.token || localStorage.getItem('thon09_token') || '';
    const csrf = window.App?.csrfToken || localStorage.getItem('thon09_csrf') || '';
    if (token) headers.Authorization = `Bearer ${token}`;
    if (csrf) headers['X-CSRF-Token'] = csrf;
    return headers;
  }

  function injectStyles() {
    if ($('#thon09-household-photo-style')) return;
    const style = document.createElement('style');
    style.id = 'thon09-household-photo-style';
    style.textContent = `
      .household-photo-capture-ui { border: 1px solid #d7e1dc; border-radius: 14px; padding: 14px; background: #f8fbf9; }
      .household-photo-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
      .household-photo-actions .btn { min-height: 42px; border-radius: 11px; font-weight: 600; }
      .household-photo-preview { min-height: 136px; border: 1px dashed #b9c9c1; border-radius: 14px; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; }
      .household-photo-preview img { width: 100%; max-height: 260px; object-fit: contain; display: block; background: #fff; }
      .household-photo-empty { color: #64748b; font-size: 14px; text-align: center; padding: 20px; }
      .household-photo-meta { margin-top: 8px; color: #64748b; font-size: 13px; }
      @media (max-width: 767px) {
        .household-photo-actions { display: grid; grid-template-columns: 1fr; }
        .household-photo-actions .btn { width: 100%; }
      }
    `;
    document.head.appendChild(style);
  }

  function revokeSelectedUrl() {
    if (state.selectedUrl) URL.revokeObjectURL(state.selectedUrl);
    state.selectedUrl = '';
  }

  function formatBytes(bytes) {
    const size = Number(bytes || 0);
    if (size >= 1024 * 1024) return `${(size / 1024 / 1024).toFixed(1)} MB`;
    return `${Math.max(1, Math.round(size / 1024))} KB`;
  }

  function householdCode(form) {
    return String(form?.elements?.householdCode?.value || 'house_new').trim().replace(/[^a-zA-Z0-9_-]+/g, '_') || 'house_new';
  }

  function timestamp() {
    const d = new Date();
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}_${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}`;
  }

  function fileName(form) {
    return `house_${householdCode(form)}_${timestamp()}.jpg`;
  }

  function renderPreview(src, meta = '') {
    const preview = $('#householdPhotoPreview');
    const metaEl = $('#householdPhotoMeta');
    const clearBtn = $('#householdPhotoClearBtn');
    if (!preview) return;
    if (src) {
      preview.innerHTML = `<img src="${src}" alt="Ảnh hộ" loading="lazy">`;
      if (clearBtn) clearBtn.classList.remove('d-none');
    } else {
      preview.innerHTML = '<div class="household-photo-empty">Chưa có ảnh hộ<br><small>Chụp ảnh hoặc chọn ảnh từ thư viện</small></div>';
      if (clearBtn) clearBtn.classList.add('d-none');
    }
    if (metaEl) metaEl.textContent = meta;
  }

  function clearSelectedPhoto() {
    revokeSelectedUrl();
    state.selectedBlob = null;
    state.selectedName = '';
    const input = $('#householdPhotoInput');
    if (input) input.value = '';
  }

  function clearAllPhotoState() {
    clearSelectedPhoto();
    state.currentFile = null;
    state.pendingDelete = false;
    renderPreview('', '');
  }

  async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
      ...options,
      headers: authHeaders(options.headers || {}),
      credentials: 'same-origin',
      cache: 'no-store',
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload?.ok) throw new Error(payload?.error?.message || 'Không nhận được phản hồi từ hệ thống');
    return payload.data;
  }

  function normalizeFileUrl(path) {
    const value = String(path || '').trim();
    if (!value) return '';
    if (/^https?:\/\//i.test(value) || value.startsWith('/')) return value;
    return `/${value.replace(/^\/+/, '')}`;
  }

  function latestPhoto(files) {
    return (Array.isArray(files) ? files : []).find(file => String(file.file_type || '').toUpperCase() === 'PHOTO' && String(file.mime_type || '').startsWith('image/')) || null;
  }

  async function loadExistingPhoto(entityId) {
    if (!entityId) {
      clearAllPhotoState();
      return;
    }
    clearAllPhotoState();
    try {
      const files = await fetchJson(`/api/files/household/${encodeURIComponent(entityId)}`);
      const photo = latestPhoto(files);
      if (photo) {
        state.currentFile = photo;
        renderPreview(normalizeFileUrl(photo.file_path), `Ảnh hiện tại: ${photo.original_name || photo.stored_name || ''}`.trim());
      }
    } catch (error) {
      show(`Không tải được ảnh hộ: ${error.message}`, 'danger');
    }
  }

  function drawImageToCanvas(source, width, height) {
    const scale = Math.min(1, MAX_IMAGE_SIDE / Math.max(width, height));
    const targetWidth = Math.max(1, Math.round(width * scale));
    const targetHeight = Math.max(1, Math.round(height * scale));
    const canvas = document.createElement('canvas');
    canvas.width = targetWidth;
    canvas.height = targetHeight;
    const ctx = canvas.getContext('2d', { alpha: false });
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, targetWidth, targetHeight);
    ctx.drawImage(source, 0, 0, targetWidth, targetHeight);
    return canvas;
  }

  function canvasToBlob(canvas) {
    return new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY));
  }

  async function compressImage(file) {
    let canvas;
    if ('createImageBitmap' in window) {
      try {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        canvas = drawImageToCanvas(bitmap, bitmap.width, bitmap.height);
        if (typeof bitmap.close === 'function') bitmap.close();
      } catch (_) {}
    }
    if (!canvas) {
      const url = URL.createObjectURL(file);
      try {
        const img = await new Promise((resolve, reject) => {
          const image = new Image();
          image.onload = () => resolve(image);
          image.onerror = () => reject(new Error('Không đọc được ảnh'));
          image.src = url;
        });
        canvas = drawImageToCanvas(img, img.naturalWidth || img.width, img.naturalHeight || img.height);
      } finally {
        URL.revokeObjectURL(url);
      }
    }
    const blob = await canvasToBlob(canvas);
    if (!blob) throw new Error('Không nén được ảnh');
    return blob;
  }

  async function handlePhotoFile(file, form) {
    if (!file) return;
    if (!IMAGE_TYPES.has(file.type)) {
      show('Ảnh hộ chỉ hỗ trợ JPG, PNG hoặc WebP.', 'danger');
      return;
    }
    try {
      renderPreview('', 'Đang tối ưu ảnh...');
      const blob = await compressImage(file);
      clearSelectedPhoto();
      state.selectedBlob = blob;
      state.selectedName = fileName(form);
      state.selectedUrl = URL.createObjectURL(blob);
      state.pendingDelete = !!state.currentFile;
      renderPreview(state.selectedUrl, `Ảnh mới đã tối ưu: ${formatBytes(file.size)} → ${formatBytes(blob.size)}`);
    } catch (error) {
      renderPreview(state.currentFile ? normalizeFileUrl(state.currentFile.file_path) : '', '');
      show(error.message || 'Không xử lý được ảnh', 'danger');
    }
  }

  async function uploadPhoto(entityId) {
    if (!entityId || !state.selectedBlob) return;
    const data = new FormData();
    data.append('module', 'household');
    data.append('entityId', String(entityId));
    data.append('fileType', 'PHOTO');
    data.append('file', state.selectedBlob, state.selectedName || `house_${entityId}_${timestamp()}.jpg`);
    const response = await fetch('/api/files/upload', {
      method: 'POST',
      headers: authHeaders(),
      credentials: 'same-origin',
      body: data,
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload?.ok) throw new Error(payload?.error?.message || 'Không upload được ảnh hộ');
    state.currentFile = payload.data || null;
    clearSelectedPhoto();
  }

  async function deleteCurrentPhoto() {
    if (!state.currentFile?.id) return;
    await fetchJson(`/api/files/${encodeURIComponent(state.currentFile.id)}`, { method: 'DELETE' });
    state.currentFile = null;
    state.pendingDelete = false;
  }

  function savedHouseholdId(id, saved) {
    return Number(id || saved?.id || saved?.household_id || saved?.data?.id || 0);
  }

  async function enhancedSaveHousehold(event) {
    if (state.saving) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const form = event.currentTarget;
    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      return;
    }
    const payload = typeof window.formData === 'function' ? window.formData(form) : Object.fromEntries(new FormData(form).entries());
    const id = payload.id;
    delete payload.id;
    delete payload.householdPhoto;

    state.saving = true;
    try {
      const saved = await window.api(id ? `/api/households/${id}` : '/api/households', { method: id ? 'PUT' : 'POST', body: payload });
      const entityId = savedHouseholdId(id, saved);
      if (entityId && state.pendingDelete && state.currentFile?.id) await deleteCurrentPhoto();
      if (entityId && state.selectedBlob) await uploadPhoto(entityId);
      window.App?.modals?.household?.hide();
      show('Đã lưu hộ dân');
      if (typeof window.loadHouseholds === 'function') window.loadHouseholds();
      if (typeof window.loadDashboard === 'function') window.loadDashboard();
      if (typeof window.refreshLoginConfig === 'function') window.refreshLoginConfig();
    } catch (error) {
      show(error.message, 'danger');
    } finally {
      state.saving = false;
    }
  }

  function enhanceHouseholdPhotoInput(form) {
    const input = form.querySelector('input[name="householdPhoto"]');
    if (!input || input.dataset.captureEnhanced === '1') return;
    input.dataset.captureEnhanced = '1';
    input.id = 'householdPhotoInput';
    input.classList.add('d-none');
    input.accept = 'image/jpeg,image/png,image/webp';

    const captureInput = document.createElement('input');
    captureInput.type = 'file';
    captureInput.accept = 'image/*';
    captureInput.capture = 'environment';
    captureInput.className = 'd-none';

    const libraryInput = document.createElement('input');
    libraryInput.type = 'file';
    libraryInput.accept = 'image/jpeg,image/png,image/webp';
    libraryInput.className = 'd-none';

    const ui = document.createElement('div');
    ui.className = 'household-photo-capture-ui';
    ui.innerHTML = `
      <div class="household-photo-actions">
        <button type="button" class="btn btn-success" id="householdPhotoCaptureBtn"><i class="fa-solid fa-camera"></i> Chụp ảnh</button>
        <button type="button" class="btn btn-outline-success" id="householdPhotoLibraryBtn"><i class="fa-solid fa-images"></i> Chọn từ thư viện</button>
        <button type="button" class="btn btn-outline-secondary" id="householdPhotoClearBtn"><i class="fa-solid fa-trash"></i> Xóa ảnh</button>
      </div>
      <div class="household-photo-preview" id="householdPhotoPreview"></div>
      <div class="household-photo-meta" id="householdPhotoMeta"></div>
    `;

    input.insertAdjacentElement('afterend', ui);
    ui.appendChild(captureInput);
    ui.appendChild(libraryInput);
    renderPreview('', '');

    $('#householdPhotoCaptureBtn', ui)?.addEventListener('click', () => {
      if (!navigator.mediaDevices && !/Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
        show('Thiết bị không hỗ trợ camera, vui lòng chọn ảnh từ thư viện.', 'warning');
        libraryInput.click();
        return;
      }
      captureInput.value = '';
      captureInput.click();
    });
    $('#householdPhotoLibraryBtn', ui)?.addEventListener('click', () => {
      libraryInput.value = '';
      libraryInput.click();
    });
    $('#householdPhotoClearBtn', ui)?.addEventListener('click', () => {
      clearSelectedPhoto();
      if (state.currentFile) state.pendingDelete = true;
      renderPreview('', state.currentFile ? 'Ảnh hiện tại sẽ được xóa khi bấm Lưu.' : '');
    });
    captureInput.addEventListener('change', () => handlePhotoFile(captureInput.files?.[0], form));
    libraryInput.addEventListener('change', () => handlePhotoFile(libraryInput.files?.[0], form));
  }

  function init() {
    const form = $('#householdForm');
    if (!form || state.initialized) return;
    state.initialized = true;
    injectStyles();
    enhanceHouseholdPhotoInput(form);
    form.addEventListener('submit', enhancedSaveHousehold, true);

    const modal = $('#householdModal');
    if (modal) {
      modal.addEventListener('shown.bs.modal', () => {
        const id = form.elements.id?.value || '';
        loadExistingPhoto(id);
      });
      modal.addEventListener('hidden.bs.modal', clearAllPhotoState);
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();