#!/usr/bin/env node

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

function validateReleaseVersions({
  tags, latestTag, rootPackage, rootLock, releaseConfig,
  readme, draft, readiness, generatedFromVersion,
}) {
  assert.ok(latestTag, 'No stable release tag is available; fetch the full Git history and tags.');
  const plugins = releaseConfig.plugins.map((plugin) => Array.isArray(plugin) ? plugin : [plugin, {}]);
  const npmPlugin = plugins.find(([name]) => name === '@semantic-release/npm');
  assert.equal(npmPlugin?.[1]?.npmPublish, false, 'The documented version policy requires @semantic-release/npm with npmPublish: false.');
  const gitIndex = plugins.findIndex(([name]) => name === '@semantic-release/git');
  assert.ok(gitIndex > plugins.indexOf(npmPlugin), 'The version policy requires @semantic-release/git after @semantic-release/npm so the calculated version is committed.');
  assert.deepEqual(plugins[gitIndex][1]?.assets, ['package.json', 'package-lock.json'], 'The version policy requires committing package.json and package-lock.json release metadata.');
  assert.equal(rootLock.version, rootPackage.version, 'package-lock.json version must match package.json release metadata.');
  assert.equal(rootLock.packages?.['']?.version, rootPackage.version, 'package-lock.json root package version must match package.json release metadata.');
  const releasedVersions = new Set(tags.map((tag) => tag.replace(/^v/, '')));
  const [major, minor, patch] = latestTag.split('.').map(Number);
  const nextVersions = [
    `${major}.${minor}.${patch + 1}`,
    `${major}.${minor + 1}.0`,
    `${major + 1}.0.0`,
  ];
  for (const [name, version] of [['Root package.json version', rootPackage.version], ['Whisk generatedFromVersion', generatedFromVersion]]) {
    assert.ok(typeof version === 'string' && /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(version), `${name} must use a stable x.y.z version.`);
  }
  assert.ok(
    rootPackage.version === latestTag || (!releasedVersions.has(rootPackage.version) && nextVersions.includes(rootPackage.version)),
    `Root package.json version must record the latest stable tag ${latestTag} or an untagged next patch, minor, or major version (${nextVersions.join(', ')}).`,
  );
  assert.ok(
    releasedVersions.has(generatedFromVersion) || nextVersions.includes(generatedFromVersion),
    `Whisk generatedFromVersion must record a published stable starter baseline or an untagged next patch, minor, or major version (${nextVersions.join(', ')}).`,
  );
  assert.ok(readme.includes(`${latestTag.split('.')[0]}.x series`), 'README.md must describe the major series from the latest release tag.');
  assert.ok(readme.includes("Composer's installed package version is authoritative"), 'README.md must identify Composer installed metadata as the parent release authority.');
  assert.ok(readme.includes('version is owned by `@semantic-release/npm`'), 'README.md must explain semantic-release ownership of npm version metadata.');

  for (const line of draft.split(/\r?\n/)) {
    // Release titles are claims about this draft. Compatibility requirements and
    // links to historical releases in ordinary prose are not draft titles.
    if (!/^\s*(?:#{1,6}\s|Title\s*:)/i.test(line)) continue;
    for (const match of line.matchAll(/\bv?(\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?)\b/g)) {
      assert.ok(!releasedVersions.has(match[1]), `The next-release draft names already-tagged release ${match[1]}; keep the draft unreleased.`);
    }
  }
  for (const [file, text] of [['README.md', readme], ['docs/release-notes-next.md', draft], ['docs/release-readiness.md', readiness]]) {
    for (const match of text.matchAll(/(?:latest|current)\s+(?:published\s+)?(?:release|tag|version)\s*:\s*[`*]*v?(\d+\.\d+\.\d+)/gi)) {
      assert.equal(match[1], latestTag, `${file} names a stale current release; use the latest tag or remove the claim.`);
    }
  }
  return latestTag;
}

function checkReleaseVersions(repoRoot = path.resolve(__dirname, '../..')) {
  const git = (...args) => execFileSync('git', args, { cwd: repoRoot, encoding: 'utf8' }).trim();
  assert.equal(git('rev-parse', '--is-shallow-repository'), 'false', 'Release version checks require full Git history and tags (fetch-depth: 0).');
  const tags = git('tag', '--list', '--sort=-version:refname').split('\n').filter(Boolean);
  const latestTag = tags.find((tag) => /^\d+\.\d+\.\d+$/.test(tag));
  assert.ok(latestTag, 'No stable release tag is available; fetch the full Git history and tags.');
  const read = (file) => fs.readFileSync(path.join(repoRoot, file), 'utf8');
  return validateReleaseVersions({
    tags,
    latestTag,
    rootPackage: JSON.parse(read('package.json')),
    rootLock: JSON.parse(read('package-lock.json')),
    releaseConfig: require(path.join(repoRoot, 'release.config.js')),
    readme: read('README.md'),
    draft: read('docs/release-notes-next.md'),
    readiness: read('docs/release-readiness.md'),
    generatedFromVersion: JSON.parse(read('whisk/project.emulsify.json')).project.generatedFromVersion,
  });
}

if (require.main === module) {
  try {
    console.log(`PASS Release version policy and unreleased draft (latest tag: ${checkReleaseVersions()}).`);
  }
  catch (error) {
    console.error(`FAIL ${error.message}`);
    process.exitCode = 1;
  }
}

module.exports = { checkReleaseVersions, validateReleaseVersions };
