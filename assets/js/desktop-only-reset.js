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