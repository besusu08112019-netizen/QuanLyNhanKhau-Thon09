const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

const model = read('app/Models/AgriculturalLandZone.php');
const controller = read('app/Controllers/AgriculturalLandZoneController.php');
const userModel = read('app/Models/User.php');
const permissionModel = read('app/Models/Permission.php');
const routes = read('index.php');
const view = read('views/app.php');
const platform = read('assets/js/app-platform.js');
const legacyApp = read('assets/js/app.utf8.min.js');
const navigationPatches = read('assets/js/view-inline-patches.js');
const landJs = read('assets/js/agricultural-land.js');
const mobileComponents = read('assets/js/mobile-component-library.js');
const appCss = read('assets/css/app.css');
const reportController = read('app/Controllers/ReportController.php');
const reportModel = read('app/Models/Report.php');
const migration = read('database/migrations/20260726_090000_create_agricultural_land_zones.sql');

assert.match(model, /class AgriculturalLandZone/);
assert.match(model, /CREATE TABLE IF NOT EXISTS agricultural_land_zones/);
assert.match(model, /CREATE TABLE IF NOT EXISTS land_usage_types/);
assert.match(model, /CREATE TABLE IF NOT EXISTS agricultural_land_zone_usage_areas/);
assert.match(model, /total_area_m2/);
assert.match(model, /report_year/);
assert.match(model, /latitude/);
assert.match(model, /longitude/);
assert.match(model, /polygon_json/);
assert.match(model, /private const FIXED_UNIT = 'mau'/);
assert.doesNotMatch(model, /fromM2|toM2|factor|number_format|round\(/, 'agricultural land must not convert or round area units');
assert.match(model, /areaDecimal/);
assert.match(model, /decimalText/);
assert.match(model, /dashboard\(array \$filters = \[\]\)/);
assert.match(model, /usageTypes\(bool \$activeOnly = true\)/);
assert.match(model, /upsertUsageType/);
assert.match(model, /usageAreasForZones/);
assert.match(model, /validUsageTypeIds/);
assert.match(model, /assertUniqueZoneCodeYear/);
assert.match(model, /assertUsageAreasWithinTotal/);
assert.match(model, /total_area_m2'\] <= 0/);
assert.match(model, /usageTotal > \(float\)\$totalArea/);
assert.match(model, /Polygon JSON không hợp lệ/);

['rice_area_m2', 'annual_crop_area_m2', 'perennial_crop_area_m2', 'aquaculture_area_m2', 'abandoned_area_m2', 'other_area_m2'].forEach(column => {
  assert.doesNotMatch(migration, new RegExp('`?' + column + '`?', 'i'), 'zone migration must not use fixed usage column ' + column);
});

['households', 'citizens', 'agriculture_production', 'gis_parcels'].forEach(table => {
  assert.doesNotMatch(migration, new RegExp('REFERENCES\\s+`?' + table + '`?', 'i'), 'migration must not reference ' + table);
  assert.doesNotMatch(model, new RegExp('JOIN\\s+`?' + table + '`?', 'i'), 'model must not join ' + table);
});

assert.match(controller, /requirePermission\('agricultural_land', 'read'\)/);
assert.match(controller, /requirePermission\('agricultural_land', 'create'\)/);
assert.match(controller, /requirePermission\('agricultural_land', 'update'\)/);
assert.match(controller, /requirePermission\('agricultural_land', 'delete'\)/);
assert.match(userModel, /module === 'agricultural_land'[\s\S]+action === 'read'/);
assert.match(permissionModel, /module'\] \?\? ''\) === 'agricultural_land'[\s\S]+!== 'read'/);
assert.match(routes, /\/api\/agricultural-land/);
assert.match(routes, /\/api\/agricultural-land\/usage-types/);
assert.match(routes, /assets\/js\/agricultural-land\.min\.js/);

assert.match(view, /id="agriculturalLandScreen"/);
assert.match(view, /id="agriculturalLandModal"/);
assert.match(view, /id="agriculturalLandUsageTypeModal"/);
assert.match(view, /id="agriculturalLandUsageAreaFields"/);
assert.match(view, /data-platform-action="agriculturalLand\.create"/);
assert.match(view, /id="agriculturalLandListAddBtn"/);
assert.match(view, /assets\/js\/agricultural-land\.min\.js/);
assert.match(platform, /moduleKey: 'agriculturalLand'/);
assert.match(platform, /permissionScope: 'agricultural_land'/);
assert.match(platform, /key === 'dashboard' \|\| key === 'production'/);
assert.match(legacyApp, /agriculturalLand:\s*'agricultural_land'/);
assert.match(legacyApp, /'agricultural_land','agriculture'/);
assert.match(legacyApp, /module === 'agricultural_land' && action === 'read'/);
assert.match(legacyApp, /api\\\/agricultural-land/);
assert.match(navigationPatches, /el\.querySelector&&el\.querySelector\('button, \[data-platform-action\], a\.btn, \.btn'\)/);
assert.match(landJs, /function validateForm/);
assert.match(landJs, /state = \{[\s\S]+unit: 'mau'/);
assert.match(landJs, /payload\.unit = 'mau'/);
assert.match(landJs, /area: String\(input\.value \|\| '0'\)\.replace/);
assert.doesNotMatch(landJs, /usage_areas[\s\S]{0,180}area: Number/, 'usage areas must be sent as original decimal text');
assert.doesNotMatch(landJs, /Math\.round|toFixed|parseInt/, 'agricultural land frontend must not round or parse integer area values');
assert.match(landJs, /Tổng diện tích phải lớn hơn 0/);
assert.match(landJs, /Tổng diện tích các loại sử dụng đất không được lớn hơn tổng diện tích khu/);
assert.match(mobileComponents, /agriculturalLandScreen/);
assert.match(mobileComponents, /#agriculturalLandListAddBtn/);
assert.match(appCss, /#agriculturalLandListAddBtn/);

assert.match(reportModel, /agricultural-land/);
assert.match(reportModel, /agricultural-land-year-compare/);
assert.match(reportController, /agricultural_land'\] => \['agricultural_land'\]|agricultural_land/);
assert.match(reportController, /module === 'agricultural_land' \? \$operation : 'read'/);

console.log('Agricultural land module static checks passed');
