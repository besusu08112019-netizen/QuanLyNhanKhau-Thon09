var Application = Application || {};

Application.SecurityService = function(db) {
  function currentUser() {
    var email = Entity.currentEmail();
    var users = db.readAll(Domain.Tables.USERS, { includeDeleted: true });
    var user = users.filter(function(item) { return String(item.email).toLowerCase() === email.toLowerCase(); })[0];
    if (!user) {
      var count = users.length;
      user = Entity.withCreateAudit(Domain.Tables.USERS, {
        email: email,
        displayName: email,
        role: count === 0 ? Domain.Roles.SUPER_ADMIN : Domain.Roles.VIEWER,
        status: Domain.Status.ACTIVE
      });
      db.append(Domain.Tables.USERS, user);
    }
    if (user.status !== Domain.Status.ACTIVE) throw new Error('Tai khoan dang bi khoa');
    return user;
  }

  function hasPermission(user, moduleName, actionName) {
    if (user.role === Domain.Roles.SUPER_ADMIN) return true;
    var permissions = db.readAll(Domain.Tables.PERMISSIONS);
    return permissions.some(function(permission) {
      return permission.role === user.role &&
        permission.module === moduleName &&
        permission.action === actionName &&
        String(permission.allowed) === 'true';
    });
  }

  function requirePermission(moduleName, actionName) {
    var user = currentUser();
    if (!hasPermission(user, moduleName, actionName)) {
      throw new Error('Khong co quyen ' + actionName + ' module ' + moduleName);
    }
    return user;
  }

  return {
    currentUser: currentUser,
    hasPermission: hasPermission,
    requirePermission: requirePermission
  };
};

Application.seedDefaultPermissions = function(db) {
  var roles = [Domain.Roles.ADMIN, Domain.Roles.OFFICER, Domain.Roles.VIEWER];
  var modules = Object.keys(Domain.Modules).map(function(key) { return Domain.Modules[key]; });
  var existing = db.readAll(Domain.Tables.PERMISSIONS, { includeDeleted: true });
  roles.forEach(function(role) {
    modules.forEach(function(moduleName) {
      Object.keys(Domain.Actions).forEach(function(actionKey) {
        var actionName = Domain.Actions[actionKey];
        var exists = existing.some(function(item) {
          return item.role === role && item.module === moduleName && item.action === actionName;
        });
        if (exists) return;
        var allowed = role === Domain.Roles.ADMIN ||
          (role === Domain.Roles.OFFICER && ['read','create','update','export'].indexOf(actionName) >= 0 && moduleName !== Domain.Modules.PERMISSION && moduleName !== Domain.Modules.USER) ||
          (role === Domain.Roles.VIEWER && ['read','export'].indexOf(actionName) >= 0);
        db.append(Domain.Tables.PERMISSIONS, Entity.withCreateAudit(Domain.Tables.PERMISSIONS, {
          role: role,
          module: moduleName,
          action: actionName,
          allowed: String(allowed)
        }));
      });
    });
  });
};
