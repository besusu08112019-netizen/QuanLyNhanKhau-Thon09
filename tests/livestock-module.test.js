const assert = require('assert');
const fs = require('fs');

function read(path) {
  return fs.readFileSync(path, 'utf8');
}

const migration = read('database/migrations/20260812_190000_livestock_facilities_and_groups.sql');
const schema = read('database/schema.sql');
const model = read('app/Models/Livestock.php');
const controller = read('app/Controllers/LivestockController.php');
const ui = read('assets/js/livestock.js');
const report = read('app/Models/Report.php');

assert(migration.includes('CREATE TABLE IF NOT EXISTS livestock_facilities'), 'migration must create livestock_facilities safely');
assert(/INFORMATION_SCHEMA\.COLUMNS[\s\S]+COLUMN_NAME = 'facility_id'/.test(migration), 'migration must guard facility_id column creation');
assert(/INFORMATION_SCHEMA\.COLUMNS[\s\S]+COLUMN_NAME = 'animal_group'/.test(migration), 'migration must guard animal_group column creation');
assert(/INFORMATION_SCHEMA\.STATISTICS[\s\S]+idx_livestock_facility/.test(migration), 'migration must guard idx_livestock_facility creation');
assert(/WHERE NOT EXISTS[\s\S]+livestock_facilities/.test(migration), 'legacy facility insert must avoid duplicates on rerun/partial failure');
assert(migration.includes("l.animal_type = 'Lợn'"), 'legacy pig rows must be detected with correct UTF-8 Lợn');
assert(migration.includes("THEN 'UNCLASSIFIED'"), 'legacy pig rows without group must become UNCLASSIFIED');
assert(!/THEN 'PIG_SOW'|THEN 'PIG_MEAT'/.test(migration), 'migration must not infer old pig rows as sow or meat pig');
assert(!migration.includes('Lá»'), 'migration must not contain mojibake pig label');

assert(schema.includes('CREATE TABLE IF NOT EXISTS `livestock_facilities`'), 'schema.sql must include livestock_facilities for new tenants');
assert(schema.includes('facility_type ENUM'), 'schema.sql must include facility_type');
assert(schema.includes('animal_group VARCHAR(80)'), 'schema.sql must include animal_group');
assert(schema.includes("status ENUM('ACTIVE','PAUSED','INACTIVE','DELETED')"), 'schema.sql must include PAUSED livestock statuses');

assert(model.includes('COUNT(DISTINCT l.household_id) AS livestock_households'), 'household KPI must count distinct households');
assert(model.includes('COUNT(DISTINCT lf.id) AS facility_total'), 'facility KPI must count distinct facilities');
assert(model.includes("COUNT(DISTINCT CASE WHEN lf.facility_type='FARM' THEN lf.id END) AS farm_total"), 'farm KPI must count distinct farm facilities');
assert(model.includes('COALESCE(SUM(l.quantity),0) AS livestock_total'), 'livestock total must sum quantities');
assert(model.includes("l.animal_group='PIG_SOW'"), 'model must aggregate pig sow group');
assert(model.includes("l.animal_group='PIG_MEAT'"), 'model must aggregate meat pig group');
assert(model.includes("l.animal_group='PIGLET'"), 'model must aggregate piglet group');
assert(model.includes("l.animal_group='PIG_BOAR'"), 'model must aggregate boar group');
assert(model.includes('private function reportQuantity'), 'reports must use filtered quantity for pig drilldowns');
assert(model.includes("$animalType === $this->pigType() && $group === '') $group='UNCLASSIFIED'"), 'new pig group omission must become UNCLASSIFIED');
assert(!/\$group='PIG_SOW'|\$group='PIG_MEAT'/.test(model), 'model must not default pigs to sow/meat groups');
assert(model.includes('SELECT id FROM livestock_facilities WHERE household_id=:household_id AND note=:note'), 'runtime legacy migration must reuse legacy facility');

assert(controller.includes("'facility_type'"), 'controller must accept facility_type filter');
assert(controller.includes("'animal_group'"), 'controller must accept animal_group filter');
assert(controller.includes("'has_pig_sow'"), 'controller must accept pig sow filter');
assert(controller.includes("'has_pig_meat'"), 'controller must accept meat pig filter');

