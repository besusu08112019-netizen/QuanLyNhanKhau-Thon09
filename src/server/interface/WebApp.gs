function doGet() {
  return HtmlService.createTemplateFromFile('src/html/Index')
    .evaluate()
    .setTitle(Domain.App.NAME)
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
}

function include(path) {
  return HtmlService.createHtmlOutputFromFile(path).getContent();
}

function api(action, payload) {
  var container = Interface.Container();
  try {
    var result = Interface.ApiController(container).handle(action, payload || {});
    return Entity.ok(result);
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
  var security = Application.SecurityService(db);
  var user = security.currentUser();
  return Entity.ok({ spreadsheetId: db.spreadsheet().getId(), admin: user.email });
}
