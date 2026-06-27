var Application = Application || {};

Application.AdminService = function(db, logger) {
  function createUser(data) {
    return Infrastructure.withLock(function() {
      var record = Entity.withCreateAudit(Domain.Tables.USERS, data || {});
      db.append(Domain.Tables.USERS, record);
      logger.info(Domain.Modules.USER, Domain.Actions.CREATE, record.id, 'Tao nguoi dung', record);
      return record;
    });
  }

  function updateUser(id, data) {
    return Infrastructure.withLock(function() {
      var existing = db.findById(Domain.Tables.USERS, id, { includeDeleted: true });
      if (!existing) throw new Error('Khong tim thay nguoi dung: ' + id);
      var record = Entity.withUpdateAudit(existing, data || {});
      db.replace(Domain.Tables.USERS, id, record);
      logger.info(Domain.Modules.USER, Domain.Actions.UPDATE, id, 'Cap nhat nguoi dung', data);
      return record;
    });
  }

  function deleteUser(id) {
    return Infrastructure.withLock(function() {
      var existing = db.findById(Domain.Tables.USERS, id, { includeDeleted: true });
      if (!existing) throw new Error('Khong tim thay nguoi dung: ' + id);
      var record = Entity.withDeleteAudit(existing);
      db.replace(Domain.Tables.USERS, id, record);
      logger.warn(Domain.Modules.USER, Domain.Actions.DELETE, id, 'Khoa nguoi dung', {});
      return record;
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
    createUser: createUser,
    updateUser: updateUser,
    deleteUser: deleteUser,
    updatePermission: updatePermission
  };
};
