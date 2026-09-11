const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const { createHash } = require('node:crypto');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const test = require('node:test');

const repoRoot = path.resolve(__dirname, '../..');
const tarball = process.env.EMULSIFY_CORE_TARBALL;
const autoload = process.env.EMULSIFY_DRUPAL_AUTOLOAD;

function run(command, args, cwd) {
  const result = spawnSync(command, args, {
    cwd,
    encoding: 'utf8',
    timeout: 180_000,
    maxBuffer: 8 * 1024 * 1024,
    env: { ...process.env, CI: '1', FORCE_COLOR: '0' },
  });
  assert.ifError(result.error);
  assert.equal(result.signal, null, result.stderr);
  return result;
}

function writeJson(file, value) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, `${JSON.stringify(value, null, 2)}\n`);
}

function assertWrapper(result, direct, footer) {
  assert.equal(result.status, direct.status, result.stderr);
  // Parse the whole stream. Extracting JSON would hide a stdout footer regression.
  const report = JSON.parse(result.stdout);
  assert.equal(report.schemaVersion, 1);
  assert.equal(report.tool.name, '@emulsify/core');
  assert.equal(result.stdout, direct.stdout);
  assert.equal(result.stderr, `${direct.stderr}${footer}`);
  return report;
}

test('real generated audit wrappers preserve the installed Core CLI contract', {
  skip: !tarball || !autoload
    ? 'Set EMULSIFY_CORE_TARBALL and EMULSIFY_DRUPAL_AUTOLOAD for the opt-in installed-consumer check.'
    : false,
}, async (t) => {
  const tarballPath = fs.realpathSync(tarball);
  const autoloadPath = fs.realpathSync(autoload);
  const suite = fs.mkdtempSync(path.join(os.tmpdir(), 'emulsify audit wrappers '));
  t.after(() => fs.rmSync(suite, { force: true, recursive: true }));
  const site = path.join(suite, 'Drupal root with spaces');
  fs.cpSync(path.join(repoRoot, 'whisk'), path.join(site, 'themes/contrib/whisk'), { recursive: true });
  const generated = run('php', [path.join(__dirname, 'generate-audit-theme.php'), autoloadPath, site], repoRoot);
  assert.equal(generated.status, 0, generated.stdout + generated.stderr);
  const consumer = path.join(site, 'themes/custom/audit_wrapper_theme');
  const manifestPath = path.join(consumer, 'package.json');
  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  const template = JSON.parse(fs.readFileSync(path.join(repoRoot, 'whisk/package.json'), 'utf8'));
  assert.equal(manifest.name, 'audit_wrapper_theme');
  assert.equal(JSON.parse(fs.readFileSync(path.join(consumer, 'project.emulsify.json'), 'utf8')).project.machineName, 'audit_wrapper_theme');
  assert.doesNotMatch(fs.readFileSync(path.join(consumer, 'docs/upgrading.md'), 'utf8'), /%%EMULSIFY_/);
  for (const name of ['audit', 'audit:twig-stories']) assert.equal(manifest.scripts[name], template.scripts[name]);
  const generatedManifest = structuredClone(manifest);
  manifest.dependencies['@emulsify/core'] = `file:${tarballPath}`;
  writeJson(manifestPath, manifest);
  const installed = run('npm', ['install', '--ignore-scripts', '--no-audit', '--no-fund'], consumer);
  assert.equal(installed.status, 0, installed.stdout + installed.stderr);
  const coreRoot = path.join(consumer, 'node_modules/@emulsify/core');
  assert.equal(fs.lstatSync(coreRoot).isSymbolicLink(), false);
  assert.ok(fs.realpathSync(coreRoot).startsWith(`${fs.realpathSync(consumer)}${path.sep}`));
  const corePackage = JSON.parse(fs.readFileSync(path.join(coreRoot, 'package.json'), 'utf8'));
  const auditRoot = path.join(suite, 'audit input with spaces');
  const component = path.join(auditRoot, 'src/components/card');
  fs.mkdirSync(component, { recursive: true });
  writeJson(path.join(auditRoot, 'project.emulsify.json'), { project: { platform: 'none' } });
  fs.writeFileSync(path.join(component, 'card.twig'), '<p>{{ title }}</p>\n');
  fs.writeFileSync(path.join(component, 'card.stories.js'), 'import cardTwig from "./card.twig";\nexport const Card = (args) => cardTwig(args);\n');

  const evidence = {
    coreVersion: corePackage.version,
    coreTarballSha256: createHash('sha256').update(fs.readFileSync(tarballPath)).digest('hex'),
    generatedScripts: Object.fromEntries(['audit', 'audit:twig-stories'].map((name) => [name, generatedManifest.scripts[name]])),
    results: [],
  };
  for (const { name, executable, failArgs, footer } of [
    { name: 'audit', executable: 'audit.js', failArgs: ['--fail-on', 'warn'], footer: '\nAudit docs: https://github.com/emulsify-ds/emulsify-core/blob/4.x/docs/migration-4x.md#storybook-migration\n' },
    { name: 'audit:twig-stories', executable: 'audit-twig-stories.js', failArgs: ['--fail-on-found'], footer: '\nMigration docs: https://github.com/emulsify-ds/emulsify-core/blob/4.x/docs/storybook.md#legacy-twig-story-compatibility\n' },
  ]) {
    const baseArgs = ['--root', auditRoot, '--json'];
    const direct = (args) => run(path.join(coreRoot, 'scripts', executable), args, consumer);
    const wrapped = (args) => run('npm', ['run', '--silent', name, '--', ...args], consumer);
    for (const { label, args, status } of [
      { label: 'warning report', args: baseArgs, status: 0 },
      { label: 'findings failure', args: [...baseArgs, ...failArgs], status: 1 },
      { label: 'invalid option', args: [...baseArgs, '--unknown-wrapper-test-option'], status: 2 },
    ]) {
      await t.test(`${name}: ${label} keeps JSON stdout and exit ${status}`, () => {
        const expected = direct(args);
        assert.equal(expected.status, status, expected.stdout + expected.stderr);
        const observed = wrapped(args);
        const report = assertWrapper(observed, expected, footer);
        if (status !== 2) assert.ok(report.findings.some(({ id }) => id === 'legacy-twig-story'));
        evidence.results.push({ name, scenario: label, args: args.map((arg) => arg === auditRoot ? '<audit-fixture>' : arg), directStatus: expected.status, wrapperStatus: observed.status, stdout: observed.stdout, stderr: observed.stderr });
      });
    }
    for (const { label, mutate } of [
      { label: 'stdout footer', mutate: (script) => script.replace(' >&2', '') },
      { label: 'masked status', mutate: (script) => script.replace('exit $status', 'exit 0') },
      { label: 'split argument path', mutate: (script) => script.replace('"$@"', '$@') },
    ]) {
      await t.test(`${name}: rejects ${label} regression`, () => {
        const original = manifest.scripts[name];
        const changed = mutate(original);
        assert.notEqual(changed, original, 'Negative control must change the generated wrapper.');
        manifest.scripts[name] = changed;
        writeJson(manifestPath, manifest);
        try {
          const args = [...baseArgs, ...failArgs];
          const expected = direct(args);
          const observed = wrapped(args);
          assert.throws(() => assertWrapper(observed, expected, footer));
          evidence.results.push({ name, scenario: `negative: ${label}`, rejected: true, directStatus: expected.status, wrapperStatus: observed.status });
        } finally {
          manifest.scripts[name] = original;
          writeJson(manifestPath, manifest);
        }
      });
    }
  }
  for (const temporaryPath of [suite, fs.realpathSync(suite)]) assert.equal(JSON.stringify(evidence).includes(temporaryPath), false);
  if (process.env.EMULSIFY_AUDIT_WRAPPER_EVIDENCE) writeJson(path.resolve(process.env.EMULSIFY_AUDIT_WRAPPER_EVIDENCE), evidence);
  t.diagnostic(`Verified generated wrappers against installed Core ${corePackage.version}; tarball SHA256 ${evidence.coreTarballSha256}.`);
});
