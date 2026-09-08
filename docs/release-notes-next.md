# Unreleased changes

This draft describes changes since the latest published tag. Semantic Release
determines the next version from the commits on `main`; do not assign a release
number to this draft or copy already-published notes into it. Published history
is in [GitHub Releases](https://github.com/emulsify-ds/emulsify-drupal/releases).

## Documentation and release checks

- Documented Composer installation and upgrades for the Drupal.org and
  Packagist package routes, and identified Emulsify Tools as the implementation
  of the Drush generation commands.
- Made Composer's installed package metadata the authoritative way to identify
  the parent theme release, and explained semantic-release ownership of the
  root npm version.
- Replaced obsolete release-specific guidance with an unreleased draft and
  checks for already-tagged draft titles and manual npm-version drift.
- Documented the different Node requirements of Whisk, Emulsify Core, and
  release tooling, plus the PHP minimum inherited from each Drupal major.

## Consumer actions

Review [UPGRADE.md](../UPGRADE.md) for opt-in changes to copied child-theme
files. Updating the parent theme does not rewrite an existing generated theme.

## Validation before publishing

- Run `npm run release:check -- --skip-smoke` and the release-version regression
  tests with `node --test .github/scripts/release-version-contract.test.cjs`.
- Run `npm run release:check` for the Drupal fixture checks.
- Run `npm run publish-test -- --no-ci` to review the calculated release.
- Add further unreleased changes only after their implementation and validation
  have been reviewed; remove this draft's published entries after release.
