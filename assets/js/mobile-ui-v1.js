(function () {
  'use strict';

  function text(value) {
    return value === null || value === undefined ? '' : String(value).trim();
  }

  function safe(value) {
    if (typeof window.escapeHtml === 'function') return window.escapeHtml(value);
    return text(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
  }

  function hasSession() {
    return !!(localStorage.getItem('thon09_token') || (window.App && App.token));
  }

  function setMobileChromeVisible(visible) {
    document.querySelector('.mobile-bottom-nav')?.classList.toggle('d-none', !visible);
    document.querySelector('.mobile-fab')?.classList.toggle('d-none', !visible);
    document.querySelector('.mobile-refresh-indicator')?.classList.toggle('d-none', !visible);
    if (!visible) {
      document.querySelector('.mobile-sheet-backdrop')?.classList.add('d-none');
      document.querySelectorAll('.mobile-bottom-sheet.is-open').forEach(panel => panel.classList.remove('is-open'));
      document.body.classList.remove('sidebar-open', 'mobile-screen-changing');
      document.querySelector('.sidebar')?.classList.remove('open');
    }
  }

  function signalAuthState(authenticated) {
    document.dispatchEvent(new CustomEvent('thon09:auth-state', { detail: { authenticated: !!authenticated } }));
  }

  function watchSessionToken() {
    if (window.__thon09MobileSessionWatcher) return;
    window.__thon09MobileSessionWatcher = true;
    const setItem = Storage.prototype.setItem;
    const removeItem = Storage.prototype.removeItem;
    Storage.prototype.setItem = function patchedSetItem(key, value) {
      const result = setItem.apply(this, arguments);
      if (this === localStorage && key === 'thon09_token') window.setTimeout(() => signalAuthState(true), 0);
      return result;
    };
    Storage.prototype.removeItem = function patchedRemoveItem(key) {
      const result = removeItem.apply(this, arguments);
      if (this === localStorage && key === 'thon09_token') window.setTimeout(() => signalAuthState(false), 0);
      return result;
    };
  }

  function fmtDate(value) {
    return typeof window.formatDate === 'function' ? window.formatDate(value) : text(value);
  }

  function relation(row) {
    return text(row.relationship || row.relationship_to_head || row.relationshipToHead || row.relation_to_head || row.household_relationship);
  }

  function ageFromDate(value) {
    const raw = text(value);
    if (!raw) return '';
    let birth = null;
    const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (iso) birth = new Date(Number(iso[1]), Number(iso[2]) - 1, Number(iso[3]));
    const vn = !birth && raw.match(/^(\d{2})\/(\d{2})\/(\d{4})/);
    if (vn) birth = new Date(Number(vn[3]), Number(vn[2]) - 1, Number(vn[1]));
    if (!birth || Number.isNaN(birth.getTime())) return '';
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const passed = today.getMonth() > birth.getMonth() || (today.getMonth() === birth.getMonth() && today.getDate() >= birth.getDate());
    if (!passed) age -= 1;
    return age >= 0 ? age + ' tuổi' : '';
  }

  function residenceInfo(row) {
    const away = row.presence_status === 'AWAY';
    const temporary = row.residency_status === 'TEMPORARY';
    return {
      cls: away ? 'person-badge-away' : (temporary ? 'person-badge-temp' : 'person-badge-home'),
      label: away ? 'Tạm vắng' : (typeof window.residencyLabel === 'function' ? window.residencyLabel(row.residency_status) : (row.residency_status || 'Thường trú'))
    };
  }

  function partyInfo(row) {
    const party = Number(row.party_member || row.partyMember || 0) === 1;
    return { cls: party ? 'person-badge-party' : 'person-badge-muted', label: party ? 'Có' : 'Không' };
  }

  window.renderPersonRows = function renderPersonRows(items) {
    const rows = Array.isArray(items) ? items : [];
    if (!rows.length && typeof window.emptyRow === 'function') return window.emptyRow(11, 'Chưa có nhân khẩu');
    const groups = new Map();
    rows.forEach(row => {
      const code = text(row.household_code || row.householdCode || 'Chưa có hộ');
      if (!groups.has(code)) groups.set(code, []);
      groups.get(code).push(row);
    });
    return Array.from(groups.entries()).map(([code, members]) => {
      const head = members.find(row => /chủ hộ/i.test(relation(row))) || members[0] || {};
      return '<tr class="ds-group-row person-household-group"><td colspan="12"><div class="ds-group-header"><div><i class="fa-solid fa-house-chimney"></i><span>Hộ ' + safe(code) + '</span><small>Chủ hộ: ' + safe(head.full_name || '') + '</small></div><strong>' + members.length + ' nhân khẩu</strong></div></td></tr>' + members.map(window.personRow).join('');
    }).join('');
  };

  window.personRow = function personRow(row = {}) {
    const residence = residenceInfo(row);
    const party = partyInfo(row);
    const age = ageFromDate(row.date_of_birth);
    return '<tr class="ds-record-row ds-person-row">'
      + '<td data-label="Chọn"><input type="checkbox" class="person-check" value="' + safe(row.id || '') + '"></td>'
      + '<td data-label="Mã hộ">' + safe(row.household_code || '') + '</td>'
      + '<td data-label="Mã nhân khẩu">' + safe(row.person_code || row.citizen_code || '') + '</td>'
      + '<td data-label="Họ và tên"><button class="btn btn-link person-name-link" onclick="showPerson(' + Number(row.id || 0) + ')">' + safe(row.full_name || '') + '</button></td>'
      + '<td data-label="Quan hệ">' + safe(relation(row)) + '</td>'
      + '<td data-label="Ngày sinh">' + fmtDate(row.date_of_birth) + '</td>'
      + '<td data-label="Tuổi">' + safe(age) + '</td>'
      + '<td data-label="Giới tính">' + safe(row.gender || '') + '</td>'
      + '<td data-label="CCCD/Số định danh">' + safe(row.identity_number || '') + '</td>'
      + '<td data-label="Cư trú"><span class="person-badge ' + residence.cls + '">' + safe(residence.label) + '</span></td>'
      + '<td data-label="Đảng viên"><span class="person-badge ' + party.cls + '">' + safe(party.label) + '</span></td>'
      + '<td data-label="Thao tác" class="text-end ds-action-cell"><button class="btn btn-sm person-row-btn" onclick="showPerson(' + Number(row.id || 0) + ')">Xem</button> <button class="btn btn-sm person-row-btn person-row-edit" onclick="openPersonForm(' + Number(row.id || 0) + ')">Sửa</button> <button class="btn btn-sm btn-outline-danger" onclick="deletePerson(' + Number(row.id || 0) + ')">Xóa</button></td>'
      + '</tr>';
  };

  function initResponsiveTableLabels() {
    const sync = (table) => {
      const headers = Array.from(table.querySelectorAll('thead th')).map(th => text(th.textContent) || (th.querySelector('input[type="checkbox"]') ? 'Chọn' : ''));
      table.querySelectorAll('tbody tr').forEach(row => {
        if (row.classList.contains('ds-group-row')) return;
        Array.from(row.children).forEach((cell, index) => {
          if (!cell.hasAttribute('data-label') && headers[index]) cell.setAttribute('data-label', headers[index]);
        });
      });
    };
    const syncRoot = (root = document) => {
      const tables = new Set();
      if (root?.matches?.('table')) tables.add(root);
      root?.querySelectorAll?.('table').forEach(table => tables.add(table));
      tables.forEach(sync);
    };
    syncRoot(document);
    window.thon09SyncResponsiveTableLabels = syncRoot;
  }

  function updateMobileActions() {
    if (!hasSession()) {
      setMobileChromeVisible(false);
      return;
    }
    setMobileChromeVisible(true);
    const fab = document.querySelector('.mobile-fab');
    document.querySelectorAll('.mobile-bottom-nav [data-mobile-screen], .mobile-bottom-nav [data-screen]').forEach(button => {
      const screenName = button.dataset.mobileScreen || button.dataset.screen;
      const currentScreen = (window.App && App.screen) || localStorage.getItem('thon09_screen') || 'dashboard';
      button.classList.toggle('active', screenName === currentScreen);
    });
    if (!fab) return;
    const screen = (window.App && App.screen) || localStorage.getItem('thon09_screen') || 'dashboard';
    const config = {
      households: { icon: 'fa-plus', label: 'Thêm hộ', action: () => window.openHouseholdForm && window.openHouseholdForm() },
      persons: { icon: 'fa-plus', label: 'Thêm nhân khẩu', action: () => window.openPersonForm && window.openPersonForm() },
      gis: { icon: 'fa-draw-polygon', label: 'Vẽ khu vực', action: () => document.querySelector('#gisDrawBtn')?.click() }
    }[screen];
    fab.classList.toggle('d-none', !config);
    if (!config) return;
    fab.innerHTML = '<i class="fa-solid ' + config.icon + '"></i><span>' + config.label + '</span>';
    fab.onclick = config.action;
  }

  function initMobileActions() {
    if (!hasSession()) {
      setMobileChromeVisible(false);
      return;
    }
    if (!document.querySelector('.mobile-bottom-nav')) {
      const nav = document.createElement('nav');
      nav.className = 'mobile-bottom-nav';
      nav.setAttribute('aria-label', 'Điều hướng nhanh');
      nav.innerHTML = [
        ['dashboard', 'fa-gauge-high', 'Tổng quan'],
        ['households', 'fa-house-chimney', 'Hộ'],
        ['persons', 'fa-users', 'Nhân khẩu'],
        ['gis', 'fa-map-location-dot', 'GIS'],
        ['reports', 'fa-chart-pie', 'Báo cáo']
      ].map(item => '<button type="button" data-mobile-screen="' + item[0] + '"><i class="fa-solid ' + item[1] + '"></i><span>' + item[2] + '</span></button>').join('');
      document.body.appendChild(nav);
    }
    if (!document.body.dataset.mobileV1NavBound) {
      document.body.dataset.mobileV1NavBound = '1';
      document.addEventListener('click', event => {
        const button = event.target.closest('.mobile-bottom-nav [data-mobile-screen], .mobile-bottom-nav [data-screen]');
        if (!button || typeof window.switchScreen !== 'function') return;
        const screen = button.dataset.mobileScreen || button.dataset.screen;
        if (!screen) return;
        event.preventDefault();
        window.switchScreen(screen);
        setTimeout(updateMobileActions, 0);
        document.querySelector('.sidebar')?.classList.remove('open');
        document.body.classList.remove('sidebar-open');
      }, true);
    }
    if (!document.querySelector('.mobile-fab')) {
      const fab = document.createElement('button');
      fab.type = 'button';
      fab.className = 'mobile-fab d-none';
      document.body.appendChild(fab);
    }
    const originalSwitch = window.switchScreen;
    if (typeof originalSwitch === 'function' && !originalSwitch.__mobileWrapped) {
      window.switchScreen = function wrappedSwitchScreen(screen) {
        const result = originalSwitch.apply(this, arguments);
        setTimeout(updateMobileActions, 0);
        return result;
      };
      window.switchScreen.__mobileWrapped = true;
    }
    updateMobileActions();
  }

  function currentScreen() {
    return (window.App && App.screen) || localStorage.getItem('thon09_screen') || 'dashboard';
  }

  function refreshCurrentScreen() {
    if (!hasSession()) return;
    const loaders = {
      dashboard: window.loadDashboard,
      households: window.loadHouseholds,
      persons: window.loadPersons,
      gis: window.loadGisAreas,
      reports: window.thon09ViewReport
    };
    const loader = loaders[currentScreen()];
    if (typeof loader !== 'function') return;
    const result = loader();
    if (result && typeof result.catch === 'function') {
      result.catch(error => {
        if (typeof window.showToast === 'function') window.showToast(error.message || 'Không tải lại được dữ liệu.', 'danger');
      });
    }
  }

  function initPullToRefresh() {
    if (!hasSession()) return;
    if (document.body.dataset.mobilePullRefreshBound) return;
    document.body.dataset.mobilePullRefreshBound = '1';
    const indicator = document.createElement('div');
    indicator.className = 'mobile-refresh-indicator';
    indicator.innerHTML = '<i class="fa-solid fa-arrow-rotate-right"></i><span>Kéo để tải lại</span>';
    document.body.appendChild(indicator);
    let startY = 0;
    let pulling = false;
    document.addEventListener('touchstart', event => {
      if (!hasSession()) return;
      if (window.innerWidth >= 1200 || window.scrollY > 0 || event.touches.length !== 1) return;
      startY = event.touches[0].clientY;
      pulling = true;
    }, { passive: true });
    document.addEventListener('touchmove', event => {
      if (!pulling) return;
      const diff = event.touches[0].clientY - startY;
      if (diff <= 0) return;
      indicator.classList.toggle('ready', diff > 72);
      indicator.style.transform = 'translate(-50%, ' + Math.min(diff / 2, 64) + 'px)';
      indicator.style.opacity = Math.min(diff / 72, 1);
    }, { passive: true });
    document.addEventListener('touchend', event => {
      if (!pulling) return;
      const diff = (event.changedTouches[0] ? event.changedTouches[0].clientY : startY) - startY;
      pulling = false;
      indicator.style.transform = '';
      indicator.style.opacity = '';
      indicator.classList.remove('ready');
      if (diff > 72) refreshCurrentScreen();
    }, { passive: true });
  }

  function initMobileFilterSheet() {
    if (!hasSession()) return;
    if (document.body.dataset.mobileSheetBound) return;
    document.body.dataset.mobileSheetBound = '1';
    const backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'mobile-sheet-backdrop d-none';
    backdrop.setAttribute('aria-label', 'Đóng bộ lọc');
    document.body.appendChild(backdrop);
    const closeSheet = () => {
      document.querySelectorAll('.mobile-bottom-sheet.is-open').forEach(panel => panel.classList.remove('is-open'));
      backdrop.classList.add('d-none');
    };
    backdrop.addEventListener('click', closeSheet);
    document.addEventListener('click', event => {
      if (window.innerWidth > 767) return;
      const trigger = event.target.closest('#personAdvancedToggle, .person-advanced-toggle');
      if (!trigger) return;
      setTimeout(() => {
        const panel = document.querySelector('#personAdvancedFilters');
        if (!panel || panel.classList.contains('d-none')) return;
        panel.classList.add('mobile-bottom-sheet', 'is-open');
        backdrop.classList.remove('d-none');
      }, 0);
    });
    document.addEventListener('click', event => {
      if (event.target.closest('#personAdvancedApply, #personAdvancedClear, #personFilterReset')) {
        setTimeout(closeSheet, 0);
      }
    });
  }

  function initScreenTransitions() {
    if (!hasSession()) return;
    if (document.body.dataset.mobileTransitionBound) return;
    document.body.dataset.mobileTransitionBound = '1';
    const originalSwitch = window.switchScreen;
    if (typeof originalSwitch !== 'function' || originalSwitch.__mobileTransitionWrapped) return;
    window.switchScreen = function switchScreenWithTransition(screen) {
      document.body.classList.add('mobile-screen-changing');
      const result = originalSwitch.apply(this, arguments);
      window.requestAnimationFrame(() => {
        if (window.thon09SyncResponsiveTableLabels) window.thon09SyncResponsiveTableLabels(document);
      });
      window.setTimeout(() => document.body.classList.remove('mobile-screen-changing'), 180);
      return result;
    };
    window.switchScreen.__mobileTransitionWrapped = true;
  }

  function boot() {
    watchSessionToken();
    initResponsiveTableLabels();
    if (hasSession()) {
      initMobileActions();
      initPullToRefresh();
      initMobileFilterSheet();
      initScreenTransitions();
    } else {
      setMobileChromeVisible(false);
    }
  }

  window.thon09InitMobileUi = function thon09InitMobileUi() {
    initMobileActions();
    initPullToRefresh();
    initMobileFilterSheet();
    initScreenTransitions();
    updateMobileActions();
  };

  document.addEventListener('thon09:auth-state', event => {
    if (event.detail && event.detail.authenticated) window.thon09InitMobileUi();
    else setMobileChromeVisible(false);
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
