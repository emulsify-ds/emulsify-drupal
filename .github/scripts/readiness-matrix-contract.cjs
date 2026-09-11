const assert = require('node:assert/strict');
const yaml = require('js-yaml');

function validateReadinessMatrix(source, requiredTargets) {
  const workflow = yaml.load(source);
  for (const [jobName, job] of Object.entries(workflow.jobs)) {
    const fixtureSteps = (job.steps || []).filter((step) => step.run?.includes('setup-fixture-site.sh'));
    if (!fixtureSteps.length) continue;
    assert.ok(job.strategy?.matrix?.include?.length, `${jobName} must declare its Drupal fixture versions in a matrix.`);
    for (const entry of job.strategy.matrix.include) {
      assert.equal(typeof entry['drupal-version'], 'string', `${jobName} must declare each Drupal matrix version as a string.`);
      assert.equal(typeof entry['php-version'], 'string', `${jobName} must declare each PHP matrix version as a string.`);
    }
    for (const step of fixtureSteps) {
      for (const command of step.run.matchAll(/setup-fixture-site\.sh[^\n]*/g)) {
        assert.match(command[0], /^setup-fixture-site\.sh\s+["']?\$\{\{\s*matrix\.drupal-version\s*\}\}["']?(?:\s|$)/,
          `${jobName}: ${step.name} must take its Drupal version from matrix.drupal-version, not a hardcoded fixture version.`);
      }
    }
  }

  const entries = workflow.jobs['theme-readiness'].strategy.matrix.include;
  for (const target of requiredTargets) {
    assert.ok(entries.some((entry) => entry['drupal-version'] === target), `theme-readiness.yml should smoke test Drupal ${target} in its readiness matrix.`);
  }
  return entries;
}

module.exports = { validateReadinessMatrix };
