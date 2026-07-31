const fs = require('fs');
const assert = require('assert');

function read(path) {
  return fs.readFileSync(path, 'utf8');
}

const schema = read('database/schema.sql');
const migration = read('database/migrations/20260731_090000_create_household_poverty_management.sql');
const index = read('index.php');
const model = read('app/Models/HouseholdPoverty.php');
const controller = read('app/Controllers/HouseholdPovertyController.php');
const permissions = read('app/Models/Permission.php');
const ui = read('assets/js/poverty-management.js');
const platform = read('assets/js/app-platform.js');
const build = read('tools/build-assets.js');

for (const source of [schema, migration]) {
  assert(/CREATE TABLE IF NOT EXISTS `?poverty_periods`?/i.test(source), 'poverty_periods table is required');
  assert(/CREATE TABLE IF NOT EXISTS `?household_poverty_records`?/i.test(source), 'household_poverty_records table is required');
  assert(/CREATE TABLE IF NOT EXISTS `?poverty_change_logs`?/i.test(source), 'poverty_change_logs table is required');
}

const householdsTable = schema.match(/CREATE TABLE IF NOT EXISTS `households` \([\s\S]*?\n\) ENGINE=/);
assert(householdsTable, 'households table must be present in schema');
assert(!/\bis_poor\b/i.test(householdsTable[0]), 'households must not store is_poor');
assert(!/\bis_near_poor\b/i.test(householdsTable[0]), 'households must not store is_near_poor');

[
  '/api/poverty/periods',
  '/api/poverty/records',
  '/api/poverty/dashboard',
  '/api/poverty/report',
  '/api/poverty/export-excel',
  '/api/poverty/export-pdf',
  '/api/poverty/households/{householdId}/history'
].forEach(route => assert(index.includes(route), `missing route ${route}`));

assert(model.includes('closeCurrentRecord'), 'record changes must close the previous active record');
assert(model.includes('writeChangeLog'), 'module must write poverty change logs');
assert(model.includes('tenantWhere'), 'model must use tenant context');
assert(model.includes('normalizeInputDate'), 'model must normalize browser locale date input');
assert(/\\d\{1,2\}.*\\\/.*\\d\{1,2\}.*\\\/.*\\d\{4\}/.test(model), 'model must accept slash-formatted dates from browser date inputs');
assert(model.includes('checkdate($month, $day, $year)'), 'model must validate normalized date input');
assert(model.includes('status = "DELETED"'), 'period create must handle soft-deleted names blocked by the unique index');
assert(model.includes('deleted_at=NULL') && model.includes('deleted_by=NULL'), 'period restore must clear soft-delete metadata');
assert(model.includes('$samePeriod'), 'period create must be idempotent when the same active period is submitted again');
assert(model.includes('deactivateOtherActivePeriods'), 'activating one poverty period must end other active periods in the tenant');
assert(!/poor_household|near_poor_household/.test(model), 'new poverty module must not use legacy household flags');

assert(controller.includes("requirePermission('poverty'"), 'controller must enforce poverty permissions');
assert(controller.includes('exportExcel') && controller.includes('exportPdf'), 'controller must support Excel and PDF export');
assert(permissions.includes("'poverty'"), 'permissions must include poverty module');

assert(ui.includes("moduleKey: 'povertyManagement'"), 'UI must register povertyManagement module');
assert(ui.includes("permissionScope: 'poverty'"), 'UI must use poverty permission scope');
assert(ui.includes('data-platform-action="poverty.'), 'UI must use Platform Actions');
assert(ui.includes('function isoDate'), 'UI must normalize date input before API submit');
assert(ui.includes('editingRecordSnapshot') && ui.includes('shouldCreateHistory'), 'UI must create a new history record when poverty type changes');
assert(ui.includes('Lịch sử hộ nghèo / hộ cận nghèo'), 'household detail history section is required');
assert(build.includes('assets/js/poverty-management.js'), 'asset build must include poverty-management.js');

assert(platform.includes("moduleKey: 'povertyManagement'"), 'platform registry must include povertyManagement');
assert(platform.includes("{ path: '/poverty'"), 'platform routes must include /poverty');

console.log('poverty-management static checks passed');
