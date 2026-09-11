const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const { validateReadinessMatrix } = require('./readiness-matrix-contract.cjs');

const workflow = fs.readFileSync(path.join(__dirname, '../workflows/theme-readiness.yml'), 'utf8');
const requiredTargets = ['11.3.*', '11.4.*', '>=12.0.0-beta1 <12.0.0-RC1@beta', 'dev-main'];

test('reads matrix versions independently of YAML quote style', () => {
  for (const target of ["'11.4.*'", '"11.4.*"', '11.4.*']) {
    assert.ok(validateReadinessMatrix(workflow.replaceAll("'11.4.*'", target), requiredTargets).some((entry) => entry['drupal-version'] === '11.4.*'));
  }
});

test('rejects hardcoded fixture versions in either readiness job', () => {
  const matrixArgument = '"${{ matrix.drupal-version }}"';
  for (const literal of ['"11.4.6"', "'11.4.6'", '11.4.6']) {
    for (const offset of [workflow.indexOf(matrixArgument), workflow.lastIndexOf(matrixArgument)]) {
      const changed = workflow.slice(0, offset) + literal + workflow.slice(offset + matrixArgument.length);
      assert.throws(() => validateReadinessMatrix(changed, requiredTargets), /not a hardcoded fixture version/);
    }
  }
});

test('cannot satisfy required coverage with a comment outside the matrix', () => {
  const changed = workflow.replaceAll("'11.4.*'", "'11.3.*'") + "\n# drupal-version: '11.4.*'\n";
  assert.throws(() => validateReadinessMatrix(changed, requiredTargets), /smoke test Drupal 11\.4\.\*/);
});

test('requires a matrix for the extended fixture job', () => {
  const changed = workflow.replace(/    strategy:\n      matrix:\n        include:\n          - drupal-version: '11\.4\.\*'\n            php-version: '8\.3'\n/, '');
  assert.throws(() => validateReadinessMatrix(changed, requiredTargets), /generated-child-theme-extended must declare/);
});
