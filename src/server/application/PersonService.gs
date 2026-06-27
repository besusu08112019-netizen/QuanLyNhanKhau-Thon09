var Application = Application || {};

Application.PersonService = function(personRepository, householdRepository, logger) {
  function clean(data) {
    data = data || {};
    return {
      id: data.id || '',
      citizenCode: String(data.citizenCode || '').trim().toUpperCase(),
      householdId: String(data.householdId || '').trim(),
      fullName: String(data.fullName || '').trim().replace(/\s+/g, ' '),
      gender: String(data.gender || '').trim(),
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

  function normalizeText(value) {
    return String(value || '').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function validate(data, existingId) {
    Entity.assertRequired(Domain.Tables.CITIZENS, data);
    if (!/^[A-Z0-9._-]{2,30}$/.test(data.citizenCode)) {
      throw new Error('Ma nhan khau chi duoc gom chu cai, so, dau cham, gach ngang hoac gach duoi; do dai 2-30 ky tu');
    }
    if (data.fullName.length < 2 || data.fullName.length > 120) {
      throw new Error('Ho ten phai co do dai 2-120 ky tu');
    }
    var normalizedGender = normalizeText(data.gender);
    if (['nam', 'nu', 'khac', 'male', 'female', 'other'].indexOf(normalizedGender) === -1) {
      throw new Error('Gioi tinh khong hop le');
    }
    if (data.identityNumber && !/^\d{9}$|^\d{12}$/.test(data.identityNumber)) {
      throw new Error('CCCD/CMND phai gom 9 hoac 12 chu so');
    }
    if (data.phone && !/^[0-9+() .-]{8,20}$/.test(data.phone)) {
      throw new Error('So dien thoai khong hop le');
    }
    if ([Domain.Status.ACTIVE, Domain.Status.INACTIVE, Domain.Status.DELETED].indexOf(data.status) === -1) {
      throw new Error('Trang thai nhan khau khong hop le');
    }
    var household = householdRepository.findById(data.householdId);
    if (!household) {
      throw new Error('Ho dan khong ton tai hoac da bi xoa');
    }
    if (data.identityNumber) {
      var duplicate = personRepository.findByIdentityNumber(data.identityNumber, { includeDeleted: true });
      if (duplicate && duplicate.id !== existingId && duplicate.status !== Domain.Status.DELETED) {
        throw new Error('CCCD/CMND da ton tai: ' + data.identityNumber);
      }
    }
  }

  function listPage(query) {
    return personRepository.listPage(query || {});
  }

  function get(id) {
    if (!id) throw new Error('Thieu ID nhan khau');
    var person = personRepository.findById(id, { includeDeleted: true });
    if (!person) throw new Error('Khong tim thay nhan khau: ' + id);
    return person;
  }

  function create(data) {
    return Infrastructure.withLock(function() {
      var payload = clean(data);
      validate(payload, '');
      var record = Entity.withCreateAudit(Domain.Tables.CITIZENS, payload);
      personRepository.create(record);
      logger.info(Domain.Modules.CITIZEN, Domain.Actions.CREATE, record.id, 'Tao nhan khau', { citizenCode: record.citizenCode, householdId: record.householdId });
      return record;
    });
  }

  function update(id, data) {
    return Infrastructure.withLock(function() {
      if (!id) throw new Error('Thieu ID nhan khau');
      var existing = personRepository.findById(id, { includeDeleted: true });
      if (!existing) throw new Error('Khong tim thay nhan khau: ' + id);
      var payload = clean(Object.assign({}, existing, data || {}));
      validate(payload, id);
      var record = Entity.withUpdateAudit(existing, payload);
      personRepository.update(id, record);
      logger.info(Domain.Modules.CITIZEN, Domain.Actions.UPDATE, id, 'Cap nhat nhan khau', { citizenCode: record.citizenCode, householdId: record.householdId });
      return record;
    });
  }

  function remove(id) {
    return Infrastructure.withLock(function() {
      if (!id) throw new Error('Thieu ID nhan khau');
      var existing = personRepository.findById(id, { includeDeleted: true });
      if (!existing) throw new Error('Khong tim thay nhan khau: ' + id);
      var record = Entity.withDeleteAudit(existing);
      personRepository.update(id, record);
      logger.warn(Domain.Modules.CITIZEN, Domain.Actions.DELETE, id, 'Xoa mem nhan khau', { citizenCode: existing.citizenCode, householdId: existing.householdId });
      return record;
    });
  }

  function restore(id) {
    return Infrastructure.withLock(function() {
      if (!id) throw new Error('Thieu ID nhan khau');
      var existing = personRepository.findById(id, { includeDeleted: true });
      if (!existing) throw new Error('Khong tim thay nhan khau: ' + id);
      var payload = clean(Object.assign({}, existing, { status: Domain.Status.ACTIVE, deletedAt: '', deletedBy: '' }));
      validate(payload, id);
      var record = Entity.withUpdateAudit(existing, payload);
      record.deletedAt = '';
      record.deletedBy = '';
      personRepository.update(id, record);
      logger.info(Domain.Modules.CITIZEN, Domain.Actions.UPDATE, id, 'Khoi phuc nhan khau', { citizenCode: record.citizenCode, householdId: record.householdId });
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
