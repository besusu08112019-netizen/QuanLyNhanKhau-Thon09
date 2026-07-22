const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const artifact = path.join(root, 'dist', 'production');

function fail(message) {
  console.error(message);
  process.exitCode = 1;
}

function exists(rel) {
  return fs.existsSync(path.join(artifact, rel));
}

function walk(dir, base = '') {
  const entries = [];
  if (!fs.existsSync(dir)) return entries;
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const rel = base ? `${base}/${entry.name}` : entry.name;
    const full = path.join(dir, entry.name);
    entries.push(rel);
    if (entry.isDirectory()) entries.push(...walk(full, rel));
  }
  return entries;
}

if (!fs.existsSync(artifact)) {
  fail('dist/production is missing');
} else {
  for (const rel of [
    'index.php',
    'api/index.php',
    'app/Core/Router.php',
    'app/Core/Database.php',
    'app/Controllers/AuthController.php',
    'app/Controllers/GisController.php',
    'app/Controllers/ReportController.php',
    'views/app.php',
    '.htaccess',
    'uploads/.htaccess',
    'storage/.htaccess',
    'manifest.json',
    'manifest.webmanifest',
    'service-worker.js',
  ]) {
    if (!exists(rel)) fail(`Required production artifact file is missing: ${rel}`);
  }

  const forbiddenExact = new Set([
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
  ]);

  const forbiddenPatterns = [
    /^\.env\./,
    /(^|\/)\.git(\/|$)/,
    /(^|\/).*\.log$/,
    /(^|\/).*\.bak$/,
    /(^|\/).*\.sql$/,
    /^backups(\/|$)/,
  ];

  for (const rel of walk(artifact)) {
    if (forbiddenExact.has(rel)) fail(`Forbidden production artifact entry: ${rel}`);
    if (forbiddenPatterns.some((pattern) => pattern.test(rel))) {
      fail(`Forbidden production artifact entry: ${rel}`);
    }
  }
}

if (!process.exitCode) {
  console.log('production artifact validation passed');
}
