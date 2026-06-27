var Application = Application || {};

Application.HouseholdService = function(repository, logger) {
  function normalizeYesNo(value) {
    var raw = String(value === undefined || value === null ? '' : value).trim();
    var normalized = raw.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    if (['co','yes','true','1','x'].indexOf(normalized) >= 0) return 'Có';
    if (['khong','no','false','0'].indexOf(normalized) >= 0) return 'Không';
    return raw || 'Không';
  }

  function pick() {
    for (var i = 0; i < arguments.length; i += 1) {
      if (arguments[i] !== undefined && arguments[i] !== null && arguments[i] !== '') return arguments[i];
    }
    return '';
  }

  function clean(data) {
    data = data || {};
    var meritoriousFamily = normalizeYesNo(pick(data.meritoriousFamily, data.isPolicyFamily));
    var poorHousehold = normalizeYesNo(pick(data.poorHousehold, data.isPoorHousehold));
    var nearPoorHousehold = normalizeYesNo(pick(data.nearPoorHousehold, data.isNearPoorHousehold));
    var disabledHousehold = normalizeYesNo(pick(data.disabledHousehold, data.hasDisabledMember));
    return {
      id: data.id || '',
      householdCode: String(data.householdCode || '').trim().toUpperCase(),
      headCitizenId: String(data.headCitizenId || '').trim(),
      address: String(data.address || data.hamlet || '').trim(),
      phone: String(data.phone || '').trim(),
      areaCode: String(data.areaCode || '').trim().toUpperCase(),
      memberCount: data.memberCount || '',
      note: String(data.note || '').trim(),
      status: data.status || Domain.Status.ACTIVE,
      headCitizenName: String(data.headCitizenName || '').trim().replace(/\s+/g, ' '),
      meritoriousFamily: meritoriousFamily,
      poorHousehold: poorHousehold,
      nearPoorHousehold: nearPoorHousehold,
      disabledHousehold: disabledHousehold,
      isPolicyFamily: meritoriousFamily,
      isPoorHousehold: poorHousehold,
      isNearPoorHousehold: nearPoorHousehold,
      hasDisabledMember: disabledHousehold
    };
  }

  function validate(data, existingId) {
    Entity.assertRequired(Domain.Tables.HOUSEHOLDS, data);
    if (!/^[A-Z0-9._-]{2,30}$/.test(data.householdCode)) {
      throw new Error('Mã hộ chỉ được gồm chữ cái, số, dấu chấm, gạch ngang hoặc gạch dưới; độ dài 2-30 ký tự');
    }
    if (data.address.length < 2) {
      throw new Error('Địa chỉ/Thôn phải có ít nhất 2 ký tự');
    }
    if (data.phone && !/^[0-9+() .-]{8,20}$/.test(data.phone)) {
      throw new Error('Số điện thoại không hợp lệ');
    }
    if ([Domain.Status.ACTIVE, Domain.Status.INACTIVE, Domain.Status.DELETED].indexOf(data.status) === -1) {
      throw new Error('Trạng thái hộ không hợp lệ');
    }
    var duplicate = repository.findByCode(data.householdCode, { includeDeleted: true });
    if (duplicate && duplicate.id !== existingId && duplicate.status !== Domain.Status.DELETED) {
      throw new Error('Mã hộ đã tồn tại: ' + data.householdCode);
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
      logger.info(Domain.Modules.HOUSEHOLD, Domain.Actions.CREATE, record.id, 'Tạo hộ dân', { householdCode: record.householdCode, headCitizenName: record.headCitizenName });
      return record;
    });
  }

  function update(id, data) {
    return Infrastructure.withLock(function() {
      if (!id) throw new Error('Thiếu ID hộ dân');
      var existing = repository.findById(id, { includeDeleted: true });
      if (!existing) throw new Error('Không tìm thấy hộ dân: ' + id);
      var payload = clean(Object.assign({}, existing, data || {}));
      validate(payload, id);
      payload.memberCount = repository.countActiveMembers(id);
      var record = Entity.withUpdateAudit(existing, payload);
      repository.update(id, record);
      logger.info(Domain.Modules.HOUSEHOLD, Domain.Actions.UPDATE, id, 'Cập nhật hộ dân', { householdCode: record.householdCode, headCitizenName: record.headCitizenName });
      return record;
    });
  }

  function remove(id) {
    return Infrastructure.withLock(function() {
      if (!id) throw new Error('Thiếu ID hộ dân');
      var existing = repository.findById(id, { includeDeleted: true });
      if (!existing) throw new Error('Không tìm thấy hộ dân: ' + id);
      var activeMembers = repository.countActiveMembers(id);
      if (activeMembers > 0) {
        throw new Error('Không thể xóa hộ đang có ' + activeMembers + ' nhân khẩu hoạt động');
      }
      var record = Entity.withDeleteAudit(existing);
      repository.update(id, record);
      logger.warn(Domain.Modules.HOUSEHOLD, Domain.Actions.DELETE, id, 'Xóa mềm hộ dân', { householdCode: existing.householdCode });
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