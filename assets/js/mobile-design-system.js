(function (window, document) {
  'use strict';

  var importantFields = new Set([
    'type',
    'area',
    'area-size',
    'metric',
    'at-home',
    'away',
    'status',
    'household-type',
    'residence',
    'party',
    'time',
    'content',
    'identity'
  ]);

  var uid = 0;

  function textOf(node) {
    return (node ? node.textContent || '' : '').replace(/\s+/g, ' ').trim();
  }

  function isEmpty(cell) {
    if (!cell || cell.hasAttribute('data-mobile-empty')) return true;
    var value = textOf(cell).toLowerCase();
    return !value || value === '-' || value === '--' || value === '---' || value === 'n/a';
  }

  function normalizeLabel(value) {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/\u0111/g, 'd')
      .replace(/\u0110/g, 'D')
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .trim();
  }

  function inferField(label) {
    if (label.indexOf('ten') >= 0 || label.indexOf('chu ho') >= 0 || label.indexOf('chu so huu') >= 0 || label.indexOf('nguoi quan ly') >= 0 || label.indexOf('nhan khau') >= 0 || label.indexOf('cong dan') >= 0 || label === 'nguoi') return 'name';
    if (label.indexOf('cccd') >= 0 || label.indexOf('cmnd') >= 0) return 'identity';
    if (label.indexOf('ma') >= 0 || label.indexOf('bien so') >= 0) return 'code';
    if (label.indexOf('dia chi') >= 0 || label.indexOf('khu vuc') >= 0 || label.indexOf('thon') >= 0) return 'area';
    if (label.indexOf('ngay') >= 0 || label.indexOf('thoi gian') >= 0) return 'time';
    if (label.indexOf('ly do') >= 0 || label.indexOf('noi dung') >= 0 || label.indexOf('ghi chu') >= 0) return 'content';
    if (label.indexOf('dien tich') >= 0 || label.indexOf('so luong') >= 0 || label.indexOf('da nop') >= 0 || label.indexOf('chua nop') >= 0 || label.indexOf('phai thu') >= 0 || label.indexOf('da thu') >= 0) return 'metric';
    if (label.indexOf('loai') >= 0) return 'type';
    if (label.indexOf('trang thai') >= 0 || label.indexOf('tinh trang') >= 0 || label.indexOf('dien ho') >= 0) return 'status';
    return label.replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'field';
  }

  function inferRole(label, index, cell) {
    if (cell.querySelector('input[type="checkbox"]') && index === 0) return 'select';
    if (label.indexOf('thao tac') >= 0 || (cell.matches('.text-end') && cell.querySelector('button, .btn, a[href]'))) return 'actions';
    if (label.indexOf('ten') >= 0 || label.indexOf('chu ho') >= 0 || label.indexOf('chu so huu') >= 0 || label.indexOf('nguoi quan ly') >= 0 || label.indexOf('nhan khau') >= 0 || label.indexOf('cong dan') >= 0 || label === 'nguoi') return 'title';
    if (label.indexOf('ma') >= 0 || label.indexOf('bien so') >= 0) return 'header-meta';
    if (label.indexOf('dia chi') >= 0 || label.indexOf('khu vuc') >= 0 || label.indexOf('thon') >= 0) return 'address';
    if (label.indexOf('trang thai') >= 0 || label.indexOf('tinh trang') >= 0 || label.indexOf('dien ho') >= 0 || label.indexOf('loai') >= 0) return 'badge';
    if (label.indexOf('dien tich') >= 0 || label.indexOf('so luong') >= 0 || label.indexOf('da nop') >= 0 || label.indexOf('chua nop') >= 0 || label.indexOf('phai thu') >= 0 || label.indexOf('da thu') >= 0) return 'stat';
    if (label.indexOf('ngay') >= 0 || label.indexOf('thoi gian') >= 0 || label.indexOf('ly do') >= 0 || label.indexOf('noi dung') >= 0) return 'stat';
    return '';
  }

  function searchSignals(control, holder) {
    return normalizeLabel([
      control.getAttribute('placeholder'),
      control.getAttribute('aria-label'),
      control.id,
      control.name,
      control.className,
      holder ? textOf(holder) : ''
    ].filter(Boolean).join(' '));
  }

  function isSearchInput(control, holder) {
    if (!control.matches('input')) return false;
    var type = (control.type || 'text').toLowerCase();
    if (['hidden', 'checkbox', 'radio', 'file', 'date', 'datetime-local', 'number', 'range', 'time', 'month', 'week', 'color', 'password'].indexOf(type) >= 0) return false;
    var text = searchSignals(control, holder);
    return type === 'search'
      || text.indexOf('tim') >= 0
      || text.indexOf('search') >= 0
      || text.indexOf('keyword') >= 0
      || text.indexOf('tu khoa') >= 0
      || text.indexOf('query') >= 0;
  }

  function isTextInput(control) {
    if (!control.matches('input')) return false;
    var type = (control.type || 'text').toLowerCase();
    return ['text', 'search', ''].indexOf(type) >= 0;
  }

  function isFilterButton(control, text) {
    if (!control.matches('button, .btn, a[href]')) return false;
    return text.indexOf('loc') >= 0
      || text.indexOf('filter') >= 0
      || text.indexOf('reset') >= 0
      || text.indexOf('lam moi') >= 0
      || text.indexOf('dat lai') >= 0
      || text.indexOf('tim') >= 0
      || text.indexOf('search') >= 0;
  }

  function isPrimaryAction(control, text) {
    if (!control.matches('button, .btn, a[href]')) return false;
    return text.indexOf('them') >= 0
      || text.indexOf('tao') >= 0
      || text.indexOf('luu') >= 0
      || text.indexOf('excel') >= 0
      || text.indexOf('pdf') >= 0
      || text.indexOf('bao cao') >= 0
      || text.indexOf('xuat') >= 0
      || text.indexOf('import') >= 0
      || text.indexOf('upload') >= 0;
  }

  function isPageSizeControl(control, text) {
    if (!control.matches('select')) return false;
    return text.indexOf('hien thi') >= 0
      || text.indexOf('page size') >= 0
      || text.indexOf('pagesize') >= 0
      || text.indexOf('so dong') >= 0
      || text.indexOf('moi trang') >= 0;
  }

  function ensureMobileLabels(table) {
    var headers = Array.from(table.querySelectorAll('thead th')).map(function (th) { return textOf(th); });
    if (!headers.length) return;
    table.querySelectorAll('tbody tr').forEach(function (row) {
      Array.from(row.children).forEach(function (cell, index) {
        if (cell.tagName !== 'TD') return;
        var label = headers[index] || cell.getAttribute('data-label') || '';
        var normalized = normalizeLabel(label);
        if (!cell.hasAttribute('data-label')) cell.setAttribute('data-label', label);
        if (!cell.dataset.mobileField) cell.dataset.mobileField = inferField(normalized);
        if (!cell.dataset.mobileRole) {
          var role = inferRole(normalized, index, cell);
          if (role) cell.dataset.mobileRole = role;
        }
      });
    });
  }

  function ensureId(element) {
    if (!element.dataset.mobileSourceId) {
      uid += 1;
      element.dataset.mobileSourceId = 'mobile-source-' + uid;
    }
    return element.dataset.mobileSourceId;
  }

  function pickTitle(cells) {
    return cells.find(function (cell) { return cell.dataset.mobileRole === 'title' && !isEmpty(cell); })
      || cells.find(function (cell) {
        var key = cell.dataset.mobileField || '';
        return ['name', 'owner', 'asset-name'].indexOf(key) >= 0 && !isEmpty(cell);
      })
      || cells.find(function (cell) { return !cell.querySelector('input, button, .btn, a[href]') && !isEmpty(cell); });
  }

  function pickMeta(cells, title) {
    return cells.find(function (cell) {
      return cell !== title && cell.dataset.mobileRole === 'header-meta' && !isEmpty(cell);
    }) || cells.find(function (cell) {
      return cell !== title && cell.dataset.mobileRole === 'meta' && !isEmpty(cell);
    });
  }

  function pickStatus(cells, title, meta) {
    return cells.find(function (cell) {
      return cell !== title && cell !== meta && cell.dataset.mobileRole === 'badge' && !isEmpty(cell);
    }) || cells.find(function (cell) {
      var key = cell.dataset.mobileField || '';
      return cell !== title && cell !== meta && ['status', 'type', 'household-type'].indexOf(key) >= 0 && !isEmpty(cell);
    });
  }

  function titleTextOf(title, meta) {
    if (!title) return 'Ban ghi';
    var strong = title.querySelector('strong, b, .fw-semibold, .fw-bold');
    var value = textOf(strong) || textOf(title);
    var metaText = meta ? textOf(meta) : '';
    if (metaText && value !== metaText && value.slice(-metaText.length) === metaText) {
      value = value.slice(0, -metaText.length).trim();
    }
    return value || textOf(title) || 'Ban ghi';
  }

  function decorateRow(row) {
    var cells = Array.from(row.children).filter(function (cell) { return cell.tagName === 'TD'; });
    if (!cells.length) return;
    var colspan = cells.find(function (cell) { return cell.hasAttribute('colspan'); });
    row.classList.add(colspan ? 'mobile-source-empty' : 'mobile-source-card');
    row.dataset.mobileSourceRow = ensureId(row);
    if (colspan) {
      row.dataset.mobileEmptyMessage = textOf(colspan) || 'Chua co du lieu.';
      return;
    }

    var title = pickTitle(cells);
    var meta = pickMeta(cells, title);
    var status = pickStatus(cells, title, meta);
    var action = cells.find(function (cell) {
      return cell.dataset.mobileRole === 'actions' || (cell.matches('.text-end') && cell.querySelector('button, .btn, a[href]'));
    });
    var select = cells.find(function (cell) { return cell.dataset.mobileRole === 'select' && cell.querySelector('input'); });
    var summary = cells.filter(function (cell) {
      if (cell === title || cell === meta || cell === action || cell === select || isEmpty(cell)) return false;
      var role = cell.dataset.mobileRole || '';
      var field = cell.dataset.mobileField || '';
      return role === 'address' || role === 'stat' || role === 'badge' || importantFields.has(field);
    }).slice(0, 4);

    if (status && summary.indexOf(status) < 0 && status !== title && status !== meta) summary.unshift(status);
    row.dataset.mobileTitle = titleTextOf(title, meta);
    row.dataset.mobileCode = meta && !isEmpty(meta) ? textOf(meta) : '';
    row.dataset.mobileStatus = status && !isEmpty(status) ? textOf(status) : '';
    row.dataset.mobileSummary = summary.slice(0, 4).map(function (cell) {
      var label = cell.getAttribute('data-label') || '';
      var value = textOf(cell);
      return label ? label + ': ' + value : value;
    }).filter(Boolean).join('  ');
    cells.forEach(function (cell) {
      cell.classList.remove('mobile-card-title-cell', 'mobile-card-code-cell', 'mobile-card-status-cell', 'mobile-card-action-cell', 'mobile-card-select-cell', 'mobile-card-summary-cell', 'mobile-card-hidden-cell');
      if (cell === title) cell.classList.add('mobile-card-title-cell');
      else if (cell === meta) cell.classList.add('mobile-card-code-cell');
      else if (cell === status) cell.classList.add('mobile-card-status-cell');
      else if (cell === action) cell.classList.add('mobile-card-action-cell');
      else if (cell === select) cell.classList.add('mobile-card-select-cell');
      else if (summary.indexOf(cell) >= 0) cell.classList.add('mobile-card-summary-cell');
      else cell.classList.add('mobile-card-hidden-cell');
    });
  }

  function actionLabel(control) {
    var aria = control.getAttribute('aria-label') || control.title || '';
    var text = textOf(control);
    return aria || text || 'Thao tac';
  }

  function visibleActionText(control) {
    var clone = control.cloneNode(true);
    clone.querySelectorAll('i, svg, .fa, .fa-solid, .fa-regular, .fa-brands, .fas, .far, .fab, .sr-only, [aria-hidden="true"]').forEach(function (node) {
      node.remove();
    });
    return textOf(clone);
  }

  function syncIconOnlyControl(control) {
    if (!control || !control.matches || !control.matches('button, .btn, a[href].btn, .nav-link, .dropdown-item, .page-link')) return;
    var hasIcon = Boolean(control.querySelector('i.fa, i.fa-solid, i.fa-regular, i.fa-brands, i.fas, i.far, i.fab, svg'));
    var hasVisibleText = Boolean(visibleActionText(control));
    control.classList.toggle('r-icon-only', hasIcon && !hasVisibleText);
    control.classList.toggle('r-icon-text', hasIcon && hasVisibleText);
  }

  function enhanceActionIcons(root) {
    var scope = root && root.querySelectorAll ? root : document;
    if (scope.matches) syncIconOnlyControl(scope);
    scope.querySelectorAll('button, .btn, a[href].btn, .nav-link, .dropdown-item, .page-link').forEach(syncIconOnlyControl);
  }

  function cloneAction(control) {
    var clone = control.cloneNode(true);
    clone.classList.add('mobile-card-action');
    clone.setAttribute('aria-label', actionLabel(control));
    if (!clone.querySelector('i')) {
      clone.textContent = actionLabel(control);
    }
    return clone;
  }

  function renderCard(row) {
    var cells = Array.from(row.children).filter(function (cell) { return cell.tagName === 'TD'; });
    if (!cells.length || cells.some(function (cell) { return cell.hasAttribute('colspan'); })) {
      var empty = document.createElement('article');
      empty.className = 'mobile-list-empty';
      empty.textContent = cells.length ? textOf(cells[0]) : 'Chua co du lieu.';
      return empty;
    }

    var title = pickTitle(cells);
    var meta = pickMeta(cells, title);
    var status = pickStatus(cells, title, meta);
    var actionCell = cells.find(function (cell) {
      return cell.dataset.mobileRole === 'actions' || (cell.matches('.text-end') && cell.querySelector('button, .btn, a[href]'));
    });
    var select = cells.find(function (cell) { return cell.dataset.mobileRole === 'select' && cell.querySelector('input'); });
    var summary = cells.filter(function (cell) {
      if (cell === title || cell === meta || cell === actionCell || cell === select || isEmpty(cell)) return false;
      var role = cell.dataset.mobileRole || '';
      var field = cell.dataset.mobileField || '';
      return role === 'address' || role === 'stat' || role === 'badge' || importantFields.has(field);
    }).slice(0, 5);

    var card = document.createElement('article');
    card.className = 'mobile-list-card';
    card.dataset.mobileSourceRow = row.dataset.mobileSourceRow || ensureId(row);

    var head = document.createElement('div');
    head.className = 'mobile-list-card-head';
    var titleNode = document.createElement('strong');
    titleNode.className = 'mobile-card-title';
    titleNode.textContent = titleTextOf(title, meta);
    head.appendChild(titleNode);
    if (meta && !isEmpty(meta)) {
      var code = document.createElement('span');
      code.className = 'mobile-card-code';
      code.textContent = textOf(meta);
      head.appendChild(code);
    }
    if (select) {
      var checkbox = select.querySelector('input[type="checkbox"]');
      if (checkbox) head.appendChild(checkbox.cloneNode(true));
    }

    var body = document.createElement('div');
    body.className = 'mobile-card-body';
    if (status && summary.indexOf(status) < 0 && status !== title && status !== meta) summary.unshift(status);
    summary.forEach(function (cell) {
      var info = document.createElement('span');
      info.className = 'mobile-card-info';
      info.dataset.mobileRole = cell.dataset.mobileRole || '';
      info.dataset.mobileField = cell.dataset.mobileField || '';
      var label = document.createElement('span');
      label.className = 'mobile-card-info-label';
      label.textContent = cell.getAttribute('data-label') || '';
      var value = document.createElement('span');
      value.className = 'mobile-card-info-value';
      value.textContent = textOf(cell);
      if (label.textContent) info.appendChild(label);
      info.appendChild(value);
      body.appendChild(info);
    });

    var actions = document.createElement('div');
    actions.className = 'mobile-card-actions';
    if (actionCell) {
      Array.from(actionCell.querySelectorAll('button, .btn, a[href]')).slice(0, 4).forEach(function (control) {
        actions.appendChild(cloneAction(control));
      });
    }

    card.appendChild(head);
    if (body.children.length) card.appendChild(body);
    if (actions.children.length) card.appendChild(actions);
    return card;
  }

  function enhanceFilters(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var selectors = [
      '.module-filter-card',
      '.module-filter-panel',
      '.person-search-card',
      '.report-filter-card',
      '.livestock-filter-card',
      '.agri-filter-card',
      '.houses-filter-card',
      '.dashboard-filter',
      '.toolbar',
      '.agri-toolbar',
      '.houses-toolbar',
      '.livestock-toolbar'
    ].join(',');
    var nodes = [];
    if (scope.matches && scope.matches(selectors)) nodes.push(scope);
    scope.querySelectorAll(selectors).forEach(function (node) { nodes.push(node); });
    nodes.forEach(function (container) {
      var oldGeneratedTriggers = Array.from(container.querySelectorAll(':scope > .mobile-filter-trigger[data-mobile-generated-filter="true"]'));
      oldGeneratedTriggers.forEach(function (trigger) { trigger.remove(); });
      container.classList.remove('mobile-filter-system', 'mobile-action-system', 'mobile-filter-expanded');
      container.querySelectorAll('.mobile-filter-search, .mobile-filter-extra, .mobile-filter-action-wrap, .mobile-filter-action').forEach(function (node) {
        node.classList.remove('mobile-filter-search', 'mobile-filter-extra', 'mobile-filter-action-wrap', 'mobile-filter-action');
      });
      container.querySelectorAll('.mobile-search-control').forEach(function (node) { node.classList.remove('mobile-search-control'); });
      if (!isFilterCompactViewport()) return;
      var hasExtra = false;
      var hasFilterControl = false;
      var hasActionControl = false;
      var entries = Array.from(container.querySelectorAll('input, select, button')).map(function (control) {
        var holder = control.closest('.module-field, .person-field, .agri-field, .houses-field, .col-md-3, .col-md-4, .col-md-6, .col-12, .d-flex') || control.parentElement;
        if (holder && holder.matches('.d-flex') && holder.querySelectorAll('input, select, button').length > 1) holder = control;
        if (holder && holder !== control && holder.querySelectorAll('input, select, button').length > 1 && !holder.matches('.module-field, .person-field, .agri-field, .houses-field, .col-md-3, .col-md-4, .col-md-6, .col-12')) holder = control;
        return {
          control: control,
          holder: holder
        };
      });
      var structuredFilterEntry = entries.find(function (entry) {
        var control = entry.control;
        return control.matches('select') || (control.matches('input') && !isTextInput(control));
      });
      var searchEntry = entries.find(function (entry) { return isSearchInput(entry.control, entry.holder); })
        || entries.find(function (entry) { return isTextInput(entry.control); });
      var primaryFilterEntry = searchEntry || structuredFilterEntry;
      entries.forEach(function (entry) {
        var control = entry.control;
        if (control.matches('[data-mobile-filter-toggle], .mobile-filter-toggle, .mobile-filter-close')) return;
        var holder = entry.holder;
        var roleHolder = holder === container ? control : holder;
        var text = searchSignals(control, holder);
        if (entry === primaryFilterEntry) {
          if (entry === searchEntry) {
            control.classList.add('mobile-search-control');
            if (!control.getAttribute('placeholder')) control.setAttribute('placeholder', 'Tim kiem...');
          }
          if (roleHolder) roleHolder.classList.add('mobile-filter-search');
        } else if (isPageSizeControl(control, text)) {
          if (roleHolder) roleHolder.classList.add('mobile-filter-action-wrap');
          control.classList.add('mobile-filter-action');
          hasActionControl = true;
        } else if (isPrimaryAction(control, text)) {
          if (roleHolder) roleHolder.classList.add('mobile-filter-action-wrap');
          control.classList.add('mobile-filter-action');
          hasActionControl = true;
        } else if (control.matches('select') || (control.matches('input') && !isTextInput(control))) {
          if (roleHolder) roleHolder.classList.add('mobile-filter-extra');
          hasExtra = true;
          hasFilterControl = true;
        } else if (isFilterButton(control, text)) {
          if (roleHolder) roleHolder.classList.add('mobile-filter-extra');
          hasExtra = true;
          hasFilterControl = true;
        } else if (holder) {
          if (control.matches('button, .btn, a[href]')) {
            if (roleHolder) roleHolder.classList.add('mobile-filter-action-wrap');
            control.classList.add('mobile-filter-action');
            hasActionControl = true;
          } else {
            roleHolder.classList.add('mobile-filter-extra');
            hasExtra = true;
            hasFilterControl = true;
          }
        }
      });
      var toolbarWithoutSearch = !searchEntry && container.matches('.toolbar, .agri-toolbar, .houses-toolbar, .livestock-toolbar');
      var hasStructuredFilter = Boolean(structuredFilterEntry);
      if (searchEntry || (hasStructuredFilter && hasFilterControl && !toolbarWithoutSearch)) container.classList.add('mobile-filter-system');
      else if (hasActionControl || toolbarWithoutSearch) container.classList.add('mobile-action-system');
      var existingToggle = container.querySelector(':scope > [data-mobile-filter-toggle], :scope > .mobile-filter-toggle, :scope > .mobile-filter-shell, :scope > .mobile-filter-trigger');
      if (container.classList.contains('mobile-filter-system') && hasExtra && hasFilterControl && isFilterCompactViewport() && !existingToggle) {
        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'mobile-filter-trigger';
        toggle.dataset.mobileFilterToggle = 'true';
        toggle.dataset.mobileGeneratedFilter = 'true';
        toggle.setAttribute('aria-label', 'Bo loc');
        toggle.innerHTML = '<i class="fa-solid fa-sliders"></i>';
        container.appendChild(toggle);
      }
    });
  }

  function enhanceKpis(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var selectors = '.dashboard-kpi-grid, .dashboard-metric-grid, .module-placeholder-grid, .houses-kpi-grid, .livestock-kpi-grid, .agri-kpi-grid, .gis-summary-grid, #publicAssetsMiniDashboard';
    var nodes = [];
    if (scope.matches && scope.matches(selectors)) nodes.push(scope);
    scope.querySelectorAll(selectors).forEach(function (node) { nodes.push(node); });
    nodes.forEach(function (container) {
      container.classList.add('mobile-kpi-system');
      Array.from(container.children).forEach(function (child) {
        if (child.nodeType === 1) child.classList.add('mobile-kpi-cell');
      });
    });
  }

  function enhancePagers(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var selectors = '.pager, .module-pager, .person-pager, [id$="Pager"]';
    var nodes = [];
    if (scope.matches && scope.matches(selectors)) nodes.push(scope);
    scope.querySelectorAll(selectors).forEach(function (node) { nodes.push(node); });
    nodes.forEach(function (pager) {
      pager.classList.add('mobile-pager-system');
      Array.from(pager.children).forEach(function (child) {
        var text = normalizeLabel(textOf(child) || child.getAttribute('aria-label') || child.title || '');
        child.classList.remove('mobile-pager-label');
        delete child.dataset.mobilePagerNav;
        if (child.matches('button, a, .btn')) {
          if (text.indexOf('truoc') >= 0 || text.indexOf('prev') >= 0 || text === '<' || text.indexOf('‹') >= 0) {
            child.dataset.mobilePagerNav = 'prev';
          } else if (text.indexOf('sau') >= 0 || text.indexOf('next') >= 0 || text === '>' || text.indexOf('›') >= 0) {
            child.dataset.mobilePagerNav = 'next';
          } else {
            child.classList.add('mobile-pager-label');
          }
        } else {
          child.classList.add('mobile-pager-label');
        }
      });
    });
  }

  function enhanceFloating(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var selectors = '.module-action-row, .floating-action-row, .fab-row, .online-status, .online-badge, [data-online-status]';
    var nodes = [];
    if (scope.matches && scope.matches(selectors)) nodes.push(scope);
    scope.querySelectorAll(selectors).forEach(function (node) { nodes.push(node); });
    nodes.forEach(function (node) {
      if (node.matches('.module-action-row, .floating-action-row, .fab-row')) node.classList.add('mobile-floating-primary');
      else node.classList.add('mobile-online-badge');
    });
  }

  function isCardViewport() {
    return !window.matchMedia || window.matchMedia('(max-width: 1024px)').matches;
  }

  function isFilterCompactViewport() {
    return !window.matchMedia || window.matchMedia('(max-width: 1023px)').matches;
  }

  function setSourceTableMode(table, active) {
    table.classList.toggle('mobile-source-table', active);
    if (active) {
      table.setAttribute('aria-hidden', 'true');
      table.setAttribute('inert', '');
    } else {
      table.removeAttribute('aria-hidden');
      table.removeAttribute('inert');
    }
  }

  function enhanceTable(wrapper) {
    var table = wrapper.querySelector('table');
    var tbody = table && table.querySelector('tbody');
    if (!table || !tbody) return;
    ensureMobileLabels(table);
    var surface = wrapper.querySelector(':scope > .mobile-list-surface');
    if (!isCardViewport()) {
      setSourceTableMode(table, false);
      wrapper.classList.remove('mobile-list-ready');
      if (surface) surface.remove();
      return;
    }
    setSourceTableMode(table, true);
    wrapper.classList.add('mobile-list-ready');
    if (!surface) {
      surface = document.createElement('div');
      surface.className = 'mobile-list-surface';
      surface.setAttribute('aria-hidden', 'true');
      wrapper.insertBefore(surface, table);
    }
    surface.innerHTML = '';
    Array.from(tbody.children).forEach(function (row) {
      if (row.classList.contains('group-row')) return;
      decorateRow(row);
      surface.appendChild(renderCard(row));
    });
  }

  function enhance(root) {
    var scope = root && root.querySelectorAll ? root : document;
    if (scope.matches && scope.matches('.table-responsive')) enhanceTable(scope);
    scope.querySelectorAll('.table-responsive').forEach(enhanceTable);
    enhanceKpis(scope);
    enhanceFilters(scope);
    enhancePagers(scope);
    enhanceFloating(scope);
    enhanceMobileMenu(scope);
    enhanceActionIcons(scope);
  }

  function enhanceMobileMenu(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var nav = scope.matches && scope.matches('.gov-nav') ? scope : document.querySelector('.gov-nav');
    if (!nav) return;
    nav.classList.add('mobile-menu-system');
    nav.querySelectorAll('.nav-section').forEach(function (section, index) {
      var title = section.querySelector(':scope > .nav-section-title, :scope > .sidebar-accordion-toggle');
      var items = section.querySelector(':scope > .nav-section-items, :scope > .sidebar-accordion-panel');
      if (!title || !items) return;
      if (!items.id) items.id = 'sidebar-menu-group-' + index;
      title.setAttribute('aria-controls', items.id);
      if (!title.hasAttribute('aria-expanded')) title.setAttribute('aria-expanded', 'true');
      if (!section.classList.contains('is-open') && !section.classList.contains('is-collapsed')) section.classList.add('is-open');
      section.dataset.mobileMenuTouched = 'true';
    });
  }

  var scheduled = false;
  function scheduleEnhance() {
    if (scheduled) return;
    scheduled = true;
    window.requestAnimationFrame(function () {
      scheduled = false;
      enhance(document);
    });
  }

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest('[data-mobile-filter-toggle]');
    if (toggle) {
      var filter = toggle.closest('.mobile-filter-system');
      if (filter) filter.classList.toggle('mobile-filter-expanded');
      return;
    }
    var menuToggle = event.target.closest('[data-sidebar-group-toggle], [data-mobile-menu-group-toggle], .sidebar-accordion-toggle');
    if (menuToggle && menuToggle.closest('.gov-nav')) {
      var section = menuToggle.closest('.nav-section');
      if (section) {
        var nextOpen = !section.classList.contains('is-open');
        section.classList.toggle('is-open', nextOpen);
        section.classList.toggle('is-collapsed', !nextOpen);
        menuToggle.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
        if (menuToggle.dataset.sidebarGroupToggle) {
          try {
            window.localStorage.setItem('thon09_sidebar_group_' + menuToggle.dataset.sidebarGroupToggle, nextOpen ? '1' : '0');
          } catch (error) {}
        }
      }
    }
  });

  if (window.MutationObserver) {
    new MutationObserver(function (mutations) {
      var shouldEnhance = mutations.some(function (mutation) {
        if (mutation.target && mutation.target.closest && mutation.target.closest('.mobile-list-surface')) return false;
        return Array.from(mutation.addedNodes || []).some(function (node) {
          return node.nodeType === 1 && (
            (node.matches && node.matches('tr, table, .table-responsive, .module-filter-card, .module-filter-panel, .toolbar, .pager, .module-pager, [id$="Pager"], .dashboard-kpi-grid, .dashboard-metric-grid'))
            || (node.querySelector && node.querySelector('tr, table, .table-responsive, .module-filter-card, .module-filter-panel, .toolbar, .pager, .module-pager, [id$="Pager"], .dashboard-kpi-grid, .dashboard-metric-grid'))
          );
        });
      });
      if (shouldEnhance) scheduleEnhance();
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  if (window.matchMedia) {
    var cardViewportQuery = window.matchMedia('(max-width: 1024px)');
    if (cardViewportQuery.addEventListener) cardViewportQuery.addEventListener('change', scheduleEnhance);
    else if (cardViewportQuery.addListener) cardViewportQuery.addListener(scheduleEnhance);
  }
  window.addEventListener('resize', scheduleEnhance, { passive: true });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scheduleEnhance);
  else scheduleEnhance();

  window.thon09EnhanceDesignSystem = enhance;
  window.Thon09MobileDesignSystem = {
    enhance: enhance,
    decorateRow: decorateRow,
    syncIconOnlyControl: syncIconOnlyControl
  };
})(window, document);
