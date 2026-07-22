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
}

{
  const request = read('app/Core/Request.php');
  assert.doesNotMatch(request, /\$_COOKIE\['thon09_token'\]/);
  assert.match(request, /\^\[a-f0-9\]\{64\}\$/);
}

{
  const index = read('index.php');
  assert.match(index, /reject_oversized_api_request/);
  assert.match(index, /redact_security_value/);
  assert.match(index, /redact_security_uri/);
  assert.match(index, /Strict-Transport-Security/);
}

{
  const users = read('app/Models/User.php');
  assert.match(users, /assertRoleAssignmentAllowed/);
  assert.match(users, /actorIsSuperAdmin/);
  assert.match(users, /\['SUPER_ADMIN', 'ADMIN'\]/);
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
  const operation = read('app/Controllers/OperationCenterController.php');
  assert.match(operation, /requireOperationalSourcePermissions/);
  assert.match(operation, /requirePermission\('logs', 'read'\)/);
  assert.match(operation, /requirePermission\('file', 'read'\)/);
  const insights = read('app/Controllers/InsightController.php');
  assert.match(insights, /requirePermission\('household', 'read'\)/);
  assert.match(insights, /requirePermission\('citizen', 'read'\)/);
  const profile = read('app/Controllers/ProfileController.php');
  assert.match(profile, /requireProfileSourcePermission/);
  assert.match(profile, /requirePermission\(\$module === 'citizen' \? 'citizen' : 'household', 'update'\)/);
}

{
  const gis = read('app/Controllers/GisController.php');
  assert.match(gis, /requirePermission\('household', 'read'\)/);
  assert.match(gis, /requirePermission\('citizen', 'read'\)/);
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
  const deploy = read('.github/workflows/deploy-ftp.yml');
  assert.match(deploy, /\.env/);
  assert.match(deploy, /\.ftp-deploy-sync-state-utf8\.json/);
  assert.match(deploy, /config\/database\.local\.php/);
  assert.doesNotMatch(deploy, /protocol:\s*ftp\b/);
  assert.match(deploy, /protocol:\s*ftps\b/);
  const cpanel = read('.cpanel.yml');
  assert.match(cpanel, /--exclude=backups\//);
  assert.match(cpanel, /--exclude=storage\//);
  assert.match(cpanel, /--exclude=\.ftp-deploy-sync-state-utf8\.json/);
  const gitignore = read('.gitignore');
  assert.match(gitignore, /\.ftp-deploy-sync-state-utf8\.json/);
  assert.ok(!fs.existsSync(path.join(root, '.ftp-deploy-sync-state-utf8.json')), 'deploy state file must not be committed');
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
}

console.log('security regression checks passed');
