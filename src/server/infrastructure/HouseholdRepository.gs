var Infrastructure = Infrastructure || {};

Infrastructure.HouseholdRepository = function(db) {
  function normalizeKeyword(value) {
    return String(value || '').trim().toLowerCase();
  }

  function matchesKeyword(household, keyword) {
    if (!keyword) return true;
    return [
      household.householdCode,
      household.address,
      household.hamlet,
      household.phone,
      household.areaCode,
      household.note
    ].some(function(value) {
      return String(value || '').toLowerCase().indexOf(keyword) >= 0;
    });
  }

  function listPage(query) {
    query = query || {};
    var keyword = normalizeKeyword(query.keyword);
    var page = Math.max(parseInt(query.page || 1, 10), 1);
    var pageSize = Math.min(Math.max(parseInt(query.pageSize || 20, 10), 5), 100);
    var rows = db.readAll(Domain.Tables.HOUSEHOLDS).filter(function(household) {
      if (query.status && household.status !== query.status) return false;
      return matchesKeyword(household, keyword);
    });
    rows.sort(function(a, b) {
      return String(a.householdCode || '').localeCompare(String(b.householdCode || ''));
    });
    var total = rows.length;
    var totalPages = Math.max(Math.ceil(total / pageSize), 1);
    if (page > totalPages) page = totalPages;
    var start = (page - 1) * pageSize;
    return {
      items: rows.slice(start, start + pageSize),
      page: page,
      pageSize: pageSize,
      total: total,
      totalPages: totalPages
    };
  }

  function findById(id, options) {
    return db.findById(Domain.Tables.HOUSEHOLDS, id, options);
  }

  function findByCode(code, options) {
    var normalized = String(code || '').trim().toLowerCase();
    return db.readAll(Domain.Tables.HOUSEHOLDS, options).filter(function(household) {
      return String(household.householdCode || '').trim().toLowerCase() === normalized;
    })[0] || null;
  }

  function create(record) {
    return db.append(Domain.Tables.HOUSEHOLDS, record);
  }

  function createMany(records) {
    return db.appendMany(Domain.Tables.HOUSEHOLDS, records || []);
  }

  function update(id, record) {
    return db.replace(Domain.Tables.HOUSEHOLDS, id, record);
  }

  function countActiveMembers(householdId) {
    return db.readAll(Domain.Tables.CITIZENS).filter(function(citizen) {
      return citizen.householdId === householdId && citizen.status === Domain.Status.ACTIVE;
    }).length;
  }

  return {
    listPage: listPage,
    findById: findById,
    findByCode: findByCode,
    create: create,
    createMany: createMany,
    update: update,
    countActiveMembers: countActiveMembers
  };
};