var Infrastructure = Infrastructure || {};

Infrastructure.Database = function() {
  var properties = PropertiesService.getScriptProperties();
  var memoryCache = {};

  function cacheKey(tableName, includeDeleted) {
    return tableName + ':' + (includeDeleted ? 'all' : 'active');
  }

  function cloneRows(rows) {
    return rows.map(function(row) { return Object.assign({}, row); });
  }

  function clearTableCache(tableName) {
    Object.keys(memoryCache).forEach(function(key) {
      if (key.indexOf(tableName + ':') === 0) delete memoryCache[key];
    });
  }

  function getSpreadsheet() {
    var spreadsheetId = properties.getProperty(Domain.App.SPREADSHEET_ID_PROPERTY);
    if (spreadsheetId) return SpreadsheetApp.openById(spreadsheetId);
    var spreadsheet = SpreadsheetApp.create(Domain.App.NAME + ' Database');
    properties.setProperty(Domain.App.SPREADSHEET_ID_PROPERTY, spreadsheet.getId());
    return spreadsheet;
  }

  function currentHeaders(sheet) {
    var lastColumn = sheet.getLastColumn();
    if (lastColumn < 1) return [];
    return sheet.getRange(1, 1, 1, lastColumn).getValues()[0].map(function(header) { return String(header || '').trim(); });
  }

  function syncHeaders(sheet, tableName, headers) {
    if (sheet.getLastRow() === 0) {
      sheet.appendRow(headers);
      sheet.setFrozenRows(1);
      sheet.getRange(1, 1, 1, headers.length).setFontWeight('bold').setBackground('#e8f0fe');
      return;
    }
    var existing = currentHeaders(sheet);
    if (tableName === Domain.Tables.HOUSEHOLDS) {
      for (var i = existing.length - 1; i >= 0; i--) {
        if (existing[i] === 'hamlet') sheet.deleteColumn(i + 1);
      }
      existing = currentHeaders(sheet);
    }
    var same = existing.length === headers.length && headers.every(function(header, index) { return existing[index] === header; });
    if (!same) {
      if (sheet.getLastColumn() < headers.length) sheet.insertColumnsAfter(sheet.getLastColumn(), headers.length - sheet.getLastColumn());
      sheet.getRange(1, 1, 1, headers.length).setValues([headers]).setFontWeight('bold').setBackground('#e8f0fe');
    }
    sheet.setFrozenRows(1);
  }

  function getSheet(tableName) {
    var spreadsheet = getSpreadsheet();
    var sheet = spreadsheet.getSheetByName(tableName) || spreadsheet.insertSheet(tableName);
    var headers = Domain.Schema[tableName];
    if (!headers) throw new Error('Bang khong hop le: ' + tableName);
    syncHeaders(sheet, tableName, headers);
    return sheet;
  }

  function readAllRaw(tableName) {
    var sheet = getSheet(tableName);
    var headers = Domain.Schema[tableName];
    var lastRow = sheet.getLastRow();
    if (lastRow < 2) return [];
    var values = sheet.getRange(2, 1, lastRow - 1, headers.length).getValues();
    return values.map(function(row) { return Entity.fromRow(headers, row); });
  }

  function readAll(tableName, options) {
    var includeDeleted = !!(options && options.includeDeleted === true);
    var key = cacheKey(tableName, includeDeleted);
    if (!memoryCache[key]) {
      var rows = readAllRaw(tableName);
      if (!includeDeleted) rows = rows.filter(function(row) { return row.status !== Domain.Status.DELETED; });
      memoryCache[key] = rows;
    }
    return cloneRows(memoryCache[key]);
  }

  function findById(tableName, id, options) {
    return readAll(tableName, options).filter(function(row) { return row.id === id; })[0] || null;
  }

  function append(tableName, record) {
    var sheet = getSheet(tableName);
    sheet.appendRow(Entity.toRow(tableName, record));
    clearTableCache(tableName);
    return record;
  }

  function appendMany(tableName, records) {
    records = records || [];
    if (!records.length) return [];
    var sheet = getSheet(tableName);
    var rows = records.map(function(record) { return Entity.toRow(tableName, record); });
    sheet.getRange(sheet.getLastRow() + 1, 1, rows.length, Domain.Schema[tableName].length).setValues(rows);
    clearTableCache(tableName);
    return records;
  }

  function replace(tableName, id, record) {
    var sheet = getSheet(tableName);
    var headers = Domain.Schema[tableName];
    var lastRow = sheet.getLastRow();
    if (lastRow < 2) throw new Error('Khong tim thay ban ghi: ' + id);
    var ids = sheet.getRange(2, 1, lastRow - 1, 1).getValues();
    for (var i = 0; i < ids.length; i++) {
      if (ids[i][0] === id) {
        sheet.getRange(i + 2, 1, 1, headers.length).setValues([Entity.toRow(tableName, record)]);
        clearTableCache(tableName);
        return record;
      }
    }
    throw new Error('Khong tim thay ban ghi: ' + id);
  }

  function count(tableName) {
    return readAll(tableName).length;
  }

  return {
    spreadsheet: getSpreadsheet,
    sheet: getSheet,
    readAll: readAll,
    findById: findById,
    append: append,
    appendMany: appendMany,
    replace: replace,
    count: count
  };
};

Infrastructure.withLock = function(callback) {
  var lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try {
    return callback();
  } finally {
    lock.releaseLock();
  }
};