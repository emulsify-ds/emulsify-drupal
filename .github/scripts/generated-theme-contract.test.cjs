const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const test = require('node:test');
const yaml = require('js-yaml');

const {
  DEFAULT_SOURCE_DIR,
  formatValidationResult,
  loadSourceContract,
  validateGeneratedTheme,
} = require('./generated-theme-contract.cjs');

const contract = loadSourceContract(DEFAULT_SOURCE_DIR);

function writeFile(root, relativePath, contents = '') {
  const absolutePath = path.join(root, relativePath);
  fs.mkdirSync(path.dirname(absolutePath), { recursive: true });
  fs.writeFileSync(absolutePath, contents);
}

function replaceSourceMachine(value, machineName) {
  return JSON.parse(JSON.stringify(value).split(contract.sourceMachineName).join(machineName));
}

function copyGeneratedDocumentation(themeDir, { machineName, displayName, description, packageJson, project }) {
  const replacements = {
    '%%EMULSIFY_THEME_NAME%%': displayName,
    '%%EMULSIFY_MACHINE_NAME%%': machineName,
    '%%EMULSIFY_DESCRIPTION%%': description || 'No description was supplied during generation.',
    '%%EMULSIFY_SOURCE_PROJECT%%': project.project.generatedFrom,
    '%%EMULSIFY_SOURCE_VERSION%%': project.project.generatedFromVersion,
    '%%EMULSIFY_CORE_RANGE%%': packageJson.dependencies['@emulsify/core'],
  };

  for (const relativePath of [
    'README.md',
    'docs/development.md',
    'docs/support-information.md',
    'docs/upgrading.md',
  ]) {
    const source = fs.readFileSync(path.join(DEFAULT_SOURCE_DIR, relativePath), 'utf8');
    writeFile(
      themeDir,
      relativePath,
      Object.entries(replacements).reduce(
        (contents, [token, value]) => contents.split(token).join(value),
        source,
      ),
    );
  }
}

function createValidTheme(t, overrides = {}) {
  const machineName = overrides.machineName || 'example_theme';
  const displayName = overrides.displayName || 'Example Theme';
  const description = overrides.description || 'Reliable generation: punctuation, paths & metadata.';
  const themeDir = fs.mkdtempSync(path.join(os.tmpdir(), `${machineName}-`));
  t.after(() => fs.rmSync(themeDir, { force: true, recursive: true }));

  const info = {
    type: 'theme',
    'base theme': contract.sourceInfo['base theme'],
    core_version_requirement: contract.starterkit.info.core_version_requirement,
    name: displayName,
    description,
    package: contract.sourceInfo.package,
    version: '1.0.0',
    generator: `${contract.sourceMachineName}:unknown-version`,
    dependencies: contract.sourceInfo.dependencies,
    regions: contract.sourceInfo.regions,
  };
  if (Array.isArray(contract.sourceInfo.libraries)) {
    info.libraries = replaceSourceMachine(contract.sourceInfo.libraries, machineName);
  }
  for (const referenceKey of ['logo', 'screenshot']) {
    if (contract.sourceInfo[referenceKey]) {
      info[referenceKey] = contract.sourceInfo[referenceKey];
    }
  }
  const packageJson = structuredClone(contract.sourcePackage);
  packageJson.name = machineName;
  const project = structuredClone(contract.sourceProject);
  project.project.name = machineName;
  project.project.machineName = machineName;

  writeFile(themeDir, `${machineName}.info.yml`, yaml.dump(info));
  if (contract.sourceLibraries !== null) {
    writeFile(themeDir, `${machineName}.libraries.yml`, yaml.dump(contract.sourceLibraries));
  }
  writeFile(themeDir, `${machineName}.theme`, '<?php\n');
  writeFile(themeDir, `${machineName}.breakpoints.yml`, yaml.dump(replaceSourceMachine(contract.sourceBreakpoints, machineName)));
  writeFile(themeDir, `config/install/${machineName}.settings.yml`, yaml.dump(contract.sourceInstall));
  writeFile(themeDir, `config/schema/${machineName}.schema.yml`, yaml.dump(replaceSourceMachine(contract.sourceSchema, machineName)));
  writeFile(themeDir, 'package.json', `${JSON.stringify(packageJson, null, 2)}\n`);
  writeFile(themeDir, 'project.emulsify.json', `${JSON.stringify(project, null, 2)}\n`);
  copyGeneratedDocumentation(themeDir, {
    machineName,
    displayName,
    description,
    packageJson,
    project,
  });
  for (const referenceKey of ['logo', 'screenshot']) {
    const reference = contract.sourceInfo[referenceKey];
    if (reference && fs.existsSync(path.join(DEFAULT_SOURCE_DIR, reference))) {
      const destination = path.join(themeDir, reference);
      fs.mkdirSync(path.dirname(destination), { recursive: true });
      fs.copyFileSync(path.join(DEFAULT_SOURCE_DIR, reference), destination);
    }
  }

  fs.cpSync(
    path.join(DEFAULT_SOURCE_DIR, 'config/emulsify-core'),
    path.join(themeDir, 'config/emulsify-core'),
    { recursive: true },
  );
  fs.copyFileSync(path.join(DEFAULT_SOURCE_DIR, 'config/jest.config.js'), path.join(themeDir, 'config/jest.config.js'));
  fs.mkdirSync(path.join(themeDir, 'templates/layout'), { recursive: true });
  fs.copyFileSync(path.join(DEFAULT_SOURCE_DIR, 'templates/layout/page.html.twig'), path.join(themeDir, 'templates/layout/page.html.twig'));
  fs.copyFileSync(path.join(DEFAULT_SOURCE_DIR, '.nvmrc'), path.join(themeDir, '.nvmrc'));

  return { themeDir, machineName, displayName, description };
}

