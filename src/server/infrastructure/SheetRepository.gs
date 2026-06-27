var Infrastructure = Infrastructure || {};

Infrastructure.Database = function() {
  var properties = PropertiesService.getScriptProperties();

  function getSpreadsheet() {
    var spreadsheetId = properties.getProperty(Domain.App.SPREADSHEET_ID_PROPERTY);
    if (spreadsheetId) return SpreadsheetApp.openById(spreadsheetId);
    var spreadsheet = SpreadsheetApp.create(Domain.App.NAME + ' Database');
    properties.setProperty(Domain.App.SPREADSHEET_ID_PROPERTY, spreadsheet.getId());
    return spreadsheet;
  }

  function getSheet(tableName) {
    var spreadsheet = getSpreadsheet();
    var sheet = spreadsheet.getSheetByName(tableName) || spreadsheet.insertSheet(tableName);
    var headers = Domain.Schema[tableName];
    if (!headers) throw new Error('Bang khong hop le: ' + tableName);
    if (sheet.getLastRow() === 0) {
      sheet.appendRow(headers);
      sheet.setFrozenRows(1);
      sheet.getRange(1, 1, 1, headers.length).setFontWeight('bold').setBackground('#e8f0fe');
    }
    return sheet;
  }

  function readAll(tableName, options) {
    var sheet = getSheet(tableName);
    var headers = Domain.Schema[tableName];
    var lastRow = sheet.getLastRow();
    if (lastRow < 2) return [];
    var values = sheet.getRange(2, 1, lastRow - 1, headers.length).getValues();
    var rows = values.map(function(row) { return Entity.fromRow(headers, row); });
    if (!options || options.includeDeleted !== true) {
      rows = rows.filter(function(row) { return row.status !== Domain.Status.DELETED; });
    }
    return rows;
  }

  function findById(tableName, id, options) {
    return readAll(tableName, options).filter(function(row) { return row.id === id; })[0] || null;
  }

  function append(tableName, record) {
    var sheet = getSheet(tableName);
    sheet.appendRow(Entity.toRow(tableName, record));
    return record;
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
