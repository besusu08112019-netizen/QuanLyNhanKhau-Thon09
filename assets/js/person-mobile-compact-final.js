(function () {
  'use strict';

  var STYLE_ID = 'thon09-person-mobile-compact-final-style';
  var OBSERVER_KEY = '__thon09PersonCompactObserver';

  function injectStyles() {
    if (document.getElementById(STYLE_ID)) return;
    var style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `
@media (max-width: 1199px) {
  :root {
    --thon09-mobile-card-radius: 14px;
    --thon09-mobile-field-radius: 10px;
    --thon09-mobile-card-gap: 7px;
    --thon09-mobile-field-gap: 6px;
  }

  #personsScreen table thead,
  #personsScreen #personRows .population-desktop-cell {
    display: none !important;
  }

  #personsScreen #personRows .population-row,
  #personsScreen #personRows .population-mobile-cell,
  #personsScreen #personRows .population-card {
    height: auto !important;
  }

  #personsScreen #personRows .population-mobile-cell {
    padding: 0 !important;
  }

  #personsScreen #personRows .population-card {
    gap: var(--thon09-mobile-card-gap) !important;
    margin: 0 0 13px !important;
    padding: 10px !important;
    border-radius: var(--thon09-mobile-card-radius) !important;
    box-shadow: 0 5px 14px rgba(15, 23, 42, 0.06) !important;
  }

  #personsScreen #personRows .population-card-head {
    grid-template-columns: minmax(0, 1fr) auto !important;
    align-items: center !important;
    gap: 8px !important;
    padding-bottom: 4px !important;
    min-height: 0 !important;
  }

  #personsScreen #personRows .population-card-title-stack {
    gap: 3px !important;
    min-width: 0 !important;
  }

  #personsScreen #personRows .population-card-name {
    display: inline-block !important;
    width: fit-content !important;
    max-width: 100% !important;
    min-width: 0 !important;
    padding: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    font-size: clamp(16px, 4.6vw, 18px) !important;
    font-weight: 650 !important;
    line-height: 1.08 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
  }

  #personsScreen #personRows .population-relation-badge {
    width: fit-content !important;
    max-width: 100% !important;
    padding: 2px 6px !important;
    border-radius: 9px !important;
    font-size: clamp(11px, 3vw, 13px) !important;
    line-height: 1.05 !important;
  }

  #personsScreen #personRows .population-card-head-actions {
    align-self: center !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
  }

  #personsScreen #personRows .population-check {
    width: clamp(24px, 6vw, 26px) !important;
    height: clamp(24px, 6vw, 26px) !important;
    margin: 0 !important;
    align-self: center !important;
  }

  #personsScreen #personRows .population-code-grid,
  #personsScreen #personRows .population-bio-grid,
  #personsScreen #personRows .population-context-grid {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: var(--thon09-mobile-field-gap) !important;
  }

  #personsScreen #personRows .population-code-box {
    min-height: 48px !important;
    padding: 7px 8px !important;
    border-radius: var(--thon09-mobile-field-radius) !important;
    gap: 3px !important;
  }

  #personsScreen #personRows .population-code-box span,
  #personsScreen #personRows .population-detail-label {
    font-size: clamp(11px, 2.75vw, 13px) !important;
    line-height: 1.1 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    word-break: keep-all !important;
  }

  #personsScreen #personRows .population-code-box strong,
  #personsScreen #personRows .population-detail-value,
  #personsScreen #personRows .population-status-pill {
    font-size: clamp(14px, 3.6vw, 18px) !important;
    line-height: 1.15 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    word-break: keep-all !important;
  }

  #personsScreen #personRows .population-detail-box {
    min-height: 52px !important;
    padding: 7px 8px !important;
    border-radius: var(--thon09-mobile-field-radius) !important;
    gap: 6px !important;
    align-items: center !important;
  }

  #personsScreen #personRows .population-card-icon {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 20px !important;
    min-width: 20px !important;
    height: 20px !important;
    font-size: 20px !important;
    line-height: 1 !important;
  }

  #personsScreen #personRows .population-detail-copy {
    min-width: 0 !important;
    gap: 2px !important;
  }

  #personsScreen #personRows .population-birth-age {
    display: none !important;
  }

  #personsScreen #personRows .population-birth-box .population-detail-value {
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
    overflow-wrap: normal !important;
  }

  #personsScreen #personRows .population-admin-grid {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) !important;
    gap: var(--thon09-mobile-field-gap) !important;
  }

  #personsScreen #personRows .population-residence-compact-final {
    min-height: 34px !important;
    grid-template-columns: auto minmax(0, auto) !important;
    justify-content: center !important;
    align-items: center !important;
    text-align: center !important;
    padding: 5px 10px !important;
  }

  #personsScreen #personRows .population-residence-compact-final .population-detail-copy {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-width: 0 !important;
  }

  #personsScreen #personRows .population-residence-compact-final .population-detail-label {
    display: none !important;
  }

  #personsScreen #personRows .population-residence-compact-final .population-status-pill {
    display: inline-block !important;
    width: auto !important;
    max-width: 100% !important;
    min-height: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
    border-radius: 0 !important;
    font-style: normal !important;
    text-align: center !important;
  }

  #personsScreen #personRows .population-action-grid {
    gap: 7px !important;
    margin-top: 1px !important;
    padding-top: 6px !important;
  }

  #personsScreen #personRows .population-action {
    min-height: 40px !important;
    height: clamp(40px, 9vw, 42px) !important;
    border-radius: 12px !important;
    font-size: clamp(13px, 3.3vw, 14px) !important;
    gap: 5px !important;
    padding: 0 8px !important;
    align-items: center !important;
    justify-content: center !important;
  }

  #personsScreen #personRows .population-action i {
    font-size: 18px !important;
    line-height: 1 !important;
  }

  #personsScreen #personRows .person-household-group-card {
    margin: 0 0 9px !important;
    padding: 9px 11px !important;
    border-radius: 12px !important;
    background: #f0faf4 !important;
  }

  #personsScreen #personRows .person-household-group-icon {
    width: 28px !important;
    height: 28px !important;
    font-size: 13px !important;
  }

  #personsScreen #personRows .person-household-group-count {
    padding: 4px 9px !important;
    font-size: clamp(10px, 2.8vw, 12px) !important;
  }
}

@media (max-width: 479px) {
  #personsScreen #personRows .population-card {
    gap: 6px !important;
    margin-bottom: 12px !important;
    padding: 9px !important;
  }

  #personsScreen #personRows .population-code-grid,
  #personsScreen #personRows .population-bio-grid,
  #personsScreen #personRows .population-context-grid {
    gap: 6px !important;
  }

  #personsScreen #personRows .population-detail-box {
    min-height: 50px !important;
    padding: 6px 7px !important;
  }

  #personsScreen #personRows .population-code-box {
    min-height: 46px !important;
    padding: 6px 7px !important;
  }

  #personsScreen #personRows .population-card-icon {
    display: inline-flex !important;
    width: 18px !important;
    min-width: 18px !important;
    height: 18px !important;
    font-size: 18px !important;
  }

  #personsScreen #personRows .population-action {
    height: 40px !important;
    min-height: 40px !important;
  }
}
`;
    document.head.appendChild(style);
  }

  function compactCards() {
    if (window.innerWidth >= 1200) return;
    document.querySelectorAll('#personsScreen #personRows .population-card').forEach(function (card) {
      var birthBox = card.querySelector('.population-birth-box');
      if (birthBox) {
        var value = birthBox.querySelector('.population-detail-value');
        var age = birthBox.querySelector('.population-birth-age');
        if (value && age && value.textContent.indexOf('tuổi') === -1) {
          var dateText = value.textContent.trim();
          var ageText = age.textContent.trim();
          if (dateText && ageText) value.textContent = dateText + ' (' + ageText + ')';
          age.remove();
        }
      }

      var residence = card.querySelector('.population-residence-box');
      if (residence) residence.classList.add('population-residence-compact-final');
    });
  }

  function startObserver() {
    if (window[OBSERVER_KEY]) return;
    var target = document.getElementById('personRows') || document.body;
    window[OBSERVER_KEY] = new MutationObserver(function () {
      compactCards();
    });
    window[OBSERVER_KEY].observe(target, { childList: true, subtree: true });
  }

  function run() {
    injectStyles();
    compactCards();
    startObserver();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run, { once: true });
  } else {
    run();
  }

  window.addEventListener('resize', compactCards);
  window.thon09CompactPopulationCards = compactCards;
})();
