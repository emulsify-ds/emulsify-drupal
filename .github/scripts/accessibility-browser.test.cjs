const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const test = require('node:test');
const yaml = require('js-yaml');

function fixture(t) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'emulsify-browser-'));
  t.after(() => fs.rmSync(root, { recursive: true, force: true }));
  const bin = path.join(root, 'browser tools');
  fs.mkdirSync(bin);
  for (const name of ['google-chrome', 'npm', 'npx', 'node']) {
    fs.writeFileSync(path.join(bin, name), `#!/bin/sh
printf '%s|%s|%s\\n' '${name}' "$*" "\${PUPPETEER_EXECUTABLE_PATH:-}" >> "$BROWSER_TEST_LOG"
if [ '${name}' = google-chrome ] && [ "$1" = --headless ]; then exit "\${BROWSER_TEST_LAUNCH_STATUS:-0}"; fi
if [ '${name}' = npm ]; then exit "\${BROWSER_TEST_CORE_STATUS:-0}"; fi
`, { mode: 0o755 });
  }
  const env = { ...process.env, PATH: `${bin}${path.delimiter}${process.env.PATH}`, BROWSER_TEST_LOG: path.join(root, 'calls'), GITHUB_ENV: path.join(root, 'github-env') };
  delete env.PUPPETEER_EXECUTABLE_PATH;
  const calls = () => fs.readFileSync(env.BROWSER_TEST_LOG, 'utf8').trim().split('\n').map((line) => line.split('|'));
  return { root, bin, env, calls };
}

const workflow = yaml.load(fs.readFileSync(path.join(__dirname, '../workflows/theme-readiness.yml'), 'utf8'));
const browserStep = workflow.jobs['generated-child-theme-extended'].steps.find(({ name }) => name === 'Select and verify runner Chrome');

test('CI discovers runner Chrome and exports it only after a sandboxed launch', (t) => {
  const { bin, env, calls } = fixture(t);
  const result = spawnSync('bash', ['-e', '-o', 'pipefail', '-c', browserStep.run], { env, encoding: 'utf8' });
  assert.equal(result.status, 0, result.stderr);
  assert.deepEqual(calls().map(([name, args]) => [name, args]), [
    ['google-chrome', '--version'],
    ['google-chrome', '--headless --dump-dom about:blank'],
  ]);
  assert.equal(fs.readFileSync(env.GITHUB_ENV, 'utf8'), `PUPPETEER_EXECUTABLE_PATH=${path.join(bin, 'google-chrome')}\n`);
});

test('CI fails when the runner browser cannot launch', (t) => {
  const { env } = fixture(t);
  const result = spawnSync('bash', ['-e', '-o', 'pipefail', '-c', browserStep.run], {
    env: { ...env, BROWSER_TEST_LAUNCH_STATUS: '17' }, encoding: 'utf8',
  });
  assert.equal(result.status, 17, result.stderr);
  assert.equal(fs.existsSync(env.GITHUB_ENV), false);
});

function runAccessibility(t, configure = () => {}) {
  const context = fixture(t);
  const fixtureDir = path.join(context.root, 'site');
  const themeDir = path.join(fixtureDir, 'web/themes/custom/example_theme');
  fs.mkdirSync(themeDir, { recursive: true });
  fs.writeFileSync(path.join(themeDir, 'example_theme.info.yml'), 'name: Example\n');
  fs.mkdirSync(path.join(fixtureDir, 'web/core/scripts'), { recursive: true });
  fs.writeFileSync(path.join(fixtureDir, 'web/core/scripts/drupal'), '');
  configure(context);
  const result = spawnSync('bash', [path.join(__dirname, 'starterkit-smoke.sh'), fixtureDir, path.join(context.root, 'output'), 'frontend-a11y'], {
    env: context.env, encoding: 'utf8',
  });
  return { ...context, result };
}

test('explicit browser reaches both audits without downloading Chrome', (t) => {
  const { result, env, calls } = runAccessibility(t, ({ bin, env }) => {
    env.PUPPETEER_EXECUTABLE_PATH = path.join(bin, 'google-chrome');
  });
  assert.equal(result.status, 0, result.stdout + result.stderr);
  assert.deepEqual(calls().map(([name]) => name), ['google-chrome', 'npm', 'node']);
  for (const [, , browser] of calls()) assert.equal(browser, env.PUPPETEER_EXECUTABLE_PATH);
});

test('local default still installs Puppeteer Chrome before both audits', (t) => {
  const { result, calls } = runAccessibility(t);
  assert.equal(result.status, 0, result.stdout + result.stderr);
  assert.deepEqual(calls().map(([name]) => name), ['npx', 'npm', 'node']);
  assert.equal(calls()[0][1], '--no-install puppeteer browsers install chrome');
});

test('a failed Core audit remains a failure after the rendered audit runs', (t) => {
  const { result, calls } = runAccessibility(t, ({ bin, env }) => {
    env.PUPPETEER_EXECUTABLE_PATH = path.join(bin, 'google-chrome');
    env.BROWSER_TEST_CORE_STATUS = '23';
  });
  assert.equal(result.status, 23, result.stdout + result.stderr);
  assert.deepEqual(calls().map(([name]) => name), ['google-chrome', 'npm', 'node']);
});

test('an invalid explicit browser fails without switching to a download', (t) => {
  const { result, calls } = runAccessibility(t, ({ root, env }) => {
    env.PUPPETEER_EXECUTABLE_PATH = path.join(root, 'missing-chrome');
  });
  assert.notEqual(result.status, 0);
  assert.match(result.stderr, /Configured Puppeteer browser is not executable/);
  assert.deepEqual(calls().map(([name]) => name), ['node']);
});
