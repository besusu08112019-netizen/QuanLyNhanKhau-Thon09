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
  assert.match(index, /Strict-Transport-Security/);
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
