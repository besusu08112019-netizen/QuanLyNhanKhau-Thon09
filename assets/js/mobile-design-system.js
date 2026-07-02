(function () {
  'use strict';

  const CSS_HREFS = [
    'assets/css/admin-design-system.css?v=20260701-admin-ds-2',
    'assets/css/person-card-layout.css?v=20260702-person-card-mobile-v1',
    'assets/css/gis-household-location.css?v=20260702-sprint15-3'
  ];
  const JS_SRC = 'assets/js/admin-design-system.js?v=20260701-admin-ds-1';
  const GROUP_JS_SRC = 'assets/js/person-household-group-style.js?v=20260702-person-final-ui';
  const GIS_LOCATION_JS_SRC = 'assets/js/gis-household-location.js?v=20260702-sprint15-3';
  const PERSON_DESKTOP_FIELDS_JS_SRC = 'assets/js/person-desktop-extra-fields.js?v=20260702-desktop-person-fields-2';

  function ensureScript(src, fileName) {
    const existing = document.querySelector('script[src*="' + fileName + '"]');
    if (existing) {
      const current = existing.getAttribute('src') || '';
      if (current !== src) existing.setAttribute('src', src);
      return;
    }
    const script = document.createElement('script');
    script.src = src;
    script.defer = true;
    document.body.appendChild(script);
  }

  function ensureSharedDesignAssets() {
    CSS_HREFS.forEach(href => {
      const fileName = href.split('?')[0].split('/').pop();
      const existing = document.querySelector('link[href*="' + fileName + '"]');
      if (existing) {
        const current = existing.getAttribute('href') || '';
        if (current !== href) existing.setAttribute('href', href);
        return;
      }
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = href;
      document.head.appendChild(link);
    });

    ensureScript(JS_SRC, 'admin-design-system.js');
    ensureScript(GROUP_JS_SRC, 'person-household-group-style.js');
    ensureScript(GIS_LOCATION_JS_SRC, 'gis-household-location.js');
    ensureScript(PERSON_DESKTOP_FIELDS_JS_SRC, 'person-desktop-extra-fields.js');
  }

  function fitPopulationNames() {
    if (window.innerWidth >= 1200) return;
    document.querySelectorAll('#personsScreen #personRows .population-card-name').forEach(name => {
      name.style.removeProperty('font-size');
      const baseSize = parseFloat(window.getComputedStyle(name).fontSize) || 22;
      const minSize = window.innerWidth < 360 ? 13 : (window.innerWidth < 480 ? 14 : 15);
      let size = Math.min(28, baseSize);
      name.style.setProperty('font-size', size + 'px', 'important');
      let guard = 0;
      while (name.scrollWidth > name.clientWidth + 1 && size > minSize && guard < 60) {
        size -= 0.5;
        name.style.setProperty('font-size', size + 'px', 'important');
        guard += 1;
      }
    });
  }

  function bindPopulationNameFit() {
    const rows = document.querySelector('#personRows');
    if (!rows || rows.__thon09MobileNameFitObserver) return;
    rows.__thon09MobileNameFitObserver = new MutationObserver(() => {
      window.requestAnimationFrame(fitPopulationNames);
      setTimeout(fitPopulationNames, 80);
    });
    rows.__thon09MobileNameFitObserver.observe(rows, { childList: true, subtree: true });
  }

  function start() {
    ensureSharedDesignAssets();
    bindPopulationNameFit();
    window.thon09FitPopulationNames = fitPopulationNames;
    window.requestAnimationFrame(fitPopulationNames);
    setTimeout(fitPopulationNames, 100);
    if (!window.__thon09PopulationNameFitResizeBound) {
      window.__thon09PopulationNameFitResizeBound = true;
      window.addEventListener('resize', () => window.requestAnimationFrame(fitPopulationNames));
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
