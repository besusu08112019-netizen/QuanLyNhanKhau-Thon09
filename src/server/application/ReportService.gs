var Application = Application || {};

Application.ReportService = function(db) {
  function dashboard(filters) {
    return Application.DashboardService(db).summary(filters || {});
  }

  function population(filters) {
    filters = filters || {};
    var citizens = db.readAll(Domain.Tables.CITIZENS).filter(function(item) {
      if (filters.householdId && item.householdId !== filters.householdId) return false;
      if (filters.status && item.status !== filters.status) return false;
      return true;
    });
    return { total: citizens.length, items: citizens };
  }

  function households() {
    var rows = db.readAll(Domain.Tables.HOUSEHOLDS);
    var citizens = db.readAll(Domain.Tables.CITIZENS);
    return rows.map(function(household) {
      var members = citizens.filter(function(citizen) { return citizen.householdId === household.id; });
      household.memberCount = members.length;
      return household;
    });
  }

  return { dashboard: dashboard, population: population, households: households };
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
    var record = {
      id: Entity.uuid('BAK'),
      timestamp: Entity.now(),
      fileId: file.getId(),
      fileName: file.getName(),
      spreadsheetId: spreadsheet.getId(),
      createdBy: Entity.currentEmail(),
      status: Domain.Status.ACTIVE,
      note: note || ''
    };
    db.append(Domain.Tables.BACKUPS, record);
    logger.info(Domain.Modules.BACKUP, Domain.Actions.CREATE, record.id, 'Tao backup', record);
    return record;
  }

  return { createBackup: createBackup, listBackups: function() { return db.readAll(Domain.Tables.BACKUPS); } };
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
    logger.info(Domain.Modules.PDF, Domain.Actions.EXPORT, citizen.id, 'Xuat PDF nhan khau', { fileId: file.getId() });
    return { fileId: file.getId(), url: file.getUrl(), name: file.getName() };
  }

  return { renderCitizenCard: renderCitizenCard };
};
