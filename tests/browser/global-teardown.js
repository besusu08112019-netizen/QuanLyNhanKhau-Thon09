const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..', '..');
const statePath = path.join(root, 'test-results', 'playwright-server.json');

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
