const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');

function read(file) {
  return fs.readFileSync(path.join(root, file), 'utf8');
}

function write(file, content) {
  fs.writeFileSync(path.join(root, file), content, 'utf8');
}

function minifyCss(source) {
  return source
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/\s+/g, ' ')
    .replace(/\s*([{}:;,>+~])\s*/g, '$1')
    .replace(/calc\(([^()]*(?:\([^()]*\)[^()]*)*)\)/g, (_, value) => 'calc(' + value.replace(/\s*\+\s*/g, ' + ').replace(/(?<=[0-9)%])\s*-\s*(?=[0-9.])/g, ' - ') + ')')
    .replace(/;}/g, '}')
    .trim();
}

function compactJs(source) {
  return source
    .split(/\r?\n/)
    .map(line => line.trim())
    .filter(line => line && !line.startsWith('//'))
    .join('\n')
    .replace(/\n{2,}/g, '\n')
    .trim() + '\n';
}

const assets = [
  ['assets/css/app.css', 'assets/css/app.min.css', minifyCss],
  ['assets/js/i18n.js', 'assets/js/i18n.min.js', compactJs],
  ['assets/js/app.utf8.js', 'assets/js/app.utf8.min.js', compactJs],
  ['assets/js/admin.utf8.js', 'assets/js/admin.utf8.min.js', compactJs],
  ['assets/js/admin-panel.js', 'assets/js/admin-panel.min.js', compactJs],
  ['assets/js/admin-panel-bridge.js', 'assets/js/admin-panel-bridge.min.js', compactJs],
  ['assets/js/import.js', 'assets/js/import.min.js', compactJs],
  ['assets/js/session.js', 'assets/js/session.min.js', compactJs],
  ['assets/js/csrf.js', 'assets/js/csrf.min.js', compactJs],
  ['assets/js/sprint8.js', 'assets/js/sprint8.min.js', compactJs],
  ['assets/js/sprint9.js', 'assets/js/sprint9.min.js', compactJs],
  ['assets/js/sprint10.js', 'assets/js/sprint10.min.js', compactJs],
  ['assets/js/view-inline-patches.js', 'assets/js/view-inline-patches.min.js', compactJs],
  ['assets/js/operation-center.js', 'assets/js/operation-center.min.js', compactJs],
  ['assets/js/system-admin.js', 'assets/js/system-admin.min.js', compactJs],
  ['assets/js/report.js', 'assets/js/report.min.js', compactJs],
  ['assets/js/gis-household-location.js', 'assets/js/gis-household-location.min.js', compactJs],
  ['assets/js/household-photo-capture.js', 'assets/js/household-photo-capture.min.js', compactJs],
  ['assets/js/household-photo-camera-fix.js', 'assets/js/household-photo-camera-fix.min.js', compactJs],
  ['assets/js/household-photo-gps.js', 'assets/js/household-photo-gps.min.js', compactJs],
  ['assets/js/gis-search.js', 'assets/js/gis-search.min.js', compactJs],
  ['assets/js/gis-smart.js', 'assets/js/gis-smart.min.js', compactJs],
  ['assets/js/digital-profile.js', 'assets/js/digital-profile.min.js', compactJs]
];

for (const [src, dest, fn] of assets) {
  if (!fs.existsSync(path.join(root, src))) continue;
  const input = read(src);
  const output = fn(input);
  write(dest, output);
  const saved = Buffer.byteLength(input) - Buffer.byteLength(output);
  process.stdout.write(`${dest}: ${Buffer.byteLength(output)} bytes (${saved >= 0 ? '-' : '+'}${Math.abs(saved)})\n`);
}