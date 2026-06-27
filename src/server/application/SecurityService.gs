var Application = Application || {};

Application.SecurityService = function(db, logger) {
  var AUTH_CACHE_PREFIX = 'AUTH_SESSION_';
  var AUTH_GOOGLE_PREFIX = 'AUTH_GOOGLE_';
  var AUTH_TTL_SECONDS = 21600;

  function normalizeEmail(email) {
    return String(email || '').trim().toLowerCase();
  }

  function activeGoogleEmail() {
    return normalizeEmail(Entity.currentEmail());
  }

  function roleLabel(role) {
    return {
      SUPER_ADMIN: 'Quản trị hệ thống',
      ADMIN: 'Quản trị viên',
      OFFICER: 'Cán bộ',
      VIEWER: 'Chỉ xem'
    }[role] || role;
  }

  function passwordProperty(userId) {
    return 'USER_PASSWORD_HASH_' + userId;
  }

  function hashPassword(userId, password) {
    var text = String(password || '');
    if (text.length < 8) throw new Error('Mật khẩu phải có ít nhất 8 ký tự');
    var raw = Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, userId + ':' + text, Utilities.Charset.UTF_8);
    return raw.map(function(byte) {
      var value = byte < 0 ? byte + 256 : byte;
      return ('0' + value.toString(16)).slice(-2);
    }).join('');
  }

  function storedPasswordHash(userId) {
    return PropertiesService.getScriptProperties().getProperty(passwordProperty(userId));
  }

  function setPasswordHash(userId, password) {
    var hash = hashPassword(userId, password);
    PropertiesService.getScriptProperties().setProperty(passwordProperty(userId), hash);
    return hash;
  }

  function tokenPayload(user) {
    return JSON.stringify({ userId: user.id, email: user.email, issuedAt: Entity.now() });
  }

  function createSession(user) {
    var token = Utilities.getUuid().replace(/-/g, '') + Utilities.getUuid().replace(/-/g, '');
    var cache = CacheService.getScriptCache();
    cache.put(AUTH_CACHE_PREFIX + token, tokenPayload(user), AUTH_TTL_SECONDS);
    var googleEmail = activeGoogleEmail();
    if (googleEmail) cache.put(AUTH_GOOGLE_PREFIX + googleEmail, tokenPayload(user), AUTH_TTL_SECONDS);
    return token;
  }

  function userFromPayload(raw) {
    if (!raw) return null;
    var parsed;
    try {
      parsed = JSON.parse(raw);
    } catch (err) {
      return null;
    }
    if (!parsed || !parsed.userId) return null;
    var user = db.findById(Domain.Tables.USERS, parsed.userId, { includeDeleted: true });
    if (!user || normalizeEmail(user.email) !== normalizeEmail(parsed.email)) return null;
    return user;
  }

  function userFromToken(authToken) {
    var token = String(authToken || '').trim();
    if (!token) return null;
    return userFromPayload(CacheService.getScriptCache().get(AUTH_CACHE_PREFIX + token));
  }

  function userFromGoogleSession() {
    var email = activeGoogleEmail();
    if (!email) return null;
    return userFromPayload(CacheService.getScriptCache().get(AUTH_GOOGLE_PREFIX + email));
  }

  function clearGoogleSession() {
    var email = activeGoogleEmail();
    if (email) CacheService.getScriptCache().remove(AUTH_GOOGLE_PREFIX + email);
  }

  function touchLogin(user) {
    var now = Entity.now();
    var previous = user.lastLoginAt ? new Date(user.lastLoginAt).getTime() : 0;
    var current = new Date(now).getTime();
    if (!previous || current - previous > 30 * 60 * 1000) {
      var record = Entity.withUpdateAudit(user, { lastLoginAt: now });
      db.replace(Domain.Tables.USERS, user.id, record);
      if (logger) logger.info(Domain.Modules.USER, Domain.Actions.READ, user.id, 'Đăng nhập hệ thống', { email: user.email, role: user.role });
      return record;
    }
    return user;
  }

  function findUserByEmail(email) {
    var target = normalizeEmail(email);
    return db.readAll(Domain.Tables.USERS, { includeDeleted: true }).filter(function(item) {
      return normalizeEmail(item.email) === target;
    })[0] || null;
  }

  function ensureGoogleUser() {
    var email = activeGoogleEmail();
    if (!email) throw new Error('Không xác định được email Google đang mở WebApp');
    var users = db.readAll(Domain.Tables.USERS, { includeDeleted: true });
    var user = users.filter(function(item) { return normalizeEmail(item.email) === email; })[0];
    if (!user) {
      user = Entity.withCreateAudit(Domain.Tables.USERS, {
        email: email,
        displayName: email,
        role: users.length === 0 ? Domain.Roles.SUPER_ADMIN : Domain.Roles.VIEWER,
        status: Domain.Status.ACTIVE,
        lastLoginAt: Entity.now()
      });
      db.append(Domain.Tables.USERS, user);
      if (logger) logger.info(Domain.Modules.USER, Domain.Actions.CREATE, user.id, 'Tự động tạo người dùng đăng nhập', { email: email, role: user.role });
    }
    return user;
  }

  function publicUser(user) {
    return {
      id: user.id,
      email: user.email,
      displayName: user.displayName || user.email,
      role: user.role,
      roleLabel: roleLabel(user.role),
      status: user.status,
      lastLoginAt: user.lastLoginAt || ''
    };
  }

  function login(credentials) {
    credentials = credentials || {};
    var email = normalizeEmail(credentials.username || credentials.email || credentials.account);
    var password = String(credentials.password || '');
    if (!email) throw new Error('Vui lòng nhập tài khoản');
    if (!password) throw new Error('Vui lòng nhập mật khẩu');
    var user = findUserByEmail(email);
    if (!user) {
      if (email !== activeGoogleEmail()) throw new Error('Tài khoản không tồn tại trong hệ thống');
      user = ensureGoogleUser();
    }
    if (user.status !== Domain.Status.ACTIVE) throw new Error('Tài khoản đang bị khóa');
    var hash = storedPasswordHash(user.id);
    if (!hash) {
      if (normalizeEmail(user.email) !== activeGoogleEmail()) throw new Error('Tài khoản chưa được cấp mật khẩu. Vui lòng liên hệ quản trị viên.');
      setPasswordHash(user.id, password);
      if (logger) logger.info(Domain.Modules.USER, Domain.Actions.UPDATE, user.id, 'Thiết lập mật khẩu ứng dụng lần đầu', { email: user.email });
    } else if (hashPassword(user.id, password) !== hash) {
      if (logger) logger.warn(Domain.Modules.USER, Domain.Actions.READ, user.id, 'Đăng nhập thất bại', { email: user.email });
      throw new Error('Tài khoản hoặc mật khẩu không đúng');
    }
    user = touchLogin(user);
    var token = createSession(user);
    return { token: token, expiresIn: AUTH_TTL_SECONDS, user: publicUser(user) };
  }

  function currentUser(authToken) {
    var user = userFromToken(authToken) || userFromGoogleSession();
    if (!user) user = ensureGoogleUser();
    if (user.status !== Domain.Status.ACTIVE) throw new Error('Tài khoản đang bị khóa');
    return touchLogin(user);
  }

  function roleAllows(user, moduleName, actionName) {
    if (!user || user.status !== Domain.Status.ACTIVE) return false;
    if (user.role === Domain.Roles.SUPER_ADMIN || user.role === Domain.Roles.ADMIN) return true;
    if ([Domain.Modules.USER, Domain.Modules.PERMISSION, Domain.Modules.SETTINGS, Domain.Modules.BACKUP, Domain.Modules.LOGS].indexOf(moduleName) >= 0) return false;
    if (user.role === Domain.Roles.OFFICER) {
      if (moduleName === Domain.Modules.IMPORT) return [Domain.Actions.READ, Domain.Actions.CREATE].indexOf(actionName) >= 0;
      if (moduleName === Domain.Modules.HOUSEHOLD || moduleName === Domain.Modules.CITIZEN) return [Domain.Actions.READ, Domain.Actions.CREATE, Domain.Actions.UPDATE, Domain.Actions.DELETE].indexOf(actionName) >= 0;
      if (moduleName === Domain.Modules.REPORT || moduleName === Domain.Modules.DASHBOARD || moduleName === Domain.Modules.PDF) return [Domain.Actions.READ, Domain.Actions.EXPORT].indexOf(actionName) >= 0;
      if (moduleName === Domain.Modules.MOVEMENT) return [Domain.Actions.READ, Domain.Actions.CREATE, Domain.Actions.UPDATE].indexOf(actionName) >= 0;
      return false;
    }
    if (user.role === Domain.Roles.VIEWER) {
      return [Domain.Modules.DASHBOARD, Domain.Modules.HOUSEHOLD, Domain.Modules.CITIZEN, Domain.Modules.REPORT].indexOf(moduleName) >= 0 && actionName === Domain.Actions.READ;
    }
    return false;
  }

  function hasPermission(user, moduleName, actionName) {
    if (!roleAllows(user, moduleName, actionName)) return false;
    if (user.role === Domain.Roles.SUPER_ADMIN || user.role === Domain.Roles.ADMIN) return true;
    var permissions = db.readAll(Domain.Tables.PERMISSIONS, { includeDeleted: true }).filter(function(permission) {
      return permission.role === user.role && permission.module === moduleName && permission.action === actionName;
    });
    if (!permissions.length) return true;
    return String(permissions[0].allowed) === 'true';
  }

  function requirePermission(moduleName, actionName, authToken) {
    var user = currentUser(authToken);
    if (!hasPermission(user, moduleName, actionName)) {
      throw new Error('Không có quyền ' + actionName + ' module ' + moduleName);
    }
    return user;
  }

  function logout(authToken) {
    var user = userFromToken(authToken) || userFromGoogleSession() || currentUser(authToken);
    if (authToken) CacheService.getScriptCache().remove(AUTH_CACHE_PREFIX + authToken);
    clearGoogleSession();
    if (logger) logger.info(Domain.Modules.USER, Domain.Actions.READ, user.id, 'Đăng xuất hệ thống', { email: user.email });
    return { email: user.email, loggedOutAt: Entity.now() };
  }

  return {
    login: login,
    currentUser: currentUser,
    hasPermission: hasPermission,
    requirePermission: requirePermission,
    roleLabel: roleLabel,
    logout: logout
  };
};