function validate(fixture, options = {}) {
  return validateGeneratedTheme({
    ...fixture,
    sourceDir: DEFAULT_SOURCE_DIR,
    ...options,
  });
}

test('accepts a valid component-neutral generated child theme', (t) => {
  const result = validate(createValidTheme(t));
  assert.deepEqual(result.errors, []);
  assert.match(formatValidationResult(result), /PASS Drupal metadata/);
});

test('accepts a valid generated machine name that contains the source name', (t) => {
  const result = validate(createValidTheme(t, {
    machineName: 'whisk_project',
    displayName: 'Whisk Project',
  }));
  assert.deepEqual(result.errors, []);
});

test('reports an unreplaced Starterkit placeholder with its replacement', (t) => {
  const fixture = createValidTheme(t);
  writeFile(fixture.themeDir, 'config/stale.txt', 'namespace: Drupal\\whiskHelper\nlabel: EMULSIFY_NAME\n');

  const output = formatValidationResult(validate(fixture));
  assert.match(output, /FAIL placeholder replacement/);
  assert.match(output, /Generated child theme "example_theme"/);
  assert.match(output, /config\/stale\.txt:1/);
  assert.match(output, /stale starter machine name "whisk"/);
  assert.match(output, /expected "example_theme"/);
  assert.match(output, /stale legacy placeholder "EMULSIFY_NAME"/);
  assert.match(output, /expected "Example Theme"/);
});

test('rejects Starterkit-only files even when their filenames were renamed', (t) => {
  const fixture = createValidTheme(t);
  writeFile(fixture.themeDir, 'example_theme.starterkit.yml', '{}\n');
  writeFile(fixture.themeDir, 'example_theme.info.emulsify.yml', '{}\n');

  const output = formatValidationResult(validate(fixture));
  assert.match(output, /FAIL generation/);
  assert.match(output, /Starterkit-only file "example_theme\.starterkit\.yml"/);
  assert.match(output, /Starterkit-only file "example_theme\.info\.emulsify\.yml"/);
});

test('rejects local references that escape the generated child theme', (t) => {
  const fixture = createValidTheme(t);
  const infoPath = path.join(fixture.themeDir, 'example_theme.info.yml');
  const info = yaml.load(fs.readFileSync(infoPath, 'utf8'));
  info.logo = '../../parent-only-logo.svg';
  fs.writeFileSync(infoPath, yaml.dump(info));

  const output = formatValidationResult(validate(fixture));
  assert.match(output, /FAIL file references/);
  assert.match(output, /logo reference "\.\.\/\.\.\/parent-only-logo\.svg"/);
  assert.match(output, /resolves outside the generated child theme/);
});

test('reports invalid and incorrectly shaped metadata without crashing', (t) => {
  const fixture = createValidTheme(t);
  fs.writeFileSync(path.join(fixture.themeDir, 'package.json'), '{\n');
  fs.writeFileSync(path.join(fixture.themeDir, 'project.emulsify.json'), 'null\n');

  const output = formatValidationResult(validate(fixture));
  assert.match(output, /invalid JSON in "package\.json"/);
  assert.match(output, /requires a top-level JSON object in "project\.emulsify\.json"/);
  assert.doesNotMatch(output, /contract could not run/);
});

