# 7.2.1 Release Notes Draft

Status date: 2026-07-31

## Version Strategy

Latest published tag: `7.2.0`.

These child-theme generation fixes should use a conventional `fix:` commit so
Semantic Release produces the patch release `7.2.1`.

## Compatibility Notes

- Drupal compatibility remains `^11.3 || ^12`.
- Emulsify Tools 2.2 or newer is required so the Drush command delegates to
  Drupal core's Starterkit generator.
- Emulsify Core remains `^4.3.0` in newly generated child themes.
- Generated child themes continue to use `emulsify` as their runtime parent
  theme; `whisk` remains generation-only.

## Draft GitHub Release Notes

Title: `7.2.1`

### Fixed

- Preserved `project.emulsify.json` when generating a child theme through
  `drush emulsify` or `drush emulsify_tools:bake`.
- Replaced the Whisk project identity in generated metadata with the child
  theme machine name.
- Replaced all `%%EMULSIFY_*%%` tokens in the generated README and development,
  upgrading, and support guides.
- Aligned Drupal core and Drush generation so both commands produce the same
  child-theme file tree.

### Internal

- Added release smoke coverage that generates multiple child-theme identities
  through both entry points, validates their metadata and documentation, and
  compares the results.
- Updated generated-source lineage metadata to `7.2.1`.

## Draft Drupal.org Release Notes

Title: `emulsify 7.2.1`

Works with Drupal: `^11.3 || ^12`

Requires Emulsify Tools: `^2.2`

This patch fixes child-theme generation through the supported Emulsify Tools
Drush commands. Generated themes now retain project metadata with the correct
machine name and contain fully resolved project documentation. Drupal core and
Drush generation produce equivalent output.

Update Emulsify Drupal and Emulsify Tools together before generating a new child
theme. Existing generated child themes are not modified automatically.

## Validation Notes

- Run `npm run release:check -- --skip-smoke` for static validation.
- Run `npm run release:check` to exercise Drupal core and Drush generation in a
  disposable Drupal fixture.
- Run `npm run publish-test -- --no-ci` before publishing.
- Publish Emulsify Tools 2.2 before Emulsify Drupal 7.2.1 so the required
  generation implementation is available to Composer.
