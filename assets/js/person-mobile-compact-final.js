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
    --thon09-person-border: #d7eadf;
    --thon09-person-line: #edf2f0;
    --thon09-person-soft: #f0faf4;
    --thon09-person-soft-2: #f7fcf9;
    --thon09-person-green: #087a42;
    --thon09-person-text: #1f2937;
    --thon09-person-muted: #667085;
    --thon09-person-radius: 16px;
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
    display: block !important;
    width: 100% !important;
    padding: 0 !important;
  }

  #personsScreen #personRows .population-card {
    display: flex !important;
    flex-direction: column !important;
    gap: 0 !important;
    margin: 0 0 14px !important;
    padding: 10px !important;
    border: 1px solid var(--thon09-person-border) !important;
    border-radius: var(--thon09-person-radius) !important;
    background: #fff !important;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06) !important;
  }

  #personsScreen #personRows .population-card-head {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) auto !important;
    align-items: center !important;
    gap: 8px !important;
    min-height: 0 !important;
    margin: 0 0 7px !important;
    padding: 10px 11px !important;
    border: 0 !important;
    border-radius: 14px !important;
    background: var(--thon09-person-soft) !important;
    box-shadow: none !important;
  }

  #personsScreen #personRows .population-card-title-stack {
    position: relative !important;
    display: grid !important;
    grid-template-columns: auto minmax(0, 1fr) !important;
    grid-template-areas: 'icon name' 'icon code' !important;
    column-gap: 9px !important;
    row-gap: 2px !important;
    align-items: center !important;
    min-width: 0 !important;
  }

  #personsScreen #personRows .population-card-title-stack::before {
    content: '\f007' !important;
    grid-area: icon !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 30px !important;
    height: 30px !important;
    border-radius: 10px !important;
    background: #d7f3e4 !important;
    color: var(--thon09-person-green) !important;
    font-family: 'Font Awesome 6 Free' !important;
    font-weight: 900 !important;
    font-size: 14px !important;
  }

  #personsScreen #personRows .population-card-name {
    grid-area: name !important;
    display: block !important;
    width: fit-content !important;
    max-width: 100% !important;
    min-width: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    color: var(--thon09-person-green) !important;
    font-size: clamp(17px, 4.6vw, 19px) !important;
    font-weight: 700 !important;
    line-height: 1.1 !important;
    text-align: left !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
  }

  #personsScreen #personRows .population-person-subcode {
    grid-area: code !important;
    color: var(--thon09-person-muted) !important;
    font-size: clamp(12px, 3vw, 13px) !important;
    font-weight: 650 !important;
    line-height: 1.15 !important;
    white-space: nowrap !important;
  }

  #personsScreen #personRows .population-relation-badge {
    width: max-content !important;
    max-width: 36vw !important;
    padding: 4px 8px !important;
    border: 1px solid #cbead8 !important;
    border-radius: 999px !important;
    background: #fff !important;
    color: var(--thon09-person-green) !important;
    font-size: clamp(11px, 3vw, 13px) !important;
    font-weight: 650 !important;
    line-height: 1.05 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
  }

  #personsScreen #personRows .population-card-head-actions {
    align-self: center !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 7px !important;
  }

  #personsScreen #personRows .population-check {
    width: 23px !important;
    height: 23px !important;
    margin: 0 !important;
    align-self: center !important;
  }

  #personsScreen #personRows .population-code-grid,
  #personsScreen #personRows .population-bio-grid,
  #personsScreen #personRows .population-context-grid,
  #personsScreen #personRows .population-admin-grid {
    display: block !important;
    gap: 0 !important;
    margin: 0 !important;
  }

  #personsScreen #personRows .population-code-box,
  #personsScreen #personRows .population-detail-box,
  #personsScreen #personRows .population-status-card {
    display: grid !important;
    grid-template-columns: auto minmax(0, 1fr) !important;
    align-items: center !important;
    gap: 9px !important;
    min-height: 0 !important;
    margin: 0 !important;
    padding: 8px 2px !important;
    border: 0 !important;
    border-top: 1px solid var(--thon09-person-line) !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
  }

  #personsScreen #personRows .population-code-box:first-child {
    display: none !important;
  }

  #personsScreen #personRows .population-code-box span,
  #personsScreen #personRows .population-detail-label {
    display: block !important;
    color: var(--thon09-person-muted) !important;
    font-size: clamp(12px, 3vw, 13px) !important;
    font-weight: 650 !important;
    line-height: 1.15 !important;
    white-space: nowrap !important;
    word-break: keep-all !important;
  }

  #personsScreen #personRows .population-code-box strong,
  #personsScreen #personRows .population-detail-value,
  #personsScreen #personRows .population-status-pill {
    display: inline-block !important;
    width: auto !important;
    max-width: 100% !important;
    color: var(--thon09-person-text) !important;
    font-size: clamp(15px, 3.8vw, 17px) !important;
    font-weight: 650 !important;
    line-height: 1.2 !important;
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
    word-break: keep-all !important;
  }

  #personsScreen #personRows .population-card-icon {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 22px !important;
    min-width: 22px !important;
    height: 22px !important;
    border-radius: 8px !important;
    background: var(--thon09-person-soft-2) !important;
    color: var(--thon09-person-green) !important;
    font-size: 16px !important;
    line-height: 1 !important;
  }

  #personsScreen #personRows .population-detail-copy {
    display: grid !important;
    grid-template-columns: minmax(82px, auto) minmax(0, 1fr) !important;
    align-items: center !important;
    gap: 8px !important;
    min-width: 0 !important;
  }

  #personsScreen #personRows .population-code-box {
    grid-template-columns: minmax(112px, auto) minmax(0, 1fr) !important;
  }

  #personsScreen #personRows .population-birth-age {
    display: none !important;
  }

  #personsScreen #personRows .population-birth-box .population-detail-value {
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
  }

  #personsScreen #personRows .population-residence-compact-final {
    min-height: 0 !important;
    padding: 8px 2px !important;
  }

  #personsScreen #personRows .population-residence-compact-final .population-detail-label {
    display: none !important;
  }

  #personsScreen #personRows .population-residence-compact-final .population-detail-copy {
    display: block !important;
  }

  #personsScreen #personRows .population-residence-compact-final .population-status-pill {
    padding: 4px 10px !important;
    border: 1px solid #bfe8cf !important;
    border-radius: 999px !important;
    background: #eaf8f0 !important;
    color: var(--thon09-person-green) !important;
    font-size: clamp(13px, 3.3vw, 15px) !important;
    font-style: normal !important;
    font-weight: 700 !important;
    white-space: nowrap !important;
  }

  #personsScreen #personRows .population-action-grid {
    display: grid !important;
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    gap: 8px !important;
    margin-top: 2px !important;
    padding-top: 9px !important;
    border-top: 1px solid var(--thon09-person-line) !important;
  }

  #personsScreen #personRows .population-action {
    min-height: 40px !important;
    height: 42px !important;
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
    background: var(--thon09-person-soft) !important;
    border: 1px solid var(--thon09-person-border) !important;
    box-shadow: none !important;
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
    margin-bottom: 12px !important;
    padding: 9px !important;
  }

  #personsScreen #personRows .population-card-head {
    padding: 9px 10px !important;
  }

  #personsScreen #personRows .population-card-title-stack::before {
    width: 28px !important;
    height: 28px !important;
  }

  #personsScreen #personRows .population-relation-badge {
    max-width: 30vw !important;
    padding: 3px 7px !important;
  }

  #personsScreen #personRows .population-code-box,
  #personsScreen #personRows .population-detail-box,
  #personsScreen #personRows .population-status-card {
    padding: 7px 1px !important;
    gap: 7px !important;
  }

  #personsScreen #personRows .population-detail-copy {
    grid-template-columns: minmax(72px, auto) minmax(0, 1fr) !important;
    gap: 7px !important;
  }

  #personsScreen #personRows .population-code-box {
    grid-template-columns: minmax(102px, auto) minmax(0, 1fr) !important;
  }

  #personsScreen #personRows .population-card-icon {
    width: 20px !important;
    min-width: 20px !important;
    height: 20px !important;
    font-size: 15px !important;
  }
}
`;
    document.head.appendChild(style);
  }

  function compactCards() {
    if (window.innerWidth >= 1200) return;
    document.querySelectorAll('#personsScreen #personRows .population-card').forEach(function (card) {
      card.classList.add('population-household-style-card');

      var codeBox = card.querySelector('.population-code-box');
      var titleStack = card.querySelector('.population-card-title-stack');
      var headActions = card.querySelector('.population-card-head-actions');
      var codeValue = codeBox && codeBox.querySelector('strong') ? codeBox.querySelector('strong').textContent.trim() : '';
      if (titleStack && codeValue && !titleStack.querySelector('.population-person-subcode')) {
        var subCode = document.createElement('span');
        subCode.className = 'population-person-subcode';
        subCode.textContent = codeValue;
        titleStack.appendChild(subCode);
      }

      var relation = titleStack && titleStack.querySelector('.population-relation-badge');
      if (relation && headActions && relation.parentElement !== headActions) {
        headActions.insertBefore(relation, headActions.firstChild);
      }

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
