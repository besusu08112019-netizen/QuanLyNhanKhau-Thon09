(() => {
  function clearSession() {
    App.token = '';
    App.user = null;
    localStorage.removeItem('thon09_token');
    localStorage.removeItem('thon09_user');
    showLogin();
  }

  window.logout = async function logout() {
    const token = App.token;
    clearSession();
    if (!token) return;
    try {
      await fetch('/api/auth/logout', { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
    } catch (_) {}
  };

  window.api = async function api(url, options = {}) {
    setLoading(true);
    try {
      const headers = { Accept: 'application/json' };
      if (options.body) headers['Content-Type'] = 'application/json';
      if (App.token && !options.public) headers.Authorization = `Bearer ${App.token}`;
      const response = await fetch(url, { method: options.method || 'GET', headers, body: options.body ? JSON.stringify(options.body) : undefined });
      const payload = await response.json().catch(() => null);
      if (response.status === 401 && !options.public) clearSession();
      if (!response.ok || !payload?.ok) throw new Error(payload?.error?.message || 'Không nhận được phản hồi từ hệ thống');
      return payload.data;
    } finally {
      setLoading(false);
    }
  };
})();
