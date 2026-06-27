var Application = Application || {};

Application.ImportService = function(importRepository, householdRepository, personRepository, logger, db) {
  function normalize(value) {
    return String(value === undefined || value === null ? '' : value).trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function text(value) {
    if (Object.prototype.toString.call(value) === '[object Date]') return Utilities.formatDate(value, Domain.App.TIMEZONE, 'yyyy-MM-dd');
    return String(value === undefined || value === null ? '' : value).trim();
  }

  function upper(value) {
    return text(value).toUpperCase();
  }

  function parseDate(value) {
    if (!value) return '';
    if (Object.prototype.toString.call(value) === '[object Date]') return Utilities.formatDate(value, Domain.App.TIMEZONE, 'yyyy-MM-dd');
    var raw = text(value);
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
    var parts = raw.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/);
    if (parts) {
      var day = ('0' + parts[1]).slice(-2);
      var month = ('0' + parts[2]).slice(-2);
      return parts[3] + '-' + month + '-' + day;
    }
    var date = new Date(raw);
    if (isNaN(date.getTime())) return '';
    return Utilities.formatDate(date, Domain.App.TIMEZONE, 'yyyy-MM-dd');
  }

  function normalizeGender(value) {
    var raw = normalize(value);
    if (raw === 'nam' || raw === 'male') return 'Nam';
    if (raw === 'nu' || raw === 'female') return 'Nu';
    if (raw === 'khac' || raw === 'other') return 'Khac';
    return text(value);
  }

  function field(row, aliases) {
    var data = row.data || {};
    var normalized = {};
    Object.keys(data).forEach(function(header) { normalized[normalize(header)] = data[header]; });
    for (var i = 0; i < aliases.length; i++) {
      var key = normalize(aliases[i]);
      if (Object.prototype.hasOwnProperty.call(normalized, key)) return normalized[key];
    }
    return '';
  }

  var householdAliases = {
    householdCode: ['Mã hộ','Ma ho','Household Code','householdCode','Ma ho gia dinh'],
    headCitizenId: ['Mã chủ hộ','Ma chu ho','Chu ho','headCitizenId'],
    address: ['Địa chỉ','Dia chi','Thôn','Thon','Hamlet','Address'],
    phone: ['Điện thoại','Dien thoai','Số điện thoại','So dien thoai','Phone'],
    areaCode: ['Mã khu vực','Ma khu vuc','Khu vực','Khu vuc','Area Code'],
    note: ['Ghi chú','Ghi chu','Note'],
    status: ['Trạng thái','Trang thai','Status']
  };

  var personAliases = {
    citizenCode: ['Mã nhân khẩu','Ma nhan khau','Citizen Code','citizenCode'],
    householdCode: ['Mã hộ','Ma ho','Household Code','householdCode'],
    fullName: ['Họ và tên','Ho va ten','Họ tên','Ho ten','Full name','fullName'],
    gender: ['Giới tính','Gioi tinh','Gender'],
    dateOfBirth: ['Ngày sinh','Ngay sinh','Date of Birth','DOB'],
    identityNumber: ['CCCD','CMND','Số CCCD','So CCCD','Số CMND','So CMND','identityNumber'],
    identityIssueDate: ['Ngày cấp CCCD','Ngay cap CCCD','Ngày cấp','Ngay cap','identityIssueDate'],
    identityIssuePlace: ['Nơi cấp CCCD','Noi cap CCCD','Nơi cấp','Noi cap','identityIssuePlace'],
    relationship: ['Quan hệ','Quan he','Relationship'],
    ethnicity: ['Dân tộc','Dan toc','Ethnicity'],
    religion: ['Tôn giáo','Ton giao','Religion'],
    occupation: ['Nghề nghiệp','Nghe nghiep','Occupation'],
    phone: ['Điện thoại','Dien thoai','Số điện thoại','So dien thoai','Phone'],
    permanentAddress: ['Thường trú','Thuong tru','Địa chỉ thường trú','Dia chi thuong tru','Permanent Address'],
    currentAddress: ['Chỗ ở hiện nay','Cho o hien nay','Địa chỉ hiện nay','Dia chi hien nay','Current Address'],
    educationLevel: ['Trình độ','Trinh do','Học vấn','Hoc van','Education'],
    maritalStatus: ['Hôn nhân','Hon nhan','Tình trạng hôn nhân','Tinh trang hon nhan','Marital Status'],
    status: ['Trạng thái','Trang thai','Status']
  };

  function source(payload) {
    payload = payload || {};
    return importRepository.readSheet(payload.spreadsheetId, payload.sheetName);
  }

  function existingHouseholdMap() {
    return db.readAll(Domain.Tables.HOUSEHOLDS, { includeDeleted: true }).reduce(function(acc, item) {
      acc[normalize(item.householdCode)] = item;
      return acc;
    }, {});
  }

  function existingIdentityMap() {
    return db.readAll(Domain.Tables.CITIZENS, { includeDeleted: true }).reduce(function(acc, item) {
      if (item.identityNumber) acc[normalize(item.identityNumber)] = item;
      return acc;
    }, {});
  }

  function makeSummary(type, sourceData, rows, errors) {
    return {
      type: type,
      spreadsheetId: sourceData.spreadsheetId,
      sheetName: sourceData.sheetName,
      totalRows: sourceData.rows.length,
      validRows: rows.length,
      errorRows: errors.length,
      errors: errors.slice(0, 200),
      headers: sourceData.headers
    };
  }

  function validateHouseholds(payload) {
    var data = source(payload);
    var mode = payload && payload.duplicateMode === 'update' ? 'update' : 'skip';
    var existing = existingHouseholdMap();
    var seen = {};
    var valid = [];
    var errors = [];
    data.rows.forEach(function(row) {
      var record = {
        householdCode: upper(field(row, householdAliases.householdCode)),
        headCitizenId: text(field(row, householdAliases.headCitizenId)),
        address: text(field(row, householdAliases.address)),
        phone: text(field(row, householdAliases.phone)),
        areaCode: upper(field(row, householdAliases.areaCode)),
        memberCount: 0,
        note: text(field(row, householdAliases.note)),
        status: upper(field(row, householdAliases.status)) || Domain.Status.ACTIVE
      };
      var rowErrors = [];
      if (!record.householdCode) rowErrors.push('Thieu Ma ho');
      if (!record.address) rowErrors.push('Thieu Dia chi/Thon');
      if (record.status && [Domain.Status.ACTIVE, Domain.Status.INACTIVE, Domain.Status.DELETED].indexOf(record.status) < 0) rowErrors.push('Trang thai ho khong hop le');
      var key = normalize(record.householdCode);
      if (key && seen[key]) rowErrors.push('Ma ho bi trung trong file import');
      if (key && existing[key] && mode !== 'update') rowErrors.push('Ma ho da ton tai');
      if (rowErrors.length) errors.push({ rowNumber: row.rowNumber, message: rowErrors.join('; ') });
      else {
        seen[key] = true;
        valid.push({ rowNumber: row.rowNumber, record: record, existing: existing[key] || null });
      }
    });
    return { sourceData: data, rows: valid, errors: errors, duplicateMode: mode };
  }

  function validatePeople(payload) {
    var data = source(payload);
    var households = existingHouseholdMap();
    var identities = existingIdentityMap();
    var seenIdentity = {};
    var valid = [];
    var errors = [];
    data.rows.forEach(function(row) {
      var householdCode = upper(field(row, personAliases.householdCode));
      var identityNumber = text(field(row, personAliases.identityNumber));
      var dateOfBirth = parseDate(field(row, personAliases.dateOfBirth));
      var fullName = text(field(row, personAliases.fullName)).replace(/\s+/g, ' ');
      var gender = normalizeGender(field(row, personAliases.gender));
      var household = households[normalize(householdCode)];
      var record = {
        citizenCode: upper(field(row, personAliases.citizenCode)) || ('NK-' + String(row.rowNumber)),
        householdId: household ? household.id : '',
        fullName: fullName,
        gender: gender,
        dateOfBirth: dateOfBirth,
        identityNumber: identityNumber,
        identityIssueDate: parseDate(field(row, personAliases.identityIssueDate)),
        identityIssuePlace: text(field(row, personAliases.identityIssuePlace)),
        relationship: text(field(row, personAliases.relationship)) || 'Khac',
        ethnicity: text(field(row, personAliases.ethnicity)),
        religion: text(field(row, personAliases.religion)),
        occupation: text(field(row, personAliases.occupation)),
        phone: text(field(row, personAliases.phone)),
        permanentAddress: text(field(row, personAliases.permanentAddress)),
        currentAddress: text(field(row, personAliases.currentAddress)),
        educationLevel: text(field(row, personAliases.educationLevel)),
        maritalStatus: text(field(row, personAliases.maritalStatus)),
        status: upper(field(row, personAliases.status)) || Domain.Status.ACTIVE
      };
      var rowErrors = [];
      if (!householdCode) rowErrors.push('Thieu Ma ho');
      if (householdCode && !household) rowErrors.push('Khong tim thay Ma ho');
      if (!record.fullName) rowErrors.push('Khong bo trong Ho ten');
      if (!record.identityNumber) rowErrors.push('Khong bo trong CCCD');
      if (record.identityNumber && !/^\d{9}$|^\d{12}$/.test(record.identityNumber)) rowErrors.push('CCCD phai gom 9 hoac 12 chu so');
      if (record.identityNumber && identities[normalize(record.identityNumber)]) rowErrors.push('CCCD da ton tai');
      if (record.identityNumber && seenIdentity[normalize(record.identityNumber)]) rowErrors.push('CCCD bi trung trong file import');
      if (!record.dateOfBirth) rowErrors.push('Ngay sinh khong hop le');
      if (['Nam','Nu','Khac'].indexOf(record.gender) < 0) rowErrors.push('Gioi tinh khong hop le');
      if (record.status && [Domain.Status.ACTIVE, Domain.Status.INACTIVE, Domain.Status.DELETED].indexOf(record.status) < 0) rowErrors.push('Trang thai nhan khau khong hop le');
      if (rowErrors.length) errors.push({ rowNumber: row.rowNumber, message: rowErrors.join('; ') });
      else {
        seenIdentity[normalize(record.identityNumber)] = true;
        valid.push({ rowNumber: row.rowNumber, record: record });
      }
    });
    return { sourceData: data, rows: valid, errors: errors };
  }

  function preview(payload) {
    var type = payload && payload.type;
    if (type === 'household') {
      var households = validateHouseholds(payload || {});
      return makeSummary('household', households.sourceData, households.rows, households.errors);
    }
    if (type === 'person') {
      var people = validatePeople(payload || {});
      return makeSummary('person', people.sourceData, people.rows, people.errors);
    }
    throw new Error('Loai import khong hop le');
  }

  function importHousehold(payload) {
    return Infrastructure.withLock(function() {
      var validation = validateHouseholds(payload || {});
      var created = [];
      var updated = [];
      validation.rows.forEach(function(item) {
        if (item.existing && validation.duplicateMode === 'update') {
          updated.push({ id: item.existing.id, record: Entity.withUpdateAudit(item.existing, item.record) });
        } else if (!item.existing) {
          created.push(Entity.withCreateAudit(Domain.Tables.HOUSEHOLDS, item.record));
        }
      });
      householdRepository.createMany(created);
      updated.forEach(function(item) { householdRepository.update(item.id, item.record); });
      var result = makeSummary('household', validation.sourceData, validation.rows, validation.errors);
      result.created = created.length;
      result.updated = updated.length;
      result.skipped = validation.rows.length - created.length - updated.length;
      result.success = created.length + updated.length;
      result.failed = validation.errors.length;
      logger.info(Domain.Modules.IMPORT, Domain.Actions.CREATE, 'household', 'Import ho gia dinh', {
        spreadsheetId: result.spreadsheetId,
        sheetName: result.sheetName,
        totalRows: result.totalRows,
        success: result.success,
        failed: result.failed,
        created: result.created,
        updated: result.updated,
        skipped: result.skipped
      });
      return result;
    });
  }

  function importPerson(payload) {
    return Infrastructure.withLock(function() {
      var validation = validatePeople(payload || {});
      var created = validation.rows.map(function(item) { return Entity.withCreateAudit(Domain.Tables.CITIZENS, item.record); });
      personRepository.createMany(created);
      var result = makeSummary('person', validation.sourceData, validation.rows, validation.errors);
      result.created = created.length;
      result.updated = 0;
      result.skipped = 0;
      result.success = created.length;
      result.failed = validation.errors.length;
      logger.info(Domain.Modules.IMPORT, Domain.Actions.CREATE, 'person', 'Import nhan khau', {
        spreadsheetId: result.spreadsheetId,
        sheetName: result.sheetName,
        totalRows: result.totalRows,
        success: result.success,
        failed: result.failed,
        created: result.created
      });
      return result;
    });
  }

  return {
    preview: preview,
    importHousehold: importHousehold,
    importPerson: importPerson
  };
};