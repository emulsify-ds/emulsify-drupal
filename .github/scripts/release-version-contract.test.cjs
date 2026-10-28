const assert = require('node:assert/strict');
const test = require('node:test');
const { validateReleaseVersions } = require('./release-version-contract.cjs');

function fixture(overrides = {}) {
  const version = overrides.latestTag || '7.2.1';
  return {
    tags: ['v1.0.0-beta.4', '7.2.1', '7.2.0'],
    latestTag: '7.2.1',
    rootPackage: { version },
    rootLock: { version, packages: { '': { version } } },
    releaseConfig: { plugins: [
      ['@semantic-release/npm', { npmPublish: false }],
      ['@semantic-release/git', { assets: ['package.json', 'package-lock.json'] }],
      '@semantic-release/github',
    ] },
    readme: "The 7.x series. Composer's installed package version is authoritative. The root version is owned by `@semantic-release/npm`.",
    draft: '# Unreleased changes\n\nRequires Emulsify Core 4.3.1.\n',
    readiness: 'Review commits since the latest tag.',
    generatedFromVersion: '7.2.1',
    ...overrides,
  };
}

test('accepts npm release metadata matching the latest tag', () => {
  assert.equal(validateReleaseVersions(fixture()), '7.2.1');
});

test('accepts a prepared next npm version and rejects stale or skipped versions', () => {
  for (const version of ['7.2.2', '7.3.0', '8.0.0', '7.0.0', '7.2.3', '7.4.0']) {
    const check = () => validateReleaseVersions(fixture({
      rootPackage: { version },
      rootLock: { version, packages: { '': { version } } },
    }));
    if (['7.2.2', '7.3.0', '8.0.0'].includes(version)) {
      assert.equal(check(), '7.2.1');
    }
    else {
      assert.throws(check, /Root package.json version/);
    }
  }
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

test('requires npm prepare before the Git plugin and both version files in the release commit', () => {
  for (const plugins of [
    ['@semantic-release/github'],
    [['@semantic-release/npm', { npmPublish: true }]],
    [['@semantic-release/npm', { npmPublish: false }]],
    [['@semantic-release/npm', { npmPublish: false }], '@semantic-release/git'],
    [['@semantic-release/git', { assets: ['package.json', 'package-lock.json'] }], ['@semantic-release/npm', { npmPublish: false }]],
    [['@semantic-release/npm', { npmPublish: false }], ['@semantic-release/git', { assets: ['package.json'] }]],
  ]) {
    assert.throws(() => validateReleaseVersions(fixture({ releaseConfig: { plugins } })), /policy/);
  }
});

test('rejects an unknown starter baseline and missing release tags', () => {
  assert.throws(() => validateReleaseVersions(fixture({ generatedFromVersion: '7.1.5' })), /generatedFromVersion/);
  assert.throws(() => validateReleaseVersions(fixture({ latestTag: undefined })), /fetch the full Git history/);
});

test('accepts the latest starter tag or an untagged next patch, minor, or major version', () => {
  for (const generatedFromVersion of ['7.2.2', '7.2.3', '7.3.0', '8.0.0']) {
    assert.equal(validateReleaseVersions(fixture({
      tags: ['7.2.2', '7.2.1'],
      latestTag: '7.2.2',
      generatedFromVersion,
    })), '7.2.2');
  }
});

test('rejects unknown starter baselines and skipped release increments', () => {
  for (const generatedFromVersion of ['7.2.0', '7.2.4', '7.3.1', '7.4.0', '8.0.1', '9.0.0']) {
    assert.throws(() => validateReleaseVersions(fixture({
      tags: ['7.2.2', '7.2.1'],
      latestTag: '7.2.2',
      generatedFromVersion,
    })), /generatedFromVersion/);
  }
});

test('accepts published historical starter baselines, including v aliases', () => {
  for (const tag of ['7.2.0', 'v7.2.0', '7.2.1', 'v7.2.1']) {
    assert.equal(validateReleaseVersions(fixture({
      tags: ['7.2.2', tag],
      latestTag: '7.2.2',
      generatedFromVersion: tag.replace(/^v/, ''),
    })), '7.2.2');
  }
});

test('rejects starter lineage outside canonical stable x.y.z versions', () => {
  for (const generatedFromVersion of [
    undefined, null, 7.3, '', '7', '7.3', 'v7.3.0', '7.3.0-beta.1',
    '7.3.0+build.1', '7.3.0.1', '07.3.0', '7.03.0', '7.3.00', ' 7.3.0', '7.3.0\n',
  ]) {
    assert.throws(() => validateReleaseVersions(fixture({ generatedFromVersion })), /generatedFromVersion/);
  }
});

test('keeps prepared starter lineage valid when its release tag is published', () => {
  const generatedFromVersion = '7.3.0';
  assert.equal(validateReleaseVersions(fixture({
    tags: ['7.2.2', '7.2.1'],
    latestTag: '7.2.2',
    generatedFromVersion,
  })), '7.2.2');
  const published = fixture({ tags: ['7.3.0', '7.2.2', '7.2.1'], latestTag: '7.3.0', generatedFromVersion });
  assert.equal(validateReleaseVersions(published), '7.3.0');
  assert.equal(validateReleaseVersions({ ...published, generatedFromVersion: '7.2.2' }), '7.3.0');
});
