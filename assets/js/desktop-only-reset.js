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

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      stripMobileTableState(document);
    }, { once: true });
  } else {
    stripMobileTableState(document);
  }

  var observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      mutation.addedNodes.forEach(function (node) {
        if (node.nodeType === 1) stripMobileTableState(node);
      });
    });
  });

  if (document.body) {
    observer.observe(document.body, { childList: true, subtree: true });
  } else {
    document.addEventListener('DOMContentLoaded', function () {
      observer.observe(document.body, { childList: true, subtree: true });
    }, { once: true });
  }
})();

(function () {
  'use strict';

  function hasSession() {
    return !!(localStorage.getItem('thon09_token') || (window.App && window.App.token));
  }

  function setVisible(visible) {
    document.querySelector('.mobile-bottom-nav')?.classList.toggle('d-none', !visible);
    document.querySelector('.mobile-fab')?.classList.toggle('d-none', !visible);
    document.querySelector('.mobile-refresh-indicator')?.classList.toggle('d-none', !visible);
    if (!visible) {
      document.querySelector('.mobile-sheet-backdrop')?.classList.add('d-none');
      document.querySelectorAll('.mobile-bottom-sheet.is-open').forEach(function (panel) {
        panel.classList.remove('is-open');
      });
      document.body.classList.remove('sidebar-open', 'mobile-screen-changing');
      document.querySelector('.sidebar')?.classList.remove('open');
    }
  }

  function syncMobileChrome() {
    setVisible(hasSession());
  }

  function observeMobileChrome() {
    var target = document.body || document.documentElement;
    if (!target || window.__thon09MobileAuthGateObserver) return;
    window.__thon09MobileAuthGateObserver = true;
    var chromeObserver = new MutationObserver(syncMobileChrome);
    chromeObserver.observe(target, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
  }

  function patchStorage() {
    if (window.__thon09MobileAuthGateStorage) return;
    window.__thon09MobileAuthGateStorage = true;
    var originalSetItem = Storage.prototype.setItem;
    var originalRemoveItem = Storage.prototype.removeItem;

    Storage.prototype.setItem = function patchedSetItem(key, value) {
      var result = originalSetItem.apply(this, arguments);
      if (this === localStorage && key === 'thon09_token') window.setTimeout(syncMobileChrome, 0);
      return result;
    };

    Storage.prototype.removeItem = function patchedRemoveItem(key) {
      var result = originalRemoveItem.apply(this, arguments);
      if (this === localStorage && key === 'thon09_token') window.setTimeout(syncMobileChrome, 0);
      return result;
    };
  }

  patchStorage();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      observeMobileChrome();
      syncMobileChrome();
    });
  } else {
    observeMobileChrome();
    syncMobileChrome();
  }
  document.addEventListener('visibilitychange', syncMobileChrome);
  window.addEventListener('storage', function (event) {
    if (event.key === 'thon09_token') syncMobileChrome();
  });
})();
