var Infrastructure = Infrastructure || {};

Infrastructure.PersonRepository = function(db) {
  function normalize(value) {
    return String(value || '').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function serializeValue(value) {
    if (value === undefined || value === null) return '';
    if (Object.prototype.toString.call(value) === '[object Date]') {
      return Utilities.formatDate(value, Domain.App.TIMEZONE, 'yyyy-MM-dd');
    }
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
  }

  function serializePerson(person) {
    var record = {};
    Object.keys(person || {}).forEach(function(key) {
      record[key] = serializeValue(person[key]);
    });
    record.status = record.status || Domain.Status.ACTIVE;
    return record;
  }

  function read(options) {
    return db.readAll(Domain.Tables.CITIZENS, options || {});
  }

  function householdIndex() {
    return db.readAll(Domain.Tables.HOUSEHOLDS, { includeDeleted: true }).reduce(function(acc, household) {
      if (household.id) acc[String(household.id)] = household;
      if (household.householdCode) acc[normalize(household.householdCode)] = household;
      return acc;
    }, {});
  }

  function enrich(person, households) {
    var record = Object.assign({}, person || {});
    var household = households[String(record.householdId)] || households[normalize(record.householdId)] || households[normalize(record.householdCode)];
    if (household) {
      record.householdCode = household.householdCode || record.householdCode || '';
      record.householdAddress = household.address || '';
      record.householdHeadName = household.headCitizenName || '';
    }
    record.status = record.status || Domain.Status.ACTIVE;
    return record;
  }

  function matchesKeyword(person, keyword) {
    if (!keyword) return true;
    return [
      person.id,
      person.citizenCode,
      person.fullName,
      person.identityNumber,
      person.phone,
      person.gender,
      person.dateOfBirth,
      person.relationship,
      person.currentAddress,
      person.permanentAddress,
      person.occupation,
      person.ethnicity,
      person.religion,
      person.educationLevel,
      person.maritalStatus,
      person.householdId,
      person.householdCode,
      person.householdAddress,
      person.householdHeadName,
      person.status
    ].some(function(value) {
      return normalize(value).indexOf(keyword) >= 0;
    });
  }

  function listPage(query) {
    query = query || {};
    var keyword = normalize(query.keyword || query.search || query.q || query.fullName || query.identityNumber || query.phone || query.citizenCode);
    var page = Math.max(parseInt(query.page || 1, 10), 1);
    var pageSize = Math.min(Math.max(parseInt(query.pageSize || 20, 10), 5), 100);
    var includeDeleted = query.includeDeleted === true || query.includeDeleted === 'true';
    var householdFilter = normalize(query.householdId || query.householdCode);
    var households = householdIndex();
    var rows = read({ includeDeleted: includeDeleted }).map(function(person) {
      return enrich(person, households);
    }).filter(function(person) {
      if (query.status && person.status !== query.status) return false;
      if (householdFilter && normalize(person.householdId) !== householdFilter && normalize(person.householdCode) !== householdFilter) return false;
      if (query.identityNumber && !keyword && normalize(person.identityNumber) !== normalize(query.identityNumber)) return false;
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
      items: rows.slice(start, start + pageSize).map(serializePerson),
      page: page,
      pageSize: pageSize,
      total: total,
      totalPages: totalPages
    };
  }

  function findById(id, options) {
    var person = db.findById(Domain.Tables.CITIZENS, id, options || {});
    return person ? serializePerson(person) : null;
  }

  function findByIdentityNumber(identityNumber, options) {
    var normalized = normalize(identityNumber);
    if (!normalized) return null;
    var person = read(options || {}).filter(function(person) {
      return normalize(person.identityNumber) === normalized;
    })[0] || null;
    return person ? serializePerson(person) : null;
  }

  function findByHouseholdId(householdId, options) {
    var normalized = normalize(householdId);
    var households = householdIndex();
    return read(options || {}).map(function(person) {
      return enrich(person, households);
    }).filter(function(person) {
      return normalize(person.householdId) === normalized || normalize(person.householdCode) === normalized;
    }).map(serializePerson);
  }

  function searchByFullName(fullName, options) {
    var keyword = normalize(fullName);
    return read(options || {}).filter(function(person) {
      return normalize(person.fullName).indexOf(keyword) >= 0;
    }).map(serializePerson);
  }

  function create(record) {
    return db.append(Domain.Tables.CITIZENS, record);
  }

  function createMany(records) {
    return db.appendMany(Domain.Tables.CITIZENS, records || []);
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
    createMany: createMany,
    update: update
  };
};