Application.rolePolicyAllows = function(role, moduleName, actionName) {
  if (role === Domain.Roles.SUPER_ADMIN || role === Domain.Roles.ADMIN) return true;
  if ([Domain.Modules.USER, Domain.Modules.PERMISSION, Domain.Modules.SETTINGS, Domain.Modules.BACKUP, Domain.Modules.LOGS].indexOf(moduleName) >= 0) return false;
  if (role === Domain.Roles.OFFICER) {
    if (moduleName === Domain.Modules.IMPORT) return [Domain.Actions.READ, Domain.Actions.CREATE].indexOf(actionName) >= 0;
    if (moduleName === Domain.Modules.HOUSEHOLD || moduleName === Domain.Modules.CITIZEN) return [Domain.Actions.READ, Domain.Actions.CREATE, Domain.Actions.UPDATE, Domain.Actions.DELETE].indexOf(actionName) >= 0;
    if (moduleName === Domain.Modules.REPORT || moduleName === Domain.Modules.DASHBOARD || moduleName === Domain.Modules.PDF) return [Domain.Actions.READ, Domain.Actions.EXPORT].indexOf(actionName) >= 0;
    if (moduleName === Domain.Modules.MOVEMENT) return [Domain.Actions.READ, Domain.Actions.CREATE, Domain.Actions.UPDATE].indexOf(actionName) >= 0;
    return false;
  }
  if (role === Domain.Roles.VIEWER) return [Domain.Modules.DASHBOARD, Domain.Modules.HOUSEHOLD, Domain.Modules.CITIZEN, Domain.Modules.REPORT].indexOf(moduleName) >= 0 && actionName === Domain.Actions.READ;
  return false;
};

Application.seedDefaultPermissions = function(db) {
  var roles = [Domain.Roles.ADMIN, Domain.Roles.OFFICER, Domain.Roles.VIEWER];
  var modules = Object.keys(Domain.Modules).map(function(key) { return Domain.Modules[key]; });
  var existing = db.readAll(Domain.Tables.PERMISSIONS, { includeDeleted: true });
  roles.forEach(function(role) {
    modules.forEach(function(moduleName) {
      Object.keys(Domain.Actions).forEach(function(actionKey) {
        var actionName = Domain.Actions[actionKey];
        var allowed = Application.rolePolicyAllows(role, moduleName, actionName);
        var found = existing.filter(function(item) { return item.role === role && item.module === moduleName && item.action === actionName; })[0];
        if (found) {
          if (String(found.allowed) !== String(allowed)) db.replace(Domain.Tables.PERMISSIONS, found.id, Entity.withUpdateAudit(found, { allowed: String(allowed) }));
          return;
        }
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