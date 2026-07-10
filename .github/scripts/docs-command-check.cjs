#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '../..');

const ROOT_CHECKS = [
  {
    relativePath: 'README.md',
    heading: 'Generate a child theme',
    packagePath: 'whisk/package.json',
    packageLabel: 'generated child themes',
    expectedScripts: ['develop'],
  },
  {
    relativePath: 'README.md',
    heading: 'Verify your generated child theme',
    packagePath: 'whisk/package.json',
    packageLabel: 'generated child themes',
    expectedScripts: ['build', 'storybook-build', 'test', 'a11y'],
  },
  {
    relativePath: 'UPGRADE.md',
    heading: 'Project Audit',
    packagePath: 'whisk/package.json',
    packageLabel: 'generated child themes',
    expectedScripts: ['audit', 'audit:twig-stories'],
  },
  {
    relativePath: 'README.md',
    heading: 'Release Readiness',
    packagePath: 'package.json',
    packageLabel: 'the root project',
    expectedScripts: ['release:check'],
  },
  {
    relativePath: 'docs/release-readiness.md',
    heading: 'Local validation',
    packagePath: 'package.json',
    packageLabel: 'the root project',
    includeInlineCode: true,
    expectedScripts: ['docs:check-commands', 'lint:php', 'release:check'],
  },
  {
    relativePath: 'docs/generated-child-theme-contract.md',
    heading: 'Run the checks',
    packagePath: 'package.json',
    packageLabel: 'the root project',
    expectedScripts: ['test:generated-theme', 'release:check'],
  },
];

const THEME_DOC_CHECKS = [
  {
    relativePath: 'README.md',
    packagePath: 'package.json',
    packageLabel: 'the theme',
    includeInlineCode: true,
    expectedScripts: ['develop', 'build', 'storybook', 'storybook-build', 'lint', 'test', 'a11y'],
    requireNpmInstall: true,
  },
  {
    relativePath: 'UPGRADING.md',
    packagePath: 'package.json',
    packageLabel: 'the theme',
    includeInlineCode: true,
  },
  {
    relativePath: 'docs/support-information.md',
    packagePath: 'package.json',
    packageLabel: 'the theme',
    includeInlineCode: true,
  },
];

