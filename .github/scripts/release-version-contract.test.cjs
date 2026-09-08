const assert = require('node:assert/strict');
const test = require('node:test');
const { validateReleaseVersions } = require('./release-version-contract.cjs');

function fixture(overrides = {}) {
  return {
    tags: ['v1.0.0-beta.4', '7.2.1', '7.2.0'],
    latestTag: '7.2.1',
    baselineVersion: '7.0.0',
    rootPackage: { version: '7.0.0' },
    rootLock: { version: '7.0.0', packages: { '': { version: '7.0.0' } } },
    releaseConfig: { plugins: [['@semantic-release/npm', { npmPublish: false }], '@semantic-release/github'] },
    readme: "The 7.x series. Composer's installed package version is authoritative. The root version is owned by `@semantic-release/npm`.",
    draft: '# Unreleased changes\n\nRequires Emulsify Core 4.3.1.\n',
    readiness: 'Review commits since the latest tag.',
    generatedFromVersion: '7.2.1',
    ...overrides,
  };
}

test('accepts release-owned npm tooling metadata that differs from the release tag', () => {
  assert.equal(validateReleaseVersions(fixture()), '7.2.1');
});

test('rejects a manual npm version correction to the current release', () => {
  assert.throws(() => validateReleaseVersions(fixture({ rootPackage: { version: '7.2.1' } })), /Do not hand-edit/);
});

test('rejects package-lock version drift in either metadata location', () => {
  for (const rootLock of [
    { version: '7.2.1', packages: { '': { version: '7.0.0' } } },
    { version: '7.0.0', packages: { '': { version: '7.2.1' } } },
  ]) {
    assert.throws(() => validateReleaseVersions(fixture({ rootLock })), /package-lock.json/);
  }
});

test('rejects the former already-shipped draft heading and release titles', () => {
  for (const draft of [
    '# 7.2.1 Release Notes Draft',
    '# Unreleased\nTitle: `emulsify 7.2.1`',
    '# Unreleased\nTitle: `v7.2.0`',
    '# v1.0.0-beta.4 Release Notes',
  ]) {
    assert.throws(() => validateReleaseVersions(fixture({ draft })), /already-tagged release/);
  }
});

test('does not confuse historical prose and compatibility with draft titles', () => {
  const draft = '# Unreleased\nRequires Core 4.3.1. Follow the upgrade notes from 7.2.1.\n';
  assert.equal(validateReleaseVersions(fixture({ draft })), '7.2.1');
});

test('rejects a stale latest release claim without rejecting historical notes', () => {
  assert.throws(() => validateReleaseVersions(fixture({ draft: '# Unreleased\nLatest published tag: `7.2.0`.' })), /stale current release/);
  assert.equal(validateReleaseVersions(fixture({ readiness: 'Tools ^2.2 became required in 7.2.1.' })), '7.2.1');
});

test('rejects version ownership changes until the policy is reviewed', () => {
  for (const plugins of [
    ['@semantic-release/github'],
    [['@semantic-release/npm', { npmPublish: true }]],
    [['@semantic-release/npm', { npmPublish: false }], '@semantic-release/git'],
  ]) {
    assert.throws(() => validateReleaseVersions(fixture({ releaseConfig: { plugins } })), /policy|ownership/);
  }
});

test('rejects stale starter lineage and missing release tags', () => {
  assert.throws(() => validateReleaseVersions(fixture({ generatedFromVersion: '7.2.0' })), /generatedFromVersion/);
  assert.throws(() => validateReleaseVersions(fixture({ latestTag: undefined })), /fetch the full Git history/);
});
