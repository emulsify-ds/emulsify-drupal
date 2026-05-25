# Upgrading From 6.x to 7.x

Emulsify 7.x is a breaking release. Plan the upgrade as a theme-platform change, not a patch-level update.

## Known Breaking Changes

- Drupal 10 support is removed.
- Drupal 11.3+ is required.
- Drupal 12 compatibility is forward-looking until Drupal 12 beta or stable releases are available.
- The Stable9 parent theme is removed.
- Emulsify now uses `base theme: false`.
- The `drupal/components` dependency is removed.
- `drupal/emulsify_tools:^2.0` is required.
- Generated child theme frontend workflow moved from Webpack to Vite.
- Generated child themes use Emulsify Core 4.
- Whisk is now a Starterkit source and should not be enabled directly.
- Favicon handling now uses a generated package workflow.
- Favicon generation happens on theme settings save or through Emulsify Tools Drush commands, not on normal page requests.
- Hook implementations moved to Drupal 11.3+ `#[Hook]` attributes.
- Legacy 6.x procedural hook include behavior is removed.

## Requirements

- Drupal 11.3+ is supported.
- Drupal 12 forward compatibility is included through the `^11.3 || ^12` core constraint.
- Drupal core development branch coverage is experimental until Drupal 12 beta or stable releases are available.
- Drupal 10 is no longer supported in 7.x.
- `drupal/emulsify_tools:^2.0` is required by both `composer.json` and `emulsify.info.yml`.

## Package Changes

- The old `drupal/components` dependency is gone.
- Frontend workflow references should move from Webpack-based build workflow commands and docs to the Vite-based build workflow shipped in 7.x.
- `whisk` remains a starter source only. Do not enable it as a runtime parent theme.

## Twig Story Migration Audit

Generated themes include an audit command for reviewing older Twig stories that
should move to Emulsify Core's preferred `renderTwig()` helper:

```bash
npm run audit:twig-stories
```

The command prints a report of likely legacy Storybook files and links to the
Emulsify Core Storybook migration documentation. Existing Twig stories that
return HTML strings can continue rendering during the upgrade, but actively
maintained stories should be migrated to `renderTwig()` as they are touched.

## Theme Architecture Changes

- `stable9` is no longer the parent theme. Emulsify now ships its own full template layer.
- Generated child themes should keep `emulsify` as their parent theme.
- If you generated a child theme in the 6.x era, review any copied Twig overrides against the 7.x template surface before carrying them forward.
- The current template parity inventory is documented in [docs/template-map.md](docs/template-map.md).

## Recommended Upgrade Path

1. Update the site to Drupal 11.3 or a newer Drupal 11 release before moving to Emulsify 7.x.
2. Require `drupal/emulsify_tools:^2.0`.
3. Update the Emulsify parent theme to 7.x.
4. Regenerate or review custom child themes so they inherit from `emulsify`, not `stable9` or `whisk`.
5. Move frontend build and local-development docs, scripts, and team habits from Webpack-based build workflow terminology to the Vite-based build workflow.
6. Run the release-readiness checks before merge or release: `npm run release:check`. The static release gate verifies that favicon defaults, install config, and schema keys stay in sync.

## Favicon Migration Notes

- Favicon settings now store a portable sanitized SVG source in theme config.
- Emulsify Drupal owns the theme settings UI, config defaults and schema, admin previews, frontend head tags, generated asset references, and portable source storage.
- Configure or update favicons in the theme settings form for `emulsify` or an Emulsify child theme.
- Generated favicon packages are environment-local build artifacts. After config import or deploy, use Emulsify Tools to regenerate them with `drush emulsify_tools:favicon-generate [theme_name]`.
- Emulsify Tools owns the full favicon Drush command documentation for `emulsify_tools:favicon-generate`, `emulsify_tools:favicon-status`, and `emulsify_tools:favicon-reset`.
- Runtime page requests do not generate missing favicon package files.
- PNG and ICO generation require the PHP `gd` extension and the `Imagick` extension.
- Generated files, package location, source limits, and deployment expectations are documented in [docs/favicon-generation.md](docs/favicon-generation.md).
