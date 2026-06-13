# Release Readiness

Use this checklist before publishing an Emulsify Drupal 7.x minor release.

## Local validation

1. Confirm the current published version on GitHub, Drupal.org, and Packagist.
2. Run `composer validate --no-check-publish`.
3. Run `npm ci --ignore-scripts`.
4. Run `npm run release:check`.
5. Run `npm run release:check -- --skip-smoke` after any release-guard edits.

## Release checks

- Confirm `composer.json`, `emulsify.info.yml`, `whisk/whisk.info.yml`, and `whisk/whisk.info.emulsify.yml` describe the same Drupal core and Emulsify Tools compatibility.
- Confirm `whisk` remains generation-only starter source and generated child themes keep `emulsify` as their runtime parent theme.
- Confirm generated themes retain `project.emulsify.json`.
- Confirm favicon defaults, install config, schema, and `FaviconSettings::DEFAULTS` remain in sync.
- Confirm release automation still emits non-prefixed SemVer tags and has `npmPublish: false`.

## Manual follow-up

- Review license metadata before release. This repository currently needs an explicit maintainer decision if `LICENSE`, `composer.json`, `package.json`, and Drupal.org metadata should be normalized.
- Copy final GitHub release notes into Drupal.org release notes and mark the release supported or recommended as appropriate.
