(function () {
  if (window.__TenantAppHouseholdPhotoGpsLoaded) return;
  window.__TenantAppHouseholdPhotoGpsLoaded = true;

  const state = {
    lastPosition: null,
    lastRequestAt: 0,
    requesting: false,
  };

  function toast(message, type = 'info') {
    if (typeof window.toast === 'function') {
      window.toast(message, type);
    } else if (typeof window.showToast === 'function') {
      window.showToast(message, type);
    } else {
      console[type === 'error' ? 'error' : 'log'](message);
    }
  }

  function formOf(element) {
    return element?.closest?.('form') || document.getElementById('householdForm') || document.querySelector('form[data-household-form]');
  }

  function ensureField(form, name) {
    if (!form) return null;
    let input = form.querySelector(`[name="${name}"]`);
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      form.appendChild(input);
    }
    return input;
  }

  function setValue(form, names, value) {
    names.forEach((name) => {
      const input = ensureField(form, name);
      if (input) {
        input.value = value == null ? '' : String(value);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }

  function writePosition(form, position) {
    if (!form || !position?.coords) return;
    const coords = position.coords;
    const latitude = Number(coords.latitude).toFixed(8);
    const longitude = Number(coords.longitude).toFixed(8);
    const accuracy = coords.accuracy != null ? Math.round(Number(coords.accuracy)) : '';

    setValue(form, ['latitude'], latitude);
    setValue(form, ['longitude'], longitude);
    setValue(form, ['locationAccuracy', 'location_accuracy'], accuracy);
    setValue(form, ['locationSource', 'location_source'], 'GPS');

    state.lastPosition = {
      latitude,
      longitude,
      accuracy,
      source: 'GPS',
      capturedAt: new Date().toISOString(),
    };
    window.__TenantAppLastHouseholdPhotoGps = state.lastPosition;

    const sourceSelect = form.querySelector('[name="locationSource"], [name="location_source"]');
    if (sourceSelect) sourceSelect.value = 'GPS';
  }

  function prepareHouseholdPhotoInput() {
    const form = document.getElementById('householdForm');
    if (!form) return;

    const input = form.querySelector('input[type="file"][name="householdPhoto"]');
    if (!input) return;

    if (!input.id) input.id = 'householdPhoto';
    if (!input.accept) input.accept = 'image/*';

    if (input.dataset.TenantAppPhotoPrepared === '1') return;
    input.dataset.TenantAppPhotoPrepared = '1';

    if (typeof window.TenantAppEnhanceHouseholdPhotoCapture === 'function') {
      window.TenantAppEnhanceHouseholdPhotoCapture();
    }
  }

  function observeHouseholdPhotoInput() {
    prepareHouseholdPhotoInput();

    document.addEventListener('shown.bs.modal', (event) => {
      if (event.target?.id === 'householdModal') {
        setTimeout(prepareHouseholdPhotoInput, 30);
      }
    });
  }

  function requestCurrentPosition(form) {
    if (!window.isSecureContext) {
      toast('GPS chá»‰ hoáº¡t Ä‘á»™ng trÃªn HTTPS hoáº·c localhost.', 'warning');
      return;
    }
    if (!navigator.geolocation) {
      toast('Thiáº¿t bá»‹ khÃ´ng há»— trá»£ GPS.', 'warning');
      return;
    }
    if (state.requesting) return;

    const now = Date.now();
    if (state.lastPosition && now - state.lastRequestAt < 15000) {
      writePosition(form, { coords: state.lastPosition });
      return;
    }

    state.requesting = true;
    state.lastRequestAt = now;
    navigator.geolocation.getCurrentPosition(
      (position) => {
        state.requesting = false;
        writePosition(form, position);
        const accuracy = position.coords.accuracy != null ? Math.round(position.coords.accuracy) : null;
        toast(accuracy ? `ÄÃ£ gáº¯n GPS cho áº£nh há»™ (Â±${accuracy} m).` : 'ÄÃ£ gáº¯n GPS cho áº£nh há»™.', 'success');
      },
      (error) => {
        state.requesting = false;
        const messages = {
          1: 'Báº¡n Ä‘Ã£ tá»« chá»‘i quyá»n GPS. áº¢nh váº«n Ä‘Æ°á»£c chá»¥p nhÆ°ng chÆ°a cÃ³ tá»a Ä‘á»™.',
          2: 'KhÃ´ng láº¥y Ä‘Æ°á»£c vá»‹ trÃ­ hiá»‡n táº¡i. Vui lÃ²ng thá»­ láº¡i ngoÃ i trá»i hoáº·c nháº­p tá»a Ä‘á»™ thá»§ cÃ´ng.',
          3: 'Láº¥y GPS quÃ¡ thá»i gian. áº¢nh váº«n Ä‘Æ°á»£c chá»¥p nhÆ°ng chÆ°a cÃ³ tá»a Ä‘á»™.',
        };
        toast(messages[error.code] || 'KhÃ´ng láº¥y Ä‘Æ°á»£c GPS cho áº£nh há»™.', 'warning');
      },
      {
        enableHighAccuracy: true,
        timeout: 30000,
        maximumAge: 0,
      }
    );
  }

  window.TenantAppRequestHouseholdPhotoGps = function TenantAppRequestHouseholdPhotoGps(target) {
    requestCurrentPosition(formOf(target));
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', observeHouseholdPhotoInput);
  } else {
    observeHouseholdPhotoInput();
  }
})();
