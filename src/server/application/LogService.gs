var Application = Application || {};

Application.LogService = function(db) {
  function write(level, moduleName, actionName, entityId, message, metadata) {
    var record = {
      id: Entity.uuid('LOG'),
      timestamp: Entity.now(),
      actorEmail: Entity.currentEmail(),
      action: actionName || '',
      module: moduleName || '',
      entityId: entityId || '',
      level: level || 'INFO',
      message: message || '',
      metadata: metadata || {}
    };
    db.append(Domain.Tables.LOGS, record);
    return record;
  }

  return {
    info: function(moduleName, actionName, entityId, message, metadata) {
      return write('INFO', moduleName, actionName, entityId, message, metadata);
    },
    warn: function(moduleName, actionName, entityId, message, metadata) {
      return write('WARN', moduleName, actionName, entityId, message, metadata);
    },
    error: function(moduleName, actionName, entityId, message, metadata) {
      return write('ERROR', moduleName, actionName, entityId, message, metadata);
    }
  };
};
