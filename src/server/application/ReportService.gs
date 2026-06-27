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
    if (text === 'nu' || text === 'female') return 'Nu';
    return 'Khac';
  }

  function residencyLabel(person) {
    var values = [person.residencyStatus, person.movementType, person.note, person.relationship].map(normalizeText).join(' ');
    if (values.indexOf('tam tru') >= 0 || values.indexOf('temporary residence') >= 0) return 'Tam tru';
    if (values.indexOf('tam vang') >= 0 || values.indexOf('temporary absence') >= 0) return 'Tam vang';
    return 'Thuong tru';
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
    if (age === null) return 'Khong ro';
    if (age <= 5) return '0-5';
    if (age <= 17) return '6-17';
    if (age <= 35) return '18-35';
    if (age <= 59) return '36-59';
    return '60+';
  }

  function movementLabel(value) {
    var text = normalizeText(value);
    if (text.indexOf('sinh') >= 0 || text.indexOf('birth') >= 0) return 'Sinh';
    if (text.indexOf('tu') >= 0 || text.indexOf('death') >= 0) return 'Tu';
    if (text.indexOf('den') >= 0 || text.indexOf('in') >= 0) return 'Chuyen den';
    if (text.indexOf('di') >= 0 || text.indexOf('out') >= 0) return 'Chuyen di';
    return value || 'Khac';
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

  function findPerson(data, key) {
    key = normalizeText(key);
    return data.citizens.filter(function(item) {
      return normalizeText(item.id) === key || normalizeText(item.citizenCode) === key || normalizeText(item.identityNumber) === key;
    })[0] || null;
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
      report.title = 'Phieu thong tin ho gia dinh';
      report.columns = ['Thong tin', 'Gia tri'];
      var household = findHousehold(data, filters.recordId);
      if (household) {
        var members = data.citizens.filter(function(person) { return person.householdId === household.id; });
        report.rows = [
          ['Ma ho', household.householdCode], ['Dia chi', household.address], ['Thon', household.hamlet], ['Dien thoai', household.phone], ['Khu vuc', household.areaCode], ['Trang thai', household.status], ['So nhan khau', members.length]
        ];
        members.forEach(function(person) { report.rows.push(['Thanh vien', person.fullName + ' - ' + person.relationship + ' - ' + person.status]); });
      }
      return report;
    }
    if (type === 'personForm') {
      report.title = 'Phieu thong tin nhan khau';
      report.columns = ['Thong tin', 'Gia tri'];
      var person = findPerson(data, filters.recordId);
      if (person) {
        report.rows = [
          ['Ma nhan khau', person.citizenCode], ['Ho ten', person.fullName], ['Gioi tinh', person.gender], ['Ngay sinh', person.dateOfBirth], ['CCCD/CMND', person.identityNumber], ['ID ho', person.householdId], ['Quan he', person.relationship], ['Dien thoai', person.phone], ['Thuong tru', person.permanentAddress], ['Noi o hien nay', person.currentAddress], ['Trang thai', person.status]
        ];
      }
      return report;
    }
    if (type === 'household') {
      report.title = 'Bao cao danh sach ho dan';
      report.columns = ['Ma ho', 'Dia chi', 'Thon', 'Dien thoai', 'Khu vuc', 'Trang thai'];
      report.rows = data.households.map(function(item) { return [item.householdCode, item.address, item.hamlet, item.phone, item.areaCode, item.status]; });
      return report;
    }
    if (type === 'population') {
      report.title = 'Bao cao danh sach nhan khau';
      report.columns = ['Ma nhan khau', 'Ho ten', 'Gioi tinh', 'Ngay sinh', 'CCCD/CMND', 'ID ho', 'Quan he', 'Trang thai'];
      report.rows = data.citizens.map(function(item) { return [item.citizenCode, item.fullName, item.gender, item.dateOfBirth, item.identityNumber, item.householdId, item.relationship, item.status]; });
      return report;
    }
    if (type === 'gender') {
      report.title = 'Bao cao nhan khau theo gioi tinh';
      report.columns = ['Gioi tinh', 'So luong'];
      report.rows = countBy(data.citizens, function(item) { return genderLabel(item.gender); }, ['Nam', 'Nu', 'Khac']).map(function(item) { return [item.label, item.value]; });
      return report;
    }
    if (type === 'age') {
      report.title = 'Bao cao nhan khau theo do tuoi';
      report.columns = ['Nhom tuoi', 'So luong'];
      report.rows = countBy(data.citizens, ageBucket, ['0-5', '6-17', '18-35', '36-59', '60+', 'Khong ro']).map(function(item) { return [item.label, item.value]; });
      return report;
    }
    if (type === 'residency') {
      report.title = 'Bao cao tinh trang cu tru';
      report.columns = ['Tinh trang cu tru', 'So luong'];
      report.rows = countBy(data.citizens, residencyLabel, ['Thuong tru', 'Tam tru', 'Tam vang']).map(function(item) { return [item.label, item.value]; });
      return report;
    }
    if (type === 'movement') {
      report.title = 'Bao cao bien dong dan cu';
      report.columns = ['Loai bien dong', 'Nhan khau', 'Ho', 'Ngay hieu luc', 'Ly do', 'Trang thai'];
      report.rows = data.movements.map(function(item) { return [movementLabel(item.type), item.citizenId, item.householdId, item.effectiveDate, item.reason, item.status]; });
      report.summary.movements = countBy(data.movements, function(item) { return movementLabel(item.type); }, ['Sinh', 'Tu', 'Chuyen den', 'Chuyen di', 'Khac']);
      return report;
    }
    report.title = 'Bao cao thong ke tong hop';
    report.columns = ['Chi so', 'Gia tri'];
    report.rows = [
      ['Tong so ho', dashboard.metrics.households], ['Tong so nhan khau', dashboard.metrics.citizens], ['Nam', dashboard.metrics.male], ['Nu', dashboard.metrics.female], ['Nhan khau hoat dong', dashboard.metrics.activeCitizens], ['Tam tru', dashboard.metrics.temporaryResidence], ['Tam vang', dashboard.metrics.temporaryAbsence]
    ];
    return report;
  }

  function dashboard(filters) { return Application.DashboardService(db).summary(filters || {}); }
  function summary(filters) { return makeReport('summary', filters || {}); }
  function population(filters) { return makeReport('population', filters || {}); }
  function households(filters) { return makeReport('household', filters || {}).rows.map(function(row) { return { householdCode: row[0], address: row[1], hamlet: row[2], phone: row[3], areaCode: row[4], status: row[5] }; }); }
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
    if (logger) logger.info(Domain.Modules.REPORT, Domain.Actions.EXPORT, rendered.report.id, 'Xuat PDF bao cao', { fileId: file.getId(), type: rendered.report.type });
    return { fileId: file.getId(), url: file.getUrl(), name: file.getName(), report: rendered.report };
  }

  function exportExcel(payload) {
    var report = makeReport(payload && payload.type, payload && payload.filters || payload || {});
    var spreadsheet = SpreadsheetApp.create(fileName(report, 'xlsx').replace(/\.xlsx$/, ''));
    var sheet = spreadsheet.getSheets()[0].setName('Report');
    var rows = [['Tieu de', report.title], ['Ngay xuat', report.generatedAt], ['Nguoi xuat', report.generatedBy], [], report.columns].concat(report.rows);
    var width = Math.max(report.columns.length, 2);
    sheet.getRange(1, 1, rows.length, width).setValues(rows.map(function(row) { var copy = row.slice(); while (copy.length < width) copy.push(''); return copy; }));
    sheet.autoResizeColumns(1, width);
    var exportUrl = 'https://docs.google.com/spreadsheets/d/' + spreadsheet.getId() + '/export?format=xlsx';
    var response = UrlFetchApp.fetch(exportUrl, { headers: { Authorization: 'Bearer ' + ScriptApp.getOAuthToken() } });
    var file = ensureFolder().createFile(response.getBlob().setName(fileName(report, 'xlsx')));
    DriveApp.getFileById(spreadsheet.getId()).setTrashed(true);
    if (logger) logger.info(Domain.Modules.REPORT, Domain.Actions.EXPORT, report.id, 'Xuat Excel bao cao', { fileId: file.getId(), type: report.type });
    return { fileId: file.getId(), url: file.getUrl(), name: file.getName(), report: report };
  }

  function print(payload) {
    var rendered = renderHtml(payload || {});
    if (logger && !(payload && payload.noLog)) logger.info(Domain.Modules.REPORT, Domain.Actions.EXPORT, rendered.report.id, 'In bao cao', { type: rendered.report.type });
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
    if (logger) logger.info(Domain.Modules.BACKUP, Domain.Actions.CREATE, record.id, 'Tao backup', record);
    return record;
  }
  function listBackups() {
    return db.readAll(Domain.Tables.BACKUPS).sort(function(a, b) { return new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime(); });
  }
  function restoreBackup(fileId) {
    if (!fileId) throw new Error('File backup la bat buoc');
    return Infrastructure.withLock(function() {
      var folder = ensureFolder(Domain.App.BACKUP_FOLDER_ID_PROPERTY, Domain.App.NAME + ' Backups');
      var source = DriveApp.getFileById(fileId);
      var restored = source.makeCopy(Domain.App.NAME + ' Restored ' + Entity.now(), folder);
      PropertiesService.getScriptProperties().setProperty(Domain.App.SPREADSHEET_ID_PROPERTY, restored.getId());
      var record = { id: Entity.uuid('BAK'), timestamp: Entity.now(), fileId: restored.getId(), fileName: restored.getName(), spreadsheetId: restored.getId(), createdBy: Entity.currentEmail(), status: Domain.Status.ACTIVE, note: 'RESTORE_FROM:' + fileId };
      var restoredDb = Infrastructure.Database();
      restoredDb.append(Domain.Tables.BACKUPS, record);
      if (logger) logger.warn(Domain.Modules.BACKUP, Domain.Actions.UPDATE, record.id, 'Khoi phuc backup', { sourceFileId: fileId, restoredSpreadsheetId: restored.getId() });
      return record;
    });
  }
  function dailyBackup() {
    return createBackup('AUTO_DAILY');
  }
  function setupDailyBackup() {
    var triggers = ScriptApp.getProjectTriggers().filter(function(trigger) { return trigger.getHandlerFunction() === 'dailyBackup'; });
    if (!triggers.length) ScriptApp.newTrigger('dailyBackup').timeBased().everyDays(1).atHour(2).create();
    if (logger) logger.info(Domain.Modules.BACKUP, Domain.Actions.UPDATE, 'dailyBackup', 'Cau hinh backup hang ngay', {});
    return { enabled: true, hour: 2 };
  }
  return { createBackup: createBackup, listBackups: listBackups, restoreBackup: restoreBackup, dailyBackup: dailyBackup, setupDailyBackup: setupDailyBackup };
};

