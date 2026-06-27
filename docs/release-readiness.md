# Release Readiness

Use this checklist before publishing an Emulsify Drupal 7.x minor release.

## Local validation

1. Confirm the current published version on GitHub, Drupal.org, and Packagist.
2. Run `composer validate --no-check-publish --strict`.
3. Run `npm ci --ignore-scripts`.
4. Run `npm audit --omit=dev`.
5. Run `npm audit`.
6. Run `npm run lint:php`.
7. Run `npm run docs:check-commands` after documentation command example changes.
8. Run `npm run release:check`.
9. Run `npm run release:check -- --skip-smoke` after any release-guard edits.

## Release checks

- Confirm `composer.json`, `emulsify.info.yml`, `whisk/whisk.info.yml`, and `whisk/whisk.info.emulsify.yml` describe the same Drupal core and Emulsify Tools compatibility.
- Confirm `LICENSE`, `package.json`, `composer.json`, and `whisk/package.json` all identify the project as `GPL-2.0-or-later`, matching Drupal.org Composer metadata guidance for hosted Drupal projects.
- Confirm local README and upgrade notes describe the current Emulsify Drupal parent theme workflow, Drupal 11.3+ and Drupal 12 forward compatibility, Node.js 24 tooling, and favicon package deployment expectations.
- Confirm the sister-project parity contract stays linked from the README and preserves the shared Emulsify Drupal/WordPress contract plus Drupal-specific generation, metadata, component include, and fixture readiness expectations.
- Confirm `whisk` remains generation-only starterkit source and generated child themes keep `emulsify` as their runtime parent theme.
- Confirm generated themes retain `project.emulsify.json`.
- Confirm favicon defaults, install config, schema, and `FaviconSettings::DEFAULTS` remain in sync.
- Confirm release automation still emits non-prefixed SemVer tags and has `npmPublish: false`.

## CI coverage

- Pull requests run Composer validation, `npm ci --ignore-scripts`, runtime and full npm audits, PHP linting, static release checks, template parity, parent-theme render smoke, favicon smoke with GD and Imagick, and generated starterkit build/test smoke.
- The semantic-release workflow runs a blocking release-readiness job before publishing from `main`. That job repeats Composer validation, clean npm install, audits, PHP linting, static release checks, and full `npm run release:check` smoke coverage with GD and Imagick.
- Scheduled and manual Theme Readiness runs include extended generated child-theme Storybook and accessibility checks using `npm run storybook-build` and `npm run a11y`.

## Version strategy

Semantic Release publishes only from `main` with the Angular conventional-commit preset. Use commit types intentionally:

- `feat:` produces a minor release.
- `fix:` and `perf:` produce a patch release.
- `docs:`, `test:`, `chore:`, `ci:`, and merge commits without a release-triggering conventional subject do not produce a release by themselves.
- `type!:` headers or `BREAKING CHANGE` footers produce a major release and should not be used for a 7.x minor unless maintainers are intentionally preparing the next major line.

Run `npm run publish-test -- --no-ci` before release. On non-`main` branches, this should load the release configuration but report that no version will be published because `release.config.js` only publishes from `main`. To predict the eventual version, review commits since the latest tag with the configured analyzer. If the commits after `7.0.0` remain fixes, docs, tests, and internal chores, Semantic Release will produce a patch release, not `7.1.0`.

For an intentional `7.1.0`, land a legitimate backward-compatible feature as a real `feat:` commit before merging to `main`, or document and follow an explicit manual release process. Do not label fixes, tests, metadata cleanup, or documentation-only work as `feat:` just to force a minor version.

## Manual follow-up

- After publishing, verify Drupal.org and Packagist show the Composer package license as `GPL-2.0-or-later`.
- Copy final GitHub release notes into Drupal.org release notes and mark the release supported or recommended as appropriate.
