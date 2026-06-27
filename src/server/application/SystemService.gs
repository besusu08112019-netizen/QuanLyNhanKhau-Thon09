var Application = Application || {};

Application.SystemService = function(db, logger) {
  var defaults = {
    unitName: 'UBND xa/phuong',
    hamletName: 'Thon 09',
    systemName: Domain.App.NAME,
    backupSchedule: 'DAILY',
    reportSigner: '',
    reportTitlePrefix: 'Quan ly nhan khau',
    supportEmail: '',
    maintenanceMessage: ''
  };

  function rowsToMap(rows) {
    return rows.reduce(function(acc, row) {
      acc[row.key] = row.value;
      return acc;
    }, {});
  }

  function getSettings() {
    var values = rowsToMap(db.readAll(Domain.Tables.SETTINGS, { includeDeleted: true }));
    return Object.assign({}, defaults, values);
  }

  function listSettings() {
    var settings = getSettings();
    return Object.keys(settings).sort().map(function(key) {
      return { key: key, value: settings[key] };
    });
  }

  function saveSettings(payload) {
    payload = payload || {};
    return Infrastructure.withLock(function() {
      var existingRows = db.readAll(Domain.Tables.SETTINGS, { includeDeleted: true });
      var existingByKey = existingRows.reduce(function(acc, row) { acc[row.key] = row; return acc; }, {});
      var saved = [];
      Object.keys(payload).forEach(function(key) {
        if (!Object.prototype.hasOwnProperty.call(defaults, key) && key.indexOf('custom.') !== 0) return;
        var row = {
          key: key,
          value: String(payload[key] === undefined || payload[key] === null ? '' : payload[key]),
          updatedAt: Entity.now(),
          updatedBy: Entity.currentEmail()
        };
        if (existingByKey[key]) db.replace(Domain.Tables.SETTINGS, key, row);
        else db.append(Domain.Tables.SETTINGS, row);
        saved.push(row);
      });
      if (logger) logger.info(Domain.Modules.SETTINGS, Domain.Actions.UPDATE, 'settings', 'Cap nhat cau hinh he thong', { keys: saved.map(function(row) { return row.key; }) });
      return getSettings();
    });
  }

  return {
    getSettings: getSettings,
    listSettings: listSettings,
    saveSettings: saveSettings
  };
};