Application.PdfService = function(db, logger) {
  function renderCitizenCard(citizenId) {
    var citizen = db.findById(Domain.Tables.CITIZENS, citizenId);
    if (!citizen) throw new Error('Khong tim thay nhan khau');
    var template = HtmlService.createTemplateFromFile('src/html/pdf/CitizenCard');
    template.citizen = citizen;
    var html = template.evaluate().getContent();
    var blob = Utilities.newBlob(html, 'text/html', citizen.citizenCode + '.html').getAs('application/pdf');
    var properties = PropertiesService.getScriptProperties();
    var folderId = properties.getProperty(Domain.App.PDF_FOLDER_ID_PROPERTY);
    var folder = folderId ? DriveApp.getFolderById(folderId) : DriveApp.createFolder(Domain.App.NAME + ' PDFs');
    properties.setProperty(Domain.App.PDF_FOLDER_ID_PROPERTY, folder.getId());
    var file = folder.createFile(blob).setName('Nhan khau ' + citizen.citizenCode + '.pdf');
    if (logger) logger.info(Domain.Modules.PDF, Domain.Actions.EXPORT, citizen.id, 'Xuat PDF nhan khau', { fileId: file.getId() });
    return { fileId: file.getId(), url: file.getUrl(), name: file.getName() };
  }
  return { renderCitizenCard: renderCitizenCard };
};