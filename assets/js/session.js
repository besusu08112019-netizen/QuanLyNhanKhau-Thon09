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

  function loadHouseholdPhotoCapture() {
    return loadScriptOnce('assets/js/household-photo-capture.js?v=20260703-sprint16-1', 'thon09-household-photo-capture')
      .then(() => loadScriptOnce('assets/js/household-photo-camera-fix.js?v=20260703-sprint16-1', 'thon09-household-photo-camera-fix'))
      .catch(error => {
        console.error('Không tải được chức năng chụp ảnh hộ', error);
      });
  }

  loadHouseholdPhotoCapture();

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