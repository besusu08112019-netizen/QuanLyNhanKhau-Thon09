(function () {
  'use strict';

  const MOBILE_QUERY = '(max-width: 1199.98px)';
  const mq = window.matchMedia ? window.matchMedia(MOBILE_QUERY) : { matches: false };
  let activeSheet = null;

  function isMobile() {
    return !!mq.matches;
  }

  function hasSession() {
    return !!(localStorage.getItem('thon09_token') || (window.App && App.token));
  }

  function text(value) {
    return value === null || value === undefined ? '' : String(value).trim();
  }

  function getUserText() {
    const current = document.getElementById('currentUser');
    const raw = text(current ? current.textContent : '');
    return raw || 'Tai khoan';
  }

  function getRoleText() {
    const raw = getUserText();
    const knownRoles = ['Super Admin', 'Admin', 'Khach', 'Can bo', 'Quan tri'];
    const role = knownRoles.find(item => raw.toLowerCase().includes(item.toLowerCase()));
    return role || '';
  }

  function ensureUserMenu() {
    const topbar = document.querySelector('.topbar-meta');
    if (!topbar || document.querySelector('.mobile-user-menu-btn')) return;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'mobile-user-menu-btn';
    button.setAttribute('aria-haspopup', 'true');
    button.setAttribute('aria-expanded', 'false');
    button.innerHTML = '<i class="fa-regular fa-user"></i><span>Tai khoan</span>';

    const popover = document.createElement('div');
    popover.className = 'mobile-user-popover';
    popover.hidden = true;
    popover.innerHTML = [
      '<strong class="mobile-user-name"></strong>',
      '<small class="mobile-user-role"></small>',
      '<button type="button" class="mobile-user-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Dang xuat</span></button>'
    ].join('');

    button.addEventListener('click', event => {
      event.preventDefault();
      const open = popover.hidden;
      popover.hidden = !open;
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
      popover.querySelector('.mobile-user-name').textContent = getUserText();
      popover.querySelector('.mobile-user-role').textContent = getRoleText();
    });

    popover.querySelector('.mobile-user-logout').addEventListener('click', event => {
      event.preventDefault();
      const logout = document.getElementById('logoutBtn');
      if (logout) logout.click();
      else if (typeof window.logout === 'function') window.logout();
    });

    document.addEventListener('click', event => {
      if (popover.hidden || event.target.closest('.mobile-user-menu-btn, .mobile-user-popover')) return;
      popover.hidden = true;
      button.setAttribute('aria-expanded', 'false');
    });

    topbar.appendChild(button);
    topbar.appendChild(popover);
  }

  function createFilterTrigger(card, label) {
    let trigger = card.querySelector(':scope > .mobile-filter-trigger');
    if (trigger) return trigger;
    trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'mobile-filter-trigger';
    trigger.innerHTML = '<i class="fa-solid fa-sliders"></i><span>' + label + '</span>';
    card.appendChild(trigger);
    return trigger;
  }

  function ensureSheet() {
    let backdrop = document.querySelector('.mobile-filter-sheet-backdrop');
    let sheet = document.querySelector('.mobile-filter-sheet');
    if (backdrop && sheet) return { backdrop, sheet };

    backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'mobile-filter-sheet-backdrop';
    backdrop.setAttribute('aria-label', 'Dong bo loc');
    backdrop.hidden = true;

    sheet = document.createElement('aside');
    sheet.className = 'mobile-filter-sheet';
    sheet.setAttribute('role', 'dialog');
    sheet.setAttribute('aria-modal', 'true');
    sheet.hidden = true;
    sheet.innerHTML = [
      '<div class="sheet-handle" aria-hidden="true"></div>',
      '<div class="sheet-head">',
      '<strong>Bo loc</strong>',
      '<button type="button" class="sheet-close" aria-label="Dong"><i class="fa-solid fa-xmark"></i></button>',
      '</div>',
      '<div class="sheet-grid"></div>'
    ].join('');

    document.body.appendChild(backdrop);
    document.body.appendChild(sheet);

    backdrop.addEventListener('click', closeFilterSheet);
    sheet.querySelector('.sheet-close').addEventListener('click', closeFilterSheet);
    bindSheetSwipe(sheet);
    return { backdrop, sheet };
  }

  function bindSheetSwipe(sheet) {
    if (sheet.dataset.swipeBound) return;
    sheet.dataset.swipeBound = '1';
    let startY = 0;
    sheet.addEventListener('touchstart', event => {
      if (!event.touches.length) return;
      startY = event.touches[0].clientY;
    }, { passive: true });
    sheet.addEventListener('touchend', event => {
      const touch = event.changedTouches[0];
      if (!touch) return;
      if (touch.clientY - startY > 80) closeFilterSheet();
    }, { passive: true });
  }

  function moveNodesToSheet(config) {
    const { sheet } = ensureSheet();
    const grid = sheet.querySelector('.sheet-grid');
    grid.innerHTML = '';
    const moved = [];
    config.nodes.forEach(node => {
      if (!node || node.classList.contains('mobile-filter-trigger')) return;
      const marker = document.createComment('mobile-filter-v2');
      const hadDNone = node.classList.contains('d-none');
      node.parentNode.insertBefore(marker, node);
      if (hadDNone) node.classList.remove('d-none');
      grid.appendChild(node);
      moved.push({ node, marker, hadDNone });
    });
    activeSheet = { moved, title: config.title };
    sheet.querySelector('.sheet-head strong').textContent = config.title;
  }

  function openFilterSheet(config) {
    if (!isMobile()) return;
    if (activeSheet) closeFilterSheet();
    moveNodesToSheet(config);
    const { backdrop, sheet } = ensureSheet();
    backdrop.hidden = false;
    sheet.hidden = false;
    requestAnimationFrame(() => {
      backdrop.classList.add('is-open');
      sheet.classList.add('is-open');
    });
    document.body.classList.add('mobile-filter-open');
  }

  function closeFilterSheet() {
    const backdrop = document.querySelector('.mobile-filter-sheet-backdrop');
    const sheet = document.querySelector('.mobile-filter-sheet');
    if (sheet) sheet.classList.remove('is-open');
    if (backdrop) backdrop.classList.remove('is-open');
    document.body.classList.remove('mobile-filter-open');

    if (activeSheet) {
      activeSheet.moved.forEach(({ node, marker, hadDNone }) => {
        if (marker.parentNode) marker.parentNode.insertBefore(node, marker);
        if (hadDNone) node.classList.add('d-none');
        marker.remove();
      });
      activeSheet = null;
    }

    window.setTimeout(() => {
      if (sheet) sheet.hidden = true;
      if (backdrop) backdrop.hidden = true;
    }, 180);
  }

  function collectChildren(parent, skipSelector) {
    if (!parent) return [];
    return Array.from(parent.children).filter(child => !skipSelector || !child.matches(skipSelector));
  }

  function initFilterSheets() {
    const householdCard = document.querySelector('.household-filter-card');
    const householdGrid = householdCard?.querySelector('.household-filter-grid');
    if (householdCard && householdGrid && !householdCard.dataset.mobileFilterV2) {
      householdCard.dataset.mobileFilterV2 = '1';
      createFilterTrigger(householdCard, 'Bo loc').addEventListener('click', () => {
        const nodes = collectChildren(householdGrid, '.household-search-field');
        openFilterSheet({ title: 'Bo loc ho gia dinh', nodes });
      });
    }

    const personCard = document.querySelector('.person-search-card');
    const personGrid = personCard?.querySelector('.person-quick-filter-grid');
    if (personCard && personGrid && !personCard.dataset.mobileFilterV2) {
      personCard.dataset.mobileFilterV2 = '1';
      createFilterTrigger(personCard, 'Bo loc').addEventListener('click', () => {
        const nodes = collectChildren(personGrid);
        const advanced = document.getElementById('personAdvancedFilters');
        openFilterSheet({ title: 'Bo loc nhan khau', nodes: advanced ? nodes.concat([advanced]) : nodes });
      });
    }

    const reportCard = document.querySelector('.report-filter-card');
    const reportGrid = reportCard?.querySelector('.report-filter-grid');
    if (reportCard && reportGrid && !reportCard.dataset.mobileFilterV2) {
      reportCard.dataset.mobileFilterV2 = '1';
      createFilterTrigger(reportCard, 'Bo loc bao cao').addEventListener('click', () => {
        const nodes = collectChildren(reportGrid, '.report-type-field');
        openFilterSheet({ title: 'Bo loc bao cao', nodes });
      });
    }
  }

  function closeSheetsOnDesktop() {
    if (!isMobile()) closeFilterSheet();
  }

  function bindPopupSwipeClose() {
    if (document.body.dataset.mobilePopupSwipeV2) return;
    document.body.dataset.mobilePopupSwipeV2 = '1';
    let startY = 0;
    document.addEventListener('touchstart', event => {
      const modal = event.target.closest('.modal.show .modal-content');
      if (!modal || !event.touches.length) return;
      startY = event.touches[0].clientY;
    }, { passive: true });
    document.addEventListener('touchend', event => {
      const modal = event.target.closest('.modal.show');
      const touch = event.changedTouches[0];
      if (!modal || !touch || touch.clientY - startY < 120) return;
      const instance = window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getInstance(modal) : null;
      if (instance) instance.hide();
    }, { passive: true });
  }

  function boot() {
    if (!hasSession()) return;
    ensureUserMenu();
    initFilterSheets();
    bindPopupSwipeClose();
    closeSheetsOnDesktop();
  }

  window.thon09InitMobileUiV2 = boot;
  document.addEventListener('DOMContentLoaded', boot);
  document.addEventListener('thon09:auth-state', event => {
    if (event.detail && event.detail.authenticated) window.setTimeout(boot, 0);
    else closeFilterSheet();
  });
  document.addEventListener('thon09:screen-change', () => window.setTimeout(boot, 0));
  window.addEventListener('resize', closeSheetsOnDesktop, { passive: true });

  if (document.readyState !== 'loading') boot();
})();
