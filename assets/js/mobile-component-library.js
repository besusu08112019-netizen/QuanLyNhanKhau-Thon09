(function (window, document) {
  'use strict';

  var mobileQuery = window.matchMedia ? window.matchMedia('(max-width: 1024px)') : null;
  var scheduled = false;
  var moduleShellEnabled = true;
  var filterStates = Object.create(null);
  var enabledModuleScreens = [
    'householdsScreen', 'personsScreen', 'temporaryResidenceScreen', 'temporaryAbsenceScreen', 'movementsScreen',
    'partyMembersScreen', 'workTasksScreen', 'workCalendarScreen', 'documentsScreen', 'financeScreen',
    'gisScreen', 'publicAssetsScreen', 'housesScreen', 'vehiclesScreen', 'agriculturalLandScreen',
    'businessHouseholdsScreen', 'contributionsScreen', 'agricultureScreen', 'livestockScreen', 'photoGalleryScreen', 'complaintsScreen', 'dataQualityScreen', 'reportsScreen',
    'operationCenterScreen', 'importScreen', 'exportExcelScreen', 'printFormsScreen',
    'systemAdminScreen', 'usersScreen', 'permissionsScreen', 'logsScreen', 'backupsScreen', 'restoreScreen', 'settingsScreen', 'appearanceScreen'
  ];
  var enabledModuleDashboards = ['dashboardHouseholds', 'dashboardPopulation', 'dashboardBusiness', 'dashboardVehicles', 'dashboardLivestock', 'dashboardGis', 'dashboardReports'];

  function text(value) {
    if (value && value.nodeType) return String(value.textContent || '').replace(/\s+/g, ' ').trim();
    return String(value == null ? '' : value);
  }

  function number(value) {
    var raw = Number(value || 0);
    if (!Number.isFinite(raw)) raw = 0;
    return raw.toLocaleString('vi-VN');
  }

  function normalizeSearchText(value) {
    var normalized = text(value).toLowerCase().replace(/Ä‘/g, 'd').replace(/Ä/g, 'd');
    if (normalized.normalize) normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return normalized.replace(/[^\p{L}\p{N}\s-]/gu, ' ').replace(/\s+/g, ' ').trim();
  }

  function filterFieldKey(field, fallback) {
    if (field && field.key) return field.key;
    if (field && field.name) return field.name;
    if (field && field.type === 'search') return 'search';
    if (field && field.type === 'select') return 'status';
    return fallback || 'field';
  }

  function el(tag, className, attrs) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    Object.keys(attrs || {}).forEach(function (key) {
      if (attrs[key] == null) return;
      node.setAttribute(key, attrs[key]);
    });
    return node;
  }

  function icon(className) {
    var node = el('i', 'fa-solid ' + className, { 'aria-hidden': 'true' });
    return node;
  }

  function append(parent, children) {
    children.forEach(function (child) {
      if (child == null) return;
      parent.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
    });
    return parent;
  }

  function AppIconButton(options) {
    var button = el('button', 'app-v2-icon-button', {
      type: 'button',
      'aria-label': options.label || options.title || 'Thao tÃ¡c',
      title: options.title || options.label || ''
    });
    if (options.action) button.setAttribute('data-screen', options.action);
    if (options.proxy) button.setAttribute('data-app-v2-proxy-click', options.proxy);
    append(button, [icon(options.icon || 'fa-ellipsis')]);
    return button;
  }

  function AppButton(options) {
    var button = el('button', 'app-v2-button', { type: 'button' });
    if (options.action) button.setAttribute('data-screen', options.action);
    append(button, [icon(options.icon || 'fa-arrow-right'), el('span', '', null)]);
    button.lastChild.textContent = options.label || 'Má»Ÿ';
    return button;
  }

  function AppFab(options) {
    var button = el('button', 'app-v2-fab', {
      type: 'button',
      'aria-label': options.label || 'Add new',
      title: options.label || 'Add new'
    });
    if (options.action) button.setAttribute('data-screen', options.action);
    if (options.proxy) button.setAttribute('data-app-v2-proxy-click', options.proxy);
    append(button, [icon(options.icon || 'fa-plus'), el('span', '', null)]);
    button.lastChild.textContent = options.label || 'Add new';
    return button;
  }

  function AppBadge(options) {
    var badge = el('span', 'app-v2-badge', { 'data-tone': options.tone || 'primary' });
    badge.textContent = options.label || '';
    return badge;
  }

  function AppStatusChip(options) {
    return AppBadge({
      label: options && options.label ? options.label : 'Tráº¡ng thÃ¡i',
      tone: options && options.tone ? options.tone : 'neutral'
    });
  }

  function AppToolbar(items) {
    var toolbar = el('div', 'app-v2-toolbar', { role: 'toolbar' });
    (items || []).forEach(function (item) {
      var chip = el(item.action ? 'button' : 'span', 'app-v2-chip', item.action ? { type: 'button', 'data-screen': item.action } : {});
      append(chip, [icon(item.icon || 'fa-circle'), text(item.label || '')]);
      toolbar.appendChild(chip);
    });
    return toolbar;
  }

  function AppInput(options) {
    var field = el('div', 'app-v2-field');
    var id = options.id || 'app-v2-input-' + Math.random().toString(36).slice(2);
    var label = el('label', '', { for: id });
    var input = el('input', 'app-v2-input', {
      id: id,
      type: options.type || 'text',
      placeholder: options.placeholder || '',
      autocomplete: options.autocomplete || 'off',
      'data-app-v2-filter-field': filterFieldKey(options)
    });
    label.textContent = options.label || '';
    if (options.value != null) input.value = options.value;
    append(field, [label, input]);
    return field;
  }

  function AppSelect(options) {
    var field = el('div', 'app-v2-field');
    var id = options.id || 'app-v2-select-' + Math.random().toString(36).slice(2);
    var label = el('label', '', { for: id });
    var select = el('select', 'app-v2-select', { id: id, 'data-app-v2-filter-field': filterFieldKey(options) });
    label.textContent = options.label || '';
    (options.options || []).forEach(function (item) {
      var option = el('option');
      option.value = item.value == null ? item.label : item.value;
      option.textContent = item.label || item.value || '';
      select.appendChild(option);
    });
    if (options.value != null) select.value = options.value;
    append(field, [label, select]);
    return field;
  }

  function readFilterState(root) {
    var state = {};
    Array.from(root.querySelectorAll('[data-app-v2-filter-field]')).forEach(function (control) {
      var key = control.getAttribute('data-app-v2-filter-field') || control.name || 'field';
      state[key] = control.value || '';
    });
    if (state.keyword && !state.search) state.search = state.keyword;
    if (state.searchText && !state.search) state.search = state.searchText;
    return state;
  }

  function emitFilterChange(root) {
    root.dispatchEvent(new CustomEvent('app-v2-filter-change', {
      bubbles: true,
      detail: { state: readFilterState(root) }
    }));
  }

  function resetFilterControls(root) {
    Array.from(root.querySelectorAll('[data-app-v2-filter-field]')).forEach(function (control) {
      control.value = '';
    });
    emitFilterChange(root);
  }

  function AppTabs(items, selected) {
    var tabs = el('div', 'app-v2-tabs', { role: 'tablist' });
    (items || []).forEach(function (item, index) {
      var key = item.key || String(index);
      var tab = el('button', 'app-v2-tab', {
        type: 'button',
        role: 'tab',
        'aria-selected': key === selected ? 'true' : 'false'
      });
      if (item.action) tab.setAttribute('data-screen', item.action);
      tab.textContent = item.label || '';
      tabs.appendChild(tab);
    });
    return tabs;
  }

  function AppSearchControl(field) {
    var search = el('label', 'app-v2-search', { 'aria-label': field.label || 'TÃ¬m kiáº¿m' });
    var input = el('input', '', {
      type: 'search',
      placeholder: field.placeholder || '',
      autocomplete: 'off',
      inputmode: 'search',
      'data-app-v2-filter-field': filterFieldKey(field)
    });
    if (field.value != null) input.value = field.value;
    append(search, [icon(field.icon || 'fa-magnifying-glass'), input]);
    return search;
  }

  function AppFilterBar(options) {
    var sheet = el('section', 'app-v2-filter-sheet app-v2-filter-bar', { 'aria-label': options.label || 'Bá»™ lá»c' });
    (options.fields || []).forEach(function (field) {
      sheet.appendChild(field.type === 'select' ? AppSelect(field) : (field.type === 'search' ? AppSearchControl(field) : AppInput(field)));
    });
    if (options.actions && options.actions.length) sheet.appendChild(AppToolbar(options.actions));
    var debounceTimer = null;
    sheet.addEventListener('input', function (event) {
      if (!event.target.matches('[data-app-v2-filter-field]')) return;
      window.clearTimeout(debounceTimer);
      debounceTimer = window.setTimeout(function () { emitFilterChange(sheet); }, 120);
    });
    sheet.addEventListener('keyup', function (event) {
      if (!event.target.matches('[data-app-v2-filter-field]')) return;
      window.clearTimeout(debounceTimer);
      debounceTimer = window.setTimeout(function () { emitFilterChange(sheet); }, 120);
    });
    sheet.addEventListener('change', function (event) {
      if (event.target.matches('[data-app-v2-filter-field]')) emitFilterChange(sheet);
    });
    sheet.addEventListener('click', function (event) {
      var button = event.target.closest('button');
      if (!button) return;
      var actionText = normalizeSearchText(button.textContent + ' ' + button.getAttribute('title') + ' ' + button.innerHTML);
      if (/rotate-right|reset|dat lai|lam moi/.test(actionText)) resetFilterControls(sheet);
      else if (/filter|ap dung/.test(actionText)) emitFilterChange(sheet);
    });
    return sheet;
  }

  function AppFilterSheet(options) {
    return AppFilterBar(options);
  }

  function AppMapToolbar(items) {
    var toolbar = el('div', 'app-v2-toolbar app-v2-map-toolbar', { role: 'toolbar', 'aria-label': 'CÃ´ng cá»¥ báº£n Ä‘á»“' });
    (items || []).forEach(function (item) {
      var button = AppIconButton({ icon: item.icon || 'fa-circle', label: item.label || 'CÃ´ng cá»¥', action: item.action || null });
      if (item.proxy) button.setAttribute('data-app-v2-proxy-click', item.proxy);
      toolbar.appendChild(button);
    });
    return toolbar;
  }

  function AppBottomSheet(options) {
    var sheet = el('section', 'app-v2-bottom-sheet', {
      'aria-label': options.label || 'Bottom sheet',
      'data-open': options.open ? 'true' : 'false'
    });
    append(sheet, [el('span', 'app-v2-bottom-sheet-handle')]);
    if (options.title) {
      var title = el('h2', 'app-v2-section-title');
      title.textContent = options.title;
      sheet.appendChild(title);
    }
    if (options.body) sheet.appendChild(options.body);
    return sheet;
  }

  function AppHeader(options) {
    var header = el('section', 'app-v2-hero', { 'aria-label': options.aria || options.title || 'TiÃªu Ä‘á»' });
    var row = el('div', 'app-v2-header');
    var group = el('div', 'app-v2-title-group');
    var eyebrow = el('span', 'app-v2-eyebrow');
    var title = el('h1', 'app-v2-title');
    var subtitle = el('p', 'app-v2-subtitle');
    eyebrow.textContent = options.eyebrow || '';
    title.textContent = options.title || '';
    subtitle.textContent = options.subtitle || '';
    append(group, [eyebrow, title, subtitle]);
    append(row, [
      group,
      AppIconButton({ icon: options.icon || 'fa-bell', label: options.iconLabel || 'ThÃ´ng bÃ¡o' })
    ]);
    append(header, [row]);
    return header;
  }

  function AppSection(options) {
    var section = el('section', 'app-v2-section', { 'aria-label': options.title || 'Khu vá»±c' });
    var head = el('div', 'app-v2-section-head');
    var title = el('h2', 'app-v2-section-title');
    title.textContent = options.title || '';
    var meta = el('span', 'app-v2-section-meta');
    meta.textContent = options.meta || '';
    append(head, [title, meta]);
    append(section, [head]);
    return section;
  }

  function AppCard(options) {
    var card = el('article', 'app-v2-card');
    if (options.title) {
      var title = el('h3', 'app-v2-section-title');
      title.textContent = options.title;
      append(card, [title]);
    }
    if (options.body) append(card, [options.body]);
    return card;
  }

  function AppStatCard(options) {
    var card = el('article', 'app-v2-stat-card');
    var label = el('span', 'app-v2-stat-label');
    var value = el('strong', 'app-v2-stat-value');
    var meta = el('span', 'app-v2-stat-meta');
    var iconWrap = el('span', 'app-v2-card-icon');
    label.textContent = options.label || '';
    value.textContent = options.value || '0';
    meta.textContent = options.meta || '';
    append(iconWrap, [icon(options.icon || 'fa-chart-simple')]);
    append(card, [label, value, meta, iconWrap]);
    return card;
  }

  function AppSummaryCard(options) {
    var card = el('article', 'app-v2-card app-v2-summary-card');
    var iconWrap = el('span', 'app-v2-card-icon');
    var group = el('div', 'app-v2-title-group');
    var label = el('span', 'app-v2-list-title');
    var value = el('p', 'app-v2-summary-value');
    var note = el('p', 'app-v2-summary-note');
    label.textContent = options.label || '';
    value.textContent = options.value || '';
    note.textContent = options.note || '';
    append(iconWrap, [icon(options.icon || 'fa-chart-simple')]);
    append(group, [label, value, note]);
    append(card, [iconWrap, group]);
    return card;
  }

  function updateListSummary(summary, options, total) {
    if (!summary) return;
    var safeTotal = Number(total || 0);
    if (!Number.isFinite(safeTotal)) safeTotal = 0;
    var label = (options && options.label) || 'Danh sÃ¡ch';
    var unit = (options && options.unit) || 'báº£n ghi';
    var labelNode = summary.querySelector('.app-v2-list-summary-label');
    var valueNode = summary.querySelector('.app-v2-list-summary-value');
    if (labelNode) labelNode.textContent = label + ':';
    if (valueNode) valueNode.textContent = number(safeTotal) + ' ' + unit;
    summary.setAttribute('data-app-v2-list-count', String(safeTotal));
  }

  function AppListSummary(options) {
    var summary = el('div', 'app-v2-list-summary', { 'data-app-v2-list-summary': 'true', 'aria-live': 'polite' });
    append(summary, [el('span', 'app-v2-list-summary-label'), el('strong', 'app-v2-list-summary-value')]);
    updateListSummary(summary, options || {}, options && options.total);
    return summary;
  }

  function AppPagination(options) {
    var state = options || {};
    var page = Math.max(1, Number(state.page || 1));
    var totalPages = Math.max(1, Number(state.totalPages || 1));
    var total = Math.max(0, Number(state.total || 0));
    var pager = el('nav', 'app-v2-pagination', {
      'aria-label': 'Phan trang danh sach',
      'data-app-v2-pagination': 'true'
    });
    var previous = el('button', 'app-v2-button app-v2-pagination-button', {
      type: 'button',
      'data-app-v2-page': String(Math.max(1, page - 1))
    });
    var next = el('button', 'app-v2-button app-v2-pagination-button', {
      type: 'button',
      'data-app-v2-page': String(Math.min(totalPages, page + 1))
    });
    var status = el('span', 'app-v2-pagination-status');
    var end = el('span', 'app-v2-pagination-end');
    append(previous, [icon('fa-arrow-left'), el('span')]);
    previous.lastChild.textContent = 'Trang truoc';
    append(next, [el('span'), icon('fa-arrow-right')]);
    next.firstChild.textContent = 'Trang sau';
    if (page <= 1) previous.setAttribute('disabled', 'disabled');
    if (page >= totalPages) next.setAttribute('disabled', 'disabled');
    status.textContent = 'Trang ' + page + ' / ' + totalPages;
    end.textContent = totalPages <= 1 || page >= totalPages ? 'Da hien thi het du lieu' : 'Con ' + number(Math.max(0, total - page * Number(state.pageSize || 0))) + ' ban ghi';
    append(pager, [previous, status, next, end]);
    return pager;
  }

  function AppEndState(options) {
    var done = el('div', 'app-v2-end-state', { role: 'status', 'aria-live': 'polite' });
    append(done, [icon(options && options.icon || 'fa-circle-check'), text(options && options.message || 'Da hien thi het du lieu')]);
    return done;
  }

  function AppEmptyState(options) {
    var empty = el('div', 'app-v2-empty');
    append(empty, [icon(options.icon || 'fa-inbox'), text(options.message || 'ChÆ°a cÃ³ dá»¯ liá»‡u')]);
    return empty;
  }

  function AppLoading(options) {
    var loading = el('div', 'app-v2-loading', { role: 'status', 'aria-live': 'polite' });
    append(loading, [el('span', 'app-v2-spinner'), text(options && options.message ? options.message : 'Äang táº£i dá»¯ liá»‡u')]);
    return loading;
  }

  function AppBottomNavigation(items, current) {
    var nav = el('nav', 'app-v2-bottom-nav', { 'aria-label': 'Äiá»u hÆ°á»›ng Mobile v2' });
    (items || []).slice(0, 5).forEach(function (item) {
      var button = el('button', '', { type: 'button', 'data-screen': item.action || '' });
      if ((item.action || '') === current) button.setAttribute('aria-current', 'page');
      append(button, [icon(item.icon || 'fa-circle'), el('span')]);
      button.lastChild.textContent = item.label || '';
      nav.appendChild(button);
    });
    return nav;
  }

  function AppDrawer(options) {
    var drawer = el('aside', 'app-v2-drawer', {
      'aria-label': options.label || 'Menu',
      'data-open': options.open ? 'true' : 'false'
    });
    var panel = el('div', 'app-v2-drawer-panel');
    append(panel, [options.body || AppEmptyState({ message: 'ChÆ°a cÃ³ ná»™i dung menu' })]);
    append(drawer, [panel]);
    return drawer;
  }

  function AppModal(options) {
    var modal = el('section', 'app-v2-modal', {
      role: 'dialog',
      'aria-modal': 'true',
      'aria-label': options.title || 'Há»™p thoáº¡i',
      'data-open': options.open ? 'true' : 'false'
    });
    var panel = el('div', 'app-v2-modal-panel');
    var header = AppSection({ title: options.title || 'Chi tiáº¿t', meta: options.meta || '' });
    var body = el('div', 'app-v2-card');
    var footer = el('div', 'app-v2-toolbar');
    append(body, [options.body || AppEmptyState({ message: 'ChÆ°a cÃ³ ná»™i dung' })]);
    append(footer, [AppButton({ label: options.confirmLabel || 'Xong', icon: 'fa-check' })]);
    append(panel, [header, body, footer]);
    append(modal, [panel]);
    return modal;
  }

  function listItem(options) {
    var item = el('article', 'app-v2-list-item');
    var iconWrap = el('span', 'app-v2-card-icon');
    var textWrap = el('div', 'app-v2-title-group');
    var title = el('span', 'app-v2-list-title');
    var subtitle = el('span', 'app-v2-list-subtitle');
    title.textContent = options.title || '';
    subtitle.textContent = options.subtitle || '';
    append(iconWrap, [icon(options.icon || 'fa-circle')]);
    append(textWrap, [title, subtitle]);
    append(item, [iconWrap, textWrap]);
    if (options.action) append(item, [AppIconButton({ icon: 'fa-arrow-right', label: options.actionLabel || 'Má»Ÿ', action: options.action })]);
    return item;
  }

  function AppMetricRow(options) {
    var row = el('div', 'app-v2-metric-row');
    var label = el('span');
    var value = el('strong');
    label.textContent = options.label || '';
    value.textContent = options.value || '0';
    append(row, [label, value]);
    return row;
  }

  function AppList(items) {
    var list = el('div', 'app-v2-list');
    (items || []).forEach(function (item) {
      list.appendChild(item && item.nodeType ? item : listItem(item || {}));
    });
    return list;
  }

  function AppRecordCard(options) {
    var cardAttrs = {};
    if (options.primaryProxy) {
      cardAttrs.role = 'button';
      cardAttrs.tabindex = '0';
      cardAttrs['data-app-v2-primary-proxy'] = options.primaryProxy;
    }
    var card = el('article', 'app-v2-card app-v2-record-card', cardAttrs);
    var iconWrap = el('span', 'app-v2-card-icon');
    var textWrap = el('div', 'app-v2-title-group');
    var title = el('h3', 'app-v2-record-title');
    var meta = el('p', 'app-v2-record-meta');
    var summaryFields = options.summaryFields || [];
    var previewNode = null;
    title.textContent = options.title || 'Báº£n ghi';
    meta.textContent = options.meta || 'Äang cáº­p nháº­t';
    append(iconWrap, [icon(options.icon || 'fa-file-lines')]);
    append(textWrap, [title]);
    if (summaryFields.length) {
      var summary = el('div', 'app-v2-record-summary');
      summaryFields.forEach(function (field) {
        var chip = el('span', 'app-v2-record-summary-chip');
        if (field.key) chip.setAttribute('data-app-v2-summary-key', field.key);
        if (field.tone) chip.setAttribute('data-tone', field.tone);
        chip.textContent = field.label ? field.label + ': ' + field.value : field.value;
        summary.appendChild(chip);
      });
      textWrap.appendChild(summary);
      previewNode = summary;
    } else {
      textWrap.appendChild(meta);
      previewNode = meta;
    }
    if (options.badges && options.badges.length) {
      var tags = el('div', 'app-v2-record-tags');
      options.badges.forEach(function (badge) {
        tags.appendChild(AppBadge(badge));
      });
      textWrap.appendChild(tags);
    }
    if (options.details && options.details.length) {
      var extraDetails = options.details.slice(0);
      if (extraDetails.length) {
        var more = el('details', 'app-v2-record-more');
        var summary = el('summary');
        var moreDetails = el('dl', 'app-v2-record-more-details');
        more.addEventListener('toggle', function () {
          if (previewNode) previewNode.hidden = more.open;
        });
        summary.textContent = 'ThÃªm thÃ´ng tin';
        extraDetails.forEach(function (field) {
          var item = el('div', 'app-v2-record-field');
          var term = el('dt');
          var value = el('dd');
          term.textContent = field.label || '';
          value.textContent = field.value || '';
          append(item, [term, value]);
          moreDetails.appendChild(item);
        });
        append(more, [summary, moreDetails]);
        textWrap.appendChild(more);
      }
    }
    append(card, [iconWrap, textWrap]);
    var actionList = options.actions || [];
    if (actionList.length) {
      var actions = el('div', 'app-v2-record-actions');
      actionList.slice(0, 3).forEach(function (item) {
        actions.appendChild(AppIconButton(item));
      });
      card.appendChild(actions);
    } else if (options.action) {
      append(card, [AppIconButton({ icon: 'fa-ellipsis', label: 'Thao tÃ¡c', action: options.action })]);
    }
    return card;
  }

  function metricValue(metrics, key) {
    return metrics && Object.prototype.hasOwnProperty.call(metrics, key) ? metrics[key] : 0;
  }

  function metricValueAny(metrics, keys) {
    for (var i = 0; i < (keys || []).length; i += 1) {
      if (metrics && Object.prototype.hasOwnProperty.call(metrics, keys[i])) return metrics[keys[i]];
    }
    return 0;
  }

  function percent(value, total) {
    var denominator = Number(total || 0);
    if (!denominator) return '0%';
    return Math.round(Number(value || 0) * 1000 / denominator) / 10 + '%';
  }

  function normalizeChartItems(items) {
    return (Array.isArray(items) ? items : []).map(function (item) {
      return { label: item.label || 'KhÃ¡c', value: Number(item.value || 0) };
    }).filter(function (item) {
      return item.label && item.value > 0;
    });
  }

  function AppDashboardChart(options) {
    var items = normalizeChartItems(options.items);
    var max = items.reduce(function (largest, item) { return Math.max(largest, item.value); }, 0);
    if (!items.length || !max) return null;
    var list = el('div', 'app-v2-chart-list');
    items.forEach(function (item) {
      var row = el('div', 'app-v2-chart-row');
      var label = el('span', 'app-v2-chart-label');
      var meter = document.createElement('meter');
      var value = el('strong', 'app-v2-chart-value');
      label.textContent = item.label;
      meter.className = 'app-v2-chart-meter';
      meter.min = 0;
      meter.max = max;
      meter.value = item.value;
      value.textContent = number(item.value);
      append(row, [label, meter, value]);
      list.appendChild(row);
    });
    return AppCard({ title: options.title, body: list });
  }

  function dashboardData() {
    var summary = window.App && window.App.dashboardSummary ? window.App.dashboardSummary : {};
    var metrics = summary.metrics || {};
    var charts = summary.charts || {};
    var generatedAt = summary.generatedAt || text(document.getElementById('dashboardGeneratedAt') && document.getElementById('dashboardGeneratedAt').textContent);
    var alerts = Array.isArray(summary.alerts) ? summary.alerts.slice(0, 4) : [];
    var tasks = Array.isArray(summary.tasks) ? summary.tasks.slice(0, 4) : [];
    var gis = summary.gis || {};
    var profiles = summary.profiles || {};
    var totalCitizens = Number(metricValueAny(metrics, ['total_citizens', 'population', 'citizens']) || 0);
    var totalHouseholds = Number(metricValueAny(metrics, ['total_households', 'households']) || 0);
    var insured = Number(metricValueAny(metrics, ['health_insurance_count', 'insured_count', 'health_insurance']) || 0);
    var male = Number(metricValue(metrics, 'male_count') || 0);
    var female = Number(metricValue(metrics, 'female_count') || 0);
    var temporary = Number(metricValueAny(metrics, ['temporary_count', 'temporary_residence_count']) || 0);
    var away = Number(metricValueAny(metrics, ['away_count', 'temporary_absence_count']) || 0);
    var poor = Number(metricValue(metrics, 'poor_households') || 0);
    var nearPoor = Number(metricValue(metrics, 'near_poor_households') || 0);
    var partyMembers = Number(metricValue(metrics, 'party_member_count') || 0);
    var children = Number(metricValue(metrics, 'children_count') || 0);
    var elderly = Number(metricValue(metrics, 'elderly_count') || 0);
    var workingAge = Number(metricValue(metrics, 'working_age_count') || 0);
    return {
      generatedAt: generatedAt || 'Äang cáº­p nháº­t dá»¯ liá»‡u',
      stats: [
        { label: 'Há»™ gia Ä‘Ã¬nh', value: number(totalHouseholds), meta: 'Tá»•ng há»™ Ä‘ang quáº£n lÃ½', icon: 'fa-house-chimney' },
        { label: 'NhÃ¢n kháº©u', value: number(totalCitizens), meta: 'Tá»•ng nhÃ¢n kháº©u hiá»‡n cÃ³', icon: 'fa-users' },
        { label: 'Nam', value: number(male), meta: percent(male, totalCitizens) + ' tá»•ng nhÃ¢n kháº©u', icon: 'fa-mars' },
        { label: 'Ná»¯', value: number(female), meta: percent(female, totalCitizens) + ' tá»•ng nhÃ¢n kháº©u', icon: 'fa-venus' },
        { label: 'Äáº£ng viÃªn', value: number(partyMembers), meta: 'Theo há»“ sÆ¡ nhÃ¢n kháº©u', icon: 'fa-star' },
        { label: 'Tráº» em', value: number(children), meta: percent(children, totalCitizens) + ' tá»•ng nhÃ¢n kháº©u', icon: 'fa-child-reaching' },
        { label: 'NgÆ°á»i cao tuá»•i', value: number(elderly), meta: percent(elderly, totalCitizens) + ' tá»•ng nhÃ¢n kháº©u', icon: 'fa-person-cane' },
        { label: 'Lao Ä‘á»™ng', value: number(workingAge), meta: percent(workingAge, totalCitizens) + ' tá»•ng nhÃ¢n kháº©u', icon: 'fa-briefcase' },
        { label: 'BHYT', value: number(insured) + ' / ' + number(totalCitizens), meta: percent(insured, totalCitizens) + ' Ä‘Ã£ tham gia', icon: 'fa-notes-medical' },
        { label: 'Táº¡m trÃº', value: number(temporary), icon: 'fa-location-dot' },
        { label: 'Táº¡m váº¯ng', value: number(away), icon: 'fa-person-walking-arrow-right' },
        { label: 'Há»™ nghÃ¨o', value: number(poor), meta: 'Theo phÃ¢n loáº¡i há»™', icon: 'fa-hand-holding-heart' },
        { label: 'Há»™ cáº­n nghÃ¨o', value: number(nearPoor), meta: 'Theo phÃ¢n loáº¡i há»™', icon: 'fa-hands-holding' }
      ],
      charts: [
        { title: 'CÆ¡ cáº¥u Nam / Ná»¯', items: normalizeChartItems(charts.population).length ? charts.population : [{ label: 'Nam', value: male }, { label: 'Ná»¯', value: female }] },
        { title: 'CÆ¡ cáº¥u Ä‘á»™ tuá»•i', items: charts.ages || [] },
        { title: 'Báº£o hiá»ƒm y táº¿', items: [{ label: 'CÃ³ BHYT', value: insured }, { label: 'ChÆ°a cÃ³ BHYT', value: Math.max(totalCitizens - insured, 0) }] },
        { title: 'Táº¡m trÃº / Táº¡m váº¯ng', items: [{ label: 'Táº¡m trÃº', value: temporary }, { label: 'Táº¡m váº¯ng', value: away }] },
        { title: 'Biáº¿n Ä‘á»™ng', items: charts.monthlyChanges || [] },
        { title: 'Há»™ nghÃ¨o / Cáº­n nghÃ¨o', items: normalizeChartItems(charts.poverty).length ? charts.poverty : [{ label: 'Há»™ nghÃ¨o', value: poor }, { label: 'Há»™ cáº­n nghÃ¨o', value: nearPoor }] },
        { title: 'Lao Ä‘á»™ng', items: charts.labor || charts.occupations || [] },
        { title: 'Há»™ gia Ä‘Ã¬nh', items: charts.households || [] }
      ],
      quickActions: [
        { title: 'Há»™ gia Ä‘Ã¬nh', subtitle: 'Tra cá»©u vÃ  cáº­p nháº­t há»“ sÆ¡ há»™', icon: 'fa-house-user', action: 'households' },
        { title: 'NhÃ¢n kháº©u', subtitle: 'Quáº£n lÃ½ thÃ´ng tin cÃ´ng dÃ¢n', icon: 'fa-id-card', action: 'persons' },
        { title: 'GIS', subtitle: 'Má»Ÿ báº£n Ä‘á»“ vÃ  vá»‹ trÃ­ há»™', icon: 'fa-map-location-dot', action: 'gis' },
        { title: 'BÃ¡o cÃ¡o', subtitle: 'Tá»•ng há»£p vÃ  xuáº¥t dá»¯ liá»‡u', icon: 'fa-chart-pie', action: 'reports' }
      ],
      health: [
        { label: 'Há»“ sÆ¡ sá»‘', value: number((profiles.citizenComplete && profiles.citizenComplete.done) || metricValue(metrics, 'digital_profiles') || metricValue(metrics, 'profiles')), note: 'Theo dÃµi hoÃ n thiá»‡n dá»¯ liá»‡u', icon: 'fa-folder-open' },
        { label: 'Äá»‹nh vá»‹ GIS', value: number(gis.locatedHouseholds || metricValue(metrics, 'located_households') || metricValue(metrics, 'gis_located')), note: 'Há»™ Ä‘Ã£ cÃ³ tá»a Ä‘á»™', icon: 'fa-location-dot' }
      ],
      alerts: alerts.map(function (item) {
        return {
          title: item.label || 'Cáº£nh bÃ¡o',
          subtitle: (item.count != null ? number(item.count) + ' há»“ sÆ¡' : 'Cáº§n kiá»ƒm tra'),
          icon: item.priority === 'high' ? 'fa-triangle-exclamation' : 'fa-bell',
          action: item.screen || 'dashboard'
        };
      }),
      tasks: tasks.map(function (item) {
        return {
          title: item.label || 'TÃ¡c vá»¥',
          subtitle: item.action || (item.count != null ? number(item.count) + ' viá»‡c' : 'Má»Ÿ Ä‘á»ƒ xá»­ lÃ½'),
          icon: 'fa-list-check',
          action: item.screen || 'dashboard'
        };
      })
    };
  }

  var MODULE_DASHBOARD_META = {
    dashboardHouseholds: {
      title: 'Há»™ gia Ä‘Ã¬nh',
      icon: 'fa-house-user',
      subtitle: 'Theo dÃµi há»“ sÆ¡ há»™, GPS vÃ  há»“ sÆ¡ sá»‘',
      kpis: [
        { key: 'totalHouseholds', label: 'Tá»•ng há»™ gia Ä‘Ã¬nh', unit: 'há»™', icon: 'fa-house-chimney', meta: 'Há»™ Ä‘ang quáº£n lÃ½' },
        { key: 'locatedHouseholds', label: 'Há»™ Ä‘Ã£ Ä‘á»‹nh vá»‹', unit: 'há»™', icon: 'fa-location-dot', meta: 'CÃ³ tá»a Ä‘á»™ GIS' },
        { key: 'digitalProfiles', label: 'Há»“ sÆ¡ sá»‘', unit: 'há»“ sÆ¡', icon: 'fa-folder-open', meta: 'ÄÃ£ hoÃ n thiá»‡n dá»¯ liá»‡u' },
        { key: 'attentionHouseholds', label: 'Cáº§n rÃ  soÃ¡t', unit: 'há»™', icon: 'fa-triangle-exclamation', meta: 'Há»™ cáº§n cáº­p nháº­t' }
      ],
      actions: [
        { label: 'Danh sÃ¡ch há»™', icon: 'fa-list', action: 'households' },
        { label: 'GIS', icon: 'fa-map-location-dot', action: 'gis' }
      ]
    },
    dashboardPopulation: {
      title: 'NhÃ¢n kháº©u',
      icon: 'fa-users',
      subtitle: 'Theo dÃµi nhÃ¢n kháº©u, Ä‘á»™ tuá»•i vÃ  cÆ° trÃº',
      kpiLimit: 8,
      kpis: [
        { key: 'totalCitizens', label: 'Tá»•ng nhÃ¢n kháº©u', unit: 'ngÆ°á»i', icon: 'fa-users', meta: 'NhÃ¢n kháº©u hiá»‡n cÃ³' },
        { key: 'maleCitizens', label: 'Nam', unit: 'ngÆ°á»i', icon: 'fa-mars', meta: 'NhÃ¢n kháº©u nam' },
        { key: 'femaleCitizens', label: 'Ná»¯', unit: 'ngÆ°á»i', icon: 'fa-venus', meta: 'NhÃ¢n kháº©u ná»¯' },
        { key: 'children', label: 'Tráº» em', unit: 'ngÆ°á»i', icon: 'fa-child-reaching', meta: 'DÆ°á»›i 16 tuá»•i' },
        { key: 'elderly', label: 'NgÆ°á»i cao tuá»•i', unit: 'ngÆ°á»i', icon: 'fa-person-cane', meta: 'Tá»« 60 tuá»•i' },
        { key: 'temporaryResidents', label: 'Táº¡m trÃº', unit: 'ngÆ°á»i', icon: 'fa-location-dot' },
        { key: 'temporaryAbsences', label: 'Táº¡m váº¯ng', unit: 'ngÆ°á»i', icon: 'fa-person-walking-arrow-right' },
        { key: 'insuredCitizens', label: 'BHYT', unit: 'ngÆ°á»i', icon: 'fa-notes-medical', meta: 'ÄÃ£ tham gia báº£o hiá»ƒm' }
      ],
      actions: [
        { label: 'NhÃ¢n kháº©u', icon: 'fa-id-card', action: 'persons' },
        { label: 'Biáº¿n Ä‘á»™ng', icon: 'fa-arrows-rotate', action: 'movements' }
      ]
    },
    dashboardBusiness: {
      title: 'Kinh doanh',
      icon: 'fa-store',
      subtitle: 'Tá»•ng quan cÆ¡ sá»Ÿ, ngÃ nh nghá» vÃ  quy mÃ´ hoáº¡t Ä‘á»™ng',
      kpis: [
        { key: 'totalBusinesses', label: 'CÆ¡ sá»Ÿ kinh doanh', unit: 'cÆ¡ sá»Ÿ', icon: 'fa-store', meta: 'Äang quáº£n lÃ½' },
        { key: 'activeBusinesses', label: 'Äang hoáº¡t Ä‘á»™ng', unit: 'cÆ¡ sá»Ÿ', icon: 'fa-circle-check', meta: 'CÆ¡ sá»Ÿ cÃ²n hoáº¡t Ä‘á»™ng' },
        { key: 'workers', label: 'Lao Ä‘á»™ng', unit: 'ngÆ°á»i', icon: 'fa-briefcase', meta: 'Tá»•ng lao Ä‘á»™ng ghi nháº­n' },
        { key: 'needsReview', label: 'Cáº§n rÃ  soÃ¡t', unit: 'cÆ¡ sá»Ÿ', icon: 'fa-triangle-exclamation', meta: 'Thiáº¿u hoáº·c cáº§n cáº­p nháº­t' }
      ],
      actions: [
        { label: 'CÆ¡ sá»Ÿ', icon: 'fa-store', action: 'businessHouseholds' },
        { label: 'BÃ¡o cÃ¡o', icon: 'fa-chart-pie', action: 'reports' }
      ]
    },
    dashboardVehicles: {
      title: 'Xe cá»™',
      icon: 'fa-car',
      subtitle: 'Theo dÃµi phÆ°Æ¡ng tiá»‡n theo há»™ vÃ  khu vá»±c',
      kpis: [
        { key: 'totalVehicles', label: 'Tá»•ng phÆ°Æ¡ng tiá»‡n', unit: 'xe', icon: 'fa-car', meta: 'PhÆ°Æ¡ng tiá»‡n Ä‘Ã£ ghi nháº­n' },
        { key: 'motorbikes', label: 'Xe mÃ¡y', unit: 'xe', icon: 'fa-motorcycle', meta: 'NhÃ³m xe mÃ¡y' },
        { key: 'cars', label: 'Ã” tÃ´', unit: 'xe', icon: 'fa-car-side', meta: 'NhÃ³m Ã´ tÃ´' },
        { key: 'expired', label: 'Cáº§n kiá»ƒm tra', unit: 'xe', icon: 'fa-triangle-exclamation', meta: 'Báº£o hiá»ƒm/kiá»ƒm Ä‘á»‹nh' }
      ],
      actions: [
        { label: 'Xe cá»™', icon: 'fa-car', action: 'vehicles' },
        { label: 'GIS', icon: 'fa-map-location-dot', action: 'gis' }
      ]
    },
    dashboardLivestock: {
      title: 'Váº­t nuÃ´i',
      icon: 'fa-paw',
      subtitle: 'Theo dÃµi Ä‘Ã n váº­t nuÃ´i, tiÃªm phÃ²ng vÃ  quy mÃ´ há»™',
      kpis: [
        { key: 'totalAnimals', label: 'Tá»•ng váº­t nuÃ´i', unit: 'con', icon: 'fa-paw', meta: 'Sá»‘ lÆ°á»£ng váº­t nuÃ´i' },
        { key: 'households', label: 'Há»™ chÄƒn nuÃ´i', unit: 'há»™', icon: 'fa-house-chimney', meta: 'Há»™ cÃ³ váº­t nuÃ´i' },
        { key: 'vaccinated', label: 'ÄÃ£ tiÃªm phÃ²ng', unit: 'con', icon: 'fa-syringe', meta: 'Theo há»“ sÆ¡ tiÃªm phÃ²ng' },
        { key: 'needsCare', label: 'Cáº§n theo dÃµi', unit: 'con', icon: 'fa-triangle-exclamation', meta: 'Cáº§n cáº­p nháº­t tráº¡ng thÃ¡i' }
      ],
      actions: [
        { label: 'Váº­t nuÃ´i', icon: 'fa-paw', action: 'livestock' },
        { label: 'NÃ´ng nghiá»‡p', icon: 'fa-seedling', action: 'agriculture' }
      ]
    },
    dashboardGis: {
      title: 'GIS',
      icon: 'fa-map-location-dot',
      subtitle: 'Tiáº¿n Ä‘á»™ Ä‘á»‹nh vá»‹, lá»›p báº£n Ä‘á»“ vÃ  marker',
      kpis: [
        { key: 'locatedHouseholds', label: 'Há»™ Ä‘Ã£ Ä‘á»‹nh vá»‹', unit: 'há»™', icon: 'fa-location-dot', meta: 'CÃ³ tá»a Ä‘á»™ báº£n Ä‘á»“' },
        { key: 'missingLocation', label: 'ChÆ°a Ä‘á»‹nh vá»‹', unit: 'há»™', icon: 'fa-map-pin', meta: 'Cáº§n bá»• sung tá»a Ä‘á»™' },
        { key: 'markers', label: 'Marker báº£n Ä‘á»“', unit: 'marker', icon: 'fa-location-crosshairs', meta: 'Äiá»ƒm Ä‘ang hiá»ƒn thá»‹' },
        { key: 'coverage', label: 'Tá»· lá»‡ phá»§ GIS', unit: '%', icon: 'fa-chart-pie', meta: 'Má»©c hoÃ n thiá»‡n Ä‘á»‹nh vá»‹' }
      ],
      actions: [
        { label: 'Má»Ÿ báº£n Ä‘á»“', icon: 'fa-map', action: 'gis' },
        { label: 'Há»™ dÃ¢n', icon: 'fa-house', action: 'households' }
      ]
    },
    dashboardReports: {
      title: 'BÃ¡o cÃ¡o',
      icon: 'fa-chart-pie',
      subtitle: 'Theo dÃµi nhÃ³m bÃ¡o cÃ¡o vÃ  tráº¡ng thÃ¡i xuáº¥t dá»¯ liá»‡u',
      kpis: [
        { key: 'totalReports', label: 'Máº«u bÃ¡o cÃ¡o', unit: 'máº«u', icon: 'fa-file-lines', meta: 'BÃ¡o cÃ¡o kháº£ dá»¥ng' },
        { key: 'exports', label: 'LÆ°á»£t xuáº¥t', unit: 'lÆ°á»£t', icon: 'fa-file-export', meta: 'Excel/PDF/In áº¥n' },
        { key: 'scheduled', label: 'Lá»‹ch bÃ¡o cÃ¡o', unit: 'lá»‹ch', icon: 'fa-calendar-check', meta: 'BÃ¡o cÃ¡o Ä‘á»‹nh ká»³' },
        { key: 'attention', label: 'Cáº§n xá»­ lÃ½', unit: 'má»¥c', icon: 'fa-triangle-exclamation', meta: 'BÃ¡o cÃ¡o cáº§n rÃ  soÃ¡t' }
      ],
      actions: [
        { label: 'BÃ¡o cÃ¡o', icon: 'fa-chart-pie', action: 'reports' },
        { label: 'In áº¥n', icon: 'fa-print', action: 'reports' }
      ]
    }
  };

  var MODULE_SCREEN_META = {
    householdsScreen: {
      title: 'Há»™ gia Ä‘Ã¬nh',
      listKind: 'households',
      overviewMode: 'compact',
      eyebrow: 'Quáº£n lÃ½ há»™',
      icon: 'fa-house-user',
      subtitle: 'Tra cá»©u, cáº­p nháº­t há»“ sÆ¡ há»™ vÃ  Ä‘á»‹nh vá»‹ GIS',
      search: 'TÃ¬m mÃ£ há»™, chá»§ há»™, Ä‘á»‹a chá»‰...',
      titleLabels: ['Chá»§ há»™', 'TÃªn chá»§ há»™', 'Chu ho', 'Ten chu ho'],
      summaryLabels: ['MÃ£ há»™', 'á»ž nhÃ ', 'Äi váº¯ng', 'Tráº¡ng thÃ¡i', 'Loáº¡i há»™', 'Ma ho', 'O nha', 'Di vang', 'Trang thai', 'Loai ho'],
      metaLabels: ['MÃ£ há»™', 'Äá»‹a chá»‰', 'á»ž nhÃ ', 'Äi váº¯ng', 'Ma ho', 'Dia chi', 'O nha', 'Di vang'],
      desktopFilter: { searchSelector: '#householdSearch', statusSelector: '#householdStatusFilter', stateKey: 'households', loaderName: 'loadHouseholds' },
      primaryAction: { label: 'ThÃªm há»™', icon: 'fa-plus', proxy: '#householdAddBtn, [data-platform-action="households.create"]' },
      nav: [{ label: 'Dashboard', icon: 'fa-chart-simple', action: 'dashboardHouseholds' }, { label: 'GIS', icon: 'fa-map-location-dot', action: 'gis' }]
    },
    personsScreen: {
      title: 'NhÃ¢n kháº©u',
      overviewMode: 'full',
      eyebrow: 'Quáº£n lÃ½ cÃ´ng dÃ¢n',
      icon: 'fa-id-card',
      subtitle: 'Há»“ sÆ¡ nhÃ¢n kháº©u, cÆ° trÃº vÃ  biáº¿n Ä‘á»™ng',
      search: 'TÃ¬m há» tÃªn, CCCD, mÃ£ há»™...',
      titleLabels: ['Há» vÃ  tÃªn', 'Há» tÃªn', 'Ho va ten', 'Ho ten'],
      summaryLabels: ['Chá»§ há»™', 'TÃªn chá»§ há»™', 'MÃ£ há»™', 'Quan há»‡', 'Giá»›i tÃ­nh', 'Tuá»•i', 'CÆ° trÃº', 'Chu ho', 'Ten chu ho', 'Ma ho', 'Quan he', 'Gioi tinh', 'Tuoi', 'Cu tru'],
      metaLabels: ['Chá»§ há»™', 'TÃªn chá»§ há»™', 'MÃ£ há»™', 'Quan há»‡', 'Tuá»•i', 'Giá»›i tÃ­nh', 'CÆ° trÃº', 'Chu ho', 'Ten chu ho', 'Ma ho', 'Quan he', 'Tuoi', 'Gioi tinh', 'Cu tru'],
      actionMode: 'detailOnly',
      desktopFilter: { searchSelector: '#personSearch', stateKey: 'persons', loaderName: 'loadPersons' },
      primaryAction: { label: 'ThÃªm nhÃ¢n kháº©u', icon: 'fa-plus', proxy: '#personAddBtn, [data-platform-action="persons.create"]' },
      nav: [{ label: 'Dashboard', icon: 'fa-chart-simple', action: 'dashboardPopulation' }, { label: 'Biáº¿n Ä‘á»™ng', icon: 'fa-arrows-rotate', action: 'movements' }],
      scopes: [
        { key: 'temporaryResidence', label: 'Táº¡m trÃº', icon: 'fa-location-dot', match: 'Táº¡m trÃº', tone: 'warning' },
        { key: 'temporaryAbsence', label: 'Táº¡m váº¯ng', icon: 'fa-person-walking-arrow-right', match: 'Táº¡m váº¯ng', tone: 'danger' }
      ]
    },
    partyMembersScreen: {
      title: 'Äáº£ng viÃªn',
      overviewMode: 'full',
      eyebrow: 'Chi bá»™',
      icon: 'fa-flag',
      subtitle: 'Há»“ sÆ¡ Äáº£ng viÃªn, chi bá»™, chá»©c vá»¥ vÃ  tÃ¬nh tráº¡ng sinh hoáº¡t',
      search: 'TÃ¬m há» tÃªn, mÃ£ Äáº£ng viÃªn, chi bá»™...',
      titleLabels: ['Há» tÃªn', 'Há» vÃ  tÃªn', 'Ho ten', 'Ho va ten'],
      summaryLabels: ['MÃ£ ÄV', 'Chi bá»™', 'Chá»©c vá»¥', 'Loáº¡i', 'TÃ¬nh tráº¡ng', 'NgÃ y vÃ o Äáº£ng', 'Ma DV', 'Chi bo', 'Chuc vu', 'Loai', 'Tinh trang', 'Ngay vao Dang'],
      metaLabels: ['MÃ£ ÄV', 'Chi bá»™', 'Chá»©c vá»¥', 'TÃ¬nh tráº¡ng', 'Ma DV', 'Chi bo', 'Chuc vu', 'Tinh trang'],
      primaryAction: { label: 'ThÃªm Äáº£ng viÃªn', icon: 'fa-plus', proxy: '#partyMemberAddBtn, [data-platform-action="partyMembers.openCreate"]' },
      nav: [{ label: 'NhÃ¢n kháº©u', icon: 'fa-users', action: 'persons' }, { label: 'BÃ¡o cÃ¡o', icon: 'fa-chart-pie', action: 'reports' }]
    },
    workTasksScreen: {
      title: 'CÃ´ng viá»‡c',
      overviewMode: 'compact',
      eyebrow: 'Äiá»u hÃ nh',
      icon: 'fa-list-check',
      subtitle: 'Giao viá»‡c, tiáº¿n Ä‘á»™, Æ°u tiÃªn vÃ  tráº¡ng thÃ¡i xá»­ lÃ½',
      search: 'TÃ¬m mÃ£ viá»‡c, ná»™i dung, ngÆ°á»i phá»¥ trÃ¡ch...',
      titleLabels: ['CÃ´ng viá»‡c', 'TiÃªu Ä‘á»', 'Ná»™i dung', 'MÃ£ viá»‡c', 'Cong viec', 'Tieu de', 'Noi dung', 'Ma viec'],
      summaryLabels: ['MÃ£', 'Loáº¡i', 'Tráº¡ng thÃ¡i', 'Æ¯u tiÃªn', 'Tiáº¿n Ä‘á»™', 'Háº¡n xá»­ lÃ½', 'Ma', 'Loai', 'Trang thai', 'Uu tien', 'Tien do', 'Han xu ly'],
      metaLabels: ['MÃ£', 'Tráº¡ng thÃ¡i', 'Æ¯u tiÃªn', 'Tiáº¿n Ä‘á»™', 'Ma', 'Trang thai', 'Uu tien', 'Tien do'],
      primaryAction: { label: 'ThÃªm cÃ´ng viá»‡c', icon: 'fa-plus', proxy: '#workTaskAddBtn, [data-platform-action="workTasks.create"]' },
      nav: [{ label: 'Lá»‹ch', icon: 'fa-calendar-days', action: 'workCalendar' }, { label: 'Äiá»u hÃ nh', icon: 'fa-tower-broadcast', action: 'operationCenter' }]
    },
    workCalendarScreen: {
      title: 'Lá»‹ch',
      overviewMode: 'compact',
      eyebrow: 'Äiá»u hÃ nh',
      icon: 'fa-calendar-days',
      subtitle: 'Lá»‹ch há»p, trá»±c, sá»± kiá»‡n vÃ  hoáº¡t Ä‘á»™ng theo thá»i gian',
      search: 'TÃ¬m lá»‹ch, ná»™i dung, Ä‘á»‹a Ä‘iá»ƒm...',
      titleLabels: ['Lá»‹ch', 'TiÃªu Ä‘á»', 'Ná»™i dung', 'Sá»± kiá»‡n', 'MÃ£ lá»‹ch', 'Lich', 'Tieu de', 'Noi dung', 'Su kien', 'Ma lich'],
      summaryLabels: ['MÃ£', 'Thá»i gian', 'Tráº¡ng thÃ¡i', 'Äá»‹a Ä‘iá»ƒm', 'Loáº¡i', 'Ma', 'Thoi gian', 'Trang thai', 'Dia diem', 'Loai'],
      metaLabels: ['MÃ£', 'Thá»i gian', 'Tráº¡ng thÃ¡i', 'Äá»‹a Ä‘iá»ƒm', 'Ma', 'Thoi gian', 'Trang thai', 'Dia diem'],
      primaryAction: { label: 'ThÃªm lá»‹ch', icon: 'fa-plus', proxy: '#workCalendarAddBtn, [data-platform-action="workCalendar.create"]' },
      nav: [{ label: 'CÃ´ng viá»‡c', icon: 'fa-list-check', action: 'workTasks' }, { label: 'Äiá»u hÃ nh', icon: 'fa-tower-broadcast', action: 'operationCenter' }]
    },
    documentsScreen: {
      title: 'VÄƒn báº£n',
      overviewMode: 'compact',
      eyebrow: 'Äiá»u hÃ nh',
      icon: 'fa-file-lines',
      subtitle: 'ThÃ´ng bÃ¡o, quyáº¿t Ä‘á»‹nh, cÃ´ng vÄƒn, káº¿ hoáº¡ch vÃ  file Ä‘Ã­nh kÃ¨m',
      search: 'TÃ¬m tiÃªu Ä‘á», sá»‘ vÄƒn báº£n, loáº¡i vÄƒn báº£n...',
      titleLabels: ['TiÃªu Ä‘á»', 'Sá»‘ vÄƒn báº£n', 'TÃªn vÄƒn báº£n', 'Tieu de', 'So van ban', 'Ten van ban'],
      summaryLabels: ['Sá»‘ vÄƒn báº£n', 'Loáº¡i', 'Tráº¡ng thÃ¡i', 'NgÃ y ban hÃ nh', 'NgÆ°á»i táº£i lÃªn', 'So van ban', 'Loai', 'Trang thai', 'Ngay ban hanh', 'Nguoi tai len'],
      metaLabels: ['Sá»‘ vÄƒn báº£n', 'Loáº¡i', 'Tráº¡ng thÃ¡i', 'NgÃ y ban hÃ nh', 'So van ban', 'Loai', 'Trang thai', 'Ngay ban hanh'],
      primaryAction: { label: 'ThÃªm vÄƒn báº£n', icon: 'fa-plus', proxy: '#documentAddBtn, [data-platform-action="documents.create"]' },
      nav: [{ label: 'CÃ´ng viá»‡c', icon: 'fa-list-check', action: 'workTasks' }, { label: 'BÃ¡o cÃ¡o', icon: 'fa-chart-pie', action: 'reports' }]
    },
    financeScreen: {
      title: 'Thu chi',
      overviewMode: 'full',
      eyebrow: 'TÃ i chÃ­nh',
      icon: 'fa-coins',
      subtitle: 'Quáº£n lÃ½ phiáº¿u thu, phiáº¿u chi, quá»¹ vÃ  chá»©ng tá»« Ä‘Ã­nh kÃ¨m',
      search: 'TÃ¬m mÃ£ phiáº¿u, ná»™i dung, ngÆ°á»i ná»™p...',
      titleLabels: ['Ná»™i dung', 'MÃ£ phiáº¿u', 'Sá»‘ chá»©ng tá»«', 'Noi dung', 'Ma phieu', 'So chung tu'],
      summaryLabels: ['Loáº¡i', 'Sá»‘ tiá»n', 'Quá»¹', 'Danh má»¥c', 'Tráº¡ng thÃ¡i', 'NgÃ y thu chi', 'Loai', 'So tien', 'Quy', 'Danh muc', 'Trang thai', 'Ngay thu chi'],
      metaLabels: ['Loáº¡i', 'Sá»‘ tiá»n', 'Quá»¹', 'Tráº¡ng thÃ¡i', 'NgÃ y thu chi', 'Loai', 'So tien', 'Quy', 'Trang thai', 'Ngay thu chi'],
      primaryAction: { label: 'ThÃªm phiáº¿u', icon: 'fa-plus', proxy: '#financeAddBtn, [data-platform-action="finance.create"]' },
      nav: [{ label: 'ÄÃ³ng gÃ³p', icon: 'fa-hand-holding-dollar', action: 'contributions' }, { label: 'BÃ¡o cÃ¡o', icon: 'fa-chart-pie', action: 'reports' }]
    },
    photoGalleryScreen: {
      title: 'Kho áº£nh',
      overviewMode: 'compact',
      eyebrow: 'TÆ° liá»‡u',
      icon: 'fa-images',
      subtitle: 'Album, hÃ¬nh áº£nh hoáº¡t Ä‘á»™ng, tag vÃ  tÃ¬m kiáº¿m tÆ° liá»‡u',
      search: 'TÃ¬m album, áº£nh, tag...',
      titleLabels: ['TiÃªu Ä‘á»', 'Album', 'TÃªn áº£nh', 'Tieu de', 'Ten anh'],
      summaryLabels: ['Album', 'NgÃ y chá»¥p', 'Tag', 'Tráº¡ng thÃ¡i', 'Album', 'Ngay chup', 'Trang thai'],
      metaLabels: ['Album', 'NgÃ y chá»¥p', 'Tag', 'Tráº¡ng thÃ¡i', 'Ngay chup', 'Trang thai'],
      primaryAction: { label: 'ThÃªm áº£nh', icon: 'fa-plus', proxy: '#photoGalleryAddBtn, [data-platform-action="photoGallery.create"]' },
      nav: [{ label: 'VÄƒn báº£n', icon: 'fa-file-lines', action: 'documents' }, { label: 'BÃ¡o cÃ¡o', icon: 'fa-chart-pie', action: 'reports' }]
    },
    complaintsScreen: {
      title: 'Pháº£n Ã¡nh',
      overviewMode: 'compact',
      eyebrow: 'Tiáº¿p nháº­n',
      icon: 'fa-comments',
      subtitle: 'Pháº£n Ã¡nh, kiáº¿n nghá»‹, phÃ¢n cÃ´ng vÃ  tráº¡ng thÃ¡i xá»­ lÃ½',
      search: 'TÃ¬m mÃ£, tiÃªu Ä‘á», ngÆ°á»i pháº£n Ã¡nh...',
      titleLabels: ['TiÃªu Ä‘á»', 'MÃ£', 'NgÆ°á»i pháº£n Ã¡nh', 'Tieu de', 'Ma', 'Nguoi phan anh'],
      summaryLabels: ['MÃ£', 'Loáº¡i', 'Æ¯u tiÃªn', 'Tráº¡ng thÃ¡i', 'Phá»¥ trÃ¡ch', 'Háº¡n xá»­ lÃ½', 'Ma', 'Loai', 'Uu tien', 'Trang thai', 'Phu trach', 'Han xu ly'],
      metaLabels: ['MÃ£', 'Loáº¡i', 'Æ¯u tiÃªn', 'Tráº¡ng thÃ¡i', 'Ma', 'Loai', 'Uu tien', 'Trang thai'],
      primaryAction: { label: 'ThÃªm pháº£n Ã¡nh', icon: 'fa-plus', proxy: '#complaintsAddBtn, [data-platform-action="complaints.create"]' },
      nav: [{ label: 'Äiá»u hÃ nh', icon: 'fa-tower-broadcast', action: 'operationCenter' }, { label: 'CÃ´ng viá»‡c', icon: 'fa-list-check', action: 'workTasks' }]
    },
    dataQualityScreen: {
      title: 'Cháº¥t lÆ°á»£ng dá»¯ liá»‡u',
      overviewMode: 'compact',
      eyebrow: 'Dá»¯ liá»‡u',
      icon: 'fa-shield-halved',
      subtitle: 'PhÃ¡t hiá»‡n há»“ sÆ¡ thiáº¿u, dá»¯ liá»‡u báº¥t thÆ°á»ng vÃ  viá»‡c cáº§n xá»­ lÃ½',
      search: 'TÃ¬m loáº¡i lá»—i, há»“ sÆ¡, má»©c Ä‘á»™...',
      titleLabels: ['Váº¥n Ä‘á»', 'Há»“ sÆ¡', 'Loáº¡i lá»—i', 'Van de', 'Ho so', 'Loai loi'],
      summaryLabels: ['Má»©c Ä‘á»™', 'Tráº¡ng thÃ¡i', 'Module', 'NgÃ y phÃ¡t hiá»‡n', 'Muc do', 'Trang thai', 'Ngay phat hien'],
      metaLabels: ['Má»©c Ä‘á»™', 'Tráº¡ng thÃ¡i', 'Module', 'Muc do', 'Trang thai'],
      primaryAction: { label: 'LÃ m má»›i', icon: 'fa-rotate-right', proxy: '[data-platform-action="dataQuality.refresh"]' },
      nav: [{ label: 'Dashboard', icon: 'fa-chart-simple', action: 'dashboard' }, { label: 'NhÃ¢n kháº©u', icon: 'fa-id-card', action: 'persons' }]
    },
    gisScreen: {
      title: 'GIS',
      overviewMode: 'full',
      eyebrow: 'Báº£n Ä‘á»“ sá»‘',
      icon: 'fa-map-location-dot',
      subtitle: 'Báº£n Ä‘á»“ há»™ dÃ¢n, marker vÃ  lá»›p dá»¯ liá»‡u',
      search: 'TÃ¬m há»™, Ä‘á»‹a chá»‰, khu vá»±c...',
      primaryAction: { label: 'Má»Ÿ báº£n Ä‘á»“', icon: 'fa-map', action: 'gis' },
      nav: [{ label: 'Há»™ dÃ¢n', icon: 'fa-house', action: 'households' }, { label: 'BÃ¡o cÃ¡o', icon: 'fa-chart-pie', action: 'reports' }]
    },
    businessHouseholdsScreen: {
      title: 'Kinh doanh',
      overviewMode: 'compact',
      eyebrow: 'Há»™ kinh doanh',
      icon: 'fa-store',
      subtitle: 'CÆ¡ sá»Ÿ, ngÃ nh nghá» vÃ  tráº¡ng thÃ¡i hoáº¡t Ä‘á»™ng',
      search: 'TÃ¬m cÆ¡ sá»Ÿ, chá»§ há»™, ngÃ nh nghá»...',
      primaryAction: { label: 'ThÃªm cÆ¡ sá»Ÿ', icon: 'fa-plus', proxy: '#businessHouseholdAddBtn, [data-platform-action="businessHouseholds.openCreate"]' },
      nav: [{ label: 'Dashboard', icon: 'fa-chart-simple', action: 'dashboardBusiness' }, { label: 'BÃ¡o cÃ¡o', icon: 'fa-chart-pie', action: 'reports' }]
    },
    agriculturalLandScreen: {
      title: 'Quá»¹ Ä‘áº¥t',
      overviewMode: 'compact',
      eyebrow: 'Quá»¹ Ä‘áº¥t nÃ´ng nghiá»‡p',
      icon: 'fa-map',
      subtitle: 'Quáº£n lÃ½ tá»•ng diá»‡n tÃ­ch Ä‘áº¥t nÃ´ng nghiá»‡p theo tá»«ng khu',
      search: 'TÃ¬m mÃ£ khu, tÃªn khu, ghi chÃº...',
      primaryAction: { label: 'ThÃªm khu Ä‘áº¥t', icon: 'fa-plus', proxy: '#agriculturalLandListAddBtn, #agriculturalLandAddBtn, [data-platform-action="agriculturalLand.create"]' },
      nav: [{ label: 'NÃ´ng nghiá»‡p', icon: 'fa-seedling', action: 'agriculture' }, { label: 'BÃ¡o cÃ¡o', icon: 'fa-chart-pie', action: 'reports' }]
    },
    vehiclesScreen: {
      title: 'Xe cá»™',
      overviewMode: 'compact',
      eyebrow: 'PhÆ°Æ¡ng tiá»‡n',
      icon: 'fa-car',
      subtitle: 'Quáº£n lÃ½ phÆ°Æ¡ng tiá»‡n theo há»™ dÃ¢n',
      search: 'TÃ¬m biá»ƒn sá»‘, chá»§ xe, loáº¡i xe...',
      primaryAction: { label: 'ThÃªm xe', icon: 'fa-plus', proxy: '#vehicleAddBtn, [data-platform-action="vehicles.create"]' },
      nav: [{ label: 'Dashboard', icon: 'fa-chart-simple', action: 'dashboardVehicles' }]
    },
    contributionsScreen: {
      title: 'ÄÃ³ng gÃ³p',
      overviewMode: 'full',
      eyebrow: 'Thu Ä‘Ã³ng gÃ³p',
      icon: 'fa-hand-holding-dollar',
      subtitle: 'Theo dÃµi khoáº£n thu vÃ  tiáº¿n Ä‘á»™ Ä‘Ã³ng gÃ³p',
      search: 'TÃ¬m há»™, khoáº£n thu, tráº¡ng thÃ¡i...',
      primaryAction: { label: 'ThÃªm khoáº£n thu', icon: 'fa-plus', proxy: '#contributionCreateCampaignBtn, [data-platform-action="contributions.create"]' },
      nav: [{ label: 'BÃ¡o cÃ¡o', icon: 'fa-chart-pie', action: 'reports' }]
    },
    agricultureScreen: {
      title: 'NÃ´ng nghiá»‡p',
      overviewMode: 'full',
      eyebrow: 'Sáº£n xuáº¥t',
      icon: 'fa-seedling',
      subtitle: 'Thá»­a Ä‘áº¥t, cÃ¢y trá»“ng vÃ  sáº£n xuáº¥t nÃ´ng nghiá»‡p',
      search: 'TÃ¬m thá»­a, chá»§ sá»­ dá»¥ng, cÃ¢y trá»“ng...',
      primaryAction: { label: 'ThÃªm thá»­a', icon: 'fa-plus', proxy: '#agriAddBtn, [data-platform-action="agriculture.create"]' },
      nav: [{ label: 'Váº­t nuÃ´i', icon: 'fa-paw', action: 'livestock' }]
    },
    housesScreen: {
      title: 'NhÃ  á»Ÿ',
      overviewMode: 'compact',
      eyebrow: 'CÃ´ng trÃ¬nh há»™',
      icon: 'fa-house-chimney-window',
      subtitle: 'NhÃ  á»Ÿ, cÃ´ng trÃ¬nh phá»¥ vÃ  tÃ¬nh tráº¡ng sá»­ dá»¥ng',
      search: 'TÃ¬m nhÃ , há»™ gia Ä‘Ã¬nh, Ä‘á»‹a chá»‰...',
      primaryAction: { label: 'ThÃªm nhÃ ', icon: 'fa-plus', proxy: '#housesAddBtn, [data-platform-action="houses.create"]' },
      nav: [{ label: 'Há»™ dÃ¢n', icon: 'fa-house-user', action: 'households' }]
    },
    publicAssetsScreen: {
      title: 'CÃ´ng trÃ¬nh',
      overviewMode: 'compact',
      eyebrow: 'TÃ i sáº£n cÃ´ng',
      icon: 'fa-building-columns',
      subtitle: 'CÃ´ng trÃ¬nh cÃ´ng cá»™ng, tÃ i sáº£n vÃ  kiá»ƒm kÃª',
      search: 'TÃ¬m mÃ£, tÃªn cÃ´ng trÃ¬nh, Ä‘Æ¡n vá»‹...',
      primaryAction: { label: 'ThÃªm cÃ´ng trÃ¬nh', icon: 'fa-plus', proxy: '#publicAssetsAddBtn, [data-platform-action="publicAssets.create"]' },
      nav: [{ label: 'GIS', icon: 'fa-map-location-dot', action: 'gis' }]
    },
    livestockScreen: {
      title: 'Váº­t nuÃ´i',
      overviewMode: 'compact',
      eyebrow: 'ChÄƒn nuÃ´i',
      icon: 'fa-paw',
      subtitle: 'ÄÃ n váº­t nuÃ´i, tiÃªm phÃ²ng vÃ  dá»‹ch bá»‡nh',
      search: 'TÃ¬m há»™, loáº¡i váº­t nuÃ´i, tráº¡ng thÃ¡i...',
      primaryAction: { label: 'ThÃªm váº­t nuÃ´i', icon: 'fa-plus', proxy: '#livestockAddBtn, [data-platform-action="livestock.create"]' },
      nav: [{ label: 'Dashboard', icon: 'fa-chart-simple', action: 'dashboardLivestock' }, { label: 'NÃ´ng nghiá»‡p', icon: 'fa-seedling', action: 'agriculture' }]
    },
    reportsScreen: {
      title: 'BÃ¡o cÃ¡o',
      overviewMode: 'full',
      eyebrow: 'Tá»•ng há»£p',
      icon: 'fa-chart-pie',
      subtitle: 'Biá»ƒu máº«u, xuáº¥t PDF/Excel vÃ  thá»‘ng kÃª',
      search: 'TÃ¬m bÃ¡o cÃ¡o, biá»ƒu máº«u...',
      primaryAction: { label: 'Xuáº¥t bÃ¡o cÃ¡o', icon: 'fa-file-export', proxy: '#reportExportBtn, [data-platform-action="reports.export"]' },
      nav: [{ label: 'Dashboard', icon: 'fa-chart-simple', action: 'dashboardReports' }]
    },
    operationCenterScreen: {
      title: 'Äiá»u hÃ nh',
      eyebrow: 'Trung tÃ¢m',
      icon: 'fa-tower-broadcast',
      subtitle: 'Theo dÃµi váº­n hÃ nh vÃ  tÃ¡c vá»¥ cáº§n xá»­ lÃ½',
      search: 'TÃ¬m tÃ¡c vá»¥, khu vá»±c...',
      primaryAction: { label: 'Dashboard', icon: 'fa-chart-simple', action: 'dashboard' },
      nav: [{ label: 'BÃ¡o cÃ¡o', icon: 'fa-chart-pie', action: 'reports' }]
    },
    temporaryResidenceScreen: {
      title: 'Táº¡m trÃº',
      overviewMode: 'none',
      eyebrow: 'Cu tru',
      icon: 'fa-location-dot',
      subtitle: 'Danh sach nhan khau co trang thai tam tru',
      search: 'Tim nhan khau, CCCD, ma ho...',
      primaryAction: { label: 'NhÃ¢n kháº©u', icon: 'fa-id-card', action: 'persons' },
      nav: [{ label: 'NhÃ¢n kháº©u', icon: 'fa-users', action: 'persons' }, { label: 'Táº¡m váº¯ng', icon: 'fa-person-walking-arrow-right', action: 'temporaryAbsence' }]
    },
    temporaryAbsenceScreen: {
      title: 'Táº¡m váº¯ng',
      overviewMode: 'none',
      icon: 'fa-person-walking-arrow-right',
      primaryAction: { label: 'NhÃ¢n kháº©u', icon: 'fa-id-card', action: 'persons' },
      nav: [{ label: 'NhÃ¢n kháº©u', icon: 'fa-users', action: 'persons' }, { label: 'Táº¡m trÃº', icon: 'fa-location-dot', action: 'temporaryResidence' }]
    },
    movementsScreen: {
      title: 'Bien dong',
      overviewMode: 'compact',
      eyebrow: 'NhÃ¢n kháº©u',
      icon: 'fa-arrows-rotate',
      subtitle: 'Sinh, tu, chuyen den, chuyen di, tam tru va tam vang',
      search: 'Tim nhan khau, CCCD, ma ho, ly do...',
      primaryAction: { label: 'ThÃªm biáº¿n Ä‘á»™ng', icon: 'fa-plus', proxy: '#movementAddBtn, [data-platform-action="admin.movement.add"]' },
      nav: [{ label: 'NhÃ¢n kháº©u', icon: 'fa-id-card', action: 'persons' }, { label: 'BÃ¡o cÃ¡o', icon: 'fa-chart-pie', action: 'reports' }]
    },
    importScreen: {
      title: 'Import',
      eyebrow: 'Dá»¯ liá»‡u',
      icon: 'fa-file-import',
      subtitle: 'Nhap du lieu vao he thong theo quy trinh hien co',
      search: 'Tim tac vu import...',
      primaryAction: { label: 'Import du lieu', icon: 'fa-file-import', action: 'import' },
      nav: [{ label: 'Xuáº¥t Excel', icon: 'fa-file-export', action: 'exportExcel' }, { label: 'BÃ¡o cÃ¡o', icon: 'fa-chart-pie', action: 'reports' }]
    },
    exportExcelScreen: {
      title: 'Xuáº¥t Excel',
      eyebrow: 'Dá»¯ liá»‡u',
      icon: 'fa-file-excel',
      subtitle: 'Xuat du lieu bao cao, ho dan va nhan khau ra Excel',
      search: 'Tim bao cao xuat Excel...',
      primaryAction: { label: 'BÃ¡o cÃ¡o tá»•ng há»£p', icon: 'fa-file-excel', proxy: '[data-platform-action="admin.report.export"]' },
      nav: [{ label: 'Import', icon: 'fa-file-import', action: 'import' }, { label: 'In an', icon: 'fa-print', action: 'printForms' }]
    },
    printFormsScreen: {
      title: 'In an',
      eyebrow: 'Bieu mau',
      icon: 'fa-print',
      subtitle: 'In nhanh cac bieu mau hanh chinh kho A4',
      search: 'Tim bieu mau...',
      primaryAction: { label: 'In bao cao', icon: 'fa-print', proxy: '[data-platform-action="admin.report.print"]' },
      nav: [{ label: 'Xuáº¥t Excel', icon: 'fa-file-excel', action: 'exportExcel' }, { label: 'BÃ¡o cÃ¡o', icon: 'fa-chart-pie', action: 'reports' }]
    },
    systemAdminScreen: {
      title: 'Quan tri he thong',
      eyebrow: 'Quan tri',
      icon: 'fa-screwdriver-wrench',
      subtitle: 'Theo doi suc khoe, phien dang nhap, hieu nang va cau hinh he thong',
      search: 'Tim tac vu quan tri...',
      primaryAction: { label: 'LÃ m má»›i', icon: 'fa-rotate-right', proxy: '[data-system-refresh], [data-platform-action="systemAdmin.refresh"]' },
      nav: [{ label: 'TÃ i khoáº£n', icon: 'fa-user-shield', action: 'users' }, { label: 'Sao luu', icon: 'fa-database', action: 'backups' }]
    },
    usersScreen: {
      title: 'TÃ i khoáº£n',
      eyebrow: 'Quan tri',
      icon: 'fa-user-shield',
      subtitle: 'Quáº£n lÃ½ ngÆ°á»i dÃ¹ng, vai trÃ² vÃ  tráº¡ng thÃ¡i tÃ i khoáº£n',
      search: 'Tim email, ten hien thi, vai tro...',
      primaryAction: { label: 'ThÃªm tÃ i khoáº£n', icon: 'fa-user-plus', proxy: '#userAddBtn, [data-platform-action="users.create"]' },
      nav: [{ label: 'Phan quyen', icon: 'fa-key', action: 'permissions' }, { label: 'Nhat ky', icon: 'fa-clock-rotate-left', action: 'logs' }]
    },
    permissionsScreen: {
      title: 'Phan quyen',
      eyebrow: 'Quan tri',
      icon: 'fa-key',
      subtitle: 'Thiet lap quyen theo vai tro, module va thao tac',
      search: 'Tim vai tro hoac quyen...',
      primaryAction: { label: 'Luu phan quyen', icon: 'fa-floppy-disk', proxy: '#permissionSaveBtn, [data-platform-action="admin.permissions.save"]' },
      nav: [{ label: 'TÃ i khoáº£n', icon: 'fa-user-shield', action: 'users' }, { label: 'Cáº¥u hÃ¬nh', icon: 'fa-gear', action: 'settings' }]
    },
    logsScreen: {
      title: 'Nhat ky',
      eyebrow: 'Quan tri',
      icon: 'fa-clock-rotate-left',
      subtitle: 'Theo doi nhat ky thao tac va hoat dong he thong',
      search: 'Tim nguoi dung, hanh dong, thoi gian...',
      primaryAction: { label: 'LÃ m má»›i', icon: 'fa-rotate-right', action: 'logs' },
      nav: [{ label: 'Dieu hanh', icon: 'fa-tower-broadcast', action: 'operationCenter' }, { label: 'Quan tri', icon: 'fa-screwdriver-wrench', action: 'systemAdmin' }]
    },
    backupsScreen: {
      title: 'Sao luu',
      eyebrow: 'Quan tri',
      icon: 'fa-database',
      subtitle: 'Theo doi ban sao luu va trang thai du lieu',
      search: 'Tim ban sao luu...',
      primaryAction: { label: 'Backup Database', icon: 'fa-database', proxy: '[data-system-backup="database"], [data-platform-action="systemAdmin.backup"]' },
      nav: [{ label: 'Khoi phuc', icon: 'fa-rotate-left', action: 'restore' }, { label: 'Quan tri', icon: 'fa-screwdriver-wrench', action: 'systemAdmin' }]
    },
    restoreScreen: {
      title: 'Khoi phuc',
      eyebrow: 'Quan tri',
      icon: 'fa-rotate-left',
      subtitle: 'Khoi phuc du lieu tu file SQL theo quy trinh hien co',
      search: 'Tim tac vu khoi phuc...',
      primaryAction: { label: 'Khoi phuc du lieu', icon: 'fa-rotate-left', proxy: '#restoreForm button[type="submit"]' },
      nav: [{ label: 'Sao luu', icon: 'fa-database', action: 'backups' }, { label: 'Quan tri', icon: 'fa-screwdriver-wrench', action: 'systemAdmin' }]
    },
    settingsScreen: {
      title: 'Cai dat',
      eyebrow: 'Quan tri',
      icon: 'fa-gear',
      subtitle: 'Cáº¥u hÃ¬nh thong tin he thong, don vi va bao cao',
      search: 'Tim truong cau hinh...',
      primaryAction: { label: 'Luu cau hinh', icon: 'fa-floppy-disk', proxy: '#settingsForm button[type="submit"]' },
      nav: [{ label: 'Giao dien', icon: 'fa-palette', action: 'appearance' }, { label: 'Quan tri', icon: 'fa-screwdriver-wrench', action: 'systemAdmin' }]
    },
    appearanceScreen: {
      title: 'Giao dien',
      eyebrow: 'Quan tri',
      icon: 'fa-palette',
      subtitle: 'Quáº£n lÃ½ logo, áº£nh ná»n vÃ  ná»™i dung hiá»ƒn thá»‹',
      search: 'Tim cau hinh giao dien...',
      primaryAction: { label: 'Luu giao dien', icon: 'fa-floppy-disk', proxy: '#appearanceForm button[type="submit"]' },
      nav: [{ label: 'Cai dat', icon: 'fa-gear', action: 'settings' }, { label: 'Quan tri', icon: 'fa-screwdriver-wrench', action: 'systemAdmin' }]
    }
  };

  var MODULE_LIST_META = {
    partyMembersScreen: { label: 'Danh sÃ¡ch Äáº£ng viÃªn', unit: 'há»“ sÆ¡', totalSelector: '#partyMemberTotalCount' },
    workTasksScreen: { label: 'Danh sÃ¡ch cÃ´ng viá»‡c', unit: 'cÃ´ng viá»‡c' },
    workCalendarScreen: { label: 'Danh sÃ¡ch lá»‹ch', unit: 'lá»‹ch' },
    documentsScreen: { label: 'Danh sÃ¡ch vÄƒn báº£n', unit: 'vÄƒn báº£n', totalSelector: '#documentsTotalCount' },
    financeScreen: { label: 'Danh sÃ¡ch thu chi', unit: 'phiáº¿u', totalSelector: '#financeTotalCount' },
    photoGalleryScreen: { label: 'Danh sÃ¡ch áº£nh', unit: 'áº£nh', totalSelector: '#photoGalleryTotalCount' },
    complaintsScreen: { label: 'Danh sÃ¡ch pháº£n Ã¡nh', unit: 'pháº£n Ã¡nh', totalSelector: '#complaintsTotalCount' },
    dataQualityScreen: { label: 'Danh sÃ¡ch váº¥n Ä‘á»', unit: 'váº¥n Ä‘á»', totalSelector: '#dataQualityIssueCount' },
    householdsScreen: { label: 'Danh sÃ¡ch há»™', unit: 'há»™', totalSelector: '#householdTotalCount' },
    personsScreen: { label: 'Danh sÃ¡ch nhÃ¢n kháº©u', unit: 'nhÃ¢n kháº©u', totalSelector: '#personTotalCount' },
    temporaryResidenceScreen: { label: 'Danh sÃ¡ch táº¡m trÃº', unit: 'nhÃ¢n kháº©u' },
    temporaryAbsenceScreen: { label: 'Táº¡m váº¯ng', unit: 'nhÃ¢n kháº©u' },
    movementsScreen: { label: 'Danh sÃ¡ch biáº¿n Ä‘á»™ng', unit: 'biáº¿n Ä‘á»™ng' },
    gisScreen: { label: 'Danh sÃ¡ch GIS', unit: 'Ä‘iá»ƒm' },
    publicAssetsScreen: { label: 'Danh sÃ¡ch cÃ´ng trÃ¬nh', unit: 'cÃ´ng trÃ¬nh', totalSelector: '#publicAssetsTotalCount' },
    housesScreen: { label: 'Danh sÃ¡ch nhÃ ', unit: 'nhÃ ', totalSelector: '#housesTotalCount' },
    vehiclesScreen: { label: 'Danh sÃ¡ch xe', unit: 'xe', totalSelector: '#vehiclesTotal' },
    livestockScreen: { label: 'Danh sÃ¡ch váº­t nuÃ´i', unit: 'con', totalSelector: '#livestockTotalCount' },
    agricultureScreen: { label: 'Danh sÃ¡ch thá»­a', unit: 'thá»­a', totalSelector: '#agriTotalCount' },
    agriculturalLandScreen: { label: 'Danh sÃ¡ch khu Ä‘áº¥t', unit: 'khu', totalSelector: '#agriculturalLandTotal' },
    businessHouseholdsScreen: { label: 'Danh sÃ¡ch há»™ kinh doanh', unit: 'há»™', totalSelector: '#businessHouseholdTotalCount' },
    contributionsScreen: { label: 'Danh sÃ¡ch khoáº£n thu', unit: 'khoáº£n' },
    reportsScreen: { label: 'Danh sÃ¡ch bÃ¡o cÃ¡o', unit: 'bÃ¡o cÃ¡o', totalSelector: '#reportCount' },
    operationCenterScreen: { label: 'Danh sÃ¡ch tÃ¡c vá»¥', unit: 'tÃ¡c vá»¥' },
    importScreen: { label: 'Danh sÃ¡ch tÃ¡c vá»¥', unit: 'tÃ¡c vá»¥' },
    exportExcelScreen: { label: 'Danh sÃ¡ch bÃ¡o cÃ¡o', unit: 'bÃ¡o cÃ¡o' },
    printFormsScreen: { label: 'Danh sÃ¡ch biá»ƒu máº«u', unit: 'biá»ƒu máº«u' },
    systemAdminScreen: { label: 'Danh sÃ¡ch tÃ¡c vá»¥', unit: 'tÃ¡c vá»¥' },
    usersScreen: { label: 'Danh sÃ¡ch tÃ i khoáº£n', unit: 'tÃ i khoáº£n', totalSelector: '#userPager' },
    permissionsScreen: { label: 'Danh sÃ¡ch quyá»n', unit: 'quyá»n' },
    logsScreen: { label: 'Danh sÃ¡ch nháº­t kÃ½', unit: 'dÃ²ng', totalSelector: '#logPager' },
    backupsScreen: { label: 'Danh sÃ¡ch sao lÆ°u', unit: 'báº£n sao', totalSelector: '#backupPager' },
    restoreScreen: { label: 'Danh sÃ¡ch khÃ´i phá»¥c', unit: 'tÃ¡c vá»¥' },
    settingsScreen: { label: 'Danh sÃ¡ch cáº¥u hÃ¬nh', unit: 'má»¥c' },
    appearanceScreen: { label: 'Danh sÃ¡ch giao diá»‡n', unit: 'má»¥c' }
  };

  function moduleMeta(key) {
    return MODULE_DASHBOARD_META[key] || {
      title: 'Dashboard',
      icon: 'fa-chart-simple',
      subtitle: 'Tá»•ng quan module',
      actions: [{ label: 'BÃ¡o cÃ¡o', icon: 'fa-chart-pie', action: 'reports' }]
    };
  }

  function moduleGeneratedAt(screen) {
    var node = screen.querySelector('[id$="GeneratedAt"]');
    return text(node && node.textContent) || 'Äang cáº­p nháº­t dá»¯ liá»‡u';
  }

  function firstFaIcon(node, fallback) {
    var iconNode = node && node.querySelector('i');
    if (!iconNode) return fallback || 'fa-chart-simple';
    return Array.from(iconNode.classList).filter(function (name) {
      return name.indexOf('fa-') === 0 && name !== 'fa-solid' && name !== 'fa-regular' && name !== 'fa-brands';
    })[0] || fallback || 'fa-chart-simple';
  }

  function numericOnlyValue(value) {
    return /^[\d.,\s/%]+$/.test(cleanLabel(value));
  }

  function withKpiUnit(value, unit) {
    var cleanValue = cleanLabel(value) || '0';
    if (!unit || !numericOnlyValue(cleanValue)) return cleanValue;
    if (unit === '%') return cleanValue.indexOf('%') >= 0 ? cleanValue : cleanValue + '%';
    if (cleanValue.indexOf('%') >= 0) return cleanValue;
    return cleanValue + ' ' + unit;
  }

  function readKpis(screen, moduleMeta) {
    var source = screen.querySelector('.dashboard-kpi-grid');
    var limit = Number(moduleMeta && moduleMeta.kpiLimit || 4);
    if (!Number.isFinite(limit) || limit < 1) limit = 4;
    var cards = source ? Array.from(source.children).slice(0, limit) : [];
    var metadata = (moduleMeta && moduleMeta.kpis) || [];
    var kpis = cards.map(function (card, index) {
      var kpiMeta = metadata[index] || {};
      var label = text(card.getAttribute('data-kpi-label') || (card.querySelector('.dashboard-kpi-label') && card.querySelector('.dashboard-kpi-label').textContent));
      var value = text(card.getAttribute('data-kpi-value') || (card.querySelector('.dashboard-kpi-value strong') && card.querySelector('.dashboard-kpi-value strong').textContent));
      var note = text(card.getAttribute('data-kpi-unit') || (card.querySelector('.dashboard-kpi-unit') && card.querySelector('.dashboard-kpi-unit').textContent));
      return {
        label: label || kpiMeta.label || 'Tá»•ng quan ' + (index + 1),
        value: withKpiUnit(value || '0', note || kpiMeta.unit),
        meta: kpiMeta.meta || note || 'Theo dá»¯ liá»‡u ' + ((screen && screen.getAttribute('data-module-dashboard')) || 'dashboard'),
        icon: firstFaIcon(card, kpiMeta.icon || (moduleMeta && moduleMeta.icon))
      };
    });
    return kpis.slice(0, limit);
  }

  function readSummaryItems(screen) {
    var items = [];
    var seen = {};
    function add(label, value) {
      label = cleanLabel(label);
      value = cleanLabel(value);
      if (!label || !value || value === '0') return;
      var id = label.toLowerCase() + '|' + value.toLowerCase();
      if (seen[id]) return;
      seen[id] = true;
      items.push({ label: label, value: value });
    }
    Array.from(screen.querySelectorAll('.dashboard-chart-grid .chart-line, .dashboard-chart-grid .dashboard-metric-line')).forEach(function (row) {
      add(text(row.querySelector('span')), text(row.querySelector('strong, b')));
    });
    Array.from(screen.querySelectorAll('.dashboard-chart-grid table tbody tr')).forEach(function (row) {
      var cells = Array.from(row.children).map(function (cell) { return cleanLabel(cell); }).filter(Boolean);
      if (cells.length >= 2) add(cells[0], cells[cells.length - 1]);
    });
    return items.slice(0, 6);
  }

  function readPanels(screen) {
    return Array.from(screen.querySelectorAll('.dashboard-chart-grid .dashboard-panel')).slice(0, 4).map(function (panel) {
      return {
        label: text(panel.querySelector('.dashboard-panel-head h3')) || 'Biá»ƒu Ä‘á»“',
        value: text(panel.querySelector('.dashboard-filter-pill')) || 'Chi tiáº¿t'
      };
    });
  }

  function renderModuleDashboard(screen) {
    var key = screen.getAttribute('data-module-dashboard') || '';
    if (!key) return;
    var meta = moduleMeta(key);
    var hostId = 'appV2' + key.charAt(0).toUpperCase() + key.slice(1);
    var host = document.getElementById(hostId);
    if (!host) {
      host = el('section', 'app-v2-page app-v2-module-dashboard', { id: hostId, 'aria-label': 'Dashboard ' + meta.title });
      screen.insertBefore(host, screen.firstChild);
    }
    var filterState = filterStates[hostId] || {};
    host.textContent = '';
    var kpis = readKpis(screen, meta);
    var summaryItems = readSummaryItems(screen);
    var filteredKpis = filterItems(kpis, filterState);

    var primary = el('div', 'app-v2-flow');
    var secondary = el('div', 'app-v2-flow');
    var layout = el('div', 'app-v2-two-pane');
    var statSection = AppSection({ title: 'Chá»‰ sá»‘', meta: 'Realtime' });
    var statGrid = el('div', 'app-v2-grid app-v2-dashboard-kpis');
    filteredKpis.forEach(function (item) { statGrid.appendChild(AppStatCard(item)); });
    append(statSection, [filteredKpis.length ? statGrid : AppEmptyState({ message: filterState.search || filterState.status ? 'KhÃ´ng tÃ¬m tháº¥y chá»‰ sá»‘ phÃ¹ há»£p' : 'Äang táº£i chá»‰ sá»‘ Dashboard', icon: 'fa-chart-simple' })]);

    var actionSection = AppSection({ title: 'Truy cáº­p nhanh', meta: 'Module' });
    append(actionSection, [AppList((meta.actions || []).map(function (item) {
      return { title: item.label, subtitle: 'Má»Ÿ ' + item.label.toLowerCase(), icon: item.icon, action: item.action };
    }))]);

    var metricSection = null;
    if (summaryItems.length) {
      metricSection = AppSection({ title: 'TÃ³m táº¯t', meta: 'Dá»¯ liá»‡u bá»• sung' });
      append(metricSection, [AppCard({ body: AppList(filterItems(summaryItems, filterState).map(function (item) { return AppMetricRow(item); })) })]);
    }

    var filterSection = AppSection({ title: 'Bá»™ lá»c nhanh', meta: 'Adaptive' });
    var filterSheet = AppFilterSheet({
      label: 'Bá»™ lá»c nhanh ' + meta.title,
      fields: [
        { label: 'Tá»« khÃ³a', type: 'search', placeholder: 'TÃ¬m trong ' + meta.title.toLowerCase() },
        { label: 'Tráº¡ng thÃ¡i', type: 'select', options: [{ label: 'Táº¥t cáº£', value: '' }, { label: 'Cáº§n xá»­ lÃ½', value: 'todo' }, { label: 'HoÃ n táº¥t', value: 'done' }] }
      ],
      actions: [{ label: 'Ãp dá»¥ng', icon: 'fa-filter' }, { label: 'LÃ m má»›i', icon: 'fa-rotate-right' }]
    });
    var searchControl = filterSheet.querySelector('[data-app-v2-filter-field="search"]');
    var statusControl = filterSheet.querySelector('[data-app-v2-filter-field="status"]');
    if (searchControl) searchControl.value = filterState.search || filterState.keyword || filterState.searchText || '';
    if (statusControl) statusControl.value = filterState.status || '';
    filterSheet.addEventListener('app-v2-filter-change', function (event) {
      filterStates[hostId] = event.detail.state || {};
      renderFilteredStats(statSection, kpis, filterStates[hostId], 'KhÃ´ng tÃ¬m tháº¥y chá»‰ sá»‘ phÃ¹ há»£p');
      if (metricSection) {
        var filteredSummaryItems = filterItems(summaryItems, filterStates[hostId]);
        setSectionBody(metricSection, filteredSummaryItems.length ? AppCard({ body: AppList(filteredSummaryItems.map(function (item) { return AppMetricRow(item); })) }) : AppEmptyState({ message: 'KhÃ´ng tÃ¬m tháº¥y tÃ³m táº¯t phÃ¹ há»£p', icon: 'fa-magnifying-glass' }));
      }
    });
    append(filterSection, [filterSheet]);

    append(primary, [statSection, filterSection, actionSection]);
    if (metricSection) append(secondary, [metricSection]);
    append(layout, [primary, secondary]);
    append(host, [
      AppHeader({
        eyebrow: 'Dashboard module',
        title: meta.title,
        subtitle: meta.subtitle + ' - ' + moduleGeneratedAt(screen),
        icon: meta.icon,
        iconLabel: meta.title
      }),
      AppTabs([
        { key: 'overview', label: 'Tá»•ng quan' },
        { key: 'data', label: 'Dá»¯ liá»‡u' },
        { key: 'report', label: 'BÃ¡o cÃ¡o', action: 'reports' }
      ], 'overview'),
      layout
    ]);
  }

  function moduleScreenMeta(screen) {
    return MODULE_SCREEN_META[screen.id] || null;
  }

  function countRecords(screen) {
    var rows = sourceRows(screen);
    if (rows.length) return rows.length;
    var cards = screen.querySelectorAll('.houses-card-grid > *, .livestock-card-grid > *, .agri-card-grid > *, [id$="Grid"] > *');
    if (cards.length) return cards.length;
    return 0;
  }

  function listSummaryMeta(screen, meta) {
    return Object.assign({ label: 'Danh sÃ¡ch ' + ((meta && meta.title) || 'dá»¯ liá»‡u'), unit: 'báº£n ghi' }, MODULE_LIST_META[screen.id] || {});
  }

  function parseCountText(value) {
    var normalized = text(value).replace(/\./g, '');
    var match = normalized.match(/(\d[\d,\s]*)/);
    if (!match) return null;
    var parsed = Number(match[1].replace(/[^\d]/g, ''));
    return Number.isFinite(parsed) ? parsed : null;
  }

  function desktopListTotal(screen, options, fallback) {
    var node = options && options.totalSelector ? screen.querySelector(options.totalSelector) || document.querySelector(options.totalSelector) : null;
    if (!node) node = screen.querySelector('[id$="TotalCount"], [id$="Total"], [id$="Count"]');
    var parsed = parseCountText(node && node.textContent);
    return parsed == null ? Number(fallback || 0) : parsed;
  }

  function listPager(screen, options) {
    var configured = options && options.pagerSelector ? screen.querySelector(options.pagerSelector) || document.querySelector(options.pagerSelector) : null;
    if (configured) return configured;
    return Array.from(screen.querySelectorAll('.pager, [id$="Pager"]')).find(function (node) {
      return node && node.querySelector && (node.querySelector('button') || cleanLabel(node.textContent));
    }) || null;
  }

  function appListState(meta) {
    var config = meta && meta.desktopFilter;
    return config && config.stateKey && window.App ? (window.App[config.stateKey] || null) : null;
  }

  function pagerInfo(pager) {
    var result = { page: null, totalPages: null };
    if (!pager) return result;
    var label = cleanLabel(pager.textContent);
    var match = label.match(/(?:Trang\s*)?(\d+)\s*\/\s*(\d+)/i);
    if (match) {
      result.page = Number(match[1]);
      result.totalPages = Number(match[2]);
      return result;
    }
    var active = pager.querySelector('.btn-primary, [aria-current="page"]');
    if (active) result.page = Number(cleanLabel(active.textContent) || active.getAttribute('data-page') || 1);
    var pages = Array.from(pager.querySelectorAll('[data-page], button')).map(function (button) {
      return Number(button.getAttribute('data-page') || cleanLabel(button.textContent));
    }).filter(function (value) {
      return Number.isFinite(value) && value > 0;
    });
    if (pages.length) result.totalPages = Math.max.apply(Math, pages);
    return result;
  }

  function paginationState(screen, meta, listMeta, total, renderedCount) {
    var state = appListState(meta) || {};
    var pager = listPager(screen, listMeta);
    var info = pagerInfo(pager);
    var pageSize = Math.max(1, Number(state.pageSize || renderedCount || 20));
    var totalItems = Math.max(0, Number(total || renderedCount || 0));
    var totalPages = Math.max(1, Number(info.totalPages || Math.ceil(totalItems / pageSize) || 1));
    var page = Math.min(totalPages, Math.max(1, Number(info.page || state.page || 1)));
    return {
      page: page,
      pageSize: pageSize,
      total: totalItems,
      totalPages: totalPages,
      hasNext: page < totalPages,
      hasPrevious: page > 1,
      pager: pager
    };
  }

  function clickPagerButton(pager, page, direction) {
    if (!pager) return false;
    var exact = Array.from(pager.querySelectorAll('button[data-page], a[data-page]')).find(function (button) {
      return Number(button.getAttribute('data-page')) === page && !button.disabled;
    });
    if (exact) {
      exact.click();
      return true;
    }
    var buttons = Array.from(pager.querySelectorAll('button, a')).filter(function (button) {
      return !button.disabled && button.getAttribute('aria-disabled') !== 'true';
    });
    var pattern = direction < 0 ? /(truoc|trÆ°á»›c|prev|previous)/i : /(sau|next)/i;
    var directional = buttons.find(function (button) {
      return pattern.test(cleanLabel(button.textContent + ' ' + button.getAttribute('aria-label') + ' ' + button.getAttribute('title')));
    });
    if (directional) {
      directional.click();
      return true;
    }
    return false;
  }

  function goToPage(screen, meta, listMeta, current, page, host) {
    var nextPage = Math.max(1, Math.min(Number(current.totalPages || 1), Number(page || 1)));
    if (nextPage === current.page) return;
    if (host) host.setAttribute('data-app-v2-list-loading', 'true');
    if (clickPagerButton(current.pager || listPager(screen, listMeta), nextPage, nextPage < current.page ? -1 : 1)) {
      window.setTimeout(schedule, 80);
      window.setTimeout(schedule, 240);
      return;
    }
    var config = meta && meta.desktopFilter;
    var state = appListState(meta);
    var loader = config && config.loaderName && window[config.loaderName];
    if (state && typeof loader === 'function') {
      state.page = nextPage;
      Promise.resolve(loader()).catch(function () {
        if (host) host.setAttribute('data-app-v2-list-error', 'true');
      }).finally(function () {
        if (host) host.removeAttribute('data-app-v2-list-loading');
        schedule();
      });
    }
  }

  function AppBackToTop() {
    var button = el('button', 'app-v2-icon-button app-v2-back-to-top', {
      type: 'button',
      'aria-label': 'Quay ve dau danh sach',
      title: 'Quay ve dau danh sach',
      'data-app-v2-back-to-top': 'true'
    });
    append(button, [icon('fa-arrow-up')]);
    return button;
  }

  function cleanLabel(value) {
    return text(value).replace(/\s+/g, ' ').replace(/[:ï¼š]+$/, '').trim();
  }

  function foldedText(value) {
    var normalized = cleanLabel(value).toLowerCase();
    if (normalized.normalize) normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return normalized.replace(/\u0111/g, 'd').replace(/\u0110/g, 'd');
  }

  function isActionLabel(label) {
    return /^(thao tÃ¡c|actions?|chá»n|checkbox)$/i.test(cleanLabel(label));
  }

  function isAddressLabel(label) {
    return /\b(address|dia chi)\b/.test(foldedText(label));
  }

  function isAddressValue(value) {
    var folded = foldedText(value);
    if (!folded || /^(active|inactive|pending|done|completed|bh|kd|gps)\b/.test(folded)) return false;
    return /\b(thon|xom|ap|xa|phuong|huyen|quan|tinh|tp|thanh pho|duong|pho|ngo|ngach|hem|to dan pho|khu|so nha|sn)\b/.test(folded) || (folded.indexOf(',') >= 0 && /\b(thon|xa|huyen|tinh|khu)\b/.test(folded));
  }

  function isActionNode(node) {
    return node && node.nodeType === 1 && node.matches && node.matches('button, a, input, select, textarea, [data-platform-action], [data-edit], [data-del]');
  }

  function directCellParts(cell) {
    var parts = [];
    Array.from(cell.childNodes || []).forEach(function (node) {
      if (!node) return;
      if (node.nodeType === 3) {
        var inlineText = cleanLabel(node.textContent);
        if (inlineText) parts.push(inlineText);
        return;
      }
      if (node.nodeType !== 1 || isActionNode(node) || (node.closest && node.closest('button, a, [data-platform-action]'))) return;
      if (node.tagName === 'BR') return;
      var value = cleanLabel(node.textContent);
      if (value) parts.push(value);
    });
    var seen = {};
    return parts.filter(function (part) {
      var key = foldedText(part);
      if (!key || seen[key]) return false;
      seen[key] = true;
      return true;
    });
  }

  function splitCellFields(cell, label, value) {
    var parts = directCellParts(cell);
    if (parts.length < 2) return [{ label: label, value: value }];
    var addressParts = parts.slice(1).filter(isAddressValue);
    if (!isAddressLabel(label) && !addressParts.length) return [{ label: label, value: value }];
    var fields = [{ label: label, value: parts[0] || value }];
    addressParts.forEach(function (part) {
      fields.push({ label: 'Äá»‹a chá»‰', value: part });
    });
    return fields;
  }

  function isDataRow(row) {
    if (!row || !row.children || row.children.length <= 1) return false;
    var joined = text(row);
    if (!joined) return false;
    return true;
  }

  function tableHeaders(row) {
    var table = row && row.closest ? row.closest('table') : null;
    if (!table) return [];
    return Array.from(table.querySelectorAll('thead th')).map(cleanLabel);
  }

  function rowFields(row) {
    var headers = tableHeaders(row);
    return Array.from(row.children).reduce(function (list, cell, index) {
      var label = cleanLabel(cell.getAttribute('data-label') || headers[index] || '');
      var value = text(cell);
      splitCellFields(cell, label, value).forEach(function (field) {
        list.push(field);
      });
      return list;
    }, []).filter(function (field) {
      return field.value && !isActionLabel(field.label);
    });
  }

  function rowActionButtons(row) {
    var headers = tableHeaders(row);
    var cells = Array.from(row.children || []);
    var actionCells = cells.filter(function (cell, index) {
      var label = cleanLabel(cell.getAttribute('data-label') || headers[index] || '');
      return isActionLabel(label);
    });
    var roots = actionCells.length ? actionCells : [row];
    return roots.reduce(function (list, root) {
      return list.concat(Array.from(root.querySelectorAll('button[data-platform-action], a[data-platform-action], button[data-edit], button[data-del]')));
    }, []);
  }

  function sourceRows(screen) {
    var tableRows = Array.from(screen.querySelectorAll('tbody tr'));
    var directRows = Array.from(screen.querySelectorAll('[id$="Rows"] > *')).filter(function (node) {
      return node.tagName !== 'TR' && !(node.closest && node.closest('tbody'));
    });
    return tableRows.concat(directRows).filter(isDataRow);
  }

  function sourceActionSelector(button) {
    if (!button) return '';
    if (!button.getAttribute('data-app-v2-source-action')) {
      sourceActionSelector.next = (sourceActionSelector.next || 0) + 1;
      button.setAttribute('data-app-v2-source-action', 'a' + sourceActionSelector.next);
    }
    return '[data-app-v2-source-action="' + button.getAttribute('data-app-v2-source-action') + '"]';
  }

  function actionIcon(button) {
    var action = String(button.getAttribute('data-platform-action') || '').toLowerCase();
    var label = cleanLabel(button.getAttribute('title') || button.getAttribute('aria-label') || text(button)).toLowerCase();
    if (/delete|xÃ³a|xoa/.test(action + ' ' + label)) return 'fa-trash';
    if (/edit|sá»­a|sua/.test(action + ' ' + label)) return 'fa-pen';
    if (/print|in\b/.test(action + ' ' + label)) return 'fa-print';
    if (/pdf/.test(action + ' ' + label)) return 'fa-file-pdf';
    if (/excel/.test(action + ' ' + label)) return 'fa-file-excel';
    if (/map|gis|Ä‘á»‹nh vá»‹|dinh vi|location/.test(action + ' ' + label)) return 'fa-location-dot';
    return 'fa-eye';
  }

  function actionLabel(button) {
    var label = cleanLabel(button.getAttribute('title') || button.getAttribute('aria-label') || text(button));
    var action = String(button.getAttribute('data-platform-action') || '');
    if (/detail|xem chi|chi tiet|chi tiáº¿t/i.test(action + ' ' + label)) return 'Xem';
    if (label) return label;
    if (/delete/i.test(action)) return 'XÃ³a';
    if (/edit/i.test(action)) return 'Sá»­a';
    return 'Xem';
  }

  function actionIdentity(button) {
    var data = button.dataset || {};
    var action = data.platformAction || (button.hasAttribute('data-edit') ? 'edit' : '') || (button.hasAttribute('data-del') ? 'delete' : '');
    var detailAction = /detail/i.test(action);
    var id = detailAction ? (data.householdId || data.id || data.citizenId || data.personId || data.publicAssetId || data.vehicleId || data.target || '') : (data.id || data.householdId || data.citizenId || data.personId || data.publicAssetId || data.vehicleId || data.target || '');
    if (action || id) return [action, id].join(':');
    return cleanLabel(actionLabel(button)).toLowerCase();
  }

  function rowActions(row, meta) {
    var buttons = rowActionButtons(row).filter(function (button) {
      return !button.matches('input, [disabled]');
    });
    if (meta && meta.actionMode === 'detailOnly') {
      buttons = buttons.filter(function (button) {
        return /detail|xem|chi ti/i.test(String(button.getAttribute('data-platform-action') || '') + ' ' + String(button.getAttribute('title') || button.getAttribute('aria-label') || text(button)));
      });
    }
    var seen = {};
    return buttons.map(function (button) {
      var identity = actionIdentity(button);
      if (seen[identity]) return null;
      seen[identity] = true;
      var proxy = sourceActionSelector(button);
      return { label: actionLabel(button), icon: actionIcon(button), proxy: proxy };
    }).filter(Boolean).slice(0, 3);
  }

  function primaryProxy(actions) {
    var detail = (actions || []).find(function (item) {
      return /xem|chi tiáº¿t|chi tiet/i.test(item.label || '') || /fa-eye/.test(item.icon || '');
    });
    return (detail || actions[0] || {}).proxy || '';
  }

  function matchesAny(value, patterns) {
    var normalized = cleanLabel(value).toLowerCase();
    var folded = normalized.normalize ? normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : normalized;
    return (patterns || []).some(function (pattern) {
      var normalizedPattern = cleanLabel(pattern).toLowerCase();
      var foldedPattern = normalizedPattern.normalize ? normalizedPattern.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : normalizedPattern;
      return normalized.indexOf(normalizedPattern) >= 0 || folded.indexOf(foldedPattern) >= 0;
    });
  }

  function foldedLabel(value) {
    var normalized = cleanLabel(value).toLowerCase();
    return normalized.normalize ? normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : normalized;
  }

  function isExactLabel(value, patterns) {
    var folded = foldedLabel(value);
    return (patterns || []).some(function (pattern) {
      return folded === foldedLabel(pattern);
    });
  }

  function pickField(fields, labels) {
    var source = fields || [];
    for (var i = 0; i < (labels || []).length; i += 1) {
      var match = source.find(function (field) {
        return matchesAny(field.label, [labels[i]]);
      });
      if (match) return match;
    }
    return null;
  }

  function pickExactField(fields, labels) {
    var source = fields || [];
    for (var i = 0; i < (labels || []).length; i += 1) {
      var match = source.find(function (field) {
        return isExactLabel(field.label, [labels[i]]);
      });
      if (match) return match;
    }
    return null;
  }

  function recordTitle(fields, meta, index) {
    var titleLabels = (meta.titleLabels || []).concat(['Há» tÃªn', 'Há» vÃ  tÃªn', 'TÃªn', 'Chá»§ há»™', 'TÃªn há»™', 'TÃªn cÃ´ng trÃ¬nh', 'TÃªn tÃ i sáº£n', 'CÆ¡ sá»Ÿ', 'MÃ£ há»™', 'MÃ£ nhÃ¢n kháº©u', 'MÃ£ thá»­a', 'Biá»ƒn sá»‘']);
    var titleField = pickField(fields, titleLabels);
    if (titleField && titleField.value) return titleField.value;
    var fallback = fields.find(function (field) {
      return field.value.length > 2 && !/^\d+$/.test(field.value);
    });
    return fallback ? fallback.value : meta.title + ' #' + (index + 1);
  }

  function recordMeta(fields, title, meta) {
    var metaLabels = (meta.metaLabels || []).concat(['Äá»‹a chá»‰', 'MÃ£ há»™', 'CCCD', 'Khu', 'Khu vá»±c', 'Tráº¡ng thÃ¡i', 'Loáº¡i', 'Diá»‡n tÃ­ch']);
    var selected = fields.filter(function (field) {
      return field.value !== title && !isAddressLabel(field.label) && matchesAny(field.label, metaLabels);
    }).slice(0, 4);
    if (!selected.length) {
      selected = fields.filter(function (field) {
        return field.value !== title && !isAddressLabel(field.label) && !/^(stt|#)$/i.test(cleanLabel(field.label));
      }).slice(0, 4);
    }
    return selected.map(function (field) {
      return field.label ? field.label + ': ' + field.value : field.value;
    }).join(' - ') || meta.subtitle;
  }

  function fieldIdentity(field) {
    var label = isAddressLabel(field && field.label) ? 'dia chi' : foldedText(field && field.label);
    return label + ':' + foldedText(field && field.value);
  }

  function householdCode(value) {
    var match = cleanLabel(value).match(/H\d{2}-\d+/i);
    return match ? match[0] : '';
  }

  function derivedHouseholdOwner(fields, title) {
    var source = (fields || []).find(function (field) {
      return householdCode(field.value) && cleanLabel(field.value).replace(householdCode(field.value), '').trim();
    });
    if (!source) return null;
    var owner = cleanLabel(source.value).replace(householdCode(source.value), '').trim();
    var separatorIndex = owner.indexOf(':');
    var fullSeparatorIndex = owner.indexOf('ï¼š');
    if (separatorIndex < 0 || (fullSeparatorIndex >= 0 && fullSeparatorIndex < separatorIndex)) separatorIndex = fullSeparatorIndex;
    if (separatorIndex > 0 && isExactLabel(owner.slice(0, separatorIndex), ['Chá»§ há»™', 'TÃªn chá»§ há»™', 'Chu ho', 'Ten chu ho'])) {
      owner = owner.slice(separatorIndex + 1).trim();
    }
    return owner && owner !== title ? { label: 'Chá»§ há»™', value: owner } : null;
  }

  function normalizedSummaryField(field, requestedLabel, title) {
    if (!field) return null;
    var label = cleanLabel(requestedLabel || field.label);
    var value = cleanLabel(field.value);
    var foldedValue = foldedLabel(value);
    var foldedRequested = foldedLabel(label);
    if (foldedValue.indexOf(foldedRequested + ':') === 0 || foldedValue.indexOf(foldedRequested + ' ') === 0) {
      value = value.slice(label.length).replace(/^[:ï¼š\s]+/, '').trim();
    }
    if (matchesAny(label, ['MÃ£ há»™', 'Ma ho'])) value = householdCode(value) || value;
    if (matchesAny(label, ['Chá»§ há»™', 'TÃªn chá»§ há»™', 'Chu ho', 'Ten chu ho'])) {
      var separatorIndex = value.indexOf(':');
      var fullSeparatorIndex = value.indexOf('ï¼š');
      if (separatorIndex < 0 || (fullSeparatorIndex >= 0 && fullSeparatorIndex < separatorIndex)) separatorIndex = fullSeparatorIndex;
      if (separatorIndex > 0 && isExactLabel(value.slice(0, separatorIndex), ['Chá»§ há»™', 'TÃªn chá»§ há»™', 'Chu ho', 'Ten chu ho'])) {
        value = value.slice(separatorIndex + 1).trim();
      }
      value = value.replace(householdCode(value), '').trim();
    }
    if (!value || value === title || (title && title.indexOf(value) >= 0)) return null;
    return { label: label, value: value };
  }

  function numericFieldValue(fields, labels) {
    var field = pickField(fields, labels);
    if (!field) return null;
    var normalized = cleanLabel(field.value).replace(/\./g, '').replace(',', '.');
    var parsed = Number(normalized.replace(/[^\d.-]/g, ''));
    return Number.isFinite(parsed) ? parsed : null;
  }

  function householdMemberSummaryField(fields, meta) {
    if (!meta || meta.listKind !== 'households') return null;
    var explicitTotal = numericFieldValue(fields, [
      'NhÃ¢n kháº©u', 'Sá»‘ nhÃ¢n kháº©u', 'ThÃ nh viÃªn', 'Sá»‘ thÃ nh viÃªn',
      'NhÃ¢n kháº©u', 'Sá»‘ nhÃ¢n kháº©u', 'ThÃ nh viÃªn', 'Sá»‘ thÃ nh viÃªn',
      'member_count', 'member_count_real', 'total_members'
    ]);
    var atHome = numericFieldValue(fields, ['á»ž nhÃ ', 'O nha', 'at_home_count']);
    var away = numericFieldValue(fields, ['Äi váº¯ng', 'Di vang', 'away_count']);
    var total = explicitTotal != null ? explicitTotal : null;
    if (total == null && (atHome != null || away != null)) total = Number(atHome || 0) + Number(away || 0);
    if (total == null) return null;
    return { key: 'household-members', label: 'NhÃ¢n kháº©u', value: number(total) + ' nhÃ¢n kháº©u', tone: 'population' };
  }

  function recordSummaryFields(fields, title, meta) {
    var labels = meta.summaryLabels || meta.metaLabels || [];
    var selected = [];
    var memberSummaryField = householdMemberSummaryField(fields, meta);
    if (memberSummaryField) selected.push(memberSummaryField);
    (labels || []).forEach(function (label) {
      var isOwnerLabel = matchesAny(label, ['Chá»§ há»™', 'TÃªn chá»§ há»™', 'Chu ho', 'Ten chu ho']);
      var match = isOwnerLabel ? pickExactField(fields, [label]) : pickField(fields, [label]);
      var summaryField = match ? normalizedSummaryField(match, label, title) : null;
      if (!summaryField && isOwnerLabel) summaryField = derivedHouseholdOwner(fields, title);
      if (!summaryField || isActionLabel(summaryField.label)) return;
      if (selected.some(function (field) { return fieldIdentity(field) === fieldIdentity(summaryField); })) return;
      if (selected.some(function (field) { return cleanLabel(field.value).toLowerCase() === cleanLabel(summaryField.value).toLowerCase(); })) return;
      selected.push(summaryField);
    });
    return selected.slice(0, 4);
  }

  function recordDetails(fields, title, summaryFields, meta) {
    var summaryIds = {};
    var summaryLabels = meta.summaryLabels || [];
    var summaryValues = (summaryFields || []).map(function (field) { return cleanLabel(field.value); }).filter(Boolean);
    (summaryFields || []).forEach(function (field) {
      summaryIds[fieldIdentity(field)] = true;
    });
    var seen = {};
    return (fields || []).filter(function (field) {
      var label = cleanLabel(field.label);
      var value = cleanLabel(field.value);
      var repeatsSummary = summaryValues.some(function (summaryValue) {
        return summaryValue && value.indexOf(summaryValue) >= 0;
      });
      var identity = fieldIdentity(field);
      if (!field.value || field.value === title || repeatsSummary || summaryIds[identity] || matchesAny(label, summaryLabels) || /^(stt|#)$/i.test(label) || isActionLabel(label) || seen[identity]) return false;
      seen[identity] = true;
      return true;
    });
  }

  function sourceRecords(screen, meta) {
    var rows = sourceRows(screen);
    if (rows.length) {
      return rows.map(function (row, index) {
        var fields = rowFields(row);
        var joined = fields.map(function (field) { return field.value; }).join(' ');
        var badges = [];
        if (/Táº¡m trÃº/i.test(joined)) badges.push({ label: 'Táº¡m trÃº', tone: 'warning' });
        if (/Táº¡m váº¯ng|Äi váº¯ng/i.test(joined)) badges.push({ label: 'Táº¡m váº¯ng', tone: 'danger' });
        if (/ThÆ°á»ng trÃº|á»ž nhÃ /i.test(joined) && !badges.length) badges.push({ label: 'ThÆ°á»ng trÃº', tone: 'success' });
        var title = recordTitle(fields, meta, index);
        var actions = rowActions(row, meta);
        var summaryFields = recordSummaryFields(fields, title, meta);
        return {
          title: title,
          meta: recordMeta(fields, title, meta),
          summaryFields: summaryFields,
          searchFields: fields,
          searchText: text(row),
          icon: meta.icon,
          action: screen.id.replace(/Screen$/, ''),
          badges: badges,
          actions: actions,
          primaryProxy: primaryProxy(actions),
          details: recordDetails(fields, title, summaryFields, meta)
        };
      });
    }
    var cards = Array.from(screen.querySelectorAll('.houses-card-grid > *, .livestock-card-grid > *, .agri-card-grid > *, [id$="Grid"] > *'));
    if (cards.length) {
      return cards.map(function (card, index) {
        var title = text(card.querySelector('h3, h4, strong, .card-title')) || meta.title + ' #' + (index + 1);
        var body = text(card).replace(title, '').trim();
        return {
          title: title,
          meta: body.slice(0, 120) || meta.subtitle,
          searchText: text(card),
          icon: meta.icon,
          action: screen.id.replace(/Screen$/, ''),
          badges: [],
          details: body ? [{ label: 'Ná»™i dung', value: body }] : []
        };
      });
    }
    return [];
  }

  function itemSearchText(item) {
    var parts = [
      item && item.title,
      item && item.label,
      item && item.value,
      item && item.meta,
      item && item.subtitle,
      item && item.unit,
      item && item.searchText
    ];
    (item && item.searchFields || []).forEach(function (field) {
      parts.push(field.label, field.value);
    });
    (item && item.summaryFields || []).forEach(function (field) {
      parts.push(field.label, field.value);
    });
    (item && item.badges || []).forEach(function (badge) {
      parts.push(badge.label, badge.tone);
    });
    (item && item.details || []).forEach(function (field) {
      parts.push(field.label, field.value);
    });
    (item && item.items || []).forEach(function (child) {
      parts.push(child.label, child.value);
    });
    return normalizeSearchText(parts.filter(Boolean).join(' '));
  }

  function itemMatchesSearch(item, query) {
    var needle = normalizeSearchText(query);
    if (!needle) return true;
    return itemSearchText(item).indexOf(needle) >= 0;
  }

  function itemMatchesStatus(item, status) {
    var value = normalizeSearchText(status);
    if (!value) return true;
    var haystack = itemSearchText(item);
    if (haystack.indexOf(value) >= 0) return true;
    if (value === 'todo') return /(can xu ly|chua|thieu|het han|qua han|cho|pending|inactive|suspended)/.test(haystack);
    if (value === 'done') return /(hoan tat|hoan thanh|da thu|da dong|da dinh vi|done|completed)/.test(haystack);
    if (value === 'active') return /(dang quan ly|dang hoat dong|dang su dung|active|co du lieu)/.test(haystack);
    return true;
  }

  function filterItems(items, state) {
    var search = state && (state.search || state.searchText || state.keyword) || '';
    var status = state && state.status || '';
    return (items || []).filter(function (item) {
      return itemMatchesSearch(item, search) && itemMatchesStatus(item, status);
    });
  }

  function replaceChildren(node, children) {
    if (!node) return;
    node.textContent = '';
    (children || []).forEach(function (child) {
      node.appendChild(child);
    });
  }

  function setSectionBody(section, body) {
    if (!section) return;
    while (section.children.length > 1) section.removeChild(section.lastChild);
    section.appendChild(body);
  }

  function renderFilteredRecords(recordList, records, state) {
    var filteredRecords = filterItems(records, state);
    if (filteredRecords.length) {
      replaceChildren(recordList, filteredRecords.map(function (record) { return AppRecordCard(record); }));
    } else {
      replaceChildren(recordList, [AppEmptyState({ message: 'KhÃ´ng tÃ¬m tháº¥y báº£n ghi phÃ¹ há»£p', icon: 'fa-magnifying-glass' })]);
    }
    return filteredRecords;
  }

  function renderFilteredStats(section, items, state, emptyMessage) {
    var filteredItems = filterItems(items, state);
    var grid = el('div', 'app-v2-grid app-v2-dashboard-kpis');
    filteredItems.forEach(function (item) { grid.appendChild(AppStatCard(item)); });
    setSectionBody(section, filteredItems.length ? grid : AppEmptyState({ message: emptyMessage, icon: 'fa-magnifying-glass' }));
    return filteredItems;
  }

  function renderFilteredSummaryList(list, items, state) {
    var filteredItems = filterItems(items, state);
    if (filteredItems.length) {
      replaceChildren(list, filteredItems.map(function (item) { return AppSummaryCard(item); }));
    } else {
      replaceChildren(list, [AppEmptyState({ message: 'KhÃ´ng tÃ¬m tháº¥y tÃ³m táº¯t phÃ¹ há»£p', icon: 'fa-magnifying-glass' })]);
    }
    return filteredItems;
  }

  function desktopFilterState(meta) {
    var config = meta && meta.desktopFilter;
    if (!config) return {};
    var appState = window.App && window.App[config.stateKey] ? window.App[config.stateKey] : {};
    var searchControl = config.searchSelector ? document.querySelector(config.searchSelector) : null;
    var statusControl = config.statusSelector ? document.querySelector(config.statusSelector) : null;
    return {
      search: searchControl ? searchControl.value : (appState.search || ''),
      status: statusControl ? statusControl.value : (appState.status || '')
    };
  }

  function moduleFilterHasFocus(host) {
    var filter = host && host.querySelector('.app-v2-filter-bar');
    return !!(filter && document.activeElement && filter.contains(document.activeElement));
  }

  function renderDesktopFilteredRecords(screen, meta, host, state) {
    var recordList = host && host.querySelector('[data-app-v2-record-list]');
    if (!recordList) return false;
    var clientState = meta && meta.desktopFilter ? { status: state && state.status && !meta.desktopFilter.statusSelector ? state.status : '' } : state;
    var renderedCount = renderFilteredRecords(recordList, sourceRecords(screen, meta), clientState || {});
    var listMeta = listSummaryMeta(screen, meta);
    var total = desktopListTotal(screen, listMeta, renderedCount.length);
    updateListSummary(host.querySelector('[data-app-v2-list-summary]'), listMeta, total);
    var pagerHost = host.querySelector('[data-app-v2-pagination-host]');
    if (pagerHost) {
      var pagination = paginationState(screen, meta, listMeta, total, renderedCount.length);
      replaceChildren(pagerHost, pagination.totalPages > 1 ? [AppPagination(pagination)] : [AppEndState()]);
    }
    return true;
  }

  function applyDesktopFilter(screen, meta, host, state, done) {
    var config = meta && meta.desktopFilter;
    if (!config) return false;
    var search = state && (state.search || state.searchText || state.keyword) || '';
    var status = state && state.status || '';
    var appState = window.App && config.stateKey ? (window.App[config.stateKey] = window.App[config.stateKey] || {}) : null;
    var searchControl = config.searchSelector ? document.querySelector(config.searchSelector) : null;
    var statusControl = config.statusSelector ? document.querySelector(config.statusSelector) : null;
    if (searchControl && searchControl.value !== search) searchControl.value = search;
    if (statusControl && statusControl.value !== status) statusControl.value = status;
    if (appState) {
      appState.search = search;
      appState.page = 1;
      if (config.statusSelector) appState.status = status;
    }
    if (host) host.setAttribute('data-app-v2-desktop-filtering', 'true');
    var loader = config.loaderName && window[config.loaderName];
    if (typeof loader !== 'function') {
      if (typeof done === 'function') done();
      return true;
    }
    Promise.resolve(loader()).finally(function () {
      if (host) host.removeAttribute('data-app-v2-desktop-filtering');
      if (typeof done === 'function') done();
      renderDesktopFilteredRecords(screen, meta, host, state);
    });
    return true;
  }

  function personScopeMetricKeys(scope) {
    if (!scope || !scope.key) return null;
    if (scope.key === 'temporaryResidence') return ['temporary_residence_count', 'temporary_count'];
    if (scope.key === 'temporaryAbsence') return ['temporary_absence_count', 'away_count'];
    return null;
  }

  function scopeMetricValue(screen, scope) {
    if (!screen || screen.id !== 'personsScreen') return null;
    var keys = personScopeMetricKeys(scope);
    var metrics = window.App && window.App.persons && window.App.persons.metrics;
    if (!keys || !metrics) return null;
    for (var i = 0; i < keys.length; i += 1) {
      if (Object.prototype.hasOwnProperty.call(metrics, keys[i])) return Number(metrics[keys[i]] || 0);
    }
    return null;
  }

  function scopedCount(screen, scope) {
    var metricValue = scopeMetricValue(screen, scope);
    if (metricValue !== null) return metricValue;
    if (!scope || !scope.match) return 0;
    return sourceRows(screen).filter(function (row) {
      return text(row).indexOf(scope.match) >= 0;
    }).length;
  }

  function overviewMode(meta) {
    return meta && meta.overviewMode ? meta.overviewMode : 'compact';
  }

  function overviewTotalLabel(listMeta, meta) {
    var base = cleanLabel(listMeta && listMeta.label);
    if (base) base = base.replace(/^Danh sÃ¡ch\s+/i, '');
    return 'Tá»•ng ' + (base || cleanLabel(meta && meta.title) || 'báº£n ghi');
  }

  function buildModuleOverview(screen, meta, listMeta, total) {
    var sectionTitle = meta && meta.overviewMode === 'compact' ? 'TÃ³m táº¯t' : 'Tá»•ng quan';
    var section = AppSection({ title: sectionTitle, meta: total ? number(total) + ' ' + ((listMeta && listMeta.unit) || 'báº£n ghi') : '0 ' + ((listMeta && listMeta.unit) || 'báº£n ghi') });
    var grid = el('div', 'app-v2-grid app-v2-module-overview-grid');
    grid.appendChild(AppSummaryCard({
      label: overviewTotalLabel(listMeta, meta),
      value: number(total),
      note: meta.subtitle,
      icon: meta.icon
    }));
    (meta.scopes || []).forEach(function (scope) {
      if (!scope || !scope.label) return;
      var usesMetric = scopeMetricValue(screen, scope) !== null;
      var scopeCard = AppSummaryCard({
        label: scope.label,
        value: number(scopedCount(screen, scope)),
        note: 'Theo dá»¯ liá»‡u Ä‘ang hiá»ƒn thá»‹',
        icon: scope.icon || meta.icon
      });
      if (usesMetric) {
        var scopeNote = scopeCard.querySelector('.app-v2-summary-note');
        if (scopeNote) scopeNote.textContent = 'Theo th\u1ed1ng k\u00ea to\u00e0n h\u1ec7 th\u1ed1ng';
      }
      scopeCard.setAttribute('data-app-v2-scope', scope.key || scope.label);
      grid.appendChild(scopeCard);
    });
    append(section, [grid]);
    return section;
  }

  function renderModuleScreen(screen) {
    var meta = moduleScreenMeta(screen);
    if (!meta) return;
    var hostId = 'appV2' + screen.id.charAt(0).toUpperCase() + screen.id.slice(1);
    var host = document.getElementById(hostId);
    if (!host) {
      host = el('section', 'app-v2-page app-v2-module-screen', { id: hostId, 'aria-label': meta.title });
      screen.insertBefore(host, screen.firstChild);
    }
    host.removeAttribute('data-app-v2-list-loading');
    host.removeAttribute('data-app-v2-list-error');
    var filterState = filterStates[hostId] || desktopFilterState(meta);
    if (moduleFilterHasFocus(host) && renderDesktopFilteredRecords(screen, meta, host, filterState)) return;
    host.textContent = '';
    var total = countRecords(screen);
    var records = sourceRecords(screen, meta);
    var listMeta = listSummaryMeta(screen, meta);
    var primary = el('div', 'app-v2-flow');
    var secondary = el('div', 'app-v2-flow');
    var layout = el('div', 'app-v2-two-pane');

    var summary = meta.overviewMode !== 'none'
      ? buildModuleOverview(screen, meta, listMeta, desktopListTotal(screen, listMeta, total))
      : null;

    var list = AppSection({ title: 'Danh sÃ¡ch', meta: 'Card List' });
    var recordList = el('div', 'app-v2-list');
    recordList.setAttribute('data-app-v2-record-list', 'true');
    var filteredRecords = renderFilteredRecords(recordList, records, meta.desktopFilter ? { status: filterState.status && !meta.desktopFilter.statusSelector ? filterState.status : '' } : filterState);
    var listTotal = desktopListTotal(screen, listMeta, filteredRecords.length);
    var listSummary = AppListSummary(Object.assign({}, listMeta, { total: listTotal }));
    var paginationHost = el('div', 'app-v2-pagination-host', { 'data-app-v2-pagination-host': 'true' });
    var listPagination = paginationState(screen, meta, listMeta, listTotal, filteredRecords.length);
    paginationHost.appendChild(listPagination.totalPages > 1 ? AppPagination(listPagination) : AppEndState());
    paginationHost.addEventListener('click', function (event) {
      var button = event.target.closest('[data-app-v2-page]');
      if (!button || button.disabled) return;
      var latestRecords = sourceRecords(screen, meta);
      var latestTotal = desktopListTotal(screen, listMeta, latestRecords.length);
      var latestPagination = paginationState(screen, meta, listMeta, latestTotal, latestRecords.length);
      goToPage(screen, meta, listMeta, latestPagination, Number(button.getAttribute('data-app-v2-page')), host);
    });
    append(list, [recordList]);

    var filters = AppSection({ title: 'Bá»™ lá»c', meta: 'Bottom Sheet ready' });
    var filterSheet = AppFilterSheet({
      label: 'Bá»™ lá»c ' + meta.title,
      fields: [
        { label: 'Tá»« khÃ³a', type: 'search', placeholder: meta.search || 'TÃ¬m kiáº¿m...' },
        { label: 'Tráº¡ng thÃ¡i', type: 'select', options: [{ label: 'Táº¥t cáº£', value: '' }, { label: 'Äang quáº£n lÃ½', value: 'active' }, { label: 'Cáº§n xá»­ lÃ½', value: 'todo' }] }
      ],
      actions: [{ label: 'Ãp dá»¥ng', icon: 'fa-filter' }, { label: 'Äáº·t láº¡i', icon: 'fa-rotate-right' }]
    });
    var searchControl = filterSheet.querySelector('[data-app-v2-filter-field="search"]');
    var statusControl = filterSheet.querySelector('[data-app-v2-filter-field="status"]');
    if (searchControl) searchControl.value = filterState.search || filterState.keyword || filterState.searchText || '';
    if (statusControl) statusControl.value = filterState.status || '';
    filterSheet.addEventListener('app-v2-filter-change', function (event) {
      filterStates[hostId] = event.detail.state || {};
      if (applyDesktopFilter(screen, meta, host, filterStates[hostId], function () {
        records = sourceRecords(screen, meta);
      })) return;
      var nextRecords = renderFilteredRecords(recordList, records, filterStates[hostId]);
      updateListSummary(listSummary, listMeta, nextRecords.length);
      replaceChildren(paginationHost, [AppEndState()]);
    });
    append(filters, [filterSheet]);

    var actions = AppSection({ title: 'Thao tÃ¡c', meta: 'Touch target 44px' });
    var actionRow = el('div', 'app-v2-action-row');
    var primaryAction = meta.primaryAction || {};
    var actionButton = AppButton({ label: primaryAction.label || 'ThÃªm má»›i', icon: primaryAction.icon || 'fa-plus', action: primaryAction.action || null });
    if (primaryAction.proxy) actionButton.setAttribute('data-app-v2-proxy-click', primaryAction.proxy);
    actionRow.appendChild(actionButton);
    (meta.nav || []).forEach(function (item) {
      actionRow.appendChild(AppButton({ label: item.label, icon: item.icon, action: item.action }));
    });
    append(actions, [actionRow]);

    append(primary, summary ? [summary, filters, listSummary, list, paginationHost, AppBackToTop()] : [filters, listSummary, list, paginationHost, AppBackToTop()]);
    append(layout, [primary, secondary]);
    append(host, [
      AppHeader({
        eyebrow: meta.eyebrow,
        title: meta.title,
        subtitle: meta.subtitle,
        icon: meta.icon,
        iconLabel: meta.title
      }),
      AppToolbar((meta.nav || []).concat((meta.scopes || []).map(function (scope) {
        return { label: scope.label, icon: scope.icon || meta.icon };
      })).concat([{ label: 'LÃ m má»›i', icon: 'fa-rotate-right' }])),
      layout
    ]);
    if (screen.id === 'gisScreen') {
      host.appendChild(AppMapToolbar([
        { label: 'LÃ m má»›i', icon: 'fa-rotate-right', proxy: '#gisRefreshBtn' },
        { label: 'GPS', icon: 'fa-location-crosshairs', proxy: '#gisCurrentLocationBtn' },
        { label: 'LÆ°u ranh giá»›i', icon: 'fa-floppy-disk', proxy: '#gisSaveBtn' },
        { label: 'HoÃ n tÃ¡c Ä‘iá»ƒm', icon: 'fa-rotate-left', proxy: '#gisUndoPointBtn' },
        { label: 'Váº½ láº¡i', icon: 'fa-rotate', proxy: '#gisRedoDrawBtn' },
        { label: 'Há»§y váº½', icon: 'fa-ban', proxy: '#gisCancelDrawBtn' },
        { label: 'XÃ³a khu vá»±c', icon: 'fa-trash', proxy: '#gisDeleteAreaBtn' },
        { label: 'Váº½ ranh giá»›i', icon: 'fa-draw-polygon', proxy: '#gisDrawBtn' },
        { label: 'Xuáº¥t PDF', icon: 'fa-file-pdf', proxy: '#gisPdfBtn' }
      ]));
      if (false) host.appendChild(AppBottomSheet({
        label: 'ThÃ´ng tin báº£n Ä‘á»“ GIS',
        title: 'Báº£n Ä‘á»“ GIS',
        body: AppList([
          { title: 'Leaflet váº«n giá»¯ nguyÃªn', subtitle: 'Map, marker, polygon, GPS vÃ  API dÃ¹ng runtime hiá»‡n táº¡i.', icon: 'fa-map-location-dot' },
          { title: 'CÃ´ng cá»¥ báº£n Ä‘á»“', subtitle: 'Toolbar má»›i proxy vá» cÃ¡c nÃºt nghiá»‡p vá»¥ cÅ©.', icon: 'fa-screwdriver-wrench' }
        ])
      }));
    }
  }

  function activeScreen() {
    return document.querySelector('.screen.active');
  }

  function isCreatePrimaryAction(action) {
    if (!action) return false;
    var textValue = [action.label, action.icon, action.proxy, action.action].join(' ').toLowerCase();
    return /fa-plus|user-plus|create|opencreate|\\.add|add|thÃªm|them|táº¡o|tao/.test(textValue);
  }

  function ensurePrimaryProxy(screen, action) {
    if (!screen || !action || !action.proxy || document.querySelector(action.proxy)) return;
    var fallback = null;
    if (screen.id === 'vehiclesScreen' && typeof window.openVehicleForm === 'function') {
      fallback = { id: 'vehicleAddBtn', handler: window.openVehicleForm };
    } else if (screen.id === 'contributionsScreen' && typeof window.openContributionCampaign === 'function') {
      fallback = { id: 'contributionCreateCampaignBtn', handler: function () { window.openContributionCampaign(); } };
    }
    if (!fallback) return;
    var button = el('button', 'd-none', {
      id: fallback.id,
      type: 'button',
      'aria-hidden': 'true',
      tabindex: '-1'
    });
    button.addEventListener('click', fallback.handler);
    screen.insertBefore(button, screen.firstChild);
  }

  function syncGlobalFab() {
    var fab = document.querySelector('[data-app-v2-global-fab]');
    var screen = activeScreen();
    var meta = screen && moduleScreenMeta(screen);
    var primaryAction = meta && meta.primaryAction;
    if (!document.body.classList.contains('app-authenticated') || !screen || !isCreatePrimaryAction(primaryAction)) {
      if (fab) fab.remove();
      return;
    }
    ensurePrimaryProxy(screen, primaryAction);
    if (!fab) {
      fab = AppFab(primaryAction);
      fab.setAttribute('data-app-v2-global-fab', 'true');
      document.body.appendChild(fab);
    }
    fab.className = 'app-v2-fab app-v2-global-fab';
    fab.setAttribute('aria-label', primaryAction.label || 'Add new');
    fab.setAttribute('title', primaryAction.label || 'Add new');
    fab.removeAttribute('data-screen');
    fab.removeAttribute('data-app-v2-proxy-click');
    if (primaryAction.action) fab.setAttribute('data-screen', primaryAction.action);
    if (primaryAction.proxy) fab.setAttribute('data-app-v2-proxy-click', primaryAction.proxy);
    var iconNode = fab.querySelector('i');
    if (iconNode) iconNode.className = 'fa-solid ' + (primaryAction.icon || 'fa-plus');
    var label = fab.querySelector('span');
    if (label) label.textContent = primaryAction.label || 'Add new';
  }

  function renderDashboard() {
    var screen = document.getElementById('dashboardScreen');
    if (!screen) return;
    var host = document.getElementById('appMobileDashboard');
    if (!host) {
      host = el('section', 'app-v2-page app-v2-dashboard', { id: 'appMobileDashboard', 'aria-label': 'Dashboard Mobile' });
      screen.insertBefore(host, screen.firstChild);
    }
    var hostId = 'appMobileDashboard';
    var filterState = filterStates[hostId] || {};
    var data = dashboardData();
    var filteredStats = filterItems(data.stats, filterState);
    var filteredCharts = filterItems(data.charts || [], filterState);
    var filteredQuickActions = filterItems(data.quickActions || [], filterState);
    var filteredHealth = filterItems(data.health || [], filterState);
    var filteredAlerts = filterItems(data.alerts || [], filterState);
    var filteredTasks = filterItems(data.tasks || [], filterState);
    host.textContent = '';
    append(host, [
      AppHeader({
        eyebrow: window.AppSettings?.unitName || 'ÄÆ¡n vá»‹ hÃ nh chÃ­nh',
        title: 'Dashboard',
        subtitle: data.generatedAt,
        icon: 'fa-bell',
        iconLabel: 'ThÃ´ng bÃ¡o'
      })
    ]);

    var toolbar = AppToolbar([
      { label: 'HÃ´m nay', icon: 'fa-calendar-day' },
      { label: 'Cáº£nh bÃ¡o', icon: 'fa-bell', action: 'dashboard' },
      { label: 'GIS', icon: 'fa-map-location-dot', action: 'gis' },
      { label: 'BÃ¡o cÃ¡o', icon: 'fa-chart-pie', action: 'reports' }
    ]);
    host.appendChild(toolbar);
    var dashboardFilterBar = AppFilterBar({
      label: 'TÃ¬m kiáº¿m Dashboard',
      fields: [{ label: 'Tá»« khÃ³a', type: 'search', placeholder: 'TÃ¬m há»™, nhÃ¢n kháº©u, CCCD, Ä‘á»‹a chá»‰...' }]
    });

    var statSection = AppSection({ title: 'Chá»‰ sá»‘ nhanh', meta: 'Mobile UI v2' });
    var statGrid = el('div', 'app-v2-grid app-v2-dashboard-kpis');
    var dashboardSearchControl = dashboardFilterBar.querySelector('[data-app-v2-filter-field="search"]');
    if (dashboardSearchControl) dashboardSearchControl.value = filterState.search || '';
    dashboardFilterBar.addEventListener('app-v2-filter-change', function (event) {
      filterStates[hostId] = event.detail.state || {};
      var nextState = filterStates[hostId];
      renderFilteredStats(statSection, data.stats, nextState, 'KhÃ´ng tÃ¬m tháº¥y chá»‰ sá»‘ phÃ¹ há»£p');
      if (chartSection) {
        var nextChartCards = filterItems(data.charts || [], nextState).map(function (item) {
          return AppDashboardChart(item);
        }).filter(Boolean);
        var nextChartGrid = el('div', 'app-v2-grid app-v2-dashboard-charts');
        nextChartCards.forEach(function (card) { nextChartGrid.appendChild(card); });
        setSectionBody(chartSection, nextChartCards.length ? nextChartGrid : AppEmptyState({ message: 'KhÃ´ng tÃ¬m tháº¥y biá»ƒu Ä‘á»“ phÃ¹ há»£p', icon: 'fa-magnifying-glass' }));
      }
      var nextActions = filterItems(data.quickActions || [], nextState);
      replaceChildren(actionList, nextActions.length ? nextActions.map(function (item) { return listItem(item); }) : [AppEmptyState({ message: 'KhÃ´ng tÃ¬m tháº¥y thao tÃ¡c phÃ¹ há»£p', icon: 'fa-magnifying-glass' })]);
      renderFilteredSummaryList(summaryList, data.health || [], nextState);
      var nextAlerts = filterItems(data.alerts || [], nextState);
      var nextTasks = filterItems(data.tasks || [], nextState);
      setSectionBody(alertSection, AppList((nextAlerts.length ? nextAlerts : [{ title: 'KhÃ´ng cÃ³ cáº£nh bÃ¡o ná»•i báº­t', subtitle: 'Dashboard Ä‘ang á»•n Ä‘á»‹nh', icon: 'fa-circle-check', action: 'dashboard' }]).concat(nextTasks)));
    });
    filteredStats.forEach(function (item) { statGrid.appendChild(AppStatCard(item)); });
    append(statSection, [filteredStats.length ? statGrid : AppEmptyState({ message: 'KhÃ´ng tÃ¬m tháº¥y chá»‰ sá»‘ phÃ¹ há»£p', icon: 'fa-magnifying-glass' })]);

    var chartCards = filteredCharts.map(function (item) {
      return AppDashboardChart(item);
    }).filter(Boolean);
    var chartSection = null;
    if (chartCards.length) {
      chartSection = AppSection({ title: 'Biá»ƒu Ä‘á»“', meta: number(chartCards.length) + ' nhÃ³m dá»¯ liá»‡u' });
      var chartGrid = el('div', 'app-v2-grid app-v2-dashboard-charts');
      chartCards.forEach(function (card) { chartGrid.appendChild(card); });
      append(chartSection, [chartGrid]);
    }

    var actionSection = AppSection({ title: 'Thao tÃ¡c nhanh', meta: '4 má»¥c chÃ­nh' });
    var actionList = el('div', 'app-v2-list');
    filteredQuickActions.forEach(function (item) { actionList.appendChild(listItem(item)); });
    if (!filteredQuickActions.length) actionList.appendChild(AppEmptyState({ message: 'KhÃ´ng tÃ¬m tháº¥y thao tÃ¡c phÃ¹ há»£p', icon: 'fa-magnifying-glass' }));
    append(actionSection, [actionList]);

    var layout = el('div', 'app-v2-dashboard-layout');
    var primary = el('div', 'app-v2-section');
    var secondary = el('div', 'app-v2-section');
    append(primary, chartSection ? [statSection, dashboardFilterBar, chartSection, actionSection] : [statSection, dashboardFilterBar, actionSection]);

    var panelSection = AppSection({ title: 'Theo dÃµi', meta: 'TÃ³m táº¯t váº­n hÃ nh' });
    var panels = el('div', 'app-v2-grid app-v2-dashboard-panels');
    append(panels, [
      AppCard({ title: 'Biáº¿n Ä‘á»™ng gáº§n Ä‘Ã¢y', body: listItem({ title: 'Theo dÃµi táº¡m trÃº, táº¡m váº¯ng', subtitle: 'Má»Ÿ module biáº¿n Ä‘á»™ng Ä‘á»ƒ xá»­ lÃ½ há»“ sÆ¡ má»›i', icon: 'fa-arrows-rotate', action: 'movements' }) }),
      AppCard({ title: 'Dá»¯ liá»‡u báº£n Ä‘á»“', body: listItem({ title: 'Tiáº¿n Ä‘á»™ Ä‘á»‹nh vá»‹ GIS', subtitle: 'Kiá»ƒm tra há»™ chÆ°a cÃ³ tá»a Ä‘á»™', icon: 'fa-location-crosshairs', action: 'gis' }) })
    ]);
    append(panelSection, [panels]);
    var summarySection = AppSection({ title: 'TÃ³m táº¯t', meta: 'Adaptive' });
    var summaryList = el('div', 'app-v2-list');
    filteredHealth.forEach(function (item) { summaryList.appendChild(AppSummaryCard(item)); });
    if (!filteredHealth.length) summaryList.appendChild(AppEmptyState({ message: 'KhÃ´ng tÃ¬m tháº¥y tÃ³m táº¯t phÃ¹ há»£p', icon: 'fa-magnifying-glass' }));
    append(summarySection, [summaryList]);
    var alertSection = AppSection({ title: 'Cáº£nh bÃ¡o vÃ  tÃ¡c vá»¥', meta: filteredAlerts.length + filteredTasks.length ? 'Dá»¯ liá»‡u tháº­t' : 'Fallback' });
    append(alertSection, [AppList((filteredAlerts.length ? filteredAlerts : [{ title: 'KhÃ´ng cÃ³ cáº£nh bÃ¡o ná»•i báº­t', subtitle: 'Dashboard Ä‘ang á»•n Ä‘á»‹nh', icon: 'fa-circle-check', action: 'dashboard' }]).concat(filteredTasks))]);
    append(secondary, [summarySection, alertSection, panelSection]);
    append(layout, [primary, secondary]);
    append(host, [layout]);
  }

  function isMobileOrTablet() {
    return !mobileQuery || mobileQuery.matches;
  }

  function enhance() {
    document.documentElement.classList.toggle('app-v2-adaptive', isMobileOrTablet());
    renderDashboard();
    if (moduleShellEnabled) {
      document.querySelectorAll('.module-dashboard-screen[data-module-dashboard]').forEach(function (screen) {
        if (enabledModuleDashboards.indexOf(screen.getAttribute('data-module-dashboard')) >= 0) renderModuleDashboard(screen);
      });
      enabledModuleScreens.forEach(function (id) {
        var screen = document.getElementById(id);
        if (screen) renderModuleScreen(screen);
      });
    }
    syncGlobalFab();
  }

  function schedule() {
    if (scheduled) return;
    scheduled = true;
    window.requestAnimationFrame(function () {
      scheduled = false;
      enhance();
    });
  }

  function syncAfterDataRequests() {
    if (typeof window.request !== 'function' || window.request.__appV2Synced) return;
    var baseRequest = window.request;
    var syncedRequest = function () {
      var result = baseRequest.apply(this, arguments);
      if (result && typeof result.finally === 'function') {
        return result.finally(function () { schedule(); });
      }
      schedule();
      return result;
    };
    syncedRequest.__appV2Synced = true;
    window.request = syncedRequest;
  }

  function scheduleDataSync() {
    schedule();
    window.setTimeout(schedule, 80);
    window.setTimeout(schedule, 240);
  }

  function dispatchProxy(selector) {
    var proxyTarget = document.querySelector(selector);
    if (proxyTarget && typeof proxyTarget.dispatchEvent === 'function') {
      proxyTarget.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
      return true;
    }
    return false;
  }

  document.addEventListener('click', function (event) {
    var target = event.target.closest('.app-v2-button[data-screen], .app-v2-icon-button[data-screen], .app-v2-chip[data-screen], .app-v2-tab[data-screen], .app-v2-bottom-nav button[data-screen]');
    var proxy = event.target.closest('[data-app-v2-proxy-click]');
    var primary = event.target.closest('[data-app-v2-primary-proxy]');
    var backToTop = event.target.closest('[data-app-v2-back-to-top]');
    if (backToTop) {
      var page = backToTop.closest('.app-v2-page');
      if (page && typeof page.scrollIntoView === 'function') page.scrollIntoView({ behavior: 'smooth', block: 'start' });
      else window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }
    if (proxy) {
      if (dispatchProxy(proxy.getAttribute('data-app-v2-proxy-click'))) return;
    }
    if (primary && !event.target.closest('button, a, input, select, textarea, summary, details')) {
      if (dispatchProxy(primary.getAttribute('data-app-v2-primary-proxy'))) return;
    }
    if (!target) return;
    var screen = target.getAttribute('data-screen');
    if (!screen) return;
    if (window.TenantAppNavigationController && typeof window.TenantAppNavigationController.navigate === 'function') {
      window.TenantAppNavigationController.navigate(screen);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    var primary = event.target.closest('[data-app-v2-primary-proxy]');
    if (!primary) return;
    if (event.target.closest('button, a, input, select, textarea, summary, details')) return;
    if (dispatchProxy(primary.getAttribute('data-app-v2-primary-proxy'))) event.preventDefault();
  });

  if (mobileQuery && mobileQuery.addEventListener) mobileQuery.addEventListener('change', schedule);
  else window.addEventListener('resize', schedule, { passive: true });

  syncAfterDataRequests();
  document.addEventListener('tenant:screen-change', scheduleDataSync);
  document.addEventListener('tenant:module-state-change', schedule);
  document.addEventListener('tenant:app-state-change', schedule);

  if (window.MutationObserver) {
    new MutationObserver(function (mutations) {
      var shouldRender = mutations.some(function (mutation) {
        if (mutation.target && mutation.target.closest && mutation.target.closest('.app-v2-page')) return false;
        if (mutation.target && mutation.target.id === 'dashboardKpis') return true;
        if (mutation.target && mutation.target.matches && mutation.target.matches('tbody, [id$="Rows"], [id$="Grid"]')) return true;
        return Array.from(mutation.addedNodes || []).some(function (node) {
          if (!node || node.nodeType !== 1) return false;
          if (node.closest && node.closest('.app-v2-page')) return false;
          if (node.matches && node.matches('.screen, .module-dashboard-screen, tbody, tbody tr, tr, [id$="Rows"], [id$="Grid"]')) return true;
          return node.querySelector && Boolean(node.querySelector('.screen, .module-dashboard-screen, tbody tr, [id$="Rows"], [id$="Grid"]'));
        });
      });
      if (shouldRender) schedule();
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', schedule);
  else schedule();

  window.TenantAppMobileComponents = {
    AppButton: AppButton,
    AppBadge: AppBadge,
    AppStatusChip: AppStatusChip,
    AppBottomSheet: AppBottomSheet,
    AppBottomNavigation: AppBottomNavigation,
    AppCard: AppCard,
    AppDrawer: AppDrawer,
    AppEmptyState: AppEmptyState,
    AppFilterBar: AppFilterBar,
    AppFilterSheet: AppFilterSheet,
    AppHeader: AppHeader,
    AppIconButton: AppIconButton,
    AppInput: AppInput,
    AppList: AppList,
    AppListSummary: AppListSummary,
    AppLoading: AppLoading,
    AppMapToolbar: AppMapToolbar,
    AppMetricRow: AppMetricRow,
    AppModal: AppModal,
    AppRecordCard: AppRecordCard,
    AppSection: AppSection,
    AppSelect: AppSelect,
    AppStatCard: AppStatCard,
    AppSummaryCard: AppSummaryCard,
    AppTabs: AppTabs,
    AppToolbar: AppToolbar,
    enableModuleShells: function () {
      moduleShellEnabled = true;
      schedule();
    },
    disableModuleShells: function () {
      moduleShellEnabled = false;
      schedule();
    },
    setModuleShellScope: function (ids) {
      enabledModuleScreens = Array.isArray(ids) ? ids.slice() : enabledModuleScreens;
      moduleShellEnabled = true;
      schedule();
    },
    setModuleDashboardScope: function (keys) {
      enabledModuleDashboards = Array.isArray(keys) ? keys.slice() : enabledModuleDashboards;
      moduleShellEnabled = true;
      schedule();
    },
    renderModuleDashboard: renderModuleDashboard,
    renderModuleScreen: renderModuleScreen,
    renderDashboard: renderDashboard,
    schedule: schedule
  };
})(window, document);