assert(ui.includes('function displayQuantity'), 'livestock list must render KPI drilldown quantity');
assert(ui.includes("state.animal_group==='PIG_SOW'"), 'UI must show sow quantity for sow drilldown');
assert(ui.includes("state.animal_type==='Lợn'"), 'UI must show pig total for pig drilldown');
assert(ui.includes('livestockFacilityTypeFilter'), 'UI must include facility type filter');
assert(ui.includes('livestockAnimalGroupFilter'), 'UI must include animal group filter');
assert(ui.includes('livestockPigSowFilter'), 'UI must include pig sow filter');
assert(ui.includes('livestockPigMeatFilter'), 'UI must include meat pig filter');

assert(report.includes('livestock-pig-farms'), 'report module must expose pig farm report');
assert(report.includes('livestock-pig-sow'), 'report module must expose pig sow report');
assert(report.includes('livestock-pig-meat'), 'report module must expose meat pig report');
assert(report.includes('livestock-pig-sow-and-meat'), 'report module must expose combined sow/meat report');

const controlled = [
  { household: 'A', facility: 'A1', type: 'HOUSEHOLD', groups: [{ type: 'Lợn', group: 'PIG_SOW', quantity: 10 }] },
  { household: 'B', facility: 'B1', type: 'FARM', groups: [{ type: 'Lợn', group: 'PIG_MEAT', quantity: 500 }] },
  { household: 'C', facility: 'C1', type: 'FARM', groups: [
    { type: 'Lợn', group: 'PIG_SOW', quantity: 100 },
    { type: 'Lợn', group: 'PIG_MEAT', quantity: 700 },
    { type: 'Lợn', group: 'PIGLET', quantity: 250 },
    { type: 'Lợn', group: 'PIG_BOAR', quantity: 5 },
  ] },
  { household: 'D', facility: 'D1', type: 'HOUSEHOLD', groups: [
    { type: 'Gà', group: null, quantity: 200 },
    { type: 'Vịt', group: null, quantity: 100 },
  ] },
];
const allGroups = controlled.flatMap((facility) => facility.groups.map((group) => ({ ...group, household: facility.household, facility: facility.facility, facilityType: facility.type })));
const sum = (groups, predicate) => groups.filter(predicate).reduce((total, group) => total + group.quantity, 0);
assert.strictEqual(new Set(controlled.map((facility) => facility.household)).size, 4, 'controlled KPI households should be unique households');
assert.strictEqual(controlled.length, 4, 'controlled facility KPI should count facilities, not groups');
assert.strictEqual(controlled.filter((facility) => facility.type === 'FARM').length, 2, 'controlled farm KPI should count farm facilities only');
assert.strictEqual(sum(allGroups, (group) => group.type === 'Lợn'), 1565, 'controlled pig total should sum all pig groups');
assert.strictEqual(sum(allGroups, (group) => group.group === 'PIG_SOW'), 110, 'controlled sow total should be 110');
assert.strictEqual(sum(allGroups, (group) => group.group === 'PIG_MEAT'), 1200, 'controlled meat pig total should be 1200');
assert.strictEqual(sum(allGroups, (group) => group.group === 'PIGLET'), 250, 'controlled piglet total should be 250');
assert.strictEqual(sum(allGroups, (group) => group.group === 'PIG_BOAR'), 5, 'controlled boar total should be 5');
const cPigTotal = sum(allGroups, (group) => group.facility === 'C1' && group.type === 'Lợn');
assert.strictEqual(cPigTotal, 1055, 'facility C pig total must be 1,055 before update');
const afterUpdate = allGroups.map((group) => group.facility === 'C1' && group.group === 'PIG_MEAT' ? { ...group, quantity: 650 } : group);
assert.strictEqual(sum(afterUpdate, (group) => group.facility === 'C1' && group.type === 'Lợn'), 1005, 'facility C pig total must be 1,005 after meat pig update');

console.log('livestock module checks passed');