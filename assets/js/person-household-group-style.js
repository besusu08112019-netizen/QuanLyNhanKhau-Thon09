(function () {
  'use strict';

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

  function escapeHtml(value) {
    if (typeof window.escapeHtml === 'function') return window.escapeHtml(value);
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
    });
  }

  function parseDate(value) {
    var text = String(value || '').trim();
    var day, month, year;
    var match = text.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
    if (match) {
      day = Number(match[1]); month = Number(match[2]); year = Number(match[3]);
    } else {
      match = text.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/);
      if (!match) return null;
      year = Number(match[1]); month = Number(match[2]); day = Number(match[3]);
    }
    var date = new Date(year, month - 1, day);
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) return null;
    return date;
  }

  function formatDate(value) {
    var date = parseDate(value);
    if (!date) return String(value || '');
    return String(date.getDate()).padStart(2, '0') + '/' + String(date.getMonth() + 1).padStart(2, '0') + '/' + date.getFullYear();
  }

  function ageFromBirthday(value) {
    var date = parseDate(value);
    if (!date) return null;
    var now = new Date();
    var age = now.getFullYear() - date.getFullYear();
    if (now.getMonth() < date.getMonth() || (now.getMonth() === date.getMonth() && now.getDate() < date.getDate())) age -= 1;
    return age >= 0 && age < 130 ? age : null;
  }

  function residenceInfo(row) {
    if (row && row.presence_status === 'AWAY') return { text: 'Tạm vắng', cls: 'population-status-away', badgeCls: 'person-badge-away' };
    if (row && row.residency_status === 'TEMPORARY') return { text: 'Tạm trú', cls: 'population-status-temp', badgeCls: 'person-badge-temp' };
    if (row && (row.residency_status === 'MOVED' || row.life_status === 'MOVED')) return { text: 'Chuyển đi', cls: 'population-status-away', badgeCls: 'person-badge-away' };
    if (row && (row.life_status === 'DECEASED' || row.life_status === 'DEAD')) return { text: 'Đã mất', cls: 'population-status-muted', badgeCls: 'person-badge-muted' };
    var text = typeof window.residencyLabel === 'function' ? window.residencyLabel(row && row.residency_status) : 'Thường trú';
    return { text: text || 'Thường trú', cls: 'population-status-home', badgeCls: 'person-badge-home' };
  }

  function relationshipText(row) {
    return String(row && (row.relationship || row.relationship_to_head || row.relationshipToHead || row.member_type || row.memberType || '') || '').trim();
  }

  function relationshipIcon(text) {
    var value = normalize(text);
    if (value.includes('chu ho')) return '👑';
    if (value.includes('vo')) return '👩';
    if (value.includes('chong')) return '👨';
    if (value.includes('con')) return '👦';
    if (value.includes('cha') || value.includes('bo')) return '👴';
    if (value.includes('me')) return '👵';
    return '👥';
  }

  function isHead(row) {
    var text = normalize(relationshipText(row));
    return text === 'chu ho' || text.includes('chu ho') || text === 'head';
  }

  function householdCodeOf(row) {
    var code = row && (row.household_code || row.householdCode || row.household_id || row.householdId);
    return String(code || 'Chưa có hộ').trim() || 'Chưa có hộ';
  }

  function compareText(a, b) {
    return String(a || '').localeCompare(String(b || ''), 'vi', { numeric: true, sensitivity: 'base' });
  }

  function sortItems(items) {
    return Array.isArray(items) ? items.slice().sort(function (a, b) {
      return compareText(householdCodeOf(a), householdCodeOf(b))
        || ((isHead(a) ? 0 : 1) - (isHead(b) ? 0 : 1))
        || compareText(a && a.full_name, b && b.full_name)
        || compareText(a && a.citizen_code, b && b.citizen_code);
    }) : [];
  }

  function genderIcon(gender) {
    return normalize(gender).includes('nu')
      ? '<i class="fa-solid fa-venus population-card-icon-female"></i>'
      : '<i class="fa-solid fa-mars population-card-icon-male"></i>';
  }

  function infoBox(iconHtml, label, value) {
    if (!String(value || '').trim()) return '';
    return '<div class="population-info-box"><span class="population-card-icon">' + iconHtml + '</span><div><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong></div></div>';
  }

  function desktopCells(row, residence, party) {
    var id = Number(row && row.id) || 0;
    return '<td class="population-desktop-cell"><input type="checkbox" class="person-check" value="' + id + '"></td>'
      + '<td class="population-desktop-cell">' + escapeHtml(row.household_code || '') + '</td>'
      + '<td class="population-desktop-cell">' + escapeHtml(row.citizen_code || '') + '</td>'
      + '<td class="population-desktop-cell"><button class="btn btn-link person-name-link" onclick="showPerson(' + id + ')">' + escapeHtml(row.full_name || '') + '</button></td>'
      + '<td class="population-desktop-cell">' + formatDate(row.date_of_birth) + '</td>'
      + '<td class="population-desktop-cell">' + escapeHtml(row.gender || '') + '</td>'
      + '<td class="population-desktop-cell">' + escapeHtml(row.identity_number || row.personal_id || row.cccd || '') + '</td>'
      + '<td class="population-desktop-cell"><span class="person-badge ' + residence.badgeCls + '">' + escapeHtml(residence.text) + '</span></td>'
      + '<td class="population-desktop-cell"><span class="person-badge ' + (party ? 'person-badge-party' : 'person-badge-muted') + '">' + (party ? 'Có' : 'Không') + '</span></td>'
      + '<td class="population-desktop-cell text-end"><button class="btn btn-sm person-row-btn" onclick="showPerson(' + id + ')">Xem</button> <button class="btn btn-sm person-row-btn person-row-edit" onclick="openPersonForm(' + id + ')">Sửa</button> <button class="btn btn-sm btn-outline-danger" onclick="deletePerson(' + id + ')">Xóa</button></td>';
  }

  function mobileCard(row, residence, party) {
    var id = Number(row && row.id) || 0;
    var householdCode = row.household_code || '';
    var relation = relationshipText(row);
    var birthText = formatDate(row.date_of_birth || row.birth_date || '');
    var age = ageFromBirthday(row.date_of_birth || row.birth_date || '');
    return '<td class="population-mobile-cell" data-mobile-role="population-card" colspan="10">'
      + '<article class="population-card">'
      + '<header class="population-card-head">'
      + '<div class="population-card-title-stack">'
      + '<button type="button" class="population-card-name" onclick="showPerson(' + id + ')">' + escapeHtml(row.full_name || '') + '</button>'
      + (relation ? '<span class="population-relation-badge">' + relationshipIcon(relation) + ' ' + escapeHtml(relation) + '</span>' : '')
      + '</div>'
      + '<div class="population-card-head-actions">'
      + (householdCode ? '<span class="population-household-badge">' + escapeHtml(householdCode) + '</span>' : '')
      + '<input type="checkbox" class="person-check population-check" value="' + id + '">'
      + '</div>'
      + '</header>'
      + '<div class="population-code-grid">'
      + '<div class="population-code-box"><span>Mã nhân khẩu</span><strong>' + escapeHtml(row.citizen_code || '') + '</strong></div>'
      + '<div class="population-code-box"><span>CCCD/Số định danh</span><strong>' + escapeHtml(row.identity_number || row.personal_id || row.cccd || '') + '</strong></div>'
      + '</div>'
      + '<div class="population-info-grid">'
      + infoBox('<i class="fa-regular fa-calendar-days population-card-icon-date"></i>', 'Ngày sinh', birthText)
      + infoBox(genderIcon(row.gender || ''), 'Giới tính', row.gender || '')
      + infoBox('<i class="fa-solid fa-users population-card-icon-age"></i>', 'Tuổi', age === null ? '--' : age + ' tuổi')
      + '</div>'
      + '<div class="population-status-grid">'
      + '<div class="population-status-field"><span>Cư trú</span><em class="population-status-badge ' + residence.cls + '">' + escapeHtml(residence.text) + '</em></div>'
      + '<div class="population-status-field"><span>Đảng viên</span><em class="population-status-badge ' + (party ? 'population-status-party' : 'population-status-muted') + '">' + (party ? 'Có' : 'Không') + '</em></div>'
      + '</div>'
      + '<div class="population-action-grid">'
      + '<button type="button" class="population-action population-action-view" onclick="showPerson(' + id + ')"><i class="fa-regular fa-eye"></i><span>Xem</span></button>'
      + '<button type="button" class="population-action population-action-edit" onclick="openPersonForm(' + id + ')"><i class="fa-regular fa-pen-to-square"></i><span>Sửa</span></button>'
      + '<button type="button" class="population-action population-action-delete" onclick="deletePerson(' + id + ')"><i class="fa-regular fa-trash-can"></i><span>Xóa</span></button>'
      + '</div>'
      + '</article>'
      + '</td>';
  }

  function renderRow(row) {
    row = row || {};
    var party = Number(row.party_member || row.partyMember || 0) === 1;
    var residence = residenceInfo(row);
    return '<tr class="population-row">' + desktopCells(row, residence, party) + mobileCard(row, residence, party) + '</tr>';
  }

  function groupItems(items) {
    return sortItems(items).reduce(function (groups, row) {
      var code = householdCodeOf(row);
      var group = groups.find(function (item) { return item.code === code; });
      if (!group) { group = { code: code, rows: [] }; groups.push(group); }
      group.rows.push(row);
      return groups;
    }, []);
  }

  function householdHeadName(rows) {
    var head = (rows || []).find(isHead) || (rows || []).find(function (row) { return row && row.head_citizen_name; }) || (rows || [])[0] || {};
    return head.head_citizen_name || head.full_name || '';
  }

  function groupHeader(group) {
    var head = householdHeadName(group.rows);
    return '<tr class="group-row person-household-group-row" data-household-code="' + escapeHtml(group.code) + '">'
      + '<td colspan="11"><div class="person-household-group-card">'
      + '<div class="person-household-group-main"><span class="person-household-group-icon"><i class="fa-solid fa-house-chimney"></i></span><div><strong>Hộ ' + escapeHtml(group.code) + '</strong>'
      + (head ? '<small>Chủ hộ: ' + escapeHtml(head) + '</small>' : '')
      + '</div></div><span class="person-household-group-count">' + group.rows.length + ' nhân khẩu</span>'
      + '</div></td></tr>';
  }

  function renderGroupedRows(items) {
    return groupItems(items).map(function (group) { return groupHeader(group) + group.rows.map(renderRow).join(''); }).join('');
  }

  function emptyRow() {
    return typeof window.emptyRow === 'function' ? window.emptyRow(10, 'Không có dữ liệu') : '<tr><td colspan="10" class="text-center text-muted py-4">Không có dữ liệu</td></tr>';
  }

  async function loadGroupedPersons() {
    var app = window.App;
    if (!app || !app.persons || typeof window.api !== 'function') return;
    try {
      var searchText = typeof window.normalizeSearchText === 'function' ? window.normalizeSearchText(app.persons.search || '') : normalize(app.persons.search || '');
      var householdText = String(app.persons.householdId || '').trim();
      var items = [];
      var total = 0;
      if (searchText) {
        var extra = householdText ? { householdId: householdText } : {};
        var allItems = typeof window.fetchAllPaged === 'function'
          ? await window.fetchAllPaged('/api/persons', extra)
          : ((await window.api('/api/persons?' + new URLSearchParams(Object.assign({ page: 1, pageSize: 10000 }, extra)).toString())).items || []);
        var filtered = allItems.filter(function (row) {
          return [row.full_name, row.citizen_code, row.identity_number, row.personal_id, row.national_id, row.phone, row.household_code, row.current_address, row.household_address]
            .some(function (value) { return normalize(value).includes(searchText); });
        });
        var sorted = sortItems(filtered);
        total = sorted.length;
        var startIndex = (app.persons.page - 1) * app.persons.pageSize;
        items = sorted.slice(startIndex, startIndex + app.persons.pageSize);
      } else {
        var params = new URLSearchParams({ page: app.persons.page, pageSize: app.persons.pageSize });
        if (householdText) params.set('householdId', householdText);
        var data = await window.api('/api/persons?' + params.toString());
        items = sortItems(data.items || []);
        total = data.total || 0;
      }
      var totalHost = document.querySelector('#personTotalCount');
      if (totalHost) totalHost.innerHTML = 'Tổng số: <strong>' + (typeof window.number === 'function' ? window.number(total) : total) + '</strong> nhân khẩu';
      var rowsHost = document.querySelector('#personRows');
      if (rowsHost) rowsHost.innerHTML = renderGroupedRows(items) || emptyRow();
      if (typeof window.updateBulkDeleteButtons === 'function') window.updateBulkDeleteButtons();
      if (typeof window.renderPager === 'function') {
        window.renderPager('#personPager', { total: total, page: app.persons.page, pageSize: app.persons.pageSize }, function (page) { app.persons.page = page; loadGroupedPersons(); });
      }
    } catch (error) {
      if (typeof window.showToast === 'function') window.showToast('Không tải được danh sách nhân khẩu: ' + error.message, 'danger');
    }
  }

  function injectStyles() {
    var old = document.getElementById('person-household-group-style');
    if (old) old.remove();
    var style = document.createElement('style');
    style.id = 'person-household-group-style';
    style.textContent = [
      '@media (max-width: 767px) {',
      '  #personsScreen #personRows .person-household-group-row, #personsScreen #personRows .person-household-group-row td { display: block !important; width: 100% !important; }',
      '  #personsScreen #personRows .person-household-group-row { margin: 18px 0 10px !important; border: 0 !important; background: transparent !important; }',
      '  #personsScreen #personRows .person-household-group-row td { padding: 0 !important; border: 0 !important; background: transparent !important; }',
      '  #personsScreen #personRows .person-household-group-card { display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 9px !important; padding: 8px 11px !important; border: 1px solid #d9f1e4 !important; border-radius: 12px !important; background: #f3fbf7 !important; color: #0f5132 !important; }',
      '  #personsScreen #personRows .person-household-group-icon { width: 28px !important; height: 28px !important; border-radius: 9px !important; font-size: 13px !important; }',
      '  #personsScreen #personRows .person-household-group-count { min-height: 24px !important; padding: 3px 8px !important; font-size: 10px !important; }',
      '  #personsScreen #personRows .population-row { margin: 0 0 20px !important; }',
      '  #personsScreen #personRows .population-card { gap: 12px !important; padding: 16px !important; }',
      '  #personsScreen #personRows .population-card-head { display: grid !important; grid-template-columns: minmax(0, 1fr) auto !important; gap: 10px !important; align-items: center !important; }',
      '  #personsScreen #personRows .population-card-title-stack { min-width: 0 !important; display: grid !important; gap: 6px !important; }',
      '  #personsScreen #personRows .population-card-name { max-width: 100% !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; word-break: normal !important; overflow-wrap: normal !important; font-size: clamp(20px, 5.5vw, 25px) !important; }',
      '  #personsScreen #personRows .population-relation-badge { width: fit-content !important; max-width: 100% !important; display: inline-flex !important; align-items: center !important; gap: 5px !important; min-height: 24px !important; padding: 3px 9px !important; border-radius: 999px !important; border: 1px solid #d9eadf !important; background: #f4faf6 !important; color: #0f5132 !important; font-size: 12px !important; font-weight: 800 !important; white-space: nowrap !important; }',
      '  #personsScreen #personRows .population-card-head-actions { align-items: center !important; gap: 8px !important; }',
      '  #personsScreen #personRows .population-household-badge { min-height: 36px !important; padding: 3px 9px !important; font-size: 18px !important; line-height: 1 !important; }',
      '  #personsScreen #personRows .population-check { width: 24px !important; height: 24px !important; align-self: center !important; margin: 0 !important; }',
      '  #personsScreen #personRows .population-code-box { min-height: 54px !important; padding: 9px 11px !important; align-items: center !important; }',
      '  #personsScreen #personRows .population-info-box { gap: 14px !important; }',
      '  #personsScreen #personRows .population-card-icon { margin-right: 2px !important; }',
      '  #personsScreen #personRows .population-status-field { display: grid !important; grid-template-columns: minmax(74px, .7fr) auto !important; align-items: center !important; justify-content: normal !important; gap: 8px !important; min-height: 54px !important; padding: 8px 10px !important; }',
      '  #personsScreen #personRows .population-status-field span { white-space: nowrap !important; word-break: keep-all !important; overflow-wrap: normal !important; min-width: max-content !important; font-size: 13px !important; }',
      '  #personsScreen #personRows .population-status-badge { justify-self: center !important; min-width: 76px !important; padding: 4px 12px !important; text-align: center !important; white-space: nowrap !important; word-break: keep-all !important; }',
      '  #personsScreen #personRows .population-action { min-height: 48px !important; border-radius: 12px !important; }',
      '  #personsScreen #personRows .population-action i { font-size: 20px !important; }',
      '}',
      '@media (max-width: 380px) {',
      '  #personsScreen #personRows .population-card { padding: 14px !important; }',
      '  #personsScreen #personRows .population-household-badge { min-height: 34px !important; font-size: 16px !important; padding-inline: 8px !important; }',
      '  #personsScreen #personRows .population-status-field { grid-template-columns: minmax(68px, .66fr) auto !important; gap: 6px !important; }',
      '  #personsScreen #personRows .population-status-badge { min-width: 68px !important; padding-inline: 9px !important; font-size: 13px !important; }',
      '}',
    ].join('\n');
    document.head.appendChild(style);
  }

  window.personRow = renderRow;
  window.renderGroupedPopulationRows = renderGroupedRows;
  window.sortPopulationItemsByHousehold = sortItems;
  window.loadPersons = loadGroupedPersons;
  try { personRow = renderRow; } catch (_) {}
  try { loadPersons = loadGroupedPersons; } catch (_) {}

  function start() {
    injectStyles();
    if (window.App && window.App.screen === 'persons') setTimeout(loadGroupedPersons, 0);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
