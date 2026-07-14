(function () {
  'use strict';

  if (!('serviceWorker' in navigator)) return;
  var isSupportedContext = window.isSecureContext || ['localhost', '127.0.0.1'].includes(window.location.hostname);
  if (!isSupportedContext) return;

  window.addEventListener('load', function () {
    navigator.serviceWorker.register('/service-worker.js').catch(function (error) {
      if (window.console && typeof window.console.warn === 'function') {
        window.console.warn('[PWA] Service worker registration failed', error);
      }
    });
  });
})();
