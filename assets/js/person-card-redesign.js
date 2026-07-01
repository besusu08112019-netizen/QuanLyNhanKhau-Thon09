(function () {
  function html(value) {
    if (typeof escapeHtml === 'function') return escapeHtml(value);
    return String(value ?? '').replace(/[&<>'"]/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char];
    });
  }

  function dateText(value) {
    if (typeof formatDate === 'function') return formatDate(value) || '--';
    if (!value) return '--';
    var parts = String(value).split('-');
    return parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : String(value);
  }

  function ageFromBirthday(value) {
    if (!value) return null;
    var parts = String(value).slice(0, 10).split('-').map(Number);
    if (parts.length < 3 || !parts[0] || !parts[1] || !parts[2]) return null;
    var today = new Date();
    var age = today.getFullYear() - parts[0];
    var monthDelta = today.getMonth() + 1 - parts[1];
    if (monthDelta < 0 || (monthDelta === 0 && today.getDate() < parts[2])) age -= 1;
    return age >= 0 ? age : null;
  }

  function maskIdentity(value) {
    var text = String(value || '').replace(/\s+/g, '');
    if (!text) return '--';
    if (text.length <= 8) return text;
    return text.slice(0, 4) + '••••' + text.slice(-4);
  }

  function residenceInfo(row) {
    var presence = String(row.presence_status || row.presenceStatus || '').toUpperCase();
    var residency = String(row.residency_status || row.residencyStatus || '').toUpperCase();
    if (presence === 'AWAY') return { text: 'Tạm vắng', tone: 'away' };
    if (residency === 'TEMPORARY') return { text: 'Tạm trú', tone: 'temp' };
    return { text: typeof residencyLabel === 'function' ? residencyLabel(row.residency_status || row.residencyStatus || 'PERMANENT') : 'Thường trú', tone: 'home' };
  }

  function genderIcon(gender) {
    var text = String(gender || '').toLowerCase();
    if (text.includes('nữ') || text.includes('nu')) return '<i class="fa-solid fa-venus population-card-icon population-card-icon-female"></i>';
    if (text.includes('nam')) return '<i class="fa-solid fa-mars population-card-icon population-card-icon-male"></i>';
    return '<i class="fa-solid fa-user population-card-icon"></i>';
  }

  function infoBox(icon, label, value) {
    if (value === null || value === undefined || value === '') return '';
    return '<div class="population-info-box">'
      + icon
      + '<div><span>' + html(label) + '</span><strong>' + html(value) + '</strong></div>'
      + '</div>';
  }

  function badge(label, value, cls) {
    return '<div class="population-status-field"><span>' + html(label) + '</span><em class="population-status-badge ' + cls + '">' + html(value) + '</em></div>';
  }

  window.ageFromDate = ageFromBirthday;
  window.personRow = function personRow(row) {
    var id = Number(row.id || 0);
    var party = Number(row.party_member || row.partyMember || 0) === 1;
    var residence = residenceInfo(row);
    var age = ageFromBirthday(row.date_of_birth || row.dateOfBirth);
    var ageLabel = age === null ? '--' : age + ' tuổi';
    var householdCode = row.household_code || row.householdCode || '';
    var citizenCode = row.citizen_code || row.citizenCode || '';
    var identity = row.identity_number || row.identityNumber || row.personal_id || row.national_id || '';
    var fullName = row.full_name || row.fullName || '';
    var gender = row.gender || '';
    var birth = row.date_of_birth || row.dateOfBirth || '';
    return '<tr class="population-row">'
      + '<td data-mobile-role="population-card" colspan="10">'
      + '<article class="population-card">'
      + '<div class="population-card-head">'
      + '<button class="population-card-name" type="button" onclick="showPerson(' + id + ')">' + html(fullName) + '</button>'
      + '<div class="population-card-head-actions"><span class="population-household-badge">' + html(householdCode || '--') + '</span><input type="checkbox" class="person-check population-check" value="' + id + '"></div>'
      + '</div>'
      + '<div class="population-code-grid">'
      + '<div class="population-code-box"><span>Mã nhân khẩu</span><strong>' + html(citizenCode || '--') + '</strong></div>'
      + '<div class="population-code-box"><span>CCCD/Số định danh</span><strong>' + html(maskIdentity(identity)) + '</strong></div>'
      + '</div>'
      + '<div class="population-info-grid">'
      + infoBox('<i class="fa-regular fa-calendar-days population-card-icon population-card-icon-date"></i>', 'Ngày sinh', dateText(birth))
      + infoBox(genderIcon(gender), 'Giới tính', gender || '--')
      + infoBox('<i class="fa-solid fa-users population-card-icon population-card-icon-age"></i>', 'Tuổi', ageLabel)
      + '</div>'
      + '<div class="population-status-grid">'
      + badge('Cư trú', residence.text, 'population-status-' + residence.tone)
      + badge('Đảng viên', party ? 'Có' : 'Không', party ? 'population-status-party' : 'population-status-muted')
      + '</div>'
      + '<div class="population-action-grid">'
      + '<button class="btn population-action population-action-view" type="button" onclick="showPerson(' + id + ')"><i class="fa-regular fa-eye"></i> Xem</button>'
      + '<button class="btn population-action population-action-edit" type="button" onclick="openPersonForm(' + id + ')"><i class="fa-solid fa-pen"></i> Sửa</button>'
      + '<button class="btn population-action population-action-delete" type="button" onclick="deletePerson(' + id + ')"><i class="fa-regular fa-trash-can"></i> Xóa</button>'
      + '</div>'
      + '</article>'
      + '</td>'
      + '</tr>';
  };
})();
