#!/usr/bin/env node

const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const repoRoot = path.resolve(__dirname, '../..');
const args = new Set(process.argv.slice(2));
const options = {
  drupalVersion: process.env.RELEASE_CHECK_DRUPAL_VERSION || '11.3.*',
  skipSmoke: args.has('--skip-smoke'),
  workDir: process.env.RELEASE_CHECK_WORKDIR || path.join(os.tmpdir(), 'emulsify-release-check'),
};

const results = [];

function addResult(status, name, detail) {
  results.push({ status, name, detail });
}

function readFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

function readJson(relativePath) {
  return JSON.parse(readFile(relativePath));
}

function ensure(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

function normalizeConstraintVersion(constraint) {
  const match = String(constraint).match(/(\d+\.\d+)/);
  return match ? match[1] : String(constraint).trim();
}

function semver(value) {
  return /^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/.test(String(value));
}

function runStaticCheck(name, callback) {
  try {
    const detail = callback();
    addResult('PASS', name, detail);
  }
  catch (error) {
    addResult('FAIL', name, error.message);
  }
}

function runSmokeCheck(name, command, argsList, cwd, options = {}) {
  const result = spawnSync(command, argsList, {
    cwd,
    encoding: 'utf8',
    stdio: 'inherit',
  });

  if (result.status !== 0) {
    const status = options.warnOnly ? 'WARN' : 'FAIL';
    addResult(status, name, options.failMessage || `${command} ${argsList.join(' ')} exited with status ${result.status}.`);
    return false;
  }

  addResult('PASS', name, options.passMessage || 'Completed successfully.');
  return true;
}

function extractJsonObjectSegment(text, key) {
  const keyPattern = new RegExp(`"${key}"\\s*:\\s*\\{`);
  const match = keyPattern.exec(text);
  if (!match) {
    return null;
  }

  const start = text.indexOf('{', match.index);
  let depth = 0;
  let inString = false;
  let escaped = false;

  for (let index = start; index < text.length; index += 1) {
    const character = text[index];

    if (inString) {
      if (escaped) {
        escaped = false;
      }
      else if (character === '\\') {
        escaped = true;
      }
      else if (character === '"') {
        inString = false;
      }
      continue;
    }

    if (character === '"') {
      inString = true;
      continue;
    }

    if (character === '{') {
      depth += 1;
      continue;
    }

    if (character === '}') {
      depth -= 1;
      if (depth === 0) {
        return text.slice(start, index + 1);
      }
    }
  }

  return null;
}

function findDuplicatePackageScripts(relativePath) {
  const raw = readFile(relativePath);
  const scriptsBlock = extractJsonObjectSegment(raw, 'scripts');
  ensure(scriptsBlock, `Unable to locate the scripts block in ${relativePath}.`);

  const keys = [...scriptsBlock.matchAll(/^\s*"([^"]+)"\s*:/gm)].map((match) => match[1]);
  const seen = new Set();
  const duplicates = new Set();

  for (const key of keys) {
    if (seen.has(key)) {
      duplicates.add(key);
    }
    seen.add(key);
  }

  return [...duplicates].sort();
}

function extractYamlValue(contents, key) {
  const match = contents.match(new RegExp(`^${key}:\\s*(.+)$`, 'm'));
  ensure(match, `Unable to find ${key}.`);
  return match[1].trim().replace(/^['"]|['"]$/g, '');
}

function extractYamlDependencyConstraint(contents, packageName) {
  const escaped = packageName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const match = contents.match(new RegExp(`^\\s*-\\s*'drupal:${escaped} \\(([^)]+)\\)'\\s*$`, 'm'));
  ensure(match, `Unable to find drupal:${packageName} dependency.`);
  return match[1].trim();
}

function extractSchemaKeys(relativePath) {
  return [...readFile(relativePath).matchAll(/^ {4}([a-z0-9_]+):$/gm)].map((match) => match[1]).sort();
}

function extractInstallKeys(relativePath) {
  return [...readFile(relativePath).matchAll(/^([a-z0-9_]+):/gm)].map((match) => match[1]).sort();
}

function extractDefaultKeys(relativePath) {
  const contents = readFile(relativePath);
  const block = contents.match(/public const DEFAULTS = \[(.*?)\n  \];/s);
  ensure(block, 'Unable to find FaviconSettings::DEFAULTS.');
  return [...block[1].matchAll(/'([^']+)'\s*=>/g)].map((match) => match[1]).sort();
}

function compareKeySets(expected, actual, label) {
  const missing = expected.filter((key) => !actual.includes(key));
  const extra = actual.filter((key) => !expected.includes(key));

  ensure(missing.length === 0 && extra.length === 0, [
    `${label} does not match the expected key set.`,
    missing.length ? `Missing: ${missing.join(', ')}` : null,
    extra.length ? `Extra: ${extra.join(', ')}` : null,
  ].filter(Boolean).join(' '));
}

function copyDirectory(source, destination) {
  fs.rmSync(destination, { force: true, recursive: true });
  fs.cpSync(source, destination, { recursive: true });
}

function runStaticChecks() {
  const rootPackage = readJson('package.json');
  const whiskPackage = readJson('whisk/package.json');
  const composer = readJson('composer.json');
  const readme = readFile('README.md');
  const emulsifyInfo = readFile('emulsify.info.yml');
  const whiskInfo = readFile('whisk/whisk.info.yml');
  const whiskInfoStarter = readFile('whisk/whisk.info.emulsify.yml');
  const whiskStarterkit = readFile('whisk/whisk.starterkit.yml');
  const themeReadinessWorkflow = readFile('.github/workflows/theme-readiness.yml');
  const coreConstraint = composer.require['drupal/core'];
  const minCoreVersion = normalizeConstraintVersion(coreConstraint);

  runStaticCheck('Composer constraints', () => {
    ensure(coreConstraint, 'composer.json must declare drupal/core.');
    ensure(composer.require['drupal/emulsify_tools'], 'composer.json must declare drupal/emulsify_tools.');
    ensure(extractYamlValue(emulsifyInfo, 'core_version_requirement') === coreConstraint, 'emulsify.info.yml must match composer drupal/core.');
    ensure(extractYamlValue(whiskInfo, 'core_version_requirement') === coreConstraint, 'whisk.info.yml must match composer drupal/core.');
    ensure(extractYamlDependencyConstraint(emulsifyInfo, 'emulsify_tools') === composer.require['drupal/emulsify_tools'], 'emulsify.info.yml must match the composer emulsify_tools constraint.');
    ensure(extractYamlDependencyConstraint(whiskInfo, 'emulsify_tools') === composer.require['drupal/emulsify_tools'], 'whisk.info.yml must match the composer emulsify_tools constraint.');
    ensure(extractYamlDependencyConstraint(whiskInfoStarter, 'emulsify_tools') === composer.require['drupal/emulsify_tools'], 'whisk.info.emulsify.yml must match the composer emulsify_tools constraint.');
    ensure(themeReadinessWorkflow.includes(`- '${minCoreVersion}.*'`), 'theme-readiness.yml should smoke test the supported Drupal patch line.');
    ensure(themeReadinessWorkflow.includes(`- '^${minCoreVersion}'`), 'theme-readiness.yml should smoke test the supported Drupal constraint.');
    return `Root theme metadata and CI matrix align to Drupal ${minCoreVersion}.`;
  });

  runStaticCheck('Package metadata', () => {
    ensure(rootPackage.name === 'emulsify-drupal', 'package.json name should remain emulsify-drupal.');
    ensure(semver(rootPackage.version), 'package.json version must be a valid semver string.');
    ensure(rootPackage.description, 'package.json description is required.');
    ensure(rootPackage.license, 'package.json license is required.');
    ensure(rootPackage.repository && rootPackage.repository.url, 'package.json repository.url is required.');
    ensure(rootPackage.bugs && rootPackage.bugs.url, 'package.json bugs.url is required.');
    ensure(rootPackage.homepage, 'package.json homepage is required.');
    ensure(rootPackage.scripts && rootPackage.scripts.prepare, 'package.json prepare script is required.');
    ensure(rootPackage.scripts['release:check'], 'package.json should expose a release:check script.');
    ensure(whiskPackage.name === 'whisk', 'whisk/package.json name should remain whisk.');
    ensure(semver(whiskPackage.version), 'whisk/package.json version must be a valid semver string.');
    ensure(whiskPackage.description, 'whisk/package.json description is required.');
    ensure(whiskPackage.license, 'whisk/package.json license is required.');
    ensure(whiskPackage.engines && whiskPackage.engines.node, 'whisk/package.json engines.node is required.');
    ensure(whiskPackage.type === 'module', 'whisk/package.json must remain an ES module package.');
    ensure(whiskPackage.dependencies && whiskPackage.dependencies['@emulsify/core'], 'whisk/package.json must declare @emulsify/core.');
    return `Validated root package ${rootPackage.version} and whisk package ${whiskPackage.version}.`;
  });

  runStaticCheck('Duplicate package scripts', () => {
    const duplicates = [
      ...findDuplicatePackageScripts('package.json').map((key) => `package.json:${key}`),
      ...findDuplicatePackageScripts('whisk/package.json').map((key) => `whisk/package.json:${key}`),
    ];
    ensure(duplicates.length === 0, `Duplicate script keys found: ${duplicates.join(', ')}.`);
    return 'No duplicate script keys were found in package metadata.';
  });

  runStaticCheck('README version references', () => {
    ensure(readme.includes(`Drupal ${minCoreVersion}+`), `README.md should mention Drupal ${minCoreVersion}+.`);
    ensure(readme.includes(`${rootPackage.version.split('.')[0]}.x series`), `README.md should mention the ${rootPackage.version.split('.')[0]}.x series.`);
    return `README.md matches Drupal ${minCoreVersion}+ and the current major release line.`;
  });

  runStaticCheck('Base theme independence', () => {
    ensure(!/^base theme:\s*stable9\s*$/m.test(emulsifyInfo), 'emulsify.info.yml should not depend on stable9.');
    ensure(readme.includes('no longer depends on `stable9`'), 'README.md should document the stable9 removal.');
    return 'Emulsify owns its template layer without a stable9 base theme fallback.';
  });

  runStaticCheck('Starterkit generation', () => {
    for (const requiredIgnore of ['/project.emulsify.json', '/whisk.info.emulsify.yml', '/whisk.starterkit.yml']) {
      ensure(whiskStarterkit.includes(requiredIgnore), `whisk.starterkit.yml should ignore ${requiredIgnore}.`);
    }
    ensure(/^\s*hidden:\s+null\s*$/m.test(whiskStarterkit), 'whisk.starterkit.yml should expose hidden: null in the starterkit info overrides.');
    ensure(extractYamlValue(whiskInfo, 'base theme') === 'emulsify', 'whisk.info.yml should keep emulsify as the base theme.');
    ensure(extractYamlValue(whiskInfo, 'hidden') === 'true', 'whisk.info.yml should remain hidden.');
    ensure(extractYamlValue(whiskInfoStarter, 'base theme') === 'emulsify', 'whisk.info.emulsify.yml should keep emulsify as the generated base theme.');
    ensure(extractYamlValue(whiskInfoStarter, 'version') === 'VERSION', 'whisk.info.emulsify.yml should preserve Drupal\'s VERSION token.');
    ensure(extractYamlValue(whiskInfoStarter, 'hidden') === 'false', 'whisk.info.emulsify.yml should unhide generated themes.');
    return 'Starterkit source files and generated theme markers look consistent.';
  });

  runStaticCheck('Schema validity', () => {
    const defaultKeys = extractDefaultKeys('src/Favicon/FaviconSettings.php');
    compareKeySets(defaultKeys, extractInstallKeys('config/install/emulsify.settings.yml'), 'config/install/emulsify.settings.yml');
    compareKeySets(defaultKeys, extractSchemaKeys('config/schema/emulsify.schema.yml'), 'config/schema/emulsify.schema.yml');
    compareKeySets(defaultKeys, extractInstallKeys('whisk/config/install/whisk.settings.yml'), 'whisk/config/install/whisk.settings.yml');
    compareKeySets(defaultKeys, extractSchemaKeys('whisk/config/schema/whisk.schema.yml'), 'whisk/config/schema/whisk.schema.yml');
    return `Validated ${defaultKeys.length} favicon settings keys across defaults, install config, and schema files.`;
  });
}

function runSmokeChecks() {
  if (options.skipSmoke) {
    addResult('SKIP', 'Stable9 template parity', 'Skipped with --skip-smoke.');
    addResult('SKIP', 'Generated theme smoke test', 'Skipped with --skip-smoke.');
    addResult('SKIP', 'No-Stable9 smoke result', 'Skipped with --skip-smoke.');
    addResult('SKIP', 'Favicon generation', 'Skipped with --skip-smoke.');
    return;
  }

  const smokeRoot = options.workDir;
  const baseFixture = path.join(smokeRoot, 'base-fixture');
  const generatedThemeFixture = path.join(smokeRoot, 'generated-theme-fixture');
  const noStable9Fixture = path.join(smokeRoot, 'no-stable9-fixture');
  const faviconFixture = path.join(smokeRoot, 'favicon-fixture');
  const generatedThemeOutput = path.join(smokeRoot, 'generated-theme-output');
  const noStable9Output = path.join(smokeRoot, 'no-stable9-output');

  fs.rmSync(smokeRoot, { force: true, recursive: true });
  fs.mkdirSync(smokeRoot, { recursive: true });

  const setupResult = spawnSync('bash', [
    path.join(repoRoot, '.github/scripts/setup-fixture-site.sh'),
    options.drupalVersion,
    baseFixture,
    repoRoot,
  ], {
    cwd: repoRoot,
    encoding: 'utf8',
    stdio: 'inherit',
  });

  if (setupResult.status !== 0) {
    addResult('FAIL', 'Stable9 template parity', 'Unable to build the Drupal fixture site for template parity checks.');
    addResult('FAIL', 'Generated theme smoke test', 'Unable to build the Drupal fixture site for smoke testing.');
    addResult('FAIL', 'No-Stable9 smoke result', 'Unable to build the Drupal fixture site for smoke testing.');
    addResult('FAIL', 'Favicon generation', 'Unable to build the Drupal fixture site for smoke testing.');
    return;
  }

  runSmokeCheck(
    'Stable9 template parity',
    'bash',
    [path.join(repoRoot, '.github/scripts/template-parity.sh'), baseFixture, repoRoot],
    repoRoot,
    { passMessage: 'Verified that Emulsify ships every stable9 template path without inheriting from stable9.' },
  );

  copyDirectory(baseFixture, generatedThemeFixture);
  runSmokeCheck(
    'Generated theme smoke test',
    'bash',
    [path.join(repoRoot, '.github/scripts/starterkit-smoke.sh'), generatedThemeFixture, generatedThemeOutput],
    repoRoot,
    { passMessage: `Starterkit generation and generated theme smoke passed on Drupal ${options.drupalVersion}.` },
  );

  copyDirectory(baseFixture, noStable9Fixture);
  runSmokeCheck(
    'No-Stable9 smoke result',
    'bash',
    [path.join(repoRoot, '.github/scripts/smoke-without-stable9.sh'), noStable9Fixture, noStable9Output],
    repoRoot,
    {
      passMessage: 'Smoke test passed without any stable9 base theme dependency.',
      failMessage: 'Smoke test failed without the stable9 base theme dependency.',
    },
  );

  copyDirectory(baseFixture, faviconFixture);
  runSmokeCheck(
    'Favicon generation',
    'bash',
    [path.join(repoRoot, '.github/scripts/favicon-smoke.sh'), faviconFixture, 'emulsify'],
    repoRoot,
    { passMessage: 'Verified export-backed favicon package generation and head attachment smoke.' },
  );
}

function printSummary() {
  console.log('\nRelease Check Summary');
  for (const result of results) {
    console.log(`${result.status.padEnd(4)} ${result.name}: ${result.detail}`);
  }
}

runStaticChecks();
runSmokeChecks();
printSummary();

const hasBlockingFailure = results.some((result) => result.status === 'FAIL');
process.exit(hasBlockingFailure ? 1 : 0);
