var Application = Application || {};

Application.RegistryService = function(db, logger) {
  function list(tableName, filters) {
    var rows = db.readAll(tableName);
    filters = filters || {};
    Object.keys(filters).forEach(function(key) {
      var value = filters[key];
      if (value === undefined || value === null || value === '') return;
      rows = rows.filter(function(row) {
        return String(row[key] || '').toLowerCase().indexOf(String(value).toLowerCase()) >= 0;
      });
    });
    return rows;
  }

  function create(tableName, data, moduleName) {
    return Infrastructure.withLock(function() {
      var record = Entity.withCreateAudit(tableName, data || {});
      db.append(tableName, record);
      logger.info(moduleName, Domain.Actions.CREATE, record.id, 'Tao ban ghi', record);
      return record;
    });
  }

  function update(tableName, id, data, moduleName) {
    return Infrastructure.withLock(function() {
      var existing = db.findById(tableName, id, { includeDeleted: true });
      if (!existing) throw new Error('Khong tim thay ban ghi: ' + id);
      var record = Entity.withUpdateAudit(existing, data || {});
      db.replace(tableName, id, record);
      logger.info(moduleName, Domain.Actions.UPDATE, id, 'Cap nhat ban ghi', data);
      return record;
    });
  }

  function remove(tableName, id, moduleName) {
    return Infrastructure.withLock(function() {
      var existing = db.findById(tableName, id, { includeDeleted: true });
      if (!existing) throw new Error('Khong tim thay ban ghi: ' + id);
      var record = Entity.withDeleteAudit(existing);
      db.replace(tableName, id, record);
      logger.warn(moduleName, Domain.Actions.DELETE, id, 'Xoa mem ban ghi', {});
      return record;
    });
  }

  function createMovement(data) {
    return Infrastructure.withLock(function() {
      var movement = Entity.withCreateAudit(Domain.Tables.MOVEMENTS, data || {});
      db.append(Domain.Tables.MOVEMENTS, movement);
      if (movement.citizenId) {
        var citizen = db.findById(Domain.Tables.CITIZENS, movement.citizenId, { includeDeleted: true });
        if (citizen) {
          var patch = {};
          if (movement.householdId) patch.householdId = movement.householdId;
          if (movement.toAddress) patch.currentAddress = movement.toAddress;
          db.replace(Domain.Tables.CITIZENS, citizen.id, Entity.withUpdateAudit(citizen, patch));
        }
      }
      logger.info(Domain.Modules.MOVEMENT, Domain.Actions.CREATE, movement.id, 'Ghi nhan bien dong', movement);
      return movement;
    });
  }

  return {
    listHouseholds: function(filters) { return list(Domain.Tables.HOUSEHOLDS, filters); },
    createHousehold: function(data) { return create(Domain.Tables.HOUSEHOLDS, data, Domain.Modules.HOUSEHOLD); },
    updateHousehold: function(id, data) { return update(Domain.Tables.HOUSEHOLDS, id, data, Domain.Modules.HOUSEHOLD); },
    deleteHousehold: function(id) { return remove(Domain.Tables.HOUSEHOLDS, id, Domain.Modules.HOUSEHOLD); },
    listCitizens: function(filters) { return list(Domain.Tables.CITIZENS, filters); },
    createCitizen: function(data) { return create(Domain.Tables.CITIZENS, data, Domain.Modules.CITIZEN); },
    updateCitizen: function(id, data) { return update(Domain.Tables.CITIZENS, id, data, Domain.Modules.CITIZEN); },
    deleteCitizen: function(id) { return remove(Domain.Tables.CITIZENS, id, Domain.Modules.CITIZEN); },
    listMovements: function(filters) { return list(Domain.Tables.MOVEMENTS, filters); },
    createMovement: createMovement,
    updateMovement: function(id, data) { return update(Domain.Tables.MOVEMENTS, id, data, Domain.Modules.MOVEMENT); },
    deleteMovement: function(id) { return remove(Domain.Tables.MOVEMENTS, id, Domain.Modules.MOVEMENT); }
  };
};
