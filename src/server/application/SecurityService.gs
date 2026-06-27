var Application = Application || {};

Application.SecurityService = function(db, logger) {
  function normalizeEmail(email) {
    return String(email || '').trim().toLowerCase();
  }

  function roleLabel(role) {
    return {
      SUPER_ADMIN: 'Quan tri he thong',
      ADMIN: 'Admin',
      OFFICER: 'Can bo',
      VIEWER: 'Chi xem'
    }[role] || role;
  }

  function touchLogin(user) {
    var now = Entity.now();
    var previous = user.lastLoginAt ? new Date(user.lastLoginAt).getTime() : 0;
    var current = new Date(now).getTime();
    if (!previous || current - previous > 30 * 60 * 1000) {
      var record = Entity.withUpdateAudit(user, { lastLoginAt: now });
      db.replace(Domain.Tables.USERS, user.id, record);
      if (logger) logger.info(Domain.Modules.USER, Domain.Actions.READ, user.id, 'Dang nhap he thong', { email: user.email, role: user.role });
      return record;
    }
    return user;
  }

  function currentUser() {
    var email = normalizeEmail(Entity.currentEmail());
    if (!email) throw new Error('Khong xac dinh duoc email nguoi dung');
    var users = db.readAll(Domain.Tables.USERS, { includeDeleted: true });
    var user = users.filter(function(item) { return normalizeEmail(item.email) === email; })[0];
    if (!user) {
      var count = users.length;
      user = Entity.withCreateAudit(Domain.Tables.USERS, {
        email: email,
        displayName: email,
        role: count === 0 ? Domain.Roles.SUPER_ADMIN : Domain.Roles.VIEWER,
        status: Domain.Status.ACTIVE,
        lastLoginAt: Entity.now()
      });
      db.append(Domain.Tables.USERS, user);
      if (logger) logger.info(Domain.Modules.USER, Domain.Actions.CREATE, user.id, 'Tu dong tao nguoi dung dang nhap', { email: email, role: user.role });
    }
    if (user.status !== Domain.Status.ACTIVE) throw new Error('Tai khoan dang bi khoa');
    return touchLogin(user);
  }

  function hasPermission(user, moduleName, actionName) {
    if (!user || user.status !== Domain.Status.ACTIVE) return false;
    if (user.role === Domain.Roles.SUPER_ADMIN) return true;
    var permissions = db.readAll(Domain.Tables.PERMISSIONS, { includeDeleted: true }).filter(function(permission) {
      return permission.role === user.role && permission.module === moduleName && permission.action === actionName;
    });
    if (permissions.length) return String(permissions[0].allowed) === 'true';
    if (user.role === Domain.Roles.ADMIN) return true;
    if (user.role === Domain.Roles.OFFICER) return [Domain.Actions.READ, Domain.Actions.CREATE, Domain.Actions.UPDATE, Domain.Actions.EXPORT].indexOf(actionName) >= 0 && moduleName !== Domain.Modules.PERMISSION && moduleName !== Domain.Modules.USER && moduleName !== Domain.Modules.SETTINGS;
    if (user.role === Domain.Roles.VIEWER) return [Domain.Actions.READ, Domain.Actions.EXPORT].indexOf(actionName) >= 0 && moduleName !== Domain.Modules.PERMISSION && moduleName !== Domain.Modules.USER && moduleName !== Domain.Modules.SETTINGS;
    return false;
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
    requirePermission: requirePermission,
    roleLabel: roleLabel
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
          (role === Domain.Roles.OFFICER && ['read','create','update','export'].indexOf(actionName) >= 0 && moduleName !== Domain.Modules.PERMISSION && moduleName !== Domain.Modules.USER && moduleName !== Domain.Modules.SETTINGS) ||
          (role === Domain.Roles.VIEWER && ['read','export'].indexOf(actionName) >= 0 && moduleName !== Domain.Modules.PERMISSION && moduleName !== Domain.Modules.USER && moduleName !== Domain.Modules.SETTINGS);
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