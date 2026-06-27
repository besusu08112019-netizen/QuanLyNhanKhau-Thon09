var Infrastructure = Infrastructure || {};

Infrastructure.HouseholdRepository = function(db) {
  function normalizeKeyword(value) {
    return String(value || '').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function makeCitizenIndex() {
    return db.readAll(Domain.Tables.CITIZENS, { includeDeleted: true }).reduce(function(acc, citizen) {
      [citizen.id, citizen.citizenCode, citizen.identityNumber].forEach(function(key) {
        key = normalizeKeyword(key);
        if (key) acc[key] = citizen;
      });
      return acc;
    }, {});
  }

  function resolveHeadName(household, citizenIndex) {
    if (String(household.headCitizenName || '').trim()) return String(household.headCitizenName).trim();
    var key = normalizeKeyword(household.headCitizenId);
    var citizen = key ? citizenIndex[key] : null;
    return citizen ? citizen.fullName : '';
  }

  function enrichHousehold(household, citizenIndex) {
    var record = Object.assign({}, household);
    record.headCitizenName = resolveHeadName(record, citizenIndex || {});
    return record;
  }

  function isYes(value) {
    var normalized = normalizeKeyword(value);
    return value === true || value === 1 || normalized === 'co' || normalized === 'yes' || normalized === 'true' || normalized === '1' || normalized === 'x';
  }

  function policyText(household) {
    return [
      isYes(household.meritoriousFamily || household.isPolicyFamily) ? 'Gia đình có công' : '',
      isYes(household.poorHousehold || household.isPoorHousehold) ? 'Hộ nghèo' : '',
      isYes(household.nearPoorHousehold || household.isNearPoorHousehold) ? 'Hộ cận nghèo Cận nghèo' : '',
      isYes(household.disabledHousehold || household.hasDisabledMember) ? 'Tàn tật Khuyết tật' : ''
    ].filter(Boolean).join(' ');
  }

  function matchesKeyword(household, keyword) {
    if (!keyword) return true;
    return [
      household.id,
      household.householdCode,
      household.headCitizenId,
      household.headCitizenName,
      household.address,
      household.hamlet,
      household.phone,
      household.areaCode,
      household.memberCount,
      household.meritoriousFamily,
      household.poorHousehold,
      household.nearPoorHousehold,
      household.disabledHousehold,
      household.isPolicyFamily,
      household.isPoorHousehold,
      household.isNearPoorHousehold,
      household.hasDisabledMember,
      policyText(household),
      household.note,
      household.status
    ].some(function(value) {
      return normalizeKeyword(value).indexOf(keyword) >= 0;
    });
  }

  function listPage(query) {
    query = query || {};
    var keyword = normalizeKeyword(query.keyword || query.search || query.q);
    var page = Math.max(parseInt(query.page || 1, 10), 1);
    var pageSize = Math.min(Math.max(parseInt(query.pageSize || 20, 10), 5), 100);
    var citizenIndex = makeCitizenIndex();
    var rows = db.readAll(Domain.Tables.HOUSEHOLDS).map(function(household) {
      return enrichHousehold(household, citizenIndex);
    }).filter(function(household) {
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
    var normalized = normalizeKeyword(code);
    return db.readAll(Domain.Tables.HOUSEHOLDS, options).filter(function(household) {
      return normalizeKeyword(household.householdCode) === normalized;
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