(function () {
  'use strict';

  function normalize(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase().replace(/\s+/g, ' ').trim();
  }

  function escapeHtml(value) {
    if (typeof window.escapeHtml === 'function') return window.escapeHtml(value);
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
    });
  }

  function firstValue(row, keys) {
    row = row || {};
    for (var i = 0; i < keys.length; i += 1) {
      var value = row[keys[i]];
      if (value !== null && value !== undefined && String(value).trim() !== '') return String(value).trim();
    }
    return '';
  }

  function parseDate(value) {
    var text = String(value || '').trim();
    var match = text.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
    var day, month, year;
    if (match) { day = Number(match[1]); month = Number(match[2]); year = Number(match[3]); }
    else {
      match = text.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/);
      if (!match) return null;
      year = Number(match[1]); month = Number(match[2]); day = Number(match[3]);
    }
    var date = new Date(year, month - 1, day);
    return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day ? date : null;
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

  function householdCodeOf(row) {
    return String(row && (row.household_code || row.householdCode || row.household_id || row.householdId) || 'Chưa có hộ').trim() || 'Chưa có hộ';
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

  function residenceInfo(row) {
    if (row && row.presence_status === 'AWAY') return { text: 'Tạm vắng', cls: 'population-status-away', badgeCls: 'person-badge-away' };
    if (row && row.residency_status === 'TEMPORARY') return { text: 'Tạm trú', cls: 'population-status-temp', badgeCls: 'person-badge-temp' };
    if (row && (row.residency_status === 'MOVED' || row.life_status === 'MOVED')) return { text: 'Chuyển đi', cls: 'population-status-away', badgeCls: 'person-badge-away' };
    if (row && (row.life_status === 'DECEASED' || row.life_status === 'DEAD')) return { text: 'Đã mất', cls: 'population-status-muted', badgeCls: 'person-badge-muted' };
    var text = typeof window.residencyLabel === 'function' ? window.residencyLabel(row && row.residency_status) : 'Thường trú';
    return { text: text || 'Thường trú', cls: 'population-status-home', badgeCls: 'person-badge-home' };
  }

  function genderIcon(gender) {
    return normalize(gender).includes('nu') ? '<i class="fa-solid fa-venus population-card-icon-female"></i>' : '<i class="fa-solid fa-mars population-card-icon-male"></i>';
  }

  function detailBox(iconHtml, label, value, extraHtml, extraClass) {
    if (!String(value || '').trim()) return '';
    return '<div class="population-detail-box ' + escapeHtml(extraClass || '') + '"><span class="population-card-icon">' + iconHtml + '</span><div class="population-detail-copy"><span class="population-detail-label">' + escapeHtml(label) + '</span><strong class="population-detail-value">' + escapeHtml(value) + '</strong>' + (extraHtml || '') + '</div></div>';
  }

  function statusBox(iconHtml, label, value, statusClass) {
    if (!String(value || '').trim()) return '';
    return '<div class="population-detail-box population-status-card ' + escapeHtml(statusClass || '') + '"><span class="population-card-icon">' + iconHtml + '</span><div class="population-detail-copy"><span class="population-detail-label">' + escapeHtml(label) + '</span><em class="population-status-pill ' + escapeHtml(statusClass || '') + '">' + escapeHtml(value) + '</em></div></div>';
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
    var birthValue = row.date_of_birth || row.birth_date || '';
    var birthText = formatDate(birthValue);
    var age = ageFromBirthday(birthValue);
    var relation = relationshipText(row);
    var ageHtml = age === null ? '' : '<small class="population-birth-age">' + age + ' tuổi</small>';
    var ethnicity = firstValue(row, ['ethnicity', 'ethnicity_name', 'ethnic_group', 'ethnicGroup', 'nation', 'nation_name']);
    var identity = row.identity_number || row.personal_id || row.cccd || '';
    return '<td class="population-mobile-cell" data-mobile-role="population-card" colspan="10">'
      + '<article class="population-card">'
      + '<header class="population-card-head"><div class="population-card-title-stack">'
      + '<button type="button" class="population-card-name" onclick="showPerson(' + id + ')">' + escapeHtml(row.full_name || '') + '</button>'
      + (relation ? '<span class="population-relation-badge">' + relationshipIcon(relation) + ' ' + escapeHtml(relation) + '</span>' : '')
      + '</div><div class="population-card-head-actions">'
      + (row.household_code ? '<span class="population-household-badge">' + escapeHtml(row.household_code) + '</span>' : '')
      + '<input type="checkbox" class="person-check population-check" value="' + id + '">'
      + '</div></header>'
      + '<div class="population-code-grid"><div class="population-code-box"><span>Mã nhân khẩu</span><strong>' + escapeHtml(row.citizen_code || '') + '</strong></div><div class="population-code-box"><span>CCCD/Số định danh</span><strong>' + escapeHtml(identity) + '</strong></div></div>'
      + '<div class="population-bio-grid">' + detailBox('<i class="fa-regular fa-calendar-days population-card-icon-date"></i>', 'Ngày sinh', birthText, ageHtml, 'population-birth-box') + detailBox(genderIcon(row.gender || ''), 'Giới tính', row.gender || '', '', 'population-gender-box') + '</div>'
      + '<div class="population-context-grid">' + detailBox('<i class="fa-solid fa-users population-card-icon-age"></i>', 'Dân tộc', ethnicity, '', 'population-ethnicity-box') + detailBox('<i class="fa-solid fa-users population-card-icon-age"></i>', 'Đảng viên', party ? 'Có' : 'Không', '', party ? 'population-party-yes' : 'population-party-no') + '</div>'
      + '<div class="population-admin-grid">' + statusBox('<i class="fa-solid fa-house-chimney population-card-icon-home"></i>', 'Cư trú', residence.text, 'population-residence-box ' + residence.cls) + '</div>'
      + '<div class="population-action-grid"><button type="button" class="population-action population-action-view" onclick="showPerson(' + id + ')"><i class="fa-regular fa-eye"></i><span>Xem</span></button><button type="button" class="population-action population-action-edit" onclick="openPersonForm(' + id + ')"><i class="fa-regular fa-pen-to-square"></i><span>Sửa</span></button><button type="button" class="population-action population-action-delete" onclick="deletePerson(' + id + ')"><i class="fa-regular fa-trash-can"></i><span>Xóa</span></button></div>'
      + '</article></td>';
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
    return '<tr class="group-row person-household-group-row" data-household-code="' + escapeHtml(group.code) + '"><td colspan="11"><div class="person-household-group-card"><div class="person-household-group-main"><span class="person-household-group-icon"><i class="fa-solid fa-house-chimney"></i></span><div><strong>Hộ ' + escapeHtml(group.code) + '</strong>' + (head ? '<small>Chủ hộ: ' + escapeHtml(head) + '</small>' : '') + '</div></div><span class="person-household-group-count">' + group.rows.length + ' nhân khẩu</span></div></td></tr>';
  }

  function renderGroupedRows(items) {
    return groupItems(items).map(function (group) { return groupHeader(group) + group.rows.map(renderRow).join(''); }).join('');
  }

  function emptyRow() {
    return typeof window.emptyRow === 'function' ? window.emptyRow(10, 'Không có dữ liệu') : '<tr><td colspan="10" class="text-center text-muted py-4">Không có dữ liệu</td></tr>';
  }

  function fitPopulationNames() {
    if (window.innerWidth >= 1200) return;
    var names = document.querySelectorAll('#personsScreen #personRows .population-card-name');
    names.forEach(function (name) {
      name.style.fontSize = '';
      var baseSize = parseFloat(window.getComputedStyle(name).fontSize) || 22;
      var minSize = window.innerWidth < 360 ? 11 : (window.innerWidth < 480 ? 12 : 14);
      var size = Math.min(28, baseSize);
      name.style.fontSize = size + 'px';
      var guard = 0;
      while (name.scrollWidth > name.clientWidth + 1 && size > minSize && guard < 40) {
        size -= 0.5;
        name.style.fontSize = size + 'px';
        guard += 1;
      }
    });
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
        var allItems = typeof window.fetchAllPaged === 'function' ? await window.fetchAllPaged('/api/persons', extra) : ((await window.api('/api/persons?' + new URLSearchParams(Object.assign({ page: 1, pageSize: 10000 }, extra)).toString())).items || []);
        var filtered = allItems.filter(function (row) {
          return [row.full_name, row.citizen_code, row.identity_number, row.personal_id, row.national_id, row.phone, row.household_code, row.current_address, row.household_address].some(function (value) { return normalize(value).includes(searchText); });
        });
        var sorted = sortItems(filtered);
        total = sorted.length;
        items = sorted.slice((app.persons.page - 1) * app.persons.pageSize, (app.persons.page - 1) * app.persons.pageSize + app.persons.pageSize);
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
      fitPopulationNames();
      if (typeof window.updateBulkDeleteButtons === 'function') window.updateBulkDeleteButtons();
      if (typeof window.renderPager === 'function') window.renderPager('#personPager', { total: total, page: app.persons.page, pageSize: app.persons.pageSize }, function (page) { app.persons.page = page; loadGroupedPersons(); });
    } catch (error) {
      if (typeof window.showToast === 'function') window.showToast('Không tải được danh sách nhân khẩu: ' + error.message, 'danger');
    }
  }

  function injectStyles() {
    var old = document.getElementById('person-household-group-style');
    if (old) old.remove();
    var style = document.createElement('style');
    style.id = 'person-household-group-style';
    style.textContent = `
@media (max-width:1199px){
  html,body{max-width:100%!important;overflow-x:hidden!important}*,*::before,*::after{box-sizing:border-box}.screen,.dashboard-overview-screen,.person-management-screen,.report-screen{padding:clamp(14px,3vw,22px)!important}.form-control,.form-select,.btn{min-height:clamp(42px,6vw,48px)!important}.modal-dialog{width:min(95vw,860px)!important;max-width:95vw!important;margin:14px auto!important}.modal-content{max-height:90vh!important;overflow:hidden!important;border-radius:clamp(14px,2.2vw,18px)!important}.modal-body{overflow:auto!important}
  .table-responsive.module-card-list:not(.person-table-wrap){overflow:visible!important}.table-responsive.module-card-list:not(.person-table-wrap) table,.table-responsive.module-card-list:not(.person-table-wrap) tbody{display:block!important;width:100%!important;min-width:0!important}.table-responsive.module-card-list:not(.person-table-wrap) thead{display:none!important}.table-responsive.module-card-list:not(.person-table-wrap) tbody{display:grid!important;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr))!important;gap:clamp(12px,2vw,18px)!important}.table-responsive.module-card-list:not(.person-table-wrap) tbody tr:not(.group-row){display:grid!important;gap:8px!important;padding:clamp(12px,2vw,16px)!important;border:1px solid #dfe7e2!important;border-radius:14px!important;background:#fff!important;box-shadow:0 8px 20px rgba(15,23,42,.055)!important;max-width:100%!important;overflow:hidden!important}.table-responsive.module-card-list:not(.person-table-wrap) tbody td{display:grid!important;grid-template-columns:minmax(88px,.8fr) minmax(0,1.2fr)!important;align-items:center!important;gap:8px!important;width:100%!important;padding:0!important;border:0!important;background:transparent!important;font-size:clamp(12px,2.2vw,14px)!important}.table-responsive.module-card-list:not(.person-table-wrap) tbody td::before{content:attr(data-label);color:#667085;font-size:clamp(11px,2vw,13px);font-weight:800;line-height:1.25}.table-responsive.module-card-list:not(.person-table-wrap) tbody td[data-mobile-role="actions"]{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:8px!important;margin-top:6px!important}.table-responsive.module-card-list:not(.person-table-wrap) tbody td[data-mobile-role="actions"]::before{display:none!important}.table-responsive.module-card-list:not(.person-table-wrap) tbody td[data-mobile-role="actions"] .btn{width:100%!important;min-height:44px!important;border-radius:12px!important}
  #personsScreen #personRows,#personsScreen .person-table-wrap,#personsScreen .person-table-wrap table,#personsScreen .person-table-wrap tbody{display:block!important;width:100%!important;min-width:0!important}#personsScreen .person-table-wrap{overflow:visible!important}#personsScreen .person-table-wrap thead,#personsScreen #personRows .population-desktop-cell{display:none!important}#personsScreen #personRows .person-household-group-row,#personsScreen #personRows .person-household-group-row td,#personsScreen #personRows .population-row,#personsScreen #personRows .population-mobile-cell{display:block!important;width:100%!important}#personsScreen #personRows .person-household-group-row{margin:clamp(14px,2.2vw,20px) 0 10px!important;border:0!important;background:transparent!important}#personsScreen #personRows .person-household-group-row td,#personsScreen #personRows .population-mobile-cell{padding:0!important;border:0!important;background:transparent!important}
  #personsScreen #personRows .person-household-group-card{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:clamp(8px,1.8vw,12px)!important;padding:clamp(8px,2vw,12px) clamp(10px,2.4vw,14px)!important;border:1px solid #d9f1e4!important;border-radius:12px!important;background:#f3fbf7!important;color:#0f5132!important}#personsScreen #personRows .person-household-group-icon{width:28px!important;height:28px!important;border-radius:9px!important;font-size:13px!important}#personsScreen #personRows .person-household-group-count{min-height:24px!important;padding:3px 8px!important;font-size:clamp(10px,1.8vw,11px)!important;white-space:nowrap!important}
  #personsScreen #personRows .population-row{margin:0 0 clamp(18px,3vw,20px)!important;border:0!important;background:transparent!important}#personsScreen #personRows .population-card,#personsScreen #personRows .population-card *{box-sizing:border-box!important}#personsScreen #personRows .population-card{display:grid!important;width:100%!important;max-width:100%!important;overflow:hidden!important;gap:clamp(8px,1.9vw,12px)!important;padding:clamp(12px,3vw,18px)!important;border:1px solid #dfe7e2!important;border-radius:18px!important;background:#fff!important;box-shadow:0 10px 24px rgba(15,23,42,.06)!important}
  #personsScreen #personRows .population-card-head{display:grid!important;grid-template-columns:minmax(0,58fr) minmax(0,42fr)!important;align-items:center!important;gap:clamp(6px,1.8vw,10px)!important;padding-bottom:8px!important;border-bottom:1px solid #edf2ef!important}#personsScreen #personRows .population-card-title-stack{min-width:0!important;width:100%!important;display:grid!important;gap:6px!important;overflow:hidden!important}#personsScreen #personRows .population-card-name{display:block!important;width:100%!important;max-width:100%!important;min-width:0!important;text-align:left!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:clip!important;word-break:normal!important;overflow-wrap:normal!important;font-size:clamp(14px,4.8vw,28px)!important;line-height:1.18!important;letter-spacing:0!important}#personsScreen #personRows .population-relation-badge{width:fit-content!important;max-width:100%!important;display:inline-flex!important;align-items:center!important;gap:5px!important;min-height:24px!important;padding:3px 9px!important;border-radius:999px!important;border:1px solid #d9eadf!important;background:#f4faf6!important;color:#0f5132!important;font-size:clamp(11px,2.4vw,12px)!important;font-weight:800!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
  #personsScreen #personRows .population-card-head-actions{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;align-items:center!important;justify-content:end!important;gap:clamp(6px,1.6vw,8px)!important;min-width:0!important;overflow:hidden!important}#personsScreen #personRows .population-household-badge{justify-self:end!important;width:auto!important;max-width:100%!important;min-width:0!important;height:clamp(32px,7vw,38px)!important;min-height:clamp(32px,7vw,38px)!important;padding:0 clamp(7px,2vw,10px)!important;font-size:clamp(14px,4vw,18px)!important;line-height:1!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}#personsScreen #personRows .population-check{align-self:center!important;justify-self:end!important;width:clamp(20px,5vw,24px)!important;height:clamp(20px,5vw,24px)!important;margin:0!important;flex:0 0 auto!important}
  #personsScreen #personRows .population-code-grid,#personsScreen #personRows .population-bio-grid,#personsScreen #personRows .population-context-grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:clamp(7px,1.8vw,10px)!important;width:100%!important;overflow:hidden!important}#personsScreen #personRows .population-code-box{min-width:0!important;width:100%!important;min-height:clamp(50px,12vw,62px)!important;padding:clamp(7px,2vw,10px)!important;display:grid!important;grid-template-columns:1fr!important;align-content:center!important;gap:3px!important;overflow:hidden!important}#personsScreen #personRows .population-code-box span{min-width:0!important;white-space:normal!important;overflow:visible!important;text-overflow:clip!important;line-height:1.15!important;font-size:clamp(10px,2.7vw,13px)!important;color:#667085!important;font-weight:800!important}#personsScreen #personRows .population-code-box strong{min-width:0!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;line-height:1.15!important;font-size:clamp(13px,3.8vw,18px)!important;color:#183b32!important}
  #personsScreen #personRows .population-detail-box{min-width:0!important;width:100%!important;min-height:clamp(66px,16vw,82px)!important;padding:clamp(8px,2vw,11px)!important;display:grid!important;grid-template-columns:auto minmax(0,1fr)!important;align-items:center!important;gap:clamp(8px,1.8vw,12px)!important;overflow:hidden!important;border:1px solid #edf1ef!important;border-radius:14px!important;background:#fff!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.65)!important}#personsScreen #personRows .population-detail-copy{min-width:0!important;display:grid!important;gap:2px!important;overflow:hidden!important}#personsScreen #personRows .population-detail-label,#personsScreen #personRows .population-detail-value,#personsScreen #personRows .population-birth-age{display:block!important;min-width:0!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;word-break:keep-all!important;overflow-wrap:normal!important;line-height:1.18!important}#personsScreen #personRows .population-detail-label{font-size:clamp(11px,2.8vw,14px)!important;color:#667085!important;font-weight:800!important}#personsScreen #personRows .population-detail-value{font-size:clamp(14px,3.9vw,19px)!important;color:#111827!important;font-weight:800!important}#personsScreen #personRows .population-birth-age{font-size:clamp(11px,2.9vw,14px)!important;color:#0f5132!important;font-weight:800!important}.population-card-icon{flex:0 0 auto!important;font-size:clamp(18px,4.4vw,23px)!important;margin:0!important}.population-card-icon-date,.population-card-icon-home{color:#0a8f4d!important}.population-card-icon-age{color:#d97706!important}.population-card-icon-female{color:#e11d48!important}.population-card-icon-male{color:#2563eb!important}
  #personsScreen #personRows .population-admin-grid{display:grid!important;grid-template-columns:1fr!important;gap:clamp(7px,1.8vw,10px)!important;width:100%!important;overflow:hidden!important}#personsScreen #personRows .population-admin-grid .population-status-card{min-height:clamp(54px,12vw,66px)!important;background:#f8fbf9!important}.population-residence-box.population-status-home,.population-party-yes{background:#eefaf3!important;border-color:#bfead2!important}.population-residence-box.population-status-temp{background:#fff8e8!important;border-color:#fde1a6!important}.population-residence-box.population-status-away{background:#fff4e6!important;border-color:#fed7aa!important}.population-party-no{background:#f8fafc!important;border-color:#e5e7eb!important}.population-status-pill{display:inline-flex!important;align-items:center!important;justify-content:center!important;width:fit-content!important;max-width:100%!important;min-height:28px!important;padding:5px 14px!important;border-radius:999px!important;font-style:normal!important;font-weight:800!important;font-size:clamp(12px,3vw,15px)!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}.population-status-pill.population-status-home{background:#dcfce7!important;color:#15803d!important;border:1px solid #bbf7d0!important}.population-status-pill.population-status-temp{background:#fef3c7!important;color:#b45309!important;border:1px solid #fde68a!important}.population-status-pill.population-status-away{background:#ffedd5!important;color:#c2410c!important;border:1px solid #fed7aa!important}.population-status-pill.population-status-muted{background:#f1f5f9!important;color:#475569!important;border:1px solid #e2e8f0!important}
  #personsScreen #personRows .population-action-grid{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:clamp(7px,1.8vw,10px)!important;width:100%!important}#personsScreen #personRows .population-action{width:100%!important;min-width:0!important;height:clamp(44px,10vw,48px)!important;min-height:clamp(44px,10vw,48px)!important;padding:0 clamp(5px,1.6vw,8px)!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:clamp(5px,1.4vw,8px)!important;overflow:hidden!important;border-radius:12px!important}#personsScreen #personRows .population-action span{white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;font-size:clamp(12px,3vw,15px)!important}#personsScreen #personRows .population-action i{flex:0 0 auto!important;font-size:clamp(16px,4vw,20px)!important}
}
@media (max-width:479px){#personsScreen #personRows .population-card-head{grid-template-columns:minmax(0,60fr) minmax(0,40fr)!important}#personsScreen #personRows .population-code-box{min-height:clamp(48px,13vw,58px)!important;padding:6px!important}#personsScreen #personRows .population-code-box strong{font-size:clamp(10px,3.25vw,14px)!important;letter-spacing:-.2px!important}#personsScreen #personRows .population-detail-box{grid-template-columns:minmax(0,1fr)!important;min-height:clamp(58px,16vw,72px)!important;padding:clamp(6px,1.7vw,8px)!important;gap:3px!important}.population-card-icon{display:none!important}#personsScreen #personRows .population-detail-label{font-size:clamp(9px,2.7vw,12px)!important}#personsScreen #personRows .population-detail-value{font-size:clamp(10px,3.25vw,14px)!important;letter-spacing:-.15px!important}#personsScreen #personRows .population-birth-age{font-size:clamp(9px,2.8vw,12px)!important}.population-status-pill{min-height:24px!important;padding:4px 10px!important;font-size:clamp(10px,3vw,13px)!important}}
@media (min-width:768px) and (max-width:1199px){#personsScreen #personRows{display:grid!important;grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr))!important;gap:0 16px!important}#personsScreen #personRows .person-household-group-row{grid-column:1/-1!important}#personsScreen #personRows .population-row{min-width:0!important}#personsScreen #personRows .population-card{height:100%!important}}
@media (min-width:1200px){#personsScreen #personRows .population-mobile-cell{display:none!important}#personsScreen #personRows .population-desktop-cell{display:table-cell!important}}
    `;
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
    if (!window.__thon09PopulationNameFitBound) {
      window.__thon09PopulationNameFitBound = true;
      window.addEventListener('resize', function () { window.requestAnimationFrame(fitPopulationNames); });
    }
    if (window.App && window.App.screen === 'persons') setTimeout(loadGroupedPersons, 0);
    setTimeout(fitPopulationNames, 80);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
