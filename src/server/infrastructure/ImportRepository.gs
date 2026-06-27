var Infrastructure = Infrastructure || {};

Infrastructure.ImportRepository = function() {
  function normalizeHeader(value) {
    return String(value || '').trim();
  }

  function openSpreadsheet(spreadsheetId) {
    if (!spreadsheetId) throw new Error('Spreadsheet ID la bat buoc');
    return SpreadsheetApp.openById(String(spreadsheetId).trim());
  }

  function readSheet(spreadsheetId, sheetName) {
    var spreadsheet = openSpreadsheet(spreadsheetId);
    if (!sheetName) throw new Error('Ten Sheet la bat buoc');
    var sheet = spreadsheet.getSheetByName(String(sheetName).trim());
    if (!sheet) throw new Error('Khong tim thay Sheet: ' + sheetName);
    var lastRow = sheet.getLastRow();
    var lastColumn = sheet.getLastColumn();
    if (lastRow < 1 || lastColumn < 1) return { spreadsheetId: spreadsheetId, sheetName: sheetName, headers: [], rows: [] };
    var values = sheet.getRange(1, 1, lastRow, lastColumn).getValues();
    var headers = values[0].map(normalizeHeader);
    var rows = values.slice(1).map(function(row, index) {
      var data = {};
      headers.forEach(function(header, columnIndex) {
        if (header) data[header] = row[columnIndex];
      });
      return { rowNumber: index + 2, data: data };
    }).filter(function(item) {
      return Object.keys(item.data).some(function(key) { return String(item.data[key] || '').trim() !== ''; });
    });
    return { spreadsheetId: spreadsheetId, sheetName: sheetName, headers: headers, rows: rows };
  }

  return {
    openSpreadsheet: openSpreadsheet,
    readSheet: readSheet
  };
};