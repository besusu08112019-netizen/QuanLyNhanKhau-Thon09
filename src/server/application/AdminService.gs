var Application = Application || {};

Application.AdminService = function(db, logger) {
  function normalizeEmail(email) {
    return String(email || '').trim().toLowerCase();
  }

  function validRole(role) {
    return [Domain.Roles.ADMIN, Domain.Roles.OFFICER, Domain.Roles.VIEWER, Domain.Roles.SUPER_ADMIN].indexOf(role) >= 0;
  }

  function validateUser(data, id) {
    data = data || {};
    var email = normalizeEmail(data.email);
    if (!email) throw new Error('Email nguoi dung la bat buoc');
    if (!/^\S+@\S+\.\S+$/.test(email)) throw new Error('Email nguoi dung khong hop le');
    if (!String(data.displayName || '').trim()) throw new Error('Ten hien thi la bat buoc');
    if (!validRole(data.role)) throw new Error('Vai tro nguoi dung khong hop le');
    var duplicate = db.readAll(Domain.Tables.USERS, { includeDeleted: true }).some(function(user) {
      return user.id !== id && normalizeEmail(user.email) === email && user.status !== Domain.Status.DELETED;
    });
    if (duplicate) throw new Error('Email nguoi dung da ton tai');
    data.email = email;
    data.displayName = String(data.displayName || '').trim();
    data.status = data.status || Domain.Status.ACTIVE;
    return data;
  }

  function hashPassword(userId, password) {
    var text = String(password || '');
    if (text.length < 8) throw new Error('Mat khau phai co it nhat 8 ky tu');
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
    filters = filters || {};
    var keyword = String(filters.keyword || '').trim().toLowerCase();
    var users = db.readAll(Domain.Tables.USERS, { includeDeleted: true }).filter(function(user) {
      if (filters.role && user.role !== filters.role) return false;
      if (filters.status && user.status !== filters.status) return false;
      if (keyword) {
        var haystack = [user.email, user.displayName, user.role, user.status].join(' ').toLowerCase();
        if (haystack.indexOf(keyword) < 0) return false;
      }
      return true;
    }).sort(function(a, b) { return String(a.email).localeCompare(String(b.email)); });
    return users;
  }

  function createUser(data) {
    return Infrastructure.withLock(function() {
      var valid = validateUser(data || {});
      var password = valid.password;
      delete valid.password;
      var record = Entity.withCreateAudit(Domain.Tables.USERS, valid);
      db.append(Domain.Tables.USERS, record);
      if (password) storePasswordHash(record.id, password);
      logger.info(Domain.Modules.USER, Domain.Actions.CREATE, record.id, 'Tao nguoi dung', { email: record.email, role: record.role });
      return record;
    });
  }

  function updateUser(id, data) {
    return Infrastructure.withLock(function() {
      var existing = db.findById(Domain.Tables.USERS, id, { includeDeleted: true });
      if (!existing) throw new Error('Khong tim thay nguoi dung: ' + id);
      var merged = Object.assign({}, existing, data || {});
      var valid = validateUser(merged, id);
      delete valid.password;
      var record = Entity.withUpdateAudit(existing, valid);
      db.replace(Domain.Tables.USERS, id, record);
      logger.info(Domain.Modules.USER, Domain.Actions.UPDATE, id, 'Cap nhat nguoi dung', { email: record.email, role: record.role, status: record.status });
      return record;
    });
  }

  function setStatus(id, status, message) {
    return Infrastructure.withLock(function() {
      var existing = db.findById(Domain.Tables.USERS, id, { includeDeleted: true });
      if (!existing) throw new Error('Khong tim thay nguoi dung: ' + id);
      if (existing.role === Domain.Roles.SUPER_ADMIN && status !== Domain.Status.ACTIVE) throw new Error('Khong the khoa tai khoan quan tri he thong');
      var record = Entity.withUpdateAudit(existing, { status: status });
      if (status === Domain.Status.ACTIVE) {
        record.deletedAt = '';
        record.deletedBy = '';
      }
      db.replace(Domain.Tables.USERS, id, record);
      logger.warn(Domain.Modules.USER, status === Domain.Status.ACTIVE ? Domain.Actions.UPDATE : Domain.Actions.DELETE, id, message, { email: record.email, status: status });
      return record;
    });
  }

  function deleteUser(id) {
    return setStatus(id, Domain.Status.INACTIVE, 'Khoa nguoi dung');
  }

  function lockUser(id) {
    return setStatus(id, Domain.Status.INACTIVE, 'Khoa nguoi dung');
  }

  function unlockUser(id) {
    return setStatus(id, Domain.Status.ACTIVE, 'Mo khoa nguoi dung');
  }

  function changePassword(id, password) {
    return Infrastructure.withLock(function() {
      var existing = db.findById(Domain.Tables.USERS, id, { includeDeleted: true });
      if (!existing) throw new Error('Khong tim thay nguoi dung: ' + id);
      storePasswordHash(id, password);
      var record = Entity.withUpdateAudit(existing, { updatedAt: Entity.now(), updatedBy: Entity.currentEmail() });
      db.replace(Domain.Tables.USERS, id, record);
      logger.info(Domain.Modules.USER, Domain.Actions.UPDATE, id, 'Doi mat khau ung dung', { email: existing.email });
      return { id: id, updatedAt: record.updatedAt };
    });
  }

  function updatePermission(id, data) {
    return Infrastructure.withLock(function() {
      var existing = db.findById(Domain.Tables.PERMISSIONS, id, { includeDeleted: true });
      if (!existing) throw new Error('Khong tim thay quyen: ' + id);
      var record = Entity.withUpdateAudit(existing, data || {});
      db.replace(Domain.Tables.PERMISSIONS, id, record);
      logger.info(Domain.Modules.PERMISSION, Domain.Actions.UPDATE, id, 'Cap nhat quyen', data);
      return record;
    });
  }

  return {
    listUsers: listUsers,
    createUser: createUser,
    updateUser: updateUser,
    deleteUser: deleteUser,
    lockUser: lockUser,
    unlockUser: unlockUser,
    changePassword: changePassword,
    updatePermission: updatePermission
  };
};