test('groups inconsistent package and project metadata into actionable output', (t) => {
  const fixture = createValidTheme(t);
  const packagePath = path.join(fixture.themeDir, 'package.json');
  const packageJson = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
  packageJson.dependencies['@emulsify/core'] = '^3.0.0';
  fs.writeFileSync(packagePath, `${JSON.stringify(packageJson, null, 2)}\n`);

  const projectPath = path.join(fixture.themeDir, 'project.emulsify.json');
  const project = JSON.parse(fs.readFileSync(projectPath, 'utf8'));
  project.project.machineName = 'wrong_theme';
  fs.writeFileSync(projectPath, `${JSON.stringify(project, null, 2)}\n`);

  const output = formatValidationResult(validate(fixture));
  assert.match(output, /FAIL frontend metadata/);
  assert.match(output, /dependencies\.@emulsify\/core/);
  assert.ok(output.includes(`expected "${contract.sourcePackage.dependencies['@emulsify/core']}"`), output);
  assert.match(output, /project\.machineName/);
  assert.match(output, /expected "example_theme"/);
});

test('does not require starter-owned source or build outputs', (t) => {
  const fixture = createValidTheme(t);
  assert.equal(fs.existsSync(path.join(fixture.themeDir, 'src')), false);
  const libraries = yaml.load(fs.readFileSync(path.join(fixture.themeDir, 'example_theme.libraries.yml'), 'utf8'));
  assert.deepEqual(libraries.global.css.theme, {});
  assert.deepEqual(libraries.global.js, {});
  assert.deepEqual(validate(fixture).errors, []);
  assert.deepEqual(validate(fixture, { checkBuiltAssets: true }).errors, []);
});

test('requires the generated documentation set', (t) => {
  for (const requiredFile of [
    'README.md',
    'docs/development.md',
    'docs/support-information.md',
    'docs/upgrading.md',
  ]) {
    const fixture = createValidTheme(t);
    fs.rmSync(path.join(fixture.themeDir, requiredFile));

    const output = formatValidationResult(validate(fixture));
    assert.match(output, /FAIL generation/);
    assert.ok(output.includes(`missing required generated file "${requiredFile}"`));
    assert.match(output, /FAIL documentation/);
  }
});

test('reports project-specific documentation values and leftover tokens', (t) => {
  const fixture = createValidTheme(t);
  const readmePath = path.join(fixture.themeDir, 'README.md');
  const readme = fs.readFileSync(readmePath, 'utf8')
    .replace(fixture.description, 'A stale description')
    .split(fixture.machineName).join('%%EMULSIFY_MACHINE_NAME%%');
  fs.writeFileSync(readmePath, readme);

  const output = formatValidationResult(validate(fixture));
  assert.match(output, /FAIL documentation/);
  assert.match(output, /missing its machine name "example_theme"/);
  assert.match(output, /missing its description/);
  assert.match(output, /FAIL placeholder replacement/);
  assert.match(output, /documentation token/);
});

test('reports a documented npm command that package.json does not expose', (t) => {
  const fixture = createValidTheme(t);
  const supportPath = path.join(fixture.themeDir, 'docs/support-information.md');
  fs.appendFileSync(supportPath, '\n```bash\nnpm run impossible-script\n```\n');

  const output = formatValidationResult(validate(fixture));
  assert.match(output, /FAIL documentation/);
  assert.match(output, /support-information\.md:\d+ documents npm run impossible-script/);
  assert.match(output, /package\.json has no "impossible-script" script/);
});

test('rejects retired terminology in docs without scanning third-party lock metadata', (t) => {
  const fixture = createValidTheme(t);
  writeFile(fixture.themeDir, 'package-lock.json', '{"packages":{"node_modules/example":{"description":"webpack adapter"}}}\n');
  assert.deepEqual(validate(fixture).errors, []);

  fs.appendFileSync(path.join(fixture.themeDir, 'README.md'), '\nThis child theme uses Webpack.\n');
  const output = formatValidationResult(validate(fixture));
  assert.match(output, /FAIL placeholder replacement/);
  assert.match(output, /retired frontend tooling "Webpack"/);
  assert.match(output, /README\.md/);
});
