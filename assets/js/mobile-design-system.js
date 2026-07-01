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
      day = Number(match[1]); month = Number(match[2]); year = Number(match[3]);
    } else {
      match = text.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/);
      if (!match) return null;
      year = Number(match[1]); month = Number(match[2]); day = Number(match[3]);
    }
    const date = new Date(year, month - 1, day);
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) return null;
    return date;
  }

  function ageFromDate(value) {
    const date = parseDate(value);
    if (!date) return null;
    const now = new Date();
    let age = now.getFullYear() - date.getFullYear();
    const beforeBirthday = now.getMonth() < date.getMonth() || (now.getMonth() === date.getMonth() && now.getDate() < date.getDate());
    if (beforeBirthday) age -= 1;
    return age >= 0 && age < 130 ? age : null;
  }

  function maskIdentity(value) {
    const text = String(value || '').replace(/\s+/g, '').trim();
    if (text.length <= 6) return text;
    return text.slice(0, 3) + '••••••' + text.slice(-3);
  }

  function setRole(cell, role, order, label) {
    if (!cell) return;
    cell.dataset.mobileRole = role;
    if (order) cell.dataset.mobilePersonOrder = String(order);
    if (label) cell.setAttribute('data-label', label);
    const value = cell.textContent.replace(/\s+/g, ' ').trim();
    cell.toggleAttribute('data-mobile-empty', isEmpty(value));
  }

  function toneForResidence(value) {
    const text = normalize(value);
    if (text.includes('tam vang') || text.includes('di vang')) return 'warning';
    if (text.includes('tam tru')) return 'info';
    if (text.includes('chuyen di') || text.includes('da chuyen')) return 'danger';
    if (text.includes('da mat') || text.includes('mat') || text.includes('chet')) return 'dark';
    if (text.includes('thuong tru') || text.includes('o nha')) return 'success';
    return 'neutral';
  }

  function normalizeResidenceText(cell) {
    if (!cell) return;
    const target = cell.querySelector('.badge, .badge-soft, .person-badge, span, a, button') || cell;
    const text = normalize(target.textContent);
    if (text.includes('tam vang') || text.includes('di vang')) target.textContent = 'Tạm vắng';
    else if (text.includes('tam tru')) target.textContent = 'Tạm trú';
    else if (text.includes('chuyen di') || text.includes('da chuyen')) target.textContent = 'Chuyển đi';
    else if (text.includes('da mat') || text.includes('mat') || text.includes('chet')) target.textContent = 'Đã mất';
    else if (text.includes('thuong tru') || text.includes('o nha')) target.textContent = 'Thường trú';
  }

  function ensureAge(cell) {
    if (!cell || cell.dataset.mobileAgeDone === '1') return;
    const text = cell.textContent.replace(/\s+/g, ' ').trim();
    const age = ageFromDate(text);
    if (age === null) return;
    const span = document.createElement('span');
    span.className = 'mobile-age-text';
    span.textContent = ' (' + age + ' tuổi)';
    cell.appendChild(span);
    cell.dataset.mobileAgeDone = '1';
  }

  function ensureMaskedIdentity(cell) {
    if (!cell || cell.dataset.mobileMaskDone === '1') return;
    const text = cell.textContent.replace(/\s+/g, '').trim();
    if (isEmpty(text)) return;
    cell.textContent = '';
    const desktop = document.createElement('span');
    desktop.className = 'desktop-sensitive';
    desktop.textContent = text;
    const mobile = document.createElement('span');
    mobile.className = 'mobile-sensitive';
    mobile.textContent = maskIdentity(text);
    cell.append(desktop, mobile);
    cell.dataset.mobileMaskDone = '1';
  }

  function enhancePersonRows(root) {
    const rows = root.querySelectorAll ? root.querySelectorAll('#personRows tr:not(.group-row)') : [];
    rows.forEach(row => {
      const cells = row.children;
      if (!cells || cells.length < 10) return;
      setRole(cells[0], 'select', 0, '');
      setRole(cells[3], 'title', 1, 'Họ tên');

      setRole(cells[7], 'badge', 2, 'Cư trú');
      cells[7].dataset.mobileTone = toneForResidence(cells[7].textContent);
      normalizeResidenceText(cells[7]);

      setRole(cells[1], 'header-meta', 3, 'Mã hộ');
      setRole(cells[4], 'info', 4, 'Ngày sinh');
      ensureAge(cells[4]);
      setRole(cells[5], 'info', 5, 'Giới tính');
      setRole(cells[6], 'info', 6, 'CCCD');
      ensureMaskedIdentity(cells[6]);
      setRole(cells[2], 'meta', 7, 'Mã nhân khẩu');
      setRole(cells[8], 'badge', 8, 'Đảng viên');
      cells[8].dataset.mobileTone = normalize(cells[8].textContent).includes('co') ? 'success' : 'neutral';
      setRole(cells[9], 'actions', 20, '');
    });
  }

  function enhanceCommonRows(root) {
    const scope = root.querySelectorAll ? root : document;
    scope.querySelectorAll('tbody tr:not(.group-row)').forEach(row => {
      Array.from(row.children).forEach(cell => {
        const value = cell.textContent.replace(/\s+/g, ' ').trim();
        if (!cell.querySelector('button, .btn, input, select, textarea') && isEmpty(value)) {
          cell.setAttribute('data-mobile-empty', 'true');
        }
      });
    });
    scope.querySelectorAll('td[data-mobile-role="actions"] button, td[data-mobile-role="actions"] .btn, td.text-end button, td.text-end .btn').forEach(button => {
      const text = normalize(button.textContent);
      if (text.includes('xem')) button.dataset.mobileAction = 'view';
      else if (text.includes('sua')) button.dataset.mobileAction = 'edit';
      else if (text.includes('xoa')) button.dataset.mobileAction = 'delete';
    });
  }

  function enhance(root) {
    try {
      const target = root && root.nodeType === 1 ? root : document;
      enhanceCommonRows(target);
      enhancePersonRows(target);
    } catch (error) {
      console.warn('Mobile design system enhancer skipped:', error);
    }
  }

  function start() {
    enhance(document);
    if (window.__thon09MobileDesignSystemObserver) return;
    window.__thon09MobileDesignSystemObserver = true;
    const observer = new MutationObserver(mutations => {
      for (const mutation of mutations) {
        mutation.addedNodes.forEach(node => {
          if (node.nodeType === 1) enhance(node);
        });
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
