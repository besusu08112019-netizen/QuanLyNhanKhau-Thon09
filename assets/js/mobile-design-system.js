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
  const PERSON_TABLE_COLSPAN = 12;

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
  }

  function textValue(value) {
    return value === null || value === undefined ? '' : String(value).trim();
  }

  function safe(value) {
    if (typeof window.escapeHtml === 'function') return window.escapeHtml(value);
    return textValue(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
  }

  function normalizeValue(value) {
    if (typeof window.normalizeSearchText === 'function') return window.normalizeSearchText(value);
    return textValue(value).toLowerCase();
  }

  function formatDateValue(value) {
    if (typeof window.formatDate === 'function') return window.formatDate(value);
    const raw = textValue(value);
    const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
    return match ? match[3] + '/' + match[2] + '/' + match[1] : raw;
  }

  function exactAge(value) {
    const raw = textValue(value);
    if (!raw) return null;
    let birth = null;
    const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (iso) birth = new Date(Number(iso[1]), Number(iso[2]) - 1, Number(iso[3]));
    const vn = !birth && raw.match(/^(\d{2})\/(\d{2})\/(\d{4})/);
    if (vn) birth = new Date(Number(vn[3]), Number(vn[2]) - 1, Number(vn[1]));
    if (!birth || Number.isNaN(birth.getTime())) return null;
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const hadBirthday = today.getMonth() > birth.getMonth() || (today.getMonth() === birth.getMonth() && today.getDate() >= birth.getDate());
    if (!hadBirthday) age -= 1;
    return age >= 0 ? age : null;
  }

  function relationshipOf(row) {
    return textValue(row.relationship || row.relationship_to_head || row.relationshipToHead || row.relation_to_head || row.household_relationship || row.member_relationship);
  }

  function applyResidence(params, value) {
    if (value === 'PERMANENT' || value === 'TEMPORARY') params.set('residencyStatus', value);
    else if (value === 'AWAY') params.set('presenceStatus', 'AWAY');
  }

  function applyAgeGroup(params, value) {
    if (value === '0_5') { params.set('ageFrom', '0'); params.set('ageTo', '5'); }
    else if (value === '6_14') { params.set('ageFrom', '6'); params.set('ageTo', '14'); }
    else if (value === '15_17') { params.set('ageFrom', '15'); params.set('ageTo', '17'); }
    else if (value === '18_59') { params.set('ageFrom', '18'); params.set('ageTo', '59'); }
    else if (value === '60_plus') params.set('ageFrom', '60');
  }

  function appendPersonFilter(params, key, value) {
    if (!value) return;
    if (key === 'residenceCombined') applyResidence(params, value);
    else if (key === 'ageGroup') applyAgeGroup(params, value);
    else params.set(key, value);
  }

  function buildPersonParams(includeSearch) {
    const state = window.App && App.persons ? App.persons : { page: 1, pageSize: 20 };
    const params = new URLSearchParams({ page: state.page || 1, pageSize: state.pageSize || 20 });
    if (state.householdId) params.set('householdId', state.householdId);
    if (includeSearch) {
      const search = textValue((document.querySelector('#personSearch') && document.querySelector('#personSearch').value) || state.search || '');
      if (search) params.set('search', search);
    }
    document.querySelectorAll('[data-person-filter]').forEach(el => {
      const key = el.dataset.personFilter;
      const value = textValue(el.value || '');
      if (state && key) state[key] = value;
      appendPersonFilter(params, key, value);
    });
    return params;
  }

  function activeFilterObject() {
    const params = buildPersonParams(false);
    params.delete('page');
    params.delete('pageSize');
    return Object.fromEntries(params.entries());
  }

  function ensurePersonTableHeader() {
    const header = document.querySelector('#personsScreen .person-table thead tr');
    if (!header || header.dataset.thon09PersonColumns === '12') return;
    header.dataset.thon09PersonColumns = '12';
    header.innerHTML = '<th><input type="checkbox" id="personCheckAll"></th>'
      + '<th>Mã hộ</th>'
      + '<th>Quan hệ</th>'
      + '<th>Mã nhân khẩu</th>'
      + '<th>Họ và tên</th>'
      + '<th>Ngày sinh</th>'
      + '<th>Tuổi</th>'
      + '<th>Giới tính</th>'
      + '<th>CCCD/Số định danh</th>'
      + '<th>Cư trú</th>'
      + '<th>Đảng viên</th>'
      + '<th class="text-end">Thao tác</th>';
  }

  function renderPersonRow(row) {
    const party = Number(row.party_member || row.partyMember || 0) === 1;
    const residenceClass = row.presence_status === 'AWAY' ? 'person-badge-away' : (row.residency_status === 'TEMPORARY' ? 'person-badge-temp' : 'person-badge-home');
    const residenceText = row.presence_status === 'AWAY' ? 'Tạm vắng' : (typeof window.residencyLabel === 'function' ? window.residencyLabel(row.residency_status) : (row.residency_status || 'Thường trú'));
    const age = exactAge(row.date_of_birth);
    const id = Number(row.id || 0);
    return '<tr>'
      + '<td><input type="checkbox" class="person-check" value="' + safe(row.id || '') + '"></td>'
      + '<td>' + safe(row.household_code || '') + '</td>'
      + '<td>' + safe(relationshipOf(row)) + '</td>'
      + '<td>' + safe(row.citizen_code || '') + '</td>'
      + '<td><button class="btn btn-link person-name-link" onclick="showPerson(' + id + ')">' + safe(row.full_name || '') + '</button></td>'
      + '<td>' + safe(formatDateValue(row.date_of_birth)) + '</td>'
      + '<td>' + (age === null ? '' : safe(age + ' tuổi')) + '</td>'
      + '<td>' + safe(row.gender || '') + '</td>'
      + '<td>' + safe(row.identity_number || '') + '</td>'
      + '<td><span class="person-badge ' + residenceClass + '">' + safe(residenceText) + '</span></td>'
      + '<td><span class="person-badge ' + (party ? 'person-badge-party' : 'person-badge-muted') + '">' + (party ? 'Có' : 'Không') + '</span></td>'
      + '<td class="text-end"><button class="btn btn-sm person-row-btn" onclick="showPerson(' + id + ')">Xem</button> <button class="btn btn-sm person-row-btn person-row-edit" onclick="openPersonForm(' + id + ')">Sửa</button> <button class="btn btn-sm btn-outline-danger" onclick="deletePerson(' + id + ')">Xóa</button></td>'
      + '</tr>';
  }

  function installPersonTableRenderer() {
    window.personRow = renderPersonRow;
    window.loadPersons = async function loadPersons() {
      try {
        ensurePersonTableHeader();
        const searchEl = document.querySelector('#personSearch');
        const searchText = normalizeValue((searchEl && searchEl.value) || (window.App && App.persons && App.persons.search) || '');
        if (window.App && App.persons) App.persons.search = (searchEl && searchEl.value || '').trim();
        let items = [];
        let total = 0;
        if (searchText) {
          const allItems = await window.fetchAllPaged('/api/persons', activeFilterObject());
          const filtered = allItems.filter(row => [row.full_name, row.citizen_code, row.identity_number, row.personal_id, row.national_id, row.phone, row.household_code, row.current_address, row.household_address]
            .some(value => normalizeValue(value).includes(searchText)));
          total = filtered.length;
          const startIndex = ((App.persons.page || 1) - 1) * (App.persons.pageSize || 20);
          items = filtered.slice(startIndex, startIndex + (App.persons.pageSize || 20));
        } else {
          const data = await window.api('/api/persons?' + buildPersonParams(false).toString());
          items = data.items || [];
          total = data.total || 0;
        }
        const totalEl = document.querySelector('#personTotalCount');
        if (totalEl && typeof window.number === 'function') totalEl.innerHTML = 'Tổng số: <strong>' + window.number(total) + '</strong> nhân khẩu';
        const grouped = items.reduce((acc, row) => {
          const code = row.household_code || 'Chưa có hộ';
          (acc[code] ||= []).push(row);
          return acc;
        }, {});
        const body = document.querySelector('#personRows');
        if (body) {
          body.innerHTML = Object.entries(grouped).map(([code, rows]) => '<tr class="group-row"><td colspan="' + PERSON_TABLE_COLSPAN + '">Mã hộ: ' + safe(code) + '</td></tr>' + rows.map(renderPersonRow).join('')).join('') || '<tr><td colspan="' + PERSON_TABLE_COLSPAN + '" class="text-center text-muted py-4">Không có dữ liệu</td></tr>';
        }
        if (typeof window.updateBulkDeleteButtons === 'function') window.updateBulkDeleteButtons();
        if (typeof window.renderPager === 'function') {
          window.renderPager('#personPager', { total, page: App.persons.page, pageSize: App.persons.pageSize }, page => { App.persons.page = page; window.loadPersons(); });
        }
      } catch (error) {
        if (typeof window.showToast === 'function') window.showToast('Không tải được danh sách nhân khẩu: ' + error.message, 'danger');
      }
    };
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
    ensurePersonTableHeader();
    installPersonTableRenderer();
    bindPopulationNameFit();
    window.thon09FitPopulationNames = fitPopulationNames;
    window.requestAnimationFrame(fitPopulationNames);
    setTimeout(fitPopulationNames, 100);
    if (window.App && App.screen === 'persons') setTimeout(() => window.loadPersons && window.loadPersons(), 50);
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
