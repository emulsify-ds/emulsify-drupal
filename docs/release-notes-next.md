# 7.1.0 Release Notes Draft

Status date: 2026-06-13

## Version Strategy

Latest published tag: `7.0.0`.

This branch intentionally includes a backward-compatible `feat:` commit for improved favicon diagnostics. With the configured Angular conventional-commit analyzer, merging this branch to `main` should produce a minor release: `7.1.0`.

Do not publish these notes as `7.1.0` if the diagnostics feature commit is removed, squashed into a non-`feat:` subject, or rewritten in a way that leaves only `fix:`, `docs:`, `test:`, `ci:`, and `chore:` commits after `7.0.0`. In that case, Semantic Release would produce a patch release instead.

## Compatibility Notes

- Drupal compatibility remains `^11.3 || ^12`.
- Drupal 12 support remains forward-compatible through package metadata and release checks.
- Emulsify Tools remains required at `^2.0`.
- Emulsify Drupal remains the parent theme. Generated child themes should keep `emulsify` as their runtime parent theme.
- `whisk` remains a generation-only starterkit source and should not be enabled directly.
- Generated child themes use Emulsify Core 4 and the Vite build workflow.
- Root release tooling requires Node.js `>=24.10`; generated child theme tooling requires Node.js `>=24`.

## Draft GitHub Release Notes

Title: `7.1.0`

### Added

- Added clearer favicon generation diagnostics in the theme settings UI for missing GD/Imagick support, missing generated package files after config import or deploy, invalid saved SVG sources, and sanitized SVG input.
- Added release guards that fail when Drupal regions declared in parent or starterkit theme metadata are not rendered by the corresponding page template.
- Added smoke coverage for block placement in `header`, `content_top`, `content`, `content_bottom`, and `footer` for both the parent theme and generated child themes.
- Added static validation for documented generated child-theme npm command examples.
- Added generated child-theme health-check documentation for build, Storybook build, tests, and optional accessibility checks.

### Changed

- Aligned repository, npm-side, generated starterkit, Composer, Drupal.org, and Packagist license metadata to `GPL-2.0-or-later`.
- Expanded release readiness CI with Composer validation, clean npm installs, runtime and full npm audits, PHP linting, static release checks, full smoke coverage with GD and Imagick, generated starterkit build/test checks, and scheduled/manual Storybook and accessibility coverage.
- Removed the unused direct root `graceful-fs` dependency.
- Refreshed npm lockfile state so runtime and full npm audit checks are clean.
- Clarified that `whisk` is a generation-only starterkit source and generated child themes keep `emulsify` as their runtime parent theme.
- Clarified the 7.x parent theme workflow, Vite build workflow, Node.js expectations, and favicon package deployment expectations in local documentation.

### Fixed

- Fixed declared `content_top` and `content_bottom` regions so placed blocks render in the parent theme and in generated child themes based on `whisk`.
- Hardened SVG favicon sanitization against CSS-based vectors, including `<style>` elements, `style` attributes, CSS `@import`, external CSS `url(...)` references, and CSS JavaScript URLs while preserving local fragment references such as `url(#gradient)`.
- Preserved the existing 7.x starterkit behavior while tightening checks around generated child-theme metadata and npm scripts.

### Documentation

- Updated `README.md` to describe Emulsify Drupal as the parent theme, explain the required Emulsify Tools workflow, document `whisk` as generation-only, and add generated child-theme verification commands.
- Updated `UPGRADE.md` with 7.0.0 to 7.1.0 guidance covering the full template layer, no `stable9` parent, Drupal 11.3+/12 compatibility, Node.js 24, Vite, generated child themes, and favicon package deployment.
- Added Twig component include guidance that recommends Single Directory Component names for new project Twig while preserving legacy namespace includes as valid for existing projects and migrations.
- Updated release-readiness notes with the validation checklist, CI coverage, release strategy, audit policy, and license metadata follow-up.
- Updated local favicon and design-token docs for generated child theme terminology.

### Internal

- Strengthened release checks for metadata consistency, package scripts, documented commands, region rendering, favicon settings schema parity, release workflow expectations, and starterkit contracts.
- Added release-time favicon portability and sanitizer coverage for CSS-based SVG vectors.
- Added a blocking semantic-release readiness job so publishing from `main` waits for Composer, npm audit, PHP lint, static release checks, and full smoke checks.

### Deprecated

- None.

## Draft Drupal.org Release Notes

Title: `emulsify 7.1.0`

### Compatibility

Works with Drupal: `^11.3 || ^12`

Requires Emulsify Tools: `^2.0`

This is a backward-compatible 7.x minor release for the Emulsify Drupal parent theme. Generated child themes should continue to use `emulsify` as their runtime parent theme. The `whisk` directory remains a starterkit source only and should not be enabled directly.

### Added

- Added clearer favicon generation diagnostics in the theme settings UI for missing PHP extensions, missing generated package files, invalid saved SVG sources, and sanitized SVG input.
- Added release guards and smoke coverage to prove declared Drupal regions render in both the parent theme and generated child themes.
- Added generated child-theme health-check documentation for build, Storybook build, tests, and optional accessibility checks.

### Changed

- Aligned repository, npm-side, generated starterkit, Composer, Drupal.org, and Packagist license metadata to `GPL-2.0-or-later`.
- Expanded release readiness checks for Composer metadata, npm install/audit policy, PHP linting, starterkit generation, favicon generation, and generated child-theme frontend behavior.
- Clarified the 7.x parent theme, generated child theme, `whisk`, Vite, Node.js, and favicon deployment workflow in local docs.
- Removed unused root dependency metadata.

### Fixed

- Fixed declared `content_top` and `content_bottom` regions so blocks placed in those regions render in the parent theme and generated child themes.
- Hardened SVG favicon sanitization against CSS-based vectors while preserving valid simple SVG favicon sources.

### Internal

- Added static checks for documented generated child-theme npm commands.
- Added release-time checks for declared region rendering, metadata consistency, favicon schema parity, release workflow expectations, and starterkit contracts.
- Added a blocking semantic-release readiness job before automated GitHub release publishing.

## Validation Notes

- `release.config.js` publishes from `main`, emits non-prefixed SemVer tags, and uses the Angular conventional-commit analyzer.
- The branch should include at least one legitimate `feat:` commit before merge so Semantic Release produces `7.1.0`.
- `npm run publish-test -- --no-ci` can load the semantic-release configuration locally, but it will not publish from `codex/7.1-release-prep` because releases are restricted to `main`.
- Final publish should wait for CI to run full `npm run release:check` with GD and Imagick installed.
