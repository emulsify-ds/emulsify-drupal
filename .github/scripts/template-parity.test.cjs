const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const test = require('node:test');

function fixture(t, { core = true } = {}) {
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'emulsify-template-parity-'));
  t.after(() => fs.rmSync(directory, { recursive: true, force: true }));
  const write = (relativePath, contents) => {
    const file = path.join(directory, relativePath);
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, contents);
  };
  if (core) write('drupal/web/core/themes/stable9/templates/layout/page.html.twig', 'Stable9 page\n');
  write('drupal/composer.json', '{"require":{"drupal/core":"12.0.0-beta1"}}\n');
  write('repo/templates/layout/page.html.twig', 'Emulsify page\n');
  write('repo/emulsify.info.yml', 'base theme: false\n');
  const report = path.join(directory, 'parity.md');
  return {
    directory,
    report,
    write,
    composer: (status, install = true) => {
      write('composer.cjs', `#!/usr/bin/env node
const fs = require('node:fs');
fs.writeFileSync('composer-call.json', JSON.stringify({ cwd: process.cwd(), args: process.argv.slice(2) }));
if (${status} === 0 && ${install}) {
  fs.mkdirSync('web/themes/contrib/stable9/templates/layout', { recursive: true });
  fs.writeFileSync('web/themes/contrib/stable9/templates/layout/page.html.twig', 'Stable9 page\\n');
}
process.stderr.write('Composer fixture result: ${status}\\n');
process.exit(${status});
`);
      const executable = path.join(directory, 'composer.cjs');
      fs.chmodSync(executable, 0o755);
      return { COMPOSER_BIN: executable };
    },
    run: (env = {}, fixtureDir = path.join(directory, 'drupal')) => spawnSync('bash', [
      path.join(__dirname, 'template-parity.sh'),
      fixtureDir,
      path.join(directory, 'repo'),
      report,
    ], { encoding: 'utf8', env: { ...process.env, TMPDIR: directory, ...env } }),
  };
}

test('allows an absent Whisk templates directory and intentional stable9 overrides', (t) => {
  const result = fixture(t).run();
  assert.equal(result.status, 0, result.stderr);
  assert.match(result.stdout, /covers every stable9 template basename/);
});

test('accepts identical Whisk files at the same relative paths, including non-Twig files', (t) => {
  const { write, run } = fixture(t);
  write('repo/whisk/templates/layout/page.html.twig', 'Emulsify page\n');
  write('repo/templates/layout/helper notes.txt', 'Shared helper\n');
  write('repo/whisk/templates/layout/helper notes.txt', 'Shared helper\n');
  const result = run();
  assert.equal(result.status, 0, result.stderr);
});

test('rejects a deliberately changed Whisk file and passes after its bytes are restored', (t) => {
  const { write, run } = fixture(t);
  for (const relativePath of ['layout/page.html.twig', 'layout/helper notes.txt']) {
    write(`repo/templates/${relativePath}`, 'Original contents\n');
    write(`repo/whisk/templates/${relativePath}`, 'Changed contents\n');
    const changed = run();
    assert.notEqual(changed.status, 0);
    assert.ok(changed.stderr.includes(`${relativePath} differs from its parent counterpart`), changed.stderr);
    write(`repo/whisk/templates/${relativePath}`, 'Original contents\n');
    const restored = run();
    assert.equal(restored.status, 0, restored.stderr);
  }
});

test('rejects a Whisk file with no same-path parent even if its basename exists elsewhere', (t) => {
  const { write, run } = fixture(t);
  write('repo/whisk/templates/other/page.html.twig', 'Emulsify page\n');
  const result = run();
  assert.notEqual(result.status, 0);
  assert.match(result.stderr, /other\/page\.html\.twig has no parent counterpart/);
});

