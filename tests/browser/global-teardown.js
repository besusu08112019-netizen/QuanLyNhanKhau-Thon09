const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..', '..');
const host = process.env.PW_PHP_HOST || '127.0.0.1';
const port = process.env.PW_PHP_PORT || '8080';
const serverFileSuffix = `${host.replace(/[^a-z0-9.-]/gi, '_')}-${port}`;
const statePath = path.join(root, 'test-results', `playwright-server-${serverFileSuffix}.json`);

function readState() {
  try {
    return JSON.parse(fs.readFileSync(statePath, 'utf8'));
  } catch (_) {
    return null;
  }
}

async function globalTeardown() {
  const state = readState();
  if (!state || state.external || !state.pid) return;

  if (process.platform === 'win32') {
    spawnSync('taskkill', ['/PID', String(state.pid), '/T', '/F'], {
      stdio: 'ignore',
      windowsHide: true
    });
  } else {
    try {
      process.kill(-state.pid, 'SIGTERM');
    } catch (_) {
      try {
        process.kill(state.pid, 'SIGTERM');
      } catch (__) {
        // The process is already gone.
      }
    }
  }

  try {
    fs.unlinkSync(statePath);
  } catch (_) {
    // The next setup run will overwrite stale state if needed.
  }
}

module.exports = globalTeardown;
