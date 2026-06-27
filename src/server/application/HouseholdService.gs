var Application = Application || {};

Application.HouseholdService = function(repository, logger) {
  function clean(data) {
    data = data || {};
    return {
      id: data.id || '',
      householdCode: String(data.householdCode || '').trim().toUpperCase(),
      headCitizenId: String(data.headCitizenId || '').trim(),
      address: String(data.address || data.hamlet || '').trim(),
      phone: String(data.phone || '').trim(),
      areaCode: String(data.areaCode || '').trim().toUpperCase(),
      memberCount: data.memberCount || '',
      note: String(data.note || '').trim(),
      status: data.status || Domain.Status.ACTIVE
    };
  }

  function validate(data, existingId) {
    Entity.assertRequired(Domain.Tables.HOUSEHOLDS, data);
    if (!/^[A-Z0-9._-]{2,30}$/.test(data.householdCode)) {
      throw new Error('Ma ho chi duoc gom chu cai, so, dau cham, gach ngang hoac gach duoi; do dai 2-30 ky tu');
    }
    if (data.address.length < 2) {
      throw new Error('Dia chi/thon phai co it nhat 2 ky tu');
    }
    if (data.phone && !/^[0-9+() .-]{8,20}$/.test(data.phone)) {
      throw new Error('So dien thoai khong hop le');
    }
    if ([Domain.Status.ACTIVE, Domain.Status.INACTIVE, Domain.Status.DELETED].indexOf(data.status) === -1) {
      throw new Error('Trang thai ho khong hop le');
    }
    var duplicate = repository.findByCode(data.householdCode, { includeDeleted: true });
    if (duplicate && duplicate.id !== existingId && duplicate.status !== Domain.Status.DELETED) {
      throw new Error('Ma ho da ton tai: ' + data.householdCode);
    }
  }

  function listPage(query) {
    return repository.listPage(query || {});
  }

  function create(data) {
    return Infrastructure.withLock(function() {
      var payload = clean(data);
      validate(payload, '');
      var record = Entity.withCreateAudit(Domain.Tables.HOUSEHOLDS, payload);
      record.memberCount = 0;
      repository.create(record);
      logger.info(Domain.Modules.HOUSEHOLD, Domain.Actions.CREATE, record.id, 'Tao ho dan', { householdCode: record.householdCode });
      return record;
    });
  }

  function update(id, data) {
    return Infrastructure.withLock(function() {
      if (!id) throw new Error('Thieu ID ho dan');
      var existing = repository.findById(id, { includeDeleted: true });
      if (!existing) throw new Error('Khong tim thay ho dan: ' + id);
      var payload = clean(Object.assign({}, existing, data || {}));
      validate(payload, id);
      payload.memberCount = repository.countActiveMembers(id);
      var record = Entity.withUpdateAudit(existing, payload);
      repository.update(id, record);
      logger.info(Domain.Modules.HOUSEHOLD, Domain.Actions.UPDATE, id, 'Cap nhat ho dan', { householdCode: record.householdCode });
      return record;
    });
  }

  function remove(id) {
    return Infrastructure.withLock(function() {
      if (!id) throw new Error('Thieu ID ho dan');
      var existing = repository.findById(id, { includeDeleted: true });
      if (!existing) throw new Error('Khong tim thay ho dan: ' + id);
      var activeMembers = repository.countActiveMembers(id);
      if (activeMembers > 0) {
        throw new Error('Khong the xoa ho dang co ' + activeMembers + ' nhan khau hoat dong');
      }
      var record = Entity.withDeleteAudit(existing);
      repository.update(id, record);
      logger.warn(Domain.Modules.HOUSEHOLD, Domain.Actions.DELETE, id, 'Xoa mem ho dan', { householdCode: existing.householdCode });
      return record;
    });
  }

  return {
    listPage: listPage,
    create: create,
    update: update,
    remove: remove
  };
};