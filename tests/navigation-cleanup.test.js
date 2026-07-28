const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const scanPaths = [
  'assets/js',
  'views'
];
const cssScanFiles = [
  'assets/css/app.css'
];

const forbidden = [
  { pattern: /window\.showApp\s*=/, reason: 'showApp monkey-patching is replaced by tenant:auth-state' },
  { pattern: /hardNavigate\s*\(/, reason: 'hardNavigate duplicates NavigationController' },
  { pattern: /window\.switchScreen\b/, reason: 'window.switchScreen duplicates NavigationController' },
  { pattern: /window\.showScreen\b/, reason: 'window.showScreen duplicates NavigationController' },
  { pattern: /navigationRepairModule\b/, reason: 'repair modules are not part of single-source navigation' },
  { pattern: /nav\.innerHTML\s*=\s*menu\.filter/, reason: 'menu rendering must come from TenantAppPlatform' },
  { pattern: /btn\.dataset\.screen\s*=\s*['"]systemAdmin['"]/, reason: 'systemAdmin menu item must come from TenantAppPlatform' },
  { pattern: /data-screen=["']import["'][\s\S]{0,120}insertAdjacentHTML|insertAdjacentHTML[\s\S]{0,120}data-screen=["']import["']/, reason: 'import menu item must come from TenantAppPlatform' },
  { pattern: /data-screen=["']users["'][\s\S]{0,180}data-screen=["']logs["'][\s\S]{0,180}data-screen=["']backups["']/, reason: 'admin menu items must come from TenantAppPlatform' }
];

const forbiddenCss = [
  { pattern: /mobile-filter-active/, reason: 'legacy mobile filter body state is replaced by .mdu-filter-*' },
  { pattern: /mobile-action-system/, reason: 'legacy mobile action shell is no longer produced by runtime code' },
  { pattern: /mobile-pager-system/, reason: 'legacy mobile pager shell is no longer produced by runtime code' },
  { pattern: /#(?:householdsScreen|personsScreen|businessHouseholdsScreen)[^{]+tbody td:nth-child\(/, reason: 'module-specific table-to-card CSS must not replace the shared .mdu-* renderer' },
  { pattern: /#(?:agricultureScreen|livestockScreen)[^{]+tbody td:nth-child\(/, reason: 'module-specific table-to-card CSS must not replace the shared .mdu-* renderer' }
];

const allowedClickListeners = new Map([
  ['assets/js/app-platform.js', [
    "host.addEventListener('click', handleClick);",
    "root.addEventListener('click', handler);",
    "root.addEventListener('click', function (event) {"
  ]],
  ['assets/js/gis-household-location.js', [
    "document.addEventListener('click', event => {",
    "button.addEventListener('click', event => {"
  ]],
  ['assets/js/contributions.js', [
    "$('#contributionResetBtn').onclick=",
    "b[0].onclick=",
    "b[1].onclick=",
    "b=>b.onclick=()=>{state.campaign=",
    "b=>b.onclick=()=>openCampaign",
    "b=>b.onclick=()=>removeCampaign",
    "b=>b.onclick=()=>openPayment"
  ]],
  ['assets/js/pwa.js', [
    "bar.querySelector('[data-pwa-sync]').addEventListener('click', flushQueueSoon);",
    "btn.addEventListener('click', promptInstall);",
    "banner.querySelector('button').addEventListener('click', applyServiceWorkerUpdate);"
  ]],
  ['assets/js/session.js', [
    "warningModalEl.querySelector('[data-idle-continue]').addEventListener('click', () => recordActivity(true));",
    "warningModalEl.querySelector('[data-idle-logout]').addEventListener('click', () => performLogout('manual'));",
    "document.addEventListener('click', event => {",
    "document.addEventListener('click', logoutClickListener, true);"
  ]],
  ['assets/js/vehicles.js', [
    "$('#vehicleResetBtn').addEventListener('click'",
    "$('#vehicleAddBtn')?.addEventListener('click'",
    "['#vehicleAddBtn','#vehicleAddBtnInline','#vehicleAddBtnList'].forEach",
    "$('#vehicleExportExcel').addEventListener('click'",
    "$('#vehicleExportPdf').addEventListener('click'",
    ".onclick="
  ]],
  ['assets/js/view-inline-patches.js', [
    "document.addEventListener('click',function(event){var item=event.target.closest&&event.target.closest('[data-screen],[data-mobile-screen]');if(!item||item.classList.contains('gov-logout'))return;var delegation=window.TenantAppPlatform&&window.TenantAppPlatform.navigationDelegation;"
  ]],
  ['assets/js/mobile-component-library.js', [
    "document.addEventListener('click', function (event) {",
    "sheet.addEventListener('click', function (event) {",
    "paginationHost.addEventListener('click', function (event) {",
    "button.addEventListener('click', fallback.handler);"
  ]],
  ['views/control-center.php', [
    "button.addEventListener('click', () => activateSection(button.dataset.section));",
    "portal.addEventListener('click', () => openTenantPortal(unit));",
    "checkWebsite.addEventListener('click', () => checkUnitWebsite(unit));",
    "checkDatabase.addEventListener('click', () => checkUnitConnection(unit));",
    "check.addEventListener('click', () => checkUnitConnection(unit));",
    "edit.addEventListener('click', () => openUnitModal(unit));",
    "lock.addEventListener('click', () => changeUnitStatus(unit, 'lock'));",
    "activate.addEventListener('click', () => changeUnitStatus(unit, 'activate'));",
    "edit.addEventListener('click', () => openAccountModal(account));",
    "password.addEventListener('click', () => openPasswordModal(account));",
    "deactivate.addEventListener('click', () => changeAccountStatus(account, 'deactivate'));",
    "activate.addEventListener('click', () => changeAccountStatus(account, 'activate'));",
    "button.addEventListener('click', () => {",
    "document.getElementById('logoutButton').addEventListener('click', logout);",
    "button.addEventListener('click', () => activateSection(button.dataset.goSection));",
    "document.getElementById('addUnitButton').addEventListener('click', () => openUnitModal());",
    "document.getElementById('refreshUnitsButton').addEventListener('click', () => loadUnits().catch((error) => setUnitsAlert(error.message)));",
    "document.getElementById('closeUnitModalButton').addEventListener('click', closeUnitModal);",
    "document.getElementById('cancelUnitButton').addEventListener('click', closeUnitModal);",
    "document.getElementById('unitModal').addEventListener('click', (event) => {",
    "document.getElementById('addAccountButton').addEventListener('click', () => openAccountModal());",
    "document.getElementById('refreshAccountsButton').addEventListener('click', () => loadAccounts());",
    "document.getElementById('closeAccountModalButton').addEventListener('click', closeAccountModal);",
    "document.getElementById('cancelAccountButton').addEventListener('click', closeAccountModal);",
    "document.getElementById('accountModal').addEventListener('click', (event) => {",
    "document.getElementById('closePasswordModalButton').addEventListener('click', closePasswordModal);",
    "document.getElementById('cancelPasswordButton').addEventListener('click', closePasswordModal);",
    "document.getElementById('passwordModal').addEventListener('click', (event) => {",
    "document.getElementById('refreshPermissionsButton').addEventListener('click', () => loadPermissions());",
    "document.getElementById('savePermissionsButton').addEventListener('click', savePermissions);"
  ]]
]);

function isAllowedClickListener(relative, line) {
  const allowed = allowedClickListeners.get(relative) || [];
  return allowed.some(snippet => line.includes(snippet));
}

function filesUnder(relativeDir) {
  const absoluteDir = path.join(root, relativeDir);
  const result = [];
  for (const entry of fs.readdirSync(absoluteDir, { withFileTypes: true })) {
    const absolute = path.join(absoluteDir, entry.name);
    if (entry.isDirectory()) {
      result.push(...filesUnder(path.relative(root, absolute)));
    } else if (/\.(js|php)$/.test(entry.name)) {
      result.push(absolute);
    }
  }
  return result;
}

const failures = [];
for (const file of scanPaths.flatMap(filesUnder)) {
  const relative = path.relative(root, file).replace(/\\/g, '/');
  const text = fs.readFileSync(file, 'utf8');
  for (const rule of forbidden) {
    if (rule.pattern.test(text)) {
      failures.push(`${relative}: ${rule.reason}`);
    }
  }
  if ((/^assets\/js\/.+\.js$/.test(relative) && !relative.endsWith('.min.js')) || /^views\/.+\.php$/.test(relative)) {
    text.split(/\r?\n/).forEach((line, index) => {
      if (!/addEventListener\((['"])click\1|\.onclick\s*=|onclick=/.test(line)) return;
      if (!isAllowedClickListener(relative, line)) {
        failures.push(`${relative}:${index + 1}: unexpected direct click handler; use platform actions or add an explicit whitelist reason`);
      }
    });
  }
}

for (const relative of cssScanFiles) {
  const absolute = path.join(root, relative);
  const text = fs.readFileSync(absolute, 'utf8');
  for (const rule of forbiddenCss) {
    if (rule.pattern.test(text)) {
      failures.push(`${relative}: ${rule.reason}`);
    }
  }
}

assert.deepStrictEqual(failures, []);
console.log('navigation cleanup tests passed');
