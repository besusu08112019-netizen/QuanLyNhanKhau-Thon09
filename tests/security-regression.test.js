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
  assert.ok(index.includes('TenantGuard::enforce($' + 'request)'));
  assert.doesNotMatch(index, /function enforce_tenant_registry_status/);
  assert.match(index, /PortalContext::isPublic\(\)/);
  assert.match(index, /control_center_disabled/);
  const tenantGuard = read('app/Core/TenantGuard.php');
  assert.match(tenantGuard, /PortalContext::isControlCenter\(\)/);
  assert.match(tenantGuard, /PortalContext::isPublic\(\)/);
  assert.match(tenantGuard, /TenantRegistryStatusService/);
  assert.match(tenantGuard, /Response::json\(\[/);
  assert.ok(tenantGuard.includes("'error' => $" + 'errorCode'));
  assert.match(tenantGuard, /TENANT_LOCKED/);
  assert.match(tenantGuard, /noindex,nofollow/);
  assert.match(tenantGuard, /no-store, no-cache/);
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
  assert.match(users, /CentralSuperAdminAuthService/);
  assert.match(users, /createCentralSuperAdminSession/);
  assert.match(users, /ensureCentralSessionColumns/);
  assert.match(users, /function findCentralSuperAdminSessionHolder/);
  assert.doesNotMatch(users, /WHERE role="SUPER_ADMIN" AND status <> "DELETED"/);
  assert.match(users, /FIELD\(role, "ADMIN", "SUPER_ADMIN", "OFFICER", "VIEWER"\)/);
  assert.match(users, /central_user_id/);
  assert.doesNotMatch(users, /function syncCentralSuperAdmin/);
  assert.match(users, /createLoginSession/);
  assert.ok(users.includes("(string) $user['role'] === 'SUPER_ADMIN'"));
  assert.match(users, /expires_at = DATE_ADD\(NOW\(\), INTERVAL :ttl SECOND\)/);
  assert.match(users, /function changePassword\(int \$id, string \$password, int \$actorId\)/);
  assert.match(users, /\$user\['role'\] === 'SUPER_ADMIN'/);
  const sprint10 = read('assets/js/sprint10.js');
  assert.match(sprint10, /function protectedUserActions10/);
  assert.match(sprint10, /SUPER_ADMIN/);
  assert.match(sprint10, /Tai khoan Super Admin duoc bao ve/);
  assert.ok(sprint10.indexOf("const row = await api('/api/users/' + id);") < sprint10.indexOf("const password = prompt('Nhap mat khau moi toi thieu 8 ky tu')"), 'sprint10 must check target role before prompting for reset password');
  const sprint8 = read('assets/js/sprint8.js');
  assert.match(sprint8, /function tenantUserActions8/);
  assert.match(sprint8, /SUPER_ADMIN/);
  assert.match(sprint8, /Tai khoan Super Admin duoc bao ve/);
  assert.ok(sprint8.indexOf("const row = await api('/api/users/' + id);") < sprint8.indexOf("const password = prompt('Nhap mat khau moi toi thieu 8 ky tu')"), 'sprint8 must check target role before prompting for reset password');
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
  assert.ok(deploy.indexOf('Upload production artifact for audit') < deploy.indexOf('Create production env'), 'audit artifact must be uploaded before production .env is created');
  assert.ok(deploy.indexOf('Create production env') < deploy.indexOf('Deploy to hosting via FTPS'), 'production .env must be created only immediately before FTPS deploy');
  assert.match(deploy, /PLATFORM_ADMIN_ENABLED=true/);
  assert.match(deploy, /PLATFORM_DEFAULT_PORTAL=TENANT/);
  assert.match(deploy, /server-dir:\s*\.\.\/public_html\//);
  assert.doesNotMatch(deploy, /server-dir:\s*\.\.\/tenant-a\.hongphongnb\.com\//);
  assert.doesNotMatch(deploy, /server-dir:\s*\.\.\/tenant-b\.hongphongnb\.com\//);
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
  assert.match(cpanel, /PLATFORM_ADMIN_ENABLED=true/);
  assert.match(cpanel, /PLATFORM_DEFAULT_PORTAL=TENANT/);
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
  const envExample = read('.env.example');
  assert.match(envExample, /CONTROL_CENTER_DB_HOST/);
  assert.match(envExample, /CONTROL_CENTER_DB_DATABASE/);
  assert.match(envExample, /CONTROL_CENTER_DB_USERNAME/);
  const tenantStatusService = read('app/Services/TenantRegistryStatusService.php');
  assert.match(tenantStatusService, /baseEnv/);
  assert.match(tenantStatusService, /website_status/);
  assert.match(tenantStatusService, /database_status/);
  assert.match(tenantStatusService, /SHOW COLUMNS FROM villages/);
  assert.match(tenantStatusService, /Database::pdo\(\)/);
  assert.ok(tenantStatusService.includes('catch (Throwable $' + 'e)'));
  assert.match(tenantStatusService, /locked_at/);
  assert.match(tenantStatusService, /deleted_at/);
  assert.match(tenantStatusService, /tenant_not_registered/);
  assert.match(tenantStatusService, /registry_unavailable/);
  assert.match(tenantStatusService, /registry_not_configured/);
  assert.match(tenantStatusService, /locked\('registry_not_configured'/);
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
  const policyAlerts = read('assets/js/policy-alerts.js');
  const policyConfig = read('config/policy_alerts.php');
  assert.match(policyAlerts, /const SUMMARY_SCREENS = new Set\(\['dashboard', 'persons', 'households'\]\)/);
  assert.match(policyAlerts, /Cảnh báo chính sách/);
  assert.match(policyAlerts, /Tự động tính theo ngày sinh, chỉ hiển thị nhân khẩu đang cư trú và còn sống\./);
  assert.match(policyConfig, /Rà soát chính sách BHYT/);
  assert.match(policyConfig, /Sắp đến tuổi rà soát trợ cấp/);
  assert.match(policyAlerts, /isSummaryScreen\(currentScreen\(\)\)/);
  assert.match(policyAlerts, /removeDashboardCard\(\)/);
  assert.doesNotMatch(policyAlerts, /if \(\$\('#dashboardScreen'\)\) loadSummary\(\);/);
  assert.doesNotMatch(policyAlerts, /document\.addEventListener\('tenant:auth-state', \(\) => setTimeout\(loadSummary/);
  assert.doesNotMatch(policyAlerts, /function init\(\) \{[\s\S]*?installModal\(\);[\s\S]*?wrapDashboardLoader/);
  assert.doesNotMatch(policyAlerts + policyConfig, /Cáº|Tá»|KhÃ|ChÆ|Æ°|Ä‘|Ä|ngÃ|chÃ|xÃ|trÃ/);
}

{
  const repository = read('app/Repositories/AdministrativeUnitRepository.php');
  assert.match(repository, /database_name/);
  assert.match(repository, /database_host/);
  assert.match(repository, /database_charset/);
  assert.match(repository, /website_status/);
  assert.match(repository, /database_status/);
  assert.match(repository, /ssl_status/);
  assert.match(repository, /connection_status/);
  assert.match(repository, /function updateDatabaseHealth/);
  assert.match(repository, /function updateWebsiteHealth/);
  const service = read('app/Services/AdministrativeUnitService.php');
  assert.match(service, /function checkConnection/);
  assert.match(service, /function checkWebsite/);
  assert.match(service, /function openPortal/);
  assert.match(service, /TENANT_REGISTRY_DB_USERNAME/);
  assert.match(service, /Không kết nối được cơ sở dữ liệu/);
  const controller = read('app/Controllers/AdministrativeUnitController.php');
  assert.match(controller, /function checkConnection/);
  assert.match(controller, /function checkWebsite/);
  assert.match(controller, /function openPortal/);
  const index = read('index.php');
  assert.match(index, /check-connection/);
  assert.match(index, /check-website/);
  assert.match(index, /open-portal/);
  assert.match(index, /\/api\/control-center\/audit/);
  const controlCenter = read('views/control-center.php');
  assert.match(controlCenter, /Mở cổng đơn vị/);
  assert.match(controlCenter, /Website/);
  assert.match(controlCenter, /Database/);
  assert.match(controlCenter, /unitDatabaseName/);
  assert.match(controlCenter, /unitDatabaseCharset/);
  assert.match(controlCenter, /Công việc cần xử lý hôm nay/);
  assert.match(controlCenter, /operationsList/);
  assert.match(controlCenter, /auditTenantFilter/);
  const controlCenterRepo = read('app/Repositories/ControlCenterRepository.php');
  assert.match(controlCenterRepo, /operationItems/);
  assert.match(controlCenterRepo, /recentAudit/);
  assert.match(controlCenterRepo, /function audit/);
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


{
  const index = read('index.php');
  assert.match(index, /PlatformSettingsController/);
  assert.match(index, /\/api\/control-center\/configuration/);
  assert.match(index, /configuration\/secret/);
  assert.match(index, /configuration\/maintenance/);
  const repository = read('app/Repositories/PlatformSettingsRepository.php');
  assert.match(repository, /platform_settings/);
  assert.match(repository, /is_secret/);
  assert.match(repository, /CREATE TABLE IF NOT EXISTS platform_settings/);
  const service = read('app/Services/PlatformSettingsService.php');
  assert.match(service, /control_center\.configuration\.read/);
  assert.match(service, /control_center\.configuration\.update/);
  assert.match(service, /control_center\.configuration\.security/);
  assert.match(service, /email\.smtp_password/);
  assert.match(service, /SMTP password updated/);
  assert.match(service, /tenant\.default_status/);
  assert.match(service, /files\.allowed_extensions/);
  assert.match(service, /PLATFORM_MAINTENANCE|maintenance\.platform_enabled/);
  assert.doesNotMatch(service, /status unavailable\s*=>\s*allow/i);
  const permissions = read('app/Services/ControlCenterPermissionService.php');
  assert.match(permissions, /control_center\.configuration\.update/);
  assert.match(permissions, /control_center\.configuration\.security/);
  const tenantGuard = read('app/Core/TenantGuard.php');
  assert.match(tenantGuard, /platformMaintenanceEnabled/);
  assert.match(tenantGuard, /PLATFORM_MAINTENANCE/);
  const view = read('views/control-center.php');
  const configurationSection = view.match(/id="configurationSection"[\s\S]+?id="notificationsSection"/);
  assert.ok(configurationSection, 'configuration section must be present');
  assert.doesNotMatch(configurationSection[0], /Đang phát triển|Sẽ quản lý cấu hình/);
  assert.match(view, /configurationTabs/);
  assert.match(view, /\/api\/control-center\/configuration/);
  assert.match(view, /platformMaintenanceToggle/);
  assert.match(view, /SMTP Password/);
  const migration = read('database/migrations/20260809_130000_platform_settings.sql');
  assert.match(migration, /CREATE TABLE IF NOT EXISTS platform_settings/);
  const adminUnits = read('app/Services/AdministrativeUnitService.php');
  assert.match(adminUnits, /tenant\.default_status/);
  assert.match(adminUnits, /PENDING_ACTIVATION/);
  const installer = read('app/Services/TenantInstallerService.php');
  assert.match(installer, /defaultTenantStatus/);
  assert.match(installer, /tenant\.default_status/);
  assert.doesNotMatch(installer, /INSERT INTO users[\s\S]+"SUPER_ADMIN"/);
  assert.match(installer, /'role' => 'ADMIN'/);
  assert.match(installer, /admin_username/);
  assert.match(migration, /UNIQUE KEY uq_platform_settings_key/);
}
{
  const branding = read('app/Services/PlatformBrandingService.php');
  assert.match(branding, /control_center_logo/);
  assert.match(branding, /default_tenant_logo/);
  assert.match(branding, /default_login_background/);
  assert.match(branding, /storage\/platform-assets/);
  assert.match(branding, /move_uploaded_file/);
  assert.match(branding, /is_uploaded_file/);
  assert.match(branding, /getimagesize/);
  assert.match(branding, /new \\finfo\(FILEINFO_MIME_TYPE\)/);
  assert.match(branding, /php.*phtml.*phar.*cgi.*exe.*js.*html.*svg/s);
  assert.match(branding, /RemoveHandler/);
  assert.match(branding, /X-Content-Type-Options|nosniff|resolveAssetPath/s);

  const settings = read('app/Services/PlatformSettingsService.php');
  assert.match(settings, /branding\.control_center_logo/);
  assert.match(settings, /branding\.favicon/);
  assert.match(settings, /branding\.default_tenant_logo/);
  assert.match(settings, /branding\.default_login_background/);
  assert.match(settings, /function uploadAsset/);
  assert.match(settings, /control_center\.configuration\.update/);
  assert.match(settings, /platform_branding\.asset_uploaded/);
  assert.match(settings, /platform_branding\.asset_reset/);
  assert.match(settings, /identityUpload' => \['enabled' => true/);

  const controller = read('app/Controllers/PlatformSettingsController.php');
  assert.match(controller, /function uploadAsset/);
  assert.match(controller, /function resetAsset/);
  assert.match(controller, /function asset\(string \$type, string \$file\)/);
  assert.match(controller, /Content-Type/);
  assert.match(controller, /nosniff/);

  const index = read('index.php');
  assert.match(index, /\/api\/platform\/settings\/assets/);
  assert.match(index, /\/api\/platform\/assets/);
  assert.match(index, /PlatformBrandingService/);
  assert.match(index, /CONTROL_CENTER_LOGO_HTML/);
  assert.match(index, /PLATFORM_FAVICON_URL/);
  assert.match(index, /TENANT_LOGO_HTML/);
  assert.match(index, /LOGIN_BACKGROUND_STYLE/);
  assert.match(index, /favicon.*PlatformBrandingService/s);

  const cccView = read('views/control-center.php');
  const identityPane = cccView.match(/if \(id === 'identity'\)[\s\S]+?if \(id === 'tenant'\)/);
  assert.ok(identityPane, 'identity configuration pane must be present');
  assert.match(identityPane[0], /brandingAssetCard\('control_center_logo'/);
  assert.match(identityPane[0], /brandingAssetCard\('favicon'/);
  assert.match(identityPane[0], /brandingAssetCard\('default_tenant_logo'/);
  assert.match(identityPane[0], /brandingAssetCard\('default_login_background'/);
  assert.doesNotMatch(identityPane[0], /identity\.logo_url|identity\.favicon_url|identity\.tenant_logo_url|identity\.login_background_url|Upload ảnh đang/);
  assert.match(cccView, /data-branding-file/);
  assert.match(cccView, /uploadPendingBrandingAssets/);
  assert.match(cccView, /configuration\.chooseAsset/);
  assert.match(cccView, /configuration\.resetAsset/);

  const tenantConfig = read('app/Core/TenantConfig.php');
  assert.match(tenantConfig, /PlatformBrandingService/);
  assert.match(tenantConfig, /default_tenant_logo/);
  assert.match(tenantConfig, /default_login_background/);
  assert.match(tenantConfig, /TENANT_LOGO_URL/);
  const appView = read('views/app.php');
  assert.match(appView, /TENANT_LOGO_HTML/);
  assert.match(appView, /LOGIN_BACKGROUND_STYLE/);
}

{
  const tenantConfig = read('app/Core/TenantConfig.php');
  assert.match(tenantConfig, /GlobalCopyrightService/);
  assert.match(tenantConfig, /globalCopyright/);
  assert.match(tenantConfig, /if \(\$key === 'copyright'\) continue/);
  assert.doesNotMatch(tenantConfig, /TENANT_COPYRIGHT/);
  assert.doesNotMatch(tenantConfig, /\(c\) ' \. \$unit/);
  const globalCopyright = read('app/Services/GlobalCopyrightService.php');
  assert.match(globalCopyright, /general\.copyright/);
  assert.ok(globalCopyright.includes('B\u1ea3n quy\u1ec1n thu\u1ed9c v\u1ec1 Th\u00f4n 09')); 
  assert.match(globalCopyright, /CONTROL_CENTER_DB_DATABASE/);
  const settings = read('app/Models/SystemSetting.php');
  const allowedLine = settings.match(/private array \$allowed = \[[^;]+;/);
  assert.ok(allowedLine, 'SystemSetting allowed list must be present');
  assert.doesNotMatch(allowedLine[0], /'copyright'/);
  assert.match(settings, /unset\(\$data\['copyright'\]\)/);
  const platformSettings = read('app/Services/PlatformSettingsService.php');
  assert.match(platformSettings, /general\.copyright/);
  const adminPanel = read('assets/js/admin-panel.js');
  assert.match(adminPanel, /data-global-setting="copyright"/);
  assert.match(adminPanel, /delete payload.copyright/);
  const appJs = read('assets/js/app.utf8.min.js');
  assert.match(appJs, /setText('#loginCopyright', settings.copyright || '')/);
  assert.doesNotMatch(appJs, /unitName ? '.*' + unitName/);
  const migration = read('database/migrations/20260809_150000_global_copyright.sql');
  assert.match(migration, /general\.copyright/);
  assert.ok(migration.includes('B\u1ea3n quy\u1ec1n thu\u1ed9c v\u1ec1 Th\u00f4n 09')); 
}

{
  const repo = read('app/Repositories/ControlCenterUserRepository.php');
  assert.match(repo, /function tenantLocalVillageId\(PDO \$pdo, array \$tenant\): int/);
  assert.match(repo, /'village_id' => \$this->tenantLocalVillageId\(\$pdo, \$tenant\)/);
  assert.match(repo, /'unit_id' => \$this->tenantLocalVillageId\(\$pdo, \$tenant\)/);
  assert.doesNotMatch(repo, /'village_id' => \$data\['unit_id'\]/);
  assert.match(repo, /SHOW TABLES LIKE 'villages'/);
  assert.match(repo, /SELECT COUNT\(\*\) FROM villages/);
  assert.match(repo, /INSERT INTO villages/);
  assert.match(repo, /function ensureTenantUserCompatibility/);
  assert.match(repo, /ALTER TABLE users ADD COLUMN username/);
  assert.match(repo, /UPDATE users SET username = LOWER\(SUBSTRING_INDEX\(email, '@', 1\)\)/);
  assert.match(repo, /u\.username AS username/);
  assert.match(repo, /u\.phone AS phone/);
  assert.match(repo, /u\.position AS position/);
  assert.match(repo, /isInternalTenantSuperAdminHolder/);
  assert.ok(repo.includes("$code !== '' && $code !== 'default'"));
  assert.doesNotMatch(repo, /if \(!\$this->hasColumn\('username'\)\) \{\s*return false;\s*\}/);
}

console.log('security regression checks passed');
