(function () {
  if (window.__thon09HouseholdPhotoCaptureLoaded) return;
  window.__thon09HouseholdPhotoCaptureLoaded = true;

  const MAX_SIDE = 1280;
  const JPEG_QUALITY = 0.8;
  const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
  let currentFile = null;
  let removeExisting = false;

  function qs(selector, root = document) {
    return root.querySelector(selector);
  }

  function toast(message, type = 'info') {
    if (typeof window.toast === 'function') {
      window.toast(message, type);
    } else if (typeof window.showToast === 'function') {
      window.showToast(message, type);
    } else {
      console[type === 'error' ? 'error' : 'log'](message);
    }
  }

  function authHeaders(extra = {}) {
    const headers = Object.assign({ Accept: 'application/json' }, extra);
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      || window.csrfToken
      || window.CSRF_TOKEN
      || '';
    if (token) headers['X-CSRF-TOKEN'] = token;
    const bearer = window.App?.token || localStorage.getItem('thon09_token') || '';
    if (bearer) headers.Authorization = 'Bearer ' + bearer;
    return headers;
  }

  async function fetchJson(url, options = {}) {
    const response = await fetch(url, Object.assign({ credentials: 'same-origin' }, options, {
      headers: authHeaders(options.headers || {}),
    }));
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok === false) {
      throw new Error(data.error?.message || data.message || 'Yêu cầu không thành công');
    }
    return data;
  }

  function installStyles() {
    if (document.getElementById('household-photo-capture-style')) return;
    const style = document.createElement('style');
    style.id = 'household-photo-capture-style';
    style.textContent = `
      .household-photo-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:8px}.household-photo-actions .btn{min-height:42px}.household-photo-preview{margin-top:10px;border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc;min-height:120px;display:grid;place-items:center;overflow:hidden}.household-photo-preview img{max-width:100%;max-height:220px;object-fit:contain;border-radius:12px}.household-photo-preview.is-empty{color:#64748b;font-weight:700}.household-photo-current{display:flex;gap:12px;align-items:center;margin-top:8px;padding:10px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.household-photo-current img{width:72px;height:72px;object-fit:cover;border-radius:10px}.household-photo-current span{font-size:13px;color:#475569}
      @media (max-width:767px){.household-photo-actions .btn{flex:1 1 100%}.household-photo-preview img{max-height:180px}}
    `;
    document.head.appendChild(style);
  }

  function isHouseholdFormVisible() {
    const modal = document.getElementById('householdModal');
    return modal && !modal.classList.contains('d-none') && getComputedStyle(modal).display !== 'none';
  }

  function fileNameForHousehold(code, ext = 'jpg') {
    const safeCode = String(code || 'unknown').replace(/[^A-Za-z0-9_-]/g, '_');
    const now = new Date();
    const stamp = [
      now.getFullYear(),
      String(now.getMonth() + 1).padStart(2, '0'),
      String(now.getDate()).padStart(2, '0'),
      '_',
      String(now.getHours()).padStart(2, '0'),
      String(now.getMinutes()).padStart(2, '0'),
      String(now.getSeconds()).padStart(2, '0'),
    ].join('');
    return `house_${safeCode}_${stamp}.${ext}`;
  }

  function loadImage(file) {
    return new Promise((resolve, reject) => {
      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = () => {
        URL.revokeObjectURL(url);
        resolve(img);
      };
      img.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('Không đọc được ảnh'));
      };
      img.src = url;
    });
  }

  async function optimizeImage(file, householdCode) {
    if (!ALLOWED_TYPES.includes(file.type)) {
      throw new Error('Chỉ cho phép ảnh JPG, PNG hoặc WebP');
    }
    const img = await loadImage(file);
    const scale = Math.min(1, MAX_SIDE / Math.max(img.width, img.height));
    const width = Math.max(1, Math.round(img.width * scale));
    const height = Math.max(1, Math.round(img.height * scale));
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0, width, height);
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY));
    if (!blob) throw new Error('Không tối ưu được ảnh');
    return new File([blob], fileNameForHousehold(householdCode, 'jpg'), { type: 'image/jpeg' });
  }

  function currentHouseholdCode() {
    return qs('#householdCode')?.value || qs('[name="household_code"]')?.value || qs('[name="householdCode"]')?.value || '';
  }

  function renderPreview(file) {
    const preview = document.getElementById('householdPhotoPreview');
    if (!preview) return;
    preview.classList.remove('is-empty');
    const url = URL.createObjectURL(file);
    preview.innerHTML = `<img src="${url}" alt="Ảnh hộ xem trước">`;
  }

  async function handleFile(file) {
    if (!file) return;
    try {
      currentFile = await optimizeImage(file, currentHouseholdCode());
      removeExisting = false;
      renderPreview(currentFile);
      toast('Đã chuẩn bị ảnh hộ, ảnh sẽ được lưu khi bấm Lưu.', 'success');
    } catch (error) {
      currentFile = null;
      toast(error.message || 'Không xử lý được ảnh', 'error');
    }
  }

  function getHouseholdIdFromForm() {
    return qs('#householdId')?.value || qs('[name="id"]')?.value || '';
  }

  function householdLocationPayload(form) {
    if (!form) return null;
    const latitude = form.querySelector('[name="latitude"]')?.value?.trim();
    const longitude = form.querySelector('[name="longitude"]')?.value?.trim();
    if (!latitude || !longitude) return null;
    return {
      latitude,
      longitude,
      accuracy: form.querySelector('[name="locationAccuracy"], [name="location_accuracy"]')?.value || null,
      source: form.querySelector('[name="locationSource"], [name="location_source"]')?.value || 'GPS',
    };
  }

  async function saveGpsIfAvailable(entityId, form) {
    const payload = householdLocationPayload(form);
    if (!entityId || !payload) return;
    try {
      await fetchJson(`/api/gis/households/${entityId}/location`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      if (typeof window.thon09LoadGisHouseholdMarkers === 'function') {
        window.thon09LoadGisHouseholdMarkers('', { force: true });
      }
    } catch (error) {
      console.warn('[Household Photo GPS] Không lưu được tọa độ ảnh hộ', error);
      toast(error.message || 'Ảnh đã lưu nhưng chưa lưu được tọa độ GPS.', 'warning');
    }
  }

  async function uploadPhoto(entityId) {
    if (!currentFile || !entityId) return;
    const formData = new FormData();
    formData.append('module', 'household');
    formData.append('entityId', String(entityId));
    formData.append('fileType', 'PHOTO');
    formData.append('file', currentFile);
    await fetchJson('/api/files', {
      method: 'POST',
      body: formData,
    });
    currentFile = null;
  }

  async function deletePhoto(entityId) {
    if (!removeExisting || !entityId) return;
    const files = await fetchJson('/api/files?module=household&entityId=' + encodeURIComponent(entityId));
    const photos = (files.data || files || []).filter(file => String(file.file_type || '').toUpperCase() === 'PHOTO');
    for (const file of photos) {
      await fetchJson('/api/files/' + encodeURIComponent(file.id), { method: 'DELETE' });
    }
    removeExisting = false;
  }
  function enhancePhotoField() {
    if (!isHouseholdFormVisible()) return;
    const fileInput = qs('#householdPhoto') || qs('[name="photo"]') || qs('input[type="file"][accept*="image"]');
    if (!fileInput || fileInput.dataset.enhancedPhotoCapture === '1') return;
    fileInput.dataset.enhancedPhotoCapture = '1';
    installStyles();

    fileInput.style.display = 'none';
    fileInput.accept = 'image/jpeg,image/png,image/webp';

    const captureInput = document.createElement('input');
    captureInput.type = 'file';
    captureInput.accept = 'image/*';
    captureInput.capture = 'environment';
    captureInput.style.display = 'none';

    const wrapper = document.createElement('div');
    wrapper.className = 'household-photo-widget';
    wrapper.innerHTML = `
      <div class="household-photo-actions">
        <button type="button" class="btn btn-success" id="householdPhotoCaptureBtn" data-household-photo-capture><i class="fas fa-camera me-1"></i> Chụp ảnh</button>
        <button type="button" class="btn btn-outline-success" id="householdPhotoLibraryBtn"><i class="fas fa-image me-1"></i> Chọn từ thư viện</button>
        <button type="button" class="btn btn-outline-danger" id="householdPhotoRemoveBtn"><i class="fas fa-trash me-1"></i> Xóa ảnh</button>
      </div>
      <div class="household-photo-preview is-empty" id="householdPhotoPreview">Ảnh hộ xem trước</div>
    `;

    fileInput.insertAdjacentElement('afterend', wrapper);
    wrapper.appendChild(captureInput);

    qs('#householdPhotoCaptureBtn', wrapper).addEventListener('click', () => captureInput.click());
    qs('#householdPhotoLibraryBtn', wrapper).addEventListener('click', () => fileInput.click());
    qs('#householdPhotoRemoveBtn', wrapper).addEventListener('click', () => {
      currentFile = null;
      removeExisting = true;
      const preview = qs('#householdPhotoPreview', wrapper);
      preview.classList.add('is-empty');
      preview.innerHTML = 'Ảnh hộ sẽ được xóa khi bấm Lưu';
    });

    fileInput.addEventListener('change', () => handleFile(fileInput.files && fileInput.files[0]));
    captureInput.addEventListener('change', () => handleFile(captureInput.files && captureInput.files[0]));
    document.dispatchEvent(new CustomEvent('thon09:household-photo-ready'));
  }

  async function enhancedSaveHousehold(originalHandler, event) {
    event.preventDefault();
    event.stopImmediatePropagation();
    const form = event.target;
    const idBefore = getHouseholdIdFromForm();
    try {
      if (typeof originalHandler === 'function') {
        await originalHandler.call(form, event);
      } else if (typeof window.saveHousehold === 'function') {
        await window.saveHousehold(event);
      } else {
        const payload = Object.fromEntries(new FormData(form).entries());
        delete payload.photo;
        delete payload.householdPhoto;
        const id = idBefore || payload.id;
        const method = id ? 'PUT' : 'POST';
        const url = id ? `/api/households/${id}` : '/api/households';
        const saved = await fetchJson(url, {
          method,
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        if (saved.data?.id && qs('#householdId')) qs('#householdId').value = saved.data.id;
      }

      const entityId = getHouseholdIdFromForm() || idBefore;
      await saveGpsIfAvailable(entityId, form);
      await deletePhoto(entityId);
      await uploadPhoto(entityId);
      if (currentFile || removeExisting) toast('Đã cập nhật ảnh hộ.', 'success');
    } catch (error) {
      toast(error.message || 'Không lưu được ảnh hộ', 'error');
    }
  }

  function hookHouseholdForm() {
    const form = qs('#householdForm');
    if (!form || form.dataset.photoCaptureSubmitHook === '1') return;
    form.dataset.photoCaptureSubmitHook = '1';
    form.addEventListener('submit', enhancedSaveHousehold, true);
  }

  function boot() {
    enhancePhotoField();
    hookHouseholdForm();
  }

  window.thon09EnhanceHouseholdPhotoCapture = boot;

  document.addEventListener('shown.bs.modal', event => {
    if (event.target?.id !== 'householdModal') return;
    window.setTimeout(boot, 30);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
