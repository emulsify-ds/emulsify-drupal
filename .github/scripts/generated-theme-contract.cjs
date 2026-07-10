#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { isDeepStrictEqual } = require('util');
const yaml = require('js-yaml');
const { validateDocumentation } = require('./docs-command-check.cjs');

const DEFAULT_SOURCE_DIR = path.resolve(__dirname, '../../whisk');
const SECTION_LABELS = {
  generation: 'generation',
  drupal: 'Drupal metadata',
  frontend: 'frontend metadata',
  references: 'file references',
  documentation: 'documentation',
  placeholders: 'placeholder replacement',
  build: 'build',
};
const REQUIRED_DOCUMENTED_SCRIPTS = ['develop', 'build', 'storybook-build', 'test', 'a11y'];
const IGNORED_SCAN_DIRECTORIES = new Set(['node_modules', 'dist', '.out', '.coverage', '.git']);

function readJson(absolutePath) {
  return JSON.parse(fs.readFileSync(absolutePath, 'utf8'));
}

function readYaml(absolutePath) {
  return yaml.load(fs.readFileSync(absolutePath, 'utf8'));
}

function listFiles(root, onSymlink = () => {}) {
  const files = [];

  function visit(directory) {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
      if (entry.isDirectory() && IGNORED_SCAN_DIRECTORIES.has(entry.name)) {
        continue;
      }

      const absolutePath = path.join(directory, entry.name);
      if (entry.isSymbolicLink()) {
        onSymlink(absolutePath);
      }
      else if (entry.isDirectory()) {
        visit(absolutePath);
      }
      else if (entry.isFile()) {
        files.push(absolutePath);
      }
    }
  }

  if (fs.existsSync(root)) {
    visit(root);
  }

  return files;
}

function toRelative(root, absolutePath) {
  return path.relative(root, absolutePath).split(path.sep).join('/');
}

function isTextFile(absolutePath) {
  const buffer = fs.readFileSync(absolutePath);
  return !buffer.includes(0);
}

