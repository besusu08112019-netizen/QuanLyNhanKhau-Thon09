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
    const hadBirthday = today.getMonth() > birth.getMonth()
      || (today.getMonth() === birth.getMonth() && today.getDate() >= birth.getDate());
    if (!hadBirthday) age -= 1;
    return age >= 0 ? age : null;
  }

  function formatAge(value) {
    const age = ageFromDate(value);
    return age === null ? '' : age + ' tuổi';
  }

  function authHeaders() {
    const token = window.App && window.App.token ? window.App.token : (localStorage.getItem('thon09_token') || '');
    return token ? { Authorization: 'Bearer ' + token } : {};
  }

  function relationshipValue(row) {
    if (!row) return '';
    return textValue(row.relationship || row.relationship_to_head || row.relationshipToHead || row.relation_to_head || row.household_relationship);
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
        headers: Object.assign({ Accept: 'application/json' }, authHeaders())
      });
      if (!response.ok) throw new Error('HTTP ' + response.status);
      const payload = await response.json();
      const value = relationshipValue(payload && (payload.data || payload));
      relationshipCache.set(id, value);
      cell.textContent = value;
    } catch (_) {
      cell.textContent = '';
    } finally {
      cell.dataset.relationshipLoading = '0';
    }
  }

  function headerText(cell) {
    return textValue(cell && cell.textContent).replace(/\s+/g, ' ');
  }

  function findHeader(row, label) {
    return Array.from(row.children).find(function (cell) {
      return headerText(cell) === label;
    });
  }

  function ensureHeaderCell(afterCell, label, key) {
    const row = afterCell && afterCell.parentElement;
    if (!row || row.querySelector('th[data-desktop-aligner="' + key + '"]') || findHeader(row, label)) return;
    const th = document.createElement('th');
    th.dataset.desktopAligner = key;
    th.textContent = label;
    afterCell.after(th);
  }

  function ensureHeader() {
    const headerRow = document.querySelector('#personsScreen .person-table thead tr');
    if (!headerRow) return;

    if (!isDesktop()) {
      headerRow.querySelectorAll('th[data-desktop-aligner]').forEach(function (cell) { cell.remove(); });
      return;
    }

    const householdCell = findHeader(headerRow, 'Mã hộ');
    if (householdCell) ensureHeaderCell(householdCell, 'Quan hệ', 'relationship');

    const birthCell = findHeader(headerRow, 'Ngày sinh');
    if (birthCell) ensureHeaderCell(birthCell, 'Tuổi', 'age');
  }

  function cloneLike(reference, text) {
    const cell = document.createElement('td');
    cell.className = reference && reference.className ? reference.className : 'population-desktop-cell';
    cell.textContent = text || '';
    return cell;
  }

  function alignLegacyRow(row) {
    if (!isDesktop() || !row || row.dataset.desktopColumnsAligned === '1') return;
    if (row.classList.contains('group-row') || row.querySelector('td[colspan]')) return;

    const cells = Array.from(row.children);
    if (cells.length !== 10 && cells.length !== 11) return;

    const mobileCell = cells[10] && cells[10].classList && cells[10].classList.contains('population-mobile-cell') ? cells[10] : null;
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
    const relationship = cloneLike(citizenCode, '');
    relationship.dataset.desktopAlignerCell = 'relationship';
    const age = cloneLike(birth, formatAge(birth.textContent));
    age.dataset.desktopAlignerCell = 'age';

    const ordered = [
      checkbox,
      household,
      relationship,
      citizenCode,
      fullName,
      birth,
      age,
      gender,
      identity,
      residence,
      party,
      actions
    ];
    if (mobileCell) ordered.push(mobileCell);

    row.replaceChildren.apply(row, ordered);
    row.dataset.desktopColumnsAligned = '1';
    fillRelationshipCell(id, relationship);
  }

  function syncColspans() {
    const span = isDesktop() ? 12 : 10;
    document.querySelectorAll('#personRows td[colspan], #personRows tr.group-row td').forEach(function (cell) {
      const current = Number(cell.getAttribute('colspan') || 0);
      if (current >= 10) cell.setAttribute('colspan', String(span));
    });
  }

  function alignRows() {
    if (!isDesktop()) return;
    ensureHeader();
    syncColspans();
    document.querySelectorAll('#personRows tr:not(.group-row)').forEach(alignLegacyRow);
  }

  function bindObserver() {
    const rows = document.querySelector('#personRows');
    if (!rows || rows.__thon09DesktopColumnAlignerObserver) return;
    rows.__thon09DesktopColumnAlignerObserver = new MutationObserver(function () {
      window.requestAnimationFrame(alignRows);
      setTimeout(alignRows, 80);
    });
    rows.__thon09DesktopColumnAlignerObserver.observe(rows, { childList: true, subtree: true });
  }

  function start() {
    bindObserver();
    alignRows();
    setTimeout(alignRows, 100);
    setTimeout(alignRows, 400);
    setTimeout(alignRows, 1200);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }

  window.addEventListener('resize', function () {
    window.requestAnimationFrame(function () {
      ensureHeader();
      syncColspans();
      alignRows();
    });
  });
})();
