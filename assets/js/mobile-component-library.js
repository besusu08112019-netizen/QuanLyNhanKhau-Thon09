(function (window, document) {
  'use strict';

  var mobileQuery = window.matchMedia ? window.matchMedia('(max-width: 1024px)') : null;
  var scheduled = false;
  var moduleShellEnabled = true;
  var enabledModuleScreens = [
    'householdsScreen', 'personsScreen', 'temporaryResidenceScreen', 'temporaryAbsenceScreen', 'movementsScreen',
    'gisScreen', 'publicAssetsScreen', 'housesScreen', 'vehiclesScreen',
    'businessHouseholdsScreen', 'contributionsScreen', 'agricultureScreen', 'livestockScreen', 'reportsScreen',
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
      'aria-label': options.label || options.title || 'Thao tác',
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
    button.lastChild.textContent = options.label || 'Mở';
    return button;
  }

  function AppBadge(options) {
    var badge = el('span', 'app-v2-badge', { 'data-tone': options.tone || 'primary' });
    badge.textContent = options.label || '';
    return badge;
  }

  function AppStatusChip(options) {
    return AppBadge({
      label: options && options.label ? options.label : 'Trạng thái',
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
      autocomplete: options.autocomplete || 'off'
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
    var select = el('select', 'app-v2-select', { id: id });
    label.textContent = options.label || '';
    (options.options || []).forEach(function (item) {
      var option = el('option');
      option.value = item.value == null ? item.label : item.value;
      option.textContent = item.label || item.value || '';
      select.appendChild(option);
    });
    append(field, [label, select]);
    return field;
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
    var search = el('label', 'app-v2-search', { 'aria-label': field.label || 'Tìm kiếm' });
    var input = el('input', '', {
      type: 'search',
      placeholder: field.placeholder || '',
      autocomplete: 'off',
      inputmode: 'search'
    });
    append(search, [icon(field.icon || 'fa-magnifying-glass'), input]);
    return search;
  }

  function AppFilterBar(options) {
    var sheet = el('section', 'app-v2-filter-sheet app-v2-filter-bar', { 'aria-label': options.label || 'Bộ lọc' });
    (options.fields || []).forEach(function (field) {
      sheet.appendChild(field.type === 'select' ? AppSelect(field) : (field.type === 'search' ? AppSearchControl(field) : AppInput(field)));
    });
    if (options.actions && options.actions.length) sheet.appendChild(AppToolbar(options.actions));
    return sheet;
  }

  function AppFilterSheet(options) {
    return AppFilterBar(options);
  }

  function AppMapToolbar(items) {
    var toolbar = el('div', 'app-v2-map-toolbar', { role: 'toolbar', 'aria-label': 'Công cụ bản đồ' });
    (items || []).forEach(function (item) {
      var button = AppIconButton({ icon: item.icon || 'fa-circle', label: item.label || 'Công cụ', action: item.action || null });
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
    var header = el('section', 'app-v2-hero', { 'aria-label': options.aria || options.title || 'Tiêu đề' });
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
      AppIconButton({ icon: options.icon || 'fa-bell', label: options.iconLabel || 'Thông báo' })
    ]);
    append(header, [row]);
    return header;
  }

  function AppSection(options) {
    var section = el('section', 'app-v2-section', { 'aria-label': options.title || 'Khu vực' });
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

  function AppEmptyState(options) {
    var empty = el('div', 'app-v2-empty');
    append(empty, [icon(options.icon || 'fa-inbox'), text(options.message || 'Chưa có dữ liệu')]);
    return empty;
  }

  function AppLoading(options) {
    var loading = el('div', 'app-v2-loading', { role: 'status', 'aria-live': 'polite' });
    append(loading, [el('span', 'app-v2-spinner'), text(options && options.message ? options.message : 'Đang tải dữ liệu')]);
    return loading;
  }

  function AppFAB(options) {
    var button = el('button', 'app-v2-fab', {
      type: 'button',
      'aria-label': options.label || 'Thao tác nhanh',
      title: options.label || ''
    });
    if (options.action) button.setAttribute('data-screen', options.action);
    append(button, [icon(options.icon || 'fa-plus')]);
    return button;
  }

  function AppBottomNavigation(items, current) {
    var nav = el('nav', 'app-v2-bottom-nav', { 'aria-label': 'Điều hướng Mobile v2' });
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
    append(panel, [options.body || AppEmptyState({ message: 'Chưa có nội dung menu' })]);
    append(drawer, [panel]);
    return drawer;
  }

  function AppModal(options) {
    var modal = el('section', 'app-v2-modal', {
      role: 'dialog',
      'aria-modal': 'true',
      'aria-label': options.title || 'Hộp thoại',
      'data-open': options.open ? 'true' : 'false'
    });
    var panel = el('div', 'app-v2-modal-panel');
    var header = AppSection({ title: options.title || 'Chi tiết', meta: options.meta || '' });
    var body = el('div', 'app-v2-card');
    var footer = el('div', 'app-v2-toolbar');
    append(body, [options.body || AppEmptyState({ message: 'Chưa có nội dung' })]);
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
    if (options.action) append(item, [AppIconButton({ icon: 'fa-arrow-right', label: options.actionLabel || 'Mở', action: options.action })]);
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
    title.textContent = options.title || 'Bản ghi';
    meta.textContent = options.meta || 'Đang cập nhật';
    append(iconWrap, [icon(options.icon || 'fa-file-lines')]);
    append(textWrap, [title]);
    if (summaryFields.length) {
      var summary = el('div', 'app-v2-record-summary');
      summaryFields.forEach(function (field) {
        var chip = el('span', 'app-v2-record-summary-chip');
        chip.textContent = field.label ? field.label + ': ' + field.value : field.value;
        summary.appendChild(chip);
      });
      textWrap.appendChild(summary);
    } else {
      textWrap.appendChild(meta);
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
        summary.textContent = 'Thêm thông tin';
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
      append(card, [AppIconButton({ icon: 'fa-ellipsis', label: 'Thao tác', action: options.action })]);
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
      return { label: item.label || 'Khác', value: Number(item.value || 0) };
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
      generatedAt: generatedAt || 'Đang cập nhật dữ liệu',
      stats: [
        { label: 'Hộ gia đình', value: number(totalHouseholds), meta: 'Tổng hộ đang quản lý', icon: 'fa-house-chimney' },
        { label: 'Nhân khẩu', value: number(totalCitizens), meta: 'Tổng nhân khẩu hiện có', icon: 'fa-users' },
        { label: 'Nam', value: number(male), meta: percent(male, totalCitizens) + ' tổng nhân khẩu', icon: 'fa-mars' },
        { label: 'Nữ', value: number(female), meta: percent(female, totalCitizens) + ' tổng nhân khẩu', icon: 'fa-venus' },
        { label: 'Đảng viên', value: number(partyMembers), meta: 'Theo hồ sơ nhân khẩu', icon: 'fa-star' },
        { label: 'Trẻ em', value: number(children), meta: percent(children, totalCitizens) + ' tổng nhân khẩu', icon: 'fa-child-reaching' },
        { label: 'Người cao tuổi', value: number(elderly), meta: percent(elderly, totalCitizens) + ' tổng nhân khẩu', icon: 'fa-person-cane' },
        { label: 'Lao động', value: number(workingAge), meta: percent(workingAge, totalCitizens) + ' tổng nhân khẩu', icon: 'fa-briefcase' },
        { label: 'BHYT', value: number(insured) + ' / ' + number(totalCitizens), meta: percent(insured, totalCitizens) + ' đã tham gia', icon: 'fa-notes-medical' },
        { label: 'Tạm trú', value: number(temporary), meta: 'Nhân khẩu tạm trú', icon: 'fa-location-dot' },
        { label: 'Tạm vắng', value: number(away), meta: 'Nhân khẩu tạm vắng', icon: 'fa-person-walking-arrow-right' },
        { label: 'Hộ nghèo', value: number(poor), meta: 'Theo phân loại hộ', icon: 'fa-hand-holding-heart' },
        { label: 'Hộ cận nghèo', value: number(nearPoor), meta: 'Theo phân loại hộ', icon: 'fa-hands-holding' }
      ],
      charts: [
        { title: 'Cơ cấu Nam / Nữ', items: normalizeChartItems(charts.population).length ? charts.population : [{ label: 'Nam', value: male }, { label: 'Nữ', value: female }] },
        { title: 'Cơ cấu độ tuổi', items: charts.ages || [] },
        { title: 'Bảo hiểm y tế', items: [{ label: 'Có BHYT', value: insured }, { label: 'Chưa có BHYT', value: Math.max(totalCitizens - insured, 0) }] },
        { title: 'Tạm trú / Tạm vắng', items: [{ label: 'Tạm trú', value: temporary }, { label: 'Tạm vắng', value: away }] },
        { title: 'Biến động', items: charts.monthlyChanges || [] },
        { title: 'Hộ nghèo / Cận nghèo', items: normalizeChartItems(charts.poverty).length ? charts.poverty : [{ label: 'Hộ nghèo', value: poor }, { label: 'Hộ cận nghèo', value: nearPoor }] },
        { title: 'Lao động', items: charts.labor || charts.occupations || [] },
        { title: 'Hộ gia đình', items: charts.households || [] }
      ],
      quickActions: [
        { title: 'Hộ gia đình', subtitle: 'Tra cứu và cập nhật hồ sơ hộ', icon: 'fa-house-user', action: 'households' },
        { title: 'Nhân khẩu', subtitle: 'Quản lý thông tin công dân', icon: 'fa-id-card', action: 'persons' },
        { title: 'GIS', subtitle: 'Mở bản đồ và vị trí hộ', icon: 'fa-map-location-dot', action: 'gis' },
        { title: 'Báo cáo', subtitle: 'Tổng hợp và xuất dữ liệu', icon: 'fa-chart-pie', action: 'reports' }
      ],
      health: [
        { label: 'Hồ sơ số', value: number((profiles.citizenComplete && profiles.citizenComplete.done) || metricValue(metrics, 'digital_profiles') || metricValue(metrics, 'profiles')), note: 'Theo dõi hoàn thiện dữ liệu', icon: 'fa-folder-open' },
        { label: 'Định vị GIS', value: number(gis.locatedHouseholds || metricValue(metrics, 'located_households') || metricValue(metrics, 'gis_located')), note: 'Hộ đã có tọa độ', icon: 'fa-location-dot' }
      ],
      alerts: alerts.map(function (item) {
        return {
          title: item.label || 'Cảnh báo',
          subtitle: (item.count != null ? number(item.count) + ' hồ sơ' : 'Cần kiểm tra'),
          icon: item.priority === 'high' ? 'fa-triangle-exclamation' : 'fa-bell',
          action: item.screen || 'dashboard'
        };
      }),
      tasks: tasks.map(function (item) {
        return {
          title: item.label || 'Tác vụ',
          subtitle: item.action || (item.count != null ? number(item.count) + ' việc' : 'Mở để xử lý'),
          icon: 'fa-list-check',
          action: item.screen || 'dashboard'
        };
      })
    };
  }

  var MODULE_DASHBOARD_META = {
    dashboardHouseholds: {
      title: 'Hộ gia đình',
      icon: 'fa-house-user',
      subtitle: 'Theo dõi hồ sơ hộ, GPS và hồ sơ số',
      actions: [
        { label: 'Danh sách hộ', icon: 'fa-list', action: 'households' },
        { label: 'GIS', icon: 'fa-map-location-dot', action: 'gis' }
      ]
    },
    dashboardPopulation: {
      title: 'Nhân khẩu',
      icon: 'fa-users',
      subtitle: 'Theo dõi nhân khẩu, độ tuổi và cư trú',
      actions: [
        { label: 'Nhân khẩu', icon: 'fa-id-card', action: 'persons' },
        { label: 'Biến động', icon: 'fa-arrows-rotate', action: 'movements' }
      ]
    },
    dashboardBusiness: {
      title: 'Kinh doanh',
      icon: 'fa-store',
      subtitle: 'Tổng quan cơ sở, ngành nghề và quy mô hoạt động',
      actions: [
        { label: 'Cơ sở', icon: 'fa-store', action: 'businessHouseholds' },
        { label: 'Báo cáo', icon: 'fa-chart-pie', action: 'reports' }
      ]
    },
    dashboardVehicles: {
      title: 'Xe cộ',
      icon: 'fa-car',
      subtitle: 'Theo dõi phương tiện theo hộ và khu vực',
      actions: [
        { label: 'Xe cộ', icon: 'fa-car', action: 'vehicles' },
        { label: 'GIS', icon: 'fa-map-location-dot', action: 'gis' }
      ]
    },
    dashboardLivestock: {
      title: 'Vật nuôi',
      icon: 'fa-paw',
      subtitle: 'Theo dõi đàn vật nuôi, tiêm phòng và quy mô hộ',
      actions: [
        { label: 'Vật nuôi', icon: 'fa-paw', action: 'livestock' },
        { label: 'Nông nghiệp', icon: 'fa-seedling', action: 'agriculture' }
      ]
    },
    dashboardGis: {
      title: 'GIS',
      icon: 'fa-map-location-dot',
      subtitle: 'Tiến độ định vị, lớp bản đồ và marker',
      actions: [
        { label: 'Mở bản đồ', icon: 'fa-map', action: 'gis' },
        { label: 'Hộ dân', icon: 'fa-house', action: 'households' }
      ]
    },
    dashboardReports: {
      title: 'Báo cáo',
      icon: 'fa-chart-pie',
      subtitle: 'Theo dõi nhóm báo cáo và trạng thái xuất dữ liệu',
      actions: [
        { label: 'Báo cáo', icon: 'fa-chart-pie', action: 'reports' },
        { label: 'In ấn', icon: 'fa-print', action: 'reports' }
      ]
    }
  };

  var MODULE_SCREEN_META = {
    householdsScreen: {
      title: 'Hộ gia đình',
      eyebrow: 'Quản lý hộ',
      icon: 'fa-house-user',
      subtitle: 'Tra cứu, cập nhật hồ sơ hộ và định vị GIS',
      search: 'Tìm mã hộ, chủ hộ, địa chỉ...',
      titleLabels: ['Chủ hộ', 'Tên chủ hộ', 'Chu ho', 'Ten chu ho'],
      summaryLabels: ['Mã hộ', 'Ở nhà', 'Đi vắng', 'Trạng thái', 'Loại hộ', 'Ma ho', 'O nha', 'Di vang', 'Trang thai', 'Loai ho'],
      metaLabels: ['Mã hộ', 'Địa chỉ', 'Ở nhà', 'Đi vắng', 'Ma ho', 'Dia chi', 'O nha', 'Di vang'],
      primaryAction: { label: 'Thêm hộ', icon: 'fa-plus', proxy: '#addHouseholdBtn, [data-platform-action="households.create"]' },
      nav: [{ label: 'Dashboard', icon: 'fa-chart-simple', action: 'dashboardHouseholds' }, { label: 'GIS', icon: 'fa-map-location-dot', action: 'gis' }]
    },
    personsScreen: {
      title: 'Nhân khẩu',
      eyebrow: 'Quản lý công dân',
      icon: 'fa-id-card',
      subtitle: 'Hồ sơ nhân khẩu, cư trú và biến động',
      search: 'Tìm họ tên, CCCD, mã hộ...',
      titleLabels: ['Họ và tên', 'Họ tên', 'Ho va ten', 'Ho ten'],
      summaryLabels: ['Chủ hộ', 'Tên chủ hộ', 'Mã hộ', 'Quan hệ', 'Giới tính', 'Tuổi', 'Cư trú', 'Chu ho', 'Ten chu ho', 'Ma ho', 'Quan he', 'Gioi tinh', 'Tuoi', 'Cu tru'],
      metaLabels: ['Chủ hộ', 'Tên chủ hộ', 'Mã hộ', 'Quan hệ', 'Tuổi', 'Giới tính', 'Cư trú', 'Chu ho', 'Ten chu ho', 'Ma ho', 'Quan he', 'Tuoi', 'Gioi tinh', 'Cu tru'],
      primaryAction: { label: 'Thêm nhân khẩu', icon: 'fa-plus', proxy: '#addPersonBtn, [data-platform-action="persons.create"]' },
      nav: [{ label: 'Dashboard', icon: 'fa-chart-simple', action: 'dashboardPopulation' }, { label: 'Biến động', icon: 'fa-arrows-rotate', action: 'movements' }],
      scopes: [
        { key: 'temporaryResidence', label: 'Tạm trú', icon: 'fa-location-dot', match: 'Tạm trú', tone: 'warning' },
        { key: 'temporaryAbsence', label: 'Tạm vắng', icon: 'fa-person-walking-arrow-right', match: 'Tạm vắng', tone: 'danger' }
      ]
    },
    gisScreen: {
      title: 'GIS',
      eyebrow: 'Bản đồ số',
      icon: 'fa-map-location-dot',
      subtitle: 'Bản đồ hộ dân, marker và lớp dữ liệu',
      search: 'Tìm hộ, địa chỉ, khu vực...',
      primaryAction: { label: 'Mở bản đồ', icon: 'fa-map', action: 'gis' },
      nav: [{ label: 'Hộ dân', icon: 'fa-house', action: 'households' }, { label: 'Báo cáo', icon: 'fa-chart-pie', action: 'reports' }]
    },
    businessHouseholdsScreen: {
      title: 'Kinh doanh',
      eyebrow: 'Hộ kinh doanh',
      icon: 'fa-store',
      subtitle: 'Cơ sở, ngành nghề và trạng thái hoạt động',
      search: 'Tìm cơ sở, chủ hộ, ngành nghề...',
      primaryAction: { label: 'Thêm cơ sở', icon: 'fa-plus', proxy: '#businessAddBtn, [data-platform-action="business.create"]' },
      nav: [{ label: 'Dashboard', icon: 'fa-chart-simple', action: 'dashboardBusiness' }, { label: 'Báo cáo', icon: 'fa-chart-pie', action: 'reports' }]
    },
    vehiclesScreen: {
      title: 'Xe cộ',
      eyebrow: 'Phương tiện',
      icon: 'fa-car',
      subtitle: 'Quản lý phương tiện theo hộ dân',
      search: 'Tìm biển số, chủ xe, loại xe...',
      primaryAction: { label: 'Thêm xe', icon: 'fa-plus', proxy: '#vehicleAddBtn, [data-platform-action="vehicles.create"]' },
      nav: [{ label: 'Dashboard', icon: 'fa-chart-simple', action: 'dashboardVehicles' }]
    },
    contributionsScreen: {
      title: 'Đóng góp',
      eyebrow: 'Thu đóng góp',
      icon: 'fa-hand-holding-dollar',
      subtitle: 'Theo dõi khoản thu và tiến độ đóng góp',
      search: 'Tìm hộ, khoản thu, trạng thái...',
      primaryAction: { label: 'Thêm khoản thu', icon: 'fa-plus', proxy: '#contributionAddBtn, [data-platform-action="contributions.create"]' },
      nav: [{ label: 'Báo cáo', icon: 'fa-chart-pie', action: 'reports' }]
    },
    agricultureScreen: {
      title: 'Nông nghiệp',
      eyebrow: 'Sản xuất',
      icon: 'fa-seedling',
      subtitle: 'Thửa đất, cây trồng và sản xuất nông nghiệp',
      search: 'Tìm thửa, chủ sử dụng, cây trồng...',
      primaryAction: { label: 'Thêm thửa', icon: 'fa-plus', proxy: '#agriAddBtn, [data-platform-action="agriculture.create"]' },
      nav: [{ label: 'Vật nuôi', icon: 'fa-paw', action: 'livestock' }]
    },
    housesScreen: {
      title: 'Nhà ở',
      eyebrow: 'Công trình hộ',
      icon: 'fa-house-chimney-window',
      subtitle: 'Nhà ở, công trình phụ và tình trạng sử dụng',
      search: 'Tìm nhà, hộ gia đình, địa chỉ...',
      primaryAction: { label: 'Thêm nhà', icon: 'fa-plus', proxy: '#housesAddBtn, [data-platform-action="houses.create"]' },
      nav: [{ label: 'Hộ dân', icon: 'fa-house-user', action: 'households' }]
    },
    publicAssetsScreen: {
      title: 'Công trình',
      eyebrow: 'Tài sản công',
      icon: 'fa-building-columns',
      subtitle: 'Công trình công cộng, tài sản và kiểm kê',
      search: 'Tìm mã, tên công trình, đơn vị...',
      primaryAction: { label: 'Thêm công trình', icon: 'fa-plus', proxy: '#publicAssetsAddBtn, [data-platform-action="publicAssets.create"]' },
      nav: [{ label: 'GIS', icon: 'fa-map-location-dot', action: 'gis' }]
    },
    livestockScreen: {
      title: 'Vật nuôi',
      eyebrow: 'Chăn nuôi',
      icon: 'fa-paw',
      subtitle: 'Đàn vật nuôi, tiêm phòng và dịch bệnh',
      search: 'Tìm hộ, loại vật nuôi, trạng thái...',
      primaryAction: { label: 'Thêm vật nuôi', icon: 'fa-plus', proxy: '#livestockAddBtn, [data-platform-action="livestock.create"]' },
      nav: [{ label: 'Dashboard', icon: 'fa-chart-simple', action: 'dashboardLivestock' }, { label: 'Nông nghiệp', icon: 'fa-seedling', action: 'agriculture' }]
    },
    reportsScreen: {
      title: 'Báo cáo',
      eyebrow: 'Tổng hợp',
      icon: 'fa-chart-pie',
      subtitle: 'Biểu mẫu, xuất PDF/Excel và thống kê',
      search: 'Tìm báo cáo, biểu mẫu...',
      primaryAction: { label: 'Xuất báo cáo', icon: 'fa-file-export', proxy: '#reportExportBtn, [data-platform-action="reports.export"]' },
      nav: [{ label: 'Dashboard', icon: 'fa-chart-simple', action: 'dashboardReports' }]
    },
    operationCenterScreen: {
      title: 'Điều hành',
      eyebrow: 'Trung tâm',
      icon: 'fa-tower-broadcast',
      subtitle: 'Theo dõi vận hành và tác vụ cần xử lý',
      search: 'Tìm tác vụ, khu vực...',
      primaryAction: { label: 'Dashboard', icon: 'fa-chart-simple', action: 'dashboard' },
      nav: [{ label: 'Báo cáo', icon: 'fa-chart-pie', action: 'reports' }]
    },
    temporaryResidenceScreen: {
      title: 'Tam tru',
      eyebrow: 'Cu tru',
      icon: 'fa-location-dot',
      subtitle: 'Danh sach nhan khau co trang thai tam tru',
      search: 'Tim nhan khau, CCCD, ma ho...',
      primaryAction: { label: 'Nhan khau', icon: 'fa-id-card', action: 'persons' },
      nav: [{ label: 'Nhan khau', icon: 'fa-users', action: 'persons' }, { label: 'Tam vang', icon: 'fa-person-walking-arrow-right', action: 'temporaryAbsence' }]
    },
    temporaryAbsenceScreen: {
      title: 'Tam vang',
      eyebrow: 'Cu tru',
      icon: 'fa-person-walking-arrow-right',
      subtitle: 'Danh sach nhan khau dang di vang de theo doi nhanh',
      search: 'Tim nhan khau, CCCD, ma ho...',
      primaryAction: { label: 'Nhan khau', icon: 'fa-id-card', action: 'persons' },
      nav: [{ label: 'Nhan khau', icon: 'fa-users', action: 'persons' }, { label: 'Tam tru', icon: 'fa-location-dot', action: 'temporaryResidence' }]
    },
    movementsScreen: {
      title: 'Bien dong',
      eyebrow: 'Nhan khau',
      icon: 'fa-arrows-rotate',
      subtitle: 'Sinh, tu, chuyen den, chuyen di, tam tru va tam vang',
      search: 'Tim nhan khau, CCCD, ma ho, ly do...',
      primaryAction: { label: 'Them bien dong', icon: 'fa-plus', proxy: '#movementAddBtn, [data-platform-action="admin.movement.add"]' },
      nav: [{ label: 'Nhan khau', icon: 'fa-id-card', action: 'persons' }, { label: 'Bao cao', icon: 'fa-chart-pie', action: 'reports' }]
    },
    importScreen: {
      title: 'Import',
      eyebrow: 'Du lieu',
      icon: 'fa-file-import',
      subtitle: 'Nhap du lieu vao he thong theo quy trinh hien co',
      search: 'Tim tac vu import...',
      primaryAction: { label: 'Import du lieu', icon: 'fa-file-import', action: 'import' },
      nav: [{ label: 'Xuat Excel', icon: 'fa-file-export', action: 'exportExcel' }, { label: 'Bao cao', icon: 'fa-chart-pie', action: 'reports' }]
    },
    exportExcelScreen: {
      title: 'Xuat Excel',
      eyebrow: 'Du lieu',
      icon: 'fa-file-excel',
      subtitle: 'Xuat du lieu bao cao, ho dan va nhan khau ra Excel',
      search: 'Tim bao cao xuat Excel...',
      primaryAction: { label: 'Bao cao tong hop', icon: 'fa-file-excel', proxy: '[data-platform-action="admin.report.export"]' },
      nav: [{ label: 'Import', icon: 'fa-file-import', action: 'import' }, { label: 'In an', icon: 'fa-print', action: 'printForms' }]
    },
    printFormsScreen: {
      title: 'In an',
      eyebrow: 'Bieu mau',
      icon: 'fa-print',
      subtitle: 'In nhanh cac bieu mau hanh chinh kho A4',
      search: 'Tim bieu mau...',
      primaryAction: { label: 'In bao cao', icon: 'fa-print', proxy: '[data-platform-action="admin.report.print"]' },
      nav: [{ label: 'Xuat Excel', icon: 'fa-file-excel', action: 'exportExcel' }, { label: 'Bao cao', icon: 'fa-chart-pie', action: 'reports' }]
    },
    systemAdminScreen: {
      title: 'Quan tri he thong',
      eyebrow: 'Quan tri',
      icon: 'fa-screwdriver-wrench',
      subtitle: 'Theo doi suc khoe, phien dang nhap, hieu nang va cau hinh he thong',
      search: 'Tim tac vu quan tri...',
      primaryAction: { label: 'Lam moi', icon: 'fa-rotate-right', proxy: '[data-system-refresh], [data-platform-action="systemAdmin.refresh"]' },
      nav: [{ label: 'Tai khoan', icon: 'fa-user-shield', action: 'users' }, { label: 'Sao luu', icon: 'fa-database', action: 'backups' }]
    },
    usersScreen: {
      title: 'Tai khoan',
      eyebrow: 'Quan tri',
      icon: 'fa-user-shield',
      subtitle: 'Quan ly nguoi dung, vai tro va trang thai tai khoan',
      search: 'Tim email, ten hien thi, vai tro...',
      primaryAction: { label: 'Them tai khoan', icon: 'fa-user-plus', proxy: '#userAddBtn, [data-platform-action="users.create"]' },
      nav: [{ label: 'Phan quyen', icon: 'fa-key', action: 'permissions' }, { label: 'Nhat ky', icon: 'fa-clock-rotate-left', action: 'logs' }]
    },
    permissionsScreen: {
      title: 'Phan quyen',
      eyebrow: 'Quan tri',
      icon: 'fa-key',
      subtitle: 'Thiet lap quyen theo vai tro, module va thao tac',
      search: 'Tim vai tro hoac quyen...',
      primaryAction: { label: 'Luu phan quyen', icon: 'fa-floppy-disk', proxy: '#permissionSaveBtn, [data-platform-action="admin.permissions.save"]' },
      nav: [{ label: 'Tai khoan', icon: 'fa-user-shield', action: 'users' }, { label: 'Cau hinh', icon: 'fa-gear', action: 'settings' }]
    },
    logsScreen: {
      title: 'Nhat ky',
      eyebrow: 'Quan tri',
      icon: 'fa-clock-rotate-left',
      subtitle: 'Theo doi nhat ky thao tac va hoat dong he thong',
      search: 'Tim nguoi dung, hanh dong, thoi gian...',
      primaryAction: { label: 'Lam moi', icon: 'fa-rotate-right', action: 'logs' },
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
      subtitle: 'Cau hinh thong tin he thong, don vi va bao cao',
      search: 'Tim truong cau hinh...',
      primaryAction: { label: 'Luu cau hinh', icon: 'fa-floppy-disk', proxy: '#settingsForm button[type="submit"]' },
      nav: [{ label: 'Giao dien', icon: 'fa-palette', action: 'appearance' }, { label: 'Quan tri', icon: 'fa-screwdriver-wrench', action: 'systemAdmin' }]
    },
    appearanceScreen: {
      title: 'Giao dien',
      eyebrow: 'Quan tri',
      icon: 'fa-palette',
      subtitle: 'Quan ly logo, anh nen va noi dung hien thi',
      search: 'Tim cau hinh giao dien...',
      primaryAction: { label: 'Luu giao dien', icon: 'fa-floppy-disk', proxy: '#appearanceForm button[type="submit"]' },
      nav: [{ label: 'Cai dat', icon: 'fa-gear', action: 'settings' }, { label: 'Quan tri', icon: 'fa-screwdriver-wrench', action: 'systemAdmin' }]
    }
  };

  function moduleMeta(key) {
    return MODULE_DASHBOARD_META[key] || {
      title: 'Dashboard',
      icon: 'fa-chart-simple',
      subtitle: 'Tổng quan module',
      actions: [{ label: 'Báo cáo', icon: 'fa-chart-pie', action: 'reports' }]
    };
  }

  function moduleGeneratedAt(screen) {
    var node = screen.querySelector('[id$="GeneratedAt"]');
    return text(node && node.textContent) || 'Đang cập nhật dữ liệu';
  }

  function firstFaIcon(node, fallback) {
    var iconNode = node && node.querySelector('i');
    if (!iconNode) return fallback || 'fa-chart-simple';
    return Array.from(iconNode.classList).filter(function (name) {
      return name.indexOf('fa-') === 0 && name !== 'fa-solid' && name !== 'fa-regular' && name !== 'fa-brands';
    })[0] || fallback || 'fa-chart-simple';
  }

  function readKpis(screen, fallbackIcon) {
    var source = screen.querySelector('.dashboard-kpi-grid');
    var cards = source ? Array.from(source.children).slice(0, 4) : [];
    return cards.map(function (card) {
      var label = text(card.querySelector('.dashboard-kpi-label, p, span, small'));
      var value = text(card.querySelector('.dashboard-kpi-value strong, strong, b'));
      var meta = text(card.querySelector('.dashboard-kpi-value span, em, small'));
      return {
        label: label || 'Chỉ số',
        value: value || '0',
        meta: meta || 'Đang cập nhật',
        icon: firstFaIcon(card, fallbackIcon)
      };
    });
  }

  function readPanels(screen) {
    return Array.from(screen.querySelectorAll('.dashboard-chart-grid .dashboard-panel')).slice(0, 4).map(function (panel) {
      return {
        label: text(panel.querySelector('.dashboard-panel-head h3')) || 'Biểu đồ',
        value: text(panel.querySelector('.dashboard-filter-pill')) || 'Chi tiết'
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
    host.textContent = '';
    var kpis = readKpis(screen, meta.icon);
    var panels = readPanels(screen);
    if (!kpis.length) {
      kpis = [
        { label: 'Tổng quan', value: '0', meta: 'Đang cập nhật', icon: meta.icon },
        { label: 'Hồ sơ', value: '0', meta: 'Đang cập nhật', icon: 'fa-folder-open' },
        { label: 'Khu vực', value: '0', meta: 'Đang cập nhật', icon: 'fa-location-dot' },
        { label: 'Cảnh báo', value: '0', meta: 'Đang cập nhật', icon: 'fa-bell' }
      ];
    }
    if (!panels.length) {
      panels = [
        { label: 'Trạng thái dữ liệu', value: 'Sẵn sàng' },
        { label: 'Tác vụ nổi bật', value: 'Mở module để xử lý' }
      ];
    }

    var primary = el('div', 'app-v2-flow');
    var secondary = el('div', 'app-v2-flow');
    var layout = el('div', 'app-v2-two-pane');
    var statSection = AppSection({ title: 'Chỉ số', meta: 'Realtime' });
    var statGrid = el('div', 'app-v2-grid app-v2-dashboard-kpis');
    kpis.forEach(function (item) { statGrid.appendChild(AppStatCard(item)); });
    append(statSection, [statGrid]);

    var actionSection = AppSection({ title: 'Truy cập nhanh', meta: 'Module' });
    append(actionSection, [AppList((meta.actions || []).map(function (item) {
      return { title: item.label, subtitle: 'Mở ' + item.label.toLowerCase(), icon: item.icon, action: item.action };
    }))]);

    var metricSection = AppSection({ title: 'Tóm tắt', meta: 'Biểu đồ' });
    append(metricSection, [AppCard({ body: AppList(panels.map(function (item) { return AppMetricRow(item); })) })]);

    var filterSection = AppSection({ title: 'Bộ lọc nhanh', meta: 'Adaptive' });
    append(filterSection, [AppFilterSheet({
      label: 'Bộ lọc nhanh ' + meta.title,
      fields: [
        { label: 'Từ khóa', type: 'search', placeholder: 'Tìm trong ' + meta.title.toLowerCase() },
        { label: 'Trạng thái', type: 'select', options: [{ label: 'Tất cả', value: '' }, { label: 'Cần xử lý', value: 'todo' }, { label: 'Hoàn tất', value: 'done' }] }
      ],
      actions: [{ label: 'Áp dụng', icon: 'fa-filter' }, { label: 'Làm mới', icon: 'fa-rotate-right' }]
    })]);

    append(primary, [statSection, actionSection]);
    append(secondary, [metricSection, filterSection]);
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
        { key: 'overview', label: 'Tổng quan' },
        { key: 'data', label: 'Dữ liệu' },
        { key: 'report', label: 'Báo cáo', action: 'reports' }
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

  function cleanLabel(value) {
    return text(value).replace(/\s+/g, ' ').replace(/[:：]+$/, '').trim();
  }

  function isActionLabel(label) {
    return /^(thao tác|actions?|chọn|checkbox)$/i.test(cleanLabel(label));
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
    return Array.from(row.children).map(function (cell, index) {
      var label = cleanLabel(cell.getAttribute('data-label') || headers[index] || '');
      var value = text(cell);
      return { label: label, value: value };
    }).filter(function (field) {
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
    if (/delete|xóa|xoa/.test(action + ' ' + label)) return 'fa-trash';
    if (/edit|sửa|sua/.test(action + ' ' + label)) return 'fa-pen';
    if (/print|in\b/.test(action + ' ' + label)) return 'fa-print';
    if (/pdf/.test(action + ' ' + label)) return 'fa-file-pdf';
    if (/excel/.test(action + ' ' + label)) return 'fa-file-excel';
    if (/map|gis|định vị|dinh vi|location/.test(action + ' ' + label)) return 'fa-location-dot';
    return 'fa-eye';
  }

  function actionLabel(button) {
    var label = cleanLabel(button.getAttribute('title') || button.getAttribute('aria-label') || text(button));
    var action = String(button.getAttribute('data-platform-action') || '');
    if (/detail|xem chi|chi tiet|chi tiết/i.test(action + ' ' + label)) return 'Xem';
    if (label) return label;
    if (/delete/i.test(action)) return 'Xóa';
    if (/edit/i.test(action)) return 'Sửa';
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

  function rowActions(row) {
    var buttons = rowActionButtons(row).filter(function (button) {
      return !button.matches('input, [disabled]');
    });
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
      return /xem|chi tiết|chi tiet/i.test(item.label || '') || /fa-eye/.test(item.icon || '');
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
    var titleLabels = (meta.titleLabels || []).concat(['Họ tên', 'Họ và tên', 'Tên', 'Chủ hộ', 'Tên hộ', 'Tên công trình', 'Tên tài sản', 'Cơ sở', 'Mã hộ', 'Mã nhân khẩu', 'Mã thửa', 'Biển số']);
    var titleField = pickField(fields, titleLabels);
    if (titleField && titleField.value) return titleField.value;
    var fallback = fields.find(function (field) {
      return field.value.length > 2 && !/^\d+$/.test(field.value);
    });
    return fallback ? fallback.value : meta.title + ' #' + (index + 1);
  }

  function recordMeta(fields, title, meta) {
    var metaLabels = (meta.metaLabels || []).concat(['Địa chỉ', 'Mã hộ', 'CCCD', 'Khu', 'Khu vực', 'Trạng thái', 'Loại', 'Diện tích']);
    var selected = fields.filter(function (field) {
      return field.value !== title && matchesAny(field.label, metaLabels);
    }).slice(0, 4);
    if (!selected.length) {
      selected = fields.filter(function (field) {
        return field.value !== title && !/^(stt|#)$/i.test(cleanLabel(field.label));
      }).slice(0, 4);
    }
    return selected.map(function (field) {
      return field.label ? field.label + ': ' + field.value : field.value;
    }).join(' - ') || meta.subtitle;
  }

  function fieldIdentity(field) {
    return cleanLabel(field && field.label).toLowerCase() + ':' + cleanLabel(field && field.value).toLowerCase();
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
    var fullSeparatorIndex = owner.indexOf('：');
    if (separatorIndex < 0 || (fullSeparatorIndex >= 0 && fullSeparatorIndex < separatorIndex)) separatorIndex = fullSeparatorIndex;
    if (separatorIndex > 0 && isExactLabel(owner.slice(0, separatorIndex), ['Chủ hộ', 'Tên chủ hộ', 'Chu ho', 'Ten chu ho'])) {
      owner = owner.slice(separatorIndex + 1).trim();
    }
    return owner && owner !== title ? { label: 'Chủ hộ', value: owner } : null;
  }

  function normalizedSummaryField(field, requestedLabel, title) {
    if (!field) return null;
    var label = cleanLabel(requestedLabel || field.label);
    var value = cleanLabel(field.value);
    var foldedValue = foldedLabel(value);
    var foldedRequested = foldedLabel(label);
    if (foldedValue.indexOf(foldedRequested + ':') === 0 || foldedValue.indexOf(foldedRequested + ' ') === 0) {
      value = value.slice(label.length).replace(/^[:：\s]+/, '').trim();
    }
    if (matchesAny(label, ['Mã hộ', 'Ma ho'])) value = householdCode(value) || value;
    if (matchesAny(label, ['Chủ hộ', 'Tên chủ hộ', 'Chu ho', 'Ten chu ho'])) {
      var separatorIndex = value.indexOf(':');
      var fullSeparatorIndex = value.indexOf('：');
      if (separatorIndex < 0 || (fullSeparatorIndex >= 0 && fullSeparatorIndex < separatorIndex)) separatorIndex = fullSeparatorIndex;
      if (separatorIndex > 0 && isExactLabel(value.slice(0, separatorIndex), ['Chủ hộ', 'Tên chủ hộ', 'Chu ho', 'Ten chu ho'])) {
        value = value.slice(separatorIndex + 1).trim();
      }
      value = value.replace(householdCode(value), '').trim();
    }
    if (!value || value === title || (title && title.indexOf(value) >= 0)) return null;
    return { label: label, value: value };
  }

  function recordSummaryFields(fields, title, meta) {
    var labels = meta.summaryLabels || meta.metaLabels || [];
    var selected = [];
    (labels || []).forEach(function (label) {
      var isOwnerLabel = matchesAny(label, ['Chủ hộ', 'Tên chủ hộ', 'Chu ho', 'Ten chu ho']);
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
    return (fields || []).filter(function (field) {
      var label = cleanLabel(field.label);
      var value = cleanLabel(field.value);
      var repeatsSummary = summaryValues.some(function (summaryValue) {
        return summaryValue && value.indexOf(summaryValue) >= 0;
      });
      return field.value && field.value !== title && !repeatsSummary && !summaryIds[fieldIdentity(field)] && !matchesAny(label, summaryLabels) && !/^(stt|#)$/i.test(label) && !isActionLabel(label);
    });
  }

  function sourceRecords(screen, meta) {
    var rows = sourceRows(screen);
    if (rows.length) {
      return rows.map(function (row, index) {
        var fields = rowFields(row);
        var joined = fields.map(function (field) { return field.value; }).join(' ');
        var badges = [];
        if (/Tạm trú/i.test(joined)) badges.push({ label: 'Tạm trú', tone: 'warning' });
        if (/Tạm vắng|Đi vắng/i.test(joined)) badges.push({ label: 'Tạm vắng', tone: 'danger' });
        if (/Thường trú|Ở nhà/i.test(joined) && !badges.length) badges.push({ label: 'Thường trú', tone: 'success' });
        var title = recordTitle(fields, meta, index);
        var actions = rowActions(row);
        var summaryFields = recordSummaryFields(fields, title, meta);
        return {
          title: title,
          meta: recordMeta(fields, title, meta),
          summaryFields: summaryFields,
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
          icon: meta.icon,
          action: screen.id.replace(/Screen$/, ''),
          badges: [],
          details: body ? [{ label: 'Nội dung', value: body }] : []
        };
      });
    }
    return [
      { title: meta.title + ' đang sẵn sàng', meta: 'Dữ liệu sẽ hiển thị tại đây khi module được tải.', icon: meta.icon, action: screen.id.replace(/Screen$/, ''), badges: [] }
    ];
  }

  function scopedCount(screen, scope) {
    if (!scope || !scope.match) return 0;
    return sourceRows(screen).filter(function (row) {
      return text(row).indexOf(scope.match) >= 0;
    }).length;
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
    host.textContent = '';
    var total = countRecords(screen);
    var records = sourceRecords(screen, meta);
    var primary = el('div', 'app-v2-flow');
    var secondary = el('div', 'app-v2-flow');
    var layout = el('div', 'app-v2-two-pane');

    var summary = AppSection({ title: 'Tổng quan', meta: total ? number(total) + ' bản ghi' : 'Đang cập nhật' });
    append(summary, [el('div', 'app-v2-grid')]);
    summary.lastChild.appendChild(AppSummaryCard({ label: 'Tổng bản ghi', value: number(total), note: meta.subtitle, icon: meta.icon }));
    summary.lastChild.appendChild(AppSummaryCard({ label: 'Trạng thái', value: total ? 'Có dữ liệu' : 'Chờ dữ liệu', note: 'Đồng bộ từ module hiện có', icon: 'fa-circle-check' }));
    (meta.scopes || []).forEach(function (scope) {
      var scopeCard = AppSummaryCard({
        label: scope.label,
        value: number(scopedCount(screen, scope)),
        note: 'Scope dùng chung trong ' + meta.title,
        icon: scope.icon || meta.icon
      });
      scopeCard.setAttribute('data-app-v2-scope', scope.key || scope.label);
      summary.lastChild.appendChild(scopeCard);
    });

    var list = AppSection({ title: 'Danh sách', meta: 'Card List' });
    var recordList = el('div', 'app-v2-list');
    records.forEach(function (record) { recordList.appendChild(AppRecordCard(record)); });
    append(list, [recordList]);

    var filters = AppSection({ title: 'Bộ lọc', meta: 'Bottom Sheet ready' });
    append(filters, [AppFilterSheet({
      label: 'Bộ lọc ' + meta.title,
      fields: [
        { label: 'Từ khóa', type: 'search', placeholder: meta.search || 'Tìm kiếm...' },
        { label: 'Trạng thái', type: 'select', options: [{ label: 'Tất cả', value: '' }, { label: 'Đang quản lý', value: 'active' }, { label: 'Cần xử lý', value: 'todo' }] }
      ],
      actions: [{ label: 'Áp dụng', icon: 'fa-filter' }, { label: 'Đặt lại', icon: 'fa-rotate-right' }]
    })]);

    var actions = AppSection({ title: 'Thao tác', meta: 'Touch target 44px' });
    var actionRow = el('div', 'app-v2-action-row');
    var primaryAction = meta.primaryAction || {};
    var actionButton = AppButton({ label: primaryAction.label || 'Thêm mới', icon: primaryAction.icon || 'fa-plus', action: primaryAction.action || null });
    if (primaryAction.proxy) actionButton.setAttribute('data-app-v2-proxy-click', primaryAction.proxy);
    actionRow.appendChild(actionButton);
    (meta.nav || []).forEach(function (item) {
      actionRow.appendChild(AppButton({ label: item.label, icon: item.icon, action: item.action }));
    });
    append(actions, [actionRow]);

    append(primary, [summary, list]);
    append(secondary, [filters, actions]);
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
      })).concat([{ label: 'Làm mới', icon: 'fa-rotate-right' }])),
      layout,
      AppFAB({ icon: primaryAction.icon || 'fa-plus', label: primaryAction.label || 'Thêm mới', action: primaryAction.action || null })
    ]);
    if (screen.id === 'gisScreen') {
      host.appendChild(AppMapToolbar([
        { label: 'Làm mới', icon: 'fa-rotate-right', proxy: '#gisRefreshBtn' },
        { label: 'GPS', icon: 'fa-location-crosshairs', proxy: '#gisCurrentLocationBtn' },
        { label: 'Vẽ ranh giới', icon: 'fa-draw-polygon', proxy: '#gisDrawBtn' },
        { label: 'Xuất PDF', icon: 'fa-file-pdf', proxy: '#gisPdfBtn' }
      ]));
      host.appendChild(AppBottomSheet({
        label: 'Thông tin bản đồ GIS',
        title: 'Bản đồ GIS',
        body: AppList([
          { title: 'Leaflet vẫn giữ nguyên', subtitle: 'Map, marker, polygon, GPS và API dùng runtime hiện tại.', icon: 'fa-map-location-dot' },
          { title: 'Công cụ bản đồ', subtitle: 'Toolbar mới proxy về các nút nghiệp vụ cũ.', icon: 'fa-screwdriver-wrench' }
        ])
      }));
    }
    if (primaryAction.proxy) host.querySelector('.app-v2-fab').setAttribute('data-app-v2-proxy-click', primaryAction.proxy);
  }

  function renderDashboard() {
    var screen = document.getElementById('dashboardScreen');
    if (!screen) return;
    var host = document.getElementById('appMobileDashboard');
    if (!host) {
      host = el('section', 'app-v2-page app-v2-dashboard', { id: 'appMobileDashboard', 'aria-label': 'Dashboard Mobile' });
      screen.insertBefore(host, screen.firstChild);
    }
    var data = dashboardData();
    host.textContent = '';
    append(host, [
      AppHeader({
        eyebrow: 'Thôn 09',
        title: 'Dashboard',
        subtitle: data.generatedAt,
        icon: 'fa-bell',
        iconLabel: 'Thông báo'
      })
    ]);

    var toolbar = AppToolbar([
      { label: 'Hôm nay', icon: 'fa-calendar-day' },
      { label: 'Cảnh báo', icon: 'fa-bell', action: 'dashboard' },
      { label: 'GIS', icon: 'fa-map-location-dot', action: 'gis' },
      { label: 'Báo cáo', icon: 'fa-chart-pie', action: 'reports' }
    ]);
    host.appendChild(toolbar);
    host.appendChild(AppFilterBar({
      label: 'Tìm kiếm Dashboard',
      fields: [{ label: 'Từ khóa', type: 'search', placeholder: 'Tìm hộ, nhân khẩu, CCCD, địa chỉ...' }]
    }));

    var statSection = AppSection({ title: 'Chỉ số nhanh', meta: 'Mobile UI v2' });
    var statGrid = el('div', 'app-v2-grid app-v2-dashboard-kpis');
    data.stats.forEach(function (item) { statGrid.appendChild(AppStatCard(item)); });
    append(statSection, [statGrid]);

    var chartCards = (data.charts || []).map(function (item) {
      return AppDashboardChart(item);
    }).filter(Boolean);
    var chartSection = null;
    if (chartCards.length) {
      chartSection = AppSection({ title: 'Biểu đồ', meta: number(chartCards.length) + ' nhóm dữ liệu' });
      var chartGrid = el('div', 'app-v2-grid app-v2-dashboard-charts');
      chartCards.forEach(function (card) { chartGrid.appendChild(card); });
      append(chartSection, [chartGrid]);
    }

    var actionSection = AppSection({ title: 'Thao tác nhanh', meta: '4 mục chính' });
    var actionList = el('div', 'app-v2-list');
    data.quickActions.forEach(function (item) { actionList.appendChild(listItem(item)); });
    append(actionSection, [actionList]);

    var layout = el('div', 'app-v2-dashboard-layout');
    var primary = el('div', 'app-v2-section');
    var secondary = el('div', 'app-v2-section');
    append(primary, chartSection ? [statSection, chartSection, actionSection] : [statSection, actionSection]);

    var panelSection = AppSection({ title: 'Theo dõi', meta: 'Tóm tắt vận hành' });
    var panels = el('div', 'app-v2-grid app-v2-dashboard-panels');
    append(panels, [
      AppCard({ title: 'Biến động gần đây', body: listItem({ title: 'Theo dõi tạm trú, tạm vắng', subtitle: 'Mở module biến động để xử lý hồ sơ mới', icon: 'fa-arrows-rotate', action: 'movements' }) }),
      AppCard({ title: 'Dữ liệu bản đồ', body: listItem({ title: 'Tiến độ định vị GIS', subtitle: 'Kiểm tra hộ chưa có tọa độ', icon: 'fa-location-crosshairs', action: 'gis' }) })
    ]);
    append(panelSection, [panels]);
    var summarySection = AppSection({ title: 'Tóm tắt', meta: 'Adaptive' });
    var summaryList = el('div', 'app-v2-list');
    data.health.forEach(function (item) { summaryList.appendChild(AppSummaryCard(item)); });
    append(summarySection, [summaryList, AppInput({ label: 'Ghi chú nhanh', placeholder: 'Nhập ghi chú xử lý...' }), AppSelect({ label: 'Trạng thái theo dõi', options: [{ label: 'Tất cả', value: '' }, { label: 'Cần xử lý', value: 'todo' }, { label: 'Hoàn tất', value: 'done' }] })]);
    var alertSection = AppSection({ title: 'Cảnh báo và tác vụ', meta: data.alerts.length + data.tasks.length ? 'Dữ liệu thật' : 'Fallback' });
    append(alertSection, [AppList((data.alerts.length ? data.alerts : [{ title: 'Không có cảnh báo nổi bật', subtitle: 'Dashboard đang ổn định', icon: 'fa-circle-check', action: 'dashboard' }]).concat(data.tasks))]);
    append(secondary, [summarySection, alertSection, panelSection]);
    append(layout, [primary, secondary]);
    append(host, [layout, AppFAB({ icon: 'fa-plus', label: 'Thêm nhanh', action: 'households' })]);
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
    var target = event.target.closest('.app-v2-button[data-screen], .app-v2-icon-button[data-screen], .app-v2-chip[data-screen], .app-v2-tab[data-screen], .app-v2-fab[data-screen], .app-v2-bottom-nav button[data-screen]');
    var proxy = event.target.closest('[data-app-v2-proxy-click]');
    var primary = event.target.closest('[data-app-v2-primary-proxy]');
    if (proxy) {
      if (dispatchProxy(proxy.getAttribute('data-app-v2-proxy-click'))) return;
    }
    if (primary && !event.target.closest('button, a, input, select, textarea, summary, details')) {
      if (dispatchProxy(primary.getAttribute('data-app-v2-primary-proxy'))) return;
    }
    if (!target) return;
    var screen = target.getAttribute('data-screen');
    if (!screen) return;
    if (window.Thon09NavigationController && typeof window.Thon09NavigationController.navigate === 'function') {
      window.Thon09NavigationController.navigate(screen);
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
  document.addEventListener('thon09:screen-change', scheduleDataSync);
  document.addEventListener('thon09:module-state-change', schedule);
  document.addEventListener('thon09:app-state-change', schedule);

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

  window.Thon09MobileComponents = {
    AppButton: AppButton,
    AppBadge: AppBadge,
    AppStatusChip: AppStatusChip,
    AppBottomSheet: AppBottomSheet,
    AppBottomNavigation: AppBottomNavigation,
    AppCard: AppCard,
    AppDrawer: AppDrawer,
    AppEmptyState: AppEmptyState,
    AppFAB: AppFAB,
    AppFilterBar: AppFilterBar,
    AppFilterSheet: AppFilterSheet,
    AppHeader: AppHeader,
    AppIconButton: AppIconButton,
    AppInput: AppInput,
    AppList: AppList,
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
