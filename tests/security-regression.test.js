const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

{
  const auth = read('app/Controllers/AuthController.php');
  assert.match(auth, /LOGIN_MAX_FAILURES/);
  assert.match(auth, /assertLoginAllowed/);
  assert.match(auth, /recordLoginFailure/);
  assert.match(auth, /Too many login attempts/);
  assert.match(auth, /destroyPhpSession/);
  assert.match(auth, /session_destroy/);
  assert.match(auth, /function keepAlive/);
}

{
  const backup = read('app/Controllers/BackupController.php');
  assert.match(backup, /requireSuperAdmin\('backup', 'restore'\)/);
  assert.match(backup, /requireSuperAdmin\('backup', 'export'\)/);
  const backupModel = read('app/Models/Backup.php');
  assert.match(backupModel, /-- Signature:/);
  assert.match(backupModel, /hash_hmac\('sha256'/);
  assert.match(backupModel, /verifyBackupSignature/);
}

{
  const base = read('app/Core/BaseController.php');
  assert.match(base, /function requireSuperAdmin/);
  assert.match(base, /SUPER_ADMIN/);
  const baseModel = read('app/Core/BaseModel.php');
  assert.match(baseModel, /paramsForSql/);
  assert.match(baseModel, /isset\(\$placeholders\['village_id'\]\)/);
  assert.match(baseModel, /TenantContext::id\(\)/);
}

{
  const request = read('app/Core/Request.php');
  assert.doesNotMatch(request, /\$_COOKIE\['tenantStorageKey('token')'\]/);
  assert.match(request, /\^\[a-f0-9\]\{64\}\$/);
}

{
  const index = read('index.php');
  assert.match(index, /reject_oversized_api_request/);
  assert.match(index, /redact_security_value/);
  assert.match(index, /redact_security_uri/);
  assert.match(index, /Strict-Transport-Security/);
  assert.match(index, /function versioned_asset/);
  assert.match(index, /idleTimeoutSeconds/);
  assert.match(index, /idleWarningSeconds/);
  assert.match(index, /\/api\/auth\/keepalive/);
  assert.match(index, /'assets\/vendor\/bootstrap\/bootstrap\.min\.css'/);
  assert.match(index, /'assets\/vendor\/bootstrap\/bootstrap\.bundle\.min\.js'/);
  assert.match(index, /'assets\/css\/print\.min\.css'/);
  assert.match(index, /'assets\/js\/report\.min\.js'/);
  const appView = read('views/app.php');
  assert.doesNotMatch(appView, /<\?=/);
  assert.match(appView, /href="\/assets\/css\/app\.min\.css"/);
  assert.match(appView, /src="\/assets\/js\/pwa\.min\.js"/);
  assert.match(appView, /class="skip-link" href="#mainContent"/);
  assert.match(appView, /id="toastHost"[\s\S]+aria-live="polite"/);
  assert.match(appView, /<meta name="robots" content="nosnippet">/);
  assert.match(appView, /id="loginForm"[\s\S]+autocomplete="off"[\s\S]+data-lpignore="true"[\s\S]+data-1p-ignore[\s\S]+data-bwignore[\s\S]+data-protonpass-ignore/);
  assert.match(appView, /id="loginEmail"[\s\S]+autocomplete="off"[\s\S]+autocorrect="off"[\s\S]+autocapitalize="off"[\s\S]+spellcheck="false"[\s\S]+data-lpignore="true"[\s\S]+data-1p-ignore[\s\S]+data-bwignore[\s\S]+data-protonpass-ignore/);
  assert.match(appView, /id="loginPassword"[\s\S]+autocomplete="off"[\s\S]+autocorrect="off"[\s\S]+autocapitalize="off"[\s\S]+spellcheck="false"[\s\S]+data-lpignore="true"[\s\S]+data-1p-ignore[\s\S]+data-bwignore[\s\S]+data-protonpass-ignore/);
  assert.doesNotMatch(appView, /<meta\s+name=["']description["']/i);
  assert.doesNotMatch(appView, /<meta\s+property=["']og:description["']/i);
  assert.doesNotMatch(appView, /<meta\s+name=["']twitter:description["']/i);
  const appJs = read('assets/js/app.utf8.min.js');
  const sessionJs = read('assets/js/session.js');
  assert.match(sessionJs, /BroadcastChannel/);
  assert.match(sessionJs, /IDLE|idleTimeoutWarningModal|last_activity_at/);
  assert.match(sessionJs, /\/api\/auth\/logout/);
  assert.match(sessionJs, /\/api\/auth\/keepalive/);
  assert.match(sessionJs, /contextmenu/);
  assert.match(sessionJs, /Phiên làm việc đã hết hạn do không có hoạt động/);
  const passwordToggleBlock = appJs.match(/const toggle = \$\('\[data-password-toggle\]'[\s\S]+?hydrateLoginIntro\(\);/);
  assert.ok(passwordToggleBlock, 'login password toggle block must be present');
  assert.match(passwordToggleBlock[0], /password\.type = visible \? 'password' : 'text'/);
  assert.doesNotMatch(passwordToggleBlock[0], /innerHTML|outerHTML|replaceChild|cloneNode|removeChild|appendChild|createElement\(['"]input['"]\)/);
  const appCss = read('assets/css/app.css');
  const cssBlock = (selector) => {
    const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const match = appCss.match(new RegExp(escaped + '\\s*\\{([^}]*)\\}'));
    assert.ok(match, selector + ' CSS block must be present');
    return match[1];
  };
  assert.doesNotMatch(cssBlock('.login-view::before'), /(filter|transform)\s*:/);
  assert.doesNotMatch(cssBlock('.login-panel'), /(backdrop-filter|animation)\s*:/);
  assert.match(cssBlock('.login-input-wrap'), /transition: border-color \.18s ease, box-shadow \.18s ease;/);
}

{
  const users = read('app/Models/User.php');
  assert.match(users, /assertRoleAssignmentAllowed/);
  assert.match(users, /actorIsSuperAdmin/);
  assert.match(users, /\['SUPER_ADMIN', 'ADMIN'\]/);
  assert.match(users, /function touchSession/);
  assert.match(users, /expires_at = DATE_ADD\(NOW\(\), INTERVAL :ttl SECOND\)/);
}

{
  const permissions = read('app/Controllers/PermissionController.php');
  assert.match(permissions, /requireSuperAdmin\('permission', 'read'\)/);
  assert.match(permissions, /requireSuperAdmin\('permission', 'update'\)/);
  const permissionModel = read('app/Models/Permission.php');
  assert.match(permissionModel, /private const MODULES/);
  assert.match(permissionModel, /private const ACTIONS/);
  assert.match(permissionModel, /in_array\(\$module, self::MODULES, true\)/);
}

{
  const systemAdmin = read('app/Controllers/SystemAdminController.php');
  assert.match(systemAdmin, /requireSuperAdmin\('system_admin'/);
  assert.doesNotMatch(systemAdmin, /\['SUPER_ADMIN', 'ADMIN'\]/);
}

{
  const reports = read('app/Controllers/ReportController.php');
  assert.match(reports, /requireReportSourcePermissions/);
  assert.match(reports, /sourceModulesForReportType/);
  assert.match(reports, /bi-dashboard/);
  assert.match(reports, /public_assets/);
}

{
  const householdBusiness = read('app/Models/HouseholdBusiness.php');
  const tenantScopedFunctions = [
    'householdSummaries',
    'find',
    'findAllByHousehold',
    'searchHouseholds',
    'where',
    'params',
    'files',
    'file',
  ];
  for (const functionName of tenantScopedFunctions) {
    const block = householdBusiness.match(new RegExp(`function ${functionName}\\([\\s\\S]+?\\n    }`));
    assert.ok(block, `${functionName} must be present in HouseholdBusiness`);
    assert.match(block[0], /tenantWhere\(/, `${functionName} must enforce tenant scope`);
  }
}

{
  const operation = read('app/Controllers/OperationCenterController.php');
  assert.match(operation, /requireOperationalSourcePermissions/);
  assert.match(operation, /requirePermission\('logs', 'read'\)/);
  assert.match(operation, /requirePermission\('file', 'read'\)/);
  const profile = read('app/Controllers/ProfileController.php');
  assert.match(profile, /requireProfileSourcePermission/);
  assert.match(profile, /requirePermission\(\$module === 'citizen' \? 'citizen' : 'household', 'update'\)/);
}

{
  const gis = read('app/Controllers/GisController.php');
  assert.match(gis, /requirePermission\('household', 'read'\)/);
  assert.match(gis, /requirePermission\('citizen', 'read'\)/);
  assert.match(gis, /positiveIntQuery\('limit'\)/);
  const gisLocations = read('app/Models/GisHouseholdLocation.php');
  assert.match(gisLocations, /function markerLimit/);
  assert.match(gisLocations, /LIMIT ' \. \$limit/);
}

{
  const importController = read('app/Controllers/ImportController.php');
  assert.match(importController, /assertZipEntrySafe/);
  assert.match(importController, /statName/);
}

{
  const publicAssets = read('app/Models/PublicAsset.php');
  assert.doesNotMatch(publicAssets, /cover_photo_url' => \$this->nullable\(\$data\['cover_photo_url'\]/);
  assert.doesNotMatch(publicAssets, /photo_url' => \$this->nullable\(\$data\['photo_url'\]/);
  const publicAssetController = read('app/Controllers/PublicAssetController.php');
  assert.match(publicAssetController, /isPublicAssetPhotoPath/);
}

{
  const pkg = JSON.parse(read('package.json'));
  assert.match(pkg.scripts['validate:artifact'], /tools\/validate-production-artifact\.js/);
  const deploy = read('.github/workflows/deploy-ftp.yml');
  assert.match(deploy, /npm run build:production/);
  assert.match(deploy, /npm run check:js/);
  assert.match(deploy, /npm run test:platform/);
  assert.match(deploy, /npm run test:navigation-cleanup/);
  assert.match(deploy, /node tests\/security-regression\.test\.js/);
  assert.match(deploy, /php -l/);
  assert.match(deploy, /npm run validate:artifact/);
  assert.ok(deploy.indexOf('Run pre-deploy checks') < deploy.indexOf('Deploy to hosting via FTPS'), 'pre-deploy checks must run before FTPS deploy');
  assert.ok(deploy.indexOf('Validate production artifact') < deploy.indexOf('Deploy to hosting via FTPS'), 'artifact validation must run before FTPS deploy');
  assert.match(deploy, /local-dir:\s*\.\/dist\/production\//);
  assert.doesNotMatch(deploy, /protocol:\s*ftp\b/);
  assert.match(deploy, /protocol:\s*ftps\b/);
  assert.match(deploy, /actions\/upload-artifact@v4/);
  assert.match(deploy, /name:\s*production-artifact/);
  assert.ok(deploy.indexOf('Upload production artifact for audit') < deploy.indexOf('Create Control Center production env'), 'audit artifact must be uploaded before production .env is created');
  assert.ok(deploy.indexOf('Create Control Center production env') < deploy.indexOf('Deploy to hosting via FTPS'), 'production .env must be created only immediately before FTPS deploy');
  assert.match(deploy, /server-dir:\s*\.\.\/public_html\//);
  assert.doesNotMatch(deploy, /server-dir:\s*\.\.\/thon09\.hongphongnb\.com\//);
  assert.doesNotMatch(deploy, /server-dir:\s*\.\.\/thon10\.hongphongnb\.com\//);
  assert.match(deploy, /state-name:\s*\.ftp-deploy-control-center-state\.json/);
  assert.match(deploy, /log-level:\s*verbose/);
  assert.match(deploy, /\.env/);
  const cpanel = read('.cpanel.yml');
  assert.match(cpanel, /DEPLOYPATH=/);
  assert.match(cpanel, /\/bin\/tar/);
  assert.doesNotMatch(cpanel, /rsync/);
  assert.match(cpanel, /--exclude='\.\/\.git'/);
  assert.match(cpanel, /--exclude='\.\/\.env'/);
  assert.match(cpanel, /--exclude='\.\/uploads'/);
  const artifact = read('tools/build-production-artifact.js');
  assert.match(artifact, /Forbidden production artifact entries/);
  assert.match(artifact, /'\.git'/);
  assert.match(artifact, /'\.github'/);
  assert.match(artifact, /'\.env'/);
  assert.match(artifact, /'docs'/);
  assert.match(artifact, /'tests'/);
  assert.match(artifact, /'tools'/);
  assert.match(artifact, /'sample-data'/);
  assert.match(artifact, /'package\.json'/);
  assert.match(artifact, /'composer\.json'/);
  const artifactValidator = read('tools/validate-production-artifact.js');
  assert.match(artifactValidator, /Required production artifact file is missing/);
  assert.match(artifactValidator, /Forbidden production artifact entry/);
  assert.match(artifactValidator, /index\.php/);
  assert.match(artifactValidator, /app\/Controllers\/GisController\.php/);
  const releaseProcess = read('docs/PRODUCTION_DEPLOY_PROCESS.md');
  assert.match(releaseProcess, /Production Release Process/);
  assert.match(releaseProcess, /cPanel Git Deploy is not used/);
  assert.match(releaseProcess, /Commit Standard/);
  assert.match(releaseProcess, /Rollback/);
  assert.match(releaseProcess, /Security Release Gate/);
  const reportTemplate = read('docs/RELEASE_REPORT_TEMPLATE.md');
  assert.match(reportTemplate, /Commit SHA:/);
  assert.match(reportTemplate, /Overall: PASS\/FAIL/);
  const v21Checklist = read('docs/V2_1_RELEASE_CHECKLISTS.md');
  assert.match(v21Checklist, /Version 2\.1 Release Checklists/);
  assert.match(v21Checklist, /QA Checklist/);
  assert.match(v21Checklist, /Deploy Checklist/);
  assert.match(v21Checklist, /Backup Checklist/);
  assert.match(v21Checklist, /Security Checklist/);
  assert.match(v21Checklist, /Release Checklist/);
  const gitignore = read('.gitignore');
  assert.match(gitignore, /\.ftp-deploy-sync-state-utf8\.json/);
  assert.match(gitignore, /\.ftp-deploy-control-center-state\.json/);
  assert.ok(!fs.existsSync(path.join(root, '.ftp-deploy-sync-state-utf8.json')), 'deploy state file must not be committed');
  assert.ok(!fs.existsSync(path.join(root, '.ftp-deploy-control-center-state.json')), 'control center deploy state file must not be committed');
}

{
  const index = read('index.php');
  assert.match(index, /function production_log_message/);
  const logFunction = index.match(/function api_log_exception[\s\S]+?\n}\r?\nfunction app_debug_enabled/);
  assert.ok(logFunction, 'api_log_exception must be present');
  assert.doesNotMatch(logFunction[0], /lastQuery/);
  assert.doesNotMatch(logFunction[0], /'sql'/);
  assert.doesNotMatch(logFunction[0], /'sql_params'/);
  const gis = read('app/Controllers/GisController.php');
  assert.doesNotMatch(gis, /getTraceAsString\(\)/);
  assert.doesNotMatch(gis, /getFile\(\)/);
  const gisArea = read('app/Models/GisArea.php');
  assert.doesNotMatch(gisArea, /'sql'\s*=>\s*\$this->lastSql/);
  assert.doesNotMatch(gisArea, /getTraceAsString\(\)/);
  const operation = read('app/Models/OperationCenter.php');
  assert.doesNotMatch(operation, /'sql'\s*=>\s*\$lastQuery/);
}

{
  const settings = read('app/Controllers/SettingController.php');
  assert.doesNotMatch(settings, /new Dashboard\(\)/);
  assert.match(settings, /'metrics' => \[\]/);
  assert.doesNotMatch(settings, /getTraceAsString\(\)/);
}

{
  const files = read('app/Controllers/FileController.php');
  assert.match(files, /image\/svg\+xml/);
  assert.match(files, /Content-Security-Policy/);
  assert.match(files, /sandbox/);
  assert.ok(
    files.indexOf("$user = $this->requirePermission('file', 'upload');") < files.indexOf('$this->storage->validateEntity($entityType, $entityId);'),
    'file uploads must authenticate before entity validation'
  );
  for (const controller of [
    'app/Controllers/ComplaintController.php',
    'app/Controllers/FinanceController.php',
    'app/Controllers/HouseholdBusinessController.php',
    'app/Controllers/WorkCalendarController.php',
    'app/Controllers/WorkTaskController.php',
  ]) {
    const source = read(controller);
    const dispositionLines = source.split(/\r?\n/).filter((line) => line.includes('Content-Disposition:'));
    for (const line of dispositionLines) {
      assert.doesNotMatch(line, /addslashes\(/);
      if (line.includes('basename(')) assert.match(line, /rawurlencode\(basename\(/);
    }
    assert.match(source, /Content-Disposition:[\s\S]*?rawurlencode\(basename\(/);
  }
}

{
  const htaccess = read('.htaccess');
  assert.match(htaccess, /X-Robots-Tag "nosnippet"/);
  assert.match(htaccess, /\(app\|config\|database\|docs\|storage\|backups\|tests\|tools\|sample-data/);
  assert.doesNotMatch(htaccess, /\(app\|config\|database\|docs\|uploads\|storage\|backups\|tests\|tools\|sample-data/);
  assert.match(htaccess, /\^uploads\/\.\*\\\.\(php\|phtml\|phar\|cgi\|pl\|asp\|aspx\|jsp\)\$/);
  assert.match(read('uploads/.htaccess'), /Options -Indexes/);
  assert.match(read('uploads/.htaccess'), /Require all denied/);
  assert.match(read('offline.html'), /<meta name="robots" content="nosnippet">/);
  assert.match(read('robots.txt'), /Disallow: \//);
  assert.match(read('sitemap.xml'), /<urlset/);
  const artifact = read('tools/build-production-artifact.js');
  assert.doesNotMatch(artifact, /'ai'/);
  assert.match(artifact, /'robots\.txt'/);
  assert.match(artifact, /'sitemap\.xml'/);
}

console.log('security regression checks passed');
