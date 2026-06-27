#!/usr/bin/env node

const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const repoRoot = path.resolve(__dirname, '../..');
const args = new Set(process.argv.slice(2));
const expectedProjectLicense = 'GPL-2.0-or-later';

// release:check is both a local release guard and a CI sanity check. Static
// checks always run; smoke checks build disposable Drupal projects unless the
// caller explicitly skips them.
const options = {
  drupalVersion: process.env.RELEASE_CHECK_DRUPAL_VERSION || null,
  skipSmoke: args.has('--skip-smoke'),
  workDir: process.env.RELEASE_CHECK_WORKDIR || fs.mkdtempSync(path.join(os.tmpdir(), 'emulsify-release-check-')),
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

function listFilesRecursive(relativePath, predicate) {
  const absolutePath = path.join(repoRoot, relativePath);
  if (!fs.existsSync(absolutePath)) {
    return [];
  }

  const files = [];
  for (const entry of fs.readdirSync(absolutePath, { withFileTypes: true })) {
    const childPath = path.join(relativePath, entry.name);
    if (entry.isDirectory()) {
      files.push(...listFilesRecursive(childPath, predicate));
    }
    else if (predicate(childPath)) {
      files.push(childPath);
    }
  }

  return files;
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

function extractSupportedDrupalTestLines(constraint) {
  const lines = String(constraint)
    .split('||')
    .map((segment) => segment.trim())
    .map((segment) => {
      const match = segment.match(/\^(\d+)(?:\.(\d+))?/);
      if (!match) {
        return null;
      }

      return match[2] ? `${match[1]}.${match[2]}.*` : `${match[1]}.*`;
    })
    .filter(Boolean);

  return [...new Set(lines)];
}

function mapDrupalLineToSmokeTarget(line) {
  if (line === '12.*') {
    // Until Drupal 12 has tagged beta/stable recommended-project releases,
    // dev-main is the only useful forward-compatibility smoke target.
    return 'dev-main';
  }

  return line;
}

function isStableSmokeTarget(target) {
  return !String(target).startsWith('dev-');
}

function chooseDefaultSmokeTarget(smokeTargets, fallbackVersion) {
  // Local release checks should block on the newest tagged/stable Drupal line.
  // Forward-looking dev branches remain opt-in locally because upstream core
  // churn can fail independently of this theme.
  const stableTargets = smokeTargets.filter(isStableSmokeTarget);
  return stableTargets[stableTargets.length - 1] || smokeTargets[0] || `${fallbackVersion}.*`;
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
  // Keep smoke steps isolated in the summary: one failure should not prevent
  // later fixture copies from proving unrelated release surfaces.
  const result = spawnSync(command, argsList, {
    cwd,
    encoding: 'utf8',
    env: { ...process.env, ...(options.env || {}) },
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

function runCapturedCommand(command, argsList, cwd) {
  return spawnSync(command, argsList, {
    cwd,
    encoding: 'utf8',
  });
}

function extractJsonObjectSegment(text, key) {
  // JSON.parse cannot report duplicate keys because later keys overwrite
  // earlier ones. This scanner extracts the raw object text so duplicate script
  // keys can be detected before parsing changes the shape.
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

function ensurePreferredReleaseLanguage(label, text) {
  ensure(!/Vite Build|Webpack Build/.test(text), `${label} should not use title-case build workflow phrases.`);
  ensure(!/subtheme|sub-theme/i.test(text), `${label} should use child theme language.`);
}

function ensureNoStaleReleaseLanguage(label, text) {
  ensure(!/\bwebpack\b/i.test(text), `${label} should use Vite language instead of Webpack.`);
  ensure(!/\bsubtheme|sub-theme\b/i.test(text), `${label} should use child theme language.`);
  ensure(!/\binherits?\s+(?:from\s+)?`?stable9`?/i.test(text), `${label} should not describe stable9 as an inherited parent theme.`);
  ensure(!/\bstable9\s+(?:parent|base)\s+theme/i.test(text), `${label} should not describe stable9 as the parent or base theme.`);
}

function extractYamlValue(contents, key) {
  const match = contents.match(new RegExp(`^${key}:\\s*(.+)$`, 'm'));
  ensure(match, `Unable to find ${key}.`);
  return match[1].trim().replace(/^['"]|['"]$/g, '');
}

function yamlTopLevelListContains(contents, section, value) {
  const lines = contents.split(/\r?\n/);
  let inSection = false;

  for (const line of lines) {
    if (/^[A-Za-z0-9_-]+:\s*$/.test(line)) {
      inSection = line.startsWith(`${section}:`);
      continue;
    }

    if (!inSection) {
      continue;
    }

    const listItem = line.trim().match(/^-\s*(.+)$/);
    if (!listItem) {
      continue;
    }

    if (listItem[1].replace(/^['"]|['"]$/g, '') === value) {
      return true;
    }
  }

  return false;
}

function extractYamlMappingKeys(contents, section, relativePath) {
  const lines = contents.split(/\r?\n/);
  const keys = [];
  let inSection = false;
  let foundSection = false;

  for (const line of lines) {
    if (/^\s*$/.test(line) || /^\s*#/.test(line)) {
      continue;
    }

    const topLevelMatch = line.match(/^([A-Za-z0-9_.-]+):(?:\s.*)?$/);
    if (topLevelMatch) {
      inSection = topLevelMatch[1] === section;
      foundSection = foundSection || inSection;
      continue;
    }

    if (!inSection) {
      continue;
    }

    const keyMatch = line.match(/^ {2}([A-Za-z0-9_.-]+):(?:\s.*)?$/);
    if (keyMatch) {
      keys.push(keyMatch[1]);
    }
  }

  ensure(foundSection, `Unable to find ${section} in ${relativePath}.`);
  ensure(keys.length > 0, `${relativePath} ${section} must declare at least one key.`);
  return keys;
}

function extractYamlDependencyConstraint(contents, packageName) {
  const escaped = packageName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const match = contents.match(new RegExp(`^\\s*-\\s*'drupal:${escaped} \\(([^)]+)\\)'\\s*$`, 'm'));
  ensure(match, `Unable to find drupal:${packageName} dependency.`);
  return match[1].trim();
}

const FAVICON_SETTING_PREFIX = 'favicon_';

function uniqueSorted(keys) {
  return [...new Set(keys)].sort();
}

function filterFaviconSettingKeys(keys) {
  return uniqueSorted(keys.filter((key) => key.startsWith(FAVICON_SETTING_PREFIX)));
}

function extractFaviconSchemaKeys(relativePath, schemaName) {
  const lines = readFile(relativePath).split(/\r?\n/);
  const keys = [];
  let foundSchema = false;
  let inSchema = false;
  let inMapping = false;

  for (const line of lines) {
    const topLevelMatch = line.match(/^([A-Za-z0-9_.-]+):\s*$/);
    if (topLevelMatch) {
      inSchema = topLevelMatch[1] === schemaName;
      foundSchema = foundSchema || inSchema;
      inMapping = false;
      continue;
    }

    if (!inSchema) {
      continue;
    }

    if (/^  mapping:\s*$/.test(line)) {
      inMapping = true;
      continue;
    }

    if (!inMapping) {
      continue;
    }

    const keyMatch = line.match(/^ {4}([a-z0-9_]+):\s*$/);
    if (keyMatch && keyMatch[1].startsWith(FAVICON_SETTING_PREFIX)) {
      keys.push(keyMatch[1]);
    }
  }

  ensure(foundSchema, `Unable to find ${schemaName} in ${relativePath}.`);
  ensure(inMapping || keys.length > 0, `Unable to find ${schemaName}.mapping in ${relativePath}.`);
  return uniqueSorted(keys);
}

function extractFaviconInstallKeys(relativePath) {
  return filterFaviconSettingKeys([...readFile(relativePath).matchAll(/^([a-z0-9_]+):/gm)].map((match) => match[1]));
}

function extractFaviconDefaultKeys(relativePath) {
  const contents = readFile(relativePath);
  const block = contents.match(/public const DEFAULTS = \[(.*?)\n  \];/s);
  ensure(block, 'Unable to find FaviconSettings::DEFAULTS.');
  const keys = [...block[1].matchAll(/'([^']+)'\s*=>/g)].map((match) => match[1]);
  const nonFaviconKeys = keys.filter((key) => !key.startsWith(FAVICON_SETTING_PREFIX));
  ensure(nonFaviconKeys.length === 0, `FaviconSettings::DEFAULTS should contain only ${FAVICON_SETTING_PREFIX} keys. Found: ${nonFaviconKeys.join(', ')}.`);
  return uniqueSorted(keys);
}

function keyDifference(expected, actual) {
  return expected.filter((key) => !actual.includes(key));
}

function formatKeys(keys) {
  return keys.length ? keys.join(', ') : '(none)';
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function stripTwigComments(contents) {
  return contents.replace(/\{#[\s\S]*?#\}/g, '');
}

function twigOutputReferencesPageRegion(template, region) {
  const outputTags = stripTwigComments(template).match(/\{\{[\s\S]*?\}\}/g) || [];
  const escapedRegion = escapeRegExp(region);
  const regionReferencePatterns = [
    new RegExp(`\\bpage\\s*\\.\\s*${escapedRegion}\\b`),
    new RegExp(`\\bpage\\s*\\[\\s*['"]${escapedRegion}['"]\\s*\\]`),
    new RegExp(`\\battribute\\s*\\(\\s*page\\s*,\\s*['"]${escapedRegion}['"]\\s*\\)`),
  ];

  return outputTags.some((outputTag) => regionReferencePatterns.some((pattern) => pattern.test(outputTag)));
}

function compareFaviconSettingKeySets(label, keySets) {
  const issues = [];
  const { defaults, install, schema } = keySets;

  const installMissingDefaults = keyDifference(defaults.keys, install.keys);
  if (installMissingDefaults.length > 0) {
    issues.push(`${install.label} is missing defaults from ${defaults.label}: ${formatKeys(installMissingDefaults)}.`);
  }

  const installExtraDefaults = keyDifference(install.keys, defaults.keys);
  if (installExtraDefaults.length > 0) {
    issues.push(`${install.label} has extra favicon keys not in ${defaults.label}: ${formatKeys(installExtraDefaults)}.`);
  }

  const schemaMissingDefaults = keyDifference(defaults.keys, schema.keys);
  if (schemaMissingDefaults.length > 0) {
    issues.push(`${schema.label} is missing defaults from ${defaults.label}: ${formatKeys(schemaMissingDefaults)}.`);
  }

  const schemaMissingInstall = keyDifference(install.keys, schema.keys);
  if (schemaMissingInstall.length > 0) {
    issues.push(`${schema.label} is missing install config keys from ${install.label}: ${formatKeys(schemaMissingInstall)}.`);
  }

  const schemaExtraDefaults = keyDifference(schema.keys, defaults.keys);
  if (schemaExtraDefaults.length > 0) {
    issues.push(`${schema.label} has extra favicon keys not in ${defaults.label}: ${formatKeys(schemaExtraDefaults)}.`);
  }

  const schemaExtraInstall = keyDifference(schema.keys, install.keys);
  if (schemaExtraInstall.length > 0) {
    issues.push(`${schema.label} has extra favicon keys not in ${install.label}: ${formatKeys(schemaExtraInstall)}.`);
  }

  ensure(issues.length === 0, [
    `Favicon settings key drift detected for ${label}.`,
    ...issues,
  ].join('\n'));
}

function ensureDeclaredRegionsRender({ metadataPath, metadataContents, templatePath }) {
  const declaredRegions = extractYamlMappingKeys(metadataContents, 'regions', metadataPath);
  const template = readFile(templatePath);
  const missingRegions = declaredRegions.filter((region) => !twigOutputReferencesPageRegion(template, region));

  ensure(missingRegions.length === 0, [
    'Declared Drupal theme regions must be rendered in their page template.',
    ...missingRegions.map((region) => `${region} is declared in ${metadataPath} but is not rendered in ${templatePath}. Add {{ page.${region} }} to the page template or remove the region declaration.`),
  ].join('\n'));

  return declaredRegions;
}

function copyDirectory(source, destination) {
  fs.rmSync(destination, { force: true, recursive: true });
  fs.cpSync(source, destination, { recursive: true });
}

const MIGRATED_THEME_HOOK_FILES = [
  ['includes/field.inc', 'src/Hook/FieldHooks.php', ['preprocess_field', 'theme_suggestions_field_alter']],
  ['includes/form.inc', 'src/Hook/FormHooks.php', ['theme_suggestions_form_alter']],
  ['includes/layout.inc', 'src/Hook/LayoutHooks.php', ['preprocess_layout']],
  ['includes/paragraphs.inc', 'src/Hook/ParagraphHooks.php', ['preprocess_paragraph']],
  ['includes/user.inc', 'src/Hook/UserHooks.php', ['theme_suggestions_user_alter']],
  [
    'includes/views.inc',
    'src/Hook/ViewsHooks.php',
    [
      'preprocess_views_view',
      'theme_suggestions_views_view_alter',
      'theme_suggestions_views_view_unformatted_alter',
      'theme_suggestions_views_mini_pager_alter',
    ],
  ],
];

const DEPENDENCY_HEAVY_FAVICON_FORM_FILES = [
  'src/Favicon/FaviconSettingsForm.php',
];

function getStrictTypeClassFiles() {
  return [
    ...listFilesRecursive('src/Favicon', (filePath) => filePath.endsWith('.php')),
    ...listFilesRecursive('src/Hook', (filePath) => filePath.endsWith('.php')),
  ].sort();
}

function getStrictTypeScriptFiles() {
  return [
    ...listFilesRecursive('.github/scripts', (filePath) => filePath.endsWith('.php')),
  ].sort();
}

function ensureStrictTypeHeaders() {
  for (const phpFilePath of getStrictTypeClassFiles()) {
    ensure(readFile(phpFilePath).startsWith('<?php\n\ndeclare(strict_types=1);\n'), `${phpFilePath} must declare strict types immediately after <?php.`);
  }
  for (const phpFilePath of getStrictTypeScriptFiles()) {
    ensure(readFile(phpFilePath).includes('\ndeclare(strict_types=1);\n'), `${phpFilePath} must declare strict types.`);
  }
}

function ensureHookAttributeMigration(themeEntrypoint) {
  for (const [legacyInclude] of MIGRATED_THEME_HOOK_FILES) {
    ensure(!fs.existsSync(path.join(repoRoot, legacyInclude)), `${legacyInclude} should not be present in 7.x.`);
  }

  ensure(!/(require|include)_once\s+/.test(themeEntrypoint), 'emulsify.theme should not include legacy hook files.');
  ensure(!/function\s+emulsify_[A-Za-z0-9_]+\s*\(/.test(themeEntrypoint), 'emulsify.theme should not contain procedural hook wrappers.');

  for (const [, target, hooks] of MIGRATED_THEME_HOOK_FILES) {
    const hookClass = readFile(target);
    for (const hook of hooks) {
      ensure(hookClass.includes(`#[Hook('${hook}')]`), `${target} must implement ${hook} with a Hook attribute.`);
    }
  }
}

function ensureDependencyHeavyFaviconFormAutowiring() {
  for (const formFilePath of DEPENDENCY_HEAVY_FAVICON_FORM_FILES) {
    const formClass = readFile(formFilePath);
    ensure(formClass.includes('use Symfony\\Component\\DependencyInjection\\Attribute\\Autowire;'), `${formFilePath} must import Symfony Autowire for constructor disambiguation.`);
    ensure(formClass.includes("#[Autowire(service: 'lock')]\n    LockBackendInterface $lock"), `${formFilePath} must explicitly autowire the request lock service.`);
  }
}

function ensureFaviconSettingsFormDelegation() {
  const themeSettingsHooks = readFile('src/Hook/ThemeSettingsHooks.php');
  const faviconSettingsForm = readFile('src/Favicon/FaviconSettingsForm.php');

  ensure(themeSettingsHooks.includes('use Drupal\\Core\\DependencyInjection\\ClassResolverInterface;'), 'ThemeSettingsHooks must inject the class resolver for non-hook form delegation.');
  ensure(themeSettingsHooks.includes('getInstanceFromDefinition(FaviconSettingsForm::class)'), 'ThemeSettingsHooks must resolve FaviconSettingsForm through the class resolver.');
  ensure(!themeSettingsHooks.includes('private readonly FaviconSettingsForm $'), 'ThemeSettingsHooks must not constructor-inject a non-service FaviconSettingsForm.');
  ensure(faviconSettingsForm.includes('implements ContainerInjectionInterface'), 'FaviconSettingsForm must be container-creatable for Form API callbacks.');
  ensure(faviconSettingsForm.includes('public static function create(ContainerInterface $container): self'), 'FaviconSettingsForm must expose create() for class resolver construction.');
  ensure(faviconSettingsForm.includes("$container->get('lock')"), 'FaviconSettingsForm::create() must resolve the lock service explicitly.');
}

function ensureWhiskPackageScriptTargets(whiskPackage) {
  const scripts = whiskPackage.scripts || {};
  const scriptText = Object.values(scripts).join('\n');

  if (scripts.test && scripts.test.includes('./config/jest.config.js')) {
    ensure(fs.existsSync(path.join(repoRoot, 'whisk/config/jest.config.js')), 'whisk/package.json test script references missing whisk/config/jest.config.js.');
  }

  if (scripts.twatch && scripts.twatch.includes('./config/jest.config.js')) {
    ensure(fs.existsSync(path.join(repoRoot, 'whisk/config/jest.config.js')), 'whisk/package.json twatch script references missing whisk/config/jest.config.js.');
  }

  ensure(!scripts['tokens:transform'], 'Whisk should not ship a default tokens:transform script because design-token tooling is project-specific.');
  ensure(!scripts['tokens:build'], 'Whisk should not ship a default tokens:build script because design-token tooling is project-specific.');
  ensure(!scripts['style-dictionary:build'], 'Whisk should not assume Style Dictionary is installed.');
  ensure(!/\bstyle-dictionary\b/.test(scriptText), 'Whisk scripts should not assume Style Dictionary is installed.');
  ensure(!/\btoken-transformer\b/.test(scriptText), 'Whisk token scripts should not reference token-transformer unless it is declared as a direct dependency.');
  ensure(!/\bstorybook-to-ghpages\b/.test(scriptText), 'Whisk scripts should not reference storybook-to-ghpages unless it is declared as a direct dependency.');
}

function ensureNoRuntimeFaviconGeneration() {
  const faviconHooks = readFile('src/Hook/FaviconHooks.php');
  ensure(!faviconHooks.includes('generatePackage('), 'FaviconHooks must not generate favicon packages during page requests.');
  ensure(!faviconHooks.includes('buildPackageStatus('), 'FaviconHooks must not resolve generation status during page requests.');
  ensure(faviconHooks.includes('packageExists('), 'FaviconHooks should only attach existing generated favicon packages.');
}

function getReleasePluginOptions(releaseConfig, pluginName) {
  const plugin = releaseConfig.plugins.find((candidate) => {
    if (Array.isArray(candidate)) {
      return candidate[0] === pluginName;
    }

    return candidate === pluginName;
  });

  ensure(plugin, `release.config.js must include ${pluginName}.`);
  return Array.isArray(plugin) ? plugin[1] || {} : {};
}

function ensureBreakingHeaderParser(label, parserOpts) {
  ensure(parserOpts && parserOpts.breakingHeaderPattern instanceof RegExp, `${label} must define breakingHeaderPattern.`);
  ensure(parserOpts.breakingHeaderPattern.test('feat!: drop legacy compatibility'), `${label} must treat type!: headers as breaking changes.`);
  ensure(parserOpts.breakingHeaderPattern.test('refactor(theme)!: drop legacy compatibility'), `${label} must treat type(scope)!: headers as breaking changes.`);
  ensure(parserOpts.noteKeywords.includes('BREAKING CHANGE'), `${label} must preserve BREAKING CHANGE footer parsing.`);
  ensure(parserOpts.noteKeywords.includes('BREAKING CHANGES'), `${label} must preserve BREAKING CHANGES footer parsing.`);
  ensure(parserOpts.noteKeywords.includes('BREAKING'), `${label} must preserve BREAKING footer parsing.`);
}

function runStaticChecks() {
  const rootPackage = readJson('package.json');
  const rootPackageLock = readJson('package-lock.json');
  const whiskPackage = readJson('whisk/package.json');
  const composer = readJson('composer.json');
  const releaseConfigSource = readFile('release.config.js');
  const releaseConfig = require(path.join(repoRoot, 'release.config.js'));
  const licenseText = readFile('LICENSE');
  const readme = readFile('README.md');
  const releaseReadinessDoc = readFile('docs/release-readiness.md');
  const sisterProjectParityDoc = readFile('docs/sister-project-parity.md');
  const themeEntrypoint = readFile('emulsify.theme');
  const faviconGenerationDoc = readFile('docs/favicon-generation.md');
  const designTokenIntegrationDoc = readFile('docs/design-token-integration.md');
  const setupFixture = readFile('.github/scripts/setup-fixture-site.sh');
  const renderReferencePages = readFile('.github/scripts/render-reference-pages.sh');
  const emulsifyInfo = readFile('emulsify.info.yml');
  const emulsifyBreakpoints = readFile('emulsify.breakpoints.yml');
  const whiskInfo = readFile('whisk/whisk.info.yml');
  const whiskBreakpoints = readFile('whisk/whisk.breakpoints.yml');
  const whiskInfoStarter = readFile('whisk/whisk.info.emulsify.yml');
  const whiskStarterkit = readFile('whisk/whisk.starterkit.yml');
  const starterkitSmoke = readFile('.github/scripts/starterkit-smoke.sh');
  const themeReadinessWorkflow = readFile('.github/workflows/theme-readiness.yml');
  const semanticReleaseWorkflow = readFile('.github/workflows/semantic-release.yml');
  const coreConstraint = composer.require['drupal/core'];
  const minCoreVersion = normalizeConstraintVersion(coreConstraint);
  const supportedDrupalLines = extractSupportedDrupalTestLines(coreConstraint);
  const supportedDrupalSmokeTargets = supportedDrupalLines.map(mapDrupalLineToSmokeTarget);

  if (!options.drupalVersion) {
    options.drupalVersion = chooseDefaultSmokeTarget(supportedDrupalSmokeTargets, minCoreVersion);
  }

  runStaticCheck('Composer constraints', () => {
    ensure(coreConstraint, 'composer.json must declare drupal/core.');
    ensure(supportedDrupalLines.length > 0, 'composer.json must expose at least one supported Drupal core test line.');
    ensure(composer.require['drupal/emulsify_tools'], 'composer.json must declare drupal/emulsify_tools.');
    ensure(extractYamlValue(emulsifyInfo, 'core_version_requirement') === coreConstraint, 'emulsify.info.yml must match composer drupal/core.');
    ensure(extractYamlValue(whiskInfo, 'core_version_requirement') === coreConstraint, 'whisk.info.yml must match composer drupal/core.');
    ensure(extractYamlValue(whiskInfoStarter, 'core_version_requirement') === coreConstraint, 'whisk.info.emulsify.yml must match composer drupal/core.');
    ensure(extractYamlDependencyConstraint(emulsifyInfo, 'emulsify_tools') === composer.require['drupal/emulsify_tools'], 'emulsify.info.yml must match the composer emulsify_tools constraint.');
    ensure(extractYamlDependencyConstraint(whiskInfo, 'emulsify_tools') === composer.require['drupal/emulsify_tools'], 'whisk.info.yml must match the composer emulsify_tools constraint.');
    ensure(extractYamlDependencyConstraint(whiskInfoStarter, 'emulsify_tools') === composer.require['drupal/emulsify_tools'], 'whisk.info.emulsify.yml must match the composer emulsify_tools constraint.');
    ensure(emulsifyBreakpoints.includes('emulsify.xsmall:'), 'emulsify.breakpoints.yml should use parent-theme emulsify.* breakpoint keys.');
    ensure(!emulsifyBreakpoints.includes('whisk.xsmall:'), 'emulsify.breakpoints.yml should not use whisk.* breakpoint keys.');
    ensure(whiskBreakpoints.includes('whisk.xsmall:'), 'whisk/whisk.breakpoints.yml should keep whisk.* keys for starterkit replacement.');
    for (const drupalTarget of supportedDrupalSmokeTargets) {
      ensure(themeReadinessWorkflow.includes(`'${drupalTarget}'`), `theme-readiness.yml should smoke test Drupal ${drupalTarget}.`);
    }
    ensure(themeReadinessWorkflow.includes("'8.4'"), 'theme-readiness.yml should run readiness smoke checks on PHP 8.4.');
    if (supportedDrupalSmokeTargets.includes('dev-main')) {
      ensure(themeReadinessWorkflow.includes("'8.5'"), 'theme-readiness.yml should run advisory Drupal dev-branch smoke checks on PHP 8.5.');
    }
    ensure(themeReadinessWorkflow.includes('pull_request:'), 'theme-readiness.yml should run on pull requests.');
    ensure(themeReadinessWorkflow.includes('schedule:'), 'theme-readiness.yml should run scheduled release-readiness coverage.');
    ensure(themeReadinessWorkflow.includes('composer validate --no-check-publish --strict'), 'theme-readiness.yml should strictly validate Composer metadata.');
    ensure(themeReadinessWorkflow.includes('npm ci --ignore-scripts'), 'theme-readiness.yml should install root npm dependencies from a clean lockfile.');
    ensure(themeReadinessWorkflow.includes('npm audit --omit=dev'), 'theme-readiness.yml should run the runtime npm audit policy.');
    ensure(/^        run: npm audit$/m.test(themeReadinessWorkflow), 'theme-readiness.yml should run the full npm audit while it is clean.');
    ensure(themeReadinessWorkflow.includes('npm run lint:php'), 'theme-readiness.yml should lint PHP files.');
    ensure(themeReadinessWorkflow.includes('npm run release:check -- --skip-smoke'), 'theme-readiness.yml should run the static release check before fixture smoke tests.');
    ensure(themeReadinessWorkflow.includes('actions/setup-node@v4'), 'theme-readiness.yml should install Node for generated child theme frontend smoke tests.');
    ensure(themeReadinessWorkflow.includes('node-version: 24'), 'theme-readiness.yml should use Node 24 for Whisk starter generated child theme frontend smoke tests.');
    ensure(themeReadinessWorkflow.includes('extensions: gd, imagick'), 'theme-readiness.yml should install GD and Imagick for favicon smoke tests.');
    ensure(themeReadinessWorkflow.includes('- 7.x'), 'theme-readiness.yml should run on pushes to 7.x while this release branch owns the workflow.');
    ensure(themeReadinessWorkflow.includes('- release-7'), 'theme-readiness.yml should run on pushes to release-7.');
    ensure(themeReadinessWorkflow.includes('github.event.pull_request.head.ref || github.ref_name'), 'theme-readiness.yml should group duplicate push/pull_request runs by head branch.');
    ensure(!themeReadinessWorkflow.includes('- 6.x'), 'theme-readiness.yml should not keep the retired 6.x release branch trigger.');
    ensure(setupFixture.includes('NodeType::create'), 'setup-fixture-site.sh should create the page node type when install profiles omit it.');
    return `Root and generated child theme metadata align to Drupal constraint lines ${supportedDrupalLines.join(', ')} via ${supportedDrupalSmokeTargets.join(', ')} smoke targets. Local smoke default: ${options.drupalVersion}.`;
  });

  runStaticCheck('Semantic release configuration', () => {
    const analyzerOptions = getReleasePluginOptions(releaseConfig, '@semantic-release/commit-analyzer');
    const notesOptions = getReleasePluginOptions(releaseConfig, '@semantic-release/release-notes-generator');

    ensure(releaseConfig.tagFormat === '${version}', 'release.config.js should emit non-prefixed SemVer tags.');
    ensure(Array.isArray(releaseConfig.branches), 'release.config.js branches must be an array.');
    ensure(releaseConfig.branches.length === 1 && releaseConfig.branches[0] === 'main', 'release.config.js should publish only from main.');
    ensure(analyzerOptions.preset === 'angular', '@semantic-release/commit-analyzer should use the angular preset.');
    ensure(notesOptions.preset === 'angular', '@semantic-release/release-notes-generator should use the angular preset.');
    ensureBreakingHeaderParser('@semantic-release/commit-analyzer parserOpts', analyzerOptions.parserOpts);
    ensureBreakingHeaderParser('@semantic-release/release-notes-generator parserOpts', notesOptions.parserOpts);
    ensure(semanticReleaseWorkflow.includes('branches:\n      - "main"'), 'semantic-release.yml should run on pushes to main.');
    ensure(semanticReleaseWorkflow.includes('fetch-depth: 0'), 'semantic-release.yml should fetch full history and tags before publishing.');
    ensure(semanticReleaseWorkflow.includes('contents: write'), 'semantic-release.yml should grant release permissions explicitly.');
    ensure(semanticReleaseWorkflow.includes('release-readiness:'), 'semantic-release.yml should run a release-readiness job before publishing.');
    ensure(semanticReleaseWorkflow.includes('needs: release-readiness'), 'semantic-release.yml release job should wait for release readiness.');
    ensure(semanticReleaseWorkflow.includes('composer validate --no-check-publish --strict'), 'semantic-release.yml should strictly validate Composer metadata before publishing.');
    ensure(semanticReleaseWorkflow.includes('npm ci --ignore-scripts'), 'semantic-release.yml should install npm dependencies from a clean lockfile before publishing.');
    ensure(semanticReleaseWorkflow.includes('npm audit --omit=dev'), 'semantic-release.yml should run the runtime npm audit before publishing.');
    ensure(/^        run: npm audit$/m.test(semanticReleaseWorkflow), 'semantic-release.yml should run the full npm audit while it is clean.');
    ensure(semanticReleaseWorkflow.includes('npm run lint:php'), 'semantic-release.yml should lint PHP files before publishing.');
    ensure(semanticReleaseWorkflow.includes('npm run release:check -- --skip-smoke'), 'semantic-release.yml should run static release checks before full smoke coverage.');
    ensure(/^        run: npm run release:check$/m.test(semanticReleaseWorkflow), 'semantic-release.yml should run full release checks before publishing.');
    ensure(semanticReleaseWorkflow.includes('extensions: gd, imagick'), 'semantic-release.yml should install GD and Imagick for favicon smoke coverage.');
    ensure(semanticReleaseWorkflow.includes('EMULSIFY_STARTERKIT_STORYBOOK_BUILD'), 'semantic-release.yml should enable release-only generated Storybook build coverage.');
    ensure(semanticReleaseWorkflow.includes('EMULSIFY_STARTERKIT_TEST'), 'semantic-release.yml should enable generated child theme test coverage in full release checks.');
    ensure(semanticReleaseWorkflow.includes('cycjimmy/semantic-release-action@v6'), 'semantic-release.yml should use a semantic-release action version that supports semantic-release 25.');
    ensure(semanticReleaseWorkflow.includes('semantic_version: 25.0.3'), 'semantic-release.yml should pin semantic-release 25.0.3 in CI.');
    ensure(semanticReleaseWorkflow.includes('id: semantic'), 'semantic-release.yml must expose semantic-release action outputs as steps.semantic.');
    ensure(!semanticReleaseWorkflow.includes('DRUPAL_ORG_SSH_KEY'), 'semantic-release.yml should leave Drupal.org syncing manual.');
    ensure(!semanticReleaseWorkflow.includes('DRUPAL_REPO_URL'), 'semantic-release.yml should leave Drupal.org syncing manual.');
    ensure(!semanticReleaseWorkflow.includes('drupal-org'), 'semantic-release.yml should leave Drupal.org syncing manual.');
    return 'Semantic release runs from main, fetches full history, emits non-prefixed SemVer tags, and treats Conventional Commit ! headers as major releases.';
  });

  runStaticCheck('Package metadata', () => {
    ensure(rootPackage.name === 'emulsify-drupal', 'package.json name should remain emulsify-drupal.');
    ensure(semver(rootPackage.version), 'package.json version must be a valid semver string.');
    ensure(rootPackage.description, 'package.json description is required.');
    ensurePreferredReleaseLanguage('package.json description', rootPackage.description);
    ensure(rootPackage.description.includes('Vite-based build workflow'), 'package.json description should mention the Vite-based build workflow.');
    ensure(rootPackage.description.includes('Emulsify Core 4'), 'package.json description should mention Emulsify Core 4.');
    ensure(rootPackage.license === expectedProjectLicense, `package.json license must be ${expectedProjectLicense}.`);
    ensure(rootPackageLock.packages && rootPackageLock.packages[''] && rootPackageLock.packages[''].license === expectedProjectLicense, `package-lock.json root package license must be ${expectedProjectLicense}. Run npm install --package-lock-only --ignore-scripts after license metadata changes.`);
    ensure(rootPackage.engines && rootPackage.engines.node === '>=24.10', 'package.json engines.node should match the Node line required by release tooling.');
    ensure(rootPackage.repository && rootPackage.repository.url, 'package.json repository.url is required.');
    ensure(rootPackage.bugs && rootPackage.bugs.url, 'package.json bugs.url is required.');
    ensure(rootPackage.homepage, 'package.json homepage is required.');
    ensure(!rootPackage.dependencies || !rootPackage.dependencies['graceful-fs'], 'package.json should not declare unused graceful-fs as a direct dependency.');
    ensure(rootPackage.scripts && rootPackage.scripts.prepare, 'package.json prepare script is required.');
    ensure(rootPackage.scripts['docs:check-commands'], 'package.json should expose a docs:check-commands script.');
    ensure(rootPackage.scripts['release:check'], 'package.json should expose a release:check script.');
    ensure(rootPackage.devDependencies && rootPackage.devDependencies['@semantic-release/npm'], 'package.json should declare @semantic-release/npm directly because release.config.js loads it as a plugin.');
    ensure(whiskPackage.name === 'whisk', 'whisk/package.json name should remain whisk.');
    ensure(semver(whiskPackage.version), 'whisk/package.json version must be a valid semver string.');
    ensure(whiskPackage.description, 'whisk/package.json description is required.');
    ensurePreferredReleaseLanguage('whisk/package.json description', whiskPackage.description);
    ensure(whiskPackage.description.includes('Vite-based build workflow'), 'whisk/package.json description should mention the Vite-based build workflow.');
    ensure(whiskPackage.description.includes('Emulsify Core 4'), 'whisk/package.json description should mention Emulsify Core 4.');
    ensure(whiskPackage.license === expectedProjectLicense, `whisk/package.json license must be ${expectedProjectLicense}.`);
    ensure(whiskPackage.engines && whiskPackage.engines.node, 'whisk/package.json engines.node is required.');
    ensure(whiskPackage.type === 'module', 'whisk/package.json must remain an ES module package.');
    ensure(whiskPackage.dependencies && whiskPackage.dependencies['@emulsify/core'], 'whisk/package.json must declare @emulsify/core.');
    ensure(whiskPackage.dependencies['@emulsify/core'].startsWith('^4.'), 'whisk/package.json should target Emulsify Core 4.');
    ensure(whiskPackage.scripts.build.includes('config/vite/vite.config.js'), 'whisk/package.json build script should use the Emulsify Core Vite config.');
    ensureWhiskPackageScriptTargets(whiskPackage);
    ensure(composer.description, 'composer.json description is required.');
    ensurePreferredReleaseLanguage('composer.json description', composer.description);
    ensure(composer.description.includes('Vite-based build workflow'), 'composer.json description should mention the Vite-based build workflow.');
    ensure(composer.description.includes('child themes'), 'composer.json description should mention child themes.');
    ensure(composer.homepage === 'https://www.emulsify.info', 'composer.json homepage should use the canonical HTTPS Emulsify URL.');
    ensure(composer.license === expectedProjectLicense, `composer.json license must be ${expectedProjectLicense}.`);
    ensure(licenseText.includes(`SPDX-License-Identifier: ${expectedProjectLicense}`), `LICENSE must include SPDX-License-Identifier: ${expectedProjectLicense}.`);
    ensure(licenseText.includes('Version 2, June 1991'), 'LICENSE should include the GNU GPL version 2 text.');
    ensure(licenseText.includes('either version 2 of the License, or'), 'LICENSE should preserve the GPL v2-or-later permission notice.');
    ensure(!licenseText.includes('Version 3, 29 June 2007'), 'LICENSE should not contain the GNU GPL version 3-only text.');
    ensure(readme.includes(expectedProjectLicense), `README.md should document ${expectedProjectLicense}.`);
    ensure(releaseReadinessDoc.includes(expectedProjectLicense), `docs/release-readiness.md should document ${expectedProjectLicense}.`);
    ensure(!releaseReadinessDoc.includes('unresolved Composer/Drupal.org license metadata question'), 'docs/release-readiness.md should not keep the old unresolved license blocker after metadata alignment.');
    return `Validated root package ${rootPackage.version}, whisk package ${whiskPackage.version}, and project license ${expectedProjectLicense}.`;
  });

  runStaticCheck('Duplicate package scripts', () => {
    const duplicates = [
      ...findDuplicatePackageScripts('package.json').map((key) => `package.json:${key}`),
      ...findDuplicatePackageScripts('whisk/package.json').map((key) => `whisk/package.json:${key}`),
    ];
    ensure(duplicates.length === 0, `Duplicate script keys found: ${duplicates.join(', ')}.`);
    return 'No duplicate script keys were found in package metadata.';
  });

  runStaticCheck('Documented npm commands', () => {
    const result = runCapturedCommand(
      process.execPath,
      [path.join(repoRoot, '.github/scripts/docs-command-check.cjs')],
      repoRoot,
    );
    const output = [result.stdout, result.stderr].filter(Boolean).join('\n').trim();
    ensure(result.status === 0, output || 'Documented npm command validation failed.');
    return output.split(/\r?\n/)[0] || 'Documented npm commands match package scripts.';
  });

  runStaticCheck('README version references', () => {
    ensure(readme.includes(`Drupal ${minCoreVersion}`), `README.md should mention Drupal ${minCoreVersion}.`);
    if (supportedDrupalLines.some((line) => line.startsWith('12'))) {
      ensure(readme.includes('Drupal 12 forward compatibility'), 'README.md should describe Drupal 12 as forward-compatible.');
      ensure(readme.includes('development branch coverage is experimental'), 'README.md should describe Drupal core development branch coverage as experimental.');
    }
    ensure(readme.includes(`${rootPackage.version.split('.')[0]}.x series`), `README.md should mention the ${rootPackage.version.split('.')[0]}.x series.`);
    ensure(readme.includes('docs/design-token-integration.md'), 'README.md should link to the optional design-token integration example.');
    ensure(designTokenIntegrationDoc.toLowerCase().includes('optional'), 'docs/design-token-integration.md should describe design-token tooling as optional.');
    return 'README.md matches the Drupal core compatibility messaging and current major release line.';
  });

  runStaticCheck('Release language consistency', () => {
    for (const [label, text] of [
      ['README.md', readme],
      ['docs/release-readiness.md', releaseReadinessDoc],
      ['.github/workflows/theme-readiness.yml', themeReadinessWorkflow],
      ['.github/workflows/semantic-release.yml', semanticReleaseWorkflow],
      ['release.config.js', releaseConfigSource],
    ]) {
      ensureNoStaleReleaseLanguage(label, text);
    }

    for (const expectedReadmeText of [
      'Whisk starter source',
      'generated child theme',
      'parent theme',
      'Emulsify Core 4',
      'Vite build workflow',
      'Storybook',
      'non-prefixed SemVer tags',
    ]) {
      ensure(readme.includes(expectedReadmeText), `README.md should use normalized release language for ${expectedReadmeText}.`);
    }

    for (const expectedReadinessText of [
      'Whisk starter remains generation-only',
      'generated child themes keep `emulsify` as their runtime parent theme',
      'Whisk-starter generated child-theme build/test smoke',
      'non-prefixed SemVer tags',
    ]) {
      ensure(releaseReadinessDoc.includes(expectedReadinessText), `docs/release-readiness.md should use normalized release language for ${expectedReadinessText}.`);
    }

    ensure(!releaseReadinessDoc.includes('generated starterkit'), 'docs/release-readiness.md should not use ambiguous generated starterkit language.');
    ensure(!releaseReadinessDoc.includes('generated themes retain'), 'docs/release-readiness.md should say generated child themes retain project.emulsify.json.');
    ensure(!/\bgenerated themes?\b/i.test(readme), 'README.md should use generated child theme language.');
    ensure(!/\bgenerated themes?\b/i.test(releaseReadinessDoc), 'docs/release-readiness.md should use generated child theme language.');
    ensure(!themeReadinessWorkflow.includes("'Starterkit:"), 'theme-readiness.yml step names should identify the Whisk starter or generated child theme, not bare Starterkit.');
    ensure(!themeReadinessWorkflow.includes('Starterkit Storybook and Accessibility'), 'theme-readiness.yml extended job should use generated child theme language.');
    ensure(themeReadinessWorkflow.includes("'Whisk starter: generate child theme'"), 'theme-readiness.yml should name the Whisk starter generation step clearly.');
    ensure(themeReadinessWorkflow.includes("'Generated child theme: build frontend assets'"), 'theme-readiness.yml should name the generated child theme frontend build step clearly.');
    ensure(themeReadinessWorkflow.includes('Generated Child Theme Storybook and Accessibility'), 'theme-readiness.yml should name scheduled/manual Storybook and a11y coverage with generated child theme language.');
    ensure(semanticReleaseWorkflow.includes('Run full release check with Drupal fixture smoke coverage'), 'semantic-release.yml should name the full release check as Drupal fixture smoke coverage.');
    return 'Release docs and workflow labels use normalized Emulsify Drupal readiness language.';
  });

  runStaticCheck('Sister project parity contract', () => {
    ensure(readme.includes('docs/sister-project-parity.md'), 'README.md should link to docs/sister-project-parity.md.');
    ensure(releaseReadinessDoc.includes('sister-project parity contract'), 'docs/release-readiness.md should include the sister-project parity contract in release checks.');
    for (const expectedText of [
      'Emulsify WordPress',
      'parent theme owns the reusable CMS runtime',
      'generated child theme owns the project implementation',
      'Whisk is the starter',
      'Emulsify Core 4',
      'Vite',
      'Storybook',
      'Twig',
      'Node.js 24',
      'Component source and generated assets have separate ownership',
      'Drupal Starterkit',
      'Emulsify Tools',
      'base theme: emulsify',
      '.info.yml',
      '.libraries.yml',
      'config/install',
      'config/schema',
      'Single Directory Components',
      '@components',
      'Drupal fixtures',
      'theme readiness',
    ]) {
      ensure(sisterProjectParityDoc.includes(expectedText), `docs/sister-project-parity.md should document ${expectedText}.`);
    }
    return 'README and parity contract document the shared Emulsify model and intentional Drupal differences.';
  });

  runStaticCheck('Parent theme independence', () => {
    ensure(!/^base theme:\s*stable9\s*$/m.test(emulsifyInfo), 'emulsify.info.yml should not depend on stable9.');
    ensure(readme.includes('no longer depends on `stable9`'), 'README.md should document the stable9 removal.');
    return 'Emulsify owns its template layer without a stable9 parent theme fallback.';
  });

  runStaticCheck('Theme region rendering', () => {
    const checkedRegions = [
      { metadataPath: 'emulsify.info.yml', metadataContents: emulsifyInfo, templatePath: 'templates/layout/page.html.twig' },
      { metadataPath: 'whisk/whisk.info.yml', metadataContents: whiskInfo, templatePath: 'whisk/templates/layout/page.html.twig' },
      { metadataPath: 'whisk/whisk.info.emulsify.yml', metadataContents: whiskInfoStarter, templatePath: 'whisk/templates/layout/page.html.twig' },
    ].map(ensureDeclaredRegionsRender);
    const smokeRegions = ['header', 'content_top', 'content', 'content_bottom', 'footer'];
    const smokeRegionsMatch = renderReferencePages.match(/^region_smoke_regions=\(([^)]+)\)$/m);
    ensure(smokeRegionsMatch, 'render-reference-pages.sh should declare region_smoke_regions for block placement smoke coverage.');
    const coveredSmokeRegions = smokeRegionsMatch[1].trim().split(/\s+/);

    for (const region of smokeRegions) {
      ensure(coveredSmokeRegions.includes(region), `render-reference-pages.sh should place and assert a region smoke marker for ${region}.`);
    }
    ensure(renderReferencePages.includes('assert_region_smoke_markers'), 'render-reference-pages.sh should fail when placed region smoke blocks do not render.');

    return `Verified ${checkedRegions.reduce((total, regions) => total + regions.length, 0)} declared region references across parent and Whisk page templates.`;
  });

  runStaticCheck('Hook attribute migration', () => {
    ensureHookAttributeMigration(themeEntrypoint);
    ensureDependencyHeavyFaviconFormAutowiring();
    ensureFaviconSettingsFormDelegation();
    return 'Legacy procedural hook includes are absent and migrated hooks are implemented with attributes.';
  });

  runStaticCheck('PHP strict typing', () => {
    ensureStrictTypeHeaders();
    return 'New 7.x PHP classes and smoke helpers declare strict types.';
  });

  runStaticCheck('Favicon settings API', () => {
    const faviconSettings = readFile('src/Favicon/FaviconSettings.php');
    ensure(!faviconSettings.includes('function getSourceSvg('), 'FaviconSettings should not keep the deprecated getSourceSvg() alias in 7.x.');
    ensure(!faviconSettings.includes('function hasExportableSource('), 'FaviconSettings should not keep the deprecated hasExportableSource() alias in 7.x.');
    ensure(!faviconSettings.includes('@todo Remove in Emulsify 8.x'), 'FaviconSettings should not introduce deprecated 7.x-only aliases.');
    return 'Favicon settings expose the 7.x portable source method names only.';
  });

  runStaticCheck('Favicon runtime behavior', () => {
    const faviconPackageGenerator = readFile('src/Favicon/FaviconPackageGenerator.php');
    const faviconThemeManager = readFile('src/Favicon/FaviconThemeManager.php');
    const faviconSettingsForm = readFile('src/Favicon/FaviconSettingsForm.php');
    const faviconHooks = readFile('src/Hook/FaviconHooks.php');
    ensureNoRuntimeFaviconGeneration();
    ensure(faviconPackageGenerator.includes("public const PACKAGE_BASE_DIRECTORY = 'public://favicon-package'"), 'FaviconPackageGenerator should centralize the package base directory.');
    ensure(faviconPackageGenerator.includes('public static function isManagedPackagePath'), 'FaviconPackageGenerator should expose a managed package path validator.');
    ensure(faviconThemeManager.includes('FaviconPackageGenerator::isManagedPackagePath($package_path, $theme_name)'), 'FaviconThemeManager reset should only delete managed package paths owned by the theme.');
    ensure(faviconHooks.includes('FaviconPackageGenerator::isManagedPackagePath'), 'FaviconHooks should only attach managed package paths owned by the active theme.');
    ensure(faviconSettingsForm.includes('persistSanitizedSourceFile'), 'FaviconSettingsForm should rewrite retained uploads with sanitized SVG markup.');
    ensure(readme.includes('Runtime page requests never generate favicon files'), 'README.md should document that page requests do not generate favicon files.');
    ensure(readme.includes('docs/favicon-generation.md'), 'README.md should link to the favicon generation lifecycle documentation.');
    for (const expectedText of [
      'GD',
      'Imagick',
      'public://favicon-package/<theme_name>/<package_hash>',
      'favicon.svg',
      'favicon.ico',
      'favicon-96x96.png',
      'apple-touch-icon.png',
      'web-app-manifest-192x192.png',
      'web-app-manifest-512x512.png',
      'web-app-manifest-512x512-maskable.png',
      'site.webmanifest',
      'metadata.json',
      'Normal page requests do not create, modify, or regenerate favicon files.',
    ]) {
      ensure(faviconGenerationDoc.includes(expectedText), `docs/favicon-generation.md should document ${expectedText}.`);
    }
    return 'Page attachments only attach existing favicon packages.';
  });

  runStaticCheck('Whisk starter generated child theme', () => {
    for (const requiredIgnore of ['/whisk.info.emulsify.yml', '/whisk.starterkit.yml']) {
      ensure(whiskStarterkit.includes(requiredIgnore), `whisk.starterkit.yml should ignore ${requiredIgnore}.`);
    }
    ensure(!whiskStarterkit.includes('/project.emulsify.json'), 'whisk.starterkit.yml should copy project.emulsify.json into generated child themes.');
    for (const requiredNoEdit of ['/config/emulsify-core/**', '/screenshot.png']) {
      ensure(yamlTopLevelListContains(whiskStarterkit, 'no_edit', requiredNoEdit), `whisk.starterkit.yml should not edit ${requiredNoEdit}.`);
    }
    ensure(yamlTopLevelListContains(whiskStarterkit, 'no_rename', '/config/emulsify-core/**'), 'whisk.starterkit.yml should not rename Emulsify Core config files.');
    ensure(whiskStarterkit.includes(`core_version_requirement: '${coreConstraint}'`), 'whisk.starterkit.yml should align generated child theme core compatibility with composer.json.');
    ensure(/^\s*hidden:\s+null\s*$/m.test(whiskStarterkit), 'whisk.starterkit.yml should expose hidden: null in the starterkit info overrides.');
    for (const starterOnlyFile of ['whisk.starterkit.yml', 'whisk.info.emulsify.yml']) {
      ensure(starterkitSmoke.includes(starterOnlyFile), `starterkit-smoke.sh should assert ${starterOnlyFile} is not retained.`);
    }
    ensure(starterkitSmoke.includes('assert_existing_file "project.emulsify.json"'), 'starterkit-smoke.sh should require project.emulsify.json in generated child themes.');
    ensure(starterkitSmoke.includes('"platform": "drupal"'), 'starterkit-smoke.sh should assert the generated Emulsify project uses the Drupal platform adapter.');
    ensure(starterkitSmoke.includes('"singleDirectoryComponents": true'), 'starterkit-smoke.sh should assert generated child theme SDC behavior.');
    ensure(starterkitSmoke.includes('phase="${3:-all}"'), 'starterkit-smoke.sh should support split CI phases while preserving all-in-one local runs.');
    ensure(starterkitSmoke.includes('tee "$log_file"'), 'starterkit-smoke.sh should stream frontend command output while preserving log artifacts.');
    ensure(starterkitSmoke.includes('npm run build'), 'starterkit-smoke.sh should verify the generated child theme Vite-based build workflow.');
    ensure(starterkitSmoke.includes('npm run test'), 'starterkit-smoke.sh should verify the generated child theme test script.');
    ensure(!starterkitSmoke.includes('frontend-tokens'), 'starterkit-smoke.sh should not assume a design-token pipeline.');
    ensure(starterkitSmoke.includes('EMULSIFY_STARTERKIT_STORYBOOK_BUILD'), 'starterkit-smoke.sh should expose release-only Storybook build coverage.');
    ensure(starterkitSmoke.includes('generated-theme-info.yml'), 'starterkit-smoke.sh should copy generated theme info into smoke artifacts.');
    ensure(whiskPackage.scripts.build.includes('vite build --config'), 'whisk/package.json build script should run a finite Vite production build.');
    for (const sourceEntry of ['foundation.scss', 'layout.scss', 'tokens.scss']) {
      ensure(fs.existsSync(path.join(repoRoot, 'whisk/src', sourceEntry)), `whisk/src/${sourceEntry} should exist so generated Vite builds produce the global library assets.`);
    }
    ensure(themeReadinessWorkflow.includes("Whisk starter: generate child theme"), 'theme-readiness.yml should split Whisk starter smoke into a generate step.');
    ensure(themeReadinessWorkflow.includes("Generated child theme: install frontend dependencies"), 'theme-readiness.yml should split generated child theme smoke into a frontend install step.');
    ensure(themeReadinessWorkflow.includes("Generated child theme: run frontend tests"), 'theme-readiness.yml should run the generated child theme test script.');
    ensure(themeReadinessWorkflow.includes('Generated Child Theme Storybook and Accessibility'), 'theme-readiness.yml should expose scheduled/manual generated child theme Storybook and a11y coverage.');
    ensure(themeReadinessWorkflow.includes('EMULSIFY_STARTERKIT_STORYBOOK_BUILD'), 'theme-readiness.yml should enable generated Storybook build coverage in extended checks.');
    ensure(themeReadinessWorkflow.includes('EMULSIFY_STARTERKIT_A11Y'), 'theme-readiness.yml should enable generated accessibility coverage in extended checks.');
    ensure(themeReadinessWorkflow.includes('EMULSIFY_STARTERKIT_TEST'), 'theme-readiness.yml should enable generated starter test coverage in extended checks.');
    ensure(!themeReadinessWorkflow.includes("Generated child theme: build design tokens"), 'theme-readiness.yml should not assume generated child themes use a design-token pipeline.');
    ensure(themeReadinessWorkflow.includes('timeout-minutes'), 'theme-readiness.yml should bound starterkit smoke phases with timeouts.');
    ensure(themeReadinessWorkflow.includes('Upload generated child theme smoke artifacts'), 'theme-readiness.yml should upload generated child theme smoke artifacts on failure.');
    ensure(extractYamlValue(whiskInfo, 'base theme') === 'emulsify', 'whisk.info.yml should keep emulsify as the generated child theme parent.');
    ensure(extractYamlValue(whiskInfo, 'hidden') === 'true', 'whisk.info.yml should remain hidden.');
    ensure(extractYamlValue(whiskInfoStarter, 'base theme') === 'emulsify', 'whisk.info.emulsify.yml should keep emulsify as the generated child theme parent.');
    ensure(extractYamlValue(whiskInfoStarter, 'version') === 'VERSION', 'whisk.info.emulsify.yml should preserve Drupal\'s VERSION token.');
    ensure(extractYamlValue(whiskInfoStarter, 'hidden') === 'false', 'whisk.info.emulsify.yml should unhide generated child themes.');
    return 'Whisk starter source files and generated child theme markers look consistent.';
  });

  runStaticCheck('Schema validity', () => {
    const defaults = {
      label: 'src/Favicon/FaviconSettings.php::DEFAULTS',
      keys: extractFaviconDefaultKeys('src/Favicon/FaviconSettings.php'),
    };

    compareFaviconSettingKeySets('emulsify.settings', {
      defaults,
      install: { label: 'config/install/emulsify.settings.yml', keys: extractFaviconInstallKeys('config/install/emulsify.settings.yml') },
      schema: { label: 'config/schema/emulsify.schema.yml emulsify.settings.mapping', keys: extractFaviconSchemaKeys('config/schema/emulsify.schema.yml', 'emulsify.settings') },
    });

    compareFaviconSettingKeySets('whisk.settings', {
      defaults,
      install: { label: 'whisk/config/install/whisk.settings.yml', keys: extractFaviconInstallKeys('whisk/config/install/whisk.settings.yml') },
      schema: { label: 'whisk/config/schema/whisk.schema.yml whisk.settings.mapping', keys: extractFaviconSchemaKeys('whisk/config/schema/whisk.schema.yml', 'whisk.settings') },
    });

    const defaultKeys = defaults.keys;
    return `Validated ${defaultKeys.length} favicon settings keys across defaults, install config, and schema files.`;
  });
}

function runSmokeChecks() {
  if (options.skipSmoke) {
    addResult('SKIP', 'Stable9 template parity', 'Skipped with --skip-smoke.');
    addResult('SKIP', 'Parent theme render smoke', 'Skipped with --skip-smoke.');
    addResult('SKIP', 'Generated child theme smoke test', 'Skipped with --skip-smoke.');
    addResult('SKIP', 'Favicon generation', 'Skipped with --skip-smoke.');
    addResult('SKIP', 'Favicon portability and sanitizer coverage', 'Skipped with --skip-smoke.');
    return;
  }

  const smokeRoot = options.workDir;
  const baseFixture = path.join(smokeRoot, 'base-fixture');
  const generatedThemeFixture = path.join(smokeRoot, 'generated-theme-fixture');
  const faviconFixture = path.join(smokeRoot, 'favicon-fixture');
  const faviconPortabilityFixture = path.join(smokeRoot, 'favicon-portability-fixture');
  const baseThemeOutput = path.join(smokeRoot, 'base-theme-output');
  const generatedThemeOutput = path.join(smokeRoot, 'generated-theme-output');

  // Build one clean Drupal fixture, then copy it for stateful smoke slices.
  // Favicon generation and starterkit enabling intentionally mutate config and
  // files, so copies keep those assertions from contaminating each other.
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
    addResult('FAIL', 'Parent theme render smoke', 'Unable to build the Drupal fixture site for smoke testing.');
    addResult('FAIL', 'Generated child theme smoke test', 'Unable to build the Drupal fixture site for smoke testing.');
    addResult('FAIL', 'Favicon generation', 'Unable to build the Drupal fixture site for smoke testing.');
    addResult('FAIL', 'Favicon portability and sanitizer coverage', 'Unable to build the Drupal fixture site for smoke testing.');
    return;
  }

  runSmokeCheck(
    'Stable9 template parity',
    'bash',
    [path.join(repoRoot, '.github/scripts/template-parity.sh'), baseFixture, repoRoot],
    repoRoot,
    { passMessage: 'Verified that Emulsify ships every stable9 template path without declaring stable9 as the parent theme.' },
  );

  runSmokeCheck(
    'Parent theme render smoke',
    'bash',
    [path.join(repoRoot, '.github/scripts/render-reference-pages.sh'), baseFixture, baseThemeOutput],
    repoRoot,
    { passMessage: 'Parent theme pages rendered successfully on the fixture site.' },
  );

  copyDirectory(baseFixture, generatedThemeFixture);
  runSmokeCheck(
    'Generated child theme smoke test',
    'bash',
    [path.join(repoRoot, '.github/scripts/starterkit-smoke.sh'), generatedThemeFixture, generatedThemeOutput],
    repoRoot,
    {
      env: { EMULSIFY_STARTERKIT_STORYBOOK_BUILD: '1' },
      passMessage: `Whisk starter generation and generated child theme smoke passed on Drupal ${options.drupalVersion}.`,
    },
  );

  copyDirectory(baseFixture, faviconFixture);
  runSmokeCheck(
    'Favicon generation',
    'bash',
    [path.join(repoRoot, '.github/scripts/favicon-smoke.sh'), faviconFixture, 'emulsify'],
    repoRoot,
    { passMessage: 'Verified existing favicon package head attachment without runtime generation.' },
  );

  copyDirectory(baseFixture, faviconPortabilityFixture);
  runSmokeCheck(
    'Favicon portability and sanitizer coverage',
    'bash',
    [path.join(repoRoot, '.github/scripts/favicon-portability-smoke.sh'), faviconPortabilityFixture, 'emulsify'],
    repoRoot,
    { passMessage: 'Verified portable favicon regeneration, reset, and sanitizer portability coverage.' },
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
