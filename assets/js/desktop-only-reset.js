(function () {
  'use strict';

  function stripMobileTableState(root) {
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('.module-card-list').forEach(function (element) {
      element.classList.remove('module-card-list');
    });
    scope.querySelectorAll('[data-mobile-role], [data-mobile-tone], [data-mobile-empty], [data-mobile-action]').forEach(function (element) {
      delete element.dataset.mobileRole;
      delete element.dataset.mobileTone;
      delete element.dataset.mobileAction;
      element.removeAttribute('data-mobile-empty');
    });
  }

  function noop() {}

  window.markResponsiveTableWrappers = noop;
  window.applyResponsiveTableLabels = noop;
  window.startResponsiveTableObserver = noop;
  window.decorateMobileCardActions = noop;
  window.thon09FitPopulationNames = noop;
  window.thon09CompactPopulationCards = noop;
  window.thon09StripMobileTableState = stripMobileTableState;

  function runStrip() {
    stripMobileTableState(document);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runStrip, { once: true });
  } else {
    runStrip();
  }
})();

(function () {
  'use strict';

  function hasSession() {
    try {
      return Boolean(localStorage.getItem('thon09_token') || (window.App && window.App.token));
    } catch (error) {
      return Boolean(window.App && window.App.token);
    }
  }

  function setClass(element, className, enabled) {
    if (!element) return;
    if (element.classList.contains(className) !== enabled) {
      element.classList.toggle(className, enabled);
    }
  }

  function syncMobileChrome() {
    var visible = hasSession();
    setClass(document.querySelector('.mobile-bottom-nav'), 'd-none', !visible);
    setClass(document.querySelector('.mobile-fab'), 'd-none', !visible);
    setClass(document.querySelector('.mobile-refresh-indicator'), 'd-none', !visible);

    if (!visible) {
      setClass(document.querySelector('.mobile-sheet-backdrop'), 'd-none', true);
      document.querySelectorAll('.mobile-bottom-sheet.is-open').forEach(function (panel) {
        panel.classList.remove('is-open');
      });
      document.body.classList.remove('sidebar-open', 'mobile-screen-changing');
      var sidebar = document.querySelector('.sidebar');
      if (sidebar) sidebar.classList.remove('open');
    }
  }

  function queueSync() {
    if (window.__thon09MobileChromeSyncQueued) return;
    window.__thon09MobileChromeSyncQueued = true;
    window.requestAnimationFrame(function () {
      window.__thon09MobileChromeSyncQueued = false;
      syncMobileChrome();
    });
  }

  function patchStorage() {
    if (window.__thon09MobileChromeStoragePatch) return;
    window.__thon09MobileChromeStoragePatch = true;
    var setItem = Storage.prototype.setItem;
    var removeItem = Storage.prototype.removeItem;
    Storage.prototype.setItem = function patchedSetItem(key) {
      var result = setItem.apply(this, arguments);
      if (this === localStorage && key === 'thon09_token') queueSync();
      return result;
    };
    Storage.prototype.removeItem = function patchedRemoveItem(key) {
      var result = removeItem.apply(this, arguments);
      if (this === localStorage && key === 'thon09_token') queueSync();
      return result;
    };
  }

  function start() {
    patchStorage();
    syncMobileChrome();
    window.setTimeout(syncMobileChrome, 0);
    window.setTimeout(syncMobileChrome, 300);
  }

  window.thon09SyncMobileChrome = syncMobileChrome;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
  window.addEventListener('pageshow', queueSync);
  window.addEventListener('storage', function (event) {
    if (event.key === 'thon09_token') queueSync();
  });
  document.addEventListener('thon09:auth-state', queueSync);
})();
