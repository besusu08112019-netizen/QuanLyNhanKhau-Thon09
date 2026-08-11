const assert = require('assert');
const fs = require('fs');

function read(path) {
  return fs.readFileSync(path, 'utf8');
}

const model = read('app/Models/CommunityOrganization.php');
const controller = read('app/Controllers/CommunityOrganizationController.php');
const routes = read('index.php');
const ui = read('assets/js/community-organizations.js');
const permissions = read('app/Models/Permission.php');
const user = read('app/Models/User.php');

assert.match(model, /CREATE TABLE IF NOT EXISTS organizations/);
assert.match(model, /CREATE TABLE IF NOT EXISTS organization_members/);
assert.match(model, /CREATE TABLE IF NOT EXISTS organization_member_history/);
assert.match(model, /citizen_id BIGINT UNSIGNED NOT NULL/);
assert.match(model, /person_id BIGINT UNSIGNED NULL/);
assert.match(model, /FOREIGN KEY \(citizen_id\) REFERENCES citizens\(id\)/);
assert.match(model, /UNIQUE KEY uq_org_member_current \(village_id, organization_id, active_member_key\)/);
assert.match(model, /active_member_key/);
assert.match(model, /tenantWhere\('c', 'citizens'\)/);
assert.match(model, /organizationByCode/);
assert.match(model, /positionBelongsToOrganization/);
assert.ok(model.includes('array_merge(, ['), 'endMembership must override existing status and ended_date');
assert.match(model, /Nhân khẩu này đã có thông tin đang tham gia tổ chức đã chọn/);

for (const route of [
  "/api/organizations",
  "/api/organizations/dashboard",
  "/api/organizations/catalogs",
  "/api/organizations/citizen-search",
  "/api/organizations/citizen/{citizenId}",
  "/api/organizations/report",
  "/api/organizations/{id}/history",
  "/api/organizations/{id}/end"
]) {
  assert.ok(routes.includes(route), `Missing route ${route}`);
}

for (const action of ['read', 'create', 'update', 'delete', 'export']) {
  assert.ok(controller.includes(`requirePermission('organizations', '${action}')`), `Missing backend permission ${action}`);
}
assert.match(permissions, /'organizations'/);
assert.match(permissions, /'manage'/);
assert.match(user, /'organizations'/);

assert.match(ui, /const API = '\/api\/organizations'/);
assert.match(ui, /Tìm nhân khẩu/);
assert.match(ui, /communityOrganizations\.selectCitizen/);
assert.match(ui, /Vui lòng chọn nhân khẩu từ danh sách\./);
assert.match(ui, /has_current_membership/);
assert.match(ui, /Authorization/);
assert.match(ui, /X-CSRF-Token/);
assert.match(ui, /permissionScope: 'citizen'/);
assert.match(ui, /window\.loadCommunityOrganizations = load/);

console.log('community organizations static checks passed');
