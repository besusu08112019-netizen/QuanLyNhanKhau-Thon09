var Application = Application || {};

Application.ReportService = function(db, logger) {
  function normalizeText(value) {
    return String(value || '').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function parseDate(value) {
    if (!value) return null;
    if (Object.prototype.toString.call(value) === '[object Date]') return value;
    var date = new Date(value);
    return isNaN(date.getTime()) ? null : date;
  }

  function statusLabel(value) {
    if (value === Domain.Status.ACTIVE) return 'Đang hoạt động';
    if (value === Domain.Status.INACTIVE) return 'Tạm ngưng';
    if (value === Domain.Status.DELETED) return 'Đã xóa';
    return value || '';
  }

  function isYes(value) {
    var text = normalizeText(value);
    return value === true || value === 1 || text === 'co' || text === 'yes' || text === 'true' || text === '1' || text === 'x';
  }

  function pick() {
    for (var i = 0; i < arguments.length; i += 1) {
      if (arguments[i] !== undefined && arguments[i] !== null && arguments[i] !== '') return arguments[i];
    }
    return '';
  }

  function policyValue(household, canonicalName, legacyName) {
    return pick(household && household[canonicalName], household && household[legacyName]);
  }

  function policySummary(household) {
    var items = [];
    if (isYes(policyValue(household, 'meritoriousFamily', 'isPolicyFamily'))) items.push('Gia đình có công');
    if (isYes(policyValue(household, 'poorHousehold', 'isPoorHousehold'))) items.push('Hộ nghèo');
    if (isYes(policyValue(household, 'nearPoorHousehold', 'isNearPoorHousehold'))) items.push('Hộ cận nghèo');
    if (isYes(policyValue(household, 'disabledHousehold', 'hasDisabledMember'))) items.push('Tàn tật');
    return items.join(', ') || 'Không';
  }

  function inRange(record, filters) {
    if (!filters || (!filters.fromDate && !filters.toDate)) return true;
    var date = parseDate(record.effectiveDate || record.updatedAt || record.createdAt || record.dateOfBirth);
    if (!date) return false;
    var from = parseDate(filters.fromDate);
    var to = parseDate(filters.toDate);
    if (from && date < from) return false;
    if (to) {
      to.setHours(23, 59, 59, 999);
      if (date > to) return false;
    }
    return true;
  }

  function genderLabel(value) {
    var text = normalizeText(value);
    if (text === 'nam' || text === 'male') return 'Nam';
    if (text === 'nu' || text === 'female') return 'Nữ';
    return 'Khác';
  }

  function residencyLabel(person) {
    var values = [person.residencyStatus, person.movementType, person.note, person.relationship].map(normalizeText).join(' ');
    if (values.indexOf('tam tru') >= 0 || values.indexOf('temporary residence') >= 0) return 'Tạm trú';
    if (values.indexOf('tam vang') >= 0 || values.indexOf('temporary absence') >= 0) return 'Tạm vắng';
    return 'Thường trú';
  }

  function ageOf(person) {
    var date = parseDate(person.dateOfBirth);
    if (!date) return null;
    var now = new Date();
    var age = now.getFullYear() - date.getFullYear();
    var monthDelta = now.getMonth() - date.getMonth();
    if (monthDelta < 0 || (monthDelta === 0 && now.getDate() < date.getDate())) age -= 1;
    return age < 0 || age > 130 ? null : age;
  }

  function ageBucket(person) {
    var age = ageOf(person);
    if (age === null) return 'Không rõ';
    if (age <= 5) return '0-5';
    if (age <= 17) return '6-17';
    if (age <= 35) return '18-35';
    if (age <= 59) return '36-59';
    return '60+';
  }

  function movementLabel(value) {
    var text = normalizeText(value);
    if (text.indexOf('sinh') >= 0 || text.indexOf('birth') >= 0) return 'Sinh';
    if (text.indexOf('tu') >= 0 || text.indexOf('death') >= 0) return 'Tử';
    if (text.indexOf('den') >= 0 || text.indexOf('in') >= 0) return 'Chuyển đến';
    if (text.indexOf('di') >= 0 || text.indexOf('out') >= 0) return 'Chuyển đi';
    return value || 'Khác';
  }

  function loadData(filters) {
    filters = filters || {};
    var households = db.readAll(Domain.Tables.HOUSEHOLDS).filter(function(item) {
      if (filters.householdStatus && item.status !== filters.householdStatus) return false;
      return inRange(item, filters);
    });
    var citizens = db.readAll(Domain.Tables.CITIZENS).filter(function(item) {
      if (filters.personStatus && item.status !== filters.personStatus) return false;
      if (filters.residencyStatus && residencyLabel(item) !== filters.residencyStatus) return false;
      return inRange(item, filters);
    });
    var movements = db.readAll(Domain.Tables.MOVEMENTS).filter(function(item) {
      if (filters.movementType && movementLabel(item.type) !== filters.movementType) return false;
      return inRange(item, filters);
    });
    return { households: households, citizens: citizens, movements: movements };
  }

  function countBy(items, resolver, order) {
    var counts = items.reduce(function(acc, item) {
      var key = resolver(item);
      acc[key] = (acc[key] || 0) + 1;
      return acc;
    }, {});
    var keys = order || Object.keys(counts).sort();
    return keys.map(function(key) { return { label: key, value: counts[key] || 0 }; });
  }

  function findHousehold(data, key) {
    key = normalizeText(key);
    return data.households.filter(function(item) {
      return normalizeText(item.id) === key || normalizeText(item.householdCode) === key;
    })[0] || null;
  }

  function householdKeys(household) {
    return [household && household.id, household && household.householdCode].map(normalizeText).filter(Boolean);
  }

  function personInHousehold(person, household) {
    var keys = householdKeys(household);
    return keys.indexOf(normalizeText(person.householdId)) >= 0 || keys.indexOf(normalizeText(person.householdCode)) >= 0;
  }

  function personHouseholdCode(data, person) {
    var household = findHousehold(data, person.householdId || person.householdCode);
    return household ? household.householdCode : (person.householdCode || person.householdId || '');
  }

  function findPerson(data, key) {
    key = normalizeText(key);
    return data.citizens.filter(function(item) {
      return normalizeText(item.id) === key || normalizeText(item.citizenCode) === key || normalizeText(item.identityNumber) === key;
    })[0] || null;
  }

  function householdHeadName(household, citizens) {
    if (household.headCitizenName) return household.headCitizenName;
    var key = normalizeText(household.headCitizenId);
    if (!key) return '';
    var person = (citizens || []).filter(function(item) {
      return normalizeText(item.id) === key || normalizeText(item.citizenCode) === key || normalizeText(item.identityNumber) === key;
    })[0];
    return person ? person.fullName : '';
  }

  function householdMemberCount(data, household) {
    return data.citizens.filter(function(person) { return personInHousehold(person, household); }).length;
  }

  function householdPolicyRows(data, matcher) {
    return data.households.filter(matcher).map(function(item) {
      return [item.householdCode, householdHeadName(item, data.citizens), item.address, item.phone, item.areaCode, householdMemberCount(data, item), policySummary(item), statusLabel(item.status)];
    });
  }

  function makeReport(type, filters) {
    filters = filters || {};
    type = type || 'summary';
    var data = loadData(filters);
    var dashboard = Application.DashboardService(db).summary({
      fromDate: filters.fromDate,
      toDate: filters.toDate,
      householdStatus: filters.householdStatus,
      personStatus: filters.personStatus
    });
    var report = {
      id: Entity.uuid('RPT'),
      type: type,
      title: '',
      generatedAt: Entity.now(),
      generatedBy: Entity.currentEmail(),
      filters: filters,
      columns: [],
      rows: [],
      summary: dashboard.metrics,
      charts: dashboard.charts
    };
    if (type === 'householdForm') {
      report.title = 'Phiếu thông tin hộ gia đình';
      report.columns = ['Thông tin', 'Giá trị'];
      var household = findHousehold(data, filters.recordId);
      if (household) {
        var members = data.citizens.filter(function(person) { return personInHousehold(person, household); });
        report.rows = [
          ['Mã hộ', household.householdCode], ['Chủ hộ', householdHeadName(household, data.citizens)], ['Địa chỉ/Thôn', household.address], ['Điện thoại', household.phone], ['Khu vực', household.areaCode], ['Diện hộ', policySummary(household)], ['Trạng thái', statusLabel(household.status)], ['Số nhân khẩu', members.length]
        ];
        members.forEach(function(person) { report.rows.push(['Thành viên', person.fullName + ' - ' + person.relationship + ' - ' + statusLabel(person.status)]); });
      }
      return report;
    }
    if (type === 'personForm') {
      report.title = 'Phiếu thông tin nhân khẩu';
      report.columns = ['Thông tin', 'Giá trị'];
      var person = findPerson(data, filters.recordId);
      if (person) {
        report.rows = [
          ['Mã nhân khẩu', person.citizenCode], ['Họ tên', person.fullName], ['Giới tính', genderLabel(person.gender)], ['Ngày sinh', person.dateOfBirth], ['CCCD/CMND', person.identityNumber], ['Mã hộ', personHouseholdCode(data, person)], ['Quan hệ', person.relationship], ['Điện thoại', person.phone], ['Thường trú', person.permanentAddress], ['Nơi ở hiện nay', person.currentAddress], ['Trạng thái', statusLabel(person.status)]
        ];
      }
      return report;
    }
    if (type === 'household') {
      report.title = 'Báo cáo danh sách hộ dân';
      report.columns = ['Mã hộ', 'Chủ hộ', 'Địa chỉ/Thôn', 'Điện thoại', 'Khu vực', 'Diện hộ', 'Trạng thái'];
      report.rows = data.households.map(function(item) { return [item.householdCode, householdHeadName(item, data.citizens), item.address, item.phone, item.areaCode, policySummary(item), statusLabel(item.status)]; });
      return report;
    }
    if (type === 'policyMeritorious' || type === 'policyPoor' || type === 'policyNearPoor' || type === 'policyDisabled') {
      var config = {
        policyMeritorious: { title: 'Danh sách gia đình/người có công', canonical: 'meritoriousFamily', legacy: 'isPolicyFamily' },
        policyPoor: { title: 'Danh sách hộ nghèo', canonical: 'poorHousehold', legacy: 'isPoorHousehold' },
        policyNearPoor: { title: 'Danh sách hộ cận nghèo', canonical: 'nearPoorHousehold', legacy: 'isNearPoorHousehold' },
        policyDisabled: { title: 'Danh sách hộ có người tàn tật', canonical: 'disabledHousehold', legacy: 'hasDisabledMember' }
      }[type];
      report.title = config.title;
      report.columns = ['Mã hộ', 'Chủ hộ', 'Địa chỉ/Thôn', 'Điện thoại', 'Khu vực', 'Số nhân khẩu', 'Diện hộ', 'Trạng thái'];
      report.rows = householdPolicyRows(data, function(item) { return isYes(policyValue(item, config.canonical, config.legacy)); });
      report.summary.policyCount = report.rows.length;
      return report;
    }
    if (type === 'population') {
      report.title = 'Báo cáo danh sách nhân khẩu';
      report.columns = ['Mã nhân khẩu', 'Họ tên', 'Giới tính', 'Ngày sinh', 'CCCD/CMND', 'Mã hộ', 'Quan hệ', 'Trạng thái'];
      report.rows = data.citizens.map(function(item) { return [item.citizenCode, item.fullName, genderLabel(item.gender), item.dateOfBirth, item.identityNumber, personHouseholdCode(data, item), item.relationship, statusLabel(item.status)]; });
      return report;
    }
    if (type === 'gender') {
      report.title = 'Báo cáo nhân khẩu theo giới tính';
      report.columns = ['Giới tính', 'Số lượng'];
      report.rows = countBy(data.citizens, function(item) { return genderLabel(item.gender); }, ['Nam', 'Nữ', 'Khác']).map(function(item) { return [item.label, item.value]; });
      return report;
    }
    if (type === 'age') {
      report.title = 'Báo cáo nhân khẩu theo độ tuổi';
      report.columns = ['Nhóm tuổi', 'Số lượng'];
      report.rows = countBy(data.citizens, ageBucket, ['0-5', '6-17', '18-35', '36-59', '60+', 'Không rõ']).map(function(item) { return [item.label, item.value]; });
      return report;
    }
    if (type === 'residency') {
      report.title = 'Báo cáo tình trạng cư trú';
      report.columns = ['Tình trạng cư trú', 'Số lượng'];
      report.rows = countBy(data.citizens, residencyLabel, ['Thường trú', 'Tạm trú', 'Tạm vắng']).map(function(item) { return [item.label, item.value]; });
      return report;
    }
    if (type === 'movement') {
      report.title = 'Báo cáo biến động dân cư';
      report.columns = ['Loại biến động', 'Nhân khẩu', 'Mã hộ', 'Ngày hiệu lực', 'Lý do', 'Trạng thái'];
      report.rows = data.movements.map(function(item) { return [movementLabel(item.type), item.citizenId, item.householdId, item.effectiveDate, item.reason, statusLabel(item.status)]; });
      report.summary.movements = countBy(data.movements, function(item) { return movementLabel(item.type); }, ['Sinh', 'Tử', 'Chuyển đến', 'Chuyển đi', 'Khác']);
      return report;
    }
    report.title = 'Báo cáo thống kê tổng hợp';
    report.columns = ['Chỉ số', 'Giá trị'];
    report.rows = [
      ['Tổng số hộ', dashboard.metrics.households], ['Tổng số nhân khẩu', dashboard.metrics.citizens], ['Nam', dashboard.metrics.male], ['Nữ', dashboard.metrics.female], ['Nhân khẩu đang hoạt động', dashboard.metrics.activeCitizens], ['Tạm trú', dashboard.metrics.temporaryResidence], ['Tạm vắng', dashboard.metrics.temporaryAbsence]
    ];
    return report;
  }

  function dashboard(filters) { return Application.DashboardService(db).summary(filters || {}); }
  function summary(filters) { return makeReport('summary', filters || {}); }
  function population(filters) { return makeReport('population', filters || {}); }
  function households(filters) { return makeReport('household', filters || {}).rows.map(function(row) { return { householdCode: row[0], headCitizenName: row[1], address: row[2], phone: row[3], areaCode: row[4], policy: row[5], status: row[6] }; }); }
  function household(filters) { return makeReport('household', filters || {}); }

  function renderHtml(payload) {
    var report = makeReport(payload && payload.type, payload && payload.filters || payload || {});
    var template = HtmlService.createTemplateFromFile('src/html/report/PrintReport');
    template.report = report;
    return { html: template.evaluate().getContent(), report: report };
  }

  function ensureFolder() {
    var properties = PropertiesService.getScriptProperties();
    var folderId = properties.getProperty(Domain.App.PDF_FOLDER_ID_PROPERTY);
    if (folderId) return DriveApp.getFolderById(folderId);
    var folder = DriveApp.createFolder(Domain.App.NAME + ' Reports');
    properties.setProperty(Domain.App.PDF_FOLDER_ID_PROPERTY, folder.getId());
    return folder;
  }

  function fileName(report, extension) {
    var stamp = Utilities.formatDate(new Date(), Domain.App.TIMEZONE, 'yyyyMMdd_HHmmss');
    return report.title.replace(/[^A-Za-z0-9]+/g, '_') + '_' + stamp + '.' + extension;
  }

  function exportPdf(payload) {
    var rendered = renderHtml(payload || {});
    var blob = Utilities.newBlob(rendered.html, 'text/html', rendered.report.id + '.html').getAs('application/pdf');
    var file = ensureFolder().createFile(blob).setName(fileName(rendered.report, 'pdf'));
    if (logger) logger.info(Domain.Modules.REPORT, Domain.Actions.EXPORT, rendered.report.id, 'Xuất PDF báo cáo', { fileId: file.getId(), type: rendered.report.type });
    return { fileId: file.getId(), url: file.getUrl(), name: file.getName(), report: rendered.report };
  }

  function exportExcel(payload) {
    var report = makeReport(payload && payload.type, payload && payload.filters || payload || {});
    var spreadsheet = SpreadsheetApp.create(fileName(report, 'xlsx').replace(/\.xlsx$/, ''));
    var sheet = spreadsheet.getSheets()[0].setName('Báo cáo');
    var rows = [['Tiêu đề', report.title], ['Ngày xuất', report.generatedAt], ['Người xuất', report.generatedBy], [], report.columns].concat(report.rows);
    var width = Math.max(report.columns.length, 2);
    sheet.getRange(1, 1, rows.length, width).setValues(rows.map(function(row) { var copy = row.slice(); while (copy.length < width) copy.push(''); return copy; }));
    sheet.autoResizeColumns(1, width);
    var exportUrl = 'https://docs.google.com/spreadsheets/d/' + spreadsheet.getId() + '/export?format=xlsx';
    var response = UrlFetchApp.fetch(exportUrl, { headers: { Authorization: 'Bearer ' + ScriptApp.getOAuthToken() } });
    var file = ensureFolder().createFile(response.getBlob().setName(fileName(report, 'xlsx')));
    DriveApp.getFileById(spreadsheet.getId()).setTrashed(true);
    if (logger) logger.info(Domain.Modules.REPORT, Domain.Actions.EXPORT, report.id, 'Xuất Excel báo cáo', { fileId: file.getId(), type: report.type });
    return { fileId: file.getId(), url: file.getUrl(), name: file.getName(), report: report };
  }

  function print(payload) {
    var rendered = renderHtml(payload || {});
    if (logger && !(payload && payload.noLog)) logger.info(Domain.Modules.REPORT, Domain.Actions.EXPORT, rendered.report.id, 'In báo cáo', { type: rendered.report.type });
    return rendered;
  }

  return { dashboard: dashboard, summary: summary, population: population, households: households, household: household, makeReport: makeReport, exportPdf: exportPdf, exportExcel: exportExcel, print: print };
};

Application.BackupService = function(db, logger) {
  function ensureFolder(propertyName, folderName) {
    var properties = PropertiesService.getScriptProperties();
    var folderId = properties.getProperty(propertyName);
    if (folderId) return DriveApp.getFolderById(folderId);
    var folder = DriveApp.createFolder(folderName);
    properties.setProperty(propertyName, folder.getId());
    return folder;
  }
  function createBackup(note) {
    var spreadsheet = db.spreadsheet();
    var folder = ensureFolder(Domain.App.BACKUP_FOLDER_ID_PROPERTY, Domain.App.NAME + ' Backups');
    var file = DriveApp.getFileById(spreadsheet.getId()).makeCopy(Domain.App.NAME + ' Backup ' + Entity.now(), folder);
    var record = { id: Entity.uuid('BAK'), timestamp: Entity.now(), fileId: file.getId(), fileName: file.getName(), spreadsheetId: spreadsheet.getId(), createdBy: Entity.currentEmail(), status: Domain.Status.ACTIVE, note: note || '' };
    db.append(Domain.Tables.BACKUPS, record);
    if (logger) logger.info(Domain.Modules.BACKUP, Domain.Actions.CREATE, record.id, 'Tạo bản sao lưu', record);
    return record;
  }
  function listBackups() {
    return db.readAll(Domain.Tables.BACKUPS).sort(function(a, b) { return new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime(); });
  }
  function restoreBackup(fileId) {
    if (!fileId) throw new Error('File sao lưu là bắt buộc');
    return Infrastructure.withLock(function() {
      var folder = ensureFolder(Domain.App.BACKUP_FOLDER_ID_PROPERTY, Domain.App.NAME + ' Backups');
      var source = DriveApp.getFileById(fileId);
      var restored = source.makeCopy(Domain.App.NAME + ' Restored ' + Entity.now(), folder);
      PropertiesService.getScriptProperties().setProperty(Domain.App.SPREADSHEET_ID_PROPERTY, restored.getId());
      var record = { id: Entity.uuid('BAK'), timestamp: Entity.now(), fileId: restored.getId(), fileName: restored.getName(), spreadsheetId: restored.getId(), createdBy: Entity.currentEmail(), status: Domain.Status.ACTIVE, note: 'RESTORE_FROM:' + fileId };
      var restoredDb = Infrastructure.Database();
      restoredDb.append(Domain.Tables.BACKUPS, record);
      if (logger) logger.warn(Domain.Modules.BACKUP, Domain.Actions.UPDATE, record.id, 'Khôi phục bản sao lưu', { sourceFileId: fileId, restoredSpreadsheetId: restored.getId() });
      return record;
    });
  }
  function dailyBackup() {
    return createBackup('AUTO_DAILY');
  }
  function setupDailyBackup() {
    var triggers = ScriptApp.getProjectTriggers().filter(function(trigger) { return trigger.getHandlerFunction() === 'dailyBackup'; });
    if (!triggers.length) ScriptApp.newTrigger('dailyBackup').timeBased().everyDays(1).atHour(2).create();
    if (logger) logger.info(Domain.Modules.BACKUP, Domain.Actions.UPDATE, 'dailyBackup', 'Cấu hình sao lưu hằng ngày', {});
    return { enabled: true, hour: 2 };
  }
  return { createBackup: createBackup, listBackups: listBackups, restoreBackup: restoreBackup, dailyBackup: dailyBackup, setupDailyBackup: setupDailyBackup };
};

Application.PdfService = function(db, logger) {
  function renderCitizenCard(citizenId) {
    var citizen = db.findById(Domain.Tables.CITIZENS, citizenId);
    if (!citizen) throw new Error('Không tìm thấy nhân khẩu');
    var template = HtmlService.createTemplateFromFile('src/html/pdf/CitizenCard');
    template.citizen = citizen;
    var html = template.evaluate().getContent();
    var blob = Utilities.newBlob(html, 'text/html', citizen.citizenCode + '.html').getAs('application/pdf');
    var properties = PropertiesService.getScriptProperties();
    var folderId = properties.getProperty(Domain.App.PDF_FOLDER_ID_PROPERTY);
    var folder = folderId ? DriveApp.getFolderById(folderId) : DriveApp.createFolder(Domain.App.NAME + ' PDFs');
    properties.setProperty(Domain.App.PDF_FOLDER_ID_PROPERTY, folder.getId());
    var file = folder.createFile(blob).setName('Nhân khẩu ' + citizen.citizenCode + '.pdf');
    if (logger) logger.info(Domain.Modules.PDF, Domain.Actions.EXPORT, citizen.id, 'Xuất PDF nhân khẩu', { fileId: file.getId() });
    return { fileId: file.getId(), url: file.getUrl(), name: file.getName() };
  }
  return { renderCitizenCard: renderCitizenCard };
};