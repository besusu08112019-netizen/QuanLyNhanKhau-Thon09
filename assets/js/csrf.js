(() => {
  App.csrfToken = localStorage.getItem('thon09_csrf') || '';

  window.api = async function secureApi(url, options = {}) {
    setLoading(true);
    try {
      const method = String(options.method || 'GET').toUpperCase();
      const headers = { Accept: 'application/json' };
      const isFormData = options.body instanceof FormData;

      if (options.body && !isFormData) {
        headers['Content-Type'] = 'application/json';
      }
      if (App.token && !options.public) {
        headers.Authorization = `Bearer ${App.token}`;
      }
      if (!options.public && !['GET', 'HEAD', 'OPTIONS'].includes(method) && App.csrfToken) {
        headers['X-CSRF-Token'] = App.csrfToken;
      }

      const response = await fetch(url, {
        method,
        headers,
        body: options.body ? (isFormData ? options.body : JSON.stringify(options.body)) : undefined,
      });
      const payload = await response.json().catch(() => null);

      if (payload?.data?.csrfToken) {
        App.csrfToken = payload.data.csrfToken;
        localStorage.setItem('thon09_csrf', App.csrfToken);
      }
      if (response.status === 401 && !options.public && !String(url).includes('/api/auth/logout')) {
        clearClientSession();
        showLogin();
      }
      if (!response.ok || !payload?.ok) {
        throw new Error(payload?.error?.message || 'Không nhận được phản hồi từ hệ thống');
      }
      return payload.data;
    } finally {
      setLoading(false);
    }
  };

  const originalLogout = window.logout;
  window.logout = async function secureLogout() {
    try {
      return await originalLogout();
    } finally {
      clearClientSession();
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
})();
