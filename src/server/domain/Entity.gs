var Entity = Entity || {};

Entity.now = function() {
  return Utilities.formatDate(new Date(), Domain.App.TIMEZONE, "yyyy-MM-dd'T'HH:mm:ssXXX");
};

Entity.uuid = function(prefix) {
  return String(prefix || 'ID') + '-' + Utilities.getUuid().replace(/-/g, '').slice(0, 20).toUpperCase();
};

Entity.currentEmail = function() {
  return Session.getActiveUser().getEmail() || 'anonymous@local';
};

Entity.pick = function(input, fields) {
  var output = {};
  fields.forEach(function(field) {
    output[field] = Object.prototype.hasOwnProperty.call(input || {}, field) ? input[field] : '';
  });
  return output;
};

Entity.assertRequired = function(tableName, data) {
  var required = Domain.RequiredFields[tableName] || [];
  var missing = required.filter(function(field) {
    return data[field] === undefined || data[field] === null || String(data[field]).trim() === '';
  });
  if (missing.length) {
    throw new Error('Thieu truong bat buoc: ' + missing.join(', '));
  }
};

Entity.toRow = function(tableName, record) {
  return Domain.Schema[tableName].map(function(field) {
    var value = record[field];
    if (value === undefined || value === null) return '';
    if (typeof value === 'object') return JSON.stringify(value);
    return value;
  });
};

Entity.fromRow = function(headers, row) {
  var record = {};
  headers.forEach(function(header, index) {
    var value = row[index];
    if (typeof value === 'string' && value.charAt(0) === '{') {
      try {
        record[header] = JSON.parse(value);
        return;
      } catch (err) {}
    }
    record[header] = value;
  });
  return record;
};

Entity.withCreateAudit = function(tableName, data) {
  Entity.assertRequired(tableName, data);
  var now = Entity.now();
  var email = Entity.currentEmail();
  var record = Entity.pick(data, Domain.Schema[tableName]);
  record.id = record.id || Entity.uuid(tableName.toUpperCase().slice(0, 3));
  record.status = record.status || Domain.Status.ACTIVE;
  record.createdAt = now;
  record.createdBy = email;
  record.updatedAt = now;
  record.updatedBy = email;
  record.deletedAt = '';
  record.deletedBy = '';
  return record;
};

Entity.withUpdateAudit = function(existing, patch) {
  var record = Object.assign({}, existing, patch || {});
  record.updatedAt = Entity.now();
  record.updatedBy = Entity.currentEmail();
  return record;
};

Entity.withDeleteAudit = function(existing) {
  var record = Object.assign({}, existing);
  record.status = Domain.Status.DELETED;
  record.deletedAt = Entity.now();
  record.deletedBy = Entity.currentEmail();
  record.updatedAt = record.deletedAt;
  record.updatedBy = record.deletedBy;
  return record;
};

Entity.ok = function(data) {
  return { ok: true, data: data || null, error: null };
};

Entity.fail = function(message, detail) {
  return { ok: false, data: null, error: { message: message, detail: detail || null } };
};