function isMapping(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function replaceMachineName(value, sourceMachineName, machineName) {
  if (Array.isArray(value)) {
    return value.map((item) => replaceMachineName(item, sourceMachineName, machineName));
  }
  if (isMapping(value)) {
    return Object.fromEntries(Object.entries(value).map(([key, item]) => [
      key.split(sourceMachineName).join(machineName),
      replaceMachineName(item, sourceMachineName, machineName),
    ]));
  }
  if (typeof value === 'string') {
    return value.split(sourceMachineName).join(machineName);
  }
  return value;
}

function containsStaleSourceName(value, sourceMachineName, ...expectedValues) {
  const remaining = expectedValues
    .filter(Boolean)
    .reduce(
      (text, expectedValue) => text.replace(new RegExp(escapeRegExp(expectedValue), 'ig'), ''),
      String(value),
    );
  return remaining.toLowerCase().includes(sourceMachineName.toLowerCase());
}

function loadSourceContract(sourceDir = DEFAULT_SOURCE_DIR) {
  const sourceMachineName = path.basename(sourceDir);
  const sourceInfoPath = path.join(sourceDir, `${sourceMachineName}.info.yml`);
  const starterkitPath = path.join(sourceDir, `${sourceMachineName}.starterkit.yml`);
  const sourcePackagePath = path.join(sourceDir, 'package.json');
  const sourceProjectPath = path.join(sourceDir, 'project.emulsify.json');

  for (const requiredPath of [sourceInfoPath, starterkitPath, sourcePackagePath, sourceProjectPath]) {
    if (!fs.existsSync(requiredPath)) {
      throw new Error(`Unable to load generated child theme source contract: missing ${requiredPath}.`);
    }
  }

  const sourceInfo = readYaml(sourceInfoPath);
  const starterkit = readYaml(starterkitPath);
  const sourcePackage = readJson(sourcePackagePath);
  const sourceProject = readJson(sourceProjectPath);
  const alternateInfoPath = path.join(sourceDir, `${sourceMachineName}.info.emulsify.yml`);
  const alternateInfo = fs.existsSync(alternateInfoPath) ? readYaml(alternateInfoPath) : {};
  const readOptionalYaml = (relativePath) => {
    const absolutePath = path.join(sourceDir, relativePath);
    return fs.existsSync(absolutePath) ? readYaml(absolutePath) : null;
  };

  return {
    sourceDir,
    sourceMachineName,
    sourceInfo,
    starterkit,
    sourcePackage,
    sourceProject,
    sourceLibraries: readYaml(path.join(sourceDir, `${sourceMachineName}.libraries.yml`)),
    sourceBreakpoints: readOptionalYaml(`${sourceMachineName}.breakpoints.yml`),
    sourceInstall: readOptionalYaml(`config/install/${sourceMachineName}.settings.yml`),
    sourceSchema: readOptionalYaml(`config/schema/${sourceMachineName}.schema.yml`),
    staleDisplayValues: [...new Set(
      [sourceInfo.name, alternateInfo.name]
        .filter((value) => value && String(value).toLowerCase() !== sourceMachineName.toLowerCase()),
    )],
    staleDescriptionValues: [...new Set([sourceInfo.description, alternateInfo.description].filter(Boolean))],
  };
}

function validateGeneratedTheme({
  themeDir,
  machineName,
  displayName,
  description,
  sourceDir = DEFAULT_SOURCE_DIR,
  checkBuiltAssets = false,
}) {
  const errors = [];
  const addError = (section, message) => errors.push({ section, message });
  const contract = loadSourceContract(sourceDir);
  const expected = (value) => JSON.stringify(value);
  const themeLabel = `Generated child theme ${expected(machineName)}`;

  if (!fs.existsSync(themeDir) || !fs.statSync(themeDir).isDirectory()) {
    addError('generation', `${themeLabel} is missing output directory ${expected(themeDir)}.`);
    return { machineName, errors, checkBuiltAssets, generationOnly: true };
  }

  const files = listFiles(themeDir, (absolutePath) => {
    addError(
      'references',
      `${themeLabel} contains symbolic link ${expected(toRelative(themeDir, absolutePath))}; generated files must be self-contained.`,
    );
  });
  const relativeFiles = new Set(files.map((file) => toRelative(themeDir, file)));

  const expectedRenamedFiles = [
    [`${contract.sourceMachineName}.info.yml`, `${machineName}.info.yml`],
    [`${contract.sourceMachineName}.libraries.yml`, `${machineName}.libraries.yml`],
    [`${contract.sourceMachineName}.theme`, `${machineName}.theme`],
    [`${contract.sourceMachineName}.breakpoints.yml`, `${machineName}.breakpoints.yml`],
    [`config/install/${contract.sourceMachineName}.settings.yml`, `config/install/${machineName}.settings.yml`],
    [`config/schema/${contract.sourceMachineName}.schema.yml`, `config/schema/${machineName}.schema.yml`],
  ];

  for (const [sourceRelativePath, generatedRelativePath] of expectedRenamedFiles) {
    if (fs.existsSync(path.join(sourceDir, sourceRelativePath)) && !relativeFiles.has(generatedRelativePath)) {
      addError(
        'generation',
        `${themeLabel} is missing generated filename ${expected(generatedRelativePath)}; expected the ${expected(sourceRelativePath)} Starterkit source file to use machine name ${expected(machineName)}.`,
      );
    }
  }

  for (const requiredFile of [
    'package.json',
    'project.emulsify.json',
    'README.md',
    'UPGRADING.md',
    'docs/support-information.md',
  ]) {
    if (fs.existsSync(path.join(sourceDir, requiredFile)) && !relativeFiles.has(requiredFile)) {
      addError('generation', `${themeLabel} is missing required generated file ${expected(requiredFile)}.`);
    }
  }

  const copiedToolingPaths = [
    ...listFiles(path.join(sourceDir, 'config/emulsify-core'))
      .filter((file) => path.basename(file) !== '.DS_Store')
      .map((file) => toRelative(sourceDir, file)),
    'config/jest.config.js',
    'templates/layout/page.html.twig',
    'screenshot.png',
    '.nvmrc',
  ].filter((relativePath) => fs.existsSync(path.join(sourceDir, relativePath)));
  for (const relativePath of copiedToolingPaths) {
    if (!relativeFiles.has(relativePath)) {
      addError(
        'generation',
        `${themeLabel} is missing generated tooling file ${expected(relativePath)}; expected it from the current Whisk Starterkit contract.`,
      );
    }
    else if ((relativePath.startsWith('config/emulsify-core/') || relativePath === 'screenshot.png')
      && !fs.readFileSync(path.join(themeDir, relativePath)).equals(fs.readFileSync(path.join(sourceDir, relativePath)))) {
      addError(
        'frontend',
        `${themeLabel} changed protected tooling file ${expected(relativePath)}; expected Drupal Starterkit no_edit content to remain byte-for-byte identical.`,
      );
    }
  }

  for (const relativeFile of relativeFiles) {
    if (/\.starterkit\.yml$|\.info\.emulsify\.yml$/.test(relativeFile)) {
      addError('generation', `${themeLabel} retained Starterkit-only file ${expected(relativeFile)}; expected it to be omitted.`);
    }
  }

  for (const relativeFile of relativeFiles) {
    if (containsStaleSourceName(relativeFile, contract.sourceMachineName, machineName)) {
      addError(
        'generation',
        `${themeLabel} retained source machine name ${expected(contract.sourceMachineName)} in filename ${expected(relativeFile)}; expected ${expected(machineName)}.`,
      );
    }
  }

  const parsed = {};
  const parseGeneratedYaml = (relativePath, section) => {
    if (!relativeFiles.has(relativePath)) {
      addError(section, `${themeLabel} cannot validate missing required YAML file ${expected(relativePath)}.`);
      return null;
    }
    try {
      const value = readYaml(path.join(themeDir, relativePath));
      if (!isMapping(value)) {
        addError(section, `${themeLabel} requires a top-level YAML mapping in ${expected(relativePath)}; found ${expected(value)}.`);
        return null;
      }
      return value;
    }
    catch (error) {
      addError(section, `${themeLabel} has invalid YAML in ${expected(relativePath)}: ${error.message}`);
      return null;
    }
  };
  const parseGeneratedJson = (relativePath, section) => {
    if (!relativeFiles.has(relativePath)) {
      addError(section, `${themeLabel} cannot validate missing required JSON file ${expected(relativePath)}.`);
      return null;
    }
    try {
      const value = readJson(path.join(themeDir, relativePath));
      if (!isMapping(value)) {
        addError(section, `${themeLabel} requires a top-level JSON object in ${expected(relativePath)}; found ${expected(value)}.`);
        return null;
      }
      return value;
    }
    catch (error) {
      addError(section, `${themeLabel} has invalid JSON in ${expected(relativePath)}: ${error.message}`);
      return null;
    }
  };

  const infoRelativePath = `${machineName}.info.yml`;
  const librariesRelativePath = `${machineName}.libraries.yml`;
  const breakpointsRelativePath = `${machineName}.breakpoints.yml`;
  const installRelativePath = `config/install/${machineName}.settings.yml`;
  const schemaRelativePath = `config/schema/${machineName}.schema.yml`;
  parsed.info = parseGeneratedYaml(infoRelativePath, 'drupal');
  parsed.libraries = parseGeneratedYaml(librariesRelativePath, 'drupal');
  parsed.breakpoints = parseGeneratedYaml(breakpointsRelativePath, 'drupal');
  parsed.install = parseGeneratedYaml(installRelativePath, 'drupal');
  parsed.schema = parseGeneratedYaml(schemaRelativePath, 'drupal');
  parsed.package = parseGeneratedJson('package.json', 'frontend');
  parsed.project = parseGeneratedJson('project.emulsify.json', 'frontend');

  if (parsed.info) {
    checkEqual(addError, 'drupal', themeLabel, infoRelativePath, 'name', parsed.info.name, displayName);
    checkEqual(addError, 'drupal', themeLabel, infoRelativePath, 'description', parsed.info.description, description);
    checkEqual(addError, 'drupal', themeLabel, infoRelativePath, 'type', parsed.info.type, 'theme');
    checkEqual(addError, 'drupal', themeLabel, infoRelativePath, 'base theme', parsed.info['base theme'], contract.sourceInfo['base theme']);
    checkEqual(
      addError,
      'drupal',
      themeLabel,
      infoRelativePath,
      'core_version_requirement',
      parsed.info.core_version_requirement,
      contract.starterkit.info.core_version_requirement,
    );

    if (parsed.info.hidden === true || parsed.info.starterkit === true) {
      addError('drupal', `${themeLabel} is still marked as a Starterkit source in ${expected(infoRelativePath)}; expected an installable generated child theme.`);
    }
    if (!isDeepStrictEqual(parsed.info.regions, contract.sourceInfo.regions)) {
      addError(
        'drupal',
        `${themeLabel} has inconsistent regions in ${expected(infoRelativePath)}; expected ${expected(contract.sourceInfo.regions)}, found ${expected(parsed.info.regions)}.`,
      );
    }
    if (!isDeepStrictEqual(parsed.info.dependencies, contract.sourceInfo.dependencies)) {
      addError(
        'drupal',
        `${themeLabel} has inconsistent Drupal dependencies in ${expected(infoRelativePath)}; expected ${expected(contract.sourceInfo.dependencies)}, found ${expected(parsed.info.dependencies)}.`,
      );
    }
    const expectedInfoLibraries = replaceMachineName(
      contract.sourceInfo.libraries,
      contract.sourceMachineName,
      machineName,
    );
    if (!isDeepStrictEqual(parsed.info.libraries, expectedInfoLibraries)) {
      addError(
        'drupal',
        `${themeLabel} has inconsistent library references in ${expected(infoRelativePath)}; expected ${expected(expectedInfoLibraries)}, found ${expected(parsed.info.libraries)}.`,
      );
    }
    const generatorPattern = new RegExp(`^${escapeRegExp(contract.sourceMachineName)}:[A-Za-z0-9._+#-]+$`);
    if (typeof parsed.info.generator !== 'string' || !generatorPattern.test(parsed.info.generator)) {
      addError(
        'drupal',
        `${themeLabel} is missing Drupal Starterkit lineage in ${expected(infoRelativePath)} key "generator"; expected ${expected(`${contract.sourceMachineName}:<source-version>`)}.`,
      );
    }

    validateInfoReferences({
      addError,
      themeDir,
      themeLabel,
      machineName,
      infoRelativePath,
      info: parsed.info,
      libraries: parsed.libraries,
    });
  }

  if (parsed.breakpoints) {
    const expectedBreakpoints = replaceMachineName(contract.sourceBreakpoints, contract.sourceMachineName, machineName);
    if (!isDeepStrictEqual(parsed.breakpoints, expectedBreakpoints)) {
      addError(
        'drupal',
        `${themeLabel} has inconsistent breakpoint metadata in ${expected(breakpointsRelativePath)}; expected the complete machine-name-adjusted Whisk breakpoint contract.`,
      );
    }
  }

  if (parsed.install && !isDeepStrictEqual(parsed.install, contract.sourceInstall)) {
    addError('drupal', `${themeLabel} has inconsistent install settings in ${expected(installRelativePath)}; expected the complete Whisk settings defaults.`);
  }

  if (parsed.schema) {
    const expectedSchema = replaceMachineName(contract.sourceSchema, contract.sourceMachineName, machineName);
    if (!isDeepStrictEqual(parsed.schema, expectedSchema)) {
      addError(
        'drupal',
        `${themeLabel} has inconsistent settings schema in ${expected(schemaRelativePath)}; expected the complete machine-name-adjusted Whisk schema contract.`,
      );
    }
  }

  if (parsed.package) {
    checkEqual(addError, 'frontend', themeLabel, 'package.json', 'name', parsed.package.name, machineName);
    checkEqual(addError, 'frontend', themeLabel, 'package.json', 'license', parsed.package.license, contract.sourcePackage.license);
    const scripts = isMapping(parsed.package.scripts) ? parsed.package.scripts : {};
    if (!isMapping(parsed.package.scripts)) {
      addError('frontend', `${themeLabel} requires a scripts object in "package.json"; found ${expected(parsed.package.scripts)}.`);
    }
    for (const script of REQUIRED_DOCUMENTED_SCRIPTS) {
      if (typeof scripts[script] !== 'string' || scripts[script].trim() === '') {
        addError('frontend', `${themeLabel} is missing documented npm script ${expected(script)} in "package.json".`);
      }
    }
    checkEqual(
      addError,
      'frontend',
      themeLabel,
      'package.json',
      'dependencies.@emulsify/core',
      parsed.package.dependencies && parsed.package.dependencies['@emulsify/core'],
      contract.sourcePackage.dependencies['@emulsify/core'],
    );
    validatePackageScriptReferences({ addError, themeDir, themeLabel, scripts, packageJson: parsed.package });
  }

  if (parsed.project) {
    const sourceProject = contract.sourceProject.project;
    const project = isMapping(parsed.project.project) ? parsed.project.project : {};
    if (!isMapping(parsed.project.project)) {
      addError('frontend', `${themeLabel} requires a project object in "project.emulsify.json"; found ${expected(parsed.project.project)}.`);
    }
    for (const [key, value] of [
      ['platform', sourceProject.platform],
      ['name', machineName],
      ['machineName', machineName],
      ['singleDirectoryComponents', sourceProject.singleDirectoryComponents],
      ['generatedFrom', sourceProject.generatedFrom],
      ['generatedFromVersion', sourceProject.generatedFromVersion],
    ]) {
      checkEqual(addError, 'frontend', themeLabel, 'project.emulsify.json', `project.${key}`, project[key], value);
    }
    if (!isDeepStrictEqual(parsed.project.starter, contract.sourceProject.starter)) {
      addError(
        'frontend',
        `${themeLabel} has inconsistent generated-source repository metadata in "project.emulsify.json"; expected ${expected(contract.sourceProject.starter)}, found ${expected(parsed.project.starter)}.`,
      );
    }
  }

  if (parsed.libraries) {
    const expectedLibraries = replaceMachineName(contract.sourceLibraries, contract.sourceMachineName, machineName);
    if (!isDeepStrictEqual(parsed.libraries, expectedLibraries)) {
      addError(
        'drupal',
        `${themeLabel} has incomplete or inconsistent Drupal libraries in ${expected(librariesRelativePath)}; expected the complete Whisk library contract.`,
      );
    }
    validateLibraryAssets({
      addError,
      themeDir,
      themeLabel,
      librariesRelativePath,
      libraries: parsed.libraries,
      sourceLibraries: contract.sourceLibraries,
      checkBuiltAssets,
    });
  }

  validateConfigReferences({ addError, themeDir, themeLabel, files, packageJson: parsed.package });
  validateMarkdownReferences({ addError, themeDir, themeLabel, files });
  validateGeneratedDocumentation({
    addError,
    themeDir,
    themeLabel,
    machineName,
    displayName,
    description,
    packageJson: parsed.package,
    projectJson: parsed.project,
  });
  validatePlaceholders({ addError, themeDir, themeLabel, machineName, displayName, description, contract, files });

  return { machineName, errors, checkBuiltAssets, generationOnly: false };
}

function validateGeneratedDocumentation({
  addError,
  themeDir,
  themeLabel,
  machineName,
  displayName,
  description,
  packageJson,
  projectJson,
}) {
  const result = validateDocumentation({ generatedTheme: themeDir });
  for (const error of result.errors) {
    addError('documentation', `${themeLabel}: ${error}`);
  }

  const readmePath = path.join(themeDir, 'README.md');
  if (!fs.existsSync(readmePath)) {
    return;
  }

  const readme = fs.readFileSync(readmePath, 'utf8');
  const project = isMapping(projectJson && projectJson.project) ? projectJson.project : {};
  const coreRange = packageJson && packageJson.dependencies && packageJson.dependencies['@emulsify/core'];
  const expectedDescription = normalizeWhitespace(description) || 'No description was supplied during generation.';
  const expectedValues = [
    ['display name', normalizeWhitespace(displayName)],
    ['machine name', normalizeWhitespace(machineName)],
    ['description', expectedDescription],
    ['generated source project', project.generatedFrom],
    ['generated source version', project.generatedFromVersion],
    ['Emulsify Core range', coreRange],
    ['generated info filename', `${machineName}.info.yml`],
    ['generated libraries filename', `${machineName}.libraries.yml`],
  ];

  for (const [label, value] of expectedValues) {
    if (typeof value === 'string' && value !== '' && !readme.includes(value)) {
      addError(
        'documentation',
        `${themeLabel} README.md is missing its ${label} ${JSON.stringify(value)}; expected project-specific generation metadata.`,
      );
    }
  }
}

function checkEqual(addError, section, themeLabel, relativePath, key, actual, expectedValue) {
  if (!isDeepStrictEqual(actual, expectedValue)) {
    addError(
      section,
      `${themeLabel} has ${JSON.stringify(key)} ${JSON.stringify(actual)} in ${JSON.stringify(relativePath)}; expected ${JSON.stringify(expectedValue)}.`,
    );
  }
}

function validateInfoReferences({ addError, themeDir, themeLabel, machineName, infoRelativePath, info, libraries }) {
  const libraryDefinitions = libraries || {};
  if (!Array.isArray(info.libraries)) {
    addError('drupal', `${themeLabel} requires a libraries list in ${JSON.stringify(infoRelativePath)}; found ${JSON.stringify(info.libraries)}.`);
  }

  for (const reference of Array.isArray(info.libraries) ? info.libraries : []) {
    const [provider, libraryName] = String(reference).split('/');
    if (provider !== machineName) {
      addError(
        'drupal',
        `${themeLabel} uses library reference ${JSON.stringify(reference)} in ${JSON.stringify(infoRelativePath)}; expected provider ${JSON.stringify(`${machineName}/`)}.`,
      );
    }
    if (!libraryName || !Object.prototype.hasOwnProperty.call(libraryDefinitions, libraryName)) {
      addError(
        'drupal',
        `${themeLabel} references missing Drupal library ${JSON.stringify(reference)} from ${JSON.stringify(infoRelativePath)}; expected a matching definition in ${JSON.stringify(`${machineName}.libraries.yml`)}.`,
      );
    }
  }

  for (const key of ['logo', 'screenshot']) {
    const reference = info[key];
    if (typeof reference === 'string' && !isExternalReference(reference)) {
      validateLocalReference({
        addError,
        themeDir,
        themeLabel,
        sourceRelativePath: infoRelativePath,
        reference,
        category: `${key} reference`,
      });
    }
  }
}

function validateLibraryAssets({
  addError,
  themeDir,
  themeLabel,
  librariesRelativePath,
  libraries,
  sourceLibraries,
  checkBuiltAssets,
}) {
  for (const [libraryName, definition] of Object.entries(libraries)) {
    if (!isMapping(definition)) {
      addError('drupal', `${themeLabel} requires library ${JSON.stringify(libraryName)} in ${JSON.stringify(librariesRelativePath)} to be a mapping; found ${JSON.stringify(definition)}.`);
      continue;
    }
    if (definition.css !== undefined && !isMapping(definition.css)) {
      addError('drupal', `${themeLabel} requires CSS metadata for library ${JSON.stringify(libraryName)} in ${JSON.stringify(librariesRelativePath)} to be a mapping.`);
    }
    if (definition.js !== undefined && !isMapping(definition.js)) {
      addError('drupal', `${themeLabel} requires JavaScript metadata for library ${JSON.stringify(libraryName)} in ${JSON.stringify(librariesRelativePath)} to be a mapping.`);
    }

    for (const asset of collectLibraryAssets(definition)) {
      if (isExternalReference(asset.path)) {
        continue;
      }

      const outputPath = path.join(themeDir, asset.path);
      validateLocalReference({
        addError,
        themeDir,
        themeLabel,
        sourceRelativePath: librariesRelativePath,
        reference: asset.path,
        category: `${asset.type.toUpperCase()} asset reference`,
        allowMissing: asset.path.startsWith('dist/'),
      });
      if (checkBuiltAssets && !fs.existsSync(outputPath)) {
        addError(
          'build',
          `${themeLabel} references missing built ${asset.type.toUpperCase()} asset ${JSON.stringify(asset.path)} from ${JSON.stringify(librariesRelativePath)} library ${JSON.stringify(libraryName)}; expected npm run build to create it.`,
        );
      }

      if (asset.type === 'css' && asset.path.startsWith('dist/global/') && asset.path.endsWith('.css')) {
        const sourcePath = `src/${path.basename(asset.path, '.css')}.scss`;
        if (!fs.existsSync(path.join(themeDir, sourcePath))) {
          addError(
            'references',
            `${themeLabel} references missing Sass entrypoint ${JSON.stringify(sourcePath)} from ${JSON.stringify(librariesRelativePath)} output ${JSON.stringify(asset.path)}; expected the source file to exist in the generated child theme.`,
          );
        }
      }
    }
  }

  for (const sourceLibraryName of Object.keys(sourceLibraries || {})) {
    if (!Object.prototype.hasOwnProperty.call(libraries, sourceLibraryName)) {
      addError(
        'drupal',
        `${themeLabel} is missing Drupal library ${JSON.stringify(sourceLibraryName)} in ${JSON.stringify(librariesRelativePath)}; expected it from the Whisk source contract.`,
      );
      continue;
    }

    const expectedAssets = collectLibraryAssets(sourceLibraries[sourceLibraryName]);
    const generatedAssets = new Set(
      collectLibraryAssets(libraries[sourceLibraryName]).map((asset) => `${asset.type}:${asset.path}`),
    );
    for (const asset of expectedAssets) {
      if (!generatedAssets.has(`${asset.type}:${asset.path}`)) {
        addError(
          'drupal',
          `${themeLabel} is missing expected ${asset.type.toUpperCase()} output ${JSON.stringify(asset.path)} from ${JSON.stringify(librariesRelativePath)} library ${JSON.stringify(sourceLibraryName)}; expected it from the Whisk source contract.`,
        );
      }
    }
  }
}

function collectLibraryAssets(definition) {
  const assets = [];
  if (!isMapping(definition)) {
    return assets;
  }
  for (const [category, entries] of Object.entries(isMapping(definition.css) ? definition.css : {})) {
    if (entries && typeof entries === 'object') {
      for (const assetPath of Object.keys(entries)) {
        assets.push({ type: 'css', category, path: assetPath });
      }
    }
  }
  for (const assetPath of Object.keys(isMapping(definition.js) ? definition.js : {})) {
    assets.push({ type: 'js', path: assetPath });
  }
  return assets;
}

function validatePackageScriptReferences({ addError, themeDir, themeLabel, scripts, packageJson }) {
  for (const [scriptName, command] of Object.entries(scripts)) {
    for (const match of command.matchAll(/--(?:config|ignore-path)\s+(['"]?)([^\s'"]+)\1/g)) {
      validateLocalReference({
        addError,
        themeDir,
        themeLabel,
        sourceRelativePath: 'package.json',
        reference: match[2],
        category: `npm script ${JSON.stringify(scriptName)}`,
        packageJson: packageJson || {},
      });
    }
    for (const match of command.matchAll(/\b(node_modules\/[^\s'"]+)/g)) {
      validateLocalReference({
        addError,
        themeDir,
        themeLabel,
        sourceRelativePath: 'package.json',
        reference: match[1],
        category: `npm script ${JSON.stringify(scriptName)}`,
        packageJson,
      });
    }
  }
}

function validateConfigReferences({ addError, themeDir, themeLabel, files, packageJson }) {
  for (const absolutePath of files) {
    const relativePath = toRelative(themeDir, absolutePath);
    if (!/\.(?:c?js|mjs|json)$/.test(relativePath) || !isTextFile(absolutePath)) {
      continue;
    }

    const contents = fs.readFileSync(absolutePath, 'utf8');
    const references = [];
    for (const match of contents.matchAll(/^\s*import(?:[\s\S]*?from\s*)?['"]([^'"]+)['"];?\s*$/gm)) {
      references.push(match[1]);
    }
    for (const match of contents.matchAll(/^\s*(?:module\.exports\s*=\s*)?require\(['"]([^'"]+)['"]\)/gm)) {
      references.push(match[1]);
    }

    if (relativePath.endsWith('.json')) {
      try {
        const parsed = JSON.parse(contents);
        const extendsValues = Array.isArray(parsed.extends) ? parsed.extends : parsed.extends ? [parsed.extends] : [];
        references.push(...extendsValues);
      }
      catch {
        // Invalid required JSON files are reported by their dedicated checks.
      }
    }

    for (const reference of references.filter((candidate) => candidate.startsWith('.'))) {
      validateLocalReference({
        addError,
        themeDir,
        themeLabel,
        sourceRelativePath: relativePath,
        reference,
        category: 'configuration import',
        packageJson: packageJson || {},
      });
    }
  }
}

function validateMarkdownReferences({ addError, themeDir, themeLabel, files }) {
  for (const absolutePath of files.filter((file) => file.endsWith('.md'))) {
    const relativePath = toRelative(themeDir, absolutePath);
    const contents = fs.readFileSync(absolutePath, 'utf8');
    for (const match of contents.matchAll(/\[[^\]]*\]\(([^)]+)\)/g)) {
      const reference = match[1].trim().replace(/^<|>$/g, '').split('#')[0];
      if (!reference || isExternalReference(reference)) {
        continue;
      }
      validateLocalReference({
        addError,
        themeDir,
        themeLabel,
        sourceRelativePath: relativePath,
        reference,
        category: 'documentation link',
      });
    }
  }
}

function validateLocalReference({
  addError,
  themeDir,
  themeLabel,
  sourceRelativePath,
  reference,
  category,
  packageJson = {},
  allowMissing = false,
}) {
  const sourceDirectory = path.dirname(path.join(themeDir, sourceRelativePath));
  const absoluteReference = path.resolve(sourceDirectory, reference);
  const relativeReference = path.relative(themeDir, absoluteReference);
  if (relativeReference === '..' || relativeReference.startsWith(`..${path.sep}`) || path.isAbsolute(relativeReference)) {
    addError(
      'references',
      `${themeLabel} has ${category} ${JSON.stringify(reference)} in ${JSON.stringify(sourceRelativePath)} that resolves outside the generated child theme; expected a self-contained path or published package.`,
    );
    return;
  }

  const normalized = relativeReference.split(path.sep).join('/');
  if (normalized.startsWith('node_modules/')) {
    if (normalized.startsWith('node_modules/@emulsify/core/') && !(packageJson.dependencies && packageJson.dependencies['@emulsify/core'])) {
      addError(
        'references',
        `${themeLabel} has ${category} ${JSON.stringify(reference)} in ${JSON.stringify(sourceRelativePath)} but package.json does not declare @emulsify/core.`,
      );
    }
    return;
  }

  if (!allowMissing && !referenceExists(absoluteReference)) {
    addError(
      'references',
      `${themeLabel} has ${category} ${JSON.stringify(reference)} in ${JSON.stringify(sourceRelativePath)} that resolves to missing local path ${JSON.stringify(normalized)}; expected it inside the generated child theme.`,
    );
  }
}

function referenceExists(absolutePath) {
  if (fs.existsSync(absolutePath)) {
    return true;
  }
  return ['.js', '.cjs', '.mjs', '.json'].some((extension) => fs.existsSync(`${absolutePath}${extension}`));
}

function validatePlaceholders({ addError, themeDir, themeLabel, machineName, displayName, description, contract, files }) {
  const staleValuePatterns = [
    ...contract.staleDisplayValues.map((value) => ({ category: 'starter display name', value, replacement: displayName })),
    ...contract.staleDescriptionValues.map((value) => ({ category: 'placeholder description', value, replacement: 'the requested description' })),
    { category: 'Starterkit marker', value: '#@starterkit:', replacement: 'generated theme content' },
    {
      category: 'retired Stable9 inheritance',
      value: 'Stable9 inheritance language',
      pattern: /(?:inherits?\s+(?:from\s+)?stable9|stable9\s+(?:parent|base)\s+theme|base\s+theme:\s*stable9)/i,
      replacement: 'the Emulsify parent-theme architecture',
    },
    { category: 'legacy placeholder', value: 'EMULSIFY_NAME', replacement: displayName },
    {
      category: 'documentation token',
      value: '%%EMULSIFY_*%%',
      pattern: /%%EMULSIFY_[A-Z_]+%%/,
      replacement: 'project-specific generated documentation',
    },
    {
      category: 'retired frontend tooling',
      value: 'Webpack',
      pattern: /\bwebpack\b/i,
      replacement: 'the Vite build workflow',
      markdownOnly: true,
    },
    {
      category: 'retired theme terminology',
      value: 'subtheme',
      pattern: /\bsub[ -]?theme\b/i,
      replacement: 'child theme',
      markdownOnly: true,
    },
  ];

  for (const absolutePath of files) {
    if (!isTextFile(absolutePath)) {
      continue;
    }
    const relativePath = toRelative(themeDir, absolutePath);
    const lines = fs.readFileSync(absolutePath, 'utf8').split(/\r?\n/);

    for (let index = 0; index < lines.length; index += 1) {
      const line = lines[index];
      let matchedSpecificValue = false;
      for (const stale of staleValuePatterns) {
        if (stale.markdownOnly && !relativePath.endsWith('.md')) {
          continue;
        }
        const pattern = stale.pattern || new RegExp(escapeRegExp(stale.value), 'i');
        if (pattern.test(line)) {
          matchedSpecificValue = true;
          addError(
            'placeholders',
            `${themeLabel} contains stale ${stale.category} ${JSON.stringify(stale.value)} in ${JSON.stringify(`${relativePath}:${index + 1}`)}; expected ${JSON.stringify(stale.replacement)}.`,
          );
        }
      }

      if (containsStaleSourceName(line, contract.sourceMachineName, machineName, displayName, description)
        && !matchedSpecificValue
        && !isAllowedSourceReference(relativePath, line, machineName, contract.sourceMachineName)) {
        addError(
          'placeholders',
          `${themeLabel} contains stale starter machine name ${JSON.stringify(contract.sourceMachineName)} in ${JSON.stringify(`${relativePath}:${index + 1}`)}; expected ${JSON.stringify(machineName)}.`,
        );
      }
    }
  }
}

function normalizeWhitespace(value) {
  return String(value || '').replace(/\s+/g, ' ').trim();
}

function isAllowedSourceReference(relativePath, line, machineName, sourceMachineName) {
  return relativePath === `${machineName}.info.yml`
    && new RegExp(`^\\s*generator:\\s*(['"]?)${escapeRegExp(sourceMachineName)}:[A-Za-z0-9._+#-]+\\1\\s*$`, 'i').test(line);
}

function isExternalReference(reference) {
  return /^(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i.test(reference);
}

function escapeRegExp(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function formatValidationResult(result) {
  const lines = [`Generated child theme ${JSON.stringify(result.machineName)} contract`];
  for (const [section, label] of Object.entries(SECTION_LABELS)) {
    if (result.generationOnly && section !== 'generation') {
      lines.push(`SKIP ${label} (generation did not complete)`);
      continue;
    }
    if (section === 'build' && !result.checkBuiltAssets) {
      lines.push('SKIP build (checked after npm run build)');
      continue;
    }
    const sectionErrors = result.errors.filter((error) => error.section === section);
    if (sectionErrors.length === 0) {
      lines.push(`PASS ${label}`);
    }
    else {
      lines.push(`FAIL ${label}`);
      lines.push(...sectionErrors.map((error) => `  - ${error.message}`));
    }
  }
  return lines.join('\n');
}

function runCli(argv = process.argv.slice(2)) {
  const checkBuiltAssets = argv[0] === '--check-built-assets';
  const values = checkBuiltAssets ? argv.slice(1) : argv;
  if (values.length !== 5) {
    console.error('Usage: generated-theme-contract.cjs [--check-built-assets] <theme-dir> <machine-name> <display-name> <description> <whisk-source-dir>');
    return 2;
  }

  try {
    const result = validateGeneratedTheme({
      themeDir: path.resolve(values[0]),
      machineName: values[1],
      displayName: values[2],
      description: values[3],
      sourceDir: path.resolve(values[4]),
      checkBuiltAssets,
    });
    const output = formatValidationResult(result);
    (result.errors.length > 0 ? console.error : console.log)(output);
    return result.errors.length > 0 ? 1 : 0;
  }
  catch (error) {
    console.error(`Generated child theme contract could not run: ${error.message}`);
    return 1;
  }
}

if (require.main === module) {
  process.exitCode = runCli();
}

module.exports = {
  DEFAULT_SOURCE_DIR,
  formatValidationResult,
  loadSourceContract,
  runCli,
  validateGeneratedTheme,
};
