var Interface = Interface || {};

Interface.Container = function() {
  var db = Infrastructure.Database();
  var logger = Application.LogService(db);
  var security = Application.SecurityService(db);
  var householdRepository = Infrastructure.HouseholdRepository(db);
  var personRepository = Infrastructure.PersonRepository(db);
  return {
    db: db,
    logger: logger,
    security: security,
    household: Application.HouseholdService(householdRepository, logger),
    person: Application.PersonService(personRepository, householdRepository, logger),
    registry: Application.RegistryService(db, logger),
    reports: Application.ReportService(db),
    backup: Application.BackupService(db, logger),
    pdf: Application.PdfService(db, logger),
    admin: Application.AdminService(db, logger)
  };
};

Interface.ApiController = function(container) {
  var routes = {
    'dashboard.summary': [Domain.Modules.DASHBOARD, Domain.Actions.READ, function(payload) { return container.reports.dashboard(payload); }],
    'household.page': [Domain.Modules.HOUSEHOLD, Domain.Actions.READ, function(payload) { return container.household.listPage(payload); }],
    'household.list': [Domain.Modules.HOUSEHOLD, Domain.Actions.READ, function(payload) { return container.household.listPage(Object.assign({ page: 1, pageSize: 100 }, payload || {})).items; }],
    'household.create': [Domain.Modules.HOUSEHOLD, Domain.Actions.CREATE, function(payload) { return container.household.create(payload); }],
    'household.update': [Domain.Modules.HOUSEHOLD, Domain.Actions.UPDATE, function(payload) { return container.household.update(payload.id, payload); }],
    'household.delete': [Domain.Modules.HOUSEHOLD, Domain.Actions.DELETE, function(payload) { return container.household.remove(payload.id); }],
    'person.page': [Domain.Modules.CITIZEN, Domain.Actions.READ, function(payload) { return container.person.listPage(payload); }],
    'person.get': [Domain.Modules.CITIZEN, Domain.Actions.READ, function(payload) { return container.person.get(payload.id); }],
    'person.create': [Domain.Modules.CITIZEN, Domain.Actions.CREATE, function(payload) { return container.person.create(payload); }],
    'person.update': [Domain.Modules.CITIZEN, Domain.Actions.UPDATE, function(payload) { return container.person.update(payload.id, payload); }],
    'person.delete': [Domain.Modules.CITIZEN, Domain.Actions.DELETE, function(payload) { return container.person.remove(payload.id); }],
    'person.restore': [Domain.Modules.CITIZEN, Domain.Actions.UPDATE, function(payload) { return container.person.restore(payload.id); }],
    'citizen.list': [Domain.Modules.CITIZEN, Domain.Actions.READ, function(payload) { return container.person.listPage(Object.assign({ page: 1, pageSize: 100 }, payload || {})).items; }],
    'citizen.create': [Domain.Modules.CITIZEN, Domain.Actions.CREATE, function(payload) { return container.person.create(payload); }],
    'citizen.update': [Domain.Modules.CITIZEN, Domain.Actions.UPDATE, function(payload) { return container.person.update(payload.id, payload); }],
    'citizen.delete': [Domain.Modules.CITIZEN, Domain.Actions.DELETE, function(payload) { return container.person.remove(payload.id); }],
    'movement.list': [Domain.Modules.MOVEMENT, Domain.Actions.READ, function(payload) { return container.registry.listMovements(payload); }],
    'movement.create': [Domain.Modules.MOVEMENT, Domain.Actions.CREATE, function(payload) { return container.registry.createMovement(payload); }],
    'movement.update': [Domain.Modules.MOVEMENT, Domain.Actions.UPDATE, function(payload) { return container.registry.updateMovement(payload.id, payload); }],
    'movement.delete': [Domain.Modules.MOVEMENT, Domain.Actions.DELETE, function(payload) { return container.registry.deleteMovement(payload.id); }],
    'report.population': [Domain.Modules.REPORT, Domain.Actions.READ, function(payload) { return container.reports.population(payload); }],
    'report.households': [Domain.Modules.REPORT, Domain.Actions.READ, function() { return container.reports.households(); }],
    'pdf.citizen': [Domain.Modules.PDF, Domain.Actions.EXPORT, function(payload) { return container.pdf.renderCitizenCard(payload.citizenId); }],
    'backup.create': [Domain.Modules.BACKUP, Domain.Actions.CREATE, function(payload) { return container.backup.createBackup(payload && payload.note); }],
    'backup.list': [Domain.Modules.BACKUP, Domain.Actions.READ, function() { return container.backup.listBackups(); }],
    'user.me': [Domain.Modules.USER, Domain.Actions.READ, function() { return container.security.currentUser(); }],
    'user.list': [Domain.Modules.USER, Domain.Actions.READ, function() { return container.db.readAll(Domain.Tables.USERS); }],
    'user.create': [Domain.Modules.USER, Domain.Actions.CREATE, function(payload) { return container.admin.createUser(payload); }],
    'user.update': [Domain.Modules.USER, Domain.Actions.UPDATE, function(payload) { return container.admin.updateUser(payload.id, payload); }],
    'user.delete': [Domain.Modules.USER, Domain.Actions.DELETE, function(payload) { return container.admin.deleteUser(payload.id); }],
    'permission.list': [Domain.Modules.PERMISSION, Domain.Actions.READ, function() { return container.db.readAll(Domain.Tables.PERMISSIONS); }],
    'permission.update': [Domain.Modules.PERMISSION, Domain.Actions.UPDATE, function(payload) { return container.admin.updatePermission(payload.id, payload); }],
    'logs.list': [Domain.Modules.LOGS, Domain.Actions.READ, function() { return container.db.readAll(Domain.Tables.LOGS).slice(-500).reverse(); }]
  };

  function handle(action, payload) {
    var route = routes[action];
    if (!route) throw new Error('API khong hop le: ' + action);
    container.security.requirePermission(route[0], route[1]);
    return route[2](payload || {});
  }

  return { handle: handle };
};
