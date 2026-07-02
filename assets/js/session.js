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

  function loadScriptOnce(src, marker) {
    return new Promise((resolve, reject) => {
      if (document.querySelector('script[data-' + marker + ']')) {
        resolve();
        return;
      }
      const script = document.createElement('script');
      script.src = src;
      script.defer = true;
      script.dataset[marker.replace(/-([a-z])/g, (_, c) => c.toUpperCase())] = '1';
      script.onload = () => resolve();
      script.onerror = () => reject(new Error('Không tải được ' + src));
      document.head.appendChild(script);
    });
  }

  function loadFinalResponsiveOverrides() {
    return loadScriptOnce('assets/js/responsive-final-production.js?v=20260702-final-responsive-2', 'thon09-responsive-final').catch(() => {});
  }

  function loadFinalMobilePersonCards() {
    const run = async () => {
      try {
        await loadScriptOnce('assets/js/mobile-design-system.js?v=20260702-person-card-v1-2', 'thon09-mobile-design-system');
        await loadScriptOnce('assets/js/person-household-group-style.js?v=20260702-person-card-v1-2', 'thon09-person-household-groups');
        if (typeof window.thon09FitPopulationNames === 'function') window.thon09FitPopulationNames();
        if (window.App && window.App.screen === 'persons' && typeof window.loadPersons === 'function') window.loadPersons();
      } catch (error) {
        console.error('Không tải được giao diện nhân khẩu mobile cuối cùng', error);
      }
    };
    if (document.readyState === 'complete') run();
    else window.addEventListener('load', run, { once: true });
  }

  loadFinalResponsiveOverrides();
  loadFinalMobilePersonCards();

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
