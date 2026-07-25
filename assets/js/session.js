(() => {
  function authCookieAttributes(maxAge) {
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    return '; path=/; SameSite=Lax; max-age=' + Number(maxAge || 0) + secure;
  }

  function syncAuthCookie() {
    const token = App.token || localStorage.getItem(tenantStorageKey('token')) || '';
    if (!token) return clearAuthCookie();
    document.cookie = tenantStorageKey('token') + '=' + encodeURIComponent(token) + authCookieAttributes(21600);
  }

  function clearAuthCookie() {
    document.cookie = tenantStorageKey('token') + '=' + authCookieAttributes(0);
  }

  window.syncAuthCookie = syncAuthCookie;
  syncAuthCookie();

  function clearSession() {
    App.token = '';
    App.user = null;
    App.csrfToken = '';
    localStorage.removeItem(tenantStorageKey('token'));
    localStorage.removeItem(tenantStorageKey('user'));
    localStorage.removeItem(tenantStorageKey('csrf'));
    clearAuthCookie();
    showLogin();
  }

  window.clearClientSession = clearSession;

  window.logout = async function logout() {
    const token = App.token;
    const csrfToken = App.csrfToken || localStorage.getItem(tenantStorageKey('csrf')) || '';
    clearSession();
    if (!token) return;
    try {
      await fetch('/api/auth/logout', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
          'X-CSRF-Token': csrfToken,
        },
      });
    } catch (_) {}
  };
})();
