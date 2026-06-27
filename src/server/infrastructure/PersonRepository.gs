var Infrastructure = Infrastructure || {};

Infrastructure.PersonRepository = function(db) {
  function normalize(value) {
    return String(value || '').trim().toLowerCase();
  }

  function read(options) {
    return db.readAll(Domain.Tables.CITIZENS, options || {});
  }

  function matchesKeyword(person, keyword) {
    if (!keyword) return true;
    return [
      person.citizenCode,
      person.fullName,
      person.identityNumber,
      person.phone,
      person.relationship,
      person.currentAddress,
      person.permanentAddress,
      person.occupation
    ].some(function(value) {
      return normalize(value).indexOf(keyword) >= 0;
    });
  }

  function listPage(query) {
    query = query || {};
    var keyword = normalize(query.keyword || query.fullName);
    var page = Math.max(parseInt(query.page || 1, 10), 1);
    var pageSize = Math.min(Math.max(parseInt(query.pageSize || 20, 10), 5), 100);
    var includeDeleted = query.includeDeleted === true || query.includeDeleted === 'true';
    var rows = read({ includeDeleted: includeDeleted }).filter(function(person) {
      if (query.status && person.status !== query.status) return false;
      if (query.householdId && person.householdId !== query.householdId) return false;
      if (query.identityNumber && normalize(person.identityNumber) !== normalize(query.identityNumber)) return false;
      return matchesKeyword(person, keyword);
    });
    rows.sort(function(a, b) {
      return normalize(a.fullName).localeCompare(normalize(b.fullName)) || normalize(a.citizenCode).localeCompare(normalize(b.citizenCode));
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
    return db.findById(Domain.Tables.CITIZENS, id, options || {});
  }

  function findByIdentityNumber(identityNumber, options) {
    var normalized = normalize(identityNumber);
    if (!normalized) return null;
    return read(options || {}).filter(function(person) {
      return normalize(person.identityNumber) === normalized;
    })[0] || null;
  }

  function findByHouseholdId(householdId, options) {
    return read(options || {}).filter(function(person) {
      return person.householdId === householdId;
    });
  }

  function searchByFullName(fullName, options) {
    var keyword = normalize(fullName);
    return read(options || {}).filter(function(person) {
      return normalize(person.fullName).indexOf(keyword) >= 0;
    });
  }

  function create(record) {
    return db.append(Domain.Tables.CITIZENS, record);
  }

  function update(id, record) {
    return db.replace(Domain.Tables.CITIZENS, id, record);
  }

  return {
    listPage: listPage,
    findById: findById,
    findByIdentityNumber: findByIdentityNumber,
    findByHouseholdId: findByHouseholdId,
    searchByFullName: searchByFullName,
    create: create,
    update: update
  };
};
