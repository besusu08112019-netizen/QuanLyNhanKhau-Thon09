(function () {
  'use strict';

  const DESKTOP_QUERY = '(min-width: 1200px)';
  const relationshipCache = new Map();

  function isDesktop() {
    return window.matchMedia ? window.matchMedia(DESKTOP_QUERY).matches : window.innerWidth >= 1200;
  }

  function textValue(value) {
    return value === null || value === undefined ? '' : String(value).trim();
  }

  function hasValue(value) {
    const text = textValue(value);
    return text !== '' && text !== '-' && text !== '--' && text.toLowerCase() !== 'null' && text.toLowerCase() !== 'undefined';
  }

  function escapeHtmlLocal(value) {
    if (typeof window.escapeHtml === 'function') return window.escapeHtml(value);
    return textValue(value).replace(/[&<>"]/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[char];
    });
  }

  function formatDateLocal(value) {
    if (typeof window.formatDate === 'function') return window.formatDate(value);
    const text = textValue(value);
    if (!text) return '';
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})/);
    return match ? match[3] + '/' + match[2] + '/' + match[1] : text;
  }

  function ageFromDate(value) {
    const text = textValue(value);
    if (!text) return null;

    let birth = null;
    const iso = text.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (iso) {
      birth = new Date(Number(iso[1]), Number(iso[2]) - 1, Number(iso[3]));
    } else {
      const vn = text.match(/^(\d{2})\/(\d{2})\/(\d{4})/);
      if (vn) birth = new Date(Number(vn[3]), Number(vn[2]) - 1, Number(vn[1]));
    }

    if (!birth || Number.isNaN(birth.getTime())) return null;
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const hasHadBirthday = today.getMonth() > birth.getMonth()
      || (today.getMonth() === birth.getMonth() && today.getDate() >= birth.getDate());
    if (!hasHadBirthday) age -= 1;
    return age >= 0 ? age : null;
  }

  function formatAge(value) {
    const age = ageFromDate(value);
    return age === null ? '' : age + ' tuổi';
  }

  function residencyLabelLocal(value) {
    if (typeof window.residencyLabel === 'function') return window.residencyLabel(value);
    const text = textValue(value);
    if (text === 'TEMPORARY') return 'Tạm trú';
    if (text === 'PERMANENT') return 'Thường trú';
    return text || 'Thường trú';
  }

  function relationshipValue(row) {
    return textValue(row.relationship || row.relationship_to_head || row.relationshipToHead || row.relation_to_head || row.household_relationship);
  }

  function authHeaders() {
    const token = window.App && window.App.token ? window.App.token : (localStorage.getItem('thon09_token') || '');
    return token ? { Authorization: 'Bearer ' + token } : {};
  }

  async function fillRelationshipCell(id, cell) {
    if (!id || !cell || cell.dataset.relationshipLoading === '1') return;
    if (relationshipCache.has(id)) {
      cell.textContent = relationshipCache.get(id);
      return;
    }

    cell.dataset.relationshipLoading = '1';
    try {
      const response = await fetch('/api/persons/' + encodeURIComponent(id), {
        headers: { Accept: 'application/json', ...authHeaders() }
      });
      if (!response.ok) throw new Error('HTTP ' + response.status);
      const row = await response.json();
      const value = relationshipValue(row && (row.data || row));
      relationshipCache.set(id, value);
      cell.textContent = value;
    } catch (_) {
      cell.textContent = '';
    } finally {
      cell.dataset.relationshipLoading = '0';
    }
  }

  function desktopPersonRow(row) {
    const party = Number(row.party_member || row.partyMember || 0) === 1;
    const residenceClass = row.presence_status === 'AWAY'
      ? 'person-badge-away'
      : (row.residency_status === 'TEMPORARY' ? 'person-badge-temp' : 'person-badge-home');
    const residenceText = row.presence_status === 'AWAY' ? 'Tạm vắng' : residencyLabelLocal(row.residency_status);

    return '<tr>'
      + '<td><input type="checkbox" class="person-check" value="' + escapeHtmlLocal(row.id || '') + '"></td>'
      + '<td>' + escapeHtmlLocal(row.household_code || '') + '</td>'
      + '<td>' + escapeHtmlLocal(relationshipValue(row)) + '</td>'
      + '<td>' + escapeHtmlLocal(row.citizen_code || '') + '</td>'
      + '<td><button class="btn btn-link person-name-link" onclick="showPerson(' + Number(row.id || 0) + ')">' + escapeHtmlLocal(row.full_name || '') + '</button></td>'
      + '<td>' + formatDateLocal(row.date_of_birth) + '</td>'
      + '<td>' + escapeHtmlLocal(formatAge(row.date_of_birth)) + '</td>'
      + '<td>' + escapeHtmlLocal(row.gender || '') + '</td>'
      + '<td>' + escapeHtmlLocal(row.identity_number || '') + '</td>'
      + '<td><span class="person-badge ' + residenceClass + '">' + escapeHtmlLocal(residenceText) + '</span></td>'
      + '<td><span class="person-badge ' + (party ? 'person-badge-party' : 'person-badge-muted') + '">' + (party ? 'Có' : 'Không') + '</span></td>'
      + '<td class="text-end"><button class="btn btn-sm person-row-btn" onclick="showPerson(' + Number(row.id || 0) + ')">Xem</button> <button class="btn btn-sm person-row-btn person-row-edit" onclick="openPersonForm(' + Number(row.id || 0) + ')">Sửa</button> <button class="btn btn-sm btn-outline-danger" onclick="deletePerson(' + Number(row.id || 0) + ')">Xóa</button></td>'
      + '</tr>';
  }

  function findHeader(row, label) {
    return Array.from(row.children).find(function (cell) {
      return textValue(cell.textContent) === label;
    });
  }

  function ensureHeaderCell(afterCell, label, key) {
    const row = afterCell && afterCell.parentElement;
    if (!row || row.querySelector('th[data-desktop-extra="' + key + '"]')) return;
    const th = document.createElement('th');
    th.dataset.desktopExtra = key;
    th.textContent = label;
    afterCell.after(th);
  }

  function syncDesktopHeader() {
    const headerRow = document.querySelector('#personsScreen .person-table thead tr');
    if (!headerRow) return;

    if (!isDesktop()) {
      headerRow.querySelectorAll('th[data-desktop-extra]').forEach(function (cell) { cell.remove(); });
      return;
    }

    const householdCell = findHeader(headerRow, 'Mã hộ');
    if (householdCell) ensureHeaderCell(householdCell, 'Quan hệ', 'relationship');

    const birthCell = findHeader(headerRow, 'Ngày sinh');
    if (birthCell) ensureHeaderCell(birthCell, 'Tuổi', 'age');
  }

  function syncColspans() {
    const span = isDesktop() ? 12 : 10;
    document.querySelectorAll('#personRows td[colspan], #personRows tr.group-row td').forEach(function (cell) {
      const current = Number(cell.getAttribute('colspan') || 0);
      if (current >= 10) cell.setAttribute('colspan', String(span));
    });
  }

  function createCell(text) {
    const cell = document.createElement('td');
    cell.textContent = text || '';
    return cell;
  }

  function rebuildOldDesktopRow(row) {
    const cells = Array.from(row.children);
    if (cells.length !== 10) return;

    const checkbox = cells[0];
    const household = cells[1];
    const citizenCode = cells[2];
    const fullName = cells[3];
    const birth = cells[4];
    const gender = cells[5];
    const identity = cells[6];
    const residence = cells[7];
    const party = cells[8];
    const actions = cells[9];
    if (!checkbox || !household || !citizenCode || !fullName || !birth || !gender || !identity || !residence || !party || !actions) return;

    const idInput = checkbox.querySelector('.person-check');
    const id = idInput ? idInput.value : '';
    const relationship = createCell('');
    relationship.dataset.desktopExtraCell = 'relationship';
    const age = createCell(formatAge(birth.textContent));
    age.dataset.desktopExtraCell = 'age';

    row.replaceChildren(checkbox, household, relationship, citizenCode, fullName, birth, age, gender, identity, residence, party, actions);
    row.dataset.desktopPersonColumns = '12';
    fillRelationshipCell(id, relationship);
  }

  function normalizeRenderedDesktopRows() {
    if (!isDesktop()) return;
    document.querySelectorAll('#personRows tr:not(.group-row)').forEach(function (row) {
      if (row.querySelector('td[colspan]')) return;
      if (row.children.length === 10) rebuildOldDesktopRow(row);
    });
  }

  function syncPersonTable() {
    syncDesktopHeader();
    syncColspans();
    normalizeRenderedDesktopRows();
  }

  function bindPersonRowsObserver() {
    const rows = document.querySelector('#personRows');
    if (!rows || rows.__thon09DesktopPersonFieldsObserver) return;
    rows.__thon09DesktopPersonFieldsObserver = new MutationObserver(function () {
      window.requestAnimationFrame(syncPersonTable);
    });
    rows.__thon09DesktopPersonFieldsObserver.observe(rows, { childList: true, subtree: true });
  }

  function patchPersonRow() {
    if (window.__thon09DesktopPersonFieldsRowPatched || typeof window.personRow !== 'function') return;
    const originalPersonRow = window.personRow;
    window.__thon09DesktopPersonFieldsOriginalRow = originalPersonRow;
    window.personRow = function personRowWithDesktopFields(row) {
      if (!isDesktop()) return originalPersonRow.apply(this, arguments);
      return desktopPersonRow(row || {});
    };
    window.__thon09DesktopPersonFieldsRowPatched = true;
  }

  function patchLoadPersons() {
    if (window.__thon09DesktopPersonFieldsLoadPatched || typeof window.loadPersons !== 'function') return;
    const originalLoadPersons = window.loadPersons;
    window.loadPersons = async function loadPersonsWithDesktopFields() {
      const result = await originalLoadPersons.apply(this, arguments);
      syncPersonTable();
      return result;
    };
    window.__thon09DesktopPersonFieldsLoadPatched = true;
  }

  function patchPersonDetailNormalization() {
    if (window.__thon09DesktopPersonFieldsDetailPatched || typeof window.normalizePersonDetailData !== 'function') return;
    const originalNormalize = window.normalizePersonDetailData;
    window.normalizePersonDetailData = function normalizePersonDetailWithAge(row) {
      const normalized = originalNormalize.apply(this, arguments) || {};
      if (!isDesktop()) return normalized;

      const source = row || {};
      if (!hasValue(normalized.relationship)) {
        const relationship = relationshipValue(source);
        if (relationship) normalized.relationship = relationship;
      }
      if (!hasValue(normalized.age)) {
        const age = formatAge(normalized.dateOfBirth || source.date_of_birth || source.dateOfBirth);
        if (age) normalized.age = age;
      }
      return normalized;
    };
    window.__thon09DesktopPersonFieldsDetailPatched = true;
  }

  function refreshIfNeeded() {
    syncPersonTable();
    if (!isDesktop()) return;
    const personsScreen = document.querySelector('#personsScreen');
    if (!personsScreen || !personsScreen.classList.contains('active') || typeof window.loadPersons !== 'function') return;
    if (window.__thon09DesktopPersonFieldsRefreshing) return;
    window.__thon09DesktopPersonFieldsRefreshing = true;
    Promise.resolve(window.loadPersons()).finally(function () {
      window.__thon09DesktopPersonFieldsRefreshing = false;
      syncPersonTable();
    });
  }

  function start() {
    patchPersonRow();
    patchLoadPersons();
    patchPersonDetailNormalization();
    bindPersonRowsObserver();
    syncPersonTable();
    setTimeout(refreshIfNeeded, 100);
    setTimeout(syncPersonTable, 400);
    setTimeout(syncPersonTable, 1000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }

  if (!window.__thon09DesktopPersonFieldsResizeBound) {
    window.__thon09DesktopPersonFieldsResizeBound = true;
    let timer = null;
    window.addEventListener('resize', function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(refreshIfNeeded, 150);
    });
  }
})();
