(function () {
  'use strict';

  if (document.getElementById('person-household-group-style')) return;
  var style = document.createElement('style');
  style.id = 'person-household-group-style';
  style.textContent = [
    '@media (max-width: 767px) {',
    '  #personsScreen #personRows .person-household-group-row,',
    '  #personsScreen #personRows .person-household-group-row td {',
    '    display: block !important;',
    '    width: 100% !important;',
    '  }',
    '  #personsScreen #personRows .person-household-group-row {',
    '    margin: 16px 0 10px !important;',
    '    border: 0 !important;',
    '    background: transparent !important;',
    '  }',
    '  #personsScreen #personRows .person-household-group-row td {',
    '    padding: 0 !important;',
    '    border: 0 !important;',
    '    background: transparent !important;',
    '  }',
    '  #personsScreen #personRows .person-household-group-card {',
    '    display: flex !important;',
    '    align-items: center !important;',
    '    justify-content: space-between !important;',
    '    gap: 10px !important;',
    '    padding: 9px 12px !important;',
    '    border: 1px solid #cfeedd !important;',
    '    border-radius: 12px !important;',
    '    background: #edf8f2 !important;',
    '    color: #0f5132 !important;',
    '  }',
    '}',
  ].join('\n');
  document.head.appendChild(style);
})();
