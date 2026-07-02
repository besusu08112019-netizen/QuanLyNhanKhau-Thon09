(() => {
  function clearSession() {
    App.token = '';
    App.user = null;
    App.csrfToken = '';
    localStorage.removeItem('thon09_token');
    localStorage.removeItem('thon09_user');
    localStorage.removeItem('thon09_csrf');
    showLogin();
  }

  function loadFinalResponsiveOverrides() {
    if (document.querySelector('script[data-thon09-responsive-final]')) return;
    const script = document.createElement('script');
    script.src = 'assets/js/responsive-final-production.js?v=20260702-final-responsive-2';
    script.defer = true;
    script.dataset.thon09ResponsiveFinal = '1';
    document.head.appendChild(script);
  }

  loadFinalResponsiveOverrides();

  window.clearClientSession = clearSession;

  window.logout = async function logout() {
    const token = App.token;
    const csrfToken = App.csrfToken || localStorage.getItem('thon09_csrf') || '';
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
