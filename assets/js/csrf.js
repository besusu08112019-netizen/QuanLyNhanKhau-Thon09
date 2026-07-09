(() => {
  App.csrfToken = localStorage.getItem('thon09_csrf') || App.csrfToken || '';
  const AUTH_REQUIRED_MESSAGE = 'PhiÃªn Ä‘Äƒng nháº­p Ä‘Ã£ háº¿t háº¡n, vui lÃ²ng Ä‘Äƒng nháº­p láº¡i';

  function redirectToLoginOnAuthFailure() {
    if (window.__thon09SessionExpired) return;
    window.__thon09SessionExpired = true;
    if (typeof clearClientSession === 'function') {
      clearClientSession();
    } else {
      App.token = '';
      App.user = null;
      App.csrfToken = '';
      localStorage.removeItem('thon09_token');
      localStorage.removeItem('thon09_user');
      localStorage.removeItem('thon09_csrf');
    }
    if (typeof showLogin === 'function') showLogin();
  }

  window.api = async function secureApi(url, options = {}) {
    setLoading(true);
    try {
      const method = String(options.method || 'GET').toUpperCase();
      const headers = { Accept: 'application/json' };
      const isFormData = options.body instanceof FormData;

      if (options.body && !isFormData) {
        headers['Content-Type'] = 'application/json';
      }
      if (!options.public && !App.token) {
        redirectToLoginOnAuthFailure();
        throw new Error(AUTH_REQUIRED_MESSAGE);
      }
      if (App.token && !options.public) {
        headers.Authorization = `Bearer ${App.token}`;
      }
      if (!options.public && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
        App.csrfToken = App.csrfToken || localStorage.getItem('thon09_csrf') || '';
        if (!App.csrfToken) {
          throw new Error('PhiÃªn Ä‘Äƒng nháº­p thiáº¿u CSRF token, vui lÃ²ng Ä‘Äƒng nháº­p láº¡i');
        }
        headers['X-CSRF-Token'] = App.csrfToken;
      }

      const response = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: options.body ? (isFormData ? options.body : JSON.stringify(options.body)) : undefined,
      });
      const payload = await response.json().catch(() => null);

      if (payload?.data?.csrfToken) {
        App.csrfToken = payload.data.csrfToken;
        localStorage.setItem('thon09_csrf', App.csrfToken);
      }
      if (response.status === 401 && !options.public && !String(url).includes('/api/auth/logout')) {
        redirectToLoginOnAuthFailure();
        throw new Error(AUTH_REQUIRED_MESSAGE);
      }
      if (!response.ok || !payload?.ok) {
        throw new Error(payload?.error?.message || 'KhÃ´ng nháº­n Ä‘Æ°á»£c pháº£n há»“i tá»« há»‡ thá»‘ng');
      }
      return payload.data;
    } finally {
      setLoading(false);
    }
  };

  function clearClientSession() {
    App.token = '';
    App.user = null;
    App.csrfToken = '';
    localStorage.removeItem('thon09_token');
    localStorage.removeItem('thon09_user');
    localStorage.removeItem('thon09_csrf');
  }

  window.clearClientSession = clearClientSession;
})();
