var Application = Application || {};

Application.PersonService = function(personRepository, householdRepository, logger) {
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

  function clean(data) {
    data = data || {};
    return {
      id: data.id || '',
      citizenCode: String(data.citizenCode || '').trim().toUpperCase(),
      householdId: String(data.householdId || '').trim(),
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
      status: data.status || Domain.Status.ACTIVE
    };
  }

  function resolveHousehold(value) {
    var key = String(value || '').trim();
    if (!key) return null;
    return householdRepository.findById(key) || householdRepository.findByCode(key);
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
    if ([Domain.Status.ACTIVE, Domain.Status.INACTIVE, Domain.Status.DELETED].indexOf(data.status) === -1) {
      throw new Error('Trạng thái nhân khẩu không hợp lệ');
    }
    var household = resolveHousehold(data.householdId);
    if (!household) {
      throw new Error('Hộ dân không tồn tại hoặc đã bị xóa');
    }
    data.householdId = household.id;
    if (data.identityNumber) {
      var duplicate = personRepository.findByIdentityNumber(data.identityNumber, { includeDeleted: true });
      if (duplicate && duplicate.id !== existingId && duplicate.status !== Domain.Status.DELETED) {
        throw new Error('CCCD/CMND đã tồn tại: ' + data.identityNumber);
      }
    }
  }

  function listPage(query) {
    query = Object.assign({}, query || {});
    if (query.householdId) {
      var household = resolveHousehold(query.householdId);
      query.householdId = household ? household.id : '__HOUSEHOLD_NOT_FOUND__';
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
      logger.info(Domain.Modules.CITIZEN, Domain.Actions.CREATE, record.id, 'Tạo nhân khẩu', { citizenCode: record.citizenCode, householdId: record.householdId });
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
      logger.info(Domain.Modules.CITIZEN, Domain.Actions.UPDATE, id, 'Cập nhật nhân khẩu', { citizenCode: record.citizenCode, householdId: record.householdId });
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
      logger.warn(Domain.Modules.CITIZEN, Domain.Actions.DELETE, id, 'Xóa mềm nhân khẩu', { citizenCode: existing.citizenCode, householdId: existing.householdId });
      return record;
    });
  }

  function restore(id) {
    return Infrastructure.withLock(function() {
      if (!id) throw new Error('Thiếu ID nhân khẩu');
      var existing = personRepository.findById(id, { includeDeleted: true });
      if (!existing) throw new Error('Không tìm thấy nhân khẩu: ' + id);
      var payload = clean(Object.assign({}, existing, { status: Domain.Status.ACTIVE, deletedAt: '', deletedBy: '' }));
      validate(payload, id);
      var record = Entity.withUpdateAudit(existing, payload);
      record.deletedAt = '';
      record.deletedBy = '';
      personRepository.update(id, record);
      logger.info(Domain.Modules.CITIZEN, Domain.Actions.UPDATE, id, 'Khôi phục nhân khẩu', { citizenCode: record.citizenCode, householdId: record.householdId });
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