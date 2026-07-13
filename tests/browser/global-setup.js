const fs = require('fs');
const http = require('http');
const path = require('path');
const { spawn } = require('child_process');

const root = path.resolve(__dirname, '..', '..');
const outputDir = path.join(root, 'test-results');
const statePath = path.join(outputDir, 'playwright-server.json');
const logPath = path.join(outputDir, 'playwright-server.log');
const host = process.env.PW_PHP_HOST || '127.0.0.1';
const port = process.env.PW_PHP_PORT || '8080';
const serverUrl = `http://${host}:${port}`;

function ensureOutputDir() {
  fs.mkdirSync(outputDir, { recursive: true });
}

function isServerReady() {
  return new Promise(resolve => {
    const request = http.get(serverUrl, response => {
      response.resume();
      response.on('end', () => resolve(true));
    });

    request.setTimeout(1000, () => {
      request.destroy();
      resolve(false);
    });
    request.on('error', () => resolve(false));
  });
}

async function waitForServer(timeoutMs) {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    if (await isServerReady()) return true;
    await new Promise(resolve => setTimeout(resolve, 250));
  }
  return false;
}

async function globalSetup() {
  ensureOutputDir();

  if (await isServerReady()) {
    fs.writeFileSync(statePath, JSON.stringify({ external: true, url: serverUrl }, null, 2));
    return;
  }

  const log = fs.openSync(logPath, 'a');
  const server = spawn('php', ['-S', `${host}:${port}`], {
    cwd: root,
    detached: true,
    stdio: ['ignore', log, log],
    windowsHide: true
  });

  server.unref();
  fs.writeFileSync(statePath, JSON.stringify({ external: false, pid: server.pid, url: serverUrl }, null, 2));

  if (!(await waitForServer(15000))) {
    try {
      if (process.platform === 'win32') {
        spawn('taskkill', ['/PID', String(server.pid), '/T', '/F'], { stdio: 'ignore', windowsHide: true });
      } else {
        process.kill(-server.pid, 'SIGTERM');
      }
    } catch (_) {
      // Best effort cleanup before surfacing the startup failure.
    }
    throw new Error(`Timed out waiting for PHP server at ${serverUrl}. See ${logPath}`);
  }
}

module.exports = globalSetup;
