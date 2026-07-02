(function () {
  'use strict';

  function fitPopulationNames() {
    if (window.innerWidth >= 1200) return;
    document.querySelectorAll('#personsScreen #personRows .population-card-name').forEach(function (name) {
      name.style.fontSize = '';
      var baseSize = parseFloat(window.getComputedStyle(name).fontSize) || 22;
      var minSize = window.innerWidth < 360 ? 10 : (window.innerWidth < 480 ? 11 : 13);
      var size = Math.min(28, baseSize);
      name.style.fontSize = size + 'px';
      var guard = 0;
      while (name.scrollWidth > name.clientWidth + 1 && size > minSize && guard < 48) {
        size -= 0.5;
        name.style.fontSize = size + 'px';
        guard += 1;
      }
    });
  }

  function bindPopulationNameObserver() {
    var rows = document.querySelector('#personRows');
    if (!rows || rows.__thon09NameFitObserver) return;
    rows.__thon09NameFitObserver = new MutationObserver(function () {
      window.requestAnimationFrame(fitPopulationNames);
      setTimeout(fitPopulationNames, 80);
    });
    rows.__thon09NameFitObserver.observe(rows, { childList: true, subtree: true });
  }

  function injectFinalResponsiveStyles() {
    var old = document.getElementById('thon09-responsive-final-production');
    if (old) old.remove();

    var style = document.createElement('style');
    style.id = 'thon09-responsive-final-production';
    style.textContent = [
      '@media (max-width: 1199px) {',
      '  html, body { max-width: 100% !important; overflow-x: hidden !important; }',
      '  #personsScreen #personRows .population-card,',
      '  #personsScreen #personRows .population-card * { box-sizing: border-box !important; }',
      '  #personsScreen #personRows .population-card { width: 100% !important; max-width: 100% !important; overflow: hidden !important; gap: clamp(8px, 1.9vw, 12px) !important; padding: clamp(12px, 3vw, 18px) !important; }',
      '  #personsScreen #personRows .population-card-head { display: grid !important; grid-template-columns: minmax(0, 58fr) minmax(0, 42fr) !important; align-items: center !important; gap: clamp(6px, 1.8vw, 10px) !important; }',
      '  #personsScreen #personRows .population-card-title-stack { min-width: 0 !important; width: 100% !important; overflow: hidden !important; }',
      '  #personsScreen #personRows .population-card-name { display: block !important; width: 100% !important; max-width: 100% !important; min-width: 0 !important; text-align: left !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: clip !important; word-break: normal !important; overflow-wrap: normal !important; font-size: clamp(10px, 3.25vw, 28px) !important; line-height: 1.18 !important; letter-spacing: 0 !important; }',
      '  #personsScreen #personRows .population-card-head-actions { display: grid !important; grid-template-columns: minmax(0, 1fr) auto !important; align-items: center !important; justify-content: end !important; gap: clamp(6px, 1.6vw, 8px) !important; min-width: 0 !important; overflow: hidden !important; }',
      '  #personsScreen #personRows .population-household-badge { justify-self: end !important; width: auto !important; max-width: 100% !important; min-width: 0 !important; height: clamp(32px, 7vw, 38px) !important; min-height: clamp(32px, 7vw, 38px) !important; padding: 0 clamp(7px, 2vw, 10px) !important; font-size: clamp(14px, 4vw, 18px) !important; line-height: 1 !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; }',
      '  #personsScreen #personRows .population-check { align-self: center !important; justify-self: end !important; width: clamp(20px, 5vw, 24px) !important; height: clamp(20px, 5vw, 24px) !important; margin: 0 !important; }',
      '  #personsScreen #personRows .population-code-grid { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: clamp(7px, 1.8vw, 10px) !important; }',
      '  #personsScreen #personRows .population-code-box { min-width: 0 !important; width: 100% !important; min-height: clamp(50px, 12vw, 62px) !important; padding: clamp(7px, 2vw, 10px) !important; display: grid !important; grid-template-columns: auto minmax(0, 1fr) !important; align-items: center !important; gap: clamp(6px, 1.8vw, 10px) !important; overflow: hidden !important; }',
      '  #personsScreen #personRows .population-code-box span,',
      '  #personsScreen #personRows .population-code-box strong { min-width: 0 !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; line-height: 1.15 !important; }',
      '  #personsScreen #personRows .population-code-box span { font-size: clamp(11px, 2.8vw, 14px) !important; }',
      '  #personsScreen #personRows .population-code-box strong { font-size: clamp(13px, 3.8vw, 18px) !important; }',
      '  #personsScreen #personRows .population-info-grid { display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: clamp(7px, 1.8vw, 10px) !important; }',
      '  #personsScreen #personRows .population-info-box { min-width: 0 !important; width: 100% !important; min-height: clamp(62px, 15vw, 78px) !important; padding: clamp(7px, 2vw, 10px) !important; display: grid !important; grid-template-columns: auto minmax(0, 1fr) !important; align-items: center !important; gap: clamp(7px, 1.8vw, 12px) !important; overflow: hidden !important; }',
      '  #personsScreen #personRows .population-info-box > div { min-width: 0 !important; overflow: hidden !important; }',
      '  #personsScreen #personRows .population-info-box span,',
      '  #personsScreen #personRows .population-info-box strong { display: block !important; min-width: 0 !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; word-break: keep-all !important; overflow-wrap: normal !important; line-height: 1.18 !important; }',
      '  #personsScreen #personRows .population-info-box span { font-size: clamp(10px, 2.7vw, 14px) !important; }',
      '  #personsScreen #personRows .population-info-box strong { font-size: clamp(13px, 3.7vw, 18px) !important; }',
      '  #personsScreen #personRows .population-card-icon { flex: 0 0 auto !important; font-size: clamp(17px, 4vw, 22px) !important; margin: 0 !important; }',
      '  #personsScreen #personRows .population-status-grid { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: clamp(7px, 1.8vw, 10px) !important; width: 100% !important; overflow: hidden !important; }',
      '  #personsScreen #personRows .population-status-field { min-width: 0 !important; width: 100% !important; min-height: clamp(48px, 11vw, 58px) !important; padding: clamp(7px, 1.8vw, 9px) clamp(7px, 2vw, 10px) !important; display: grid !important; grid-template-columns: minmax(52px, .52fr) minmax(0, .88fr) !important; align-items: center !important; justify-items: center !important; gap: clamp(5px, 1.4vw, 8px) !important; overflow: hidden !important; }',
      '  #personsScreen #personRows .population-status-field span { justify-self: start !important; min-width: 0 !important; max-width: 100% !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; word-break: keep-all !important; overflow-wrap: normal !important; font-size: clamp(11px, 2.9vw, 14px) !important; line-height: 1.15 !important; }',
      '  #personsScreen #personRows .population-status-badge { justify-self: center !important; max-width: 100% !important; width: auto !important; min-width: 0 !important; padding: 4px clamp(7px, 1.8vw, 10px) !important; border-radius: 999px !important; text-align: center !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; word-break: keep-all !important; overflow-wrap: normal !important; font-size: clamp(11px, 2.9vw, 14px) !important; line-height: 1.1 !important; }',
      '  #personsScreen #personRows .population-action-grid { display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: clamp(7px, 1.8vw, 10px) !important; width: 100% !important; }',
      '  #personsScreen #personRows .population-action { width: 100% !important; min-width: 0 !important; height: clamp(44px, 10vw, 48px) !important; min-height: clamp(44px, 10vw, 48px) !important; padding: 0 clamp(5px, 1.6vw, 8px) !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: clamp(5px, 1.4vw, 8px) !important; overflow: hidden !important; }',
      '  #personsScreen #personRows .population-action span { white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; font-size: clamp(12px, 3vw, 15px) !important; }',
      '  #personsScreen #personRows .population-action i { flex: 0 0 auto !important; font-size: clamp(16px, 4vw, 20px) !important; }',
      '  .table-responsive.module-card-list:not(.person-table-wrap),',
      '  .table-responsive.module-card-list:not(.person-table-wrap) * { box-sizing: border-box !important; max-width: 100% !important; }',
      '}',
      '@media (max-width: 479px) {',
      '  #personsScreen #personRows .population-card-head { grid-template-columns: minmax(0, 60fr) minmax(0, 40fr) !important; }',
      '  #personsScreen #personRows .population-code-box { grid-template-columns: 1fr !important; align-content: center !important; gap: 3px !important; }',
      '  #personsScreen #personRows .population-code-box strong { font-size: clamp(13px, 4vw, 17px) !important; }',
      '  #personsScreen #personRows .population-info-box { grid-template-columns: auto minmax(0, 1fr) !important; min-height: clamp(58px, 16vw, 70px) !important; }',
      '  #personsScreen #personRows .population-info-box span { font-size: clamp(10px, 2.8vw, 12px) !important; }',
      '  #personsScreen #personRows .population-info-box strong { font-size: clamp(12px, 3.7vw, 16px) !important; }',
      '  #personsScreen #personRows .population-status-field { grid-template-columns: minmax(46px, .5fr) minmax(0, .9fr) !important; }',
      '  #personsScreen #personRows .population-status-field span,',
      '  #personsScreen #personRows .population-status-badge { font-size: clamp(10px, 3vw, 13px) !important; }',
      '}',
      '@media (min-width: 1200px) {',
      '  #thon09-responsive-final-production-desktop-guard { display: none !important; }',
      '}',
    ].join('\n');
    document.head.appendChild(style);
  }

  function markLoaded() {
    document.documentElement.setAttribute('data-thon09-responsive-final', '2');
  }

  function bootFinalResponsive() {
    injectFinalResponsiveStyles();
    bindPopulationNameObserver();
    window.requestAnimationFrame(fitPopulationNames);
    setTimeout(fitPopulationNames, 80);
    markLoaded();
  }

  window.thon09FitPopulationNames = fitPopulationNames;
  bootFinalResponsive();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootFinalResponsive);
  }
  window.addEventListener('resize', function () { window.requestAnimationFrame(fitPopulationNames); });
  setTimeout(bootFinalResponsive, 800);
  setTimeout(bootFinalResponsive, 1800);
})();
