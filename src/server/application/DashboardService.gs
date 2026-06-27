var Application = Application || {};

Application.DashboardService = function(db) {
  function normalizeText(value) {
    return String(value || '').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function parseDate(value) {
    if (!value) return null;
    if (Object.prototype.toString.call(value) === '[object Date]') return value;
    var date = new Date(value);
    return isNaN(date.getTime()) ? null : date;
  }

  function inDateRange(record, filters) {
    if (!filters || (!filters.fromDate && !filters.toDate)) return true;
    var date = parseDate(record.updatedAt || record.createdAt || record.effectiveDate || record.dateOfBirth);
    if (!date) return false;
    var from = parseDate(filters.fromDate);
    var to = parseDate(filters.toDate);
    if (from && date < from) return false;
    if (to) {
      to.setHours(23, 59, 59, 999);
      if (date > to) return false;
    }
    return true;
  }

  function genderKey(value) {
    var text = normalizeText(value);
    if (text === 'nam' || text === 'male') return 'male';
    if (text === 'nu' || text === 'female') return 'female';
    return 'other';
  }

  function personStatus(value) {
    var text = normalizeText(value);
    if (!text || text === 'active' || text === 'alive' || text === 'con song') return 'ALIVE';
    if (text === 'inactive' || text === 'deceased' || text === 'dead' || text === 'da chet') return 'DECEASED';
    if (text === 'deleted') return Domain.Status.DELETED;
    return String(value || '').trim().toUpperCase();
  }

  function personStatusMatches(actual, expected) {
    if (!expected) return true;
    return personStatus(actual) === personStatus(expected);
  }

  function residencyKey(person) {
    var values = [person.residencyStatus, person.movementType, person.note, person.relationship].map(normalizeText).join(' ');
    if (values.indexOf('tam tru') >= 0 || values.indexOf('temporary residence') >= 0) return 'temporaryResidence';
    if (values.indexOf('tam vang') >= 0 || values.indexOf('temporary absence') >= 0) return 'temporaryAbsence';
    return 'regular';
  }

  function ageOf(person) {
    var date = parseDate(person.dateOfBirth);
    if (!date) return null;
    var now = new Date();
    var age = now.getFullYear() - date.getFullYear();
    var monthDelta = now.getMonth() - date.getMonth();
    if (monthDelta < 0 || (monthDelta === 0 && now.getDate() < date.getDate())) age -= 1;
    return age < 0 || age > 130 ? null : age;
  }

  function bucketAge(age) {
    if (age === null) return 'Không rõ';
    if (age <= 5) return '0-5';
    if (age <= 17) return '6-17';
    if (age <= 35) return '18-35';
    if (age <= 59) return '36-59';
    return '60+';
  }

  function householdStatusLabel(value) {
    if (value === Domain.Status.ACTIVE) return 'Đang hoạt động';
    if (value === Domain.Status.INACTIVE) return 'Tạm ngưng';
    if (value === Domain.Status.DELETED) return 'Đã xóa';
    return value || 'Không rõ';
  }

  function load(filters) {
    filters = filters || {};
    var households = db.readAll(Domain.Tables.HOUSEHOLDS).filter(function(household) {
      if (filters.householdStatus && household.status !== filters.householdStatus) return false;
      return inDateRange(household, filters);
    });
    var citizens = db.readAll(Domain.Tables.CITIZENS).filter(function(person) {
      if (filters.personStatus && !personStatusMatches(person.status, filters.personStatus)) return false;
      if (filters.residencyStatus && residencyKey(person) !== filters.residencyStatus) return false;
      return inDateRange(person, filters);
    });
    var movements = db.readAll(Domain.Tables.MOVEMENTS).filter(function(movement) {
      return inDateRange(movement, filters);
    });
    return { households: households, citizens: citizens, movements: movements, filters: filters };
  }

  function populationChartFrom(citizens) {
    var counts = citizens.reduce(function(acc, person) {
      acc[genderKey(person.gender)] += 1;
      return acc;
    }, { male: 0, female: 0, other: 0 });
    return [
      { label: 'Nam', value: counts.male },
      { label: 'Nữ', value: counts.female },
      { label: 'Khác', value: counts.other }
    ];
  }

  function householdChartFrom(households) {
    var counts = households.reduce(function(acc, household) {
      var key = household.status || 'UNKNOWN';
      acc[key] = (acc[key] || 0) + 1;
      return acc;
    }, {});
    return Object.keys(counts).sort().map(function(key) {
      return { label: householdStatusLabel(key), value: counts[key] };
    });
  }

  function ageChartFrom(citizens) {
    var order = ['0-5', '6-17', '18-35', '36-59', '60+', 'Không rõ'];
    var counts = order.reduce(function(acc, key) {
      acc[key] = 0;
      return acc;
    }, {});
    citizens.forEach(function(person) {
      counts[bucketAge(ageOf(person))] += 1;
    });
    return order.map(function(key) {
      return { label: key, value: counts[key] };
    });
  }

  function summary(filters) {
    var dataset = load(filters);
    var citizens = dataset.citizens;
    var households = dataset.households;
    var livingCitizens = citizens.filter(function(person) { return personStatus(person.status) === 'ALIVE'; });
    var residency = citizens.reduce(function(acc, person) {
      acc[residencyKey(person)] += 1;
      return acc;
    }, { regular: 0, temporaryResidence: 0, temporaryAbsence: 0 });
    var gender = populationChartFrom(citizens).reduce(function(acc, item) {
      acc[item.label] = item.value;
      return acc;
    }, {});
    return {
      metrics: {
        households: households.length,
        citizens: citizens.length,
        male: gender.Nam || 0,
        female: gender['Nữ'] || 0,
        activeCitizens: livingCitizens.length,
        temporaryResidence: residency.temporaryResidence,
        temporaryAbsence: residency.temporaryAbsence
      },
      charts: {
        population: populationChartFrom(citizens),
        households: householdChartFrom(households),
        ages: ageChartFrom(citizens)
      },
      recentMovements: dataset.movements.slice(-10).reverse(),
      capacity: { households: 1000, citizens: 3000 },
      generatedAt: Entity.now()
    };
  }

  function populationChart(filters) {
    return populationChartFrom(load(filters).citizens);
  }

  function householdChart(filters) {
    return householdChartFrom(load(filters).households);
  }

  function ageChart(filters) {
    return ageChartFrom(load(filters).citizens);
  }

  return {
    summary: summary,
    populationChart: populationChart,
    householdChart: householdChart,
    ageChart: ageChart
  };
};