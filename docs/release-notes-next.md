# Unreleased changes

This draft describes changes since the latest published tag. Semantic Release
determines the next version from the commits on `main`; published history is in
[GitHub Releases](https://github.com/emulsify-ds/emulsify-drupal/releases).
These fixes and validation changes do not justify forcing a minor release.

## Fixed

- Favicon sources now allow only supported static SVG elements. Animated links,
  script handlers, namespaced event attributes, and nested SVG data URIs are
  removed with a warning. Oversized canvases are rejected, and Imagick
  rasterization has bounded dimensions and resource limits. Simplify unsupported
  SVG artwork before re-uploading; embedded raster images remain supported.
- PHP linting and copied lint/fix/format wrappers run every constituent check
  and return failure when any check fails.
- Generated Jest configuration discovers colocated project tests, runs native
  ESM, reports project coverage, and fails when no tests exist or a test fails.
  The existing `test` and `twatch` script names are preserved.
- Favicon previews use enabled, existing saved packages, with balanced browser
  markup, the actual maskable image, and no second application of background or
  padding. Unsaved form changes leave saved previews intact while generation
  controls and pending-change notices continue to update.

## Dependencies and CI

- Incorporated the compatible lockfile updates reviewed in dependency PRs
  #378 and #379, plus compatible npm bundle fixes. Root npm and Composer audits
  report no advisories in the recorded runs; the fresh Whisk tree resolves
  Emulsify Core 4.4.0. See the [dependency audit](./dependency-audit-2026-09-08.md)
  for preserved ranges and complete outputs.
- Restricted validation jobs to read-only repository permissions, scoped
  publishing permissions to the release job, and pinned actions to verified
  immutable commits.
- Added pull-request consumer coverage that generates a theme, installs a real
  component fixture, builds Vite and Storybook, runs the existing `a11y` command,
  and audits the component and Drupal pages against WCAG 2.2 AA rules. Build,
  rendering, and accessibility failures propagate to CI.
- Added published-package installation checks for both Drupal.org and
  Packagist routes on Drupal 11/PHP 8.3 and Drupal 12/PHP 8.5, including strict
  Composer validation, audit, theme enabling, libraries, and breakpoints. All
  four recorded legs passed. The current Drupal 12 resolution is an alpha;
  see [published dependency validation](./published-dependency-validation.md).

## Documentation and characterization

- Documented Composer installation, upgrades, and installed-release identity;
  verified Tools-owned Drush generation and the appropriate core Starterkit
  entrypoint. Release checks reject stale draft titles and manual drift in
  semantic-release-owned npm version metadata.
- Explained Whisk's advertised Node range, Core's effective Node floor, the
  separate release-tooling requirement, and PHP floors inherited from Drupal.
  Copied guides now explain project test discovery and failure on missing tests.
- Added exact characterization tests and a [Twig hook and helper
  contract](./twig-hook-contract.md) for paragraph variables, form/field/Views
  suggestions, and paired PHP/JavaScript helpers. Existing output and all
  observed divergences remain unchanged.

## Findings still requiring follow-up

The stricter accessibility gate currently fails on `target-size` at three
locations: the site-name link on `/node/1`, the site-name link on `/user/login`,
and the active Log in tab on `/user/login`. No rule is suppressed and no
existing theme markup, classes, or styles were changed to make it pass. The
real component has no reported violations. See [the findings and failure
propagation evidence](./consumer-accessibility.md).

The Whisk audit reports six high-severity package entries caused by the single
unfixed upstream `extract-zip` symlink traversal advisory. The registry has no
compatible fixed release; no forced dependency-range change or replacement was
made. The [audit record](./dependency-audit-2026-09-08.md) identifies every
affected entry and its deferral reason.

## Consumer actions and release validation

Review [UPGRADE.md](../UPGRADE.md) for before/after diffs of copied scripts,
Jest configuration, and documentation. A parent-theme update does not rewrite
an existing generated child theme.

Run `npm run release:check -- --skip-smoke` for static validation,
`npm run release:check` for Drupal fixture coverage, and
`npm run publish-test -- --no-ci` to review the calculated release. Review the
linked audit and accessibility findings before publishing; remove this draft's
published entries after release.
