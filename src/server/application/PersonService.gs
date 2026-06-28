var Application = Application || {};

Application.PersonService = function(personRepository, householdRepository, logger) {
  var PERSON_STATUS_ALIVE = 'ALIVE';
  var PERSON_STATUS_DECEASED = 'DECEASED';
  var PRESENCE_AT_HOME = 'AT_HOME';
  var PRESENCE_AWAY = 'AWAY';

  function normalizeText(value) {
    return String(value || '').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function normalizeGender(value) {
    var normalized = normalizeText(value);
    if (normalized === 'nam' || normalized === 'male') return 'Nam';
    if (normalized === 'nu' || normalized === 'female') return 'Nữ';
    if (normalized === 'khac' || normalized === 'other') return 'Khác';
    return String(value || '').trim();
  }

  function normalizeStatus(value) {
    var normalized = normalizeText(value);
    if (!normalized || normalized === 'active' || normalized === 'alive' || normalized === 'con song') return PERSON_STATUS_ALIVE;
    if (normalized === 'inactive' || normalized === 'deceased' || normalized === 'dead' || normalized === 'da chet' || normalized === 'mat') return PERSON_STATUS_DECEASED;
    if (normalized === 'deleted') return Domain.Status.DELETED;
    return String(value || '').trim().toUpperCase();
  }

  function normalizePresenceStatus(value) {
    var normalized = normalizeText(value);
    if (!normalized || normalized === 'at_home' || normalized === 'at home' || normalized === 'home' || normalized === 'o nha' || normalized === 'co mat') return PRESENCE_AT_HOME;
    if (normalized === 'away' || normalized === 'di vang' || normalized === 'vang' || normalized === 'tam vang') return PRESENCE_AWAY;
    return String(value || '').trim().toUpperCase();
  }

  function isHouseholdHead(value) {
    return normalizeText(value) === 'chu ho';
  }

  function clean(data) {
    data = data || {};
    return {
      id: data.id || '',
      citizenCode: String(data.citizenCode || '').trim().toUpperCase(),
      householdId: String(data.householdId || data.householdCode || '').trim().toUpperCase(),
      fullName: String(data.fullName || '').trim().replace(/\s+/g, ' '),
      gender: normalizeGender(data.gender),
      dateOfBirth: String(data.dateOfBirth || '').trim(),
      identityNumber: String(data.identityNumber || '').trim(),
      identityIssueDate: String(data.identityIssueDate || '').trim(),
      identityIssuePlace: String(data.identityIssuePlace || '').trim(),
      relationship: String(data.relationship || '').trim(),
      ethnicity: String(data.ethnicity || '').trim(),
      religion: String(data.religion || '').trim(),
      occupation: String(data.occupation || '').trim(),
      phone: String(data.phone || '').trim(),
      permanentAddress: String(data.permanentAddress || '').trim(),
      currentAddress: String(data.currentAddress || '').trim(),
      educationLevel: String(data.educationLevel || '').trim(),
      maritalStatus: String(data.maritalStatus || '').trim(),
      status: normalizeStatus(data.status),
      presenceStatus: normalizePresenceStatus(data.presenceStatus || data.currentStatus || data.residencyStatus)
    };
  }

  function resolveHousehold(value) {
    var key = String(value || '').trim();
    if (!key) return null;
    return householdRepository.findByCode(key) || householdRepository.findById(key);
  }

  function findHouseholdForSync(value) {
    var key = String(value || '').trim();
    if (!key) return null;
    return householdRepository.findByCode(key, { includeDeleted: true }) || householdRepository.findById(key, { includeDeleted: true });
  }

  function isSameHeadReference(household, person) {
    var currentHeadId = normalizeText(household && household.headCitizenId);
    var currentHeadName = normalizeText(household && household.headCitizenName);
    var personId = normalizeText(person && person.id);
    var citizenCode = normalizeText(person && person.citizenCode);
    var fullName = normalizeText(person && person.fullName);
    if (currentHeadId) return currentHeadId === personId || currentHeadId === citizenCode;
    return !!(currentHeadName && currentHeadName === fullName);
  }

  function clearPreviousHouseholdHead(previous, current) {
    if (!previous || !isHouseholdHead(previous.relationship)) return;
    if (current && current.status !== Domain.Status.DELETED && isHouseholdHead(current.relationship) && normalizeText(previous.householdId) === normalizeText(current.householdId)) return;
    var household = findHouseholdForSync(previous.householdId);
    if (!household || household.status === Domain.Status.DELETED || !isSameHeadReference(household, previous)) return;
    var record = Entity.withUpdateAudit(household, { headCitizenId: '', headCitizenName: '' });
    householdRepository.update(household.id, record);
    logger.info(Domain.Modules.HOUSEHOLD, Domain.Actions.UPDATE, household.id, 'Xóa liên kết chủ hộ cũ từ nhân khẩu', { householdCode: household.householdCode, citizenCode: previous.citizenCode });
  }

  function syncHouseholdHead(record, previous) {
    clearPreviousHouseholdHead(previous, record);
    if (!record || record.status === Domain.Status.DELETED || !isHouseholdHead(record.relationship)) return;
    var household = findHouseholdForSync(record.householdId);
    if (!household || household.status === Domain.Status.DELETED) return;
    var headCitizenId = record.citizenCode || record.id || '';
    var headCitizenName = record.fullName || '';
    if (household.headCitizenId === headCitizenId && household.headCitizenName === headCitizenName) return;
    var updated = Entity.withUpdateAudit(household, { headCitizenId: headCitizenId, headCitizenName: headCitizenName });
    householdRepository.update(household.id, updated);
    logger.info(Domain.Modules.HOUSEHOLD, Domain.Actions.UPDATE, household.id, 'Đồng bộ chủ hộ từ nhân khẩu', { householdCode: household.householdCode, citizenCode: record.citizenCode, headCitizenName: headCitizenName });
  }

  function validate(data, existingId) {
    Entity.assertRequired(Domain.Tables.CITIZENS, data);
    if (!/^[A-Z0-9._-]{2,30}$/.test(data.citizenCode)) {
      throw new Error('Mã nhân khẩu chỉ được gồm chữ cái, số, dấu chấm, gạch ngang hoặc gạch dưới; độ dài 2-30 ký tự');
    }
    if (data.fullName.length < 2 || data.fullName.length > 120) {
      throw new Error('Họ tên phải có độ dài 2-120 ký tự');
    }
    var normalizedGender = normalizeText(data.gender);
    if (['nam', 'nu', 'khac', 'male', 'female', 'other'].indexOf(normalizedGender) === -1) {
      throw new Error('Giới tính không hợp lệ');
    }
    if (data.identityNumber && !/^\d{9}$|^\d{12}$/.test(data.identityNumber)) {
      throw new Error('CCCD/CMND phải gồm 9 hoặc 12 chữ số');
    }
    if (data.phone && !/^[0-9+() .-]{8,20}$/.test(data.phone)) {
      throw new Error('Số điện thoại không hợp lệ');
    }
    if ([PERSON_STATUS_ALIVE, PERSON_STATUS_DECEASED, Domain.Status.DELETED].indexOf(data.status) === -1) {
      throw new Error('Trạng thái nhân khẩu không hợp lệ');
    }
    if ([PRESENCE_AT_HOME, PRESENCE_AWAY].indexOf(data.presenceStatus) === -1) {
      throw new Error('Hiện tại chỉ được chọn Ở nhà hoặc Đi vắng');
    }
    var household = resolveHousehold(data.householdId);
    if (!household) {
      throw new Error('Không tìm thấy Mã hộ: ' + data.householdId);
    }
    data.householdId = String(household.householdCode || data.householdId).trim().toUpperCase();
    if (data.identityNumber) {
      var duplicate = personRepository.findByIdentityNumber(data.identityNumber, { includeDeleted: true });
      if (duplicate && duplicate.id !== existingId && duplicate.status !== Domain.Status.DELETED) {
        throw new Error('CCCD/CMND đã tồn tại: ' + data.identityNumber);
      }
    }
  }

  function listPage(query) {
    query = Object.assign({}, query || {});
    if (query.status) query.status = normalizeStatus(query.status);
    if (query.presenceStatus) query.presenceStatus = normalizePresenceStatus(query.presenceStatus);
    if (query.householdId || query.householdCode) {
      var household = resolveHousehold(query.householdId || query.householdCode);
      query.householdId = household ? household.householdCode : '__HOUSEHOLD_NOT_FOUND__';
      query.householdCode = query.householdId;
    }
    return personRepository.listPage(query);
  }

  function get(id) {
    if (!id) throw new Error('Thiếu ID nhân khẩu');
    var person = personRepository.findById(id, { includeDeleted: true });
    if (!person) throw new Error('Không tìm thấy nhân khẩu: ' + id);
    return person;
  }

  function create(data) {
    return Infrastructure.withLock(function() {
      var payload = clean(data);
      validate(payload, '');
      var record = Entity.withCreateAudit(Domain.Tables.CITIZENS, payload);
      personRepository.create(record);
      syncHouseholdHead(record, null);
      logger.info(Domain.Modules.CITIZEN, Domain.Actions.CREATE, record.id, 'Tạo nhân khẩu', { citizenCode: record.citizenCode, householdCode: record.householdId });
      return record;
    });
  }

  function update(id, data) {
    return Infrastructure.withLock(function() {
      if (!id) throw new Error('Thiếu ID nhân khẩu');
      var existing = personRepository.findById(id, { includeDeleted: true });
      if (!existing) throw new Error('Không tìm thấy nhân khẩu: ' + id);
      var payload = clean(Object.assign({}, existing, data || {}));
      validate(payload, id);
      var record = Entity.withUpdateAudit(existing, payload);
      personRepository.update(id, record);
      syncHouseholdHead(record, existing);
      logger.info(Domain.Modules.CITIZEN, Domain.Actions.UPDATE, id, 'Cập nhật nhân khẩu', { citizenCode: record.citizenCode, householdCode: record.householdId });
      return record;
    });
  }

  function remove(id) {
    return Infrastructure.withLock(function() {
      if (!id) throw new Error('Thiếu ID nhân khẩu');
      var existing = personRepository.findById(id, { includeDeleted: true });
      if (!existing) throw new Error('Không tìm thấy nhân khẩu: ' + id);
      var record = Entity.withDeleteAudit(existing);
      personRepository.update(id, record);
      clearPreviousHouseholdHead(existing, record);
      logger.warn(Domain.Modules.CITIZEN, Domain.Actions.DELETE, id, 'Xóa mềm nhân khẩu', { citizenCode: existing.citizenCode, householdCode: existing.householdId });
      return record;
    });
  }

  function restore(id) {
    return Infrastructure.withLock(function() {
      if (!id) throw new Error('Thiếu ID nhân khẩu');
      var existing = personRepository.findById(id, { includeDeleted: true });
      if (!existing) throw new Error('Không tìm thấy nhân khẩu: ' + id);
      var payload = clean(Object.assign({}, existing, { status: PERSON_STATUS_ALIVE, deletedAt: '', deletedBy: '' }));
      validate(payload, id);
      var record = Entity.withUpdateAudit(existing, payload);
      record.deletedAt = '';
      record.deletedBy = '';
      personRepository.update(id, record);
      syncHouseholdHead(record, existing);
      logger.info(Domain.Modules.CITIZEN, Domain.Actions.UPDATE, id, 'Khôi phục nhân khẩu', { citizenCode: record.citizenCode, householdCode: record.householdId });
      return record;
    });
  }

  return {
    listPage: listPage,
    get: get,
    create: create,
    update: update,
    remove: remove,
    restore: restore
  };
};