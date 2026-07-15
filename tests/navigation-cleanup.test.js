const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const scanPaths = [
  'assets/js',
  'views'
];

const forbidden = [
  { pattern: /window\.showApp\s*=/, reason: 'showApp monkey-patching is replaced by thon09:auth-state' },
  { pattern: /hardNavigate\s*\(/, reason: 'hardNavigate duplicates NavigationController' },
  { pattern: /window\.switchScreen\b/, reason: 'window.switchScreen duplicates NavigationController' },
  { pattern: /window\.showScreen\b/, reason: 'window.showScreen duplicates NavigationController' },
  { pattern: /navigationRepairModule\b/, reason: 'repair modules are not part of single-source navigation' },
  { pattern: /nav\.innerHTML\s*=\s*menu\.filter/, reason: 'menu rendering must come from Thon09Platform' },
  { pattern: /btn\.dataset\.screen\s*=\s*['"]systemAdmin['"]/, reason: 'systemAdmin menu item must come from Thon09Platform' },
  { pattern: /data-screen=["']import["'][\s\S]{0,120}insertAdjacentHTML|insertAdjacentHTML[\s\S]{0,120}data-screen=["']import["']/, reason: 'import menu item must come from Thon09Platform' },
  { pattern: /data-screen=["']users["'][\s\S]{0,180}data-screen=["']logs["'][\s\S]{0,180}data-screen=["']backups["']/, reason: 'admin menu items must come from Thon09Platform' }
];

const allowedClickListeners = new Map([
  ['assets/js/app-platform.js', [
    "host.addEventListener('click', handleClick);",
    "root.addEventListener('click', handler);"
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
  ['assets/js/vehicles.js', [
    "$('#vehicleResetBtn').addEventListener('click'",
    "['#vehicleAddBtn','#vehicleAddBtnInline','#vehicleAddBtnList'].forEach",
    "$('#vehicleExportExcel').addEventListener('click'",
    "$('#vehicleExportPdf').addEventListener('click'",
    ".onclick="
  ]],
  ['assets/js/view-inline-patches.js', [
    "document.addEventListener('click',function(event){var item=event.target.closest&&event.target.closest('[data-screen],[data-mobile-screen]');if(!item||item.classList.contains('gov-logout'))return;var delegation=window.Thon09Platform&&window.Thon09Platform.navigationDelegation;"
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

assert.deepStrictEqual(failures, []);
console.log('navigation cleanup tests passed');
