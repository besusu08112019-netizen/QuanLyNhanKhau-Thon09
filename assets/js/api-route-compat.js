(function () {
  'use strict';

  if (window.__thon09ApiRouteCompat) return;
  window.__thon09ApiRouteCompat = true;

  var originalFetch = window.fetch;
  if (typeof originalFetch !== 'function') return;

  function rewriteUrl(input) {
    if (typeof input === 'string') {
      return input.replace(/\/api\/auth\/login(?=\?|$)/, '/api/login');
    }
    if (input && typeof input.url === 'string' && input.url.indexOf('/api/auth/login') !== -1) {
      var nextUrl = input.url.replace(/\/api\/auth\/login(?=\?|$)/, '/api/login');
      try { return new Request(nextUrl, input); } catch (error) { return input; }
    }
    return input;
  }

  window.fetch = function (input, init) {
    return originalFetch.call(this, rewriteUrl(input), init);
  };
})();
