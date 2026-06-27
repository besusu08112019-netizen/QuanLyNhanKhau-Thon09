var Application = Application || {};

Application.UserService = function(userRepository, logger, db) {
  var publicRoles = [Domain.Roles.ADMIN, Domain.Roles.OFFICER, Domain.Roles.VIEWER];

  function normalizeEmail(email) {
    return String(email || '').trim().toLowerCase();
  }

  function roleList() {
    return [
      { code: Domain.Roles.SUPER_ADMIN, name: 'Quản trị hệ thống', description: 'Tài khoản quản trị đầu tiên, có toàn quyền hệ thống' },
      { code: Domain.Roles.ADMIN, name: 'Quản trị viên', description: 'Toàn quyền quản trị và vận hành hệ thống' },
      { code: Domain.Roles.OFFICER, name: 'Cán bộ', description: 'Quản lý hộ dân, nhân khẩu và xem báo cáo' },
      { code: Domain.Roles.VIEWER, name: 'Chỉ xem', description: 'Chỉ xem dữ liệu và dashboard' }
    ];
  }

  function validRole(role, existing) {
    if (existing && existing.role === Domain.Roles.SUPER_ADMIN && role === Domain.Roles.SUPER_ADMIN) return true;
    return publicRoles.indexOf(role) >= 0;
  }

  function validateUser(data, id, existing) {
    data = data || {};
    var email = normalizeEmail(data.email);
    if (!email) throw new Error('Email người dùng là bắt buộc');
    if (!/^\S+@\S+\.\S+$/.test(email)) throw new Error('Email người dùng không hợp lệ');
    if (!String(data.displayName || '').trim()) throw new Error('Tên hiển thị là bắt buộc');
    if (!validRole(data.role, existing)) throw new Error('Vai trò người dùng không hợp lệ');
    var duplicate = userRepository.findByEmail(email);
    if (duplicate && duplicate.id !== id && duplicate.status !== Domain.Status.DELETED) throw new Error('Email người dùng đã tồn tại');
    return {
      email: email,
      displayName: String(data.displayName || '').trim(),
      role: data.role,
      status: data.status || Domain.Status.ACTIVE,
      lastLoginAt: data.lastLoginAt || ''
    };
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

  function storePasswordHash(userId, password) {
    var hash = hashPassword(userId, password);
    PropertiesService.getScriptProperties().setProperty('USER_PASSWORD_HASH_' + userId, hash);
    return hash;
  }

  function listUsers(filters) {
    return userRepository.list(filters || {});
  }

  function pageUsers(filters) {
    return userRepository.page(filters || {});
  }

  function getUser(id) {
    var user = userRepository.findById(id);
    if (!user) throw new Error('Không tìm thấy người dùng: ' + id);
    return user;
  }

  function createUser(data) {
    return Infrastructure.withLock(function() {
      var valid = validateUser(data || {});
      var password = data && data.password;
      var record = Entity.withCreateAudit(Domain.Tables.USERS, valid);
      userRepository.create(record);
      if (password) storePasswordHash(record.id, password);
      logger.info(Domain.Modules.USER, Domain.Actions.CREATE, record.id, 'Tạo người dùng', { email: record.email, role: record.role });
      return record;
    });
  }

  function updateUser(id, data) {
    return Infrastructure.withLock(function() {
      var existing = getUser(id);
      if (existing.role === Domain.Roles.SUPER_ADMIN && data && data.role && data.role !== Domain.Roles.SUPER_ADMIN) throw new Error('Không thể đổi vai trò quản trị hệ thống');
      var merged = Object.assign({}, existing, data || {});
      var valid = validateUser(merged, id, existing);
      var record = Entity.withUpdateAudit(existing, valid);
      userRepository.update(id, record);
      logger.info(Domain.Modules.USER, Domain.Actions.UPDATE, id, 'Cập nhật người dùng', { email: record.email, role: record.role, status: record.status });
      return record;
    });
  }

  function changeRole(id, role) {
    return Infrastructure.withLock(function() {
      var existing = getUser(id);
      if (existing.role === Domain.Roles.SUPER_ADMIN) throw new Error('Không thể đổi vai trò quản trị hệ thống');
      if (publicRoles.indexOf(role) < 0) throw new Error('Vai trò người dùng không hợp lệ');
      var record = Entity.withUpdateAudit(existing, { role: role });
      userRepository.update(id, record);
      logger.info(Domain.Modules.USER, Domain.Actions.UPDATE, id, 'Đổi vai trò người dùng', { email: record.email, role: role });
      return record;
    });
  }

  function setStatus(id, status, message) {
    return Infrastructure.withLock(function() {
      var existing = getUser(id);
      if (existing.role === Domain.Roles.SUPER_ADMIN && status !== Domain.Status.ACTIVE) throw new Error('Không thể khóa tài khoản quản trị hệ thống');
      var record = Entity.withUpdateAudit(existing, { status: status });
      if (status === Domain.Status.ACTIVE) {
        record.deletedAt = '';
        record.deletedBy = '';
        userRepository.unlock(id, record);
      } else {
        userRepository.lock(id, record);
      }
      logger.warn(Domain.Modules.USER, status === Domain.Status.ACTIVE ? Domain.Actions.UPDATE : Domain.Actions.DELETE, id, message, { email: record.email, status: status });
      return record;
    });
  }

  function deleteUser(id) {
    return setStatus(id, Domain.Status.DELETED, 'Xóa người dùng');
  }

  function lockUser(id) {
    return setStatus(id, Domain.Status.INACTIVE, 'Khóa người dùng');
  }

  function unlockUser(id) {
    return setStatus(id, Domain.Status.ACTIVE, 'Mở khóa người dùng');
  }

  function changePassword(id, password) {
    return Infrastructure.withLock(function() {
      var existing = getUser(id);
      storePasswordHash(id, password);
      var record = Entity.withUpdateAudit(existing, {});
      userRepository.update(id, record);
      logger.info(Domain.Modules.USER, Domain.Actions.UPDATE, id, 'Đổi mật khẩu ứng dụng', { email: existing.email });
      return { id: id, updatedAt: record.updatedAt };
    });
  }

  function updatePermission(id, data) {
    return Infrastructure.withLock(function() {
      var existing = db.findById(Domain.Tables.PERMISSIONS, id, { includeDeleted: true });
      if (!existing) throw new Error('Không tìm thấy quyền: ' + id);
      var record = Entity.withUpdateAudit(existing, data || {});
      db.replace(Domain.Tables.PERMISSIONS, id, record);
      logger.info(Domain.Modules.PERMISSION, Domain.Actions.UPDATE, id, 'Cập nhật quyền', data);
      return record;
    });
  }

  return {
    roleList: roleList,
    listUsers: listUsers,
    pageUsers: pageUsers,
    getUser: getUser,
    createUser: createUser,
    updateUser: updateUser,
    deleteUser: deleteUser,
    changeRole: changeRole,
    lockUser: lockUser,
    unlockUser: unlockUser,
    changePassword: changePassword,
    updatePermission: updatePermission
  };
};

Application.AdminService = function(db, logger) {
  return Application.UserService(Infrastructure.UserRepository(db), logger, db);
};