var Infrastructure = Infrastructure || {};

Infrastructure.UserRepository = function(db) {
  function normalize(value) {
    return String(value || '').trim().toLowerCase();
  }

  function sortByEmail(users) {
    return users.sort(function(a, b) { return String(a.email || '').localeCompare(String(b.email || '')); });
  }

  function applyFilters(users, filters) {
    filters = filters || {};
    var keyword = normalize(filters.keyword);
    return sortByEmail(users.filter(function(user) {
      if (filters.role && user.role !== filters.role) return false;
      if (filters.status && user.status !== filters.status) return false;
      if (keyword) {
        var haystack = normalize([user.email, user.displayName, user.role, user.status].join(' '));
        if (haystack.indexOf(keyword) < 0) return false;
      }
      return true;
    }));
  }

  function list(filters) {
    return applyFilters(db.readAll(Domain.Tables.USERS, { includeDeleted: true }), filters || {});
  }

  function page(filters) {
    filters = filters || {};
    var pageNumber = Math.max(parseInt(filters.page || 1, 10), 1);
    var pageSize = Math.min(Math.max(parseInt(filters.pageSize || 20, 10), 1), 100);
    var rows = list(filters);
    var total = rows.length;
    var start = (pageNumber - 1) * pageSize;
    return {
      items: rows.slice(start, start + pageSize),
      page: pageNumber,
      pageSize: pageSize,
      total: total,
      totalPages: Math.max(Math.ceil(total / pageSize), 1)
    };
  }

  function findById(id) {
    return db.findById(Domain.Tables.USERS, id, { includeDeleted: true });
  }

  function findByEmail(email) {
    var target = normalize(email);
    return db.readAll(Domain.Tables.USERS, { includeDeleted: true }).filter(function(user) {
      return normalize(user.email) === target;
    })[0] || null;
  }

  function findByRole(role) {
    return list({ role: role });
  }

  function create(record) {
    db.append(Domain.Tables.USERS, record);
    return record;
  }

  function update(id, record) {
    db.replace(Domain.Tables.USERS, id, record);
    return record;
  }

  function remove(id, record) {
    db.replace(Domain.Tables.USERS, id, record);
    return record;
  }

  function lock(id, record) {
    db.replace(Domain.Tables.USERS, id, record);
    return record;
  }

  function unlock(id, record) {
    db.replace(Domain.Tables.USERS, id, record);
    return record;
  }

  return {
    list: list,
    page: page,
    findById: findById,
    findByEmail: findByEmail,
    findByRole: findByRole,
    create: create,
    update: update,
    remove: remove,
    lock: lock,
    unlock: unlock
  };
};