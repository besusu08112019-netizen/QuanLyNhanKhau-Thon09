(function () {
  'use strict';

  const CSS_HREFS = [
    'assets/css/admin-design-system.css?v=20260701-admin-ds-2',
    'assets/css/person-card-layout.css?v=20260701-person-card-mobile-only-2'
  ];
  const JS_SRC = 'assets/js/admin-design-system.js?v=20260701-admin-ds-1';

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
    if (!document.querySelector('script[src*="admin-design-system.js"]')) {
      const script = document.createElement('script');
      script.src = JS_SRC;
      script.defer = true;
      document.body.appendChild(script);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ensureSharedDesignAssets);
  } else {
    ensureSharedDesignAssets();
  }
})();

(function () {
  'use strict';

  const EMPTY_VALUES = new Set(['', '-', '--', '---', 'n/a', 'na', 'null', 'undefined', 'khong co du lieu']);

  function normalize(value) {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/g, 'd')
      .replace(/Đ/g, 'D')
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .trim();
  }

  function isEmpty(value) {
    return EMPTY_VALUES.has(normalize(value));
  }

  function parseDate(value) {
    const text = String(value || '').trim();
    let day, month, year;
    let match = text.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
    if (match) {
      day = Number(match[1]);
      month = Number(match[2]);
      year = Number(match[3]);
    } else {
      match = text.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/);
      if (!match) return null;
      year = Number(match[1]);
      month = Number(match[2]);
      day = Number(match[3]);
    }
    const date = new Date(year, month - 1, day);
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) return null;
    return date;
  }

  function ageFromBirthday(value) {
    const date = parseDate(value);
    if (!date) return null;
    const now = new Date();
    let age = now.getFullYear() - date.getFullYear();
    const beforeBirthday = now.getMonth() < date.getMonth() || (now.getMonth() === date.getMonth() && now.getDate() < date.getDate());
    if (beforeBirthday) age -= 1;
    return age >= 0 && age < 130 ? age : null;
  }

  function escapeHtml(value) {
    if (typeof window.escapeHtml === 'function') return window.escapeHtml(value);
    return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
  }

  function formatDateOnlyText(value) {
    const date = parseDate(value);
    if (!date) return escapeHtml(value || '');
    return String(date.getDate()).padStart(2, '0') + '/' + String(date.getMonth() + 1).padStart(2, '0') + '/' + date.getFullYear();
  }

  function residenceInfo(row) {
    if (row && row.presence_status === 'AWAY') return { text: 'Tạm vắng', cls: 'population-status-away', badgeCls: 'person-badge-away' };
    if (row && row.residency_status === 'TEMPORARY') return { text: 'Tạm trú', cls: 'population-status-temp', badgeCls: 'person-badge-temp' };
    if (row && (row.residency_status === 'MOVED' || row.life_status === 'MOVED')) return { text: 'Chuyển đi', cls: 'population-status-away', badgeCls: 'person-badge-away' };
    if (row && (row.life_status === 'DECEASED' || row.life_status === 'DEAD')) return { text: 'Đã mất', cls: 'population-status-muted', badgeCls: 'person-badge-muted' };
    const text = typeof window.residencyLabel === 'function' ? window.residencyLabel(row && row.residency_status) : 'Thường trú';
    return { text: text || 'Thường trú', cls: 'population-status-home', badgeCls: 'person-badge-home' };
  }

  function genderIcon(gender) {
    return normalize(gender).includes('nu')
      ? '<i class="fa-solid fa-venus population-card-icon-female"></i>'
      : '<i class="fa-solid fa-mars population-card-icon-male"></i>';
  }

  function infoBox(iconHtml, label, value) {
    if (isEmpty(value)) return '';
    return '<div class="population-info-box"><span class="population-card-icon">' + iconHtml + '</span><div><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong></div></div>';
  }

  function renderDesktopCells(row, residence, party) {
    const id = Number(row && row.id) || 0;
    return '<td class="population-desktop-cell"><input type="checkbox" class="person-check" value="' + id + '"></td>'
      + '<td class="population-desktop-cell">' + escapeHtml(row.household_code || '') + '</td>'
      + '<td class="population-desktop-cell">' + escapeHtml(row.citizen_code || '') + '</td>'
      + '<td class="population-desktop-cell"><button class="btn btn-link person-name-link" onclick="showPerson(' + id + ')">' + escapeHtml(row.full_name || '') + '</button></td>'
      + '<td class="population-desktop-cell">' + formatDateOnlyText(row.date_of_birth) + '</td>'
      + '<td class="population-desktop-cell">' + escapeHtml(row.gender || '') + '</td>'
      + '<td class="population-desktop-cell">' + escapeHtml(row.identity_number || row.personal_id || row.cccd || '') + '</td>'
      + '<td class="population-desktop-cell"><span class="person-badge ' + residence.badgeCls + '">' + escapeHtml(residence.text) + '</span></td>'
      + '<td class="population-desktop-cell"><span class="person-badge ' + (party ? 'person-badge-party' : 'person-badge-muted') + '">' + (party ? 'Có' : 'Không') + '</span></td>'
      + '<td class="population-desktop-cell text-end"><button class="btn btn-sm person-row-btn" onclick="showPerson(' + id + ')">Xem</button> <button class="btn btn-sm person-row-btn person-row-edit" onclick="openPersonForm(' + id + ')">Sửa</button> <button class="btn btn-sm btn-outline-danger" onclick="deletePerson(' + id + ')">Xóa</button></td>';
  }

  function renderMobileCard(row, residence, party) {
    const id = Number(row && row.id) || 0;
    const householdCode = row.household_code || '';
    const citizenCode = row.citizen_code || '';
    const fullName = row.full_name || '';
    const identity = row.identity_number || row.personal_id || row.cccd || '';
    const birthText = formatDateOnlyText(row.date_of_birth || row.birth_date || '');
    const age = ageFromBirthday(row.date_of_birth || row.birth_date || '');
    const gender = row.gender || '';

    return '<td class="population-mobile-cell" data-mobile-role="population-card" colspan="10">'
      + '<article class="population-card">'
      + '<header class="population-card-head">'
      + '<button type="button" class="population-card-name" onclick="showPerson(' + id + ')">' + escapeHtml(fullName) + '</button>'
      + '<div class="population-card-head-actions">'
      + (householdCode ? '<span class="population-household-badge">' + escapeHtml(householdCode) + '</span>' : '')
      + '<input type="checkbox" class="person-check population-check" value="' + id + '">'
      + '</div>'
      + '</header>'
      + '<div class="population-code-grid">'
      + '<div class="population-code-box"><span>Mã nhân khẩu</span><strong>' + escapeHtml(citizenCode) + '</strong></div>'
      + '<div class="population-code-box"><span>CCCD/Số định danh</span><strong>' + escapeHtml(identity) + '</strong></div>'
      + '</div>'
      + '<div class="population-info-grid">'
      + infoBox('<i class="fa-regular fa-calendar-days population-card-icon-date"></i>', 'Ngày sinh', birthText)
      + infoBox(genderIcon(gender), 'Giới tính', gender)
      + infoBox('<i class="fa-solid fa-users population-card-icon-age"></i>', 'Tuổi', age === null ? '--' : age + ' tuổi')
      + '</div>'
      + '<div class="population-status-grid">'
      + '<div class="population-status-field"><span>Cư trú</span><em class="population-status-badge ' + residence.cls + '">' + escapeHtml(residence.text) + '</em></div>'
      + '<div class="population-status-field"><span>Đảng viên</span><em class="population-status-badge ' + (party ? 'population-status-party' : 'population-status-muted') + '">' + (party ? 'Có' : 'Không') + '</em></div>'
      + '</div>'
      + '<div class="population-action-grid">'
      + '<button type="button" class="population-action population-action-view" onclick="showPerson(' + id + ')"><i class="fa-regular fa-eye"></i><span>Xem</span></button>'
      + '<button type="button" class="population-action population-action-edit" onclick="openPersonForm(' + id + ')"><i class="fa-regular fa-pen-to-square"></i><span>Sửa</span></button>'
      + '<button type="button" class="population-action population-action-delete" onclick="deletePerson(' + id + ')"><i class="fa-regular fa-trash-can"></i><span>Xóa</span></button>'
      + '</div>'
      + '</article>'
      + '</td>';
  }

  function renderPopulationRow(row) {
    row = row || {};
    const party = Number(row.party_member || row.partyMember || 0) === 1;
    const residence = residenceInfo(row);
    return '<tr class="population-row">' + renderDesktopCells(row, residence, party) + renderMobileCard(row, residence, party) + '</tr>';
  }

  function markActionButtons(root) {
    const scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('td[data-mobile-role="actions"] button, td[data-mobile-role="actions"] .btn, td.text-end button, td.text-end .btn').forEach(button => {
      const text = normalize(button.textContent);
      if (text.includes('xem')) button.dataset.mobileAction = 'view';
      else if (text.includes('sua')) button.dataset.mobileAction = 'edit';
      else if (text.includes('xoa')) button.dataset.mobileAction = 'delete';
    });
  }

  window.ageFromDate = ageFromBirthday;
  window.personRow = renderPopulationRow;
  try {
    if (typeof personRow === 'function') personRow = renderPopulationRow;
  } catch (_) {}

  function start() {
    markActionButtons(document);
    if (!window.__thon09PopulationCardRefresh && typeof window.loadPersons === 'function' && window.App && window.App.screen === 'persons') {
      window.__thon09PopulationCardRefresh = true;
      setTimeout(() => window.loadPersons(), 0);
    }
    if (window.__thon09MobileDesignSystemObserver) return;
    window.__thon09MobileDesignSystemObserver = true;
    const observer = new MutationObserver(mutations => {
      mutations.forEach(mutation => mutation.addedNodes.forEach(node => {
        if (node.nodeType === 1) markActionButtons(node);
      }));
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();