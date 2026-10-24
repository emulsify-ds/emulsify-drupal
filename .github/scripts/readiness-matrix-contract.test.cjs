const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const yaml = require('js-yaml');
const { validateReadinessMatrix } = require('./readiness-matrix-contract.cjs');

const workflow = fs.readFileSync(path.join(__dirname, '../workflows/theme-readiness.yml'), 'utf8');
const requiredTargets = ['11.3.*', '11.4.*', '>=11.5.0-beta1 <11.5.0-RC1@beta', '>=12.0.0-beta1 <12.0.0-RC1@beta', 'dev-main'];

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

test('requires advisory Drupal 11.5 beta coverage on PHP 8.3', () => {
  for (const change of [{ 'php-version': '8.5' }, { experimental: false }, { experimental: undefined }]) {
    const changed = yaml.load(workflow);
    const entry = changed.jobs['theme-readiness'].strategy.matrix.include.find((item) => item['drupal-version'] === '>=11.5.0-beta1 <11.5.0-RC1@beta');
    Object.assign(entry, change);
    assert.throws(() => validateReadinessMatrix(yaml.dump(changed), requiredTargets), /Drupal 11\.5 beta readiness coverage must be advisory on PHP 8\.3/);
  }
});

test('cannot satisfy Drupal 11.5 beta coverage with a comment', () => {
  const changed = yaml.load(workflow);
  changed.jobs['theme-readiness'].strategy.matrix.include = changed.jobs['theme-readiness'].strategy.matrix.include.filter((entry) => entry['drupal-version'] !== '>=11.5.0-beta1 <11.5.0-RC1@beta');
  assert.throws(() => validateReadinessMatrix(yaml.dump(changed) + "\n# drupal-version: '>=11.5.0-beta1 <11.5.0-RC1@beta'\n", requiredTargets), /smoke test Drupal >=11\.5\.0-beta1/);
});
