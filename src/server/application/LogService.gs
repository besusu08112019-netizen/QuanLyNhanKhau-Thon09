var Application = Application || {};

Application.LogService = function(db) {
  function normalize(value) {
    return String(value || '').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function parseDate(value) {
    if (!value) return null;
    var date = new Date(value);
    return isNaN(date.getTime()) ? null : date;
  }

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

  function matchesDate(row, filters) {
    var timestamp = parseDate(row.timestamp);
    var from = parseDate(filters.fromDate);
    var to = parseDate(filters.toDate);
    if (from && (!timestamp || timestamp < from)) return false;
    if (to) {
      to.setHours(23, 59, 59, 999);
      if (!timestamp || timestamp > to) return false;
    }
    return true;
  }

  function search(filters) {
    filters = filters || {};
    var keyword = normalize(filters.keyword);
    var page = Math.max(parseInt(filters.page || 1, 10), 1);
    var pageSize = Math.min(Math.max(parseInt(filters.pageSize || 50, 10), 1), 500);
    var rows = db.readAll(Domain.Tables.LOGS, { includeDeleted: true }).filter(function(row) {
      if (filters.module && row.module !== filters.module) return false;
      if (filters.action && row.action !== filters.action) return false;
      if (filters.level && row.level !== filters.level) return false;
      if (filters.actorEmail && normalize(row.actorEmail).indexOf(normalize(filters.actorEmail)) < 0) return false;
      if (!matchesDate(row, filters)) return false;
      if (keyword) {
        var haystack = normalize([row.actorEmail, row.module, row.action, row.entityId, row.level, row.message, JSON.stringify(row.metadata || {})].join(' '));
        if (haystack.indexOf(keyword) < 0) return false;
      }
      return true;
    }).sort(function(a, b) { return new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime(); });
    var total = rows.length;
    var start = (page - 1) * pageSize;
    return { items: rows.slice(start, start + pageSize), page: page, pageSize: pageSize, total: total, totalPages: Math.max(Math.ceil(total / pageSize), 1) };
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
    },
    search: search
  };
};