function readFile(root, relativePath) {
  return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

function readJson(root, relativePath) {
  return JSON.parse(readFile(root, relativePath));
}

function normalizeHeadingText(text) {
  return text.replace(/\s+#+\s*$/, '').trim();
}

function extractMarkdownSection(root, relativePath, heading) {
  const lines = readFile(root, relativePath).split(/\r?\n/);

  if (!heading) {
    return {
      text: lines.join('\n'),
      startLine: 1,
    };
  }

  for (let index = 0; index < lines.length; index += 1) {
    const match = lines[index].match(/^(#{1,6})\s+(.+?)\s*$/);
    if (!match || normalizeHeadingText(match[2]) !== heading) {
      continue;
    }

    const level = match[1].length;
    const bodyStart = index + 1;
    let bodyEnd = lines.length;
    for (let nextIndex = bodyStart; nextIndex < lines.length; nextIndex += 1) {
      const nextMatch = lines[nextIndex].match(/^(#{1,6})\s+/);
      if (nextMatch && nextMatch[1].length <= level) {
        bodyEnd = nextIndex;
        break;
      }
    }

    return {
      text: lines.slice(bodyStart, bodyEnd).join('\n'),
      startLine: bodyStart + 1,
    };
  }

  throw new Error(`${relativePath}:1 is missing the "${heading}" documentation section.`);
}

function extractShellFenceCommands(section) {
  const commands = [];
  const lines = section.text.split(/\r?\n/);
  let shellFence = null;

  for (let index = 0; index < lines.length; index += 1) {
    const line = lines[index];
    const fenceMatch = line.match(/^```([A-Za-z0-9_-]*)\s*$/);

    if (!shellFence && fenceMatch) {
      const language = fenceMatch[1].toLowerCase();
      shellFence = {
        collect: ['bash', 'sh', 'shell'].includes(language),
        lines: [],
        startLine: section.startLine + index + 1,
      };
      continue;
    }

    if (shellFence && /^```\s*$/.test(line)) {
      if (shellFence.collect) {
        commands.push(...extractNpmRunCommands(shellFence.lines.join('\n'), shellFence.startLine));
      }
      shellFence = null;
      continue;
    }

    if (shellFence && shellFence.collect) {
      shellFence.lines.push(line);
    }
  }

  return commands;
}

function extractInlineCommands(section) {
  const commands = [];
  for (const match of section.text.matchAll(/`([^`\n]*\bnpm\s+run\s+[^`]*)`/g)) {
    commands.push(...extractNpmRunCommands(match[1], section.startLine + lineOffsetForIndex(section.text, match.index)));
  }
  return commands;
}

function extractNpmRunCommands(text, startLine) {
  const commands = [];
  const lines = text.split(/\r?\n/);
  for (let index = 0; index < lines.length; index += 1) {
    const line = lines[index].trim();
    if (line === '' || line.startsWith('#')) {
      continue;
    }

    for (const match of line.matchAll(/\bnpm\s+run\s+([A-Za-z0-9:_-]+)/g)) {
      commands.push({
        script: match[1],
        line: startLine + index,
      });
    }
  }

  return commands;
}

function hasExactNpmInstall(section) {
  return section.text.split(/\r?\n/).some((line) => line.trim() === 'npm install')
    || /`npm install`/.test(section.text);
}

function lineOffsetForIndex(text, index) {
  return text.slice(0, index).split(/\r?\n/).length - 1;
}

function unique(values) {
  return [...new Set(values)];
}

function prefixThemeScope(scope) {
  return {
    ...scope,
    relativePath: path.join('whisk', scope.relativePath),
    packagePath: path.join('whisk', scope.packagePath),
  };
}

function validateScope(root, scope, displayRoot = '') {
  const displayPath = path.join(displayRoot, scope.relativePath);
  let section;
  let packageJson;

  try {
    section = extractMarkdownSection(root, scope.relativePath, scope.heading);
  }
  catch (error) {
    return { documentedScripts: [], errors: [`${displayPath}:1 ${error.message.replace(/^.*?:1\s+/, '')}`] };
  }

  try {
    packageJson = readJson(root, scope.packagePath);
  }
  catch (error) {
    return { documentedScripts: [], errors: [`${path.join(displayRoot, scope.packagePath)}:1 could not be read: ${error.message}`] };
  }

  const scripts = packageJson.scripts || {};
  const commands = [
    ...extractShellFenceCommands(section),
    ...(scope.includeInlineCode ? extractInlineCommands(section) : []),
  ];
  const documentedScripts = unique(commands.map((command) => command.script)).sort();
  const errors = [];
  const scopeLabel = scope.heading ? `${displayPath}#${scope.heading}` : displayPath;

  if (scope.expectedScripts?.length && commands.length === 0) {
    errors.push(`${displayPath}:1 ${scopeLabel} does not document any npm run commands for ${scope.packageLabel}.`);
  }

  for (const expectedScript of scope.expectedScripts || []) {
    if (!documentedScripts.includes(expectedScript)) {
      errors.push(`${displayPath}:1 should document npm run ${expectedScript} for ${scope.packageLabel}.`);
    }
  }

  if (scope.requireNpmInstall && !hasExactNpmInstall(section)) {
    errors.push(`${displayPath}:1 should document the exact npm install command for ${scope.packageLabel}.`);
  }

  for (const command of commands) {
    if (!scripts[command.script]) {
      errors.push(`${displayPath}:${command.line} documents npm run ${command.script} for ${scope.packageLabel}, but ${path.join(displayRoot, scope.packagePath)} has no "${command.script}" script.`);
    }
  }

  return {
    documentedScripts,
    errors,
  };
}

function validateDocumentation(options = {}) {
  const generatedTheme = options.generatedTheme
    ? path.resolve(options.cwd || process.cwd(), options.generatedTheme)
    : null;
  const checks = generatedTheme
    ? THEME_DOC_CHECKS
    : [
      ...ROOT_CHECKS,
      ...THEME_DOC_CHECKS.map(prefixThemeScope),
    ];
  const root = generatedTheme || repoRoot;
  const displayRoot = generatedTheme || '';
  const errors = [];
  const summaries = [];

  for (const scope of checks) {
    const result = validateScope(root, scope, displayRoot);
    errors.push(...result.errors);
    const scopeLabel = scope.heading
      ? `${scope.relativePath}#${scope.heading}`
      : scope.relativePath;
    summaries.push(`${scopeLabel} -> ${scope.packagePath}: ${result.documentedScripts.join(', ')}`);
  }

  return { errors, summaries, count: checks.length };
}

function parseArguments(argv) {
  if (argv.length === 0) {
    return {};
  }

  if (argv.length === 2 && argv[0] === '--generated-theme' && argv[1]) {
    return { generatedTheme: argv[1] };
  }

  throw new Error('Usage: docs-command-check.cjs [--generated-theme <theme-directory>]');
}

function runCli(argv = process.argv.slice(2)) {
  let result;
  try {
    result = validateDocumentation(parseArguments(argv));
  }
  catch (error) {
    console.error(error.message);
    return 1;
  }

  if (result.errors.length > 0) {
    for (const error of result.errors) {
      console.error(error);
    }
    return 1;
  }

  console.log(`Validated documented npm scripts in ${result.count} documentation sections.`);
  for (const summary of result.summaries) {
    console.log(`- ${summary}`);
  }
  return 0;
}

if (require.main === module) {
  process.exitCode = runCli();
}

module.exports = { validateDocumentation };
