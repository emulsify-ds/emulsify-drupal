#!/usr/bin/env node

const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

// Exercise the copied project configuration and npm wrapper with real Jest.
const themeDir = fs.realpathSync(path.resolve(process.argv[2] || ''));
assert.ok(process.argv[2] && fs.existsSync(path.join(themeDir, 'config/jest.config.js')), 'Pass an installed, disposable generated child theme directory.');
const componentDir = path.join(themeDir, 'components/emulsify-jest-check');
assert.ok(!fs.existsSync(componentDir), `Refusing to replace existing component ${componentDir}`);
const outputDir = fs.mkdtempSync(path.join(os.tmpdir(), 'emulsify-jest-results-'));
const testFile = path.join(componentDir, 'value.test.js');
const sourceFile = path.join(componentDir, 'value.js');
const untestedFile = path.join(componentDir, 'untested.mjs');
const resultFile = path.join(outputDir, 'tests.json');

function run() {
  const result = spawnSync('npm', ['test', '--', '--runInBand', '--json', `--outputFile=${resultFile}`, `--coverageDirectory=${outputDir}`], {
    cwd: themeDir, encoding: 'utf8', timeout: 60000,
  });
  process.stdout.write(result.stdout || '');
  process.stderr.write(result.stderr || '');
  assert.ifError(result.error);
  return { status: result.status, report: JSON.parse(fs.readFileSync(resultFile, 'utf8')) };
}

try {
  fs.mkdirSync(componentDir, { recursive: true });
  fs.writeFileSync(sourceFile, 'export const value = 42;\n');
  fs.writeFileSync(untestedFile, 'export const unused = () => 17;\n');
  const passing = "import { value } from './value.js';\ntest('project ESM component executes', () => expect(value).toBe(42));\n";
  fs.writeFileSync(testFile, passing);
  let result = run();
  assert.equal(result.status, 0, 'Passing project tests must succeed.');
  assert.ok(result.report.testResults.some((entry) => entry.name === testFile && entry.status === 'passed'), 'Jest must discover a colocated ESM project test.');
  const coverage = JSON.parse(fs.readFileSync(path.join(outputDir, 'coverage-final.json'), 'utf8'));
  assert.ok(coverage[sourceFile], 'Coverage must include executed project code.');
  assert.ok(coverage[untestedFile], 'Coverage must include untested project code.');
  assert.ok(!Object.keys(coverage).some((file) => file.includes('/node_modules/')), 'Coverage must exclude vendored code.');
  fs.writeFileSync(testFile, passing.replace('toBe(42)', 'toBe(0)'));
  result = run();
  assert.notEqual(result.status, 0, 'A failing project test must fail npm test.');
  assert.ok(result.report.testResults.some((entry) => entry.name === testFile && entry.status === 'failed'), 'The deliberate failure must be the discovered project test.');
  console.log('PASS Project Jest discovery, ESM, coverage, and failure propagation.');
}
finally {
  fs.rmSync(componentDir, { recursive: true, force: true });
  fs.rmSync(outputDir, { recursive: true, force: true });
}
