(function () {
  'use strict';

  function hasSession() {
    return !!(localStorage.getItem('thon09_token') || (window.App && App.token));
  }

  function setVisible(visible) {
    document.querySelector('.mobile-bottom-nav')?.classList.toggle('d-none', !visible);
    document.querySelector('.mobile-fab')?.classList.toggle('d-none', !visible);
    document.querySelector('.mobile-refresh-indicator')?.classList.toggle('d-none', !visible);
    if (!visible) {
      document.querySelector('.mobile-sheet-backdrop')?.classList.add('d-none');
      document.querySelectorAll('.mobile-bottom-sheet.is-open').forEach(panel => panel.classList.remove('is-open'));
      document.body.classList.remove('sidebar-open', 'mobile-screen-changing');
      document.querySelector('.sidebar')?.classList.remove('open');
    }
  }

  function sync() {
    setVisible(hasSession());
  }

  function patchStorage() {
    if (window.__thon09MobileAuthGateStorage) return;
    window.__thon09MobileAuthGateStorage = true;
    const originalSetItem = Storage.prototype.setItem;
    const originalRemoveItem = Storage.prototype.removeItem;

    Storage.prototype.setItem = function patchedSetItem(key, value) {
      const result = originalSetItem.apply(this, arguments);
      if (this === localStorage && key === 'thon09_token') window.setTimeout(sync, 0);
      return result;
    };

    Storage.prototype.removeItem = function patchedRemoveItem(key) {
      const result = originalRemoveItem.apply(this, arguments);
      if (this === localStorage && key === 'thon09_token') window.setTimeout(sync, 0);
      return result;
    };
  }

  patchStorage();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', sync);
  else sync();
  document.addEventListener('visibilitychange', sync);
  window.addEventListener('storage', event => {
    if (event.key === 'thon09_token') sync();
  });
})();
