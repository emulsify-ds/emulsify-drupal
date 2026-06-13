#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '../..');

const CHECKS = [
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
];

function readFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

function readJson(relativePath) {
  return JSON.parse(readFile(relativePath));
}

function normalizeHeadingText(text) {
  return text.replace(/\s+#+\s*$/, '').trim();
}

function extractMarkdownSection(relativePath, heading) {
  const lines = readFile(relativePath).split(/\r?\n/);

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

  throw new Error(`${relativePath} is missing the "${heading}" documentation section.`);
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

function lineOffsetForIndex(text, index) {
  return text.slice(0, index).split(/\r?\n/).length - 1;
}

function unique(values) {
  return [...new Set(values)];
}

function validateScope(scope) {
  const section = extractMarkdownSection(scope.relativePath, scope.heading);
  const packageJson = readJson(scope.packagePath);
  const scripts = packageJson.scripts || {};
  const commands = [
    ...extractShellFenceCommands(section),
    ...(scope.includeInlineCode ? extractInlineCommands(section) : []),
  ];
  const documentedScripts = unique(commands.map((command) => command.script)).sort();
  const errors = [];

  if (commands.length === 0) {
    errors.push(`${scope.relativePath}#${scope.heading} does not document any npm run commands for ${scope.packageLabel}.`);
  }

  for (const expectedScript of scope.expectedScripts || []) {
    if (!documentedScripts.includes(expectedScript)) {
      errors.push(`${scope.relativePath}#${scope.heading} should document npm run ${expectedScript} for ${scope.packageLabel}.`);
    }
  }

  for (const command of commands) {
    if (!scripts[command.script]) {
      errors.push(`${scope.relativePath}:${command.line} documents npm run ${command.script} for ${scope.packageLabel}, but ${scope.packagePath} has no "${command.script}" script.`);
    }
  }

  return {
    documentedScripts,
    errors,
  };
}

const errors = [];
const summaries = [];

for (const scope of CHECKS) {
  const result = validateScope(scope);
  errors.push(...result.errors);
  summaries.push(`${scope.relativePath}#${scope.heading} -> ${scope.packagePath}: ${result.documentedScripts.join(', ')}`);
}

if (errors.length > 0) {
  for (const error of errors) {
    console.error(error);
  }
  process.exit(1);
}

console.log(`Validated documented npm scripts in ${CHECKS.length} documentation sections.`);
for (const summary of summaries) {
  console.log(`- ${summary}`);
}