test('prefers the core baseline without invoking Composer', (t) => {
  const site = fixture(t);
  const result = site.run(site.composer(1));
  assert.equal(result.status, 0, result.stderr);
  assert.equal(fs.existsSync(path.join(site.directory, 'drupal/composer-call.json')), false);
  assert.match(fs.readFileSync(site.report, 'utf8'), /Stable9 source: Drupal core/);
});

test('uses an already installed contrib baseline without invoking Composer', (t) => {
  const site = fixture(t, { core: false });
  site.write('drupal/web/themes/contrib/stable9/templates/layout/page.html.twig', 'Stable9 page\n');
  const result = site.run(site.composer(1));
  assert.equal(result.status, 0, result.stderr);
  assert.equal(fs.existsSync(path.join(site.directory, 'drupal/composer-call.json')), false);
  assert.match(fs.readFileSync(site.report, 'utf8'), /Stable9 source: Contrib drupal\/stable9/);
});

test('installs stable contrib Stable9 only in the disposable fixture using normal constraints', (t) => {
  const site = fixture(t, { core: false });
  const result = site.run(site.composer(0));
  assert.equal(result.status, 0, result.stderr);
  assert.match(result.stdout, /covers every stable9 template basename/);
  const call = JSON.parse(fs.readFileSync(path.join(site.directory, 'drupal/composer-call.json'), 'utf8'));
  assert.equal(call.cwd, fs.realpathSync(path.join(site.directory, 'drupal')));
  assert.deepEqual(call.args, ['require', '--dev', '--no-interaction', '--prefer-dist', '--with-all-dependencies', 'drupal/stable9:*@stable']);
  assert.equal(fs.existsSync(path.join(site.directory, 'repo/composer-call.json')), false);
});

test('reports an explicit skip only for Composer dependency resolution status 2', (t) => {
  const site = fixture(t, { core: false });
  const result = site.run(site.composer(2));
  assert.equal(result.status, 0, result.stderr);
  assert.match(result.stdout, /^SKIP Stable9 template parity: .*baseline coverage was not verified\./m);
  assert.match(result.stderr, /Composer fixture result: 2/);
  assert.doesNotMatch(result.stdout, /covers every stable9 template basename/);
  assert.match(fs.readFileSync(site.report, 'utf8'), /SKIP Stable9 template parity:/);
});

test('keeps unrelated Composer errors and missing or empty template baselines as failures', (t) => {
  const site = fixture(t, { core: false });
  const failed = site.run(site.composer(1));
  assert.equal(failed.status, 1);
  assert.match(failed.stderr, /Composer fixture result: 1/);
  assert.doesNotMatch(failed.stdout, /SKIP/);
  const missing = site.run(site.composer(0, false));
  assert.equal(missing.status, 1);
  assert.match(missing.stderr, /Composer succeeded but contrib stable9 templates are missing/);
  assert.doesNotMatch(missing.stdout, /SKIP/);
  site.write('drupal/web/themes/contrib/stable9/templates/README.md', 'No Twig baseline\n');
  const empty = site.run();
  assert.equal(empty.status, 1);
  assert.match(empty.stderr, /baseline contains no \.html\.twig templates/);
});

test('still rejects Whisk drift when Stable9 would be unavailable', (t) => {
  const site = fixture(t, { core: false });
  site.write('repo/whisk/templates/layout/page.html.twig', 'Changed contents\n');
  const result = site.run(site.composer(2));
  assert.equal(result.status, 1);
  assert.match(result.stderr, /differs from its parent counterpart/);
  assert.equal(fs.existsSync(path.join(site.directory, 'drupal/composer-call.json')), false);
});

test('refuses to install a fallback in the source checkout', (t) => {
  const site = fixture(t, { core: false });
  const result = site.run(site.composer(0), path.join(site.directory, 'repo'));
  assert.equal(result.status, 1);
  assert.match(result.stderr, /requires a disposable fixture outside the source checkout/);
  assert.equal(fs.existsSync(path.join(site.directory, 'repo/composer-call.json')), false);
});
