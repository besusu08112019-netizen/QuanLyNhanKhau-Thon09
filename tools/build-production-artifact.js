const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const out = path.join(root, 'dist', 'production');

const includeFiles = [
  '.htaccess',
  'favicon.ico',
  'index.php',
  'manifest.json',
  'manifest.webmanifest',
  'offline.html',
  'robots.txt',
  'service-worker.js',
  'sitemap.xml',
  'api/index.php',
  'config/app.php',
  'config/database.example.php',
  'storage/.htaccess',
  'uploads/.htaccess',
  'views/app.php',
];

const includeDirs = [
  'app',
  'assets',
];

const runtimeDirs = [
  'storage/cache',
  'uploads',
];

function rmDir(dir) {
  fs.rmSync(dir, { recursive: true, force: true });
}

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

function copyFile(rel) {
  const src = path.join(root, rel);
  if (!fs.existsSync(src)) {
    throw new Error(`Missing artifact file: ${rel}`);
  }
  const dest = path.join(out, rel);
  ensureDir(path.dirname(dest));
  fs.copyFileSync(src, dest);
}

function copyDir(rel) {
  const src = path.join(root, rel);
  if (!fs.existsSync(src)) {
    throw new Error(`Missing artifact directory: ${rel}`);
  }
  const dest = path.join(out, rel);
  fs.cpSync(src, dest, {
    recursive: true,
    filter(source) {
      const name = path.basename(source);
      if (name === '.DS_Store' || name === 'Thumbs.db') return false;
      if (name.endsWith('.log')) return false;
      return true;
    },
  });
}

function assertExcluded() {
  const forbidden = [
    '.git',
    '.github',
    '.env',
    '.deploy.env',
    '.ftp-deploy-sync-state.json',
    '.ftp-deploy-sync-state-utf8.json',
    'docs',
    'tests',
    'tools',
    'sample-data',
    'package.json',
    'package-lock.json',
    'composer.json',
    'composer.lock',
    'database',
    'node_modules',
    'vendor',
  ];

  const present = forbidden.filter((rel) => fs.existsSync(path.join(out, rel)));
  if (present.length) {
    throw new Error(`Forbidden production artifact entries: ${present.join(', ')}`);
  }
}

rmDir(out);
ensureDir(out);
includeDirs.forEach(copyDir);
includeFiles.forEach(copyFile);
runtimeDirs.forEach((rel) => ensureDir(path.join(out, rel)));
fs.writeFileSync(path.join(out, 'storage', 'cache', '.gitkeep'), '');
assertExcluded();

console.log(`Production artifact built at ${path.relative(root, out)}`);
