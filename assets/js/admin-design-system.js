(function () {
  'use strict';

  const EMPTY = new Set(['', '-', '--', '---', 'n/a', 'na', 'null', 'undefined', 'khong co du lieu', 'không có dữ liệu']);

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
    return EMPTY.has(normalize(value));
  }

  function parseDate(value) {
    const text = String(value || '').trim();
    let match = text.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
    if (match) return new Date(Number(match[3]), Number(match[2]) - 1, Number(match[1]));
    match = text.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/);
    if (match) return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    return null;
  }

  function age(value) {
    const date = parseDate(value);
    if (!date || Number.isNaN(date.getTime())) return null;
    const now = new Date();
    let years = now.getFullYear() - date.getFullYear();
    if (now.getMonth() < date.getMonth() || (now.getMonth() === date.getMonth() && now.getDate() < date.getDate())) years -= 1;
    return years >= 0 && years <= 130 ? years : null;
  }

  function maskIdentity(value) {
    const text = String(value || '').replace(/\s+/g, '').trim();
    if (text.length <= 6) return text;
    return text.slice(0, 3) + '••••••' + text.slice(-3);
  }

  function isListContext(node) {
    return Boolean(node.closest('tbody, .module-card-list, .table-responsive')) && !node.closest('.modal, [role="dialog"], .detail-card, .person-detail-card');
  }

  function hideEmptyFields(root) {
    root.querySelectorAll?.('td, .info-item, .detail-item, .form-readonly-field, .profile-field, .kv-row, dl div, .modal-body li').forEach(el => {
      if (el.querySelector('input, select, textarea, button, .btn')) return;
      const text = el.textContent.replace(/\s+/g, ' ').trim();
      if (isEmpty(text) || /^([^:：]+[:：])\s*$/.test(text)) el.setAttribute('data-empty-hidden', 'true');
    });
  }

  function enhanceAge(root) {
    root.querySelectorAll?.('[data-label], td, .detail-item, .info-item').forEach(el => {
      if (el.dataset.ageEnhanced === '1') return;
      const label = normalize(el.getAttribute('data-label') || el.querySelector('.label, dt, strong')?.textContent || '');
      if (!label.includes('ngay sinh')) return;
      const text = el.textContent.replace(/\s+/g, ' ').trim();
      const years = age(text);
      if (years === null) return;
      const span = document.createElement('span');
      span.className = 'ds-age-chip';
      span.textContent = ' (' + years + ' tuổi)';
      el.appendChild(span);
      el.dataset.ageEnhanced = '1';
    });
  }

  function enhanceIdentityMask(root) {
    root.querySelectorAll?.('td[data-label], td').forEach(el => {
      if (!isListContext(el) || el.dataset.identityMasked === '1') return;
      const label = normalize(el.getAttribute('data-label') || '');
      if (!(label.includes('cccd') || label.includes('dinh danh'))) return;
      const text = el.textContent.replace(/\s+/g, '').trim();
      if (isEmpty(text)) return;
      el.textContent = maskIdentity(text);
      el.dataset.identityMasked = '1';
    });
  }

  function applyBadgeTone(el, tone, text) {
    const cell = el.closest('td') || el;
    cell.dataset.mobileTone = tone;
    el.classList.remove('badge-resident', 'badge-away', 'badge-temporary', 'badge-moved', 'badge-deceased');
    if (tone === 'success') el.classList.add('badge-resident');
    if (tone === 'warning') el.classList.add('badge-away');
    if (tone === 'info') el.classList.add('badge-temporary');
    if (tone === 'danger') el.classList.add('badge-moved');
    if (tone === 'dark') el.classList.add('badge-deceased');
    if (text) el.textContent = text;
  }

  function normalizeResidenceBadges(root) {
    root.querySelectorAll?.('.badge, .badge-soft, .person-badge, td[data-mobile-role="badge"] span').forEach(el => {
      const text = normalize(el.textContent);
      if (!text) return;
      if (text.includes('tam vang') || text.includes('di vang')) applyBadgeTone(el, 'warning', 'Tạm vắng');
      else if (text.includes('tam tru')) applyBadgeTone(el, 'info', 'Tạm trú');
      else if (text.includes('chuyen di') || text.includes('da chuyen')) applyBadgeTone(el, 'danger', 'Chuyển đi');
      else if (text.includes('da mat') || text.includes('khai tu') || text.includes('chet')) applyBadgeTone(el, 'dark', 'Đã mất');
      else if (text.includes('thuong tru') || text.includes('o nha')) applyBadgeTone(el, 'success', 'Thường trú');
    });
  }

  function enhance(root) {
    try {
      hideEmptyFields(root);
      enhanceAge(root);
      enhanceIdentityMask(root);
      normalizeResidenceBadges(root);
    } catch (error) {
      console.warn('Admin design system enhancer skipped:', error);
    }
  }

  function start() {
    enhance(document);
    if (window.__thon09AdminDesignObserver) return;
    window.__thon09AdminDesignObserver = true;
    new MutationObserver(mutations => {
      mutations.forEach(mutation => mutation.addedNodes.forEach(node => {
        if (node.nodeType === 1) enhance(node);
      }));
    }).observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
