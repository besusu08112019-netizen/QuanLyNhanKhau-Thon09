var Interface = Interface || {};

Interface.Container = function() {
  var db = Infrastructure.Database();
  var logger = Application.LogService(db);
  var security = Application.SecurityService(db, logger);
  var householdRepository = Infrastructure.HouseholdRepository(db);
  var personRepository = Infrastructure.PersonRepository(db);
  var userRepository = Infrastructure.UserRepository(db);
  var userService = Application.UserService(userRepository, logger, db);
  return {
    db: db,
    logger: logger,
    security: security,
    household: Application.HouseholdService(householdRepository, logger),
    person: Application.PersonService(personRepository, householdRepository, logger),
    dashboard: Application.DashboardService(db),
    registry: Application.RegistryService(db, logger),
    reports: Application.ReportService(db, logger),
    backup: Application.BackupService(db, logger),
    pdf: Application.PdfService(db, logger),
    admin: userService,
    user: userService,
    system: Application.SystemService(db, logger),
    importer: Application.ImportService(Infrastructure.ImportRepository(), householdRepository, personRepository, logger, db)
  };
};

Interface.ApiController = function(container) {
  function normalizeText(value) {
    return String(value || '').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function safeValue(value) {
    if (value === undefined || value === null) return '';
    if (Object.prototype.toString.call(value) === '[object Date]') return Utilities.formatDate(value, Domain.App.TIMEZONE, 'yyyy-MM-dd');
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
  }

  function safeRecord(record) {
    var output = {};
    Object.keys(record || {}).forEach(function(key) { output[key] = safeValue(record[key]); });
    output.status = output.status || Domain.Status.ACTIVE;
    return output;
  }

  function householdMap() {
    return container.db.readAll(Domain.Tables.HOUSEHOLDS, { includeDeleted: true }).reduce(function(acc, household) {
      if (household.id) acc[String(household.id)] = household;
      if (household.householdCode) acc[normalizeText(household.householdCode)] = household;
      return acc;
    }, {});
  }

  function safePersonPage(payload) {
    payload = payload || {};
    var page = Math.max(parseInt(payload.page || 1, 10), 1);
    var pageSize = Math.min(Math.max(parseInt(payload.pageSize || 20, 10), 5), 100);
    var keyword = normalizeText(payload.keyword || payload.search || payload.q || payload.fullName || payload.identityNumber || payload.phone || payload.citizenCode);
    var householdFilter = normalizeText(payload.householdId || payload.householdCode);
    var includeDeleted = payload.includeDeleted === true || payload.includeDeleted === 'true' || payload.status === Domain.Status.DELETED;
    var households = householdMap();
    var rows = container.db.readAll(Domain.Tables.CITIZENS, { includeDeleted: includeDeleted }).map(function(person) {
      var record = Object.assign({}, person || {});
      var originalHouseholdRef = record.householdId;
      var household = households[String(record.householdId)] || households[normalizeText(record.householdId)] || households[normalizeText(record.householdCode)];
      if (household) {
        record.householdInternalId = household.id || '';
        record.householdCode = household.householdCode || record.householdCode || '';
        record.householdId = record.householdCode;
        record.householdAddress = household.address || '';
        record.householdHeadName = household.headCitizenName || '';
      } else {
        record.householdCode = record.householdCode || originalHouseholdRef || '';
        record.householdId = record.householdCode;
      }
      record.status = record.status || Domain.Status.ACTIVE;
      return record;
    }).filter(function(person) {
      if (payload.status && person.status !== payload.status) return false;
      if (householdFilter && normalizeText(person.householdId) !== householdFilter && normalizeText(person.householdCode) !== householdFilter && normalizeText(person.householdInternalId) !== householdFilter) return false;
      if (!keyword) return true;
      return [person.id, person.citizenCode, person.fullName, person.identityNumber, person.phone, person.gender, person.dateOfBirth, person.relationship, person.currentAddress, person.permanentAddress, person.occupation, person.ethnicity, person.religion, person.educationLevel, person.maritalStatus, person.householdId, person.householdCode, person.householdAddress, person.householdHeadName, person.status].some(function(value) {
        return normalizeText(value).indexOf(keyword) >= 0;
      });
    });
    rows.sort(function(a, b) {
      return normalizeText(a.fullName).localeCompare(normalizeText(b.fullName)) || normalizeText(a.citizenCode).localeCompare(normalizeText(b.citizenCode));
    });
    var total = rows.length;
    var totalPages = Math.max(Math.ceil(total / pageSize), 1);
    if (page > totalPages) page = totalPages;
    var start = (page - 1) * pageSize;
    return {
      items: rows.slice(start, start + pageSize).map(safeRecord),
      page: page,
      pageSize: pageSize,
      total: total,
      totalPages: totalPages
    };
  }

  var routes = {
    'dashboard.summary': [Domain.Modules.DASHBOARD, Domain.Actions.READ, function(payload) { return container.dashboard.summary(payload); }],
    'dashboard.populationChart': [Domain.Modules.DASHBOARD, Domain.Actions.READ, function(payload) { return container.dashboard.populationChart(payload); }],
    'dashboard.householdChart': [Domain.Modules.DASHBOARD, Domain.Actions.READ, function(payload) { return container.dashboard.householdChart(payload); }],
    'dashboard.ageChart': [Domain.Modules.DASHBOARD, Domain.Actions.READ, function(payload) { return container.dashboard.ageChart(payload); }],
    'household.page': [Domain.Modules.HOUSEHOLD, Domain.Actions.READ, function(payload) { return container.household.listPage(payload); }],
    'household.list': [Domain.Modules.HOUSEHOLD, Domain.Actions.READ, function(payload) { return container.household.listPage(Object.assign({ page: 1, pageSize: 100 }, payload || {})).items; }],
    'household.create': [Domain.Modules.HOUSEHOLD, Domain.Actions.CREATE, function(payload) { return container.household.create(payload); }],
    'household.update': [Domain.Modules.HOUSEHOLD, Domain.Actions.UPDATE, function(payload) { return container.household.update(payload.id, payload); }],
    'household.delete': [Domain.Modules.HOUSEHOLD, Domain.Actions.DELETE, function(payload) { return container.household.remove(payload.id); }],
    'person.safePage': [Domain.Modules.CITIZEN, Domain.Actions.READ, function(payload) { return safePersonPage(payload); }],
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
    'report.summary': [Domain.Modules.REPORT, Domain.Actions.READ, function(payload) { return container.reports.summary(payload); }],
    'report.population': [Domain.Modules.REPORT, Domain.Actions.READ, function(payload) { return container.reports.population(payload); }],
    'report.household': [Domain.Modules.REPORT, Domain.Actions.READ, function(payload) { return container.reports.household(payload); }],
    'report.households': [Domain.Modules.REPORT, Domain.Actions.READ, function(payload) { return container.reports.households(payload); }],
    'report.preview': [Domain.Modules.REPORT, Domain.Actions.READ, function(payload) { return container.reports.print(Object.assign({}, payload || {}, { noLog: true })); }],
    'report.exportPdf': [Domain.Modules.REPORT, Domain.Actions.EXPORT, function(payload) { return container.reports.exportPdf(payload); }],
    'report.exportExcel': [Domain.Modules.REPORT, Domain.Actions.EXPORT, function(payload) { return container.reports.exportExcel(payload); }],
    'report.print': [Domain.Modules.REPORT, Domain.Actions.EXPORT, function(payload) { return container.reports.print(payload); }],
    'pdf.citizen': [Domain.Modules.PDF, Domain.Actions.EXPORT, function(payload) { return container.pdf.renderCitizenCard(payload.citizenId); }],
    'backup.create': [Domain.Modules.BACKUP, Domain.Actions.CREATE, function(payload) { return container.backup.createBackup(payload && payload.note); }],
    'backup.list': [Domain.Modules.BACKUP, Domain.Actions.READ, function() { return container.backup.listBackups(); }],
    'backup.restore': [Domain.Modules.BACKUP, Domain.Actions.UPDATE, function(payload) { return container.backup.restoreBackup(payload.fileId); }],
    'backup.daily': [Domain.Modules.BACKUP, Domain.Actions.CREATE, function() { return container.backup.dailyBackup(); }],
    'backup.setupDaily': [Domain.Modules.BACKUP, Domain.Actions.UPDATE, function() { return container.backup.setupDailyBackup(); }],
    'import.preview': [Domain.Modules.IMPORT, Domain.Actions.READ, function(payload) { return container.importer.preview(payload); }],
    'import.household': [Domain.Modules.IMPORT, Domain.Actions.CREATE, function(payload) { return container.importer.importHousehold(payload); }],
    'import.person': [Domain.Modules.IMPORT, Domain.Actions.CREATE, function(payload) { return container.importer.importPerson(payload); }],
    'user.me': [Domain.Modules.USER, Domain.Actions.READ, function(payload) { return container.security.currentUser(payload && payload._authToken); }],
    'user.page': [Domain.Modules.USER, Domain.Actions.READ, function(payload) { return container.user.pageUsers(payload); }],
    'user.get': [Domain.Modules.USER, Domain.Actions.READ, function(payload) { return container.user.getUser(payload.id); }],
    'user.list': [Domain.Modules.USER, Domain.Actions.READ, function(payload) { return container.user.listUsers(payload); }],
    'user.create': [Domain.Modules.USER, Domain.Actions.CREATE, function(payload) { return container.user.createUser(payload); }],
    'user.update': [Domain.Modules.USER, Domain.Actions.UPDATE, function(payload) { return container.user.updateUser(payload.id, payload); }],
    'user.delete': [Domain.Modules.USER, Domain.Actions.DELETE, function(payload) { return container.user.deleteUser(payload.id); }],
    'user.lock': [Domain.Modules.USER, Domain.Actions.DELETE, function(payload) { return container.user.lockUser(payload.id); }],
    'user.unlock': [Domain.Modules.USER, Domain.Actions.UPDATE, function(payload) { return container.user.unlockUser(payload.id); }],
    'user.changeRole': [Domain.Modules.USER, Domain.Actions.UPDATE, function(payload) { return container.user.changeRole(payload.id, payload.role); }],
    'user.changePassword': [Domain.Modules.USER, Domain.Actions.UPDATE, function(payload) { return container.user.changePassword(payload.id, payload.password); }],
    'user.logout': [Domain.Modules.USER, Domain.Actions.READ, function(payload) { return container.security.logout(payload && payload._authToken); }],
    'role.list': [Domain.Modules.USER, Domain.Actions.READ, function() { return container.user.roleList(); }],
    'permission.list': [Domain.Modules.PERMISSION, Domain.Actions.READ, function() { return container.db.readAll(Domain.Tables.PERMISSIONS); }],
    'permission.update': [Domain.Modules.PERMISSION, Domain.Actions.UPDATE, function(payload) { return container.user.updatePermission(payload.id, payload); }],
    'logs.list': [Domain.Modules.LOGS, Domain.Actions.READ, function(payload) { return container.logger.search(Object.assign({ page: 1, pageSize: 500 }, payload || {})).items; }],
    'logs.search': [Domain.Modules.LOGS, Domain.Actions.READ, function(payload) { return container.logger.search(payload); }],
    'settings.get': [Domain.Modules.SETTINGS, Domain.Actions.READ, function() { return container.system.getSettings(); }],
    'settings.list': [Domain.Modules.SETTINGS, Domain.Actions.READ, function() { return container.system.listSettings(); }],
    'settings.save': [Domain.Modules.SETTINGS, Domain.Actions.UPDATE, function(payload) { return container.system.saveSettings(payload); }]
  };

  function handle(action, payload) {
    payload = payload || {};
    if (action === 'auth.login') return container.security.login(payload);
    if (action === 'auth.logout') return container.security.logout(payload._authToken);
    if (action === 'user.me') return container.security.currentUser(payload._authToken);
    var route = routes[action];
    if (!route) throw new Error('API không hợp lệ: ' + action);
    container.security.requirePermission(route[0], route[1], payload._authToken);
    return route[2](payload);
  }

  return { handle: handle };
};