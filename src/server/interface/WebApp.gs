function doGet() {
  return HtmlService.createTemplateFromFile('src/html/Index')
    .evaluate()
    .setTitle(Domain.App.NAME)
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
}

function include(path) {
  return HtmlService.createHtmlOutputFromFile(path).getContent();
}

function sanitizeApiValue(value) {
  if (value === undefined || value === null) return value === undefined ? null : value;
  if (Object.prototype.toString.call(value) === '[object Date]') {
    return Utilities.formatDate(value, Domain.App.TIMEZONE, 'yyyy-MM-dd');
  }
  if (Array.isArray(value)) {
    return value.map(function(item) { return sanitizeApiValue(item); });
  }
  if (typeof value === 'object') {
    var output = {};
    Object.keys(value).forEach(function(key) {
      output[key] = sanitizeApiValue(value[key]);
    });
    return output;
  }
  return value;
}

function api(action, payload) {
  var container = Interface.Container();
  try {
    var result = Interface.ApiController(container).handle(action, payload || {});
    return Entity.ok(sanitizeApiValue(result));
  } catch (err) {
    try {
      container.logger.error('api', action, '', err.message, { stack: err.stack });
    } catch (logErr) {}
    return Entity.fail(err.message, err.stack || '');
  }
}

function setup() {
  var db = Infrastructure.Database();
  Object.keys(Domain.Schema).forEach(function(tableName) {
    db.sheet(tableName);
  });
  Application.seedDefaultPermissions(db);
  var logger = Application.LogService(db);
  var security = Application.SecurityService(db, logger);
  var user = security.currentUser();
  Application.SystemService(db, logger).saveSettings({ systemName: Domain.App.NAME, hamletName: 'Thon 09' });
  return Entity.ok({ spreadsheetId: db.spreadsheet().getId(), admin: user.email });
}

function dailyBackup() {
  var db = Infrastructure.Database();
  var logger = Application.LogService(db);
  return Application.BackupService(db, logger).dailyBackup();
}