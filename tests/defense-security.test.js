const fs = require('fs');
const assert = require('assert');

function read(file) { return fs.readFileSync(file, 'utf8'); }

const model = read('app/Models/DefenseSecurity.php');
const controller = read('app/Controllers/DefenseSecurityController.php');
const routes = read('index.php');
const permission = read('app/Models/Permission.php');
const user = read('app/Models/User.php');
const js = read('assets/js/defense-security.js');
const view = read('views/app.php');
const report = read('app/Models/Report.php');

assert(model.includes('defense_nvqs_records'), 'NVQS table must exist');
assert(model.includes('defense_militia_records'), 'Militia table must exist');
assert(model.includes('defense_security_force_records'), 'Security force table must exist');
assert(model.includes('citizen_id BIGINT UNSIGNED NOT NULL'), 'records must link to citizens by citizen_id');
assert(model.includes("REFERENCES citizens(id)"), 'records must FK to citizens(id)');
assert(model.includes('village_id BIGINT UNSIGNED NOT NULL'), 'new tables must be tenant scoped');
assert(model.includes("tenantWhere('c', 'citizens')"), 'citizen joins must be tenant scoped');
assert(model.includes("tenantWhere('h', 'households')"), 'household joins must be tenant scoped');
assert(model.includes('paginateNvqsCandidates'), 'NVQS age/unregistered metrics must list citizens without existing records');
assert(!/nguồn nước|nuoc sach|rural_clean_water/i.test(model), 'defense module must not reuse clean water tables/logic');

assert(controller.includes("requirePermission('defense_security'"), 'controller must enforce defense_security permission');
assert(routes.includes('/api/defense-security/nvqs'), 'NVQS routes missing');
assert(routes.includes('/api/defense-security/militia'), 'militia routes missing');
assert(routes.includes('/api/defense-security/security-force'), 'security force routes missing');
assert(permission.includes('defense_security'), 'permission matrix missing defense_security');
assert(user.includes('defense_security'), 'runtime permission missing defense_security');
assert(report.includes("DefenseSecurity())->report"), 'Report model must route defense reports');

assert(view.includes('defenseSecurityScreen'), 'screen missing');
assert(view.includes('defense-security.min.js'), 'minified JS not loaded');
assert(js.includes('Vui lòng chọn nhân khẩu từ danh sách.'), 'form must reject free-text citizen input');
assert(js.includes('data-platform-action="defenseSecurity.selectCitizen"'), 'citizen autocomplete selection missing');
assert(js.includes('toIsoDate'), 'date normalization must use dd/mm/yyyy input');
assert(js.includes('defenseSecurity.createForCitizen'), 'candidate rows must allow creating a real NVQS record by citizen_id');

assert(model.includes("elseif ($metric === 'registration_age') $where[] = \"$ageExpr = \" . (int) $settings['nvqs_registration_age']"), '17-year NVQS registration list must use nvqs_registration_age');
assert(model.includes("$ageExpr BETWEEN \" . (int) $settings['nvqs_call_age'] . ' AND ' . (int) $settings['nvqs_follow_end_age']"), 'call-up tracking age must start at nvqs_call_age, not nvqs_registration_age');

console.log('defense-security static regression checks passed');
